<?php

namespace App\Services\KPI;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use App\Services\SYSTEM\Application\Exports\AbstractExportService as Tugas;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Backfill cap KPI ke metode PER PARAMETER untuk seluruh header lama
 * (keputusan user 12 Okt 2026, membalik keputusan 28 Agt "data lama tidak
 * dihitung ulang").
 *
 * Dijalankan dari Postman (KpiBackfillCapController), diproses di background per
 * potongan 300 header. Rumus milik KpiCapParameterKalkulator → KpiScoringService.
 *
 * Penanda potongan (Jenis='POTONG', Id_Update_Performance = indeks potongan,
 * unik per batch) ditulis DI DALAM transaksi yang sama dengan perubahannya:
 * Cloud Tasks yang mengirim ulang potongan yang sudah selesai gagal menyisipkan
 * penanda dan keluar tanpa mengubah atau menghitung apa pun.
 *
 * Penulisan set-based lewat tabel sementara: Web_HRIS berjalan tanpa RCSI,
 * transaksi panjang memblokir pembaca.
 */
class KpiCapParameterBackfillService
{
    public const TASK_TYPE = 'kpi-backfill-cap';

    public const MODULE = 'kpi-backfill';

    public const TABEL_CADANGAN = 'N_KPI_Backfill_Cap_Cadangan';

    public const POTONGAN = 300;

    /** whereIn dipecah agar tak menabrak batas 2.100 parameter SQL Server. */
    private const ID_PER_KUERI = 500;

    private const KOLOM_HEADER = ['Total_Score', 'Total_Score_Achievement', 'Total_Score_Raw', 'Total_Score_Achievement_Raw',
        'Flag_Capped', 'Metode_Cap', 'Cap_Min_Applied', 'Cap_Max_Applied', 'Id_Performance'];

    private const KOLOM_DETAIL = ['Score', 'Achievement', 'Score_Raw', 'Achievement_Raw', 'Flag_Capped'];

    public function __construct(private KpiCapParameterKalkulator $kalkulator) {}

    public function tabelCadanganAda(): bool
    {
        return Schema::hasTable(self::TABEL_CADANGAN);
    }

    /** @return list<int> header aktif yang belum ber-Metode_Cap PARAMETER, urut naik */
    public function idSasaran(): array
    {
        return DB::table('KPI_Update_Performance')
            ->where('Flag_Aktif', 'Y')
            ->whereNull('Status')
            ->where(fn ($q) => $q->whereNull('Metode_Cap')->orWhere('Metode_Cap', '<>', 'PARAMETER'))
            ->orderBy('Id_Update_Performance')
            ->pluck('Id_Update_Performance')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    // ── baca ────────────────────────────────────────────────────────────

    /** @return array{0: Collection, 1: Collection} header keyed id; detail dikelompokkan per header */
    private function muat(array $ids): array
    {
        $header = collect();
        $detail = collect();
        foreach (array_chunk($ids, self::ID_PER_KUERI) as $bagian) {
            $header = $header->merge(DB::table('KPI_Update_Performance')->whereIn('Id_Update_Performance', $bagian)
                ->get(array_merge(['Id_Update_Performance', 'No_Transaksi', 'Periode', 'Flag_Approval', 'Flag_Aktif', 'Status'], self::KOLOM_HEADER)));
            $detail = $detail->merge(DB::table('KPI_Update_Performance_Detail as d')
                ->join('KPI_Pengajuan_Det as det', 'det.Id_Pengajuan_Det', '=', 'd.Id_Pengajuan_Det')
                ->join('KPI_Pengajuan_Detail as pd', 'pd.Id_Pengajuan_Detail', '=', 'det.Id_Pengajuan_Detail')
                ->whereIn('d.Id_Update_Performance', $bagian)
                ->orderBy('d.Id_Update_Performance_Detail')
                ->get(['d.Id_Update_Performance', 'd.Id_Update_Performance_Detail', 'd.Score', 'd.Achievement',
                    'd.Score_Raw', 'd.Achievement_Raw', 'd.Flag_Capped', 'pd.Bobot',
                    'd.Nilai_Actual', 'det.Nilai_Target', 'pd.Id_Master_Formula']));
        }

        return [
            $header->keyBy(fn ($h) => (int) $h->Id_Update_Performance),
            $detail->groupBy(fn ($d) => (int) $d->Id_Update_Performance),
        ];
    }

    /**
     * Apakah cap per parameter benar-benar mengubah header ini?
     *
     * Ya bila ada parameter yang SEKARANG dijepit tetapi belum pernah dijepit
     * (Score_Raw masih kosong). Selain itu hasilnya hanya label: keputusan user
     * 12 Okt 2026 — data lama yang menyimpan Achievement dengan rumus berbeda tak
     * boleh ikut berubah totalnya (pratinjau dev: 1.117 header akan berubah skor
     * padahal tak satu pun parameternya dijepit).
     */
    private function terdampak(Collection $baris, array $hasil): bool
    {
        foreach ($baris as $i => $d) {
            if ($d->Score_Raw === null && $hasil['detail'][$i]['Flag_Capped'] === 'Y') {
                return true;
            }
        }

        return false;
    }

    /**
     * Persen pencapaian sebenarnya, dihitung ulang dari aktual/target dengan rumus
     * parameternya (sama seperti finalisasi). null bila target 0 atau rumus gagal.
     */
    private function persenDariRumus(object $d, array $rumus): ?float
    {
        if ((float) $d->Nilai_Target == 0.0) {
            return null;
        }
        try {
            $c = app(KpiScoringService::class)->scoreParameterById(
                $d->Nilai_Actual, $d->Nilai_Target, $d->Bobot, $d->Id_Master_Formula, $rumus);
        } catch (\Throwable) {
            return null;
        }

        return (float) ($c['achievement_raw'] ?? $c['achievement']);
    }

    /**
     * Di kolom mana baris detail ini menyimpan persen pencapaian?
     *
     * Pratinjau dev 12 Okt 2026: sebagian baris menyimpannya di Score (aturan
     * sekarang), sebagian di Achievement (tertukar), sebagian tak cocok dengan
     * rumus sama sekali (target dikoreksi sesudah dinilai?). Aturan final
     * backfill ditetapkan dari golongan ini di produksi.
     *
     * @return string persen_di_score | persen_di_achievement | tak_cocok | target_nol | rumus_galat
     */
    public function golonganKolom(object $d, ?float $persen): string
    {
        if ((float) $d->Nilai_Target == 0.0) {
            return 'target_nol';
        }
        if ($persen === null) {
            return 'rumus_galat';
        }
        $score = (float) ($d->Score_Raw ?? $d->Score);
        $ach = (float) ($d->Achievement_Raw ?? $d->Achievement);

        return match (true) {
            abs($score - $persen) < 0.02 => 'persen_di_score',
            abs($ach - $persen) < 0.02 => 'persen_di_achievement',
            default => 'tak_cocok',
        };
    }

    /** Diperiksa ulang di dalam potongan: header bisa berubah sesudah pencacahan. */
    private function masihSasaran(object $h): bool
    {
        return $h->Flag_Aktif === 'Y' && $h->Status === null && $h->Metode_Cap !== 'PARAMETER';
    }

    public function pratinjau(): array
    {
        $ids = $this->idSasaran();
        $pita = $this->kalkulator->muatPita();
        $kode = DB::table('KPI_Master_Performance')->pluck('Kode', 'Id_Master_Performance');

        $perStatus = [];
        $detailSasaran = $detailBerubah = $skorBerubah = $dihitungUlang = $hanyaLabel = 0;
        $rumus = DB::table('KPI_Master_Formula')->pluck('Rumus', 'Id_Master_Formula')->all();
        $golDetail = array_fill_keys(['persen_di_score', 'persen_di_achievement', 'tak_cocok', 'target_nol', 'rumus_galat'], 0);
        $golHeader = ['semua_persen_di_score' => 0, 'ada_tertukar' => 0, 'ada_tak_cocok_atau_target_nol' => 0];
        $contohGol = [];
        $pindah = [];
        $contoh = [];

        foreach (array_chunk($ids, self::ID_PER_KUERI) as $bagian) {
            [$header, $detail] = $this->muat($bagian);
            foreach ($header as $id => $h) {
                $baris = $detail->get($id, collect())->values();
                $hasil = $this->kalkulator->hitung($baris, $pita);
                $status = match ($h->Flag_Approval) {
                    'Y' => 'disetujui',
                    'T' => 'ditolak',
                    default => 'menunggu',
                };
                $perStatus[$status] = ($perStatus[$status] ?? 0) + 1;
                $detailSasaran += $baris->count();
                $golDiHeader = [];
                foreach ($baris as $d) {
                    $persen = $this->persenDariRumus($d, $rumus);
                    $g = $this->golonganKolom($d, $persen);
                    $golDetail[$g]++;
                    $golDiHeader[$g] = true;
                    if ($g !== 'persen_di_score' && count($contohGol[$g] ?? []) < 5) {
                        $contohGol[$g][] = [
                            'no_transaksi' => $h->No_Transaksi, 'periode' => $h->Periode,
                            'aktual' => (float) $d->Nilai_Actual, 'target' => (float) $d->Nilai_Target, 'bobot' => (float) $d->Bobot,
                            'score' => (float) $d->Score, 'achievement' => (float) $d->Achievement, 'persen_dari_rumus' => $persen,
                        ];
                    }
                }
                if (isset($golDiHeader['tak_cocok']) || isset($golDiHeader['target_nol']) || isset($golDiHeader['rumus_galat'])) {
                    $golHeader['ada_tak_cocok_atau_target_nol']++;
                } elseif (isset($golDiHeader['persen_di_achievement'])) {
                    $golHeader['ada_tertukar']++;
                } else {
                    $golHeader['semua_persen_di_score']++;
                }
                if (! $this->terdampak($baris, $hasil)) {
                    $hanyaLabel++;

                    continue;
                }
                $dihitungUlang++;
                foreach ($baris as $i => $d) {
                    $b = $hasil['detail'][$i];
                    if (abs((float) $d->Score - $b['Score']) > 0.005 || abs((float) $d->Achievement - $b['Achievement']) > 0.005) {
                        $detailBerubah++;
                    }
                }
                if (abs((float) $h->Total_Score - $hasil['header']['Total_Score']) > 0.005) {
                    $skorBerubah++;
                }

                $dari = (int) $h->Id_Performance;
                $ke = $hasil['header']['Id_Performance'];
                if ($dari !== $ke) {
                    $kunci = ($kode[$dari] ?? (string) $dari).'→'.($kode[$ke] ?? (string) $ke);
                    $pindah[$kunci] = ($pindah[$kunci] ?? 0) + 1;
                    if (count($contoh) < 20) {
                        $contoh[] = [
                            'no_transaksi' => $h->No_Transaksi,
                            'periode' => $h->Periode,
                            'skor_lama' => round((float) $h->Total_Score, 2),
                            'skor_baru' => $hasil['header']['Total_Score'],
                            'level_lama' => $kode[$dari] ?? $dari,
                            'level_baru' => $kode[$ke] ?? $ke,
                        ];
                    }
                }
            }
        }
        arsort($pindah);
        [$max, $min] = app(KpiScoringService::class)->paramCapBounds();

        return [
            'batas' => ['min' => $min, 'max' => $max],
            'header_sasaran' => count($ids),
            // Letak persen pencapaian per baris — dasar aturan final backfill.
            // Eksekusi dikunci sampai aturan itu ditetapkan dari angka produksi.
            'diagnosis_kolom' => ['detail' => $golDetail, 'header' => $golHeader, 'contoh' => $contohGol],
            // Skor & level dihitung ulang hanya untuk header terdampak cap; sisanya
            // hanya diberi label Metode_Cap='PARAMETER'.
            'header_dihitung_ulang' => $dihitungUlang,
            'header_hanya_label' => $hanyaLabel,
            'per_status' => $perStatus,
            'detail_sasaran' => $detailSasaran,
            'detail_berubah' => $detailBerubah,
            'header_skor_berubah' => $skorBerubah,
            'perpindahan_level' => collect($pindah)->map(function ($n, $k) {
                [$dari, $ke] = explode('→', $k);

                return ['dari' => $dari, 'ke' => $ke, 'jumlah' => $n];
            })->values()->all(),
            'contoh' => $contoh,
        ];
    }

    // ── batch ───────────────────────────────────────────────────────────

    public function batchAktif(): ?object
    {
        return DB::table(Tugas::TABLE)->where('Task_Type', self::TASK_TYPE)->whereNull('Deleted_At')
            ->whereIn('Status', [Tugas::STATUS_QUEUED, Tugas::STATUS_PROCESSING])->first();
    }

    public function mulai(string $mode, ?string $requestedBy, ?string $sumberBatch = null): string
    {
        $id = ($mode === 'rollback' ? 'kpi-bfr-' : 'kpi-bf-').now()->format('Ymd-His').'-'.strtoupper(Str::random(6));
        $now = now();
        DB::table(Tugas::TABLE)->insert([
            'Export_Id' => $id,
            'Task_Type' => self::TASK_TYPE,
            'Module' => self::MODULE,
            'Format' => $mode,
            'Payload_Json' => json_encode(['mode' => $mode, 'sumber' => $sumberBatch]),
            'Status' => Tugas::STATUS_QUEUED,
            'Total_Records' => 0,
            'Processed_Records' => 0,
            'Percent' => 0,
            'Requested_By' => $requestedBy,
            'Created_At' => $now,
            'Updated_At' => $now,
        ]);

        return $id;
    }

    public function payload(string $batchId): array
    {
        return json_decode((string) DB::table(Tugas::TABLE)->where('Export_Id', $batchId)->value('Payload_Json'), true) ?: [];
    }

    /** @return list<int> sasaran (jalankan) atau header yang belum dipulihkan (rollback) */
    public function idUntukBatch(string $batchId): array
    {
        $p = $this->payload($batchId);
        if (($p['mode'] ?? null) !== 'rollback') {
            return $this->idSasaran();
        }

        return DB::table(self::TABEL_CADANGAN)->where('Batch_Id', (string) $p['sumber'])->where('Jenis', 'HEADER')
            ->whereNull('Dipulihkan_At')->orderBy('Id_Update_Performance')
            ->pluck('Id_Update_Performance')->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    public function setelTotal(string $batchId, int $total): void
    {
        DB::table(Tugas::TABLE)->where('Export_Id', $batchId)->update([
            'Total_Records' => $total,
            'Status' => $total === 0 ? Tugas::STATUS_READY : Tugas::STATUS_PROCESSING,
            'Percent' => $total === 0 ? 100 : 0,
            'Started_At' => now(),
            'Completed_At' => $total === 0 ? now() : null,
            'Updated_At' => now(),
        ]);
    }

    /** Penanda potongan; false = potongan ini sudah pernah diproses. */
    private function klaimPotongan(string $batchId, int $indeks): bool
    {
        try {
            DB::table(self::TABEL_CADANGAN)->insert(['Batch_Id' => $batchId, 'Jenis' => 'POTONG', 'Id_Update_Performance' => $indeks]);

            return true;
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'UX_KPI_Backfill_Cap_Potong')) {
                return false;
            }
            throw $e;
        }
    }

    private function majukan(string $batchId, int $n): void
    {
        DB::table(Tugas::TABLE)->where('Export_Id', $batchId)->update([
            'Processed_Records' => DB::raw('Processed_Records + '.(int) $n),
            'Updated_At' => now(),
        ]);
        $t = DB::table(Tugas::TABLE)->where('Export_Id', $batchId)->first(['Total_Records', 'Processed_Records']);
        $total = (int) $t->Total_Records;
        $selesai = (int) $t->Processed_Records >= $total;
        DB::table(Tugas::TABLE)->where('Export_Id', $batchId)->update([
            'Percent' => $total > 0 ? min(100, (int) round($t->Processed_Records / $total * 100)) : 100,
            'Status' => $selesai ? Tugas::STATUS_READY : Tugas::STATUS_PROCESSING,
            'Completed_At' => $selesai ? now() : null,
        ]);
    }

    public function tandaiGagal(string $batchId, string $pesan): void
    {
        DB::table(Tugas::TABLE)->where('Export_Id', $batchId)->update([
            'Status' => Tugas::STATUS_FAILED,
            'Error_Message' => mb_substr($pesan, 0, 1000),
            'Completed_At' => now(),
            'Updated_At' => now(),
        ]);
    }

    // ── tulis ───────────────────────────────────────────────────────────

    /** @return int jumlah header yang benar-benar dihitung ulang */
    public function prosesPotongan(string $batchId, int $indeks, array $ids): int
    {
        return DB::transaction(function () use ($batchId, $indeks, $ids) {
            if (! $this->klaimPotongan($batchId, $indeks)) {
                return 0;
            }
            [$header, $detail] = $this->muat($ids);
            $pita = $this->kalkulator->muatPita();
            $cadangan = $hasilHeader = $hasilDetail = $hanyaLabel = [];

            foreach ($header as $id => $h) {
                if (! $this->masihSasaran($h)) {
                    continue;
                }
                $baris = $detail->get($id, collect())->values();
                $hasil = $this->kalkulator->hitung($baris, $pita);

                $cadangan[] = ['Batch_Id' => $batchId, 'Jenis' => 'HEADER', 'Id_Update_Performance' => $id,
                    'Id_Update_Performance_Detail' => null,
                    'Nilai_Lama_Json' => json_encode(collect((array) $h)->only(self::KOLOM_HEADER)->all())];
                if (! $this->terdampak($baris, $hasil)) {
                    $hanyaLabel[] = $id;

                    continue;
                }
                foreach ($baris as $d) {
                    $cadangan[] = ['Batch_Id' => $batchId, 'Jenis' => 'DETAIL', 'Id_Update_Performance' => $id,
                        'Id_Update_Performance_Detail' => (int) $d->Id_Update_Performance_Detail,
                        'Nilai_Lama_Json' => json_encode(collect((array) $d)->only(self::KOLOM_DETAIL)->all())];
                }
                $hasilHeader[] = ['Id' => $id] + $hasil['header'];
                array_push($hasilDetail, ...$hasil['detail']);
            }

            foreach (array_chunk($cadangan, 300) as $c) {
                DB::table(self::TABEL_CADANGAN)->insert($c);
            }
            $this->tulisDetail($hasilDetail);
            $this->tulisHeader($hasilHeader);
            foreach (array_chunk($hanyaLabel, self::ID_PER_KUERI) as $c) {
                DB::table('KPI_Update_Performance')->whereIn('Id_Update_Performance', $c)
                    ->update(['Metode_Cap' => UpdatePerformanceController::METODE_CAP_PARAMETER]);
            }
            $this->majukan($batchId, count($ids));

            return count($hasilHeader) + count($hanyaLabel);
        });
    }

    /** @return int jumlah header yang dipulihkan */
    public function pulihkanPotongan(string $batchId, string $sumberBatch, int $indeks, array $ids): int
    {
        return DB::transaction(function () use ($batchId, $sumberBatch, $indeks, $ids) {
            if (! $this->klaimPotongan($batchId, $indeks)) {
                return 0;
            }
            $baris = collect();
            foreach (array_chunk($ids, self::ID_PER_KUERI) as $bagian) {
                $baris = $baris->merge(DB::table(self::TABEL_CADANGAN)->where('Batch_Id', $sumberBatch)
                    ->whereIn('Jenis', ['HEADER', 'DETAIL'])->whereNull('Dipulihkan_At')
                    ->whereIn('Id_Update_Performance', $bagian)->get());
            }
            $hdr = $baris->where('Jenis', 'HEADER')
                ->map(fn ($r) => ['Id' => (int) $r->Id_Update_Performance] + json_decode($r->Nilai_Lama_Json, true))->values()->all();
            $det = $baris->where('Jenis', 'DETAIL')
                ->map(fn ($r) => ['Id_Update_Performance_Detail' => (int) $r->Id_Update_Performance_Detail] + json_decode($r->Nilai_Lama_Json, true))->values()->all();

            $this->tulisDetail($det);
            $this->tulisHeader($hdr);
            foreach ($baris->pluck('Id_Cadangan')->chunk(self::ID_PER_KUERI) as $c) {
                DB::table(self::TABEL_CADANGAN)->whereIn('Id_Cadangan', $c->all())->update(['Dipulihkan_At' => now()]);
            }
            $this->majukan($batchId, count($ids));

            return count($hdr);
        });
    }

    /** Tulis nilai lewat tabel sementara + satu UPDATE…FROM, bukan per baris. */
    private function tulisDetail(array $rows): void
    {
        if ($rows === []) {
            return;
        }
        DB::statement('CREATE TABLE #bf_detail (Id int PRIMARY KEY, Score float NULL, Achievement float NULL,
            Score_Raw float NULL, Achievement_Raw float NULL, Flag_Capped char(1) NULL)');
        try {
            foreach (array_chunk($rows, 300) as $c) {
                DB::table('#bf_detail')->insert(array_map(fn ($r) => [
                    'Id' => $r['Id_Update_Performance_Detail'],
                    'Score' => $r['Score'],
                    'Achievement' => $r['Achievement'],
                    'Score_Raw' => $r['Score_Raw'],
                    'Achievement_Raw' => $r['Achievement_Raw'],
                    'Flag_Capped' => $r['Flag_Capped'],
                ], $c));
            }
            DB::statement('UPDATE d SET d.Score = t.Score, d.Achievement = t.Achievement, d.Score_Raw = t.Score_Raw,
                d.Achievement_Raw = t.Achievement_Raw, d.Flag_Capped = t.Flag_Capped
                FROM KPI_Update_Performance_Detail d JOIN #bf_detail t ON t.Id = d.Id_Update_Performance_Detail');
        } finally {
            DB::statement('DROP TABLE #bf_detail');
        }
    }

    private function tulisHeader(array $rows): void
    {
        if ($rows === []) {
            return;
        }
        DB::statement('CREATE TABLE #bf_header (Id int PRIMARY KEY, Total_Score float NULL, Total_Score_Achievement float NULL,
            Total_Score_Raw float NULL, Total_Score_Achievement_Raw float NULL, Flag_Capped char(1) NULL, Metode_Cap varchar(20) NULL,
            Cap_Min_Applied decimal(9,2) NULL, Cap_Max_Applied decimal(9,2) NULL, Id_Performance int NULL)');
        try {
            foreach (array_chunk($rows, 150) as $c) {
                DB::table('#bf_header')->insert(array_map(fn ($r) => collect($r)->only(array_merge(['Id'], self::KOLOM_HEADER))->all(), $c));
            }
            DB::statement('UPDATE h SET h.Total_Score = t.Total_Score, h.Total_Score_Achievement = t.Total_Score_Achievement,
                h.Total_Score_Raw = t.Total_Score_Raw, h.Total_Score_Achievement_Raw = t.Total_Score_Achievement_Raw,
                h.Flag_Capped = t.Flag_Capped, h.Metode_Cap = t.Metode_Cap, h.Cap_Min_Applied = t.Cap_Min_Applied,
                h.Cap_Max_Applied = t.Cap_Max_Applied, h.Id_Performance = t.Id_Performance
                FROM KPI_Update_Performance h JOIN #bf_header t ON t.Id = h.Id_Update_Performance');
        } finally {
            DB::statement('DROP TABLE #bf_header');
        }
    }

    public function status(string $batchId): ?array
    {
        $t = DB::table(Tugas::TABLE)->where('Export_Id', $batchId)->where('Task_Type', self::TASK_TYPE)->first();
        if (! $t) {
            return null;
        }
        $p = json_decode((string) $t->Payload_Json, true) ?: [];

        return [
            'batch_id' => $t->Export_Id,
            'mode' => $p['mode'] ?? null,
            'sumber' => $p['sumber'] ?? null,
            'status' => $t->Status,
            'total' => (int) $t->Total_Records,
            'diproses' => (int) $t->Processed_Records,
            'persen' => (int) $t->Percent,
            'mulai' => $t->Started_At,
            'selesai' => $t->Completed_At,
            'galat' => $t->Error_Message,
        ];
    }
}
