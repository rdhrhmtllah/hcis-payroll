<?php

namespace Tests\Integration\VISITOR\Workflow\Notification;

use App\Services\SYSTEM\Notification\NotificationService;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitApiService;
use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisitorDecisionNotificationTest extends TestCase
{
    use DatabaseTransactions;
    use MenegaskanTautanDetail;

    public function test_keputusan_permit_dikirim_ke_pemohon_bukan_ke_admin(): void
    {
        [$noTransaksi, $idTransaksi, $pemohon] = $this->buatPermit();

        $terkirim = app(VisitorNotificationService::class)->notifikasiKeputusan(
            VisitorNotificationService::DOMAIN_PERMIT,
            $noTransaksi,
            VisitorNotificationService::KEPUTUSAN_DISETUJUI,
        );

        $this->assertSame(1, $terkirim);

        $rows = DB::table('N_HRIS_Notification')->where('Reference_Id', $noTransaksi)->get();
        $this->assertCount(1, $rows);

        $row = $rows->first();
        $this->assertSame($pemohon, trim((string) $row->Kode_Karyawan));
        $this->assertNotContains(
            trim((string) $row->Kode_Karyawan),
            app(VisitorNotificationService::class)->penerimaAdmin(VisitorNotificationService::DOMAIN_TAMU),
            'Keputusan ditujukan ke pemohon; kalau kebetulan sama dengan admin, pilih pemohon lain di fixture.',
        );
        $this->assertTautanDetail($row->Action_Url, '/transaksipermit', VisitorNotificationService::DOMAIN_PERMIT, (int) $idTransaksi);
        $this->assertSame('success', $row->Severity);
    }

    public function test_keputusan_terlihat_lewat_jalur_baca_lonceng_topbar(): void
    {
        [$noTransaksi, , $pemohon] = $this->buatTamu();

        app(VisitorNotificationService::class)->notifikasiKeputusan(
            VisitorNotificationService::DOMAIN_TAMU,
            $noTransaksi,
            VisitorNotificationService::KEPUTUSAN_DITOLAK,
            ['alasan' => 'Dokumen tidak lengkap'],
        );

        $terlihat = collect(app(NotificationService::class)->listUnread($pemohon, [
            'limit' => 20,
            'source' => 'MAIN_NOTIF',
        ]))->pluck('reference_id')->all();

        $this->assertContains(
            $noTransaksi,
            $terlihat,
            'Notifikasi keputusan tidak lolos saringan source=MAIN_NOTIF, jadi tak akan muncul di lonceng.',
        );
    }

    public function test_keputusan_tamu_b2b_menautkan_halaman_pengajuan_b2b(): void
    {
        // DDL-2026-121: domain TAMU_B2B membaca tabel tamu yang sama, tetapi
        // pemohonnya dibawa ke /transaksi-tamu-b2b. Token detailnya bertanda
        // domain TAMU karena showTamu() di kedua halaman membaca token TAMU.
        [$noTransaksi, $idTransaksi, $pemohon] = $this->buatTamu();

        $terkirim = app(VisitorNotificationService::class)->notifikasiKeputusan(
            VisitorNotificationService::DOMAIN_TAMU_B2B,
            $noTransaksi,
            VisitorNotificationService::KEPUTUSAN_DISETUJUI,
        );

        $this->assertSame(1, $terkirim);

        $row = DB::table('N_HRIS_Notification')->where('Reference_Id', $noTransaksi)->first();
        $this->assertNotNull($row);
        $this->assertSame($pemohon, trim((string) $row->Kode_Karyawan));
        $this->assertStringContainsString('Tamu B2B', (string) $row->Title);
        $this->assertTautanDetail($row->Action_Url, '/transaksi-tamu-b2b', VisitorNotificationService::DOMAIN_TAMU, $idTransaksi);
    }

    public function test_keputusan_berbeda_atas_transaksi_sama_tidak_saling_menimpa(): void
    {
        [$noTransaksi] = $this->buatPermit();
        $svc = app(VisitorNotificationService::class);

        $svc->notifikasiKeputusan(VisitorNotificationService::DOMAIN_PERMIT, $noTransaksi, VisitorNotificationService::KEPUTUSAN_DISETUJUI);
        $svc->notifikasiKeputusan(VisitorNotificationService::DOMAIN_PERMIT, $noTransaksi, VisitorNotificationService::KEPUTUSAN_DIBATALKAN, ['alasan' => 'Kendaraan dijual']);
        // Penerbitan ulang keputusan yang sama tidak menambah baris.
        $svc->notifikasiKeputusan(VisitorNotificationService::DOMAIN_PERMIT, $noTransaksi, VisitorNotificationService::KEPUTUSAN_DIBATALKAN, ['alasan' => 'Kendaraan dijual']);

        $this->assertSame(
            2,
            DB::table('N_HRIS_Notification')->where('Reference_Id', $noTransaksi)->count(),
            'Disetujui dan dibatalkan adalah dua peristiwa berbeda; keduanya harus tersimpan, tapi masing-masing sekali.',
        );
    }

    public function test_transaksi_tak_dikenal_tidak_menerbitkan_apa_pun(): void
    {
        $terkirim = app(VisitorNotificationService::class)->notifikasiKeputusan(
            VisitorNotificationService::DOMAIN_PERMIT,
            'PMT-TIDAK-ADA-'.Str::upper(Str::random(6)),
            VisitorNotificationService::KEPUTUSAN_DISETUJUI,
        );

        $this->assertSame(0, $terkirim);
    }

    public function test_keputusan_tak_dikenal_ditolak(): void
    {
        [$noTransaksi] = $this->buatPermit();

        $this->expectException(\InvalidArgumentException::class);

        app(VisitorNotificationService::class)->notifikasiKeputusan(
            VisitorNotificationService::DOMAIN_PERMIT,
            $noTransaksi,
            'DIPERTIMBANGKAN',
        );
    }

    /**
     * Permit mitra: pemohonnya PENANDA APLIKASI, bukan orang.
     *
     * Tanpa penjaganya, notifikasi keputusan tetap DITULIS — N_HRIS_Notification
     * tidak punya foreign key ke Karyawan, jadi barisnya tersimpan atas nama
     * 'API#233' dan tidak pernah muncul di lonceng siapa pun. Uji ini karena itu
     * menagih dua hal sekaligus: nilai baliknya 0, DAN tabelnya benar-benar
     * kosong. Yang pertama saja tidak membuktikan apa-apa tentang baris yatim.
     */
    public function test_keputusan_permit_mitra_tidak_menerbitkan_notifikasi_yatim(): void
    {
        $noTransaksi = $this->buatPermitMitra();

        $terkirim = app(VisitorNotificationService::class)->notifikasiKeputusan(
            VisitorNotificationService::DOMAIN_PERMIT,
            $noTransaksi,
            VisitorNotificationService::KEPUTUSAN_DISETUJUI,
        );

        $this->assertSame(0, $terkirim, 'Permit mitra tidak punya pemohon manusia untuk dikabari.');
        $this->assertSame(
            0,
            DB::table('N_HRIS_Notification')->where('Reference_Id', $noTransaksi)->count(),
            'Baris notifikasi tertulis atas nama penanda aplikasi — tidak ada yang bisa membacanya.',
        );
    }

    public function test_penolakan_permit_mitra_juga_tidak_menerbitkan_apa_pun(): void
    {
        $noTransaksi = $this->buatPermitMitra();

        $terkirim = app(VisitorNotificationService::class)->notifikasiKeputusan(
            VisitorNotificationService::DOMAIN_PERMIT,
            $noTransaksi,
            VisitorNotificationService::KEPUTUSAN_DITOLAK,
            ['alasan' => 'Data kendaraan tidak lengkap'],
        );

        $this->assertSame(0, $terkirim);
        $this->assertSame(0, DB::table('N_HRIS_Notification')->where('Reference_Id', $noTransaksi)->count());
    }

    /**
     * Pemohon yang bukan aplikasi TETAPI juga bukan karyawan.
     *
     * Berbeda dari permit mitra, keadaan ini tidak wajar: ada pemohon sungguhan
     * yang kehilangan kabar keputusannya. Ia tetap ditolak — menulis baris yang
     * tak terbaca tidak menolong siapa pun — tetapi penolakannya dicatat, bukan
     * sama diamnya dengan yang wajar.
     */
    public function test_pemohon_yang_bukan_karyawan_ditolak_dan_dicatat(): void
    {
        $noTransaksi = 'PMT-TEST-'.Str::upper(Str::random(8));
        $hantu = 'ZZ'.Str::upper(Str::random(6));

        DB::table('N_EMI_VISITOR_Transaksi_Permit')->insert([
            'No_Transaksi' => $noTransaksi,
            'Kode_Karyawan_Pemohon' => $hantu,
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => $hantu,
            'Idempotency_Key' => 'TEST-'.Str::upper(Str::random(20)),
        ]);

        // spy(), bukan shouldReceive(): yang diuji satu panggilan tertentu, dan
        // mock ketat akan meledak pada log lain yang kebetulan lewat.
        Log::spy();

        $terkirim = app(VisitorNotificationService::class)->notifikasiKeputusan(
            VisitorNotificationService::DOMAIN_PERMIT,
            $noTransaksi,
            VisitorNotificationService::KEPUTUSAN_DISETUJUI,
        );

        $this->assertSame(0, $terkirim);
        $this->assertSame(0, DB::table('N_HRIS_Notification')->where('Reference_Id', $noTransaksi)->count());

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $pesan, array $ctx = []) => $pesan === 'VISITOR_NOTIF_KEPUTUSAN_PEMOHON_TIDAK_DIKENAL'
                && ($ctx['no_transaksi'] ?? null) === $noTransaksi);
    }

    /**
     * Penanda yang dicetak dan penanda yang dikenali harus berasal dari satu nilai.
     *
     * Selisih satu karakter di antara keduanya tidak menyalakan galat apa pun:
     * penandanya hanya berhenti dikenali, dan baris yatimnya kembali tertulis.
     */
    public function test_penanda_pemohon_aplikasi_dicetak_dari_konstanta_yang_sama(): void
    {
        $aplikasi = DB::table('N_HRIS_Api_Aplikasi')
            ->whereNull('Kode_Karyawan_PIC')
            ->orWhere('Kode_Karyawan_PIC', '')
            ->first(['Id_Api_Aplikasi']);

        if ($aplikasi === null) {
            $this->markTestSkipped('Tidak ada aplikasi API tanpa PIC untuk diuji.');
        }

        $identitas = app(VisitorPermitApiService::class)->identitasPemohon((int) $aplikasi->Id_Api_Aplikasi);

        $this->assertStringStartsWith(
            VisitorPermitApiService::PREFIX_PEMOHON_APLIKASI,
            $identitas['kode'],
        );
    }

    /**
     * Permit mitra dengan pemohon penanda aplikasi.
     */
    private function buatPermitMitra(): string
    {
        $noTransaksi = 'PMT-TEST-'.Str::upper(Str::random(8));
        $penanda = VisitorPermitApiService::PREFIX_PEMOHON_APLIKASI.'999';

        DB::table('N_EMI_VISITOR_Transaksi_Permit')->insert([
            'No_Transaksi' => $noTransaksi,
            'Kode_Karyawan_Pemohon' => $penanda,
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => $penanda,
            'Idempotency_Key' => 'TEST-'.Str::upper(Str::random(20)),
        ]);

        return $noTransaksi;
    }

    /**
     * @return array{0:string,1:int,2:string}
     */
    private function buatPermit(): array
    {
        $pemohon = $this->pemohonBukanAdmin();
        $noTransaksi = 'PMT-TEST-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $noTransaksi,
            'Kode_Karyawan_Pemohon' => $pemohon,
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => $pemohon,
            'Idempotency_Key' => 'TEST-'.Str::upper(Str::random(20)),
        ]);

        return [$noTransaksi, (int) $id, $pemohon];
    }

    /**
     * @return array{0:string,1:int,2:string}
     */
    private function buatTamu(): array
    {
        $pemohon = $this->pemohonBukanAdmin();
        $noTransaksi = 'VSR-TEST-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $noTransaksi,
            'Kode_Karyawan_PIC' => $pemohon,
            'Status_Kunjungan' => StatusKunjunganTamu::PENDING_APPROVAL,
            'Tanggal_Kunjungan' => now()->addDay()->toDateString(),
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => $pemohon,
            'Idempotency_Key' => 'TEST-'.Str::upper(Str::random(20)),
        ]);

        return [$noTransaksi, (int) $id, $pemohon];
    }

    private function pemohonBukanAdmin(): string
    {
        $admin = app(VisitorNotificationService::class)->penerimaAdmin(VisitorNotificationService::DOMAIN_TAMU);

        $karyawan = DB::table('Karyawan')
            ->whereNotNull('UserID_Web')
            ->whereNotIn('Kode_Karyawan', $admin ?: ['__none__'])
            ->orderBy('Kode_Karyawan')
            ->first(['Kode_Karyawan']);

        $this->assertNotNull($karyawan, 'Tidak ada karyawan non-admin untuk dijadikan pemohon.');

        return trim((string) $karyawan->Kode_Karyawan);
    }
}
