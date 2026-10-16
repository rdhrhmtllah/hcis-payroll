<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Export;

use App\Services\KPI\PerformanceAppraisal\Export\PaKompetensiDataset;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use Tests\TestCase;

/**
 * Kontrak dataset kompetensi Export Studio PA (Tahap 2).
 *
 * Tidak menyentuh basis data: seluruh penegasan lewat toSql() dan metadata
 * katalog. Pendaftaran ke DatasetRegistry diuji di Tahap 3 (PaExportPendaftaranTest),
 * dan eksekusi sungguhan di SQL Server diuji di Tahap 4 (integration).
 */
class PaKompetensiDatasetTest extends TestCase
{
    private PaKompetensiDataset $dataset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataset = new PaKompetensiDataset;
    }

    public function test_kunci_dan_grain(): void
    {
        $this->assertSame('kompetensi', $this->dataset->kunci());
        $this->assertStringContainsString('kompetensi', strtolower($this->dataset->grain()));
        $this->assertStringContainsString('peserta', strtolower($this->dataset->grain()));
    }

    public function test_base_query_hanya_menghasilkan_baris_agregat(): void
    {
        // Baris ber-PA_Rater_Category_Id terisi adalah "kompetensi menurut satu
        // kategori penilai"; dataset ini hanya membaca gabungan lintas kategori,
        // yang ditandai NULL pada kolom itu. Diperiksa dengan alias, bukan sekadar
        // kata "is null" muncul di suatu tempat.
        $sql = $this->dataset->baseQuery()->toSql();

        $this->assertStringContainsString('[rc].[PA_Rater_Category_Id] is null', $sql);
    }

    public function test_filter_aktif_berlaku_pada_hasil_peserta_kompetensi_dan_result_competency(): void
    {
        // Perhitungan ulang meninggalkan baris lama ber-Flag_Aktif='T' pada seluruh
        // lapisan ini. Diperiksa per alias supaya satu saringan yang hilang tidak
        // tersamarkan oleh saringan lain yang masih ada.
        $sql = $this->dataset->baseQuery()->toSql();

        foreach (['[r].[Flag_Aktif]', '[p].[Flag_Aktif]', '[k].[Flag_Aktif]', '[rc].[Flag_Aktif]'] as $alias) {
            $this->assertStringContainsString($alias, $sql, "Saringan {$alias} hilang dari baseQuery().");
        }
    }

    public function test_base_query_menyebut_semua_tabel_dan_tidak_menyentuh_view_divisi(): void
    {
        $sql = $this->dataset->baseQuery()->toSql();

        foreach (['N_PA_Result_Competency', 'N_PA_Result', 'N_PA_Participant', 'N_PA_Cycle', 'N_PA_Competency', 'N_PA_Competency_Type'] as $tabel) {
            $this->assertStringContainsString($tabel, $sql, "baseQuery() tidak menyebut tabel {$tabel}.");
        }
        // View itu berbaris satu per pasangan divisi x sub-divisi; orang yang
        // bernaung di dua divisi akan tergandakan.
        $this->assertStringNotContainsString('View_Divisi_Sub_Divisi', $sql);
    }

    public function test_dimensi_minimum_tersedia(): void
    {
        $kunci = array_map(fn ($d) => $d->key, $this->dataset->dimensi());

        foreach ([
            'kode_cycle', 'nama_cycle', 'tahun',
            'kode_karyawan', 'nama_karyawan', 'level', 'divisi', 'sub_divisi', 'jabatan', 'lokasi',
            'jenis_kompetensi', 'kode_kompetensi', 'nama_kompetensi',
        ] as $wajib) {
            $this->assertContains($wajib, $kunci, "Dimensi {$wajib} hilang.");
        }
    }

    public function test_dimensi_identitas_dibaca_dari_snapshot_siklus(): void
    {
        $select = [];
        foreach ($this->dataset->dimensi() as $d) {
            $select[$d->key] = $d->select;
        }

        $this->assertStringContainsString('Saat_Cycle', $select['nama_karyawan']);
        $this->assertStringContainsString('Saat_Cycle', $select['divisi']);
    }

    public function test_dimensi_memakai_alias_sederhana_tanpa_subkueri(): void
    {
        // SQL Server melarang ekspresi sub-kueri di GROUP BY, dan ReportCompiler
        // menaruh select dimensi apa adanya ke groupBy(). Dimensi wajib berupa
        // alias kolom polos.
        foreach ($this->dataset->dimensi() as $d) {
            $this->assertStringNotContainsString('SELECT', strtoupper($d->select), "Dimensi {$d->key} memuat subkueri.");
        }
    }

    public function test_ukuran_pasangan_peserta_rata_rata_dan_penilai(): void
    {
        $peta = [];
        foreach ($this->dataset->ukuran() as $u) {
            $peta[$u->key] = $u->agregat;
        }

        $this->assertSame('COUNT(*)', $peta['jumlah_pasang'] ?? null);
        $this->assertStringContainsString('COUNT(DISTINCT', $peta['jumlah_peserta'] ?? '');
        $this->assertStringContainsString('AVG(', $peta['rata_rata_nilai'] ?? '');
        $this->assertStringContainsString('SUM(COALESCE', $peta['jumlah_penilai'] ?? '');
    }

    public function test_totalisasi_rata_rata_dan_count_distinct_diabaikan_sedangkan_count_dan_sum_ditotal(): void
    {
        $peta = [];
        foreach ($this->dataset->ukuran() as $u) {
            $peta[$u->key] = $u->totalisasi;
        }

        // Rata-rata dari rata-rata bukan rata-rata; COUNT(DISTINCT) rusak bila
        // dijumlahkan (satu peserta lintas jenis kompetensi terhitung ganda).
        $this->assertSame(Ukuran::TOTAL_ABAIKAN, $peta['rata_rata_nilai'] ?? null);
        $this->assertSame(Ukuran::TOTAL_ABAIKAN, $peta['jumlah_peserta'] ?? null);

        // COUNT(*) dan SUM() tetap boleh dijumlahkan.
        $this->assertSame(Ukuran::TOTAL_JUMLAH, $peta['jumlah_pasang'] ?? null);
        $this->assertSame(Ukuran::TOTAL_JUMLAH, $peta['jumlah_penilai'] ?? null);
    }

    public function test_opsi_menyaring_flag_aktif_dan_agregat_global(): void
    {
        // Opsi harus lahir dari populasi yang sama dengan baseQuery: baris agregat
        // (PA_Rater_Category_Id IS NULL) ber-Flag_Aktif='Y'. Tanpa itu, dropdown
        // menawarkan nilai yang memilihnya menghasilkan nol baris tanpa galat.
        foreach (['queryDivisiUnik', 'queryJenisUnik', 'queryKodeKompetensiUnik'] as $method) {
            $sql = $this->dataset->{$method}()->toSql();

            $this->assertStringContainsString('[rc].[PA_Rater_Category_Id] is null', $sql);
            $this->assertStringContainsString('[rc].[Flag_Aktif]', $sql);
        }

        $sqlCycle = $this->dataset->queryKodeCycleUnik()->toSql();
        $this->assertStringContainsString('[rc].[PA_Rater_Category_Id] is null', $sqlCycle);
    }

    public function test_opsi_kode_cycle_tidak_lebih_sempit_dari_base_query(): void
    {
        // baseQuery() leftJoin N_PA_Cycle TANPA syarat Flag_Aktif, jadi baris siklus
        // nonaktif tetap bisa keluar. Opsi sengaja TIDAK menyaring Flag_Aktif cycle.
        $sql = $this->dataset->queryKodeCycleUnik()->toSql();

        $this->assertStringNotContainsString('[c].[Flag_Aktif]', $sql);
    }

    public function test_bukan_admin_mendapat_nol_baris(): void
    {
        $q = $this->dataset->terapkanLingkup(
            $this->dataset->baseQuery(),
            new Lingkup('EMP001', false, [], false)
        );

        $this->assertStringContainsString('1 = 0', $q->toSql());
    }

    public function test_admin_penuh_tidak_disempitkan(): void
    {
        $awal = $this->dataset->baseQuery()->toSql();
        $sesudah = $this->dataset->terapkanLingkup(
            $this->dataset->baseQuery(),
            new Lingkup('EMP001', true, [], false)
        )->toSql();

        $this->assertSame($awal, $sesudah);
    }
}
