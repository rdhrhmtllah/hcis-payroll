<?php

namespace App\Console\Commands\SYSTEM\Application\Database;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Potret skema + indeks, untuk dibandingkan antar lingkungan.
 *
 * ── Kenapa perintah ini ada ───────────────────────────────────────────────
 *
 * Setiap angka performa yang bisa saya ukur berasal dari pengembangan, dan
 * pengembangan berbohong tentang dua hal sekaligus: jumlah barisnya jauh
 * lebih kecil, dan indeksnya belum tentu sama dengan produksi. Sebuah kolom
 * yang di sini terlihat berindeks bisa saja tidak berindeks di sana — dan
 * justru di sanalah tabelnya besar.
 *
 * Perintah ini menghasilkan satu berkas JSON yang bisa dibuat di KEDUA
 * lingkungan lalu dibandingkan (`db:inventaris-skema --banding=a.json,b.json`).
 * Yang dilaporkan: tabel yang hanya ada di salah satu, kolom yang hanya ada
 * di salah satu, dan indeks yang hanya ada di salah satu.
 *
 * ── Keamanan ──────────────────────────────────────────────────────────────
 *
 * HANYA MEMBACA. Yang dibaca cuma katalog sistem (sys.tables, sys.columns,
 * sys.indexes, sys.partitions) dan — bila diminta — sys.dm_db_index_usage_stats.
 * Tidak ada satu pun baris data bisnis yang disentuh, tidak ada DDL, tidak ada
 * tulis. Aman dijalankan pada koneksi read-only di produksi.
 *
 * Satu peringatan tentang statistik pemakaian: isinya di-reset setiap kali
 * layanan SQL Server dimulai ulang, jadi angkanya hanya bermakna bersama
 * `sqlserver_start_time` yang ikut dicatat di potret.
 */
class InventarisSkema extends Command
{
    protected $signature = 'db:inventaris-skema
        {--keluaran= : Tulis potret ke berkas JSON ini}
        {--pemakaian : Sertakan statistik pemakaian indeks (seeks/scans/updates)}
        {--koneksi= : Nama koneksi database (default: koneksi aplikasi)}
        {--banding= : Dua berkas potret dipisah koma, bandingkan lalu keluar}
        {--nganggur : Laporkan indeks yang nol dibaca tetapi dibayar tiap tulis}';

    protected $description = 'Potret skema + indeks (hanya baca) untuk dibandingkan antar lingkungan';

    public function handle(): int
    {
        if (($pasangan = (string) $this->option('banding')) !== '') {
            return $this->banding($pasangan);
        }

        if ($this->option('nganggur')) {
            return $this->nganggur();
        }

        $potret = $this->potret();

        $berkas = (string) $this->option('keluaran');
        if ($berkas !== '') {
            file_put_contents($berkas, json_encode($potret, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
            $this->info("Potret ditulis: {$berkas}");
        }

        $this->line(sprintf(
            '%s  |  %d tabel, %d kolom, %d indeks  |  SQL Server hidup sejak %s',
            $potret['basis_data'], count($potret['tabel']),
            array_sum(array_map(fn ($t) => count($t['kolom']), $potret['tabel'])),
            array_sum(array_map(fn ($t) => count($t['indeks']), $potret['tabel'])),
            $potret['sqlserver_start_time'],
        ));

        $heap = array_filter($potret['tabel'], fn ($t) => $t['heap']);
        $this->line(sprintf('  heap (tanpa indeks klaster): %d', count($heap)));

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function potret(): array
    {
        $db = DB::connection($this->option('koneksi') ?: null);

        $tabel = [];
        foreach ($db->select('SELECT s.name AS skema, t.name AS nama, t.object_id FROM sys.tables t JOIN sys.schemas s ON s.schema_id = t.schema_id') as $t) {
            $tabel[$t->nama] = [
                'skema' => $t->skema,
                'baris' => 0,
                'heap' => true,
                'kolom' => [],
                'indeks' => [],
            ];
        }

        foreach ($db->select('
            SELECT t.name AS tabel, SUM(p.rows) AS baris
            FROM sys.tables t
            JOIN sys.partitions p ON p.object_id = t.object_id AND p.index_id IN (0, 1)
            GROUP BY t.name') as $r) {
            if (isset($tabel[$r->tabel])) {
                $tabel[$r->tabel]['baris'] = (int) $r->baris;
            }
        }

        foreach ($db->select('
            SELECT t.name AS tabel, c.name AS kolom, ty.name AS tipe,
                   c.max_length, c.precision, c.scale, c.is_nullable, c.is_identity
            FROM sys.columns c
            JOIN sys.tables t ON t.object_id = c.object_id
            JOIN sys.types ty ON ty.user_type_id = c.user_type_id
            ORDER BY t.name, c.column_id') as $r) {
            if (! isset($tabel[$r->tabel])) {
                continue;
            }
            $tabel[$r->tabel]['kolom'][$r->kolom] = sprintf(
                '%s(%d,%d,%d)%s%s',
                $r->tipe, $r->max_length, $r->precision, $r->scale,
                $r->is_nullable ? ' NULL' : ' NOT NULL',
                $r->is_identity ? ' IDENTITY' : '',
            );
        }

        $pemakaian = [];
        if ($this->option('pemakaian')) {
            foreach ($db->select('
                SELECT t.name AS tabel, i.name AS indeks,
                       us.user_seeks, us.user_scans, us.user_lookups, us.user_updates
                FROM sys.dm_db_index_usage_stats us
                JOIN sys.tables t ON t.object_id = us.object_id
                JOIN sys.indexes i ON i.object_id = us.object_id AND i.index_id = us.index_id
                WHERE us.database_id = DB_ID()') as $r) {
                $pemakaian[$r->tabel.'|'.($r->indeks ?? '(heap)')] = [
                    'seeks' => (int) $r->user_seeks,
                    'scans' => (int) $r->user_scans,
                    'lookups' => (int) $r->user_lookups,
                    'updates' => (int) $r->user_updates,
                ];
            }
        }

        /*
         * Kunci dan kolom INCLUDE diambil terpisah lalu digabung berurutan.
         * Urutan kunci menentukan kegunaan indeks — indeks (A, B) tidak
         * menolong kueri yang hanya menyaring B — jadi urutannya tidak boleh
         * hilang saat dibandingkan.
         */
        $kunci = [];
        foreach ($db->select('
            SELECT t.name AS tabel, i.name AS indeks, c.name AS kolom,
                   ic.key_ordinal, ic.is_included_column, ic.is_descending_key
            FROM sys.index_columns ic
            JOIN sys.indexes i ON i.object_id = ic.object_id AND i.index_id = ic.index_id
            JOIN sys.tables t ON t.object_id = i.object_id
            JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
            ORDER BY t.name, i.name, ic.is_included_column, ic.key_ordinal, c.name') as $r) {
            $k = $r->tabel.'|'.($r->indeks ?? '(heap)');
            $kunci[$k][$r->is_included_column ? 'ikut' : 'kunci'][] =
                $r->kolom.($r->is_descending_key ? ' DESC' : '');
        }

        foreach ($db->select("
            SELECT t.name AS tabel, i.name AS indeks, i.type_desc AS jenis,
                   i.is_unique, i.is_primary_key, i.is_unique_constraint,
                   ISNULL(i.filter_definition, '') AS saringan
            FROM sys.indexes i
            JOIN sys.tables t ON t.object_id = i.object_id
            WHERE i.type_desc <> 'HEAP'
            ORDER BY t.name, i.name") as $r) {
            if (! isset($tabel[$r->tabel])) {
                continue;
            }
            $k = $r->tabel.'|'.$r->indeks;
            if ($r->jenis === 'CLUSTERED') {
                $tabel[$r->tabel]['heap'] = false;
            }
            $tabel[$r->tabel]['indeks'][$r->indeks] = array_filter([
                'jenis' => $r->jenis,
                'unik' => (bool) $r->is_unique,
                'pk' => (bool) $r->is_primary_key,
                'kunci' => implode(', ', $kunci[$k]['kunci'] ?? []),
                'ikut' => implode(', ', $kunci[$k]['ikut'] ?? []),
                'saringan' => $r->saringan,
                'pemakaian' => $pemakaian[$k] ?? null,
            ], fn ($v) => $v !== '' && $v !== null && $v !== false);
        }

        ksort($tabel);

        return [
            'basis_data' => $db->getDatabaseName(),
            'diambil_pada' => now()->toDateTimeString(),
            'sqlserver_start_time' => (string) ($db->select('SELECT sqlserver_start_time s FROM sys.dm_os_sys_info')[0]->s ?? ''),
            'tabel' => $tabel,
        ];
    }

    /**
     * Indeks yang NOL dibaca tetapi tetap dipelihara setiap kali barisnya ditulis.
     *
     * Sebuah indeks non-unik hanya punya satu alasan hidup: mempercepat baca.
     * Kalau sepanjang jendela statistik ia tidak pernah di-seek, di-scan, maupun
     * di-lookup, seluruh biaya pemeliharaannya murni kerugian.
     *
     * TIGA HAL YANG DIKECUALIKAN, DAN KENAPA
     *
     *   - indeks UNIK dan primary key. Nol pembacaan tidak membuatnya sia-sia:
     *     tugasnya menolak baris kembar, bukan mempercepat. Membuangnya karena
     *     "tak terbaca" adalah cara paling rapi untuk kehilangan keutuhan data.
     *   - indeks berfilter unik yang sama alasannya.
     *   - tidak ada yang dikecualikan berdasarkan nama tabel di sini; penyaringan
     *     fitur (mis. yang sedang digarap) diserahkan kepada pembaca laporan.
     *
     * DAN SATU PERINGATAN YANG TIDAK BOLEH DIHILANGKAN
     *
     * Statistik ini milik SATU lingkungan dan direset setiap layanan SQL Server
     * dimulai ulang. Indeks yang menganggur di pengembangan bisa jadi justru
     * tulang punggung di produksi — jalur yang tidak pernah dijalankan di sini
     * dijalankan ribuan kali di sana. Laporan ini DAFTAR KANDIDAT untuk
     * dibandingkan, bukan daftar hapus. Jalankan di kedua lingkungan lebih dulu.
     */
    private function nganggur(): int
    {
        $db = DB::connection($this->option('koneksi') ?: null);

        $mulai = (string) ($db->select('SELECT sqlserver_start_time s FROM sys.dm_os_sys_info')[0]->s ?? '');
        $this->line("Jendela statistik: sejak {$mulai} (direset tiap SQL Server dimulai ulang)");
        $this->line('Basis data       : '.$db->getDatabaseName());
        $this->newLine();

        $baris = $db->select("
            SELECT t.name AS tabel, i.name AS indeks,
                   ISNULL(us.user_updates, 0) AS tulis,
                   (SELECT SUM(p.rows) FROM sys.partitions p
                     WHERE p.object_id = t.object_id AND p.index_id IN (0, 1)) AS baris,
                   ISNULL((SELECT SUM(ps.used_page_count) * 8 FROM sys.dm_db_partition_stats ps
                     WHERE ps.object_id = i.object_id AND ps.index_id = i.index_id), 0) AS kb
            FROM sys.indexes i
            JOIN sys.tables t ON t.object_id = i.object_id
            LEFT JOIN sys.dm_db_index_usage_stats us
                   ON us.object_id = i.object_id AND us.index_id = i.index_id
                  AND us.database_id = DB_ID()
            WHERE i.type_desc = 'NONCLUSTERED'
              AND i.is_primary_key = 0
              AND i.is_unique_constraint = 0
              AND i.is_unique = 0
              AND ISNULL(us.user_seeks, 0) = 0
              AND ISNULL(us.user_scans, 0) = 0
              AND ISNULL(us.user_lookups, 0) = 0
            ORDER BY ISNULL(us.user_updates, 0) DESC, t.name, i.name");

        $this->line(sprintf('%-40s %-38s %10s %10s %10s',
            'TABEL', 'INDEKS', 'TULIS', 'BARIS', 'UKURAN KB'));

        foreach ($baris as $r) {
            $this->line(sprintf('%-40s %-38s %10s %10s %10s',
                substr($r->tabel, 0, 40), substr((string) $r->indeks, 0, 38),
                number_format((int) $r->tulis), number_format((int) $r->baris),
                number_format((int) $r->kb)));
        }

        $totalTulis = array_sum(array_map(fn ($r) => (int) $r->tulis, $baris));
        $this->newLine();
        $this->line(sprintf('%d indeks nol-dibaca; %s pemeliharaan tulis tanpa satu pun pembacaan.',
            count($baris), number_format($totalTulis)));
        $this->line('Bandingkan dengan produksi sebelum membuang satu pun: indeks yang');
        $this->line('menganggur di sini bisa jadi tulang punggung di sana.');

        return self::SUCCESS;
    }

    private function banding(string $pasangan): int
    {
        $berkas = array_map('trim', explode(',', $pasangan));
        if (count($berkas) !== 2) {
            $this->error('--banding butuh dua berkas dipisah koma.');

            return self::FAILURE;
        }
        foreach ($berkas as $b) {
            if (! is_file($b)) {
                $this->error("Berkas potret tidak ada: {$b}");

                return self::FAILURE;
            }
        }

        [$a, $b] = array_map(fn ($p) => json_decode((string) file_get_contents($p), true), $berkas);
        $namaA = $a['basis_data'] ?? $berkas[0];
        $namaB = $b['basis_data'] ?? $berkas[1];

        $this->line("A = {$namaA} ({$a['diambil_pada']})");
        $this->line("B = {$namaB} ({$b['diambil_pada']})");
        $this->newLine();

        $tabelA = array_keys($a['tabel']);
        $tabelB = array_keys($b['tabel']);

        $this->bagian('tabel hanya di A', array_diff($tabelA, $tabelB));
        $this->bagian('tabel hanya di B', array_diff($tabelB, $tabelA));

        $kolomHilang = [];
        $indeksHilangA = [];
        $indeksHilangB = [];
        $indeksBeda = [];

        foreach (array_intersect($tabelA, $tabelB) as $t) {
            $ka = $a['tabel'][$t]['kolom'] ?? [];
            $kb = $b['tabel'][$t]['kolom'] ?? [];
            foreach (array_diff(array_keys($ka), array_keys($kb)) as $c) {
                $kolomHilang[] = "{$t}.{$c}  (hanya di A)";
            }
            foreach (array_diff(array_keys($kb), array_keys($ka)) as $c) {
                $kolomHilang[] = "{$t}.{$c}  (hanya di B)";
            }

            $ia = $a['tabel'][$t]['indeks'] ?? [];
            $ib = $b['tabel'][$t]['indeks'] ?? [];
            foreach (array_diff(array_keys($ia), array_keys($ib)) as $i) {
                $indeksHilangB[] = "{$t}.{$i}  kunci=".($ia[$i]['kunci'] ?? '-');
            }
            foreach (array_diff(array_keys($ib), array_keys($ia)) as $i) {
                $indeksHilangA[] = "{$t}.{$i}  kunci=".($ib[$i]['kunci'] ?? '-');
            }
            /*
             * Nama sama tetapi isinya beda adalah kasus paling licik: skrip
             * penerapan mengira indeksnya sudah ada dan melewatinya.
             */
            foreach (array_intersect(array_keys($ia), array_keys($ib)) as $i) {
                foreach (['kunci', 'ikut', 'saringan', 'unik'] as $sifat) {
                    if (($ia[$i][$sifat] ?? null) !== ($ib[$i][$sifat] ?? null)) {
                        $indeksBeda[] = "{$t}.{$i}  {$sifat}: A=[".
                            (string) ($ia[$i][$sifat] ?? '-').'] B=['.(string) ($ib[$i][$sifat] ?? '-').']';
                        break;
                    }
                }
            }
        }

        $this->bagian('kolom yang hanya ada di salah satu', $kolomHilang);
        $this->bagian("indeks TIDAK ADA di A ({$namaA})", $indeksHilangA);
        $this->bagian("indeks TIDAK ADA di B ({$namaB})", $indeksHilangB);
        $this->bagian('indeks bernama sama tetapi BERBEDA isinya', $indeksBeda);

        return self::SUCCESS;
    }

    /** @param  iterable<string>  $baris */
    private function bagian(string $judul, iterable $baris): void
    {
        $baris = array_values(is_array($baris) ? $baris : iterator_to_array($baris));
        $this->line(sprintf('=== %s: %d ===', $judul, count($baris)));
        foreach (array_slice($baris, 0, 60) as $b) {
            $this->line('  '.$b);
        }
        if (count($baris) > 60) {
            $this->line(sprintf('  ... %d lagi', count($baris) - 60));
        }
        $this->newLine();
    }
}
