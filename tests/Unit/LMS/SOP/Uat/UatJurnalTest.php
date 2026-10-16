<?php

namespace Tests\Unit\LMS\SOP\Uat;

use App\Services\LMS\SOP\Uat\UatJurnal;
use App\Services\LMS\SOP\Uat\UatTabelIzin;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Jurnal jalan-uji — catatan baris apa saja yang dibuat sebuah jalan otomasi.
 *
 * KENAPA JURNAL, BUKAN MENCOCOKKAN AWALAN NAMA. Menghapus berdasarkan awalan
 * "UAT-AUTO-" terasa cukup sampai dua hal terjadi, dan keduanya pasti terjadi:
 * suatu hari ada dokumen sungguhan bernama awalan sama, dan suatu hari sebuah
 * test gagal setelah menyisipkan baris tapi sebelum sempat menamainya. Jurnal
 * mencatat ID pada saat baris itu lahir, sehingga tak bergantung pada nama.
 */
class UatJurnalTest extends TestCase
{
    private string $sarang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sarang = sys_get_temp_dir().'/uat-jurnal-'.bin2hex(random_bytes(4));
        mkdir($this->sarang, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->sarang.'/*') ?: [] as $f) {
            unlink($f);
        }
        @rmdir($this->sarang);
        parent::tearDown();
    }

    public function test_mencatat_lalu_memuat_kembali_menghasilkan_isi_yang_sama(): void
    {
        $j = UatJurnal::mulai($this->sarang);
        $j->catat('N_SOP_Dokumen', 11);
        $j->catat('N_SOP_Dokumen', 12);
        $j->catat('N_SOP_Item_Versi', 900);
        $j->simpan();

        $lagi = UatJurnal::muat($j->id(), $this->sarang);

        $this->assertSame([11, 12], $lagi->baris()['N_SOP_Dokumen']);
        $this->assertSame([900], $lagi->baris()['N_SOP_Item_Versi']);
    }

    /**
     * Sebuah jalan yang mati di tengah (CTRL-C, listrik padam) meninggalkan
     * jurnal tanpa pembersihnya. Justru itu keadaan yang paling butuh jurnal,
     * jadi ia harus tertulis SEGERA — bukan ditahan di memori sampai akhir.
     */
    public function test_catatan_tersimpan_segera_tanpa_menunggu_simpan_dipanggil(): void
    {
        $j = UatJurnal::mulai($this->sarang);
        $j->catat('N_SOP_Dokumen', 7);

        $lagi = UatJurnal::muat($j->id(), $this->sarang);
        $this->assertSame([7], $lagi->baris()['N_SOP_Dokumen']);
    }

    public function test_id_yang_sama_tidak_tercatat_dua_kali(): void
    {
        $j = UatJurnal::mulai($this->sarang);
        $j->catat('N_SOP_Dokumen', 5);
        $j->catat('N_SOP_Dokumen', 5);

        $this->assertSame([5], $j->baris()['N_SOP_Dokumen']);
    }

    /**
     * Jurnal adalah gerbang terakhir sebelum DELETE. Menolak tabel terlarang di
     * sini berarti jurnal yang menyimpan nama tabel berbahaya tidak pernah
     * lahir — pembersih tak pernah menghadapi pilihan itu.
     */
    public function test_menolak_mencatat_tabel_di_luar_daftar_putih(): void
    {
        $j = UatJurnal::mulai($this->sarang);

        $this->expectException(RuntimeException::class);
        $j->catat('N_LMS_Master_Konten', 1);
    }

    public function test_menolak_id_yang_bukan_bilangan_positif(): void
    {
        $j = UatJurnal::mulai($this->sarang);

        $this->expectException(RuntimeException::class);
        $j->catat('N_SOP_Dokumen', 0);
    }

    public function test_dua_jalan_punya_id_berbeda_dan_tak_saling_menimpa(): void
    {
        $a = UatJurnal::mulai($this->sarang);
        $a->catat('N_SOP_Dokumen', 1);
        $b = UatJurnal::mulai($this->sarang);
        $b->catat('N_SOP_Dokumen', 2);

        $this->assertNotSame($a->id(), $b->id());
        $this->assertSame([1], UatJurnal::muat($a->id(), $this->sarang)->baris()['N_SOP_Dokumen']);
        $this->assertSame([2], UatJurnal::muat($b->id(), $this->sarang)->baris()['N_SOP_Dokumen']);
    }

    public function test_semua_menemukan_jurnal_yang_tertinggal(): void
    {
        $a = UatJurnal::mulai($this->sarang);
        $a->catat('N_SOP_Dokumen', 1);
        $b = UatJurnal::mulai($this->sarang);
        $b->catat('N_SOP_Dokumen', 2);

        $ditemukan = UatJurnal::semua($this->sarang);

        $this->assertContains($a->id(), $ditemukan);
        $this->assertContains($b->id(), $ditemukan);
    }

    public function test_id_jalan_tidak_bisa_menembus_folder_lain(): void
    {
        $this->expectException(RuntimeException::class);
        UatJurnal::muat('../../.env', $this->sarang);
    }

    public function test_seluruh_tabel_yang_diizinkan_bisa_dicatat(): void
    {
        $j = UatJurnal::mulai($this->sarang);
        foreach (UatTabelIzin::DIIZINKAN as $t) {
            $j->catat($t, 1);
        }
        $this->assertCount(count(UatTabelIzin::DIIZINKAN), $j->baris());
    }
}
