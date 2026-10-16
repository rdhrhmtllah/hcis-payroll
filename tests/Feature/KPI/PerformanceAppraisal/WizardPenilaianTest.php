<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

/**
 * Wizard tugas penilaian lewat HTTP (spec §5.3, §6): urutan dan kunci langkah, akses lembar
 * yang tertahan pada langkah terkunci, pratinjau Kalibrasi Penilai, dan kode galat stabil.
 */
class WizardPenilaianTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanLembarPenilaian;

    private const API = '/api/v1/performance-appraisal/saya';

    protected function setUp(): void
    {
        parent::setUp();

        // Urutan ditetapkan di sini, tidak menumpang DML-2026-124: uji yang bergantung pada
        // data yang tidak ia buat akan memerah begitu datanya berubah, dengan sebab yang salah.
        DB::table('N_PA_Rater_Category')->update(['Urutan_Pengisian' => null]);

        foreach (['SELF' => 1, 'ATASAN' => 2, 'PEER' => 3, 'BAWAHAN' => 4] as $kode => $urutan) {
            DB::table('N_PA_Rater_Category')->where('Kode_Kategori', $kode)->update(['Urutan_Pengisian' => $urutan]);
        }
    }

    public function test_langkah_berurutan_menurut_master_dan_terkunci_sampai_sebelumnya_tuntas(): void
    {
        $self = $this->lembarTerisi(['kategori' => 'SELF', 'status_penugasan' => 'BELUM_MULAI']);
        $this->lembarTerisi(['kategori' => 'ATASAN', 'cycle_id' => $self['cycle_id']]);

        $this->actingAs($this->penilaiUji());
        $r = $this->getJson(self::API.'/wizard?cycle_id='.$self['cycle_id'])->assertOk();

        $this->assertSame(['SELF', 'ATASAN'], array_column($r->json('result.langkah'), 'kode_kategori'));
        $this->assertSame(['AKTIF', 'TERKUNCI'], array_column($r->json('result.langkah'), 'status'));
        $this->assertStringContainsString('Diri Sendiri', (string) $r->json('result.langkah.1.sebab_terkunci'));
        $this->assertSame('SELF', $r->json('result.langkah_aktif'));
    }

    public function test_kategori_tanpa_lembar_dilompati_dan_semua_tuntas_menuju_ringkasan(): void
    {
        $l = $this->lembarTerisi(['kategori' => 'ATASAN']);

        $this->actingAs($this->penilaiUji());
        $r = $this->getJson(self::API.'/wizard?cycle_id='.$l['cycle_id'])->assertOk();

        $this->assertSame(['ATASAN'], array_column($r->json('result.langkah'), 'kode_kategori'));
        $this->assertSame('TUNTAS', $r->json('result.langkah.0.status'));
        $this->assertSame('RINGKASAN', $r->json('result.langkah_aktif'));
        $this->assertTrue($r->json('result.boleh_kirim'));
    }

    /**
     * Pengiriman mencatat waktu, pelaku, dan jalurnya di kolom sendiri (DDL-2026-169), bukan
     * hanya di Updated_At yang tertimpa oleh perubahan apa pun sesudahnya.
     */
    public function test_kirim_mencatat_waktu_pelaku_dan_jalur_pengiriman(): void
    {
        $l = $this->lembarTerisi(['kategori' => 'ATASAN']);
        $user = $this->penilaiUji();

        $sebelum = DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $l['assignment_id'])
            ->first(['Submitted_At', 'Submitted_By', 'Submitted_Via']);
        $this->assertNull($sebelum->Submitted_At, 'Lembar yang belum dikirim sudah bercap kirim.');

        $this->actingAs($user);
        $this->postJson(self::API.'/kirim-semua', ['cycle_id' => $l['cycle_id']])->assertOk();

        $sesudah = DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $l['assignment_id'])
            ->first(['Status_Penugasan', 'Submitted_At', 'Submitted_By', 'Submitted_Via']);
        $this->assertSame('SELESAI', $sesudah->Status_Penugasan);
        $this->assertNotNull($sesudah->Submitted_At);
        $this->assertSame((int) $user->Id_Users, (int) $sesudah->Submitted_By);
        $this->assertSame('PENILAI', $sesudah->Submitted_Via);
    }

    /**
     * Dua siklus terbuka bersamaan: keduanya ditawarkan, dan tanpa pilihan yang dibuka adalah
     * yang masih menuntut tindakan — bukan yang id-nya terbesar.
     */
    public function test_dua_siklus_terbuka_ditawarkan_dan_yang_perlu_tindakan_dipilih(): void
    {
        $lama = $this->lembarTerisi(['kategori' => 'ATASAN', 'status_penugasan' => 'BELUM_MULAI']);
        $baru = $this->lembarTerisi(['kategori' => 'ATASAN', 'status_penugasan' => 'SELESAI']);
        $this->assertGreaterThan($lama['cycle_id'], $baru['cycle_id']);

        $this->actingAs($this->penilaiUji());
        $r = $this->getJson(self::API.'/wizard')->assertOk();

        $ditawarkan = array_column($r->json('result.siklus_tersedia'), 'cycle_id');
        $this->assertContains($lama['cycle_id'], $ditawarkan);
        $this->assertContains($baru['cycle_id'], $ditawarkan);
        $this->assertSame($lama['cycle_id'], $r->json('result.siklus.cycle_id'),
            'Yang sudah terkirim semua tidak boleh menutupi siklus yang masih punya tugas.');

        // Pilihan eksplisit tetap dihormati.
        $this->getJson(self::API.'/wizard?cycle_id='.$baru['cycle_id'])->assertOk()
            ->assertJsonPath('result.siklus.cycle_id', $baru['cycle_id']);
    }

    public function test_menunggu_verifikasi_dilaporkan_terpisah_dari_belum_diisi(): void
    {
        $l = $this->lembarTerisi(['kategori' => 'ATASAN', 'status_penugasan' => 'MENUNGGU_VERIFIKASI']);

        $this->actingAs($this->penilaiUji());
        $r = $this->getJson(self::API.'/wizard?cycle_id='.$l['cycle_id'])->assertOk();

        $this->assertSame('TUNTAS', $r->json('result.langkah.0.status'));
        $this->assertSame(1, $r->json('result.pengiriman.menunggu_verifikasi'));
        $this->assertSame(0, $r->json('result.pengiriman.belum_diisi'));
        $this->assertSame(100, $r->json('result.pengiriman.persen'));
        $this->assertFalse($r->json('result.boleh_kirim'));
    }

    public function test_langkah_terkunci_tidak_membawa_akses_lembar(): void
    {
        $self = $this->lembarTerisi(['kategori' => 'SELF', 'status_penugasan' => 'BELUM_MULAI']);
        $atasan = $this->lembarTerisi(['kategori' => 'ATASAN', 'status_penugasan' => 'BELUM_MULAI', 'cycle_id' => $self['cycle_id']]);
        DB::table('N_PA_Assignment_Integration')->where('PA_Assignment_Id', $atasan['assignment_id'])
            ->update(['Short_Token' => 'KODE1', 'Akses_Otp' => '123456', 'Link_Ujian' => 'https://hclearn.contoh/ujian/1']);

        $this->actingAs($this->penilaiUji());
        $r = $this->getJson(self::API.'/wizard/langkah/ATASAN/lembar?cycle_id='.$self['cycle_id'])->assertOk();

        $this->assertSame('TERKUNCI', $r->json('result.langkah.status'));
        $baris = $r->json('result.lembar.0');
        $this->assertNull($baris['url_ujian']);
        $this->assertNull($baris['short_token']);
        $this->assertNull($baris['akses_otp']);
        $this->assertStringNotContainsString('KODE1', $r->getContent(), 'Kode akses bocor lewat kolom lain.');
        $this->assertStringNotContainsString('123456', $r->getContent(), 'OTP bocor lewat kolom lain.');
    }

    public function test_pratinjau_rata_rata_ternormalisasi_dan_kolom_gabungan(): void
    {
        $a = $this->lembarTerisi(['skor' => [80, 60], 'skor_maksimal' => 100]);
        $this->lembarTerisi(['skor' => [4], 'skor_maksimal' => 5, 'cycle_id' => $a['cycle_id']]);

        $this->actingAs($this->penilaiUji());
        $r = $this->getJson(self::API.'/wizard/langkah/ATASAN/pratinjau?cycle_id='.$a['cycle_id'])->assertOk();

        $kolom = collect($r->json('result.kompetensi'))->keyBy('competency_id');
        // (80/100 dan 4/5) dinormalkan: (80 + 80) / 2 = 80; kompetensi kedua hanya dimiliki satu orang.
        $this->assertEquals(80.0, $kolom[$a['kompetensi'][0]]['rata_rata']);
        $this->assertSame(2, $kolom[$a['kompetensi'][0]]['jumlah_dinilai']);
        $this->assertSame(1, $kolom[$a['kompetensi'][1]]['jumlah_dinilai']);
        $this->assertCount(2, $r->json('result.orang'));
    }

    public function test_boleh_diubah_mengikuti_penanda_kategori_dan_status_lembar(): void
    {
        $l = $this->lembarTerisi(['boleh_kalibrasi' => 'T']);
        $this->actingAs($this->penilaiUji());

        $r = $this->getJson(self::API.'/wizard/langkah/ATASAN/pratinjau?cycle_id='.$l['cycle_id'])->assertOk();
        $this->assertFalse($r->json('result.orang.0.boleh_diubah'));

        DB::table('N_PA_Rater_Category')->where('Kode_Kategori', 'ATASAN')->update(['Flag_Boleh_Kalibrasi' => 'Y']);
        $r = $this->getJson(self::API.'/wizard/langkah/ATASAN/pratinjau?cycle_id='.$l['cycle_id'])->assertOk();
        $this->assertTrue($r->json('result.orang.0.boleh_diubah'));
    }

    public function test_kalibrasi_lewat_http_mengembalikan_pratinjau_terbaru(): void
    {
        $l = $this->lembarTerisi(['skor' => [70, 90], 'boleh_kalibrasi' => 'Y']);
        $this->actingAs($this->penilaiUji());

        $r = $this->postJson(self::API.'/kalibrasi', [
            'assignment_id' => $l['assignment_id'],
            'perubahan' => [['competency_id' => $l['kompetensi'][0], 'skor' => 50]],
            'alasan' => 'Terlalu tinggi dibanding rekan',
        ])->assertOk();

        $sel = collect($r->json('result.pratinjau.orang.0.sel'))->keyBy('competency_id');
        $this->assertEquals(50.0, $sel[$l['kompetensi'][0]]['skor_efektif']);
        $this->assertEquals(70.0, $sel[$l['kompetensi'][0]]['skor_asli']);
        $this->assertTrue($sel[$l['kompetensi'][0]]['terkalibrasi']);
        $this->assertSame('Terlalu tinggi dibanding rekan', $r->json('result.pratinjau.orang.0.kalibrasi_terakhir.alasan'));
    }

    public function test_galat_kalibrasi_membawa_kode_stabil(): void
    {
        $l = $this->lembarTerisi(['boleh_kalibrasi' => 'T']);
        $this->actingAs($this->penilaiUji());

        $r = $this->postJson(self::API.'/kalibrasi', [
            'assignment_id' => $l['assignment_id'],
            'perubahan' => [['competency_id' => $l['kompetensi'][0], 'skor' => 50]],
            'alasan' => 'x',
        ]);

        $r->assertStatus(403);
        $this->assertSame('PA_KALIBRASI_DITUTUP', $r->json('error_code'));
        $this->assertNotEmpty($r->json('request_id'));
    }

    public function test_langkah_yang_bukan_milik_saya_dijawab_404(): void
    {
        $l = $this->lembarTerisi(['kategori' => 'ATASAN']);
        $this->actingAs($this->penilaiUji());

        $r = $this->getJson(self::API.'/wizard/langkah/PEER/pratinjau?cycle_id='.$l['cycle_id']);

        $r->assertStatus(404);
        $this->assertSame('PA_LANGKAH_TIDAK_DIKENAL', $r->json('error_code'));
    }

    public function test_panel_progres_dan_wizard_memakai_urutan_yang_sama(): void
    {
        DB::table('N_PA_Rater_Category')->update(['Urutan_Pengisian' => null]);
        DB::table('N_PA_Rater_Category')->where('Kode_Kategori', 'PEER')->update(['Urutan_Pengisian' => 1]);
        DB::table('N_PA_Rater_Category')->where('Kode_Kategori', 'ATASAN')->update(['Urutan_Pengisian' => 2]);

        $a = $this->lembarTerisi(['kategori' => 'ATASAN']);
        $this->lembarTerisi(['kategori' => 'PEER', 'cycle_id' => $a['cycle_id']]);
        $this->actingAs($this->penilaiUji());

        $wizard = array_column($this->getJson(self::API.'/wizard?cycle_id='.$a['cycle_id'])->json('result.langkah'), 'kode_kategori');
        $progres = array_column($this->getJson(self::API.'/progres-kategori?cycle_id='.$a['cycle_id'])->json('result.kategori'), 'kode_kategori');

        $this->assertSame(['PEER', 'ATASAN'], $wizard);
        $this->assertSame($wizard, $progres);
    }
}
