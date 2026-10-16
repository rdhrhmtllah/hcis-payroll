<?php

namespace Tests\Feature\SYSTEM;

use Tests\TestCase;

/**
 * WhatsApp: satu pintu, dan hasilnya bukan objek Response.
 *
 * ── Cacat yang ditutup ───────────────────────────────────────────────────────
 *
 * Whatsapp::send_message() mengembalikan ARRAY — isinya Http::post(...)->json()
 * — dan MELEMPAR \Exception bila gagal. Delapan lokasi di TicketController
 * memperlakukan hasilnya sebagai objek Response:
 *
 *     $response = Whatsapp::send_message($pesan, [...]);
 *     if ($response->getStatusCode() !== 200) { DB::rollBack(); ... }
 *
 * Memanggil metode pada array melempar \Error, dan \Error BUKAN turunan
 * \Exception. Jadi di jalur SUKSES pun permintaan berakhir 500 dan seluruh
 * pekerjaan basis data dibatalkan. Tiket hanya tersimpan bila penerimanya
 * kebetulan tidak punya nomor HP.
 *
 * Dua cacat lain menumpang di lokasi yang sama: HTTP 10-20 detik dijalankan
 * saat transaksi masih terbuka (dengan RCSI mati, selama itu pula pembaca lain
 * mengantre), dan WhatsApp yang sedang bermasalah membatalkan data tiket yang
 * sah lewat DB::rollBack().
 *
 * ── Kenapa pintunya WhatsappLogHelper::sendAndLog ────────────────────────────
 *
 * sendAndLog menormalkan nomor, menolak format yang tidak sah, lalu mengantre
 * lewat SendWhatsappJob. Job itu membawa afterCommit()-nya sendiri, sehingga
 * jaminan "tidak ada WA untuk transaksi yang rollback" tidak bergantung pada
 * satu baris konfigurasi koneksi. Request tidak pernah menunggu HTTP.
 *
 * ── Siapa yang MASIH boleh memanggil send_message langsung ───────────────────
 *
 * Hanya pihak yang MEMANG pengirimnya: helper itu sendiri, job pekerja, dan
 * perintah konsol. Daftarnya dibekukan di bawah supaya pemanggil baru — atau
 * hilangnya salah satu tanpa disengaja — membuat uji ini merah.
 *
 * Uji ini tekstual dan tidak menyentuh basis data. Komentar dibuang lewat
 * token_get_all lebih dulu, supaya kalimat penjelasan di atas (yang menyebut
 * getStatusCode dan send_message) tidak ikut terhitung sebagai kode.
 */
class WhatsappSatuPintuTest extends TestCase
{
    /**
     * Berkas yang boleh memanggil Whatsapp::send_message secara langsung,
     * beserta jumlah pemanggilannya. Angka ikut dibekukan supaya penambahan
     * diam-diam di berkas yang sudah terdaftar tetap ketahuan.
     *
     * Whatsapp.php sendiri tidak masuk daftar: ia MENDEFINISIKAN metodenya dan
     * tidak pernah memanggilnya lewat nama kelas.
     */
    private const PENGIRIM_SAH = [
        'app/Helpers/WhatsappLogHelper.php' => 1,                                  // performSend, dijalankan worker
        'app/Jobs/SYSTEM/Notification/WhatsApp/SendWhatsappMessage.php' => 1,
        'app/Jobs/ESS/Attendance/Leave/Notification/SendExpiredIzinWhatsappJob.php' => 1,
        'app/Console/Commands/SYSTEM/Notification/WhatsApp/waUlang.php' => 1,

        /*
         * Tinggal SATU sisa di AbsensiController (baris ~639): closure-nya
         * menambah kolom angka_wa setelah pengiriman berhasil. Memindahkannya ke
         * antrean menghilangkan penambahan itu, jadi keputusannya ditahan —
         * bukan diakali. Sudah dibungkus DB::afterCommit, jadi tidak menahan lock.
         *
         * Dulu dua. Yang satu lagi dipindahkan ke antrean oleh f15984dc
         * ("notifikasi approver izin lewat antrean") pada 10 September 2026,
         * tetapi angka di sini lupa diturunkan — sehingga penjaganya merah justru
         * karena ada PERBAIKAN, bukan karena ada pelanggaran. Angka ini ratchet:
         * ia hanya boleh turun. Menaikkannya kembali berarti menambah pengiriman
         * HTTP sinkron di dalam permintaan web, dan itu yang dilarang berkas ini.
         */
        'app/Http/Controllers/ESS/Attendance/Legacy/AbsensiController.php' => 1,
    ];

    /** Metode objek Response yang tidak pernah ada pada hasil send_message. */
    private const METODE_RESPONSE = [
        'getStatusCode', 'successful', 'failed', 'body', 'status', 'ok', 'throw',
    ];

    /** Buang komentar tetapi pertahankan nomor baris. */
    private function tanpaKomentar(string $berkas): string
    {
        $keluar = '';
        foreach (token_get_all((string) file_get_contents($berkas)) as $t) {
            if (is_array($t)) {
                if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) {
                    $keluar .= str_repeat("\n", substr_count($t[1], "\n"));

                    continue;
                }
                $keluar .= $t[1];
            } else {
                $keluar .= $t;
            }
        }

        return $keluar;
    }

    /** @return array<string, string> jalur relatif => isi tanpa komentar */
    private function berkasPhp(): array
    {
        $keluar = [];
        foreach (['app', 'routes'] as $akar) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($akar), \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $f) {
                if ($f->getExtension() !== 'php') {
                    continue;
                }
                $rel = str_replace([base_path().DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], ['', '/'],
                    $f->getPathname());
                $keluar[$rel] = $this->tanpaKomentar($f->getPathname());
            }
        }
        ksort($keluar);

        return $keluar;
    }

    public function test_hanya_pengirim_sah_yang_memanggil_send_message_langsung(): void
    {
        $nyata = [];
        foreach ($this->berkasPhp() as $rel => $isi) {
            $n = preg_match_all('/Whatsapp::\s*send_message\s*\(/i', $isi);
            if ($n > 0) {
                $nyata[$rel] = $n;
            }
        }

        $tidakSah = array_diff_key($nyata, self::PENGIRIM_SAH);
        $this->assertSame([], $tidakSah,
            'Pemanggil Whatsapp::send_message langsung di luar daftar pengirim sah. '
            .'Pakai WhatsappLogHelper::sendAndLog: ia mengantre lewat SendWhatsappJob '
            .'(afterCommit melekat di job), jadi request tidak menahan lock maupun menunggu HTTP.');

        foreach (self::PENGIRIM_SAH as $rel => $jumlah) {
            $this->assertArrayHasKey($rel, $nyata,
                "{$rel} terdaftar sebagai pengirim sah tetapi tidak lagi memanggil send_message. "
                .'Kalau itu disengaja, hapus barisnya dari PENGIRIM_SAH.');
            if ($jumlah !== null) {
                $this->assertSame($jumlah, $nyata[$rel],
                    "Jumlah pemanggilan send_message di {$rel} berubah.");
            }
        }
    }

    public function test_hasil_send_message_tidak_pernah_diperlakukan_sebagai_objek(): void
    {
        $pelanggaran = [];

        foreach ($this->berkasPhp() as $rel => $isi) {
            if (! preg_match_all('/(\$\w+)\s*=\s*[^;]*Whatsapp::\s*send_message\s*\(/i',
                $isi, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                continue;
            }

            foreach ($m as $x) {
                $variabel = ltrim($x[1][0], '$');
                /*
                 * Jendela 60 baris sesudah penugasan: cukup untuk menangkap
                 * pemakaian di blok yang sama tanpa menyeberang ke metode lain.
                 */
                $sisa = substr($isi, $x[0][1]);
                $jendela = implode("\n", array_slice(explode("\n", $sisa), 0, 60));

                foreach (self::METODE_RESPONSE as $metode) {
                    $pola = '/\$'.preg_quote($variabel, '/').'\s*->\s*'.$metode.'\s*\(/';
                    if (preg_match($pola, $jendela)) {
                        $baris = substr_count(substr($isi, 0, $x[0][1]), "\n") + 1;
                        $pelanggaran[] = "{$rel}:{$baris} \${$variabel}->{$metode}()";
                    }
                }
            }
        }

        $this->assertSame([], $pelanggaran,
            'send_message mengembalikan ARRAY (Http::post(...)->json()), bukan objek Response. '
            .'Memanggil metode padanya melempar \Error — dan \Error bukan turunan \Exception, '
            .'jadi catch (\Exception) tidak menangkapnya dan transaksi ikut hilang.');
    }

    public function test_pengirim_langsung_tidak_berada_di_dalam_transaksi_terbuka(): void
    {
        /*
         * Pengirim sah pun tidak boleh memanggil HTTP dengan transaksi terbuka.
         * Yang diperiksa: dalam metode yang memanggil send_message, tidak ada
         * DB::beginTransaction() yang mendahuluinya tanpa commit/rollBack di
         * antaranya.
         */
        $pelanggaran = [];

        foreach (self::PENGIRIM_SAH as $rel => $_) {
            $isi = $this->tanpaKomentar(base_path($rel));
            if (! preg_match_all('/Whatsapp::\s*send_message\s*\(/i', $isi, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($m[0] as $x) {
                $sebelum = substr($isi, 0, $x[1]);
                $buka = preg_match_all('/DB::\s*beginTransaction\s*\(/i', $sebelum);
                $tutup = preg_match_all('/DB::\s*(commit|rollBack)\s*\(/i', $sebelum);
                if ($buka > $tutup) {
                    $baris = substr_count($sebelum, "\n") + 1;
                    $pelanggaran[] = "{$rel}:{$baris} (beginTransaction {$buka} > penutup {$tutup})";
                }
            }
        }

        $this->assertSame([], $pelanggaran,
            'HTTP WhatsApp dipanggil saat transaksi masih terbuka. Dengan RCSI mati, '
            .'selama 10-20 detik itu pembaca lain mengantre di belakangnya.');
    }
}
