<?php

namespace Tests\Feature\SYSTEM\MasterData;

use App\Services\SYSTEM\MasterData\Entitas\EntitasService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Master Entitas Perusahaan — badan hukum dalam grup.
 *
 * Berisi gerbang skema DDL-2026-019 sekaligus perilaku service-nya. DDL modul
 * ini dijalankan manual (tak ada Laravel migration), jadi tes skema di sini
 * adalah SATU-SATUNYA bukti otomatis bahwa tabelnya benar-benar ada di basis
 * data yang sedang dipakai.
 */
class EntitasTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): EntitasService
    {
        return app(EntitasService::class);
    }

    private function kolom(string $tabel): array
    {
        return array_map(
            fn ($r) => $r->COLUMN_NAME,
            DB::select('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?', [$tabel])
        );
    }

    public function test_tabel_entitas_ada_dengan_kolom_yang_dibutuhkan(): void
    {
        $kolom = $this->kolom('N_HRIS_Master_Entitas');
        $this->assertNotEmpty(
            $kolom,
            'Tabel N_HRIS_Master_Entitas belum ada — jalankan '
            .'database/ddl/DDL-2026-019-hris-master-entitas/forward.sql'
        );

        foreach ([
            'Id_Entitas', 'Kode_Entitas', 'Nama_Entitas', 'Nama_Singkat', 'Warna',
            'Induk_Entitas_Id', 'Urutan', 'Flag_Aktif',
            'Created_At', 'Created_By', 'Updated_At', 'Updated_By',
        ] as $wajib) {
            $this->assertContains($wajib, $kolom, "Kolom {$wajib} hilang di N_HRIS_Master_Entitas.");
        }
    }

    public function test_dua_kolom_entitas_ditambahkan_ke_dokumen(): void
    {
        $kolom = $this->kolom('N_SOP_Dokumen');

        foreach (['Entitas_Pemilik_Id', 'Entitas_Lingkup_Id'] as $wajib) {
            $this->assertContains($wajib, $kolom, "N_SOP_Dokumen.{$wajib} belum ada — jalankan DDL-2026-019.");
        }
    }

    public function test_kolom_entitas_dokumen_nullable(): void
    {
        // 41 dokumen lahir sebelum kolom ini ada; NOT NULL akan menolak setiap
        // tulisan atas baris lama, termasuk yang tak menyentuh entitas.
        foreach (['Entitas_Pemilik_Id', 'Entitas_Lingkup_Id'] as $k) {
            $nullable = DB::selectOne(
                'SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?',
                ['N_SOP_Dokumen', $k]
            );
            $this->assertSame('YES', $nullable->IS_NULLABLE, "N_SOP_Dokumen.{$k} harus nullable.");
        }
    }

    public function test_empat_entitas_ter_seed_dengan_payung_di_atas(): void
    {
        $rows = DB::table('N_HRIS_Master_Entitas')->pluck('Induk_Entitas_Id', 'Kode_Entitas')->all();

        foreach (['ENB', 'EMI', 'GMN', 'EHG'] as $kode) {
            $this->assertArrayHasKey(
                $kode,
                $rows,
                "Entitas {$kode} belum ter-seed — jalankan database/dml/DML-2026-020-seed-hris-entitas/data-change.sql"
            );
        }

        $idEhg = DB::table('N_HRIS_Master_Entitas')->where('Kode_Entitas', 'EHG')->value('Id_Entitas');

        $this->assertNull($rows['EHG'], 'EHG adalah payung, jadi ia tak boleh punya induk.');
        foreach (['ENB', 'EMI', 'GMN'] as $kode) {
            $this->assertSame((int) $idEhg, (int) $rows[$kode], "{$kode} harus bernaung di bawah EHG.");
        }
    }

    public function test_cakupan_payung_dihitung_dari_kolom_induk(): void
    {
        /* Inilah alasan hirarkinya disimpan sebagai kolom, bukan dihafal di
           kode: menambah PT baru cukup satu insert, dan cakupannya ikut benar
           tanpa satu baris kode pun berubah. */
        $idEhg = (int) DB::table('N_HRIS_Master_Entitas')->where('Kode_Entitas', 'EHG')->value('Id_Entitas');
        $idEnb = (int) DB::table('N_HRIS_Master_Entitas')->where('Kode_Entitas', 'ENB')->value('Id_Entitas');

        $cakupanEhg = $this->svc()->cakupan($idEhg);
        $this->assertContains($idEnb, $cakupanEhg, 'ENB harus tercakup EHG.');
        $this->assertGreaterThanOrEqual(4, count($cakupanEhg));

        $this->assertSame([$idEnb], $this->svc()->cakupan($idEnb),
            'Entitas biasa hanya mencakup dirinya sendiri.');
    }

    public function test_opsi_menandai_entitas_payung(): void
    {
        $opsi = collect($this->svc()->opsi())->keyBy('Kode_Entitas');

        $this->assertTrue($opsi['EHG']['Cakup_Semua'], 'EHG harus ditandai mencakup entitas lain.');
        $this->assertFalse($opsi['ENB']['Cakup_Semua']);
        $this->assertNotEmpty($opsi['ENB']['Warna'], 'Warna merek disimpan, bukan dihitung dari nama.');
    }

    public function test_entitas_nonaktif_tak_muncul_dan_tak_dianggap_ada(): void
    {
        $id = DB::table('N_HRIS_Master_Entitas')->insertGetId([
            'Kode_Entitas' => 'UJIOFF',
            'Nama_Entitas' => 'PT Uji Nonaktif',
            'Urutan' => 99,
            'Flag_Aktif' => 'T',
            'Created_At' => now(),
        ], 'Id_Entitas');

        $this->assertFalse($this->svc()->ada($id));
        $this->assertNotContains('UJIOFF', array_column($this->svc()->opsi(), 'Kode_Entitas'));
    }

    /* ── Pengelolaan (halaman SYSTEM) ─────────────────────────────────── */

    public function test_membuat_entitas_baru_di_bawah_payung(): void
    {
        $idEhg = (int) DB::table('N_HRIS_Master_Entitas')->where('Kode_Entitas', 'EHG')->value('Id_Entitas');

        $id = $this->svc()->create([
            'Kode_Entitas' => 'ujibaru',
            'Nama_Entitas' => 'PT Uji Baru',
            'Nama_Singkat' => 'Uji Baru',
            'Warna' => '#123456',
            'Induk_Entitas_Id' => $idEhg,
            'Urutan' => 9,
        ], 1);

        $row = DB::table('N_HRIS_Master_Entitas')->where('Id_Entitas', $id)->first();
        $this->assertSame('UJIBARU', $row->Kode_Entitas, 'Kode selalu disimpan huruf besar.');
        $this->assertSame($idEhg, (int) $row->Induk_Entitas_Id);

        // Cakupan payung ikut bertambah tanpa satu baris kode pun berubah —
        // itulah alasan hirarkinya disimpan sebagai kolom.
        $this->assertContains($id, $this->svc()->cakupan($idEhg));
    }

    public function test_kode_terkunci_saat_diubah(): void
    {
        $id = $this->svc()->create([
            'Kode_Entitas' => 'UJIKUNCI',
            'Nama_Entitas' => 'PT Uji Kunci',
        ], 1);

        $this->svc()->update($id, [
            'Kode_Entitas' => 'BERUBAH',
            'Nama_Entitas' => 'PT Uji Kunci Baru',
        ], 1);

        $row = DB::table('N_HRIS_Master_Entitas')->where('Id_Entitas', $id)->first();
        $this->assertSame('UJIKUNCI', $row->Kode_Entitas,
            'Kode adalah rujukan tetap; membiarkannya berpindah berarti membiarkan rujukan menunjuk entitas lain tanpa jejak.');
        $this->assertSame('PT Uji Kunci Baru', $row->Nama_Entitas, 'Nama tetap boleh diubah.');
    }

    public function test_entitas_tak_boleh_jadi_induk_dirinya_sendiri(): void
    {
        $id = $this->svc()->create(['Kode_Entitas' => 'UJIDIRI', 'Nama_Entitas' => 'PT Uji Diri'], 1);

        $this->expectException(\DomainException::class);

        $this->svc()->update($id, [
            'Kode_Entitas' => 'UJIDIRI',
            'Nama_Entitas' => 'PT Uji Diri',
            'Induk_Entitas_Id' => $id,
        ], 1);
    }

    public function test_lingkaran_induk_ditolak(): void
    {
        /* Tanpa penjaga ini cakupan() akan berputar dan permintaannya
           menggantung sampai timeout — kegagalan yang muncul jauh dari tempat
           kesalahannya dibuat. */
        $atas = $this->svc()->create(['Kode_Entitas' => 'UJIATAS', 'Nama_Entitas' => 'PT Uji Atas'], 1);
        $bawah = $this->svc()->create([
            'Kode_Entitas' => 'UJIBAWAH',
            'Nama_Entitas' => 'PT Uji Bawah',
            'Induk_Entitas_Id' => $atas,
        ], 1);

        $this->expectException(\DomainException::class);

        // Memasang anak sebagai induk dari induknya menutup lingkaran.
        $this->svc()->update($atas, [
            'Kode_Entitas' => 'UJIATAS',
            'Nama_Entitas' => 'PT Uji Atas',
            'Induk_Entitas_Id' => $bawah,
        ], 1);
    }

    public function test_entitas_yang_masih_dipakai_dokumen_tak_bisa_dinonaktifkan(): void
    {
        $id = $this->svc()->create(['Kode_Entitas' => 'UJIPAKAI', 'Nama_Entitas' => 'PT Uji Pakai'], 1);

        app(\App\Services\LMS\SOP\SopDokumenService::class)->buat([
            'Judul' => 'SOP UJI ENTITAS TERPAKAI',
            'Tipe' => 'DOKUMEN',
            'Entitas_Pemilik_Id' => $id,
        ], 1);

        $this->assertSame(1, $this->svc()->softDelete($id, 1));
        $this->assertSame('Y',
            DB::table('N_HRIS_Master_Entitas')->where('Id_Entitas', $id)->value('Flag_Aktif'),
            'Penolakan harus benar-benar membatalkan penonaktifan, bukan sekadar melaporkan angka.');
    }

    public function test_payung_yang_masih_punya_anak_tak_bisa_dinonaktifkan(): void
    {
        /* Menonaktifkan payung yang masih punya anak membuat anaknya menunjuk
           induk yang tak muncul di mana pun, dan cakupan()-nya menyusut diam-diam. */
        $atas = $this->svc()->create(['Kode_Entitas' => 'UJIPAYUNG', 'Nama_Entitas' => 'PT Uji Payung'], 1);
        $this->svc()->create([
            'Kode_Entitas' => 'UJIANAK',
            'Nama_Entitas' => 'PT Uji Anak',
            'Induk_Entitas_Id' => $atas,
        ], 1);

        $this->assertSame(1, $this->svc()->softDelete($atas, 1));
        $this->assertSame('Y', DB::table('N_HRIS_Master_Entitas')->where('Id_Entitas', $atas)->value('Flag_Aktif'));
    }

    public function test_entitas_bebas_bisa_dinonaktifkan(): void
    {
        $id = $this->svc()->create(['Kode_Entitas' => 'UJIBEBAS', 'Nama_Entitas' => 'PT Uji Bebas'], 1);

        $this->assertSame(0, $this->svc()->softDelete($id, 1));
        $this->assertSame('T', DB::table('N_HRIS_Master_Entitas')->where('Id_Entitas', $id)->value('Flag_Aktif'));
    }

    /* ── Logo dan struktur grup ───────────────────────────────────────── */

    public function test_kolom_logo_ada_dan_nullable(): void
    {
        $this->assertContains('Logo_Path', $this->kolom('N_HRIS_Master_Entitas'),
            'Kolom Logo_Path belum ada — jalankan database/ddl/DDL-2026-022-hris-entitas-logo/forward.sql');

        $nullable = DB::selectOne(
            'SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['N_HRIS_Master_Entitas', 'Logo_Path']
        );
        $this->assertSame('YES', $nullable->IS_NULLABLE);
    }

    public function test_logo_url_meneruskan_rujukan_yang_sudah_berupa_url(): void
    {
        /* Kolomnya sengaja menampung DUA bentuk. Yang sudah berupa URL atau path
           publik diteruskan apa adanya — itulah yang membuat entitas tetap bisa
           berlogo di environment yang kredensial GCS-nya belum siap. */
        $this->assertSame('https://contoh.test/logo.png',
            EntitasService::logoUrl('https://contoh.test/logo.png'));
        $this->assertSame('/logo/enb.png', EntitasService::logoUrl('/logo/enb.png'));
        $this->assertNull(EntitasService::logoUrl(null));
        $this->assertNull(EntitasService::logoUrl(''));
    }

    public function test_logo_path_gcs_tak_menjatuhkan_halaman_saat_kredensial_gagal(): void
    {
        /* Menandatangani path GCS bisa gagal di environment tertentu. Yang diuji
           di sini bukan keberhasilannya, melainkan bahwa kegagalannya TIDAK
           dilempar: satu logo yang tak bisa ditampilkan tak boleh menjatuhkan
           seluruh halaman master. */
        $hasil = EntitasService::logoUrl('system/entitas-logo/2026/08/enb-'.uniqid().'.png');

        $this->assertTrue($hasil === null || is_string($hasil));
    }

    public function test_logo_ikut_tersimpan_dan_terbaca(): void
    {
        $id = $this->svc()->create([
            'Kode_Entitas' => 'UJILOGO',
            'Nama_Entitas' => 'PT Uji Logo',
            'Logo_Path' => 'https://contoh.test/uji.png',
        ], 1);

        $this->assertSame('https://contoh.test/uji.png',
            DB::table('N_HRIS_Master_Entitas')->where('Id_Entitas', $id)->value('Logo_Path'));

        $opsi = collect($this->svc()->opsi())->firstWhere('Kode_Entitas', 'UJILOGO');
        $this->assertSame('https://contoh.test/uji.png', $opsi['Logo_Url']);
    }

    public function test_pohon_menyusun_hirarki_dari_kolom_induk(): void
    {
        $pohon = $this->svc()->pohon();

        // Seed punya satu payung (EHG) dengan tiga anak, jadi akarnya tunggal
        // dan bukan bungkus semu.
        $this->assertSame('EHG', $pohon['title'] ?? null, 'Akar pohon harus EHG.');

        $kodeAnak = array_column($pohon['children'] ?? [], 'title');
        foreach (['ENB', 'EMI', 'GMN'] as $kode) {
            $this->assertContains($kode, $kodeAnak, "{$kode} harus muncul sebagai anak EHG.");
        }
    }

    public function test_pohon_membungkus_saat_akarnya_lebih_dari_satu(): void
    {
        // Entitas tanpa induk adalah akar kedua; komponen pohon tetap butuh satu
        // pangkal, jadi keduanya dibungkus simpul semu bernama 'root'.
        $this->svc()->create(['Kode_Entitas' => 'UJIAKAR', 'Nama_Entitas' => 'PT Uji Akar'], 1);

        $pohon = $this->svc()->pohon();

        $this->assertSame('root', $pohon['id'] ?? null);
        $this->assertGreaterThanOrEqual(2, count($pohon['children'] ?? []));
    }

    public function test_create_menolak_kode_kembar(): void
    {
        $this->expectException(\DomainException::class);

        $this->svc()->create(['Kode_Entitas' => 'enb', 'Nama_Entitas' => 'PT Kembar'], 1);
    }

    public function test_kode_entitas_tak_boleh_kembar(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('N_HRIS_Master_Entitas')->insert([
            'Kode_Entitas' => 'ENB',
            'Nama_Entitas' => 'PT Kembar',
            'Urutan' => 98,
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
        ]);
    }
}
