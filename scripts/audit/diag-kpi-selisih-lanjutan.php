<?php

/**
 * Lanjutan READ-ONLY dari diag-kpi-monitoring-vs-autocap.php:
 *  (1) pastikan 35 kandidat Auto Cap yang tak muncul di monitoring memang
 *      karyawan non-aktif (nilai kolom Aktif + tanggal resign),
 *  (2) telusuri kenapa 4 baris berstatus Draft tidak ikut jadi kandidat Auto Cap.
 *
 * Pakai: php scripts/audit/diag-kpi-selisih-lanjutan.php 2026-07
 */
$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

DB::listen(function ($q) {
    if (! preg_match('/^(select|set|exec sp_)/i', ltrim($q->sql))) {
        fwrite(STDERR, "BLOKIR non-SELECT\n");
        exit(9);
    }
});

$periode = $argv[1] ?? '2026-07';
$tahun = (int) substr($periode, 0, 4);

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
    ->distinct()->get(['p.Id_User', 'k.Kode_Karyawan', 'k.Nama', 'k.Aktif']);

$nonAktif = $unfilled->filter(fn ($r) => $r->Aktif !== 'Y');
echo 'Kandidat Auto Cap total: '.$unfilled->count().', non-aktif: '.$nonAktif->count()."\n";
echo 'Sebaran nilai kolom Aktif: '.json_encode($unfilled->groupBy('Aktif')->map->count())."\n\n";

// (2) empat draft yang tidak jadi kandidat Auto Cap
$scheduleIds = DB::table('KPI_Master_Penjadwalan')
    ->where('Flag_Penilaian_KPI', 'Y')->where('Periode', $periode)
    ->pluck('Id_Master_Penjadwalan')->all();

$draftUsers = DB::table('KPI_Update_Performance_Sementara')
    ->whereIn('Id_Penjadwalan', $scheduleIds)->where('Flag_Aktif', 'Y')
    ->where(fn ($q) => $q->whereNull('Flag_Pindah')->orWhere('Flag_Pindah', '<>', 'Y'))
    ->distinct()->pluck('Id_User')->map(fn ($v) => (int) $v)->all();

$idB = $unfilled->pluck('Id_User')->map(fn ($v) => (int) $v)->all();
$draftOnly = array_values(array_diff($draftUsers, $idB));
echo 'Draft hidup: '.count($draftUsers).', di antaranya BUKAN kandidat Auto Cap: '.count($draftOnly)."\n";

foreach ($draftOnly as $uid) {
    $k = DB::table('Karyawan')->where('UserID_Web', $uid)->first(['Kode_Karyawan', 'Nama', 'Aktif']);
    $pengajuan = DB::table('KPI_Pengajuan as p')
        ->join('KPI_Master_Penjadwalan as j', 'p.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
        ->where('p.Id_User', $uid)->where('j.Tahun', $tahun)
        ->get(['p.Id_Pengajuan', 'p.Flag_Aktif', 'p.Flag_Approval', 'p.Status', 'j.Id_Master_Penjadwalan']);
    $adaTargetBulan = DB::table('KPI_Pengajuan_Detail as pd')
        ->join('KPI_Pengajuan_Det as pdet', 'pdet.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail')
        ->whereIn('pd.Id_Pengajuan', $pengajuan->pluck('Id_Pengajuan')->all() ?: [0])
        ->where('pdet.Periode', 'like', $periode.'%')->count();
    $upAktif = DB::table('KPI_Update_Performance')->where('Id_User', $uid)
        ->where('Flag_Aktif', 'Y')->whereNull('Status')->where('Periode', $periode)->count();

    echo "\n  uid={$uid} ".($k->Kode_Karyawan ?? '?').' | '.($k->Nama ?? '?').' | Aktif='.($k->Aktif ?? '?')."\n";
    echo '    pengajuan tahun '.$tahun.': '.$pengajuan->count()
        .' (approved&aktif&hidup: '.$pengajuan->filter(fn ($p) => $p->Flag_Aktif === 'Y' && $p->Flag_Approval === 'Y' && $p->Status === null)->count().")\n";
    echo "    baris target periode {$periode}: {$adaTargetBulan}\n";
    echo "    KPI_Update_Performance hidup periode {$periode}: {$upAktif}\n";
}

echo "\nSelesai (read-only).\n";
