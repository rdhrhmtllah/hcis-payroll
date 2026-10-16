# DML-2026-040 — Pemetaan langkah, jenis pengesah, dan syarat tahap

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-040-seed-langkah-versi` |
| Jenis | DML |
| Target development | `Web_HRIS` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |

## Langkah

| Langkah | Tahap |
|---|---|
| 1 Pengajuan | Drafting |
| 2 Review | Review |
| 3 Pengesahan | Approval, Approved |
| 4 Terbit & Sebar | Filing and Registered |

Hold, Cancel, Obsolete sengaja tanpa langkah — mereka keadaan yang menempel,
bukan kotak isian sendiri.

## Syarat per tahap

| Tahap | Syarat |
|---|---|
| Review | — (boleh langsung dimulai) |
| Approval | Catatan review |
| Approved | Pengesah |
| Filing | Berkas final + pengesah |

## Backfill

Seluruh baris pengesah lama — termasuk tiga baris yang sempat tersimpan sebagai
`RENCANA` sebelum keputusan final — menjadi `REALISASI`. Pengesah kini hanya
satu blok di langkah Pengesahan.
