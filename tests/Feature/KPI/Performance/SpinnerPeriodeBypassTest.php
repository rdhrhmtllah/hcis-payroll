<?php

namespace Tests\Feature\KPI\Performance;

use Tests\TestCase;

/**
 * Tombol Update periode di halaman by-pass tidak boleh berputar selamanya.
 *
 * ── Bug aslinya ─────────────────────────────────────────────────────────────
 *
 * updatePeriod() menyalakan spinner LEBIH DULU, baru mencari penjadwalan yang
 * dipilih, lalu keluar diam-diam bila tidak ketemu:
 *
 *     this.loading.savePeriod = true;
 *     const selectedItem = this.scheduleList.find(...);
 *     if (!selectedItem) return;        // di LUAR try/finally
 *     try { ... } finally { this.loading.savePeriod = false; }
 *
 * `return` itu berada di luar try/finally, jadi tidak ada yang pernah mematikan
 * spinner-nya. Dan karena ia juga tidak memberi pesan apa pun, satu-satunya
 * jalan keluar bagi pengguna adalah me-refresh halaman — yang membuatnya tampak
 * "akhirnya selesai" padahal tidak ada satu pun permintaan yang pernah dikirim.
 *
 * ── Kenapa dijaga dari bentuk berkasnya ─────────────────────────────────────
 *
 * Yang dijaga adalah URUTAN, dan urutan yang salah tidak melempar galat apa pun:
 * tidak ada exception, tidak ada log, tidak ada permintaan gagal. Ia hanya
 * berputar. Perilaku seperti itu tak bisa ditangkap uji HTTP.
 */
class SpinnerPeriodeBypassTest extends TestCase
{
    private const HALAMAN = 'resources/js/Pages/KPI/Performance/Update/team/HomeTeamPerformanceBaypass.vue';

    private function badanUpdatePeriod(): string
    {
        $isi = file_get_contents(base_path(self::HALAMAN));

        $awal = strpos($isi, 'async updatePeriod(');
        $this->assertNotFalse($awal, 'Metode updatePeriod() tidak ditemukan — penjaga ini tidak menguji apa pun.');

        $akhir = strpos($isi, 'async fetchListData(', $awal);
        $this->assertNotFalse($akhir, 'Batas akhir updatePeriod() tidak ditemukan.');

        return substr($isi, $awal, $akhir - $awal);
    }

    public function test_spinner_dinyalakan_setelah_seluruh_penjagaan(): void
    {
        $badan = $this->badanUpdatePeriod();

        $spinner = strpos($badan, 'this.loading.savePeriod = true;');
        $this->assertNotFalse($spinner, 'Penyalaan spinner tidak ditemukan di updatePeriod().');

        $try = strpos($badan, 'try {', $spinner);
        $this->assertNotFalse($try, 'Tidak ada try setelah spinner dinyalakan.');

        $antara = substr($badan, $spinner, $try - $spinner);

        $this->assertStringNotContainsString(
            'return',
            $antara,
            'Ada jalan keluar antara penyalaan spinner dan try/finally. '.
            'Sekali jalur itu diambil, tombol Update berputar selamanya dan pengguna '.
            'hanya bisa keluar dengan me-refresh halaman.',
        );
    }

    /**
     * Penjadwalan kini OPSIONAL: tidak memilih apa pun harus tetap mengirim
     * permintaan, dengan id null, bukan berhenti diam-diam.
     */
    public function test_penjadwalan_kosong_tetap_dikirim_sebagai_null(): void
    {
        $badan = $this->badanUpdatePeriod();

        $this->assertStringNotContainsString(
            'if (!selectedItem)',
            $badan,
            'Penjagaan "wajib pilih penjadwalan" hidup lagi. Periode by-pass bersifat global '.
            'menurut bulan+tahun; jadwalnya diturunkan server dari periode itu.',
        );

        $this->assertStringContainsString(
            'selectedItem?.Id_Master_Penjadwalan ?? null',
            $badan,
            'Tanpa ini, id yang tidak dipilih terkirim sebagai undefined/kosong alih-alih null.',
        );
    }

    public function test_pencocokan_penjadwalan_tahan_beda_tipe(): void
    {
        $badan = $this->badanUpdatePeriod();

        $this->assertStringContainsString(
            'String(item.Id_Master_Penjadwalan)',
            $badan,
            'Pencocokan id kembali membandingkan nilai mentah. fetchScheduleList() sudah '.
            'memakai String() justru karena perbandingan mentah pernah meleset hanya '.
            'karena beda tipe — dan melesetnya berakhir di jalur "tidak ketemu" yang sama.',
        );
    }
}
