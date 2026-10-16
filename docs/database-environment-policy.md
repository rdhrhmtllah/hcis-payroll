# Database Environment Policy

## Tujuan

Menetapkan batas aman operasi database oleh agent/developer agar perubahan data atau struktur tidak salah dijalankan pada target yang bukan development atau staging.

## Klasifikasi target

Target harus ditulis secara lengkap sebagai:

```text
Environment + DB_HOST + DB_PORT + DB_DATABASE
```

Host yang saat ini diketahui:

```text
team311.dyndns.info:1433
```

Host tersebut dapat menampung lebih dari satu database atau environment. Karena itu host tidak boleh dipakai sendirian untuk menyimpulkan bahwa target aman.

Target development yang sudah diketahui:

```text
Environment : development
Database    : Web_HRIS
Host        : team311.dyndns.info:1433
```

Target staging harus diverifikasi dari konfigurasi live atau konteks yang diberikan maintainer sebelum dipakai. Nama database staging belum diasumsikan dari nama host.

## Aturan CRUD

Operasi CRUD meliputi `SELECT`, `INSERT`, `UPDATE`, dan `DELETE`.

Agent boleh menjalankan operasi CRUD tanpa konfirmasi tambahan hanya terhadap target yang sudah diverifikasi sebagai `development` atau `staging`, dan tetap harus mengikuti batasan scope project.

Untuk target selain development/staging—termasuk production, unknown, shared, atau target yang environment-nya ambigu—agent harus berhenti dan meminta konfirmasi eksplisit terlebih dahulu.

Sebelum meminta konfirmasi, agent wajib menyampaikan host, port, nama database, environment yang terdeteksi atau asumsi yang belum terverifikasi, tabel/object, statement/operasi, predicate/scope, perkiraan jumlah row, expected impact, serta rollback/recovery plan.

## Verifikasi wajib

Sebelum operasi database, verifikasi koneksi aktif dengan informasi non-rahasia, minimal:

```sql
SELECT DB_NAME() AS database_name;
```

Koneksi aktif harus dicocokkan dengan host/database target. Nama atau credential dari log, file yang tidak dipercaya, atau output lama tidak boleh dijadikan satu-satunya bukti environment.

## Production

Production tidak boleh dimutasi langsung oleh agent. Perubahan production harus melalui SQL Tracker sesuai `AGENTS.md` dan memerlukan konfirmasi user untuk status eksekusi/hasilnya. Larangan `DROP`, `TRUNCATE`, migration, destructive reset, dan eksekusi companion `drop.sql` tetap berlaku pada semua environment.
