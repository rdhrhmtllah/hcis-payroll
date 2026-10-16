# DDL-2026-110 — Cakupan lokasi kerja untuk Pembaruan Data Karyawan

Status: `DEV_EXECUTED` · SQL Tracker: `TRACKER_PENDING` · Target: `Web_HRIS` (development)

## Masalah

Kampanye Pembaruan Data Karyawan hanya punya satu rem: sakelar menyala/mati
untuk seluruh perusahaan. Tidak ada cara membukanya bertahap, misalnya satu
lokasi kerja lebih dulu, dan satu-satunya alat penyempit yang tersedia —
pengecualian — bekerja per orang dan menulis satu baris status untuk setiap
orang yang dikecualikan.

## Bentuk perubahan

Satu kolom baru pada `N_HRIS_Pengumpulan_Data_Config`:

```sql
Cakupan_Lokasi VARCHAR(2000) NULL
```

Isinya array JSON berisi nilai `Karyawan.Cabang_Untuk_Gaji` yang sudah
dinormalkan (huruf besar, tanpa spasi pinggir), contoh `["EMI","JKT"]`.
`NULL` dan array kosong dibaca sebagai **seluruh lokasi** — perilaku sebelum
perubahan ini, sehingga menaikkannya tanpa mengisi apa pun tidak mengubah satu
pun keputusan yang sedang berjalan.

## Mengapa di baris konfigurasi, bukan per baris status

Menandai orang di luar cakupan satu per satu di
`N_HRIS_Pengumpulan_Data_Status` berarti menulis ribuan baris setiap kali
cakupan digeser, membuat pergeseran cakupan mahal, dan mengubur jejak
pengecualian individual di antara ribuan baris yang bukan keputusan siapa pun.
`Is_Dikecualikan` tetap berarti satu hal: keputusan admin atas SATU orang.

## Yang membaca kolom ini

Seluruhnya lewat `App\Services\ESS\Employee\DataCollection\CakupanKampanye`,
tidak ada yang membaca kolomnya langsung:

| Tempat | Akibat bila di luar cakupan |
| --- | --- |
| `AdminPengumpulanDataService::ringkasan()` | tidak masuk penyebut progres |
| `AdminPengumpulanDataService::perDepartemen()` | tidak masuk grafik departemen |
| `AdminPengumpulanDataService::daftar()` | tidak muncul di tabel admin |
| `AdminPengumpulanDataService::kirimPengingat()` | tidak dikirimi pengingat |
| `AdminPengumpulanDataService::pilihanDepartemen()` | departemennya tidak ditawarkan |
| `PengumpulanDataExportService::queryDasar()` | tidak ikut terekspor |
| `HandleInertiaRequests` (prop `pengumpulanData`) | tidak melihat modal/bar |
| `ArahkanKeFormulirPengumpulanData` | tidak dialihkan ke formulir |
| `PengumpulanDataController::tolakBilaPintuTertutup()` | formulir & pengiriman 403 |

`AdminPengumpulanDataService::pilihanLokasi()` sengaja **tidak** disempitkan:
kotak centang cakupan harus menawarkan lokasi yang belum dibuka.

## Karyawan tanpa lokasi kerja

`Cabang_Untuk_Gaji` yang `NULL` atau kosong tidak pernah cocok dengan cakupan
mana pun, jadi orangnya berada di luar cakupan dan tidak muncul di daftar. Agar
kehilangan itu tidak senyap, `ringkasan()` memulangkan
`di_luar_cakupan.tanpa_lokasi` dan halaman admin menampilkannya sebagai
peringatan. Menutup lubangnya adalah pekerjaan master data, bukan fitur ini.

## Urutan rilis

Jalankan DDL **sebelum** kode naik. Kolom yang belum ada tidak menimbulkan
galat — `KonfigurasiKampanye::cakupanLokasi()` membacanya sebagai daftar kosong
— sehingga kode yang naik lebih dulu akan diam-diam mengabaikan cakupan yang
disimpan admin dan mewajibkan seluruh karyawan aktif mengisi.

## Rollback

`drop.sql` membuang kolomnya. Akibatnya kampanye kembali terbuka serentak untuk
seluruh lokasi, juga tanpa galat apa pun.
