<?php

namespace Tests\Feature\ESS\Attendance;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Daftar cuti bersama harus berbiaya TETAP, bukan sebanding jumlah acaranya.
 *
 * Saat penjaga ini ditulis, layar ini menembak **102 kueri** untuk 16 acara:
 * satu kueri induk lalu enam kueri per baris —
 *
 *   32x  baris N_HRIS_Cloud_Task_Log terbaru (EXECUTE dan ROLLBACK, 2x per baris)
 *   16x  agregat hitungan dari N_HRIS_Cuti_Bersama_Detail
 *   16x  hitungan dari N_HRIS_Cuti_Bersama_Exclusion
 *   16x  MAX(Rolled_Back_At) dari N_HRIS_Cuti_Bersama_Detail
 *   16x  katalog kolom, dari Schema::hasColumn() yang dipanggil per baris
 *
 * Tak ada yang rusak dan tak ada error — layarnya hanya makin lambat setiap
 * kali seorang admin menambah acara cuti bersama. Pada 100 acara bentuk itu
 * berarti sekitar 600 kueri, pada 500 acara sekitar 3.000.
 *
 * AMBANGNYA RELATIF, BUKAN ANGKA MATI.
 * Yang dijaga bukan "tepat 10 kueri" melainkan "tidak tumbuh per baris", jadi
 * ambangnya dibandingkan dengan JUMLAH BARIS yang benar-benar ada di basis
 * data. Ambang mati akan lolos diam-diam kalau suatu hari data devnya menyusut.
 */
class CutiBersamaDaftarKueriTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Kueri tetap yang wajar: beberapa milik kerangka kerja & perizinan,
     * ditambah satu kueri induk dan tiga kueri pelengkap massal.
     * Longgar, tapi masih jauh di bawah bentuk per-baris.
     */
    private const AMBANG_TETAP = 20;

    /**
     * Di bawah ini, ambang tetapnya tidak lagi mengikat: dengan sedikit baris,
     * pola per-baris pun masih muat di bawah 20 dan penjaganya jadi hijau
     * palsu. Lebih baik melewatkan uji dengan terang-terangan.
     */
    private const BARIS_MINIMUM = 12;

    public function test_biaya_daftar_cuti_bersama_tidak_tumbuh_per_acara(): void
    {
        $this->withoutMiddleware();

        $jumlahBaris = DB::table('N_HRIS_Cuti_Bersama')->where('Flag_Aktif', 'Y')->count();

        if ($jumlahBaris < self::BARIS_MINIMUM) {
            $this->markTestSkipped(
                "Hanya {$jumlahBaris} acara cuti bersama aktif di data dev; di bawah "
                .self::BARIS_MINIMUM.' baris ambangnya tak mengikat dan uji ini tak membuktikan apa pun.'
            );
        }

        $cacah = 0;
        DB::listen(function () use (&$cacah) {
            $cacah++;
        });

        $res = $this->getJson('/api/v1/cuti-bersama-adv/list');

        $res->assertOk();

        $this->assertLessThanOrEqual(
            self::AMBANG_TETAP,
            $cacah,
            "Daftar cuti bersama menjalankan {$cacah} kueri untuk {$jumlahBaris} acara. "
            .'Batasnya '.self::AMBANG_TETAP.' — biayanya harus tetap, bukan per baris. '
            .'Kalau angkanya naik seiring jumlah acara, ada kueri yang kembali masuk '
            .'ke dalam perulangan; ambil massal sebelum map(), lihat ambilPelengkapDaftar().'
        );

        /* Pemeriksaan kedua yang menangkap hal berbeda: sekalipun ambang tetapnya
           suatu hari dinaikkan, jumlah kueri tak boleh menyamai jumlah baris. */
        $this->assertLessThan(
            $jumlahBaris,
            $cacah,
            "Jumlah kueri ({$cacah}) mencapai jumlah acara ({$jumlahBaris}) — itu bentuk per-baris."
        );
    }

    /** Perbaikan biaya tak ada gunanya kalau isi yang tampil ikut berubah. */
    public function test_daftar_tetap_membawa_pelengkap_tiap_baris(): void
    {
        $this->withoutMiddleware();

        $res = $this->getJson('/api/v1/cuti-bersama-adv/list');
        $res->assertOk();

        $daftar = $res->json('result.list');
        $this->assertIsArray($daftar);

        if ($daftar === []) {
            $this->markTestSkipped('Tidak ada acara cuti bersama aktif di data dev.');
        }

        foreach ($daftar as $baris) {
            $this->assertArrayHasKey('detail_counts', $baris);
            $this->assertArrayHasKey('total_detail', (array) $baris['detail_counts']);
            $this->assertArrayHasKey('exclusion_count', $baris);
            $this->assertArrayHasKey('selected_dates_parsed', $baris);
            $this->assertArrayHasKey('execution_snapshot', $baris);
            $this->assertArrayHasKey('status', $baris['execution_snapshot']);

            // Kunci ini ADA walau isinya null; hilangnya berarti layar kehilangan kolom.
            $this->assertTrue(array_key_exists('latest_execute_task', $baris));
            $this->assertTrue(array_key_exists('latest_rollback_task', $baris));
        }
    }

    /**
     * ORDER BY daftar wajib memuat kolom UNIK sebagai pemutus seri.
     *
     * Created_At tidak unik — di dev, enam dari enam belas acara berbagi detik
     * yang sama persis. Selama ORDER BY hanya memakai kolom itu, urutan di dalam
     * kelompok kembar tidak terdefinisi: basis data boleh mengembalikannya
     * berbeda antar permintaan, dan begitu daftarnya dipotong per halaman, satu
     * baris bisa muncul di dua halaman sementara baris lain tak pernah muncul.
     *
     * KENAPA INI PENJAGA BENTUK, BUKAN PENJAGA PERILAKU.
     * Sudah dicoba menulisnya sebagai penjaga perilaku — memanggil semua halaman
     * lalu menuntut tiap baris muncul tepat sekali. Uji itu TETAP HIJAU ketika
     * pemutus serinya sengaja dibuang, sebab SQL Server kebetulan mengembalikan
     * urutan yang sama untuk kueri yang sama. Yang salah bukan "urutannya
     * berbeda hari ini", melainkan "urutannya tidak dijamin" — dan jaminan tak
     * bisa dibuktikan dengan satu kali menjalankan. Perubahan indeks atau rencana
     * paralel bisa mengubahnya kapan saja tanpa satu pun error.
     *
     * Maka yang dituntut di sini adalah janji yang bisa diperiksa mesin: kueri
     * induknya mengurutkan sampai ke kolom yang unik.
     */
    public function test_urutan_daftar_memakai_pemutus_seri_unik(): void
    {
        $this->withoutMiddleware();

        $kueri = [];
        DB::listen(function ($q) use (&$kueri) {
            $kueri[] = $q->sql;
        });

        $this->getJson('/api/v1/cuti-bersama-adv/list')->assertOk();

        $induk = null;
        foreach ($kueri as $sql) {
            if (str_contains($sql, 'from [N_HRIS_Cuti_Bersama]') && str_contains($sql, 'order by')) {
                $induk = $sql;
                break;
            }
        }

        $this->assertNotNull($induk, 'Kueri induk daftar cuti bersama tidak ditemukan dalam rekaman.');

        $urutan = substr($induk, (int) strripos($induk, 'order by'));

        $this->assertStringContainsString(
            'Id_Cuti_Bersama',
            $urutan,
            "ORDER BY daftar tidak memuat kolom unik sebagai pemutus seri:\n  {$urutan}\n\n"
            .'Created_At tidak unik, jadi tanpa pemutus seri urutan baris kembar tidak '
            .'dijamin dan paginasi bisa menampilkan satu baris dua kali sementara baris '
            .'lain tak pernah muncul.'
        );
    }

    /**
     * Gabungan seluruh halaman memuat tiap baris tepat sekali.
     *
     * Ini BUKAN penjaga bagi pemutus seri — lihat catatan di uji sebelumnya,
     * bentuk ini terbukti tetap hijau saat pemutus serinya dibuang. Yang
     * ditangkapnya adalah kesalahan paginasi yang kasar: salah hitung offset,
     * total_pages yang tak cocok dengan total, atau potongan yang meleset satu.
     */
    public function test_paginasi_tidak_menduplikasi_atau_menghilangkan_baris(): void
    {
        $this->withoutMiddleware();

        $pertama = $this->getJson('/api/v1/cuti-bersama-adv/list?page=1');
        $pertama->assertOk();

        $total = (int) $pertama->json('result.pagination.total');
        $perHalaman = (int) $pertama->json('result.pagination.per_page');
        $jumlahHalaman = (int) $pertama->json('result.pagination.total_pages');

        if ($total <= $perHalaman) {
            $this->markTestSkipped("Hanya {$total} acara — belum ada halaman kedua untuk dibandingkan.");
        }

        $terkumpul = [];
        for ($h = 1; $h <= $jumlahHalaman; $h++) {
            $res = $this->getJson('/api/v1/cuti-bersama-adv/list?page='.$h);
            $res->assertOk();
            foreach ($res->json('result.list') ?? [] as $baris) {
                $terkumpul[] = (int) $baris['Id_Cuti_Bersama'];
            }
        }

        $kembar = array_keys(array_filter(array_count_values($terkumpul), fn ($n) => $n > 1));

        $this->assertSame(
            [],
            $kembar,
            'Acara berikut muncul di lebih dari satu halaman: '.implode(', ', $kembar)
            .'. Penyebab paling lazim: ORDER BY tanpa pemutus seri, sehingga baris '
            .'ber-Created_At kembar berpindah posisi antar permintaan.'
        );

        $this->assertCount(
            $total,
            $terkumpul,
            'Gabungan semua halaman memuat '.count($terkumpul)." baris, sedangkan totalnya {$total}. "
            .'Ada baris yang tidak pernah muncul di halaman mana pun.'
        );
    }

    /**
     * Kolom pemilih ROW_NUMBER tidak boleh ikut terkirim.
     *
     * Pengambilan massal log tugas memakai ROW_NUMBER() untuk memilih baris
     * terbaru. Kolom bantunya hanya alat; kalau ia lolos ke jawaban, layar
     * menerima medan yang dahulu tak pernah ada.
     */
    public function test_kolom_bantu_pemilih_tidak_bocor_ke_jawaban(): void
    {
        $this->withoutMiddleware();

        $res = $this->getJson('/api/v1/cuti-bersama-adv/list');
        $res->assertOk();

        foreach ($res->json('result.list') ?? [] as $baris) {
            foreach (['latest_execute_task', 'latest_rollback_task'] as $medan) {
                if (is_array($baris[$medan] ?? null)) {
                    $this->assertArrayNotHasKey('urutan', $baris[$medan], "{$medan} membawa kolom bantu 'urutan'.");
                }
            }
        }
    }
}
