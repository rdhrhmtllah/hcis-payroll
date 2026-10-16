<?php

namespace Tests\Feature\LMS\SOP\Uat;

use App\Services\LMS\SOP\SopDocumentControlService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\Feature\LMS\SOP\Uat\Support\UatDunia;
use Tests\TestCase;

/**
 * UAT-SOP-043 — register kontrol dokumen tidak boleh saling bertentangan.
 *
 * Menjaga hal yang paling mendasar dari sistem dokumen terkendali: laporan
 * kepatuhannya harus bisa dipercaya auditor.
 *
 * 043 punya sejarah nyata. Sebelum Master Dokumen, tiap paket menyimpan salinan
 * tata kelola dokumennya sendiri, dan salinan itu SUDAH menyimpang di produksi:
 * satu berkas dilaporkan dengan tata kelola berbeda pada dua paket, pada saat
 * yang sama. Uji ini menjaga agar keadaan itu tak kembali.
 *
 * UAT-SOP-046 ("dokumen lewat masa berlaku tertandai kadaluarsa") DICABUT
 * 15 Agu 2026 bersama konsep masa berlaku itu sendiri — keputusan produk, bukan
 * uji yang gugur. Sebuah SOP kini berlaku sampai ada revisi versi baru, jadi
 * tak ada lagi keadaan yang bisa diuji kontrol itu. Ember register yang tersisa
 * adalah TANPA_PENGESAHAN dan RENDER_GAGAL; yang pertama sudah dijaga di pintu
 * terbit oleh penjaga pengesah wajib.
 */
class Kontrol043RegisterKontrolDokumenTest extends TestCase
{
    use DatabaseTransactions;

    private function dunia(): UatDunia
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan keenam hak akses halaman dokumen.');
        }

        try {
            return UatDunia::mulai((int) $admin->Id_Users)
                ->buatDokumenTerbit()
                ->buatPaket()
                ->tambahItem('Item Dokumen')
                ->terbitkanPaket();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }
    }

    /**
     * Baris register milik dunia uji ini.
     *
     * DUA JEBAKAN pada keluaran register(), keduanya menghasilkan daftar kosong
     * yang diam-diam membuat assertEmpty() lulus tanpa menguji apa pun:
     *
     *   1. Balikannya TUPLE [rows, total], bukan array ber-kunci 'rows'.
     *   2. Id_SOP_Dokumen pada baris sudah DI-HASH sebelum dikembalikan.
     *      Membandingkannya dengan id integer tidak pernah cocok.
     *
     * Karena itu penyaringnya memakai Nomor_SOP_Resmi, yang dipasang fixture
     * dengan penanda unik per jalan-uji dan dikembalikan apa adanya.
     */
    private function barisDunia(UatDunia $d, array $filter = []): array
    {
        [$rows] = app(SopDocumentControlService::class)->register($filter, 1, 500);

        return array_values(array_filter(
            is_array($rows) ? $rows : iterator_to_array($rows),
            fn ($r) => $this->ambil($r, 'Nomor_SOP_Resmi') === $d->tanda()
        ));
    }

    private function ambil($baris, string $kolom)
    {
        return is_array($baris) ? ($baris[$kolom] ?? null) : ($baris->$kolom ?? null);
    }

    #[Group('UAT-SOP-043')]
    public function test_dokumen_yang_dipakai_dua_paket_dilaporkan_dengan_tata_kelola_yang_sama(): void
    {
        $d = $this->dunia();
        $lain = $d->paketTambahan('Paket Kedua');
        $d->terbitkanPaketLain($lain['paket']);

        $baris = $this->barisDunia($d);

        $this->assertGreaterThanOrEqual(2, count($baris),
            'Dokumen ini dipakai dua paket, jadi register semestinya menyentuhnya '
            .'dari kedua paket itu.');

        $versi = array_unique(array_map(fn ($r) => $this->ambil($r, 'Id_SOP_Item_Versi'), $baris));
        $berlaku = array_unique(array_map(
            fn ($r) => (string) $this->ambil($r, 'Tanggal_Berlaku'), $baris));
        $pengesah = array_unique(array_map(
            fn ($r) => (string) $this->ambil($r, 'Disahkan_Oleh'), $baris));

        $this->assertCount(1, $versi,
            'Satu dokumen terbaca dengan versi aktif yang BERBEDA antar paket. '
            .'Dua paket bisa menunjuk revisi berbeda dan keduanya sama-sama tampak sah.');
        $this->assertCount(1, $berlaku,
            'Tanggal berlaku dokumen yang sama berbeda antar paket.');
        $this->assertCount(1, $pengesah,
            'Pengesah dokumen yang sama berbeda antar paket — persis kontradiksi '
            .'yang Master Dokumen ada untuk menghapuskannya.');
    }
}
