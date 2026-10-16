<?php

/**
 * Memverifikasi setiap jalur impor relatif dan beralias di resources/js
 * menunjuk berkas yang benar-benar ada.
 *
 * Dipakai sebagai pengganti murah untuk `npm run build` saat memindahkan
 * berkas frontend. Build penuh memerlukan beberapa menit dan pada repo ini
 * kehabisan heap Node bawaan (2 GB), sementara pemeriksaan ini menyelesaikan
 * pekerjaan yang sama untuk kelas kesalahan yang paling mungkin muncul akibat
 * pemindahan berkas: jalur impor yang tidak lagi menunjuk ke mana-mana.
 *
 * Yang TIDAK diperiksa: kebenaran tipe, nama ekspor, dan galat kompilasi
 * template. Untuk itu build tetap diperlukan.
 *
 * Pakai:
 *   php scripts/verify-frontend-imports.php [--max=N]
 */
$root = dirname(__DIR__);
$js = $root.'/resources/js';

$max = 0;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--max=')) {
        $max = (int) substr($arg, 6);
    }
}

// Alias dibaca dari vite.config.js agar tidak menduplikasi konfigurasi.
$aliases = [];
$vite = file_get_contents($root.'/vite.config.js');
if (preg_match_all('~[\'"]([@\w/.-]+)[\'"]\s*:\s*path\.resolve\(__dirname,\s*[\'"]([^\'"]+)[\'"]\s*\)~', $vite, $m, PREG_SET_ORDER)) {
    foreach ($m as $hit) {
        $aliases[$hit[1]] = realpath($root.'/'.ltrim($hit[2], './')) ?: $root.'/'.ltrim($hit[2], './');
    }
}

// getPathname() memakai pemisah asli OS. Di Windows itu '\', sementara $js
// memakai '/', sehingga pemotongan prefiks gagal, jalurnya jadi ganda, dan
// SETIAP berkas dilewati diam-diam — pemeriksa melaporkan "0 putus" karena tak
// memeriksa apa pun. Normalkan dulu; lihat penjaga di bawah yang menolak nol.
$normal = static fn (string $path): string => str_replace('\\', '/', $path);
$jsNormal = $normal($js);

$files = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($js)) as $f) {
    if ($f->isFile() && in_array($f->getExtension(), ['vue', 'js'], true)) {
        $files[] = str_replace($jsNormal.'/', '', $normal($f->getPathname()));
    }
}
sort($files);

$broken = [];
$checked = 0;
foreach ($files as $rel) {
    $src = file_get_contents($js.'/'.$rel);

    // Buang komentar lebih dulu. Contoh pemakaian di dalam komentar dokumentasi
    // ("Pakai: import pageMeta from ...") bukan impor sungguhan dan akan
    // menghasilkan temuan palsu bila ikut dipindai.
    $src = preg_replace('~/\*.*?\*/~s', '', $src);
    $src = preg_replace('~^\s*//.*$~m', '', $src);
    $src = preg_replace('~<!--.*?-->~s', '', $src);

    if (! preg_match_all('~(?:from|import)\s*\(?\s*[\'"]([^\'"]+)[\'"]~', $src, $m)) {
        continue;
    }
    foreach ($m[1] as $spec) {
        $base = null;
        if ($spec !== '' && $spec[0] === '.') {
            $base = dirname($js.'/'.$rel).'/'.$spec;
        } else {
            foreach ($aliases as $prefix => $target) {
                if ($spec === $prefix || str_starts_with($spec, $prefix.'/')) {
                    $base = $target.substr($spec, strlen($prefix));
                    break;
                }
            }
        }
        if ($base === null) {
            continue; // paket node_modules, bukan urusan pemeriksaan ini
        }
        $checked++;
        $found = false;
        foreach (['', '.vue', '.js', '.mjs', '.json', '.css', '/index.vue', '/index.js'] as $suffix) {
            if (is_file($base.$suffix) || (is_dir($base) && $suffix === '')) {
                // direktori saja tidak cukup; harus ada berkas
                if (is_file($base.$suffix)) {
                    $found = true;
                    break;
                }
            }
        }
        if (! $found) {
            $broken[] = [$rel, $spec];
        }
    }
}

foreach ($broken as [$rel, $spec]) {
    printf("  %s\n      -> %s\n", $rel, $spec);
}
printf("berkas=%d  jalur diperiksa=%d  putus=%d  ditoleransi=%d\n",
    count($files), $checked, count($broken), $max);

// Penjaga kerja-nyata. Pemeriksaan yang tidak menyentuh apa pun menghasilkan
// angka SEMPURNA — nol putus — dan itulah bentuk kegagalan paling berbahaya
// bagi alat verifikasi. Ambangnya sengaja longgar: yang dijaga adalah
// "benar-benar bekerja", bukan angka tertentu.
if (count($files) < 100 || $checked < 100) {
    fwrite(STDERR, sprintf(
        'GAGAL: pemeriksa nyaris tak bekerja (berkas=%d, jalur=%d). '.
        "Nol putus di sini BUKAN kabar baik — curigai pemotongan prefiks jalur.\n",
        count($files),
        $checked,
    ));
    exit(2);
}

exit(count($broken) > $max ? 1 : 0);
