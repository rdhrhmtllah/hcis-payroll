# DML-2026-061 — Bersihkan isi warisan pada versi draft

| | |
|---|---|
| Change ID | `DML-2026-061-bersihkan-warisan-versi` |
| Jenis | DML (tanpa DDL — skema tak berubah) |
| Target | `Web_HRIS` (development) |
| Status | **DEV DONE** — dijalankan di development 30 Agu 2026 atas izin user. SQL Tracker & produksi masih `PENDING`. |
| Artefak | `database/dml/DML-2026-061-bersihkan-warisan-versi/data-change.sql` |

## Kenapa

Sampai 30 Agu 2026 formulir versi baru di wizard SOP mengisi dirinya sendiri
dari versi sebelumnya — matriks pengesah, berkas, dan paket ujian. Maksudnya
kenyamanan: admin tak perlu memilih ulang hal yang sama tiap revisi.

Isian itu ikut **tersimpan** pada penyimpanan pertama, dan sejak saat itu tak
ada lagi yang membedakannya dari pilihan sadar admin. Dua akibatnya membuat
sistem melaporkan hal yang tak pernah terjadi:

1. **Riwayat versi menyebut "berkas terlampir"** pada versi yang berkasnya belum
   pernah dipilih siapa pun. Layarnya jujur; yang tak jujur adalah data yang
   ditulis tanpa ada yang memilihnya.
2. **Gerbang `REALISASI_PENGESAH` (DML-2026-048) lolos sendiri** di tiap revisi
   sesudah v00 — versi tercatat disahkan oleh orang-orang yang belum melihatnya.

Yang pertama lebih dari salah tampil: revisi yang terbit tanpa disadari membawa
**berkas lama di bawah nomor revisi baru**, dan karyawan membaca dokumen yang
sama sambil dihitung sudah membaca yang baru.

## Yang sudah dikerjakan di kode

Pengisian otomatisnya dicabut — versi baru kini lahir kosong:

- `resources/js/Pages/LMS/SOP/Governance/Admin/SopVersiWizard.vue`
  — `inisialisasiVersiBaru()` tinggal `kosong()` + nomor versi.
- `app/Http/Controllers/LMS/SOP/Governance/SopAdminVersiWizardController.php`
  — muatan `Versi_Sebelumnya` dipangkas menjadi `Versi_Ke` saja, sehingga tak
  ada isi yang bisa dirangkai ulang pembaca berikutnya. Empat kueri per
  pemuatan halaman ikut hilang.
- `tests/Feature/LMS/SOP/SopWizardHalamanTest.php`
  — penjaga daftar tertutup: `assertSame(['Versi_Ke'], array_keys($prev))`.

Change ini membereskan baris yang terlanjur lahir **sebelum** pencabutan itu.

## Ruang lingkup

Hanya versi yang memenuhi **semuanya**:

- `Versi_Ke > 0` — versi pertama tak mewarisi apa pun
- `Status = 'DRAFT'` — belum pernah terbit, jadi belum ada karyawan yang
  menerimanya
- `Id_Master_Konten` **sama persis** dengan versi tepat sebelumnya

Kesamaan konten dengan pendahulunya adalah sidik jari warisan itu. Versi yang
admin-nya memang sengaja memilih ulang berkas yang sama **tidak bisa dibedakan**
dari sini — karena itu jaringnya dibatasi pada DRAFT, di mana biaya salah tebak
cuma memilih ulang berkas, bukan mengubah arsip yang sudah mengikat orang.

## Baris terduga vs hasil nyata (dev, 30 Agu 2026)

| Tabel | Operasi | Terduga | Hasil |
|---|---|---|---|
| `N_SOP_Item_Versi` | UPDATE | 2 (id `11183`, `12140`) | 2 ✔ |
| `N_SOP_Item_Versi_Pengesah` | UPDATE | 6 (3 per versi, dinonaktifkan) | 6 ✔ |

Keduanya `DRAFT`; tak satu pun `PUBLISHED`, jadi belum ada karyawan yang
menerima berkas keliru.

Verifikasi sesudah eksekusi — kedua versi:

```
Id_Master_Konten = NULL   Id_Master_Ujian = NULL   Nama_Ujian = NULL
Disahkan_Oleh    = NULL   Jabatan_Pengesah = NULL  Kode_Karyawan_Pengesah = NULL
pengesah aktif   = 0      pengesah nonaktif = 3
```

Dibaca ulang lewat jalur yang dipakai tab Versi (`riwayatVersiPaginated`) pada
dokumen 17054:

```
v1  DRAFT      Id_Master_Konten = NULL   Disahkan_Oleh = NULL     ← sudah bersih
v0  PUBLISHED  Id_Master_Konten = '318'  Disahkan_Oleh = 'Aji'    ← tak tersentuh
```

## Yang TIDAK dilakukan

- **Tidak ada DELETE.** Baris pengesah dinonaktifkan (`Flag_Aktif = 'T'`),
  sehingga jejaknya tetap terbaca kalau ada yang mempertanyakan apa yang
  terjadi. Pembacanya (`nilaiSyarat`, `blokPengesah`) sudah menyaring
  `Flag_Aktif` sejak awal.
- **Tidak menyentuh versi PUBLISHED.** Dokumen yang sudah berlaku bagi karyawan
  tak diubah lewat jalur ini.
- **Tidak menyentuh tanggal proses.** `Tanggal_Permintaan`, `Tanggal_Respon`,
  dan `Tanggal_Akhir_Approval` bukan warisan: dua yang pertama diketik admin,
  yang ketiga dicap `capTanggal()` saat versi benar-benar masuk tahap
  Pengesahan.

## Sisa pekerjaan user

Development sudah selesai. Yang tersisa:

1. Catat change ini ke SQL Tracker, lalu isi `SQL_Tracker_Reference` dan ubah
   `Status_SQL_Tracker` menjadi `DONE`.
2. Jalankan di produksi bila memang ada baris terdampak di sana — periksa dulu
   dengan `SELECT` sasaran di kepala skrip. Di produksi, warisan ini bisa
   menyentuh versi yang **sudah terbit**; skrip ini sengaja hanya menyasar
   `DRAFT`, jadi versi terbit yang terlanjur membawa berkas warisan harus
   ditangani sebagai keputusan tersendiri, bukan lewat change ini.
3. Setelahnya perbarui `Status_Production` dan `Status_Verification`.
