<?php

namespace App\Services\KPI\PerformanceAppraisal;

use RuntimeException;

/**
 * Satu-satunya tempat yang memutuskan apakah sekumpulan bobot berjumlah 100, dan
 * satu-satunya tempat yang membagi ulang bobot ketika sebagian kategori kosong.
 *
 * Empat aturan yang sebelumnya hanya berupa kalimat di README bermuara ke sini: bobot
 * detail penilai per salinan, target distribusi grade per siklus, bobot hasil resolusi
 * per peserta, dan Bobot_Dalam_Kategori per (peserta, kategori).
 *
 * DUA FUNGSI, DAN URUTANNYA PENTING
 *
 * `redistribusi()` menghasilkan bobot yang PASTI berjumlah 100. `pastikanSeratus()`
 * memeriksa bahwa memang begitu. Yang pertama mencegah, yang kedua menangkap. Kalau
 * hanya ada pemeriksa, setiap pemanggil membuat pembagi ulangnya sendiri dan
 * pembulatannya akan berbeda-beda.
 *
 * KENAPA PEMBULATAN JADI SOAL
 *
 * Membagi 100 ke tiga kategori menghasilkan 33,33 tiga kali — jumlahnya 99,99, bukan
 * 100. Perbandingan sama-dengan yang naif akan menolak pembagian yang justru benar.
 * Karena itu `redistribusi()` memakai sisa terbesar: selisih pembulatannya
 * ditambahkan ke bagian terbesar sehingga jumlahnya kembali tepat 100, dan
 * `pastikanSeratus()` tetap memberi toleransi kecil untuk angka yang datang dari
 * sumber lain.
 */
class PaBobotGuard
{
    /**
     * Toleransi jumlah. Cukup untuk menyerap satu langkah pembulatan pada dua angka
     * desimal, tidak cukup untuk menyembunyikan kesalahan yang sebenarnya.
     */
    public const TOLERANSI = 0.011;

    /**
     * Melempar bila jumlahnya bukan 100. Bentuk inilah yang seharusnya dipakai —
     * mengembalikan boolean mengundang orang lupa memeriksanya.
     *
     * @param  array<string|int, float|int|string|null>  $bobot  boleh dikunci nama kategori
     */
    public function pastikanSeratus(array $bobot, string $konteks): void
    {
        $total = $this->total($bobot);

        if (abs($total - 100.0) <= self::TOLERANSI) {
            return;
        }

        $rincian = [];
        foreach ($bobot as $kunci => $nilai) {
            $rincian[] = is_int($kunci) ? (string) $this->angka($nilai) : "{$kunci}={$this->angka($nilai)}";
        }

        throw new RuntimeException(sprintf(
            'Bobot %s berjumlah %s, seharusnya 100. Rincian: %s.',
            $konteks,
            rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.'),
            $rincian === [] ? '(kosong)' : implode(' + ', $rincian)
        ));
    }

    public function berjumlahSeratus(array $bobot): bool
    {
        return abs($this->total($bobot) - 100.0) <= self::TOLERANSI;
    }

    /**
     * Membagi ulang bobot ke kategori yang TERISI, dengan hasil yang pasti berjumlah
     * tepat 100.
     *
     * Contoh nyata: aturan level Manager memberi Atasan 45, Self 10, Rekan 15, Bawahan
     * 30. Kalau tidak ada rekan yang mengisi, 15 miliknya dibagi ulang secara
     * proporsional ke tiga kategori sisanya.
     *
     * Ini BUKAN penambalan. Kategori yang kosong bukan berarti nilainya nol — berarti
     * tidak ada informasi dari sana, jadi bobotnya dialihkan ke yang punya informasi.
     * Memperlakukannya sebagai nol akan menghukum peserta atas ketidakhadiran orang
     * lain.
     *
     * @param  array<string|int, float|int|string|null>  $bobotAwal  seluruh kategori beserta bobot aturannya
     * @param  list<string|int>  $kunciTerisi  kategori yang benar-benar punya nilai
     * @return array<string|int, float> hanya kategori terisi, berjumlah tepat 100
     */
    public function redistribusi(array $bobotAwal, array $kunciTerisi): array
    {
        $terisi = [];
        foreach ($kunciTerisi as $k) {
            if (! array_key_exists($k, $bobotAwal)) {
                throw new RuntimeException("Kategori {$k} tidak ada di daftar bobot awal.");
            }
            $terisi[$k] = $this->angka($bobotAwal[$k]);
        }

        if ($terisi === []) {
            throw new RuntimeException(
                'Tidak ada kategori terisi sama sekali; bobot tidak bisa dibagi ulang. '
                .'Peserta seperti ini harus TERTAHAN, bukan diberi angka.'
            );
        }

        $totalTerisi = array_sum($terisi);

        if ($totalTerisi <= 0) {
            throw new RuntimeException(
                'Seluruh kategori terisi berbobot nol; pembagian ulang akan membagi dengan nol.'
            );
        }

        $hasil = [];
        foreach ($terisi as $k => $b) {
            $hasil[$k] = round($b / $totalTerisi * 100, 2);
        }

        /* Sisa pembulatan diberikan ke bagian TERBESAR, bukan ke yang pertama.
           Memberikannya ke yang pertama membuat hasilnya bergantung pada urutan
           masukan — dua panggilan dengan isi sama tapi urutan beda akan menghasilkan
           angka berbeda, dan itu mustahil dijelaskan saat diaudit. */
        $selisih = round(100.0 - array_sum($hasil), 2);

        if (abs($selisih) >= 0.01) {
            /* Saat beberapa bagian SAMA BESAR, max() memilih yang pertama menurut
               urutan masukan — dan itu mengembalikan ketergantungan urutan yang justru
               ingin dihindari. Kandidatnya karena itu diurutkan menurut kuncinya dulu,
               sehingga tiga bobot yang sama besar selalu memberikan sisanya ke kunci
               yang sama, berapa pun urutan datangnya. */
            $kandidat = array_keys($hasil, max($hasil), true);
            sort($kandidat);
            $kunciTerbesar = $kandidat[0];
            $hasil[$kunciTerbesar] = round($hasil[$kunciTerbesar] + $selisih, 2);
        }

        $this->pastikanSeratus($hasil, 'hasil pembagian ulang');

        return $hasil;
    }

    /**
     * Membagi rata ke sejumlah penilai dalam satu kategori — bakunya
     * `Bobot_Dalam_Kategori` saat penugasan dibentuk. Satu atasan 100, dua atasan
     * 50/50, tiga atasan 33,33/33,33/33,34.
     *
     * @return list<float> berjumlah tepat 100
     */
    public function bagiRata(int $jumlah): array
    {
        if ($jumlah < 1) {
            throw new RuntimeException('Jumlah penilai untuk pembagian rata minimal 1.');
        }

        return array_values($this->redistribusi(
            array_fill(0, $jumlah, 1.0),
            range(0, $jumlah - 1)
        ));
    }

    /**
     * @param  array<string|int, float|int|string|null>  $bobot
     */
    private function total(array $bobot): float
    {
        $total = 0.0;
        foreach ($bobot as $nilai) {
            $total += $this->angka($nilai);
        }

        return round($total, 2);
    }

    private function angka(float|int|string|null $nilai): float
    {
        return $nilai === null ? 0.0 : (float) $nilai;
    }
}
