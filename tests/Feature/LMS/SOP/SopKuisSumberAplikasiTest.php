<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopKuisDispatchService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Ujian SOP harus MEMPERKENALKAN DIRINYA ke HC Learn.
 *
 * HC Learn melayani dua pemanggil — LMS dan SOP — dan memakai penanda asal untuk
 * menentukan ke mana peserta dikembalikan seusai ujian. Tanpa penanda itu ia
 * jatuh ke bawaannya, LMS, sehingga karyawan yang berangkat dari halaman SOP
 * mendarat di modul yang sama sekali lain; kewajiban SOP-nya tampak belum
 * tuntas padahal ujiannya sudah dikerjakan.
 *
 * Diuji lewat PERMINTAAN YANG BENAR-BENAR TERKIRIM, bukan lewat isi array di
 * dalam service: yang menentukan perilaku HC Learn adalah badan HTTP-nya, dan
 * array yang benar bisa saja tak pernah sampai ke sana.
 */
class SopKuisSumberAplikasiTest extends TestCase
{
    use DatabaseTransactions;

    private function skenarioAtauSkip(): object
    {
        $b = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan', '=', 'pj.Id_SOP_Penjadwalan')
            ->where('i.Tipe_Item', 'KUIS')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->whereNotNull('v.Id_Master_Ujian')
            ->first(['i.Id_SOP_Paket', 'i.Id_SOP_Paket_Item', 'd.Kode_Karyawan']);

        if (! $b) {
            $this->markTestSkipped('Tak ada penugasan atas item KUIS ber-ujian.');
        }

        return $b;
    }

    /** Balasan HC Learn secukupnya supaya alurnya sampai ke ujung. */
    private function palsukanHcLearn(): void
    {
        Http::fake(['*' => Http::response([
            'success' => true,
            'result' => [[
                'Kode_Karyawan' => 'X',
                'Id_Ujian_Token' => 'token-uji',
                'Link' => 'https://hclearn.example/ujian/token-uji',
            ]],
        ], 200)]);
    }

    /** Badan permintaan pertama ke HC Learn, atau null bila tak ada. */
    private function badanPertama(): ?array
    {
        $kirim = null;
        Http::recorded(function ($request) use (&$kirim) {
            if ($kirim === null && str_contains($request->url(), 'penjadwalan/training')) {
                $kirim = json_decode($request->body(), true);
            }

            return true;
        });

        return $kirim;
    }

    public function test_permintaan_menyebut_sumber_aplikasi_sop(): void
    {
        $s = $this->skenarioAtauSkip();
        $this->palsukanHcLearn();

        app(SopKuisDispatchService::class)->launch(
            $s->Kode_Karyawan, (int) $s->Id_SOP_Paket, (int) $s->Id_SOP_Paket_Item);

        $badan = $this->badanPertama();
        if ($badan === null) {
            $this->markTestSkipped('Alurnya berhenti sebelum memanggil HC Learn (mis. gerbang presensi).');
        }

        $this->assertSame('SOP', $badan['Sumber_Aplikasi'] ?? null,
            'HC Learn tak diberi tahu pemanggilnya — peserta akan dikembalikan ke LMS.');
    }

    /**
     * User_Agent di kontrak ini adalah penanda PEMANGGIL, bukan jejak perangkat.
     *
     * Mengirim UA peramban ke sana membuat HC Learn membaca "Mozilla/5.0 …" dan
     * tak mengenali siapa yang memanggil.
     */
    public function test_user_agent_adalah_penanda_pemanggil_bukan_ua_peramban(): void
    {
        $s = $this->skenarioAtauSkip();
        $this->palsukanHcLearn();

        app(SopKuisDispatchService::class)->launch(
            $s->Kode_Karyawan, (int) $s->Id_SOP_Paket, (int) $s->Id_SOP_Paket_Item);

        $badan = $this->badanPertama();
        if ($badan === null) {
            $this->markTestSkipped('Alurnya berhenti sebelum memanggil HC Learn.');
        }

        $this->assertSame('SOP', $badan['User_Agent'] ?? null);
        $this->assertStringNotContainsStringIgnoringCase('mozilla', (string) ($badan['User_Agent'] ?? ''));
    }

    /**
     * Bentuk Id_Integrasi: DAFTAR pasangan, bukan skalar.
     *
     * Itulah yang dipakai HC Learn memetakan token yang kembali ke karyawan yang
     * benar. Skalar diterima tanpa keluhan lalu hasil ujiannya mendarat di orang
     * yang salah — kegagalan yang tak pernah terlihat sebagai galat.
     */
    public function test_id_integrasi_berbentuk_daftar_pasangan(): void
    {
        $s = $this->skenarioAtauSkip();
        $this->palsukanHcLearn();

        app(SopKuisDispatchService::class)->launch(
            $s->Kode_Karyawan, (int) $s->Id_SOP_Paket, (int) $s->Id_SOP_Paket_Item);

        $badan = $this->badanPertama();
        if ($badan === null || ! isset($badan['Id_Integrasi'])) {
            $this->markTestSkipped('Permintaannya jalur perpanjang, yang memang tak membawa Id_Integrasi.');
        }

        $this->assertIsArray($badan['Id_Integrasi']);
        $this->assertArrayHasKey('Kode_Karyawan', $badan['Id_Integrasi'][0]);
        $this->assertArrayHasKey('Id_Integrasi', $badan['Id_Integrasi'][0]);
        $this->assertSame($s->Kode_Karyawan, $badan['Id_Integrasi'][0]['Kode_Karyawan']);

        // Daftar karyawannya sejalan dengan pemetaan di atas.
        $this->assertSame([$s->Kode_Karyawan], $badan['Id_Karyawan'] ?? null);
    }

    /**
     * Penanda yang sama harus ikut pada PERPANJANGAN, bukan hanya pencetakan.
     *
     * Perpanjangan memakai token yang sama; tanpa penandanya, peserta yang
     * tokennya kebetulan masih hidup tetap dikembalikan ke LMS. Cacat yang
     * muncul pada sebagian orang jauh lebih sulit dipercaya daripada cacat yang
     * muncul pada semua.
     */
    public function test_jalur_perpanjang_membawa_penanda_yang_sama(): void
    {
        $r = new \ReflectionMethod(SopKuisDispatchService::class, 'perpanjang');
        $isi = file_get_contents((new \ReflectionClass(SopKuisDispatchService::class))->getFileName());

        $awal = $r->getStartLine();
        $badan = implode("\n", array_slice(explode("\n", $isi), $awal - 1, $r->getEndLine() - $awal + 1));

        /* Cocok longgar terhadap spasi: Pint merapikan perataan kunci array, dan
           asersi yang mematok jaraknya akan pecah oleh pemformatan — bukan oleh
           perilaku yang salah. */
        $this->assertMatchesRegularExpression("/'Sumber_Aplikasi'\s*=>\s*self::SUMBER/", $badan,
            'Perpanjangan token dikirim tanpa penanda asal.');
        $this->assertMatchesRegularExpression("/'User_Agent'\s*=>\s*self::SUMBER/", $badan);
    }

    /** Satu tetapan, bukan empat teks bebas yang bisa menyimpang diam-diam. */
    public function test_penandanya_satu_tetapan(): void
    {
        $c = new \ReflectionClass(SopKuisDispatchService::class);

        $this->assertSame('SOP', $c->getConstant('SUMBER'));
    }

    /* ── sopuniq: penunjuk halaman asal peserta ────────────────────── */

    public function test_muatan_membawa_sopuniq_hashid_paket(): void
    {
        $s = $this->skenarioAtauSkip();
        $this->palsukanHcLearn();

        app(SopKuisDispatchService::class)->launch(
            $s->Kode_Karyawan, (int) $s->Id_SOP_Paket, (int) $s->Id_SOP_Paket_Item);

        $badan = $this->badanPertama();
        if ($badan === null) {
            $this->markTestSkipped('Alurnya berhenti sebelum memanggil HC Learn.');
        }

        $this->assertSame(
            Hashids::connection('custom')->encode((int) $s->Id_SOP_Paket),
            $badan['Sopuniq'] ?? null,
            'sopuniq bukan hashid PAKET — HC Learn tak bisa menyusun kembali /sop/{hashid}.',
        );
    }

    /**
     * Tautan yang diserahkan ke peserta membawa sopuniq walau HC Learn belum
     * menuliskannya. Itulah gunanya rangkap ini: selama sisi sana belum memakai
     * field baru, peserta tetap membawa penunjuknya.
     */
    public function test_tautan_ditempeli_sopuniq_bila_balasan_belum_membawanya(): void
    {
        $svc = app(SopKuisDispatchService::class);
        $m = new \ReflectionMethod($svc, 'tempelSopuniq');
        $m->setAccessible(true);

        $hash = Hashids::connection('custom')->encode(23);

        $this->assertSame(
            "/evo/third-party?wo_aut=SJF&otp=93483&sopuniq={$hash}",
            $m->invoke($svc, '/evo/third-party?wo_aut=SJF&otp=93483', 23),
        );

        // Tautan tanpa query sama sekali tetap dapat pemisah yang benar.
        $this->assertSame("/evo/third-party?sopuniq={$hash}", $m->invoke($svc, '/evo/third-party', 23));
    }

    /**
     * Kalau HC Learn SUDAH menuliskannya, jangan ditulis dua kali — nilai kedua
     * yang berbeda akan membuat tujuan kembali bergantung pada parser siapa yang
     * membacanya lebih dulu.
     */
    public function test_sopuniq_tak_ditempel_dua_kali(): void
    {
        $svc = app(SopKuisDispatchService::class);
        $m = new \ReflectionMethod($svc, 'tempelSopuniq');
        $m->setAccessible(true);

        $asli = '/evo/third-party?wo_aut=SJF&sopuniq=SUDAH_ADA';
        $this->assertSame($asli, $m->invoke($svc, $asli, 23));
    }

    /**
     * Query yang ditempel SESUDAH '#' bukan query sama sekali — ia jadi bagian
     * dari fragment dan tak pernah terbaca server mana pun.
     */
    public function test_sopuniq_disisipkan_sebelum_fragment(): void
    {
        $svc = app(SopKuisDispatchService::class);
        $m = new \ReflectionMethod($svc, 'tempelSopuniq');
        $m->setAccessible(true);

        $hash = Hashids::connection('custom')->encode(23);
        $this->assertSame(
            "/evo/third-party?otp=1&sopuniq={$hash}#mulai",
            $m->invoke($svc, '/evo/third-party?otp=1#mulai', 23),
        );
    }

    /**
     * KETIGA pintu keluar tautan melewati penempelan yang sama.
     *
     * Token yang dipakai ulang, yang baru dicetak, dan yang diperpanjang —
     * menempelkannya di dua di antaranya menghasilkan cacat yang paling sulit
     * dipercaya: sebagian peserta pulang ke tempat yang benar, sebagian tidak,
     * tanpa pola yang kelihatan dari luar.
     */
    public function test_semua_pintu_keluar_tautan_lewat_satu_penempel(): void
    {
        $isi = file_get_contents((new \ReflectionClass(SopKuisDispatchService::class))->getFileName());

        $this->assertSame(0, substr_count($isi, "return ['url' => \$link]"),
            'Masih ada tautan yang diserahkan tanpa melewati tautan().');
        $this->assertSame(3, substr_count($isi, '$this->tautan($link, $idPaket)'),
            'Jumlah pintu keluar berubah — pastikan yang baru ikut menempelkan sopuniq.');
    }
}
