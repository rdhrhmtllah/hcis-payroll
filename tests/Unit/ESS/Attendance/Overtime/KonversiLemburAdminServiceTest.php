<?php

namespace Tests\Unit\ESS\Attendance\Overtime;

use App\Services\ESS\Attendance\Overtime\Conversion\KonversiLemburAdminService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class KonversiLemburAdminServiceTest extends TestCase
{
    public function test_catatan_batal_memakai_penanda_dan_muat_di_kolom_note_tolak(): void
    {
        $catatan = KonversiLemburAdminService::catatanBatal('  Salah input tanggal lembur  ');

        $this->assertSame('Dibatalkan admin: Salah input tanggal lembur', $catatan);
        $this->assertTrue(KonversiLemburAdminService::apakahBatalAdmin($catatan));
        $this->assertFalse(KonversiLemburAdminService::apakahBatalAdmin('Ditolak'));
        $this->assertFalse(KonversiLemburAdminService::apakahBatalAdmin(null));

        // Note_Tolak varchar(200): alasan panjang dipotong, bukan ditolak SQL Server.
        $this->assertSame(200, mb_strlen(KonversiLemburAdminService::catatanBatal(str_repeat('x', 500))));
    }

    public function test_alasan_terlalu_pendek_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        KonversiLemburAdminService::catatanBatal('ok');
    }

    public function test_filter_dinormalkan_dan_nilai_asing_dibuang(): void
    {
        $svc = new KonversiLemburAdminService;

        $f = $svc->normalisasiFilter([
            'status' => 'diterapkan',
            'jenis' => 'hapus-semua',
            'tanggal_dari' => '2026-10-01',
            'tanggal_sampai' => 'bukan-tanggal',
            'page' => '-3',
            'limit' => '9999',
            'search' => '  Budi  ',
            'urut' => 'DROP TABLE',
        ]);

        $this->assertSame('DITERAPKAN', $f['status']);
        $this->assertNull($f['jenis']);
        $this->assertSame('2026-10-01', $f['tanggal_dari']);
        $this->assertNull($f['tanggal_sampai']);
        $this->assertSame(1, $f['page']);
        $this->assertSame(100, $f['limit']);
        $this->assertSame('Budi', $f['search']);
        $this->assertSame('dibuat', $f['urut']);
    }

    public function test_saringan_organisasi_jadi_daftar_bersih_tanpa_duplikat(): void
    {
        $svc = new KonversiLemburAdminService;

        $f = $svc->normalisasiFilter([
            'divisi' => ['  Produksi ', '', 'Produksi', null, ['bersarang']],
            'departemen' => 'Gudang',
            'level' => [],
            'jabatan' => ['Operator', 'Leader'],
            'lokasi' => ['EMI'],
        ]);

        $this->assertSame(['Produksi'], $f['divisi']);
        // Nilai tunggal (mis. dari tautan) tetap diterima sebagai daftar satu nilai.
        $this->assertSame(['Gudang'], $f['departemen']);
        $this->assertSame([], $f['level']);
        $this->assertSame(['Operator', 'Leader'], $f['jabatan']);
        $this->assertSame(['EMI'], $f['lokasi']);
        $this->assertSame([], $svc->normalisasiFilter([])['divisi']);
    }

    public function test_saringan_organisasi_dibatasi_agar_aman_dari_batas_parameter_sql_server(): void
    {
        $f = (new KonversiLemburAdminService)->normalisasiFilter([
            'jabatan' => array_map(fn ($i) => "Jabatan {$i}", range(1, 1000)),
        ]);

        $this->assertCount(200, $f['jabatan']);
    }

    public function test_hanya_status_hidup_yang_boleh_dibatalkan(): void
    {
        foreach (['MENUNGGU', 'DISETUJUI', 'DITERAPKAN'] as $s) {
            $this->assertTrue(KonversiLemburAdminService::bolehDibatalkan($s), $s);
        }
        foreach (['DITOLAK', 'DIBATALKAN'] as $s) {
            $this->assertFalse(KonversiLemburAdminService::bolehDibatalkan($s), $s);
        }
    }
}
