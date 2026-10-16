<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopProgressReadService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Identitas dokumen pada kartu SOP Saya.
 *
 * KEPEMILIKAN MELEKAT PADA DOKUMEN, KARTU MEWAKILI PAKET. Seluruh isi berkas
 * ini berputar pada ketegangan itu: satu paket boleh memuat dokumen milik
 * beberapa departemen, dan yang mudah rusak adalah dua ujungnya —
 *
 *   - meringkasnya jadi satu pemilik, sehingga dokumen HR di dalam paket yang
 *     didominasi HSE tak pernah terlihat dari sisi HR;
 *   - membuang paket yang tak punya dokumen sama sekali, sehingga paket berisi
 *     kuis dan tautan lenyap dari setiap kelompok tanpa pesan apa pun.
 *
 * Keduanya kegagalan yang tak menimbulkan galat, dan keduanya dijaga di sini.
 *
 * Fixture dibuat sendiri, tidak menumpang data development: `N_SOP_Paket_Item`
 * di sana kosong sejak reset, sehingga uji yang menumpang akan hijau tanpa
 * pernah menyentuh satu pun cabang.
 */
class SopSayaIdentitasDokumenTest extends TestCase
{
    use DatabaseTransactions;

    private string $kode = 'UJIIDT01';

    private function svc(): SopProgressReadService
    {
        return app(SopProgressReadService::class);
    }

    private function dokumen(string $judul, array $tambahan = []): int
    {
        return app(SopDokumenService::class)->buat(array_merge([
            'Judul' => $judul.' '.substr((string) microtime(true), -5),
            'Tipe' => 'DOKUMEN',
        ], $tambahan), 1);
    }

    private function departemen(): ?int
    {
        return DB::table('N_HRIS_Master_Departemen')->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Id_Departemen')->value('Id_Departemen');
    }

    private function departemenLain(int $bukan): ?int
    {
        return DB::table('N_HRIS_Master_Departemen')->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Id_Departemen', '<>', $bukan)->orderBy('Id_Departemen')->value('Id_Departemen');
    }

    private function paketKosong(string $nama = 'PAKET UJI IDENT'): int
    {

        return DB::table('N_SOP_Master_Paket')->insertGetId([
            'Nama_Paket' => $nama.' '.substr((string) microtime(true), -6),
            'Status' => SopStatus::PAKET_PUBLISHED,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Mode_Distribusi' => 'TERBATAS',
            'Prioritas' => SopStatus::PRIORITAS_NORMAL,
        ], 'Id_SOP_Paket');
    }

    private function item(int $idPaket, ?int $idDokumen, array $tambahan = []): int
    {
        return DB::table('N_SOP_Paket_Item')->insertGetId(array_merge([
            'Id_SOP_Paket' => $idPaket,
            'Tipe_Item' => $idDokumen ? 'DOKUMEN' : 'TAUTAN',
            'Judul_Item' => 'Item uji',
            'Id_SOP_Dokumen' => $idDokumen,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Flag_Wajib' => SopStatus::AKTIF,
        ], $tambahan), 'Id_SOP_Paket_Item');
    }

    private function tugaskan(int $idPaket, string $sumber = 'MANUAL'): void
    {
        $idJadwal = DB::table('N_SOP_Penjadwalan')->insertGetId([
            'Id_SOP_Paket' => $idPaket,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Sumber' => $sumber,
        ], 'Id_SOP_Penjadwalan');

        DB::table('N_SOP_Penjadwalan_Detail')->insert([
            'Id_SOP_Penjadwalan' => $idJadwal,
            'Kode_Karyawan' => $this->kode,
            'Status_Paket' => SopStatus::STATUS_BELUM,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Flag_Cabut_Akses' => 'T',
        ]);
    }

    /** Kartu untuk satu paket, dari hasil tugasSaya(). */
    private function kartu(int $idPaket): ?array
    {
        $hasil = $this->svc()->tugasSaya($this->kode, 'all');
        $enc = \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode($idPaket);

        foreach ($hasil['items'] as $k) {
            if ($k['Id_SOP_Paket'] === $enc) {
                return $k;
            }
        }

        return null;
    }

    /* ── Nilai jamak ────────────────────────────────────────────────────── */

    public function test_paket_lintas_departemen_membawa_kedua_pemiliknya(): void
    {
        $a = $this->departemen();
        $b = $a ? $this->departemenLain($a) : null;
        if (! $a || ! $b) {
            $this->markTestSkipped('Butuh minimal dua departemen aktif di master.');
        }

        $paket = $this->paketKosong();
        $this->item($paket, $this->dokumen('DOK A', ['Departemen_Id' => $a]));
        $this->item($paket, $this->dokumen('DOK B', ['Departemen_Id' => $b]));
        $this->tugaskan($paket);

        $kartu = $this->kartu($paket);

        $this->assertNotNull($kartu, 'Paket yang ditugaskan tidak muncul di Functional Document.');
        $ids = array_column($kartu['Departemen'], 'id');
        sort($ids);

        $this->assertSame([min($a, $b), max($a, $b)], $ids,
            'Kepemilikan diringkas jadi satu — dokumen departemen lain jadi tak terlihat.');
    }

    /**
     * Sepuluh dokumen satu departemen tetap SATU lencana.
     *
     * Tanpa de-duplikasi, kartu memuat nama yang sama berulang kali dan
     * pembacanya menyimpulkan ada sepuluh departemen.
     */
    public function test_banyak_dokumen_satu_departemen_tetap_satu_entri(): void
    {
        $dept = $this->departemen();
        if (! $dept) {
            $this->markTestSkipped('Master departemen kosong.');
        }

        $paket = $this->paketKosong();
        foreach (range(1, 3) as $n) {
            $this->item($paket, $this->dokumen("DOK {$n}", ['Departemen_Id' => $dept]));
        }
        $this->tugaskan($paket);

        $this->assertCount(1, $this->kartu($paket)['Departemen']);
    }

    /* ── Ketiadaan yang harus tetap terlihat ────────────────────────────── */

    /**
     * Paket tanpa satu pun dokumen tetap punya kartu, dengan daftar KOSONG.
     *
     * Daftar kosong itulah yang dibaca layar sebagai "Tanpa pemilik". Bila
     * paketnya justru hilang dari hasil, ia lenyap dari seluruh rail tanpa
     * pesan — dan yang lenyap justru paket yang isinya kuis, yakni tugas yang
     * paling sering luput.
     */
    public function test_paket_tanpa_dokumen_tetap_muncul_dengan_pemilik_kosong(): void
    {
        $paket = $this->paketKosong('PAKET KUIS SAJA');
        $this->item($paket, null);
        $this->tugaskan($paket);

        $kartu = $this->kartu($paket);

        $this->assertNotNull($kartu, 'Paket tanpa dokumen hilang dari Functional Document.');
        $this->assertSame([], $kartu['Departemen']);
        $this->assertSame([], $kartu['Jenis_Dokumen']);
    }

    /**
     * Dokumen yang belum bertuan dicatat sebagai entri ber-id null.
     *
     * Bukan dibuang: dokumen tanpa departemen adalah pekerjaan tata kelola yang
     * menunggu, dan membuangnya membuat paket yang separuh isinya belum
     * bertuan tampil seolah sudah rapi.
     */
    public function test_dokumen_tanpa_departemen_jadi_entri_null_bukan_dibuang(): void
    {
        $paket = $this->paketKosong();
        $this->item($paket, $this->dokumen('DOK TANPA DEPT'));
        $this->tugaskan($paket);

        $dept = $this->kartu($paket)['Departemen'];

        $this->assertCount(1, $dept);
        $this->assertNull($dept[0]['id']);
    }

    /* ── Batas yang menentukan ──────────────────────────────────────────── */

    /** Item nonaktif tidak menyumbang kepemilikan. */
    public function test_item_nonaktif_tidak_menyumbang_pemilik(): void
    {
        $dept = $this->departemen();
        if (! $dept) {
            $this->markTestSkipped('Master departemen kosong.');
        }

        $paket = $this->paketKosong();
        $this->item($paket, $this->dokumen('DOK MATI', ['Departemen_Id' => $dept]), ['Flag_Aktif' => SopStatus::INAKTIF]);
        $this->item($paket, null);
        $this->tugaskan($paket);

        $this->assertSame([], $this->kartu($paket)['Departemen']);
    }

    /**
     * Item TIDAK WAJIB tetap menyumbang kepemilikan.
     *
     * Keputusan yang disengaja, dan karena itu dipatri di sini: daftar ini
     * menjawab "paket ini menyangkut siapa", bukan "apa yang wajib saya baca".
     * Dokumen rujukan HR yang tak wajib tetap membuat paket itu menyangkut HR,
     * dan menyaringnya dengan Flag_Wajib akan menyembunyikannya dari orang HR
     * yang mencarinya.
     */
    public function test_item_tidak_wajib_tetap_menyumbang_pemilik(): void
    {
        $dept = $this->departemen();
        if (! $dept) {
            $this->markTestSkipped('Master departemen kosong.');
        }

        $paket = $this->paketKosong();
        $this->item($paket, $this->dokumen('DOK OPSIONAL', ['Departemen_Id' => $dept]), ['Flag_Wajib' => SopStatus::INAKTIF]);
        $this->tugaskan($paket);

        $this->assertSame([$dept], array_column($this->kartu($paket)['Departemen'], 'id'));
    }

    /* ── Medan lain yang ikut ───────────────────────────────────────────── */

    public function test_sumber_penugasan_ikut_di_kartu(): void
    {
        $paket = $this->paketKosong();
        $this->item($paket, null);
        $this->tugaskan($paket, 'AUTO_RULE');

        $this->assertSame('AUTO_RULE', $this->kartu($paket)['Sumber']);
    }

    /**
     * Jenis dokumen terurut menaik menurut level hirarki.
     *
     * Layar mengambil yang PERTAMA sebagai lencana utama, dengan alasan paket
     * yang memuat Kebijakan beserta instruksi kerja turunannya paling tepat
     * dikenali sebagai Kebijakan. Urutan itu ditegakkan di sini; bila ia
     * bergeser, lencana kartu ikut berubah tanpa satu pun galat.
     */
    public function test_jenis_dokumen_terurut_menurut_level_hirarki(): void
    {
        $jenis = DB::table('N_SOP_Master_Jenis_Dokumen')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Level_Hirarki')
            ->orderBy('Level_Hirarki')->limit(2)->get(['Id_SOP_Jenis_Dokumen', 'Level_Hirarki']);

        if ($jenis->count() < 2) {
            $this->markTestSkipped('Butuh minimal dua jenis dokumen berlevel.');
        }

        $paket = $this->paketKosong();
        // Sengaja disisipkan dengan urutan TERBALIK supaya pengurutan benar-benar diuji.
        $this->item($paket, $this->dokumen('DOK RENDAH', ['Jenis_Dokumen_Id' => $jenis[1]->Id_SOP_Jenis_Dokumen]));
        $this->item($paket, $this->dokumen('DOK TINGGI', ['Jenis_Dokumen_Id' => $jenis[0]->Id_SOP_Jenis_Dokumen]));
        $this->tugaskan($paket);

        $level = array_column($this->kartu($paket)['Jenis_Dokumen'], 'level');

        $this->assertSame([(int) $jenis[0]->Level_Hirarki, (int) $jenis[1]->Level_Hirarki], $level);
    }

    /* ── Baris per dokumen (tampilan "Per Dokumen") ─────────────────────── */

    /** Baris dokumen untuk satu paket. */
    private function barisDokumen(int $idPaket): array
    {
        $hasil = $this->svc()->tugasSaya($this->kode, 'all');
        $enc = \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode($idPaket);

        return array_values(array_filter($hasil['dokumen'], fn ($d) => $d['Id_SOP_Paket'] === $enc));
    }

    /**
     * Judul & nomor dibaca dari DOKUMEN, bukan dari cerminan di item.
     *
     * Cerminan di `N_SOP_Paket_Item` ditimpa setiap kali item disimpan, tetapi
     * baris lama yang belum pernah disunting ulang sejak dokumennya berganti
     * nomor akan menyebut nomor yang sudah tidak berlaku — dan tak ada apa pun
     * di layar yang menandainya. Uji ini sengaja MEMBUAT penyimpangan itu.
     */
    public function test_baris_dokumen_memakai_identitas_dokumen_bukan_cerminan(): void
    {
        $idDok = $this->dokumen('DOK SUMBER');
        $paket = $this->paketKosong();
        $this->item($paket, $idDok, ['Judul_Item' => 'CERMINAN BASI', 'Nomor_SOP_Resmi' => 'NOMOR-BASI']);
        $this->tugaskan($paket);

        $asli = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)->first(['Judul', 'Nomor_SOP_Resmi']);
        $baris = $this->barisDokumen($paket);

        $this->assertCount(1, $baris);
        $this->assertSame($asli->Judul, $baris[0]['Judul']);
        $this->assertNotSame('CERMINAN BASI', $baris[0]['Judul']);
    }

    /** Item non-dokumen tetap muncul — ia tetap tugas yang harus dikerjakan. */
    public function test_item_bukan_dokumen_tetap_jadi_baris(): void
    {
        $paket = $this->paketKosong('PAKET KUIS');
        $this->item($paket, null, ['Judul_Item' => 'Kuis Akhir']);
        $this->tugaskan($paket);

        $baris = $this->barisDokumen($paket);

        $this->assertCount(1, $baris);
        $this->assertSame('Kuis Akhir', $baris[0]['Judul']);
        $this->assertSame([], $baris[0]['Departemen']);
    }

    public function test_item_nonaktif_tidak_jadi_baris_dokumen(): void
    {
        $paket = $this->paketKosong();
        $this->item($paket, $this->dokumen('DOK HIDUP'));
        $this->item($paket, $this->dokumen('DOK MATI'), ['Flag_Aktif' => SopStatus::INAKTIF]);
        $this->tugaskan($paket);

        $this->assertCount(1, $this->barisDokumen($paket));
    }

    /**
     * Tiap baris mewarisi atribut paket induknya.
     *
     * Tanpa warisan itu, saringan tenggat/sumber/prioritas dan tab status
     * berhenti bekerja begitu orang pindah ke tampilan Dokumen — tanpa galat,
     * hanya daftar yang tak menyusut saat disaring.
     */
    public function test_baris_dokumen_mewarisi_atribut_paket_induk(): void
    {
        $paket = $this->paketKosong();
        $this->item($paket, $this->dokumen('DOK WARIS'));
        $this->tugaskan($paket, 'AUTO_RULE');

        $b = $this->barisDokumen($paket)[0];

        $this->assertSame('AUTO_RULE', $b['Sumber']);
        $this->assertSame(SopStatus::PRIORITAS_NORMAL, $b['Prioritas']);
        $this->assertArrayHasKey('Grup', $b);
        // Butiran satu dokumen: dipakai bangunGrup() menghitung persen kelompok.
        $this->assertSame(1, $b['Total_Item']);
    }

    /**
     * Satu item tetap SATU baris walau punya beberapa progres versi lama.
     *
     * Inilah penggandaan yang benar-benar bisa terjadi. Seseorang boleh pernah
     * mengonfirmasi dua versi lama pada item yang sama — UNIQUE di
     * N_SOP_Progress memperbolehkannya karena kunci ketiganya adalah versi.
     * Sebagai leftJoin, item itu akan muncul sebagai DUA baris dokumen di
     * layar; karena itu "perlu dibaca ulang" dihitung lewat EXISTS.
     *
     * (Progres pada versi yang SAMA tak bisa kembar — constraint itu yang
     * menahannya — jadi kasus itu sengaja tidak diuji di sini.)
     */
    public function test_item_dengan_banyak_progres_versi_lama_tetap_satu_baris(): void
    {
        $paket = $this->paketKosong();
        $idItem = $this->item($paket, $this->dokumen('DOK BERVERSI'));
        $this->tugaskan($paket);

        $detail = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('pj.Id_SOP_Paket', $paket)->where('d.Kode_Karyawan', $this->kode)
            ->value('d.Id_SOP_Penjadwalan_Detail');

        $aktif = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $idItem)->value('Id_Item_Versi_Aktif');

        // Dua versi LAIN yang benar-benar ada, supaya kunci asingnya sah.
        $lama = DB::table('N_SOP_Item_Versi')
            ->when($aktif, fn ($q) => $q->where('Id_SOP_Item_Versi', '<>', $aktif))
            ->orderBy('Id_SOP_Item_Versi')->limit(2)->pluck('Id_SOP_Item_Versi');

        if ($lama->count() < 2) {
            $this->markTestSkipped('Butuh minimal dua versi item di database ini.');
        }

        foreach ($lama as $idVersi) {
            DB::table('N_SOP_Progress')->insert([
                'Id_SOP_Penjadwalan_Detail' => $detail,
                'Id_SOP_Paket_Item' => $idItem,
                'Id_SOP_Item_Versi' => $idVersi,
                'Kode_Karyawan' => $this->kode,
                'Tipe_Item' => 'DOKUMEN',
                'Status' => SopStatus::ITEM_DIKONFIRMASI,
                'Persen_Selesai' => 100,
                'Created_At' => now(),
            ]);
        }

        $baris = $this->barisDokumen($paket);

        $this->assertCount(1, $baris, 'Item bercabang jadi beberapa baris karena progres versi lama.');
        // Dan keadaannya terbaca benar: sudah pernah dikonfirmasi di versi lain,
        // versi aktifnya belum.
        $this->assertSame(1, $baris[0]['Item_Perlu_Ulang']);
        $this->assertSame(SopStatus::ITEM_BELUM, $baris[0]['Status_Item']);
    }

    /** Status baca dibaca per ITEM, bukan diwarisi dari paket. */
    public function test_status_item_dibaca_per_item(): void
    {
        $paket = $this->paketKosong();
        $idA = $this->item($paket, $this->dokumen('DOK A'));
        $this->item($paket, $this->dokumen('DOK B'));
        $this->tugaskan($paket);

        $detail = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('pj.Id_SOP_Paket', $paket)->where('d.Kode_Karyawan', $this->kode)
            ->value('d.Id_SOP_Penjadwalan_Detail');

        DB::table('N_SOP_Progress')->insert([
            'Id_SOP_Penjadwalan_Detail' => $detail,
            'Id_SOP_Paket_Item' => $idA,
            'Id_SOP_Item_Versi' => DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $idA)->value('Id_Item_Versi_Aktif'),
            'Kode_Karyawan' => $this->kode,
            'Tipe_Item' => 'DOKUMEN',
            'Status' => SopStatus::ITEM_DIKONFIRMASI,
            'Persen_Selesai' => 100,
            'Created_At' => now(),
        ]);

        $status = array_column($this->barisDokumen($paket), 'Status_Item');
        sort($status);

        $this->assertSame([SopStatus::ITEM_BELUM, SopStatus::ITEM_DIKONFIRMASI], $status);
    }

    /* ── Biaya ──────────────────────────────────────────────────────────── */

    /**
     * Jumlah kueri TIDAK tumbuh mengikuti jumlah kartu.
     *
     * Bentuk yang jelas lebih mudah ditulis — satu kueri identitas per kartu di
     * dalam perulangan — tetap memberi jawaban yang benar, sehingga tak ada
     * satu pun uji lain di berkas ini yang akan menangkapnya. Yang membedakan
     * hanya jumlah perjalanan ke database pada halaman yang dibuka setiap
     * karyawan setiap hari.
     *
     * Dibandingkan RELATIF: satu paket versus lima. Ambang mutlak akan berubah
     * setiap kali ada kueri lain ditambahkan ke halaman, lalu diturunkan
     * seseorang sampai tak menjaga apa pun.
     */
    public function test_kueri_tidak_tumbuh_mengikuti_jumlah_kartu(): void
    {
        $dept = $this->departemen();
        if (! $dept) {
            $this->markTestSkipped('Master departemen kosong.');
        }

        $buat = function (int $n) use ($dept) {
            foreach (range(1, $n) as $i) {
                $paket = $this->paketKosong("PAKET N{$i}");
                $this->item($paket, $this->dokumen("DOK N{$i}", ['Departemen_Id' => $dept]));
                $this->tugaskan($paket);
            }
        };

        $hitung = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->svc()->tugasSaya($this->kode, 'all');
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $buat(1);
        $satu = $hitung();

        $buat(4);
        $lima = $hitung();

        $this->assertSame($satu, $lima,
            "Jumlah kueri tumbuh dari {$satu} (1 paket) jadi {$lima} (5 paket) — ada kueri per kartu.");
    }
}
