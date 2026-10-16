# DML-2026-190 — Penugasan Lengkap Dept Head & PIC Admin Seluruh Modul (RBAC)

Memasang pejabat **Kepala Bidang / Dept Head (`SUPER_ADMIN`)** dan **PIC Pengelola (`ADMIN`)** di seluruh 5 modul yang sebelumnya masih kosong agar rantai delegasi wewenang terdistribusi merata dan dapat diuji menyeluruh:

1. **ESS**:
   - Dept Head (`ESS_SUPER_ADMIN`): Frans Bachtiar (#594 - A1)
   - PIC Admin (`ESS_ADMIN`): Aji (#622) & Dhea (#600) *(sudah terpasang)*
2. **VISITOR**:
   - Dept Head (`VISITOR_SUPER_ADMIN`): Dimas (#603 - E15)
   - PIC Admin (`VISITOR_ADMIN`): Arya (#624) *(sudah terpasang)*
3. **DMS**:
   - Dept Head (`DMS_SUPER_ADMIN`): Cahya (#630) *(sudah terpasang)*
   - PIC Admin (`DMS_ADMIN`): Diva Mona Lisa (#595 - A2)
4. **LMS**:
   - Dept Head (`LMS_SUPER_ADMIN`): Erick (#631 - F18)
   - PIC Admin (`LMS_ADMIN`): Amelia (#683 - A5)
5. **KPI**:
   - Dept Head (`KPI_SUPER_ADMIN`): Tari (#642) & Aji (#622) *(sudah terpasang)*
   - PIC Admin (`KPI_ADMIN`): Haris (#644) & Iqbal (#648) *(sudah terpasang)*

## Hasil Akhir

Kini setiap modul memiliki minimal:
- 1 Super Admin (Dept Head) dengan akses penuh modul & wewenang angkat admin.
- 1 Admin Modul (PIC Pengelola) dengan kuasa operasional staf.

## Production

Jalankan via SQL Tracker setelah DML-2026-187.
