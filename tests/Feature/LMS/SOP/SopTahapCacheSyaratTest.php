<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopTahapVersiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Cache nilai syarat di SopTahapVersiService.
 *
 * CACHE ITU ADA KARENA ALASAN YANG BAIK: tujuanSah() memanggil syaratTahap()
 * untuk setiap tujuan, dan tanpa cache satu layar bisa menembakkan 20+ kueri
 * hanya untuk menggambar checklist. Komentarnya berjanji cache itu "hidup
 * selama satu permintaan HTTP saja; tak ada jawaban basi".
 *
 * JANJI ITU TERNYATA HANYA BENAR bila tak ada yang MENGUBAH data di tengah
 * permintaan. Urutan "periksa syarat → penuhi syaratnya → periksa lagi"
 * membaca jawaban dari sebelum syaratnya dipenuhi, lalu menolak perpindahan
 * yang sebenarnya sah.
 *
 * Ditemukan 2026-08-27 oleh sop:seed-demo, yang menjalankan seluruh siklus
 * hidup dokumen dalam SATU proses. Lewat layar hal ini tak pernah terlihat:
 * tiap langkah di sana adalah permintaan HTTP tersendiri dengan instance
 * service yang baru — dan itulah kenapa ia bisa hidup lama tanpa ketahuan.
 *
 * Uji di berkas ini memakai SATU instance service untuk seluruh urutan. Itu
 * bukan kebetulan melainkan inti pengujiannya: memakai instance baru tiap
 * langkah akan hijau bahkan tanpa perbaikannya.
 */
class SopTahapCacheSyaratTest extends TestCase
{
    use DatabaseTransactions;

    private function versiBaru(SopDokumenService $dok): int
    {
        $id = $dok->buat([
            'Judul' => 'SOP UJI CACHE '.substr((string) microtime(true), -5),
            'Tipe' => 'DOKUMEN',
        ], 1);

        $idVersi = $dok->tambahVersi($id, ['Ringkasan_Perubahan' => 'awal'], 1);
        if (! $idVersi) {
            $this->markTestSkipped('Versi gagal dibuat.');
        }

        return $idVersi;
    }

    public function test_syarat_dibaca_ulang_setelah_isiannya_dipenuhi(): void
    {
        /* REGRESI. Sebelum perbaikan, pindah ke Pemberkasan ditolak dengan
           "Lengkapi: Realisasi pengesah" padahal pengesahnya baru saja
           tersimpan — nilai syaratnya masih yang dibaca saat masuk Pengesahan. */
        $dok = app(SopDokumenService::class);
        $tahap = app(SopTahapVersiService::class); // SATU instance, sengaja

        $idVersi = $this->versiBaru($dok);

        $tahap->pindah($idVersi, 'REVIEW', 1);
        $tahap->pindah($idVersi, 'APPROVAL', 1);   // di sini nilai syarat ter-cache

        $dok->ubahVersi($idVersi, [
            'Pengesah' => [[
                'Peran' => 'Disahkan oleh', 'Nama' => 'Direktur Uji', 'Jabatan' => 'Direktur',
            ]],
        ], 1);

        $hasil = $tahap->pindah($idVersi, 'APPROVED', 1);

        $this->assertSame('APPROVED', $hasil->Kode_Tahap,
            'Pengesah sudah tersimpan; perpindahan ke Pemberkasan harus diterima.');
    }

    public function test_cache_masih_menahan_pembacaan_berulang_dalam_satu_langkah(): void
    {
        /* Perbaikannya TIDAK BOLEH sekadar membuang cache-nya. Dalam satu
           pemanggilan tujuanSah(), syarat untuk beberapa tujuan dibaca
           berturut-turut dari nilai yang sama — dan itulah beban yang cache ini
           ada untuk mencegahnya. */
        $dok = app(SopDokumenService::class);
        $tahap = app(SopTahapVersiService::class);
        $idVersi = $this->versiBaru($dok);
        $tahap->pindah($idVersi, 'REVIEW', 1);
        $tahap->pindah($idVersi, 'APPROVAL', 1);

        \DB::enableQueryLog();
        $tahap->tujuanSah($idVersi);
        $sekali = count(\DB::getQueryLog());
        \DB::flushQueryLog();

        $tahap->tujuanSah($idVersi);
        $dua = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $this->assertLessThanOrEqual($sekali, $dua,
            'Pembacaan kedua tak boleh lebih mahal daripada yang pertama.');
    }

    public function test_lupakan_nilai_bisa_dipanggil_untuk_satu_versi(): void
    {
        /* Pemanggil batch yang mengubah data lewat jalur lain butuh cara
           menyatakan "yang tadi sudah basi" tanpa membuang cache versi lain. */
        $dok = app(SopDokumenService::class);
        $tahap = app(SopTahapVersiService::class);
        $idVersi = $this->versiBaru($dok);

        $tahap->tujuanSah($idVersi);
        $tahap->lupakanNilai($idVersi);
        $tahap->lupakanNilai();

        $this->assertNotEmpty($tahap->tujuanSah($idVersi),
            'Melupakan cache tak boleh mengubah jawabannya, hanya kesegarannya.');
    }
}
