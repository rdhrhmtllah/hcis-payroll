# Audit VMS menyeluruh (27 Sep 2026)

Lanjutan `plans/visitor/2026-09-26-vms-celah-alur-sisa.md`. Empat audit baca-saja paralel atas
kode branch `fix/vms/celah-alur-lanjutan` (memuat PR #464 & #466) dan data dev `Web_HRIS`:
integritas data, hak akses/keamanan, laporan & kontrak API, siklus hidup & sinkron gerbang.
Angka = dev per 27 Sep 2026; verifikasi ulang sebelum mengerjakan. Butir bertanda ✅
sudah diverifikasi langsung oleh sesi utama, sisanya hasil agen audit.

## HIGH

### H1 ✅ Cetak kartu massal membocorkan kartu permit orang lain (IDOR)
`POST transaksipermit-kartu-pdf-batch` (`routes/VISITOR/Permit/VehiclePermit/VehiclePermit.php:22`,
grup `visitorPermitPage` = menu global semua karyawan) → `VehiclePermitController::kartuPdfBatch`
memakai `basePermitQuery()->whereIn('h.Id_Transaksi_Permit', ids)` tanpa filter pemilik. Karyawan
mana pun bisa mengunduh PDF kartu permit aktif orang lain, berikut QR `Kode_Kartu` asli yang lolos
verifikasi sebagai "KARTU SAH"; efek samping, `Kode_Kartu` baru ditulis ke DB.
**Arah:** pindahkan ke pintu admin saja (`LaporanVisitorWeb.php:66`) atau saring
`Kode_Karyawan_Pemohon` + `whereNull('No_Transaksi_Tamu')`.

### H2 ✅ Penjadwal tidak berjalan di host dev
`visitor:expire-efektif` terdaftar (`N_HRIS_Scheduler_Configs` id 17, `*/5`, `Kernel.php`), tetapi
tak ada crontab/systemd timer/proses `schedule:run`. Sapuan terakhir 22 Sep 13:56 (manual).
Akibat: 20 `N_VISITOR_Tamu_Efektif` AKTIF lewat jendela, PMT0926-0200 AKTIF di gerbang padahal
habis 25 Sep; butir #2 (tutup COKLAT berlalu) menumpuk jadi 6; `KELUAR_TAK_TERCATAT` tak muncul
karena bergantung `EXPIRED`. **Arah:** pasang pemicu `schedule:run` di dev; **pastikan production
punya pemicunya**; tambah alarm "sapuan terakhir > 15 menit" dan `onFailure`.

### H3 ✅ "Di dalam area" masih menghitung tamu KELUAR_TAK_TERCATAT
`VisitorKehadiranQueryService::diDalamSql()` = masuk ∧ belum keluar, tanpa `Status_Efektif`;
dipakai dashboard operasional, Papan Harian (`sedang_di_dalam`, `menginap`), Papan Tamu.
Monitoring Tamu (siklus) bilang "Keluar Tak Tercatat", dashboard bilang "di dalam".
Diperkenalkan oleh PR #466 — **perbaiki bersama PR itu**: `diDalamSql` mensyaratkan
`Status_Efektif <> 'EXPIRED'`.

### H4 Status permit COKLAT dihitung dua cara berbeda, keduanya bukan `permitStatusSql`
Cara A (`VisitorPapanTamuService:104`, `LaporanVisitorController:530`, `VisitorGuestDataset:46`,
`JadwalKedatanganTamuController:325`) vs cara B (`VisitorPermitQueryService:384`,
`GuestRegistrationController:915`, `JadwalKedatanganTamuController:405`). Dev: 1 EXPIRED tampil
AKTIF, 2 ditolak tampil KEDALUWARSA vs "ditolak" di layar lain. **Arah:** satu konstanta SQL
status COKLAT di `VisitorPermitQueryService`, urutan cabang sama dengan `permitStatusSql`.

### H5 API mitra: status permit berbeda antar endpoint
`/permit-tamu/{no}` memakai `permitStatusSql`; `/tamu-b2b/{no}` (`TamuB2BApiController:488`,
`:353`) memakai kolom mentah → satu permit bisa EXPIRED di satu endpoint dan APPROVED di lainnya.
**Arah:** `permitKunjungan()` membaca `permitStatusSql`.

## MEDIUM

- **M1** Detail permit (`VehiclePermitController::show:455`) tanpa filter pemilik bila akun tak
  punya `Kode_Karyawan` (8 user dev tanpa Karyawan; lolos `check.jabatan`? UNVERIFIED). → 403.
- **M2** API mitra bisa membaca kunjungan/permit mitra lain dengan nomor berurutan
  (`TamuB2BApiController::status`, `PermitTamuApiController::status`). Disengaja & terdokumentasi;
  minimal sembunyikan `alasan` untuk permit karyawan.
- **M3** Tak ada larangan menyetujui pengajuan sendiri (`ApprovalRequestService::validateApproverAccess`).
- **M4** Revoke tunggal & bulk-`items` tak menyaring `Jenis_Akses='PERMIT'`
  (`VisitorEfektifService:484-556`): baris INSPEKSI pengajuan pending bisa dicabut → setelah approve
  `syncPermitEfektif` menolak menimpa DIBATALKAN, permit tak pernah sampai gerbang. Juga
  `terbitkanAksesInspeksi` meng-update tanpa penjaga `Status_Efektif <> 'DIBATALKAN'`.
- **M5** Cabut kunjungan tidak atomik dengan pembatalan COKLAT-nya
  (`VerifikatorVisitorController:1058-1068`); retry 404 → COKLAT tetap hidup.
- **M6** `pindahOlehPetugas` tidak menolak permit COKLAT (`JadwalInspeksiService:410`) → booking
  TERJADWAL & baris INSPEKSI "KARYAWAN" untuk permit tamu, kuota slot tertahan.
- **M7** Kartu/filter "Sudah kedaluwarsa" Monitoring Permit selalu 0
  (`LaporanVisitorController:695,823` menyaring `IN ('APPROVED','REVOKED')`).
- **M8** Papan Harian memuat permit yang sudah dicabut (`VisitorPapanHarianService:262`).
- **M9** Tamu tidak hadir & COKLAT tutup otomatis tak terbaca di laporan/API: `alasan` null
  (tersimpan di `HRIS_Approval_Request.Catatan`), tren "ditolak" membengkak di hari penyapu jalan.
- **M10** Laporan Harian & urutan kolom status Laporan Tamu memakai status mentah
  (`LaporanVisitorController:444,107`); "Tidak Datang" Export Studio pakai `Tanggal_Kunjungan`
  (`VisitorGuestDataset:164`); grafik permit per kategori presisi hari (`VisitorDashboardAnalitikService:199`).
- **M11** Data: PMT0726-0002 layar VALIDITY_REQUIRED tapi gerbang AKTIF; PMT0926-0085 proyeksi vs
  detail beda tanggal (kunjungan mitra bertahun 2025); VSR0926-0092/0163 PENDING_APPROVAL selamanya
  (siklus tak punya cabang kedaluwarsa approval). Butuh keputusan user / DML.

## LOW

- PII (`no_identitas`, `no_hp`) masuk `N_HRIS_Api_Log.Request_Ringkas` (`HrisApiGuard` ~314).
- Rate limit API per IP, bukan per aplikasi (`RouteServiceProvider:27`).
- Ekstensi berkas unggahan dari nama file klien (`VehiclePermitController::buildDocumentFileName`,
  `VerifikatorVisitorController::simpanDokumenPermit`, `FotoKendaraanTamuService:28`) → `guessExtension()`.
- OpenAPI menyebut `DELETE /permit-tamu/{no}`, rute sebenarnya `POST …/batal`.
- Auto-gugur tak memanggil `akhiriAksesInspeksi`; pindah jadwal pemohon memeriksa header tanpa kunci.
- API permit mitra: replay selalu `PENDING`; dua kiriman bersamaan → 500; tanpa kunci retry menggandakan.
- Penyapu tanpa try/catch per cabang; `FormatTanggalHelper::getCurrentTime()` membaca tanggal dummy KPI.
- Zona waktu bercampur (`AT TIME ZONE` / `SYSDATETIME` / `GETDATE` / PHP `now()`); aman selama DB +07:00.
- Peta label status tamu diduplikasi 5 tempat; `StatusKunjunganTamu::LABEL` tak dipakai; dua tes
  (`AllMonitoring.test.js:253`, `TamuHistory.test.js:243`) basi harapannya, bukan kodenya.
- Data yatim: 6 approval tanpa transaksi, 4 notifikasi ke transaksi hilang, 2 COKLAT menunjuk
  kunjungan tak ada, `VSR-CONTOH-MITRA` data contoh; 15 proyeksi permit `Flag_Sync='T'` sejak 14 Sep.
- Kode mati berbahaya bila diberi rute lagi: `GuestRegistrationController::update/destroy/checkIn/checkOut`.

## Sudah bersih (ringkas)

Semua rute VISITOR ber-`auth`+`check.jabatan` (kecuali redirect lama); CSRF aktif; approve/reject/
koreksi mengunci header & cek giliran; COKLAT tak bisa diajukan karyawan; matriks B2B dicek server;
API mitra HMAC+nonce+whitelist; tak ada raw SQL dari input request; notifikasi sesudah commit;
penyapu idempoten & tak menimpa DIBATALKAN; 0 booking ganda/yatim; 0 nomor/kode kartu ganda;
0 permit disetujui tanpa proyeksi; 0 proyeksi AKTIF untuk permit ditolak.

## Status per 28 Sep 2026

- **Selesai:** H1 (#467), H3 (#466), H4 · H5 (#471), M4 · M7 · M8 (#472), M3 (#473);
  M1 · M2 · M5 · M6 · M9 · M10 dan Low berikut (branch `fix/vms/audit-sisa`): PII log API,
  ekstensi unggahan dari isi berkas, OpenAPI `…/batal`, auto-gugur mengakhiri akses inspeksi,
  kunci header saat pemohon memindah jadwal, replay API permit mitra (status terkini, kiriman
  bersamaan jadi replay), isolasi cabang penyapu, label status tersisa, dua tes label basi.
- **Dokumentasi saja:** H2 → `docs/workflows/penjadwal-hcis.md` (#468); pemicu dev menunggu keputusan user.
- **Ditunda (butuh keputusan/lebih besar):** M11 dan data yatim (butuh DML), rate limit per
  aplikasi, zona waktu, `FormatTanggalHelper::getCurrentTime()`, peta label ganda,
  kode mati `GuestRegistrationController`, 15 proyeksi `Flag_Sync='T'`.

## Audit ulang 28 Sep 2026 (kode & sistem; data dev sengaja dibiarkan)

Tiga audit baca-saja (backend tulis, laporan/API, frontend). Diperbaiki di branch
`fix/vms/data-bersih`, masing-masing bertes (dibuktikan gagal di kode lama):

- Penyapu menutup kunjungan ber-approval kedaluwarsa (REJECTED + catatan, pemohon dikabari).
- Batas approval permit digeser melewati slot inspeksi; approval kedaluwarsa → 409, bukan 500.
- Penyapu mengakhiri akses palang INSPEKSI saat membatalkan booking permit yang mati.
- Proyeksi gerbang menulis ulang penyetuju saat baris INSPEKSI menjadi PERMIT.
- API mitra: `Str` tak diimpor (500 tanpa kunci idempotensi); kuota dari status kanonis;
  kiriman bersamaan tamu B2B → replay; kunjungan berakhir → `EXPIRED`; OpenAPI diselaraskan.
- Papan/antrean/analitik bersama: ranah dijaga kunci halaman (kebocoran data tamu ke
  pemegang kunci permit), matriks kategori, grafik per tipe tamu, kueri papan dibatasi.
- Monitoring Permit: kartu = filter (jendela jam, tanpa yang dicabut); laporan harian historis.
- `boleh_revoke` memakai kunci meja permit; `{id}` numerik di rute; kode kartu tidak saling timpa.
- Frontend: modal tamu dari meja permit, tombol revoke/detail permit dijaga, kamera pemindai,
  seleksi setelah revoke, label status bukti transaksi, timer debounce.
- Tidak dikerjakan: tombol batal permit masih tampil saat server akan menolak (pesan 409 jelas).
