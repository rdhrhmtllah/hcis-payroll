<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopLog;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopVersiItem;
use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
/* Tanpa impor ini, Hashids di itemVersionDetails() diselesaikan sebagai
   App\Services\LMS\SOP\Hashids — kelas yang tak ada — dan pratinjau publish paket
   runtuh jadi 500 tepat ketika ada versi item berstatus DRAFT/READY. Baris di
   bawahnya tak pernah terpanggil pada paket yang isinya sudah terbit semua,
   jadi cacatnya diam sampai keadaan itu muncul. */
use Illuminate\Support\Facades\Schema;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Audit versi paket SOP.
 *
 * Layer ini sengaja dibuat opsional: sebelum DDL versi paket dijalankan, semua
 * caller tetap no-op agar publish item/paket tidak patah di environment lama.
 */
class SopPaketVersionService
{
    private const TABLE = 'N_SOP_Paket_Versi';

    private const ITEM_TABLE = 'N_SOP_Paket_Versi_Item';

    private ?bool $available = null;

    public function available(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        return $this->available = Schema::hasTable(self::TABLE) && Schema::hasTable(self::ITEM_TABLE);
    }

    public function current(int $idPaket): ?array
    {
        if (! $this->available()) {
            return null;
        }
        if (! $this->hasOfficialRelease($idPaket)) {
            return null;
        }

        $row = DB::table(self::TABLE)
            ->where('Id_SOP_Paket', $idPaket)
            ->orderByDesc('Versi_Ke')
            ->first([
                'Id_SOP_Paket_Versi',
                'Versi_Ke',
                'Trigger_Perubahan',
                'Ringkasan_Perubahan',
                'Tanggal_Publish',
                'Kode_Karyawan_Publisher',
                'Item_Total',
                'Item_Wajib',
            ]);

        if (! $row) {
            return null;
        }

        return [
            'id' => (int) $row->Id_SOP_Paket_Versi,
            'versi' => (int) $row->Versi_Ke,
            'trigger' => $row->Trigger_Perubahan,
            'ringkasan' => $row->Ringkasan_Perubahan,
            'tanggal_publish' => $row->Tanggal_Publish,
            'publisher_kode' => $row->Kode_Karyawan_Publisher,
            'item_total' => (int) $row->Item_Total,
            'item_wajib' => (int) $row->Item_Wajib,
        ];
    }

    public function history(int $idPaket, int $limit = 8): array
    {
        if (! $this->available()) {
            return [];
        }
        if (! $this->hasOfficialRelease($idPaket)) {
            return [];
        }

        return DB::table(self::TABLE)
            ->where('Id_SOP_Paket', $idPaket)
            ->orderByDesc('Versi_Ke')
            ->limit($limit)
            ->get([
                'Id_SOP_Paket_Versi',
                'Versi_Ke',
                'Trigger_Perubahan',
                'Ringkasan_Perubahan',
                'Tanggal_Publish',
                'Kode_Karyawan_Publisher',
                'Item_Total',
                'Item_Wajib',
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->Id_SOP_Paket_Versi,
                'versi' => (int) $row->Versi_Ke,
                'trigger' => $row->Trigger_Perubahan,
                'ringkasan' => $row->Ringkasan_Perubahan,
                'tanggal_publish' => $row->Tanggal_Publish,
                'publisher_kode' => $row->Kode_Karyawan_Publisher,
                'item_total' => (int) $row->Item_Total,
                'item_wajib' => (int) $row->Item_Wajib,
            ])
            ->all();
    }

    public function nextVersion(int $idPaket): int
    {
        if (! $this->available()) {
            return 1;
        }
        if (! $this->hasOfficialRelease($idPaket)) {
            return 1;
        }

        return ((int) DB::table(self::TABLE)->where('Id_SOP_Paket', $idPaket)->max('Versi_Ke')) + 1;
    }

    /**
     * Item paket yang BELUM punya versi untuk dirilis — judulnya, berurutan.
     *
     * Belum siap = dokumennya punya versi DRAFT dan TAK punya satu pun versi
     * TERBIT atau SIAP RILIS. Sampai 5 Okt 2026 syaratnya cuma "ada versi
     * DRAFT", warisan dari sebelum alur revisi dokumen: dulu versi draft
     * berarti itemnya belum berisi apa-apa, sekarang dokumen yang direvisi
     * tetap punya versi terbit yang sah. Revisi yang sedang menunggu pengesahan
     * menahan rilis pertama paket (paket 7181 di dev), padahal yang akan
     * dirilis adalah versi terbitnya.
     *
     * Ditautkan lewat DOKUMEN, mundur ke FK untuk versi ber-dokumen NULL —
     * sama dengan hitungan lain di pendingChanges().
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function itemBelumSiapRilis(int $idPaket): \Illuminate\Support\Collection
    {
        return DB::table('N_SOP_Paket_Item as i')
            ->where('i.Id_SOP_Paket', $idPaket)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->whereExists(fn ($q) => $this->versiItem($q, [SopStatus::VERSI_DRAFT]))
            ->whereNotExists(fn ($q) => $this->versiItem($q, [SopStatus::VERSI_READY, SopStatus::VERSI_PUBLISHED]))
            ->orderBy('i.Urutan')
            ->pluck('i.Judul_Item')
            ->map(fn ($j) => (string) ($j ?: 'Item tanpa judul'))
            ->values();
    }

    /** Subkueri: versi aktif berstatus tertentu milik item `i` (lewat dokumen, mundur ke FK). */
    private function versiItem($q, array $status): void
    {
        $q->select(DB::raw(1))
            ->from('N_SOP_Item_Versi as v')
            ->where(fn ($w) => $w->whereColumn('v.Id_SOP_Dokumen', 'i.Id_SOP_Dokumen')
                ->orWhereColumn('v.Id_SOP_Paket_Item', 'i.Id_SOP_Paket_Item'))
            ->where('v.Flag_Aktif', SopStatus::AKTIF)
            ->whereIn('v.Status', $status);
    }

    public function pendingChanges(int $idPaket): array
    {
        $current = $this->snapshot($idPaket)->values()->all();
        /* Item ditautkan lewat DOKUMEN (mundur ke FK untuk versi ber-dokumen
           NULL). Dengan FK saja, revisi draft pada dokumen bersama hanya
           terhitung di paket pembuat versinya — paket lain yang menyajikan
           dokumen yang sama akan melaporkan "nol perubahan menunggu" padahal
           dokumennya justru punya revisi yang belum terbit. Sejak fan-out
           Fase 3, menerbitkan revisi itu menggeser pointer mereka juga. */
        $joinItemLewatDokumen = fn ($j) => SopVersiItem::tautkan($j, 'i', 'v');

        $draftItemVersions = $this->itemBelumSiapRilis($idPaket)->count();
        $readyItemVersions = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', $joinItemLewatDokumen)
            ->where('i.Id_SOP_Paket', $idPaket)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('v.Flag_Aktif', SopStatus::AKTIF)
            ->where('v.Status', SopStatus::VERSI_READY)
            ->distinct()
            // Dihitung per ITEM PAKET INI, bukan per FK versi: dengan dokumen
            // bersama, FK versi menunjuk item di paket lain.
            ->count('i.Id_SOP_Paket_Item');
        $sectionRows = DB::table('N_SOP_Paket_Section')
            ->where('Id_SOP_Paket', $idPaket)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Urutan')
            ->get(['Nama_Section']);
        $sectionCount = $sectionRows->count();

        if (! $this->available() || ! $this->hasOfficialRelease($idPaket)) {
            return [
                'total' => count($current) + (int) $sectionCount + 1,
                'is_first_release' => true,
                'summary' => [
                    'package' => 1,
                    'sections' => (int) $sectionCount,
                    'items' => count($current),
                    'added' => count($current),
                    'changed' => 0,
                    'removed' => 0,
                    'metadata' => 0,
                    'section_updates' => 0,
                    'draft_item_versions' => $draftItemVersions,
                    'ready_item_versions' => $readyItemVersions,
                ],
                // Rilis pertama: seluruh isi paket adalah penambahan. Bentuknya harus sama
                // dengan SopPaketDraftService::pendingChanges() karena panel diff di Builder
                // membaca kunci package/sections/items/versions.
                'details' => [
                    'package' => [[
                        'type' => 'package', 'action' => 'added',
                        'title' => DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->value('Nama_Paket') ?: 'Paket Dokumen baru',
                        'fields' => [],
                    ]],
                    'sections' => $sectionRows->map(fn ($row) => [
                        'type' => 'section', 'action' => 'added', 'title' => $row->Nama_Section, 'fields' => [],
                    ])->all(),
                    'items' => collect($current)->map(fn ($row) => [
                        'type' => 'item', 'action' => 'added',
                        'title' => $row['Judul_Item'] ?? 'Item Dokumen',
                        'subtitle' => $row['Tipe_Item'] ?? null,
                        'fields' => [],
                    ])->all(),
                    'versions' => $this->itemVersionDetails($idPaket),
                ],
            ];
        }

        $last = DB::table(self::TABLE)
            ->where('Id_SOP_Paket', $idPaket)
            ->orderByDesc('Versi_Ke')
            ->first(['Snapshot_JSON', 'Tanggal_Publish']);

        $previous = $last && $last->Snapshot_JSON
            ? (json_decode($last->Snapshot_JSON, true) ?: [])
            : [];

        $currentByItem = collect($current)->keyBy('Id_SOP_Paket_Item');
        $previousByItem = collect($previous)->keyBy('Id_SOP_Paket_Item');

        $added = $currentByItem->keys()->diff($previousByItem->keys())->values();
        $removed = $previousByItem->keys()->diff($currentByItem->keys())->values();
        $changed = $currentByItem->keys()
            ->intersect($previousByItem->keys())
            ->filter(fn ($id) => $this->snapshotRowChanged($previousByItem[$id], $currentByItem[$id]))
            ->values();

        $publishedAt = $last?->Tanggal_Publish ? Carbon::parse($last->Tanggal_Publish) : null;
        $metadataChanged = $this->changedAfterRelease('N_SOP_Master_Paket', 'Id_SOP_Paket', $idPaket, $publishedAt);
        $sectionUpdates = $this->sectionUpdatesAfterRelease($idPaket, $publishedAt);

        $summary = [
            'package' => 0,
            'sections' => 0,
            'items' => 0,
            'added' => $added->count(),
            'changed' => $changed->count(),
            'removed' => $removed->count(),
            'metadata' => $metadataChanged ? 1 : 0,
            'section_updates' => $sectionUpdates,
            'draft_item_versions' => $draftItemVersions,
            'ready_item_versions' => $readyItemVersions,
        ];
        $summary['items'] = $summary['added'] + $summary['changed'] + $summary['removed'];
        $summary['sections'] = $sectionUpdates;
        $summary['package'] = $summary['metadata'];

        return [
            'total' => $summary['items'] + $summary['sections'] + $summary['package'] + $readyItemVersions,
            'is_first_release' => false,
            'summary' => $summary,
            'details' => [
                'package' => $metadataChanged ? [[
                    'type' => 'package', 'action' => 'changed',
                    'title' => DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->value('Nama_Paket') ?: 'Identitas paket',
                    'fields' => [],
                ]] : [],
                'sections' => $sectionUpdates > 0 ? [[
                    'type' => 'section', 'action' => 'changed',
                    'title' => $sectionUpdates.' section diperbarui setelah rilis terakhir',
                    'fields' => [],
                ]] : [],
                'items' => collect()
                    ->concat($added->map(fn ($id) => $this->itemChangeRow($currentByItem[$id] ?? null, 'added')))
                    ->concat($changed->map(fn ($id) => $this->itemChangeRow($currentByItem[$id] ?? null, 'changed')))
                    ->concat($removed->map(fn ($id) => $this->itemChangeRow($previousByItem[$id] ?? null, 'removed')))
                    ->filter()->values()->all(),
                'versions' => $this->itemVersionDetails($idPaket),
                // Kunci lama dipertahankan agar konsumen selain Builder tidak ikut rusak.
                'added' => $added->all(),
                'changed' => $changed->all(),
                'removed' => $removed->all(),
            ],
        ];
    }

    /** Satu baris diff item dalam bentuk yang dibaca panel rilis Builder. */
    private function itemChangeRow($row, string $action): ?array
    {
        if (! $row) {
            return null;
        }

        return [
            'type' => 'item',
            'action' => $action,
            'id' => ! empty($row['Id_SOP_Paket_Item']) ? Hashids::connection('custom')->encode((int) $row['Id_SOP_Paket_Item']) : null,
            'title' => $row['Judul_Item'] ?? 'Item Dokumen',
            'subtitle' => $row['Tipe_Item'] ?? null,
            'fields' => [],
        ];
    }

    /** Versi item yang masih Draft/Siap Rilis, ditampilkan sebagai grup "Versi Konten". */
    private function itemVersionDetails(int $idPaket): array
    {
        return DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'v.Id_SOP_Paket_Item')
            ->leftJoin('N_LMS_Master_Konten as k', 'k.Id_Master_Konten', '=', 'v.Id_Master_Konten')
            ->where('i.Id_SOP_Paket', $idPaket)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('v.Flag_Aktif', SopStatus::AKTIF)
            ->whereIn('v.Status', [SopStatus::VERSI_DRAFT, SopStatus::VERSI_READY])
            ->orderBy('i.Urutan')
            ->orderByDesc('v.Versi_Ke')
            ->get(['i.Id_SOP_Paket_Item', 'i.Judul_Item', 'v.Versi_Ke', 'v.Status', 'v.Ringkasan_Perubahan', 'k.Judul_Konten as Konten_Nama'])
            ->map(fn ($row) => [
                'type' => 'version',
                'action' => $row->Status === SopStatus::VERSI_READY ? 'ready' : 'draft',
                'id' => Hashids::connection('custom')->encode((int) $row->Id_SOP_Paket_Item),
                'title' => $row->Judul_Item,
                'subtitle' => trim('v'.$row->Versi_Ke.' '.($row->Konten_Nama ? '· '.$row->Konten_Nama : '')),
                'fields' => array_values(array_filter([$row->Ringkasan_Perubahan])),
            ])
            ->all();
    }

    public function releasedSnapshot(int $idPaket): ?array
    {
        // array_key_exists, bukan ??=: null adalah jawaban yang SAH di sini
        // (paket tanpa snapshot v2), dan ??= akan mengulang kuerinya tiap kali
        // untuk justru paket-paket yang paling sering ditanya.
        if (array_key_exists($idPaket, $this->memoSnapshotRilis)) {
            return $this->memoSnapshotRilis[$idPaket];
        }

        return $this->memoSnapshotRilis[$idPaket] = $this->bacaSnapshotRilis($idPaket);
    }

    private function bacaSnapshotRilis(int $idPaket): ?array
    {
        if (! $this->available() || ! $this->hasOfficialRelease($idPaket)) {
            return null;
        }

        $row = DB::table(self::TABLE)
            ->where('Id_SOP_Paket', $idPaket)
            ->orderByDesc('Versi_Ke')
            ->first(['Id_SOP_Paket_Versi', 'Versi_Ke', 'Snapshot_JSON']);
        if (! $row || ! $row->Snapshot_JSON) {
            return null;
        }

        $items = json_decode($row->Snapshot_JSON, true);
        if (! is_array($items) || empty($items)) {
            return null;
        }

        $schema = (int) ($items[0]['Snapshot_Schema'] ?? 1);
        if ($schema < 2) {
            return null;
        }

        $sections = collect($items)
            ->filter(fn ($item) => ! empty($item['Id_SOP_Section']))
            ->map(fn ($item) => [
                'Id_SOP_Section' => (int) $item['Id_SOP_Section'],
                'Nama_Section' => $item['Section_Nama'] ?? 'Section',
                'Deskripsi' => $item['Section_Deskripsi'] ?? null,
                'Urutan' => (int) ($item['Section_Urutan'] ?? 0),
            ])
            ->unique('Id_SOP_Section')
            ->sortBy('Urutan')
            ->values()
            ->all();

        return [
            'id' => (int) $row->Id_SOP_Paket_Versi,
            'versi' => (int) $row->Versi_Ke,
            'sections' => $sections,
            'items' => collect($items)->sortBy('Urutan')->values()->all(),
        ];
    }

    public function releasedItemVersion(int $idPaket, int $idItem): ?int
    {
        $snapshot = $this->releasedSnapshot($idPaket);
        if (! $snapshot) {
            return null;
        }

        $item = collect($snapshot['items'])->first(fn ($row) => (int) ($row['Id_SOP_Paket_Item'] ?? 0) === $idItem);
        $version = $item['Id_SOP_Item_Versi'] ?? null;

        return $version ? (int) $version : null;
    }

    public function purgeUnreleasedDraftArtifacts(int $idPaket): void
    {
        if (! $this->available()) {
            return;
        }
        if ($this->hasOfficialRelease($idPaket)) {
            return;
        }

        $ids = DB::table(self::TABLE)
            ->where('Id_SOP_Paket', $idPaket)
            ->pluck('Id_SOP_Paket_Versi')
            ->all();

        if (! empty($ids)) {
            DB::table(self::ITEM_TABLE)->whereIn('Id_SOP_Paket_Versi', $ids)->delete();
            DB::table(self::TABLE)->whereIn('Id_SOP_Paket_Versi', $ids)->delete();
        }

        $update = ['Updated_At' => now()];
        if (SkemaTersedia::kolom('N_SOP_Master_Paket', 'Versi_Paket_Aktif')) {
            $update['Versi_Paket_Aktif'] = null;
        }
        if (SkemaTersedia::kolom('N_SOP_Master_Paket', 'Id_SOP_Paket_Versi_Aktif')) {
            $update['Id_SOP_Paket_Versi_Aktif'] = null;
        }
        if (count($update) > 1) {
            DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->update($update);
        }

        $this->lupakanMemo($idPaket);
    }

    public function release(
        int $idPaket,
        string $trigger = 'PAKET_PUBLISH',
        ?int $userId = null,
        ?int $idVersiPemicu = null,
        ?string $ringkasan = null,
        bool $activatePendingVersions = true,
    ): ?array {
        if (! $this->available()) {
            return null;
        }

        /*
         * Paket adalah satuan rilis UNTUK PERUBAHAN MILIK PAKET — susunan item,
         * urutan, wajib/tidak. Semua versi item DRAFT dipromosikan di sini,
         * tepat sebelum snapshot dibuat, dan tak ada item yang boleh menggeser
         * pointer aktifnya sendiri dari Builder.
         *
         * REVISI DOKUMEN TERKENDALI ADALAH PENGECUALIAN, dan itu disengaja
         * (keputusan produk 2026-08-10). Ia menggeser pointer di SEMUA paket
         * pemakainya seketika lewat fan-out, tanpa menunggu paketnya
         * diterbitkan ulang — sebab kalau menunggu, karyawan di paket yang
         * belum di-publish membaca revisi yang sudah dicabut.
         *
         * Akibat yang harus disadari: nomor rilis paket tak lagi menentukan
         * isinya sendiri. Untuk menjawab "apa yang dibaca karyawan pada tanggal
         * X", rilis paket harus dibaca bersama riwayat revisi dokumennya.
         */
        if ($activatePendingVersions) {
            $this->activatePendingItemVersions($idPaket, $userId);
        }

        $snapshot = $this->snapshot($idPaket);
        if ($snapshot->isEmpty()) {
            return null;
        }

        $hash = sha1(json_encode([
            'package' => $this->packageReleaseState($idPaket),
            'items' => $snapshot->values()->all(),
        ], JSON_UNESCAPED_UNICODE));
        $last = DB::table(self::TABLE)
            ->where('Id_SOP_Paket', $idPaket)
            ->orderByDesc('Versi_Ke')
            ->first(['Id_SOP_Paket_Versi', 'Versi_Ke', 'Snapshot_Hash']);

        if ($last && hash_equals((string) $last->Snapshot_Hash, $hash)) {
            return [
                'id' => (int) $last->Id_SOP_Paket_Versi,
                'versi' => (int) $last->Versi_Ke,
                'created' => false,
            ];
        }

        $version = $last ? ((int) $last->Versi_Ke + 1) : 1;
        $kodePublisher = Auth::user()?->karyawan?->Kode_Karyawan;
        $itemTotal = $snapshot->count();
        $itemWajib = $snapshot->where('Wajib', true)->count();

        $idVersion = DB::transaction(function () use ($idPaket, $version, $trigger, $ringkasan, $hash, $snapshot, $itemTotal, $itemWajib, $idVersiPemicu, $userId, $kodePublisher) {
            $id = DB::table(self::TABLE)->insertGetId([
                'Id_SOP_Paket' => $idPaket,
                'Versi_Ke' => $version,
                'Status' => SopStatus::PAKET_PUBLISHED,
                'Trigger_Perubahan' => mb_substr($trigger, 0, 30),
                'Ringkasan_Perubahan' => $ringkasan,
                'Snapshot_Hash' => $hash,
                'Snapshot_JSON' => json_encode($snapshot->values()->all(), JSON_UNESCAPED_UNICODE),
                'Item_Total' => $itemTotal,
                'Item_Wajib' => $itemWajib,
                'Id_SOP_Item_Versi_Pemicu' => $idVersiPemicu,
                'Published_By' => $userId,
                'Kode_Karyawan_Publisher' => $kodePublisher,
                'Tanggal_Publish' => now(),
                'Created_At' => now(),
            ], 'Id_SOP_Paket_Versi');

            foreach ($snapshot as $row) {
                DB::table(self::ITEM_TABLE)->insert([
                    'Id_SOP_Paket_Versi' => $id,
                    'Id_SOP_Paket_Item' => $row['Id_SOP_Paket_Item'],
                    'Id_SOP_Item_Versi' => $row['Id_SOP_Item_Versi'],
                    'Id_SOP_Section' => $row['Id_SOP_Section'],
                    'Urutan' => $row['Urutan'],
                    'Tipe_Item' => $row['Tipe_Item'],
                    'Judul_Item' => $row['Judul_Item'],
                    'Wajib' => $row['Wajib'] ? SopStatus::AKTIF : SopStatus::INAKTIF,
                    'Versi_Item' => $row['Versi_Item'],
                    'Status_Item_Versi' => $row['Status_Item_Versi'],
                    'Ringkasan_Perubahan' => $row['Ringkasan_Perubahan'],
                    'Created_At' => now(),
                ]);
            }

            $update = ['Updated_At' => now()];
            if (SkemaTersedia::kolom('N_SOP_Master_Paket', 'Versi_Paket_Aktif')) {
                $update['Versi_Paket_Aktif'] = $version;
            }
            if (SkemaTersedia::kolom('N_SOP_Master_Paket', 'Id_SOP_Paket_Versi_Aktif')) {
                $update['Id_SOP_Paket_Versi_Aktif'] = $id;
            }
            if (count($update) > 1) {
                DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->update($update);
            }

            return (int) $id;
        });

        SopLog::catat(
            'PAKET_VERSION',
            "Versi paket v{$version} dicatat ({$trigger}).",
            $idPaket,
            null,
            $idVersiPemicu,
            null,
            ['versi_paket' => $version, 'id_paket_versi' => $idVersion, 'oleh_id_users' => $userId],
        );

        // Rilis baru mengubah kedua jawaban yang di-memo: paket ini kini punya
        // rilis resmi, dan snapshotnya bukan lagi yang tadi dibaca.
        $this->lupakanMemo($idPaket);

        return ['id' => $idVersion, 'versi' => $version, 'created' => true];
    }

    private function snapshot(int $idPaket)
    {
        return DB::table('N_SOP_Paket_Item as i')
            ->leftJoin('N_SOP_Paket_Section as s', 's.Id_SOP_Section', '=', 'i.Id_SOP_Section')
            ->leftJoin('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
            ->leftJoin('N_LMS_Master_Konten as kn', 'kn.Id_Master_Konten', '=', 'v.Id_Master_Konten')
            ->leftJoin('Karyawan as pub', 'pub.Kode_Karyawan', '=', 'v.Kode_Karyawan_Publisher')
            ->where('i.Id_SOP_Paket', $idPaket)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('i.Urutan')
            ->get([
                'i.Id_SOP_Paket_Item',
                'i.Id_SOP_Section',
                's.Nama_Section as Section_Nama',
                's.Deskripsi as Section_Deskripsi',
                's.Urutan as Section_Urutan',
                'i.Urutan',
                'i.Tipe_Item',
                'i.Judul_Item',
                'i.Nomor_SOP_Resmi',
                'i.Deskripsi_Item',
                'i.Konten_Teks',
                'i.URL_Tautan',
                'i.Label_Tautan',
                'i.Mode_Akses',
                'i.Jenis_Quiz',
                'i.Nilai_Minimum',
                'i.Flag_Camera',
                'i.Flag_Wajib',
                'i.Flag_Highlight',
                'i.Flag_Watermark',
                'i.Id_Item_Versi_Aktif',
                'v.Versi_Ke',
                'v.Status as Status_Item_Versi',
                'kn.Judul_Konten as Konten_Nama',
                'kn.Tipe_Konten as Konten_Tipe',
                'v.Ringkasan_Perubahan',
                'v.Tanggal_Berlaku',
                'v.Disahkan_Oleh',
                'v.Jabatan_Pengesah',
                'v.Tanggal_Pengesahan',
                'v.Nomor_Pengesahan',
                'v.Kode_Karyawan_Publisher',
                'pub.Nama as Publisher_Nama',
            ])
            ->map(fn ($row) => [
                'Snapshot_Schema' => 2,
                'Id_SOP_Paket_Item' => (int) $row->Id_SOP_Paket_Item,
                'Id_SOP_Section' => $row->Id_SOP_Section ? (int) $row->Id_SOP_Section : null,
                'Section_Nama' => $row->Section_Nama,
                'Section_Deskripsi' => $row->Section_Deskripsi,
                'Section_Urutan' => $row->Section_Urutan ? (int) $row->Section_Urutan : null,
                'Urutan' => (int) $row->Urutan,
                'Tipe_Item' => $row->Tipe_Item,
                'Judul_Item' => $row->Judul_Item,
                'Nomor_SOP_Resmi' => $row->Nomor_SOP_Resmi,
                'Deskripsi_Item' => $row->Deskripsi_Item,
                'Konten_Teks' => $row->Konten_Teks,
                'URL_Tautan' => $row->URL_Tautan,
                'Label_Tautan' => $row->Label_Tautan,
                'Mode_Akses' => $row->Mode_Akses,
                'Jenis_Quiz' => $row->Jenis_Quiz,
                'Nilai_Minimum' => $row->Nilai_Minimum !== null ? (float) $row->Nilai_Minimum : null,
                'Flag_Camera' => $row->Flag_Camera,
                'Wajib' => $row->Flag_Wajib === SopStatus::AKTIF,
                'Flag_Highlight' => $row->Flag_Highlight,
                'Flag_Watermark' => $row->Flag_Watermark,
                'Id_SOP_Item_Versi' => $row->Id_Item_Versi_Aktif ? (int) $row->Id_Item_Versi_Aktif : null,
                /* !== null, BUKAN cek falsy: versi 00 itu sah sejak penomoran
                   dimulai dari nol, dan cek falsy mengubahnya jadi null —
                   snapshot rilis lalu mencatat "tanpa versi" untuk versi
                   yang jelas ada. */
                'Versi_Item' => $row->Versi_Ke !== null ? (int) $row->Versi_Ke : null,
                'Status_Item_Versi' => $row->Status_Item_Versi,
                'Konten_Nama' => $row->Konten_Nama,
                'Konten_Tipe' => $row->Konten_Tipe,
                'Ringkasan_Perubahan' => $row->Ringkasan_Perubahan,
                'Tanggal_Berlaku' => $row->Tanggal_Berlaku,
                'Disahkan_Oleh' => $row->Disahkan_Oleh,
                'Jabatan_Pengesah' => $row->Jabatan_Pengesah,
                'Tanggal_Pengesahan' => $row->Tanggal_Pengesahan,
                'Nomor_Pengesahan' => $row->Nomor_Pengesahan,
                'Kode_Karyawan_Publisher' => $row->Kode_Karyawan_Publisher,
                'Publisher_Nama' => $row->Publisher_Nama,
            ]);
    }

    private function activatePendingItemVersions(int $idPaket, ?int $userId): void
    {
        $pending = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'v.Id_SOP_Paket_Item')
            ->where('i.Id_SOP_Paket', $idPaket)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('v.Flag_Aktif', SopStatus::AKTIF)
            ->where('v.Status', SopStatus::VERSI_READY)
            ->orderBy('v.Id_SOP_Paket_Item')
            ->orderByDesc('v.Versi_Ke')
            ->get(['v.Id_SOP_Item_Versi', 'v.Id_SOP_Paket_Item']);

        foreach ($pending->unique('Id_SOP_Paket_Item') as $versi) {
            DB::table('N_SOP_Item_Versi')
                ->where('Id_SOP_Paket_Item', $versi->Id_SOP_Paket_Item)
                ->where('Status', SopStatus::VERSI_PUBLISHED)
                ->update(['Status' => 'OBSOLETE', 'Updated_By' => $userId, 'Updated_At' => now()]);

            DB::table('N_SOP_Item_Versi')
                ->where('Id_SOP_Paket_Item', $versi->Id_SOP_Paket_Item)
                ->where('Status', SopStatus::VERSI_READY)
                ->where('Id_SOP_Item_Versi', '<>', $versi->Id_SOP_Item_Versi)
                ->update(['Status' => 'OBSOLETE', 'Updated_By' => $userId, 'Updated_At' => now()]);

            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $versi->Id_SOP_Item_Versi)->update([
                'Status' => SopStatus::VERSI_PUBLISHED,
                'Tanggal_Publish' => now(),
                'Kode_Karyawan_Publisher' => Auth::user()?->karyawan?->Kode_Karyawan,
                'Updated_By' => $userId,
                'Updated_At' => now(),
            ]);

            DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $versi->Id_SOP_Paket_Item)->update([
                'Id_Item_Versi_Aktif' => $versi->Id_SOP_Item_Versi,
                'Updated_By' => $userId,
                'Updated_At' => now(),
            ]);
        }
    }

    private function packageReleaseState(int $idPaket): ?object
    {
        return DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->first([
            'Kode_Paket', 'Nama_Paket', 'Kode_Karyawan_Owner',
            'Deskripsi', 'Tujuan_Ruang_Lingkup', 'Prioritas', 'Estimasi_Waktu_Baca_Menit',
            'Mode_Urutan_Item', 'Mode_Distribusi', 'Cover_Path', 'Cover_Position', 'Flag_Absensi',
        ]);
    }

    private function snapshotRowChanged(array $previous, array $current): bool
    {
        /*
         * Backfill awal menyimpan snapshot schema v1 yang lebih tipis dari schema v2.
         * Untuk paket lama, bandingkan field yang memang ada di release sebelumnya
         * supaya admin tidak melihat pending changes palsu hanya karena format snapshot
         * aplikasi sekarang lebih lengkap.
         */
        unset($previous['Snapshot_Schema'], $current['Snapshot_Schema']);
        $keys = array_unique(array_merge(array_keys($previous), array_keys($current)));
        $previousKeys = array_keys($previous);
        if (! empty($previousKeys)) {
            $keys = $previousKeys;
        }

        $previous = array_intersect_key($previous, array_flip($keys));
        $current = array_intersect_key($current, array_flip($keys));

        ksort($previous);
        ksort($current);

        return json_encode($previous, JSON_UNESCAPED_UNICODE) !== json_encode($current, JSON_UNESCAPED_UNICODE);
    }

    private function changedAfterRelease(string $table, string $key, int $id, ?Carbon $publishedAt): bool
    {
        if (! $publishedAt || ! SkemaTersedia::kolom($table, 'Updated_At')) {
            return false;
        }

        $updatedAt = DB::table($table)->where($key, $id)->value('Updated_At');
        if (! $updatedAt) {
            return false;
        }

        return Carbon::parse($updatedAt)->gt($publishedAt->copy()->addSeconds(5));
    }

    private function sectionUpdatesAfterRelease(int $idPaket, ?Carbon $publishedAt): int
    {
        if (! $publishedAt || ! SkemaTersedia::kolom('N_SOP_Paket_Section', 'Updated_At')) {
            return 0;
        }

        return DB::table('N_SOP_Paket_Section')
            ->where('Id_SOP_Paket', $idPaket)
            ->where('Updated_At', '>', $publishedAt->copy()->addSeconds(5))
            ->count();
    }

    /**
     * Dua pertanyaan yang sama, ditanyakan ribuan kali dalam satu jalur.
     *
     * Diukur 9 September 2026 pada SATU uji dengan dua asersi: 5.686 kueri,
     * dan 5.660 di antaranya cuma dua pertanyaan ini — masing-masing 2.830 kali,
     * 155 detik dari total 157. Sebabnya SopSequenceGate memanggil
     * releasedSnapshot() SEKALI PER ITEM, dan releasedSnapshot() memanggil
     * hasOfficialRelease() lebih dulu. Satu paket 40 item yang digerbangi untuk
     * 35 karyawan sudah 2.800 kueri, dan jawabannya identik semuanya.
     *
     * Memoisasi ini terikat UMUR INSTANCE, bukan statik. Cache statik akan
     * bertahan lintas job di worker antrean dan menjawab dengan keadaan yang
     * sudah lama berubah; yang terikat instance mati bersama instance-nya.
     * Karena itu pemanggil di jalur panas WAJIB berbagi instance lewat
     * container (binding scoped), bukan `new SopPaketVersionService` — yang
     * terakhir membuat memo ini tak pernah kena.
     *
     * Kedua jalur tulis di kelas ini (release, purgeUnreleasedDraftArtifacts)
     * membatalkan memonya untuk paket yang bersangkutan.
     *
     * @var array<int,bool>
     */
    private array $memoRilisResmi = [];

    /** @var array<int,array|null> */
    private array $memoSnapshotRilis = [];

    /** Dipanggil tiap kali kelas ini mengubah rilis sebuah paket. */
    private function lupakanMemo(int $idPaket): void
    {
        unset($this->memoRilisResmi[$idPaket], $this->memoSnapshotRilis[$idPaket]);
    }

    private function hasOfficialRelease(int $idPaket): bool
    {
        return $this->memoRilisResmi[$idPaket] ??= DB::table('N_SOP_Master_Paket')
            ->where('Id_SOP_Paket', $idPaket)
            ->whereNotNull('Tanggal_Publish')
            ->exists();
    }
}
