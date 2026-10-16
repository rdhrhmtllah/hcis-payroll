# DML-2026-105-pisah-monitoring-dan-verifikator

## Ringkas

Dua halaman gabungan dipecah per domain menjadi **empat halaman**.

| Halaman lama | Halaman baru | URL |
|---|---|---|
| `visitorLaporanPage` (Laporan Visitor) | `visitorMonitoringTamuPage` | `/monitoring-tamu` |
| | `visitorMonitoringPermitPage` | `/monitoring-permit` |
| `visitorVerifikatorPage` (Verifikator Visitor) | `visitorVerifikatorPermitPage` | `/verifikator-visitor/permit` |
| | `visitorVerifikatorTamuPage` | `/verifikator-visitor/tamu` |

## Kenapa muncul sekarang

Permintaan user 2026-09-15. Kedua halaman itu sejak awal memisahkan pekerjaan
lewat tab, dan panelnya memang sudah berdiri sendiri — tetapi hak aksesnya satu.
Admin yang hanya mengurus tamu tetap melihat, dan di meja verifikator tetap
**dapat menyetujui**, permit kendaraan.

Untuk verifikator, user secara eksplisit memilih dua halaman terpisah daripada
"satu halaman dua menu" setelah diberi tahu bahwa yang terakhir tidak memisahkan
akses sama sekali.

## Keputusan desain

**Pemisahannya sampai ke data, bukan hanya tampilan.** `check.jabatan` ternyata
sudah menerima banyak kunci dengan makna ATAU, jadi tiap endpoint dijaga kunci
domain yang benar-benar memakainya. Tiga endpoint monitoring (`papan-harian`,
`papan-tamu`, `antrian`) dan statistik verifikator dibaca kedua domain, jadi
keduanya dijaga dua kunci. Menyalin endpoint ke dua grup rute tidak mungkin: URL
yang sama terdaftar dua kali dan yang terakhir menang secara diam-diam.

**URL lama menjadi pengalihan, bukan 404.** Notifikasi yang sudah terkirim,
papan harian, dan bookmark semuanya menautkan URL lama. Pengalihan verifikator
membawa serta seluruh query string dan memilih halaman dari `jenis` — tanpa itu
setiap tautan notifikasi tamu mendarat di meja permit dan detailnya tidak pernah
terbuka. Rute pengalihannya sengaja **tidak** dijaga kunci lama: menjaganya
justru mengunci orang yang sudah dipindahkan ke kunci baru.

**Hak akses diturunkan dari data.** Pemegang aktif kunci lama menerima kedua
kunci barunya beserta dimensi perusahaan/level/divisinya sendiri — bukan dari
daftar user yang ditulis tangan, supaya tetap benar bila audiensnya berubah
sebelum changeset ini dijalankan ke production. Satu orang yang terlewat akan
kehilangan halamannya tanpa pemberitahuan apa pun.

**Hak akses lama tidak dihapus.** Ia satu-satunya catatan siapa yang dulu
berhak; menghapusnya membuat pembatalan pemecahan ini mustahil. Menghapusnya
juga tidak menambah keamanan: tidak ada satu rute pun yang masih dijaga kunci
itu, dan ada uji yang menagihnya.

**Menu lama disembunyikan lewat `Is_Show_Sidebar`, bukan dihapus.**
`N_HRIS_Menus` tidak punya kolom `Flag_Aktif` — dikonfirmasi dari
INFORMATION_SCHEMA — sedangkan `Is_Show_Sidebar` memang disaring
`InertiaShellService`. Menghapus barisnya tidak dapat dibatalkan.

**Statistik verifikator bertambah rincian per domain.** Meja lama memakai angka
gabungan (`disetujui_hari_ini`, `ditolak_total`). Halaman Permit yang memajang
angka gabungan akan menampilkan pekerjaan domain lain sebagai miliknya sendiri,
dan tidak ada yang akan menyadarinya karena angkanya tetap masuk akal. Kunci
gabungannya **dipertahankan** — ia bagian kontrak yang sudah dibaca pemanggil
lain.

## Berkas

| Peran | Path |
|---|---|
| DML | `database/dml/DML-2026-105-pisah-monitoring-dan-verifikator/data-change.sql` |

Tidak ada DDL: seluruhnya memakai tabel yang sudah ada.

## Status

Development: **DONE** (2026-09-15, `Web_HRIS`).

Diverifikasi dari basis data, bukan dari keluaran skrip: empat baris menu baru
terbentuk dengan `Is_Show_Sidebar='Y'` dan urutan 20/25/30/35; empat baris
`N_HRIS_Grup_Fitur_Map` aktif; hak akses tiap kunci baru **2 baris**, sama
persis dengan pemegang kunci lamanya; kedua menu gabungan lama
`Is_Show_Sidebar='T'`; cache menu di-flush.

Uji sudah ditulis (`tests/Feature/VISITOR/PemisahanHalamanVisitorTest.php`)
tetapi **belum dijalankan** atas permintaan user.

## Pekerjaan user

- Merekam change ini di SQL Tracker, lalu mengeksekusinya ke production dan
  memverifikasinya. Jalankan **sesudah** kodenya rilis: tanpa kodenya, menu baru
  menunjuk URL yang belum ada.
- Menetapkan siapa yang sebenarnya berhak atas tiap domain. Saat ini keempat
  kunci diberikan kepada seluruh pemegang kunci lama — pemecahan aksesnya baru
  **mungkin**, belum **terjadi**. Pencabutan per orang dilakukan lewat
  pengelolaan hak akses halaman seperti biasa.
