<?php

namespace Tests\Unit\SYSTEM\Application\Reports;

use App\Services\SYSTEM\Application\Reports\DefinisiLaporan;
use App\Services\SYSTEM\Application\Reports\DefinisiValidator;
use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Dimensi;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\Saringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DefinisiValidatorTest extends TestCase
{
    private function dataset(): DatasetLaporan
    {
        return new class implements DatasetLaporan
        {
            public function kunci(): string
            {
                return 'kepatuhan';
            }

            public function label(): string
            {
                return 'Kepatuhan';
            }

            public function grain(): string
            {
                return 'karyawan x paket x item';
            }

            public function dimensi(): array
            {
                return [
                    new Dimensi('divisi', 'Divisi', 'k.Divisi', 'teks', 'identitas'),
                    new Dimensi('paket', 'Paket', 'mp.Nama_Paket', 'teks', 'paket'),
                    new Dimensi('ip', 'IP', 'pr.Konfirmasi_IP', 'teks', 'atestasi', true),
                ];
            }

            public function ukuran(): array
            {
                return [
                    new Ukuran('jml_karyawan', 'Karyawan', 'COUNT(DISTINCT d.Kode_Karyawan)', 'angka'),
                    new Ukuran('persen_selesai', 'Selesai', 'AVG(x)', 'persen'),
                ];
            }

            public function saringan(): array
            {
                return [
                    new Saringan('periode', 'Periode', 'd.Tanggal_Assign', 'tanggal', ['antara']),
                    new Saringan('divisi', 'Divisi', 'k.Divisi', 'teks', ['salah_satu']),
                ];
            }

            public function baseQuery(): Builder
            {
                return DB::table('N_HRIS_Report_Template');
            }

            public function terapkanLingkup(Builder $q, Lingkup $l): Builder
            {
                return $q;
            }
        };
    }

    private function periksa(array $definisi): array
    {
        return (new DefinisiValidator)->periksa(DefinisiLaporan::dariArray($definisi), $this->dataset());
    }

    private function sah(): array
    {
        return [
            'dataset' => 'kepatuhan',
            'mode' => 'ringkasan',
            'dimensi' => ['divisi'],
            'ukuran' => ['jml_karyawan'],
            'saringan' => [['field' => 'divisi', 'op' => 'salah_satu', 'nilai' => ['PROD']]],
        ];
    }

    public function test_definisi_sah_lolos(): void
    {
        $this->assertSame([], $this->periksa($this->sah()));
    }

    public function test_dimensi_tak_dikenal_ditolak(): void
    {
        $d = $this->sah();
        $d['dimensi'] = ['divisi', 'tidak_ada'];

        $this->assertArrayHasKey('dimensi', $this->periksa($d));
    }

    /**
     * Kunci bermusuhan harus DITOLAK, bukan di-escape. Klaim "injeksi mustahil
     * secara konstruksi" hanya benar selama tak ada satu pun jalur yang
     * menerima string bebas menuju SQL.
     */
    public function test_kunci_bermusuhan_ditolak_bukan_di_escape(): void
    {
        $d = $this->sah();
        $d['dimensi'] = ["divisi'; DROP TABLE N_HRIS_Report_Template; --"];

        $this->assertArrayHasKey('dimensi', $this->periksa($d));
    }

    public function test_ukuran_tak_dikenal_ditolak(): void
    {
        $d = $this->sah();
        $d['ukuran'] = ['tidak_ada'];

        $this->assertArrayHasKey('ukuran', $this->periksa($d));
    }

    public function test_ringkasan_tanpa_ukuran_ditolak(): void
    {
        $d = $this->sah();
        $d['ukuran'] = [];

        $this->assertArrayHasKey('ukuran', $this->periksa($d));
    }

    public function test_rinci_tanpa_kolom_ditolak(): void
    {
        $this->assertArrayHasKey('kolom', $this->periksa([
            'dataset' => 'kepatuhan',
            'mode' => 'rinci',
            'kolom' => [],
        ]));
    }

    public function test_dimensi_lebih_dari_enam_ditolak(): void
    {
        $d = $this->sah();
        $d['dimensi'] = array_fill(0, 7, 'divisi');

        $this->assertArrayHasKey('dimensi', $this->periksa($d));
    }

    public function test_operator_tak_didukung_tipe_field_ditolak(): void
    {
        $d = $this->sah();
        $d['saringan'] = [['field' => 'divisi', 'op' => 'antara', 'nilai' => ['a', 'b']]];

        $this->assertArrayHasKey('saringan', $this->periksa($d));
    }

    public function test_bagan_di_luar_pilihan_ditolak(): void
    {
        $d = $this->sah();
        $d['bagan'] = ['tipe' => 'batang', 'dimensi' => 'paket', 'ukuran' => ['jml_karyawan']];

        $this->assertArrayHasKey('bagan', $this->periksa($d));
    }

    public function test_mode_tak_dikenal_ditolak(): void
    {
        $d = $this->sah();
        $d['mode'] = 'pivot3d';

        $this->assertArrayHasKey('mode', $this->periksa($d));
    }

    /**
     * Satu tempelan daftar panjang menjadi IN (@P1..@P900): SQL Server menyusun
     * ulang rencana untuk tiap panjang daftar yang berbeda. Ditolak, bukan
     * dipangkas — daftar terpotong menghasilkan laporan yang tampak lengkap.
     */
    public function test_saringan_dengan_nilai_terlalu_banyak_ditolak(): void
    {
        $galat = $this->periksa([
            'dataset' => 'kepatuhan', 'mode' => 'ringkasan',
            'dimensi' => ['divisi'], 'ukuran' => ['jml_karyawan'],
            'saringan' => [[
                'field' => 'divisi',
                'op' => 'salah_satu',
                'nilai' => array_map(fn ($i) => 'x'.$i, range(1, DefinisiValidator::MAKS_NILAI_SARINGAN + 1)),
            ]],
        ]);

        $this->assertArrayHasKey('saringan', $galat);
        $this->assertNotEmpty(array_filter($galat['saringan'], fn ($g) => str_contains($g, 'maksimal')));
    }

    public function test_saringan_dengan_nilai_pas_di_batas_diterima(): void
    {
        $galat = $this->periksa([
            'dataset' => 'kepatuhan', 'mode' => 'ringkasan',
            'dimensi' => ['divisi'], 'ukuran' => ['jml_karyawan'],
            'saringan' => [[
                'field' => 'divisi',
                'op' => 'salah_satu',
                'nilai' => array_map(fn ($i) => 'x'.$i, range(1, DefinisiValidator::MAKS_NILAI_SARINGAN)),
            ]],
        ]);

        $this->assertSame([], $galat);
    }
}
