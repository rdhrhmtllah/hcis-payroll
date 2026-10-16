<?php

namespace Tests\Unit\VISITOR\Permit;

use App\Services\VISITOR\Permit\VehiclePermit\PlatNomorKendaraan;
use PHPUnit\Framework\TestCase;

/**
 * Satu kendaraan, satu bentuk tersimpan — dari pintu mana pun ia masuk.
 *
 * Modul Visitor punya EMPAT pintu penulisan plat: layar tamu, layar permit,
 * API tamu B2B, API permit. Ketika masing-masing menafsirkan plat dengan
 * aturannya sendiri, kendaraan yang sama tercatat dalam dua bentuk berbeda,
 * lalu pencarian plat di meja verifikator meleset atas kendaraan yang jelas
 * ada — kegagalan yang tidak menyalakan galat apa pun dan hanya terlihat
 * sebagai "kok tidak ketemu".
 *
 * Penjaga di berkas ini menagih tiga hal yang pernah benar-benar salah, bukan
 * sekadar memeriksa bahwa fungsinya mengembalikan sesuatu.
 */
class PlatNomorKendaraanTest extends TestCase
{
    private PlatNomorKendaraan $plat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plat = new PlatNomorKendaraan;
    }

    /**
     * Bentuk lama mengembalikan tiga null ketika polanya tak cocok, sehingga
     * "B 12345 XYZ" dari mitra tersimpan sebagai kendaraan TANPA plat: tanpa
     * galat, tanpa jejak, dan tak mungkin ditelusuri belakangan.
     *
     * @dataProvider platTakKanonis
     */
    public function test_plat_tak_kanonis_tidak_pernah_hilang_senyap(string $masukan): void
    {
        $hasil = $this->plat->normalkan($masukan);

        $this->assertNotNull(
            $hasil['prefix'],
            "Plat '{$masukan}' hilang senyap: seluruh bagiannya null.",
        );
        $this->assertFalse(
            $hasil['kanonis'],
            "Plat '{$masukan}' seharusnya ditandai tak kanonis.",
        );
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function platTakKanonis(): array
    {
        return [
            'lima digit' => ['B 12345 XYZ'],
            'suffix empat huruf' => ['B 1234 ABCD'],
            'salah ketik pemakai' => ['B 1234 MT / 2'],
        ];
    }

    /**
     * Salinan terpisah di VehiclePermitController memecah atas spasi saja,
     * sehingga "bg1234mt" menjadi prefix delapan karakter di satu pintu dan
     * [BG|1234|MT] di pintu lain.
     */
    public function test_plat_rapat_dan_plat_berspasi_menghasilkan_bentuk_yang_sama(): void
    {
        $rapat = $this->plat->normalkan('bg1234mt');
        $berspasi = $this->plat->normalkan('BG 1234 MT');

        $this->assertSame($berspasi, $rapat, 'Dua penulisan plat yang sama menghasilkan dua baris berbeda.');
        $this->assertSame('BG', $rapat['prefix']);
        $this->assertSame('1234', $rapat['angka']);
        $this->assertSame('MT', $rapat['suffix']);
    }

    /**
     * Jalur web tamu memeriksa prefix dalam huruf besar ke tabel master, lalu
     * MENYIMPAN masukan aslinya — "b" lolos pemeriksaan lalu tersimpan sebagai
     * "b" dan tak pernah cocok lagi dengan masternya. Suffix bahkan tak pernah
     * dibesarkan hurufnya sama sekali.
     */
    public function test_seluruh_bagian_dibesarkan_hurufnya_bukan_sebagian(): void
    {
        $hasil = $this->plat->normalkan(null, 'b', '1234', 'mt');

        $this->assertSame('B', $hasil['prefix']);
        $this->assertSame('MT', $hasil['suffix']);
    }

    /**
     * Layar permit mengirim tiga bagian DAN string utuh. Urutan yang berlaku
     * sejak dulu adalah tiga bagian menang; membaliknya membuat formulir itu
     * diam-diam menyimpan kendaraan yang lain.
     */
    public function test_tiga_bagian_terpisah_menang_atas_string_utuh(): void
    {
        $hasil = $this->plat->normalkan('ZZ 9999 ZZ', 'B', '1234', 'MT');

        $this->assertSame('B', $hasil['prefix'], 'String utuh menimpa bagian terpisah.');
        $this->assertSame('MT', $hasil['suffix']);
    }

    /**
     * muat() harus benar-benar MENOLAK, bukan sekadar ada.
     *
     * Kolom tamu varchar(5) dan kolom permit varchar(10); tanpa penjaga ini
     * yang sampai ke pemakainya adalah galat truncate SQL Server yang tidak
     * menyebut field mana pun.
     *
     * @dataProvider bagianKepanjangan
     */
    public function test_bagian_yang_tak_muat_kolomnya_tertangkap(string $masukan, int $lebar, string $bagian): void
    {
        $hasil = $this->plat->normalkan($masukan);

        $this->assertSame(
            $bagian,
            $this->plat->muat($hasil, $lebar),
            "Bagian '{$bagian}' dari '{$masukan}' lolos penjaga lebar {$lebar}.",
        );
    }

    /**
     * @return array<string, array{0:string,1:int,2:string}>
     */
    public static function bagianKepanjangan(): array
    {
        return [
            'angka 6 char di kolom tamu' => ['B 123456 XYZ', PlatNomorKendaraan::LEBAR_TAMU, 'angka'],
            'prefix 7 char di kolom tamu' => ['BGXYZAB 1234 MT', PlatNomorKendaraan::LEBAR_TAMU, 'prefix'],
            'suffix 11 char di kolom permit' => ['B 1234 ABCDEFGHIJK', PlatNomorKendaraan::LEBAR_PERMIT, 'suffix'],
        ];
    }

    /**
     * Sisi PENERIMAAN penjaga. Tanpa ini, muat() yang selalu mengembalikan
     * nama bagian akan tetap membuat seluruh uji di atas hijau sambil menolak
     * setiap plat yang sah.
     */
    public function test_plat_kanonis_muat_di_kedua_kolom(): void
    {
        $hasil = $this->plat->normalkan('BG 1234 MT');

        $this->assertNull($this->plat->muat($hasil, PlatNomorKendaraan::LEBAR_TAMU));
        $this->assertNull($this->plat->muat($hasil, PlatNomorKendaraan::LEBAR_PERMIT));
    }

    /**
     * Plat memang opsional: kendaraan boleh tidak ada sama sekali.
     */
    public function test_masukan_kosong_menghasilkan_plat_kosong_yang_sah(): void
    {
        $hasil = $this->plat->normalkan('');

        $this->assertNull($hasil['prefix']);
        $this->assertNull($this->plat->muat($hasil, PlatNomorKendaraan::LEBAR_TAMU));
    }

    /**
     * pecah() masih dipakai PermitTamuApiController; bentuk kembaliannya tidak
     * boleh ikut berubah hanya karena normalkan() menambah kunci baru.
     */
    public function test_pecah_tetap_mengembalikan_tiga_kunci_saja(): void
    {
        $this->assertSame(
            ['prefix', 'angka', 'suffix'],
            array_keys($this->plat->pecah('BG 1234 MT')),
        );
    }

    /**
     * Batas validasi diturunkan dari lebar kolom, bukan angka yang disalin.
     * Inilah yang mencegah kembalinya `max:10` di atas kolom varchar(5).
     */
    public function test_aturan_validasi_mengikuti_lebar_kolom(): void
    {
        $tamu = PlatNomorKendaraan::aturanValidasi(PlatNomorKendaraan::LEBAR_TAMU);

        $this->assertContains('max:5', $tamu['plat_angka']);
        $this->assertContains('max:5', $tamu['plat_suffix']);

        $permit = PlatNomorKendaraan::aturanValidasi(PlatNomorKendaraan::LEBAR_PERMIT);

        $this->assertContains('max:10', $permit['plat_angka']);
    }
}
