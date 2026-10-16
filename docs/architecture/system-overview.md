# System Overview

## Project

KPI-Unreal adalah aplikasi Laravel dengan frontend Vite/Vue dan koneksi SQL Server eksternal.

## Komponen

```text
Browser
  -> Cloudflare named tunnel
  -> Laravel application :5678
  -> SQL Server eksternal

Vite HMR :5173
  -> Cloudflare WSS route saat development
```

## Runtime development

- Laravel dijalankan dengan `env -u DATABASE_URL` karena environment global dapat mengarahkan Laravel ke PostgreSQL yang salah.
- Vite dipakai untuk HMR saat development.
- Production build berada di `public/build/`.

## Related notes

- [[../database/sql-server|SQL Server]]
- [[../workflows/dev-to-production|Development ke Production]]
- [[../decisions/ADR-001-sql-server|ADR-001 SQL Server]]
