# DDL-2026-123-visitor-jadwal-inspeksi-permit

## Ringkas

Permit kendaraan karyawan (Hijau/Merah) kini punya **jadwal inspeksi fisik**:
pemohon memilih slot (tanggal, jam, pos) saat mengajukan, SHE melihat agenda
harian yang juga memuat kedatangan tamu B2B ber-permit coklat, dan permit baru
bisa disetujui setelah jam inspeksinya tiba. Sebelumnya inspeksi terputus dari
alur: tidak ada kapan, di mana, dan jam berapa.

| | |
|---|---|
| Change ID | `DDL-2026-123-visitor-jadwal-inspeksi-permit` |
| Target | `Web_HRIS` (development); production hanya lewat SQL Tracker |
| Bergantung pada | `DDL-2026-121`, `DDL-2026-122` (agenda membaca `B2B_Unit` & estimasi kunjungan) |
| Basis registry | grup `VISITOR` `Id_Grup_Fitur=4`, diverifikasi 2026-09-18 di `Web_HRIS` |
| Spesifikasi | `docs/superpowers/specs/2026-09-18-visitor-jadwal-inspeksi-permit-design.md` |
| Status | `DEV_EXECUTED` (artifact r4) |

## Keputusan user (2026-09-18)

- Karyawan **memilih slot sendiri**, **saat mengajukan**; berlaku untuk **setiap pengajuan**.
- SHE menyusun **pola mingguan + pengecualian tanggal**.
- Jadwal ulang mandiri sampai **H-1**, maksimal **2 kali**. Tidak hadir → wajib pilih
  slot baru (tidak memakan jatah jadwal ulang); **tidak hadir kedua menggugurkan** permit.
- Default agen yang diterima ("ya lanjutkan"): banyak pos inspeksi; agenda SHE
  menggabungkan kedatangan tamu B2B ber-permit coklat.

## Objek

**DDL (`forward.sql`)** — enam tabel, tanpa CHECK (daftar nilai divalidasi backend):

| Tabel | Isi |
|---|---|
| `N_VISITOR_Master_Shift_Inspeksi` (r3) | `Nama_Shift` (unik), `Jam_Mulai`/`Jam_Selesai` = **jam kerja karyawan** (`Jam_Selesai <= Jam_Mulai` berarti melewati tengah malam, mis. Shift 3 23:00–07:00), `Urutan`, `Flag_Aktif`, audit |
| `N_VISITOR_Master_Pos_Inspeksi` (r2) | `Nama_Pos` (unik, `UX_VISITOR_Master_Pos_Inspeksi_Nama`), `Keterangan_Lokasi` (ditampilkan ke pemohon), `Urutan`, `Flag_Aktif` (pos nonaktif tidak membuka slot), audit |
| `N_VISITOR_Inspeksi_Pola` | `Id_Pos_Inspeksi` (FK master), `Id_Shift_Inspeksi` (FK master shift, r3), `Hari_Minggu` 1–7 (ISO), jam mulai/selesai `time(0)`, `Durasi_Menit`, `Kuota_Per_Slot`, `Flag_Aktif`, audit |
| `N_VISITOR_Inspeksi_Pengecualian` | `Tanggal`, `Id_Pos_Inspeksi` (FK master; `NULL` = semua pos, hanya `TUTUP`), `Id_Shift_Inspeksi` (opsional, r3), `Jenis` `TUTUP`/`TAMBAH`, jam/durasi/kuota untuk `TAMBAH`, `Keterangan` |
| `N_VISITOR_Inspeksi_Hari_Shift` (r4) | Sakelar `Hari_Minggu` × `Id_Shift_Inspeksi`; `Id_Shift_Inspeksi` `NULL` = seluruh hari. **Tidak ada baris = aktif**; `Flag_Aktif='T'` mematikan. Unik `UX_VISITOR_Inspeksi_Hari_Shift` |
| `N_VISITOR_Inspeksi_Booking` | `No_Transaksi_Permit`, `Id_Pos_Inspeksi` (FK master), `Id_Shift_Inspeksi` (asal slotnya, r3), `Snapshot_Nama_Pos` (nama saat dipesan; ikut nama baru selama masih `TERJADWAL`), `Waktu_Mulai/Selesai`, `Status` `TERJADWAL`/`DIPINDAH`/`TIDAK_HADIR`/`SELESAI`/`DIBATALKAN`, `Alasan`, `Diinspeksi_Oleh/_At`, audit |

Indeks: `UX_VISITOR_Inspeksi_Booking_Aktif` (unik, tersaring `Status='TERJADWAL'`
per permit — satu jadwal aktif per permit), `IX_…_Slot` (hitung kuota),
`IX_…_Permit` (riwayat), `IX_VISITOR_Inspeksi_Pengecualian_Tanggal`.

Slot kosong **tidak disimpan**: dihitung dari pola + pengecualian dikurangi
booking `TERJADWAL` (`JadwalInspeksiService::slotRentang`). Kuota dijaga hitungan
`UPDLOCK, HOLDLOCK` di dalam transaksi pengajuan.

**DML (`data-change.sql`)**: menu `visitorAgendaInspeksiPage` (`/agenda-inspeksi`,
header Administrator Visitor, urutan 21), peta grup VISITOR `Flag_Hibah_Role='T'`,
kunci halaman **diturunkan dari pemegang aktif kunci Verifikator Permit**, pola
awal Senin–Jumat 08:00–11:00 "Pos Utama" slot 30 menit kuota 2 **hanya bila
`DB_NAME() = 'Web_HRIS'`**, pendaftaran dashboard change.

## Alur

1. **Pengajuan** (`POST /transaksipermit`): wajib `inspeksi_pos` + `inspeksi_mulai`
   (`Y-m-d H:i`). Slot diperiksa sebelum unggah berkas, lalu dipesan di dalam
   transaksi yang sama. Slot direbut orang lain → 422 `VISITOR_PERMIT_SLOT_INVALID`,
   form memuat ulang slot tanpa mengosongkan isian lain.
2. **Pemohon** melihat ringkasan jadwal di detail Riwayat Permit dan bisa
   memindah (`POST /transaksipermit/{id}/pindah-inspeksi`) selama permit belum diputus.
3. **SHE** membuka Agenda Inspeksi: agenda per tanggal, kapasitas per pos,
   "Mulai Inspeksi" membuka meja Verifikator Permit (keputusan tetap satu pintu),
   "Tidak Hadir" setelah jam slot dimulai (notifikasi ke pemohon).
4. **Approve** permit ber-booking ditolak sebelum jam slotnya; saat disetujui
   booking ditandai `SELESAI` (pelaku = approver). **Tolak/batal** melepas slot.
   Permit lama tanpa booking tidak terpengaruh.

## Revisi artifact

| Revisi | Isi |
|---|---|
| r1 | Pos berupa teks bebas `Pos_Inspeksi` di pola, pengecualian, booking (dijalankan di dev 2026-09-18). |
| r4 | Tiap hari memuat KETIGA shift, dan jadwal ada di dalam shift. Sakelar aktif/nonaktif per (hari, shift) maupun per hari penuh disimpan di `N_VISITOR_Inspeksi_Hari_Shift`. Keputusan user 2026-09-20 (dengan pratinjau layar): "tiap hari pasti ada 3 shift, di tiap shift baru ada jadwal-jadwal tertentu, dan bisa dinonaktifkan per shift per hari atau satu hari penuh". Mematikan TIDAK menghapus jadwal — dihidupkan lagi, slotnya kembali sama persis. Slot TAMBAH dari pengecualian tanggal tidak tunduk pada sakelar mingguan. |
| r3 | Shift kerja karyawan jadi master `N_VISITOR_Master_Shift_Inspeksi`; tiap jadwal milik satu shift. Keputusan user 2026-09-20: Shift 1/2/3 adalah jam kerja karyawan, dan SHE membuka inspeksi **di sela-sela** jam itu — karena itu jam jadwal TIDAK dibatasi jam shift (layar hanya menandai "di luar jam kerja shift"). Shift nonaktif menghentikan seluruh jadwalnya; booking lama dibiarkan dan jumlahnya dilaporkan. Jadwal yang menyeberang tengah malam tetap milik hari mulainya. Tiga shift disemai di SEMUA environment. |
| r2 | Pos menjadi master `N_VISITOR_Master_Pos_Inspeksi`; ketiga tabel merujuk `Id_Pos_Inspeksi` (FK), booking menyimpan `Snapshot_Nama_Pos`. Keputusan user 2026-09-19: salah ketik nama memecah kuota, ganti nama memutus booking, dan pemohon butuh keterangan lokasi. Production langsung memakai r2. |

## Penyelarasan development r1 → r2 (BUKAN artifact — jangan dijalankan di production)

Dev sudah menjalankan r1 dan berisi data (5 pola + 1 booking uji manual J31),
jadi tabel tidak dibangun ulang; nama pos teks dipetakan ke master. Skripnya
memuat `DROP INDEX` / `DROP COLUMN`, yang terlarang bagi agen (`AGENTS.md`); user
mengizinkannya secara eksplisit untuk penyelarasan dev ini (2026-09-19). Isi
ketiga tabel dicadangkan ke JSON lokal lebih dulu. Urutan:

1. Skrip penyelarasan di `Web_HRIS`: buat master, isi dari nama
   pos yang terpakai, tambah & isi `Id_Pos_Inspeksi`, buang kolom teks lama di pola
   dan pengecualian, ganti nama `Booking.Pos_Inspeksi` → `Snapshot_Nama_Pos`, buang
   dua indeks lama yang memuat kolom teks.
2. Jalankan ulang `forward.sql` r2 + `data-change.sql` r2: memasang indeks
   baru dan tiga FK.
3. Jalankan ulang sekali lagi → tidak ada perubahan (bukti dev = artifact).

Selisih yang disadari: kolom `Id_Pos_Inspeksi` hasil penyelarasan berada di
urutan terakhir tabel, bukan di posisi `CREATE TABLE` r2 — kosmetik, tidak
memengaruhi kueri.

Hasil 2026-09-19: 1 pos ("Pos Utama", Id 1), 5 pola dan 1 booking (PMT0926-0100,
J31) terpetakan tanpa kehilangan baris; 3 FK + indeks terpasang; sidik skema
run r2 ke-1 dan ke-2 identik (no-op); kolom teks `Pos_Inspeksi` tidak tersisa.

### Penyelarasan development r2 → r3

Tanpa DROP: buat master shift, semai tiga shift, tambah `Id_Shift_Inspeksi` di
pola/pengecualian/booking, isi pola lama (08:00–11:00) dengan Shift 1, lalu
`ALTER COLUMN ... NOT NULL` pada pola. Hasil 2026-09-20: 3 shift, 0 pola tanpa
shift, 3 FK shift terpasang, rerun r3 no-op.

### Penyelarasan development r3 → r4

Tanpa DROP: cukup tabel `N_VISITOR_Inspeksi_Hari_Shift` + indeks unik + FK dari
`forward.sql` r4. Tidak ada baris sakelar yang perlu disemai, karena tanpa baris
berarti aktif. Hasil 2026-09-20: tabel terbentuk, rerun r4 no-op.

## Kode

`JadwalInspeksiService`, `AgendaInspeksiController`
(`routes/VISITOR/Permit/Inspection/AgendaInspeksi.php`), `VehiclePermitController`
(store/show/cancel, `slotInspeksi`, `pindahInspeksi`), `VisitorApprovalDecisionService`
(penjaga approve, pelepasan saat tolak), `AgendaInspeksiPage.vue`,
`SlotInspeksiPicker.vue`, `PermitForm.vue`, `PermitStatusList.vue`.

## Riwayat eksekusi development

| Tanggal | Artifact | Hasil |
|---|---|---|
| 2026-09-18 | `forward.sql` | OK — tiga tabel + empat indeks terbentuk. |
| 2026-09-18 | `data-change.sql` | OK — menu + peta grup, 3 baris kunci halaman (turunan pemegang Verifikator Permit), 5 baris pola dev. |

## Pekerjaan user

- Rekam di SQL Tracker **setelah** DDL-2026-121 dan DDL-2026-122; urutan `forward.sql` lalu `data-change.sql`.
- Production: SHE **wajib** menyusun pola lewat Pengaturan Slot sebelum rilis —
  tanpa pola, pengajuan permit karyawan tidak bisa dikirim (tidak ada slot).
- Pengingat H-1 ke pemohon belum dibuat (di luar lingkup).
