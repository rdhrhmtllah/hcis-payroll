<?php

/**
 * Diagnostik READ-ONLY: kenapa jumlah "Tidak Update + Belum Update + Draft" di
 * tab Monitoring > Performance Tracking berbeda dengan jumlah kandidat di tab
 * Enforcement (Auto Cap) untuk periode yang sama.
 *
 * Tidak menulis apa pun. Semua statement non-SELECT diblokir listener di bawah.
 *
 * Pakai: php scripts/audit/diag-kpi-monitoring-vs-autocap.php 2026-07
 */
$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

DB::listen(function ($q) {
    $sql = ltrim($q->sql);
    if (! preg_match('/^(select|set|exec sp_)/i', $sql)) {
        fwrite(STDERR, "BLOKIR statement non-SELECT: ".substr($sql, 0, 120)."\n");
        exit(9);
    }
});

$periode = $argv[1] ?? '2026-07';
$periodeDate = Carbon::parse($periode.'-01');
$tahun = $periodeDate->year;

echo "== Periode: {$periode} (tahun {$tahun}) ==\n";
echo '== DB: '.config('database.connections.'.config('database.default').'.host')
    .' / '.config('database.connections.'.config('database.default').'.database')." ==\n\n";

// ---------------------------------------------------------------------------
// SISI A — Monitoring > Performance Tracking
// ---------------------------------------------------------------------------
$schedules = DB::table('KPI_Master_Penjadwalan')
    ->where('Flag_Penilaian_KPI', 'Y')
    ->where('Periode', $periode)
    ->get(['Id_Master_Penjadwalan', 'Flag_Perorangan', 'Jadwal_Akhir', 'Nama_Kegiatan']);

echo "Penjadwalan PENILAIAN dgn Periode={$periode}: ".$schedules->count()."\n";
foreach ($schedules as $s) {
    echo "  #{$s->Id_Master_Penjadwalan} peror={$s->Flag_Perorangan} akhir={$s->Jadwal_Akhir} {$s->Nama_Kegiatan}\n";
}
if ($schedules->isEmpty()) {
    echo "Tidak ada penjadwalan penilaian -> monitoring balas 404.\n";
    exit(0);
}

$scheduleIds = $schedules->pluck('Id_Master_Penjadwalan')->all();
$hasGlobal = $schedules->contains(fn ($s) => ($s->Flag_Perorangan ?? 'T') !== 'Y');
$peroranganIds = $schedules->filter(fn ($s) => ($s->Flag_Perorangan ?? 'T') === 'Y')
    ->pluck('Id_Master_Penjadwalan')->all();

$universe = function () use ($hasGlobal, $peroranganIds) {
    $q = DB::table('Karyawan as k')->where('k.Aktif', 'Y');
    if (! $hasGlobal) {
        $q->whereIn('k.Kode_Karyawan', function ($sub) use ($peroranganIds) {
            $sub->from('N_KPI_Master_Penjadwalan_Perorangan')
                ->whereIn('Id_Master_Penjadwalan', $peroranganIds)
                ->select('Kode_Karyawan');
        });
    }

    return $q;
};

$rows = $universe()
    ->leftJoin('KPI_Update_Performance as up', function ($join) use ($scheduleIds, $periode) {
        $join->on('up.Id_User', '=', 'k.UserID_Web')
            ->where('up.Flag_Aktif', '=', 'Y')
            ->whereNull('up.Status')
            ->where(function ($q) use ($scheduleIds, $periode) {
                $q->whereIn('up.Id_Penjadwalan', $scheduleIds)
                    ->orWhere('up.Periode', 'like', $periode.'%');
            });
    })
    ->get([
        'k.UserID_Web', 'k.Kode_Karyawan', 'k.Nama',
        'up.Id_Update_Performance', 'up.Periode as Up_Periode', 'up.Id_Penjadwalan as Up_Jadwal',
    ])
    ->groupBy('Kode_Karyawan')
    ->map(fn ($g) => $g->sortByDesc(fn ($r) => $r->Id_Update_Performance ?? 0)->first())
    ->values();

echo "\nUniverse monitoring (karyawan aktif terjadwal): ".$rows->count()."\n";

$draftByUser = DB::table('KPI_Update_Performance_Sementara')
    ->whereIn('Id_Penjadwalan', $scheduleIds)
    ->where('Flag_Aktif', 'Y')
    ->where(fn ($q) => $q->whereNull('Flag_Pindah')->orWhere('Flag_Pindah', '<>', 'Y'))
    ->get(['Id_User', 'Id_Update_Performance_Sementara'])
    ->groupBy('Id_User')
    ->map(fn ($g) => $g->sortByDesc('Id_Update_Performance_Sementara')->first());

$candidateIds = $rows
    ->filter(fn ($r) => ! $r->Id_Update_Performance && ! $draftByUser->get($r->UserID_Web))
    ->pluck('UserID_Web')->map(fn ($v) => (int) $v)->all();

$svc = app(\App\Services\KPI\KpiEnforcementService::class);
$cls = $svc->classifyParameterTargetForPeriod($candidateIds, $periodeDate);

$bucket = ['sudah_isi' => [], 'draft' => [], 'belum_tidak_update' => [], 'tanpa_target' => [], 'tanpa_parameter' => []];
foreach ($rows as $r) {
    $uid = (int) $r->UserID_Web;
    if ($r->Id_Update_Performance) {
        $bucket['sudah_isi'][$uid] = $r;

        continue;
    }
    if ($draftByUser->get($r->UserID_Web)) {
        $bucket['draft'][$uid] = $r;

        continue;
    }
    $c = $cls[$uid] ?? 'no_parameter';
    if ($c === 'no_parameter') {
        $bucket['tanpa_parameter'][$uid] = $r;
    } elseif ($c === 'no_target') {
        $bucket['tanpa_target'][$uid] = $r;
    } else {
        $bucket['belum_tidak_update'][$uid] = $r;
    }
}

foreach ($bucket as $k => $v) {
    echo sprintf("  %-20s %d\n", $k, count($v));
}
$setA = $bucket['belum_tidak_update'] + $bucket['draft'];
echo 'SISI A (Belum/Tidak Update + Draft) = '.count($setA)."\n";

// ---------------------------------------------------------------------------
// SISI B — Enforcement / Auto Cap (unfilledBaseQuery)
// ---------------------------------------------------------------------------
$unfilled = DB::table('KPI_Pengajuan as p')
    ->join('KPI_Master_Penjadwalan as j', 'p.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
    ->join('Karyawan as k', 'p.Id_User', '=', 'k.UserID_Web')
    ->where('p.Flag_Aktif', 'Y')
    ->where('p.Flag_Approval', 'Y')
    ->whereNull('p.Status')
    ->where('j.Tahun', $tahun)
    ->whereExists(function ($q) use ($periode) {
        $q->select(DB::raw(1))
            ->from('KPI_Pengajuan_Detail as pd')
            ->join('KPI_Pengajuan_Det as pdet', 'pdet.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail')
            ->whereColumn('pd.Id_Pengajuan', 'p.Id_Pengajuan')
            ->where('pdet.Periode', 'like', $periode.'%');
    })
    ->whereNotExists(function ($q) use ($periode) {
        $q->select(DB::raw(1))
            ->from('KPI_Update_Performance as up')
            ->whereColumn('up.Id_User', 'p.Id_User')
            ->where('up.Flag_Aktif', 'Y')
            ->whereNull('up.Status')
            ->where('up.Periode', $periode);
    })
    ->distinct()
    ->get(['p.Id_User', 'k.Kode_Karyawan', 'k.Nama', 'k.Aktif']);

$setB = [];
foreach ($unfilled as $r) {
    $setB[(int) $r->Id_User] = $r;
}
echo "\nSISI B (Auto Cap 'belum mengisi') = ".count($setB)."\n";

// ---------------------------------------------------------------------------
// SELISIH
// ---------------------------------------------------------------------------
$onlyB = array_diff_key($setB, $setA);
$onlyA = array_diff_key($setA, $setB);
echo "\nHanya di B (muncul di Auto Cap, tidak di 91): ".count($onlyB)."\n";
echo 'Hanya di A (muncul di 91, tidak di Auto Cap): '.count($onlyA)."\n";

$alasan = [];
foreach ($onlyB as $uid => $r) {
    if (($r->Aktif ?? null) !== 'Y') {
        $alasan['karyawan_nonaktif'][] = $r;

        continue;
    }
    if (! isset($bucket['sudah_isi'][$uid]) && ! isset($bucket['draft'][$uid])
        && ! isset($bucket['belum_tidak_update'][$uid]) && ! isset($bucket['tanpa_target'][$uid])
        && ! isset($bucket['tanpa_parameter'][$uid])) {
        $alasan['diluar_universe_penjadwalan_penilaian'][] = $r;

        continue;
    }
    if (isset($bucket['sudah_isi'][$uid])) {
        $up = $bucket['sudah_isi'][$uid];
        $key = ($up->Up_Periode === $periode) ? 'sudah_isi_periode_sama' : 'sudah_isi_TAPI_periode_lain';
        $alasan[$key][] = (object) ['Kode_Karyawan' => $r->Kode_Karyawan, 'Nama' => $r->Nama,
            'Up_Periode' => $up->Up_Periode, 'Up_Jadwal' => $up->Up_Jadwal];

        continue;
    }
    if (isset($bucket['tanpa_target'][$uid])) {
        $alasan['distatus_Tidak_Ada_Target'][] = $r;

        continue;
    }
    if (isset($bucket['tanpa_parameter'][$uid])) {
        $alasan['distatus_Tidak_Ada_Parameter'][] = $r;

        continue;
    }
    $alasan['lainnya'][] = $r;
}

echo "\n-- Rincian sebab baris yang hanya ada di Auto Cap --\n";
foreach ($alasan as $k => $v) {
    echo sprintf("  %-38s %d\n", $k, count($v));
}
foreach ($alasan as $k => $v) {
    echo "\n  [$k] contoh maks 8:\n";
    foreach (array_slice($v, 0, 8) as $r) {
        echo '    '.$r->Kode_Karyawan.' | '.$r->Nama
            .(isset($r->Up_Periode) ? ' | up.Periode='.$r->Up_Periode.' up.Jadwal='.$r->Up_Jadwal : '')
            ."\n";
    }
}

echo "\n-- Contoh baris yang hanya ada di Monitoring (A) --\n";
foreach (array_slice($onlyA, 0, 8, true) as $uid => $r) {
    $tipe = isset($bucket['draft'][$uid]) ? 'draft' : 'belum/tidak update';
    echo '    '.$r->Kode_Karyawan.' | '.$r->Nama.' | '.$tipe."\n";
}

echo "\nSelesai (read-only).\n";
