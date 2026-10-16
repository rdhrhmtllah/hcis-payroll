# Koreksi Data Permit saat Review — Desain

Tanggal: 2026-09-23 · Branch: `feat/vms/review-permit-flexible` (dari `master` `5b2e9791`) ·
Modul: VISITOR › Workflow › Verification · Change: `DDL-2026-138-visitor-koreksi-permit`

## Masalah

Verifikator hanya bisa menyetujui atau menolak permit karyawan (HIJAU/MERAH) persis
seperti yang diajukan. Kalau pemohon salah memilih jenis permit atau salah mengisi data
kendaraan, satu-satunya jalan adalah menolak lalu menunggu pengajuan ulang — inspeksi
tertunda. Untuk permit tamu (COKLAT), verifikator sudah bisa mengisi model/warna/tahun
dan jendela waktu, tetapi permit karyawan sama sekali tidak bisa diubah.

## Keputusan user

1. Verifikator boleh **memindahkan jenis permit**, terbatas **antar kategori permit
   karyawan** (kategori aktif dengan `Flag_Muncul_Pengajuan='Y'`, mis. HIJAU ↔ MERAH).
   COKLAT tidak boleh menjadi asal maupun tujuan.
2. Field yang boleh dikoreksi: **jenis permit, plat nomor, model kendaraan (jenis
   kendaraan ikut model), warna, tahun, keterangan pengajuan**. Foto dokumen dan slot
   inspeksi tidak termasuk.
3. Approval bertingkat: koreksi di tahap mana pun **tidak mengulang approval**;
   persetujuan tahap sebelumnya tetap berlaku, dan setiap koreksi tercatat di riwayat.
4. Koreksi adalah **aksi terpisah** ("Koreksi Data") sebelum approve, bukan ikut
   payload approve.
5. Pemohon **tidak diberi notifikasi**; ia melihat data terbaru di halaman permitnya.

## Aturan

- Hanya **pemegang giliran approval** permit tersebut
  (`ApprovalRequestService::validateApproverAccess`, sama dengan simpan checklist).
- Hanya permit **PENDING** dengan `Sumber_Pengajuan='HCIS'` dan `No_Transaksi_Tamu`
  NULL. Permit tamu, API mitra, APPROVED/REJECTED/REVOKED/EXPIRED/NO_FLOW ditolak.
- **Alasan wajib**, satu per kali simpan (maks 500 karakter).
- Hanya field yang **benar-benar berubah** yang disimpan dan dicatat; permintaan tanpa
  perubahan ditolak (`VISITOR_PERMIT_KOREKSI_TANPA_PERUBAHAN`, 422).
- Validasi master sama dengan pengajuan (`VehiclePermitController::store`):
  - kategori: aktif + penyaring `terapkanKategoriPengajuan` (dipindah ke tempat yang
    bisa dipakai bersama, bukan disalin);
  - plat: tiga bagian wajib, `PlatNomorKendaraan::muat` dengan `LEBAR_PERMIT`, prefix
    ada di `N_EMI_VISITOR_Master_Plat_Kendaraan` untuk akses `PERMIT`/`ALL`;
  - model aktif (jenis kendaraan = `Id_Master_Tipe` milik model); warna aktif;
  - tahun dan keterangan dengan aturan yang sama seperti form pengajuan.
- `Id_Master_Kategori_Permit` dan `Snapshot_Kategori_Permit` **selalu diubah bersama**.

## Data (DDL-2026-138)

Tabel baru `N_EMI_VISITOR_Transaksi_Permit_Koreksi` — satu baris per field yang berubah:

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| `Id_Permit_Koreksi` | int identity | NOT NULL | PK |
| `No_Transaksi` | varchar (ikut tipe kolom header) | NOT NULL | nomor permit |
| `Kelompok_Koreksi` | uniqueidentifier | NOT NULL | mengelompokkan satu kali simpan |
| `Nama_Field` | varchar(50) | NOT NULL | `KATEGORI_PERMIT`, `PLAT_NOMOR`, `MODEL_KENDARAAN`, `WARNA`, `TAHUN`, `KETERANGAN` — divalidasi backend, tanpa CHECK |
| `Nilai_Lama` | nvarchar(500) | NULL | teks terbaca manusia (nama, bukan ID) |
| `Nilai_Baru` | nvarchar(500) | NULL | idem |
| `Alasan` | nvarchar(500) | NOT NULL | |
| `Created_At` | datetime2 | NOT NULL | |
| `Created_By` | varchar(100) | NOT NULL | `Kode_Karyawan` verifikator (sumber: user login) |

Indeks `IX_VISITOR_Permit_Koreksi_No_Transaksi (No_Transaksi) INCLUDE (Created_At)` untuk
menampilkan riwayat per permit. Tanpa FK (pola tabel VISITOR lain berelasi lewat
`No_Transaksi` tanpa FK). Tipe pasti `No_Transaksi` diverifikasi dari `sys.columns`
header sebelum DDL ditulis. Dokumentasi tabel di `docs/database/tables/visitor/` dan
catatan perubahan di `docs/changes/DDL-2026-138-visitor-koreksi-permit/`.

## Backend

**Route** (grup `auth` + `check.jabatan:visitorVerifikatorPermitPage` yang sudah ada,
`routes/VISITOR/Workflow/Verification/VerifikatorVisitorWeb.php`):

- `POST verifikator-permit/{noTransaksi}/koreksi` → simpan koreksi.
- `GET verifikator-permit/{noTransaksi}/koreksi/pratinjau-checklist?id_kategori_permit=&id_vehicle_model=`
  → `{ dipertahankan, dibuang, ditambah }` (jumlah + teks pertanyaan yang dibuang),
  tanpa menulis apa pun.

**Berkas**

- `app/Services/VISITOR/Workflow/Verification/PermitKoreksiService.php` — seluruh
  logika koreksi dan pratinjau.
- `app/Http/Requests/VISITOR/Workflow/Verification/PermitKoreksiRequest.php` — validasi
  bentuk input.
- `VerifikatorVisitorController` — dua method tipis (`koreksiPermit`,
  `pratinjauKoreksiChecklist`); `showPermit` ditambah `riwayat_koreksi` dan
  `boleh_koreksi`.
- `VisitorPermitChecklistService` — method baru `selaraskanSnapshot(noTransaksi,
  idDetail, idKategori, idTipeKendaraan)` dan `pratinjauSelaras(...)`, memakai kueri
  pertanyaan yang sama dengan `buatSnapshot` (diekstrak jadi satu sumber).

**Alur simpan (satu transaksi)**

1. Kunci header (`UPDLOCK`), muat detail + model lama. Tolak bila status bukan
   PENDING, bukan HCIS, atau bukan pemegang giliran.
2. Validasi master untuk field yang dikirim.
3. Hitung selisih terhadap data lama; kosong → tolak.
4. Update `N_EMI_VISITOR_Transaksi_Permit_Detail` untuk field yang berubah.
5. Bila kategori **atau** tipe kendaraan berubah → `selaraskanSnapshot`: baris checklist
   yang pertanyaannya masih berlaku **dipertahankan beserta `Jawaban_Admin`/
   `Catatan_Admin`**; yang tidak berlaku dihapus; pertanyaan baru di-insert kosong.
6. Bila ada booking inspeksi aktif (`JadwalInspeksiService::aktif`) dan baris
   `N_VISITOR_Permit_Efektif` ber-`Jenis_Akses='INSPEKSI'` → panggil
   `VisitorEfektifService::terbitkanAksesInspeksi` dengan jam slot booking, sehingga
   kategori/plat/kendaraan di palang ikut baru (`Flag_Sync='T'`).
7. Insert baris riwayat (satu `Kelompok_Koreksi` untuk seluruh field), update
   `Updated_At`/`Updated_By` header.

**Galat** memakai `ResponseHelper::errorWithCode` dengan kode `VISITOR_PERMIT_KOREKSI_*`
(status tidak valid 409, akses 403, validasi/tanpa perubahan 422).

## Frontend

Di `resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/`:

- `PermitKoreksiDialog.vue` — form terisi data sekarang (jenis permit, plat
  prefix/angka/suffix, model, warna, tahun, keterangan, alasan wajib). Field yang
  berubah diberi keterangan "sebelumnya: …". Saat kategori/model berubah, memanggil
  endpoint pratinjau dan menampilkan peringatan jawaban checklist yang akan terbuang.
  Tombol simpan nonaktif sampai ada perubahan dan alasan terisi.
- `PermitKoreksiRiwayat.vue` — daftar koreksi per kelompok: waktu, nama verifikator,
  alasan, field lama → baru. Tampil hanya bila ada riwayat.
- `PermitReviewModal.vue` — hanya menambah tombol "Koreksi Data" (bila
  `boleh_koreksi`), memasang kedua komponen, dan memuat ulang detail setelah simpan.

Komponen memakai `@/components/ui` (HcisDialog, HcisField, HcisSelect, HcisButton,
HcisAlert) seperti modal yang ada.

## Pengujian

- Integration `tests/Integration/VISITOR/Workflow/Verification/PermitKoreksiTest.php`:
  sukses (detail berubah, riwayat per field, header ter-update); penolakan (bukan
  PENDING, tamu/API, bukan pemegang giliran, alasan kosong, tanpa perubahan, COKLAT,
  kategori nonaktif, prefix plat tidak valid); checklist (jawaban relevan bertahan,
  tak relevan terbuang, baru ditambah); palang (baris INSPEKSI berganti kategori/plat);
  pratinjau tidak menulis apa pun. Data uji dibuat & dibersihkan dalam transaksi uji.
- `tests/Feature/VISITOR/VisitorRouteContractTest.php` — dua route baru.
- Vitest `PermitKoreksiDialog.test.js` — alasan wajib, deteksi perubahan, peringatan
  checklist, tombol tersembunyi saat `boleh_koreksi` false.
- Verifikasi akhir: skrip `scripts/`, build, suite test proyek, lalu uji manual di dev
  server lewat tunnel.

## Di luar cakupan

Pindah ke/dari COKLAT, koreksi permit tamu/API, koreksi setelah approve, ganti foto
dokumen, pindah slot inspeksi oleh verifikator, notifikasi ke pemohon, reset approval.
