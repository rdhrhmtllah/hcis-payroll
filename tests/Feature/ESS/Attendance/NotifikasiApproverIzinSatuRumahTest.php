<?php

namespace Tests\Feature\ESS\Attendance;

use Tests\TestCase;

/**
 * Notifikasi WhatsApp ke approver pertama punya SATU rumah, berikut penjaganya.
 *
 * ── Cacat yang ditemukan saat menyatukannya ──────────────────────────────────
 *
 * Blok ini tersalin TIGA KALI di metode pengajuan izin — sekali untuk
 * sakit/izin, sekali untuk cuti, sekali untuk terlambat/pulang cepat. Salinan
 * sakit/izin menulis:
 *
 *     if (tidak ada nomor HP) { catat } else { susun pesan; kirim }
 *
 * Dua salinan lainnya kehilangan `else`-nya:
 *
 *     if (tidak ada nomor HP) { catat }
 *     susun pesan; kirim          <- tetap jalan
 *
 * Jadi untuk pengajuan CUTI dan TERLAMBAT/PULANG CEPAT, ketika approver tidak
 * punya nomor HP, sistem mencatat "tidak ada nomor HP" lalu tetap mengirim
 * WhatsApp ke tujuan kosong — dan bila API-nya menjawab apa pun, angka_wa ikut
 * dinaikkan seolah pesannya sampai.
 *
 * Terukur di basis data pengembangan: 0 dari 958 karyawan aktif tanpa nomor HP,
 * jadi cabang itu LATEN. Ia menyala pada karyawan pertama yang masuk tanpa
 * nomor HP — dan besaran dev bukan gambaran produksi.
 *
 * Uji ini bersifat tekstual karena rumahnya menyentuh basis data. Yang dijaga
 * bukan gaya kode melainkan keberadaan `return` itu: tanpa dia, pemeriksaan
 * nomor HP cuma menulis catatan lalu membiarkan pengirimannya jalan terus.
 */
class NotifikasiApproverIzinSatuRumahTest extends TestCase
{
    private const CONTROLLER = 'app/Http/Controllers/ESS/Attendance/Legacy/AbsensiController.php';

    private function isi(): string
    {
        return (string) file_get_contents(base_path(self::CONTROLLER));
    }

    private function rumah(string $isi): string
    {
        $m = [];
        $cocok = preg_match(
            '/\n[ \t]*private function notifikasiApproverIzin\s*\(/',
            $isi,
            $m,
            PREG_OFFSET_CAPTURE,
        );
        $this->assertSame(1, $cocok, 'notifikasiApproverIzin() hilang.');

        $buka = strpos($isi, '{', strpos($isi, '): void', $m[0][1]));
        $dalam = 0;
        for ($i = $buka; $i < strlen($isi); $i++) {
            if ($isi[$i] === '{') {
                $dalam++;
            } elseif ($isi[$i] === '}') {
                $dalam--;
                if ($dalam === 0) {
                    return substr($isi, $m[0][1], $i - $m[0][1] + 1);
                }
            }
        }

        $this->fail('Kurung notifikasiApproverIzin() tidak seimbang.');
    }

    public function test_penjaga_tanpa_nomor_hp_menghentikan_pengiriman(): void
    {
        /*
         * INTI PR INI. Yang dicari bukan "ada pemeriksaan nomor HP" — itu ada di
         * ketiga salinan lama juga, dan dua di antaranya tetap mengirim. Yang
         * dicari adalah cabang itu KELUAR dari metode sebelum pesannya disusun.
         */
        $rumah = $this->rumah($this->isi());

        $posIf = strpos($rumah, 'if (! $userInsertNoHp');
        $this->assertNotFalse($posIf, 'Pemeriksaan nomor HP hilang sama sekali.');

        $posPesan = strpos($rumah, '$pesan = [');
        $this->assertNotFalse($posPesan, 'Muatan WhatsApp hilang.');
        $this->assertGreaterThan($posIf, $posPesan, 'Muatan disusun sebelum diperiksa.');

        $antara = substr($rumah, $posIf, $posPesan - $posIf);

        $this->assertMatchesRegularExpression(
            '/\breturn;/',
            $antara,
            'Cabang "tidak ada nomor HP" tidak keluar dari metode. Itu persis cacat '.
            'yang diperbaiki: dua dari tiga salinan lama mencatat peringatannya lalu '.
            'tetap mengirim WhatsApp ke tujuan kosong, dan menaikkan angka_wa.',
        );
    }

    public function test_ketiga_jenis_pengajuan_memakai_rumah_yang_sama(): void
    {
        $isi = $this->isi();

        $this->assertSame(
            3,
            substr_count($isi, '$this->notifikasiApproverIzin('),
            'Ketiga jenis pengajuan (sakit/izin, cuti, terlambat/pulang cepat) harus '.
            'memanggil rumah yang sama.',
        );

        $this->assertSame(
            1,
            substr_count($isi, "DB::table('HRIS_Approval_flow as a')"),
            'Kueri approver pertama kembali tersalin. Dulu ada tiga, dan justru di '.
            'salinan itulah penjaganya hilang.',
        );
    }

    public function test_muatan_approver_tinggal_dua_dan_yang_kedua_konteks_lain(): void
    {
        /*
         * Template notif_approver_hcis dipakai di DUA konteks berbeda: approver
         * pertama saat pengajuan (rumah ini) dan approver BERIKUTNYA saat rantai
         * approval berjalan. Yang kedua sengaja tidak ikut disatukan — datanya
         * berasal dari $nextFlow, bukan dari pengajuan yang baru masuk.
         *
         * Dijaga jumlahnya supaya salinan ketiga tidak diam-diam lahir lagi.
         */
        $isi = $this->isi();

        $this->assertSame(
            2,
            substr_count($isi, "'notif_approver_hcis'"),
            'Jumlah pemakai template approver berubah. Seharusnya dua: rumah bersama '.
            'untuk approver pertama, dan jalur approver berikutnya yang konteksnya beda.',
        );
    }

    /*
     * Dua uji sebelumnya di tempat ini menjaga BENTUK lama pengiriman:
     * `if ($response)` sebelum kenaikan `angka_wa`, dan `DB::afterCommit(`
     * sebelum `Whatsapp::send_message(`. Keduanya dicabut ketika rumah ini
     * dipindah ke antrean, dan alasannya dicatat di sini supaya pencabutan itu
     * bisa diperiksa ulang, bukan sekadar dipercaya:
     *
     *  - PENUNDAAN tidak hilang, ia menguat. SendWhatsappJob memanggil
     *    afterCommit() di konstruktornya, jadi dispatch tetap menunggu commit
     *    DAN HTTP-nya pindah ke worker — bukan lagi di dalam permintaan.
     *    Penggantinya ada di bawah: pengiriman langsung DILARANG di rumah ini.
     *
     *  - angka_wa dilepas. Kolom itu ditulis di sepuluh tempat dan TIDAK PERNAH
     *    dibaca: satu-satunya pembacaan di repo sudah dikomentari
     *    (ExpiredIzin.php `// ->where("angka_wa", '>', 2)`). WhatsappLogHelper
     *    sudah lebih dulu melepas callback yang mengurusnya untuk seluruh modul
     *    lain, jadi mempertahankannya di sini berarti izin sendirian memikul
     *    penghitung yang sudah ditinggalkan. Bila kelak angka itu dibutuhkan,
     *    sumbernya Whatsapp_Log — bukan kolom ini.
     */
    public function test_pengiriman_lewat_antrean_bukan_http_di_dalam_permintaan(): void
    {
        $rumah = $this->rumah($this->isi());

        $this->assertStringContainsString(
            'WhatsappLogHelper::sendAndLog(',
            $rumah,
            'Notifikasi approver izin tidak lagi lewat pintu terpusat. Tanpa itu tak ada '
            .'job yang di-dispatch (tak ada apa pun di Cloud Tasks) dan tak ada percobaan '
            .'ulang ketika pengiriman gagal.',
        );

        $this->assertStringNotContainsString(
            'Whatsapp::send_message(',
            $rumah,
            'Pengiriman WhatsApp kembali dijalankan LANGSUNG di dalam permintaan. HTTP '
            .'sinkron di jalur ini pernah jadi biang timeout, dan jalur langsung tak '
            .'punya percobaan ulang maupun validasi bentuk nomor HP.',
        );
    }
}
