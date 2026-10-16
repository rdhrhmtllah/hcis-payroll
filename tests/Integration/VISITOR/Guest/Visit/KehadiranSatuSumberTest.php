<?php

namespace Tests\Integration\VISITOR\Guest\Visit;

use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use App\Models\ESS\Employee\Models\User;
use App\Services\ESS\Dashboard\Homepage\HomepageVisitorService;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService;
use App\Services\VISITOR\Workflow\Reporting\Dashboard\VisitorDashboardOperasionalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Satu sumber kehadiran untuk SELURUH halaman tamu.
 *
 * Kehadiran tidak pernah ditulis HCIS: aplikasi palang di gerbang menuliskannya
 * ke N_VISITOR_Tamu_Efektif, dan syncTamuEfektif sengaja tidak menyentuh kedua
 * kolom waktu itu. Setiap pembaca yang menurunkan aturannya sendiri — entah
 * membaca kolom detail saja, entah memercayai Status_Kunjungan — akan buta
 * terhadap orang yang benar-benar masuk.
 *
 * Uji ini menagih hal yang sama dari SEMUA pembaca sekaligus, karena cacatnya
 * memang muncul satu per satu di tempat yang berbeda.
 */
class KehadiranSatuSumberTest extends TestCase
{
    use DatabaseTransactions;

    private const PIC = 'ZZKHD9';

    public function test_helper_kehadiran_menempatkan_efektif_di_atas_detail(): void
    {
        $sql = app(VisitorKehadiranQueryService::class)->masukSql();

        // Urutan COALESCE menentukan siapa yang menang saat keduanya terisi.
        // Palang harus menang; catatan manual hanya cadangan.
        $this->assertStringContainsString('e.Waktu_Aktual_Masuk', $sql);
        $this->assertStringContainsString('d.Waktu_Aktual_Masuk', $sql);
        $this->assertLessThan(
            strpos($sql, 'd.Waktu_Aktual_Masuk'),
            strpos($sql, 'e.Waktu_Aktual_Masuk'),
            'Kolom efektif harus disebut lebih dulu di dalam COALESCE.',
        );
    }

    public function test_penyaring_kehadiran_ditulis_sargable(): void
    {
        // COALESCE(a,b) IS NOT NULL membungkus kolom dalam fungsi sehingga
        // indeks IX_VISITOR_Tamu_Efektif_Kehadiran tidak akan pernah terpakai.
        $sql = app(VisitorKehadiranQueryService::class)->adaMasukSql();

        $this->assertStringNotContainsString('COALESCE', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function test_kartu_homepage_melihat_tamu_yang_ditandai_palang(): void
    {
        // Diukur sebagai SELISIH, bukan ambang mutlak: basis data pengembangan
        // sudah memuat kunjungan lain, dan ambang ">= 1" akan lulus karena baris
        // orang lain — hijau palsu yang menyembunyikan cacatnya.
        $kode = $this->kodePemohon();
        $sebelum = app(HomepageVisitorService::class)->getSummary($kode)['active_now'];

        $nomor = $this->buatKunjungan(masukPalang: now()->subHour())[0];
        $sesudah = app(HomepageVisitorService::class)->getSummary($kode)['active_now'];

        $this->assertSame(
            $sebelum + 1,
            $sesudah,
            "Kunjungan {$nomor} sudah ditandai palang tetapi tidak menambah hitungan homepage.",
        );
    }

    public function test_kartu_homepage_tidak_menghitung_yang_sudah_keluar(): void
    {
        $kode = $this->kodePemohon();
        $sebelum = app(HomepageVisitorService::class)->getSummary($kode)['active_now'];

        $this->buatKunjungan(masukPalang: now()->subHours(3), keluarPalang: now()->subMinutes(5));

        $this->assertSame($sebelum, app(HomepageVisitorService::class)->getSummary($kode)['active_now']);
    }

    public function test_di_dalam_area_melihat_tamu_yang_ditandai_palang(): void
    {
        [$nomor] = $this->buatKunjungan(masukPalang: now()->subHours(2));

        $daftar = app(VisitorDashboardOperasionalService::class)
            ->queryDiDalamArea()
            ->pluck('No_Transaksi')
            ->all();

        $this->assertContains($nomor, $daftar);
    }

    public function test_di_dalam_area_membuang_yang_sudah_keluar_lewat_palang(): void
    {
        [$nomor] = $this->buatKunjungan(masukPalang: now()->subHours(3), keluarPalang: now()->subMinutes(5));

        $daftar = app(VisitorDashboardOperasionalService::class)
            ->queryDiDalamArea()
            ->pluck('No_Transaksi')
            ->all();

        $this->assertNotContains($nomor, $daftar);
    }

    public function test_stats_halaman_tamu_menghitung_kehadiran_dari_palang(): void
    {
        $user = $this->penggunaPemohon();
        $sebelum = $this->statsTamu($user)['selesai_keluar'] ?? 0;

        $this->buatKunjungan(masukPalang: now()->subHours(2), keluarPalang: now()->subHour());

        $this->assertSame($sebelum + 1, $this->statsTamu($user)['selesai_keluar'] ?? 0);
    }

    /**
     * @return array<string,mixed>
     */
    private function statsTamu(User $user): array
    {
        $response = app(GuestRegistrationController::class)->getStats($this->permintaan($user));

        return json_decode($response->getContent(), true)['result'] ?? [];
    }

    private function kodePemohon(): string
    {
        return $this->karyawanPemohon()[0];
    }

    private function penggunaPemohon(): User
    {
        return $this->karyawanPemohon()[1];
    }

    /**
     * @return array{0:string,1:User}
     */
    private function karyawanPemohon(): array
    {
        $karyawan = DB::table('Karyawan')->whereNotNull('UserID_Web')->orderBy('Kode_Karyawan')
            ->first(['Kode_Karyawan', 'UserID_Web']);
        $this->assertNotNull($karyawan);
        $user = User::find($karyawan->UserID_Web);
        $this->assertNotNull($user);

        return [trim((string) $karyawan->Kode_Karyawan), $user];
    }

    // ---------------------------------------------------------------- helpers

    private function permintaan(User $user): Request
    {
        $request = Request::create('/transaksitamu-stats', 'GET');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    /**
     * @return array{0:string,1:User,2:string}
     */
    private function buatKunjungan(
        ?\DateTimeInterface $masukPalang = null,
        ?\DateTimeInterface $keluarPalang = null,
    ): array {
        [$kode, $user] = $this->karyawanPemohon();
        $nomor = 'VSR-KSS-'.Str::upper(Str::random(10));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => $kode,
            // Sengaja SCHEDULED, bukan CHECKED_IN: palang tidak menulis kolom
            // status, jadi inilah keadaan sebenarnya di produksi. (Kode
            // tersimpan sejak DDL-2026-121; dulu 'TERJADWAL'.)
            'Status_Kunjungan' => StatusKunjunganTamu::SCHEDULED,
            'Tanggal_Kunjungan' => now()->toDateString(),
            'Needs_Approval' => 'T',
            'Flag_Approval' => 'Y',
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => $kode,
            'Idempotency_Key' => 'KSS-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'No_Transaksi' => $nomor,
            'Id_Transaksi_Tamu' => $id,
            'Nama_Lengkap_Tamu' => 'Tamu Satu Sumber',
            'Jumlah_Tamu' => 1,
        ]);

        DB::table('N_VISITOR_Tamu_Efektif')->insert([
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $nomor,
            'Nama_Tamu' => 'Tamu Satu Sumber',
            'Kode_Karyawan_PIC' => $kode,
            'Nama_PIC' => 'PIC Uji',
            'Tanggal_Kunjungan' => now()->toDateString(),
            'Status_Efektif' => 'AKTIF',
            'Waktu_Aktual_Masuk' => $masukPalang,
            'Waktu_Aktual_Keluar' => $keluarPalang,
            'Disetujui_Oleh_Nama' => 'Sistem Uji',
            'Disetujui_At' => now(),
            'Riwayat_Approval' => '[]',
            'Dibuat_At' => now(),
            'Dibuat_Oleh_Nama' => 'Sistem Uji',
            'Updated_At' => now(),
            'Flag_Sync' => 'Y',
        ]);

        return [$nomor, $user, $kode];
    }
}
