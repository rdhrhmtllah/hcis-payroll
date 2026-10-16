# DDL-2026-084 — Daftar pengecualian awal peserta PA

| | |
|---|---|
| Change ID | `DDL-2026-084-performance-appraisal-pre-exclusion` |
| Jenis | DDL |
| Artifact revision | **r1** |
| Forward | `database/ddl/DDL-2026-084-performance-appraisal-pre-exclusion/forward.sql` |
| Rollback | `.../drop.sql` |
| Bergantung pada | DDL-2026-066 (`N_PA_Cycle`) |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Masalah yang diselesaikan

`N_PA_Participant.Alasan_Dikecualikan` sudah bisa menampung pengecualian — tetapi baris
peserta baru ada **sesudah** penyiapan berjalan.

Admin yang sudah tahu sejak awal bahwa sekelompok orang tidak ikut dinilai — pegawai
magang, orang yang sedang tugas belajar, direksi — tidak punya tempat menuliskannya sampai
958 baris terlanjur terbentuk. Ia harus menyiapkan dulu, lalu mengecualikan satu per satu
dari daftar panjang.

Tabel ini membalik urutannya: **daftar dibuat lebih dulu, penyiapan menghormatinya.**

## Keputusan bentuk

**Tabel sendiri, bukan kolom di `Karyawan`.** Pengecualian ini milik **satu siklus**, bukan
sifat orangnya. Orang yang tidak dinilai tahun ini karena sedang tugas belajar tetap dinilai
tahun depan. Kolom di `Karyawan` akan terbawa selamanya dan tak seorang pun ingat
mencabutnya.

**Tidak menunjuk `N_PA_Participant`** — justru karena barisnya belum ada; itu seluruh
maksudnya. Yang ditunjuk adalah siklus dan pasangan (perusahaan, karyawan), sama seperti
yang dipakai `N_PA_Participant` sendiri untuk menyebut orang.

**`Alasan` NOT NULL, dan itu bukan formalitas.** Pengecualian tanpa alasan menghasilkan
orang yang hilang dari penilaian tanpa siapa pun bisa menjawab kenapa — dan pertanyaan itu
selalu datang setahun kemudian, ketika yang memutuskannya sudah lupa.

**Dicabut dengan menonaktifkan, bukan menghapus.** Baris yang dihapus membuat *"kenapa dia
dinilai padahal dulu dikecualikan"* mustahil dijawab.

**Indeks unik TERSARING** pada `Flag_Aktif = 'Y'`, bukan unique constraint biasa: baris yang
sudah dicabut boleh berulang, sehingga orang yang sama bisa dikecualikan lagi kemudian
dengan alasan lain, dan riwayat keduanya tetap ada.

## Satu aturan yang menentukan perilakunya

Pengecualian awal diperiksa **paling awal** di `nilaiKelayakan()` — sebelum aturan otomatis
mana pun (FREELANCE, resign, level tanpa aturan).

Urutannya disengaja: **keputusan manusia menang atas aturan mesin.** Kalau seseorang sudah
ditulis di daftar dengan alasan tertentu, alasan **itu** yang tercatat — bukan "Level
FREELANCE dikecualikan" yang kebetulan juga berlaku. Yang membaca setahun kemudian perlu
tahu bahwa ada orang yang memutuskannya, bukan aturan.

## Pratinjau dan penyiapan membaca daftar yang sama

Keduanya memanggil `nilaiKelayakan()` yang sama, tetapi masing-masing membaca daftarnya
sendiri lewat `praKecuali()`. Kalau salah satu lupa membacanya, angkanya berselisih tepat
pada orang-orang yang paling diperhatikan admin — dan ada uji yang menuntut keduanya sama.

Daftar dibaca **sekali per penyiapan**, bukan sekali per karyawan. Pada 958 orang,
menanyakannya per baris berarti 958 perjalanan ke database untuk jawaban yang sama.

## Verifikasi lewat EFEK

Diuji ujung ke ujung pada data nyata, di dalam transaksi yang dibatalkan:

| | Diikutkan | Dikecualikan |
|---|---|---|
| Sebelum daftar | 859 | 99 |
| Sesudah 3 orang ditambahkan | 857 | 101 |
| Penyiapan sungguhan | **857** | **101** |

Turun 2 dari 3 karena satu orang memang **sudah** dikecualikan aturan lain — dan alasan yang
tercatat untuknya adalah alasan admin, bukan alasan otomatis.

Tabelnya berdiri dengan tiga indeks: `PK`, `UX_PA_PreExcl_Cycle_Karyawan` (unik, tersaring),
`IX_PA_PreExcl_Cycle_Aktif`.

## Endpoint

| Alamat | Guna |
|---|---|
| `GET /cycles/{id}/pra-kecuali` | daftar, dengan penanda karyawan yang sudah tidak aktif |
| `POST /cycles/{id}/pra-kecuali` | menambah sekelompok orang dengan satu alasan |
| `POST /cycles/{id}/pra-kecuali/{id}/cabut` | mencabut satu baris |

Ketiganya digerbangi `SUNTING_PESERTA` — daftar ini hanya bermakna sebelum siklus dikunci.
Sesudah peserta terbentuk, jalurnya `PUT /participants/{id}/status`.

## Rollback

`drop.sql` membuang tabelnya. **Daftar pengecualian yang sudah ditulis ikut hilang** — kalau
siklus berjalan sudah memakainya, salin dulu isinya.
