<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopDokumenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contoh pengisian FORM wajib PDF — ditagih di gerbang terbit (10 Okt 2026).
 *
 * Hanya PDF yang bisa ditahan "hanya lihat": pembaca aman tak bisa menampilkan
 * .xlsx/.docx, jadi contoh non-PDF selalu lolos sebagai unduhan dan izin paket
 * tak berarti apa-apa. Jenis konten Library tak cukup untuk menolaknya —
 * 'document' mencakup Word dan Excel juga.
 *
 * Gerbangnya pastikanIsiSah(), dipakai kedua pintu terbit (Master Dokumen dan
 * publish paket). "Kembalikan versi" memakainya juga, tetapi aturan ini TIDAK
 * berlaku surut: versi lama pernah sah berlaku.
 */
class SopFormContohPdfTerbitTest extends TestCase
{
    use DatabaseTransactions;

    private function konten(bool $pdf): int
    {
        $q = DB::table('N_LMS_Master_Konten as k')
            ->join('N_LMS_Master_Konten_Berkas as b', 'b.Id_Master_Konten', '=', 'k.Id_Master_Konten')
            ->where('k.Tipe_Konten', 'document');

        $pdf
            ? $q->where(fn ($w) => $w->where('b.Type_File', 'like', '%pdf%')->orWhere('b.File_Path', 'like', '%.pdf'))
            : $q->where('b.Type_File', 'not like', '%pdf%')->where('b.File_Path', 'not like', '%.pdf');

        $id = $q->orderBy('k.Id_Master_Konten')->value('k.Id_Master_Konten');

        if (! $id) {
            $this->markTestSkipped('Library Konten tak punya berkas document '.($pdf ? 'PDF' : 'non-PDF').'.');
        }

        return (int) $id;
    }

    private function versiForm(int $idKonten): int
    {
        $svc = app(SopDokumenService::class);
        $idDok = $svc->buat(['Judul' => 'UJI CONTOH PDF '.substr((string) microtime(true), -5), 'Tipe' => 'FORM'], 1);
        $idVersi = (int) $svc->tambahVersi($idDok, ['Ringkasan_Perubahan' => 'Awal'], 1);

        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->update(['Id_Master_Konten' => $idKonten]);

        return $idVersi;
    }

    private function gerbang(int $idVersi, bool $terbitBaru = true): void
    {
        $m = new \ReflectionMethod(SopDokumenService::class, 'pastikanIsiSah');
        $m->setAccessible(true);
        $m->invoke(app(SopDokumenService::class), $idVersi, $terbitBaru);
    }

    public function test_contoh_pengisian_non_pdf_ditolak_saat_terbit(): void
    {
        $v = $this->versiForm($this->konten(pdf: false));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('wajib berupa PDF');

        $this->gerbang($v);
    }

    public function test_contoh_pengisian_pdf_lolos(): void
    {
        $v = $this->versiForm($this->konten(pdf: true));

        $this->gerbang($v);

        // Sampai sini tanpa pengecualian = lolos. Asersi eksplisit supaya uji
        // ini tak dilaporkan "risky" dan tak bisa hijau karena dilewati.
        $this->assertTrue(true);
    }

    public function test_kembalikan_versi_lama_non_pdf_tidak_ditagih_surut(): void
    {
        $v = $this->versiForm($this->konten(pdf: false));

        $this->gerbang($v, terbitBaru: false);

        $this->assertTrue(true);
    }

    public function test_dokumen_non_pdf_tidak_ikut_ditolak(): void
    {
        // Aturan ini milik FORM. DOKUMEN tetap diatur kebijakannya sendiri.
        $svc = app(SopDokumenService::class);
        $idDok = $svc->buat(['Judul' => 'UJI DOKUMEN NON PDF '.substr((string) microtime(true), -5), 'Tipe' => 'DOKUMEN'], 1);
        $v = (int) $svc->tambahVersi($idDok, ['Ringkasan_Perubahan' => 'Awal'], 1);
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $v)
            ->update(['Id_Master_Konten' => $this->konten(pdf: false)]);

        try {
            $this->gerbang($v);
        } catch (\DomainException $e) {
            // DOKUMEN tanpa pengesah ditolak oleh aturan lain — yang penting bukan aturan PDF.
            $this->assertStringNotContainsString('wajib berupa PDF', $e->getMessage());

            return;
        }

        $this->assertTrue(true);
    }
}
