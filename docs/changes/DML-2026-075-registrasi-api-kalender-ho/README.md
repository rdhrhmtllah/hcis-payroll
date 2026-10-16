# DML-2026-075 — Registrasi API Kalender Operasional HO

| | |
|---|---|
| Change ID | `DML-2026-075-registrasi-api-kalender-ho` |
| Target database | `Web_HRIS` (development) — production menyusul lewat SQL Tracker |
| Jenis | DML saja; **tidak ada DDL, tidak ada tabel baru** |
| Status | `DEV_EXECUTED` — dieksekusi di development 2026-09-04; `TRACKER_PENDING` untuk production |
| Skrip | `database/dml/DML-2026-075-registrasi-api-kalender-ho/data-change.sql` |
| Spec | `docs/superpowers/specs/2026-09-04-api-kalender-operasional-ho-design.md` |
| Rencana | `docs/superpowers/plans/2026-09-04-api-kalender-operasional-ho.md` |

## Tujuan

Mendaftarkan Web Careers sebagai konsumen API kalender operasional Head Office,
dan menyiapkan data uji Head Office di development.

## Objek yang tersentuh

| Tabel | Operasi | Perkiraan baris |
|---|---|---|
| `N_HRIS_Api_Aplikasi` | INSERT | 1 |
| `N_HRIS_Api_Endpoint` | INSERT | 2 |
| `N_HRIS_Api_Akses_Endpoint` | INSERT | 2 |
| `Karyawan` | UPDATE (**development saja**) | 12 |
| `HRIS_Hari_Libur` | INSERT (**development saja**) | 3 |
| `N_HRIS_Cuti_Bersama` | INSERT (**development saja**) | 6 |
| `N_HRIS_Cuti_Bersama_Exclusion` | INSERT (**development saja**) | 2 |

Seluruh INSERT bersifat idempoten — dijaga `IF NOT EXISTS` / `NOT EXISTS`, jadi
menjalankan ulang tidak menggandakan baris.

## Yang perlu diperhatikan sebelum eksekusi

**Kunci API.** `Api_Public` dan `Api_Secret` dibangkitkan saat eksekusi dan diisi
ke dua variabel di kepala skrip. Skrip menolak berjalan bila keduanya masih
placeholder. Kunci tidak pernah masuk repo; serahkan ke Web Careers lewat jalur
rahasia.

**Pola rute, bukan jalur konkret.** `Url_Route` untuk endpoint tahun berisi
`/api/v1/hcis/kalender-operasional/tahun/{tahun}` — dengan kurung kurawal apa
adanya. `HrisApiGuard` mencocokkan whitelist ke `route()->uri()`, sehingga satu
baris melayani semua tahun. Diisi `.../tahun/2026` berarti whitelist tidak cocok
untuk tahun mana pun.

**BAGIAN 4 hanya untuk development.** Ia mengubah `Karyawan.Cabang_Untuk_Gaji`
menjadi `HEAD OFFICE EVO` supaya API bisa diuji dengan populasi yang masuk akal —
master dev nyaris tidak punya nilai kolom ini, sehingga hanya satu karyawan yang
terdeteksi Head Office. Dua belas kode yang dipilih **seluruhnya karyawan
sintetis** (`UJI0003`–`UJI0036`, bernama "… PRATAMA"/"… WIJAYA"); tidak ada
karyawan sungguhan yang disentuh. `SELECT` perekam nilai lama wajib dijalankan
dan hasilnya disimpan sebelum `UPDATE`, dan `UPDATE`-nya hanya mengenai baris
yang memang masih kosong.

**Jangan bawa BAGIAN 4 dan 5 ke production.** Penentuan Head Office di production
harus datang dari master yang benar, bukan dari data uji.

## Kalender dummy 2027 (BAGIAN 6)

Tahun 2027 dipilih karena di dev benar-benar kosong — nol hari libur, nol cuti
bersama, nol absensi — sehingga 2026 tetap berisi data asli untuk dibandingkan.
Setiap baris dummy menguji satu cabang aturan, dan yang **tidak** muncul sama
pentingnya dengan yang muncul.

`GET .../tahun/2027` menghasilkan tepat 6 tanggal:

| Tanggal | Jenis | Nama |
|---|---|---|
| 2027-01-01 | `LIBUR_NASIONAL` | UJI Tahun Baru 2027 |
| 2027-03-15 | `LIBUR_NASIONAL` | UJI Libur Nasional Contoh |
| 2027-03-16 | `CUTI_BERSAMA` | UJI CB Sekantor Tanpa Filter |
| 2027-03-17 | `CUTI_BERSAMA` | UJI CB Sekantor Tanpa Filter |
| 2027-04-05 | `CUTI_BERSAMA` | UJI CB Khusus Head Office |
| 2027-12-25 | `LIBUR_NASIONAL` | UJI Hari Raya Natal 2027 |

Yang sengaja **tidak** muncul, masing-masing menguji satu penolakan:

- `2027-04-06` — cuti bersama hanya untuk cabang EMI, bukan HO
- `2027-04-07` — menyasar 2 karyawan HO tapi keduanya ada di tabel pengecualian
- `2027-04-08` — dibatalkan (`Flag_Cancelled = 'Y'`)
- `2027-03-15` — cuti bersama bentrok libur nasional; terbit sebagai `LIBUR_NASIONAL`

Seluruh baris dummy `Flag_Executed = 'T'`, `Scheduled_At` dan `Cloud_Task_Name`
`NULL`. Tidak ada penjadwal maupun job yang memindai `N_HRIS_Cuti_Bersama`
(diperiksa sebelum eksekusi), jadi tak satu pun baris ini akan memotong saldo
cuti siapa pun.

## Hasil eksekusi development (2026-09-04)

Dijalankan terhadap `Web_HRIS`, target diverifikasi lewat `SELECT DB_NAME()`.

- BAGIAN 1–3: 1 aplikasi (`Id_Api_Aplikasi = 2`), 2 endpoint (`Id = 2, 3`), 2 baris akses
- BAGIAN 4: 12 baris `Karyawan` — seluruh nilai lama terverifikasi kosong sebelum
  UPDATE dan tersimpan di luar repo; karyawan HO aktif kini 13 (12 sintetis + `R52`)
- BAGIAN 6: 3 hari libur, 6 cuti bersama, 2 pengecualian

Verifikasi ujung-ke-ujung dengan permintaan **bertanda tangan sungguhan** lewat
HTTP kernel: kedua endpoint 200 dengan isi persis seperti tabel di atas; tanda
tangan rusak, public key tak dikenal, dan permintaan tanpa header ditolak 401;
nonce yang diulang ditolak 401; galat kontrak 400 keluar dengan pesan yang benar.

Catatan kecil yang sudah ada sebelumnya: `N_HRIS_Api_Log` mencatat status dari
sudut pandang guard, sehingga permintaan yang lolos guard lalu ditolak controller
dengan 400 tetap tercatat 200. Perilaku ini milik guard dan tidak diubah di sini.

## Pekerjaan yang tersisa untuk pengguna

1. Serahkan pasangan kunci ke Web Careers lewat jalur rahasia (tersimpan di luar repo)
2. Catat **BAGIAN 1–3 saja** di SQL Tracker, lalu eksekusi ke production dari sana
3. Verifikasi production, baru status boleh naik ke `COMPLETED`
4. Bila data uji dev sudah tidak diperlukan, jalankan BAGIAN 5 dan 7

**BAGIAN 4, 5, 6, dan 7 tidak boleh masuk SQL Tracker.** Membawanya ke production
berarti mengarang hari libur perusahaan dan mengubah master karyawan.
