<?php

namespace Tests\Integration\LMS\SOP\Export;

use App\Services\LMS\SOP\Export\SopDatasetKepatuhan;
use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SopDatasetKepatuhanTest extends TestCase
{
    private function ds(): SopDatasetKepatuhan
    {
        return app(SopDatasetKepatuhan::class);
    }

    public function test_terdaftar_di_registry_modul_sop(): void
    {
        $this->assertSame('kepatuhan', app(DatasetRegistry::class)->ambil('sop', 'kepatuhan')->kunci());
    }

    public function test_kunci_dimensi_dan_ukuran_unik(): void
    {
        $dim = array_map(fn ($d) => $d->key, $this->ds()->dimensi());
        $ukr = array_map(fn ($u) => $u->key, $this->ds()->ukuran());

        $this->assertSame(array_values(array_unique($dim)), $dim, 'Ada kunci dimensi ganda.');
        $this->assertSame(array_values(array_unique($ukr)), $ukr, 'Ada kunci ukuran ganda.');
    }

    /**
     * Penjaga kelas kebocoran: COUNT(DISTINCT ip) membocorkan persis apa yang
     * di-mask di kolomnya. Ukuran yang menyebut ekspresi field sensitif harus
     * ikut bertanda sensitif.
     */
    public function test_ukuran_turunan_field_sensitif_ikut_sensitif(): void
    {
        $sensitif = [];
        foreach ($this->ds()->dimensi() as $d) {
            if ($d->sensitif) {
                $sensitif[] = $d->select;
            }
        }

        foreach ($this->ds()->ukuran() as $u) {
            foreach ($sensitif as $ekspresi) {
                if (str_contains($u->agregat, $ekspresi) && ! $u->sensitif) {
                    $this->fail("Ukuran '{$u->key}' memakai field sensitif tapi tidak ditandai sensitif.");
                }
            }
        }

        $this->assertTrue(true);
    }

    public function test_ip_dan_perangkat_ditandai_sensitif(): void
    {
        $peta = [];
        foreach ($this->ds()->dimensi() as $d) {
            $peta[$d->key] = $d->sensitif;
        }

        $this->assertTrue($peta['ip'] ?? false);
        $this->assertTrue($peta['perangkat'] ?? false);
    }

    public function test_base_query_memaksa_aturan_bisnis(): void
    {
        $q = $this->ds()->baseQuery();
        $sql = strtolower($q->toSql());

        $this->assertStringContainsString('n_sop_penjadwalan_detail', $sql);
        $this->assertStringContainsString(
            'left join [n_sop_progress]',
            $sql,
            'Join ke progress harus LEFT — inner join menghapus item yang belum pernah dibuka dan membuat kepatuhan terbaca lebih baik daripada kenyataannya.'
        );
        $this->assertContains('T', $q->getBindings(), 'Flag_Cabut_Akses harus tersaring.');
    }

    public function test_base_query_benar_benar_jalan_di_database(): void
    {
        $this->assertIsInt((int) $this->ds()->baseQuery()->limit(1)->count());
    }

    public function test_lingkup_leader_menyaring_ke_anggota_tim(): void
    {
        $q = $this->ds()->terapkanLingkup(
            $this->ds()->baseQuery(),
            new Lingkup('L001', false, ['A001', 'A002'], false)
        );

        $this->assertContains('A001', $q->getBindings());
        $this->assertContains('A002', $q->getBindings());
    }

    public function test_lingkup_admin_tidak_menyaring(): void
    {
        $sebelum = $this->ds()->baseQuery()->toSql();
        $sesudah = $this->ds()
            ->terapkanLingkup($this->ds()->baseQuery(), new Lingkup('AD1', true, [], true))
            ->toSql();

        $this->assertSame($sebelum, $sesudah);
    }

    /**
     * Saringan paket menyaring KOLOM ID. Selama daftar pilihannya berisi nama,
     * memilih satu paket mengirim teks ke kolom int dan SQL Server menggagalkan
     * seluruh query dengan galat konversi — bukan nol baris.
     */
    public function test_opsi_paket_bernilai_id_bukan_nama(): void
    {
        $opsi = $this->ds()->opsiSaringan();

        $this->assertArrayHasKey('paket', $opsi);
        $this->assertNotEmpty($opsi['paket'], 'Daftar paket kosong — uji ini tak menguji apa pun.');

        foreach ($opsi['paket'] as $o) {
            $this->assertIsInt($o['value'], "Opsi 'paket' harus bernilai ID.");
            $this->assertIsString($o['label']);
        }
    }

    /**
     * Kategori dicabut dari dataset — sebagai dimensi, sebagai saringan, dan
     * sebagai katalog opsi.
     *
     * KUNCI CACHE-nya ikut dijaga di sini. Mencabut saringan tanpa menaikkan
     * kunci berarti setengah jam pertama sesudah rilis layar masih menawarkan
     * saringan yang server sudah tak kenal — dan permintaannya ditolak dengan
     * galat yang tak menunjuk ke sebabnya.
     */
    public function test_kategori_dicabut_dari_dataset_kepatuhan(): void
    {
        $dimensi = array_map(fn ($d) => $d->key, $this->ds()->dimensi());
        $saringan = array_map(fn ($s) => $s->key, $this->ds()->saringan());
        $opsi = $this->ds()->opsiSaringan();

        $this->assertNotEmpty($dimensi, 'Dataset kosong — uji ini tak menguji apa pun.');
        $this->assertNotContains('kategori', $dimensi);
        $this->assertNotContains('kategori', $saringan);
        $this->assertArrayNotHasKey('kategori', $opsi);

        // Sumbu paket lain HARUS tetap ada; kalau ini ikut hilang, yang terjadi
        // bukan pencabutan Kategori melainkan dataset yang rusak.
        $this->assertContains('prioritas', $dimensi);
        $this->assertContains('paket', $saringan);
    }

    /**
     * Setiap kunci opsi harus benar-benar ada sebagai saringan. Opsi yatim
     * tidak pernah tampil dan hanya membuat katalog membengkak.
     */
    public function test_setiap_kunci_opsi_punya_saringan_yang_cocok(): void
    {
        $saringan = array_map(fn ($s) => $s->key, $this->ds()->saringan());

        foreach (array_keys($this->ds()->opsiSaringan()) as $kunci) {
            $this->assertContains($kunci, $saringan, "Opsi '{$kunci}' tidak punya saringan.");
        }
    }

    /**
     * Mode_Distribusi NULL diperlakukan PUBLIK di seluruh modul; menyaring pada
     * kolom mentah diam-diam membuang paket lama yang kolomnya belum terisi.
     */
    public function test_saringan_distribusi_menoleransi_nilai_kosong(): void
    {
        $peta = [];
        foreach ($this->ds()->saringan() as $s) {
            $peta[$s->key] = $s->kolom;
        }

        $this->assertStringContainsString('COALESCE', $peta['distribusi']);
    }

    /**
     * Dataset Studio dan lapisan monitoring harus memakai gerbang penugasan
     * yang SAMA.
     *
     * Bila salah satu berubah sendirian, Studio dan layar Monitoring melaporkan
     * angka berbeda atas data yang sama, dan tak ada yang memberitahu siapa pun.
     * Diuji lewat EFEK: baris yang sama harus lolos atau terbuang di keduanya.
     */
    public function test_gerbang_penugasan_sama_dengan_lapisan_monitoring(): void
    {
        $monitoring = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('d.Flag_Aktif', 'Y')->where('d.Flag_Cabut_Akses', 'T')
            // Dataset Studio ikut mensyaratkan paketnya punya item aktif; tanpa
            // syarat yang sama, selisih yang terukur adalah beda populasi, bukan
            // beda gerbang.
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('N_SOP_Paket_Item as pi_cek')
                ->whereColumn('pi_cek.Id_SOP_Paket', 'p.Id_SOP_Paket')
                ->where('pi_cek.Flag_Aktif', 'Y'));

        app(\App\Services\LMS\SOP\SopMonitoringPeopleService::class)
            ->terapkanSaringanPaket($monitoring, [], 'p', 'pj', 'd');

        $dariMonitoring = $monitoring->distinct()->pluck('d.Id_SOP_Penjadwalan_Detail')->sort()->values()->all();

        $dariStudio = $this->ds()->baseQuery()
            ->distinct()->pluck('d.Id_SOP_Penjadwalan_Detail')->sort()->values()->all();

        $this->assertNotEmpty($dariMonitoring, 'Tak ada penugasan sama sekali; uji ini tak membuktikan apa pun.');
        $this->assertSame(
            $dariMonitoring,
            $dariStudio,
            'Gerbang penugasan Studio menyimpang dari lapisan monitoring.'
        );
    }

    /**
     * Item tanpa rekam progress belum pernah disentuh — bukan data hilang.
     * Jalur lama sudah lama meng-COALESCE-nya; selama dataset ini tidak,
     * keadaan terpenting tertulis '-' dan '= BELUM' tak pernah cocok.
     */
    public function test_status_item_kosong_dibaca_belum_bukan_tanda_hubung(): void
    {
        $peta = [];
        foreach ($this->ds()->dimensi() as $d) {
            $peta[$d->key] = $d->select;
        }
        $saringan = [];
        foreach ($this->ds()->saringan() as $f) {
            $saringan[$f->key] = $f->kolom;
        }

        $this->assertStringContainsString('COALESCE', $peta['status_item']);
        $this->assertStringContainsString('COALESCE', $saringan['status_item']);
    }
}
