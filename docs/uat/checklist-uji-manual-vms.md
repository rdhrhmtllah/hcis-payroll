# Checklist uji manual VMS (Visitor Management System)

Disusun 29 Sep 2026 dari rute, service, dan audit `plans/visitor/2026-09-27-audit-vms-menyeluruh.md`.
Tanda **[R]** = area yang baru diperbaiki 27–28 Sep (prioritaskan, rawan regresi).
Permit karyawan HIJAU & MERAH sudah punya skenario rinci di `IT_BA_UAT VMS-PERMIT.docx`;
di sini cukup yang belum tercakup di sana.

## 0. Persiapan

- [ ] Siapkan akun per peran: pemohon karyawan, PIC tamu reguler, pengaju B2B (Procurement **dan** Logistic), verifikator permit (ADMIN_PERMIT), tim SHE (agenda inspeksi), admin master data, pemantau (monitoring/export), dan satu akun **tanpa** akses VMS.
- [ ] Minimal dua akun verifikator (untuk uji "tidak boleh putus pengajuan sendiri" dan konflik koreksi).
- [ ] Mode WhatsApp `dummy`/`allowlist` supaya notifikasi tidak terkirim ke orang sungguhan.
- [ ] Setelan `services.visitor.permit.pos_tamu_b2b` terisi (dev: Pos Security, Id 238).
- [ ] Postman/klien HTTP berkredensial mitra (HMAC) untuk bagian API.

## 1. Akses & menu

- [ ] Tiap halaman VMS hanya muncul di sidebar untuk peran pemiliknya.
- [ ] Akun tanpa akses membuka URL langsung (`/transaksipermit`, `/verifikator-visitor/permit`, `/agenda-inspeksi`, `/monitoring-permit`, `/admin/visitor/master-data`, `/visitor/export-studio`) → ditolak, bukan 500.
- [ ] Pemegang akses tamu REGULAR tidak bisa membuka `/transaksi-tamu-b2b`, `/verifikator-visitor/b2b`, `/monitoring-b2b` (dan sebaliknya).
- [ ] Pemegang kunci halaman permit tidak melihat data tamu di papan/antrean/analitik bersama. **[R]**
- [ ] `/approvalvisitor` lama redirect ke `/verifikator-visitor`; tombol aksi lama tidak lagi mengubah data.
- [ ] Karyawan non-Procurement/Logistic membuka form B2B → pesan "belum terdaftar sebagai departemen pengaju tamu B2B".

## 2. Master data (`/admin/visitor/master-data/permit` & `/tamu`)

- [ ] Tambah, ubah, nonaktifkan, aktifkan lagi: kategori permit, durasi, model/tipe/warna kendaraan, kode plat, keperluan.
- [ ] Item nonaktif hilang dari form pengajuan, tetapi transaksi lama yang memakainya tetap tampil benar.
- [ ] Import: pratinjau file benar, file format salah, baris tidak valid/duplikat → ditandai, lalu jalankan → data masuk sesuai pratinjau.
- [ ] Export: pratinjau dan unduh; isi berkas sama dengan layar.

## 3. Permit karyawan (`/transaksipermit`) — pelengkap dokumen UAT

- [ ] Plat: kode wilayah tak dikenal ditolak; bagian plat terlalu panjang ditolak dengan pesan jelas.
- [ ] Unggah dokumen: format/ukuran salah ditolak; berkas berekstensi palsu (mis. .exe diganti .jpg) ditolak. **[R]**
- [ ] Klik "Ajukan" dua kali cepat → hanya satu transaksi.
- [ ] Kuota: masih ada pengajuan menunggu → ditolak; jatah penuh → ditolak; jatah kembali setelah permit ditolak/dicabut/kedaluwarsa.
- [ ] Slot inspeksi: slot penuh/lewat tidak bisa dipilih; hanya 14 hari ke depan; hari yang ditutup SHE tidak muncul.
- [ ] Pindah jadwal inspeksi: alasan wajib (maks 250), paling lambat H-1, maksimal 2 kali.
- [ ] Batalkan pengajuan yang masih menunggu; pengajuan yang sudah diputus tidak bisa dibatalkan (pesan jelas).
- [ ] Kartu statistik = isi daftar (izin COKLAT milik tamu tidak ikut terhitung).
- [ ] Pass digital & PDF kartu hanya untuk permit aktif; PDF massal tidak bisa memuat kartu orang lain. **[R]**
- [ ] Akun tanpa data karyawan membuka detail permit orang lain → 403. **[R]**

## 4. Agenda inspeksi SHE (`/agenda-inspeksi`)

- [ ] Kelola pos, shift, pola mingguan, pengecualian tanggal, tutup/buka hari → slot di form pemohon ikut berubah.
- [ ] Nonaktifkan/hapus pos atau shift yang masih punya booking → perilaku jelas (ditolak atau booking tetap aman).
- [ ] "Tidak hadir" hanya bisa setelah jam inspeksi dimulai; tidak hadir ke-2 → permit gugur otomatis dan akses palang inspeksinya berakhir. **[R]**
- [ ] Petugas memindah jadwal pemohon; permit COKLAT tidak bisa dipindah. **[R]**
- [ ] Daftar "belum dicatat" memuat jadwal lewat yang belum diisi hasilnya.
- [ ] Baris tamu B2B: hanya tombol Review & Tidak Hadir; "Selesai" tercatat otomatis setelah permit COKLAT disetujui; permit ditolak tidak bisa dicatat; pos B2B kosong → tombol tidak muncul.

## 5. Verifikator permit (`/verifikator-visitor/permit`)

- [ ] Antrian vs riwayat benar; angka kartu statistik = isi daftar.
- [ ] Review: simpan checklist, unggah/ganti dokumen, lihat riwayat koreksi.
- [ ] Setujui tanpa hasil inspeksi lengkap (model, tahun, warna) → ditolak.
- [ ] Masa berlaku: mulai & akhir wajib berpasangan, akhir > mulai, mulai tidak boleh tanggal lampau, durasi minimal 1 bulan; `VALIDITY_REQUIRED` hanya bisa ditetapkan sekali.
- [ ] Tolak tanpa alasan → ditolak; dengan alasan → pemohon dapat notifikasi.
- [ ] Verifikator memutus pengajuannya sendiri → ditolak. **[R]**
- [ ] Approval bertingkat: langkah pertama → "Approval dicatat dan diteruskan", baru final di approver terakhir.
- [ ] Approval kedaluwarsa → pesan 409 "batas waktu sudah lewat", bukan 500. **[R]**
- [ ] Koreksi data (kategori, plat, model, warna, tahun, keterangan): alasan wajib; tanpa perubahan ditolak; hanya permit karyawan yang masih menunggu; dua verifikator membuka dialog bersamaan → yang kedua dapat pesan konflik.
- [ ] Cabut (revoke) satu & massal → hilang dari akses gerbang, kartu jadi tidak sah; pengajuan yang masih pending tidak ikut rusak. **[R]**

## 6. Tamu reguler (`/transaksitamu`)

- [ ] Estimasi masuk/keluar wajib berpasangan, keluar > masuk, rentang maksimal 30 hari.
- [ ] Tamu tanpa kendaraan (pejalan kaki) bisa disimpan tanpa plat.
- [ ] Foto kendaraan: jpg/png/webp ≤ 5 MB; unggah gagal → pengajuan tetap tersimpan dengan peringatan.
- [ ] Isian melebihi panjang kolom → pesan jelas, bukan 500.
- [ ] Anggota rombongan: tambah/hapus beberapa orang, tersimpan dan tampil di detail.
- [ ] Keperluan nonaktif tidak bisa dipilih.
- [ ] Status sesuai setelan approval (saat ini tanpa approval → langsung terjadwal); PIC bisa membatalkan.
- [ ] Riwayat menampilkan label status yang benar untuk semua status (terjadwal, masuk, keluar, keluar tak tercatat, dibatalkan, ditolak, dicabut).

## 7. Tamu B2B (`/transaksi-tamu-b2b`)

- [ ] Wajib berkendaraan dan plat wajib diisi.
- [ ] Kunjungan langsung terjadwal tanpa approval; permit COKLAT terbit dan menunggu di meja verifikator permit.
- [ ] Approve COKLAT di meja permit → tamu dapat akses gerbang; reject → tidak.
- [ ] Cabut kunjungan → permit COKLAT-nya ikut batal (ulangi sekali lagi bila gagal, tidak boleh tersisa). **[R]**
- [ ] Unit Procurement vs Logistic hanya melihat datanya sendiri; tampilan QC Incoming sesuai matriks.
- [ ] Jadwal kedatangan tamu (`/jadwal-kedatangan-tamu`): daftar, statistik, detail cocok.

## 8. API mitra (Postman)

- [ ] Tanpa/dengan tanda tangan salah → ditolak; aplikasi tidak di-whitelist ke endpoint → ditolak.
- [ ] `Idempotency-Key` kosong/>100 karakter → 422; kirim ulang key sama → balasan sama, tidak ada data ganda; dua kiriman bersamaan → satu data. **[R]**
- [ ] Tamu B2B: ajukan, cek status; status permit sama dengan endpoint `/permit-tamu/{no}`. **[R]**
- [ ] Permit tamu: ajukan, cek status, `POST …/batal`.
- [ ] Kunjungan yang sudah berakhir berstatus `EXPIRED`. **[R]**
- [ ] Cek kartu publik `/v1/hcis/visitor/kartu-permit/{kode}`: kode valid, kode acak (palsu), permit dicabut, masa habis, belum pernah berlaku, kode bukan 32 hex → 404.

## 9. Verifikasi kartu

- [ ] Pindai QR/NFC dari halaman verifikasi kartu (kamera berfungsi, izin kamera ditolak → pesan jelas). **[R]**
- [ ] Karyawan hanya bisa memverifikasi kartunya sendiri; admin bisa semua.

## 10. Monitoring & laporan

- [ ] `/monitoring-permit`, `/monitoring-tamu`, `/monitoring-b2b`: angka kartu = filter = isi tabel; "Sudah kedaluwarsa" tidak selalu 0; permit dicabut tidak muncul di papan harian. **[R]**
- [ ] Status permit COKLAT sama di papan tamu, laporan, jadwal kedatangan, export. **[R]**
- [ ] "Di dalam area" tidak menghitung tamu keluar tak tercatat. **[R]**
- [ ] Detail permit/tamu dari monitoring, cetak kartu admin (satu & massal).
- [ ] Dashboard analitik & "sekarang": filter tanggal, grafik per kategori/tipe tamu masuk akal.
- [ ] Laporan harian tanggal lampau menampilkan data historis, bukan status hari ini. **[R]**

## 11. Export Studio (`/visitor/export-studio/permit` & `/tamu`)

- [ ] Katalog kolom muncul, pilih kolom & filter, jalankan, unduh hasil; simpan & pakai ulang template.
- [ ] Angka "Tidak Datang" sesuai laporan tamu.

## 12. Proses otomatis & notifikasi

- [ ] `php artisan visitor:expire-efektif`: permit/tamu lewat masa berlaku → kedaluwarsa & akses gerbang berakhir; kunjungan approval kedaluwarsa → ditolak + PIC dikabari; booking permit mati → akses inspeksi berakhir. **[R]**
- [ ] Penjadwal benar-benar menjalankan perintah itu (tiap 5 menit) di lingkungan uji.
- [ ] Notifikasi in-app & WA untuk: pengajuan baru (ke verifikator), disetujui/ditolak/dicabut (ke pemohon/PIC); tautan detail di notifikasi terbuka benar.
- [ ] Widget VMS di homepage dan jumlah pending approval cocok dengan meja verifikator.

## 13. Lintas halaman

- [ ] Tampilan mobile (form pengajuan, pass digital, pemindai kartu).
- [ ] Refresh/back browser di tengah modal tidak memunculkan data basi atau kiriman ganda.
- [ ] Tombol batal permit yang akan ditolak server menampilkan pesan 409 yang jelas (dikenal, belum diperbaiki).
