# DDL-2026-038 — Catatan review per versi beserta lampirannya

| Bidang | Nilai |
|---|---|
| Change ID | `DDL-2026-038-sop-versi-review` |
| Jenis | DDL |
| Target development | `Web_HRIS` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |

## Isi

```text
N_SOP_Item_Versi_Review            Review_Ke, Tanggal_Review, Hasil_Review (nvarchar(max))
N_SOP_Item_Versi_Review_Lampiran   Path_Berkas, Nama/Tipe/Ukuran, Urutan
```

## Tabel anak, bukan kolom

Sebuah versi biasanya direview lebih dari sekali sebelum disetujui. Menyimpannya
sebagai `Review_1..n` berarti memilih angka maksimum di muka — dan angka itu
selalu salah: terlalu kecil bagi dokumen yang berputar lama, terlalu besar bagi
yang lolos sekali jalan. Alasan yang sama berlaku bagi lampirannya.

## Beda dari `Tanggal_Respon`

| | `Tanggal_Respon` (DDL-2026-034) | `N_SOP_Item_Versi_Review` |
|---|---|---|
| Isi | satu tanggal ringkas di identitas versi | catatan tiap putaran |
| Menjawab | kapan hasil review keluar | putaran ke berapa, apa isinya, lampirannya |
| Diisi | tercap otomatis saat tahap bergeser | diketik admin |

Keduanya sengaja tidak digabung.

## Sanitasi di server, bukan di layar

`Hasil_Review` disaring `SopHtml::sanitize()` — daftar putih 15 tag yang **tidak
memuat `<img>`**, jadi gambar tak pernah bisa menyusup lewat isi teks. Tag
berbahaya seperti `<script>` dibuang beserta isinya.

Editor Quill di layar juga menyaring dan membuang `<img>` saat paste, tetapi itu
**kenyamanan, bukan penjagaan**: panggilan API langsung tak pernah melewati
layar. Ada tes yang menembak endpoint dengan `<img src=x onerror=...>` dan
memastikan yang tersimpan sudah bersih.

## Daftar ditulis ulang, baris lama dinonaktifkan

Form mengirim daftar yang **dimaksud**; menambah saja akan meninggalkan review
yang sudah dihapus admin, dan ia muncul lagi setiap kali layar dimuat. Pola yang
sama dipakai blok pengesah pada versi.

Baris lama **dinonaktifkan**, bukan dihapus: lampirannya menunjuk berkas di GCS,
dan baris yang lenyap membuat berkas itu yatim tanpa jejak siapa pun.

## Lampiran

Menerima dua bentuk, sama seperti `N_HRIS_Master_Entitas.Logo_Path`: data URI
hasil unggah, dan path yang sudah tersimpan. Bentuk kedua diteruskan apa adanya
— tanpa itu, menyunting review akan mengunggah ulang gambar yang sama setiap
kali disimpan.

Berkas yang jenisnya di luar daftar (PNG/JPG/WEBP/GIF) atau melebihi 2 MB
**ditolak per berkas**, bukan menggagalkan seluruh penyimpanan: satu gambar
bermasalah tak boleh membuang catatan review yang sudah diketik panjang.

## Batas yang diketahui

Unggahan menulis ke disk `gcs`. Pada environment development ini kredensial GCS
rusak (path kunci masih format Windows di mesin Linux), sehingga **jalur
unggahnya belum terverifikasi end-to-end** — sama seperti logo Master Entitas.
Yang sudah terverifikasi: sanitasi, penulisan ulang, penomoran putaran,
penerusan path, dan penolakan berkas.

## Rollback

`drop.sql` **tidak pernah dijalankan agen**. Ia menghapus isi yang diketik
manusia dan tak ada di tempat lain; berkas di GCS tidak ikut terhapus dan akan
menjadi yatim.
