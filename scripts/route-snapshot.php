<?php

/**
 * Menghasilkan snapshot seluruh route terdaftar: method, URI, nama,
 * middleware, dan action. Dipakai sebagai pembanding sebelum/sesudah pada
 * pekerjaan yang memindahkan file atau mengubah loader route.
 *
 * Menggantikan `php artisan route:list`, yang tidak dapat dijalankan di
 * environment ini karena RouteListCommand me-resolve controller dan memicu
 * kegagalan Google Application Default Credentials lewat CloudTaskService.
 *
 * Snapshot ini sengaja memakai Route::middleware(), bukan gatherMiddleware():
 * gatherMiddleware() me-resolve controller dari container dan akan memicu
 * kegagalan ADC yang sama. Middleware grup sudah ikut ter-merge ke dalam
 * middleware route pada saat registrasi, sehingga hasilnya tetap lengkap.
 *
 * Pakai:
 *   php scripts/route-snapshot.php FILE_TUJUAN
 */
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';

$target = $argv[1] ?? null;
if ($target === null) {
    fwrite(STDERR, "Pakai: php scripts/route-snapshot.php FILE_TUJUAN\n");
    exit(2);
}

$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();

$rows = [];
foreach (app('router')->getRoutes() as $route) {
    $uses = $route->getAction('uses');
    $rows[] = implode(' | ', [
        implode(',', $route->methods()),
        $route->uri(),
        $route->getName() ?? '-',
        implode(',', (array) $route->middleware()),
        is_string($uses) ? $uses : 'closure',
    ]);
}
sort($rows);

file_put_contents($target, implode("\n", $rows)."\n");
printf("routes=%d  ditulis ke %s\n", count($rows), $target);
