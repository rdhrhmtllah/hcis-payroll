<?php

namespace App\Services\LMS\SOP\Support;

/**
 * Nomor dokumen gabungan SOP.
 *
 *   Jenis / Entitas / Departemen / RuangLingkup / NoAPQC / NoDokumen / Revisi
 *   IK    / ENB     / PROD       / EHG          / 09     / 003       / 01
 *
 * LEBAR SEGMEN mengikuti register dokumen terkendali perusahaan (register EMI,
 * 143 dokumen, keputusan pemilik 2026-09-17): No. Dokumen TIGA digit mulai dari
 * 001, Revisi DUA digit mulai dari 00, No. APQC dua digit.
 *
 * DIHITUNG, TIDAK DISIMPAN. Setiap segmen sudah punya kolomnya sendiri di
 * N_SOP_Dokumen, dan segmen terakhir bergerak setiap kali versi baru terbit.
 * Menyimpannya sebagai satu kolom teks berarti menyimpan salinan yang basi
 * sejak revisi berikutnya — dan yang basi diam-diam adalah yang paling jarang
 * diperiksa. Kolom-kolomnya tetap menjadi kebenaran; ini hanya cara membacanya.
 *
 * SATU PENULIS. Layar juga perlu menampilkannya, tetapi rumus ini TIDAK
 * disalin ke JavaScript: dua implementasi untuk satu format pasti menyimpang,
 * dan yang menyimpang di sini adalah nomor yang tercetak di dokumen resmi.
 * Server yang merakit, layar yang menampilkan.
 */
class SopNomorDokumen
{
    /** Pemisah antar segmen. */
    public const PEMISAH = '/';

    /** Ditampilkan menggantikan segmen yang belum terisi. */
    public const KOSONG = '??';

    /** Lebar minimum segmen angka. Lebih lebar dibiarkan, tak pernah dipotong. */
    public const LEBAR_APQC = 2;

    public const LEBAR_NOMOR = 3;

    public const LEBAR_REVISI = 2;

    /**
     * Rakit nomor lengkap dari tujuh segmen.
     *
     * Mengembalikan null bila ADA SATU SAJA segmen yang kosong. Nomor separuh
     * jadi lebih berbahaya daripada tak ada nomor: ia terlihat sah, tercetak,
     * lalu dipakai merujuk dokumen yang identitasnya belum lengkap. Pemanggil
     * yang ingin memperlihatkan kekurangannya memakai pratinjau().
     *
     * @param  array{
     *     jenis: ?string, entitas: ?string, departemen: ?string,
     *     lingkup: ?string, apqc: ?string, nomor: int|string|null,
     *     revisi: int|string|null
     * }  $segmen
     */
    public static function rakit(array $segmen): ?string
    {
        $bagian = self::bagian($segmen);

        foreach ($bagian as $b) {
            if ($b === null) {
                return null;
            }
        }

        return implode(self::PEMISAH, $bagian);
    }

    /**
     * Bentuk untuk diperlihatkan saat identitasnya belum lengkap.
     *
     * Segmen yang kosong menjadi "??" — terlihat sebagai lubang yang harus
     * diisi, bukan sebagai nomor yang sudah jadi.
     */
    public static function pratinjau(array $segmen): string
    {
        return implode(self::PEMISAH, array_map(
            fn ($b) => $b ?? self::KOSONG,
            self::bagian($segmen),
        ));
    }

    /**
     * Nama segmen yang masih kosong, untuk pesan yang bisa dikerjakan.
     *
     * @return array<int, string>
     */
    public static function segmenKosong(array $segmen): array
    {
        $label = [
            'jenis' => 'Jenis dokumen',
            'entitas' => 'Entitas pemilik',
            'departemen' => 'Departemen pemilik',
            'lingkup' => 'Ruang lingkup',
            'apqc' => 'Proses APQC',
            'nomor' => 'No. dokumen',
            'revisi' => 'Revisi',
        ];

        $kosong = [];
        foreach (self::bagian($segmen) as $kunci => $nilai) {
            if ($nilai === null) {
                $kosong[] = $label[$kunci];
            }
        }

        return $kosong;
    }

    /**
     * Tujuh segmen dalam URUTAN TETAP, masing-masing sudah diformat.
     *
     * Urutannya ditulis sekali di sini dan tidak boleh diurutkan ulang oleh
     * pemanggil: nomor yang segmennya bertukar tempat tetap terlihat sah dan
     * tak ada yang menyadarinya sampai dokumennya dicari dan tak ketemu.
     *
     * @return array<string, ?string>
     */
    private static function bagian(array $segmen): array
    {
        return [
            'jenis' => self::teks($segmen['jenis'] ?? null),
            'entitas' => self::teks($segmen['entitas'] ?? null),
            'departemen' => self::teks($segmen['departemen'] ?? null),
            'lingkup' => self::teks($segmen['lingkup'] ?? null),
            /* APQC sudah dua digit di masternya ("01".."13"), tetapi tetap
               dilewatkan pemformat angka: bila suatu saat ada kode yang
               ditulis "7", nomornya tak boleh ikut berubah bentuk. */
            'apqc' => self::angka($segmen['apqc'] ?? null, self::LEBAR_APQC),
            'nomor' => self::angka($segmen['nomor'] ?? null, self::LEBAR_NOMOR),
            'revisi' => self::angka($segmen['revisi'] ?? null, self::LEBAR_REVISI),
        ];
    }

    /** Segmen teks: huruf besar, tanpa spasi. Kosong menjadi null. */
    private static function teks($nilai): ?string
    {
        $v = strtoupper(trim((string) ($nilai ?? '')));

        return $v === '' ? null : $v;
    }

    /**
     * Segmen angka, dipadatkan nol di depan sampai $lebar digit.
     *
     * NOL ITU SAH DI SINI, dan pemformat ini sengaja tidak menghakimi nilainya:
     * `$v ? … : null` akan menghapus segmen "00". Yang diperiksa null/string
     * kosong, bukan benar-tidaknya angkanya.
     *
     * Tetapi nol hanya lazim pada SATU segmen. REVISI mulai dari nol, karena ia
     * menghitung berapa kali dokumennya sudah direvisi dan terbitan pertama
     * belum pernah. NOMOR URUT DOKUMEN mulai dari satu — tak ada dokumen
     * ke-nol dalam sebuah kombinasi. Aturannya ditegakkan di hulu
     * (SopDokumenService::saranNoDokumen dan validasi No_Dokumen), bukan di
     * pemformat ini: memaksakannya di sini akan ikut menolak revisi 00 yang
     * bentuknya persis sama.
     *
     * Lebih lebar dari $lebar dibiarkan apa adanya. Memotongnya membuat 1000
     * tampil sebagai 000, yaitu nomor lain yang juga benar-benar ada.
     */
    public static function angka($nilai, int $lebar = 2): ?string
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        if (! is_numeric($nilai)) {
            return self::teks($nilai);
        }

        return str_pad((string) (int) $nilai, $lebar, '0', STR_PAD_LEFT);
    }
}
