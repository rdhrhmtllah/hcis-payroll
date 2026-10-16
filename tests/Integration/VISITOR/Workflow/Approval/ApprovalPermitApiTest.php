<?php

namespace Tests\Integration\VISITOR\Workflow\Approval;

use App\Services\VISITOR\Workflow\Approval\VisitorApprovalDecisionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Approval final permit tamu memakai JENDELA WAKTU, bukan durasi bulanan.
 *
 * Penjaga MONTH di resolveDuration() melindungi permit KARYAWAN dan TIDAK
 * dilonggarkan untuk ini — permit tamu memang tidak melewatinya sama sekali.
 * Uji cermin di bawah menagih fakta itu secara langsung: kalau penjaganya ikut
 * longgar, permit karyawan bisa disetujui tanpa masa berlaku sama sekali.
 *
 * Jendelanya kini DIPILIH VERIFIKATOR saat menyetujui. Mitra tidak tahu kapan
 * tamunya benar-benar tiba dan sampai kapan ia boleh berada di area; yang tahu
 * adalah orang yang memeriksanya di pos.
 */
class ApprovalPermitApiTest extends TestCase
{
    use DatabaseTransactions;

    /** Kode karyawan NYATA di Web_HRIS development. */
    private const PIC = 'A1';

    public function test_permit_api_disetujui_tanpa_id_durasi(): void
    {
        $no = $this->buatPermitApi();

        $final = app(VisitorApprovalDecisionService::class)->approvePermit($no, self::PIC, [], false);

        $this->assertTrue($final, 'Approval tidak mencapai tahap final.');
        $this->assertSame(
            'Y',
            DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->value('Flag_Approval'),
        );
    }

    public function test_masa_berlaku_diturunkan_dari_jendela(): void
    {
        $no = $this->buatPermitApi();

        app(VisitorApprovalDecisionService::class)->approvePermit($no, self::PIC, [], false);

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $no)->first();

        $this->assertNull($detail->Id_Master_Durasi, 'Permit mitra tidak memilih baris master durasi.');
        // Label durasinya dihitung dari TANGGAL yang tersentuh, bukan dari
        // lama jamnya: satpam di gerbang membaca tanggal.
        $this->assertSame('1 Hari', $detail->Snapshot_Nama_Durasi);
        $this->assertSame('2026-09-10', substr((string) $detail->Tanggal_Mulai_Berlaku, 0, 10));
        $this->assertSame('2026-09-10', substr((string) $detail->Tanggal_Kadaluarsa, 0, 10));
    }

    public function test_proyeksi_membawa_jendela_identitas_dan_sumber(): void
    {
        $no = $this->buatPermitApi();

        app(VisitorApprovalDecisionService::class)->approvePermit($no, self::PIC, [], false);

        $pe = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->first();

        $this->assertNotNull($pe, 'Permit mitra tidak sampai ke proyeksi gerbang.');
        $this->assertSame('API', $pe->Sumber_Permit);
        $this->assertSame('Rudi Mitra', $pe->Nama_Pemegang);
        $this->assertSame('PT Mitra Sejahtera', $pe->Instansi_Pemegang);
        $this->assertSame('2026-09-10 08:00:00', substr((string) $pe->Waktu_Mulai_Berlaku, 0, 19));
        $this->assertSame('2026-09-10 17:00:00', substr((string) $pe->Waktu_Berakhir, 0, 19));
    }

    public function test_kolom_tanggal_lama_ikut_terisi_di_proyeksi(): void
    {
        // Invarian kontrak gerbang: aplikasi palang yang belum mengadopsi kolom
        // jam HANYA membaca Tanggal_*; membiarkannya kosong membuat permit tak
        // terlihat di gerbang tanpa satu pun galat.
        $no = $this->buatPermitApi();

        app(VisitorApprovalDecisionService::class)->approvePermit($no, self::PIC, [], false);

        $pe = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->first();

        $this->assertSame('2026-09-10', substr((string) $pe->Tanggal_Mulai_Berlaku, 0, 10));
        $this->assertSame('2026-09-10', substr((string) $pe->Tanggal_Kadaluarsa, 0, 10));
    }

    public function test_cermin_permit_hcis_masih_menuntut_durasi_bulanan(): void
    {
        $no = $this->buatPermitHcis();

        $this->expectException(\InvalidArgumentException::class);
        app(VisitorApprovalDecisionService::class)->approvePermit($no, self::PIC, [], false);
    }

    public function test_permit_api_tanpa_jendela_ditolak_saat_approval(): void
    {
        // Tidak seharusnya terjadi lewat endpoint (jendela wajib di validasi),
        // tetapi baris yang disisipkan tangan lain tidak boleh lolos jadi izin
        // tanpa masa berlaku — itu akses yang tidak pernah kedaluwarsa.
        $no = $this->buatPermit('API', null, null);

        $this->expectException(\DomainException::class);
        app(VisitorApprovalDecisionService::class)->approvePermit($no, self::PIC, [], false);
    }

    public function test_verifikator_menetapkan_jendela_lintas_hari(): void
    {
        // Tamu proyek yang bekerja beberapa hari tetap SATU permit, bukan satu
        // per hari. Sebelumnya mustahil: jendelanya dipaksa sehari.
        $no = $this->buatPermit('API', null, null);
        $mulai = Carbon::today()->setTime(8, 0);
        $akhir = Carbon::today()->addDays(3)->setTime(17, 0);

        app(VisitorApprovalDecisionService::class)->approvePermit($no, self::PIC, [
            'waktu_mulai_berlaku' => $mulai->format('Y-m-d H:i:s'),
            'waktu_berakhir' => $akhir->format('Y-m-d H:i:s'),
        ], false);

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $no)->first();

        $this->assertSame('4 Hari', $detail->Snapshot_Nama_Durasi);
        $this->assertSame($mulai->toDateString(), substr((string) $detail->Tanggal_Mulai_Berlaku, 0, 10));
        $this->assertSame($akhir->toDateString(), substr((string) $detail->Tanggal_Kadaluarsa, 0, 10));
        $this->assertSame(
            $akhir->format('Y-m-d H:i:s'),
            substr((string) $detail->Waktu_Berakhir, 0, 19),
        );
    }

    /**
     * Batas setengah-terbuka tidak boleh terbaca dua hari.
     *
     * Izin sehari berakhir pukul 00:00 hari BERIKUTNYA. Menghitung tanggal itu
     * sebagai hari yang tersentuh membuat izin sehari mengaku dua hari — di
     * label durasinya maupun di kolom kedaluwarsa yang dibaca gerbang.
     */
    public function test_izin_yang_berakhir_tepat_tengah_malam_tetap_sehari(): void
    {
        $no = $this->buatPermit('API', null, null);

        app(VisitorApprovalDecisionService::class)->approvePermit($no, self::PIC, [
            'waktu_mulai_berlaku' => Carbon::today()->format('Y-m-d H:i:s'),
            'waktu_berakhir' => Carbon::tomorrow()->format('Y-m-d H:i:s'),
        ], false);

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $no)->first();

        $this->assertSame('1 Hari', $detail->Snapshot_Nama_Durasi);
        $this->assertSame(
            Carbon::today()->toDateString(),
            substr((string) $detail->Tanggal_Kadaluarsa, 0, 10),
        );
    }

    public function test_jendela_yang_dimulai_tanggal_lewat_ditolak(): void
    {
        // Izin akses fisik yang berlaku surut tidak menjaga apa pun; ia hanya
        // melegalkan kunjungan yang terlanjur terjadi tanpa pemeriksaan.
        $no = $this->buatPermit('API', null, null);

        $this->expectException(\InvalidArgumentException::class);

        app(VisitorApprovalDecisionService::class)->approvePermit($no, self::PIC, [
            'waktu_mulai_berlaku' => Carbon::yesterday()->setTime(8, 0)->format('Y-m-d H:i:s'),
            'waktu_berakhir' => Carbon::today()->setTime(17, 0)->format('Y-m-d H:i:s'),
        ], false);
    }

    /**
     * Jam boleh mundur DI DALAM hari ini.
     *
     * Verifikator yang menyetujui sore hari tetap boleh menuliskan berlaku
     * sejak pagi — tamunya memang sudah menunggu di pos sejak pagi, dan
     * memaksanya menulis jam sekarang membuat catatan gerbang berbohong.
     */
    public function test_jam_mundur_di_dalam_hari_ini_tetap_diterima(): void
    {
        $no = $this->buatPermit('API', null, null);

        $final = app(VisitorApprovalDecisionService::class)->approvePermit($no, self::PIC, [
            'waktu_mulai_berlaku' => Carbon::today()->setTime(0, 1)->format('Y-m-d H:i:s'),
            'waktu_berakhir' => Carbon::tomorrow()->setTime(6, 0)->format('Y-m-d H:i:s'),
        ], false);

        $this->assertTrue($final);
    }

    public function test_waktu_berakhir_sebelum_mulai_ditolak(): void
    {
        $no = $this->buatPermit('API', null, null);

        $this->expectException(\InvalidArgumentException::class);

        app(VisitorApprovalDecisionService::class)->approvePermit($no, self::PIC, [
            'waktu_mulai_berlaku' => Carbon::today()->setTime(17, 0)->format('Y-m-d H:i:s'),
            'waktu_berakhir' => Carbon::today()->setTime(8, 0)->format('Y-m-d H:i:s'),
        ], false);
    }

    public function test_satu_ujung_jendela_saja_ditolak(): void
    {
        // Separuh jendela lebih berbahaya daripada tidak ada sama sekali: ia
        // terlihat terisi di layar, tetapi tidak menentukan apa pun.
        $no = $this->buatPermit('API', null, null);

        $this->expectException(\InvalidArgumentException::class);

        app(VisitorApprovalDecisionService::class)->approvePermit($no, self::PIC, [
            'waktu_mulai_berlaku' => Carbon::today()->setTime(8, 0)->format('Y-m-d H:i:s'),
        ], false);
    }

    /**
     * Jendela yang sudah tersimpan TIDAK ikut dihakimi aturan "tidak mundur".
     *
     * Permit turunan kunjungan membawa tanggal kunjungannya sejak diajukan.
     * Kalau aturan pilihan verifikator ikut dipasang pada nilai tersimpan,
     * pengajuan yang menunggu terlalu lama berubah menjadi mustahil disetujui —
     * persis kebuntuan yang dibongkar pekerjaan ini.
     */
    public function test_jendela_lama_yang_sudah_tersimpan_tetap_bisa_disetujui(): void
    {
        $no = $this->buatPermitApi();

        $final = app(VisitorApprovalDecisionService::class)->approvePermit($no, self::PIC, [], false);

        $this->assertTrue($final);
    }

    private function buatPermitApi(): string
    {
        return $this->buatPermit('API', '2026-09-10 08:00:00', '2026-09-10 17:00:00');
    }

    private function buatPermitHcis(): string
    {
        return $this->buatPermit('HCIS', null, null);
    }

    private function buatPermit(string $sumber, ?string $mulai, ?string $selesai): string
    {
        $nomor = 'PMT-APV-'.Str::upper(Str::random(8));
        $kategori = DB::table('N_EMI_VISITOR_Master_Kategori_Permit')->where('Nama_Kategori', 'COKLAT')->first();

        $this->assertNotNull($kategori, 'Master kategori COKLAT belum ada; DDL-2026-077 belum dijalankan.');

        // Hasil inspeksi kendaraan WAJIB ada sebelum permit COKLAT boleh
        // disetujui — izin akses fisik tidak boleh terbit tanpa seorang pun
        // pernah melihat kendaraannya. Yang ditiru di sini adalah bekas yang
        // ditinggalkan verifikator setelah mengisi formulir inspeksi.
        //
        // Id-nya diambil dari master NYATA, bukan angka karangan: kolomnya
        // dijaga foreign key, dan id yang tidak ada gagal dengan galat yang
        // sama sekali tidak menyinggung inspeksi.
        $model = DB::table('N_EMI_VISITOR_Master_Vehicle_Model')->where('Flag_Aktif', 'Y')->first();
        $warna = DB::table('N_EMI_VISITOR_Master_Vehicle_Color')->where('Flag_Aktif', 'Y')->first();

        $this->assertNotNull($model, 'Master model kendaraan kosong; fixture perlu ditinjau.');
        $this->assertNotNull($warna, 'Master warna kendaraan kosong; fixture perlu ditinjau.');

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            // Permit HCIS milik karyawan lain: sejak audit 27 Sep (M3) pengaju tak
            // boleh menyetujui permitnya sendiri, dan yang diuji di sini aturan durasi.
            'Kode_Karyawan_Pemohon' => $sumber === 'HCIS' ? 'H45' : self::PIC,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => null,
            'Sumber_Pengajuan' => $sumber,
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Updated_At' => now(),
            'Idempotency_Key' => 'APV-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'Id_Transaksi_Permit' => $id,
            'No_Transaksi' => $nomor,
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(10000, 99999),
            'Plat_Nomor_Suffix' => 'MT',
            'Id_Master_Kategori_Permit' => $kategori->Id_Kategori_Permit,
            'Snapshot_Kategori_Permit' => $kategori->Nama_Kategori,
            'Nama_Tamu' => 'Rudi Mitra',
            'Instansi_Tamu' => 'PT Mitra Sejahtera',
            'Waktu_Mulai_Berlaku' => $mulai,
            'Waktu_Berakhir' => $selesai,
            'Id_Master_Model' => $model->Id_Vehicle_Model,
            'Id_Master_Warna' => $warna->Id_Master_Color,
            'Tahun_Kendaraan' => 2020,
        ]);

        // Foto pemeriksaan yang diunggah verifikator: sejak 29 Sep 2026 wajib
        // sebelum permit tamu berkendaraan boleh disetujui.
        foreach (['FOTO_KENDARAAN', 'STNK', 'SIM'] as $jenis) {
            DB::table('N_EMI_VISITOR_Transaksi_Permit_Dokumen')->insert([
                'No_Transaksi' => $nomor,
                'Jenis_Dokumen' => $jenis,
                'Path_File_URL' => 'visitor/uji/'.strtolower($jenis).'.jpg',
            ]);
        }

        // approvePermit() hanya sampai ke penetapan masa berlaku bila
        // isApprovalComplete() true. Satu langkah approval yang sudah disetujui
        // membuat pemeriksaan itu terpenuhi; tanpa baris ini tesnya hijau karena
        // tidak pernah sampai ke kode yang diuji.
        DB::table('HRIS_Approval_Request')->insert([
            'No_Transaksi' => $nomor,
            // Kode_Karyawan NOT NULL — pemohonnya, bukan approvernya.
            'Kode_Karyawan' => self::PIC,
            'Request_Order_Flow' => 1,
            'Approver_Karyawan_Kode' => self::PIC,
            'Flag_Approval' => null,
            'created_at' => now(),
            'update_at' => now(),
        ]);

        return $nomor;
    }
}
