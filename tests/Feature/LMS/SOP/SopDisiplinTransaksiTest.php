<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopApqcService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Disiplin transaksi modul SOP — Fase 1.
 *
 * Menguji EFEK, bukan teks `catch`-nya. `\Error` (TypeError, ArgumentCountError,
 * DivisionByZeroError) BUKAN turunan `\Exception`, sehingga `catch (\Exception)`
 * melewatkannya: `DB::rollBack()` tak pernah dipanggil dan transaksi tetap
 * terbuka selama sisa request — termasuk selagi Laravel merender exception dan
 * menulis log. Pada basis data tanpa RCSI, transaksi yang menggantung seperti
 * itu memblokir pembaca lain.
 *
 * CATATAN PENTING: DatabaseTransactions membuka satu transaksi sebelum tiap
 * uji, jadi transactionLevel() bernilai 1 di sini, bukan 0. Baseline karena itu
 * DITANGKAP, tidak diasumsikan.
 */
class SopDisiplinTransaksiTest extends TestCase
{
    use DatabaseTransactions;

    private function adminApqc(): ?User
    {
        return User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopApqcPage'));
    }

    public function test_error_di_tengah_mutasi_tidak_meninggalkan_transaksi_menggantung(): void
    {
        $admin = $this->adminApqc();
        if (! $admin) {
            $this->markTestSkipped('Tak ada user dgn akses menuSopApqcPage di data dev.');
        }

        $this->app->bind(SopApqcService::class, fn () => new class extends SopApqcService
        {
            public function create(array $data, int $userId): int
            {
                throw new \TypeError('galat tipe yang disengaja untuk uji');
            }
        });

        $levelSebelum = DB::transactionLevel();
        $jumlahSebelum = DB::table('N_SOP_Master_APQC')->count();

        // Kode_Proses dibatasi max:10 — payload lebih panjang berhenti di 422
        // dan tak pernah mencapai service, sehingga ujinya lulus tanpa menguji apa pun.
        $res = $this->actingAs($admin)->postJson('/api/v1/sop/admin/apqc/store', [
            'Kode_Proses' => 'UJI-DIS-1',
            'Nama_Proses' => 'Uji disiplin transaksi',
            'Urutan' => 999,
        ]);

        /* Penjaga kerja-nyata: buktikan permintaannya BENAR-BENAR sampai ke
           service. Bila validasi menolaknya (422) atau izinnya kurang (403),
           penegasan di bawah akan lulus tanpa satu pun transaksi pernah dibuka
           — hijau palsu yang paling mudah masuk ke uji semacam ini.
           Kode lama (TypeError lolos ke handler Laravel) dan kode baru
           (ditangkap \Throwable) sama-sama menghasilkan 500. */
        $res->assertStatus(500);

        $this->assertSame(
            $levelSebelum,
            DB::transactionLevel(),
            'Transaksi menggantung setelah \\Error: rollBack() tak pernah dipanggil. '
            .'Inilah yang terjadi ketika sebuah titik hanya menangkap \\Exception.'
        );

        $this->assertSame(
            $jumlahSebelum,
            DB::table('N_SOP_Master_APQC')->count(),
            'Ada baris tersisa setelah \\Error — mutasinya tidak di-rollback.'
        );
    }
}
