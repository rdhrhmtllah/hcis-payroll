<?php

namespace App\Services\SYSTEM\Audit\Activity;

use Illuminate\Support\Facades\DB;

/**
 * Membaca keadaan kunci & permintaan aktif SQL Server APA ADANYA, saat itu juga.
 *
 * Bedanya dengan db:health-snapshot: perintah itu hanya menulis bila ada
 * blocking ATAU permintaan aktif >= 20. Dalam uji terkendali berisi dua orang,
 * ambang itu tidak pernah tersentuh — jadi ia diam persis pada keadaan yang
 * ingin kita ukur. Pembaca ini tanpa ambang: ia melaporkan apa adanya, termasuk
 * "sedang sepi", karena sepi pun jawaban.
 *
 * ATRIBUSI KE CONTROLLER. Baris DMV sendirian hanya menyebut session_id — angka
 * yang tak berarti apa-apa bagi orang yang sedang mencari fitur mana yang
 * mengunci. Kaitannya dibangun lewat CONTEXT_INFO: middleware RequestDiagnostics
 * (Layer 2, 'tag_session') menuliskan Trace_Id ke sesi, dan Trace_Id itu ada di
 * N_HRIS_Request_Diagnostics bersama Path & Method. Tanpa Layer 2 menyala,
 * kolom 'jalur' akan kosong dan pembaca ini mengatakannya terang-terangan
 * alih-alih menyajikan angka telanjang.
 *
 * Setiap pembacaan dibatasi plafon baris supaya satu sampel tidak pernah
 * membengkak menjadi beban tersendiri.
 */
class PembacaKunciLangsung
{
    public function __construct(
        protected GerbangDiagnostikRealtime $gerbang,
    ) {}

    /**
     * Satu sampel keadaan basis data.
     *
     * @return array{
     *     diambil_pada: string,
     *     atribusi_aktif: bool,
     *     permintaan: list<array<string,mixed>>,
     *     rantai_blokir: list<array<string,mixed>>,
     *     lock_per_objek: list<array<string,mixed>>,
     *     koneksi: array<string,mixed>,
     * }
     */
    public function sampel(): array
    {
        $maks = max(10, (int) config('request-diagnostics.realtime.maks_baris', 200));

        $permintaan = $this->permintaanAktif($maks);

        // Tanda sesi berbentuk "trace|METHOD /jalur" (lihat
        // RequestDiagnostics::tagSession). Rutenya dibaca dari tandanya sendiri
        // supaya permintaan yang MASIH BERJALAN pun terkenali — barisnya di
        // N_HRIS_Request_Diagnostics baru lahir setelah respons terkirim.
        foreach ($permintaan as &$p) {
            [$jejak, $rute] = $this->uraikanTanda((string) ($p['context_info'] ?? ''));
            $p['trace_id'] = $jejak;
            $p['jalur'] = $rute;
        }
        unset($p);

        // Tanda berbentuk lama (trace saja, tanpa rute) masih mungkin ada pada
        // sesi yang bertahan sejak sebelum perubahan. Untuk itu tabelnya tetap
        // dijadikan cadangan — sekali kueri untuk semuanya, bukan per baris.
        $tanpaJalur = array_values(array_filter(
            $permintaan,
            static fn ($p) => $p['jalur'] === null && $p['trace_id'] !== null,
        ));
        if ($tanpaJalur) {
            $peta = $this->petaTraceKeJalur($tanpaJalur);
            foreach ($permintaan as &$p) {
                if ($p['jalur'] === null && isset($peta[$p['trace_id']])) {
                    $p['jalur'] = $peta[$p['trace_id']];
                }
            }
            unset($p);
        }

        // Sesi yang MEMEGANG transaksi tanpa menjalankan apa pun tidak muncul di
        // dm_exec_requests, jadi ia tak punya sql_text — padahal ia justru akar
        // yang paling sering. Kalimat terakhirnya diambil dari
        // dm_exec_connections.most_recent_sql_handle, dan itulah yang mengubah
        // "sesi #59" menjadi kode yang bisa dibuka orangnya.
        $menganggur = $this->transaksiMenganggur();

        return [
            'diambil_pada' => now()->format('Y-m-d H:i:s'),
            'atribusi_aktif' => (bool) config('request-diagnostics.tag_session', false),
            'permintaan' => $permintaan,
            'rantai_blokir' => $this->rantaiBlokir($permintaan, $menganggur),
            'transaksi_menganggur' => $menganggur,
            'lock_menunggu' => $this->lockMenunggu($maks),
            'lock_per_objek' => $this->lockPerObjek($maks),
            'koneksi' => $this->tekananKoneksi(),
        ];
    }

    /**
     * Permintaan yang SEDANG berjalan, beserta siapa yang menahannya.
     *
     * @return list<array<string,mixed>>
     */
    protected function permintaanAktif(int $maks): array
    {
        $baris = DB::select(
            'SELECT TOP ('.$maks.')
                    r.session_id            AS session_id,
                    r.status                AS status,
                    ISNULL(r.wait_type, \'\') AS wait_type,
                    r.wait_time             AS wait_ms,
                    r.total_elapsed_time    AS elapsed_ms,
                    r.blocking_session_id   AS blocking_session_id,
                    r.cpu_time              AS cpu_ms,
                    r.logical_reads         AS logical_reads,
                    s.login_name            AS login_name,
                    s.host_name             AS host_name,
                    s.program_name          AS program_name,
                    LTRIM(RTRIM(REPLACE(CONVERT(varchar(128), s.context_info), CHAR(0), \'\'))) AS context_info,
                    CAST(SUBSTRING(st.text, 1, 600) AS varchar(600)) AS sql_text
               FROM sys.dm_exec_requests r
               JOIN sys.dm_exec_sessions s ON r.session_id = s.session_id
               OUTER APPLY sys.dm_exec_sql_text(r.sql_handle) st
              WHERE s.is_user_process = 1
                AND r.session_id <> @@SPID
              ORDER BY r.wait_time DESC, r.total_elapsed_time DESC'
        );

        return array_map(fn ($r) => (array) $r, $baris);
    }

    /**
     * Rantai penahan: siapa menahan siapa, dan siapa AKAR-nya.
     *
     * Akarnya yang penting. Dalam rantai A←B←C, yang pantas diperbaiki C —
     * melihat A saja membuat orang memperbaiki korban, bukan penyebabnya.
     *
     * @param  list<array<string,mixed>>  $permintaan
     * @param  list<array<string,mixed>>  $menganggur
     * @return list<array<string,mixed>>
     */
    protected function rantaiBlokir(array $permintaan, array $menganggur = []): array
    {
        $penahan = [];
        foreach ($permintaan as $p) {
            if ((int) ($p['blocking_session_id'] ?? 0) !== 0) {
                $penahan[(int) $p['session_id']] = (int) $p['blocking_session_id'];
            }
        }

        if (! $penahan) {
            return [];
        }

        $indeks = [];
        foreach ($permintaan as $p) {
            $indeks[(int) $p['session_id']] = $p;
        }

        // Akar yang menganggur tidak ada di $indeks; keterangannya diambil dari
        // daftar transaksi menganggur supaya barisnya tetap bisa menyebut jalur
        // dan kalimat SQL terakhirnya.
        $indeksMenganggur = [];
        foreach ($menganggur as $m) {
            $indeksMenganggur[(int) $m['session_id']] = $m;
        }

        $hasil = [];
        foreach ($penahan as $korban => $langsung) {
            // Telusuri ke hulu sampai menemukan sesi yang tidak ditahan siapa pun.
            // Batas langkah mencegah putaran tak berujung bila DMV menyajikan
            // siklus (bisa terjadi saat deadlock sedang diurai).
            $akar = $langsung;
            $lewat = [$korban => true];
            $langkah = 0;
            while (isset($penahan[$akar]) && ! isset($lewat[$akar]) && $langkah < 20) {
                $lewat[$akar] = true;
                $akar = $penahan[$akar];
                $langkah++;
            }

            $hasil[] = [
                'session_korban' => $korban,
                'jalur_korban' => $indeks[$korban]['jalur'] ?? null,
                'wait_type' => $indeks[$korban]['wait_type'] ?? '',
                'wait_ms' => $indeks[$korban]['wait_ms'] ?? 0,
                'session_penahan_langsung' => $langsung,
                'session_akar' => $akar,
                'jalur_akar' => $indeks[$akar]['jalur'] ?? ($indeksMenganggur[$akar]['jalur'] ?? null),
                // Akar yang berjalan punya sql_text; yang menganggur punya
                // kalimat TERAKHIRNYA. Keduanya sama-sama menunjuk ke kode.
                'sql_akar' => $indeks[$akar]['sql_text'] ?? ($indeksMenganggur[$akar]['sql_terakhir'] ?? null),
                'umur_transaksi_akar_detik' => $indeksMenganggur[$akar]['umur_transaksi_detik'] ?? null,
                'akar_diam_detik' => $indeksMenganggur[$akar]['diam_detik'] ?? null,
                // Akar yang TIDAK punya baris di dm_exec_requests berarti ia
                // sedang tidak menjalankan apa pun: transaksi yang dibuka lalu
                // ditinggal menunggu sesuatu di luar DB. Itu bentuk paling jahat
                // dan paling sering luput, jadi ia ditandai.
                'akar_menganggur' => ! isset($indeks[$akar]),
            ];
        }

        return $hasil;
    }

    /**
     * Sesi yang MEMEGANG transaksi terbuka tanpa menjalankan apa pun.
     *
     * Bentuk paling merusak sekaligus paling sering luput: ia menahan lock
     * sambil menunggu sesuatu di LUAR basis data — panggilan HTTP, berkas,
     * atau sekadar kode yang lupa commit — dan karena tidak menjalankan kueri
     * apa pun ia tidak muncul di dm_exec_requests sama sekali. Pemantau yang
     * hanya melihat permintaan aktif akan melaporkan "tidak ada apa-apa"
     * sementara separuh sistem menunggunya.
     *
     * Kalimat terakhirnya diambil dari most_recent_sql_handle; itulah yang
     * mengubah "sesi #59" menjadi kode yang bisa dibuka orangnya.
     *
     * @return list<array<string,mixed>>
     */
    protected function transaksiMenganggur(): array
    {
        $baris = DB::select(
            "SELECT TOP (50)
                    s.session_id                    AS session_id,
                    s.open_transaction_count        AS transaksi_terbuka,
                    DATEDIFF(second, s.last_request_end_time, GETDATE()) AS diam_detik,
                    DATEDIFF(second, at.transaction_begin_time, GETDATE()) AS umur_transaksi_detik,
                    s.login_name                    AS login_name,
                    s.host_name                     AS host_name,
                    s.program_name                  AS program_name,
                    LTRIM(RTRIM(REPLACE(CONVERT(varchar(128), s.context_info), CHAR(0), ''))) AS context_info,
                    CAST(SUBSTRING(t.text, 1, 600) AS varchar(600)) AS sql_terakhir
               FROM sys.dm_exec_sessions s
               JOIN sys.dm_exec_connections c ON c.session_id = s.session_id
               LEFT JOIN sys.dm_tran_session_transactions st ON st.session_id = s.session_id
               LEFT JOIN sys.dm_tran_active_transactions at ON at.transaction_id = st.transaction_id
              OUTER APPLY sys.dm_exec_sql_text(c.most_recent_sql_handle) t
              WHERE s.is_user_process = 1
                AND s.open_transaction_count > 0
                AND s.status <> 'running'
                AND s.session_id <> @@SPID
              ORDER BY DATEDIFF(second, s.last_request_end_time, GETDATE()) DESC"
        );

        // Satu sesi bisa memulangkan beberapa baris — lewat join transaksi, dan
        // karena sebuah sesi boleh punya lebih dari satu koneksi. Sebagian baris
        // itu TIDAK membawa sql_terakhir.
        //
        // Menyimpan yang pertama saja membuat kalimat terakhirnya hilang secara
        // acak, dan hilangnya justru pada hal yang paling dicari: kode apa yang
        // membuka transaksi ini. Karena itu baris tanpa SQL selalu kalah oleh
        // baris yang membawanya.
        //
        // Preferensi ini TIDAK berpenjaga, dan itu disengaja: uji hanya bisa
        // membuat satu koneksi bersih, sehingga baris kembar tak pernah muncul
        // dan sabotase apa pun tetap hijau. Terlihat pertama kali pada sesi
        // sungguhan, di mana sql_akar keluar kosong padahal sesinya jelas baru
        // menjalankan UPDATE. Yang berpenjaga adalah pengambilan kalimatnya
        // (lihat test_transaksi_menganggur_membawa_kalimat_terakhirnya).
        $perSesi = [];
        foreach ($baris as $r) {
            $b = (array) $r;
            $id = (int) $b['session_id'];
            $punyaSql = trim((string) ($b['sql_terakhir'] ?? '')) !== '';
            $sudahPunyaSql = trim((string) ($perSesi[$id]['sql_terakhir'] ?? '')) !== '';

            if (isset($perSesi[$id]) && ($sudahPunyaSql || ! $punyaSql)) {
                continue;
            }

            [$jejak, $rute] = $this->uraikanTanda((string) ($b['context_info'] ?? ''));
            $b['trace_id'] = $jejak;
            $b['jalur'] = $rute;
            $perSesi[$id] = $b;
        }

        return array_values($perSesi);
    }

    /**
     * Lock yang sedang MENUNGGU — bukan yang sudah dipegang.
     *
     * Daftar lock yang dipegang panjang dan sebagian besar tidak menarik: lock
     * yang GRANT tidak menyakiti siapa pun. Yang berstatus WAIT jumlahnya
     * sedikit dan tiap barisnya langsung menamai objek yang jadi rebutan —
     * itulah yang menunjuk ke fitur, tanpa perlu dibaca satu per satu.
     *
     * @return list<array<string,mixed>>
     */
    protected function lockMenunggu(int $maks): array
    {
        $baris = DB::select(
            'SELECT TOP ('.$maks.')
                    ISNULL(OBJECT_NAME(COALESCE(
                        CASE WHEN l.resource_type = \'OBJECT\'
                             THEN CAST(l.resource_associated_entity_id AS int) END,
                        p.object_id
                    )), \'(bukan objek)\') AS objek,
                    l.resource_type  AS tipe_sumber,
                    l.request_mode   AS mode_diminta,
                    COUNT(*)         AS jumlah,
                    COUNT(DISTINCT l.request_session_id) AS jumlah_sesi
               FROM sys.dm_tran_locks l
               LEFT JOIN sys.partitions p
                      ON l.resource_type IN (\'KEY\', \'PAGE\', \'RID\', \'HOBT\')
                     AND p.hobt_id = l.resource_associated_entity_id
              WHERE l.resource_database_id = DB_ID()
                AND l.request_status = \'WAIT\'
              GROUP BY ISNULL(OBJECT_NAME(COALESCE(
                           CASE WHEN l.resource_type = \'OBJECT\'
                                THEN CAST(l.resource_associated_entity_id AS int) END,
                           p.object_id
                       )), \'(bukan objek)\'),
                       l.resource_type, l.request_mode
              ORDER BY COUNT(*) DESC'
        );

        return array_map(fn ($r) => (array) $r, $baris);
    }

    /**
     * Lock yang sedang dipegang, dikelompokkan per OBJEK.
     *
     * Per objek, bukan per sesi: pertanyaan yang ingin dijawab adalah "tabel
     * mana yang jadi rebutan", dan itulah yang menunjuk ke fitur.
     *
     * @return list<array<string,mixed>>
     */
    protected function lockPerObjek(int $maks): array
    {
        // resource_associated_entity_id BERBEDA ARTI menurut resource_type:
        // untuk OBJECT ia object_id (int), untuk KEY/PAGE/RID/HOBT ia hobt_id
        // (bigint). Menyerahkannya mentah-mentah ke OBJECT_NAME() — yang hanya
        // menerima int — melempar "Arithmetic overflow" begitu ada satu saja
        // lock baris atau halaman, dan itu justru bentuk lock yang paling
        // sering muncul. Yang bertipe hobt karena itu dilewatkan sys.partitions
        // dulu untuk menemukan objeknya.
        $baris = DB::select(
            'SELECT TOP ('.$maks.')
                    ISNULL(OBJECT_NAME(COALESCE(
                        CASE WHEN l.resource_type = \'OBJECT\'
                             THEN CAST(l.resource_associated_entity_id AS int) END,
                        p.object_id
                    )), \'(bukan objek)\') AS objek,
                    l.resource_type   AS tipe_sumber,
                    l.request_mode    AS mode,
                    l.request_status  AS keadaan,
                    COUNT(*)          AS jumlah,
                    COUNT(DISTINCT l.request_session_id) AS jumlah_sesi
               FROM sys.dm_tran_locks l
               LEFT JOIN sys.partitions p
                      ON l.resource_type IN (\'KEY\', \'PAGE\', \'RID\', \'HOBT\')
                     AND p.hobt_id = l.resource_associated_entity_id
              WHERE l.resource_database_id = DB_ID()
              GROUP BY ISNULL(OBJECT_NAME(COALESCE(
                           CASE WHEN l.resource_type = \'OBJECT\'
                                THEN CAST(l.resource_associated_entity_id AS int) END,
                           p.object_id
                       )), \'(bukan objek)\'),
                       l.resource_type, l.request_mode, l.request_status
              ORDER BY COUNT(*) DESC'
        );

        return array_map(fn ($r) => (array) $r, $baris);
    }

    /**
     * Tekanan koneksi: inilah yang benar-benar habis saat "Login timeout expired".
     *
     * @return array<string,mixed>
     */
    protected function tekananKoneksi(): array
    {
        $baris = DB::select(
            'SELECT s.program_name AS program,
                    COUNT(*) AS jumlah,
                    SUM(CASE WHEN s.status = \'running\' THEN 1 ELSE 0 END) AS berjalan,
                    SUM(CASE WHEN s.status = \'sleeping\' AND s.open_transaction_count > 0 THEN 1 ELSE 0 END) AS tidur_bertransaksi
               FROM sys.dm_exec_sessions s
              WHERE s.is_user_process = 1
              GROUP BY s.program_name
              ORDER BY COUNT(*) DESC'
        );

        $total = 0;
        $tidurBertransaksi = 0;
        foreach ($baris as $r) {
            $total += (int) $r->jumlah;
            $tidurBertransaksi += (int) $r->tidur_bertransaksi;
        }

        return [
            'total_sesi' => $total,
            // Sesi yang TIDUR tetapi memegang transaksi terbuka adalah bentuk
            // paling merusak: ia menahan lock tanpa mengerjakan apa pun, dan
            // tidak muncul di dm_exec_requests sehingga luput dari pemantau
            // yang hanya melihat permintaan aktif.
            'tidur_bertransaksi' => $tidurBertransaksi,
            'per_program' => array_map(fn ($r) => (array) $r, $baris),
        ];
    }

    /**
     * Pecah tanda sesi menjadi [trace, rute].
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected function uraikanTanda(string $tanda): array
    {
        $tanda = trim($tanda);
        if ($tanda === '') {
            return [null, null];
        }

        if (! str_contains($tanda, '|')) {
            // Bentuk lama: hanya trace, tanpa rute.
            return [$tanda, null];
        }

        [$jejak, $rute] = explode('|', $tanda, 2);
        $jejak = trim($jejak);
        $rute = trim($rute);

        return [$jejak !== '' ? $jejak : null, $rute !== '' ? $rute : null];
    }

    /**
     * Peta Trace_Id -> "METHOD /jalur" untuk sesi yang tertandai.
     *
     * Satu kueri untuk semua trace yang terlihat, bukan satu per baris: pemantau
     * yang ber-N+1 akan menambah beban yang sedang ia ukur.
     *
     * @param  list<array<string,mixed>>  $permintaan
     * @return array<string,string>
     */
    protected function petaTraceKeJalur(array $permintaan): array
    {
        $jejak = array_values(array_unique(array_filter(array_map(
            static fn ($p) => (string) ($p['context_info'] ?? ''),
            $permintaan,
        ))));

        if (! $jejak) {
            return [];
        }

        try {
            $baris = DB::table('N_HRIS_Request_Diagnostics')
                ->whereIn('Trace_Id', $jejak)
                ->orderByDesc('Id')
                ->limit(count($jejak) * 2)
                ->get(['Trace_Id', 'Path', 'Method']);
        } catch (\Throwable) {
            return [];
        }

        $peta = [];
        foreach ($baris as $b) {
            $peta[$b->Trace_Id] ??= trim(($b->Method ?? '').' '.($b->Path ?? ''));
        }

        return $peta;
    }
}
