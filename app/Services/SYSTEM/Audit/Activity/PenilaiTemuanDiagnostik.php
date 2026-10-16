<?php

namespace App\Services\SYSTEM\Audit\Activity;

use Illuminate\Support\Facades\DB;

/**
 * Mengubah angka menjadi TEMUAN: apa yang salah, di mana, dan kenapa itu salah.
 *
 * Tiga panel sebelumnya memberi angka. Angka tidak memberi tahu siapa pun apa
 * yang harus diperbaiki — orang (atau agen) yang membacanya tetap harus tahu
 * bahwa "kueri sama 4 kali dalam satu permintaan" itu N+1, bahwa "7.470
 * logical reads sekali jalan" itu scan, dan bahwa "sesi tidur memegang
 * transaksi" itu transaksi bocor. Pengetahuan itu ditulis di sini, sekali,
 * supaya laporannya bisa dibaca tanpa harus sudah mengerti lebih dulu.
 *
 * Tiap temuan membawa BUKTINYA — angka yang membuatnya disimpulkan — supaya
 * bisa dibantah. Penilai yang hanya berkata "ada masalah" tanpa menunjukkan
 * dasarnya membuat orang memperbaiki hal yang salah dengan yakin.
 *
 * Ambangnya sengaja longgar dan disebutkan terang-terangan di tiap temuan:
 * ambang yang tersembunyi membuat orang berdebat tentang angkanya alih-alih
 * tentang masalahnya.
 */
class PenilaiTemuanDiagnostik
{
    /** Kueri yang sama berulang sebanyak ini dalam SATU permintaan = N+1. */
    private const AMBANG_N1 = 5;

    /** Kueri sebanyak ini dalam satu permintaan sudah patut dipertanyakan. */
    private const AMBANG_KUERI_PER_PERMINTAAN = 30;

    /** Logical reads sekali eksekusi di atas ini hampir selalu scan, bukan seek. */
    private const AMBANG_SCAN = 5000;

    /** Rata-rata durasi kueri di atas ini terasa oleh orang yang menunggunya. */
    private const AMBANG_LAMBAT_MS = 500;

    /** Transaksi terbuka tanpa kueri berjalan selama ini = kemungkinan bocor. */
    private const AMBANG_TRX_MENGANGGUR_DETIK = 10;

    public function __construct(
        protected PerekamKueriPerRute $perekam,
        protected PembacaKueriTerberat $kueri,
    ) {}

    /**
     * @return list<array{
     *     berat: string, jenis: string, tempat: string,
     *     ringkas: string, bukti: string, saran: string
     * }>
     */
    public function nilai(?string $sesi = null, int $jamLock = 6): array
    {
        $temuan = array_merge(
            $this->dariRekaman($sesi),
            $this->dariPeringkatKueri(),
            $this->dariLock($jamLock),
            $this->dariDiagnostikPermintaan($jamLock),
        );

        // Diurutkan menurut berat, lalu tetap stabil supaya laporan yang sama
        // dua kali berturut-turut terbaca sama.
        $urutan = ['tinggi' => 0, 'sedang' => 1, 'rendah' => 2];
        usort($temuan, static fn ($a, $b) => [$urutan[$a['berat']] ?? 9, $a['tempat']]
            <=> [$urutan[$b['berat']] ?? 9, $b['tempat']]);

        // Satu jenis dibatasi 5 teratas. Satu pola yang berulang belasan kali
        // menenggelamkan jenis temuan lain, dan pembacanya berhenti membaca
        // jauh sebelum sampai ke yang berbeda.
        $perJenis = [];
        $disaring = [];
        foreach ($temuan as $t) {
            $perJenis[$t['jenis']] = ($perJenis[$t['jenis']] ?? 0) + 1;
            if ($perJenis[$t['jenis']] > 5) {
                continue;
            }
            $disaring[] = $t;
        }

        return $disaring;
    }

    /** Temuan dari rekaman sesi uji — satu-satunya sumber yang tahu RUTE-nya. */
    protected function dariRekaman(?string $sesi): array
    {
        $l = $this->perekam->laporan($sesi);
        $out = [];

        foreach ($l['rute'] ?? [] as $r) {
            foreach ($r['daftar'] as $d) {
                if ($d['n'] < self::AMBANG_N1) {
                    continue;
                }

                // n dibagi jumlah permintaan: 5 permintaan yang masing-masing
                // menjalankannya sekali BUKAN N+1, meski totalnya 5.
                $perPermintaan = $r['permintaan'] > 0 ? $d['n'] / $r['permintaan'] : $d['n'];
                if ($perPermintaan < self::AMBANG_N1) {
                    continue;
                }

                $out[] = [
                    'berat' => $perPermintaan >= 20 ? 'tinggi' : 'sedang',
                    'jenis' => 'N+1',
                    'tempat' => $r['rute'],
                    'ringkas' => 'Kueri yang sama dijalankan '.round($perPermintaan, 1).'x dalam satu permintaan.',
                    'bukti' => "{$d['n']}x total pada {$r['permintaan']} permintaan, {$d['ms']} ms · ".
                        'ambang '.self::AMBANG_N1.'x/permintaan · '.substr($d['sql'], 0, 120),
                    'saran' => 'Angkat kueri ini keluar dari perulangan: satu kueri berhimpun '.
                        '(whereIn / join) menggantikan N kueri per baris.',
                ];
            }

            if ($r['kueri_terbanyak'] >= self::AMBANG_KUERI_PER_PERMINTAAN) {
                $out[] = [
                    'berat' => $r['kueri_terbanyak'] >= 60 ? 'tinggi' : 'sedang',
                    'jenis' => 'kueri berlebih',
                    'tempat' => $r['rute'],
                    'ringkas' => "Satu permintaan menjalankan {$r['kueri_terbanyak']} kueri.",
                    'bukti' => "{$r['permintaan']} permintaan, {$r['kueri']} kueri, {$r['ms']} ms · ".
                        'ambang '.self::AMBANG_KUERI_PER_PERMINTAAN,
                    'saran' => 'Periksa relasi yang dimuat malas dan pemanggilan berulang di '.
                        'controller/service rute ini.',
                ];
            }
        }

        return $out;
    }

    /** Temuan dari biaya kueri sisi basis data — tak tahu rute, tetapi tahu ongkos. */
    protected function dariPeringkatKueri(): array
    {
        $out = [];

        foreach (($this->kueri->ambil('cpu', 30)['baris'] ?? []) as $b) {
            $sql = substr($b['sql_text'], 0, 120);
            $objek = $b['objek'] !== '(ad-hoc)' ? $b['objek'] : 'ad-hoc';

            if ($b['baca_per_eksekusi'] >= self::AMBANG_SCAN) {
                $out[] = [
                    'berat' => $b['baca_per_eksekusi'] >= 50000 ? 'tinggi' : 'sedang',
                    'jenis' => 'scan',
                    'tempat' => $objek,
                    'ringkas' => number_format($b['baca_per_eksekusi']).' logical reads sekali jalan.',
                    'bukti' => "{$b['eksekusi']}x eksekusi, CPU ".number_format($b['cpu_ms']).' ms · '.
                        'ambang '.number_format(self::AMBANG_SCAN).' · '.$sql,
                    'saran' => 'Sebanyak itu berarti membaca banyak halaman untuk sedikit baris — '.
                        'biasanya indeks yang tak terpakai, atau predikat yang tak sargable.',
                ];
            }

            if ($b['rata_ms'] >= self::AMBANG_LAMBAT_MS) {
                $out[] = [
                    'berat' => $b['rata_ms'] >= 2000 ? 'tinggi' : 'sedang',
                    'jenis' => 'kueri lambat',
                    'tempat' => $objek,
                    'ringkas' => "Rata-rata {$b['rata_ms']} ms sekali jalan.",
                    'bukti' => "{$b['eksekusi']}x eksekusi, maks {$b['durasi_maks_ms']} ms · ".
                        'ambang '.self::AMBANG_LAMBAT_MS.' ms · '.$sql,
                    'saran' => 'Selama ini menahan satu koneksi. Pada beban ramai, koneksi yang '.
                        'tertahan menumpuk lebih cepat daripada yang dilepas.',
                ];
            }
        }

        return $out;
    }

    /** Temuan dari rekaman lock — satu-satunya yang tahu siapa menahan siapa. */
    protected function dariLock(int $jam): array
    {
        try {
            $baris = DB::table('N_HRIS_DB_Health_Snapshot')
                ->where('Captured_At', '>=', now()->subHours($jam))
                ->whereNotNull('Blocking_Session_Id')
                ->where('Blocking_Session_Id', '<>', 0)
                ->orderByDesc('Wait_Time_Ms')
                ->limit(200)
                ->get(['Captured_At', 'Session_Id', 'Blocking_Session_Id', 'Wait_Type', 'Wait_Time_Ms', 'Context_Info', 'Sql_Text']);
        } catch (\Throwable) {
            return [];
        }

        $perObjek = [];
        foreach ($baris as $b) {
            $rute = str_contains((string) $b->Context_Info, '|')
                ? explode('|', (string) $b->Context_Info, 2)[1]
                : ('sesi #'.$b->Session_Id);
            $perObjek[$rute] ??= ['n' => 0, 'maks' => 0, 'wait' => '', 'sql' => ''];
            $perObjek[$rute]['n']++;
            if ((int) $b->Wait_Time_Ms > $perObjek[$rute]['maks']) {
                $perObjek[$rute]['maks'] = (int) $b->Wait_Time_Ms;
                $perObjek[$rute]['wait'] = (string) $b->Wait_Type;
                $perObjek[$rute]['sql'] = substr((string) $b->Sql_Text, 0, 120);
            }
        }

        $out = [];
        foreach ($perObjek as $rute => $d) {
            $out[] = [
                'berat' => $d['maks'] >= 10000 ? 'tinggi' : 'sedang',
                'jenis' => 'tertahan lock',
                'tempat' => $rute,
                'ringkas' => "Tertahan sampai {$d['maks']} ms ({$d['wait']}).",
                'bukti' => "{$d['n']} sampel memuatnya dalam {$jam} jam terakhir · ".$d['sql'],
                'saran' => 'Cari penahannya, bukan korbannya. Bila penahannya menganggur, '.
                    'yang bocor transaksinya — biasanya panggilan lambat di dalam transaksi.',
            ];
        }

        return $out;
    }

    /** Temuan dari diagnostik per-permintaan: koneksi yang gagal diambil. */
    protected function dariDiagnostikPermintaan(int $jam): array
    {
        try {
            $baris = DB::table('N_HRIS_Request_Diagnostics')
                ->where('Created_At', '>=', now()->subHours($jam))
                ->orderByDesc('Total_Ms')
                ->limit(100)
                ->get(['Path', 'Method', 'Http_Status', 'Total_Ms', 'Conn_Acquire_Ms', 'Db_Query_Count']);
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($baris as $b) {
            // Lama TETAPI tanpa satu kueri pun = waktunya habis SEBELUM sempat
            // bertanya ke basis data. Itu tanda koneksinya yang tak didapat,
            // bukan kuerinya yang lambat — dan keduanya menuntut perbaikan
            // yang sama sekali berbeda.
            if ((int) $b->Db_Query_Count === 0 && (int) $b->Total_Ms >= 3000) {
                $out[] = [
                    'berat' => 'tinggi',
                    'jenis' => 'gagal ambil koneksi',
                    'tempat' => $b->Method.' /'.ltrim((string) $b->Path, '/'),
                    'ringkas' => "{$b->Total_Ms} ms tanpa menjalankan satu kueri pun.",
                    'bukti' => "status {$b->Http_Status}, Conn_Acquire ".($b->Conn_Acquire_Ms ?? '-').' ms',
                    'saran' => 'Bukan kuerinya yang lambat — pool koneksinya yang habis. '.
                        'Cari siapa yang menahan koneksi lama, bukan kueri di rute ini.',
                ];
            }
        }

        return $out;
    }
}
