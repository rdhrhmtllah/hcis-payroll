<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

use Illuminate\Support\Facades\DB;

/**
 * SATU PINTU nilai After yang berlaku (spec §4–§5).
 *
 * Nilai berlaku satu peserta = isian sesi FINAL TERBARU yang memuatnya. Sesi DRAFT atau
 * DIBATALKAN tidak pernah berlaku. Hanya kelas ini yang boleh membaca `Nilai_Sesudah` dan
 * `Isi_Sesudah` untuk keperluan nilai; `BalancingSatuPintuTest` menjaganya.
 */
class PaBalancingNilaiBerlaku
{
    private ?int $siklusTermuat = null;

    /** @var array<int, array<int, float>> */
    private array $petaSiklus = [];

    /**
     * Memuat nilai berlaku seluruh peserta satu siklus dalam satu kueri. Dipanggil
     * `hitungSiklus`; tanpa ini tiap peserta menambah satu perjalanan ke basis data.
     */
    public function muatSiklus(int $cycleId): void
    {
        $terbaru = DB::table('N_PA_Balancing_Peserta as bp')
            ->join('N_PA_Balancing_Sesi as s', 's.Id_PA_Balancing_Sesi', '=', 'bp.PA_Balancing_Sesi_Id')
            ->where('s.PA_Cycle_Id', $cycleId)
            ->where('s.Status_Sesi', PaBalancingStatus::FINAL)
            ->orderBy('s.Final_At')->orderBy('s.Id_PA_Balancing_Sesi')
            ->get(['bp.PA_Participant_Id', 'bp.Id_PA_Balancing_Peserta'])
            // Urut naik lalu ditimpa: yang tersisa per peserta adalah sesi FINAL terbaru.
            ->mapWithKeys(fn ($r) => [(int) $r->PA_Participant_Id => (int) $r->Id_PA_Balancing_Peserta]);

        $this->petaSiklus = [];

        if ($terbaru->isNotEmpty()) {
            $peserta = $terbaru->flip();

            foreach ($terbaru->values()->chunk(1000) as $potongan) {
                DB::table('N_PA_Balancing_Kompetensi')
                    ->whereIn('PA_Balancing_Peserta_Id', $potongan->all())
                    ->get(['PA_Balancing_Peserta_Id', 'PA_Competency_Id', 'Nilai_Sesudah'])
                    ->each(function ($r) use ($peserta) {
                        $this->petaSiklus[(int) $peserta[(int) $r->PA_Balancing_Peserta_Id]][(int) $r->PA_Competency_Id] = (float) $r->Nilai_Sesudah;
                    });
            }
        }

        $this->siklusTermuat = $cycleId;
    }

    /** @return array<int, float> competency_id => After */
    public function kompetensiBerlaku(int $participantId, ?int $cycleId = null): array
    {
        if ($cycleId !== null && $cycleId === $this->siklusTermuat) {
            return $this->petaSiklus[$participantId] ?? [];
        }

        $bp = $this->pesertaSesiFinalTerbaru($participantId);

        if ($bp === null) {
            return [];
        }

        return DB::table('N_PA_Balancing_Kompetensi')->where('PA_Balancing_Peserta_Id', $bp)
            ->get(['PA_Competency_Id', 'Nilai_Sesudah'])
            ->mapWithKeys(fn ($r) => [(int) $r->PA_Competency_Id => (float) $r->Nilai_Sesudah])
            ->all();
    }

    /** @return array<int, array{sebelum:?string, sesudah:?string}> competency_id => isi */
    public function kualitatifBerlaku(int $participantId): array
    {
        $bp = $this->pesertaSesiFinalTerbaru($participantId);

        if ($bp === null) {
            return [];
        }

        return DB::table('N_PA_Balancing_Kualitatif')->where('PA_Balancing_Peserta_Id', $bp)
            ->get(['PA_Competency_Id', 'Isi_Sebelum', 'Isi_Sesudah'])
            ->mapWithKeys(fn ($r) => [(int) $r->PA_Competency_Id => ['sebelum' => $r->Isi_Sebelum, 'sesudah' => $r->Isi_Sesudah]])
            ->all();
    }

    public function lupakan(): void
    {
        $this->siklusTermuat = null;
        $this->petaSiklus = [];
    }

    private function pesertaSesiFinalTerbaru(int $participantId): ?int
    {
        $id = DB::table('N_PA_Balancing_Peserta as bp')
            ->join('N_PA_Balancing_Sesi as s', 's.Id_PA_Balancing_Sesi', '=', 'bp.PA_Balancing_Sesi_Id')
            ->where('bp.PA_Participant_Id', $participantId)
            ->where('s.Status_Sesi', PaBalancingStatus::FINAL)
            ->orderByDesc('s.Final_At')->orderByDesc('s.Id_PA_Balancing_Sesi')
            ->value('bp.Id_PA_Balancing_Peserta');

        return $id === null ? null : (int) $id;
    }
}
