# DDL-2026-103-visitor-permit-coklat-approval

## Ringkas

Permit COKLAT tamu berhenti terbit sendiri. Ia kini **transaksi permit yang
sesungguhnya**, melewati alur persetujuan yang sama dengan permit kendaraan.

| Objek | Perubahan |
|---|---|
| `N_EMI_VISITOR_Transaksi_Permit` | + `No_Transaksi_Tamu` (tautan ke kunjungan) |
| `N_EMI_VISITOR_Transaksi_Permit_Detail` | + `Jenis_Kendaraan_Tamu` |
| `UX_VISITOR_Permit_Transaksi_Tamu` | indeks unik baru: satu kunjungan tepat satu permit |
| `IX_VISITOR_Permit_Pemohon_Sumber_Tamu` | indeks baru, penopang penyaring kuota |
| `N_VISITOR_Permit_Efektif` | penutupan baris permit COKLAT format lama (DML) |

## Kenapa muncul sekarang

Permintaan user 2026-09-14: *"tamu ny memang auto approve, tpi kalau untuk
permit yang di ajukan melalui tamu itu tetap harus approval seperti biasa."*

Sebelum change ini, registrasi tamu B2B menulis izin gerbangnya **langsung ke
proyeksi** `N_VISITOR_Permit_Efektif` dengan `Status_Efektif='AKTIF'`. Tidak ada
transaksi permit, tidak ada baris `HRIS_Approval_Request` — jadi tidak ada apa
pun yang bisa disetujui atau ditolak. Akses fisik ke area perusahaan terbit
begitu kunjungannya efektif, tanpa seorang pun memutuskannya.

## Keputusan desain

**Permit butuh nomornya sendiri, karena itu butuh kolom tautan.** Alur approval
berkunci `No_Transaksi`. Rancangan lama memakai nomor KUNJUNGAN sebagai nomor
permit, sehingga persetujuan tamu dan persetujuan permit akan saling menimpa di
satu kunci yang sama. Memberi permit nomor `PMT` sendiri memaksa adanya kolom
yang menghubungkannya kembali ke kunjungan — itulah `No_Transaksi_Tamu`.

**Satu kunjungan tepat satu permit, dijaga indeks unik.** Registrasi tamu
disinkronkan ulang setiap kali datanya disunting. Tanpa kunci unik, setiap
penyuntingan berpeluang melahirkan permit kedua: dua izin gerbang atas satu
tamu, dan yang satu tidak akan pernah dicabut ketika tamunya pulang. Predikat
indeksnya menyertakan `Flag_Aktif = 'Y'` supaya permit yang dinonaktifkan tidak
ikut menahan kunci, dan bersifat terfilter sehingga permit karyawan
(`No_Transaksi_Tamu` NULL) tidak tersentuh sama sekali.

**Permit yang ditolak tidak dihidupkan ulang oleh penyuntingan tamu.** Keputusan
verifikator bukan sesuatu yang bisa dibatalkan dengan mengganti satu huruf pada
nama tamunya.

**Jenis kendaraan tamu butuh kolomnya sendiri.** Permit karyawan menurunkan
jenis kendaraan dari master model; registrasi tamu hanya punya teks bebas.
Rancangan lama menyalin teks itu langsung ke proyeksi. Karena proyeksinya kini
dibangun dari transaksi permit, teksnya harus punya tempat di sana — kalau tidak,
satpam kehilangan satu-satunya petunjuk kendaraan apa yang datang, tanpa galat.

**Izin sehari tidak memakan kuota permit PIC.** `terpakai()` menghitung semua
permit atas nama seorang karyawan. Tanpa pengecualian, karyawan yang
mendaftarkan dua tamu akan membaca "2/2 terpakai" pada permit kendaraannya
sendiri — tanpa satu pun keterangan kenapa.

**Verifikator tidak memilih durasi untuk izin sehari.** Sama seperti permit
mitra: masa berlakunya sudah dipaku sejak pengajuan. Panel review sebelumnya
menuntut pilihan durasi pada approval final, sehingga tombol Setujui tidak
pernah menyala padahal server memang mengabaikan durasi yang dipilih.

## Inspeksi verifikator (tanpa perubahan skema)

Keputusan user 2026-09-14, menyusul change ini: review permit COKLAT harus
sama lengkapnya dengan review permit HIJAU/MERAH. Perbedaannya, field yang tidak
dipunyai permit COKLAT justru **diisi verifikator** — itulah bagian inspeksinya.

| Bagian | Sebelum | Sesudah |
|---|---|---|
| Checklist pemeriksaan fisik | tidak pernah terbit untuk COKLAT | di-snapshot sejak pengajuan, wajib lengkap sebelum disetujui |
| Merek/model, tahun, warna | selamanya `-` di gerbang | dipilih verifikator saat review, wajib sebelum disetujui |
| Dokumen kendaraan | kartunya disembunyikan | diunggah verifikator di pos |
| Pilihan durasi | dituntut tetapi diabaikan server | tidak ditampilkan; masa berlakunya sudah dipaku satu hari |

Seluruhnya memakai kolom yang sudah ada (`Id_Master_Model`, `Id_Master_Warna`,
`Tahun_Kendaraan`, tabel checklist dan dokumen), sehingga **tidak ada perubahan
skema** untuk bagian ini.

Tiga hal yang sengaja dikerjakan begini:

**Penjaganya di server, bukan di tombol.** Jalur approval legacy dan pemanggilan
langsung ke service melewati layar sepenuhnya; tombol yang di-disable saja
membiarkan izin akses fisik terbit tanpa seorang pun melihat kendaraannya.

**"Ada kendaraan untuk diinspeksi" punya SATU definisi**, ekspresi SQL
`VisitorPermitQueryService::wajibInspeksiSql()`, dipakai kolom yang dibaca layar
maupun penjaga di jalur persetujuan. Dua definisi yang berbeda tipis membuat
tombol Setujui menyala sementara servernya menolak. Tamu pejalan kaki tidak
membawa apa pun untuk diperiksa, jadi ia tidak diberi checklist dan tidak
dituntut apa pun.

**Isian yang tidak dikirim tidak menimpa apa pun.** Approval bertahap
menjalankan formulir yang sama sekali per langkah; approver kedua yang mengirim
formulir kosong tidak boleh menghapus catatan approver pertama.

Snapshot checklist juga berlaku untuk `/permit-tamu`, dan `Jenis_Kendaraan_Tamu`
kini ikut disimpan dari payload mitra — sebelumnya kolom itu kosong sehingga
gerbang tidak punya satu pun petunjuk kendaraan apa yang datang.

## Konsekuensi bila tidak dijalankan

Change ini **wajib jalan sebelum kodenya rilis**. Tanpa `No_Transaksi_Tamu`,
setiap registrasi tamu B2B gagal dengan `Invalid column name 'No_Transaksi_Tamu'`
— termasuk registrasi dari layar HCIS.

## Yang berubah bagi mitra API

Balasan `GET /api/v1/hcis/visitor/tamu-b2b/{no_transaksi}` kini memuat dua status
yang harus dibaca keduanya:

- `status` — keadaan KUNJUNGAN-nya.
- `permit_coklat.status` — keadaan IZIN GERBANG-nya, bertambah nilai `PENDING`
  dan `EXPIRED`, plus kunci baru `permit_coklat.no_transaksi`.

Kunjungan `APPROVED` tidak lagi berarti tamunya boleh masuk. Mitra yang
memperlakukan `status: APPROVED` sebagai izin masuk akan mengirim tamunya ke
gerbang sebelum izinnya ada.

## Data lama

`N_VISITOR_Permit_Efektif` berformat lama (berkunci nomor kunjungan, tanpa
transaksi permit) **ditutup**, bukan dikonversi: mengarangkan transaksi permit
berikut riwayat persetujuannya berarti memalsukan siapa menyetujui apa. Dibiarkan
hidup, ia menjadi izin gerbang yang tidak bisa dimatikan siapa pun kecuali lewat
lewatnya `Waktu_Berakhir` — sapuan "tamu sudah keluar" dan penutupan saat
check-out kini keduanya mencari tautan yang tidak pernah ada pada baris itu.

Di production tidak ada satu pun baris seperti itu: `DDL-2026-077` dan
`DDL-2026-079` keduanya masih `PENDING` di sana, sehingga DML-nya memutakhirkan
nol baris. Ia tetap disertakan supaya urutan yang dijalankan di production
identik dengan yang diverifikasi di development.

## Berkas

| Peran | Path |
|---|---|
| Forward DDL | `database/ddl/DDL-2026-103-visitor-permit-coklat-approval/forward.sql` |
| Drop (manual saja) | `database/ddl/DDL-2026-103-visitor-permit-coklat-approval/drop.sql` |
| DML | `database/dml/DDL-2026-103-visitor-permit-coklat-approval/data-change.sql` |

## Status

Development: **DEV_EXECUTED** (2026-09-14, `Web_HRIS`).

Diverifikasi dari katalog sistem, bukan dari keluaran skrip: `No_Transaksi_Tamu
varchar(50) NULL` dan `Jenis_Kendaraan_Tamu varchar(50) NULL` terbentuk;
`UX_VISITOR_Permit_Transaksi_Tamu` terbentuk unik dengan filter
`([No_Transaksi_Tamu] IS NOT NULL AND [Flag_Aktif]='Y')`;
`IX_VISITOR_Permit_Pemohon_Sumber_Tamu` terbentuk; DML menyisakan 0 baris permit
COKLAT format lama yang masih AKTIF.

## Pekerjaan user

- Merekam change ini di SQL Tracker, lalu mengeksekusinya ke production dan
  memverifikasinya. Jalankan bersama `DDL-2026-102`; keduanya prasyarat rilis
  yang sama.
- **Memberi tahu mitra** bahwa `permit_coklat.status` kini bisa `PENDING`, dan
  tamu baru boleh dikirim ke gerbang setelah nilainya `APPROVED`.
- **Memastikan PIC aplikasi mitra punya alur approval PERMIT.** Tanpa itu,
  registrasi tamu B2B ditolak `422` — permit yang tidak punya alur tidak akan
  pernah muncul di meja siapa pun.
