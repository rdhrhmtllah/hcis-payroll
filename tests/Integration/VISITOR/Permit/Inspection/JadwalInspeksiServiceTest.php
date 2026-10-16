<?php

namespace Tests\Integration\VISITOR\Permit\Inspection;

use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Jadwal inspeksi permit karyawan (DDL-2026-123).
 *
 * Pola milik data dev dimatikan DI DALAM transaksi uji dan diganti satu pola
 * pos rekaan, supaya hitungan slot dan kuota tidak bergantung pada isi dev.
 * Waktu dibekukan pada Senin 21 Sep 2026 06:00.
 */
class JadwalInspeksiServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const POS = 'Pos Uji Inspeksi';

    private const SENIN = '2026-09-21';

    private JadwalInspeksiService $jadwal;

    private int $idPos;

    private int $idShift;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::SENIN.' 06:00'));
        $this->jadwal = app(JadwalInspeksiService::class);

        DB::table('N_VISITOR_Inspeksi_Pola')->update(['Flag_Aktif' => 'T']);
        DB::table('N_VISITOR_Inspeksi_Pengecualian')->update(['Flag_Aktif' => 'T']);
        DB::table(JadwalInspeksiService::T_HARI_SHIFT)->update(['Flag_Aktif' => 'Y']);

        $this->idShift = (int) DB::table(JadwalInspeksiService::T_SHIFT)->insertGetId([
            'Nama_Shift' => 'Shift Uji', 'Jam_Mulai' => '07:00', 'Jam_Selesai' => '15:00',
            'Urutan' => 9, 'Flag_Aktif' => 'Y', 'Created_By' => 'UJI',
        ]);

        $this->idPos = (int) DB::table(JadwalInspeksiService::T_POS)->insertGetId([
            'Nama_Pos' => self::POS,
            'Keterangan_Lokasi' => 'Gerbang uji',
            'Flag_Aktif' => 'Y',
            'Created_By' => 'UJI',
        ]);

        // Senin & Selasa 08:00–09:00, slot 30 menit, 2 kendaraan per slot.
        foreach ([1, 2] as $hari) {
            DB::table('N_VISITOR_Inspeksi_Pola')->insert([
                'Id_Pos_Inspeksi' => $this->idPos,
                'Id_Shift_Inspeksi' => $this->idShift,
                'Hari_Minggu' => $hari,
                'Jam_Mulai' => '08:00',
                'Jam_Selesai' => '09:00',
                'Durasi_Menit' => 30,
                'Kuota_Per_Slot' => 2,
                'Flag_Aktif' => 'Y',
                'Created_By' => 'UJI',
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_slot_dihitung_dari_pola_hari_itu_saja(): void
    {
        $slot = $this->jadwal->slotRentang(Carbon::parse(self::SENIN), Carbon::parse('2026-09-23'));

        $this->assertSame(
            ['2026-09-21 08:00', '2026-09-21 08:30', '2026-09-22 08:00', '2026-09-22 08:30'],
            array_column($slot, 'mulai'),
            'Rabu tidak berpola, jadi tidak boleh punya slot.',
        );
        $this->assertSame(['id_pos' => $this->idPos, 'pos' => self::POS, 'lokasi' => 'Gerbang uji', 'id_shift' => $this->idShift, 'shift' => 'Shift Uji', 'mulai' => '2026-09-21 08:00', 'selesai' => '2026-09-21 08:30', 'kuota' => 2, 'terisi' => 0, 'sisa' => 2], $slot[0]);
    }

    public function test_pengecualian_tutup_dan_tambah_diterapkan(): void
    {
        $this->pengecualian(['Tanggal' => self::SENIN, 'Id_Pos_Inspeksi' => null, 'Jenis' => 'TUTUP']);
        $this->pengecualian([
            'Tanggal' => '2026-09-23', 'Id_Pos_Inspeksi' => $this->idPos, 'Jenis' => 'TAMBAH',
            'Jam_Mulai' => '13:00', 'Jam_Selesai' => '14:00', 'Durasi_Menit' => 60, 'Kuota_Per_Slot' => 5,
        ]);

        $slot = $this->jadwal->slotRentang(Carbon::parse(self::SENIN), Carbon::parse('2026-09-23'));

        $this->assertSame(
            ['2026-09-22 08:00', '2026-09-22 08:30', '2026-09-23 13:00'],
            array_column($slot, 'mulai'),
            'TUTUP tanpa pos menutup semua pos; TAMBAH membuka slot di luar pola.',
        );
        $this->assertSame(5, end($slot)['kuota']);
    }

    public function test_pesan_menolak_slot_yang_penuh(): void
    {
        DB::transaction(function () {
            $this->jadwal->pesan('UJI-INSP-1', $this->idPos, self::SENIN.' 08:00', 'UJI');
            $this->jadwal->pesan('UJI-INSP-2', $this->idPos, self::SENIN.' 08:00', 'UJI');
        });

        $slot = collect($this->jadwal->slotTersedia(Carbon::parse(self::SENIN), Carbon::parse(self::SENIN)))
            ->pluck('mulai')->all();
        $this->assertNotContains(self::SENIN.' 08:00', $slot, 'Slot penuh masih ditawarkan.');

        $this->expectException(\DomainException::class);
        DB::transaction(fn () => $this->jadwal->pesan('UJI-INSP-3', $this->idPos, self::SENIN.' 08:00', 'UJI'));
    }

    public function test_pesan_menolak_slot_yang_tidak_ada_di_pola(): void
    {
        $this->expectException(\DomainException::class);
        DB::transaction(fn () => $this->jadwal->pesan('UJI-INSP-1', $this->idPos, self::SENIN.' 08:15', 'UJI'));
    }

    public function test_pesan_menolak_slot_yang_terlalu_dekat(): void
    {
        Carbon::setTestNow(Carbon::parse(self::SENIN.' 07:45'));

        $this->expectException(\DomainException::class);
        DB::transaction(fn () => $this->jadwal->pesan('UJI-INSP-1', $this->idPos, self::SENIN.' 08:00', 'UJI'));
    }

    public function test_pindah_paling_lambat_h_min_1_dan_maksimal_dua_kali(): void
    {
        // Permit sungguhan: pindah() kini mengunci & memeriksa headernya.
        $no = $this->permitMenunggu();
        DB::transaction(fn () => $this->jadwal->pesan($no, $this->idPos, '2026-09-22 08:00', 'UJI'));

        $this->jadwal->pindah($no, $this->idPos, '2026-09-22 08:30', 'UJI');
        $this->jadwal->pindah($no, $this->idPos, '2026-09-22 08:00', 'UJI');

        $ringkasan = $this->jadwal->ringkasan($no);
        $this->assertSame(2, $ringkasan['jadwal_ulang_terpakai']);
        $this->assertFalse($ringkasan['boleh_pindah']);

        try {
            $this->jadwal->pindah($no, $this->idPos, '2026-09-22 08:30', 'UJI');
            $this->fail('Pemindahan ketiga seharusnya ditolak.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('2 kali', $e->getMessage());
        }

        $this->assertSame(1, DB::table('N_VISITOR_Inspeksi_Booking')
            ->where('No_Transaksi_Permit', $no)->where('Status', 'TERJADWAL')->count());
    }

    public function test_petugas_memindah_tanpa_memakan_jatah_pemohon(): void
    {
        $no = $this->permitMenunggu();
        DB::transaction(fn () => $this->jadwal->pesan($no, $this->idPos, '2026-09-22 08:00', 'UJI'));

        $this->jadwal->pindahOlehPetugas($no, $this->idPos, '2026-09-22 08:30', 'SHE-UJI', 'Pos penuh');

        $lama = DB::table('N_VISITOR_Inspeksi_Booking')->where('No_Transaksi_Permit', $no)
            ->where('Status', JadwalInspeksiService::DIPINDAH_PETUGAS)->first();
        $this->assertNotNull($lama, 'Booking lama tidak ditandai dipindah petugas.');
        $this->assertSame('Pos penuh', $lama->Alasan);
        $this->assertSame('SHE-UJI', $lama->Updated_By);
        $this->assertSame(0, $this->jadwal->ringkasan($no)['jadwal_ulang_terpakai']);

        // Jatah pemohon utuh: dua kali pindah sendiri masih boleh.
        $this->jadwal->pindah($no, $this->idPos, '2026-09-22 08:00', 'UJI');
        $this->jadwal->pindah($no, $this->idPos, '2026-09-22 08:30', 'UJI');
        $this->assertSame(2, $this->jadwal->ringkasan($no)['jadwal_ulang_terpakai']);
    }

    public function test_ringkasan_menghitung_pemindahan_petugas_terpisah(): void
    {
        $no = $this->permitMenunggu();
        DB::transaction(fn () => $this->jadwal->pesan($no, $this->idPos, '2026-09-22 08:00', 'UJI'));
        $this->jadwal->pindahOlehPetugas($no, $this->idPos, '2026-09-22 08:30', 'SHE-UJI', 'Pos penuh');
        $this->jadwal->pindah($no, $this->idPos, '2026-09-22 08:00', 'UJI');

        $r = $this->jadwal->ringkasan($no);
        $this->assertSame(1, $r['jadwal_ulang_terpakai']);
        $this->assertSame(1, $r['dipindah_petugas']);
    }

    public function test_petugas_tidak_bisa_memindah_jadwal_permit_tamu(): void
    {
        // Permit COKLAT/mitra tidak memakai slot; memesan untuknya membuat booking
        // TERJADWAL yang tak pernah ditutup approve dan menahan kursi (audit M6).
        $no = $this->permitMenunggu();
        DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->update(['No_Transaksi_Tamu' => 'VSR-UJI-'.substr(uniqid(), -6)]);
        DB::table('N_VISITOR_Inspeksi_Booking')->insert([
            'No_Transaksi_Permit' => $no, 'Id_Pos_Inspeksi' => $this->idPos, 'Snapshot_Nama_Pos' => 'Pos',
            'Waktu_Mulai' => self::SENIN.' 08:00', 'Waktu_Selesai' => self::SENIN.' 08:30',
            'Status' => JadwalInspeksiService::TIDAK_HADIR, 'Created_At' => now(), 'Created_By' => 'UJI',
        ]);

        $this->expectException(\DomainException::class);
        $this->jadwal->pindahOlehPetugas($no, $this->idPos, '2026-09-22 08:00', 'SHE-UJI', 'x');
    }

    public function test_petugas_boleh_memindah_pada_hari_h(): void
    {
        $no = $this->permitMenunggu();
        DB::transaction(fn () => $this->jadwal->pesan($no, $this->idPos, self::SENIN.' 08:00', 'UJI'));

        $baru = $this->jadwal->pindahOlehPetugas($no, $this->idPos, '2026-09-22 08:00', 'SHE-UJI', 'Petugas berhalangan');

        $this->assertSame('2026-09-22 08:00', Carbon::parse($baru->Waktu_Mulai)->format('Y-m-d H:i'));
    }

    public function test_petugas_tidak_bisa_memindah_permit_yang_sudah_diputus(): void
    {
        $no = $this->permitMenunggu();
        DB::transaction(fn () => $this->jadwal->pesan($no, $this->idPos, '2026-09-22 08:00', 'UJI'));
        DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->update(['Flag_Approval' => 'Y']);

        $this->expectException(\DomainException::class);
        $this->jadwal->pindahOlehPetugas($no, $this->idPos, '2026-09-22 08:30', 'SHE-UJI', 'x');
    }

    public function test_alasan_panjang_muat_di_kolom_booking(): void
    {
        $no = $this->permitMenunggu();
        DB::transaction(fn () => $this->jadwal->pesan($no, $this->idPos, '2026-09-22 08:00', 'UJI'));

        $this->jadwal->pindahOlehPetugas($no, $this->idPos, '2026-09-22 08:30', 'SHE-UJI', str_repeat('a', 255));

        $alasan = DB::table('N_VISITOR_Inspeksi_Booking')->where('No_Transaksi_Permit', $no)
            ->where('Status', JadwalInspeksiService::DIPINDAH_PETUGAS)->value('Alasan');
        $this->assertSame(JadwalInspeksiService::MAKS_ALASAN, mb_strlen($alasan));
    }

    public function test_petugas_wajib_memberi_alasan(): void
    {
        $no = $this->permitMenunggu();
        DB::transaction(fn () => $this->jadwal->pesan($no, $this->idPos, '2026-09-22 08:00', 'UJI'));

        $this->expectExceptionMessage('Alasan pemindahan jadwal wajib diisi.');
        $this->jadwal->pindahOlehPetugas($no, $this->idPos, '2026-09-22 08:30', 'SHE-UJI', '   ');
    }

    public function test_pemohon_tidak_bisa_memindah_jadwal_permit_yang_sudah_diputus(): void
    {
        // Penjaga di DALAM transaksi pindah(), bukan hanya di controller: berlomba
        // dengan pengguguran di detik yang sama, permit gugur dapat booking baru.
        $no = $this->permitMenunggu();
        DB::transaction(fn () => $this->jadwal->pesan($no, $this->idPos, '2026-09-22 08:00', 'UJI'));
        DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->update(['Flag_Approval' => 'T']);

        $this->expectException(\DomainException::class);
        $this->jadwal->pindah($no, $this->idPos, '2026-09-22 08:30', 'UJI');
    }

    public function test_pindah_pada_hari_h_ditolak(): void
    {
        DB::transaction(fn () => $this->jadwal->pesan('UJI-INSP-1', $this->idPos, self::SENIN.' 08:00', 'UJI'));

        $this->assertFalse($this->jadwal->ringkasan('UJI-INSP-1')['boleh_pindah']);
        $this->expectException(\DomainException::class);
        $this->jadwal->pindah('UJI-INSP-1', $this->idPos, '2026-09-22 08:00', 'UJI');
    }

    public function test_pemohon_hanya_bisa_membatalkan_paling_lambat_h_min_1(): void
    {
        $this->assertTrue($this->jadwal->bolehDibatalkanPemohon('UJI-INSP-1'), 'Permit tanpa jadwal tidak punya hari H.');

        DB::transaction(fn () => $this->jadwal->pesan('UJI-INSP-1', $this->idPos, '2026-09-22 08:00', 'UJI'));
        $this->assertTrue($this->jadwal->bolehDibatalkanPemohon('UJI-INSP-1'));
        $this->assertTrue($this->jadwal->ringkasan('UJI-INSP-1')['boleh_batal']);

        // Hari H: tertutup sejak pagi, juga setelah jam inspeksinya lewat.
        foreach (['2026-09-22 00:01', '2026-09-22 10:00'] as $saat) {
            Carbon::setTestNow(Carbon::parse($saat));
            $this->assertFalse($this->jadwal->bolehDibatalkanPemohon('UJI-INSP-1'), "Pembatalan dibuka pada {$saat}.");
            $this->assertFalse($this->jadwal->ringkasan('UJI-INSP-1')['boleh_batal']);
        }

        // Hari H lewat tanpa dicatat: pemohon tidak boleh ikut terkunci.
        Carbon::setTestNow(Carbon::parse('2026-09-23 08:00'));
        $this->assertTrue($this->jadwal->bolehDibatalkanPemohon('UJI-INSP-1'));
    }

    public function test_tidak_hadir_baru_bisa_dicatat_setelah_jamnya_mulai(): void
    {
        DB::transaction(fn () => $this->jadwal->pesan('UJI-INSP-1', $this->idPos, self::SENIN.' 08:00', 'UJI'));

        try {
            $this->jadwal->tidakHadir('UJI-INSP-1', 'SHE');
            $this->fail('Tidak hadir sebelum jamnya seharusnya ditolak.');
        } catch (\DomainException) {
            // sesuai harapan
        }

        Carbon::setTestNow(Carbon::parse(self::SENIN.' 08:05'));
        $hasil = $this->jadwal->tidakHadir('UJI-INSP-1', 'SHE');

        $this->assertSame(['gugur' => false, 'jumlah' => 1], $hasil);
        $ringkasan = $this->jadwal->ringkasan('UJI-INSP-1');
        $this->assertTrue($ringkasan['wajib_pilih_ulang']);
        $this->assertTrue($ringkasan['boleh_pindah'], 'Sesudah tidak hadir pemohon WAJIB bisa memilih ulang.');
    }

    public function test_tidak_hadir_kedua_menggugurkan_permit(): void
    {
        $nomor = $this->permitMenunggu();
        DB::transaction(fn () => $this->jadwal->pesan($nomor, $this->idPos, self::SENIN.' 08:00', 'UJI'));

        Carbon::setTestNow(Carbon::parse(self::SENIN.' 08:05'));
        $this->jadwal->tidakHadir($nomor, 'SHE');

        // Tidak hadir tidak memakan jatah jadwal ulang: pilih ulang tetap boleh.
        $this->jadwal->pindah($nomor, $this->idPos, '2026-09-22 08:00', $nomor);

        Carbon::setTestNow(Carbon::parse('2026-09-22 08:10'));
        $hasil = $this->jadwal->tidakHadir($nomor, 'SHE');

        $this->assertSame(['gugur' => true, 'jumlah' => 2], $hasil);
        $this->assertSame('T', DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $nomor)->value('Flag_Approval'));
        // Permit gugur: akses palang sementaranya berakhir saat itu juga, bukan
        // menunggu jam slot habis (audit, Low).
        $this->assertNotSame('AKTIF', DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->value('Status_Efektif'));

        $ringkasan = $this->jadwal->ringkasan($nomor);
        $this->assertTrue($ringkasan['gugur']);
        $this->assertFalse($ringkasan['boleh_pindah'], 'Permit gugur masih menawarkan pilih jadwal.');
        $this->assertFalse($ringkasan['wajib_pilih_ulang']);
    }

    public function test_approve_setelah_jam_inspeksi_menandai_selesai(): void
    {
        DB::transaction(fn () => $this->jadwal->pesan('UJI-INSP-1', $this->idPos, self::SENIN.' 08:00', 'UJI'));

        Carbon::setTestNow(Carbon::parse(self::SENIN.' 08:20'));
        DB::transaction(fn () => $this->jadwal->tandaiSelesaiSaatApprove('UJI-INSP-1', 'APPROVER'));

        $ringkasan = $this->jadwal->ringkasan('UJI-INSP-1');
        $this->assertSame('SELESAI', $ringkasan['status']);
        $this->assertSame('APPROVER', $ringkasan['diinspeksi_oleh']);
    }

    /**
     * Permintaan user 29 Sep 2026: pemohon yang datang lebih awal boleh langsung
     * diinspeksi dan disetujui — tidak ditolak "jadwal belum tiba". Kursi slot
     * yang tidak jadi dipakai ikut lepas untuk pemohon lain.
     */
    public function test_approve_sebelum_jam_inspeksi_diizinkan_dan_melepas_kursi(): void
    {
        DB::transaction(fn () => $this->jadwal->pesan('UJI-INSP-1', $this->idPos, self::SENIN.' 08:00', 'UJI'));

        DB::transaction(fn () => $this->jadwal->tandaiSelesaiSaatApprove('UJI-INSP-1', 'APPROVER'));

        $ringkasan = $this->jadwal->ringkasan('UJI-INSP-1');
        $this->assertSame('SELESAI', $ringkasan['status']);
        $this->assertSame('APPROVER', $ringkasan['diinspeksi_oleh']);

        $slot = collect($this->jadwal->slotRentang(Carbon::parse(self::SENIN), Carbon::parse(self::SENIN)))
            ->firstWhere('mulai', self::SENIN.' 08:00');
        $this->assertSame($slot['kuota'] ?? $slot['sisa'], $slot['sisa'], 'Kursi slot yang tidak jadi dipakai tidak dilepas.');
    }

    public function test_approve_permit_tanpa_booking_tidak_disentuh(): void
    {
        // Pengajuan sebelum fitur ini tidak punya booking dan tetap bisa diputus.
        DB::transaction(fn () => $this->jadwal->tandaiSelesaiSaatApprove('UJI-INSP-LAMA', 'APPROVER'));

        $this->assertNull($this->jadwal->ringkasan('UJI-INSP-LAMA'));
    }

    public function test_batalkan_melepas_kursi_slot(): void
    {
        DB::transaction(function () {
            $this->jadwal->pesan('UJI-INSP-1', $this->idPos, self::SENIN.' 08:00', 'UJI');
            $this->jadwal->pesan('UJI-INSP-2', $this->idPos, self::SENIN.' 08:00', 'UJI');
        });

        $this->jadwal->batalkan('UJI-INSP-1', 'Ditolak verifikator.', 'UJI');

        $slot = collect($this->jadwal->slotRentang(Carbon::parse(self::SENIN), Carbon::parse(self::SENIN)))
            ->firstWhere('mulai', self::SENIN.' 08:00');
        $this->assertSame(1, $slot['sisa']);
        $this->assertSame('DIBATALKAN', $this->jadwal->ringkasan('UJI-INSP-1')['status']);
    }

    private function pengecualian(array $isi): void
    {
        DB::table('N_VISITOR_Inspeksi_Pengecualian')->insert($isi + ['Flag_Aktif' => 'Y', 'Created_By' => 'UJI']);
    }

    /** Permit menunggu keputusan, cukup untuk membuktikan pengguguran. */
    private function permitMenunggu(): string
    {
        $contoh = DB::table('N_EMI_VISITOR_Transaksi_Permit')->whereNull('No_Transaksi_Tamu')->orderByDesc('Id_Transaksi_Permit')->first();
        $this->assertNotNull($contoh, 'Butuh minimal satu permit karyawan di dev sebagai cetakan kolom wajib.');

        $baris = (array) $contoh;
        unset($baris['Id_Transaksi_Permit']);
        $nomor = 'UJI-INSP-'.substr(uniqid(), -6);
        $baris['No_Transaksi'] = $nomor;
        $baris['Flag_Approval'] = null;
        $baris['Flag_Aktif'] = 'Y';
        // Kunci idempotensi unik per pemohon; salinan tak boleh bertabrakan.
        $baris['Idempotency_Key'] = null;
        // Kode kartu berindeks unik tersaring (DDL-2026-127).
        $baris['Kode_Kartu'] = null;

        DB::table('N_EMI_VISITOR_Transaksi_Permit')->insert($baris);

        return $nomor;
    }

    public function test_batas_approval_digeser_melewati_slot_inspeksi(): void
    {
        // Permit karyawan baru bisa disetujui SESUDAH inspeksinya. Batas approval
        // yang jatuh sebelum slot membuatnya mustahil disetujui (audit ulang 28 Sep).
        $no = $this->permitBerbatasApproval(self::SENIN.' 23:59:59');
        $selasa = Carbon::parse(self::SENIN)->addDay()->toDateString();

        DB::transaction(fn () => $this->jadwal->pesan($no, $this->idPos, $selasa.' 08:00', 'UJI'));

        // Akhir hari H+1 (Rabu): bawaan hari_tenggang_putus = 1.
        $this->assertSame('2026-09-23 23:59:59', $this->batasApproval($no));
    }

    public function test_batas_approval_dimajukan_ke_h_plus_1_slot_yang_dekat(): void
    {
        // Batas lama jauh sesudah slotnya (Selasa): pengajuan yang jadwalnya
        // lewat tidak boleh menggantung selama itu.
        $no = $this->permitBerbatasApproval('2026-09-30 23:59:59');

        DB::transaction(fn () => $this->jadwal->pesan($no, $this->idPos, '2026-09-22 08:00', 'UJI'));
        $this->assertSame('2026-09-23 23:59:59', $this->batasApproval($no));

        // Jadwal dipindah ke Senin depan: batasnya ikut.
        $this->jadwal->pindah($no, $this->idPos, '2026-09-28 08:00', 'UJI');
        $this->assertSame('2026-09-29 23:59:59', $this->batasApproval($no));
    }

    public function test_hari_tenggang_putus_bisa_disetel(): void
    {
        config(['services.visitor.permit.hari_tenggang_putus' => 0]);
        $no = $this->permitBerbatasApproval(self::SENIN.' 23:59:59');

        DB::transaction(fn () => $this->jadwal->pesan($no, $this->idPos, '2026-09-22 08:00', 'UJI'));

        $this->assertSame('2026-09-22 23:59:59', $this->batasApproval($no));
    }

    public function test_pengajuan_kedaluwarsa_tinggal_arsip(): void
    {
        $no = $this->permitBerbatasApproval(self::SENIN.' 23:59:59');
        DB::transaction(fn () => $this->jadwal->pesan($no, $this->idPos, '2026-09-22 08:00', 'UJI'));
        // Tidak hadir lalu batasnya lewat: dulu "pilih jadwal baru" tetap
        // ditawarkan, dan memesan menyetel ulang batasnya — permit hidup lagi.
        DB::table('N_VISITOR_Inspeksi_Booking')->where('No_Transaksi_Permit', $no)->update(['Status' => 'TIDAK_HADIR']);
        Carbon::setTestNow(Carbon::parse('2026-09-24 08:00'));

        $ringkasan = $this->jadwal->ringkasan($no);
        $this->assertTrue($ringkasan['kedaluwarsa']);
        $this->assertFalse($ringkasan['boleh_pindah']);
        $this->assertFalse($ringkasan['wajib_pilih_ulang']);
        $this->assertFalse($ringkasan['boleh_batal']);

        try {
            $this->jadwal->pindah($no, $this->idPos, '2026-09-28 08:00', 'UJI');
            $this->fail('Pengajuan kedaluwarsa masih bisa memesan jadwal baru.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('sudah lewat', $e->getMessage());
        }

        $this->assertSame('2026-09-23 23:59:59', $this->batasApproval($no), 'Batas approval pengajuan kedaluwarsa disetel ulang.');
        $this->assertSame(0, DB::table('N_VISITOR_Inspeksi_Booking')
            ->where('No_Transaksi_Permit', $no)->where('Status', 'TERJADWAL')->count());
    }

    private function permitBerbatasApproval(string $expiredAt): string
    {
        $no = $this->permitMenunggu();
        DB::table('HRIS_Approval_Request')->insert([
            'Kode_Karyawan' => 'A1', 'No_Transaksi' => $no, 'Flag_Approval' => null, 'Request_Order_Flow' => 1,
            'Expired_At' => Carbon::parse($expiredAt), 'created_at' => now(), 'update_at' => now(),
        ]);

        return $no;
    }

    private function batasApproval(string $no): string
    {
        return Carbon::parse(DB::table('HRIS_Approval_Request')->where('No_Transaksi', $no)->value('Expired_At'))
            ->format('Y-m-d H:i:s');
    }

    public function test_pos_nonaktif_tidak_membuka_slot_dan_tidak_bisa_dipesan(): void
    {
        DB::table(JadwalInspeksiService::T_POS)->where('Id_Pos_Inspeksi', $this->idPos)->update(['Flag_Aktif' => 'T']);

        $this->assertSame([], $this->jadwal->slotRentang(Carbon::parse(self::SENIN), Carbon::parse('2026-09-22')));

        $this->expectException(\DomainException::class);
        DB::transaction(fn () => $this->jadwal->pesan('UJI-INSP-1', $this->idPos, self::SENIN.' 08:00', 'UJI'));
    }

    public function test_booking_menyimpan_salinan_nama_dan_lokasi_terbaca_dari_master(): void
    {
        DB::transaction(fn () => $this->jadwal->pesan('UJI-INSP-1', $this->idPos, '2026-09-22 08:00', 'UJI'));

        $this->assertSame(self::POS, DB::table('N_VISITOR_Inspeksi_Booking')
            ->where('No_Transaksi_Permit', 'UJI-INSP-1')->value('Snapshot_Nama_Pos'));

        $ringkasan = $this->jadwal->ringkasan('UJI-INSP-1');
        $this->assertSame($this->idPos, $ringkasan['id_pos']);
        $this->assertSame(self::POS, $ringkasan['pos']);
        $this->assertSame('Gerbang uji', $ringkasan['lokasi']);
    }

    public function test_pos_berbeda_berkuota_sendiri_walau_jamnya_sama(): void
    {
        // Kuota dihitung per Id pos: dua pos di jam yang sama tidak saling
        // menghabiskan kursi — dulu nama teks yang beda huruf besar kecilnya
        // pun bisa memecah atau menggabungkan hitungan tanpa sengaja.
        $posLain = (int) DB::table(JadwalInspeksiService::T_POS)->insertGetId([
            'Nama_Pos' => self::POS.' B', 'Flag_Aktif' => 'Y', 'Created_By' => 'UJI',
        ]);
        DB::table('N_VISITOR_Inspeksi_Pola')->insert([
            'Id_Pos_Inspeksi' => $posLain, 'Id_Shift_Inspeksi' => $this->idShift, 'Hari_Minggu' => 1, 'Jam_Mulai' => '08:00', 'Jam_Selesai' => '09:00',
            'Durasi_Menit' => 30, 'Kuota_Per_Slot' => 1, 'Flag_Aktif' => 'Y', 'Created_By' => 'UJI',
        ]);

        DB::transaction(function () {
            $this->jadwal->pesan('UJI-INSP-1', $this->idPos, self::SENIN.' 08:00', 'UJI');
            $this->jadwal->pesan('UJI-INSP-2', $this->idPos, self::SENIN.' 08:00', 'UJI');
        });
        DB::transaction(fn () => $this->jadwal->pesan('UJI-INSP-3', $posLain, self::SENIN.' 08:00', 'UJI'));

        $slot = collect($this->jadwal->slotRentang(Carbon::parse(self::SENIN), Carbon::parse(self::SENIN)))
            ->where('mulai', self::SENIN.' 08:00')->keyBy('id_pos');
        $this->assertSame(0, $slot[$this->idPos]['sisa']);
        $this->assertSame(0, $slot[$posLain]['sisa']);
        $this->assertSame(2, $slot[$this->idPos]['terisi']);
        $this->assertSame(1, $slot[$posLain]['terisi']);
    }

    public function test_shift_nonaktif_menghentikan_seluruh_jadwalnya(): void
    {
        DB::table(JadwalInspeksiService::T_SHIFT)->where('Id_Shift_Inspeksi', $this->idShift)->update(['Flag_Aktif' => 'T']);

        $this->assertSame([], $this->jadwal->slotRentang(Carbon::parse(self::SENIN), Carbon::parse('2026-09-22')));

        $this->expectException(\DomainException::class);
        DB::transaction(fn () => $this->jadwal->pesan('UJI-INSP-1', $this->idPos, self::SENIN.' 08:00', 'UJI'));
    }

    public function test_booking_menyimpan_shift_asal_slotnya(): void
    {
        DB::transaction(fn () => $this->jadwal->pesan('UJI-INSP-1', $this->idPos, '2026-09-22 08:00', 'UJI'));

        $this->assertSame($this->idShift, (int) DB::table('N_VISITOR_Inspeksi_Booking')
            ->where('No_Transaksi_Permit', 'UJI-INSP-1')->value('Id_Shift_Inspeksi'));
        $this->assertSame('Shift Uji', $this->jadwal->ringkasan('UJI-INSP-1')['shift']);
    }

    public function test_jadwal_menyeberang_tengah_malam_milik_hari_mulainya(): void
    {
        // Shift 3 (23:00-07:00): jadwal 23:00-01:00 hari Senin menghasilkan slot
        // 23:00, 23:30, 00:00, 00:30 — dua terakhir jatuh pada tanggal Selasa,
        // tetapi lahir dari pola HARI SENIN. Tanpa penanganan ini, jam selesai
        // yang lebih kecil dari jam mulai menghasilkan NOL slot tanpa galat.
        $malam = (int) DB::table(JadwalInspeksiService::T_SHIFT)->insertGetId([
            'Nama_Shift' => 'Shift Malam Uji', 'Jam_Mulai' => '23:00', 'Jam_Selesai' => '07:00',
            'Urutan' => 10, 'Flag_Aktif' => 'Y', 'Created_By' => 'UJI',
        ]);
        DB::table('N_VISITOR_Inspeksi_Pola')->where('Id_Pos_Inspeksi', $this->idPos)->update(['Flag_Aktif' => 'T']);
        DB::table('N_VISITOR_Inspeksi_Pola')->insert([
            'Id_Pos_Inspeksi' => $this->idPos, 'Id_Shift_Inspeksi' => $malam, 'Hari_Minggu' => 1,
            'Jam_Mulai' => '23:00', 'Jam_Selesai' => '01:00', 'Durasi_Menit' => 30, 'Kuota_Per_Slot' => 1,
            'Flag_Aktif' => 'Y', 'Created_By' => 'UJI',
        ]);

        $slot = $this->jadwal->slotRentang(Carbon::parse(self::SENIN), Carbon::parse(self::SENIN));

        $this->assertSame(
            ['2026-09-21 23:00', '2026-09-21 23:30', '2026-09-22 00:00', '2026-09-22 00:30'],
            array_column($slot, 'mulai'),
        );
        $this->assertSame('Shift Malam Uji', $slot[0]['shift']);

        // Slot dini hari itu tetap bisa dipesan, dan kuotanya ikut terhitung.
        DB::transaction(fn () => $this->jadwal->pesan('UJI-INSP-1', $this->idPos, '2026-09-22 00:00', 'UJI'));
        $sesudah = collect($this->jadwal->slotRentang(Carbon::parse(self::SENIN), Carbon::parse(self::SENIN)))
            ->firstWhere('mulai', '2026-09-22 00:00');
        $this->assertSame(0, $sesudah['sisa']);
    }

    public function test_hari_dinonaktifkan_menutup_seluruh_shift_hari_itu(): void
    {
        $this->matikan(1, null);

        $slot = $this->jadwal->slotRentang(Carbon::parse(self::SENIN), Carbon::parse('2026-09-22'));

        $this->assertSame(['2026-09-22 08:00', '2026-09-22 08:30'], array_column($slot, 'mulai'),
            'Senin harus kosong seluruhnya, Selasa tidak terpengaruh.');
    }

    public function test_shift_bisa_dimatikan_hanya_pada_hari_tertentu(): void
    {
        $this->matikan(1, $this->idShift);

        $slot = $this->jadwal->slotRentang(Carbon::parse(self::SENIN), Carbon::parse('2026-09-22'));

        $this->assertSame(['2026-09-22 08:00', '2026-09-22 08:30'], array_column($slot, 'mulai'),
            'Shift dimatikan di hari Senin saja; Selasa tetap membuka slot.');
        $this->assertSame([], array_filter($slot, fn ($s) => str_starts_with($s['mulai'], self::SENIN)));
    }

    public function test_slot_tambahan_tetap_dibuka_walau_harinya_dimatikan(): void
    {
        // Pengecualian TAMBAH adalah keputusan SHE untuk tanggal itu sendiri;
        // sakelar mingguan tidak boleh menelannya.
        $this->matikan(1, null);
        $this->pengecualian([
            'Tanggal' => self::SENIN, 'Id_Pos_Inspeksi' => $this->idPos, 'Jenis' => 'TAMBAH',
            'Jam_Mulai' => '13:00', 'Jam_Selesai' => '14:00', 'Durasi_Menit' => 60, 'Kuota_Per_Slot' => 3,
        ]);

        $slot = collect($this->jadwal->slotRentang(Carbon::parse(self::SENIN), Carbon::parse(self::SENIN)));

        $this->assertSame([self::SENIN.' 13:00'], $slot->pluck('mulai')->all());
        $this->assertSame(3, $slot->first()['kuota']);
    }

    public function test_hari_dihidupkan_lagi_memulihkan_jadwalnya(): void
    {
        // Sakelar tidak menghapus jadwal: menghidupkan kembali harus
        // mengembalikan slot yang sama persis.
        $sebelum = $this->jadwal->slotRentang(Carbon::parse(self::SENIN), Carbon::parse(self::SENIN));
        $this->matikan(1, null);
        $this->assertSame([], $this->jadwal->slotRentang(Carbon::parse(self::SENIN), Carbon::parse(self::SENIN)));

        DB::table(JadwalInspeksiService::T_HARI_SHIFT)
            ->where('Hari_Minggu', 1)->whereNull('Id_Shift_Inspeksi')
            ->update(['Flag_Aktif' => 'Y']);

        $this->assertEquals($sebelum, $this->jadwal->slotRentang(Carbon::parse(self::SENIN), Carbon::parse(self::SENIN)));
    }

    private function matikan(int $hari, ?int $idShift): void
    {
        DB::table(JadwalInspeksiService::T_HARI_SHIFT)->insert([
            'Hari_Minggu' => $hari,
            'Id_Shift_Inspeksi' => $idShift,
            'Flag_Aktif' => 'T',
            'Created_By' => 'UJI',
        ]);
    }

    public function test_jadwal_lewat_tanpa_dicatat_tidak_mengubah_apa_pun_sendiri(): void
    {
        // Keputusan tetap milik manusia: tidak ada penjadwal yang menandai
        // tidak hadir, menggugurkan pengajuan, atau menutup bookingnya.
        DB::transaction(fn () => $this->jadwal->pesan('UJI-INSP-1', $this->idPos, self::SENIN.' 08:00', 'UJI'));
        Carbon::setTestNow(Carbon::parse('2026-09-23 10:00'));

        $ringkasan = $this->jadwal->ringkasan('UJI-INSP-1');

        $this->assertSame('TERJADWAL', $ringkasan['status'], 'Status booking berubah sendiri tanpa ada yang mencatat.');
        $this->assertTrue($ringkasan['lewat_jadwal']);
        $this->assertSame(0, $ringkasan['tidak_hadir']);
        $this->assertFalse($ringkasan['gugur']);
        // Pintunya dibuka selama jatahnya ada: pemohon tidak ikut terkunci menunggu orang lain.
        $this->assertTrue($ringkasan['boleh_pindah']);
    }

    public function test_pindah_karena_lewat_jadwal_memakan_jatah_jadwal_ulang(): void
    {
        // Permit sungguhan: pindah() kini mengunci & memeriksa headernya.
        $no = $this->permitMenunggu();
        DB::transaction(fn () => $this->jadwal->pesan($no, $this->idPos, self::SENIN.' 08:00', 'UJI'));
        Carbon::setTestNow(Carbon::parse('2026-09-23 10:00'));

        $this->jadwal->pindah($no, $this->idPos, '2026-09-28 08:00', 'UJI');

        $ringkasan = $this->jadwal->ringkasan($no);
        // Dulu gratis: tidak datang berulang kali, selama SHE tak mencatat,
        // membuat permitnya bisa dipindah terus dan tak pernah kedaluwarsa.
        $this->assertSame(1, $ringkasan['jadwal_ulang_terpakai']);
        $this->assertSame('2026-09-28 08:00', $ringkasan['mulai']);
        $this->assertFalse($ringkasan['lewat_jadwal']);

        // Booking lamanya ditutup sebagai KEDALUWARSA, bukan TIDAK_HADIR.
        $lama = DB::table('N_VISITOR_Inspeksi_Booking')
            ->where('No_Transaksi_Permit', $no)
            ->where('Waktu_Mulai', self::SENIN.' 08:00:00')
            ->first();
        $this->assertSame(JadwalInspeksiService::KEDALUWARSA, $lama->Status);
        $this->assertSame(0, DB::table('N_VISITOR_Inspeksi_Booking')
            ->where('No_Transaksi_Permit', $no)
            ->where('Status', JadwalInspeksiService::TIDAK_HADIR)->count());

        // Slot lama dilepas: kursinya kembali bisa dipakai orang lain.
        $slot = collect($this->jadwal->slotRentang(Carbon::parse(self::SENIN), Carbon::parse(self::SENIN)))
            ->firstWhere('mulai', self::SENIN.' 08:00');
        $this->assertSame(2, $slot['sisa']);

        // Terlewat lagi: jatah kedua terpakai, sesudahnya pintu ditutup.
        Carbon::setTestNow(Carbon::parse('2026-09-29 10:00'));
        $this->jadwal->pindah($no, $this->idPos, '2026-10-05 08:00', 'UJI');
        $this->assertSame(2, $this->jadwal->ringkasan($no)['jadwal_ulang_terpakai']);

        Carbon::setTestNow(Carbon::parse('2026-10-06 10:00'));
        $ringkasan = $this->jadwal->ringkasan($no);
        $this->assertTrue($ringkasan['lewat_jadwal']);
        $this->assertFalse($ringkasan['boleh_pindah']);

        $this->expectException(\DomainException::class);
        $this->jadwal->pindah($no, $this->idPos, '2026-10-12 08:00', 'UJI');
    }

    public function test_daftar_belum_dicatat_hanya_memuat_yang_lewat_dan_belum_diputus(): void
    {
        $lewat = $this->permitMenunggu();
        $nanti = $this->permitMenunggu();
        DB::transaction(function () use ($lewat, $nanti) {
            $this->jadwal->pesan($lewat, $this->idPos, self::SENIN.' 08:00', 'UJI');
            $this->jadwal->pesan($nanti, $this->idPos, '2026-09-22 08:00', 'UJI');
        });

        Carbon::setTestNow(Carbon::parse(self::SENIN.' 09:00'));
        $daftar = $this->jadwal->belumDicatat()->pluck('No_Transaksi_Permit');

        $this->assertTrue($daftar->contains($lewat), 'Yang lewat jadwal justru tidak terdaftar.');
        $this->assertFalse($daftar->contains($nanti), 'Jadwal yang belum tiba ikut terbawa.');

        // Permit yang sudah diputus keluar dari daftar: tak ada lagi yang perlu dicatat.
        DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $lewat)->update(['Flag_Approval' => 'Y']);
        $this->assertFalse($this->jadwal->belumDicatat()->pluck('No_Transaksi_Permit')->contains($lewat));
    }

    public function test_memesan_slot_menerbitkan_akses_palang_selebar_slotnya(): void
    {
        // Pemohon harus bisa masuk gerbang untuk menemui SHE, padahal
        // permitnya belum diputus. Jendelanya sempit dengan sendirinya: lewat
        // jamnya, akses mati tanpa ada yang perlu membersihkannya.
        $nomor = $this->permitMenunggu();

        DB::transaction(fn () => $this->jadwal->pesan($nomor, $this->idPos, '2026-09-22 08:00', 'UJI'));

        $efektif = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->first();

        $this->assertNotNull($efektif, 'Pemohon tidak punya akses apa pun untuk menemui SHE.');
        $this->assertSame('AKTIF', $efektif->Status_Efektif);
        $this->assertSame(VisitorEfektifService::DURASI_AKSES_INSPEKSI, $efektif->Nama_Durasi);
        // Kategori asli (HIJAU/MERAH) tidak dipakai di tahap ini: petugas
        // palang yang membacanya akan mengira permitnya sudah terbit.
        // DDL-2026-132: penandanya KOLOM, bukan kalimat di kolom kategori.
        // Kolom kategori kini berisi kategori asli permitnya supaya palang tahu
        // zona sebenarnya dan dashboard tidak punya ember hantu.
        $this->assertSame(VisitorEfektifService::JENIS_INSPEKSI, $efektif->Jenis_Akses);
        // Slot 08:00–08:30 dengan toleransi 15 menit di kedua ujungnya.
        $this->assertSame('2026-09-22 07:45:00', substr((string) $efektif->Waktu_Mulai_Berlaku, 0, 19));
        $this->assertSame('2026-09-22 08:45:00', substr((string) $efektif->Waktu_Berakhir, 0, 19));
    }

    public function test_akses_palang_tidak_membuat_pengajuan_tampak_disetujui(): void
    {
        // Jebakan terbesar pendekatan ini: satu baris di tabel efektif dibaca
        // belasan layar. Kalau status permitnya ikut berubah, verifikator
        // melihat "sudah disetujui" untuk pengajuan yang belum ia sentuh.
        $nomor = $this->permitMenunggu();
        DB::transaction(fn () => $this->jadwal->pesan($nomor, $this->idPos, '2026-09-22 08:00', 'UJI'));

        $status = DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
            ->leftJoin('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('h.No_Transaksi', $nomor)
            ->selectRaw(app(VisitorPermitQueryService::class)->permitStatusSql().' as s')
            ->value('s');

        $this->assertNotSame('APPROVED', $status);
        $this->assertNotSame('REVOKED', $status);
    }

    public function test_memindah_jadwal_menggeser_jendelanya_tanpa_menambah_baris(): void
    {
        $nomor = $this->permitMenunggu();
        DB::transaction(fn () => $this->jadwal->pesan($nomor, $this->idPos, '2026-09-22 08:00', 'UJI'));

        $this->jadwal->pindah($nomor, $this->idPos, '2026-09-22 08:30', 'UJI');

        $baris = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->get();

        $this->assertCount(1, $baris, 'Akses lama menumpuk; pemohon punya dua jendela sekaligus.');
        $this->assertSame('2026-09-22 08:15:00', substr((string) $baris->first()->Waktu_Mulai_Berlaku, 0, 19));
    }

    public function test_mengakhiri_akses_memakai_expired_bukan_dibatalkan(): void
    {
        // 'DIBATALKAN' adalah cabang PERTAMA permitStatusSql dan berarti
        // "akses dicabut". Memakainya di sini membuat pengajuan yang ditolak
        // terbaca "Dicabut" di seluruh layar — sebab yang salah untuk kejadian
        // yang benar.
        $nomor = $this->permitMenunggu();
        DB::transaction(fn () => $this->jadwal->pesan($nomor, $this->idPos, '2026-09-22 08:00', 'UJI'));

        app(VisitorEfektifService::class)->akhiriAksesInspeksi($nomor);

        $efektif = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->first();
        $this->assertSame('EXPIRED', $efektif->Status_Efektif);
        $this->assertNotSame('DIBATALKAN', $efektif->Status_Efektif);

        $status = DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
            ->leftJoin('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('h.No_Transaksi', $nomor)
            ->selectRaw(app(VisitorPermitQueryService::class)->permitStatusSql().' as s')
            ->value('s');
        $this->assertNotSame('REVOKED', $status);
    }
}
