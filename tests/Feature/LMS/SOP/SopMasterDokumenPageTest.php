<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Halaman Master Dokumen SOP — titik awal alur pembuatan SOP.
 *
 * ALUR YANG DITUJU: admin mendeklarasikan SOP baru di sini LEBIH DULU, lalu SOP
 * itu dipakai di banyak Paket SOP lewat pemilih Dokumen di Builder (Fase 6).
 * Sebelum ini, dokumen hanya bisa lahir sebagai efek samping pembuatan item —
 * tak ada cara mendeklarasikan SOP tanpa langsung menempelkannya ke satu paket.
 */
class SopMasterDokumenPageTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * User yang benar-benar boleh membuka halaman ini.
     *
     * Kunci menuSopDokumenPage BARU dan hidup di database, bukan di kode.
     * Selama baris menu + grant perannya belum dijalankan
     * (plans/sop/sql/27_menu_master_dokumen.sql), tak seorang pun memilikinya
     * dan seluruh endpoint membalas 403. Test di-SKIP dengan pesan yang
     * menyebut skripnya — bukan dibiarkan merah, yang akan menormalkan suite
     * merah dan membuat kegagalan sungguhan tenggelam.
     */
    private function admin(): ?User
    {
        return User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopDokumenPage'));
    }

    private function adminAtauSkip(): User
    {
        $u = $this->admin();
        if (! $u) {
            $this->markTestSkipped(
                'Belum ada user dengan akses menuSopDokumenPage. Jalankan '
                .'plans/sop/sql/27_menu_master_dokumen.sql (baris menu + grant peran) lebih dulu.'
            );
        }

        return $u;
    }

    /**
     * Bawa sebuah versi sampai ke tahap yang boleh menerbitkan.
     *
     * KENAPA PERLU. Sejak DML-2026-048, penerbitan hanya sah dari Pemberkasan
     * (APPROVED) — bukan lagi dari draft yang baru lahir. Uji yang memanggil
     * terbitkanVersi() tepat sesudah tambahVersi() karena itu ditolak gerbang
     * tahap, dan tujuh di antaranya sudah merah sejak alurnya berubah.
     *
     * MENYUSURI alurnya, bukan menyetel Tahap_Id langsung: yang hendak
     * dibuktikan uji-uji itu adalah akibat penerbitan, dan penerbitan yang
     * dicapai dengan melangkahi gerbangnya sendiri tak membuktikan apa pun.
     *
     * Tanggal masa berlaku dilengkapi karena FILING_REGISTERED menuntut
     * ketiganya (DDL-2026-049) di samping berkas dan pengesah.
     */
    /**
     * JALAN BUNTU BERSEGI DUA — tipe selain DOKUMEN tak bisa terbit sama sekali.
     *
     * Dua aturan yang masing-masing masuk akal, bertabrakan:
     *
     *   1. Blok pengesahan hanya berlaku untuk tipe DOKUMEN.
     *      pastikanFieldSesuaiTipe() MENOLAK Disahkan_Oleh pada KUIS/VIDEO/FORM.
     *   2. Sejak DML-2026-048, masuk Pemberkasan menuntut REALISASI_PENGESAH —
     *      tanpa memandang tipe.
     *
     * Akibatnya KUIS, VIDEO, dan FORM tak bisa memperoleh pengesah, jadi tak
     * bisa mencapai Pemberkasan, jadi tak bisa diterbitkan. Selamanya.
     *
     * DILEWATI, BUKAN DITAMBAL. Mengisi Disahkan_Oleh di fixture akan ditolak
     * aturan (1); menyetel Tahap_Id langsung akan melangkahi aturan (2) dan
     * membuat uji ini hijau atas alur yang tak ada di kenyataan. Keduanya
     * menyembunyikan cacatnya. Pesan lewat inilah yang memunculkannya di tiap
     * kali suite dijalankan.
     *
     * Perbaikannya keputusan produk: apakah REALISASI_PENGESAH memang harus
     * berlaku bagi tipe yang tak punya blok pengesahan?
     */
    private function lewatiBilaTipeTakBisaTerbit(string $tipe): void
    {
        $this->markTestSkipped(
            "Tipe {$tipe} tak bisa mencapai Pemberkasan: blok pengesahan hanya untuk DOKUMEN "
            .'(pastikanFieldSesuaiTipe menolak Disahkan_Oleh), sementara syarat REALISASI_PENGESAH '
            .'(DML-2026-048) berlaku untuk semua tipe. Butuh keputusan produk, bukan tambalan uji.'
        );
    }

    private function siapkanTerbit(int $idVersi): void
    {
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->update([
            'Tanggal_Berlaku' => now()->toDateString(),
            'Tanggal_Kadaluarsa' => now()->addYear()->toDateString(),
            'Tanggal_Review_Berikutnya' => now()->addMonths(6)->toDateString(),
        ]);

        $tahap = app(\App\Services\LMS\SOP\SopTahapVersiService::class);
        foreach (['REVIEW', 'APPROVAL', 'APPROVED'] as $kode) {
            $tahap->lupakanNilai($idVersi);
            $tahap->pindah($idVersi, $kode, 1);
        }
    }

    public function test_bisa_mendeklarasikan_dokumen_baru_tanpa_item(): void
    {
        $id = app(SopDokumenService::class)->buat([
            'Judul' => 'SOP DEKLARASI BARU',
            'Nomor_SOP_Resmi' => 'SOP-BARU-001',
            'Tipe' => 'DOKUMEN',
            'Deskripsi' => 'Dibuat dari halaman Master Dokumen.',
        ], 1);

        $dok = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->first();

        $this->assertNotNull($dok);
        $this->assertSame('SOP DEKLARASI BARU', $dok->Judul);
        $this->assertNull($dok->Id_Versi_Aktif,
            'Dokumen yang baru dideklarasikan belum punya versi PUBLISHED; pointer versi aktif '
            .'hanya boleh menunjuk versi yang sudah sah.');
        $this->assertSame(0, DB::table('N_SOP_Paket_Item')->where('Id_SOP_Dokumen', $id)->count(),
            'Deklarasi tak boleh diam-diam membuat item — itulah bedanya dengan alur lama.');
    }

    /**
     * Invarian "dokumen yatim" HARUS dilonggarkan, bukan dipertahankan.
     *
     * Sebelum halaman ini ada, dokumen selalu lahir bersama itemnya, sehingga
     * dokumen tanpa item pasti cacat. Sejak deklarasi jadi langkah PERTAMA
     * alur, dokumen tanpa item adalah keadaan yang SAH dan biasa — dan
     * membiarkannya memerahkan gerbang berarti gerbang itu merah setiap kali
     * admin mendeklarasikan SOP baru, lalu berhenti dipercaya.
     */
    public function test_dokumen_tanpa_item_tidak_memerahkan_gerbang_verifikasi(): void
    {
        $sebelum = $this->pelanggaranDokumenYatim();

        app(SopDokumenService::class)->buat([
            'Judul' => 'SOP BELUM DIPAKAI', 'Tipe' => 'DOKUMEN',
        ], 1);

        $this->assertSame($sebelum, $this->pelanggaranDokumenYatim(),
            'Mendeklarasikan dokumen baru tanpa item tak boleh menambah pelanggaran invarian mana pun.');
    }

    /**
     * Jumlah pelanggaran invarian yang BISA disebabkan dokumen yatim.
     *
     * Dulu test di atas menuntut `sop:dokumen-verify` exit 0 — yaitu SELURUH
     * gerbang bersih. Itu menguji lebih dari yang ia klaim: gerbang itu
     * membaca seluruh data, sehingga cacat yang sama sekali tak berhubungan
     * membuatnya merah. Persis itu yang terjadi pada 11 Agustus 2026, ketika
     * invarian "item ber-versi tanpa versi berlaku di paket TERBIT"
     * ditambahkan dan langsung menemukan satu pelanggaran nyata — test ini
     * ikut merah untuk sebab yang bukan urusannya.
     *
     * Kini yang diperiksa hanya invarian yang memang bisa dipicu deklarasi
     * dokumen baru. Gerbangnya sendiri tetap jujur melaporkan sisanya.
     */
    private function pelanggaranDokumenYatim(): int
    {
        return DB::table('N_SOP_Paket_Item')
            ->whereIn('Tipe_Item', \App\Services\LMS\SOP\Support\SopTipeItem::VERSIONED)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNull('Id_SOP_Dokumen')
            ->count()
            + DB::table('N_SOP_Paket_Item')
                ->whereNotIn('Tipe_Item', \App\Services\LMS\SOP\Support\SopTipeItem::VERSIONED)
                ->whereNotNull('Id_SOP_Dokumen')
                ->count();
    }

    public function test_halaman_master_dokumen_bisa_dibuka(): void
    {
        $admin = $this->adminAtauSkip();

        $this->actingAs($admin)->get('/sop/admin/dokumen')->assertOk();
    }

    public function test_daftar_dokumen_melaporkan_paket_pemakai(): void
    {
        $admin = $this->adminAtauSkip();

        $rows = $this->actingAs($admin)
            ->getJson('/api/v1/sop/admin/dokumen/current')
            ->assertOk()
            ->json('result.rows');

        $this->assertIsArray($rows);
        if ($rows === []) {
            return;
        }

        $this->assertArrayHasKey('Jumlah_Paket_Pemakai', $rows[0],
            'Angka ini yang membuat admin tahu dampak revisi sebelum menyentuhnya.');
        $this->assertFalse(ctype_digit((string) $rows[0]['Id_SOP_Dokumen']),
            'Id wajib ter-encode, bukan id DB mentah.');
    }

    public function test_endpoint_store_menolak_judul_kosong(): void
    {
        $admin = $this->adminAtauSkip();

        $this->actingAs($admin)
            ->postJson('/api/v1/sop/admin/dokumen/store', ['Tipe' => 'DOKUMEN'])
            ->assertStatus(422);
    }

    /**
     * Isian tata kelola yang sah untuk sebuah tipe item.
     *
     * Jenis hanya disertakan bila tipe itu memang punya jenis yang sah — KUIS
     * sengaja tak menempati tingkat mana pun, dan mengirim jenis untuknya justru
     * ditolak.
     */
    private function tataKelolaUntuk(string $tipe): array
    {
        $opsi = app(\App\Services\LMS\SOP\SopJenisDokumenService::class)->opsi($tipe);
        $dep = app(\App\Services\SYSTEM\MasterData\Departemen\DepartemenService::class)->opsi();
        $entitas = app(\App\Services\SYSTEM\MasterData\Entitas\EntitasService::class)->opsi();
        $apqc = app(\App\Services\LMS\SOP\SopApqcService::class)->opsi($tipe);

        /* Sejak DDL-2026-019 pintu identitas menuntut DEPARTEMEN (bukan divisi)
           plus entitas pemilik dan ruang lingkup. Sejak DDL-2026-027 departemen
           datang dari MASTER KODE DEPARTEMEN sebagai hashid, bukan id mentah
           sub-divisi. Sub_Divisi_Id dan Divisi_Id sengaja TIDAK dikirim:
           keduanya kolom turunan yang dihitung server. */
        $out = ['Departemen_Id' => $dep !== [] ? $dep[0]['Id'] : null];
        if ($opsi !== []) {
            $out['Jenis_Dokumen_Id'] = $opsi[0]['Id'];
        }
        if ($entitas !== []) {
            $out['Entitas_Pemilik_Id'] = $entitas[0]['Id'];
            $out['Entitas_Lingkup_Id'] = $entitas[0]['Id'];
        }
        /* Kewajiban APQC diturunkan dari master: hanya disertakan bila tipe ini
           memang punya proses yang sah, sama seperti jenis dokumen. */
        if ($apqc !== []) {
            $out['APQC_Id'] = $apqc[0]['Id'];
        }

        return $out;
    }

    public function test_endpoint_store_menolak_tanpa_departemen_dan_entitas(): void
    {
        $admin = $this->adminAtauSkip();

        // Setiap dokumen harus punya penanggung jawab. Tanpa aturan ini,
        // pertanyaan "SOP milik Produksi ada berapa" tak pernah bisa dijawab.
        $this->actingAs($admin)->postJson('/api/v1/sop/admin/dokumen/store', [
            'Judul' => 'SOP TANPA PEMILIK',
            'Tipe' => 'DOKUMEN',
        ])->assertStatus(422)->assertJsonValidationErrors(['Departemen_Id', 'Entitas_Pemilik_Id', 'APQC_Id']);
    }

    public function test_endpoint_store_menerima_jenis_dan_pemilik(): void
    {
        $admin = $this->adminAtauSkip();
        $tata = $this->tataKelolaUntuk('DOKUMEN');
        if (! isset($tata['Jenis_Dokumen_Id'], $tata['Entitas_Pemilik_Id']) || ! $tata['Departemen_Id']) {
            $this->markTestSkipped('Seed jenis/entitas atau master departemen belum tersedia.');
        }

        $res = $this->actingAs($admin)->postJson('/api/v1/sop/admin/dokumen/store', [
            'Judul' => 'SOP UJI STORE TATA KELOLA',
            'Tipe' => 'DOKUMEN',
        ] + $tata)->assertStatus(201);

        $idDok = Hashids::connection('custom')
            ->decode($res->json('result.Id_SOP_Dokumen'))[0] ?? null;

        $row = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)->first();
        $this->assertNotNull($row->Jenis_Dokumen_Id);
        $this->assertSame(
            Hashids::connection('custom')->decode($tata['Departemen_Id'])[0],
            (int) $row->Departemen_Id,
        );
        /* Sub-divisi TURUNAN: terisi hanya bila departemennya punya binding.
           Menuntutnya selalu terisi akan menjadikan 35 dari 52 kode gagal
           dipakai — padahal justru itu keadaan yang dirancang. */
        $terikat = app(\App\Services\SYSTEM\MasterData\Departemen\DepartemenService::class)
            ->subDivisiTerikat(Hashids::connection('custom')->decode($tata['Departemen_Id'])[0]);
        $this->assertSame($terikat, $row->Sub_Divisi_Id === null ? null : (int) $row->Sub_Divisi_Id);
        $this->assertNotNull($row->Entitas_Pemilik_Id, 'Entitas pemilik harus tersimpan.');
        $this->assertNotNull($row->Entitas_Lingkup_Id, 'Ruang lingkup harus tersimpan.');
        $this->assertNotNull($row->APQC_Id, 'Proses APQC harus tersimpan.');
    }

    public function test_ubah_identitas_dokumen_menyebar_ke_item_perujuk(): void
    {
        $admin = $this->adminAtauSkip();

        $item = DB::table('N_SOP_Paket_Item')
            ->whereNotNull('Id_SOP_Dokumen')->where('Flag_Aktif', SopStatus::AKTIF)->first();
        if (! $item) {
            $this->markTestSkipped('Tak ada item ber-dokumen.');
        }

        $id = Hashids::connection('custom')->encode($item->Id_SOP_Dokumen);

        /* Sejak DDL-2026-016, pintu identitas mewajibkan jenis (bila tipe itu
           punya jenis yang sah) dan unit pemilik. Itu jalur backfill terpandu:
           dokumen lama diklasifikasikan saat pertama kali disunting. */
        $this->actingAs($admin)->putJson(
            "/api/v1/sop/admin/dokumen/update/{$id}",
            [
                'Judul' => 'JUDUL DARI MASTER',
                'Tipe' => $item->Tipe_Item,
                'Nomor_SOP_Resmi' => 'NO-MASTER',
            ] + $this->tataKelolaUntuk($item->Tipe_Item),
        )->assertOk();

        $this->assertSame('JUDUL DARI MASTER',
            DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $item->Id_SOP_Paket_Item)->value('Judul_Item'),
            'Mengubah identitas di Master wajib menyebar ke item perujuk — kalau tidak, satu '
            .'dokumen terkendali punya dua nama.');
    }

    // ---------------------------------------------------------------------
    // SOP UTUH: berkas + versi + pengesahan dikelola di Master
    // ---------------------------------------------------------------------

    /**
     * Inti konsep yang disepakati: SOP disiapkan LENGKAP di master sebelum
     * dipakai paket mana pun. Sebelum DDL 28, ini mustahil —
     * N_SOP_Item_Versi.Id_SOP_Paket_Item NOT NULL, sehingga versi tak bisa ada
     * tanpa item, dan SOP tak bisa siap sebelum ditempelkan ke paket.
     */
    public function test_versi_bisa_ditambahkan_pada_dokumen_tanpa_item(): void
    {
        $svc = app(SopDokumenService::class);

        $idDok = $svc->buat(['Judul' => 'SOP UTUH DI MASTER', 'Tipe' => 'DOKUMEN'], 1);

        $konten = DB::table('N_LMS_Master_Konten')->value('Id_Master_Konten');
        if (! $konten) {
            $this->markTestSkipped('Tak ada konten Library di data dev.');
        }

        $idVersi = $svc->tambahVersi($idDok, [
            'Id_Master_Konten' => $konten,
            'Tanggal_Berlaku' => '2026-01-01',
            'Tanggal_Kadaluarsa' => '2027-01-01',
            'Disahkan_Oleh' => 'Manager HSE',
            'Nomor_Pengesahan' => 'SK-UJI-001',
        ], 1);

        $this->assertNotNull($idVersi);

        $v = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->first();
        $this->assertNull($v->Id_SOP_Paket_Item,
            'Versi milik DOKUMEN, bukan item — itulah yang membuat SOP bisa disiapkan sebelum dipakai.');
        $this->assertSame((int) $idDok, (int) $v->Id_SOP_Dokumen);
        // Versi PERTAMA bernomor 0 sejak penomoran dimulai dari nol —
        // ditampilkan sebagai "v00". Angka 1 adalah kontrak lama.
        $this->assertSame(0, (int) $v->Versi_Ke);
        $this->assertSame('Manager HSE', $v->Disahkan_Oleh,
            'Pengesahan ditetapkan di master, bukan diulang per paket.');
    }

    public function test_terbitkan_versi_menjadikannya_berlaku_pada_dokumen(): void
    {
        $svc = app(SopDokumenService::class);

        $idDok = $svc->buat(['Judul' => 'SOP TERBIT DI MASTER', 'Tipe' => 'DOKUMEN'], 1);
        $konten = DB::table('N_LMS_Master_Konten')->value('Id_Master_Konten');
        if (! $konten) {
            $this->markTestSkipped('Tak ada konten Library di data dev.');
        }

        $idVersi = $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji', 'Id_Master_Konten' => $konten], 1);
        $this->siapkanTerbit($idVersi);
        $this->assertTrue($svc->terbitkanVersi($idVersi, 1));

        $this->assertSame((int) $idVersi,
            (int) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)->value('Id_Versi_Aktif'),
            'Pointer versi berlaku dokumen dipakai Master Dokumen dan pemilih di Builder.');

        $this->assertSame(SopStatus::PAKET_PUBLISHED,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value('Status'));
    }

    public function test_dua_sop_boleh_sama_sama_punya_versi_tanpa_item(): void
    {
        $svc = app(SopDokumenService::class);
        $konten = DB::table('N_LMS_Master_Konten')->value('Id_Master_Konten');
        if (! $konten) {
            $this->markTestSkipped('Tak ada konten Library di data dev.');
        }

        $a = $svc->tambahVersi($svc->buat(['Judul' => 'SOP A', 'Tipe' => 'DOKUMEN'], 1), ['Id_Master_Konten' => $konten], 1);
        $b = $svc->tambahVersi($svc->buat(['Judul' => 'SOP B', 'Tipe' => 'DOKUMEN'], 1), ['Id_Master_Konten' => $konten], 1);

        $this->assertNotNull($a);
        $this->assertNotNull($b,
            'Dua baris (Id_SOP_Paket_Item NULL, Versi_Ke 1) harus boleh berdampingan. Index lama '
            .'UNIQUE (Id_SOP_Paket_Item, Versi_Ke) menolaknya dengan error 2601 karena SQL Server '
            .'menganggap NULL setara — itulah kenapa DDL 28 membuangnya.');
    }

    // ---------------------------------------------------------------------
    // Tahap kesiapan — sumbu utama halaman
    // ---------------------------------------------------------------------

    /**
     * Tahap adalah daur hidup SOP yang sebenarnya, bukan label hias — dan
     * halaman memakainya untuk menentukan tombol aksi tiap baris. SOP yang
     * baru dideklarasikan wajib berada di tahap BARU: ia sudah punya nama,
     * tapi karyawan belum bisa membacanya sama sekali.
     */
    /**
     * KUIS bukan berkas: isinya rujukan ujian HC Learn. Penjagaan terbit yang
     * memeriksa Id_Master_Konten untuk SEMUA tipe membuat kuis bisa
     * dideklarasikan di Master tapi tak pernah bisa diselesaikan di sana —
     * jalan buntu yang baru ketahuan di ujung.
     */
    public function test_versi_kuis_terbit_dengan_ujian_bukan_berkas(): void
    {
        $admin = $this->adminAtauSkip();
        $svc = app(SopDokumenService::class);

        $idDok = $svc->buat(['Judul' => 'KUIS UJI TERBIT', 'Tipe' => 'KUIS'], 1);
        $idVersi = $svc->tambahVersi($idDok, ['Id_Master_Ujian' => 'UJI-123'], 1);

        $this->lewatiBilaTipeTakBisaTerbit('KUIS');
        $this->siapkanTerbit($idVersi);

        $token = Hashids::connection('custom')->encode($idVersi);

        $this->actingAs($admin)
            ->postJson("/api/v1/sop/admin/dokumen/versi/{$token}/terbitkan")
            ->assertOk();

        $this->assertSame((int) $idVersi,
            (int) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)->value('Id_Versi_Aktif'),
            'Kuis dengan ujian tertaut wajib bisa diterbitkan dari Master Dokumen.');
    }

    /**
     * Nama ujian harus tersimpan DI SOP, bukan cuma disandarkan pada salinan di
     * N_SOP_Paket_Item. Salinan itu hanya ada bila kuisnya sudah dipakai item —
     * padahal alur yang kita tuju justru sebaliknya: SOP disiapkan di Master
     * lebih dulu, paket menyusul. Tanpa penyimpanan ini, kuis yang baru
     * dideklarasikan selamanya tampil sebagai "ujian tertaut" tanpa keterangan.
     */
    public function test_nama_ujian_tersimpan_di_versi_dan_tampil_di_detail(): void
    {
        $admin = $this->adminAtauSkip();
        $svc = app(SopDokumenService::class);

        $idDok = $svc->buat(['Judul' => 'KUIS UJI NAMA', 'Tipe' => 'KUIS'], 1);
        $svc->tambahVersi($idDok, [
            'Id_Master_Ujian' => 'UJI-NAMA-1',
            'Nama_Ujian' => 'PRE TEST - UJI OTOMATIS',
        ], 1);

        $riwayat = $this->actingAs($admin)
            ->getJson('/api/v1/sop/admin/dokumen/'
                .Hashids::connection('custom')->encode($idDok).'/detail')
            ->assertOk()->json('result.riwayat_versi');

        $this->assertSame('PRE TEST - UJI OTOMATIS', $riwayat[0]['Nama_Ujian'],
            'Nama ujian yang dipilih admin harus bertahan tanpa bergantung pada paket mana pun.');
        $this->assertTrue($riwayat[0]['Punya_Ujian']);
    }

    /**
     * Layar menandai versi yang isinya belum dilampirkan. Tanpa kedua kolom ini
     * di payload, penandanya membaca undefined dan menampilkan "tanpa berkas"
     * pada SETIAP versi — termasuk yang jelas punya berkas. Label yang salah
     * lebih buruk daripada tidak ada label.
     */
    public function test_riwayat_versi_membawa_isi_versinya(): void
    {
        $admin = $this->adminAtauSkip();

        $item = DB::table('N_SOP_Paket_Item')
            ->whereNotNull('Id_SOP_Dokumen')->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Id_Item_Versi_Aktif')->first();
        if (! $item) {
            $this->markTestSkipped('Tak ada item ber-dokumen dgn versi aktif.');
        }

        $id = Hashids::connection('custom')->encode($item->Id_SOP_Dokumen);

        $riwayat = $this->actingAs($admin)
            ->getJson("/api/v1/sop/admin/dokumen/{$id}/detail")
            ->assertOk()->json('result.riwayat_versi');

        $this->assertNotEmpty($riwayat);
        foreach ($riwayat as $v) {
            $this->assertArrayHasKey('Punya_Berkas', $v,
                'Tanpa penanda ini, label "tanpa berkas" menyala pada semua versi.');
            $this->assertArrayHasKey('Punya_Ujian', $v,
                'Kuis memakai rujukan ujian, bukan berkas — penandanya butuh ini.');
            $this->assertArrayHasKey('Nama_Ujian', $v);

            /* Token HC Learn tak boleh sampai ke klien: layar tak memakainya
               untuk apa pun, dan satu-satunya perannya di sana adalah bocor —
               persis yang terjadi sebelum ini, tampil mentah sebagai judul.
               Yang dibutuhkan layar adalah Nama_Ujian, dan itu sudah dikirim. */
            if (($v['Status'] ?? null) !== SopStatus::PAKET_DRAFT) {
                $this->assertArrayNotHasKey('Id_Master_Ujian', $v,
                    'Token ujian HC Learn tak boleh dikirim ke klien.');
            }

            /* Id_Master_Konten DULU ikut dibuang di baris yang sama, tetapi
               alasannya sama sekali lain — dan sejak 30 Agu 2026 tak berlaku
               lagi. Ia dikirim sebagai HASHID, bukan token mentah, dan versi
               terbit kini bisa dibuka untuk perubahan terbatas: tanpa kolom ini
               kartu "Lampiran Berkas Prosedur" tampil KOSONG pada versi yang
               jelas punya berkas. Yang menahan penggantiannya adalah
               Field_Boleh_Ubah, bukan hilangnya kolomnya. */
            $this->assertArrayHasKey('Id_Master_Konten', $v,
                'Layar butuh rujukan berkasnya untuk menampilkan lampiran.');
            $this->assertArrayHasKey('Konten_Nama', $v);
        }
    }

    public function test_versi_kuis_tanpa_ujian_ditolak_terbit(): void
    {
        $admin = $this->adminAtauSkip();
        $svc = app(SopDokumenService::class);

        $idDok = $svc->buat(['Judul' => 'KUIS TANPA UJIAN', 'Tipe' => 'KUIS'], 1);
        $idVersi = $svc->tambahVersi($idDok, [], 1);

        $this->actingAs($admin)
            ->postJson('/api/v1/sop/admin/dokumen/versi/'.Hashids::connection('custom')->encode($idVersi).'/terbitkan')
            ->assertStatus(422);
    }

    public function test_daftar_melaporkan_tahap_dan_ringkasannya(): void
    {
        $admin = $this->adminAtauSkip();

        $svc = app(SopDokumenService::class);
        $idDok = $svc->buat(['Judul' => 'SOP UJI TAHAP', 'Tipe' => 'DOKUMEN'], 1);

        $hasil = $this->actingAs($admin)
            ->getJson('/api/v1/sop/admin/dokumen/current?q='.urlencode('SOP UJI TAHAP'))
            ->assertOk()
            ->assertJsonStructure(['result' => ['rows', 'ringkasan']])
            ->json('result');

        $baris = collect($hasil['rows'])->firstWhere('Judul', 'SOP UJI TAHAP');
        $this->assertNotNull($baris);
        $this->assertSame('BARU', $baris['Tahap'],
            'SOP tanpa versi ada di tahap BARU — sudah dinamai, tapi belum bisa dibaca siapa pun.');
        $this->assertFalse($baris['Punya_Versi_Berlaku']);

        foreach (['total', 'BARU', 'DRAFT', 'SIAP', 'DIPAKAI', 'PERHATIAN'] as $k) {
            $this->assertArrayHasKey($k, $hasil['ringkasan'],
                "Ringkasan wajib memuat '{$k}' — chip di atas daftar memakainya sebagai angka DAN saringan.");
        }
    }

    /**
     * Saringan tahap dijalankan di SQL, bukan di PHP setelah paging.
     * Menyaring setelah paging membuat halaman berisi lebih sedikit baris dari
     * yang dijanjikan, dan angka total-nya berbohong.
     */
    public function test_saringan_tahap_hanya_mengembalikan_tahap_itu(): void
    {
        $admin = $this->adminAtauSkip();

        app(SopDokumenService::class)->buat(['Judul' => 'SOP SARING BARU', 'Tipe' => 'DOKUMEN'], 1);

        $hasil = $this->actingAs($admin)
            ->getJson('/api/v1/sop/admin/dokumen/current?tahap=BARU')
            ->assertOk()->json('result');

        foreach ($hasil['rows'] as $r) {
            $this->assertSame('BARU', $r['Tahap'],
                'Saringan tahap tak boleh meloloskan tahap lain.');
        }
        $this->assertSame(count($hasil['rows']) <= $hasil['per_page'], true);
    }

    /**
     * Picker pengesah harus membawa JABATAN, bukan cuma nama.
     *
     * Jalur jabatan tidak sewajar tampaknya: Karyawan.Kode_Jabatan kosong untuk
     * SELURUH karyawan aktif, sehingga join ke HRIS_Jabatan selalu menghasilkan
     * kosong; dan view level-jabatan punya dua kolom mirip — ID_Level dan
     * ID_Level_Jabatan — di mana hanya yang kedua cocok. Yang salah tidak
     * memberi galat apa pun, ia hanya mengembalikan jabatan kosong untuk semua
     * orang, yang terlihat seperti data yang memang belum diisi.
     */
    public function test_opsi_pengesah_membawa_nama_dan_jabatan(): void
    {
        $admin = $this->adminAtauSkip();

        $hasil = $this->actingAs($admin)
            ->getJson('/api/v1/sop/admin/dokumen/options/pengesah')
            ->assertOk()->json('result');

        if (empty($hasil['rows'])) {
            $this->markTestSkipped('Tak ada karyawan aktif.');
        }

        $berjabatan = collect($hasil['rows'])->filter(fn ($k) => ! empty($k['Jabatan']))->count();
        $this->assertGreaterThan(0, $berjabatan,
            'Tak satu pun karyawan membawa jabatan — pertanda kolom join yang dipakai salah, '
            .'bukan pertanda datanya memang kosong.');

        foreach ($hasil['rows'] as $k) {
            $this->assertArrayHasKey('Kode_Karyawan', $k);
            $this->assertArrayHasKey('Nama', $k);
        }
        $this->assertArrayHasKey('tercakup', $hasil,
            'Setiap pembatasan wajib dilaporkan — "tak ketemu" tak boleh tertukar dengan "terpotong".');
    }

    /**
     * Rujukan datang dari klien, jadi harus dibuktikan. Kode yang tak menunjuk
     * karyawan aktif mana pun ditolak: rujukan menggantung lebih buruk daripada
     * tak ada rujukan, sebab ia mengaku terverifikasi padahal tidak.
     */
    public function test_rujukan_pengesah_palsu_ditolak(): void
    {
        $admin = $this->adminAtauSkip();
        $idDok = app(SopDokumenService::class)->buat(['Judul' => 'SOP UJI PENGESAH', 'Tipe' => 'DOKUMEN'], 1);

        $this->actingAs($admin)
            ->postJson('/api/v1/sop/admin/dokumen/'
                .Hashids::connection('custom')->encode($idDok).'/versi', [
                    'Disahkan_Oleh' => 'Orang Karangan',
                    'Kode_Karyawan_Pengesah' => 'TIDAK-ADA-999',
                ])
            ->assertStatus(422);
    }

    /**
     * Nama dan jabatan pengesah adalah SNAPSHOT, bukan hasil join saat dibaca.
     *
     * Kalau keduanya ditarik dari master karyawan setiap kali dibaca, SOP yang
     * sudah sah akan berubah sendiri catatan pengesahannya begitu orangnya naik
     * jabatan atau resign — persis kelas kesalahan yang membuat dokumen
     * terkendali ada. Rujukan kode hanya pendamping, dan kodenya sendiri tak
     * pernah dikirim ke klien.
     */
    public function test_pengesah_disimpan_sebagai_snapshot_dengan_penanda_rujukan(): void
    {
        $admin = $this->adminAtauSkip();

        $karyawan = DB::table('Karyawan')->where('Aktif', 'Y')->first(['Kode_Karyawan', 'Nama']);
        if (! $karyawan) {
            $this->markTestSkipped('Tak ada karyawan aktif.');
        }

        $svc = app(SopDokumenService::class);
        $idDok = $svc->buat(['Judul' => 'SOP UJI SNAPSHOT', 'Tipe' => 'DOKUMEN'], 1);

        $this->actingAs($admin)
            ->postJson('/api/v1/sop/admin/dokumen/'
                .Hashids::connection('custom')->encode($idDok).'/versi', [
                    'Disahkan_Oleh' => $karyawan->Nama,
                    'Jabatan_Pengesah' => 'MANAGER SAAT ITU',
                    'Kode_Karyawan_Pengesah' => $karyawan->Kode_Karyawan,
                ])
            ->assertStatus(201);

        $riwayat = $this->actingAs($admin)
            ->getJson('/api/v1/sop/admin/dokumen/'
                .Hashids::connection('custom')->encode($idDok).'/detail')
            ->assertOk()->json('result.riwayat_versi');

        $v = $riwayat[0];
        $this->assertSame($karyawan->Nama, $v['Disahkan_Oleh']);
        $this->assertSame('MANAGER SAAT ITU', $v['Jabatan_Pengesah'],
            'Jabatan harus bertahan apa adanya — bukan ditimpa jabatan orang itu hari ini.');
        $this->assertTrue($v['Pengesah_Terverifikasi']);
        $this->assertArrayNotHasKey('Kode_Karyawan_Pengesah', $v,
            'Kode karyawan tak dipakai layar untuk apa pun, jadi tak perlu dikirim.');

        // Diketik lepas: sah, dan penandanya jujur menyebut tak terverifikasi.
        $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Konsultan Luar'], 1);
        $riwayat2 = $this->actingAs($admin)
            ->getJson('/api/v1/sop/admin/dokumen/'
                .Hashids::connection('custom')->encode($idDok).'/detail')
            ->assertOk()->json('result.riwayat_versi');

        $ketik = collect($riwayat2)->firstWhere('Disahkan_Oleh', 'Konsultan Luar');
        $this->assertNotNull($ketik, 'Pengesah di luar master karyawan harus tetap bisa disimpan.');
        $this->assertFalse($ketik['Pengesah_Terverifikasi']);
    }

    /**
     * Pengesah boleh lebih dari satu orang, karena blok tanda tangan di dokumen
     * nyata hampir selalu punya beberapa baris.
     *
     * Kolom tunggal di N_SOP_Item_Versi WAJIB tetap tercermin: register
     * kepatuhan menghitung tanpa_pengesahan dari sana, ekspor membacanya, dan
     * penyaring TANPA_PENGESAHAN memakainya. Blok yang terisi sementara kolom
     * itu kosong akan membuat SOP yang jelas sudah disahkan terhitung belum.
     */
    public function test_blok_pengesah_boleh_lebih_dari_satu_dan_tercermin(): void
    {
        $admin = $this->adminAtauSkip();

        $karyawan = DB::table('Karyawan')->where('Aktif', 'Y')->first(['Kode_Karyawan', 'Nama']);
        if (! $karyawan) {
            $this->markTestSkipped('Tak ada karyawan aktif.');
        }

        $idDok = app(SopDokumenService::class)->buat(['Judul' => 'SOP UJI BLOK TTD', 'Tipe' => 'DOKUMEN'], 1);
        $id = Hashids::connection('custom')->encode($idDok);

        $this->actingAs($admin)->postJson("/api/v1/sop/admin/dokumen/{$id}/versi", [
            'Pengesah' => [
                ['Peran' => 'Disiapkan oleh', 'Nama' => $karyawan->Nama,
                    'Jabatan' => 'STAFF SAAT ITU', 'Kode_Karyawan' => $karyawan->Kode_Karyawan],
                ['Peran' => 'Diperiksa oleh', 'Nama' => 'Konsultan Luar', 'Jabatan' => 'AUDITOR'],
                // Baris kosong: tertinggal di formulir, bukan penanda tangan.
                ['Peran' => 'Disetujui oleh', 'Nama' => '  '],
            ],
        ])->assertStatus(201);

        $v = $this->actingAs($admin)
            ->getJson("/api/v1/sop/admin/dokumen/{$id}/detail")
            ->assertOk()->json('result.riwayat_versi.0');

        $this->assertCount(2, $v['Pengesah'],
            'Baris tanpa nama harus dibuang — itu baris kosong, bukan penanda tangan.');
        $this->assertSame('Disiapkan oleh', $v['Pengesah'][0]['Peran']);
        $this->assertTrue($v['Pengesah'][0]['Terverifikasi']);
        $this->assertFalse($v['Pengesah'][1]['Terverifikasi'],
            'Penanda tangan di luar master karyawan harus tercatat apa adanya, tanpa mengaku terverifikasi.');

        // Cermin ke kolom tunggal — yang dibaca register kepatuhan dan ekspor.
        $this->assertSame($karyawan->Nama, $v['Disahkan_Oleh']);
        $this->assertSame('STAFF SAAT ITU', $v['Jabatan_Pengesah'],
            'Jabatan harus bertahan apa adanya, bukan ditimpa jabatan orang itu hari ini.');
    }

    /**
     * Versi lama dan versi buatan Builder tak punya baris di tabel blok. Tanpa
     * aturan mundur, pengesah mereka lenyap dari layar — data yang ada, tapi
     * tak lagi terlihat, adalah kehilangan yang paling sulit disadari.
     */
    public function test_versi_tanpa_baris_blok_mundur_ke_kolom_tunggal(): void
    {
        $admin = $this->adminAtauSkip();

        $svc = app(SopDokumenService::class);
        $idDok = $svc->buat(['Judul' => 'SOP UJI MUNDUR TTD', 'Tipe' => 'DOKUMEN'], 1);
        // Ditulis lewat kolom tunggal saja, persis seperti jalur Builder.
        $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Lama', 'Jabatan_Pengesah' => 'KEPALA'], 1);

        $v = $this->actingAs($admin)
            ->getJson('/api/v1/sop/admin/dokumen/'
                .Hashids::connection('custom')->encode($idDok).'/detail')
            ->assertOk()->json('result.riwayat_versi.0');

        $this->assertCount(1, $v['Pengesah']);
        $this->assertSame('Pengesah Lama', $v['Pengesah'][0]['Nama']);
        $this->assertSame('KEPALA', $v['Pengesah'][0]['Jabatan']);
        $this->assertNull($v['Pengesah'][0]['Peran'],
            'Versi lama memang tak punya peran — mengarang salah satu akan mengaku tahu yang tak diketahui.');
    }

    /**
     * Isi versi LAMA harus bisa dibuka dari Master.
     *
     * Sampai kini hanya versi yang sedang berlaku yang punya jalan. Padahal
     * justru versi lama yang ditanyakan saat ada keluhan: "yang dibaca karyawan
     * bulan lalu itu isinya apa?" Arsip yang tak bisa dibuka bukan arsip.
     *
     * Endpoint per-versi sebenarnya sudah ada, tapi di bawah gerbang
     * menuSopBuilderPage — user Master menerima 403 di sana. Rute ini menunjuk
     * LAYANAN yang sama dengan gerbang Master sendiri.
     */
    public function test_isi_versi_lama_bisa_dibuka_dari_master(): void
    {
        $admin = $this->adminAtauSkip();

        $versi = DB::table('N_SOP_Item_Versi')
            ->whereNotNull('Id_SOP_Dokumen')->whereNotNull('Id_Master_Konten')
            ->where('Status', SopStatus::VERSI_OBSOLETE)
            ->value('Id_SOP_Item_Versi');

        if (! $versi) {
            $this->markTestSkipped('Tak ada versi lama ber-konten untuk diuji.');
        }

        $this->actingAs($admin)
            ->getJson('/api/v1/sop/admin/dokumen/versi/'
                .Hashids::connection('custom')->encode($versi).'/konten')
            ->assertOk()
            ->assertJsonPath('result.type', fn ($t) => $t !== null);
    }

    /**
     * Rute di bawah gerbang Master hanya boleh menjangkau versi milik DOKUMEN.
     *
     * Tanpa syarat itu, ia menjadi jalan membaca isi versi item mana pun di
     * sistem — termasuk yang gerbangnya sengaja lain.
     */
    public function test_konten_versi_menolak_versi_tanpa_dokumen(): void
    {
        $admin = $this->adminAtauSkip();

        $yatim = DB::table('N_SOP_Item_Versi')->whereNull('Id_SOP_Dokumen')
            ->value('Id_SOP_Item_Versi');

        if (! $yatim) {
            $this->markTestSkipped('Tak ada versi tanpa dokumen di basis data ini.');
        }

        $this->actingAs($admin)
            ->getJson('/api/v1/sop/admin/dokumen/versi/'
                .Hashids::connection('custom')->encode($yatim).'/konten')
            ->assertStatus(404);
    }

    /**
     * Penomoran otomatis dimulai dari NOL, dan nomor manual dipakai apa adanya.
     *
     * (MAX ?? -1) + 1, bukan (MAX ?? 0) + 1: yang kedua membuat dokumen baru
     * mulai dari 1 dan nomor 0 tak pernah terpakai sama sekali — padahal
     * "v00" justru bentuk yang diminta.
     *
     * Nomornya tetap INT. Menyimpannya sebagai teks agar bisa "00" merusak
     * urutan riwayat: secara leksikal "10" lebih kecil daripada "9".
     */
    public function test_penomoran_versi_otomatis_mulai_dari_nol(): void
    {
        $svc = app(SopDokumenService::class);
        $idDok = $svc->buat(['Judul' => 'SOP UJI NOMOR NOL', 'Tipe' => 'DOKUMEN'], 1);

        $v0 = $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji'], 1);
        $this->assertSame(0, (int) DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $v0)->value('Versi_Ke'),
            'Versi pertama sebuah SOP harus bernomor 0, bukan 1.');

        $v1 = $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji'], 1);
        $this->assertSame(1, (int) DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $v1)->value('Versi_Ke'));

        // MANUAL boleh melompat — penomoran resmi dokumen tak selalu berurutan.
        $v9 = $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji', 'Versi_Ke' => 9], 1);
        $this->assertSame(9, (int) DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $v9)->value('Versi_Ke'));

        // Otomatis berikutnya melanjutkan dari yang TERTINGGI, bukan dari hitungan.
        $v10 = $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji'], 1);
        $this->assertSame(10, (int) DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $v10)->value('Versi_Ke'));

        // KEMBAR ditolak — unique index terfilter (Id_SOP_Dokumen, Versi_Ke).
        $this->assertNull($svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji', 'Versi_Ke' => 9], 1),
            'Nomor yang sudah terpakai harus ditolak sebelum menyentuh basis data.');
    }

    /**
     * Bentrok nomor dijawab 422 dengan menyebut angkanya, bukan galat 2601.
     *
     * Dibiarkan sampai basis data, yang muncul cuma "Violation of UNIQUE KEY
     * constraint" — kalimat yang tak memberi tahu admin apa yang harus diubah.
     */
    public function test_nomor_versi_kembar_ditolak_dengan_pesan_yang_menyebut_angkanya(): void
    {
        $admin = $this->adminAtauSkip();
        $svc = app(SopDokumenService::class);

        $idDok = $svc->buat(['Judul' => 'SOP UJI NOMOR KEMBAR', 'Tipe' => 'DOKUMEN'], 1);
        $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji', 'Versi_Ke' => 3], 1);

        $this->actingAs($admin)
            ->postJson('/api/v1/sop/admin/dokumen/'
                .Hashids::connection('custom')->encode($idDok).'/versi', ['Versi_Ke' => 3])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains((string) $m, '03'));
    }

    /**
     * Berkasnya ADA tapi jenisnya lain tetap ditolak terbit.
     *
     * Penjagaan lama hanya bertanya "punya berkas atau tidak". SOP bertipe
     * VIDEO yang dilampiri PDF lolos pemeriksaan itu, lalu karyawan membuka
     * pemutar video yang isinya bukan video — kegagalan yang baru ketahuan di
     * sisi mereka, jauh setelah admin merasa pekerjaannya selesai.
     */
    public function test_versi_dengan_berkas_salah_jenis_ditolak_terbit(): void
    {
        $admin = $this->adminAtauSkip();
        $svc = app(SopDokumenService::class);

        $video = DB::table('N_LMS_Master_Konten')->where('Tipe_Konten', 'video')
            ->value('Id_Master_Konten');
        if (! $video) {
            $this->markTestSkipped('Tak ada konten video di Library dev.');
        }

        // SOP bertipe DOKUMEN, tapi berkasnya video.
        $idDok = $svc->buat(['Judul' => 'SOP UJI JENIS BERKAS', 'Tipe' => 'DOKUMEN'], 1);
        $versi = $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji', 'Id_Master_Konten' => $video], 1);
        /* Dibawa sampai Pemberkasan lebih dulu. Tanpa ini penolakannya datang
           dari gerbang TAHAP, dan uji ini berhenti membuktikan bahwa gerbang
           ISI-lah yang menolak berkas berjenis salah — hijau karena sebab yang
           keliru sama menyesatkannya dengan merah. */
        $this->siapkanTerbit($versi);

        $this->actingAs($admin)
            ->postJson('/api/v1/sop/admin/dokumen/versi/'
                .Hashids::connection('custom')->encode($versi).'/terbitkan')
            ->assertStatus(422);

        $this->assertNull(
            DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)->value('Id_Versi_Aktif'),
            'Penolakan tak boleh menyisakan pointer versi aktif yang terlanjur bergeser.');

        /* SOP bertipe VIDEO dengan berkas video yang sama: sah.
           Tanpa Disahkan_Oleh — profil tipe tidak memberlakukan pengesahan untuk
           VIDEO, dan penjaga terbit pun tidak lagi menuntutnya. */
        $this->lewatiBilaTipeTakBisaTerbit('VIDEO');

        $idVideo = $svc->buat(['Judul' => 'SOP UJI VIDEO SAH', 'Tipe' => 'VIDEO'], 1);
        $versiVideo = $svc->tambahVersi($idVideo, ['Id_Master_Konten' => $video], 1);
        $this->siapkanTerbit($versiVideo);

        $this->actingAs($admin)
            ->postJson('/api/v1/sop/admin/dokumen/versi/'
                .Hashids::connection('custom')->encode($versiVideo).'/terbitkan')
            ->assertOk();
    }

    /**
     * Penjagaan isi berlaku di KEDUA pintu penerbitan, bukan cuma di controller.
     *
     * Penerbitan bisa terjadi lewat dua jalan: Master SOP lewat controllernya,
     * dan publish paket di Builder yang memanggil SopDokumenService secara
     * langsung — melewati controller sepenuhnya. Aturan yang tinggal di
     * controller hanya menjaga satu pintu, dan pintu yang tak terjaga selalu
     * yang lebih sering dilewati.
     *
     * Dipanggil di sini persis seperti jalur Builder memanggilnya.
     */
    public function test_penjagaan_isi_berlaku_saat_layanan_dipanggil_langsung(): void
    {
        $svc = app(SopDokumenService::class);

        $idDok = $svc->buat(['Judul' => 'SOP UJI PINTU KEDUA', 'Tipe' => 'DOKUMEN'], 1);
        $versi = $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji'], 1);   // tanpa berkas sama sekali

        try {
            $this->siapkanTerbit($versi);
            $svc->terbitkanVersi($versi, 1);
            $this->fail('Versi tanpa berkas terbit lewat jalur layanan — pintu itu tak terjaga.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('belum punya berkas', $e->getMessage());
        }

        $this->assertNull(
            DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)->value('Id_Versi_Aktif'),
            'Penolakan tak boleh menyisakan pointer versi aktif yang terlanjur bergeser.');

        $this->assertSame(SopStatus::PAKET_DRAFT,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $versi)->value('Status'),
            'Versi yang ditolak harus tetap DRAFT, bukan setengah terbit.');
    }

    /**
     * Berkas melaporkan dokumen mana saja yang sudah memakainya.
     *
     * Satu berkas di Library melahirkan dokumen terkendali baru setiap kali
     * seseorang melampirkannya tanpa tahu sudah ada yang memakainya. Di data
     * dev, satu berkas menopang LIMA BELAS dokumen di delapan paket — itulah
     * sumber judul dan nomor SOP kembar di arsip.
     *
     * Nama paketnya ikut, dan itu bukan hiasan: "sudah dipakai" tanpa menyebut
     * di mana tak menolong siapa pun memutuskan mau memakai ulang atau tidak.
     */
    public function test_berkas_melaporkan_dokumen_yang_sudah_memakainya(): void
    {
        $admin = $this->adminAtauSkip();

        $konten = DB::table('N_SOP_Item_Versi')
            ->whereNotNull('Id_Master_Konten')->whereNotNull('Id_SOP_Dokumen')
            ->select('Id_Master_Konten', DB::raw('COUNT(DISTINCT Id_SOP_Dokumen) as n'))
            ->groupBy('Id_Master_Konten')->having(DB::raw('COUNT(DISTINCT Id_SOP_Dokumen)'), '>', 1)
            ->orderByDesc('n')->first();

        if (! $konten) {
            $this->markTestSkipped('Tak ada berkas yang dipakai lebih dari satu dokumen.');
        }

        $hasil = $this->actingAs($admin)
            ->getJson('/api/v1/sop/admin/dokumen/konten/'
                .Hashids::connection('custom')->encode($konten->Id_Master_Konten).'/pemakai')
            ->assertOk()->json('result');

        $this->assertSame((int) $konten->n, (int) $hasil['total'],
            'Jumlah dokumen pemakai harus lengkap — melaporkan sebagian membuat admin '
            .'mengira duplikasinya lebih kecil daripada sebenarnya.');

        $baris = $hasil['rows'][0];
        foreach (['Id_SOP_Dokumen', 'Judul', 'Paket'] as $kolom) {
            $this->assertArrayHasKey($kolom, $baris);
        }
        $this->assertIsArray($baris['Paket']);
        $this->assertFalse(ctype_digit((string) $baris['Id_SOP_Dokumen']),
            'Id wajib ter-encode — tombol "pakai ini" mengirimkannya kembali apa adanya.');
    }

    /**
     * Versi lama bisa DIKEMBALIKAN — lewat jalurnya sendiri, bukan terbitkan.
     *
     * Kebutuhannya nyata: SOP baru terbit lalu ketahuan bermasalah, dan admin
     * perlu memberlakukan lagi versi sebelumnya. Sampai sebelum ini "fiturnya"
     * ada secara tak sengaja — tombol terbit muncul pada versi OBSOLETE, dan
     * menekannya memang memundurkan pointer, tapi lewat jalur publish yang
     * mencatat jejaknya sebagai "versi baru diterbitkan" dan melewati
     * rekonsiliasi. Jejak yang menyatakan kebalikan dari yang terjadi lebih
     * buruk daripada tak ada jejak.
     */
    public function test_versi_lama_bisa_dikembalikan_lewat_jalurnya_sendiri(): void
    {
        $admin = $this->adminAtauSkip();
        $svc = app(SopDokumenService::class);

        $konten = DB::table('N_LMS_Master_Konten')->where('Tipe_Konten', 'document')
            ->value('Id_Master_Konten');
        if (! $konten) {
            $this->markTestSkipped('Tak ada konten dokumen di Library dev.');
        }

        $idDok = $svc->buat(['Judul' => 'SOP UJI ROLLBACK', 'Tipe' => 'DOKUMEN'], 1);

        $lama = $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji', 'Id_Master_Konten' => $konten], 1);
        $this->siapkanTerbit($lama);
        $svc->terbitkanVersi($lama, 1);

        $baru = $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji', 'Id_Master_Konten' => $konten], 1);
        $this->siapkanTerbit($baru);
        $svc->terbitkanVersi($baru, 1);

        // Kembalikan ke versi lama.
        $this->actingAs($admin)
            ->postJson('/api/v1/sop/admin/dokumen/versi/'
                .Hashids::connection('custom')->encode($lama).'/kembalikan')
            ->assertOk();

        $this->assertSame(SopStatus::VERSI_PUBLISHED,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $lama)->value('Status'),
            'Versi yang dikembalikan harus jadi yang berlaku.');
        $this->assertSame(SopStatus::VERSI_OBSOLETE,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $baru)->value('Status'),
            'Versi yang digantikan harus dicabut — dua versi berlaku sekaligus mustahil dibaca.');

        /* Pointer DOKUMEN ikut kembali. rollback() hanya menggeser pointer item;
           tanpa baris tambahan di layanan, dokumen akan menyebut versi lain yang
           berlaku daripada yang benar-benar disajikan. */
        $this->assertSame((int) $lama,
            (int) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)->value('Id_Versi_Aktif'));

        // DRAFT bukan urusan rollback — ia belum pernah berlaku.
        $draft = $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji', 'Id_Master_Konten' => $konten], 1);
        $this->actingAs($admin)
            ->postJson('/api/v1/sop/admin/dokumen/versi/'
                .Hashids::connection('custom')->encode($draft).'/kembalikan')
            ->assertStatus(422);
    }

    /**
     * FORM ber-PDF bisa DIBACA di layar, FORM lain hanya bisa diunduh.
     *
     * Aturannya milik SopAksesBerkas::bisaPratinjau() dan sudah dipakai sisi
     * karyawan; yang kurang cuma admin belum menerima penandanya, sehingga
     * seluruh FORM diperlakukan sebagai unduhan — termasuk yang PDF, yang
     * sebenarnya bisa dibaca langsung.
     *
     * Memaksa xlsx atau docx ke penampil hanya menghasilkan layar kosong,
     * jadi penandanya harus per BERKAS, bukan per tipe dokumen.
     */
    public function test_penanda_pratinjau_membedakan_form_pdf_dari_form_lain(): void
    {
        $admin = $this->adminAtauSkip();

        $item = DB::table('N_SOP_Paket_Item')
            ->where('Tipe_Item', 'FORM')->whereNotNull('Id_SOP_Dokumen')
            ->where('Flag_Aktif', SopStatus::AKTIF)->first(['Id_SOP_Dokumen']);
        if (! $item) {
            $this->markTestSkipped('Tak ada dokumen FORM di data dev.');
        }

        $riwayat = $this->actingAs($admin)
            ->getJson('/api/v1/sop/admin/dokumen/'
                .Hashids::connection('custom')->encode($item->Id_SOP_Dokumen).'/detail')
            ->assertOk()->json('result.riwayat_versi');

        $this->assertNotEmpty($riwayat);
        foreach ($riwayat as $v) {
            $this->assertArrayHasKey('Bisa_Pratinjau', $v,
                'Tanpa penanda ini, layar hanya bisa menebak dari tipe dokumen — dan '
                .'tebakan itu salah untuk FORM ber-PDF.');
            $this->assertIsBool($v['Bisa_Pratinjau']);

            /* Tak punya berkas berarti tak ada yang bisa dipratinjau. Membiarkan
               keduanya lepas akan memunculkan tombol Lihat pada versi kosong. */
            if (! $v['Punya_Berkas']) {
                $this->assertFalse($v['Bisa_Pratinjau'],
                    'Versi tanpa berkas tak boleh mengaku bisa dipratinjau.');
            }
        }
    }

    /**
     * Versi yang sudah DICABUT tak bisa diterbitkan ulang.
     *
     * Syarat lama cuma "bukan PUBLISHED", sehingga versi OBSOLETE ikut
     * menawarkan terbit. Menerbitkannya bukan penerbitan melainkan
     * PENGEMBALIAN: pointer mundur ke revisi lama, jejak auditnya berbunyi
     * "versi baru diterbitkan" padahal yang terjadi sebaliknya, dan
     * rekonsiliasi kewajiban yang jadi bagian rollback dilewati sama sekali.
     *
     * Itulah yang membuat pratinjau menampilkan "v04 -> v03" — bacaan yang
     * benar atas tindakan yang seharusnya tak ditawarkan.
     */
    public function test_versi_obsolete_tak_bisa_diterbitkan_ulang(): void
    {
        $admin = $this->adminAtauSkip();
        $svc = app(SopDokumenService::class);

        $konten = DB::table('N_LMS_Master_Konten')->where('Tipe_Konten', 'document')
            ->value('Id_Master_Konten');
        if (! $konten) {
            $this->markTestSkipped('Tak ada konten dokumen di Library dev.');
        }

        $idDok = $svc->buat(['Judul' => 'SOP UJI OBSOLETE', 'Tipe' => 'DOKUMEN'], 1);

        $lama = $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji', 'Id_Master_Konten' => $konten], 1);
        $this->siapkanTerbit($lama);
        $svc->terbitkanVersi($lama, 1);

        $baru = $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji', 'Id_Master_Konten' => $konten], 1);
        $this->siapkanTerbit($baru);
        $svc->terbitkanVersi($baru, 1);

        $this->assertSame(SopStatus::VERSI_OBSOLETE,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $lama)->value('Status'));

        $this->actingAs($admin)
            ->postJson('/api/v1/sop/admin/dokumen/versi/'
                .Hashids::connection('custom')->encode($lama).'/terbitkan')
            ->assertStatus(422);

        $this->assertSame((int) $baru,
            (int) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)->value('Id_Versi_Aktif'),
            'Penolakan tak boleh memundurkan pointer versi aktif.');
    }

    /**
     * Peran penanda tangan dibatasi daftar tetap.
     *
     * Teks bebas sudah terbukti menghasilkan "Disetujui" dan "Disetujui oleh"
     * sebagai dua kelompok berbeda untuk satu maksud, plus satu salah ketik
     * yang ikut tersimpan. Laporan "berapa SOP yang sudah diperiksa" jadi harus
     * menebak ejaan mana yang dimaksud.
     *
     * NILAI LAMA TIDAK DINORMALKAN: seluruh varian menyimpang ada pada versi
     * PUBLISHED dan OBSOLETE — catatan yang sudah diteken dan riwayat yang
     * sudah lewat. Yang dijaga daftar ini adalah baris yang ditulis sejak kini.
     */
    public function test_peran_pengesah_dibatasi_daftar_tetap(): void
    {
        $admin = $this->adminAtauSkip();
        $svc = app(SopDokumenService::class);

        $idDok = $svc->buat(['Judul' => 'SOP UJI PERAN', 'Tipe' => 'DOKUMEN'], 1);
        $id = Hashids::connection('custom')->encode($idDok);

        // Di luar daftar -> ditolak sebelum menyentuh basis data.
        $this->actingAs($admin)
            ->postJson("/api/v1/sop/admin/dokumen/{$id}/versi", [
                'Pengesah' => [['Peran' => 'Dianukan oleh', 'Nama' => 'Seseorang']],
            ])
            ->assertStatus(422);

        // Setiap nilai dalam daftar harus diterima — kalau tidak, layar
        // menawarkan pilihan yang ditolak server, dan admin baru tahu
        // setelah menekan simpan.
        foreach (\App\Services\LMS\SOP\Support\SopPeranPengesah::SEMUA as $peran) {
            $this->actingAs($admin)
                ->postJson("/api/v1/sop/admin/dokumen/{$id}/versi", [
                    'Pengesah' => [['Peran' => $peran, 'Nama' => 'Seseorang']],
                ])
                ->assertStatus(201);
        }
    }

    /**
     * DRAFT boleh disunting dan dihapus; yang sudah TERBIT tidak.
     *
     * Draft belum dilihat siapa pun, jadi melarangnya tak melindungi apa-apa —
     * cuma memaksa admin meninggalkan draft salah ketik menumpuk di riwayat.
     *
     * Versi PUBLISHED sebaliknya: itu catatan yang sudah dibaca dan
     * dikonfirmasi karyawan. Mengubah isinya membuat rekam kepatuhan mereka
     * berbunyi "selesai membaca v03" sementara v03 diam-diam menjadi dokumen
     * lain — kegagalan yang membuat dokumen terkendali ada. Koreksinya lewat
     * revisi minor, yang mencatat perbaikannya alih-alih menyembunyikannya.
     */
    public function test_draft_bisa_disunting_dan_dihapus_tapi_terbit_tidak(): void
    {
        $admin = $this->adminAtauSkip();
        $svc = app(SopDokumenService::class);

        $konten = DB::table('N_LMS_Master_Konten')->value('Id_Master_Konten');
        if (! $konten) {
            $this->markTestSkipped('Tak ada konten Library di data dev.');
        }

        $idDok = $svc->buat(['Judul' => 'SOP UJI SUNTING DRAFT', 'Tipe' => 'DOKUMEN'], 1);
        $id = Hashids::connection('custom')->encode($idDok);

        $draft = $svc->tambahVersi($idDok, [
            'Id_Master_Konten' => $konten,
            'Pengesah' => [['Peran' => 'Disetujui oleh', 'Nama' => 'Nama Salah Ketik']],
        ], 1);

        // SUNTING draft: nama diperbaiki, nomor digeser.
        $this->actingAs($admin)
            ->putJson('/api/v1/sop/admin/dokumen/versi/'
                .Hashids::connection('custom')->encode($draft), [
                    'Versi_Ke' => 7,
                    'Id_Master_Konten' => Hashids::connection('custom')->encode($konten),
                    'Pengesah' => [['Peran' => 'Disetujui oleh', 'Nama' => 'Nama Benar']],
                ])
            ->assertOk();

        $v = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $draft)
            ->first(['Versi_Ke', 'Disahkan_Oleh']);
        $this->assertSame(7, (int) $v->Versi_Ke);
        $this->assertSame('Nama Benar', $v->Disahkan_Oleh);

        /* Blok ditulis ULANG, bukan ditambahi — kalau ditambahi, baris yang
           sudah dihapus admin tetap tertinggal. */
        $this->assertSame(1, DB::table('N_SOP_Item_Versi_Pengesah')
            ->where('Id_SOP_Item_Versi', $draft)->count());

        // TERBITKAN, lalu buktikan kedua pintu tertutup.
        $this->siapkanTerbit($draft);
        $svc->terbitkanVersi($draft, 1);

        $this->actingAs($admin)
            ->putJson('/api/v1/sop/admin/dokumen/versi/'
                .Hashids::connection('custom')->encode($draft), ['Versi_Ke' => 8])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/sop/admin/dokumen/versi/'
                .Hashids::connection('custom')->encode($draft))
            ->assertStatus(422);

        $this->assertSame(7, (int) DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $draft)->value('Versi_Ke'),
            'Versi terbit tak boleh berubah sedikit pun oleh permintaan yang ditolak.');

        // Draft LAIN tetap bisa dihapus, berikut blok penanda tangannya.
        $draft2 = $svc->tambahVersi($idDok, [
            'Id_Master_Konten' => $konten,
            'Pengesah' => [['Nama' => 'Ikut Terhapus']],
        ], 1);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/sop/admin/dokumen/versi/'
                .Hashids::connection('custom')->encode($draft2))
            ->assertOk();

        $this->assertFalse(DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $draft2)->exists());
        $this->assertSame(0, DB::table('N_SOP_Item_Versi_Pengesah')
            ->where('Id_SOP_Item_Versi', $draft2)->count(),
            'Blok penanda tangan menggantung lewat foreign key — ia harus ikut jatuh.');

        $this->assertNotNull($id);
    }

    /**
     * Menyusuri SELURUH halaman harus menghasilkan tepat satu baris per SOP —
     * tak ada yang kembar, tak ada yang hilang.
     *
     * Judul tidak unik di arsip ini, dan kedua urutan lain penuh seri. Tanpa
     * kunci unik di ujung ORDER BY, SQL Server bebas menyusun ulang baris yang
     * seri pada tiap kueri: satu SOP muncul di dua halaman sementara yang lain
     * tak pernah muncul. Selama daftar muat satu halaman cacat itu tak
     * terlihat — ia baru menggigit setelah paginasi memenggal.
     */
    public function test_menyusuri_semua_halaman_tanpa_kembar_dan_tanpa_hilang(): void
    {
        $admin = $this->adminAtauSkip();

        foreach (['terbaru', 'judul', 'pemakai', 'kadaluarsa'] as $urut) {
            $terkumpul = [];
            $page = 1;
            $total = null;

            do {
                $hasil = $this->actingAs($admin)
                    ->getJson("/api/v1/sop/admin/dokumen/current?urut={$urut}&limit=5&page={$page}")
                    ->assertOk()->json('result');

                $total ??= $hasil['total'];
                foreach ($hasil['rows'] as $r) {
                    $terkumpul[] = $r['Id_SOP_Dokumen'];
                }
                $page++;
            } while ($page <= $hasil['total_page'] && $page < 50);

            $this->assertCount($total, $terkumpul,
                "Urut '{$urut}': jumlah baris terkumpul lintas halaman harus sama dengan total.");
            $this->assertSame(count($terkumpul), count(array_unique($terkumpul)),
                "Urut '{$urut}': ada dokumen yang muncul di lebih dari satu halaman — "
                .'artinya ada SOP lain yang tak pernah muncul sama sekali.');
        }
    }

    /**
     * Tombol terbit tak boleh menjanjikan apa yang gerbangnya tolak.
     *
     * Layar sempat menghafal jawabannya sendiri — "tampilkan Pratinjau Terbit
     * bila tahapnya APPROVAL" — dan hafalan itu basi sejak DML-2026-048:
     * APPROVAL kini adalah langkah Pengesahan yang BELUM selesai, dan satu-
     * satunya transisi hidup menuju Filing and Registered berangkat dari
     * APPROVED. Akibatnya admin yang versinya masih menunggu pengesahan
     * disuguhi tombol terbit yang pasti ditolak terbitkanVersi(), sekaligus
     * kehilangan tombol wizard yang tersembunyi di balik v-else-nya.
     *
     * BERDIRI DI PEMBERKASAN BELUM BERARTI SELESAI. Langkah terakhir itulah
     * tempat berkas final dan tiga tanggal masa berlaku diisi. Menawarkan
     * terbit sebelum checklistnya penuh melahirkan versi yang menggantung —
     * datanya kurang dibanding versi lain, padahal sudah mengikat karyawan.
     *
     * Yang diuji adalah EFEKNYA di payload, bukan nama tahapnya: begitu
     * kebijakan alur bergeser lagi di tabel transisi atau tabel syarat,
     * jawaban di sini ikut bergeser tanpa satu baris pun diubah.
     */
    public function test_boleh_terbit_mengikuti_transisi_dan_syarat_bukan_nama_tahap(): void
    {
        $admin = $this->adminAtauSkip();
        $svc = app(SopDokumenService::class);

        $idDok = $svc->buat(['Judul' => 'SOP GERBANG PRATINJAU TERBIT', 'Tipe' => 'DOKUMEN'], 1);
        $idVersi = $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji'], 1);
        $this->assertNotNull($idVersi, 'Versi uji gagal lahir.');

        $token = Hashids::connection('custom')->encode($idDok);

        /* Tahap dipasang langsung, bukan lewat pindah(): yang diuji adalah
           pembacaan gerbang, dan menyeret syarat perpindahan ke dalamnya
           membuat test ini gagal karena sebab yang sama sekali lain. */
        $tahapId = fn (string $kode) => (int) DB::table('N_SOP_Master_Tahap_Versi')
            ->where('Kode_Tahap', $kode)->value('Id_Tahap');

        $barisDi = function (string $kode) use ($admin, $token, $idVersi, $tahapId): array {
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
                ->update(['Tahap_Id' => $tahapId($kode)]);

            $rows = $this->actingAs($admin)
                ->getJson("/api/v1/sop/admin/dokumen/{$token}/versi?limit=50")
                ->assertOk()->json('result.rows');

            $baris = collect($rows)->firstWhere(
                'Id_SOP_Item_Versi', Hashids::connection('custom')->encode($idVersi));

            $this->assertNotNull($baris, "Versi uji tak muncul di riwayat saat tahap {$kode}.");
            $this->assertArrayHasKey('Boleh_Terbit', $baris,
                'Tanpa kolom ini layar membaca undefined dan kembali menebak dari nama tahap.');

            return $baris;
        };

        $this->assertFalse($barisDi('DRAFTING')['Boleh_Terbit'],
            'Versi yang baru didraft jelas belum boleh diterbitkan.');

        $this->assertFalse($barisDi('APPROVAL')['Boleh_Terbit'],
            'Pengesahan BELUM selesai di tahap APPROVAL — transisi APPROVAL→FILING_REGISTERED '
            .'sudah dimatikan DML-2026-048, jadi tombol terbit di sini adalah jalan buntu.');

        /* Sudah di Pemberkasan, tapi berkas dan tanggalnya belum ada. */
        $belumLengkap = $barisDi('APPROVED');
        $this->assertFalse($belumLengkap['Boleh_Terbit'],
            'Berdiri di Pemberkasan tanpa berkas final dan tanggal masa berlaku BUKAN "siap '
            .'terbit" — menerbitkannya melahirkan versi yang datanya menggantung.');
        $this->assertNotEmpty($belumLengkap['Kurang_Terbit'],
            'Tombol yang hilang tanpa sebab membuat admin menebak. Yang kurang harus disebut.');

        /* Lengkapi seluruh checklist tahap terbit, lalu gerbangnya harus buka. */
        $konten = DB::table('N_LMS_Master_Konten')->value('Id_Master_Konten');
        if (! $konten) {
            $this->markTestSkipped('Tak ada konten untuk dijadikan berkas final versi uji.');
        }
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->update([
            'Id_Master_Konten' => $konten,
            'Tanggal_Berlaku' => now()->toDateString(),
            'Tanggal_Kadaluarsa' => now()->addYear()->toDateString(),
            'Tanggal_Review_Berikutnya' => now()->addMonths(6)->toDateString(),
        ]);

        $lengkap = $barisDi('APPROVED');
        $this->assertSame([], $lengkap['Kurang_Terbit'],
            'Checklist sudah penuh — sisa yang dilaporkan berarti syaratnya dibaca dari sumber '
            .'yang berbeda dengan yang menegakkannya.');
        $this->assertTrue($lengkap['Boleh_Terbit'],
            'Pemberkasan yang tuntas adalah satu-satunya keadaan yang boleh terbit — bila di '
            .'sini pun tertutup, tak ada versi yang bisa diterbitkan dari Master Dokumen.');
    }

    /**
     * Pratinjau berkas di langkah Pemberkasan harus punya alamat yang SUNGGUHAN.
     *
     * Layar sempat merakit sendiri "/api/v1/sop/konten/{id}/file" — rute yang
     * tak pernah terdaftar — sehingga tombol Pratinjau Berkas selalu berakhir
     * 404. Berkas SOP memang tak bisa dialamati langsung: ia duduk di GCS dan
     * hanya terbuka lewat signed URL, jadi yang menjawab harus server.
     *
     * Dikunci KONTEN, bukan versi: di wizard berkasnya baru dipilih dan belum
     * tersimpan, sehingga endpoint per-versi akan menampilkan berkas lama.
     */
    public function test_pratinjau_berkas_konten_punya_endpoint_yang_menjawab(): void
    {
        $admin = $this->adminAtauSkip();

        $konten = DB::table('N_LMS_Master_Konten as k')
            ->join('N_LMS_Master_Konten_Berkas as b', 'b.Id_Master_Konten', '=', 'k.Id_Master_Konten')
            ->where('k.Scope', 'SOP')
            ->whereNotNull('b.File_Path')
            ->first(['k.Id_Master_Konten']);
        if (! $konten) {
            $this->markTestSkipped('Tak ada konten SOP berberkas untuk dipratinjau.');
        }

        $token = Hashids::connection('custom')->encode($konten->Id_Master_Konten);

        $hasil = $this->actingAs($admin)
            ->getJson("/api/v1/sop/admin/dokumen/konten/{$token}/berkas")
            ->assertOk()->json('result');

        $this->assertNotEmpty($hasil['url'] ?? null,
            'Tanpa url, penampil membuka layar kosong — persis gejala 404 yang diperbaiki.');
        $this->assertNotEmpty($hasil['mime'] ?? null,
            'Mime dipakai penampil untuk memilih mode. Layar dulu memaku application/pdf, '
            .'sehingga berkas docx dirender sebagai PDF rusak.');

        /* ISOLASI SCOPE. Endpoint ini berdiri di gerbang Master Dokumen; konten
           milik modul lain tak boleh ikut terbuka lewat sini. */
        $luar = DB::table('N_LMS_Master_Konten')->where('Scope', '<>', 'SOP')
            ->value('Id_Master_Konten');
        if ($luar) {
            $this->actingAs($admin)
                ->getJson('/api/v1/sop/admin/dokumen/konten/'
                    .Hashids::connection('custom')->encode($luar).'/berkas')
                ->assertStatus(404);
        }
    }
}
