<?php

namespace Tests\Integration\VISITOR\Permit\VehiclePermit;

use App\Http\Controllers\VISITOR\Permit\VehiclePermit\VehiclePermitController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Permit yang dicabut harus terbaca oleh PEMOHONNYA, lengkap dengan sebabnya.
 *
 * Pencabutan menulis ke N_VISITOR_Permit_Efektif dan sama sekali tidak
 * menyentuh Flag_Approval. Tanpa penanganan khusus, permit yang sudah dicabut
 * tetap memenuhi syarat cabang 'APPROVED' — pemohon membaca "Aktif", kartu
 * digitalnya tetap tampil sah, dan yang bersangkutan baru tahu di gerbang.
 * Tidak ada satu pun error yang menyalakannya.
 */
class PermitDicabutTerbacaPemohonTest extends TestCase
{
    use DatabaseTransactions;

    private const PEMOHON = 'ZZRVK1';

    private const ALASAN = 'Kendaraan sudah tidak dipakai di area pabrik.';

    public function test_permit_yang_dicabut_tidak_lagi_berstatus_approved(): void
    {
        $nomor = $this->buatPermitDisetujui();

        $sebelum = $this->baris($nomor);
        $this->assertSame('APPROVED', $sebelum->Status_Permit, 'Prasyarat gagal: permitnya belum aktif.');

        $this->cabut($nomor);

        $this->assertSame('REVOKED', $this->baris($nomor)->Status_Permit);
    }

    public function test_sebab_pencabutan_ikut_dikirim_ke_pemohon(): void
    {
        // Verifikator WAJIB mengisi alasan saat mencabut; kalau alasannya tidak
        // ikut sampai ke layar pemohon, kewajiban itu tidak ada gunanya.
        $nomor = $this->buatPermitDisetujui();
        $this->cabut($nomor);

        $baris = $this->baris($nomor);

        $this->assertSame(self::ALASAN, $baris->Alasan_Pencabutan);
        $this->assertSame('Admin Uji', $baris->Dicabut_Oleh);
        $this->assertNotNull($baris->Dicabut_Pada);
    }

    public function test_urutan_cabang_status_menang_atas_approved(): void
    {
        // Flag_Approval TETAP 'Y' sesudah dicabut — itulah sebabnya cabang
        // REVOKED harus diperiksa lebih dulu. Uji ini menagih fakta itu secara
        // langsung, supaya pembalikan urutan CASE ketahuan.
        $nomor = $this->buatPermitDisetujui();
        $this->cabut($nomor);

        $this->assertSame(
            'Y',
            DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $nomor)->value('Flag_Approval'),
            'Prasyarat berubah: pencabutan kini menyentuh Flag_Approval, uji ini perlu ditinjau.',
        );
        $this->assertSame('REVOKED', $this->baris($nomor)->Status_Permit);
    }

    public function test_permit_yang_belum_dicabut_tidak_terpengaruh(): void
    {
        $nomor = $this->buatPermitDisetujui();

        $baris = $this->baris($nomor);

        $this->assertSame('APPROVED', $baris->Status_Permit);
        $this->assertNull($baris->Alasan_Pencabutan);
    }

    public function test_join_efektif_tidak_menggandakan_baris_permit(): void
    {
        // Join tambahan ke tabel efektif tidak boleh membuat satu pengajuan
        // terhitung dua kali — kuota dan daftar pemohon akan ikut salah.
        $nomor = $this->buatPermitDisetujui();
        $this->cabut($nomor);

        $this->assertCount(1, $this->query()->where('h.No_Transaksi', $nomor)->get());
    }

    public function test_hanya_permit_yang_dicabut_yang_berubah_status(): void
    {
        // Pemohon boleh punya lebih dari satu permit. Mencabut salah satunya
        // tidak boleh menyeret yang lain — gejalanya akan terlihat seperti
        // "semua akses saya hilang" padahal hanya satu yang dicabut.
        $dicabut = $this->buatPermitDisetujui();
        $utuh = $this->buatPermitDisetujui();

        $this->cabut($dicabut);

        $this->assertSame('REVOKED', $this->baris($dicabut)->Status_Permit);
        $this->assertSame('APPROVED', $this->baris($utuh)->Status_Permit);
        $this->assertNull($this->baris($utuh)->Alasan_Pencabutan, 'Alasan menempel ke permit yang salah.');
    }

    public function test_pencabutan_mengembalikan_jatah_kuota(): void
    {
        // Akses yang sudah dicabut tidak boleh terus menahan jatah: pemohon
        // akan membaca "2/2 terpakai" dan tidak bisa mengajukan pengganti,
        // tanpa satu pun pesan yang menjelaskan kenapa.
        $kuota = app(\App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQuotaService::class);

        $nomor = $this->buatPermitDisetujui();
        $sebelum = $kuota->terpakai(self::PEMOHON);

        $this->cabut($nomor);

        $this->assertSame(
            $sebelum - 1,
            $kuota->terpakai(self::PEMOHON),
            'Permit yang dicabut masih memakan jatah kuota.',
        );
    }

    public function test_permit_dicabut_tetap_terlihat_admin_di_all_monitoring(): void
    {
        // Menyingkirkannya dari monitoring akan membuat permit lenyap persis
        // pada saat orang paling ingin melihat kenapa ia dicabut.
        $nomor = $this->buatPermitDisetujui();
        $this->cabut($nomor);

        $isi = json_decode(
            app(\App\Http\Controllers\VISITOR\Workflow\Reporting\LaporanVisitorController::class)
                ->getLaporanPermit(\Illuminate\Http\Request::create('/x', 'GET', ['limit' => 200]))
                ->getContent(),
            true,
        )['result'];

        $baris = collect($isi)->firstWhere('No_Transaksi', $nomor);

        $this->assertNotNull($baris, 'Permit yang dicabut hilang dari All Monitoring.');
        $this->assertSame('DIBATALKAN', $baris['Status_Efektif']);
        $this->assertSame(self::ALASAN, $baris['Alasan_Pencabutan']);
    }

    public function test_kartu_permit_aktif_tidak_menghitung_yang_dicabut(): void
    {
        // Kartu "Permit Aktif" dulu menghitung Flag_Approval='Y' mentah.
        // Pencabutan tidak menyentuh kolom itu, sehingga kartunya tetap
        // mengklaim akses yang sudah dihentikan masih hidup — sementara
        // daftar di bawahnya bertuliskan "Dicabut". Dua angka bertentangan di
        // layar yang sama, tanpa satu pun error.
        $nomor = $this->buatPermitDisetujui();

        $sebelum = $this->stats();
        $this->assertGreaterThanOrEqual(1, $sebelum['permit_aktif']);

        $this->cabut($nomor);
        $sesudah = $this->stats();

        $this->assertSame($sebelum['permit_aktif'] - 1, $sesudah['permit_aktif']);
        $this->assertSame(($sebelum['dicabut'] ?? 0) + 1, $sesudah['dicabut']);
    }

    public function test_kartu_dan_daftar_pemohon_memakai_kosakata_yang_sama(): void
    {
        // Kartu memakai statusCaseSql(), daftar memakai basePermitQuery() yang
        // juga memakai statusCaseSql(). Kalau salah satunya pindah ke
        // permitStatusSql() — yang punya cabang VALIDITY_REQUIRED — permit yang
        // daftarnya bertuliskan "Aktif" berhenti terhitung di kartunya.
        $this->buatPermitDisetujui();

        $stats = $this->stats();
        $daftar = $this->query()->where('h.Kode_Karyawan_Pemohon', self::PEMOHON)->get();

        foreach (['APPROVED' => 'permit_aktif', 'REJECTED' => 'ditolak', 'REVOKED' => 'dicabut'] as $status => $kunci) {
            $this->assertSame(
                $daftar->where('Status_Permit', $status)->count(),
                (int) $stats[$kunci],
                "Kartu '{$kunci}' berselisih dengan daftar untuk status {$status}.",
            );
        }
    }

    // ---------------------------------------------------------------- helpers

    /** @return array<string,int> */
    private function stats(): array
    {
        $request = \Illuminate\Http\Request::create('/transaksipermit-stats', 'GET');
        $request->setUserResolver(fn () => (object) ['Kode_Karyawan' => self::PEMOHON]);

        return json_decode(
            app(VehiclePermitController::class)->getStats($request)->getContent(),
            true,
        )['result'];
    }

    private function query()
    {
        $controller = app(VehiclePermitController::class);
        $method = new \ReflectionMethod($controller, 'basePermitQuery');
        $method->setAccessible(true);

        return $method->invoke($controller);
    }

    private function baris(string $nomor): object
    {
        $baris = $this->query()->where('h.No_Transaksi', $nomor)->first();

        $this->assertNotNull($baris, "Permit {$nomor} tidak ditemukan.");

        return $baris;
    }

    private function cabut(string $nomor): void
    {
        $terpengaruh = app(\App\Services\VISITOR\Operations\Expiration\VisitorEfektifService::class)
            ->revokePermitEfektif($nomor, self::ALASAN, self::PEMOHON);

        // Memakai service yang sama dengan endpoint revoke, bukan UPDATE
        // sendiri: kalau aturannya berubah, uji ini ikut berubah dengan
        // sendirinya alih-alih menguji salinan aturan yang sudah basi.
        $this->assertSame(1, $terpengaruh, 'Pencabutan tidak menyentuh baris efektif mana pun.');

        DB::table('N_VISITOR_Permit_Efektif')
            ->where('No_Transaksi', $nomor)
            ->update(['Dibatalkan_Oleh_Nama' => 'Admin Uji']);
    }

    private function buatPermitDisetujui(): string
    {
        $nomor = 'PMT-RVK-'.Str::upper(Str::random(10));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => self::PEMOHON,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => 'Y',
            'Approved_At' => now()->subDay(),
            'Created_At' => now()->subDays(2),
            'Created_By' => self::PEMOHON,
            'Idempotency_Key' => 'RVK-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'No_Transaksi' => $nomor,
            'Id_Transaksi_Permit' => $id,
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(100000, 999999),
            'Plat_Nomor_Suffix' => 'RV',
            'Tanggal_Mulai_Berlaku' => now()->subDay()->toDateString(),
            'Tanggal_Kadaluarsa' => now()->addMonths(3)->toDateString(),
        ]);

        DB::table('N_VISITOR_Permit_Efektif')->insert([
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => self::PEMOHON,
            'Nama_Pemohon' => 'Pemohon Uji',
            'Status_Efektif' => 'AKTIF',
            'Tanggal_Mulai_Berlaku' => now()->subDay()->toDateString(),
            'Tanggal_Kadaluarsa' => now()->addMonths(3)->toDateString(),
            'Disetujui_Oleh_Nama' => 'Sistem Uji',
            'Disetujui_At' => now()->subDay(),
            'Riwayat_Approval' => '[]',
            'Dibuat_At' => now(),
            'Dibuat_Oleh_Nama' => 'Sistem Uji',
            'Updated_At' => now(),
            'Flag_Sync' => 'Y',
        ]);

        return $nomor;
    }
}
