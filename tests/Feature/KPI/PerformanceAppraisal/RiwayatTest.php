<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaRiwayatService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Riwayat penilaian lintas siklus — sisi admin.
 *
 * Perbedaannya dengan riwayat peserta adalah inti berkas ini: yang ini SENGAJA memuat
 * hasil yang belum terbit dan yang tertahan, karena itulah pekerjaan yang belum selesai.
 * Kalau suatu hari ada yang "merapikan" keduanya menjadi satu method ber-parameter, uji
 * di sini yang harus menghalanginya.
 */
class RiwayatTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleLama;

    private int $cycleBaru;

    private int $levelId;

    private array $stok = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->stok = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->limit(10)
            ->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan'])
            ->all();

        $this->assertGreaterThanOrEqual(6, count($this->stok), 'Uji ini butuh beberapa karyawan.');

        $this->levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');

        $this->cycleLama = $this->buatSiklus((int) date('Y') - 1);
        $this->cycleBaru = $this->buatSiklus((int) date('Y'));
    }

    public function test_peserta_tanpa_hasil_tetap_muncul(): void
    {
        $this->buatPeserta($this->cycleBaru, null, null, null);

        $r = $this->service()->riwayatAdmin(['cycle_id' => $this->cycleBaru]);

        /* LEFT JOIN, bukan JOIN. Justru merekalah pekerjaan yang belum selesai; membuang
           mereka dari daftar berarti orang yang paling perlu ditindaklanjuti tidak pernah
           ditemukan lagi. */
        $this->assertSame(1, $r['total']);
        $this->assertNull($r['baris'][0]['Final_Score']);
        $this->assertNull($r['baris'][0]['Status_Hasil']);
    }

    public function test_hasil_belum_terbit_dan_tertahan_ikut_terbawa(): void
    {
        $this->buatPeserta($this->cycleBaru, 80.0, 'B', 'DIHITUNG');
        $this->buatPeserta($this->cycleBaru, null, null, 'TERTAHAN');

        $status = array_column(
            $this->service()->riwayatAdmin(['cycle_id' => $this->cycleBaru])['baris'],
            'Status_Hasil',
        );

        // Berbeda dari riwayat peserta, yang hanya memulangkan TERBIT.
        $this->assertContains('DIHITUNG', $status);
        $this->assertContains('TERTAHAN', $status);
    }

    public function test_kalibrasi_ditandai_dari_flag_bukan_dari_selisih_grade(): void
    {
        /* Uji ini DITULIS ULANG pada 2 September 2026. Versi lamanya menegaskan aturan
           yang sengaja dicabut: `dikalibrasi` diturunkan dari selisih `Kode_Grade_Sistem`
           dan `Kode_Grade_Final`.

           Aturan itu keliru, dan kekeliruannya justru pada kasus yang paling
           berkonsekuensi: kalibrasi yang menggeser KUADRAN tanpa mengubah grade. Seorang
           peserta bisa dipindahkan dari POTENTIAL_CANDIDATE ke STRIVING_STAR — dari kotak
           4 ke kotak 1 pada matriks sembilan kotak, dan kuadran itulah yang dipakai untuk
           keputusan suksesi — dengan grade tetap B. Aturan selisih-grade membacanya
           sebagai "tidak pernah dikalibrasi".

           Ketiga baris di bawah menutup ketiga kombinasinya, dan baris ketiga adalah
           yang membuat uji ini ada. */

        // Tidak dikalibrasi: grade sama, flag mati.
        $this->buatPeserta($this->cycleBaru, 80.0, 'B', 'DIHITUNG', gradeSistem: 'B');

        // Dikalibrasi dan grade-nya memang bergeser — kasus yang aturan lama pun benar.
        $this->buatPeserta($this->cycleBaru, 85.0, 'A', 'DIHITUNG',
            gradeSistem: 'B_PLUS', flagDikalibrasi: 'Y');

        /* Dikalibrasi TETAPI grade-nya tidak bergeser. Inilah yang dulu terbaca `false`.
           Kalau suatu saat seseorang mengembalikan aturan selisih-grade, baris inilah
           yang akan memerah — dan pesannya menjelaskan kenapa. */
        $this->buatPeserta($this->cycleBaru, 78.0, 'C', 'DIHITUNG',
            gradeSistem: 'C', flagDikalibrasi: 'Y');

        $baris = collect($this->service()->riwayatAdmin(['cycle_id' => $this->cycleBaru])['baris'])
            ->keyBy('Kode_Grade_Final');

        $this->assertFalse($baris['B']['dikalibrasi']);
        $this->assertTrue($baris['A']['dikalibrasi']);
        $this->assertTrue(
            $baris['C']['dikalibrasi'],
            'Kalibrasi yang hanya menggeser kuadran — grade sistem dan final SAMA — tetap '
            .'kalibrasi. Menurunkannya dari selisih grade membuat penyesuaian yang paling '
            .'menentukan justru tak terlihat.'
        );

        /* Kolom mentahnya TIDAK boleh ikut terbit. Dua kunci yang menyatakan hal sama akan
           dipakai bergantian oleh layar yang berbeda, dan suatu hari salah satunya berubah
           arti sementara yang lain tidak. */
        $this->assertArrayNotHasKey('Flag_Dikalibrasi', $baris['A']);
    }

    public function test_tren_urut_terlama_lebih_dulu_sedangkan_tabel_terbaru_lebih_dulu(): void
    {
        $this->buatPeserta($this->cycleLama, 70.0, 'C', 'DIHITUNG');
        $this->buatPeserta($this->cycleBaru, 90.0, 'A', 'DIHITUNG');

        // SENGAJA tanpa saringan: yang diuji adalah pengurutan global, dan menyaringnya
        // ke siklus uji justru menghapus yang hendak dibuktikan.
        $r = $this->service()->riwayatAdmin();

        /* Diukur RELATIF, bukan dengan angka mutlak.
           Basis data pengembangan memuat siklus lain yang sah - versi pertama uji ini
           menuntut tepat dua kelompok tren dan merah karena siklus contoh dev ikut
           terhitung, yaitu karena datanya bertambah, bukan karena kodenya salah.

           Dan dikunci pada ID SIKLUS, bukan tahun: `tren()` mengelompokkan per siklus,
           sehingga dua siklus dalam satu tahun akan mematahkan asumsi "satu tahun satu
           titik" bahkan di basis data kosong. */
        $urutan = array_column($r['tren'], 'id_cycle');

        $posisiLama = array_search($this->cycleLama, $urutan, true);
        $posisiBaru = array_search($this->cycleBaru, $urutan, true);

        $this->assertNotFalse($posisiLama, 'Siklus lama harus muncul di tren.');
        $this->assertNotFalse($posisiBaru, 'Siklus baru harus muncul di tren.');
        $this->assertLessThan($posisiBaru, $posisiLama, 'Tren dibaca kiri ke kanan: terlama dulu.');

        /* Tabelnya kebalikannya, dan itu disengaja - tabel dibaca dari yang paling
           relevan. Diuji sebagai SIFAT (deret tahunnya tidak pernah naik), bukan dengan
           menebak isi baris pertama.

           JUJUR TENTANG KEKUATANNYA: pemeriksaan ini LEMAH di basis data pengembangan.
           Halaman pertama didominasi satu siklus, sehingga deret tahunnya seragam dan
           sifatnya nyaris mustahil gagal. Yang benar-benar menguji pengurutan adalah
           penegasan tren di atas, tempat kedua siklus buatan uji sungguh berdampingan.
           Ini dibiarkan sebagai jaring pengaman, bukan sebagai bukti. */
        $tahun = array_map(fn ($b) => (int) $b['Tahun'], $r['baris']);
        $menurun = $tahun;
        rsort($menurun);

        $this->assertSame($menurun, $tahun, 'Tabel harus terurut terbaru lebih dulu.');
    }

    public function test_tren_memakai_saringan_yang_sama_dengan_tabelnya(): void
    {
        $this->buatPeserta($this->cycleLama, 70.0, 'C', 'DIHITUNG');
        $this->buatPeserta($this->cycleBaru, 90.0, 'A', 'DIHITUNG');

        $r = $this->service()->riwayatAdmin(['cycle_id' => $this->cycleBaru]);

        /* Grafik yang mengabaikan saringan akan memperlihatkan tren seluruh perusahaan di
           samping tabel satu divisi, dan orang akan membandingkan keduanya seolah
           keduanya bicara tentang hal yang sama. */
        $this->assertCount(1, $r['tren']);
        $this->assertSame(90.0, $r['tren'][0]['rata_rata']);
    }

    public function test_tren_berupa_angka_bukan_teks(): void
    {
        $this->buatPeserta($this->cycleBaru, 80.0, 'B', 'DIHITUNG');
        $this->buatPeserta($this->cycleBaru, 90.0, 'A', 'DIHITUNG');

        $tren = $this->service()->riwayatAdmin(['cycle_id' => $this->cycleBaru])['tren'][0];

        $this->assertIsFloat($tren['rata_rata']);
        $this->assertSame(85.0, $tren['rata_rata']);
        $this->assertIsInt($tren['jumlah']);
    }

    public function test_saringan_grade_mempersempit(): void
    {
        $this->buatPeserta($this->cycleBaru, 80.0, 'B', 'DIHITUNG');
        $this->buatPeserta($this->cycleBaru, 90.0, 'A', 'DIHITUNG');

        $r = $this->service()->riwayatAdmin(['cycle_id' => $this->cycleBaru, 'kode_grade' => 'A']);

        $this->assertSame(1, $r['total']);
        $this->assertSame('A', $r['baris'][0]['Kode_Grade_Final']);
    }

    public function test_permintaan_di_atas_plafon_dilaporkan_dipangkas(): void
    {
        $r = $this->service()->riwayatAdmin(['cycle_id' => $this->cycleBaru, 'per_halaman' => 5000]);

        $this->assertTrue($r['dipangkas']);
        $this->assertSame(PaRiwayatService::MAKS_PER_HALAMAN, $r['per_halaman']);
    }

    /* ------------------------------------------------------------------ *
     * Bangunan uji                                                        *
     * ------------------------------------------------------------------ */

    private function service(): PaRiwayatService
    {
        return $this->app->make(PaRiwayatService::class);
    }

    private function buatSiklus(int $tahun): int
    {
        $nama = 'Siklus Uji Riwayat '.$tahun.' '.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => 'UJI-RIW-'.substr(bin2hex(random_bytes(4)), 0, 8),
            'Nama_Cycle' => $nama,
            'Tahun' => $tahun,
            'Status_Cycle' => 'CLOSED',
        ]);

        return (int) DB::table('N_PA_Cycle')->where('Nama_Cycle', $nama)->value('Id_PA_Cycle');
    }

    private function buatPeserta(
        int $cycleId,
        ?float $skor,
        ?string $grade,
        ?string $status,
        ?string $gradeSistem = null,
        string $flagDikalibrasi = 'T',
    ): int {
        $k = array_shift($this->stok);
        $this->assertNotNull($k, 'Stok karyawan uji habis.');

        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $cycleId,
            'Kode_Perusahaan' => $k->Kode_Perusahaan,
            'Kode_Karyawan' => $k->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $k->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $k->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $this->levelId,
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        $participantId = (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Kode_Karyawan', $k->Kode_Karyawan)
            ->value('Id_PA_Participant');

        if ($status !== null) {
            DB::table('N_PA_Result')->insert([
                'PA_Participant_Id' => $participantId,
                'Final_Score' => $skor,
                'Kode_Grade_Final' => $grade,
                'Kode_Grade_Sistem' => $gradeSistem ?? $grade,
                'Flag_Dikalibrasi' => $flagDikalibrasi,
                'Status_Hasil' => $status,
            ]);
        }

        return $participantId;
    }
}
