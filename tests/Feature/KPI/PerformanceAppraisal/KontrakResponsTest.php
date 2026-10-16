<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\PerformanceAppraisal\PaAssignmentBuilderService;
use App\Services\KPI\PerformanceAppraisal\PaPayloadMapperService;
use App\Services\KPI\PerformanceAppraisal\PaSkorEfektif;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MembandingkanBentukRespons;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

/**
 * Penjaga batas antara backend dan frontend.
 *
 * KENAPA UJI INI ADA
 *
 * Selama frontend dikerjakan atas fixture, fixture itu adalah kenyataan bagi mereka.
 * Fixture yang tidak dijaga akan basi dalam beberapa hari: backend menggeser satu nama
 * field, semuanya tetap hijau, dan barang buktinya baru muncul saat penggabungan —
 * ketika memperbaikinya paling mahal.
 *
 * Uji ini memanggil endpoint SUNGGUHAN, lalu membandingkan susunan kuncinya dengan
 * berkas fixture yang dipakai frontend. Yang dibandingkan bentuknya, bukan nilainya:
 * nilai memang berbeda tiap database, susunan kunci tidak boleh.
 *
 * PETA BERKUNCI DINAMIS
 *
 * Sebagian respons dikunci nilai bisnis — `usulan` dikunci kode kategori, `kesiapan`
 * juga. Kuncinya tidak sama antar level, jadi membandingkannya harfiah akan gagal untuk
 * alasan yang salah. Peta seperti itu didaftarkan di `petaDinamis()` dan dibandingkan
 * lewat satu anaknya saja.
 *
 * Pembandingnya sendiri hidup di `Support\MembandingkanBentukRespons`, dipakai bersama
 * `KontrakLaporanTest`. Sebelumnya berkas ini memegang salinannya sendiri — dua salinan
 * pembanding adalah dua hal yang bisa berselisih, dan yang berselisih di situ justru
 * penjaga yang seharusnya mencegah perselisihan.
 */
class KontrakResponsTest extends TestCase
{
    use DatabaseTransactions;
    use MembandingkanBentukRespons;
    use MenyiapkanLembarPenilaian;

    /**
     * Alamat yang isinya dikunci nilai bisnis, bukan nama field.
     *
     * @return list<string>
     */
    protected function petaDinamis(): array
    {
        return [
            'result.usulan',
            'result.kesiapan.kategori',
            'result.ringkasan',
            'result.aturan_penilai',
            'result.di_luar_jendela.per_status',
        ];
    }

    private int $cycleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->penggunaBerakses());
        $this->cycleId = $this->siklusUji();
    }

    public function test_daftar_siklus_sebentuk_dengan_fixture(): void
    {
        $r = $this->getJson('/api/v1/performance-appraisal/cycles');

        $r->assertOk();
        $this->samakanBentuk('cycles.json', $r->json());
    }

    public function test_keranjang_tertunda_sebentuk_dengan_fixture(): void
    {
        // Fixture menggambarkan keranjang yang TERISI. Kalau di database uji keranjangnya
        // kosong, bentuk isinya tidak pernah terbandingkan dan uji ini akan hijau tanpa
        // menguji apa pun. Karena itu satu peserta TERTUNDA dibuat lebih dulu.
        $this->pesertaTertunda();

        $r = $this->getJson("/api/v1/performance-appraisal/cycles/{$this->cycleId}/tertunda");

        $r->assertOk();
        $this->assertNotEmpty($r->json('result.tertunda'), 'Keranjang uji harus terisi.');
        $this->samakanBentuk('tertunda.json', $r->json());
    }

    public function test_usulan_penilai_sebentuk_dengan_fixture(): void
    {
        $participantId = $this->pesertaDiikutkan();

        $r = $this->getJson("/api/v1/performance-appraisal/participants/{$participantId}/usulan-penilai");

        $r->assertOk();
        $this->assertNotEmpty(
            $r->json('result.usulan'),
            'Peserta uji harus punya aturan penilai, kalau tidak bentuk isinya tak terbandingkan.'
        );
        $this->samakanBentuk('usulan-penilai.json', $r->json());
    }

    public function test_hitung_peserta_sebentuk_dengan_fixture(): void
    {
        $participantId = $this->pesertaDiikutkan();
        $this->tutupSiklus();

        $r = $this->postJson("/api/v1/performance-appraisal/participants/{$participantId}/hitung");

        $r->assertOk();
        $this->samakanBentuk('hitung-peserta.json', $r->json());
    }

    public function test_hitung_siklus_sebentuk_dengan_fixture(): void
    {
        $participantId = $this->pesertaDiikutkan();

        /* Hanya DUA peserta yang diikutkan. Menghitung seluruh siklus atas ratusan
           peserta pada database pengembangan jarak jauh butuh puluhan menit, dan bentuk
           responsnya sama saja dengan dua peserta.

           Kenapa dua, bukan satu: `gagal` di fixture adalah daftar BERISI. Kalau di
           siklus uji tak ada satu pun peserta yang gagal, daftarnya kosong, bentuk
           isinya tidak pernah terbandingkan, dan uji ini hijau tanpa menguji apa pun —
           persis jebakan yang sudah dihindari di keranjang tertunda. */
        $dua = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->orderBy('Id_PA_Participant')
            ->limit(2)
            ->pluck('Id_PA_Participant');

        $this->assertCount(2, $dua, 'Siklus uji butuh dua peserta DIIKUTKAN.');
        $this->assertSame($participantId, (int) $dua[0]);

        DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->whereNotIn('Id_PA_Participant', $dua->all())
            ->update(['Status_Peserta' => 'DIKECUALIKAN', 'Alasan_Dikecualikan' => 'uji kontrak']);

        $this->tutupSiklus();

        /* Peserta kedua dibuat GAGAL lewat aturan sungguhan, bukan lewat data yang
           dikarang: hasil yang sudah TERBIT menolak dihitung ulang, dan penolakan itu
           yang mendarat di `gagal`. */
        DB::table('N_PA_Result')
            ->where('PA_Participant_Id', (int) $dua[1])
            ->update(['Status_Hasil' => 'TERBIT']);

        $r = $this->postJson("/api/v1/performance-appraisal/cycles/{$this->cycleId}/hitung");

        $r->assertOk();
        $this->assertNotEmpty(
            $r->json('result.gagal'),
            'Daftar gagal harus terisi, kalau tidak bentuk isinya tak terbandingkan.'
        );
        $this->samakanBentuk('hitung-siklus.json', $r->json());
    }

    public function test_daftar_peserta_sebentuk_dengan_fixture(): void
    {
        $this->pesertaDiikutkan();

        $r = $this->getJson(
            "/api/v1/performance-appraisal/cycles/{$this->cycleId}/participants?per_halaman=5"
        );

        $r->assertOk();
        $this->assertNotEmpty($r->json('result.peserta'), 'Halaman peserta harus terisi.');
        $this->samakanBentuk('participants.json', $r->json());
    }

    public function test_daftar_peserta_melaporkan_pemangkasan_ukuran_halaman(): void
    {
        $this->pesertaDiikutkan();

        $r = $this->getJson(
            "/api/v1/performance-appraisal/cycles/{$this->cycleId}/participants?per_halaman=999"
        );

        $r->assertOk();

        // Pembatasan apa pun WAJIB dilaporkan. Pemangkasan yang diam membuat orang
        // mengira datanya memang hanya sebanyak yang terlihat.
        $this->assertTrue($r->json('result.dipangkas'));
        $this->assertSame(100, $r->json('result.per_halaman'));
    }

    public function test_detail_hasil_sebentuk_dengan_fixture(): void
    {
        $participantId = $this->pesertaBerKpiLengkap();

        /* Fixture ini menggambarkan hasil yang TERISI: ada penilai, ada radar kompetensi,
           ada catatan, ada kuadran. Peserta yang tidak pernah dinilai menghasilkan empat
           cabang kosong, dan `bentuk()` memulangkan `alamat[]` untuk daftar kosong -
           sehingga 32 kunci dilaporkan "hilang" padahal semuanya masih ada di kode.
           Merah yang menuduh tempat yang salah lebih buruk daripada tidak ada uji. */
        $this->beriNilaiPenuh($participantId);
        $this->kalibrasiSatuButir($participantId);

        $this->tutupSiklus();
        $this->postJson("/api/v1/performance-appraisal/participants/{$participantId}/hitung")
            ->assertOk();

        /* Siklus TERDAHULU yang sudah terbit untuk orang yang sama, supaya `tren` berisi dan
           bentuknya ikut dibandingkan. Dulu bergantung pada kebetulan data dev (apakah orang
           yang terpilih punya hasil di siklus lain), sehingga uji ini merah-hijau bergantian. */
        $ini = DB::table('N_PA_Participant')->where('Id_PA_Participant', $participantId)->first();
        $sebelumId = (int) DB::table('N_PA_Cycle')->insertGetId([
            'Kode_Cycle' => 'UJI-KTR-TRN-'.bin2hex(random_bytes(3)), 'Nama_Cycle' => 'Siklus Sebelumnya', 'Tahun' => 2001,
            'Status_Cycle' => 'PUBLISHED', 'Periode_Dinilai_Dari' => '2001-01-01', 'Periode_Dinilai_Sampai' => '2001-12-31',
        ], 'Id_PA_Cycle');
        $pidSebelum = (int) DB::table('N_PA_Participant')->insertGetId([
            'PA_Cycle_Id' => $sebelumId, 'Kode_Perusahaan' => $ini->Kode_Perusahaan, 'Kode_Karyawan' => $ini->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $ini->Nama_Karyawan_Saat_Cycle, 'ID_Level_Jabatan_Saat_Cycle' => $ini->ID_Level_Jabatan_Saat_Cycle,
            'ID_Level_Saat_Cycle' => $ini->ID_Level_Saat_Cycle, 'PA_Cycle_Rater_Policy_Snapshot_Id' => $ini->PA_Cycle_Rater_Policy_Snapshot_Id,
            'Status_Peserta' => 'DIIKUTKAN',
        ], 'Id_PA_Participant');
        DB::table('N_PA_Result')->insert([
            'PA_Participant_Id' => $pidSebelum, 'Calculation_Version' => 1, 'Status_Hasil' => 'TERBIT', 'Flag_Aktif' => 'Y',
            'Final_Score' => 70.0, 'Nilai_KPI' => 72.0, 'Nilai_Kompetensi' => 68.0, 'Kode_Grade_Final' => 'C',
        ]);

        $r = $this->getJson("/api/v1/performance-appraisal/participants/{$participantId}/hasil");

        $r->assertOk();
        $this->assertNotNull($r->json('result.tren'), 'Tren siklus sebelumnya harus terisi.');

        // Prasyarat, bukan hiasan: keempat cabang harus benar-benar terisi, kalau tidak
        // bentuk isinya tak pernah terbandingkan dan uji ini hijau tanpa menguji apa pun.
        $this->assertNotEmpty($r->json('result.penilai'), 'Hasil uji harus punya penilai.');
        $this->assertNotEmpty($r->json('result.kompetensi'), 'Radar kompetensi harus terisi.');
        $this->assertNotEmpty($r->json('result.catatan'), 'Catatan hasil harus terisi.');
        $this->assertNotNull($r->json('result.kuadran'), 'Kuadran harus terisi.');

        /* Dua cabang yang menggerakkan panel kualitatif: daftar PERTANYAAN yang diajukan,
           dan daftar KATEGORI tempat admin menuliskan kesimpulannya. Keduanya daftar, jadi
           bila kosong `bentuk()` memulangkan `alamat[]` dan isinya tak pernah terbandingkan
           — uji ini hijau sambil tidak menguji apa pun. */
        $this->assertNotEmpty($r->json('result.pertanyaan_kualitatif'), 'Daftar pertanyaan kualitatif harus terisi.');
        $this->assertNotEmpty($r->json('result.kategori_catatan'), 'Kategori catatan harus terisi.');
        $this->assertNotEmpty($r->json('result.kalibrasi_penilai'), 'Riwayat Kalibrasi Penilai harus terisi.');

        $this->samakanBentuk('hasil-peserta.json', $r->json());
    }

    public function test_detail_hasil_tetap_terbuka_untuk_peserta_tertahan(): void
    {
        // Justru di layar inilah Alasan_Tertahan dibaca. Menutupnya berarti menutup
        // satu-satunya tempat yang bisa menjelaskan kenapa seseorang tidak dinilai.
        $participantId = $this->pesertaTertunda();

        $r = $this->getJson("/api/v1/performance-appraisal/participants/{$participantId}/hasil");

        $r->assertOk();
        $this->assertNotNull($r->json('result.peserta.Id_PA_Participant'));
        $this->assertSame([], $r->json('result.aturan_penilai'));
    }

    public function test_kalibrasi_menolak_hasil_yang_belum_punya_grade(): void
    {
        $participantId = $this->pesertaDiikutkan();
        $this->tutupSiklus();
        $this->postJson("/api/v1/performance-appraisal/participants/{$participantId}/hitung")
            ->assertOk();

        $resultId = DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $participantId)
            ->value('Id_PA_Result');

        DB::table('N_PA_Result')->where('Id_PA_Result', $resultId)->update([
            'Status_Hasil' => 'TERTAHAN',
            'Kode_Grade_Final' => null,
            'Kode_Grade_Sistem' => null,
        ]);

        $r = $this->postJson("/api/v1/performance-appraisal/results/{$resultId}/kalibrasi", [
            'kode_grade' => 'A',
            'alasan' => 'uji penolakan',
        ]);

        // Kalibrasi Forum ditutup sejak Balancing (B7, spec 2026-09-30): selalu 422 bersandi.
        $r->assertStatus(422);
        $this->assertSame('PA_KALIBRASI_FORUM_DITUTUP', $r->json('error_code'));

        // Dan tidak boleh meninggalkan jejak kalibrasi apa pun.
        $this->assertSame(
            0,
            DB::table('N_PA_Calibration_History')->where('PA_Result_Id', $resultId)->count()
        );
    }

    public function test_kalibrasi_menggeser_grade_final_tanpa_menyentuh_grade_sistem(): void
    {
        $participantId = $this->pesertaDiikutkan();
        $this->tutupSiklus();

        $resultId = (int) DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $participantId)
            ->value('Id_PA_Result');

        // Hasilnya disiapkan langsung ke keadaan "sudah bergrade": yang diuji di sini
        // kalibrasinya, bukan perhitungannya.
        $grade = DB::table('N_PA_Cycle_Score_Range_Snapshot')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Score_Type', 'FINAL')->where('Score_Usage', 'GRADE')
            ->orderBy('Urutan_Tampil')
            ->pluck('Kode_Range');

        $this->assertGreaterThanOrEqual(2, $grade->count(), 'Pita grade siklus uji harus ada.');

        DB::table('N_PA_Result')->where('Id_PA_Result', $resultId)->update([
            'Status_Hasil' => 'DIHITUNG',
            'Kode_Grade_Sistem' => $grade[0],
            'Kode_Grade_Final' => $grade[0],
            'Flag_Dikalibrasi' => 'T',
        ]);

        $r = $this->postJson("/api/v1/performance-appraisal/results/{$resultId}/kalibrasi", [
            'kode_grade' => $grade[1],
            'alasan' => 'hasil forum kalibrasi',
        ]);

        /* Kalibrasi Forum ditutup sejak Balancing (B7, spec 2026-09-30). Grade hanya bergeser
           lewat skor kompetensi di sesi Balancing dan kurva; endpoint lama menjelaskan itu. */
        $r->assertStatus(422);
        $this->assertSame('PA_KALIBRASI_FORUM_DITUTUP', $r->json('error_code'));

        $sesudah = DB::table('N_PA_Result')->where('Id_PA_Result', $resultId)
            ->first(['Kode_Grade_Sistem', 'Kode_Grade_Final', 'Flag_Dikalibrasi']);

        $this->assertSame($grade[0], $sesudah->Kode_Grade_Final, 'Grade tidak boleh bergeser.');
        $this->assertSame('T', $sesudah->Flag_Dikalibrasi);
        $this->assertSame(0, DB::table('N_PA_Calibration_History')->where('PA_Result_Id', $resultId)->count());
    }

    public function test_kalibrasi_menolak_perubahan_kosong(): void
    {
        $participantId = $this->pesertaDiikutkan();
        $this->tutupSiklus();

        $resultId = (int) DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $participantId)
            ->value('Id_PA_Result');

        $grade = DB::table('N_PA_Cycle_Score_Range_Snapshot')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Score_Type', 'FINAL')->where('Score_Usage', 'GRADE')
            ->orderBy('Urutan_Tampil')->value('Kode_Range');

        DB::table('N_PA_Result')->where('Id_PA_Result', $resultId)->update([
            'Status_Hasil' => 'DIHITUNG',
            'Kode_Grade_Sistem' => $grade,
            'Kode_Grade_Final' => $grade,
        ]);

        // Riwayat yang tidak mencatat perubahan apa pun hanya menambah baris untuk dibaca
        // orang tanpa memberi tahu apa-apa.
        $r = $this->postJson("/api/v1/performance-appraisal/results/{$resultId}/kalibrasi", [
            'kode_grade' => $grade,
            'alasan' => 'tanpa perubahan',
        ]);

        $r->assertStatus(422);
        $this->assertSame(
            0,
            DB::table('N_PA_Calibration_History')->where('PA_Result_Id', $resultId)->count()
        );
    }

    public function test_hav_map_sebentuk_dengan_fixture_dan_selalu_sembilan_kotak(): void
    {
        $this->pesertaDiikutkan();

        $r = $this->getJson("/api/v1/performance-appraisal/cycles/{$this->cycleId}/hav-map");

        $r->assertOk();

        // Sembilan kotak SELALU ada, termasuk yang kosong. Matriks berlubang lebih
        // menyesatkan daripada kotak bernilai nol.
        $this->assertCount(9, $r->json('result.kuadran'));
        $this->assertNotEmpty($r->json('result.grade'), 'Pita grade salinan siklus harus ada.');

        $this->samakanBentuk('hav-map.json', $r->json());
    }

    public function test_penolakan_aturan_menjawab_422_dengan_pesan_terbaca(): void
    {
        // Siklus DRAFT belum punya peserta, dan penguncian pada keadaan itu ditolak.
        $r = $this->postJson("/api/v1/performance-appraisal/cycles/{$this->cycleId}/kunci");

        $r->assertStatus(422);

        // 422 berarti ATURAN yang menolak, bukan sistem yang rusak. Bentuk galatnya
        // dijaga karena frontend menampilkan `message` apa adanya.
        $this->samakanBentuk('galat.json', $r->json());
        $this->assertNotEmpty($r->json('message'));
        $this->assertFalse($r->json('success'));
    }

    public function test_fixture_yang_dipakai_frontend_benar_benar_terbaca(): void
    {
        // Penjaga atas penjaga. Kalau berkas fixture-nya pindah atau rusak, uji-uji di
        // atas bisa lolos karena tak ada yang dibandingkan.
        /* Jalurnya milik trait. Satu konstanta yang lepas di sini sempat mematikan
           SEPERTIGA daya periksa berkas ini — 342 assertion turun ke 222 — sementara
           ringkasannya tetap membaca "1 failed", seolah kerugiannya sekecil satu uji.
           Uji yang menggelung banyak berkas seperti ini menyembunyikan besarnya
           kerusakan di balik satu baris merah. */
        $berkas = glob(base_path(self::DIR_FIXTURE.'/*.json'));

        $this->assertGreaterThanOrEqual(14, count($berkas), 'Fixture frontend tidak lengkap.');

        foreach ($berkas as $b) {
            $isi = json_decode((string) file_get_contents($b), true);
            $this->assertIsArray($isi, "Fixture {$b} bukan JSON yang bisa dibaca.");
            $this->assertArrayHasKey('success', $isi, "Fixture {$b} tidak memakai amplop ResponseHelper.");
            $this->assertArrayHasKey('message', $isi, "Fixture {$b} tidak memakai amplop ResponseHelper.");
        }
    }

    /* ------------------------------------------------------------------ *
     * Pembanding bentuk                                                   *
     * ------------------------------------------------------------------ */

    /* ================================================================== *
     * Sisi peserta                                                        *
     *                                                                     *
     * Bertindak sebagai KARYAWAN, bukan sebagai admin. Alamat-alamat ini   *
     * menentukan lingkupnya dari user yang login, jadi memakai user admin  *
     * yang kebetulan tidak punya baris Karyawan akan gagal untuk alasan    *
     * yang sama sekali salah.                                              *
     * ================================================================== */

    public function test_ringkasan_saya_sebentuk_dengan_fixture(): void
    {
        $karyawan = $this->penggunaKaryawan();
        $this->actingAs($karyawan);

        /* `di_luar_jendela.per_status` BERKUNCI DATA: isinya status siklus yang benar-benar
           ada, bukan daftar tetap. Fixture menjanjikan kunci `CLOSED`, dan sebelum ini
           kunci itu muncul hanya karena basis data pengembangan kebetulan menyimpan siklus
           tertutup milik orang lain — ujinya lulus atas data yang tidak pernah ia buat.
           Begitu basis datanya dikosongkan, kuncinya hilang dan ujinya memerah untuk sebab
           yang tidak ada hubungannya dengan kode. Sekarang tugas itu dibuat sendiri. */
        $this->penugasanDiSiklusTertutup($karyawan);

        $r = $this->getJson('/api/v1/performance-appraisal/saya/ringkasan');

        $r->assertOk();
        $this->assertNotSame([], $r->json('result.di_luar_jendela.per_status'),
            'Ringkasan uji harus memuat tugas di luar jendela, kalau tidak bentuknya tak pernah terbandingkan.');
        $this->samakanBentuk('saya-ringkasan.json', $r->json());
    }

    public function test_assignment_saya_sebentuk_dengan_fixture(): void
    {
        $karyawan = $this->penggunaKaryawan();
        $this->actingAs($karyawan);

        /* Fixture menggambarkan daftar yang TERISI. Kalau user uji kebetulan tidak punya
           penugasan, `assignment` kosong, bentuk barisnya tidak pernah terbandingkan, dan
           uji ini hijau tanpa menguji apa pun. */
        $this->penugasanUntuk($karyawan);

        $r = $this->getJson('/api/v1/performance-appraisal/saya/assignment');

        $r->assertOk();
        $this->assertNotEmpty($r->json('result.assignment'), 'Daftar uji harus terisi.');
        $this->samakanBentuk('saya-assignment.json', $r->json());
    }

    /**
     * Keempat bentuk wizard dibandingkan atas data yang TERISI: langkah berisi, lembar
     * berisi, orang pertama terkalibrasi dan punya jawaban naratif. Pembanding bentuk
     * menganggap null berbeda dari objek dan daftar kosong berbeda dari daftar berisi.
     */
    public function test_wizard_saya_sebentuk_dengan_fixture(): void
    {
        $l = $this->lembarTerisi(['boleh_kalibrasi' => 'Y', 'naratif' => 'Teks uji']);
        $this->actingAs($this->penilaiUji());

        $this->samakanBentuk('saya-wizard.json',
            $this->getJson('/api/v1/performance-appraisal/saya/wizard?cycle_id='.$l['cycle_id'])->assertOk()->json());
    }

    public function test_lembar_langkah_sebentuk_dengan_fixture(): void
    {
        $l = $this->lembarTerisi(['boleh_kalibrasi' => 'Y']);
        $this->actingAs($this->penilaiUji());

        $this->samakanBentuk('saya-lembar.json',
            $this->getJson('/api/v1/performance-appraisal/saya/wizard/langkah/ATASAN/lembar?cycle_id='.$l['cycle_id'])
                ->assertOk()->json());
    }

    public function test_pratinjau_langkah_sebentuk_dengan_fixture(): void
    {
        $l = $this->lembarTerisi(['boleh_kalibrasi' => 'Y', 'naratif' => 'Teks uji']);
        $this->kalibrasiLangsung($l['assignment_id'], $l['kompetensi'][0], 70, 60);
        $this->actingAs($this->penilaiUji());

        $this->samakanBentuk('saya-pratinjau.json',
            $this->getJson('/api/v1/performance-appraisal/saya/wizard/langkah/ATASAN/pratinjau?cycle_id='.$l['cycle_id'])
                ->assertOk()->json());
    }

    public function test_kalibrasi_sebentuk_dengan_fixture(): void
    {
        $l = $this->lembarTerisi(['boleh_kalibrasi' => 'Y', 'naratif' => 'Teks uji']);
        $this->actingAs($this->penilaiUji());

        $this->samakanBentuk('saya-kalibrasi.json', $this->postJson('/api/v1/performance-appraisal/saya/kalibrasi', [
            'assignment_id' => $l['assignment_id'],
            'perubahan' => [['competency_id' => $l['kompetensi'][0], 'skor' => 60]],
            'alasan' => 'Uji kontrak',
        ])->assertOk()->json());
    }

    public function test_hasil_saya_sebentuk_dengan_fixture(): void
    {
        $karyawan = $this->penggunaKaryawan();
        $this->actingAs($karyawan);

        // Fixture menggambarkan hasil yang TERBIT dan terisi; tanpa ini responsnya
        // berbentuk "belum terbit" dan yang dibandingkan bukan bentuk yang dipakai layar.
        $this->hasilTerbitUntuk($karyawan);

        $r = $this->getJson('/api/v1/performance-appraisal/saya/hasil');

        $r->assertOk();
        $this->assertTrue($r->json('result.tersedia'), 'Hasil uji harus sudah terbit.');
        $this->assertNotEmpty($r->json('result.kompetensi'), 'Radar kompetensi harus terisi.');
        $this->samakanBentuk('saya-hasil.json', $r->json());
    }

    public function test_riwayat_saya_sebentuk_dengan_fixture(): void
    {
        $karyawan = $this->penggunaKaryawan();
        $this->actingAs($karyawan);

        $this->hasilTerbitUntuk($karyawan);

        $r = $this->getJson('/api/v1/performance-appraisal/saya/riwayat');

        $r->assertOk();
        $this->assertNotEmpty($r->json('result.periode'), 'Riwayat uji harus terisi.');
        $this->samakanBentuk('saya-riwayat.json', $r->json());
    }

    /* ------------------------------------------------------------------ *
     * Bahan uji                                                           *
     * ------------------------------------------------------------------ */

    private function penggunaBerakses(): User
    {
        $userId = DB::table('HRIS_Page_Access')
            ->where('Jenis_page', 'PerformanceAppraisalPage')
            ->where('Flag_Aktif', 'Y')
            ->whereNotNull('UserID_Web')
            ->value('UserID_Web');

        $this->assertNotNull(
            $userId,
            'Tidak ada satu pun user berakses PerformanceAppraisalPage; DML-2026-084 belum dijalankan.'
        );

        $user = User::find($userId);
        $this->assertNotNull($user, "User {$userId} tidak ditemukan.");

        return $user;
    }

    private function siklusUji(): int
    {
        $kode = 'UJI-KONTRAK-'.substr(bin2hex(random_bytes(4)), 0, 8);

        return (int) DB::table('N_PA_Cycle')->insertGetId([
            'Kode_Cycle' => $kode,
            'Nama_Cycle' => 'Siklus Uji Kontrak Respons',
            'Tahun' => 2026,
            'Periode_Dinilai_Dari' => '2026-01-01',
            'Periode_Dinilai_Sampai' => '2026-06-30',
            'Status_Cycle' => 'DRAFT',
            'Flag_Aktif' => 'Y',
        ], 'Id_PA_Cycle');
    }

    /**
     * Menyiapkan siklus sekali lalu memakai ulang pesertanya.
     *
     * Penyiapan menyentuh seluruh karyawan aktif dan itu memakan waktu; memanggilnya
     * per uji akan melipatgandakan durasinya tanpa menambah apa pun.
     */
    /* ------------------------------------------------------------------ *
     * Penopang uji sisi peserta                                           *
     * ------------------------------------------------------------------ */

    /**
     * User yang PASTI punya baris Karyawan aktif.
     *
     * `penggunaBerakses()` memilih pemegang kunci halaman admin, dan orang itu belum tentu
     * punya baris Karyawan - akun sistem sering tidak punya. Memakainya di sini akan
     * menghasilkan 422 "belum tersambung ke data karyawan", yang benar sebagai perilaku
     * tetapi salah sebagai uji: yang merah bukan kontraknya, melainkan pilihan usernya.
     */
    private function penggunaKaryawan(): User
    {
        $userId = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNotNull('UserID_Web')
            ->orderBy('Kode_Karyawan')
            ->value('UserID_Web');

        $this->assertNotNull($userId, 'Tidak ada karyawan aktif yang tersambung ke user.');

        $user = User::find($userId);
        $this->assertNotNull($user, "User {$userId} tidak ditemukan.");

        return $user;
    }

    /**
     * Satu penugasan pada siklus uji tempat user ini menjadi PENILAI.
     *
     * Dibuat sendiri, bukan menumpang penugasan yang kebetulan sudah ada di database
     * pengembangan: yang menumpang akan hijau hari ini dan merah begitu data itu berubah,
     * dan merahnya tidak akan menunjuk ke sebab yang sebenarnya.
     */
    /**
     * Penugasan untuk user ini pada siklus yang sudah DITUTUP.
     *
     * Dipakai menegakkan bentuk `di_luar_jendela`, yang kuncinya berasal dari status siklus
     * yang sungguh-sungguh ada. Siklusnya dibuat terpisah dari siklus uji utama: yang utama
     * harus tetap bisa diisi, dan mengubah statusnya demi satu uji akan merusak semua uji
     * lain di berkas ini.
     */
    private function penugasanDiSiklusTertutup(User $user): int
    {
        $karyawan = DB::table('Karyawan')
            ->where('UserID_Web', $user->Id_Users)
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan']);

        $nama = 'Siklus Uji Kontrak Tertutup '.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => 'UJI-TUTUP-'.substr(bin2hex(random_bytes(4)), 0, 8),
            'Nama_Cycle' => $nama,
            'Tahun' => (int) date('Y'),
            'Status_Cycle' => 'CLOSED',
            'Flag_Aktif' => 'Y',
        ]);

        $cycleId = (int) DB::table('N_PA_Cycle')->where('Nama_Cycle', $nama)->value('Id_PA_Cycle');

        $levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');

        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $cycleId,
            'Kode_Perusahaan' => $karyawan->Kode_Perusahaan,
            'Kode_Karyawan' => $karyawan->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $karyawan->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $karyawan->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $levelId,
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        $participantId = (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Kode_Karyawan', $karyawan->Kode_Karyawan)
            ->value('Id_PA_Participant');

        DB::table('N_PA_Assignment')->insert([
            'PA_Participant_Id' => $participantId,
            'Kode_Perusahaan_Penilai' => $karyawan->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $karyawan->Kode_Karyawan,
            'Nama_Penilai_Saat_Cycle' => $karyawan->Nama,
            'PA_Rater_Category_Id' => (int) DB::table('N_PA_Rater_Category')
                ->where('Kode_Kategori', 'SELF')->value('Id_PA_Rater_Category'),
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => 'BELUM_MULAI',
        ]);

        return $participantId;
    }

    private function penugasanUntuk(User $user): int
    {
        $participantId = $this->pesertaDiikutkan();

        $karyawan = DB::table('Karyawan')
            ->where('UserID_Web', $user->Id_Users)
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama']);

        $kategoriId = (int) DB::table('N_PA_Rater_Category')
            ->where('Kode_Kategori', 'SELF')
            ->value('Id_PA_Rater_Category');

        DB::table('N_PA_Assignment')->insert([
            'PA_Participant_Id' => $participantId,
            'Kode_Perusahaan_Penilai' => $karyawan->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $karyawan->Kode_Karyawan,
            'Nama_Penilai_Saat_Cycle' => $karyawan->Nama,
            'PA_Rater_Category_Id' => $kategoriId,
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => 'BELUM_MULAI',
            'Due_At' => now()->addDays(7)->format('Y-m-d H:i:s'),
        ]);

        /* Siklusnya DIBUKA. `/saya/assignment` hanya memulangkan tugas dari siklus yang
           bisa diisi (`PaPesertaService::STATUS_SIKLUS_DAPAT_DIISI`), jadi penugasan pada
           siklus yang belum dibuka tidak akan pernah muncul.

           Sebelum ini daftarnya terisi bukan oleh baris yang dibuat di sini, melainkan oleh
           siklus terbuka milik orang lain yang kebetulan ada di basis data pengembangan —
           uji ini membandingkan bentuk atas data yang tidak pernah ia buat, dan memerah
           begitu basis datanya dikosongkan. */
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
            ->update(['Status_Cycle' => 'OPEN']);

        return (int) DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->where('Kode_Karyawan_Penilai', $karyawan->Kode_Karyawan)
            ->orderByDesc('Id_PA_Assignment')
            ->value('Id_PA_Assignment');
    }

    /**
     * Hasil TERBIT untuk user ini, lengkap dengan radar kompetensi dan catatan.
     *
     * Peserta siklus uji dipaksa menjadi orang ini supaya hasilnya benar-benar terbaca
     * lewat `/saya/hasil`, yang lingkupnya dari user yang login.
     */
    private function hasilTerbitUntuk(User $user): int
    {
        $this->siapkanSekali();

        $karyawan = DB::table('Karyawan')
            ->where('UserID_Web', $user->Id_Users)
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama']);

        /* Barisnya DICARI, bukan dibuat dengan menimpa peserta lain.
           `siapkan` mendaftarkan SELURUH karyawan aktif, jadi orang ini sudah punya
           barisnya sendiri di siklus uji. Versi sebelumnya mengambil peserta DIIKUTKAN
           pertama lalu menimpa identitasnya - yang menabrak unique index (siklus,
           perusahaan, karyawan) begitu baris aslinya memang sudah ada, dan seandainya
           lolos pun ia akan MENGHAPUS satu peserta lain dari siklus. */
        $participantId = (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Kode_Perusahaan', $karyawan->Kode_Perusahaan)
            ->where('Kode_Karyawan', $karyawan->Kode_Karyawan)
            ->value('Id_PA_Participant');

        $this->assertNotEmpty($participantId, sprintf(
            'Karyawan %s tidak terdaftar sebagai peserta di siklus uji; '
            .'`siapkan` seharusnya mendaftarkan seluruh karyawan aktif.',
            $karyawan->Kode_Karyawan,
        ));

        $kuadranId = DB::table('N_PA_Hav_Quadrant')->value('Id_PA_Hav_Quadrant');

        /* UPDATE, bukan INSERT. `siapkan` sudah membuat satu baris hasil BELUM_DIHITUNG
           untuk SETIAP peserta, dan `UX_PA_Result_Participant` unik per peserta - jadi
           menyisipkan baris kedua pasti ditolak. Pola yang sama dengan barisan peserta
           satu tabel di atasnya: apa pun yang menumpang hasil `siapkan` harus mengubah
           yang sudah ada, bukan menambah. */
        $nilai = [
            'Nilai_KPI' => 85.00,
            'Nilai_Kompetensi' => 72.50,
            'Final_Score' => 82.50,
            'Kode_Grade_Final' => 'B',
            'Kode_Grade_Sistem' => 'B',
            'PA_Hav_Quadrant_Id' => $kuadranId,
            'Status_Hasil' => 'TERBIT',
            'Published_At' => now()->format('Y-m-d H:i:s'),
        ];

        $resultId = (int) DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $participantId)
            ->value('Id_PA_Result');

        if ($resultId > 0) {
            DB::table('N_PA_Result')->where('Id_PA_Result', $resultId)->update($nilai);
        } else {
            // Siklus yang belum pernah dihitung boleh saja belum punya barisnya.
            DB::table('N_PA_Result')->insert($nilai + ['PA_Participant_Id' => $participantId]);

            $resultId = (int) DB::table('N_PA_Result')
                ->where('PA_Participant_Id', $participantId)
                ->value('Id_PA_Result');
        }

        // Baris kompetensi & catatan dibersihkan dulu supaya pemanggilan berulang tidak
        // menumpuk dan membuat cacah di uji lain menyimpang.
        DB::table('N_PA_Result_Competency')->where('PA_Result_Id', $resultId)->delete();
        DB::table('N_PA_Result_Note')->where('PA_Result_Id', $resultId)->delete();

        $kompetensi = DB::table('N_PA_Competency')->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Competency')->first(['Id_PA_Competency']);

        $this->assertNotNull($kompetensi, 'Master kompetensi kosong; radar tak bisa dibentuk.');

        // PA_Rater_Category_Id NULL = baris AGREGAT lintas kategori. Itulah satu-satunya
        // baris yang boleh dilihat peserta.
        DB::table('N_PA_Result_Competency')->insert([
            'PA_Result_Id' => $resultId,
            'PA_Competency_Id' => $kompetensi->Id_PA_Competency,
            'PA_Rater_Category_Id' => null,
            'Nilai' => 80.00,
        ]);

        $kategoriCatatan = $this->kompetensiKualitatif();

        if ($kategoriCatatan !== null) {
            DB::table('N_PA_Result_Note')->insert([
                'PA_Result_Id' => $resultId,
                'PA_Competency_Id' => $kategoriCatatan,
                'Isi' => 'Catatan uji kontrak.',
                'Urutan_Tampil' => 1,
            ]);
        }

        return $resultId;
    }

    /**
     * Id kompetensi kualitatif pertama — kategori tempat catatan hasil bergantung.
     */
    private function kompetensiKualitatif(): ?int
    {
        $id = DB::table('N_PA_Competency as k')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'k.PA_Competency_Type_Id')
            ->where('k.Flag_Aktif', 'Y')
            ->where('t.Kode_Type', 'KUALITATIF')
            ->orderBy('k.Urutan_Tampil')
            ->value('k.Id_PA_Competency');

        return $id === null ? null : (int) $id;
    }

    private function pesertaDiikutkan(): int
    {
        $this->siapkanSekali();

        $id = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->orderBy('Id_PA_Participant')
            ->value('Id_PA_Participant');

        $this->assertNotNull($id, 'Siklus uji tidak menghasilkan satu pun peserta DIIKUTKAN.');

        return (int) $id;
    }

    private function pesertaTertunda(): int
    {
        $this->siapkanSekali();

        $id = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Status_Peserta', 'TERTUNDA')
            ->value('Id_PA_Participant');

        if ($id === null) {
            // Data pengembangan boleh saja tidak punya level tanpa aturan. Satu peserta
            // dipindahkan ke TERTUNDA supaya bentuk isinya tetap terbandingkan.
            $id = DB::table('N_PA_Participant')
                ->where('PA_Cycle_Id', $this->cycleId)
                ->where('Status_Peserta', 'DIIKUTKAN')
                ->orderByDesc('Id_PA_Participant')
                ->value('Id_PA_Participant');

            $this->assertNotNull($id, 'Siklus uji kosong.');

            DB::table('N_PA_Participant')
                ->where('Id_PA_Participant', $id)
                ->update([
                    'Status_Peserta' => 'TERTUNDA',
                    'PA_Cycle_Rater_Policy_Snapshot_Id' => null,
                ]);
        }

        return (int) $id;
    }

    /**
     * Menyiapkan siklus uji — SELALU sebagai admin, siapa pun yang sedang bertindak.
     *
     * Endpoint `siapkan` ada di balik `check.jabatan:PerformanceAppraisalPage`. Uji sisi
     * peserta bertindak sebagai karyawan biasa, yang hanya memegang
     * `PerformanceAppraisalSayaPage`, sehingga helper ini sempat menjawab 403 — dan
     * merahnya berhenti SEBELUM bentuk responsnya sempat dibandingkan, sehingga tiga
     * fixture dilaporkan gagal tanpa pernah benar-benar diperiksa.
     *
     * Identitas pemanggil dikembalikan sesudahnya. Meninggalkan sesi admin akan membuat
     * uji berikutnya lulus sebagai admin padahal maksudnya menguji karyawan — kebocoran
     * hak yang tidak akan terlihat sebagai kegagalan, hanya sebagai uji yang berhenti
     * menguji apa yang tertulis di namanya.
     */
    /**
     * Peserta yang pilar KPI-nya BISA terhitung — bukan sekadar peserta pertama.
     *
     * Kuadran HAV hanya terisi bila KEDUA pilar terhitung. Sisi kompetensi dipenuhi
     * `beriNilaiPenuh()`, tetapi sisi KPI datang dari data KPI sungguhan: butuh pengajuan
     * disetujui pada tahun siklus dengan total bobot tepat 100, DAN realisasi disetujui
     * pada bulan-bulan di rentang siklus. Peserta yang tidak memenuhinya berakhir
     * TERTAHAN dengan kuadran null — perilaku yang benar, tetapi membuat cabang
     * `result.kuadran.*` di fixture tak pernah terbandingkan.
     *
     * Kandidatnya DICARI, bukan ditanam. Menanam baris ke tabel `KPI_*` demi sebuah uji
     * berarti menulis ke luar wilayah modul ini, dan uji yang mengarang prasyaratnya
     * sendiri berhenti memberi tahu apakah prasyarat itu benar-benar ada.
     */
    private function pesertaBerKpiLengkap(): int
    {
        $this->siapkanSekali();

        $siklus = DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $this->cycleId)
            ->first(['Tahun', 'Periode_Dinilai_Dari', 'Periode_Dinilai_Sampai']);

        $bulan = $this->bulanDalamRentang(
            (string) $siklus->Periode_Dinilai_Dari,
            (string) $siklus->Periode_Dinilai_Sampai,
        );

        // User KPI dengan total bobot tepat 100 pada tahun siklus.
        $berbobotPenuh = DB::table('KPI_Pengajuan_Detail as pd')
            ->join('KPI_Pengajuan as p', function ($j) {
                $j->on('p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
                    ->where('p.Flag_Aktif', 'Y')->where('p.Flag_Approval', 'Y')
                    ->whereNull('p.Status');
            })
            ->join('KPI_Master_Penjadwalan as mp', function ($j) use ($siklus) {
                $j->on('mp.Id_Master_Penjadwalan', '=', 'p.Id_Penjadwalan')
                    ->where('mp.Tahun', (string) $siklus->Tahun);
            })
            ->groupBy('p.Id_User')
            ->havingRaw('ABS(SUM(pd.Bobot) - 100) < 0.01')
            ->pluck('p.Id_User')
            ->all();

        /* ...yang juga punya realisasi disetujui di bulan-bulan rentang siklus, PADA
           RENCANA TAHUN YANG SAMA.

           Penjadwalannya ikut disaring, dan itu bukan kehati-hatian berlebih: mesin
           menghitung parameter dan realisasi dalam SATU kueri yang terikat
           `KPI_Master_Penjadwalan.Tahun`. Tanpa saringan yang sama di sini, kandidat yang
           rencananya bertotal 100 pada tahun siklus tetapi realisasinya menempel pada
           rencana tahun lain akan lolos seleksi, lalu ditolak mesin dengan "Tidak ada KPI
           disetujui pada rentang periode siklus" — dan ujinya merah di tempat yang salah,
           menuduh kontrak respons padahal yang keliru pemilihan pesertanya. Terjadi pada
           karyawan A4: rencana 2026 bertotal 100, realisasi Januari-Juni ada, dan tetap nol
           baris parameter menurut mesin — pengajuan yang memikul realisasi itu tidak
           disetujui, dan seleksi ini dulu tidak memeriksanya sama sekali. */
        $berrealisasi = $berbobotPenuh === [] ? [] : DB::table('KPI_Pengajuan as p')
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Flag_Approval', 'Y')
            ->whereNull('p.Status')
            ->join('KPI_Master_Penjadwalan as mp', function ($j) use ($siklus) {
                $j->on('mp.Id_Master_Penjadwalan', '=', 'p.Id_Penjadwalan')
                    ->where('mp.Tahun', (string) $siklus->Tahun);
            })
            ->join('KPI_Pengajuan_Detail as pd', 'pd.Id_Pengajuan', '=', 'p.Id_Pengajuan')
            ->join('KPI_Pengajuan_Det as pdet', function ($j) use ($bulan) {
                $j->on('pdet.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail')
                    ->whereIn('pdet.Periode', $bulan);
            })
            ->join('KPI_Update_Performance_Detail as upd', 'upd.Id_Pengajuan_Det', '=', 'pdet.Id_Pengajuan_Det')
            ->join('KPI_Update_Performance as up', function ($j) {
                $j->on('up.Id_Update_Performance', '=', 'upd.Id_Update_Performance')
                    ->where('up.Flag_Aktif', 'Y')->where('up.Flag_Approval', 'Y')
                    ->where('up.Flag_Verifikasi', 'Y')->whereNull('up.Status');
            })
            ->whereIn('p.Id_User', $berbobotPenuh)
            ->distinct()
            ->pluck('p.Id_User')
            ->all();

        $kode = $berrealisasi === [] ? [] : DB::table('KPI_Users')
            ->whereIn('Id_Users', $berrealisasi)
            ->whereNotNull('Kode_Users')
            ->pluck('Kode_Users')
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->all();

        $participantId = $kode === [] ? null : DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->whereIn('Kode_Karyawan', $kode)
            ->orderBy('Id_PA_Participant')
            ->value('Id_PA_Participant');

        /* DILEWATI, bukan digagalkan — dan sebabnya bukan kemalasan.

           Yang tidak terpenuhi di sini adalah PRASYARAT DATA, bukan perilaku yang sedang
           diuji: uji ini butuh karyawan yang pengajuan KPI-nya disetujui bertotal bobot 100
           DAN punya realisasi disetujui pada rentang bulan siklus. Sejak pengecualian level
           bisa disunting admin (DDL-2026-091), orang-orang itu juga bisa berada di level
           yang sedang dikecualikan sehingga tak pernah menjadi peserta.

           Menggagalkannya akan menuduh kode atas keadaan basis data pengembangan, dan
           kegagalan seperti itu dikejar ke tempat yang salah. Pesannya menyebutkan persis
           apa yang kurang supaya bisa disiapkan, bukan ditebak. */
        if ($participantId === null) {
            $this->markTestSkipped(sprintf(
                'Tidak ada peserta yang pilar KPI-nya bisa terhitung pada siklus uji '
                .'(tahun %s, bulan %s). Dibutuhkan karyawan dengan pengajuan KPI disetujui '
                .'bertotal bobot 100 pada tahun itu DAN realisasi disetujui pada salah satu '
                .'bulan tersebut, DAN levelnya tidak sedang dikecualikan dari PA. Tanpa itu '
                .'hasilnya TERTAHAN, kuadran null, dan cabang result.kuadran.* di fixture tak '
                .'pernah terbandingkan.',
                $siklus->Tahun, implode(', ', $bulan),
            ));
        }

        return (int) $participantId;
    }

    /**
     * Daftar bulan `YYYY-MM` dalam satu rentang, inklusif.
     *
     * @return list<string>
     */
    private function bulanDalamRentang(string $dari, string $sampai): array
    {
        $mulai = new \DateTimeImmutable(substr($dari, 0, 7).'-01');
        $akhir = new \DateTimeImmutable(substr($sampai, 0, 7).'-01');

        $bulan = [];

        while ($mulai <= $akhir) {
            $bulan[] = $mulai->format('Y-m');
            $mulai = $mulai->modify('+1 month');
        }

        return $bulan;
    }

    /**
     * Membuat satu peserta benar-benar bernilai: penugasan, paket soal, dan jawaban.
     *
     * Lewat mesin sungguhan - penugasan dibentuk `PaAssignmentBuilderService`, jawaban
     * dipetakan `PaPayloadMapperService` - bukan dengan menyisipkan baris hasil langsung.
     * Menyisipkannya langsung akan membuat uji ini tetap hijau seandainya rantai
     * pemetaannya putus, dan rantai itu memang PERNAH putus tanpa satu pun pesan galat
     * (DDL-2026-083 r2).
     */
    /**
     * Satu Kalibrasi Penilai atas butir berskor pertama milik peserta. Pembanding bentuk
     * membedakan daftar kosong dari yang berisi; tanpa ini `kalibrasi_penilai` selalu `[]`
     * dan bentuk isinya tidak pernah terbandingkan.
     */
    private function kalibrasiSatuButir(int $participantId): void
    {
        $aid = (int) DB::table('N_PA_Assignment')->where('PA_Participant_Id', $participantId)
            ->orderBy('Id_PA_Assignment')->value('Id_PA_Assignment');
        $butir = ($this->app->make(PaSkorEfektif::class)->butirBerskor([$aid])[$aid] ?? collect())
            ->first(fn ($b) => $b->PA_Competency_Id !== null);

        $this->assertNotNull($butir, 'Penugasan uji tidak punya butir berskor untuk dikalibrasi.');

        $maks = (float) $butir->Skor_Maksimal;
        $sebelum = (float) $butir->Skor;
        $kepala = $this->kalibrasiLangsung($aid, (int) $butir->PA_Competency_Id, $sebelum, $sebelum >= 1 ? $sebelum - 1 : min($maks, $sebelum + 1), $maks);

        // Tindakan yang sama juga merevisi satu esai, supaya `perubahan_jawaban[]` tidak kosong
        // dan bentuk isinya ikut terbandingkan dengan fixture.
        DB::table('N_PA_Kalibrasi_Penilai_Jawaban')->insert([
            'PA_Kalibrasi_Penilai_Id' => $kepala,
            'Kode_Soal_Sumber' => 'UJI-KONTRAK-ESAI',
            'PA_Competency_Id' => (int) $butir->PA_Competency_Id,
            'Jawaban_Sebelum' => 'Jawaban asli.',
            'Jawaban_Sesudah' => 'Jawaban yang direvisi.',
        ]);
    }

    private function beriNilaiPenuh(int $participantId): void
    {
        $sebelumnya = auth()->user();
        $this->actingAs($this->penggunaBerakses());

        try {
            $penugasan = $this->app->make(PaAssignmentBuilderService::class);
            $calon = $penugasan->usulkan($participantId)['SELF']['calon'][0];

            $penugasan->tugaskan($participantId, [[
                'kode_kategori' => 'SELF',
                'kode_perusahaan' => $calon['kode_perusahaan'],
                'kode_karyawan' => $calon['kode_karyawan'],
                'sumber' => 'USULAN',
                'organization_relationship_id' => null,
            ]], 1);

            $assignmentId = (int) DB::table('N_PA_Assignment')
                ->where('PA_Participant_Id', $participantId)
                ->value('Id_PA_Assignment');

            $levelId = (int) DB::table('N_PA_Participant')
                ->where('Id_PA_Participant', $participantId)
                ->value('ID_Level_Saat_Cycle');

            /* SATU BUTIR PER JENIS WAJIB, bukan satu butir saja.
               Sebelum 2 September 2026 fixture ini memuat satu butir bernilai, sementara
               siklusnya mewajibkan tiga jenis kompetensi. Hasilnya terbaca UTUH — nilai,
               grade, kuadran lengkap — padahal di produksi peserta serupa akan ditahan
               karena dua pertiga penilaiannya tidak pernah terjadi. Fixture yang
               menggambarkan keadaan yang tidak mungkin membuat kontraknya berbohong,
               dan itu baru ketahuan ketika penjaga cakupan dipasang. */
            $jenisWajib = DB::table('N_PA_Cycle_Competency_Requirement_Snapshot')
                ->where('PA_Cycle_Id', $this->cycleId)
                ->where('ID_Level', $levelId)
                ->where('Flag_Wajib', 'Y')
                ->pluck('PA_Competency_Type_Id')
                ->unique()
                ->values();

            $this->assertNotEmpty(
                $jenisWajib,
                'Siklus uji tidak punya satu pun jenis kompetensi wajib pada level peserta; '
                .'tanpa itu penjaga cakupan tidak pernah terlibat dan fixture ini kembali '
                .'menggambarkan hasil yang terlalu mudah.'
            );

            $butir = [];

            foreach ($jenisWajib as $typeId) {
                $kompetensiId = DB::table('N_PA_Competency')
                    ->where('Flag_Aktif', 'Y')
                    ->where('PA_Competency_Type_Id', $typeId)
                    ->orderBy('Id_PA_Competency')
                    ->value('Id_PA_Competency');

                $this->assertNotNull($kompetensiId, sprintf(
                    'Jenis kompetensi wajib %d tidak punya satu pun kompetensi aktif di '
                    .'master, sehingga paket soal mustahil menutupinya.', $typeId,
                ));

                $butir[] = [
                    'kode_soal' => 'UJI-'.substr(bin2hex(random_bytes(4)), 0, 8),
                    'competency_id' => (int) $kompetensiId,
                ];
            }

            $kategoriCatatan = $this->kompetensiKualitatif();

            $this->assertNotNull($kategoriCatatan, 'Master kompetensi kualitatif kosong.');
            $kodeCatatan = 'UJIN-'.substr(bin2hex(random_bytes(3)), 0, 6);
            $namaPaket = 'Paket Uji Kontrak '.substr(bin2hex(random_bytes(3)), 0, 6);

            DB::table('N_PA_Cycle_Question_Package')->insert([
                'PA_Cycle_Id' => $this->cycleId,
                'ID_Level' => $levelId,
                'Id_Master_Ujian' => 'PKG-'.substr(bin2hex(random_bytes(3)), 0, 6),
                'Nama_Ujian_Sumber' => $namaPaket,
                'Sumber_Kontrak' => 'MANUAL',
                'Flag_Aktif' => 'Y',
            ]);

            $packageId = (int) DB::table('N_PA_Cycle_Question_Package')
                ->where('Nama_Ujian_Sumber', $namaPaket)
                ->value('Id_PA_Cycle_Question_Package');

            /* Satu butir bernilai (mengisi radar kompetensi) dan satu butir naratif
               (mengisi catatan). Tanpa yang kedua, cabang `catatan` tetap kosong dan
               enam kuncinya kembali dilaporkan hilang. */
            /* DUA insert terpisah, bukan satu batch berisi dua baris.
               Laravel menyusun daftar kolom dari baris PERTAMA saja, sehingga batch yang
               anggotanya punya himpunan kunci berbeda akan menulis nilai baris kedua ke
               kolom milik baris pertama - dan `Skor_Maksimal` diam-diam tidak
               ikut tertulis sama sekali. Di sini SQL Server kebetulan menolaknya karena
               jumlah kolomnya beda; kalau jumlahnya kebetulan sama, ia akan diterima dan
               menulis nilai ke kolom yang salah tanpa sepatah pun peringatan. */
            foreach ($butir as $urutan => $b) {
                DB::table('N_PA_Cycle_Question_Package_Item')->insert([
                    'PA_Cycle_Question_Package_Id' => $packageId,
                    'Id_Jenis_Indikator' => 'ALT-KONTRAK',
                    /* Kode dan id bagian DIBEDAKAN: pemeta mencocokkan `Detail[].Kode_Soal`
                       dengan `Kode_Soal_Sumber`, bukan dengan id bagian. Fixture yang
                       menaruh kode ke `Id_Jenis_Soal` akan hijau di atas pemeta yang di
                       production tidak memetakan satu butir pun (10 September 2026). */
                    'Id_Jenis_Soal' => 'BAGIAN-'.$b['kode_soal'],
                    'Kode_Soal_Sumber' => $b['kode_soal'],
                    'Nama_Bagian_Sumber' => 'Soal uji kontrak',
                    'PA_Competency_Id' => $b['competency_id'],
                    'Skor_Maksimal' => 5,
                    'Bobot_Penilaian' => 100,
                    'Jenis_Soal' => 'SKALA',
                    'Urutan_Tampil' => $urutan + 1,
                ]);
            }

            DB::table('N_PA_Cycle_Question_Package_Item')->insert([
                'PA_Cycle_Question_Package_Id' => $packageId,
                'Id_Jenis_Indikator' => 'ALT-KONTRAK',
                'Id_Jenis_Soal' => 'BAGIAN-'.$kodeCatatan,
                'Kode_Soal_Sumber' => $kodeCatatan,
                'Nama_Bagian_Sumber' => 'Catatan uji kontrak',

                /* Butir naratif menunjuk KOMPETENSI KUALITATIF, sama seperti butir berskala
                   menunjuk kompetensi berskor. Sejak DDL-2026-088 tidak ada master jenis
                   catatan tersendiri; butir tanpa kompetensi jawabannya tersimpan tetapi
                   tidak pernah sampai ke panel catatan. */
                'PA_Competency_Id' => $kategoriCatatan,
                'Jenis_Soal' => 'ESAI',
                'Urutan_Tampil' => count($butir) + 1,
            ]);

            $token = 'TOKEN-UJI-'.substr(bin2hex(random_bytes(4)), 0, 8);

            DB::table('N_PA_Assignment_Integration')->insert([
                'PA_Assignment_Id' => $assignmentId,
                'PA_Cycle_Question_Package_Id' => $packageId,
                'Id_Ujian_Token' => $token,
                'Flag_Aktif' => 'Y',
            ]);

            DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
                ->update(['Status_Cycle' => 'OPEN']);

            $this->app->make(PaPayloadMapperService::class)->terimaDanPetakan($token, [
                'Id_Ujian_Nilai_Akhir' => 'RESULT-'.bin2hex(random_bytes(5)),
                'Api_Version' => 'v2',
                'Detail' => array_merge(
                    array_map(fn ($b) => [
                        'Kode_Soal' => $b['kode_soal'], 'Jenis_Soal' => 'SKALA', 'Skor' => 4,
                        'Skor_Maksimal' => 5, 'Bobot_Penilaian' => 100,
                    ], $butir),
                    [[
                        'Kode_Soal' => $kodeCatatan, 'Jenis_Soal' => 'ESAI',
                        'Jawaban_Teks' => 'Catatan contoh untuk uji kontrak.',
                    ]],
                ),
            ], 1);

            /* PENILAI MENEKAN KIRIM — difixture.
               |
               | Sejak `PaRaterSubmitService`, pemetaan payload berhenti di TERKUMPUL;
               | pelepasan ke SELESAI menunggu penilai mengirim seluruh tugasnya. Perhitungan
               | hanya membaca penugasan SELESAI, jadi tanpa langkah ini `result.kompetensi`
               | pulang KOSONG — dan uji bentuk respons ini merah karena datanya, bukan
               | karena bentuknya. */
            DB::table('N_PA_Assignment')
                ->where('Id_PA_Assignment', $assignmentId)
                ->update(['Status_Penugasan' => PaPayloadMapperService::STATUS_SELESAI]);
        } finally {
            if ($sebelumnya !== null) {
                $this->actingAs($sebelumnya);
            }
        }
    }

    private function siapkanSekali(): void
    {
        $sudah = DB::table('N_PA_Participant')->where('PA_Cycle_Id', $this->cycleId)->exists();

        if ($sudah) {
            return;
        }

        $sebelumnya = auth()->user();

        $this->actingAs($this->penggunaBerakses());

        try {
            $this->postJson("/api/v1/performance-appraisal/cycles/{$this->cycleId}/siapkan")
                ->assertOk();
        } finally {
            if ($sebelumnya !== null) {
                $this->actingAs($sebelumnya);
            }
        }
    }

    private function tutupSiklus(): void
    {
        DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $this->cycleId)
            ->update(['Status_Cycle' => 'CLOSED']);
    }
}
