# DML-2026-167-pa-draf-deskripsi-perilaku-kompetensi

## Metadata

- **Change ID**: `DML-2026-167-pa-draf-deskripsi-perilaku-kompetensi`
- **Target Database**: `Web_HRIS` (development)
- **Type**: DML
- **Checksum (SHA-256)**: `3bf83791c2c8f5cfebb2291489cea460ff8eb32caee1f70710273b02a991e3d8`
- **Affected Objects**: `N_PA_Competency (Deskripsi, Expected_Behaviour)`
- **Status**: `DEV_EXECUTED`

## Deskripsi

Mengisi **draf** Deskripsi untuk 21 kompetensi aktif dan Expected Behaviour untuk 17
kompetensi berskor, dicocokkan lewat `Kode_Competency`. Kompetensi kualitatif
(QL01–QL04) berupa esai, jadi hanya Deskripsi yang diisi.

Sesudah DDL-2026-166 seluruh layar membaca arti dan perilaku kompetensi dari master. Teks
yang sebelumnya ditebak di frontend (`getCompetencyDescription` di
`PaSayaResultPanel.vue` dan `PaLeaderMemberDetailView.vue`) dihapus, dan isinya menjadi
dasar Deskripsi di sini. HC merevisinya lewat `/performance-appraisal/master/kompetensi`.

## Keamanan

- Setiap kolom hanya diisi bila masih `NULL`; isian HC tidak pernah ditimpa.
- Aman diulang: eksekusi kedua mengubah 0 baris.

## Eksekusi development

- 2026-10-06, `Web_HRIS @ instance-20240809-031237` (DB_NAME + @@SERVERNAME).
- Sebelum: 21/21 kompetensi aktif kosong pada kedua kolom.
- Dijalankan dalam transaksi dengan pemeriksaan jumlah: Deskripsi_Diisi = 21,
  Perilaku_Diisi = 17 → commit. Dijalankan ulang: 0/0.

## Production

Tidak berlaku sebagai DML wajib. Isi resmi production sebaiknya ditulis HC lewat Master
Kompetensi. Bila HC menyetujui draf ini apa adanya, berkas yang sama aman dijalankan lewat
SQL Tracker (hanya mengisi kolom yang masih kosong).

## Amandemen r2 (2026-10-06)

Dinomori ulang dari `DML-2026-163-pa-draf-deskripsi-perilaku-kompetensi` karena nomornya juga dipakai `DDL-2026-163-sinkron-prod-indeks` di master. Isi perubahan tidak berubah selain kode change. **Checksum lama `c9d5a6f8b20b29e54893902f9ff56419d780ecaf801ece6236c5dcde3eba7e5a` tidak boleh dicatat di SQL Tracker**; pakai checksum di atas.
