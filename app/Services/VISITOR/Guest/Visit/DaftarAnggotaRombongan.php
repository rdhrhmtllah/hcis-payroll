<?php

namespace App\Services\VISITOR\Guest\Visit;

use InvalidArgumentException;

/** Penyusun dan pembaca manifes anggota rombongan tamu. */
class DaftarAnggotaRombongan
{
    public const MAKS_ANGGOTA_ROMBONGAN = 50;

    private const MAKS_PANJANG_NAMA = 150;

    private const MAKS_PANJANG_JENIS_IDENTITAS = 20;

    private const MAKS_PANJANG_NO_IDENTITAS = 100;

    /**
     * @param  array<int, array<string, mixed>>  $anggotaLain
     * @return array<int, array<string, string>>
     */
    public function susun(string $namaUtama, array $anggotaLain): array
    {
        $daftar = [$this->bersihkan(['nama' => $namaUtama], 'Nama tamu utama')];

        foreach (array_values($anggotaLain) as $index => $anggota) {
            $daftar[] = $this->bersihkan(
                is_array($anggota) ? $anggota : ['nama' => $anggota],
                'Anggota ke-'.($index + 2),
            );
        }

        if (count($daftar) > self::MAKS_ANGGOTA_ROMBONGAN) {
            throw new InvalidArgumentException(
                'Jumlah anggota rombongan maksimal '.self::MAKS_ANGGOTA_ROMBONGAN.' orang, termasuk tamu utama.',
            );
        }

        return $daftar;
    }

    /**
     * Manifes APA ADANYA - tamu utama tidak ikut disisipkan.
     *
     * Dipakai pintu API mitra: yang dikirim mitra adalah daftar orang yang
     * menyertai tamunya, dan tamu yang datang sendirian memang berdaftar
     * kosong. Menyisipkan namanya sendiri di sana membuat mitra membaca
     * kembali sebuah nama yang tidak pernah ia kirim.
     *
     * @param  array<int, array<string, mixed>>  $anggota
     * @return array<int, array<string, string>>
     */
    public function susunApaAdanya(array $anggota): array
    {
        $daftar = [];

        foreach (array_values($anggota) as $index => $satu) {
            $daftar[] = $this->bersihkan(
                is_array($satu) ? $satu : ['nama' => $satu],
                'Anggota ke-'.($index + 1),
            );
        }

        // Batasnya dihitung terhadap JUMLAH ORANG, bukan panjang manifesnya:
        // manifes tanpa tamu utama berisi satu orang lebih sedikit, dan
        // membandingkan panjangnya begitu saja diam-diam melonggarkan batas
        // untuk pintu API.
        if (count($daftar) + 1 > self::MAKS_ANGGOTA_ROMBONGAN) {
            throw new InvalidArgumentException(
                'Jumlah anggota rombongan maksimal '.self::MAKS_ANGGOTA_ROMBONGAN.' orang, termasuk tamu utama.',
            );
        }

        return $daftar;
    }

    /**
     * Berapa ORANG yang datang, apa pun bentuk manifesnya.
     *
     * Ada dua bentuk manifes yang sah: layar HCIS menyimpan tamu utama sebagai
     * baris pertama, API mitra tidak. Menghitung panjang manifes begitu saja
     * membuat tamu API terhitung kurang satu - dan angka itulah yang dibaca
     * papan harian, laporan, dan dasbor.
     *
     * @param  array<int, array<string, string>>  $daftar
     */
    public function jumlahOrang(array $daftar, bool $memuatTamuUtama): int
    {
        return $memuatTamuUtama ? max(count($daftar), 1) : count($daftar) + 1;
    }

    /** @param array<int, array<string, string>> $daftar */
    public function keJson(array $daftar): string
    {
        return json_encode(array_values($daftar), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @return array<int, array<string, string>> */
    public function dariJson(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $isi = json_decode($json, true);

        if (! is_array($isi)) {
            return [];
        }

        if ($isi === []) {
            return [];
        }

        $kunci = array_keys($isi);

        return $kunci === range(0, count($isi) - 1) ? $isi : [];
    }

    /** @param array<int, array<string, string>> $daftar */
    public function jumlah(array $daftar): int
    {
        return count($daftar);
    }

    /**
     * @param  array<string, mixed>  $anggota
     * @return array<string, string>
     */
    private function bersihkan(array $anggota, string $label): array
    {
        $nama = trim((string) ($anggota['nama'] ?? ''));

        if ($nama === '') {
            throw new InvalidArgumentException($label.' wajib diisi.');
        }

        if (mb_strlen($nama) > self::MAKS_PANJANG_NAMA) {
            throw new InvalidArgumentException($label.' maksimal '.self::MAKS_PANJANG_NAMA.' karakter.');
        }

        $bersih = ['nama' => $nama];

        foreach ([
            'jenis_identitas' => self::MAKS_PANJANG_JENIS_IDENTITAS,
            'no_identitas' => self::MAKS_PANJANG_NO_IDENTITAS,
        ] as $kunci => $maksPanjang) {
            $nilai = trim((string) ($anggota[$kunci] ?? ''));

            if (mb_strlen($nilai) > $maksPanjang) {
                throw new InvalidArgumentException($label.' '.$kunci.' maksimal '.$maksPanjang.' karakter.');
            }

            if ($nilai !== '') {
                $bersih[$kunci] = $nilai;
            }
        }

        return $bersih;
    }
}
