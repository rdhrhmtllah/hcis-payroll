# UAT — Satu Alur Utuh: Permit Kendaraan Karyawan

Satu permit diikuti dari pengajuan sampai dicabut. Dua peran saja: **pemohon**
(karyawan biasa) dan **SHE/admin** (B6 Andre, C9 Arya, atau H45 Ridho — ketiganya
memegang kunci verifikator dan monitoring sekaligus).

**Basis URL:** `http://hcis-w-3.test` · dev siap: 3 kategori, 3 durasi, 31 slot
inspeksi 7 hari ke depan.

Siapkan satu hal sebelum mulai: catat **nomor permit** yang muncul di langkah 2.
Seluruh langkah berikutnya memakai nomor itu. Di bawah ditulis `PMT…`.

Perintah pemeriksa dipakai berulang, jadi taruh di terminal terpisah:

```bash
cd C:/laragon/www/HCIS-W-3
php artisan tinker --execute="dump((array) DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi','PMT…')->first());"
```

> **Kedaluwarsa berjalan sendiri tiap 5 menit** (`visitor:expire-efektif` aktif
> di `N_HRIS_Scheduler_Configs`). Kalau status berubah saat Anda mengamati,
> itu bukan bug.

---

## Tahap 1 — Pemohon mengajukan

**1.** Login sebagai **karyawan biasa** → buka `/transaksipermit`.

Harus terlihat tiga tab: **Pass Digital**, **Buat Baru**, **Riwayat Permit**.

**2.** Tab **Buat Baru** → isi kendaraan (plat, jenis, model, warna), pilih
**kategori** dan **durasi**, lalu pilih **slot inspeksi** dari daftar jadwal →
**Submit**.

- **Harus:** tersimpan, muncul di **Riwayat Permit** berstatus menunggu, dan
  jadwal inspeksinya tertera.
- **Catat nomor permitnya.**
- **Tidak boleh:** daftar slot kosong. Kalau kosong, SHE belum mengatur pos/shift.

---

## Tahap 2 — Akses palang SEBELUM disetujui

Inilah bagian yang paling baru. Pemohon perlu bisa masuk gerbang untuk menemui
SHE, padahal permitnya belum diputus.

**3.** Jalankan perintah pemeriksa di atas dengan nomor permit Anda.

**Harus:**

| Kolom | Nilai |
|---|---|
| `Status_Efektif` | `AKTIF` |
| `Nama_Durasi` | `Akses Inspeksi` |
| `Nama_Kategori_Permit` | `AKSES INSPEKSI - BELUM DISETUJUI` |
| `Waktu_Mulai_Berlaku` | jam slot **dikurangi 15 menit** |
| `Waktu_Berakhir` | jam slot selesai **ditambah 15 menit** |
| `Flag_Sync` | `T` |
| `Flag_Batal` | `T` |

Artinya: gerbang hanya membuka pada jendela sempit di sekitar jam inspeksi.
Lewat jam itu, aksesnya mati sendiri tanpa ada yang perlu membersihkan.

**4.** Kembali ke `/transaksipermit` → tab **Pass Digital**.

**Harus:** jadwal inspeksi yang barusan dipesan **tampil** di sana. Kalau kosong,
pastikan `npm run dev` sedang berjalan (bundle lama tidak punya bagian ini).

---

## Tahap 3 — Pemohon menggeser jadwal

**5.** Masih sebagai pemohon, pindahkan jadwal inspeksi ke slot lain.

**Harus:** jadwal berubah, dan di tabel proyeksi **jumlah barisnya tetap satu** —
jendelanya bergeser di baris yang sama, `Flag_Sync` kembali `T`.

```bash
php artisan tinker --execute="echo DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi','PMT…')->count();"
```

**Harus: `1`.** Kalau jadi 2, ada jalur yang menambah baris alih-alih menimpa.

---

## Tahap 4 — SHE menyetujui

**6.** Login sebagai **B6 / C9 / H45** → `/verifikator-visitor/permit` → buka
pengajuan tadi → isi checklist inspeksi → **Approve**.

**7.** Jalankan lagi perintah pemeriksa.

**Harus:** baris yang **sama** (tetap satu) kini berubah:

| Kolom | Dari | Menjadi |
|---|---|---|
| `Nama_Kategori_Permit` | `AKSES INSPEKSI - BELUM DISETUJUI` | kategori asli, mis. `HIJAU` |
| `Nama_Durasi` | `Akses Inspeksi` | durasi asli, mis. `3 Bulan` |
| `Waktu_Berakhir` | jam slot + 15 mnt | akhir masa berlaku penuh |
| `Flag_Sync` | `Y` (kalau sudah ditarik) | **`T` lagi** |

`Flag_Sync` kembali `T` itu yang penting — sebelum perbaikan, persetujuan tidak
pernah sampai ke aplikasi palang, tanpa satu pun galat.

---

## Tahap 5 — Kartu dicetak

**8.** Login lagi sebagai **pemohon** → `/transaksipermit` → tab **Pass Digital**
→ **Cetak**.

**Harus:**

- PDF terbuka di penampil berkas dan bisa diunduh.
- Tata letak **Template 2**: bar jenis permit rata kiri, blok plat tidak menempel
  ke bar di atasnya.
- **Ukuran berkas ±22 KB**, bukan ratusan KB.
- Ada **QR** di kartunya.

**9.** Bandingkan tata letak lain: tambahkan `?template=1` di ujung URL kartu.

---

## Tahap 6 — Keaslian kartu

**10.** Scan QR kartu itu dengan **kamera HP biasa / Google Lens**.

**Harus:** yang muncul hanya **32 karakter heksadesimal**. Bukan tautan, bukan
nomor permit, dan tidak ada halaman yang terbuka.

**11.** Masih sebagai pemohon: tombol **Cek Keaslian Kartu Permit** di
`/transaksipermit` → scan kartunya sendiri.

**Harus:** **KARTU SAH**, identitas dan plat tampil.

**12.** Sekarang scan kartu **milik karyawan lain** (minta satu kartu lain, atau
cetak dari akun lain).

**Harus:** ditolak dengan **BUKAN KARTU ANDA**, dan **tidak satu pun** data
identitas ikut tampil — tidak plat, tidak nama, tidak nomor permit.

**13.** Login sebagai **admin** → scanner di `/monitoring-permit`, scan kartu
milik siapa pun.

**Harus:** **boleh** — inilah beda kedua pintu itu.

---

## Tahap 7 — Sisi admin

**14.** `/monitoring-permit` → cari permit tadi → **cetak kartunya**.

**Harus:** berkas sama persis dengan yang dicetak pemohon.

**15.** Centang **beberapa baris** → **Cetak Bundel**.

**Harus:** satu PDF, **2 kartu per halaman A4**. Kalau ada baris tak aktif ikut
tercentang, ia **dilewati diam-diam** — bukan menggagalkan seluruh cetakan.

**16.** Pakai **filter tanggal**, mode **tanggal approve**, lalu ganti ke mode
**masa berlaku**, rentang yang sama.

**Harus:** hasil kedua mode **berbeda**. Permit yang disetujui bulan ini untuk
masa berlaku bulan depan akan muncul di mode approve dan hilang di mode berlaku.
Ketik tanggal ngawur → **diabaikan**, bukan galat, bukan hasil kosong.

---

## Tahap 8 — Aplikasi palang menarik data

**17.** Tiru aplikasi pihak ketiga menarik data:

```bash
php artisan visitor:simulasi-palang
```

**18.** Periksa lagi.

**Harus:** `Flag_Sync` menjadi **`Y`** — artinya sudah tersinkron, tidak ada
kerjaan baru untuk gerbang.

**19.** Sekarang ubah sesuatu pada permit itu dari HCIS (mis. ralat plat lewat
meja verifikator), lalu periksa lagi.

**Harus:** `Flag_Sync` kembali **`T`**. Perubahan pada baris yang sudah pernah
ditarik wajib menyalakan ulang penanda itu; kalau tidak, gerbang terus memakai
data lama tanpa ada yang tahu.

---

## Tahap 9 — Pencabutan

**20.** Login sebagai **SHE/admin** → `/verifikator-visitor/permit` → **cabut**
permit tadi, isi alasannya.

**21.** Periksa lagi.

**Harus:** `Status_Efektif` = **`DIBATALKAN`**, `Flag_Batal` = **`Y`**,
`Flag_Sync` = **`T`**, dan `Alasan_Status` berisi alasan yang Anda ketik.

**22.** Di layar HCIS, status permitnya harus terbaca **Dicabut**.

**23.** Scan **kartu kertas** yang tadi sudah dicetak.

**Harus:** terbaca **tidak berlaku beserta sebabnya**, walaupun kertasnya mulus.
Inilah inti keamanannya: pemeriksa membaca keadaan terkini dari basis data,
bukan isi kertas.

**24.** Coba **cetak ulang** kartu permit yang sudah dicabut itu.

**Harus:** **ditolak** — permit yang tidak aktif tidak punya wujud cetak.

---

## Penutup — pemeriksaan konsistensi

**25.** Terakhir, pastikan tidak ada baris yang benderanya bertentangan:

```sql
-- keduanya HARUS 0
SELECT COUNT(*) FROM N_VISITOR_Permit_Efektif
 WHERE (Status_Efektif = 'DIBATALKAN' AND Flag_Batal <> 'Y')
    OR (Status_Efektif <> 'DIBATALKAN' AND Flag_Batal = 'Y');

SELECT COUNT(*) FROM N_VISITOR_Tamu_Efektif
 WHERE (Status_Efektif = 'DIBATALKAN' AND Flag_Batal <> 'Y')
    OR (Status_Efektif <> 'DIBATALKAN' AND Flag_Batal = 'Y');
```

Kalau salah satu tidak nol, catat `No_Transaksi`-nya dan aksi mana yang
mendahuluinya — itu cukup untuk melacaknya.

---

## Kalau ada yang meleset

Sebut **nomor langkahnya** saja. Tiap langkah di atas hanya menguji satu hal,
jadi nomornya sudah menunjuk ke satu tempat di kode.
