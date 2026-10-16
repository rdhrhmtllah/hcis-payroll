<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Kalibrasi — satu-satunya jalan menimpa hasil mesin.
 *
 * KENAPA SEBABNYA WAJIB
 *
 * Baris riwayat kalibrasi hanya lahir ketika seseorang menyatakan mesin keliru. Tanpa
 * sebab tertulis, yang tersisa setahun kemudian hanyalah grade yang tidak cocok dengan
 * angkanya sendiri, dan tak seorang pun bisa menjelaskan mana yang benar.
 *
 * KENAPA HASIL MESIN TIDAK DITIMPA
 *
 * `Kode_Grade_Sistem` tidak pernah berubah; yang bergeser hanya `Kode_Grade_Final`.
 * Keduanya berdampingan supaya pertanyaan "berapa hasil hitungannya sebelum digeser"
 * bisa dijawab tanpa menghitung ulang — dan tanpa mempercayai ingatan siapa pun.
 *
 * KENAPA YANG TERTAHAN TIDAK BISA DIKALIBRASI
 *
 * Peserta `TERTAHAN` belum punya nilai akhir. Menggeser grade yang tidak ada berarti
 * memberi angka lewat pintu belakang, melewati justru pemeriksaan yang menahannya.
 * Yang benar adalah memperbaiki sebab tertahannya lalu menghitung ulang.
 */
class PaCalibrationService
{
    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaKurvaService $kurva,
    ) {}

    /**
     * Menggeser grade akhir dan/atau kuadran satu hasil.
     *
     * @return array{
     *     result_id:int, kode_grade_sistem:string|null, kode_grade_final:string,
     *     kuadran_id:int|null, kode_kuadran:string|null
     * }
     */
    public function kalibrasi(
        int $resultId,
        ?string $kodeGradeBaru,
        ?int $kuadranBaruId,
        string $alasan,
        ?int $olehUserId = null,
        ?string $olehNama = null,
    ): array {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw new RuntimeException('Kalibrasi wajib beralasan.');
        }

        /* Performed_By NOT NULL. Ditolak di sini supaya sebabnya terbaca; kalau
           dibiarkan sampai INSERT, yang muncul adalah galat transaksi SQL Server yang
           menutupi sebab aslinya. */
        if ($olehUserId === null) {
            throw new RuntimeException(
                'Kalibrasi wajib menyebut pelakunya; riwayat kalibrasi tanpa pelaku '
                .'tidak bisa dipertanggungjawabkan.'
            );
        }

        $hasil = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('r.Id_PA_Result', $resultId)
            ->first([
                'r.Id_PA_Result', 'r.PA_Participant_Id', 'r.Status_Hasil',
                'r.Kode_Grade_Sistem', 'r.Kode_Grade_Final', 'r.PA_Hav_Quadrant_Id',
                'r.Flag_Aktif', 'p.PA_Cycle_Id',
            ]);

        if ($hasil === null) {
            throw new RuntimeException("Hasil {$resultId} tidak ada.");
        }

        if ($hasil->Flag_Aktif !== 'Y') {
            throw new RuntimeException("Hasil {$resultId} sudah tidak aktif.");
        }

        $this->gate->pastikanBoleh(PaCycleGate::KALIBRASI, (int) $hasil->PA_Cycle_Id);
        // Siklus KURVA: forum bekerja di atas grade sistem, yang baru ada sesudah kurva dikunci.
        $this->kurva->pastikanTerkunci((int) $hasil->PA_Cycle_Id);

        if ($hasil->Kode_Grade_Final === null) {
            throw new RuntimeException(
                "Hasil {$resultId} belum punya grade — statusnya {$hasil->Status_Hasil}. "
                .'Perbaiki dulu sebab tertahannya lalu hitung ulang; menggeser grade yang '
                .'tidak ada berarti memberi angka lewat pintu belakang.'
            );
        }

        $gradeSebelum = (string) $hasil->Kode_Grade_Final;
        $kuadranSebelum = $hasil->PA_Hav_Quadrant_Id === null ? null : (int) $hasil->PA_Hav_Quadrant_Id;

        $gradeSesudah = $kodeGradeBaru === null ? $gradeSebelum : trim($kodeGradeBaru);
        $kuadranSesudah = $kuadranBaruId ?? $kuadranSebelum;

        if ($gradeSesudah === $gradeSebelum && $kuadranSesudah === $kuadranSebelum) {
            // Riwayat yang tidak mencatat perubahan apa pun hanya menambah baris yang
            // harus dibaca orang tanpa memberi tahu apa-apa.
            throw new RuntimeException(
                'Kalibrasi tidak mengubah apa pun; grade dan kuadrannya sama dengan yang sekarang.'
            );
        }

        $this->pastikanGradeSah((int) $hasil->PA_Cycle_Id, $gradeSesudah);
        $this->pastikanKuadranSah($kuadranSesudah);

        $kodeKuadran = null;

        DB::transaction(function () use (
            $resultId, $gradeSebelum, $gradeSesudah, $kuadranSebelum, $kuadranSesudah,
            $alasan, $olehUserId, $olehNama, &$kodeKuadran
        ) {
            DB::table('N_PA_Calibration_History')->insert([
                'PA_Result_Id' => $resultId,
                'Kode_Grade_Sebelum' => $gradeSebelum,
                'Kode_Grade_Sesudah' => $gradeSesudah,
                'PA_Hav_Quadrant_Sebelum_Id' => $kuadranSebelum,
                'PA_Hav_Quadrant_Sesudah_Id' => $kuadranSesudah,
                'Alasan' => $alasan,
                /* Id pelakunya di Performed_By (int NOT NULL), NAMANYA dibekukan
                   terpisah di Performed_By_Nama. Keduanya, bukan salah satu: id
                   menyambung ke daftar user selama user itu masih ada, dan namanya tetap
                   terbaca ketika ia sudah tidak ada. */
                'Performed_By' => $olehUserId,
                'Performed_By_Nama' => $olehNama,
                'Performed_At' => FormatTanggalHelper::getCurrentTime(),
            ]);

            DB::table('N_PA_Result')
                ->where('Id_PA_Result', $resultId)
                ->update([
                    // Kode_Grade_Sistem SENGAJA tidak disentuh.
                    'Kode_Grade_Final' => $gradeSesudah,
                    'PA_Hav_Quadrant_Id' => $kuadranSesudah,
                    'Flag_Dikalibrasi' => 'Y',
                    'Updated_At' => FormatTanggalHelper::getCurrentTime(),
                    'Updated_By' => $olehUserId,
                ]);

            if ($kuadranSesudah !== null) {
                $kodeKuadran = DB::table('N_PA_Hav_Quadrant')
                    ->where('Id_PA_Hav_Quadrant', $kuadranSesudah)
                    ->value('Kode_Kuadran');
            }
        });

        return [
            'result_id' => $resultId,
            'kode_grade_sistem' => $hasil->Kode_Grade_Sistem,
            'kode_grade_final' => $gradeSesudah,
            'kuadran_id' => $kuadranSesudah,
            'kode_kuadran' => $kodeKuadran,
        ];
    }

    /**
     * Grade harus ada di pita SALINAN SIKLUS, bukan di master hidup.
     *
     * Master boleh berubah setelah siklus berjalan; yang mengikat hasil adalah salinan
     * yang dibekukan saat siklus disiapkan.
     */
    private function pastikanGradeSah(int $cycleId, string $kode): void
    {
        $ada = DB::table('N_PA_Cycle_Score_Range_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Score_Type', 'FINAL')
            ->where('Score_Usage', 'GRADE')
            ->where('Kode_Range', $kode)
            ->where('Flag_Aktif', 'Y')
            ->exists();

        if (! $ada) {
            throw new RuntimeException(
                "Grade {$kode} tidak ada di pita salinan siklus ini. Kalibrasi hanya boleh "
                .'memindahkan ke grade yang memang berlaku pada siklus tersebut.'
            );
        }
    }

    private function pastikanKuadranSah(?int $kuadranId): void
    {
        if ($kuadranId === null) {
            return;
        }

        $ada = DB::table('N_PA_Hav_Quadrant')
            ->where('Id_PA_Hav_Quadrant', $kuadranId)
            ->where('Flag_Aktif', 'Y')
            ->exists();

        if (! $ada) {
            throw new RuntimeException("Kuadran {$kuadranId} tidak ada atau tidak aktif.");
        }
    }
}
