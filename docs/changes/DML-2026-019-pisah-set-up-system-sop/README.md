# DML-2026-019 — Pisah halaman Kebijakan & Otomasi SOP

**Target development:** `Web_HRIS` @ team311 · **Status:** DEV_DONE · SQL Tracker PENDING · Production PENDING

## Yang berubah

Halaman `/sop/admin/kebijakan` sebelumnya memuat empat tab. Dua di antaranya
menyentuh mesin, dua lainnya menyentuh kebijakan penugasan. Keduanya kini
halaman terpisah:

| Halaman | URL | `Jenis_page` | Header sidebar | Tab |
|---|---|---|---|---|
| Otomasi SOP | `/sop/admin/kebijakan` | `menuSopKebijakanPage` | Administrator SOP | Aturan Penugasan, Riwayat Eksekusi |
| Kebijakan Global SOP | `/sop/admin/setup-system` | `menuSopSetupSystemPage` | **Set Up System** (baru) | Kebijakan Global, Otomasi Terjadwal |

Keduanya **tetap milik modul SOP** — `Id_Grup_Fitur = 3` (LMS), diverifikasi dari
`N_HRIS_Master_Grup_Fitur` pada 2026-08-22. Yang dipisah halamannya, bukan grup
fiturnya.

## Mengapa per-halaman, bukan per-tab

Gerbang otorisasi aplikasi ini per-`Jenis_page`. `CheckJabatan` memanggil
`User::memilikiJabatan()`, yang menggabungkan tiga sumber **tanpa prioritas dan
tanpa deny**:

```
akses = (role -> Id_Grup_Fitur -> SELURUH Jenis_page grup itu)
      ∪ (HRIS_Page_Access eksplisit per-user)
      ∪ (N_HRIS_Menus.Is_Global_Menu = 'Y')
```

Tidak ada konsep izin di bawah halaman. Menyembunyikan tab di Vue tidak menutup
satu endpoint pun — ketiga belas endpoint `/api/v1/sop/admin/kebijakan/*`
bergantung pada satu kunci yang sama. Memisah halaman memberi pemisahan hak
secara langsung.

## Langkah DML

1. `N_HRIS_Menus` — 1 baris baru (`menuSopSetupSystemPage`, header `setup_system`
   / "Set Up System", `Sort_Header` 12, tepat setelah Administrator SOP di 11).
   Header di aplikasi ini didenormalisasi per baris menu; tidak ada tabel header
   terpisah (`InertiaShellService:512-528`).
2. `N_HRIS_Grup_Fitur_Map` — 1 baris (`Id_Grup_Fitur = 3`). Tanpa baris ini menu
   tidak pernah muncul di sidebar dan tidak ada galat yang menjelaskannya.
3. `HRIS_Page_Access` — hak diturunkan dari pemegang `menuSopKebijakanPage`.
4. `N_HRIS_Menus` — halaman lama di-rename menjadi "Otomasi SOP". URL dan
   `Jenis_page` sengaja tidak diubah: keduanya kunci akses yang sudah dipegang,
   dan `/sop/admin/kebijakan` masih ditautkan dari `contentDashboard.vue`.
5. `N_SYSTEM_Database_Change` — pendaftaran ke dashboard.

### Langkah 3 tidak boleh dilewat

Pada 2026-08-22, `N_HRIS_Role_User` **tidak punya satu pun penugasan role aktif
untuk `Id_Grup_Fitur = 3`**. Seluruh akses SOP hari ini bergantung pada
`HRIS_Page_Access` eksplisit — dua user: `NOIR` (Id 4) dan `aji` (622). Tanpa
langkah 3, halaman baru membalas 403 untuk semua orang.

## Hasil eksekusi development

| Objek | Hasil |
|---|---|
| `N_HRIS_Menus` | 1 insert (Id_Menu 90) + 1 update (rename) |
| `N_HRIS_Grup_Fitur_Map` | 1 insert (Id_Grup_Map 223) |
| `HRIS_Page_Access` | 2 insert (UserID_Web 4, 622) |
| `N_SYSTEM_Database_Change` | 1 insert |

Diverifikasi setelah `flushMenuCache()`:

- `memilikiJabatan('menuSopSetupSystemPage')` = BOLEH untuk user 4 dan 622,
  DITOLAK untuk kontrol negatif (user 1 / Steven).
- Sidebar untuk user 4 menampilkan grup `Set Up System` berisi "Kebijakan Global
  SOP", dan "Otomasi SOP" pada grup Administrator SOP.

## Perubahan kode yang menyertai

- Baru: `SopAdminSetupSystemController` (settings + scheduler), `SopSetupSystem.vue`.
- `SopAdminKebijakanController` melepas `settings()`, `updateSettings()`,
  `schedulerHealth()`; kunci `schedulers` dilepas dari respons `/summary`.
- `POST /api/v1/sop/admin/kebijakan/runs/{type}` bergerbang **dua kunci**
  (`menuSopKebijakanPage,menuSopSetupSystemPage`) — tombolnya ada di kartu job
  halaman Set Up System, tetapi memicu evaluasi tepat setelah aturan diubah
  adalah kebutuhan admin modul.

### Cacat yang ikut diperbaiki

Kartu job pertama pada tab Otomasi Terjadwal memakai `type: 'apply'` dan
`command: 'sop:auto-apply'`. Keduanya tidak ada: `runNow()` hanya mengenal
`rule-scan` dan `reminder` (membalas 422), dan `N_HRIS_Scheduler_Configs`
memakai `sop:rule-scan` — sehingga kartunya selalu berbunyi "Belum
dikonfigurasi". Diperbaiki menjadi `rule-scan` / `sop:rule-scan`, dengan uji
regresi yang sudah dibuktikan menyala merah pada nilai lama.

## Tugas user

1. Review perubahan ini.
2. Jalankan DML ke production lewat SQL Tracker.
3. Verifikasi di production, lalu set `PRODUCTION_EXECUTED`.

Prasyarat production: kode aplikasi yang mendaftarkan rute `sop/admin/setup-system`
harus sudah ter-deploy lebih dulu — kalau tidak, menunya muncul tetapi 404.
