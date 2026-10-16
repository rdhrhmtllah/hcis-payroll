<?php

namespace Tests\Integration\VISITOR\Permit\VehiclePermit;

use App\Http\Controllers\VISITOR\Permit\VehiclePermit\VerifikasiKartuPermitController;
use App\Services\VISITOR\Permit\VehiclePermit\KartuPermitQr;
use App\Services\VISITOR\Permit\VehiclePermit\VerifikasiKartuPermitService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Keaslian kartu permit yang dicetak.
 *
 * Yang menjaga kartu dari pemalsuan BUKAN gambar QR-nya — gambar bisa disalin
 * siapa pun. Penjaganya dua: kode acak 128 bit yang hanya ada di basis data
 * (DDL-2026-127), dan pemeriksaan yang membaca keadaan permit TERKINI dari
 * basis data, bukan dari kertas. Uji ini menagih keduanya.
 *
 * Tidak ada halaman publik: pemeriksaannya hanya dari HCIS yang sudah login.
 */
class VerifikasiKartuPermitTest extends TestCase
{
    use DatabaseTransactions;

    /** Karyawan NYATA di Web_HRIS development yang belum resign. */
    private const PEMOHON = 'B6';

    public function test_kode_kartu_acak_dan_tidak_memuat_nomor_permit(): void
    {
        $permit = $this->buatPermit('Y');
        $qr = app(KartuPermitQr::class);
        $kode = $qr->kode($permit['id']);

        // 32 heksadesimal = 16 byte = 128 bit. Nomor permit TIDAK boleh ikut
        // tercetak: kartu ini ditempel di dashboard dan terbaca siapa saja.
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/i', $kode);
        $this->assertStringNotContainsString((string) $permit['id'], $kode);
        $this->assertStringNotContainsString($permit['no_transaksi'], $kode);

        $this->assertSame($permit['id'], $qr->bacaId($kode));
        // Pemindai kadang memulangkan huruf besar.
        $this->assertSame($permit['id'], $qr->bacaId(strtoupper($kode)));
        // Kode tetap sama setiap dipanggil: kartu yang sudah tercetak tidak
        // boleh mati hanya karena PDF-nya dibuka ulang.
        $this->assertSame($kode, $qr->kode($permit['id']));
    }

    public function test_kode_kartu_tidak_pernah_sama_antar_permit(): void
    {
        $qr = app(KartuPermitQr::class);
        $a = $this->buatPermit('Y');
        $b = $this->buatPermit('Y');

        $this->assertNotSame($qr->kode($a['id']), $qr->kode($b['id']));
    }

    public function test_kode_karangan_dan_bentuk_lama_ditolak(): void
    {
        $qr = app(KartuPermitQr::class);

        $this->assertNull($qr->bacaId(str_repeat('a', 32)));
        $this->assertNull($qr->bacaId('8588'));
        $this->assertNull($qr->bacaId('HCIS-PASS-1.8588.abc'));
        $this->assertNull($qr->bacaId('halo'));
        $this->assertNull($qr->bacaId(''));
    }

    public function test_memutar_kode_mematikan_kartu_lama_saja(): void
    {
        // Gunanya ketika sebuah kode bocor: kartu itu saja yang mati, kartu
        // lain tidak ikut terbawa — beda dari tanda tangan berbasis APP_KEY
        // yang mematikan seluruh kartu sekaligus begitu kuncinya dirotasi.
        $qr = app(KartuPermitQr::class);
        $korban = $this->buatPermit('Y');
        $lain = $this->buatPermit('Y');

        $kodeLama = $qr->kode($korban['id']);
        $kodeLain = $qr->kode($lain['id']);
        $kodeBaru = $qr->putar($korban['id']);

        $this->assertNull($qr->bacaId($kodeLama));
        $this->assertSame($korban['id'], $qr->bacaId($kodeBaru));
        $this->assertSame($lain['id'], $qr->bacaId($kodeLain));
    }

    public function test_permit_aktif_terbaca_sah(): void
    {
        $permit = $this->buatPermit('Y');

        $hasil = $this->periksa(app(KartuPermitQr::class)->kode($permit['id']));

        $this->assertTrue($hasil['sah']);
        $this->assertSame('KARTU SAH', $hasil['judul']);
        $this->assertSame('AKTIF', $hasil['status']);
        $this->assertSame($permit['no_transaksi'], $hasil['no_transaksi']);
    }

    public function test_permit_yang_dicabut_terbaca_tidak_berlaku_beserta_sebabnya(): void
    {
        // Pencabutan menulis ke N_VISITOR_Permit_Efektif dan TIDAK menyentuh
        // Flag_Approval. Kalau urutan cabangnya terbalik, kartu yang sudah
        // dicabut terbaca "AKTIF" — kegagalan yang paling berbahaya di gerbang.
        $permit = $this->buatPermit('Y');
        DB::table('N_VISITOR_Permit_Efektif')->insert([
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $permit['no_transaksi'],
            // Kolom wajib proyeksi gerbang. Tanpa ini INSERT-nya ditolak basis
            // data, dan kegagalannya menyamar sebagai bug fitur.
            'Kode_Karyawan_Pemohon' => self::PEMOHON,
            'Nama_Pemohon' => 'Pemohon Uji',
            'Disetujui_Oleh_Nama' => 'Sistem Uji',
            'Disetujui_At' => now()->subDay(),
            'Riwayat_Approval' => '[]',
            'Dibuat_At' => now(),
            'Dibuat_Oleh_Nama' => 'Sistem Uji',
            'Tanggal_Mulai_Berlaku' => now()->subMonth()->toDateString(),
            'Tanggal_Kadaluarsa' => now()->addYear()->toDateString(),
            'Status_Efektif' => 'DIBATALKAN',
            'Flag_Batal' => 'Y',
            'Flag_Sync' => 'T',
            'Alasan_Status' => 'Kendaraan dijual.',
            'Dibatalkan_At' => now(),
            'Updated_At' => now(),
        ]);

        $hasil = $this->periksa(app(KartuPermitQr::class)->kode($permit['id']));

        $this->assertFalse($hasil['sah']);
        $this->assertSame('DICABUT', $hasil['status']);
        $this->assertStringContainsString('Kendaraan dijual.', $hasil['sebab']);
    }

    public function test_permit_yang_belum_disetujui_tidak_pernah_terbaca_sah(): void
    {
        $permit = $this->buatPermit(null);

        $hasil = $this->periksa(app(KartuPermitQr::class)->kode($permit['id']));

        $this->assertFalse($hasil['sah']);
        $this->assertSame('BELUM DISETUJUI', $hasil['status']);
    }

    public function test_permit_kedaluwarsa_terbaca_tidak_berlaku(): void
    {
        $permit = $this->buatPermit('Y', kadaluarsa: now()->subDay()->toDateString());

        $hasil = $this->periksa(app(KartuPermitQr::class)->kode($permit['id']));

        $this->assertFalse($hasil['sah']);
        $this->assertSame('KEDALUWARSA', $hasil['status']);
    }

    public function test_kartu_pemegang_yang_resign_tidak_berlaku_sesudah_hari_terakhirnya(): void
    {
        $permit = $this->buatPermit('Y');
        $token = app(KartuPermitQr::class)->kode($permit['id']);

        // Hari kerja terakhir: kartunya masih sah sampai akhir hari.
        DB::table('Karyawan')->where('Kode_Karyawan', self::PEMOHON)->update(['Tanggal_Resign' => now()->startOfDay()]);
        $this->assertTrue($this->periksa($token)['sah']);

        DB::table('Karyawan')->where('Kode_Karyawan', self::PEMOHON)->update(['Tanggal_Resign' => now()->subDay()->startOfDay()]);
        $hasil = $this->periksa($token);
        $this->assertFalse($hasil['sah']);
        $this->assertSame('KEDALUWARSA', $hasil['status']);
        $this->assertStringContainsString('resign', $hasil['sebab']);

        // Publik tidak perlu tahu status kepegawaian pemegangnya.
        $publik = app(VerifikasiKartuPermitService::class)->keadaanPublik($token);
        $this->assertFalse($publik['sah']);
        $this->assertSame('KEDALUWARSA', $publik['status']);
        $this->assertStringNotContainsString('resign', $publik['sebab']);
    }

    public function test_token_karangan_disebut_tidak_dikenali_tanpa_menyentuh_data(): void
    {
        $hasil = $this->periksa(str_repeat('f', 32));

        $this->assertFalse($hasil['sah']);
        $this->assertSame('KARTU TIDAK DIKENALI', $hasil['judul']);
        // Tidak boleh membocorkan apa pun tentang permit bernomor itu.
        $this->assertArrayNotHasKey('plat', $hasil);
        $this->assertArrayNotHasKey('pemegang', $hasil);
    }

    public function test_pintu_karyawan_hanya_mengakui_kartunya_sendiri(): void
    {
        // Kunci halaman pintu ini self-service dan dipegang semua karyawan,
        // jadi kepemilikan adalah satu-satunya yang menahan. Tanpa penjaga ini,
        // siapa pun bisa memindai kartu orang lain dan membaca identitas
        // pemegangnya dari layar HCIS-nya sendiri.
        $permit = $this->buatPermit('Y');
        $token = app(KartuPermitQr::class)->kode($permit['id']);

        $milikSendiri = $this->periksaSebagaiKaryawan($token, self::PEMOHON);
        $this->assertTrue($milikSendiri['sah'], 'Pemegang kartunya sendiri justru ditolak.');

        $orangLain = $this->periksaSebagaiKaryawan($token, 'ZZBUKANDIA');
        $this->assertFalse($orangLain['sah']);
        $this->assertSame('BUKAN KARTU ANDA', $orangLain['judul']);

        // Tidak satu pun identitas boleh ikut terbawa pada penolakan itu.
        foreach (['plat', 'pemegang', 'no_transaksi', 'status'] as $bocor) {
            $this->assertArrayNotHasKey($bocor, $orangLain, "Field '{$bocor}' bocor ke bukan pemiliknya.");
        }
    }

    /**
     * Pintu ADMIN: boleh memeriksa kartu siapa pun.
     *
     * Cabang status (sah / dicabut / belum disetujui / kedaluwarsa) diuji dari
     * sini supaya yang ditagih memang cabangnya, bukan kepemilikan. Pintu
     * karyawan punya ujinya sendiri di atas.
     *
     * @return array<string, mixed>
     */
    private function periksa(string $token): array
    {
        $balasan = app(VerifikasiKartuPermitController::class)->admin(
            Request::create('/laporanvisitor-permit-verifikasi-kartu', 'POST', ['token' => $token])
        );

        return json_decode($balasan->getContent(), true)['result'];
    }

    /**
     * Pintu KARYAWAN: hanya kartunya sendiri.
     *
     * @return array<string, mixed>
     */
    private function periksaSebagaiKaryawan(string $token, string $kodeKaryawan): array
    {
        $request = Request::create('/transaksipermit-verifikasi-kartu', 'POST', ['token' => $token]);
        $request->setUserResolver(fn () => (object) ['Kode_Karyawan' => $kodeKaryawan]);

        $balasan = app(VerifikasiKartuPermitController::class)($request);

        return json_decode($balasan->getContent(), true)['result'];
    }

    /** @return array{id: int, no_transaksi: string} */
    private function buatPermit(?string $flagApproval, ?string $kadaluarsa = null): array
    {
        $nomor = 'UJI-QR-'.Str::upper(Str::random(8));

        $id = (int) DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => self::PEMOHON,
            'Flag_Approval' => $flagApproval,
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => 'UJI',
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'Id_Transaksi_Permit' => $id,
            'No_Transaksi' => $nomor,
            'Snapshot_Kategori_Permit' => 'Permit Hijau',
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(1000, 9999),
            'Plat_Nomor_Suffix' => 'QR',
            'Tanggal_Mulai_Berlaku' => now()->subMonth()->toDateString(),
            'Tanggal_Kadaluarsa' => $kadaluarsa ?? now()->addYear()->toDateString(),
        ]);

        return ['id' => $id, 'no_transaksi' => $nomor];
    }
}
