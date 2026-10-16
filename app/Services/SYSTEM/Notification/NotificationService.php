<?php

namespace App\Services\SYSTEM\Notification;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    private const TABLE = 'N_HRIS_Notification';

    /**
     * Tipe SEBENARNYA kolom teks yang dipakai sebagai predikat.
     *
     * Diperiksa di katalog (sys.columns), bukan diasumsikan: satu tipe yang
     * salah tulis di sini menghasilkan CAST yang memotong nilai tanpa suara.
     */
    private const TIPE_KOLOM = [
        'Kode_Karyawan' => 'varchar(50)',
        'Is_Deleted' => 'char(1)',
        'Is_Read' => 'char(1)',
        'Channel' => 'varchar(20)',
        'Module' => 'varchar(50)',
        'Type' => 'varchar(50)',
        'source' => 'varchar(100)',
    ];

    public const CHANNEL_DASHBOARD_PERSONAL = 'DASH_PERSON';

    public const DELIVERY_IN_APP = 'IN_APP';

    public const DELIVERY_WHATSAPP = 'WHATSAPP';

    public const DELIVERY_EMAIL = 'EMAIL';

    public const DELIVERY_PUSH = 'PUSH';

    /**
     * Ambil notifikasi unread dengan filter channel/module/type/source opsional.
     */
    public function listUnread(string $kodeKaryawan, array $options = [])
    {
        $limit = max(1, min(100, (int) ($options['limit'] ?? 10)));

        return $this->activeUnreadQuery($kodeKaryawan, $options)
            ->orderBy('Created_At', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($notif) => $this->mapNotification($notif));
    }

    /**
     * Ambil notifikasi dengan filter status read/unread, channel, module, type, source opsional.
     */
    public function listNotifications(string $kodeKaryawan, array $options = [])
    {
        $limit = max(1, min(100, (int) ($options['limit'] ?? 10)));

        return $this->activeQuery($kodeKaryawan, $options)
            ->orderBy('Created_At', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($notif) => $this->mapNotification($notif));
    }

    /**
     * Ambil notifikasi dengan pagination (untuk Modal Pusat Notifikasi).
     */
    public function paginateNotifications(string $kodeKaryawan, array $options = [])
    {
        $perPage = max(1, min(100, (int) ($options['limit'] ?? 15)));

        $paginator = $this->activeQuery($kodeKaryawan, $options)
            ->orderBy('Created_At', 'desc')
            ->paginate($perPage);

        $paginator->getCollection()->transform(function ($notif) {
            return $this->mapNotification($notif);
        });

        return $paginator;
    }

    /**
     * Dapatkan notifikasi unread untuk seorang karyawan.
     *
     * @deprecated Gunakan listUnread().
     */
    public function getUnreadNotifications(string $kodeKaryawan, int $limit = 10, array $options = [])
    {
        return $this->listUnread($kodeKaryawan, array_merge($options, ['limit' => $limit]));
    }

    /**
     * Dapatkan jumlah notifikasi yang belum dibaca
     */
    public function countUnread(string $kodeKaryawan, array $options = []): int
    {
        return $this->activeUnreadQuery($kodeKaryawan, $options)->count();
    }

    /**
     * Tandai satu notifikasi sebagai sudah dibaca
     */
    public function markAsRead(int $idNotification, ?string $kodeKaryawan = null): bool
    {
        return $this->ownedQuery($idNotification, $kodeKaryawan)->update([
            'Is_Read' => 'Y',
            'Read_At' => now(),
        ]) > 0;
    }

    /**
     * Tandai semua notifikasi untuk seorang karyawan sebagai sudah dibaca
     */
    public function markAllAsRead(string $kodeKaryawan, array $options = []): int
    {
        return $this->activeUnreadQuery($kodeKaryawan, $options)->update([
            'Is_Read' => 'Y',
            'Read_At' => now(),
        ]);
    }

    /**
     * Manual dismiss / Mark Done: sembunyikan sekaligus tandai sudah dibaca.
     */
    public function dismiss(int $idNotification, ?string $kodeKaryawan = null): bool
    {
        return $this->ownedQuery($idNotification, $kodeKaryawan)->update($this->resolvedColumns()) > 0;
    }

    /**
     * Upsert notifikasi berdasarkan Notification_Key agar tidak double notif.
     *
     * Jika key yang sama sudah dismissed/deleted, method ini tidak menghidupkan ulang
     * notifikasi tersebut kecuali revive_deleted=true diberikan secara eksplisit.
     */
    public function upsertByKey(array $payload): ?int
    {
        $notificationKey = trim((string) ($payload['notification_key'] ?? ($payload['Notification_Key'] ?? '')));
        if ($notificationKey === '') {
            return $this->createNotification($payload);
        }

        $kodeKaryawan = $this->requireString($payload, ['kode_karyawan', 'Kode_Karyawan']);
        $existing = DB::table(self::TABLE)
            ->where('Notification_Key', $notificationKey)
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->first();

        if ($existing) {
            if ($this->isDeletedRow($existing) && empty($payload['revive_deleted'])) {
                return (int) $existing->Id_Notification;
            }

            DB::table(self::TABLE)
                ->where('Id_Notification', $existing->Id_Notification)
                ->update($this->buildUpdateColumns($payload));

            return (int) $existing->Id_Notification;
        }

        return $this->createNotification(array_merge($payload, ['notification_key' => $notificationKey]));
    }

    /**
     * Resolve notifikasi syncable berdasarkan key.
     */
    public function resolveByKey(string $notificationKey, ?string $kodeKaryawan = null): bool
    {
        $query = DB::table(self::TABLE)->where('Notification_Key', $notificationKey);

        if ($kodeKaryawan !== null) {
            $query->where('Kode_Karyawan', $kodeKaryawan);
        }

        return $query->update($this->resolvedColumns()) > 0;
    }

    /**
     * Versi BATCH resolveByKey: resolve banyak key sekaligus (1 UPDATE whereIn),
     * menggantikan N panggilan resolveByKey (1 UPDATE/key) saat sync notif personal
     * (per tanggal absen / per submission). Hasil identik (kolom resolvedColumns sama).
     *
     * @param  array<int,string>  $notificationKeys
     */
    public function resolveManyByKey(array $notificationKeys, ?string $kodeKaryawan = null): void
    {
        $keys = array_values(array_unique(array_filter(array_map('trim', $notificationKeys), 'strlen')));
        if (empty($keys)) {
            return;
        }

        foreach (array_chunk($keys, 500) as $chunk) {
            $query = DB::table(self::TABLE)->whereIn('Notification_Key', $chunk);
            if ($kodeKaryawan !== null) {
                $query->where('Kode_Karyawan', $kodeKaryawan);
            }
            $query->update($this->resolvedColumns());
        }
    }

    /**
     * Cek apakah Notification_Key pernah di-dismiss/delete oleh user/system.
     */
    public function existsDismissedKey(string $notificationKey, string $kodeKaryawan): bool
    {
        return DB::table(self::TABLE)
            ->where('Notification_Key', $notificationKey)
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Is_Deleted', 'Y')
            ->exists();
    }

    /**
     * Versi BATCH existsDismissedKey: dari daftar key, kembalikan map key yang
     * SUDAH di-dismiss (Is_Deleted='Y') untuk kode ini. Menggantikan N panggilan
     * exists() (1 per key) dengan 1 query.
     *
     * @param  array<int,string>  $notificationKeys
     * @return array<string,true> key dismissed => true
     */
    public function dismissedKeysIn(array $notificationKeys, string $kodeKaryawan): array
    {
        $keys = array_values(array_unique(array_filter(array_map('trim', $notificationKeys), 'strlen')));
        if (empty($keys)) {
            return [];
        }

        $result = [];
        foreach (array_chunk($keys, 500) as $chunk) {
            DB::table(self::TABLE)
                ->where('Kode_Karyawan', $kodeKaryawan)
                ->where('Is_Deleted', 'Y')
                ->whereIn('Notification_Key', $chunk)
                ->pluck('Notification_Key')
                ->each(function ($k) use (&$result) {
                    $result[trim((string) $k)] = true;
                });
        }

        return $result;
    }

    /**
     * Buat notifikasi baru
     */
    public function createNotification(array $data): int
    {
        return DB::table(self::TABLE)->insertGetId($this->buildInsertColumns($data));
    }

    /**
     * Versi BATCH dari upsertByKey untuk banyak payload sekaligus (mis. sync Action
     * Center leader yang meng-upsert notif per anggota tim). Menggantikan N×(SELECT+
     * UPDATE/INSERT) dengan: 1 SELECT existing (semua key) + bulk INSERT baru +
     * UPDATE per-id untuk yang sudah ada. Logika identik upsertByKey (reuse
     * buildInsertColumns/buildUpdateColumns/isDeletedRow) -> hasil sama, jauh lebih
     * sedikit query.
     *
     * @param  array<int,array<string,mixed>>  $payloads  tiap item wajib punya
     *                                                    notification_key + kode_karyawan (sama seperti upsertByKey).
     */
    public function upsertManyByKey(array $payloads): void
    {
        if (empty($payloads)) {
            return;
        }

        // Normalisasi + buang yang tak punya key (fallback ke create biasa).
        $valid = [];
        foreach ($payloads as $p) {
            $key = trim((string) ($p['notification_key'] ?? ($p['Notification_Key'] ?? '')));
            $kode = trim((string) ($p['kode_karyawan'] ?? ($p['Kode_Karyawan'] ?? '')));
            if ($key === '' || $kode === '') {
                // Tanpa key/kode tak bisa di-dedup -> insert langsung (jarang).
                $this->createNotification($p);

                continue;
            }
            $valid[] = ['key' => $key, 'kode' => $kode, 'payload' => $p];
        }
        if (empty($valid)) {
            return;
        }

        // 1 SELECT untuk SEMUA (key,kode) existing. Index unik per (key,kode) di
        // memori. whereIn pada key + filter kode di memori (key sudah cukup selektif).
        $keys = array_values(array_unique(array_map(fn ($v) => $v['key'], $valid)));
        $existingByPair = [];
        foreach (array_chunk($keys, 500) as $chunk) {
            DB::table(self::TABLE)
                ->whereIn('Notification_Key', $chunk)
                ->get()
                ->each(function ($row) use (&$existingByPair) {
                    $pair = trim((string) $row->Notification_Key).'|'.trim((string) $row->Kode_Karyawan);
                    // Simpan hanya 1 (mestinya unik per pair).
                    $existingByPair[$pair] = $row;
                });
        }

        $inserts = [];
        foreach ($valid as $v) {
            $pair = $v['key'].'|'.$v['kode'];
            $existing = $existingByPair[$pair] ?? null;

            if ($existing) {
                if ($this->isDeletedRow($existing) && empty($v['payload']['revive_deleted'])) {
                    continue; // identik perilaku upsertByKey: jangan sentuh yg terhapus
                }
                $updateColumns = $this->buildUpdateColumns($v['payload']);
                // Skip UPDATE bila konten BERMAKNA tak berubah (mayoritas sync ulang
                // menulis notif yang isinya sama -> UPDATE mubazir). Hemat write besar.
                if (! $this->notificationContentChanged($existing, $updateColumns, $v['payload'])) {
                    continue;
                }
                DB::table(self::TABLE)
                    ->where('Id_Notification', $existing->Id_Notification)
                    ->update($updateColumns);

                continue;
            }

            // Baru -> kumpulkan untuk bulk insert.
            $inserts[] = $this->buildInsertColumns(
                array_merge($v['payload'], ['notification_key' => $v['key']]),
            );
        }

        /*
         * Bulk insert semua yang baru dalam chunk.
         *
         * Ukuran chunk DIHITUNG, bukan ditetapkan. SQL Server menolak lebih dari
         * 2100 parameter per pernyataan, dan satu baris notifikasi mengikat satu
         * parameter per kolom — jadi angka tetap 200 yang dipakai sebelumnya baru
         * aman kalau baris selebar <=10 kolom. Nyatanya payload biasa saja sudah
         * 14 kolom (200 x 14 = 2800 binding), dan bisa lebih lebar lagi kalau
         * membawa Expired_At/Sent_At/Meta. Akibatnya setiap pengiriman massal di
         * atas ~150 penerima gagal keras, bukan melambat: scan pengingat malam
         * mati sebelum sempat menandai Terakhir_Diingatkan_At sehingga terulang
         * identik tiap malam, dan tombol "Ingatkan semua" balas 500 walau datanya
         * sudah ter-commit.
         *
         * Lebar diambil dari baris TERLEBAR, dan plafonnya 2000 supaya masih ada
         * ruang bila suatu saat pernyataannya membawa binding tambahan.
         */
        $lebarBaris = 1;
        foreach ($inserts as $baris) {
            $lebarBaris = max($lebarBaris, count($baris));
        }
        $perChunk = max(1, intdiv(2000, $lebarBaris));

        foreach (array_chunk($inserts, $perChunk) as $chunk) {
            DB::table(self::TABLE)->insert($chunk);
        }
    }

    /**
     * Apakah UPDATE notif benar-benar mengubah konten BERMAKNA? Dipakai
     * upsertManyByKey untuk skip UPDATE mubazir (sync ulang menulis isi yang sama).
     * Membandingkan kolom isi (Type/Title/Message/dst) + perubahan status baca
     * (reset Is_Read saat tanpa preserve_read pada notif yang sudah dibaca).
     */
    protected function notificationContentChanged(object $existing, array $updateColumns, array $payload): bool
    {
        // Kolom konten yang dibandingkan (string/int). Expired_At/Sent_At/Meta
        // bisa berupa Expression -> diperlakukan "berubah" bila ada (jarang & murah).
        $compare = ['Type', 'Title', 'Message', 'Module', 'Severity', 'Action_Url', 'Reference_Id', 'Priority', 'Channel', 'source'];
        foreach ($compare as $col) {
            if (! array_key_exists($col, $updateColumns)) {
                continue;
            }
            $new = $updateColumns[$col];
            if ($new instanceof \Illuminate\Database\Query\Expression) {
                return true; // ekspresi -> anggap berubah (aman)
            }
            $old = $existing->{$col} ?? null;
            // Bandingkan sebagai string (kolom DB string); Priority int.
            if ((string) $new !== (string) $old) {
                return true;
            }
        }

        // Meta_JSON berubah?
        if (array_key_exists('Meta_JSON', $updateColumns)) {
            $newMeta = $updateColumns['Meta_JSON'];
            if ($newMeta instanceof \Illuminate\Database\Query\Expression) {
                return true;
            }
            if ((string) $newMeta !== (string) ($existing->Meta_JSON ?? null)) {
                return true;
            }
        }

        // Reset status baca: bila payload akan menimpa Is_Read='T' (tanpa preserve_read)
        // sementara notif existing sedang 'Y' (sudah dibaca) -> itu perubahan bermakna.
        if (array_key_exists('Is_Read', $updateColumns)
            && (string) $updateColumns['Is_Read'] !== (string) ($existing->Is_Read ?? '')) {
            return true;
        }

        // Bila existing ter-set Is_Deleted='Y' lalu di-revive jadi 'T' -> berubah.
        if (array_key_exists('Is_Deleted', $updateColumns)
            && (string) $updateColumns['Is_Deleted'] !== (string) ($existing->Is_Deleted ?? '')) {
            return true;
        }

        return false;
    }

    public static function dashboardPersonalKey(string $kodeKaryawan, string $date, string $type): string
    {
        return self::dashboardPersonalTokenKey($kodeKaryawan, Carbon::parse($date)->toDateString(), $type);
    }

    public static function dashboardPersonalTokenKey(string $kodeKaryawan, string $token, string $type): string
    {
        return implode('|', [
            self::CHANNEL_DASHBOARD_PERSONAL,
            strtoupper(trim($kodeKaryawan)),
            strtoupper(trim($token)),
            strtoupper(trim($type)),
        ]);
    }

    protected function activeUnreadQuery(string $kodeKaryawan, array $options = []): Builder
    {
        $options['is_read'] = false;

        return $this->activeQuery($kodeKaryawan, $options);
    }

    protected function activeQuery(string $kodeKaryawan, array $options = []): Builder
    {
        $query = DB::table(self::TABLE);

        $this->samaTeks($query, 'Kode_Karyawan', $kodeKaryawan);
        $this->samaTeks($query, 'Is_Deleted', 'T');

        $query
            ->where(function (Builder $query) {
                $query->whereNull('Expired_At')->orWhere('Expired_At', '>=', now());
            })
            ->where(function (Builder $query) {
                $query->whereNull('Sent_At')->orWhere('Sent_At', '<=', now());
            });

        if (isset($options['is_read'])) {
            $this->samaTeks($query, 'Is_Read', $options['is_read'] ? 'Y' : 'T');
        }

        $this->applyOptionalFilters($query, $options);

        return $query;
    }

    protected function ownedQuery(int $idNotification, ?string $kodeKaryawan = null): Builder
    {
        $query = DB::table(self::TABLE)->where('Id_Notification', $idNotification);

        if ($kodeKaryawan !== null) {
            $query->where('Kode_Karyawan', $kodeKaryawan);
        }

        return $query;
    }

    /**
     * Perbandingan kolom TEKS dengan parameter yang tipenya sama.
     *
     * pdo_sqlsrv mengirim setiap string PHP sebagai `nvarchar(4000)`. Kolom di
     * tabel ini varchar/char, jadi SQL Server harus meng-CONVERT KOLOMNYA lebih
     * dulu — dan kolom yang dibungkus fungsi TIDAK BISA di-seek.
     *
     * Akibatnya terlihat telanjang di sini: IX_Notification_Unread_Shell
     * kuncinya cocok SEMPURNA dengan predikat topbar, tetapi tercatat 4 seek
     * melawan 34.331 scan. Diukur pada predikat yang sama persis:
     *
     *     parameter nvarchar : 432 logical reads
     *     parameter varchar  :   6 logical reads
     *
     * Jalur ini dipukul setiap pengguna di setiap halaman, jadi 426 pembacaan
     * halaman yang dihemat itu dikalikan seluruh lalu lintas.
     *
     * CAST dipasang PER KUERI, bukan lewat sakelar koneksi.
     * PDO::SQLSRV_ATTR_ENCODING => SQLSRV_ENCODING_SYSTEM memang menyembuhkan
     * sisi pengiriman, tetapi merusak sisi pembacaan — data kembali dalam
     * codepage sistem sementara Laravel memperlakukan semuanya UTF-8, dan
     * seluruh halaman menjawab 500 dengan "Malformed UTF-8 characters".
     * Catatannya ada di config/database.php.
     *
     * Nama kolom dan tipenya SELALU literal dari kelas ini, tak pernah dari
     * permintaan — daftar tetap di TIPE_KOLOM di bawah yang menentukannya.
     */
    private function samaTeks(Builder $query, string $kolom, string $nilai): void
    {
        $tipe = self::TIPE_KOLOM[$kolom] ?? null;

        if ($tipe === null) {
            // Kolom di luar daftar dibandingkan apa adanya: menebak tipenya
            // justru bisa memotong nilai secara diam-diam.
            $query->where($kolom, $nilai);

            return;
        }

        $query->whereRaw("[{$kolom}] = CAST(? AS {$tipe})", [$nilai]);
    }

    protected function applyOptionalFilters(Builder $query, array $options): void
    {
        if (! array_key_exists('channel', $options)) {
            $options['channel'] = self::DELIVERY_IN_APP;
        }

        foreach (['channel' => 'Channel', 'module' => 'Module', 'type' => 'Type'] as $optionKey => $column) {
            $value = $options[$optionKey] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $values = array_values(array_filter($value, fn ($item) => trim((string) $item) !== ''));
                if ($values) {
                    $query->whereIn($column, $values);
                }

                continue;
            }

            $this->samaTeks($query, $column, (string) $value);
        }

        $source = $options['source'] ?? null;
        if ($source !== null && trim((string) $source) !== '') {
            $sourceValue = (string) $source;
            // Kolom `source` adalah sumber utama dan index-friendly. JSON_VALUE di WHERE
            // tidak bisa pakai index (full scan tiap request via shell), jadi cabang
            // fallback Meta_JSON hanya dipakai saat masa transisi sebelum backfill
            // kolom `source` selesai (lihat problem/fix-notification-perf.sql).
            if (config('notification.legacy_source_fallback', false)) {
                $query->where(function (Builder $q) use ($sourceValue) {
                    $this->samaTeks($q, 'source', $sourceValue);
                    $q->orWhere(function (Builder $q) use ($sourceValue) {
                        $q->where(function (Builder $q) {
                            $q->whereNull('source')->orWhere('source', '');
                        })->whereRaw(
                            "JSON_VALUE(CASE WHEN ISJSON(Meta_JSON) = 1 THEN Meta_JSON ELSE NULL END, '$.source') = ?",
                            [$sourceValue],
                        );
                    });
                });
            } else {
                $this->samaTeks($query, 'source', $sourceValue);
            }
        }

        $search = $options['search'] ?? null;
        if ($search !== null && trim((string) $search) !== '') {
            $searchStr = '%'.trim((string) $search).'%';
            $query->where(function (Builder $q) use ($searchStr) {
                $q->where('Title', 'LIKE', $searchStr)
                    ->orWhere('Message', 'LIKE', $searchStr);
            });
        }
    }

    protected function buildInsertColumns(array $data): array
    {
        $now = now();

        return array_filter(
            [
                'Kode_Karyawan' => $this->requireString($data, ['kode_karyawan', 'Kode_Karyawan']),
                'Type' => $this->requireString($data, ['type', 'Type']),
                'Title' => $this->requireString($data, ['title', 'Title']),
                'Message' => $this->optionalString($data, ['message', 'Message']),
                'Module' => $this->normalizeModule($this->requireString($data, ['module', 'Module'])),
                'Severity' => $this->optionalString($data, ['severity', 'Severity']) ?? 'info',
                'Action_Url' => $this->optionalString($data, ['action_url', 'Action_Url']),
                'Is_Read' => $this->optionalString($data, ['is_read', 'Is_Read']) ?? 'T',
                'Created_At' => $data['created_at'] ?? ($data['Created_At'] ?? $now),
                'Read_At' => $data['read_at'] ?? ($data['Read_At'] ?? null),
                'Created_By' => $this->optionalString($data, ['created_by', 'Created_By']) ?? 'SYSTEM',
                'Expired_At' => $data['expired_at'] ?? ($data['Expired_At'] ?? null),
                'Reference_Id' => $this->optionalString($data, ['reference_id', 'Reference_Id']),
                'Notification_Key' => $this->optionalString($data, ['notification_key', 'Notification_Key']),
                'Priority' => $this->optionalInt($data, ['priority', 'Priority']),
                'Channel' => $this->optionalString($data, ['channel', 'Channel']) ?? self::DELIVERY_IN_APP,
                'Sent_At' => $data['sent_at'] ?? ($data['Sent_At'] ?? null),
                'Meta_JSON' => $this->optionalMetaJson($data),
                'source' => $this->resolveSourceColumn($data),
                'Is_Deleted' => $this->optionalString($data, ['is_deleted', 'Is_Deleted']) ?? 'T',
            ],
            fn ($value) => $value !== null,
        );
    }

    protected function buildUpdateColumns(array $data): array
    {
        $columns = [];
        $this->putOptionalString($columns, 'Type', $data, ['type', 'Type']);
        $this->putOptionalString($columns, 'Title', $data, ['title', 'Title']);
        $this->putOptionalString($columns, 'Message', $data, ['message', 'Message']);
        $this->putOptionalModule($columns, $data);
        $this->putOptionalString($columns, 'Severity', $data, ['severity', 'Severity']);
        $this->putOptionalString($columns, 'Action_Url', $data, ['action_url', 'Action_Url']);
        $this->putOptionalString($columns, 'Created_By', $data, ['created_by', 'Created_By']);
        $this->putOptionalRaw($columns, 'Expired_At', $data, ['expired_at', 'Expired_At']);
        $this->putOptionalString($columns, 'Reference_Id', $data, ['reference_id', 'Reference_Id']);
        $this->putOptionalInt($columns, 'Priority', $data, ['priority', 'Priority']);
        $this->putOptionalString($columns, 'Channel', $data, ['channel', 'Channel']);
        $columns['Channel'] ??= self::DELIVERY_IN_APP;
        $this->putOptionalRaw($columns, 'Sent_At', $data, ['sent_at', 'Sent_At']);
        $this->putOptionalMeta($columns, $data);

        $source = $this->resolveSourceColumn($data);
        if ($source !== null) {
            $columns['source'] = $source;
        }

        $columns['Is_Deleted'] = 'T';

        // Secara default, upsert mereset status baca (notif yang masih relevan
        // dianggap perlu perhatian lagi). Tapi untuk Action Center kita ingin
        // status "sudah dibaca" (abu-abu) bertahan meski issue-nya masih ada =>
        // caller bisa kirim preserve_read=true agar Is_Read/Read_At tidak ditimpa.
        if (empty($data['preserve_read'])) {
            $columns['Is_Read'] = 'T';
            $columns['Read_At'] = null;
        }

        return $columns;
    }

    protected function resolvedColumns(): array
    {
        return [
            'Is_Deleted' => 'Y',
            'Is_Read' => 'Y',
            'Read_At' => now(),
        ];
    }

    protected function mapNotification(object $notif): array
    {
        $createdAt = $notif->Created_At ?? null;

        return [
            'id' => $notif->Id_Notification,
            'type' => $notif->Type,
            'title' => $notif->Title,
            'message' => $notif->Message,
            'module' => $notif->Module,
            'severity' => $notif->Severity,
            'action_url' => $notif->Action_Url,
            'created_at' => $createdAt,
            'created_at_human' => $this->formatHumanDateTime($createdAt),
            'reference_id' => $notif->Reference_Id ?? null,
            'notification_key' => $notif->Notification_Key ?? null,
            'priority' => isset($notif->Priority) ? (int) $notif->Priority : 3,
            'channel' => $notif->Channel ?? null,
            'meta' => $this->decodeMetaJson($notif->Meta_JSON ?? null),
            'is_read' => false,
        ];
    }

    protected function formatHumanDateTime(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)
                ->locale('id')
                ->translatedFormat('d M Y H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    protected function isDeletedRow(object $row): bool
    {
        return strtoupper(trim((string) ($row->Is_Deleted ?? 'T'))) === 'Y';
    }

    protected function requireString(array $data, array $keys): string
    {
        $value = $this->optionalString($data, $keys);

        if ($value === null) {
            throw new \InvalidArgumentException('Notification payload missing required field: '.implode('/', $keys));
        }

        return $value;
    }

    protected function optionalString(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $value = trim((string) $data[$key]);

                return $value === '' ? null : $value;
            }
        }

        return null;
    }

    protected function optionalInt(array $data, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                return (int) $data[$key];
            }
        }

        return null;
    }

    /**
     * Resolve nilai kolom `source` dari payload. Prioritas: key eksplisit
     * (source/Source) lalu meta.source. Dipakai untuk dual-write supaya kolom
     * `source` selalu sinkron dengan Meta_JSON.$.source.
     */
    protected function resolveSourceColumn(array $data): ?string
    {
        $explicit = $this->optionalString($data, ['source', 'Source']);
        if ($explicit !== null) {
            return $explicit;
        }

        foreach (['meta', 'Meta_JSON', 'meta_json'] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $meta = $data[$key];
            if (is_string($meta)) {
                $decoded = json_decode($meta, true);
                $meta = is_array($decoded) ? $decoded : [];
            }

            if (is_array($meta) && isset($meta['source']) && trim((string) $meta['source']) !== '') {
                return trim((string) $meta['source']);
            }
        }

        return null;
    }

    protected function optionalMetaJson(array $data): ?string
    {
        if (array_key_exists('meta', $data)) {
            return $this->encodeMetaJson($data['meta']);
        }

        if (array_key_exists('Meta_JSON', $data)) {
            return $this->encodeMetaJson($data['Meta_JSON']);
        }

        if (array_key_exists('meta_json', $data)) {
            return $this->encodeMetaJson($data['meta_json']);
        }

        return null;
    }

    protected function encodeMetaJson(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return json_last_error() === JSON_ERROR_NONE
                ? json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected function decodeMetaJson(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function normalizeModule(string $module): string
    {
        $module = strtoupper(trim($module));

        return match ($module) {
            'HCIS', 'KPI', 'LMS', 'VISITOR' => $module,
            'PENGAJUAN', 'LEMBUR', 'OVERTIME', 'ABSENSI', 'ATTENDANCE' => 'HCIS',
            default => 'HCIS',
        };
    }

    protected function putOptionalString(array &$columns, string $column, array $data, array $keys): void
    {
        $value = $this->optionalString($data, $keys);
        if ($value !== null) {
            $columns[$column] = $value;
        }
    }

    protected function putOptionalModule(array &$columns, array $data): void
    {
        $value = $this->optionalString($data, ['module', 'Module']);
        if ($value !== null) {
            $columns['Module'] = $this->normalizeModule($value);
        }
    }

    protected function putOptionalInt(array &$columns, string $column, array $data, array $keys): void
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $columns[$column] = $data[$key] === null || $data[$key] === '' ? null : (int) $data[$key];

                return;
            }
        }
    }

    protected function putOptionalRaw(array &$columns, string $column, array $data, array $keys): void
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $columns[$column] = $data[$key];

                return;
            }
        }
    }

    protected function putOptionalMeta(array &$columns, array $data): void
    {
        foreach (['meta', 'Meta_JSON', 'meta_json'] as $key) {
            if (array_key_exists($key, $data)) {
                $columns['Meta_JSON'] = $this->encodeMetaJson($data[$key]);

                return;
            }
        }
    }
}
