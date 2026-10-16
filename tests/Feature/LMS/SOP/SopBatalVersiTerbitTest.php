<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopTahapVersiService;
use App\Services\LMS\SOP\Support\SopNomorVersi;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Membatalkan versi yang SUDAH TERBIT — hanya selama belum beredar.
 *
 * KENAPA ADA. Admin menerbitkan versi lalu sadar berkasnya salah. Tanpa jalur
 * ini satu-satunya obat adalah menerbitkan revisi pengganti — dan revisi itu
 * mengonsumsi satu nomor untuk kekeliruan berumur beberapa menit, sementara
 * yang keliru menjadi OBSOLETE dan nomornya terkunci selamanya.
 *
 * GARISNYA: nomor revisi dikonsumsi saat versinya SAMPAI KE ORANG, bukan saat
 * diterbitkan. Sesudah beredar, membatalkan berarti menyatakan dokumen yang
 * sudah diikuti orang "tak pernah terbit" — dan untuk itulah OBSOLETE ada.
 *
 * Menuntut DML-2026-064 (transisi FILING_REGISTERED -> CANCEL + syarat
 * BELUM_BEREDAR).
 */
class SopBatalVersiTerbitTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopDokumenService
    {
        return app(SopDokumenService::class);
    }

    private function tahapId(string $kode): int
    {
        return (int) DB::table('N_SOP_Master_Tahap_Versi')->where('Kode_Tahap', $kode)->value('Id_Tahap');
    }

    /**
     * Melengkapi versi sampai memenuhi syarat terbit.
     *
     * PERLU JUGA UNTUK YANG ARSIP: mengembalikan versi lama ke berlaku adalah
     * masuk ke FILING_REGISTERED, dan tahap itu menuntut syaratnya dipenuhi
     * seperti biasa. Versi arsip yang tak lengkap memang menolak dibangkitkan —
     * di dunia nyata ia dulu terbit, jadi ia pasti lengkap; hanya fixture ini
     * yang harus mengejarnya.
     */
    private function lengkapiSyaratTerbit(int $idVersi): void
    {
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->update([
            'Id_Master_Konten' => DB::table('N_LMS_Master_Konten')->value('Id_Master_Konten'),
            'Disahkan_Oleh' => 'Penguji',
            'Tanggal_Berlaku' => '2026-01-01',
            'Tanggal_Kadaluarsa' => '2027-01-01',
            'Tanggal_Review_Berikutnya' => '2026-07-01',
        ]);
    }

    /**
     * Dokumen dengan v00 OBSOLETE dan v01 PUBLISHED.
     *
     * Keadaannya disetel langsung: yang diuji adalah PEMBATALANNYA, dan
     * menyeret seluruh alur terbit ke sini membuat uji gagal karena sebab yang
     * sama sekali lain.
     */
    private function dokumenDuaVersi(): array
    {
        $svc = $this->svc();
        $idDok = $svc->buat([
            'Judul' => 'SOP UJI BATAL TERBIT '.substr((string) microtime(true), -5),
            'Tipe' => 'DOKUMEN',
        ], 1);

        $lama = $svc->tambahVersi($idDok, ['Ringkasan_Perubahan' => 'v00'], 1);
        $this->lengkapiSyaratTerbit($lama);
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $lama)->update([
            'Status' => SopStatus::VERSI_OBSOLETE,
            'Tahap_Id' => $this->tahapId('OBSOLETE'),
            /* Versi arsip sungguhan selalu membawa tanggal ini — dan tanpanya
               uji pengembalian di bawah tak menguji apa pun. */
            'Tanggal_Obsolete' => '2026-06-01',
        ]);

        $baru = $svc->tambahVersi($idDok, ['Ringkasan_Perubahan' => 'v01'], 1);
        $this->lengkapiSyaratTerbit($baru);
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $baru)->update([
            'Status' => SopStatus::VERSI_PUBLISHED,
            'Tahap_Id' => $this->tahapId('FILING_REGISTERED'),
        ]);
        DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)->update(['Id_Versi_Aktif' => $baru]);

        return [$idDok, $lama, $baru];
    }

    /**
     * Pasang dokumen sebagai item pada sebuah paket.
     *
     * @return array{0:int,1:int} [id paket, id paket item]
     */
    private function pasangDiPaket(int $idDok, int $idVersi, string $statusPaket): array
    {
        $idPaket = DB::table('N_SOP_Master_Paket')->insertGetId([
            'Nama_Paket' => 'PAKET UJI '.substr((string) microtime(true), -5),
            'Status' => $statusPaket,
        ], 'Id_SOP_Paket');

        $idItem = DB::table('N_SOP_Paket_Item')->insertGetId([
            'Id_SOP_Paket' => $idPaket,
            'Id_SOP_Dokumen' => $idDok,
            'Id_Item_Versi_Aktif' => $idVersi,
            'Tipe_Item' => 'DOKUMEN',
            'Judul_Item' => 'Item uji',
        ], 'Id_SOP_Paket_Item');

        return [$idPaket, $idItem];
    }

    public function test_membatalkan_versi_terbit_melepas_nomornya(): void
    {
        [$idDok, $lama, $baru] = $this->dokumenDuaVersi();

        $this->assertNull($this->svc()->batalkanVersiTerbit($baru, 1, $lama));

        $this->assertSame(SopStatus::INAKTIF,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $baru)->value('Flag_Aktif'),
            'Versi batal harus mati, itulah yang melepas nomornya.');

        $this->assertSame(1, SopNomorVersi::berikutnya($idDok),
            'Nomor yang dilepas tak ditawarkan kembali — padahal itulah gunanya jalur ini.');
    }

    public function test_versi_pengganti_kembali_berlaku(): void
    {
        [$idDok, $lama, $baru] = $this->dokumenDuaVersi();

        $this->svc()->batalkanVersiTerbit($baru, 1, $lama);

        $this->assertSame(SopStatus::VERSI_PUBLISHED,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $lama)->value('Status'));
        $this->assertSame($lama,
            (int) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)->value('Id_Versi_Aktif'),
            'Pointer dokumen tak ikut kembali — laporan akan menyebut versi yang sudah batal.');
    }

    /**
     * Pengganti kembali SEUTUHNYA — tahap dan tanggal digantikannya ikut mundur.
     *
     * Menulis `Status='PUBLISHED'` saja meninggalkan versi yang sedang berlaku
     * bertahap `OBSOLETE` dan tetap membawa `Tanggal_Obsolete`: stepper
     * menampilkannya sebagai arsip, riwayat menyebutnya "digantikan pada"
     * tanggal yang sudah lewat, dan `Status` tak lagi sejalan dengan
     * `Status_Teknis` tahapnya. Persis yang terjadi di dev 31 Agu 2026
     * (DML-2026-074) — uji lama hanya memeriksa `Status`, jadi hijau.
     */
    public function test_pengganti_kembali_dengan_tahap_dan_tanggalnya(): void
    {
        [, $lama, $baru] = $this->dokumenDuaVersi();

        $this->svc()->batalkanVersiTerbit($baru, 1, $lama);

        $v = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Master_Tahap_Versi as t', 't.Id_Tahap', '=', 'v.Tahap_Id')
            ->where('v.Id_SOP_Item_Versi', $lama)
            ->first(['v.Status', 'v.Tanggal_Obsolete', 't.Kode_Tahap', 't.Status_Teknis']);

        $this->assertSame('FILING_REGISTERED', $v->Kode_Tahap,
            'Versi berlaku masih bertahap arsip — layar akan menyebutnya arsip.');
        $this->assertNull($v->Tanggal_Obsolete,
            'Versi berlaku masih membawa tanggal digantikan.');
        $this->assertSame($v->Status_Teknis, $v->Status,
            'Status dan tahap berselisih — dua sumber untuk satu keadaan.');
    }

    /**
     * Tanpa pengganti, dokumen berakhir TANPA versi berlaku.
     *
     * Keadaan yang sah, dan harus benar-benar terjadi: pointer yang tetap
     * menunjuk versi batal adalah keadaan yang setiap pembaca laporan harus
     * menebak artinya.
     */
    public function test_tanpa_pengganti_dokumen_tak_punya_versi_berlaku(): void
    {
        [$idDok, , $baru] = $this->dokumenDuaVersi();

        $this->assertNull($this->svc()->batalkanVersiTerbit($baru, 1, null));

        $this->assertNull(
            DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)->value('Id_Versi_Aktif'));
    }

    /**
     * PENJAGA UTAMANYA — versi yang sudah sampai ke orang tak bisa dibatalkan.
     *
     * Ditegakkan syarat BELUM_BEREDAR pada tahap CANCEL, bukan cabang di kode:
     * aturannya tinggal di data, sama seperti seluruh rancangan tahap ini.
     */
    public function test_versi_yang_sudah_beredar_menolak_dibatalkan(): void
    {
        [, $lama, $baru] = $this->dokumenDuaVersi();

        /* Rollup coverage dipakai sebagai penanda peredaran karena ia butuh
           paling sedikit tetangga — Progress menuntut baris penjadwalan yang
           tak ada hubungannya dengan yang diuji di sini. */
        $paket = DB::table('N_SOP_Master_Paket')->value('Id_SOP_Paket');
        if (! $paket) {
            $this->markTestSkipped('Butuh satu paket untuk menandai peredaran.');
        }

        DB::table('N_SOP_Coverage_Rollup')->insert([
            'Id_SOP_Paket' => $paket,
            'Id_SOP_Item_Versi' => $baru,
            'Total_Target' => 1,
            'Jumlah_Dibuka' => 1,
            'Jumlah_Dikonfirmasi' => 1,
            'Persen_Dikonfirmasi' => 100,
        ]);

        $galat = $this->svc()->batalkanVersiTerbit($baru, 1, $lama);

        $this->assertNotNull($galat, 'Versi yang sudah dibaca orang masih bisa dibatalkan.');
        $this->assertStringContainsString('Belum diterima siapa pun', $galat);

        $this->assertSame(SopStatus::VERSI_PUBLISHED,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $baru)->value('Status'),
            'Ditolak tapi tetap dibatalkan.');
    }

    /**
     * MASUK PAKET DRAFT BUKAN "SUDAH BEREDAR".
     *
     * Admin menyusun paket dalam keadaan DRAFT: dokumennya sudah terpasang
     * sebagai item, tapi belum satu pun karyawan bisa melihatnya. Menghitung
     * pemasangan itu sebagai peredaran menolak pembatalan dengan alasan yang
     * tidak benar — dan admin tak punya cara membuktikan sebaliknya dari layar.
     */
    public function test_pemasangan_pada_paket_draft_bukan_peredaran(): void
    {
        [$idDok, $lama, $baru] = $this->dokumenDuaVersi();
        $this->pasangDiPaket($idDok, $baru, 'DRAFT');

        $this->assertNull($this->svc()->batalkanVersiTerbit($baru, 1, $lama),
            'Paket yang belum terbit ikut dihitung sebagai peredaran.');
    }

    /** Paket yang SUDAH terbit memang peredaran — di situ gerbangnya menutup. */
    public function test_pemasangan_pada_paket_terbit_menghalangi(): void
    {
        [$idDok, $lama, $baru] = $this->dokumenDuaVersi();
        $this->pasangDiPaket($idDok, $baru, 'PUBLISHED');

        $galat = $this->svc()->batalkanVersiTerbit($baru, 1, $lama);

        $this->assertNotNull($galat);
        $this->assertStringContainsString('Belum diterima siapa pun', $galat);
    }

    /**
     * Tanpa pengganti, pointer ITEM ikut dilepas — bukan hanya pointer dokumen.
     *
     * kembalikanVersi() menggeser pointer setiap item perujuk; tanpa pengganti
     * tak ada yang menggesernya, dan item paket tetap menunjuk versi yang baru
     * saja dibatalkan. Paketnya lalu terbit membawa versi mati sebagai isinya.
     */
    public function test_tanpa_pengganti_pointer_item_ikut_dilepas(): void
    {
        [$idDok, , $baru] = $this->dokumenDuaVersi();
        [, $idItem] = $this->pasangDiPaket($idDok, $baru, 'DRAFT');

        $this->assertNull($this->svc()->batalkanVersiTerbit($baru, 1, null));

        $this->assertNull(
            DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $idItem)->value('Id_Item_Versi_Aktif'),
            'Item paket masih menunjuk versi yang sudah dibatalkan.');
    }

    /** Dengan pengganti, pointer item mengikutinya — bukan tertinggal. */
    public function test_dengan_pengganti_pointer_item_mengikuti(): void
    {
        [$idDok, $lama, $baru] = $this->dokumenDuaVersi();
        [, $idItem] = $this->pasangDiPaket($idDok, $baru, 'DRAFT');

        $this->svc()->batalkanVersiTerbit($baru, 1, $lama);

        $this->assertSame($lama,
            (int) DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $idItem)->value('Id_Item_Versi_Aktif'));
    }

    /** Jalur ini hanya untuk yang sedang BERLAKU — bukan draft, bukan arsip. */
    public function test_menolak_versi_yang_bukan_terbit(): void
    {
        $svc = $this->svc();
        $idDok = $svc->buat(['Judul' => 'SOP UJI BUKAN TERBIT '.substr((string) microtime(true), -5), 'Tipe' => 'DOKUMEN'], 1);
        $draft = $svc->tambahVersi($idDok, [], 1);

        $galat = $svc->batalkanVersiTerbit($draft, 1, null);

        $this->assertNotNull($galat);
        $this->assertStringContainsString('sedang berlaku', $galat);
    }

    /** Pengganti dari dokumen LAIN ditolak — pointer tak boleh menyeberang dokumen. */
    public function test_menolak_pengganti_milik_dokumen_lain(): void
    {
        [, , $baru] = $this->dokumenDuaVersi();
        [, $lainLama] = $this->dokumenDuaVersi();

        $galat = $this->svc()->batalkanVersiTerbit($baru, 1, $lainLama);

        $this->assertNotNull($galat);
        $this->assertStringContainsString('tak dikenali', $galat);
    }

    /** Kandidat pengganti diurut dari TERBARU ke terlama — yang paling mungkin dipilih di atas. */
    public function test_kandidat_pengganti_diurut_terbaru_dulu(): void
    {
        [$idDok, $lama, $baru] = $this->dokumenDuaVersi();

        /* Satu versi arsip lagi, bernomor lebih rendah. */
        $paling = $this->svc()->tambahVersi($idDok, ['Versi_Ke' => 5], 1);
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $paling)->update([
            'Status' => SopStatus::VERSI_OBSOLETE,
            'Tahap_Id' => $this->tahapId('OBSOLETE'),
        ]);

        $urut = collect($this->svc()->kandidatPengganti($baru))->pluck('Versi_Ke')->all();

        $this->assertSame([5, 0], $urut, 'Kandidat tak diurut dari yang terbaru.');
        $this->assertNotNull($lama);
    }

    /** Transisi & syaratnya harus benar-benar terpasang — tanpa itu penjaganya semu. */
    public function test_master_membuka_jalur_terbit_ke_batal_dengan_syaratnya(): void
    {
        $aktif = DB::table('N_SOP_Master_Tahap_Versi_Transisi as x')
            ->join('N_SOP_Master_Tahap_Versi as d', 'd.Id_Tahap', '=', 'x.Dari_Tahap_Id')
            ->join('N_SOP_Master_Tahap_Versi as k', 'k.Id_Tahap', '=', 'x.Ke_Tahap_Id')
            ->where('d.Kode_Tahap', 'FILING_REGISTERED')
            ->where('k.Kode_Tahap', 'CANCEL')
            ->value('x.Flag_Aktif');

        $this->assertSame(SopStatus::AKTIF, $aktif,
            'Transisi terbit -> batal belum aktif. Jalankan DML-2026-064.');

        $syarat = DB::table('N_SOP_Master_Tahap_Versi_Syarat as s')
            ->join('N_SOP_Master_Tahap_Versi as t', 't.Id_Tahap', '=', 's.Tahap_Id')
            ->where('t.Kode_Tahap', 'CANCEL')
            ->where('s.Flag_Aktif', SopStatus::AKTIF)
            ->pluck('s.Kode_Syarat')->all();

        $this->assertContains('BELUM_BEREDAR', $syarat,
            'Tanpa syarat ini, versi yang sudah beredar pun bisa dibatalkan.');
        $this->assertNotNull(app(SopTahapVersiService::class));
    }
}
