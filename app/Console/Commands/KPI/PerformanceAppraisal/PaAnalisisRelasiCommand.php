<?php

namespace App\Console\Commands\KPI\PerformanceAppraisal;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Menimbang calon sumber relasi organisasi untuk Performance Appraisal — TANPA MENULIS.
 *
 * KENAPA PERINTAH INI ADA
 *
 * `N_HRIS_Organization_Relationship` berisi nol baris, dan tidak ada satu pun jalur tulis
 * ke sana di seluruh aplikasi: tak ada layar, tak ada perintah, tak ada DML seed. Yang
 * membacanya hanya `PaAssignmentBuilderService`. Akibatnya usulan penilai untuk ATASAN,
 * PEER, dan BAWAHAN selalu kosong, dan penugasan `USULAN` selain SELF ditolak karena wajib
 * menunjuk relasi asal — sehingga hanya penilaian diri yang bisa terbentuk.
 *
 * Rancangannya (`docs/superpowers/specs/2026-08-29-performance-appraisal-organization-model.md`)
 * sudah menandai ini sebagai pertanyaan terbuka O01 (siapa pemilik dan penulisnya) dan O06
 * (bolehkah approval flow jadi seed) — dengan catatan tegas: **tidak boleh blind import**.
 *
 * Perintah ini adalah cara menghormati catatan itu. Ia memperlihatkan apa yang AKAN lahir
 * dari tiap sumber, berikut yang mencurigakan dan yang saling bertentangan, supaya
 * keputusannya diambil di atas angka dan contoh — bukan di atas dugaan.
 *
 * TIDAK MENULIS SATU BARIS PUN. Itu bukan kehati-hatian berlebihan: relasi yang salah
 * menghasilkan orang dinilai oleh atasan yang keliru, dengan hasil yang tetap terlihat
 * wajar. Kesalahan yang tidak menampakkan diri adalah kesalahan yang paling mahal.
 */
class PaAnalisisRelasiCommand extends Command
{
    protected $signature = 'pa:analisis-relasi
                            {--sumber= : Batasi ke satu sumber: tim, hris, kpi}
                            {--contoh=10 : Berapa baris contoh yang ditampilkan per sumber}';

    protected $description = 'Menimbang calon sumber relasi organisasi PA. Read-only, tidak menulis apa pun.';

    /**
     * Calon sumber, beserta bentuk kolomnya.
     *
     * Ketiganya menyimpan gagasan yang sama dengan nama yang berbeda — dan salah satunya
     * memakai nama yang MENYESATKAN: `Kode_Karyawan_Team` di `HRIS_Karyawan_Team` berarti
     * LEADER, sedangkan `Kode_Karyawan_Team` di `KPI_Karyawan_Team` berarti ANGGOTA.
     * Terbalik. Karena itu pemetaannya ditulis eksplisit di sini alih-alih disimpulkan
     * dari nama kolomnya.
     */
    private const SUMBER = [
        'tim' => [
            'nama' => 'HRIS_Karyawan_Team',
            'tabel' => 'HRIS_Karyawan_Team',
            'atasan' => 'Kode_Karyawan_Team',
            'bawahan' => 'Kode_Karyawan',
            'saring_status' => null,
            'catatan' => 'Sumber "tim" yang dipakai Leader Dashboard, SOP, shift, dan lembur.',
        ],
        'hris' => [
            'nama' => 'HRIS_Approval_Flow',
            'tabel' => 'HRIS_Approval_Flow',
            'atasan' => 'Kode_Karyawan',
            'bawahan' => 'Kode_Karyawan_Requester',
            'saring_status' => 'Status',
            'catatan' => 'Alur persetujuan umum (cuti, izin, shift).',
        ],
        'kpi' => [
            'nama' => 'KPI_Approval_Flow_Performance',
            'tabel' => 'KPI_Approval_Flow_Performance',
            'atasan' => 'Kode_Karyawan_Approver',
            'bawahan' => 'Kode_Karyawan_Requester',
            'saring_status' => 'Status',
            'catatan' => 'Alur persetujuan dokumen KPI. Inilah O06 pada rancangan.',
        ],
    ];

    public function handle(): int
    {
        $this->info('ANALISIS CALON SUMBER RELASI ORGANISASI — PERFORMANCE APPRAISAL');
        $this->line(str_repeat('=', 78));
        $this->newLine();
        $this->warn('  Perintah ini TIDAK MENULIS satu baris pun. Seluruhnya SELECT.');
        $this->newLine();

        $aktif = (int) DB::table('Karyawan')->where('Aktif', 'Y')->count();
        $terpasang = (int) DB::table('N_HRIS_Organization_Relationship')->count();

        $this->line("  Karyawan aktif                          : {$aktif}");
        $this->line("  Baris N_HRIS_Organization_Relationship  : {$terpasang}");

        if ($terpasang > 0) {
            $this->newLine();
            $this->warn(
                '  Tabel relasi SUDAH BERISI. Angka di bawah menggambarkan apa yang akan '
                .'lahir dari sumber, bukan selisih terhadap yang sudah ada.'
            );
        }

        $pilih = $this->option('sumber');
        $daftar = $pilih === null ? self::SUMBER : array_intersect_key(self::SUMBER, [$pilih => true]);

        if ($daftar === []) {
            $this->error('  Sumber tidak dikenal. Pilihannya: tim, hris, kpi.');

            return self::FAILURE;
        }

        $ringkas = [];

        foreach ($daftar as $kunci => $s) {
            $ringkas[$kunci] = $this->periksaSumber($kunci, $s, $aktif);
        }

        if (count($daftar) > 1) {
            $this->bandingkanSumber();
        }

        $this->newLine();
        $this->line(str_repeat('=', 78));
        $this->info('  Tidak ada yang ditulis. Keputusan sumber ada pada pemilik proses.');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $s
     * @return array<string, int>
     */
    private function periksaSumber(string $kunci, array $s, int $aktif): array
    {
        $this->newLine();
        $this->line(str_repeat('-', 78));
        $this->info("  SUMBER: {$s['nama']}  (--sumber={$kunci})");
        $this->line('  '.$s['catatan']);
        $this->newLine();

        $dasar = fn () => $this->kueriDasar($s);

        $barisTotal = (int) $dasar()->count();

        // Berapa karyawan aktif yang akan punya ATASAN.
        $punyaAtasan = (int) $dasar()
            ->join('Karyawan as kb', 'kb.Kode_Karyawan', '=', $s['tabel'].'.'.$s['bawahan'])
            ->where('kb.Aktif', 'Y')
            ->distinct()
            ->count($s['tabel'].'.'.$s['bawahan']);

        /* PEER diturunkan, tidak disimpan: dua orang beratasan sama adalah rekan. Jumlahnya
           tumbuh KUADRATIK terhadap besar kelompok — satu atasan dengan 57 bawahan
           melahirkan 3.192 pasangan rekan sendirian. Itu sebabnya angka ini ditampilkan
           terpisah dan disertai kelompok terbesarnya. */
        $kelompok = $dasar()
            ->join('Karyawan as kb', 'kb.Kode_Karyawan', '=', $s['tabel'].'.'.$s['bawahan'])
            ->where('kb.Aktif', 'Y')
            ->groupBy($s['tabel'].'.'.$s['atasan'])
            ->selectRaw($s['tabel'].'.'.$s['atasan'].' as atasan, COUNT(DISTINCT '.$s['tabel'].'.'.$s['bawahan'].') as n')
            ->get();

        $pasanganPeer = 0;
        $terbesar = 0;

        foreach ($kelompok as $k) {
            $n = (int) $k->n;
            $pasanganPeer += $n * ($n - 1);
            $terbesar = max($terbesar, $n);
        }

        $persen = $aktif === 0 ? 0 : round($punyaAtasan / $aktif * 100, 1);

        $this->line('  YANG AKAN LAHIR');
        $this->line(sprintf('    baris sumber terpakai       : %d', $barisTotal));
        $this->line(sprintf('    ATASAN                      : %d  (cakupan %s%% dari %d karyawan aktif)', $punyaAtasan, $persen, $aktif));
        $this->line(sprintf('    BAWAHAN (kebalikannya)      : %d', $punyaAtasan));
        $this->line(sprintf('    PEER (diturunkan, berarah)  : %d  — kelompok terbesar %d orang', $pasanganPeer, $terbesar));

        if ($terbesar >= 20) {
            $this->newLine();
            $this->warn(sprintf(
                '    Kelompok terbesar berisi %d orang. "Rekan kerja" sebanyak itu tidak '
                .'bermakna sebagai penilai — aturan penilai hanya meminta 2 per orang, jadi '
                .'pemilihannya akan jatuh pada urutan, bukan pada relevansi.',
                $terbesar,
            ));
        }

        $this->newLine();
        $this->line('  YANG MENCURIGAKAN');

        $anomali = $this->anomali($s);

        foreach ($anomali as $label => $n) {
            $this->line(sprintf('    %-34s : %s', $label, $n === 0 ? '0' : "<comment>{$n}</comment>"));
        }

        $this->contoh($s);

        return ['atasan' => $punyaAtasan, 'peer' => $pasanganPeer] + $anomali;
    }

    /**
     * @param  array<string, mixed>  $s
     * @return array<string, int>
     */
    private function anomali(array $s): array
    {
        $t = $s['tabel'];
        $a = $t.'.'.$s['atasan'];
        $b = $t.'.'.$s['bawahan'];

        /* Atasan yang sudah tidak aktif. Ia tidak akan pernah mengisi, dan penugasannya
           hanya memindahkan kekosongan ke tahap berikutnya — hasil peserta ditahan karena
           kategori atasannya tidak memenuhi ambang. */
        $nonaktif = (int) $this->kueriDasar($s)
            ->leftJoin('Karyawan as ka', 'ka.Kode_Karyawan', '=', DB::raw("$a"))
            ->where(function ($q) {
                $q->whereNull('ka.Kode_Karyawan')->orWhere('ka.Aktif', '<>', 'Y');
            })
            ->count();

        // Atasan dirinya sendiri: relasi yang tidak berarti apa-apa dan akan menghasilkan
        // penilaian diri kedua yang menyamar sebagai penilaian atasan.
        $diriSendiri = (int) $this->kueriDasar($s)->whereColumn($a, '=', $b)->count();

        /* Punya lebih dari satu atasan. Bukan salah, tetapi HARUS diputuskan: aturan
           penilai meminta satu ATASAN, jadi seseorang harus memilih yang mana — dan kalau
           tak ada yang memilih, urutan baris yang memutuskan. */
        /* Dihitung di PHP, bukan lewat subkueri. Hasilnya paling banyak beberapa puluh
           baris — membungkusnya jadi turunan hanya menambah kerumitan yang harus dibaca
           orang lain, tanpa menghemat apa pun. */
        $berganda = $this->kueriDasar($s)
            ->groupBy(DB::raw($b))
            ->havingRaw('COUNT(DISTINCT '.$a.') > 1')
            ->selectRaw($b.' as x')
            ->get()
            ->count();

        return [
            'atasan tidak aktif / tak dikenal' => $nonaktif,
            'atasan = dirinya sendiri' => $diriSendiri,
            'punya lebih dari satu atasan' => $berganda,
        ];
    }

    /** @param array<string, mixed> $s */
    private function contoh(array $s): void
    {
        $n = max(0, (int) $this->option('contoh'));

        if ($n === 0) {
            return;
        }

        $t = $s['tabel'];

        $baris = $this->kueriDasar($s)
            ->join('Karyawan as kb', 'kb.Kode_Karyawan', '=', $t.'.'.$s['bawahan'])
            ->leftJoin('Karyawan as ka', 'ka.Kode_Karyawan', '=', $t.'.'.$s['atasan'])
            ->where('kb.Aktif', 'Y')
            // Urutan disebut eksplisit: tanpa ORDER BY, SQL Server tidak menjamin baris
            // mana yang muncul, sehingga "contoh" berubah tiap dijalankan tanpa sebab.
            ->orderBy('kb.Kode_Karyawan')
            ->limit($n)
            ->get([
                'kb.Kode_Karyawan as bawahan', 'kb.Nama as nama_bawahan',
                DB::raw($t.'.'.$s['atasan'].' as atasan'),
                'ka.Nama as nama_atasan', 'ka.Aktif as atasan_aktif',
            ]);

        if ($baris->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->line("  CONTOH ({$baris->count()} baris pertama menurut kode karyawan)");

        foreach ($baris as $r) {
            $tanda = $r->atasan_aktif === 'Y' ? '' : '  <comment>[atasan tidak aktif]</comment>';
            $this->line(sprintf(
                '    %-8s %-28s <- atasan %-8s %s%s',
                $r->bawahan,
                mb_substr((string) $r->nama_bawahan, 0, 28),
                $r->atasan,
                mb_substr((string) ($r->nama_atasan ?? 'TIDAK DIKENAL'), 0, 24),
                $tanda,
            ));
        }
    }

    /**
     * Di mana dua sumber TIDAK SEPAKAT tentang siapa atasan seseorang.
     *
     * Ini pemeriksaan yang paling menentukan, dan satu-satunya yang tidak bisa dilihat dari
     * satu sumber saja. Kalau dua sumber sepakat, keduanya kemungkinan besar memang
     * menggambarkan struktur organisasi. Kalau berselisih, setidaknya satu di antaranya
     * menggambarkan sesuatu yang LAIN — dan approval flow memang menjawab pertanyaan yang
     * berbeda: "siapa menyetujui dokumennya", bukan "siapa atasannya".
     */
    private function bandingkanSumber(): void
    {
        $this->newLine();
        $this->line(str_repeat('-', 78));
        $this->info('  KECOCOKAN ANTAR-SUMBER');
        $this->line('  Diperiksa pada orang yang punya atasan di KEDUA sumber.');
        $this->newLine();

        $pasangan = [['tim', 'kpi'], ['hris', 'kpi'], ['tim', 'hris']];

        foreach ($pasangan as [$x, $y]) {
            $a = self::SUMBER[$x];
            $b = self::SUMBER[$y];

            $kiri = $this->petaAtasan($a);
            $kanan = $this->petaAtasan($b);

            $irisan = array_intersect_key($kiri, $kanan);

            if ($irisan === []) {
                $this->line(sprintf('    %-30s vs %-30s : tidak ada irisan', $a['nama'], $b['nama']));

                continue;
            }

            $sepakat = 0;

            foreach ($irisan as $orang => $atasanKiri) {
                if (array_intersect($atasanKiri, $kanan[$orang]) !== []) {
                    $sepakat++;
                }
            }

            $total = count($irisan);
            $persen = round($sepakat / $total * 100, 1);

            $this->line(sprintf(
                '    %-30s vs %-30s : %d/%d sepakat (%s%%)',
                $a['nama'], $b['nama'], $sepakat, $total, $persen,
            ));

            if ($persen < 80.0) {
                $this->warn(sprintf(
                    '      Selisih %d orang. Dua sumber yang berbeda sebanyak ini tidak '
                    .'mungkin dua-duanya menggambarkan struktur organisasi.',
                    $total - $sepakat,
                ));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $s
     * @return array<string, list<string>>
     */
    private function petaAtasan(array $s): array
    {
        $t = $s['tabel'];

        $baris = $this->kueriDasar($s)
            ->join('Karyawan as kb', 'kb.Kode_Karyawan', '=', $t.'.'.$s['bawahan'])
            ->where('kb.Aktif', 'Y')
            ->get([
                DB::raw($t.'.'.$s['bawahan'].' as bawahan'),
                DB::raw($t.'.'.$s['atasan'].' as atasan'),
            ]);

        $peta = [];

        foreach ($baris as $r) {
            $peta[(string) $r->bawahan][] = (string) $r->atasan;
        }

        return $peta;
    }

    /**
     * Kueri dasar satu sumber, sudah disaring baris yang tidak berlaku.
     *
     * `Status IS NULL` berarti aktif pada kedua tabel approval flow — nilai lain berarti
     * dibatalkan. `HRIS_Karyawan_Team` tidak punya kolom status sama sekali, jadi
     * penyaringannya hanya lewat keaktifan karyawan.
     */
    private function kueriDasar(array $s): \Illuminate\Database\Query\Builder
    {
        $q = DB::table($s['tabel']);

        if ($s['saring_status'] !== null) {
            $q->whereNull($s['tabel'].'.'.$s['saring_status']);
        }

        // Baris yang memasangkan orang dengan dirinya sendiri tetap DIHITUNG di anomali,
        // jadi tidak disaring di sini — menyaringnya akan menyembunyikan temuan.
        return $q;
    }
}
