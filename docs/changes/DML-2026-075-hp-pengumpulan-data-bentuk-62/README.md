# DML-2026-075 — Nomor HP pengumpulan data disatukan ke bentuk `62…`

| | |
|---|---|
| **Change ID** | `DML-2026-075-hp-pengumpulan-data-bentuk-62` |
| **Jenis** | DML |
| **Target** | `Web_HRIS` (development) |
| **Artefak** | `database/dml/DML-2026-075-hp-pengumpulan-data-bentuk-62/data-change.sql` |
| **Objek terpengaruh** | `N_HRIS_Pengumpulan_Data_Submission.Data_Json`, `N_HRIS_Pengumpulan_Data_Status.Draft_Json` |
| **Status** | Dijalankan di dev 4 Sep 2026 — 6 baris; verifikasi A1=0, A2=0, B1=0, C1=3. Produksi masih PENDING. |

## Sebabnya

Fitur Pengumpulan Data menyimpan nomor HP sebagai `08…`. Seluruh sistem yang
lain memakai `62…`:

| sumber | bentuk |
|---|---|
| `Karyawan.HP` | `62…` — **958 dari 958** baris karyawan aktif, seragam `62` + 11 angka berawalan `8` |
| `WhatsappNotificationMirrorService::normalizePhone()` | mengubah apa pun yang diterimanya menjadi `62…` sebelum mengirim |
| `N_HRIS_Pengumpulan_Data_*` | `08…` |

Yang menyimpang adalah fiturnya, bukan datanya. Dua akibat yang benar-benar
terjadi:

1. **Prefill harus membengkokkan data master.** `PengirimanDataService::normalkanHp()`
   mengubah `6282184462915` milik master jadi `082184462915` semata-mata supaya
   lolos aturan fiturnya sendiri. Perbaikan yang terlihat benar sebulan lalu
   sebenarnya menambal gejala dari aturan yang salah arah.
2. **Deteksi HP kembar buta.** `AdminPengumpulanDataService` menyusun indeks
   duplikat dengan perbandingan string apa adanya, jadi satu nomor yang sama
   dalam dua bentuk tidak pernah terbaca kembar.

## Yang sudah diubah di kode

| berkas | perubahan |
|---|---|
| `config/pengumpulan_data.php` | `$polaHp` dibalik `^08…` → `^62…`; `$awalanSimpanHp = '62'` dideklarasikan dan dipublikasikan ke UI sebagai `prefix_simpan` pada `no_hp` dan `darurat_no_hp` |
| `resources/js/composables/ESS/Employee/DataCollection/formatBidang.js` | `nilaiSimpanHp()` memasang awalan yang **dibaca dari skema**, bukan yang ditanam |
| `resources/js/Pages/ESS/Employee/DataCollection/components/BidangIsian.vue` | meneruskan `definisi.prefix_simpan` ke `nilaiSimpanHp()` |
| `app/Services/ESS/Employee/DataCollection/PengirimanDataService.php` | `normalkanHp()` mengembalikan `'62'.$angka` — untuk 958 baris nyata kini hampir no-op |

Bentuk simpan sekarang hidup di **satu** tempat (`config`), dan pola validasi
server disusun dari nilai yang sama. Sebelumnya angka itu tertulis terpisah di
config, di browser, dan di dalam uji — tiga tempat yang bisa berselisih diam-diam.

## Ruang lingkup data

Dicari lewat pola `LIKE '08%'`, bukan id yang dipatok.

| tabel | baris | keterangan |
|---|---|---|
| `N_HRIS_Pengumpulan_Data_Submission` | 3 | Id 263 (H45), 300 (Y62), 339 (E14) — `no_hp` dan `darurat_no_hp` |
| `N_HRIS_Pengumpulan_Data_Status` | 3 | A4, C9 (`no_hp`), K32 (`darurat_no_hp`) |

F19 tidak ikut: draf-nya sudah `62…`.

**A4 (`082839283`)** hanya berisi 8 angka yang diketik, di bawah minimum 9. Ia
tidak sah sebelum maupun sesudah change ini; bentuknya tetap diseragamkan dan
wizard akan menahannya di langkah itu. Draf memang isian separuh jadi.

## Yang sengaja TIDAK disentuh

`Snapshot_Lama_Json` — rekaman mentah sistem induk pada detik pengiriman.
Menormalkannya berarti membuat rekaman yang lebih rapi daripada kenyataannya,
dan perbandingan "berbeda dari data lama" jadi membandingkan dua hasil olahan.
Ketiganya memang sudah `62…`, karena disalin apa adanya dari `Karyawan.HP` —
justru itu buktinya.

`Draft_Updated_At` — merekam kapan **orangnya** terakhir menyimpan draf.
Menggesernya akan membuat draf lama tampak baru disentuh pemiliknya.

## Verifikasi

| pemeriksaan | harapan |
|---|---|
| `A1_Submission_Masih_08` | 0 |
| `A2_Draft_Masih_08` | 0 |
| `B1_Terkirim_Tak_Lolos_Pola` | 0 — tiap nomor terkirim lolos `^628[0-9]{8,12}$` |
| `C1_Snapshot_Masih_62` | 3 — snapshot mentah tak ikut berubah |

Draf sengaja tidak dituntut lolos pola.

## Sifat

Idempoten: predikat `LIKE '08%'` membuat jalan kedua tidak menemukan apa pun.

## Sisa pekerjaan pengguna

1. Tinjau dan setujui `data-change.sql`.
2. Jalankan ke produksi lewat SQL Tracker.
3. Verifikasi keempat angka di atas pada produksi.

Perlu diketahui: di produksi jumlah barisnya bisa jauh lebih besar daripada 6,
dan **draf yang berbentuk `08…` itulah yang paling perlu diperbaiki** — kotaknya
menampilkan nomor yang tampak benar (karena `angkaDiketikHp()` membuang `0`
maupun `62` di depan), pemeriksa di browser diam, dan hanya server yang menolak
saat Kirim, dengan kalimat yang menyuruh mengetik persis apa yang sudah tertera
di layar.
