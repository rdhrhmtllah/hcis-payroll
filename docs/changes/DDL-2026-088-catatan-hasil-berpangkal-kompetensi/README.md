# DDL-2026-088 — Catatan hasil berpangkal pada kompetensi

| | |
|---|---|
| Change ID | `DDL-2026-088-catatan-hasil-berpangkal-kompetensi` |
| Jenis | DDL + DML (satu perubahan, dua berkas) |
| Forward | `database/ddl/DDL-2026-088-catatan-hasil-berpangkal-kompetensi/forward.sql` |
| Data | `database/dml/DDL-2026-088-catatan-hasil-berpangkal-kompetensi/data-change.sql` |
| Rollback | `.../drop.sql` (alat bantu manual; agen tidak menjalankannya) |
| Bergantung pada | tidak ada |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Masalah yang diselesaikan

Dimensi kualitatif ada **dua kali** di basis data ini, dan keduanya menjawab pertanyaan yang
sama — "kategori naratif apa saja yang dikenal sistem":

| Master | Isinya |
|---|---|
| `N_PA_Competency` jenis `KUALITATIF` | QL01 Strength, QL02 Development Area, QL03 Initiative, QL04 Recommendation |
| `N_PA_Result_Note_Type` | STRENGTH, DEVELOPMENT_AREA |

Yang kedua disemai 1 September 2026, sebelum daftar pertama ada. Sejak itu keduanya harus
dijaga sinkron dengan tangan, lewat `config/pa_catatan_kualitatif.php` yang isinya pemetaan
yang ditulis manual.

Ketidaksinkronannya bukan bahaya teoretis; ia sudah berakibat. Dua dari empat pertanyaan
kualitatif — Initiative dan Recommendation — dijawab enam penilai, tersimpan lengkap di
`N_PA_Assignment_Score`, lalu **tidak pernah sampai ke report peserta**, karena tidak ada
jenis catatan yang bersesuaian. Tidak ada satu pun galat yang menandainya: panelnya hanya
tidak ada.

Sesudah perubahan ini catatan hasil berpangkal langsung pada **kompetensi**. Menambah dimensi
kualitatif berikutnya cukup satu baris di master kompetensi; panelnya ikut dengan sendirinya,
tanpa master kedua dan tanpa config pemetaan.

## Keputusan bentuk

**Kompetensi, bukan butir soal.** Catatan hasil menunjuk KOMPETENSI, bukan butir soal: butir
milik paket per siklus dan bisa berganti, sedangkan kompetensi hidup di master dan tetap
menjadi jangkar yang sama lintas siklus.

**Amandemen 7 September 2026 — kategori yang DITAWARKAN, bukan yang dikenal.** Versi pertama
perubahan ini juga menurunkan *daftar kotak isian admin* dari master, dengan alasan panel
report sebaiknya sama bentuknya bagi semua orang. Alasan itu tidak bertahan menghadapi data
sungguhan: butir kualitatif hanya ada pada sebagian level, sehingga peserta yang penilainya
tidak pernah ditanyai apa pun tetap disodori empat kotak kosong — bukan keseragaman,
melainkan pekerjaan yang tampak menggantung padahal tidak ada bahannya.

Sejak amandemen ini `kategori_catatan` diturunkan dari dua sumber yang digabung: dimensi yang
**benar-benar ditanyakan** kepada penilai orang itu, ditambah dimensi yang **sudah terlanjur
punya catatan** pada hasilnya. Sumber kedua mencegah catatan lama menghilang dari layar ketika
paket soal siklus berubah. Daftar kosong berarti peserta itu memang tidak punya dimensi
kualitatif, dan layarnya menyembunyikan seluruh panel catatan. Yang tidak berubah: tabelnya
tetap menunjuk kompetensi, dan urutan panelnya tetap diambil dari `Urutan_Tampil` master.

**Expand, bukan ganti di tempat.** Kolom lama `PA_Result_Note_Type_Id` tidak dibuang, hanya
dilonggarkan menjadi NULL-able. Dua alasan: 88 baris yang sudah ada masih menunjuk ke sana dan
itulah satu-satunya jalan mundur bila pemetaannya keliru; dan pencabutan kolom adalah `DROP`,
yang dijalankan manusia, bukan agen.

**Dicocokkan lewat kode, bukan Id.** Id berbeda antara dev dan production. Angka yang ditulis
tangan akan menaruh catatan orang di bawah kategori yang keliru pada salah satu lingkungan,
dan tidak ada satu pun galat yang menandainya — kalimatnya tetap tampil, hanya di bawah judul
yang salah.

**Penjaga yang menolak, bukan yang memaafkan.** Baris yang jenis catatannya tidak punya
padanan kompetensi akan tersisa NULL, dan skrip DML-nya menolak selesai. Catatan tanpa
kategori tidak bisa ditampilkan di panel mana pun; lebih baik ketahuan saat migrasi daripada
muncul sebagai panel kosong di layar karyawan.

## Urutan yang mengikat

1. `forward.sql` — kolom, foreign key, indeks, pelonggaran NOT NULL.
2. `data-change.sql` — mengisi kolom barunya.

Terbalik, langkah kedua gagal karena kolomnya belum ada.

## Verifikasi di dev

Dijalankan pada `Web_HRIS`. Keenam batch `forward.sql` selesai; kolom, foreign key
`FK_PA_Result_Note_Competency`, dan indeks `IX_PA_Result_Note_Competency` terbentuk, dan kolom
lamanya menjadi NULL-able.

DML-nya memindahkan **88 baris**: QL01 Strength 44, QL02 Development Area 44, dan `catatan
tanpa kompetensi: 0`. Penjaganya lewat.

Catatan pelaksanaan: percobaan pertama gagal dengan *"A transaction that was started in a MARS
batch is still active at the end of the batch"*. Sebabnya `GO` di antara `BEGIN TRANSACTION`
dan `COMMIT` pada `forward.sql` — sementara tiap langkah DDL-nya memang **butuh** batasnya
sendiri, karena foreign key dan indeks menunjuk kolom yang baru dibuat langkah sebelumnya.
Transaksi eksplisitnya dicabut dengan alasan yang ditulis di dalam berkasnya: tiap langkah
atomik sendiri dan dijaga `IF NOT EXISTS`, jadi jalan yang terhenti di tengah tinggal
dijalankan ulang.

## Penyerta di kode

- `PaResultCalculationService::simpanCatatanDariPenilai()` menulis `PA_Competency_Id`, dan
  melewati butir yang kompetensinya `Flag_Berskor='Y'` — komentar di samping angka adalah
  pelengkap nilai, bukan umpan balik naratif yang berdiri sendiri.
- `PaResultNoteService::simpanCatatanAdmin()` menerima kode kompetensi kualitatif (QL01–QL04),
  bukan kode jenis catatan.
- `PaParticipantReadService::detailHasil()` mengirim kunci baru `kategori_catatan`, sehingga
  kotak isian admin lahir dari DATA, bukan dari dua kode yang dipaku di komponen. Sejak
  amandemen 7 September 2026 isinya dimensi yang benar-benar ditanyakan kepada penilai orang
  itu (ditambah dimensi yang sudah punya catatan), bukan seluruh isi master.
- `N_PA_Cycle_Question_Package_Item.PA_Result_Note_Type_Id` **tidak lagi ditulis maupun
  dibaca** siapa pun. Ia tetap disalin apa adanya oleh penyalinan cetak biru siklus supaya
  salinan benar-benar cermin masternya sampai kolomnya dicabut. Pilihannya juga dicabut dari
  layar master data: field yang tidak menentukan apa pun lebih menyesatkan daripada tidak ada.
- `config/pa_catatan_kualitatif.php` dihapus.

## Yang tersisa untuk manusia

Dua kolom, satu constraint, dan satu tabel menunggu pencabutan — semuanya `DROP`:

- `N_PA_Result_Note.PA_Result_Note_Type_Id` beserta foreign key-nya,
- `N_PA_Cycle_Question_Package_Item.PA_Result_Note_Type_Id`,
- `CK_PA_Cycle_Question_Item_Peran`, yang berbunyi
  `PA_Competency_Id IS NULL OR PA_Result_Note_Type_Id IS NULL` — aturan "kompetensi ATAU
  jenis catatan, jangan keduanya" yang tidak berlaku lagi sejak perannya dibaca dari
  `Flag_Berskor` kompetensinya sendiri,
- tabel `N_PA_Result_Note_Type` itu sendiri.

Sampai dicabut, constraint itu tidak mengganggu: baris baru hanya mengisi kompetensinya, jadi
syaratnya selalu terpenuhi. Yang perlu diingat, ia **menolak** pengisian kompetensi pada baris
lama yang masih memegang jenis catatan — itulah sebabnya DML-2026-106 mengosongkan penunjuk
lamanya dalam pernyataan yang sama.

Dijalankan **sesudah** data barunya terbukti benar di production, bukan sebelumnya.

## Rollback

`drop.sql` mengembalikan `N_PA_Result_Note` ke keadaan sebelumnya. Periksa lebih dulu:

```sql
SELECT COUNT(*) FROM N_PA_Result_Note WHERE PA_Result_Note_Type_Id IS NULL;
```

Bila hasilnya bukan nol, ada baris yang lahir sesudah perubahan dan sama sekali tidak mengisi
kolom lama. Mengembalikan `NOT NULL` akan gagal, dan membuang barisnya berarti menghapus
catatan yang sudah dibaca orang.
