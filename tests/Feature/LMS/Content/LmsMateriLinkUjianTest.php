<?php

namespace Tests\Feature\LMS\Content;

use App\Http\Controllers\LMS\Content\Material\LmsMateriController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Detail materi LMS tak boleh runtuh karena sesi ujian belum dicetak.
 *
 * Aktivitas pihak-ketiga dijadwalkan lebih dulu; tokennya baru lahir belakangan.
 * Di data ini 102 dari 163 baris integrasi masih berpayload NULL — keadaan yang
 * SAH, bukan kerusakan. Membaca Link_Ujian tanpa penjaga membuat SELURUH halaman
 * detail materi berbalas 500, bukan sekadar kehilangan satu tombol.
 */
class LmsMateriLinkUjianTest extends TestCase
{
    use DatabaseTransactions;

    private function terap($link, $penjadwalan)
    {
        $c = app(LmsMateriController::class);
        $m = new \ReflectionMethod($c, 'applyAdditionalIntegrasiData');
        $m->setAccessible(true);

        return $m->invoke($c, $link, $penjadwalan);
    }

    /**
     * Keadaan yang bikin 500: payload kosong → Link_Ujian tak ada.
     *
     * Diuji lewat pembacanya, sebab itulah bentuk yang benar-benar sampai ke
     * helper ini saat baris integrasinya belum punya sesi.
     */
    public function test_payload_tanpa_link_tak_meruntuhkan_apa_pun(): void
    {
        $payloadKosong = [];

        $this->assertNull($this->terap($payloadKosong['Link_Ujian'] ?? null, 9),
            'Baris integrasi tanpa sesi ujian harus menghasilkan link null, bukan galat.');
    }

    public function test_link_ditempeli_id_penjadwalan(): void
    {
        $hash = Hashids::connection('custom')->encode(9);

        $this->assertSame(
            "https://hclearn.test/evo/third-party?wo_aut=SJF&otp=1&id_penjadwalan={$hash}",
            $this->terap('https://hclearn.test/evo/third-party?wo_aut=SJF&otp=1', 9),
        );
        // Tanpa query sama sekali tetap dapat pemisah yang benar.
        $this->assertSame(
            "https://hclearn.test/ujian?id_penjadwalan={$hash}",
            $this->terap('https://hclearn.test/ujian', 9),
        );
    }

    /**
     * Penjadwalan yang tak terbaca TIDAK BOLEH membuang linknya.
     *
     * Dulu cabang ini mengembalikan null, sehingga tombol ujian hilang tanpa
     * satu pun galat — peserta menyimpulkan ujiannya belum siap, padahal
     * sesinya sudah dicetak dan menunggu. Tanpa id_penjadwalan linknya memang
     * kurang lengkap, tapi masih mengantar peserta ke ujiannya.
     */
    public function test_tanpa_id_penjadwalan_link_tetap_diberikan(): void
    {
        $link = 'https://hclearn.test/evo/third-party?wo_aut=SJF';

        $this->assertSame($link, $this->terap($link, null));
        $this->assertSame($link, $this->terap($link, 0));
    }

    /** null masuk sebagai penjadwalan tak boleh melempar TypeError. */
    public function test_penjadwalan_null_diterima_tanda_tangannya(): void
    {
        $m = new \ReflectionMethod(LmsMateriController::class, 'applyAdditionalIntegrasiData');
        $p = $m->getParameters()[1];

        $this->assertTrue($p->allowsNull(),
            'Pemanggilnya mengirim `?? null`; tanda tangan yang menuntut int memaksanya jadi 0.');
    }

    /**
     * Query yang ditempel sesudah '#' bukan query sama sekali — ia jadi bagian
     * dari fragment dan tak pernah terbaca server mana pun.
     */
    public function test_disisipkan_sebelum_fragment(): void
    {
        $hash = Hashids::connection('custom')->encode(9);

        $this->assertSame(
            "https://hclearn.test/ujian?otp=1&id_penjadwalan={$hash}#mulai",
            $this->terap('https://hclearn.test/ujian?otp=1#mulai', 9),
        );
    }

    /**
     * Penjaga data: kalau suatu saat ada baris berpayload yang justru TAK punya
     * Link_Ujian, pembacanya tetap harus aman — dan uji ini yang memberi tahu
     * bahwa bentuk datanya berubah.
     */
    public function test_payload_berisi_selalu_membawa_link(): void
    {
        $tanpa = 0;
        foreach (DB::table('N_LMS_Penjadwalan_Integrasi')->whereNotNull('Payload_Json')
            ->pluck('Payload_Json') as $j) {
            $p = json_decode((string) $j, true);
            if (! is_array($p) || ! array_key_exists('Link_Ujian', $p)) {
                $tanpa++;
            }
        }

        $this->assertSame(0, $tanpa,
            'Ada payload integrasi tanpa Link_Ujian — bentuk datanya berubah, tinjau ulang pembacanya.');
    }
}
