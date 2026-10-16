<?php

/**
 * Menyalin katalog skema basis data yang sedang dikonfigurasi ke
 * storage/app/katalog-prod.json, untuk dipakai verifikasi-ddl-production.php.
 *
 * READ ONLY dan berpenjaga: setiap pernyataan non-SELECT menghentikan proses.
 * Dipakai terhadap production, jadi penjaga itu bukan hiasan.
 *
 * Pakai: php scripts/audit/dump-katalog-production.php
 */
$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

DB::listen(function ($q) {
    if (! preg_match('/^(select|set|exec sp_)/i', ltrim($q->sql))) {
        fwrite(STDERR, 'BLOKIR non-SELECT: '.substr(ltrim($q->sql), 0, 120)."\n");
        exit(9);
    }
});

$out = [
    'db' => DB::selectOne('SELECT DB_NAME() n')->n,
    'host' => config('database.connections.'.config('database.default').'.host'),
    'diambil' => now()->toDateTimeString(),
];

$out['tabel'] = collect(DB::select("SELECT TABLE_NAME n FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE='BASE TABLE'"))
    ->pluck('n')->map('strtolower')->all();

$kolom = [];
foreach (DB::select('SELECT TABLE_NAME t, COLUMN_NAME c, IS_NULLABLE nl FROM INFORMATION_SCHEMA.COLUMNS') as $r) {
    $kolom[strtolower($r->t)][strtolower($r->c)] = $r->nl;
}
$out['kolom'] = $kolom;

$indeks = [];
foreach (DB::select('SELECT t.name t, i.name i FROM sys.indexes i JOIN sys.tables t ON t.object_id = i.object_id WHERE i.name IS NOT NULL') as $r) {
    $indeks[strtolower($r->i)] = strtolower($r->t);
}
$out['indeks'] = $indeks;

$out['constraint'] = collect(DB::select("SELECT name n FROM sys.objects WHERE type IN ('D','C','F','UQ','PK')"))
    ->pluck('n')->map('strtolower')->all();
$out['prosedur'] = collect(DB::select("SELECT name n FROM sys.objects WHERE type IN ('P','FN','IF','TF','V')"))
    ->pluck('n')->map('strtolower')->all();

$tujuan = $root.'/storage/app/katalog-prod.json';
file_put_contents($tujuan, json_encode($out));

echo 'DB   : '.$out['db'].' @ '.$out['host']."\n";
echo 'Objek: '.count($out['tabel']).' tabel, '.count($out['indeks']).' indeks, '
    .count($out['constraint']).' constraint, '.count($out['prosedur'])." prosedur/view\n";
echo 'Tulis: '.$tujuan."\n";
