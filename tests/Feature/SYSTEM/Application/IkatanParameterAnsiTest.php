<?php

namespace Tests\Feature\SYSTEM\Application;

use App\Database\SqlServerKoneksiAnsi;
use Illuminate\Database\SqlServerConnection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Ikatan parameter ANSI: hanya ASCII, dan MATI secara baku.
 *
 * ── Apa yang dijaga ──────────────────────────────────────────────────────────
 *
 * SqlServerKoneksiAnsi mengirim parameter string ASCII sebagai varchar alih-alih
 * nvarchar, supaya SQL Server tidak perlu meng-CONVERT kolom varchar — konversi
 * yang membuat indeks tak bisa di-seek. Terverifikasi di tingkat wire: deklarasi
 * parameternya berubah dari `@P1 nvarchar(4000)` jadi `@P1 varchar(8000)`.
 *
 * Dua sifat yang menentukan aman-tidaknya, dan keduanya dijaga di sini:
 *
 *  1. Nilai NON-ASCII tidak boleh ikut jalur itu. SQLSRV_ENCODING_SYSTEM
 *     memakai codepage sistem yang tak bisa membawa seluruh Unicode; nilai
 *     non-ASCII karena itu wajib tetap nvarchar. Kalau penjagaan ini lepas,
 *     kerusakannya DIAM — nama ber-aksen tersimpan salah, tak ada galat.
 *
 *  2. Baku MATI. Ini mengubah cara setiap parameter sampai ke basis data;
 *     lingkungan yang belum membuktikannya dengan suite penuh tidak boleh
 *     mendapatkannya diam-diam.
 *
 * ── Yang TIDAK dijanjikan ────────────────────────────────────────────────────
 *
 * Bukan "74% kueri jadi cepat". Konversi implisit hanya merugikan bila ada seek
 * indeks yang terhalang olehnya. Terukur: kueri notifikasi topbar 432 logical
 * reads -> 6, sementara kueri HRIS_Page_Access tetap 37 -> 37 karena di sana tak
 * ada seek yang sedang dihalangi.
 */
class IkatanParameterAnsiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_baku_mati_kecuali_dinyalakan(): void
    {
        /* Suite ini berjalan tanpa DB_SQLSRV_ANSI_BIND, jadi koneksinya harus
           yang bawaan. Kalau ia berubah tanpa sakelar, sebuah lingkungan bisa
           mendapatkan perubahan perilaku basis data tanpa pernah memilihnya. */
        $this->assertInstanceOf(
            SqlServerConnection::class,
            DB::connection(),
            'Koneksi bawaan berubah tanpa sakelar DB_SQLSRV_ANSI_BIND.',
        );

        if (! env('DB_SQLSRV_ANSI_BIND', false)) {
            $this->assertNotInstanceOf(
                SqlServerKoneksiAnsi::class,
                DB::connection(),
                'DB_SQLSRV_ANSI_BIND mati, tetapi koneksi ANSI tetap terpasang.',
            );
        }
    }

    /**
     * @dataProvider nilaiUji
     */
    public function test_hanya_ascii_yang_dikirim_sebagai_ansi(mixed $nilai, bool $harusAnsi, string $sebab): void
    {
        $koneksi = new SqlServerKoneksiAnsi(DB::connection()->getPdo());

        $metode = new ReflectionMethod($koneksi, 'kirimSebagaiAnsi');
        $metode->setAccessible(true);

        $this->assertSame($harusAnsi, $metode->invoke($koneksi, $nilai), $sebab);
    }

    /** @return array<string,array{0:mixed,1:bool,2:string}> */
    public static function nilaiUji(): array
    {
        return [
            'kode karyawan' => ['A1', true, 'Kode karyawan ASCII — justru isi predikat yang panas.'],
            'flag' => ['Y', true, 'Flag Y/T ASCII.'],
            'nomor transaksi' => ['PMT-2026-000123', true, 'Nomor transaksi ASCII.'],
            'aksen' => ['Café', false, 'Aksen di luar ASCII — codepage sistem bisa merusaknya.'],
            'non-latin' => ['日本語', false, 'Aksara non-Latin wajib tetap nvarchar.'],
            'emoji' => ['✅', false, 'Emoji wajib tetap nvarchar.'],
            'kosong' => ['', false, 'String kosong dibiarkan pada jalur bawaan.'],
            'bilangan' => [42, false, 'Bukan string — tak ada urusannya dengan encoding.'],
            'null' => [null, false, 'Null dibiarkan pada jalur bawaan.'],
        ];
    }

    /** Teks non-ASCII harus pulang-pergi UTUH lewat koneksi ANSI. */
    public function test_teks_non_ascii_tetap_utuh_lewat_koneksi_ansi(): void
    {
        $koneksi = new SqlServerKoneksiAnsi(
            DB::connection()->getPdo(),
            DB::connection()->getDatabaseName(),
            DB::connection()->getTablePrefix(),
            DB::connection()->getConfig(),
        );

        foreach (['ASCII biasa', 'Café Ámsterdam', '日本語テスト', 'Emoji ✅🚀'] as $teks) {
            $kembali = $koneksi->selectOne('SELECT CAST(? AS nvarchar(100)) AS v', [$teks])->v;

            $this->assertSame(
                $teks,
                $kembali,
                "Teks berubah saat pulang-pergi: \"{$teks}\" menjadi \"{$kembali}\". ".
                'Kerusakan seperti ini tidak melempar galat — ia hanya menyimpan yang salah.',
            );
        }
    }
}
