<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopAksesBerkas;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Service Item Paket SOP (Builder — level item).
 * CRUD item 7 tipe + urutan (drag-drop). Berkas/versi ditangani service lain.
 */
class SopItemService
{
    private string $table = 'N_SOP_Paket_Item';

    /** Semua item dalam satu paket (urut). Id encoded. */
    public function byPaket(int $idPaket)
    {
        return DB::table("{$this->table} as i")
            ->leftJoin('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'i.Id_SOP_Dokumen')
            ->leftJoin('N_HRIS_Master_Departemen as dpt', 'dpt.Id_Departemen', '=', 'd.Departemen_Id')
            ->leftJoin('N_SOP_Master_Jenis_Dokumen as jd', 'jd.Id_SOP_Jenis_Dokumen', '=', 'd.Jenis_Dokumen_Id')
            ->leftJoin('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'd.Id_Versi_Aktif')
            ->where('i.Id_SOP_Paket', $idPaket)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('i.Urutan')
            /* BERAPA PAKET LAIN yang menyajikan dokumen yang sama.
               Angka ini yang membuat peringatan "revisinya berlaku di semua
               paket" berhenti jadi kalimat umum: satu paket dan sebelas paket
               dibaca sama persis kalau jumlahnya tak disebut. Subquery, bukan
               join — join akan menggandakan barisnya dan urutan item ikut kacau. */
            ->selectRaw('i.*, dpt.Nama_Departemen, jd.Nama_Jenis, v.Versi_Ke as Versi, v.Versi_Ke as Versi_String, (
                SELECT COUNT(DISTINCT x.Id_SOP_Paket)
                FROM N_SOP_Paket_Item x
                WHERE x.Id_SOP_Dokumen = i.Id_SOP_Dokumen
                  AND x.Id_SOP_Paket <> i.Id_SOP_Paket
                  AND x.Flag_Aktif = ?
            ) as Paket_Lain_Pemakai', [SopStatus::AKTIF])
            ->get()
            ->map(function ($item) {
                $item->Id_SOP_Paket_Item = Hashids::connection('custom')->encode($item->Id_SOP_Paket_Item);
                $item->Id_SOP_Paket = Hashids::connection('custom')->encode($item->Id_SOP_Paket);
                if (! empty($item->Id_Item_Versi_Aktif)) {
                    $item->Id_Item_Versi_Aktif = Hashids::connection('custom')->encode($item->Id_Item_Versi_Aktif);
                }
                if (! empty($item->Id_SOP_Section)) {
                    $item->Id_SOP_Section = Hashids::connection('custom')->encode($item->Id_SOP_Section);
                }
                /* WAJIB di-encode seperti id lain. Form Builder mengirim balik
                   nilai ini apa adanya, dan terapkanDokumen() men-decode-nya
                   sebagai Hashid — id mentah akan gagal decode dan membuat
                   SETIAP simpan item ber-dokumen dibalas 422. */
                if (! empty($item->Id_SOP_Dokumen)) {
                    $item->Id_SOP_Dokumen = Hashids::connection('custom')->encode($item->Id_SOP_Dokumen);
                }

                return $item;
            });
    }

    /** Tambah item. Urutan otomatis di akhir. Return Id (int). */
    public function create(int $idPaket, array $data, int $userId): int
    {
        $maxUrutan = DB::table($this->table)
            ->where('Id_SOP_Paket', $idPaket)->max('Urutan') ?? 0;

        return DB::table($this->table)->insertGetId([
            'Id_SOP_Paket' => $idPaket,
            'Id_SOP_Section' => $data['Id_SOP_Section'] ?? null,
            'Tipe_Item' => $data['Tipe_Item'],
            'Judul_Item' => $data['Judul_Item'],
            'Nomor_SOP_Resmi' => $data['Nomor_SOP_Resmi'] ?? null,
            // Fase 6: dokumen terpilih di Builder. Identitas dua baris di atas
            // sudah diambil dari dokumen itu oleh terapkanDokumen().
            'Id_SOP_Dokumen' => $data['Id_SOP_Dokumen'] ?? null,
            'Deskripsi_Item' => $data['Deskripsi_Item'] ?? null,
            'Konten_Teks' => $data['Konten_Teks'] ?? null,
            'Mode_Akses' => $data['Mode_Akses'] ?? null,
            'URL_Tautan' => $data['URL_Tautan'] ?? null,
            'Label_Tautan' => $data['Label_Tautan'] ?? null,
            'Jenis_Quiz' => $data['Jenis_Quiz'] ?? null,
            'Nilai_Minimum' => $data['Nilai_Minimum'] ?? null,
            'Id_Master_Ujian' => $data['Id_Master_Ujian'] ?? null,
            'Nama_Ujian' => $data['Nama_Ujian'] ?? null,
            'Flag_Camera' => ($data['Flag_Camera'] ?? null) === 'Y' ? 'Y' : 'T',
            'Urutan' => $maxUrutan + 1,
            'Flag_Wajib' => ($data['Flag_Wajib'] ?? true) ? 'Y' : 'T',
            'Flag_Highlight' => ($data['Flag_Highlight'] ?? false) ? 'Y' : 'T',
            'Flag_Watermark' => SopAksesBerkas::watermarkTersimpan($data['Flag_Watermark'] ?? null, $data['Mode_Akses'] ?? null),
            'Created_By' => $userId,
            'Created_At' => now(),
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Paket_Item');
    }

    /**
     * Tautkan item ke sebuah Dokumen SOP: pointer versi aktif + cerminan identitas.
     *
     * Dipakai DUA pemanggil — penyalinan item dan pembuatan item di Builder.
     * Sengaja tidak digandakan: pointer yang keliru di salah satu jalur berarti
     * karyawan disajikan versi yang belum sah, dan cacat seperti itu tak terlihat
     * di layar mana pun sampai ada yang membuka dokumennya.
     *
     * Pointer hanya diisi bila versi aktif dokumen berstatus PUBLISHED —
     * menunjuk DRAFT berarti menyajikan versi yang belum disahkan. Dokumen yang
     * belum punya versi terbit menyisakan pointer NULL, dan itu keadaan yang
     * SAH: itemnya memang belum menyajikan apa pun sampai versinya terbit.
     *
     * Ujian ikut dicerminkan ke kolom item supaya daftar Builder tak menampilkan
     * item KUIS yang seolah belum menautkan ujian. Sumber kebenarannya tetap
     * baris versi — itulah yang dibaca SopKuisDispatchService.
     */
    public function tautkanDokumen(int $idItem, int $idDokumen, int $userId): void
    {
        $versi = DB::table('N_SOP_Dokumen as d')
            ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'd.Id_Versi_Aktif')
            ->where('d.Id_SOP_Dokumen', $idDokumen)
            ->where('v.Status', SopStatus::PAKET_PUBLISHED)
            ->first(['v.Id_SOP_Item_Versi', 'v.Id_Master_Ujian', 'v.Nama_Ujian'])
            ?? DB::table('N_SOP_Item_Versi')
                ->where('Id_SOP_Dokumen', $idDokumen)
                ->where('Status', SopStatus::PAKET_PUBLISHED)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->orderByDesc('Versi_Ke')
                ->first(['Id_SOP_Item_Versi', 'Id_Master_Ujian', 'Nama_Ujian']);

        DB::table($this->table)->where('Id_SOP_Paket_Item', $idItem)->update([
            'Id_SOP_Dokumen' => $idDokumen,
            'Id_Item_Versi_Aktif' => $versi->Id_SOP_Item_Versi ?? null,
            'Id_Master_Ujian' => $versi->Id_Master_Ujian ?? null,
            'Nama_Ujian' => $versi->Nama_Ujian ?? null,
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);

        // Identitas ikut dokumennya, bukan judul yang diketik di form item.
        app(SopDokumenService::class)->sinkronIdentitasKeItem($idDokumen, $userId);
    }

    /** Update item. */
    public function update(int $id, array $data, int $userId): void
    {
        DB::table($this->table)->where('Id_SOP_Paket_Item', $id)->update([
            'Judul_Item' => $data['Judul_Item'],
            'Nomor_SOP_Resmi' => $data['Nomor_SOP_Resmi'] ?? null,
            /* Fase 6. Dikirim hanya bila form menyertakan dokumen; kalau tidak,
               tautan lama DIPERTAHANKAN — menimpanya dengan null akan memutus
               item dari dokumennya diam-diam pada setiap penyuntingan biasa. */
            'Id_SOP_Dokumen' => $data['Id_SOP_Dokumen']
                ?? DB::table($this->table)->where('Id_SOP_Paket_Item', $id)->value('Id_SOP_Dokumen'),
            'Deskripsi_Item' => $data['Deskripsi_Item'] ?? null,
            'Konten_Teks' => $data['Konten_Teks'] ?? null,
            'Mode_Akses' => $data['Mode_Akses'] ?? null,
            'URL_Tautan' => $data['URL_Tautan'] ?? null,
            'Label_Tautan' => $data['Label_Tautan'] ?? null,
            'Jenis_Quiz' => $data['Jenis_Quiz'] ?? null,
            'Nilai_Minimum' => $data['Nilai_Minimum'] ?? null,
            'Id_Master_Ujian' => $data['Id_Master_Ujian'] ?? null,
            'Nama_Ujian' => $data['Nama_Ujian'] ?? null,
            'Flag_Camera' => ($data['Flag_Camera'] ?? null) === 'Y' ? 'Y' : 'T',
            'Flag_Wajib' => ($data['Flag_Wajib'] ?? true) ? 'Y' : 'T',
            'Flag_Highlight' => ($data['Flag_Highlight'] ?? false) ? 'Y' : 'T',
            'Flag_Watermark' => SopAksesBerkas::watermarkTersimpan($data['Flag_Watermark'] ?? null, $data['Mode_Akses'] ?? null),
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);
    }

    /**
     * Simpan ulang urutan + (opsional) pindah section (drag-drop).
     *
     * @param  array  $ordered  daftar baris ['id'=>int, 'section'=>?int] dalam urutan global baru
     */
    public function reorder(array $ordered): void
    {
        foreach ($ordered as $i => $row) {
            $payload = ['Urutan' => $i + 1];
            if (array_key_exists('section', $row)) {
                $payload['Id_SOP_Section'] = $row['section'];
            }
            DB::table($this->table)
                ->where('Id_SOP_Paket_Item', $row['id'])
                ->update($payload);
        }
    }

    /** Soft delete item. */
    public function softDelete(int $id, int $userId): void
    {
        DB::table($this->table)->where('Id_SOP_Paket_Item', $id)->update([
            'Flag_Aktif' => SopStatus::INAKTIF,
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);
    }

    /** Validasi tipe item valid. */
    public function tipeValid(string $tipe): bool
    {
        return in_array($tipe, SopTipeItem::SEMUA, true);
    }

    /**
     * Salin baris item ke paket/section tujuan (di akhir urutan). Menyalin versi aktif utk item file.
     * Return id item baru.
     */
    public function cloneRow(
        object $src,
        int $newPaket,
        ?int $newSection,
        int $userId,
        SopItemVersionService $versiSvc,
        bool $berbagiDokumen = false,
    ): int {
        $maxUrutan = DB::table($this->table)->where('Id_SOP_Paket', $newPaket)->max('Urutan') ?? 0;

        $newId = DB::table($this->table)->insertGetId([
            'Id_SOP_Paket' => $newPaket,
            'Id_SOP_Section' => $newSection,
            'Tipe_Item' => $src->Tipe_Item,
            'Judul_Item' => $src->Judul_Item,
            'Nomor_SOP_Resmi' => $src->Nomor_SOP_Resmi,
            'Deskripsi_Item' => $src->Deskripsi_Item,
            'Konten_Teks' => $src->Konten_Teks,
            'Mode_Akses' => $src->Mode_Akses,
            'URL_Tautan' => $src->URL_Tautan,
            'Label_Tautan' => $src->Label_Tautan,
            'Jenis_Quiz' => $src->Jenis_Quiz,
            'Nilai_Minimum' => $src->Nilai_Minimum,
            'Id_Master_Ujian' => $src->Id_Master_Ujian,
            'Nama_Ujian' => $src->Nama_Ujian,
            'Flag_Camera' => $src->Flag_Camera ?? 'T',
            'Urutan' => $maxUrutan + 1,
            'Flag_Wajib' => $src->Flag_Wajib,
            'Flag_Highlight' => $src->Flag_Highlight,
            'Flag_Watermark' => $src->Flag_Watermark ?? 'Y',
            'Created_By' => $userId,
            'Created_At' => now(),
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Paket_Item');

        if (SopTipeItem::isVersioned($src->Tipe_Item)) {
            if ($berbagiDokumen && ($src->Id_SOP_Dokumen ?? null) !== null) {
                /* FASE 6 — BERBAGI (keputusan produk user 2026-08-10).
                   Item salinan menunjuk dokumen yang SAMA, sehingga revisi di
                   paket mana pun menyebar ke semuanya lewat fan-out Fase 3.

                   TIDAK ADA BARIS VERSI BARU yang dibuat. Item salinan mewarisi
                   seluruh riwayat versi dokumennya; menulis Versi_Ke=1 pada
                   dokumen yang sudah punya versi 1 akan ditolak unique index
                   terfilter 26B dengan error 2601.

                   Pointer hanya diisi bila versi aktif dokumen berstatus
                   PUBLISHED — menunjuk DRAFT berarti menyajikan versi yang
                   belum sah ke karyawan. */
                $this->tautkanDokumen($newId, (int) $src->Id_SOP_Dokumen, $userId);
            } else {
                /* Duplikat di paket yang SAMA (dan jalur clone paket) tetap
                   mendapat dokumen SENDIRI. Keputusan produk hanya menyebut
                   salin ke paket LAIN, dan dua item berbagi dokumen di dalam
                   satu paket memunculkan pertanyaan yang belum terjawab: rollup
                   hanya menyimpan satu baris angka per (paket, versi). */
                app(SopDokumenService::class)->ensureForItem($newId, $userId);
                $versiSvc->cloneActiveTo((int) $src->Id_SOP_Paket_Item, $newId, $userId);
            }
        }

        return $newId;
    }

    /** Salin satu item by id (endpoint clone item) — di section yang sama, judul +" (Salinan)". Return id/null. */
    public function cloneById(int $itemId, int $userId, SopItemVersionService $versiSvc): ?int
    {
        $src = DB::table($this->table)->where('Id_SOP_Paket_Item', $itemId)->where('Flag_Aktif', SopStatus::AKTIF)->first();
        if (! $src) {
            return null;
        }
        $src->Judul_Item = mb_substr((string) ($src->Judul_Item ?? 'Item'), 0, 190).' (Salinan)';

        return $this->cloneRow($src, (int) $src->Id_SOP_Paket, $src->Id_SOP_Section ? (int) $src->Id_SOP_Section : null, $userId, $versiSvc);
    }

    /**
     * Salin item ke PAKET LAIN (beda dari paket asalnya) — dipakai fitur "Salin ke
     * paket lain" di Builder. Judul TIDAK diubah (beda konteks paket, bukan
     * duplikat berdampingan di paket yang sama seperti cloneById()). Item ABSENSI
     * ditolak: itu toggle level-paket, bukan baris berdiri sendiri (aturan sama
     * dengan storeItem()). Pemanggil WAJIB sudah memvalidasi $newPaket berstatus
     * DRAFT dan $newSection benar-benar milik $newPaket sebelum memanggil ini.
     */
    public function copyToPaket(int $itemId, int $newPaket, int $newSection, int $userId, SopItemVersionService $versiSvc): ?int
    {
        $src = DB::table($this->table)->where('Id_SOP_Paket_Item', $itemId)->where('Flag_Aktif', SopStatus::AKTIF)->first();
        if (! $src) {
            return null;
        }
        if ($src->Tipe_Item === SopTipeItem::ABSENSI) {
            return null;
        }

        // BERBAGI dokumen (keputusan produk user 2026-08-10): salinan tetap
        // seiring dengan sumbernya, revisi menyebar ke semua paket perujuk.
        return $this->cloneRow($src, $newPaket, $newSection, $userId, $versiSvc, berbagiDokumen: true);
    }

    /**
     * Ubah tipe item (menjaga integritas versi):
     *  - non-file → non-file: update kolom saja.
     *  - non-file → file: butuh $idKonten; buat versi v1 sebagai DRAFT rilis paket.
     *  - file → non-file: nonaktifkan semua versi + kosongkan pointer.
     *  - file → file: pertahankan versi (validasi konten dilakukan di controller).
     * $extra = payload versi (Id_Master_Konten, tanggal, pengesahan) bila menuju file.
     */
    public function changeType(int $itemId, string $newTipe, ?int $idKonten, array $extra, int $userId, SopItemVersionService $versiSvc): void
    {
        $src = DB::table($this->table)->where('Id_SOP_Paket_Item', $itemId)->first();
        if (! $src) {
            return;
        }
        $wasFile = SopTipeItem::isFile($src->Tipe_Item);
        $nowFile = SopTipeItem::isFile($newTipe);

        DB::table($this->table)->where('Id_SOP_Paket_Item', $itemId)
            ->update(['Tipe_Item' => $newTipe, 'Updated_By' => $userId, 'Updated_At' => now()]);

        if (! $wasFile && $nowFile) {
            $versiId = $versiSvc->create($itemId, array_merge($extra, ['Id_Master_Konten' => $idKonten]), $userId);
        } elseif ($wasFile && ! $nowFile) {
            $versiSvc->deactivateAll($itemId, $userId);
        }
        // file→file & non-file→non-file: tidak ada perubahan versi.
    }
}
