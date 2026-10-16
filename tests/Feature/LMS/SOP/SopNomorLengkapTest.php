<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopDokumenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Nomor dokumen gabungan, dari kolom sampai layar.
 *
 * Format dan aturannya diuji terpisah di SopNomorDokumenTest (unit, tanpa
 * basis data). Yang diuji DI SINI adalah penyambungannya: apakah tiap segmen
 * benar-benar diambil dari master yang tepat, dan apakah nomor itu sampai ke
 * pemanggilnya lewat pintu HTTP.
 */
class SopNomorLengkapTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopDokumenService
    {
        return app(SopDokumenService::class);
    }

    private function idJenis(string $kodeCetak): int
    {
        $id = DB::table('N_SOP_Master_Jenis_Dokumen')
            ->where('Kode_Jenis_Dokumen', $kodeCetak)->value('Id_SOP_Jenis_Dokumen');
        if (! $id) {
            $this->markTestSkipped("Seed DML-2026-031 belum dijalankan — kode {$kodeCetak} tak ada.");
        }

        return (int) $id;
    }

    private function idEntitas(string $kode): int
    {
        $id = DB::table('N_HRIS_Master_Entitas')->where('Kode_Entitas', $kode)->value('Id_Entitas');
        if (! $id) {
            $this->markTestSkipped("Seed entitas {$kode} belum ada.");
        }

        return (int) $id;
    }

    private function idApqc(string $kode): int
    {
        $id = DB::table('N_SOP_Master_APQC')->where('Kode_Proses', $kode)->value('Id_SOP_APQC');
        if (! $id) {
            $this->markTestSkipped("Seed APQC {$kode} belum ada.");
        }

        return (int) $id;
    }

    private function departemen(): object
    {
        $row = DB::table('N_HRIS_Master_Departemen')->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan')->first(['Id_Departemen', 'Kode_Departemen']);
        if (! $row) {
            $this->markTestSkipped('Seed DML-2026-028 belum dijalankan.');
        }

        return $row;
    }

    private function identitasLengkap(array $ubah = []): array
    {
        $dep = $this->departemen();

        return array_merge([
            'Jenis_Dokumen_Id' => $this->idJenis('SOP'),
            'Entitas_Pemilik_Id' => $this->idEntitas('ENB'),
            'Departemen_Id' => (int) $dep->Id_Departemen,
            'Entitas_Lingkup_Id' => $this->idEntitas('EHG'),
            'APQC_Id' => $this->idApqc('09'),
            'No_Dokumen' => 3,
            'Revisi' => 1,
        ], $ubah);
    }

    /* ── Skema & seed ───────────────────────────────────────────────────── */

    public function test_kolom_kode_jenis_dokumen_ada(): void
    {
        $kolom = DB::selectOne(
            'SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['N_SOP_Master_Jenis_Dokumen', 'Kode_Jenis_Dokumen']
        );

        $this->assertNotNull($kolom,
            'Kolom Kode_Jenis_Dokumen belum ada — jalankan DDL-2026-030.');
    }

    /**
     * Setiap jenis dokumen punya kode — itulah yang dijamin DDL-2026-030.
     *
     * DIUJI SEBAGAI INVARIAN, BUKAN SEBAGAI DAFTAR. Versi sebelumnya menuntut
     * larik persis ['PLC','SK','SOP','IK','STD','FRM','MAN'] terurut
     * Level_Hirarki, dan itu patah karena dua hal yang sama-sama BUKAN
     * kerusakan: hierarkinya ditata ulang (Manual naik ke tingkat 2, urutan
     * yang wajar untuk Kebijakan → Manual → SK → Prosedur → IK → Standar →
     * Formulir), dan sebuah jenis percobaan lahir lalu dinonaktifkan.
     *
     * Yang benar-benar berbahaya adalah jenis AKTIF tanpa kode: nomor dokumen
     * dirakit dari kode itu, jadi jenis tanpa kode melahirkan nomor cacat.
     * Itu yang dijaga sekarang, dan penjagaan itu tetap berlaku berapa pun
     * jenis yang ditambahkan kemudian.
     */
    public function test_setiap_jenis_aktif_punya_kode(): void
    {
        $tanpaKode = DB::table('N_SOP_Master_Jenis_Dokumen')
            ->where('Flag_Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('Kode_Jenis_Dokumen')->orWhere('Kode_Jenis_Dokumen', ''))
            ->pluck('Nama_Jenis')->all();

        $this->assertSame([], $tanpaKode,
            'Jenis dokumen aktif tanpa kode akan melahirkan nomor dokumen cacat: '
            .implode(', ', $tanpaKode));

        $aktif = DB::table('N_SOP_Master_Jenis_Dokumen')
            ->where('Flag_Aktif', 'Y')->pluck('Kode_Jenis_Dokumen')->all();

        foreach (['PLC', 'MAN', 'SK', 'SOP', 'IK', 'STD', 'FRM'] as $baku) {
            $this->assertContains($baku, $aktif, "Jenis dokumen baku '{$baku}' hilang dari master.");
        }
    }

    public function test_kode_jenis_dokumen_unik_di_tingkat_basis_data(): void
    {
        /* Dua jenis yang memakai kode sama membuat nomor AMBIGU: tak ada lagi
           cara membaca "SOP/ENB/..." kembali menjadi satu jenis tertentu.
           Filtered index — supaya baris yang kodenya belum diisi tak saling
           menolak, sebab SQL Server menganggap seluruh NULL sama. */
        $idx = DB::selectOne(
            "SELECT is_unique, has_filter FROM sys.indexes
             WHERE object_id = OBJECT_ID('N_SOP_Master_Jenis_Dokumen')
               AND name = 'UX_SOP_Jenis_Kode_Jenis_Dokumen'"
        );

        $this->assertNotNull($idx, 'Indeks UX_SOP_Jenis_Kode_Jenis_Dokumen hilang — jalankan DDL-2026-030.');
        $this->assertSame(1, (int) $idx->is_unique);
        $this->assertSame(1, (int) $idx->has_filter, 'Harus filtered, supaya kode kosong tak saling menolak.');
    }

    public function test_kode_jenis_kembar_ditolak_dengan_pesan_yang_terbaca(): void
    {
        $this->expectException(\DomainException::class);

        app(\App\Services\LMS\SOP\SopJenisDokumenService::class)->create([
            'Kode_Jenis' => 'UJI_KEMBAR_CETAK',
            'Kode_Jenis_Dokumen' => 'SOP',
            'Nama_Jenis' => 'Uji Kembar',
            'Level_Hirarki' => 9,
            'Tipe_Item' => [],
        ], 1);
    }

    /* ── Perakitan dari master ──────────────────────────────────────────── */

    public function test_setiap_segmen_diambil_dari_masternya_sendiri(): void
    {
        $dep = $this->departemen();

        $nomor = $this->svc()->nomorLengkap($this->identitasLengkap());

        $this->assertSame(
            "SOP/ENB/{$dep->Kode_Departemen}/EHG/09/003/01",
            $nomor,
        );
    }

    public function test_jenis_tanpa_kode_cetak_membatalkan_nomor(): void
    {
        /* Jenis boleh lahir tanpa kode cetak — kepanjangan resminya mungkin
           belum ditetapkan. Yang tak boleh adalah nomornya terbit setengah. */
        $id = app(\App\Services\LMS\SOP\SopJenisDokumenService::class)->create([
            'Kode_Jenis' => 'UJI_TANPA_CETAK',
            'Kode_Jenis_Dokumen' => null,
            'Nama_Jenis' => 'Uji Tanpa Kode Cetak',
            'Level_Hirarki' => 9,
            'Tipe_Item' => ['DOKUMEN'],
        ], 1);

        $this->assertNull($this->svc()->nomorLengkap($this->identitasLengkap([
            'Jenis_Dokumen_Id' => $id,
        ])));
        $this->assertStringStartsWith('??/', $this->svc()->pratinjauNomor($this->identitasLengkap([
            'Jenis_Dokumen_Id' => $id,
        ])));
    }

    public function test_revisi_mengikuti_versi_yang_berlaku_bukan_draft(): void
    {
        /* Yang tercetak di dokumen resmi adalah revisi yang SEDANG berlaku.
           Draft yang belum terbit belum berlaku bagi siapa pun. */
        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI REVISI NOMOR',
            'Tipe' => 'DOKUMEN',
        ] + $this->identitasLengkap(), 1);

        $this->assertNull($this->svc()->revisiBerlaku($id),
            'Belum ada versi terbit, jadi belum ada revisi yang berlaku.');

        $this->svc()->tambahVersi($id, ['Ringkasan_Perubahan' => 'Terbit awal'], 1);

        $this->assertNull($this->svc()->revisiBerlaku($id),
            'Versi yang baru ditambahkan masih draft — belum menjadi versi aktif.');
    }

    /* ── Pintu HTTP ─────────────────────────────────────────────────────── */

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

    public function test_pratinjau_nomor_ikut_dikirim_endpoint_saran(): void
    {
        $dep = $this->departemen();
        $enc = fn ($v) => Hashids::connection('custom')->encode($v);

        $res = $this->actingAs($this->adminMaster())->getJson(
            '/api/v1/sop/admin/dokumen/saran-nomor?'.http_build_query([
                'jenis' => $enc($this->idJenis('SOP')),
                'entitas_pemilik' => $enc($this->idEntitas('ENB')),
                'departemen' => $enc((int) $dep->Id_Departemen),
                'entitas_lingkup' => $enc($this->idEntitas('EHG')),
                'apqc' => $enc($this->idApqc('09')),
                'no_dokumen' => 5,
            ]),
        );

        $res->assertStatus(200);
        $this->assertSame(
            "SOP/ENB/{$dep->Kode_Departemen}/EHG/09/005/00",
            $res->json('result.Nomor_Pratinjau'),
            'Nomor yang SEDANG diketik harus dipakai, bukan saran sistem.',
        );
        $this->assertSame([], $res->json('result.Segmen_Kosong'));
    }

    public function test_pratinjau_menyebut_apa_yang_kurang(): void
    {
        $res = $this->actingAs($this->adminMaster())->getJson(
            '/api/v1/sop/admin/dokumen/saran-nomor?'.http_build_query([
                'entitas_pemilik' => Hashids::connection('custom')->encode($this->idEntitas('ENB')),
            ]),
        );

        $res->assertStatus(200);
        $this->assertNull($res->json('result.Nomor_Lengkap'));
        $this->assertContains('Jenis dokumen', $res->json('result.Segmen_Kosong'));
        $this->assertContains('Departemen pemilik', $res->json('result.Segmen_Kosong'));
    }

    public function test_detail_dokumen_membawa_nomor_lengkap(): void
    {
        $dep = $this->departemen();
        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI DETAIL NOMOR',
            'Tipe' => 'DOKUMEN',
        ] + $this->identitasLengkap(['No_Dokumen' => 4]), 1);

        $res = $this->actingAs($this->adminMaster())
            ->getJson('/api/v1/sop/admin/dokumen/'.Hashids::connection('custom')->encode($id).'/detail');

        $res->assertStatus(200);
        /* Belum ada versi berlaku, jadi nomornya memang belum final — yang
           dikirim adalah bentuk berlubang beserta sebabnya. */
        $this->assertNull($res->json('result.dokumen.Nomor_Lengkap'));
        $this->assertSame(
            "SOP/ENB/{$dep->Kode_Departemen}/EHG/09/004/??",
            $res->json('result.dokumen.Nomor_Pratinjau'),
        );
        $this->assertSame(['Revisi'], $res->json('result.dokumen.Nomor_Segmen_Kosong'));
    }

    /* ── Nomor SOP Resmi ADALAH nomor rakitan ───────────────────────────── */

    public function test_nomor_resmi_dirakit_saat_dokumen_lahir(): void
    {
        /* Nomor SOP Resmi bukan nomor kedua di sebelah nomor rakitan — ia
           nomor rakitan itu sendiri, disimpan supaya 45 pembaca kolom ini
           (monitoring, ekspor, repository, dataset kepatuhan) ikut mendapatnya. */
        $dep = $this->departemen();
        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI NOMOR RESMI LAHIR',
            'Tipe' => 'DOKUMEN',
        ] + $this->identitasLengkap(['No_Dokumen' => 2]), 1);

        $this->svc()->tambahVersi($id, ['Ringkasan_Perubahan' => 'Terbit awal'], 1);

        /* Belum ada versi BERLAKU, jadi segmen Revisi masih kosong dan nomor
           belum bisa dirakit utuh. */
        $this->assertNull(
            DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->value('Nomor_SOP_Resmi'),
        );
    }

    public function test_nomor_resmi_tidak_pernah_mengosongkan_penomoran_lama(): void
    {
        /* 16 dokumen lama menyimpan penomoran yang diketik tangan dan masih
           beredar di luar sistem. Menimpanya dengan null berarti menghapus
           satu-satunya jejak nomor itu. */
        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI NOMOR LAMA',
            'Tipe' => 'DOKUMEN',
            'Nomor_SOP_Resmi' => 'SOP-LEGACY-001',
        ], 1);

        $this->svc()->segarkanNomorResmi($id, 1);

        $this->assertSame(
            'SOP-LEGACY-001',
            DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->value('Nomor_SOP_Resmi'),
            'Identitas belum lengkap — nilai lama harus dibiarkan apa adanya.',
        );
    }

    public function test_nomor_resmi_ditulis_begitu_identitasnya_lengkap(): void
    {
        $dep = $this->departemen();
        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI NOMOR RESMI LENGKAP',
            'Tipe' => 'DOKUMEN',
        ] + $this->identitasLengkap(['No_Dokumen' => 6]), 1);

        /* Segmen Revisi dipenuhi dengan menunjuk versi aktif secara langsung —
           jalur terbit penuh sudah diuji berkas lain. */
        $idVersi = $this->svc()->tambahVersi($id, ['Ringkasan_Perubahan' => 'Terbit awal'], 1);
        DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->update(['Id_Versi_Aktif' => $idVersi]);

        $nomor = $this->svc()->segarkanNomorResmi($id, 1);

        $this->assertSame("SOP/ENB/{$dep->Kode_Departemen}/EHG/09/006/00", $nomor);
        $this->assertSame(
            $nomor,
            DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->value('Nomor_SOP_Resmi'),
            'Nomor rakitan harus TERSIMPAN, bukan hanya dihitung saat ditampilkan.',
        );
    }

    public function test_nomor_resmi_ikut_disebarkan_ke_item_perujuk(): void
    {
        /* Kolom item adalah cerminan dengan satu penulis. Tanpa penyebaran,
           daftar paket dan monitoring menyebut nomor yang sudah tak dipakai. */
        $dep = $this->departemen();
        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI SEBAR NOMOR',
            'Tipe' => 'DOKUMEN',
        ] + $this->identitasLengkap(['No_Dokumen' => 8]), 1);

        $idVersi = $this->svc()->tambahVersi($id, ['Ringkasan_Perubahan' => 'Terbit awal'], 1);
        DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->update(['Id_Versi_Aktif' => $idVersi]);

        $item = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Dokumen', $id)->exists();
        $nomor = $this->svc()->segarkanNomorResmi($id, 1);

        $this->assertNotNull($nomor);
        if ($item) {
            $this->assertSame($nomor, DB::table('N_SOP_Paket_Item')
                ->where('Id_SOP_Dokumen', $id)->value('Nomor_SOP_Resmi'));
        }
    }

    public function test_mengubah_identitas_merakit_ulang_nomor_resminya(): void
    {
        $dep = $this->departemen();
        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI RAKIT ULANG',
            'Tipe' => 'DOKUMEN',
        ] + $this->identitasLengkap(['No_Dokumen' => 1]), 1);

        $idVersi = $this->svc()->tambahVersi($id, ['Ringkasan_Perubahan' => 'Terbit awal'], 1);
        DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->update(['Id_Versi_Aktif' => $idVersi]);
        $this->svc()->segarkanNomorResmi($id, 1);

        // Pindah jenis: segmen pertama harus ikut berubah.
        $this->svc()->ubah($id, [
            'Judul' => 'SOP UJI RAKIT ULANG',
            'Tipe' => 'DOKUMEN',
            'Jenis_Dokumen_Id' => $this->idJenis('IK'),
        ], 1);

        $this->assertStringStartsWith('IK/',
            (string) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->value('Nomor_SOP_Resmi'),
            'Nomor resmi harus mengikuti identitas terbarunya.');
    }
}
