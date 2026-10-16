# DDL-2026-062 — Organization Relationship Type sederhana

| Field | Value |
|---|---|
| Change ID | `DDL-2026-062-organization-relationship-simple-type` |
| Target database | `Web_HRIS` development |
| Environment | Development pada host terkonfigurasi; diverifikasi dengan `DB_NAME()` |
| Status | `TRACKER_PENDING` — development `DONE`, verification `DEV_DONE` |
| Dependency | `DDL-2026-061-hris-organization-relationship` |
| Module basis | `ESS` (`Id_Grup_Fitur=1`), page aktif `addKaryawanTeam` |
| Verification date | 2026-08-30 |
| Forward DDL | `database/ddl/DDL-2026-062-organization-relationship-simple-type/forward.sql` |
| Manual cleanup | `database/ddl/DDL-2026-062-organization-relationship-simple-type/drop.sql` |
| DML/registry | `database/dml/DDL-2026-062-organization-relationship-simple-type/data-change.sql` |
| Concept | `docs/superpowers/specs/2026-08-30-organization-relationship-type-semantics-concept.md` |
| Table docs | `docs/database/tables/hris/n-hris-organization-relationship-type.md`; `docs/database/tables/hris/n-hris-organization-relationship.md` |
| Forward checksum SHA-256 | `3388bd6ed66e9e18f113b29eb309fbe7c7a353bfd8fa29184866adc5f444c60d` |
| DML checksum SHA-256 | `e2a3c2b581f4da8a38a164220ab56f05954701067111d271ae9019b26a149adf` |

## Scope dan alasan

Change 061 sudah membuat tabel master dan fakta dengan FK ID type yang benar. Change ini tidak menulis ulang artefak 061 karena checksum-nya sudah terkunci dan statusnya `TRACKER_PENDING`.

Perubahan additive:

- menambahkan `Flag_Hubungan_Dua_Arah` dan `Flag_Pembentuk_Struktur_Organisasi` pada master;
- menambahkan `Jenis_Konteks` dan `Referensi_Konteks` pada row fakta;
- menjaga pasangan context sama-sama NULL atau sama-sama terisi;
- menambahkan index pencarian relationship per context dan periode;
- menanam `ATASAN` secara idempoten dan menyelaraskan `PEER`;
- menonaktifkan empat type lama tanpa menghapus row master;
- mendaftarkan change baru pada `N_SYSTEM_Database_Change`.

## Safety audit sebelum eksekusi

- Target live terverifikasi `Web_HRIS` development.
- Dua tabel dependency tersedia.
- FK `N_HRIS_Organization_Relationship.Id_Organization_Relationship_Type` sudah trusted dan mengarah ke PK master.
- Total relationship row: `0`.
- Empat legacy supervisor type direferensikan oleh `0` row.
- Tidak ditemukan controller/service/model/route/test atau SQL module yang memakai tabel/kode ini.
- DML membatalkan transaksi bila legacy type sudah direferensikan pada saat eksekusi.
- Tidak ada DROP/TRUNCATE/DELETE dalam forward atau DML.

## DML scope pertama kali

Target tepat pada `Web_HRIS` development:

- `ATASAN`: insert maksimal 1 row bila belum ada, lalu diselaraskan ke aktif;
- `PEER`: insert maksimal 1 row bila dependency tidak memilikinya, lalu update maksimal 1 row bila nilainya berbeda;
- empat code legacy: update maksimal 4 row menjadi nonaktif bila nilainya berbeda;
- relationship fact: 0 row diubah;
- registry: insert maksimal 1 row bila belum ada.

Rerun aman karena insert memakai `NOT EXISTS`, update hanya menyentuh nilai yang berbeda, dan registry tidak digandakan.

## Constraint dan index

- FK ID type dari change 061 tetap menjadi authority; tidak ada `Kode_Relasi` pada tabel fakta.
- `CK_HRIS_Org_Rel_Context_Pair` menjaga pasangan context lengkap.
- `IX_HRIS_Org_Rel_Context_Period` mendukung filter context/type/status/periode.
- `UX_HRIS_Org_Rel_Primary_Open` existing dipertahankan. Ia membatasi satu primary open-ended per subject/type secara global, lebih ketat daripada per-context.
- Overlap periode bounded, cycle hierarchy, PEER reverse duplicate, dan aturan primary khusus `ATASAN` masih membutuhkan transaction validation aplikasi; trigger tidak ditambahkan.

## Development execution dan verification

Exact `forward.sql` dan `data-change.sql` dijalankan pada `Web_HRIS` development tanggal 2026-08-30.

Hasil:

- dua flag master `char(1) NOT NULL` terpasang dengan default `T`;
- dua kolom context nullable terpasang;
- `CK_HRIS_Org_Rel_Context_Pair` enabled dan trusted;
- `IX_HRIS_Org_Rel_Context_Period` tersedia;
- FK type tetap enabled/trusted dan menunjuk PK master;
- `ATASAN` aktif dengan nilai dua-arah `T`, pembentuk-struktur `Y`;
- `PEER` aktif dengan nilai dua-arah `Y`, pembentuk-struktur `T`;
- empat legacy type nonaktif dan tetap tersimpan;
- relationship fact tetap `0` row;
- registry change tepat `1` row dengan `DONE/PENDING/PENDING/DEV_DONE`.

Rerun exact artifacts berhasil dan seluruh DML menjadi `0 rows affected`. Uji transaksi rollback membuktikan context pair yang tidak lengkap ditolak dan duplikasi primary open-ended ditolak. Setelah test, relationship fact tetap `0` row.

## Rollback/recovery

Agent tidak menjalankan `drop.sql`. File tersebut hanya manual cleanup untuk metadata/context schema dan sengaja tidak membalik seed/status. Re-aktivasi legacy type atau penghapusan `ATASAN` memerlukan DML terpisah setelah impact review.

## Agent completed

- Blueprint/review artifact dibuat.
- Forward DDL, manual drop aid, dan DML terpisah dibuat.
- Development execution dan verification selesai.
- Checksum exact artifacts diverifikasi tetap sama setelah eksekusi.
- SQL Tracker recording dan production execution belum dilakukan.

## User tasks

- Setelah development selesai, review artifacts dan checksum.
- Jalankan change 061 lebih dahulu melalui SQL Tracker production.
- Jalankan exact forward/DML change 062 melalui SQL Tracker production.
- Konfirmasikan hasil production sebelum status `PRODUCTION_EXECUTED` atau `COMPLETED` ditetapkan.
