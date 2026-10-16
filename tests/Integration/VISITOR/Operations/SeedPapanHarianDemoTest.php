<?php

namespace Tests\Integration\VISITOR\Operations;

use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Data contoh tidak boleh merusak apa pun.
 *
 * No_Transaksi lahir dari counter atomik di HRIS_Counter yang HANYA menyemai
 * diri sekali dari MAX. Menyisipkan baris langsung ke tabel transaksi
 * melewatinya, sehingga counter tertinggal di belakang dan pengajuan berikutnya
 * menabrak nomor yang sudah dipakai — seluruh pengajuan tamu dan permit gagal
 * dengan galat yang tidak menyebut sebabnya.
 */
class SeedPapanHarianDemoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_counter_tidak_tertinggal_di_belakang_nomor_yang_dipakai(): void
    {
        Artisan::call('visitor:seed-papan-harian');

        foreach ([
            ['VSR', 'N_EMI_VISITOR_Transaksi_Tamu'],
            ['PMT', 'N_EMI_VISITOR_Transaksi_Permit'],
        ] as [$seri, $tabel]) {
            $prefix = $seri.now()->format('my').'-';

            $maksimum = (int) substr(
                (string) DB::table($tabel)->where('No_Transaksi', 'LIKE', $prefix.'%')->max('No_Transaksi'),
                -4,
            );
            $counter = (int) DB::table('HRIS_Counter')->where('prefix', $prefix)->value('next_val');

            $this->assertGreaterThanOrEqual(
                $maksimum,
                $counter,
                "Counter {$prefix} ({$counter}) tertinggal di belakang nomor terpakai ({$maksimum}); ".
                'pengajuan berikutnya akan menabrak nomor yang sudah ada.',
            );
        }
    }

    public function test_membersihkan_tidak_pernah_menurunkan_counter(): void
    {
        Artisan::call('visitor:seed-papan-harian');
        $sesudahSeed = (int) DB::table('HRIS_Counter')->where('prefix', 'VSR'.now()->format('my').'-')->value('next_val');

        Artisan::call('visitor:seed-papan-harian', ['--bersihkan' => true]);
        $sesudahBersih = (int) DB::table('HRIS_Counter')->where('prefix', 'VSR'.now()->format('my').'-')->value('next_val');

        // Menurunkan counter akan mendaur ulang nomor yang pernah terpakai.
        // Melompati nomor tidak berbahaya; mengulanginya berbahaya.
        $this->assertGreaterThanOrEqual($sesudahSeed, $sesudahBersih);
    }

    public function test_data_contoh_hanya_menyentuh_barisnya_sendiri(): void
    {
        $sebelum = DB::table('N_EMI_VISITOR_Transaksi_Tamu')
            ->where('Idempotency_Key', 'NOT LIKE', 'SEEDDEMO-%')
            ->orWhereNull('Idempotency_Key')
            ->count();

        Artisan::call('visitor:seed-papan-harian');
        Artisan::call('visitor:seed-papan-harian', ['--bersihkan' => true]);

        $sesudah = DB::table('N_EMI_VISITOR_Transaksi_Tamu')
            ->where('Idempotency_Key', 'NOT LIKE', 'SEEDDEMO-%')
            ->orWhereNull('Idempotency_Key')
            ->count();

        $this->assertSame($sebelum, $sesudah, 'Data sungguhan ikut terhapus oleh pembersih data contoh.');
    }

    /**
     * DDL-2026-121: Status_Kunjungan hanya menyimpan keputusan manusia.
     * Kehadiran (CHECKED_IN / CHECKED_OUT) diturunkan dari jam palang, jadi
     * data contoh tidak boleh menuliskannya — begitu pula kosakata lama.
     */
    public function test_data_contoh_hanya_menulis_status_tersimpan(): void
    {
        Artisan::call('visitor:seed-papan-harian');

        $status = DB::table('N_EMI_VISITOR_Transaksi_Tamu')
            ->where('Idempotency_Key', 'LIKE', 'SEEDDEMO-%')
            ->distinct()
            ->pluck('Status_Kunjungan')
            ->all();

        $this->assertNotEmpty($status, 'Data contoh tidak menulis satu pun kunjungan.');
        foreach ($status as $nilai) {
            $this->assertContains($nilai, StatusKunjunganTamu::TERSIMPAN, "Status '{$nilai}' bukan kode tersimpan.");
        }
        // Tiga keadaan yang sengaja disemai: terjadwal, menunggu, dicabut.
        foreach ([StatusKunjunganTamu::SCHEDULED, StatusKunjunganTamu::PENDING_APPROVAL, StatusKunjunganTamu::REVOKED] as $wajib) {
            $this->assertContains($wajib, $status);
        }
    }
}
