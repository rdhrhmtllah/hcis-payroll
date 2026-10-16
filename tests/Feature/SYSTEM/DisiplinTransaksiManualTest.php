<?php

namespace Tests\Feature\SYSTEM;

use Tests\TestCase;

/**
 * Setiap transaksi manual wajib punya jaring pengaman untuk \Error.
 *
 * MENGAPA INI PENJAGA STRUKTURAL, BUKAN PENJAGA PERILAKU.
 * Cacatnya tersebar di lebih dari seratus metode lintas modul, dan bentuknya
 * selalu sama: `DB::beginTransaction()` dipasangkan `catch (\Exception)`.
 * `\Error` — TypeError, ArgumentCountError, DivisionByZeroError — BUKAN turunan
 * `\Exception`, sehingga ia lolos, `rollBack()` tak pernah dipanggil, dan
 * transaksi tetap terbuka sepanjang sisa request. Pada basis data ini, yang
 * berjalan tanpa RCSI, transaksi menggantung seperti itu membuat setiap pembaca
 * tabel yang sama ikut antre sampai batas waktunya habis.
 *
 * Menulis satu uji perilaku per titik akan menuntut fikstur dan otentikasi untuk
 * seratus endpoint. Penjaga ini menempuh jalan lain: ia membaca kodenya. Yang
 * dijaga bukan "galat tertangani dengan benar" melainkan sesuatu yang lebih
 * sempit dan bisa diperiksa mesin — TIDAK ADA transaksi manual yang dibiarkan
 * tanpa jaring \Throwable.
 *
 * Bentuk yang diterima:
 *   } catch (\Throwable $e) {
 *       DB::rollBack();
 *       throw $e;          // dilempar ulang: handler global tetap mencatat
 *   }                      //  stack trace-nya dan responsnya tak berubah
 *
 * Melempar ulang itu bagian dari kontrak, bukan hiasan: menelan \Error di sini
 * akan menyembunyikan cacat pemrograman yang justru harus terlihat.
 */
class DisiplinTransaksiManualTest extends TestCase
{
    /** Berkas PHP di bawah app/. */
    private function berkasApp(): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('app'), \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $out[] = $f->getPathname();
            }
        }
        sort($out);

        return $out;
    }

    /** Komentar dibuang tapi jumlah barisnya dipertahankan, supaya nomor baris tetap benar. */
    private function tanpaKomentar(string $s): string
    {
        $s = preg_replace_callback('~/\*.*?\*/~s', fn ($m) => str_repeat("\n", substr_count($m[0], "\n")), $s);

        return preg_replace('~//.*$~m', '', $s);
    }

    /**
     * @return array<int,string> daftar "berkas:baris metode()" yang belum terlindungi
     */
    private function titikTanpaJaring(): array
    {
        $pelanggar = [];
        $fungsi = '~^\s{4}(?:public|protected|private)?\s*(?:static\s+)?function\s+(\w+)~';

        foreach ($this->berkasApp() as $path) {
            $baris = explode("\n", $this->tanpaKomentar((string) file_get_contents($path)));

            $batas = [];
            foreach ($baris as $i => $l) {
                if (preg_match($fungsi, $l)) {
                    $batas[] = $i;
                }
            }
            $batas[] = count($baris);

            for ($b = 0; $b < count($batas) - 1; $b++) {
                $awal = $batas[$b];
                $isi = implode("\n", array_slice($baris, $awal, $batas[$b + 1] - $awal));

                /* Case-INsensitive, dan itu bukan kerapian: satu titik nyata
                   menulis `DB::BeginTransaction()` dengan B kapital dan karena
                   itu tak pernah terhitung sama sekali. Nama metode PHP tidak
                   peka huruf besar-kecil; penjaga yang peka justru buta pada
                   kode yang sah. */
                if (! preg_match('~DB::beginTransaction~i', $isi)) {
                    continue;
                }

                /* Jaring dianggap ada bila metode ini menangkap \Throwable DAN
                   membatalkan transaksinya (langsung atau lewat helper).

                   Case-INsensitive, alasan yang sama dengan di atas: empat titik
                   nyata menulis `catch (\throwable $th)` dengan t kecil. Nama
                   kelas PHP tidak peka huruf besar-kecil, jadi keempatnya BENAR
                   menangkap \Error dan memang me-rollback — penjaga yang peka
                   menuduh kode yang sudah aman, dan tuduhan palsu membuat orang
                   berhenti mempercayai penjaganya. */
                $adaThrowable = (bool) preg_match('~catch\s*\(\s*\\\\?Throwable~i', $isi);
                $adaRollback = (bool) preg_match('~rollBack\s*\(|rollback\s*\(|safeRollback\s*\(~', $isi);

                if (! $adaThrowable || ! $adaRollback) {
                    preg_match($fungsi, $baris[$awal], $m);
                    $rel = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
                    $pelanggar[] = str_replace('\\', '/', $rel).':'.($awal + 1).'  '.($m[1] ?? '?').'()';
                }
            }
        }

        return $pelanggar;
    }

    public function test_tiap_transaksi_manual_punya_jaring_throwable(): void
    {
        $pelanggar = $this->titikTanpaJaring();

        $this->assertSame(
            [],
            $pelanggar,
            count($pelanggar).' metode membuka transaksi manual tanpa jaring \\Throwable. '
            .'\\Error bukan turunan \\Exception, jadi di titik-titik ini rollBack() tak pernah '
            .'dipanggil dan transaksinya menggantung — pada basis data tanpa RCSI itu memblokir '
            ."pembaca lain.\n\nYang belum terlindungi:\n  ".implode("\n  ", array_slice($pelanggar, 0, 80))
            .(count($pelanggar) > 80 ? "\n  ... dan ".(count($pelanggar) - 80).' lagi' : '')
        );
    }
}
