<?php

namespace Tests\Unit\KPI\PerformanceAppraisal\Export;

use App\Services\KPI\PerformanceAppraisal\Export\PaHasilDataset;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use Tests\TestCase;

class PaHasilDatasetTest extends TestCase
{
    private PaHasilDataset $dataset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataset = new PaHasilDataset;
    }

    public function test_kunci_dan_butiran(): void
    {
        $this->assertSame('hasil', $this->dataset->kunci());
        $this->assertStringContainsString('satu peserta', $this->dataset->grain());
    }

    public function test_status_hasil_selalu_tersedia_sebagai_dimensi(): void
    {
        // Keputusan produk: hasil yang belum terbit BOLEH ikut terekspor, tetapi
        // statusnya wajib terbaca di berkasnya. Tanpa kolom ini, angka draft dan
        // angka final tidak bisa dibedakan oleh yang membukanya.
        $kunci = array_map(fn ($d) => $d->key, $this->dataset->dimensi());
        $this->assertContains('status_hasil', $kunci);
        $this->assertContains('alasan_tertahan', $kunci);
    }

    public function test_identitas_dibaca_dari_snapshot_siklus(): void
    {
        $select = [];
        foreach ($this->dataset->dimensi() as $d) {
            $select[$d->key] = $d->select;
        }

        $this->assertStringContainsString('Saat_Cycle', $select['nama_karyawan']);
        $this->assertStringContainsString('Saat_Cycle', $select['divisi']);
    }

    /**
     * Penjaga tabrakan kunci dimensi/ukuran dulu ditulis di sini DAN di
     * PaPenugasanDatasetTest secara terpisah. Dinaikkan jadi satu uji yang
     * menyapu SELURUH dataset terdaftar di DatasetRegistry (semua modul,
     * bukan cuma pa) -- lihat
     * Tests\Unit\SYSTEM\Application\Reports\DatasetKunciTidakBertabrakanTest.
     * Dua untungnya: dataset PA tahap berikutnya otomatis ikut terjaga tanpa
     * ada yang perlu ingat menambahkan salinan uji ini lagi, dan dataset
     * modul lain (mis. VisitorGuestDataset) ikut disapu.
     */

    /**
     * Ketiga dimensi angka baru ini ada supaya skor tiap peserta bisa dibaca.
     * Format bawaan Dimensi ('teks') tidak dibulatkan oleh
     * ReportRunner::formatNilai() -- angka SQL Server mentah (mis. hasil CAST
     * atau desimal panjang) akan tampil apa adanya, bertentangan dengan tujuan
     * kolom ini. Format 'angka' membulatkan ke 2 desimal (lihat angkaRingkas()).
     */
    public function test_nilai_baris_diformat_sebagai_angka(): void
    {
        $format = [];
        foreach ($this->dataset->dimensi() as $d) {
            $format[$d->key] = $d->format;
        }

        foreach (['nilai_kpi_baris', 'nilai_kompetensi_baris', 'nilai_akhir_baris'] as $kunci) {
            $this->assertSame('angka', $format[$kunci] ?? null, "Dimensi {$kunci} tidak diformat sebagai angka.");
        }
    }

    /**
     * Kolom sumbernya sudah decimal(6,2) di database (lihat
     * DDL-2026-080-performance-appraisal-result). CAST(... AS float) pada
     * dimensi (bukan ukuran) memperkenalkan artefak presisi biner yang tidak
     * pernah ada di datanya -- dimensi hanya membaca nilai apa adanya, tidak
     * mengagregasi seperti AVG() yang butuh CAST float di ukuran().
     */
    public function test_nilai_baris_tidak_di_cast_ke_float(): void
    {
        $select = [];
        foreach ($this->dataset->dimensi() as $d) {
            $select[$d->key] = $d->select;
        }

        foreach (['nilai_kpi_baris', 'nilai_kompetensi_baris', 'nilai_akhir_baris'] as $kunci) {
            $this->assertStringNotContainsString('float', strtolower($select[$kunci] ?? ''), "Dimensi {$kunci} tidak seharusnya di-CAST ke float.");
        }
    }

    public function test_rata_rata_tidak_ikut_grand_total(): void
    {
        $ukuranAvg = array_filter($this->dataset->ukuran(), fn ($u) => str_starts_with($u->agregat, 'AVG('));

        // Ditegaskan dulu bahwa ukuran ber-AVG memang ADA -- tanpa ini, menghapus
        // seluruh ukuran AVG() dari dataset akan membuat uji ini lulus dengan nol
        // penegasan alih-alih memerahkannya.
        $this->assertNotEmpty($ukuranAvg, 'Dataset ini seharusnya punya ukuran ber-agregat AVG().');

        foreach ($ukuranAvg as $u) {
            $this->assertSame(Ukuran::TOTAL_ABAIKAN, $u->totalisasi,
                "Ukuran {$u->key} memakai AVG tetapi ikut ditotal.");
        }
    }

    public function test_base_query_menyebut_kedua_tabel_dan_tidak_menyentuh_view_divisi(): void
    {
        $sql = $this->dataset->baseQuery()->toSql();

        $this->assertStringContainsString('N_PA_Result', $sql);
        $this->assertStringContainsString('N_PA_Participant', $sql);
        // View itu berbaris satu per pasangan divisi x sub-divisi, sehingga orang
        // yang bernaung di dua divisi akan tergandakan.
        $this->assertStringNotContainsString('View_Divisi_Sub_Divisi', $sql);
    }

    public function test_base_query_menyaring_flag_aktif_pada_result(): void
    {
        // Perhitungan ulang meninggalkan baris versi lama; tanpa saringan ini satu
        // peserta muncul berkali-kali dengan angka berbeda-beda. Diperiksa dengan
        // alias ([r].[Flag_Aktif]), bukan sekadar kata "Flag_Aktif" muncul di suatu
        // tempat -- satu penegasan longgar seperti itu tetap hijau kalau saringan
        // pada N_PA_Result dihapus asalkan saringan pada N_PA_Participant (atau
        // N_PA_Score_Range) masih ada.
        $sql = $this->dataset->baseQuery()->toSql();

        $this->assertStringContainsString('[r].[Flag_Aktif]', $sql);
    }

    public function test_base_query_menyaring_flag_aktif_pada_participant(): void
    {
        // Saringan ini hidup di klausa ON join utama (lihat baseQuery()), bukan di
        // WHERE. Diperiksa dengan alias ([p].[Flag_Aktif]) dengan alasan yang sama
        // seperti uji di atas.
        $sql = $this->dataset->baseQuery()->toSql();

        $this->assertStringContainsString('[p].[Flag_Aktif]', $sql);
    }

    /**
     * nama_grade WAJIB membaca salinan yang dibekukan per siklus
     * (N_PA_Cycle_Score_Range_Snapshot), BUKAN master hidup (N_PA_Score_Range).
     * Master boleh berubah/dinonaktifkan setelah siklus berjalan; laporan
     * historis wajib mengikuti nama pita yang berlaku saat siklus itu disiapkan
     * -- lihat PaCalibrationService::pastikanGradeSah(). Join berkunci EMPAT
     * kolom: PA_Cycle_Id + Kode_Range + Score_Type + Score_Usage. Tanpa
     * PA_Cycle_Id, setiap peserta tergandakan sebanyak jumlah siklus.
     *
     * Ditegaskan dulu bahwa join ini memang ADA sebelum memeriksa sifatnya --
     * mencabut joinnya tidak boleh membuat uji ini diam-diam tetap hijau.
     */
    public function test_join_score_range_memakai_snapshot_siklus_dan_empat_kunci(): void
    {
        $sql = $this->dataset->baseQuery()->toSql();

        $this->assertStringContainsString('N_PA_Cycle_Score_Range_Snapshot', $sql,
            'baseQuery() harus join ke salinan siklus, bukan master hidup.');
        $this->assertStringNotContainsString('N_PA_Score_Range]', $sql,
            'nama_grade tidak boleh membaca master hidup N_PA_Score_Range.');

        $this->assertStringContainsString('[sr].[PA_Cycle_Id]', $sql);
        $this->assertStringContainsString('[p].[PA_Cycle_Id]', $sql);
        $this->assertStringContainsString('[sr].[Kode_Range]', $sql);
        $this->assertStringContainsString('[sr].[Score_Type]', $sql);
        $this->assertStringContainsString('[sr].[Score_Usage]', $sql);
        $this->assertStringContainsString('[sr].[Flag_Aktif]', $sql);
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

    public function test_opsi_divisi_dan_grade_final_menyaring_flag_aktif(): void
    {
        // Perhitungan ulang meninggalkan baris hasil lama ber-Flag_Aktif='T'; tanpa
        // saringan ini, grade/divisi miliknya tetap muncul di dropdown lalu memilihnya
        // menghasilkan nol baris tanpa satu pun galat.
        $sqlDivisi = $this->dataset->queryDivisiUnik()->toSql();
        $this->assertStringContainsString('Flag_Aktif', $sqlDivisi);

        $sqlGrade = $this->dataset->queryGradeFinalUnik()->toSql();
        $this->assertStringContainsString('Flag_Aktif', $sqlGrade);
    }

    public function test_opsi_kode_cycle_tidak_lebih_sempit_dari_base_query(): void
    {
        // baseQuery() leftJoin N_PA_Cycle TANPA syarat Flag_Aktif, jadi baris siklus
        // nonaktif tetap bisa keluar di hasil. Opsi ini sengaja TIDAK menyaring
        // Flag_Aktif juga -- kalau disaring sementara kuerinya tidak, opsinya jadi
        // lebih sempit daripada data yang sebenarnya bisa muncul, dan siklus yang
        // barisnya ada di hasil jadi tak bisa dipilih sama sekali.
        $sql = $this->dataset->queryKodeCycleUnik()->toSql();
        $this->assertStringNotContainsString('Flag_Aktif', $sql);
    }

    public function test_opsi_status_hasil_memakai_konstanta_bukan_teks_ulang(): void
    {
        $nilai = array_column($this->dataset->opsiStatusHasil(), 'value');

        $this->assertSame([
            PaResultCalculationService::STATUS_BELUM,
            PaResultCalculationService::STATUS_DIHITUNG,
            PaResultCalculationService::STATUS_TERTAHAN,
            PaResultCalculationService::STATUS_TERBIT,
        ], $nilai);
    }

    public function test_bentuk_opsi_saringan_value_label(): void
    {
        foreach ($this->dataset->opsiStatusHasil() as $item) {
            $this->assertArrayHasKey('value', $item);
            $this->assertArrayHasKey('label', $item);
        }
    }

    /**
     * Spec garis 237: setiap dataset PA menyediakan saringan siklus, tahun, divisi,
     * sub-divisi, level, jabatan, dan lokasi (ditambah saringan khas). Yang umum ini
     * dulu absen dari dataset hasil; penegasan ini menjaga subset persisnya.
     */
    public function test_saringan_menyediakan_subset_umum_wajib(): void
    {
        $saringan = [];
        foreach ($this->dataset->saringan() as $s) {
            $saringan[$s->key] = $s->kolom;
        }

        foreach (['kode_cycle', 'tahun', 'divisi', 'sub_divisi', 'level', 'jabatan', 'lokasi'] as $wajib) {
            $this->assertArrayHasKey($wajib, $saringan, "Saringan umum {$wajib} hilang.");
        }
    }

    public function test_saringan_umum_membaca_kolom_snapshot_siklus(): void
    {
        $saringan = [];
        foreach ($this->dataset->saringan() as $s) {
            $saringan[$s->key] = $s->kolom;
        }

        // Identitas organisasi WAJIB dari kolom *_Saat_Cycle (snapshot saat siklus),
        // bukan master karyawan hidup -- supaya laporan historis tidak berubah-ubah.
        $this->assertSame('p.Nama_Sub_Divisi_Saat_Cycle', $saringan['sub_divisi']);
        $this->assertSame('p.Nama_Level_Saat_Cycle', $saringan['level']);
        $this->assertSame('p.Nama_Jabatan_Saat_Cycle', $saringan['jabatan']);
        $this->assertSame('p.Lokasi_Saat_Cycle', $saringan['lokasi']);
    }

    public function test_opsi_snapshot_menyaring_flag_aktif(): void
    {
        // Sama seperti opsi divisi: baris participant lama ber-Flag_Aktif='T' tetap
        // menyimpan nilai snapshotnya; tanpa saringan ini nilai mati tetap muncul di
        // dropdown lalu memilihnya menghasilkan nol baris tanpa satu pun galat.
        $sql = $this->dataset->queryOpsiSnapshot('Nama_Level_Saat_Cycle')->toSql();

        $this->assertStringContainsString('Flag_Aktif', $sql);
        $this->assertStringContainsString('Nama_Level_Saat_Cycle', $sql);
        $this->assertStringContainsString('N_PA_Participant', $sql);
    }

    public function test_opsi_saringan_menyediakan_opsi_untuk_subset_umum(): void
    {
        $opsi = $this->dataset->opsiSaringan();

        foreach (['divisi', 'sub_divisi', 'level', 'jabatan', 'lokasi', 'kode_cycle'] as $kunci) {
            $this->assertArrayHasKey($kunci, $opsi, "opsiSaringan() tidak memuat kunci {$kunci}.");
            $this->assertIsArray($opsi[$kunci], "opsiSaringan()[{$kunci}] harus berupa daftar.");
        }
    }
}
