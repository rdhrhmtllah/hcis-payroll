<?php

/**
 * Verifikasi READ-ONLY atas klaim "gap 121 vs 90 = karyawan non-aktif".
 *
 * Yang diuji:
 *  (1) 35 kandidat non-aktif itu benar resign? Kapan? (Tanggal_Resign vs periode)
 *  (2) Apakah mereka sebenarnya SUDAH mengisi tapi barisnya dibatalkan/Flag_Aktif='T'?
 *  (3) Di sisi monitoring: adakah yang dihitung "sudah mengisi" padahal baris
 *      performance-nya periode LAIN (cocok lewat jalur Id_Penjadwalan)?
 *  (4) Adakah karyawan aktif dgn performance hidup periode ini yang JUSTRU di
 *      luar universe monitoring (hilang dari layar)?
 *  (5) Apakah irisan 86 benar-benar orang yang sama di kedua sisi?
 *
 * Pakai: php scripts/audit/diag-kpi-verifikasi-gap.php 2026-07
 */
$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

DB::listen(function ($q) {
    if (! preg_match('/^(select|set|exec sp_)/i', ltrim($q->sql))) {
        fwrite(STDERR, 'BLOKIR non-SELECT: '.substr(ltrim($q->sql), 0, 120)."\n");
        exit(9);
    }
});

$periode = $argv[1] ?? '2026-07';
$periodeDate = Carbon::parse($periode.'-01');
$awalBulan = $periodeDate->copy()->startOfMonth();
$akhirBulan = $periodeDate->copy()->endOfMonth();
$tahun = $periodeDate->year;

echo "=== VERIFIKASI GAP KPI {$periode} (READ-ONLY) ===\n";
echo 'DB: '.config('database.connections.'.config('database.default').'.host')
    .' / '.config('database.connections.'.config('database.default').'.database')."\n\n";

// --- Sisi B: kandidat Auto Cap -------------------------------------------------
$unfilled = DB::table('KPI_Pengajuan as p')
    ->join('KPI_Master_Penjadwalan as j', 'p.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
    ->join('Karyawan as k', 'p.Id_User', '=', 'k.UserID_Web')
    ->where('p.Flag_Aktif', 'Y')->where('p.Flag_Approval', 'Y')->whereNull('p.Status')
    ->where('j.Tahun', $tahun)
    ->whereExists(function ($q) use ($periode) {
        $q->select(DB::raw(1))->from('KPI_Pengajuan_Detail as pd')
            ->join('KPI_Pengajuan_Det as pdet', 'pdet.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail')
            ->whereColumn('pd.Id_Pengajuan', 'p.Id_Pengajuan')
            ->where('pdet.Periode', 'like', $periode.'%');
    })
    ->whereNotExists(function ($q) use ($periode) {
        $q->select(DB::raw(1))->from('KPI_Update_Performance as up')
            ->whereColumn('up.Id_User', 'p.Id_User')
            ->where('up.Flag_Aktif', 'Y')->whereNull('up.Status')->where('up.Periode', $periode);
    })
    ->distinct()
    ->get(['p.Id_User', 'k.Kode_Karyawan', 'k.Nama', 'k.Aktif', 'k.Tanggal_Resign',
        'k.Tgl_Masuk', 'k.Status_Karyawan']);

$nonAktif = $unfilled->filter(fn ($r) => $r->Aktif !== 'Y')->values();
echo '(1) Kandidat Auto Cap: '.$unfilled->count().' | Aktif=Y: '
    .$unfilled->count() - $nonAktif->count().' | Aktif<>Y: '.$nonAktif->count()."\n";

$kat = ['resign_sebelum_bulan_ini' => 0, 'resign_DALAM_bulan_ini' => 0,
    'resign_SESUDAH_bulan_ini' => 0, 'tanpa_Tanggal_Resign' => 0];
$detail = [];
foreach ($nonAktif as $r) {
    if (empty($r->Tanggal_Resign)) {
        $kat['tanpa_Tanggal_Resign']++;
        $k = 'tanpa_Tanggal_Resign';
    } else {
        $tr = Carbon::parse($r->Tanggal_Resign);
        if ($tr->lt($awalBulan)) {
            $k = 'resign_sebelum_bulan_ini';
        } elseif ($tr->lte($akhirBulan)) {
            $k = 'resign_DALAM_bulan_ini';
        } else {
            $k = 'resign_SESUDAH_bulan_ini';
        }
        $kat[$k]++;
    }
    $detail[$k][] = $r;
}
foreach ($kat as $k => $v) {
    echo sprintf("    %-28s %d\n", $k, $v);
}
foreach ($detail as $k => $list) {
    echo "\n    [$k] semua:\n";
    foreach ($list as $r) {
        echo '      '.str_pad($r->Kode_Karyawan, 18).' | '.str_pad(substr($r->Nama, 0, 28), 28)
            .' | masuk='.substr((string) $r->Tgl_Masuk, 0, 10)
            .' | resign='.(($r->Tanggal_Resign) ? substr((string) $r->Tanggal_Resign, 0, 10) : '-')
            .' | status='.($r->Status_Karyawan ?? '-')."\n";
    }
}

// --- (2) Apakah non-aktif itu sebenarnya sudah mengisi (baris mati/dibatalkan)? --
echo "\n(2) Jejak KPI_Update_Performance periode {$periode} milik ".$nonAktif->count()." orang non-aktif:\n";
$idNon = $nonAktif->pluck('Id_User')->map(fn ($v) => (int) $v)->all();
$jejak = DB::table('KPI_Update_Performance')
    ->whereIn('Id_User', $idNon ?: [0])
    ->where('Periode', $periode)
    ->get(['Id_User', 'No_Transaksi', 'Flag_Aktif', 'Status', 'Flag_Approval']);
echo '    baris ditemukan: '.$jejak->count()."\n";
foreach ($jejak->groupBy(fn ($r) => 'Flag_Aktif='.($r->Flag_Aktif ?? 'NULL').' Status='.($r->Status ?? 'NULL')) as $k => $g) {
    echo sprintf("      %-34s %d\n", $k, $g->count());
}
$jejakLain = DB::table('KPI_Update_Performance')
    ->whereIn('Id_User', $idNon ?: [0])
    ->where('Periode', 'like', $tahun.'%')
    ->where('Flag_Aktif', 'Y')->whereNull('Status')
    ->get(['Id_User', 'Periode']);
echo '    (pembanding) baris HIDUP mereka di bulan lain '.$tahun.': '.$jejakLain->count()
    .' -> '.json_encode($jejakLain->groupBy('Periode')->map->count())."\n";

// --- Sisi A: universe monitoring ---------------------------------------------
$schedules = DB::table('KPI_Master_Penjadwalan')
    ->where('Flag_Penilaian_KPI', 'Y')->where('Periode', $periode)
    ->get(['Id_Master_Penjadwalan', 'Flag_Perorangan']);
$scheduleIds = $schedules->pluck('Id_Master_Penjadwalan')->all();
$hasGlobal = $schedules->contains(fn ($s) => ($s->Flag_Perorangan ?? 'T') !== 'Y');
$peroranganIds = $schedules->filter(fn ($s) => ($s->Flag_Perorangan ?? 'T') === 'Y')
    ->pluck('Id_Master_Penjadwalan')->all();

$mkUniverse = function () use ($hasGlobal, $peroranganIds) {
    $q = DB::table('Karyawan as k')->where('k.Aktif', 'Y');
    if (! $hasGlobal) {
        $q->whereIn('k.Kode_Karyawan', function ($sub) use ($peroranganIds) {
            $sub->from('N_KPI_Master_Penjadwalan_Perorangan')
                ->whereIn('Id_Master_Penjadwalan', $peroranganIds)->select('Kode_Karyawan');
        });
    }

    return $q;
};

$rows = $mkUniverse()
    ->leftJoin('KPI_Update_Performance as up', function ($join) use ($scheduleIds, $periode) {
        $join->on('up.Id_User', '=', 'k.UserID_Web')
            ->where('up.Flag_Aktif', '=', 'Y')->whereNull('up.Status')
            ->where(function ($q) use ($scheduleIds, $periode) {
                $q->whereIn('up.Id_Penjadwalan', $scheduleIds)
                    ->orWhere('up.Periode', 'like', $periode.'%');
            });
    })
    ->get(['k.UserID_Web', 'k.Kode_Karyawan', 'k.Nama',
        'up.Id_Update_Performance', 'up.Periode as Up_Periode', 'up.Id_Penjadwalan as Up_Jadwal'])
    ->groupBy('Kode_Karyawan')
    ->map(fn ($g) => $g->sortByDesc(fn ($r) => $r->Id_Update_Performance ?? 0)->first())
    ->values();

// --- (3) "sudah mengisi" tapi periodenya bukan periode ini --------------------
$sudah = $rows->filter(fn ($r) => (bool) $r->Id_Update_Performance);
$salahPeriode = $sudah->filter(fn ($r) => substr((string) $r->Up_Periode, 0, 7) !== $periode);
echo "\n(3) Monitoring menandai 'sudah mengisi': ".$sudah->count()
    .' | di antaranya periode baris BUKAN '.$periode.': '.$salahPeriode->count()."\n";
foreach ($salahPeriode->take(10) as $r) {
    echo '      '.str_pad($r->Kode_Karyawan, 18).' | up.Periode='.$r->Up_Periode.' up.Jadwal='.$r->Up_Jadwal."\n";
}

// --- (4) performance hidup periode ini milik orang di LUAR universe -----------
$semuaHidup = DB::table('KPI_Update_Performance as up')
    ->join('Karyawan as k', 'up.Id_User', '=', 'k.UserID_Web')
    ->where('up.Flag_Aktif', 'Y')->whereNull('up.Status')->where('up.Periode', $periode)
    ->get(['up.Id_User', 'k.Kode_Karyawan', 'k.Aktif']);
$universeKode = $rows->pluck('Kode_Karyawan')->flip();
$diLuar = $semuaHidup->filter(fn ($r) => ! $universeKode->has($r->Kode_Karyawan));
echo "\n(4) Performance HIDUP periode {$periode}: ".$semuaHidup->count()
    .' | pemiliknya di luar universe monitoring: '.$diLuar->count()
    .' (aktif='.$diLuar->where('Aktif', 'Y')->count().', non-aktif='.$diLuar->where('Aktif', '!=', 'Y')->count().")\n";
foreach ($diLuar->where('Aktif', 'Y')->take(10) as $r) {
    echo '      AKTIF tapi di luar universe: '.$r->Kode_Karyawan."\n";
}

// --- (5) irisan --------------------------------------------------------------
$draftUsers = DB::table('KPI_Update_Performance_Sementara')
    ->whereIn('Id_Penjadwalan', $scheduleIds)->where('Flag_Aktif', 'Y')
    ->where(fn ($q) => $q->whereNull('Flag_Pindah')->orWhere('Flag_Pindah', '<>', 'Y'))
    ->distinct()->pluck('Id_User')->map(fn ($v) => (int) $v)->flip();

$cand = $rows->filter(fn ($r) => ! $r->Id_Update_Performance && ! $draftUsers->has((int) $r->UserID_Web))
    ->pluck('UserID_Web')->map(fn ($v) => (int) $v)->all();
$cls = app(\App\Services\KPI\KpiEnforcementService::class)
    ->classifyParameterTargetForPeriod($cand, $periodeDate);

$setA = [];
foreach ($rows as $r) {
    $uid = (int) $r->UserID_Web;
    if ($r->Id_Update_Performance) {
        continue;
    }
    if ($draftUsers->has($uid)) {
        $setA[$uid] = 'draft';

        continue;
    }
    if (($cls[$uid] ?? 'no_parameter') === 'has') {
        $setA[$uid] = 'belum/tidak update';
    }
}
$setB = $unfilled->keyBy(fn ($r) => (int) $r->Id_User);

$irisan = array_intersect_key($setA, $setB->all());
echo "\n(5) |A| = ".count($setA).' | |B| = '.$setB->count().' | irisan = '.count($irisan)
    .' | hanya A = '.count(array_diff_key($setA, $setB->all()))
    .' | hanya B = '.count(array_diff_key($setB->all(), $setA))."\n";

$hanyaB = array_diff_key($setB->all(), $setA);
$aktifTapiHanyaB = array_filter($hanyaB, fn ($r) => $r->Aktif === 'Y');
echo '    hanya-B yang karyawannya AKTIF (= sisa yang TIDAK terjelaskan resign): '
    .count($aktifTapiHanyaB)."\n";
foreach ($aktifTapiHanyaB as $uid => $r) {
    echo '      '.$r->Kode_Karyawan.' | '.$r->Nama."\n";
}

echo "\nSelesai (read-only).\n";
