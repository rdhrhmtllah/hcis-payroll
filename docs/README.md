# KPI-Unreal Knowledge Base

Vault Obsidian untuk dokumentasi teknis, keputusan, workflow, dan konteks project KPI-Unreal.

## Navigasi

- [[architecture/system-overview|System Overview]]
- [[architecture/module-registry|Module Registry]]
- [[architecture/module-structure-inventory|Module Structure Inventory]]
- [[architecture/frontend-architecture|Frontend Architecture Definition]]
- [[design-system|HCIS Design System]]
- [[architecture/structure-alignment-final-status|Structure Alignment — Status dan Temuan Terbuka]]
- [[architecture/agent-development-workflow|Agent Development Workflow Standard]]
- [[architecture/ketentuan-tak-tertulis|Ketentuan Tak Tertulis — pertimbangan yang tidak diatur AGENTS.md]]
- [[architecture/testing-and-quality-standard|Testing and Quality Standard]]
- [[architecture/make-fullcontroller-definition|make:fullcontroller Definition]]
- [[database/README|Database Documentation]]
- [[database/naming-conventions|Konvensi Penamaan Database]]
- [[database/table-design-rules|Aturan Desain Tabel]]
- [[database/tables/README|Dokumentasi Tabel]]
- [[changes/README|Dokumentasi Perubahan Database]]
- [[database/sql-server|SQL Server]]
- [[workflows/ddl-sql-tracker|DDL dan SQL Tracker]]
- [[workflows/dev-to-production|Development ke Production]]
- [[workflows/penjadwal-hcis|Penjadwal (Scheduler) HCIS]]
- [[workflows/agent-safety|Aturan Agent]]
- [[KPI/performance-appraisal/README|Performance Appraisal — pintu masuk agent]]
- [[decisions/README|Architecture Decisions]]
- [[templates/feature-note|Template Feature]]
- `docs/uat/` — dokumen Business Analyst untuk UAT (format .docx)

## Alat verifikasi struktural

Lima skrip di `scripts/` memeriksa keutuhan struktur pada setiap perubahan yang
memindahkan atau menghapus berkas. Rinciannya di
[[architecture/testing-and-quality-standard|Testing and Quality Standard]]
bagian 17.

```text
scripts/verify-references.php        simbol App\ yang tidak resolve
scripts/verify-frontend-imports.php  jalur impor yang putus
scripts/verify-inertia-pages.php     nama halaman Inertia tanpa komponen
scripts/route-snapshot.php           snapshot seluruh route untuk dibandingkan
scripts/console-snapshot.php         snapshot perintah artisan
```

## Source of truth

- Source code: repository Git
- Aturan agent: `AGENTS.md`
- Rencana kerja: `plans/`
- DDL dan status: `database/ddl/`
- Dokumentasi/knowledge base: `docs/`

## Prinsip

Dokumentasi harus menjelaskan alasan dan workflow. Jangan menyimpan password, token, atau connection string lengkap di vault.
