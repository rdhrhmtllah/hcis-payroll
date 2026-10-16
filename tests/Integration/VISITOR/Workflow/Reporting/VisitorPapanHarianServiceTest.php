<?php

namespace Tests\Integration\VISITOR\Workflow\Reporting;

use App\Services\VISITOR\Workflow\Reporting\Monitoring\VisitorPapanHarianService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pengelompokan papan harian.
 *
 * Tiap kelompok diuji DUA sisi: satu baris yang seharusnya masuk dan satu yang
 * seharusnya tidak. Pengelompokan yang keliru dan mengembalikan semua baris
 * akan lolos mulus bila hanya diuji dari sisi "ada isinya".
 */
class VisitorPapanHarianServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const PIC = 'ZZPAPAN1';

    private function papan(): array
    {
        return app(VisitorPapanHarianService::class)->rakit();
    }

    /**
     * @param  array<int,mixed>  $baris
     */
    private function nomor(array $baris): array
    {
        return array_map(static fn ($r) => $r->No_Transaksi, $baris);
    }

    public function test_harus_datang_hari_ini_memuat_yang_terjadwal_dan_membuang_yang_esok(): void
    {
        $iniHariIni = $this->buatTamu(tanggal: now()->toDateString());
        $besok = $this->buatTamu(tanggal: now()->addDay()->toDateString());
        $kemarin = $this->buatTamu(tanggal: now()->subDay()->toDateString());

        $nomor = $this->nomor($this->papan()['harus_datang']);

        $this->assertContains($iniHariIni, $nomor);
        $this->assertNotContains($besok, $nomor, 'Jadwal besok bukan urusan papan hari ini.');
        $this->assertNotContains($kemarin, $nomor, 'Jadwal kemarin yang tak datang bukan "harus datang hari ini".');
    }

    public function test_kunjungan_berhari_hari_yang_rentangnya_mencakup_hari_ini_harus_datang(): void
    {
        // DDL-2026-121: "hari ini" = rentang Estimasi Masuk..Keluar beririsan
        // dengan hari ini, bukan Tanggal_Kunjungan = hari ini. Kunjungan MITRA
        // yang dimulai kemarin dan berakhir besok tetap harus datang hari ini;
        // yang rentangnya sudah habis kemarin tidak.
        $mitra = $this->buatTamu(
            tanggal: now()->subDay()->toDateString(),
            estimasiMasuk: now()->subDay()->startOfDay()->addHours(8),
            estimasiKeluar: now()->addDay()->startOfDay()->addHours(17),
        );
        $habisKemarin = $this->buatTamu(
            tanggal: now()->subDays(2)->toDateString(),
            estimasiMasuk: now()->subDays(2)->startOfDay()->addHours(8),
            estimasiKeluar: now()->subDay()->startOfDay()->addHours(17),
        );

        $papan = $this->papan();
        $nomor = $this->nomor($papan['harus_datang']);

        $this->assertContains($mitra, $nomor, 'Kunjungan berhari-hari hilang dari papan pada hari keduanya.');
        $this->assertNotContains($habisKemarin, $nomor);

        $baris = collect($papan['harus_datang'])->firstWhere('No_Transaksi', $mitra);
        $this->assertSame(1, (int) $baris->Hadir_Hari_Ini);
        $this->assertNotNull($baris->Estimasi_Masuk);
        $this->assertNotNull($baris->Estimasi_Keluar);
    }

    public function test_papan_tamu_bisa_disaring_per_tipe(): void
    {
        // DDL-2026-121: papan Regular dan papan B2B terpisah; penyaringnya di
        // server (rakit($domain, $tipe)), bukan di layar.
        $reguler = $this->buatTamu(tanggal: now()->toDateString(), tipe: 'REGULAR');
        $warisan = $this->buatTamu(tanggal: now()->toDateString(), tipe: null);
        $b2b = $this->buatTamu(tanggal: now()->toDateString(), tipe: 'B2B');

        $papanReguler = $this->nomor(app(VisitorPapanHarianService::class)->rakit('tamu', 'REGULAR')['harus_datang']);
        $papanB2b = $this->nomor(app(VisitorPapanHarianService::class)->rakit('tamu', 'B2B')['harus_datang']);
        $papanGabungan = $this->nomor(app(VisitorPapanHarianService::class)->rakit('tamu')['harus_datang']);

        $this->assertContains($reguler, $papanReguler);
        $this->assertContains($warisan, $papanReguler, 'Baris warisan tanpa tipe harus terbaca REGULAR.');
        $this->assertNotContains($b2b, $papanReguler);
        $this->assertContains($b2b, $papanB2b);
        $this->assertNotContains($reguler, $papanB2b);
        $this->assertNotContains($warisan, $papanB2b);
        foreach ([$reguler, $warisan, $b2b] as $n) {
            $this->assertContains($n, $papanGabungan, 'Tanpa tipe, papan memuat kedua tipe.');
        }
    }

    public function test_tautan_detail_menuju_meja_sesuai_tipe(): void
    {
        $reguler = $this->buatTamu(tanggal: now()->toDateString(), tipe: 'REGULAR');
        $b2b = $this->buatTamu(tanggal: now()->toDateString(), tipe: 'B2B');

        $baris = collect($this->papan()['harus_datang'])->keyBy('No_Transaksi');

        $this->assertStringStartsWith('/verifikator-visitor/tamu?detail=', $baris[$reguler]->Tautan_Detail);
        $this->assertStringStartsWith('/verifikator-visitor/b2b?detail=', $baris[$b2b]->Tautan_Detail);
    }

    public function test_yang_sudah_masuk_pindah_dari_harus_datang_ke_sedang_di_dalam(): void
    {
        $belum = $this->buatTamu(tanggal: now()->toDateString());
        $sudahMasuk = $this->buatTamu(tanggal: now()->toDateString(), masuk: now()->subHour());

        $papan = $this->papan();

        $this->assertContains($belum, $this->nomor($papan['harus_datang']));
        $this->assertNotContains(
            $sudahMasuk,
            $this->nomor($papan['harus_datang']),
            'Yang sudah di dalam tidak boleh tetap tampil sebagai "belum datang".',
        );
        $this->assertContains($sudahMasuk, $this->nomor($papan['sedang_di_dalam']));
    }

    public function test_yang_sudah_keluar_tidak_lagi_dihitung_sedang_di_dalam(): void
    {
        $masihDiDalam = $this->buatTamu(tanggal: now()->toDateString(), masuk: now()->subHours(2));
        $sudahKeluar = $this->buatTamu(
            tanggal: now()->toDateString(),
            masuk: now()->subHours(3),
            keluar: now()->subMinutes(10),
        );

        $papan = $this->papan();

        $this->assertContains($masihDiDalam, $this->nomor($papan['sedang_di_dalam']));
        $this->assertNotContains($sudahKeluar, $this->nomor($papan['sedang_di_dalam']));
        $this->assertNotContains($sudahKeluar, $this->nomor($papan['sedang_di_dalam']));
    }

    public function test_tamu_menginap_tetap_muncul_walau_tanggalnya_bukan_hari_ini(): void
    {
        // Aturan yang paling mudah salah disaring: membatasi papan ke
        // Tanggal_Kunjungan = hari ini akan menyembunyikan orang yang masih
        // berada di dalam sejak kemarin — baris terpenting di papan mana pun.
        $menginap = $this->buatTamu(
            tanggal: now()->subDays(2)->toDateString(),
            masuk: now()->subDays(2)->setTime(9, 0),
        );

        $papan = $this->papan();

        $this->assertContains($menginap, $this->nomor($papan['sedang_di_dalam']));
        $this->assertContains($menginap, $this->nomor($papan['menginap']));
    }

    public function test_tamu_yang_masuk_hari_ini_tidak_dihitung_menginap(): void
    {
        $hariIni = $this->buatTamu(tanggal: now()->toDateString(), masuk: now()->subHour());

        $this->assertNotContains($hariIni, $this->nomor($this->papan()['menginap']));
    }

    public function test_tamu_dibatalkan_dan_nonaktif_tidak_muncul_di_papan(): void
    {
        // DDL-2026-121: DIBATALKAN lama terpecah menjadi REJECTED (approver),
        // WITHDRAWN (pemohon), dan REVOKED (verifikator) — ketiganya dibuang.
        $ditolak = $this->buatTamu(tanggal: now()->toDateString(), status: 'REJECTED');
        $ditarik = $this->buatTamu(tanggal: now()->toDateString(), status: 'WITHDRAWN');
        $dicabut = $this->buatTamu(tanggal: now()->toDateString(), status: 'REVOKED');
        $nonaktif = $this->buatTamu(tanggal: now()->toDateString(), flagAktif: 'T');
        $terjadwal = $this->buatTamu(tanggal: now()->toDateString());

        $papan = $this->papan();
        $nomor = $this->nomor($papan['harus_datang']);

        $this->assertContains($terjadwal, $nomor);
        foreach ([$ditolak, $ditarik, $dicabut, $nonaktif] as $n) {
            $this->assertNotContains($n, $nomor);
            $this->assertNotContains($n, $this->nomor($papan['menunggu_approval_hari_ini']));
        }
    }

    public function test_waktu_masuk_dibaca_dari_tabel_efektif_yang_ditulis_palang(): void
    {
        // Sumber kebenaran kehadiran adalah N_VISITOR_Tamu_Efektif: aplikasi
        // palang di gerbang yang menandainya, dan syncTamuEfektif SENGAJA tidak
        // menyentuh kedua kolom waktu itu. Membaca kolom detail saja membuat
        // papan buta terhadap orang yang benar-benar sudah masuk.
        $nomor = $this->buatTamu(tanggal: now()->toDateString());
        $this->tandaiLewatPalang($nomor, masuk: now()->subHour());

        $papan = $this->papan();

        $this->assertContains($nomor, $this->nomor($papan['sedang_di_dalam']));
        $this->assertNotContains($nomor, $this->nomor($papan['harus_datang']));
    }

    public function test_waktu_keluar_dari_palang_mengeluarkan_baris_dari_di_dalam(): void
    {
        $nomor = $this->buatTamu(tanggal: now()->toDateString());
        $this->tandaiLewatPalang($nomor, masuk: now()->subHours(3), keluar: now()->subMinutes(20));

        $papan = $this->papan();

        // Yang sudah keluar tidak lagi jadi urusan papan: papan memuat hal yang
        // perlu ditindak, dan riwayatnya ada di All Monitoring.
        $this->assertNotContains($nomor, $this->nomor($papan['sedang_di_dalam']));
        $this->assertNotContains($nomor, $this->nomor($papan['harus_datang']));
    }

    public function test_catatan_manual_di_detail_tetap_dipakai_bila_palang_belum_menulis(): void
    {
        // Cadangan, bukan sumber utama: selama baris efektif masih kosong,
        // catatan manual tetap harus terlihat.
        $nomor = $this->buatTamu(tanggal: now()->toDateString(), masuk: now()->subHour());

        $this->assertContains($nomor, $this->nomor($this->papan()['sedang_di_dalam']));
    }

    public function test_tamu_yang_belum_disetujui_bukan_harus_datang(): void
    {
        $disetujui = $this->buatTamu(tanggal: now()->toDateString());
        $belumDisetujui = $this->buatTamu(tanggal: now()->toDateString(), status: 'PENDING_APPROVAL');

        $papan = $this->papan();

        // Tamu yang belum disetujui tidak dapat di-check-in; menaruhnya di
        // "harus datang" memberi admin tombol yang dijamin gagal.
        $this->assertContains($disetujui, $this->nomor($papan['harus_datang']));
        $this->assertNotContains($belumDisetujui, $this->nomor($papan['harus_datang']));
    }

    public function test_tamu_belum_disetujui_hari_ini_tetap_terlihat_di_kelompoknya_sendiri(): void
    {
        // Menyaringnya keluar tanpa menampilkannya di mana pun menciptakan titik
        // buta: kunjungan hari ini yang tertahan approval justru perlu dikejar.
        $belumDisetujui = $this->buatTamu(tanggal: now()->toDateString(), status: 'PENDING_APPROVAL');
        $besok = $this->buatTamu(tanggal: now()->addDay()->toDateString(), status: 'PENDING_APPROVAL');

        $nomor = $this->nomor($this->papan()['menunggu_approval_hari_ini']);

        $this->assertContains($belumDisetujui, $nomor);
        $this->assertNotContains($besok, $nomor);
    }

    public function test_permit_kedaluwarsa_hari_ini_terpisah_dari_yang_masih_lama(): void
    {
        $hariIni = $this->buatPermit(mulai: now()->subMonth(), kadaluarsa: now());
        $masihLama = $this->buatPermit(mulai: now()->subMonth(), kadaluarsa: now()->addMonths(3));

        $nomor = $this->nomor($this->papan()['permit_kedaluwarsa_hari_ini']);

        $this->assertContains($hariIni, $nomor);
        $this->assertNotContains($masihLama, $nomor);
    }

    public function test_permit_yang_dicabut_tidak_masuk_papan(): void
    {
        // Izin yang sudah dicabut tidak lagi "kedaluwarsa hari ini" atau
        // "segera kedaluwarsa" — aksesnya sudah tidak ada (audit 27 Sep, M8).
        $hariIni = $this->buatPermit(mulai: now()->subMonth(), kadaluarsa: now());
        $segera = $this->buatPermit(mulai: now()->subMonth(), kadaluarsa: now()->addDays(5));
        foreach ([$hariIni, $segera] as $no) {
            DB::table('N_VISITOR_Permit_Efektif')->insert([
                'Kode_Perusahaan' => '001', 'No_Transaksi' => $no, 'Kode_Karyawan_Pemohon' => self::PIC,
                'Nama_Pemohon' => 'Uji', 'Status_Efektif' => 'DIBATALKAN', 'Jenis_Akses' => 'PERMIT',
                'Tanggal_Mulai_Berlaku' => now()->subMonth()->toDateString(), 'Tanggal_Kadaluarsa' => now()->addDays(5)->toDateString(),
                'Disetujui_Oleh_Nama' => 'Sistem Uji', 'Disetujui_At' => now()->subMonth(), 'Riwayat_Approval' => '[]',
                'Dibuat_At' => now()->subMonth(), 'Dibuat_Oleh_Nama' => 'Sistem Uji', 'Flag_Sync' => 'Y', 'Updated_At' => now(),
            ]);
        }

        $papan = $this->papan();

        $this->assertNotContains($hariIni, $this->nomor($papan['permit_kedaluwarsa_hari_ini']));
        $this->assertNotContains($segera, $this->nomor($papan['permit_segera_kedaluwarsa']));
    }

    public function test_satu_jendela_kedaluwarsa_tiga_puluh_hari(): void
    {
        // Dua jendela yang tumpang tindih (7 hari dan 30 hari) menampilkan permit
        // yang sama dua kali. Disatukan jadi satu jendela 30 hari.
        $lusa = $this->buatPermit(mulai: now()->subMonth(), kadaluarsa: now()->addDays(2));
        $sebulan = $this->buatPermit(mulai: now()->subMonth(), kadaluarsa: now()->addDays(29));
        $jauh = $this->buatPermit(mulai: now()->subMonth(), kadaluarsa: now()->addDays(60));

        $nomor = $this->nomor($this->papan()['permit_segera_kedaluwarsa']);

        $this->assertContains($lusa, $nomor);
        $this->assertContains($sebulan, $nomor);
        $this->assertNotContains($jauh, $nomor);
    }

    public function test_papan_tamu_tidak_mengirim_kelompok_permit(): void
    {
        // Menyaring hanya saat render berarti muatannya tetap dikirim dan
        // dihitung sia-sia. Penyaringan harus terjadi di server.
        $papan = app(VisitorPapanHarianService::class)->rakit('tamu');

        foreach (array_keys($papan) as $kunci) {
            $this->assertStringNotContainsString('permit', $kunci, "Kelompok {$kunci} bocor ke papan tamu.");
        }
        $this->assertArrayHasKey('harus_datang', $papan);
    }

    public function test_papan_permit_tidak_mengirim_kelompok_tamu(): void
    {
        $papan = app(VisitorPapanHarianService::class)->rakit('permit');

        foreach (['harus_datang', 'sedang_di_dalam', 'menginap', 'menunggu_approval_hari_ini'] as $kunci) {
            $this->assertArrayNotHasKey($kunci, $papan, "Kelompok {$kunci} bocor ke papan permit.");
        }
        $this->assertArrayHasKey('permit_kedaluwarsa_hari_ini', $papan);
    }

    public function test_tanpa_domain_kedua_ranah_tetap_dikirim(): void
    {
        // Pemanggil lama tidak boleh berubah perilakunya.
        $papan = app(VisitorPapanHarianService::class)->rakit();

        $this->assertArrayHasKey('harus_datang', $papan);
        $this->assertArrayHasKey('permit_kedaluwarsa_hari_ini', $papan);
    }

    public function test_kelompok_yang_bukan_tindakan_tidak_lagi_dikirim(): void
    {
        // "Permit berlaku hari ini" dan "sudah keluar hari ini" adalah direktori
        // dan catatan retrospektif, bukan hal yang perlu ditindak. Tempatnya di
        // All Monitoring; mengirimnya di papan hanya memperpanjang muatan.
        $papan = $this->papan();

        $this->assertArrayNotHasKey('permit_berlaku_hari_ini', $papan);
        $this->assertArrayNotHasKey('sudah_keluar_hari_ini', $papan);
    }

    public function test_ringkasan_membawa_hitungan_orang_bukan_hanya_transaksi(): void
    {
        // Untuk lobi, "12 orang di dalam" beda arti dengan "5 kunjungan".
        // Hitungan orang inilah yang hilang kalau Kartu Keadaan dibuang begitu saja.
        $this->buatTamu(tanggal: now()->toDateString(), jumlahTamu: 4, masuk: now()->subHour());

        $ringkas = $this->papan()['ringkasan'];

        $this->assertArrayHasKey('sedang_di_dalam_orang', $ringkas);
        $this->assertGreaterThanOrEqual(4, $ringkas['sedang_di_dalam_orang']);
        $this->assertGreaterThan(
            $ringkas['sedang_di_dalam'],
            $ringkas['sedang_di_dalam_orang'],
            'Rombongan berisi 4 orang harus menghasilkan orang > transaksi.',
        );
    }

    /**
     * Permit yang disetujui tetapi belum bertanggal BUKAN lagi kelompok papan.
     *
     * Keadaannya nyata — VALIDITY_REQUIRED adalah status resmi di meja
     * verifikasi, lengkap dengan saringannya sendiri — tetapi tempatnya di
     * meja itu, bukan di dashboard. Penjaga ini menuntut kelompoknya benar-
     * benar hilang, karena mengembalikannya berarti satu pekerjaan yang sama
     * dirawat di dua layar.
     */
    public function test_permit_tanpa_tanggal_bukan_kelompok_papan(): void
    {
        $this->buatPermit(mulai: null, kadaluarsa: null);

        $this->assertArrayNotHasKey('permit_tanpa_tanggal', $this->papan());
    }

    public function test_ringkasan_membawa_angka_menunggu_review(): void
    {
        $ringkas = $this->papan()['ringkasan'];

        // Angka tanpa daftar: daftarnya dimiliki tabel antrean keputusan.
        $this->assertArrayHasKey('permit_menunggu_review', $ringkas);
        $this->assertIsInt($ringkas['permit_menunggu_review']);
    }

    public function test_menunggu_review_sama_dengan_antrean_permit(): void
    {
        // Dua layar, satu definisi. Kalau angka ini dihitung ulang dari
        // Flag_Approval IS NULL, ia akan berselisih dengan tabel antrean
        // begitu ada baris approval yang kedaluwarsa.
        $papan = app(VisitorPapanHarianService::class)->rakit('permit');
        $antrean = app(\App\Services\VISITOR\Workflow\Reporting\Dashboard\VisitorDashboardOperasionalService::class)
            ->antrianBerhalaman(['limit' => 1], 'permit');

        $this->assertSame(
            (int) $antrean['paginasi']['total'],
            (int) $papan['ringkasan']['permit_menunggu_review'],
        );
    }

    public function test_ringkasan_angka_sesuai_isi_kelompoknya(): void
    {
        $this->buatTamu(tanggal: now()->toDateString());
        $this->buatTamu(tanggal: now()->toDateString(), masuk: now()->subHour());

        $papan = $this->papan();

        // Angka ringkas yang dihitung terpisah dari daftarnya akan berselisih
        // diam-diam begitu salah satu aturannya berubah.
        $this->assertSame(count($papan['harus_datang']), $papan['ringkasan']['harus_datang']);
        $this->assertSame(count($papan['sedang_di_dalam']), $papan['ringkasan']['sedang_di_dalam']);
        $this->assertSame(count($papan['menginap']), $papan['ringkasan']['menginap']);
    }

    // ---------------------------------------------------------------- helpers

    private function buatTamu(
        string $tanggal,
        ?\DateTimeInterface $masuk = null,
        ?\DateTimeInterface $keluar = null,
        // Kode tersimpan DDL-2026-121 (StatusKunjunganTamu::TERSIMPAN).
        string $status = 'SCHEDULED',
        string $flagAktif = 'Y',
        int $jumlahTamu = 1,
        ?string $tipe = null,
        ?\DateTimeInterface $estimasiMasuk = null,
        ?\DateTimeInterface $estimasiKeluar = null,
    ): string {
        $noTransaksi = 'VSR-PPN-'.Str::upper(Str::random(10));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $noTransaksi,
            'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => $status,
            'Tanggal_Kunjungan' => $tanggal,
            'Needs_Approval' => 'T',
            'Flag_Aktif' => $flagAktif,
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'PPN-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'No_Transaksi' => $noTransaksi,
            'Id_Transaksi_Tamu' => $id,
            'Nama_Lengkap_Tamu' => 'Tamu Papan',
            'Jumlah_Tamu' => $jumlahTamu,
            'Tipe_Tamu' => $tipe,
            'Waktu_Mulai_Kunjungan' => $estimasiMasuk,
            'Waktu_Selesai_Kunjungan' => $estimasiKeluar,
            'Waktu_Aktual_Masuk' => $masuk,
            'Waktu_Aktual_Keluar' => $keluar,
        ]);

        return $noTransaksi;
    }

    /**
     * Meniru aplikasi palang: menulis waktu aktual LANGSUNG ke baris efektif.
     * HCIS tidak punya jalur tulis ke kolom ini, dan syncTamuEfektif sengaja
     * melewatinya, jadi inilah satu-satunya cara data itu lahir di produksi.
     */
    private function tandaiLewatPalang(
        string $noTransaksi,
        ?\DateTimeInterface $masuk = null,
        ?\DateTimeInterface $keluar = null,
    ): void {
        DB::table('N_VISITOR_Tamu_Efektif')->insert([
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $noTransaksi,
            'Nama_Tamu' => 'Tamu Papan',
            'Kode_Karyawan_PIC' => self::PIC,
            'Nama_PIC' => 'PIC Uji',
            'Tanggal_Kunjungan' => now()->toDateString(),
            'Status_Efektif' => 'AKTIF',
            'Disetujui_Oleh_Nama' => 'Sistem Uji',
            'Disetujui_At' => now(),
            'Riwayat_Approval' => '[]',
            'Dibuat_At' => now(),
            'Dibuat_Oleh_Nama' => 'Sistem Uji',
            'Updated_At' => now(),
            'Waktu_Aktual_Masuk' => $masuk,
            'Waktu_Aktual_Keluar' => $keluar,
            'Flag_Sync' => 'Y',
        ]);
    }

    private function buatPermit(?\DateTimeInterface $mulai, ?\DateTimeInterface $kadaluarsa): string
    {
        $noTransaksi = 'PMT-PPN-'.Str::upper(Str::random(10));

        DB::table('N_EMI_VISITOR_Transaksi_Permit')->insert([
            'No_Transaksi' => $noTransaksi,
            'Kode_Karyawan_Pemohon' => self::PIC,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => 'Y',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'PPN-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'No_Transaksi' => $noTransaksi,
            'Tanggal_Mulai_Berlaku' => $mulai?->format('Y-m-d'),
            'Tanggal_Kadaluarsa' => $kadaluarsa?->format('Y-m-d'),
        ]);

        return $noTransaksi;
    }
}
