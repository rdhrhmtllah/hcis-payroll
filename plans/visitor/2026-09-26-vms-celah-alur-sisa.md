# Plan: Celah alur VMS yang tersisa (audit 26 Sep 2026)

Hasil penelusuran ujung-ke-ujung alur Visitor (data dev `Web_HRIS` + kode + penjadwal)
pada 26 Sep 2026. Tiap butir berdiri sendiri — boleh dikerjakan terpisah, oleh agent
mana pun, dalam urutan apa pun kecuali yang disebutkan saling bergantung.

Semua angka di bawah adalah hitungan **dev per 26 Sep 2026**; verifikasi ulang sebelum
mengerjakan, jangan dipercaya sebagai angka hari ini.

## Yang SUDAH rapat (jangan diaudit ulang tanpa sebab baru)

Diperiksa 26 Sep 2026 dan hasilnya nol temuan:

- booking `TERJADWAL` di atas permit yang sudah diputus / tidak aktif — **0**;
- slot yang jamnya lewat tanpa dicatat siapa pun — **0**;
- kunjungan tamu disetujui tanpa baris proyeksi gerbang — **0**;
- permit maupun kunjungan menunggu **tanpa** alur approval (mustahil diputus) — **0**;
- permit coklat hidup di atas kunjungan yang sudah mati/ditolak — **0**;
- `visitor:expire-efektif` benar terjadwal tiap 5 menit;
- `Kode_Kartu` adalah **kode QR acak 128-bit per permit**, bukan nomor kartu fisik
  (`KartuPermitQr`), jadi kode yang tertinggal pada permit mati BUKAN masalah
  pemakaian ulang kartu — sempat terlihat seperti temuan, ternyata bukan.

---

## 1. Kedatangan tamu B2B yang lewat tanpa hasil tidak pernah ditagih

> **SELESAI 2026-09-26** (branch `fix/vms/agenda-tamu-b2b-tertagih`). Panel "Lewat Jadwal &
> Belum Dicatat" dikembalikan — ia terhapus tak sengaja di `ed5876e2` (22 Sep), sehingga
> booking permit karyawan yang terlewat pun ikut tak terlihat. Kini memuat kedatangan B2B
> yang **permitnya masih menunggu**; yang disetujui tidak ditagih karena approve sudah
> menandai SELESAI (9 baris disetujui di dev = sisa sebelum aturan itu). Tombol tamu hanya
> aktif bila `VISITOR_INSPEKSI_POS_TAMU_B2B` disetel. Penjaga di `InspeksiKedatanganTamuB2bTest`.

**Bukti.** 11 kedatangan B2B yang jendela kunjungannya sudah lewat tanpa satu pun baris
di `N_VISITOR_Inspeksi_Booking` (9 permitnya sudah disetujui — sisa sejarah sebelum
penutupan otomatis ada; 2 permitnya masih menunggu sampai sekarang).

**Sebabnya.** `JadwalInspeksiService::belumDicatat()` hanya memuat booking berstatus
`TERJADWAL` milik permit karyawan. Tamu B2B tidak pernah punya baris `TERJADWAL`
(lihat `docs/architecture/aturan-inspeksi-dan-approval-permit-visitor.md`), sehingga
panel "Belum Dicatat" di Agenda Inspeksi buta total terhadap baris tamu.

**Kerjanya.** Perluas `belumDicatat()` — atau tambahkan sumber kedua di sebelahnya —
agar memuat kedatangan B2B yang `estimasiKeluarSql() < now()` dan belum punya baris
hasil, lalu tampilkan di panel yang sama dengan penanda jenisnya. Hati-hati: panel itu
dipakai untuk menagih pekerjaan, jadi baris tamu yang permitnya sudah DITOLAK tidak
boleh ikut (tak ada yang perlu dikerjakan).

**Penjaga wajib.** Uji yang membuktikan baris tamu lewat-tanpa-hasil muncul di daftar,
dan hilang begitu hasilnya dicatat; plus uji bahwa permit tamu yang ditolak tidak ikut.

## 2. Permit coklat menggantung di atas kunjungan yang sudah berlalu

> **SELESAI 2026-09-26 — opsi (a)** (branch `fix/vms/celah-alur-lanjutan`).
> `PermitCoklatTamuService::tutupKunjunganBerlalu()` dipanggil `visitor:expire-efektif`:
> permit COKLAT menunggu yang estimasi keluar kunjungannya lewat ditolak dengan catatan
> `ALASAN_KUNJUNGAN_BERLALU`. Kunjungan diperpanjang & permit yang sudah diputus tak disentuh.

**Bukti.** 2 permit coklat masih `Flag_Approval IS NULL` untuk kunjungan yang tanggalnya
sudah lewat. Tamunya tidak akan datang lagi, tetapi pengajuannya hidup terus.

**Keputusan yang dibutuhkan user (tanyakan dulu).** Saat kunjungan berakhir tanpa
keputusan permit: (a) permitnya ditutup otomatis dengan sebab ("kunjungan sudah
berlalu"), (b) dibiarkan tetapi ditagih di layar (lihat butir 1), atau (c) dibiarkan
apa adanya. Butir ini berpasangan dengan butir 1 — kalau (a) dipilih, butir 1 tetap
perlu untuk jendela waktu sebelum penutupan berjalan.

**Hati-hati.** Penutupan otomatis menyentuh `Flag_Approval`; jangan memakai jalur
`gugurkanPermit()` milik permit karyawan (kosakata "gugur karena tidak hadir" salah di
sini), dan jangan sampai membatalkan izin tamu yang kunjungannya justru diperpanjang.

## 3. 18 permit berbunyi "Kedaluwarsa" tetapi `Flag_Approval` NULL selamanya

> **DIPUTUSKAN 2026-09-26 — opsi biarkan.** Status tetap hidup sebagai ekspresi
> `permitStatusSql()`; tidak ada DML.

**Bukti.** 18 permit aktif yang seluruh baris approval-nya kedaluwarsa
(`Expired_At < now()`, `Flag_Approval IS NULL`). Menurut `permitStatusSql()` semuanya
`EXPIRED`.

**Yang SUDAH benar.** Layar pemohon menampilkan "Kedaluwarsa", kuota sudah bebas
(`STATUS_MEMBEBASKAN_KUOTA` memuat `EXPIRED`), dan meja verifikator sudah
mengecualikannya dari antrean (`$antreanKedaluwarsa` di `VerifikatorVisitorController`).

**Yang bolong.** Kolom mentahnya berbohong: ekspor, laporan BI, dan integrasi mitra yang
membaca `Flag_Approval` menghitung 18 baris itu sebagai "masih menunggu".

**Keputusan yang dibutuhkan user.** Biarkan (status hanya hidup sebagai ekspresi) atau
tulis keadaan finalnya ke kolom. Kalau ditulis, ia perubahan DATA — wajib lewat DML
bernomor beserta hitungan baris terdampak, dan `permitStatusSql()` tidak boleh ikut
berubah artinya.

## 4. Ketidakhadiran tamu B2B tidak mengabari siapa pun

> **SELESAI 2026-09-26** (branch yang sama). Notifikasi `TAMU_TIDAK_HADIR` ke PIC kunjungan
> (domain `TAMU_B2B`); kunjungan mitra tanpa PIC karyawan dilewati diam-diam oleh
> `pemohonDapatDikabari`. Belum ditambahkan ke respons `GET /tamu-b2b/{no_transaksi}`.

`AgendaInspeksiController::catatKedatanganTamu()` (status `TIDAK_HADIR`) tidak mengirim
notifikasi apa pun, sedangkan jalur ketidakhadiran permit karyawan
(`AgendaInspeksiController::tidakHadir()`) mengirim `PublishVisitorNotificationsJob`
ke pemohonnya.

**Kerjanya.** Kirim notifikasi ke PIC kunjungan (dan, untuk kunjungan mitra, catat agar
terbaca lewat `GET /tamu-b2b/{no_transaksi}`). Ikuti pola job yang sudah ada: dispatch
SESUDAH transaksi, dibungkus try/catch, kegagalan notifikasi tidak boleh menggagalkan
pencatatan. Penanda `source='MAIN_NOTIF'` wajib — lihat memori notifikasi modul baru.

## 5. Tamu check-in tanpa check-out tidak pernah ditutup

> **SELESAI 2026-09-26 — opsi laporan membedakan** (branch yang sama). Status tampil baru
> `KELUAR_TAK_TERCATAT` bila masuk tercatat, keluar kosong, dan proyeksi gerbang `EXPIRED`.
> Jam keluar TIDAK diisi karangan.

**Bukti.** `VSR0926-0086` — `Waktu_Aktual_Masuk` 17 Sep 16:06, `Waktu_Aktual_Keluar`
NULL, `Status_Efektif` sudah `EXPIRED`. Durasi kunjungan dan okupansi di laporan
menggantung selamanya.

**Keputusan yang dibutuhkan user.** Apakah kedaluwarsa otomatis sekalian menutup jam
keluarnya (dan dengan jam apa — estimasi keluar? jam kedaluwarsa?), atau laporan yang
harus membedakan "keluar tak tercatat" dari "masih di dalam". Jangan menebak: menulis
jam keluar palsu merusak laporan durasi yang justru hendak diperbaiki.

## 6. Beban kerja B2B tidak terlihat di kartu Okupansi pos

> **SELESAI 2026-09-26** (branch yang sama). Kartu pos menampilkan "+N kedatangan tamu B2B —
> di luar kuota" (bingkai putus-putus); pos tamu tanpa slot tetap diberi kartu.

Kartu "Okupansi & Kapasitas Pos" hanya menghitung slot. Karena kedatangan tamu B2B
sengaja **tidak memakan kuota** (keputusan user 25 Sep 2026), pekerjaan nyata tim SHE di
pos gerbang tidak muncul angkanya sama sekali.

**Kerjanya (kosmetik, prioritas rendah).** Tambahkan hitungan terpisah — misalnya
"+N kedatangan tamu" di kartu pos yang disetel `pos_tamu_b2b` — dengan gaya yang jelas
BUKAN bagian dari kuota, supaya keputusan "tidak memakan kuota" tidak tampak dibatalkan.

## 7. Satu anomali data: permit APPROVED tanpa proyeksi gerbang

> **SELESAI 2026-09-26 — opsi nonaktifkan** lewat `DML-2026-139-visitor-nonaktifkan-permit-tanpa-proyeksi`
> (dijalankan di dev). Jalurnya sudah tertutup: permit ini disetujui sebelum `ec786d63`
> (14 Sep) mewajibkan inspeksi & jendela sebelum approve.

`PMT0926-0012` — `Flag_Approval='Y'`, `Approved_At` 14 Sep 2026, tetapi tidak punya baris
`N_VISITOR_Permit_Efektif`. Kartunya tidak akan terbaca di palang.

**Kerjanya.** Telusuri jalur mana yang menyetujuinya tanpa `syncPermitEfektif()` (legacy?
impor?), perbaiki barisnya, dan bila jalurnya masih hidup, tutup dengan penjaga. Satu
baris — periksa dulu apakah masih ada di dev saat dikerjakan.

---

## Cara memverifikasi ulang angkanya

Semua angka di atas berasal dari kueri baca-saja terhadap `N_EMI_VISITOR_Transaksi_Permit`,
`N_EMI_VISITOR_Transaksi_Tamu(_Detail)`, `N_VISITOR_Inspeksi_Booking`,
`N_VISITOR_Permit_Efektif`, `N_VISITOR_Tamu_Efektif`, dan `HRIS_Approval_Request`.
Bentuk kuerinya: pasangan "baris X tanpa pasangan Y" (`whereNotExists`) dan
"status layar vs kolom mentah". Tidak ada satu pun yang butuh tulis.

## Rujukan

- Aturan yang sudah ditetapkan: `docs/architecture/aturan-inspeksi-dan-approval-permit-visitor.md`
- Penjaga terkait: `tests/Integration/VISITOR/Permit/Inspection/InspeksiKedatanganTamuB2bTest.php`,
  `RebutanSlotInspeksiTest.php`, `tests/Integration/VISITOR/Workflow/Approval/InspeksiPermitCoklatTest.php`
