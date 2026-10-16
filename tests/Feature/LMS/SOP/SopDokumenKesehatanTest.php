<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopDokumenKesehatanService as Kesehatan;
use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Kesehatan dokumen — lapis dokumen dashboard monitoring.
 *
 * YANG DIJAGA DI SINI adalah satu hal: **satuannya dokumen, bukan baris
 * paket.**
 *
 * Lapis dokumen yang lama berangkat dari N_SOP_Item_Versi lalu JOIN ke
 * N_SOP_Paket_Item, sehingga satu dokumen yang dipakai tiga paket terhitung
 * tiga kali, dan dokumen yang belum masuk paket mana pun tak punya baris sama
 * sekali. Dua uji pertama di bawah menuntut kebalikannya, dan keduanya akan
 * MERAH bila seseorang kelak mengembalikan butiran lama.
 */
class SopDokumenKesehatanTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): Kesehatan
    {
        return app(Kesehatan::class);
    }

    private function dokumenBaru(string $judul, array $tambahan = []): int
    {
        return app(SopDokumenService::class)->buat(array_merge([
            'Judul' => $judul.' '.substr((string) microtime(true), -5),
            'Tipe' => 'DOKUMEN',
        ], $tambahan), 1);
    }

    /** Paket TERBIT berisi satu item yang menunjuk dokumen. */
    private function paketBerisi(int $idDokumen, string $distribusi = 'TERBATAS'): int
    {

        $idPaket = DB::table('N_SOP_Master_Paket')->insertGetId([
            'Nama_Paket' => 'PAKET UJI '.substr((string) microtime(true), -6),
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

    /** Satu penugasan aktif pada paket. */
    private function tugaskan(
        int $idPaket,
        string $status = SopStatus::STATUS_PROGRESS,
        string $sumber = 'MANUAL',
    ): void {
        $idJadwal = DB::table('N_SOP_Penjadwalan')->insertGetId([
            'Id_SOP_Paket' => $idPaket,
            'Sumber' => $sumber,
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Penjadwalan');

        DB::table('N_SOP_Penjadwalan_Detail')->insert([
            'Id_SOP_Penjadwalan' => $idJadwal,
            'Kode_Karyawan' => 'UJI001',
            /* Status_Paket sengaja BUKAN 'BELUM' secara baku: gerbang paket
               publik hanya menghitung penugasan yang sudah tersentuh, dan uji
               yang tak menyadarinya akan menuduh klasifikasinya salah. */
            'Status_Paket' => $status,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Flag_Cabut_Akses' => 'T',
        ]);
    }

    private function kelompokDari(int $idDokumen): ?string
    {
        return $this->svc()->klasifikasi()
            ->firstWhere('Id_SOP_Dokumen', $idDokumen)?->Kelompok;
    }

    /* ── Satuan: dokumen, bukan baris paket ─────────────────────────────── */

    public function test_dokumen_di_banyak_paket_dihitung_sekali(): void
    {
        /* REGRESI. Inilah sebab lapis dokumen dirombak: register lama
           menghitung baris paket, jadi dokumen yang dipakai tiga paket
           menaikkan totalnya tiga angka. */
        $id = $this->dokumenBaru('SOP DIPAKAI BANYAK PAKET');
        $this->paketBerisi($id);
        $this->paketBerisi($id);
        $this->paketBerisi($id);

        $muncul = $this->svc()->klasifikasi()->where('Id_SOP_Dokumen', $id)->count();

        $this->assertSame(1, $muncul,
            'Satu dokumen harus muncul satu baris, berapa pun jumlah paket yang memakainya.');
    }

    public function test_dokumen_tanpa_paket_tetap_terlihat(): void
    {
        /* REGRESI. Di register lama dokumen begini bukan bernilai nol —
           barisnya tak ada sama sekali, sehingga "dokumen sah yang tak pernah
           sampai ke siapa pun" mustahil dihitung. */
        $id = $this->dokumenBaru('SOP TANPA PAKET');

        $this->assertSame(Kesehatan::BELUM_BEREDAR, $this->kelompokDari($id));
    }

    /* ── Klasifikasi ────────────────────────────────────────────────────── */

    public function test_dokumen_di_paket_terbit_tanpa_penugasan_jadi_publik(): void
    {
        $id = $this->dokumenBaru('SOP PUBLIK');
        $this->paketBerisi($id);

        $this->assertSame(Kesehatan::BEREDAR, $this->kelompokDari($id));
    }

    public function test_dokumen_dengan_penugasan_admin_jadi_fungsional(): void
    {
        $id = $this->dokumenBaru('SOP FUNGSIONAL');
        $this->tugaskan($this->paketBerisi($id));

        $this->assertSame(Kesehatan::DITUGASKAN, $this->kelompokDari($id));
    }

    public function test_fungsional_menang_atas_publik(): void
    {
        /* Satu dokumen bisa dijadwalkan di paket A dan cuma dibuka di paket B.
           Yang menentukan adalah ada tidaknya KEWAJIBAN, di mana pun. */
        $id = $this->dokumenBaru('SOP FUNGSIONAL DAN PUBLIK');
        $this->paketBerisi($id);
        $this->tugaskan($this->paketBerisi($id));

        $this->assertSame(Kesehatan::DITUGASKAN, $this->kelompokDari($id));
    }

    public function test_penugasan_yang_dicabut_tidak_membuat_fungsional(): void
    {
        $id = $this->dokumenBaru('SOP DICABUT');
        $idPaket = $this->paketBerisi($id);
        $this->tugaskan($idPaket);
        /* Dua langkah, bukan UPDATE ber-JOIN: tata bahasa update-with-join
           berbeda antar driver, dan uji yang gagal karena dialek SQL akan
           terbaca seolah klasifikasinya yang salah. */
        $jadwal = DB::table('N_SOP_Penjadwalan')
            ->where('Id_SOP_Paket', $idPaket)->pluck('Id_SOP_Penjadwalan');
        DB::table('N_SOP_Penjadwalan_Detail')
            ->whereIn('Id_SOP_Penjadwalan', $jadwal)
            ->update(['Flag_Cabut_Akses' => 'Y']);

        $this->assertSame(Kesehatan::BEREDAR, $this->kelompokDari($id),
            'Akses yang sudah dicabut bukan kewajiban yang masih berjalan.');
    }

    /* ── Invarian angka ─────────────────────────────────────────────────── */

    public function test_tiga_kelompok_menjumlah_tepat_ke_total(): void
    {
        /* Syarat yang dipegang keras: angka yang tak menjumlah adalah sumber
           keraguan yang tak pernah selesai. */
        $this->dokumenBaru('SOP A');
        $this->paketBerisi($this->dokumenBaru('SOP B'));
        $this->tugaskan($this->paketBerisi($this->dokumenBaru('SOP C')));

        $k = $this->svc()->kesehatan()['Komposisi'];
        $jumlah = array_sum(array_column($k['Baris'], 'Jumlah'));

        $this->assertSame($k['Total'], $jumlah);
        $this->assertGreaterThanOrEqual(3, $k['Total']);
    }

    public function test_kelompok_saling_lepas(): void
    {
        $dok = $this->svc()->klasifikasi();

        foreach ($dok as $d) {
            $this->assertContains($d->Kelompok, Kesehatan::KELOMPOK,
                "Dokumen {$d->Id_SOP_Dokumen} berlabel kelompok di luar daftar.");
        }
    }

    /* ── Sebaran: satu kartu, banyak sumbu ──────────────────────────────── */

    private function sebaran(): array
    {
        return $this->svc()->kesehatan()['Sebaran'];
    }

    public function test_katalog_sumbu_dan_datanya_berangkat_dari_daftar_yang_sama(): void
    {
        /* Pemilih sumbu di layar digambar dari 'Sumbu', isinya dibaca dari
           'Data'. Dua daftar yang menyimpang berarti tombol yang menampilkan
           panel kosong — tanpa galat apa pun. */
        $s = $this->sebaran();

        $this->assertSame(
            array_keys(Kesehatan::SUMBU_SEBARAN),
            array_column($s['Sumbu'], 'Kunci'),
        );
        foreach (array_column($s['Sumbu'], 'Kunci') as $k) {
            $this->assertArrayHasKey($k, $s['Data'], "Sumbu {$k} tak punya datanya.");
        }
    }

    public function test_semua_sumbu_memakai_bentuk_baris_yang_sama(): void
    {
        /* Satu bentuk untuk semua sumbu adalah syarat panel tunggal itu
           sendiri. Begitu sebuah sumbu memulangkan medan yang lain, layar
           harus bercabang — dan sumbu berikutnya akan menuntut cabang
           berikutnya sampai kelimanya perlahan menyimpang. */
        $medan = ['Id', 'Nilai', 'Kode', 'Nama', 'Label', 'Tanpa',
            'Total', 'Fungsional', 'Publik', 'Belum_Beredar'];

        foreach ($this->sebaran()['Data'] as $k => $d) {
            foreach ($d['Baris'] as $b) {
                foreach ($medan as $m) {
                    $this->assertArrayHasKey($m, $b, "Sumbu {$k} kehilangan medan {$m}.");
                }
            }
        }
    }

    public function test_pecahan_kelompok_menjumlah_ke_total_tiap_baris(): void
    {
        /* Berlaku untuk SETIAP sumbu: batang bertumpuk yang segmennya tak
           menjumlah ke totalnya sendiri adalah grafik yang berbohong. */
        foreach ($this->sebaran()['Data'] as $k => $d) {
            foreach ($d['Baris'] as $b) {
                $this->assertSame(
                    (int) $b['Total'],
                    (int) $b['Fungsional'] + (int) $b['Publik'] + (int) $b['Belum_Beredar'],
                    "Baris \"{$b['Nama']}\" pada sumbu {$k} tak menjumlah ke totalnya sendiri.",
                );
            }
        }
    }

    public function test_sumbu_yang_mengaku_menjumlah_benar_benar_menjumlah(): void
    {
        /* 'Menjumlah' bukan hiasan: layar memakainya untuk memutuskan perlu
           tidaknya menyebut bahwa batangnya tak bisa dijumlahkan. Pengakuan
           yang salah lebih buruk daripada tak ada pengakuan. */
        $s = $this->sebaran();

        foreach ($s['Data'] as $k => $d) {
            if (! $d['Menjumlah']) {
                $this->assertNotEmpty($d['Catatan'],
                    "Sumbu {$k} tak menjumlah tetapi tak menjelaskan sebabnya.");

                continue;
            }

            $this->assertSame(
                $s['Total'],
                array_sum(array_column($d['Baris'], 'Total')),
                "Sumbu {$k} mengaku menjumlah ke total dokumen, tetapi tidak.",
            );
        }
    }

    public function test_sumbu_jenis_menampilkan_tingkat_yang_kosong(): void
    {
        /* Tingkat piramid yang kosong adalah temuan paling penting di sumbu
           ini. Menyembunyikannya karena tak ada datanya membuat ketimpangan
           tak pernah terlihat.

           TINGKAT KOSONGNYA DIBUAT SENDIRI, tidak dipungut dari data dev.
           Versi lama uji ini bergantung pada kebetulan bahwa master masih punya
           jenis yang belum terpakai; per 1 Sep 2026 kebetulan itu habis —
           ketujuh jenis AKTIF semuanya sudah dipakai dokumen, dan satu-satunya
           jenis tak terpakai (CNTH "CONTOH + Revisi") ber-Flag_Aktif='T'
           sehingga sebaranJenis() memang tak menggambarnya.

           Melonggarkan assertion-nya berarti membuang justru satu-satunya hal
           yang dijaga uji ini. Jadi fiksturnya yang menyediakan prasyaratnya:
           satu jenis AKTIF yang dijamin nol dokumen — dijamin karena baru saja
           lahir di dalam transaksi uji ini dan digulung balik sesudahnya. */
        $unik = substr((string) microtime(true), -5);
        $jenisKosong = app(\App\Services\LMS\SOP\SopJenisDokumenService::class)->create([
            'Kode_Jenis' => 'UJIKSG'.$unik,
            'Kode_Jenis_Dokumen' => 'UK'.$unik,
            'Nama_Jenis' => 'Jenis Uji Tanpa Dokumen '.$unik,
            /* Sengaja di dasar piramid: menyisipkannya di tengah akan menggeser
               urutan yang dijaga uji tetangga bila kelak keduanya digabung. */
            'Level_Hirarki' => 99,
            'Tipe_Item' => ['DOKUMEN'],
        ], 1);

        $d = $this->sebaran()['Data']['jenis'];
        $aktif = DB::table('N_SOP_Master_Jenis_Dokumen')
            ->where('Flag_Aktif', SopStatus::AKTIF)->count();

        $this->assertGreaterThanOrEqual($aktif, count($d['Baris']));

        /* Dicocokkan sebagai int di kedua sisi. insertGetId() memulangkan tipe
           yang bergantung driver sementara sebaranJenis() sudah men-cast ke int,
           dan firstWhere() membandingkan secara KETAT — pasangan '123'/123 akan
           memulangkan null dan terbaca seolah barisnya memang tak digambar. */
        $baris = collect($d['Baris'])->firstWhere('Id', (int) $jenisKosong);
        $this->assertNotNull($baris,
            'Jenis aktif yang belum dipakai dokumen mana pun HILANG dari piramid — '
            .'persis ketimpangan yang sumbu ini ada untuk memperlihatkannya.');
        $this->assertSame(0, (int) $baris['Total'],
            'Barisnya ada tetapi tak bernilai nol — angkanya bukan berasal dari jenis ini.');
        $this->assertContains(0, array_column($d['Baris'], 'Total'),
            'Master punya jenis yang belum dipakai dokumen mana pun — barisnya harus tetap ada.');
    }

    public function test_sumbu_jenis_terurut_dari_puncak_ke_dasar(): void
    {
        $baris = array_filter($this->sebaran()['Data']['jenis']['Baris'], fn ($b) => ! $b['Tanpa']);
        $nama = array_values(array_map(fn ($b) => $b['Nama'], $baris));

        $harusnya = DB::table('N_SOP_Master_Jenis_Dokumen')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Level_Hirarki')
            ->pluck('Nama_Jenis')->all();

        $this->assertSame($harusnya, $nama, 'Piramid harus terbaca dari puncak ke dasar.');
    }

    public function test_sumbu_apqc_menghitung_proses_yang_belum_punya_sop(): void
    {
        $d = $this->sebaran()['Data']['apqc'];
        $master = DB::table('N_SOP_Master_APQC')
            ->where('Flag_Aktif', SopStatus::AKTIF)->count();

        $this->assertGreaterThanOrEqual($master, count($d['Baris']));
        $this->assertSame(
            count(array_filter($d['Baris'], fn ($b) => $b['Total'] === 0 && ! $b['Tanpa'])),
            $d['Kosong'],
            'Angka "proses kosong" harus sama dengan baris nol yang benar-benar tergambar.',
        );
    }

    public function test_nilai_tautan_bisa_dibaca_kembali_master_dokumen(): void
    {
        /* REGRESI. Piramid lama mengirim id MENTAH ke ?jenis=, padahal Master
           Dokumen men-decode hashid — decode gagal, saringannya batal tanpa
           satu pun tanda, dan admin mendarat di daftar SELURUH dokumen di
           layar yang menjanjikan satu tingkat saja. */
        foreach (['jenis', 'apqc', 'unit'] as $k) {
            foreach ($this->sebaran()['Data'][$k]['Baris'] as $b) {
                if ($b['Tanpa'] || $b['Nilai'] === null) {
                    continue;
                }

                $this->assertSame(
                    $b['Id'],
                    Hashids::connection('custom')->decode($b['Nilai'])[0] ?? null,
                    "Nilai tautan sumbu {$k} tak bisa dibaca kembali oleh Master Dokumen.",
                );
            }
        }
    }

    public function test_sumbu_paket_mengaku_ketika_batangnya_tak_bisa_dijumlahkan(): void
    {
        /* Satu dokumen boleh dipakai beberapa paket — fan-out memang dirancang
           begitu — jadi batangnya melebihi jumlah dokumen. Yang dijaga di sini
           bukan angkanya melainkan PENGAKUANNYA: pembaca yang menjumlahkan
           sendiri lalu menemukan hasilnya melebihi total akan berhenti
           mempercayai seluruh kartu. */
        $id = $this->dokumenBaru('SOP DUA PAKET');
        $this->paketBerisi($id);
        $this->paketBerisi($id);

        $d = $this->sebaran()['Data']['paket'];

        $this->assertFalse($d['Menjumlah']);
        $this->assertStringContainsString('lebih dari satu paket', (string) $d['Catatan']);
    }

    public function test_sumbu_paket_menghitung_dokumen_bukan_item(): void
    {
        /* Satu paket bisa memuat DUA item yang menunjuk dokumen yang sama —
           keadaan yang memang terjadi. Batang yang menghitung item sedangkan
           judulnya menjanjikan dokumen adalah angka yang tak bisa dicocokkan
           dengan apa pun. */
        $id = $this->dokumenBaru('SOP DUA ITEM SATU PAKET');
        $idPaket = $this->paketBerisi($id);

        DB::table('N_SOP_Paket_Item')->insert([
            'Id_SOP_Paket' => $idPaket,
            'Tipe_Item' => 'DOKUMEN',
            'Judul_Item' => 'Item uji kedua',
            'Id_SOP_Dokumen' => $id,
            'Flag_Aktif' => SopStatus::AKTIF,
        ]);

        $baris = collect($this->sebaran()['Data']['paket']['Baris'])
            ->firstWhere('Id', $idPaket);

        $this->assertNotNull($baris, 'Paket yang baru dibuat harus punya barisnya sendiri.');
        $this->assertSame(1, $baris['Total']);
    }

    /* ── Rentang tanggal ────────────────────────────────────────────────── */

    public function test_kelompok_dokumen_tak_bergeser_karena_rentang_tanggal(): void
    {
        /* REGRESI. Kelompok sebuah dokumen adalah sifatnya SEKARANG, bukan
           sifatnya pada sebuah jendela waktu. Selama rentang ikut menggigit,
           dokumen yang ditugaskan enam bulan lalu berpindah jadi "belum
           beredar" begitu layar disetel ke 30 hari terakhir — dan komposisi,
           sebaran, antrean "sah tapi tak beredar", serta kalimat penyebut ikut
           berbohong bersamanya. Sejak tab Ringkasan berbaku 30 hari, itu bukan
           keadaan langka melainkan tampilan BAKU. */
        $id = $this->dokumenBaru('SOP UJI RENTANG');
        $this->tugaskan($this->paketBerisi($id));

        $sempit = [
            'date_start' => '2020-01-01', 'date_end' => '2020-01-02',
            'dari' => '2020-01-01', 'sampai' => '2020-01-02', 'date_mode' => 'assign',
        ];

        $polos = $this->svc()->klasifikasi()->firstWhere('Id_SOP_Dokumen', $id)?->Kelompok;
        $berentang = $this->svc()->klasifikasi($sempit)->firstWhere('Id_SOP_Dokumen', $id)?->Kelompok;

        $this->assertSame(Kesehatan::DITUGASKAN, $polos);
        $this->assertSame($polos, $berentang,
            'Rentang tanggal tak boleh memindahkan dokumen antar kelompok.');
    }

    public function test_lead_time_menghitung_terbitan_pertama_bukan_versi_ke_satu(): void
    {
        /* REGRESI. Dulu barisnya disaring dengan `Versi_Ke = 1`, padahal
           nomorVersiBerikut() memulai dari NOL — jadi `1` menunjuk versi KEDUA.
           Akibatnya seluruh versi pertama tak terhitung dan kartunya
           menampilkan 0 hari selamanya, tanpa satu pun tanda bahwa ia sedang
           menghitung himpunan kosong. */
        $terbit = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'v.Id_SOP_Dokumen')
            ->where('v.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->whereNull('d.Nonaktif_At')
            ->whereNotNull('v.Tanggal_Publish')
            ->distinct()->count('v.Id_SOP_Dokumen');

        if ($terbit === 0) {
            $this->markTestSkipped('Belum ada dokumen terbit di basis data ini.');
        }

        $lead = $this->svc()->kesehatan()['Lead_Time'];

        $this->assertGreaterThan(0, $lead['Total_Dokumen_Terbit'],
            'Ada dokumen terbit, tetapi lead time menghitung nol — saringan versinya tak menggigit.');
        $this->assertLessThanOrEqual($terbit, $lead['Total_Dokumen_Terbit'],
            'Lead time menghitung lebih banyak daripada dokumen yang benar-benar terbit — '
            .'kemungkinan satu dokumen terhitung sekali per versi.');
    }

    /* ── Gerbang paket publik ───────────────────────────────────────────── */

    public function test_penugasan_publik_yang_belum_dibuka_dilaporkan_terpisah(): void
    {
        /* Gerbang itu menyaring pendaftaran MANDIRI pada paket PUBLIK yang belum
           pernah dibuka — orang yang sekadar penasaran, bukan yang diwajibkan.
           Angka ini ada supaya gerbangnya berhenti bekerja diam-diam: tanpanya,
           penugasan tersebut lenyap dari seluruh laporan tanpa satu pun tanda.

           Sumbernya WAJIB MANDIRI. Fikstur ini dulu memakai MANUAL sebagai wakil
           "penugasan apa pun", yang benar selama gerbang mengabaikan Sumber.
           Sejak gerbang membedakan kewajiban dari minat, MANUAL justru LOLOS —
           memakainya di sini akan menguji kebalikan dari yang dimaksud. */
        $id = $this->dokumenBaru('SOP PUBLIK BELUM DIBUKA');
        $this->tugaskan($this->paketBerisi($id, 'PUBLIK'), SopStatus::STATUS_BELUM, 'MANDIRI');

        $r = $this->svc()->kesehatan();

        $this->assertGreaterThanOrEqual(1, $r['Tersaring_Gerbang_Publik']['Penugasan']);
        $this->assertSame(Kesehatan::BEREDAR, $this->kelompokDari($id),
            'Selama gerbang menyaringnya, dokumen itu belum boleh disebut wajib — '
            .'tetapi jumlahnya wajib dilaporkan terpisah.');
    }

    /* -- Endpoint -------------------------------------------------------- */

    private function adminMonitoring(): User
    {
        $uid = DB::table('HRIS_Page_Access')->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Jenis_page', 'menuSopMonitoringPage')
            ->whereNotNull('UserID_Web')->value('UserID_Web');

        $u = $uid ? User::find($uid) : null;
        if (! $u) {
            $this->markTestSkipped('Tak ada user dengan menuSopMonitoringPage di database ini.');
        }

        return $u;
    }

    public function test_endpoint_membalas_bentuk_yang_dijanjikan(): void
    {
        /* Bentuk payload dikunci di sini karena layar menggambar langsung dari
           kuncinya. Kunci yang berganti nama tanpa layar ikut berubah tak
           menimbulkan galat apa pun -- panelnya cuma menampilkan nol. */
        $r = $this->actingAs($this->adminMonitoring())
            ->getJson('/api/v1/sop/admin/monitoring/dokumen/kesehatan');

        $r->assertStatus(200);
        $r->assertJsonStructure([
            'result' => [
                'Komposisi' => ['Total', 'Baris'],
                'Sebaran' => [
                    'Total',
                    'Sumbu' => [['Kunci', 'Nama', 'Ikon', 'Satuan', 'Keterangan', 'Param', 'Baris']],
                    'Data',
                ],
                'Versi_Menggantung' => ['Total', 'Baris'],
                'Tersaring_Gerbang_Publik' => ['Penugasan', 'Paket'],
            ],
        ]);
    }

    public function test_endpoint_menolak_tanpa_hak_monitoring(): void
    {
        $punya = DB::table('HRIS_Page_Access')->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Jenis_page', 'menuSopMonitoringPage')
            ->whereNotNull('UserID_Web')->pluck('UserID_Web');

        $u = User::query()->whereNotIn('Id_Users', $punya->all())->first();
        if (! $u) {
            $this->markTestSkipped('Semua user punya menuSopMonitoringPage di database ini.');
        }

        $this->actingAs($u)
            ->getJson('/api/v1/sop/admin/monitoring/dokumen/kesehatan')
            ->assertStatus(403);
    }

    /* -- Sumber penugasan: kewajiban vs minat --------------------------- */

    public function test_pendaftaran_mandiri_tidak_membuat_dokumen_fungsional(): void
    {
        /* REGRESI, dan cacat ini sempat hidup di klasifikasi pertama.

           N_SOP_Progress.Id_SOP_Penjadwalan_Detail NOT NULL, jadi tak ada
           tempat menyimpan jejak baca tanpa penugasan. Karyawan yang membuka
           SOP PUBLIK karena itu didaftarkan sendiri oleh sistem dengan
           Sumber='MANDIRI'. Tanpa penyaring sumber, satu pembaca sukarela
           memindahkan dokumennya ke kelompok Fungsional -- "wajib" berhenti
           berarti wajib. */
        $id = $this->dokumenBaru('SOP DIBACA SUKARELA');
        $this->tugaskan($this->paketBerisi($id, 'PUBLIK'), SopStatus::STATUS_PROGRESS, 'MANDIRI');

        $this->assertSame(Kesehatan::BEREDAR, $this->kelompokDari($id),
            'Pendaftaran mandiri adalah minat, bukan kewajiban.');
    }

    public function test_penugasan_aturan_otomatis_tetap_fungsional(): void
    {
        /* AUTO_RULE lahir dari aturan penugasan, bukan dari karyawan sendiri --
           tetap sebuah kewajiban meski tak ada admin yang menekan tombol. */
        $id = $this->dokumenBaru('SOP OTOMATIS');
        $this->tugaskan($this->paketBerisi($id), SopStatus::STATUS_PROGRESS, 'AUTO_RULE');

        $this->assertSame(Kesehatan::DITUGASKAN, $this->kelompokDari($id));
    }

    public function test_sumber_kewajiban_tidak_memuat_mandiri(): void
    {
        /* Penjaga atas daftarnya sendiri: menambahkan MANDIRI ke sana akan
           membuat kedua uji di atas hijau lagi secara kebetulan. */
        $this->assertNotContains('MANDIRI', Kesehatan::SUMBER_KEWAJIBAN);
        $this->assertContains('MANUAL', Kesehatan::SUMBER_KEWAJIBAN);
        $this->assertContains('AUTO_RULE', Kesehatan::SUMBER_KEWAJIBAN);
    }
}
