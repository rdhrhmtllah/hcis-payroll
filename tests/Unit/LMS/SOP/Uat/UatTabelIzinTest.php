<?php

namespace Tests\Unit\LMS\SOP\Uat;

use App\Services\LMS\SOP\Uat\UatTabelIzin;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Daftar-putih tabel yang boleh dihapus otomasi UAT.
 *
 * Uji ini tidak menyentuh database sama sekali — dan memang harus begitu. Yang
 * dijaga di sini adalah keputusan "tabel mana yang boleh disentuh", dan
 * keputusan itu tidak boleh bergantung pada keadaan database mana pun. Sebuah
 * daftar-putih yang hanya benar ketika terhubung ke server tertentu bukan
 * daftar-putih.
 */
class UatTabelIzinTest extends TestCase
{
    public function test_tabel_sop_yang_dibuat_test_boleh_dihapus(): void
    {
        foreach (['N_SOP_Dokumen', 'N_SOP_Item_Versi', 'N_SOP_Master_Paket',
            'N_SOP_Paket_Item', 'N_SOP_Penjadwalan', 'N_SOP_Progress'] as $t) {
            $this->assertTrue(UatTabelIzin::diizinkan($t), "$t seharusnya diizinkan");
        }
    }

    public function test_tabel_di_luar_daftar_ditolak(): void
    {
        $this->assertFalse(UatTabelIzin::diizinkan('N_HRIS_Karyawan'));
        $this->assertFalse(UatTabelIzin::diizinkan('Users'));
        $this->assertFalse(UatTabelIzin::diizinkan(''));
    }

    /**
     * Berkas SOP ditulis ke N_LMS_Master_Konten — tabel milik modul LMS yang
     * dipakai belasan berkas LMS lain. Salah WHERE di sana merusak materi
     * pelatihan, bukan data uji.
     */
    public function test_tabel_milik_modul_lain_ditolak(): void
    {
        foreach (['N_LMS_Master_Konten', 'N_LMS_Master_Konten_Berkas',
            'N_LMS_Master_Konten_Media'] as $t) {
            $this->assertFalse(UatTabelIzin::diizinkan($t), "$t milik modul lain, harus ditolak");
        }
    }

    /**
     * N_HRIS_Notification KINI diizinkan, dan itu perubahan yang disengaja.
     *
     * Ia tetap milik seluruh HCIS. Yang berubah: menugaskan paket uji menulis
     * ke sini di luar transaksi, sehingga tiap jalan peramban meninggalkan
     * pemberitahuan pada lonceng karyawan sungguhan yang menunjuk paket yang
     * sudah dihapus — 27 baris sudah menumpuk sebelum ini disadari.
     *
     * Yang menjaganya tetap aman bukan daftar ini, melainkan cara pembersih
     * bekerja: ia hanya menghapus id yang TERCATAT di jurnal. Uji ini berdiri
     * supaya penambahan tadi tak terbaca sebagai kelalaian oleh pembaca
     * berikutnya, dan supaya penghapusannya dari daftar juga harus disengaja.
     */
    public function test_pemberitahuan_diizinkan_karena_dibuat_jalan_uji_sendiri(): void
    {
        $this->assertTrue(UatTabelIzin::diizinkan('N_HRIS_Notification'),
            'Tanpa ini, pemberitahuan buatan jalan-uji menumpuk di akun karyawan sungguhan '
            .'dan menunjuk paket yang sudah dihapus.');
    }

    /**
     * Skenario 025 MENGUBAH setelan, bukan membuatnya. Menghapus barisnya
     * meninggalkan modul SOP tanpa konfigurasi — kerusakan yang jauh lebih
     * lama umurnya daripada sesi uji yang menyebabkannya.
     */
    public function test_tabel_setelan_ditolak(): void
    {
        $this->assertFalse(UatTabelIzin::diizinkan('N_SOP_Setting'));
    }

    public function test_penolakan_menyebutkan_alasannya_bukan_sekadar_gagal(): void
    {
        try {
            UatTabelIzin::pastikanDiizinkan('N_LMS_Master_Konten');
            $this->fail('Seharusnya melempar untuk tabel milik modul lain.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('N_LMS_Master_Konten', $e->getMessage());
            $this->assertStringContainsString('LMS', $e->getMessage(),
                'Pesan harus menyebut PEMILIKNYA, supaya yang membaca tahu kenapa '
                .'ditolak dan tidak sekadar menambahkannya ke daftar.');
        }
    }

    public function test_urutan_hapus_memuat_persis_tabel_yang_diizinkan(): void
    {
        $urut = UatTabelIzin::URUTAN_HAPUS;

        $this->assertSame(
            [], array_diff($urut, UatTabelIzin::DIIZINKAN),
            'Ada tabel di urutan hapus yang tidak ada di daftar-putih.'
        );
        $this->assertSame(
            [], array_diff(UatTabelIzin::DIIZINKAN, $urut),
            'Ada tabel diizinkan yang tidak pernah dihapus — ia akan tertinggal selamanya.'
        );
        $this->assertSame(count($urut), count(array_unique($urut)),
            'Tabel terdaftar dua kali pada urutan hapus.');
    }

    /**
     * Urutan hapus harus anak-dulu-induk-belakangan, kalau tidak foreign key
     * menolak dan pembersihan berhenti separuh jalan — meninggalkan justru
     * keadaan yang paling sulit dibereskan.
     */
    public function test_anak_dihapus_sebelum_induknya(): void
    {
        $urut = array_flip(UatTabelIzin::URUTAN_HAPUS);

        $pasangan = [
            ['N_SOP_Progress', 'N_SOP_Paket_Item'],
            ['N_SOP_Paket_Item', 'N_SOP_Master_Paket'],
            ['N_SOP_Paket_Section', 'N_SOP_Master_Paket'],
            ['N_SOP_Item_Versi_Pengesah', 'N_SOP_Item_Versi'],
            ['N_SOP_Item_Versi_Berkas', 'N_SOP_Item_Versi'],
            ['N_SOP_Item_Versi', 'N_SOP_Dokumen'],
            ['N_SOP_Penjadwalan_Detail', 'N_SOP_Penjadwalan'],
            ['N_SOP_Paket_Versi_Item', 'N_SOP_Paket_Versi'],
            ['N_SOP_Paket_Draft_Item', 'N_SOP_Paket_Draft'],
        ];

        foreach ($pasangan as [$anak, $induk]) {
            $this->assertLessThan(
                $urut[$induk], $urut[$anak],
                "$anak (anak) harus dihapus sebelum $induk (induk)."
            );
        }
    }
}
