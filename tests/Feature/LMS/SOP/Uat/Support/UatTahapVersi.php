<?php

namespace Tests\Feature\LMS\SOP\Uat\Support;

use App\Services\LMS\SOP\SopTahapVersiService;

/**
 * Membawa sebuah versi dokumen dari Drafting sampai AMBANG TERBIT.
 *
 * KENAPA HELPER INI ADA. Sampai pertengahan 2026 sebuah versi bisa diterbitkan
 * langsung dari Drafting: terbitkanVersi() hanya menuntut berkas dan pengesah,
 * dan tak pernah bertanya versinya sedang berada di tahap apa. Puluhan uji
 * ditulis di atas kebiasaan itu — buat dokumen, tambah versi, terbitkan.
 *
 * Dua DML resmi mengubah alurnya, dan keduanya disengaja:
 *
 *   DML-2026-048  APPROVED dihidupkan kembali sebagai langkah PEMBERKASAN yang
 *                 berdiri sendiri (langkah 4). Penerbitan tak lagi boleh
 *                 dilompati dari Pengesahan, sebab di Pemberkasan itulah berkas
 *                 finalnya dipasang.
 *   DML-2026-051  FILING_REGISTERED menjadi langkah TINJAUAN AKHIR (langkah 5).
 *
 * Alur yang berlaku sekarang, dibaca dari N_SOP_Master_Tahap_Versi:
 *
 *   DRAFTING(1) → REVIEW(2) → APPROVAL(3) → APPROVED(4) → FILING_REGISTERED(5)
 *
 * dan terbitkanVersi() menolak versi yang tahapnya belum mengarah ke
 * FILING_REGISTERED dengan kalimat "Versi ini belum boleh diterbitkan dari
 * tahap ...". Penjaga itu BENAR; yang usang adalah uji-uji yang menerbitkan
 * dari Drafting. Helper ini menutup selisihnya di satu tempat, bukan di tujuh
 * berkas uji yang masing-masing menyalin urutan tahapnya sendiri.
 *
 * YANG SENGAJA TIDAK DILAKUKAN HELPER INI: ia tidak MENGARANG apa pun demi
 * memenuhi syarat sebuah tahap. Tak ada berkas yang dipasangkan, tak ada
 * pengesah yang disisipkan. Alasannya bukan kerapian melainkan kebenaran uji —
 * beberapa skenario (UAT-SOP-013) justru menguji bahwa versi TANPA pengesah
 * atau TANPA berkas ditolak terbit. Helper yang melengkapi kekurangan itu
 * diam-diam akan membuat penjaga yang sedang diuji tak pernah menyala, dan
 * ujinya berubah menjadi hijau yang tak menguji apa pun.
 *
 * Karena itu ia berhenti — tanpa melempar — begitu langkah berikutnya
 * terhalang syaratnya sendiri. Versi ditinggalkan di tahap terjauh yang sah,
 * dan penjaga yang seharusnya menyala tetap menyala di tempatnya.
 */
class UatTahapVersi
{
    /**
     * Batas jumlah perpindahan.
     *
     * Alur nyata hanya butuh tiga langkah (Review → Pengesahan → Pemberkasan).
     * Batasnya dipasang lebih longgar supaya penambahan tahap di master tidak
     * langsung mematahkan helper, tetapi tetap ada: alur tersimpan sebagai
     * DATA, dan data yang salah sunting bisa membentuk lingkaran — perulangan
     * tak berbatas di dalam uji akan menahan koneksi database dev sampai
     * habis, dan RCSI yang mati membuat pemakai lain ikut antre di belakangnya.
     */
    private const BATAS_LANGKAH = 10;

    /**
     * Jalankan versi maju sejauh syaratnya mengizinkan, lalu berhenti di ambang
     * terbit.
     *
     * "Ambang terbit" = tahap yang daftar tujuannya memuat FILING_REGISTERED,
     * yaitu tepat kondisi yang diperiksa SopDokumenService::terbitkanVersi().
     * Diperiksa lewat daftar tujuan, bukan dengan membandingkan kode tahap
     * dengan 'APPROVED', supaya helper ini tak menjadi salinan kedua dari alur
     * yang justru sengaja disimpan sebagai data.
     *
     * @return string kode tahap terakhir yang berhasil dicapai
     */
    public static function bawaKeAmbangTerbit(int $idVersi, int $userId): string
    {
        $svc = app(SopTahapVersiService::class);

        for ($i = 0; $i < self::BATAS_LANGKAH; $i++) {
            /* Nilai syarat di-cache per instance service. Satu proses uji yang
               mengisi pengesah lalu langsung berpindah tahap akan diperiksa
               terhadap jawaban dari SEBELUM isiannya masuk — persis cacat yang
               ditemukan sop:seed-demo pada 2026-08-27. */
            $svc->lupakanNilai($idVersi);

            $tujuan = $svc->tujuanSah($idVersi);

            /* Sudah di ambang terbit: berhenti. Melangkah lagi berarti menempuh
               FILING_REGISTERED lewat pemindahan tahap, dan itu ditolak — di
               sana pointer dokumen, versi lama, nomor resmi, dan paket perujuk
               ikut bergerak, jadi jalurnya hanya satu: terbitkanVersi(). */
            foreach ($tujuan as $t) {
                if ($t['Kode_Tahap'] === 'FILING_REGISTERED') {
                    return (string) ($svc->tahapVersi($idVersi)->Kode_Tahap ?? '');
                }
            }

            $maju = null;
            foreach ($tujuan as $t) {
                if (($t['Aksi'] ?? null) === 'MAJU') {
                    $maju = $t;
                    break;
                }
            }

            // Tahap akhir, atau tak ada jalan maju: tak ada lagi yang bisa ditempuh.
            if ($maju === null) {
                break;
            }

            /* Syarat diperiksa DI SINI, bukan dibiarkan meledak sebagai
               DomainException di dalam pindah(). Kekurangan syarat adalah
               keadaan yang memang ingin diuji sebagian skenario; melemparnya
               dari dalam fixture akan mengubah "penjaga menolak terbit" menjadi
               "penyiapan uji gagal", dan sebabnya tak lagi terbaca dari pesan
               kegagalannya. */
            foreach ($maju['Syarat']['daftar'] ?? [] as $syarat) {
                if (! ($syarat['terpenuhi'] ?? false)) {
                    return (string) ($svc->tahapVersi($idVersi)->Kode_Tahap ?? '');
                }
            }

            $svc->pindah($idVersi, $maju['Kode_Tahap'], $userId);
        }

        return (string) ($svc->tahapVersi($idVersi)->Kode_Tahap ?? '');
    }
}
