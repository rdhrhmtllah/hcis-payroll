<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopTahapVersiService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Versi SOP yang dibatalkan: beku sebagai arsip, dan nomornya dilepas.
 *
 * AKAR YANG MENGIKAT KEDUANYA — tahap CANCEL punya Status_Teknis = 'DRAFT'.
 * Versi yang dibatalkan TETAP berstatus DRAFT; yang berpindah adalah tahapnya.
 * Setiap penjaga yang ditulis `Status === 'DRAFT'` karena itu diam-diam
 * meloloskannya, dan arsip yang seharusnya beku masih menerima suntingan.
 *
 * Rancangannya: docs/superpowers/specs/2026-08-30-sop-versi-batal-beku-dan-
 * nomor-dipakai-ulang-design.md
 */
class SopVersiBatalBekuTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopDokumenService
    {
        return app(SopDokumenService::class);
    }

    /** Cap tulis-bersamaan sebuah versi, sama rumusnya dengan Cap_Versi milik server. */
    private function capVersi(int $idVersi): string
    {
        $r = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->first(['Updated_At', 'Created_At']);

        return (string) (($r->Updated_At ?? null) ?: $r->Created_At);
    }

    /** Dokumen baru + satu versi, lalu versinya dibatalkan. */
    private function versiBatal(): array
    {
        $svc = $this->svc();

        $idDok = $svc->buat([
            'Judul' => 'SOP UJI BEKU '.substr((string) microtime(true), -5),
            'Tipe' => 'DOKUMEN',
        ], 1);

        $idVersi = $svc->tambahVersi($idDok, ['Ringkasan_Perubahan' => 'Sebelum dibatalkan'], 1);
        $this->assertNotNull($idVersi, 'Versi fixture gagal lahir.');

        /* PEMBATALAN TAK BISA DICAPAI DARI DRAFTING. DML-2026-042 memutuskan
           "di tahap Pemberkasan user menentukan versi ini diterbitkan atau
           dibatalkan", dan menutup jalur batal dari langkah-langkah awal:
           menawarkan keputusan sebesar itu sebelum ada yang bisa ditimbang.
           Satu-satunya tujuan sah dari Drafting adalah Review.

           Fixture ini karena itu MENYUSURI alurnya, bukan menyetel Tahap_Id
           langsung. Menyetelnya langsung akan melewati aturan yang justru
           menjadi konteks seluruh berkas uji ini — dan uji yang melangkahi
           alur berhenti membuktikan bahwa alurnya bekerja. */
        $tahap = app(SopTahapVersiService::class);
        $tahap->pindah($idVersi, 'REVIEW', 1);
        $tahap->pindah($idVersi, 'APPROVAL', 1);
        $tahap->pindah($idVersi, 'CANCEL', 1);

        return [$idDok, $idVersi];
    }

    /**
     * Penilai tunggalnya membaca TAHAP, bukan status.
     *
     * Kalau ia jatuh kembali ke `Status`, seluruh penjaga di bawah ikut mati
     * tanpa satu pun galat — sebab versi batal memang berstatus DRAFT.
     */
    public function test_dibatalkan_dibaca_dari_tahap_bukan_status(): void
    {
        [, $idVersi] = $this->versiBatal();

        $this->assertSame(SopStatus::PAKET_DRAFT,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value('Status'),
            'Versi batal seharusnya TETAP berstatus DRAFT — kalau tidak, premis '
            .'seluruh berkas uji ini sudah berubah dan penjaganya perlu ditinjau ulang.');

        $this->assertTrue($this->svc()->dibatalkan($idVersi));
    }

    /** Versi yang masih berjalan jelas bukan arsip — penjaganya tak boleh menyala. */
    public function test_versi_berjalan_tidak_dianggap_arsip(): void
    {
        $svc = $this->svc();
        $idDok = $svc->buat(['Judul' => 'SOP UJI HIDUP '.substr((string) microtime(true), -5), 'Tipe' => 'DOKUMEN'], 1);
        $idVersi = $svc->tambahVersi($idDok, [], 1);

        $this->assertFalse($svc->dibatalkan($idVersi),
            'Draft yang masih berjalan ikut terhitung arsip — penjaganya akan mengunci '
            .'versi yang justru sedang dikerjakan.');
    }

    /** ── Pintu 1: menyunting isian ──────────────────────────────────────── */
    public function test_arsip_menolak_disunting(): void
    {
        [, $idVersi] = $this->versiBatal();

        $galat = $this->svc()->ubahVersi($idVersi, ['Ringkasan_Perubahan' => 'Diubah sesudah batal'], 1);

        $this->assertSame(SopDokumenService::PESAN_ARSIP, $galat);
        $this->assertSame('Sebelum dibatalkan',
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value('Ringkasan_Perubahan'),
            'Penolakannya dilaporkan tapi isinya tetap berubah — penjaga yang mengembalikan '
            .'pesan sesudah menulis bukan penjaga.');
    }

    /** ── Pintu 2: menghapus ─────────────────────────────────────────────── */
    public function test_arsip_menolak_dihapus(): void
    {
        [, $idVersi] = $this->versiBatal();

        $galat = $this->svc()->hapusVersi($idVersi, 1);

        $this->assertSame(SopDokumenService::PESAN_ARSIP, $galat);
        $this->assertTrue(
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->exists(),
            'Barisnya hilang padahal penolakan dilaporkan. Menghapus arsip membuang jejak '
            .'keputusan membatalkan — justru bagian yang paling ditanyakan saat diaudit.');
    }

    /**
     * ── Pintu 3: catatan review ─────────────────────────────────────────
     *
     * Pintu ini sebelumnya tak punya penjaga status sama sekali. Penjaganya
     * tinggal di controller, jadi diuji lewat HTTP — menguji konstantanya di
     * sini tak membuktikan apa pun tentang endpoint-nya.
     */
    public function test_arsip_menolak_catatan_review_baru(): void
    {
        $admin = User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopDokumenPage'));
        if (! $admin) {
            $this->markTestSkipped('Belum ada user dengan akses menuSopDokumenPage.');
        }

        [, $idVersi] = $this->versiBatal();
        $token = Hashids::connection('custom')->encode($idVersi);

        $sebelum = DB::table('N_SOP_Item_Versi_Review')
            ->where('Id_SOP_Item_Versi', $idVersi)->count();

        $this->actingAs($admin)
            ->putJson("/api/v1/sop/admin/dokumen/versi/{$token}/review", [
                'Review' => [
                    ['Review_Ke' => 1, 'Tanggal_Review' => now()->toDateString(),
                        'Hasil_Review' => 'Catatan sesudah versi dibatalkan.'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', SopDokumenService::PESAN_ARSIP);

        $this->assertSame($sebelum,
            DB::table('N_SOP_Item_Versi_Review')->where('Id_SOP_Item_Versi', $idVersi)->count(),
            'Penolakan dilaporkan tapi catatannya tetap tersimpan.');
    }

    /** ── Pintu yang SUDAH terjaga — dipastikan tetap tertutup ───────────── */
    public function test_arsip_tak_bisa_pindah_tahap_maupun_terbit(): void
    {
        [, $idVersi] = $this->versiBatal();
        $tahap = app(SopTahapVersiService::class);

        $this->assertSame([], $tahap->tujuanSah($idVersi),
            'CANCEL adalah tahap terminal — tak boleh ada satu pun tujuan sah dari sana, '
            .'termasuk jalan kembali untuk membangkitkannya.');

        try {
            $tahap->pindah($idVersi, 'DRAFTING', 1);
            $this->fail('Versi batal berhasil dipindahkan keluar dari tahap terminal.');
        } catch (\DomainException $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        try {
            $this->svc()->terbitkanVersi($idVersi, 1);
            $this->fail('Versi batal berhasil diterbitkan.');
        } catch (\DomainException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    /**
     * ── Penomoran: nomor yang dibatalkan dipakai ulang ──────────────────
     *
     * Draft yang batal tak pernah menjadi revisi yang diterbitkan, jadi ia tak
     * boleh mengonsumsi nomor revisi. Tanpa ini nomor resmi dokumen melompat
     * dari …/00 ke …/02, menyebut Revisi 01 yang tak pernah sampai ke siapa pun.
     *
     * CATATAN: uji ini menuntut DDL-2026-062 sudah dijalankan. Selama indeks
     * uniknya masih yang lama, penyimpanan versi kedua ditolak basis data
     * dengan galat 2601 — dan itulah yang akan terlihat di sini.
     */
    public function test_nomor_versi_yang_dibatalkan_dipakai_ulang(): void
    {
        [$idDok, $idBatal] = $this->versiBatal();

        $this->assertSame(0,
            (int) DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idBatal)->value('Versi_Ke'));

        $idBaru = $this->svc()->tambahVersi($idDok, [], 1);
        $this->assertNotNull($idBaru,
            'Versi baru gagal lahir. Bila sebabnya galat kembar (2601), DDL-2026-062 '
            .'belum dijalankan — indeks uniknya masih memandang versi batal.');

        $this->assertSame(0,
            (int) DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idBaru)->value('Versi_Ke'),
            'Versi pengganti memakai nomor berikutnya, bukan nomor yang dilepas versi batal.');
    }

    /**
     * Versi yang PERNAH TERBIT tak pernah melepas nomornya, walau kemudian
     * tergantikan. Ia sudah beredar; memakai ulang nomornya berarti dua dokumen
     * berbeda beredar sebagai revisi yang sama.
     */
    public function test_nomor_versi_terbit_tetap_terkunci(): void
    {
        $svc = $this->svc();
        $idDok = $svc->buat(['Judul' => 'SOP UJI KUNCI '.substr((string) microtime(true), -5), 'Tipe' => 'DOKUMEN'], 1);
        $idVersi = $svc->tambahVersi($idDok, [], 1);

        /* Statusnya disetel langsung: yang diuji penomorannya, dan menyeret
           seluruh gerbang penerbitan ke sini membuat uji gagal karena sebab lain. */
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->update(['Status' => SopStatus::VERSI_PUBLISHED, 'Flag_Aktif' => SopStatus::AKTIF]);

        $idBaru = $svc->tambahVersi($idDok, [], 1);
        $this->assertNotNull($idBaru);

        $this->assertSame(1,
            (int) DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idBaru)->value('Versi_Ke'),
            'Nomor versi yang sudah terbit ikut dilepas — dua dokumen berbeda akan beredar '
            .'sebagai revisi yang sama.');
    }

    /**
     * Muatan membawa Dibatalkan sebagai FAKTA — dijaga di pintunya.
     *
     * Setiap layar sebelumnya menyimpulkannya sendiri, dan simpulan itu
     * meleset dengan cara yang berbeda-beda:
     *
     *   wizard  → membaca `tahap.Kode` padahal muatan mengirim `Kode_Tahap`;
     *             mencari kata "batal" pada nama tahap yang berbunyi "Cancel";
     *             mencari kelompok langkah pada tahap yang tak punya langkah.
     *             Tujuh cabang OR, tak satu pun menyala. Wizard versi arsip
     *             karena itu terbuka penuh untuk disunting.
     *   modal   → `Status === 'DRAFT'`, yang memang benar bagi versi batal,
     *             sehingga Sunting, Hapus, dan Lengkapi Versi ikut tampil.
     *
     * Selama jawabannya tak dikirim server, layar berikutnya akan menyimpulkan
     * sendiri lagi.
     */
    public function test_muatan_membawa_dibatalkan_sebagai_fakta(): void
    {
        $admin = User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopDokumenPage'));
        if (! $admin) {
            $this->markTestSkipped('Belum ada user dengan akses menuSopDokumenPage.');
        }

        [$idDok, $idVersi] = $this->versiBatal();

        /* Pintu 1 — riwayat versi, dibaca modal & Master Dokumen. */
        $baris = collect(
            $this->actingAs($admin)
                ->getJson('/api/v1/sop/admin/dokumen/'
                    .Hashids::connection('custom')->encode($idDok).'/versi?limit=50')
                ->assertOk()->json('result.rows')
        )->firstWhere('Id_SOP_Item_Versi', Hashids::connection('custom')->encode($idVersi));

        $this->assertNotNull($baris, 'Versi batal tak muncul di riwayat.');
        $this->assertArrayHasKey('Dibatalkan', $baris,
            'Tanpa kolom ini layar membaca undefined dan kembali menyimpulkan sendiri.');
        $this->assertTrue($baris['Dibatalkan']);
        $this->assertSame('DRAFT', $baris['Status'],
            'Premis seluruh cacat ini: versi batal TETAP berstatus DRAFT. Kalau tidak lagi, '
            .'penjaga di berkas ini perlu ditinjau ulang.');

        /* Pintu 2 — alur langkah, dibaca wizard. */
        $tahap = $this->actingAs($admin)
            ->getJson('/api/v1/sop/admin/dokumen/versi/'
                .Hashids::connection('custom')->encode($idVersi).'/langkah')
            ->assertOk()->json('result.Tahap');

        $this->assertArrayHasKey('Dibatalkan', $tahap);
        $this->assertTrue($tahap['Dibatalkan'],
            'Wizard tak akan mengunci formulirnya, dan versi arsip bisa disunting penuh.');
    }

    /**
     * TULIS-BERSAMAAN — cap yang basi ditolak, bukan menimpa diam-diam.
     *
     * Wizard versi berumur lima langkah (berkas, matriks pengesah, tiga
     * tanggal), jadi jendela dua admin pada satu draft bukan hitungan detik.
     * Sebelum penjaga ini, yang menyimpan belakangan menimpa yang pertama tanpa
     * satu pun tanda.
     */
    public function test_cap_basi_ditolak_dan_tak_menimpa(): void
    {
        $svc = $this->svc();
        $idDok = $svc->buat(['Judul' => 'SOP UJI CAP '.substr((string) microtime(true), -5), 'Tipe' => 'DOKUMEN'], 1);
        $idVersi = $svc->tambahVersi($idDok, ['Ringkasan_Perubahan' => 'Awal'], 1);

        /* Admin A membaca capnya. Updated_At masih NULL pada baris yang baru
           lahir, jadi capnya jatuh ke Created_At — persis seperti yang dikirim
           server sebagai Cap_Versi. Membaca Updated_At saja di sini menghasilkan
           cap kosong, dan penjaganya dilewati: uji yang hijau tanpa menguji. */
        $capA = $this->capVersi($idVersi);

        /* Admin B menyimpan lebih dulu — capnya bergeser. */
        $this->assertNull($svc->ubahVersi($idVersi, ['Ringkasan_Perubahan' => 'Tulisan B'], 1),
            'Simpanan pertama seharusnya lolos.');

        /* Admin A menyimpan dengan cap yang sudah basi. */
        $galat = $svc->ubahVersi($idVersi, [
            'Ringkasan_Perubahan' => 'Tulisan A',
            'Updated_At_Dibaca' => (string) $capA,
        ], 1);

        $this->assertNotNull($galat, 'Cap basi diterima — pekerjaan admin lain tertimpa diam-diam.');
        $this->assertStringContainsString('diubah orang lain', $galat);
        $this->assertSame('Tulisan B',
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value('Ringkasan_Perubahan'),
            'Penolakan dilaporkan tapi isinya tetap tertimpa.');
    }

    /**
     * GAGAL KE ARAH "TERIMA" bila capnya tak dikirim.
     *
     * Jalur non-layar — backfill, penyalinan paket, perintah artisan — tak punya
     * cap untuk dikirim. Menolak mereka berarti mematikan jalur yang tak pernah
     * berebut dengan siapa pun.
     */
    public function test_tanpa_cap_penyimpanan_tetap_diterima(): void
    {
        $svc = $this->svc();
        $idDok = $svc->buat(['Judul' => 'SOP UJI TANPA CAP '.substr((string) microtime(true), -5), 'Tipe' => 'DOKUMEN'], 1);
        $idVersi = $svc->tambahVersi($idDok, [], 1);

        $svc->ubahVersi($idVersi, ['Ringkasan_Perubahan' => 'Sekali'], 1);

        $this->assertNull($svc->ubahVersi($idVersi, ['Ringkasan_Perubahan' => 'Dua kali'], 1),
            'Pemanggil tanpa cap ikut terkunci — jalur batch dan artisan mati.');
    }

    /** Cap yang MASIH segar tak boleh ditolak — penjaga yang selalu menyala tak berguna. */
    public function test_cap_segar_diterima(): void
    {
        $svc = $this->svc();
        $idDok = $svc->buat(['Judul' => 'SOP UJI CAP SEGAR '.substr((string) microtime(true), -5), 'Tipe' => 'DOKUMEN'], 1);
        $idVersi = $svc->tambahVersi($idDok, [], 1);

        $cap = $this->capVersi($idVersi);

        $this->assertNull($svc->ubahVersi($idVersi, [
            'Ringkasan_Perubahan' => 'Dengan cap segar',
            'Updated_At_Dibaca' => (string) $cap,
        ], 1));
    }

    /**
     * Muatan membawa Updated_At — tanpanya layar tak punya cap untuk dikirim,
     * dan penjaga tulis-bersamaan di atas tak pernah aktif bagi siapa pun.
     *
     * Diuji pada KEDUA kueri riwayat: keduanya berbagi satu pemformat, dan
     * kolom yang hanya ada di salah satunya berakhir sebagai separuh layar
     * yang menyimpan tanpa perlindungan.
     */
    public function test_muatan_riwayat_membawa_cap(): void
    {
        $admin = User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopDokumenPage'));
        if (! $admin) {
            $this->markTestSkipped('Belum ada user dengan akses menuSopDokumenPage.');
        }

        $svc = $this->svc();
        $idDok = $svc->buat(['Judul' => 'SOP UJI MUATAN CAP '.substr((string) microtime(true), -5), 'Tipe' => 'DOKUMEN'], 1);
        $svc->tambahVersi($idDok, [], 1);
        $token = Hashids::connection('custom')->encode($idDok);

        foreach ([
            "/api/v1/sop/admin/dokumen/{$token}/versi?limit=50" => 'result.rows',
            "/api/v1/sop/admin/dokumen/{$token}/detail" => 'result.riwayat_versi',
        ] as $url => $jalur) {
            $baris = $this->actingAs($admin)->getJson($url)->assertOk()->json($jalur);
            $this->assertNotEmpty($baris, "Riwayat kosong pada {$url}.");
            $this->assertNotEmpty($baris[0]['Cap_Versi'] ?? null,
                "Kueri di balik {$url} tak membawa Cap_Versi — layar yang memakainya "
                .'menyimpan tanpa penjaga tulis-bersamaan.');
            $this->assertArrayHasKey('Boleh_Disunting', $baris[0]);
        }
    }

    /**
     * NOMOR USULAN DI MUATAN WIZARD HARUS SAMA dengan yang dipakai server.
     *
     * Ini cacat yang lolos dari perbaikan pertama, dan lolosnya diam-diam.
     * `nomorVersiBerikut()` sudah menyaring versi hidup, tapi muatan wizard
     * menghitung MAX-nya SENDIRI tanpa saringan, mengirimkannya ke layar sebagai
     * usulan, dan layar mengembalikannya sebagai nomor MANUAL. Penomoran
     * otomatis yang sudah benar karena itu tak pernah terpakai: versi baru pada
     * dokumen yang v01-nya dibatalkan lahir sebagai v02, dan nomor resmi
     * melompat dari …/00 ke …/02.
     *
     * Diuji lewat MUATAN HALAMAN, bukan lewat service. Servicenya sudah benar
     * sejak awal — yang salah adalah angka yang sampai ke layar.
     */
    public function test_usulan_nomor_di_wizard_sama_dengan_yang_dipakai_server(): void
    {
        $admin = User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopDokumenPage'));
        if (! $admin) {
            $this->markTestSkipped('Belum ada user dengan akses menuSopDokumenPage.');
        }

        [$idDok] = $this->versiBatal();

        $versi = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
        $r = $this->actingAs($admin)->get(
            '/sop/admin/dokumen/'.Hashids::connection('custom')->encode($idDok).'/versi',
            ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $versi]
        )->assertStatus(200);

        $usulan = $r->json('props.infoVersi.Next_Versi_Ke');

        /* Versi satu-satunya sudah dibatalkan, jadi nomornya dilepas: usulan
           berikutnya kembali ke 0, bukan 1. */
        $this->assertSame(0, $usulan,
            'Muatan wizard mengusulkan nomor yang berbeda dari yang dihitung server. '
            .'Layar mengirimkannya balik sebagai nomor manual, sehingga penomoran '
            .'otomatis dilewati dan nomor resmi melompat.');

        $this->assertSame(
            \App\Services\LMS\SOP\Support\SopNomorVersi::berikutnya($idDok),
            $usulan,
            'Usulan di muatan dan penulis tunggalnya menjawab berbeda.');
    }

    /**
     * ALUR PENUH: nomor yang diusulkan wizard HARUS diterima endpoint-nya.
     *
     * PINTU KELIMA, dan yang ini menggigit paling keras karena menolak di muka:
     * `storeVersi` memanggil `nomorVersiTerpakai()` untuk menangkap bentrok
     * sebelum menulis — maksudnya baik, supaya pesannya menyebut angkanya
     * alih-alih memuntahkan galat 2601. Tapi ia menghitung versi yang DIBATALKAN
     * sebagai pemakai, sehingga admin ditolak "Versi 01 sudah ada" tepat pada
     * nomor yang baru saja dilepas pembatalan — dan yang diusulkan sendiri oleh
     * muatan wizard satu layar sebelumnya.
     *
     * DIUJI SEBAGAI ALUR, BUKAN SEBAGAI METODE. Menguji nomorVersiTerpakai()
     * sendirian tak akan menangkapnya: yang salah adalah dua tempat yang
     * menjawab pertanyaan sama dengan rumus berbeda, dan itu hanya terlihat
     * saat keduanya dilewati berurutan.
     */
    public function test_nomor_usulan_wizard_diterima_endpoint_pembuatan_versi(): void
    {
        $admin = User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopDokumenPage'));
        if (! $admin) {
            $this->markTestSkipped('Belum ada user dengan akses menuSopDokumenPage.');
        }

        [$idDok] = $this->versiBatal();
        $token = Hashids::connection('custom')->encode($idDok);

        /* Persis angka yang dikirim muatan wizard ke layar. */
        $usulan = \App\Services\LMS\SOP\Support\SopNomorVersi::berikutnya($idDok);

        $this->assertFalse($this->svc()->nomorVersiTerpakai($idDok, $usulan),
            'Nomor yang diusulkan wizard dianggap sudah terpakai. Admin akan ditolak '
            .'pada angka yang disodorkan sistem itu sendiri.');

        $this->actingAs($admin)
            ->postJson("/api/v1/sop/admin/dokumen/{$token}/versi", [
                'Versi_Ke' => $usulan,
                'Ringkasan_Perubahan' => 'Versi pengganti sesudah pembatalan.',
            ])
            ->assertSuccessful();

        $this->assertSame($usulan,
            (int) DB::table('N_SOP_Item_Versi')->where('Id_SOP_Dokumen', $idDok)
                ->where('Flag_Aktif', SopStatus::AKTIF)->max('Versi_Ke'),
            'Versi lahir dengan nomor selain yang diusulkan.');
    }

    /**
     * INVARIAN YANG MENOPANG INDEKS TERFILTER.
     *
     * DDL-2026-062 menyaring dengan Flag_Aktif = 'Y' karena hanya CANCEL yang
     * mematikan versinya. Predikat indeks tak bisa menengok tabel master, jadi
     * ketepatan itu tak bisa dijaga basis data — uji ini penggantinya.
     *
     * Menambah tahap kedua ber-Nonaktifkan_Versi='Y' akan membuat nomor versi
     * ikut dilepas oleh tahap itu tanpa ada yang memutuskannya. Merah di sini
     * datang lebih dulu daripada nomor yang bocor diam-diam.
     */
    public function test_hanya_cancel_yang_menonaktifkan_versi(): void
    {
        $mematikan = DB::table('N_SOP_Master_Tahap_Versi')
            ->where('Nonaktifkan_Versi', SopStatus::AKTIF)
            ->pluck('Kode_Tahap')->sort()->values()->all();

        $this->assertSame(['CANCEL'], $mematikan,
            'Ada tahap lain yang mematikan versi. Indeks unik DDL-2026-062 menyaring dengan '
            .'Flag_Aktif, jadi tahap itu ikut MELEPAS nomor versi — tinjau apakah itu memang '
            .'yang dimaksud sebelum membiarkan uji ini hijau.');
    }
}
