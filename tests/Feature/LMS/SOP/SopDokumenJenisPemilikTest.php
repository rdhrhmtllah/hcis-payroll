<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopJenisDokumenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Penjaga klasifikasi dan kepemilikan pada dokumen SOP.
 *
 * Pembagian tugas yang diuji di sini:
 *   Service  — KESAHIHAN nilai, berlaku bagi siapa pun pemanggilnya.
 *   Controller — KEWAJIBAN mengisi, hanya di pintu manusia.
 *
 * Pembagian itu disengaja. Service juga melayani backfill dan penyalinan paket,
 * yang secara definisi tak punya jawaban untuk kedua field ini; mewajibkannya
 * di sana akan mematikan jalur yang sah demi aturan yang bukan miliknya.
 */
class SopDokumenJenisPemilikTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopDokumenService
    {
        return app(SopDokumenService::class);
    }

    private function jenis(string $kode): int
    {
        $id = DB::table('N_SOP_Master_Jenis_Dokumen')->where('Kode_Jenis', $kode)->value('Id_SOP_Jenis_Dokumen');
        if (! $id) {
            $this->markTestSkipped("Seed DML-2026-017 belum dijalankan — jenis {$kode} tak ada.");
        }

        return (int) $id;
    }

    /** Sepasang divisi + sub-divisi yang benar-benar ada. */
    private function unit(): object
    {
        $row = DB::table('View_Divisi_Sub_Divisi')->whereNotNull('ID_Sub_Divisi')->first();
        if (! $row) {
            $this->markTestSkipped('View_Divisi_Sub_Divisi kosong di environment ini.');
        }

        return $row;
    }

    public function test_dokumen_bisa_lahir_dengan_jenis_dan_pemilik(): void
    {
        $unit = $this->unit();

        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI TATA KELOLA',
            'Tipe' => 'DOKUMEN',
            'Jenis_Dokumen_Id' => $this->jenis('PROSEDUR'),
            'Divisi_Id' => (int) $unit->ID_Divisi,
            'Sub_Divisi_Id' => (int) $unit->ID_Sub_Divisi,
        ], 1);

        $row = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->first();
        $this->assertSame($this->jenis('PROSEDUR'), (int) $row->Jenis_Dokumen_Id);
        $this->assertSame((int) $unit->ID_Divisi, (int) $row->Divisi_Id);
        $this->assertSame((int) $unit->ID_Sub_Divisi, (int) $row->Sub_Divisi_Id);
    }

    public function test_pasangan_jenis_dan_tipe_yang_tak_sah_ditolak(): void
    {
        // FORMULIR hanya menerima tipe FORM. Dokumen bertipe DOKUMEN yang
        // mengaku Formulir akan membuat laporan piramid berbohong.
        $this->expectException(\DomainException::class);

        $this->svc()->buat([
            'Judul' => 'SOP UJI PASANGAN SALAH',
            'Tipe' => 'DOKUMEN',
            'Jenis_Dokumen_Id' => $this->jenis('FORMULIR'),
        ], 1);
    }

    public function test_jenis_nonaktif_ditolak(): void
    {
        $jenisSvc = app(SopJenisDokumenService::class);
        $id = $jenisSvc->create([
            'Kode_Jenis' => 'UJI_NONAKTIF',
            'Nama_Jenis' => 'Nonaktif',
            'Level_Hirarki' => 2,
            'Tipe_Item' => ['DOKUMEN'],
        ], 1);
        $jenisSvc->softDelete($id, 1);

        $this->expectException(\DomainException::class);

        $this->svc()->buat([
            'Judul' => 'SOP UJI JENIS MATI',
            'Tipe' => 'DOKUMEN',
            'Jenis_Dokumen_Id' => $id,
        ], 1);
    }

    public function test_sub_divisi_asing_ditolak(): void
    {
        $unit = $this->unit();
        $asing = DB::table('View_Divisi_Sub_Divisi')
            ->where('ID_Divisi', '<>', $unit->ID_Divisi)
            ->whereNotNull('ID_Sub_Divisi')
            ->value('ID_Sub_Divisi');

        if (! $asing) {
            $this->markTestSkipped('Butuh dua divisi berbeda untuk menguji pasangan asing.');
        }

        $this->expectException(\DomainException::class);

        $this->svc()->buat([
            'Judul' => 'SOP UJI UNIT ASING',
            'Tipe' => 'DOKUMEN',
            'Divisi_Id' => (int) $unit->ID_Divisi,
            'Sub_Divisi_Id' => (int) $asing,
        ], 1);
    }

    public function test_departemen_berdiri_sendiri_dan_divisi_diturunkan(): void
    {
        /* Aturan berubah 22 Agu 2026. Dulu Departemen tanpa Divisi ditolak;
           sekarang Departemen justru satu-satunya yang ditanyakan, dan Divisi
           menjadi kolom TURUNAN yang dihitung server. */
        $sub = DB::table('View_Divisi_Sub_Divisi')
            ->select('ID_Sub_Divisi')
            ->whereNotNull('ID_Sub_Divisi')
            ->groupBy('ID_Sub_Divisi')
            ->havingRaw('COUNT(DISTINCT ID_Divisi) = 1')
            ->first();

        if (! $sub) {
            $this->markTestSkipped('Tak ada departemen berinduk tunggal di environment ini.');
        }

        $divisiHarusnya = DB::table('View_Divisi_Sub_Divisi')
            ->where('ID_Sub_Divisi', $sub->ID_Sub_Divisi)->value('ID_Divisi');

        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI DEPARTEMEN SENDIRI',
            'Tipe' => 'DOKUMEN',
            'Sub_Divisi_Id' => (int) $sub->ID_Sub_Divisi,
        ], 1);

        $row = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->first();
        $this->assertSame((int) $sub->ID_Sub_Divisi, (int) $row->Sub_Divisi_Id);
        $this->assertSame((int) $divisiHarusnya, (int) $row->Divisi_Id,
            'Divisi harus diturunkan dari departemen berinduk tunggal.');
    }

    public function test_departemen_bercabang_tidak_menebak_divisi(): void
    {
        $sub = DB::table('View_Divisi_Sub_Divisi')
            ->select('ID_Sub_Divisi')
            ->groupBy('ID_Sub_Divisi')
            ->havingRaw('COUNT(DISTINCT ID_Divisi) > 1')
            ->first();

        if (! $sub) {
            $this->markTestSkipped('Tak ada departemen bercabang di environment ini.');
        }

        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI DEPARTEMEN BERCABANG',
            'Tipe' => 'DOKUMEN',
            'Sub_Divisi_Id' => (int) $sub->ID_Sub_Divisi,
        ], 1);

        /* Menebak salah satu induk berarti menulis fakta yang tak pernah
           dinyatakan siapa pun, lalu dibaca laporan sebagai kebenaran. */
        $this->assertNull(
            DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->value('Divisi_Id'),
            'Departemen bercabang harus meninggalkan Divisi_Id kosong, bukan menebak.'
        );
    }

    public function test_departemen_tak_dikenal_ditolak(): void
    {
        $this->expectException(\DomainException::class);

        $this->svc()->buat([
            'Judul' => 'SOP UJI DEPARTEMEN ASING',
            'Tipe' => 'DOKUMEN',
            'Sub_Divisi_Id' => 99999999,
        ], 1);
    }

    public function test_jalur_backfill_tetap_boleh_tanpa_jenis_dan_pemilik(): void
    {
        // Ini yang membedakan kesahihan dari kewajiban. Penyalinan paket dan
        // backfill memanggil service tanpa kedua field ini, dan menolaknya di
        // sini akan mematikan jalur yang sah.
        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI TANPA TATA KELOLA',
            'Tipe' => 'DOKUMEN',
        ], 1);

        $row = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->first();
        $this->assertNull($row->Jenis_Dokumen_Id);
        $this->assertNull($row->Divisi_Id);
    }

    public function test_ubah_tanpa_mengirim_field_tata_kelola_tidak_menghapusnya(): void
    {
        $unit = $this->unit();
        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI UBAH SEBAGIAN',
            'Tipe' => 'DOKUMEN',
            'Jenis_Dokumen_Id' => $this->jenis('PROSEDUR'),
            'Divisi_Id' => (int) $unit->ID_Divisi,
        ], 1);

        // Pemanggil lama yang hanya mengubah judul TIDAK boleh menghapus
        // klasifikasi — penghapusan diam adalah kegagalan terburuknya.
        $this->svc()->ubah($id, [
            'Judul' => 'SOP UJI UBAH SEBAGIAN (BARU)',
            'Tipe' => 'DOKUMEN',
        ], 1);

        $row = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->first();
        $this->assertSame('SOP UJI UBAH SEBAGIAN (BARU)', $row->Judul);
        $this->assertSame($this->jenis('PROSEDUR'), (int) $row->Jenis_Dokumen_Id);
        $this->assertSame((int) $unit->ID_Divisi, (int) $row->Divisi_Id);
    }

    public function test_ubah_bisa_mengosongkan_secara_eksplisit(): void
    {
        $unit = $this->unit();
        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI KOSONGKAN',
            'Tipe' => 'DOKUMEN',
            'Jenis_Dokumen_Id' => $this->jenis('PROSEDUR'),
            'Divisi_Id' => (int) $unit->ID_Divisi,
        ], 1);

        $this->svc()->ubah($id, [
            'Judul' => 'SOP UJI KOSONGKAN',
            'Tipe' => 'DOKUMEN',
            'Jenis_Dokumen_Id' => null,
            'Divisi_Id' => null,
            'Sub_Divisi_Id' => null,
        ], 1);

        $row = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->first();
        $this->assertNull($row->Jenis_Dokumen_Id);
        $this->assertNull($row->Divisi_Id);
    }

    public function test_jejak_ubah_menyebut_nama_bukan_id(): void
    {
        $unit = $this->unit();
        $id = $this->svc()->buat(['Judul' => 'SOP UJI JEJAK TATA KELOLA', 'Tipe' => 'DOKUMEN'], 1);

        $this->svc()->ubah($id, [
            'Judul' => 'SOP UJI JEJAK TATA KELOLA',
            'Tipe' => 'DOKUMEN',
            'Jenis_Dokumen_Id' => $this->jenis('PROSEDUR'),
            'Divisi_Id' => (int) $unit->ID_Divisi,
        ], 1);

        $baris = DB::table('N_SOP_Log')
            ->where('Id_SOP_Dokumen', $id)
            ->where('Aksi', 'DOK_UBAH')
            ->orderByDesc('Id_SOP_Log')
            ->first(['Keterangan', 'Meta_JSON']);

        if (! $baris) {
            $this->markTestSkipped('Jejak DOK_UBAH tak terbaca di environment ini.');
        }

        /* Kalimat Keterangan hanya menyebut LABEL kolom yang berubah
           (SopDiff::kalimat); pasangan nilai lama→baru hidup di Meta_JSON.
           Yang diuji di sini adalah nilainya — sebab id telanjang di situlah
           yang akan membuat riwayat tak terbaca setahun kemudian. */
        $this->assertStringContainsString('Jenis dokumen', (string) $baris->Keterangan);
        $this->assertStringContainsString('Unit pemilik', (string) $baris->Keterangan);

        $meta = json_decode((string) $baris->Meta_JSON, true);
        $nilaiBaru = array_column($meta['perubahan'] ?? [], 'baru', 'field');

        $this->assertSame('Prosedur', $nilaiBaru['Jenis dokumen'] ?? null,
            'Jejak audit harus menyebut nama jenis, bukan id.');
        $this->assertNotNull($nilaiBaru['Unit pemilik'] ?? null,
            'Unit pemilik harus tercatat sebagai satu nilai siap baca.');
        $this->assertNotSame((string) $this->jenis('PROSEDUR'), (string) ($nilaiBaru['Jenis dokumen'] ?? ''));
    }
}
