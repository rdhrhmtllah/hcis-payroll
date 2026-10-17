<p align="center">
  <img src="public/logo/EVOGROUP.png" width="180" alt="EVO Group Logo" />
</p>

<h1 align="center">HCIS Payroll Platform</h1>

<p align="center">
  <b>Sistem Manajemen Kompensasi, Penggajian &amp; Perpajakan Karyawan Terintegrasi</b><br>
  Dibangun dengan arsitektur modern berbasis <b>Laravel 10</b>, <b>Inertia.js</b>, <b>Vue 3</b>, dan <b>HCIS Design System</b>.
</p>

<p align="center">
  <a href="https://github.com/rdhrhmtllah/hcis-payroll"><img src="https://img.shields.io/badge/Status-Active%20Development-success?style=flat-square" alt="Status" /></a>
  <a href="https://github.com/rdhrhmtllah/hcis-payroll"><img src="https://img.shields.io/badge/Stack-Laravel%2010%20%7C%20Inertia%20%7C%20Vue%203-indigo?style=flat-square" alt="Tech Stack" /></a>
  <a href="https://github.com/rdhrhmtllah/hcis-payroll"><img src="https://img.shields.io/badge/Design%20System-HCIS%20Verified-blue?style=flat-square" alt="Design System" /></a>
</p>

---

Aplikasi web penggajian (*Payroll Management System*) modern, terstandarisasi, dan terintegrasi. Menggunakan fondasi arsitektur dan **HCIS Design System** resmi untuk menjamin konsistensi visual, aksesibilitas tinggi, dan kinerja antarmuka yang optimal.

---

## 1. Arsitektur Frontend & HCIS Design System

HCIS Payroll menerapkan sistem desain resmi yang wajib dipatuhi oleh seluruh pengembang maupun AI agent (Claude, Hermes, Codex, dsb.).

### Prinsip Utama: "Desain Tanpa Aksen"
1. **Permukaan Bersih & Netral**: Gunakan kontainer putih (`#ffffff`) atau abu-abu lembut (`var(--hcis-slate-50, #f8fafc)`) dengan garis tepi netral tipis (`border border-slate-200`).
2. **Tanpa Aksen Tebal / Berlebihan**: Dilarang menggunakan aksen dekoratif tebal seperti garis tepi kiri berwarna (`border-left: 4px solid ...`) atau blok warna menyala (`bg-warning`) yang kontrasnya kasar.
3. **Hirarki Lewat Tipografi**: Sorotan informasi dibangun melalui ukuran huruf, ketebalan teks (`font-semibold`, `font-bold`), serta tata letak rapi—bukan melalui taburan warna-warni.
4. **Satu Font Konsisten**: Seluruh teks, angka nominal gaji, NIK, tanggal, badge, maupun kode wajib menggunakan satu font baku: **Inter**. Dilarang menggunakan `font-monospace` sembarangan.
5. **Dilarang Meniru Class Bootstrap Mentah**: Seluruh markup UI wajib menggunakan komponen resmi `Hcis*` dari `@/components/ui`.

---

## 2. Tabel Padanan Komponen Wajib

Setiap kebutuhan UI wajib menggunakan komponen resmi berikut:

| Kebutuhan Antarmuka | DILARANG (Pola Mentah / Legacy) | WAJIB (Standar Resmi HCIS) |
|---|---|---|
| **Kontainer / Card** | `<div class="card">`, `<div class="card-header">` | `<HcisCard variant="default\|glass\|flat">` |
| **Tombol Aksi** | `<button class="btn btn-primary">` | `<HcisButton variant="primary">` |
| **Tombol Sekunder / Batal** | `<button class="btn btn-secondary">` | `<HcisButton variant="secondary">` |
| **Tombol Hantu / Ikon** | `<button class="btn btn-link">` | `<HcisButton variant="ghost">` |
| **Tombol / Tab Pill** | `<button class="btn rounded-pill">` | **DILARANG.** Gunakan `HcisTabs` atau `HcisFilterBar` |
| **Penyaring & Pencarian** | Tombol manual + `<div class="input-group">` | `<HcisFilterBar :quick-chips="..." :filters="..." />` |
| **Label Status / Badge** | `<span class="badge bg-primary">`, `bg-warning` | `<HcisBadge :label="..." variant="..." appearance="subtle">` |
| **Tabel Data Ringkas** | Tabel HTML biasa tanpa pembungkus | `.ap-kemampuan-table-wrap` + `.ap-kemampuan-table` |
| **Tabel Data Lengkap** | Tabel kustom / div berantakan | `<HcisTable>` atau `<HcisDataTable>` |
| **Form Input / Teks** | `<input class="form-control">` | `<HcisField>`, `<HcisInputNumber>`, `<HcisSelect>` |
| **Pilihan Tanggal** | Datepicker eksternal | `<HcisDatePicker>`, `<HcisDateRangePicker>` |
| **Modal / Dialog** | Modal Bootstrap / overlay kustom | `<HcisDialog>` atau `<HcisDualPaneDialog>` |
| **Keadaan Kosong** | Teks biasa "Data Kosong" | `<HcisEmptyState title="..." description="..." />` |
| **Skeleton Loading** | Spinner Bootstrap (`spinner-border`) | `<HcisSkeleton :lines="N" />` |
| **Kartu Metrik / Angka** | Kotak angka dengan warna manual | `<HcisStatCard layout="compact" appearance="crystal">` |
| **Palet Perintah (Ctrl+K)** | Input pencarian melayang manual | `<HcisCommandPalette :show="..." :sources="..." />` |

---

## 3. Katalog Komponen Interaktif (`/design-system`)

Aplikasi dilengkapi modul katalog interaktif untuk melihat langsung dokumentasi teknis, daftar *props*, *events*, *slots*, serta demo interaktif tiap komponen:

- **Akses Langsung**: Buka URL `/design-system` di browser saat aplikasi berjalan.
- **Fondasi Desain**: `/design-system/fondasi` (Warna, tipografi, radius, elevasi).
- **Pola Tata Letak**: `/design-system/pola` (Archetype form, data table, detail screen).
- **Panduan Kode**: `/design-system/panduan` & `docs/architecture/standar-ui-design-system-hcis.md`.
- **Sumber Tunggal Definisi**: `resources/js/components/ui/registry.js`.

### Contoh Pemakaian Komponen di Halaman Vue

```vue
<template>
    <AppShell>
        <HcisHeader title="Penggajian Karyawan" subtitle="Kelola batch payroll bulanan">
            <template #actions>
                <HcisButton variant="primary" icon="bi-plus-circle" @click="bukaModal">
                    Tambah Batch
                </HcisButton>
            </template>
        </HcisHeader>

        <HcisCard title="Ringkasan Periode">
            <HcisStatGroup columns="3">
                <HcisStatCard title="Total Gaji" :value="formatRupiah(totalGaji)" icon="bi-cash" />
                <HcisStatCard title="Karyawan Masuk Payroll" :value="totalKaryawan" icon="bi-people" />
                <HcisStatCard title="Status" value="PROCESSED" icon="bi-check-circle">
                    <template #badge>
                        <HcisBadge variant="success">Selesai</HcisBadge>
                    </template>
                </HcisStatCard>
            </HcisStatGroup>
        </HcisCard>
    </AppShell>
</template>

<script setup>
import AppShell from '@/Layouts/AppShell.vue';
import {
    HcisHeader,
    HcisCard,
    HcisButton,
    HcisStatCard,
    HcisStatGroup,
    HcisBadge,
} from '@/components/ui';
</script>
```

---

## 4. Perintah Verifikasi Otomatis (Design System & Health Checks)

Sebelum melakukan commit atau push kode baru, jalankan skrip verifikasi untuk memastikan tidak ada pelanggaran standar desain, import yang putus, atau route yang gagal:

### 1. Verifikasi Kepatuhan Design System
Memeriksa file `.vue` terhadap class Bootstrap mentah, tombol pill bulat, background mencolok, dan font non-standar:
```bash
# Periksa berkas Vue yang berubah di git (cepat)
php scripts/verify-design-system.php

# Periksa satu berkas tertentu
php scripts/verify-design-system.php --file=resources/js/Pages/PAYROLL/Dashboard/Index.vue

# Periksa seluruh berkas Vue dalam proyek
php scripts/verify-design-system.php --all
```

### 2. Verifikasi Import Frontend (Zero Broken Imports)
Memastikan seluruh import modul, komponen, dan utilitas JavaScript/Vue saling terhubung:
```bash
php scripts/verify-frontend-imports.php
```

### 3. Verifikasi Rute Halaman Inertia
Memeriksa apakah seluruh `Inertia::render()` di controller terhubung ke file `.vue` yang sah:
```bash
php scripts/verify-inertia-pages.php
```

### 4. Build Aset Frontend
Memastikan bundling Vite berjalan mulus tanpa error kompilasi:
```bash
npm run build
```

---

## 5. Struktur Direktori Proyek

```
hcis-payroll/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── PAYROLL/               # Controller fitur domain Payroll
│   │   │   └── SYSTEM/                # Controller sistem (Auth, Audit, Design System)
│   │   └── Middleware/                # HandleInertiaRequests, Authenticate, dll.
│   └── Models/                        # Model Eloquent (User, Karyawan, Divisi, Payroll)
├── resources/
│   ├── js/
│   │   ├── components/
│   │   │   ├── ui/                    # 40+ Komponen Resmi Hcis* (Single Source of Truth)
│   │   │   └── Shell/                 # Komponen Shell (AppSidebar, AppTopbar)
│   │   ├── Layouts/
│   │   │   └── AppShell.vue           # Layout tunggal aplikasi dengan sidebar & topbar
│   │   ├── Pages/
│   │   │   ├── PAYROLL/               # Halaman modul Payroll
│   │   │   └── SYSTEM/                # Halaman sistem & katalog /design-system
│   │   └── utils/                     # Helper & utilitas format
├── routes/
│   ├── PAYROLL/                       # Rute modul Payroll (payroll.php)
│   ├── SYSTEM/                        # Rute teknis (DesignSystem.php, dll.)
│   ├── api.php                        # Rute API RESTful
│   └── web.php                        # Rute web utama
├── scripts/
│   ├── verify-design-system.php       # Validator kepatuhan UI Design System
│   ├── verify-frontend-imports.php    # Validator dependensi import frontend
│   └── verify-inertia-pages.php       # Validator pemetaan halaman Inertia
└── docs/
    └── architecture/
        └── standar-ui-design-system-hcis.md  # Dokumen aturan baku desain HCIS
```

---

## 6. Panduan Menjalankan Proyek Secara Lokal

1. **Clone repository**:
   ```bash
   git clone https://github.com/rdhrhmtllah/hcis-payroll.git
   cd hcis-payroll
   ```

2. **Pasang dependensi backend & frontend**:
   ```bash
   composer install
   npm install
   ```

3. **Konfigurasi Environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *Sesuaikan konfigurasi koneksi database di berkas `.env`.*

4. **Jalankan Aplikasi**:
   ```bash
   # Terminal 1: Backend Server
   php artisan serve

   # Terminal 2: Frontend HMR (Hot Module Replacement)
   npm run dev
   ```

5. **Buka Aplikasi**:
   - Dashboard: `http://localhost:8000/dashboard`
   - Design System: `http://localhost:8000/design-system`
