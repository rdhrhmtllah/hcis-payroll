<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopApqcService;
use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Master APQC — klasifikasi proses bisnis dokumen SOP.
 *
 * Berisi gerbang skema DDL-2026-023 sekaligus perilakunya. DDL modul ini
 * dijalankan manual, jadi tes skema di sini SATU-SATUNYA bukti otomatis bahwa
 * tabelnya sudah ada di basis data yang sedang dipakai.
 */
class SopApqcTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopApqcService
    {
        return app(SopApqcService::class);
    }

    private function kolom(string $tabel): array
    {
        return array_map(
            fn ($r) => $r->COLUMN_NAME,
            DB::select('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?', [$tabel])
        );
    }

    /**
     * Kode proses yang belum dipakai siapa pun di basis data ini.
     *
     * Sebelumnya fixture menuliskan '99' dan '98' apa adanya. Angka mutlak
     * seperti itu bertahan hanya sampai ada orang membuat APQC bernomor sama
     * lewat layar — dan itu memang terjadi: 'CONTOH_2 + Revisi' menempati 99
     * pada 10 September 2026, lalu berkas ini merah dengan pesan yang terbaca
     * seperti cacat kode, padahal cuma tabrakan nomor. Nomor APQC pun sengaja
     * tetap terpakai walau baris­nya dinonaktifkan, jadi menonaktifkannya tidak
     * membebaskan nomornya.
     */
    private function kodeProsesBebas(): string
    {
        $terpakai = DB::table('N_SOP_Master_APQC')->pluck('Kode_Proses')
            ->map(fn ($v) => trim((string) $v))->all();

        for ($i = 999; $i >= 100; $i--) {
            if (! in_array((string) $i, $terpakai, true)) {
                return (string) $i;
            }
        }

        $this->fail('Tidak ada kode proses bebas untuk fixture ini.');
    }

    public function test_tabel_apqc_dan_pemetaannya_ada(): void
    {
        $kolom = $this->kolom('N_SOP_Master_APQC');
        $this->assertNotEmpty(
            $kolom,
            'Tabel N_SOP_Master_APQC belum ada — jalankan '
            .'database/ddl/DDL-2026-023-sop-master-apqc/forward.sql'
        );

        foreach (['Id_SOP_APQC', 'Kode_Proses', 'Nama_Proses', 'Urutan', 'Flag_Aktif'] as $wajib) {
            $this->assertContains($wajib, $kolom, "Kolom {$wajib} hilang di N_SOP_Master_APQC.");
        }

        foreach (['Id_SOP_APQC_Tipe_Item', 'APQC_Id', 'Tipe_Item', 'Flag_Aktif'] as $wajib) {
            $this->assertContains($wajib, $this->kolom('N_SOP_Master_APQC_Tipe_Item'),
                "Kolom {$wajib} hilang di tabel pemetaan APQC.");
        }

        $this->assertContains('APQC_Id', $this->kolom('N_SOP_Dokumen'),
            'N_SOP_Dokumen.APQC_Id belum ada — jalankan DDL-2026-023.');
    }

    public function test_kolom_apqc_dokumen_nullable(): void
    {
        // Dokumen lama lahir sebelum kolom ini ada.
        $nullable = DB::selectOne(
            'SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['N_SOP_Dokumen', 'APQC_Id']
        );
        $this->assertSame('YES', $nullable->IS_NULLABLE);
    }

    public function test_tiga_belas_kategori_ter_seed(): void
    {
        $kode = DB::table('N_SOP_Master_APQC')->pluck('Nama_Proses', 'Kode_Proses')->all();

        foreach (['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13'] as $k) {
            $this->assertArrayHasKey(
                $k,
                $kode,
                "Kategori APQC {$k} belum ter-seed — jalankan database/dml/DML-2026-024-seed-sop-apqc/data-change.sql"
            );
        }

        $this->assertSame('Manage Information Technology', $kode['08'],
            'Ejaan kategori 08 diperbaiki dari daftar asal saat seed; nilai ini dikutip di audit.');
    }

    public function test_opsi_disaring_oleh_tipe_item(): void
    {
        /* Pemetaan default: DOKUMEN, FORM, VIDEO. KUIS sengaja tidak dipetakan,
           dengan alasan yang sama seperti pada jenis dokumen. */
        $this->assertNotEmpty($this->svc()->opsi('DOKUMEN'));
        $this->assertNotEmpty($this->svc()->opsi('FORM'));
        $this->assertSame([], $this->svc()->opsi('KUIS'),
            'KUIS bukan dokumen yang memetakan proses, jadi ia tak boleh menawarkan APQC.');
    }

    public function test_mencabut_seluruh_tipe_membuat_apqc_berhenti_ditawarkan(): void
    {
        /* Inilah inti keputusan menaruh aturan sebagai DATA: admin mengubah
           kapan APQC berlaku lewat master, bukan lewat rilis. */
        $id = (int) DB::table('N_SOP_Master_APQC')->where('Kode_Proses', '01')->value('Id_SOP_APQC');

        $this->svc()->setTipeItem($id, [], 1);

        $kode = array_column($this->svc()->opsi('DOKUMEN'), 'Kode_Proses');
        $this->assertNotContains('01', $kode);
        $this->assertFalse($this->svc()->pasanganSah($id, 'DOKUMEN'));
    }

    public function test_dokumen_menyimpan_apqc_yang_sah(): void
    {
        $id = (int) DB::table('N_SOP_Master_APQC')->where('Kode_Proses', '07')->value('Id_SOP_APQC');

        $idDok = app(SopDokumenService::class)->buat([
            'Judul' => 'SOP UJI APQC SAH',
            'Tipe' => 'DOKUMEN',
            'APQC_Id' => $id,
        ], 1);

        $this->assertSame($id, (int) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)->value('APQC_Id'));
    }

    public function test_pasangan_apqc_dan_tipe_yang_tak_sah_ditolak(): void
    {
        $id = (int) DB::table('N_SOP_Master_APQC')->where('Kode_Proses', '02')->value('Id_SOP_APQC');
        $this->svc()->setTipeItem($id, ['FORM'], 1);

        $this->expectException(\DomainException::class);

        app(SopDokumenService::class)->buat([
            'Judul' => 'SOP UJI APQC TAK SAH',
            'Tipe' => 'DOKUMEN',
            'APQC_Id' => $id,
        ], 1);
    }

    public function test_apqc_nonaktif_ditolak(): void
    {
        $id = $this->svc()->create([
            'Kode_Proses' => $this->kodeProsesBebas(),
            'Nama_Proses' => 'Uji Nonaktif',
            'Tipe_Item' => ['DOKUMEN'],
        ], 1);
        $this->svc()->softDelete($id, 1);

        $this->expectException(\DomainException::class);

        app(SopDokumenService::class)->buat([
            'Judul' => 'SOP UJI APQC MATI',
            'Tipe' => 'DOKUMEN',
            'APQC_Id' => $id,
        ], 1);
    }

    public function test_apqc_yang_masih_dipakai_tak_bisa_dinonaktifkan(): void
    {
        $id = $this->svc()->create([
            'Kode_Proses' => $this->kodeProsesBebas(),
            'Nama_Proses' => 'Uji Terpakai',
            'Tipe_Item' => ['DOKUMEN'],
        ], 1);

        app(SopDokumenService::class)->buat([
            'Judul' => 'SOP UJI APQC TERPAKAI',
            'Tipe' => 'DOKUMEN',
            'APQC_Id' => $id,
        ], 1);

        $this->assertSame(1, $this->svc()->softDelete($id, 1));
        $this->assertSame(SopStatus::AKTIF,
            DB::table('N_SOP_Master_APQC')->where('Id_SOP_APQC', $id)->value('Flag_Aktif'),
            'Penolakan harus membatalkan penonaktifan, bukan sekadar melaporkan angka.');
    }

    public function test_nomor_proses_tak_boleh_kembar(): void
    {
        $this->expectException(\DomainException::class);

        $this->svc()->create(['Kode_Proses' => '07', 'Nama_Proses' => 'Kembar'], 1);
    }

    public function test_jalur_backfill_tetap_boleh_tanpa_apqc(): void
    {
        $idDok = app(SopDokumenService::class)->buat([
            'Judul' => 'SOP UJI TANPA APQC',
            'Tipe' => 'DOKUMEN',
        ], 1);

        $this->assertNull(DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)->value('APQC_Id'));
    }
}
