<?php

/**
 * Menyapu rute GET sebuah fitur lewat kernel HTTP, sebagai pengguna login.
 *
 *   php scripts/audit/sapu-rute.php ESS Leave 4
 *
 * Kenapa lewat kernel, bukan browser: menyisir dengan tangan itu lambat dan
 * BOCOR — yang terlewat tidak ketahuan terlewat. Menyapu dari daftar rute
 * membuat cakupannya bisa dihitung, dan itulah satu-satunya cara memastikan
 * "tidak ada yang terlewat" bukan sekadar perasaan.
 *
 * HANYA GET. Rute yang mengubah data sengaja tidak disentuh: basis data dev
 * dipakai bersama, dan menyapu POST/PUT/DELETE di sana berarti mengubah data
 * orang lain yang sedang bekerja.
 *
 * Rute berparameter dilewati bila nilainya tak bisa ditebak, dan dilaporkan
 * apa adanya sebagai "belum tersentuh" — lubang yang diakui lebih baik
 * daripada cakupan yang dilebih-lebihkan.
 */
$akar = __DIR__.'/../..';
require $akar.'/vendor/autoload.php';
$app = require $akar.'/bootstrap/app.php';

use App\Models\ESS\Employee\Models\User;
use Illuminate\Contracts\Console\Kernel as KonsolKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

$app->make(KonsolKernel::class)->bootstrap();

$modul = $argv[1] ?? 'ESS';
$fitur = $argv[2] ?? 'Leave';
$idUser = (int) ($argv[3] ?? 4);

$pengguna = User::where('Id_Users', $idUser)->first();
if (! $pengguna) {
    fwrite(STDERR, "User {$idUser} tidak ada.\n");
    exit(1);
}

$kernel = $app->make(HttpKernel::class);

$aman = [];
$dilewati = [];
foreach (Route::getRoutes() as $r) {
    $act = $r->getActionName();
    if (! str_contains($act, $modul) || ! str_contains($act, $fitur)) {
        continue;
    }
    $metode = $r->methods();
    if (! in_array('GET', $metode, true)
        || array_intersect($metode, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
        continue;
    }

    $uri = $r->uri();
    if (str_contains($uri, '{')) {
        $dilewati[] = $uri;

        continue;
    }
    $aman[] = $uri;
}

printf("%s/%s — %d rute GET tanpa parameter, %d dilewati (berparameter)\n\n",
    $modul, $fitur, count($aman), count($dilewati));

$ringkas = ['ok' => 0, 'galat' => 0, 'tolak' => 0];
foreach ($aman as $uri) {
    Auth::login($pengguna);
    $req = Request::create('/'.ltrim($uri, '/'), 'GET');
    $req->headers->set('X-Requested-With', 'XMLHttpRequest');
    $req->headers->set('Accept', 'application/json');

    $t = microtime(true);
    try {
        $res = $kernel->handle($req);
        $kode = $res->getStatusCode();
        $kernel->terminate($req, $res);
    } catch (\Throwable $e) {
        $kode = 'EX';
    }
    $ms = (microtime(true) - $t) * 1000;

    if ($kode === 'EX' || (is_int($kode) && $kode >= 500)) {
        $ringkas['galat']++;
        $tanda = '!!';
    } elseif (is_int($kode) && $kode >= 400) {
        $ringkas['tolak']++;
        $tanda = '--';
    } else {
        $ringkas['ok']++;
        $tanda = 'ok';
    }

    printf("  %s [%3s] %7.0f ms  %s\n", $tanda, $kode, $ms, $uri);
}

printf("\nringkas: %d ok · %d ditolak(4xx) · %d galat(5xx/exception)\n",
    $ringkas['ok'], $ringkas['tolak'], $ringkas['galat']);

if ($dilewati) {
    printf("\nBELUM TERSENTUH — berparameter, perlu nilai nyata (%d):\n", count($dilewati));
    foreach ($dilewati as $u) {
        echo '  '.$u."\n";
    }
}
