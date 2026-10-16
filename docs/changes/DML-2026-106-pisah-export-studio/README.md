# DML-2026-106-pisah-export-studio

## Ringkas

Export Studio Visitor dipecah per dataset, menyusul pemecahan Monitoring dan
Verifikator (`DML-2026-105`).

| Halaman lama | Halaman baru | URL |
|---|---|---|
| `visitorExportStudioPage` | `visitorExportStudioTamuPage` | `/visitor/export-studio/tamu` |
| | `visitorExportStudioPermitPage` | `/visitor/export-studio/permit` |

## Keputusan desain

**Satu komponen halaman, dua dataset.** Datasetnya dipaku dari server lewat prop
`datasetTerkunci`, dan tombol pengganti dataset di header hilang ketika ia
terkunci — menekannya akan memanggil endpoint domain lain dan dibalas `403`
tanpa sebab yang terbaca pemakainya. Penjaganya juga dipasang di dalam
`gantiDataset()`, bukan hanya pada tombolnya: tombol yang hilang dari layar
tidak menghalangi pemanggilan dari tempat lain.

Komponennya sengaja **tidak digandakan**. Isinya ratusan baris gaya perakit yang
identik; salinan kedua akan menua sendiri-sendiri, dan yang tertinggal justru
domain yang jarang dibuka.

**Datasetnya ruas LITERAL pada prefiks rute, bukan parameter yang dibatasi
`where()`.** Percobaan pertama memakai dua grup `api/v1/visitor/export-studio/{studioDataset}`
dengan `where()` berbeda, dan hasilnya: koleksi rute berkunci metode + POLA URI,
sehingga kedua grup terdaftar dengan pola identik dan yang belakangan **menimpa**
yang pertama. Seluruh jalur tamu lenyap tanpa satu pun galat — hanya 404 saat
dipanggil. Ini terdeteksi karena setiap URL diuji resolusinya satu per satu,
bukan karena rutenya "terlihat benar". Nilai datasetnya kini diteruskan lewat
`defaults()` supaya tanda tangan metode controller tidak berubah.

**Daftar endpointnya ditulis sekali, dipakai dua kali.** Dua salinan yang
disunting terpisah akan menua sendiri-sendiri.

Sisanya mengikuti `DML-2026-105` persis: hak akses diturunkan dari data, baris
akses kunci lama tidak dihapus, dan menu lama disembunyikan lewat
`Is_Show_Sidebar` — bukan dihapus, karena `N_HRIS_Menus` tidak punya kolom
`Flag_Aktif` dan penghapusan baris tidak dapat dibatalkan.

## Berkas

| Peran | Path |
|---|---|
| DML | `database/dml/DML-2026-106-pisah-export-studio/data-change.sql` |

Tidak ada DDL: seluruhnya memakai tabel yang sudah ada.

## Status

Development: **DONE** (2026-09-15, `Web_HRIS`).

Diverifikasi dari basis data dan dari resolusi rute, bukan dari keluaran skrip:
dua baris menu baru `Is_Show_Sidebar='Y'` urutan 40/45; dua pemetaan grup fitur
aktif; hak akses tiap kunci baru **2 baris**, sama dengan pemegang kunci lamanya;
menu studio gabungan `Is_Show_Sidebar='T'`; cache menu di-flush. Enam URL diuji
resolusinya — dua halaman dan empat endpoint — dan seluruhnya mendarat pada
kunci serta dataset yang benar.

Uji sudah ditulis tetapi **belum dijalankan** atas permintaan user.

## Pekerjaan user

- Merekam change ini di SQL Tracker bersama `DML-2026-105`, lalu mengeksekusi ke
  production dan memverifikasinya. Jalankan **sesudah** kodenya rilis.
- Menetapkan siapa yang berhak atas tiap dataset. Saat ini kedua kunci diberikan
  kepada seluruh pemegang kunci lama — pemisahannya baru **mungkin**, belum
  **terjadi**.
