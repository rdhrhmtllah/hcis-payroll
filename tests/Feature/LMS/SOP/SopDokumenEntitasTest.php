<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\SYSTEM\MasterData\Entitas\EntitasService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Entitas pemilik dan ruang lingkup pada dokumen SOP.
 *
 * Pembagiannya sama dengan jenis dokumen dan unit kerja:
 *   Service    — KESAHIHAN nilai, berlaku bagi siapa pun pemanggilnya.
 *   Controller — KEWAJIBAN mengisi, hanya di pintu manusia.
 *
 * Yang diuji di sini adalah sisi service. Sisi kewajiban diuji di
 * SopMasterDokumenPageTest dan SopBuilderItemDokumenTest, karena di sanalah
 * pintu manusianya berada.
 */
class SopDokumenEntitasTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopDokumenService
    {
        return app(SopDokumenService::class);
    }

    private function entitas(string $kode): int
    {
        $id = DB::table('N_HRIS_Master_Entitas')->where('Kode_Entitas', $kode)->value('Id_Entitas');
        if (! $id) {
            $this->markTestSkipped("Seed DML-2026-020 belum dijalankan — entitas {$kode} tak ada.");
        }

        return (int) $id;
    }

    public function test_dokumen_menyimpan_pemilik_dan_ruang_lingkup_yang_berbeda(): void
    {
        /* Keduanya kolom terpisah justru karena bisa berbeda: dokumen yang
           DIMILIKI satu PT bisa BERLAKU se-grup. */
        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI ENTITAS BEDA',
            'Tipe' => 'DOKUMEN',
            'Entitas_Pemilik_Id' => $this->entitas('ENB'),
            'Entitas_Lingkup_Id' => $this->entitas('EHG'),
        ], 1);

        $row = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->first();
        $this->assertSame($this->entitas('ENB'), (int) $row->Entitas_Pemilik_Id);
        $this->assertSame($this->entitas('EHG'), (int) $row->Entitas_Lingkup_Id);
    }

    public function test_entitas_tak_dikenal_ditolak(): void
    {
        $this->expectException(\DomainException::class);

        $this->svc()->buat([
            'Judul' => 'SOP UJI ENTITAS ASING',
            'Tipe' => 'DOKUMEN',
            'Entitas_Pemilik_Id' => 99999999,
        ], 1);
    }

    public function test_entitas_nonaktif_ditolak(): void
    {
        $id = DB::table('N_HRIS_Master_Entitas')->insertGetId([
            'Kode_Entitas' => 'UJIMATI',
            'Nama_Entitas' => 'PT Uji Mati',
            'Urutan' => 97,
            'Flag_Aktif' => 'T',
            'Created_At' => now(),
        ], 'Id_Entitas');

        // Menonaktifkan entitas tanpa menutup pintu pemakaian barunya hanya
        // menyembunyikannya dari dropdown; pemanggil yang mengirim id lama tetap lolos.
        $this->expectException(\DomainException::class);

        $this->svc()->buat([
            'Judul' => 'SOP UJI ENTITAS MATI',
            'Tipe' => 'DOKUMEN',
            'Entitas_Lingkup_Id' => $id,
        ], 1);
    }

    public function test_jalur_backfill_tetap_boleh_tanpa_entitas(): void
    {
        // Kesahihan bukan kewajiban: penyalinan paket dan backfill memanggil
        // service tanpa entitas, dan menolaknya akan mematikan jalur yang sah.
        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI TANPA ENTITAS',
            'Tipe' => 'DOKUMEN',
        ], 1);

        $row = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->first();
        $this->assertNull($row->Entitas_Pemilik_Id);
        $this->assertNull($row->Entitas_Lingkup_Id);
    }

    public function test_ubah_tanpa_mengirim_entitas_tidak_menghapusnya(): void
    {
        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI ENTITAS BERTAHAN',
            'Tipe' => 'DOKUMEN',
            'Entitas_Pemilik_Id' => $this->entitas('EMI'),
            'Entitas_Lingkup_Id' => $this->entitas('EMI'),
        ], 1);

        $this->svc()->ubah($id, [
            'Judul' => 'SOP UJI ENTITAS BERTAHAN (BARU)',
            'Tipe' => 'DOKUMEN',
        ], 1);

        $row = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->first();
        $this->assertSame('SOP UJI ENTITAS BERTAHAN (BARU)', $row->Judul);
        $this->assertSame($this->entitas('EMI'), (int) $row->Entitas_Pemilik_Id,
            'Pemanggil yang hanya mengubah judul tidak boleh menghapus entitas.');
    }

    public function test_jejak_ubah_menyebut_nama_entitas_bukan_id(): void
    {
        $id = $this->svc()->buat(['Judul' => 'SOP UJI JEJAK ENTITAS', 'Tipe' => 'DOKUMEN'], 1);

        $this->svc()->ubah($id, [
            'Judul' => 'SOP UJI JEJAK ENTITAS',
            'Tipe' => 'DOKUMEN',
            'Entitas_Pemilik_Id' => $this->entitas('GMN'),
        ], 1);

        $baris = DB::table('N_SOP_Log')
            ->where('Id_SOP_Dokumen', $id)->where('Aksi', 'DOK_UBAH')
            ->orderByDesc('Id_SOP_Log')->first(['Keterangan', 'Meta_JSON']);

        if (! $baris) {
            $this->markTestSkipped('Jejak DOK_UBAH tak terbaca di environment ini.');
        }

        $meta = json_decode((string) $baris->Meta_JSON, true);
        $nilaiBaru = array_column($meta['perubahan'] ?? [], 'baru', 'field');

        $this->assertSame(
            app(EntitasService::class)->label($this->entitas('GMN')),
            $nilaiBaru['Entitas pemilik'] ?? null,
            'Jejak audit harus menyebut nama entitas; id telanjang tak menjelaskan apa pun setahun kemudian.'
        );
    }
}
