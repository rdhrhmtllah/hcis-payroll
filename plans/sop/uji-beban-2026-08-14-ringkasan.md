# Uji beban & kunci modul SOP — ringkasan tiga jalan

Tanggal: 14 Agustus 2026 · Lingkungan: dev (vhost Laragon + `Web_HRIS` di team311, RCSI **mati**)

> Berkas `uji-beban-2026-08-14.md` dihasilkan `sop:beban-laporan` dan **selalu
> memuat jalan TERAKHIR saja**. Dokumen ini yang menyimpan keseluruhan ceritanya,
> termasuk temuan yang sudah tidak terlihat di sana.

## Pertanyaan yang dijawab

> Apakah menugaskan atau mencabut akses puluhan orang sekaligus mengganggu orang
> yang sedang membaca?

RCSI mati, sehingga pembaca mengantre di belakang penulis — itulah yang membuat
"tahan banyak orang" dan "operasi massal tidak mengunci" sebenarnya satu
pertanyaan.

**Jawabannya: tidak.** Terbukti pada dua bentuk, bukan satu.

## Tiga jalan, dan apa yang masing-masing temukan

### Jalan 1 — bentuk realistis (baca paket A, ubah paket B)

50 serentak · 3 × 60 detik · 50 karyawan disasar.

| Fase | n | p50 | p95 | gagal | blocking | Δ `LCK_M_S` |
|---|--:|--:|--:|--:|--:|--:|
| dasar | 345 | 2.717 ms | 6.777 ms | 0 | 0 | 0 |
| assign | 300 | 2.604 ms | 7.144 ms | 0 | 0 | 0 |
| cabut | 250 | 1.438 ms | 7.299 ms | 0 | 0 | 0 |

895 permintaan, **nol gagal, nol pembaca terblokir**.

**Tapi GAGAL pada kriteria 4:** assign massal 8.079 ms, cabut massal **15.872 ms**
— keduanya melewati ambang 5 detik yang ditetapkan sebelum menjalankan.

Dan p95 ~7 detik muncul **juga di fase dasar**, tanpa operasi massal apa pun.

### Temuan sela — p95 itu bukan basis data

Diagnostik hanya menulis bila permintaan ≥3 detik. Sepanjang uji, **tak satu pun
permintaan SOP tercatat** — padahal `/api/v1/sop/*` pernah tercatat 23 kali
sebelumnya, jadi middleware-nya terbukti menjangkau.

Artinya kerja sisi server tetap di bawah 3 detik; selisih ~4 detik itu **antrean
`FcgidMaxProcesses 50`** di Apache — dan itu justru yang **tidak berlaku** untuk
Cloud Run, yang menggandakan kontainer.

Dua korban yang tercatat justru bukan endpoint SOP: `homepage/personal/bootstrap`
5.956 ms dan `upcoming-schedule` 4.129 ms, milik orang lain yang sedang memakai
dev. Beban ini memang terasa oleh pengguna lain.

### Pembedahan — kenapa 15,9 detik

| | |
|---|---|
| Kueri per orang | **6,1** (terukur: 20 orang = 122 kueri) |
| Waktu kueri | 7.360 ms dari 7.490 ms dinding — **98%** |
| Satu `SELECT 1` ke DB dev | **72 ms** |

Bukan kuerinya yang mahal — semuanya sepele. Yang mahal **jumlah bolak-baliknya**.
6,1 × 49 × 72 ms ≈ 15,9 detik, cocok persis.

### Perbaikan — pencabutan berbasis himpunan

`cabutSatuDetail()` per baris → `cabutBanyakDetail()` untuk sekumpulan detail:

```
sebelum  122 kueri  7.490 ms   (6,1 per orang)
sesudah    8 kueri    572 ms   (0,4 per orang)
```

Perilaku dikunci lebih dulu oleh `CabutMassalTest`, ditulis **sebelum**
perubahan — dan uji itu langsung menangkap dugaan yang salah: `Total_Target`
turun sebanyak **baris progres**, bukan jumlah orang.

Rollup dikelompokkan per **(paket, versi item)**, bukan per versi saja:
`cabutAkses()` mencabut satu orang lintas paket, dan versi dokumen yang sama bisa
disajikan beberapa paket.

### Jalan 2 — hijau palsu, dan ini yang paling layak diingat

Percobaan pertama bentuk terkeras melaporkan **"LULUS keenam kriteria"**. Datanya:

```
ditugaskan=0   dicabut=0   transaksi: 294 ms, 67 ms
```

Paket bacanya sudah ditugaskan ke semua orang, jadi seluruh sasaran dilewati dan
pencabutan menyasar header kosong. **Angkanya sempurna karena tak ada yang
terjadi.**

Laporan sekarang **menggugurkan jalan yang tak menyentuh satu baris pun** —
operasi massal yang tidak melakukan apa-apa selalu memberi nol blocking dan
transaksi sekejap, dan tanpa penjaga itu jalan kosong tak bisa dibedakan dari
jalan yang aman.

### Jalan 3 — bentuk terkeras, sah

Operasi massal menghantam paket yang **persis sedang dibaca** 50 permintaan
serentak. Sasarannya karyawan tanpa akun login, sehingga tak ada pembaca yang
kehilangan akses di tengah jalan dan yang tersisa untuk diukur murni soal kunci.

| Fase | n | p95 | gagal | blocking | Δ `LCK_M_S` |
|---|--:|--:|--:|--:|--:|
| dasar | 320 | 7.973 ms | 0 | 0 | 0 |
| assign | 270 | 7.958 ms | 0 | 0 | 0 |
| cabut | 315 | 8.181 ms | 0 | 0 | 0 |

18 ditugaskan, 18 dicabut. **Nol blocking.** Transaksi massal **3.190 ms dan
640 ms** — dari 8.079 / 15.872 ms sebelum perbaikan.

## Yang TIDAK dijawab

- **Jalur antrean.** Penugasan di atas 30 orang dilempar ke `ProsesBulkAssignSop`
  lewat Cloud Tasks; uji ini memanggil layanannya langsung, yaitu jalur inline.
  Cloud Tasks mendorong lewat HTTP dari Google dan tak bisa menjangkau laptop —
  **hanya bisa dibuktikan di staging.**
- **Saturasi koneksi Cloud SQL** dan throughput lapis web. Laragon ≠ Cloud Run.
- Populasi dev membatasi skalanya: **58 karyawan aktif, 35 punya akun login.**

## Menjalankan ulang

```bash
UAT_AUTO_ALLOW=1 php artisan --env=uat sop:beban-jalan --serentak=50 --detik=60 --karyawan=50
UAT_AUTO_ALLOW=1 php artisan --env=uat sop:beban-jalan --serentak=50 --detik=60 --karyawan=50 --paket-sama
php artisan sop:beban-laporan
UAT_AUTO_ALLOW=1 php artisan --env=uat sop:uat-bersihkan --semua
```

Untuk staging: ganti `UAT_BASE_URL`. Sesinya tetap bekerja karena
`SESSION_DRIVER=database` menulis ke basis data yang sama.
