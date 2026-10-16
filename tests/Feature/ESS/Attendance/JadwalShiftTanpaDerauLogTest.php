<?php

namespace Tests\Feature\ESS\Attendance;

use Tests\TestCase;

/**
 * Jalur jadwal shift tidak boleh menulis log tingkat debug.
 *
 * ShiftRequestService::getWeekSchedule() dipanggil sekali untuk setiap kunjungan
 * halaman utama (lewat HomepageHcisService) dan menelusuri jendela tujuh hari.
 * Satu `Log::debug` di dalam loop itu berarti tujuh baris per kunjungan, per
 * pengguna. calculateShiftDurationHours() lebih parah lagi: dipanggil di dalam
 * loop penjumlahan durasi, jadi berlipat mengikuti jumlah item jadwal.
 *
 * Terukur sebelum dibersihkan: satu panggilan getWeekSchedule() untuk SATU
 * karyawan menghasilkan 7 baris di laravel.log; sesudahnya 0. Dengan
 * `LOG_LEVEL=debug` — yang menjadi baku di `.env.example` maupun di
 * config/logging.php — baris-baris itu ikut tertulis di semua lingkungan, dan
 * mengubur entri yang benar-benar perlu dibaca saat ada insiden.
 *
 * Penjaga ini memeriksa berkasnya, bukan efeknya, dengan sengaja: ia harus
 * merah begitu seseorang menempelkan `Log::debug` baru di sana, tanpa perlu
 * menyiapkan data absensi lebih dulu.
 */
class JadwalShiftTanpaDerauLogTest extends TestCase
{
    private const BERKAS = 'app/Services/ESS/Attendance/Shift/Request/ShiftRequestService.php';

    /** @test */
    public function shift_request_service_tidak_menulis_log_debug(): void
    {
        $isi = file_get_contents(base_path(self::BERKAS));
        $this->assertNotFalse($isi, 'Berkas '.self::BERKAS.' tidak terbaca.');

        $pelanggar = [];
        foreach (explode("\n", $isi) as $i => $baris) {
            $bersih = ltrim($baris);

            // Baris berkomentar tidak dieksekusi, jadi tidak menghasilkan derau.
            if (str_starts_with($bersih, '//') || str_starts_with($bersih, '*') || str_starts_with($bersih, '/*')) {
                continue;
            }

            if (preg_match('/\bLog::debug\s*\(/', $bersih)) {
                $pelanggar[] = ($i + 1).': '.trim($baris);
            }
        }

        $this->assertSame(
            [],
            $pelanggar,
            "ShiftRequestService menulis Log::debug di jalur yang dipanggil setiap kunjungan halaman utama.\n".
            "Satu baris di dalam loop tujuh hari berarti tujuh baris per kunjungan per pengguna, dan itu\n".
            "mengubur entri yang benar-benar perlu dibaca. Pakai kanal khusus (mis. `shiftLog`) bila memang\n".
            "butuh jejak, jangan laravel.log tingkat debug.\n\nDitemukan di:\n  ".implode("\n  ", $pelanggar),
        );
    }
}
