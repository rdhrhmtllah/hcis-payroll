<?php

namespace Tests\Feature\SYSTEM;

use Tests\TestCase;

/**
 * Laporan indeks menganggur tidak boleh menyeret indeks yang menjaga keutuhan.
 *
 * ── Kenapa dijaga ─────────────────────────────────────────────────────────
 *
 * Sebuah indeks NON-UNIK hanya punya satu alasan hidup: mempercepat baca. Kalau
 * sepanjang jendela statistik ia tidak pernah di-seek, di-scan, maupun
 * di-lookup, biaya pemeliharaannya murni kerugian — dan pada Web_HRIS itu
 * terukur 619.086 pemeliharaan tulis tanpa satu pun pembacaan dalam tiga minggu.
 *
 * Tetapi indeks UNIK berbeda sifatnya. Tugasnya menolak baris kembar, bukan
 * mempercepat; nol pembacaan tidak membuatnya sia-sia. Laporan yang menyeretnya
 * masuk adalah cara paling rapi untuk kehilangan keutuhan data — dan repo ini
 * penuh indeks unik BERFILTER (WHERE Flag_Aktif = 'Y') yang justru jarang
 * terbaca karena tugasnya menjaga, bukan melayani kueri.
 *
 * Jebakan itu sudah nyaris terjadi sekali: pemeriksaan indeks kembar yang
 * pertama menyaring is_primary_key dan is_unique_constraint tetapi LUPA
 * is_unique, sehingga UX_PA_Cycle_* dan kerabatnya ikut terdaftar.
 *
 * ── Yang dijaga ──────────────────────────────────────────────────────────
 *
 *   1. ketiga saringan keutuhan ada: is_primary_key, is_unique_constraint,
 *      dan is_unique
 *   2. "tidak terbaca" berarti KETIGA penghitung baca nol — bukan salah satunya
 *   3. peringatan bahwa ini daftar kandidat, bukan daftar hapus, tidak hilang
 *
 * Uji ini tekstual dan tidak menyentuh basis data; perintahnya sendiri hanya
 * membaca katalog sistem.
 */
class InventarisIndeksNganggurTest extends TestCase
{
    private const PERINTAH = 'app/Console/Commands/SYSTEM/Application/Database/InventarisSkema.php';

    private function kueri(): string
    {
        $isi = (string) file_get_contents(base_path(self::PERINTAH));
        $awal = strpos($isi, 'private function nganggur(): int');
        $this->assertNotFalse($awal, 'Metode nganggur() hilang.');

        return substr($isi, $awal);
    }

    public function test_indeks_penjaga_keutuhan_dikecualikan(): void
    {
        $kueri = $this->kueri();

        foreach ([
            'i.is_primary_key = 0' => 'primary key',
            'i.is_unique_constraint = 0' => 'unique constraint',
            'i.is_unique = 0' => 'unique index',
        ] as $saringan => $jenis) {
            $this->assertStringContainsString($saringan, $kueri,
                "Laporan indeks menganggur tidak lagi mengecualikan {$jenis}. Nol "
                .'pembacaan TIDAK membuat indeks unik sia-sia — tugasnya menolak baris '
                .'kembar. Menyeretnya ke daftar kandidat adalah cara paling rapi untuk '
                .'kehilangan keutuhan data.');
        }
    }

    public function test_tidak_terbaca_berarti_ketiga_penghitung_nol(): void
    {
        $kueri = $this->kueri();

        foreach (['user_seeks', 'user_scans', 'user_lookups'] as $penghitung) {
            $this->assertMatchesRegularExpression(
                '/ISNULL\(us\.'.$penghitung.', 0\)\s*=\s*0/',
                $kueri,
                "Penghitung {$penghitung} tidak lagi diharuskan nol. Indeks yang "
                .'dipakai lewat lookup saja akan terlihat menganggur padahal tidak.');
        }
    }

    public function test_peringatan_dev_bukan_produksi_tidak_hilang(): void
    {
        /*
         * Angka ini milik SATU lingkungan dan direset tiap SQL Server dimulai
         * ulang. Tanpa peringatannya, daftar ini akan dibaca sebagai daftar
         * hapus — dan indeks yang menganggur di pengembangan bisa jadi tulang
         * punggung di produksi.
         */
        $isi = (string) file_get_contents(base_path(self::PERINTAH));

        $this->assertStringContainsString('Bandingkan dengan produksi sebelum membuang satu pun', $isi,
            'Peringatan "bandingkan dengan produksi" hilang dari keluaran perintah.');
        $this->assertStringContainsString('sqlserver_start_time', $this->kueri(),
            'Jendela statistik tidak lagi dilaporkan; angkanya jadi tak bermakna.');
    }
}
