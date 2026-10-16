<?php

namespace App\Services\KPI;

use Illuminate\Support\Facades\DB;

/**
 * Skor KPI AKUMULASI (Year-To-Date) untuk sekumpulan karyawan sekaligus.
 *
 * ── Kenapa berdiri sendiri ──────────────────────────────────────────────────
 *
 * Angka YTD muncul di tiga tempat: dashboard pribadi, dashboard tim, dan
 * (mulai sekarang) Monitoring Performance Tracking. Sebelum ini perhitungannya
 * hidup sebagai metode protected di dalam HomepageKpiService, sehingga pemakai
 * keempat mana pun hanya punya dua pilihan: menyalin kuerinya, atau mengarang
 * rumusnya sendiri. Di modul ini sudah terbukti apa akibatnya — `kpi:verify-ytd`
 * memakai aturan yang sedikit berbeda dan melaporkan skor 50 untuk orang yang
 * tidak punya data sama sekali, padahal dashboard mengeluarkannya dari hitungan.
 *
 * ── Definisinya ─────────────────────────────────────────────────────────────
 *
 * Per parameter: Σ Nilai_Actual dan Σ Nilai_Target sepanjang periode yang
 * diminta, dijalankan lewat rumus parameter, dijepit per parameter (50–125),
 * lalu dinormalisasi terhadap Σbobot. BUKAN rata-rata Total_Score bulanan —
 * dua metode itu memang berbeda dan tidak boleh saling menggantikan.
 *
 * Hanya realisasi yang HIDUP dan TERVERIFIKASI yang dihitung: Flag_Aktif='Y',
 * Status NULL, Flag_Approval='Y', Flag_Verifikasi='Y'. Pengajuan induknya pun
 * harus hidup dan disetujui.
 */
class KpiAkumulasiYtdService
{
    /**
     * @param  array<int,int|string>  $userIds
     * @param  array<int,string>  $periods  Daftar 'YYYY-MM'
     * @param  bool  $sertakanBobotNol  Sertakan user yang punya baris realisasi
     *                                  tetapi Σbobot-nya nol, sebagai 0.0.
     * @return array<string,float> Id_User => skor. Karyawan TANPA BARIS sama
     *                             sekali tidak pernah muncul di hasil —
     *                             ketiadaan kunci adalah tanda "tidak ada
     *                             data", bukan nol dan bukan angka lantai cap.
     */
    public function skorPerUser(array $userIds, int $year, array $periods, bool $sertakanBobotNol = false): array
    {
        $userIds = collect($userIds)->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();

        if (empty($userIds) || empty($periods)) {
            return [];
        }

        $rows = collect();

        // Dipotong 500: SQL Server menolak lebih dari 2.100 parameter, dan satu
        // scope monitoring bisa memuat ratusan karyawan sekaligus.
        foreach (array_chunk($userIds, 500) as $bagian) {
            $rows = $rows->merge($this->barisParameter($bagian, $year, $periods));
        }

        $scoring = app(KpiScoringService::class);
        $hasil = [];

        foreach ($rows->groupBy('Id_User') as $userId => $paramRows) {
            $paramDefs = $paramRows
                ->map(fn ($r) => [
                    'id' => (string) $r->id,
                    'bobot' => (float) $r->bobot,
                    'formula' => $r->formula,
                ])
                ->all();

            $sumTarget = $paramRows->mapWithKeys(fn ($r) => [(string) $r->id => (float) $r->tgt])->all();
            $sumActual = $paramRows->mapWithKeys(fn ($r) => [(string) $r->id => (float) $r->act])->all();

            $calc = $scoring->accumulatedScore($paramDefs, $sumTarget, $sumActual, [], ['cap' => true, 'round' => 1]);

            // total_bobot <= 0 berarti tidak ada parameter/realisasi. Tidak pernah
            // diberi angka lantai cap: "tidak punya data" dan "nilainya rendah"
            // adalah dua keadaan yang berbeda.
            //
            // $sertakanBobotNol ada demi satu pemanggil lama (dashboard homepage)
            // yang memang menghitung orang seperti itu sebagai 0. Bawaannya
            // dilewati, supaya pemakai baru tidak mewarisi perilaku itu tanpa
            // memilihnya.
            if ((float) $calc['total_bobot'] > 0) {
                $hasil[(string) $userId] = (float) $calc['final_pct'];
            } elseif ($sertakanBobotNol) {
                $hasil[(string) $userId] = 0.0;
            }
        }

        return $hasil;
    }

    /**
     * Sama seperti skorPerUser(), tetapi membawa serta rinciannya per parameter.
     *
     * Dipakai export Monitoring: berkasnya harus menunjukkan DARI MANA angka YTD
     * itu datang — Σtarget dan Σactual tiap parameter — bukan sekadar satu angka
     * akhir yang tidak bisa ditelusuri. Sengaja memakai kueri dan penilaian yang
     * persis sama dengan skorPerUser: kalau angka di berkas dan angka di layar
     * boleh berbeda satu digit pun, keduanya berhenti bisa dipercaya.
     *
     * Karyawan tanpa baris sama sekali tidak muncul, sama seperti skorPerUser().
     *
     * @param  array<int,int|string>  $userIds
     * @param  array<int,string>  $periods  Daftar 'YYYY-MM'
     * @return array<string,array<string,mixed>> Id_User => ringkasan + rincian parameter
     */
    public function rincianPerUser(array $userIds, int $year, array $periods): array
    {
        $userIds = collect($userIds)->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();

        if (empty($userIds) || empty($periods)) {
            return [];
        }

        $rows = collect();
        foreach (array_chunk($userIds, 500) as $bagian) {
            $rows = $rows->merge($this->barisParameter($bagian, $year, $periods));
        }

        $scoring = app(KpiScoringService::class);
        $hasil = [];

        foreach ($rows->groupBy('Id_User') as $userId => $paramRows) {
            $paramDefs = $paramRows
                ->map(fn ($r) => [
                    'id' => (string) $r->id,
                    'bobot' => (float) $r->bobot,
                    'formula' => $r->formula,
                ])
                ->all();

            $sumTarget = $paramRows->mapWithKeys(fn ($r) => [(string) $r->id => (float) $r->tgt])->all();
            $sumActual = $paramRows->mapWithKeys(fn ($r) => [(string) $r->id => (float) $r->act])->all();

            $calc = $scoring->accumulatedScore($paramDefs, $sumTarget, $sumActual, [], ['cap' => true, 'round' => 1]);

            if ((float) $calc['total_bobot'] <= 0) {
                continue;
            }

            $hasil[(string) $userId] = [
                'skor' => (float) $calc['final_pct'],
                'raw_pct' => (float) $calc['raw_pct'],
                'sum_weighted' => (float) $calc['sum_weighted'],
                'total_bobot' => (float) $calc['total_bobot'],
                'parameter' => $paramRows->map(function ($r) use ($calc) {
                    $item = $calc['items'][(string) $r->id] ?? [];

                    return [
                        'id' => (int) $r->id,
                        'nama_parameter' => $r->nama_parameter,
                        'bsc' => $r->bsc,
                        'satuan' => $r->satuan,
                        'rumus' => $r->formula,
                        'bobot' => (float) $r->bobot,
                        'sum_target' => (float) ($item['target'] ?? $r->tgt),
                        'sum_actual' => (float) ($item['actual'] ?? $r->act),
                        'score' => (float) ($item['score'] ?? 0),
                        'achievement' => (float) ($item['achievement'] ?? 0),
                    ];
                })->values()->all(),
            ];
        }

        return $hasil;
    }

    /** Periode Jan..$bulan pada satu tahun, dalam bentuk 'YYYY-MM'. */
    public function periodeSampai(int $year, int $bulan): array
    {
        $bulan = max(1, min(12, $bulan));

        return collect(range(1, $bulan))
            ->map(fn ($m) => sprintf('%04d-%02d', $year, $m))
            ->all();
    }

    /**
     * @param  array<int,string>  $userIds
     * @param  array<int,string>  $periods
     */
    private function barisParameter(array $userIds, int $year, array $periods)
    {
        return DB::table('KPI_Pengajuan_Detail as pd')
            ->join('KPI_Pengajuan as p', function ($j) {
                $j->on('p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
                    ->where('p.Flag_Aktif', 'Y')
                    ->where('p.Flag_Approval', 'Y')
                    ->whereNull('p.Status');
            })
            ->join('KPI_Master_Penjadwalan as mp', function ($j) use ($year) {
                $j->on('mp.Id_Master_Penjadwalan', '=', 'p.Id_Penjadwalan')
                    ->where('mp.Tahun', (string) $year);
            })
            ->leftJoin('KPI_Pengajuan_Det as pdet', function ($j) use ($periods) {
                $j->on('pdet.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail')
                    ->whereIn('pdet.Periode', $periods);
            })
            ->leftJoin('KPI_Update_Performance_Detail as upd', 'upd.Id_Pengajuan_Det', '=', 'pdet.Id_Pengajuan_Det')
            ->join('KPI_Update_Performance as up', function ($j) {
                $j->on('up.Id_Update_Performance', '=', 'upd.Id_Update_Performance')
                    ->where('up.Flag_Aktif', 'Y')
                    ->where('up.Flag_Approval', 'Y')
                    ->where('up.Flag_Verifikasi', 'Y')
                    ->whereNull('up.Status');
            })
            ->leftJoin('KPI_Master_Formula as mf', 'mf.Id_Master_Formula', '=', 'pd.Id_Master_Formula')
            ->leftJoin('KPI_Master_Bsc_Kategori as bc', 'bc.Id_Master_Bsc_Kategori', '=', 'pd.Id_Bsc_Kategori')
            ->leftJoin('KPI_Master_Satuan as ms', 'ms.Id_Master_Satuan', '=', 'pd.Id_Master_Satuan')
            ->whereIn('p.Id_User', $userIds)
            ->groupBy('p.Id_User', 'pd.Id_Pengajuan_Detail', 'pd.Bobot', 'pd.Id_Master_Formula')
            ->select(
                'p.Id_User',
                'pd.Id_Pengajuan_Detail as id',
                'pd.Bobot as bobot',
                DB::raw('COALESCE(SUM(pdet.Nilai_Target), 0) as tgt'),
                DB::raw('COALESCE(SUM(upd.Nilai_Actual), 0) as act'),
                DB::raw('MAX(CAST(mf.Rumus AS VARCHAR(MAX))) as formula'),
                // Diambil sebagai agregat, bukan lewat GROUP BY: menambah kolom ke
                // GROUP BY bisa memecah baris yang seharusnya satu, dan itu akan
                // diam-diam mengubah angka skorPerUser() yang memakai kueri ini juga.
                DB::raw('MAX(pd.Nama_Parameter) as nama_parameter'),
                DB::raw('MAX(bc.Nama_Kategori) as bsc'),
                DB::raw('MAX(ms.Simbol_Satuan) as satuan'),
            )
            ->get();
    }
}
