# DML-2026-111-tampilkan-menu-distribusi-performance

**Deskripsi**: Mengaktifkan tampilan menu "Distribusi Performance" (`/performance-appraisal/distribusi`) pada sidebar modul KPI di bawah grup `Administrator PA`.

- **Change ID**: `DML-2026-111-tampilkan-menu-distribusi-performance`
- **Target Database**: `Web_HRIS` (Development)
- **Tipe**: `DML`
- **Checksum SHA-256**: `790157a3a6b581f2981ff81349743b092685b8c5307b3acb95cec12a959db4de`
- **Affected Objects**:
  - `N_HRIS_Menus.Is_Show_Sidebar`
  - `N_HRIS_Menus.Nama_Menu`
  - `N_HRIS_Menus.Menu_Subtitle`

---

### Rasional & Dampak
- Halaman analisis sebaran kinerja organisasi dan kurva distribusi normal (`/performance-appraisal/distribusi`) telah siap dan diakses secara langsung oleh admin HC.
- Mengubah `Is_Show_Sidebar` dari `'T'` menjadi `'Y'` untuk `Jenis_page = 'PaDistribusiPage'` agar menu muncul pada sidebar navigasi di modul KPI kelompok `Administrator PA`.

---

### Verifikasi Development
1. `UPDATE N_HRIS_Menus SET Is_Show_Sidebar = 'Y', Nama_Menu = 'Distribusi Performance', Menu_Subtitle = 'Analisis kurva normal & sebaran hasil' WHERE Jenis_page = 'PaDistribusiPage' AND Kode_Perusahaan = '001';` $\rightarrow$ 1 baris terpengaruh.
2. `InertiaShellService::flushMenuCache()` dijalankan.
3. Struktur navigasi sidebar diverifikasi via `InertiaShellService::build()` $\rightarrow$ menu `Distribusi Performance` muncul aktif di bawah grup `Administrator PA`.
