# Master Dokumen SOP — Dokumen Terkendali Bersama Lintas Paket

Status: **rancangan, menunggu persetujuan**
Tanggal: 2026-08-07

---

## Latar Belakang

Hari ini sebuah "dokumen SOP" tidak punya wujud sendiri di basis data. Yang ada
adalah **item di dalam paket** (`N_SOP_Paket_Item`) yang memiliki rantai versinya
sendiri (`N_SOP_Item_Versi`). Seluruh identitas legal dokumen — nomor pengesahan,
pengesah, tanggal berlaku, tanggal kadaluarsa, jadwal review — menempel di versi
item itu.

Akibatnya, satu dokumen fisik yang dipakai di banyak paket tersimpan sebagai
banyak salinan tata kelola yang berdiri sendiri, dan **sudah menyimpang di data
produksi saat ini**:

| Dokumen (berkas) | Sebaran | Kondisi terukur |
|---|---|---|
| konten#80 | 15 item di **8 paket** | `Tanggal_Berlaku` & `Tanggal_Kadaluarsa` punya 2 nilai berbeda |
| konten#173 | 2 item di 2 paket | `Nomor_Pengesahan`, `Tanggal_Berlaku`, **dan** `Disahkan_Oleh` semua berbeda |
| konten#172 | 4 item di 2 paket | masih konsisten |

Gejala yang sudah terlihat di layar audit (register kontrol dokumen,
`SopDocumentControlService`) — dokumen yang sama persis dilaporkan dengan status
kepatuhan yang saling bertentangan:

```
konten#80 — KONTRADIKTIF (13 item aktif)
  [KADALUARSA        ] 2026-07-29  paket: SOP Uji Coba Builder
  [TANPA_MASA_BERLAKU] (kosong)    paket: SOP Perjalanan Dinas
  [TANPA_MASA_BERLAKU] (kosong)    paket: SOP Meeting          … +10 lagi
```

Register itu menghitung per **versi item aktif** (`baseVersiAktif()`), sehingga
satu dokumen dihitung 13 kali. Untuk sistem SOP terkendali, ini cacat integritas
kepatuhan — bukan sekadar ketidaknyamanan admin.

Pemicu langsungnya: fitur "Salin item ke paket lain" yang baru dibangun. Berkasnya
memang sudah dipakai bersama (salinan menunjuk `Id_Master_Konten` yang sama), tapi
tata kelolanya tersalin sebagai *snapshot* yang langsung hidup sendiri — dan
revisi di paket asal tidak pernah menyusul ke salinannya.

## Keputusan Produk (sudah disepakati)

1. **Dokumen tetap sinkron selamanya (tautan hidup).** Satu dokumen dikelola di
   satu tempat; revisi berlaku otomatis di semua paket yang memakainya. Bukan
   snapshot sekali-salin.
2. **Kewajiban baca tetap per paket.** Karyawan yang sudah membaca dokumen di
   Paket A tetap harus mengkonfirmasi di Paket B. Bukti kepatuhan terikat pada
   konteks penugasannya, dan seluruh laporan/monitoring per-paket tetap utuh.
   → `N_SOP_Progress` **tidak** perlu dibongkar.

## Temuan yang Menentukan Bentuk Rancangan

Inventaris field memberi hasil yang sangat bersih:

> **Tidak ada satu pun kolom di `N_SOP_Item_Versi` yang benar-benar milik paket —
> kecuali `Id_SOP_Paket_Item` itu sendiri.**

Semua isinya adalah kebenaran dokumen (berkas, pengesahan, masa berlaku, nomor
versi, ringkasan perubahan). Sebaliknya seluruh setelan per-paket sudah rapi di
`N_SOP_Paket_Item` (`Urutan`, `Id_SOP_Section`, `Flag_Wajib`, `Flag_Highlight`,
`Mode_Akses`).

Artinya inti refactor ini adalah **memindahkan satu foreign key**, bukan
membongkar modul.

## Rancangan Skema

**Entitas baru `N_SOP_Dokumen`** — dokumen terkendali:
`Id_SOP_Dokumen` (PK), `Nomor_SOP_Resmi`, `Judul`, `Tipe` (DOKUMEN/FORM/VIDEO/KUIS),
`Deskripsi`, `Id_Versi_Aktif`, `Flag_Aktif`, kolom pembukuan.
`Nomor_SOP_Resmi` dan `Judul` naik dari item, karena selama ini digandakan di
setiap paket.

**`N_SOP_Item_Versi` menjadi versi DOKUMEN** — tukar FK `Id_SOP_Paket_Item` →
`Id_SOP_Dokumen`. Seluruh kolom pengesahan tetap di tempatnya. Unique index
`UX_N_SOP_Item_Versi_Urut` di-rekey dari `(Id_SOP_Paket_Item, Versi_Ke)` ke
`(Id_SOP_Dokumen, Versi_Ke)`.

**`N_SOP_Paket_Item` menjadi rujukan + setelan paket** — tambah `Id_SOP_Dokumen`
**nullable**, supaya tipe tanpa versi (PARAGRAF, TAUTAN, ABSENSI) tetap berjalan
apa adanya tanpa dipaksa punya dokumen.

### Kunci keamanan migrasi: pertahankan `Id_Item_Versi_Aktif`

Kolom `N_SOP_Paket_Item.Id_Item_Versi_Aktif` **tetap ada**, tapi berubah makna:
dari "versi yang dipilih paket ini" menjadi **cerminan yang disinkronkan sistem**
saat dokumen terbit.

Ini keputusan yang paling menghemat risiko. Sekitar sembilan tempat baca besar
menjoin lewat pointer itu dan **semuanya berjalan tanpa diubah sama sekali**:
`SopProgressReadService::detailBundle`, `SopPaketVersionService::snapshot`,
`SopExportRinciService`, `SopRecertificationService`, `SopReconciliationService`,
`SopRepositoryReadService`, `SopPenjadwalanService`, `SopDocumentControlService`.

Yang wajib ditulis ulang hanya join yang menyimpulkan **item DARI versi** — karena
di model baru satu versi menunjuk banyak item, join itu akan menggandakan baris:

- `SopMonitoringService.php:203-204` dan `:247-248` — harus lewat rollup →
  `Id_SOP_Paket_Item`, bukan versi → item.
- `SopProgressReadService::versiVisibleInPaket` (`:971-976`) — otorisasi akan
  salah beri/tolak.
- `SopSecurePdfAccessService.php:37,78` — akses PDF ditelusuri versi → item →
  paket; dengan berbagi, ini melebar ke semua paket pemegang dokumen. Perubahan
  semantik yang harus disengaja, bukan efek samping.
- `SopItemVersionService::publish()` — sekarang menggeser pointer satu item dan
  merekonsiliasi satu paket; harus fan-out ke semua item/paket perujuk.

`N_SOP_Progress` tetap sah tanpa migrasi data: barisnya sudah membawa konteks
paket sendiri (`Id_SOP_Penjadwalan_Detail` → penjadwalan → paket, plus
`Id_SOP_Paket_Item`); `Id_SOP_Item_Versi` hanya menyatakan "revisi mana yang
dibaca". `N_SOP_Coverage_Rollup` berkunci `(Id_SOP_Paket, Id_SOP_Item_Versi)`,
jadi tetap ter-rollup per paket dengan benar.

Snapshot rilis (`N_SOP_Paket_Versi_Item` + `Snapshot_JSON`) menyimpan salinan
fisik dan `Id_SOP_Item_Versi` yang dibekukan, sehingga **rilis lama tetap akurat**
setelah dokumen direvisi.

## Perubahan Permukaan

**Halaman Master Dokumen SOP** — bukan halaman baru yang berdiri sendiri.
Register dokumen yang sudah ada di Monitoring (`SopDocumentControlService`, ember
KADALUARSA / AKAN_KADALUARSA / REVIEW_LEWAT / REVIEW_DEKAT / TANPA_PENGESAHAN /
TANPA_MASA_BERLAKU / RENDER_GAGAL) **ditumbuhkan** menjadi halaman kelola: daftar
dokumen, kelola versi & pengesahan, dan daftar paket pemakai tiap dokumen. Membuat
layar dokumen kedua akan memberi admin dua angka berbeda untuk hal yang sama.

**Builder** — pemilihnya berpindah lapis, bukan berubah sifat. Builder sekarang
pun sudah tidak menerima unggahan langsung (jalur itu sudah dihapus; wajib pilih
dari Library konten). Yang berubah: yang dipilih bukan lagi **berkas**
(`N_LMS_Master_Konten`) melainkan **Dokumen SOP** (`N_SOP_Dokumen`). Field
pengesahan & masa berlaku hilang dari form item — ia milik dokumen.

Dua lapis ini tidak mubazir: Master Konten = berkasnya (dipakai bersama modul
LMS), Master Dokumen SOP = entitas terkendalinya. Revisi dokumen = versi baru yang
menunjuk berkas baru.

## Pagar Pengaman Wajib

Tiga bahaya nyata bila fan-out dipasang polos:

1. **Rekonsiliasi inline.** Ambang async (`penjadwalan_async_ambang`, default 30)
   dihitung **per paket** (`SopAdminBuilderController::reconcileSetelahPublish`
   `:476-494`). Revisi konten#80 menyentuh 8 paket; bila masing-masing di bawah
   ambang, 8 rekonsiliasi berjalan **inline dalam satu request** — memegang lock
   `N_SOP_Progress`/`N_SOP_Penjadwalan_Detail` pada DB tanpa RCSI. Ini persis pola
   "Login timeout expired" yang sudah pernah terjadi.
   → Ambang harus dihitung dari **total fan-out**, atau langsung diantrikan
   begitu menyentuh lebih dari satu paket.
2. **Banjir notifikasi.** Kunci dedup `SOP_UPDATED:{idPaket}:{kode}` bersifat per
   paket, tanpa dedup lintas paket. Karyawan di 5 paket menerima 5 notifikasi
   untuk satu revisi. → kunci per dokumen + ringkasan paket terdampak.
3. **`rollback()` senyap.** Hari ini rollback sengaja tidak merekonsiliasi
   (`SopItemVersionService.php:174`). Untuk dokumen bersama, itu meninggalkan
   banyak paket dalam keadaan tak sinkron tanpa jejak. Semantiknya harus
   diputuskan eksplisit.

Selain itu, `Snapshot_Hash`/`pendingChanges()` ikut menghitung kolom pengesahan,
sehingga revisi dokumen di Paket A akan membuat Paket B tampak "ada perubahan
menunggu rilis" padahal tak disentuh. Deteksi perubahan harus dipisah antara
**revisi dokumen** dan **perubahan tata letak paket**.

## Rencana Bertahap

Skala data kecil dan menguntungkan: **14 paket, 63 item, 54 versi, 930 baris
progres**. Sekarang justru waktu terbaik, sebelum datanya membesar.

Tetapi penggabungan **tidak boleh otomatis**: konten#80 punya dua nilai
`Tanggal_Kadaluarsa` berbeda, dan mesin tidak tahu mana yang benar.

**Tahap 1 — fondasi, migrasi 1:1.** Tiap item ber-versi menjadi satu dokumen
sendiri. **Nol perubahan perilaku**: tidak ada dokumen yang digabung, tidak ada
keputusan data yang perlu diambil manusia, semua layar tampil sama persis.
Struktur berdiri, pagar pengaman terpasang, tes membuktikan tak ada regresi.

**Tahap 2 — alat "Gabungkan Dokumen".** Admin melihat kandidat duplikat (13 item
konten#80), meninjau nilai pengesahan yang menyimpang berdampingan, memilih mana
yang benar, lalu menggabungkan secara sadar dan tercatat di jejak audit.

Pemisahan ini menjaga agar risiko struktur tidak bercampur dengan risiko
keputusan data — kalau ada yang salah di Tahap 1, ketahuan sebelum satu pun baris
pengesahan disatukan.

## Verifikasi

- **Tahap 1 wajib membuktikan nol regresi**: bandingkan keluaran `detailBundle`,
  export rinci, register kontrol dokumen, dan snapshot rilis sebelum vs sesudah
  migrasi untuk seluruh 14 paket — harus identik.
- Uji fan-out: revisi satu dokumen yang dipakai >1 paket → pastikan pointer semua
  item perujuk ikut bergeser, rekonsiliasi **terantri** (bukan inline), dan
  karyawan menerima **satu** notifikasi berisi daftar paket terdampak.
- Uji regresi kontradiksi: setelah Tahap 2 menggabungkan konten#80, register
  kontrol dokumen harus melaporkannya sebagai **satu** baris dengan **satu**
  status.
- Uji snapshot lama: rilis paket yang dibuat sebelum migrasi harus tetap
  menampilkan governance yang sama persis setelah dokumennya direvisi.

## Berkas Kunci

- Skema: `plans/sop/sql/` (DDL baru untuk `N_SOP_Dokumen` + ALTER dua tabel)
- `app/Services/SOP/SopItemVersionService.php` — siklus versi pindah ke dokumen
- `app/Services/SOP/SopItemService.php` — item jadi rujukan
- `app/Services/SOP/SopDocumentControlService.php` — register tumbuh jadi master
- `app/Services/SOP/SopReconciliationService.php` + `SopAdminBuilderController::reconcileSetelahPublish` — ambang fan-out
- `app/Services/SOP/SopNotificationService.php` — dedup lintas paket
- `app/Services/SOP/SopMonitoringService.php`, `SopProgressReadService.php`, `SopSecurePdfAccessService.php` — join versi→item ditulis ulang
- `app/Services/SOP/SopPaketVersionService.php` — pemisahan deteksi perubahan
- Builder UI: `resources/js/Pages/SOP/modules/admin/SopBuilder.vue`, `components/ui/ItemFields.vue`
