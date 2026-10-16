<?php

namespace Tests\Unit\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaStatusPenugasan;
use PHPUnit\Framework\TestCase;

class PaStatusPenugasanTest extends TestCase
{
    public function test_terkumpul_bukan_tuntas(): void
    {
        // Lembarnya terisi, tetapi penilainya belum menekan kirim untuk seluruh
        // tugasnya. Menghitungnya tuntas membuat hasil lahir di atas penilaian
        // yang pengisinya sendiri belum menganggap selesai.
        $this->assertNotContains('TERKUMPUL', PaStatusPenugasan::TUNTAS);
        $this->assertSame(['SELESAI'], PaStatusPenugasan::TUNTAS);
    }

    public function test_terkumpul_dan_menunggu_verifikasi_tidak_terhitung_terlambat(): void
    {
        $this->assertContains('TERKUMPUL', PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT);
        $this->assertContains('MENUNGGU_VERIFIKASI', PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT);
        $this->assertContains('DIBATALKAN', PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT);
    }

    public function test_sudah_dikerjakan_memuat_terkumpul(): void
    {
        $this->assertContains('TERKUMPUL', PaStatusPenugasan::SUDAH_DIKERJAKAN);
        $this->assertContains('SELESAI', PaStatusPenugasan::SUDAH_DIKERJAKAN);
        $this->assertNotContains('BELUM_MULAI', PaStatusPenugasan::SUDAH_DIKERJAKAN);
        $this->assertNotContains('DIBATALKAN', PaStatusPenugasan::SUDAH_DIKERJAKAN);
    }

    public function test_sql_daftar_mengutip_setiap_nilai(): void
    {
        $this->assertSame("'SELESAI','TERKUMPUL'", PaStatusPenugasan::sqlDaftar(['SELESAI', 'TERKUMPUL']));
    }

    public function test_sql_daftar_menolak_nilai_di_luar_kamus(): void
    {
        // Nilai status dirangkai ke dalam SQL mentah. Tanpa penjagaan ini, satu
        // salah ketik menjadi SQL, dan salahnya tidak terlihat sebagai salah ketik.
        $this->expectException(\InvalidArgumentException::class);
        PaStatusPenugasan::sqlDaftar(['SELESAI', "X'; DROP TABLE y--"]);
    }

    public function test_ketat_berbeda_dari_longgar_pada_terkumpul_dan_menunggu_verifikasi(): void
    {
        // Dua himpunan "tidak dihitung terlambat" HARUS berbeda persis pada dua nilai
        // ini. Kalau suatu hari ada yang "merapikan" keduanya jadi satu, uji ini yang
        // bicara lebih dulu — bukan angka terlambat yang diam-diam berubah di layar
        // admin atau di daftar peserta.
        $this->assertSame(['SELESAI', 'DIBATALKAN'], PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT_KETAT);

        $this->assertNotContains('TERKUMPUL', PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT_KETAT);
        $this->assertNotContains('MENUNGGU_VERIFIKASI', PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT_KETAT);

        $this->assertContains('TERKUMPUL', PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT);
        $this->assertContains('MENUNGGU_VERIFIKASI', PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT);

        // Keduanya tetap sepakat soal SELESAI dan DIBATALKAN — perbedaannya HANYA pada
        // TERKUMPUL dan MENUNGGU_VERIFIKASI.
        $this->assertContains('SELESAI', PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT_KETAT);
        $this->assertContains('DIBATALKAN', PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT_KETAT);
        $this->assertContains('SELESAI', PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT);
        $this->assertContains('DIBATALKAN', PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT);
    }

    public function test_sql_daftar_menolak_larik_kosong(): void
    {
        // Larik kosong lolos foreach tanpa pernah memeriksa apa pun, dan
        // implode('', []) menghasilkan string kosong. Pemanggil yang merangkainya
        // jadi "Status_Penugasan NOT IN ()" akan mengirim SQL tak sah ke SQL Server —
        // bukan status tak dikenal yang kentara, tapi kueri yang gagal dieksekusi.
        $this->expectException(\InvalidArgumentException::class);
        PaStatusPenugasan::sqlDaftar([]);
    }
}
