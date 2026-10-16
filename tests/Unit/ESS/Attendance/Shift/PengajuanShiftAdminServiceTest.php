<?php

namespace Tests\Unit\ESS\Attendance\Shift;

use App\Services\ESS\Attendance\Shift\Admin\PengajuanShiftAdminService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PengajuanShiftAdminServiceTest extends TestCase
{
    /**
     * Status satu baris (satu karyawan dalam satu transaksi) diturunkan dari
     * hitungan tanggal AKTIF-nya: terap = sudah masuk jadwal, tunda = belum
     * diterapkan & tidak ditolak.
     */
    public function test_status_diturunkan_dari_hitungan_tanggal(): void
    {
        $s = fn (bool $batal, int $aktif, int $terap, int $tunda) => PengajuanShiftAdminService::statusDariHitungan($batal, $aktif, $terap, $tunda);

        $this->assertSame('DIBATALKAN', $s(true, 3, 0, 3));
        $this->assertSame('DIBATALKAN', $s(false, 0, 0, 0), 'semua tanggal dibatalkan per baris');
        $this->assertSame('MENUNGGU', $s(false, 2, 0, 2));
        $this->assertSame('SEBAGIAN', $s(false, 3, 1, 2));
        $this->assertSame('DITERAPKAN', $s(false, 2, 2, 0));
        $this->assertSame('DITERAPKAN', $s(false, 3, 2, 0), 'sisanya ditolak, yang diterapkan tetap berlaku');
        $this->assertSame('DITOLAK', $s(false, 2, 0, 0));
    }

    public function test_hanya_yang_masih_punya_tanggal_tertunda_yang_boleh_dibatalkan(): void
    {
        foreach (['MENUNGGU', 'SEBAGIAN'] as $st) {
            $this->assertTrue(PengajuanShiftAdminService::bolehDibatalkan($st), $st);
        }
        foreach (['DITERAPKAN', 'DITOLAK', 'DIBATALKAN'] as $st) {
            $this->assertFalse(PengajuanShiftAdminService::bolehDibatalkan($st), $st);
        }
    }

    public function test_catatan_batal_berpenanda_dan_muat_di_kolom_terkecil(): void
    {
        $c = PengajuanShiftAdminService::catatanBatal("  Salah\n pilih shift  ");

        $this->assertSame('Dibatalkan admin: Salah pilih shift', $c);
        $this->assertTrue(PengajuanShiftAdminService::apakahBatalAdmin($c));
        $this->assertFalse(PengajuanShiftAdminService::apakahBatalAdmin('Ditolak atasan'));
        $this->assertFalse(PengajuanShiftAdminService::apakahBatalAdmin(null));
        // Note_Tolak varchar(200) di Tukar Hari & Status Hari — kolom terkecil.
        $this->assertSame(200, mb_strlen(PengajuanShiftAdminService::catatanBatal(str_repeat('x', 500))));
    }

    public function test_alasan_terlalu_pendek_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PengajuanShiftAdminService::catatanBatal(' ok ');
    }

    public function test_jenis_transaksi_dikenali_dari_nomornya(): void
    {
        $this->assertSame('STATUS_HARI', PengajuanShiftAdminService::jenisDariNomor('TSH10-26-0001'));
        $this->assertSame('TUKAR_HARI', PengajuanShiftAdminService::jenisDariNomor('TH10-26-0001'));
        $this->assertSame('GANTI_SHIFT', PengajuanShiftAdminService::jenisDariNomor('SH10-26-0008'));
    }

    public function test_sumber_pengajuan_dari_jenis_transaksi_dan_flag_admin(): void
    {
        $this->assertSame('KARYAWAN', PengajuanShiftAdminService::sumberDari('SELF_SHIFT', 'T'));
        $this->assertSame('KARYAWAN', PengajuanShiftAdminService::sumberDari('TUKAR_HARI', null));
        $this->assertSame('LEADER', PengajuanShiftAdminService::sumberDari('LEADER_SHIFT', 'T'));
        $this->assertSame('LEADER', PengajuanShiftAdminService::sumberDari('STATUS_HARI', null));
        $this->assertSame('ADMIN', PengajuanShiftAdminService::sumberDari('LEADER_SHIFT', 'Y'));
        $this->assertSame('ADMIN', PengajuanShiftAdminService::sumberDari('STATUS_HARI', 'Y'));
    }

    public function test_filter_dinormalkan_dan_nilai_asing_dibuang(): void
    {
        $f = (new PengajuanShiftAdminService)->normalisasiFilter([
            'status' => 'sebagian',
            'jenis' => 'status_hari',
            'sumber' => 'leader',
            'tanggal_dari' => '2026-10-01',
            'tanggal_sampai' => '2026-02-31',
            'divisi' => [' Produksi ', 'Produksi', '', ['x']],
            'lokasi' => 'EMI',
            'page' => '0',
            'limit' => '500',
            'urut' => 'hapus',
            'arah' => 'ASC',
        ]);

        $this->assertSame('SEBAGIAN', $f['status']);
        $this->assertSame('STATUS_HARI', $f['jenis']);
        $this->assertSame('LEADER', $f['sumber']);
        $this->assertSame('2026-10-01', $f['tanggal_dari']);
        $this->assertNull($f['tanggal_sampai']);
        $this->assertSame(['Produksi'], $f['divisi']);
        $this->assertSame(['EMI'], $f['lokasi']);
        $this->assertSame([], $f['jabatan']);
        $this->assertSame(1, $f['page']);
        $this->assertSame(100, $f['limit']);
        $this->assertSame('dibuat', $f['urut']);
        $this->assertSame('asc', $f['arah']);

        $kosong = (new PengajuanShiftAdminService)->normalisasiFilter(['status' => 'DROP', 'sumber' => 'robot']);
        $this->assertNull($kosong['status']);
        $this->assertNull($kosong['sumber']);
    }

    /** Saringan jenis/sumber memangkas subkueri yang tak mungkin cocok. */
    public function test_sumber_yang_dihitung_mengikuti_saringan(): void
    {
        $f = fn (array $x) => (new PengajuanShiftAdminService)->normalisasiFilter($x);

        $this->assertSame(['GANTI_SHIFT', 'STATUS_HARI', 'TUKAR_HARI'], PengajuanShiftAdminService::sumberDihitung($f([])));
        $this->assertSame(['TUKAR_HARI'], PengajuanShiftAdminService::sumberDihitung($f(['jenis' => 'TUKAR_HARI'])));
        // Tukar Hari hanya diajukan karyawan; Status Hari hanya leader/admin.
        $this->assertSame(['GANTI_SHIFT', 'TUKAR_HARI'], PengajuanShiftAdminService::sumberDihitung($f(['sumber' => 'KARYAWAN'])));
        $this->assertSame(['GANTI_SHIFT', 'STATUS_HARI'], PengajuanShiftAdminService::sumberDihitung($f(['sumber' => 'ADMIN'])));
        $this->assertSame([], PengajuanShiftAdminService::sumberDihitung($f(['sumber' => 'KARYAWAN', 'jenis' => 'STATUS_HARI'])));
    }
}
