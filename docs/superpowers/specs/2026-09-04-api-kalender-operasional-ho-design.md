# API Kalender Operasional Head Office — Desain

- Tanggal: 2026-09-04
- Modul: `ESS` → submodul `Attendance` → fitur `Calendar/Operational`
- Konsumen: Web Careers (spesifikasi dari mereka, 2 September 2026)
- Status: **terimplementasi** 2026-09-04 (kode + uji selesai; DML belum dieksekusi)
- Rencana: `docs/superpowers/plans/2026-09-04-api-kalender-operasional-ho.md`

## 1. Tujuan

Web Careers menghitung SLA rekrutmen dalam satuan **hari kerja**
(`N_WEB_CAREERS_Master_Sla_Mpp.Hari_Kerja`, `N_WEB_CAREERS_Detail_MPP.Sla_Hari_Kerja`).
Untuk itu mereka perlu tahu tanggal berapa saja Head Office tidak beroperasi.
HCIS memegang datanya; API ini menyajikannya sebagai kontrak yang stabil, supaya
Web Careers tidak perlu ikut memahami skema internal kami.

Dua endpoint baca-saja:

1. `GET /api/v1/hcis/kalender-operasional/tahun/{tahun}` — kalender setahun
2. `GET /api/v1/hcis/kalender-operasional?dari=&sampai=` — kalender per rentang

## 2. Basis verifikasi database

Wajib dicatat menurut `AGENTS.md`. Semua angka di bawah adalah snapshot bertanggal,
bukan otoritas; task berikutnya memverifikasi ulang.

- Lingkungan: **development**, `Web_HRIS` @ `team311.dyndns.info:1433`
  (diverifikasi lewat `SELECT DB_NAME()`)
- Tanggal verifikasi: **2026-09-04**
- Grup fitur aktif di `N_HRIS_Master_Grup_Fitur`: **7** — `ESS`, `KPI`, `LMS`,
  `VISITOR`, `SYSTEM`, `BUILDER`, `DMS`. Catatan: `CLAUDE.md` masih menyebut lima
  grup (snapshot lama); `BUILDER` dan `DMS` sudah aktif. `ESS` (`Id_Grup_Fitur = 1`,
  `Flag_Aktif = 'Y'`) adalah pemilik fitur ini.
- Fitur ini **tidak punya halaman**, jadi tidak ada baris `N_HRIS_Grup_Fitur_Map`
  dan tidak ada `Jenis_page`. Kepemilikan modul dipakai untuk penempatan berkas saja,
  seperti integrasi pihak ketiga LMS yang sudah ada.
- Pekerjaan DDL/DML lain yang masih menggantung saat spec ini ditulis: 79 entri
  belum `COMPLETED` di `database/ddl/status.json`. Tidak satu pun beririsan dengan
  fitur ini.

### Tabel sumber (apa adanya di dev)

| Tabel | Bentuk yang relevan |
|---|---|
| `HRIS_Hari_Libur` | 3 kolom: `Kode_Perusahaan varchar(3)`, `Tanggal datetime`, `Nama_Hari_Libur varchar(50)`. 29 baris, satu-satunya `Kode_Perusahaan` adalah `001`, tanpa duplikat tanggal. **Tidak ada penanda unit/cabang apa pun.** |
| `N_HRIS_Cuti_Bersama` | `Selected_Dates` (JSON array tanggal), `Tanggal_Mulai`, `Tanggal_Selesai`, `Flag_Aktif`, `Flag_Cancelled`, `Flag_Executed`, `Scheduled_At`, dan empat filter sasaran: `Filter_Cabang`, `Filter_Divisi`, `Filter_Sub_Divisi`, `Filter_Kode_Karyawan` (semuanya `nvarchar(max)` berisi JSON array). |
| `N_HRIS_Cuti_Bersama_Exclusion` | `Id_Cuti_Bersama`, `Kode_Karyawan` — karyawan yang dikecualikan dari satu cuti bersama. |
| `Karyawan` | `Cabang_Untuk_Gaji`, `ID_Divisi`, `ID_Sub_Divisi`, `Aktif`, `Tanggal_Resign`. |

Isi filter penting untuk diketahui: `Filter_Cabang` menyimpan **nilai**
`Karyawan.Cabang_Untuk_Gaji` (mis. `"HEAD OFFICE EVO"`), sedangkan `Filter_Divisi`
dan `Filter_Sub_Divisi` menyimpan **ID**, bukan nama.

## 3. Keputusan yang mengunci desain

### 3.1 Penanda Head Office

HO ditentukan dari **`Karyawan.Cabang_Untuk_Gaji`** (keputusan pemilik produk).
Pola pencocokan disimpan di config, bukan di kode, sehingga bisa disetel saat master
dibersihkan tanpa deploy ulang.

Di dev per 2026-09-04 hanya satu karyawan aktif yang bernilai `HEAD OFFICE EVO`
(901 karyawan aktif kolomnya kosong). Data uji akan disiapkan terpisah — lihat §9.

### 3.2 Mengapa pencocokan kata pada nama cuti bersama ditolak

Rencana awal adalah mencari kata "Head Office" pada data cuti bersama. Itu ditolak
karena **melewatkan mayoritas kasus**: di dev, hampir semua cuti bersama filternya
kosong. Filter kosong berarti berlaku untuk **seluruh** karyawan — termasuk HO —
padahal tak ada kata "Head Office" di mana pun pada baris tersebut. Pencocokan kata
akan melaporkan HO masuk kerja pada hari kantor justru tutup, dan SLA Web Careers
akan berjalan di hari libur.

Gantinya, dipakai aturan sasaran yang sudah ada dan sudah dipakai produksi.

### 3.3 `Flag_Executed` sengaja tidak jadi syarat

`Flag_Executed` menandai **saldo cuti sudah dipotong**, bukan kantor tutup. Cuti
bersama yang sudah ditetapkan tapi belum dieksekusi tetap hari libur. Kalau
eksekusi dijadikan syarat, kalender akan menyembunyikan hari libur yang sudah pasti
dan Web Careers menghitung SLA berjalan di hari kantor tutup.

### 3.4 `status` cuti bersama dari tanggal

`TERJADWAL` bila tanggalnya di masa depan, `BERJALAN` bila hari ini, `SELESAI` bila
sudah lewat — relatif terhadap tanggal server (`Asia/Jakarta`). Bukan dari
`Flag_Executed`/`Scheduled_At`, karena keduanya bicara soal pemotongan saldo, bukan
soal kalender.

## 4. Aturan penentu (inti fitur)

Sebuah cuti bersama menutup HO pada tanggal *T* bila **semua** syarat berikut benar:

1. `Flag_Aktif = 'Y'` **dan** `Flag_Cancelled <> 'Y'`
2. *T* ada di daftar tanggalnya. Daftar dibentuk dengan aturan yang sudah dipakai
   `CutiController::normalizeCutiBersamaDates`: pakai `Selected_Dates` bila terisi;
   bila kosong barulah rentang `Tanggal_Mulai..Tanggal_Selesai` dibentangkan
   (tanggal terbalik ditukar; `Tanggal_Selesai` kosong berarti satu hari)
3. Ada **minimal satu** karyawan HO yang tersasar. Karyawan HO adalah yang
   `Aktif = 'Y'`, `Tanggal_Resign` kosong, dan `Cabang_Untuk_Gaji` cocok penanda HO.
   Tersasar mengikuti aturan yang sudah ada di
   `cutiBersamaService.php:256` (`isKaryawanTargetedForQueuedCutiBersama`):

   ```
   tersasar = ada di Filter_Kode_Karyawan
            ATAU (ada filter unit DAN cocok Filter_Cabang DAN cocok Filter_Divisi
                  DAN cocok Filter_Sub_Divisi)
            ATAU (tidak ada filter kode DAN tidak ada filter unit)
   ```

   Cabang klausa terakhir itulah yang menangkap kasus terbanyak: cuti bersama tanpa
   filter berlaku sekantor.
4. Karyawan itu **tidak** ada di `N_HRIS_Cuti_Bersama_Exclusion` untuk
   `Id_Cuti_Bersama` tersebut.

Bila seluruh karyawan HO yang tersasar ternyata dikecualikan, cuti bersama itu tidak
menutup HO.

Aturan ini ditulis satu kali di `PenentuTanggalTidakOperasional` dan tidak disalin
ke tempat lain.

## 5. Pemetaan keluaran

| `jenis` | Sumber | Catatan |
|---|---|---|
| `LIBUR_NASIONAL` | seluruh baris `HRIS_Hari_Libur` pada rentang | `Kode_Perusahaan` diabaikan, konsisten dengan semua pemakai tabel ini yang sudah ada. `nama` dari `Nama_Hari_Libur` |
| `CUTI_BERSAMA` | aturan §4 | `nama` dari `Nama_Cuti_Bersama` (kosong → `"Cuti Bersama"`); `status` per §3.4 |
| `LIBUR_PERUSAHAAN` | — | **Tidak ada sumbernya di database.** Nilai tetap didokumentasikan di kontrak sebagai cadangan, tapi tidak akan pernah terbit sampai ada tabelnya |

Aturan penggabungan:

- Urut menaik menurut `tanggal`
- **Tanpa duplikat tanggal.** Bila satu tanggal sekaligus libur nasional dan cuti
  bersama, terbit satu entri saja dan `LIBUR_NASIONAL` menang
- Bila beberapa cuti bersama jatuh pada tanggal sama, dipilih satu — yang
  `Id_Cuti_Bersama`-nya terkecil, supaya keluarannya deterministik

`hari_kerja_mingguan` berasal dari `config/kalender_operasional.php`
(`HEAD_OFFICE => [1,2,3,4,5,6]`); tak ada sumber database untuk ini.
`digenerate_at` adalah ISO 8601 dengan offset `+07:00` (`Asia/Jakarta`).

## 6. Kontrak

Bungkus respons memakai kunci **`sukses`**, bukan `success`. Karena itu
`ResponseHelper` **tidak** dipakai di jalur ini — kontrak eksternal tidak boleh ikut
berubah saat helper internal diubah. Penjawabnya khusus untuk endpoint ini.

### 6.1 Sukses (200)

```json
{
  "sukses": true,
  "message": "Kalender operasional 2026",
  "result": {
    "unit": "HEAD_OFFICE",
    "tahun": 2026,
    "hari_kerja_mingguan": [1, 2, 3, 4, 5, 6],
    "digenerate_at": "2026-09-02T08:00:00+07:00",
    "tanggal_tidak_operasional": [
      { "tanggal": "2026-03-31", "jenis": "LIBUR_NASIONAL", "nama": "H-1 Idul Fitri" },
      { "tanggal": "2026-04-02", "jenis": "CUTI_BERSAMA", "nama": "Cuti Bersama Idul Fitri", "status": "TERJADWAL" }
    ]
  }
}
```

Endpoint rentang mengganti `tahun` dengan `dari` dan `sampai` pada `result`, dan
`message` berbunyi `"Kalender operasional {dari} s/d {sampai}"`. Field `status` hanya
ada pada entri `CUTI_BERSAMA`.

### 6.2 Galat (400)

```json
{ "sukses": false, "message": "Parameter 'dari' wajib diisi (format YYYY-MM-DD).", "result": null }
```

Kondisi yang menghasilkan 400:

| Kondisi | Pesan |
|---|---|
| `dari` kosong | `Parameter 'dari' wajib diisi (format YYYY-MM-DD).` |
| `sampai` kosong | `Parameter 'sampai' wajib diisi (format YYYY-MM-DD).` |
| format tanggal salah | `Parameter '{nama}' harus berformat YYYY-MM-DD.` |
| `sampai < dari` | `Parameter 'sampai' tidak boleh lebih awal dari 'dari'.` |
| rentang > 400 hari | `Rentang tanggal maksimal 400 hari.` |
| `unit` tak dikenal | `Unit '{nilai}' tidak dikenal.` |
| `tahun` di luar 2020–2100 | `Parameter 'tahun' harus 4 digit antara 2020 dan 2100.` |

Rentang inklusif di kedua ujung. Rentang tanpa libur bukan galat: 200 dengan
`"tanggal_tidak_operasional": []`.

`unit` opsional, default `HEAD_OFFICE`; satu-satunya nilai yang dikenal hari ini.
Unit baru cukup menambah entri config.

## 7. Autentikasi

> **Revisi 2026-09-04, setelah spec disetujui.** Rencana awal membuat
> `IntegrationApiGuard` baru berisi langkah 1–8 `LmsApiGuard`. Ternyata kelas itu
> **sudah ada**: `App\Http\Middleware\HrisApiGuard` (alias `hris.api.guard`, sudah
> terdaftar di `app/Http/Kernel.php:88`), dengan registry generiknya sendiri
> `N_HRIS_Api_Aplikasi` / `N_HRIS_Api_Endpoint` / `N_HRIS_Api_Akses_Endpoint` /
> `N_HRIS_Api_Nonce` / `N_HRIS_Api_Log` — semuanya sudah ada di dev. Maka **tidak ada
> middleware baru** dan **tidak ada registry `N_LMS_*`** yang dipakai; catatan utang
> penamaan di bawah gugur dengan sendirinya. Bagian ini ditulis ulang sesuai kenyataan.

Memakai `hris.api.guard` yang sudah ada. Skemanya sama persis dengan integrasi LMS,
sehingga kode penandatangan di sisi Web Careers identik dengan yang sudah ada:

- HMAC-SHA512 atas `METHOD \n PATH \n QUERY \n TIMESTAMP \n NONCE \n BODY_HASH \n PUBLIC_KEY`
- Header: `X-HC-Public`, `X-HC-Signature`, `X-HC-Timestamp`, `X-HC-Nonce`, `X-HC-Body-Hash`
- Timestamp kedaluwarsa 60 detik; nonce sekali pakai (`N_HRIS_Api_Nonce`)
- Wajib HTTPS di production
- Whitelist endpoint per aplikasi lewat `N_HRIS_Api_Akses_Endpoint` × `N_HRIS_Api_Endpoint`
- Setiap percobaan tercatat di `N_HRIS_Api_Log`
- Untuk `GET`, `X-HC-Body-Hash` adalah `sha256("")`

Middleware `lms.api.guard` **tidak bisa dipakai**: langkah ke-9-nya mewajibkan
`Kode_Karyawan`, `Id_Penjadwalan_Integrasi`, dan `Id_Ujian_Token` di body, sehingga
setiap `GET` kalender ditolak 422 sebelum mencapai controller. `hris.api.guard` adalah
padanannya tanpa langkah itu. `LmsApiGuard` tidak disentuh sama sekali.

### 7.1 Perbaikan wajib: jalur bertanda tangan pada rute berparameter

`HrisApiGuard` memakai `$request->route()->uri()` untuk **dua** hal sekaligus:
pencarian whitelist dan komponen `PATH` di payload tanda tangan. Untuk rute statis
keduanya sama, jadi cacat ini belum pernah terlihat — satu-satunya konsumen guard hari
ini (callback kuis SOP) jalurnya statis.

Endpoint tahun kita berparameter. `route()->uri()` menghasilkan
`api/v1/hcis/kalender-operasional/tahun/{tahun}` — **pola**, dengan kurung kurawal —
sedangkan Web Careers akan menandatangani jalur sebenarnya,
`/api/v1/hcis/kalender-operasional/tahun/2026`. Tanda tangan tidak akan pernah cocok
dan setiap permintaan ditolak 401 tanpa petunjuk penyebabnya.

Perbaikannya minimal dan dipisah perannya:

- **Payload tanda tangan** memakai jalur sebenarnya (`$request->getPathInfo()`) —
  inilah yang dilihat dan ditandatangani klien
- **Pencarian whitelist** tetap memakai pola `route()->uri()`, supaya satu baris
  registry cukup untuk semua tahun, bukan satu baris per tahun
- **Log** mencatat jalur sebenarnya, sehingga `N_HRIS_Api_Log` menunjukkan permintaan
  yang benar-benar terjadi

Untuk rute statis kedua nilai identik, jadi perubahan ini **tidak mengubah apa pun**
bagi konsumen yang ada. Uji regresi yang membuktikan hal itu wajib ada (§10 butir 16).

## 8. Arsitektur berkas

```
Baru:
  routes/ESS/Attendance/Calendar/OperationalCalendarApi.php
  app/Http/Controllers/ESS/Attendance/Calendar/Operational/KalenderOperasionalApiController.php
  app/Services/ESS/Attendance/Calendar/Operational/PenentuTanggalTidakOperasional.php
  app/Services/ESS/Attendance/Calendar/Operational/KalenderOperasionalService.php
  app/Console/Commands/ESS/Attendance/Calendar/FlushKalenderOperasionalCommand.php
  config/kalender_operasional.php
  database/dml/DML-2026-075-registrasi-api-kalender-ho/data-change.sql
  tests/Unit/ESS/Attendance/Calendar/PenentuTanggalTidakOperasionalTest.php
  tests/Feature/ESS/Attendance/Calendar/KalenderOperasionalApiTest.php
  tests/Feature/SYSTEM/Integration/HrisApiGuardJalurBertandaTanganTest.php

Diubah:
  routes/api.php                             (require berkas rute baru)
  app/Http/Middleware/HrisApiGuard.php       (§7.1, terbatas pada pemisahan jalur)
```

Tidak ada tabel baru, jadi tidak ada `database/ddl/` dan tidak ada dokumen tabel di
`docs/database/tables/`.

`routes/ESS/Attendance/Calendar/OperationalCalendarApi.php` di-`require` dari
`routes/api.php`, mengikuti pola `routes/LMS/Integration/LmsThirdPartyApi.php`.

Pembagian tanggung jawab:

- **Controller** — baca parameter, validasi kontrak, panggil service, susun bungkus
  `sukses`/`message`/`result`. Tidak ada kueri di sini.
- **`KalenderOperasionalService`** — mengambil data dari database dan mengelola cache.
  Tidak memuat aturan bisnis.
- **`PenentuTanggalTidakOperasional`** — **kelas murni tanpa database**: menerima
  larik libur, larik cuti bersama, daftar karyawan HO, dan peta pengecualian;
  mengembalikan daftar tanggal terurut. Seluruh aturan §4 dan §5 hidup di sini dan
  hanya di sini.
- **Middleware** — hanya keamanan transport.
- **Config** — penanda unit dan hari kerja mingguan.

Pemisahan penentu dari pengambil data disengaja: aturan yang paling mudah salah
justru jadi bagian yang bisa diuji tanpa menyentuh database sama sekali. Uji dev di
repo ini menembak `Web_HRIS` dan RCSI-nya mati, sehingga suite yang bergantung
database ikut mengunci UI pengembang.

Kedua endpoint memakai service yang sama; endpoint tahun hanyalah rentang
`{tahun}-01-01`..`{tahun}-12-31`.

### Beban kueri

Empat kueri per permintaan yang tidak ter-cache, semuanya bertutup rentang:

1. `HRIS_Hari_Libur` pada rentang
2. `N_HRIS_Cuti_Bersama` yang beririsan rentang dan aktif/tidak dibatalkan
3. daftar karyawan HO (`Kode_Karyawan`, `Cabang_Untuk_Gaji`, `ID_Divisi`,
   `ID_Sub_Divisi`) — hanya yang cocok penanda HO, jadi kecil
4. `N_HRIS_Cuti_Bersama_Exclusion` untuk `Id_Cuti_Bersama` yang terkumpul saja

Pencocokan sasaran dikerjakan di PHP terhadap daftar HO, bukan per baris ke database,
supaya tidak muncul pola N+1. Filter tersimpan sebagai JSON di kolom `nvarchar(max)`
sehingga tidak bisa disaring secara sargable di SQL Server; membawa daftar HO yang
kecil ke memori adalah bentuk yang benar di sini.

### Cache

Kunci `unit + dari + sampai`, TTL 15 menit, plus perintah `php artisan kalender:flush`
untuk pembersihan manual. Master libur berubah beberapa kali setahun, jadi TTL pendek
sudah cukup dan tidak menimbulkan kelas bug "kalender basi". Cache juga menahan
endpoint ini agar tidak ikut menjenuhkan koneksi database.

Menyambungkan pembersihan cache ke mutasi admin cuti bersama **di luar cakupan** —
itu menyentuh alur admin yang sedang hidup.

## 9. Data uji di development

Terpisah dan butuh persetujuan tersendiri sebelum dijalankan, karena `Karyawan`
adalah tabel master di luar tabel proyek ini.

Rencananya: `UPDATE Karyawan SET Cabang_Untuk_Gaji = 'HEAD OFFICE EVO'` untuk
sejumlah `Kode_Karyawan` yang didaftarkan lebih dulu. Sebelum eksekusi harus
ditunjukkan: daftar kode yang tepat, jumlah baris terdampak, nilai lama tiap baris,
dan skrip pengembaliannya. Semuanya masuk
`database/dml/DML-2026-075-registrasi-api-kalender-ho/data-change.sql` bersama
pendaftaran aplikasi Web Careers.

DML pendaftaran integrasi:

1. satu baris `N_HRIS_Api_Aplikasi` untuk Web Careers — `Arah = 'IN'`,
   `Id_Grup_Fitur = 1` (ESS). `Api_Public`/`Api_Secret` dibangkitkan saat eksekusi dan
   **tidak pernah ditulis ke berkas yang ter-commit**; skripnya memakai placeholder
   yang diisi manual
2. dua baris `N_HRIS_Api_Endpoint`, `Method = 'GET'`, `Arah = 'IN'`,
   `Id_Grup_Fitur = 1`, dengan `Url_Route` berisi **pola** rute:
   - `/api/v1/hcis/kalender-operasional/tahun/{tahun}`
   - `/api/v1/hcis/kalender-operasional`
3. dua baris `N_HRIS_Api_Akses_Endpoint` yang menghubungkan keduanya

`Url_Route` wajib pola, bukan jalur konkret — kalau diisi `.../tahun/2026`, whitelist
gagal cocok untuk tahun mana pun (lihat §7.1).

## 10. Pengujian

Uji fitur di `tests/Feature/ESS/Attendance/Calendar/KalenderOperasionalApiTest.php`.
Setiap penjaga harus pernah dilihat **merah** sebelum dianggap bekerja — penjaga yang
tak pernah gagal tidak membuktikan apa pun.

Perilaku yang dituntut:

1. Cuti bersama **tanpa filter** terbit sebagai tanggal tidak operasional HO
2. Cuti bersama yang hanya menyasar cabang non-HO **tidak** terbit
3. Cuti bersama yang menyasar HO lewat `Filter_Cabang` terbit
4. Cuti bersama `Flag_Cancelled = 'Y'` atau `Flag_Aktif = 'T'` tidak terbit
5. Cuti bersama yang seluruh sasaran HO-nya ada di tabel pengecualian tidak terbit
6. Cuti bersama `Flag_Executed = 'T'` **tetap** terbit (§3.3)
7. `Selected_Dates` dipakai bila terisi; rentang dibentangkan bila kosong
8. Tanggal yang sekaligus libur nasional dan cuti bersama terbit satu entri,
   `jenis = LIBUR_NASIONAL`
9. Keluaran urut menaik dan tanpa duplikat
10. `status` `TERJADWAL`/`BERJALAN`/`SELESAI` sesuai posisi tanggal terhadap hari ini
11. Ketujuh kondisi 400 di §6.2, masing-masing dengan bentuk body yang benar
12. Rentang tanpa libur balas 200 dengan array kosong
13. Endpoint tahun setara dengan rentang 1 Januari–31 Desember tahun itu
14. Permintaan tanpa header tanda tangan yang sah ditolak 401
15. Aplikasi sah yang endpoint-nya tidak di-whitelist ditolak 403
16. **Regresi §7.1**: untuk rute statis, jalur yang dipakai payload tanda tangan sama
    persis dengan pola rute — membuktikan konsumen guard yang ada tidak terpengaruh

Butir 1–13 diuji tanpa database lewat `PenentuTanggalTidakOperasional` dan lewat uji
kontrak yang mem-bypass middleware. Butir 14–16 menyentuh registry, jadi digerbangi
env seperti uji dev lain di repo ini.

Gerbang struktural `AGENTS.md` (`composer dump-autoload -o --strict-psr`,
`verify-references`, `verify-frontend-imports`, `verify-inertia-pages`,
`route-snapshot`, `console-snapshot`) dijalankan dengan baseline diambil **sebelum**
perubahan. Tidak ada perubahan frontend, jadi `verify-frontend-imports` dan
`verify-inertia-pages` diharapkan tidak berubah; `route-snapshot` bertambah dua rute
dan `console-snapshot` bertambah satu perintah — keduanya disengaja dan dijelaskan di
pesan commit.

## 11. Di luar cakupan

- Tabel kalender termaterialisasi
- Menyatukan `HrisApiGuard` dengan `LmsApiGuard` (dua registry hidup berdampingan;
  penyatuannya task tersendiri dengan regresi LMS sendiri)
- Mengaktifkan kembali rute callback SOP yang `require`-nya masih dinonaktifkan di
  `routes/api.php`
- Halaman UI apa pun untuk kalender operasional
- Sumber data `LIBUR_PERUSAHAAN`
- Pembersihan cache otomatis dari mutasi admin cuti bersama
- Membersihkan `Cabang_Untuk_Gaji` yang kosong pada 901 karyawan aktif di dev
