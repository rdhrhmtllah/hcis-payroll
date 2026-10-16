<?php

namespace App\Services\VISITOR\Permit\VehiclePermit;

use Illuminate\Support\Facades\DB;

/**
 * Aturan yang berlaku HANYA bagi pengajuan permit dari aplikasi mitra B2B.
 *
 * Dua batas di bawah sengaja konstanta, bukan kolom per aplikasi: satu tempat
 * diubah, dan tidak ada keputusan yang harus diambil ulang tiap mitra baru.
 *
 * Kelas ini juga satu-satunya tempat PIC aplikasi di-resolve. Permit mitra
 * memakai PIC yang melekat pada pendaftaran aplikasinya, tidak pernah kode
 * karyawan yang dikirim pemanggil — kalau boleh dikirim, siapa pun yang memegang
 * kunci API bisa menggantungkan tanggung jawab izin gerbang pada karyawan mana
 * pun yang ia tebak kodenya.
 */
class VisitorPermitApiService
{
    /**
     * Awalan penanda pemohon yang BUKAN orang.
     *
     * Dipakai dua arah: dicetak di sini, lalu dikenali kembali oleh pihak yang
     * hendak memperlakukan pemohon sebagai manusia — notifikasi keputusan yang
     * mencari karyawan untuk dikirimi, dan registrasi tamu yang menuntut PIC
     * nyata. Nilainya dipusatkan karena selisih satu karakter antara yang
     * mencetak dan yang mengenali tidak menyalakan galat apa pun: penandanya
     * hanya berhenti dikenali, dan notifikasinya jatuh ke baris yatim.
     */
    public const PREFIX_PEMOHON_APLIKASI = 'API#';

    /**
     * Pengganti kuota per karyawan.
     *
     * Kuota permit karyawan dihitung per Kode_Karyawan_Pemohon dan dibatasi dua
     * pengajuan aktif. Seluruh permit satu mitra memakai satu PIC, jadi kuota
     * itu akan menolak permit ketiga dengan "jatah penuh" — batas per aplikasi
     * ini yang menggantikannya.
     */
    public const MAKS_PERMIT_AKTIF_PER_APLIKASI = 50;

    /**
     * Identitas pemohon permit yang diterbitkan aplikasi ini.
     *
     * Permit mitra TIDAK diajukan karyawan mana pun, jadi pemohonnya adalah
     * aplikasinya sendiri — kecuali pendaftarannya menunjuk seorang PIC.
     *
     * PIC bersifat OPSIONAL, dan itu keputusan sadar. Alur approval mengambil
     * approver dari N_HRIS_Approval_Config, bukan dari struktur organisasi
     * pemohon; kuota per karyawan sudah dilewati permit mitra; dan
     * Kode_Karyawan_Pemohon di tabel transaksi nullable tanpa FK. Satu-satunya
     * yang memaksa adanya nilai adalah proyeksi gerbang, yang menuntut kode dan
     * nama pemohon tidak kosong — dan keduanya bisa dipenuhi identitas aplikasi.
     *
     * Konsekuensi yang diterima sadar: tanpa PIC, notifikasi ke pemohon tidak
     * terkirim karena tidak ada karyawan untuk dikirimi. Itu tidak merugikan —
     * mitra memang dirancang mengetahui keputusan lewat endpoint status, bukan
     * lewat notifikasi HCIS.
     *
     * @return array{kode: string, nama: string}
     */
    public function identitasPemohon(int $idAplikasi): array
    {
        $app = DB::table('N_HRIS_Api_Aplikasi')
            ->where('Id_Api_Aplikasi', $idAplikasi)
            ->first(['Nama_Aplikasi', 'Kode_Karyawan_PIC']);

        if (! $app) {
            throw new \DomainException("Aplikasi API {$idAplikasi} tidak ditemukan.");
        }

        $pic = trim((string) ($app->Kode_Karyawan_PIC ?? ''));

        if ($pic !== '') {
            return ['kode' => $pic, 'nama' => (string) $app->Nama_Aplikasi];
        }

        // Penanda stabil, bukan kode karyawan. Sengaja tidak menyerupai kode
        // karyawan mana pun supaya pembaca laporan langsung tahu izin ini tidak
        // diajukan orang. Kolomnya varchar(20) di proyeksi; 'API#' + id muat
        // jauh di bawahnya.
        return [
            'kode' => self::PREFIX_PEMOHON_APLIKASI.$idAplikasi,
            'nama' => (string) $app->Nama_Aplikasi,
        ];
    }

    /**
     * Jumlah permit aplikasi ini yang masih menahan jatah.
     *
     * Dihitung dari status kanonis (permitStatusSql), sama dengan
     * VisitorPermitQuotaService::STATUS_MEMAKAI_KUOTA. Dulu dari Flag_Approval
     * mentah: permit yang disetujui tetap 'Y' selamanya, jadi yang sudah
     * kedaluwarsa, dicabut, atau dibatalkan lewat /batal terus menahan jatah —
     * dan mitra akhirnya ditolak 429 permanen (audit ulang 28 Sep).
     */
    public function permitAktif(int $idAplikasi): int
    {
        $status = VisitorPermitQuotaService::STATUS_MEMAKAI_KUOTA;
        $isian = implode(',', array_fill(0, count($status), '?'));

        return DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
            ->leftJoin('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('h.Id_Api_Aplikasi', $idAplikasi)
            ->where('h.Flag_Aktif', 'Y')
            // Jatah ini membatasi endpoint /permit-tamu, bukan registrasi tamu.
            // Permit COKLAT yang lahir dari registrasi tamu B2B ikut membawa
            // Id_Api_Aplikasi mitranya; tanpa pengecualian ini, mitra yang
            // mendaftarkan dua tamu mendadak tidak bisa lagi mengajukan permit
            // sama sekali — dan pesan galatnya bicara soal batas permit, bukan
            // soal tamu.
            ->whereNull('h.No_Transaksi_Tamu')
            ->whereRaw(app(VisitorPermitQueryService::class)->permitStatusSql()." IN ({$isian})", $status)
            ->distinct()
            ->count('h.No_Transaksi');
    }

    public function melampauiBatas(int $idAplikasi): bool
    {
        return $this->permitAktif($idAplikasi) >= self::MAKS_PERMIT_AKTIF_PER_APLIKASI;
    }
}
