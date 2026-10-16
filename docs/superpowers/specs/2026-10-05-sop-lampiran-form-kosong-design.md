# Lampiran form kosong untuk dokumen FORM

Tanggal: 5 Okt 2026. Status: menunggu tinjauan user.

## Masalahnya

Dokumen bertipe FORM (Formulir) hanya punya satu berkas, yaitu yang dipasang
di langkah Pemberkasan. Dalam praktiknya berkas itu adalah **contoh
pengisian**. Karyawan juga butuh **form kosong** untuk diunduh lalu diisi
sendiri, dan sekarang belum ada tempat untuk menaruhnya.

## Keputusan (dari user, 5 Okt 2026)

| Pertanyaan | Jawaban |
|---|---|
| Satu atau beberapa form kosong per versi? | **Beberapa** (mis. versi Word dan Excel). |
| Wajib sebelum terbit? | **Opsional**. Versi FORM boleh terbit tanpa lampiran. |
| Kapan boleh diubah? | **Sama seperti berkas**: bebas selama draft; sesudah terbit masih boleh selama dokumennya belum dijadwalkan ke karyawan; sesudah itu lewat versi baru. |
| Pendekatan | Tabel lampiran per versi, berkasnya dari **Library Konten**. |

Diputuskan dalam desain dan disetujui user di obrolan yang sama:

- Revisi baru **tidak mewarisi** lampiran, sama seperti berkas utama yang
  dipilih ulang di tiap versi.
- Mengunduh lampiran **tidak** mengubah progres karyawan. Progres item FORM
  tetap dari "Tandai selesai".
- Lampiran hanya untuk tipe FORM.

## Ketergantungan

Aturan "sesudah terbit masih boleh selama belum dijadwalkan" memakai
`SopVersiKeadaan::FIELD_ISI` dan `$sudahDijadwalkan` dari PR #515. Branch ini
dibangun di atas branch PR #515, dan PR-nya baru bisa di-merge sesudah #515.

## Rancangan

### Data — DDL-2026-158-sop-versi-lampiran-form

Tabel baru `N_SOP_Item_Versi_Lampiran`:

| Kolom | Tipe | Catatan |
|---|---|---|
| `Id_Lampiran` | int IDENTITY, PK | |
| `Id_SOP_Item_Versi` | int NOT NULL | FK ke `N_SOP_Item_Versi` |
| `Id_Master_Konten` | int NOT NULL | Berkas di Library Konten (`N_LMS_Master_Konten`, Scope SOP) |
| `Urutan` | int NOT NULL, default 0 | Urutan tampil |
| `Flag_Aktif` | char(1) NOT NULL, default 'Y' | Lampiran yang dilepas dinonaktifkan, tidak dihapus |
| `Created_At`, `Created_By`, `Updated_At`, `Updated_By` | | Jejak |

Indeks `(Id_SOP_Item_Versi, Flag_Aktif, Urutan)`. Tabel anak dipilih, bukan
kolom, karena jumlahnya bebas — pola yang sama dengan
`N_SOP_Item_Versi_Review_Lampiran`.

Artefak: `database/ddl/DDL-2026-158-sop-versi-lampiran-form/forward.sql` dan
`drop.sql`, entri `database/ddl/status.json`, dokumentasi
`docs/changes/DDL-2026-158-sop-versi-lampiran-form/README.md`, dan dokumentasi
tabel di `docs/database/tables/`. Forward DDL hanya dijalankan agent di
`Web_HRIS` (development), sesudah user menyetujui SQL-nya. SQL Tracker dan
production dikerjakan user.

### Profil tipe

`SopTipeItem::PROFIL` mendapat blok baru `lampiran` = `BERLAKU` (opsional)
untuk FORM saja; cerminnya di `constants.js` (`PROFIL_TIPE`). Tipe lain yang
mengirim lampiran terisi ditolak server lewat `pastikanFieldSesuaiTipe()`,
sama seperti blok lain.

### Aturan ubah

`Lampiran` menjadi field semu, seperti `Pengesah`:

- masuk `FIELD_DRAFT` dan `FIELD_ISI`, sehingga versi terbit yang belum
  dijadwalkan masih bisa mengubahnya;
- tidak masuk `FIELD_ARSIP`: arsip dan riwayat tidak menerima lampiran baru.

Penolakan pada dokumen yang sudah dijadwalkan memakai alasan yang sama dengan
berkas ("sudah dijadwalkan ke karyawan lewat paket...").

### Menyimpan (admin)

- Wizard mengirim `Lampiran: [{ Id_Master_Konten, Urutan }]` bersama
  `PUT /versi/{id}` dan `POST /{dokumen}/versi`. Kunci yang tidak dikirim
  berarti "jangan sentuh"; larik kosong berarti "lepas semua".
- Server memvalidasi tiap konten: ada, Scope SOP, dan cocok untuk FORM
  (`SopTipeItem::kontenCocok`). Konten yang sama tidak boleh dua kali.
- Penulisan memakai selisih: lampiran yang tetap diperbarui urutannya,
  yang dilepas di-`Flag_Aktif = 'T'`, yang baru di-insert. Semua di dalam
  transaksi `ubahVersi()`, sehingga cap tulis-bersamaan dan riwayat
  perubahan (`SopDiff`, label "Form kosong") ikut berlaku.

### Muatan admin

`getRiwayatVersi` membawa `Lampiran` per versi:
`[{ Id_Lampiran, Id_Master_Konten (hashid), Judul, Ext, Urutan }]`, diambil
sekali untuk seluruh baris halaman (bukan satu kueri per versi).

### Unduh (karyawan)

- `detailBundle` membawa `Lampiran` untuk item FORM dari versi yang berlaku:
  `[{ Id_Lampiran (hashid), Nama, Ext, Ukuran }]`, diambil sekali per paket.
- Endpoint baru `GET api/v1/sop/{idPaket}/versi/{idVersi}/lampiran/{idLampiran}`
  dengan gerbang yang sama dengan `apiVersiKonten`: absensi, versi terlihat di
  paket, dan versi masih berlaku. Lampiran harus milik versi itu dan aktif.
  Jawabannya `SopKontenViewService::forKonten(..., paksaUnduh: true)`:
  tautan GCS bertanda tangan, 15 menit, sebagai unduhan.

### Layar

- **Admin — wizard, langkah Pemberkasan (FORM saja).** Di bawah kartu
  "Lampiran Berkas Prosedur" ada kartu "Form Kosong untuk Diunduh": daftar
  lampiran dengan tambah (pemilih Library Konten yang sama), pratinjau, hapus,
  dan geser naik/turun. Dimatikan mengikuti `bolehUbah('Lampiran')`.
- **Karyawan — halaman detail paket, kartu FORM.** Di bawah tombol "Lihat
  Formulir" dan "Unduh Formulir" (contoh pengisian), daftar "Unduh form
  kosong", satu tombol per lampiran dengan nama berkasnya.

## Pengujian

Tiap penjaga wajib pernah dilihat merah.

1. Versi FORM draft menyimpan dua lampiran berurutan; simpanan berikutnya
   menukar urutan, melepas satu, dan menambah satu, dengan jejak riwayat.
2. Tipe DOKUMEN yang mengirim lampiran terisi ditolak.
3. Konten yang tak ada, bukan Scope SOP, atau kembar ditolak.
4. Versi terbit yang belum dijadwalkan menerima perubahan lampiran; yang sudah
   dijadwalkan menolak dan lampirannya tetap.
5. Arsip (dibatalkan/digantikan) menolak perubahan lampiran.
6. Muatan riwayat versi membawa lampiran dalam satu kueri untuk seluruh
   halaman.
7. Endpoint unduh karyawan: lampiran milik versi lain, nonaktif, atau dari
   versi yang tak berlaku lagi ditolak; yang sah mengembalikan tautan unduh.
8. Browser dev: admin menambah form kosong di Pemberkasan, karyawan melihat
   dan mengunduhnya di kartu FORM.

## Di luar lingkup

- Lampiran di riwayat versi lama untuk karyawan (hanya versi yang berlaku).
- Menampilkan lampiran di laci/ringkasan Master Dokumen dan Tinjauan Akhir.
- Pewarisan lampiran ke revisi baru.
- Pelacakan unduhan lampiran.
