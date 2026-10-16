<?php

namespace Tests\Unit\ESS\Attendance\Calendar;

use App\Services\ESS\Attendance\Calendar\Operational\PenentuTanggalTidakOperasional;
use PHPUnit\Framework\TestCase;

/**
 * Mengunci aturan §4 dan §5 spec
 * docs/superpowers/specs/2026-09-04-api-kalender-operasional-ho-design.md.
 *
 * Kelas yang diuji sengaja murni — tanpa database — supaya aturan yang paling
 * mudah salah justru jadi bagian yang bisa diuji tanpa menyentuh Web_HRIS.
 */
class PenentuTanggalTidakOperasionalTest extends TestCase
{
    private PenentuTanggalTidakOperasional $penentu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->penentu = new PenentuTanggalTidakOperasional;
    }

    /** Satu karyawan Head Office aktif. */
    private function ho(): array
    {
        return [['kode' => 'HO1', 'cabang' => 'HEAD OFFICE EVO', 'divisi' => '22', 'sub_divisi' => '5']];
    }

    private function cb(array $ubah = []): array
    {
        return array_merge([
            'id' => 1,
            'nama' => 'Cuti Bersama Idul Fitri',
            'selected_dates' => '["2026-04-02"]',
            'tanggal_mulai' => '2026-04-02',
            'tanggal_selesai' => '2026-04-02',
            'flag_aktif' => 'Y',
            'flag_cancelled' => 'T',
            'filter_kode_karyawan' => null,
            'filter_cabang' => null,
            'filter_divisi' => null,
            'filter_sub_divisi' => null,
        ], $ubah);
    }

    /** @return list<array<string,mixed>> */
    private function jalankan(array $cutiBersama, array $libur = [], array $pengecualian = [], ?array $karyawanHo = null): array
    {
        return $this->penentu->tentukan(
            $libur,
            $cutiBersama,
            $karyawanHo ?? $this->ho(),
            $pengecualian,
            '2026-01-01',
            '2026-12-31',
            '2026-09-04',
        );
    }

    /** @test */
    public function cuti_bersama_tanpa_filter_menutup_head_office(): void
    {
        $hasil = $this->jalankan([$this->cb()]);

        $this->assertCount(1, $hasil);
        $this->assertSame('2026-04-02', $hasil[0]['tanggal']);
        $this->assertSame('CUTI_BERSAMA', $hasil[0]['jenis']);
        $this->assertSame('Cuti Bersama Idul Fitri', $hasil[0]['nama']);
    }

    /** @test */
    public function cuti_bersama_yang_hanya_menyasar_cabang_lain_tidak_menutup_head_office(): void
    {
        $hasil = $this->jalankan([$this->cb(['filter_cabang' => '["CELENTANG"]'])]);

        $this->assertSame([], $hasil);
    }

    /** @test */
    public function cuti_bersama_yang_menyasar_cabang_head_office_menutup_head_office(): void
    {
        $hasil = $this->jalankan([$this->cb(['filter_cabang' => '["HEAD OFFICE EVO"]'])]);

        $this->assertCount(1, $hasil);
    }

    /** @test */
    public function cuti_bersama_yang_menyasar_kode_karyawan_ho_menutup_head_office(): void
    {
        $hasil = $this->jalankan([$this->cb([
            'filter_kode_karyawan' => '["HO1"]',
            'filter_cabang' => '["CELENTANG"]',
        ])]);

        $this->assertCount(1, $hasil, 'Sasaran per kode berlaku terpisah dari sasaran per unit.');
    }

    /** @test */
    public function cuti_bersama_yang_dibatalkan_tidak_terbit(): void
    {
        $this->assertSame([], $this->jalankan([$this->cb(['flag_cancelled' => 'Y'])]));
    }

    /** @test */
    public function cuti_bersama_tidak_aktif_tidak_terbit(): void
    {
        $this->assertSame([], $this->jalankan([$this->cb(['flag_aktif' => 'T'])]));
    }

    /** @test */
    public function cuti_bersama_yang_seluruh_sasaran_ho_nya_dikecualikan_tidak_terbit(): void
    {
        $hasil = $this->jalankan([$this->cb()], [], [1 => ['HO1' => true]]);

        $this->assertSame([], $hasil);
    }

    /** @test */
    public function cuti_bersama_tetap_terbit_bila_masih_ada_satu_karyawan_ho_yang_tidak_dikecualikan(): void
    {
        $karyawan = [
            ['kode' => 'HO1', 'cabang' => 'HEAD OFFICE EVO', 'divisi' => '22', 'sub_divisi' => '5'],
            ['kode' => 'HO2', 'cabang' => 'HEAD OFFICE EVO', 'divisi' => '22', 'sub_divisi' => '5'],
        ];

        $hasil = $this->jalankan([$this->cb()], [], [1 => ['HO1' => true]], $karyawan);

        $this->assertCount(1, $hasil, 'Satu karyawan HO yang tersisa sudah cukup menutup kantor.');
    }

    /** @test */
    public function cuti_bersama_yang_belum_dieksekusi_tetap_terbit(): void
    {
        // Flag_Executed menandai saldo cuti sudah dipotong, bukan kantor tutup.
        $hasil = $this->jalankan([$this->cb(['flag_executed' => 'T'])]);

        $this->assertCount(1, $hasil);
    }

    /** @test */
    public function selected_dates_dipakai_bila_terisi(): void
    {
        $hasil = $this->jalankan([$this->cb([
            'selected_dates' => '["2026-04-02","2026-04-06"]',
            'tanggal_mulai' => '2026-04-02',
            'tanggal_selesai' => '2026-04-06',
        ])]);

        $this->assertSame(['2026-04-02', '2026-04-06'], array_column($hasil, 'tanggal'));
    }

    /** @test */
    public function rentang_dibentangkan_bila_selected_dates_kosong(): void
    {
        $hasil = $this->jalankan([$this->cb([
            'selected_dates' => null,
            'tanggal_mulai' => '2026-04-02',
            'tanggal_selesai' => '2026-04-04',
        ])]);

        $this->assertSame(['2026-04-02', '2026-04-03', '2026-04-04'], array_column($hasil, 'tanggal'));
    }

    /** @test */
    public function libur_nasional_menang_atas_cuti_bersama_pada_tanggal_sama(): void
    {
        $hasil = $this->jalankan(
            [$this->cb()],
            [['tanggal' => '2026-04-02', 'nama' => 'Wafat Yesus Kristus']],
        );

        $this->assertCount(1, $hasil, 'Tanggal tidak boleh terduplikat.');
        $this->assertSame('LIBUR_NASIONAL', $hasil[0]['jenis']);
        $this->assertSame('Wafat Yesus Kristus', $hasil[0]['nama']);
        $this->assertArrayNotHasKey('status', $hasil[0], 'status hanya milik CUTI_BERSAMA.');
    }

    /** @test */
    public function keluaran_urut_menaik_dan_tanpa_duplikat(): void
    {
        $hasil = $this->jalankan(
            [
                $this->cb(['id' => 5, 'selected_dates' => '["2026-06-10"]']),
                $this->cb(['id' => 6, 'selected_dates' => '["2026-02-11","2026-06-10"]']),
            ],
            [
                ['tanggal' => '2026-12-25', 'nama' => 'Hari Raya Natal'],
                ['tanggal' => '2026-01-01', 'nama' => 'Tahun Baru 2026'],
            ],
        );

        $tanggal = array_column($hasil, 'tanggal');

        $this->assertSame(['2026-01-01', '2026-02-11', '2026-06-10', '2026-12-25'], $tanggal);
        $this->assertSame($tanggal, array_unique($tanggal));
    }

    /** @test */
    public function cuti_bersama_ber_id_terkecil_menang_saat_tanggalnya_sama(): void
    {
        // Diberikan dengan urutan terbalik: keluaran tidak boleh bergantung
        // urutan baris yang kebetulan dikembalikan SQL Server.
        $hasil = $this->jalankan([
            $this->cb(['id' => 9, 'nama' => 'Yang Belakangan']),
            $this->cb(['id' => 2, 'nama' => 'Yang Terdahulu']),
        ]);

        $this->assertCount(1, $hasil);
        $this->assertSame('Yang Terdahulu', $hasil[0]['nama']);
    }

    /** @test */
    public function status_dihitung_dari_posisi_tanggal_terhadap_hari_ini(): void
    {
        $hasil = $this->jalankan([
            $this->cb(['id' => 1, 'selected_dates' => '["2026-09-03"]']),
            $this->cb(['id' => 2, 'selected_dates' => '["2026-09-04"]']),
            $this->cb(['id' => 3, 'selected_dates' => '["2026-09-05"]']),
        ]);

        $this->assertSame(
            ['SELESAI', 'BERJALAN', 'TERJADWAL'],
            array_column($hasil, 'status'),
        );
    }

    /** @test */
    public function tanggal_di_luar_rentang_yang_diminta_tidak_terbit(): void
    {
        $hasil = $this->penentu->tentukan(
            [['tanggal' => '2026-01-01', 'nama' => 'Tahun Baru 2026']],
            [$this->cb(['selected_dates' => '["2026-04-02","2026-10-20"]'])],
            $this->ho(),
            [],
            '2026-09-01',
            '2026-12-31',
            '2026-09-04',
        );

        $this->assertSame(['2026-10-20'], array_column($hasil, 'tanggal'));
    }

    /** @test */
    public function kode_karyawan_berspasi_ekor_tetap_cocok(): void
    {
        // Kolom Kode_Karyawan bertipe varchar dan isinya kerap berspasi ekor.
        $hasil = $this->jalankan(
            [$this->cb(['filter_kode_karyawan' => '["ho1 "]', 'filter_cabang' => '["CELENTANG"]'])],
            [],
            [],
            [['kode' => ' HO1', 'cabang' => 'HEAD OFFICE EVO', 'divisi' => '22', 'sub_divisi' => '5']],
        );

        $this->assertCount(1, $hasil);
    }

    /** @test */
    public function tanpa_karyawan_ho_tidak_ada_cuti_bersama_yang_terbit(): void
    {
        $hasil = $this->jalankan([$this->cb()], [], [], []);

        $this->assertSame([], $hasil, 'Tanpa karyawan HO, tak ada yang bisa membuat HO tutup.');
    }
}
