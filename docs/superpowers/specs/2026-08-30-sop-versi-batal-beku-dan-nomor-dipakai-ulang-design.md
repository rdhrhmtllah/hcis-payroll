# Versi SOP yang dibatalkan: beku sebagai arsip, nomornya dipakai ulang

**Tanggal**: 30 Agustus 2026
**Modul**: LMS/SOP — Master Dokumen, siklus versi
**Status**: rancangan disetujui, siap diimplementasikan

## Masalahnya

Dua keluhan yang ternyata berakar sama.

**Pertama**, versi yang sudah dibatalkan masih bisa dikerjakan. Ia seharusnya
beku — arsip yang hanya dibaca, tak bisa disunting, tak bisa diterbitkan, tak
bisa dibangkitkan.

**Kedua**, nomor versinya hangus. Bila v01 dibatalkan lalu admin membuat versi
baru, versi itu bernomor v02 — dan "Revisi 01" tak pernah diterbitkan kepada
siapa pun. Nomor resmi dokumen melompat dari `…/00` ke `…/02`. Dalam tata kelola
dokumen, nomor revisi dikonsumsi saat revisi **diterbitkan**, bukan saat
draftnya dibuat.

## Akar yang mengikat keduanya

```
CANCEL   Status_Teknis = 'DRAFT'
```

Yang berpindah saat versi dibatalkan adalah **tahapnya**, bukan statusnya. Versi
batal tetap berstatus `DRAFT`. Akibatnya setiap penjaga yang ditulis
`Status === 'DRAFT'` diam-diam ikut memasukkan versi batal.

Gejalanya sudah muncul di beberapa tempat, termasuk tombol "Sunting Draft" dan
"Pindahkan tahap" yang tampil di kartu berlencana "Dibatalkan (Arsip)" — sudah
diperbaiki terpisah di `SopMasterDokumen.vue` pada 30 Agu 2026.

### Pintu mutasi yang masih terbuka

| Pintu | Keadaan sekarang |
|---|---|
| `SopDokumenService::ubahVersi()` | hanya menolak `Status !== 'DRAFT'` → versi batal lolos, isinya bisa diubah |
| `SopDokumenService::hapusVersi()` | idem → arsip bisa dihapus |
| `SopAdminDokumenController::simpanReviewVersi()` | tak ada penjaga status sama sekali |

### Yang sudah terjaga — tidak disentuh

| Jalur | Penjaganya |
|---|---|
| Pindah tahap | `pastikanTransisiSah()` menolak keluar dari tahap `Is_Terminal='Y'`; CANCEL terminal |
| Penerbitan | `terbitkanVersi()` menuntut `FILING_REGISTERED` ada di `tujuanSah()`, yang kosong dari tahap terminal |
| Pembangkitan | rute `rollbackVersi()` sudah dicabut sebelumnya |

## Bagian 1 — Beku sebagai arsip

Satu penilai tunggal, dibaca dari **tahap**, bukan dari status:

```php
SopDokumenService::dibatalkan(int $idVersi): bool
```

Sumbernya `N_SOP_Master_Tahap_Versi.Nonaktifkan_Versi = 'Y'` pada tahap yang
sedang ditempati versi — bukan literal `'CANCEL'` di kode. Seluruh rancangan
alur di modul ini memang ada supaya kebijakan tahap bisa berubah sebagai data;
menuliskan nama tahapnya sebagai kode akan membuat setiap perubahan kebijakan
menunggu rilis.

Dipasang di tiga pintu di atas. Pesannya seragam:

> Versi ini sudah dibatalkan dan tersimpan sebagai arsip. Buat versi baru untuk
> melanjutkan.

Menyebut jalan keluarnya, bukan sekadar menolak.

## Bagian 2 — Nomor dipakai ulang

### DDL

Indeks unik yang ada sekarang tak memandang status:

```
UX_N_SOP_Item_Versi_Dokumen_Urut  UNIQUE (Id_SOP_Dokumen, Versi_Ke)
filter: ([Id_SOP_Dokumen] IS NOT NULL)
```

Disempitkan menjadi:

```sql
WHERE Id_SOP_Dokumen IS NOT NULL AND Flag_Aktif = 'Y'
```

Indeks terfilter SQL Server tak bisa menengok tabel lain, jadi predikatnya harus
satu kolom pada tabel yang sama. `Flag_Aktif` tepat karena **hanya CANCEL** yang
memilikinya `Nonaktifkan_Versi='Y'`. Verifikasi pada dev 30 Agu 2026: satu-satunya
baris ber-`Flag_Aktif='T'` yang bertaut dokumen adalah dua versi CANCEL.

Ketepatan itu adalah **kebetulan yang bisa bergeser**, dan karena itu dikunci
dengan uji invarian (lihat Pengujian). Menambah tahap kedua yang mematikan versi
akan menyalakan uji itu merah sebelum nomor mulai bocor diam-diam.

**Alternatif yang ditolak**: kolom `Dibatalkan_At` khusus. Ia memutus
ketergantungan pada `Flag_Aktif`, tapi menambah kolom yang artinya sudah dipikul
tahap — dua sumber kebenaran untuk satu fakta, dan yang menyimpang di antaranya
akan menjadi cacat berikutnya.

### Kode

`SopDokumenService::nomorVersiBerikut()`:

- jalur otomatis: `MAX(Versi_Ke)` hanya atas versi `Flag_Aktif = 'Y'`
- jalur manual: bentrok dinilai hanya terhadap versi `Flag_Aktif = 'Y'`

`SopDokumenService::ubahVersi()` — pemeriksaan bentrok saat admin mengganti nomor
versi secara manual ikut menyaring `Flag_Aktif = 'Y'`. Kalau tidak, nomor yang
sudah dibebaskan indeks tetap ditolak layar, dan admin menghadapi penolakan yang
tak bisa ia jelaskan.

### Yang sengaja TIDAK berubah

`OBSOLETE` punya `Nonaktifkan_Versi = 'T'` — versi lama yang tergantikan tetap
`Flag_Aktif='Y'`. Nomor yang **pernah benar-benar terbit** karena itu terkunci
selamanya. Revisi 00 yang digantikan tak akan pernah dipakai ulang, dan memang
harus begitu: ia sudah beredar.

`revisiBerlaku()` membaca `Versi_Ke` dari versi yang **berlaku**
(`Id_Versi_Aktif`), bukan dari nomor tertinggi — tak tersentuh perubahan ini.

## Bagian 3 — Riwayat yang memuat dua "v01"

Keputusan produk (30 Agu 2026): versi batal **mempertahankan** nomornya. Riwayat
karena itu bisa memuat dua baris "v01" — satu batal, satu hidup. Pembedanya
cukup lencana "Dibatalkan (Arsip)", tanggal dibuat, dan sebab pembatalannya.

Kekhawatiran awal: **ekspor** yang hanya memuat kolom nomor versi akan
menampilkan dua baris yang terbaca identik.

**Diperiksa — tidak terjadi.** Kedua dataset Export Studio menautkan versinya
lewat *pointer versi aktif*, bukan lewat seluruh riwayat:

| Dataset | Join versinya |
|---|---|
| `SopDatasetDokumen` | `vr.Id_SOP_Item_Versi = dok.Id_Versi_Aktif` |
| `SopDatasetKepatuhan` | `vr.Id_SOP_Item_Versi = pi.Id_Item_Versi_Aktif` |

Pointer itu hanya boleh menunjuk versi yang sudah sah terbit, sehingga draft
yang dibatalkan **tak pernah muncul di ekspor sama sekali** — tak ada baris
kembar untuk dibedakan. Tak ada pekerjaan di sini.

Satu catatan untuk pembaca berikutnya: dimensi `status_versi` pada dataset itu
membaca `vr.Status`, yang bernilai `DRAFT` juga bagi versi batal. Selama
pointernya menyaring seperti sekarang hal itu tak pernah terlihat; kalau suatu
saat ada dataset yang menyusuri SELURUH riwayat versi, ia wajib memakai
`tahap_versi` (`th.Nama_Tahap`) — bukan `status_versi` — untuk menyebut
pembatalan.

## Pengujian

Tiap penjaga baru wajib pernah dilihat **merah** sebelum dinyatakan bekerja —
penjaga yang tak pernah gagal belum terbukti menyala.

1. **Penolakan di tiga pintu.** Versi di tahap CANCEL menolak `ubahVersi`,
   `hapusVersi`, dan simpan review, masing-masing dengan pesan arsipnya.
2. **Penomoran.** v00 terbit + v01 batal → berikutnya **01**.
   v00 terbit + v01 obsolete → berikutnya **02**.
3. **Indeks.** Dua v01 hidup ditolak basis data; v01 batal + v01 hidup diterima.
4. **Invarian master.** Hanya `CANCEL` yang boleh `Nonaktifkan_Versi='Y'`.
   Ini penjaga bagi predikat indeks terfilter di atas.
5. **Suite penuh.** Penjaga di service bersama menyentuh setiap pemanggil —
   `ubahVersi` dan `hapusVersi` dipakai lebih dari satu layar.

## Catatan operasional

Mengganti indeks berarti `DROP INDEX`, dan aturan proyek melarang agen
menjalankannya. Artefak DDL disiapkan; **user yang mengeksekusi**.

## Berkas terdampak

| Berkas | Perubahan |
|---|---|
| `app/Services/LMS/SOP/SopDokumenService.php` | `dibatalkan()`; penjaga di `ubahVersi`/`hapusVersi`; `nomorVersiBerikut()` menyaring aktif |
| `app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php` | penjaga arsip pada `simpanReviewVersi` |
| `database/ddl/DDL-2026-062-…/forward.sql` + `drop.sql` | indeks unik terfilter |
| `docs/changes/DDL-2026-062-…/README.md` | catatan change |
| `tests/Feature/LMS/SOP/SopVersiBatalBekuTest.php` | penjaga arsip, penomoran, invarian master |
| Dataset Export Studio | **tak disentuh** — versi batal tak pernah masuk ekspor (lihat Bagian 3) |

---

## Bagian 4 — Versi yang SUDAH TERBIT pun boleh dibatalkan (31 Agu 2026)

Rancangan di atas melepas nomor versi yang dibatalkan **selagi masih draft**.
Satu kasus tersisa terbuka, dan justru kasus yang paling sering terjadi: admin
sudah menekan terbit, lalu sadar berkas yang dilampirkan salah.

Sampai sini satu-satunya obat adalah menerbitkan revisi pengganti. Revisi itu
mengonsumsi satu nomor untuk kekeliruan berumur beberapa menit, dan yang keliru
jadi `OBSOLETE` — nomornya terkunci selamanya. Dokumen berakhir punya
"Revisi 01" yang isinya cuma salah unggah.

### Garisnya bukan waktu, bukan peran

**Nomor revisi dikonsumsi saat versinya SAMPAI KE ORANG, bukan saat
diterbitkan.**

Selama belum ada yang menerima, membatalkan hanya mengoreksi kekeliruan
beberapa menit lalu. Sesudah beredar, membatalkan berarti menyatakan dokumen
yang sudah dibaca dan dikonfirmasi orang "tak pernah terbit" — dan untuk itulah
`OBSOLETE` ada.

Karena itu gerbangnya bukan tenggat ("dalam 24 jam"), bukan peran yang lebih
tinggi, dan bukan konfirmasi berlapis. Gerbangnya adalah **rujukan hilir yang
nyata**: progres baca, penunjukan paket item, penjadwalan integrasi, rekap
coverage, dan paket versi item. Semuanya dinilai sebagai satu syarat tahap,
`BELUM_BEREDAR` (DML-2026-064), bukan sebagai cabang `if` di kode — sama seperti
seluruh rancangan tahap versi.

Draft memenuhi syarat itu dengan sendirinya, jadi pembatalan draft tak berubah
sama sekali.

### Yang ikut berpindah saat dibatalkan

Satu transaksi, `lockForUpdate` pada dokumennya:

```
pointer Id_Versi_Aktif dikosongkan     ← WAJIB lebih dulu
versi dipindahkan ke CANCEL             (Flag_Aktif='T', nomornya lepas)
pengganti dikembalikan ke PUBLISHED     (opsional — admin boleh memilih "tidak ada")
Nomor_SOP_Resmi dirakit ulang           segarkanNomorResmi()
jejak VERSI_BATAL_TERBIT
```

Urutannya bukan selera. Indeks unik dari DDL-2026-062 hanya mengizinkan **satu**
versi `PUBLISHED` per dokumen; menukar dua langkah pertama akan ditolak basis
data di tengah jalan.

### Dokumen tanpa versi berlaku adalah keadaan yang SAH

Admin boleh memilih "tidak ada pengganti". Menyembunyikan pilihan itu berarti
menebak untuk orang lain — dan pointer yang tetap menunjuk versi batal adalah
keadaan yang setiap pembaca laporan harus tafsirkan sendiri. Dialognya
mengusulkan versi terbaru lebih dulu, tapi menyebutkan akibatnya bila tak ada
yang dipilih.

### Akibat pada kalimat di layar

Banner Tinjauan Akhir dulu menjanjikan penerbitan "tak bisa ditarik". Sejak
change ini janji itu keliru, dan peringatan yang keliru lebih buruk daripada
tak ada: ia membuat admin menerbitkan dengan gentar, lalu menemukan sendiri
bahwa aturannya lain. Kalimatnya diganti menjadi syarat sebenarnya.

### Berkas terdampak (Bagian 4)

| Berkas | Perubahan |
|---|---|
| `SopDokumenService.php` | `batalkanVersiTerbit()`, `kandidatPengganti()` |
| `SopTahapVersiService.php` | syarat `BELUM_BEREDAR` + `sudahBeredar()` |
| `SopAdminDokumenController.php` | `pratinjauBatalTerbit()`, `batalkanVersiTerbit()` |
| `SopBatalTerbitDialog.vue` | dua peringatan + pemilih pengganti (terbaru → terlama) |
| `SopMasterDokumen.vue` | tombol pada baris `PUBLISHED` |
| `LangkahTinjauan.vue` | kalimat keadaan yang jujur soal pembatalan |
| `database/dml/DML-2026-064-…` | transisi + syarat |
| `tests/Feature/LMS/SOP/SopBatalVersiTerbitTest.php` | 8 uji |
| `SopBatalTerbitDialog.test.js` | 6 uji |
