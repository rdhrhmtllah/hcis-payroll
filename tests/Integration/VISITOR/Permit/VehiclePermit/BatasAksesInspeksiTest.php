<?php

namespace Tests\Integration\VISITOR\Permit\VehiclePermit;

use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQuotaExceeded;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQuotaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Membatasi eksploitasi akses palang sementara.
 *
 * Akses inspeksi diterbitkan SAAT SUBMIT, jauh sebelum permitnya diputus.
 * Sementara itu 'REJECTED' membebaskan jatah permit sepenuhnya. Kedua hal itu
 * bersama membentuk putaran: ajukan -> dapat jendela gerbang -> ditolak ->
 * jatah kembali penuh -> ajukan lagi -> jendela baru, tanpa batas. Penolakan
 * justru menjadi cara tercepat mendapatkan jendela berikutnya.
 *
 * Dua batas baru menutupnya dari sumbu yang berbeda, dan keduanya diuji di
 * sini bersama batas lama supaya ketiganya tidak saling menelan:
 *
 * - max_pengajuan_pending  : berapa boleh MENUNGGU KEPUTUSAN sekaligus.
 * - max_akses_inspeksi_harian : berapa permit boleh mendapat akses HARI INI.
 */
class BatasAksesInspeksiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_batas_pending_menghitung_yang_belum_diputus_saja(): void
    {
        // Permit yang SUDAH disetujui tidak lagi menahan keputusan siapa pun,
        // jadi ia tidak boleh ikut menghabiskan jatah pending - kalau ikut,
        // pemohon yang permitnya disetujui justru terkunci.
        $kode = $this->karyawanBersih();

        $this->buatPermit($kode, disetujui: true);
        $this->assertSame(0, $this->kuota()->pendingTerpakai($kode), 'Permit yang sudah disetujui ikut terhitung menunggu keputusan.');

        $this->buatPermit($kode, disetujui: false);
        $this->assertSame(1, $this->kuota()->pendingTerpakai($kode));
    }

    public function test_pengajuan_kedua_ditolak_saat_satu_masih_menunggu(): void
    {
        $kode = $this->karyawanBersih();
        $this->buatPermit($kode, disetujui: false);

        $this->expectException(VisitorPermitQuotaExceeded::class);

        DB::transaction(fn () => $this->kuota()->assertAvailableWithinTransaction($kode));
    }

    public function test_kode_galatnya_membedakan_sebab(): void
    {
        // Jalan keluarnya berbeda - menunggu keputusan, menunggu besok, atau
        // memang sudah punya cukup permit - jadi layar harus bisa membedakannya.
        $kode = $this->karyawanBersih();
        $this->buatPermit($kode, disetujui: false);

        try {
            DB::transaction(fn () => $this->kuota()->assertAvailableWithinTransaction($kode));
            $this->fail('Batas pending tidak menggigit.');
        } catch (VisitorPermitQuotaExceeded $e) {
            $this->assertSame('VISITOR_PERMIT_PENDING_LIMIT', $e->kode);
        }
    }

    public function test_akses_inspeksi_harian_dihitung_per_permit_bukan_per_booking(): void
    {
        // Menggeser jadwal membuat baris booking baru tetapi TIDAK menerbitkan
        // jendela gerbang tambahan. Menghitung baris akan menghukum orang yang
        // menggeser jadwalnya, bukan orang yang mengulang pengajuan.
        $kode = $this->karyawanBersih();
        $nomor = $this->buatPermit($kode, disetujui: false);

        $this->buatBooking($nomor, JadwalInspeksiService::DIPINDAH);
        $this->buatBooking($nomor, JadwalInspeksiService::TERJADWAL);

        $this->assertSame(1, $this->kuota()->aksesInspeksiHariIni($kode), 'Dua booking pada satu permit terhitung dua akses.');
    }

    public function test_akses_kemarin_tidak_ikut_terhitung_hari_ini(): void
    {
        // Batasnya HARIAN. Tanpa penjaga ini, orang yang pernah gagal dua kali
        // minggu lalu tidak akan pernah bisa mengajukan lagi.
        $kode = $this->karyawanBersih();
        $nomor = $this->buatPermit($kode, disetujui: true);

        $this->buatBooking($nomor, JadwalInspeksiService::TERJADWAL, now()->subDay());

        $this->assertSame(0, $this->kuota()->aksesInspeksiHariIni($kode));
    }

    public function test_batas_harian_menutup_putaran_tolak_lalu_ajukan_lagi(): void
    {
        $kode = $this->karyawanBersih();

        // Dua percobaan hari ini, keduanya sudah DITOLAK - jatah permit dan
        // jatah pending sama-sama kembali kosong, persis celahnya.
        foreach ([1, 2] as $ke) {
            $nomor = $this->buatPermit($kode, disetujui: false, ditolak: true);
            $this->buatBooking($nomor, JadwalInspeksiService::TIDAK_HADIR);
        }

        $this->assertSame(0, $this->kuota()->terpakai($kode), 'Prasyarat gagal: permit yang ditolak masih memakai jatah.');
        $this->assertSame(0, $this->kuota()->pendingTerpakai($kode), 'Prasyarat gagal: permit yang ditolak masih menunggu keputusan.');
        $this->assertSame(2, $this->kuota()->aksesInspeksiHariIni($kode));

        try {
            DB::transaction(fn () => $this->kuota()->assertAvailableWithinTransaction($kode));
            $this->fail('Putaran tolak-lalu-ajukan-lagi masih terbuka.');
        } catch (VisitorPermitQuotaExceeded $e) {
            $this->assertSame('VISITOR_PERMIT_AKSES_HARIAN', $e->kode);
        }
    }

    public function test_ambang_inspeksi_dibaca_dari_config(): void
    {
        config(['services.visitor.permit.maks_tidak_hadir' => 5]);
        config(['services.visitor.permit.maks_jadwal_ulang' => 4]);
        config(['services.visitor.permit.jeda_minimum_menit' => 45]);

        $this->assertSame(5, JadwalInspeksiService::maksTidakHadir());
        $this->assertSame(4, JadwalInspeksiService::maksJadwalUlang());
        $this->assertSame(45, JadwalInspeksiService::jedaMinimumMenit());
    }

    // ---------------------------------------------------------------- helpers

    private function kuota(): VisitorPermitQuotaService
    {
        return app(VisitorPermitQuotaService::class);
    }

    /** Karyawan yang belum punya permit sama sekali, supaya angkanya bersih. */
    private function karyawanBersih(): string
    {
        $kode = DB::table('Karyawan as k')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('N_EMI_VISITOR_Transaksi_Permit as h')
                    ->whereColumn('h.Kode_Karyawan_Pemohon', 'k.Kode_Karyawan');
            })
            // Belum resign: permit karyawan yang lewat hari terakhirnya terbaca EXPIRED.
            ->whereNull('k.Tanggal_Resign')
            ->orderBy('k.Kode_Karyawan')
            ->value('k.Kode_Karyawan');

        $this->assertNotNull($kode, 'Tidak ada karyawan tanpa permit untuk dijadikan subjek uji.');

        return (string) $kode;
    }

    private function buatPermit(string $kode, bool $disetujui, bool $ditolak = false): string
    {
        $nomor = 'PMT-BTS-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => $kode,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => $ditolak ? 'T' : ($disetujui ? 'Y' : null),
            'Approved_At' => $disetujui ? now()->subDay() : null,
            'Created_At' => now(),
            'Created_By' => $kode,
            'Idempotency_Key' => 'BTS-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'No_Transaksi' => $nomor,
            'Id_Transaksi_Permit' => $id,
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(100000, 999999),
            'Plat_Nomor_Suffix' => 'BT',
            'Tanggal_Mulai_Berlaku' => now()->subDay()->toDateString(),
            'Tanggal_Kadaluarsa' => now()->addMonths(3)->toDateString(),
        ]);

        // Tanpa baris approval, permit yang belum diputus terbaca 'NO_FLOW' -
        // bukan 'PENDING'. Keduanya sama-sama menunggu keputusan, tetapi uji
        // yang hanya memakai salah satunya akan luput pada jalur sebelahnya.
        if (! $disetujui && ! $ditolak) {
            DB::table('HRIS_Approval_Request')->insert([
                'No_Transaksi' => $nomor,
                'Kode_Karyawan' => $kode,
                'Request_Order_Flow' => 1,
                'Approver_Karyawan_Kode' => $kode,
                'Flag_Approval' => null,
                'created_at' => now(),
                'update_at' => now(),
            ]);
        }

        return $nomor;
    }

    private function buatBooking(string $noPermit, string $status, ?\Carbon\Carbon $dibuat = null): void
    {
        DB::table('N_VISITOR_Inspeksi_Booking')->insert([
            'No_Transaksi_Permit' => $noPermit,
            'Id_Pos_Inspeksi' => DB::table(JadwalInspeksiService::T_POS)->where('Flag_Aktif', 'Y')->value('Id_Pos_Inspeksi'),
            'Snapshot_Nama_Pos' => 'Pos Uji Batas',
            'Waktu_Mulai' => now()->addDay()->setTime(9, 0),
            'Waktu_Selesai' => now()->addDay()->setTime(9, 30),
            'Status' => $status,
            'Created_At' => $dibuat ?? now(),
            'Created_By' => 'UJI',
        ]);
    }
}
