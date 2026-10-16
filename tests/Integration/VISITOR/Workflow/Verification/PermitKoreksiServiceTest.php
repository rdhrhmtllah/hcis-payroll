<?php

namespace Tests\Integration\VISITOR\Workflow\Verification;

use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitChecklistService;
use App\Services\VISITOR\Workflow\Verification\PermitKoreksiException;
use App\Services\VISITOR\Workflow\Verification\PermitKoreksiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Koreksi data permit karyawan oleh verifikator saat review.
 * Master dev yang dipakai (diverifikasi 2026-09-23): model 3 = Toyota Avanza
 * (tipe 3 Mobil Penumpang), model 1 = tipe 2 Sepeda Motor, warna 1 = Hitam,
 * prefix plat 'BG' aktif, karyawan 'A1' ada.
 */
class PermitKoreksiServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const VERIFIKATOR = 'A1';

    private int $katA;

    private int $katB;

    protected function setUp(): void
    {
        parent::setUp();
        // A1 tercatat resign di dev (3 Sep 2025); permitnya akan terbaca EXPIRED.
        // Di sini ia diperlakukan masih bekerja, dibatalkan bersama transaksi uji.
        DB::table('Karyawan')->where('Kode_Karyawan', 'A1')->update(['Tanggal_Resign' => null]);
        DB::table('N_EMI_VISITOR_Master_Approval_Question')->update(['Flag_Aktif' => 'T']);
        $this->katA = $this->kategori('Y');
        $this->katB = $this->kategori('Y');
    }

    public function test_koreksi_mengubah_detail_dan_mencatat_riwayat_per_field(): void
    {
        $no = $this->permitPending();

        $berubah = $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input([
            'id_kategori_permit' => $this->katB,
            'no_plat' => 'BG 9999 ZZ',
        ]), 'Salah pilih zona');

        $this->assertSame([PermitKoreksiService::FIELD_KATEGORI, PermitKoreksiService::FIELD_PLAT], $berubah);

        $d = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $no)->first();
        $this->assertSame($this->katB, (int) $d->Id_Master_Kategori_Permit);
        $this->assertSame($this->namaKategori($this->katB), $d->Snapshot_Kategori_Permit);
        $this->assertSame(['BG', '9999', 'ZZ'], [$d->Plat_Nomor_Prefix, $d->Plat_Nomor_Angka, $d->Plat_Nomor_Suffix]);

        $riwayat = DB::table(PermitKoreksiService::T_KOREKSI)->where('No_Transaksi', $no)->orderBy('Id_Permit_Koreksi')->get();
        $this->assertCount(2, $riwayat);
        $this->assertSame(1, $riwayat->pluck('Kelompok_Koreksi')->unique()->count());
        $this->assertSame('BG 1234 AA', $riwayat[1]->Nilai_Lama);
        $this->assertSame('BG 9999 ZZ', $riwayat[1]->Nilai_Baru);
        $this->assertSame('Salah pilih zona', $riwayat[0]->Alasan);
        $this->assertSame(self::VERIFIKATOR, $riwayat[0]->Created_By);

        $this->assertSame(self::VERIFIKATOR, DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->value('Updated_By'));
    }

    public function test_tanpa_perubahan_ditolak(): void
    {
        $no = $this->permitPending();
        $this->assertDitolak('VISITOR_PERMIT_KOREKSI_TANPA_PERUBAHAN', 422, fn () => $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(), 'x'));
    }

    public function test_alasan_kosong_ditolak(): void
    {
        $no = $this->permitPending();
        $this->assertDitolak('VISITOR_PERMIT_KOREKSI_VALIDASI', 422, fn () => $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['tahun_kendaraan' => 2019]), '   '));
    }

    public function test_coklat_dan_kategori_nonaktif_ditolak(): void
    {
        $no = $this->permitPending();
        $coklat = (int) DB::table('N_EMI_VISITOR_Master_Kategori_Permit')->where('Nama_Kategori', 'COKLAT')->value('Id_Kategori_Permit');

        foreach ([$coklat, $this->kategori('Y', 'T'), $this->kategori('T')] as $kategori) {
            $this->assertDitolak('VISITOR_PERMIT_KOREKSI_VALIDASI', 422, fn () => $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['id_kategori_permit' => $kategori]), 'x'));
        }
    }

    public function test_prefix_plat_tidak_dikenal_ditolak(): void
    {
        $no = $this->permitPending();
        $this->assertDitolak('VISITOR_PERMIT_KOREKSI_VALIDASI', 422, fn () => $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['no_plat' => 'QQQ 1234 AA']), 'x'));
    }

    public function test_bukan_pemegang_giliran_ditolak(): void
    {
        $no = $this->permitPending(approver: 'ZZ_BUKAN_SIAPA');
        $this->assertDitolak('VISITOR_PERMIT_KOREKSI_AKSES_DITOLAK', 403, fn () => $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['tahun_kendaraan' => 2019]), 'x'));
        $this->assertFalse($this->svc()->bolehKoreksi($no, self::VERIFIKATOR));
    }

    public function test_permit_sudah_diputus_ditolak(): void
    {
        $no = $this->permitPending();
        DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->update(['Flag_Approval' => 'T']);
        $this->assertDitolak('VISITOR_PERMIT_KOREKSI_STATUS_TIDAK_VALID', 409, fn () => $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['tahun_kendaraan' => 2019]), 'x'));
    }

    public function test_permit_tamu_ditolak(): void
    {
        $no = $this->permitPending();
        DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->update(['Sumber_Pengajuan' => 'API']);
        $this->assertDitolak('VISITOR_PERMIT_KOREKSI_STATUS_TIDAK_VALID', 409, fn () => $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['tahun_kendaraan' => 2019]), 'x'));
    }

    public function test_approval_kedaluwarsa_ditolak(): void
    {
        $no = $this->permitPending();
        DB::table('HRIS_Approval_Request')->where('No_Transaksi', $no)->update(['Expired_At' => now()->subDay()]);
        $this->assertDitolak('VISITOR_PERMIT_KOREKSI_STATUS_TIDAK_VALID', 409, fn () => $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['tahun_kendaraan' => 2019]), 'x'));
    }

    public function test_permit_tidak_ada_ditolak_404(): void
    {
        $this->assertDitolak('VISITOR_PERMIT_KOREKSI_TIDAK_DITEMUKAN', 404, fn () => $this->svc()->koreksi('PMT-TIDAK-ADA', self::VERIFIKATOR, $this->input(), 'x'));
    }

    public function test_ganti_tipe_kendaraan_menyelaraskan_checklist_dan_menjaga_jawaban(): void
    {
        $umum = $this->pertanyaan('Umum', null, null);
        $mobil = $this->pertanyaan('Khusus mobil', null, 3);
        $motor = $this->pertanyaan('Khusus motor', null, 2);
        $no = $this->permitPending();

        $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['id_vehicle_model' => 1]), 'Ternyata motor');

        $baris = DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->where('No_Transaksi', $no)->get()->keyBy(fn ($r) => (int) $r->Id_Master_Pertanyaan);
        $this->assertSame([$umum, $motor], $baris->keys()->sort()->values()->all());
        $this->assertSame('YA', $baris[$umum]->Jawaban_Admin);
        $this->assertArrayNotHasKey($mobil, $baris->all());
    }

    public function test_pratinjau_tidak_menulis(): void
    {
        $this->pertanyaan('Khusus mobil', null, 3);
        $no = $this->permitPending();

        $hasil = $this->svc()->pratinjauChecklist($no, self::VERIFIKATOR, $this->katA, 1);

        $this->assertCount(1, $hasil['dibuang']);
        $this->assertSame(1, DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->where('No_Transaksi', $no)->count());
    }

    public function test_akses_inspeksi_aktif_ikut_disinkron(): void
    {
        $no = $this->permitPending();
        $this->pasangAksesInspeksi($no);

        $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['id_kategori_permit' => $this->katB, 'no_plat' => 'BG 9999 ZZ']), 'x');

        $pe = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->first();
        $this->assertSame($this->namaKategori($this->katB), $pe->Nama_Kategori_Permit);
        $this->assertStringContainsString('9999', (string) $pe->No_Plat_Kendaraan);
        $this->assertSame('T', $pe->Flag_Sync);
    }

    public function test_akses_inspeksi_yang_sudah_berakhir_tidak_dihidupkan(): void
    {
        $no = $this->permitPending();
        $this->pasangAksesInspeksi($no);
        DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->update(['Status_Efektif' => 'EXPIRED']);

        $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['id_kategori_permit' => $this->katB]), 'x');

        $this->assertSame('EXPIRED', DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->value('Status_Efektif'));
    }

    public function test_keterangan_spasi_bukan_perubahan_dan_kosong_jadi_null(): void
    {
        $no = $this->permitPending();
        $this->assertDitolak('VISITOR_PERMIT_KOREKSI_TANPA_PERUBAHAN', 422, fn () => $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['keterangan_pengajuan' => '  Parkir harian  ']), 'x'));

        $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['keterangan_pengajuan' => '   ']), 'x');
        $this->assertNull(DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $no)->value('Keterangan_Pengajuan'));
    }

    public function test_plat_dinormalkan_sebelum_dibandingkan(): void
    {
        $no = $this->permitPending();
        $this->assertDitolak('VISITOR_PERMIT_KOREKSI_TANPA_PERUBAHAN', 422, fn () => $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['no_plat' => ' bg  1234 aa ']), 'x'));
    }

    public function test_nilai_lama_riwayat_dibaca_setelah_kunci(): void
    {
        // Dua koreksi berurutan: nilai lama koreksi kedua adalah nilai baru koreksi pertama.
        $no = $this->permitPending();
        $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['tahun_kendaraan' => 2019]), 'pertama');
        $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['tahun_kendaraan' => 2018]), 'kedua');

        $tahun = DB::table(PermitKoreksiService::T_KOREKSI)->where('No_Transaksi', $no)->orderBy('Id_Permit_Koreksi')->get();
        $this->assertSame(['2020', '2019'], $tahun->pluck('Nilai_Lama')->all());
    }

    public function test_form_basi_tidak_menimpa_koreksi_rekan(): void
    {
        // A dan B membuka permit yang sama. A mengoreksi plat; B yang formnya
        // masih memuat plat lama hanya mengubah tahun. Plat A harus bertahan.
        $no = $this->permitPending();
        $awalB = $this->input();

        $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['no_plat' => 'BG 9999 ZZ']), 'A: plat salah', $this->input());
        $berubah = $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['tahun_kendaraan' => 2018]), 'B: tahun salah', $awalB);

        $this->assertSame([PermitKoreksiService::FIELD_TAHUN], $berubah);
        $d = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $no)->first();
        $this->assertSame(['BG', '9999', 'ZZ'], [$d->Plat_Nomor_Prefix, $d->Plat_Nomor_Angka, $d->Plat_Nomor_Suffix]);
        $this->assertSame(2018, (int) $d->Tahun_Kendaraan);
    }

    public function test_field_yang_sama_diubah_rekan_ditolak_sebagai_konflik(): void
    {
        $no = $this->permitPending();
        $awalB = $this->input();

        $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['no_plat' => 'BG 9999 ZZ']), 'A', $this->input());

        $this->assertDitolak('VISITOR_PERMIT_KOREKSI_KONFLIK', 409, fn () => $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['no_plat' => 'BG 5555 YY']), 'B', $awalB));
    }

    public function test_riwayat_dikelompokkan_terbaru_dulu(): void
    {
        $no = $this->permitPending();
        $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['tahun_kendaraan' => 2019, 'id_vehicle_color' => $this->warnaLain()]), 'pertama');
        $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['tahun_kendaraan' => 2018, 'id_vehicle_color' => $this->warnaLain()]), 'kedua');

        $riwayat = $this->svc()->riwayat($no);

        $this->assertCount(2, $riwayat);
        $this->assertSame('kedua', $riwayat[0]['alasan']);
        $this->assertSame(self::VERIFIKATOR, $riwayat[0]['oleh']);
        $this->assertContains(['field' => PermitKoreksiService::FIELD_TAHUN, 'lama' => '2020', 'baru' => '2019'], $riwayat[1]['perubahan']);
    }

    // ── helper ─────────────────────────────────────────────────────────

    private function svc(): PermitKoreksiService
    {
        return app(PermitKoreksiService::class);
    }

    private function assertDitolak(string $kode, int $status, callable $aksi): void
    {
        try {
            $aksi();
            $this->fail("Diharapkan PermitKoreksiException {$kode}");
        } catch (PermitKoreksiException $e) {
            $this->assertSame($kode, $e->kode, $e->getMessage());
            $this->assertSame($status, $e->status);
        }
    }

    /** Isian lengkap = data permit saat ini; timpa hanya field yang diuji. */
    private function input(array $timpa = []): array
    {
        return array_merge([
            'id_kategori_permit' => $this->katA,
            'no_plat' => 'BG 1234 AA',
            'id_vehicle_model' => 3,
            'id_vehicle_color' => 1,
            'tahun_kendaraan' => 2020,
            'keterangan_pengajuan' => 'Parkir harian',
        ], $timpa);
    }

    private function kategori(string $muncul, string $aktif = 'Y'): int
    {
        return (int) DB::table('N_EMI_VISITOR_Master_Kategori_Permit')->insertGetId([
            'Nama_Kategori' => 'UJI '.Str::upper(Str::random(8)),
            'Flag_Aktif' => $aktif,
            'Flag_Muncul_Pengajuan' => $muncul,
        ]);
    }

    private function namaKategori(int $id): string
    {
        return (string) DB::table('N_EMI_VISITOR_Master_Kategori_Permit')->where('Id_Kategori_Permit', $id)->value('Nama_Kategori');
    }

    private function warnaLain(): int
    {
        return (int) DB::table('N_EMI_VISITOR_Master_Vehicle_Color')->where('Flag_Aktif', 'Y')->where('Id_Master_Color', '<>', 1)->inRandomOrder()->value('Id_Master_Color');
    }

    private function pertanyaan(string $teks, ?int $kategori, ?int $tipe): int
    {
        return (int) DB::table('N_EMI_VISITOR_Master_Approval_Question')->insertGetId([
            'Teks_Pertanyaan' => $teks,
            'Target_Id_Kategori_Permit' => $kategori,
            'Target_Id_Tipe_Kendaraan' => $tipe,
            'Flag_Aktif' => 'Y',
        ]);
    }

    /** Permit HCIS PENDING milik 'A1', kategori A, Avanza hitam 2020, checklist terjawab 'YA'. */
    private function permitPending(string $approver = self::VERIFIKATOR): string
    {
        $no = 'PMT-KRK-'.Str::upper(Str::random(8));
        $idHeader = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $no, 'Kode_Karyawan_Pemohon' => 'A1', 'Sumber_Pengajuan' => 'HCIS',
            'Flag_Aktif' => 'Y', 'Flag_Approval' => null, 'Created_At' => now(), 'Created_By' => 'A1',
            'Idempotency_Key' => 'KRK-'.Str::upper(Str::random(20)),
        ]);
        $idDetail = (int) DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insertGetId([
            'Id_Transaksi_Permit' => $idHeader, 'No_Transaksi' => $no,
            'Plat_Nomor_Prefix' => 'BG', 'Plat_Nomor_Angka' => '1234', 'Plat_Nomor_Suffix' => 'AA',
            'Id_Master_Model' => 3, 'Id_Master_Warna' => 1, 'Tahun_Kendaraan' => 2020,
            'Id_Master_Kategori_Permit' => $this->katA, 'Snapshot_Kategori_Permit' => $this->namaKategori($this->katA),
            'Keterangan_Pengajuan' => 'Parkir harian',
        ]);
        app(VisitorPermitChecklistService::class)->buatSnapshot($no, $idDetail, $this->katA, 3);
        DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->where('No_Transaksi', $no)->update(['Jawaban_Admin' => 'YA']);
        DB::table('HRIS_Approval_Request')->insert([
            'No_Transaksi' => $no, 'Kode_Karyawan' => 'A1', 'Request_Order_Flow' => 1,
            'Approver_Karyawan_Kode' => $approver, 'Flag_Approval' => null,
            'Expired_At' => now()->addDays(3), 'created_at' => now(), 'update_at' => now(),
        ]);

        return $no;
    }

    private function pasangAksesInspeksi(string $no): void
    {
        $mulai = now()->addDay()->setTime(10, 0);
        DB::table('N_VISITOR_Inspeksi_Booking')->insert([
            'No_Transaksi_Permit' => $no,
            'Id_Pos_Inspeksi' => DB::table(JadwalInspeksiService::T_POS)->where('Flag_Aktif', 'Y')->value('Id_Pos_Inspeksi'),
            'Snapshot_Nama_Pos' => 'Pos Uji Koreksi',
            'Waktu_Mulai' => $mulai, 'Waktu_Selesai' => $mulai->copy()->addMinutes(30),
            'Status' => JadwalInspeksiService::TERJADWAL, 'Created_At' => now(), 'Created_By' => 'UJI',
        ]);
        app(VisitorEfektifService::class)->terbitkanAksesInspeksi($no, $mulai, $mulai->copy()->addMinutes(30));
    }
}
