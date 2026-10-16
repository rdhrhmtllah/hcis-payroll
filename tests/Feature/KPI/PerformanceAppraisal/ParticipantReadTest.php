<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaParticipantReadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Daftar peserta, detail hasil, dan HAV Map — sisi admin.
 *
 * Bedanya dengan `PaPesertaService` adalah inti berkas ini: yang ini SENGAJA menampilkan
 * hasil yang belum terbit, yang tertahan beserta sebabnya, dan nilai PER KATEGORI
 * PENILAI. Karyawan tidak pernah melihat ketiganya. Kalau suatu hari ada yang menyatukan
 * keduanya, uji di sini yang harus menghalanginya.
 */
class ParticipantReadTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleId;

    private int $levelId;

    private array $stok = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->stok = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->limit(8)
            ->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan'])
            ->all();

        $this->assertGreaterThanOrEqual(4, count($this->stok), 'Uji ini butuh beberapa karyawan.');

        $this->levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $this->cycleId = $this->buatSiklus();
    }

    public function test_daftar_berhalaman_dan_melaporkan_pemangkasan(): void
    {
        $this->buatPeserta();
        $this->buatPeserta();

        $hasil = $this->service()->daftar($this->cycleId, ['per_halaman' => 5000]);

        $this->assertSame(2, $hasil['total']);
        // Pembatasan yang tidak diberitahukan membuat orang mengira datanya memang segitu.
        $this->assertTrue($hasil['dipangkas']);
    }

    public function test_saringan_status_peserta_mempersempit(): void
    {
        $this->buatPeserta(status: 'DIIKUTKAN');
        $this->buatPeserta(status: 'DIKECUALIKAN');

        $semua = $this->service()->daftar($this->cycleId);
        $satu = $this->service()->daftar($this->cycleId, ['status_peserta' => 'DIKECUALIKAN']);

        $this->assertSame(2, $semua['total']);
        $this->assertSame(1, $satu['total'], 'Saringan harus benar-benar mempersempit.');
    }

    public function test_ringkasan_mencacah_per_status(): void
    {
        $this->buatPeserta(status: 'DIIKUTKAN');
        $this->buatPeserta(status: 'DIIKUTKAN');
        $this->buatPeserta(status: 'DIKECUALIKAN');

        $r = $this->service()->ringkasan($this->cycleId);

        /* Versi pertama uji ini hanya menegaskan `assertIsArray`, dan itu TIDAK MENGUJI
           APA PUN: ia tetap hijau seandainya cacahnya nol semua, tertukar antar status,
           atau mengabaikan cycleId sepenuhnya - bahkan lolos pada nilai awal yang
           di-hardcode sebelum satu kueri pun dijalankan. */
        $this->assertSame(2, $r['DIIKUTKAN']);
        $this->assertSame(1, $r['DIKECUALIKAN']);

        /* Ketiga kunci SELALU ada, diisi nol lebih dulu. Layar yang kehilangan kunci
           TERTUNDA akan menggambar kolom kosong tanpa tahu ia kehilangan sesuatu, jadi
           janji itu layak dijaga di sini. */
        $this->assertSame(0, $r['TERTUNDA']);
        $this->assertSame(['DIIKUTKAN', 'DIKECUALIKAN', 'TERTUNDA'], array_keys($r));
    }

    public function test_detail_hasil_menampilkan_sebab_tertahan(): void
    {
        $p = $this->buatPeserta();

        DB::table('N_PA_Result')->insert([
            'PA_Participant_Id' => $p,
            'Status_Hasil' => 'TERTAHAN',
            'Alasan_Tertahan' => 'Tidak ada KPI disetujui pada rentang periode siklus.',
        ]);

        $detail = $this->service()->detailHasil($p);

        /* Di bawah `peserta`, BUKAN `hasil`. Kolom peserta dan kolom hasil digabung lewat
           leftJoin menjadi satu baris datar - `Status_Hasil`, `Final_Score`, dan
           `Alasan_Tertahan` semuanya tinggal di sana.

           Dan diambil TANPA `?? ''`. Peredam itu menyamakan "salah alamat" dengan
           "sebabnya memang kosong": keduanya menghasilkan pesan gagal yang identik,
           sehingga uji ini tidak bisa membedakan kunci yang keliru dari data yang hilang.
           Tanpa peredam, PHP langsung menyebut kunci yang tidak ada. */
        $this->assertArrayHasKey('peserta', $detail);
        $this->assertArrayHasKey('Alasan_Tertahan', $detail['peserta']);

        /* Justru di layar inilah sebab tertahan dibaca. Menyembunyikannya berarti menutup
           satu-satunya tempat yang bisa menjelaskan kenapa seseorang tidak dinilai. */
        $this->assertStringContainsString(
            'KPI disetujui',
            (string) $detail['peserta']['Alasan_Tertahan'],
        );

        $this->assertSame('TERTAHAN', $detail['peserta']['Status_Hasil']);
    }

    public function test_detail_hasil_memuat_keterlambatan_tiap_penilai(): void
    {
        $p = $this->buatPeserta();
        $penilai = array_shift($this->stok);
        $kategoriId = (int) DB::table('N_PA_Rater_Category')
            ->where('Kode_Kategori', 'SELF')->value('Id_PA_Rater_Category');

        DB::table('N_PA_Assignment')->insert([
            'PA_Participant_Id' => $p,
            'Kode_Perusahaan_Penilai' => $penilai->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $penilai->Kode_Karyawan,
            'Nama_Penilai_Saat_Cycle' => $penilai->Nama,
            'PA_Rater_Category_Id' => $kategoriId,
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => 'BELUM_MULAI',
            'Due_At' => now()->subDays(3)->format('Y-m-d'),
        ]);

        $baris = $this->service()->detailHasil($p)['penilai'][0];

        // Diturunkan server, bukan dihitung layar — dua rumus untuk satu gagasan pasti
        // berselisih, dan yang berselisih di sini dipakai mengejar orang.
        $this->assertTrue($baris['terlambat']);
        $this->assertSame(3, $baris['hari_terlambat']);

        // Tenggat hari ini masih miliknya.
        DB::table('N_PA_Assignment')->where('PA_Participant_Id', $p)
            ->update(['Due_At' => now()->format('Y-m-d')]);

        $this->assertFalse($this->service()->detailHasil($p)['penilai'][0]['terlambat']);
    }

    public function test_hav_map_mengembalikan_sebaran_sembilan_kotak(): void
    {
        $p = $this->buatPeserta();
        $kuadranId = DB::table('N_PA_Hav_Quadrant')->value('Id_PA_Hav_Quadrant');

        DB::table('N_PA_Result')->insert([
            'PA_Participant_Id' => $p,
            'Final_Score' => 88.0,
            'Kode_Grade_Final' => 'A',
            'Kode_Grade_Sistem' => 'A',
            'PA_Hav_Quadrant_Id' => $kuadranId,
            'Status_Hasil' => 'DIHITUNG',
        ]);

        $peta = $this->service()->havMap($this->cycleId);

        $this->assertIsArray($peta);
        $this->assertNotEmpty($peta, 'HAV Map tidak boleh kosong ketika ada hasil berkuadran.');
    }

    public function test_hav_map_mengirim_pita_havmap_salinan_siklus_per_sumbu(): void
    {
        /* Grafik sebar menggambar batas kotak dari pita ini. Dulu batasnya ditulis tetap
           33,3/66,6 di layar, padahal kotak dihitung dari pita siklus (bawaan 70/85), sehingga
           titik tampil di kotak yang berbeda dari kotak sebenarnya. Angka di sini sengaja
           BUKAN bawaan, supaya uji gagal kalau pita dibaca dari master atau ditebak. */
        $this->salinPitaHavmap([
            'KPI' => ['RENDAH' => [0, 60], 'SEDANG' => [60, 80], 'TINGGI' => [80, 999.99]],
            'KOMPETENSI' => ['RENDAH' => [0, 65], 'SEDANG' => [65, 90], 'TINGGI' => [90, 999.99]],
        ]);

        $pita = $this->service()->havMap($this->cycleId)['pita'];

        $ringkas = fn (array $daftar) => array_map(
            fn ($p) => [$p['Kode_Range'], (float) $p['Nilai_Dari'], (float) $p['Nilai_Sampai']],
            $daftar
        );

        $this->assertSame(
            [['RENDAH', 0.0, 60.0], ['SEDANG', 60.0, 80.0], ['TINGGI', 80.0, 999.99]],
            $ringkas($pita['kpi'])
        );
        $this->assertSame(
            [['RENDAH', 0.0, 65.0], ['SEDANG', 65.0, 90.0], ['TINGGI', 90.0, 999.99]],
            $ringkas($pita['kompetensi'])
        );
    }

    public function test_hav_map_tanpa_pita_siklus_mengirim_daftar_kosong_bukan_tebakan(): void
    {
        $pita = $this->service()->havMap($this->cycleId)['pita'];

        $this->assertSame(['kpi' => [], 'kompetensi' => []], $pita);
    }

    /* ------------------------------------------------------------------ *
     * Bangunan uji                                                        *
     * ------------------------------------------------------------------ */

    private function service(): PaParticipantReadService
    {
        return $this->app->make(PaParticipantReadService::class);
    }

    private function buatSiklus(): int
    {
        $nama = 'Siklus Uji Baca Peserta '.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => 'UJI-BACA-'.substr(bin2hex(random_bytes(4)), 0, 8),
            'Nama_Cycle' => $nama,
            'Tahun' => (int) date('Y'),
            'Status_Cycle' => 'CLOSED',
        ]);

        return (int) DB::table('N_PA_Cycle')->where('Nama_Cycle', $nama)->value('Id_PA_Cycle');
    }

    /**
     * Menyalin pita HAVMAP dan FINAL|GRADE master ke siklus uji, dengan batas HAVMAP
     * ditimpa. Pita FINAL|GRADE dan satu pita nonaktif menjadi pengecoh.
     *
     * @param  array<string, array<string, array{0: float|int, 1: float|int}>>  $batas
     */
    private function salinPitaHavmap(array $batas): void
    {
        $master = DB::table('N_PA_Score_Range')
            ->where('Flag_Aktif', 'Y')
            ->where(fn ($q) => $q->where('Score_Usage', 'HAVMAP')
                ->orWhere(fn ($q) => $q->where('Score_Type', 'FINAL')->where('Score_Usage', 'GRADE')))
            ->get();

        $this->assertGreaterThanOrEqual(6, $master->where('Score_Usage', 'HAVMAP')->count(),
            'Uji ini butuh pita HAVMAP master untuk KPI dan KOMPETENSI.');

        // Urutan sisip dibalik supaya uji juga membuktikan pengurutan menurut Nilai_Dari.
        foreach ($master->reverse() as $p) {
            [$dari, $sampai] = $batas[$p->Score_Type][$p->Kode_Range] ?? [$p->Nilai_Dari, $p->Nilai_Sampai];

            DB::table('N_PA_Cycle_Score_Range_Snapshot')->insert([
                'PA_Cycle_Id' => $this->cycleId,
                'PA_Score_Range_Id' => $p->Id_PA_Score_Range,
                'Score_Type' => $p->Score_Type,
                'Score_Usage' => $p->Score_Usage,
                'Kode_Range' => $p->Kode_Range,
                'Nama_Range' => $p->Nama_Range,
                'Nilai_Dari' => $dari,
                'Nilai_Sampai' => $sampai,
                'Warna' => $p->Warna,
                'Urutan_Tampil' => $p->Urutan_Tampil,
            ]);
        }

        $pengecoh = $master->firstWhere('Score_Type', 'KPI');
        DB::table('N_PA_Cycle_Score_Range_Snapshot')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'PA_Score_Range_Id' => $pengecoh->Id_PA_Score_Range,
            'Score_Type' => 'KPI',
            'Score_Usage' => 'HAVMAP',
            'Kode_Range' => 'NONAKTIF',
            'Nama_Range' => 'Pita nonaktif',
            'Nilai_Dari' => 1,
            'Nilai_Sampai' => 2,
            'Urutan_Tampil' => 99,
            'Flag_Aktif' => 'T',
        ]);
    }

    private function buatPeserta(string $status = 'DIIKUTKAN'): int
    {
        $k = array_shift($this->stok);
        $this->assertNotNull($k, 'Stok karyawan uji habis.');

        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'Kode_Perusahaan' => $k->Kode_Perusahaan,
            'Kode_Karyawan' => $k->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $k->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $k->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $this->levelId,
            'Nama_Level_Saat_Cycle' => 'STAFF',
            'Status_Peserta' => $status,
            'Alasan_Dikecualikan' => $status === 'DIKECUALIKAN' ? 'alasan uji' : null,
        ]);

        return (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Kode_Karyawan', $k->Kode_Karyawan)
            ->value('Id_PA_Participant');
    }
}
