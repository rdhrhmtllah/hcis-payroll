# DML-2026-114-hide-menu-kalibrasi-pa

**Deskripsi**: Menyembunyikan menu "Kalibrasi Grade" (`/performance-appraisal/kalibrasi`) dari sidebar navigasi `Administrator PA` karena alur kerja kalibrasi telah terintegrasi langsung pada halaman Pemantauan & Verifikasi (`/performance-appraisal/pemantauan?tab=HASIL`).

- **Change ID**: `DML-2026-114-hide-menu-kalibrasi-pa`
- **Target Database**: `Web_HRIS` (Development)
- **Tipe**: `DML`
- **Checksum SHA-256**: `ed156a563e9446ce6fbf1b6aa02f9a491c6ca8f6f0922af7e460d682beafe857`
- **Affected Objects**:
  - `N_HRIS_Menus.Is_Show_Sidebar`
  - `N_HRIS_Menus.Sort_Menu`

---

### Rasional & Dampak
- Alur kalibrasi evaluasi multi-rater dilakukan secara terpadu melalui modal review hasil per peserta (`PaResultDialog`) pada halaman `Pemantauan & Verifikasi`.
- Menyembunyikan menu (`Is_Show_Sidebar = 'T'`) pada tabel `N_HRIS_Menus` untuk `Url_Menu = '/performance-appraisal/kalibrasi'`, serta merapatkan nomor urut `Sort_Menu` (1..13) untuk menu aktif di bawah grup `Administrator PA`.
- Endpoint controller `/performance-appraisal/kalibrasi` dialihkan secara otomatis (*redirect*) menuju `/performance-appraisal/pemantauan?tab=HASIL` agar tautan lama atau bookmark pengguna tetap berfungsi dengan mulus.

---

### Eksekusi Development
1. DML dijalankan pada basis data `Web_HRIS`.
2. Cache menu dibersihkan (`php artisan cache:clear`).
3. Susunan sidebar dan status `Is_Show_Sidebar = 'T'` terverifikasi.
