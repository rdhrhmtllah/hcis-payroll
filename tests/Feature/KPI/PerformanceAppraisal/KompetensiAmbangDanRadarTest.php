<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaMasterReadService;
use App\Services\KPI\PerformanceAppraisal\PaMasterWriteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Ambang nilai (KKM) dan penanda sumbu radar pada kompetensi — DDL-2026-096.
 *
 * Dua kolom, dan hampir seluruh isi berkas ini menjaga satu perbedaan yang mudah hilang:
 * ambang KOSONG tidak sama dengan ambang NOL. Kosong berarti HC belum memutuskan, dan
 * sumbunya dilewati saat menggambar jaring KKM. Nol berarti HC memutuskan standarnya nol,
 * dan jaringnya penyok ke pusat. Kode yang melewatkan nilai kosong ke `(float)` mengubah
 * yang pertama menjadi yang kedua tanpa satu pun galat, dan bentuk radar yang salah tidak
 * akan pernah mengeluh.
 *
 * Yang kedua: kompetensi kualitatif tidak boleh menjadi sumbu radar. Ia membawa teks, tidak
 * pernah punya baris di `N_PA_Result_Competency`, dan sebagai sumbu ia selamanya kosong —
 * kegagalan yang persis melahirkan `Flag_Berskor` pada DDL-2026-067.
 */
class KompetensiAmbangDanRadarTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ambang_kosong_tersimpan_null_bukan_nol(): void
    {
        $id = $this->buatKompetensi(['nilai_ambang' => '']);

        $baris = DB::table('N_PA_Competency')->where('Id_PA_Competency', $id)->first();

        $this->assertNotNull($baris);
        $this->assertNull(
            $baris->Nilai_Ambang,
            'Ambang kosong harus tersimpan NULL. Tersimpan 0, ia berhenti berarti "belum '
            .'ditetapkan" dan mulai berarti "standarnya nol" — jaring KKM lalu penyok ke '
            .'pusat pada sumbu yang sebenarnya belum diputuskan.'
        );
    }

    public function test_ambang_nol_yang_disengaja_tetap_tersimpan_sebagai_nol(): void
    {
        /* Pasangan wajib dari uji di atas. Tanpa ini, `ambangKompetensi()` bisa
           "diperbaiki" menjadi `if (! $nilai) return null;` — yang membuat uji sebelumnya
           tetap hijau sambil diam-diam membuang nol yang memang dimaksudkan. */
        $id = $this->buatKompetensi(['nilai_ambang' => 0]);

        $this->assertSame(
            0.0,
            (float) DB::table('N_PA_Competency')->where('Id_PA_Competency', $id)->value('Nilai_Ambang'),
            'Nol yang diketik HC adalah keputusan, bukan kekosongan.'
        );
    }

    public function test_ambang_di_luar_nol_sampai_seratus_ditolak(): void
    {
        foreach ([-1, 100.01, 125, 1000] as $diluar) {
            try {
                $this->buatKompetensi(['nilai_ambang' => $diluar]);
                $this->fail("Ambang {$diluar} berada di luar 0–100 dan seharusnya ditolak.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('0', $e->getMessage());
            }
        }
    }

    public function test_batas_nol_dan_seratus_masih_diterima(): void
    {
        /* Batasnya INKLUSIF di kedua ujung. Tanpa uji ini, `>` yang tertulis `>=` (atau
           sebaliknya) hanya terasa pada satu angka di ujung — dan angka itulah yang dipakai
           orang yang menetapkan standar sempurna. */
        foreach ([0, 100] as $batas) {
            $id = $this->buatKompetensi(['nilai_ambang' => $batas]);

            $this->assertSame(
                (float) $batas,
                (float) DB::table('N_PA_Competency')->where('Id_PA_Competency', $id)->value('Nilai_Ambang'),
            );
        }
    }

    public function test_ambang_bukan_angka_ditolak(): void
    {
        $this->expectException(RuntimeException::class);

        $this->buatKompetensi(['nilai_ambang' => 'delapan puluh']);
    }

    public function test_kompetensi_tak_berskor_tidak_bisa_jadi_sumbu_radar(): void
    {
        try {
            $this->buatKompetensi(['flag_berskor' => 'T', 'flag_radar' => 'Y']);
            $this->fail('Kompetensi tak berskor seharusnya ditolak menjadi sumbu radar.');
        } catch (RuntimeException $e) {
            // Pesannya harus menyebut SEBABNYA, bukan sekadar "tidak boleh". Yang membaca
            // sedang mencentang kotak dan berhak tahu kenapa kotak itu tidak bisa dicentang.
            $this->assertStringContainsString('radar', strtolower($e->getMessage()));
        }
    }

    public function test_kompetensi_tak_berskor_tidak_bisa_punya_ambang(): void
    {
        $this->expectException(RuntimeException::class);

        $this->buatKompetensi([
            'flag_berskor' => 'T',
            'flag_radar' => 'T',
            'nilai_ambang' => 75,
        ]);
    }

    public function test_kompetensi_tak_berskor_sah_selama_keluar_radar_dan_tanpa_ambang(): void
    {
        /* Uji PENERIMAAN yang memasangi dua penolakan di atas. Tanpa ini, penjaga yang
           menolak SEMUA kompetensi kualitatif — termasuk yang benar — tetap membuat kedua
           uji penolakan hijau. */
        $id = $this->buatKompetensi(['flag_berskor' => 'T', 'flag_radar' => 'T']);

        $baris = DB::table('N_PA_Competency')->where('Id_PA_Competency', $id)->first();

        $this->assertSame('T', $baris->Flag_Berskor);
        $this->assertSame('T', $baris->Flag_Radar);
        $this->assertNull($baris->Nilai_Ambang);
    }

    public function test_flag_radar_baku_menyala_untuk_kompetensi_berskor(): void
    {
        /* Bakunya 'Y', dan itu disengaja: menambah kompetensi berskor tanpa menyebut apa pun
           harus menghasilkan perilaku yang sama seperti sebelum kolomnya ada. */
        $id = $this->buatKompetensi([]);

        $this->assertSame('Y', DB::table('N_PA_Competency')
            ->where('Id_PA_Competency', $id)->value('Flag_Radar'));
    }

    public function test_katalog_baca_memulangkan_ambang_dan_penanda_radar(): void
    {
        $id = $this->buatKompetensi(['nilai_ambang' => 82.5, 'flag_radar' => 'Y']);

        $baris = $this->baca()->detail('kompetensi', $id)['baris'];

        /* Kolomnya harus ada di KATALOG baca, bukan sekadar ada di tabel. `detail()`
           memilih kolom secara eksplisit lewat `$k['kolom']`, jadi kolom yang lupa
           didaftarkan di sana tetap tersimpan rapi di database dan tidak pernah sampai ke
           layar mana pun. */
        $this->assertArrayHasKey('Nilai_Ambang', $baris);
        $this->assertArrayHasKey('Flag_Radar', $baris);
        $this->assertSame(82.5, (float) $baris['Nilai_Ambang']);
        $this->assertSame('Y', $baris['Flag_Radar']);
    }

    public function test_menyunting_kolom_lain_tidak_menghapus_ambang_yang_sudah_ada(): void
    {
        /* Jalur simpan menulis SELURUH kolom setiap kali. Formulir yang lupa menyertakan
           `nilai_ambang` karenanya tidak sekadar "tidak mengubahnya" — ia mengosongkannya.
           Uji ini mengunci bahwa muatan yang menyertakan ambangnya memang mempertahankannya,
           supaya jalur suntingnya tidak diam-diam menjadi jalur penghapusan. */
        $id = $this->buatKompetensi(['nilai_ambang' => 70]);
        $kode = DB::table('N_PA_Competency')->where('Id_PA_Competency', $id)->value('Kode_Competency');

        $this->tulis()->simpan('kompetensi', $id, [
            'pa_competency_type_id' => $this->tipeBerskor(),
            'kode_competency' => $kode,
            'nama_competency' => 'Nama Sesudah Disunting',
            'nilai_ambang' => 70,
            'flag_radar' => 'Y',
        ], 1);

        $baris = DB::table('N_PA_Competency')->where('Id_PA_Competency', $id)->first();

        $this->assertSame('Nama Sesudah Disunting', $baris->Nama_Competency);
        $this->assertSame(70.0, (float) $baris->Nilai_Ambang);
    }

    /**
     * @param  array<string, mixed>  $tambahan
     */
    private function buatKompetensi(array $tambahan): int
    {
        /* Huruf besar sejak dibangkitkan: `kode()` menjalankan strtoupper() sebelum menulis,
           sedangkan bin2hex memulangkan huruf kecil. Membandingkan masukan mentah dengan
           nilai tersimpan akan hijau hanya ketika kelima karakternya kebetulan angka semua. */
        $kode = strtoupper('UJI'.substr(bin2hex(random_bytes(3)), 0, 5));

        $hasil = $this->tulis()->simpan('kompetensi', null, array_merge([
            'pa_competency_type_id' => $this->tipeBerskor(),
            'kode_competency' => $kode,
            'nama_competency' => 'Kompetensi Uji Ambang',
            'urutan_tampil' => 99,
        ], $tambahan), 1);

        return (int) $hasil['id'];
    }

    private function tipeBerskor(): int
    {
        $id = DB::table('N_PA_Competency_Type')->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Competency_Type')->value('Id_PA_Competency_Type');

        $this->assertNotNull($id, 'Master jenis kompetensi kosong; uji ini butuh minimal satu.');

        return (int) $id;
    }

    private function baca(): PaMasterReadService
    {
        return $this->app->make(PaMasterReadService::class);
    }

    private function tulis(): PaMasterWriteService
    {
        return $this->app->make(PaMasterWriteService::class);
    }
}
