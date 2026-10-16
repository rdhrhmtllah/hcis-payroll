<?php

namespace Tests\Integration\VISITOR\Workflow\Reporting;

use App\Services\VISITOR\Workflow\Reporting\Dashboard\VisitorDashboardAnalitikService;
use App\Services\VISITOR\Workflow\Reporting\Export\VisitorPermitDataset;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Permit coklat tidak boleh menenggelamkan angka permit karyawan.
 *
 * Satu tabel kini memuat dua populasi yang besarnya jauh berbeda: permit
 * kendaraan karyawan (24 transaksi di development) dan permit akses tamu (satu
 * per tamu — 83 registrasi di development, dan bertambah setiap hari). Setiap
 * hitungan yang tidak membedakan keduanya akan terlihat meledak padahal tidak
 * ada yang berubah.
 *
 * Perbandingannya RELATIF (selisih sebelum dan sesudah), bukan angka mutlak:
 * database development berisi data yang berubah-ubah, dan penjaga yang
 * menegaskan angka tetap akan merah karena alasan yang salah.
 */
class PermitCoklatTerpisahDiLaporanTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Kode karyawan NYATA di Web_HRIS development.
     *
     * Kode karangan tidak bisa dipakai: syncTamuEfektif() menulis Nama_PIC yang
     * NOT NULL di N_VISITOR_Tamu_Efektif, dan namanya di-resolve lewat JOIN ke
     * tabel Karyawan. PIC yang tidak ada membuat seluruh registrasi gagal 500 —
     * kegagalan yang menyamar sebagai bug fitur, padahal fixture-nya yang salah.
     */
    private const PIC = 'A1';

    public function test_permit_coklat_tidak_menambah_hitungan_kategori_karyawan(): void
    {
        $sebelum = $this->jumlahPerSumber();

        $this->buatPermitCoklatMentah();

        $sesudah = $this->jumlahPerSumber();

        $this->assertSame(
            $sebelum['KARYAWAN'],
            $sesudah['KARYAWAN'],
            'Permit coklat ikut terhitung sebagai permit karyawan.',
        );
        $this->assertSame(
            $sebelum['TAMU'] + 1,
            $sesudah['TAMU'],
            'Permit coklat tidak terhitung sebagai permit tamu.',
        );
    }

    public function test_setiap_baris_kategori_membawa_sumbernya(): void
    {
        // Tanpa kunci 'sumber', grafik "per kategori" hanya menampilkan nama
        // kategori dan pembaca tidak punya cara membedakan dua populasi itu.
        $this->buatPermitCoklatMentah();

        foreach ($this->kategori() as $baris) {
            $this->assertArrayHasKey('sumber', $baris);
            $this->assertContains($baris['sumber'], ['KARYAWAN', 'TAMU']);
        }
    }

    public function test_tidak_ada_baris_kategori_bersumber_null(): void
    {
        // Sumber_Permit nullable. Tanpa ISNULL di GROUP BY, baris yang belum
        // ter-backfill mengelompok sendiri sebagai NULL — satu kategori hantu
        // yang tidak berarti apa-apa bagi pembacanya.
        $this->buatPermitKaryawanTanpaSumber();

        $sumber = array_column($this->kategori(), 'sumber');

        $this->assertNotContains('', $sumber);
        $this->assertNotContains(null, $sumber);
    }

    public function test_export_studio_menyediakan_dimensi_dan_saringan_sumber(): void
    {
        $dataset = app(VisitorPermitDataset::class);

        $dimensi = array_map(fn ($d) => $d->key, $dataset->dimensi());
        $saringan = array_map(fn ($s) => $s->key, $dataset->saringan());

        $this->assertContains('sumber_permit', $dimensi, 'Laporan permit tidak bisa memisah karyawan dari tamu.');
        $this->assertContains('sumber_permit', $saringan);
        $this->assertContains('waktu_mulai_berlaku', $dimensi);
        $this->assertContains('waktu_berakhir', $dimensi);
    }

    /**
     * Opsi saringan sumber memuat TEPAT asal yang benar-benar ada.
     *
     * Dulu hanya dua: permit karyawan dan permit tamu. 'API' menyusul ketika
     * mitra mulai mengajukan permit lewat endpoint B2B — asal yang berbeda
     * wewenang maupun penanganannya, dan admin permit perlu memisahkannya.
     *
     * Yang dijaga dua arah: tidak ada asal nyata yang hilang dari dropdown
     * (pemakainya tak punya cara menyaringnya), dan tidak ada nilai karangan
     * yang ditawarkan (menyaringnya selalu menghasilkan nol baris tanpa
     * sebab yang terlihat).
     */
    public function test_opsi_saringan_sumber_tepat_asal_yang_ada(): void
    {
        $opsi = app(VisitorPermitDataset::class)->opsiSaringan();

        $this->assertSame(
            ['KARYAWAN', 'TAMU', 'API'],
            array_column($opsi['sumber_permit'], 'value'),
        );
    }

    /** @return array<int, array{kategori:string, sumber:string, jumlah:int}> */
    private function kategori(): array
    {
        return app(VisitorDashboardAnalitikService::class)->permitPerKategori();
    }

    /** @return array{KARYAWAN:int, TAMU:int} */
    private function jumlahPerSumber(): array
    {
        $hasil = ['KARYAWAN' => 0, 'TAMU' => 0];

        foreach ($this->kategori() as $baris) {
            $hasil[$baris['sumber']] = ($hasil[$baris['sumber']] ?? 0) + $baris['jumlah'];
        }

        return $hasil;
    }

    private function buatPermitCoklatMentah(): string
    {
        return $this->sisipkanPermit('VSR-LAP-', 'COKLAT', 'TAMU');
    }

    private function buatPermitKaryawanTanpaSumber(): string
    {
        return $this->sisipkanPermit('PMT-LAP-', 'HIJAU', null);
    }

    private function sisipkanPermit(string $prefiks, string $kategori, ?string $sumber): string
    {
        $nomor = $prefiks.Str::upper(Str::random(8));

        DB::table('N_VISITOR_Permit_Efektif')->insert([
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => self::PIC,
            'Nama_Pemohon' => 'PIC Uji',
            'Nama_Kategori_Permit' => $kategori,
            'Sumber_Permit' => $sumber,
            'Status_Efektif' => 'AKTIF',
            'Waktu_Mulai_Berlaku' => $sumber === 'TAMU' ? now() : null,
            'Waktu_Berakhir' => $sumber === 'TAMU' ? now()->addHours(4) : null,
            'Tanggal_Mulai_Berlaku' => now()->toDateString(),
            'Tanggal_Kadaluarsa' => now()->addDay()->toDateString(),
            'Disetujui_Oleh_Nama' => 'Sistem Uji',
            'Disetujui_At' => now(),
            'Riwayat_Approval' => '[]',
            'Dibuat_At' => now(),
            'Dibuat_Oleh_Nama' => 'Sistem Uji',
            'Updated_At' => now(),
            'Flag_Sync' => 'T',
        ]);

        return $nomor;
    }
}
