<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Alur berlangkah satu siklus: ada di langkah mana, apa berikutnya, apa yang menghalangi.
 *
 * KENAPA ADA
 *
 * Sampai 3 September 2026 pengelolaan siklus adalah sembilan dialog pada satu layar, tanpa
 * urutan yang terlihat. Admin menjalankannya satu atau dua kali setahun dan tidak mungkin
 * mengingat apa yang datang sesudah apa — jadi ia menekan tombol, ditolak 422, lalu
 * menyimpulkan sistemnya rusak. Yang kurang bukan kemampuan, melainkan URUTAN yang
 * ditunjukkan.
 *
 * LANGKAH AKTIF DITURUNKAN DARI POSISI, BUKAN DARI KELENGKAPAN ISI
 *
 * Ini aturan yang sudah dibayar mahal di modul SOP. Wizard versi dokumen dulu memilih
 * langkah dengan mencari "langkah pertama yang isinya belum lengkap"; akibatnya layar
 * melompat setiap kali menyimpan, dan penggunanya melaporkannya sebagai "tersimpan tapi
 * selalu balik ke Review" — terdengar seperti cacat penyimpanan, padahal murni pemilih
 * langkah.
 *
 * Karena itu di sini langkah aktif dihitung SEMATA dari `Status_Cycle`, yang memang posisi
 * resmi siklus dan yang sama dipakai gerbang untuk mengizinkan operasi. Kelengkapan isi
 * tetap dilaporkan — sebagai `penghalang` — tetapi TIDAK PERNAH dipakai memilih langkah.
 *
 * PENGHALANG DISEBUT, BUKAN DISIMPULKAN LAYAR
 *
 * Tiap langkah membawa daftar penghalangnya sendiri beserta kalimat yang bisa dibaca.
 * Layar yang menyimpulkan sendiri "kenapa tombol ini mati" akan menyimpulkannya berbeda
 * dari yang ditegakkan server, dan perbedaan itu baru terlihat ketika seseorang menekan
 * tombolnya.
 */
class PaCycleStepService
{
    public const SELESAI = 'SELESAI';

    public const BERJALAN = 'BERJALAN';

    public const TERKUNCI = 'TERKUNCI';

    /**
     * Langkah, berurutan, beserta status siklus tempat masing-masing BERJALAN.
     *
     * Satu status memetakan ke tepat satu langkah. Status yang memetakan ke dua langkah
     * berarti ada keadaan tempat layar tidak bisa menjawab "saya di mana" — dan itu
     * persis pertanyaan yang alur ini ada untuk menjawabnya.
     */
    private const LANGKAH = [
        ['kode' => 'JADWAL', 'nama' => 'Jadwal Siklus', 'status' => []],
        ['kode' => 'PESERTA', 'nama' => 'Siapkan Peserta', 'status' => ['DRAFT']],
        ['kode' => 'PENILAI', 'nama' => 'Tugaskan Penilai', 'status' => ['PREPARED']],
        ['kode' => 'PENGISIAN', 'nama' => 'Pengisian', 'status' => ['LOCKED', 'OPEN']],
        ['kode' => 'HASIL', 'nama' => 'Hasil & Penerbitan', 'status' => ['CLOSED', 'PUBLISHED']],
    ];

    public function __construct(
        private readonly PaCycleGate $gate,

        /* Satu-satunya penulis aturan "mana pengecualian yang diputuskan orang".
           Disuntikkan supaya ketergantungannya terbaca dari luar; layanan yang diam-diam
           menyentuh layanan lain adalah yang paling sulit ditelusuri saat perilakunya
           berubah. */
        private readonly PaCyclePreparationService $penyiapan,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function alur(int $cycleId): array
    {
        $siklus = DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->first([
                'Id_PA_Cycle', 'Kode_Cycle', 'Nama_Cycle', 'Tahun', 'Status_Cycle',
                'Periode_Dinilai_Dari', 'Periode_Dinilai_Sampai',
                'Pengisian_Dari', 'Pengisian_Sampai', 'Locked_At', 'Published_At',
            ]);

        if ($siklus === null) {
            throw new RuntimeException("Siklus {$cycleId} tidak ditemukan.");
        }

        $status = (string) $siklus->Status_Cycle;
        $aktif = $this->nomorLangkahAktif($status);
        $cacah = $this->cacah($cycleId);
        $penghalang = $this->penghalang($cycleId, $siklus, $cacah);

        $langkah = [];

        foreach (self::LANGKAH as $i => $l) {
            $nomor = $i + 1;

            $langkah[] = [
                'nomor' => $nomor,
                'kode' => $l['kode'],
                'nama' => $l['nama'],
                /* Tiga keadaan, dan bedanya penting bagi layar: SELESAI boleh dibuka untuk
                   ditinjau, BERJALAN adalah tempat orang berada sekarang, TERKUNCI belum
                   boleh disentuh sama sekali. Menyamakan dua yang pertama membuat orang
                   mengira bisa mengulang langkah yang sudah beku. */
                'keadaan' => $nomor < $aktif ? self::SELESAI
                    : ($nomor === $aktif ? self::BERJALAN : self::TERKUNCI),
                'keterangan' => $this->keterangan($l['kode'], $siklus, $cacah),
                'aksi_utama' => $nomor === $aktif ? $this->aksiUtama($l['kode'], $status) : null,
                /* Penghalang HANYA untuk langkah yang sedang berjalan. Menampilkannya pada
                   langkah terkunci akan membanjiri layar dengan keluhan tentang pekerjaan
                   yang memang belum waktunya dikerjakan. */
                'penghalang' => $nomor === $aktif ? ($penghalang[$l['kode']] ?? []) : [],
            ];
        }

        return [
            'siklus' => [
                'cycle_id' => (int) $siklus->Id_PA_Cycle,
                'kode_cycle' => $siklus->Kode_Cycle,
                'nama_cycle' => $siklus->Nama_Cycle,
                'tahun' => (int) $siklus->Tahun,
                'status_cycle' => $status,
            ],
            'langkah_aktif' => $aktif,
            'total_langkah' => count(self::LANGKAH),
            'langkah' => $langkah,
            'cacah' => $cacah,
            /* Dikirim juga secara utuh supaya layar tidak perlu menyimpulkan tombol mana
               yang hidup dari status — jawaban gerbang dan jawaban layar wajib sama. */
            'operasi_boleh' => $this->gate->operasiUntukStatus($status),
        ];
    }

    /* ------------------------------------------------------------------ *
     * Posisi                                                              *
     * ------------------------------------------------------------------ */

    /**
     * Nomor langkah yang sedang berjalan — SEMATA dari status siklus.
     *
     * Tidak melihat kelengkapan isi sedikit pun. Itu bukan penyederhanaan melainkan
     * seluruh maksudnya: dua sumber kebenaran untuk "saya di langkah berapa" pasti
     * menyimpang, dan menyimpangnya baru terlihat ketika orang menemukan dirinya di
     * langkah yang salah.
     */
    private function nomorLangkahAktif(string $status): int
    {
        foreach (self::LANGKAH as $i => $l) {
            if (in_array($status, $l['status'], true)) {
                return $i + 1;
            }
        }

        // Status tak dikenal — mendarat di langkah pertama, bukan melempar. Siklus yang
        // statusnya aneh tetap harus bisa dibuka dan diperbaiki orang.
        return 1;
    }

    /* ------------------------------------------------------------------ *
     * Isi tiap langkah                                                    *
     * ------------------------------------------------------------------ */

    /** @return array<string, int> */
    private function cacah(int $cycleId): array
    {
        $peserta = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->groupBy('Status_Peserta')
            ->selectRaw('Status_Peserta as s, COUNT(*) as n')
            ->pluck('n', 's');

        $diikutkan = (int) ($peserta['DIIKUTKAN'] ?? 0);

        $berpenilai = (int) DB::table('N_PA_Participant as p')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('p.Flag_Aktif', 'Y')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))->from('N_PA_Assignment as a')
                    ->whereColumn('a.PA_Participant_Id', 'p.Id_PA_Participant')
                    ->where('a.Flag_Aktif', 'Y');
            })
            ->count();

        $hasil = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('r.Flag_Aktif', 'Y')
            ->groupBy('r.Status_Hasil')
            ->selectRaw('r.Status_Hasil as s, COUNT(*) as n')
            ->pluck('n', 's');

        return [
            'peserta_diikutkan' => $diikutkan,
            /* Hanya yang diputuskan orang — aturannya milik
               `PaCyclePreparationService::cacahDikecualikan()`. */
            'peserta_dikecualikan' => $this->penyiapan->cacahDikecualikan([$cycleId])[$cycleId]['manual'] ?? 0,
            'peserta_tertunda' => (int) ($peserta['TERTUNDA'] ?? 0),
            'peserta_berpenilai' => $berpenilai,
            'peserta_tanpa_penilai' => max(0, $diikutkan - $berpenilai),
            'hasil_dihitung' => (int) ($hasil['DIHITUNG'] ?? 0),
            'hasil_tertahan' => (int) ($hasil['TERTAHAN'] ?? 0),
            'hasil_terbit' => (int) ($hasil['TERBIT'] ?? 0),
            'hasil_belum' => (int) ($hasil['BELUM_DIHITUNG'] ?? 0),
        ];
    }

    /** @param array<string, int> $c */
    private function keterangan(string $kode, object $s, array $c): string
    {
        return match ($kode) {
            'JADWAL' => sprintf(
                'Periode dinilai %s s.d. %s; pengisian %s s.d. %s.',
                $s->Periode_Dinilai_Dari ?? 'belum diisi',
                $s->Periode_Dinilai_Sampai ?? 'belum diisi',
                $s->Pengisian_Dari ?? 'belum diisi',
                $s->Pengisian_Sampai ?? 'belum diisi',
            ),
            'PESERTA' => $c['peserta_diikutkan'] === 0 && $c['peserta_dikecualikan'] === 0
                ? 'Peserta belum dibentuk.'
                : sprintf('%d diikutkan, %d dikecualikan, %d tertunda.',
                    $c['peserta_diikutkan'], $c['peserta_dikecualikan'], $c['peserta_tertunda']),
            'PENILAI' => sprintf('%d dari %d peserta sudah punya penilai.',
                $c['peserta_berpenilai'], $c['peserta_diikutkan']),
            'PENGISIAN' => $s->Locked_At === null
                ? 'Aturan penilaian belum dibekukan.'
                : sprintf('Aturan dibekukan %s.', $s->Locked_At),
            'HASIL' => sprintf('%d terbit, %d dihitung, %d tertahan, %d belum dihitung.',
                $c['hasil_terbit'], $c['hasil_dihitung'], $c['hasil_tertahan'], $c['hasil_belum']),
            default => '',
        };
    }

    /**
     * Aksi utama langkah berjalan — SATU, bukan daftar tombol.
     *
     * Nama dan operasinya datang dari server supaya layar tidak menerjemahkan sendiri
     * status menjadi tombol. `operasi` dicocokkan dengan `operasi_boleh`, jadi tombolnya
     * mati untuk alasan yang sama persis dengan yang akan menolaknya bila ditekan.
     *
     * @return array{label:string, operasi:string|null, alamat:string|null}
     */
    private function aksiUtama(string $kode, string $status): array
    {
        return match ($kode) {
            'JADWAL' => ['label' => 'Sunting Jadwal', 'operasi' => null, 'alamat' => 'sunting-siklus'],
            'PESERTA' => ['label' => 'Siapkan Peserta', 'operasi' => PaCycleGate::SUNTING_SALINAN, 'alamat' => 'siapkan'],
            'PENILAI' => ['label' => 'Tugaskan Penilai', 'operasi' => PaCycleGate::BUAT_PENUGASAN, 'alamat' => 'penugasan'],
            'PENGISIAN' => $status === 'OPEN'
                ? ['label' => 'Tutup Pengisian', 'operasi' => PaCycleGate::TUTUP_PENGISIAN, 'alamat' => 'tutup-pengisian']
                : ['label' => 'Buka Pengisian', 'operasi' => PaCycleGate::BUKA_PENGISIAN, 'alamat' => 'buka-pengisian'],
            'HASIL' => ['label' => 'Hitung Hasil', 'operasi' => PaCycleGate::HITUNG_HASIL, 'alamat' => 'hitung'],
            default => ['label' => '', 'operasi' => null, 'alamat' => null],
        };
    }

    /**
     * Apa yang menghalangi langkah berjalan, dalam kalimat yang bisa dibaca.
     *
     * Kosong berarti langkahnya siap diselesaikan. Isi berarti ada yang harus dibereskan
     * lebih dulu — dan menyebutkannya di sini jauh lebih berguna daripada membiarkan orang
     * menekan tombol lalu menerima 422 yang harus ia terjemahkan sendiri.
     *
     * @param  array<string, int>  $c
     * @return array<string, list<string>>
     */
    private function penghalang(int $cycleId, object $s, array $c): array
    {
        $p = ['JADWAL' => [], 'PESERTA' => [], 'PENILAI' => [], 'PENGISIAN' => [], 'HASIL' => []];

        if ($s->Periode_Dinilai_Dari === null || $s->Periode_Dinilai_Sampai === null) {
            $p['JADWAL'][] = 'Periode dinilai belum diisi. Nilai KPI diambil atas rentang ini, '
                .'jadi perhitungan tidak bisa berjalan tanpanya.';
        }

        if ($c['peserta_diikutkan'] === 0 && $c['peserta_dikecualikan'] === 0) {
            $p['PESERTA'][] = 'Peserta belum dibentuk. Jalankan Siapkan Peserta.';
        }

        if ($c['peserta_tertunda'] > 0) {
            $p['PENILAI'][] = sprintf(
                '%d peserta masih TERTUNDA — levelnya gagal diresolusi atau tidak punya '
                .'aturan penilai. Siklus tidak bisa dikunci selama keranjang ini terisi.',
                $c['peserta_tertunda'],
            );
        }

        if ($c['peserta_tanpa_penilai'] > 0) {
            $p['PENILAI'][] = sprintf(
                '%d peserta belum punya satu penilai pun. Hasil mereka pasti tertahan.',
                $c['peserta_tanpa_penilai'],
            );
        }

        if ($s->Pengisian_Dari === null || $s->Pengisian_Sampai === null) {
            $p['PENGISIAN'][] = 'Jendela pengisian belum diisi. Tanpa itu penugasan lahir '
                .'tanpa tenggat, dan penilai yang tidak mengisi tidak akan pernah tercatat '
                .'terlambat.';
        }

        if ($c['hasil_tertahan'] > 0) {
            $p['HASIL'][] = sprintf(
                '%d hasil TERTAHAN. Tertahan bukan kegagalan — mesin menolak mengarang '
                .'angka — tetapi penerbitan tidak bisa ditutup selama masih ada yang tersisa.',
                $c['hasil_tertahan'],
            );
        }

        return $p;
    }
}
