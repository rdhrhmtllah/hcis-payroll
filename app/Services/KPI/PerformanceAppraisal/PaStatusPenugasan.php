<?php

namespace App\Services\KPI\PerformanceAppraisal;

use InvalidArgumentException;

/**
 * Arti status penugasan, di satu tempat.
 *
 * Sebelum kelas ini ada, arti "sudah dikerjakan" diketik ulang di
 * PaAdminDashboardService, PaLeaderService, dan PaNotificationService. Tiga
 * salinan berarti tiga kesempatan untuk menyimpang, dan menyimpangnya tidak
 * menggagalkan apa pun — ia hanya membuat dua halaman melaporkan angka kemajuan
 * berbeda untuk siklus yang sama, tanpa cara mengetahui mana yang benar.
 *
 * DUA DEFINISI "TERLAMBAT" — SENGAJA, BUKAN LUPA DISATUKAN
 *
 * Ada DUA himpunan status yang dibebaskan dari "terlambat", dan keduanya BENAR untuk
 * fiturnya masing-masing:
 *
 * - `TIDAK_DIHITUNG_TERLAMBAT` (longgar) — mengukur keterlambatan MENGISI. Begitu
 *   lembarnya terisi (TERKUMPUL) atau tinggal menunggu pengesah HC Learn
 *   (MENUNGGU_VERIFIKASI), penilainya dianggap SUDAH bekerja dan tidak lagi dikejar.
 *   Dipakai oleh progres per divisi di dashboard (`PaAdminDashboardService::
 *   progresPerDivisi()`), `PaLeaderService`, dan saringan pengingat
 *   `PaNotificationService` — jadi pengingat otomatis HANYA dikirim ke BELUM_MULAI dan
 *   BERJALAN.
 * - `TIDAK_DIHITUNG_TERLAMBAT_KETAT` (ketat) — hanya SELESAI dan DIBATALKAN yang
 *   dibebaskan. TERKUMPUL dan MENUNGGU_VERIFIKASI TETAP dihitung terlambat, karena
 *   dipakai oleh daftar/keranjang tindak lanjut administratif, tempat yang dianggap
 *   "tuntas" hanya yang benar-benar SELESAI atau DIBATALKAN — pengisian yang belum
 *   final (baru terkumpul atau baru menunggu pengesah) masih pantas muncul di daftar
 *   itu. Dipakai oleh `PaAdminDashboardService::syaratTerlambat()`/`sqlTerlambat()`
 *   (`cacahTerlambat()`, `kueriTerlambat()`, dan — sejak koreksi tahap Pengisian — kartu
 *   "Lewat Tenggat" `ringkasanPengisian()`, supaya angka kartunya sama dengan isi daftar
 *   saat diklik) dan `PaParticipantReadService::keterlambatan()`. Karena itu kartu
 *   tersebut TIDAK sama dengan jumlah penerima pengingat; teks kartunya menyebut
 *   "lewat tenggat, belum selesai", bukan "perlu pengingat".
 *
 * TERTUKAR AKIBATNYA: pakai KETAT di tempat yang harusnya longgar membuat orang yang
 * lembarnya sudah terisi (tinggal menunggu kirim/pengesahan) ikut dikejar sebagai
 * terlambat mengisi — lencana yang salah alamat. Pakai LONGGAR di tempat yang
 * harusnya ketat membuat daftar tindak lanjut kehilangan orang yang secara
 * administratif belum tuntas, padahal itulah yang seharusnya masih ditagih.
 *
 * `PaPesertaService::keterlambatan()` memakai daftar KETIGA, `['SELESAI', 'TERKUMPUL',
 * 'DIBATALKAN']`, dan SENGAJA belum dialihkan ke salah satu konstanta di atas —
 * bukan lupa. Daftar itu bukan subset dari `TIDAK_DIHITUNG_TERLAMBAT` (tidak ikut
 * membebaskan MENUNGGU_VERIFIKASI, yang daftar itu tidak punya) maupun dari
 * `TIDAK_DIHITUNG_TERLAMBAT_KETAT` (justru membebaskan TERKUMPUL, yang KETAT tidak).
 * Memaksakannya ke salah satu berarti mengubah perilaku `PaPesertaService` tanpa
 * dasar mana yang benar. Keputusan mana yang seharusnya dipakai di sana — KETAT,
 * LONGGAR, atau definisi keempat bernama sendiri — menunggu pemilik proyek.
 */
final class PaStatusPenugasan
{
    public const BELUM_MULAI = 'BELUM_MULAI';

    public const BERJALAN = 'BERJALAN';

    public const MENUNGGU_VERIFIKASI = 'MENUNGGU_VERIFIKASI';

    public const TERKUMPUL = 'TERKUMPUL';

    public const SELESAI = 'SELESAI';

    public const DIBATALKAN = 'DIBATALKAN';

    /** Seluruh nilai yang sah. Dipakai sebagai kamus oleh sqlDaftar(). */
    public const SEMUA = [
        self::BELUM_MULAI, self::BERJALAN, self::MENUNGGU_VERIFIKASI,
        self::TERKUMPUL, self::SELESAI, self::DIBATALKAN,
    ];

    /**
     * TUNTAS — pekerjaannya benar-benar selesai dan boleh ikut menghasilkan nilai.
     * TERKUMPUL sengaja TIDAK di sini.
     */
    public const TUNTAS = [self::SELESAI];

    /**
     * SUDAH_DIKERJAKAN — penilainya sudah mengisi lembarnya, apa pun yang masih
     * ditunggu sesudahnya. Inilah pembilang angka kemajuan pengisian.
     */
    public const SUDAH_DIKERJAKAN = [self::SELESAI, self::TERKUMPUL, self::MENUNGGU_VERIFIKASI];

    /**
     * TIDAK_DIHITUNG_TERLAMBAT — status yang tidak boleh dihitung terlambat
     * meskipun Due_At sudah lewat. DIBATALKAN ikut di sini karena penugasan yang
     * dicabut bukan keterlambatan siapa pun.
     */
    public const TIDAK_DIHITUNG_TERLAMBAT = [
        self::SELESAI, self::TERKUMPUL, self::MENUNGGU_VERIFIKASI, self::DIBATALKAN,
    ];

    /**
     * TIDAK_DIHITUNG_TERLAMBAT_KETAT — versi LEBIH KERAS dari daftar di atas: hanya
     * SELESAI dan DIBATALKAN yang dibebaskan dari "terlambat". TERKUMPUL dan
     * MENUNGGU_VERIFIKASI SENGAJA TIDAK di sini — untuk daftar/keranjang tindak lanjut
     * administratif, pengisian yang belum final masih pantas ditagih. Lihat docblock
     * kelas ini untuk penjelasan kenapa dua definisi ini hidup berdampingan.
     */
    public const TIDAK_DIHITUNG_TERLAMBAT_KETAT = [self::SELESAI, self::DIBATALKAN];

    /**
     * Merangkai daftar status menjadi potongan SQL `'A','B'`.
     *
     * Nilainya diperiksa terhadap kamus lebih dulu: status dirangkai ke dalam SQL
     * mentah, jadi satu salah ketik akan menjadi SQL dan salahnya tidak akan
     * terbaca sebagai salah ketik.
     *
     * Larik kosong DITOLAK, bukan dibiarkan menghasilkan string kosong. Pemanggil
     * yang merangkainya jadi `Status_Penugasan NOT IN ()` akan mengirim SQL tak sah
     * ke SQL Server — kegagalannya baru kentara saat kueri itu dieksekusi, bukan di
     * sini tempat penyebabnya jelas.
     *
     * @param  list<string>  $status
     */
    public static function sqlDaftar(array $status): string
    {
        if ($status === []) {
            throw new InvalidArgumentException(
                'Daftar status tidak boleh kosong: hasilnya akan menjadi SQL tak sah seperti "NOT IN ()".'
            );
        }

        foreach ($status as $s) {
            if (! in_array($s, self::SEMUA, true)) {
                throw new InvalidArgumentException("Status penugasan tidak dikenal: '{$s}'.");
            }
        }

        return implode(',', array_map(fn ($s) => "'{$s}'", $status));
    }
}
