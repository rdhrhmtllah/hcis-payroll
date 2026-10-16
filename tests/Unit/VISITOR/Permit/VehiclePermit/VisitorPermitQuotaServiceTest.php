<?php

namespace Tests\Unit\VISITOR\Permit\VehiclePermit;

use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQuotaService;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalPolicyService;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class VisitorPermitQuotaServiceTest extends TestCase
{
    /**
     * Penjaga terpenting berkas ini.
     *
     * Kuota diputuskan per status permit. Kalau kelak ada status baru di
     * permitStatusSql() dan tak seorang pun memutuskan apakah ia memakan kuota,
     * status itu akan diam-diam dianggap membebaskan kuota — jatah bocor tanpa
     * ada yang sadar. Uji ini memaksa keputusannya diambil: menambah status
     * tanpa menaruhnya di salah satu daftar akan MERAH di sini.
     */
    public function test_setiap_status_permit_diputuskan_memakai_kuota_atau_tidak(): void
    {
        $sql = app(VisitorPermitQueryService::class)->permitStatusSql();

        preg_match_all("/THEN\s+'([A-Z_]+)'|ELSE\s+'([A-Z_]+)'/", $sql, $cocok);
        $status = array_values(array_unique(array_filter(array_merge($cocok[1], $cocok[2]))));

        $this->assertNotEmpty($status, 'Status permit tidak terbaca dari permitStatusSql().');

        $diputuskan = array_merge(
            VisitorPermitQuotaService::STATUS_MEMAKAI_KUOTA,
            VisitorPermitQuotaService::STATUS_MEMBEBASKAN_KUOTA,
        );

        sort($status);
        sort($diputuskan);

        $this->assertSame(
            $status,
            $diputuskan,
            'Ada status permit yang belum diputuskan memakan kuota atau tidak.',
        );
    }

    /**
     * Dua ekspresi status tidak boleh berselisih soal "dicabut".
     *
     * permitStatusSql() dipakai admin dan kuota; statusCaseSql() dipakai layar
     * pemohon. Keduanya CASE terpisah, jadi tanpa penjaga ini salah satunya
     * bisa berhenti mengenali pencabutan — dan gejalanya berupa dua layar yang
     * saling bertentangan, bukan galat.
     */
    public function test_kedua_ekspresi_status_memakai_deteksi_pencabutan_yang_sama(): void
    {
        $query = app(VisitorPermitQueryService::class);
        $dicabut = $query->dicabutSql();

        $controller = app(\App\Http\Controllers\VISITOR\Permit\VehiclePermit\VehiclePermitController::class);
        $method = new \ReflectionMethod($controller, 'statusCaseSql');
        $method->setAccessible(true);

        $this->assertStringContainsString($dicabut, $query->permitStatusSql());
        $this->assertStringContainsString($dicabut, $method->invoke($controller));
    }

    public function test_cabang_revoked_diperiksa_sebelum_approved(): void
    {
        // Pencabutan tidak menyentuh Flag_Approval, sehingga permit yang sudah
        // dicabut TETAP memenuhi cabang APPROVED. Urutan cabang inilah satu-
        // satunya yang mencegah akses mati terbaca aktif.
        $sql = app(VisitorPermitQueryService::class)->permitStatusSql();

        $this->assertLessThan(
            strpos($sql, "'APPROVED'"),
            strpos($sql, "'REVOKED'"),
            'Cabang REVOKED harus mendahului APPROVED.',
        );
    }

    public function test_status_yang_masih_hidup_memakan_kuota(): void
    {
        $this->assertSame(
            ['APPROVED', 'NO_FLOW', 'PENDING', 'VALIDITY_REQUIRED'],
            $this->urut(VisitorPermitQuotaService::STATUS_MEMAKAI_KUOTA),
        );
    }

    public function test_status_yang_sudah_selesai_mengembalikan_kuota(): void
    {
        // REVOKED masuk daftar ini: aksesnya memang sudah tidak ada, jadi
        // menahan jatahnya membuat pemohon terkunci tanpa penjelasan.
        $this->assertSame(
            ['EXPIRED', 'REJECTED', 'REVOKED'],
            $this->urut(VisitorPermitQuotaService::STATUS_MEMBEBASKAN_KUOTA),
        );
    }

    public function test_batas_dibaca_dari_config_permit(): void
    {
        config(['services.visitor.permit.max_pengajuan_aktif' => 5]);

        $this->assertSame(5, app(VisitorPermitQuotaService::class)->batas());
    }

    public function test_batas_baku_dua_agar_fitur_menyala_tanpa_menunggu_env(): void
    {
        // Baku 0 akan membuat penjaga ini tak pernah berbunyi kalau satu env
        // lupa dipasang, dan tak ada gejala apa pun yang memberi tahu.
        $this->assertSame(2, app(VisitorPermitQuotaService::class)->batas());
    }

    public function test_batas_ikut_terbawa_ke_prop_halaman_permit(): void
    {
        // Formulir perlu tahu batasnya untuk menampilkan sisa jatah; propnya
        // sudah mengalir lewat pageProps(), jadi tak ada plumbing baru.
        $props = app(VisitorApprovalPolicyService::class)->pageProps('permit', null);

        $this->assertSame(2, (int) $props['config']['max_pengajuan_aktif']);
    }

    public function test_batas_nol_berarti_tanpa_batas(): void
    {
        config(['services.visitor.permit.max_pengajuan_aktif' => 0]);

        $quota = app(VisitorPermitQuotaService::class);

        $this->assertSame(0, $quota->batas());
        $this->assertFalse(
            $quota->melampauiBatas(999),
            'Batas 0 adalah jalan keluar eksplisit "tanpa batas".',
        );
    }

    public function test_batas_negatif_diperlakukan_sebagai_tanpa_batas(): void
    {
        // Salah ketik di .env tidak boleh berubah jadi batas yang mustahil
        // dipenuhi sehingga semua orang terkunci dari mengajukan.
        config(['services.visitor.permit.max_pengajuan_aktif' => -3]);

        $quota = app(VisitorPermitQuotaService::class);

        $this->assertSame(0, $quota->batas());
        $this->assertFalse($quota->melampauiBatas(1));
    }

    public function test_batas_mengikat_tepat_saat_jatah_habis(): void
    {
        config(['services.visitor.permit.max_pengajuan_aktif' => 2]);

        $quota = app(VisitorPermitQuotaService::class);

        $this->assertFalse($quota->melampauiBatas(0));
        $this->assertFalse($quota->melampauiBatas(1));
        $this->assertTrue($quota->melampauiBatas(2), 'Yang ke-3 harus ditolak, bukan yang ke-4.');
        $this->assertTrue($quota->melampauiBatas(3));
    }

    public function test_atomic_guard_refuses_to_run_without_transaction(): void
    {
        DB::shouldReceive('transactionLevel')->once()->andReturn(0);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires an active transaction');

        app(VisitorPermitQuotaService::class)->assertAvailableWithinTransaction('EMP-001');
    }

    /**
     * @param  array<int,string>  $nilai
     * @return array<int,string>
     */
    private function urut(array $nilai): array
    {
        sort($nilai);

        return $nilai;
    }
}
