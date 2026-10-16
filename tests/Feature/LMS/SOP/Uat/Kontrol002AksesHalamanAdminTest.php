<?php

namespace Tests\Feature\LMS\SOP\Uat;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\TestCase;

/**
 * UAT-SOP-001 & UAT-SOP-002 — hak akses halaman SOP.
 *
 * 002 adalah skenario KONTROL: yang dibuktikan bukan bahwa halaman terbuka,
 * melainkan bahwa halaman MENOLAK. Pada Acceptance Criteria skenario kontrol
 * tidak bisa diturunkan menjadi Minor — sistem dokumen terkendali yang gagal
 * menolak orang yang tak berwenang tidak layak Go Live meski seluruh jalur
 * normalnya mulus.
 *
 * Diuji lewat HTTP, bukan dengan memanggil memilikiJabatan() langsung. Yang
 * dijanjikan skenario adalah "mengetik alamatnya langsung tetap ditolak", dan
 * itu janji tentang middleware pada rute — bukan tentang satu fungsi. Memanggil
 * fungsinya akan tetap hijau seandainya seseorang menghapus middleware dari
 * salah satu rute.
 */
class Kontrol002AksesHalamanAdminTest extends TestCase
{
    use DatabaseTransactions;

    #[Group('UAT-SOP-001')]
    public function test_admin_sop_dapat_membuka_ketujuh_halaman_pengelolaan(): void
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped(
                'Tidak ada user yang memiliki KETUJUH hak akses halaman dokumen ('
                .implode(', ', array_keys(UatAkun::HALAMAN_ADMIN)).'). '
                .'Perhatikan: plans/sop/sql/24A_sop_menu_akses_dml.sql hanya memuat ENAM — '
                .'menuSopDokumenPage tidak ada di dalamnya karena halaman Master Dokumen '
                .'dibuat setelah skrip itu ditulis.'
            );
        }

        foreach (UatAkun::HALAMAN_ADMIN as $page => $alamat) {
            $res = $this->actingAs($admin)->get('/'.$alamat);

            $this->assertNotSame(403, $res->getStatusCode(),
                "Admin ditolak di /{$alamat}; hak akses {$page} belum diberikan.");
            $this->assertLessThan(500, $res->getStatusCode(),
                "Halaman /{$alamat} melempar galat server, bukan menolak akses.");
        }
    }

    #[Group('UAT-SOP-002')]
    public function test_karyawan_biasa_ditolak_di_seluruh_halaman_pengelolaan(): void
    {
        $karyawan = UatAkun::karyawanBiasa();
        if (! $karyawan) {
            $this->markTestSkipped(
                'Tidak ada user tanpa hak akses admin SOP pada 400 user pertama. '
                .'Skenario 002 butuh satu akun karyawan biasa untuk membuktikan penolakan.'
            );
        }

        foreach (UatAkun::HALAMAN_ADMIN as $page => $alamat) {
            $this->actingAs($karyawan)
                ->get('/'.$alamat)
                ->assertStatus(403);
        }
    }

    /**
     * Sisi lain dari skenario yang sama, dan yang paling mudah rusak diam-diam:
     * SOP Saya dan Repository SENGAJA global — tak digerbangi hak akses apa pun
     * (lihat catatan di 24A_sop_menu_akses_dml.sql). Kalau suatu hari seseorang
     * "merapikan" dengan menambahkan check.jabatan di sana, seluruh karyawan
     * kehilangan akses ke SOP-nya sendiri, dan gejalanya akan terlihat seperti
     * masalah hak akses perorangan — bukan seperti perubahan rute.
     */
    #[Group('UAT-SOP-002')]
    public function test_halaman_sop_karyawan_tetap_terbuka_bagi_karyawan_biasa(): void
    {
        $karyawan = UatAkun::karyawanBiasa();
        if (! $karyawan) {
            $this->markTestSkipped('Tidak ada akun karyawan biasa untuk diuji.');
        }

        foreach (['sop/saya', 'sop/repository'] as $alamat) {
            $res = $this->actingAs($karyawan)->get('/'.$alamat);

            $this->assertNotSame(403, $res->getStatusCode(),
                "/{$alamat} menolak karyawan biasa. Halaman ini sengaja global — "
                .'menggerbanginya mengunci seluruh karyawan dari SOP mereka sendiri.');
        }
    }
}
