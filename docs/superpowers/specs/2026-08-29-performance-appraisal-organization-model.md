# Performance Appraisal — Rancangan Konseptual Data Organisasi

| | |
|---|---|
| **Status** | Brainstorming konseptual — **bukan spesifikasi DDL** |
| **Tanggal verifikasi** | 2026-08-29 |
| **Database yang dibaca** | `Web_HRIS` — development, host terkonfigurasi `team311.dyndns.info:1433` |
| **Ownership utama** | `ESS / Organization / Relationship` — fakta organisasi lintas fitur |
| **Konsumen pertama** | `KPI / Appraisal` — Policy dan assignment penilai |
| **Companion** | `docs/superpowers/specs/2026-08-29-performance-appraisal-design.md` |
| **Implementasi** | Tidak ada kode, DDL, DML, atau perubahan database dalam dokumen ini |

## 1. Tujuan dan keputusan utama

HCIS sudah mempunyai master `Karyawan`, Divisi/Sub Divisi, Level, dan Jabatan. Performance Appraisal tidak boleh membuat salinan master tersebut hanya untuk memperoleh struktur penilai.

Rancangan ini memisahkan tiga fakta yang memiliki lifecycle berbeda:

```text
MASTER EXISTING
Karyawan · Divisi · Level · Jabatan
                  │
                  ▼
ORGANIZATION_RELATIONSHIP
fakta relasi karyawan-ke-karyawan yang berlaku
                  │
                  ▼
RATER POLICY + DETAIL
aturan kategori, sumber kandidat, jumlah, dan bobot appraisal
                  │
          candidate resolution
                  │
          keputusan/review admin
                  ▼
PARTICIPANT RATER ASSIGNMENT
snapshot final siapa menilai siapa di dalam satu cycle
```

Keputusan desain:

1. `Organization_Relationship` menjadi tabel baru utama dan menyimpan **satu baris per relasi directional** antara dua karyawan.
2. Relasi organisasi adalah sumber **kandidat**, bukan assignment appraisal final.
3. Bobot appraisal tidak disimpan pada relasi organisasi. Bobot appraisal berasal dari Rater Policy dan dibekukan pada konfigurasi/assignment peserta per cycle.
4. Peer dan bawahan sebisa mungkin diturunkan dari relasi organisasi, bukan diduplikasi menjadi banyak fakta baru.
5. `Employee_Organization_Assignment` ditunda. Tabel itu baru dibutuhkan jika HCIS harus menyimpan histori effective-dated Divisi, Level, Jabatan, atau penempatan rangkap.
6. Perubahan relasi setelah cycle dikunci tidak mengubah assignment cycle tersebut. Cycle lama selalu membaca snapshot-nya sendiri.

## 2. Basis database dan ownership yang diverifikasi

Verifikasi read-only pada 2026-08-29 menemukan:

- Grup fitur `ESS` aktif dengan `Id_Grup_Fitur = 1`.
- Grup fitur `KPI` aktif dengan `Id_Grup_Fitur = 2`.
- Page organisasi existing `addKaryawanTeam` dipetakan ke grup `ESS`.
- `Karyawan.Kode_Karyawan` adalah identifier karyawan bertipe `varchar(255)`.
- `Karyawan.ID_Divisi_Sub_Divisi` menunjuk struktur Divisi/Sub Divisi yang dapat dibaca melalui `View_Divisi_Sub_Divisi`.
- `Karyawan.ID_Level_Jabatan` menunjuk kombinasi Level/Jabatan yang dapat dibaca melalui `View_Golongan_Sub_Golongan_Level_Jabatan` dan `HRIS_Level_Jabatan`.
- `Karyawan` tidak mempunyai kolom atasan langsung.
- `KPI_Approval_Flow_Performance` mempunyai bentuk pasangan requester–approver, tetapi semantiknya urutan approval dan data development-nya tercampur data uji. Tabel itu bukan master organisasi.
- `N_AMS_User_Leader_Teams` mempunyai beberapa konsep yang berguna sebagai referensi bentuk—tipe relasi, periode berlaku, primary, priority, dan scope—tetapi tabel tersebut kosong dan tidak boleh dianggap sumber organisasi aktif.

Relationship menjadi fondasi organisasi lintas modul. Ownership pemeliharaan awalnya berada pada `ESS / Organization / Relationship`, tetapi relasinya tidak memiliki constraint same-unit dan tidak membawa aturan akses khusus satu modul. Rater Policy dan assignment appraisal tetap dimiliki `KPI / Appraisal`; modul lain menentukan access/scope sendiri.

> Nama tabel di bawah adalah nama logis. Mengikuti konvensi baru repo, kandidat nama fisiknya adalah `N_ESS_Organization_Relationship`. Nama tersebut belum disetujui sebagai DDL dan tidak boleh dibuat dari dokumen ini.

## 3. Tabel utama: `Organization_Relationship`

### 3.1 Semantik baris

Satu baris menyatakan:

> Dalam rentang waktu tertentu, `Karyawan_Subjek` memiliki hubungan `Jenis_Relasi` terhadap `Karyawan_Terkait`.

Contoh directional:

```text
MGR-A --DIRECT_SUPERVISOR--> GM-01
MGR-A --FUNCTIONAL_SUPERVISOR--> DIR-FIN
MGR-A --PEER/COLLABORATOR--> MGR-B       # hanya jika peer perlu dipelihara eksplisit
STAFF-01 --DIRECT_SUPERVISOR--> MGR-A
```

Pada contoh terakhir, MGR-A adalah bawahan dari GM-01 bila dibaca dari sisi GM-01, dan STAFF-01 adalah bawahan MGR-A bila relasi dibaca secara terbalik. Tidak perlu membuat baris `MGR-A --SUBORDINATE--> STAFF-01` karena itu menduplikasi fakta yang sama.

### 3.2 Kolom konseptual

| Kolom | Tipe konseptual | Null | Fungsi |
|---|---|:---:|---|
| `Id_Organization_Relationship` | `int identity` | Tidak | Primary key relasi. |
| `Kode_Karyawan_Subjek` | `varchar(255)` | Tidak | Karyawan pemilik relasi; referensi logis ke `Karyawan.Kode_Karyawan`. |
| `Kode_Karyawan_Terkait` | `varchar(255)` | Tidak | Karyawan pada ujung relasi; referensi logis ke `Karyawan.Kode_Karyawan`. |
| `Kode_Jenis_Relasi` | `varchar(50)` | Tidak | Makna directional, misalnya `DIRECT_SUPERVISOR`, `FUNCTIONAL_SUPERVISOR`, atau `PEER`. Daftar dikelola backend/configuration, bukan `CHECK` constraint. |
| `Berlaku_Dari` | `date` | Tidak | Awal relasi berlaku. |
| `Berlaku_Sampai` | `date` | Ya | Akhir relasi berlaku; `NULL` berarti masih terbuka. |
| `Flag_Utama` | `char(1)` | Tidak | `Y/T`; menandai relasi primary di antara relasi sejenis. Untuk direct supervisor normalnya hanya satu primary aktif. |
| `Prioritas` | `int` | Tidak | Urutan resolusi bila beberapa relasi sejenis berlaku; bukan bobot appraisal. Default konseptual `1`. |
| `Sumber_Data` | `varchar(50)` | Tidak | Provenance, misalnya `MANUAL`, `IMPORT`, atau `KPI_APPROVAL_FLOW`. |
| `Referensi_Sumber` | `varchar(255)` | Ya | Identifier sumber/import untuk traceability tanpa menjadikannya authority. |
| `Catatan` | `nvarchar(500)` | Ya | Penjelasan relasi atau alasan pengecualian. Tidak boleh memuat rahasia atau data sensitif yang tidak perlu. |
| `Flag_Aktif` | `char(1)` | Tidak | `Y/T`; status administratif row. Validitas bisnis tetap memperhitungkan periode berlaku. |
| `Created_At` | `datetime2` | Tidak | Waktu pembuatan. |
| `Created_By` | `varchar(100)` | Tidak/terbuka | Actor pembuat. Identifier actor harus diputuskan sebelum DDL—belum boleh diasumsikan `Kode_Karyawan` atau `UserID_Web`. |
| `Updated_At` | `datetime2` | Ya | Waktu perubahan metadata terakhir. |
| `Updated_By` | `varchar(100)` | Ya | Actor perubahan; sumber identifier mengikuti keputusan audit actor. |
| `Cancelled_At` | `datetime2` | Ya | Opsional jika pembatalan administratif perlu dibedakan dari berakhir normal. |
| `Cancelled_By` | `varchar(100)` | Ya | Actor pembatalan. |

### 3.3 Kolom opsional yang tidak direkomendasikan untuk versi awal

| Kolom | Kapan layak dipakai | Batasan |
|---|---|---|
| `Bobot_Relasi_Default decimal(5,2)` | Bila bisnis mempunyai makna organisasi nyata, misalnya alokasi 70% ke atasan struktural dan 30% ke atasan matriks. | Tidak boleh menjadi bobot nilai appraisal. Jika hanya dipakai PA, tempatnya harus di Rater Policy/Participant Config. |
| `ID_Divisi_Sub_Divisi_Scope bigint` | Bila satu atasan fungsional hanya berlaku untuk scope unit tertentu. | Jangan diisi sekadar menyalin divisi karyawan. |
| `ID_Level_Jabatan_Scope bigint` | Bila relasi dibatasi pada suatu jabatan/level tertentu. | Membutuhkan use case nyata; tanpa itu menambah kombinasi keadaan yang sulit divalidasi. |
| `Metadata_Json nvarchar(max)` | Integrasi mempunyai metadata sumber yang bentuknya tidak stabil. | Bukan tempat menyimpan atribut bisnis inti yang seharusnya menjadi kolom terstruktur. |

Rekomendasi versi awal: jangan menambahkan bobot atau scope sebelum ada kasus bisnis yang tidak dapat direpresentasikan oleh tipe relasi, periode, primary, dan priority.

### 3.4 Jenis relasi awal

Set awal yang cukup untuk Performance Appraisal:

| Jenis | Disimpan atau diturunkan | Catatan |
|---|---|---|
| `DIRECT_SUPERVISOR` | Disimpan | Atasan struktural/Atasan 1. Maksimal satu primary aktif per subjek pada satu tanggal. |
| `FUNCTIONAL_SUPERVISOR` | Disimpan | Atasan matriks/fungsional. Dapat jamak sesuai aturan organisasi. |
| `PEER` atau `COLLABORATOR` | Opsional disimpan | Hanya bila peer eksplisit memang dipelihara organisasi. Default PA dapat merekomendasikan peer dari shared supervisor/unit. |
| `SUBORDINATE` | Diturunkan | Kebalikan `DIRECT_SUPERVISOR`; jangan disimpan dua kali. |
| `SECOND_LEVEL_SUPERVISOR` | Diturunkan | Atasan langsung dari atasan langsung; tidak harus menjadi fakta tersendiri. |

`PEER` perlu keputusan semantik. Orang dengan atasan sama adalah **kandidat peer**, tetapi belum tentu cukup sering bekerja bersama untuk menjadi rater. Karena itu sistem hanya merekomendasikan; admin tetap menetapkan.

### 3.5 Invarian konseptual

Sebelum implementasi, desain harus menjaga:

1. Subjek dan karyawan terkait tidak boleh sama untuk relasi supervisor/peer.
2. Satu subjek tidak boleh memiliki dua `DIRECT_SUPERVISOR` primary dengan periode aktif yang tumpang tindih.
3. `Berlaku_Sampai` tidak boleh lebih awal dari `Berlaku_Dari`.
4. Relasi supervisor tidak boleh membentuk siklus, misalnya A → B → C → A.
5. Row tidak dihapus untuk pergantian normal; periode lama ditutup dan row baru dibuat.
6. `Flag_Aktif = Y` tidak cukup untuk menentukan relasi berlaku. Query juga wajib memeriksa tanggal efektif.
7. Relasi hasil import harus membawa provenance dan tidak otomatis dianggap terverifikasi.
8. Pembacaan historis memakai tanggal acuan eksplisit, bukan selalu tanggal hari ini.

## 4. Contoh data konseptual

Contoh ini memakai kode rekaan dan tidak mewakili data karyawan nyata.

### 4.1 Baris yang disimpan

| Id | Subjek | Jenis relasi | Karyawan terkait | Berlaku | Utama | Prioritas | Sumber | Makna |
|---:|---|---|---|---|:---:|---:|---|---|
| 1 | `MGR-A` | `DIRECT_SUPERVISOR` | `GM-01` | 2026-01-01–NULL | Y | 1 | MANUAL | Atasan langsung Manager A adalah GM-01. |
| 2 | `MGR-A` | `FUNCTIONAL_SUPERVISOR` | `DIR-FIN` | 2026-01-01–NULL | Y | 1 | MANUAL | Atasan fungsional Manager A adalah Direktur Finance. |
| 3 | `MGR-A` | `PEER` | `MGR-B` | 2026-01-01–NULL | Y | 1 | MANUAL | Peer eksplisit karena kolaborasi lintas unit; row ini opsional. |
| 4 | `STAFF-01` | `DIRECT_SUPERVISOR` | `MGR-A` | 2026-01-01–NULL | Y | 1 | IMPORT | STAFF-01 melapor langsung kepada Manager A. |
| 5 | `STAFF-02` | `DIRECT_SUPERVISOR` | `MGR-A` | 2026-02-01–NULL | Y | 1 | IMPORT | STAFF-02 melapor langsung kepada Manager A. |
| 6 | `MGR-B` | `DIRECT_SUPERVISOR` | `GM-01` | 2026-01-01–NULL | Y | 1 | MANUAL | MGR-B berbagi atasan langsung dengan MGR-A. |

### 4.2 Kandidat yang dapat diturunkan untuk Manager A

| Kategori PA | Cara resolusi | Kandidat |
|---|---|---|
| Self | Peserta sendiri | `MGR-A` |
| Atasan 1 | `MGR-A --DIRECT_SUPERVISOR--> ?` | `GM-01` |
| Atasan 2 | Atasan langsung dari `GM-01` bila tersedia; alternatifnya functional supervisor sesuai policy | Bergantung relasi `GM-01` atau `DIR-FIN` |
| Peer | Peer eksplisit + karyawan lain yang mempunyai direct supervisor sama | `MGR-B` |
| Bawahan | Kebalikan relasi direct supervisor: `? --DIRECT_SUPERVISOR--> MGR-A` | `STAFF-01`, `STAFF-02` |

Contoh ini menunjukkan kenapa `SUBORDINATE` tidak perlu disimpan. Dua row bawahan sudah menjadi fakta organisasi yang lengkap dari perspektif anggota tim.

## 5. Relasi ke master existing

### 5.1 `Karyawan`

Kedua ujung `Organization_Relationship` secara logis menunjuk:

```text
Organization_Relationship.Kode_Karyawan_Subjek
    → Karyawan.Kode_Karyawan

Organization_Relationship.Kode_Karyawan_Terkait
    → Karyawan.Kode_Karyawan
```

Verifikasi implementasi berikutnya menemukan PK `Karyawan` bersifat komposit: `(Kode_Perusahaan, Kode_Karyawan)`. Karena itu FK fisik yang aman harus membawa kode perusahaan dan kode karyawan pada masing-masing ujung relationship; FK satu kolom ke `Kode_Karyawan` ditolak SQL Server. Lifecycle karyawan resign tetap tidak menghapus histori relasi.

Karyawan resign tidak menyebabkan histori relasi dihapus. Status aktif dipakai ketika menghasilkan kandidat baru, sedangkan cycle lama tetap dapat menampilkan snapshot historis.

### 5.2 Divisi dan Sub Divisi

Current organization assignment dibaca dari:

```text
Karyawan.ID_Divisi_Sub_Divisi
    → View_Divisi_Sub_Divisi.ID_DIVISI_SUB_DIVISI
    → HRIS_Divisi_Sub_Divisi / HRIS_Divisi / HRIS_Sub_Divisi
```

`Organization_Relationship` tidak menyalin ID divisi setiap karyawan. Divisi hanya dipakai saat resolver membutuhkan filter, misalnya rekomendasi peer dalam divisi yang sama.

### 5.3 Level dan Jabatan

Current level/jabatan dibaca melalui:

```text
Karyawan.ID_Level_Jabatan
    → HRIS_Level_Jabatan.ID_Level_Jabatan
    → HRIS_Level.ID_Level
    → HRIS_Jabatan.ID_Jabatan

atau melalui:
View_Golongan_Sub_Golongan_Level_Jabatan
```

Jangan menambahkan `ID_Level` dan `ID_Jabatan` ke relationship hanya untuk memudahkan query. Menyimpan keduanya bersama `ID_Level_Jabatan` menciptakan kemungkinan kombinasi yang saling bertentangan.

Rater Policy boleh memilih blueprint berdasarkan level hasil resolusi ini. Ketika cycle dikunci, level/jabatan/divisi yang relevan disalin ke snapshot peserta supaya promosi atau mutasi berikutnya tidak mengubah Policy cycle lama.

## 6. Opsi tertunda: `Employee_Organization_Assignment`

### 6.1 Kapan tabel ini diperlukan

Tabel ini tidak diperlukan hanya untuk menyimpan atasan. `Organization_Relationship` sudah effective-dated dan cukup untuk histori supervisor.

Tambahkan `Employee_Organization_Assignment` hanya bila HCIS perlu menjawab salah satu pertanyaan berikut secara authoritative:

- Divisi/Sub Divisi karyawan pada tanggal tertentu.
- Level dan Jabatan karyawan pada periode tertentu.
- Mutasi lintas perusahaan atau unit.
- Acting position, rangkap jabatan, atau secondary assignment.
- Rekonstruksi organisasi historis di luar snapshot suatu cycle appraisal.

Jika kebutuhan tersebut belum ada, gunakan current assignment dari `Karyawan` dan snapshot pada `N_PA_Participant`.

### 6.2 Kolom konseptual

| Kolom | Tipe konseptual | Null | Fungsi |
|---|---|:---:|---|
| `Id_Employee_Organization_Assignment` | `int identity` | Tidak | Primary key assignment organisasi. |
| `Kode_Karyawan` | `varchar(255)` | Tidak | Referensi logis ke `Karyawan.Kode_Karyawan`. |
| `Kode_Perusahaan` | `varchar(3)` | Tidak | Perusahaan assignment; mengikuti master existing. |
| `ID_Divisi_Sub_Divisi` | `bigint` | Ya | Referensi kombinasi Divisi/Sub Divisi existing. |
| `ID_Level_Jabatan` | `bigint` | Ya | Referensi kombinasi Level/Jabatan existing. |
| `Jenis_Assignment` | `varchar(50)` | Tidak | Misalnya `PRIMARY`, `ACTING`, atau `SECONDARY`; daftar divalidasi backend. |
| `Berlaku_Dari` | `date` | Tidak | Awal assignment. |
| `Berlaku_Sampai` | `date` | Ya | Akhir assignment. |
| `Flag_Utama` | `char(1)` | Tidak | `Y/T`; satu primary aktif per karyawan pada satu tanggal. |
| `Sumber_Data` | `varchar(50)` | Tidak | Sumber assignment, misalnya HR master/import/manual. |
| `Alasan_Perubahan` | `nvarchar(500)` | Ya | Alasan mutasi, acting, atau koreksi. |
| `Flag_Aktif` | `char(1)` | Tidak | Status administratif row. |
| audit lifecycle | `datetime2` + `varchar(100)` | Sesuai kebutuhan | Created/Updated/Cancelled; actor source harus diputuskan sebelum DDL. |

### 6.3 Batasan

1. Jangan membuat tabel ini lalu tetap memperlakukan kolom current pada `Karyawan` sebagai authority yang berbeda tanpa aturan sinkronisasi.
2. Harus diputuskan siapa penulis utama: HR master, integrasi, atau HCIS.
3. Satu primary assignment tidak boleh overlap untuk karyawan yang sama.
4. Secondary/acting assignment harus punya aturan apakah memengaruhi rater policy.
5. Kehadiran tabel ini tidak menghapus kebutuhan snapshot per cycle.

## 7. Dari blueprint Rater Policy ke assignment appraisal

### 7.1 Struktur konseptual policy

Rater Policy berada di domain `KPI / Appraisal`, bukan di tabel organisasi.

```text
RATER_POLICY
· identitas policy dan versi
· level/band sasaran
· status dan periode berlaku

RATER_POLICY_DETAIL
· kategori: SELF / ATASAN_1 / ATASAN_2 / PEER / BAWAHAN
· jenis relationship sumber
· metode resolusi kandidat
· bobot appraisal
· min/maks penilai
· wajib/tidak
· aturan fallback
```

Contoh aturan policy untuk Manager:

| Kategori | Metode kandidat | Min/Maks | Bobot contoh |
|---|---|---:|---:|
| Self | Diri sendiri | 1/1 | 10% |
| Atasan 1 | Direct supervisor primary | 1/1 | 45% |
| Atasan 2 | Supervisor tingkat dua atau functional supervisor | 0/1 | Belum diputuskan |
| Peer | Explicit peer lalu shared supervisor/unit | 2/3 | 15% |
| Bawahan | Inverse direct-supervisor relation | 2/5 | 30% |

Angka hanya ilustrasi dari material PIC dan belum menjadi konfigurasi final, terutama Atasan 2.

### 7.2 Alur resolusi

```text
1. Materialisasi peserta cycle
   └─ ambil Karyawan aktif yang memenuhi scope
   └─ snapshot divisi, level, dan jabatan

2. Pilih blueprint policy
   └─ berdasarkan level/band snapshot peserta

3. Ambil kandidat pada tanggal acuan cycle
   └─ direct supervisor dari Organization_Relationship
   └─ atasan tingkat dua lewat traversal maksimal yang dibatasi
   └─ bawahan lewat inverse relation
   └─ peer eksplisit atau shared-supervisor/unit

4. Saring kandidat
   └─ karyawan valid/eligible
   └─ periode relationship berlaku
   └─ bukan diri sendiri kecuali kategori SELF
   └─ bukan pasangan konflik/pengecualian
   └─ memenuhi min/maks dan batas workload

5. Hasilkan rekomendasi
   └─ sertakan alasan dan sumber setiap kandidat
   └─ jangan langsung menjadi assignment

6. Admin review per peserta
   └─ menerima, mengganti, atau menambah kandidat
   └─ setiap override membutuhkan alasan

7. Materialisasi konfigurasi peserta
   └─ snapshot bobot, min/maks, dan kategori yang berlaku

8. Materialisasi Participant Rater Assignment
   └─ satu baris per pasangan peserta–penilai–kategori
   └─ simpan provenance: rekomendasi/manual/fallback

9. Readiness gate
   └─ peserta tanpa atasan, peer kurang, duplikat, atau workload berlebih masuk bucket
   └─ cycle tidak dapat dikunci sebelum bucket wajib selesai

10. Lock cycle
    └─ relationship live tidak lagi mengubah assignment
    └─ perubahan darurat memakai controlled amendment + audit
```

### 7.3 Provenance kandidat

Setiap rekomendasi perlu menjelaskan kenapa seseorang muncul, misalnya:

```text
GM-01     → DIRECT_SUPERVISOR primary sejak 2026-01-01
DIR-FIN   → FUNCTIONAL_SUPERVISOR primary sejak 2026-01-01
MGR-B     → PEER eksplisit
MGR-C     → kandidat peer karena shared supervisor GM-01
STAFF-01  → subordinate karena direct supervisor-nya MGR-A
```

Penjelasan ini penting agar admin dapat meninjau ratusan rekomendasi tanpa menebak logika sistem.

## 8. Batas antar-domain

| Domain | Menyimpan | Tidak menyimpan |
|---|---|---|
| `ESS / Organization` | Fakta hubungan organisasi dan, bila kelak perlu, histori assignment unit/level/jabatan. | Bobot appraisal, status pengisian, nilai, grade. |
| `KPI / Appraisal / Policy` | Blueprint kategori rater, metode candidate resolution, min/maks, bobot penilaian. | Fakta atasan live sebagai authority. |
| `KPI / Appraisal / Cycle` | Snapshot peserta, konfigurasi individual, assignment final, payload, hasil, dan audit cycle. | Master karyawan/divisi/level/jabatan duplikat. |

Aturan akses juga mengikuti batas tersebut:

- Pengelola organisasi dapat memelihara relationship tetapi tidak otomatis melihat nilai appraisal.
- Admin appraisal dapat memilih kandidat dari relationship tetapi tidak otomatis berhak mengubah master organisasi.
- Leader dashboard cycle lama membaca scope/snapshot cycle, bukan relationship live, agar mutasi hari ini tidak membuka atau menutup hasil historis secara diam-diam.

## 9. Risiko dan keputusan yang masih terbuka

| # | Pertanyaan | Dampak |
|---|---|---|
| O01 | Siapa pemilik bisnis dan penulis `Organization_Relationship`: HC/HR master, admin HCIS, atau sinkronisasi? | Menentukan ownership halaman, audit actor, dan source of truth. |
| O02 | Apakah peer perlu disimpan eksplisit atau cukup direkomendasikan dari shared supervisor/unit lalu dipilih per cycle? | Menentukan volume pemeliharaan organisasi. |
| O03 | Apakah atasan fungsional selalu kandidat Atasan 2, atau kategori tersendiri? | Menentukan mapping Rater Policy. |
| O04 | Apakah perusahaan memiliki hubungan matriks dengan persentase organisasi nyata? | Menentukan perlu/tidaknya `Bobot_Relasi_Default`. |
| O05 | Tanggal apa yang menjadi acuan candidate resolution: awal periode dinilai, awal pengisian, atau tanggal lock? | Mengubah kandidat ketika terjadi mutasi. Rekomendasi: tanggal lock dengan snapshot eksplisit, kecuali HC menetapkan lain. |
| O06 | Apakah relasi dari KPI Approval Flow boleh menjadi seed awal setelah data uji dibersihkan dan diverifikasi? | Menentukan strategi bootstrap; tidak boleh blind import. |
| O07 | Apakah histori divisi/level/jabatan dibutuhkan di luar appraisal? | Menentukan apakah `Employee_Organization_Assignment` perlu dibuat. |
| O08 | Identifier audit actor yang resmi apa? | Memblokir detail DDL audit columns. |

## 10. Rekomendasi keputusan untuk versi pertama

1. Buat hanya satu authority baru untuk relasi karyawan-ke-karyawan: `Organization_Relationship`.
2. Mulai dengan `DIRECT_SUPERVISOR` dan `FUNCTIONAL_SUPERVISOR`; peer eksplisit hanya jika HC memang mampu memeliharanya.
3. Turunkan bawahan dan atasan tingkat dua, jangan simpan inverse/transitive relation sebagai row baru.
4. Jangan menaruh bobot appraisal di relationship.
5. Gunakan current Divisi/Level/Jabatan dari `Karyawan` saat materialisasi, lalu snapshot pada participant cycle.
6. Tunda `Employee_Organization_Assignment` sampai ada kebutuhan histori organisasi lintas fitur dan penulis authoritative-nya jelas.
7. Jadikan hasil resolver sebagai rekomendasi transparan, kemudian materialisasikan keputusan admin ke Participant Rater Assignment.
8. Jangan memakai `KPI_Approval_Flow_Performance` sebagai source of truth. Bila dipakai untuk bootstrap, perlakukan sebagai sumber import yang harus dibersihkan dan dikonfirmasi.

## 11. Yang tidak dikerjakan

- Tidak membuat tabel atau migration.
- Tidak menghasilkan `forward.sql`, `drop.sql`, atau DML.
- Tidak mengubah master `Karyawan`, Divisi, Level, atau Jabatan.
- Tidak menetapkan foreign key, index, unique constraint, atau cascade final.
- Tidak menetapkan angka bobot Atasan 2.
- Tidak membuat page, route, controller, model, service, atau permission.

Dokumen ini siap menjadi bahan review HC/PIC dan dasar penyempurnaan checkpoint Performance Appraisal. Implementasi baru boleh dimulai setelah ownership, source of truth, jenis relasi awal, tanggal acuan, policy Atasan 2, dan audit actor diputuskan.

## 12. Follow-up implementasi fondasi organisasi

Keputusan dua tabel organisasi kemudian disetujui dan dilaksanakan pada development melalui `DDL-2026-061-hris-organization-relationship`. Implementasinya menjadi fondasi lintas modul, memakai composite FK resmi `Karyawan.(Kode_Perusahaan, Kode_Karyawan)`, tidak membatasi relasi berdasarkan unit/jabatan, dan membedakan direct, functional, peer, project, serta matrix relationship.

Lihat `plans/hris/2026-08-29-organization-relationship-design.md` dan `docs/changes/DDL-2026-061-hris-organization-relationship/README.md`. SQL Tracker dan production masih pending.
