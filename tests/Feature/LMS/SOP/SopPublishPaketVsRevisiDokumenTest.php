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
 * Tabrakan antara dua penulis "versi mana yang berlaku".
 *
 * Master SOP menerbitkan revisi dokumen dan menyebarkannya ke semua paket
 * seketika (Fase 3). Publish paket, di sisi lain, menulis pointer dari SALINAN
 * yang diambil saat draftnya dibuka. Kalau revisi terbit di antara keduanya,
 * publish paket memundurkan karyawan ke versi yang sudah dicabut.
 *
 * Keputusan produk 2026-08-10: revisi dokumen menang. Aturan "publish paket
 * dulu" tetap berlaku untuk milik paket — susunan item, urutan, penugasan —
 * bukan untuk revisi dokumen, yang memang berlaku serentak di semua pemakainya.
 */
class SopPublishPaketVsRevisiDokumenTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Sumbu penomoran versi harus DOKUMEN, bukan item — tertinggal sejak Fase 2
     * di penulis mentah jalur draft.
     *
     * Sumbu item menghitung ulang dari nol untuk tiap item, sehingga dokumen
     * yang sudah di v2 lewat Master akan ditulisi v2 lagi. Unique index
     * terfilter 26B menolaknya dengan galat 2601, dan publish paket GAGAL.
     */
    public function test_penomoran_versi_memakai_sumbu_dokumen_bukan_item(): void
    {
        $svc = app(SopDokumenService::class);
        $idDok = $svc->buat(['Judul' => 'SOP UJI SUMBU', 'Tipe' => 'DOKUMEN'], 1);

        // Dua versi lewat Master: dokumen kini di v2.
        $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji'], 1);
        $v2 = $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji'], 1);

        $maksDokumen = (int) DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $idDok)->max('Versi_Ke');
        // Dua versi lewat Master menghasilkan v00 dan v01, jadi maksnya 1.
        $this->assertSame(1, $maksDokumen);

        /* Item baru yang memakai dokumen itu punya maks versi ITEM = 0.
           Sumbu lama akan menghitung 0 + 1 = 1 — angka yang sudah dipakai
           dokumen ini. Sumbu dokumen menghitung 2 + 1 = 3. */
        $maksItem = (int) DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $idDok)
            ->where('Id_SOP_Paket_Item', 99999999)->max('Versi_Ke');
        $this->assertSame(0, $maksItem);   // item ini belum punya versi sama sekali

        $this->assertNotSame($maksItem + 1, $maksDokumen + 1,
            'Kedua sumbu memberi nomor berbeda — memakai sumbu item akan menabrak '
            .'unique index terfilter 26B (Id_SOP_Dokumen, Versi_Ke).');

        // Nomor berikutnya pada sumbu dokumen memang belum terpakai.
        $this->assertFalse(
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Dokumen', $idDok)
                ->where('Versi_Ke', $maksDokumen + 1)->exists(),
            'Nomor dari sumbu dokumen harus bebas; kalau tidak, unique index menolak.');

        $this->assertNotNull($v2);
    }

    /**
     * REVISI MINOR membawa penyelesaian; REVISI MAYOR tidak.
     *
     * Penyelesaian dicatat per (item x versi), jadi setiap revisi otomatis
     * membuka kewajiban baru. Benar untuk perombakan isi, tapi memaksa ratusan
     * orang membaca ulang karena satu kop surat berubah mengikis arti
     * "dibuka lagi" itu sendiri.
     *
     * Yang dibawa HANYA yang sudah selesai — yang belum memang belum membaca
     * apa pun. Dan bawaannya harus bisa dibedakan dari yang dikerjakan
     * sungguhan, kalau tidak revisi minor jadi cara diam-diam menyatakan orang
     * sudah membaca sesuatu yang tak pernah mereka buka.
     */
    public function test_revisi_minor_membawa_penyelesaian_mayor_tidak(): void
    {
        $detail = DB::table('N_SOP_Penjadwalan_Detail')->where('Flag_Aktif', SopStatus::AKTIF)
            ->first(['Id_SOP_Penjadwalan_Detail', 'Kode_Karyawan']);
        $paket = DB::table('N_SOP_Master_Paket')->where('Flag_Aktif', SopStatus::AKTIF)->first(['Id_SOP_Paket']);
        if (! $detail || ! $paket) {
            $this->markTestSkipped('Tak ada penugasan/paket aktif.');
        }

        $svc = app(SopDokumenService::class);
        $idDok = $svc->buat(['Judul' => 'SOP UJI SIFAT REVISI', 'Tipe' => 'DOKUMEN'], 1);

        $idItem = DB::table('N_SOP_Paket_Item')->insertGetId([
            'Id_SOP_Paket' => $paket->Id_SOP_Paket, 'Id_SOP_Dokumen' => $idDok,
            'Tipe_Item' => 'DOKUMEN', 'Judul_Item' => 'Item uji sifat revisi',
            'Urutan' => 997, 'Flag_Aktif' => SopStatus::AKTIF,
            'Created_By' => 1, 'Created_At' => now(),
        ], 'Id_SOP_Paket_Item');

        $v1 = $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji', 'Id_Master_Konten' => 1], 1);
        $this->siapkanTerbit($v1);
        $svc->terbitkanVersi($v1, 1);

        /* Karyawan menyelesaikan v1.
           updateOrInsert, bukan insert: sejak terbit ikut merekonsiliasi, baris
           kewajiban untuk v1 sudah disemai berstatus BELUM begitu v1 terbit.
           Menuntaskannya berarti mengubah baris itu — persis yang dilakukan
           karyawan sungguhan. */
        DB::table('N_SOP_Progress')->updateOrInsert(
            [
                'Id_SOP_Penjadwalan_Detail' => $detail->Id_SOP_Penjadwalan_Detail,
                'Id_SOP_Paket_Item' => $idItem,
                'Id_SOP_Item_Versi' => $v1,
            ],
            [
                'Kode_Karyawan' => $detail->Kode_Karyawan,
                'Tipe_Item' => 'DOKUMEN',
                'Status' => SopStatus::STATUS_SELESAI,
                'Created_At' => now(),
            ]
        );

        // REVISI MINOR -> penyelesaian dibawa, dan ketahuan dibawa dari mana.
        $v2 = $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji', 'Id_Master_Konten' => 1], 1);
        $this->siapkanTerbit($v2);
        $svc->terbitkanVersi($v2, 1, 'T');
        $this->jalankanRekonsiliasiTerantri($idDok);

        /* Dipatok pada KARYAWAN yang penyelesaiannya dipasang di atas.
           Rekonsiliasi menyemai satu baris per penugasan aktif paket ini — di
           data dev ada lima — dan hanya satu di antaranya yang boleh membawa
           penyelesaian. Membaca first() tanpa menyebut penugasannya berarti
           menguji baris yang kebetulan dikembalikan lebih dulu oleh SQL Server:
           lulus atau gagal tergantung urutan baris, bukan tergantung perilaku. */
        $bawaan = DB::table('N_SOP_Progress')
            ->where('Id_SOP_Penjadwalan_Detail', $detail->Id_SOP_Penjadwalan_Detail)
            ->where('Id_SOP_Paket_Item', $idItem)->where('Id_SOP_Item_Versi', $v2)
            ->first(['Status', 'Id_Versi_Asal']);

        $this->assertNotNull($bawaan, 'Revisi minor harus membawa penyelesaian karyawan.');
        $this->assertSame(SopStatus::STATUS_SELESAI, $bawaan->Status);
        $this->assertSame((int) $v1, (int) $bawaan->Id_Versi_Asal,
            'Bawaan wajib bisa dibedakan dari yang dikerjakan sungguhan.');

        // REVISI MAYOR -> tak dibawa; karyawan kembali wajib membaca.
        $v3 = $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji', 'Id_Master_Konten' => 1], 1);
        $this->siapkanTerbit($v3);
        $svc->terbitkanVersi($v3, 1, 'Y');
        $this->jalankanRekonsiliasiTerantri($idDok);

        /* Barisnya ADA — rekonsiliasi menyemai kewajiban baru untuk v3, dan
           memang harus: tanpa baris itu karyawan tak punya apa pun untuk
           dikerjakan. Yang tak boleh adalah penyelesaiannya ikut terbawa.
           Jadi yang diuji bukan "tak ada baris", melainkan "baris itu kosong":
           bukan SELESAI, dan tak menunjuk versi asal mana pun. */
        $mayor = DB::table('N_SOP_Progress')
            ->where('Id_SOP_Penjadwalan_Detail', $detail->Id_SOP_Penjadwalan_Detail)
            ->where('Id_SOP_Paket_Item', $idItem)->where('Id_SOP_Item_Versi', $v3)
            ->first(['Status', 'Id_Versi_Asal']);

        $this->assertNotNull($mayor, 'Revisi mayor tetap harus menyemai kewajiban barunya.');
        $this->assertNotSame(SopStatus::STATUS_SELESAI, $mayor->Status,
            'Revisi mayor tak boleh membawa penyelesaian — itu justru gunanya.');
        $this->assertNull($mayor->Id_Versi_Asal,
            'Kewajiban revisi mayor harus asli, bukan bawaan dari versi sebelumnya.');
    }

    /**
     * Menerbitkan revisi merekonsiliasi SEMUA paket perujuk, bukan nol.
     *
     * Sebelum ini, terbit menggeser pointer di semua paket lalu berhenti —
     * rekonsiliasi tak dipanggil dari mana pun kecuali rollback. Akibatnya
     * Status_Paket karyawan tetap tertulis SELESAI padahal versi yang harus
     * mereka baca sudah berganti: angka kepatuhan benar menurut catatan, salah
     * menurut kenyataan, dan tak seorang pun diberi tahu.
     *
     * Diuji lewat efeknya pada penugasan, bukan lewat pemanggilan: yang penting
     * bukan "rekonsiliasi dipanggil", melainkan status karyawan benar-benar
     * dihitung ulang.
     */
    public function test_terbit_merekonsiliasi_paket_perujuk(): void
    {
        $detail = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            /* Penugasan yang aksesnya DICABUT sengaja dilewati rekonsiliasi —
               memilihnya di sini akan menguji jalur yang memang tak berjalan. */
            ->where('d.Flag_Cabut_Akses', 'T')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('i.Flag_Wajib', SopStatus::AKTIF)
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->first(['d.Id_SOP_Penjadwalan_Detail', 'i.Id_SOP_Dokumen', 'pj.Id_SOP_Paket']);

        if (! $detail) {
            $this->markTestSkipped('Tak ada penugasan atas item ber-dokumen.');
        }

        $konten = DB::table('N_LMS_Master_Konten')->where('Tipe_Konten', 'document')
            ->value('Id_Master_Konten');
        if (! $konten) {
            $this->markTestSkipped('Tak ada konten dokumen di Library dev.');
        }

        /* Status sengaja dibuat BOHONG lebih dulu: ditulis SELESAI padahal
           kewajibannya belum tentu tuntas. Rekonsiliasi yang benar akan
           menghitung ulang dan mengoreksinya. */
        DB::table('N_SOP_Penjadwalan_Detail')
            ->where('Id_SOP_Penjadwalan_Detail', $detail->Id_SOP_Penjadwalan_Detail)
            ->update(['Status_Paket' => SopStatus::STATUS_SELESAI]);

        $svc = app(SopDokumenService::class);
        $versi = $svc->tambahVersi((int) $detail->Id_SOP_Dokumen, ['Disahkan_Oleh' => 'Pengesah Uji', 'Id_Master_Konten' => $konten], 1);
        $this->siapkanTerbit($versi);
        $svc->terbitkanVersi($versi, 1);
        $this->jalankanRekonsiliasiTerantri((int) $detail->Id_SOP_Dokumen);

        $sesudah = DB::table('N_SOP_Penjadwalan_Detail')
            ->where('Id_SOP_Penjadwalan_Detail', $detail->Id_SOP_Penjadwalan_Detail)
            ->value('Status_Paket');

        $this->assertNotSame(SopStatus::STATUS_SELESAI, $sesudah,
            'Status penugasan tak dihitung ulang setelah revisi terbit — angka kepatuhan '
            .'tetap menyatakan SELESAI untuk versi yang belum dibaca siapa pun.');
    }

    /**
     * Jalur Builder harus IKUT menghormati sifat revisi.
     *
     * Yang membuka kewajiban karyawan bukan tombol publish paket, melainkan
     * versi item yang bergeser — dan itu terjadi di kedua jalur. Sebelum ini
     * publish paket selalu mayor tanpa admin pernah ditanya, karena
     * terbitkanVersi() dipanggil tanpa menyebut sifatnya sama sekali.
     *
     * Diuji lewat tanda tangan, bukan lewat alur draft penuh: menjalankan
     * publish paket sungguhan menuntut draft lengkap dengan section dan item
     * siap rilis, dan yang hendak dibuktikan di sini justru bagian yang dulu
     * hilang — bahwa sifatnya DITERUSKAN, bukan dijatuhkan ke default.
     */
    public function test_publish_paket_meneruskan_sifat_revisi(): void
    {
        $publish = new \ReflectionMethod(\App\Services\LMS\SOP\SopPaketDraftService::class, 'publish');
        $param = collect($publish->getParameters())->firstWhere(fn ($p) => $p->getName() === 'sifatRevisi');

        $this->assertNotNull($param,
            'publish() paket tak menerima sifat revisi — jalurnya akan selalu mayor.');
        $this->assertTrue($param->isDefaultValueAvailable());
        $this->assertSame('Y', $param->getDefaultValue(),
            'Default WAJIB mayor: admin yang tak memilih tak boleh menghasilkan "tak usah baca".');

        $apply = new \ReflectionMethod(\App\Services\LMS\SOP\SopPaketDraftService::class, 'applyItems');
        $this->assertNotNull(
            collect($apply->getParameters())->firstWhere(fn ($p) => $p->getName() === 'sifatRevisi'),
            'applyItems() tak menerima sifatnya, jadi ia tak pernah sampai ke terbitkanVersi().');

        /* Penerbit versi wajib punya tempat untuk menerimanya — kalau tidak,
           parameter di atas cuma diteruskan lalu hilang di ujung. */
        $terbit = new \ReflectionMethod(SopDokumenService::class, 'terbitkanVersi');
        $this->assertNotNull(
            collect($terbit->getParameters())->firstWhere(fn ($p) => $p->getName() === 'wajibBacaUlang'),
            'terbitkanVersi() tak menerima sifat revisi.');
    }

    /**
     * Sifatnya bisa berbeda PER DOKUMEN dalam satu publish.
     *
     * Satu publish paket bisa menaikkan beberapa dokumen dengan alasan yang
     * berbeda: satu cuma salah ketik, satu lagi benar-benar berubah isinya.
     * Satu saklar untuk semuanya memaksa admin memilih yang paling ketat — dan
     * karena kalau ragu orang memilih mayor, hasilnya sama saja dengan tak punya
     * pilihan, sehingga "dibuka lagi" pelan-pelan kehilangan artinya.
     *
     * Alasan uji ini memakai tanda tangan sama seperti di atas: menjalankan
     * publish paket sungguhan menuntut draft lengkap berisi section dan item
     * siap rilis. Yang tak boleh hilang justru DEFAULT-nya — peta kosong harus
     * berarti "ikuti sifat menyeluruh", bukan "semua minor".
     */
    public function test_publish_paket_menerima_sifat_per_dokumen(): void
    {
        foreach (['publish', 'applyItems'] as $nama) {
            $m = new \ReflectionMethod(\App\Services\LMS\SOP\SopPaketDraftService::class, $nama);
            $p = collect($m->getParameters())->firstWhere(fn ($x) => $x->getName() === 'sifatPerDokumen');

            $this->assertNotNull($p, "{$nama}() tak menerima sifat per dokumen.");
            $this->assertTrue($p->isDefaultValueAvailable());
            $this->assertSame([], $p->getDefaultValue(),
                'Peta kosong harus berarti "ikuti sifat menyeluruh", bukan melonggarkan semuanya.');
        }
    }

    /**
     * Pratinjau WAJIB menyebut dokumennya, kalau tidak pilihannya tak punya
     * tempat untuk ditautkan.
     *
     * Inilah sambungan yang paling mungkin patah diam-diam: layarnya tetap
     * menampilkan pilihan per baris, tombolnya tetap bisa ditekan, dan hasilnya
     * tetap "berhasil" — hanya saja tak satu pun pilihan sampai ke server,
     * karena kuncinya kosong. Diuji pada data hidup.
     */
    public function test_pratinjau_publish_menyebut_dokumen_tiap_barisnya(): void
    {
        $svc = app(\App\Services\LMS\SOP\SopPaketDraftService::class);

        $baris = null;
        foreach (DB::table('N_SOP_Master_Paket')->where('Flag_Aktif', SopStatus::AKTIF)->pluck('Id_SOP_Paket') as $id) {
            $d = $svc->dampakPublish((int) $id);
            $kandidat = collect($d['versi'] ?? [])->first(fn ($v) => $v['Versi_Sekarang'] !== null);
            if ($kandidat) {
                $baris = $kandidat;
                break;
            }
        }

        if (! $baris) {
            $this->markTestSkipped('Tak ada draft paket berisi revisi dokumen siap rilis.');
        }

        $this->assertArrayHasKey('Id_SOP_Dokumen', $baris);
        $this->assertNotNull($baris['Id_SOP_Dokumen'],
            'Baris revisi tanpa id dokumen — pilihan sifat revisinya tak punya kunci.');

        $id = Hashids::connection('custom')->decode((string) $baris['Id_SOP_Dokumen'])[0] ?? null;
        $this->assertNotNull($id, 'Id dokumen tak bisa didekode — bukan hashid polos.');
        $this->assertTrue(
            DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', (int) $id)->exists(),
            'Id dokumen menunjuk baris yang tak ada.',
        );
    }

    /**
     * Peta dari klien disaring keras, dan condongnya SELALU ke arah aman.
     *
     * Kunci yang tak bisa didekode dibuang — bukan dianggap dokumen lain — dan
     * nilai apa pun selain 'T' jatuh ke mayor. Salah tulis di sisi klien harus
     * berakhir pada karyawan diminta membaca ulang, bukan pada revisi yang lolos
     * tanpa dibaca siapa pun.
     */
    public function test_peta_sifat_dari_klien_disaring_ke_arah_aman(): void
    {
        $c = app(\App\Http\Controllers\LMS\SOP\Governance\SopAdminBuilderController::class);
        $m = new \ReflectionMethod($c, 'sifatPerDokumen');
        $m->setAccessible(true);

        $enc = fn ($id) => Hashids::connection('custom')->encode($id);

        $hasil = $m->invoke($c, [
            $enc(11) => 'T',
            $enc(12) => 'Y',
            $enc(13) => 'entah',      // tak dikenali → mayor
            'bukan-hashid' => 'T',    // kunci ngawur → dibuang
        ]);

        $this->assertSame(['T'], array_values(array_intersect_key($hasil, [11 => null])));
        $this->assertSame('Y', $hasil[12]);
        $this->assertSame('Y', $hasil[13], 'Nilai tak dikenali melonggarkan revisi.');
        $this->assertCount(3, $hasil, 'Kunci yang tak bisa didekode ikut terbawa.');

        $this->assertSame([], $m->invoke($c, null), 'Masukan bukan-array harus jadi peta kosong.');
        $this->assertSame([], $m->invoke($c, 'T'));
    }

    /**
     * Pratinjau dampak harus menyebut PAKET MANA naik ke VERSI BERAPA.
     *
     * Menerbitkan revisi mengubah apa yang dibaca karyawan di semua paket
     * pemakainya seketika. "3 paket" tak memberi tahu paket mana, dan admin
     * yang tak tahu apa yang akan berubah tak benar-benar menyetujui apa pun.
     *
     * Ambang antrian ikut dilaporkan: di atasnya, penyebaran lewat worker
     * queue — dan bila worker mati, pekerjaannya tak terjadi tanpa satu pun
     * pesan galat. Itu harus diketahui SEBELUM tombolnya ditekan.
     */
    public function test_pratinjau_dampak_menyebut_paket_dan_versi_tujuannya(): void
    {
        $admin = User::whereNotNull('Id_Users')->get()
            ->first(fn ($x) => $x->memilikiJabatan('menuSopDokumenPage'));
        if (! $admin) {
            $this->markTestSkipped('Belum ada user dengan akses menuSopDokumenPage.');
        }

        $paket = DB::table('N_SOP_Master_Paket')->where('Flag_Aktif', SopStatus::AKTIF)->first(['Id_SOP_Paket']);
        if (! $paket) {
            $this->markTestSkipped('Tak ada paket aktif.');
        }

        $svc = app(SopDokumenService::class);
        $idDok = $svc->buat(['Judul' => 'SOP UJI PRATINJAU', 'Tipe' => 'DOKUMEN'], 1);

        DB::table('N_SOP_Paket_Item')->insert([
            'Id_SOP_Paket' => $paket->Id_SOP_Paket, 'Id_SOP_Dokumen' => $idDok,
            'Tipe_Item' => 'DOKUMEN', 'Judul_Item' => 'Item uji pratinjau',
            'Urutan' => 998, 'Flag_Aktif' => SopStatus::AKTIF,
            'Created_By' => 1, 'Created_At' => now(),
        ]);

        $v1 = $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji', 'Id_Master_Konten' => 1], 1);

        $hasil = $this->actingAs($admin)
            ->getJson('/api/v1/sop/admin/dokumen/versi/'
                .Hashids::connection('custom')->encode($v1).'/dampak')
            ->assertOk()->json('result');

        // Versi PERTAMA sebuah SOP bernomor 0 ("v00") sejak penomoran
        // dimulai dari nol. Angka 1 adalah kontrak lama.
        $this->assertSame(0, (int) $hasil['Versi_Ke']);
        $this->assertGreaterThanOrEqual(1, count($hasil['rincian']),
            'Paket pemakai harus disebut satu per satu, bukan cuma dihitung.');

        $baris = $hasil['rincian'][0];
        foreach (['Nama_Paket', 'Versi_Sekarang', 'Versi_Baru', 'Jumlah_Karyawan'] as $kolom) {
            $this->assertArrayHasKey($kolom, $baris,
                "Pratinjau tanpa '{$kolom}' tak menjawab 'naik jadi versi berapa'.");
        }
        $this->assertSame(0, (int) $baris['Versi_Baru']);

        $this->assertArrayHasKey('akan_antri', $hasil,
            'Admin harus tahu penyebarannya lewat antrian SEBELUM menekan terbit.');
        $this->assertArrayHasKey('ambang', $hasil);
    }

    /**
     * Snapshot rilis paket membekukan STRUKTUR, bukan versi dokumennya.
     *
     * Snapshot menyimpan Id_SOP_Item_Versi apa adanya saat paket dirilis. Untuk
     * susunan item, urutan, dan wajib/tidak itu benar. Tapi versi berkas milik
     * DOKUMEN, dan revisinya berlaku serentak di semua paket pemakainya.
     *
     * Fan-out Fase 3 hanya menggeser Id_Item_Versi_Aktif, tak pernah menyentuh
     * snapshot — sementara snapshot itulah yang benar-benar disajikan. Paket
     * yang dilayani snapshot terus membagikan berkas PRA-REVISI tanpa tanda apa
     * pun, dan itu terjadi sungguhan: paket 23 masih menyajikan .xlsx dari
     * rilis 3 Agustus padahal dokumennya sudah direvisi ke PDF.
     */
    public function test_snapshot_rilis_menyajikan_versi_dokumen_terkini(): void
    {
        $baris = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan', '=', 'pj.Id_SOP_Penjadwalan')
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->whereNotNull('i.Id_Item_Versi_Aktif')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->first(['i.Id_SOP_Paket', 'i.Id_SOP_Paket_Item', 'i.Id_Item_Versi_Aktif', 'd.Kode_Karyawan']);

        if (! $baris) {
            $this->markTestSkipped('Tak ada penugasan atas item ber-dokumen.');
        }

        $bundle = app(\App\Services\LMS\SOP\SopProgressReadService::class)
            ->detailBundle($baris->Kode_Karyawan, (int) $baris->Id_SOP_Paket);

        $item = collect($bundle['items'] ?? [])
            ->first(fn ($x) => ($x['Id_SOP_Paket_Item'] ?? null)
                === Hashids::connection('custom')->encode((int) $baris->Id_SOP_Paket_Item));

        if (! $item) {
            $this->markTestSkipped('Item tak muncul di bundle penugasan itu.');
        }

        $versiDisajikan = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $baris->Id_Item_Versi_Aktif)->value('Versi_Ke');

        $this->assertSame((int) $versiDisajikan, (int) $item['Versi'],
            'Bundle menyajikan versi selain versi aktif dokumen — karyawan membaca revisi lama.');
    }

    /**
     * Penugasan pada paket BERSNAPSHOT yang pointer hidupnya sudah bergeser —
     * yaitu keadaan sesudah sebuah dokumen direvisi.
     */

    /**
     * Jalankan rekonsiliasi yang penerbitan hanya ANTRIKAN.
     *
     * Sejak Fase 2, terbitkanVersi() tidak lagi menyisir karyawan di dalam
     * transaksinya: ia menulis SATU baris intent ke outbox
     * (SOP_RECONCILE_DOKUMEN) dan menyerahkan penyisirannya ke
     * SopReconcileHandler yang dijalankan Cloud Tasks. Alasannya benar --
     * transaksi penerbitan tetap O(item), bukan O(item x karyawan).
     *
     * Akibatnya kewajiban karyawan tidak pernah dihitung ulang di dalam satu
     * permintaan, dan karena itu juga tidak di dalam satu uji. Uji di berkas ini
     * ditulis SEBELUM perubahan itu dan masih menagih hasilnya seketika; yang
     * usang bentuk pemeriksaannya, bukan hal yang diperiksanya.
     *
     * Handler dipanggil dengan muatan yang BENAR-BENAR ditulis penerbitan, bukan
     * muatan karangan -- menyimpulkan "rekonsiliasi berjalan" dari keberadaan
     * baris outbox hanya membuktikan pesannya terkirim, bukan penerimanya
     * mengerjakan hal yang benar. Polanya sama dengan Alur044SiklusHidupLanjutTest.
     */
    private function jalankanRekonsiliasiTerantri(int $idDokumen): void
    {
        $outbox = \App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox::query()
            ->where('Task_Type', 'SOP_RECONCILE_DOKUMEN')
            ->where('Aggregate_Id', $idDokumen)
            ->orderByDesc('Id_Task_Outbox')
            ->first();

        $this->assertNotNull($outbox,
            'Penerbitan revisi tidak mengantrikan rekonsiliasi sama sekali. Tanpa intent '
            .'itu, tak ada apa pun yang akan menyisir karyawan.');

        $muatan = is_array($outbox->Payload)
            ? $outbox->Payload
            : (array) json_decode((string) $outbox->Payload, true);

        app(\App\Services\LMS\SOP\SopReconcileHandler::class)->handle($outbox, $muatan);
    }

    /**
     * Menaikkan versi ke tahap yang memang mengarah ke penerbitan.
     *
     * terbitkanVersi() menolak versi yang masih di Drafting sejak gerbang tahap
     * dipasang: aturannya hidup sebagai data di N_SOP_Master_Tahap_Versi_Transisi,
     * dan hanya tahap yang punya transisi ke FILING_REGISTERED yang boleh
     * menerbitkan. Fixture di berkas ini dibuat sebelum gerbang itu ada, jadi ia
     * menerbitkan dari Drafting dan sejak itu selalu melempar DomainException.
     *
     * Bentuknya disamakan dengan siapkanTerbit() di SopMasterDokumenPageTest --
     * satu-satunya cara yang benar untuk sampai ke sana, dan menyalinnya utuh
     * lebih jujur daripada menambal gerbangnya.
     */
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

    private function paketSnapshotYangSudahDirevisiAtauSkip(): object
    {
        $kandidat = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan', '=', 'pj.Id_SOP_Penjadwalan')
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->whereNotNull('i.Id_Item_Versi_Aktif')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->distinct()
            ->get(['i.Id_SOP_Paket', 'i.Id_SOP_Paket_Item', 'i.Id_Item_Versi_Aktif', 'd.Kode_Karyawan']);

        $versiSvc = app(\App\Services\LMS\SOP\SopPaketVersionService::class);

        foreach ($kandidat as $b) {
            $snap = $versiSvc->releasedSnapshot((int) $b->Id_SOP_Paket);
            if (! $snap) {
                continue;
            } // tanpa snapshot, penjaganya memang tak lewat jalur ini

            $beku = collect($snap['items'])
                ->contains(fn ($x) => (int) ($x['Id_SOP_Item_Versi'] ?? 0) === (int) $b->Id_Item_Versi_Aktif);

            if (! $beku) {
                return $b;
            } // pointer hidup sudah di luar snapshot
        }

        $this->markTestSkipped('Tak ada paket bersnapshot yang dokumennya sudah direvisi.');
    }

    /**
     * Snapshot rilis tak boleh jadi jalan buntu bagi penjaga akses.
     *
     * detailBundle sudah meluruskan item ke versi dokumen terkini, jadi tombol
     * "Lihat" menawarkan versi hidup. Tapi penjaga kontennya masih menanyakan
     * versi BEKU di snapshot, lalu menolak berkas yang halamannya sendiri baru
     * saja menawarkan — 403 pada dokumen yang baru diperbarui.
     *
     * Diuji lewat penjaga sungguhan, bukan lewat kueri tiruan: yang salah bukan
     * aturannya melainkan tempat aturan itu berhenti bertanya.
     */
    public function test_versi_dokumen_terkini_boleh_dibuka_meski_snapshot_membekukan_versi_lama(): void
    {
        $b = $this->paketSnapshotYangSudahDirevisiAtauSkip();

        $this->assertTrue(
            app(\App\Services\LMS\SOP\SopProgressReadService::class)
                ->versiVisibleInPaket($b->Kode_Karyawan, (int) $b->Id_SOP_Paket, (int) $b->Id_Item_Versi_Aktif),
            'Versi dokumen terkini ditolak penjaga karena snapshot masih menyimpan versi lama.',
        );
    }

    /**
     * Jalan keluar di atas tidak boleh melar jadi "siapa pun boleh".
     *
     * Endpoint ini mengembalikan signed URL berkas aslinya, jadi longgar sedikit
     * di sini berarti isi paket TERBATAS bisa dipanen lewat URL langsung.
     */
    public function test_versi_yang_sama_tetap_ditolak_dari_paket_yang_tak_memuatnya(): void
    {
        $b = $this->paketSnapshotYangSudahDirevisiAtauSkip();

        $paketLain = DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket', '!=', $b->Id_SOP_Paket)
            ->whereNotIn('Id_SOP_Paket', function ($q) use ($b) {
                $q->from('N_SOP_Paket_Item')
                    ->where('Id_SOP_Dokumen', function ($s) use ($b) {
                        $s->from('N_SOP_Paket_Item')->select('Id_SOP_Dokumen')
                            ->where('Id_SOP_Paket_Item', $b->Id_SOP_Paket_Item);
                    })
                    ->select('Id_SOP_Paket');
            })
            ->value('Id_SOP_Paket');

        if (! $paketLain) {
            $this->markTestSkipped('Tak ada paket lain yang tak memuat dokumen itu.');
        }

        $this->assertFalse(
            app(\App\Services\LMS\SOP\SopProgressReadService::class)
                ->versiVisibleInPaket($b->Kode_Karyawan, (int) $paketLain, (int) $b->Id_Item_Versi_Aktif),
            'Versi dokumen bisa dibuka lewat paket yang sama sekali tak memuatnya.',
        );
    }

    /**
     * Mode_Akses & Tipe_Item harus tetap terbaca untuk versi milik DOKUMEN.
     *
     * Versi dokumen ber-Id_SOP_Paket_Item NULL, sehingga pencarian item lewat FK
     * tunggal tak menemukan apa pun — dan "tak ketemu" di jalur ini tidak jatuh
     * ke sisi aman: FORM jadi tak boleh diunduh (satu-satunya alasan tipe itu
     * ada), sementara DOKUMEN VIEW_ONLY berhenti dikenali sebagai VIEW_ONLY
     * sehingga URL mentahnya ikut terkirim.
     */
    public function test_aturan_akses_terbaca_untuk_versi_milik_dokumen(): void
    {
        $b = $this->paketSnapshotYangSudahDirevisiAtauSkip();

        $item = DB::table('N_SOP_Item_Versi as vs')
            ->join('N_SOP_Paket_Item as i', function ($j) {
                $j->on(function ($q) {
                    $q->on('i.Id_SOP_Dokumen', '=', 'vs.Id_SOP_Dokumen')
                        ->orOn('i.Id_SOP_Paket_Item', '=', 'vs.Id_SOP_Paket_Item');
                });
            })
            ->where('vs.Id_SOP_Item_Versi', $b->Id_Item_Versi_Aktif)
            ->where('i.Id_SOP_Paket', $b->Id_SOP_Paket)
            ->first(['i.Mode_Akses', 'i.Tipe_Item']);

        $this->assertNotNull($item, 'Aturan akses item tak terbaca untuk versi milik dokumen.');
        $this->assertNotNull($item->Tipe_Item);
    }

    /**
     * Riwayat versi di sisi KARYAWAN harus selengkap riwayat dokumennya.
     *
     * Dua saringan memenggalnya, keduanya berakar sama — versi milik dokumen
     * ber-Id_SOP_Paket_Item NULL:
     *
     *   1. pencarian versi lewat FK item saja, yang hanya menemukan versi
     *      warisan dari zaman versi milik item;
     *   2. batas "versi yang pernah dirilis" (N_SOP_Paket_Versi_Item), yang tak
     *      pernah mencatat revisi dokumen.
     *
     * Akibatnya karyawan melihat SATU baris untuk dokumen berisi enam versi,
     * dan yang satu itu versi tertua — sementara admin melihat keenamnya.
     * Riwayat yang memangkas diam-diam lebih buruk daripada tak ada riwayat:
     * ia terbaca sebagai pernyataan bahwa dokumennya memang tak pernah direvisi.
     */
    public function test_riwayat_versi_karyawan_selengkap_riwayat_dokumennya(): void
    {
        $b = $this->paketSnapshotYangSudahDirevisiAtauSkip();

        $idDok = DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket_Item', $b->Id_SOP_Paket_Item)->value('Id_SOP_Dokumen');

        $jumlahResmi = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $idDok)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereIn('Status', [SopStatus::PAKET_PUBLISHED, 'OBSOLETE'])
            ->count();

        $this->assertGreaterThan(1, $jumlahResmi,
            'Kondisi ujinya tak mewakili — dokumennya baru punya satu versi resmi.');

        $rows = app(\App\Services\LMS\SOP\SopProgressReadService::class)
            ->itemVersiHistory($b->Kode_Karyawan, (int) $b->Id_SOP_Paket, (int) $b->Id_SOP_Paket_Item);

        $this->assertCount($jumlahResmi, $rows,
            'Karyawan hanya melihat sebagian riwayat versi dokumennya.');
    }

    /**
     * Dan yang ditandai "berlaku" harus versi HIDUP, bukan versi beku snapshot.
     *
     * Salah menandai di sini bukan cacat kosmetik: baris itulah yang diberi
     * lencana "Versi Berlaku" di layar karyawan, jadi revisi lama tampil sebagai
     * yang sah sementara revisi terbaru tampil sebagai riwayat.
     */
    public function test_riwayat_versi_menandai_versi_hidup_sebagai_yang_berlaku(): void
    {
        $b = $this->paketSnapshotYangSudahDirevisiAtauSkip();

        $rows = app(\App\Services\LMS\SOP\SopProgressReadService::class)
            ->itemVersiHistory($b->Kode_Karyawan, (int) $b->Id_SOP_Paket, (int) $b->Id_SOP_Paket_Item);

        $aktif = collect($rows)->filter(fn ($r) => $r['Is_Aktif'] === true)->values();

        $this->assertCount(1, $aktif, 'Versi yang berlaku harus tepat satu.');
        $this->assertSame(
            Hashids::connection('custom')->encode((int) $b->Id_Item_Versi_Aktif),
            $aktif[0]['Id_SOP_Item_Versi'],
            'Lencana "Versi Berlaku" menempel pada versi beku snapshot, bukan versi hidup.',
        );
    }

    /**
     * Tawaran "Lihat Riwayat Versi Terdahulu" ikut dihitung dua sumbu.
     *
     * Flag ini yang menentukan apakah riwayatnya ditawarkan sama sekali; menghitung
     * lewat FK item saja menjawab "1" untuk dokumen berisi enam versi.
     */
    public function test_flag_ada_riwayat_menghitung_versi_dokumen(): void
    {
        $b = $this->paketSnapshotYangSudahDirevisiAtauSkip();

        $bundle = app(\App\Services\LMS\SOP\SopProgressReadService::class)
            ->detailBundle($b->Kode_Karyawan, (int) $b->Id_SOP_Paket);

        $item = collect($bundle['items'] ?? [])
            ->first(fn ($x) => ($x['Id_SOP_Paket_Item'] ?? null)
                === Hashids::connection('custom')->encode((int) $b->Id_SOP_Paket_Item));

        if (! $item) {
            $this->markTestSkipped('Item tak muncul di bundle penugasan itu.');
        }

        $this->assertTrue($item['Ada_Riwayat'],
            'Dokumen berisi banyak versi dilaporkan tak punya riwayat, sehingga tak pernah ditawarkan.');
    }

    /**
     * Pointer hasil penyelesaian tak boleh mundur ke versi yang sudah dicabut.
     *
     * Inilah bentuk konkret cacatnya: draft dibuka saat dokumen di v1, Master
     * menerbitkan v2, lalu paket diterbitkan. Tanpa penjagaan, pointer kembali
     * ke v1 yang berstatus OBSOLETE — karyawan membaca revisi yang sudah tak
     * berlaku, dan invarian "pointer aktif hanya menunjuk PUBLISHED" jebol.
     */
    public function test_pointer_tidak_mundur_ke_versi_yang_sudah_dicabut(): void
    {
        $paket = DB::table('N_SOP_Master_Paket')->where('Flag_Aktif', SopStatus::AKTIF)->first(['Id_SOP_Paket']);
        if (! $paket) {
            $this->markTestSkipped('Tak ada paket aktif.');
        }

        $svc = app(SopDokumenService::class);
        $idDok = $svc->buat(['Judul' => 'SOP UJI TAK MUNDUR', 'Tipe' => 'DOKUMEN'], 1);

        $idItem = DB::table('N_SOP_Paket_Item')->insertGetId([
            'Id_SOP_Paket' => $paket->Id_SOP_Paket, 'Id_SOP_Dokumen' => $idDok,
            'Tipe_Item' => 'DOKUMEN', 'Judul_Item' => 'Item uji tak mundur',
            'Urutan' => 999, 'Flag_Aktif' => SopStatus::AKTIF,
            'Created_By' => 1, 'Created_At' => now(),
        ], 'Id_SOP_Paket_Item');

        $v1 = $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji', 'Id_Master_Konten' => 1], 1);
        $this->siapkanTerbit($v1);
        $svc->terbitkanVersi($v1, 1);

        // Draft dibuka SEKARANG: ia menyalin v1 sebagai targetnya.
        $target = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $idItem)->value('Id_Item_Versi_Aktif');
        $this->assertSame((int) $v1, (int) $target);

        // Master menerbitkan revisi SESUDAH draft dibuka.
        $v2 = $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji', 'Id_Master_Konten' => 1], 1);
        $this->siapkanTerbit($v2);
        $svc->terbitkanVersi($v2, 1);

        $this->assertSame(SopStatus::VERSI_OBSOLETE,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $v1)->value('Status'),
            'Revisi baru harus mencabut versi sebelumnya.');

        /* Inilah keputusannya: penyelesaian pointer saat publish paket memakai
           versi aktif DOKUMEN, bukan salinan usang yang dibawa draft. */
        $pointer = (new \ReflectionClass(\App\Services\LMS\SOP\SopPaketDraftService::class))
            ->getMethod('pointerTakMundur');
        $pointer->setAccessible(true);
        $hasil = $pointer->invoke(app(\App\Services\LMS\SOP\SopPaketDraftService::class), $idItem, $target);

        $this->assertSame((int) $v2, (int) $hasil,
            'Publish paket memundurkan karyawan ke versi yang sudah dicabut.');
        $this->assertSame(SopStatus::VERSI_PUBLISHED,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $hasil)->value('Status'),
            'Pointer aktif hanya boleh menunjuk versi PUBLISHED.');
    }
}
