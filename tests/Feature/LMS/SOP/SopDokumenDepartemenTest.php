<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopDokumenService;
use App\Services\SYSTEM\MasterData\Departemen\DepartemenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Kepemilikan dokumen lewat MASTER KODE DEPARTEMEN (DDL-2026-027).
 *
 * Sejak perubahan ini, `Departemen_Id` adalah acuan kepemilikan; `Sub_Divisi_Id`
 * dan `Divisi_Id` menjadi TURUNAN dari binding departemen, bukan isian sendiri.
 * Turunannya tetap ditulis karena jalur pelaporan yang menyambung karyawan lewat
 * ID_Divisi_Sub_Divisi masih membutuhkannya.
 */
class SopDokumenDepartemenTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopDokumenService
    {
        return app(SopDokumenService::class);
    }

    private function dep(): DepartemenService
    {
        return app(DepartemenService::class);
    }

    /** Departemen yang punya binding ke sub-divisi; dilewati bila tak ada. */
    private function departemenTerikat(): object
    {
        $row = DB::table('N_HRIS_Master_Departemen')
            ->whereNotNull('Sub_Divisi_Id')->where('Flag_Aktif', 'Y')
            ->first(['Id_Departemen', 'Sub_Divisi_Id', 'Kode_Departemen']);

        if (! $row) {
            $this->markTestSkipped('Belum ada departemen yang terikat sub-divisi — jalankan DML-2026-028.');
        }

        return $row;
    }

    private function departemenLepas(): int
    {
        return $this->dep()->create([
            'Kode_Departemen' => 'UJILPS'.substr((string) microtime(true), -3),
            'Nama_Departemen' => 'Departemen Tanpa Binding',
            'Sub_Divisi_Id' => null,
            'Urutan' => 900,
        ], 1);
    }

    private function baris(int $id): object
    {
        return DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)
            ->first(['Departemen_Id', 'Sub_Divisi_Id', 'Divisi_Id', 'No_Dokumen']);
    }

    public function test_sub_divisi_diturunkan_dari_binding_departemen(): void
    {
        $dep = $this->departemenTerikat();

        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI TURUNAN DEPARTEMEN',
            'Tipe' => 'DOKUMEN',
            'Departemen_Id' => (int) $dep->Id_Departemen,
        ], 1);

        $baris = $this->baris($id);
        $this->assertSame((int) $dep->Id_Departemen, (int) $baris->Departemen_Id);
        $this->assertSame((int) $dep->Sub_Divisi_Id, (int) $baris->Sub_Divisi_Id,
            'Sub-divisi adalah cerminan binding departemen, bukan isian terpisah.');
    }

    public function test_departemen_tanpa_binding_meninggalkan_sub_divisi_kosong(): void
    {
        /* Bukan kelalaian: 35 dari 52 kode memang tak punya baris sendiri di
           struktur HRIS. Mengisinya dengan tebakan akan menulis fakta yang tak
           pernah dinyatakan siapa pun, lalu dibaca laporan sebagai kebenaran. */
        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI DEPARTEMEN LEPAS',
            'Tipe' => 'DOKUMEN',
            'Departemen_Id' => $this->departemenLepas(),
        ], 1);

        $baris = $this->baris($id);
        $this->assertNull($baris->Sub_Divisi_Id);
        $this->assertNull($baris->Divisi_Id);
    }

    public function test_departemen_yang_tak_dikenal_ditolak(): void
    {
        $this->expectException(\DomainException::class);

        $this->svc()->buat([
            'Judul' => 'SOP UJI DEPARTEMEN ASING',
            'Tipe' => 'DOKUMEN',
            'Departemen_Id' => 999999999,
        ], 1);
    }

    public function test_departemen_nonaktif_ditolak(): void
    {
        $mati = $this->departemenLepas();
        $this->dep()->softDelete($mati, 1);

        $this->expectException(\DomainException::class);

        $this->svc()->buat([
            'Judul' => 'SOP UJI DEPARTEMEN MATI',
            'Tipe' => 'DOKUMEN',
            'Departemen_Id' => $mati,
        ], 1);
    }

    public function test_memindahkan_departemen_ikut_memindahkan_turunannya(): void
    {
        /* Membiarkan sub-divisi lama bertahan setelah departemennya berganti
           menyimpan pasangan yang tak pernah ada di struktur. */
        $terikat = $this->departemenTerikat();
        $lepas = $this->departemenLepas();

        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI PINDAH DEPARTEMEN',
            'Tipe' => 'DOKUMEN',
            'Departemen_Id' => (int) $terikat->Id_Departemen,
        ], 1);
        $this->assertNotNull($this->baris($id)->Sub_Divisi_Id);

        $this->svc()->ubah($id, [
            'Judul' => 'SOP UJI PINDAH DEPARTEMEN',
            'Tipe' => 'DOKUMEN',
            'Departemen_Id' => $lepas,
        ], 1);

        $baris = $this->baris($id);
        $this->assertSame($lepas, (int) $baris->Departemen_Id);
        $this->assertNull($baris->Sub_Divisi_Id, 'Turunan lama tidak boleh tertinggal.');
        $this->assertNull($baris->Divisi_Id);
    }

    public function test_departemen_ikut_menentukan_deret_nomor_dokumen(): void
    {
        /* Lingkup deret berpindah dari Sub_Divisi_Id ke Departemen_Id bersama
           DDL-2026-027. Kalau tidak, arti "kombinasi yang sama" berubah diam-diam
           begitu pemiliknya pindah acuan — dua departemen berbeda akan berbagi
           satu deret hanya karena keduanya tak punya binding.

           DERETNYA MULAI DARI SATU, bukan nol. Uji ini dulu menuntut nol dan
           itulah yang keliru, bukan kodenya: saranNoDokumen() menjelaskannya
           panjang lebar — nomor urut dokumen menjawab "dokumen keberapa dalam
           kombinasi ini", dan tidak ada dokumen ke-nol. Segmen REVISI di nomor
           resmi memang mulai dari nol, tapi ia menghitung hal lain (berapa kali
           dokumennya sudah direvisi); dua segmen bersebelahan dengan dua aturan
           berbeda, dan menyamakannya "supaya konsisten" justru merusak arti
           salah satunya. */
        $a = $this->departemenLepas();
        $b = $this->departemenLepas();

        $this->svc()->buat(['Judul' => 'SOP UJI DERET DEP A', 'Tipe' => 'DOKUMEN', 'Departemen_Id' => $a], 1);
        $id = $this->svc()->buat(['Judul' => 'SOP UJI DERET DEP B', 'Tipe' => 'DOKUMEN', 'Departemen_Id' => $b], 1);

        $this->assertSame(1, (int) $this->baris($id)->No_Dokumen,
            'Departemen berbeda berarti kombinasi berbeda, jadi deretnya mulai dari awal '
            .'lagi — dan awalnya SATU, bukan nol.');
    }

    public function test_deret_naik_di_departemen_yang_sama(): void
    {
        $dep = $this->departemenLepas();

        $pertama = $this->svc()->buat(['Judul' => 'SOP UJI DERET SAMA A', 'Tipe' => 'DOKUMEN', 'Departemen_Id' => $dep], 1);
        $id = $this->svc()->buat(['Judul' => 'SOP UJI DERET SAMA B', 'Tipe' => 'DOKUMEN', 'Departemen_Id' => $dep], 1);

        /* Kedua angka disebut, bukan yang kedua saja: yang dijaga adalah LANGKAH
           deretnya (satu per dokumen), dan angka kedua sendirian tak
           membedakan "mulai dari 1 lalu +1" dari "mulai dari 2". */
        $this->assertSame(1, (int) $this->baris($pertama)->No_Dokumen,
            'Dokumen pertama sebuah kombinasi bernomor 1 — tidak ada dokumen ke-nol.');
        $this->assertSame(2, (int) $this->baris($id)->No_Dokumen,
            'Dokumen berikutnya di kombinasi yang sama naik satu langkah.');
    }

    public function test_jejak_audit_menyebut_kode_departemen_bukan_id(): void
    {
        /* Id mentah di jejak audit tak menjawab pertanyaan siapa pun; kodenya
           yang dikenali orang, dan itu pula yang tercetak di nomor dokumen. */
        $terikat = $this->departemenTerikat();
        $lepas = $this->departemenLepas();

        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI JEJAK DEPARTEMEN',
            'Tipe' => 'DOKUMEN',
            'Departemen_Id' => (int) $terikat->Id_Departemen,
        ], 1);

        $this->svc()->ubah($id, [
            'Judul' => 'SOP UJI JEJAK DEPARTEMEN',
            'Tipe' => 'DOKUMEN',
            'Departemen_Id' => $lepas,
        ], 1);

        $meta = DB::table('N_SOP_Log')
            ->where('Id_SOP_Dokumen', $id)
            ->orderByDesc('Id_SOP_Log')
            ->value('Meta_JSON');

        $this->assertNotNull($meta, 'Perubahan kepemilikan harus meninggalkan jejak.');
        $this->assertStringContainsString($terikat->Kode_Departemen, (string) $meta,
            'Jejaknya menyebut kode departemen lama.');
    }

    /* ── Pintu manusia ──────────────────────────────────────────────────── */

    /** Admin yang benar-benar memegang kunci Master Dokumen. */
    private function adminMaster(): User
    {
        $uid = DB::table('HRIS_Page_Access')
            ->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'menuSopDokumenPage')
            ->whereNotNull('UserID_Web')
            ->value('UserID_Web');

        $u = $uid ? User::find($uid) : null;
        if (! $u) {
            $this->markTestSkipped('Tak ada user dengan menuSopDokumenPage.');
        }

        return $u;
    }

    /** Isian identitas lengkap dan sah untuk pintu Master. */
    private function identitas(array $ubah = []): array
    {
        $jenis = app(\App\Services\LMS\SOP\SopJenisDokumenService::class)->opsi('DOKUMEN');
        $apqc = app(\App\Services\LMS\SOP\SopApqcService::class)->opsi('DOKUMEN');
        $entitas = app(\App\Services\SYSTEM\MasterData\Entitas\EntitasService::class)->opsi();
        $dep = $this->dep()->opsi();

        if ($entitas === [] || $dep === []) {
            $this->markTestSkipped('Seed entitas atau departemen belum dijalankan.');
        }

        $out = [
            'Judul' => 'SOP UJI PINTU DEPARTEMEN',
            'Tipe' => 'DOKUMEN',
            'Departemen_Id' => $dep[0]['Id'],
            'Entitas_Pemilik_Id' => $entitas[0]['Id'],
            'Entitas_Lingkup_Id' => $entitas[0]['Id'],
        ];
        if ($jenis !== []) {
            $out['Jenis_Dokumen_Id'] = $jenis[0]['Id'];
        }
        if ($apqc !== []) {
            $out['APQC_Id'] = $apqc[0]['Id'];
        }

        return array_merge($out, $ubah);
    }

    public function test_pintu_master_menolak_dokumen_tanpa_departemen(): void
    {
        /* Kewajiban mengisi hidup di pintu manusia, bukan di service: service
           juga melayani backfill dan penyalinan paket yang tak punya jawaban
           untuk field ini. */
        $data = $this->identitas();
        unset($data['Departemen_Id']);

        $this->actingAs($this->adminMaster())
            ->postJson('/api/v1/sop/admin/dokumen/store', $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['Departemen_Id']);
    }

    public function test_pintu_master_menolak_departemen_yang_tak_terbaca(): void
    {
        $this->actingAs($this->adminMaster())
            ->postJson('/api/v1/sop/admin/dokumen/store',
                $this->identitas(['Departemen_Id' => 'bukan-hashid']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['Departemen_Id']);
    }

    public function test_pintu_master_menolak_sub_divisi_kiriman_klien(): void
    {
        /* Sub-divisi adalah kolom TURUNAN. Menerimanya dari klien membuka dua
           penulis untuk satu nilai, dan dua penulis pasti menyimpang. */
        $terikat = $this->departemenTerikat();
        $lepas = $this->departemenLepas();

        $res = $this->actingAs($this->adminMaster())->postJson(
            '/api/v1/sop/admin/dokumen/store',
            $this->identitas([
                'Departemen_Id' => Hashids::connection('custom')->encode($lepas),
                'Sub_Divisi_Id' => (int) $terikat->Sub_Divisi_Id,
            ]),
        );

        $res->assertStatus(201);
        $id = Hashids::connection('custom')->decode($res->json('result.Id_SOP_Dokumen'))[0];

        $this->assertNull($this->baris($id)->Sub_Divisi_Id,
            'Sub-divisi kiriman klien harus diabaikan; yang berlaku adalah binding departemennya.');
    }

    public function test_pintu_master_menyimpan_departemen_dan_turunannya(): void
    {
        $terikat = $this->departemenTerikat();

        $res = $this->actingAs($this->adminMaster())->postJson(
            '/api/v1/sop/admin/dokumen/store',
            $this->identitas([
                'Departemen_Id' => Hashids::connection('custom')->encode((int) $terikat->Id_Departemen),
            ]),
        );

        $res->assertStatus(201);
        $id = Hashids::connection('custom')->decode($res->json('result.Id_SOP_Dokumen'))[0];

        $baris = $this->baris($id);
        $this->assertSame((int) $terikat->Id_Departemen, (int) $baris->Departemen_Id);
        $this->assertSame((int) $terikat->Sub_Divisi_Id, (int) $baris->Sub_Divisi_Id);
    }
}
