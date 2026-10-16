<?php

namespace Tests\Feature\LMS\SOP\Uat;

use App\Services\LMS\SOP\SopCompletionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\Feature\LMS\SOP\Uat\Support\UatDunia;
use Tests\TestCase;

/**
 * UAT-SOP-035 — belum lulus kuis berarti paket belum selesai.
 *
 * Skenario KONTROL. Kuis adalah satu-satunya tempat modul ini mengukur
 * PEMAHAMAN, bukan sekadar mencatat bahwa halaman sudah dibuka. Kelulusan yang
 * bisa ditembus membuat seluruh angka kepatuhan berbunyi lebih meyakinkan
 * daripada kenyataannya.
 */
class Kontrol035KelulusanKuisTest extends TestCase
{
    use DatabaseTransactions;

    private function dunia(): UatDunia
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan keenam hak akses halaman dokumen.');
        }

        $karyawan = UatAkun::karyawanBiasa();
        $kode = $karyawan ? UatAkun::kodeKaryawan($karyawan) : null;
        if (! $kode) {
            $this->markTestSkipped('Tidak ada akun karyawan yang tertaut data karyawan.');
        }

        try {
            return UatDunia::mulai((int) $admin->Id_Users)
                ->buatDokumenTerbit()
                ->buatPaket()
                ->tambahItem('Item Kuis')
                ->tugaskanKe($kode);
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }
    }

    private function kirim(UatDunia $d, float $nilai, ?float $ambang): string
    {
        return app(SopCompletionService::class)->submitKuisResult(
            $d->detailId(), $d->idItem[0], $d->idVersi, $d->idPaket,
            $d->kodeKaryawan, 'KUIS', $nilai, $ambang
        );
    }

    /**
     * KETIDAKLULUSAN PUNYA LEBIH DARI SATU NAMA.
     *
     * Dokumentasi submitKuisResult() menyebut balikannya 'LULUS'|'BELUM_LULUS'|
     * 'ALREADY_DONE', tapi ada nilai keempat yang benar-benar terjadi:
     * 'TIDAK_LULUS_FINAL', dipakai saat percobaan terakhir habis. Menuntut
     * string yang persis membuat uji ini gagal atas perbedaan penamaan, bukan
     * atas perbedaan perilaku — dan seorang pembaca akan menyimpulkan gerbang
     * kelulusannya rusak padahal ia bekerja.
     *
     * Yang dijaga skenario 035 adalah "belum lulus berarti belum selesai",
     * bukan ejaan status tertentu.
     */
    private function assertTidakLulus(UatDunia $d, string $hasil, string $pesan): void
    {
        $this->assertNotSame('LULUS', $hasil, $pesan);

        $this->assertSame('T',
            DB::table('N_SOP_Progress')
                ->where('Id_SOP_Penjadwalan_Detail', $d->detailId())
                ->where('Id_SOP_Paket_Item', $d->idItem[0])
                ->value('Status_Lulus'),
            $pesan.' (Status_Lulus tercatat lulus)');
    }

    #[Group('UAT-SOP-035')]
    public function test_nilai_di_bawah_ambang_tidak_diluluskan(): void
    {
        $d = $this->dunia();

        $this->assertTidakLulus($d, $this->kirim($d, 40, 70),
            'Nilai 40 dengan ambang 70 diluluskan.');
    }

    /**
     * Sisi positif. Gerbang yang menggagalkan SEMUA ORANG lulus uji di atas
     * dengan sempurna sambil membuat paket berkuis mustahil diselesaikan.
     */
    #[Group('UAT-SOP-035')]
    public function test_nilai_di_atas_ambang_dinyatakan_lulus(): void
    {
        $d = $this->dunia();

        $this->assertSame('LULUS', $this->kirim($d, 85, 70));
    }

    #[Group('UAT-SOP-035')]
    public function test_nilai_tepat_di_ambang_dinyatakan_lulus(): void
    {
        $d = $this->dunia();

        $this->assertSame('LULUS', $this->kirim($d, 70, 70),
            'Nilai yang PERSIS sama dengan ambang minimum harus lulus — "minimum" '
            .'berarti batas bawah yang diterima, bukan batas yang harus dilewati.');
    }

    /**
     * Perkara yang paling mudah lolos dari mata: ambang kosong.
     *
     * Dulu Nilai_Minimum NULL berarti "lulus otomatis" — skor 0 pun lolos, dan
     * itu diam-diam meloloskan orang yang sama sekali tidak memahami SOP-nya.
     * Kolomnya opsional di Builder, jadi keadaan ini bukan kemungkinan teoretis
     * melainkan kelalaian pengisian yang wajar terjadi.
     */
    #[Group('UAT-SOP-035')]
    public function test_ambang_kosong_tidak_meloloskan_nilai_nol(): void
    {
        $d = $this->dunia();

        $this->assertTidakLulus($d, $this->kirim($d, 0, null),
            'Ambang lulus yang belum diisi meloloskan nilai 0. Item kuis yang '
            .'ambangnya lupa diisi menjadi item yang tak menguji apa pun.');
    }
}
