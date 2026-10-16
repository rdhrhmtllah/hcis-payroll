<?php

namespace Tests\Feature\SYSTEM\Organization;

use App\Services\SYSTEM\Organization\OrganizationRelationApprovalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Usulan ATASAN dari approval KPI/HCIS → Excel → impor ke Relasi Organisasi.
 * Memakai data dev sungguhan (approval KPI ±900 baris); semua tulisan dibatalkan.
 */
class RelasiDariApprovalTest extends TestCase
{
    use DatabaseTransactions;

    private OrganizationRelationApprovalService $layanan;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->layanan = app(OrganizationRelationApprovalService::class);
        $this->dir = sys_get_temp_dir().'/uji-relasi-approval-'.bin2hex(random_bytes(4));
        @mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    /** Berkas usulan dengan sejumlah baris BEDA ditandai Impor=Y, Ganti=Y. */
    private function berkas(int $jumlahBeda, ?callable $ubah = null): string
    {
        $jalur = $this->dir.'/usulan.xlsx';
        file_put_contents($jalur, $this->layanan->ekspor('uji')['isi']);
        $buku = IOFactory::load($jalur);
        $w = $buku->getSheet(0);
        $n = 0;
        [$status, $impor, $ganti] = [$this->kol($w, 'Status'), $this->kol($w, 'Impor (Y/T)'), $this->kol($w, 'Ganti Atasan Lama (Y/T)')];
        for ($r = 2; $r <= $w->getHighestRow(); $r++) {
            $w->setCellValue("{$impor}{$r}", 'T');
        }
        for ($r = 2; $r <= $w->getHighestRow() && $n < $jumlahBeda; $r++) {
            if ($w->getCell("{$status}{$r}")->getValue() === 'BEDA') {
                $w->setCellValue("{$impor}{$r}", 'Y');
                $w->setCellValue("{$ganti}{$r}", 'Y');
                $n++;
            }
        }
        if ($ubah) {
            $ubah($w);
        }
        $hasil = $this->dir.'/olah.xlsx';
        IOFactory::createWriter($buku, 'Xlsx')->save($hasil);

        return $hasil;
    }

    /** Huruf kolom menurut judulnya — uji tidak bergantung pada urutan kolom. */
    private function kol($w, string $judul): string
    {
        foreach ($w->getRowIterator(1, 1)->current()->getCellIterator() as $sel) {
            if ($sel->getValue() === $judul) {
                return $sel->getColumn();
            }
        }
        $this->fail("Kolom '{$judul}' tidak ada di lembar usulan.");
    }

    public function test_lembar_usulan_tanpa_kode_perusahaan_dan_isian_admin_berdekatan(): void
    {
        $jalur = $this->dir.'/usulan.xlsx';
        file_put_contents($jalur, $this->layanan->ekspor('uji')['isi']);
        $kepala = IOFactory::load($jalur)->getSheet(0)->rangeToArray('A1:Z1')[0];

        $this->assertEmpty(array_filter($kepala, fn ($k) => $k !== null && stripos($k, 'perusahaan') !== false), 'Kode perusahaan tidak ditampilkan.');
        $this->assertSame(
            ['Status', 'Atasan 1 (Kode Karyawan)', 'Nama Atasan 1 (usulan)', 'Atasan 2 (Kode Karyawan)', 'Nama Atasan 2 (usulan)', 'Impor (Y/T)', 'Ganti Atasan Lama (Y/T)'],
            array_slice($kepala, 5, 7),
        );
    }

    public function test_approver_tidak_aktif_tidak_ditampilkan_dan_tidak_diusulkan(): void
    {
        [$a, $b] = $this->empatKaryawan();
        $nonaktif = strtoupper(trim((string) DB::table('Karyawan')->where('Aktif', 'T')->orderBy('Kode_Karyawan')->value('Kode_Karyawan')));
        $this->assertNotSame('', $nonaktif, 'Butuh satu karyawan tidak aktif di data dev.');
        $this->approvalUji($a, kpi: [1 => $nonaktif, 2 => $b], hcis: []);
        $this->approvalUji($b, kpi: [], hcis: []); // tanpa atasannya atasan

        $u = collect($this->layanan->usulan())->firstWhere('kode_karyawan', $a);

        $this->assertSame($b, $u['atasan_dipakai'], 'Approver tidak aktif dilewati; yang aktif berikutnya jadi Atasan 1.');
        $this->assertSame('', $u['atasan2_dipakai']);
        $this->assertStringNotContainsString($nonaktif, $u['atasan_kpi'].$u['lanjutan'].$u['catatan']);

        // Bila tak satu pun approver aktif, barisnya tetap ada supaya admin tahu.
        $this->approvalUji($a, kpi: [1 => $nonaktif], hcis: []);
        $u = collect($this->layanan->usulan())->firstWhere('kode_karyawan', $a);
        $this->assertSame('TANPA_APPROVER_AKTIF', $u['status']);
        $this->assertSame('T', $u['impor']);
    }

    public function test_usulan_menandai_status_dan_hanya_baru_atau_lengkapi_yang_otomatis_diimpor(): void
    {
        $usulan = collect($this->layanan->usulan());
        if ($usulan->isEmpty()) {
            $this->markTestSkipped('Data dev tidak punya approval KPI/HCIS.');
        }

        foreach ($usulan as $u) {
            $this->assertContains($u['status'], ['BARU', 'LENGKAPI', 'BEDA', 'SAMA', 'TANPA_APPROVER_AKTIF']);
            // Hanya yang tidak mengganti siapa pun yang otomatis diimpor; mengganti selalu keputusan admin.
            $this->assertSame(in_array($u['status'], ['BARU', 'LENGKAPI'], true) ? 'Y' : 'T', $u['impor']);
            if ($u['atasan2_dipakai'] !== '') {
                $this->assertNotSame($u['atasan_dipakai'], $u['atasan2_dipakai'], 'Atasan 2 tidak boleh sama dengan Atasan 1.');
            }
            $this->assertSame('T', $u['ganti']);
        }

        $kpi = DB::table('KPI_Approval_Flow_Performance')->where('order_flow', 1)->first(['Kode_Karyawan_Requester', 'Kode_Karyawan_Approver']);
        $baris = $usulan->firstWhere('kode_karyawan', strtoupper(trim($kpi->Kode_Karyawan_Requester)));
        if ($baris !== null) {
            $this->assertStringContainsString(strtoupper(trim($kpi->Kode_Karyawan_Approver)), $baris['atasan_kpi']);
        }
    }

    public function test_pratinjau_tidak_menulis_dan_jalankan_membentuk_pasangan_atasan_bawahan(): void
    {
        $jalur = $this->berkas(20);
        $sebelum = DB::table('N_HRIS_Organization_Relationship')->where('Sumber_Data', OrganizationRelationApprovalService::SUMBER)->count();

        $pratinjau = $this->layanan->impor($jalur, false, 4);
        if ($pratinjau['diimpor'] === 0) {
            $this->markTestSkipped('Tidak ada baris BEDA di data dev.');
        }
        $this->assertFalse($pratinjau['dijalankan']);
        $this->assertSame([], $pratinjau['galat']);
        $this->assertSame($sebelum, DB::table('N_HRIS_Organization_Relationship')->where('Sumber_Data', OrganizationRelationApprovalService::SUMBER)->count());

        $hasil = $this->layanan->impor($jalur, true, 4);
        $this->assertTrue($hasil['dijalankan']);
        $this->assertSame($pratinjau['dibuat'], $hasil['dibuat'], 'Angka pratinjau = angka sungguhan.');
        $this->assertSame($sebelum + 2 * $hasil['dibuat'], DB::table('N_HRIS_Organization_Relationship')->where('Sumber_Data', OrganizationRelationApprovalService::SUMBER)->where('Flag_Aktif', 'Y')->count());

        $timpang = DB::selectOne("SELECT COUNT(*) n FROM N_HRIS_Organization_Relationship a
            JOIN N_HRIS_Organization_Relationship_Type t ON t.Id_Organization_Relationship_Type = a.Id_Organization_Relationship_Type AND t.Kode_Relasi = 'ATASAN'
            WHERE a.Flag_Aktif = 'Y' AND a.Sumber_Data = ? AND NOT EXISTS (
                SELECT 1 FROM N_HRIS_Organization_Relationship b
                JOIN N_HRIS_Organization_Relationship_Type tb ON tb.Id_Organization_Relationship_Type = b.Id_Organization_Relationship_Type AND tb.Kode_Relasi = 'BAWAHAN'
                WHERE b.Flag_Aktif = 'Y' AND b.Kode_Karyawan_Subjek = a.Kode_Karyawan_Terkait AND b.Kode_Karyawan_Terkait = a.Kode_Karyawan_Subjek)", [OrganizationRelationApprovalService::SUMBER])->n;
        $this->assertSame(0, (int) $timpang, 'Setiap ATASAN punya pasangan BAWAHAN.');

        // Ganti = Y: subjek yang diimpor tinggal punya paling banyak dua atasan aktif (Atasan 1 dan 2).
        $ganda = DB::selectOne("SELECT COUNT(*) n FROM (SELECT a.Kode_Karyawan_Subjek FROM N_HRIS_Organization_Relationship a
            JOIN N_HRIS_Organization_Relationship_Type t ON t.Id_Organization_Relationship_Type = a.Id_Organization_Relationship_Type AND t.Kode_Relasi = 'ATASAN'
            WHERE a.Flag_Aktif = 'Y' AND a.Kode_Karyawan_Subjek IN (SELECT i.Kode_Karyawan_Subjek FROM N_HRIS_Organization_Relationship i
                WHERE i.Sumber_Data = ? AND i.Flag_Aktif = 'Y' AND i.Id_Organization_Relationship_Type = t.Id_Organization_Relationship_Type)
            GROUP BY a.Kode_Karyawan_Subjek HAVING COUNT(*) > 2) x", [OrganizationRelationApprovalService::SUMBER])->n;
        $this->assertSame(0, (int) $ganda);

        // Diimpor ulang: tidak ada yang dibuat atau dicabut lagi.
        $ulang = $this->layanan->impor($jalur, true, 4);
        $this->assertSame(0, $ulang['dibuat']);
        $this->assertSame(0, $ulang['dicabut']);
    }

    /**
     * Keputusan user 14 Okt 2026: rantai KPI urutan 1–2 lalu HCIS urutan 1–2, tanpa nama ganda,
     * dua orang pertama jadi Atasan 1 dan 2. KPI [B], HCIS [B, C] → B dan C.
     */
    public function test_dua_atasan_dari_gabungan_kpi_lalu_hcis_tanpa_ganda(): void
    {
        [$a, $b, $c, $d] = $this->empatKaryawan();
        $this->approvalUji($a, kpi: [1 => $b], hcis: [1 => $b, 2 => $c]);

        $u = collect($this->layanan->usulan())->firstWhere('kode_karyawan', $a);
        $this->assertSame($b, $u['atasan_dipakai']);
        $this->assertSame($c, $u['atasan2_dipakai']);
        $this->assertStringContainsString('Atasan 1 dari KPI urutan 1; Atasan 2 dari HCIS urutan 2', $u['catatan']);

        // KPI yang punya dua tingkat dipakai utuh; HCIS hanya keterangan.
        $this->approvalUji($a, kpi: [1 => $b, 2 => $d], hcis: [1 => $c]);
        $u = collect($this->layanan->usulan())->firstWhere('kode_karyawan', $a);
        $this->assertSame([$b, $d], [$u['atasan_dipakai'], $u['atasan2_dipakai']]);
        $this->assertStringContainsString($c, $u['lanjutan']);
    }

    public function test_atasan_2_dari_atasannya_atasan_bila_approval_hanya_satu_orang(): void
    {
        [$a, $b, $c, $d] = $this->empatKaryawan();
        $this->approvalUji($a, kpi: [1 => $b], hcis: [1 => $b]);
        $this->approvalUji($b, kpi: [], hcis: [1 => $d]);

        $u = collect($this->layanan->usulan())->firstWhere('kode_karyawan', $a);

        $this->assertSame([$b, $d], [$u['atasan_dipakai'], $u['atasan2_dipakai']]);
        $this->assertStringContainsString('Atasan 2 dari approval Atasan 1 (HCIS urutan 1)', $u['catatan']);

        // Bila approval karyawan sendiri sudah memberi dua orang, atasannya atasan tidak dipakai.
        $this->approvalUji($a, kpi: [1 => $b, 2 => $c], hcis: []);
        $u = collect($this->layanan->usulan())->firstWhere('kode_karyawan', $a);
        $this->assertSame([$b, $c], [$u['atasan_dipakai'], $u['atasan2_dipakai']]);
    }

    public function test_impor_menulis_atasan_1_dan_2_dengan_prioritas_dan_pasangan_bawahan(): void
    {
        [$a, $b, $c] = $this->empatKaryawan();
        $this->approvalUji($a, kpi: [1 => $b], hcis: [1 => $b, 2 => $c]);
        DB::table('N_HRIS_Organization_Relationship')->where('Kode_Karyawan_Subjek', $a)->orWhere('Kode_Karyawan_Terkait', $a)
            ->update(['Flag_Aktif' => 'T', 'Cancelled_At' => now()]);

        $jalur = $this->dir.'/usulan.xlsx';
        file_put_contents($jalur, $this->layanan->ekspor('uji')['isi']);
        $buku = IOFactory::load($jalur);
        $w = $buku->getSheet(0);
        [$kode, $impor, $status, $atasan2] = [$this->kol($w, 'Kode Karyawan'), $this->kol($w, 'Impor (Y/T)'), $this->kol($w, 'Status'), $this->kol($w, 'Atasan 2 (Kode Karyawan)')];
        for ($r = 2; $r <= $w->getHighestRow(); $r++) {
            $milikA = $w->getCell("{$kode}{$r}")->getValue() === $a;
            $w->setCellValue("{$impor}{$r}", $milikA ? 'Y' : 'T');
            if ($milikA) {
                $this->assertSame('BARU', $w->getCell("{$status}{$r}")->getValue());
                $this->assertSame($c, $w->getCell("{$atasan2}{$r}")->getValue());
            }
        }
        $olah = $this->dir.'/olah.xlsx';
        IOFactory::createWriter($buku, 'Xlsx')->save($olah);

        $hasil = $this->layanan->impor($olah, true, 4);

        $this->assertSame([], $hasil['galat']);
        $this->assertSame(2, $hasil['dibuat']);
        $atasan = DB::table('N_HRIS_Organization_Relationship as r')
            ->join('N_HRIS_Organization_Relationship_Type as t', 't.Id_Organization_Relationship_Type', '=', 'r.Id_Organization_Relationship_Type')
            ->where('t.Kode_Relasi', 'ATASAN')->where('r.Flag_Aktif', 'Y')->where('r.Kode_Karyawan_Subjek', $a)
            ->orderBy('r.Prioritas')->pluck('r.Prioritas', 'r.Kode_Karyawan_Terkait')->map(fn ($p) => (int) $p)->all();
        $this->assertSame([$b => 1, $c => 2], $atasan);
        $this->assertSame(2, DB::table('N_HRIS_Organization_Relationship as r')
            ->join('N_HRIS_Organization_Relationship_Type as t', 't.Id_Organization_Relationship_Type', '=', 'r.Id_Organization_Relationship_Type')
            ->where('t.Kode_Relasi', 'BAWAHAN')->where('r.Flag_Aktif', 'Y')->where('r.Kode_Karyawan_Terkait', $a)
            ->whereIn('r.Kode_Karyawan_Subjek', [$b, $c])->count(), 'Kedua atasan mendapat pasangan BAWAHAN.');
    }

    public function test_berkas_lama_tanpa_kolom_atasan_2_tetap_terbaca(): void
    {
        [$a, $b, $c] = $this->empatKaryawan();
        $this->approvalUji($a, kpi: [1 => $b], hcis: [1 => $b, 2 => $c]);
        DB::table('N_HRIS_Organization_Relationship')->where('Kode_Karyawan_Subjek', $a)->orWhere('Kode_Karyawan_Terkait', $a)
            ->update(['Flag_Aktif' => 'T', 'Cancelled_At' => now()]);

        $jalur = $this->dir.'/usulan.xlsx';
        file_put_contents($jalur, $this->layanan->ekspor('uji')['isi']);
        $buku = IOFactory::load($jalur);
        $w = $buku->getSheet(0);
        // Bentuk berkas sebelum 14 Okt 2026: judul lama, tanpa kolom Atasan 2.
        $w->setCellValue($this->kol($w, 'Atasan 1 (Kode Karyawan)').'1', 'Atasan Dipakai (Kode Karyawan)');
        $w->removeColumn($this->kol($w, 'Atasan 2 (Kode Karyawan)'), 2);
        [$kode, $impor] = [$this->kol($w, 'Kode Karyawan'), $this->kol($w, 'Impor (Y/T)')];
        for ($r = 2; $r <= $w->getHighestRow(); $r++) {
            $w->setCellValue("{$impor}{$r}", $w->getCell("{$kode}{$r}")->getValue() === $a ? 'Y' : 'T');
        }
        $olah = $this->dir.'/lama.xlsx';
        IOFactory::createWriter($buku, 'Xlsx')->save($olah);

        $hasil = $this->layanan->impor($olah, false, 4);

        $this->assertSame([], $hasil['galat']);
        $this->assertSame(1, $hasil['dibuat'], 'Tanpa kolom Atasan 2, hanya Atasan 1 yang dibuat.');
    }

    /** @return list<string> empat kode karyawan aktif yang berbeda */
    private function empatKaryawan(): array
    {
        $kode = DB::table('Karyawan')->where('Aktif', 'Y')->whereNull('Tanggal_Resign')
            ->orderBy('Kode_Karyawan')->limit(40)->pluck('Kode_Karyawan')
            ->map(fn ($k) => strtoupper(trim((string) $k)))->unique()->values()->slice(10, 4)->values()->all();
        $this->assertCount(4, $kode, 'Butuh empat karyawan aktif di data dev.');

        return $kode;
    }

    /** Mengganti seluruh alur approval pemohon dengan rantai uji (dibatalkan oleh transaksi uji). */
    private function approvalUji(string $pemohon, array $kpi, array $hcis): void
    {
        DB::table('KPI_Approval_Flow_Performance')->where('Kode_Karyawan_Requester', $pemohon)->delete();
        DB::table('HRIS_Approval_Flow')->where('Kode_Karyawan_Requester', $pemohon)->delete();
        foreach ($kpi as $urut => $apr) {
            DB::table('KPI_Approval_Flow_Performance')->insert(['Kode_Karyawan_Requester' => $pemohon, 'Kode_Karyawan_Approver' => $apr, 'order_flow' => $urut]);
        }
        foreach ($hcis as $urut => $apr) {
            DB::table('HRIS_Approval_Flow')->insert(['Kode_Karyawan_Requester' => $pemohon, 'Kode_Karyawan' => $apr, 'order_flow' => $urut, 'Division_Id' => 1, 'level_Id' => 1]);
        }
    }

    public function test_satu_baris_salah_membatalkan_semuanya(): void
    {
        $jalur = $this->berkas(5, function ($w) {
            $w->setCellValue($this->kol($w, 'Impor (Y/T)').'2', 'Y');
            // atasan = diri sendiri
            $w->setCellValueExplicit($this->kol($w, 'Atasan 1 (Kode Karyawan)').'2', (string) $w->getCell($this->kol($w, 'Kode Karyawan').'2')->getValue(), 'str');
        });

        $hasil = $this->layanan->impor($jalur, true, 4);

        $this->assertFalse($hasil['dijalankan']);
        $this->assertNotEmpty(array_filter($hasil['galat'], fn ($g) => str_contains($g, 'dirinya sendiri')));
        $this->assertSame(0, DB::table('N_HRIS_Organization_Relationship')->where('Sumber_Data', OrganizationRelationApprovalService::SUMBER)->count());
    }

    public function test_berkas_lama_dengan_kode_perusahaan_angka_tetap_dikenali(): void
    {
        $jalur = $this->berkas(3, function ($w) {
            // Berkas lama masih memuat kode perusahaan; "001" diketik ulang Excel menjadi angka 1.
            $impor = $this->kol($w, 'Impor (Y/T)');
            $w->setCellValue('S1', 'Kode Perusahaan');
            $w->setCellValue('T1', 'Kode Perusahaan Atasan');
            for ($r = 2; $r <= $w->getHighestRow(); $r++) {
                if ($w->getCell("{$impor}{$r}")->getValue() === 'Y') {
                    $w->setCellValue("S{$r}", 1);
                    $w->setCellValue("T{$r}", 1);
                }
            }
        });

        $hasil = $this->layanan->impor($jalur, false, 4);
        if ($hasil['diimpor'] === 0) {
            $this->markTestSkipped('Tidak ada baris BEDA di data dev.');
        }
        $this->assertSame([], $hasil['galat']);
        $this->assertSame(3, $hasil['diimpor']);
    }

    public function test_berkas_tanpa_kolom_wajib_ditolak(): void
    {
        $buku = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $buku->getActiveSheet()->fromArray([['NIK', 'Atasan']], null, 'A1');
        $jalur = $this->dir.'/salah.xlsx';
        IOFactory::createWriter($buku, 'Xlsx')->save($jalur);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Kolom berikut tidak ditemukan');
        $this->layanan->impor($jalur, false, 4);
    }
}
