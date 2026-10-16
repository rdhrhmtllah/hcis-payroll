<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\Support\SopSetting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Memo per-proses SopSetting.
 *
 * Cache produksi memakai basis data (tak ada Redis), jadi tiap pembacaan cache
 * adalah satu kueri — dan SopSetting::int()/bool() dipanggil berkali-kali untuk
 * nilai yang sama dalam satu permintaan. Memo memangkasnya menjadi satu.
 *
 * Yang diuji EFEKNYA di dua arah, dan arah kedua yang penting: memo mempercepat
 * pembacaan berulang, tetapi memo yang tak dibersihkan saat setting berubah
 * menyajikan nilai basi HANYA pada permintaan yang menulis lalu membaca
 * kembali. Cacat seperti itu tak pernah terlihat di jalur baca biasa.
 */
class SopSettingMemoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        /* Uji lain di suite yang sama sudah bisa mengisi memo dan cache. Tanpa
           pembersihan ini "pembacaan pertama" di bawah bukan yang pertama. */
        SopSetting::flush();
    }

    protected function tearDown(): void
    {
        SopSetting::flush();

        parent::tearDown();
    }

    /**
     * Hitung pernyataan yang diterbitkan selama $fn berjalan.
     *
     * DB::listen tak bisa dicabut, jadi pendengar dari pemanggilan sebelumnya
     * tetap hidup — tak apa: masing-masing menaikkan pencacahnya sendiri, dan
     * pencacah yang sudah dibaca tidak dilihat lagi.
     */
    private function hitungKueri(\Closure $fn): int
    {
        $n = 0;
        DB::listen(function () use (&$n) {
            $n++;
        });

        $fn();

        return $n;
    }

    private function lewatiBilaTabelKosong(): void
    {
        if (DB::table('N_SOP_Setting')->count() === 0) {
            $this->markTestSkipped('N_SOP_Setting kosong di lingkungan ini — tak ada yang bisa diukur.');
        }
    }

    /**
     * (a) Pembacaan kedua dan seterusnya tidak menembus ke basis data.
     */
    public function test_pembacaan_berulang_hanya_menerbitkan_kueri_sekali(): void
    {
        $this->lewatiBilaTabelKosong();

        $pertama = $this->hitungKueri(function () {
            $this->assertNotSame([], SopSetting::semua(),
                'PENJAGA KERJA-NYATA: pembacaan yang tak memulangkan apa pun menghasilkan '
                .'jumlah kueri yang sempurna tanpa membuktikan apa pun.');
        });

        $this->assertGreaterThan(0, $pertama,
            'Pembacaan pertama tidak menyentuh basis data sama sekali — memo atau cache '
            .'belum benar-benar dibersihkan oleh flush(), sehingga sisa uji ini tak sah.');

        $kedua = $this->hitungKueri(function () {
            SopSetting::semua();
            SopSetting::get('default_reminder_hari');
            SopSetting::int('penjadwalan_async_ambang', 30);
            SopSetting::bool('mover_auto_assign', true);
        });

        $this->assertSame(0, $kedua,
            "Empat pembacaan setelah yang pertama menerbitkan {$kedua} kueri. Memo per-proses "
            .'tidak bekerja: dengan cache berbasis basis data, tiap pembacaan cache adalah kueri.');
    }

    /**
     * (b) PENJAGA MEMO BASI.
     *
     * Setelah setting diubah dan flush() dipanggil, pembacaan berikutnya DI
     * PROSES YANG SAMA wajib memulangkan nilai BARU. Bila flush() hanya
     * membuang baris cache dan lupa mengosongkan memo, penegasan terakhir di
     * bawah inilah satu-satunya yang menyalakan lampu merah.
     */
    public function test_setelan_yang_baru_diubah_terbaca_di_proses_yang_sama(): void
    {
        $baris = DB::table('N_SOP_Setting')->whereNotNull('Nilai')->orderBy('Kode_Setting')->first();
        if (! $baris) {
            $this->markTestSkipped('N_SOP_Setting tak punya baris bernilai di lingkungan ini.');
        }

        $kunci = (string) $baris->Kode_Setting;
        $semula = (string) $baris->Nilai;
        /* Satu karakter, selalu berbeda dari nilai semula, dan pasti muat pada
           kolom sependek apa pun. Diputar balik oleh DatabaseTransactions. */
        $baru = $semula === '1' ? '2' : '1';

        // Pembacaan ini yang MENGISI memo dengan nilai lama.
        $this->assertSame($semula, SopSetting::get($kunci),
            "Setelan '{$kunci}' tak terbaca sesuai isi tabel — prasyarat uji ini gagal.");

        DB::table('N_SOP_Setting')->where('Kode_Setting', $kunci)->update(['Nilai' => $baru]);
        SopSetting::flush();

        $this->assertSame($baru, SopSetting::get($kunci),
            "Setelan '{$kunci}' masih terbaca '{$semula}' sesudah diubah dan di-flush. "
            .'flush() membuang baris cache tetapi tidak mengosongkan memo per-proses, '
            .'sehingga permintaan yang MENULIS setting lalu membacanya kembali '
            .'(updateSettings() lalu settingValues() pada respons yang sama) menerima '
            .'nilai basi — dan hanya di jalur itu.');
    }
}
