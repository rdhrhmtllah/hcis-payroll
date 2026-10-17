# Standar UI & Design System HCIS (Autoritatif & Mengikat)

> **STATUS DOKUMEN: AUTHORITATIVE & BINDING (SUMBER TUNGGAL KEBENARAN)**  
> Berlaku untuk seluruh AI agent (Claude, Hermes, Codex, OpenCode, delegated agents) dan pengembang frontend.  
> Tidak ada toleransi untuk improvisasi gaya, padding sembarangan, warna manual, atau penyalinan pola lama.

---

## 1. Masalah Utama: Pola Warisan (Legacy) & Larangan Meniru

Repository ini memiliki ratusan berkas warisan (legacy) yang ditulis sebelum design system HCIS dibakukan. Berkas-berkas lama tersebut banyak memakai:
- Class Bootstrap mentah (`btn`, `card`, `card-header`, `input-group`, `form-control`, `badge bg-*`).
- Tombol lonjong pill kaku (`rounded-pill`).
- Kotak warna mencolok (`bg-warning`, dll.) dan border aksen tebal.
- Ukuran font, padding, dan bayangan yang acak.

**ATURAN MUTLAK:**  
**DILARANG KERAS MENYALIN ATAU MENIRU KODE DARI BERKAS WARISAN TERSEBUT!**  
Saat membuat halaman, tab, modal, atau komponen baru, agen HANYA boleh merujuk ke **Daftar Berkas Teladan (Gold Standard)** di Bagian 6 dokumen ini.

---

## 2. Tabel Padanan Wajib: Dilarang vs Wajib Pakai

Setiap kali membutuhkan elemen antarmuka, gunakan padanan resmi berikut:

| Kebutuhan UI | DILARANG (Pola Lama / Mentah) | WAJIB (Standar Resmi HCIS) |
|---|---|---|
| **Kontainer / Kartu** | `<div class="card">`, `<div class="card-header">` | `<HcisCard variant="glass\|flat\|default">` |
| **Tombol Utama** | `<button class="btn btn-primary">` | `<HcisButton variant="primary">` |
| **Tombol Sekunder** | `<button class="btn btn-secondary">`, `btn-outline-*` | `<HcisButton variant="secondary">` |
| **Tombol Hantu / Ikon** | `<button class="btn btn-link">` | `<HcisButton variant="ghost">` |
| **Tombol / Tab Pill** | `<button class="btn rounded-pill">` | **DILARANG.** Gunakan `HcisFilterBar` atau `HcisTabs` |
| **Penyaring & Cari** | Tombol manual + `<div class="input-group">` | `<HcisFilterBar :quick-chips="..." :filters="..." />` |
| **Badge / Label Status** | `<span class="badge bg-primary">`, `bg-warning` | `<HcisBadge :label="..." variant="..." appearance="subtle">` |
| **Tabel Data Ringkas** | Tabel HTML biasa tanpa styling baku | `.ap-kemampuan-table-wrap` + `.ap-kemampuan-table` (lihat Bagian 4) |
| **Tabel Data Kompleks** | Tabel kustom atau div grid acak | `<HcisTable>` atau `<HcisDataTable>` |
| **Input Teks / Form** | `<input class="form-control">` | `<HcisField>`, `<HcisInput>`, `<HcisSelect>` |
| **Ikon Antarmuka** | `<i class="bi bi-...">`, ikon SVG manual, emoji | `<HcisIcon name="..." />` |
| **Keadaan Kosong** | Teks biasa "Data kosong" di tengah div | `<HcisEmptyState title="..." description="..." />` |
| **Indikator Memuat** | Spinner Bootstrap (`spinner-border`) | `<HcisSkeleton :lines="N" />` |
| **Kartu Metrik / Angka** | Kotak angka dengan warna background manual | `<HcisStatCard layout="compact" appearance="crystal">` |
| **Pesan Peringatan** | `<div class="alert alert-warning">` | `<HcisAlert variant="..." appearance="subtle">` |
| **Modal / Dialog** | Modal Bootstrap mentah | `<HcisDialog>` atau `HcisDualPaneDialog` |

---

## 3. Prinsip Visual: "Desain Tanpa Aksen"

User secara tegas menolak tampilan dengan aksen dekoratif berlebihan. Terapkan prinsip berikut:

1. **Kotak Polos & Bersih**:  
   Gunakan permukaan putih (`#ffffff` / `var(--hcis-surface)`) atau netral lembut (`var(--hcis-slate-50, #f8fafc)`).  
   Garis tepi menggunakan border netral tipis: `1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.85)`.
2. **Dilarang Border Warna Tebal**:  
   Jangan memakai aksen dekoratif seperti garis tepi kiri berwarna (`border-left: 4px solid ...`).
3. **Dilarang Kotak Latar Warna Menyala**:  
   Jangan memakai background blok warna kuning (`bg-warning`), merah terang, atau biru pekat yang kontrasnya kasar.  
   Untuk menyorot status penting (misalnya alur persetujuan transaksi), gunakan badge netral/subtle atau pita latar lembut (`#faf5ff` dengan border `#e9d5ff`).
4. **Sorot Lewat Tipografi**:  
   Hirarki visual dibangun lewat **ukuran huruf, ketebalan font (`fw-bold`, `fw-semibold`), dan tata letak teratur**, bukan melalui warna-warna mencolok.
5. **Dilarang Tab / Tombol Berbentuk Pill Bulat Kaku**:  
   Gunakan navigasi resmi `HcisTabs` (dengan gaya underline) atau `HcisFilterBar` (dengan quick-chips resmi).

---

## 4. Dimensi, Tipografi & Spacing Baku

Seluruh elemen antarmuka wajib mengikuti ukuran dan jarak baku berikut:

### Tipografi
- **Judul Layar / Page**: `h4.fw-bold.text-dark` (font-size ~1.25rem - 1.5rem)
- **Judul Kartu / Modul**: `h5.fw-bold.text-dark` (font-size ~1.1rem) atau `h6.fw-bold.text-dark` (~0.95rem)
- **Kicker (Kategori kecil di atas judul)**:  
  ```css
  .ap-kicker {
      font-size: 0.6875rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--hcis-brand-600, #0284c7);
  }
  ```
- **Keterangan / Subtitle**: `p.ap-kecil.text-muted` (font-size: `0.75rem`, line-height: `1.45`)
- **Konsistensi Font (LARANGAN KERAS FONT-MONOSPACE / FONT LAIN)**:  
  **DILARANG KERAS menggunakan `font-monospace` atau jenis font lain!** Seluruh teks, angka, NIK, tanggal, badge, maupun kode wajib konsisten 100% menggunakan font standar HCIS (`Inter` / `var(--hcis-font-body)`). Jangan pernah berganti font di tengah halaman.

### Padding & Spacing
- **Card Header**: `padding: 0.85rem 1.15rem`
- **Card Body**: `padding: 1rem 1.15rem`
- **Banner / Governance Card Header**: `padding: 0.75rem 1rem`
- **Table Cell (`th` & `td`)**: `padding: 0.65rem 0.85rem`
- **Gap Antar Elemen**: `gap-2` (0.5rem), `gap-3` (0.75rem), `gap-4` (1rem). Jangan membuat gap raksasa yang menyisakan ruang kosong canggung.

### Sudut (Border Radius) & Bayangan (Shadow)
- **Kartu Utama / Container**: `border-radius: var(--hcis-radius-xl, 12px)`
- **Item / Panel Dalam / Table Wrap**: `border-radius: var(--hcis-radius-md, 8px)` atau `10px`
- **Bayangan Halus (Baku)**: `box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03)`
- **Bayangan Hover**: `box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06)`

### Pola Tabel Standar HCIS (CSS Wajib untuk Tabel Rincian)
```css
.ap-kemampuan-table-wrap {
    overflow-x: auto;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
}
.ap-kemampuan-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
}
.ap-kemampuan-table th {
    padding: 0.65rem 0.85rem;
    background: var(--hcis-surface-subtle, #f8fafc);
    color: var(--hcis-text-secondary, #64748b);
    font-weight: 700;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
}
.ap-kemampuan-table td {
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
    vertical-align: middle;
}
.ap-kemampuan-table tr:last-child td {
    border-bottom: none;
}
.ap-kemampuan-table tr:hover td {
    background-color: var(--hcis-surface-subtle, #f8fafc);
}
```

---

## 5. Tata Letak Baku (Layout Archetype)

Jangan membagi layout menjadi 2 kolom lebar bila salah satu kolom hanya berisi sedikit teks (menghindari kotak kosong melompong). Gunakan susunan vertikal seimbang:

1. **Baris Metrik Ringkas (Atas)**:  
   Gunakan grid 4 kartu `HcisStatCard` (`layout="compact" appearance="crystal"`).
2. **Kartu Tata Kelola / Header Layar**:  
   Kartu putih bersih berisi `ap-kicker`, judul, badge jumlah, deskripsi, dan tombol aksi (Segarkan/Tambah). Bila perlu, sertakan grid panduan singkat di bawahnya.
3. **Filter Bar Mandiri**:  
   Letakkan `HcisFilterBar` di baris tersendiri di bawah header card. Jangan dijejalkan ke dalam header card.
4. **Daftar Kartu Konten**:  
   Setiap entitas utama berada dalam kartu tersendiri.  
   - Header kartu: Badge kategori/modul, judul, kode, status badge, tombol aksi.
   - Body kartu: Ribbon alur/metadata (bila ada), diikuti tabel data terstruktur (`ap-kemampuan-table`).

---

## 6. Daftar Berkas Teladan (Gold Standard Reference)

Sebelum membuat atau mengubah komponen Vue, buka dan ikuti kode dari berkas teladan berikut:

1. **Tata Kelola, Metrik & Filter**:  
   `resources/js/Pages/SYSTEM/Governance/Authorization/AksesPeran/components/TabAdmin.vue`
2. **Katalog, Matriks Hak & Tabel Otorisasi**:  
   `resources/js/Pages/SYSTEM/Governance/Authorization/AksesPeran/components/TabPeran.vue`
3. **Template, Kriteria & Filter Bar**:  
   `resources/js/Pages/SYSTEM/Governance/Authorization/AksesPeran/components/TabCakupan.vue`
4. **Katalog Fitur & Alur Kerja Bisnis (Hasil Refactor Baku)**:  
   `resources/js/Pages/SYSTEM/Governance/Authorization/AksesPeran/components/TabFiturBisnis.vue`
5. **Form Wizard Interaktif**:  
   `resources/js/Pages/SYSTEM/Governance/Authorization/AksesPeran/components/WizardPenugasan.vue`

---

## 7. Verifikasi Wajib Sebelum Selesai

Setiap agen yang membuat atau memodifikasi tampilan frontend wajib menjalankan skrip pemeriksa design system:

```bash
php scripts/verify-design-system.php
```

Skrip ini akan memeriksa apakah ada class Bootstrap mentah, tombol pill, atau elemen non-standar yang terselip.
