<?php

namespace Tests\Integration\VISITOR\Workflow\Reporting;

use App\Http\Controllers\VISITOR\Workflow\Reporting\LaporanVisitorController;
use App\Models\ESS\Employee\Models\User;
use App\Services\VISITOR\Workflow\Reporting\Monitoring\VisitorPapanHarianService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\VISITOR\MelihatSeluruhKategoriTamu;
use Tests\TestCase;

/**
 * Monitoring Permit harus menyebut PEMEGANG izin, bukan pemohonnya.
 *
 * Tiga jenis izin kini hidup berdampingan — kendaraan karyawan, turunan
 * kunjungan tamu, dan pengajuan aplikasi mitra — dan dua di antaranya diajukan
 * ATAS NAMA orang yang tidak akan lewat gerbang. Papan yang menampilkan nama
 * pemohon membuat satpam mencari orang yang memang tidak datang, dan tidak ada
 * galat apa pun yang menandainya.
 */
class MonitoringPermitDimensiBaruTest extends TestCase
{
    use DatabaseTransactions;
    use MelihatSeluruhKategoriTamu;

    /** Kode karyawan NYATA di Web_HRIS development. */
    private const PIC = 'A1';

    public function test_papan_menyebut_pemegang_izin_bukan_pemohonnya(): void
    {
        $permit = $this->buatPermit(tamu: true, namaTamu: 'Bagas Pengunjung');

        $baris = $this->cariDiPapan($permit);

        $this->assertNotNull($baris, 'Izin yang berlaku tidak muncul di papan harian.');
        $this->assertSame('TAMU', $baris->Jenis_Permit);
        $this->assertSame('Bagas Pengunjung', $baris->Nama_Pemegang);
        $this->assertSame('PT Uji Papan', $baris->Instansi_Pemegang);
    }

    public function test_izin_karyawan_tetap_menyebut_karyawannya(): void
    {
        // Penjaga yang terlalu bersemangat akan mengosongkan nama pada izin
        // karyawan biasa — dan itu tidak akan terlihat oleh uji yang hanya
        // memeriksa izin tamu.
        $permit = $this->buatPermit(tamu: false);

        $baris = $this->cariDiPapan($permit);

        $this->assertSame('KARYAWAN', $baris->Jenis_Permit);
        $this->assertNotSame('-', $baris->Nama_Pemegang);
        $this->assertNull($baris->Instansi_Pemegang);
        $this->assertNull($baris->Tautan_Tamu);
    }

    public function test_papan_menautkan_dua_arah_tanpa_lewat_url_gabungan(): void
    {
        $permit = $this->buatPermit(tamu: true);

        $baris = $this->cariDiPapan($permit);

        $this->assertStringStartsWith('/verifikator-visitor/permit?detail=', $baris->Tautan_Detail);
        // Arah balik: dari izin bermasalah langsung ke kunjungan asalnya.
        // DDL-2026-121: permit coklat hanya milik tamu B2B, jadi tautannya ke
        // meja B2B — meja Regular kini membalas 404 untuk baris B2B.
        $this->assertStringStartsWith('/verifikator-visitor/b2b?detail=', (string) $baris->Tautan_Tamu);
    }

    public function test_jendela_jam_ikut_terbaca(): void
    {
        // Dua izin yang sama-sama "kedaluwarsa hari ini" bisa berarti pukul
        // delapan pagi dan tengah malam. Tanpa jamnya, papan tidak bisa
        // membedakan yang sudah mati dari yang masih setengah hari lagi.
        $permit = $this->buatPermit(tamu: true);

        $baris = $this->cariDiPapan($permit);

        $this->assertNotNull($baris->Waktu_Berakhir);
        $this->assertSame('17:00', substr((string) $baris->Waktu_Berakhir, 11, 5));
    }

    public function test_penyaring_asal_membelah_himpunan_dengan_utuh(): void
    {
        $this->buatPermit(tamu: true);
        $this->buatPermit(tamu: false);

        $semua = count($this->laporan([]));
        $karyawan = count($this->laporan(['asal' => 'KARYAWAN']));
        $tamu = count($this->laporan(['asal' => 'TAMU']));
        $mitra = count($this->laporan(['asal' => 'MITRA']));

        $this->assertGreaterThan(0, $karyawan);
        $this->assertGreaterThan(0, $tamu);

        // Izin turunan kunjungan MITRA membawa Sumber_Pengajuan 'API' juga.
        // Tanpa pengecualian di penyaring MITRA ia terhitung dua kali, dan
        // jumlah ketiganya melampaui totalnya — penyaring yang tetap terlihat
        // "bekerja" karena masing-masing memang memulangkan lebih sedikit baris.
        $this->assertSame(
            $semua,
            $karyawan + $tamu + $mitra,
            'Penyaring asal izin tumpang tindih; satu izin terhitung di dua golongan.',
        );
    }

    public function test_daftar_membawa_pemegang_dan_asal_izinnya(): void
    {
        $permit = $this->buatPermit(tamu: true, namaTamu: 'Bagas Pengunjung');

        $baris = collect($this->laporan([]))->firstWhere('No_Transaksi', $permit);

        $this->assertNotNull($baris, 'Izin tidak muncul di daftar lengkap.');
        $this->assertSame('TAMU', $baris['Jenis_Asal_Permit']);
        $this->assertSame('Bagas Pengunjung', $baris['Nama_Pemegang']);
        // Pemohonnya TIDAK dihapus: ia PIC yang bertanggung jawab, dan admin
        // sering perlu menghubunginya justru ketika izinnya bermasalah.
        $this->assertNotEmpty($baris['Nama_Karyawan']);
    }

    private function cariDiPapan(string $noPermit): ?object
    {
        $papan = app(VisitorPapanHarianService::class)->rakit('permit');
        $semua = array_merge(
            $papan['permit_kedaluwarsa_hari_ini'] ?? [],
            $papan['permit_segera_kedaluwarsa'] ?? [],
        );

        return collect($semua)->firstWhere('No_Transaksi', $noPermit);
    }

    /** @return array<int,array<string,mixed>> */
    private function laporan(array $penyaring): array
    {
        $user = User::find(DB::table('Karyawan')->where('Kode_Karyawan', self::PIC)->value('UserID_Web'));
        $this->assertNotNull($user, 'Karyawan '.self::PIC.' tidak punya baris login.');

        $request = Request::create('/laporanvisitor-permit', 'GET', $penyaring + ['limit' => 200]);
        // Laporan tamu disaring MATRIKS sejak halaman Jadwal dilebur ke sini;
        // pengguna tanpa hak kategori memulangkan daftar kosong.
        $this->beriHakLihatSeluruhKategoriTamu($user?->Id_Users !== null ? (int) $user->Id_Users : null);
        $request->setUserResolver(fn () => $user);

        $isi = json_decode(
            app(LaporanVisitorController::class)->getLaporanPermit($request)->getContent(),
            true,
        );

        return $isi['result'] ?? [];
    }

    private function buatPermit(bool $tamu, string $namaTamu = 'Tamu Papan'): string
    {
        $nomor = 'PMT-MPM-'.Str::upper(Str::random(8));
        $noTamu = null;

        if ($tamu) {
            $noTamu = 'VSR-MPM-'.Str::upper(Str::random(8));
            $idTamu = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
                'No_Transaksi' => $noTamu,
                'Kode_Karyawan_PIC' => self::PIC,
                'Status_Kunjungan' => 'SCHEDULED',
                'Flag_Approval' => 'Y',
                'Tanggal_Kunjungan' => now()->toDateString(),
                'Flag_Aktif' => 'Y',
                'Created_At' => now(),
                'Created_By' => self::PIC,
                'Idempotency_Key' => 'MPM-'.Str::upper(Str::random(20)),
            ]);

            DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
                'Id_Transaksi_Tamu' => $idTamu,
                'No_Transaksi' => $noTamu,
                'Nama_Lengkap_Tamu' => $namaTamu,
                'Tipe_Tamu' => 'B2B',
                'Jumlah_Tamu' => 1,
            ]);
        }

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            'No_Transaksi_Tamu' => $noTamu,
            'Kode_Karyawan_Pemohon' => self::PIC,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => 'Y',
            'Sumber_Pengajuan' => $tamu ? 'API' : 'HCIS',
            'Approved_At' => now(),
            'Approved_By' => self::PIC,
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'MPM-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'Id_Transaksi_Permit' => $id,
            'No_Transaksi' => $nomor,
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(10000, 99999),
            'Plat_Nomor_Suffix' => 'MP',
            'Nama_Tamu' => $tamu ? $namaTamu : null,
            'Instansi_Tamu' => $tamu ? 'PT Uji Papan' : null,
            // Kedaluwarsa hari ini pukul 17:00 supaya masuk kelompok papan.
            'Tanggal_Mulai_Berlaku' => now()->toDateString(),
            'Tanggal_Kadaluarsa' => now()->toDateString(),
            'Waktu_Mulai_Berlaku' => now()->startOfDay(),
            'Waktu_Berakhir' => now()->setTime(17, 0),
        ]);

        return $nomor;
    }
}
