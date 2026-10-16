<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaCycleSetupService;
use App\Services\KPI\PerformanceAppraisal\PaCycleSetupStepService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;

/**
 * Wizard penyiapan harus ditempuh RUNUT.
 *
 * Tiap langkah menuliskan salinannya sendiri dari master, dan langkah berikutnya menyusun
 * setelannya di atas salinan itu — paket soal atas level yang bobotnya sudah ditetapkan,
 * aturan penilai atas paket yang sudah dipilih. Melompat berarti menyetel sesuatu di atas
 * dasar yang belum ada, dan akibatnya baru ketahuan jauh kemudian ketika penugasan
 * dibentuk.
 *
 * DUA HAL YANG MUDAH TERTUKAR, dan berkas ini menjaga keduanya tetap terpisah:
 *
 *  - `boleh_disunting` menjawab "status siklusnya mengizinkan?"
 *  - `dapat_dibuka` menjawab "gilirannya sudah tiba?"
 *
 * Menggabungkannya membuat layar tidak bisa membedakan siklus yang terkunci dari langkah
 * yang belum tiba gilirannya — dan keterangan yang muncul akan salah pada salah satu dari
 * dua keadaan itu.
 */
class UrutanLangkahSetupTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleId;

    protected function setUp(): void
    {
        parent::setUp();

        /* Dibuat lewat jalur sungguhan, bukan INSERT mentah: sejak 6 September 2026
           pembuatan siklus IKUT menyalin cetak biru master, dan justru keadaan sesudah
           penyalinan itulah yang diuji di sini. */
        $this->cycleId = $this->layananSetup()->buat([
            'nama_cycle' => 'Siklus Uji Urutan Langkah',
            'tahun' => (int) date('Y'),
            'periode_dinilai_dari' => date('Y').'-01-01',
            'periode_dinilai_sampai' => date('Y').'-06-30',
        ], 1)['cycle_id'];
    }

    public function test_langkah_pertama_selalu_bisa_dibuka(): void
    {
        $langkah = $this->langkah();

        $this->assertTrue($langkah[0]['dapat_dibuka'],
            'Langkah pertama tidak punya langkah sebelumnya; ia tak mungkin terkunci urutan.');
        $this->assertNull($langkah[0]['alasan_urutan']);
    }

    public function test_langkah_yang_gilirannya_belum_tiba_terkunci_dan_menyebut_sebabnya(): void
    {
        $terkunci = array_values(array_filter($this->langkah(), fn ($l) => ! $l['dapat_dibuka']));

        $this->assertNotEmpty($terkunci,
            'Tidak ada satu pun langkah terkunci pada siklus baru; aturan urutannya tidak bekerja.');

        foreach ($terkunci as $l) {
            // Sebab yang tidak disebut memaksa admin menebak apakah ia kurang hak, salah
            // urutan, atau memang siklusnya sudah lewat.
            $this->assertNotEmpty($l['alasan_urutan'], "Langkah {$l['kode']} terkunci tanpa sebab.");
            $this->assertStringContainsString('lebih dulu', $l['alasan_urutan']);

            // Dan yang terkunci urutan BUKAN berarti siklusnya terkunci.
            $this->assertTrue($l['boleh_disunting'],
                "Langkah {$l['kode']} dilaporkan terkunci status siklus, padahal siklusnya DRAFT.");
        }
    }

    public function test_pindah_ke_langkah_yang_belum_gilirannya_ditolak_server(): void
    {
        $sasaran = $this->langkahTerkunciPertama();

        try {
            $this->step()->pindah($this->cycleId, $sasaran['kode'], 1);
            $this->fail("Pindah ke {$sasaran['kode']} seharusnya ditolak.");
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('lebih dulu', $e->getMessage());
        }

        /* Ditolak berarti TIDAK berpindah. Penjaga yang melempar tetapi posisinya sudah
           terlanjur ditulis meninggalkan admin di langkah yang katanya tak boleh dibuka. */
        $this->assertNotSame($sasaran['kode'], DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $this->cycleId)->value('Langkah_Setup_Aktif'));
    }

    public function test_langkah_yang_sudah_selesai_tetap_bisa_dibuka(): void
    {
        /* Keadaan ini lahir sendiri: cetak biru master yang disalin saat siklus dibuat
           menyelesaikan bobot pilar dan aturan penilai sekaligus, sementara paket soal di
           antaranya belum. Menguncinya berarti admin tidak bisa MEMERIKSA setelan yang
           sudah jadi — padahal tidak ada apa pun yang dilompati. */
        $selesai = array_values(array_filter(
            $this->langkah(),
            fn ($l) => $l['status'] === PaCycleSetupStepService::STATUS_SELESAI,
        ));

        $this->assertNotEmpty($selesai,
            'Siklus baru tidak punya satu pun langkah selesai; cetak biru master tidak tersalin.');

        foreach ($selesai as $l) {
            $this->assertTrue($l['dapat_dibuka'],
                "Langkah {$l['kode']} sudah selesai tetapi tidak bisa dibuka.");
        }

        // Dan benar-benar bisa dipindahi, bukan sekadar dilaporkan begitu.
        $this->step()->pindah($this->cycleId, $selesai[0]['kode'], 1);

        $this->assertSame($selesai[0]['kode'], DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $this->cycleId)->value('Langkah_Setup_Aktif'));
    }

    public function test_langkah_terbuka_setelah_pendahulunya_selesai(): void
    {
        $sasaran = $this->langkahTerkunciPertama();

        // Seluruh langkah sebelum sasaran ditandai selesai — itulah satu-satunya syaratnya.
        foreach ($this->langkah() as $l) {
            if ($l['urut'] >= $sasaran['urut']) {
                break;
            }

            $this->step()->tandai($this->cycleId, $l['kode'], PaCycleSetupStepService::STATUS_SELESAI, 'uji', 1);
        }

        $sesudah = array_values(array_filter($this->langkah(), fn ($l) => $l['kode'] === $sasaran['kode']))[0];

        $this->assertTrue($sesudah['dapat_dibuka'],
            "Langkah {$sasaran['kode']} tetap terkunci padahal pendahulunya sudah selesai.");

        $this->step()->pindah($this->cycleId, $sasaran['kode'], 1);

        $this->assertSame($sasaran['kode'], DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $this->cycleId)->value('Langkah_Setup_Aktif'));
    }

    /* ------------------------------------------------------------------ *
     * Bangunan uji                                                        *
     * ------------------------------------------------------------------ */

    /** @return list<array<string, mixed>> */
    /**
     * SIKLUS TERKUNCI BUKAN JALAN BUNTU.
     *
     * `SUNTING_PESERTA` dan `BUAT_PENUGASAN` berhenti di PREPARED — menyiapkan atau membentuk
     * ULANG sesudah siklus dikunci memang tidak boleh. Tetapi `KECUALIKAN_PESERTA` dan
     * `TAMBAH_PENILAI` di server sengaja mengizinkan LOCKED, sebab justru di sanalah
     * kebutuhannya muncul: seseorang resign, atau HC Learn menolak akunnya.
     *
     * Sampai 10 September 2026 layar mematikan keduanya dengan satu bendera yang membaca
     * gerbang pertama saja, sehingga siklus terkunci tidak menyisakan satu tombol pun untuk
     * membereskan penilai yang ditolak HC Learn — persis kebuntuan pada siklus PA-2026-02.
     *
     * CARA MEMBUATNYA MERAH: hapus `gerbang_tambahan` dari definisi langkah, atau kembalikan
     * `kemampuan` menjadi gema `boleh_disunting`.
     */
    public function test_siklus_terkunci_masih_mengizinkan_perbaikan_satu_orang(): void
    {
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)->update([
            'Status_Cycle' => 'LOCKED',
        ]);

        $peserta = $this->cari('PESERTA');
        $penugasan = $this->cari('PENUGASAN');

        /* Menyiapkan ULANG memang sudah tertutup — itu bagian dari premisnya. Tanpa
           penegasan ini, uji di bawah tetap hijau pada layar yang membuka SEGALANYA. */
        $this->assertFalse($peserta['boleh_disunting']);
        $this->assertFalse($penugasan['boleh_disunting']);

        $this->assertTrue(
            $peserta['kemampuan']['kecualikan_peserta'] ?? false,
            'Mengecualikan satu peserta ikut mati saat siklus dikunci; tidak ada jalan '
            .'membereskan orang yang ternyata sudah resign.'
        );

        $this->assertTrue(
            $penugasan['kemampuan']['ubah_penilai'] ?? false,
            'Mengganti satu penilai ikut mati saat siklus dikunci; penilai yang ditolak '
            .'HC Learn tidak akan pernah bisa diganti.'
        );
    }

    /** @return array<string, mixed> */
    private function cari(string $kode): array
    {
        foreach ($this->langkah() as $l) {
            if ($l['kode'] === $kode) {
                return $l;
            }
        }

        $this->fail("Langkah {$kode} tidak ada di keadaan penyiapan.");
    }

    private function langkah(): array
    {
        return $this->step()->keadaan($this->cycleId)['langkah'];
    }

    /** @return array<string, mixed> */
    private function langkahTerkunciPertama(): array
    {
        foreach ($this->langkah() as $l) {
            if (! $l['dapat_dibuka']) {
                return $l;
            }
        }

        $this->fail('Tidak ada langkah terkunci; aturan urutannya tidak bekerja.');
    }

    /* Namanya BUKAN `setup()`: nama method di PHP tidak peka huruf besar-kecil, jadi ia
       bertabrakan dengan `setUp()` milik PHPUnit dan berkasnya gagal di-parse. */
    private function layananSetup(): PaCycleSetupService
    {
        return $this->app->make(PaCycleSetupService::class);
    }

    private function step(): PaCycleSetupStepService
    {
        return $this->app->make(PaCycleSetupStepService::class);
    }
}
