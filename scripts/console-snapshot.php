<?php

/**
 * Membangun Console Kernel dan mendaftar seluruh perintah Artisan yang
 * teregistrasi. Dipakai sebagai pembanding sebelum/sesudah pemindahan file.
 *
 * Mem-boot kernel juga me-resolve dependency setiap command dari container,
 * sehingga skrip ini menangkap kelas kesalahan yang tidak terlihat oleh
 * verify-references.php: referensi tak-terkualifikasi ke bekas tetangga
 * sesama namespace. Ketika satu namespace dipecah ke beberapa modul, acuan
 * polos seperti `NotificationService` berhenti resolve, padahal tidak ada
 * pernyataan `use` maupun `::class` yang berubah.
 *
 * Pakai:
 *   php scripts/console-snapshot.php FILE_TUJUAN
 */
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';

$target = $argv[1] ?? null;
if ($target === null) {
    fwrite(STDERR, "Pakai: php scripts/console-snapshot.php FILE_TUJUAN\n");
    exit(2);
}

$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$names = array_keys(Illuminate\Support\Facades\Artisan::all());
sort($names);

file_put_contents($target, implode("\n", $names)."\n");
printf("commands=%d  ditulis ke %s\n", count($names), $target);
