<?php

namespace App\Services\SYSTEM\Application\Database;

use InvalidArgumentException;

/**
 * Membandingkan inventaris tabel dan kolom dua basis data.
 *
 * Dipakai untuk menjawab satu pertanyaan yang selama ini hanya bisa diduga-duga:
 * apakah `Web_HRIS_Cut_Off` (staging) masih sama dengan `Web_HRIS` (development)?
 *
 * Cara menjaga keduanya tetap sama BUKAN dengan menjalankan setiap DDL dua kali —
 * itu memberi tiap perubahan dua kesempatan untuk terlupa, dan lupanya diam.
 * Yang dipakai adalah restore ulang satu arah, sehingga skema staging mengikuti
 * development tanpa satu pun DDL dijalankan dua kali. Kelas ini menutup celah yang
 * tersisa: jeda antara dua refresh, di situlah drift hidup.
 *
 * Logikanya sengaja murni — tidak menyentuh basis data sama sekali — supaya bisa
 * diuji tanpa koneksi. Yang mengambil datanya adalah perintah `db:banding-skema`.
 */
class PembandingSkema
{
    /**
     * Nama basis data ikut dirangkai ke dalam SQL (SQL Server tidak menerima
     * parameter untuk nama objek), jadi bentuknya WAJIB diperiksa lebih dulu.
     * Tanpa ini, satu nilai dari argumen perintah bisa menjadi SQL apa pun.
     */
    public static function pastikanNamaDatabaseAman(string $nama): string
    {
        if (preg_match('/^[A-Za-z0-9_]{1,128}$/', $nama) !== 1) {
            throw new InvalidArgumentException(
                "Nama basis data tidak sah: '{$nama}'. Hanya huruf, angka, dan garis bawah yang diterima."
            );
        }

        return $nama;
    }

    /**
     * Kueri inventaris kolom untuk satu basis data.
     *
     * Hanya skema `dbo` dan tabel buatan sendiri: tabel bawaan SQL Server berbeda
     * antar-instance karena alasan yang tak ada hubungannya dengan aplikasi ini,
     * dan memasukkannya membuat laporan berisik lalu diabaikan orang.
     */
    public static function kueriInventaris(string $database): string
    {
        $db = self::pastikanNamaDatabaseAman($database);

        return "SELECT t.name AS tabel, c.name AS kolom, ty.name AS tipe,
                       c.max_length AS panjang, c.precision AS presisi, c.scale AS skala,
                       c.is_nullable AS nullable
                FROM [{$db}].sys.columns c
                JOIN [{$db}].sys.tables t ON t.object_id = c.object_id
                JOIN [{$db}].sys.schemas s ON s.schema_id = t.schema_id
                JOIN [{$db}].sys.types ty ON ty.user_type_id = c.user_type_id
                WHERE s.name = 'dbo' AND t.is_ms_shipped = 0
                ORDER BY t.name, c.column_id";
    }

    /**
     * @param  list<array<string, mixed>>  $dari  inventaris basis data acuan (development)
     * @param  list<array<string, mixed>>  $ke  inventaris basis data yang diperiksa (staging)
     * @return array{
     *     sama: bool,
     *     tabel_hilang: list<string>,
     *     tabel_asing: list<string>,
     *     kolom_hilang: list<string>,
     *     kolom_asing: list<string>,
     *     kolom_beda: list<array{kolom: string, dari: string, ke: string}>
     * }
     */
    public function banding(array $dari, array $ke): array
    {
        $a = $this->kelompokkan($dari);
        $b = $this->kelompokkan($ke);

        $tabelHilang = array_values(array_diff(array_keys($a), array_keys($b)));
        $tabelAsing = array_values(array_diff(array_keys($b), array_keys($a)));

        sort($tabelHilang);
        sort($tabelAsing);

        $kolomHilang = [];
        $kolomAsing = [];
        $kolomBeda = [];

        /*
         * Tabel yang hilang seluruhnya TIDAK diurai per kolom. Satu rilis fitur baru
         * bisa membawa puluhan tabel; melaporkan tiap kolomnya menenggelamkan
         * perbedaan yang justru halus dan berbahaya — kolom yang diam-diam berubah
         * tipe — di bawah ratusan baris yang sudah terwakili satu nama tabel.
         */
        foreach ($a as $tabel => $kolomA) {
            if (! isset($b[$tabel])) {
                continue;
            }

            $kolomB = $b[$tabel];

            foreach ($kolomA as $nama => $sifatA) {
                if (! isset($kolomB[$nama])) {
                    $kolomHilang[] = "{$tabel}.{$nama}";

                    continue;
                }

                $sifatB = $kolomB[$nama];

                if ($sifatA !== $sifatB) {
                    $kolomBeda[] = [
                        'kolom' => "{$tabel}.{$nama}",
                        'dari' => $this->rangkumSifat($sifatA),
                        'ke' => $this->rangkumSifat($sifatB),
                    ];
                }
            }

            foreach ($kolomB as $nama => $_) {
                if (! isset($kolomA[$nama])) {
                    $kolomAsing[] = "{$tabel}.{$nama}";
                }
            }
        }

        sort($kolomHilang);
        sort($kolomAsing);
        usort($kolomBeda, fn ($x, $y) => strcmp($x['kolom'], $y['kolom']));

        return [
            'sama' => $tabelHilang === [] && $tabelAsing === [] && $kolomHilang === []
                && $kolomAsing === [] && $kolomBeda === [],
            'tabel_hilang' => $tabelHilang,
            'tabel_asing' => $tabelAsing,
            'kolom_hilang' => $kolomHilang,
            'kolom_asing' => $kolomAsing,
            'kolom_beda' => $kolomBeda,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return array<string, array<string, array{tipe: string, panjang: int, presisi: int, skala: int, nullable: bool}>>
     */
    private function kelompokkan(array $baris): array
    {
        $hasil = [];

        foreach ($baris as $b) {
            $tabel = (string) ($b['tabel'] ?? '');
            $kolom = (string) ($b['kolom'] ?? '');

            if ($tabel === '' || $kolom === '') {
                continue;
            }

            $hasil[$tabel][$kolom] = [
                'tipe' => (string) ($b['tipe'] ?? ''),
                'panjang' => (int) ($b['panjang'] ?? 0),
                'presisi' => (int) ($b['presisi'] ?? 0),
                'skala' => (int) ($b['skala'] ?? 0),
                // Driver SQL Server mengembalikan bit sebagai 0/1, kadang sebagai
                // string. Dinormalkan supaya "1" dan 1 tidak terbaca sebagai beda.
                'nullable' => (bool) ($b['nullable'] ?? false),
            ];
        }

        return $hasil;
    }

    /** @param array{tipe: string, panjang: int, presisi: int, skala: int, nullable: bool} $sifat */
    private function rangkumSifat(array $sifat): string
    {
        $inti = $sifat['tipe'];

        if (in_array($sifat['tipe'], ['decimal', 'numeric'], true)) {
            $inti .= "({$sifat['presisi']},{$sifat['skala']})";
        } elseif ($sifat['panjang'] > 0) {
            $inti .= "({$sifat['panjang']})";
        }

        return $inti.($sifat['nullable'] ? ' NULL' : ' NOT NULL');
    }
}
