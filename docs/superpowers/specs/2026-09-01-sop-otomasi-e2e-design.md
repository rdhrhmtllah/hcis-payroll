# Otomasi Dokumen — lapis peramban untuk UAT-SOP-024 & 025

Tanggal: 2026-09-01
Status: **RANCANGAN — MENUNGGU ACC**

Halaman: `/sop/admin/kebijakan` — "Otomasi Dokumen" (`SopKebijakan.vue`)
Skenario UAT: **024** (menyusun aturan + pratinjau dampak) dan **025**
(mengaktifkan, menonaktifkan, melihat riwayat)

---

## 1. Pertanyaan yang dijawab rancangan ini

*"Apakah Otomasi Dokumen benar-benar bekerja, persis seperti yang akan dialami
user langsung?"*

Kata **persis** itu yang menentukan bentuk pekerjaannya. Aturan penugasan
otomatis punya sifat yang tak dimiliki fitur lain di modul ini: ia menugaskan
orang **tanpa admin menekan apa pun lagi**. Uji yang memanggil service langsung
membuktikan mesinnya benar; ia tidak membuktikan bahwa orang yang duduk di depan
layar bisa sampai ke mesin itu, melihat dampaknya sebelum terjadi, dan
menghentikannya sesudahnya. Ketiganya adalah janji yang dibuat oleh layar.

### 1.1 Apa yang sudah terbukti, dan di lapis mana

| Lapis | Berkas | Yang dijaga | Yang tak tersentuh |
|---|---|---|---|
| PHPUnit UAT | `tests/Feature/LMS/SOP/Uat/Alur024KebijakanOtomasiTest.php` | draft→pratinjau punya baris TOTAL · aturan bisa dinonaktifkan · aturan tanpa kriteria ditolak · setelan tersimpan | seluruh layar |
| PHPUnit fitur | `tests/Feature/LMS/SOP/Otomasi/` (4 berkas) | service, endpoint, riwayat induk-anak, tabel jembatan | seluruh layar |
| Vitest | `SopKebijakan.test.js`, `SopAturanPratinjau.test.js`, `SopAturanPaketList.test.js`, `SopKriteriaGrup.test.js` | komponen di jsdom | server, DB, hashids, antrean |
| **Peramban** | **tidak ada** | — | — |

`UatSkenario::PERAMBAN` saat ini `[8, 9, 16, 19, 31, 32]`. 024 dan 025 **tidak**
di dalamnya, artinya katalog hari ini menyatakan keduanya cukup dibuktikan dari
backend. Rancangan ini mengubah pernyataan itu.

### 1.2 Koreksi penomoran

Rancangan awal saya memakai `040-otomasi-dokumen.spec.js`. Itu **salah** dan
sudah dibuang: rentang 40–43 milik modul "Monitoring & Laporan"
(`UatSkenario::MODUL`), dan `Alur040MonitoringTest.php` sudah menempatinya.
Penomoran spec peramban di repo ini mengikuti nomor skenario UAT, bukan urutan
penulisan — `019-pratinjau-dan-terbit.spec.js` adalah UAT-SOP-019.

Berkas yang benar: **`024-…`** dan **`025-…`**.

---

## 2. Keputusan rancangan

### 2.1 Cohort WAJIB tepat satu orang, dan itu digerbangi keras

Ini keputusan terpenting di seluruh dokumen.

`resolveCohort()` menghitung `matched = (groups ∪ include_karyawan) \
(exclude_karyawan ∪ exclude_groups)`. Sementara `SopAssignmentRuleConfig`
menuntut **minimal satu grup target, dan tiap grup wajib punya minimal satu
kriteria** — grup kosong berarti "seluruh karyawan aktif", dan penjaganya ada
justru supaya satu klik tak sengaja tidak menyasar seluruh headcount.

Konsekuensinya untuk uji: aturan uji tak bisa dibuat "tanpa cakupan". Bentuk
yang dipakai spec ini:

```
groups           = [ satu grup berkriteria X ]
exclude_groups   = [ grup berkriteria X yang sama ]
include_karyawan = [ UAT_NIK_KARYAWAN_1 ]
```

Grup X dinetralkan oleh dirinya sendiri, menyisakan `matched = {KARYAWAN_1}`
— selama KARYAWAN_1 sendiri bukan anggota X.

**Grup X itu apa, konkretnya.** Satu kriteria yang terbukti **tidak** memuat
KARYAWAN_1 — misalnya tipe pekerja atau departemen yang berbeda dari miliknya.
Nilainya tidak dipatok di dokumen ini karena bergantung isi
`filterOptions()` pada DB yang dipakai; ia dipilih sekali saat spec ditulis dan
ditulis sebagai konstanta bernama di puncak berkas, bukan tersebar sebagai
literal. Kalau pilihan itu meleset, gerbang `matched === 1` menangkapnya
sebagai kegagalan bernas — bukan sebagai penugasan yang sudah terjadi.

**Gerbangnya:** sebelum menekan *Aktifkan & Terapkan*, spec membaca
`data-uji="pratinjau-total"` dan menuntut `matched === 1`. Kalau bukan 1, spec
**gagal di situ** dengan pesan yang menyuruh operator memilih NIK lain — dan
aturannya tidak pernah diaktifkan.

Ini bukan kerapian. Basis data dev berisi karyawan sungguhan. Aturan uji yang
cohort-nya meleset akan menugaskan paket uji ke puluhan orang nyata, dan
pembersihnya hanya menghapus baris yang tercatat di jurnal. Gerbang ini adalah
satu-satunya yang berdiri antara satu salah-ketik NIK dan penugasan massal di
DB yang dipakai orang bekerja.

### 2.2 Dunia dibangun untuk KARYAWAN_2, aturan menyasar KARYAWAN_1

`sop:uat-dunia <nik>` menerbitkan paket **dan menugaskannya** ke `<nik>`. Kalau
dunianya dibangun untuk orang yang sama dengan sasaran aturan, langkah "paket
muncul di SOP Saya" sudah hijau sebelum otomasi berjalan — uji yang tak menguji
apa pun.

Karena itu: `sop:uat-dunia $KARYAWAN_2` membuat paket terbit yang dimiliki
KARYAWAN_2, lalu aturan menyasar KARYAWAN_1 yang belum memilikinya. Efek
otomasi punya sebelum-dan-sesudah yang nyata.

`--draft` tidak bisa dipakai untuk ini: paket DRAFT tak muncul di dropdown
aturan (`paketPublished()`), jadi aturannya tak akan bisa disimpan.

### 2.3 Prasyarat negatif di AWAL, bukan penutup di akhir

Langkah pertama spec adalah membuktikan KARYAWAN_1 **tidak** melihat paket uji
di `/sop/saya`. Kalau ternyata sudah terlihat — data sisa jalan sebelumnya,
NIK yang keliru, paket publik — spec gagal di langkah 1, bukan hijau palsu di
langkah 6.

### 2.4 Semua aksi lewat layar; DB hanya untuk fakta yang tak punya cerminan

Tak ada panggilan `axios` langsung, tak ada `POST` ke API dari spec. Semua
melalui klik, karena itu yang diminta.

Pengecualian yang sama dengan spec 019: fakta yang **tak punya cerminan visual
sama sekali** dibaca lewat `artisan tinker`. Di sini hanya satu — nilai
`Deadline_Hari` yang tersimpan pada penugasan hasil otomasi. Layar SOP Saya
menampilkan tanggal jatuh tempo, bukan angka "N hari" yang diketik admin;
tanpa membaca kolomnya, spec sama hijaunya seandainya tenggatnya dibuang diam-diam
di perjalanan.

---

## 3. Prasyarat yang harus datang dari manusia

`.env.uat` belum ada (hanya `.env.uat.example`). Berkas itu gitignored dan
memuat kredensial; ia disusun dengan **menyalin `.env` lalu menimpa** baris di
bawah — bukan disusun dari nol.

| Kunci | Nilai | Kenapa tak bisa saya karang |
|---|---|---|
| `UAT_BASE_URL` | vhost Laragon untuk worktree ini | `php artisan serve` di Windows melayani satu permintaan pada satu waktu; terukur 54,7 menit lalu menggantung, versus 2,8 menit lewat vhost. Gejalanya menyamar jadi "elemen tidak ditemukan" |
| `UAT_NIK_ADMIN` | NIK dengan ketujuh hak akses halaman SOP | Hak akses di sistem ini eksplisit per-halaman; nol role LMS aktif |
| `UAT_NIK_KARYAWAN_1` | sasaran aturan, **bukan** admin | Batas akses tak bisa dibuktikan kalau sama dengan admin (spec 000 sudah `skip` untuk kasus itu) |
| `UAT_NIK_KARYAWAN_2` | pemilik dunia uji, berbeda dari KARYAWAN_1 | Lihat §2.2 |
| `DB_DATABASE` | keputusan | Contohnya menyebut `Web_HRIS_Cut_Off` terisolasi. Kalau dipilih `Web_HRIS` dev: spec menulis ke DB yang dipakai orang bekerja, dan RCSI mati di sana sehingga pembaca antre di belakang penulis |
| `UAT_SOP_IZINKAN=1` | gerbang | Tanpa ini `UatGerbang` menolak; sengaja, supaya `artisan test` biasa atau CI tak pernah memicunya |
| `QUEUE_CONNECTION=sync` | wajib | Tanpanya penugasan menunggu di Cloud Tasks dan SOP Saya kosong — kegagalan yang terlihat persis seperti bug fitur |

Dua NIK karyawan harus **berbeda** dan **keduanya bukan anggota grup X** yang
dipakai §2.1. Spec memverifikasi ini lewat gerbang `matched === 1`, jadi
kesalahannya terlaporkan, bukan tersembunyi.

---

## 4. Tiga perbaikan fondasi yang mendahului spec

Ketiganya bukan hiasan — tanpa mereka spec ini meninggalkan sampah di DB dev
atau tak bisa dilaporkan.

### 4.1 `UatTabelIzin` kehilangan tabel jembatan — **bug yang sudah ada**

`app/Services/LMS/SOP/Uat/UatTabelIzin.php:59` memuat `N_SOP_Assignment_Rule`
tetapi **tidak** `N_SOP_Assignment_Rule_Paket`. Tabel jembatan itu lahir dari
DDL-2026-053 dan daftar-putihnya tidak ikut diperbarui.

Akibatnya hari ini: `sop:uat-bersihkan` yang mencoba menghapus sebuah aturan
akan **mati di foreign key** dan meninggalkan aturan yatim. Ini berlaku untuk
siapa pun yang menyentuh aturan dari harness UAT, bukan cuma spec ini.

Perbaikan: satu baris, ditaruh **sebelum** induknya (daftar itu urut anak-dulu).

Perbaikan ini punya utang uji sendiri: penjaga regresi harus pernah dilihat
MERAH sebelum dipercaya. Rencananya satu tes yang membangun aturan berpaket,
menjalankan pembersih, dan menuntut baris jembatannya hilang — dijalankan sekali
dengan baris §4.1 dicabut, untuk memastikan ia benar-benar menyala.

### 4.2 `sop:uat-dunia --lengkapi` perlu `--aturan=<id>`

Aturan dibuat lewat **peramban**, jadi barisnya tak pernah masuk jurnal
jalan-uji. Ini persis masalah yang sudah dipecahkan `--lengkapi` untuk baris
hasil penerbitan di spec 019.

Perluasan: `--aturan=<id>` membuat `UatPembangunDunia` mencatat ke jurnal —
baris `N_SOP_Assignment_Rule`, baris `N_SOP_Assignment_Rule_Paket` miliknya,
baris `N_SOP_Automation_Run` (induk **dan** anak), serta baris penjadwalan /
detail / progres yang lahir dari penerapan aturan itu.

Tanpa ini, tiap jalan-uji meninggalkan satu aturan aktif di halaman Otomasi
yang akan ikut dievaluasi scan berikutnya.

### 4.3 `UatSkenario::PERAMBAN` menerima 24 dan 25

Supaya `sop:uat-laporan` menandai kedua skenario itu berlapis `peramban`, bukan
`backend`. Diperiksa: tak ada penjaga jumlah pada `PERAMBAN`
(`UatSkenarioTest.php` hanya menjaga rentang, kembar, dan irisan dengan
`MANUAL`) — jadi penambahan ini aman.

Catatan yang ditemukan sambil memeriksa, di luar lingkup: **009 sudah terdaftar
`PERAMBAN` tetapi tak punya berkas spec.** Ia dilaporkan BELUM selamanya. Bukan
bagian pekerjaan ini; dicatat supaya tidak dikira akibat perubahan ini.

---

## 5. Spec 024 — menyusun aturan dan menimbang dampaknya

Berkas: `tests/e2e/024-otomasi-aturan-penugasan.spec.js`
Judul test diberi tag `@UAT-SOP-024`; `sop:uat-laporan` memetakan hasil peramban
lewat tag pada judul, bukan lewat pemindaian sumber.

**`beforeAll`** — `sop:uat-dunia $KARYAWAN_2 --item=2` → `{ jalan, paket,
paket_hash, penjadwalan }`.

| # | Perjalanan | Yang dijaga |
|---|---|---|
| 1 | Login KARYAWAN_1 → `/sop/saya`. Paket uji **tidak** ada | Prasyarat negatif (§2.3) |
| 2 | Admin → `/sop/admin/kebijakan`. Tab **Aturan Penugasan** aktif, tombol **Aturan Baru** ada | Halaman terbuka untuk peran yang benar |
| 3 | **Aturan Baru** → isi `#sop-nama-aturan` dengan nama bertanda jalan-uji; `SopAturanPaketList` → pilih paket uji; `SopKriteriaGrup` (`id-prefix="sop-grup"`) → grup X; Selalu Sertakan → KARYAWAN_1; Selalu Kecualikan grup → grup X | Formulir sungguhan, validasi sungguhan |
| 4 | Mode Tenggat (`#sop-deadline-mode`) = **RELATIVE**, `#sop-deadline-days` = 7 | Tenggat ikut tersimpan |
| 5 | **Simpan Draft** → kartu muncul di daftar berstatus DRAFT | Draft dulu, aktif belakangan — itu yang memberi pratinjau arti |
| 6 | **Hitung Dampak** → `[data-uji="pratinjau-total"]`: `matched === 1`, `new ≥ 1` | **Gerbang §2.1.** Cohort kosong dan cohort meledak sama-sama tertahan di sini |
| 7 | Layar menyebut nama KARYAWAN_1 pada rincian pratinjau | Pratinjau menampilkan ORANG, bukan cuma angka |
| 8 | **Kontrol:** aturan baru dengan satu grup yang **tak diisi kriteria apa pun** → ditolak, pesannya terbaca di layar | Penjaga "seluruh headcount" hidup di jalur layar, bukan cuma di service |
| 9 | **Kontrol:** aturan baru tanpa paket → ditolak | `paket required|array|min:1` sampai ke mata user |

Langkah 8 dan 9 adalah skenario **kontrol** — membuktikan sistem menolak.
Acceptance Criteria memperlakukannya berbeda: kontrol tak boleh diturunkan jadi
Minor.

---

## 6. Spec 025 — mengaktifkan, membuktikan, menghentikan

Berkas: `tests/e2e/025-otomasi-terapkan-dan-riwayat.spec.js`, tag
`@UAT-SOP-025`.

Dunia dan aturannya dibangun ulang di berkas ini sendiri. Spec yang bersandar
pada sisa berkas lain akan gagal berbeda-beda tergantung urutan jalan — dan
`fullyParallel: false, workers: 1` membuat ketergantungan itu tak terlihat
sampai suatu hari urutannya berubah.

Penyusunan aturannya memakai langkah yang sama dengan 024 §3–5, jadi ia
diangkat menjadi satu helper `susunAturan(page, { nama, paket, sasaran })` di
`tests/e2e/support/otomasi.js`. Helper itu **tetap mengklik layar** — ia bukan
pintasan API, hanya penghapus salinan. Kalau formulirnya berubah, satu tempat
yang ikut berubah, dan kedua spec ikut merah bersamaan alih-alih salah satunya
diam-diam menguji formulir versi lama.

| # | Perjalanan | Yang dijaga |
|---|---|---|
| 1 | **Aktifkan & Terapkan** → kartu jadi ACTIVE | Satu tombol memang mengaktifkan **dan** menerapkan; itu yang ditulis labelnya |
| 2 | Tab **Riwayat Eksekusi** → baris induk `SUCCESS`, `Total_Ditambahkan ≥ 1`, kolom Paket Dokumen menyebut paket uji | Riwayat adalah satu-satunya jejak audit; kalau ia bohong, tak ada tempat lain untuk memeriksa |
| 3 | Login KARYAWAN_1 → `/sop/saya`: **paket uji muncul** | **Inti seluruh pekerjaan ini.** Tanpa langkah ini semua yang di atas cuma menguji layar bicara pada dirinya sendiri |
| 4 | Tenggatnya benar — tanggal di layar, dan `Deadline_Hari = 7` dibaca lewat tinker (§2.4) | Tenggat yang dibuang diam-diam tak punya cerminan visual |
| 5 | Admin → **Jalankan Evaluasi** (`runNow('rule-scan')`) → riwayat: `Ditambahkan = 0`, `Dipertahankan ≥ 1` | Idempotensi. Aturan yang menugaskan ulang tiap scan membanjiri orang dengan kewajiban duplikat |
| 6 | KARYAWAN_1 → `/sop/saya`: paket uji **tepat satu**, bukan dua | Idempotensi dilihat dari sisi yang merasakannya |
| 7 | **Nonaktifkan Aturan** → status turun dari ACTIVE | Aturan otomatis yang tak bisa dimatikan harus dibersihkan akibatnya tiap hari |
| 8 | KARYAWAN_1 → `/sop/saya`: paket **masih ada** | Janji yang tertulis di tombolnya sendiri: *"penugasan yang ada tetap tercatat"*. Uji yang berhenti di "statusnya berubah" akan sama hijaunya seandainya penugasannya ikut lenyap |
| 9 | Aturan kedua, dua paket sekaligus → riwayat: **1 baris induk + 2 baris anak** (`Is_Anak`) | DDL-2026-053. Bentuk induk-anak itu yang membuat satu aktivasi lima paket tidak memakan sepersepuluh jendela riwayat |

**`afterAll`** — `sop:uat-dunia $KARYAWAN_2 --lengkapi <jalan> --penjadwalan
<id> --paket <id> --aturan <id>` lalu `sop:uat-bersihkan <jalan>`.
`--aturan` adalah §4.2; tanpanya aturan, jembatan, dan baris run tertinggal.

---

## 7. Kait uji yang perlu ditambahkan

Yang **sudah ada** dan dipakai apa adanya:
`data-uji="pratinjau-total"`, `data-uji="tertahan-tenggat"`,
`data-uji="setelan-override"`, `data-uji="buang-{Id_SOP_Paket}"`.

Yang **perlu ditambahkan** — empat, sekecil mungkin:

| Kait | Tempat | Kenapa selektor teks tak cukup |
|---|---|---|
| `data-uji="kartu-aturan-{id}"` | kartu aturan di daftar | Nama aturan dipilih spec, tapi daftar bisa memuat aturan lain dengan nama mirip dari jalan yang gagal di tengah |
| `data-uji="status-aturan"` | lencana status pada panel | "ACTIVE"/"DRAFT" muncul juga di chip filter di atasnya |
| `data-uji="baris-run-{id}"` | baris tabel riwayat | Membedakan baris induk dari anaknya butuh identitas, bukan urutan |
| `data-uji="paket-kartu-{hash}"` | kartu paket di `/sop/saya` | Langkah 3 dan 6 dan 8 semuanya bersandar pada ini |

### 7.1 Jebakan selektor yang sudah terdokumentasi di repo

- **Tombol berikon**: glif Bootstrap Icons dari `::before` ikut terhitung ke
  nama aksesibel. Jangkar EKOR (`/Simpan Draft$/i`), bukan `/^Simpan Draft/i`
  — yang terakhir tak pernah cocok.
- **Kotak centang HCIS** disembunyikan CSS: tekan `<label>`, baca `<input>`.
  Berlaku untuk `#sop-reminder-toggle`.
- **`.sop-fade-up`** menahan `translateY(0)` selamanya lewat `animation … both`,
  membuat elemen `position: fixed` di dalamnya terperangkap. Modal aturan wajib
  `<Teleport to="body">` dengan class `sop-scope` ikut dibawa.

---

## 8. Yang spec ini TIDAK bisa jangkau

Bagian ini ada supaya laporan hijau tidak menjanjikan lebih dari yang
dibuktikannya.

### 8.1 Apakah otomasi benar-benar berjalan sendiri — **belum terverifikasi**

`sop:rule-scan` dan `sop:reminder-scan` **tidak terdaftar di scheduler mana pun
dalam kode**. `app/Console/Kernel.php:26` membaca daftar jadwal dari tabel DB
(`$schedule->command($config->Command)`).

Artinya "Otomasi Terjadwal" hidup jika dan hanya jika:

1. barisnya ada dan aktif di tabel jadwal, **dan**
2. cron `schedule:run` benar-benar berjalan di mesin itu.

Peramban tak bisa membuktikan keduanya. Tombol *Jalankan Evaluasi* bisa hijau
sempurna sementara jadwal otomatisnya tak pernah menyala — dan tidak ada satu
pun uji di repo hari ini yang menangkap keadaan itu.

**Usul, sebagai bagian pekerjaan ini:** satu tes PHPUnit yang menuntut baris
`sop:rule-scan` terdaftar dan aktif di tabel jadwal. Poin (2) tetap tindakan
manusia — dicatat sebagai langkah manual sekali, bukan dianggap terbukti.

### 8.2 Digest reminder

Pengirimannya lewat jalur antrean/notifikasi. `QUEUE_CONNECTION=sync` membuat
jobnya berjalan inline, tapi yang diuji jadi "job dipanggil", bukan "orang
menerima". Di luar lingkup; `Alur047NotifikasiTest` sudah memegang sisi
notifikasi in-app.

### 8.3 Gerbang tenggat absolut (`tertahan_tenggat`)

Paket dengan `deadline_mode = ABSOLUTE` bertanggal lampau akan **tertahan**.
Kait `data-uji="tertahan-tenggat"` sudah ada dan Vitest sudah menjaganya di
jsdom. Sengaja tidak diangkat ke peramban: menambah satu perjalanan lagi
memperpanjang jalan-uji yang menulis ke DB dev, dengan tambahan keyakinan yang
kecil. Dicatat sebagai keputusan, bukan kelalaian.

---

## 9. Tabrakan dengan sesi lain

Sesi `hcis-w-3-51` sedang merancang E2E untuk "living document"
(`SopReconciliationService`) di atas harness yang sama. Pembagian yang sudah
saya kirimkan dan menunggu jawabannya:

| Wilayah | Pemegang |
|---|---|
| `Services/LMS/SOP/Uat/Skenario/`, `UatPembangunDunia` | sesi 51 — fondasi, dipakai keenam spec lama |
| `playwright.config.js` (proyek queue-worker kedua) | sesi 51 |
| `UatTabelIzin.php`, `SopUatDunia.php` | siapa pun, asal `N_SOP_Assignment_Rule_Paket` masuk daftar-putih |
| `.env.uat` | siapa pun duluan; yang lain **menambah** kunci, tidak menimpa |
| spec 024/025, `tests/e2e/support/otomasi.js` (baru), kait `data-uji` | sesi ini |

`tests/e2e/support/uat.js` yang sudah ada **tidak** saya sentuh — helper baru
duduk di berkas sendiri supaya dua sesi tidak menyunting penopang yang sama.

**Urutan yang disarankan:** kalau sesi 51 benar membongkar `UatPembangunDunia`,
fondasi itu selesai lebih dulu dan spec ini menempel ke bentuk barunya.
Menjalankan keduanya paralel berarti dua sesi menyunting pembangun dunia yang
sama.

---

## 10. Kriteria selesai

1. `.env.uat` ada dan `sop:uat-sesi` mencetak cookie untuk ketiga peran.
2. Spec 000 (penopang) hijau — kalau ia merah, sisanya tak berarti apa-apa.
3. Spec 024 dan 025 hijau lewat vhost Laragon.
4. `sop:uat-bersihkan` menghabiskan jurnal tanpa sisa; `--dry-run` sesudahnya
   melaporkan jurnal kosong, dan tak ada aturan bertanda jalan-uji tersisa di
   `/sop/admin/kebijakan`.
5. Penjaga §4.1 pernah dilihat **MERAH** dengan perbaikannya dicabut.
6. `sop:uat-laporan --playwright=storage/app/uat-auto/playwright.json`
   menandai 024 dan 025 **PASS**, berlapis `peramban`.
7. Suite penuh dijalankan sekali — bukan cuma berkas yang disentuh. Perubahan
   `UatTabelIzin` dan `SopUatDunia` menyentuh setiap pemanggil harness UAT.
   Baseline yang sudah diketahui: ~19 kegagalan non-SOP yang memang sudah ada
   sebelum pekerjaan ini.

---

## 11. Risiko

| Risiko | Penanganan |
|---|---|
| Cohort meleset → penugasan massal ke karyawan nyata di DB dev | Gerbang `matched === 1` (§2.1); aturan tak pernah diaktifkan kalau meleset |
| Jalan-uji mati di tengah meninggalkan aturan aktif | `sop:uat-bersihkan` bisa dijalankan manual dengan `--semua`; jurnal ditulis ke disk per baris, bukan ditahan di memori |
| RCSI mati di `Web_HRIS` → spec memperlambat orang yang bekerja | Pilih `Web_HRIS_Cut_Off`; kalau tidak, jalankan di luar jam kerja |
| `npm run dev` meninggalkan proses vite yatim → worker Playwright mati `3221225794` | Pastikan tak ada vite berjalan dari worktree lain sebelum menjalankan suite |
| `--reporter=line` menimpa reporter JSON | Jangan tambahkan `--reporter` di baris perintah; `sop:uat-laporan` akan diam-diam membaca hasil jalan sebelumnya |
| Dua worktree `npm run dev` berebut port 5173 | Gejalanya halaman reload tiap 2 detik dan JS dari worktree lain; matikan yang tak dipakai |
