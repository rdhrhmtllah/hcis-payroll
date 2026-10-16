<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Support;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Satu lembar penilaian yang SUDAH TERISI, milik user uji sebagai penilai.
 *
 * Baris payload dan skornya ditulis langsung. Itu sah untuk uji yang membaca skor
 * (PaSkorEfektif, wizard, kalibrasi), karena yang diuji adalah pembacanya, bukan pemeta.
 * Uji yang MEMBUTUHKAN jalur pemeta — "kalibrasi selamat melewati petakanUlang()" — tidak
 * memakai lembar dari trait ini dan mengirim payload sungguhan lewat PaPayloadMapperService.
 *
 * Setiap siklus dibuat sendiri, dan pemanggil WAJIB memakai `cycle_id` dari hasilnya:
 * basis data pengembangan bisa punya siklus OPEN lain (mis. siklus contoh presentasi) yang
 * memuat user uji yang sama.
 */
trait MenyiapkanLembarPenilaian
{
    protected function penilaiUji(): User
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
     * @param  array{status_siklus?:string, status_penugasan?:string, kategori?:string,
     *               skor?:list<float|int>, skor_maksimal?:float|int, cycle_id?:int,
     *               penilai?:User, boleh_kalibrasi?:string, naratif?:string,
     *               esai_per_soal?:list<array{id?:?int, pertanyaan?:?string, jawaban?:?string}>}  $opsi
     * @return array{cycle_id:int, assignment_id:int, participant_id:int, payload_id:int,
     *               kategori_id:int, kompetensi:list<int>, penilai:object}
     */
    protected function lembarTerisi(array $opsi = []): array
    {
        $user = $opsi['penilai'] ?? $this->penilaiUji();

        $penilai = DB::table('Karyawan')
            ->where('UserID_Web', $user->Id_Users)
            ->where('Aktif', 'Y')
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama']);

        $cycleId = $opsi['cycle_id'] ?? $this->siklusLembar($opsi['status_siklus'] ?? 'OPEN');

        $kodeKategori = $opsi['kategori'] ?? 'ATASAN';
        $kategoriId = (int) DB::table('N_PA_Rater_Category')
            ->where('Kode_Kategori', $kodeKategori)
            ->value('Id_PA_Rater_Category');
        $this->assertGreaterThan(0, $kategoriId, "Kategori {$kodeKategori} tidak ada di master.");

        if (isset($opsi['boleh_kalibrasi'])) {
            DB::table('N_PA_Rater_Category')
                ->where('Id_PA_Rater_Category', $kategoriId)
                ->update(['Flag_Boleh_Kalibrasi' => $opsi['boleh_kalibrasi']]);
        }

        // Orang yang dinilai: karyawan aktif yang belum menjadi peserta di siklus ini.
        $dinilai = DB::table('Karyawan as k')
            ->where('k.Aktif', 'Y')
            ->whereNotNull('k.ID_Level_Jabatan')
            ->where('k.Kode_Karyawan', '<>', $penilai->Kode_Karyawan)
            ->whereNotExists(function ($q) use ($cycleId) {
                $q->from('N_PA_Participant as p')
                    ->whereColumn('p.Kode_Karyawan', 'k.Kode_Karyawan')
                    ->where('p.PA_Cycle_Id', $cycleId);
            })
            ->orderBy('k.Kode_Karyawan')
            ->first(['k.Kode_Perusahaan', 'k.Kode_Karyawan', 'k.Nama', 'k.ID_Level_Jabatan']);

        $levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');

        $participantId = (int) DB::table('N_PA_Participant')->insertGetId([
            'PA_Cycle_Id' => $cycleId,
            'Kode_Perusahaan' => $dinilai->Kode_Perusahaan,
            'Kode_Karyawan' => $dinilai->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $dinilai->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $dinilai->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $levelId,
            'Status_Peserta' => 'DIIKUTKAN',
        ], 'Id_PA_Participant');

        $assignmentId = (int) DB::table('N_PA_Assignment')->insertGetId([
            'PA_Participant_Id' => $participantId,
            'Kode_Perusahaan_Penilai' => $penilai->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $penilai->Kode_Karyawan,
            'Nama_Penilai_Saat_Cycle' => $penilai->Nama,
            'PA_Rater_Category_Id' => $kategoriId,
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => $opsi['status_penugasan'] ?? 'TERKUMPUL',
            'Due_At' => now()->addDays(7)->format('Y-m-d H:i:s'),
        ], 'Id_PA_Assignment');

        $integrationId = (int) DB::table('N_PA_Assignment_Integration')->insertGetId([
            'PA_Assignment_Id' => $assignmentId,
            'Status_Integrasi' => 'BERHASIL',
            'Flag_Aktif' => 'Y',
        ], 'Id_PA_Assignment_Integration');

        $payloadId = (int) DB::table('N_PA_Assignment_Payload')->insertGetId([
            'PA_Assignment_Integration_Id' => $integrationId,
            'Payload_Json' => '{}',
            // CK_PA_Assignment_Payload_Hash menuntut 64 karakter; Terpeta 'Y' wajib berversi pemeta.
            'Payload_Hash' => hash('sha256', random_bytes(16)),
            'Flag_Terpeta' => 'Y',
            'Mapper_Version' => 1,
            'Status_Skor' => 'FINAL',
        ], 'Id_PA_Assignment_Payload');

        $skor = $opsi['skor'] ?? [70.0, 85.0];
        $maks = $opsi['skor_maksimal'] ?? 100.0;

        $kompetensi = DB::table('N_PA_Competency')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Berskor', 'Y')
            ->orderBy('Id_PA_Competency')
            ->limit(count($skor))
            ->pluck('Id_PA_Competency')
            ->map(fn ($x) => (int) $x)
            ->all();
        $this->assertCount(count($skor), $kompetensi, 'Master kompetensi berskor kurang.');

        foreach ($skor as $i => $nilai) {
            DB::table('N_PA_Assignment_Score')->insert([
                'PA_Assignment_Payload_Id' => $payloadId,
                'PA_Competency_Id' => $kompetensi[$i],
                'Kode_Soal_Sumber' => 'UJI'.($i + 1),
                'Skor' => $nilai,
                'Skor_Maksimal' => $maks,
                'Mapper_Version' => 1,
                'Urutan_Tampil' => $i + 1,
                'Flag_Aktif' => 'Y',
            ]);
        }

        /* Jawaban naratif opsional, pada kompetensi tak berskor. Uji kontrak membutuhkannya:
           pembanding bentuk menganggap daftar kosong berbeda dari daftar berisi, jadi tanpa
           satu jawaban naratif bentuk `naratif[]` tidak pernah terbandingkan. */
        if (! empty($opsi['naratif'])) {
            DB::table('N_PA_Assignment_Score')->insert([
                'PA_Assignment_Payload_Id' => $payloadId,
                'PA_Competency_Id' => DB::table('N_PA_Competency')->where('Flag_Aktif', 'Y')
                    ->where('Flag_Berskor', 'T')->orderBy('Id_PA_Competency')->value('Id_PA_Competency'),
                'Kode_Soal_Sumber' => 'UJI-TEKS',
                'Skor' => null,
                'Skor_Maksimal' => null,
                'Jawaban_Teks' => (string) $opsi['naratif'],
                'Mapper_Version' => 1,
                'Urutan_Tampil' => count($skor) + 1,
                'Flag_Aktif' => 'Y',
            ]);
        }

        /* Esai format per soal (sejak 26 Sep 2026): satu baris skor bagian tanpa skor dan
           tanpa teks, dengan satu anak per soal di N_PA_Assignment_Score_Esai. */
        if (! empty($opsi['esai_per_soal'])) {
            $scoreId = (int) DB::table('N_PA_Assignment_Score')->insertGetId([
                'PA_Assignment_Payload_Id' => $payloadId,
                'PA_Competency_Id' => DB::table('N_PA_Competency')->where('Flag_Aktif', 'Y')
                    ->where('Flag_Berskor', 'T')->orderBy('Id_PA_Competency')->value('Id_PA_Competency'),
                'Kode_Soal_Sumber' => 'UJI-ESAI',
                'Skor' => null,
                'Skor_Maksimal' => null,
                'Mapper_Version' => 1,
                'Urutan_Tampil' => count($skor) + 2,
                'Flag_Aktif' => 'Y',
            ], 'Id_PA_Assignment_Score');

            foreach (array_values($opsi['esai_per_soal']) as $i => $soal) {
                DB::table('N_PA_Assignment_Score_Esai')->insert([
                    'PA_Assignment_Score_Id' => $scoreId,
                    'Id_Master_Soal' => $soal['id'] ?? null,
                    'Urutan_Tampil' => $i + 1,
                    'Pertanyaan' => $soal['pertanyaan'] ?? null,
                    'Jawaban_Teks' => $soal['jawaban'] ?? null,
                ]);
            }
        }

        return [
            'cycle_id' => $cycleId,
            'assignment_id' => $assignmentId,
            'participant_id' => $participantId,
            'payload_id' => $payloadId,
            'kategori_id' => $kategoriId,
            'kompetensi' => $kompetensi,
            'penilai' => $penilai,
        ];
    }

    protected function siklusLembar(string $status = 'OPEN'): int
    {
        return (int) DB::table('N_PA_Cycle')->insertGetId([
            'Kode_Cycle' => 'UJI-KLB-'.substr(bin2hex(random_bytes(4)), 0, 8),
            'Nama_Cycle' => 'Siklus Uji Kalibrasi Penilai',
            'Tahun' => (int) date('Y'),
            'Status_Cycle' => $status,
            'Flag_Aktif' => 'Y',
            'Pengisian_Dari' => now()->subDay()->format('Y-m-d H:i:s'),
            'Pengisian_Sampai' => now()->addDays(20)->format('Y-m-d H:i:s'),
        ], 'Id_PA_Cycle');
    }

    /** Menyimpan kalibrasi langsung ke tabel — untuk uji pembaca, bukan uji penulis. */
    protected function kalibrasiLangsung(int $assignmentId, int $competencyId, float $sebelum, float $sesudah, float $maks = 100.0): int
    {
        $kepala = (int) DB::table('N_PA_Kalibrasi_Penilai')->insertGetId([
            'PA_Assignment_Id' => $assignmentId,
            'Alasan' => 'Uji',
            'Created_By' => 1,
        ], 'Id_PA_Kalibrasi_Penilai');

        DB::table('N_PA_Kalibrasi_Penilai_Detail')->insert([
            'PA_Kalibrasi_Penilai_Id' => $kepala,
            'PA_Competency_Id' => $competencyId,
            'Skor_Sebelum' => $sebelum,
            'Skor_Sesudah' => $sesudah,
            'Skor_Maksimal' => $maks,
        ]);

        return $kepala;
    }
}
