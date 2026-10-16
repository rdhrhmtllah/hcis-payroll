# DDL-2026-052 — Gerbang katalog publik pada dokumen SOP · **DIBATALKAN**

| | |
|---|---|
| Change ID | `DDL-2026-052-sop-dokumen-mode-distribusi` |
| Jenis | DDL |
| Status | **`CANCELLED`** — jangan diteruskan ke SQL Tracker maupun produksi |
| Dibatalkan | 2026-08-27, pada hari yang sama dengan pembuatannya |
| Target development | `Web_HRIS` (host team311) |
| Checksum forward (sha256) | `757e28a621731635e514b3d39ff4df8ba6a26a1db3320d31782dc601138373a0` |

## Kenapa dibatalkan

Kolom `N_SOP_Dokumen.Mode_Distribusi` memberi **dokumen gerbang publikasi
sendiri**, terlepas dari paket yang memuatnya. Itu menyalahi aturan modul:
jangkauan sebuah dokumen ditentukan oleh paket yang memuatnya, dan tidak oleh
apa pun yang melekat pada dokumen itu sendiri.

Aturan itu sudah berlaku dan sudah terlihat di layar sebelah. Daftar **Dokumen**
di SOP Saya (`BarisDokumenSaya.vue`) tidak punya keberadaan sendiri: dokumen
muncul di sana karena ada paket yang **ditugaskan** kepada pembacanya — sampai
ke detail bahwa setiap baris menautkan ke halaman paket, bukan ke pembaca
dokumen, "supaya tidak melewati gerbang presensi, urutan section, dan prasyarat
antar-item".

Perpustakaan publik adalah pertanyaan yang sama dengan lingkup berbeda:
dokumen muncul karena ada paket **PUBLIK** yang memuatnya. Tidak ada gerbang
kedua yang perlu dibuat, dan membuatnya justru menciptakan jalan bagi dokumen
untuk terbuka tanpa satu pun paket yang mengizinkannya.

## Yang menggantikannya

`SopRepositoryReadService::katalogDokumen()` — berangkat dari `basePaket()`
seperti seluruh jalur baca lain di kelas itu, lalu turun ke item dan dokumennya.
Nol perubahan skema, nol permukaan admin baru.

Konsekuensi yang disengaja: dokumen yang belum pernah dimasukkan ke paket
publik memang tidak muncul. Cara mengisi perpustakaan adalah menerbitkan paket
sebagai publik — bukan memublikkan dokumen satuan.

## Keadaan basis data development

Kolomnya **sempat dieksekusi** ke `Web_HRIS` sebelum pembatalan ini, dan masih
menempel di sana: `varchar(10) NOT NULL DEFAULT 'TERBATAS'`, 41 baris terisi
`TERBATAS`.

Ia dibiarkan karena agen dilarang menjalankan `DROP` dalam bentuk apa pun.
Membiarkannya tidak berbahaya — kolomnya `NOT NULL` dengan nilai bawaan, dan
sesudah perubahan ini **tidak ada satu baris kode pun yang membacanya**.

Bila ingin bersih betul, jalankan manual:

```text
database/ddl/DDL-2026-052-sop-dokumen-mode-distribusi/drop.sql
```

Berkas `forward.sql` sengaja **tidak dihapus**: ia adalah catatan apa yang
pernah dijalankan ke development, dan menghapusnya membuat kolom yang menempel
di sana tak punya asal-usul yang bisa ditelusuri.

## Pelajaran

Sebelum menambah gerbang baru pada sebuah entitas, periksa apakah entitas itu
sudah punya pemilik keterlihatan. Di modul ini dokumen sudah punya — paketnya.
Layar yang sudah menampilkan dokumen (SOP Saya) adalah tempat paling murah
untuk memeriksanya.

## Sisa pekerjaan pengguna

1. **Jangan** catat perubahan ini ke SQL Tracker.
2. Opsional: jalankan `drop.sql` di development bila ingin kolomnya hilang.
