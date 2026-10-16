# Timeout callback HC Learn → `/api/v1/lms/third-party/exam/complete`

Insiden: 2026-09-01 ~14:47 WIB, `Id_Ujian_Token` 12029, peserta MAT ARIF, Score 81.
HC Learn melaporkan `cURL error 28: Operation timed out after 10002 ms with 0 bytes received`.

**Sumber bukti:** ekspor Cloud Logging `hcis log.json` (10.000 entri, revisi
`hris-00180-nhs`, jendela 14:43:28–14:54:03 WIB). Semua angka di bawah berasal
dari sana.

---

## Kesimpulan

Callback HC Learn **tidak gagal karena modul LMS**. Ia jadi korban **padamnya
seluruh HCIS selama ±7 menit (14:45–14:52 WIB)**. Penyebab padamnya: **aplikasi
tidak bisa lagi membuka koneksi ke SQL Server** ("Login timeout expired"),
seluruh proses PHP menumpuk menunggu koneksi, satu-satunya instance Cloud Run
tersumbat sampai 75 request bersamaan, dan Cloud Run baru menambah instance
kedua **4 menit** setelah kemacetan dimulai.

Bukan salah HC Learn. Bukan salah kode callback. Ini insiden infrastruktur
koneksi database yang menelan semua endpoint sekaligus.

---

## Bukti 1 — request-nya sampai, dan kita balas 401 setelah 225 detik

Tiga callback HC Learn di jendela itu:

| Mulai (WIB) | Status | Latency |
|---|---|---|
| 14:44:40 | **200** | 0,4 dtk |
| **14:47:34** | **401** | **225,5 dtk** |
| 14:48:14 | **200** | 0,5 dtk |

Yang gagal persis yang jatuh di tengah jendela padam. Dua tetangganya, sebelum
dan sesudah, sukses dalam setengah detik — jadi signature, token, whitelist,
dan `Kode_Karyawan = "MAT ARIF"` semuanya **valid**; tidak ada yang salah pada
payload HC Learn.

Baris `REQ_DIAG` untuk request itu:

```
Path=api/v1/lms/third-party/exam/complete  Http_Status=401
Total_Ms=159528   Conn_Acquire_Ms=null   Db_Query_Count=0
```

`Conn_Acquire_Ms = null` + `Db_Query_Count = 0` artinya, menurut dokumentasi
middleware-nya sendiri: **tidak ada satu pun query berjalan** — request itu
menghabiskan 159 detik gagal mendapat koneksi DB, sesudah lebih dulu antre ±66
detik sebelum PHP-nya jalan (225 dtk total menurut Cloud Run).

**Kenapa 401?** `LmsApiGuard` punya tepat dua penolakan yang tak butuh query:
header tak lengkap, dan `abs(time() - strtotime($timestamp)) > 60` →
**"Request expired"**. Header mereka lengkap. Jadi 401 itu **akibat**, bukan
sebab: saat guard akhirnya jalan, `X-HC-Timestamp` sudah basi 3 menit.

Ironisnya HC Learn tak pernah melihat 401 itu — mereka menyerah di detik ke-10,
balasan kita baru keluar di detik ke-225.

## Bukti 2 — seluruh situs padam, bukan cuma endpoint ini

Dari 1.132 request di jendela itu: **p50 0,25 dtk, p95 207 dtk, maks 301 dtk**.
109 request lebih lambat dari 10 detik. Status: 47×500, 7×502, 20×503, 1×504.

Yang paling telak: **`/apple-touch-icon-precomposed.png` kena 503 setelah 205
detik**, dan `/animation/empty-data.json` setelah 204 detik. Itu **file statis**
— tidak menyentuh database sama sekali. Kalau file statis pun ikut antre 3
menit, yang macet adalah **seluruh instance**, bukan satu query.

Ikut tumbang di jendela yang sama: `/login`, `/logout`, `/homepage`,
`/api/v1/activities`, `/homepage/notifications/unread`, `/dashboard-ceo`,
`/izin/submit`, `/perbaiki-checkinout`.

## Bukti 3 — sebabnya akuisisi koneksi, bukan query lambat

Dari 93 baris `REQ_DIAG`:

- **53 baris punya `Db_Query_Count = 0`** → gagal total mendapat koneksi.
- 40 baris sisanya: **median `Conn_Acquire_Ms` = 10.176 ms**, maksimum
  **94.089 ms**. Normalnya 16–53 ms.
- **Query nyata terlambat di seluruh jendela cuma 2,8 detik** (satu `insert` ke
  `N_HRIS_Transaksi_Shift_Det`). Tidak ada query berat yang bisa disalahkan.

`Slowest_Query_Sql` untuk request-request terparah semuanya berbunyi
`SET CONTEXT_INFO 0x...` atau `SET LOCK_TIMEOUT 60000` — yaitu **statement
pertama**, yang waktunya sudah termasuk pembukaan koneksi. Jadi 52 dtk, 55 dtk,
94 dtk itu murni waktu menunggu koneksi.

Di log mentah: **51× ERROR + 106× WARNING "Login timeout expired"**, mulai
14:46:50, memuncak 14:48–14:50, reda 14:51:10.

## Bukti 4 — satu instance, dan autoscaling telat 4 menit

Concurrency di instance `737cddca` (per 10 dtk, WIB):

```
14:45:19  10      14:47:19  51
14:45:49  24      14:47:49  62
14:46:19  29      14:48:19  69
14:46:49  48      14:48:49  75   ← puncak
14:47:09  48      14:51:19  23   ← mulai reda
```

Log sistem Cloud Run:

```
14:47:44  Starting new instance. Reason: AUTOSCALING
14:51:50  Starting new instance. Reason: AUTOSCALING
14:53:05  Starting new instance. Reason: AUTOSCALING  (×2)
```

Kemacetan mulai ±14:44:26, instance kedua baru lahir **14:47:44** — dan hanya
menerima 1–7 request sementara instance pertama masih memegang 65. Praktis
seluruh trafik ditanggung **satu instance** sepanjang insiden.

Sesudah pulih (14:49–14:53) trafik justru **lebih tinggi** — 90–128 request per
30 detik vs 33–46 saat padam — dengan latency rata-rata 0,3–1,1 dtk dan **nol
5xx**. Jadi bebannya sendiri sanggup dilayani; yang mematikan adalah satu
instance yang sudah terlanjur tersumbat.

## Bukti 5 — komposisi trafik

15 URL teratas di jendela itu:

```
220  /handle-task                    ← Cloud Tasks, 19% dari seluruh request
105  /homepage/notifications/unread  ← polling
 93  /api/v1/activities              ← polling
 42  /login
 23  /api/v1/activity/handshake      ← polling
```

`/handle-task` (queue worker via HTTP) berbagi kuota concurrency dan jatah
koneksi DB yang **sama persis** dengan trafik pengguna. Latency rata-ratanya
2,52 dtk, maksimum 255 dtk. Ditambah tiga endpoint polling, baseline-nya
konstan dan tak pernah reda.

---

## Koreksi atas analisis awal (sebelum log ini ada)

Dua dugaan saya di versi pertama dokumen ini **tidak terbukti** sebagai sebab
insiden:

1. **`N_LMS_Api_Nonces` tanpa index** — masih cacat nyata (lihat di bawah), tapi
   BUKAN pemicu insiden ini: request yang gagal tak pernah sampai ke query nonce
   (`Db_Query_Count = 0`).
2. **"LOCK_TIMEOUT kita 10 dtk = timeout klien 10 dtk"** — **salah**. Log
   menunjukkan production memakai `SET LOCK_TIMEOUT 60000`, bukan 10000.
   Konsekuensinya justru terbalik dan lebih buruk: dengan 60 detik, request yang
   ter-blok **menahan koneksi enam kali lebih lama** — persis yang docblock
   middleware itu sendiri peringatkan akan menyebabkan saturasi pool.

---

## Yang perlu dikerjakan (menunggu ACC, belum dikerjakan)

**P0 — kapasitas & isolasi (ini yang mencegah insiden terulang)**

1. **`min-instances ≥ 2` di Cloud Run.** Satu instance = satu titik gagal, dan
   autoscaler butuh 4 menit untuk bereaksi — terlalu lambat.
2. **Turunkan `concurrency` per instance.** Default 80 terlalu tinggi untuk
   Apache prefork + koneksi SQL Server tanpa pooling: 80 request bersamaan =
   80 koneksi dari satu instance. Nilai 20–30 memaksa autoscaler menambah
   instance jauh lebih awal, alih-alih menumpuk di satu.
3. **Pisahkan `/handle-task` ke service Cloud Run sendiri.** 19% trafik adalah
   queue worker yang saat ini merebut slot dan koneksi milik pengguna.
4. **Cek metrik Cloud SQL di 14:44–14:52** (CPU, active connections,
   `Login timeout` di sisi server). Log aplikasi hanya menunjukkan gejalanya;
   apakah DB-nya CPU 100% atau kehabisan koneksi hanya terlihat dari sana.
   Ini satu-satunya bagian yang belum terjawab.

**P1 — memperkecil beban koneksi**

5. **Turunkan `DB_WEB_LOCK_TIMEOUT_MS` dari 60000 ke ~10000.** Angka sekarang
   membuat request ter-blok menahan koneksi sampai satu menit.
6. **Matikan `REQUEST_DIAGNOSTICS_TAG_SESSION`.** Sedang **ON** di production
   (terlihat dari `SET CONTEXT_INFO` di log), padahal config-nya sendiri
   menulis *"DEFAULT OFF ... nyalakan HANYA saat sedang berburu lock aktif"*.
   Satu round-trip DB ekstra di setiap request.
7. **Kurangi polling.** `notifications/unread` + `api/v1/activities` +
   `activity/handshake` = 221 request (20%) tanpa satu pun aksi pengguna.

**P2 — supaya kegagalan tak menyamar jadi kesunyian**

8. **Longgarkan jendela `X-HC-Timestamp`** dari 60 dtk, atau jangan jawab 401
   untuk request yang basi **karena antrean kita sendiri** — beri 503 + `Retry-After`
   supaya HC Learn tahu ini masalah sementara di kita, bukan tanda tangan mereka
   yang salah.
9. **Idempotensi per `Id_Ujian_Token`**: kalau skor sudah tercatat, jawab 200
   segera tanpa membuka transaksi. Menjinakkan retry HC Learn (mereka memang
   retry — tercatat di `SopKuisDispatchService`).
10. **DDL `N_LMS_Api_Nonces`**: tambah `UNIQUE (Nonce)` + index `Expired_At`,
    menyamai kembarannya `N_HRIS_Api_Nonce` yang sudah punya keduanya. Cacat
    laten, bukan sebab insiden ini, tapi `WHERE Nonce = ?` sekarang full scan di
    tabel yang menurut docblock `ApiNoncePrune` "sudah menumpuk sejak awal
    integrasi".
11. **Buang panggilan ganda `recalculateFinalScoreIfReady()`** di
    `TrainingExecutionService::completeActivity()` — dipanggil dua kali dalam
    satu transaksi (±8 query join-6-tabel terbuang sambil memegang lock).

---

## Yang perlu diperiksa terpisah

`SELECT` skor MAT ARIF: apakah callback 14:44:40 (200) atau 14:48:14 (200) sudah
menuliskan Score 81 untuk `Id_Penjadwalan_Integrasi = 7651`. Kalau sudah,
datanya **tidak hilang** — HC Learn hanya menandainya gagal karena tidak
menerima balasan. Yang perlu diperbaiki jadi status sinkronisasinya, bukan
datanya.
