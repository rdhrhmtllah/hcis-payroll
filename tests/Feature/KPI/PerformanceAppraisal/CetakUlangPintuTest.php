<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\PerformanceAppraisal\PaHcLearnScheduleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;

/**
 * Menerbitkan ULANG kode akses satu penugasan — `extend-time` dalam kontrak HC Learn.
 *
 * ── Bentuknya berubah 10 September 2026 ───────────────────────────────────────
 *
 * Dulu pencetakan ulang berarti MEMBUAT BARIS BARU dan menonaktifkan yang lama, karena
 * tokennya kita karang sendiri. Kontrak HC Learn tidak bekerja begitu: `extend-time`
 * menerbitkan kode baru pada pintu yang SUDAH ada, `api_ref`-nya tidak berubah, dan kode
 * lamanya mati seketika. Membuat baris baru berarti menjadwalkan ulang — dan itu
 * menghasilkan pintu KEDUA untuk penugasan yang sama.
 *
 * Karena itu yang ditegaskan berkas ini juga berubah: barisnya tetap SATU dan api_ref-nya
 * tetap, sementara short_token dan OTP-nya yang berganti.
 *
 * ── Yang tetap dijaga ─────────────────────────────────────────────────────────
 *
 * Bukan penerbitannya, melainkan APA YANG TIDAK BOLEH IKUT TERJADI: jawaban orang yang
 * terbuang karena kodenya diterbitkan ulang sesudah ia menyetorkannya, dan penerbitan atas
 * siklus yang sudah ditutup.
 *
 * HC Learn DIPALSUKAN di tingkat HTTP, bukan dilewati lewat cabang khusus uji: yang teruji
 * dengan begitu adalah jalur yang benar-benar dipakai production, termasuk penandatanganan
 * dan pembacaan balasannya.
 *
 * Uji ini menulis HANYA ke tabel berawalan `N_PA_`, dan karyawannya diambil dari data
 * sungguhan karena FK ke `Karyawan` melarang kode karangan.
 */
class CetakUlangPintuTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleId;

    private int $participantId;

    private int $assignmentId;

    private int $paketId;

    private int $levelId;

    private int $kategoriId;

    private string $apiRef;

    protected function setUp(): void
    {
        parent::setUp();

        $stok = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->limit(2)
            ->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan'])
            ->all();

        $this->assertCount(2, $stok, 'Uji ini butuh dua karyawan aktif yang berbeda.');

        $this->levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $this->kategoriId = (int) DB::table('N_PA_Rater_Category')
            ->where('Kode_Kategori', 'ATASAN')
            ->value('Id_PA_Rater_Category');

        $this->cycleId = $this->buatSiklus('OPEN');
        $this->paketId = $this->buatPaket();
        $this->participantId = $this->buatPeserta($stok[0]);
        $this->assignmentId = $this->buatPenugasan($stok[1]);
        /* Permintaan ke HC Learn ditandatangani dengan identitas pengguna yang sedang masuk;
           tanpa `actingAs` ia berhenti di "sambungan belum tersedia" sebelum sempat menyentuh
           HTTP palsu di bawah. */
        $this->actingAs($this->penggunaBerkunci());

        $this->apiRef = bin2hex(random_bytes(16));
        $this->buatIntegrasi($this->apiRef);

        /* Fake TIDAK didaftarkan di sini. `Http::fake()` menumpuk stub dan yang MENANG adalah
           yang pertama terdaftar, bukan yang terakhir — jadi fake milik setUp akan menelan
           setiap uji yang ingin menjawab berbeda, dan uji itu diam-diam mengukur jawaban
           setUp. Tiap uji mendaftarkan jawabannya sendiri, sekali. */
    }

    /**
     * Balasan HC Learn untuk `extend-time` dan `update`.
     *
     * Bentuknya disalin dari dokumentasi mereka: kode BARU di akarnya, kode lama di
     * `kode_lama` supaya pesan yang telanjur dikirim ke penilai bisa dicabut.
     */
    private function penggunaBerkunci(): User
    {
        $pengguna = User::whereNotNull('User_Key')->first();

        $this->assertNotNull($pengguna, 'Tidak ada pengguna ber-User_Key; permintaan tidak bisa ditandatangani.');

        return $pengguna;
    }

    /**
     * @param  string|null  $link  tautan yang dipulangkan `extend-time`; null = jalur normal
     */
    private function palsukanHcLearn(string $short = 'EVOBARU1', string $otp = 'OTPBARU1', ?string $link = null): void
    {
        /* SATU pendaftaran fake per uji. Memanggil `Http::fake()` dua kali dengan pola yang
           sama membuat yang menang bukan yang terakhir didaftarkan — dan uji lalu mengukur
           jawaban milik setUp, bukan jawaban yang sedang diuji. */
        Http::fake([
            '*/api/v1/extend-time/penjadwalan/*' => Http::response([
                'success' => true,
                'status' => 200,
                'result' => [
                    'api_ref' => $this->apiRef,
                    'waktu_mulai' => '2026-09-01 08:00:00',
                    'waktu_akhir' => '2026-09-30 17:00:00',
                    'akses_otp' => $otp,
                    'short_token' => $short,
                    'link_ujian' => $link ?? "https://hclearn.example.com/evo/rekrutmen?wo_aut={$short}&otp={$otp}",
                    'kode_lama' => ['akses_otp' => 'OTPLAMA1', 'short_token' => 'EVOLAMA1'],
                ],
            ]),
            '*/api/v1/pa/update/penjadwalan/*' => Http::response([
                'success' => true,
                'status' => 200,
                'result' => ['api_ref' => $this->apiRef, 'nama_ujian' => 'Ujian sesudah disusun ulang'],
            ]),
        ]);
    }

    /* ------------------------------------------------------------------ *
     * Jalan yang benar                                                    *
     * ------------------------------------------------------------------ */

    public function test_kode_baru_terbit_pada_pintu_yang_sama(): void
    {
        $this->palsukanHcLearn();

        $lama = $this->integrasiAktif();

        $hasil = $this->service()->cetakUlangPintu(
            $this->assignmentId,
            'Penilai gagal masuk: jadwalnya telanjur lewat.',
            1,
        );

        /* api_ref TIDAK berubah - itulah identitas yang dipegang HC Learn, dan seluruh
           endpoint token mereka menerimanya. Yang berganti kodenya. */
        $this->assertSame($this->apiRef, $hasil['api_ref']);
        $this->assertSame('EVOBARU1', $hasil['short_token']);
        $this->assertSame('OTPBARU1', $hasil['akses_otp']);
        $this->assertSame('EVOLAMA1', $hasil['kode_lama']['short_token']);

        /* TEPAT SATU pintu aktif, dan barisnya yang ITU JUGA. Baris kedua berarti
           penjadwalan ulang - pintu kedua untuk penugasan yang sama. */
        $this->assertSame(1, $this->jumlahPintuAktif());

        $baru = $this->integrasiAktif();
        $this->assertSame($lama->Id_PA_Assignment_Integration, $baru->Id_PA_Assignment_Integration);
        $this->assertSame($this->apiRef, $baru->Api_Ref);
        $this->assertSame('EVOBARU1', $baru->Short_Token);
        $this->assertSame('OTPBARU1', $baru->Akses_Otp);
    }

    /**
     * TAUTAN HASIL CETAK ULANG HARUS BISA DIBUKA.
     *
     * HC Learn memulangkan JALUR yang berbeda pada dua endpoint mereka untuk pintu yang sama:
     * `status` penjadwalan memberi `/evo/rekrutmen`, `extend-time` memberi `/evo/third-party`.
     * Token dan OTP-nya identik; yang kedua menjawab "tidak ditemukan" saat dibuka penilai.
     *
     * Akibatnya sunyi: pencetakan ulang BERHASIL, kode barunya sah, tetapi tautan yang
     * tersimpan mati. Penilai mengklik dan melihat halaman tak ditemukan; admin menyimpulkan
     * pencetakannya gagal, padahal yang gagal hanya alamatnya. Terjadi 11 September 2026 pada
     * penugasan SELF Ridho di siklus PA-2026-03, dan dibuktikan dengan menyadap jawaban mentah
     * kedua endpoint.
     *
     * CARA MEMBUATNYA MERAH: simpan `link_ujian` dari `extend-time` apa adanya.
     */
    public function test_tautan_cetak_ulang_memakai_jalur_yang_terbukti_hidup(): void
    {
        /* Bentuk jawaban HC Learn DISALIN dari yang sungguhan: jalurnya berbeda dari tautan
           yang tersimpan, query-nya membawa kode baru. */
        $this->palsukanHcLearn(
            'EVOK5NCC',
            'OSP7H13U',
            'https://hclearn.example.com/evo/third-party?wo_aut=EVOK5NCC&otp=OSP7H13U',
        );

        $lama = $this->integrasiAktif();
        $this->assertStringContainsString('/evo/rekrutmen', (string) $lama->Link_Ujian,
            'Prasyarat uji gagal: tautan awalnya bukan jalur yang terbukti hidup.');

        $hasil = $this->service()->cetakUlangPintu($this->assignmentId, 'Tautan penilai tidak bisa dibuka.', 1);

        /* YANG DIPULANGKAN KE LAYAR sama dengan yang tersimpan. Sampai 11 September 2026
           keduanya berbeda sumber: kolomnya sudah diperbaiki, tetapi dialog cetak ulang
           menampilkan tautan MENTAH dari HC Learn — jadi admin mengklik tautan yang berbunyi
           "Tautan Tidak Valid" tepat sesudah menekan tombolnya. */
        $this->assertStringContainsString('/evo/rekrutmen', (string) $hasil['link_ujian']);
        $this->assertStringNotContainsString('/evo/third-party', (string) $hasil['link_ujian']);

        $baris = $this->integrasiAktif();

        $this->assertSame($hasil['link_ujian'], (string) $baris->Link_Ujian,
            'Tautan yang dilihat admin berbeda dari yang tersimpan.');

        /* JALURNYA yang lama — itulah yang terbukti bisa dibuka. */
        $this->assertStringContainsString('/evo/rekrutmen', (string) $baris->Link_Ujian);
        $this->assertStringNotContainsString('/evo/third-party', (string) $baris->Link_Ujian);

        /* KODENYA yang baru — kalau tidak, penilai membuka tautan hidup memakai kode mati. */
        $this->assertStringContainsString('wo_aut=EVOK5NCC', (string) $baris->Link_Ujian);
        $this->assertStringContainsString('otp=OSP7H13U', (string) $baris->Link_Ujian);
        $this->assertSame('EVOK5NCC', $baris->Short_Token);
        $this->assertSame('OSP7H13U', $baris->Akses_Otp);
    }

    /**
     * Bila jalurnya SUDAH SAMA, tautan mereka dipakai apa adanya — penambalan ini harus
     * berhenti bekerja sendiri begitu HC Learn membetulkan endpoint-nya.
     */
    public function test_tautan_dipakai_apa_adanya_bila_jalurnya_sudah_benar(): void
    {
        $this->palsukanHcLearn();

        $this->service()->cetakUlangPintu($this->assignmentId, 'Penilai berganti perangkat.', 1);

        $this->assertSame(
            'https://hclearn.example.com/evo/rekrutmen?wo_aut=EVOBARU1&otp=OTPBARU1',
            (string) $this->integrasiAktif()->Link_Ujian,
        );
    }

    public function test_paket_sel_yang_berubah_disusun_ulang_lebih_dulu(): void
    {
        $this->palsukanHcLearn();

        /* Justru inilah gunanya. Ketika paket sel disalin ulang, baris integrasi masih
           menunjuk paket yang keliru - menerbitkan kode baru tanpa menyusun ulang alat
           tesnya akan memberi penilai tautan segar menuju soal yang sudah tidak berlaku. */
        $paketSalinanUlang = $this->gantiPaketSel();

        $this->service()->cetakUlangPintu($this->assignmentId, 'paket disalin ulang', 1);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/v1/pa/update/penjadwalan/'.$this->apiRef));

        $this->assertSame($paketSalinanUlang, (int) $this->integrasiAktif()->PA_Cycle_Question_Package_Id);
    }

    public function test_sebab_dan_kode_lamanya_tercatat_pada_jejak(): void
    {
        $this->palsukanHcLearn();

        $this->service()->cetakUlangPintu($this->assignmentId, 'Token lama rusak di sisi HC Learn.', 1);

        $jejak = json_decode($this->integrasiAktif()->Payload_Json, true);
        $catatan = $jejak['cetak_ulang'][0] ?? null;

        /* Tanpa ini, pertanyaan "kenapa orang ini menerima kode kedua" tidak punya jawaban -
           dan kode lamanya tidak bisa dicabut dari pesan yang telanjur dikirim. */
        $this->assertNotNull($catatan, 'Jejak penerbitan ulang tidak tercatat.');
        $this->assertSame('Token lama rusak di sisi HC Learn.', $catatan['sebab']);
        $this->assertArrayHasKey('kode_lama', $catatan);
    }

    /* ------------------------------------------------------------------ *
     * Yang ditolak                                                        *
     * ------------------------------------------------------------------ */

    public function test_penugasan_yang_sudah_selesai_ditolak(): void
    {
        DB::table('N_PA_Assignment')
            ->where('Id_PA_Assignment', $this->assignmentId)
            ->update(['Status_Penugasan' => 'SELESAI']);

        $this->tolak(
            fn () => $this->service()->cetakUlangPintu($this->assignmentId, 'terlambat', 1),
            'sudah menyetorkan pekerjaannya',
        );

        $this->assertSame(1, $this->jumlahPintuAktif());
    }

    public function test_penugasan_yang_menunggu_verifikasi_ditolak(): void
    {
        // Lebih halus daripada SELESAI: penilainya sudah mengirim, dan pekerjaannya sedang
        // diperiksa pihak lain. Mencetak ulang membuang pekerjaan itu di tengah jalan.
        DB::table('N_PA_Assignment')
            ->where('Id_PA_Assignment', $this->assignmentId)
            ->update(['Status_Penugasan' => 'MENUNGGU_VERIFIKASI']);

        $this->tolak(
            fn () => $this->service()->cetakUlangPintu($this->assignmentId, 'terlambat', 1),
            'sudah menyetorkan pekerjaannya',
        );
    }

    public function test_penugasan_yang_dibatalkan_ditolak(): void
    {
        DB::table('N_PA_Assignment')
            ->where('Id_PA_Assignment', $this->assignmentId)
            ->update(['Status_Penugasan' => 'DIBATALKAN']);

        $this->tolak(
            fn () => $this->service()->cetakUlangPintu($this->assignmentId, 'apa saja', 1),
            'sudah dibatalkan',
        );
    }

    public function test_peserta_yang_dikecualikan_ditolak(): void
    {
        DB::table('N_PA_Participant')
            ->where('Id_PA_Participant', $this->participantId)
            ->update(['Status_Peserta' => 'DIKECUALIKAN', 'Alasan_Dikecualikan' => 'resign']);

        $this->tolak(
            fn () => $this->service()->cetakUlangPintu($this->assignmentId, 'apa saja', 1),
            'tidak diikutkan',
        );
    }

    public function test_hasil_yang_sudah_terbit_ditolak(): void
    {
        DB::table('N_PA_Result')->insert([
            'PA_Participant_Id' => $this->participantId,
            'Final_Score' => 88.0,
            'Kode_Grade_Final' => 'A',
            'Kode_Grade_Sistem' => 'A',
            'Status_Hasil' => 'TERBIT',
        ]);

        $this->tolak(
            fn () => $this->service()->cetakUlangPintu($this->assignmentId, 'terlambat', 1),
            'sudah TERBIT',
        );
    }

    public function test_wajib_beralasan(): void
    {
        $this->tolak(
            fn () => $this->service()->cetakUlangPintu($this->assignmentId, '   ', 1),
            'wajib beralasan',
        );

        // Penolakan tidak boleh menyentuh pintu yang ada.
        $this->assertSame(1, $this->jumlahPintuAktif());
    }

    public function test_siklus_yang_sudah_ditutup_menolak(): void
    {
        /* CLOSED dan PUBLISHED ditutup: di sana admin sedang memverifikasi atau angkanya
           sudah dilihat orang, dan mengubah masukannya membatalkan yang sedang dipandang. */
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
            ->update(['Status_Cycle' => 'CLOSED']);

        $this->tolak(
            fn () => $this->service()->cetakUlangPintu($this->assignmentId, 'terlambat', 1),
            'tidak diizinkan',
        );
    }

    /* ------------------------------------------------------------------ *
     * Bangunan uji                                                        *
     * ------------------------------------------------------------------ */

    private function tolak(callable $aksi, string $frasa): void
    {
        try {
            $aksi();
            $this->fail("Seharusnya ditolak dengan menyebut '{$frasa}'.");
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam `try`
            // ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat frasa yang
            // ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($frasa, $e->getMessage());
        }
    }

    private function service(): PaHcLearnScheduleService
    {
        return $this->app->make(PaHcLearnScheduleService::class);
    }

    private function jumlahPintuAktif(): int
    {
        return (int) DB::table('N_PA_Assignment_Integration')
            ->where('PA_Assignment_Id', $this->assignmentId)
            ->where('Flag_Aktif', 'Y')
            ->count();
    }

    private function integrasiAktif(): object
    {
        $baris = DB::table('N_PA_Assignment_Integration')
            ->where('PA_Assignment_Id', $this->assignmentId)
            ->where('Flag_Aktif', 'Y')
            ->first();

        $this->assertNotNull($baris, 'Penugasan uji kehilangan pintu aktifnya.');

        return $baris;
    }

    private function buatSiklus(string $status): int
    {
        $nama = 'Siklus Uji Cetak Ulang '.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => 'UJI-CETAK-'.substr(bin2hex(random_bytes(4)), 0, 8),
            'Nama_Cycle' => $nama,
            'Tahun' => (int) date('Y'),
            'Status_Cycle' => $status,

            /* Jendela WAJIB ada: HC Learn menuntut Waktu_Mulai dan Waktu_Akhir pada setiap
               penjadwalan maupun perpanjangan, dan siklus tanpa jendela ditolak sebelum
               permintaannya berangkat. */
            'Pengisian_Dari' => now()->subDay()->format('Y-m-d H:i:s'),
            'Pengisian_Sampai' => now()->addDays(20)->format('Y-m-d H:i:s'),
        ]);

        return (int) DB::table('N_PA_Cycle')->where('Nama_Cycle', $nama)->value('Id_PA_Cycle');
    }

    /**
     * Paket untuk SEL (level, kategori) penugasan uji.
     *
     * `UX_PA_CycleQPkg_Cycle_Level_Rater` melarang dua paket pada sel yang sama, dan justru
     * batasan itulah yang membentuk rancangan operasinya: tidak ada "paket lain" untuk
     * dituju, yang ada hanyalah paket sel ini yang disalin ulang.
     */
    private function buatPaket(): int
    {
        $kode = 'SIM-UJI-CETAK-'.substr(bin2hex(random_bytes(3)), 0, 6);

        DB::table('N_PA_Cycle_Question_Package')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'ID_Level' => $this->levelId,
            'PA_Rater_Category_Id' => $this->kategoriId,
            'Id_Master_Ujian' => $kode,
            'Nama_Ujian_Sumber' => 'Paket Uji Cetak Ulang',
            'Sumber_Kontrak' => 'SIMULASI',
            'Flag_Aktif' => 'Y',
            'Snapshot_At' => now()->format('Y-m-d H:i:s'),
        ]);

        return (int) DB::table('N_PA_Cycle_Question_Package')
            ->where('Id_Master_Ujian', $kode)
            ->value('Id_PA_Cycle_Question_Package');
    }

    /**
     * Menyalin ulang paket sel: yang lama dinonaktifkan, yang baru menggantikannya.
     *
     * Indeks uniknya TIDAK bersyarat `Flag_Aktif`, jadi baris lama harus benar-benar keluar
     * dari sel itu. Dipindahkan ke kategori penilai lain — cara yang sama dipakai kode
     * sungguhan ketika paket disalin ulang tanpa membuang jejak yang lama.
     */
    private function gantiPaketSel(): int
    {
        $kategoriLain = (int) DB::table('N_PA_Rater_Category')
            ->where('Id_PA_Rater_Category', '<>', $this->kategoriId)
            ->orderBy('Id_PA_Rater_Category')
            ->value('Id_PA_Rater_Category');

        DB::table('N_PA_Cycle_Question_Package')
            ->where('Id_PA_Cycle_Question_Package', $this->paketId)
            ->update(['PA_Rater_Category_Id' => $kategoriLain, 'Flag_Aktif' => 'T']);

        return $this->buatPaket();
    }

    private function buatPeserta(object $karyawan): int
    {
        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'Kode_Perusahaan' => $karyawan->Kode_Perusahaan,
            'Kode_Karyawan' => $karyawan->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $karyawan->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $karyawan->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $this->levelId,
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        return (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Kode_Karyawan', $karyawan->Kode_Karyawan)
            ->value('Id_PA_Participant');
    }

    private function buatPenugasan(object $penilai): int
    {
        DB::table('N_PA_Assignment')->insert([
            'PA_Participant_Id' => $this->participantId,
            'Kode_Perusahaan_Penilai' => $penilai->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $penilai->Kode_Karyawan,
            'Nama_Penilai_Saat_Cycle' => $penilai->Nama,
            'PA_Rater_Category_Id' => $this->kategoriId,
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => 'BELUM_MULAI',
        ]);

        return (int) DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $this->participantId)
            ->where('Kode_Karyawan_Penilai', $penilai->Kode_Karyawan)
            ->value('Id_PA_Assignment');
    }

    private function buatIntegrasi(string $apiRef): void
    {
        DB::table('N_PA_Assignment_Integration')->insert([
            'PA_Assignment_Id' => $this->assignmentId,
            'PA_Cycle_Question_Package_Id' => $this->paketId,
            'Api_Ref' => $apiRef,
            'Short_Token' => 'EVOLAMA1',
            'Akses_Otp' => 'OTPLAMA1',
            'Link_Ujian' => 'https://hclearn.example.com/evo/rekrutmen?wo_aut=EVOLAMA1&otp=OTPLAMA1',
            'Process_Id' => 'proses-uji-'.substr($apiRef, 0, 8),
            'Status_Integrasi' => 'BERHASIL',
            'Payload_Json' => json_encode(['catatan' => 'pintu awal uji']),
            'Flag_Aktif' => 'Y',
            'Created_At' => now()->format('Y-m-d H:i:s'),
        ]);
    }
}
