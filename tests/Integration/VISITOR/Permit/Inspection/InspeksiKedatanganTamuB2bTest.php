<?php

namespace Tests\Integration\VISITOR\Permit\Inspection;

use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Inspeksi tamu B2B: didatangi, bukan dijadwalkan.
 *
 * Permit COKLAT tamu B2B TIDAK memesan slot. Tamunya datang pada jadwal
 * kunjungannya sendiri dan tim SHE yang mendatangi pos gerbang saat itu —
 * berbeda dari permit karyawan (hijau/merah), yang pemohonnya memilih slot lalu
 * datang ke pos.
 *
 * Tiga akibat yang menyertainya, dan ketiganya diuji di sini karena masing-
 * masing gagal dengan diam:
 *
 *  - Pencatatannya TIDAK memakan kursi slot. Kalau ia menulis baris TERJADWAL,
 *    kedatangan tamu diam-diam menutup kursi yang dijual ke permit karyawan —
 *    dan yang terlihat hanyalah slot yang "penuh sendiri".
 *  - Ketidakhadiran TAMU tidak menggugurkan permit. Jalur tidakHadir() milik
 *    permit karyawan menggugurkan pada hitungan kedua; memakai ulang jalur itu
 *    berarti menghukum pemegang permit atas kelalaian pihak lain.
 *  - Tanpa pos yang disetel, pencatatannya ditolak dengan sebab yang menyebut
 *    setelannya — bukan menulis baris ke pos yang kebetulan pertama.
 */
class InspeksiKedatanganTamuB2bTest extends TestCase
{
    use DatabaseTransactions;

    private const HARI = '2026-09-21';

    private JadwalInspeksiService $jadwal;

    private int $idPos;

    private int $idShift;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::HARI.' 06:00'));
        $this->jadwal = app(JadwalInspeksiService::class);

        DB::table('N_VISITOR_Inspeksi_Pola')->update(['Flag_Aktif' => 'T']);
        DB::table('N_VISITOR_Inspeksi_Pengecualian')->update(['Flag_Aktif' => 'T']);
        DB::table(JadwalInspeksiService::T_HARI_SHIFT)->update(['Flag_Aktif' => 'Y']);

        $this->idShift = (int) DB::table(JadwalInspeksiService::T_SHIFT)->insertGetId([
            'Nama_Shift' => 'Shift Uji Tamu', 'Jam_Mulai' => '07:00', 'Jam_Selesai' => '15:00',
            'Urutan' => 9, 'Flag_Aktif' => 'Y', 'Created_By' => 'UJI',
        ]);

        $this->idPos = (int) DB::table(JadwalInspeksiService::T_POS)->insertGetId([
            'Nama_Pos' => 'Pos Satpam Uji', 'Keterangan_Lokasi' => 'Gerbang utama', 'Flag_Aktif' => 'Y', 'Created_By' => 'UJI',
        ]);

        // Senin 08:00–09:00, dua slot 30 menit, SATU kursi per slot.
        DB::table('N_VISITOR_Inspeksi_Pola')->insert([
            'Id_Pos_Inspeksi' => $this->idPos,
            'Id_Shift_Inspeksi' => $this->idShift,
            'Hari_Minggu' => 1,
            'Jam_Mulai' => '08:00',
            'Jam_Selesai' => '09:00',
            'Durasi_Menit' => 30,
            'Kuota_Per_Slot' => 1,
            'Flag_Aktif' => 'Y',
            'Created_By' => 'UJI',
        ]);

        config(['services.visitor.permit.pos_tamu_b2b' => $this->idPos]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_kedatangan_tamu_dicatat_tanpa_pernah_menjadi_terjadwal(): void
    {
        $permit = $this->permitTamu();

        $booking = $this->jadwal->catatKedatanganTamuB2b(
            $permit,
            JadwalInspeksiService::SELESAI,
            Carbon::parse(self::HARI.' 08:00'),
            Carbon::parse(self::HARI.' 17:00'),
            'SHE-1',
        );

        $this->assertSame(JadwalInspeksiService::SELESAI, $booking->Status);
        $this->assertSame($this->idPos, (int) $booking->Id_Pos_Inspeksi);
        $this->assertSame('SHE-1', $booking->Diinspeksi_Oleh);
        $this->assertNotNull($booking->Diinspeksi_At);

        $this->assertSame(
            0,
            DB::table('N_VISITOR_Inspeksi_Booking')
                ->where('No_Transaksi_Permit', $permit)
                ->where('Status', JadwalInspeksiService::TERJADWAL)
                ->count(),
            'Pencatatan kedatangan tamu membuat baris TERJADWAL; ia akan memakan kursi slot.',
        );
    }

    public function test_kedatangan_tamu_tidak_mengurangi_kursi_permit_karyawan(): void
    {
        // Inti keputusannya. Satu kursi, dan tamu B2B tidak boleh memakannya.
        $sebelum = $this->sisaSlot(self::HARI.' 08:00');
        $this->assertSame(1, $sebelum, 'Prasyarat gagal: slot ujinya tidak berkursi satu.');

        $this->jadwal->catatKedatanganTamuB2b(
            $this->permitTamu(),
            JadwalInspeksiService::SELESAI,
            Carbon::parse(self::HARI.' 08:00'),
            Carbon::parse(self::HARI.' 08:30'),
            'SHE-1',
        );

        $this->assertSame(
            1,
            $this->sisaSlot(self::HARI.' 08:00'),
            'Kedatangan tamu B2B memakan kursi slot milik permit karyawan.',
        );
    }

    public function test_tamu_tidak_hadir_tidak_menggugurkan_permitnya(): void
    {
        $permit = $this->permitTamu(null);

        foreach ([1, 2] as $keberapa) {
            $this->jadwal->catatKedatanganTamuB2b(
                $permit,
                JadwalInspeksiService::TIDAK_HADIR,
                Carbon::parse(self::HARI.' 08:00'),
                Carbon::parse(self::HARI.' 17:00'),
                'SHE-1',
                'Tamu tidak muncul di pos.',
            );
        }

        $this->assertNull(
            DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $permit)->value('Flag_Approval'),
            'Ketidakhadiran TAMU menggugurkan permitnya; itu hukuman untuk kelalaian pihak lain.',
        );
    }

    public function test_hasil_yang_salah_tekan_diperbaiki_bukan_ditumpuk(): void
    {
        // Dua kenyataan pada satu kedatangan membuat agenda dan laporan
        // menjawab berbeda tergantung baris mana yang kebetulan terbaca duluan.
        $permit = $this->permitTamu();

        foreach ([JadwalInspeksiService::TIDAK_HADIR, JadwalInspeksiService::SELESAI] as $status) {
            $this->jadwal->catatKedatanganTamuB2b(
                $permit,
                $status,
                Carbon::parse(self::HARI.' 08:00'),
                Carbon::parse(self::HARI.' 17:00'),
                'SHE-1',
            );
        }

        $baris = DB::table('N_VISITOR_Inspeksi_Booking')->where('No_Transaksi_Permit', $permit)->get();

        $this->assertCount(1, $baris);
        $this->assertSame(JadwalInspeksiService::SELESAI, $baris->first()->Status);
    }

    /**
     * "Selesai" ditolak selama permitnya belum diputus.
     *
     * Urutan kerjanya: verifikator memutus permit COKLAT (itulah yang memberi
     * akses gerbang), baru tim SHE memeriksa kendaraan tamunya di pos. Kalau
     * SHE boleh menutup pekerjaannya lebih dulu, agendanya bersih sementara
     * pengajuannya masih menggantung di meja verifikator — dan tidak ada satu
     * layar pun yang menunjukkan pasangan itu.
     */
    public function test_selesai_ditolak_selama_permitnya_belum_direview(): void
    {
        $permit = $this->permitTamu(null);

        try {
            $this->jadwal->catatKedatanganTamuB2b(
                $permit,
                JadwalInspeksiService::SELESAI,
                Carbon::parse(self::HARI.' 08:00'),
                Carbon::parse(self::HARI.' 17:00'),
                'SHE-1',
            );
            $this->fail('Inspeksi diselesaikan padahal permitnya belum diputus siapa pun.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('direview', $e->getMessage());
        }

        // Tetapi ketidakhadiran TAMU harus tetap bisa dicatat: itu satu-satunya
        // cara menutup barisnya tanpa menunggu keputusan permitnya.
        $this->jadwal->catatKedatanganTamuB2b(
            $permit,
            JadwalInspeksiService::TIDAK_HADIR,
            Carbon::parse(self::HARI.' 08:00'),
            Carbon::parse(self::HARI.' 17:00'),
            'SHE-1',
        );

        $this->assertSame(
            JadwalInspeksiService::TIDAK_HADIR,
            DB::table('N_VISITOR_Inspeksi_Booking')->where('No_Transaksi_Permit', $permit)->value('Status'),
        );
    }

    /** Permit yang sudah DITOLAK tidak punya inspeksi untuk dicatat sama sekali. */
    public function test_permit_yang_ditolak_tidak_bisa_dicatat(): void
    {
        $permit = $this->permitTamu('T');

        $this->expectException(\DomainException::class);

        $this->jadwal->catatKedatanganTamuB2b(
            $permit,
            JadwalInspeksiService::TIDAK_HADIR,
            Carbon::parse(self::HARI.' 08:00'),
            Carbon::parse(self::HARI.' 17:00'),
            'SHE-1',
        );
    }

    public function test_pos_belum_disetel_ditolak_dengan_menyebut_setelannya(): void
    {
        config(['services.visitor.permit.pos_tamu_b2b' => 0]);

        try {
            $this->jadwal->catatKedatanganTamuB2b(
                $this->permitTamu(),
                JadwalInspeksiService::SELESAI,
                Carbon::parse(self::HARI.' 08:00'),
                Carbon::parse(self::HARI.' 17:00'),
                'SHE-1',
            );
            $this->fail('Hasil inspeksi tercatat padahal posnya belum disetel.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('pos_tamu_b2b', $e->getMessage());
        }
    }

    public function test_pos_yang_dinonaktifkan_tidak_dipakai_diam_diam(): void
    {
        DB::table(JadwalInspeksiService::T_POS)->where('Id_Pos_Inspeksi', $this->idPos)->update(['Flag_Aktif' => 'T']);

        $this->expectException(\DomainException::class);

        $this->jadwal->catatKedatanganTamuB2b(
            $this->permitTamu(),
            JadwalInspeksiService::SELESAI,
            Carbon::parse(self::HARI.' 08:00'),
            Carbon::parse(self::HARI.' 17:00'),
            'SHE-1',
        );
    }

    public function test_status_selain_selesai_dan_tidak_hadir_ditolak(): void
    {
        // TERJADWAL lewat pintu ini akan memakan kursi slot tanpa seorang pun
        // pernah memesannya.
        $this->expectException(\DomainException::class);

        $this->jadwal->catatKedatanganTamuB2b(
            $this->permitTamu(),
            JadwalInspeksiService::TERJADWAL,
            Carbon::parse(self::HARI.' 08:00'),
            Carbon::parse(self::HARI.' 17:00'),
            'SHE-1',
        );
    }

    /**
     * Agenda memberitahu SHE harus berjaga di mana, bahkan sebelum dicatat.
     *
     * Barisnya tidak punya slot, jadi posnya tidak bisa diturunkan dari data
     * barisnya sendiri. Kalau payload-nya tidak membawa pos dari setelan,
     * kolom "Pos & Shift" tetap bergaris pendek dan tombol aksinya tak pernah
     * muncul — persis keadaan yang membuat halaman ini terbaca rusak.
     */
    public function test_agenda_membawa_pos_dan_izin_mencatat_untuk_baris_tamu(): void
    {
        // Masih menunggu: sejak 30 Sep 2026 permit yang sudah disetujui terbaca
        // inspeksinya SELESAI (persetujuanlah yang menutupnya).
        $permit = $this->permitTamu(null);
        $this->kunjunganUntuk($permit);

        $baris = $this->barisAgenda($permit);

        $this->assertNotNull($baris, 'Baris tamu B2B tidak muncul di agenda tanggal kunjungannya.');
        $this->assertSame('Pos Satpam Uji', $baris['Pos_Inspeksi']);
        // Id pos ikut: kartu okupansi menghitung kedatangan tamu per pos, dan
        // saringan pos agenda mencocokkan baris dengan id-nya.
        $this->assertSame($this->idPos, $baris['Id_Pos_Inspeksi']);
        $this->assertTrue($baris['Boleh_Tidak_Hadir_Tamu']);
        $this->assertArrayNotHasKey(
            'Boleh_Selesai',
            $baris,
            'Agenda menawarkan aksi "selesai"; penutupnya harus keputusan review, bukan tombol di sini.',
        );
        $this->assertNull($baris['Status_Inspeksi']);

        DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $permit)->update(['Flag_Approval' => 'Y']);
        $this->jadwal->catatKedatanganTamuB2b(
            $permit,
            JadwalInspeksiService::SELESAI,
            Carbon::parse(self::HARI.' 08:00'),
            Carbon::parse(self::HARI.' 17:00'),
            'SHE-1',
        );

        $this->assertSame(JadwalInspeksiService::SELESAI, $this->barisAgenda($permit)['Status_Inspeksi']);
    }

    /**
     * SHE berjaga menyambut tamu menurut jam yang diajukan di kunjungannya —
     * permit tamu tidak punya slot. Agenda dan detail permit harus membawa
     * estimasi masuk DAN keluar yang sama.
     */
    public function test_estimasi_masuk_dan_keluar_tamu_sampai_ke_agenda_dan_detail_permit(): void
    {
        $permit = $this->permitTamu(null);
        $this->kunjunganUntuk($permit);

        $baris = $this->barisAgenda($permit);
        $this->assertSame(self::HARI.' 08:00', $baris['Mulai']);
        $this->assertSame(self::HARI.' 17:00', $baris['Selesai']);

        $noTamu = DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $permit)->value('No_Transaksi_Tamu');
        $ringkasan = app(\App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService::class)->ringkasanKunjungan($noTamu);

        $this->assertSame(self::HARI.' 08:00', $ringkasan['estimasi_masuk']);
        $this->assertSame(self::HARI.' 17:00', $ringkasan['estimasi_keluar']);
        $this->assertArrayHasKey('aktual_masuk', $ringkasan);
        $this->assertNull(app(\App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService::class)->ringkasanKunjungan(null));
    }

    /**
     * Kasus PMT0926-0239/0240 (30 Sep 2026): permit sudah disetujui tanpa
     * baris hasil inspeksi, dan tamunya sudah keluar. Persetujuan itulah yang
     * menutup inspeksinya — barisnya tidak boleh lagi menawarkan review
     * maupun "tamu tidak hadir".
     */
    public function test_permit_disetujui_tanpa_baris_hasil_terbaca_selesai(): void
    {
        $permit = $this->permitTamu('Y');
        $this->kunjunganUntuk($permit);

        $baris = $this->barisAgenda($permit);

        $this->assertSame(JadwalInspeksiService::SELESAI, $baris['Status_Inspeksi']);
        $this->assertFalse($baris['Boleh_Tidak_Hadir_Tamu'] && $baris['Status_Inspeksi'] !== JadwalInspeksiService::SELESAI);
    }

    public function test_tamu_yang_sudah_lewat_palang_tidak_bisa_ditandai_tidak_hadir(): void
    {
        $permit = $this->permitTamu(null);
        $this->kunjunganUntuk($permit);
        $noTamu = DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $permit)->value('No_Transaksi_Tamu');
        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $noTamu)
            ->update(['Waktu_Aktual_Masuk' => self::HARI.' 08:10']);

        $this->assertFalse($this->barisAgenda($permit)['Boleh_Tidak_Hadir_Tamu']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('sudah tercatat masuk gerbang');
        $this->jadwal->catatKedatanganTamuB2b(
            $permit,
            JadwalInspeksiService::TIDAK_HADIR,
            Carbon::parse(self::HARI.' 08:00'),
            Carbon::parse(self::HARI.' 17:00'),
            'SHE-1',
        );
    }

    /** Permit masih menunggu: yang ditawarkan hanya "tidak hadir". */
    public function test_permit_menunggu_hanya_menawarkan_tidak_hadir(): void
    {
        $permit = $this->permitTamu(null);
        $this->kunjunganUntuk($permit);

        $baris = $this->barisAgenda($permit);

        $this->assertNotNull($baris);
        $this->assertTrue($baris['Boleh_Tidak_Hadir_Tamu']);
    }

    /** Pos belum disetel: barisnya tetap tampil, tetapi aksinya tidak ditawarkan. */
    public function test_tanpa_pos_yang_disetel_aksinya_tidak_ditawarkan(): void
    {
        config(['services.visitor.permit.pos_tamu_b2b' => 0]);

        $permit = $this->permitTamu();
        $this->kunjunganUntuk($permit);
        $baris = $this->barisAgenda($permit);

        $this->assertNotNull($baris, 'Baris tamu hilang dari agenda hanya karena posnya belum disetel.');
        $this->assertFalse($baris['Boleh_Tidak_Hadir_Tamu']);
        $this->assertNull($baris['Pos_Inspeksi']);
    }

    // ---------------------------------------------------------------- helpers

    /** @return array<string,mixed>|null */
    // ── Daftar "Lewat Jadwal & Belum Dicatat" untuk kedatangan tamu B2B ──

    public function test_kedatangan_tamu_lewat_tanpa_hasil_masuk_daftar_belum_dicatat(): void
    {
        $permit = $this->permitTamu(null);
        $this->kunjunganUntuk($permit);
        Carbon::setTestNow(Carbon::parse('2026-09-22 09:00'));

        $baris = $this->barisBelumDicatat($permit);

        $this->assertNotNull($baris, 'Kedatangan tamu yang lewat tanpa hasil tidak ditagih.');
        $this->assertSame('COKLAT', $baris['Jenis']);
        $this->assertSame('Pos Satpam Uji', $baris['Pos_Inspeksi']);
        $this->assertTrue($baris['Boleh_Tidak_Hadir_Tamu']);
    }

    public function test_kedatangan_tamu_hilang_dari_daftar_setelah_hasilnya_dicatat(): void
    {
        $permit = $this->permitTamu(null);
        $this->kunjunganUntuk($permit);
        Carbon::setTestNow(Carbon::parse('2026-09-22 09:00'));

        $this->jadwal->catatKedatanganTamuB2b($permit, JadwalInspeksiService::TIDAK_HADIR, Carbon::parse(self::HARI.' 08:00'), Carbon::parse(self::HARI.' 17:00'), 'SHE-1');

        $this->assertNull($this->barisBelumDicatat($permit));
    }

    public function test_kedatangan_tamu_yang_permitnya_ditolak_atau_diputus_tidak_ditagih(): void
    {
        $ditolak = $this->permitTamu('T');
        $this->kunjunganUntuk($ditolak);
        // Disetujui = sudah diinspeksi (approve menandai SELESAI); sisa lama tanpa baris hasil tak perlu ditagih.
        $disetujui = $this->permitTamu('Y');
        $this->kunjunganUntuk($disetujui);
        Carbon::setTestNow(Carbon::parse('2026-09-22 09:00'));

        $this->assertNull($this->barisBelumDicatat($ditolak));
        $this->assertNull($this->barisBelumDicatat($disetujui));
    }

    public function test_kedatangan_tamu_yang_belum_lewat_tidak_ditagih(): void
    {
        $permit = $this->permitTamu(null);
        $this->kunjunganUntuk($permit);
        Carbon::setTestNow(Carbon::parse(self::HARI.' 12:00'));

        $this->assertNull($this->barisBelumDicatat($permit));
    }

    public function test_tamu_tidak_hadir_mengabari_pic_kunjungannya(): void
    {
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\VISITOR\Workflow\Notification\PublishVisitorNotificationsJob::class]);
        $permit = $this->permitTamu(null);
        $this->kunjunganUntuk($permit);
        $noTamu = (string) DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $permit)->value('No_Transaksi_Tamu');

        $request = \Illuminate\Http\Request::create("/agenda-inspeksi/{$permit}/kedatangan-tamu", 'POST', ['status' => JadwalInspeksiService::TIDAK_HADIR]);
        $route = new \Illuminate\Routing\Route('POST', 'agenda-inspeksi/{noTransaksi}/kedatangan-tamu', []);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $jawab = app(\App\Http\Controllers\VISITOR\Permit\Inspection\AgendaInspeksiController::class)->catatKedatanganTamu($request);
        $this->assertSame(200, $jawab->getStatusCode(), $jawab->getContent());

        \Illuminate\Support\Facades\Bus::assertDispatched(
            \App\Jobs\VISITOR\Workflow\Notification\PublishVisitorNotificationsJob::class,
            fn ($job) => ($ev = (new \ReflectionProperty($job, 'events'))->getValue($job)[0])
                && $ev['domain'] === \App\Services\VISITOR\Workflow\Notification\VisitorNotificationService::DOMAIN_TAMU_B2B
                && $ev['no_transaksi'] === $noTamu
                && $ev['decision'] === \App\Services\VISITOR\Workflow\Notification\VisitorNotificationService::KEPUTUSAN_TAMU_TIDAK_HADIR,
        );
    }

    private function barisBelumDicatat(string $noPermit): ?array
    {
        $isi = json_decode(
            app(\App\Http\Controllers\VISITOR\Permit\Inspection\AgendaInspeksiController::class)->belumDicatat()->getContent(),
            true,
        )['result']['daftar'] ?? [];

        foreach ($isi as $baris) {
            if (($baris['No_Transaksi'] ?? null) === $noPermit) {
                return $baris;
            }
        }

        return null;
    }

    private function barisAgenda(string $noPermit): ?array
    {
        $request = \Illuminate\Http\Request::create('/agenda-inspeksi-data', 'GET', ['tanggal' => self::HARI]);

        $isi = json_decode(
            app(\App\Http\Controllers\VISITOR\Permit\Inspection\AgendaInspeksiController::class)
                ->data($request)->getContent(),
            true,
        )['result'] ?? [];

        foreach ($isi['coklat'] ?? [] as $baris) {
            if (($baris['No_Transaksi'] ?? null) === $noPermit) {
                return $baris;
            }
        }

        return null;
    }

    /** Kunjungan tamu B2B pemilik permit itu, pada tanggal uji. */
    private function kunjunganUntuk(string $noPermit): void
    {
        $noTamu = (string) DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('No_Transaksi', $noPermit)
            ->value('No_Transaksi_Tamu');

        $contoh = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->orderByDesc('Id_Transaksi_Tamu')->first();
        $this->assertNotNull($contoh, 'Butuh satu kunjungan tamu di dev sebagai cetakan kolom wajib.');

        $header = (array) $contoh;
        unset($header['Id_Transaksi_Tamu']);
        $header['No_Transaksi'] = $noTamu;
        $header['Flag_Aktif'] = 'Y';
        $header['Flag_Approval'] = 'Y';
        $header['Status_Kunjungan'] = 'SCHEDULED';
        $header['Tanggal_Kunjungan'] = self::HARI;
        $header['Idempotency_Key'] = 'UJI-'.Str::upper(Str::random(20));

        $idTamu = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId($header);

        $contohDetail = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->orderByDesc('Id_Transaksi_Tamu_Detail')->first();
        $this->assertNotNull($contohDetail, 'Butuh satu detail tamu di dev sebagai cetakan kolom wajib.');

        $detail = (array) $contohDetail;
        unset($detail['Id_Transaksi_Tamu_Detail']);
        $detail['Id_Transaksi_Tamu'] = $idTamu;
        $detail['No_Transaksi'] = $noTamu;
        $detail['Tipe_Tamu'] = 'B2B';
        $detail['Nama_Lengkap_Tamu'] = 'Tamu Uji Inspeksi';
        $detail['Waktu_Mulai_Kunjungan'] = self::HARI.' 08:00';
        $detail['Waktu_Selesai_Kunjungan'] = self::HARI.' 17:00';
        // Cetakannya baris nyata: jam hadirnya tidak boleh ikut tersalin.
        $detail['Waktu_Aktual_Masuk'] = null;
        $detail['Waktu_Aktual_Keluar'] = null;

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert($detail);
    }

    private function sisaSlot(string $mulai): int
    {
        $slot = collect($this->jadwal->slotRentang(Carbon::parse(self::HARI), Carbon::parse(self::HARI)))
            ->firstWhere('mulai', substr($mulai, 0, 16));

        $this->assertNotNull($slot, 'Slot uji tidak terbentuk dari polanya.');

        return (int) $slot['sisa'];
    }

    /**
     * Permit COKLAT turunan kunjungan tamu.
     *
     * Bawaannya SUDAH DISETUJUI, sebab itulah keadaan normal saat tamunya tiba:
     * permitnya diputus lebih dulu — itu yang memberi akses gerbang — baru SHE
     * memeriksa kendaraannya. Kirim null untuk menguji yang masih menunggu.
     */
    private function permitTamu(?string $flagApproval = 'Y'): string
    {
        $contoh = DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->whereNull('No_Transaksi_Tamu')
            ->orderByDesc('Id_Transaksi_Permit')
            ->first();

        $this->assertNotNull($contoh, 'Butuh satu permit karyawan di dev sebagai cetakan kolom wajib.');

        $baris = (array) $contoh;
        unset($baris['Id_Transaksi_Permit']);
        $nomor = 'UJI-B2B-'.Str::upper(Str::random(8));
        $baris['No_Transaksi'] = $nomor;
        $baris['No_Transaksi_Tamu'] = 'VSR-UJI-'.Str::upper(Str::random(8));
        $baris['Flag_Approval'] = $flagApproval;
        $baris['Flag_Aktif'] = 'Y';
        $baris['Idempotency_Key'] = null;
        $baris['Kode_Kartu'] = null;

        DB::table('N_EMI_VISITOR_Transaksi_Permit')->insert($baris);

        return $nomor;
    }
}
