<?php

namespace Tests\Feature\KPI\Performance;

use Tests\TestCase;

/**
 * Satu keadaan tidak boleh dikatakan dua kali di layar yang sama.
 *
 * ── Kejadiannya ─────────────────────────────────────────────────────────────
 *
 * Spanduk "periode terkunci" ditambahkan supaya penolakan finalisasi tidak lagi
 * muncul di detik terakhir. Tetapi ketika pengajuannya sedang MENUNGGU
 * persetujuan — keadaan yang paling sering terjadi, dan keadaan yang SEHAT —
 * FinalReview sudah menampilkan panel "Data Telah Terkirim" dengan isi yang
 * sama. Hasilnya pengguna membaca dua paragraf berturut-turut yang mengatakan
 * satu hal, dan yang kedua bernada seperti masalah.
 *
 * Yang tersisa untuk spanduk ini hanyalah keadaan yang TIDAK dicakup panel itu:
 * periode yang sudah DISETUJUI, atau pengajuan menggantung yang bukan milik
 * transaksi yang sedang dibuka halaman ini.
 *
 * Dijaga dari bentuk berkasnya karena yang dijaga adalah SYARAT TAMPIL, dan
 * syarat tampil yang salah tidak melempar galat apa pun — ia hanya membuat
 * layar mengulang dirinya sendiri.
 */
class SpandukPeriodeTidakGandaTest extends TestCase
{
    private const HALAMAN = 'resources/js/Pages/KPI/Performance/Update/updatePerformance.vue';

    private function isi(): string
    {
        return file_get_contents(base_path(self::HALAMAN));
    }

    public function test_spanduk_tidak_tampil_saat_panel_terkirim_sudah_bicara(): void
    {
        $isi = $this->isi();

        $this->assertStringNotContainsString(
            'v-if="periodeTerkunci"',
            $isi,
            'Spanduk kembali tampil hanya berdasarkan periodeTerkunci. Pada pengajuan yang '.
            'sedang menunggu persetujuan, panel "Data Telah Terkirim" sudah mengatakannya — '.
            'spanduk itu menjadi salinan kedua.',
        );

        $this->assertStringContainsString(
            'v-if="tampilkanSpandukTerkunci"',
            $isi,
            'Syarat tampil spanduk tidak lagi lewat tampilkanSpandukTerkunci().',
        );

        $awal = strpos($isi, 'tampilkanSpandukTerkunci() {');
        $this->assertNotFalse($awal, 'tampilkanSpandukTerkunci() tidak ditemukan.');

        $badan = substr($isi, $awal, 400);

        $this->assertStringContainsString(
            'this.terkunciMenunggu && this.isFinalized',
            $badan,
            'Syaratnya tidak lagi melihat pasangan "menunggu + sudah difinalisasi", '.
            'yang justru satu-satunya keadaan yang sudah dicakup panel FinalReview.',
        );
    }

    public function test_nada_pesan_dibedakan_menurut_status(): void
    {
        $isi = $this->isi();

        // "Batalkan yang lama" hanya masuk akal untuk pengajuan yang SUDAH
        // disetujui. Menyuruhnya pada pengajuan yang sedang berjalan berarti
        // meminta orang membatalkan sesuatu yang justru sedang bekerja.
        $awal = strpos($isi, 'terkunciPenjelasan() {');
        $this->assertNotFalse($awal, 'terkunciPenjelasan() tidak ditemukan.');

        $badan = substr($isi, $awal, 900);
        $cabang = strpos($badan, 'if (this.terkunciMenunggu)');

        $this->assertNotFalse($cabang, 'Penjelasan tidak lagi bercabang menurut status.');

        $this->assertStringNotContainsString(
            'Batalkan',
            substr($badan, $cabang, strpos($badan, 'return `Pengajuan ${no} sudah disetujui') - $cabang),
            'Cabang "sedang menunggu" ikut menyuruh membatalkan pengajuan yang justru sedang berjalan.',
        );
    }
}
