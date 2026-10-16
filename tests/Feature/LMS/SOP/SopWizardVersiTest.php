<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopTahapVersiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Wizard versi dokumen SOP.
 *
 * YANG DIUJI DI SINI: langkah yang dibuka layar ditentukan POSISI TAHAP, bukan
 * kelengkapan isi.
 *
 * Sebelum perbaikan 2026-08-26, layar memilih "langkah pertama yang isinya
 * kurang". Server melaporkan langkah Pengajuan SELALU lengkap, sehingga versi
 * yang baru disimpan di langkah 1 langsung melompat ke langkah 2 atau 3 — dan
 * melompat lagi setiap kali admin menyimpan. Kerjanya tersimpan; yang salah
 * layar tak pernah menunjukkannya. Uji di berkas ini mengunci perilaku baru
 * supaya kelas bug itu tak lahir kembali.
 */
class SopWizardVersiTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopTahapVersiService
    {
        return app(SopTahapVersiService::class);
    }

    private function dok(): SopDokumenService
    {
        return app(SopDokumenService::class);
    }

    /** Dokumen baru beserta versi pertamanya — sengaja TANPA berkas & pengesah. */
    private function versiBaru(): int
    {
        $id = $this->dok()->buat([
            'Judul' => 'SOP UJI WIZARD '.substr((string) microtime(true), -5),
            'Tipe' => 'DOKUMEN',
        ], 1);

        $idVersi = $this->dok()->tambahVersi($id, ['Ringkasan_Perubahan' => 'Terbit awal'], 1);
        if (! $idVersi) {
            $this->markTestSkipped('Versi gagal dibuat.');
        }

        return $idVersi;
    }

    private function beriPengesah(int $idVersi): void
    {
        $this->dok()->ubahVersi($idVersi, [
            'Pengesah' => [[
                'Peran' => 'Disahkan oleh',
                'Nama' => 'Direktur Uji',
                'Jabatan' => 'Direktur',
            ]],
        ], 1);
    }

    /* ── Langkah berjalan ───────────────────────────────────────────────── */

    public function test_versi_baru_berdiri_di_langkah_satu(): void
    {
        /* REGRESI. Versi yang baru lahir ada di DRAFTING = langkah 1. */
        $idVersi = $this->versiBaru();

        $this->assertSame(1, $this->svc()->langkahBerjalan($idVersi),
            'Versi di DRAFTING harus berdiri di langkah 1, bukan melompat ke langkah yang isinya kurang.');
    }

    public function test_langkah_berjalan_mengikuti_tahap_bukan_isi(): void
    {
        $idVersi = $this->versiBaru();
        $this->svc()->pindah($idVersi, 'REVIEW', 1);

        /* Tanpa satu pun catatan review ditulis — isi langkah 2 masih kosong,
           tetapi versinya memang BERDIRI di langkah 2. */
        $this->assertSame(2, $this->svc()->langkahBerjalan($idVersi));

        $this->svc()->pindah($idVersi, 'APPROVAL', 1);
        $this->assertSame(3, $this->svc()->langkahBerjalan($idVersi));
    }

    public function test_disahkan_membuka_langkah_empat(): void
    {
        /* Inti DML-2026-048: APPROVED duduk di LANGKAH 4, bukan langkah 3
           seperti sebelum DML-2026-042. Tanpa ini, langkah Pemberkasan tak
           akan pernah terbuka — satu-satunya jalan maju dari Approval adalah
           menerbitkan, sedangkan berkasnya justru diisi di langkah 4. */
        $idVersi = $this->versiBaru();
        $this->svc()->pindah($idVersi, 'REVIEW', 1);
        $this->svc()->pindah($idVersi, 'APPROVAL', 1);
        $this->beriPengesah($idVersi);
        $this->svc()->pindah($idVersi, 'APPROVED', 1);

        $this->assertSame(4, $this->svc()->langkahBerjalan($idVersi));
    }

    /* ── Kunci langkah ──────────────────────────────────────────────────── */

    public function test_langkah_di_depan_terkunci(): void
    {
        $idVersi = $this->versiBaru();
        $alur = $this->svc()->alurVersi($idVersi);

        $terkunci = collect($alur['Langkah'])->pluck('Terkunci', 'Nomor');

        $this->assertSame(1, $alur['Langkah_Aktif']);
        $this->assertFalse($terkunci[1], 'Langkah berjalan tak boleh terkunci.');
        $this->assertTrue($terkunci[2], 'Langkah 2 berada di depan tahap berjalan.');
        $this->assertTrue($terkunci[3]);
        $this->assertTrue($terkunci[4]);
    }

    public function test_langkah_yang_sudah_dilewati_tetap_terbuka(): void
    {
        /* Mengoreksi ringkasan perubahan sesudah masuk Review adalah hal wajar.
           Mengunci masa lalu memaksa admin membatalkan versi hanya untuk
           memperbaiki satu kalimat. */
        $idVersi = $this->versiBaru();
        $this->svc()->pindah($idVersi, 'REVIEW', 1);

        $terkunci = collect($this->svc()->alurVersi($idVersi)['Langkah'])->pluck('Terkunci', 'Nomor');

        $this->assertFalse($terkunci[1]);
        $this->assertFalse($terkunci[2]);
        $this->assertTrue($terkunci[3]);
    }

    public function test_kelengkapan_isi_tidak_lagi_memilih_langkah(): void
    {
        /* Penjaga atas bug aslinya. Langkah 1 dilaporkan Lengkap dan langkah 3
           TIDAK — dulu itu cukup untuk membuat layar membuka langkah 3. Kini
           `Lengkap` tetap dilaporkan sebagai keterangan, tetapi langkah yang
           dibuka tetap langkah 1. */
        $idVersi = $this->versiBaru();
        $alur = $this->svc()->alurVersi($idVersi);
        $lengkap = collect($alur['Langkah'])->pluck('Lengkap', 'Kelompok');

        $this->assertTrue($lengkap['PENGAJUAN']);
        $this->assertFalse($lengkap['PENGESAHAN'], 'Belum ada pengesah — fakta ini tetap dilaporkan.');
        $this->assertSame(1, $alur['Langkah_Aktif'], 'Kelengkapan isi tak boleh menggeser langkah aktif.');
    }

    /* ── Aksi bernama ───────────────────────────────────────────────────── */

    public function test_aksi_utama_bernama_dan_menyebut_tujuannya(): void
    {
        $idVersi = $this->versiBaru();
        $aksi = $this->svc()->alurVersi($idVersi)['Aksi_Utama'];

        $this->assertSame('SIMPAN_LANJUT', $aksi['Jenis']);
        $this->assertSame('REVIEW', $aksi['Ke_Tahap']);
        $this->assertStringContainsString('Review', $aksi['Label'],
            'Label dirakit server supaya layar tak menyusun kalimatnya sendiri.');
        $this->assertSame([], $aksi['Syarat_Kurang']);
    }

    /**
     * Syarat yang kurang MELAPOR, bukan menurunkan tombol jadi "Simpan".
     *
     * Sampai 2026-08-27 aksinya jatuh ke Simpan biasa selama syaratnya belum
     * terpenuhi. Itu melahirkan klik ganda yang persis dikeluhkan: admin
     * mengetik pengesah, tombolnya masih "Simpan" karena server menilai data
     * TERSIMPAN, ia menekan Simpan, barulah tombol lanjut muncul dan ia
     * menekannya lagi.
     *
     * Yang membuat pembalikan ini aman: SIMPAN_LANJUT menyimpan DULU baru
     * memindahkan, jadi syaratnya diperiksa terhadap data sesudah simpan — dan
     * seluruh syarat yang ada dipasok dari formulir yang sedang dibuka.
     */
    public function test_syarat_kurang_dilaporkan_tanpa_menurunkan_tombol(): void
    {
        $idVersi = $this->versiBaru();
        $this->svc()->pindah($idVersi, 'REVIEW', 1);
        $this->svc()->pindah($idVersi, 'APPROVAL', 1);

        $aksi = $this->svc()->alurVersi($idVersi)['Aksi_Utama'];

        $this->assertSame('SIMPAN_LANJUT', $aksi['Jenis']);
        $this->assertSame('APPROVED', $aksi['Ke_Tahap']);
        $this->assertStringContainsString('Pemberkasan', $aksi['Label']);
        $this->assertContains('Realisasi pengesah', $aksi['Syarat_Kurang'],
            'Yang kurang tetap dilaporkan — layar menampilkannya di samping tombol.');
    }

    /**
     * Penegakannya tetap di server, bukan di tombol.
     *
     * Tombol yang menawarkan maju TIDAK berarti majunya diizinkan. Kalau
     * formulirnya memang kosong, pindah() menolak dan menyebut apa yang kurang.
     */
    public function test_maju_tetap_ditolak_bila_syaratnya_benar_benar_kosong(): void
    {
        $idVersi = $this->versiBaru();
        $this->svc()->pindah($idVersi, 'REVIEW', 1);
        $this->svc()->pindah($idVersi, 'APPROVAL', 1);

        try {
            $this->svc()->pindah($idVersi, 'APPROVED', 1);
            $this->fail('Maju ke Pemberkasan tanpa pengesah seharusnya ditolak.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Realisasi pengesah', $e->getMessage());
        }
    }

    /** Sesudah pengesahnya tersimpan, yang kurang habis dengan sendirinya. */
    public function test_syarat_kurang_habis_setelah_pengesah_tersimpan(): void
    {
        $idVersi = $this->versiBaru();
        $this->svc()->pindah($idVersi, 'REVIEW', 1);
        $this->svc()->pindah($idVersi, 'APPROVAL', 1);
        $this->beriPengesah($idVersi);

        $aksi = $this->svc()->alurVersi($idVersi)['Aksi_Utama'];

        $this->assertSame('SIMPAN_LANJUT', $aksi['Jenis']);
        $this->assertSame([], $aksi['Syarat_Kurang']);
    }

    public function test_aksi_lain_menamai_akibat_bukan_tahap_tujuan(): void
    {
        $idVersi = $this->versiBaru();
        $this->svc()->pindah($idVersi, 'REVIEW', 1);
        $this->svc()->pindah($idVersi, 'APPROVAL', 1);

        $jenis = collect($this->svc()->alurVersi($idVersi)['Aksi_Lain'])->pluck('Label', 'Jenis');

        $this->assertArrayHasKey('KEMBALIKAN', $jenis->all());
        $this->assertArrayHasKey('BATALKAN', $jenis->all());
        $this->assertStringContainsString('Kembalikan', $jenis['KEMBALIKAN']);
        $this->assertStringContainsString('Batalkan', $jenis['BATALKAN']);
    }

    /* ── Penjaga penerbitan ─────────────────────────────────────────────── */

    public function test_terbit_ditolak_dari_tahap_yang_bukan_pemberkasan(): void
    {
        /* Penjaga wajib punya uji yang menuntut PENOLAKAN. Uji jalur sukses
           saja pernah membuat penjaga mati tanpa ketahuan berhari-hari. */
        $idVersi = $this->versiBaru();
        $this->svc()->pindah($idVersi, 'REVIEW', 1);
        $this->svc()->pindah($idVersi, 'APPROVAL', 1);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/belum boleh diterbitkan/i');

        $this->dok()->terbitkanVersi($idVersi, 1);
    }

    public function test_terbit_ditolak_dari_drafting(): void
    {
        $idVersi = $this->versiBaru();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/belum boleh diterbitkan/i');

        $this->dok()->terbitkanVersi($idVersi, 1);
    }

    public function test_dari_pemberkasan_penjaga_tahap_dilewati(): void
    {
        /* Dari APPROVED, FILING_REGISTERED adalah tujuan sah — jadi penjaga
           TAHAP lolos. Yang menolak berikutnya adalah pastikanIsiSah() karena
           berkas finalnya memang belum dipasang. Dibedakan lewat pesannya:
           kalau penjaga tahap salah pasang, pesan yang muncul akan keliru dan
           uji ini gagal. */
        $idVersi = $this->versiBaru();
        $this->svc()->pindah($idVersi, 'REVIEW', 1);
        $this->svc()->pindah($idVersi, 'APPROVAL', 1);
        $this->beriPengesah($idVersi);
        $this->svc()->pindah($idVersi, 'APPROVED', 1);

        try {
            $this->dok()->terbitkanVersi($idVersi, 1);
            $this->fail('Versi tanpa berkas final seharusnya ditolak.');
        } catch (\DomainException $e) {
            $this->assertStringNotContainsString('belum boleh diterbitkan', $e->getMessage(),
                'Penolakan datang dari penjaga tahap, padahal Pemberkasan memang mengarah ke Filing.');
        }
    }

    /* ── Syarat tanggal masa berlaku (DDL-2026-049 / DML-2026-050) ───────── */

    /** Nilai syarat dibaca lewat refleksi: ia privat, dan memang seharusnya. */
    private function nilaiSyarat(int $idVersi): array
    {
        $m = new \ReflectionMethod(SopTahapVersiService::class, 'nilaiSyarat');
        $m->setAccessible(true);

        return $m->invoke($this->svc(), $idVersi);
    }

    public function test_versi_tanpa_tanggal_belum_memenuhi_syarat_terbit(): void
    {
        $nilai = $this->nilaiSyarat($this->versiBaru());

        $this->assertFalse($nilai['TANGGAL_BERLAKU']);
        $this->assertFalse($nilai['TANGGAL_KADALUARSA']);
        $this->assertFalse($nilai['TANGGAL_REVIEW_BERIKUTNYA']);
    }

    /**
     * Mengisi SATU tanggal memenuhi ketiganya.
     *
     * Itu bukan kelonggaran melainkan inti rancangannya: dua tanggal turunan
     * dilengkapi baku oleh SopMasaBerlakuService di jalur simpan, sehingga
     * gerbang yang bertambah tak berubah menjadi tiga pekerjaan tambahan bagi
     * admin. Kalau pelengkapan baku itu copot, uji ini yang merah lebih dulu.
     */
    public function test_mengisi_tanggal_berlaku_melengkapi_dua_tanggal_turunan(): void
    {
        $idVersi = $this->versiBaru();
        $this->dok()->ubahVersi($idVersi, ['Tanggal_Berlaku' => '2026-03-15'], 1);

        $nilai = $this->nilaiSyarat($idVersi);

        $this->assertTrue($nilai['TANGGAL_BERLAKU']);
        $this->assertTrue($nilai['TANGGAL_KADALUARSA']);
        $this->assertTrue($nilai['TANGGAL_REVIEW_BERIKUTNYA']);

        $baris = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $idVersi)
            ->first(['Tanggal_Kadaluarsa', 'Tanggal_Review_Berikutnya']);

        $this->assertStringStartsWith('2028-03-15', (string) $baris->Tanggal_Kadaluarsa);
        $this->assertStringStartsWith('2027-03-15', (string) $baris->Tanggal_Review_Berikutnya);
    }

    /** Isian admin menang atas baku. */
    public function test_kadaluarsa_yang_diketik_admin_tidak_ditimpa_baku(): void
    {
        $idVersi = $this->versiBaru();
        $this->dok()->ubahVersi($idVersi, [
            'Tanggal_Berlaku' => '2026-03-15',
            'Tanggal_Kadaluarsa' => '2030-01-01',
        ], 1);

        $baris = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $idVersi)
            ->first(['Tanggal_Kadaluarsa']);

        $this->assertStringStartsWith('2030-01-01', (string) $baris->Tanggal_Kadaluarsa);
    }

    public function test_syarat_tanggal_terdaftar_di_master_filing(): void
    {
        /* Gerbangnya DATA, bukan cabang di kode. Kalau baris masternya hilang,
           seluruh penjagaan tanggal lenyap tanpa satu pun galat — dan itu tak
           akan terlihat sampai ada versi tanpa masa berlaku yang terbit. */
        $kode = DB::table('N_SOP_Master_Tahap_Versi_Syarat as s')
            ->join('N_SOP_Master_Tahap_Versi as t', 't.Id_Tahap', '=', 's.Tahap_Id')
            ->where('t.Kode_Tahap', 'FILING_REGISTERED')
            ->where('s.Flag_Aktif', 'Y')
            ->pluck('s.Kode_Syarat')->all();

        $this->assertContains('TANGGAL_BERLAKU', $kode);
        $this->assertContains('TANGGAL_KADALUARSA', $kode);
        $this->assertContains('TANGGAL_REVIEW_BERIKUTNYA', $kode);
    }

    /* ── Versi yang belum lahir ─────────────────────────────────────────── */

    /**
     * REGRESI. Layar pernah memaku aksi versi baru sebagai "Simpan Draft
     * Versi", karena endpoint template hanya mengirim rangka langkah tanpa
     * aksi. Akibatnya admin menghadapi jembatan yang tak dimintanya: satu klik
     * untuk menyimpan, satu klik lagi untuk lanjut — padahal ia membuka wizard
     * ini memang untuk mendefinisikan versi lalu meneruskannya.
     */
    public function test_template_menamai_aksi_maju_bukan_sekadar_simpan(): void
    {
        $aksi = $this->svc()->alurTemplate()['Aksi_Utama'];

        $this->assertSame('SIMPAN_LANJUT', $aksi['Jenis']);
        $this->assertSame('REVIEW', $aksi['Ke_Tahap']);
        $this->assertStringContainsString('Simpan & Lanjut', $aksi['Label']);
        $this->assertSame([], $aksi['Syarat_Kurang']);
    }

    /** Bentuknya sama persis dengan alurVersi() supaya layar tak punya dua jalur. */
    public function test_template_berbentuk_sama_dengan_alur_versi(): void
    {
        $template = $this->svc()->alurTemplate();
        $nyata = $this->svc()->alurVersi($this->versiBaru());

        $this->assertSame(array_keys($nyata), array_keys($template));
        $this->assertSame(1, $template['Langkah_Aktif']);
        $this->assertSame([], $template['Aksi_Lain'],
            'Belum ada yang bisa dikembalikan maupun dibatalkan sebelum versinya ada.');
    }

    /** Langkah 1 terbuka, sisanya terkunci — dihitung server, bukan ditempel layar. */
    public function test_template_mengunci_langkah_selain_yang_pertama(): void
    {
        $langkah = collect($this->svc()->alurTemplate()['Langkah'])->keyBy('Nomor');

        $this->assertFalse($langkah[1]['Terkunci']);
        $this->assertTrue($langkah[2]['Terkunci']);
        $this->assertTrue($langkah[3]['Terkunci']);
        $this->assertTrue($langkah[4]['Terkunci']);
    }

    /**
     * Label tahapnya dibaca dari master, bukan dikarang.
     *
     * Kalau tahap pertama dinamai ulang, kalimat tombolnya ikut — tanpa satu
     * baris kode pun berubah. Itu seluruh alasan aksinya dipindah ke server.
     */
    public function test_label_template_mengikuti_nama_tahap_di_master(): void
    {
        $nama = DB::table('N_SOP_Master_Tahap_Versi')
            ->where('Kode_Tahap', 'REVIEW')->value('Nama_Tahap');

        $this->assertSame(
            'Simpan & Lanjut ke '.$nama,
            $this->svc()->alurTemplate()['Aksi_Utama']['Label'],
        );
    }

    /* ── Langkah 5: Tinjauan Akhir (DML-2026-051) ───────────────────────── */

    /** Versi baru pun sudah melihat kelima kotaknya di rel langkah. */
    public function test_alur_punya_lima_langkah(): void
    {
        $nama = array_column($this->svc()->alurTemplate()['Langkah'], 'Nama', 'Nomor');

        $this->assertCount(5, $nama);
        $this->assertSame('Pemberkasan', $nama[4]);
        $this->assertSame('Tinjauan Akhir', $nama[5],
            'Bukan "Review": langkah 2 sudah memakai nama itu untuk catatan review.');
    }

    private function diPemberkasan(): int
    {
        $idVersi = $this->versiBaru();
        $this->svc()->pindah($idVersi, 'REVIEW', 1);
        $this->svc()->pindah($idVersi, 'APPROVAL', 1);
        $this->beriPengesah($idVersi);
        $this->svc()->pindah($idVersi, 'APPROVED', 1);

        return $idVersi;
    }

    /**
     * Dari Pemberkasan, tombolnya MENGANTAR — bukan menerbitkan dari kejauhan.
     *
     * Penerbitan menggeser pointer dokumen, meng-obsolete versi lama, merakit
     * ulang nomor resmi, dan merekonsiliasi paket perujuk. Semuanya tak bisa
     * ditarik, jadi di antaranya berdiri satu langkah yang hanya untuk dibaca.
     */
    public function test_pemberkasan_mengantar_ke_tinjauan_bukan_menerbitkan(): void
    {
        $alur = $this->svc()->alurVersi($this->diPemberkasan());

        $this->assertSame(4, $alur['Langkah_Aktif']);
        $this->assertSame('LANJUT_LANGKAH', $alur['Aksi_Antar']['Jenis']);
        $this->assertSame(5, $alur['Aksi_Antar']['Ke_Langkah']);
        $this->assertSame('Simpan & Lanjut ke Tinjauan Akhir', $alur['Aksi_Antar']['Label']);

        /* Aksi utamanya tetap penerbitan — yang berpindah hanya kapan ia
           ditawarkan, bukan apa yang ia lakukan. */
        $this->assertSame('TERBITKAN', $alur['Aksi_Utama']['Jenis']);
        $this->assertSame(5, $alur['Aksi_Utama']['Ke_Langkah']);
    }

    /**
     * Langkah tinjauan boleh DIBUKA sebelum ditempati.
     *
     * Ia ada untuk dilihat sebelum diterbitkan; menguncinya sampai versinya
     * terbit berarti rekap yang hanya bisa dibaca sesudah terlambat.
     */
    public function test_langkah_tinjauan_tidak_terkunci_dari_pemberkasan(): void
    {
        $langkah = collect($this->svc()->alurVersi($this->diPemberkasan())['Langkah'])->keyBy('Nomor');

        $this->assertFalse($langkah[5]['Terkunci']);
    }

    /** Pengecualian itu SEMPIT: langkah maju lain tetap terkunci seperti semula. */
    public function test_pengecualian_kunci_tidak_bocor_ke_langkah_lain(): void
    {
        $idVersi = $this->versiBaru();
        $this->svc()->pindah($idVersi, 'REVIEW', 1);

        $langkah = collect($this->svc()->alurVersi($idVersi)['Langkah'])->keyBy('Nomor');

        $this->assertTrue($langkah[3]['Terkunci'], 'Pengesahan masih di depan.');
        $this->assertTrue($langkah[4]['Terkunci'], 'Pemberkasan masih di depan.');
        $this->assertTrue($langkah[5]['Terkunci'],
            'Tinjauan hanya terbuka ketika penerbitan memang sudah jadi aksi utamanya.');
    }

    /** Belum terbit berarti langkah tinjauan belum selesai. */
    public function test_tinjauan_belum_selesai_sebelum_terbit(): void
    {
        $langkah = collect($this->svc()->alurVersi($this->diPemberkasan())['Langkah'])
            ->firstWhere('Kelompok', 'TINJAUAN');

        $this->assertFalse($langkah['Lengkap']);
        $this->assertSame('menunggu diterbitkan', $langkah['Ringkasan']);
    }

    /* ── Review kosong itu SAH ───────────────────────────────────────────── */

    /**
     * REGRESI. Versi yang melewati Review tanpa catatan pernah selamanya
     * berbunyi "0 putaran review" dengan Lengkap=false, dan admin membacanya
     * sebagai pekerjaan yang tertinggal. Padahal DML-2026-042 sudah mencabut
     * syarat CATATAN_REVIEW justru karena review kosong itu sah — perpanjangan
     * dan review yang berjalan mulus memang tak meninggalkan catatan.
     */
    public function test_review_terlewati_tanpa_catatan_terhitung_selesai(): void
    {
        $idVersi = $this->versiBaru();
        $this->svc()->pindah($idVersi, 'REVIEW', 1);
        $this->svc()->pindah($idVersi, 'APPROVAL', 1);

        $review = collect($this->svc()->alurVersi($idVersi)['Langkah'])
            ->firstWhere('Kelompok', 'REVIEW');

        $this->assertTrue($review['Lengkap'],
            'Langkah Review yang sudah dilewati harus terhitung selesai walau tanpa catatan.');
        $this->assertSame('tanpa catatan review', $review['Ringkasan']);
    }

    /** Yang BELUM dilewati tetap jujur menyebut dirinya belum berisi. */
    public function test_review_yang_sedang_dikerjakan_belum_terhitung_selesai(): void
    {
        $idVersi = $this->versiBaru();
        $this->svc()->pindah($idVersi, 'REVIEW', 1);

        $review = collect($this->svc()->alurVersi($idVersi)['Langkah'])
            ->firstWhere('Kelompok', 'REVIEW');

        $this->assertFalse($review['Lengkap']);
        $this->assertSame('belum ada catatan', $review['Ringkasan']);
    }
}
