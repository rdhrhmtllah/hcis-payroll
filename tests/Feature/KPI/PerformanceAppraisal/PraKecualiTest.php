<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Katalog\PaLevelDikecualikan;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaCyclePreparationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;

/**
 * Daftar pengecualian AWAL — siapa yang tidak dinilai, ditulis sebelum peserta dibentuk.
 *
 * Yang paling dijaga: daftar ini harus mengubah **pratinjau DAN penyiapan sungguhan dengan
 * cara yang sama**. Keduanya memanggil `nilaiKelayakan()` yang sama, tetapi masing-masing
 * membaca daftarnya sendiri — kalau salah satu lupa membacanya, angkanya berselisih tepat
 * pada orang-orang yang paling diperhatikan admin.
 */
class PraKecualiTest extends TestCase
{
    use DatabaseTransactions;

    private PaCyclePreparationService $service;

    private PaCycleGate $gate;

    /** Level yang benar-benar dipakai berkas ini; pengecualiannya dinetralkan di setUp. */
    private const LEVEL_YANG_DIPAKAI = [2];

    protected function setUp(): void
    {
        parent::setUp();

        $this->gate = new PaCycleGate;
        /* Dirakit container, bukan `new` dengan daftar argumen tetap — sebab yang sama
           seperti di CyclePreparationTest: daftar harfiah MATI TOTAL sebagai
           ArgumentCountError begitu servicenya menerima ketergantungan baru, dan
           kegagalan itu tidak mengatakan apa pun tentang perilaku yang sedang diuji. */
        $this->service = app(PaCyclePreparationService::class);

        /* PENGECUALIAN LEVEL YANG DIPAKAI BERKAS INI DINETRALKAN LEBIH DULU.

           Sejak Matriks Jenis Penilai bisa disunting admin (DDL-2026-091), daftar level yang
           tidak dinilai adalah DATA HIDUP: satu level yang dikecualikan seseorang membuat uji
           ini gagal menyebut peserta yang tidak terbentuk - kegagalan yang terbaca seperti
           regresi lalu dikejar ke tempat yang salah.

           DISEBUT SATU PER SATU, bukan dikosongkan semuanya: mengosongkan seluruhnya menarik
           level lain ikut disiapkan, termasuk yang bobot penilainya sedang tidak genap 100 di
           basis data pengembangan.

           Aman karena berkas ini memakai DatabaseTransactions. Memonya ikut dilupakan, kalau
           tidak jawaban lama yang sudah dibaca tetap dipakai. */
        DB::table('N_PA_Level_Exclusion')
            ->where('Flag_Aktif', 'Y')
            ->whereIn('ID_Level', self::LEVEL_YANG_DIPAKAI)
            ->update(['Flag_Aktif' => 'T']);
        PaLevelDikecualikan::lupakan();

    }

    public function test_pengecualian_awal_terbawa_ke_pratinjau_dan_penyiapan(): void
    {
        $cycleId = $this->siklusDraft();
        $orang = $this->karyawanBelumDikecualikan($cycleId, 3);

        $sebelum = $this->service->pratinjauPenyiapan($cycleId);

        $this->service->tambahPraKecuali(
            $cycleId,
            array_map(fn ($k) => [
                'kode_perusahaan' => $k->Kode_Perusahaan,
                'kode_karyawan' => $k->Kode_Karyawan,
            ], $orang),
            'Sedang tugas belajar tahun ini.',
            4,
        );

        $sesudah = $this->service->pratinjauPenyiapan($cycleId);

        // Pratinjau ikut bergeser, tepat sebanyak orang yang ditambahkan.
        $this->assertSame(
            $sebelum['cacah']['diikutkan'] - count($orang),
            $sesudah['cacah']['diikutkan'],
            'Pratinjau tidak menghormati daftar pengecualian awal.'
        );
        $this->assertSame(count($orang), $sesudah['pra_kecuali_terpakai']);

        // Dan penyiapan sungguhan menghasilkan angka yang SAMA PERSIS.
        $this->gate->lupakanMemo();
        $this->service->siapkan($cycleId);

        $nyata = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->count();

        $this->assertSame(
            $sesudah['cacah']['diikutkan'],
            $nyata,
            'Pratinjau dan penyiapan berselisih pada orang yang dikecualikan admin.'
        );
    }

    /**
     * Sesudah peserta terbentuk, menambah pengecualian ikut mengecualikan pesertanya saat itu
     * juga. Dulu server menjawab "ditambahkan" sementara orangnya tetap DIIKUTKAN.
     */
    public function test_pengecualian_sesudah_peserta_terbentuk_ikut_mengecualikan_peserta(): void
    {
        $cycleId = $this->siklusDraft();
        $orang = $this->karyawanBelumDikecualikan($cycleId, 1)[0];

        $this->service->siapkan($cycleId);
        $this->gate->lupakanMemo();

        $status = fn () => DB::table('N_PA_Participant')->where('PA_Cycle_Id', $cycleId)
            ->where('Kode_Karyawan', $orang->Kode_Karyawan)->value('Status_Peserta');
        $this->assertSame('DIIKUTKAN', $status());

        $hasil = $this->service->tambahPraKecuali($cycleId, [[
            'kode_perusahaan' => $orang->Kode_Perusahaan,
            'kode_karyawan' => $orang->Kode_Karyawan,
        ]], 'Mutasi ke anak usaha bulan ini.', 4);

        $this->assertSame(1, $hasil['peserta_dikecualikan']);
        $this->assertSame('DIKECUALIKAN', $status());
    }

    public function test_alasan_admin_menang_atas_alasan_otomatis(): void
    {
        /* Urutan pemeriksaan disengaja: keputusan manusia diperiksa SEBELUM aturan mesin.
           Kalau seorang FREELANCE juga ada di daftar admin, yang tercatat harus alasan
           ADMIN — yang membaca setahun kemudian perlu tahu ada orang yang memutuskannya,
           bukan aturan yang kebetulan juga berlaku. */
        $freelance = DB::table('Karyawan as k')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as v',
                'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')
            ->where('v.ID_Level', 21)
            ->first(['k.Kode_Perusahaan', 'k.Kode_Karyawan']);

        if ($freelance === null) {
            $this->markTestSkipped('Data dev tidak punya karyawan FREELANCE aktif.');
        }

        $cycleId = $this->siklusDraft();

        $this->service->tambahPraKecuali($cycleId, [[
            'kode_perusahaan' => $freelance->Kode_Perusahaan,
            'kode_karyawan' => $freelance->Kode_Karyawan,
        ]], 'Diputuskan HC: tidak ikut siklus ini.', 4);

        $this->gate->lupakanMemo();
        $this->service->siapkan($cycleId);

        $baris = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Kode_Karyawan', $freelance->Kode_Karyawan)
            ->first(['Status_Peserta', 'Alasan_Dikecualikan']);

        $this->assertSame('DIKECUALIKAN', $baris->Status_Peserta);
        $this->assertSame('Diputuskan HC: tidak ikut siklus ini.', $baris->Alasan_Dikecualikan);
        $this->assertStringNotContainsString('FREELANCE', (string) $baris->Alasan_Dikecualikan);
    }

    public function test_alasan_wajib(): void
    {
        $cycleId = $this->siklusDraft();
        $orang = $this->karyawanBelumDikecualikan($cycleId, 1);

        try {
            $this->service->tambahPraKecuali($cycleId, [[
                'kode_perusahaan' => $orang[0]->Kode_Perusahaan,
                'kode_karyawan' => $orang[0]->Kode_Karyawan,
            ]], '   ', 4);
            $this->fail('Pengecualian tanpa alasan seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('wajib beralasan', $e->getMessage());
        }

        $this->assertSame(0, DB::table('N_PA_Cycle_Pre_Exclusion')
            ->where('PA_Cycle_Id', $cycleId)->count(),
            'Penolakan menyisakan baris; sama saja dengan tidak menolak.');
    }

    public function test_orang_yang_sama_tidak_tercatat_dua_kali(): void
    {
        $cycleId = $this->siklusDraft();
        $orang = $this->karyawanBelumDikecualikan($cycleId, 2);

        $muatan = array_map(fn ($k) => [
            'kode_perusahaan' => $k->Kode_Perusahaan,
            'kode_karyawan' => $k->Kode_Karyawan,
        ], $orang);

        $pertama = $this->service->tambahPraKecuali($cycleId, $muatan, 'Alasan pertama.', 4);
        $this->assertSame(2, $pertama['ditambah']);

        // Dikirim ulang — bukan galat, tetapi juga tidak menggandakan.
        $kedua = $this->service->tambahPraKecuali($cycleId, $muatan, 'Alasan kedua.', 4);
        $this->assertSame(0, $kedua['ditambah']);
        $this->assertSame(2, $kedua['sudah_ada']);

        $this->assertSame(2, DB::table('N_PA_Cycle_Pre_Exclusion')
            ->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')->count());
    }

    public function test_cabut_menonaktifkan_bukan_menghapus(): void
    {
        $cycleId = $this->siklusDraft();
        $orang = $this->karyawanBelumDikecualikan($cycleId, 1);

        $this->service->tambahPraKecuali($cycleId, [[
            'kode_perusahaan' => $orang[0]->Kode_Perusahaan,
            'kode_karyawan' => $orang[0]->Kode_Karyawan,
        ]], 'Salah tulis.', 4);

        $id = (int) DB::table('N_PA_Cycle_Pre_Exclusion')
            ->where('PA_Cycle_Id', $cycleId)->value('Id_PA_Cycle_Pre_Exclusion');

        $this->service->cabutPraKecuali($cycleId, $id, 4);

        /* Barisnya HARUS masih ada. Baris yang dihapus membuat "kenapa dia dinilai padahal
           dulu dikecualikan" mustahil dijawab. */
        $baris = DB::table('N_PA_Cycle_Pre_Exclusion')
            ->where('Id_PA_Cycle_Pre_Exclusion', $id)
            ->first(['Flag_Aktif', 'Alasan', 'Updated_By']);

        $this->assertNotNull($baris, 'Baris terhapus; seharusnya hanya dinonaktifkan.');
        $this->assertSame('T', $baris->Flag_Aktif);
        $this->assertSame('Salah tulis.', $baris->Alasan, 'Alasannya harus tetap terbaca.');
        $this->assertSame(4, (int) $baris->Updated_By);

        // Dan ia tidak lagi berpengaruh pada pratinjau.
        $this->assertSame(0, $this->service->pratinjauPenyiapan($cycleId)['pra_kecuali_terpakai']);
    }

    public function test_cabut_baris_milik_siklus_lain_ditolak(): void
    {
        /* Kekeliruan paling lazim di sini: mencabut baris milik siklus lain. Tanpa
           pemeriksaan siklusnya, satu id yang salah ketik mencabut pengecualian pada siklus
           yang sama sekali tidak sedang dibuka. */
        $a = $this->siklusDraft();
        $b = $this->siklusDraft();
        $orang = $this->karyawanBelumDikecualikan($a, 1);

        $this->service->tambahPraKecuali($a, [[
            'kode_perusahaan' => $orang[0]->Kode_Perusahaan,
            'kode_karyawan' => $orang[0]->Kode_Karyawan,
        ]], 'Milik siklus A.', 4);

        $id = (int) DB::table('N_PA_Cycle_Pre_Exclusion')
            ->where('PA_Cycle_Id', $a)->value('Id_PA_Cycle_Pre_Exclusion');

        try {
            $this->service->cabutPraKecuali($b, $id, 4);
            $this->fail('Mencabut baris milik siklus lain seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tidak ada pada siklus', $e->getMessage());
        }

        $this->assertSame('Y', DB::table('N_PA_Cycle_Pre_Exclusion')
            ->where('Id_PA_Cycle_Pre_Exclusion', $id)->value('Flag_Aktif'));
    }

    /* ------------------------------------------------------------------ *
     * Penyiapan                                                           *
     * ------------------------------------------------------------------ */

    private function siklusDraft(): int
    {
        $kode = 'UJI-PK-'.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => $kode,
            'Nama_Cycle' => 'Siklus Uji PraKecuali',
            'Tahun' => 2026,
            'Status_Cycle' => 'DRAFT',
            'Periode_Dinilai_Dari' => '2026-01-01',
            'Periode_Dinilai_Sampai' => '2026-06-30',
        ]);

        $this->gate->lupakanMemo();

        return (int) DB::table('N_PA_Cycle')->where('Kode_Cycle', $kode)->value('Id_PA_Cycle');
    }

    /**
     * Karyawan yang BELUM dikecualikan aturan otomatis mana pun.
     *
     * Memakai orang yang kebetulan FREELANCE atau sudah resign membuat cacah "diikutkan"
     * tidak bergeser saat ia ditambahkan — dan ujinya lulus tanpa membuktikan apa pun.
     *
     * @return list<object>
     */
    private function karyawanBelumDikecualikan(int $cycleId, int $jumlah): array
    {
        $orang = DB::table('Karyawan as k')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as v',
                'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')

            /* Levelnya harus BENAR-BENAR dinilai, bukan sekadar bukan FREELANCE.
               Sejak pengecualian level jadi data yang disunting admin (DDL-2026-091),
               memilih orang dari level yang dikecualikan membuat pratinjau tidak bergeser
               sebanyak yang ditambahkan - dan ujinya gagal dengan selisih satu yang tidak
               ada hubungannya dengan pengecualian awal yang sedang diuji. */
            ->whereNotIn('v.ID_Level', PaLevelDikecualikan::daftar() ?: [0])
            ->orderBy('k.Kode_Karyawan')
            ->limit($jumlah)
            ->get(['k.Kode_Perusahaan', 'k.Kode_Karyawan'])
            ->all();

        $this->assertCount($jumlah, $orang, 'Data dev tidak punya cukup karyawan yang layak dinilai.');

        return $orang;
    }
}
