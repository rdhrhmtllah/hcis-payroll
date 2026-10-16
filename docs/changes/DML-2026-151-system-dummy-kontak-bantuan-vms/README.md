# DML-2026-151 — Data dummy kontak bantuan VMS (Umum & Tamu)

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-151-system-dummy-kontak-bantuan-vms` |
| Jenis | DML — **data dummy, hanya development** |
| Target | Web_HRIS development |
| Status | `DEV_EXECUTED`; SQL Tracker & production `TIDAK_BERLAKU` |
| Artifact | `database/dml/DML-2026-151-system-dummy-kontak-bantuan-vms/data-change.sql` (sha256 `38a47f1e175dec31cf34b7ce44b2a6057d8195e76cef8c9d22fc1f1c5921a2c1`) |

## Tujuan

Permintaan user 29 Sep 2026: melihat modal **Bantuan VMS** di Pusat Bantuan dengan seksi
per fitur. Melengkapi DML-2026-145 (3 kontak VISITOR/PERMIT).

| Kode_Fitur | Nama_Kontak | Aktif |
|---|---|---|
| UMUM | [DUMMY] Helpdesk VMS | Y |
| TAMU | [DUMMY] Resepsionis Lobi | Y |
| TAMU | [DUMMY] Security Pos Tamu (tanpa email, WA saja) | Y |
| TAMU | [DUMMY] Resepsionis Lama (nonaktif) | T — memastikan tidak tampil |

Semua baris ditandai `Created_By = 'DUMMY-DML-2026-151'`.

## Hasil eksekusi development (2026-09-29)

```text
Bantuan VMS — 6 kontak · Permit, Tamu
  Umum: [DUMMY] Helpdesk VMS
  Permit: [DUMMY] Admin Permit SHE, [DUMMY] Helpdesk GA, [DUMMY] Pos Keamanan Utama
  Tamu: [DUMMY] Resepsionis Lobi, [DUMMY] Security Pos Tamu
```

Dijalankan ulang: tetap 4 baris (idempoten).
