<?php

namespace Tests\Unit\SYSTEM\Application\Reports;

use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Dimensi;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\Saringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DatasetRegistryTest extends TestCase
{
    private function datasetPalsu(string $kunci = 'uji'): DatasetLaporan
    {
        return new class($kunci) implements DatasetLaporan
        {
            public function __construct(private string $k) {}

            public function kunci(): string
            {
                return $this->k;
            }

            public function label(): string
            {
                return 'Uji';
            }

            public function grain(): string
            {
                return 'satu baris per apa pun';
            }

            public function dimensi(): array
            {
                return [new Dimensi('a', 'A', 't.a', 'teks', 'inti')];
            }

            public function ukuran(): array
            {
                return [new Ukuran('n', 'N', 'COUNT(*)', 'angka')];
            }

            public function saringan(): array
            {
                return [new Saringan('a', 'A', 't.a', 'teks', ['salah_satu'])];
            }

            public function baseQuery(): Builder
            {
                return DB::table('N_HRIS_Report_Template as t');
            }

            public function terapkanLingkup(Builder $q, Lingkup $l): Builder
            {
                return $q;
            }
        };
    }

    public function test_dataset_terdaftar_bisa_diambil(): void
    {
        $reg = new DatasetRegistry;
        $reg->daftarkan('sop', $this->datasetPalsu('kepatuhan'));

        $this->assertSame('kepatuhan', $reg->ambil('sop', 'kepatuhan')->kunci());
    }

    public function test_dataset_modul_lain_tidak_bocor(): void
    {
        $reg = new DatasetRegistry;
        $reg->daftarkan('sop', $this->datasetPalsu('kepatuhan'));

        $this->expectException(RuntimeException::class);
        $reg->ambil('kpi', 'kepatuhan');
    }

    public function test_kunci_tak_dikenal_ditolak_bukan_dikembalikan_null(): void
    {
        $reg = new DatasetRegistry;

        $this->expectException(RuntimeException::class);
        $reg->ambil('sop', 'tidak-ada');
    }

    public function test_semua_hanya_mengembalikan_dataset_modulnya(): void
    {
        $reg = new DatasetRegistry;
        $reg->daftarkan('sop', $this->datasetPalsu('kepatuhan'));
        $reg->daftarkan('kpi', $this->datasetPalsu('parameter'));

        $this->assertSame(['kepatuhan'], array_keys($reg->semua('sop')));
    }
}
