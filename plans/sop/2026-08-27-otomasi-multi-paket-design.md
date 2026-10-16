# Otomasi SOP — satu aturan, banyak paket

Tanggal: 2026-08-27
Status: **RANCANGAN — MENUNGGU ACC**

Halaman: `/sop/admin/kebijakan` — tab **Aturan Penugasan** (`SopKebijakan.vue`)
Perubahan basis data: **DDL-2026-053** (dieksekusi ke dev 2026-08-27; nomor 052
sudah dipakai `DDL-2026-052-sop-dokumen-mode-distribusi`)

---

## 1. Masalah

### 1.1 Aturan terikat tepat satu paket

`N_SOP_Assignment_Rule` punya kolom `Id_SOP_Paket int NOT NULL` — satu kolom,
satu paket. Tidak ada tabel jembatan. Yang jamak di dalam satu aturan hanyalah
grup cohort-nya:

```
Aturan  =  1 paket  ×  N grup target (OR)
           ^^^^^^^      grup = divisi AND dept AND level AND jabatan AND lokasi AND tipe
```

Kebutuhan nyatanya kebalikan arah: **satu cohort mewajibkan banyak paket.**
Divisi Finance — dikerucutkan departemen, level, dan lokasi kerja — wajib
membaca lima paket SOP sekaligus.

### 1.2 Akibatnya kalau dipaksa jalan sekarang

Lima paket = lima aturan terpisah, masing-masing mengulang kriteria cohort yang
sama persis.

| Akibat | Keterangan |
|---|---|
| Kriteria tersalin lima kali | Cakupan Finance berubah → sunting lima kali. Tak ada apa pun yang menjaga kelimanya tetap identik; drift-nya senyap. |
| Riwayat & daftar membengkak | Lima header penjadwalan, lima baris riwayat tiap scan harian, lima kartu berkriteria kembar. |
| Keseragaman jadwal dijaga tangan | Tenggat & reminder ditetapkan per aturan; kalau maunya seragam, sistem tak menjaminnya. |

### 1.3 Dan arah sebaliknya juga tertutup

Penjaga "satu paket hanya boleh punya satu aturan"
(`SopAssignmentRuleService.php:78-86`) berarti kalau salah satu paket Finance
juga wajib untuk Divisi Legal, tak bisa dibuat aturan kedua untuk paket itu —
satu-satunya jalan adalah menambahkan grup OR "Legal" ke aturan pemilik paket
tersebut.

Efek akhirnya, pengelompokan di layar terbalik dari cara orang bekerja. Admin
berpikir *"cohort Finance wajib paket A–E"*, sistem memaksanya masuk sebagai
*"paket A wajib untuk Finance+Legal+…"*, lima kali, dari lima kartu berbeda.

### 1.4 Yang TIDAK bermasalah

Mesin penerapannya sudah dikeraskan dan tidak dibongkar:

- apply dipecah chunk 100 per transaksi, chunk gagal dicatat bukan ditelan
  (`SopAssignmentRuleService.php:238-262`)
- `claim()` menerima RUNNING/FAILED, ditambah hook `failed()` dan
  `reapStale(120)` di dua command cron — run nyangkut bisa pulih sendiri
- normalizer menolak grup tanpa kriteria, sehingga satu klik tak sengaja tak
  bisa menyasar seluruh headcount
- cron aktif: `sop:rule-scan` 00:10, `sop:reminder-scan` 00:30

### 1.5 Keadaan data

`N_SOP_Assignment_Rule` = **0 baris**. `N_SOP_Automation_Run` = **0 baris**
(dev, 2026-08-27). Fiturnya belum pernah dipakai, jadi tidak ada migrasi data —
tetapi juga tidak ada satu pun bukti lapangan bahwa mesinnya benar. Uji menjadi
satu-satunya bukti yang tersedia.

---

## 2. Keputusan

Lima keputusan diambil bersama pengguna pada 2026-08-27. Jangan ditawar ulang.

### 2.1 Tenggat: seragam, boleh di-override per paket

Satu setelan tenggat + reminder induk berlaku untuk semua paket dalam aturan.
Tiap paket boleh menyalakan "pakai tenggat sendiri".

Bentuk data yang ada sudah menampungnya: `N_SOP_Penjadwalan` menyimpan
`Tanggal_Deadline`, `Flag_Reminder`, dan `Irama_Reminder_Hari` di tingkat
header, dan `Id_SOP_Paket`-nya NOT NULL. Satu aturan lima paket = lima header,
masing-masing boleh berbeda jadwalnya. Tidak perlu kolom baru untuk menyimpan
hasilnya.

### 2.2 Tumpang tindih: dilarang sejak formulir

Satu paket hanya boleh diklaim oleh satu aturan. Penyimpanan yang mengklaim
paket milik aturan lain ditolak.

Konsekuensi yang diterima sadar: kasus paket-C-wajib-untuk-Finance-DAN-Legal
tidak terlayani. Paket itu harus digandakan menjadi dua paket di Master Paket.

Imbalannya, mesin pencabutan tidak berubah sama sekali — `cabutMilikRule()`
tidak perlu tahu apa pun tentang aturan lain, dan tidak ada risiko akses hilang
karena aturan A mencabut orang yang masih diwajibkan aturan B.

### 2.3 Riwayat: satu baris per paket, dengan baris induk

Satu aktivasi atas aturan berisi lima paket menghasilkan satu baris induk plus
lima baris anak. Paket yang gagal terlihat MERAH sendirian tanpa menyeret
empat lainnya.

### 2.4 Membuang paket dari aturan aktif: penugasan dibiarkan

Karyawan tetap memegang paket itu dan progresnya utuh; yang berhenti hanya
penugasan BARU. Paket langsung bebas diklaim aturan lain.

Ini konsisten dengan janji yang sudah tertulis di tombol Nonaktifkan —
*"Penugasan yang ada tetap tercatat."* Konsekuensinya: tidak ada cara massal
melepas paket itu dari orang-orang tersebut lewat halaman ini; jalurnya lewat
Cabut Akses di Penjadwalan.

### 2.5 Penyimpanan daftar paket: tabel jembatan

Bukan di dalam `Draft_Config_JSON`. Alasannya tunggal dan menentukan: keputusan
2.2 menjadikan penjaga formulir sebagai **satu-satunya** pengaman terhadap
tumpang tindih, dan hanya tabel jembatan yang membuat penjaga itu bisa
ditegakkan mesin basis data lewat filtered unique index. Daftar di JSON berarti
scan + parse seluruh aturan tiap simpan, tanpa index, dan bisa dibobol dua
penyimpanan bersamaan.

### 2.6 Pengecualian berbasis kriteria (ditambahkan 2026-08-27)

Sebelum ini satu-satunya cara mengecualikan adalah mengetik NIK satu per satu.
"Seluruh Finance kecuali level Manager" harus dibongkar jadi daftar nama — dan
daftar itu langsung basi begitu ada yang naik jabatan.

Config mendapat `exclude_groups`, berbentuk **sama persis** dengan `groups`:
AND di dalam satu grup, OR antar grup. Layar memakai satu komponen kartu untuk
keduanya, sehingga grup target dan grup pengecualian tak bisa lagi berbeda
diam-diam.

Menyertakan lewat filter **tidak** ditambahkan: `groups` sudah OR antar grup,
jadi "sertakan juga seluruh Legal" adalah grup target kedua. Yang benar-benar
tak punya jalan hanyalah pengecualian.

Penjaga grup kosong berlaku untuk keduanya, dengan pesan masing-masing. Grup
target kosong menyasar seluruh headcount; grup pengecualian kosong
mengecualikan seluruh headcount, dan hasilnya aturan yang tampak aktif tetapi
tak pernah menugaskan siapa pun — kegagalan yang tak menghasilkan satu pun
galat.

### 2.7 Angka orang vs angka penugasan pada baris TOTAL

`matched` dan `excluded` adalah jumlah ORANG dan sama di setiap baris paket,
karena cohort tidak bergantung paket. Baris TOTAL karena itu **tidak**
menjumlahkannya — penjumlahan atas lima paket memberi lima kali headcount dan
terbaca sebagai jangkauan aturan. Kolom sisanya adalah jumlah PENUGASAN dan
memang dijumlahkan; bedanya dinyatakan di bawah label TOTAL.

Konsekuensi sampingannya: cohort kini diresolusi **sekali** per apply/pratinjau,
bukan sekali per paket. `resolveTargets()` bukan kueri murah untuk diulang lima
kali demi jawaban yang identik.

### 2.8 Perorangan dipilih, bukan diketik (ditambahkan 2026-08-27)

Textarea NIK pada Sertakan/Kecualikan diganti pemilih orang yang bisa dicari
nama maupun NIK-nya. Mengetik kode menuntut admin tahu kodenya lebih dulu, dan
satu salah ketik lolos tanpa gejala — kode yang tak cocok siapa pun diam-diam
tak berefek, dan pengecualian yang dikira terpasang sebenarnya tidak.

Bentuk config **tidak berubah**: `include_karyawan` dan `exclude_karyawan`
tetap daftar `Kode_Karyawan`. Yang berganti hanya cara mengisinya.

Daftar orang dimuat sekali lewat `/options` dan disaring di layar, bukan lewat
pencarian ke server. `HcisSelect` menyaring lokal, dan menambahkan jalur
pencarian jarak jauh ke komponen kit bersama menyentuh puluhan halaman lain
demi satu pemakai. Populasinya 958 karyawan aktif (dev, 2026-08-27), ter-cache
30 menit. Bila headcount tumbuh sampai daftar ini terasa berat, yang benar
adalah menambah pencarian jarak jauh ke `HcisSelect` sebagai inisiatif
tersendiri — bukan menambal dengan plafon senyap di endpoint ini.

Kode tersimpan yang sudah tak ada di daftar aktif tetap ditampilkan, dengan
penanda *"tidak aktif / tak dikenal"*. Pengecualian yang hilang dari pandangan
tetapi masih bekerja adalah yang paling sulit dilacak ketika seseorang bertanya
kenapa ia tak menerima SOP.

---

## 3. Bentuk data — DDL-2026-053

### 3.1 Tabel baru `N_SOP_Assignment_Rule_Paket`

Satu baris per (aturan, paket).

| Kolom | Tipe | Guna |
|---|---|---|
| `Id_SOP_Assignment_Rule_Paket` | int IDENTITY PK | |
| `Id_SOP_Assignment_Rule` | int NOT NULL → FK `N_SOP_Assignment_Rule` | pemilik |
| `Id_SOP_Paket` | int NOT NULL → FK `N_SOP_Master_Paket` | paket yang diklaim |
| `Urutan` | int NOT NULL DEFAULT 1 | urutan tampil dan urutan apply |
| `Flag_Override_Jadwal` | char(1) NOT NULL DEFAULT 'T' | ikut induk / pakai tenggat sendiri |
| `Deadline_Mode` | varchar(10) NULL | NONE \| RELATIVE \| ABSOLUTE — dipakai bila override |
| `Deadline_Hari` | int NULL | bila mode RELATIVE |
| `Tanggal_Deadline` | date NULL | bila mode ABSOLUTE |
| `Flag_Reminder` | char(1) NULL | bila override |
| `Irama_Reminder_Hari` | int NULL | bila override |
| `Last_Apply_Status` | varchar(20) NOT NULL DEFAULT 'IDLE' | status apply **paket ini sendiri** |
| `Last_Apply_Error` | nvarchar(1000) NULL | |
| `Last_Applied_At` | datetime2 NULL | |
| `Created_By` / `Created_At` | int NULL / datetime2 NOT NULL | |
| `Updated_By` / `Updated_At` | int NULL / datetime2 NULL | |
| `Flag_Aktif` | char(1) NOT NULL DEFAULT 'Y' | |

`Last_Apply_Status` per paket adalah yang membuat keputusan 2.3 mungkin —
tanpanya, kegagalan satu paket hanya bisa dinyatakan di tingkat aturan.

Kolom aktor memakai `int` (Id_users), bukan `varchar(100)` bawaan, mengikuti
`N_SOP_Assignment_Rule.Created_By` yang sudah `int`. `AGENTS.md` mewajibkan
identitas aktor dipastikan sebelum DDL; sumbernya di sini adalah `Auth::id()`
yang diteruskan controller.

### 3.2 Index

```sql
CREATE UNIQUE INDEX UX_SOP_Assignment_Rule_Paket_Paket
    ON N_SOP_Assignment_Rule_Paket (Id_SOP_Paket)
    WHERE Flag_Aktif = 'Y';

CREATE INDEX IX_SOP_Assignment_Rule_Paket_Rule
    ON N_SOP_Assignment_Rule_Paket (Id_SOP_Assignment_Rule, Urutan);
```

Filtered unique index itulah penjaga keputusan 2.2. Ditegakkan mesin basis
data, bukan hanya `if` di PHP, sehingga dua admin yang menyimpan bersamaan tak
bisa sama-sama lolos.

### 3.3 Perubahan kolom yang ada

| Objek | Perubahan | Catatan |
|---|---|---|
| `UQ_N_SOP_Assignment_Rule_Paket` | **DILEPAS** | Ditemukan saat eksekusi, tidak terlihat dari kode maupun dari `INFORMATION_SCHEMA.COLUMNS`. UNIQUE CONSTRAINT pada `Id_SOP_Paket` — penjaga versi lama. **Wajib** dilepas: SQL Server menganggap NULL bentrok dengan NULL, jadi begitu kolomnya berhenti diisi, seluruh tabel hanya bisa memuat SATU baris aturan. Dijalankan atas persetujuan eksplisit pengguna. |
| `N_SOP_Assignment_Rule.Id_SOP_Paket` | NOT NULL → **NULL**, berhenti ditulis | **Tidak** di-drop oleh agent. `AGENTS.md` melarang agent mengeksekusi ALTER yang membuang data. Statement drop-nya ditulis di `drop.sql` untuk dijalankan pengguna bila mau. Tabelnya 0 baris, jadi tak ada data yang hilang — tapi aturannya tetap berlaku. Harus SESUDAH DROP CONSTRAINT di atas. |
| `N_SOP_Automation_Run` | + `Id_SOP_Paket int NULL` (FK) | paket yang dikerjakan baris ini; NULL untuk baris induk |
| `N_SOP_Automation_Run` | + `Id_Run_Induk int NULL` (self-FK) | menunjuk baris induk; NULL untuk baris induk itu sendiri |

### 3.4 Utang yang ikut dibayar: hash draft

`SopAssignmentRuleConfig::hash()` menghitung sha256 dari config JSON. Dengan
daftar paket pindah ke tabel, perubahan daftar paket **tidak** akan mengubah
`Draft_Hash`, sehingga `Draft_Berubah` — lencana "draft berbeda dari yang
aktif" di layar — berbohong.

Perbaikannya: hash dihitung dari config **ditambah snapshot daftar paket**
(pasangan id + urutan + setelan override, terurut deterministik).

---

## 4. Mesin apply

### 4.1 Loop per paket

`applyQueued()` berubah dari satu paket menjadi loop atas baris paket, urut
`Urutan`. Tiap paket adalah satuan kegagalan yang berdiri sendiri:

```
untuk tiap paket dalam aturan (urut Urutan):
    ├ resolve jadwal  = setelan override paket, atau setelan induk
    ├ gerbang tenggat absolut lewat → paket ini NEEDS_ATTENTION, lanjut paket berikut
    ├ impact(paket)   → siapa yang baru, dipertahankan, tercakup, dilepas
    ├ buatHeader(paket, opts)      ← satu header per paket
    ├ assignKeHeader per chunk 100 ← tidak berubah
    └ tulis Last_Apply_Status paket itu (SUCCESS / FAILED / NEEDS_ATTENTION)
```

**Paket yang sudah tersemai tidak dianulir karena paket berikutnya gagal.** Ini
bukan kebijakan baru — itu janji yang sudah dipegang jalur chunk di
`SopAssignmentRuleService.php:238-262`, dinaikkan satu tingkat.

### 4.2 Status di tingkat aturan

Turunan dari status paket-paketnya:

| Kondisi | `Last_Apply_Status` aturan |
|---|---|
| Semua paket SUCCESS | `SUCCESS` |
| Ada paket FAILED atau NEEDS_ATTENTION | `NEEDS_ATTENTION` |

Tidak ada status `PARTIAL` baru. `NEEDS_ATTENTION` sudah ada, sudah dihitung
kartu "Perhatian" di ringkasan (`SopAdminKebijakanController.php:52`), dan sudah
punya warna serta label di layar.

Hook `failed()` di `ApplySopAssignmentRule` — yang dipanggil ketika job menyerah
sebelum `handle()` sempat menutup dirinya — harus ikut menandai baris paket yang
masih `IDLE` atau `RUNNING` menjadi `FAILED`. Tanpa itu, aturan tampak
`NEEDS_ATTENTION` tetapi paket-paketnya tak satu pun menjelaskan sebabnya.

### 4.3 Pencabutan tidak berubah

`cabutMilikRule($ruleId, $idPaket, …)` sudah menerima paket sebagai parameter,
jadi ia cukup dipanggil di dalam loop. Karena keputusan 2.2 menjamin satu paket
hanya dimiliki satu aturan, tidak ada pemeriksaan lintas-aturan yang perlu
ditambahkan.

### 4.4 Perbaikan kuadratik yang ikut sekarang

`impact()` memanggil `in_array($code, $matched, true)` di dalam filter atas
seluruh penugasan aktif paket (`SopAssignmentRuleService.php:449-453`) —
O(|penugasan| × |cocok|). Dengan lima paket, biaya itu dikali lima. Diganti
`array_flip` menjadi lookup O(1).

Ikut sekarang, bukan nanti: perubahannya tiga baris dan berada persis di jalur
yang sedang dibongkar.

---

## 5. Riwayat eksekusi

### 5.1 Induk dan anak

```
[induk]  Id_SOP_Paket=NULL   Id_Run_Induk=NULL    "Aturan Finance · 5 paket"
  ├ [anak] Id_SOP_Paket=A    Id_Run_Induk=induk   SUCCESS  +12 baru
  ├ [anak] Id_SOP_Paket=B    Id_Run_Induk=induk   SUCCESS  +12 baru
  ├ [anak] Id_SOP_Paket=C    Id_Run_Induk=induk   FAILED   galat di sini
  └ …
```

Baris **induk** yang memegang penjaga konkurensi. `claim()`,
`hasActiveForRule()`, `reapStale()`, dan hook `failed()` tetap bekerja pada satu
baris seperti sekarang — tidak ada pengaman yang sudah ada yang perlu diubah
bentuknya. Baris anak murni catatan hasil.

### 5.2 Join Nama_Paket

`history()` mengambil `Nama_Paket` lewat `r.Id_SOP_Paket` langsung, menggantikan
join `run → aturan → paket` (`SopAutomationRunService.php:135-136`) yang putus
begitu aturan tak lagi menunjuk satu paket.

### 5.3 Plafon dilaporkan, bukan disembunyikan

Frontend tidak pernah mengirim `limit`, jadi diam-diam terpakai 50. Dengan baris
anak, 50 akan penuh lima kali lebih cepat.

- Plafon dihitung atas **baris induk**; baris anak ikut serta tanpa memakan jatah.
- Kaki tabel menyebut angkanya: *"menampilkan 50 eksekusi terakhir dari 214"*.

Konvensi repo sejak audit paging target penjadwalan: setiap pembatasan wajib
dilaporkan, tidak boleh memotong diam-diam.

Sekalian dirapikan: controller mengizinkan `limit` sampai 200
(`SopAdminKebijakanController.php:178`) sementara service menjepitnya ke 100
(`SopAutomationRunService.php:142`).

---

## 6. Bentuk layar

### 6.1 Seksi 1 — daftar paket

Dropdown "Paket SOP Published" tunggal, yang terkunci setelah tersimpan,
digantikan **daftar paket**. Tiap baris satu kartu ringkas:

- nama paket + lencana Publik/Terbatas
- pegangan urut (naik/turun)
- tombol buang
- switch **"Pakai tenggat sendiri"** — mati berarti ikut induk; nyala membuka
  setelan tenggat + reminder mini di dalam baris itu

Paket yang sudah diklaim aturan lain **tidak muncul** di pilihan. Bila lolos
karena balapan, unique index menolaknya dan pesan galatnya menyebut nama aturan
pemiliknya — bukan "gagal menyimpan" yang buta.

Daftarnya **tetap bisa disunting setelah aturan aktif** — paket boleh ditambah
maupun dibuang. Kunci `:disabled="!!selectedId"` yang sekarang menempel pada
dropdown paket dicabut; keputusan 2.4 justru menuntut pembuangan paket bisa
dilakukan tanpa menonaktifkan aturannya lebih dulu.

Setelan override sebuah paket boleh bermode `NONE` sekalipun setelan induk
punya tenggat — artinya "paket ini tanpa tenggat". Override adalah penggantian
utuh, bukan penambahan di atas induk.

### 6.2 Seksi 4 — setelan induk

Tetap ada, berganti peran menjadi setelan induk, dengan keterangan tegas:
berlaku untuk semua paket kecuali yang di-override.

### 6.3 Pratinjau dampak

Enam angka hari ini (`Cocok / Baru / Dipertahankan / Tercakup / Dicabut /
Ditahan`) menjadi **tabel per paket** dengan enam kolom yang sama, plus baris
TOTAL. Paket yang tertahan gerbang tenggat absolut ditandai di barisnya sendiri,
sehingga admin tahu paket mana yang tidak akan jalan sebelum menekan Aktifkan.

Tombol "Aktifkan & Terapkan" tetap menuntut pratinjau lebih dulu.

### 6.4 Daftar aturan kiri

`Nama_Paket` diganti lencana "5 paket"; nama-nama paketnya muncul di panel kanan.

### 6.5 Remah yang dibersihkan

`resetRuleFilters` terdefinisi **dua kali** di objek `methods`
(`SopKebijakan.vue:1053` dan `:1280`) — yang kedua diam-diam menang.

---

## 7. Pengujian

Setiap penjaga wajib pernah terlihat **MERAH** sebelum dianggap sah. Repo ini
punya sejarah penjaga yang tak pernah menyala dan uji yang tak menguji apa pun.

| Uji | Menuntut |
|---|---|
| Tumpang tindih ditolak | Aturan B mengklaim paket milik aturan A → **ditolak**, pesan menyebut A |
| Kegagalan sebagian | 3 paket, yang tengah gagal → 2 tersemai utuh, aturan `NEEDS_ATTENTION`, 3 baris anak di riwayat |
| Override tenggat | Paket ber-override melahirkan header dengan `Tanggal_Deadline` berbeda dari saudaranya |
| Buang paket dari aturan aktif | Penugasan lama utuh, paket langsung bisa diklaim aturan lain |
| Hash jujur | Menambah/membuang paket **mengubah** `Draft_Hash` |
| Plafon dilaporkan | Riwayat > plafon → kaki tabel menyebut angka sebenarnya |

Lingkup jalan-uji adalah **suite SOP penuh**, bukan berkas baru saja:
`impact()`, `buatHeader()`, dan `assignKeHeader()` punya pemakai lain. Repo ini
pernah kena — satu penjaga baru di service bersama mematahkan 9 uji lama di 4
berkas dan baru ketahuan berhari-hari kemudian.

Vitest: `SopKebijakan.vue` — daftar paket, switch override, tabel pratinjau per
paket.

Sesuai kesepakatan sesi, `php artisan test`, `npx vitest`, dan `npm run build`
**ditahan sampai ACC**. Yang dijalankan selama penulisan hanya pemeriksaan
sintaksis statis (`php -l`, `node --check`).

---

## 8. Risiko

| Risiko | Sikap |
|---|---|
| 0 baris data = tak ada bukti lapangan | Tidak ada migrasi yang perlu ditulis, tetapi uji menjadi satu-satunya bukti bahwa mesin ini benar. |
| Antrean SQL Tracker | `database/ddl/status.json` berisi 59 catatan, semuanya `development=DONE` tapi `sql_tracker=PENDING` dan `production=PENDING`. DDL-2026-053 akan menumpuk di antrean yang sama. Keadaan berdiri repo, bukan akibat perubahan ini. |
| Paket C wajib untuk dua cohort | Tidak terlayani (keputusan 2.2). Jalannya: gandakan paket di Master Paket. |
| `Id_SOP_Paket` lama tertinggal nullable | Kolom yatim sampai pengguna menjalankan `drop.sql`. Tidak ada kode yang membacanya setelah perubahan ini. |

---

## 9. Berkas yang tersentuh

**Basis data**
- `database/ddl/DDL-2026-053-sop-aturan-multi-paket/forward.sql` (baru)
- `database/ddl/DDL-2026-053-sop-aturan-multi-paket/drop.sql` (baru)
- `database/ddl/status.json` (catatan baru)
- `docs/changes/DDL-2026-053-sop-aturan-multi-paket/README.md` (baru)

**Backend**
- `app/Services/LMS/SOP/SopAssignmentRuleService.php` — loop per paket, impact O(1), status turunan
- `app/Services/LMS/SOP/SopAutomationRunService.php` — induk/anak, join paket, plafon dilaporkan
- `app/Services/LMS/SOP/Support/SopAssignmentRuleConfig.php` — hash menyerap daftar paket
- `app/Http/Controllers/LMS/SOP/Governance/SopAdminKebijakanController.php` — validasi daftar paket, plafon selaras
- `app/Jobs/LMS/SOP/Automation/ApplySopAssignmentRule.php` — hook `failed()` menandai paket yang belum selesai

**Frontend**
- `resources/js/Pages/LMS/SOP/Governance/Admin/SopKebijakan.vue`
- komponen baru untuk baris paket (memecah berkas 1.455 baris)

**Uji**
- `tests/Feature/LMS/SOP/Uat/Alur024KebijakanOtomasiTest.php` (menyesuaikan)
- berkas uji baru untuk penjaga di tabel Bagian 7
- `resources/js/Pages/LMS/SOP/Governance/Admin/SopKebijakan.test.js`
