<?php

/**
 * Memindai setiap simbol App\ yang dirujuk lewat `use` atau `::class` di
 * app/, routes/, tests/, dan database/, lalu memastikan semuanya dapat
 * di-resolve oleh autoloader.
 *
 * Dibuat untuk menutup celah verifikasi yang meloloskan regresi SOP: ekspresi
 * `Foo::class` dievaluasi menjadi string pada waktu compile dan tidak memicu
 * autoloader, sehingga route tetap terdaftar tanpa error dan baru gagal saat
 * dispatch. Pemeriksaan syntax dan pemuatan kelas per-file tidak menangkapnya.
 *
 * Pakai:
 *   php scripts/verify-references.php [--out=FILE] [--max=N]
 *
 * --max  jumlah referensi tak-resolve yang masih ditoleransi. Exit code 1 bila
 *        jumlah sebenarnya melebihi angka ini. Default 0.
 *
 * Sejak generasi lama fitur cuti dibuang pada 2026-08-18, repo ini bersih pada
 * ambang nol. Jangan menaikkan --max untuk melewatkan temuan baru: begitu
 * ambangnya naik, gerbang berhenti bisa membedakan kerusakan lama dari
 * kerusakan baru — itu persis kondisi yang meloloskan matinya seluruh endpoint
 * SOP sebelumnya.
 */
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';

$out = null;
$max = 0;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--out=')) {
        $out = substr($arg, 6);
    } elseif (str_starts_with($arg, '--max=')) {
        $max = (int) substr($arg, 6);
    }
}

$refs = [];
foreach (['app', 'routes', 'tests', 'database'] as $dir) {
    $path = "$root/$dir";
    if (! is_dir($path)) {
        continue;
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $source = file_get_contents($full = $file->getPathname());
        $relative = str_replace("$root/", '', $full);

        if (preg_match_all('/^\s*use\s+(App\\\\[A-Za-z0-9_\\\\]+)\s*(?:as\s+\w+)?\s*;/m', $source, $matches)) {
            foreach ($matches[1] as $class) {
                $refs[$class][] = $relative;
            }
        }
        if (preg_match_all('/\\\\(App\\\\[A-Za-z0-9_\\\\]+)::class/', $source, $matches)) {
            foreach ($matches[1] as $class) {
                $refs[$class][] = $relative;
            }
        }
    }
}

$missing = [];
foreach ($refs as $class => $files) {
    if (class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class)) {
        continue;
    }
    $missing[$class] = array_values(array_unique($files));
}
ksort($missing);

$report = '';
foreach ($missing as $class => $files) {
    $report .= $class."\n";
    foreach ($files as $file) {
        $report .= '    '.$file."\n";
    }
}

if ($out !== null) {
    file_put_contents($out, $report);
} elseif ($report !== '') {
    echo $report."\n";
}

printf("referenced=%d  unresolvable=%d  allowed=%d\n", count($refs), count($missing), $max);

exit(count($missing) > $max ? 1 : 0);
