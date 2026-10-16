<?php

namespace App\Database;

use Illuminate\Database\SqlServerConnection;
use PDO;

/**
 * Mengikat parameter string ASCII sebagai varchar, bukan nvarchar.
 *
 * ── Masalah yang diselesaikan ────────────────────────────────────────────────
 *
 * pdo_sqlsrv mengirim SETIAP string PHP sebagai `nvarchar(4000)`. Basis data ini
 * punya 4.241 kolom varchar/char melawan 756 nvarchar. Ketika parameter nvarchar
 * bertemu kolom varchar, SQL Server harus meng-CONVERT KOLOMNYA — dan kolom yang
 * dibungkus fungsi TIDAK BISA di-seek.
 *
 * Terukur 9 September 2026: 6.655 dari 8.995 rencana ter-cache memuat
 * CONVERT_IMPLICIT. Pada kueri notifikasi topbar, satu-satunya yang sudah
 * diperbaiki per-kueri, bedanya 432 logical reads menjadi 6.
 *
 * ── Kenapa BUKAN sakelar koneksi ─────────────────────────────────────────────
 *
 * PDO::SQLSRV_ATTR_ENCODING => SQLSRV_ENCODING_SYSTEM sudah dicoba dan ditolak:
 * ia mengubah encoding pengiriman DAN pembacaan sekaligus, sehingga data kembali
 * dalam codepage sistem sementara Laravel memperlakukan semuanya UTF-8. Hasilnya
 * 14 uji merah dan halaman menjawab 500 dengan "Malformed UTF-8 characters".
 * Catatannya di config/database.php.
 *
 * `bindParam` menerima encoding PER PARAMETER. Itu menyentuh sisi pengiriman
 * saja, dan jalur pembacaan tetap UTF-8 apa adanya.
 *
 * ── Kenapa hanya nilai ASCII ─────────────────────────────────────────────────
 *
 * SQLSRV_ENCODING_SYSTEM memakai codepage sistem, yang tidak bisa membawa
 * seluruh Unicode. Nilai non-ASCII karena itu tetap dikirim sebagai nvarchar:
 *
 *   - untuk kolom nvarchar itu memang bentuk yang benar;
 *   - untuk kolom varchar, nilai non-ASCII tak akan cocok dengan isi kolomnya
 *     bagaimanapun caranya dikirim, jadi tak ada yang hilang.
 *
 * Yang tersisa — string ASCII, yaitu kode karyawan, flag Y/T, nama kolom
 * enumerasi, nomor transaksi — justru seluruh isi predikat yang panas.
 */
class SqlServerKoneksiAnsi extends SqlServerConnection
{
    /**
     * Nilai yang sedang terikat, ditahan supaya referensinya tetap hidup.
     *
     * bindParam mengikat by reference dan baru membaca isinya saat execute().
     * Variabel lokal di dalam perulangan sudah lenyap pada saat itu, jadi
     * nilainya WAJIB ditahan pada objek yang umurnya melewati execute().
     *
     * @var array<int|string,string>
     */
    private array $nilaiTerikat = [];

    /**
     * @param  \PDOStatement  $statement
     * @param  array  $bindings
     * @return void
     */
    public function bindValues($statement, $bindings)
    {
        $this->nilaiTerikat = [];

        foreach ($bindings as $key => $value) {
            $param = is_string($key) ? $key : $key + 1;

            if ($this->kirimSebagaiAnsi($value)) {
                $this->nilaiTerikat[$param] = $value;

                $statement->bindParam(
                    $param,
                    $this->nilaiTerikat[$param],
                    PDO::PARAM_STR,
                    0,
                    PDO::SQLSRV_ENCODING_SYSTEM,
                );

                continue;
            }

            $statement->bindValue(
                $param,
                $value,
                match (true) {
                    is_int($value) => PDO::PARAM_INT,
                    is_resource($value) => PDO::PARAM_LOB,
                    default => PDO::PARAM_STR,
                },
            );
        }
    }

    /** Hanya string ASCII murni; sisanya dibiarkan pada jalur bawaan. */
    private function kirimSebagaiAnsi(mixed $value): bool
    {
        return is_string($value)
            && $value !== ''
            && defined('PDO::SQLSRV_ENCODING_SYSTEM')
            && mb_check_encoding($value, 'ASCII');
    }
}
