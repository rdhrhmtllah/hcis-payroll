# SOP Builder: Toggle Watermark per Item Dokumen

Status: Approved
Tanggal: 2026-08-07
Branch: feat/watermark-dynamic-doc

## Latar Belakang

PDF Reader SOP (`SopPdfReader.vue`) saat ini SELALU membubuhkan watermark
(nama pembaca, IP tersamar, tanggal, "Property of Evo Group") pada setiap
halaman dokumen `DOKUMEN` yang dibaca, dibakar server-side via GD
(`SecurePdfPageRenderService::applyWatermarkGd`). Tidak ada cara mematikannya
per dokumen — `SopSecurePdfController::buildWatermark()` dipanggil tanpa
syarat untuk setiap token PDF SOP.

Admin butuh kontrol untuk menyalakan/mematikan watermark per item dokumen
di SOP Builder (mis. dokumen internal yang tidak sensitif tidak perlu
watermark, tapi dokumen kebijakan HR tetap perlu).

Modul LMS Training sudah punya pola serupa: `Flag_Download` pada
`N_LMS_Materi_Det` menentukan `shouldApplySecurePdfWatermark()` — ini jadi
acuan pola untuk SOP, meski field & letaknya berbeda (SOP butuh toggle
watermark yang independen dari izin unduh, bukan reuse `Mode_Akses`).

## Keputusan Desain

**Scope: per item, bukan global.** Tiap item `DOKUMEN` di SOP Builder
punya toggle watermark sendiri — konsisten dengan toggle lain yang sudah
ada di editor item (`Flag_Wajib`, `Flag_Highlight`, `Flag_Camera`).

**Bukan reuse `Mode_Akses`.** `Mode_Akses` (VIEW_ONLY/VIEW_UNDUH) hanya
mengatur endpoint download mentah terpisah (`SopRepositoryController`) dan
sama sekali tidak terhubung ke watermark saat ini. Watermark tetap field
independen agar admin bisa, misalnya, mengizinkan unduh SEKALIGUS tetap
mewatermark, atau sebaliknya.

**Bukan disimpan di level konten library (`Id_Master_Konten` /
`N_LMS_Master_Konten_Berkas`).** Satu file konten library bisa dipakai
ulang oleh beberapa item SOP di paket berbeda, dan tabel yang sama dipakai
modul LMS Training. Menaruh toggle di level konten akan membuat perubahan
di satu SOP diam-diam memengaruhi paket/pembaca lain yang memakai file
sama. Toggle harus melekat ke `N_SOP_Paket_Item`, bukan ke filenya.

## Perubahan Skema

Tambah kolom `Flag_Watermark CHAR(1) NOT NULL DEFAULT ('Y')` — konvensi
Y/T sama seperti `Flag_Wajib`/`Flag_Camera`/`Flag_Highlight` — di dua
tabel:

```sql
-- N_SOP_Paket_Item (item aktif/published)
IF COL_LENGTH('dbo.N_SOP_Paket_Item','Flag_Watermark') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Paket_Item
        ADD Flag_Watermark CHAR(1) NOT NULL DEFAULT ('Y');
END;
GO

-- N_SOP_Paket_Draft_Item (working draft — diedit admin sebelum publish)
IF COL_LENGTH('dbo.N_SOP_Paket_Draft_Item','Flag_Watermark') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Paket_Draft_Item
        ADD Flag_Watermark CHAR(1) NOT NULL DEFAULT ('Y');
END;
GO
```

Default `'Y'` (watermark aktif) untuk seluruh baris lama maupun baru —
tidak ada perubahan perilaku sampai admin sengaja mematikannya di item
tertentu.

Kolom ini hanya bermakna untuk `Tipe_Item = 'DOKUMEN'`. Tidak dipakai
untuk `FORM`/`VIDEO`, tapi tetap ada di baris tabel (sama seperti
`Flag_Camera` yang cuma relevan untuk `KUIS`).

**Di luar cakupan:** `N_SOP_Paket_Versi_Item` (snapshot riwayat rilis)
TIDAK ikut ditambah kolom ini. Riwayat rilis tidak perlu mencatat status
watermark per versi untuk kebutuhan sekarang.

## Perubahan Backend — Jalur Simpan (Builder → Draft → Publish)

Mengikuti pola `Flag_Wajib`/`Flag_Highlight` yang sudah ada, field baru
dijahit ke titik berikut:

1. `SopAdminBuilderController::validateItemData()` — tambah rule
   `'Flag_Watermark' => 'nullable|boolean'`.
2. `SopItemService::create()`, `update()`, `cloneRow()` — tambah
   `'Flag_Watermark' => ($data['Flag_Watermark'] ?? true) ? 'Y' : 'T'`
   (default ON kalau tidak dikirim), mirror baris `Flag_Wajib`/
   `Flag_Highlight` yang sudah ada di masing-masing method.
3. `SopPaketDraftService` — tambahkan `Flag_Watermark` ke:
   - konstanta `ITEM_COLUMNS`
   - setiap list field draft↔aktif (bikin draft dari item lama, simpan
     draft, publish draft → aktif, clone item/section/paket)
   - label diff (`'Flag_Watermark' => 'Watermark'`) supaya perubahan
     toggle ini muncul di ringkasan "Catatan rilis" saat publish.
4. Publish draft → aktif otomatis membawa nilai ini asal langkah 3 benar
   (mekanisme copy field yang sudah ada, tidak perlu logic baru).

## Perubahan Backend — Penegakan Saat Baca Dokumen

Bagian paling krusial: `SopSecurePdfController::token()` saat ini hanya
menerima `berkasId` — tidak tahu ITEM mana yang sedang dibuka. Karena satu
`Id_Master_Konten` bisa dipakai ulang oleh beberapa item/paket, keputusan
watermark tidak boleh diresolusi murni dari `berkasId`.

- **FE** (`SopPdfReader.vue::handshake()`) mengirim `idItem` (prop yang
  sudah ada) ke `POST /api/v1/sop/secure-pdf/token/{berkasId}`.
- **Backend** `SopSecurePdfController::token()` memakai `idItem` untuk
  ambil `Flag_Watermark` langsung dari baris `N_SOP_Paket_Item` milik item
  tsb (bukan join ambigu lewat `berkasId` saja) — query ini dijalankan
  berbarengan dengan alur otorisasi yang sudah ada
  (`SopSecurePdfAccessService::canAccess`), yang memang sudah menelusuri
  item→paket→penugasan user.
- **Fail-safe:** kalau `idItem` tidak dikirim/tidak valid (link lama,
  cache FE basi), watermark tetap ON. Default aman, mirror
  `resolveActivityFlagDownload()` di LMS yang jatuh ke `'T'` saat konteks
  tidak jelas.
- `buildWatermark()` di-skip (return array kosong) kalau
  `Flag_Watermark === 'T'` untuk item tsb. Tidak perlu ubah
  `SecurePdfPageRenderService` — `applyWatermarkGd()` sudah menangani
  `$lines === []` dengan mengembalikan gambar bersih tanpa watermark.

**Isolasi antar modul:** mematikan watermark di satu item SOP TIDAK
memengaruhi watermark LMS Training meski kebetulan memakai file source
yang sama, karena keputusan diambil dari `N_SOP_Paket_Item.Flag_Watermark`
(spesifik SOP), bukan dari tabel konten yang dipakai bersama.

## Perubahan UI — SOP Builder

Toggle baru di `resources/js/Pages/SOP/components/ui/ItemFields.vue`, di
blok `Tipe_Item === 'DOKUMEN'` (baris ~26-40), di bawah kontrol "Hak akses
berkas" (`Mode_Akses`) yang sudah ada — pola UI sama (segmented button):

```
Watermark keamanan
[● Aktif]  [○ Nonaktif]
"Saat aktif, tiap halaman dibubuhi watermark nama pembaca, IP, dan tanggal."
```

Tampil hanya untuk `Tipe_Item === 'DOKUMEN'` (sama seperti `Mode_Akses`
sekarang). Form model (`itemForm.Flag_Watermark`) mengikuti konvensi
`Y`/`T` yang sama dengan `Flag_Wajib`.

## Testing

- Item baru default `Flag_Watermark = 'Y'` tanpa disentuh admin.
- Toggle OFF di Builder → publish → baca dokumen di reader → halaman
  bersih tanpa watermark.
- Toggle tetap ON (default) untuk item lama yang belum pernah disentuh →
  tidak ada regresi.
- Item yang sama Id_Master_Konten dipakai 2 item berbeda dengan setting
  watermark berbeda → masing-masing menghormati setting itemnya sendiri
  (bukan setting item lain).
- `idItem` hilang/tidak valid di request token → fail-safe watermark ON.
- Draft → discard tanpa publish → `Flag_Watermark` di tabel aktif tidak
  berubah (isolasi draft vs aktif tetap terjaga, sama seperti field lain).
