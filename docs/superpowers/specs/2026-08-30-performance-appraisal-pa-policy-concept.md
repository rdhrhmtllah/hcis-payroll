# PA Policy, Master Kategori Rater, dan PA Policy Detail

- **Tanggal:** 2026-08-30
- **Status:** Struktur as-built DDL-2026-064 sudah DEV_DONE pada Web_HRIS
  dengan prefix final `N_PA_*`; SQL Tracker/production 064 masih pending
- **Modul:** KPI
- **Submodul:** Performance Appraisal
- **Fitur:** Policy
- **Nama tabel target:** `N_PA_Rater_Policy`, `N_PA_Rater_Category`, dan `N_PA_Rater_Policy_Detail`

## 1. Ringkasan keputusan desain

`N_PA_Rater_Policy` adalah header atau blueprint aturan Performance Appraisal. Satu baris menyatakan identitas, level sasaran, versi, status, dan periode berlaku suatu Policy.

Tabel ini **tidak menyimpan detail rater**. Daftar kategori rater dikendalikan oleh master konseptual `N_PA_Rater_Category` pada bagian 11. Pemakaian kategori, jumlah rater, bobot, kewajiban, dan cara mengambil kandidat ditempatkan pada child table `N_PA_Rater_Policy_Detail` yang dirancang pada bagian 12.

> **Prefix:** DDL-2026-064 revisi r2 membuat `N_PA_Rater_Policy`,
> `N_PA_Rater_Category`, dan `N_PA_Rater_Policy_Detail`. Nama itu final dan berlaku di
> development maupun production. Revisi r1 sempat memakai `N_HRIS_PA_*`, tetapi
> artifact-nya diamandemen di tempat sebelum masuk SQL Tracker sehingga tidak ada
> change rename dan production tidak pernah melihat nama lama.

Pemisahan header dan detail dipilih agar:

- satu policy dapat memiliki banyak kategori rater;
- versi policy dapat dikelola tanpa menduplikasi identitas dan metadata header;
- aturan kandidat dapat berubah tanpa mencampur policy dengan relasi karyawan aktual;
- policy dapat dipakai ulang oleh lebih dari satu appraisal cycle, lalu di-snapshot pada saat cycle disiapkan.

Struktur kolom dan nama fisik sudah diimplementasikan melalui DDL-2026-064.
Cycle, participant, assignment, form, dan result tetap berada di luar scope
change tersebut.

## 2. Tujuan tabel

`N_PA_Rater_Policy` menjadi sumber blueprint untuk menjawab pertanyaan berikut:

- policy appraisal apa yang berlaku untuk level tertentu;
- versi policy mana yang aktif pada tanggal acuan cycle;
- sejak kapan dan sampai kapan policy berlaku;
- dari policy versi mana sebuah versi baru diturunkan;
- siapa yang membuat, memperbarui, dan mengaktifkan policy.

Policy tidak menunjuk langsung ke seorang rater atau satu baris relasi organisasi. Rater aktual baru ditentukan ketika participant appraisal pada suatu cycle diproses.

## 3. Rancangan kolom konseptual

| Kolom | Tipe konseptual | Nullable | Fungsi |
|---|---|---:|---|
| `Id_PA_Rater_Policy` | `int identity` | Tidak | Primary key internal policy. |
| `Kode_Policy` | `varchar(50)` | Tidak | Business key stabil dan mudah dibaca, misalnya `PA-STAFF`. Keunikan final masih perlu diputuskan apakah per versi atau lintas versi. |
| `Nama_Policy` | `nvarchar(150)` | Tidak | Nama policy yang ditampilkan kepada pengguna. |
| `Deskripsi` | `nvarchar(500)` | Ya | Penjelasan singkat ruang lingkup dan tujuan policy. |
| `ID_Level` | `bigint` | Tidak | Referensi level HCIS yang menjadi sasaran policy pada rancangan awal. Mengacu ke `HRIS_Level.ID_Level`. |
| `Versi` | `int` | Tidak | Nomor versi policy. Versi baru disarankan dibuat ketika policy aktif perlu diubah. |
| `PA_Rater_Policy_Sumber_Id` | `int` | Ya | Self-reference ke policy sumber ketika policy dibuat dari salinan atau versi sebelumnya. |
| `Status_Policy` | `varchar(20)` | Tidak | Status lifecycle konseptual, misalnya `DRAFT`, `ACTIVE`, `INACTIVE`, atau `ARCHIVED`. Daftar nilai divalidasi aplikasi, bukan dikunci sebagai enum database. |
| `Berlaku_Dari` | `date` | Tidak | Tanggal pertama policy dapat dipilih untuk cycle atau participant. |
| `Berlaku_Sampai` | `date` | Ya | Tanggal akhir berlaku. `NULL` berarti belum ditentukan tanggal berakhir. |
| `Catatan_Versi` | `nvarchar(500)` | Ya | Alasan perubahan atau ringkasan perbedaan dibanding versi sumber. |
| `Flag_Aktif` | `char(1)` | Tidak | Penanda administratif `Y`/`T` bahwa record masih dapat digunakan. Berbeda dari status approval/lifecycle policy. |
| `Created_At` | `datetime2` | Tidak | Waktu pembuatan record. |
| `Created_By` | `int` | Ya | Aktor pembuat. Kandidat sumber aktor adalah `KPI_Users.Id_Users`; adopsi final perlu dikonfirmasi. `NULL` dapat dipertimbangkan untuk proses sistem. |
| `Updated_At` | `datetime2` | Ya | Waktu perubahan terakhir. |
| `Updated_By` | `int` | Ya | Aktor perubahan terakhir. |
| `Activated_At` | `datetime2` | Ya | Waktu policy resmi diaktifkan. |
| `Activated_By` | `int` | Ya | Aktor yang mengaktifkan policy. |

### Kolom yang sengaja tidak ditempatkan pada header

Header tidak menyimpan kolom berikut:

- tipe/kategori rater;
- jenis Organization Relationship;
- bobot rater;
- minimum atau maksimum jumlah rater;
- employee/rater tertentu;
- urutan pemilihan kandidat;
- appraisal cycle atau participant tertentu;
- komposisi nilai KPI dibanding nilai PA.

Data tersebut merupakan detail aturan, konfigurasi cycle, atau hasil assignment—bukan identitas policy.

## 4. Contoh isi data

Contoh berikut hanya untuk membantu review dan **bukan data seed**.

| Id | Kode Policy | Nama Policy | ID Level | Level ilustratif | Versi | Sumber | Status | Berlaku Dari | Berlaku Sampai | Aktif |
|---:|---|---|---:|---|---:|---:|---|---|---|:---:|
| 101 | `PA-PRAMUKARYA` | PA Pramukarya | 1 | NON CLERK/PRAMUKARYA | 1 | `NULL` | `ACTIVE` | 2026-01-01 | `NULL` | Y |
| 102 | `PA-STAFF` | PA Staff | 2 | STAFF | 1 | `NULL` | `ACTIVE` | 2026-01-01 | `NULL` | Y |
| 108 | `PA-MANAGER` | PA Manager | 8 | MANAGER | 1 | `NULL` | `ACTIVE` | 2026-01-01 | 2026-12-31 | Y |
| 109 | `PA-MANAGER` | PA Manager | 8 | MANAGER | 2 | 108 | `DRAFT` | 2027-01-01 | `NULL` | Y |

Contoh baris 108 dan 109 menunjukkan bahwa identitas policy dapat dipertahankan, sedangkan revisinya dibuat sebagai record versi baru. Detail kategori dan bobot rater versi 2 akan disimpan sebagai kumpulan baris detail tersendiri yang mengacu ke baris 109.

## 5. Relasi konseptual

```text
HRIS_Level (1)
    |
    | level sasaran
    v
N_PA_Rater_Policy (1) ──memiliki──> (*) N_PA_Rater_Policy_Detail
                                            ^
                                            | kategori terkontrol
                                            |
                                N_PA_Rater_Category (1)

N_PA_Rater_Policy_Detail
    |
    | memilih sumber utama bila relevan
    v
N_HRIS_Organization_Relationship_Type (0..1)
    |
    | mengklasifikasikan
    v
N_HRIS_Organization_Relationship (*)
    |
    | menghasilkan kandidat pada tanggal acuan
    v
Participant Rater Assignment (snapshot per cycle; rancangan berikutnya)
```

### Relasi ke level HCIS

Rancangan awal memakai `ID_Level bigint NOT NULL` sebagai logical foreign key ke `HRIS_Level.ID_Level`, karena kolom tersebut merupakan primary key master level saat ini.

Satu baris policy berlaku untuk satu level. Jika kelak satu policy perlu dipakai banyak level, perubahan ke tabel mapping harus menjadi change terpisah; schema 064 tetap memakai satu `ID_Level` per policy.

### Relasi ke Organization Relationship

`N_PA_Rater_Policy` tidak berelasi langsung ke `N_HRIS_Organization_Relationship`. Header Policy harus tetap generik dan dapat digunakan lintas Cycle maupun lintas susunan organisasi.

Hubungan terjadi melalui detail policy:

1. Header menentukan blueprint dan level sasaran.
2. Detail memilih kategori dari `N_PA_Rater_Category`, lalu menentukan metode dan sumber kandidat dari Organization Relationship bila relevan.
3. Saat participant appraisal dibentuk, resolver membaca `N_HRIS_Organization_Relationship` yang efektif pada tanggal acuan.
4. Kandidat ditinjau atau dipilih sesuai aturan detail.
5. Hasil final disimpan sebagai Participant Rater Assignment dan di-snapshot untuk cycle tersebut.

Relasi organisasi dapat lintas divisi, departemen, jabatan, dan struktur matrix/cross-functional. PA Policy tidak boleh menambahkan syarat bahwa subject dan related employee harus berada pada unit yang sama.

## 6. Batas tanggung jawab header dan detail

### `N_PA_Rater_Policy` — dokumen ini

- identitas policy;
- level sasaran;
- versi dan asal versi;
- status serta periode berlaku;
- audit pembuatan, perubahan, dan aktivasi.

### `N_PA_Rater_Category` — bagian 11

- vocabulary kategori appraisal yang terkontrol;
- kode, nama, definisi, dan status kategori;
- identitas stabil untuk policy, snapshot, assignment, dan reporting;
- tidak memiliki bobot atau aturan resolver per policy.

### `N_PA_Rater_Policy_Detail` — bagian 12

- kategori rater;
- jenis atau metode resolusi Organization Relationship;
- bobot;
- minimum/maksimum rater;
- wajib/opsional;
- urutan atau prioritas fallback;
- aturan khusus untuk self, atasan berjenjang, peer, bawahan, atau kandidat manual.

Dengan batas ini, perubahan bobot atau tipe rater tidak memperbesar header dan tidak mengubah data relasi organisasi aktual.

## 7. Alur penggunaan policy

1. Admin membuat header policy berstatus `DRAFT` untuk satu level.
2. Admin mengisi baris-baris `PA Policy Detail`.
3. Sistem memvalidasi total bobot, kelengkapan aturan, periode, dan konflik policy aktif.
4. Policy diaktifkan setelah review.
5. Saat appraisal cycle dibuka, sistem memilih policy berdasarkan level participant dan tanggal acuan.
6. Detail policy membaca kandidat dari Organization Relationship atau sumber lain sesuai metodenya.
7. Admin atau sistem memfinalkan rater.
8. Policy, detail yang relevan, dan rater final di-snapshot ke konteks cycle agar perubahan master berikutnya tidak mengubah appraisal yang sedang berjalan.

## 8. Keputusan yang masih terbuka

1. **Cardinality level:** apakah satu policy hanya untuk satu `ID_Level`, atau satu policy dapat dipakai oleh banyak level melalui tabel mapping?
2. **Scope perusahaan:** apakah policy global untuk seluruh perusahaan atau perlu `Kode_Perusahaan`/mapping perusahaan?
3. **Business key:** apakah `Kode_Policy` unik global, atau kombinasi `Kode_Policy + Versi` yang unik?
4. **Policy aktif yang tumpang tindih:** apakah hanya boleh ada satu versi aktif per level pada tanggal yang sama?
5. **Immutability:** apakah policy yang sudah aktif atau sudah dipakai cycle wajib immutable dan hanya dapat diganti melalui versi baru? Rekomendasi awal: ya.
6. **Lifecycle approval:** apakah aktivasi cukup oleh admin, atau memerlukan approval berjenjang dan audit approval terpisah?
7. **Tanggal acuan:** pemilihan policy memakai tanggal mulai cycle, tanggal akhir cycle, tanggal assignment, atau tanggal appraisal participant dibuat?
8. **Level khusus:** bagaimana fallback untuk `FREELANCE`, `DIREKTUR`, `DIREKTUR UTAMA`, `NOT SET`, dan `BELUM`?
9. **Role specialist:** bagaimana membedakan role seperti Officer Specialist bila master level saja belum cukup—berdasarkan jabatan, mapping khusus, atau pilihan manual?
10. **SELF dan relasi turunan:** apakah kategori self tidak memakai Organization Relationship Type, dan apakah atasan tingkat dua dihitung oleh resolver dari relasi direct supervisor berantai?
11. **Composition score:** apakah persentase KPI versus PA berada pada policy ini, policy composition terpisah, atau konfigurasi/snapshot cycle? Rekomendasi awal: jangan dimasukkan ke header sebelum model scoring disepakati.
12. **Audit actor:** apakah seluruh kolom `*_By` memakai `KPI_Users.Id_Users`, dan bagaimana representasi aktor sistem/job?
13. **Copy/version behavior:** ketika membuat versi baru, apakah seluruh detail otomatis disalin sebagai draft?
14. **Page ownership aplikasi:** modul KPI (`Id_Grup_Fitur=2`) sudah
    terverifikasi, tetapi belum ada page khusus PA. Source inspection menunjukkan
    `MasterPerformance` (`/performance-kpi`) adalah legacy Performance Range,
    sehingga PA perlu page mapping baru sebelum implementasi route/UI.

## 9. Batasan draft

- DDL/DML development berada pada DDL-2026-064; production belum berubah.
- Constraint, index, nullability, dan panjang fisik final didokumentasikan pada table docs KPI.
- Cycle, participant, assignment, question bank, form section, dan scoring result belum didesain penuh.
- Contoh data tidak boleh dipakai sebagai seed tanpa review dan change record terpisah.
- Nama `N_PA_Rater_Policy`, `N_PA_Rater_Category`, dan `N_PA_Rater_Policy_Detail` memakai
  prefix domain PA dan sudah menjadi nama fisik development.

## 10. Basis verifikasi

Verifikasi read-only dilakukan pada development `Web_HRIS` tanggal 2026-08-30:

- master group aktif menunjukkan modul KPI dengan `Id_Grup_Fitur = 2`;
- `HRIS_Level.ID_Level` tersedia sebagai primary key bertipe `bigint`;
- `N_HRIS_Organization_Relationship_Type` dan `N_HRIS_Organization_Relationship` tersedia sebagai fondasi sumber kandidat lintas organisasi;
- modul KPI aktif (`Id_Grup_Fitur=2`) adalah ownership boundary; page
  `MasterPerformance` tidak menjadi page PA karena mengelola Performance Range.

## 11. Rancangan konseptual master kategori rater

### 11.1 Tujuan dan tanggung jawab

`N_PA_Rater_Category` adalah master controlled vocabulary untuk peran seorang rater dalam perhitungan dan pelaporan Performance Appraisal. Master ini mencegah nama atau kode kategori diketik bebas pada setiap Policy, sehingga kategori tidak salah kaprah, tidak terlupa, dan tetap konsisten lintas Policy, Cycle, assignment, serta hasil.

Master kategori bertanggung jawab atas:

- ID dan business code kategori yang stabil;
- nama dan definisi bisnis kategori appraisal;
- status kategori masih dapat dipilih atau tidak;
- identitas kategori yang konsisten untuk reporting dan snapshot.

Master kategori **tidak** menyimpan:

- bobot, minimum/maksimum rater, atau aturan per level;
- jenis Organization Relationship yang wajib digunakan;
- employee yang menjadi kandidat atau assignment aktual;
- metode resolusi, fallback, eligibility, workload, atau izin override;
- periode policy atau cycle.

Data tersebut tetap menjadi tanggung jawab PA Policy Detail, resolver, atau snapshot cycle sesuai domainnya.

### 11.2 Rancangan kolom konseptual

| Kolom | Tipe konseptual | Nullable | Fungsi |
|---|---|---:|---|
| `Id_PA_Rater_Category` | `int identity` | Tidak | Primary key internal kategori rater. Nilai inilah yang direferensikan PA Policy Detail. |
| `Kode_Kategori` | `varchar(40)` | Tidak | Business key unik dan stabil: `ATASAN`, `BAWAHAN`, `PEER`, atau `SELF`. |
| `Nama_Kategori` | `nvarchar(100)` | Tidak | Label bisnis kategori. |
| `Deskripsi` | `nvarchar(500)` | Tidak | Definisi peran kategori dalam appraisal agar tidak bergantung pada penafsiran nama. |
| `Flag_Aktif` | `char(1)` | Tidak | `Y`/`T`, menentukan apakah kategori dapat dipilih untuk policy baru. Kategori nonaktif tetap dipertahankan untuk histori. |
| `Created_At` | `datetime2` | Tidak | Waktu pembuatan kategori. |
| `Created_By` | `int` | Ya | Aktor pembuat; mengikuti keputusan audit actor pada PA Policy. |
| `Updated_At` | `datetime2` | Ya | Waktu perubahan terakhir. |
| `Updated_By` | `int` | Ya | Aktor perubahan terakhir. |

Master ini tidak memerlukan effective date pada rancangan awal. Perubahan makna kategori yang sudah digunakan tidak dilakukan in-place; buat kode kategori baru atau kelola perubahan melalui proses versioning yang disetujui.

### 11.3 Kandidat kategori awal dan contoh data

Empat baris berikut adalah seed idempoten DDL-2026-064.

| Kode | Nama | Definisi peran appraisal | Aktif |
|---|---|---|:---:|
| `ATASAN` | Atasan | Rater menilai sebagai atasan participant. | Y |
| `BAWAHAN` | Bawahan | Rater menilai sebagai bawahan participant. | Y |
| `PEER` | Rekan Sejawat | Rater menilai sebagai rekan sejawat participant. | Y |
| `SELF` | Diri Sendiri | Participant menilai dirinya sendiri. | Y |

Kategori seperti `SECOND_LEVEL_SUPERVISOR`, `FUNCTIONAL_SUPERVISOR`, atau `PROJECT_STAKEHOLDER` belum otomatis menjadi master awal. Kategori tersebut ditambahkan hanya bila bisnis memang ingin nilainya dihitung dan dilaporkan sebagai kelompok penilai tersendiri. Bila hanya menjadi cara mencari kandidat untuk kategori yang sudah ada, cukup diletakkan sebagai Relationship Type atau resolver pada Policy Detail.

### 11.4 Perbedaan kategori appraisal dan Organization Relationship Type

| Aspek | `N_PA_Rater_Category` | `N_HRIS_Organization_Relationship_Type` |
|---|---|---|
| Pertanyaan yang dijawab | “Dalam kapasitas apa nilai rater ini dihitung dan dilaporkan?” | “Apa fakta hubungan organisasi antara dua karyawan?” |
| Domain pemilik | KPI / Performance Appraisal | Fondasi organisasi lintas modul |
| Contoh | `ATASAN`, `BAWAHAN`, `PEER`, `SELF` | `ATASAN`, `BAWAHAN`, `PEER` |
| Menyimpan bobot | Tidak | Tidak |
| Dipakai oleh | Policy Detail, assignment snapshot, hasil per kategori | Resolver kandidat lintas modul |
| Dapat memiliki kode teks sama | Ya, tetapi ID dan semantiknya tetap berbeda | Ya, karena berada pada namespace/domain berbeda |

Kategori appraisal `ATASAN`, `BAWAHAN`, dan `PEER` dipetakan melalui Policy Detail ke Organization Relationship Type dengan kode sepadan. `SELF` memakai FK organization type `NULL`. Arah tidak ditafsirkan melalui metode tambahan.

### 11.5 Relasi dan validasi master

```text
N_PA_Rater_Category (1)
              |
              | dipilih oleh
              v
N_PA_Rater_Policy_Detail (*)
              |
              | resolver policy-specific
              v
N_HRIS_Organization_Relationship_Type (0..1 sumber utama)
```

Validasi utama master:

1. `Kode_Kategori` wajib unik, tidak kosong, dan tidak dapat diketik langsung pada PA Policy Detail.
2. Kode kategori yang sudah direferensikan tidak boleh diganti karena menjadi identitas reporting dan integrasi.
3. Definisi kategori wajib menjelaskan peran penilaian, bukan algoritma kandidat.
4. Hanya kategori aktif yang dapat dipilih untuk policy draft baru.
5. Menonaktifkan kategori tidak menghapus detail, snapshot, assignment, atau hasil historis.
6. Kategori yang sudah direferensikan tidak dihapus; nonaktifkan untuk mempertahankan histori.
7. Menambahkan kategori baru belum otomatis membuatnya berlaku. Kategori baru hanya aktif pada appraisal setelah direferensikan oleh PA Policy Detail yang valid.

### 11.6 Keputusan master yang masih terbuka

1. Siapa pemilik governance master dan siapa yang berwenang menambah atau menonaktifkan kategori?
2. Apakah empat kandidat awal sudah cukup untuk versi pertama?
3. Apakah atasan tingkat dua menjadi kategori `SECOND_LEVEL_SUPERVISOR` tersendiri atau hanya variasi resolver/sumber kandidat?
4. Apakah functional supervisor dinilai sebagai kategori tersendiri atau digabung ke kategori atasan yang sudah ada?
5. Apakah kode master wajib berbahasa Inggris seperti contoh, sementara label dapat berbahasa Indonesia?
6. Apakah perubahan nama/definisi perlu histori master tersendiri selain audit update biasa?

## 12. Rancangan konseptual PA Policy Detail

### 12.1 Tujuan dan grain data

`N_PA_Rater_Policy_Detail` adalah child table dari `N_PA_Rater_Policy`. Satu baris mewakili **satu kategori rater yang berlaku dalam satu versi Policy**.

Contoh grain:

```text
Policy PA Manager versi 1 + kategori SELF      = satu baris detail
Policy PA Manager versi 1 + kategori ATASAN    = satu baris detail
Policy PA Manager versi 1 + kategori PEER      = satu baris detail
Policy PA Manager versi 1 + kategori BAWAHAN   = satu baris detail
```

Detail menyimpan aturan appraisal, bukan fakta hubungan organisasi dan bukan rater aktual. Kategori diperoleh melalui FK ke `N_PA_Rater_Category`. Walaupun kategori appraisal dan jenis relasi organisasi dapat memiliki kode teks yang sama, keduanya adalah entitas berbeda:

- kategori rater menjelaskan **peran penilai dalam hasil appraisal**;
- Organization Relationship Type menjelaskan **fakta atau sumber kandidat dalam organisasi**;
- kandidat organisasi dibaca langsung memakai type eksplisit `ATASAN`, `BAWAHAN`, atau `PEER`; `SELF` menunjuk participant sendiri.

### 12.2 Rancangan kolom konseptual

| Kolom | Tipe konseptual | Nullable | Fungsi |
|---|---|---:|---|
| `Id_PA_Rater_Policy_Detail` | `int identity` | Tidak | Primary key internal detail policy. |
| `PA_Rater_Policy_Id` | `int` | Tidak | Foreign key konseptual ke `N_PA_Rater_Policy.Id_PA_Rater_Policy`. Menentukan pemilik dan versi Policy. |
| `PA_Rater_Category_Id` | `int` | Tidak | Foreign key konseptual ke `N_PA_Rater_Category.Id_PA_Rater_Category`. Detail tidak menyimpan kode/nama kategori bebas. |
| `Organization_Relationship_Type_Id` | `int` | Ya | Foreign key konseptual ke `N_HRIS_Organization_Relationship_Type.Id_Organization_Relationship_Type` untuk sumber relasi utama. `NULL` diperbolehkan untuk `SELF`, `MANUAL`, atau resolver yang tidak memakai satu jenis relasi langsung. |
| `Flag_Izinkan_Manual` | `char(1)` | Tidak | `Y`/`T`, menentukan apakah admin boleh menambah atau mengganti kandidat dengan alasan dan audit. Bukan izin akses pengguna. |
| `Bobot_Persen` | `decimal(5,2)` | Tidak | Kontribusi kategori rater terhadap nilai PA participant. Bobot antar-detail aktif dalam satu policy harus berjumlah 100%. |
| `Min_Rater` | `smallint` | Tidak | Jumlah minimum assignment rater final yang harus tersedia untuk kategori ini sebelum participant/cycle dapat dikunci. |
| `Maks_Rater` | `smallint` | Ya | Jumlah maksimum assignment; `NULL` berarti tanpa batas. |
| `Min_Rater_Selesai` | `smallint` | Ya | Threshold selesai eksplisit; `NULL` berarti seluruh rater valid yang ditugaskan harus selesai. |
| `Urutan_Tampil` | `smallint` | Tidak | Urutan kategori pada konfigurasi, review readiness, dan laporan. Tidak menentukan urutan approval. |
| `Catatan_Aturan` | `nvarchar(500)` | Ya | Penjelasan aturan atau alasan khusus yang perlu terlihat saat review admin. Bukan tempat menyimpan ekspresi program bebas. |
| `Flag_Aktif` | `char(1)` | Tidak | `Y`/`T`, penanda bahwa detail masuk dalam validasi dan materialisasi policy. |
| `Created_At` | `datetime2` | Tidak | Waktu pembuatan detail. |
| `Created_By` | `int` | Ya | Aktor pembuat; mengikuti keputusan audit actor pada header. |
| `Updated_At` | `datetime2` | Ya | Waktu perubahan terakhir. |
| `Updated_By` | `int` | Ya | Aktor perubahan terakhir. |

`Flag_Wajib` tidak ditambahkan karena dapat diturunkan dari `Min_Rater > 0`. Menyimpan keduanya berisiko menghasilkan konfigurasi bertentangan, misalnya `Flag_Wajib = T` tetapi `Min_Rater = 2`.

### 12.3 Foreign key dan relasi konseptual

| Dari | Ke | Cardinality | Makna |
|---|---|---|---|
| `N_PA_Rater_Policy_Detail.PA_Rater_Policy_Id` | `N_PA_Rater_Policy.Id_PA_Rater_Policy` | Banyak ke satu | Seluruh detail mengikuti versi, lifecycle, periode, dan level dari header. |
| `N_PA_Rater_Policy_Detail.PA_Rater_Category_Id` | `N_PA_Rater_Category.Id_PA_Rater_Category` | Banyak ke satu | Menentukan peran rater dalam perhitungan dan pelaporan appraisal. |
| `N_PA_Rater_Policy_Detail.Organization_Relationship_Type_Id` | `N_HRIS_Organization_Relationship_Type.Id_Organization_Relationship_Type` | Banyak ke nol/satu | Menentukan jenis relasi utama yang dibaca resolver bila kategori menggunakan fakta relasi organisasi. |
| Resolver policy detail | `N_HRIS_Organization_Relationship` | Tidak menjadi FK langsung | Membaca baris relasi aktual yang efektif pada tanggal acuan untuk memperoleh kandidat. |
| Policy detail terpilih | Snapshot participant/cycle | Sumber salinan | Bobot, min/maks, completion rule, dan kategori dibekukan ketika cycle dikunci. |

Keunikan konseptual minimum adalah satu `PA_Rater_Category_Id` aktif per `PA_Rater_Policy_Id`. Beberapa penilai dalam satu kategori — misalnya dua atasan — **tidak** ditangani dengan menggandakan baris kategori dan bobot di sini, melainkan lewat beberapa baris `N_PA_Assignment` yang membawa `Bobot_Dalam_Kategori` masing-masing (K18). Policy hanya menyatakan bobot antar-kategori dan batas jumlah penilai; siapa dan seberapa berbobot ditentukan per peserta oleh admin.

Schema 064 tidak memakai cascade delete. Policy yang telah digunakan cycle tidak boleh hilang beserta detailnya; lifecycle dan strategi arsip menjadi aturan aplikasi.

### 12.4 Contoh isi data

Contoh berikut konsisten dengan header policy 102 (`PA-STAFF` versi 1) dan 108 (`PA-MANAGER` versi 1). ID Relationship Type ditampilkan sebagai kode agar contoh mudah dibaca; implementasi fisik kelak memakai ID master yang sesuai. Seluruh angka hanya ilustrasi dan bukan seed.

| Id Detail | Policy Id | Category (hasil join) | Relationship Type | Manual | Bobot | Min/Maks | Min Selesai | Urutan |
|---:|---:|---|---|:---:|---:|---:|---:|---:|
| 1021 | 102 | `SELF` | `NULL` | T | 10.00 | 1/1 | `NULL` | 1 |
| 1022 | 102 | `ATASAN` | `ATASAN` | Y | 60.00 | 1/1 | `NULL` | 2 |
| 1023 | 102 | `PEER` | `PEER` | Y | 30.00 | 2/3 | 2 | 3 |
| 1081 | 108 | `SELF` | `NULL` | T | 10.00 | 1/1 | `NULL` | 1 |
| 1082 | 108 | `ATASAN` | `ATASAN` | Y | 45.00 | 1/1 | `NULL` | 2 |
| 1083 | 108 | `PEER` | `PEER` | Y | 15.00 | 2/3 | 2 | 3 |
| 1084 | 108 | `BAWAHAN` | `BAWAHAN` | Y | 30.00 | 2/5 | 2 | 4 |

Penjelasan contoh:

- `SELF` tidak membutuhkan Organization Relationship karena kandidatnya adalah participant sendiri.
- Kategori `ATASAN` membaca Relationship Type `ATASAN`; Related Employee adalah atasan participant sesuai kontrak type.
- `PEER` lebih dahulu membaca relasi `PEER`; bila belum cukup, resolver dapat merekomendasikan karyawan dengan atasan yang sama.
- Kategori `BAWAHAN` membaca Relationship Type eksplisit `BAWAHAN`; Related Employee adalah bawahan participant.
- Metadata `Flag_Relasi_Setara` tidak mengubah mapping kategori PA: `PEER=Y`, sedangkan `ATASAN` dan `BAWAHAN=T`. PA tetap mereferensikan ID type eksplisit dan tidak memakai flag ini untuk menafsirkan arah. Nama fisik tersebut sudah tersedia di development melalui DDL-2026-063.
- Policy Manager versi 1 tidak memiliki kategori atasan tingkat dua; **tidak adanya baris berarti kategori tersebut tidak berlaku**, bukan berbobot nol tersembunyi.
- Total bobot detail aktif untuk policy 102 dan policy 108 masing-masing tepat 100%.
- Semua rater valid di dalam kategori yang sama mempunyai bobot internal sama. Policy tidak menyimpan pilihan metode penggabungan nilai.

### 12.5 Validasi bisnis utama

#### Validasi saat menyimpan draft

1. `PA_Rater_Policy_Id` harus menunjuk header yang masih dapat diedit.
2. `PA_Rater_Category_Id` wajib menunjuk kategori master yang aktif dan tidak boleh duplikat dalam policy yang sama.
3. `Bobot_Persen` berada pada rentang 0–100 dengan maksimal dua angka desimal.
4. `Min_Rater >= 1`; `Maks_Rater` boleh `NULL`, atau wajib `>= Min_Rater` bila diisi.
5. `Min_Rater_Selesai` boleh `NULL` untuk mewajibkan seluruh assignment selesai; bila diisi wajib `>= 1` dan tidak melebihi `Maks_Rater` yang terisi.
6. Jika `Bobot_Persen > 0`, rekomendasi awalnya `Min_Rater >= 1` dan `Min_Rater_Selesai >= 1`; konfigurasi berbobot tetapi opsional memerlukan aturan redistribusi eksplisit.
7. `Organization_Relationship_Type_Id` wajib dan harus aktif untuk kategori `ATASAN`, `BAWAHAN`, dan `PEER`.
8. Kategori `SELF` harus tanpa Relationship Type, dengan `Min_Rater = Maks_Rater = 1`; `Min_Rater_Selesai=NULL` berarti satu assignment Self tersebut wajib selesai.

Draft boleh sementara memiliki total bobot belum 100% agar admin dapat menyusun policy bertahap. Kondisi itu harus terlihat jelas sebagai validation warning dan tidak boleh lolos aktivasi.

#### Gate sebelum aktivasi policy

1. Policy memiliki minimal satu detail aktif.
2. Jumlah `Bobot_Persen` seluruh detail aktif tepat `100.00`.
3. Tidak ada detail aktif berbobot positif tanpa minimum rater dan minimum selesai yang sah.
4. Setiap kombinasi kategori dan Relationship Type konsisten; `SELF` wajib `NULL`.
5. Semua referensi master aktif dan dapat digunakan.
6. Simulasi kandidat dijalankan terhadap populasi level terkait untuk menampilkan peserta yang tidak akan memenuhi minimum; hasil simulasi adalah readiness report, bukan perubahan relasi otomatis.

#### Gate assignment dan hasil

1. Sebelum cycle dikunci, jumlah assignment final per participant dan kategori harus memenuhi `Min_Rater` serta tidak melebihi `Maks_Rater`.
2. Participant tidak boleh menjadi rater dirinya sendiri kecuali kategori `SELF`.
3. Rater yang sama tidak boleh diduplikasi dalam kategori yang sama.
4. Kandidat manual harus menyimpan alasan, aktor, waktu, dan provenance override pada assignment, bukan pada policy detail.
5. Nilai kategori hanya dihitung jika jumlah penilaian valid yang selesai memenuhi `Min_Rater_Selesai`.
6. Jika minimum selesai tidak terpenuhi, participant masuk status tertahan untuk keputusan admin; sistem tidak boleh diam-diam merata-ratakan respons yang kurang atau menormalisasi ulang bobot.
7. Setelah kategori lolos gate, skor kategori selalu berupa rata-rata nilai seluruh rater valid, lalu dikalikan `Bobot_Persen` snapshot.

#### Perhitungan fixed normalized average

Semua kategori memakai satu aturan yang tidak dapat dikonfigurasi:

```text
Skor_Kategori = AVG(Nilai_Rater_Valid)
Kontribusi_Kategori = Skor_Kategori × (Bobot_Persen / 100)
```

Setiap rater valid di dalam kategori mempunyai bobot internal yang sama, yaitu `1 / jumlah rater valid`. Tidak ada mode `SUM`, median, trimmed mean, atau weighted-per-rater.

Contoh kategori Atasan memiliki dua rater valid dengan nilai 80 dan 70:

```text
Skor_Kategori_Atasan = (80 + 70) / 2 = 75
```

Nilai 75 baru dikalikan bobot kategori. Jika `Bobot_Persen=45`, kontribusinya terhadap nilai akhir adalah `75 × 45% = 33,75`.

Aturan yang sama berlaku untuk kategori Bawahan dan Peer. Kategori Self hanya memiliki satu rater, sehingga rata-ratanya sama dengan nilai Self tersebut. Penilaian yang tidak valid, dibatalkan, atau belum selesai tidak masuk pembilang maupun jumlah rater; gate `Min_Rater_Selesai` harus tetap terpenuhi sebelum perhitungan dilakukan.

### 12.6 Resolusi kandidat dari Organization Relationship

Resolver menggunakan fakta organisasi yang efektif pada tanggal acuan participant/cycle:

```text
Policy Detail
  ├─ Kategori SELF
  │    └─ participant sendiri
  └─ ATASAN / BAWAHAN / PEER
       └─ subject employee = participant
          related employee = kandidat sesuai arti type
```

Filter kandidat minimal:

- relationship aktif dan efektif pada tanggal acuan;
- employee kandidat memenuhi eligibility appraisal;
- tidak self kecuali kategori `SELF`;
- menghormati primary/direct versus functional/project/matrix sesuai metode;
- tidak memaksakan kesamaan divisi, departemen, level, atau jabatan;
- kandidat dan alasan pemilihannya dapat dijelaskan kepada admin;
- jumlah akhir mengikuti batas min/maks dan pemeriksaan workload.

`Bobot_Persen` tidak pernah dibaca dari Organization Relationship. Bobot appraisal hanya berasal dari policy detail dan kemudian dari snapshot cycle.

### 12.7 Lifecycle, versioning, dan snapshot cycle

1. Selama header `DRAFT`, detail dapat ditambah, diubah, dinonaktifkan, atau disusun ulang.
2. Aktivasi header hanya berhasil setelah seluruh activation gate detail lulus.
3. Rekomendasi awal: header dan detail yang sudah `ACTIVE` bersifat immutable. Perubahan dibuat dengan menyalin header beserta detail ke versi baru berstatus `DRAFT`.
4. Saat cycle menyiapkan participant, sistem memilih header berdasarkan level snapshot dan tanggal acuan.
5. Sistem menyalin identitas policy/version dan seluruh nilai detail yang relevan—kategori, relationship type, bobot, min/maks, serta completion rule—ke konfigurasi/snapshot cycle. Fixed normalized average adalah aturan sistem, bukan field konfigurasi.
6. Resolver memakai Organization Relationship yang berlaku pada tanggal acuan untuk menghasilkan kandidat beserta provenance.
7. Admin memfinalkan assignment, lalu cycle dikunci.
8. Setelah lock, perubahan PA Policy, PA Policy Detail, HRIS Level, maupun Organization Relationship tidak mengubah konfigurasi dan assignment cycle lama.
9. Perubahan darurat setelah lock harus melalui controlled amendment dengan alasan, aktor, waktu, before/after, dan perhitungan dampak; bentuk tabel amendment belum termasuk rancangan ini.

Snapshot diperlukan karena policy adalah reusable blueprint, sedangkan cycle adalah catatan historis. Menyimpan FK ke policy saja tidak cukup untuk menjamin hasil lama tetap dapat direproduksi.

### 12.8 Keputusan PA Policy Detail yang masih terbuka

1. **Kategori atasan tingkat dua:** apakah memakai master category baru, digabung ke kategori atasan, atau hanya dibedakan pada resolver/snapshot?
2. **Atasan tingkat dua:** bila kelak digunakan, apakah memerlukan kategori dan desain sumber kandidat baru pada change terpisah?
3. **Peer fallback:** apakah kekurangan kandidat selalu membutuhkan konfirmasi manual atau fitur sumber tambahan baru?
5. **Optional weighted category:** apakah kategori dengan bobot positif boleh memiliki `Min_Rater = 0`; jika boleh, bagaimana bobot yang tidak terisi didistribusikan?
6. **Minimum selesai:** apakah `Min_Rater_Selesai` harus selalu sama dengan `Min_Rater`, atau boleh lebih kecil untuk kategori dengan banyak assignment?
7. **Duplicate cross-category:** apakah orang yang sama boleh menjadi rater untuk participant yang sama pada dua kategori berbeda, misalnya direct sekaligus functional supervisor?
8. **Manual override:** kategori mana yang boleh diganti manual, siapa yang berwenang, dan approval apa yang diperlukan?
9. **Primary relationship:** apakah resolver selalu memprioritaskan `Flag_Primary = Y`, dan kapan secondary relationship boleh dipakai?
10. **Eligibility dan konflik:** sumber aturan kandidat aktif, masa kerja minimum, status cuti, konflik kepentingan, dan pengecualian pasangan penilai belum ditentukan.
11. **Workload cap:** apakah batas maksimum assignment per rater diatur global, per cycle, per level, atau per policy detail?
12. **Tanggal acuan:** keputusan header tentang tanggal acuan juga menentukan relationship mana yang efektif ketika resolver berjalan.
13. **Snapshot fisik:** apakah snapshot detail berada pada konfigurasi cycle per level atau disalin per participant untuk mendukung override individual?

### 12.9 Rekomendasi untuk tahap berikutnya

Sebelum DDL, keputusan minimum yang perlu disetujui adalah:

1. daftar awal, definisi, dan governance `N_PA_Rater_Category`;
2. aturan ketika kandidat relationship type langsung belum memenuhi minimum;
3. aturan kategori atasan tingkat dua dan peer;
4. Policy kategori berbobot yang tidak memperoleh minimum rater;
5. aturan immutability serta bentuk snapshot cycle.

Setelah keputusan tersebut stabil, rancangan berikutnya dapat beralih ke PA Cycle dan participant policy snapshot tanpa mencampur fakta organisasi dengan konfigurasi appraisal.
