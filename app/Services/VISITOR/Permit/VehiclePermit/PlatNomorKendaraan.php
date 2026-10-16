<?php

namespace App\Services\VISITOR\Permit\VehiclePermit;

/**
 * Satu-satunya penafsir plat nomor di modul Visitor.
 *
 * Basis data menyimpan plat terpisah sebagai prefix, angka, dan suffix,
 * sedangkan yang datang dari pemakai maupun mitra berbentuk macam-macam:
 * satu string utuh ("BG 1234 MT", "bg1234mt") atau tiga kolom terpisah.
 *
 * Aturannya hidup di satu kelas karena dipakai EMPAT pintu penulisan — web
 * tamu, web permit, API tamu B2B, API permit — dan dua penafsir yang berbeda
 * berarti kendaraan yang sama tercatat dengan dua bentuk berbeda di gerbang,
 * lalu pencarian plat di meja verifikator meleset atas kendaraan yang jelas
 * ada.
 *
 * Tiga kegagalan yang PERNAH terjadi dan dijaga di sini:
 *
 *  1. Plat hilang senyap. Bentuk lama mengembalikan tiga null ketika polanya
 *     tak cocok, sehingga "B 12345 XYZ" dari mitra tersimpan sebagai kendaraan
 *     tanpa plat — tanpa galat, tanpa jejak.
 *  2. Dua tafsir. Salinan terpisah di VehiclePermitController memecah atas
 *     spasi saja, sehingga "bg1234mt" menjadi prefix delapan karakter,
 *     sementara pintu mitra membacanya [BG|1234|MT].
 *  3. Huruf besar setengah jalan. Jalur web tamu memeriksa prefix dalam huruf
 *     besar ke tabel master, lalu MENYIMPAN masukan aslinya — "b" lolos
 *     pemeriksaan lalu tersimpan sebagai "b" dan tak pernah cocok lagi.
 */
class PlatNomorKendaraan
{
    /**
     * Lebar kolom penyimpanan yang sebenarnya, per tabel tujuan.
     *
     * Keduanya memang berbeda di basis data — tabel tamu varchar(5), tabel
     * permit varchar(10) — untuk bagian yang secara logika sama. Angkanya
     * ditulis di sini apa adanya, bukan diseragamkan diam-diam: menyamakannya
     * adalah perubahan DDL dan bukan wewenang kode.
     */
    public const LEBAR_TAMU = 5;

    public const LEBAR_PERMIT = 10;

    /**
     * Bentuk kanonis plat Indonesia: 1-3 huruf, 1-4 angka, 0-3 huruf.
     *
     * Muat di kedua lebar kolom di atas, jadi plat yang cocok dengan pola ini
     * tidak pernah bisa terpotong di tabel mana pun.
     */
    private const POLA_KANONIS = '/^([A-Z]{1,3})(\d{1,4})([A-Z]{0,3})$/';

    /**
     * Memecah dan menormalkan plat menjadi tiga bagiannya.
     *
     * Masukan boleh datang sebagai satu string utuh, sebagai tiga bagian
     * terpisah, atau keduanya. Bila keduanya ada, TIGA BAGIAN yang menang:
     * itulah urutan yang sudah berlaku di layar permit, dan membaliknya
     * membuat formulir yang mengirim keduanya diam-diam menyimpan nilai lain.
     *
     * Kunci `kanonis` menjawab "apakah bentuknya plat Indonesia yang wajar",
     * BUKAN "apakah boleh disimpan". Keduanya sengaja dipisah: menolak setiap
     * bentuk tak kanonis akan menolak plat dinas dan plat khusus yang selama
     * ini tersimpan baik-baik saja. Yang berhak menolak adalah muat().
     *
     * @return array{prefix: ?string, angka: ?string, suffix: ?string, kanonis: bool}
     */
    public function normalkan(
        ?string $platTunggal = null,
        ?string $prefix = null,
        ?string $angka = null,
        ?string $suffix = null,
    ): array {
        $terpisahAda = $this->bersihkan($prefix) !== null
            || $this->bersihkan($angka) !== null
            || $this->bersihkan($suffix) !== null;

        $utuh = $terpisahAda
            ? ''
            : strtoupper(trim((string) preg_replace('/[^A-Za-z0-9]+/', ' ', (string) $platTunggal)));

        if ($utuh !== '') {
            if (preg_match(self::POLA_KANONIS, str_replace(' ', '', $utuh), $m)) {
                return [
                    'prefix' => $m[1],
                    'angka' => $m[2],
                    'suffix' => $m[3] !== '' ? $m[3] : null,
                    'kanonis' => true,
                ];
            }

            // Tidak kanonis, tetapi TIDAK dibuang. Plat yang tak terbaca lebih
            // baik tersimpan apa adanya lalu ditolak muat() dengan sebab yang
            // jelas, daripada lenyap tanpa galat.
            $bagian = explode(' ', $utuh);

            return [
                'prefix' => $bagian[0] ?? null,
                'angka' => $bagian[1] ?? null,
                'suffix' => isset($bagian[2]) ? implode('', array_slice($bagian, 2)) : null,
                'kanonis' => false,
            ];
        }

        $terpisah = [
            'prefix' => $this->bersihkan($prefix),
            'angka' => $this->bersihkan($angka),
            'suffix' => $this->bersihkan($suffix),
        ];

        // Tiga bagian terpisah pun diuji terhadap pola yang sama, supaya
        // "kanonis" berarti hal yang sama dari pintu mana pun ia masuk.
        $gabung = $terpisah['prefix'].$terpisah['angka'].$terpisah['suffix'];

        return $terpisah + [
            'kanonis' => $gabung !== '' && preg_match(self::POLA_KANONIS, $gabung) === 1,
        ];
    }

    /**
     * Apakah plat muat di kolom tujuannya.
     *
     * Mengembalikan nama bagian yang tidak muat, atau null bila aman. Inilah
     * satu-satunya penolakan yang berhak menghentikan penyimpanan: yang
     * dicegah bukan selera bentuk, melainkan galat "String or binary data
     * would be truncated" yang sampai ke pemakainya sebagai "gagal disimpan"
     * tanpa menyebut field mana.
     *
     * @param  array{prefix: ?string, angka: ?string, suffix: ?string}  $plat
     */
    public function muat(array $plat, int $lebar): ?string
    {
        foreach (['prefix', 'angka', 'suffix'] as $bagian) {
            if (strlen((string) ($plat[$bagian] ?? '')) > $lebar) {
                return $bagian;
            }
        }

        return null;
    }

    /**
     * Aturan validasi untuk tiga bagian plat, dipatok ke lebar kolom tujuan.
     *
     * Dipakai FormRequest maupun validate() inline supaya batasnya tidak
     * disalin-salin lalu menyimpang dari kolomnya — yang sudah pernah terjadi:
     * `max:10` terpasang di keempat pintu padahal kolom tamu varchar(5).
     *
     * @return array<string, array<int, string>>
     */
    public static function aturanValidasi(int $lebar = self::LEBAR_TAMU): array
    {
        return [
            'plat_prefix' => ['nullable', 'string', 'max:'.$lebar],
            'plat_angka' => ['nullable', 'string', 'max:'.$lebar],
            'plat_suffix' => ['nullable', 'string', 'max:'.$lebar],
        ];
    }

    /**
     * Bentuk lama, dipertahankan agar pemanggil yang ada tidak ikut berubah.
     *
     * @return array{prefix: ?string, angka: ?string, suffix: ?string}
     */
    public function pecah(?string $platTunggal, ?string $prefix = null, ?string $angka = null, ?string $suffix = null): array
    {
        $hasil = $this->normalkan($platTunggal, $prefix, $angka, $suffix);

        unset($hasil['kanonis']);

        return $hasil;
    }

    private function bersihkan(?string $nilai): ?string
    {
        $bersih = strtoupper(trim((string) $nilai));

        return $bersih !== '' ? $bersih : null;
    }
}
