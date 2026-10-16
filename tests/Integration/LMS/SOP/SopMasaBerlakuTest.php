<?php

namespace Tests\Integration\LMS\SOP;

use App\Services\LMS\SOP\SopMasaBerlakuService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Baku masa berlaku adalah DATA, bukan konstanta.
 *
 * Uji ini menjaga dua hal sekaligus: angkanya benar-benar dibaca dari master
 * jenis dokumen, DAN dokumen yang tak punya jenis tetap mendapat baku. Yang
 * kedua bukan kemewahan — Jenis_Dokumen_Id boleh NULL, dan dokumen yang lahir
 * tanpa tanggal kadaluarsa akan lolos diam-diam dari seluruh mekanisme
 * peringatan yang dibangun di atas kolom itu.
 */
class SopMasaBerlakuTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopMasaBerlakuService
    {
        return app(SopMasaBerlakuService::class);
    }

    private function unik(): string
    {
        return substr(str_replace('.', '', (string) microtime(true)), -8);
    }

    private function jenis(?int $bulanBerlaku, ?int $bulanReview): int
    {
        return (int) DB::table('N_SOP_Master_Jenis_Dokumen')->insertGetId([
            'Kode_Jenis' => 'UJI'.$this->unik(),
            'Nama_Jenis' => 'Jenis Uji Masa Berlaku',
            'Level_Hirarki' => 9,
            'Urutan' => 999,
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Masa_Berlaku_Bulan' => $bulanBerlaku,
            'Siklus_Review_Bulan' => $bulanReview,
        ], 'Id_SOP_Jenis_Dokumen');
    }

    private function dokumen(?int $idJenis): int
    {
        return (int) DB::table('N_SOP_Dokumen')->insertGetId([
            'Judul' => 'SOP UJI MASA BERLAKU '.$this->unik(),
            'Tipe' => 'DOKUMEN',
            'Jenis_Dokumen_Id' => $idJenis,
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
        ], 'Id_SOP_Dokumen');
    }

    /* ── Aturan dibaca dari master ──────────────────────────────────────── */

    public function test_aturan_dibaca_dari_jenis_dokumen(): void
    {
        $id = $this->dokumen($this->jenis(36, 18));

        $this->assertSame(
            ['Masa_Berlaku_Bulan' => 36, 'Siklus_Review_Bulan' => 18],
            $this->svc()->aturanDokumen($id),
        );
    }

    public function test_dokumen_tanpa_jenis_tetap_dapat_baku(): void
    {
        $id = $this->dokumen(null);

        $this->assertSame(
            ['Masa_Berlaku_Bulan' => 24, 'Siklus_Review_Bulan' => 12],
            $this->svc()->aturanDokumen($id),
        );
    }

    public function test_jenis_tanpa_setelan_jatuh_ke_baku(): void
    {
        $id = $this->dokumen($this->jenis(null, null));

        $this->assertSame(
            ['Masa_Berlaku_Bulan' => 24, 'Siklus_Review_Bulan' => 12],
            $this->svc()->aturanDokumen($id),
        );
    }

    /**
     * Nol bukan kebijakan, itu baris master yang salah isi.
     *
     * Menghormatinya berarti melahirkan versi yang kadaluarsa pada hari ia
     * mulai berlaku.
     */
    public function test_nol_bulan_diperlakukan_seperti_kosong(): void
    {
        $id = $this->dokumen($this->jenis(0, 0));

        $this->assertSame(
            ['Masa_Berlaku_Bulan' => 24, 'Siklus_Review_Bulan' => 12],
            $this->svc()->aturanDokumen($id),
        );
    }

    /* ── Turunan ────────────────────────────────────────────────────────── */

    public function test_turunan_dihitung_dari_tanggal_berlaku(): void
    {
        $id = $this->dokumen($this->jenis(24, 12));

        $this->assertSame([
            'Tanggal_Kadaluarsa' => '2028-03-15',
            'Tanggal_Review_Berikutnya' => '2027-03-15',
        ], $this->svc()->turunanDari($id, '2026-03-15'));
    }

    /**
     * Ujung bulan dijepit, bukan diluberkan.
     *
     * 31 Jan + 1 bulan meluber ke 3 Mar pada perilaku baku PHP. Layar menjepit
     * dengan cara yang sama; kalau kedua sisi berbeda, tanggal yang ditawarkan
     * browser tak akan sama dengan yang disimpan server.
     */
    public function test_akhir_bulan_tidak_meluber(): void
    {
        $id = $this->dokumen($this->jenis(1, 1));

        $this->assertSame([
            'Tanggal_Kadaluarsa' => '2026-02-28',
            'Tanggal_Review_Berikutnya' => '2026-02-28',
        ], $this->svc()->turunanDari($id, '2026-01-31'));
    }

    /**
     * Tanpa tanggal berlaku, TIDAK ada turunan.
     *
     * Menghitungnya dari hari ini berarti mengarang fakta: tanggal berlakunya
     * memang belum diputuskan, dan kadaluarsa yang lahir dari tebakan akan
     * memicu notifikasi atas keputusan yang tak pernah diambil siapa pun.
     */
    public function test_tanpa_tanggal_berlaku_tidak_ada_turunan(): void
    {
        $id = $this->dokumen($this->jenis(24, 12));

        $this->assertSame([
            'Tanggal_Kadaluarsa' => null,
            'Tanggal_Review_Berikutnya' => null,
        ], $this->svc()->turunanDari($id, null));
    }

    /* ── Melengkapi muatan simpan ───────────────────────────────────────── */

    /** Isian admin TIDAK ditimpa baku — baku adalah usulan, bukan koreksi. */
    public function test_lengkapi_baku_menghormati_isian_yang_sudah_ada(): void
    {
        $id = $this->dokumen($this->jenis(24, 12));

        $hasil = $this->svc()->lengkapiBaku($id, [
            'Tanggal_Berlaku' => '2026-03-15',
            'Tanggal_Kadaluarsa' => '2030-01-01',
        ]);

        $this->assertSame('2030-01-01', $hasil['Tanggal_Kadaluarsa']);
        $this->assertSame('2027-03-15', $hasil['Tanggal_Review_Berikutnya']);
    }

    /** Tanpa tanggal berlaku, tak ada yang dilengkapi sama sekali. */
    public function test_lengkapi_baku_tak_mengarang_saat_tanggal_berlaku_kosong(): void
    {
        $id = $this->dokumen($this->jenis(24, 12));

        $hasil = $this->svc()->lengkapiBaku($id, ['Ringkasan_Perubahan' => 'Terbit awal']);

        $this->assertArrayNotHasKey('Tanggal_Kadaluarsa', $hasil);
        $this->assertArrayNotHasKey('Tanggal_Review_Berikutnya', $hasil);
    }
}
