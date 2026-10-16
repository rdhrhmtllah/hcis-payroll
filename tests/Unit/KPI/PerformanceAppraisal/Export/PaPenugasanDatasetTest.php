<?php

namespace Tests\Unit\KPI\PerformanceAppraisal\Export;

use App\Services\KPI\PerformanceAppraisal\Export\PaPenugasanDataset;
use App\Services\KPI\PerformanceAppraisal\PaStatusPenugasan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use Tests\TestCase;

class PaPenugasanDatasetTest extends TestCase
{
    private PaPenugasanDataset $dataset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataset = new PaPenugasanDataset;
    }

    public function test_butiran_satu_penugasan(): void
    {
        $this->assertSame('penugasan', $this->dataset->kunci());
        $this->assertStringContainsString('satu penugasan', $this->dataset->grain());
    }

    /**
     * Penjaga tabrakan kunci dimensi/ukuran dulu ditulis di sini DAN di
     * PaHasilDatasetTest secara terpisah. Dinaikkan jadi satu uji yang
     * menyapu SELURUH dataset terdaftar di DatasetRegistry (semua modul,
     * bukan cuma pa) -- lihat
     * Tests\Unit\SYSTEM\Application\Reports\DatasetKunciTidakBertabrakanTest.
     * Dua untungnya: dataset PA tahap berikutnya otomatis ikut terjaga tanpa
     * ada yang perlu ingat menambahkan salinan uji ini lagi, dan dataset
     * modul lain (mis. VisitorGuestDataset) ikut disapu.
     */
    public function test_ukuran_terisi_memakai_konstanta_bersama_bukan_teks_sendiri(): void
    {
        // Inti tugas ini. Kalau daftar statusnya diketik ulang di sini, Export
        // Studio dan halaman pemantauan akan menyimpang suatu hari, dan tidak ada
        // yang tahu kapan.
        $agregat = [];
        foreach ($this->dataset->ukuran() as $u) {
            $agregat[$u->key] = $u->agregat;
        }

        $daftar = PaStatusPenugasan::sqlDaftar(PaStatusPenugasan::SUDAH_DIKERJAKAN);
        $this->assertStringContainsString($daftar, $agregat['sudah_terisi']);
    }

    public function test_terlambat_tidak_menghitung_terkumpul(): void
    {
        $agregat = [];
        foreach ($this->dataset->ukuran() as $u) {
            $agregat[$u->key] = $u->agregat;
        }

        $this->assertStringContainsString(
            PaStatusPenugasan::sqlDaftar(PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT),
            $agregat['terlambat']
        );
        $this->assertStringContainsString('Due_At', $agregat['terlambat']);
    }

    public function test_persen_kemajuan_tidak_ikut_grand_total(): void
    {
        foreach ($this->dataset->ukuran() as $u) {
            if ($u->key === 'persen_kemajuan') {
                $this->assertSame(Ukuran::TOTAL_ABAIKAN, $u->totalisasi);

                return;
            }
        }

        $this->fail('Ukuran persen_kemajuan tidak ada.');
    }

    public function test_opsi_status_dirender_dari_konstanta_yang_sama(): void
    {
        // Label pada CASE dan daftar opsi harus lahir dari sumber yang sama.
        // Ditulis terpisah, memilih opsi membandingkan teks yang tak pernah
        // dihasilkan kueri -> nol baris TANPA galat.
        $opsi = $this->dataset->opsiSaringan()['status_penugasan'];
        $nilai = array_column($opsi, 'value');

        $this->assertSame(PaStatusPenugasan::SEMUA, $nilai);
    }

    public function test_base_query_menyebut_kedua_tabel_dan_tidak_menyentuh_view_divisi(): void
    {
        $sql = $this->dataset->baseQuery()->toSql();

        $this->assertStringContainsString('N_PA_Assignment', $sql);
        $this->assertStringContainsString('N_PA_Participant', $sql);
        // View itu berbaris satu per pasangan divisi x sub-divisi, sehingga orang
        // yang bernaung di dua divisi akan tergandakan.
        $this->assertStringNotContainsString('View_Divisi_Sub_Divisi', $sql);
    }

    public function test_base_query_menyaring_flag_aktif_pada_assignment(): void
    {
        // Diperiksa dengan alias ([a].[Flag_Aktif]), bukan sekadar kata "Flag_Aktif"
        // muncul di suatu tempat pada SQL -- satu penegasan longgar seperti itu tetap
        // hijau kalau saringan Flag_Aktif pada N_PA_Assignment dihapus asalkan
        // saringan pada N_PA_Participant masih ada.
        $sql = $this->dataset->baseQuery()->toSql();

        $this->assertStringContainsString('[a].[Flag_Aktif]', $sql);
    }

    public function test_base_query_menyaring_flag_aktif_pada_participant(): void
    {
        // Saringan ini hidup di klausa ON join utama (lihat baseQuery()), bukan di
        // WHERE. Diperiksa dengan alias ([p].[Flag_Aktif]) dengan alasan yang sama
        // seperti uji di atas.
        $sql = $this->dataset->baseQuery()->toSql();

        $this->assertStringContainsString('[p].[Flag_Aktif]', $sql);
    }

    public function test_opsi_sumber_penugasan_menyaring_flag_aktif(): void
    {
        // Diambil dari N_PA_Assignment, tabel yang sama yang disaring Flag_Aktif='Y'
        // di WHERE baseQuery(). Tanpa saringan ini, sumber penugasan milik baris lama
        // (dibentuk ulang, Flag_Aktif='T') tetap muncul di dropdown lalu memilihnya
        // menghasilkan nol baris tanpa satu pun galat.
        $sql = $this->dataset->querySumberPenugasanUnik()->toSql();

        $this->assertStringContainsString('Flag_Aktif', $sql);
    }

    public function test_opsi_divisi_menyaring_flag_aktif(): void
    {
        // Diambil dari N_PA_Participant, tabel yang sama yang disaring Flag_Aktif='Y'
        // di klausa ON join utama baseQuery(). Alasannya sama seperti sumber_penugasan.
        $sql = $this->dataset->queryDivisiUnik()->toSql();

        $this->assertStringContainsString('Flag_Aktif', $sql);
    }

    public function test_opsi_kategori_penilai_tidak_lebih_sempit_dari_base_query(): void
    {
        // baseQuery() leftJoin N_PA_Rater_Category TANPA syarat Flag_Aktif, jadi
        // kategori yang sudah dinonaktifkan tetap bisa keluar di hasil selama masih
        // ada penugasan aktif yang menunjuknya. Opsi ini sengaja TIDAK menyaring
        // Flag_Aktif juga -- kalau disaring sementara kuerinya tidak, opsinya jadi
        // lebih sempit daripada data yang sebenarnya bisa muncul, dan kategori yang
        // barisnya ada di hasil jadi tak bisa dipilih sama sekali di dropdown.
        $sql = $this->dataset->queryKategoriPenilaiUnik()->toSql();

        $this->assertStringNotContainsString('Flag_Aktif', $sql);
    }

    public function test_opsi_kode_cycle_tidak_lebih_sempit_dari_base_query(): void
    {
        // baseQuery() leftJoin N_PA_Cycle TANPA syarat Flag_Aktif, persis seperti
        // PaHasilDataset. Opsi ini sengaja TIDAK menyaring Flag_Aktif juga, dengan
        // alasan yang sama: menyaringnya di sini saja akan membuat siklus nonaktif
        // yang barisnya masih ada di hasil jadi tak bisa dipilih di dropdown.
        $sql = $this->dataset->queryKodeCycleUnik()->toSql();

        $this->assertStringNotContainsString('Flag_Aktif', $sql);
    }
}
