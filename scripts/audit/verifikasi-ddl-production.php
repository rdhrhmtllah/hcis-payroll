<?php

/**
 * Verifikasi READ-ONLY: perubahan skema mana yang SUDAH benar-benar ada di
 * production, dibaca dari katalog production itu sendiri — bukan dari ledger.
 *
 * Kenapa perlu: `database/ddl/status.json` mencatat niat, bukan kenyataan. Satu
 * entri bisa berstatus PENDING padahal objeknya sudah lama ada (dijalankan
 * manual lewat SQL Tracker tanpa ledger diperbarui), dan sebaliknya. Menandai
 * "selesai" dari ingatan adalah cara paling cepat kehilangan jejak mana yang
 * benar-benar tertinggal — persis yang terjadi pada DDL-2026-059, yang setengah
 * masuk: satu tabel sudah nullable, satu lagi belum.
 *
 * Cara pakai:
 *   1) php scripts/audit/dump-katalog-production.php   (menulis katalog)
 *   2) php scripts/audit/verifikasi-ddl-production.php [--json]
 *
 * Sinyal yang dibaca dari forward.sql:
 *   CREATE TABLE x                 -> tabel ada?
 *   ALTER TABLE x ADD y            -> kolom ada?
 *   ALTER TABLE x ALTER COLUMN y   -> nullability cocok?
 *   CREATE [UNIQUE] INDEX n ON x   -> indeks ada?
 *
 * Perubahan tanpa sinyal terbaca (umumnya DML) dilaporkan TAK_TERVERIFIKASI —
 * bukan "selesai". Menebak di sini sama saja dengan tidak memeriksa.
 */
$root = dirname(__DIR__, 2);

$katalogPath = $root.'/storage/app/katalog-prod.json';
if (! is_file($katalogPath)) {
    fwrite(STDERR, "Katalog production belum ada. Jalankan dump-katalog-production.php lebih dulu.\n");
    exit(2);
}

$kat = json_decode(file_get_contents($katalogPath), true);
$tabel = array_flip($kat['tabel']);
$kolom = $kat['kolom'];
$indeks = $kat['indeks'];

function bersihkan(string $sql): string
{
    $sql = preg_replace('!/\*.*?\*/!s', ' ', $sql);
    $sql = preg_replace('!--[^\n]*!', ' ', $sql);

    return $sql;
}

function namaBersih(string $n): string
{
    return strtolower(trim(str_replace(['[', ']', 'dbo.', '"'], '', $n)));
}

$hasil = [];
$dirs = glob($root.'/database/ddl/*', GLOB_ONLYDIR) ?: [];

foreach ($dirs as $dir) {
    $id = basename($dir);
    $fw = $dir.'/forward.sql';
    if (! is_file($fw)) {
        continue;
    }

    $sql = bersihkan(file_get_contents($fw));
    $sinyal = [];

    if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?([\[\]\w\.]+)/i', $sql, $m)) {
        foreach ($m[1] as $t) {
            $sinyal[] = ['tipe' => 'tabel', 'tabel' => namaBersih($t)];
        }
    }
    if (preg_match_all('/ALTER\s+TABLE\s+([\[\]\w\.]+)\s+ADD\s+(?!CONSTRAINT)([\[\]\w]+)/i', $sql, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) {
            $sinyal[] = ['tipe' => 'kolom', 'tabel' => namaBersih($x[1]), 'kolom' => namaBersih($x[2])];
        }
    }
    if (preg_match_all('/ALTER\s+TABLE\s+([\[\]\w\.]+)\s+ALTER\s+COLUMN\s+([\[\]\w]+)[^;]*?\b(NOT\s+NULL|NULL)\b/i', $sql, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) {
            $sinyal[] = [
                'tipe' => 'nullability',
                'tabel' => namaBersih($x[1]),
                'kolom' => namaBersih($x[2]),
                'nullable' => preg_match('/NOT/i', $x[3]) ? 'NO' : 'YES',
            ];
        }
    }
    if (preg_match_all('/CREATE\s+(?:UNIQUE\s+)?(?:NONCLUSTERED\s+|CLUSTERED\s+)?INDEX\s+([\[\]\w]+)/i', $sql, $m)) {
        foreach ($m[1] as $i) {
            $sinyal[] = ['tipe' => 'indeks', 'nama' => namaBersih($i)];
        }
    }

    if (! $sinyal) {
        $hasil[$id] = ['vonis' => 'TAK_TERVERIFIKASI', 'ada' => 0, 'hilang' => 0, 'rincian' => []];

        continue;
    }

    $ada = 0;
    $hilang = [];
    foreach ($sinyal as $s) {
        $ok = false;
        if ($s['tipe'] === 'tabel') {
            $ok = isset($tabel[$s['tabel']]);
        } elseif ($s['tipe'] === 'kolom') {
            $ok = isset($kolom[$s['tabel']][$s['kolom']]);
        } elseif ($s['tipe'] === 'nullability') {
            $p = $kolom[$s['tabel']][$s['kolom']] ?? null;
            $ok = $p !== null && $p === $s['nullable'];
        } elseif ($s['tipe'] === 'indeks') {
            $ok = isset($indeks[$s['nama']]);
        }
        if ($ok) {
            $ada++;
        } else {
            $hilang[] = $s;
        }
    }

    $vonis = ! $hilang ? 'SUDAH_ADA' : ($ada > 0 ? 'SEBAGIAN' : 'BELUM_ADA');
    $hasil[$id] = ['vonis' => $vonis, 'ada' => $ada, 'hilang' => count($hilang), 'rincian' => array_slice($hilang, 0, 6)];
}

if (in_array('--json', $argv, true)) {
    echo json_encode($hasil, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

$ring = array_count_values(array_column($hasil, 'vonis'));
echo '== Katalog production: '.$kat['db'].' (diambil '.$kat['diambil'].") ==\n\n";
foreach (['SUDAH_ADA', 'SEBAGIAN', 'BELUM_ADA', 'TAK_TERVERIFIKASI'] as $v) {
    printf("  %-18s %d\n", $v, $ring[$v] ?? 0);
}

foreach (['SEBAGIAN', 'BELUM_ADA'] as $v) {
    echo "\n== $v ==\n";
    foreach ($hasil as $id => $h) {
        if ($h['vonis'] !== $v) {
            continue;
        }
        printf("  %-56s ada=%d hilang=%d\n", substr($id, 0, 56), $h['ada'], $h['hilang']);
        foreach ($h['rincian'] as $r) {
            echo '        - '.json_encode($r)."\n";
        }
    }
}
