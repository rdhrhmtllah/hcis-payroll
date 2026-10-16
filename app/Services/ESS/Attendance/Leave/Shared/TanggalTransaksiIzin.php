<?php

namespace App\Services\ESS\Attendance\Leave\Shared;

use App\Helpers\FormatTanggalHelper;
use Illuminate\Support\Facades\DB;

/**
 * Tanggal-tanggal detail sebuah transaksi izin, dikenali dari prefiks nomornya.
 *
 * ── Kenapa ini punya rumah ───────────────────────────────────────────────────
 *
 * getTanggal() tersalin di LIMA controller — AdminCutiController, CutiController,
 * IzinAtasanApprovalController, AbsensiController, uDashController — dan
 * kelimanya sudah MENYIMPANG bertiga arah:
 *
 *   AdminCuti + Cuti  : 3 prefiks, mengembalikan ['tanggal', 'jumlahHari']
 *   IzinAtasan        : 6 prefiks (+IP, TL, PC), mengembalikan $tanggal saja
 *   Absensi           : 3 prefiks, mengembalikan $tanggal saja
 *   uDash             : 3 prefiks, array, tetapi tanggalnya MENTAH — barisan
 *                       formatternya dikomentari karena layar itu membandingkan
 *                       tanggal, bukan menampilkannya
 *
 * Jadi satu nama metode, tiga bentuk balikan, dan dua peta prefiks berbeda.
 * Salinan yang paling lengkap petanya justru yang paling miskin balikannya.
 *
 * ── Peta prefiks di sini adalah gabungan keenamnya ───────────────────────────
 *
 * IP, TL, dan PC hanya dikenal oleh salinan IzinAtasan. Diperiksa di basis data
 * pengembangan: HRIS_Approval_Request tidak memuat satu pun baris berprefiks
 * itu hari ini, jadi menggabungkannya inert — tetapi ia menghapus pertanyaan
 * "kenapa satu layar mengenali dan empat lainnya tidak".
 *
 * ── Cacat kecil yang ikut ditutup ────────────────────────────────────────────
 *
 * Di keempat salinan yang mengembalikan array, $tanggal diinisialisasi null
 * tetapi $jumlahHari TIDAK. Pada prefiks di luar peta, barisnya membaca variabel
 * yang tak pernah ditulis — dan Laravel mengubah peringatan PHP menjadi
 * ErrorException, jadi yang terjadi bukan null melainkan lemparan yang ditelan
 * catch pemanggil, lalu seluruh daftarnya gagal.
 *
 * Tidak sedang menyala: view N_HRIS_VW_All_Transactions, sumber pemanggil
 * terbanyak, hanya memuat CT, IS, dan TP. Ia menyala pada jenis izin keempat
 * yang ditambahkan tanpa menyentuh kelima peta itu.
 */
class TanggalTransaksiIzin
{
    /**
     * prefiks No_Transaksi => [tabel detail, kolom tanggal].
     *
     * Petanya HARUS sejalan dengan jenis transaksi yang bisa muncul di layar
     * izin. Menambah jenis baru tanpa menambahkannya di sini membuat tanggalnya
     * hilang dari seluruh daftar — diam-diam, karena prefiks tak dikenal bukan
     * galat melainkan "tidak ada tanggal".
     */
    private const PETA = [
        'CT' => ['Transaksi_Cuti_Det', 'Tanggal_Cuti'],
        'IS' => ['Transaksi_Sakit_Izin_Det', 'Tanggal_Sakit_Izin'],
        'IP' => ['Transaksi_Sakit_Izin_Det', 'Tanggal_Sakit_Izin'],
        'TP' => ['Transaksi_Terlambat_Pulang_Cepat_Detail', 'Tanggal_Masuk_Pulang'],
        'TL' => ['Transaksi_Terlambat_Pulang_Cepat_Detail', 'Tanggal_Masuk_Pulang'],
        'PC' => ['Transaksi_Terlambat_Pulang_Cepat_Detail', 'Tanggal_Masuk_Pulang'],
    ];

    /**
     * @param  bool  $diformat  true  → 'tanggal' berupa teks siap tampil
     *                          false → 'tanggal' berupa larik tanggal mentah,
     *                          untuk pemanggil yang MEMBANDINGKAN
     *                          tanggal alih-alih menampilkannya
     *                          (uDashController). Perbedaan itu nyata
     *                          dan disengaja; ia jadi argumen supaya
     *                          terbaca, bukan tersembunyi di salinan.
     * @return array{tanggal: string|array|null, jumlahHari: int}
     */
    public static function ringkas(string $noTransaksi, bool $diformat = true): array
    {
        $prefix = substr($noTransaksi, 0, 2);

        if (! isset(self::PETA[$prefix])) {
            // jumlahHari SELALU terisi di sini — itu yang dulu terlewat.
            return ['tanggal' => null, 'jumlahHari' => 0];
        }

        [$tabel, $kolom] = self::PETA[$prefix];

        $det = DB::table($tabel)
            ->where('No_Transaksi', $noTransaksi)
            ->pluck($kolom)
            ->toArray();

        return [
            'tanggal' => $diformat ? FormatTanggalHelper::formatTanggalMultiple($det) : $det,
            'jumlahHari' => count($det),
        ];
    }

    /**
     * Teks tanggal siap tampil saja — bentuk yang dipakai IzinAtasanApproval
     * dan Absensi, yang memang tidak pernah memerlukan jumlah harinya.
     */
    public static function teks(string $noTransaksi): string|array|null
    {
        return self::ringkas($noTransaksi)['tanggal'];
    }
}
