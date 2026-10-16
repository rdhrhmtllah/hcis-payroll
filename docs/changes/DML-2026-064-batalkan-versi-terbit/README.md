# DML-2026-064 — Versi terbit boleh dibatalkan selama belum beredar

| | |
|---|---|
| Change ID | `DML-2026-064-batalkan-versi-terbit` |
| Jenis | DML (tanpa DDL) |
| Target | `Web_HRIS` (development) |
| Status | **DEV DONE** — dijalankan 30 Agu 2026. SQL Tracker & produksi `PENDING`. |
| Artefak | `database/dml/DML-2026-064-batalkan-versi-terbit/data-change.sql` |
| Rancangan | `docs/superpowers/specs/2026-08-30-sop-versi-batal-beku-dan-nomor-dipakai-ulang-design.md` |

## Kenapa

Admin menerbitkan versi, lalu sadar berkas yang dilampirkan salah. Sampai
change ini satu-satunya jalan keluar adalah **menerbitkan revisi pengganti** —
dan revisi itu mengonsumsi satu nomor untuk kekeliruan berumur beberapa menit.
Versi yang keliru menjadi `OBSOLETE`, yang berarti nomornya terkunci selamanya.
Dokumen jadi punya "Revisi 01" yang isinya cuma salah unggah, dan "Revisi 02"
yang sebetulnya revisi pertama yang berlaku.

Keputusannya (30 Agu 2026): versi terbit **boleh dibatalkan**, sehingga
nomornya dilepas dan bisa dipakai penggantinya — tetapi hanya selama belum ada
yang menerimanya.

## Garisnya

**Nomor revisi dikonsumsi saat versinya SAMPAI KE ORANG, bukan saat
diterbitkan.**

Selama belum ada yang menerima, membatalkan hanya mengoreksi kekeliruan
beberapa menit lalu. Sesudah beredar, membatalkan berarti menyatakan dokumen
yang sudah dibaca dan dikonfirmasi orang "tak pernah terbit" — dan untuk
itulah `OBSOLETE` ada. Karena itu garisnya bukan waktu ("dalam 24 jam"), bukan
peran, dan bukan konfirmasi ekstra: garisnya adalah rujukan hilir yang nyata.

Di dev saat change ini ditulis: **31 versi terbit, 28 di antaranya sudah
beredar**. Jalur ini memang untuk kasus sempit, bukan pintu umum.

## Apa yang diubah

| Tabel | Operasi | Terduga | Hasil |
|---|---|---|---|
| `N_SOP_Master_Tahap_Versi_Transisi` | INSERT | 1 (`FILING_REGISTERED → CANCEL`) | 1 ✔ |
| `N_SOP_Master_Tahap_Versi_Syarat` | INSERT | 1 (`BELUM_BEREDAR` pada `CANCEL`) | 1 ✔ |

Aturannya tinggal di **data**, bukan sebagai cabang `if` di kode — sama seperti
seluruh rancangan tahap versi. Yang menolak pembatalan adalah mesin syarat yang
sama yang menolak penerbitan tanpa berkas.

`BELUM_BEREDAR` dinilai `SopTahapVersiService::nilaiSyarat()` dari lima rujukan
hilir:

```
N_SOP_Progress                 progres baca karyawan
N_SOP_Paket_Item               ditunjuk item paket   — hanya bila paketnya PUBLISHED
N_SOP_Penjadwalan_Integrasi    sudah masuk penjadwalan
N_SOP_Coverage_Rollup          sudah punya rekap penyelesaian
N_SOP_Paket_Versi_Item         terikat versi paket   — hanya bila versi paketnya PUBLISHED
```

**Dua di antaranya harus melewati induknya lebih dulu** (koreksi 31 Agu 2026).
Admin menyusun paket dalam keadaan `DRAFT`: dokumennya sudah terpasang sebagai
item, tapi belum satu pun karyawan bisa melihatnya. Menghitung pemasangan itu
sebagai peredaran menolak pembatalan dengan alasan yang **tidak benar** — dan
admin tak punya cara membuktikan sebaliknya dari layar. Tiga rujukan lainnya
tak perlu disaring: ketiganya hanya lahir sesudah paketnya terbit.

**Draft memenuhi syarat ini dengan sendirinya** — ia memang belum pernah sampai
ke siapa pun. Jadi memasangnya pada `CANCEL` tak mengubah apa pun bagi
pembatalan draft yang sudah berjalan; ia hanya menggigit pada yang sudah
terbit, persis yang dimaksud.

## Yang menyertainya di kode

| Bagian | Berkas |
|---|---|
| Pembatalan + pengembalian pengganti | `SopDokumenService::batalkanVersiTerbit()` |
| Daftar versi yang bisa dikembalikan | `SopDokumenService::kandidatPengganti()` |
| Penilaian syarat | `SopTahapVersiService::sudahBeredar()` |
| Endpoint | `GET`/`POST /api/v1/sop/admin/dokumen/versi/{id}/batal-terbit` |
| Dialog | `SopBatalTerbitDialog.vue` |
| Pintu masuk | `SopMasterDokumen.vue` — tombol pada baris `PUBLISHED` |

Satu transaksi, dengan `lockForUpdate` pada dokumennya: pointer dikosongkan →
versi dipindahkan ke `CANCEL` → pengganti dikembalikan ke `PUBLISHED` → nomor
resmi dirakit ulang lewat `segarkanNomorResmi()` → jejak `VERSI_BATAL_TERBIT`.

**Bila tak ada pengganti**, pointer setiap item paket perujuk ikut dikosongkan
(koreksi 31 Agu 2026). `kembalikanVersi()`-lah yang biasanya menggesernya; tanpa
pengganti tak ada yang menggeser, dan item paket tetap menunjuk versi yang baru
saja dibatalkan — paketnya lalu terbit membawa versi mati sebagai isinya.

Urutannya bukan selera. Pointer harus lepas lebih dulu, sebab indeks unik dari
DDL-2026-062 hanya mengizinkan **satu** versi `PUBLISHED` per dokumen; menukar
keduanya dalam urutan terbalik akan ditolak basis data di tengah jalan.

## Yang sengaja TIDAK dilakukan

- **Tidak ada pemaksaan lewat peran atau kata sandi.** Kalau versinya sudah
  beredar, jawabannya bukan "izin lebih tinggi" melainkan "terbitkan revisi".
- **Pengganti tak dipilihkan otomatis.** Dialog mengusulkan yang terbaru, tapi
  admin bisa memilih "tidak ada" — dokumen tanpa versi berlaku adalah keadaan
  sah, dan menyembunyikannya berarti menebak untuk orang lain.
- **Nomor tak ditulis tangan sebagai SQL.** Tujuh segmennya tinggal di
  `SopNomorDokumen`; merakitnya di skrip akan jadi implementasi kedua.

## Verifikasi (dev, 30 Agu 2026)

```
FILING_REGISTERED -> CANCEL aktif = Y
syarat CANCEL: BELUM_BEREDAR (Belum diterima siapa pun) aktif = Y

versi 12520 (belum beredar) : BELUM_BEREDAR = true
versi 11349 (sudah beredar) : BELUM_BEREDAR = false
  -> '"Cancel" belum bisa dimasuki. Lengkapi: Belum diterima siapa pun.'

jalur bahagia (dok 17053, transaksi di-rollback):
  pointer 12520 -> 12138
  nomor  SK/HOL/IA/EHG/02/01/01 -> SK/HOL/IA/EHG/02/01/00
  usulan berikutnya = v01   (nomor yang dilepas ditawarkan kembali)
```

Uji otomatis: `tests/Feature/LMS/SOP/SopBatalVersiTerbitTest.php` (8 lulus pada
30 Agu; 5 uji ditambahkan 31 Agu untuk kedua koreksi di atas — **belum
dijalankan**) dan `SopBatalTerbitDialog.test.js` (6 lulus).

## Sisa pekerjaan user

1. Catat ke SQL Tracker, isi `SQL_Tracker_Reference`, ubah `Status_SQL_Tracker`.
2. Jalankan di produksi. Change ini hanya menambah baris master — tak menyentuh
   satu pun versi yang sudah ada, jadi ia aman diborong bersama DDL-2026-062.
3. Perbarui `Status_Production` dan `Status_Verification`.
