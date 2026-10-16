<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaStatistik;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use Illuminate\Support\Facades\DB;

/**
 * Simulasi hitung ulang selama sesi berlangsung (spec §6.4, B11). TIDAK menulis apa pun.
 *
 * Titik awal = After yang tersimpan di sesi ini, ditimpa isian layar yang belum disimpan.
 * Peserta sesi lain yang sudah FINAL tetapi belum dihitung ulang ikut dengan nilai berlakunya,
 * supaya gambaran kurva lengkap.
 */
class PaBalancingSimulasiService
{
    public function __construct(
        private readonly PaResultCalculationService $perhitungan,
        private readonly PaKurvaService $kurva,
        private readonly PaBalancingPenjaga $penjaga,
        private readonly PaBalancingPotretHasil $potret,
    ) {}

    public function simulasikan(int $sesiId, array $draft): array
    {
        $sesi = DB::table('N_PA_Balancing_Sesi')->where('Id_PA_Balancing_Sesi', $sesiId)->first();

        if ($sesi === null || $sesi->Status_Sesi === PaBalancingStatus::DIBATALKAN) {
            throw new PaBalancingException("Sesi Balancing {$sesiId} tidak ditemukan atau sudah dibatalkan.", PaBalancingException::SESI_TIDAK_DITEMUKAN, 404);
        }

        $cycleId = (int) $sesi->PA_Cycle_Id;
        $isian = $this->isianAwal($sesiId);

        foreach ($draft as $d) {
            $pid = (int) ($d['participant_id'] ?? 0);

            if (! isset($isian[$pid])) {
                throw new PaBalancingException("Peserta #{$pid} bukan anggota sesi ini.", PaBalancingException::DATA_TIDAK_SAH);
            }

            foreach ((array) ($d['kompetensi'] ?? []) as $k) {
                $nilai = (float) ($k['nilai_sesudah'] ?? -1);

                if ($nilai < 0 || $nilai > 100) {
                    throw new PaBalancingException('Nilai After harus 0 sampai 100.', PaBalancingException::NILAI_DI_LUAR_RENTANG);
                }

                $isian[$pid][(int) $k['competency_id']] = $nilai;
            }
        }

        $lain = DB::table('N_PA_Balancing_Peserta')
            ->whereIn('PA_Balancing_Sesi_Id', $this->penjaga->sesiFinalBelumDihitung($cycleId)->pluck('Id_PA_Balancing_Sesi'))
            ->whereNotIn('PA_Participant_Id', array_keys($isian))
            ->distinct()->pluck('PA_Participant_Id')->map(fn ($x) => (int) $x)->all();

        /* Yang Kalibrasi Forum-nya akan dilepas hitung ulang ikut disimulasikan dari angkanya
           sendiri — persis seperti hitung ulang menghitung mereka. Tanpa ini, pada siklus PITA
           grade forum mereka tampak bertahan di simulasi lalu hilang sesudah hitung ulang.
           Hasil yang sudah dibaca tidak termasuk (lihat PaBalancingPenjaga). */
        $lain = array_values(array_unique([...$lain, ...array_diff(
            $this->penjaga->kalibrasiForumBelumDibaca($cycleId)->pluck('PA_Participant_Id')->map(fn ($x) => (int) $x)->all(),
            array_keys($isian),
        )]));

        $baru = [];

        foreach ($isian as $pid => $after) {
            $baru[$pid] = $this->perhitungan->simulasiHasil($pid, $after);
        }

        foreach ($lain as $pid) {
            $baru[$pid] = $this->perhitungan->simulasiHasil($pid);
        }

        $sebelum = $this->potret->siklus($cycleId);
        $metode = $this->kurva->metode($cycleId);
        $finalBaru = array_filter(array_map(fn ($b) => $b['final'], $baru), fn ($v) => $v !== null);
        $sim = $metode === 'KURVA' ? $this->kurva->simulasikanGrade($cycleId, $finalBaru) : null;

        $peserta = [];
        $terdampak = [];
        $semua = [];

        foreach ($sebelum as $pid => $s) {
            $b = $baru[$pid] ?? null;
            $finalSesudah = $b['final'] ?? $s->Final_Score;
            $gradeSesudah = $sim !== null ? ($sim['grade'][$pid] ?? $s->Kode_Grade_Final) : ($b['grade_pita'] ?? $s->Kode_Grade_Final);
            $gradeTengah = ($sim !== null && isset($sim['grup'][$pid]) && $s->Final_Score !== null)
                ? PaKurvaStatistik::gradeUntuk($sim['pita'][$sim['grup'][$pid]], $s->Final_Score)
                : $s->Kode_Grade_Final;

            $baris = [
                'participant_id' => $pid,
                'nama' => $s->Nama,
                'sebelum' => [
                    'nilai_kompetensi' => $s->Nilai_Kompetensi, 'final' => $s->Final_Score, 'grade' => $s->Kode_Grade_Final,
                    'kuadran_id' => $s->PA_Hav_Quadrant_Id, 'kode_kuadran' => $s->Kode_Kuadran,
                ],
                'sesudah' => [
                    'nilai_kompetensi' => $b['nilai_kompetensi'] ?? $s->Nilai_Kompetensi, 'final' => $finalSesudah, 'grade' => $gradeSesudah,
                    'kuadran_id' => $b === null ? $s->PA_Hav_Quadrant_Id : $b['kuadran_id'],
                    'kode_kuadran' => $b === null ? $s->Kode_Kuadran : $b['kode_kuadran'],
                ],
                'sebab' => PaBalancingKalkulator::sebab($s->Final_Score, $finalSesudah, $s->Kode_Grade_Final, $gradeTengah, $gradeSesudah),
            ];

            $semua[$pid] = $baris + ['sesi' => isset($isian[$pid])];

            if (isset($isian[$pid])) {
                $peserta[] = $baris;
            } elseif ($baris['sebab'] !== null) {
                $terdampak[] = $baris;
            }
        }

        return [
            'metode' => $metode, 'peserta' => $peserta, 'terdampak' => $terdampak,
            'grade' => $this->urutanGrade($cycleId),
            // Seluruh peserta siklus: HAV map dan sebaran grade sebelum/sesudah di modal simulasi.
            'semua' => array_values(array_map(fn ($b) => [
                'participant_id' => $b['participant_id'], 'nama' => $b['nama'], 'sesi' => $b['sesi'],
                'final_sebelum' => $b['sebelum']['final'], 'final_sesudah' => $b['sesudah']['final'],
                'grade_sebelum' => $b['sebelum']['grade'], 'grade_sesudah' => $b['sesudah']['grade'],
                'kuadran_sebelum' => $b['sebelum']['kode_kuadran'], 'kuadran_sesudah' => $b['sesudah']['kode_kuadran'],
            ], $semua)),
            'kurva' => $sim === null ? [] : $this->kurvaGrup($cycleId, $sim, $semua),
        ];
    }

    /** @return list<array{kode:string, nama:string}> urutan grade siklus, tertinggi lebih dulu */
    private function urutanGrade(int $cycleId): array
    {
        return DB::table('N_PA_Cycle_Score_Range_Snapshot')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')
            ->where('Score_Type', 'FINAL')->where('Score_Usage', 'GRADE')->orderBy('Urutan_Tampil')
            ->get(['Kode_Range', 'Nama_Range'])->map(fn ($g) => ['kode' => (string) $g->Kode_Range, 'nama' => (string) $g->Nama_Range])->all();
    }

    /**
     * Kurva per grup sebelum (kurva terkunci) dan sesudah (μ/σ baru di memori), plus nilai
     * Final anggota untuk titik di grafik. Proporsi teoretis = pembanding sebaran grade.
     *
     * @param  array<int, array<string, mixed>>  $semua
     * @return list<array<string, mixed>>
     */
    private function kurvaGrup(int $cycleId, array $sim, array $semua): array
    {
        $teoretis = $this->kurva->proporsiTeoretis($cycleId);
        $hasil = [];

        foreach ($this->kurva->pratinjau($cycleId)['grup'] as $g) {
            $gid = (int) $g['id'];
            $anggota = array_filter($semua, fn ($b) => ($sim['grup'][$b['participant_id']] ?? null) === $gid);
            $sebaranSesudah = array_fill_keys(array_column($sim['pita'][$gid] ?? [], 'kode'), 0);

            foreach ($anggota as $b) {
                if ($b['sesudah']['grade'] !== null && isset($sebaranSesudah[$b['sesudah']['grade']])) {
                    $sebaranSesudah[$b['sesudah']['grade']]++;
                }
            }

            $stat = $sim['stat'][$gid] ?? ['n' => 0, 'rata' => null, 'sd' => null];
            $hasil[] = [
                'id' => $gid, 'kode' => $g['kode'], 'nama' => $g['nama'], 'level' => array_column($g['level'], 'nama'),
                'proporsi' => $teoretis,
                'sebelum' => ['n' => $g['n'], 'rata' => $g['rata'], 'sd' => $g['sd'], 'pita' => $g['pita'], 'sebaran' => $g['sebaran']],
                'sesudah' => ['n' => $stat['n'], 'rata' => $stat['rata'], 'sd' => $stat['sd'], 'pita' => $sim['pita'][$gid] ?? [], 'sebaran' => $sebaranSesudah],
                'anggota' => array_values(array_map(fn ($b) => [
                    'participant_id' => $b['participant_id'], 'nama' => $b['nama'], 'sesi' => $b['sesi'],
                    'sebelum' => $b['sebelum']['final'], 'sesudah' => $b['sesudah']['final'],
                    'grade_sebelum' => $b['sebelum']['grade'], 'grade_sesudah' => $b['sesudah']['grade'],
                ], $anggota)),
            ];
        }

        return $hasil;
    }

    /** @return array<int, array<int, float>> participant_id => [competency_id => After tersimpan] */
    private function isianAwal(int $sesiId): array
    {
        $isian = [];

        DB::table('N_PA_Balancing_Peserta as bp')
            ->leftJoin('N_PA_Balancing_Kompetensi as k', 'k.PA_Balancing_Peserta_Id', '=', 'bp.Id_PA_Balancing_Peserta')
            ->where('bp.PA_Balancing_Sesi_Id', $sesiId)
            ->get(['bp.PA_Participant_Id', 'k.PA_Competency_Id', 'k.Nilai_Sesudah'])
            ->each(function ($r) use (&$isian) {
                $isian[(int) $r->PA_Participant_Id] ??= [];

                if ($r->PA_Competency_Id !== null) {
                    $isian[(int) $r->PA_Participant_Id][(int) $r->PA_Competency_Id] = (float) $r->Nilai_Sesudah;
                }
            });

        return $isian;
    }
}
