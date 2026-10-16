<?php

namespace Tests\Feature\LMS\SOP\Uat;

use App\Services\LMS\SOP\SopPenjadwalanTargetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\Feature\LMS\SOP\Uat\Support\UatDunia;
use Tests\TestCase;

/**
 * UAT-SOP-022 — jumlah pada pratinjau sasaran harus sama dengan yang benar-benar
 * tersimpan.
 *
 * Skenario KONTROL, dan salah satu yang paling pantas dipercayakan pada mesin:
 * manusia tak bisa menghitung ratusan baris lintas halaman tanpa keliru, dan
 * justru karena itulah selisihnya bisa bertahun-tahun tak ketahuan. Karyawan
 * yang luput dari penugasan tidak mengeluh — ia tidak tahu ada yang harus
 * dibacanya.
 *
 * Yang dijaga bukan cuma kesamaan angka, tapi juga KETERUSTERANGAN: setiap
 * pembatasan jumlah harus dinyatakan, bukan diam-diam memotong.
 */
class Kontrol022PratinjauSasaranTest extends TestCase
{
    use DatabaseTransactions;

    private function dunia(): UatDunia
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan keenam hak akses halaman dokumen.');
        }

        try {
            return UatDunia::mulai((int) $admin->Id_Users)
                ->buatDokumenTerbit()
                ->buatPaket()
                ->tambahItem('Item Sasaran')
                ->terbitkanPaket();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }
    }

    /**
     * Saringan unit yang benar-benar berisi orang di database ini.
     *
     * DUA HAL YANG MUDAH SALAH, dan keduanya sempat saya lakukan:
     *
     *   1. filterOptions() mengembalikan Collection berisi STRING biasa (hasil
     *      pluck), bukan array asosiatif ber-'value'/'id'. Casting `(array)`
     *      atas sebuah Collection membongkar PROPERTI objeknya, bukan isinya —
     *      dan nilai yang lolos ke query menjadi 0.
     *   2. Kunci opsinya 'departemen', tapi kunci SARINGANNYA 'dept'. Memakai
     *      nama opsi sebagai nama saringan membuat baseKandidat() mengabaikannya
     *      diam-diam, sehingga hasilnya seluruh karyawan — dan ujinya lulus atas
     *      pertanyaan yang tak pernah ditanyakan.
     */
    private function saringanBerisi(SopPenjadwalanTargetService $svc): array
    {
        $opsi = $svc->filterOptions();

        $peta = ['divisi' => 'divisi', 'departemen' => 'dept', 'lokasi' => 'lokasi'];

        foreach ($peta as $kunciOpsi => $kunciSaringan) {
            $daftar = $opsi[$kunciOpsi] ?? [];
            $daftar = is_object($daftar) && method_exists($daftar, 'all') ? $daftar->all() : (array) $daftar;

            foreach ($daftar as $nilai) {
                if (! is_string($nilai) || trim($nilai) === '') {
                    continue;
                }
                $f = [$kunciSaringan => [$nilai]];
                if (count($svc->resolveTargets($f)) > 0) {
                    return $f;
                }
            }
        }

        $this->markTestSkipped(
            'Tidak ada saringan unit yang menghasilkan kandidat di database ini, '
            .'sehingga kesamaan pratinjau dan hasil tak bisa dibuktikan.'
        );
    }

    #[Group('UAT-SOP-022')]
    public function test_jumlah_pratinjau_sama_dengan_jumlah_sasaran_yang_diselesaikan(): void
    {
        $d = $this->dunia();
        $svc = app(SopPenjadwalanTargetService::class);
        $f = $this->saringanBerisi($svc);

        $ringkas = $svc->hitungRingkas($f, null, $d->idPaket);
        $sasaran = $svc->resolveTargets($f);

        $this->assertSame(count($sasaran), (int) $ringkas['total'],
            'Angka yang dilihat admin di pratinjau berbeda dari daftar yang benar-benar '
            .'diselesaikan sistem. Selisihnya adalah karyawan yang tak pernah tahu ada '
            .'dokumen yang harus dibacanya.');
    }

    /**
     * Daftar sasaran harus DETERMINISTIK. Tanpa urutan yang pasti, dua panggilan
     * berturut-turut bisa mengembalikan susunan berbeda — dan pada daftar yang
     * dipenggal per halaman, orang yang sama bisa hilang di antara dua halaman
     * tanpa pernah tampak hilang.
     */
    #[Group('UAT-SOP-022')]
    public function test_daftar_sasaran_stabil_antar_pemanggilan(): void
    {
        $svc = app(SopPenjadwalanTargetService::class);
        $f = $this->saringanBerisi($svc);

        $this->assertSame($svc->resolveTargets($f), $svc->resolveTargets($f),
            'Dua pemanggilan menghasilkan urutan berbeda; penomoran halaman di atasnya '
            .'tak bisa dipercaya.');
    }

    #[Group('UAT-SOP-022')]
    public function test_nik_yang_tidak_dikenal_dilaporkan_bukan_dibuang_diam_diam(): void
    {
        $svc = app(SopPenjadwalanTargetService::class);

        $asli = DB::table('Karyawan')->where('Aktif', 'Y')->value('Kode_Karyawan');
        if (! $asli) {
            $this->markTestSkipped('Tidak ada karyawan aktif untuk dijadikan pembanding.');
        }

        $ngawur = 'UAT-AUTO-NIK-TIDAK-ADA';
        $hasil = $svc->validasiTempel(trim($asli)."\n".$ngawur);

        $this->assertContains($ngawur, $hasil['tidak_ditemukan'],
            'NIK yang tidak dikenal tidak dilaporkan. Admin akan mengira seluruh '
            .'daftar tempelannya diterima, padahal sebagian lenyap.');
        $this->assertNotEmpty($hasil['valid'],
            'NIK yang sah ikut ditolak; penyaringnya menolak segalanya.');
    }

    /**
     * Pembatasan jumlah tempelan harus DILAPORKAN, bukan memotong diam-diam.
     * Kode yang dibuang tanpa jejak tak masuk kategori mana pun — admin tak
     * punya cara mengetahui bahwa daftarnya tidak utuh.
     */
    #[Group('UAT-SOP-022')]
    public function test_kelebihan_tempelan_dilaporkan_lewat_penanda_terpotong(): void
    {
        $svc = app(SopPenjadwalanTargetService::class);

        $banyak = [];
        for ($i = 0; $i < 1200; $i++) {
            $banyak[] = 'UAT-AUTO-N'.$i;
        }

        $hasil = $svc->validasiTempel(implode("\n", $banyak));

        $this->assertArrayHasKey('terpotong', $hasil,
            'Tak ada penanda jumlah yang dipotong pada hasil validasi tempel.');
        $this->assertGreaterThan(0, (int) $hasil['terpotong'],
            'Tempelan 1200 baris tidak melaporkan satu pun yang terpotong, padahal '
            .'batasnya di bawah itu — artinya kelebihannya lenyap tanpa jejak.');
    }
}
