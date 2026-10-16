<?php

namespace Tests\Integration\VISITOR\Operations;

use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\VISITOR\MenerbitkanPermitCoklat;
use Tests\TestCase;

/**
 * Permit coklat lahir sebagai PENGAJUAN, lalu jadi proyeksi setelah disetujui.
 *
 * Rancangan sebelumnya menulisnya langsung ke proyeksi gerbang sebagai baris
 * AKTIF: izin akses fisik terbit tanpa seorang pun menyetujuinya. Sekarang ia
 * transaksi permit yang sesungguhnya — dan itulah hal pertama yang diuji di
 * sini, karena kegagalannya tidak menyalakan galat apa pun: yang terjadi hanya
 * izin gerbang yang hidup lebih cepat dari yang seharusnya.
 *
 * Yang lain adalah kontrak yang dibaca aplikasi gate pihak ketiga. Dua di
 * antaranya juga diam kalau salah:
 *
 *  - Kolom `date` lama WAJIB ikut terisi turunan dari kolom waktu. Gate yang
 *    belum diperbarui hanya membaca kolom `date`; kalau kosong, permit coklat
 *    tidak terlihat sama sekali di gerbang.
 *  - Tamu pejalan kaki tetap dapat permit. Plat kosong bukan alasan melewatkan
 *    penerbitan — 29 dari 83 baris tamu di development tidak berplat padahal
 *    hanya 3 yang menyatakan tidak berkendara.
 */
class PermitCoklatTerbitTest extends TestCase
{
    use DatabaseTransactions;
    use MenerbitkanPermitCoklat;

    /**
     * Kode karyawan NYATA di Web_HRIS development.
     *
     * Kode karangan tidak bisa dipakai: syncTamuEfektif() menulis Nama_PIC yang
     * NOT NULL di N_VISITOR_Tamu_Efektif, dan namanya di-resolve lewat JOIN ke
     * tabel Karyawan. PIC yang tidak ada membuat seluruh registrasi gagal 500 —
     * kegagalan yang menyamar sebagai bug fitur, padahal fixture-nya yang salah.
     */
    private const PIC = 'A1';

    public function test_permit_belum_hidup_di_gerbang_sebelum_disetujui(): void
    {
        // Penjaga utama change ini. Kalau merah, izin gerbang kembali terbit
        // tanpa persetujuan siapa pun.
        $nomor = $this->buatTamu('2026-09-10 08:00:00', '2026-09-10 17:00:00');

        $noPermit = $this->ajukanPermitCoklat($nomor);

        $this->assertNotNull($noPermit, 'Permit COKLAT tidak diajukan sama sekali.');
        $this->assertStringStartsWith('PMT', $noPermit, 'Permit COKLAT harus punya nomor transaksi permitnya sendiri.');

        $pengajuan = DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $noPermit)->first();

        $this->assertNotNull($pengajuan);
        $this->assertNull($pengajuan->Flag_Approval, 'Pengajuan permit COKLAT harus menunggu keputusan.');
        $this->assertSame($nomor, $pengajuan->No_Transaksi_Tamu);

        $this->assertSame(
            0,
            DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $noPermit)->count(),
            'Permit yang belum disetujui sudah terbaca di gerbang.',
        );
    }

    public function test_alur_approval_permit_ikut_terbentuk(): void
    {
        // Pengajuan tanpa alur tidak akan pernah muncul di meja siapa pun; ia
        // menggantung sebagai izin yang tidak pernah terbit, tanpa galat.
        $nomor = $this->buatTamu('2026-09-10 08:00:00', '2026-09-10 17:00:00');
        $noPermit = $this->ajukanPermitCoklat($nomor);

        $this->assertGreaterThan(
            0,
            DB::table('HRIS_Approval_Request')->where('No_Transaksi', $noPermit)->count(),
            'Permit COKLAT terbit tanpa alur persetujuan.',
        );
    }

    public function test_permit_coklat_b2b_terbit_mengikuti_rentang_estimasi(): void
    {
        // DDL-2026-121: jendela permit = rentang Estimasi Masuk–Keluar, bukan
        // lagi satu hari kalender penuh dari tanggal kunjungan.
        $nomor = $this->buatTamu('2026-09-10 08:00:00', '2026-09-10 17:00:00');

        $baris = $this->permit($this->terbitkanPermitCoklat($nomor));

        $this->assertSame('AKTIF', $baris->Status_Efektif);
        $this->assertSame('TAMU', $baris->Sumber_Permit);
        $this->assertSame('COKLAT', $baris->Nama_Kategori_Permit);
        $this->assertSame('1 Hari', $baris->Nama_Durasi);
        $this->assertSame('Tamu Coklat', $baris->Nama_Pemegang);
        $this->assertSame('PT Uji', $baris->Instansi_Pemegang);
        $this->assertSame('2026-09-10 08:00:00', substr((string) $baris->Waktu_Mulai_Berlaku, 0, 19));
        // Sejak 29 Sep 2026 berakhir 23:59 di hari terakhir kunjungan, bukan di
        // jam estimasi keluarnya.
        $this->assertSame('2026-09-10 23:59:00', substr((string) $baris->Waktu_Berakhir, 0, 19));
    }

    public function test_permit_coklat_mitra_berhari_hari_berlaku_selama_rentangnya(): void
    {
        // Permit sehari membuat gerbang menolak tamu MITRA mulai hari kedua.
        $nomor = $this->buatTamu('2026-09-10 08:00:00', '2026-09-12 17:00:00');

        $baris = $this->permit($this->terbitkanPermitCoklat($nomor));

        $this->assertSame('2026-09-10 08:00:00', substr((string) $baris->Waktu_Mulai_Berlaku, 0, 19));
        $this->assertSame('2026-09-12 23:59:00', substr((string) $baris->Waktu_Berakhir, 0, 19));
        $this->assertSame('2026-09-10', substr((string) $baris->Tanggal_Mulai_Berlaku, 0, 10));
        $this->assertSame('2026-09-12', substr((string) $baris->Tanggal_Kadaluarsa, 0, 10));
        // Tiga tanggal kalender (10–12).
        $this->assertSame('3 Hari', $baris->Nama_Durasi);
    }

    public function test_jenis_kendaraan_tamu_ikut_sampai_ke_gerbang(): void
    {
        // Kendaraan tamu tidak berasal dari master model, jadi ia menumpang
        // kolom teks di detail permit. Hilangnya tidak menyalakan galat: satpam
        // hanya kehilangan satu-satunya petunjuk kendaraan apa yang datang.
        $nomor = $this->buatTamu('2026-09-10 08:00:00', '2026-09-10 17:00:00');

        $this->assertSame('Mobil', $this->permit($this->terbitkanPermitCoklat($nomor))->Nama_Vehicle_Type);
    }

    public function test_kolom_tanggal_lama_mengikuti_ujung_rentang_estimasi(): void
    {
        // DDL-2026-121: kunjungan lintas tengah malam kini diizinkan sampai
        // Estimasi Keluar, jadi kolom tanggal inklusif lama menunjuk hari
        // keluarnya — dulu keduanya tanggal kunjungan.
        $nomor = $this->buatTamu('2026-09-10 20:00:00', '2026-09-11 06:00:00');

        $baris = $this->permit($this->terbitkanPermitCoklat($nomor));

        $this->assertSame('2026-09-10', substr((string) $baris->Tanggal_Mulai_Berlaku, 0, 10));
        $this->assertSame('2026-09-11', substr((string) $baris->Tanggal_Kadaluarsa, 0, 10));
        // Berlaku sampai 11/09 23:59 — dua tanggal kalender.
        $this->assertSame('2 Hari', $baris->Nama_Durasi);
    }

    public function test_tamu_pejalan_kaki_tetap_dapat_permit(): void
    {
        $nomor = $this->buatTamu('2026-09-10 08:00:00', '2026-09-10 12:00:00', berplat: false);

        $baris = $this->permit($this->terbitkanPermitCoklat($nomor));

        $this->assertSame('AKTIF', $baris->Status_Efektif);
        $this->assertSame('', trim((string) $baris->No_Plat_Kendaraan));
    }

    public function test_label_durasi_mengikuti_panjang_rentang(): void
    {
        // Dulu selalu '1 Hari'. Sejak DDL-2026-121 label mengikuti rentang
        // estimasi: di bawah atau tepat 24 jam tetap '1 Hari', lebih dari itu
        // dibulatkan ke atas per hari.
        $sehari = $this->buatTamu('2026-09-10 08:00:00', '2026-09-10 14:30:00');
        $tepatDuaHari = $this->buatTamu('2026-09-10 08:00:00', '2026-09-12 08:00:00');

        $this->assertSame('1 Hari', $this->permit($this->terbitkanPermitCoklat($sehari))->Nama_Durasi);
        // 10/09 08:00 s.d. 12/09 23:59 (usulan sejak 29 Sep 2026): tiga tanggal kalender.
        $this->assertSame('3 Hari', $this->permit($this->terbitkanPermitCoklat($tepatDuaHari))->Nama_Durasi);
    }

    public function test_pemanggilan_ulang_tidak_menggandakan_pengajuan(): void
    {
        $nomor = $this->buatTamu('2026-09-10 08:00:00', '2026-09-10 17:00:00');

        $pertama = $this->ajukanPermitCoklat($nomor);
        $kedua = $this->ajukanPermitCoklat($nomor);

        $this->assertSame($pertama, $kedua);
        $this->assertSame(
            1,
            DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi_Tamu', $nomor)->count(),
        );
    }

    public function test_tamu_b2b_tanpa_jam_tetap_menerbitkan_permit_dari_tanggal(): void
    {
        // Kunjungan lama tanpa estimasi: fallback satu hari kalender penuh.
        $nomor = $this->buatTamu(null, null);

        $baris = $this->permit($this->terbitkanPermitCoklat($nomor));

        $this->assertSame('1 Hari', $baris->Nama_Durasi);
        $this->assertSame('2026-09-10 00:00:00', substr((string) $baris->Waktu_Mulai_Berlaku, 0, 19));
        $this->assertSame('2026-09-11 00:00:00', substr((string) $baris->Waktu_Berakhir, 0, 19));
        // Batas akhir eksklusif 00:00 → tanggal inklusifnya tetap hari itu.
        $this->assertSame('2026-09-10', substr((string) $baris->Tanggal_Kadaluarsa, 0, 10));
    }

    public function test_tamu_reguler_tidak_mengajukan_permit(): void
    {
        $nomor = $this->buatTamu('2026-09-10 08:00:00', '2026-09-10 17:00:00', tipe: 'REGULAR');

        $this->assertNull($this->ajukanPermitCoklat($nomor));
    }

    public function test_kunjungan_yang_belum_disetujui_tidak_mengajukan_permit(): void
    {
        // Mengajukan izin gerbang untuk kunjungan yang masih menunggu approval
        // berarti verifikator diminta menyetujui akses bagi tamu yang belum
        // tentu jadi datang.
        $nomor = $this->buatTamu('2026-09-10 08:00:00', '2026-09-10 17:00:00', disetujui: false);

        $this->assertNull($this->ajukanPermitCoklat($nomor));
    }

    public function test_permit_tamu_tidak_memakan_kuota_permit_pic(): void
    {
        // PIC mengajukan atas nama tamunya, bukan untuk dirinya sendiri. Tanpa
        // pengecualian ini, karyawan yang mendaftarkan dua tamu membaca
        // "2/2 terpakai" pada permit kendaraannya sendiri.
        $sebelum = app(\App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQuotaService::class)
            ->terpakai(self::PIC);

        $this->ajukanPermitCoklat($this->buatTamu('2026-09-10 08:00:00', '2026-09-10 17:00:00'));

        $this->assertSame(
            $sebelum,
            app(\App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQuotaService::class)->terpakai(self::PIC),
        );
    }

    public function test_pencabutan_memakai_metode_permit_yang_sudah_ada(): void
    {
        // Permit coklat kini bernomor permitnya sendiri, jadi revokePermitEfektif()
        // bekerja apa adanya dengan nomor itu. Kalau ini merah, ada varian revoke
        // tersendiri yang tidak seharusnya ada — dan pintu-pintu pencabutan akan
        // meleset.
        $nomor = $this->buatTamu('2026-09-10 08:00:00', '2026-09-10 17:00:00');
        $noPermit = $this->terbitkanPermitCoklat($nomor);

        $terpengaruh = app(VisitorEfektifService::class)
            ->revokePermitEfektif($noPermit, 'Kunjungan dibatalkan', self::PIC);

        $this->assertSame(1, $terpengaruh);
        $this->assertSame('DIBATALKAN', $this->permit($noPermit)->Status_Efektif);
    }

    public function test_permit_karyawan_ditandai_sumber_karyawan(): void
    {
        // Pasangan Sumber_Permit='TAMU'. Tanpa ini setiap laporan yang memisah
        // permit karyawan dari permit tamu bergantung pada backfill sekali jalan.
        $ini = DB::table('N_VISITOR_Permit_Efektif')
            ->whereNull('Sumber_Permit')
            ->where('Status_Efektif', 'AKTIF')
            ->count();

        $this->assertSame(0, $ini, 'Masih ada baris permit tanpa Sumber_Permit; backfill DML belum jalan.');
    }

    private function permit(string $noPermit): object
    {
        $baris = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $noPermit)->first();
        $this->assertNotNull($baris, "Permit coklat {$noPermit} tidak terbit.");

        return $baris;
    }

    private function buatTamu(
        ?string $mulai,
        ?string $selesai,
        bool $berplat = true,
        string $tipe = 'B2B',
        bool $disetujui = true,
    ): string {
        $nomor = 'VSR-CKL-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => $disetujui ? StatusKunjunganTamu::SCHEDULED : StatusKunjunganTamu::PENDING_APPROVAL,
            'Flag_Approval' => $disetujui ? 'Y' : null,
            'Tanggal_Kunjungan' => '2026-09-10',
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'CKL-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Tamu Coklat',
            'Tipe_Tamu' => $tipe,
            'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Meeting',
            'Jumlah_Tamu' => 1,
            'Jenis_Kendaraan' => $berplat ? 'Mobil' : 'Tidak Berkendara',
            'Plat_Nomor_Prefix' => $berplat ? 'BG' : null,
            'Plat_Nomor_Angka' => $berplat ? (string) random_int(10000, 99999) : null,
            'Plat_Nomor_Suffix' => $berplat ? 'CK' : null,
            'Waktu_Mulai_Kunjungan' => $mulai,
            'Waktu_Selesai_Kunjungan' => $selesai,
        ]);

        return $nomor;
    }
}
