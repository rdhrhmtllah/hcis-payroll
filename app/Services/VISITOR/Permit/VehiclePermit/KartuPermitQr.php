<?php

namespace App\Services\VISITOR\Permit\VehiclePermit;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\DB;

/**
 * Kode keaslian pada kartu permit yang dicetak (DDL-2026-127).
 *
 * QR berisi SATU kode acak 32 heksadesimal (16 byte = 128 bit) milik kartu
 * itu sendiri — bukan tautan, bukan nomor permit, dan tidak memuat apa pun
 * yang bisa dibaca orang yang memindainya dengan kamera biasa.
 *
 * Bentuk sebelumnya memuat nomor permit apa adanya + tanda tangan HMAC
 * APP_KEY. Dua akibat yang dihilangkan di sini:
 *
 * 1. Nomor internal permit ikut tercetak di kartu yang ditempel di dashboard.
 * 2. Seluruh kartu yang sudah tercetak mati serentak begitu APP_KEY dirotasi.
 *
 * Yang menjaga kartu dari pemalsuan:
 *
 * - Kodenya acak kriptografis dan hanya ada di basis data. Menebaknya berarti
 *   menembak 128 bit; tidak ada pola yang bisa diturunkan dari kartu lain,
 *   sebab tidak ada rumus yang menghubungkan kode dengan nomor permitnya.
 * - Pemeriksanya membaca keadaan permit TERKINI. Kartu asli yang sudah dicabut
 *   tetap terbaca "tidak berlaku" walau cetakannya mulus.
 * - Kode satu kartu bisa diputar ulang (rotasi) tanpa menyentuh kartu lain.
 */
class KartuPermitQr
{
    private const TABEL = 'N_EMI_VISITOR_Transaksi_Permit';

    /**
     * Kode kartu milik satu permit; dibuat sekali lalu dipakai seterusnya.
     *
     * Dibuat saat dibutuhkan, bukan saat permit lahir: permit yang tak pernah
     * dicetak kartunya tidak perlu menyandang kode apa pun.
     */
    public function kode(int $idTransaksiPermit): string
    {
        $kode = DB::table(self::TABEL)
            ->where('Id_Transaksi_Permit', $idTransaksiPermit)
            ->value('Kode_Kartu');

        if (filled($kode)) {
            return (string) $kode;
        }

        // Cetak pertama yang berlomba (tampil + unduh, klik ganda): hanya satu
        // yang boleh menulis, yang lain memakai kode pemenang. Tanpa penjaga
        // IS NULL, kartu dari cetakan pertama ditimpa dan gagal diverifikasi.
        DB::table(self::TABEL)
            ->where('Id_Transaksi_Permit', $idTransaksiPermit)
            ->where(fn ($q) => $q->whereNull('Kode_Kartu')->orWhere('Kode_Kartu', ''))
            ->update(['Kode_Kartu' => bin2hex(random_bytes(16))]);

        return (string) DB::table(self::TABEL)
            ->where('Id_Transaksi_Permit', $idTransaksiPermit)
            ->value('Kode_Kartu');
    }

    /**
     * Terbitkan kode baru untuk satu kartu. Kartu lama yang sudah tercetak
     * otomatis tidak dikenali lagi — itulah gunanya saat sebuah kode bocor.
     */
    public function putar(int $idTransaksiPermit): string
    {
        // random_bytes: CSPRNG. rand()/uniqid() bisa ditebak dari keluaran
        // sebelumnya, dan kode yang bisa ditebak membuat seluruh kartu palsu
        // bisa dibuat tanpa menyentuh HCIS sama sekali.
        $kode = bin2hex(random_bytes(16));

        DB::table(self::TABEL)
            ->where('Id_Transaksi_Permit', $idTransaksiPermit)
            ->update(['Kode_Kartu' => $kode]);

        return $kode;
    }

    /**
     * Baca Id permit dari kode kartu, atau null bila tidak dikenali.
     *
     * Bentuk yang salah ditolak sebelum menyentuh basis data: kolom yang
     * dicari berindeks unik, dan membiarkan masukan bebas mengetuknya setiap
     * kali pemindaian gagal hanya membuang kueri.
     */
    public function bacaId(string $kode): ?int
    {
        $bersih = strtolower(self::kodeDariIsiQr($kode));

        if (! preg_match('/^[0-9a-f]{32}$/', $bersih)) {
            return null;
        }

        $id = DB::table(self::TABEL)
            ->whereRaw('LOWER(Kode_Kartu) = ?', [$bersih])
            ->value('Id_Transaksi_Permit');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Isi QR: URL halaman cek kartu PUBLIK bila dikonfigurasi, kode polos bila
     * tidak. Alamatnya milik layanan terpisah (bukan domain HCIS), jadi domain
     * HCIS tidak pernah tercetak di kartu.
     */
    public static function isiQr(string $kode): string
    {
        $dasar = rtrim((string) config('services.visitor.kartu_publik_url', ''), '/');

        return $dasar === '' ? $kode : $dasar.'/'.$kode;
    }

    /**
     * Kode kartu dari isi QR apa pun bentuknya: kode polos (kartu lama) atau URL
     * halaman cek publik (kartu baru). Pemindai di dalam HCIS menerima keduanya.
     */
    public static function kodeDariIsiQr(string $isi): string
    {
        $isi = trim($isi);

        if (str_contains($isi, '/')) {
            $jalur = (string) (parse_url($isi, PHP_URL_PATH) ?? $isi);
            $isi = (string) last(array_filter(explode('/', $jalur), 'strlen'));
        }

        return $isi;
    }

    /** QR kartu sebagai data URI PNG untuk ditanam di PDF. */
    public function dataUri(int $idTransaksiPermit): string
    {
        $qr = new QRCode(new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            // Kartu ditempel di dashboard: kena panas, debu, dan lipatan.
            // Koreksi galat tinggi menjaga QR tetap terbaca meski kotor.
            'eccLevel' => EccLevel::H,
            'scale' => 6,
            'outputBase64' => true,
            'quietzoneSize' => 0,
        ]));

        return $qr->render(self::isiQr($this->kode($idTransaksiPermit)));
    }
}
