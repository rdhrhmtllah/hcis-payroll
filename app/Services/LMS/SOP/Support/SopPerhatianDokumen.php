<?php

namespace App\Services\LMS\SOP\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Dua daftar kerja Master Dokumen — dan garis yang memisahkannya.
 *
 * PERLU DITINJAU adalah JAM YANG BERDETAK: dokumen yang sedang mengikat
 * karyawan dan tanggalnya menagih — masa berlaku atau jadwal review sudah lewat,
 * atau tinggal hitungan minggu. Terlambat berarti orang membaca dokumen yang
 * sudah kedaluwarsa.
 *
 * BELUM LENGKAP adalah PEKERJAAN YANG MENGGANTUNG: dokumen yang belum punya
 * versi berlaku sama sekali. Tak ada tenggat, tak ada yang rusak bila dibiarkan
 * sebulan, dan populasinya memang akan selalu besar — mendeklarasikan SOP adalah
 * langkah PERTAMA alur, jadi tumpukan draft itu keadaan normal, bukan gejala.
 *
 * KENAPA DIPISAH. Digabung, lima belas draft akan menenggelamkan satu dokumen
 * yang benar-benar mau kedaluwarsa — kebalikan persis dari gunanya daftar ini.
 *
 * SATU PINTU. Penyaring daftar dan penghitung pilnya membaca ambang dan
 * predikat yang SAMA dari sini. Menuliskannya dua kali adalah cara pil
 * mengatakan "3" sementara daftarnya menampilkan lima baris — dan admin lalu
 * berhenti memercayai keduanya.
 */
class SopPerhatianDokumen
{
    /**
     * Seberapa awal sebuah tanggal mulai menagih.
     *
     * Satu kuartal, dan angkanya bukan selera: itu waktu yang realistis untuk
     * menjadwalkan review, menyiapkan revisi, dan mengejar tanda tangan
     * pengesah. Lebih pendek dan dokumennya terlanjur lewat masa berlaku
     * sebelum penggantinya terbit; lebih panjang dan pilnya menyala terus untuk
     * dokumen yang belum perlu disentuh, lalu berhenti dibaca.
     */
    public const AMBANG_HARI = 90;

    /** Tanggal terjauh yang masih dianggap "sebentar lagi". */
    public static function batas(): string
    {
        return now()->addDays(self::AMBANG_HARI)->toDateString();
    }

    /**
     * Dokumen berlaku yang tanggalnya menagih.
     *
     * Keduanya dibaca dari versi yang SEDANG berlaku, bukan dari versi mana pun
     * — jadwal review milik revisi lama tak lagi menagih siapa pun.
     */
    public static function terapkanTinjau(Builder $q, string $alias = 'd'): Builder
    {
        $batas = self::batas();

        return $q->whereNotNull($alias.'.Id_Versi_Aktif')
            ->whereExists(fn ($s) => $s->select(DB::raw(1))->from('N_SOP_Item_Versi as vt')
                ->whereColumn('vt.Id_SOP_Item_Versi', $alias.'.Id_Versi_Aktif')
                ->where(fn ($x) => $x
                    ->where('vt.Tanggal_Review_Berikutnya', '<=', $batas)
                    ->orWhere('vt.Tanggal_Kadaluarsa', '<=', $batas)));
    }

    /** Dokumen yang belum punya versi berlaku — baik belum berversi maupun masih draft. */
    public static function terapkanBelumLengkap(Builder $q, string $alias = 'd'): Builder
    {
        return $q->whereNull($alias.'.Id_Versi_Aktif');
    }

    /**
     * Penilaian per baris, untuk lencana di daftarnya.
     *
     * TERLAMBAT dipisah dari SEGERA karena tindakannya berbeda: yang satu
     * dokumen yang MASIH disajikan padahal sudah lewat, yang satu pekerjaan
     * yang masih punya waktu. Menyeragamkan keduanya jadi satu warna membuat
     * yang mendesak tak terlihat di antara yang belum mendesak.
     *
     * @return array{Keadaan: ?string, Sebab: ?string, Tanggal: ?string}
     */
    public static function nilai(?string $review, ?string $kadaluarsa): array
    {
        $kosong = ['Keadaan' => null, 'Sebab' => null, 'Tanggal' => null];

        $hariIni = now()->toDateString();
        $batas = self::batas();

        /* KADALUARSA DIPERIKSA LEBIH DULU. Bila keduanya menagih, yang disebut
           adalah yang akibatnya lebih berat: dokumen yang lewat masa berlaku
           tak sah dipakai, sedangkan review yang telat "hanya" belum ditinjau. */
        foreach ([['KADALUARSA', $kadaluarsa], ['REVIEW', $review]] as [$sebab, $tgl]) {
            if ($tgl === null || $tgl === '') {
                continue;
            }
            $tgl = substr($tgl, 0, 10);
            if ($tgl < $hariIni) {
                return ['Keadaan' => 'TERLAMBAT', 'Sebab' => $sebab, 'Tanggal' => $tgl];
            }
        }

        /* Yang belum lewat: disebut yang PALING DEKAT, bukan yang paling
           berat. Di sini pertanyaannya "apa yang harus saya kerjakan lebih
           dulu", dan jawabannya selalu tanggal terdekat — biasanya review,
           yang memang dirancang jatuh sebelum masa berlakunya habis. */
        $dekat = $kosong;
        foreach ([['KADALUARSA', $kadaluarsa], ['REVIEW', $review]] as [$sebab, $tgl]) {
            if ($tgl === null || $tgl === '') {
                continue;
            }
            $tgl = substr($tgl, 0, 10);
            if ($tgl <= $batas && ($dekat['Tanggal'] === null || $tgl < $dekat['Tanggal'])) {
                $dekat = ['Keadaan' => 'SEGERA', 'Sebab' => $sebab, 'Tanggal' => $tgl];
            }
        }

        return $dekat;
    }
}
