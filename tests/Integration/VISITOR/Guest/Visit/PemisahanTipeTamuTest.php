<?php

namespace Tests\Integration\VISITOR\Guest\Visit;

use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use App\Http\Controllers\VISITOR\Workflow\Verification\VerifikatorVisitorController;
use App\Http\Requests\VISITOR\Workflow\Approval\VisitorApproveRequest;
use App\Services\VISITOR\Guest\Visit\RegistrasiTamuService;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\VehiclePermit\PermitCoklatTamuService;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalDecisionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Support\VISITOR\PermintaanRegistrasiTamu;
use Tests\TestCase;

/**
 * Pemisahan Tamu Regular & B2B (DDL-2026-121) — rantai penuhnya, bukan
 * lapisannya satu per satu: registrasi → pool approval → status tersimpan →
 * status tampil, serta isolasi rute antar tipe.
 *
 * Butuh data-change.sql DDL-2026-121 sudah dijalankan di basis data uji
 * (role ADMIN_TAMU_B2B + config TAMU_B2B).
 */
class PemisahanTipeTamuTest extends TestCase
{
    use DatabaseTransactions;
    use PermintaanRegistrasiTamu;

    private const PIC = 'A1';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.visitor.tamu.needs_approval' => true,
            'services.visitor.tamu_b2b.needs_approval' => true,
        ]);
    }

    public function test_b2b_masuk_pool_admin_tamu_b2b_bukan_admin_tamu(): void
    {
        $nomor = $this->daftarkan(TipeTamu::B2B);

        $header = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->first();
        $this->assertSame(StatusKunjunganTamu::PENDING_APPROVAL, $header->Status_Kunjungan);

        $pool = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ar.Group_Reference_Id')
            ->where('ar.No_Transaksi', $nomor)
            ->distinct()
            ->pluck('rf.Role_Code')
            ->all();

        $this->assertSame(['ADMIN_TAMU_B2B'], $pool);
    }

    public function test_regular_tetap_di_pool_admin_tamu(): void
    {
        $nomor = $this->daftarkan(TipeTamu::REGULAR);

        $pool = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ar.Group_Reference_Id')
            ->where('ar.No_Transaksi', $nomor)
            ->distinct()
            ->pluck('rf.Role_Code')
            ->all();

        $this->assertSame(['ADMIN_TAMU'], $pool);
    }

    public function test_tipe_dari_isian_diabaikan_rute_yang_menentukan(): void
    {
        // Halaman Regular tidak boleh bisa dipakai mendaftarkan tamu B2B hanya
        // dengan menambah satu field.
        $nomor = $this->daftarkan(TipeTamu::REGULAR, ['tipe_tamu' => 'B2B']);

        $this->assertSame(
            TipeTamu::REGULAR,
            DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->value('Tipe_Tamu'),
        );
    }

    public function test_kategori_dan_tanggal_diturunkan_dari_estimasi(): void
    {
        $masuk = now()->addDays(2)->setTime(20, 0);

        $visitor = $this->daftarkan(TipeTamu::REGULAR, [
            'waktu_mulai_kunjungan' => $masuk->format('Y-m-d H:i:s'),
            'waktu_selesai_kunjungan' => $masuk->copy()->addHours(8)->format('Y-m-d H:i:s'),
        ]);
        $mitra = $this->daftarkan(TipeTamu::REGULAR, [
            'waktu_mulai_kunjungan' => $masuk->format('Y-m-d H:i:s'),
            'waktu_selesai_kunjungan' => $masuk->copy()->addHours(26)->format('Y-m-d H:i:s'),
        ]);

        $this->assertSame('VISITOR', $this->detail($visitor)->Tamu_Kategori);
        $this->assertSame('MITRA', $this->detail($mitra)->Tamu_Kategori);
        $this->assertSame(
            $masuk->toDateString(),
            substr((string) DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $mitra)->value('Tanggal_Kunjungan'), 0, 10),
        );
    }

    public function test_b2b_tanpa_kendaraan_ditolak_validasi(): void
    {
        $this->expectException(ValidationException::class);

        $this->permintaanRegistrasiTamu(
            $this->payload(['jenis_kendaraan' => 'Tidak Berkendara', 'plat_prefix' => null, 'plat_angka' => null]),
            self::PIC,
            null,
            TipeTamu::B2B,
        );
    }

    public function test_regular_tanpa_estimasi_ditolak_validasi(): void
    {
        $this->expectException(ValidationException::class);

        $payload = $this->payload();
        unset($payload['waktu_mulai_kunjungan'], $payload['waktu_selesai_kunjungan']);

        $this->permintaanRegistrasiTamu($payload, self::PIC, null, TipeTamu::REGULAR);
    }

    public function test_meja_regular_tidak_melayani_kunjungan_b2b(): void
    {
        $nomor = $this->daftarkan(TipeTamu::B2B);
        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->value('Id_Transaksi_Tamu');

        $regular = $this->permintaanBertipe(TipeTamu::REGULAR);
        $b2b = $this->permintaanBertipe(TipeTamu::B2B);

        $this->assertSame(404, $this->verifikator()->showTamu($regular, (string) $id)->getStatusCode());
        $this->assertSame(200, $this->verifikator()->showTamu($b2b, (string) $id)->getStatusCode());

        $approve = VisitorApproveRequest::create('/verifikator-tamu/'.$nomor.'/approve', 'POST');
        $approve->attributes->set(TipeTamu::ATRIBUT, TipeTamu::REGULAR);

        try {
            $this->verifikator()->approveTamu($approve, $nomor);
            $this->fail('Meja Regular tidak boleh menyetujui kunjungan B2B.');
        } catch (NotFoundHttpException) {
            $this->assertSame(
                StatusKunjunganTamu::PENDING_APPROVAL,
                DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->value('Status_Kunjungan'),
            );
        }
    }

    public function test_status_tersimpan_tolak_tarik_cabut(): void
    {
        $ditolak = $this->daftarkan(TipeTamu::REGULAR);
        // Penolak wajib anggota pool ADMIN_TAMU — mesin approval menolak orang lain.
        $verifikator = (string) DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'ru.UserId_Web')
            ->where('rf.Role_Code', 'ADMIN_TAMU')
            ->where('ru.Flag_Aktif', 'Y')
            ->where('rf.Flag_Aktif', 'Y')
            ->value('k.Kode_Karyawan');
        app(VisitorApprovalDecisionService::class)->rejectTamu($ditolak, $verifikator, 'Uji penolakan');
        $this->assertSame(StatusKunjunganTamu::REJECTED, $this->statusTersimpan($ditolak));

        $ditarik = $this->daftarkan(TipeTamu::REGULAR);
        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $ditarik)->value('Id_Transaksi_Tamu');
        $batal = Request::create('/transaksitamu/cancel/'.$id, 'POST');
        $batal->setUserResolver(fn () => (object) ['Kode_Karyawan' => self::PIC]);
        $batal->attributes->set(TipeTamu::ATRIBUT, TipeTamu::REGULAR);
        app(GuestRegistrationController::class)->cancel($batal, (string) $id);
        $this->assertSame(StatusKunjunganTamu::WITHDRAWN, $this->statusTersimpan($ditarik));

        config(['services.visitor.tamu.needs_approval' => false]);
        $dicabut = $this->daftarkan(TipeTamu::REGULAR);
        $this->assertSame(StatusKunjunganTamu::SCHEDULED, $this->statusTersimpan($dicabut));
        app(VisitorEfektifService::class)->revokeTamuEfektif($dicabut, 'Uji pencabutan', self::PIC);
        $this->assertSame(StatusKunjunganTamu::REVOKED, $this->statusTersimpan($dicabut));
    }

    public function test_tidak_hadir_sampai_estimasi_keluar_jadi_cancelled_bukan_scheduled(): void
    {
        config(['services.visitor.tamu.needs_approval' => false]);

        $nomor = $this->daftarkan(TipeTamu::REGULAR);
        $this->assertSame(StatusKunjunganTamu::SCHEDULED, $this->statusTampil($nomor));

        // Estimasi Keluar digeser ke masa lalu — tamu tak pernah masuk.
        // Selisih dalam hari, bukan menit: SYSDATETIME() server dan now() PHP
        // tidak dijamin sejam.
        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->update([
            'Waktu_Mulai_Kunjungan' => now()->subDays(2),
            'Waktu_Selesai_Kunjungan' => now()->subDay(),
        ]);
        // Seperti setiap penulis jam kunjungan (GuestRegistrationController::update):
        // baris efektif disinkron ulang — akhir aksesnya ikut dibaca siklus.
        app(\App\Services\VISITOR\Operations\Expiration\VisitorEfektifService::class)->syncTamuEfektif($nomor);
        $this->assertSame(StatusKunjunganTamu::CANCELLED, $this->statusTampil($nomor));

        // Yang sudah masuk tidak pernah dibaca tidak hadir.
        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->update([
            'Waktu_Aktual_Masuk' => now()->subDays(2)->addHour(),
        ]);
        $this->assertSame(StatusKunjunganTamu::CHECKED_IN, $this->statusTampil($nomor));
    }

    public function test_mitra_berhari_hari_tidak_dibaca_tidak_hadir_di_hari_kedua(): void
    {
        config(['services.visitor.tamu.needs_approval' => false]);

        $nomor = $this->daftarkan(TipeTamu::REGULAR);
        DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->update([
            'Tanggal_Kunjungan' => now()->subDay()->toDateString(),
        ]);
        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->update([
            'Waktu_Mulai_Kunjungan' => now()->subDay()->setTime(8, 0),
            'Waktu_Selesai_Kunjungan' => now()->addDays(2)->setTime(17, 0),
        ]);

        $this->assertSame(StatusKunjunganTamu::SCHEDULED, $this->statusTampil($nomor));
    }

    public function test_permit_coklat_mitra_berlaku_selama_rentang_estimasi(): void
    {
        $masuk = now()->addDays(3)->setTime(7, 0);
        $keluar = $masuk->copy()->addHours(30);

        $nomor = $this->daftarkan(TipeTamu::B2B, [
            'waktu_mulai_kunjungan' => $masuk->format('Y-m-d H:i:s'),
            'waktu_selesai_kunjungan' => $keluar->format('Y-m-d H:i:s'),
        ]);

        // Meniru titik akhir approveTamu(): kunjungan final, lalu permit diajukan.
        DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->update([
            'Flag_Approval' => 'Y',
            'Status_Kunjungan' => StatusKunjunganTamu::SCHEDULED,
        ]);
        $noPermit = app(PermitCoklatTamuService::class)->sinkron($nomor);

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $noPermit)->first();

        // Satu hari kalender (perilaku lama) membuat gerbang menolak MITRA di hari kedua.
        $this->assertSame($masuk->format('Y-m-d H:i'), substr((string) $detail->Waktu_Mulai_Berlaku, 0, 16));
        // Usulan berakhir 23:59 di hari terakhir kunjungan (29 Sep 2026).
        $this->assertSame($keluar->toDateString().' 23:59', substr((string) $detail->Waktu_Berakhir, 0, 16));
        $this->assertSame('2 Hari', $detail->Snapshot_Nama_Durasi);
        $this->assertSame($keluar->toDateString(), substr((string) $detail->Tanggal_Kadaluarsa, 0, 10));
    }

    public function test_api_mitra_transisi_tanpa_estimasi_tetap_satu_hari(): void
    {
        [$masuk, $keluar] = app(RegistrasiTamuService::class)->rentangEstimasi(['tanggal_kunjungan' => '2026-10-01']);

        $this->assertSame('2026-10-01 00:00:00', $masuk->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-02 00:00:00', $keluar->format('Y-m-d H:i:s'));
    }

    private function daftarkan(string $tipe, array $ubah = []): string
    {
        $request = $this->permintaanRegistrasiTamu(
            $this->payload($ubah),
            self::PIC,
            'PTT-'.Str::upper(Str::random(20)),
            $tipe,
        );

        $isi = json_decode(app(GuestRegistrationController::class)->store($request)->getContent(), true);

        $this->assertNotEmpty($isi['result']['no_transaksi'] ?? null, 'Registrasi gagal: '.json_encode($isi));

        return $isi['result']['no_transaksi'];
    }

    private function payload(array $ubah = []): array
    {
        $masuk = now()->addDay()->setTime(9, 0);

        return array_merge([
            'nama_tamu' => 'Tamu Pemisahan Uji',
            'keperluan' => 'Meeting',
            'pic_tujuan' => 'HRD',
            'instansi_asal' => 'PT Uji',
            'waktu_mulai_kunjungan' => $masuk->format('Y-m-d H:i:s'),
            'waktu_selesai_kunjungan' => $masuk->copy()->addHours(6)->format('Y-m-d H:i:s'),
            // Dari master, bukan karangan: controller menolak jenis/kode plat
            // yang tidak terdaftar aktif.
            'jenis_kendaraan' => DB::table('N_EMI_VISITOR_Master_Vehicle_Type')
                ->where('Flag_Aktif', 'Y')
                ->where(fn ($q) => $q->whereNull('Akses')->orWhereIn('Akses', ['ALL', 'TAMU']))
                // Master memuat baris "Tidak Berkendaraan" — B2B menolaknya.
                ->where('Nama_Tipe_Kendaraan', 'NOT LIKE', '%tidak%')
                ->where('Nama_Tipe_Kendaraan', 'NOT LIKE', '%tanpa%')
                ->orderBy('Id_Vehicle_Type')
                ->value('Nama_Tipe_Kendaraan'),
            'plat_prefix' => DB::table('N_EMI_VISITOR_Master_Plat_Kendaraan')->where('Flag_Aktif', 'Y')->value('Kode_Plat'),
            'plat_angka' => '1234',
            'plat_suffix' => 'UJ',
            'daftar_anggota' => [],
        ], $ubah);
    }

    private function permintaanBertipe(string $tipe): Request
    {
        $request = Request::create('/', 'GET');
        $request->attributes->set(TipeTamu::ATRIBUT, $tipe);

        return $request;
    }

    private function verifikator(): VerifikatorVisitorController
    {
        return app(VerifikatorVisitorController::class);
    }

    private function detail(string $nomor): object
    {
        return DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->first();
    }

    private function statusTersimpan(string $nomor): ?string
    {
        return DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->value('Status_Kunjungan');
    }

    private function statusTampil(string $nomor): string
    {
        $kehadiran = app(VisitorKehadiranQueryService::class);

        return (string) $kehadiran->join(
            DB::table('N_EMI_VISITOR_Transaksi_Tamu as h')
                ->join('N_EMI_VISITOR_Transaksi_Tamu_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
                ->where('h.No_Transaksi', $nomor)
        )->value(DB::raw($kehadiran->siklusKunjunganSql()));
    }
}
