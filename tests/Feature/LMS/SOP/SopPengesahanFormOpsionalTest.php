<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopTahapVersiService;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Formulir juga disahkan — tetapi opsional (keputusan user 5 Okt 2026).
 *
 * Sampai hari ini blok pengesahan hanya berlaku untuk DOKUMEN. FORM kini ikut
 * menerimanya, tetapi tingkatnya BERLAKU, bukan WAJIB: blok tampil dan boleh
 * diisi, tanpa menahan langkah, terbit, atau memunculkan lencana merah.
 *
 * Yang dijaga di sini adalah pemisahan dua pertanyaan yang dulu dijawab satu
 * fungsi: "boleh diisi?" (blokBerlaku) dan "harus diisi?" (blokWajib). Selama
 * keduanya sama, menjadikan pengesahan FORM terlihat akan diam-diam
 * membuatnya wajib di empat tempat sekaligus.
 */
class SopPengesahanFormOpsionalTest extends TestCase
{
    use DatabaseTransactions;

    private function versiForm(): int
    {
        $svc = app(SopDokumenService::class);
        $idDok = $svc->buat(['Judul' => 'UJI PENGESAHAN FORM '.substr((string) microtime(true), -5), 'Tipe' => 'FORM'], 1);

        return (int) $svc->tambahVersi($idDok, ['Ringkasan_Perubahan' => 'Awal'], 1);
    }

    private function panggil(object $obj, string $metode, ...$arg)
    {
        $m = new \ReflectionMethod($obj, $metode);
        $m->setAccessible(true);

        return $m->invoke($obj, ...$arg);
    }

    public function test_profil_form_pengesahan_berlaku_tapi_tidak_wajib(): void
    {
        $this->assertTrue(SopTipeItem::blokBerlaku(SopTipeItem::FORM, SopTipeItem::BLOK_PENGESAHAN));
        $this->assertFalse(SopTipeItem::blokWajib(SopTipeItem::FORM, SopTipeItem::BLOK_PENGESAHAN));
        $this->assertTrue(SopTipeItem::blokWajib(SopTipeItem::DOKUMEN, SopTipeItem::BLOK_PENGESAHAN));
    }

    public function test_form_menerima_blok_pengesah(): void
    {
        $v = $this->versiForm();

        $this->assertNull(app(SopDokumenService::class)->ubahVersi($v, [
            'Pengesah' => [['Peran' => 'Disahkan oleh', 'Nama' => 'Pengesah Uji', 'Jabatan' => 'Manager']],
        ], 1));

        $this->assertSame(1, DB::table('N_SOP_Item_Versi_Pengesah')
            ->where('Id_SOP_Item_Versi', $v)->where('Jenis', 'REALISASI')->count());
    }

    public function test_form_tanpa_pengesah_tidak_ditahan(): void
    {
        $v = $this->versiForm();
        $tahap = app(SopTahapVersiService::class);

        $this->assertTrue($this->panggil($tahap, 'nilaiSyarat', $v)['REALISASI_PENGESAH'],
            'Syarat pengesah menahan perpindahan tahap FORM padahal pengesahannya opsional.');

        $langkah = collect($tahap->langkahVersi($v))->firstWhere('Kelompok', 'PENGESAHAN');
        $this->assertTrue($langkah['Lengkap'], 'Langkah Pengesahan FORM tanpa pengesah ditandai belum lengkap.');

        // Gerbang terbit: tak melempar untuk FORM tanpa pengesah.
        $this->panggil(app(SopDokumenService::class), 'pastikanAdaPengesah', $v);
    }
}
