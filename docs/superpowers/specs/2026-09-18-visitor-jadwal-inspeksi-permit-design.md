# Jadwal Inspeksi Permit Hijau/Merah — Desain

Tanggal: 2026-09-18 · Branch: `feat/vms/inspeksi-permit-slot` (dari `feat/vms/tamu-b2b-matriks`) ·
Modul: VISITOR › Permit › VehiclePermit (+ Workflow Verification) · Change: `DDL-2026-123-visitor-jadwal-inspeksi-permit`

## Masalah

Permit COKLAT punya titik temu inspeksi (estimasi kedatangan tamu). Permit karyawan
(HIJAU/MERAH) tidak: tidak ada tanggal, jam, dan pos inspeksi, sehingga SHE tidak
tahu kapan kendaraan datang dan permit bisa disetujui dari meja tanpa inspeksi fisik.

## Keputusan user

1. Karyawan **memilih slot** inspeksi sendiri, **saat mengajukan** permit.
2. SHE menyediakan slot lewat **pola mingguan + pengecualian tanggal**.
3. Jadwal ulang mandiri **sampai H-1, maks 2 kali**; tidak hadir → wajib pilih slot
   baru; **2x tidak hadir → pengajuan gugur**.
4. Inspeksi wajib **setiap pengajuan** (baru maupun perpanjangan).
5. Default agen (disetujui "lanjutkan"): pos bisa lebih dari satu (per pola);
   Agenda Inspeksi SHE menggabungkan HIJAU/MERAH dan kedatangan COKLAT.

## Data (DDL-2026-123)

- `N_VISITOR_Inspeksi_Pola` — `Pos_Inspeksi`, `Hari_Minggu` (1=Senin…7=Minggu),
  `Jam_Mulai`, `Jam_Selesai`, `Durasi_Menit`, `Kuota_Per_Slot`, `Flag_Aktif`, audit.
- `N_VISITOR_Inspeksi_Pengecualian` — `Tanggal`, `Pos_Inspeksi` (NULL = semua pos),
  `Jenis` (`TUTUP`/`TAMBAH`), jam/durasi/kuota untuk `TAMBAH`, `Keterangan`, audit.
- `N_VISITOR_Inspeksi_Booking` — `No_Transaksi_Permit`, `Pos_Inspeksi`,
  `Waktu_Mulai`, `Waktu_Selesai`, `Status` (`TERJADWAL`/`DIPINDAH`/`TIDAK_HADIR`/
  `SELESAI`/`DIBATALKAN`), `Alasan`, `Diinspeksi_Oleh`/`_At`, audit. Satu baris per
  booking; jadwal ulang = baris lama `DIPINDAH` + baris baru. Hitungan jadwal ulang
  dan tidak hadir dibaca dari jumlah baris berstatus itu. Indeks unik terfilter:
  satu booking `TERJADWAL` per permit.
- Slot kosong **tidak disimpan**: dihitung dari pola + pengecualian − booking
  `TERJADWAL`. Kuota dijaga dengan hitungan ber-`UPDLOCK, HOLDLOCK` di dalam
  transaksi booking.
- Nilai status/jenis divalidasi backend (tanpa CHECK, sesuai AGENTS.md).

## Alur karyawan

Form permit mendapat langkah **Jadwal Inspeksi**: kalender 14 hari ke depan berisi
slot yang masih berkuota (per pos). Slot dipesan di transaksi yang sama dengan
pengajuan. Riwayat permit menampilkan jadwalnya, tombol **Pindah Jadwal**
(sampai H-1, maks 2x; setelah tidak hadir selalu diizinkan), dan status inspeksi.

## Alur SHE

Page **Agenda Inspeksi** (`/agenda-inspeksi`,
`visitorAgendaInspeksiPage`, kunci diturunkan dari pemegang Verifikator Permit):

- Agenda per hari: booking HIJAU/MERAH per slot + kedatangan tamu COKLAT (estimasi).
- **Mulai Inspeksi** membuka meja review permit yang sudah ada; **Tidak Hadir**
  mencatat ketidakhadiran (hanya setelah jam slot dimulai).
- Tab **Pengaturan Slot**: kelola pola mingguan dan pengecualian tanggal.

## Aturan server

- Pengajuan permit karyawan wajib memilih slot yang sah & berkuota.
- Approve permit ber-booking hanya bila jam slot `TERJADWAL` sudah tiba (atau sudah
  `SELESAI`); approve menandai booking `SELESAI` + pemeriksanya. Permit lama tanpa
  booking tidak terkena penjaga ini.
- Reject / pembatalan pemohon → booking `DIBATALKAN` (slot dilepas).
- Tidak hadir ke-2 → permit gugur (Flag_Approval `T`, langkah approval ditutup
  dengan catatan) + notifikasi ke pemohon.

## Di luar cakupan

Pengingat H-1 otomatis (butuh scheduler; menyusul), inspeksi untuk permit API mitra.
