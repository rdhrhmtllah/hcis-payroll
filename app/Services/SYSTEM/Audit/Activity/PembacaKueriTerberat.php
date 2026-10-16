<?php

namespace App\Services\SYSTEM\Audit\Activity;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Biaya SETIAP kueri, bukan hanya yang kebetulan sedang berjalan.
 *
 * PembacaKunciLangsung memotret keadaan pada satu detik. Kueri yang selesai
 * dalam 30 ms tetapi dijalankan 2.000 kali hampir tak pernah tertangkap
 * potret mana pun — padahal justru gabungan itulah yang menghabiskan CPU dan
 * koneksi. Angka nyata dari dev: satu SELECT atas N_HRIS_VW_All_Transaksi
 * dijalankan 1.934 kali dengan 7.470 logical reads sekali jalan; ia tidak
 * pernah muncul di potret, dan ia yang paling mahal.
 *
 * sys.dm_exec_query_stats menyimpan hitungan itu per rencana yang ter-cache,
 * jadi tak ada kueri yang lolos karena kebetulan cepat.
 *
 * GARIS DASAR. Angkanya kumulatif sejak rencananya masuk cache, jadi apa
 * adanya ia bercerita tentang seluruh umur server — bukan tentang uji yang
 * baru saja dijalankan. Karena itu ada penanda: tandai dasar sebelum menguji,
 * lalu yang dilaporkan SELISIHNYA. Tanpa itu, kueri yang sudah menumpuk
 * sebulan akan selalu menutupi kueri yang baru saja Anda picu.
 *
 * BATASNYA, supaya tak dikira lebih tahu daripada yang sebenarnya:
 *   - kueri ber-OPTION(RECOMPILE) atau yang rencananya tak di-cache tak ikut
 *   - hitungannya hilang saat rencananya tergusur dari cache atau server restart
 *   - satu baris = satu PERNYATAAN, jadi satu prosedur bisa muncul beberapa kali
 */
class PembacaKueriTerberat
{
    private const KUNCI_DASAR = 'diagnostik:kueri-dasar';

    /**
     * Nama alias tiap kolom di dalam sub-kueri peringkat.
     *
     * ORDER BY di LUAR sub-kueri hanya melihat aliasnya, bukan nama aslinya —
     * memakai nama asli di sana melempar "Invalid column name".
     */
    private const ALIAS = [
        'total_worker_time' => 'cpu_ms',
        'total_elapsed_time' => 'durasi_ms',
        'total_logical_reads' => 'baca',
        'total_logical_writes' => 'tulis',
        'execution_count' => 'eksekusi',
    ];

    /** Kolom yang boleh dipakai mengurutkan — daftar tertutup, bukan masukan bebas. */
    private const URUTAN = [
        'cpu' => 'total_worker_time',
        'durasi' => 'total_elapsed_time',
        'baca' => 'total_logical_reads',
        'tulis' => 'total_logical_writes',
        'eksekusi' => 'execution_count',
    ];

    /**
     * @return array{
     *     diambil_pada: string,
     *     urut: string,
     *     ada_garis_dasar: bool,
     *     garis_dasar_pada: ?string,
     *     baris: list<array<string,mixed>>,
     * }
     */
    public function ambil(string $urut = 'cpu', int $limit = 25): array
    {
        $kolom = self::URUTAN[$urut] ?? self::URUTAN['cpu'];
        $limit = max(5, min(100, $limit));

        $dasar = Cache::get(self::KUNCI_DASAR);
        $adaDasar = is_array($dasar) && isset($dasar['angka']);

        // Saat ada garis dasar, plafonnya dilonggarkan sebelum diperingkat
        // ulang: kueri yang baru dipicu bisa saja belum masuk N teratas
        // menurut angka kumulatif, padahal selisihnya paling besar.
        $sekarang = $this->bacaMentah($kolom, $adaDasar ? 300 : $limit);

        $baris = $adaDasar
            ? $this->selisih($sekarang, $dasar['angka'], $kolom, $limit)
            : array_slice($sekarang, 0, $limit);

        return [
            'diambil_pada' => now()->format('Y-m-d H:i:s'),
            'urut' => array_search($kolom, self::URUTAN, true) ?: 'cpu',
            'ada_garis_dasar' => $adaDasar,
            'garis_dasar_pada' => $adaDasar ? ($dasar['pada'] ?? null) : null,
            'baris' => array_values($baris),
        ];
    }

    /**
     * Menandai keadaan sekarang sebagai titik nol.
     *
     * Disimpan di cache, bukan tabel: ini alat ukur sesi uji, bukan riwayat
     * yang perlu bertahan. Menyimpannya di tabel justru menambah tulisan ke
     * basis data yang sedang diukur.
     */
    public function tandaiDasar(): array
    {
        $angka = [];
        foreach ($this->bacaMentah('total_worker_time', 2000) as $r) {
            $angka[$r['kunci']] = [
                'eksekusi' => $r['eksekusi'],
                'cpu_ms' => $r['cpu_ms'],
                'durasi_ms' => $r['durasi_ms'],
                'baca' => $r['baca'],
                'tulis' => $r['tulis'],
            ];
        }

        $pada = now()->format('Y-m-d H:i:s');
        Cache::put(self::KUNCI_DASAR, ['pada' => $pada, 'angka' => $angka], now()->addHours(6));

        return ['pada' => $pada, 'jumlah_kueri' => count($angka)];
    }

    public function hapusDasar(): void
    {
        Cache::forget(self::KUNCI_DASAR);
    }

    /**
     * @return list<array<string,mixed>>
     */
    protected function bacaMentah(string $kolom, int $limit): array
    {
        // Peringkatnya diambil LEBIH DULU di sub-kueri, baru teks SQL-nya
        // dijemput. Bentuk sebaliknya — CROSS APPLY berdampingan dengan TOP —
        // membuat SQL Server mengambil teks untuk RIBUAN baris sebelum
        // membuang hampir semuanya: terukur 8,9 detik sekali baca, yang
        // menjadikan pemantaunya beban tersendiri.
        $baris = DB::select(
            'SELECT CONVERT(varchar(64), qs.sql_handle, 2) + \':\' + CAST(qs.statement_start_offset AS varchar(12)) AS kunci,
                    qs.eksekusi,
                    qs.cpu_ms,
                    qs.durasi_ms,
                    qs.baca,
                    qs.tulis,
                    qs.durasi_maks_ms,
                    qs.terakhir_jalan,
                    ISNULL(OBJECT_NAME(st.objectid, st.dbid), \'(ad-hoc)\') AS objek,
                    CAST(SUBSTRING(st.text,
                        (qs.statement_start_offset/2) + 1,
                        CASE qs.statement_end_offset WHEN -1 THEN DATALENGTH(st.text)
                             ELSE (qs.statement_end_offset - qs.statement_start_offset)/2 + 1 END
                    ) AS varchar(500)) AS sql_text
               FROM (
                    SELECT TOP ('.$limit.')
                           sql_handle, statement_start_offset, statement_end_offset,
                           execution_count        AS eksekusi,
                           total_worker_time/1000 AS cpu_ms,
                           total_elapsed_time/1000 AS durasi_ms,
                           total_logical_reads    AS baca,
                           total_logical_writes   AS tulis,
                           max_elapsed_time/1000  AS durasi_maks_ms,
                           last_execution_time    AS terakhir_jalan
                      FROM sys.dm_exec_query_stats
                     ORDER BY '.$kolom.' DESC
               ) qs
              CROSS APPLY sys.dm_exec_sql_text(qs.sql_handle) st
              -- Kueri diagnostik ini sendiri dibuang: pemantau yang memuncaki
              -- daftarnya sendiri hanya membuat orang mengejar bayangannya.
              WHERE st.text NOT LIKE \'%dm_exec_%\'
                AND st.text NOT LIKE \'%dm_tran_locks%\'
                AND st.text NOT LIKE \'%dm_os_waiting%\'
                -- Kueri KATALOG dibuang: Management Studio dan alat sejenis
                -- membaca sys.objects/sys.columns berulang kali dan angkanya
                -- besar sekali (terukur 109.361 logical reads). Ia bukan milik
                -- aplikasi, tetapi memuncaki daftar dan menutupi kueri yang
                -- benar-benar perlu diperbaiki.
                AND st.text NOT LIKE \'%sys.objects%\'
                AND st.text NOT LIKE \'%sys.columns%\'
                AND st.text NOT LIKE \'%sys.all_objects%\'
                AND st.text NOT LIKE \'%is_ms_shipped%\'
                AND st.text NOT LIKE \'%SCHEMA_NAME(%\'
                AND st.text NOT LIKE \'%sys.database_principals%\'
                AND st.text NOT LIKE \'%sys.dm_%\'
                AND st.text NOT LIKE \'%sys.sql_modules%\'
                AND st.text NOT LIKE \'%sys.indexes%\'
              ORDER BY qs.'.self::ALIAS[$kolom].' DESC'
        );

        return array_map(static function ($r) {
            $b = (array) $r;
            $b['eksekusi'] = (int) $b['eksekusi'];
            $b['rata_ms'] = $b['eksekusi'] > 0
                ? round(((int) $b['durasi_ms']) / $b['eksekusi'], 1)
                : 0;
            $b['baca_per_eksekusi'] = $b['eksekusi'] > 0
                ? (int) round(((int) $b['baca']) / $b['eksekusi'])
                : 0;
            $b['sql_text'] = trim(preg_replace('/\s+/', ' ', (string) $b['sql_text']));

            return $b;
        }, $baris);
    }

    /**
     * Selisih terhadap garis dasar, lalu diperingkat ulang menurut selisihnya.
     *
     * Kueri yang TIDAK bergerak sejak garis dasar dibuang seluruhnya — bukan
     * ditampilkan dengan nol. Yang dicari adalah apa yang dipicu oleh uji tadi,
     * dan baris bernilai nol hanya membuatnya lebih sulit dilihat.
     *
     * @param  list<array<string,mixed>>  $sekarang
     * @param  array<string,array<string,int|float>>  $dasar
     * @return list<array<string,mixed>>
     */
    protected function selisih(array $sekarang, array $dasar, string $kolom, int $limit): array
    {
        $petaKolom = [
            'total_worker_time' => 'cpu_ms',
            'total_elapsed_time' => 'durasi_ms',
            'total_logical_reads' => 'baca',
            'total_logical_writes' => 'tulis',
            'execution_count' => 'eksekusi',
        ];
        $kunciUrut = $petaKolom[$kolom] ?? 'cpu_ms';

        $hasil = [];
        foreach ($sekarang as $r) {
            $awal = $dasar[$r['kunci']] ?? null;

            // Kueri yang BELUM ada saat garis dasar dibuat berarti baru muncul —
            // seluruh angkanya milik jendela ini.
            $r['eksekusi'] = $r['eksekusi'] - (int) ($awal['eksekusi'] ?? 0);
            $r['cpu_ms'] = (int) $r['cpu_ms'] - (int) ($awal['cpu_ms'] ?? 0);
            $r['durasi_ms'] = (int) $r['durasi_ms'] - (int) ($awal['durasi_ms'] ?? 0);
            $r['baca'] = (int) $r['baca'] - (int) ($awal['baca'] ?? 0);
            $r['tulis'] = (int) $r['tulis'] - (int) ($awal['tulis'] ?? 0);

            if ($r['eksekusi'] <= 0 && (int) $r[$kunciUrut] <= 0) {
                continue;
            }

            $r['rata_ms'] = $r['eksekusi'] > 0 ? round($r['durasi_ms'] / $r['eksekusi'], 1) : 0;
            $r['baca_per_eksekusi'] = $r['eksekusi'] > 0 ? (int) round($r['baca'] / $r['eksekusi']) : 0;
            $r['baru'] = $awal === null;

            $hasil[] = $r;
        }

        usort($hasil, static fn ($a, $b) => $b[$kunciUrut] <=> $a[$kunciUrut]);

        return array_slice($hasil, 0, $limit);
    }
}
