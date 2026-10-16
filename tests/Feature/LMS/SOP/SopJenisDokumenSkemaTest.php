<?php

namespace Tests\Feature\LMS\SOP;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Gerbang skema DDL-2026-016.
 *
 * DDL modul SOP dijalankan manual (tak ada Laravel migration), jadi tes inilah
 * SATU-SATUNYA yang membuktikan tabel dan kolomnya sudah benar-benar ada di
 * basis data yang sedang dipakai. Tanpa ini, kegagalannya baru muncul sebagai
 * "Invalid column name" di tengah alur admin.
 */
class SopJenisDokumenSkemaTest extends TestCase
{
    private function kolom(string $tabel): array
    {
        return array_map(
            fn ($r) => $r->COLUMN_NAME,
            DB::select('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?', [$tabel])
        );
    }

    public function test_tabel_master_jenis_dokumen_ada_dengan_kolom_yang_dibutuhkan(): void
    {
        $kolom = $this->kolom('N_SOP_Master_Jenis_Dokumen');
        $this->assertNotEmpty(
            $kolom,
            'Tabel N_SOP_Master_Jenis_Dokumen belum ada — jalankan '
            .'database/ddl/DDL-2026-016-sop-jenis-dokumen-kepemilikan/forward.sql'
        );

        foreach ([
            'Id_SOP_Jenis_Dokumen', 'Kode_Jenis', 'Nama_Jenis', 'Level_Hirarki',
            'Deskripsi', 'Urutan', 'Flag_Aktif', 'Created_At', 'Created_By', 'Updated_At', 'Updated_By',
        ] as $wajib) {
            $this->assertContains($wajib, $kolom, "Kolom {$wajib} hilang di N_SOP_Master_Jenis_Dokumen.");
        }
    }

    public function test_tabel_pemetaan_tipe_item_ada(): void
    {
        $kolom = $this->kolom('N_SOP_Master_Jenis_Dokumen_Tipe_Item');
        $this->assertNotEmpty($kolom, 'Tabel pemetaan jenis↔tipe belum ada — jalankan DDL-2026-016.');

        foreach (['Id_SOP_Jenis_Dokumen_Tipe_Item', 'Jenis_Dokumen_Id', 'Tipe_Item', 'Flag_Aktif'] as $wajib) {
            $this->assertContains($wajib, $kolom, "Kolom {$wajib} hilang di tabel pemetaan.");
        }
    }

    public function test_tiga_kolom_tata_kelola_ditambahkan_ke_dokumen(): void
    {
        $kolom = $this->kolom('N_SOP_Dokumen');

        foreach (['Jenis_Dokumen_Id', 'Divisi_Id', 'Sub_Divisi_Id'] as $wajib) {
            $this->assertContains($wajib, $kolom, "N_SOP_Dokumen.{$wajib} belum ada — jalankan DDL-2026-016.");
        }
    }

    public function test_kolom_tata_kelola_nullable(): void
    {
        // 41 dokumen lahir sebelum kolom ini ada. NOT NULL akan menolak setiap
        // pembacaan-tulis atas baris lama, termasuk yang tak menyentuh klasifikasi.
        foreach (['Jenis_Dokumen_Id', 'Divisi_Id', 'Sub_Divisi_Id'] as $k) {
            $nullable = DB::selectOne(
                'SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?',
                ['N_SOP_Dokumen', $k]
            );
            $this->assertSame('YES', $nullable->IS_NULLABLE, "N_SOP_Dokumen.{$k} harus nullable.");
        }
    }

    public function test_empat_jenis_dasar_sudah_ter_seed(): void
    {
        $kode = DB::table('N_SOP_Master_Jenis_Dokumen')->pluck('Kode_Jenis')->all();

        foreach (['KEBIJAKAN', 'PROSEDUR', 'INSTRUKSI_KERJA', 'FORMULIR'] as $k) {
            $this->assertContains(
                $k,
                $kode,
                "Jenis {$k} belum ter-seed — jalankan database/dml/DML-2026-017-seed-sop-jenis-dokumen/data-change.sql"
            );
        }
    }
}
