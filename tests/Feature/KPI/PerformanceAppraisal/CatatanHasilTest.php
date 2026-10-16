<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaResultNoteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;

/**
 * Catatan hasil: dua sumber dalam satu tabel, dan batas di antaranya.
 *
 * `N_PA_Result_Note` menampung dua hal yang terlihat sama di layar tetapi berbeda bobotnya —
 * kalimat yang diambil dari jawaban penilai (`PA_Assignment_Id` terisi) dan kalimat yang
 * diketik admin (`PA_Assignment_Id` NULL). Batas itu dijaga dari DUA arah, dan berkas ini
 * menguji keduanya: menyimpan catatan admin tidak menyentuh catatan penilai, dan perhitungan
 * ulang tidak menghapus tulisan admin.
 *
 * Tanpa salah satunya, satu kali hitung ulang menghapus tulisan admin tanpa ada yang tahu —
 * kegagalan yang tidak pernah muncul sebagai galat, hanya sebagai teks yang hilang.
 */
class CatatanHasilTest extends TestCase
{
    use DatabaseTransactions;

    private int $participantId;

    private int $resultId;

    private int $assignmentId;

    protected function setUp(): void
    {
        parent::setUp();

        $karyawan = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan']);

        $this->assertNotNull($karyawan, 'Basis data pengembangan tidak punya karyawan aktif.');

        $nama = 'Siklus Uji Catatan '.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => 'UJI-CATATAN-'.substr(bin2hex(random_bytes(4)), 0, 8),
            'Nama_Cycle' => $nama,
            'Tahun' => (int) date('Y'),
            'Status_Cycle' => 'OPEN',
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

        $this->participantId = (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Kode_Karyawan', $karyawan->Kode_Karyawan)
            ->value('Id_PA_Participant');

        DB::table('N_PA_Assignment')->insert([
            'PA_Participant_Id' => $this->participantId,
            'Kode_Perusahaan_Penilai' => $karyawan->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $karyawan->Kode_Karyawan,
            'Nama_Penilai_Saat_Cycle' => $karyawan->Nama,
            'PA_Rater_Category_Id' => (int) DB::table('N_PA_Rater_Category')
                ->where('Kode_Kategori', 'SELF')->value('Id_PA_Rater_Category'),
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => 'SELESAI',
        ]);

        $this->assignmentId = (int) DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $this->participantId)
            ->value('Id_PA_Assignment');

        DB::table('N_PA_Result')->insert([
            'PA_Participant_Id' => $this->participantId,
            'Status_Hasil' => 'DIHITUNG',
            'Final_Score' => 80,
            'Kode_Grade_Final' => 'B',
            'Kode_Grade_Sistem' => 'B',
        ]);

        $this->resultId = (int) DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $this->participantId)
            ->value('Id_PA_Result');
    }

    public function test_catatan_admin_tersimpan_dan_bertanda_bukan_dari_penilai(): void
    {
        $hasil = $this->service()->simpanCatatanAdmin($this->participantId, [
            'QL01' => ['Konsisten menyelesaikan pekerjaan', 'Terbuka menerima masukan'],
            'QL02' => ['Delegasi tugas harian'],
        ], 1);

        $this->assertSame(3, $hasil['ditulis']);

        $baris = DB::table('N_PA_Result_Note')
            ->where('PA_Result_Id', $this->resultId)
            ->where('Flag_Aktif', 'Y')
            ->get(['PA_Assignment_Id', 'Isi']);

        $this->assertCount(3, $baris);

        /* `PA_Assignment_Id` NULL adalah SATU-SATUNYA penanda "ditulis admin", dan
           perhitungan ulang memakainya untuk tidak menghapus tulisan itu. Kalau ia
           terisi, tulisan admin ikut tersapu pada hitung ulang berikutnya. */
        foreach ($baris as $b) {
            $this->assertNull($b->PA_Assignment_Id);
        }
    }

    public function test_menyimpan_ulang_mengganti_bukan_menggandakan(): void
    {
        $this->service()->simpanCatatanAdmin($this->participantId, [
            'QL01' => ['Versi pertama'],
        ], 1);

        $hasil = $this->service()->simpanCatatanAdmin($this->participantId, [
            'QL01' => ['Versi kedua'],
        ], 1);

        $this->assertSame(1, $hasil['dicabut']);

        $aktif = DB::table('N_PA_Result_Note')
            ->where('PA_Result_Id', $this->resultId)
            ->where('Flag_Aktif', 'Y')
            ->pluck('Isi');

        // Layarnya kotak teks berisi seluruh daftar, jadi yang dikirim adalah keadaan
        // akhir. Menambahkannya baris demi baris menggandakan isi tiap kali disimpan.
        $this->assertSame(['Versi kedua'], $aktif->all());

        /* Yang lama DINONAKTIFKAN, bukan dihapus: enam bulan kemudian, satu-satunya
           keterangan bahwa catatan seseorang pernah berbunyi lain adalah baris itu. */
        $this->assertSame(1, DB::table('N_PA_Result_Note')
            ->where('PA_Result_Id', $this->resultId)
            ->where('Flag_Aktif', 'T')
            ->count());
    }

    public function test_catatan_penilai_tidak_ikut_tersentuh(): void
    {
        DB::table('N_PA_Result_Note')->insert([
            'PA_Result_Id' => $this->resultId,
            'PA_Competency_Id' => $this->kompetensiKualitatif('QL01'),
            'PA_Assignment_Id' => $this->assignmentId,
            'Isi' => 'Kalimat dari penilai',
            'Urutan_Tampil' => 1,
            'Flag_Aktif' => 'Y',
        ]);

        $this->service()->simpanCatatanAdmin($this->participantId, [
            'QL01' => ['Kalimat dari admin'],
        ], 1);

        $dariPenilai = DB::table('N_PA_Result_Note')
            ->where('PA_Result_Id', $this->resultId)
            ->whereNotNull('PA_Assignment_Id')
            ->where('Flag_Aktif', 'Y')
            ->pluck('Isi');

        // Observasi banyak orang tidak boleh terhapus oleh kesimpulan satu orang.
        $this->assertSame(['Kalimat dari penilai'], $dariPenilai->all());
    }

    public function test_baris_kosong_dibuang(): void
    {
        $hasil = $this->service()->simpanCatatanAdmin($this->participantId, [
            // Kotak teks berbaris banyak hampir selalu menyisakan baris kosong di ujungnya.
            'QL01' => ['Poin pertama', '', '   ', 'Poin kedua'],
        ], 1);

        $this->assertSame(2, $hasil['ditulis']);
    }

    public function test_hasil_yang_sudah_terbit_ditolak(): void
    {
        DB::table('N_PA_Result')->where('Id_PA_Result', $this->resultId)
            ->update(['Status_Hasil' => 'TERBIT']);

        try {
            $this->service()->simpanCatatanAdmin($this->participantId, ['QL01' => ['Terlambat']], 1);
            $this->fail('Hasil TERBIT seharusnya menolak penyuntingan catatan.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('sudah TERBIT', $e->getMessage());
        }

        $this->assertSame(0, DB::table('N_PA_Result_Note')
            ->where('PA_Result_Id', $this->resultId)->count());
    }

    public function test_kategori_catatan_yang_tidak_dikenal_ditolak(): void
    {
        try {
            $this->service()->simpanCatatanAdmin($this->participantId, ['NGAWUR' => ['isi']], 1);
            $this->fail('Kategori catatan karangan seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            throw $gagal;
        } catch (RuntimeException $e) {
            // Pesannya menyebut yang ADA, bukan sekadar menolak: admin yang salah ketik
            // butuh tahu pilihannya, bukan diberi tahu bahwa ia salah.
            $this->assertStringContainsString('tidak dikenal', $e->getMessage());
            $this->assertStringContainsString('QL01', $e->getMessage());
        }
    }

    /**
     * Kategori catatan yang DITOLAK justru kategori berskor.
     *
     * Kompetensi berskor dirata-ratakan menjadi angka; kalimat yang ditaruh di bawahnya
     * akan muncul di report sebagai catatan yang tak seorang pun menulisnya sebagai
     * catatan. Tanpa uji ini, satu kata terlewat pada saringan `KUALITATIF` membuat
     * seluruh master kompetensi — puluhan baris — menjadi kotak isian di layar admin.
     */
    public function test_kompetensi_berskor_bukan_kategori_catatan(): void
    {
        $berskor = DB::table('N_PA_Competency as k')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'k.PA_Competency_Type_Id')
            ->where('k.Flag_Aktif', 'Y')
            ->where('t.Kode_Type', '<>', 'KUALITATIF')
            ->value('k.Kode_Competency');

        $this->assertNotNull($berskor, 'Master kompetensi berskor kosong; uji ini tidak menguji apa pun.');

        try {
            $this->service()->simpanCatatanAdmin($this->participantId, [$berskor => ['isi']], 1);
            $this->fail("Kompetensi berskor {$berskor} seharusnya bukan kategori catatan.");
        } catch (AssertionFailedError $gagal) {
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tidak dikenal', $e->getMessage());
        }
    }

    private function kompetensiKualitatif(string $kode): int
    {
        $id = DB::table('N_PA_Competency')->where('Kode_Competency', $kode)
            ->value('Id_PA_Competency');

        $this->assertNotNull($id, "Master kompetensi kualitatif {$kode} tidak ada.");

        return (int) $id;
    }

    private function service(): PaResultNoteService
    {
        return $this->app->make(PaResultNoteService::class);
    }
}
