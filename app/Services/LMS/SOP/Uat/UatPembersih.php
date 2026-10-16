<?php

namespace App\Services\LMS\SOP\Uat;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Membersihkan baris yang dibuat satu jalan otomasi UAT.
 *
 * RENCANA DIPISAH DARI PELAKSANAAN, dan pemisahan itu bukan selera arsitektur.
 * Perintah yang menghapus data harus bisa diperiksa TANPA menghapus apa pun —
 * kalau satu-satunya cara mengetahui apa yang akan dihapus adalah dengan
 * menjalankannya, tidak ada cara aman untuk mengujinya sama sekali. rencana()
 * murni dan bisa diuji tanpa database; jalankan() hanya melaksanakan rencana
 * yang sudah bisa dibaca mata.
 *
 * Kunci utama dibaca dari INFORMATION_SCHEMA, bukan dari peta yang ditulis
 * tangan. Peta yang salah satu baris saja menghasilkan WHERE pada kolom yang
 * keliru — dan WHERE pada kolom keliru bisa cocok dengan baris milik orang lain.
 * Membacanya dari basis data membuat kesalahan itu mustahil: kalau kuncinya tak
 * bisa ditentukan, pembersih berhenti.
 */
class UatPembersih
{
    /**
     * Susun rencana penghapusan.
     *
     * @param  null|callable(string): (string|array|null)  $kunciUtama  Penentu kunci
     *                                                                  utama; dibiarkan kosong untuk membaca INFORMATION_SCHEMA. Bisa
     *                                                                  diganti pada uji supaya rencananya teruji tanpa database.
     * @return list<array{tabel: string, kunci: string, id: list<int>}>
     */
    public function rencana(UatJurnal $jurnal, ?callable $kunciUtama = null): array
    {
        $kunciUtama ??= fn (string $t) => $this->kunciDariSkema($t);
        $baris = $jurnal->baris();
        $rencana = [];

        foreach (UatTabelIzin::URUTAN_HAPUS as $tabel) {
            $id = array_values(array_unique(array_map('intval', $baris[$tabel] ?? [])));
            $id = array_values(array_filter($id, fn (int $v) => $v > 0));

            /* Tabel tanpa ID dilewati, BUKAN dimasukkan dengan daftar kosong.
               Langkah berisi daftar kosong akan menjadi DELETE tanpa WHERE yang
               efektif — dan itu tepat kerusakan yang seluruh kelas ini ada untuk
               mencegahnya. */
            if ($id === []) {
                continue;
            }

            UatTabelIzin::pastikanDiizinkan($tabel);

            $kunci = $kunciUtama($tabel);

            if (is_array($kunci)) {
                if (count($kunci) !== 1) {
                    throw new RuntimeException(
                        "Tabel '{$tabel}' berkunci utama gabungan (".implode(', ', $kunci).'). '
                        .'WHERE satu kolom akan mencocoki baris yang bukan milik jalan ini, '
                        .'jadi pembersih berhenti alih-alih menebak.'
                    );
                }
                $kunci = $kunci[0];
            }

            if (! is_string($kunci) || $kunci === '') {
                throw new RuntimeException(
                    "Kunci utama tabel '{$tabel}' tidak bisa ditentukan. Tanpa kunci, "
                    .'penghapusan hanya bisa dilakukan dengan menebak kolom — dan tebakan '
                    .'yang keliru menghapus baris milik orang lain.'
                );
            }

            $rencana[] = ['tabel' => $tabel, 'kunci' => $kunci, 'id' => $id];
        }

        return $rencana;
    }

    /**
     * Laksanakan rencana. Mengembalikan jumlah baris terhapus per tabel.
     *
     * @param  list<array{tabel: string, kunci: string, id: list<int>}>  $rencana
     * @return array<string, int>
     */
    public function jalankan(array $rencana, bool $kering = false): array
    {
        $hasil = [];

        foreach ($rencana as $langkah) {
            ['tabel' => $tabel, 'kunci' => $kunci, 'id' => $id] = $langkah;

            UatTabelIzin::pastikanDiizinkan($tabel);
            if ($id === []) {
                throw new RuntimeException("Langkah untuk '{$tabel}' tanpa ID — ditolak.");
            }

            if ($kering) {
                $hasil[$tabel] = count($id);

                continue;
            }

            /* Dipecah supaya tidak menabrak batas parameter SQL Server, dan
               supaya tiap DELETE memegang lebih sedikit kunci baris — server
               ini punya RCSI mati, jadi transaksi panjang membuat pembaca
               antre di belakangnya. */
            $terhapus = 0;
            foreach (array_chunk($id, 200) as $potong) {
                $terhapus += DB::table($tabel)->whereIn($kunci, $potong)->delete();
            }

            $hasil[$tabel] = $terhapus;
        }

        return $hasil;
    }

    /**
     * Kunci utama satu tabel menurut basis data itu sendiri.
     *
     * @return string|array|null
     */
    private function kunciDariSkema(string $tabel)
    {
        $kolom = DB::table('INFORMATION_SCHEMA.KEY_COLUMN_USAGE as k')
            ->join('INFORMATION_SCHEMA.TABLE_CONSTRAINTS as c', function ($j) {
                $j->on('k.CONSTRAINT_NAME', '=', 'c.CONSTRAINT_NAME')
                    ->on('k.TABLE_SCHEMA', '=', 'c.TABLE_SCHEMA');
            })
            ->where('c.CONSTRAINT_TYPE', 'PRIMARY KEY')
            ->where('k.TABLE_NAME', $tabel)
            ->orderBy('k.ORDINAL_POSITION')
            ->pluck('k.COLUMN_NAME')
            ->all();

        return match (count($kolom)) {
            0 => null,
            1 => $kolom[0],
            default => $kolom,
        };
    }
}
