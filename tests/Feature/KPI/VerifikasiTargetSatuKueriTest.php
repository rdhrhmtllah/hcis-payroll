<?php

namespace Tests\Feature\KPI;

use Tests\TestCase;

/**
 * Daftar target parameter diambil SEKALI, bukan sekali per parameter.
 *
 * Bentuk lama menjalankan SELECT di dalam `foreach ($kpiDetails as $d)`.
 * Jumlahnya kecil — rata-rata 3,9 parameter per pengajuan — tetapi yang mahal
 * di lingkungan ini bukan besarnya kueri melainkan JUMLAH PERJALANAN:
 * ASYNC_NETWORK_IO adalah 33,5% waktu tunggu server, dan basis data dev berada
 * di luar mesin.
 *
 * DUA KEGAGALAN PENJAGA YANG DICATAT DI SINI, sebab keduanya bentuk
 * "uji yang tak menguji apa pun" dan keduanya saya buat sendiri:
 *
 *   1. Versi pertama uji perilaku MEMBANGUN KUERI BATCH-NYA SENDIRI lalu
 *      menghitungnya — jadi merusak controller tak berpengaruh apa pun. Ini
 *      pengulangan kekeliruan yang sudah terjadi sekali pada penjaga akses
 *      halaman; pola yang sama, sesi yang sama.
 *   2. Versi pertama uji struktur mencari `foreach ($kpiDetails as $d)` dengan
 *      strpos — dan berkas ini punya DUA perulangan bernama sama (baris 416 dan
 *      488). Ia memeriksa yang SALAH, yang memang tak pernah menyentuh tabel
 *      target, sehingga sabotase lolos hijau.
 *
 * Versi di bawah menyasar perulangan yang BENAR, dikenali dari penanda batch
 * yang mendahuluinya.
 */
class VerifikasiTargetSatuKueriTest extends TestCase
{
    private const BERKAS = 'app/Http/Controllers/KPI/Approval/Verification/KpiVerifikasiController.php';

    private function isi(): string
    {
        $penuh = base_path(self::BERKAS);
        $this->assertFileExists($penuh);

        return (string) file_get_contents($penuh);
    }

    /**
     * Perulangan yang dimaksud dikenali dari penanda batch `$targetPerDetail`
     * yang berada tepat sebelumnya — bukan dari nama `$kpiDetails`, yang di
     * berkas ini dipakai oleh DUA perulangan berbeda.
     */
    private function badanPerulanganTarget(string $isi): string
    {
        $penanda = strpos($isi, '$targetPerDetail');
        $this->assertNotFalse(
            $penanda,
            'Penanda batch $targetPerDetail hilang — pengambilan target kembali ke bentuk per-parameter?',
        );

        $awal = strpos($isi, 'foreach ($kpiDetails as $d)', $penanda);
        $this->assertNotFalse($awal, 'Perulangan target tak ditemukan sesudah penanda batch.');

        /* Batas blok dihitung dari kurung kurawal, bukan dari pola indentasi:
           indentasi berubah kalau kodenya dibungkus ulang, dan penjaga yang
           bergantung padanya akan diam-diam memeriksa potongan yang salah. */
        $mulai = strpos($isi, '{', $awal);
        $dalam = 0;
        for ($i = $mulai; $i < strlen($isi); $i++) {
            if ($isi[$i] === '{') {
                $dalam++;
            }
            if ($isi[$i] === '}') {
                $dalam--;
                if ($dalam === 0) {
                    return substr($isi, $mulai, $i - $mulai + 1);
                }
            }
        }

        $this->fail('Batas perulangan target tak bisa ditentukan.');
    }

    public function test_tak_ada_pengambilan_target_di_dalam_perulangan(): void
    {
        $badan = $this->badanPerulanganTarget($this->isi());

        $this->assertStringNotContainsString(
            "DB::table('KPI_Pengajuan_Det')",
            $badan,
            'Pengambilan target kembali berada DI DALAM perulangan. Satu kueri per parameter '
            .'berarti satu perjalanan bolak-balik per parameter, dan perjalanan adalah biaya '
            .'terbesar di lingkungan ini.',
        );
    }

    public function test_target_diambil_tepat_sekali_di_seluruh_metode(): void
    {
        /* Sisi lain dari penjaga di atas: memindahkan kueri KELUAR dari
           perulangan tetapi menjalankannya dua kali bukan perbaikan. */
        $isi = $this->isi();

        $awal = strpos($isi, "\$kpiDetails = DB::table('KPI_Pengajuan_Detail as pd')");
        $this->assertNotFalse($awal, 'Blok parameter verifikasi tak ditemukan.');

        $akhir = strpos($isi, "'tipe' => 'PARAMETER'", $awal);
        $this->assertNotFalse($akhir, 'Akhir blok parameter verifikasi tak ditemukan.');

        $blok = substr($isi, $awal, $akhir - $awal);

        $this->assertSame(
            1,
            substr_count($blok, "DB::table('KPI_Pengajuan_Det')"),
            'Target diambil lebih dari sekali dalam satu permintaan.',
        );
        $this->assertStringContainsString(
            '->whereIn(',
            $blok,
            'Pengambilan target tak lagi memakai whereIn — apakah ia kembali per-parameter?',
        );
    }
}
