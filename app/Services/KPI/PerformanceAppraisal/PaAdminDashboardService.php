<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Dashboard admin dan monitoring progres satu siklus.
 *
 * SATU ATURAN "TERLAMBAT", DUA TEMPAT MENGHITUNGNYA
 *
 * Keterlambatan per baris dihitung PHP (`PaPesertaService`, `PaParticipantReadService`);
 * di sini ia harus dihitung SQL, karena mencacah 958 peserta berarti membaca semuanya ke
 * memori hanya untuk menjumlahkan. Dua tempat untuk satu gagasan adalah persis bentuk
 * yang suatu hari berselisih — dan yang berselisih di sini adalah angka yang dipakai
 * mengejar orang.
 *
 * Karena itu aturannya diletakkan di SATU method, `syaratTerlambat()`, dan padanan
 * PHP-nya disebut namanya di sana. `Due_At` bertipe DATE dan berarti SAMPAI AKHIR hari
 * itu, jadi ambangnya `Due_At < hari ini` — bukan `<=`, dan bukan perbandingan waktu.
 *
 * SELURUHNYA BERBASIS HIMPUNAN
 *
 * Tidak ada satu pun perulangan yang menjalankan kueri. Siklus penuh berisi hampir
 * seribu peserta dan beberapa ribu penugasan; sekali saja ada kueri di dalam perulangan,
 * halaman ini berhenti bisa dibuka pada siklus yang sebenarnya.
 */
class PaAdminDashboardService
{
    public const MAKS_PER_HALAMAN = 100;

    public function __construct(
        private readonly PaCycleGate $gate,

        /* Satu-satunya penulis aturan "mana pengecualian yang diputuskan orang".
           Disuntikkan supaya ketergantungannya terbaca dari luar; layanan yang diam-diam
           menyentuh layanan lain adalah yang paling sulit ditelusuri saat perilakunya
           berubah. */
        private readonly PaCyclePreparationService $penyiapan,
    ) {}

    /**
     * Angka utama satu siklus: peserta, penugasan, hasil, dan progres per divisi.
     *
     * @return array<string, mixed>
     */
    public function ringkasan(int $cycleId): array
    {
        $siklus = $this->siklus($cycleId);

        $peserta = $this->cacah('N_PA_Participant', 'Status_Peserta', $cycleId);
        $hasil = $this->cacahHasil($cycleId);
        $penugasan = $this->cacahPenugasan($cycleId);

        $totalPenugasan = array_sum($penugasan);
        $selesai = $penugasan['SELESAI'] ?? 0;

        return [
            'siklus' => $siklus,
            'operasi_boleh' => $this->gate->operasiYangBoleh($cycleId),

            'peserta' => [
                'total' => array_sum($peserta),
                'per_status' => $peserta,
                'diikutkan' => $peserta['DIIKUTKAN'] ?? 0,
                'tertunda' => $peserta['TERTUNDA'] ?? 0,
                /* Hanya yang diputuskan orang — aturannya milik
                   `PaCyclePreparationService::cacahDikecualikan()`. */
                'dikecualikan' => $this->penyiapan->cacahDikecualikan([$cycleId])[$cycleId]['manual'] ?? 0,
            ],

            'penugasan' => [
                'total' => $totalPenugasan,
                'per_status' => $penugasan,
                'selesai' => $selesai,
                'terlambat' => $this->cacahTerlambat($cycleId),
                /* Dibulatkan di server. Layar yang menghitungnya sendiri akan membulatkan
                   dengan caranya sendiri, dan dua angka berbeda untuk satu gagasan
                   mustahil dijelaskan kepada orang yang menanyakannya. */
                'penyelesaian_persen' => self::persenTuntas($selesai, $totalPenugasan),
            ],

            'hasil' => [
                'total' => array_sum($hasil),
                'per_status' => $hasil,
                'terbit' => $hasil[PaResultPublishService::STATUS_TERBIT] ?? 0,
                'tertahan' => $hasil['TERTAHAN'] ?? 0,
            ],

            'per_divisi' => $this->progresPerDivisi($cycleId),
        ];
    }

    /**
     * Monitoring rinci: peta panas per divisi dan daftar penugasan terlambat.
     *
     * @param  array{id_divisi?:int|null, id_level?:int|null, pa_rater_category_id?:int|null,
     *               status?:string|null, q?:string|null, halaman?:int, per_halaman?:int}  $saringan
     * @return array<string, mixed>
     */
    public function monitoring(int $cycleId, array $saringan = []): array
    {
        $this->siklus($cycleId);

        $halaman = max(1, (int) ($saringan['halaman'] ?? 1));
        $diminta = (int) ($saringan['per_halaman'] ?? 25);
        $perHalaman = max(1, min($diminta, self::MAKS_PER_HALAMAN));

        $dasar = fn () => $this->kueriTerlambat($cycleId, $saringan);

        $total = (int) $dasar()->count();

        $baris = $dasar()
            // Yang paling lama terlambat lebih dulu — itu yang paling perlu dikejar.
            // Kunci utama terakhir supaya baris tidak berpindah halaman antar permintaan.
            ->orderBy('a.Due_At')
            ->orderBy('a.Id_PA_Assignment')
            ->offset(($halaman - 1) * $perHalaman)
            ->limit($perHalaman)
            ->get([
                'a.Id_PA_Assignment', 'a.Due_At', 'a.Status_Penugasan',
                'a.Nama_Penilai_Saat_Cycle', 'a.Kode_Karyawan_Penilai',
                'c.Kode_Kategori', 'c.Nama_Kategori',
                'p.Id_PA_Participant', 'p.Nama_Karyawan_Saat_Cycle',
                'p.Nama_Divisi_Saat_Cycle', 'p.Nama_Level_Saat_Cycle',
            ])
            ->map(fn ($b) => (array) $b + ['hari_terlambat' => $this->hariTerlambat($b->Due_At)])
            ->values()
            ->all();

        /* Daftar PENUH, dengan saringan yang sama. Inilah yang ditampilkan tabel utama
           pemantauan; `terlambat` di bawah tetap ada karena ia jawaban atas pertanyaan lain
           dan dipakai panel tersendiri. */
        $dasarSemua = fn () => $this->kueriPenugasan($cycleId, $saringan);
        $totalSemua = (int) $dasarSemua()->count();

        $barisSemua = $dasarSemua()
            /* Yang belum selesai lebih dulu, lalu tenggat terdekat. Halaman pemantauan dibuka
               untuk mengejar yang tertinggal; menaruh yang sudah selesai di halaman pertama
               memaksa admin menggulung melewati pekerjaan yang sudah beres. */
            ->orderByRaw("CASE WHEN a.Status_Penugasan = 'SELESAI' THEN 1 ELSE 0 END")
            ->orderByRaw('CASE WHEN a.Due_At IS NULL THEN 1 ELSE 0 END')
            ->orderBy('a.Due_At')
            ->orderBy('a.Id_PA_Assignment')
            ->offset(($halaman - 1) * $perHalaman)
            ->limit($perHalaman)
            ->get([
                'a.Id_PA_Assignment', 'a.Due_At', 'a.Status_Penugasan',
                'a.Started_At', 'a.Finished_At',
                'a.Nama_Penilai_Saat_Cycle', 'a.Kode_Karyawan_Penilai',
                'c.Kode_Kategori', 'c.Nama_Kategori',
                'p.Id_PA_Participant', 'p.Kode_Karyawan', 'p.Nama_Karyawan_Saat_Cycle',
                'p.Nama_Divisi_Saat_Cycle', 'p.Nama_Level_Saat_Cycle',
            ])
            ->map(fn ($b) => (array) $b + ['hari_terlambat' => $this->hariTerlambat($b->Due_At)])
            ->values()
            ->all();

        return [
            'ringkasan' => $this->ringkasanPengisian($cycleId),
            'keranjang' => $this->keranjang($cycleId),
            'kategori' => $this->rekapKategori($cycleId),
            'peserta' => $this->halamanPeserta($cycleId, $saringan, $halaman, $perHalaman),
            'penugasan' => [
                'baris' => $barisSemua,
                'halaman' => $halaman,
                'per_halaman' => $perHalaman,
                'total' => $totalSemua,
                'total_halaman' => (int) ceil($totalSemua / $perHalaman),
                'dipangkas' => $diminta > self::MAKS_PER_HALAMAN,
            ],
            'peta_panas' => $this->petaPanas($cycleId),
            'terlambat' => [
                'baris' => $baris,
                'halaman' => $halaman,
                'per_halaman' => $perHalaman,
                'total' => $total,
                'total_halaman' => (int) ceil($total / $perHalaman),
                'dipangkas' => $diminta > self::MAKS_PER_HALAMAN,
            ],
            'pilihan_saringan' => $this->pilihanSaringan($cycleId),
        ];
    }

    /**
     * Pemantauan PER PENILAI: satu baris per orang yang menilai — berapa orang yang ia nilai,
     * berapa yang sudah SELESAI, dan status gabungannya. Pertanyaan yang tidak terjawab tampilan
     * per karyawan: "siapa yang belum bergerak sama sekali?". Yang paling tertinggal lebih dulu.
     *
     *   status  BELUM_MULAI  seluruh lembarnya belum dibuka
     *           SEDANG       sebagian sudah, sebagian belum
     *           TERKUMPUL    semua terisi tetapi belum dikirim / menunggu verifikasi
     *           SELESAI      seluruhnya selesai
     *
     * @param  array{q?:?string, status?:?string, halaman?:int, per_halaman?:int}  $saringan
     * @return array{baris:list<array<string, mixed>>, halaman:int, per_halaman:int, total:int, total_halaman:int}
     */
    public function monitoringPenilai(int $cycleId, array $saringan = []): array
    {
        $this->siklus($cycleId);
        $halaman = max(1, (int) ($saringan['halaman'] ?? 1));
        $perHalaman = max(1, min((int) ($saringan['per_halaman'] ?? 25), self::MAKS_PER_HALAMAN));
        $selesai = "'".PaStatusPenugasan::SELESAI."'";
        $belum = "'".PaStatusPenugasan::BELUM_MULAI."'";
        $kumpul = "'".PaStatusPenugasan::TERKUMPUL."','".PaStatusPenugasan::MENUNGGU_VERIFIKASI."'";

        /* Saringan yang SAMA dengan tampilan per karyawan (14 Okt 2026): divisi dan level orang
           yang DINILAI, serta kategori penilai — diterapkan pada lembar sebelum dikelompokkan,
           jadi "penilai Divisi X" berarti progres lembar-lembarnya untuk orang Divisi X. */
        $grup = $this->dasarPenugasan($cycleId)
            ->when(! empty($saringan['id_divisi']), fn ($q) => $q->where('p.ID_Divisi_Saat_Cycle', (int) $saringan['id_divisi']))
            ->when(! empty($saringan['id_level']), fn ($q) => $q->where('p.ID_Level_Saat_Cycle', (int) $saringan['id_level']))
            ->when(! empty($saringan['pa_rater_category_id']), fn ($q) => $q->where('a.PA_Rater_Category_Id', (int) $saringan['pa_rater_category_id']))
            ->when(trim((string) ($saringan['q'] ?? '')) !== '', function ($q) use ($saringan) {
                $cari = trim((string) $saringan['q']);
                $q->where(fn ($w) => $w->where('a.Nama_Penilai_Saat_Cycle', 'LIKE', '%'.$cari.'%')->orWhere('a.Kode_Karyawan_Penilai', 'LIKE', $cari.'%'));
            })
            ->groupBy('a.Kode_Perusahaan_Penilai', 'a.Kode_Karyawan_Penilai')
            ->selectRaw("a.Kode_Perusahaan_Penilai as kode_perusahaan, a.Kode_Karyawan_Penilai as kode_karyawan,
                MAX(a.Nama_Penilai_Saat_Cycle) as nama, COUNT(*) as total,
                SUM(CASE WHEN a.Status_Penugasan = {$selesai} THEN 1 ELSE 0 END) as selesai,
                SUM(CASE WHEN a.Status_Penugasan = {$belum} THEN 1 ELSE 0 END) as belum_mulai,
                SUM(CASE WHEN a.Status_Penugasan IN ({$kumpul}) THEN 1 ELSE 0 END) as terkumpul,
                MAX(COALESCE(a.Finished_At, a.Started_At)) as terakhir_aktif");

        $status = (string) ($saringan['status'] ?? '');
        $luar = DB::query()->fromSub($grup, 'g')
            ->selectRaw("g.*, CASE WHEN g.selesai = g.total THEN 'SELESAI'
                WHEN g.belum_mulai = g.total THEN 'BELUM_MULAI'
                WHEN g.selesai + g.terkumpul = g.total THEN 'TERKUMPUL'
                ELSE 'SEDANG' END as status");
        $dasar = fn () => DB::query()->fromSub($luar, 'h')
            ->when(in_array($status, ['BELUM_MULAI', 'SEDANG', 'TERKUMPUL', 'SELESAI'], true), fn ($q) => $q->where('h.status', $status));

        $total = (int) $dasar()->count();

        // Jumlah per status TANPA saringan status — angka chip, sama seperti tampilan per karyawan.
        $perStatus = DB::query()->fromSub($luar, 'h')->groupBy('h.status')
            ->selectRaw('h.status, COUNT(*) as n')->pluck('n', 'status')->map(fn ($n) => (int) $n);
        $ringkasan = ['semua' => (int) $perStatus->sum()]
            + collect(['BELUM_MULAI', 'SEDANG', 'TERKUMPUL', 'SELESAI'])->mapWithKeys(fn ($s) => [$s => (int) ($perStatus[$s] ?? 0)])->all();

        $baris = $dasar()
            ->orderByRaw('CAST(h.selesai AS float) / NULLIF(h.total, 0)')
            ->orderBy('h.nama')
            ->offset(($halaman - 1) * $perHalaman)->limit($perHalaman)
            ->get()
            ->map(fn ($r) => [
                'kode_perusahaan' => $r->kode_perusahaan,
                'kode_karyawan' => trim((string) $r->kode_karyawan),
                'nama' => $r->nama,
                'total' => (int) $r->total,
                'selesai' => (int) $r->selesai,
                'belum_mulai' => (int) $r->belum_mulai,
                'terkumpul' => (int) $r->terkumpul,
                'persen' => (int) $r->total === 0 ? 0 : (int) floor(100 * (int) $r->selesai / (int) $r->total),
                'status' => $r->status,
                'terakhir_aktif' => $r->terakhir_aktif,
            ])->all();

        return ['baris' => $baris, 'halaman' => $halaman, 'per_halaman' => $perHalaman,
            'total' => $total, 'total_halaman' => (int) ceil($total / $perHalaman), 'ringkasan' => $ringkasan];
    }

    /**
     * Rincian satu penilai: orang-orang yang ia nilai beserta status tiap lembar — bentuk baris
     * sama dengan daftar penugasan supaya tombol Akses ulang bisa dipakai apa adanya.
     *
     * @return list<array<string, mixed>>
     */
    public function rincianPenilai(int $cycleId, string $kodePerusahaan, string $kodeKaryawan): array
    {
        $this->siklus($cycleId);

        return $this->dasarPenugasan($cycleId)
            ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id')
            ->where('a.Kode_Perusahaan_Penilai', $kodePerusahaan)
            ->where('a.Kode_Karyawan_Penilai', $kodeKaryawan)
            ->orderByRaw("CASE WHEN a.Status_Penugasan = '".PaStatusPenugasan::SELESAI."' THEN 1 ELSE 0 END")
            ->orderBy('p.Nama_Karyawan_Saat_Cycle')
            ->get([
                'a.Id_PA_Assignment', 'a.Status_Penugasan', 'a.Due_At', 'a.Started_At', 'a.Finished_At',
                'a.Nama_Penilai_Saat_Cycle as Nama_Penilai', 'a.Kode_Karyawan_Penilai as Kode_Penilai',
                'c.Kode_Kategori', 'c.Nama_Kategori',
                'p.Id_PA_Participant', 'p.Kode_Karyawan', 'p.Nama_Karyawan_Saat_Cycle as Nama_Karyawan_Yang_Dinilai',
                'p.Nama_Level_Saat_Cycle',
            ])
            ->map(fn ($b) => (array) $b + ['hari_terlambat' => $this->hariTerlambat($b->Due_At)])
            ->all();
    }

    /* ------------------------------------------------------------------ *
     * Aturan terlambat — SATU tempat                                      *
     * ------------------------------------------------------------------ */

    /**
     * Syarat "terlambat" versi SQL — definisi KETAT (`PaStatusPenugasan::
     * TIDAK_DIHITUNG_TERLAMBAT_KETAT`): hanya SELESAI dan DIBATALKAN yang dibebaskan.
     * Dipakai `cacahTerlambat()`, `kueriTerlambat()` (daftar/saringan TERLAMBAT), dan —
     * lewat `sqlTerlambat()` — kartu `ringkasanPengisian()`, progres per karyawan
     * `halamanPeserta()`, serta peta panas, supaya angka kartu sama dengan isi daftar
     * saat diklik. Hanya `progresPerDivisi()` (dashboard ringkasan) yang masih memakai
     * definisi LONGGAR, `TIDAK_DIHITUNG_TERLAMBAT`. Jangan disatukan diam-diam; lihat
     * docblock `PaStatusPenugasan` untuk kenapa keduanya berbeda.
     *
     * Padanan PHP-nya ada di `PaParticipantReadService::keterlambatan()` (isinya
     * sama, sudah dialihkan ke konstanta yang sama). `PaPesertaService::
     * keterlambatan()` memakai daftar KETIGA, `['SELESAI','TERKUMPUL','DIBATALKAN']`,
     * yang bukan subset dari `TIDAK_DIHITUNG_TERLAMBAT` (tidak membebaskan
     * MENUNGGU_VERIFIKASI) maupun dari `TIDAK_DIHITUNG_TERLAMBAT_KETAT` (justru
     * membebaskan TERKUMPUL) — SENGAJA dibiarkan apa adanya, bukan lupa dialihkan;
     * keputusan mana yang benar untuknya menunggu pemilik proyek.
     *
     * `Due_At` bertipe DATE dan berarti sampai AKHIR hari itu, jadi ambangnya
     * `Due_At < hari ini`. Memakai `<=` akan menandai orang terlambat pada hari yang
     * masih miliknya.
     */
    private function syaratTerlambat($q)
    {
        return $q->whereRaw($this->sqlTerlambat());
    }

    /** Satu definisi "lewat tenggat" untuk kartu, daftar, dan progres per karyawan. */
    private function sqlTerlambat(): string
    {
        return '(a.Due_At IS NOT NULL AND a.Due_At < CAST(GETDATE() AS date) AND a.Status_Penugasan NOT IN ('
            .PaStatusPenugasan::sqlDaftar(PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT_KETAT).'))';
    }

    /**
     * Penugasan yang DIHITUNG di layar pemantauan: aktif, tidak dibatalkan, milik peserta
     * yang masih DIIKUTKAN. Kartu, rekap kategori, daftar, dan kepala tahap memakai basis
     * yang sama — sebelumnya kartu ikut menghitung penugasan dibatalkan dan peserta yang
     * dikecualikan, sehingga totalnya beda dari kepala tahap dan persennya tak pernah 100.
     */
    private function dasarPenugasan(int $cycleId)
    {
        return DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('a.Flag_Aktif', 'Y')
            ->where('a.Status_Penugasan', '<>', PaStatusPenugasan::DIBATALKAN);
    }

    /**
     * Daftar pemantauan BERHALAMAN PER KARYAWAN.
     *
     * Sebelumnya halaman dipotong per baris penugasan lalu dikelompokkan per karyawan di
     * layar: penilai satu orang tersebar di beberapa halaman (yang SELESAI diurutkan ke
     * belakang), sehingga progresnya terbaca "0/1 Belum Mengisi" padahal 5 dari 6 sudah
     * selesai. Kini progres dihitung server atas SEMUA penilai orang itu; daftar penilai
     * yang dikirim hanya yang cocok dengan saringan, supaya saringan tetap fokus.
     *
     * @return array<string, mixed>
     */
    private function halamanPeserta(int $cycleId, array $saringan, int $halaman, int $perHalaman): array
    {
        /* Tiga kueri ringan yang digabung di PHP, bukan satu join ke subkueri agregat: join itu
           memakan ±1,7 detik di SQL Server, sedangkan bagian-bagiannya masing-masing puluhan
           milidetik. Jumlah peserta per siklus hanya ratusan sampai seribuan. */
        $progres = $this->dasarPenugasan($cycleId)
            ->groupBy('a.PA_Participant_Id')
            ->selectRaw("a.PA_Participant_Id AS pid, COUNT(*) AS total,
                SUM(CASE WHEN a.Status_Penugasan = 'SELESAI' THEN 1 ELSE 0 END) AS selesai,
                SUM(CASE WHEN a.Status_Penugasan = 'TERKUMPUL' THEN 1 ELSE 0 END) AS terkumpul,
                SUM(CASE WHEN a.Status_Penugasan = 'BERJALAN' THEN 1 ELSE 0 END) AS berjalan,
                SUM(CASE WHEN a.Status_Penugasan = 'BELUM_MULAI' THEN 1 ELSE 0 END) AS belum_mulai,
                SUM(CASE WHEN a.Status_Penugasan = '".PaStatusPenugasan::MENUNGGU_VERIFIKASI."' THEN 1 ELSE 0 END) AS menunggu_verifikasi,
                SUM(CASE WHEN ".$this->sqlTerlambat()." THEN 1 ELSE 0 END) AS terlambat,
                MIN(CASE WHEN a.Status_Penugasan <> 'SELESAI' THEN a.Due_At END) AS tenggat_terdekat")
            ->get()
            ->keyBy(fn ($r) => (int) $r->pid);

        $cocok = $this->kueriPenugasan($cycleId, $saringan)->distinct()->pluck('a.PA_Participant_Id')
            ->map(fn ($v) => (int) $v)->flip();

        $orang = DB::table('N_PA_Participant as p')
            ->leftJoin('N_PA_Result as r', fn ($j) => $j->on('r.PA_Participant_Id', '=', 'p.Id_PA_Participant')->where('r.Flag_Aktif', 'Y'))
            ->where('p.PA_Cycle_Id', $cycleId)->where('p.Flag_Aktif', 'Y')->where('p.Status_Peserta', 'DIIKUTKAN')
            ->get([
                'p.Id_PA_Participant', 'p.Kode_Karyawan', 'p.Nama_Karyawan_Saat_Cycle',
                'p.Nama_Divisi_Saat_Cycle', 'p.Nama_Level_Saat_Cycle', 'p.ID_Level_Saat_Cycle', 'r.Id_PA_Result',
            ])
            ->filter(fn ($p) => $cocok->has((int) $p->Id_PA_Participant) && $progres->has((int) $p->Id_PA_Participant))
            ->map(function ($p) use ($progres) {
                $g = $progres[(int) $p->Id_PA_Participant];
                foreach (['total', 'selesai', 'terkumpul', 'berjalan', 'belum_mulai', 'menunggu_verifikasi', 'terlambat'] as $k) {
                    $p->{$k} = (int) $g->{$k};
                }
                $p->tenggat = $g->tenggat_terdekat === null ? null : substr((string) $g->tenggat_terdekat, 0, 19);

                return $p;
            })
            // Yang belum tuntas lebih dulu, lalu tenggat terdekat — itu yang perlu dikejar.
            ->sort(fn ($x, $y) => [(int) ($x->selesai === $x->total), $x->tenggat === null, $x->tenggat, $x->Nama_Karyawan_Saat_Cycle, (int) $x->Id_PA_Participant]
                <=> [(int) ($y->selesai === $y->total), $y->tenggat === null, $y->tenggat, $y->Nama_Karyawan_Saat_Cycle, (int) $y->Id_PA_Participant])
            ->values();

        $total = $orang->count();
        $baris = $orang->slice(($halaman - 1) * $perHalaman, $perHalaman)->values();

        $ids = $baris->pluck('Id_PA_Participant')->map(fn ($v) => (int) $v)->all();
        $penilai = $ids === [] ? collect() : $this->kueriPenugasan($cycleId, $saringan)
            ->whereIn('a.PA_Participant_Id', $ids)
            ->orderByRaw("CASE WHEN a.Status_Penugasan = 'SELESAI' THEN 1 ELSE 0 END")
            ->orderBy('c.Nama_Kategori')
            ->orderBy('a.Id_PA_Assignment')
            ->get([
                'a.Id_PA_Assignment', 'a.PA_Participant_Id', 'a.Due_At', 'a.Status_Penugasan',
                'a.Started_At', 'a.Finished_At',
                'a.Nama_Penilai_Saat_Cycle', 'a.Kode_Karyawan_Penilai', 'a.Kode_Perusahaan_Penilai',
                'c.Kode_Kategori', 'c.Nama_Kategori',
            ])
            ->map(fn ($b) => (array) $b + ['hari_terlambat' => $this->hariTerlambat($b->Due_At)])
            ->groupBy('PA_Participant_Id');

        return [
            'baris' => $baris->map(fn ($b) => [
                'Id_PA_Participant' => (int) $b->Id_PA_Participant,
                'Id_PA_Result' => $b->Id_PA_Result === null ? null : (int) $b->Id_PA_Result,
                'Kode_Karyawan' => $b->Kode_Karyawan,
                'Nama_Karyawan_Saat_Cycle' => $b->Nama_Karyawan_Saat_Cycle,
                'Nama_Divisi_Saat_Cycle' => $b->Nama_Divisi_Saat_Cycle,
                'Nama_Level_Saat_Cycle' => $b->Nama_Level_Saat_Cycle,
                'ID_Level_Saat_Cycle' => $b->ID_Level_Saat_Cycle === null ? null : (int) $b->ID_Level_Saat_Cycle,
                'progres' => [
                    'total' => (int) $b->total, 'selesai' => (int) $b->selesai, 'terkumpul' => (int) $b->terkumpul,
                    'berjalan' => (int) $b->berjalan, 'belum_mulai' => (int) $b->belum_mulai,
                    'menunggu_verifikasi' => (int) $b->menunggu_verifikasi, 'terlambat' => (int) $b->terlambat,
                ],
                'penilai' => array_values(($penilai[$b->Id_PA_Participant] ?? collect())->all()),
            ])->values()->all(),
            'halaman' => $halaman,
            'per_halaman' => $perHalaman,
            'total' => $total,
            'total_halaman' => (int) ceil($total / $perHalaman),
        ];
    }

    private function hariTerlambat(mixed $dueAt): ?int
    {
        if ($dueAt === null) {
            return null;
        }

        $tenggat = new \DateTimeImmutable(substr((string) $dueAt, 0, 10));
        $hariIni = new \DateTimeImmutable(date('Y-m-d'));

        return $tenggat >= $hariIni ? null : max(0, (int) $tenggat->diff($hariIni)->days);
    }

    /**
     * Persen penyelesaian, DIBULATKAN KE BAWAH.
     *
     * `round()` membuat 1.139 dari 1.140 terbaca 100% — layar lalu menulis "Pengisian
     * Tuntas" dan "0% sisa" padahal satu penilai belum mengisi. Angka 100 di layar ini
     * berarti tuntas, jadi ia hanya boleh muncul ketika `selesai === total`. Pembagian
     * bulat (`intdiv`), bukan `floor()` atas float: 29/100*100 pada float bisa jatuh ke
     * 28,999… lalu terpotong menjadi 28.
     */
    private static function persenTuntas(int $selesai, int $total): int
    {
        return $total > 0 ? intdiv($selesai * 100, $total) : 0;
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    /**
     * @return array<string, mixed>
     */
    private function siklus(int $cycleId): array
    {
        $s = DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->first([
                'Id_PA_Cycle', 'Kode_Cycle', 'Nama_Cycle', 'Tahun', 'Status_Cycle',
                'Periode_Dinilai_Dari', 'Periode_Dinilai_Sampai',
                'Pengisian_Dari', 'Pengisian_Sampai',
            ]);

        if ($s === null) {
            throw new RuntimeException("Siklus {$cycleId} tidak ditemukan.");
        }

        return (array) $s;
    }

    /**
     * @return array<string, int>
     */
    private function cacah(string $tabel, string $kolom, int $cycleId): array
    {
        return DB::table($tabel)
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->groupBy($kolom)
            ->selectRaw("{$kolom} as nilai, COUNT(*) as jumlah")
            ->pluck('jumlah', 'nilai')
            ->mapWithKeys(fn ($n, $k) => [(string) $k => (int) $n])
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function cacahHasil(int $cycleId): array
    {
        return DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            /* Hanya yang DIIKUTKAN. Tanpa penyaring ini dashboard menghitung juga peserta
               yang DIKECUALIKAN — baris hasilnya memang dibentuk saat penyiapan lalu tidak
               pernah diisi — sehingga dashboard melaporkan 958 total dan 953 "belum
               dihitung" pada siklus yang sama, pada detik yang sama, ketika layar
               penerbitan melaporkan 5 dan 0. Selisih 953 untuk satu gagasan yang sama.

               Yang menyimpang adalah dashboard, bukan penerbitan: `PaResultPublishService`
               menyaring DIIKUTKAN, dan contoh di kontrak §2.13 memakai angka DIIKUTKAN.
               Efek nyatanya bukan sekadar angka jelek — dashboard menampilkan tumpukan
               pekerjaan yang secara sengaja tidak akan pernah dikerjakan siapa pun. */
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('p.Flag_Aktif', 'Y')
            ->where('r.Flag_Aktif', 'Y')
            ->groupBy('r.Status_Hasil')
            ->selectRaw('r.Status_Hasil as nilai, COUNT(*) as jumlah')
            ->pluck('jumlah', 'nilai')
            ->mapWithKeys(fn ($n, $k) => [(string) $k => (int) $n])
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function cacahPenugasan(int $cycleId): array
    {
        return DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('a.Flag_Aktif', 'Y')
            ->where('a.Status_Penugasan', '<>', 'DIBATALKAN')
            ->groupBy('a.Status_Penugasan')
            ->selectRaw('a.Status_Penugasan as nilai, COUNT(*) as jumlah')
            ->pluck('jumlah', 'nilai')
            ->mapWithKeys(fn ($n, $k) => [(string) $k => (int) $n])
            ->all();
    }

    private function cacahTerlambat(int $cycleId): int
    {
        return (int) $this->syaratTerlambat(
            DB::table('N_PA_Assignment as a')
                ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
                ->where('p.PA_Cycle_Id', $cycleId)
                ->where('a.Flag_Aktif', 'Y')
        )->count();
    }

    /**
     * Progres penugasan per divisi — SATU kueri untuk seluruh divisi.
     *
     * @return list<array<string, mixed>>
     */
    private function progresPerDivisi(int $cycleId): array
    {
        return DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('a.Flag_Aktif', 'Y')
            ->where('a.Status_Penugasan', '<>', 'DIBATALKAN')
            ->groupBy('p.ID_Divisi_Saat_Cycle', 'p.Nama_Divisi_Saat_Cycle')
            ->selectRaw(
                'p.ID_Divisi_Saat_Cycle as id_divisi, p.Nama_Divisi_Saat_Cycle as nama_divisi, '
                .'COUNT(*) as total, '
                ."SUM(CASE WHEN a.Status_Penugasan = 'SELESAI' THEN 1 ELSE 0 END) as selesai, "
                .'SUM(CASE WHEN a.Due_At IS NOT NULL AND a.Due_At < CAST(GETDATE() AS date) '
                // TERKUMPUL dan MENUNGGU_VERIFIKASI bukan terlambat: lembarnya sudah
                // dikerjakan, yang belum hanya pengiriman/pengesahannya - dan itu bukan
                // keterlambatan mengisi. Sumbernya PaStatusPenugasan, bukan diketik ulang.
                .'AND a.Status_Penugasan NOT IN ('.PaStatusPenugasan::sqlDaftar(PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT).') THEN 1 ELSE 0 END) as terlambat'
            )
            ->orderBy('p.Nama_Divisi_Saat_Cycle')
            ->get()
            ->map(function ($r) {
                $total = (int) $r->total;
                $selesai = (int) $r->selesai;

                return [
                    'id_divisi' => $r->id_divisi === null ? null : (int) $r->id_divisi,
                    // Divisi kosong TIDAK disembunyikan. Peserta tanpa divisi tetap orang
                    // yang harus dinilai, dan membuangnya dari peta membuat jumlah per
                    // divisi tidak pernah sama dengan jumlah keseluruhan — selisih yang
                    // akan dicari orang berjam-jam.
                    'nama_divisi' => $r->nama_divisi ?? '(Tanpa divisi)',
                    'total' => $total,
                    'selesai' => $selesai,
                    'terlambat' => (int) $r->terlambat,
                    'penyelesaian_persen' => self::persenTuntas($selesai, $total),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Peta panas divisi × status penugasan — satu kueri.
     *
     * Basisnya `dasarPenugasan()`, sama dengan kartu dan daftar: dulu peta ini ikut menghitung
     * penugasan nonaktif dan peserta yang dikecualikan, sehingga jumlah per divisinya tidak
     * pernah sama dengan "Total Penugasan" di kartu sebelahnya.
     *
     * `id_divisi` ikut dikirim supaya klik pada baris menyaring dengan id, bukan menebak
     * dari nama; baris "(Tanpa divisi)" membawa `null` dan layar tahu ia tak bisa disaring.
     * `terlambat` memakai `sqlTerlambat()` — definisi yang sama dengan kartu "Lewat Tenggat".
     *
     * @return array<string, mixed>
     */
    private function petaPanas(int $cycleId): array
    {
        $baris = $this->dasarPenugasan($cycleId)
            ->groupBy('p.ID_Divisi_Saat_Cycle', 'p.Nama_Divisi_Saat_Cycle', 'a.Status_Penugasan')
            ->selectRaw('p.ID_Divisi_Saat_Cycle as id_divisi, p.Nama_Divisi_Saat_Cycle as divisi, a.Status_Penugasan as status, COUNT(*) as jumlah, '
                .'SUM(CASE WHEN '.$this->sqlTerlambat().' THEN 1 ELSE 0 END) as terlambat')
            ->get();

        $status = [];
        $sel = [];
        $idDivisi = [];
        $terlambat = [];

        foreach ($baris as $b) {
            $divisi = $b->divisi ?? '(Tanpa divisi)';
            $status[$b->status] = true;
            $sel[$divisi][$b->status] = ($sel[$divisi][$b->status] ?? 0) + (int) $b->jumlah;
            $terlambat[$divisi] = ($terlambat[$divisi] ?? 0) + (int) $b->terlambat;

            /* Satu nama bisa (jarang) tercatat dengan dua id; yang pertama dipakai untuk
               menyaring, dan nama tanpa id tetap tak bisa disaring. */
            if (! array_key_exists($divisi, $idDivisi) || $idDivisi[$divisi] === null) {
                $idDivisi[$divisi] = $b->id_divisi === null ? null : (int) $b->id_divisi;
            }
        }

        ksort($sel);
        $kolom = array_keys($status);
        sort($kolom);

        /* Dikirim sebagai baris LENGKAP — tiap divisi memuat setiap status, nol pun
           ditulis. Peta panas yang selnya kadang tidak ada memaksa layar menebak apakah
           sel kosong berarti nol atau berarti belum dimuat. */
        return [
            'status' => $kolom,
            'baris' => array_map(
                fn ($divisi) => [
                    'id_divisi' => $idDivisi[$divisi] ?? null,
                    'divisi' => $divisi,
                    'sel' => array_map(fn ($s) => $sel[$divisi][$s] ?? 0, $kolom),
                    'total' => array_sum($sel[$divisi]),
                    'terlambat' => $terlambat[$divisi] ?? 0,
                ],
                array_keys($sel),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $saringan
     */
    private function kueriTerlambat(int $cycleId, array $saringan)
    {
        $q = $this->kueriPenugasan($cycleId, $saringan);

        $this->syaratTerlambat($q);

        return $q;
    }

    /**
     | KERANJANG KEPUTUSAN ADMIN.
     |
     | Menjawab pertanyaan yang berbeda dari pemantauan pengisian. Pemantauan menjawab
     | "bagaimana pengisian berjalan"; keranjang ini menjawab "apa yang menunggu keputusan
     | saya". Keduanya di satu halaman, tetapi tidak boleh di satu daftar — daftar yang
     | mencampur keduanya membuat admin menyaring dua kali untuk satu pekerjaan.
     |
     | Empat keranjang, berurutan sesuai apa yang harus dilakukan:
     |
     |   siap_dihitung  — penilainya lengkap, hasilnya belum pernah dihitung. Sejak
     |                    perhitungan otomatis berjalan, isinya seharusnya nol; yang tertinggal
     |                    di sini berarti antreannya bermasalah, dan itu justru perlu terlihat.
     |   tertahan       — sudah dihitung, tetapi tidak berangka. Sebabnya tertulis pada
     |                    `Alasan_Tertahan` dan biasanya di luar PA — KPI belum disetujui.
     |   perlu_verifikasi — sudah bergrade, menunggu diperiksa lalu diterbitkan.
     |   kiriman_susulan  — INI YANG PALING MUDAH TERLEWAT. Payload yang datang SESUDAH
     |                    hasilnya terbit. `TERIMA_PAYLOAD` sengaja masih menerima pada CLOSED
     |                    supaya kiriman HC Learn tidak hilang, sementara `hitung()` menolak
     |                    menghitung ulang hasil terbit — keduanya benar, tetapi bersama-sama
     |                    menghasilkan kiriman yang tersimpan rapi dan tidak pernah berpengaruh.
     |                    Dulu langka; sejak penerbitan per orang, rutin. Karena itu dideteksi
     |                    dari `Diterima_At > Published_At`, bukan dibiarkan diam.
     |
     | @return array<string, mixed>
     */
    /*
     | PENGGOLONG SEBAB TERTAHAN.
     |
     | Sebab tertahan disimpan sebagai KALIMAT, bukan kode — tujuh sebab dari tiga keluarga:
     | lima soal KPI, satu soal ambang penilai, satu soal paket soal yang tak menutup jenis
     | kompetensi wajib. Selama belum ada kolom kodenya, penggolongannya bersandar pada
     | kalimat pembuka tiap sebab yang stabil.
     |
     | DICARI DI MANA SAJA DALAM KALIMAT, BUKAN HANYA DI AWALNYA (koreksi 8 Okt 2026).
     | Perhitungan menggabungkan sebab pilar KPI dan pilar kompetensi menjadi satu kalimat
     | (`gabungAlasanTertahan`, KPI lebih dulu). Dengan pencocokan awalan, orang yang tertahan
     | karena KPI DAN penilai hanya masuk keranjang KPI: terukur di siklus #11091, 41 orang
     | "KPI" seluruhnya juga kekurangan penilai, sementara keranjang penilai hanya
     | menunjukkan 56 dari 97 orang yang penilainya bermasalah. Sekarang satu orang muncul di
     | SETIAP keranjang yang sebabnya ia bawa, sehingga jumlah keranjang boleh melebihi jumlah
     | orang tertahan — total `tertahan` dihitung tersendiri, bukan dijumlahkan.
     |
     | Itu tetap rapuh, dan justru karena itu ada keranjang `tertahan_lain`: sebab yang tidak
     | dikenali TIDAK dibuang dan tidak dipaksa masuk salah satu keluarga — ia muncul sebagai
     | keranjang tersendiri yang namanya mengundang pemeriksaan. Kehilangan orang dari daftar
     | pekerjaan jauh lebih mahal daripada satu keranjang yang isinya perlu ditengok.
     */
    private const SEBAB_TERTAHAN_PENILAI = ['%Tak satu pun kategori penilai%'];

    private const SEBAB_TERTAHAN_KPI = [
        '%Karyawan tidak punya akun KPI%',
        // Bukan sekadar "Kode karyawan %": di tengah kalimat frasa itu terlalu umum.
        '%Kode karyawan % menunjuk % akun KPI%',
        '%Tidak ada KPI disetujui%',
        '%Seluruh parameter KPI%',
        '%Total bobot parameter KPI%',
    ];

    /* Sebab yang DIKENAL tetapi tidak punya keranjang sendiri — saat ini hanya paket soal yang
       tak menutup jenis kompetensi wajib. Ia selalu ikut `tertahan_lain`, juga ketika
       digabung dengan sebab KPI; tanpa itu sebab ini tak terlihat di keranjang mana pun
       selama sebab KPI-nya belum beres. */
    private const SEBAB_TERTAHAN_LAIN = ['%Jenis kompetensi wajib tidak tercakup%'];

    /** Potongan SQL: kalimat sebab memuat salah satu pola. Pola konstanta, bukan masukan. */
    private function sqlSebab(array $pola): string
    {
        return '('.implode(' OR ', array_map(
            fn ($p) => "res.Alasan_Tertahan LIKE '".str_replace("'", "''", $p)."'",
            $pola,
        )).')';
    }

    /** Masuk `tertahan_lain`: sebab lain yang dikenal, ATAU tak satu pun keluarga cocok. */
    private function sqlSebabLain(): string
    {
        return '('.$this->sqlSebab(self::SEBAB_TERTAHAN_LAIN)
            .' OR (res.Alasan_Tertahan IS NULL OR (NOT '.$this->sqlSebab(self::SEBAB_TERTAHAN_KPI)
            .' AND NOT '.$this->sqlSebab(self::SEBAB_TERTAHAN_PENILAI).')))';
    }

    /**
     * Predikat SQL: peserta (alias `p`) masih punya bulan KPI dalam periode siklus yang target-
     * nya ada di pengajuan DISETUJUI tetapi realisasinya belum aktif + disetujui + terverifikasi.
     * Sama dengan saringan yang dipakai perhitungan PA (`parameterKpi`), jadi bulan yang
     * terhitung "kurang" di sini persis bulan yang tidak ikut menyumbang nilai KPI.
     *
     * Orang seperti ini biasanya tetap DIHITUNG — dari bulan yang sudah ada — sehingga tidak
     * pernah muncul di keranjang tertahan mana pun (defect UAT 8 Okt: "tidak bisa cari yang
     * belum update KPI"). Siklus tanpa periode atau tahun: predikatnya selalu salah.
     */
    private function sqlKpiBelumLengkap(int $cycleId): string
    {
        $inti = $this->sqlKpiBelumLengkapInti($cycleId);

        if ($inti === null) {
            return '(1 = 0)';
        }

        return "EXISTS (SELECT 1 {$inti['dari']}
                 WHERE UPPER(LTRIM(RTRIM(ku.Kode_Users))) = UPPER(LTRIM(RTRIM(p.Kode_Karyawan)))
                   AND {$inti['syarat']})";
    }

    /**
     * Aturan yang SAMA dengan `sqlKpiBelumLengkap()`, sebagai HIMPUNAN kode karyawan (kolom
     * `kode`, dan `n` = 1) untuk di-LEFT JOIN sekali.
     *
     * KENAPA ADA BENTUK KEDUA (14 Okt 2026). Di dalam kueri `keranjang()` versi EXISTS
     * berkorelasi dengan tiap peserta, dan pencocokan `UPPER(LTRIM(RTRIM(..)))` tidak bisa
     * memakai indeks — tabel KPI dipindai ulang per peserta. Terukur pada siklus 958 peserta:
     * 10,3 detik, padahal tanpa bagian ini 0,4 detik; dan kueri itu ikut berjalan pada SETIAP
     * pencarian di Pemantauan. Sebagai himpunan ia dihitung satu kali.
     */
    private function sqlKodeKpiBelumLengkap(int $cycleId): string
    {
        $inti = $this->sqlKpiBelumLengkapInti($cycleId);

        if ($inti === null) {
            return '(SELECT CAST(NULL AS nvarchar(50)) AS kode, 1 AS n WHERE 1 = 0)';
        }

        return "(SELECT DISTINCT UPPER(LTRIM(RTRIM(ku.Kode_Users))) AS kode, 1 AS n {$inti['dari']}
                  WHERE {$inti['syarat']})";
    }

    /** @return array{dari:string, syarat:string}|null */
    private function sqlKpiBelumLengkapInti(int $cycleId): ?array
    {
        [$dari, $sampai, $tahun] = $this->periodeKpi($cycleId);

        if ($dari === null) {
            return null;
        }

        return [
            'dari' => "FROM KPI_Users ku
                  JOIN KPI_Pengajuan kp ON kp.Id_User = ku.Id_Users AND kp.Flag_Aktif = 'Y'
                                       AND kp.Flag_Approval = 'Y' AND kp.Status IS NULL
                  JOIN KPI_Master_Penjadwalan kmp ON kmp.Id_Master_Penjadwalan = kp.Id_Penjadwalan
                                                AND kmp.Tahun = '{$tahun}'
                  JOIN KPI_Pengajuan_Detail kpd ON kpd.Id_Pengajuan = kp.Id_Pengajuan
                  JOIN KPI_Pengajuan_Det kd ON kd.Id_Pengajuan_Detail = kpd.Id_Pengajuan_Detail
                                           AND kd.Periode >= '{$dari}' AND kd.Periode <= '{$sampai}'",
            'syarat' => "NOT EXISTS (SELECT 1
                                     FROM KPI_Update_Performance_Detail kud
                                     JOIN KPI_Update_Performance ku2 ON ku2.Id_Update_Performance = kud.Id_Update_Performance
                                    WHERE kud.Id_Pengajuan_Det = kd.Id_Pengajuan_Det
                                      AND ku2.Flag_Aktif = 'Y' AND ku2.Flag_Approval = 'Y'
                                      AND ku2.Flag_Verifikasi = 'Y' AND ku2.Status IS NULL)",
        ];
    }

    /** @return array{0:?string, 1:?string, 2:?string} periode 'YYYY-MM' dari/sampai dan tahun siklus. */
    private function periodeKpi(int $cycleId): array
    {
        $c = DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->first(['Periode_Dinilai_Dari', 'Periode_Dinilai_Sampai', 'Tahun']);

        if ($c === null || $c->Periode_Dinilai_Dari === null || $c->Periode_Dinilai_Sampai === null) {
            return [null, null, null];
        }

        // Dari kolom tanggal/angka milik siklus sendiri, bukan masukan pengguna; tetap dipastikan bentuknya.
        $dari = substr((string) $c->Periode_Dinilai_Dari, 0, 7);
        $sampai = substr((string) $c->Periode_Dinilai_Sampai, 0, 7);
        $tahun = (string) (int) ($c->Tahun ?: substr($sampai, 0, 4));

        if (! preg_match('/^\d{4}-\d{2}$/', $dari) || ! preg_match('/^\d{4}-\d{2}$/', $sampai)) {
            return [null, null, null];
        }

        return [$dari, $sampai, $tahun];
    }

    /**
     * Bulan KPI yang kurang per peserta di satu halaman keranjang.
     *
     * @param  list<string>  $kode  kode karyawan
     * @return array<string, list<string>> [KODE => ['2026-09', ...]]
     */
    private function bulanKpiKurang(int $cycleId, array $kode): array
    {
        [$dari, $sampai, $tahun] = $this->periodeKpi($cycleId);

        if ($dari === null || $kode === []) {
            return [];
        }

        $hasil = [];
        foreach (DB::table('KPI_Users as ku')
            ->join('KPI_Pengajuan as kp', function ($j) {
                $j->on('kp.Id_User', '=', 'ku.Id_Users')->where('kp.Flag_Aktif', 'Y')->where('kp.Flag_Approval', 'Y')->whereNull('kp.Status');
            })
            ->join('KPI_Master_Penjadwalan as kmp', function ($j) use ($tahun) {
                $j->on('kmp.Id_Master_Penjadwalan', '=', 'kp.Id_Penjadwalan')->where('kmp.Tahun', $tahun);
            })
            ->join('KPI_Pengajuan_Detail as kpd', 'kpd.Id_Pengajuan', '=', 'kp.Id_Pengajuan')
            ->join('KPI_Pengajuan_Det as kd', 'kd.Id_Pengajuan_Detail', '=', 'kpd.Id_Pengajuan_Detail')
            ->whereBetween('kd.Periode', [$dari, $sampai])
            ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(ku.Kode_Users)))'), array_map(fn ($k) => strtoupper(trim($k)), $kode))
            ->whereNotExists(fn ($q) => $q->from('KPI_Update_Performance_Detail as kud')
                ->join('KPI_Update_Performance as ku2', 'ku2.Id_Update_Performance', '=', 'kud.Id_Update_Performance')
                ->whereColumn('kud.Id_Pengajuan_Det', 'kd.Id_Pengajuan_Det')
                ->where('ku2.Flag_Aktif', 'Y')->where('ku2.Flag_Approval', 'Y')->where('ku2.Flag_Verifikasi', 'Y')->whereNull('ku2.Status'))
            ->distinct()
            ->get([DB::raw('UPPER(LTRIM(RTRIM(ku.Kode_Users))) as kode'), 'kd.Periode']) as $r) {
            $hasil[$r->kode][] = (string) $r->Periode;
        }

        return array_map(function ($v) {
            $v = array_values(array_unique($v));
            sort($v);

            return $v;
        }, $hasil);
    }

    public function keranjang(int $cycleId): array
    {
        $kpiLike = $this->sqlSebab(self::SEBAB_TERTAHAN_KPI);
        $penilaiLike = $this->sqlSebab(self::SEBAB_TERTAHAN_PENILAI);
        $lainLike = $this->sqlSebabLain();
        $kodeKpiKurang = $this->sqlKodeKpiBelumLengkap($cycleId);
        $gugur = PaResultPublishService::sqlKategoriGugur('res');

        $r = DB::selectOne("
            SELECT
              SUM(CASE WHEN sisa.n = 0 AND res.Status_Hasil = 'BELUM_DIHITUNG'
                       THEN 1 ELSE 0 END) AS siap_dihitung,

              /* MENGUNCI: selama TERTAHAN, orangnya tidak bisa diterbitkan sama sekali.
                 Tiga keranjang sebab BOLEH TUMPANG TINDIH (lihat PENGGOLONG SEBAB TERTAHAN);
                 jumlah orangnya dihitung sendiri di `tertahan`. */
              SUM(CASE WHEN res.Status_Hasil = 'TERTAHAN' THEN 1 ELSE 0 END) AS tertahan,
              SUM(CASE WHEN res.Status_Hasil = 'TERTAHAN' AND {$kpiLike}
                       THEN 1 ELSE 0 END) AS needs_kpi,
              SUM(CASE WHEN res.Status_Hasil = 'TERTAHAN' AND {$penilaiLike}
                       THEN 1 ELSE 0 END) AS needs_rater,
              SUM(CASE WHEN res.Status_Hasil = 'TERTAHAN' AND {$lainLike}
                       THEN 1 ELSE 0 END) AS tertahan_lain,

              /* KPI belum lengkap: hasil belum terbit, tetapi masih ada bulan KPI periode siklus
                 tanpa realisasi yang disetujui + diverifikasi. Tidak mengunci; pengingat. */
              SUM(CASE WHEN res.Status_Hasil IN ('DIHITUNG', 'TERTAHAN', 'BELUM_DIHITUNG') AND kpikurang.n = 1
                       THEN 1 ELSE 0 END) AS kpi_belum_lengkap,

              /* Kategori penilai GUGUR (pengisi di bawah minimal) pada hasil yang siap terbit.
                 Penerbitan menahannya; keranjang ini membuatnya terlihat sebelum itu. */
              SUM(CASE WHEN res.Status_Hasil = 'DIHITUNG' AND kgugur.n = 1
                       THEN 1 ELSE 0 END) AS kategori_tidak_dihitung,

              /* TIDAK mengunci: menerbitkan tanpa kesimpulan admin tetap sah. Dipisah supaya
                 pekerjaan yang dianjurkan tidak terbaca sebagai pekerjaan yang menahan. */
              SUM(CASE WHEN res.Status_Hasil = 'DIHITUNG' AND kual.n > 0 AND catatan.n = 0
                       THEN 1 ELSE 0 END) AS needs_review_qualitative,
              SUM(CASE WHEN res.Status_Hasil = 'DIHITUNG'
                        AND NOT (kual.n > 0 AND catatan.n = 0)
                       THEN 1 ELSE 0 END) AS reviewing_calibration,

              SUM(CASE WHEN res.Status_Hasil = 'TERBIT'    THEN 1 ELSE 0 END) AS terbit,
              SUM(CASE WHEN susulan.n > 0                  THEN 1 ELSE 0 END) AS kiriman_susulan
            FROM N_PA_Participant p
            JOIN N_PA_Result res
              ON res.PA_Participant_Id = p.Id_PA_Participant AND res.Flag_Aktif = 'Y'
            CROSS APPLY (
                SELECT COUNT(*) AS n FROM N_PA_Assignment a
                 WHERE a.PA_Participant_Id = p.Id_PA_Participant
                   AND a.Flag_Aktif = 'Y'
                   AND a.Status_Penugasan NOT IN ('SELESAI', 'DIBATALKAN')
            ) sisa
            CROSS APPLY (
                SELECT COUNT(*) AS n
                  FROM N_PA_Assignment a2
                  JOIN N_PA_Assignment_Integration i ON i.PA_Assignment_Id = a2.Id_PA_Assignment
                  JOIN N_PA_Assignment_Payload pl
                    ON pl.PA_Assignment_Integration_Id = i.Id_PA_Assignment_Integration
                 WHERE a2.PA_Participant_Id = p.Id_PA_Participant
                   AND a2.Flag_Aktif = 'Y'
                   AND res.Published_At IS NOT NULL
                   AND pl.Diterima_At > res.Published_At
            ) susulan
            /* Jawaban KUALITATIF yang MASUK — bukan butir yang ditanyakan. Peserta yang levelnya
               tidak punya soal kualitatif tidak boleh mendarat di keranjang 'perlu
               disimpulkan'; tidak ada yang bisa ia simpulkan. Predikatnya milik tahap
               Kesimpulan Kualitatif supaya angka antrean dan isi tahap itu tidak berselisih.
               Dibungkus CROSS APPLY karena SQL Server menolak agregat atas subkueri. */
            CROSS APPLY (
                SELECT CASE WHEN ".PaKesimpulanKualitatifService::sqlAdaJawabanKualitatif('p').'
                            THEN 1 ELSE 0 END AS n
            ) kual
            /* Catatan yang DITULIS ADMIN — `PA_Assignment_Id` NULL. Catatan berasal-penilai
               tidak dihitung: ia bahan mentahnya, bukan kesimpulan yang dicari. */
            CROSS APPLY (
                SELECT CASE WHEN '.PaKesimpulanKualitatifService::sqlAdaKesimpulan('res')."
                            THEN 1 ELSE 0 END AS n
            ) catatan
            /* Himpunan, dihitung sekali — lihat sqlKodeKpiBelumLengkap(). */
            LEFT JOIN {$kodeKpiKurang} kpikurang
              ON kpikurang.kode = UPPER(LTRIM(RTRIM(p.Kode_Karyawan)))
            CROSS APPLY (
                SELECT CASE WHEN {$gugur} THEN 1 ELSE 0 END AS n
            ) kgugur
            WHERE p.PA_Cycle_Id = ? AND p.Flag_Aktif = 'Y' AND p.Status_Peserta = 'DIIKUTKAN'
        ", [$cycleId]);

        $needsKpi = (int) $r->needs_kpi;
        $needsRater = (int) $r->needs_rater;
        $tertahanLain = (int) $r->tertahan_lain;
        $perluDisimpulkan = (int) $r->needs_review_qualitative;
        $sedangDikalibrasi = (int) $r->reviewing_calibration;

        return [
            /* Mestinya mendekati NOL sejak perhitungan diantrekan otomatis begitu penilai
               terakhir selesai. Angka yang tidak nol di sini bukan sekadar pekerjaan — ia
               alarm bahwa antreannya tidak jalan. Karena itu keranjangnya dipertahankan. */
            'siap_dihitung' => (int) $r->siap_dihitung,

            /* DUA ANGKA RINGKASAN, dan keduanya WAJIB ada.
               |
               | `daftar()` melayani jenis 'tertahan' dan 'perlu_verifikasi' sebagai keranjang
               | tersendiri, tetapi ringkasan ini dulu tidak pernah mengirim angkanya. Layar
               | jadi punya daftar yang bisa dibuka tanpa kartu yang menunjukkan isinya —
               | keranjang yang tak terlihat sama saja dengan keranjang yang tidak ada.
               |
               | `tertahan` dihitung SENDIRI (jumlah orang), bukan dijumlahkan dari tiga
               | sebabnya: sejak 8 Okt 2026 satu orang bisa berada di beberapa keranjang sebab
               | sekaligus, sehingga penjumlahannya menghitung orang yang sama dua kali.
               | Saringan `isiKeranjang('tertahan')` memakai syarat yang sama persis
               | (Status_Hasil = TERTAHAN), jadi kartu dan daftarnya tetap sepakat.
               |
               | `perlu_verifikasi` tetap diturunkan dari dua pecahannya, yang memang saling
               | lepas (satu NOT dari yang lain). */
            'tertahan' => (int) $r->tertahan,
            'perlu_verifikasi' => $perluDisimpulkan + $sedangDikalibrasi,

            'needs_kpi' => $needsKpi,
            'needs_rater' => $needsRater,
            'tertahan_lain' => $tertahanLain,
            'kpi_belum_lengkap' => (int) $r->kpi_belum_lengkap,
            'kategori_tidak_dihitung' => (int) $r->kategori_tidak_dihitung,
            'needs_review_qualitative' => $perluDisimpulkan,
            'reviewing_calibration' => $sedangDikalibrasi,
            'terbit' => (int) $r->terbit,
            'kiriman_susulan' => (int) $r->kiriman_susulan,
        ];
    }

    /* Dua potongan SQL ini dipakai penggolong DAN saringan, dan keduanya milik tahap
       Kesimpulan Kualitatif: angka di kartu, isi daftarnya, dan isi tahap itu harus lahir
       dari aturan yang sama persis. */
    private function sqlAdaJawabanKualitatif(): string
    {
        return PaKesimpulanKualitatifService::sqlAdaJawabanKualitatif('p');
    }

    private function sqlAdaCatatanAdmin(): string
    {
        return PaKesimpulanKualitatifService::sqlAdaKesimpulan('res');
    }

    /**
     | Isi satu keranjang, berpaginasi.
     |
     | @return array<string, mixed>
     */
    public function isiKeranjang(int $cycleId, string $jenis, int $halaman = 1, int $perHalaman = 25, ?string $cari = null): array
    {
        $perHalaman = max(1, min($perHalaman, self::MAKS_PER_HALAMAN));
        $cari = trim((string) $cari);

        $dasar = fn () => DB::table('N_PA_Participant as p')
            ->join('N_PA_Result as res', function ($j) {
                $j->on('res.PA_Participant_Id', '=', 'p.Id_PA_Participant')
                    ->where('res.Flag_Aktif', '=', 'Y');
            })
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            /* PENCARIAN DI SERVER (koreksi 8 Okt 2026), dengan aturan yang sama dengan tab
               Hasil (`PaParticipantReadService::kueriTersaring`): nama di mana saja, NIK dari
               depan. Dulu layar menyaring 25 baris halaman yang sedang terbuka saja, lalu
               menyatakan orangnya "tidak ditemukan pada kategori antrean ini" padahal ia ada
               di halaman lain. Total dan paginasinya ikut tersaring. */
            ->when($cari !== '', fn ($q) => $q->where(function ($w) use ($cari) {
                $w->where('p.Nama_Karyawan_Saat_Cycle', 'LIKE', '%'.$cari.'%')
                    ->orWhere('p.Kode_Karyawan', 'LIKE', $cari.'%');
            }));

        $saring = function ($q) use ($jenis, $cycleId) {
            return match ($jenis) {
                'siap_dihitung' => $q->where('res.Status_Hasil', 'BELUM_DIHITUNG')
                    ->whereRaw("NOT EXISTS (SELECT 1 FROM N_PA_Assignment a
                                 WHERE a.PA_Participant_Id = p.Id_PA_Participant
                                   AND a.Flag_Aktif = 'Y'
                                   AND a.Status_Penugasan NOT IN ('SELESAI','DIBATALKAN'))"),
                /* Saringan di sini adalah cerminan persis dari penggolong pada
                   `keranjang()` — potongan SQL-nya sama — supaya angka di kartu dan isi
                   daftarnya tidak berselisih; yang dipercaya orang adalah yang dibukanya,
                   yaitu daftarnya. Keranjang sebab tertahan BOLEH tumpang tindih. */
                'tertahan' => $q->where('res.Status_Hasil', 'TERTAHAN'),
                'perlu_verifikasi' => $q->where('res.Status_Hasil', 'DIHITUNG'),
                'needs_kpi' => $q->where('res.Status_Hasil', 'TERTAHAN')
                    ->whereRaw($this->sqlSebab(self::SEBAB_TERTAHAN_KPI)),

                'needs_rater' => $q->where('res.Status_Hasil', 'TERTAHAN')
                    ->whereRaw($this->sqlSebab(self::SEBAB_TERTAHAN_PENILAI)),

                'tertahan_lain' => $q->where('res.Status_Hasil', 'TERTAHAN')
                    ->whereRaw($this->sqlSebabLain()),

                'kpi_belum_lengkap' => $q->whereIn('res.Status_Hasil', ['DIHITUNG', 'TERTAHAN', 'BELUM_DIHITUNG'])
                    ->whereRaw($this->sqlKpiBelumLengkap($cycleId)),

                'kategori_tidak_dihitung' => $q->where('res.Status_Hasil', 'DIHITUNG')
                    ->whereRaw(PaResultPublishService::sqlKategoriGugur('res')),

                'needs_review_qualitative' => $q->where('res.Status_Hasil', 'DIHITUNG')
                    ->whereRaw($this->sqlAdaJawabanKualitatif())
                    ->whereRaw('NOT '.$this->sqlAdaCatatanAdmin()),

                'reviewing_calibration' => $q->where('res.Status_Hasil', 'DIHITUNG')
                    ->whereRaw('NOT ('.$this->sqlAdaJawabanKualitatif()
                        .' AND NOT '.$this->sqlAdaCatatanAdmin().')'),

                'terbit' => $q->where('res.Status_Hasil', 'TERBIT'),
                'kiriman_susulan' => $q->whereNotNull('res.Published_At')
                    ->whereRaw("EXISTS (SELECT 1
                                  FROM N_PA_Assignment a2
                                  JOIN N_PA_Assignment_Integration i ON i.PA_Assignment_Id = a2.Id_PA_Assignment
                                  JOIN N_PA_Assignment_Payload pl ON pl.PA_Assignment_Integration_Id = i.Id_PA_Assignment_Integration
                                 WHERE a2.PA_Participant_Id = p.Id_PA_Participant
                                   AND a2.Flag_Aktif = 'Y'
                                   AND pl.Diterima_At > res.Published_At)"),

                /* Jenis yang tak dikenal DITOLAK, bukan diperlakukan sebagai "semua". Salah
                   ketik nama keranjang harus gagal keras, bukan diam-diam mengembalikan
                   seluruh siklus sebagai daftar pekerjaan. */
                default => throw new RuntimeException("Keranjang '{$jenis}' tidak dikenal."),
            };
        };

        $total = (int) $saring($dasar())->count();

        $baris = $saring($dasar())
            ->orderBy('p.Nama_Karyawan_Saat_Cycle')
            ->orderBy('p.Id_PA_Participant')
            ->offset(($halaman - 1) * $perHalaman)
            ->limit($perHalaman)
            ->get([
                'p.Id_PA_Participant', 'p.Kode_Karyawan', 'p.Nama_Karyawan_Saat_Cycle',
                'p.Nama_Jabatan_Saat_Cycle', 'p.Nama_Divisi_Saat_Cycle', 'p.Nama_Level_Saat_Cycle',
                'res.Id_PA_Result', 'res.Status_Hasil', 'res.Nilai_KPI', 'res.Nilai_Kompetensi',
                'res.Final_Score', 'res.Kode_Grade_Final', 'res.Alasan_Tertahan',
                'res.Published_At', 'res.Calculated_At',

                /* KPI yang baru sebagian disetujui TIDAK menahan perhitungan — angkanya
                   dihitung dari periode yang sudah ada. Untuk PENERBITAN itu masalah besar:
                   hasil terbit tidak bisa dihitung ulang, sehingga persetujuan yang menyusul
                   tidak akan pernah memperbaikinya. Karena itu angkanya ikut ke layar. */
                'res.Jumlah_KPI_Belum_Disetujui',
            ])
            ->map(fn ($b) => (array) $b)
            ->values()
            ->all();

        // Kategori yang gugur ikut dikirim supaya admin tahu penilai mana yang harus ditagih.
        if ($jenis === 'kategori_tidak_dihitung' && $baris !== []) {
            $gugur = PaResultPublishService::kategoriGugur(array_map(fn ($b) => (int) $b['Id_PA_Result'], $baris));
            foreach ($baris as $i => $b) {
                $baris[$i]['kategori_gugur'] = $gugur[(int) $b['Id_PA_Result']] ?? [];
            }
        }

        // Bulan yang kurang ikut dikirim supaya admin tahu apa yang harus dikejar, bukan sekadar "kurang".
        if ($jenis === 'kpi_belum_lengkap' && $baris !== []) {
            $kurang = $this->bulanKpiKurang($cycleId, array_column($baris, 'Kode_Karyawan'));
            foreach ($baris as $i => $b) {
                $baris[$i]['bulan_kpi_kurang'] = $kurang[strtoupper(trim((string) $b['Kode_Karyawan']))] ?? [];
            }
        }

        return [
            'jenis' => $jenis,
            'baris' => $baris,
            'halaman' => $halaman,
            'per_halaman' => $perHalaman,
            'total' => $total,
            'total_halaman' => (int) ceil($total / $perHalaman),
        ];
    }

    /**
     | Cacah pengisian se-siklus. SATU kueri, bukan satu per angka.
     |
     | @return array<string, int|float>
     */
    private function ringkasanPengisian(int $cycleId): array
    {
        $r = $this->dasarPenugasan($cycleId)
            ->selectRaw("
                COUNT(*) AS total,
                SUM(CASE WHEN a.Status_Penugasan = 'SELESAI' THEN 1 ELSE 0 END) AS selesai,
                SUM(CASE WHEN a.Status_Penugasan = 'BERJALAN' THEN 1 ELSE 0 END) AS berjalan,
                SUM(CASE WHEN a.Status_Penugasan = 'BELUM_MULAI' THEN 1 ELSE 0 END) AS belum_mulai,

                /* Terisi penuh, tetapi penilainya belum menekan kirim - dan karena itu
                   BELUM ikut dihitung. Dipisah dari `selesai` dengan sengaja: menyatukannya
                   membuat siklus tampak 100% pada layar admin sementara tidak satu pun
                   hasilnya boleh lahir. */
                SUM(CASE WHEN a.Status_Penugasan = 'TERKUMPUL' THEN 1 ELSE 0 END) AS terkumpul,

                /* Sudah dikirim, tinggal menunggu pengesah HC Learn. Dulu ikut `total` tetapi
                   tidak masuk keranjang mana pun, sehingga kartu dan chip tidak pernah
                   berjumlah sama dengan totalnya. */
                SUM(CASE WHEN a.Status_Penugasan = '".PaStatusPenugasan::MENUNGGU_VERIFIKASI."' THEN 1 ELSE 0 END) AS menunggu_verifikasi,

                /* Orang yang DINILAI, atas seluruh siklus — bukan baris halaman yang sedang
                   terbuka (itu hanya 10 orang, sementara siklusnya berisi ratusan). */
                COUNT(DISTINCT a.PA_Participant_Id) AS karyawan_dinilai,

                /* Definisi KETAT, sama dengan saringan TERLAMBAT di daftar: lembar terisi yang
                   belum dikirim dan lewat tenggat ikut ditagih. Sebelumnya kartu memakai versi
                   longgar, sehingga angka kartu lebih kecil dari isi daftar saat diklik. */
                SUM(CASE WHEN ".$this->sqlTerlambat().' THEN 1 ELSE 0 END) AS terlambat
            ')
            ->first();

        $total = (int) $r->total;

        return [
            'total' => $total,
            'selesai' => (int) $r->selesai,
            'berjalan' => (int) $r->berjalan,
            'belum_mulai' => (int) $r->belum_mulai,
            'terkumpul' => (int) $r->terkumpul,
            'menunggu_verifikasi' => (int) $r->menunggu_verifikasi,
            'terlambat' => (int) $r->terlambat,
            'karyawan_dinilai' => (int) $r->karyawan_dinilai,

            /* Persennya dihitung di sini, bukan di layar. Dua tempat yang membulatkan dengan
               caranya sendiri menghasilkan dua angka untuk satu gagasan. */
            'persen' => self::persenTuntas((int) $r->selesai, $total),
        ];
    }

    /**
     | Rekap per KATEGORI PENILAI, dikunci kode kategori.
     |
     | Bentuknya peta — bukan daftar — karena yang membacanya menanyakan "berapa persen ATASAN
     | sudah mengisi", bukan "kategori keberapa".
     |
     | @return array<string, array<string, mixed>>
     */
    private function rekapKategori(int $cycleId): array
    {
        return $this->dasarPenugasan($cycleId)
            ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id')
            ->groupBy('c.Kode_Kategori', 'c.Nama_Kategori')
            ->orderBy('c.Nama_Kategori')
            ->selectRaw("c.Kode_Kategori AS kode, c.Nama_Kategori AS nama, COUNT(*) AS total,
                         SUM(CASE WHEN a.Status_Penugasan = 'SELESAI' THEN 1 ELSE 0 END) AS selesai")
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->kode => [
                'kode' => (string) $r->kode,
                'nama' => (string) $r->nama,
                'total' => (int) $r->total,
                'selesai' => (int) $r->selesai,
                'persen' => self::persenTuntas((int) $r->selesai, (int) $r->total),
            ]])
            ->all();
    }

    /**
     | SELURUH penugasan siklus — dasar bagi pemantauan pengisian.
     |
     | Dipisahkan dari `kueriTerlambat()` karena keduanya menjawab pertanyaan yang berbeda:
     | yang itu "siapa yang sudah lewat tenggat", yang ini "bagaimana pengisian berjalan".
     | Sebelumnya hanya ada yang pertama, sementara halaman pemantauan dibangun untuk yang
     | kedua — lengkap dengan pencarian, chip status, dan saringan kategori. Akibatnya tabelnya
     | kosong justru ketika semuanya sehat: nol orang terlambat terbaca sebagai nol data.
     |
     | Saringannya SATU tempat untuk kedua jalur. Menyalinnya akan membuat "cari nama" bekerja
     | di satu tabel dan tidak di tabel sebelahnya, pada halaman yang sama.
     */
    private function kueriPenugasan(int $cycleId, array $saringan)
    {
        $q = $this->dasarPenugasan($cycleId)
            ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id');

        if (! empty($saringan['id_divisi'])) {
            $q->where('p.ID_Divisi_Saat_Cycle', (int) $saringan['id_divisi']);
        }

        if (! empty($saringan['id_level'])) {
            $q->where('p.ID_Level_Saat_Cycle', (int) $saringan['id_level']);
        }

        if (! empty($saringan['pa_rater_category_id'])) {
            $q->where('a.PA_Rater_Category_Id', (int) $saringan['pa_rater_category_id']);
        }

        if (! empty($saringan['status'])) {
            if ($saringan['status'] === 'TERLAMBAT') {
                $this->syaratTerlambat($q);
            } elseif ($saringan['status'] === 'BELUM_SELESAI') {
                /* Isi kartu "Belum Selesai": SEMUA yang belum SELESAI — belum mulai, sedang
                   mengisi, terisi belum dikirim, dan menunggu verifikasi HC Learn. Dulu hanya
                   dua yang pertama, sementara "% sisa" di kartu yang sama dihitung dari
                   100 − persen selesai; angka dan persennya tidak pernah cocok. */
                $q->whereNotIn('a.Status_Penugasan', PaStatusPenugasan::TUNTAS);
            } else {
                $q->where('a.Status_Penugasan', $saringan['status']);
            }
        }

        $cari = trim((string) ($saringan['q'] ?? ''));

        if ($cari !== '') {
            // Nama yang dinilai ATAU nama penilainya — orang mencari dengan nama yang
            // diingatnya, dan ia belum tentu ingat perannya yang mana.
            $q->where(function ($w) use ($cari) {
                $w->where('p.Nama_Karyawan_Saat_Cycle', 'LIKE', '%'.$cari.'%')
                    ->orWhere('a.Nama_Penilai_Saat_Cycle', 'LIKE', '%'.$cari.'%')
                    // NIK yang dinilai maupun penilainya — awalan, supaya tetap memakai indeks.
                    ->orWhere('p.Kode_Karyawan', 'LIKE', $cari.'%')
                    ->orWhere('a.Kode_Karyawan_Penilai', 'LIKE', $cari.'%');
            });
        }

        return $q;
    }

    /**
     * Pilihan saringan yang BENAR-BENAR ada di siklus ini.
     *
     * Diambil dari peserta siklusnya, bukan dari master. Dropdown berisi divisi yang tak
     * seorang pun di siklus ini menempatinya hanya membuat orang menyaring lalu menatap
     * tabel kosong dan mengira sistemnya rusak.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function pilihanSaringan(int $cycleId): array
    {
        $divisi = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->whereNotNull('ID_Divisi_Saat_Cycle')
            ->groupBy('ID_Divisi_Saat_Cycle', 'Nama_Divisi_Saat_Cycle')
            ->orderBy('Nama_Divisi_Saat_Cycle')
            ->get(['ID_Divisi_Saat_Cycle as id', 'Nama_Divisi_Saat_Cycle as nama'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'nama' => $r->nama])
            ->all();

        $level = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->whereNotNull('ID_Level_Saat_Cycle')
            ->groupBy('ID_Level_Saat_Cycle', 'Nama_Level_Saat_Cycle')
            ->orderBy('Nama_Level_Saat_Cycle')
            ->get(['ID_Level_Saat_Cycle as id', 'Nama_Level_Saat_Cycle as nama'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'nama' => $r->nama])
            ->all();

        $kategori = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('a.Flag_Aktif', 'Y')
            ->groupBy('c.Id_PA_Rater_Category', 'c.Kode_Kategori', 'c.Nama_Kategori')
            ->orderBy('c.Nama_Kategori')
            ->get(['c.Id_PA_Rater_Category as id', 'c.Kode_Kategori as kode', 'c.Nama_Kategori as nama'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'kode' => $r->kode, 'nama' => $r->nama])
            ->all();

        return [
            'divisi' => $divisi,
            'level' => $level,
            'kategori_penilai' => $kategori,
            /* SELESAI ikut sejak daftar penuh disajikan. Sebelumnya sengaja dikecualikan
               karena satu-satunya daftar yang ada adalah "yang terlambat", dan di sana SELESAI
               memang mustahil muncul — menyaringnya hanya akan menghasilkan tabel kosong. */
            'status_penugasan' => array_map(
                fn ($s) => ['kode' => $s, 'nama' => $s],
                ['BELUM_MULAI', 'BERJALAN', 'SELESAI'],
            ),
        ];
    }
}
