<?php

namespace App\Services\KPI\PerformanceAppraisal\Katalog;

use RuntimeException;

/**
 * Satu-satunya tempat yang menafsirkan bentuk ujian → paket → alat tes → bagian dari
 * HC Learn.
 *
 * KENAPA IDENTITASNYA MAJEMUK
 *
 * HC Learn tidak menyediakan id per-instance untuk bagian. Yang ada hanya
 * `id_jenis_soal`, dan dokumentasi mereka sendiri menyebutnya pengenal BANK SOAL:
 * "Bank yang sama bisa dipakai beberapa alat tes." Dikonfirmasi langsung pada
 * 9 September 2026 bahwa hanya itu yang bisa mereka sediakan.
 *
 * Dua field lain yang tampak menggoda dan keduanya berbahaya:
 *
 *   - posisi dalam array bergeser ketika bagian disisipkan di tengah, sehingga label ikut
 *     pindah ke bagian yang salah — tanpa galat, tanpa gejala;
 *   - `kode_soal` adalah kode JENIS soal, boleh null, dan berulang seperti banknya.
 *
 * Jadi identitasnya pasangan (id_jenis_indikator, id_jenis_soal). Pasangan itu unik hanya
 * selama satu alat tes tidak memakai bank yang sama dua kali — yang tidak dijamin di mana
 * pun. `kembar()` mendeteksinya supaya pengikatannya bisa DITOLAK, bukan disimpan ambigu.
 */
class PaBagianKatalog
{
    /**
     * Kunci satu bagian: pasangan alat tes dan bank soalnya.
     *
     * @param  array<string, mixed>  $bagian
     */
    public static function kunci(array $bagian): string
    {
        return ($bagian['id_jenis_indikator'] ?? '').'|'.($bagian['id_jenis_soal'] ?? '');
    }

    /**
     * Urutan tampil satu bagian di dalam paket siklusnya.

     * DUA SUMBU DIGABUNG supaya bagian dari alat tes kedua tidak menyelip di antara
     * bagian alat tes pertama. Aman di smallint: PA 360 memakai paling banyak dua alat
     * tes dengan 21 bagian, jadi tertinggi 122.
     *
     * DITAMBAH SATU, dan itu bukan kosmetik. `ratakan()` memulangkan POSISI ARRAY, yang
     * dimulai dari nol; sementara `N_PA_Cycle_Question_Package_Item.Urutan_Tampil`
     * menuntut `>= 1` lewat `CK_PA_Cycle_Question_Item_Urutan`. Tanpa penambahan ini
     * bagian PERTAMA dari alat tes pertama selalu bernilai 0, dan karena penyisipannya
     * satu pernyataan untuk seluruh paket, SATU baris itu menggagalkan seluruh impor —
     * setiap paket, setiap sel, setiap kali. Menjepitnya dengan `max(1, ...)` bukan
     * jalan keluar: bagian pertama dan kedua akan sama-sama bernilai 1 dan urutannya
     * hilang.
     *
     * Berada di sini, bukan disalin di tiap penyisip, karena penyisipnya ADA DUA —
     * impor satu sel dan penyalinan massal dari master. Rumus yang disalin adalah rumus
     * yang suatu hari berbeda di satu tempat saja.
     *
     * @param  array<string, mixed>  $bagian
     */
    public static function urutanTampil(array $bagian): int
    {
        return ((int) ($bagian['urutan_alat_tes'] ?? 0)) * 100
            + ((int) ($bagian['urutan_bagian'] ?? 0))
            + 1;
    }

    /**
     * Meratakan seluruh bagian dari seluruh alat tes dalam satu ujian.
     *
     * Diratakan karena admin melabeli kompetensi, dan kompetensi tidak mengenal batas alat
     * tes. Nama alat tesnya tetap dibawa supaya layar bisa mengelompokkannya kembali —
     * satu paket bisa memuat dua alat tes yang sama-sama punya "Bagian 1".
     *
     * @param  array<string, mixed>  $ujian
     * @return list<array{id_jenis_indikator: string, id_jenis_soal: string, nama_alat_tes: string, nama: string, kode_soal: ?string, urutan_alat_tes: int, urutan_bagian: int}>
     */
    public static function ratakan(array $ujian): array
    {
        $daftar = [];
        $alatTes = (array) ($ujian['paket']['alat_tes'] ?? $ujian['alat_tes'] ?? []);

        foreach (array_values($alatTes) as $iAlat => $alat) {
            $alat = (array) $alat;

            /* Alat tes khusus (Kraeplin, PAPI, DISC) memang tidak berbagian — jumlah_bagian
               bernilai 0 menurut kontrak mereka. Dilewati, bukan digagalkan. */
            foreach (array_values((array) ($alat['bagian'] ?? [])) as $iBagian => $b) {
                $b = (array) $b;
                $idJenisSoal = $b['id_jenis_soal'] ?? null;

                if ($idJenisSoal === null || $idJenisSoal === '') {
                    throw new RuntimeException(
                        'Bagian soal ini tidak membawa identitas bagian yang bisa dipercaya. '
                        .'Posisi dalam daftar tidak boleh dipakai karena bergeser saat '
                        .'penyisipan, dan kode_soal tidak unik. Minta HC Learn menyertakan '
                        .'id_jenis_soal pada setiap bagian.'
                    );
                }

                $daftar[] = [
                    'id_jenis_indikator' => (string) ($alat['id_jenis_indikator'] ?? ''),
                    'id_jenis_soal' => (string) $idJenisSoal,
                    'nama_alat_tes' => (string) ($alat['nama'] ?? ''),
                    'nama' => (string) ($b['nama'] ?? ''),
                    'kode_soal' => isset($b['kode_soal']) ? (string) $b['kode_soal'] : null,

                    /* Urutan tampilan saja. TIDAK pernah dipakai sebagai kunci — kontrak
                       HC Learn sendiri menyatakan urutan array adalah urutan pengerjaan
                       dan tidak ada field nomor tersendiri. */
                    'urutan_alat_tes' => $iAlat,
                    'urutan_bagian' => $iBagian,
                ];
            }
        }

        return $daftar;
    }

    /**
     * Pasangan yang muncul lebih dari sekali.
     *
     * Selama ada yang kembar, seluruh pemetaan ambigu: dua bagian berbeda menunjuk baris
     * yang sama. Pemanggilnya WAJIB menolak pengikatan, bukan menyimpan yang terakhir.
     *
     * @param  list<array<string, mixed>>  $daftar
     * @return list<string>
     */
    public static function kembar(array $daftar): array
    {
        $hitung = array_count_values(array_map(fn ($b) => self::kunci($b), $daftar));

        return array_values(array_keys(array_filter($hitung, fn ($n) => $n > 1)));
    }

    /**
     * Sidik HIMPUNAN pasangan — bukan susunannya, dan bukan label kita.
     *
     * Label kita sengaja tidak ikut: sidik yang mencampur keduanya membuat admin yang
     * mengganti labelnya sendiri terbaca sebagai "ujian berubah di HC Learn", yaitu sistem
     * menuduh HC Learn atas perubahan yang kita buat sendiri.
     *
     * @param  list<array<string, mixed>>  $daftar
     */
    public static function sidik(array $daftar): string
    {
        $kunci = array_map(fn ($b) => self::kunci($b), $daftar);
        sort($kunci);

        return hash('sha256', implode("\n", $kunci));
    }
}
