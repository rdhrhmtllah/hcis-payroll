<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\PerformanceAppraisal\Katalog\PaBagianKatalog;
use App\Services\KPI\PerformanceAppraisal\PaHcLearnCatalogService;
use App\Services\KPI\PerformanceAppraisal\PaQuestionBindingMasterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Ikatan paket soal PA berbasis bagian — gerbang simpan, kesehatan, dan penyalinan.
 *
 * TIGA SIFAT YANG DIJAGA, dan ketiganya pernah salah di modul lain:
 *
 *  1. `matriks()` TIDAK memanggil HC Learn. Halaman yang memanggil layanan luar tiap dibuka
 *     ikut mati ketika layanan itu mati.
 *  2. Sel yang bermasalah TIDAK disegarkan sidiknya. Menyegarkannya menghapus bukti bahwa ia
 *     bermasalah, dan pemeriksaan berikutnya melaporkannya bersih padahal tak ada yang
 *     diperbaiki.
 *  3. `salinKeSiklus()` memeriksa SEGAR. Status tersimpan bisa berumur berminggu-minggu;
 *     memercayainya di gerbang penyalinan berarti meloloskan ujian rusak ke siklus baru.
 *
 * CARA MEMBUATNYA MERAH
 *
 *  - uji matriks: kembalikan pemanggilan `daftarPaket()` ke dalam `matriks()`;
 *  - uji simpan: buang pemanggilan `PaBijeksiLabel::periksa()` dari `simpan()`;
 *  - uji sidik: hapus penjaga `if ($masalah === [])` di `periksaSel()`;
 *  - uji salin: ganti pemanggilan `periksaSel()` di `salinKeSiklus()` dengan pembacaan kolom
 *    `Status_Kesehatan`.
 */
class PaIkatanPaketSoalTest extends TestCase
{
    private PaQuestionBindingMasterService $service;

    private int $levelId;

    /** @var list<int> */
    private array $wajib;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PaQuestionBindingMasterService::class);

        /* Level diambil dari DATA, bukan ditulis sebagai angka. Angka mutlak di fixture akan
           berbohong begitu matriks kompetensi berubah — dan matriks kompetensi memang berubah,
           itu justru salah satu pemicu yang dijaga rancangan ini. */
        $baris = DB::table('N_PA_Competency_Requirement')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('ID_Level')
            ->first(['ID_Level']);

        if ($baris === null) {
            $this->markTestSkipped('Data syarat kompetensi kosong; uji ini tidak bermakna.');
        }

        $this->levelId = (int) $baris->ID_Level;
        $this->wajib = $this->service->kompetensiWajibLevel($this->levelId);

        $this->palsukanKatalog();
    }

    /**
     * Katalog HC Learn dipalsukan DI TINGKAT HTTP, bukan lewat jalur kode tersendiri.
     *
     * Sejak simulasi katalog dibuang (9 September 2026), `PaHcLearnCatalogService` hanya
     * punya satu sumber. Memalsukannya di sini berarti yang teruji adalah jalur yang
     * benar-benar dipakai production — termasuk perataan empat tingkat dan penyeragaman
     * bentuknya — bukan cabang khusus uji yang tidak pernah dijalani siapa pun.
     *
     * Dua ujian diterbitkan: satu SEPADAN dengan cacah kompetensi wajib level uji, satu
     * lagi sengaja tidak, supaya gerbang penolakannya punya bahan.
     */
    private function palsukanKatalog(): void
    {
        /* Katalognya menandatangani permintaannya dengan identitas pengguna yang sedang
           masuk, jadi tanpa `actingAs` ia berhenti di "sambungan belum tersedia" sebelum
           sempat menyentuh HTTP palsu di bawah. */
        $this->actingAs($this->penggunaBerakses());

        $n = count($this->wajib);

        Http::fake([
            '*/api/v1/pa/nama-tes*' => Http::response([
                'success' => true,
                'result' => [
                    $this->ujianPalsu(900001, 'Uji Sepadan', $n),
                    $this->ujianPalsu(900002, 'Uji Tak Sepadan', $n + 1),

                    /* Sepadan KEDUA. Ada supaya penggantian paket satu sel bisa diuji
                       dengan ujian yang benar-benar berbeda — tanpa ini penjaganya
                       melewati dirinya sendiri, dan penjaga yang melewati diri sendiri
                       tidak menjaga apa pun. */
                    $this->ujianPalsu(900003, 'Uji Sepadan Kedua', $n),
                ],
            ]),
        ]);

        app(PaHcLearnCatalogService::class)->lupakanMemo();
    }

    private function penggunaBerakses(): User
    {
        $userId = DB::table('HRIS_Page_Access')
            ->where('Jenis_page', 'PerformanceAppraisalPage')
            ->where('Flag_Aktif', 'Y')
            ->whereNotNull('UserID_Web')
            ->value('UserID_Web');

        $this->assertNotNull(
            $userId,
            'Tidak ada user berakses PerformanceAppraisalPage; DML-2026-084 belum dijalankan.'
        );

        $user = User::find($userId);
        $this->assertNotNull($user, "User {$userId} tidak ditemukan.");

        return $user;
    }

    /**
     * Satu ujian berbentuk PERSIS balasan HC Learn: ujian → paket → alat tes → bagian.
     *
     * Bagiannya dipecah ke DUA alat tes supaya jalur identitas berpasangan
     * (id_jenis_indikator, id_jenis_soal) ikut terlatih — di sanalah satu bank soal bisa
     * muncul di dua alat tes berbeda, dan itu justru kasus yang dijaga rancangan ini.
     *
     * @return array<string, mixed>
     */
    private function ujianPalsu(int $id, string $nama, int $jumlahBagian): array
    {
        $alat = [[], []];

        for ($i = 0; $i < $jumlahBagian; $i++) {
            $alat[$i % 2][] = [
                'id_jenis_soal' => (string) (($id * 100) + $i),
                'kode_soal' => sprintf('K%02d', $i + 1),
                'nama' => 'Bagian '.($i + 1),
            ];
        }

        return [
            'id_master_ujian' => $id,
            'nama' => $nama,
            'kode' => 'UJI-'.$id,
            'kategori' => 'ujian',
            'paket' => [
                'id_master_paket_ujian' => $id + 500,
                'nama' => 'Paket '.$nama,
                'alat_tes' => [
                    ['id_jenis_indikator' => (string) ($id + 10), 'nama' => 'Alat A', 'bagian' => $alat[0]],
                    ['id_jenis_indikator' => (string) ($id + 11), 'nama' => 'Alat B', 'bagian' => $alat[1]],
                ],
            ],
        ];
    }

    /** Ujian sepadan PERTAMA. Ada dua di katalog palsu; yang kedua dipakai uji penggantian. */
    /** @return array<string, mixed>|null */
    private function ujianSepadan(): ?array
    {
        foreach (app(PaHcLearnCatalogService::class)->daftarPaket()['paket'] as $u) {
            if ($u['jumlah_bagian'] === count($this->wajib)) {
                return $u;
            }
        }

        return null;
    }

    /** @return array<string, int> */
    private function labelLengkap(array $ujian): array
    {
        $label = [];

        foreach (array_values($this->wajib) as $i => $kompetensiId) {
            $label[PaBagianKatalog::kunci($ujian['Bagian'][$i])] = $kompetensiId;
        }

        return $label;
    }

    public function test_matriks_tidak_memanggil_hc_learn(): void
    {
        app(PaHcLearnCatalogService::class)->lupakanMemo();

        Http::preventStrayRequests();
        Http::fake();

        app(PaQuestionBindingMasterService::class)->matriks();

        Http::assertNothingSent();
    }

    public function test_pemetaan_lengkap_tersimpan_beserta_detail_dan_indikatornya(): void
    {
        $ujian = $this->ujianSepadan();

        if ($ujian === null) {
            $this->markTestSkipped('Tidak ada ujian sepadan dengan kompetensi wajib level uji.');
        }

        $hasil = $this->service->simpan(
            $this->levelId, null, $ujian['Id_Master_Ujian'], $this->labelLengkap($ujian),
        );

        $this->assertSame('SEHAT', $hasil['status_kesehatan']);
        $this->assertSame(count($this->wajib), $hasil['jumlah_bagian']);

        $detail = DB::table('N_PA_Question_Binding_Master_Detail')
            ->where('PA_Question_Binding_Master_Id', $hasil['id'])
            ->where('Flag_Aktif', 'Y')
            ->get();

        $this->assertCount(count($this->wajib), $detail);

        /* Indikatornya WAJIB tersimpan. Tanpa itu identitas bagiannya tidak lengkap, dan dua
           bagian dari alat tes berbeda yang memakai bank soal sama akan bertabrakan. */
        $this->assertTrue(
            $detail->every(fn ($d) => $d->Id_Jenis_Indikator !== '' && $d->Id_Jenis_Indikator !== null),
            'Ada baris detail tanpa Id_Jenis_Indikator.',
        );
    }

    public function test_label_kurang_satu_ditolak_dan_tidak_menyisakan_apa_pun(): void
    {
        $ujian = $this->ujianSepadan();

        if ($ujian === null) {
            $this->markTestSkipped('Tidak ada ujian sepadan.');
        }

        $label = $this->labelLengkap($ujian);
        array_pop($label);

        $sebelum = DB::table('N_PA_Question_Binding_Master_Detail')->count();

        try {
            $this->service->simpan($this->levelId, null, $ujian['Id_Master_Ujian'], $label);
            $this->fail('Pemetaan yang tidak lengkap seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('kompetensi', strtolower($e->getMessage()));
        }

        /* Penolakan HARUS bersih. Header tersimpan tanpa detail adalah keadaan paling
           menyesatkan di alur ini: matriks melaporkan sel terisi, lalu penyalinannya kosong. */
        $this->assertSame($sebelum, DB::table('N_PA_Question_Binding_Master_Detail')->count());
    }

    public function test_ujian_yang_cacah_bagiannya_tidak_sepadan_ditolak(): void
    {
        $tidakSepadan = null;

        foreach (app(PaHcLearnCatalogService::class)->daftarPaket()['paket'] as $u) {
            if ($u['jumlah_bagian'] > 0 && $u['jumlah_bagian'] !== count($this->wajib)) {
                $tidakSepadan = $u;
                break;
            }
        }

        if ($tidakSepadan === null) {
            $this->markTestSkipped('Butuh ujian yang cacah bagiannya berbeda.');
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/bagian/i');

        $this->service->simpan($this->levelId, null, $tidakSepadan['Id_Master_Ujian'], []);
    }

    public function test_menyimpan_ulang_mengganti_detail_lama_bukan_menumpuknya(): void
    {
        $ujian = $this->ujianSepadan();

        if ($ujian === null) {
            $this->markTestSkipped('Tidak ada ujian sepadan.');
        }

        $label = $this->labelLengkap($ujian);
        $this->service->simpan($this->levelId, null, $ujian['Id_Master_Ujian'], $label);

        // Tukar dua label, lalu simpan ulang sel yang sama.
        $kunci = array_keys($label);

        if (count($kunci) >= 2) {
            [$label[$kunci[0]], $label[$kunci[1]]] = [$label[$kunci[1]], $label[$kunci[0]]];
        }

        $hasil = $this->service->simpan($this->levelId, null, $ujian['Id_Master_Ujian'], $label);

        $this->assertFalse($hasil['baru']);
        $this->assertSame(
            count($this->wajib),
            DB::table('N_PA_Question_Binding_Master_Detail')
                ->where('PA_Question_Binding_Master_Id', $hasil['id'])
                ->where('Flag_Aktif', 'Y')
                ->count(),
        );
    }

    public function test_matriks_menampilkan_status_tersimpan_beserta_cap_waktunya(): void
    {
        $ujian = $this->ujianSepadan();

        if ($ujian === null) {
            $this->markTestSkipped('Tidak ada ujian sepadan.');
        }

        $hasil = $this->service->simpan($this->levelId, null, $ujian['Id_Master_Ujian'], $this->labelLengkap($ujian));

        $m = $this->service->matriks();
        $level = collect($m['level'])->firstWhere('id_level', $this->levelId);

        /* Sel yang dicari adalah sel YANG BARU DISIMPAN, dikenali dari id barisnya — bukan
           "sel terisi yang pertama ditemui". Sebaris bisa memuat sel kategori lain dengan
           ikatannya sendiri, dan sel itu boleh saja sedang bermasalah karena sebab yang tak
           ada hubungannya dengan uji ini. Versi pertama uji ini memang membaca sel pertama,
           dan ia MERAH di suite penuh sementara hijau ketika berkasnya dijalankan sendiri —
           kegagalan yang menyamar jadi cacat kesehatan padahal cacat pemilihan sel. */
        $sel = collect($level['sel'] ?? [])->first(fn ($s) => ($s['id'] ?? null) === $hasil['id']);

        $this->assertNotNull($sel, 'Sel yang baru disimpan tidak muncul di matriks.');
        $this->assertSame('SEHAT', $sel['status_kesehatan']);
        $this->assertNotNull($sel['diperiksa_pada']);
        $this->assertSame($ujian['Id_Master_Ujian'], $sel['id_master_ujian']);
    }

    public function test_sel_bermasalah_tidak_disegarkan_sidiknya(): void
    {
        $ujian = $this->ujianSepadan();

        if ($ujian === null) {
            $this->markTestSkipped('Tidak ada ujian sepadan.');
        }

        $hasil = $this->service->simpan(
            $this->levelId, null, $ujian['Id_Master_Ujian'], $this->labelLengkap($ujian),
        );

        // Rusakkan dari sisi KITA: satu label dihapus, seolah bagiannya belum dipetakan.
        DB::table('N_PA_Question_Binding_Master_Detail')
            ->where('PA_Question_Binding_Master_Id', $hasil['id'])
            ->limit(1)
            ->delete();

        $sidikSebelum = DB::table('N_PA_Question_Binding_Master')
            ->where('Id_PA_Question_Binding_Master', $hasil['id'])
            ->value('Sidik_Bagian');

        $periksa = $this->service->periksaSel($hasil['id']);

        $this->assertSame('BERMASALAH', $periksa['status']);
        $this->assertContains('KOMPETENSI_KURANG', array_column($periksa['masalah'], 'jenis'));

        $this->assertSame($sidikSebelum, DB::table('N_PA_Question_Binding_Master')
            ->where('Id_PA_Question_Binding_Master', $hasil['id'])
            ->value('Sidik_Bagian'));

        // Statusnya TERSIMPAN, bukan sekadar dikembalikan — matriks membaca kolom ini.
        $this->assertSame('BERMASALAH', DB::table('N_PA_Question_Binding_Master')
            ->where('Id_PA_Question_Binding_Master', $hasil['id'])
            ->value('Status_Kesehatan'));
    }

    /**
     * IMPOR SATU SEL BENAR-BENAR MENDARAT DI TABEL BUTIRNYA.
     *
     * Ini lapisan yang selama ini buta. Uji-uji di atas berhenti di pemetaan MASTER —
     * yang tabelnya membolehkan urutan bernilai nol — sehingga tak satu pun menyentuh
     * `N_PA_Cycle_Question_Package_Item`, satu-satunya tabel yang menuntut
     * `Urutan_Tampil >= 1` lewat `CK_PA_Cycle_Question_Item_Urutan`.
     *
     * Akibat kebutaan itu: `ratakan()` memulangkan POSISI ARRAY (mulai dari nol), rumus
     * urutannya menyalin apa adanya, dan bagian PERTAMA setiap paket bernilai 0. Karena
     * seluruh butir disisipkan dalam SATU pernyataan, satu baris itu menggagalkan
     * seluruh impor — setiap paket, setiap sel, setiap kali. Admin melihatnya sebagai
     * SQLSTATE[23000] di layar Langkah 2, dan tidak ada satu pun uji yang merah.
     *
     * CARA MEMBUATNYA MERAH: buang `+ 1` di `PaBagianKatalog::urutanTampil()`.
     */
    public function test_impor_satu_sel_mendarat_dan_urutannya_sah(): void
    {
        $ujian = $this->ujianSepadan();
        $this->assertNotNull($ujian, 'Katalog palsu tidak memuat ujian yang sepadan.');

        $this->service->simpan(
            $this->levelId,
            null,
            (string) $ujian['Id_Master_Ujian'],
            $this->labelLengkap($ujian),
        );

        /* Siklusnya DIBUANG di akhir, berhasil maupun gagal. Berkas ini tidak memakai
           DatabaseTransactions, dan siklus uji yang tertinggal ikut terhitung di layar
           admin yang sedang dipakai orang. */
        $cycleId = $this->siklusDraft();

        try {
            app(PaHcLearnCatalogService::class)->impor(
                $cycleId,
                $this->levelId,
                (string) $ujian['Id_Master_Ujian'],
                null,
                null,
                false,
            );

            $paketId = DB::table('N_PA_Cycle_Question_Package')
                ->where('PA_Cycle_Id', $cycleId)
                ->where('ID_Level', $this->levelId)
                ->value('Id_PA_Cycle_Question_Package');

            $this->assertNotNull($paketId, 'Paket siklus tidak terbentuk.');

            $urutan = DB::table('N_PA_Cycle_Question_Package_Item')
                ->where('PA_Cycle_Question_Package_Id', $paketId)
                ->orderBy('Urutan_Tampil')
                ->pluck('Urutan_Tampil')
                ->map(fn ($v) => (int) $v)
                ->all();
        } finally {
            $this->buangSiklus($cycleId);
        }

        $this->assertCount(
            count($ujian['Bagian']),
            $urutan,
            'Cacah butir yang mendarat tidak sama dengan cacah bagian yang diimpor.'
        );

        /* Nilai terkecilnya, bukan sekadar "ada isinya". Nol lolos dari pemeriksaan
           jumlah baris tetapi ditolak basis data — dan penolakannya membatalkan
           seluruh sisipan, jadi tanpa penegasan ini uji tetap hijau di kode yang rusak
           hanya bila penyisipannya kebetulan dipecah. */
        $this->assertGreaterThanOrEqual(
            1,
            min($urutan),
            'Urutan_Tampil bernilai di bawah 1; CK_PA_Cycle_Question_Item_Urutan menolaknya.'
        );

        /* Urutannya juga harus BERBEDA satu sama lain. Menjepit nol dengan max(1, ...)
           akan lolos penegasan di atas tetapi membuat bagian pertama dan kedua sama,
           dan urutan pengerjaan yang dikirim HC Learn hilang diam-diam. */
        $this->assertSame(
            count($urutan),
            count(array_unique($urutan)),
            'Ada dua bagian berurutan sama; urutan pengerjaan dari HC Learn hilang.'
        );
    }

    /**
     * SEL YANG BELUM DILABELI TIDAK BOLEH MEMINJAM LABEL SEL LAIN.
     *
     * Ikatan master berkunci (ID_Level, PA_Rater_Category_Id) — tiap sel matriks punya
     * pelabelannya sendiri. Pencarian label di impor per-sel dulu menyaring level dan
     * ujian saja, sehingga sel kategori penilai yang tak pernah diisi tetap lolos: ia
     * diam-diam memakai label milik kategori lain, dan gerbang "belum dipetakan" tidak
     * pernah menyala.
     *
     * Yang terlihat admin: paket soal bisa dijatuhkan ke sel mana pun tanpa pernah
     * dilabeli — persis keluhan "asal pakai saja soalnya". Yang terjadi di data: label
     * kompetensi sebuah sel ditentukan oleh sel lain.
     *
     * Penyalinan massal dari master TIDAK punya cacat ini karena ia berjalan per sel.
     * Dua pintu setara, aturannya cuma di salah satu — pola yang sudah berulang di modul
     * ini, jadi yang diuji EFEKNYA, bukan keberadaan penyaringnya.
     *
     * CARA MEMBUATNYA MERAH: buang penyaringan `PA_Rater_Category_Id` di
     * `PaHcLearnCatalogService::labelDariMaster()`.
     */
    public function test_sel_kategori_lain_tidak_meminjam_label_yang_bukan_miliknya(): void
    {
        $ujian = $this->ujianSepadan();
        $this->assertNotNull($ujian, 'Katalog palsu tidak memuat ujian yang sepadan.');

        $kategori = DB::table('N_PA_Rater_Category')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Rater_Category')
            ->pluck('Id_PA_Rater_Category')
            ->map(fn ($v) => (int) $v)
            ->all();

        if (count($kategori) < 2) {
            $this->markTestSkipped('Butuh dua kategori penilai aktif; data master belum memenuhinya.');
        }

        [$dilabeli, $kosong] = $kategori;

        /* Dilabeli untuk kategori PERTAMA saja; kategori kedua sengaja dibiarkan kosong. */
        $this->service->simpan(
            $this->levelId,
            $dilabeli,
            (string) $ujian['Id_Master_Ujian'],
            $this->labelLengkap($ujian),
        );

        /* Baris ber-rater NULL DINONAKTIFKAN selama uji ini, dan itu bagian dari
           premisnya. Baris itu memang cadangan yang SAH — "paket selevel" yang dipakai
           kategori mana pun, aturan yang sama dengan `matriks()` — sehingga selama ia ada,
           impor memang boleh lolos tanpa label sel sendiri. Uji lain di berkas ini
           meninggalkannya karena berkas ini tidak memakai DatabaseTransactions, jadi
           tanpa penonaktifan ini yang terukur adalah cadangan itu, bukan peminjaman
           antar kategori yang sedang dijaga. Dihidupkan lagi di `finally`. */
        $baku = DB::table('N_PA_Question_Binding_Master')
            ->where('ID_Level', $this->levelId)
            ->where('Id_Master_Ujian', (string) $ujian['Id_Master_Ujian'])
            ->whereNull('PA_Rater_Category_Id')
            ->where('Flag_Aktif', 'Y')
            ->pluck('Id_PA_Question_Binding_Master')
            ->all();

        if ($baku !== []) {
            DB::table('N_PA_Question_Binding_Master')
                ->whereIn('Id_PA_Question_Binding_Master', $baku)
                ->update(['Flag_Aktif' => 'T']);
        }

        $cycleId = $this->siklusDraft();

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/belum dipetakan ke kompetensi/i');

            app(PaHcLearnCatalogService::class)->impor(
                $cycleId,
                $this->levelId,
                (string) $ujian['Id_Master_Ujian'],
                $kosong,
                null,
                false,
            );
        } finally {
            if ($baku !== []) {
                DB::table('N_PA_Question_Binding_Master')
                    ->whereIn('Id_PA_Question_Binding_Master', $baku)
                    ->update(['Flag_Aktif' => 'Y']);
            }

            $this->buangSelMaster($dilabeli, (string) $ujian['Id_Master_Ujian']);
            $this->buangSiklus($cycleId);
        }
    }

    /**
     * SEL TERISI BISA DIGANTI — dan penggantiannya harus DIMINTA.
     *
     * Layar menjanjikan "klik untuk mengganti paket soal" pada sel terisi, tetapi impor
     * menolaknya dan menyuruh "nonaktifkan yang lama lebih dulu" — tindakan yang tidak
     * ada di layar mana pun dan tidak punya endpoint. Jalannya buntu: sel yang sudah
     * terisi tidak bisa ditukar sama sekali, padahal
     * `UX_PA_CycleQPkg_Cycle_Level_Rater` sengaja tersaring pada `Flag_Aktif = 'Y'`
     * justru supaya satu sel bisa berganti paket dan riwayat keduanya tetap terbaca.
     *
     * Dua sifat dijaga sekaligus, karena keduanya bisa rusak sendiri-sendiri: tanpa
     * `ganti` impor tetap DITOLAK (klik yang tidak sengaja tidak menukar soal satu sel),
     * dan dengan `ganti` paket lama beserta butirnya BENAR-BENAR nonaktif — bukan
     * tertinggal aktif berdampingan dengan penggantinya.
     *
     * CARA MEMBUATNYA MERAH: kembalikan gerbang "sudah ditempati" menjadi penolakan tanpa
     * syarat, atau hapus penonaktifan paket lama di dalam transaksinya.
     */
    public function test_sel_terisi_hanya_berganti_bila_penggantian_diminta(): void
    {
        $semua = app(PaHcLearnCatalogService::class)->daftarPaket()['paket'];
        $sepadan = $this->ujianSepadan();
        $this->assertNotNull($sepadan, 'Katalog palsu tidak memuat ujian yang sepadan.');

        $this->service->simpan(
            $this->levelId,
            null,
            (string) $sepadan['Id_Master_Ujian'],
            $this->labelLengkap($sepadan),
        );

        /* Ujian KEDUA yang juga sepadan, supaya penggantiannya benar-benar menukar paket
           dan bukan ditolak "sudah memakai ujian ini". Cacah bagiannya disamakan lebih
           dulu lewat pelabelan, sehingga gerbang bijeksi tidak ikut bicara. */
        $lain = collect($semua)->first(
            fn ($u) => $u['Id_Master_Ujian'] !== $sepadan['Id_Master_Ujian']
                && count($u['Bagian']) === count($this->wajib),
        );

        if ($lain === null) {
            $this->markTestSkipped('Katalog palsu hanya memuat satu ujian sepadan.');
        }

        $cycleId = $this->siklusDraft();

        try {
            $katalog = app(PaHcLearnCatalogService::class);

            $katalog->impor($cycleId, $this->levelId, (string) $sepadan['Id_Master_Ujian'], null, null, false);

            $lamaId = (int) DB::table('N_PA_Cycle_Question_Package')
                ->where('PA_Cycle_Id', $cycleId)
                ->where('Flag_Aktif', 'Y')
                ->value('Id_PA_Cycle_Question_Package');

            $this->assertGreaterThan(0, $lamaId, 'Paket pertama tidak terbentuk.');

            /* Label untuk ujian kedua dipasang SESUDAH impor pertama: menyimpannya lebih
               dulu akan menimpa ikatan sel yang sama (satu sel satu ujian di master). */
            $this->service->simpan(
                $this->levelId,
                null,
                (string) $lain['Id_Master_Ujian'],
                $this->labelLengkap($lain),
            );

            $ditolak = null;

            try {
                $katalog->impor($cycleId, $this->levelId, (string) $lain['Id_Master_Ujian'], null, null, false);
            } catch (RuntimeException $e) {
                $ditolak = $e->getMessage();
            }

            $this->assertNotNull($ditolak, 'Impor ke sel terisi lolos tanpa diminta mengganti.');

            $katalog->impor($cycleId, $this->levelId, (string) $lain['Id_Master_Ujian'], null, null, false, true);

            $aktif = DB::table('N_PA_Cycle_Question_Package')
                ->where('PA_Cycle_Id', $cycleId)
                ->where('Flag_Aktif', 'Y')
                ->get(['Id_PA_Cycle_Question_Package', 'Id_Master_Ujian']);

            $this->assertCount(1, $aktif, 'Sel punya lebih dari satu paket aktif sesudah penggantian.');
            $this->assertSame((string) $lain['Id_Master_Ujian'], (string) $aktif[0]->Id_Master_Ujian);

            $this->assertSame(
                0,
                DB::table('N_PA_Cycle_Question_Package_Item')
                    ->where('PA_Cycle_Question_Package_Id', $lamaId)
                    ->where('Flag_Aktif', 'Y')
                    ->count(),
                'Butir paket lama masih aktif; hasil penilaian bisa terpetakan ke soal yang sudah ditinggalkan.'
            );
        } finally {
            $this->buangSiklus($cycleId);
        }
    }

    /**
     * LANGKAH 2 BISA MELABELI SENDIRI, dan pintunya dijaga izin siklus — bukan izin master.
     *
     * Pelabelan hidup di master, dan sebelum ini satu-satunya alamatnya dijaga
     * `PaMasterPaketSoalPage`. Admin siklus memegang `PerformanceAppraisalPage`; memanggil
     * alamat master dari layar siklus akan dijawab 403 untuk orang yang justru sedang
     * menjalankan pekerjaannya, sehingga ia harus berpindah halaman, mencari sel yang
     * sama, melabeli, lalu kembali dan mengulang pilihannya.
     *
     * Yang dijaga di sini EFEKNYA: sesudah melabeli lewat alamat siklus, impor untuk sel
     * itu berhasil. Menegaskan "rutenya ada" saja tidak membuktikan pelabelannya mendarat
     * di sel yang benar — dan sel yang salah persis cacat yang baru saja ditutup.
     *
     * CARA MEMBUATNYA MERAH: pindahkan rute `paket-soal/label` ke grup master, atau buang
     * `rater_category_id` dari kirimannya.
     */
    public function test_langkah_dua_bisa_melabeli_sel_tanpa_pindah_ke_master(): void
    {
        $ujian = $this->ujianSepadan();
        $this->assertNotNull($ujian, 'Katalog palsu tidak memuat ujian yang sepadan.');

        $kategori = DB::table('N_PA_Rater_Category')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Rater_Category')
            ->value('Id_PA_Rater_Category');

        $this->assertNotNull($kategori, 'Tidak ada kategori penilai aktif.');

        $cycleId = $this->siklusDraft();

        try {
            $bagian = $this->getJson(sprintf(
                '/api/v1/performance-appraisal/cycles/%d/paket-soal/bagian?id_level=%d&id_master_ujian=%s&rater_category_id=%d',
                $cycleId,
                $this->levelId,
                $ujian['Id_Master_Ujian'],
                $kategori,
            ));

            $bagian->assertOk();
            $this->assertNotEmpty($bagian->json('result.bagian'), 'Bagian ujian tidak terbawa.');

            $simpan = $this->postJson(
                "/api/v1/performance-appraisal/cycles/{$cycleId}/paket-soal/label",
                [
                    'id_level' => $this->levelId,
                    'rater_category_id' => (int) $kategori,
                    'id_master_ujian' => (string) $ujian['Id_Master_Ujian'],
                    'label' => $this->labelLengkap($ujian),
                ],
            );

            $simpan->assertOk();

            /* Buktinya impor sel itu lolos. Sel lain sengaja TIDAK disentuh, jadi kalau
               pelabelannya mendarat di sel yang salah, gerbang "belum dipetakan" akan
               menyala di sini. */
            app(PaHcLearnCatalogService::class)->impor(
                $cycleId,
                $this->levelId,
                (string) $ujian['Id_Master_Ujian'],
                (int) $kategori,
                null,
                false,
            );

            $this->assertSame(
                1,
                DB::table('N_PA_Cycle_Question_Package')
                    ->where('PA_Cycle_Id', $cycleId)
                    ->where('PA_Rater_Category_Id', (int) $kategori)
                    ->where('Flag_Aktif', 'Y')
                    ->count(),
                'Paket sel tidak terbentuk sesudah pelabelan dari Langkah 2.'
            );
        } finally {
            $this->buangSelMaster((int) $kategori, (string) $ujian['Id_Master_Ujian']);
            $this->buangSiklus($cycleId);
        }
    }

    /**
     * SEL KOSONG DIISI ACUAN DARI SEL SEBARIS — dan acuannya BERTANDA.
     *
     * Satu baris memakai ujian yang sama di beberapa kategori penilai adalah kejadian
     * biasa, dan kompetensi wajib ditentukan per LEVEL saja: `kompetensiWajibLevel()`
     * tidak mengenal kategori penilai, jadi pemetaan yang benar untuk satu sel pasti
     * benar juga untuk tetangganya sebaris. Mengetik ulang dua puluh satu baris untuk
     * hasil yang sudah pasti sama adalah pekerjaan yang tidak perlu ada.
     *
     * DUA sifat dijaga, karena yang kedua yang paling mudah hilang: isiannya terisi, DAN
     * `label_acuan_dari` menyebut asalnya. Isian yang muncul tanpa sebab akan dikira
     * pekerjaan yang sudah pernah dilakukan lalu disimpan tanpa dibaca — dan yang
     * tersimpan tanpa dibaca adalah pemetaan yang menentukan nilai orang mendarat di
     * kompetensi mana.
     *
     * Ini SALINAN, bukan pinjaman: yang disimpan tetap menjadi ikatan sel itu sendiri.
     * Peminjaman diam-diam justru cacat yang ditutup uji tetangga di berkas ini.
     *
     * CARA MEMBUATNYA MERAH: pulangkan `labelSelUntuk()` apa adanya di
     * `labelAcuanSel()`, atau buang `label_acuan_dari` dari balasan.
     */
    public function test_sel_kosong_mendapat_acuan_label_dari_sel_sebaris(): void
    {
        $ujian = $this->ujianSepadan();
        $this->assertNotNull($ujian, 'Katalog palsu tidak memuat ujian yang sepadan.');

        $kategori = DB::table('N_PA_Rater_Category')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Rater_Category')
            ->pluck('Id_PA_Rater_Category')
            ->map(fn ($v) => (int) $v)
            ->all();

        if (count($kategori) < 2) {
            $this->markTestSkipped('Butuh dua kategori penilai aktif.');
        }

        [$sumber, $kosong] = $kategori;

        $this->service->simpan(
            $this->levelId,
            $sumber,
            (string) $ujian['Id_Master_Ujian'],
            $this->labelLengkap($ujian),
        );

        /* Sel ber-rater NULL dinonaktifkan: ia cadangan yang sah dan akan terpakai lebih
           dulu, sehingga yang terukur bukan penyalinan antar kategori yang dijaga di sini. */
        $baku = DB::table('N_PA_Question_Binding_Master')
            ->where('ID_Level', $this->levelId)
            ->where('Id_Master_Ujian', (string) $ujian['Id_Master_Ujian'])
            ->whereNull('PA_Rater_Category_Id')
            ->where('Flag_Aktif', 'Y')
            ->pluck('Id_PA_Question_Binding_Master')
            ->all();

        if ($baku !== []) {
            DB::table('N_PA_Question_Binding_Master')
                ->whereIn('Id_PA_Question_Binding_Master', $baku)
                ->update(['Flag_Aktif' => 'T']);
        }

        $cycleId = $this->siklusDraft();

        try {
            $r = $this->getJson(sprintf(
                '/api/v1/performance-appraisal/cycles/%d/paket-soal/bagian?id_level=%d&id_master_ujian=%s&rater_category_id=%d',
                $cycleId,
                $this->levelId,
                $ujian['Id_Master_Ujian'],
                $kosong,
            ));

            $r->assertOk();

            $this->assertNotEmpty(
                $r->json('result.label_awal'),
                'Sel kosong tidak mendapat acuan label dari sel sebaris.'
            );

            $this->assertNotNull(
                $r->json('result.label_acuan_dari'),
                'Acuannya terisi tanpa menyebut asalnya; admin akan mengira itu pekerjaannya sendiri.'
            );

            /* Sel SUMBER melaporkan label miliknya sendiri, dan karena itu TANPA penanda
               asal. Tanpa penegasan ini, penanda yang selalu terisi juga lolos. */
            $milik = $this->getJson(sprintf(
                '/api/v1/performance-appraisal/cycles/%d/paket-soal/bagian?id_level=%d&id_master_ujian=%s&rater_category_id=%d',
                $cycleId,
                $this->levelId,
                $ujian['Id_Master_Ujian'],
                $sumber,
            ));

            $milik->assertOk();
            $this->assertNotEmpty($milik->json('result.label_awal'));
            $this->assertNull(
                $milik->json('result.label_acuan_dari'),
                'Sel yang punya labelnya sendiri dilaporkan sebagai salinan.'
            );
        } finally {
            if ($baku !== []) {
                DB::table('N_PA_Question_Binding_Master')
                    ->whereIn('Id_PA_Question_Binding_Master', $baku)
                    ->update(['Flag_Aktif' => 'Y']);
            }

            $this->buangSelMaster($sumber, (string) $ujian['Id_Master_Ujian']);
            $this->buangSiklus($cycleId);
        }
    }

    /**
     * Membuang sel MASTER berkategori yang dibuat uji — beserta detail labelnya.
     *
     * Berkas ini sengaja tidak memakai DatabaseTransactions, jadi apa pun yang ditulisnya
     * tertinggal di basis data pengembangan. Sel ber-rater NULL memang dibiarkan: ia dipakai
     * ulang antar uji di berkas ini dan `simpan()` menyegarkannya. Sel BERKATEGORI tidak
     * boleh dibiarkan — ia menambah baris kedua pada baris level yang sama, mengikat ujian
     * karangan (900001/900003) yang tidak ada di katalog HC Learn sungguhan, lalu tampil di
     * matriks admin sebagai sel terisi yang tak bisa dipakai siapa pun. Terbukti merugikan:
     * sel tinggalan seperti itu ditandai BERMASALAH oleh uji lain yang menyalin ke siklus,
     * dan membuat uji matriks di berkas ini merah di suite penuh.
     */
    private function buangSelMaster(?int $raterCategoryId, string $idMasterUjian): void
    {
        $id = DB::table('N_PA_Question_Binding_Master')
            ->where('ID_Level', $this->levelId)
            ->where('Id_Master_Ujian', $idMasterUjian)
            ->when(
                $raterCategoryId === null,
                fn ($q) => $q->whereNull('PA_Rater_Category_Id'),
                fn ($q) => $q->where('PA_Rater_Category_Id', $raterCategoryId),
            )
            ->pluck('Id_PA_Question_Binding_Master')
            ->all();

        if ($id === []) {
            return;
        }

        DB::table('N_PA_Question_Binding_Master_Detail')
            ->whereIn('PA_Question_Binding_Master_Id', $id)
            ->delete();

        DB::table('N_PA_Question_Binding_Master')
            ->whereIn('Id_PA_Question_Binding_Master', $id)
            ->delete();
    }

    /** Membuang siklus uji beserta paket dan butirnya. */
    private function buangSiklus(int $cycleId): void
    {
        $paket = DB::table('N_PA_Cycle_Question_Package')
            ->where('PA_Cycle_Id', $cycleId)
            ->pluck('Id_PA_Cycle_Question_Package')
            ->all();

        if ($paket !== []) {
            DB::table('N_PA_Cycle_Question_Package_Item')
                ->whereIn('PA_Cycle_Question_Package_Id', $paket)
                ->delete();

            DB::table('N_PA_Cycle_Question_Package')
                ->whereIn('Id_PA_Cycle_Question_Package', $paket)
                ->delete();
        }

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->delete();
    }

    /**
     * Siklus DRAFT sekali pakai. Ditulis mentah, bukan lewat penyiapan, karena yang
     * diuji di sini impor paketnya — bukan pembentukan pesertanya.
     */
    private function siklusDraft(): int
    {
        $kode = 'UJI-IKATAN-'.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => $kode,
            'Nama_Cycle' => 'Siklus Uji Ikatan Paket Soal',
            'Tahun' => 2026,
            'Status_Cycle' => 'DRAFT',
            'Periode_Dinilai_Dari' => '2026-01-01',
            'Periode_Dinilai_Sampai' => '2026-06-30',
            'Pengisian_Dari' => '2026-07-01',
            'Pengisian_Sampai' => '2026-07-31',
        ]);

        return (int) DB::table('N_PA_Cycle')->where('Kode_Cycle', $kode)->value('Id_PA_Cycle');
    }

    /**
     * @dataProvider ruteIkatan
     */
    public function test_rute_ikatan_dijaga_izin_halaman(string $metode, string $uri): void
    {
        /* Menegaskan 401 saja tidak cukup: middleware `auth` sendirian sudah menghasilkannya,
           sehingga uji semacam itu tetap hijau meski penjaga izinnya hilang. Yang ditegaskan
           di sini keberadaan middleware izinnya. */
        $cocok = null;

        foreach (Route::getRoutes()->getRoutes() as $r) {
            if (in_array($metode, $r->methods(), true) && $r->uri() === $uri) {
                $cocok = $r;
                break;
            }
        }

        $this->assertNotNull($cocok, "Rute {$metode} {$uri} tidak terdaftar.");

        $middleware = $cocok->gatherMiddleware();

        $this->assertContains('auth', $middleware);
        $this->assertTrue(
            (bool) count(array_filter($middleware, fn ($m) => str_starts_with((string) $m, 'check.jabatan:'))),
            "Rute {$metode} {$uri} tidak memakai check.jabatan.",
        );
    }

    public static function ruteIkatan(): array
    {
        $p = 'api/v1/performance-appraisal/master/ikatan-paket-soal';

        return [
            'matriks' => ['GET', $p.'/matriks'],
            'bagian' => ['GET', $p.'/bagian'],
            'label' => ['GET', $p.'/{id}/label'],
            'simpan' => ['POST', $p],
            'verifikasi' => ['POST', $p.'/verifikasi'],
            'periksa' => ['POST', $p.'/{id}/periksa'],
        ];
    }
}
