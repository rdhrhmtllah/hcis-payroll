<?php

namespace Tests\Integration\VISITOR\Workflow\Reporting;

use App\Http\Controllers\VISITOR\Workflow\Reporting\LaporanVisitorController;
use App\Models\ESS\Employee\Models\User;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use App\Services\VISITOR\Workflow\Reporting\Monitoring\VisitorPapanTamuService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\VISITOR\MelihatSeluruhKategoriTamu;
use Tests\TestCase;

/**
 * Monitoring Tamu harus membedakan apa yang memang berbeda.
 *
 * Sepanjang pengerjaan modul ini lahir dua dimensi baru — tipe tamu
 * (REGULAR / B2B) dan izin gerbang turunan kunjungannya — tetapi monitoring
 * tidak pernah ikut menampilkannya. Akibatnya tamu B2B yang izinnya masih
 * menunggu verifikator tampil IDENTIK dengan tamu yang sudah aman, dan admin
 * baru mengetahuinya ketika tamunya tertahan di palang.
 *
 * Yang ditagih di sini KEBERADAAN dimensinya, bukan rupa layarnya: itu yang
 * bertahan ketika tampilannya disusun ulang.
 */
class MonitoringTamuDimensiBaruTest extends TestCase
{
    use DatabaseTransactions;
    use MelihatSeluruhKategoriTamu;

    /** Kode karyawan NYATA di Web_HRIS development. */
    private const PIC = 'A1';

    public function test_papan_harian_membawa_tipe_asal_dan_manifes(): void
    {
        $nomor = $this->buatKunjunganHariIni(['Budi Santoso', 'Andi Wijaya']);

        $baris = $this->cariDiPapan($nomor);

        $this->assertNotNull($baris, 'Kunjungan hari ini tidak muncul di papan harian.');
        $this->assertSame('B2B', $baris->Tipe_Tamu);
        $this->assertSame('HCIS', $baris->Sumber_Kunjungan);

        // Manifes dikirim sebagai ARRAY siap pakai, bukan teks JSON mentah:
        // layar yang harus mem-parse sendiri akan menampilkan tanda kurung siku
        // begitu isinya tak terduga.
        $this->assertIsArray($baris->Daftar_Anggota);
        $this->assertSame(['Budi Santoso', 'Andi Wijaya'], array_column($baris->Daftar_Anggota, 'nama'));
    }

    /**
     * Manifes rusak kini DICEGAH, bukan sekadar ditoleransi.
     *
     * Bentuk awal uji ini menimpa manifes dengan '{bukan json' lalu memastikan
     * papan tetap termuat. Sejak CHECK constraint
     * CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Daftar_Anggota_Json dipasang,
     * keadaan itu tidak bisa lagi dibentuk — basis datanya menolak lebih dulu,
     * dan itu perlindungan yang lebih kuat daripada menoleransinya di hilir.
     */
    public function test_manifes_rusak_ditolak_basis_data(): void
    {
        $nomor = $this->buatKunjunganHariIni([]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $nomor)
            ->update(['Daftar_Anggota_Json' => '{bukan json']);
    }

    /**
     * Sisi yang MASIH mungkin terjadi: manifes kosong.
     *
     * Seluruh registrasi lama tidak pernah mengumpulkan daftar anggota, dan
     * papan tidak boleh jatuh karenanya — kegagalan satu baris warisan jauh
     * lebih merugikan ketika ia menjatuhkan seluruh papan.
     */
    public function test_manifes_kosong_tidak_menjatuhkan_papan(): void
    {
        $nomor = $this->buatKunjunganHariIni([]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $nomor)
            ->update(['Daftar_Anggota_Json' => null]);

        $baris = $this->cariDiPapan($nomor);

        $this->assertNotNull($baris);
        $this->assertSame([], $baris->Daftar_Anggota);
    }

    public function test_papan_menautkan_langsung_ke_meja_tamu_bukan_url_gabungan(): void
    {
        // URL gabungan lama masih hidup sebagai pengalihan, tetapi menautkan ke
        // sana menambah satu lompatan pada SETIAP baris papan.
        // DDL-2026-121: meja tamu kini per tipe — baris B2B ke meja B2B,
        // baris Regular ke meja Regular.
        $b2b = $this->buatKunjunganHariIni([], 'B2B');
        $reguler = $this->buatKunjunganHariIni([], 'REGULAR');

        $this->assertStringStartsWith('/verifikator-visitor/b2b?detail=', $this->cariDiPapan($b2b)->Tautan_Detail);
        $this->assertStringStartsWith('/verifikator-visitor/tamu?detail=', $this->cariDiPapan($reguler)->Tautan_Detail);
    }

    public function test_izin_gerbang_ikut_terbaca_beserta_tautannya(): void
    {
        $nomor = $this->buatKunjunganHariIni([]);
        $this->buatPermitCoklat($nomor);

        $baris = $this->cariDiPapan($nomor);

        $this->assertSame('MENUNGGU', $baris->Status_Permit_Coklat);
        $this->assertStringStartsWith('/verifikator-visitor/permit?detail=', (string) $baris->Tautan_Permit);
    }

    public function test_kunjungan_tanpa_izin_tidak_mengaku_punya(): void
    {
        // Tamu reguler memang tidak berizin gerbang. Membalas keadaan apa pun
        // di situ membuat admin mengira ada izin yang belum diproses.
        $nomor = $this->buatKunjunganHariIni([], 'REGULAR');

        $baris = $this->cariDiPapan($nomor);

        $this->assertNull($baris->Status_Permit_Coklat);
        $this->assertNull($baris->Tautan_Permit);
    }

    /**
     * DDL-2026-121: tipe tamu kini DITETAPKAN RUTE (halaman Regular / B2B),
     * bukan disaring lewat ?tipe=. Yang ditagih: tiap halaman hanya memuat
     * tipenya sendiri, dan ?tipe= dari klien tidak bisa membuka tipe lain.
     */
    public function test_daftar_dibelah_per_tipe_oleh_rute_bukan_isian(): void
    {
        $b2b = $this->buatKunjunganHariIni([], 'B2B');
        $reguler = $this->buatKunjunganHariIni([], 'REGULAR');

        $halamanReguler = array_column($this->laporan([]), 'No_Transaksi');
        $halamanB2b = array_column($this->laporan([], 'B2B'), 'No_Transaksi');

        $this->assertContains($reguler, $halamanReguler);
        $this->assertNotContains($b2b, $halamanReguler, 'Baris B2B bocor ke halaman Regular.');
        $this->assertContains($b2b, $halamanB2b);
        $this->assertNotContains($reguler, $halamanB2b, 'Baris Regular bocor ke halaman B2B.');

        // Pemegang kunci Regular tidak boleh menarik baris B2B hanya dengan
        // mengganti isian ?tipe=.
        $this->assertNotContains(
            $b2b,
            array_column($this->laporan(['tipe' => 'B2B']), 'No_Transaksi'),
            '?tipe=B2B di rute Regular memulangkan baris B2B.',
        );
    }

    public function test_daftar_lengkap_bisa_disaring_per_asal_dan_per_kategori(): void
    {
        $visitor = $this->buatKunjunganHariIni([], 'REGULAR');
        $mitra = $this->buatKunjunganHariIni([], 'REGULAR');
        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $visitor)->update(['Tamu_Kategori' => 'VISITOR']);
        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $mitra)->update(['Tamu_Kategori' => 'MITRA']);

        $semua = count($this->laporan([]));
        $hcis = count($this->laporan(['sumber' => 'HCIS']));
        $asalMitra = count($this->laporan(['sumber' => 'MITRA']));

        // Penyaringnya harus MEMBELAH himpunan, bukan sekadar mengurangi.
        // Penyaring yang salah arah tetap memulangkan lebih sedikit baris dan
        // tetap terlihat "bekerja".
        $this->assertSame($semua, $hcis + $asalMitra, 'Asal pendaftaran tidak membelah himpunan dengan utuh.');

        // Kategori (VISITOR / MITRA, DDL-2026-121) menggantikan penyaring tipe
        // di layar: tiap kategori hanya memuat dirinya sendiri.
        $kategoriVisitor = $this->laporan(['kategori' => 'VISITOR']);
        $kategoriMitra = $this->laporan(['kategori' => 'MITRA']);

        $this->assertContains($visitor, array_column($kategoriVisitor, 'No_Transaksi'));
        $this->assertNotContains($mitra, array_column($kategoriVisitor, 'No_Transaksi'));
        $this->assertContains($mitra, array_column($kategoriMitra, 'No_Transaksi'));
        $this->assertNotContains($visitor, array_column($kategoriMitra, 'No_Transaksi'));
        foreach ($kategoriMitra as $baris) {
            $this->assertSame('MITRA', $baris['Tamu_Kategori']);
        }
    }

    public function test_penyaring_reguler_ikut_memuat_baris_warisan_tanpa_tipe(): void
    {
        // Kolom Tipe_Tamu baru ada belakangan; kunjungan lama menyimpan NULL.
        // Menyaring dengan '=' saja membuat mereka lenyap dari hasil tanpa satu
        // pun keterangan. Sejak DDL-2026-121 penyaring itu dipasang rute
        // Regular, jadi warisan harus tetap tampil di halaman Regular.
        $nomor = $this->buatKunjunganHariIni([], 'REGULAR');
        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $nomor)
            ->update(['Tipe_Tamu' => null]);

        $this->assertContains($nomor, array_column($this->laporan([]), 'No_Transaksi'));
        $this->assertNotContains($nomor, array_column($this->laporan([], 'B2B'), 'No_Transaksi'));
    }

    /**
     * Status di daftar menceritakan KEHADIRAN, bukan keadaan baris gerbang.
     *
     * Dulu kolomnya memulangkan Status_Efektif mentah dan layar melabelinya
     * "Aktif / Di Dalam". Terukur di development: 10 dari 10 baris berlencana
     * hijau "Di Dalam", dan NOL di antaranya benar-benar di dalam — enam belum
     * pernah datang, empat sudah pulang. Lencana yang tidak pernah sekali pun
     * benar lebih buruk daripada kolom yang kosong.
     */
    public function test_status_menceritakan_kehadiran_bukan_keadaan_baris_gerbang(): void
    {
        // Baris Regular: daftar dibaca lewat rute Regular (DDL-2026-121).
        $belumDatang = $this->buatKunjunganHariIni([], 'REGULAR');
        $diDalam = $this->buatKunjunganHariIni([], 'REGULAR');
        $sudahKeluar = $this->buatKunjunganHariIni([], 'REGULAR');

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $diDalam)
            ->update(['Waktu_Aktual_Masuk' => now()->subHour()]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $sudahKeluar)
            ->update(['Waktu_Aktual_Masuk' => now()->subHours(3), 'Waktu_Aktual_Keluar' => now()->subHour()]);

        $status = collect($this->laporan([]))->pluck('Status_Kunjungan', 'No_Transaksi');

        // Kosakata status tampil DDL-2026-121 (StatusKunjunganTamu::TAMPIL).
        $this->assertSame('SCHEDULED', $status[$belumDatang]);
        $this->assertSame('CHECKED_IN', $status[$diDalam]);
        $this->assertSame('CHECKED_OUT', $status[$sudahKeluar]);
    }

    public function test_jadwal_yang_lewat_tanpa_kedatangan_disebut_cancelled(): void
    {
        // "Terjadwal" selamanya adalah kebohongan yang paling mudah lolos:
        // jadwalnya memang pernah benar, hanya tidak pernah diperbarui.
        // DDL-2026-121: Estimasi Keluar lewat tanpa masuk = CANCELLED (dulu
        // TIDAK_DATANG). Tanpa estimasi, Estimasi Keluar = Tanggal_Kunjungan + 1 hari.
        $nomor = $this->buatKunjunganHariIni([], 'REGULAR');
        DB::table('N_EMI_VISITOR_Transaksi_Tamu')
            ->where('No_Transaksi', $nomor)
            ->update(['Tanggal_Kunjungan' => now()->subDays(3)->toDateString()]);

        $status = collect($this->laporanRentangLuas())->firstWhere('No_Transaksi', $nomor);

        $this->assertSame('CANCELLED', $status['Status_Kunjungan']);
    }

    public function test_kunjungan_berhari_hari_tidak_cancelled_sebelum_estimasi_keluar(): void
    {
        // Pasangan uji di atas: tanggal kunjungan yang sama sudah lewat, tetapi
        // Estimasi Keluar-nya masih besok (MITRA berhari-hari). Membaca tanggal
        // kunjungan saja akan menyebutnya CANCELLED sejak hari kedua.
        $nomor = $this->buatKunjunganHariIni([], 'REGULAR');
        DB::table('N_EMI_VISITOR_Transaksi_Tamu')
            ->where('No_Transaksi', $nomor)
            ->update(['Tanggal_Kunjungan' => now()->subDays(3)->toDateString()]);
        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $nomor)
            ->update([
                'Waktu_Mulai_Kunjungan' => now()->subDays(3)->startOfDay()->addHours(8),
                'Waktu_Selesai_Kunjungan' => now()->addDay(),
            ]);

        $status = collect($this->laporanRentangLuas())->firstWhere('No_Transaksi', $nomor);

        $this->assertSame('SCHEDULED', $status['Status_Kunjungan']);
    }

    /**
     * Keputusan akhir yang tersimpan menang atas kehadiran: kunjungan yang
     * dicabut / ditolak / ditarik tidak punya kehadiran untuk diceritakan,
     * walau palang sempat mencatat sesuatu. DDL-2026-121 memecah DIBATALKAN
     * lama menjadi REJECTED / WITHDRAWN / REVOKED.
     */
    public function test_keputusan_akhir_menang_atas_kehadiran(): void
    {
        foreach (['REVOKED', 'REJECTED', 'WITHDRAWN'] as $keputusan) {
            $nomor = $this->buatKunjunganHariIni([], 'REGULAR');
            DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
                ->where('No_Transaksi', $nomor)
                ->update(['Waktu_Aktual_Masuk' => now()->subHour()]);
            DB::table('N_EMI_VISITOR_Transaksi_Tamu')
                ->where('No_Transaksi', $nomor)
                ->update(['Status_Kunjungan' => $keputusan]);

            $status = collect($this->laporan([]))->firstWhere('No_Transaksi', $nomor);

            $this->assertNotNull($status, "Kunjungan {$keputusan} hilang dari daftar.");
            $this->assertSame($keputusan, $status['Status_Kunjungan']);
        }
    }

    public function test_penyaring_status_memakai_kosakata_yang_sama_dengan_kolomnya(): void
    {
        // Penyaring yang memakai kosakata lain memulangkan baris yang
        // lencananya bertuliskan hal berbeda — dan tidak ada galat apa pun.
        $diDalam = $this->buatKunjunganHariIni([], 'REGULAR');
        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $diDalam)
            ->update(['Waktu_Aktual_Masuk' => now()->subHour()]);

        $hasil = $this->laporan(['status' => 'CHECKED_IN']);

        $this->assertContains($diDalam, array_column($hasil, 'No_Transaksi'));
        foreach ($hasil as $baris) {
            $this->assertSame('CHECKED_IN', $baris['Status_Kunjungan']);
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function laporanRentangLuas(): array
    {
        $user = User::find(DB::table('Karyawan')->where('Kode_Karyawan', self::PIC)->value('UserID_Web'));
        $request = Request::create('/laporanvisitor-tamu', 'GET', [
            'date_from' => now()->subDays(30)->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
            'limit' => 200,
        ]);
        // Laporan tamu disaring MATRIKS sejak halaman Jadwal dilebur ke sini;
        // pengguna tanpa hak kategori memulangkan daftar kosong.
        $this->beriHakLihatSeluruhKategoriTamu($user?->Id_Users !== null ? (int) $user->Id_Users : null);
        $request->setUserResolver(fn () => $user);

        $isi = json_decode(
            app(LaporanVisitorController::class)->getLaporanTamu($request)->getContent(),
            true,
        );

        return $isi['result']['data'] ?? $isi['result'] ?? [];
    }

    private function cariDiPapan(string $nomor): ?object
    {
        $hasil = app(VisitorPapanTamuService::class)->daftar(['limit' => 100, 'cari' => $nomor]);

        return collect($hasil['baris'])->firstWhere('No_Transaksi', $nomor);
    }

    /**
     * @param  string|null  $tipeRute  tipe yang ditetapkan rute (middleware
     *                                 visitor.tipe-tamu); null = rute Regular lama
     * @return array<int,array<string,mixed>>
     */
    private function laporan(array $penyaring, ?string $tipeRute = null): array
    {
        $userId = DB::table('Karyawan')->where('Kode_Karyawan', self::PIC)->value('UserID_Web');
        $user = User::find($userId);
        $this->assertNotNull($user, 'Karyawan '.self::PIC.' tidak punya baris login.');

        $request = Request::create('/laporanvisitor-tamu', 'GET', $penyaring + [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
            'limit' => 100,
        ]);
        // Laporan tamu disaring MATRIKS sejak halaman Jadwal dilebur ke sini;
        // pengguna tanpa hak kategori memulangkan daftar kosong.
        $this->beriHakLihatSeluruhKategoriTamu($user?->Id_Users !== null ? (int) $user->Id_Users : null);
        $request->setUserResolver(fn () => $user);
        if ($tipeRute !== null) {
            $request->attributes->set(TipeTamu::ATRIBUT, $tipeRute);
        }

        $isi = json_decode(
            app(LaporanVisitorController::class)->getLaporanTamu($request)->getContent(),
            true,
        );

        return $isi['result']['data'] ?? $isi['result'] ?? [];
    }

    /** @param list<string> $anggota */
    private function buatKunjunganHariIni(array $anggota, string $tipe = 'B2B'): string
    {
        $nomor = 'VSR-MON-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => 'SCHEDULED',
            'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => now()->toDateString(),
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'MON-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Tamu Monitoring',
            'Tipe_Tamu' => $tipe,
            // Tamu B2B SELALU punya unit di jalur sungguhan (RegistrasiTamuService
            // memakukannya), dan matriks menyaring lewat unit itu. Fixture tanpa
            // unit bukan bentuk yang pernah ada di data - 0 dari 13 baris B2B di
            // development - jadi menghilangkannya di sini membuat uji berangkat
            // dari keadaan yang tidak mungkin terjadi.
            'B2B_Unit' => $tipe === 'B2B' ? 'PROCUREMENT' : null,
            'Instansi_Atau_Asal' => 'PT Uji Monitoring',
            'Deksripsi_Keperluan' => 'Meeting',
            'Jumlah_Tamu' => count($anggota) + 1,
            'Daftar_Anggota_Json' => json_encode(
                array_map(static fn (string $nama): array => ['nama' => $nama], $anggota),
            ),
        ]);

        return $nomor;
    }

    private function buatPermitCoklat(string $noTransaksiTamu): string
    {
        $nomor = 'PMT-MON-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            'No_Transaksi_Tamu' => $noTransaksiTamu,
            'Kode_Karyawan_Pemohon' => self::PIC,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => null,
            'Sumber_Pengajuan' => 'HCIS',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'MON-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'Id_Transaksi_Permit' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Tamu' => 'Tamu Monitoring',
        ]);

        return $nomor;
    }
}
