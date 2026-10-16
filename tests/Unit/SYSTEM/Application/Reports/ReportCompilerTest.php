<?php

namespace Tests\Unit\SYSTEM\Application\Reports;

use App\Services\SYSTEM\Application\Reports\DefinisiLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Dimensi;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\Saringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use App\Services\SYSTEM\Application\Reports\ReportCompiler;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportCompilerTest extends TestCase
{
    private function dataset(): DatasetLaporan
    {
        return new class implements DatasetLaporan
        {
            public function kunci(): string
            {
                return 'uji';
            }

            public function label(): string
            {
                return 'Uji';
            }

            public function grain(): string
            {
                return 'satu baris per template';
            }

            public function dimensi(): array
            {
                return [
                    new Dimensi('modul', 'Modul', 't.Module', 'teks', 'inti'),
                    new Dimensi('mode', 'Mode', 't.Mode', 'teks', 'inti'),
                ];
            }

            public function ukuran(): array
            {
                return [new Ukuran('jml', 'Jumlah', 'COUNT(*)', 'angka')];
            }

            public function saringan(): array
            {
                return [
                    new Saringan('modul', 'Modul', 't.Module', 'teks', ['salah_satu']),
                    new Saringan('dibuat', 'Dibuat', 't.Created_At', 'tanggal', ['antara']),
                ];
            }

            public function baseQuery(): Builder
            {
                return DB::table('N_HRIS_Report_Template as t');
            }

            public function terapkanLingkup(Builder $q, Lingkup $l): Builder
            {
                return $l->adminPenuh ? $q : $q->whereIn('t.Kode_Karyawan_Pemilik', $l->anggotaTim);
            }
        };
    }

    private function susun(array $definisi, ?Lingkup $l = null)
    {
        return (new ReportCompiler)->susun(
            DefinisiLaporan::dariArray($definisi),
            $this->dataset(),
            $l ?? new Lingkup('AD1', true, [], true)
        );
    }

    public function test_ringkasan_membentuk_group_by(): void
    {
        $sql = strtolower($this->susun([
            'dataset' => 'uji', 'mode' => 'ringkasan', 'dimensi' => ['modul'], 'ukuran' => ['jml'],
        ])->toSql());

        $this->assertStringContainsString('group by', $sql);
        $this->assertStringContainsString('count(*)', $sql);
    }

    public function test_rinci_tidak_membentuk_group_by(): void
    {
        $sql = strtolower($this->susun([
            'dataset' => 'uji', 'mode' => 'rinci', 'kolom' => ['modul', 'mode'],
        ])->toSql());

        $this->assertStringNotContainsString('group by', $sql);
    }

    public function test_saringan_salah_satu_jadi_binding_bukan_literal(): void
    {
        $q = $this->susun([
            'dataset' => 'uji', 'mode' => 'ringkasan', 'dimensi' => ['modul'], 'ukuran' => ['jml'],
            'saringan' => [['field' => 'modul', 'op' => 'salah_satu', 'nilai' => ['sop', 'kpi']]],
        ]);

        $this->assertContains('sop', $q->getBindings());
        $this->assertStringNotContainsString('sop', $q->toSql());
    }

    public function test_saringan_antara_memakai_dua_batas(): void
    {
        $q = $this->susun([
            'dataset' => 'uji', 'mode' => 'ringkasan', 'dimensi' => ['modul'], 'ukuran' => ['jml'],
            'saringan' => [['field' => 'dibuat', 'op' => 'antara', 'nilai' => ['2026-01-01', '2026-03-31']]],
        ]);

        $this->assertContains('2026-01-01', $q->getBindings());
        $this->assertContains('2026-03-31', $q->getBindings());
    }

    public function test_lingkup_leader_ikut_tersusun(): void
    {
        $q = $this->susun(
            ['dataset' => 'uji', 'mode' => 'ringkasan', 'dimensi' => ['modul'], 'ukuran' => ['jml']],
            new Lingkup('L1', false, ['A1'], false)
        );

        $this->assertContains('A1', $q->getBindings());
    }

    public function test_urut_hanya_menerima_kunci_terpilih(): void
    {
        $sql = strtolower($this->susun([
            'dataset' => 'uji', 'mode' => 'ringkasan', 'dimensi' => ['modul'], 'ukuran' => ['jml'],
            'urut' => [['key' => 'jml', 'arah' => 'desc'], ['key' => 'tidak_dipilih', 'arah' => 'asc']],
        ])->toSql());

        $this->assertStringContainsString('order by', $sql);
        $this->assertStringNotContainsString('tidak_dipilih', $sql);
    }

    /**
     * Tanpa penetralan, mengetik "100%" pada saringan "mengandung" mencari apa
     * pun yang diawali "100" — hasil yang terlihat masuk akal tapi bukan yang
     * diminta.
     */
    public function test_wildcard_like_dinetralkan(): void
    {
        $this->assertSame('[%]100[_]a[[]b', ReportCompiler::amankanLike('%100_a[b'));
    }
}
