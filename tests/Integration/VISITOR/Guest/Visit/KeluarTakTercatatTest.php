<?php

namespace Tests\Integration\VISITOR\Guest\Visit;

use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tamu yang tercatat masuk tetapi tak pernah tercatat keluar, dan kunjungannya
 * sudah berakhir di gerbang, tidak boleh terbaca "masih di dalam" selamanya
 * (celah #5, keputusan user 26 Sep 2026: opsi b — tanpa jam keluar palsu).
 */
class KeluarTakTercatatTest extends TestCase
{
    use DatabaseTransactions;

    /** Kode karyawan NYATA di Web_HRIS development (Nama_PIC di proyeksi di-resolve lewat Karyawan). */
    private const PIC = 'A1';

    public function test_masuk_tanpa_keluar_setelah_kunjungan_berakhir_terbaca_keluar_tak_tercatat(): void
    {
        $nomor = $this->kunjungan();
        $this->efektif($nomor, masuk: now()->subDays(2), status: 'EXPIRED');

        $this->assertSame(StatusKunjunganTamu::KELUAR_TAK_TERCATAT, $this->siklus($nomor));
        $this->assertSame('Keluar Tak Tercatat', StatusKunjunganTamu::label(StatusKunjunganTamu::KELUAR_TAK_TERCATAT));
    }

    public function test_masuk_tanpa_keluar_selama_kunjungan_berjalan_tetap_di_dalam(): void
    {
        $nomor = $this->kunjungan();
        $this->efektif($nomor, masuk: now()->subHour(), status: 'AKTIF');

        $this->assertSame(StatusKunjunganTamu::CHECKED_IN, $this->siklus($nomor));
    }

    public function test_jam_keluar_tidak_diisi_palsu(): void
    {
        $nomor = $this->kunjungan();
        $this->efektif($nomor, masuk: now()->subDays(2), status: 'EXPIRED');

        $this->siklus($nomor);

        $this->assertNull(DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->value('Waktu_Aktual_Keluar'));
    }

    public function test_keluar_tak_tercatat_tidak_dihitung_di_dalam_area(): void
    {
        // Monitoring (siklus) dan hitungan "di dalam" wajib sepakat.
        $nomor = $this->kunjungan();
        $this->efektif($nomor, masuk: now()->subDays(2), status: 'EXPIRED');

        $this->assertFalse($this->diDalam($nomor), 'Tamu keluar-tak-tercatat masih dihitung di dalam area.');
        $this->assertNotContains($nomor, collect(app(\App\Services\VISITOR\Workflow\Reporting\Monitoring\VisitorPapanHarianService::class)->rakit('tamu')['sedang_di_dalam'])->pluck('No_Transaksi')->all());
    }

    public function test_tamu_yang_masih_berkunjung_tetap_di_dalam_area(): void
    {
        $nomor = $this->kunjungan();
        $this->efektif($nomor, masuk: now()->subHour(), status: 'AKTIF');

        $this->assertTrue($this->diDalam($nomor));
    }

    private function diDalam(string $nomor): bool
    {
        $kehadiran = app(VisitorKehadiranQueryService::class);

        return $kehadiran->join(
            DB::table('N_EMI_VISITOR_Transaksi_Tamu as h')
                ->join('N_EMI_VISITOR_Transaksi_Tamu_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
                ->where('h.No_Transaksi', $nomor)
        )->whereRaw($kehadiran->diDalamSql())->exists();
    }

    private function siklus(string $nomor): string
    {
        $kehadiran = app(VisitorKehadiranQueryService::class);

        return (string) $kehadiran->join(
            DB::table('N_EMI_VISITOR_Transaksi_Tamu as h')
                ->join('N_EMI_VISITOR_Transaksi_Tamu_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
                ->where('h.No_Transaksi', $nomor)
        )->selectRaw($kehadiran->siklusKunjunganSql().' as Siklus')->value('Siklus');
    }

    private function efektif(string $nomor, \Carbon\Carbon $masuk, string $status): void
    {
        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);
        DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)
            ->update(['Waktu_Aktual_Masuk' => $masuk, 'Waktu_Aktual_Keluar' => null, 'Status_Efektif' => $status]);
    }

    private function kunjungan(): string
    {
        $nomor = 'VSR-KTT-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => StatusKunjunganTamu::SCHEDULED,
            'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => now()->toDateString(),
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'KTT-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Tamu Tak Keluar',
            'Tipe_Tamu' => 'REGULAR',
            'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Meeting',
            'Jumlah_Tamu' => 1,
            'Jenis_Kendaraan' => 'Tidak Berkendara',
        ]);

        return $nomor;
    }
}
