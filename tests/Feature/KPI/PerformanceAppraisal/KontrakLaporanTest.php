<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\PerformanceAppraisal\Kurva\PaGrupKurvaMasterService;
use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MembandingkanBentukRespons;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanSiklusKurva;
use Tests\TestCase;

/**
 * Penjaga batas frontend untuk enam alamat yang lahir belakangan: bobot pilar,
 * penyuntingan peserta, dan empat layar laporan admin.
 *
 * Terpisah dari `KontrakResponsTest` karena bahan ujinya berbeda sifat. Di sana siklus
 * uji disiapkan lewat `siapkan` yang menyentuh seluruh karyawan aktif dan memakan
 * menit-menit; di sini yang dibutuhkan hanya segelintir baris yang bentuknya lengkap,
 * dan membangunnya sendiri jauh lebih cepat sekaligus lebih bisa dipastikan isinya.
 *
 * TIAP FIXTURE HARUS DIBANDINGKAN DALAM KEADAAN TERISI
 *
 * Daftar kosong tidak mengatakan apa-apa tentang bentuk isinya. Uji yang membandingkan
 * peta panas kosong dengan fixture peta panas berisi akan hijau tanpa menguji apa pun —
 * karena itu tiap uji di bawah menegaskan isinya lebih dulu, sebelum membandingkan.
 */
class KontrakLaporanTest extends TestCase
{
    use DatabaseTransactions;
    use MembandingkanBentukRespons;
    use MenyiapkanLembarPenilaian;
    use MenyiapkanSiklusKurva;

    private const PREFIKS = '/api/v1/performance-appraisal';

    private int $cycleId;

    private int $levelId;

    private int $levelLain;

    private int $kategoriId;

    private array $stok = [];

    private array $stokPenilai = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->penggunaBerakses());

        $semua = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->limit(14)
            ->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan'])
            ->all();

        $this->assertGreaterThanOrEqual(10, count($semua), 'Uji ini butuh beberapa karyawan.');

        $this->stok = array_slice($semua, 0, 7);
        $this->stokPenilai = array_slice($semua, 7);

        $level = DB::table('HRIS_Level')->orderBy('ID_Level')->limit(2)->pluck('ID_Level');
        $this->levelId = (int) $level[0];
        $this->levelLain = (int) $level[1];

        $this->kategoriId = (int) DB::table('N_PA_Rater_Category')
            ->where('Kode_Kategori', 'SELF')->value('Id_PA_Rater_Category');

        $this->cycleId = $this->buatSiklus();
    }

    /**
     * Peta berkunci nilai bisnis. Kuncinya berbeda antar siklus; isinya yang harus sama.
     *
     * @return list<string>
     */
    protected function petaDinamis(): array
    {
        return [
            'result.peserta.per_status',
            'result.penugasan.per_status',
            'result.hasil.per_status',
            'result.asal_angka.per_status',
        ];
    }

    /* ------------------------------------------------------------------ *
     * Fitur 9 dan 10                                                      *
     * ------------------------------------------------------------------ */

    public function test_bobot_pilar_sebentuk_dengan_fixture(): void
    {
        $this->buatSalinanBobot($this->levelId, 80, 20);

        $r = $this->getJson(self::PREFIKS."/cycles/{$this->cycleId}/bobot-pilar");

        $r->assertOk();
        $this->assertNotEmpty($r->json('result.bobot'), 'Daftar bobot harus terisi.');
        $this->samakanBentuk('bobot-pilar-siklus.json', $r->json());
    }

    public function test_ubah_status_peserta_sebentuk_dengan_fixture(): void
    {
        $participantId = $this->buatPeserta();

        $r = $this->putJson(self::PREFIKS."/participants/{$participantId}/status", [
            'status' => 'DIKECUALIKAN',
            'alasan' => 'cuti panjang sampai Desember',
        ]);

        $r->assertOk();
        $this->assertTrue($r->json('result.berubah'), 'Statusnya harus benar-benar berubah.');
        $this->samakanBentuk('ubah-status-peserta.json', $r->json());
    }

    /* ------------------------------------------------------------------ *
     * A09 — penyiapan siklus                                              *
     * ------------------------------------------------------------------ */

    public function test_detail_siklus_sebentuk_dengan_fixture(): void
    {
        $this->buatSalinanBobot($this->levelId, 80, 20);
        $this->buatPeserta();

        $r = $this->getJson(self::PREFIKS."/cycles/{$this->cycleId}");

        $r->assertOk();
        // Terisi lebih dulu; peta kosong tidak mengatakan apa-apa tentang bentuk isinya.
        $this->assertNotEmpty($r->json('result.peserta.per_status'));
        $this->assertNotEmpty($r->json('result.kelengkapan'));

        $this->samakanBentuk('siklus-detail.json', $r->json());
    }

    public function test_buat_siklus_sebentuk_dengan_fixture(): void
    {
        $r = $this->postJson(self::PREFIKS.'/cycles', [
            'kode_cycle' => 'UJI-KTR-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
            'nama_cycle' => 'Siklus Uji Kontrak Setup',
            'tahun' => 2026,
            'periode_dinilai_dari' => '2026-01-01',
            'periode_dinilai_sampai' => '2026-06-30',
        ]);

        $r->assertOk();
        $this->assertSame('DRAFT', $r->json('result.status'));
        $this->samakanBentuk('siklus-buat.json', $r->json());
    }

    public function test_perbarui_siklus_sebentuk_dengan_fixture(): void
    {
        $r = $this->putJson(self::PREFIKS."/cycles/{$this->cycleId}", [
            'nama_cycle' => 'Nama Sesudah Disunting',
        ]);

        $r->assertOk();

        /* Menegaskan EFEKNYA, bukan hanya amplopnya. `periode_berubah` adalah daftar
           skalar, dan pembanding bentuk meluruhkan daftar kosong maupun berisi ke alamat
           yang sama — sehingga uji ini akan hijau seandainya `perbarui()` tidak menulis
           apa pun, asal amplopnya kembali. */
        $this->assertSame('Nama Sesudah Disunting', DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $this->cycleId)->value('Nama_Cycle'));

        $this->samakanBentuk('siklus-perbarui.json', $r->json());
    }

    /* ------------------------------------------------------------------ *
     * Fitur 16 sampai 19                                                  *
     * ------------------------------------------------------------------ */

    public function test_ringkasan_siklus_sebentuk_dengan_fixture(): void
    {
        $p = $this->buatPeserta(divisi: 'PRODUCTION');
        $this->buatPenugasan($p, null, 'SELESAI');
        $this->buatHasil($p, 82.5, 'B', 'TERBIT');

        $r = $this->getJson(self::PREFIKS."/cycles/{$this->cycleId}/ringkasan");

        $r->assertOk();
        // Ketiganya harus terisi, kalau tidak cabangnya tak pernah terbandingkan.
        $this->assertNotEmpty($r->json('result.per_divisi'), 'Progres per divisi harus terisi.');
        $this->assertNotEmpty($r->json('result.penugasan.per_status'));
        $this->assertNotEmpty($r->json('result.hasil.per_status'));

        $this->samakanBentuk('siklus-ringkasan.json', $r->json());
    }

    public function test_monitoring_sebentuk_dengan_fixture(): void
    {
        $p = $this->buatPeserta(divisi: 'PRODUCTION');

        // Tenggat LAMPAU, kalau tidak `terlambat.baris` kosong dan bentuk barisnya —
        // bagian yang paling dipakai layar — tidak pernah terbandingkan.
        $this->buatPenugasan($p, now()->subDays(4)->format('Y-m-d'), 'BELUM_MULAI');

        $r = $this->getJson(self::PREFIKS."/cycles/{$this->cycleId}/monitoring");

        $r->assertOk();
        $this->assertNotEmpty($r->json('result.terlambat.baris'), 'Daftar terlambat harus terisi.');
        $this->assertNotEmpty($r->json('result.peta_panas.baris'), 'Peta panas harus terisi.');
        $this->assertNotEmpty($r->json('result.pilihan_saringan.divisi'));

        $this->samakanBentuk('siklus-monitoring.json', $r->json());
    }

    public function test_distribusi_sebentuk_dengan_fixture(): void
    {
        $kuadranId = DB::table('N_PA_Hav_Quadrant')->value('Id_PA_Hav_Quadrant');

        $a = $this->buatPeserta();
        $b = $this->buatPeserta();

        // Dua nilai di kelompok histogram BERBEDA supaya cabang histogram tidak kosong,
        // dan satu berkuadran supaya cabang kuadran memuat bentuk yang terisi.
        $this->buatHasil($a, 72.5, 'B', 'DIHITUNG');
        $this->buatHasil($b, 88.0, 'A', 'TERBIT', $kuadranId);

        /* Sorotan talenta mengikuti GRADE sejak 8 Oktober 2026: daftar "grade terendah" hanya
           terisi oleh grade di dua pita terbawah, jadi satu hasil bergrade D dibutuhkan supaya
           cabang itu ikut terbandingkan dengan fixture. */
        $this->buatHasil($this->buatPeserta(), 45.0, 'D', 'DIHITUNG');

        $r = $this->getJson(self::PREFIKS."/cycles/{$this->cycleId}/distribusi");

        $r->assertOk();
        $this->assertNotEmpty($r->json('result.grade'), 'Sebaran grade harus terisi.');
        $this->assertNotEmpty($r->json('result.histogram'), 'Histogram harus terisi.');
        $this->assertNotEmpty($r->json('result.per_level'), 'Statistik per level harus terisi.');
        $this->assertNotEmpty($r->json('result.kuadran'), 'Sebaran kuadran harus terisi.');

        $this->samakanBentuk('siklus-distribusi.json', $r->json());
    }

    public function test_riwayat_admin_sebentuk_dengan_fixture(): void
    {
        $p = $this->buatPeserta();
        $this->buatHasil($p, 82.5, 'B', 'TERBIT');

        $r = $this->getJson(self::PREFIKS.'/riwayat?cycle_id='.$this->cycleId);

        $r->assertOk();
        $this->assertNotEmpty($r->json('result.baris'), 'Tabel riwayat harus terisi.');
        // Tren hanya terisi bila ada nilai akhir — itulah sebabnya hasilnya diberi skor.
        $this->assertNotEmpty($r->json('result.tren'), 'Tren harus terisi.');

        $this->samakanBentuk('riwayat-admin.json', $r->json());
    }

    public function test_kurva_siklus_sebentuk_dengan_fixture(): void
    {
        $level = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $s = $this->siklusKurva([$level => [50, 60, 70, 80, 90]]);

        // Satu hasil TERTAHAN supaya daftar peringatan global terisi dan bentuknya terbandingkan.
        $pid = $s['peserta'][$level][0];
        DB::table('N_PA_Result')->where('PA_Participant_Id', $pid)->update(['Status_Hasil' => 'TERTAHAN', 'Final_Score' => null]);

        // Hasil tertahan menuntut alasan tertulis saat mengunci (koreksi 8 Okt 2026).
        app(PaKurvaService::class)->kunci($s['cycle_id'], null, 'Uji kontrak: tertahan disengaja');

        $r = $this->getJson(self::PREFIKS.'/cycles/'.$s['cycle_id'].'/kurva');

        $r->assertOk();
        $this->assertNotEmpty($r->json('result.grup.0.pita'));
        $this->assertNotEmpty($r->json('result.peringatan'));
        $this->samakanBentuk('kurva-siklus.json', $r->json());
    }

    public function test_master_grup_kurva_sebentuk_dengan_fixture(): void
    {
        [$a, $b] = DB::table('HRIS_Level')->orderBy('ID_Level')->limit(2)->pluck('ID_Level')->map(fn ($x) => (int) $x)->all();

        /* `cacah_level` dibaca dari peserta siklus terbaru. Uji ini menyiapkan pesertanya sendiri:
           tanpa satu peserta pun daftarnya kosong dan bentuk butirnya tak pernah terbandingkan
           (terjadi sejak data siklus dev dikosongkan untuk UAT, 8 Okt 2026). */
        $siklus = (int) DB::table('N_PA_Cycle')->insertGetId([
            'Kode_Cycle' => 'UJI-GK-'.bin2hex(random_bytes(3)), 'Nama_Cycle' => 'Uji Grup Kurva',
            'Tahun' => 2099, 'Status_Cycle' => 'DRAFT', 'Flag_Aktif' => 'Y',
        ], 'Id_PA_Cycle');
        $k = DB::table('Karyawan')->where('Aktif', 'Y')->whereNotNull('ID_Level_Jabatan')->orderBy('Kode_Karyawan')
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan']);
        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $siklus, 'Kode_Perusahaan' => $k->Kode_Perusahaan, 'Kode_Karyawan' => $k->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $k->Nama, 'ID_Level_Jabatan_Saat_Cycle' => $k->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $a, 'Status_Peserta' => 'DIIKUTKAN',
        ]);

        $daftar = app(PaGrupKurvaMasterService::class)->simpan([['kode' => 'UJI_STAF', 'nama' => 'Staf', 'level_ids' => [$a, $b]]], null);

        $this->assertNotEmpty($daftar['level_tanpa_grup']);
        $this->samakanBentuk('master-grup-kurva.json', ['success' => true, 'status' => 200, 'message' => 'Berhasil', 'result' => $daftar]);
    }

    /* ------------------------------------------------------------------ *
     * Bangunan uji                                                        *
     * ------------------------------------------------------------------ */

    private function penggunaBerakses(): User
    {
        $userId = DB::table('HRIS_Page_Access')
            ->where('Jenis_page', 'PerformanceAppraisalPage')
            ->where('Flag_Aktif', 'Y')
            ->whereNotNull('UserID_Web')
            ->value('UserID_Web');

        $this->assertNotNull(
            $userId,
            'Tidak ada user berakses PerformanceAppraisalPage; DML-2026-084 belum dijalankan.'
        );

        $user = User::find($userId);
        $this->assertNotNull($user, "User {$userId} tidak ditemukan.");

        return $user;
    }

    private function buatSiklus(): int
    {
        $nama = 'Siklus Uji Kontrak Laporan '.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => 'UJI-LAP-'.substr(bin2hex(random_bytes(4)), 0, 8),
            'Nama_Cycle' => $nama,
            'Tahun' => (int) date('Y'),
            // DRAFT supaya SUNTING_PESERTA dan SUNTING_BOBOT_PILAR sama-sama terbuka.
            'Status_Cycle' => 'DRAFT',
        ]);

        return (int) DB::table('N_PA_Cycle')->where('Nama_Cycle', $nama)->value('Id_PA_Cycle');
    }

    private function buatSalinanBobot(int $levelId, float $kpi, float $kompetensi): void
    {
        DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'ID_Level' => $levelId,
            'Bobot_KPI' => $kpi,
            'Bobot_Kompetensi' => $kompetensi,
        ]);
    }

    private function buatPeserta(?string $divisi = 'PRODUCTION'): int
    {
        $k = array_shift($this->stok);
        $this->assertNotNull($k, 'Stok karyawan uji habis.');

        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'Kode_Perusahaan' => $k->Kode_Perusahaan,
            'Kode_Karyawan' => $k->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $k->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $k->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $this->levelId,
            'Nama_Level_Saat_Cycle' => 'STAFF',
            'ID_Divisi_Saat_Cycle' => $divisi === null ? null : 111,
            'Nama_Divisi_Saat_Cycle' => $divisi,
            'Nama_Jabatan_Saat_Cycle' => 'Staff Uji',
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        return (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Kode_Karyawan', $k->Kode_Karyawan)
            ->value('Id_PA_Participant');
    }

    private function buatPenugasan(int $participantId, ?string $dueAt, string $status): int
    {
        // Penilai dari stok TERPISAH: indeks uniknya menyaring Flag_Aktif, bukan status.
        $penilai = array_shift($this->stokPenilai);
        $this->assertNotNull($penilai, 'Stok penilai uji habis.');

        DB::table('N_PA_Assignment')->insert([
            'PA_Participant_Id' => $participantId,
            'Kode_Perusahaan_Penilai' => $penilai->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $penilai->Kode_Karyawan,
            'Nama_Penilai_Saat_Cycle' => $penilai->Nama,
            'PA_Rater_Category_Id' => $this->kategoriId,
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => $status,
            'Due_At' => $dueAt,
        ]);

        return (int) DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->orderByDesc('Id_PA_Assignment')
            ->value('Id_PA_Assignment');
    }

    private function buatHasil(
        int $participantId,
        float $skor,
        string $grade,
        string $status,
        mixed $kuadranId = null,
    ): int {
        DB::table('N_PA_Result')->insert([
            'PA_Participant_Id' => $participantId,
            'Nilai_KPI' => $skor,
            'Nilai_Kompetensi' => $skor,
            'Final_Score' => $skor,
            'Kode_Grade_Final' => $grade,
            'Kode_Grade_Sistem' => $grade,
            'PA_Hav_Quadrant_Id' => $kuadranId,
            'Status_Hasil' => $status,
            'Published_At' => now()->format('Y-m-d H:i:s'),
        ]);

        return (int) DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $participantId)
            ->value('Id_PA_Result');
    }
}
