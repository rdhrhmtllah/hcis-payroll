<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaAuditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;

/**
 * Lini masa audit (A12).
 *
 * DUA JANJI YANG PALING MUDAH DILANGGAR TANPA KETAHUAN
 *
 * 1. **`cacah` memuat kedua angka meski disaring.** Layar yang menampilkan keputusan
 *    manusia saja tetap harus bisa berkata "dan sekian lainnya lahir dari aturan". Kalau
 *    cacah sistemnya ikut tersaring menjadi nol, orang menyimpulkan tidak ada yang
 *    terjadi — persis pada angka yang paling dibutuhkan.
 * 2. **Nama pelaku yang DIBEKUKAN menang atas hasil join.** Kalibrasi menyimpan namanya
 *    sendiri justru karena pelaku yang sudah keluar membuat join ke daftar user kosong,
 *    pada baris yang paling perlu dijelaskan.
 */
class AuditTimelineTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleId;

    private int $participantId;

    private int $participantLain;

    private int $userId;

    private array $stok = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->stok = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')->limit(6)
            ->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan'])->all();

        $this->assertGreaterThanOrEqual(3, count($this->stok), 'Uji ini butuh beberapa karyawan.');

        $this->userId = (int) DB::table('KPI_Users')->orderBy('Id_Users')->value('Id_Users');
        $this->cycleId = $this->buatSiklus();
        $this->participantId = $this->buatPeserta();
        $this->participantLain = $this->buatPeserta();
    }

    /* ------------------------------------------------------------------ *
     * Memisahkan aturan dari keputusan                                    *
     * ------------------------------------------------------------------ */

    public function test_kejadian_tanpa_pelaku_ditandai_sebagai_oleh_sistem(): void
    {
        // Pengecualian TANPA pelaku — inilah bentuk "aturan yang menyala".
        $this->kecualikan($this->participantId, null);
        // Dan satu DENGAN pelaku — keputusan seseorang.
        $this->kecualikan($this->participantLain, $this->userId);

        $lini = $this->service()->lini($this->cycleId);

        $sistem = collect($lini['kejadian'])->where('oleh_sistem', true);
        $manusia = collect($lini['kejadian'])->where('oleh_sistem', false);

        $this->assertCount(1, $sistem);
        $this->assertCount(1, $manusia);
        $this->assertTrue($sistem->first()['aktor_tidak_diketahui']);
    }

    public function test_cacah_tetap_memuat_kedua_angka_meski_disaring(): void
    {
        $this->kecualikan($this->participantId, null);
        $this->kecualikan($this->participantLain, $this->userId);

        $disaring = $this->service()->lini($this->cycleId, ['hanya_keputusan_manusia' => true]);

        // Barisnya tersaring...
        $this->assertCount(1, $disaring['kejadian']);
        $this->assertSame(1, $disaring['total']);

        /* ...tetapi cacahnya TIDAK. Menghitungnya dari `total` yang sudah tersaring akan
           membuat cacah sistem menjadi nol persis ketika penyaringnya menyala — yaitu
           ketika angka itu paling dibutuhkan. */
        $this->assertSame(1, $disaring['cacah']['keputusan_manusia']);
        $this->assertSame(1, $disaring['cacah']['oleh_sistem']);
    }

    public function test_saringan_keputusan_manusia_baku_mati(): void
    {
        $this->kecualikan($this->participantId, null);

        // Menyaring diam-diam membuat orang menyimpulkan tidak ada kejadian pada hal yang
        // justru disembunyikan.
        $this->assertCount(1, $this->service()->lini($this->cycleId)['kejadian']);
    }

    /* ------------------------------------------------------------------ *
     * Nama pelaku                                                         *
     * ------------------------------------------------------------------ */

    public function test_nama_pelaku_yang_dibekukan_menang_atas_hasil_join(): void
    {
        $resultId = $this->buatHasil($this->participantId);

        // `Performed_By` menunjuk user yang ADA, tetapi namanya dibekukan berbeda.
        DB::table('N_PA_Calibration_History')->insert([
            'PA_Result_Id' => $resultId,
            'Kode_Grade_Sebelum' => 'B',
            'Kode_Grade_Sesudah' => 'A',
            'Alasan' => 'penyesuaian hasil rapat kalibrasi',
            'Performed_By' => $this->userId,
            'Performed_By_Nama' => 'Nama Beku Saat Itu',
        ]);

        $kejadian = collect($this->service()->lini($this->cycleId)['kejadian'])
            ->firstWhere('jenis', 'KALIBRASI');

        $this->assertNotNull($kejadian, 'Kalibrasi harus muncul di lini masa.');

        /* Yang dibekukan menang. Kalau join yang menang, baris milik orang yang sudah
           keluar dari perusahaan akan kehilangan namanya — persis baris yang paling perlu
           dijelaskan setahun kemudian. */
        $this->assertSame('Nama Beku Saat Itu', $kejadian['aktor_nama']);
        $this->assertSame($this->userId, $kejadian['aktor_id']);
        $this->assertFalse($kejadian['oleh_sistem']);
        $this->assertStringContainsString('B menjadi A', $kejadian['rincian']);
    }

    /* ------------------------------------------------------------------ *
     * Sumber kejadian                                                     *
     * ------------------------------------------------------------------ */

    public function test_pembatalan_penugasan_muncul_dengan_sebabnya(): void
    {
        $this->buatPenugasanDibatalkan($this->participantId, 'penilai pindah unit');

        $kejadian = collect($this->service()->lini($this->cycleId)['kejadian'])
            ->firstWhere('jenis', 'PENUGASAN_DIBATALKAN');

        $this->assertNotNull($kejadian);
        $this->assertSame('penilai pindah unit', $kejadian['sebab']);
        $this->assertSame($this->participantId, $kejadian['participant_id']);
    }

    public function test_penguncian_dan_penerbitan_siklus_tercatat(): void
    {
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)->update([
            'Locked_At' => now(), 'Locked_By' => $this->userId,
            'Published_At' => now(), 'Published_By' => $this->userId,
        ]);

        $jenis = collect($this->service()->lini($this->cycleId)['kejadian'])->pluck('jenis');

        $this->assertContains('SIKLUS_DIKUNCI', $jenis);
        $this->assertContains('SIKLUS_DITERBITKAN', $jenis);
    }

    /* ------------------------------------------------------------------ *
     * Saringan dan batas                                                  *
     * ------------------------------------------------------------------ */

    public function test_saringan_jenis_dan_peserta_mempersempit(): void
    {
        $this->kecualikan($this->participantId, $this->userId);
        $this->buatPenugasanDibatalkan($this->participantLain, 'coba');

        $semua = $this->service()->lini($this->cycleId);
        $satuJenis = $this->service()->lini($this->cycleId, ['jenis' => 'PENUGASAN_DIBATALKAN']);
        $satuOrang = $this->service()->lini($this->cycleId, ['participant_id' => $this->participantId]);

        $this->assertSame(2, $semua['total']);
        $this->assertSame(1, $satuJenis['total'], 'Saringan jenis harus mempersempit.');
        $this->assertSame(1, $satuOrang['total'], 'Saringan peserta harus mempersempit.');
    }

    public function test_batas_nilai_lama_dinyatakan_di_dalam_balasannya(): void
    {
        $lini = $this->service()->lini($this->cycleId);

        /* Dinyatakan di balasannya, bukan hanya di dokumen. Pembaca yang mengira ini
           riwayat lengkap akan menyimpulkan tidak ada perubahan pada hal-hal yang justru
           tidak terekam. */
        $this->assertTrue($lini['batas']['konfigurasi_tanpa_nilai_lama']);
        $this->assertStringContainsString('nilai sebelum', $lini['batas']['catatan']);
        $this->assertNotEmpty($lini['jenis_tersedia']);
    }

    public function test_permintaan_di_atas_plafon_dilaporkan_dipangkas(): void
    {
        $lini = $this->service()->lini($this->cycleId, ['per_halaman' => 5000]);

        $this->assertTrue($lini['dipangkas']);
        $this->assertSame(PaAuditService::MAKS_PER_HALAMAN, $lini['per_halaman']);
    }

    public function test_siklus_yang_tidak_ada_ditolak(): void
    {
        $hantu = (int) DB::table('N_PA_Cycle')->max('Id_PA_Cycle') + 99999;

        try {
            $this->service()->lini($hantu);
            $this->fail('Siklus yang tidak ada seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tidak ditemukan', $e->getMessage());
        }
    }

    /* ------------------------------------------------------------------ *
     * Bangunan uji                                                        *
     * ------------------------------------------------------------------ */

    private function service(): PaAuditService
    {
        return $this->app->make(PaAuditService::class);
    }

    private function buatSiklus(): int
    {
        $nama = 'Siklus Uji Audit '.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => 'UJI-AUDIT-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
            'Nama_Cycle' => $nama,
            'Tahun' => (int) date('Y'),
            'Status_Cycle' => 'CLOSED',
        ]);

        return (int) DB::table('N_PA_Cycle')->where('Nama_Cycle', $nama)->value('Id_PA_Cycle');
    }

    private function buatPeserta(): int
    {
        $k = array_shift($this->stok);
        $this->assertNotNull($k, 'Stok karyawan uji habis.');

        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'Kode_Perusahaan' => $k->Kode_Perusahaan,
            'Kode_Karyawan' => $k->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $k->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $k->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level'),
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        return (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Kode_Karyawan', $k->Kode_Karyawan)
            ->value('Id_PA_Participant');
    }

    private function kecualikan(int $participantId, ?int $olehUserId): void
    {
        DB::table('N_PA_Participant')->where('Id_PA_Participant', $participantId)->update([
            'Status_Peserta' => 'DIKECUALIKAN',
            'Alasan_Dikecualikan' => $olehUserId === null ? 'Level FREELANCE' : 'cuti panjang',
            'Updated_At' => now()->format('Y-m-d H:i:s'),
            'Updated_By' => $olehUserId,
            // Snapshot_By ikut dikosongkan; kalau tidak, COALESCE di service akan
            // memungutnya dan kejadian "tanpa pelaku" jadi punya pelaku.
            'Snapshot_By' => $olehUserId,
        ]);
    }

    private function buatPenugasanDibatalkan(int $participantId, string $sebab): void
    {
        $penilai = end($this->stok) ?: $this->stok[0];

        DB::table('N_PA_Assignment')->insert([
            'PA_Participant_Id' => $participantId,
            'Kode_Perusahaan_Penilai' => $penilai->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $penilai->Kode_Karyawan,
            'Nama_Penilai_Saat_Cycle' => $penilai->Nama,
            'PA_Rater_Category_Id' => (int) DB::table('N_PA_Rater_Category')
                ->where('Kode_Kategori', 'SELF')->value('Id_PA_Rater_Category'),
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => 'DIBATALKAN',
            'Cancelled_At' => now()->format('Y-m-d H:i:s'),
            'Cancelled_By' => $this->userId,
            'Cancel_Reason' => $sebab,
        ]);
    }

    private function buatHasil(int $participantId): int
    {
        DB::table('N_PA_Result')->insert([
            'PA_Participant_Id' => $participantId,
            'Final_Score' => 82.5,
            'Kode_Grade_Final' => 'A',
            'Kode_Grade_Sistem' => 'B',
            'Status_Hasil' => 'DIHITUNG',
        ]);

        return (int) DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $participantId)->value('Id_PA_Result');
    }
}
