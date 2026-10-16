<?php

namespace Tests\Feature\LMS\SOP\Export;

use App\Services\LMS\SOP\Export\SopDatasetDokumen;
use App\Services\LMS\SOP\SopDokumenKesehatanService;
use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\Support\SopDokumenKelompok;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dua pintu, satu aturan — diuji LEWAT EFEKNYA.
 *
 * Kelompok dokumen dihitung dua kali dengan cara yang berbeda: dashboard
 * merakit query builder lalu menggabungkan di PHP, Export Studio memakai LEFT
 * JOIN atas daftar id. Keduanya meminjam klausa yang sama dari
 * SopDokumenKelompok, tetapi "memanggil kelas yang sama" bukan bukti hasilnya
 * sama — bukti satu-satunya adalah angkanya.
 *
 * Pola bug yang dijaga di sini sudah berulang di modul SOP: sebuah aturan
 * dipasang di satu dari dua jalur setara, dan selisihnya baru terbaca
 * berbulan-bulan kemudian sebagai "angka dashboard beda dengan angka laporan".
 *
 * UJI INI MEMBUAT DATANYA SENDIRI, tidak menumpang keadaan database dev.
 * Alasannya keras: pada database yang tabel penjadwalannya kosong, SELURUH
 * dokumen jatuh ke satu kelompok, dan perbandingan apa pun akan lulus tanpa
 * pernah menyentuh cabang WAJIB maupun aturan "wajib menang". Penjaga yang
 * tak pernah melihat kasusnya tak bisa dibedakan dari penjaga yang tak ada.
 *
 * Fixture-nya meniru SopDokumenKesehatanTest apa adanya — termasuk
 * Status_Paket yang sengaja bukan 'BELUM', karena gerbang paket publik hanya
 * menghitung penugasan yang sudah tersentuh.
 */
class SopDokumenKelompokTest extends TestCase
{
    use DatabaseTransactions;

    private function dokumenBaru(string $judul): int
    {
        return app(SopDokumenService::class)->buat([
            'Judul' => $judul.' '.substr((string) microtime(true), -5),
            'Tipe' => 'DOKUMEN',
        ], 1);
    }

    /** Paket TERBIT berisi satu item yang menunjuk dokumen. */
    private function paketBerisi(int $idDokumen, string $distribusi = 'TERBATAS'): int
    {

        $idPaket = DB::table('N_SOP_Master_Paket')->insertGetId([
            'Nama_Paket' => 'PAKET UJI KEL '.substr((string) microtime(true), -6),
            'Status' => SopStatus::PAKET_PUBLISHED,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Mode_Distribusi' => $distribusi,
        ], 'Id_SOP_Paket');

        DB::table('N_SOP_Paket_Item')->insert([
            'Id_SOP_Paket' => $idPaket,
            'Tipe_Item' => 'DOKUMEN',
            'Judul_Item' => 'Item uji',
            'Id_SOP_Dokumen' => $idDokumen,
            'Flag_Aktif' => SopStatus::AKTIF,
        ]);

        return $idPaket;
    }

    private function tugaskan(int $idPaket): void
    {
        $idJadwal = DB::table('N_SOP_Penjadwalan')->insertGetId([
            'Id_SOP_Paket' => $idPaket,
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Penjadwalan');

        DB::table('N_SOP_Penjadwalan_Detail')->insert([
            'Id_SOP_Penjadwalan' => $idJadwal,
            'Kode_Karyawan' => 'UJI001',
            'Status_Paket' => SopStatus::STATUS_PROGRESS,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Flag_Cabut_Akses' => 'T',
        ]);
    }

    /** @return array{wajib:int, terbuka:int, belum:int} id dokumen per kelompok yang diharapkan */
    private function siapkanKetigaKelompok(): array
    {
        $wajib = $this->dokumenBaru('SOP WAJIB');
        $this->tugaskan($this->paketBerisi($wajib));

        $terbuka = $this->dokumenBaru('SOP TERBUKA');
        $this->paketBerisi($terbuka);

        $belum = $this->dokumenBaru('SOP BELUM BEREDAR');

        return ['wajib' => $wajib, 'terbuka' => $terbuka, 'belum' => $belum];
    }

    /** Kelompok satu dokumen menurut dataset Export Studio. */
    private function kelompokDataset(int $idDokumen): ?string
    {
        return app(SopDatasetDokumen::class)->baseQuery()
            ->where('dok.Id_SOP_Dokumen', $idDokumen)
            ->selectRaw('('.SopDokumenKelompok::sqlKelompokKode().') AS Kelompok')
            ->value('Kelompok');
    }

    /** Kelompok satu dokumen menurut layanan dashboard. */
    private function kelompokDashboard(int $idDokumen): ?string
    {
        return app(SopDokumenKesehatanService::class)->klasifikasi()
            ->firstWhere('Id_SOP_Dokumen', $idDokumen)?->Kelompok;
    }

    /* ── Ketiga cabang benar-benar dilewati ─────────────────────────────── */

    public function test_dataset_mengelompokkan_ketiga_keadaan_dengan_benar(): void
    {
        $id = $this->siapkanKetigaKelompok();

        $this->assertSame(SopDokumenKelompok::DITUGASKAN, $this->kelompokDataset($id['wajib']));
        $this->assertSame(SopDokumenKelompok::BEREDAR, $this->kelompokDataset($id['terbuka']));
        $this->assertSame(SopDokumenKelompok::BELUM_BEREDAR, $this->kelompokDataset($id['belum']));
    }

    public function test_dataset_dan_dashboard_sepakat_pada_ketiga_kelompok(): void
    {
        $id = $this->siapkanKetigaKelompok();

        foreach ($id as $peran => $idDokumen) {
            $this->assertSame(
                $this->kelompokDashboard($idDokumen),
                $this->kelompokDataset($idDokumen),
                "Dokumen '{$peran}' dikelompokkan berbeda oleh dashboard dan Export Studio."
            );
        }
    }

    /**
     * WAJIB MENANG atas TERBUKA.
     *
     * Bukan sekadar urutan cabang CASE: sebuah dokumen bisa memenuhi keduanya
     * sekaligus — dijadwalkan di paket A, sekadar terbuka di paket B — dan yang
     * menentukan harus selalu ada tidaknya kewajiban, di mana pun.
     */
    public function test_wajib_menang_atas_terbuka(): void
    {
        $id = $this->dokumenBaru('SOP WAJIB SEKALIGUS TERBUKA');
        $this->paketBerisi($id);                       // beredar, tanpa penugasan
        $this->tugaskan($this->paketBerisi($id));      // sekaligus jadi kewajiban

        $this->assertSame(SopDokumenKelompok::DITUGASKAN, $this->kelompokDataset($id));
        $this->assertSame(SopDokumenKelompok::DITUGASKAN, $this->kelompokDashboard($id));
    }

    /**
     * Penugasan yang aksesnya dicabut tidak membuat dokumen jadi wajib.
     *
     * Menuntut PENOLAKAN, bukan penerimaan. Penjaga yang hanya diuji pada
     * kasus yang lolos tak pernah terbukti menyaring apa pun.
     */
    public function test_penugasan_dicabut_tidak_membuat_wajib(): void
    {
        $id = $this->dokumenBaru('SOP PENUGASAN DICABUT');
        $idPaket = $this->paketBerisi($id);
        $this->tugaskan($idPaket);

        DB::table('N_SOP_Penjadwalan_Detail')
            ->whereIn('Id_SOP_Penjadwalan', DB::table('N_SOP_Penjadwalan')->where('Id_SOP_Paket', $idPaket)->pluck('Id_SOP_Penjadwalan'))
            ->update(['Flag_Cabut_Akses' => 'Y']);

        // Turun ke TERBUKA, bukan tetap WAJIB: paketnya masih terbit.
        $this->assertSame(SopDokumenKelompok::BEREDAR, $this->kelompokDataset($id));
    }

    /* ── Butiran: satu dokumen, satu baris ──────────────────────────────── */

    /**
     * Dokumen yang dipakai tiga paket tetap SATU baris.
     *
     * Inilah kerusakan yang paling mudah lahir dari rancangan ini: tabel
     * turunan yang lupa DISTINCT/GROUP BY mengembalikan satu baris per
     * penugasan, dan dokumennya tergandakan di seluruh laporan — termasuk di
     * COUNT(*), tanpa satu pun galat.
     */
    public function test_dokumen_di_banyak_paket_tetap_satu_baris(): void
    {
        $id = $this->dokumenBaru('SOP DIPAKAI BANYAK PAKET');
        $this->tugaskan($this->paketBerisi($id));
        $this->tugaskan($this->paketBerisi($id));
        $this->paketBerisi($id);

        $baris = app(SopDatasetDokumen::class)->baseQuery()
            ->where('dok.Id_SOP_Dokumen', $id)->count();

        $this->assertSame(1, $baris, 'Satu dokumen harus satu baris, berapa pun paket yang memakainya.');
    }

    /** Dokumen tanpa paket mana pun tetap punya baris — bukan nol, bukan hilang. */
    public function test_dokumen_tanpa_paket_tetap_terlihat(): void
    {
        $id = $this->dokumenBaru('SOP TANPA PAKET');

        $this->assertSame(1, app(SopDatasetDokumen::class)->baseQuery()
            ->where('dok.Id_SOP_Dokumen', $id)->count());
    }

    /* ── Lingkup: leader tidak membaca seluruh register ─────────────────── */

    /**
     * Non-admin hanya melihat dokumen yang jadi kewajiban anggota timnya.
     *
     * Menuntut DUA hal sekaligus, dan keduanya perlu: yang menjadi kewajiban
     * timnya TERLIHAT, dan yang bukan TIDAK. Menguji satu arah saja membuat
     * lingkup yang kebetulan membuang segalanya — atau tak membuang apa pun —
     * tetap terbaca lulus.
     */
    public function test_lingkup_non_admin_hanya_dokumen_timnya(): void
    {
        $milikTim = $this->dokumenBaru('SOP MILIK TIM');
        $this->tugaskan($this->paketBerisi($milikTim));

        $bukanMilikTim = $this->dokumenBaru('SOP BUKAN MILIK TIM');
        $this->paketBerisi($bukanMilikTim);

        $ds = app(SopDatasetDokumen::class);
        $lingkup = new \App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup(
            kodeKaryawan: 'UJI001',
            adminPenuh: false,
            anggotaTim: ['UJI001'],
            bolehFieldTerbatas: false,
        );

        $terlihat = $ds->terapkanLingkup($ds->baseQuery(), $lingkup)
            ->pluck('dok.Id_SOP_Dokumen')->map(fn ($v) => (int) $v)->all();

        $this->assertContains($milikTim, $terlihat);
        $this->assertNotContains($bukanMilikTim, $terlihat);
    }

    /** Tim kosong berarti NOL baris, bukan seluruh register. */
    public function test_lingkup_tanpa_anggota_tim_menghasilkan_nol_baris(): void
    {
        $this->siapkanKetigaKelompok();

        $ds = app(SopDatasetDokumen::class);
        $lingkup = new \App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup(
            kodeKaryawan: 'UJI001',
            adminPenuh: false,
            anggotaTim: [],
            bolehFieldTerbatas: false,
        );

        $this->assertSame(0, $ds->terapkanLingkup($ds->baseQuery(), $lingkup)->count());
    }

    /**
     * Ketiga kelompok saling lepas dan menjumlah TEPAT ke jumlah dokumen aktif.
     *
     * Angka yang tak menjumlah adalah sumber keraguan yang tak pernah selesai,
     * dan dashboard yang diragukan tak dipakai siapa pun. Diperiksa pada jalur
     * dataset karena di sanalah bentuknya paling mudah rusak.
     */
    public function test_kelompok_menjumlah_tepat_ke_jumlah_dokumen_aktif(): void
    {
        $this->siapkanKetigaKelompok();

        $total = DB::table('N_SOP_Dokumen')->where('Flag_Aktif', SopStatus::AKTIF)->count();

        $rows = app(SopDatasetDokumen::class)->baseQuery()
            ->selectRaw('('.SopDokumenKelompok::sqlKelompokKode().') AS Kelompok, COUNT(*) AS Jml')
            ->groupBy(DB::raw(SopDokumenKelompok::sqlKelompokKode()))
            ->get();

        $jumlah = 0;
        $terlihat = [];
        foreach ($rows as $r) {
            $jumlah += (int) $r->Jml;
            $terlihat[] = (string) $r->Kelompok;
        }

        $this->assertSame($total, $jumlah, 'Jumlah per kelompok tidak sama dengan jumlah dokumen aktif.');

        // Ketiganya harus benar-benar muncul; fixture di atas menjaminnya.
        foreach (SopDokumenKesehatanService::KELOMPOK as $k) {
            $this->assertContains($k, $terlihat, "Kelompok '{$k}' tidak muncul sama sekali.");
        }
    }
}
