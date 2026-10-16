# DDL-2026-064 — Indeks penopang jendela 90 hari panel Pemahaman

| | |
|---|---|
| Change ID | `DDL-2026-064-sop-indeks-jendela-baca` |
| Tipe | DDL (hanya `CREATE INDEX`) |
| Basis data target | `Web_HRIS` |
| Lingkungan eksekusi agen | development, diverifikasi `SELECT DB_NAME()` |
| Tanggal eksekusi dev | 2026-09-01 |
| Forward | `database/ddl/DDL-2026-064-sop-indeks-jendela-baca/forward.sql` |
| Drop (alat bantu manual) | `database/ddl/DDL-2026-064-sop-indeks-jendela-baca/drop.sql` |
| DML | tidak ada |
| Checksum forward.sql (sha256) | `8e80083e40d28d69c984b298a7394f839dd0990ebcff0181fdaa32d0ae323ce1` |

## Mengapa

Audit 2026-09-01 menemukan `SopRingkasanAgregatService::bacaVsEstimasi()` dan
`kualitasBaca()` memindai **seluruh `N_SOP_Progress` yang pernah tercatat**, tanpa
batas waktu apa pun. Terukur: **1.624 logical reads** untuk 1.156 baris, dengan
rencana berisi dua `Clustered Index Scan` dan dua `Sort`.

Kode diperbaiki lebih dulu — keduanya kini dibatasi `Waktu_Buka >= 90 hari`
(keputusan produk 2026-09-01). Tetapi **jendela saja tidak cukup**: tanpa indeks,
SQL Server tetap membaca setiap baris untuk menguji predikatnya. Jendela membatasi
pertumbuhan; indeks inilah yang mengubah pindaian menjadi pencarian.

## Objek yang dibuat

```
IX_N_SOP_Prg_Waktu_Buka
    ON N_SOP_Progress (Waktu_Buka)
    INCLUDE (Id_SOP_Paket_Item, Kode_Karyawan, Durasi_Aktif_Detik)
    WHERE Waktu_Buka IS NOT NULL
```

**Terfilter `WHERE Waktu_Buka IS NOT NULL`** karena kolomnya nullable dan baris
tanpa waktu buka tak pernah dicari jalur ini — 241 dari 1.156 baris (21%) pada
saat pengukuran. Indeks terfilter membuatnya tak ikut memikul biaya tulis.

**`INCLUDE` memuat tepat kolom yang dibutuhkan agregatnya**, sehingga kuerinya
tercakup penuh dan tak perlu kembali ke tabel.

## Hasil terukur (dev, 2026-09-01)

| | Sebelum | Sesudah |
|---|---|---|
| Logical reads | **1.624** | **28** |
| Akses `N_SOP_Progress` | Clustered Index Scan | **Index Seek** |

Perbandingan diambil pada data yang sama; jendela 90 hari sudah aktif di kedua
sisi, sehingga selisih ini murni akibat indeksnya.

## Catatan penting soal keterbatasan pengukuran

Pada data dev, jendela 90 hari **hampir tidak membuang apa pun** — 797 dari 801
baris berdurasi memang berumur di bawah 90 hari. Manfaat jendelanya karena itu
belum terlihat di sini dan baru muncul setelah tabel berisi riwayat lebih panjang.
Yang terlihat sekarang murni manfaat indeksnya.

## Kolom waktu yang dipilih, dan mengapa bukan yang lain

`Waktu_Buka`, **bukan `Updated_At`**. Diukur pada 2026-09-01: `Updated_At` bernilai
NULL pada **1.152 dari 1.156 baris (99,7%)**, sehingga menyaring dengannya akan
mengosongkan panel ini hampir seluruhnya **tanpa satu pun galat**. Sebaliknya,
seluruh 801 baris berdurasi > 0 memiliki `Waktu_Buka` — nol NULL.

## Yang sengaja TIDAK diberi jendela

Panel **Titik Henti** (`penyerapanVersi`) tidak dibatasi waktu, dan itu keputusan
sadar. Ia menjawab "siapa yang masih tertinggal di versi lama" — pertanyaan tentang
**keadaan sekarang**, bukan aktivitas terkini. Orang yang mengonfirmasi revisi kedua
tiga tahun lalu sementara yang berlaku sudah keempat memang masih tertinggal;
menyaringnya lewat jendela waktu akan menyembunyikan persis orang yang dicari panel
itu.

## Dikerjakan agen

- Menulis `forward.sql` dan `drop.sql`.
- Menjalankan `forward.sql` terhadap `Web_HRIS` (development) setelah memverifikasi
  target dengan `SELECT DB_NAME()`.
- Memverifikasi indeks terbentuk lewat `sys.indexes`, dan memverifikasi rencana
  eksekusinya benar-benar berubah menjadi `Index Seek`.
- Mencatat perubahan ini di `database/ddl/status.json`.

## Tersisa untuk pemilik project

1. Tinjau dan setujui `forward.sql`.
2. Catat ke SQL Tracker dengan isi dan checksum yang identik.
3. Eksekusi ke produksi, lalu konfirmasikan agar status `production` dapat dinaikkan.
   Status itu tidak boleh disimpulkan dari hasil development.
