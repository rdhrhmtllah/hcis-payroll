<?php

namespace Tests\Integration\VISITOR\Guest\Schedule;

use App\Http\Controllers\VISITOR\Guest\Schedule\JadwalKedatanganTamuController;
use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use App\Http\Requests\VISITOR\Guest\Visit\SimpanTamuRequest;
use App\Services\VISITOR\Guest\Visit\MatriksTamuService;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Matriks departemen × jenis tamu (DDL-2026-122).
 *
 * Yang dijaga adalah janji matriks user: QC Incoming hanya melihat B2B
 * Procurement, QC FG hanya B2B Logistic, GA hanya Regular; hanya Procurement
 * dan Logistic yang boleh mengajukan unitnya sendiri. Butuh data-change.sql
 * DDL-2026-122 sudah dijalankan (enam role + baris matriks).
 */
class MatriksTamuDepartemenTest extends TestCase
{
    use DatabaseTransactions;

    private int $pengguna;

    private string $kodeKaryawan;

    protected function setUp(): void
    {
        parent::setUp();

        // Satu karyawan nyata yang TIDAK memegang role departemen tamu apa pun;
        // role diberikan per uji di dalam transaksi.
        $baris = DB::table('Karyawan as k')
            ->whereNotNull('k.UserID_Web')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('N_HRIS_Role_User as ru')
                    ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
                    ->whereColumn('ru.UserId_Web', 'k.UserID_Web')
                    ->where('rf.Role_Code', 'like', 'TAMU_DEPT_%');
            })
            ->orderBy('k.UserID_Web')
            ->first(['k.UserID_Web', 'k.Kode_Karyawan']);

        $this->assertNotNull($baris, 'Butuh satu karyawan ber-UserID_Web tanpa role departemen tamu.');

        $this->pengguna = (int) $baris->UserID_Web;
        $this->kodeKaryawan = (string) $baris->Kode_Karyawan;
    }

    public function test_matriks_awal_sesuai_gambar_user(): void
    {
        $harapan = [
            'TAMU_DEPT_GA' => ['REGULAR', 'Y', 'T'],
            'TAMU_DEPT_LOGISTIC' => ['B2B_LOGISTIC', 'Y', 'Y'],
            'TAMU_DEPT_QC_FG' => ['B2B_LOGISTIC', 'Y', 'T'],
            'TAMU_DEPT_QC_INC' => ['B2B_PROCUREMENT', 'Y', 'T'],
            'TAMU_DEPT_WAREHOUSE' => ['B2B_PROCUREMENT', 'Y', 'T'],
            'TAMU_DEPT_PROCUREMENT' => ['B2B_PROCUREMENT', 'Y', 'Y'],
        ];

        $nyata = DB::table('N_VISITOR_Matriks_Tamu as m')
            ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'm.Role_Id')
            ->where('m.Flag_Aktif', 'Y')
            ->get(['rf.Role_Code', 'm.Kategori_Tamu', 'm.Flag_Lihat', 'm.Flag_Ajukan'])
            ->mapWithKeys(fn ($r) => [$r->Role_Code => [$r->Kategori_Tamu, $r->Flag_Lihat, $r->Flag_Ajukan]])
            ->all();

        foreach ($harapan as $role => $baris) {
            $this->assertSame($baris, $nyata[$role] ?? null, "Matriks {$role} menyimpang dari gambar user.");
        }
    }

    public function test_qc_incoming_hanya_melihat_procurement_dan_tak_bisa_mengajukan(): void
    {
        $this->beriRole('TAMU_DEPT_QC_INC');

        $matriks = new MatriksTamuService;

        $this->assertSame([MatriksTamuService::B2B_PROCUREMENT], $matriks->kategoriBolehLihat($this->pengguna));
        $this->assertSame([], $matriks->unitBolehAjukan($this->pengguna));
    }

    public function test_keanggotaan_nonaktif_tidak_memberi_hak(): void
    {
        $this->beriRole('TAMU_DEPT_GA', 'T');

        $this->assertSame([], (new MatriksTamuService)->kategoriBolehLihat($this->pengguna));
    }

    public function test_pengguna_tanpa_role_departemen_ditolak_mengajukan_b2b(): void
    {
        $respons = app(GuestRegistrationController::class)->store($this->permintaanB2b());

        $this->assertSame(403, $respons->getStatusCode());
        $this->assertSame('VISITOR_B2B_UNIT_TIDAK_BERHAK', json_decode($respons->getContent(), true)['error_code'] ?? null);
    }

    public function test_satu_unit_terkunci_walau_isian_meminta_unit_lain(): void
    {
        $this->beriRole('TAMU_DEPT_LOGISTIC');

        $nomor = $this->daftarkanB2b(['b2b_unit' => 'PROCUREMENT']);

        $this->assertSame(
            'LOGISTIC',
            DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->value('B2B_Unit'),
        );
    }

    public function test_dua_unit_wajib_memilih_salah_satunya(): void
    {
        $this->beriRole('TAMU_DEPT_LOGISTIC');
        $this->beriRole('TAMU_DEPT_PROCUREMENT');

        $tanpaPilihan = app(GuestRegistrationController::class)->store($this->permintaanB2b());
        $this->assertSame(422, $tanpaPilihan->getStatusCode());

        $nomor = $this->daftarkanB2b(['b2b_unit' => 'PROCUREMENT']);
        $this->assertSame(
            'PROCUREMENT',
            DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->value('B2B_Unit'),
        );
    }

    public function test_jadwal_hanya_memuat_kategori_departemen(): void
    {
        $this->beriRole('TAMU_DEPT_LOGISTIC');
        $logistic = $this->daftarkanB2b();

        $procurement = $this->sisipkanKunjungan('B2B', 'PROCUREMENT');
        $regular = $this->sisipkanKunjungan('REGULAR', null);

        // Pengguna kedua: QC FG — hanya B2B Logistic.
        DB::table('N_HRIS_Role_User')
            ->where('UserId_Web', $this->pengguna)
            ->where('Assigned_By', 'UJI-MATRIKS')
            ->update(['Flag_Aktif' => 'T']);
        $this->beriRole('TAMU_DEPT_QC_FG');

        $nomor = $this->jadwal(['dari' => now()->addDay()->toDateString(), 'sampai' => now()->addDays(2)->toDateString()]);

        $this->assertContains($logistic, $nomor);
        $this->assertNotContains($procurement, $nomor, 'QC FG tidak boleh melihat B2B Procurement.');
        $this->assertNotContains($regular, $nomor, 'QC FG tidak boleh melihat tamu Regular.');

        // Meminta kategori di luar matriks: disaring habis, bukan dilayani.
        $this->assertSame([], $this->jadwal([
            'dari' => now()->addDay()->toDateString(),
            'kategori' => MatriksTamuService::B2B_PROCUREMENT,
        ]));
    }

    public function test_jadwal_memuat_kunjungan_berhari_hari_di_hari_tengahnya(): void
    {
        $this->beriRole('TAMU_DEPT_GA');
        $nomor = $this->sisipkanKunjungan('REGULAR', null, now()->subDay()->setTime(8, 0), now()->addDays(2)->setTime(17, 0));

        $this->assertContains($nomor, $this->jadwal(['dari' => now()->toDateString()]));
    }

    public function test_halaman_jadwal_dialihkan_ke_monitoring(): void
    {
        // Halaman Jadwal dilebur ke Monitoring Tamu. URL-nya DIALIHKAN, bukan
        // dihapus: pranala lama hidup di bookmark, email, dan tangkapan layar
        // SOP, dan URL yang mati berakhir sebagai 404 yang dilaporkan sebagai
        // "modulnya hilang".
        $rute = Route::getRoutes()->match(Request::create('/jadwal-kedatangan-tamu', 'GET'));

        $this->assertStringContainsString('RedirectController', $rute->getActionName());
    }

    public function test_endpoint_data_jadwal_tetap_dijaga_kunci_halaman(): void
    {
        // Halamannya boleh pindah; DATANYA tidak boleh ikut terbuka. Endpoint
        // ini masih dipakai layar lain, dan melepas penjaganya bersamaan
        // dengan pemindahan halaman membuat dua perubahan yang berbeda
        // tampak seperti satu.
        foreach ([
            'jadwal-kedatangan-tamu-data',
            'jadwal-kedatangan-tamu-list',
            'jadwal-kedatangan-tamu-stats',
        ] as $uri) {
            $rute = Route::getRoutes()->match(Request::create('/'.$uri, 'GET'));
            $middleware = $rute->gatherMiddleware();

            $berkunci = collect($middleware)->contains(
                fn ($m) => is_string($m) && str_starts_with($m, 'check.jabatan:') && str_contains($m, 'visitorJadwalTamuPage'),
            );

            $this->assertTrue($berkunci, $uri.' kehilangan penjaga kunci halamannya.');
        }
    }

    private function beriRole(string $kode, string $aktif = 'Y'): void
    {
        DB::table('N_HRIS_Role_User')->insert([
            'UserId_Web' => $this->pengguna,
            'Id_Role' => DB::table('N_HRIS_Role_Functional')->where('Role_Code', $kode)->value('Id_Role'),
            'Assigned_At' => now(),
            'Assigned_By' => 'UJI-MATRIKS',
            'Flag_Aktif' => $aktif,
        ]);
    }

    private function penggunaUji(): object
    {
        return (object) ['Kode_Karyawan' => $this->kodeKaryawan, 'Id_Users' => $this->pengguna];
    }

    private function permintaanB2b(array $ubah = []): SimpanTamuRequest
    {
        $masuk = now()->addDay()->setTime(9, 0);
        $request = SimpanTamuRequest::create('/transaksi-tamu-b2b', 'POST', array_merge([
            'nama_tamu' => 'Tamu Matriks Uji',
            'keperluan' => 'Pengiriman',
            'pic_tujuan' => 'Gudang',
            'instansi_asal' => 'PT Uji',
            'waktu_mulai_kunjungan' => $masuk->format('Y-m-d H:i:s'),
            'waktu_selesai_kunjungan' => $masuk->copy()->addHours(4)->format('Y-m-d H:i:s'),
            'jenis_kendaraan' => DB::table('N_EMI_VISITOR_Master_Vehicle_Type')
                ->where('Flag_Aktif', 'Y')
                ->where(fn ($q) => $q->whereNull('Akses')->orWhereIn('Akses', ['ALL', 'TAMU']))
                ->where('Nama_Tipe_Kendaraan', 'NOT LIKE', '%tidak%')
                ->where('Nama_Tipe_Kendaraan', 'NOT LIKE', '%tanpa%')
                ->orderBy('Id_Vehicle_Type')
                ->value('Nama_Tipe_Kendaraan'),
            'plat_prefix' => DB::table('N_EMI_VISITOR_Master_Plat_Kendaraan')->where('Flag_Aktif', 'Y')->value('Kode_Plat'),
            'plat_angka' => '4321',
            'plat_suffix' => 'MT',
            'daftar_anggota' => [],
        ], $ubah));
        $request->headers->set('Idempotency-Key', 'MTX-'.Str::upper(Str::random(20)));
        $request->attributes->set(TipeTamu::ATRIBUT, TipeTamu::B2B);
        $request->setUserResolver(fn () => $this->penggunaUji());
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();

        return $request;
    }

    private function daftarkanB2b(array $ubah = []): string
    {
        $isi = json_decode(app(GuestRegistrationController::class)->store($this->permintaanB2b($ubah))->getContent(), true);
        $this->assertNotEmpty($isi['result']['no_transaksi'] ?? null, 'Registrasi gagal: '.json_encode($isi));

        return $isi['result']['no_transaksi'];
    }

    /** Kunjungan terjadwal langsung di tabel — cukup untuk dibaca jadwal. */
    private function sisipkanKunjungan(string $tipe, ?string $unit, $masuk = null, $keluar = null): string
    {
        $masuk ??= now()->addDay()->setTime(10, 0);
        $keluar ??= $masuk->copy()->addHours(3);
        $nomor = 'MTX-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => $this->kodeKaryawan,
            'Status_Kunjungan' => StatusKunjunganTamu::SCHEDULED,
            'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => $masuk->toDateString(),
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => $this->kodeKaryawan,
            'Idempotency_Key' => $nomor,
            'Sumber_Pengajuan' => 'HCIS',
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Tamu '.$nomor,
            'Tipe_Tamu' => $tipe,
            'B2B_Unit' => $unit,
            'Tamu_Kategori' => 'VISITOR',
            'Instansi_Atau_Asal' => 'PT Uji',
            'Jumlah_Tamu' => 1,
            'Waktu_Mulai_Kunjungan' => $masuk,
            'Waktu_Selesai_Kunjungan' => $keluar,
        ]);

        return $nomor;
    }

    /** @return list<string> nomor transaksi di jadwal */
    private function jadwal(array $param): array
    {
        $request = Request::create('/jadwal-kedatangan-tamu-data', 'GET', $param);
        $request->setUserResolver(fn () => $this->penggunaUji());

        $isi = json_decode(app(JadwalKedatanganTamuController::class)->data($request)->getContent(), true);
        $this->assertTrue($isi['success'] ?? false, 'Jadwal gagal: '.json_encode($isi));

        return array_column($isi['result']['baris'], 'No_Transaksi');
    }
}
