<?php

/**
 * Verifikasi READ-ONLY SESUDAH perbaikan: memanggil jalur kode yang sebenarnya
 * (KpiEnforcementController), bukan menyalin ulang querinya, lalu melaporkan
 * komposisi angka Monitoring vs Auto Cap dan sisa selisihnya.
 *
 * Penjaga: setiap statement non-SELECT menghentikan proses.
 *
 * Pakai: php scripts/audit/diag-kpi-sesudah-perbaikan.php 2026-07
 */
$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();

use App\Http\Controllers\KPI\Monitoring\Enforcement\KpiEnforcementController;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

DB::listen(function ($q) {
    if (! preg_match('/^(select|set|exec sp_)/i', ltrim($q->sql))) {
        fwrite(STDERR, 'BLOKIR non-SELECT: '.substr(ltrim($q->sql), 0, 120)."\n");
        exit(9);
    }
});

$periode = $argv[1] ?? '2026-07';

echo "=== SESUDAH PERBAIKAN — periode {$periode} (READ-ONLY) ===\n";
echo 'DB: '.config('database.connections.'.config('database.default').'.host')
    .' / '.config('database.connections.'.config('database.default').'.database')."\n\n";

$admin = User::first();
if (! $admin) {
    fwrite(STDERR, "Tidak ada akun untuk berperan sebagai admin.\n");
    exit(1);
}
// Guard 'web' membutuhkan request terikat sebelum setUser (jalur CLI tidak punya).
app()->instance('request', Request::create('/x', 'GET'));
Auth::setUser($admin);

$panggil = function (string $metode, array $params) use ($admin) {
    $req = Request::create('/x', 'GET', $params);
    $req->setUserResolver(fn () => $admin);
    app()->instance('request', $req);

    $res = app(KpiEnforcementController::class)->{$metode}($req);

    return [$res->getStatusCode(), json_decode($res->getContent(), true)];
};

// ── Monitoring: seluruh baris, lalu dikelompokkan per status ────────────────
[$kode, $isi] = $panggil('getPerformanceTracking', [
    'periode' => $periode, 'mode' => 'bulan', 'per_page' => 100, 'page' => 1,
]);
if ($kode !== 200) {
    fwrite(STDERR, "getPerformanceTracking balas {$kode}\n");
    exit(1);
}

$total = (int) ($isi['total_data'] ?? 0);
$perPage = 100;
$semua = collect($isi['result']['rows'] ?? $isi['result'] ?? []);
for ($hal = 2; $hal <= (int) ceil($total / $perPage); $hal++) {
    [, $lagi] = $panggil('getPerformanceTracking', [
        'periode' => $periode, 'mode' => 'bulan', 'per_page' => $perPage, 'page' => $hal,
    ]);
    $semua = $semua->merge($lagi['result']['rows'] ?? $lagi['result'] ?? []);
}

echo "MONITORING > Performance Tracking (universe {$total} baris)\n";
foreach ($semua->groupBy('status')->map->count()->sortDesc() as $status => $n) {
    echo sprintf("    %-20s %d\n", $status, $n);
}
$sasaran = ['tidak_submit', 'belum_submit', 'draft'];
$sisiA = $semua->whereIn('status', $sasaran);
echo '  A = Tidak Update + Belum Update + Draft = '.$sisiA->count()."\n\n";

// ── Auto Cap ────────────────────────────────────────────────────────────────
[$kode2, $isi2] = $panggil('getUnfilledEmployees', [
    'periode' => $periode, 'per_page' => 100, 'page' => 1,
]);
if ($kode2 !== 200) {
    fwrite(STDERR, "getUnfilledEmployees balas {$kode2}\n");
    exit(1);
}
$totalB = (int) ($isi2['total_data'] ?? 0);
$sisiB = collect($isi2['result']['rows'] ?? $isi2['result'] ?? []);
for ($hal = 2; $hal <= (int) ceil($totalB / 100); $hal++) {
    [, $lagi] = $panggil('getUnfilledEmployees', ['periode' => $periode, 'per_page' => 100, 'page' => $hal]);
    $sisiB = $sisiB->merge($lagi['result']['rows'] ?? $lagi['result'] ?? []);
}
echo 'ENFORCEMENT > Auto Cap: B = '.$totalB."\n\n";

// ── Selisih + sebabnya ──────────────────────────────────────────────────────
$kodeA = $sisiA->pluck('kode_karyawan')->flip();
$kodeB = $sisiB->pluck('kode_karyawan')->flip();

$hanyaA = $sisiA->reject(fn ($r) => $kodeB->has($r['kode_karyawan']))->values();
$hanyaB = $sisiB->reject(fn ($r) => $kodeA->has($r['kode_karyawan']))->values();

echo 'Irisan = '.$sisiA->filter(fn ($r) => $kodeB->has($r['kode_karyawan']))->count()
    .' | hanya A = '.$hanyaA->count().' | hanya B = '.$hanyaB->count()."\n";

$statusKaryawan = function ($kodeList) {
    return DB::table('Karyawan')->whereIn('Kode_Karyawan', $kodeList ?: ['-'])
        ->get(['Kode_Karyawan', 'Nama', 'Aktif', 'Tanggal_Resign'])->keyBy('Kode_Karyawan');
};

if ($hanyaA->isNotEmpty()) {
    echo "\n  Hanya di Monitoring:\n";
    $k = $statusKaryawan($hanyaA->pluck('kode_karyawan')->all());
    foreach ($hanyaA as $r) {
        $x = $k->get($r['kode_karyawan']);
        echo '    '.str_pad($r['kode_karyawan'], 18).' | status='.str_pad($r['status'], 14)
            .' | Aktif='.($x->Aktif ?? '?')
            .' | resign='.(($x->Tanggal_Resign ?? null) ? substr((string) $x->Tanggal_Resign, 0, 10) : '-')."\n";
    }
}
if ($hanyaB->isNotEmpty()) {
    echo "\n  Hanya di Auto Cap:\n";
    $k = $statusKaryawan($hanyaB->pluck('kode_karyawan')->all());
    foreach ($hanyaB as $r) {
        $x = $k->get($r['kode_karyawan']);
        echo '    '.str_pad($r['kode_karyawan'], 18).' | Aktif='.($x->Aktif ?? '?')
            .' | resign='.(($x->Tanggal_Resign ?? null) ? substr((string) $x->Tanggal_Resign, 0, 10) : '-')."\n";
    }
}

// ── Penjaga: tidak boleh ada yang resign SEBELUM periode di kedua sisi ───────
$awal = $periode.'-01';
$cek = function ($kumpulan, string $label) use ($awal, $statusKaryawan) {
    $k = $statusKaryawan($kumpulan->pluck('kode_karyawan')->all());
    $pelanggar = $k->filter(fn ($x) => $x->Aktif !== 'Y'
        && (empty($x->Tanggal_Resign) || substr((string) $x->Tanggal_Resign, 0, 10) < $awal));
    echo "\n  {$label}: resign sebelum {$awal} yang masih lolos = ".$pelanggar->count()."\n";
    foreach ($pelanggar->take(5) as $x) {
        echo '      '.$x->Kode_Karyawan.' resign='.substr((string) $x->Tanggal_Resign, 0, 10)."\n";
    }
};
$cek($sisiA, 'Monitoring');
$cek($sisiB, 'Auto Cap');

echo "\nSelesai (read-only).\n";
