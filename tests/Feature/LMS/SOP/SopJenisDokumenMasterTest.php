<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopJenisDokumenService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Master Jenis Dokumen SOP — CRUD dan aturan pasangan tipe item.
 *
 * Yang diuji di sini bukan "tersimpan atau tidak", melainkan tiga janji yang
 * dilanggar diam-diam kalau tak dijaga: kode jenis tetap unik, jenis yang masih
 * dipakai tak bisa dinonaktifkan, dan daftar tipe sah benar-benar menyaring.
 */
class SopJenisDokumenMasterTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopJenisDokumenService
    {
        return app(SopJenisDokumenService::class);
    }

    public function test_membuat_jenis_beserta_tipe_item_sahnya(): void
    {
        $id = $this->svc()->create([
            'Kode_Jenis' => 'UJI_MANUAL_MUTU',
            'Nama_Jenis' => 'Manual Mutu',
            'Level_Hirarki' => 1,
            'Deskripsi' => 'Puncak piramid untuk pengujian.',
            'Tipe_Item' => ['DOKUMEN', 'VIDEO'],
        ], 1);

        $row = DB::table('N_SOP_Master_Jenis_Dokumen')->where('Id_SOP_Jenis_Dokumen', $id)->first();
        $this->assertSame('UJI_MANUAL_MUTU', $row->Kode_Jenis);
        $this->assertSame(1, (int) $row->Level_Hirarki);
        $this->assertSame(SopStatus::AKTIF, $row->Flag_Aktif);

        $this->assertEqualsCanonicalizing(['DOKUMEN', 'VIDEO'], $this->svc()->tipeSah($id));
    }

    public function test_kode_jenis_tak_boleh_kembar(): void
    {
        $this->svc()->create([
            'Kode_Jenis' => 'UJI_KEMBAR',
            'Nama_Jenis' => 'Pertama',
            'Level_Hirarki' => 2,
        ], 1);

        $this->expectException(\DomainException::class);

        $this->svc()->create([
            'Kode_Jenis' => 'uji_kembar',   // huruf kecil pun tetap kode yang sama
            'Nama_Jenis' => 'Kedua',
            'Level_Hirarki' => 3,
        ], 1);
    }

    public function test_mengubah_daftar_tipe_mencabut_yang_tak_lagi_dikirim(): void
    {
        $id = $this->svc()->create([
            'Kode_Jenis' => 'UJI_GANTI_TIPE',
            'Nama_Jenis' => 'Ganti Tipe',
            'Level_Hirarki' => 3,
            'Tipe_Item' => ['DOKUMEN', 'VIDEO'],
        ], 1);

        $this->svc()->update($id, [
            'Kode_Jenis' => 'UJI_GANTI_TIPE',
            'Nama_Jenis' => 'Ganti Tipe',
            'Level_Hirarki' => 3,
            'Tipe_Item' => ['DOKUMEN'],
        ], 1);

        $this->assertSame(['DOKUMEN'], $this->svc()->tipeSah($id));
        $this->assertFalse($this->svc()->pasanganSah($id, 'VIDEO'));

        // Barisnya DINONAKTIFKAN, bukan dihapus — unique index memakai
        // (Jenis, Tipe) tanpa Flag_Aktif, jadi menghidupkannya lagi harus update.
        $this->assertDatabaseHas('N_SOP_Master_Jenis_Dokumen_Tipe_Item', [
            'Jenis_Dokumen_Id' => $id,
            'Tipe_Item' => 'VIDEO',
            'Flag_Aktif' => SopStatus::INAKTIF,
        ]);
    }

    public function test_menghidupkan_kembali_tipe_yang_pernah_dicabut(): void
    {
        $id = $this->svc()->create([
            'Kode_Jenis' => 'UJI_HIDUP_LAGI',
            'Nama_Jenis' => 'Hidup Lagi',
            'Level_Hirarki' => 3,
            'Tipe_Item' => ['VIDEO'],
        ], 1);

        $this->svc()->setTipeItem($id, [], 1);
        $this->svc()->setTipeItem($id, ['VIDEO'], 1);

        $this->assertSame(['VIDEO'], $this->svc()->tipeSah($id));
        $this->assertSame(
            1,
            DB::table('N_SOP_Master_Jenis_Dokumen_Tipe_Item')
                ->where('Jenis_Dokumen_Id', $id)->where('Tipe_Item', 'VIDEO')->count(),
            'Menghidupkan kembali harus meng-update baris yang ada, bukan menambah baris kedua.'
        );
    }

    public function test_jenis_yang_masih_dipakai_dokumen_tak_bisa_dinonaktifkan(): void
    {
        $id = $this->svc()->create([
            'Kode_Jenis' => 'UJI_TERPAKAI',
            'Nama_Jenis' => 'Terpakai',
            'Level_Hirarki' => 2,
            'Tipe_Item' => ['DOKUMEN'],
        ], 1);

        app(SopDokumenService::class)->buat([
            'Judul' => 'SOP UJI JENIS TERPAKAI',
            'Tipe' => 'DOKUMEN',
            'Jenis_Dokumen_Id' => $id,
        ], 1);

        $this->assertSame(1, $this->svc()->softDelete($id, 1));
        $this->assertSame(
            SopStatus::AKTIF,
            DB::table('N_SOP_Master_Jenis_Dokumen')->where('Id_SOP_Jenis_Dokumen', $id)->value('Flag_Aktif'),
            'Penolakan harus benar-benar membatalkan penonaktifan, bukan sekadar melaporkan angka.'
        );
    }

    public function test_opsi_disaring_oleh_tipe_item(): void
    {
        $seedFormulir = DB::table('N_SOP_Master_Jenis_Dokumen')->where('Kode_Jenis', 'FORMULIR')->first();
        if (! $seedFormulir) {
            $this->markTestSkipped('Seed DML-2026-017 belum dijalankan.');
        }

        $kodeUntukForm = array_column($this->svc()->opsi('FORM'), 'Kode_Jenis');
        $kodeUntukDokumen = array_column($this->svc()->opsi('DOKUMEN'), 'Kode_Jenis');

        $this->assertContains('FORMULIR', $kodeUntukForm);
        $this->assertNotContains('KEBIJAKAN', $kodeUntukForm,
            'Kebijakan bukan formulir — menawarkannya untuk tipe FORM berarti menyodorkan pilihan yang server tolak.');
        $this->assertContains('KEBIJAKAN', $kodeUntukDokumen);
    }

    public function test_kuis_sengaja_tak_menempati_tingkat_mana_pun(): void
    {
        // Kuis adalah alat verifikasi pemahaman, bukan dokumen terkendali.
        // Memberinya tingkat piramid akan mengaburkan arti piramid itu sendiri.
        $this->assertSame([], $this->svc()->opsi('KUIS'));
    }
}
