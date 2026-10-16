<?php

namespace Tests\Feature\SYSTEM\MasterData;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\SYSTEM\MasterData\Departemen\DepartemenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Master Kode Departemen — acuan kepemilikan dokumen dan segmen nomor dokumen.
 *
 * Berisi gerbang skema DDL-2026-027 sekaligus perilaku service-nya. DDL modul
 * ini dijalankan manual (tak ada Laravel migration), jadi tes skema di sini
 * adalah SATU-SATUNYA bukti otomatis bahwa tabelnya benar-benar ada di basis
 * data yang sedang dipakai.
 */
class DepartemenTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): DepartemenService
    {
        return app(DepartemenService::class);
    }

    private function kolom(string $tabel): array
    {
        return array_map(
            fn ($r) => $r->COLUMN_NAME,
            DB::select('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?', [$tabel])
        );
    }

    /** Departemen sementara; dibatalkan lagi oleh DatabaseTransactions. */
    private function buatDepartemen(array $ubah = []): int
    {
        return $this->svc()->create(array_merge([
            'Kode_Departemen' => 'UJI'.substr((string) microtime(true), -5),
            'Nama_Departemen' => 'Departemen Uji',
            'Sub_Divisi_Id' => null,
            'Urutan' => 900,
        ], $ubah), 1);
    }

    /* ── Skema ──────────────────────────────────────────────────────────── */

    public function test_tabel_departemen_ada_dengan_kolom_yang_dibutuhkan(): void
    {
        $kolom = $this->kolom('N_HRIS_Master_Departemen');
        $this->assertNotEmpty(
            $kolom,
            'Tabel N_HRIS_Master_Departemen belum ada — jalankan '
            .'database/ddl/DDL-2026-027-hris-master-departemen/forward.sql'
        );

        foreach ([
            'Id_Departemen', 'Kode_Departemen', 'Nama_Departemen', 'Sub_Divisi_Id',
            'Urutan', 'Flag_Aktif', 'Created_At', 'Created_By', 'Updated_At', 'Updated_By',
        ] as $wajib) {
            $this->assertContains($wajib, $kolom, "Kolom {$wajib} hilang di N_HRIS_Master_Departemen.");
        }
    }

    public function test_kolom_departemen_ditambahkan_ke_dokumen(): void
    {
        $this->assertContains('Departemen_Id', $this->kolom('N_SOP_Dokumen'),
            'N_SOP_Dokumen.Departemen_Id belum ada — jalankan DDL-2026-027.');
    }

    public function test_kode_departemen_unik_di_tingkat_basis_data(): void
    {
        /* Penjaga aplikasi bisa dilewati seed dan skrip; indeks unik tidak.
           Satu kode yang menunjuk dua departemen berarti satu nomor dokumen
           menunjuk dua penanggung jawab. */
        $adaIndeks = DB::selectOne(
            "SELECT COUNT(*) AS n FROM sys.indexes
             WHERE object_id = OBJECT_ID('N_HRIS_Master_Departemen')
               AND is_unique = 1 AND name = 'UX_HRIS_Departemen_Kode'"
        );

        $this->assertSame(1, (int) $adaIndeks->n,
            'Indeks unik UX_HRIS_Departemen_Kode hilang — jalankan DDL-2026-027.');
    }

    /* ── Seed ───────────────────────────────────────────────────────────── */

    public function test_seed_memuat_seluruh_kode_perusahaan(): void
    {
        $this->assertGreaterThanOrEqual(52, DB::table('N_HRIS_Master_Departemen')->count(),
            'Seed 52 kode belum dijalankan — lihat DML-2026-028.');
    }

    public function test_binding_hanya_dipasang_untuk_nama_yang_cocok_persis(): void
    {
        /* Pencocokan mirip pernah diukur dan hasilnya berbahaya: "Key Account"
           70% mirip "ACCOUNTING". Setiap binding yang ada HARUS menunjuk
           sub-divisi yang namanya sama persis. */
        $menyimpang = DB::table('N_HRIS_Master_Departemen as d')
            ->join('HRIS_Sub_Divisi as s', 's.ID_Sub_Divisi', '=', 'd.Sub_Divisi_Id')
            ->whereRaw('UPPER(LTRIM(RTRIM(s.Keterangan))) <> UPPER(LTRIM(RTRIM(d.Nama_Departemen)))')
            ->count();

        $this->assertSame(0, $menyimpang,
            'Ada binding yang namanya tidak sama persis — binding tidak boleh ditebak.');
    }

    /* ── Perilaku service ───────────────────────────────────────────────── */

    public function test_kode_kembar_ditolak(): void
    {
        $kode = 'UJIDUP'.substr((string) microtime(true), -3);
        $this->buatDepartemen(['Kode_Departemen' => $kode]);

        $this->expectException(\DomainException::class);

        $this->buatDepartemen(['Kode_Departemen' => $kode, 'Nama_Departemen' => 'Kembaran']);
    }

    public function test_kode_disimpan_huruf_besar(): void
    {
        $id = $this->buatDepartemen(['Kode_Departemen' => 'ujikcl']);

        $this->assertSame('UJIKCL', $this->svc()->kode($id));
    }

    public function test_kode_tidak_ikut_berubah_saat_disunting(): void
    {
        /* Kode sudah tercetak sebagai segmen nomor dokumen. Memindahkannya
           berarti memindahkan arti nomor yang sudah beredar tanpa jejak. */
        $id = $this->buatDepartemen(['Kode_Departemen' => 'UJITTP']);

        $this->svc()->update($id, [
            'Kode_Departemen' => 'UJITTP',
            'Nama_Departemen' => 'Nama Baru',
            'Sub_Divisi_Id' => null,
            'Urutan' => 5,
        ], 1);

        $baris = $this->svc()->cari($id);
        $this->assertSame('UJITTP', $baris->Kode_Departemen);
        $this->assertSame('Nama Baru', $baris->Nama_Departemen);
    }

    public function test_binding_ke_sub_divisi_yang_tak_ada_ditolak(): void
    {
        $this->expectException(\DomainException::class);

        $this->buatDepartemen(['Sub_Divisi_Id' => 999999999]);
    }

    public function test_binding_boleh_kosong(): void
    {
        /* 35 dari 52 kode memang tak punya pasangan di struktur yang tersimpan.
           Kosong lebih baik daripada dipasangkan ke sub-divisi yang salah. */
        $id = $this->buatDepartemen(['Sub_Divisi_Id' => null]);

        $this->assertNull($this->svc()->subDivisiTerikat($id));
    }

    public function test_departemen_nonaktif_tidak_muncul_di_opsi(): void
    {
        $id = $this->buatDepartemen(['Kode_Departemen' => 'UJINON']);
        $this->svc()->softDelete($id, 1);

        $kode = array_column($this->svc()->opsi(), 'Kode_Departemen');
        $this->assertNotContains('UJINON', $kode);
    }

    public function test_departemen_yang_masih_dipakai_dokumen_tak_bisa_dinonaktifkan(): void
    {
        $id = $this->buatDepartemen();
        app(SopDokumenService::class)->buat([
            'Judul' => 'SOP UJI PEMAKAI DEPARTEMEN',
            'Tipe' => 'DOKUMEN',
            'Departemen_Id' => $id,
        ], 1);

        $this->assertSame(1, $this->svc()->softDelete($id, 1),
            'Menonaktifkan departemen yang masih dipakai akan membuat dokumen menunjuk master yang hilang.');
        $this->assertNotNull($this->svc()->cari($id), 'Baris tidak boleh ikut dinonaktifkan saat ditolak.');
    }

    public function test_kode_dan_nama_tetap_terbaca_setelah_dinonaktifkan(): void
    {
        /* Dokumen lama tetap menunjuk departemen yang sudah dinonaktifkan, dan
           nomornya yang sudah tercetak tidak berubah. Kalau labelnya ikut
           hilang, riwayat dokumen berhenti bisa dibaca. */
        $id = $this->buatDepartemen(['Kode_Departemen' => 'UJIARS']);
        $this->svc()->softDelete($id, 1);

        $this->assertSame('UJIARS', $this->svc()->kode($id));
        $this->assertNull($this->svc()->cari($id), 'cari() bawaan hanya melihat yang aktif.');
    }

    public function test_peta_mengembalikan_kode_untuk_banyak_id_sekaligus(): void
    {
        $a = $this->buatDepartemen(['Kode_Departemen' => 'UJIPTA']);
        $b = $this->buatDepartemen(['Kode_Departemen' => 'UJIPTB']);

        $peta = $this->svc()->peta([$a, $b, null, 0]);

        $this->assertSame('UJIPTA', $peta[$a]->Kode_Departemen);
        $this->assertSame('UJIPTB', $peta[$b]->Kode_Departemen);
    }
}
