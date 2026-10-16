# DML-2026-195 — Registrasi Fitur Percontohan ESS.CUTI dan Pemetaan Role Kemampuan

Mendaftarkan fitur percontohan `ESS.CUTI` ke tabel master fitur bisnis baru (`N_HRIS_Fitur`, `N_HRIS_Fitur_Kemampuan`) serta memetakan peran-peran terkait ke dalam `N_HRIS_Role_Kemampuan`.

## Ringkasan Perubahan

1. Registrasi fitur `ESS.CUTI` (Pengajuan Cuti Karyawan) dengan halaman `cutiPage`.
2. Registrasi 5 kemampuan bisnis:
   - `ESS.CUTI.LIHAT`: Melihat Hak & Saldo Cuti (Aksi: VIEW, Cakupan: DIRI,TIM,UNIT,SEMUA)
   - `ESS.CUTI.AJUKAN`: Mengajukan Permohonan Cuti (Aksi: CREATE, Cakupan: DIRI,TIM)
   - `ESS.CUTI.BATALKAN`: Membatalkan Permohonan Cuti Draf (Aksi: DELETE, Cakupan: DIRI,TIM)
   - `ESS.CUTI.VERIFIKASI`: Memverifikasi Permohonan Cuti Staf (Aksi: APPROVE, Cakupan: TIM,UNIT,SEMUA, Wajib Approval)
   - `ESS.CUTI.ADMIN`: Mengelola Master & Kuota Cuti Karyawan (Aksi: EDIT, Cakupan: UNIT,SEMUA)
3. Pemetaan kemampuan ke peran:
   - `ESS_SUPER_ADMIN`: 5 kemampuan (akses penuh)
   - `ESS_ADMIN`: `ESS.CUTI.LIHAT`, `ESS.CUTI.ADMIN`
   - `ADMIN_HC_CUTI_VERIFIKATOR`: `ESS.CUTI.LIHAT`, `ESS.CUTI.VERIFIKASI`

## Eksekusi & Idempotensi

Skrip idempoten menggunakan `NOT EXISTS` dan `MERGE`, aman dijalankan berulang.
