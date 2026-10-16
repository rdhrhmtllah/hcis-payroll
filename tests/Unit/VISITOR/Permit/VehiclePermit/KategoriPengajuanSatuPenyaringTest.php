<?php

namespace Tests\Unit\VISITOR\Permit\VehiclePermit;

use PHPUnit\Framework\TestCase;

/**
 * Penyaring kategori pengajuan hanya boleh punya SATU definisi.
 *
 * Ada dua pemanggil yang setara: daftar pilihan yang dikirim ke layar, dan
 * penjaga saat pengajuan disimpan. Selama keduanya menyusun penyaringnya
 * sendiri, dropdown bisa menyembunyikan kategori yang tetap diterima server —
 * pola kegagalan yang sudah berulang di repo ini, dan yang tidak menyalakan
 * galat apa pun: yang terjadi hanyalah permit terbit dari pintu yang salah.
 */
class KategoriPengajuanSatuPenyaringTest extends TestCase
{
    private function sumber(): string
    {
        $path = dirname(__DIR__, 5).'/app/Http/Controllers/VISITOR/Permit/VehiclePermit/VehiclePermitController.php';

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_penyaringnya_hidup_di_satu_metode(): void
    {
        $this->assertStringContainsString('function terapkanKategoriPengajuan(', $this->sumber());
    }

    public function test_kedua_pemanggil_memakai_metode_yang_sama(): void
    {
        // Penjaga di store() memakai metode bersama controller; daftar pilihan
        // kini disusun VisitorPermitMasterService (dipakai juga dialog koreksi)
        // dan memanggil kelas penyaring yang sama secara langsung.
        $this->assertStringContainsString('$this->terapkanKategoriPengajuan(', $this->sumber());
        $this->assertStringContainsString('return KategoriPermitPengajuan::saring($query);', $this->sumber());

        $master = (string) file_get_contents(dirname(__DIR__, 5).'/app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitMasterService.php');
        $this->assertStringContainsString(
            'KategoriPermitPengajuan::saring(',
            $master,
            'Daftar pilihan kategori berhenti memakai penyaring bersama.',
        );
    }

    public function test_tidak_ada_penyaring_kategori_yang_disusun_sendiri(): void
    {
        // Pola yang dihindari: menyebut kolom penandanya langsung di luar
        // metode bersama. Satu di antaranya akan tertinggal saat aturannya
        // berubah, dan yang tertinggal itu justru penjaga di servernya.
        //
        // Yang dihitung adalah kemunculan DI LUAR metode bersamanya, bukan
        // se-berkas. Bentuk sebelumnya menuntut tepat satu kemunculan, padahal
        // penyaringnya sendiri sah menyebut kolom itu dua kali (null ATAU 'Y')
        // dan docblocknya sekali lagi — sehingga ia memerah atas kode yang
        // justru benar, dan angka "1" itu hanya kebetulan sesuai sesaat.
        $sumber = $this->sumber();

        // Potongan metodenya dicari dengan pola yang TIDAK bergantung pada
        // bentuk akhiran baris: berkas ini ber-CRLF, dan pencarian literal
        // "\n    }\n" meleset diam-diam lalu membuat seluruh berkas dianggap
        // "di luar" — penjaga yang menuduh tanpa sebab.
        $cocok = preg_match(
            '/protected function terapkanKategoriPengajuan\(.*?\R    \}/s',
            $sumber,
            $blok,
            PREG_OFFSET_CAPTURE,
        );

        $this->assertSame(1, $cocok, 'Penyaring bersama tidak ditemukan; uji ini berhenti menguji apa pun.');

        $luar = substr_replace($sumber, '', $blok[0][1], strlen($blok[0][0]));

        // Komentar dibuang: menyebut nama kolom saat MENJELASKAN aturannya
        // bukan pelanggaran, dan melarangnya hanya mendorong orang menulis
        // penjelasan yang kabur.
        $luar = (string) preg_replace('#/\*.*?\*/|//[^\r\n]*#s', '', $luar);

        $this->assertSame(
            0,
            substr_count((string) $luar, 'Flag_Muncul_Pengajuan'),
            'Kolom penanda disebut di luar penyaring bersama.',
        );
    }

    public function test_kategori_tidak_disaring_dengan_menyebut_namanya(): void
    {
        // Menyembunyikan COKLAT dengan menuliskan namanya di kode membuat
        // kategori izin tamu berikutnya menuntut perubahan kode lagi — dan
        // sampai itu terjadi, ia ditawarkan ke karyawan.
        $this->assertStringNotContainsString("Nama_Kategori', 'COKLAT'", $this->sumber());
        $this->assertStringNotContainsString("Nama_Kategori !== 'COKLAT'", $this->sumber());
    }
}
