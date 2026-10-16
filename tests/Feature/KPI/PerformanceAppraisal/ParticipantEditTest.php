<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaParticipantEditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;

/**
 * Penyuntingan keikutsertaan peserta — jalur manual di samping penyiapan otomatis.
 *
 * Yang paling penting di berkas ini bukan penolakannya, melainkan EFEK SAMPINGnya:
 * mengecualikan orang yang sudah punya penugasan harus ikut membatalkan penugasannya.
 * Kalau tidak, penilainya tetap melihat tugas untuk orang yang sudah tidak ada di siklus,
 * mengerjakannya dengan sungguh-sungguh, dan hasilnya tidak dipakai siapa pun. Itu
 * kegagalan yang tidak pernah muncul sebagai galat, hanya sebagai waktu orang yang
 * terbuang — jadi ia harus diuji dengan menghitung barisnya, bukan dipercaya.
 */
class ParticipantEditTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleId;

    private int $participantId;

    private int $snapshotId;

    private int $kategoriId;

    private object $karyawan;

    private int $levelId;

    /**
     * Karyawan NYATA yang dipakai bergantian, satu per peserta.
     *
     * FK ke `Karyawan` melarang kode karangan; unique (siklus, perusahaan, karyawan)
     * melarang orang yang sama dua kali. Maka: beberapa orang berbeda yang sungguh ada.
     */
    private array $stokKaryawan = [];

    /** Karyawan nyata yang dipakai sebagai PENILAI, terpisah dari stok peserta. */
    private array $stokPenilai = [];

    protected function setUp(): void
    {
        parent::setUp();

        $stok = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->limit(10)
            ->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan'])
            ->all();

        $this->assertGreaterThanOrEqual(6, count($stok),
            'Uji ini butuh beberapa karyawan aktif yang berbeda.');

        /* Dibelah dua supaya peserta dan penilai tidak pernah memperebutkan orang yang
           sama. `N_PA_Assignment` punya FK komposit (Kode_Perusahaan_Penilai,
           Kode_Karyawan_Penilai) ke `Karyawan`, jadi penilai karangan ditolak sama seperti
           peserta karangan - dan unique (peserta, penilai, kategori) melarang penilai yang
           sama dua kali pada peserta yang sama. */
        $this->stokKaryawan = array_slice($stok, 0, 5);
        $this->stokPenilai = array_slice($stok, 5);

        $this->karyawan = $this->stokKaryawan[0];

        $this->kategoriId = (int) DB::table('N_PA_Rater_Category')
            ->where('Kode_Kategori', 'SELF')
            ->value('Id_PA_Rater_Category');

        $this->levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $this->cycleId = $this->buatSiklus('DRAFT');
        $this->snapshotId = $this->buatSnapshotAturan();
        $this->participantId = $this->buatPeserta('DIIKUTKAN', $this->snapshotId);
    }

    /* ------------------------------------------------------------------ *
     * Status yang boleh dan tidak boleh diminta                           *
     * ------------------------------------------------------------------ */

    public function test_tertunda_tidak_bisa_ditetapkan_manual(): void
    {
        try {
            $this->service()->ubahStatus($this->participantId, 'TERTUNDA', 'apa saja', 1);
            $this->fail('TERTUNDA seharusnya ditolak sebagai tujuan.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            /* TERTUNDA adalah KEADAAN, bukan pilihan: artinya level orang itu belum punya
               aturan penilai. Menetapkannya dengan tangan mengarang penghalang, dan
               gerbang penguncian siklus kemudian menolak karena penghalang karangan itu. */
            $this->assertStringContainsString('bukan pilihan melainkan keadaan', $e->getMessage());
        }

        $this->assertSame('DIIKUTKAN', $this->statusPeserta());
    }

    public function test_mengecualikan_wajib_beralasan(): void
    {
        try {
            $this->service()->ubahStatus($this->participantId, 'DIKECUALIKAN', '   ', 1);
            $this->fail('Pengecualian tanpa alasan seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('wajib beralasan', $e->getMessage());
        }

        // Statusnya tidak boleh ikut berubah saat penolakan.
        $this->assertSame('DIIKUTKAN', $this->statusPeserta());

        // Dengan alasan, diterima.
        $hasil = $this->service()->ubahStatus($this->participantId, 'DIKECUALIKAN', 'cuti panjang', 1);

        $this->assertTrue($hasil['berubah']);
        $this->assertSame('DIKECUALIKAN', $this->statusPeserta());
    }

    public function test_diikutkan_ditolak_bila_levelnya_belum_punya_aturan(): void
    {
        $tanpaAturan = $this->buatPeserta('DIKECUALIKAN', null);

        try {
            $this->service()->ubahStatus($tanpaAturan, 'DIIKUTKAN', '', 1);
            $this->fail('Peserta tanpa salinan aturan seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('belum punya salinan aturan penilai', $e->getMessage());
        }

        /* Arah sebaliknya: yang PUNYA salinan aturan diterima, sehingga penolakan di atas
           terbukti bergantung pada salinannya. */
        $punyaAturan = $this->buatPeserta('DIKECUALIKAN', $this->snapshotId);
        $hasil = $this->service()->ubahStatus($punyaAturan, 'DIIKUTKAN', '', 1);

        $this->assertTrue($hasil['berubah']);
    }

    /* ------------------------------------------------------------------ *
     * Efek samping yang wajib ikut terjadi                                *
     * ------------------------------------------------------------------ */

    public function test_mengecualikan_ikut_membatalkan_penugasannya(): void
    {
        $this->buatPenugasan();
        $this->buatPenugasan();

        $this->assertSame(2, $this->penugasanAktif(), 'Prasyarat: dua penugasan aktif.');

        $hasil = $this->service()->ubahStatus($this->participantId, 'DIKECUALIKAN', 'pindah anak perusahaan', 1);

        $this->assertSame(2, $hasil['penugasan_dibatalkan']);
        $this->assertSame(
            0,
            $this->penugasanAktif(),
            'Penugasan yang ditinggalkan hidup akan menagih pekerjaan yang tidak dipakai siapa pun.'
        );
    }

    public function test_alasan_dibersihkan_saat_diikutkan_kembali(): void
    {
        $this->service()->ubahStatus($this->participantId, 'DIKECUALIKAN', 'cuti panjang', 1);

        $this->assertSame('cuti panjang', DB::table('N_PA_Participant')
            ->where('Id_PA_Participant', $this->participantId)
            ->value('Alasan_Dikecualikan'));

        $this->service()->ubahStatus($this->participantId, 'DIIKUTKAN', '', 1);

        /* Alasan lama yang ditinggalkan membuat baris yang berbunyi "diikutkan" sekaligus
           "dikecualikan karena cuti", dan pembacanya tidak punya cara tahu mana yang
           berlaku. */
        $this->assertNull(DB::table('N_PA_Participant')
            ->where('Id_PA_Participant', $this->participantId)
            ->value('Alasan_Dikecualikan'));
    }

    public function test_status_yang_sama_bukan_galat(): void
    {
        $hasil = $this->service()->ubahStatus($this->participantId, 'DIIKUTKAN', '', 1);

        // Dua orang menekan tombol yang sama, atau satu orang menekannya dua kali.
        // Keduanya bukan kesalahan yang perlu diteriakkan.
        $this->assertFalse($hasil['berubah']);
        $this->assertSame(0, $hasil['penugasan_dibatalkan']);
    }

    /* ------------------------------------------------------------------ *
     * Gerbang                                                             *
     * ------------------------------------------------------------------ */

    public function test_mengikutkan_kembali_tertutup_begitu_siklus_dikunci(): void
    {
        /* Bukan kerapian: pembentukan penugasan berhenti di PREPARED, jadi orang yang
           diikutkan sesudah kunci lahir tanpa satu pun penilai dan tidak punya cara
           mendapatkannya. Ia menggantung menunggu angka yang tak pernah bisa dihitung. */
        $this->service()->ubahStatus($this->participantId, 'DIKECUALIKAN', 'cuti panjang', 1);

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
            ->update(['Status_Cycle' => 'OPEN']);

        try {
            $this->service()->ubahStatus($this->participantId, 'DIIKUTKAN', '', 1);
            $this->fail('Siklus OPEN seharusnya menolak pengikutan kembali.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tidak diizinkan', $e->getMessage());
        }

        $this->assertSame('DIKECUALIKAN', $this->statusPeserta());
    }

    public function test_mengeluarkan_peserta_masih_boleh_setelah_pengisian_dibuka(): void
    {
        /* Justru DI SINI kebutuhannya muncul: orangnya resign atau dipindah sesudah
           penilaian berjalan. Sebelum 6 September 2026 ini mustahil — dan penugasannya
           tetap hidup, menagih pekerjaan untuk orang yang tidak akan dinilai. */
        $this->buatPenugasan();
        $this->buatPenugasan();
        $this->assertSame(2, $this->penugasanAktif());

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
            ->update(['Status_Cycle' => 'OPEN']);

        $hasil = $this->service()->ubahStatus($this->participantId, 'DIKECUALIKAN', 'resign', 1);

        $this->assertTrue($hasil['berubah']);
        $this->assertSame('DIKECUALIKAN', $this->statusPeserta());

        // Yang penting bukan statusnya, melainkan efek sampingnya: penugasan yang
        // ditinggalkan hidup akan tetap muncul di "Penilaian Saya" para penilainya.
        $this->assertSame(2, $hasil['penugasan_dibatalkan']);
        $this->assertSame(0, $this->penugasanAktif());
    }

    public function test_hasil_yang_sudah_terbit_tidak_bisa_dikecualikan(): void
    {
        DB::table('N_PA_Result')->insert([
            'PA_Participant_Id' => $this->participantId,
            'Final_Score' => 82.5,
            'Kode_Grade_Final' => 'A',
            'Kode_Grade_Sistem' => 'A',
            'Status_Hasil' => 'TERBIT',
        ]);

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
            ->update(['Status_Cycle' => 'OPEN']);

        try {
            $this->service()->ubahStatus($this->participantId, 'DIKECUALIKAN', 'resign', 1);
            $this->fail('Hasil yang sudah TERBIT seharusnya menolak pengecualian.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            // Orangnya sudah melihat nilainya. Mengeluarkannya menghapus angka itu dari
            // riwayatnya tanpa ada yang pernah mengatakannya.
            $this->assertStringContainsString('sudah TERBIT', $e->getMessage());
        }

        $this->assertSame('DIIKUTKAN', $this->statusPeserta());
    }

    /* ------------------------------------------------------------------ *
     * Massal                                                              *
     * ------------------------------------------------------------------ */

    public function test_yang_gagal_tidak_menghentikan_sisanya(): void
    {
        $kedua = $this->buatPeserta('DIIKUTKAN', $this->snapshotId);
        $hantu = (int) DB::table('N_PA_Participant')->max('Id_PA_Participant') + 99999;

        $hasil = $this->service()->ubahStatusBanyak(
            [$this->participantId, $hantu, $kedua],
            'DIKECUALIKAN',
            'perampingan siklus',
            1,
        );

        $this->assertSame(3, $hasil['diminta']);
        $this->assertSame(2, $hasil['berhasil'], 'Dua peserta nyata harus tetap terproses.');
        $this->assertSame(1, $hasil['gagal']);
        $this->assertSame($hantu, $hasil['rincian_gagal'][0]['participant_id']);

        // Dan keduanya benar-benar berubah, bukan sekadar dilaporkan berhasil.
        $this->assertSame('DIKECUALIKAN', $this->statusPeserta());
        $this->assertSame('DIKECUALIKAN', DB::table('N_PA_Participant')
            ->where('Id_PA_Participant', $kedua)->value('Status_Peserta'));
    }

    /* ------------------------------------------------------------------ *
     * Bangunan uji                                                        *
     * ------------------------------------------------------------------ */

    private function service(): PaParticipantEditService
    {
        return $this->app->make(PaParticipantEditService::class);
    }

    private function statusPeserta(): string
    {
        return (string) DB::table('N_PA_Participant')
            ->where('Id_PA_Participant', $this->participantId)
            ->value('Status_Peserta');
    }

    private function penugasanAktif(): int
    {
        return (int) DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $this->participantId)
            ->where('Flag_Aktif', 'Y')
            ->where('Status_Penugasan', '<>', 'DIBATALKAN')
            ->count();
    }

    private function buatSiklus(string $status): int
    {
        $nama = 'Siklus Uji Sunting Peserta '.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => 'UJI-SUNTING-'.substr(bin2hex(random_bytes(4)), 0, 8),
            'Nama_Cycle' => $nama,
            'Tahun' => (int) date('Y'),
            'Status_Cycle' => $status,
        ]);

        return (int) DB::table('N_PA_Cycle')->where('Nama_Cycle', $nama)->value('Id_PA_Cycle');
    }

    private function buatSnapshotAturan(): int
    {
        $levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $kode = 'UJI-SUNTING-'.substr(bin2hex(random_bytes(3)), 0, 6);

        // NOT NULL, dan mudah terlewat karena salinan ini "seharusnya berdiri sendiri".
        // Ia tetap menunjuk policy asalnya.
        $policyId = DB::table('N_PA_Rater_Policy')
            ->orderBy('Id_PA_Rater_Policy')
            ->value('Id_PA_Rater_Policy');

        $this->assertNotNull($policyId, 'Master aturan penilai kosong.');

        DB::table('N_PA_Cycle_Rater_Policy_Snapshot')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'ID_Level' => $levelId,
            'PA_Rater_Policy_Id' => $policyId,
            'Kode_Policy_Salinan' => $kode,
            'Nama_Policy_Salinan' => 'Policy Uji Sunting Peserta',
            'Versi_Policy_Salinan' => 1,
        ]);

        return (int) DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Kode_Policy_Salinan', $kode)
            ->value('Id_PA_Cycle_Rater_Policy_Snapshot');
    }

    private function buatPeserta(string $status, ?int $snapshotId): int
    {
        $karyawan = array_shift($this->stokKaryawan);

        $this->assertNotNull($karyawan, 'Stok karyawan uji habis; tambah limit di setUp().');

        // Kode ASLI. Ada FK ke Karyawan, jadi kode karangan pasti ditolak.
        $kode = $karyawan->Kode_Karyawan;

        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'Kode_Perusahaan' => $karyawan->Kode_Perusahaan,
            'Kode_Karyawan' => $kode,
            'Nama_Karyawan_Saat_Cycle' => $karyawan->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $karyawan->ID_Level_Jabatan,
            // NOT NULL, dan mudah terlewat karena namanya mirip baris di atasnya. Yang
            // ini LEVEL (band jabatan), yang di atas JABATAN.
            'ID_Level_Saat_Cycle' => $this->levelId,
            'Status_Peserta' => $status,
            'PA_Cycle_Rater_Policy_Snapshot_Id' => $snapshotId,
        ]);

        return (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Kode_Karyawan', $kode)
            ->value('Id_PA_Participant');
    }

    private function buatPenugasan(): int
    {
        $penilai = array_shift($this->stokPenilai);

        $this->assertNotNull($penilai, 'Stok penilai uji habis; tambah limit di setUp().');

        // Kode ASLI. FK komposit ke Karyawan menolak kode karangan.
        $kode = $penilai->Kode_Karyawan;

        DB::table('N_PA_Assignment')->insert([
            'PA_Participant_Id' => $this->participantId,
            'Kode_Perusahaan_Penilai' => $penilai->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $kode,
            'Nama_Penilai_Saat_Cycle' => $penilai->Nama,
            'PA_Rater_Category_Id' => $this->kategoriId,
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => 'BELUM_MULAI',
        ]);

        return (int) DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $this->participantId)
            ->where('Kode_Karyawan_Penilai', $kode)
            ->value('Id_PA_Assignment');
    }
}
