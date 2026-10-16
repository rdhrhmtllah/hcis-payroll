<?php

namespace Tests\Integration\VISITOR\Workflow\Approval;

use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use App\Services\VISITOR\Permit\VehiclePermit\PermitCoklatTamuService;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalDecisionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Permit COKLAT disetujui lewat INSPEKSI, bukan sekadar klik.
 *
 * Permit karyawan membawa data kendaraannya sejak pengajuan. Permit COKLAT
 * tidak punya sama sekali — tamu hanya menyebut plat dan "Mobil" — sehingga
 * merek, tahun, dan warna adalah hasil pemeriksaan verifikator di pos. Tanpa
 * penjaga, permitnya tetap terbit ke gerbang dengan ketiga kolom berisi '-',
 * dan tidak ada satu pun galat yang menyalakannya.
 *
 * Penjaganya sengaja di SERVER: jalur approval legacy dan pemanggilan langsung
 * ke service melewati layar sepenuhnya, jadi tombol yang di-disable saja
 * membiarkan izin akses fisik terbit tanpa seorang pun melihat kendaraannya.
 */
class InspeksiPermitCoklatTest extends TestCase
{
    use DatabaseTransactions;

    /** Kode karyawan NYATA di Web_HRIS development. */
    private const PIC = 'A1';

    public function test_checklist_inspeksi_ikut_lahir_saat_permit_coklat_diajukan(): void
    {
        // saveAnswers() meng-UPDATE baris ini, bukan menyisipkannya. Pengajuan
        // yang lahir tanpa snapshot tidak menampilkan satu pun pertanyaan di
        // meja verifikator, dan jawabannya tidak punya tempat untuk disimpan.
        $noPermit = $this->ajukanPermitCoklat();

        $this->assertGreaterThan(
            0,
            DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->where('No_Transaksi', $noPermit)->count(),
            'Permit COKLAT lahir tanpa satu pun pertanyaan inspeksi.',
        );
    }

    public function test_snapshot_menyimpan_teks_pertanyaannya_bukan_hanya_id(): void
    {
        // Teks pertanyaan di master boleh berubah; bukti inspeksi harus tetap
        // memperlihatkan pertanyaan yang benar-benar dijawab saat itu.
        $noPermit = $this->ajukanPermitCoklat();

        $baris = DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')
            ->where('No_Transaksi', $noPermit)
            ->pluck('Snapshot_Pertanyaan');

        // Jumlahnya ditagih lebih dulu: tanpa ini, snapshot yang tidak terbit
        // sama sekali membuat pemeriksaan "tidak ada yang kosong" di bawahnya
        // hijau tanpa membuktikan apa pun.
        $this->assertNotEmpty($baris, 'Tidak ada satu pun baris checklist untuk diperiksa.');
        $this->assertCount(
            0,
            $baris->filter(fn ($teks) => trim((string) $teks) === ''),
            'Ada baris checklist tanpa snapshot teks pertanyaannya.',
        );
    }

    public function test_approval_ditolak_selama_data_kendaraan_belum_diisi(): void
    {
        $noPermit = $this->ajukanPermitCoklat();
        $this->pasangApprover($noPermit);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Lengkapi hasil inspeksi kendaraan');

        app(VisitorApprovalDecisionService::class)->approvePermit(
            $noPermit,
            self::PIC,
            ['answers' => $this->jawabanLengkap($noPermit)],
            true,
        );
    }

    public function test_approval_ditolak_selama_checklist_belum_lengkap(): void
    {
        $noPermit = $this->ajukanPermitCoklat();
        $this->pasangApprover($noPermit);

        $this->expectExceptionMessage('Checklist pemeriksaan wajib dilengkapi');

        app(VisitorApprovalDecisionService::class)->approvePermit(
            $noPermit,
            self::PIC,
            $this->inspeksiLengkap(),
            true,
        );
    }

    public function test_hasil_inspeksi_tersimpan_dan_sampai_ke_gerbang(): void
    {
        $noPermit = $this->ajukanPermitCoklat();
        $this->pasangApprover($noPermit);

        $final = app(VisitorApprovalDecisionService::class)->approvePermit(
            $noPermit,
            self::PIC,
            array_merge($this->inspeksiLengkap(), ['answers' => $this->jawabanLengkap($noPermit)]),
            true,
        );

        $this->assertTrue($final, 'Approval tidak mencapai tahap final.');

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $noPermit)->first();

        $this->assertSame(3, (int) $detail->Id_Master_Model);
        $this->assertSame(1, (int) $detail->Id_Master_Warna);
        $this->assertSame(2021, (int) $detail->Tahun_Kendaraan);

        // Yang dibaca satpam adalah proyeksi, bukan tabel transaksi. Inspeksi
        // yang berhenti di tabel transaksi tidak menolong siapa pun di gerbang.
        $pe = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $noPermit)->first();

        $this->assertNotNull($pe, 'Permit tidak sampai ke proyeksi gerbang.');
        $this->assertSame('Toyota Avanza', $pe->Nama_Model_Kendaraan);
        $this->assertSame('Hitam', $pe->Warna_Kendaraan);
        $this->assertSame(2021, (int) $pe->Tahun_Kendaraan);
        // Tipe kendaraan naik dari teks bebas tamu ke master begitu modelnya
        // ditetapkan verifikator.
        $this->assertSame('Mobil Penumpang', $pe->Nama_Vehicle_Type);
    }

    /**
     * Keputusan inilah yang menutup inspeksi kedatangan tamunya.
     *
     * Tamu B2B tidak memesan slot — SHE mendatangi pos gerbang saat tamunya
     * tiba — sehingga tidak ada baris booking yang bisa ditandai seperti pada
     * permit karyawan. Agenda SHE karena itu sengaja TIDAK punya tombol
     * "Selesai": satu-satunya penutupnya adalah approval ini. Kalau
     * penyambungannya hilang, agenda hari itu tetap menampilkan baris tamu yang
     * tak pernah bisa ditutup, dan tak ada galat yang menyalakannya.
     */
    public function test_approval_menutup_inspeksi_kedatangan_tamunya(): void
    {
        $idPos = (int) DB::table(JadwalInspeksiService::T_POS)->insertGetId([
            'Nama_Pos' => 'Pos Uji Approval Tamu',
            'Keterangan_Lokasi' => 'Gerbang uji',
            'Flag_Aktif' => 'Y',
            'Created_By' => 'UJI',
        ]);
        config(['services.visitor.permit.pos_tamu_b2b' => $idPos]);

        $noPermit = $this->ajukanPermitCoklat();
        $this->pasangApprover($noPermit);

        $this->assertSame(
            0,
            DB::table('N_VISITOR_Inspeksi_Booking')->where('No_Transaksi_Permit', $noPermit)->count(),
            'Prasyarat gagal: permit tamu sudah punya baris inspeksi sebelum diputus.',
        );

        app(VisitorApprovalDecisionService::class)->approvePermit(
            $noPermit,
            self::PIC,
            array_merge($this->inspeksiLengkap(), ['answers' => $this->jawabanLengkap($noPermit)]),
            true,
        );

        $baris = DB::table('N_VISITOR_Inspeksi_Booking')->where('No_Transaksi_Permit', $noPermit)->get();

        $this->assertCount(1, $baris, 'Inspeksi kedatangan tamu tidak tercatat saat permitnya disetujui.');
        $this->assertSame(JadwalInspeksiService::SELESAI, $baris->first()->Status);
        $this->assertSame($idPos, (int) $baris->first()->Id_Pos_Inspeksi);
        $this->assertSame(
            0,
            DB::table('N_VISITOR_Inspeksi_Booking')
                ->where('No_Transaksi_Permit', $noPermit)
                ->where('Status', JadwalInspeksiService::TERJADWAL)
                ->count(),
            'Penutupan ini membuat baris TERJADWAL; ia akan memakan kursi slot permit karyawan.',
        );
    }

    /** Pos belum disetel tidak boleh menggagalkan approval siapa pun. */
    public function test_approval_tetap_berhasil_walau_pos_tamu_belum_disetel(): void
    {
        config(['services.visitor.permit.pos_tamu_b2b' => 0]);

        $noPermit = $this->ajukanPermitCoklat();
        $this->pasangApprover($noPermit);

        $final = app(VisitorApprovalDecisionService::class)->approvePermit(
            $noPermit,
            self::PIC,
            array_merge($this->inspeksiLengkap(), ['answers' => $this->jawabanLengkap($noPermit)]),
            true,
        );

        $this->assertTrue($final, 'Approval gagal hanya karena setelan pos tamu belum diisi.');
        $this->assertSame('Y', DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $noPermit)->value('Flag_Approval'));
    }

    public function test_tamu_pejalan_kaki_tidak_menuntut_inspeksi_apa_pun(): void
    {
        // Tidak ada kendaraan berarti tidak ada yang bisa diperiksa. Mewajibkan
        // inspeksi di sini membuat izin masuknya tidak akan pernah bisa
        // disetujui — dan sebabnya tidak akan terbaca di mana pun.
        $noPermit = $this->ajukanPermitCoklat(berplat: false);
        $this->pasangApprover($noPermit);

        $this->assertSame(
            0,
            DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->where('No_Transaksi', $noPermit)->count(),
            'Tamu pejalan kaki diberi checklist kendaraan.',
        );

        $final = app(VisitorApprovalDecisionService::class)->approvePermit($noPermit, self::PIC, [], true);

        $this->assertTrue($final);
        $this->assertNotNull(DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $noPermit)->first());
    }

    public function test_isian_kosong_tidak_menghapus_hasil_inspeksi_sebelumnya(): void
    {
        // Approval bertahap membuka formulir yang sama berkali-kali. Approver
        // kedua yang mengirimnya kosong tidak boleh menghapus catatan approver
        // pertama — kegagalannya berupa permit yang terbit tanpa bukti inspeksi
        // padahal inspeksinya benar-benar dilakukan.
        $noPermit = $this->ajukanPermitCoklat();

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
            ->where('No_Transaksi', $noPermit)
            ->update(['Id_Master_Model' => 3, 'Id_Master_Warna' => 1, 'Tahun_Kendaraan' => 2021]);

        $this->pasangApprover($noPermit);

        app(VisitorApprovalDecisionService::class)->approvePermit(
            $noPermit,
            self::PIC,
            ['answers' => $this->jawabanLengkap($noPermit)],
            true,
        );

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $noPermit)->first();

        $this->assertSame(3, (int) $detail->Id_Master_Model);
        $this->assertSame(2021, (int) $detail->Tahun_Kendaraan);
    }

    /** @return array<string,int> */
    public function test_approval_ditolak_selama_foto_pemeriksaan_belum_lengkap(): void
    {
        // Permintaan user 29 Sep 2026: foto pemeriksaan permit COKLAT wajib dan
        // tidak boleh dilewati — dijaga server, bukan hanya tombol Setujui.
        $noPermit = $this->ajukanPermitCoklat();
        $this->pasangApprover($noPermit, denganFoto: false);
        $this->lampirkanFoto($noPermit, ['STNK']);

        try {
            app(VisitorApprovalDecisionService::class)->approvePermit(
                $noPermit,
                self::PIC,
                array_merge($this->inspeksiLengkap(), ['answers' => $this->jawabanLengkap($noPermit)]),
                true,
            );
            $this->fail('Permit tamu tersetujui tanpa foto pemeriksaan lengkap.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Foto Kendaraan, SIM', $e->getMessage());
        }

        $this->assertNull(
            DB::table('HRIS_Approval_Request')->where('No_Transaksi', $noPermit)->value('Flag_Approval'),
            'Langkah approval tercatat walau fotonya ditolak.',
        );
    }

    public function test_tamu_pejalan_kaki_tidak_diwajibkan_foto(): void
    {
        $noPermit = $this->ajukanPermitCoklat(berplat: false);
        $this->pasangApprover($noPermit, denganFoto: false);

        $final = app(VisitorApprovalDecisionService::class)->approvePermit(
            $noPermit,
            self::PIC,
            ['answers' => $this->jawabanLengkap($noPermit)],
            true,
        );

        $this->assertTrue($final, 'Tamu tanpa kendaraan ikut dimintai foto kendaraan.');
    }

    private function inspeksiLengkap(): array
    {
        // 3 = Toyota Avanza (tipe 3 = Mobil Penumpang), 1 = Hitam.
        return ['id_master_model' => 3, 'id_master_warna' => 1, 'tahun_kendaraan' => 2021];
    }

    /** @return array<int, array{question_id:int, jawaban:string}> */
    private function jawabanLengkap(string $noPermit): array
    {
        return DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')
            ->where('No_Transaksi', $noPermit)
            ->pluck('Id_Master_Pertanyaan')
            ->map(fn ($id) => ['question_id' => (int) $id, 'jawaban' => 'YA'])
            ->all();
    }

    /**
     * Ganti alur bawaannya dengan satu langkah beratas-nama PIC uji.
     *
     * Alur nyata milik PIC ini berujung pada ROLE pool yang keanggotaannya
     * berbeda antar environment, sedangkan ApprovalService memvalidasi approver
     * sungguhan. Yang diuji berkas ini bukan wewenang siapa yang boleh
     * menyetujui, melainkan apa yang wajib ada sebelum permitnya terbit.
     */
    /**
     * Foto pemeriksaan yang diunggah verifikator di pos. Sejak 29 Sep 2026
     * ketiganya WAJIB sebelum permit tamu berkendaraan boleh disetujui.
     *
     * @param  list<string>  $jenis
     */
    private function lampirkanFoto(string $noPermit, array $jenis = ['FOTO_KENDARAAN', 'STNK', 'SIM']): void
    {
        foreach ($jenis as $j) {
            DB::table('N_EMI_VISITOR_Transaksi_Permit_Dokumen')->insert([
                'No_Transaksi' => $noPermit,
                'Jenis_Dokumen' => $j,
                'Path_File_URL' => 'visitor/uji/'.strtolower($j).'-'.Str::random(6).'.jpg',
            ]);
        }
    }

    /** Approver + foto pemeriksaan lengkap: keadaan normal meja verifikator. */
    private function pasangApprover(string $noPermit, bool $denganFoto = true): void
    {
        if ($denganFoto) {
            $this->lampirkanFoto($noPermit);
        }

        DB::table('HRIS_Approval_Request')->where('No_Transaksi', $noPermit)->delete();

        DB::table('HRIS_Approval_Request')->insert([
            'No_Transaksi' => $noPermit,
            'Kode_Karyawan' => self::PIC,
            'Request_Order_Flow' => 1,
            'Approver_Karyawan_Kode' => self::PIC,
            'Flag_Approval' => null,
            'created_at' => now(),
            'update_at' => now(),
        ]);
    }

    private function ajukanPermitCoklat(bool $berplat = true): string
    {
        $nomor = 'VSR-INS-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => StatusKunjunganTamu::SCHEDULED,
            'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => '2026-09-20',
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'INS-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Tamu Inspeksi',
            'Tipe_Tamu' => 'B2B',
            'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Meeting',
            'Jumlah_Tamu' => 1,
            'Jenis_Kendaraan' => $berplat ? 'Mobil' : 'Tidak Berkendara',
            'Plat_Nomor_Prefix' => $berplat ? 'BG' : null,
            'Plat_Nomor_Angka' => $berplat ? (string) random_int(10000, 99999) : null,
            'Plat_Nomor_Suffix' => $berplat ? 'IN' : null,
        ]);

        $noPermit = app(PermitCoklatTamuService::class)->sinkron($nomor);

        $this->assertNotNull($noPermit, 'Permit COKLAT tidak diajukan sama sekali.');

        return $noPermit;
    }
}
