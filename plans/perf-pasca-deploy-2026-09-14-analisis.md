# Analisis performa pasca-deploy stable — 12–14 Sep 2026

Status: **investigasi selesai, belum ada perubahan kode.** Menunggu ACC.

Bahan:

- `log error hcis after deploy stable 2/3/4.json` — 3.496 event request Cloud Run
  unik (setelah dedup `insertId`), revisi `hris-00183-h5q`, 12 Sep 06:33 – 14 Sep
  06:33 UTC.
- `N_S_Request_Diagnostics` — 1.813 baris, 12 Sep 00:11 – 14 Sep 15:08 WIB.
  Ambang perekaman terukur: `MIN(Total_Ms) = 1.974`, jadi tabel ini **hanya**
  berisi request lambat/5xx. Bukan sampel trafik normal.
- `N_IS_DB_Health_Snapshot` — 18 baris, 4 kejadian blocking chain.

---

## 1. Bentuk insidennya: satu jendela, bukan degradasi merata

Sebaran `>5 dtk` per menit di jendela 14 Sep 01:50–02:15 UTC (08:50–09:15 WIB):

```
menit | mulai | >5dtk | 5xx | 503 | in-flight pada awal menit
01:56 |    43 |     0 |   0 |   0 |  0
01:57 |    99 |    18 |  17 |   0 |  0
01:58 |    55 |    31 |  29 |   0 |  8
01:59 |    33 |    29 |  26 |   2 | 26
02:00 |    32 |    19 |  19 |  11 | 52
02:01 |   110 |     9 |   9 |   9 | 71   ← puncak
02:02 |   116 |     0 |   0 |   0 | 64
02:03 |    90 |     3 |   0 |   0 | 37
02:04 |    25 |     0 |   0 |   0 | 11
```

Seluruh 500/503/504 dalam tiga berkas log terkonsentrasi di jendela ~8 menit ini.
Di luar itu sistem sehat. Jadi yang dicari bukan "endpoint yang selalu lambat",
melainkan **apa yang membuat satu instance tersumbat selama 8 menit**.

Ciri khasnya: puluhan request dengan waktu mulai berbeda-beda **selesai pada
detik yang sama**. Contoh gugus terbesar — 27 request mulai antara 01:58:30 dan
02:00:08, semuanya selesai 02:02:38 dengan 500. Itu pola antrean yang dilepas
serentak, bukan kueri yang masing-masing lambat.

`in-flight` puncak 71 request pada satu instance — lihat §3 untuk pembagiannya
per instance, yang ternyata justru bagian paling menentukan dari seluruh analisis
ini.

---

## 2. Akar: yang lambat adalah MEMBUKA KONEKSI, bukan kueri dan bukan lock

Ini temuan baru yang menjawab pertanyaan yang tergantung di
`plans/lms-callback-timeout-hclearn.md` ("apakah DB-nya CPU 100% atau kehabisan
koneksi").

`Conn_Acquire_Ms` di `RequestDiagnostics` = jarak dari awal middleware sampai
kueri **pertama selesai**. Laravel menyambung secara malas di dalam
`Connection::run()`, jadi ongkos connect ikut terhitung di kueri pertama itu.

Dari 145 baris dengan `Conn_Acquire_Ms > 1.000`, **111 baris** punya
`Conn_Acquire_Ms ≈ Slowest_Query_Ms`, dan kueri terlambatnya adalah
`SET CONTEXT_INFO 0x…` — pernyataan pertama yang dijalankan aplikasi di koneksi
baru. `SET CONTEXT_INFO` tidak mengambil lock, tidak memindai apa pun, dan tidak
menyentuh satu baris pun.

Baris paling telanjang (14 Sep 08:58:11 WIB):

```
homepage/notifications/unread
  Total_Ms         30.198
  Conn_Acquire_Ms  30.171
  Db_Query_Count        8
  Db_Query_Ms      30.192
  Slowest_Query_Ms 30.171   ← SET CONTEXT_INFO
```

Tujuh kueri sisanya: **21 ms bertujuh**. PHP di luar DB: **6 ms**.

Artinya, selama insiden:

- basis data **mengeksekusi dengan normal** — bukan CPU 100%, bukan lock;
- PHP **tidak** kekurangan CPU untuk kerja aplikasinya;
- yang menghabiskan 8–39 detik adalah **jabat tangan koneksi TDS itu sendiri**.

`DB_LOGIN_TIMEOUT` = 15 dtk. Begitu connect melewati 15 detik, driver melempar
`SQLSTATE[HYT00] Login timeout expired`. Itulah galat yang dilihat pengguna —
dan namanya menyesatkan: tidak ada hubungannya dengan halaman login.

**Konsekuensi cara pandang.** Sumber daya langka di sini bukan "kueri per detik"
melainkan **laju pembukaan koneksi baru**. PHP tidak punya connection pool: satu
request HTTP = satu koneksi baru = satu login ke SQL Server. Satu instance dengan
71 request in-flight berarti 71 login serentak dari satu container.

Di mana tepatnya jalur connect itu mengantre — CPU container, konektor VPC, atau
antrean login SQL Server — dijawab oleh §3: **di dalam container**.

---

## 3. Penguat 1 — SATU instance mentok di plafon `concurrency` 80 dan tak pernah pulih

> **Koreksi.** Versi pertama dokumen ini menulis "seluruh jendela dilayani satu
> instance". **Itu salah.** Sebabnya kesalahan analisis, bukan datanya: saya
> memotong `labels.instanceId` ke 8 karakter untuk keterbacaan, padahal keenam
> instanceId di log ini berbagi **prefix 10 karakter** (`00a41e8c1d`) — jadi
> semuanya runtuh jadi satu nama. Dengan id penuh, jendela insiden dilayani
> **4 instance**. Temuan yang benar ternyata jauh lebih tajam dari yang salah.

Pembagian per instance di jendela 01:50–02:15 UTC, dengan `instanceId` penuh:

| instance | n | 5xx | 503 | >5 dtk | in-flight maks | hidup |
|---|---|---|---|---|---|---|
| `…2f3c3a05` | 255 | **100** | 22 | **106** | **80** (02:01:23) | 01:53:03 → 02:05:28 |
| `…e2dbe0eb` | 298 | **0** | 0 | 3 | 9 | 02:00:47 → 02:03:37 |
| `…cae1c56e` | 2 | 0 | 0 | 0 | 2 | 02:03:32 → 02:03:35 |
| `…d17edfba` | 50 | 1 | 0 | 0 | 5 | 02:03:36 → 02:13:50 |

Dua hal yang langsung terbaca:

**Pertama, angka 80 itu bukan kebetulan.** In-flight instance `…2f3c3a05`
memuncak **tepat di 80** dan tidak pernah lewat. 80 adalah nilai default
`concurrency` Cloud Run. Jadi plafon itu **terbukti mengikat** di produksi, bukan
sekadar dugaan — dan 22 × 503 muncul persis ketika ia tersentuh (Cloud Run
menolak request saat antrean instance penuh).

**Kedua, dan ini yang paling penting: instance penggantinya sehat sempurna,
di menit yang sama.** `…e2dbe0eb` naik 02:00:47 dan melayani 298 request dengan
**nol 5xx**, p50 0,12 dtk, p95 2,56 dtk.

Pada rentang tumpang-tindih 02:00:47–02:03:37, ketika **kedua instance melayani
trafik bersamaan**:

```
…2f3c3a05 (lama)  : 12 request  → 12 × 503, semuanya > 5 dtk
…e2dbe0eb (baru)  : 298 request → 0 error,  p95 2,56 dtk
```

Autoscaler bekerja. Yang tidak terjadi adalah **pemulihan instance lama**: 80
request yang terlanjur masuk ke sana tetap macet di sana sampai 02:05, dan tiap
kegagalan menahan slotnya sendiri sampai batas Cloud Run.

Dikonfirmasi dari sisi diagnostik: sejak 09:01:55 WIB — setelah instance sehat
mengambil alih — `Conn_Acquire_Ms` turun ke **16–121 ms** padahal `Total_Ms`
tetap 3–12 detik. Kuerinya masih berat (itu §5), tapi **membuka koneksinya
kembali normal**.

---

## 4. Penguat 2 — `/handle-task` berbagi instance dengan pengguna

`/handle-task` adalah handler Cloud Tasks (`config/queue.php:41`), di-host di
service Cloud Run yang **sama** dengan trafik pengguna.

Di jendela insiden: **120 panggilan = 22,3% dari seluruh request non-aset**,
peringkat 1. Sepanjang tiga berkas log: 121 panggilan, **28 di antaranya 500**
(23%), p95 28,5 dtk, maks 252,7 dtk.

500 pada handler Cloud Tasks berarti **retry**. Retry masuk ke instance yang
sedang tersumbat, memakan slot dan koneksi lagi, dan menaikkan peluang 500
berikutnya. Selama insiden ini adalah satu-satunya sumber trafik yang **tumbuh
karena kegagalan**.

Tambahan: scheduler `/scheduler-T4sk-RuNn3r-sEcr3t` berjalan **tiap menit**.
Eksekusi 01:59:01 memakan **247,3 detik** — satu request yang menahan slot
selama empat menit di tengah insiden. Eksekusi menit-menit lain: 0,7–6,2 dtk.

---

## 5. Beban dasar — apa yang membuat instance rapuh sejak awal

Yang di bawah ini tidak menyebabkan insiden, tapi menentukan berapa lama sebuah
request menahan slot & koneksi. Menurunkannya menaikkan ambang di mana §2–4
mulai menggigit.

### 5a. Satu bentuk kueri = 65% dari seluruh waktu lambat

Kueri `src (UNION ALL 9 view) × flow × det` dari
`AllTransactionsReadService::baseQuery()` — dalam berbagai pembungkus — menyumbang
**5.895 detik dari ~9.100 detik** total waktu request lambat yang tercatat.

| total | n | maks | dipakai oleh |
|---|---|---|---|
| 4.968 dtk | 886 | 56,5 dtk | bootstrap ×639, weekly-calendar ×126, day-detail ×44, izin/getDataIzin ×43, getDataDH ×26 |
| 291 dtk | 170 | 3,9 dtk | shift-approval/dashboard-stats (`count(distinct)` atas subquery yang sama) |
| 284 dtk | 39 | 31,2 dtk | bootstrap ×21, day-detail ×13 (varian kolom lain) |
| 189 dtk | 56 | 9,4 dtk | leader-dashboard/* |
| 86 dtk | 51 | 3,4 dtk | dashboard-stats (agregat `Jenis`) |
| 55 dtk | 3 | 25,1 dtk | `pendingForApprover` — `count(distinct)` atas 3 lapis derived table |

Pola yang berulang: hasil `baseQuery()` dibungkus lagi sebagai subquery lalu
disaring/dihitung **di luar**, pada kolom yang lahir dari ekspresi `CASE`
(`Dashboard_Status`, `Approval_Method`). Predikat seperti itu tidak bisa
di-pushdown — SQL Server harus memateralisasi union sembilan view lebih dulu.
Filter hulu per-sumber yang sudah ada (`$tap`) memang menolong, tapi tidak
menolong untuk lapisan luar ini.

Arah perbaikan yang perlu dibahas: `Dashboard_Status`/`Approval_Method` dihitung
sekali dan **dipersist** (indexed view atau kolom terhitung di tabel ringkasan),
sehingga penyaringnya jadi sargable dan `count(distinct)` tidak perlu membangun
ulang union tiap kali.

### 5b. `homepage/personal/bootstrap` — endpoint termahal, dan dilewati semua orang

665 baris lambat (terbanyak), p50 **5,17 dtk**, p95 13,8 dtk, maks 91,6 dtk,
median 23 kueri. Kumulatif **4.599 detik** — separuh dari seluruh waktu lambat.

Ini yang dipanggil tepat setelah login. Pengalaman "login-nya lama/timeout"
sebagian besar adalah endpoint ini, bukan `prosesLogin` (p50 `prosesLogin` hanya
0,82 dtk di log Cloud Run).

### 5c. `shift-approval/history` — 748 detik dari satu `select *`

```sql
select * from [N_HRIS_VW_Approval_Tracking_Generic]
where [Processed_By_Kode] = ? and [No_Transaksi] in (?,?,…10 buah)
order by [Processed_At] desc
```

143 panggilan, maks 13,5 dtk, untuk **10 baris** per halaman. `select *` atas
view tracking generic. Ini yang paling murah diperbaiki di seluruh daftar:
sebutkan kolomnya, dan periksa index pendukung `(Processed_By_Kode, No_Transaksi)`.

### 5d. N+1 yang masih tersisa di jalur tulis

| endpoint | kueri median | kueri maks | p95 |
|---|---|---|---|
| `leader-shift/submit` | 413 | **965** | 28,9 dtk |
| `leader-shift/offday` | 194 | 519 | 10,7 dtk |
| `shift-approval/process` | 90 | 527 | 16,0 dtk |
| `homepage/personal/upcoming-schedule` | 48 | 62 | 22,2 dtk |
| `shift-approval/inbox` | 34 | 36 | 6,0 dtk |

`leader-shift/submit` dengan 965 kueri dalam satu request adalah yang paling
berat; ini jalur yang sama dengan catatan `pemblokir-akar-leader-shift-submit`
(dirty-mark per-tanggal di dalam transaksi). Perlu diverifikasi ulang apakah
perbaikan sebelumnya benar-benar mencakup jalur ini.

### 5e. Blocking chain — nyata, tapi masalah terpisah

Empat snapshot, dan kepala rantainya **selalu pembaca panjang**, bukan penulis:

```
14 Sep 07:45:01
  sess 70  GET /homepage/personal/bootstrap   running, cpu 6.095 ms   ← kepala
   └ sess 72  POST /self-shift/submit         LCK_M_IX  6.493 ms
      └ sess 59, 74 …
         └ sess 71 weekly-calendar, 73 bootstrap, 76, 80   LCK_M_S
14 Sep 10:15:01
  sess 71  GET /homepage/personal/weekly-calendar  runnable, cpu 2.016 ms
   └ sess 58  POST /self-shift/submit         LCK_M_IX  4.113 ms
13 Sep 17:06:01
  sess 69  POST /leader-shift/submit (UPDATE HRIS_Shift_Sementara)
   └ sess 71  GET /homepage/personal/bootstrap  LCK_M_S 5.874 ms
```

Pembaca memblok penulis karena **RCSI mati** di `Web_HRIS` (lihat catatan
`sqlserver-rcsi-mati-web-hris`): di READ COMMITTED tanpa RCSI, scan multi-detik
memegang shared lock cukup lama untuk menahan `IX` milik penulis. Selama §5a–5b
belum turun, tiap perbaikan di sisi penulis akan terus dikalahkan oleh pembaca.

---

## 6. Temuan sampingan dari log Cloud Run

**62% request adalah 404.** 2.161 dari 3.496. Sekitar 1.900 di antaranya aset
build lama (`/build/assets/app-*.css|js`, `Login-*.js`, `design-system-*.css`),
plus 196 × `apple-touch-icon*.png`. `.htaccess` mengarahkan semua berkas yang
tidak ada ke `index.php`, jadi tiap satu tetap membayar boot framework penuh.
Berpasangan dengan **66 × 409** pada `/homepage`, `/logout`, `/login`,
`/leader-shift` — 409 adalah Inertia menolak versi aset yang basi. Gejalanya:
tab yang terbuka saat deploy berputar antara aset 404 dan reload paksa.

**407 dari 484 × 401 berasal dari dua poller**: `homepage/notifications/unread`
(206) dan `api/v1/activities` (201). `resources/js/app.js:147` menunjukkan 401 di
sini memang disengaja sebagai bukti sesi habis, tapi volumenya berarti klien
tetap memanggil terus sesudah sesi mati.

**`REQUEST_DIAGNOSTICS_TAG_SESSION` menyala di produksi** — terbukti dari adanya
`SET CONTEXT_INFO` di data. `config/request-diagnostics.php:47` sendiri menulis
*"DEFAULT OFF karena menambah 1 round-trip DB per request. Nyalakan HANYA saat
sedang berburu lock aktif."* Ironinya ganda: pemantau itu **satu-satunya alasan
kita bisa membuktikan §2**, tapi ia juga menambah satu round-trip di tiap
request. Matikan setelah §7 terjawab, bukan sebelum.

---

## 7. Di mana antrean connect itu berada — terjawab: di dalam container

Ada tiga kandidat, dan penanganannya berbeda jauh:

1. **CPU container** — jabat tangan (TLS bila `DB_ENCRYPT` menyala) bersaing
   dengan ~80 proses Apache prefork lain di jatah vCPU yang sama.
2. **Konektor VPC / jalur jaringan ke SQL Server** — pembukaan koneksi baru
   diantre di konektor.
3. **Antrean login SQL Server** (`THREADPOOL`) — worker habis dipakai sesi lain.

Kandidat 2 dan 3 adalah sumber daya yang **dipakai bersama oleh semua instance**.
Kalau antreannya di sana, instance baru akan ikut lambat. §3 menunjukkan ia
**tidak**: di rentang yang sama, di menit yang sama, ke basis data yang sama,
`…2f3c3a05` menjawab 12 × 503 sementara `…e2dbe0eb` menjawab 298 request tanpa
satu pun error dengan p95 2,56 dtk — dan `Conn_Acquire_Ms` di sana 16–121 ms.

Jadi yang mengantre adalah **kandidat 1: sumber daya di dalam container** (CPU /
proses / slot Apache), bukan DB dan bukan jaringan. Ini juga menjelaskan kenapa
di request yang sama, connect makan 30 detik tapi tujuh kueri sesudahnya hanya
21 ms bertujuh: begitu koneksi terbentuk, kueri hanyalah tunggu I/O — sedangkan
jabat tangan butuh CPU, dan CPU-lah yang habis.

**Yang tetap perlu diambil dari GCP** — bukan lagi untuk menentukan kandidat,
melainkan untuk menyetel angkanya:

- `container/cpu/utilizations` per instance pada 01:57–02:05 UTC → berapa vCPU
  yang sebenarnya dibutuhkan di `concurrency` target.
- Nilai `concurrency`, `cpu`, `memory`, `min-instances`, `max-instances` yang
  terpasang sekarang di revisi `hris-00183-h5q`.
- Nilai `DB_ENCRYPT`. Kalau menyala, tiap connect membayar handshake TLS penuh —
  itu komponen CPU terbesar dan paling langsung bisa ditekan.

---

## 8. Urutan kerja yang diusulkan

**P0 — mencegah kaskade terulang (infrastruktur, tanpa sentuh kode)**

1. **Turunkan `concurrency` dari 80 ke 20–30.** Ini nomor satu, bukan
   `min-instances`. §3 membuktikan plafon 80 mengikat di produksi (in-flight
   memuncak tepat 80) dan §7 membuktikan yang habis adalah sumber daya di dalam
   container. Dengan 20, beban yang sama tersebar ke ~4 container dan laju
   jabat-tangan per container turun jadi seperempat.
2. **Naikkan `cpu` per instance** bila §7 menunjukkan utilisasi mentok — atau
   matikan `DB_ENCRYPT` bila enkripsi ke SQL Server memang tak diperlukan pada
   jalur jaringan itu. Keduanya menyerang ongkos jabat tangan secara langsung.
3. `min-instances ≥ 2`. Autoscaler **sudah** bekerja (instance sehat naik dalam
   ~3 menit); ini memangkas jeda itu, bukan memperbaiki sebab.
4. Pindahkan `/handle-task` ke service Cloud Run tersendiri. 22,3% trafik, 23%
   di antaranya 500 → retry yang tumbuh justru saat sistem sedang jatuh.

**P1 — memperkecil beban per request**

4. `shift-approval/history`: buang `select *`, periksa index. 748 dtk, perbaikan
   termurah di daftar (5c).
5. `homepage/personal/bootstrap`: turunkan p50 dari 5,17 dtk (5b) — sebagian
   besar adalah 5a.
6. `leader-shift/submit` 965 kueri (5d).
7. Setelah §7 terjawab: matikan `REQUEST_DIAGNOSTICS_TAG_SESSION`, dan turunkan
   `DB_WEB_LOCK_TIMEOUT_MS` bila produksi memang masih 60000 (catatan
   `lms-callback-timeout-hclearn.md` menemukan demikian).

**P2 — struktural, perlu pembahasan tersendiri**

8. Persist `Dashboard_Status`/`Approval_Method` supaya penyaringnya sargable
   (5a). Ini yang membuka 65% waktu lambat.
9. RCSI di `Web_HRIS` (5e). Perubahan tingkat basis data, butuh keputusan
   terpisah.
10. Aset 404 + 409 Inertia saat deploy (§6): pertahankan aset build lama satu
    rilis, atau tangani 409 dengan reload yang lebih lunak.

---

## Batasan analisis ini

- Ketiga berkas log adalah **ekspor tersaring**, bukan seluruh trafik. Berkas 3
  dan 4 hanya ERROR/WARNING; berkas 2 adalah ekspor penuh (semua severity) tapi
  hanya untuk jendela 01:56–02:04 UTC. Karena itu hitungan "instance unik per
  jam" di data ini **tidak** bisa dipakai untuk menyimpulkan jumlah instance di
  jam-jam tenang — angka per-instance di §3 hanya sahih untuk jendela insiden,
  yang memang tercakup penuh oleh berkas 2.
- `labels.instanceId` Cloud Run di service ini panjangnya 186–190 karakter dan
  **berbagi prefix 10 karakter**. Memotongnya untuk keterbacaan akan meleburkan
  instance yang berbeda jadi satu — kesalahan yang sempat terjadi di versi
  pertama dokumen ini dan menghasilkan kesimpulan yang terbalik.
- `N_S_Request_Diagnostics` hanya merekam request ≥ ~2 dtk atau 5xx. Semua p50 di
  dokumen ini adalah **p50 di antara request lambat**, bukan p50 seluruh trafik.
- Tidak ada satu pun baris di data ini yang berisi stack trace aplikasi. Sebab
  dari 74 × 500 tidak bisa dipastikan dari sini; yang bisa dipastikan hanyalah
  *kapan* dan *dalam keadaan apa* mereka terjadi.
