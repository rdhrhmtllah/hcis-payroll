<?php

namespace Tests\Integration\VISITOR\Guest\Visit;

use App\Services\VISITOR\Guest\Visit\FotoKendaraanTamuService;
use App\Services\VISITOR\Guest\Visit\RegistrasiTamuService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Ekstensi berkas tersimpan diturunkan dari ISI berkas, bukan nama yang dikirim
 * klien: gambar sah bernama `.html` tidak boleh tersimpan dengan ekstensi itu
 * (audit VMS 27 Sep, Low).
 */
class EkstensiUnggahanDariIsiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_gambar_bernama_html_tersimpan_sebagai_png(): void
    {
        $no = DB::table(RegistrasiTamuService::T_DETAIL)->value('No_Transaksi');
        if (! $no) {
            $this->markTestSkipped('Tidak ada transaksi tamu di DB uji.');
        }
        Storage::fake(FotoKendaraanTamuService::DISK);

        // PNG sungguhan bernama .html. UploadedFile::fake() tidak dipakai karena
        // menebak MIME dari nama berkas, bukan isinya.
        $png = tempnam(sys_get_temp_dir(), 'png');
        imagepng(imagecreatetruecolor(2, 2), $png);
        $berkas = new UploadedFile($png, 'foto.html', 'text/html', null, true);

        $path = app(FotoKendaraanTamuService::class)->simpan($no, $berkas);
        @unlink($png);

        $this->assertStringEndsWith('.png', $path);
        Storage::disk(FotoKendaraanTamuService::DISK)->assertExists($path);
    }
}
