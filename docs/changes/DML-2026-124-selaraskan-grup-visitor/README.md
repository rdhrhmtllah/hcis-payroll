# DML-2026-124-selaraskan-grup-visitor

## Ringkas

Menyelaraskan data grup fitur Visitor di production dengan kode aplikasi:

| Data | Production sebelum | Sesudah | Development |
|---|---|---|---|
| `N_HRIS_Master_Grup_Fitur.Kode_Grup_Fitur` (grup Visitor) | `VMS` | `VISITOR` | `VISITOR` (tak berubah) |
| `N_HRIS_Grup_Fitur_Map.Flag_Hibah_Role` halaman admin Visitor | `'T'` (dicabut manual 2026-09-18) | `'T'` (dicatat, 0 baris) | `'T'` (tak berubah) |

## Kenapa muncul sekarang

Keluhan 2026-09-18: pemegang `ADMIN_PERMIT` di production (RIAN KRISNADI dan lainnya)
tetap melihat seluruh menu admin Tamu. Penelusurannya menemukan dua sebab:

1. **Kode grup berbeda antar lingkungan.** Grup Visitor di production berkode `VMS`,
   sedangkan development dan seluruh kode aplikasi memakai `VISITOR`; tidak satu baris
   kode pun memakai `VMS`. Setiap pencarian grup lewat kodenya meleset tanpa galat:
   - langkah 2 DML `DDL-2026-110-hibah-role-per-halaman` mengubah **0 baris**, sehingga
     hibah halaman admin Visitor tetap `'Y'`;
   - halaman Menu & Matrix Visitor (`MenuApprovalMatrixController`,
     `LOWER(Kode_Grup_Fitur) = 'visitor'`) tidak menemukan grupnya;
   - `ApprovalMatrixService::TAB_TAK_BERLAKU['VISITOR']` tidak pernah cocok.
2. **Resolver sidebar tanpa penyaring** `Flag_Hibah_Role`. Diperbaiki di kode (PR #440,
   branch `fix/system/sidebar-hibah-role`), bukan di change ini.

Pencabutan hibah sudah dilakukan manual di production pada 2026-09-18
(`UPDATE ... WHERE Id_Grup_Fitur = 4`, 13 baris). Change ini mencatatnya secara idempoten.

Verifikasi production DDL-2026-110 sebelumnya berstatus "PRODUCTION_DONE" karena hanya
memeriksa **objek skema** (kolomnya ada); isi DML-nya tidak ikut diperiksa. Itulah
sebabnya selisih ini baru ketahuan dari keluhan.

## Keputusan desain

**Data diselaraskan ke kode, bukan kode diajari `VMS`.** Registry modul
(`docs/architecture/module-registry.md`) menetapkan `VISITOR`, dan beberapa tempat di
kode mencari grup lewat kodenya. Menambal tiap tempat dengan alias berarti selisih
lingkungan dibiarkan hidup dan akan menjebak kode berikutnya.

**Grup dicari lewat role `ADMIN_PERMIT`/`ADMIN_TAMU`**, bukan lewat kode (itulah yang
berbeda) maupun Id (tidak dijamin sama). Penjaga menolak bila kedua role tidak
menunjuk tepat satu grup, bila grup itu tidak memetakan `visitorVerifikatorPermitPage`,
atau bila kode `VISITOR` sudah dipakai grup lain (kolomnya dijaga indeks unik
`IX_HRIS_Master_Grup_Fitur_Kode`).

**Yang sengaja tidak disentuh:** `Is_Global_Menu` untuk `visitorPermitPage` dan
`visitorTamuPage`. Di production keduanya `'T'` (di development `'Y'`), jadi pengajuan
permit/tamu di production hanya terbuka bagi pemegang role Visitor dan kunci eksplisit.
Menjadikannya global adalah keputusan bisnis tersendiri.

## Status

Development (`Web_HRIS`, 2026-09-18): **DEV_EXECUTED**.

- Eksekusi nyata: kode grup 0 baris, hibah 0 baris, pendaftaran dashboard 1 baris,
  sesuai harapan karena development sudah selaras.
- Jalur production disimulasikan di dalam transaksi yang di-`ROLLBACK`: grup 4 diubah ke
  `VMS` dan 3 hibah dikembalikan `'Y'`, lalu skrip dijalankan. Hasilnya kode 1 baris,
  hibah 3 baris, sisa `'Y'` hanya dua halaman pengajuan. Sesudah rollback data
  development utuh (`VISITOR`, 2 baris `'Y'`).

## Berkas

| Peran | Path |
|---|---|
| DML | `database/dml/DML-2026-124-selaraskan-grup-visitor/data-change.sql` |
| Kode terkait | `AuthorizationScopeResolver::getUsablePageTypes()` (PR #440) |

## Pekerjaan user

- Review, rekam di SQL Tracker, lalu jalankan ke production (`emi_db_web_hr`).
  Harapan: kode grup **1 baris**, hibah **0 baris** (sudah dicabut manual), pendaftaran 1 baris.
- Sebelum menjalankan, pastikan tidak ada data production lain yang menyimpan teks
  `'VMS'` sebagai rujukan modul (mis. konfigurasi approval atau notifikasi). Kode
  aplikasi sendiri tidak memakainya.
- Verifikasi sesudahnya: halaman Menu & Matrix Visitor terbuka, dan pemegang
  `ADMIN_PERMIT` hanya melihat menu admin Permit.
