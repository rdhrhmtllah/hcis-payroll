# DDL-2026-075 — Rater assignment and the HC Learn integration row

## Change record

| Field | Value |
|---|---|
| Change ID | DDL-2026-075-performance-appraisal-assignment |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / Assignment |
| Status | TRACKER_PENDING |
| Artifact revision | **r3** |
| Dependencies | **DDL-2026-061, 064, 072, 073.** Must run after all of them. |
| Forward DDL | database/ddl/DDL-2026-075-performance-appraisal-assignment/forward.sql |
| Manual reset | database/ddl/DDL-2026-075-performance-appraisal-assignment/drop.sql |
| DML | database/dml/DDL-2026-075-performance-appraisal-assignment/data-change.sql |
| Concept | docs/superpowers/specs/2026-08-30-performance-appraisal-cycle-participant-snapshot-plan.md |
| Forward checksum SHA-256 | 2ea82992f552680de885d27db0d047d37bca672776b6de52370c2274e7b59203 |
| DML checksum SHA-256 | c5f75a5f0175167df05dccc4086aa73c553da4e2f9d7051eee43f2690a697708 |

## Revision history

| Revision | Date | Trigger | Change |
|---|---|---|---|
| r1 | — | Rancangan awal | Revisi ini benar-benar berjalan di development. |
| r2 | 2026-09-01 | Penyeragaman penamaan yang disetujui pemilik proyek | Nama kolom, constraint, index, dan tabel diselaraskan dengan prinsip: Inggris untuk yang struktural (waktu, pelaku, versi), Indonesia untuk nilai bisnis. Lihat `plans/kpi/pa-penamaan-2026-09-01.md`. |
| r3 | 2026-09-04 | Bobot penilai yang diatur admin tersapu pembagian rata setiap kali susunan penilai berubah | Tiga kolom pada `N_PA_Assignment`: `Bobot_Kustom`, `Bobot_Diatur_Oleh`, `Bobot_Diatur_Pada`, plus `CK_PA_Assignment_Bobot_Kustom` dan `CK_PA_Assignment_Bobot_Kustom_Jejak`. Verifikasi kolom naik 19 → 22. Development disejajarkan lewat `ALTER TABLE ... ADD` — non-destruktif, tanpa satu baris data pun tersentuh. **Checksum r1 dan r2 TIDAK BOLEH dicatat ke SQL Tracker.** |

## Scope

Four tables. `N_PA_Assignment_Status` and `N_PA_Assignment_Source` are closed
dictionaries seeded here (four and two rows). `N_PA_Assignment` is one rater assigned
to one participant. `N_PA_Assignment_Integration` holds the HC Learn exam token.

## The token is not a column on the assignment — that was checked, not assumed

The question was raised directly: LMS and SOP appear to keep integration data in a
separate table, so should PA do the same? The codebase was read rather than recalled,
and it does:

- Training keeps `Id_Ujian_Token`, `Payload_Json`, `Started_At`, `Finished_At` in
  **`N_LMS_Penjadwalan_Integrasi`**, joined to the assignment through
  `Id_Penjadwalan_Det`. `N_LMS_Penjadwalan_Det` itself has no token column at all.
- SOP keeps the same shape in **`N_SOP_Penjadwalan_Integrasi`**.
  `N_SOP_Penjadwalan_Detail` has no token column either.

So `Id_Ujian_Token` moved out of `N_PA_Assignment` into
`N_PA_Assignment_Integration`, which also carries the mint response
(`Payload_Json`, the source of `Link_Ujian`) and the exam window.

Two behaviours were carried over from their implementations because both are load-
bearing:

1. **More than one row per assignment, one active.** In SOP a new row appears only
   when the admin swaps the question version — a resend or an extension always
   `UPDATE`s the existing row, because discarding a token means a result HC Learn is
   still delivering can no longer be recognised. The same rule applies here.
2. **The callback finds its row by token without filtering `Flag_Aktif`.** Someone who
   already started the old version must still be able to submit.

## One thing deliberately done better than the source

`SopKuisCallbackService` has to look up its row with
`->orderByDesc(...)->first()` because the token column carries no uniqueness. That is
a guess about which row was meant. Here `UX_PA_Assignment_Integration_Token` is a
filtered unique index over `Id_Ujian_Token` where it is not null, so callback lookup is
deterministic — while rows that were never dispatched keep a NULL token and do not
collide with each other. Both halves of that were tested.

## Physical decisions

- **`Id_Cycle` is not duplicated here.** The cycle is already fixed by the participant,
  and storing it twice creates two values that can disagree.
- **The assignment points at the rater *category*, not at a cycle snapshot row.** Since
  K32 the effective weight can come from a per-participant override, so the evidence of
  "which weight was actually used" belongs to the calculation result
  (`N_PA_Result_Rater_Category`), not frozen here.
- **Self-assessment is an ordinary row** with category `SELF`, rater equal to the
  participant — not a separate column and not a separate table. Tested and accepted.
- **Rater identity is a composite FK** `(Kode_Perusahaan_Penilai, Kode_Karyawan_Penilai)`
  to `Karyawan`, matching that table's real primary key.
- **`Sumber_Penugasan` and `Status_Penugasan` name their role, not their type.** The
  `Kode_*` name lives in the master. Both FKs target the master's `UNIQUE` code column
  so their `DEFAULT` still works — the same pattern as `N_PA_Participant.Status_Peserta`.
- **`Bobot_Dalam_Kategori` is the within-category weight** (K18): two superiors default
  to 50/50 and an admin may set 70/30. This is a different layer from `Bobot_Persen` in
  the cycle snapshot, which is the weight *between* categories. They multiply in
  sequence and must never be swapped.
- **Uniqueness is per (participant, rater, category), filtered on active.** One person
  may legitimately rate the same participant under two categories — a peer who is also
  a subordinate — and that was tested and accepted. A retired assignment does not block
  a replacement.

## What is deliberately not enforced

`Alasan_Manual` being required when the source is `MANUAL` is an application gate. A
CHECK could state it only by hardcoding the business literal `'MANUAL'` into the schema
— exactly what the project rules forbid for business value lists, and exactly what
building the master was meant to avoid.

Σ `Bobot_Dalam_Kategori` per (participant, category) = 100 spans rows, so it is
validated at cycle lock (G10). Re-normalising the survivors after an assignment is
cancelled is likewise application work.

## Known limitation

`N_HRIS_Organization_Relationship` still has zero rows, so the `USULAN` path cannot be
exercised end to end until the converter endpoint (K21) exists. The FK is in place and
tested; only real recommendation data is missing.

## Pre-execution audit

- Verified target `Web_HRIS` development.
- None of the four tables existed.
- `N_PA_Participant`, `N_PA_Rater_Category`, `N_PA_Cycle_Question_Package`,
  `N_HRIS_Organization_Relationship`, and `Karyawan` were present.
- Confirmed `Karyawan`'s primary key really is
  `(Kode_Perusahaan varchar(3), Kode_Karyawan varchar(255))` before writing the
  composite FK.
- Change ID 075 was unused in the repository and in `N_SYSTEM_Database_Change`.

## Kenapa r3 ada: bobot yang diam-diam berubah mengubah nilai orang

`Bobot_Dalam_Kategori` selalu dibagi rata dan **dihitung ulang setiap kali susunan penilai
berubah**. Rata adalah bawaan yang masuk akal, tetapi bukan satu-satunya yang benar: atasan
langsung dan atasan dua tingkat di atas tidak selalu layak menimbang sama.

Sejak ada layar untuk mengaturnya, muncul cacat yang lebih serius daripada ketiadaan fiturnya:
mesin tidak punya cara membedakan 33,33 yang **dibagi rata** dari 33,33 yang **sengaja dipilih**.
Keduanya tersapu bersama pada perubahan berikutnya. Bobot menentukan bagaimana jawaban tiap
penilai dijumlahkan menjadi nilai akhir seseorang — angka yang berubah diam-diam di situ
mengubah hasil penilaian tanpa meninggalkan jejak, dan tidak ada satu pun kolom yang bisa
menjawab "siapa yang memutuskan atasan ini bernilai 60%".

`Bobot_Kustom` menjadikannya keputusan yang tercatat, bukan tebakan.

### Jejak dipisahkan dari Updated_By/Updated_At — sengaja

`Updated_By` dan `Updated_At` bergeser pada perubahan apa pun: status berubah, tenggat digeser,
penugasan dibatalkan. Keduanya tidak bisa menjawab siapa yang menetapkan bobotnya, yaitu
satu-satunya pertanyaan yang benar-benar ditanyakan ketika sebuah hasil disanggah.

`Bobot_Diatur_Oleh` sengaja BOLEH kosong: penetapan lewat perintah konsol memang tidak punya
pengguna, dan memaksakannya akan membuat jalur itu berbohong dengan menunjuk pengguna mana pun
yang kebetulan ada. Yang WAJIB ada adalah waktunya — itulah yang ditegakkan
`CK_PA_Assignment_Bobot_Kustom_Jejak`.

### Aturan yang dijalankan mesin

`hitungUlangBobot()` mempertahankan baris bertanda `Y` dan membagi sisanya ke baris bertanda
`T`. Bobot khusus digugurkan — dikembalikan rata, penandanya dilepas, **dan keadaan itu
dilaporkan ke pemanggil** — hanya bila pembagiannya tidak lagi mungkin berarti:

- sisanya negatif (yang khusus sudah melebihi 100%); atau
- sisanya nol padahal ada penilai biasa yang menunggu.

Kasus kedua tampak sah karena jumlahnya tepat 100, tetapi penilai berbobot 0% adalah penilai
yang jawabannya tidak ikut dihitung sama sekali. Membiarkannya berarti seseorang mengisi
penilaian yang tidak pernah berpengaruh, tanpa ada yang memberitahunya.

## Realignment development untuk r3

Bukan bagian dari change ini bagi produksi — produksi menjalankan `forward.sql` yang sudah
diamandemen, yang membuat tabelnya lengkap sejak lahir. Berikut hanya untuk menyejajarkan
development yang tabelnya sudah terlanjur ada.

```sql
IF DB_NAME() <> 'Web_HRIS' THROW 50999, 'realignment DDL-075 r3 hanya untuk Web_HRIS', 1;

IF COL_LENGTH('N_PA_Assignment', 'Bobot_Kustom') IS NULL
    ALTER TABLE N_PA_Assignment ADD
        Bobot_Kustom char(1) NOT NULL
            CONSTRAINT DF_PA_Assignment_Bobot_Kustom DEFAULT ('T'),
        Bobot_Diatur_Oleh int NULL,
        Bobot_Diatur_Pada datetime2(0) NULL;

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Assignment_Bobot_Kustom')
    ALTER TABLE N_PA_Assignment WITH CHECK ADD CONSTRAINT CK_PA_Assignment_Bobot_Kustom
        CHECK (Bobot_Kustom IN ('Y', 'T'));

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Assignment_Bobot_Kustom_Jejak')
    ALTER TABLE N_PA_Assignment WITH CHECK ADD CONSTRAINT CK_PA_Assignment_Bobot_Kustom_Jejak
        CHECK (Bobot_Kustom = 'T' OR Bobot_Diatur_Pada IS NOT NULL);
```

Hasil pada 2026-09-04: kolom ditambahkan, dua CHECK terpasang dan **trusted**, kolom milik
change ini menjadi 22, dan seluruh 3.587 baris yang ada mendapat nilai baku `T` — artinya tidak
satu pun bobot lama diperlakukan sebagai keputusan, yang memang benar: tidak ada yang pernah
memutuskannya.

### Penjaganya dibuktikan menyala, bukan sekadar terpasang

| Yang dicoba | Hasil |
|---|---|
| `Bobot_Kustom='Y'` tanpa `Bobot_Diatur_Pada` | ditolak `CK_PA_Assignment_Bobot_Kustom_Jejak` |
| `Bobot_Kustom='X'` | ditolak `CK_PA_Assignment_Bobot_Kustom` |
| `Y` berjejak lengkap | diterima |
| `Y` berjejak waktu tanpa pengguna (jalur konsol) | diterima |

Perilaku mesinnya diuji terpisah dengan data nyata di dalam transaksi yang digulung balik:
tanpa khusus tetap rata; 60/40 bertahan; penilai ketiga membuatnya gugur dan **dilaporkan**;
50/30 menyisakan tepat 20 untuk yang biasa; `kembalikanBobotRata()` melepas penandanya; dan
bobot yang ditetapkan bertahan melewati pemanggilan `hitungUlangBobot()` berikutnya.

## Agent completed

- Created and executed the exact forward.sql in ten batches and the exact
  data-change.sql in one batch.
- Verified column counts 10/9/19/12, four status rows, two source rows, eight enabled
  and trusted foreign keys, four enabled and trusted CHECK constraints, and eleven
  PK/index objects.
- 21 rollback behaviour tests against real employees, all rolled back and leaving every
  table at its seeded state. Every rejection test asserts the error came from SQL Server
  and records which constraint fired, so a PHP-level failure cannot pass as a
  constraint. An earlier run of this suite did exactly that — a wrong type hint made
  five rejection tests "pass" without touching the database — which is why the assertion
  was added.

  Assignment:
  - accepted a self-assessment row (rater = participant, category `SELF`);
  - accepted two superiors on one participant weighted 70/30;
  - accepted one person rating the same participant under two different categories;
  - rejected a duplicate active row for the same (participant, rater, category)
    → `UX_PA_Assignment_Peserta_Penilai`;
  - accepted the duplicate once the earlier row was retired;
  - rejected `Bobot_Dalam_Kategori = 150` → `CK_PA_Assignment_Bobot`;
  - rejected a status absent from the master → `FK_PA_Assignment_Status_Master`;
  - rejected a source absent from the master → `FK_PA_Assignment_Sumber`;
  - rejected a rater absent from `Karyawan` → `FK_PA_Assignment_Penilai`;
  - rejected a finish time before the start time → `CK_PA_Assignment_Rentang_Waktu`;
  - confirmed the defaults land as `BELUM_MULAI` and `USULAN`.

  Integration:
  - accepted a row with no token (dispatched later);
  - accepted a token with payload and exam window;
  - rejected the same token on a second assignment
    → `UX_PA_Assignment_Integration_Token`;
  - accepted many rows with NULL tokens, confirming NULLs do not collide;
  - rejected two active rows for one assignment
    → `UX_PA_Assignment_Integration_Aktif`;
  - accepted a replacement row after the previous one was retired, leaving two rows;
  - confirmed a retired row is still findable by token, which is what lets a rater who
    started the old version submit;
  - rejected a window ending before it starts
    → `CK_PA_Assignment_Integration_Jendela`;
  - rejected a non-existent question package → `FK_PA_Assignment_Integration_Package`;
  - rejected an orphan integration row → `FK_PA_Assignment_Integration_Assignment`.
- Re-ran the exact artifacts and confirmed a no-op; both data tables left empty and both
  masters unchanged at 4 and 2 rows.

## User tasks

- Review the exact SQL and checksums.
- Record the exact tested artifacts and checksums in SQL Tracker.
- Execute production manually — **075 must follow 061, 064, 072, and 073**.
- Confirm production execution before production verification.
