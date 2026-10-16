<?php

namespace Tests\Feature\ESS\Employee;

use App\Jobs\ESS\Employee\DataCollection\KirimPengingatPengumpulanDataJob;
use App\Services\ESS\Employee\DataCollection\AdminPengumpulanDataService;
use App\Services\ESS\Employee\DataCollection\CakupanKampanye;
use App\Services\ESS\Employee\DataCollection\KonfigurasiKampanye;
use App\Services\ESS\Employee\DataCollection\PengumpulanDataExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Cakupan lokasi: kampanye pembaruan data dibuka per lokasi kerja.
 *
 * Yang dijaga berkas ini bukan "apakah kolomnya tersimpan", melainkan apakah
 * cakupan benar-benar menyempitkan SEMUA tempat yang menghitung populasi.
 * Satu kueri yang lupa menerapkannya tidak menghasilkan galat apa pun — ia
 * hanya membuat persentase progres berbohong, atau mengirimi pengingat kepada
 * orang yang formulirnya bahkan belum dibuka.
 *
 * Setiap penjaga berpasangan dengan penjaga PENOLAKAN (orang yang sama, cakupan
 * yang memuatnya) supaya tidak ada yang lulus hanya karena semuanya kosong.
 */
class CakupanLokasiPengumpulanDataTest extends TestCase
{
    use DatabaseTransactions;

    /** Lokasi yang dijamin tidak dimiliki siapa pun. */
    private const LOKASI_ASING = 'ZZ-LOKASI-TIDAK-ADA';

    private function admin(): AdminPengumpulanDataService
    {
        return app(AdminPengumpulanDataService::class);
    }

    private function cakupan(): CakupanKampanye
    {
        return app(CakupanKampanye::class);
    }

    /**
     * Cakupan disetel lewat kolomnya langsung, BUKAN lewat aturCakupan(), supaya
     * jalur baca diuji terpisah dari jalur tulis. Kalau keduanya dipakai
     * bersamaan, satu kesalahan normalisasi yang sama di kedua sisi akan saling
     * menutupi dan seluruh berkas ini tetap hijau.
     */
    private function setCakupan(array $lokasi): void
    {
        DB::table('N_HRIS_Pengumpulan_Data_Config')->update([
            'Cakupan_Lokasi' => $lokasi === [] ? null : json_encode($lokasi),
        ]);

        app(KonfigurasiKampanye::class)->bersihkanCache();
    }

    private function karyawanBerlokasi(): object
    {
        $karyawan = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNull('Tanggal_Resign')
            ->whereNotNull('Cabang_Untuk_Gaji')
            ->whereRaw("LTRIM(RTRIM(Cabang_Untuk_Gaji)) <> ''")
            ->orderBy('Kode_Karyawan')
            ->first(['Kode_Karyawan', 'Cabang_Untuk_Gaji']);

        $this->assertNotNull($karyawan, 'Butuh karyawan aktif yang punya Cabang_Untuk_Gaji.');

        return $karyawan;
    }

    private function jumlahAktif(): int
    {
        return DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNull('Tanggal_Resign')
            ->count();
    }

    private function jumlahAktifDiLokasi(string $lokasi): int
    {
        return DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNull('Tanggal_Resign')
            ->whereRaw('UPPER(LTRIM(RTRIM(Cabang_Untuk_Gaji))) = ?', [CakupanKampanye::normalkan($lokasi)])
            ->count();
    }

    // -----------------------------------------------------------------------
    // Bawaan: tanpa cakupan, tidak ada yang berubah.
    // -----------------------------------------------------------------------

    public function test_tanpa_cakupan_seluruh_karyawan_aktif_tetap_jadi_target(): void
    {
        $this->setCakupan([]);

        $this->assertTrue($this->cakupan()->terbukaUntukSemuaLokasi());
        $this->assertSame($this->jumlahAktif(), $this->admin()->ringkasan()['total']);
    }

    public function test_tanpa_cakupan_orang_tanpa_lokasi_tetap_ikut(): void
    {
        $this->setCakupan([]);

        // Kode yang tidak ada di tabel Karyawan berlokasi kosong bagi mesin ini —
        // bentuk paling murni dari "tak punya lokasi".
        $this->assertTrue($this->cakupan()->mencakup('KODE-TIDAK-ADA-XYZ'));
    }

    // -----------------------------------------------------------------------
    // Ringkasan dan daftar.
    // -----------------------------------------------------------------------

    public function test_ringkasan_hanya_menghitung_lokasi_yang_dibuka(): void
    {
        $karyawan = $this->karyawanBerlokasi();
        $harusnya = $this->jumlahAktifDiLokasi($karyawan->Cabang_Untuk_Gaji);

        $this->setCakupan([CakupanKampanye::normalkan($karyawan->Cabang_Untuk_Gaji)]);
        $ringkasan = $this->admin()->ringkasan();

        $this->assertSame($harusnya, $ringkasan['total']);
        $this->assertLessThan(
            $this->jumlahAktif(),
            $ringkasan['total'],
            'Uji ini tidak membuktikan apa pun bila satu lokasi memuat SELURUH karyawan aktif.',
        );
        $this->assertSame($ringkasan['total'], $ringkasan['sudah'] + $ringkasan['belum'] + $ringkasan['dikecualikan']);
    }

    public function test_orang_di_luar_cakupan_hilang_dari_daftar(): void
    {
        $karyawan = $this->karyawanBerlokasi();

        $this->setCakupan([self::LOKASI_ASING]);
        $daftar = $this->admin()->daftar(['cari' => $karyawan->Kode_Karyawan]);

        $this->assertSame(0, $daftar['total']);
    }

    /** Pasangan penolakan: orang yang sama, cakupan yang memuatnya. */
    public function test_orang_di_dalam_cakupan_tetap_muncul_di_daftar(): void
    {
        $karyawan = $this->karyawanBerlokasi();

        $this->setCakupan([CakupanKampanye::normalkan($karyawan->Cabang_Untuk_Gaji)]);
        $daftar = $this->admin()->daftar(['cari' => $karyawan->Kode_Karyawan]);

        $this->assertGreaterThan(0, $daftar['total']);
    }

    public function test_daftar_tidak_pernah_memuat_karyawan_tanpa_lokasi_saat_cakupan_dipakai(): void
    {
        $karyawan = $this->karyawanBerlokasi();
        $this->setCakupan([CakupanKampanye::normalkan($karyawan->Cabang_Untuk_Gaji)]);

        $kodeDalamDaftar = collect($this->admin()->daftar(['per_halaman' => 200])['items'])
            ->pluck('Kode_Karyawan')
            ->all();

        $tanpaLokasi = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNull('Tanggal_Resign')
            ->where(function ($q) {
                $q->whereNull('Cabang_Untuk_Gaji')->orWhereRaw("LTRIM(RTRIM(Cabang_Untuk_Gaji)) = ''");
            })
            ->pluck('Kode_Karyawan')
            ->all();

        $this->assertEmpty(
            array_intersect($kodeDalamDaftar, $tanpaLokasi),
            'Karyawan tanpa lokasi kerja tidak boleh ikut cakupan mana pun.',
        );
    }

    // -----------------------------------------------------------------------
    // Nilai kotor di master data.
    // -----------------------------------------------------------------------

    public function test_cakupan_cocok_walau_ditulis_beda_huruf_dan_berspasi(): void
    {
        $karyawan = $this->karyawanBerlokasi();
        $kotor = '  '.mb_strtolower($karyawan->Cabang_Untuk_Gaji).' ';

        // Sengaja lewat normalkanDaftar: itulah yang dipakai saat admin menyimpan.
        $this->setCakupan(CakupanKampanye::normalkanDaftar([$kotor]));

        $this->assertTrue(
            $this->cakupan()->mencakup($karyawan->Kode_Karyawan),
            'Satu spasi ekor di master data tidak boleh mengeluarkan orang dari kampanye.',
        );
        $this->assertGreaterThan(0, $this->admin()->daftar(['cari' => $karyawan->Kode_Karyawan])['total']);
    }

    public function test_lokasi_asing_tidak_mencakup_siapa_pun(): void
    {
        $karyawan = $this->karyawanBerlokasi();
        $this->setCakupan([self::LOKASI_ASING]);

        $this->assertFalse($this->cakupan()->mencakup($karyawan->Kode_Karyawan));
        $this->assertSame(0, $this->admin()->ringkasan()['total']);
    }

    // -----------------------------------------------------------------------
    // Pilihan lokasi untuk kotak centang.
    // -----------------------------------------------------------------------

    public function test_pilihan_lokasi_tidak_ikut_menyempit_oleh_cakupan(): void
    {
        $sebelum = $this->admin()->pilihanLokasi();

        $this->setCakupan([self::LOKASI_ASING]);

        $this->assertSame(
            $sebelum,
            $this->admin()->pilihanLokasi(),
            'Kotak centang cakupan harus tetap menawarkan lokasi yang BELUM dibuka.',
        );
        $this->assertNotEmpty($sebelum);
    }

    public function test_pilihan_lokasi_sudah_ternormalkan_dan_tanpa_kembar(): void
    {
        $pilihan = $this->admin()->pilihanLokasi();

        $this->assertSame(array_values(array_unique($pilihan)), $pilihan);

        foreach ($pilihan as $lokasi) {
            $this->assertSame(CakupanKampanye::normalkan($lokasi), $lokasi);
            $this->assertNotSame('', $lokasi);
        }
    }

    // -----------------------------------------------------------------------
    // Menyimpan cakupan.
    // -----------------------------------------------------------------------

    public function test_menyimpan_cakupan_membentuk_daftar_kanonis_dan_tercatat_di_audit(): void
    {
        $tersimpan = $this->admin()->aturCakupan(['  emi ', 'EMI', 'jkt'], 'UJI-ADMIN');

        $this->assertSame(['EMI', 'JKT'], $tersimpan);
        $this->assertSame(['EMI', 'JKT'], $this->cakupan()->lokasiTerpilih());

        $audit = DB::table('N_HRIS_Pengumpulan_Data_Audit')
            ->where('Aksi', 'UBAH_CAKUPAN')
            ->orderByDesc('Id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('UJI-ADMIN', $audit->Dilakukan_Oleh);
        $this->assertSame('EMI, JKT', $audit->Nilai_Sesudah);
    }

    public function test_menyimpan_daftar_kosong_mengembalikan_kampanye_ke_semua_lokasi(): void
    {
        $this->setCakupan(['EMI']);

        $this->admin()->aturCakupan([], 'UJI-ADMIN');

        $this->assertTrue($this->cakupan()->terbukaUntukSemuaLokasi());
        $this->assertSame($this->jumlahAktif(), $this->admin()->ringkasan()['total']);
        $this->assertNull(
            DB::table('N_HRIS_Pengumpulan_Data_Config')->orderBy('Id')->value('Cakupan_Lokasi'),
            'Daftar kosong disimpan sebagai NULL, bukan sebagai "[]".',
        );
    }

    /**
     * Cakupan adalah gelombang pembukaan, bukan pengecualian massal. Begitu ia
     * menulis baris status, menggeser cakupan jadi menulis ribuan baris dan
     * jejak pengecualian individual ikut terkubur.
     */
    public function test_mengubah_cakupan_tidak_menyentuh_satu_baris_status_pun(): void
    {
        $sebelum = DB::table('N_HRIS_Pengumpulan_Data_Status')->count();

        $this->admin()->aturCakupan(['EMI'], 'UJI-ADMIN');
        $this->admin()->aturCakupan([], 'UJI-ADMIN');

        $this->assertSame($sebelum, DB::table('N_HRIS_Pengumpulan_Data_Status')->count());
        $this->assertSame(
            0,
            DB::table('N_HRIS_Pengumpulan_Data_Status')->where('Is_Dikecualikan', 'Y')
                ->whereNull('Dikecualikan_Oleh')->count(),
            'Tidak boleh ada pengecualian tanpa pelaku — itu tanda cakupan menumpang kolom yang salah.',
        );
    }

    // -----------------------------------------------------------------------
    // Pengingat dan ekspor.
    // -----------------------------------------------------------------------

    public function test_pengingat_tidak_menyasar_lokasi_yang_belum_dibuka(): void
    {
        Queue::fake();
        $this->setCakupan([self::LOKASI_ASING]);

        $this->assertSame(0, $this->admin()->kirimPengingat('UJI-ADMIN'));
        Queue::assertNothingPushed();
    }

    public function test_pengingat_tetap_menyasar_lokasi_yang_dibuka(): void
    {
        Queue::fake();

        $karyawan = $this->karyawanBerlokasi();
        $lokasi = CakupanKampanye::normalkan($karyawan->Cabang_Untuk_Gaji);
        $this->setCakupan([$lokasi]);

        $harusnya = DB::table('Karyawan as k')
            ->leftJoin('N_HRIS_Pengumpulan_Data_Status as s', 's.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->whereRaw('UPPER(LTRIM(RTRIM(k.Cabang_Untuk_Gaji))) = ?', [$lokasi])
            ->where(function ($q) {
                $q->where('s.Is_Dikecualikan', '<>', 'Y')->orWhereNull('s.Is_Dikecualikan');
            })
            ->where(function ($q) {
                $q->whereNull('s.Status')->orWhere('s.Status', '<>', 'SELESAI');
            })
            ->count();

        $this->assertGreaterThan(0, $harusnya, 'Butuh minimal satu orang yang belum mengisi di lokasi itu.');
        $this->assertSame($harusnya, $this->admin()->kirimPengingat('UJI-ADMIN'));
        Queue::assertPushed(KirimPengingatPengumpulanDataJob::class);
    }

    public function test_ekspor_hanya_memuat_lokasi_yang_dibuka(): void
    {
        $ekspor = app(PengumpulanDataExportService::class);
        $karyawan = $this->karyawanBerlokasi();

        $this->setCakupan([CakupanKampanye::normalkan($karyawan->Cabang_Untuk_Gaji)]);
        $dalam = $ekspor->hitungTarget([]);

        $this->setCakupan([self::LOKASI_ASING]);
        $luar = $ekspor->hitungTarget([]);

        $this->assertSame($this->jumlahAktifDiLokasi($karyawan->Cabang_Untuk_Gaji), $dalam);
        $this->assertSame(0, $luar);
    }

    // -----------------------------------------------------------------------
    // Yang tertinggal harus bersuara.
    // -----------------------------------------------------------------------

    public function test_ringkasan_melaporkan_berapa_orang_yang_ditinggalkan(): void
    {
        $karyawan = $this->karyawanBerlokasi();
        $diLokasi = $this->jumlahAktifDiLokasi($karyawan->Cabang_Untuk_Gaji);

        $this->setCakupan([CakupanKampanye::normalkan($karyawan->Cabang_Untuk_Gaji)]);
        $ringkasan = $this->admin()->ringkasan();

        $this->assertSame($this->jumlahAktif() - $diLokasi, $ringkasan['di_luar_cakupan']['total']);
    }

    public function test_tanpa_cakupan_tidak_ada_yang_dilaporkan_tertinggal(): void
    {
        $this->setCakupan([]);

        $this->assertSame(
            ['total' => 0, 'tanpa_lokasi' => 0],
            $this->admin()->ringkasan()['di_luar_cakupan'],
        );
    }

    // -----------------------------------------------------------------------
    // Saringan tampilan — hal yang berbeda dari cakupan.
    // -----------------------------------------------------------------------

    public function test_saringan_lokasi_tidak_memperluas_cakupan(): void
    {
        $karyawan = $this->karyawanBerlokasi();

        $this->setCakupan([self::LOKASI_ASING]);

        $daftar = $this->admin()->daftar([
            'lokasi' => $karyawan->Cabang_Untuk_Gaji,
            'per_halaman' => 200,
        ]);

        $this->assertSame(
            0,
            $daftar['total'],
            'Memilih lokasi di dropdown tidak boleh menarik masuk lokasi yang kampanyenya belum dibuka.',
        );
    }

    public function test_saringan_lokasi_menyempitkan_daftar_di_dalam_cakupan(): void
    {
        $this->setCakupan([]);

        $karyawan = $this->karyawanBerlokasi();
        $daftar = $this->admin()->daftar(['lokasi' => $karyawan->Cabang_Untuk_Gaji, 'per_halaman' => 200]);

        $this->assertSame($this->jumlahAktifDiLokasi($karyawan->Cabang_Untuk_Gaji), $daftar['total']);
        $this->assertLessThan($this->jumlahAktif(), $daftar['total']);
    }

    public function test_saringan_lokasi_kosong_tidak_menyaring_apa_pun(): void
    {
        $this->setCakupan([]);

        $this->assertSame(
            $this->jumlahAktif(),
            $this->admin()->daftar(['lokasi' => '   ', 'per_halaman' => 200])['total'],
        );
    }
}
