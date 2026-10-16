<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pembaca seluruh master Performance Appraisal.
 *
 * SATU BENTUK RESPONS UNTUK DELAPAN LAYAR
 *
 * Kedelapan master dibaca lewat satu jalur dengan bentuk yang sama: `baris`, `halaman`,
 * `per_halaman`, `total`, `total_halaman`, `dipangkas`. Layar yang berbeda-beda bentuk
 * responsnya memaksa frontend menulis delapan pembaca, dan delapan pembaca berarti
 * delapan tempat yang bisa berselisih dengan backend.
 *
 * YANG BOLEH DIUBAH BERBEDA-BEDA, DAN ITU DISENGAJA
 *
 * `dapat_dinonaktifkan` dan `dapat_ditambah` ikut dikirim per master. Frontend tidak
 * perlu menghafal mana yang kamus tertutup dan mana yang bebas tumbuh — dan kalau
 * aturannya berubah, tombolnya ikut berubah tanpa merilis frontend.
 *
 * KENAPA PAKET SOAL BUKAN MASTER
 *
 * `N_PA_Cycle_Question_Package` dan butirnya menggantung pada SIKLUS, bukan berdiri
 * sendiri lintas siklus. Keduanya tetap disediakan di sini karena layarnya memang
 * dibutuhkan, tetapi ditandai `lingkup: "siklus"` dan WAJIB menyertakan `cycle_id`.
 * Menyebutnya master akan membuat orang mengira menyuntingnya berlaku untuk semua
 * siklus — padahal siklus yang sudah berjalan memakai salinannya sendiri.
 */
class PaMasterReadService
{
    public const MAKS_PER_HALAMAN = 200;

    /**
     * Daftar master beserta sifatnya.
     *
     * @var array<string, array<string, mixed>>
     */
    private const KATALOG = [
        'jenis-kompetensi' => [
            'halaman' => 'PaMasterJenisKompetensiPage',
            'tabel' => 'N_PA_Competency_Type',
            'kunci' => 'Id_PA_Competency_Type',
            'nama' => 'Jenis Kompetensi',
            'lingkup' => 'master',
            'cari' => ['Kode_Type', 'Nama_Type'],
            'urut' => ['Urutan_Tampil', 'Kode_Type'],
            'kolom' => ['Id_PA_Competency_Type', 'Kode_Type', 'Nama_Type', 'Deskripsi',
                'Urutan_Tampil', 'Flag_Aktif'],
            'dapat_ditambah' => true,
            'dapat_dinonaktifkan' => true,
        ],
        'kompetensi' => [
            'halaman' => 'PaMasterKompetensiPage',
            'tabel' => 'N_PA_Competency',
            'kunci' => 'Id_PA_Competency',
            'nama' => 'Kompetensi',
            'lingkup' => 'master',
            'cari' => ['Kode_Competency', 'Nama_Competency'],
            'urut' => ['Urutan_Tampil', 'Kode_Competency'],
            'kolom' => ['Id_PA_Competency', 'PA_Competency_Type_Id', 'Kode_Competency',
                // Expected_Behaviour TIDAK lagi dibaca (10 Okt 2026): perilaku diisi per tingkat
                // (N_PA_Competency_Proficiency). Kolomnya dibiarkan di tabel, tidak dihapus.
                'Nama_Competency', 'Deskripsi', 'Flag_Berskor', 'Nilai_Ambang', 'Flag_Radar',
                'Urutan_Tampil', 'Flag_Aktif'],
            'dapat_ditambah' => true,
            'dapat_dinonaktifkan' => true,
        ],
        'kategori-penilai' => [
            'halaman' => 'PaMasterKategoriPenilaiPage',
            'tabel' => 'N_PA_Rater_Category',
            'kunci' => 'Id_PA_Rater_Category',
            'nama' => 'Kategori Penilai',
            'lingkup' => 'master',
            'cari' => ['Kode_Kategori', 'Nama_Kategori'],
            'urut' => ['Id_PA_Rater_Category'],
            'kolom' => ['Id_PA_Rater_Category', 'Kode_Kategori', 'Nama_Kategori',
                'Deskripsi', 'Flag_Aktif'],
            // Kodenya menjadi kunci asing di aturan penilai, penugasan, dan jejak hasil.
            // Menambah kategori baru tanpa memberinya aturan di setiap level hanya
            // menghasilkan kategori yang tak pernah muncul; menonaktifkannya memutus
            // aturan yang sedang dipakai siklus berjalan.
            'dapat_ditambah' => false,
            'dapat_dinonaktifkan' => false,
        ],
        'aturan-penilai' => [
            'halaman' => 'PaMasterAturanPenilaiPage',
            'tabel' => 'N_PA_Rater_Policy',
            'kunci' => 'Id_PA_Rater_Policy',
            'nama' => 'Aturan Penilai',
            'lingkup' => 'master',
            'cari' => ['Kode_Policy', 'Nama_Policy'],
            'urut' => ['ID_Level', 'Kode_Policy', 'Versi'],
            'kolom' => ['Id_PA_Rater_Policy', 'Kode_Policy', 'Nama_Policy', 'Deskripsi',
                'ID_Level', 'Versi', 'Status_Policy', 'Berlaku_Dari', 'Berlaku_Sampai',
                'Catatan_Versi', 'Flag_Aktif', 'Activated_At'],
            'dapat_ditambah' => true,
            'dapat_dinonaktifkan' => true,
        ],
        'bobot-pilar' => [
            'halaman' => 'PaMasterBobotPilarPage',
            'tabel' => 'N_PA_Pillar_Weight',
            'kunci' => 'Id_PA_Pillar_Weight',
            'nama' => 'Bobot KPI & Kompetensi',
            'lingkup' => 'master',
            'cari' => [],
            'urut' => ['ID_Level'],
            'kolom' => ['Id_PA_Pillar_Weight', 'ID_Level', 'Bobot_KPI', 'Bobot_Kompetensi', 'Catatan'],
            // Tabelnya TIDAK punya Flag_Aktif: satu level punya tepat satu bobot, dan
            // "menonaktifkan" bobot pilar berarti level itu tak bisa dinilai sama sekali.
            'dapat_ditambah' => true,
            'dapat_dinonaktifkan' => false,
        ],
        'pita-nilai' => [
            'halaman' => 'PaMasterPitaNilaiPage',
            'tabel' => 'N_PA_Score_Range',
            'kunci' => 'Id_PA_Score_Range',
            'nama' => 'Rentang Nilai',
            'lingkup' => 'master',
            'cari' => ['Kode_Range', 'Nama_Range'],
            'urut' => ['Score_Type', 'Score_Usage', 'Urutan_Tampil'],
            'kolom' => ['Id_PA_Score_Range', 'Score_Type', 'Score_Usage', 'Kode_Range', 'Nama_Range',
                'Nilai_Dari', 'Nilai_Sampai', 'Batas_Z', 'Nilai_Hasil', 'Target_Persen', 'Warna',
                'Urutan_Tampil', 'Flag_Aktif'],
            'dapat_ditambah' => true,
            'dapat_dinonaktifkan' => true,
        ],
        'kuadran-hav' => [
            'halaman' => 'PaMasterKuadranHavPage',
            'tabel' => 'N_PA_Hav_Quadrant',
            'kunci' => 'Id_PA_Hav_Quadrant',
            'nama' => 'Kuadran HAV',
            'lingkup' => 'master',
            'cari' => ['Kode_Kuadran', 'Nama_Kuadran'],
            'urut' => ['Urutan_Tampil', 'Nomor_Box'],
            'kolom' => ['Id_PA_Hav_Quadrant', 'Kode_Kuadran', 'Nama_Kuadran', 'Nomor_Box',
                'Tingkat_Performa', 'Tingkat_Kompetensi', 'Deskripsi', 'Warna',
                'Urutan_Tampil', 'Flag_Aktif'],
            // Matriksnya 3x3 dan sudah penuh; unique index melarang dua kotak menempati
            // sel yang sama. Kotak kesepuluh tidak punya tempat untuk berdiri.
            'dapat_ditambah' => false,
            'dapat_dinonaktifkan' => false,
        ],
        'paket-soal' => [
            'halaman' => 'PaMasterPaketSoalPage',
            'tabel' => 'N_PA_Cycle_Question_Package',
            'kunci' => 'Id_PA_Cycle_Question_Package',
            'nama' => 'Paket Soal',
            'lingkup' => 'siklus',
            'cari' => ['Id_Master_Ujian', 'Nama_Ujian_Sumber'],
            'urut' => ['ID_Level'],
            'kolom' => ['Id_PA_Cycle_Question_Package', 'PA_Cycle_Id', 'ID_Level',
                'Id_Master_Ujian', 'Nama_Ujian_Sumber', 'Sumber_Kontrak', 'Catatan', 'Flag_Aktif'],
            'dapat_ditambah' => true,
            'dapat_dinonaktifkan' => true,
        ],
        'butir-soal' => [
            'halaman' => 'PaMasterPaketSoalPage',
            'tabel' => 'N_PA_Cycle_Question_Package_Item',
            'kunci' => 'Id_PA_Cycle_Question_Package_Item',
            'nama' => 'Pemetaan Soal ke Kompetensi',
            'lingkup' => 'paket',
            'cari' => ['Id_Jenis_Soal', 'Nama_Bagian_Sumber'],
            'urut' => ['Urutan_Tampil', 'Id_Jenis_Soal'],
            'kolom' => ['Id_PA_Cycle_Question_Package_Item', 'PA_Cycle_Question_Package_Id',
                'Id_Jenis_Soal', 'Nama_Bagian_Sumber', 'Jenis_Soal', 'PA_Competency_Id',
                'Bobot_Penilaian', 'Skor_Maksimal',
                'Urutan_Tampil', 'Flag_Aktif'],
            'dapat_ditambah' => true,
            'dapat_dinonaktifkan' => true,
        ],
    ];

    /**
     * Kunci halaman satu master.
     *
     * Tiap master punya kunci sendiri, bukan satu kunci untuk semuanya. Itu bukan
     * kerumitan tambahan melainkan satu-satunya cara memberi seseorang hak mengubah
     * kompetensi TANPA sekaligus memberinya hak mengubah pita grade — dan pita grade
     * mengubah arti nilai semua orang di siklus berikutnya.
     *
     * Butir soal berbagi kunci dengan paket soal: keduanya satu layar, dan memisahkannya
     * hanya menghasilkan hak yang membuka layar tanpa bisa mengisinya.
     */
    public static function halamanMaster(string $master): string
    {
        return self::katalog($master)['halaman'];
    }

    /**
     * @return list<string>
     */
    public static function daftarMaster(): array
    {
        return array_keys(self::KATALOG);
    }

    /**
     * @return array<string, mixed>
     */
    public static function katalog(string $master): array
    {
        if (! isset(self::KATALOG[$master])) {
            throw new RuntimeException(
                "Master {$master} tidak dikenal. Yang tersedia: "
                .implode(', ', array_keys(self::KATALOG)).'.'
            );
        }

        return self::KATALOG[$master];
    }

    /**
     * Satu halaman baris master.
     *
     * @param  array{q?:string|null, flag_aktif?:string|null, cycle_id?:int|null,
     *               package_id?:int|null, id_level?:int|null, sumbu?:string|null,
     *               kegunaan?:string|null, halaman?:int, per_halaman?:int}  $saringan
     * @return array<string, mixed>
     */
    public function daftar(string $master, array $saringan = []): array
    {
        $k = self::katalog($master);

        $halaman = max(1, (int) ($saringan['halaman'] ?? 1));
        $diminta = (int) ($saringan['per_halaman'] ?? 50);
        $perHalaman = max(1, min($diminta, self::MAKS_PER_HALAMAN));

        $dasar = fn () => $this->kueri($k, $saringan);

        $total = (int) $dasar()->count();

        $kueri = $dasar();

        foreach ($k['urut'] as $kolom) {
            if ($kolom === $k['kunci']) {
                continue;
            }

            $kueri->orderBy($kolom);
        }

        // Kunci utama selalu jadi pengurut TERAKHIR; tanpa itu baris bisa berpindah
        // halaman di antara dua permintaan. Kalau ia sudah disebut di daftar urut,
        // penyebutannya dilewati - SQL Server menolak kolom yang sama dua kali.
        $baris = $kueri->orderBy($k['kunci'])
            ->offset(($halaman - 1) * $perHalaman)
            ->limit($perHalaman)
            ->get($k['kolom'])
            ->map(fn ($b) => (array) $b)
            ->all();

        return [
            'master' => $master,
            'nama' => $k['nama'],
            'lingkup' => $k['lingkup'],
            'dapat_ditambah' => $k['dapat_ditambah'],
            'dapat_dinonaktifkan' => $k['dapat_dinonaktifkan'],
            'baris' => $baris,
            'halaman' => $halaman,
            'per_halaman' => $perHalaman,
            'total' => $total,
            'total_halaman' => (int) ceil($total / $perHalaman),
            'dipangkas' => $diminta > self::MAKS_PER_HALAMAN,
        ];
    }

    /**
     * Satu baris master. Untuk aturan penilai, rincian kategorinya ikut.
     *
     * @return array<string, mixed>
     */
    public function detail(string $master, int $id): array
    {
        $k = self::katalog($master);

        $baris = DB::table($k['tabel'])->where($k['kunci'], $id)->first($k['kolom']);

        if ($baris === null) {
            throw new RuntimeException("{$k['nama']} #{$id} tidak ada.");
        }

        $hasil = [
            'master' => $master,
            'nama' => $k['nama'],
            'baris' => (array) $baris,
        ];

        if ($master === 'aturan-penilai') {
            $hasil['detail'] = $this->detailAturanPenilai($id);
        }

        if ($master === 'paket-soal') {
            $hasil['butir'] = $this->daftar('butir-soal', ['package_id' => $id, 'per_halaman' => 200])['baris'];
        }

        return $hasil;
    }

    /**
     * Rincian bobot per kategori penilai satu aturan.
     *
     * @return list<array<string, mixed>>
     */
    public function detailAturanPenilai(int $policyId): array
    {
        return DB::table('N_PA_Rater_Policy_Detail as d')
            ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'd.PA_Rater_Category_Id')
            ->where('d.PA_Rater_Policy_Id', $policyId)
            ->where('d.Flag_Aktif', 'Y')
            ->orderBy('d.Urutan_Tampil')
            ->get([
                'd.Id_PA_Rater_Policy_Detail', 'd.PA_Rater_Category_Id',
                'c.Kode_Kategori', 'c.Nama_Kategori',
                'd.Organization_Relationship_Type_Id', 'd.Bobot_Persen',
                'd.Min_Rater', 'd.Maks_Rater', 'd.Min_Rater_Selesai',
                'd.Flag_Izinkan_Manual', 'd.Urutan_Tampil', 'd.Catatan_Aturan',
            ])
            // Nama dari master apa adanya; "Peer" hanya untuk halaman PA Saya (PaPesertaService).
            ->map(fn ($b) => (array) $b)
            ->all();
    }

    /**
     * Seluruh daftar acuan yang dibutuhkan layar master dalam SATU panggilan.
     *
     * Delapan layar yang masing-masing mengambil daftar levelnya sendiri berarti delapan
     * kali menanyakan hal yang sama. Semuanya kecil dan jarang berubah, jadi dikirim
     * sekaligus.
     *
     * @return array<string, mixed>
     */
    public function referensi(): array
    {
        PaLevelMasterService::sinkronHris();

        return [
            'level' => DB::table('N_PA_Level')->where('Flag_Aktif', 'Y')
                ->orderBy('Urutan')->orderBy('ID_Level')
                ->get(['ID_Level', 'Nama_Level', 'Urutan as Level_Hierarchy'])
                ->map(fn ($b) => (array) $b)->all(),
            'jenis_kompetensi' => DB::table('N_PA_Competency_Type')
                ->where('Flag_Aktif', 'Y')->orderBy('Urutan_Tampil')
                ->get(['Id_PA_Competency_Type', 'Kode_Type', 'Nama_Type'])
                ->map(fn ($b) => (array) $b)->all(),
            'kategori_penilai' => DB::table('N_PA_Rater_Category')
                ->where('Flag_Aktif', 'Y')->orderBy('Id_PA_Rater_Category')
                ->get(['Id_PA_Rater_Category', 'Kode_Kategori', 'Nama_Kategori'])
                ->map(fn ($b) => (array) $b)->all(),
            'jenis_relasi' => DB::table('N_HRIS_Organization_Relationship_Type')
                ->where('Flag_Aktif', 'Y')->orderBy('Urutan')
                ->get(['Id_Organization_Relationship_Type', 'Kode_Relasi', 'Nama_Relasi'])
                ->map(fn ($b) => (array) $b)->all(),
            'status_aturan' => DB::table('N_PA_Rater_Policy_Status')
                ->orderBy('Urutan_Alur')
                ->get(['Kode_Status', 'Nama_Status', 'Deskripsi'])
                ->map(fn ($b) => (array) $b)->all(),
            'sumbu' => DB::table('N_PA_Score_Type')->where('Flag_Aktif', 'Y')
                ->get(['Kode_Type', 'Nama_Type'])->map(fn ($b) => (array) $b)->all(),
            'kegunaan' => DB::table('N_PA_Score_Usage')->where('Flag_Aktif', 'Y')
                ->get(['Kode_Usage', 'Nama_Usage'])->map(fn ($b) => (array) $b)->all(),
            'kompetensi' => DB::table('N_PA_Competency')
                ->where('Flag_Aktif', 'Y')->orderBy('Urutan_Tampil')
                ->get(['Id_PA_Competency', 'Kode_Competency', 'Nama_Competency', 'PA_Competency_Type_Id'])
                ->map(fn ($b) => (array) $b)->all(),
            'siklus' => DB::table('N_PA_Cycle')
                ->where('Flag_Aktif', 'Y')->orderByDesc('Tahun')->orderByDesc('Id_PA_Cycle')
                ->get(['Id_PA_Cycle', 'Kode_Cycle', 'Nama_Cycle', 'Tahun', 'Status_Cycle'])
                ->map(fn ($b) => (array) $b)->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $k
     * @param  array<string, mixed>  $saringan
     */
    private function kueri(array $k, array $saringan)
    {
        $q = DB::table($k['tabel']);

        // Lingkup siklus dan paket WAJIB disaring. Tanpa itu, layar paket soal akan
        // menampilkan paket milik seluruh siklus bercampur jadi satu.
        if ($k['lingkup'] === 'siklus') {
            $cycleId = $saringan['cycle_id'] ?? null;

            if ($cycleId === null) {
                throw new RuntimeException(
                    "{$k['nama']} menggantung pada siklus; sebutkan cycle_id. "
                    .'Tanpa itu paket milik semua siklus akan bercampur.'
                );
            }

            $q->where('PA_Cycle_Id', (int) $cycleId);
        }

        if ($k['lingkup'] === 'paket') {
            $packageId = $saringan['package_id'] ?? null;

            if ($packageId === null) {
                throw new RuntimeException(
                    "{$k['nama']} menggantung pada paket soal; sebutkan package_id."
                );
            }

            $q->where('PA_Cycle_Question_Package_Id', (int) $packageId);
        }

        if (! empty($saringan['flag_aktif'])) {
            $q->where('Flag_Aktif', $saringan['flag_aktif']);
        }

        if (! empty($saringan['id_level']) && in_array('ID_Level', $k['kolom'], true)) {
            $q->where('ID_Level', (int) $saringan['id_level']);
        }

        if (! empty($saringan['sumbu']) && in_array('Score_Type', $k['kolom'], true)) {
            $q->where('Score_Type', $saringan['sumbu']);
        }

        if (! empty($saringan['kegunaan']) && in_array('Score_Usage', $k['kolom'], true)) {
            $q->where('Score_Usage', $saringan['kegunaan']);
        }

        $cari = trim((string) ($saringan['q'] ?? ''));

        if ($cari !== '' && $k['cari'] !== []) {
            $q->where(function ($w) use ($k, $cari) {
                foreach ($k['cari'] as $kolom) {
                    $w->orWhere($kolom, 'LIKE', '%'.$cari.'%');
                }
            });
        }

        return $q;
    }
}
