<?php

/**
 * Memverifikasi setiap nama halaman Inertia yang dirender dari PHP benar-benar
 * memiliki berkas komponen di resources/js/Pages.
 *
 * Nama halaman adalah string biasa. Tidak ada autoloader, linter, maupun
 * compiler yang memeriksanya: `Inertia::render('Foo/Bar')` yang salah tetap
 * lolos seluruh pemeriksaan PHP dan baru gagal di browser sebagai layar putih.
 * Resolver di resources/js/app.js memetakan nama itu langsung menjadi
 * `./Pages/<nama>.vue`, sehingga pengecekannya dapat dilakukan secara statis.
 *
 * Pakai:
 *   php scripts/verify-inertia-pages.php [--out=FILE] [--max=N] [--orphans]
 *
 * --max      jumlah rujukan tak-tertaut yang masih ditoleransi (default 0)
 * --orphans  tampilkan juga komponen halaman yang tidak pernah dirender
 */
$root = dirname(__DIR__);
$pagesDir = $root.'/resources/js/Pages';

$out = null;
$max = 0;
$showOrphans = in_array('--orphans', $argv, true);
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--out=')) {
        $out = substr($arg, 6);
    } elseif (str_starts_with($arg, '--max=')) {
        $max = (int) substr($arg, 6);
    }
}

// Kumpulkan komponen halaman yang tersedia.
$available = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pagesDir)) as $f) {
    if (! $f->isFile() || $f->getExtension() !== 'vue') {
        continue;
    }
    $rel = substr($f->getPathname(), strlen($pagesDir) + 1, -4);
    $available[str_replace(DIRECTORY_SEPARATOR, '/', $rel)] = true;
}

// Kumpulkan nama halaman yang dirender dari PHP.
$refs = [];
foreach (['app', 'routes'] as $dir) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir)) as $f) {
        if (! $f->isFile() || $f->getExtension() !== 'php') {
            continue;
        }
        $src = file_get_contents($p = $f->getPathname());
        $rel = str_replace($root.'/', '', $p);

        // Inertia::render('X'), inertia('X'), ->render('X') — kutip tunggal maupun ganda.
        $pattern = '/(?:Inertia\s*::\s*render|(?<![\w>])inertia|->\s*render)\s*\(\s*'
                 .'([\'"])([A-Za-z0-9_\-\/. ]+)\1/';
        if (preg_match_all($pattern, $src, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $refs[$hit[2]][] = $rel;
            }
        }
    }
}

$missing = [];
foreach ($refs as $name => $files) {
    if (! isset($available[$name])) {
        $missing[$name] = array_values(array_unique($files));
    }
}
ksort($missing);

$report = '';
foreach ($missing as $name => $files) {
    $report .= $name."\n";
    foreach ($files as $file) {
        $report .= '    '.$file."\n";
    }
}

if ($out !== null) {
    file_put_contents($out, $report);
} elseif ($report !== '') {
    echo $report."\n";
}

printf("halaman tersedia=%d  dirender=%d  tidak tertaut=%d  ditoleransi=%d\n",
    count($available), count($refs), count($missing), $max);

if ($showOrphans) {
    $orphans = array_diff_key($available, $refs);
    ksort($orphans);
    echo "\nkomponen halaman yang tidak pernah dirender dari PHP: ".count($orphans)."\n";
    foreach (array_keys($orphans) as $o) {
        echo "    $o\n";
    }
}

exit(count($missing) > $max ? 1 : 0);
