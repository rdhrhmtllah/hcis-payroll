# DML-2026-033 — Seed delapan tahap proses versi

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-033-seed-tahap-versi` |
| Jenis | DML |
| Target development | `Web_HRIS` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Artifact | `database/dml/DML-2026-033-seed-tahap-versi/data-change.sql` |

## Delapan tahap

| # | Kode | Nama | Status teknis | Butuh berkas | Akhir |
|---|---|---|---|---|---|
| 1 | `DRAFTING` | Drafting | `DRAFT` | — | — |
| 2 | `REVIEW` | Review | `DRAFT` | — | — |
| 3 | `APPROVAL` | Approval | `DRAFT` | — | — |
| 4 | `HOLD` | Hold | `DRAFT` | — | — |
| 5 | `CANCEL` | Cancel | `DRAFT` | — | **ya** |
| 6 | `APPROVED` | Approved | `DRAFT` | **ya** | — |
| 7 | `FILING_REGISTERED` | Filing and Registered | `PUBLISHED` | **ya** | — |
| 8 | `OBSOLETE` | Obsolete | `OBSOLETE` | — | **ya** |

Berkas sengaja **belum dituntut sampai Approved** — seluruh maksud tahapan ini
adalah versi boleh lahir dan bergerak sebelum berkasnya ada.

## Sembilan belas transisi

```text
Drafting  → Review, Cancel
Review    → Approval, Drafting, Hold, Cancel
Approval  → Approved, Review, Hold, Cancel
Hold      → Drafting, Review, Approval, Cancel
Approved  → Filing and Registered, Review, Hold, Cancel
Filing    → Obsolete
Cancel    → (tidak ada)
Obsolete  → (tidak ada)
```

Dua jalur mundur ditambahkan karena alurnya nyata, bukan untuk kelengkapan:
**Approval → Review** (ditolak pengesah) dan **Approved → Review** (ternyata
masih ada yang keliru sebelum didaftarkan).

`Cancel` dan `Obsolete` tak punya transisi keluar sama sekali — itulah arti
`Is_Terminal`, dan tabel transisi yang menegakkannya.

## Backfill 75 versi lama

Diturunkan dari `Status`, bukan ditebak — kebalikan persis dari `Status_Teknis`,
sehingga tak ada versi yang berpindah keadaan hanya karena kolom baru muncul.

| Status lama | Tahap | Jumlah |
|---|---|---|
| `DRAFT` | Drafting | 15 |
| `PUBLISHED` | Filing and Registered | 44 |
| `OBSOLETE` | Obsolete | 16 |

`READY` tak ikut dipetakan: `CK_N_SOP_Versi_Status` tak pernah mengizinkannya di
tabel ini.

## Filing and Registered tak ditempuh lewat pemindahan tahap

Tahap itu berarti versinya benar-benar terbit, dan terbit menggeser pointer
dokumen, meng-obsolete versi lama, merakit ulang nomor resmi, serta
merekonsiliasi seluruh paket perujuk. `SopTahapVersiService::pindah()` menolaknya
dengan pesan yang mengarahkan ke jalur terbit — dua jalan menuju satu peristiwa
pasti menyimpang.

## Rollback

Tidak ada. Menarik sebuah tahap dari peredaran dilakukan dengan
menonaktifkannya (`Flag_Aktif = 'T'`), bukan menghapus barisnya — versi yang
sudah menunjuknya harus tetap bisa dibaca.
