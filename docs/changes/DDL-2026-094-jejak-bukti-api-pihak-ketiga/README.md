# DDL-2026-094 — Jejak Bukti API Pihak Ketiga

| | |
| --- | --- |
| Change ID | `DDL-2026-094-jejak-bukti-api-pihak-ketiga` |
| Status | `DEV_EXECUTED` |
| Development | `127.0.0.1:14330 / Web_HRIS` — `DONE` |
| SQL Tracker | `PENDING` |
| Production | `PENDING` |
| Forward | `database/ddl/DDL-2026-094-jejak-bukti-api-pihak-ketiga/forward.sql` |
| Drop | `database/ddl/DDL-2026-094-jejak-bukti-api-pihak-ketiga/drop.sql` |
| Checksum forward | `fef508ae512d5f3811e56c8d459262a204434b41ebb1513b74670694bb9784e4` |

## Masalah yang dibuktikan

`N_LMS_Api_Logs` hidup di production: **1.639 baris** dari 17 April sampai 10
September 2026, seluruhnya dari konsumen **HCLEARN** ke
`/api/v1/lms/third-party/exam/complete`. Tapi tabel lama hanya membuktikan
permintaan lolos gerbang keamanan:

1. `LmsApiGuard` menulis angka `200` yang dipaku **sebelum**
   `$next($request)`. Kalau controller kemudian gagal, log tetap 200. Sebaran
   production menguatkan ini: 1.623 × 200, 7 × 422, 5 × 403, 4 × 404, dan
   **nol 5xx**.
2. Enam penolakan awal—HTTPS, header tak lengkap, timestamp kedaluwarsa,
   aplikasi tak dikenal, nonce berulang, body hash tak cocok—tidak mencatat
   baris sama sekali.
3. Endpoint yang sama menerima hasil **LMS maupun SOP**, tetapi log tidak
   menyimpan pemilik tokennya.
4. Tidak ada correlation ID atau hash payload yang bisa disebut kedua pihak
   untuk membuktikan mereka membahas permintaan yang sama.

`HrisApiGuard` punya cacat waktu pencatatan yang sama pada registry generik
`N_HRIS_Api_*`: status 200 ditulis sebelum controller dan hanya 6 dari 16 kolom
bukti diisi.

## Kontrak bukti sesudah perubahan

Setiap permintaan menghasilkan **tepat satu baris sesudah respons diketahui**.

| Bukti | Arti |
| --- | --- |
| `Correlation_Id` | ID unik yang juga dikembalikan lewat header `X-HC-Correlation-Id` |
| `Status_Code` | status HTTP yang benar-benar diterima pemanggil |
| `Signature_Valid` | NULL bila belum bisa dinilai, `T` bila HMAC salah, `Y` hanya setelah `hash_equals()` lolos |
| `Id_Api_Aplikasi` | konsumen yang public key-nya cocok; NULL bila belum dikenali |
| `Modul` | `LMS` atau `SOP`, diturunkan dari pemilik token pada endpoint HCLEARN bersama |
| `Payload_Hash` | SHA-256 body mentah; membuktikan payload sama tanpa membuka token |
| `Request_Ringkas` | body teredaksi + dipotong |
| `Response_Ringkas` | body respons teredaksi + dipotong |
| `Error_Message` | pesan 4xx/5xx yang dikembalikan |
| `Durasi_Ms` | waktu dari masuk guard sampai respons selesai |
| `Sumber_Error` | LMS log: `GERBANG`/`APLIKASI`; generic log mengikuti CHECK resmi: `AUTH`, `SIGNATURE`, `NONCE`, `CLIENT`, `SERVER`, `NONE` |

`N_LMS_Api_Logs` mendapat sembilan kolom baru: `Http_Method`, `Correlation_Id`,
`Modul`, `Durasi_Ms`, `Sumber_Error`, `Payload_Hash`, `Request_Ringkas`,
`Response_Ringkas`, `Error_Message`; plus dua CHECK (`Modul`, `Sumber_Error`) dan dua indeks penelusuran.

`N_HRIS_Api_Log` sudah memiliki seluruh kolom bukti yang diperlukan. Tidak ada
DDL tambahan untuk registry generik; `HrisApiGuard` sekarang mengisinya sesuai
CHECK yang ada (`Arah='IN'`, bukan `INBOUND`).

## Keamanan data

- Header, API secret, HMAC signature, dan body mentah tidak disimpan.
- Nama kunci yang mengandung `secret`, `password`, `token`, `signature`, atau
  `api_key` diredaksi menjadi `[REDACTED]` pada request **dan respons**.
- `Payload_Hash` dihitung sebelum redaksi, sehingga tetap bisa dibandingkan
  dengan hash milik pengirim tanpa membuka isinya.
- Gagal menulis log tidak menggagalkan permintaan bisnis; kesalahan pencatatan
  jatuh ke log berkas bersama correlation ID.
- Nonce tetap dikonsumsi sebelum controller. Respons bisnis 4xx tidak membuat
  payload yang sama dapat dimainkan ulang.

## Bug tambahan yang ditemukan saat uji nyata

### Identitas aplikasi dan signature tidak ikut berubah

Uji pertama sesudah DDL menunjukkan semua baris ber-`Id_Api_Aplikasi=NULL` dan
signature salah tercatat `Y`. Root cause: jejak awal memakai `Y`, tetapi hasil
lookup aplikasi dan `hash_equals()` tidak pernah disalin ke array jejak.

Fix: signature dimulai NULL, aplikasi diisi sesudah public key cocok, `T` hanya
pada cabang HMAC salah, dan `Y` hanya setelah HMAC lolos.

### Validasi 422 berubah menjadi 500

Permintaan bertanda tangan yang valid, token valid, tetapi tanpa `Score` lolos
gerbang lalu menjawab 500. Root cause di **dua pintu**:

```php
ResponseHelper::error($e->errors(), 422)
```

`ValidationException::errors()` adalah array, sedangkan
`ResponseHelper::error()` menerima string. TypeError itu kemudian ditangkap
guard dan berubah 500. Kedua controller diperbaiki:

- `LmsThirdPartyCallbackController`
- `SopKuisCallbackController`

Pesan validasi sekarang diratakan menjadi string; uji nyata membuktikan
`Score` hilang menjawab 422 dan log menyimpan 422 dengan sumber `APLIKASI`.

### Nilai generic log tidak mengikuti CHECK

Uji generic pertama gagal menulis karena `Arah='INBOUND'` tidak muat
`varchar(3)`, dan `Sumber_Error='APLIKASI'` ditolak CHECK. Nilai resminya dibaca
dari katalog lalu dipakai apa adanya:

- Arah: `IN` / `OUT`
- Sumber: `NONE`, `NETWORK`, `SIGNATURE`, `NONCE`, `AUTH`, `SERVER`, `CLIENT`

## Verifikasi development yang sudah dijalankan

### DDL

`forward.sql` dijalankan ke **development lokal**
`127.0.0.1:14330 / Web_HRIS` dalam tiga tahap idempoten:

1. 10 batch awal — 0 gagal;
2. setelah amend `Modul` + `Payload_Hash`, 12 batch — 0 gagal;
3. setelah CHECK `Modul` + `Sumber_Error`, 14 batch final — 0 gagal.

Tidak ada DDL dijalankan ke production.

### Efek LMS/HCLEARN

Semua uji dalam transaksi luar dan di-rollback:

| Skenario | Respons | Baris bukti |
| --- | ---: | --- |
| tanpa header keamanan | 401 | app NULL, signature NULL, `GERBANG` |
| signature salah | 401 | app=HCLEARN, signature `T`, `GERBANG` |
| body kurang | 422 | app=HCLEARN, signature `Y`, `GERBANG` |
| token tak dikenal | 404 | app=HCLEARN, signature `Y`, `GERBANG` |
| token LMS valid, `Score` hilang | 422 | `APLIKASI`, Modul=LMS, hash cocok, error tersimpan |
| token LMS valid, `Score=80` | 200 | Modul=LMS, hash cocok, result training tersimpan |

Permintaan sukses benar-benar mengeksekusi `TrainingExecutionService` dan
menghasilkan ID transaksi/progress sebelum transaksi luar di-rollback. Count
log dan nonce sama sebelum/sesudah setiap kelompok uji.

Development lokal belum punya token SOP, jadi efek `Modul=SOP` tidak dipaksakan
dengan fixture buatan. Penentuannya tetap dari pemilik token, dan penanda pada
cabang SOP serta LMS dikunci tes statis. Verifikasi SOP nyata dilakukan setelah
production punya callback SOP yang bisa dicoba.

### Efek registry generik

Semua uji rollback:

| Skenario | Respons/log | Kategori |
| --- | --- | --- |
| tanpa header | 401/401 | `AUTH`, app/signature NULL |
| signature salah | 401/401 | `SIGNATURE`, signature `T` |
| controller kalender menolak tahun 2019 | 400/400 | `CLIENT`, signature `Y` |
| controller kalender menerima tahun 2026 | 200/200 | `NONE`, signature `Y` |

Semua membawa app, grup fitur, `Arah='IN'`, dan correlation ID yang benar.
Penjaga lama `HrisApiGuardJalurBertandaTanganTest` tetap hijau: pola rute untuk
whitelist tidak tercampur dengan jalur nyata untuk HMAC.

### Tes otomatis

```text
19 tests, 76 assertions — PASS
```

Mencakup `JejakBuktiApiPihakKetigaTest`, `JejakBuktiHrisApiGuardTest`, dan
`HrisApiGuardJalurBertandaTanganTest`. Assertion baru sudah dilihat merah lewat
sabotase status 200, jalur yang melewati logger, nilai CHECK salah, penyatuan
pola/jalur, hilangnya modul/hash/redaksi, dan header korelasi.

## Data uji

Empat baris uji awal sempat tersimpan di development lokal sebelum pola
rollback dipakai. Pembersihan dibatasi tepat pada empat `Correlation_Id`:

```text
expected=4, found=4, deleted=4, remaining=0
```

Tidak ada data HCLEARN nyata lain yang dihapus. Tidak ada data production yang
disentuh.

## Batas yang tetap berlaku

- Endpoint SOP khusus
  `/api/v1/hris/third-party/sop/exam/complete` **tidak aktif** karena require di
  `routes/api.php` sengaja dikomentari. Callback SOP yang hidup tetap masuk
  lewat endpoint HCLEARN LMS bersama. Route tidak diaktifkan diam-diam.
- Production belum punya tabel `N_HRIS_Api_*`; registry generic baru aktif
  setelah Bagian 5 DDL-2026-092 dijalankan. Sampai itu terjadi,
  `HrisApiGuard` tetap membalas seperti biasa dan kegagalan pencatatan hanya
  masuk log berkas.

## Agent completed

- DDL development lokal dijalankan dan diverifikasi.
- Dua guard diubah ke pencatatan setelah respons.
- Dua controller validasi diperbaiki.
- Uji kernel bertanda tangan + rollback dan targeted suite hijau.
- Data uji lokal dibersihkan tepat.

## User tasks

1. Tinjau perubahan dan checksum forward terbaru.
2. Rekam `forward.sql` di SQL Tracker dengan checksum
   `fef508ae512d5f3811e56c8d459262a204434b41ebb1513b74670694bb9784e4`.
3. Jalankan ke production setelah approval.
4. Verifikasi satu callback HCLEARN LMS dan satu SOP nyata; simpan correlation ID
   dari header respons.
5. Naikkan status sampai `COMPLETED` setelah production terverifikasi.
