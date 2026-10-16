# Panduan penguji — dua skenario UAT SOP yang wajib dijalankan manusia

Dari 48 skenario pada dokumen UAT, **46 sudah terbukti otomatis**. Dua sisanya
tidak bisa, dan itu bukan kekurangan yang akan ditutup nanti — keduanya menyentuh
sesuatu di luar jangkauan suite. Berkas ini menyiapkan penguji agar sesi UAT-nya
tidak habis untuk menemukan prasyarat yang belum ada.

Kolom **Hasil Aktual** dan **Status** pada dokumen UAT tetap diisi penguji saat
sesi. Berkas ini tidak mengisinya, dan tidak boleh dianggap sebagai bukti.

---

## UAT-SOP-034 — Kuis / Checkpoint

**Penguji:** Karyawan · **Modul:** Kuis / Checkpoint

**Kenapa tak bisa diotomatiskan.** Mengerjakan kuisnya berlangsung di sistem
ujian **HC Learn**, di luar modul SOP. Yang bisa dilihat SOP hanyalah hasil yang
dikembalikan sistem itu. Suite bisa membuktikan apa yang terjadi *setelah* nilai
masuk — dan memang membuktikannya di UAT-SOP-035, termasuk penolakan saat nilai
di bawah batas — tetapi tidak bisa mengerjakan ujiannya.

### Siapkan sebelum sesi

| Prasyarat | Cara memastikan |
|---|---|
| Paket SOP berisi item bertipe **Kuis** yang sudah tertaut paket ujian | Builder → item → tipe Kuis → kolom "Ujian (HC Learn)" terisi |
| **Batas nilai lulus** terisi pada item itu | Item kuis tanpa `Nilai_Minimum` akan ditolak saat disimpan |
| Paket sudah **terbit** dan **ditugaskan** ke akun karyawan penguji | Menu Penjadwalan |
| Akun penguji bisa masuk HC Learn dan melihat paket ujiannya | Coba lebih dulu, bukan saat sesi berlangsung |

Di basis data dev saat panduan ini ditulis ada **4 item kuis, 3 di antaranya
sudah tertaut ujian**. Pastikan yang dipakai penguji termasuk yang tertaut.

### Langkah

1. Masuk sebagai Karyawan, buka **SOP Saya**, buka paket yang memuat item kuis.
2. Buka item kuisnya, kerjakan sampai selesai **dengan jawaban yang benar**.
3. Kembali ke halaman paket setelah ujian selesai.

### Lulus bila

- Kuis benar-benar terbuka dari dalam SOP (bukan hanya tautan yang mati).
- Nilai kembali ke SOP dan **tampil** pada item tersebut.
- Item kuis berubah menjadi selesai, dan progres paket ikut naik.

### Gagal bila

- Nilai tidak pernah kembali meski ujian sudah selesai di HC Learn.
- Item tetap belum selesai padahal nilainya sudah di atas batas.

### Yang TIDAK membuktikan apa-apa

Nilai yang tampil segera setelah ujian **belum tentu tersimpan**. Muat ulang
halaman paketnya sekali sebelum menyatakan lulus — layar bisa menampilkan hasil
yang belum sampai ke basis data.

---

## UAT-SOP-036 — Absensi Check-in

**Penguji:** Karyawan · **Modul:** Absensi Check-in

**Kenapa tak bisa diotomatiskan.** Bukan kameranya — layanan check-in menerima
gambar sebagai data URI, jadi secara teknis bisa dipanggil dari uji. Yang
menghalangi adalah **fotonya tersimpan ke Google Cloud Storage**. Kontrak
keamanan otomasi UAT hanya menghapus baris pada daftar-putih tabel; berkas yang
tertinggal di penyimpanan awan berada di luar jangkauannya, dan otomasi yang
meninggalkan sampah yang tak bisa ia bereskan sendiri bukan otomasi yang aman.

### Siapkan sebelum sesi

| Prasyarat | Cara memastikan |
|---|---|
| Paket dengan **Gerbang absensi = Wajib absensi** | Builder → langkah Identitas → Gerbang absensi |
| Paket sudah terbit dan ditugaskan ke akun karyawan penguji | Menu Penjadwalan |
| Perangkat penguji punya **kamera** dan izinnya diberikan ke peramban | Coba buka halaman check-in sebelum sesi |
| Peramban diakses lewat **HTTPS atau localhost** | Kamera tidak akan menyala di HTTP biasa, dan pesannya tidak menyebutkan itu |

Di basis data dev saat panduan ini ditulis ada **5 paket bergerbang absensi**.

### Langkah

1. Masuk sebagai Karyawan, buka paket yang mensyaratkan kehadiran.
2. Perhatikan bahwa isi paket **masih terkunci** sebelum check-in — catat ini,
   karena inilah yang membuat gerbangnya berarti.
3. Lakukan check-in sesuai cara yang diminta sistem (ambil foto wajah).
4. Setelah berhasil, buka kembali daftar itemnya.

### Lulus bila

- Check-in berhasil dan **waktunya tercatat**.
- Item paket **terbuka** setelah check-in, dan sebelumnya memang terkunci.
- Bukti fotonya bisa dilihat kembali oleh admin pada rekam kehadiran.

### Gagal bila

- Item sudah terbuka **sebelum** check-in — gerbangnya tidak menggerbangi apa pun.
- Check-in dilaporkan berhasil tapi waktunya tidak tercatat.

### Sekalian diuji: penolakan foto galeri

Sistem menolak foto yang **diambil dari galeri**, bukan langsung dari kamera —
dikenali dari jejak EXIF yang dibawa foto galeri. Coba unggah foto dari galeri
dan pastikan pesan penolakannya muncul. Ini bukan bagian resmi skenario 036,
tapi ia satu-satunya kesempatan menguji penjaga tersebut dengan perangkat nyata.

### Yang TIDAK membuktikan apa-apa

Layar bertuliskan "check-in berhasil" **belum** berarti fotonya tersimpan. Minta
admin membuka rekam kehadiran dan memastikan fotonya benar-benar bisa dibuka —
kegagalan unggah ke penyimpanan awan bisa terjadi setelah layar terlanjur
mengabarkan keberhasilan.

---

## Catatan untuk fasilitator

Kedua skenario di atas meninggalkan jejak yang **tidak** dibersihkan otomasi:
baris hasil ujian pada sistem HC Learn, dan berkas foto pada penyimpanan awan.
Itu memang konsekuensi menjalankannya dengan data sungguhan, dan disebutkan di
sini supaya tidak mengagetkan setelah sesi selesai.

Skenario lain tidak perlu dijalankan manual kecuali penguji ingin melihatnya
sendiri. Statusnya bisa dibaca pada laporan kesiapan yang dihasilkan
`php artisan sop:uat-laporan`.
