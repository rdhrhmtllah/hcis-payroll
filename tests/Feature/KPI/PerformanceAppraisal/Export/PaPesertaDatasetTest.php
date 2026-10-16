<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Export;

use App\Services\KPI\PerformanceAppraisal\Export\PaPesertaDataset;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use Tests\TestCase;

/**
 * Kontrak dataset peserta Export Studio PA (Tahap 2).
 *
 * Tidak menyentuh basis data: seluruh penegasan lewat toSql() dan metadata
 * katalog. Pendaftaran ke DatasetRegistry diuji di Tahap 3, eksekusi SQL Server
 * di Tahap 4.
 */
class PaPesertaDatasetTest extends TestCase
{
    private PaPesertaDataset $dataset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataset = new PaPesertaDataset;
    }

    public function test_kunci_dan_grain(): void
    {
        $this->assertSame('peserta', $this->dataset->kunci());
        $this->assertStringContainsString('peserta', strtolower($this->dataset->grain()));
        $this->assertStringContainsString('siklus', strtolower($this->dataset->grain()));
    }

    public function test_basis_hanya_participant_dan_siklus_tanpa_inner_join_hasil(): void
    {
        // Dataset peserta harus bisa melaporkan peserta DIKECUALIKAN/TERTUNDA yang
        // tidak punya baris hasil sama sekali; INNER JOIN ke N_PA_Result membuangnya.
        $sql = $this->dataset->baseQuery()->toSql();

        $this->assertStringContainsString('N_PA_Participant', $sql);
        $this->assertStringContainsString('N_PA_Cycle', $sql);
        $this->assertStringNotContainsString('N_PA_Result', $sql);
    }

    public function test_join_penugasan_memakai_left_join_derived_table_yang_diagregasi_per_participant(): void
    {
        // LEFT JOIN ke turunan yang sudah COUNT(*) per participant: peserta tanpa
        // tugas tetap muncul (satu baris, jumlah_penugasan NULL) dan peserta dengan
        // banyak tugas TIDAK tergandakan.
        $sql = $this->dataset->baseQuery()->toSql();

        $this->assertStringContainsString('left join', strtolower($sql));
        $this->assertStringContainsString('N_PA_Assignment', $sql);
        $this->assertStringContainsString('count(*)', strtolower($sql));
        $this->assertStringContainsString('PA_Participant_Id', $sql);
    }

    public function test_dimensi_siklus_periode_dan_snapshot(): void
    {
        $peta = [];
        foreach ($this->dataset->dimensi() as $d) {
            $peta[$d->key] = $d->select;
        }

        foreach (['kode_cycle', 'nama_cycle', 'tahun', 'periode_dari', 'periode_sampai'] as $k) {
            $this->assertArrayHasKey($k, $peta, "Dimensi {$k} hilang.");
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

    public function test_alasan_dikecualikan_dan_snapshot_at_menjadi_dimensi_rinci(): void
    {
        $peta = [];
        foreach ($this->dataset->dimensi() as $d) {
            $peta[$d->key] = $d->select;
        }

        $this->assertStringContainsString('Alasan_Dikecualikan', $peta['alasan_dikecualikan'] ?? '');
        $this->assertStringContainsString('Snapshot_At', $peta['snapshot_at'] ?? '');
    }

    public function test_dimensi_memakai_alias_sederhana_tanpa_subkueri(): void
    {
        foreach ($this->dataset->dimensi() as $d) {
            $this->assertStringNotContainsString('SELECT', strtoupper($d->select), "Dimensi {$d->key} memuat subkueri.");
        }
    }

    public function test_ukuran_jumlah_orang_dan_jumlah_penugasan(): void
    {
        $peta = [];
        foreach ($this->dataset->ukuran() as $u) {
            $peta[$u->key] = $u->agregat;
        }

        $this->assertSame('COUNT(*)', $peta['jumlah_orang'] ?? null);
        // SUM dari derived column, COALESCE ke 0 supaya peserta tanpa tugas
        // menyumbang 0, bukan NULL yang menggagalkan penjumlahan.
        $this->assertStringContainsString('SUM(COALESCE', $peta['jumlah_penugasan'] ?? '');
        $this->assertStringContainsString('jumlah_penugasan', $peta['jumlah_penugasan'] ?? '');
    }

    public function test_totalisasi_kedua_ukuran_ikut_ditotal(): void
    {
        $peta = [];
        foreach ($this->dataset->ukuran() as $u) {
            $peta[$u->key] = $u->totalisasi;
        }

        $this->assertSame(Ukuran::TOTAL_JUMLAH, $peta['jumlah_orang'] ?? null);
        $this->assertSame(Ukuran::TOTAL_JUMLAH, $peta['jumlah_penugasan'] ?? null);
    }

    public function test_opsi_status_menyediakan_diikutkan_dikecualikan_dan_tertunda(): void
    {
        $opsi = $this->dataset->opsiSaringan()['status_peserta'] ?? null;
        $this->assertNotNull($opsi, 'opsiSaringan() tidak memuat kunci status_peserta.');

        $nilai = array_column($opsi, 'value');

        foreach (['DIIKUTKAN', 'DIKECUALIKAN', 'TERTUNDA'] as $status) {
            $this->assertContains($status, $nilai, "Status {$status} tidak tersedia sebagai opsi.");
        }
    }

    public function test_opsi_status_lahir_dari_satu_konstanta_daftar(): void
    {
        // Label dan filter harus lahir dari sumber yang sama; ditulis terpisah,
        // memilih opsi membandingkan teks yang tak pernah dihasilkan kueri.
        $nilai = array_column($this->dataset->opsiStatusPeserta(), 'value');

        $this->assertSame($this->dataset->daftarStatusPeserta(), $nilai);
    }

    public function test_opsi_divisi_menyaring_flag_aktif(): void
    {
        $sql = $this->dataset->queryDivisiUnik()->toSql();

        $this->assertStringContainsString('[p].[Flag_Aktif]', $sql);
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
