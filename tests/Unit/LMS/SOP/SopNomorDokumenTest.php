<?php

namespace Tests\Unit\LMS\SOP;

use App\Services\LMS\SOP\Support\SopNomorDokumen;
use PHPUnit\Framework\TestCase;

/**
 * Perakit nomor dokumen gabungan.
 *
 *   Jenis / Entitas / Departemen / RuangLingkup / NoAPQC / NoDokumen / Revisi
 *
 * Unit test murni — tak menyentuh basis data. Yang diuji di sini adalah
 * FORMATNYA, dan format itulah yang tercetak di dokumen resmi.
 */
class SopNomorDokumenTest extends TestCase
{
    private function lengkap(array $ubah = []): array
    {
        return array_merge([
            'jenis' => 'IK',
            'entitas' => 'ENB',
            'departemen' => 'PROD',
            'lingkup' => 'EHG',
            'apqc' => '09',
            'nomor' => 3,
            'revisi' => 1,
        ], $ubah);
    }

    public function test_tujuh_segmen_dirakit_berurutan(): void
    {
        $this->assertSame('IK/ENB/PROD/EHG/09/003/01', SopNomorDokumen::rakit($this->lengkap()));
    }

    public function test_revisi_nol_adalah_segmen_yang_sah(): void
    {
        /* Revisi nol adalah keadaan NORMAL setiap terbitan pertama: dokumennya
           belum pernah direvisi. Cek falsy (`$v ? … : null`) akan menghapus
           segmen "00" dan menghasilkan nomor yang kehilangan satu bagian tanpa
           satu pun galat. */
        $this->assertSame(
            'PLC/EHG/HC/EHG/06/001/00',
            SopNomorDokumen::rakit($this->lengkap([
                'jenis' => 'PLC', 'entitas' => 'EHG', 'departemen' => 'HC',
                'apqc' => '06', 'nomor' => 1, 'revisi' => 0,
            ])),
        );
    }

    /**
     * Pemformat TIDAK menghakimi nilai segmennya.
     *
     * Nomor urut dokumen mulai dari satu, tetapi aturan itu ditegakkan di hulu
     * — saranNoDokumen() dan validasi No_Dokumen — bukan di sini. Menolak nol di
     * pemformat akan ikut menolak revisi 00, yang bentuknya persis sama dan
     * justru wajib ada. Dokumen lama bernomor 00 pun tetap harus terbaca utuh.
     */
    public function test_pemformat_tetap_merakit_nomor_nol_warisan(): void
    {
        $this->assertSame(
            'PLC/EHG/HC/EHG/06/000/00',
            SopNomorDokumen::rakit($this->lengkap([
                'jenis' => 'PLC', 'entitas' => 'EHG', 'departemen' => 'HC',
                'apqc' => '06', 'nomor' => 0, 'revisi' => 0,
            ])),
        );
    }

    public function test_no_dokumen_tiga_digit_revisi_dan_apqc_dua_digit(): void
    {
        /* Lebar mengikuti register dokumen terkendali perusahaan: No. Dokumen
           001, Revisi 00. Revisi dan APQC TIDAK ikut melebar. */
        $this->assertSame('IK/ENB/PROD/EHG/09/007/02', SopNomorDokumen::rakit(
            $this->lengkap(['nomor' => 7, 'revisi' => 2, 'apqc' => 9]),
        ));
        $this->assertSame('SOP/EMI/WFP/EMI/04/001/00', SopNomorDokumen::rakit([
            'jenis' => 'SOP', 'entitas' => 'EMI', 'departemen' => 'WFP', 'lingkup' => 'EMI',
            'apqc' => '04', 'nomor' => '001', 'revisi' => '00',
        ]), 'Nomor register EMI harus terakit persis sama dengan kolom No. Dokumen Lengkap-nya.');
    }

    public function test_angka_lebih_lebar_tidak_dipotong(): void
    {
        /* Memotongnya membuat 1000 tampil sebagai 000 dan revisi 100 sebagai
           00 — nomor lain yang juga benar-benar ada. */
        $this->assertSame('IK/ENB/PROD/EHG/09/1000/100', SopNomorDokumen::rakit(
            $this->lengkap(['nomor' => 1000, 'revisi' => 100]),
        ));
    }

    public function test_satu_segmen_kosong_membatalkan_seluruh_nomor(): void
    {
        /* Nomor separuh jadi lebih berbahaya daripada tak ada nomor: ia
           terlihat sah, tercetak, lalu dipakai merujuk dokumen yang
           identitasnya belum lengkap. */
        foreach (['jenis', 'entitas', 'departemen', 'lingkup', 'apqc', 'nomor', 'revisi'] as $segmen) {
            $this->assertNull(
                SopNomorDokumen::rakit($this->lengkap([$segmen => null])),
                "Segmen {$segmen} kosong seharusnya membatalkan nomor.",
            );
        }
    }

    public function test_pratinjau_memperlihatkan_lubangnya(): void
    {
        $this->assertSame('??/ENB/PROD/EHG/09/003/01', SopNomorDokumen::pratinjau(
            $this->lengkap(['jenis' => null]),
        ));
    }

    public function test_pratinjau_menyebut_seluruh_segmen_yang_kurang(): void
    {
        $kurang = SopNomorDokumen::segmenKosong($this->lengkap([
            'jenis' => null, 'apqc' => null, 'revisi' => null,
        ]));

        $this->assertSame(['Jenis dokumen', 'Proses APQC', 'Revisi'], $kurang);
    }

    public function test_dokumen_lengkap_tak_menyisakan_kekurangan(): void
    {
        $this->assertSame([], SopNomorDokumen::segmenKosong($this->lengkap()));
    }

    public function test_segmen_teks_dinaikkan_jadi_huruf_besar(): void
    {
        $this->assertSame('IK/ENB/PROD/EHG/09/003/01', SopNomorDokumen::rakit(
            $this->lengkap(['jenis' => 'ik', 'departemen' => ' prod ']),
        ));
    }

    public function test_string_kosong_diperlakukan_sama_dengan_null(): void
    {
        /* Form HTML mengirim string kosong, bukan null. Kalau tak disamakan,
           nomornya akan berisi segmen kosong di antara dua garis miring. */
        $this->assertNull(SopNomorDokumen::rakit($this->lengkap(['departemen' => ''])));
        $this->assertNull(SopNomorDokumen::rakit($this->lengkap(['nomor' => ''])));
    }

    public function test_urutan_segmen_tidak_ikut_urutan_kunci_pemanggil(): void
    {
        /* Nomor yang segmennya bertukar tempat tetap terlihat sah, dan tak ada
           yang menyadarinya sampai dokumennya dicari dan tak ketemu. */
        $terbalik = [
            'revisi' => 1, 'nomor' => 3, 'apqc' => '09',
            'lingkup' => 'EHG', 'departemen' => 'PROD', 'entitas' => 'ENB', 'jenis' => 'IK',
        ];

        $this->assertSame('IK/ENB/PROD/EHG/09/003/01', SopNomorDokumen::rakit($terbalik));
    }
}
