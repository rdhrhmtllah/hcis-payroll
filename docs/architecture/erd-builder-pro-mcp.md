# Panduan MCP ERD Builder Pro

Endpoint: `https://server-diagram.ajt.my.id/api/mcp` · scope `mcp:read mcp:write`.
Diagram kerja HCIS: project **EVO - HCIS**, dokumen **Main Diagram Project**,
uid `4005170e-d590-4da6-85b0-33449ac59a2c`.

Dokumen ini menjelaskan **31 tool** yang benar-benar muncul di server per 31 Agustus 2026,
dikelompokkan menurut kegunaannya. Tool yang bisa mengubah atau menghapus data diberi
tanda ⚠️.

---

## Pola yang berlaku untuk hampir semua tool tulis

Semuanya memakai **usul dulu, terapkan kemudian**:

```
*_propose  →  balikan berisi proposal_id + preview persis
             (tidak menulis apa pun)
*_apply    →  proposal_id + confirmation yang HARUS sama persis
```

Proposal kedaluwarsa sekitar dua jam, hanya berlaku untuk pemiliknya, dan hangus setelah
sukses. Dua hal yang menolak apply: **diagram yang sudah bergeser versinya** (ada orang
lain menyimpan lebih dulu) dan proposal yang kedaluwarsa. Karena itu, jangan menahan
proposal lama-lama; baca versinya, usulkan, terapkan.

`expected_version` pada `erd_patch_propose` dan `erd_dictionary_propose` adalah kunci
optimistic locking. Ambil dari `erd_schema_read`. Kalau meleset, balikannya
`Conflict: expected version X, current version is Y` — baca ulang, jangan paksa.

---

## 1 · Workspace, project, dan dokumen

| Tool | Fungsi | Sifat |
|---|---|---|
| `workspace_projects` | Daftar project aktif milik user. Parameter opsional `query`, `limit`, `offset`. | baca |
| `project_summary` | Jumlah dokumen dalam satu project. Butuh `project_uid`. | baca |
| `project_siblings` | Seluruh dokumen aktif dalam satu project, untuk konteks lintas dokumen. | baca |
| `workspace_list_files` | Daftar project, note, flowchart, drawing, dan ERD biasa. `project_uid` opsional. | baca |
| `workspace_search` | Cari dokumen berdasarkan judul. Butuh `query`. | baca |
| `workspace_recent` | Dokumen yang terakhir disentuh. | baca |
| `backup_list` | Metadata backup. Isi berkas backup **tidak pernah** dikembalikan lewat MCP. | baca |

Catatan penting: diagram DB Client dan diagram database produksi **tidak tersedia** lewat
MCP ini. Hanya ERD biasa.

---

## 2 · Membaca diagram

| Tool | Fungsi | Sifat |
|---|---|---|
| `document_read` | Ringkasan satu dokumen. Butuh `type` (`diagrams`/`notes`/`flowcharts`/`drawings`) dan `uid`. | baca |
| `document_read_full` | Snapshot penuh: entities, relationships, viewport, dbml_source. | baca |
| `erd_schema_read` | **Ini yang dipakai sehari-hari.** Bentuk ternormalisasi dan ramah agen. Opsional `table_id` untuk satu tabel beserta relasinya saja. | baca |

`erd_schema_read` mengembalikan `version` — angka itu yang dipakai sebagai
`expected_version` saat mengusulkan perubahan.

> **Jebakan ukuran.** Diagram 37 tabel menghasilkan sekitar 155 KB. Balikannya akan
> disimpan ke berkas dan Anda diminta membacanya dari sana. Olah dengan skrip
> (`jq`/Python), jangan disalin ke dalam jawaban — menyalin isi diagram ke jawaban
> adalah penyebab paling sering gagalnya tugas ERD, karena batas keluaran 64.000 token
> terlampaui sebelum pekerjaannya sempat dikirim.

`table_id` bukan nama tabel. `N_PA_Assignment` ber-id `paassign`. Selalu ambil id dari
hasil baca, jangan menebaknya.

---

## 3 · Mengubah struktur ERD ⚠️

| Tool | Fungsi | Sifat |
|---|---|---|
| `erd_patch_propose` | Menyiapkan perubahan **granular**: tabel, kolom, index, constraint, relasi. Maksimal 100 operasi. | usul |
| `erd_patch_apply` ⚠️ | Menerapkan satu proposal patch. | tulis |
| `workspace_write_propose` | Operasi dokumen tingkat atas, termasuk `diagram_save` yang **mengganti seluruh diagram**. | usul |
| `workspace_write_apply` ⚠️ | Menerapkan satu mutasi workspace. Termasuk penghapusan permanen. | tulis |

Operasi yang tersedia di `erd_patch_propose`:

```
table_add / table_update / table_delete ⚠️
column_add / column_update / column_delete ⚠️
index_add / index_update / index_delete
constraint_add / constraint_update / constraint_delete
relationship_add / relationship_update / relationship_delete ⚠️
```

Balikannya membawa preview `before`/`after` per operasi, `migration_plan` dengan SQL maju
dan mundur untuk PostgreSQL dan MySQL, serta ringkasan risiko
(`safe`/`caution`/`breaking`/`reversible`).

> **Pakai `erd_patch_*`, hindari `diagram_save`.** `diagram_save` bersifat ganti-penuh:
> payload wajib memuat SELURUH entitas dan relasi yang ada. Pada diagram 37 tabel itu
> melampaui batas keluaran dan gagal berulang kali. `erd_patch_*` mengirim hanya yang
> berubah, jadi tidak punya masalah itu. Ini bukan preferensi gaya — ini yang membedakan
> berhasil dan gagal.

---

## 4 · Data Dictionary dan governance

| Tool | Fungsi | Sifat |
|---|---|---|
| `erd_dictionary_read` | Metadata bisnis + laporan cakupan: skor, jumlah terdokumentasi, klasifikasi, dan daftar `gaps` per objek. Format `json`, `markdown`, atau `csv`. | baca |
| `erd_dictionary_propose` | Menyiapkan update kamus untuk tabel dan/atau kolom. Maksimal **500** update sekali jalan. | usul |
| `erd_dictionary_apply` ⚠️ | Menerapkan proposal kamus. Hanya menyentuh dokumentasi, tidak pernah bentuk skema. | tulis |

Bentuk satu update: `{table_id, column_id?, governance}` — tanpa `column_id` berarti
tingkat tabel. Field `governance` yang didukung:

```
business_name   nama bisnis yang dipahami non-teknis
description     arti kolom/tabel dalam satu kalimat
domain          area bisnis  ← inilah pengelompokan yang dibaca laporan
owner           pemilik proses bisnis
steward         penjaga kualitas datanya
classification  public | internal | confidential | restricted
lifecycle       tahap hidup data
review_status   status peninjauan
retention       masa simpan
glossary_term   istilah baku
tags            label bebas
```

**Tiga field ini bernilai TERBATAS, dan server menolak nilai di luarnya** — ketahuan
hanya setelah ditolak, jadi dicatat di sini supaya tidak diulang:

```
classification   public | internal | confidential | restricted
lifecycle        draft | active | deprecated
review_status    unreviewed | in-review | approved
```

Sisanya teks bebas. `tags` berupa larik string.

`erd_dictionary_read` melaporkan `gaps` sebagai daftar objek beserta field yang belum
diisi — itu daftar kerja yang bisa langsung dipakai, bukan sekadar skor.

> **Jebakan ukuran, lagi.** Balikan `propose` dan `apply` memuat pasangan before/after
> untuk SETIAP objek. Pada batch 80 kolom itu sekitar 60-68 KB dan akan disimpan ke
> berkas, bukan dikembalikan langsung. Itu bukan kegagalan — ambil `proposal_id`-nya
> dari berkas dengan skrip, lalu lanjutkan apply seperti biasa.

---

## 5 · Subject Area — pengelompokan modul bisnis

| Tool | Fungsi | Sifat |
|---|---|---|
| `erd_subject_area_list` | Daftar subject area tersimpan. | baca |
| `erd_subject_area_read` | Satu area: `node_ids`, warna, viewport. Butuh `area_id` (UUID). | baca |
| `erd_subject_area_propose` | Menyiapkan `create` / `update` / `delete` ⚠️ satu area. | usul |
| `erd_subject_area_apply` ⚠️ | Menerapkan satu proposal area. | tulis |

Bentuk `create`: `area: {name, color, node_ids, viewport_x?, viewport_y?, viewport_zoom?}`.
`node_ids` wajib memakai id tabel dari `erd_schema_read`.

Subject area adalah **saringan bernama**: ia mengelompokkan tabel tanpa memindahkannya.
Aman dipakai untuk presentasi per modul.

---

## 6 · Perspective — sudut pandang visual

| Tool | Fungsi | Sifat |
|---|---|---|
| `erd_perspective_list` | Daftar perspective tersimpan. | baca |
| `erd_perspective_read` | Satu perspective: sections, warna, posisi lokal tabel, mode edge. | baca |
| `erd_perspective_auto_layout` | **Menghitung tata letak dua tingkat tanpa menyimpan.** Bisa dari `perspective_id` atau dari `draft` sections. | baca |
| `erd_perspective_propose` | `create` / `update` / `auto_layout` / `delete` ⚠️. | usul |
| `erd_perspective_apply` ⚠️ | Menerapkan satu proposal perspective. | tulis |

Perspective **tidak pernah** mengubah tabel, kolom, atau relasi — hanya section, warna,
posisi lokal, dan penyaringan garis. Jadi ini alat paling aman untuk membuat tampilan
alur bisnis.

`draft` pada `auto_layout` menerima `direction` (`left-to-right` / `top-to-bottom`),
`edge_mode` (`all` / `internal` / `cross-section`), dan `sections` (maksimal 40).

**Bedanya dengan Subject Area:** subject area menjawab "tabel ini milik modul apa";
perspective menjawab "kalau saya bercerita tentang alur ini, tabelnya disusun bagaimana".
Satu tabel boleh muncul di banyak perspective dengan posisi berbeda.

---

## 7 · Impact analysis

| Tool | Fungsi | Sifat |
|---|---|---|
| `erd_impact_analyze` | Simulasi radius dampak sebelum perubahan berisiko. | baca |

Operasi yang bisa disimulasikan: `table-delete`, `table-rename`, `column-delete`,
`column-rename`, `column-type-change`, `column-nullability-change`. Butuh `table_id`, dan
`column_id` untuk operasi tingkat kolom.

**Jalankan ini lebih dulu** untuk setiap hapus, ganti nama, ganti tipe, ubah nullability,
sentuh primary key atau foreign key, dan perubahan relasi.

---

## 8 · Riwayat dan pemulihan

| Tool | Fungsi | Sifat |
|---|---|---|
| `history_list` | Daftar revisi tersimpan, plus `current_updated_at`. | baca |
| `history_read` | Isi satu revisi, tanpa memulihkan. | baca |
| `history_restore_propose` | Menyiapkan pemulihan satu revisi. | usul |
| `history_restore_apply` ⚠️ | Memulihkan. Membuat revisi pengaman sebelum menimpa. | tulis |

`history_list` adalah cara termurah memastikan apakah sebuah percobaan yang gagal sempat
menulis atau tidak: bandingkan `current_updated_at` sebelum dan sesudah.

---

## Alur kerja untuk permintaan yang sering muncul

**Memahami ERD.**
`erd_schema_read` → olah hasilnya dengan skrip. Kalau butuh sisi bisnisnya, tambahkan
`erd_dictionary_read` untuk melihat `gaps`.

**Menambah atau mengubah tabel/kolom/relasi.**
`erd_schema_read` untuk id dan versi → kalau perubahannya berisiko jalankan
`erd_impact_analyze` dulu → `erd_patch_propose` → tunjukkan preview dan minta persetujuan →
`erd_patch_apply` → `erd_schema_read` lagi untuk membuktikan hasilnya.

**Mengisi Data Dictionary.**
`erd_dictionary_read` untuk daftar `gaps` → `erd_dictionary_propose` per batch (batas 500,
tapi pecah lebih kecil supaya payload-nya tidak melampaui batas keluaran) →
`erd_dictionary_apply`.

**Membuat Subject Area per modul.**
`erd_schema_read` untuk id tabel → tentukan kelompok yang benar-benar koheren secara
bisnis → satu `erd_subject_area_propose` + `erd_subject_area_apply` per area.

**Membuat Perspective alur bisnis.**
Susun `draft.sections` → `erd_perspective_auto_layout` untuk melihat tata letaknya →
`erd_perspective_propose` op `create` → `erd_perspective_apply`.

**Merapikan tata letak.**
`erd_perspective_auto_layout` pada perspective yang sudah ada, lalu
`erd_perspective_propose` op `auto_layout`. Ini tidak menyentuh posisi di diagram utama.

---

## Risiko yang perlu diingat

1. **`diagram_save` adalah ganti-penuh.** Berhenti di tengah berarti berisiko menimpa
   dengan payload tak lengkap. Pakai `erd_patch_*`.
2. **Batas keluaran 64.000 token** membunuh tugas yang menyalin isi diagram ke jawaban.
   Baca ke berkas, olah dengan skrip.
3. **Konflik versi itu fitur, bukan gangguan.** Kalau apply ditolak karena versi bergeser,
   berarti ada perubahan lain — baca ulang dan gabungkan, jangan memaksa.
4. **`*_delete` tidak punya undo selain riwayat.** Jalankan `erd_impact_analyze` dulu.
5. **SQL migrasi yang dihasilkan hanya mencakup objek yang dimodelkan.** Trigger, view,
   routine, grant, dan partisi tidak ikut — dan diagram ini memodelkan SQL Server
   sementara SQL yang dihasilkan PostgreSQL/MySQL, jadi perlakukan sebagai acuan, bukan
   skrip siap pakai. Sumber kebenaran DDL HCIS tetap `database/ddl/`.

## Jebakan yang sudah memakan korban

**`erd_dictionary_propose` MENGGANTI seluruh objek governance tingkat tabel, bukan
menggabungkannya.** Mengirim hanya `description` akan menghapus `domain`, `owner`,
`steward`, `lifecycle`, `review_status`, `retention`, dan `tags` milik tabel itu.
Terjadi 1 September 2026 pada `N_PA_Score_Type` dan `N_PA_Assignment_Payload`; ketahuan
dan dipulihkan di apply berikutnya. Kalau menyentuh kamus tingkat tabel, **kirim seluruh
field**, bukan yang berubah saja.

**`sort_order` diabaikan pada `column_add`.** Kolom baru mendarat di akhir, sehingga
bertabrakan urutannya dengan kolom audit. Perbaikannya lewat `column_update` susulan.
