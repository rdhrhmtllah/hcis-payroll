<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Katalog\PaLevelDikecualikan;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaCyclePreparationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pratinjau penyiapan.
 *
 * DUA HAL YANG DIJAGA, DAN YANG KEDUA LEBIH PENTING
 *
 * 1. Pratinjau tidak menulis apa pun. Perkakas yang dijalankan orang justru ketika sedang
 *    ragu tidak boleh mengubah keadaan.
 *
 * 2. **Pratinjau tidak boleh berbohong.** Angkanya harus SAMA PERSIS dengan yang dihasilkan
 *    penyiapan sungguhan — kalau tidak, orang mengambil keputusan berdasarkan angka yang
 *    keliru, dan itu lebih berbahaya daripada tidak ada pratinjau sama sekali.
 *
 * Uji kedua itulah yang membuat berkas ini ada. Menguji pratinjau sendirian hanya
 * membuktikan ia menghasilkan angka, bukan bahwa angkanya benar.
 */
class PratinjauPenyiapanTest extends TestCase
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
        /* Dirakit container — daftar argumen harfiah mati sebagai ArgumentCountError
           begitu servicenya menerima ketergantungan baru. */
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

    public function test_pratinjau_tidak_menulis_apa_pun(): void
    {
        $cycleId = $this->siklusDraft();

        $sebelum = $this->cacahTabelPa();
        $this->service->pratinjauPenyiapan($cycleId);
        $sesudah = $this->cacahTabelPa();

        $this->assertSame(
            $sebelum,
            $sesudah,
            'Pratinjau menulis ke basis data. Ia dijalankan orang justru ketika sedang ragu; '
            .'tulisan tak terduga di saat itu paling mahal.'
        );
    }

    public function test_angka_pratinjau_sama_dengan_penyiapan_sungguhan(): void
    {
        /* INI penjaga terpenting di berkas ini.
           Pratinjau memakai `nilaiKelayakan()` yang sama dengan penyiapan, tetapi harus
           MERAMALKAN salinan aturan per level — salinan itu belum ada sebelum penyiapan
           berjalan. Ramalan yang meleset menghasilkan pratinjau yang berbohong, dan
           kebohongannya tidak akan pernah menampakkan diri sebagai galat. */
        $cycleId = $this->siklusDraft();

        $pratinjau = $this->service->pratinjauPenyiapan($cycleId);

        $this->gate->lupakanMemo();
        $this->service->siapkan($cycleId);

        $nyata = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->groupBy('Status_Peserta')
            ->selectRaw('Status_Peserta as s, COUNT(*) as n')
            ->pluck('n', 's');

        $this->assertSame(
            $pratinjau['cacah']['diikutkan'],
            (int) ($nyata['DIIKUTKAN'] ?? 0),
            'Jumlah DIIKUTKAN pada pratinjau berbeda dari kenyataan.'
        );
        /* DUA angka pratinjau berbanding SATU status di basis data.
           `Status_Peserta` hanya mengenal DIKECUALIKAN; yang membedakan asalnya cuma ada di
           pratinjau. Karena itu yang dibandingkan jumlah keduanya — membandingkan salah
           satunya saja akan merah untuk alasan yang salah. */
        $this->assertSame(
            $pratinjau['cacah']['dikecualikan'] + $pratinjau['cacah']['dikecualikan_otomatis'],
            (int) ($nyata['DIKECUALIKAN'] ?? 0),
            'Jumlah DIKECUALIKAN pada pratinjau berbeda dari kenyataan.'
        );
        $this->assertSame(
            $pratinjau['cacah']['tertunda'],
            (int) ($nyata['TERTUNDA'] ?? 0),
            'Jumlah TERTUNDA pada pratinjau berbeda dari kenyataan.'
        );

        // PENJAGA KERJA-NYATA: nol berbanding nol juga "sama". Kalau penyiapannya tidak
        // membentuk siapa pun, uji di atas lulus tanpa membuktikan apa pun.
        $this->assertGreaterThan(
            0,
            $pratinjau['cacah']['diikutkan'],
            'Pratinjau tidak memperkirakan seorang pun diikutkan; ujinya tidak menguji apa pun.'
        );
    }

    public function test_level_tanpa_aturan_terdeteksi_sebelum_penyiapan(): void
    {
        /* Inilah gunanya pratinjau. Level STAFF dimatikan aturannya, dan orang-orangnya
           AKAN mendarat di keranjang TERTUNDA — tetapi tanpa pratinjau itu baru ketahuan
           sesudah 958 baris terlanjur terbentuk dan siklus menolak dikunci. */
        DB::table('N_PA_Rater_Policy')->where('ID_Level', 2)->update(['Flag_Aktif' => 'T']);

        $cycleId = $this->siklusDraft();
        $pratinjau = $this->service->pratinjauPenyiapan($cycleId);

        $this->assertGreaterThan(0, $pratinjau['cacah']['tertunda']);

        $level = array_column($pratinjau['level_tanpa_aturan'], 'id_level');
        $this->assertContains(2, $level, 'Level yang aturannya dimatikan harus dilaporkan.');

        // Jumlah orangnya ikut disebut — "level 2 tidak punya aturan" tanpa angka tidak
        // memberi tahu apakah itu menyangkut tiga orang atau tiga ratus.
        foreach ($pratinjau['level_tanpa_aturan'] as $l) {
            $this->assertGreaterThan(0, $l['jumlah_karyawan']);
        }

        $this->assertStringContainsString('TERTUNDA', $pratinjau['catatan']);
    }

    /*
     | ANGKA "DIKECUALIKAN" DI LANGKAH 4 HANYA MENGHITUNG KEPUTUSAN ADMIN.
     |
     | Sebelum 7 September 2026 ia satu angka yang menggabungkan pengecualian aturan tetap
     | (level FREELANCE, karyawan yang mundur sebelum periode berakhir) dengan pengecualian
     | yang ditetapkan admin. Pada data sungguhan itu berarti admin yang mengecualikan DUA
     | orang membaca angka 101 — dan yang terbaca olehnya bukan "aturan bekerja", melainkan
     | "saya tidak sengaja mengeluarkan 99 orang".
     |
     | Pemisahan yang sama sudah berlaku di kartu siklus, dashboard, dan ringkasan pra-kunci;
     | pratinjau Langkah 4 satu-satunya yang tertinggal.
     */
    public function test_pengecualian_admin_dipisah_dari_pengecualian_aturan(): void
    {
        $cycleId = $this->siklusDraft();

        $awal = $this->service->pratinjauPenyiapan($cycleId);

        /* Pada basis data pengembangan, pengecualian OTOMATIS memang ada (level FREELANCE
           dan karyawan yang sudah mundur). Tanpa itu uji ini tidak menguji apa pun: nol
           tidak bisa dibedakan dari nol. */
        $this->assertGreaterThan(
            0,
            $awal['cacah']['dikecualikan_otomatis'],
            'Data uji tidak punya pengecualian otomatis; pemisahannya tak terbukti.'
        );

        // Belum ada keputusan admin, jadi angkanya harus NOL - bukan seratus sekian.
        $this->assertSame(0, $awal['cacah']['dikecualikan']);
    }

    public function test_sebab_dikelompokkan_bukan_didaftar_per_orang(): void
    {
        $cycleId = $this->siklusDraft();
        $pratinjau = $this->service->pratinjauPenyiapan($cycleId);

        $this->assertNotEmpty($pratinjau['sebab_dikecualikan']);

        /* Kelompoknya harus JAUH lebih sedikit daripada orangnya. Sembilan puluh sembilan
           baris "Level FREELANCE dikecualikan" menenggelamkan satu baris yang benar-benar
           perlu dilihat — dan yang satu itulah alasan layar ini dibuka. */
        $this->assertLessThan(
            $pratinjau['cacah']['dikecualikan'] + $pratinjau['cacah']['dikecualikan_otomatis'],
            count($pratinjau['sebab_dikecualikan']),
            'Sebab tidak dikelompokkan; daftarnya sama panjang dengan jumlah orangnya.'
        );

        // Contohnya dibatasi supaya balasannya tidak membengkak jadi ratusan baris.
        $this->assertLessThanOrEqual(10, count($pratinjau['contoh']['dikecualikan']));
        $this->assertTrue($pratinjau['ini_pratinjau']);
    }

    public function test_pratinjau_ditolak_pada_siklus_yang_sudah_dikunci(): void
    {
        /* Gerbangnya sama dengan penyiapan. Pratinjau atas siklus yang sudah dikunci
           menjawab pertanyaan yang tidak lagi bisa ditindaklanjuti — dan menampilkannya
           mengundang orang mengira penyiapan masih bisa diulang. */
        $cycleId = $this->siklusDraft();
        $this->service->siapkan($cycleId);
        $this->service->kunci($cycleId, 4);

        $this->expectExceptionMessage('SUNTING_SALINAN');
        $this->service->pratinjauPenyiapan($cycleId);
    }

    public function test_pratinjau_menyebut_sudah_dijalankan_dan_perubahan_bila_diulang(): void
    {
        /* Langkah 4 dulu terus bertanya "Siap menjalankan?" pada siklus yang pesertanya sudah
           terbentuk. Pratinjau kini menyebut keadaannya: belum/sudah dijalankan, hasil nyata,
           siapa dan kapan, dan apa yang berubah bila dijalankan ULANG. */
        $cycleId = $this->siklusDraft();

        $awal = $this->service->pratinjauPenyiapan($cycleId);
        $this->assertFalse($awal['sudah_dijalankan']);
        $this->assertNull($awal['terakhir_dijalankan']);

        $this->gate->lupakanMemo();
        $this->service->siapkan($cycleId, 4);
        $this->gate->lupakanMemo();

        $sesudah = $this->service->pratinjauPenyiapan($cycleId);
        $nyata = DB::table('N_PA_Participant')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')
            ->groupBy('Status_Peserta')->selectRaw('Status_Peserta as s, COUNT(*) as n')->pluck('n', 's');

        $this->assertTrue($sesudah['sudah_dijalankan']);
        $this->assertSame((int) ($nyata['DIIKUTKAN'] ?? 0), $sesudah['terbentuk']['diikutkan']);
        $this->assertSame((int) ($nyata['DIKECUALIKAN'] ?? 0), $sesudah['terbentuk']['dikecualikan']);
        $this->assertGreaterThan(0, $sesudah['terbentuk']['diikutkan']);
        $this->assertNotNull($sesudah['terakhir_dijalankan']['pada']);
        $this->assertSame(4, (int) DB::table('N_PA_Cycle_Setup_Step')->where('PA_Cycle_Id', $cycleId)
            ->where('Kode_Langkah', 'PESERTA')->value('Disimpan_By'), 'Penyiapan tidak mencatat siapa yang menjalankannya.');
        $this->assertSame(['peserta_baru' => 0, 'level_berubah' => 0, 'level_tertahan' => 0], $sesudah['bila_diulang']);

        // Level satu peserta bergeser (mis. koreksi Level PA dicabut) → terhitung berubah, tidak tertahan.
        $p = DB::table('N_PA_Participant')->where('PA_Cycle_Id', $cycleId)->where('Status_Peserta', 'DIIKUTKAN')->first(['Id_PA_Participant', 'ID_Level_Saat_Cycle']);
        $levelLain = DB::table('HRIS_Level')->where('ID_Level', '<>', (int) $p->ID_Level_Saat_Cycle)->value('ID_Level');
        DB::table('N_PA_Participant')->where('Id_PA_Participant', $p->Id_PA_Participant)->update(['ID_Level_Saat_Cycle' => $levelLain]);

        $ulang = $this->service->pratinjauPenyiapan($cycleId);
        $this->assertSame(1, $ulang['bila_diulang']['level_berubah']);
        $this->assertSame(0, $ulang['bila_diulang']['level_tertahan']);
    }

    public function test_perubahan_sejak_penugasan_dan_segarkan_level_tertahan(): void
    {
        /* Menjalankan ulang penyiapan TIDAK menyentuh penugasan yang sudah ada. Yang tidak bisa
           diselesaikannya — peserta tanpa penugasan, Level PA tertahan, peserta yang tidak aktif
           lagi — disebut `perubahanPeserta()`, dan yang tertahan bisa dibereskan sekali jalan. */
        $cycleId = $this->siklusDraft();
        $this->service->siapkan($cycleId, 4);
        $this->gate->lupakanMemo();

        $awal = $this->service->perubahanPeserta($cycleId);
        $this->assertFalse($awal['ada_penugasan']);
        $this->assertSame([], $awal['level_tertahan']);
        $this->assertSame([], $awal['resign']);

        app(\App\Services\KPI\PerformanceAppraisal\PaAssignmentBuilderService::class)->bentukMassalCepat($cycleId, false, 3, 4);
        $p = DB::table('N_PA_Participant as p')
            ->join('N_PA_Assignment as a', 'a.PA_Participant_Id', '=', 'p.Id_PA_Participant')
            ->where('p.PA_Cycle_Id', $cycleId)->where('a.Flag_Aktif', 'Y')
            ->first(['p.Id_PA_Participant', 'p.ID_Level_Saat_Cycle', 'p.Kode_Perusahaan', 'p.Kode_Karyawan']);
        $this->assertNotNull($p, 'Penugasan uji tidak terbentuk; uji ini tidak menguji apa pun.');

        $sesudah = $this->service->perubahanPeserta($cycleId);
        $this->assertTrue($sesudah['ada_penugasan']);
        $this->assertGreaterThan(0, $sesudah['belum_berpenugasan']);

        // Level peserta berpenugasan bergeser → TERTAHAN, dan bisa disegarkan (belum ada yang diisi).
        $levelAsli = (int) $p->ID_Level_Saat_Cycle;
        $levelLain = DB::table('HRIS_Level')->where('ID_Level', '<>', $levelAsli)->value('ID_Level');
        DB::table('N_PA_Participant')->where('Id_PA_Participant', $p->Id_PA_Participant)->update(['ID_Level_Saat_Cycle' => $levelLain]);

        $tertahan = collect($this->service->perubahanPeserta($cycleId)['level_tertahan'])->firstWhere('participant_id', (int) $p->Id_PA_Participant);
        $this->assertNotNull($tertahan);
        $this->assertTrue($tertahan['bisa_disegarkan']);
        $this->assertGreaterThan(0, $tertahan['penugasan_aktif']);

        $hasil = $this->service->segarkanLevelTertahan($cycleId, [(int) $p->Id_PA_Participant], 4);
        $this->assertSame(1, $hasil['disegarkan']);
        $this->assertSame($tertahan['penugasan_aktif'], $hasil['penugasan_dibatalkan']);
        $this->assertSame($levelAsli, (int) DB::table('N_PA_Participant')->where('Id_PA_Participant', $p->Id_PA_Participant)->value('ID_Level_Saat_Cycle'));
        $this->assertFalse(DB::table('N_PA_Assignment')->where('PA_Participant_Id', $p->Id_PA_Participant)->where('Flag_Aktif', 'Y')->exists());

        // Karyawan peserta tidak aktif lagi di HRIS → disebut sebagai resign; menjalankan ulang tidak mengeluarkannya.
        DB::table('Karyawan')->where('Kode_Perusahaan', $p->Kode_Perusahaan)->where('Kode_Karyawan', $p->Kode_Karyawan)->update(['Aktif' => 'T']);
        $resign = collect($this->service->perubahanPeserta($cycleId)['resign'])->firstWhere('participant_id', (int) $p->Id_PA_Participant);
        $this->assertSame('Tidak aktif lagi di HRIS', $resign['keterangan'] ?? null);
    }

    public function test_cabut_pengecualian_mengikutkan_kembali_peserta_yang_sudah_terbentuk(): void
    {
        /* Sampai 13 Okt: tombol cabut tidak pernah tampil (field `dikecualikan_saat_ini` tidak
           dikirim), dan pencabutan hanya menyentuh daftar — peserta yang sudah terbentuk tetap
           DIKECUALIKAN tanpa jalan kembali dari layar mana pun. */
        $cycleId = $this->siklusDraft();
        $this->service->siapkan($cycleId, 4);
        $this->gate->lupakanMemo();

        $dua = DB::table('N_PA_Participant as p')
            ->join('Karyawan as k', function ($j) {
                $j->on('k.Kode_Perusahaan', '=', 'p.Kode_Perusahaan')->on('k.Kode_Karyawan', '=', 'p.Kode_Karyawan');
            })
            ->where('p.PA_Cycle_Id', $cycleId)->where('p.Status_Peserta', 'DIIKUTKAN')->where('k.Aktif', 'Y')
            ->orderBy('p.Id_PA_Participant')->limit(2)
            ->get(['p.Id_PA_Participant', 'p.Kode_Perusahaan', 'p.Kode_Karyawan']);
        $this->assertCount(2, $dua, 'Siklus uji butuh dua peserta diikutkan.');
        [$a, $b] = [$dua[0], $dua[1]];

        $this->service->tambahPraKecuali($cycleId, [
            ['kode_perusahaan' => $a->Kode_Perusahaan, 'kode_karyawan' => $a->Kode_Karyawan],
            ['kode_perusahaan' => $b->Kode_Perusahaan, 'kode_karyawan' => $b->Kode_Karyawan],
        ], 'Cuti panjang (uji).', 4);
        $this->gate->lupakanMemo();

        $daftar = collect($this->service->daftarPraKecuali($cycleId))->keyBy('kode_karyawan');
        $this->assertTrue($daftar[$a->Kode_Karyawan]['dikecualikan_saat_ini']);

        /* A meniru orang yang dikecualikan SEJAK PERTAMA dibentuk: penyiapan tidak mengisi
           penunjuk aturannya (UAT 13 Okt, Iqbal K32 siklus 14916 — ditolak "level belum punya
           aturan" padahal levelnya punya). */
        DB::table('N_PA_Participant')->where('Id_PA_Participant', $a->Id_PA_Participant)->update(['PA_Cycle_Rater_Policy_Snapshot_Id' => null]);

        // B dikecualikan ulang atas sebab LAIN — pencabutan baris daftarnya tidak boleh mengembalikannya.
        DB::table('N_PA_Participant')->where('Id_PA_Participant', $b->Id_PA_Participant)->update(['Alasan_Dikecualikan' => 'Resign (uji).']);

        $hasilA = $this->service->cabutPraKecuali($cycleId, (int) $daftar[$a->Kode_Karyawan]['id'], 4);
        $this->assertTrue($hasilA['peserta_diikutkan']);
        $pesertaA = DB::table('N_PA_Participant')->where('Id_PA_Participant', $a->Id_PA_Participant)->first();
        $this->assertSame('DIIKUTKAN', $pesertaA->Status_Peserta);
        $this->assertNull($pesertaA->Alasan_Dikecualikan);
        $this->assertNotNull($pesertaA->PA_Cycle_Rater_Policy_Snapshot_Id, 'Penunjuk aturan tidak diisi saat diikutkan kembali.');

        $this->gate->lupakanMemo();
        $hasilB = $this->service->cabutPraKecuali($cycleId, (int) $daftar[$b->Kode_Karyawan]['id'], 4);
        $this->assertFalse($hasilB['peserta_diikutkan']);
        $this->assertSame('DIKECUALIKAN', DB::table('N_PA_Participant')->where('Id_PA_Participant', $b->Id_PA_Participant)->value('Status_Peserta'));

        $this->assertSame([], $this->service->daftarPraKecuali($cycleId));
    }

    /* ------------------------------------------------------------------ *
     * Penyiapan                                                           *
     * ------------------------------------------------------------------ */

    private function siklusDraft(): int
    {
        $kode = 'UJI-PRAT-'.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => $kode,
            'Nama_Cycle' => 'Siklus Uji Pratinjau',
            'Tahun' => 2026,
            'Status_Cycle' => 'DRAFT',
            'Periode_Dinilai_Dari' => '2026-01-01',
            'Periode_Dinilai_Sampai' => '2026-06-30',
        ]);

        $this->gate->lupakanMemo();

        return (int) DB::table('N_PA_Cycle')->where('Kode_Cycle', $kode)->value('Id_PA_Cycle');
    }

    /** @return array<string, int> */
    private function cacahTabelPa(): array
    {
        $tabel = collect(DB::select(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME LIKE 'N[_]PA[_]%'
             ORDER BY TABLE_NAME"
        ))->pluck('TABLE_NAME');

        $this->assertGreaterThan(20, $tabel->count(), 'Pemindaian tabel PA gagal.');

        $cacah = [];

        foreach ($tabel as $t) {
            $cacah[$t] = (int) DB::table($t)->count();
        }

        return $cacah;
    }
}
