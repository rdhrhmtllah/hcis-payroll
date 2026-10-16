<?php

namespace Tests\Integration\VISITOR\Workflow\Notification;

use App\Http\Controllers\VISITOR\Workflow\Verification\VerifikatorVisitorController;
use App\Http\Requests\VISITOR\Workflow\Approval\VisitorApproveRequest;
use App\Http\Requests\VISITOR\Workflow\Approval\VisitorRejectRequest;
use App\Models\ESS\Employee\Models\User;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalDecisionService;
use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Menguji SAMBUNGAN di controller: apakah keputusan verifikator menerbitkan
 * notifikasi ke pemohon. Mesin keputusannya (VisitorApprovalDecisionService)
 * digantikan stub karena menyiapkan approval flow + checklist + master durasi
 * yang sungguhan tidak menguji apa pun tentang sambungan ini. Yang diperiksa
 * tetap efek nyata: baris di N_HRIS_Notification.
 */
class VerifikatorDecisionNotifiesRequesterTest extends TestCase
{
    use DatabaseTransactions;
    use MenegaskanTautanDetail;

    public function test_permit_disetujui_final_memberi_notif_ke_pemohon(): void
    {
        [$noTransaksi, $id, $pemohon] = $this->buatPermit();
        $this->stubKeputusan(permitFinal: true);

        $this->controller()->approvePermit($this->approveRequest(), $noTransaksi);

        $row = $this->notifTunggal($noTransaksi);
        $this->assertSame($pemohon, trim((string) $row->Kode_Karyawan));
        $this->assertTautanDetail($row->Action_Url, '/transaksipermit', VisitorNotificationService::DOMAIN_PERMIT, $id);
        $this->assertSame('success', $row->Severity);
    }

    public function test_permit_yang_masih_diteruskan_belum_memberi_notif(): void
    {
        [$noTransaksi] = $this->buatPermit();
        $this->stubKeputusan(permitFinal: false);

        $this->controller()->approvePermit($this->approveRequest(), $noTransaksi);

        $this->assertSame(
            0,
            DB::table('N_HRIS_Notification')->where('Reference_Id', $noTransaksi)->count(),
            'Approval bertahap belum berarti "disetujui dan aktif"; pemohon belum boleh diberi tahu.',
        );
    }

    public function test_permit_ditolak_memberi_notif_beserta_alasan(): void
    {
        [$noTransaksi, $id, $pemohon] = $this->buatPermit();
        $this->stubKeputusan();

        $this->controller()->rejectPermit($this->rejectRequest('Dokumen STNK kedaluwarsa'), $noTransaksi);

        $row = $this->notifTunggal($noTransaksi);
        $this->assertSame($pemohon, trim((string) $row->Kode_Karyawan));
        $this->assertTautanDetail($row->Action_Url, '/transaksipermit', VisitorNotificationService::DOMAIN_PERMIT, $id);
        $this->assertSame('danger', $row->Severity);
        $this->assertStringContainsString('Dokumen STNK kedaluwarsa', (string) $row->Message);
    }

    public function test_permit_dicabut_memberi_notif_peringatan(): void
    {
        [$noTransaksi, , $pemohon] = $this->buatPermit();
        $this->stubEfektif(affected: 1);

        $this->controller()->revokePermit($this->revokeRequest('Kendaraan sudah dijual'), $noTransaksi);

        $row = $this->notifTunggal($noTransaksi);
        $this->assertSame($pemohon, trim((string) $row->Kode_Karyawan));
        $this->assertSame('warning', $row->Severity);
        $this->assertStringContainsString('Kendaraan sudah dijual', (string) $row->Message);
    }

    public function test_pencabutan_yang_tak_mengenai_apa_pun_tidak_memberi_notif(): void
    {
        [$noTransaksi] = $this->buatPermit();
        $this->stubEfektif(affected: 0);

        $this->controller()->revokePermit($this->revokeRequest('Salah sasaran'), $noTransaksi);

        $this->assertSame(
            0,
            DB::table('N_HRIS_Notification')->where('Reference_Id', $noTransaksi)->count(),
            'Tidak ada akses yang dicabut, jadi tidak ada kabar untuk pemohon.',
        );
    }

    public function test_tamu_disetujui_final_memberi_notif_ke_pemohon(): void
    {
        [$noTransaksi, $id, $pemohon] = $this->buatTamu();
        $this->stubKeputusan(tamuFinal: true);

        $this->controller()->approveTamu($this->approveRequest(), $noTransaksi);

        $row = $this->notifTunggal($noTransaksi);
        $this->assertSame($pemohon, trim((string) $row->Kode_Karyawan));
        $this->assertTautanDetail($row->Action_Url, '/transaksitamu', VisitorNotificationService::DOMAIN_TAMU, $id);
        $this->assertSame('success', $row->Severity);
    }

    public function test_tamu_ditolak_memberi_notif_beserta_alasan(): void
    {
        [$noTransaksi] = $this->buatTamu();
        $this->stubKeputusan();

        $this->controller()->rejectTamu($this->rejectRequest('Jadwal bentrok'), $noTransaksi);

        $row = $this->notifTunggal($noTransaksi);
        $this->assertSame('danger', $row->Severity);
        $this->assertStringContainsString('Jadwal bentrok', (string) $row->Message);
    }

    public function test_tamu_dicabut_memberi_notif_peringatan(): void
    {
        [$noTransaksi] = $this->buatTamu();
        $this->stubEfektif(affected: 1);

        $this->controller()->revokeTamu($this->revokeRequest('Tamu batal datang'), $noTransaksi);

        $row = $this->notifTunggal($noTransaksi);
        $this->assertSame('warning', $row->Severity);
        $this->assertStringContainsString('Tamu batal datang', (string) $row->Message);
    }

    public function test_tamu_b2b_disetujui_di_meja_b2b_menautkan_halaman_b2b(): void
    {
        // DDL-2026-121: keputusan dari meja B2B memakai domain TAMU_B2B —
        // pemohon dibawa ke halaman pengajuan B2B, tetapi token detailnya
        // tetap bertanda domain TAMU (barisnya di tabel yang sama).
        [$noTransaksi, $id, $pemohon] = $this->buatTamu(TipeTamu::B2B);
        $this->stubKeputusan(tamuFinal: true);

        $this->controller()->approveTamu($this->approveRequest(TipeTamu::B2B), $noTransaksi);

        $row = $this->notifTunggal($noTransaksi);
        $this->assertSame($pemohon, trim((string) $row->Kode_Karyawan));
        $this->assertTautanDetail($row->Action_Url, '/transaksi-tamu-b2b', VisitorNotificationService::DOMAIN_TAMU, $id);
        $this->assertStringContainsString('Tamu B2B', (string) $row->Title);
        $this->assertSame('success', $row->Severity);
    }

    public function test_tamu_b2b_ditolak_di_meja_b2b_menautkan_halaman_b2b(): void
    {
        [$noTransaksi, $id] = $this->buatTamu(TipeTamu::B2B);
        $this->stubKeputusan();

        $this->controller()->rejectTamu($this->rejectRequest('Kendaraan tidak sesuai', TipeTamu::B2B), $noTransaksi);

        $row = $this->notifTunggal($noTransaksi);
        $this->assertTautanDetail($row->Action_Url, '/transaksi-tamu-b2b', VisitorNotificationService::DOMAIN_TAMU, $id);
        $this->assertSame('danger', $row->Severity);
    }

    public function test_meja_regular_menolak_memutus_kunjungan_b2b(): void
    {
        // Rute Regular dan B2B berbagi method; tanpa penjaga tipe, pemegang
        // kunci Regular bisa memutus kunjungan B2B lewat endpoint Regular.
        [$noTransaksi] = $this->buatTamu(TipeTamu::B2B);
        $this->stubKeputusan(tamuFinal: true);

        try {
            $this->controller()->approveTamu($this->approveRequest(), $noTransaksi);
            $this->fail('Meja Regular berhasil menyetujui kunjungan B2B.');
        } catch (NotFoundHttpException) {
            // Diharapkan 404, bukan 403: keberadaannya pun bukan urusan meja ini.
        }

        $this->assertSame(0, DB::table('N_HRIS_Notification')->where('Reference_Id', $noTransaksi)->count());
    }

    public function test_meja_b2b_menolak_mencabut_kunjungan_regular(): void
    {
        [$noTransaksi] = $this->buatTamu();
        $this->stubEfektif(affected: 1);

        try {
            $this->controller()->revokeTamu($this->revokeRequest('Salah meja', TipeTamu::B2B), $noTransaksi);
            $this->fail('Meja B2B berhasil mencabut kunjungan Regular.');
        } catch (NotFoundHttpException) {
            // Diharapkan.
        }

        $this->assertSame(0, DB::table('N_HRIS_Notification')->where('Reference_Id', $noTransaksi)->count());
    }

    // ---------------------------------------------------------------- helpers

    private function notifTunggal(string $noTransaksi): object
    {
        $rows = DB::table('N_HRIS_Notification')->where('Reference_Id', $noTransaksi)->get();
        $this->assertCount(1, $rows, 'Harus tepat satu notifikasi untuk pemohon.');

        return $rows->first();
    }

    private function controller(): VerifikatorVisitorController
    {
        return app(VerifikatorVisitorController::class);
    }

    private function stubKeputusan(bool $permitFinal = true, bool $tamuFinal = true): void
    {
        $this->instance(
            VisitorApprovalDecisionService::class,
            new class($permitFinal, $tamuFinal) extends VisitorApprovalDecisionService
            {
                public function __construct(private bool $permitFinal, private bool $tamuFinal) {}

                public function approvePermit(string $n, string $a, array $d, bool $c): bool
                {
                    return $this->permitFinal;
                }

                // Nilai bawaan WAJIB ikut disalin. Tanpanya PHP menolak seluruh
                // kelas anonim ini dengan fatal "must be compatible", dan
                // fatalnya menggugurkan SATU BERKAS uji penuh sebelum satu pun
                // assertion sempat berjalan.
                public function approveTamu(string $n, string $a, ?string $c = null): bool
                {
                    return $this->tamuFinal;
                }

                public function rejectPermit(string $n, string $r, string $alasan): void {}

                public function rejectTamu(string $n, string $r, string $alasan): void {}
            },
        );
    }

    private function stubEfektif(int $affected): void
    {
        $this->instance(
            VisitorEfektifService::class,
            new class($affected) extends VisitorEfektifService
            {
                public function __construct(private int $affected) {}

                public function revokePermitEfektif(string $n, ?string $alasan, string $oleh): int
                {
                    return $this->affected;
                }

                public function revokeTamuEfektif(string $n, ?string $alasan, string $oleh): int
                {
                    return $this->affected;
                }
            },
        );
    }

    private function approveRequest(?string $tipe = null): VisitorApproveRequest
    {
        return $this->formRequest(VisitorApproveRequest::class, ['catatan' => 'Sesuai'], $tipe);
    }

    private function rejectRequest(string $alasan, ?string $tipe = null): VisitorRejectRequest
    {
        return $this->formRequest(VisitorRejectRequest::class, ['alasan' => $alasan], $tipe);
    }

    private function revokeRequest(string $alasan, ?string $tipe = null): Request
    {
        return $this->requestUntuk(Request::class, ['alasan' => $alasan], $tipe);
    }

    /**
     * FormRequest sungguhan, sudah tervalidasi — controller menuntut tipe ini
     * dan memanggil validated() di atasnya.
     *
     * @template T of FormRequest
     *
     * @param  class-string<T>  $kelas
     * @return T
     */
    private function formRequest(string $kelas, array $payload, ?string $tipe = null): FormRequest
    {
        $request = $this->requestUntuk($kelas, $payload, $tipe);
        $request->setContainer(app())->setRedirector(app(Redirector::class));
        $request->validateResolved();

        return $request;
    }

    /**
     * `$tipe` meniru middleware visitor.tipe-tamu:<TIPE> (DDL-2026-121). Null =
     * tanpa penetapan, dibaca REGULAR seperti rute /verifikator-tamu.
     *
     * @template T of Request
     *
     * @param  class-string<T>  $kelas
     * @return T
     */
    private function requestUntuk(string $kelas, array $payload, ?string $tipe = null): Request
    {
        $verifikator = User::find(624);
        $this->assertNotNull($verifikator, 'User verifikator (624) tidak ditemukan.');

        $request = $kelas::create('/verifikator-visitor', 'POST', $payload);
        $request->setUserResolver(fn () => $verifikator);

        if ($tipe !== null) {
            $request->attributes->set(TipeTamu::ATRIBUT, TipeTamu::sah($tipe));
        }

        return $request;
    }

    /**
     * @return array{0:string,1:int,2:string}
     */
    private function buatPermit(): array
    {
        $pemohon = $this->pemohon();
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
     * Baris detail WAJIB ada: sejak DDL-2026-121 setiap aksi verifikator tamu
     * mencocokkan Tipe_Tamu di detail dengan tipe rute, dan header tanpa detail
     * dibalas 404 sebelum sambungan notifikasinya sempat diuji.
     *
     * @return array{0:string,1:int,2:string}
     */
    private function buatTamu(string $tipe = TipeTamu::REGULAR): array
    {
        $pemohon = $this->pemohon();
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

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $noTransaksi,
            'Nama_Lengkap_Tamu' => 'Tamu Notif',
            'Tipe_Tamu' => $tipe,
            'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Meeting',
            'Jumlah_Tamu' => 1,
            'Jenis_Kendaraan' => 'Tidak Berkendara',
        ]);

        return [$noTransaksi, (int) $id, $pemohon];
    }

    private function pemohon(): string
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
