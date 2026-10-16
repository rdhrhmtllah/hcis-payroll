# DML-2026-115-label-menu-bobot-kpi-kompetensi

## Metadata

- **Change ID**: `DML-2026-115-label-menu-bobot-kpi-kompetensi`
- **Target Database**: `Web_HRIS`
- **Type**: DML
- **Revision**: `r1`
- **DML Checksum (SHA-256)**: `272047c94117d2f2066c70907240b21f382ed7def57747b04ef0346d6e7fd585`
- **Forward Checksum (SHA-256)**: `272047c94117d2f2066c70907240b21f382ed7def57747b04ef0346d6e7fd585`
- **Affected Objects**: `N_HRIS_Menus`
- **Status**: `DEV_EXECUTED` -> `TRACKER_PENDING`

## Deskripsi

Penyelarasan nama menu sidebar Administrator PA untuk halaman konfigurasi bobot pilar (`/performance-appraisal/master/bobot-pilar`) dari sebelumnya `Master · Bobot Penilaian` menjadi **`Master · Bobot KPI & Kompetensi`**, dengan subjudul **`Proporsi pembagian bobot capaian KPI vs evaluasi Kompetensi per level jabatan`** serta perapian nomor urut menu `Sort_Menu` (1..14).

### Latar Belakang & Rasional

Istilah "Bobot Pilar" atau "Bobot Penilaian" sebelumnya terlalu abstrak dan kurang langsung dipahami oleh administrator HR. Pada kenyataannya, konfigurasi ini mengatur proporsi persentase evaluasi antara 2 komponen utama:
1. **KPI (Pilar Hasil Kerja / Output)**
2. **Kompetensi (Pilar Perilaku / 360°)**

Penamaan menjadi **Master · Bobot KPI & Kompetensi** jauh lebih lazim, jelas, dan langsung mengkomunikasikan fungsi halaman kepada pengguna.

## Artefak Perubahan

- `database/dml/DML-2026-115-label-menu-bobot-kpi-kompetensi/data-change.sql`

## Verifikasi Development

1. Update dijalankan pada basis data `Web_HRIS`.
2. Hasil verifikasi query membuktikan `Nama_Menu`, `Menu_Subtitle`, dan `Sort_Menu` telah terbarui dengan benar.
3. Cache dibersihkan (`php artisan cache:clear`).
