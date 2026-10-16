<?php

/**
 * Verifikasi READ-ONLY: berkas export KPI harus menyebut ORANG dan STATUS yang
 * sama persis dengan layar monitoring.
 *
 * Kenapa ini diperiksa terpisah: export dibangun belakangan oleh job dari
 * payload, bukan dari objek yang hidup di request. Begitu aturan kepegawaian
 * atau aturan label berubah di layar tanpa ikut dititipkan ke payload, admin
 * memilih baris dari tabel lalu menerima berkas yang menyebut himpunan lain —
 * dan ketidakcocokan diam seperti itu tidak memunculkan satu pun galat.
 *
 * Pakai: php scripts/audit/diag-kpi-export-vs-layar.php 2026-07
 */
$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();

use App\Http\Controllers\KPI\Monitoring\Enforcement\KpiEnforcementController;
use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\KpiExportService;
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
$tahun = substr($periode, 0, 4);

echo "=== Export vs layar — periode {$periode} (READ-ONLY) ===\n";
echo 'DB: '.config('database.connections.'.config('database.default').'.database')."\n\n";

$admin = User::first();
app()->instance('request', Request::create('/x', 'GET'));
Auth::setUser($admin);

$panggil = function (string $metode, array $params) use ($admin) {
    $peta = [];
    for ($h = 1; $h <= 40; $h++) {
        $req = Request::create('/x', 'GET', $params + ['per_page' => 100, 'page' => $h]);
        $req->setUserResolver(fn () => $admin);
        app()->instance('request', $req);
        $res = app(KpiEnforcementController::class)->{$metode}($req);
        if ($res->getStatusCode() !== 200) {
            return null;
        }
        $isi = json_decode($res->getContent(), true);
        $rows = $isi['result']['rows'] ?? $isi['result'] ?? [];
        foreach ($rows as $r) {
            $peta[$r['kode_karyawan']] = $r;
        }
        if (count($peta) >= (int) ($isi['total_data'] ?? 0) || ! $rows) {
            break;
        }
    }

    return $peta;
};

/** Bangun dataset export lewat jalur job yang sebenarnya. */
$dataset = function (array $payload) {
    $job = (object) [
        'Export_Id' => 'DIAG-'.bin2hex(random_bytes(4)),
        'Format' => 'excel',
        'Payload_Json' => json_encode($payload),
        'Total_Records' => 1,
    ];
    $m = new ReflectionMethod(KpiExportService::class, 'buildDataset');
    $m->setAccessible(true);

    return $m->invoke(app(KpiExportService::class), $job);
};

/** Meta jadwal, diturunkan lewat metode yang dipakai layar. */
$meta = function (string $jenis, string $scope) {
    $m = new ReflectionMethod(KpiEnforcementController::class, 'exportScheduleMeta');
    $m->setAccessible(true);

    return $m->invoke(app(KpiEnforcementController::class), $jenis, $scope);
};

// ── 1. PERFORMANCE: layar bulanan vs berkas ────────────────────────────────
$layar = $panggil('getPerformanceTracking', ['periode' => $periode, 'mode' => 'bulan']);
$metaPerf = $meta('performance', $periode);
echo "1) PERFORMANCE TRACKING\n";
echo '   meta membawa batas_kepegawaian : '.($metaPerf['batas_kepegawaian'] ?? '(TIDAK ADA)')."\n";
echo '   meta membawa global_berjalan   : '
    .(array_key_exists('global_berjalan', $metaPerf) ? var_export($metaPerf['global_berjalan'], true) : '(TIDAK ADA)')."\n";

/* select_all WAJIB. Tanpa penanda itu, buildDataset membaca mode "manual" dan
   id_users menjadi array kosong, sehingga chunkedGet memulangkan nol baris —
   bukan karena datanya tidak ada, melainkan karena payload-nya salah bentuk. */
/*
 * Membangun dataset export MENULIS: bumpProgress() meng-UPDATE
 * N_HRIS_Async_Tasks sepanjang perulangan. Sah di development, TIDAK boleh
 * terhadap production — jadi jalur ini tertutup kecuali diminta eksplisit lewat
 * --dengan-export.
 *
 * Yang tetap bisa diperiksa tanpa menulis sebutir pun adalah dua hal yang
 * menentukan SIAPA yang masuk berkas: universe export dan batas kepegawaian
 * pada meta.
 *
 * Catatan payload: select_all WAJIB ada. Tanpa penanda itu buildDataset membaca
 * mode "manual" dan id_users menjadi array kosong, sehingga hasilnya nol baris —
 * bukan karena datanya tidak ada, melainkan karena payload-nya salah bentuk.
 */
$bolehExport = in_array('--dengan-export', $argv, true);

if ($bolehExport) {
    $ds = $dataset([
        'jenis' => 'performance',
        'scope_value' => $periode,
        'mode' => 'bulan',
        'select_all' => true,
        'filters' => [],
        'schedule_meta' => $metaPerf,
    ]);

    $berkas = [];
    foreach ($ds['rows'] as $r) {
        $berkas[$r['kode_karyawan']] = $r['status'] ?? '?';
    }

    echo '   layar  : '.count($layar)." baris
";
    echo '   berkas : '.count($berkas)." baris
";
    echo '   hanya di layar : '.count(array_diff_key($layar, $berkas))
        .' | hanya di berkas : '.count(array_diff_key($berkas, $layar))."
";

    $petaLabel = [
        'belum_submit' => 'Belum Update', 'tidak_submit' => 'Tidak Update',
        'draft' => 'Draft', 'approved' => 'Disetujui', 'waiting' => 'Menunggu Approval',
        'rejected' => 'Ditolak', 'tanpa_parameter' => 'Tidak Ada Parameter',
        'tanpa_target' => 'Tidak Ada Target',
    ];
    $beda = 0;
    foreach ($layar as $k => $r) {
        if (! isset($berkas[$k])) {
            continue;
        }
        $harap = $petaLabel[$r['status']] ?? null;
        if ($harap !== null && $berkas[$k] !== $harap) {
            $beda++;
            if ($beda <= 5) {
                echo "        beda: $k layar='{$r['status']}' vs berkas='{$berkas[$k]}'
";
            }
        }
    }
    echo '   label berbeda  : '.$beda."
";
} else {
    $uq = new ReflectionMethod(KpiExportService::class, 'universeQuery');
    $uq->setAccessible(true);
    $jml = (clone $uq->invoke(app(KpiExportService::class), $metaPerf, $periode))->count();

    echo '   universe export (tanpa menulis) : '.$jml."
";
    echo '   universe layar                  : '.count($layar)."
";
    echo '   -> '.($jml === count($layar) ? 'COCOK' : 'TIDAK COCOK')."
";
    echo "   (berkas penuh butuh --dengan-export; jalur itu MENULIS progres,
";
    echo "    jadi jalankan hanya terhadap basis data development.)
";
}

// ── 2. SUBMISSION: layar tahunan ───────────────────────────────────────────
echo "\n2) SUBMISSION MONITORING (tahun {$tahun})\n";
$sub = $panggil('getSubmissionStatus', ['tahun' => $tahun]);
if ($sub === null) {
    echo "   tidak ada penjadwalan form untuk tahun ini\n";
} else {
    $c = array_count_values(array_map(fn ($r) => $r['status'], $sub));
    ksort($c);
    echo '   universe: '.count($sub)."\n";
    foreach ($c as $st => $n) {
        printf("      %-20s %d\n", $st, $n);
    }
    $metaSub = $meta('parameter', $tahun);
    echo '   batas_kepegawaian meta : '.($metaSub['batas_kepegawaian'] ?? '(TIDAK ADA)')."\n";
}

// ── 3. Kepegawaian: siapa non-aktif yang masih lolos, dan apakah wajar ─────
echo "\n3) KEPEGAWAIAN\n";
foreach (['Performance '.$periode => $layar, 'Submission '.$tahun => ($sub ?? [])] as $label => $kum) {
    if (! $kum) {
        continue;
    }
    $k = DB::table('Karyawan')->whereIn('Kode_Karyawan', array_keys($kum))
        ->get(['Kode_Karyawan', 'Aktif', 'Tanggal_Resign'])->keyBy('Kode_Karyawan');
    $non = $k->filter(fn ($x) => $x->Aktif !== 'Y');
    $batas = str_contains($label, 'Submission')
        ? $tahun.'-01-31'
        : date('Y-m-t', strtotime($periode.'-01'));
    $melanggar = $non->filter(fn ($x) => empty($x->Tanggal_Resign)
        || substr((string) $x->Tanggal_Resign, 0, 10) < $batas);
    printf("   %-22s total=%d non-aktif=%d | batas=%s | resign SEBELUM batas yang masih lolos=%d\n",
        $label, count($kum), $non->count(), $batas, $melanggar->count());
    foreach ($melanggar->take(5) as $x) {
        echo '        '.$x->Kode_Karyawan.' resign='.substr((string) $x->Tanggal_Resign, 0, 10)."\n";
    }
}

echo "\nSelesai (read-only).\n";
