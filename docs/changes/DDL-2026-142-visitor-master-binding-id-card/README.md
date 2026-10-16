# DDL-2026-142-visitor-master-binding-id-card

- **Target:** Web_HRIS (development sudah ada; production lewat SQL Tracker)
- **Artefak:** `database/ddl/DDL-2026-142-visitor-master-binding-id-card/forward.sql` (sha256 `9b6bda12029f2a53e3acfa492afefa2a7ec4e0e1ed5e48c2ad0ac53a68c823c8`), `drop.sql` (pembersihan manual, tidak dijalankan agent)
- **Objek:** `dbo.N_EMI_Master_NFC`, `dbo.N_EMI_Binding_NFC` (FK `fk_binding_master_nfc`)
- **Status:** lihat `database/ddl/status.json`

## Tujuan

Pengajuan permit kendaraan karyawan mensyaratkan ID Card (kartu NFC) yang sudah
dipasangkan ke pemohon (`N_EMI_Binding_NFC.flag_aktif='Y'`, dicocokkan lewat
`kode_karyawan`) pada kartu yang aktif dan tervalidasi sistem
(`N_EMI_Master_NFC.flag_aktif='Y'`, `flag_validasi_sistem='Y'`) dan tidak dipegang
orang lain. Logikanya di `App\Services\VISITOR\Permit\VehiclePermit\KartuNfcPemohon`.

## Riwayat

- 2026-09-28: tabel dibuat manual oleh user di development; berkas ini mencatatnya.
- Data dummy development: DML-2026-141-visitor-dummy-kartu-nfc (tidak untuk production).

## Tugas user

1. Rekam `forward.sql` ke SQL Tracker dan jalankan di production **sebelum** kode fitur
   naik; tanpa tabel ini form permit semua karyawan terkunci ("pengecekan ID Card
   belum tersedia"). Alternatif sementara: `VISITOR_PERMIT_WAJIB_KARTU_NFC=false`.
2. Isi data kartu & pairing sungguhan di production (oleh IT support).
