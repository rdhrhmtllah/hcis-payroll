# Memisahkan Laporan Visitor menjadi Monitoring dan Export Studio

- Tanggal: 2026-08-25
- Modul: `VISITOR` / submodul `Workflow` / fitur `Reporting`
- Basis data pengembangan: `Web_HRIS` @ team311
- Changeset: `DML-2026-026-register-visitor-export-studio-menu`

## Masalah

`/laporanvisitor` memuat tiga tab dalam satu shell: Dashboard, Aktivitas harian,
dan Export Studio. Monitoring tidak punya ruang tumbuh tanpa menambah tumpukan
pada halaman yang sudah dipakai bersama Export Studio.

## Keadaan yang ditemukan sebelum merancang

Empat temuan penelusuran yang membentuk desain ini:

1. **Sisi server sudah terpisah per fitur.** `app/…/Reporting/Export/`
   (controller, request, job, service, dataset) berdiri sendiri terhadap
   `app/…/Reporting/Dashboard/`. Yang menyatu hanya shell frontend dan
   pendaftaran halaman — jadi ini pemisahan shell, bukan pembelahan subsistem.
2. **`LaporanTamu.vue` dan `LaporanPermit.vue` yatim** — 834 baris, nol
   referensi. Dicabut sengaja di commit `ccc4d967 feat(VISITOR): add report
   export studio`; Export Studio menggantikannya, berkasnya tak pernah dibuang.
3. **Bug laten di berkas yatim itu**: `LaporanTamu.vue:368` memanggil
   `/laporanvisitor-permit`, bukan `-tamu`. Tidak berbahaya selama mati.
4. **Preseden ada di repo ini.** SOP Export Studio sudah jadi halaman sendiri
   (`sop/admin/export-studio`, method `halaman()`), lengkap dengan pola gerbang
   dan struktur folder yang bisa dicermin.

## Keputusan

| Pertanyaan | Keputusan |
|---|---|
| Izin akses | Dua kunci terpisah |
| Isi Monitoring | Dashboard + Aktivitas harian; berkas yatim dihapus |
| URL & kunci lama | Monitoring mewarisi `/laporanvisitor` + `visitorLaporanPage`; Studio serba baru |

Alternatif yang ditolak: satu shell dipakai dua route dengan prop pemilih tab.
Lebih murah, tapi dua halaman yang tumbuh ke arah berbeda akan saling menyandera
lewat satu berkas — persis yang ingin dihindari.

## Rancangan

### 1. Berkas frontend

```
Reporting/Monitoring/                  ← dari Laporan/
  monitoringVisitor.vue                shell, tanpa tab studio
  components/  dashboard/  api/        pindah apa adanya

Reporting/ExportStudio/                ← BARU, mencerminkan app/…/Reporting/Export/
  exportStudioVisitor.vue              shell baru
  components/VisitorExportStudioPanel.vue  (+ test) pindah dari Laporan/
```

Panelnya sudah mandiri (149 baris, menerima `base-url` sebagai prop), jadi ini
pemindahan berkas, bukan penulisan ulang. Rename folder nol risiko: tidak ada
baris basis data yang menyebut nama folder.

### 2. Sisi server

- `VisitorExportStudioController` mendapat satu method `halaman()`, mencermin
  `SopExportStudioController`.
- Route halaman baru: `GET /visitor/export-studio`.
- Gerbang API Studio pindah dari `visitorLaporanPage` ke `visitorExportStudioPage`.

**Satu kunci, bukan OR.** SOP memakai `check.jabatan:A,B` sebagai jembatan
sementara karena DML-nya belum jalan saat itu. DML kita dijalankan bersamaan,
dan OR justru membatalkan pemisahan yang jadi tujuan pekerjaan ini.

### 3. DML-2026-026

Tiga baris, murni penambahan:

| Tabel | Isi | Baris |
|---|---|---|
| `N_HRIS_Menus` | `visitorExportStudioPage`, header `administrator_visitor` | 1 |
| `N_HRIS_Grup_Fitur_Map` | petakan ke grup `VISITOR` (`Id_Grup_Fitur` = 4) | 1 |
| `HRIS_Page_Access` | user 4 dan 624 — persis pemegang `visitorLaporanPage` hari ini | 2 |

Tidak ada UPDATE dan tidak ada DELETE. `/laporanvisitor` beserta kuncinya tetap
utuh milik Monitoring, jadi tidak ada seorang pun yang bisa kehilangan akses.

**Batas yang harus diketahui: pemisahan izinnya tidak seutuh namanya.** Baris
`N_HRIS_Grup_Fitur_Map` wajib ada supaya menunya muncul, dan baris itu memberi
akses kepada siapa pun yang memegang role grup VISITOR. Lewat jalur role,
Monitoring dan Studio tetap satu paket — granularitas role di aplikasi ini
memang sebatas grup fitur (lihat `docs/architecture/module-registry.md`).
Pemisahan yang benar-benar menggigit hanya ada di jalur `HRIS_Page_Access`
eksplisit. Hari ini tak berdampak (yang lewat role hanya user 624), tapi jangan
berasumsi kunci terpisah berarti akses terpisah bagi pemegang role.

### 4. Pembersihan

- Hapus `LaporanTamu.vue` dan `LaporanPermit.vue` — bug salah endpoint ikut lenyap.
- Endpoint `laporanvisitor-tamu` / `-permit` menjadi tak berpemakai. **Dibiarkan**
  dan dilaporkan; membuang endpoint di luar permintaan.
- `routes/VISITOR/Workflow/Reporting/LaporanVisitorApi.php` yang duplikat
  **tidak** disentuh — nyata, tapi bukan pekerjaan ini.

### 5. Uji

| Yang diuji | Kenapa |
|---|---|
| Tiap halaman merender panelnya sendiri dan BUKAN milik tetangganya | Membuktikan pemisahannya nyata, bukan sekadar dua route ke isi yang sama |
| Halaman & API Studio menolak pemegang `visitorLaporanPage` saja | Inilah yang membuktikan gerbangnya menggigit; tanpa ini kunci baru cuma hiasan |
| Monitoring tetap terbuka bagi pemegang kunci lama | Menjamin tak ada yang jatuh saat rilis |
| Uji lama ikut pindah dan tetap hijau | `laporanVisitor.test.js`, `VisitorExportStudioPanel.test.js` |

## Sisa pekerjaan pengguna

Sesuai `AGENTS.md`: eksekusi DML ke SQL Tracker dan produksi, lalu verifikasi.
Ledger saat ini memuat 26 changeset dengan `tracker PENDING` dan
`production PENDING`; changeset ini menjadi yang ke-27.
