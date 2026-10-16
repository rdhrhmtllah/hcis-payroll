<?php

namespace App\Services\SYSTEM\Application\Reports;

use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;

/**
 * Memeriksa definisi terhadap katalog dataset.
 *
 * Dijalankan DUA KALI: saat simpan dan saat jalan. Sebuah template bisa menjadi
 * tak sah tanpa disentuh siapa pun — field dihapus dari katalog, atau hak
 * penjalankannya berubah. Galat saat jalan WAJIB menyebut field mana yang
 * bermasalah; "template rusak" tanpa nama field memaksa pengguna membangun
 * ulang dari nol.
 */
class DefinisiValidator
{
    public const MAKS_DIMENSI = 6;

    public const MAKS_UKURAN = 12;

    public const MAKS_KOLOM = 60;

    public const MAKS_SARINGAN = 20;

    /**
     * Batas jumlah nilai dalam SATU saringan 'salah_satu'.
     *
     * MAKS_SARINGAN membatasi banyaknya saringan, bukan isinya — satu tempelan
     * daftar karyawan bisa membawa ratusan nilai dan menjadi IN (@P1..@P900).
     * Bentuk itu memaksa SQL Server menyusun ulang rencana untuk tiap panjang
     * daftar yang berbeda, dan sudah pernah jadi salah satu penyumbang CPU
     * mentok di sini. Ditolak, bukan dipangkas diam-diam: daftar yang terpotong
     * menghasilkan laporan yang tampak lengkap padahal bukan.
     */
    public const MAKS_NILAI_SARINGAN = 200;

    private const MODE = ['ringkasan', 'rinci'];

    private const OPERATOR_PER_TIPE = [
        'teks' => ['sama_dengan', 'tidak_sama', 'salah_satu', 'mengandung'],
        'angka' => ['sama_dengan', 'tidak_sama', 'lebih_besar', 'lebih_besar_sama', 'lebih_kecil', 'lebih_kecil_sama', 'salah_satu', 'antara'],
        'tanggal' => ['antara', 'sama_dengan', 'tidak_sama', 'lebih_besar', 'lebih_besar_sama', 'lebih_kecil', 'lebih_kecil_sama'],
    ];

    /**
     * Agregat racikan pengguna: pasangan (kolom katalog, fungsi whitelist).
     *
     * Ditolak DI SINI, bukan dibiarkan gagal di SQL. Pasangan yang keliru —
     * SUM atas kolom teks, misalnya — menghasilkan galat basis data mentah yang
     * tidak menyebut kolom mana penyebabnya, dan pemakainya hanya melihat
     * "laporan gagal dibuat" di layar perakit yang penuh pilihan.
     *
     * @param  array<string, Dimensi>  $dimensiSah
     * @param  array<string, string[]>  $galat
     * @return array<string, string[]>
     */
    private function periksaAgregatRacikan(DefinisiLaporan $d, array $dimensiSah, array $galat): array
    {
        if ($d->agregat === []) {
            return $galat;
        }

        if (count($d->agregat) > self::MAKS_UKURAN) {
            $galat['agregat'][] = 'Maksimal '.self::MAKS_UKURAN.' agregat racikan.';
        }

        $terpakai = [];

        foreach ($d->agregat as $item) {
            if (! is_array($item)) {
                $galat['agregat'][] = 'Bentuk agregat tidak dikenal.';

                continue;
            }

            $kolom = (string) ($item['kolom'] ?? '');
            $fungsi = strtolower((string) ($item['fungsi'] ?? ''));

            if (! isset($dimensiSah[$kolom])) {
                $galat['agregat'][] = "Kolom agregat tidak dikenal: {$kolom}";

                continue;
            }

            if (! AgregatRacikan::diizinkan($dimensiSah[$kolom], $fungsi)) {
                $galat['agregat'][] = sprintf(
                    'Fungsi %s tidak berlaku untuk kolom %s (%s). Yang tersedia: %s.',
                    strtoupper($fungsi ?: '-'),
                    $dimensiSah[$kolom]->label,
                    $dimensiSah[$kolom]->tipe,
                    implode(', ', array_map('strtoupper', AgregatRacikan::fungsiUntuk($dimensiSah[$kolom]))),
                );

                continue;
            }

            // Pasangan kembar tidak menghasilkan kolom kedua; menyebutnya di
            // sini lebih baik daripada diam-diam melipatnya, karena pemakainya
            // memang mengira baru saja menambah sesuatu.
            $kunci = AgregatRacikan::kunci($kolom, $fungsi);
            if (isset($terpakai[$kunci])) {
                $galat['agregat'][] = sprintf(
                    '%s %s sudah ditambahkan.',
                    strtoupper($fungsi),
                    $dimensiSah[$kolom]->label,
                );
            }
            $terpakai[$kunci] = true;
        }

        return $galat;
    }

    /** @return array<string, string[]> kosong bila sah */
    public function periksa(DefinisiLaporan $d, DatasetLaporan $ds): array
    {
        $galat = [];

        $dimensiSah = $this->petakan($ds->dimensi());
        $ukuranSah = $this->petakan($ds->ukuran());
        $saringanSah = $this->petakan($ds->saringan());

        if (! in_array($d->mode, self::MODE, true)) {
            $galat['mode'][] = "Mode tidak dikenal: {$d->mode}";
        }

        foreach ($d->dimensi as $k) {
            if (! is_string($k) || ! isset($dimensiSah[$k])) {
                $galat['dimensi'][] = 'Dimensi tidak dikenal: '.(is_string($k) ? $k : gettype($k));
            }
        }
        if (count($d->dimensi) > self::MAKS_DIMENSI) {
            $galat['dimensi'][] = 'Maksimal '.self::MAKS_DIMENSI.' dimensi.';
        }

        foreach ($d->ukuran as $k) {
            // Kunci racikan dilewati DI SINI.
            //
            // Ia otomatis ikut ke daftar ukuran saat definisi diurai, tetapi
            // hanya masuk katalog bila pasangannya sah. Pasangan yang keliru
            // karena itu menghasilkan dua pesan untuk satu kesalahan — dan yang
            // pertama berbunyi "Ukuran tidak dikenal: racik__avg__nama_pemegang",
            // kunci internal yang tidak berarti apa pun bagi pemakainya.
            // Penjelasan yang benar sudah disusun pemeriksa agregat di bawah.
            if (is_string($k) && str_starts_with($k, AgregatRacikan::AWALAN_KUNCI)) {
                continue;
            }

            if (! is_string($k) || ! isset($ukuranSah[$k])) {
                $galat['ukuran'][] = 'Ukuran tidak dikenal: '.(is_string($k) ? $k : gettype($k));
            }
        }
        if (count($d->ukuran) > self::MAKS_UKURAN) {
            $galat['ukuran'][] = 'Maksimal '.self::MAKS_UKURAN.' ukuran.';
        }

        $galat = $this->periksaAgregatRacikan($d, $dimensiSah, $galat);

        foreach ($d->kolom as $k) {
            if (! is_string($k) || ! isset($dimensiSah[$k])) {
                $galat['kolom'][] = 'Kolom tidak dikenal: '.(is_string($k) ? $k : gettype($k));
            }
        }
        if (count($d->kolom) > self::MAKS_KOLOM) {
            $galat['kolom'][] = 'Maksimal '.self::MAKS_KOLOM.' kolom.';
        }

        if ($d->mode === 'ringkasan' && count($d->ukuran) === 0) {
            $galat['ukuran'][] = 'Mode ringkasan membutuhkan minimal satu ukuran.';
        }
        if ($d->mode === 'rinci' && count($d->kolom) === 0) {
            $galat['kolom'][] = 'Mode rinci membutuhkan minimal satu kolom.';
        }

        if (count($d->saringan) > self::MAKS_SARINGAN) {
            $galat['saringan'][] = 'Maksimal '.self::MAKS_SARINGAN.' saringan.';
        }
        foreach ($d->saringan as $s) {
            $field = is_array($s) ? ($s['field'] ?? null) : null;
            $op = is_array($s) ? ($s['op'] ?? null) : null;
            $nilai = is_array($s) ? ($s['nilai'] ?? null) : null;

            if (is_array($nilai) && count($nilai) > self::MAKS_NILAI_SARINGAN) {
                $galat['saringan'][] = "Saringan '".(is_string($field) ? $field : '?')."' memuat "
                    .count($nilai).' nilai; maksimal '.self::MAKS_NILAI_SARINGAN
                    .'. Persempit dengan saringan lain (divisi, paket, periode) alih-alih menempel daftar panjang.';
            }

            if (! is_string($field) || ! isset($saringanSah[$field])) {
                $galat['saringan'][] = 'Saringan tidak dikenal: '.(is_string($field) ? $field : gettype($field));

                continue;
            }

            $def = $saringanSah[$field];
            $diizinkanTipe = self::OPERATOR_PER_TIPE[$def->tipe] ?? [];

            if (! is_string($op) || ! in_array($op, $def->operator, true) || ! in_array($op, $diizinkanTipe, true)) {
                $galat['saringan'][] = "Operator '".(is_string($op) ? $op : gettype($op))."' tidak berlaku untuk field '{$field}' bertipe {$def->tipe}.";
            }
        }

        if ($d->bagan !== null) {
            if (! in_array($d->bagan['dimensi'] ?? null, $d->dimensi, true)) {
                $galat['bagan'][] = 'Dimensi bagan harus salah satu dimensi yang dipilih.';
            }
            foreach ((array) ($d->bagan['ukuran'] ?? []) as $u) {
                if (! in_array($u, $d->ukuran, true)) {
                    $galat['bagan'][] = 'Ukuran bagan harus salah satu ukuran yang dipilih.';
                }
            }
        }

        return $galat;
    }

    /** @return array<string, object> */
    private function petakan(array $daftar): array
    {
        $peta = [];
        foreach ($daftar as $item) {
            $peta[$item->key] = $item;
        }

        return $peta;
    }
}
