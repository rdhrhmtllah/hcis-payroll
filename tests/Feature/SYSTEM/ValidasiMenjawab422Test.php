<?php

namespace Tests\Feature\SYSTEM;

use Tests\TestCase;

/**
 * Parameter yang kurang dijawab 422, bukan 500.
 *
 * Pendamping perilaku bagi DisiplinValidasiTertelanTest, yang menjaga BENTUK
 * kodenya. Penjaga struktural bisa saja hijau sementara jawabannya tetap salah —
 * misalnya bila klausa ValidationException ada tetapi menelan pengecualiannya
 * alih-alih melempar ulang. Uji ini menuntut hasil yang benar-benar terlihat di
 * jaringan.
 *
 * Dua bentuk perbaikan diwakili masing-masing satu rute:
 *
 *   - validasi dinaikkan ke luar try  -> GET  /api/v1/kuota-management/karyawan/detail
 *   - catch (ValidationException)     -> POST /api/v1/lms/training/{id}/checkin
 *
 * Tidak ada kueri yang berjalan di kedua kasus: validasi gagal sebelum baris
 * basis data pertama, jadi uji ini tidak menyentuh basis data sama sekali.
 *
 * withoutMiddleware() mematikan autentikasi dan pemeriksaan jabatan dengan
 * sengaja. Yang diuji di sini bukan siapa yang boleh masuk, melainkan apa yang
 * dijawab controller ketika masukannya kurang — dan gerbang-gerbang itu akan
 * menjawab 302/403 lebih dulu sehingga controllernya tak pernah tersentuh.
 */
class ValidasiMenjawab422Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    public function test_validasi_di_luar_try_menjawab_422_bukan_500(): void
    {
        $res = $this->getJson('/api/v1/kuota-management/karyawan/detail');

        $res->assertStatus(422);
        $res->assertJsonValidationErrors('kode_karyawan');
    }

    public function test_catch_validation_exception_melempar_ulang_sehingga_tetap_422(): void
    {
        // Rutenya memvalidasi SESUDAH $request->replace() menaruh hasil decode
        // Hashids, jadi validasinya tak bisa dinaikkan ke luar try; yang dijaga
        // di sini adalah klausa catch-nya melempar ulang, bukan menelan.
        $res = $this->postJson('/api/v1/lms/training/1/checkin', []);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['Id_Penjadwalan', 'Id_Materi', 'Output', 'photo']);
    }

    /**
     * Kegagalan sistem yang sungguhan tetap 500.
     *
     * Tanpa ini, "semua jadi 422" juga akan lolos kedua uji di atas. Yang
     * dijaga bukan sekadar munculnya 422, melainkan bahwa 422 dan 500 kembali
     * menunjuk dua hal yang berbeda.
     */
    public function test_parameter_lengkap_tapi_isinya_tak_masuk_akal_bukan_lagi_soal_validasi(): void
    {
        // kode_karyawan lolos validasi (string terisi) tetapi bukan ciphertext
        // yang sah, sehingga Crypt::decryptString di dalam try melempar — dan
        // ITU memang kegagalan yang pantas dilaporkan sebagai 500.
        $res = $this->getJson('/api/v1/kuota-management/karyawan/detail?kode_karyawan=bukan-ciphertext');

        $res->assertStatus(500);
    }
}
