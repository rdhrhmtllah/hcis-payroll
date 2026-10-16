<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopDokumenService;
use App\Services\SYSTEM\MasterData\Entitas\EntitasService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Kode cetak entitas sebagai PEMILIK — DDL-2026-056.
 *
 * DUA POSISI, DUA ARTI. Nomor dokumen memuat entitas dua kali:
 *
 *     IK / ENB / PROD / EHG / 09 / 03 / 01
 *          ^pemilik          ^ruang lingkup
 *
 * Segmen pemilik menyebut badan hukum; segmen lingkup menyatakan cakupan, dan
 * "EHG" di sana berarti "seluruh entitas" — bukan nama sebuah perusahaan.
 * Karena itu holding tercetak "HOL" sebagai pemilik dan tetap "EHG" sebagai
 * lingkup.
 *
 * YANG PALING DIJAGA BERKAS INI: segmen pemilik dirakit di TIGA berkas
 * berbeda — daftar (SopAdminDokumenController::getDataCurrent), detail
 * (::detail), dan penyimpanan/pratinjau (SopDokumenService::segmenNomor).
 * Aturan yang hanya terpasang di sebagian akan membuat nomor di kartu daftar,
 * di panel detail, dan yang tersimpan berbeda satu sama lain — tanpa satu pun
 * pesan galat. Modul ini punya riwayat panjang penjaga yang cuma terpasang di
 * satu dari dua pintu.
 */
class SopKodePemilikNomorTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopDokumenService
    {
        return app(SopDokumenService::class);
    }

    private function idEntitas(string $kode): int
    {
        $id = DB::table('N_HRIS_Master_Entitas')->where('Kode_Entitas', $kode)->value('Id_Entitas');
        if (! $id) {
            $this->markTestSkipped("Seed entitas {$kode} belum ada.");
        }

        return (int) $id;
    }

    private function adminMaster(): User
    {
        $uid = DB::table('HRIS_Page_Access')
            ->where('Flag_Aktif', 'Y')->where('Jenis_page', 'menuSopDokumenPage')
            ->whereNotNull('UserID_Web')->value('UserID_Web');

        $u = $uid ? User::find($uid) : null;
        if (! $u) {
            $this->markTestSkipped('Tak ada user dengan menuSopDokumenPage.');
        }

        return $u;
    }

    /**
     * Dokumen beridentitas lengkap, dimiliki holding dan berlingkup holding.
     *
     * Kedua peran sengaja diisi entitas yang SAMA — justru di situlah kedua
     * kode harus terlihat berbeda. Dokumen yang pemilik dan lingkupnya berbeda
     * entitas tak akan pernah memperlihatkan cacat ini.
     */
    private function buatDokumenHolding(): int
    {
        $dep = DB::table('N_HRIS_Master_Departemen')->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan')->first(['Id_Departemen']);

        /* JENIS HARUS AKTIF, HARUS MENERIMA TIPE DOKUMEN, DAN HARUS DIPILIH
           SECARA DETERMINISTIK — tiga syarat yang sebelumnya tak satu pun ada.
           Fikstur lama hanya meminta "yang Kode_Jenis_Dokumen-nya terisi", tanpa
           ORDER BY, sehingga SQL Server bebas memulangkan baris mana saja. Di
           basis data dev per 1 Sep 2026 yang terpulang adalah CNTH ("CONTOH +
           Revisi", Flag_Aktif='T'), dan SopDokumenService::pastikanJenisSesuaiTipe()
           menolaknya dengan DomainException "Jenis dokumen yang dipilih tidak
           ada atau sudah dinonaktifkan" — sebelum satu pun aturan nomor sempat
           diuji.

           Bukan longgarkan penjaganya: penjaganya benar. Yang salah adalah
           fikstur yang mengambil master mati sebagai bahan uji. FORMULIR pun
           tersaring di sini karena ia hanya sah untuk tipe FORM, sementara
           dokumen uji ini bertipe DOKUMEN. */
        $jenis = DB::table('N_SOP_Master_Jenis_Dokumen as j')
            ->join('N_SOP_Master_Jenis_Dokumen_Tipe_Item as t', 't.Jenis_Dokumen_Id', '=', 'j.Id_SOP_Jenis_Dokumen')
            ->where('j.Flag_Aktif', 'Y')
            ->whereNotNull('j.Kode_Jenis_Dokumen')
            ->where('t.Tipe_Item', 'DOKUMEN')
            ->where('t.Flag_Aktif', 'Y')
            ->orderBy('j.Level_Hirarki')->orderBy('j.Id_SOP_Jenis_Dokumen')
            ->value('j.Id_SOP_Jenis_Dokumen');

        $apqc = DB::table('N_SOP_Master_APQC')->whereNotNull('Kode_Proses')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Id_SOP_APQC')->value('Id_SOP_APQC');

        if (! $dep || ! $jenis || ! $apqc) {
            $this->markTestSkipped(
                'Seed master belum lengkap: butuh departemen aktif, proses APQC aktif ber-Kode_Proses, '
                .'dan jenis dokumen AKTIF ber-Kode_Jenis_Dokumen yang menerima tipe DOKUMEN.');
        }

        $ehg = $this->idEntitas('EHG');

        return $this->svc()->buat([
            'Judul' => 'SOP UJI KODE PEMILIK HOLDING',
            'Tipe' => 'DOKUMEN',
            'Jenis_Dokumen_Id' => (int) $jenis,
            'Entitas_Pemilik_Id' => $ehg,
            'Departemen_Id' => (int) $dep->Id_Departemen,
            'Entitas_Lingkup_Id' => $ehg,
            'APQC_Id' => (int) $apqc,
            'No_Dokumen' => 97,
        ], 1);
    }

    /* ── Skema ──────────────────────────────────────────────────────────── */

    public function test_kolom_kode_sebagai_pemilik_ada(): void
    {
        $kolom = DB::selectOne(
            'SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['N_HRIS_Master_Entitas', 'Kode_Sebagai_Pemilik']
        );

        $this->assertNotNull($kolom,
            'Kolom Kode_Sebagai_Pemilik belum ada — jalankan DDL-2026-056.');
    }

    /* ── Aturan jatuh-balik ─────────────────────────────────────────────── */

    public function test_kode_kosong_jatuh_ke_kode_entitas(): void
    {
        /* Kolom yang lupa diisi HARUS berperilaku seperti sebelum fitur ini
           ada. Kalau ia menghasilkan null, nomornya kehilangan satu segmen dan
           rakit() memulangkan null — dokumen kehilangan nomornya diam-diam. */
        $this->assertSame('ENB', EntitasService::kodePemilik(
            (object) ['Kode_Entitas' => 'ENB', 'Kode_Sebagai_Pemilik' => null]
        ));
        $this->assertSame('ENB', EntitasService::kodePemilik(
            (object) ['Kode_Entitas' => 'ENB', 'Kode_Sebagai_Pemilik' => '   ']
        ));
    }

    public function test_kode_khusus_menang_atas_kode_entitas(): void
    {
        $this->assertSame('HOL', EntitasService::kodePemilik(
            (object) ['Kode_Entitas' => 'EHG', 'Kode_Sebagai_Pemilik' => 'HOL']
        ));
    }

    public function test_entitas_null_tetap_null(): void
    {
        $this->assertNull(EntitasService::kodePemilik(null));
    }

    /* ── Penjaga inti: tiga perakit harus sepakat ───────────────────────── */

    public function test_tiga_perakit_menghasilkan_nomor_yang_sama(): void
    {
        $ehg = $this->idEntitas('EHG');
        $kodePemilik = DB::table('N_HRIS_Master_Entitas')
            ->where('Id_Entitas', $ehg)->value('Kode_Sebagai_Pemilik');

        if (! $kodePemilik) {
            $this->markTestSkipped('EHG belum punya Kode_Sebagai_Pemilik — jalankan DML DDL-2026-056.');
        }

        $id = $this->buatDokumenHolding();
        $hash = Hashids::connection('custom')->encode($id);
        $admin = $this->adminMaster();

        /* 1. Perakit penyimpanan/pratinjau. */
        $dariService = $this->svc()->nomorLengkap(
            (array) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)
                ->first(['Jenis_Dokumen_Id', 'Entitas_Pemilik_Id', 'Departemen_Id',
                    'Entitas_Lingkup_Id', 'APQC_Id', 'No_Dokumen'])
            + ['Revisi' => $this->svc()->revisiBerlaku($id)]
        );

        /* 2. Perakit panel detail. */
        $detail = $this->actingAs($admin)->getJson("/api/v1/sop/admin/dokumen/{$hash}/detail");
        $detail->assertOk();
        $dariDetail = $detail->json('result.dokumen.Nomor_Pratinjau');

        /* 3. Perakit kartu daftar. Dicari berdasarkan id, bukan diambil baris
              pertama: urutan daftar tak dijamin memuat dokumen uji di depan. */
        $daftar = $this->actingAs($admin)->getJson('/api/v1/sop/admin/dokumen/current?limit=100');
        $daftar->assertOk();
        $baris = collect($daftar->json('result.rows'))->firstWhere('Id_SOP_Dokumen', $hash);
        $this->assertNotNull($baris, 'Dokumen uji tak ditemukan di halaman pertama daftar.');
        $dariDaftar = $baris['Nomor_Pratinjau'];

        $pratinjauService = $this->svc()->pratinjauNomor(
            (array) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)
                ->first(['Jenis_Dokumen_Id', 'Entitas_Pemilik_Id', 'Departemen_Id',
                    'Entitas_Lingkup_Id', 'APQC_Id', 'No_Dokumen'])
            + ['Revisi' => $this->svc()->revisiBerlaku($id)]
        );

        $this->assertSame($pratinjauService, $dariDetail,
            'Nomor di panel detail berbeda dari yang dirakit service.');
        $this->assertSame($pratinjauService, $dariDaftar,
            'Nomor di kartu daftar berbeda dari yang dirakit service.');

        /* PENJAGA KERJA-NYATA. Ketiganya bisa "sepakat" karena sama-sama
           kosong; kesepakatan itu tak membuktikan apa pun. Nomornya harus
           benar-benar memuat kode pemilik. */
        $this->assertStringContainsString(
            $kodePemilik, (string) $pratinjauService,
            'Nomor tak memuat kode pemilik sama sekali — perakitnya tak memakai kodePemilik().'
        );

        if ($dariService !== null) {
            $this->assertSame($dariService, $pratinjauService);
        }
    }

    public function test_segmen_lingkup_tetap_memakai_kode_entitas(): void
    {
        $ehg = $this->idEntitas('EHG');
        $kodePemilik = DB::table('N_HRIS_Master_Entitas')
            ->where('Id_Entitas', $ehg)->value('Kode_Sebagai_Pemilik');

        if (! $kodePemilik) {
            $this->markTestSkipped('EHG belum punya Kode_Sebagai_Pemilik — jalankan DML DDL-2026-056.');
        }

        $id = $this->buatDokumenHolding();
        $segmen = $this->svc()->segmenNomor(
            (array) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)
                ->first(['Jenis_Dokumen_Id', 'Entitas_Pemilik_Id', 'Departemen_Id',
                    'Entitas_Lingkup_Id', 'APQC_Id', 'No_Dokumen'])
            + ['Revisi' => null]
        );

        /* Dokumen ini pemilik DAN lingkupnya entitas yang sama. Kalau kedua
           segmen tercetak sama, berarti salah satu aturan tak berlaku. */
        $this->assertSame($kodePemilik, $segmen['entitas'],
            'Segmen pemilik harus memakai kode cetak pemilik.');
        $this->assertSame('EHG', $segmen['lingkup'],
            'Segmen ruang lingkup harus tetap memakai Kode_Entitas — di sana EHG berarti "seluruh entitas".');
        $this->assertNotSame($segmen['entitas'], $segmen['lingkup'],
            'Kedua segmen tercetak sama, jadi pemisahan peran tak benar-benar bekerja.');
    }
}
