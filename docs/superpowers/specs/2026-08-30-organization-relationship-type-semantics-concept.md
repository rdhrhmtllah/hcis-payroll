# Plan dan Draft Konseptual Organization Relationship

- **Tanggal:** 2026-08-30
- **Status:** Konsep final; DDL-2026-063 sudah DEV_DONE pada Web_HRIS, SQL Tracker/production pending
- **Modul/Submodul/Fitur:** ESS / Organization / Relationship
- **Master:** N_HRIS_Organization_Relationship_Type
- **Tabel fakta:** N_HRIS_Organization_Relationship

## 1. Keputusan terbaru

1. Relation Type eksplisit yang dituju adalah ATASAN, BAWAHAN, dan PEER.
2. BAWAHAN bukan hasil reverse lookup ATASAN. Makna setiap row dibaca langsung dari Relation Type.
3. Master tidak memerlukan Sifat_Arah, direction, role endpoint, atau kolom arah pengganti.
4. Metadata generik yang dipertahankan adalah Flag_Relasi_Setara dan Flag_Pembentuk_Struktur_Organisasi, ditambah kolom existing.
5. SELF bukan relasi antar-karyawan dan tetap hanya menjadi kategori Performance Appraisal (PA).
6. Primary/secondary bukan type; statusnya berada pada Flag_Utama di row aktual.
7. Konteks dan periode berlaku melekat pada row aktual.
8. HRIS Level menunjukkan senioritas, bukan atasan atau bawahan aktual.
9. Development saat ini sudah menerima change 063: ATASAN, BAWAHAN, dan PEER aktif serta nama fisik Flag_Relasi_Setara sudah diterapkan.

## 2. Kontrak satu row

Satu row dibaca:

> Dalam periode berlaku, Subject Employee memiliki Relation Type terhadap Related Employee, dengan status utama dan konteks tertentu bila diperlukan.

| Subject | Type | Related | Makna langsung |
|---|---|---|---|
| A | ATASAN | B | B adalah atasan A. |
| A | BAWAHAN | B | B adalah bawahan A. |
| A | PEER | B | B adalah peer A; maknanya sama bila dibaca B terhadap A. |

Contoh satu fakta reporting yang disimpan sebagai pasangan eksplisit:

    ANDI --[ATASAN, utama, UNIT:PROCUREMENT]--> BUDI
    BUDI --[BAWAHAN, utama, UNIT:PROCUREMENT]--> ANDI

Consumer tidak perlu memahami resolver milik PA atau menafsirkan row melalui reverse lookup.

ATASAN dan BAWAHAN bukan relasi setara: kedua endpoint mempunyai peran berbeda. PEER adalah relasi setara, sehingga satu row canonical cukup untuk satu pasangan dan pembacaan kedua endpoint mempunyai makna sejajar.

## 3. Master N_HRIS_Organization_Relationship_Type

### 3.1 Tujuan

Master menjadi sumber resmi kode, nama, definisi endpoint, dan metadata generik lintas aplikasi. Master tidak menyimpan employee, primary, context, periode, atau aturan akses.

### 3.2 Kolom konseptual

| Kolom | Tipe konseptual | Nullable | Status | Fungsi |
|---|---|:---:|---|---|
| Id_Organization_Relationship_Type | int identity | Tidak | Existing | Primary key yang direferensikan row relationship. |
| Kode_Relasi | varchar(50) | Tidak | Existing | Business key unik; target awal ATASAN, BAWAHAN, PEER. |
| Nama_Relasi | nvarchar(100) | Tidak | Existing | Nama tampilan bisnis. |
| Deskripsi | nvarchar(500) | Ya | Existing | Definisi eksplisit arti Subject dan Related. |
| Flag_Relasi_Setara | char(1) | Tidak | DEV_DONE 063 | Y bila relasi tidak mempunyai pihak lebih tinggi/rendah dan kedua endpoint dibaca setara. PEER=Y; ATASAN/BAWAHAN=T. |
| Flag_Pembentuk_Struktur_Organisasi | char(1) | Tidak | DEV_DONE 062 | Y bila type merupakan bagian struktur organisasi. ATASAN/BAWAHAN=Y; PEER=T. |
| Urutan | int | Tidak | Existing | Urutan tampilan. |
| Flag_Aktif | char(1) | Tidak | Existing | Type dapat dipakai pada row baru bila Y. |
| Kolom audit existing | sesuai fisik | Sesuai fisik | Existing | Audit create/update. |

Tidak ada Sifat_Arah, direction, kode role, nama role, atau metadata arah pengganti. Flag_Relasi_Setara bukan metadata arah; flag ini hanya menyatakan apakah kedua pihak mempunyai kedudukan setara. Kontrak Subject–Related tetap dijelaskan oleh Kode_Relasi dan Deskripsi.

### 3.3 Contoh target master

Contoh ini bukan seed DML.

| Id ilustratif | Kode | Definisi row | Relasi Setara | Pembentuk Struktur | Aktif target |
|---:|---|---|:---:|:---:|:---:|
| 1 | ATASAN | Related adalah atasan Subject. | T | Y | Y |
| 2 | BAWAHAN | Related adalah bawahan Subject. | T | Y | Y |
| 3 | PEER | Related adalah peer Subject. | Y | T | Y |

Flag_Relasi_Setara=Y tidak berarti row disimpan dua kali; untuk PEER satu canonical row dibaca dari kedua endpoint. Flag_Pembentuk_Struktur_Organisasi=Y pada ATASAN dan BAWAHAN menyatakan keduanya mempunyai semantik hierarchy. Flag tersebut tidak berarti chart harus menggambar kedua row cermin sebagai dua garis.

## 4. Tabel fakta N_HRIS_Organization_Relationship

| Kolom | Tipe konseptual | Nullable | Status | Fungsi |
|---|---|:---:|---|---|
| Id_Organization_Relationship | int identity | Tidak | Existing | Primary key row aktual. |
| Id_Organization_Relationship_Type | int | Tidak | Existing | FK ke primary key master, bukan kode bebas. |
| Kode_Perusahaan_Subjek, Kode_Karyawan_Subjek | sesuai fisik | Tidak | Existing | Composite employee key Subject. |
| Kode_Perusahaan_Terkait, Kode_Karyawan_Terkait | sesuai fisik | Tidak | Existing | Composite employee key Related; artinya mengikuti type. |
| Flag_Utama | char(1) | Tidak | Existing | Y untuk garis reporting utama, T untuk secondary/kontekstual; PEER selalu T. |
| Jenis_Konteks | varchar(30) | Ya | DEV_DONE 062 | Jenis scope seperti organization, unit, team, project, atau matrix. |
| Referensi_Konteks | varchar(100) | Ya | DEV_DONE 062 | Identifier context resmi. |
| Berlaku_Dari, Berlaku_Sampai | date | Sesuai fisik | Existing | Periode efektif; akhir nullable. |
| Prioritas | int | Tidak | Existing | Urutan relasi kontekstual; bukan bobot appraisal. |
| Provenance, status, catatan, audit | sesuai fisik | Sesuai fisik | Existing | Traceability dan lifecycle. |

Tidak ada FK yang mewajibkan kedua employee berada di divisi, level, atau jabatan yang sama. Matrix dan cross-functional tetap didukung.

### 4.1 Primary, context, dan periode

- Flag_Utama=Y pada ATASAN berarti Related adalah atasan utama Subject.
- Pada row cermin BAWAHAN, flag disalin: Related adalah bawahan pada garis utama yang sama.
- Maksimal satu ATASAN utama efektif per Subject dan context yang sama.
- BAWAHAN cermin tidak dihitung sebagai assignment primary kedua.
- Atasan secondary direkomendasikan memiliki context eksplisit.
- PEER selalu Flag_Utama=T.

Row efektif memenuhi:

    Flag_Aktif = Y
    AND Berlaku_Dari <= Tanggal_Acuan
    AND (Berlaku_Sampai IS NULL OR Berlaku_Sampai >= Tanggal_Acuan)

## 5. Pairing dan aturan duplicate

Type eksplisit BAWAHAN menyederhanakan consumer, tetapi menghasilkan dua row untuk satu fakta reporting. Rekomendasinya adalah paired mirror yang dikelola atomik, bukan dua row bebas:

    A --ATASAN--> B
    B --BAWAHAN--> A

Pasangan wajib mempunyai periode, context, Flag_Utama, status, dan provenance yang konsisten serta dibuat atau ditutup dalam satu transaksi. Identity pasangan yang dapat diaudit—pair ID atau source reference terkontrol—harus diputuskan pada change berikutnya.

Aturan canonical:

1. ATASAN A→B dan BAWAHAN B→A adalah pasangan cermin, bukan duplicate.
2. Pasangan cermin kedua pada scope/periode sama harus ditolak.
3. ATASAN B→A bukan cermin row pertama; artinya A adalah atasan B dan dapat menciptakan cycle.
4. BAWAHAN A→B tanpa ATASAN B→A tidak direkomendasikan. Apakah database wajib menolaknya adalah keputusan change berikutnya.
5. PEER A–B disimpan satu kali dengan canonical endpoint; PEER B–A pada scope/periode sama adalah reverse duplicate.

Master type BAWAHAN sudah dapat diaktifkan tanpa menulis fact row. Namun, penulisan paired mirror ke tabel fakta belum boleh dimulai sampai transaksi atomik dan makna Flag_Utama pada sisi BAWAHAN diputuskan.

## 6. Contoh Andi, Budi, Sari

Contoh ini bukan insert database.

| Id | Subject | Type | Related | Utama | Context | Berlaku | Pair |
|---:|---|---|---|:---:|---|---|---|
| 101 | Andi | ATASAN | Budi | Y | UNIT:PROCUREMENT | 2026-01-01–NULL | P-001 |
| 102 | Budi | BAWAHAN | Andi | Y | UNIT:PROCUREMENT | 2026-01-01–NULL | P-001 |
| 103 | Andi | ATASAN | Sari | T | UNIT:PRODUCTION | 2026-01-01–NULL | P-002 |
| 104 | Sari | BAWAHAN | Andi | T | UNIT:PRODUCTION | 2026-01-01–NULL | P-002 |
| 105 | Andi | PEER | Dedi | T | TEAM:IMPROVEMENT | 2026-01-01–NULL | — |

Pair hanya ilustrasi; kolom tersebut belum ada pada implementasi 063.

    Andi + ATASAN  -> Budi, Sari
    Budi + BAWAHAN -> Andi
    Sari + BAWAHAN -> Andi
    Andi + PEER    -> Dedi
    Dedi + PEER    -> Andi  (baca kedua endpoint karena relasinya setara)

## 7. Organization chart

Node dan label berasal dari master Karyawan, Jabatan, Divisi/unit, dan organisasi existing. Level boleh ditampilkan sebagai senioritas, tetapi tidak membentuk edge.

| Relationship | Tampilan | Aturan |
|---|---|---|
| ATASAN, Flag_Utama=Y | Garis solid | Edge utama Subject menuju Related. |
| ATASAN, Flag_Utama=T | Garis titik-titik | Edge secondary/kontekstual berlabel context. |
| BAWAHAN | Tidak menggambar edge tambahan bila pasangan ATASAN tersedia | Semantik hierarchy dari sisi atasan; pasangan di-deduplicate menjadi satu garis. |
| PEER | Connector lateral opsional | Bukan parent-child hierarchy. |

    Budi
      ▲
      │ solid: utama / Procurement
      │
    Andi
      └········▶ Sari
         dotted: secondary / Production

ATASAN dan BAWAHAN sama-sama eligible untuk penelusuran struktur sesuai titik awal consumer. Chart memilih satu orientasi canonical—direkomendasikan ATASAN—dan menggabungkan pasangan sebelum render. Traversal tetap memakai context, depth limit, dan cycle detection. Relationship bukan permission; authorization milik tiap modul.

## 8. Validasi utama

1. Subject dan Related berbeda; type aktif saat row baru dibuat.
2. ATASAN: Related atasan Subject; BAWAHAN: Related bawahan Subject.
3. ATASAN dan BAWAHAN wajib Flag_Relasi_Setara=T karena endpoint memiliki peran lebih tinggi/rendah; PEER=Y karena kedua endpoint setara.
4. ATASAN dan BAWAHAN wajib Flag_Pembentuk_Struktur_Organisasi=Y; PEER=T.
5. Hanya pasangan reporting dapat membawa Flag_Utama=Y; PEER selalu T.
6. Maksimal satu ATASAN utama efektif per Subject/context dan tidak overlap.
7. Row cermin konsisten pada endpoint terbalik, periode, context, primary, status, dan provenance.
8. Chart/traversal harus deduplicate pasangan cermin.
9. Hierarchy tidak boleh cycle.
10. PEER satu canonical row; reverse duplicate ditolak.
11. Akhir periode tidak mendahului awal; status dan periode diperiksa bersama.
12. Level, jabatan, atau unit tidak dipakai untuk mengarang relationship tanpa row.

## 9. Kompatibilitas dengan implementasi 061/062/063

Yang tetap cocok:

- fact table sudah FK ke primary key Relation Type;
- kontrak Subject→Related, periode, primary, context, provenance, audit, dan FK employee tetap relevan;
- metadata Flag_Relasi_Setara dan Flag_Pembentuk_Struktur_Organisasi sudah tersedia di development setelah 063;
- tidak diperlukan Sifat_Arah atau kolom direction baru;
- tidak diperlukan perubahan master Karyawan, Divisi, Level, atau Jabatan.

Keadaan development setelah 063:

- ATASAN, BAWAHAN, dan PEER aktif dengan nilai flag final;
- nama fisik lama Flag_Hubungan_Dua_Arah sudah menjadi Flag_Relasi_Setara;
- belum ada identity atau constraint paired mirror;
- artifact 062 tidak ditulis ulang; 063 menjadi change baru dengan checksum sendiri;
- rollback test membuktikan reverse duplicate PEER masih diterima schema dan mirror BAWAHAN kedua dengan Flag_Utama=Y ditolak index primary existing.

Sebelum fact row ditulis, change/aplikasi berikutnya perlu keputusan pairing atomik, revisi aman aturan primary sisi BAWAHAN, canonical PEER, cycle validation, serta focused/impacted test. Production mengikuti urutan SQL Tracker 061 → 062 → 063.

Dokumen ini tidak membuat change ID, DDL, DML, seed, atau mutasi database.

## 10. Dampak ke Performance Appraisal

PA tidak memerlukan metode FORWARD atau INVERSE untuk membedakan arah:

    DIRECT_SUPERVISOR -> Relationship Type ATASAN
    SUBORDINATE       -> Relationship Type BAWAHAN
    PEER              -> Relationship Type PEER
    SELF              -> participant sendiri; tanpa relationship

Metode_Resolusi tidak diperlukan sebagai penafsir arah. Jika konsep metode serupa dipertahankan, fungsinya hanya untuk strategi kandidat lain seperti manual atau fallback, bukan mengubah ATASAN menjadi BAWAHAN.

PA tetap menentukan kategori, eligibility, bobot, jumlah rater, fallback, assignment, review, dan snapshot. Organization Relationship menyediakan kandidat efektif dengan makna type langsung.

## 11. Keputusan sebelum change berikutnya

1. Apakah paired mirror ATASAN–BAWAHAN wajib dan dikelola atomik? Rekomendasi: ya.
2. Apakah perlu pair ID eksplisit, atau reference existing cukup tanpa ambigu?
3. Apakah chart memakai ATASAN sebagai orientasi canonical sambil kedua type tetap Flag_Pembentuk_Struktur_Organisasi=Y? Rekomendasi: ya.
4. Apakah Flag_Utama disalin ke BAWAHAN sebagai garis reporting yang sama? Rekomendasi: ya.
5. Apakah satu atasan utama berlaku global per Subject atau per Subject+context?
6. Apa authority master context unit, team, project, dan matrix?
7. Bagaimana canonical endpoint PEER ditentukan?
8. Apakah relationship lintas perusahaan diizinkan?
9. Kapan service maintenance relationship dan aturan paired mirror mulai dirancang?

Setelah keputusan tersebut, pekerjaan terpisah dapat melalui inspeksi live, artifact baru, review, execution development, verification, dan SQL Tracker. Tidak ada deploy production otomatis.

## 12. Batasan

- Revisi ini hanya dokumentasi konseptual.
- Development Web_HRIS telah menerima 061/062/063; SQL Tracker/production masih pending sesuai ledger.
- Tidak ada SQL, migration, seed, DDL, DML, atau data master yang diubah.
- Contoh employee dan Pair bukan data nyata.
- Dokumen as-built membedakan keadaan fisik sekarang dari target konsep.
- Plan bukan authorization menjalankan database change.
