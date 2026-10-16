<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopProgressReadService;
use App\Services\LMS\SOP\Support\SopAksesBerkas;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Item FORM: berkas apa pun boleh diunduh, dan yang berupa PDF juga boleh
 * dilihat — tanpa pembaca terlacak, karena FORM memang tak dilacak per halaman.
 *
 * Dua hal yang diikat paling keras di sini:
 *
 *  1. Commit 9227de9d menegakkan Mode_Akses di server untuk menutup kebocoran
 *     DOKUMEN VIEW_ONLY, tapi gerbangnya dipasang tanpa membedakan tipe item —
 *     seluruh item FORM (semuanya VIEW_ONLY, karena wizard tak pernah menanyakan
 *     kolom itu untuk FORM) ikut ditolak 403. Aturan unduh sekarang punya satu
 *     rumah, dan tes ini menjaga agar kedua sisinya tetap benar sekaligus.
 *
 *  2. "PDF" tak bisa disimpulkan dari data yang selama ini dikirim ke browser:
 *     Konten_Tipe bernilai 'document' baik untuk PDF maupun .xlsx, dan
 *     Konten_Nama adalah judul konten tanpa ekstensi. Tombol "Lihat" harus tahu
 *     sebelum ditekan, jadi mime asli wajib ikut turun dari server.
 */
class SopFormBerkasTest extends TestCase
{
    use DatabaseTransactions;

    private const MIME_PDF = 'application/pdf';

    private const MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * SELURUH nilai Mode_Akses yang boleh ada, bukan yang kebetulan ada.
     *
     * Daftarnya dikunci CK_N_SOP_Item_Akses di N_SOP_Paket_Item, dan
     * test_the_set_of_access_modes_is_still_the_one_this_file_assumes() di bawah
     * menjaga agar daftar ini tak diam-diam ketinggalan.
     */
    private const MODE_AKSES = [null, 'VIEW_ONLY', 'VIEW_UNDUH'];

    /* ── aturan unduh ───────────────────────────────────────────────── */

    /**
     * Contoh pengisian non-PDF (warisan) tetap boleh diunduh, apa pun izinnya.
     *
     * Diuji atas seluruh domain Mode_Akses yang skema izinkan, bukan atas baris
     * yang kebetulan ada: commit 9227de9d pernah menolak 403 SELURUH item FORM,
     * dan berkas .xlsx/.docx tak punya jalan lain untuk sampai ke karyawan —
     * pembaca aman hanya bisa menampilkan PDF.
     */
    public function test_non_pdf_form_samples_may_be_downloaded_whatever_their_access_mode(): void
    {
        foreach (self::MODE_AKSES as $mode) {
            foreach ([[self::MIME_XLSX, null], [null, 'lms/konten/8/abc-export.xlsx'], [null, null]] as [$mime, $path]) {
                $this->assertTrue(
                    SopAksesBerkas::bolehUnduh($mode, 'FORM', $mime, $path),
                    'Contoh pengisian FORM non-PDF ber-Mode_Akses '.($mode ?? 'NULL').' ditolak mengunduh; '
                    .'pembaca aman tak bisa menampilkannya, jadi berkasnya tak bisa dipakai sama sekali.',
                );
            }
        }
    }

    /**
     * Contoh pengisian PDF mengikuti izin yang admin pilih per paket
     * (keputusan user 10 Okt 2026) — persis seperti DOKUMEN.
     */
    public function test_a_pdf_form_sample_follows_the_access_mode_chosen_for_the_package(): void
    {
        foreach ([[self::MIME_PDF, null], [null, 'lms/konten/6/abc-contoh.pdf']] as [$mime, $path]) {
            $this->assertTrue(SopAksesBerkas::bolehUnduh('VIEW_UNDUH', 'FORM', $mime, $path));
            $this->assertFalse(SopAksesBerkas::bolehUnduh('VIEW_ONLY', 'FORM', $mime, $path),
                'Contoh pengisian PDF ber-VIEW_ONLY masih boleh diunduh — izin paketnya diabaikan.');
            $this->assertFalse(SopAksesBerkas::bolehUnduh(null, 'FORM', $mime, $path));
        }
    }

    /**
     * Penjaga atas daftar di atas.
     *
     * Tanpa ini, nilai Mode_Akses keempat yang kelak ditambahkan lewat DDL akan
     * lolos tanpa pernah diuji, dan uji di atas tetap hijau sambil memeriksa
     * domain yang sudah bukan domainnya lagi.
     */
    public function test_the_set_of_access_modes_is_still_the_one_this_file_assumes(): void
    {
        $ck = DB::selectOne("
            SELECT definition FROM sys.check_constraints
            WHERE parent_object_id = OBJECT_ID('N_SOP_Paket_Item') AND name = 'CK_N_SOP_Item_Akses'");

        $this->assertNotNull($ck, 'CK_N_SOP_Item_Akses hilang — domain Mode_Akses tak lagi dijaga skema.');

        foreach (array_filter(self::MODE_AKSES) as $mode) {
            $this->assertStringContainsString("'{$mode}'", $ck->definition,
                "Mode_Akses '{$mode}' tak lagi disebut CK_N_SOP_Item_Akses.");
        }

        /* Hitung berapa nilai yang disebut CHECK-nya; lebih banyak daripada yang
           didaftar di sini berarti ada nilai baru yang belum pernah diuji. */
        $this->assertSame(
            count(array_filter(self::MODE_AKSES)),
            preg_match_all("/'[A-Z_]+'/", $ck->definition),
            'CK_N_SOP_Item_Akses menyebut nilai yang tak ada di MODE_AKSES — '
            .'tambahkan ke daftar itu supaya aturan unduhnya ikut diuji.',
        );
    }

    public function test_view_only_documents_still_refuse_to_be_downloaded(): void
    {
        // Justru alasan gerbang itu ada. Melonggarkannya untuk FORM tak boleh
        // ikut melonggarkannya untuk DOKUMEN.
        $this->assertFalse(SopAksesBerkas::bolehUnduh('VIEW_ONLY', 'DOKUMEN'));
        $this->assertFalse(SopAksesBerkas::bolehUnduh(null, 'DOKUMEN'));
    }

    public function test_documents_marked_downloadable_are_still_downloadable(): void
    {
        $this->assertTrue(SopAksesBerkas::bolehUnduh('VIEW_UNDUH', 'DOKUMEN'));
    }

    /**
     * Endpoint sungguhan, bukan aturannya saja.
     *
     * Menguji SopAksesBerkas terpisah membuktikan aturannya benar, bukan bahwa
     * controller memanggilnya. Justru di sambungan itulah cacat sebelumnya
     * hidup: aturannya ada di kepala penulisnya, penegakannya di tempat lain.
     */
    public function test_the_endpoint_follows_the_package_access_mode_for_a_pdf_sample(): void
    {
        $baris = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Item_Versi as vs', fn ($j) => \App\Services\LMS\SOP\Support\SopVersiItem::tautkan($j, 'i', 'vs'))
            ->join('N_LMS_Master_Konten_Berkas as b', 'b.Id_Master_Konten', '=', 'vs.Id_Master_Konten')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan', '=', 'pj.Id_SOP_Penjadwalan')
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'd.Kode_Karyawan')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->where('i.Tipe_Item', 'FORM')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('vs.Status', SopStatus::PAKET_PUBLISHED)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where(fn ($q) => $q->where('b.Type_File', 'like', '%pdf%')->orWhere('b.File_Path', 'like', '%.pdf'))
            ->first(['u.Id_Users as user_id', 'i.Id_SOP_Paket', 'i.Id_SOP_Paket_Item', 'vs.Id_SOP_Item_Versi']);

        if (! $baris) {
            $this->markTestSkipped('Tak ada user login yang ditugasi item FORM berkas PDF.');
        }

        /*
         * APP_URL di repo ini ditulis tanpa skema ("n8n.ajt.my.id") karena
         * CloudTaskService merangkai sendiri "https://" di depannya. Efek
         * sampingannya: url() memperlakukannya sebagai segmen path, sehingga
         * SETIAP permintaan uji meleset dari route-nya dan berbalas 404 —
         * seolah endpointnya tak ada. Dipatok di sini, bukan di .env, supaya
         * asumsi CloudTasks tak ikut berubah.
         */
        // config() saja tak cukup: UrlGenerator sudah terlanjur dibuat dengan
        // root yang lama, jadi rootnya yang harus dipaksa.
        \Illuminate\Support\Facades\URL::forceRootUrl('http://localhost');

        $enc = fn ($id) => \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode($id);
        $url = "/api/v1/sop/{$enc($baris->Id_SOP_Paket)}/versi/{$enc($baris->Id_SOP_Item_Versi)}/konten";
        $user = \App\Models\ESS\Employee\Models\User::find($baris->user_id);
        $setel = fn (string $mode) => DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket_Item', $baris->Id_SOP_Paket_Item)->update(['Mode_Akses' => $mode]);

        // Hanya lihat: unduhan ditolak, dan URL mentah tak ikut terkirim saat dibuka.
        $setel('VIEW_ONLY');
        $this->actingAs($user)->getJson($url.'?unduh=1')->assertStatus(403);

        $lihat = $this->actingAs($user)->getJson($url);
        $lihat->assertOk();
        $this->assertNotEmpty($lihat->json('result.berkas_id'), 'Contoh pengisian PDF tak membawa berkas_id untuk pembaca aman.');
        $this->assertNull($lihat->json('result.url'),
            'URL mentah contoh pengisian ikut terkirim — "hanya lihat" bisa dilewati dengan membukanya di tab baru.');

        // Boleh unduh: lolos, dan dipaksa terunduh. Bukan sekadar "bukan 403" —
        // 500 pun lolos syarat selonggar itu.
        $setel('VIEW_UNDUH');
        $res = $this->actingAs($user)->getJson($url.'?unduh=1');
        $res->assertOk();
        $this->assertNotEmpty($res->json('result.url'), 'Endpoint lolos gerbang tapi tak memberi URL berkas.');
        $this->assertStringContainsString('attachment', $res->json('result.url'), 'URL tak dipaksa terunduh.');
    }

    /* ── aturan pratinjau ───────────────────────────────────────────── */

    public function test_only_pdf_form_files_are_previewable(): void
    {
        $this->assertTrue(SopAksesBerkas::bisaPratinjau('FORM', self::MIME_PDF));
        $this->assertFalse(SopAksesBerkas::bisaPratinjau('FORM', self::MIME_XLSX));
        $this->assertFalse(SopAksesBerkas::bisaPratinjau('FORM', null));
    }

    public function test_documents_are_not_previewed_through_the_form_path(): void
    {
        // DOKUMEN punya jalurnya sendiri — pembaca aman dengan tanda air dan
        // pelacakan halaman. Menandainya "bisa pratinjau" di sini akan membuka
        // jalan pintas yang melewati seluruh rantai itu.
        $this->assertFalse(SopAksesBerkas::bisaPratinjau('DOKUMEN', self::MIME_PDF));
        $this->assertFalse(SopAksesBerkas::bisaPratinjau('VIDEO', self::MIME_PDF));
    }

    public function test_pdf_is_recognised_from_the_file_path_when_mime_is_missing(): void
    {
        // Type_File boleh kosong pada berkas lama; ekstensi tetap memberi tahu.
        $this->assertTrue(SopAksesBerkas::bisaPratinjau('FORM', null, 'lms/konten/6/abc-sk-hol.pdf'));
        $this->assertFalse(SopAksesBerkas::bisaPratinjau('FORM', null, 'lms/konten/8/abc-export.xlsx'));
    }

    public function test_the_extension_badge_reads_the_same_whichever_column_supplies_it(): void
    {
        // Kolom Ext disimpan huruf kecil, jalur cadangan membaca dari path.
        // Lencana yang berganti bentuk antar item terbaca seperti dua hal berbeda.
        $this->assertSame('XLSX', SopAksesBerkas::ekstensi('xlsx', null));
        $this->assertSame('XLSX', SopAksesBerkas::ekstensi(null, 'lms/konten/8/abc-export.xlsx'));
        $this->assertSame('PDF', SopAksesBerkas::ekstensi('  ', 'lms/konten/6/abc.pdf'));
        $this->assertNull(SopAksesBerkas::ekstensi(null, null));
    }

    /* ── mime benar-benar sampai ke klien ───────────────────────────── */

    /**
     * SEMUA penugasan yang memuat item FORM, satu per paket.
     *
     * Sengaja bukan yang pertama saja. detailBundle() punya dua jalur yang sama
     * sekali berbeda — snapshot rilis (JSON, tak menyimpan mime sama sekali) dan
     * query langsung — dan paket mana memakai yang mana tak kelihatan dari luar.
     * Mengambil satu baris berarti separuh implementasinya lolos tanpa diuji.
     *
     * @return array<int,array{0:string,1:int}>
     */
    private function penugasanBerFormulir(): array
    {
        $rows = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('i.Tipe_Item', 'FORM')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->groupBy('pj.Id_SOP_Paket')
            ->selectRaw('pj.Id_SOP_Paket, MIN(d.Kode_Karyawan) as Kode_Karyawan')
            ->get();

        if ($rows->isEmpty()) {
            $this->markTestSkipped('Tak ada penugasan yang memuat item FORM.');
        }

        return $rows->map(fn ($r) => [$r->Kode_Karyawan, (int) $r->Id_SOP_Paket])->all();
    }

    /** Paket ini dilayani snapshot rilis atau query langsung? Untuk pesan gagal. */
    private function jalur(int $idPaket): string
    {
        return (new \App\Services\LMS\SOP\SopPaketVersionService)->releasedSnapshot($idPaket)
            ? 'snapshot rilis' : 'query langsung';
    }

    private function itemFormDari(array $bundle): array
    {
        return array_values(array_filter($bundle['items'] ?? [], fn ($it) => ($it['Tipe_Item'] ?? null) === 'FORM'));
    }

    public function test_every_bundle_path_reports_the_file_type_the_database_actually_holds(): void
    {
        $diperiksa = 0;

        foreach ($this->penugasanBerFormulir() as [$kode, $idPaket]) {
            $jalur = $this->jalur($idPaket);

            // Kebenarannya diambil langsung dari tabel berkas, bukan dari bundle —
            // membandingkan bundle dengan dirinya sendiri tak membuktikan apa pun.
            $mimeAsli = DB::table('N_SOP_Paket_Item as i')
                ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
                ->join('N_LMS_Master_Konten_Berkas as b', 'b.Id_Master_Konten', '=', 'v.Id_Master_Konten')
                ->where('i.Id_SOP_Paket', $idPaket)
                ->where('i.Tipe_Item', 'FORM')
                ->where('i.Flag_Aktif', SopStatus::AKTIF)
                ->pluck('b.Type_File', 'i.Judul_Item');

            // Izin dari tabel hidup — sumber yang sama dengan gerbang apiVersiKonten.
            $modeHidup = DB::table('N_SOP_Paket_Item')
                ->where('Id_SOP_Paket', $idPaket)->where('Tipe_Item', 'FORM')->where('Flag_Aktif', SopStatus::AKTIF)
                ->pluck('Mode_Akses', 'Judul_Item');

            $bundle = app(SopProgressReadService::class)->detailBundle($kode, $idPaket);
            $items = $this->itemFormDari($bundle ?? []);

            $this->assertNotEmpty($items, "Paket {$idPaket} ({$jalur}) tak memuat satu pun item FORM.");

            foreach ($items as $it) {
                $harusnya = $mimeAsli[$it['Judul_Item']] ?? null;

                $this->assertSame(
                    $harusnya,
                    $it['Konten_Mime'],
                    "Paket {$idPaket} ({$jalur}) salah melaporkan mime '{$it['Judul_Item']}'.",
                );
                $this->assertSame(
                    SopAksesBerkas::bisaPratinjau('FORM', $harusnya),
                    $it['Bisa_Pratinjau'],
                    "Paket {$idPaket} ({$jalur}) salah menandai pratinjau '{$it['Judul_Item']}'.",
                );
                /* Snapshot rilis menyimpan Mode_Akses saat rilis; yang ditegakkan
                   gerbang adalah tabel hidup. Layar yang membaca snapshot akan
                   menyembunyikan tombol yang boleh, atau menawarkan yang ditolak. */
                $this->assertSame(
                    SopAksesBerkas::bolehUnduh($modeHidup[$it['Judul_Item']] ?? null, 'FORM', $harusnya),
                    $it['Boleh_Unduh'],
                    "Paket {$idPaket} ({$jalur}) salah melaporkan izin unduh '{$it['Judul_Item']}'.",
                );
                $diperiksa++;
            }
        }

        $this->assertGreaterThan(0, $diperiksa, 'Tak ada item FORM yang benar-benar diperiksa.');
    }

    /**
     * Izin di layar = izin yang ditegakkan gerbang, walau snapshot rilis bilang lain.
     *
     * DML-2026-165 mengubah Mode_Akses item FORM langsung di tabel, tanpa rilis
     * ulang paket. Snapshot_JSON-nya masih menyimpan VIEW_ONLY. Kalau bundle
     * membaca snapshot, karyawan melihat contoh pengisian tanpa tombol unduh
     * padahal gerbangnya mengizinkan — persis yang DML itu hendak cegah.
     */
    public function test_the_bundle_reads_the_access_mode_the_gate_enforces(): void
    {
        $diperiksa = 0;

        foreach ($this->penugasanBerFormulir() as [$kode, $idPaket]) {
            $snapshot = (new \App\Services\LMS\SOP\SopPaketVersionService)->releasedSnapshot($idPaket);
            if (! $snapshot) {
                continue;
            }

            foreach (collect($snapshot['items'])->where('Tipe_Item', 'FORM') as $s) {
                $kebalikan = ($s['Mode_Akses'] ?? null) === 'VIEW_UNDUH' ? 'VIEW_ONLY' : 'VIEW_UNDUH';
                DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $s['Id_SOP_Paket_Item'])
                    ->update(['Mode_Akses' => $kebalikan]);

                // Dicocokkan lewat id: judul item ditimpa judul dokumen di bundle.
                $hash = \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode((int) $s['Id_SOP_Paket_Item']);
                $it = collect($this->itemFormDari(app(SopProgressReadService::class)->detailBundle($kode, $idPaket) ?? []))
                    ->firstWhere('Id_SOP_Paket_Item', $hash);

                $this->assertNotNull($it, "Item FORM #{$s['Id_SOP_Paket_Item']} hilang dari bundle paket {$idPaket}.");
                $this->assertSame($kebalikan, $it['Mode_Akses'],
                    "Paket {$idPaket} masih membaca Mode_Akses dari snapshot rilis, bukan dari tabel yang ditegakkan gerbang.");
                $diperiksa++;
            }
        }

        if ($diperiksa === 0) {
            $this->markTestSkipped('Tak ada item FORM tertugas di paket bersnapshot rilis.');
        }
    }

    public function test_both_a_previewable_and_a_download_only_form_are_covered(): void
    {
        // Tanpa penjaga ini, tes di atas bisa hijau sempurna sambil hanya pernah
        // melihat satu sisi keputusannya.
        $terlihat = [];

        foreach ($this->penugasanBerFormulir() as [$kode, $idPaket]) {
            foreach ($this->itemFormDari(app(SopProgressReadService::class)->detailBundle($kode, $idPaket) ?? []) as $it) {
                $terlihat[$it['Bisa_Pratinjau'] ? 'pratinjau' : 'unduh_saja'] = true;
            }
        }

        /* Penjaga cakupan, bukan penjaga cacat. Kalau datanya memang tidak
           memuat kedua sisi, yang dilaporkannya adalah lubang FIXTURE, bukan
           kode yang rusak — dan kegagalan yang terbaca seperti cacat justru
           membuat berkas ini berhenti dipercaya. Terukur 11 September 2026:
           Web_HRIS punya SATU item FORM aktif, jadi kedua sisi mustahil terlihat
           sekaligus di sini.

           Tetap dilewati dengan sebab yang menyebut sisi mana yang hilang,
           supaya begitu datanya lengkap penjaganya hidup kembali dengan
           sendirinya — bukan dihapus dan dilupakan. */
        $hilang = array_values(array_diff(['pratinjau', 'unduh_saja'], array_keys($terlihat)));

        if (! empty($terlihat) && ! empty($hilang)) {
            $this->markTestSkipped(
                'Data dev belum memuat kedua sisi keputusan pratinjau; yang belum ada: '
                .implode(', ', $hilang).'. Butuh sedikitnya satu formulir PDF DAN satu non-PDF '
                .'yang tertugas, sementara hari ini item FORM aktifnya hanya satu.',
            );
        }

        $this->assertArrayHasKey('pratinjau', $terlihat, 'Tak ada formulir PDF yang teruji.');
        $this->assertArrayHasKey('unduh_saja', $terlihat, 'Tak ada formulir non-PDF yang teruji.');
    }

    public function test_version_history_of_a_form_item_carries_its_file_type(): void
    {
        $diperiksa = 0;

        foreach ($this->penugasanBerFormulir() as [$kode, $idPaket]) {
            $idItem = DB::table('N_SOP_Paket_Item')
                ->where('Id_SOP_Paket', $idPaket)
                ->where('Tipe_Item', 'FORM')
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->value('Id_SOP_Paket_Item');

            $versi = app(SopProgressReadService::class)->itemVersiHistory($kode, $idPaket, (int) $idItem);

            /* Jenis berkas tiap versi tetap turun, dan pratinjau mengikutinya —
               TETAPI hanya untuk versi yang berlaku. Sejak keputusan produk
               12 Agu 2026 (abae0ce4) isi versi lama tak dibagikan lagi: versi
               PDF yang sudah digantikan Bisa_Dibuka=false, maka juga tak bisa
               dipratinjau. Ekspektasi lama (bisaPratinjau saja) baru terbukti
               keliru begitu data dev punya FORM PDF dengan versi lama
               (v02 terbit 10 Okt 2026). */
            foreach ($versi as $v) {
                $this->assertArrayHasKey('Konten_Mime', $v);
                $this->assertSame(
                    $v['Bisa_Dibuka'] && SopAksesBerkas::bisaPratinjau('FORM', $v['Konten_Mime'], $v['Konten_Ext']),
                    $v['Bisa_Pratinjau'],
                    "Versi {$v['Versi_Ke']} salah menandai pratinjau.",
                );
                $diperiksa++;
            }
        }

        $this->assertGreaterThan(0, $diperiksa, 'Tak ada versi FORM yang benar-benar diperiksa.');
    }
}
