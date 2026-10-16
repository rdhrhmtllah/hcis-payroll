# Database Change Dashboard

Dokumen ini adalah daftar pusat seluruh perubahan database project. Tujuannya agar developer dapat langsung melihat perubahan mana yang masih harus dikerjakan dan memperbarui statusnya setelah setiap tahap.

## Cara membaca dashboard

Perhatikan empat status terpisah berikut:

| Kolom | Arti |
|---|---|
| `Development` | Apakah agent/developer sudah menjalankan script di database development |
| `SQL Tracker` | Apakah script yang sama sudah dicatat/dimasukkan ke SQL Tracker |
| `Production` | Apakah perubahan sudah dijalankan ke production melalui SQL Tracker |
| `Verification` | Apakah hasil perubahan sudah diverifikasi pada target terakhir |

Jangan menganggap `Development = DONE` berarti perubahan sudah selesai. Perubahan baru lengkap setelah SQL Tracker, production, dan verification selesai.

## Dashboard saat ini

| Change ID | Jenis | Ringkasan | Development | SQL Tracker | Production | Verification | File eksekusi utama |
|---|---|---|---|---|---|---|---|
| `20260816_175007_add-system-module` | DML | Register grup fitur `SYSTEM` | `DONE` | `PENDING` | `PENDING` | `DEV_DONE` | [`data-change.sql`](../../database/dml/20260816_175007_add-system-module/data-change.sql) |

| `DDL-2026-001-create-system-database-change` | DDL | Create dashboard table | `DONE` | `PENDING` | `PENDING` | `DEV_DONE` | [`forward.sql`](../../database/ddl/DDL-2026-001-create-system-database-change/forward.sql) |
| `DML-2026-002-register-system-dashboard-change` | DML | Register existing change in dashboard | `DONE` | `PENDING` | `PENDING` | `DEV_DONE` | [`data-change.sql`](../../database/dml/DML-2026-002-register-system-dashboard-change/data-change.sql) |

| `DML-2026-003-add-system-database-change-menu` | DML | Add SYSTEM dashboard sidebar menu | `DONE` | `PENDING` | `PENDING` | `DEV_DONE` | [`data-change.sql`](../../database/dml/DML-2026-003-add-system-database-change-menu/data-change.sql) |

| `DML-2026-004-restrict-system-database-change-menu` | DML | Restrict dashboard menu to System Admin | `DONE` | `PENDING` | `PENDING` | `DEV_DONE` | [`data-change.sql`](../../database/dml/DML-2026-004-restrict-system-database-change-menu/data-change.sql) |

| `DDL-2026-005-create-system-task-outbox` | DDL | Create durable SYSTEM task outbox | `DONE` | `PENDING` | `PENDING` | `DEV_DONE` | [`forward.sql`](../../database/ddl/DDL-2026-005-create-system-task-outbox/forward.sql) |

| `DDL-2026-006-add-provider-result-to-task-outbox` | DDL | Add provider result fields to task outbox | `DONE` | `PENDING` | `PENDING` | `DEV_DONE` | [`forward.sql`](../../database/ddl/DDL-2026-006-add-provider-result-to-task-outbox/forward.sql) |

| `DML-2026-022-register-visitor-master-data-menu` | DML | Register menu Master Data Visitor dan akses `SHE_ADMIN` | `DONE` | `PENDING` | `PENDING` | `DEV_DONE` | [`data-change.sql`](../../database/dml/DML-2026-022-register-visitor-master-data-menu/data-change.sql) |

| `DDL-2026-023-add-visitor-master-soft-status` | DDL | Add soft status to Visitor color and purpose masters | `DONE` | `PENDING` | `PENDING` | `DEV_DONE` | [`forward.sql`](../../database/ddl/DDL-2026-023-add-visitor-master-soft-status/forward.sql) |

| `DML-2026-024-move-visitor-verification-menus` | DML | Salah target header; superseded oleh DML-2026-025, jangan dipromosikan | `DONE` | `PENDING` | `PENDING` | `DEV_DONE` | [`data-change.sql`](../../database/dml/DML-2026-024-move-visitor-verification-menus/data-change.sql) |

| `DML-2026-025-correct-visitor-admin-menu-header` | DML | Correct Verifikator dan Laporan ke Administrator Visitor | `DONE` | `PENDING` | `PENDING` | `DEV_DONE` | [`data-change.sql`](../../database/dml/DML-2026-025-correct-visitor-admin-menu-header/data-change.sql) |

| `DML-2026-026-register-visitor-export-studio-menu` | DML | Export Studio jadi halaman sendiri; murni penambahan, `visitorLaporanPage` tidak disentuh | `DONE` | `PENDING` | `PENDING` | `DEV_DONE` | [`data-change.sql`](../../database/dml/DML-2026-026-register-visitor-export-studio-menu/data-change.sql) |

| `DDL-2026-027-index-visitor-kehadiran` | DDL | Lima indeks untuk pola baca kehadiran & masa berlaku Visitor; tidak menyentuh data | `DONE` | `PENDING` | `PENDING` | `DEV_DONE` | [`forward.sql`](../../database/ddl/DDL-2026-027-index-visitor-kehadiran/forward.sql) |

### Action yang masih harus dilakukan

Untuk change berikut:

```text
20260816_175007_add-system-module
```

Masukkan isi file ini ke SQL Tracker:

```text
database/dml/20260816_175007_add-system-module/data-change.sql
```

Setelah selesai, ubah kolom `SQL Tracker` dari `PENDING` menjadi `DONE` atau `RECORDED`, lalu catat ID/referensi SQL Tracker di dokumentasi change. Jangan mengubahnya hanya berdasarkan niat; ubah setelah benar-benar tercatat.

## Status yang digunakan

### Development

```text
PENDING   → belum dijalankan di development
DONE      → sudah dijalankan dan hasilnya diverifikasi
FAILED    → eksekusi gagal; jangan lanjut ke tahap berikutnya
```

### SQL Tracker

```text
PENDING   → belum dimasukkan ke SQL Tracker
RECORDED  → sudah dicatat di SQL Tracker; simpan referensi/ID-nya
```

### Production

```text
PENDING   → belum dijalankan ke production
DONE      → user sudah menjalankan dari SQL Tracker ke production
```

### Verification

```text
PENDING   → belum diverifikasi
DEV_DONE  → development sudah diverifikasi, production belum
DONE      → target akhir sudah diverifikasi
FAILED    → verifikasi gagal
```

## Siapa yang memperbarui status?

```text
Agent/developer:
- membuat script;
- menjalankan script yang disetujui di development;
- memverifikasi development;
- mengisi status Development dan Verification development;
- mencatat hasil eksekusi.

User/developer yang mengelola SQL Tracker:
- memasukkan script yang sama ke SQL Tracker;
- mengisi referensi SQL Tracker;
- mengubah SQL Tracker menjadi RECORDED;
- menjalankan perubahan production sesuai prosedur;
- mengonfirmasi Production dan Verification akhir.
```

## Aturan penting

- File SQL yang dicatat ke SQL Tracker harus sama dengan file yang diuji di development.
- Untuk DDL, file utama adalah `forward.sql`; `drop.sql` hanya script manual dan tidak dijalankan agent.
- Untuk DML, file utama adalah `data-change.sql`; tidak ada rollback otomatis.
- README bukan file yang dijalankan.
- Jangan menandai status selesai hanya karena file sudah dibuat.
- Setiap perubahan harus memiliki dokumentasi di `docs/changes/<change-id>/`.
- Status terstruktur juga dicatat di `database/ddl/status.json` sebagai ledger project; meskipun namanya `ddl`, ledger ini mencatat DDL dan DML agar memiliki satu sumber status.
