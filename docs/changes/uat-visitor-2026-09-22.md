# UAT Visitor — Permit, Tamu, Tamu B2B (2026-09-22)

Cabang `feat/vms/akses-inspeksi-palang` (`9f5bffc0`). Semua pekerjaan permit,
kartu, QR, filter tanggal, dan akses palang sudah termuat di cabang ini —
`feat/vms/kartu-batch-filter-tanggal` dan `master` nol commit tertinggal.

**Basis URL:** `http://hcis-w-3.test` · **Vite:** hidup di `:5176`

Tiap langkah menyebut **apa yang harus terlihat**. Kalau yang muncul lain,
catat nomor langkahnya — itu cukup untuk melacaknya.

---

## 0. Sebelum mulai

| Cek | Cara | Harus |
|---|---|---|
| Vite jalan | buka halaman apa saja, lihat console | tak ada `ERR_CONNECTION_REFUSED :5176` |
| Slot inspeksi ada | `/transaksipermit` → **Buat Baru** | pilihan jadwal terisi (dev: 66 slot / 14 hari) |
| Peran siap | 3 akun: **pemohon** (karyawan biasa), **SHE/verifikator**, **admin** | ketiganya bisa login |

Dua perintah bantu selama UAT:

```bash
php artisan visitor:expire-efektif      # memaksa sapuan kedaluwarsa SEKARANG
php artisan visitor:simulasi-palang     # meniru aplikasi pihak ketiga menarik data
```

> **Kedaluwarsa berjalan sendiri tiap 5 menit.** `visitor:expire-efektif`
> terdaftar aktif di `N_HRIS_Scheduler_Configs` dengan cron `*/5 * * * *`
> (penjadwalan HCIS berbasis tabel, bukan di kode). Jadi baris yang jendelanya
> lewat akan berubah `EXPIRED` sendiri dalam ≤5 menit — jangan kaget kalau
> status berubah saat Anda sedang memeriksa. Jalankan perintahnya manual hanya
> bila tidak mau menunggu.

---

## A. PERMIT — jalur utama (pemohon → SHE → kartu)

### A1. Ajukan permit + pesan slot inspeksi
1. Login **pemohon** → `/transaksipermit` → tab **Buat Baru**.
2. Isi kendaraan, pilih kategori & durasi, lalu **pilih slot inspeksi**.
3. Submit.

**Harus:** pengajuan tersimpan, muncul di tab **Riwayat Permit** dengan status
menunggu, dan jadwal inspeksinya tampil.

> **Yang baru dan paling perlu diperhatikan:** sejak submit, barisnya **sudah
> masuk** `N_VISITOR_Permit_Efektif` dengan jendela **slot ± 15 menit**,
> durasi `Akses Inspeksi`, kategori `AKSES INSPEKSI - BELUM DISETUJUI`.
> Ini yang memberi pemohon satu kali buka palang untuk menemui SHE.

Periksa dengan:

```bash
php artisan tinker --execute="dump(DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi','PMT...')->first());"
```

**Harus:** `Status_Efektif=AKTIF`, `Flag_Sync=T`, `Flag_Batal=T`,
`Nama_Durasi='Akses Inspeksi'`, `Waktu_Mulai_Berlaku` = jam slot − 15 menit.

### A2. Pass Digital sebelum disetujui
4. Tab **Pass Digital**.

**Harus:** jadwal inspeksi yang barusan dipesan **tampil** di sana (dulu tidak
muncul karena bundle basi — kalau kosong, pastikan `npm run dev` jalan).

### A3. Geser jadwal
5. Masih sebagai pemohon, pindahkan jadwal inspeksi ke slot lain.

**Harus:** baris lama tertutup, baris `N_VISITOR_Permit_Efektif` **tidak
bertambah** — jendelanya bergeser di baris yang sama, dan `Flag_Sync` kembali `T`.

### A4. SHE menyetujui
6. Login **SHE** → `/verifikator-visitor/permit` → buka pengajuan → isi
   checklist → **Approve**.

**Harus:** baris `N_VISITOR_Permit_Efektif` yang **sama** (jangan bertambah)
kini memakai kategori & durasi asli (mis. `HIJAU` / `3 Bulan`), masa berlaku
penuh, dan **`Flag_Sync` kembali `T`** — ini perbaikan yang baru saja dibuat;
sebelumnya persetujuan tidak pernah sampai ke palang.

### A5. Cetak kartu (pemohon)
7. Tab **Pass Digital** → **Cetak**.

**Harus:** PDF terbuka di HcisFileViewer, bisa diunduh, **Template 2** yang
tampil (bar jenis permit rata kiri, blok plat tidak menempel ke bar di atasnya).
Untuk membandingkan Template 1, tambahkan `?template=1` pada URL kartu.

### A6. Keaslian kartu — pintu karyawan
8. Masih sebagai pemohon: tombol **Cek Keaslian Kartu Permit** di
   `/transaksipermit` → scan QR kartunya sendiri.

**Harus:** terbaca **sah**, identitas tampil.

9. Scan QR kartu **milik orang lain**.

**Harus:** ditolak dengan **BUKAN KARTU ANDA**, dan **tidak satu pun data
identitas** ikut tampil.

10. Scan QR dengan kamera HP biasa / Google Lens.

**Harus:** yang muncul hanya deretan **32 karakter heksadesimal** — bukan
tautan, bukan nomor permit, dan tidak ada halaman yang terbuka.

---

## B. PERMIT — jalur admin & sisi gelap

### B1. Cetak dari monitoring
11. Login **admin** → `/monitoring-permit` (All Monitoring) → cetak satu kartu.

**Harus:** PDF sama dengan milik pemohon.

### B2. Cetak borongan
12. Centang beberapa baris → **Cetak Bundel**.

**Harus:** satu PDF, **2 kartu per halaman A4**, pemisah halaman jatuh setelah
kartu genap. Baris non-APPROVED yang ikut tercentang **dilewati diam-diam**,
tidak menggagalkan seluruh cetakan. Batas 60 id per permintaan.

### B3. Filter tanggal
13. Pakai filter rentang, mode **tanggal approve**, lalu mode **masa berlaku**.

**Harus:** kedua mode memberi hasil berbeda dan masuk akal; tanggal yang tidak
bisa dibaca **diabaikan** (bukan galat, bukan hasil kosong).

### B4. Keaslian kartu — pintu admin
14. Scanner di `/monitoring-permit` dan `/verifikator-visitor/permit`.

**Harus:** bisa memindai kartu **siapa saja** (beda dari langkah 9).

### B5. Pencabutan sampai ke palang
15. SHE mencabut satu permit yang sudah aktif (`/verifikator-visitor/permit`,
    satuan **dan** bulk revoke).

**Harus:** `Status_Efektif=DIBATALKAN`, **`Flag_Batal=Y`**, **`Flag_Sync=T`**.
Di layar HCIS statusnya **Dicabut**. Scan kartunya → terbaca **tidak berlaku**
walaupun kertasnya mulus.

### B6. Penolakan mematikan akses inspeksi
16. Ajukan permit baru + slot (langkah A1), lalu **tolak / batalkan** sebelum
    disetujui.

**Harus:** barisnya jadi `Status_Efektif=EXPIRED` (bukan `DIBATALKAN`),
**`Flag_Batal=Y`**, **`Flag_Sync=T`**. Di layar HCIS **tidak** boleh terbaca
"Dicabut" — ini disengaja: `permitStatusSql()` memeriksa `DIBATALKAN` paling
awal, jadi memakai status itu akan salah sebab.

### B7. Jadwal lewat tanpa direview
17. `/agenda-inspeksi` sebagai SHE → panel **belum dicatat**.

**Harus:** pengajuan yang slotnya sudah lewat **disorot** saja. Tidak ada yang
ditutup otomatis, tidak ada status yang berubah sendiri — sesuai keputusan Anda.
Pemohon boleh memesan ulang tanpa terkena aturan H-1 maupun kuota 2×, dan baris
lamanya tertutup sebagai `KEDALUWARSA` (tidak dihitung sebagai mangkir).

---

## C. TAMU REGULER

> **Catatan:** bagian ini menguji perubahan yang baru masuk di commit
> `e2e7e850` — saringan berkendara/tanpa kendaraan dan urutan daftar yang
> dibalik jadi menaik (`JadwalKedatanganTamuController`), riwayat tamu
> berbentuk kartu (`TamuHistory.vue`, +605 baris), serta pemotongan query
> string saat menebak nama berkas pratinjau (`HcisFileViewer.vue`,
> `TamuReviewModal.vue`). Kalau ada yang aneh di langkah C, tersangka
> pertamanya commit itu, dan ia bisa dibalik sendiri tanpa menyentuh
> pekerjaan permit.

18. Login **PIC** → `/transaksitamu` → daftarkan tamu (dengan kendaraan).
19. `/jadwal-kedatangan-tamu` → coba filter **Berkendara** dan **Tanpa
    Kendaraan**; perhatikan juga **urutan daftar sekarang menaik**.
20. Login **verifikator** → `/verifikator-visitor/tamu` → setujui.

**Harus:** `N_VISITOR_Tamu_Efektif` terisi lengkap — `Waktu_Estimasi_Masuk`,
`Waktu_Estimasi_Keluar` (perhatikan kata **Estimasi**; kolom aktual terpisah),
`Tamu_Kategori`, `B2B_Unit`, `Path_Foto_Kendaraan` — dengan `Flag_Sync=T`,
`Flag_Batal=T`.

21. Ubah sesuatu pada kunjungan yang **sudah pernah ditarik palang**
    (jalankan `visitor:simulasi-palang` dulu supaya `Flag_Sync` jadi `Y`).

**Harus:** sesudah perubahan, **`Flag_Sync` kembali `T`**. Ini perbaikan yang
baru dibuat; sebelumnya perubahan pada baris yang sudah tersinkron tidak pernah
sampai ke gerbang, tanpa galat apa pun.

22. PIC **membatalkan sendiri** kunjungannya.

**Harus:** `Status_Efektif=DIBATALKAN`, `Flag_Batal=Y`, `Flag_Sync=T`, dan
header `Status_Kunjungan=REVOKED`.

23. `/laporanvisitor-papan-harian` dan `/monitoring-tamu`.

**Harus:** kunjungan tampil dengan status yang benar; yang dicabut terbaca
**DICABUT**, bukan sekadar hilang.

---

## D. TAMU B2B

### D1. Lewat layar internal
24. `/transaksi-tamu-b2b` → daftarkan → `/verifikator-visitor/b2b` → setujui →
    `/monitoring-b2b`.

**Harus:** sama seperti tamu reguler, plus `B2B_Unit` terisi.

### D2. Lewat API pihak ketiga
Konsumen `B2B` sudah terdaftar dan kelima endpoint sudah di-whitelist.
Penanda tangan HMAC sudah disiapkan:

```bash
# Windows/Git Bash: awali MSYS_NO_PATHCONV=1 supaya path tidak diubah shell
MSYS_NO_PATHCONV=1 php storage/framework/cache/kirim_api_b2b.php \
  POST /api/v1/hcis/visitor/tamu-b2b '{"...":"..."}'

MSYS_NO_PATHCONV=1 php storage/framework/cache/kirim_api_b2b.php \
  GET /api/v1/hcis/visitor/tamu-b2b/VSR0926-0001
```

25. Ajukan lewat `POST /tamu-b2b`, lalu cek `GET /tamu-b2b/{no}`.

**Harus:** transaksinya bertanda **B2B Procurement**; statusnya bisa dibaca
kembali; `DIBATALKAN` terbaca sebagai `REVOKED` di respons.

26. Kirim ulang permintaan dengan **nonce yang sama**.

**Harus:** `401 Duplicate request` — anti-replay bekerja.

27. `POST /permit-tamu` dan `POST /permit-tamu/{no}/batal` (permit coklat).

**Harus:** pembatalan lewat API juga menghasilkan `Flag_Batal=Y` + `Flag_Sync=T`.

---

## E. Kontrak palang (menyeluruh)

28. Setelah semua langkah di atas, jalankan pemeriksaan penutup:

```sql
-- keduanya HARUS 0
SELECT COUNT(*) FROM N_VISITOR_Permit_Efektif
 WHERE (Status_Efektif = 'DIBATALKAN' AND Flag_Batal <> 'Y')
    OR (Status_Efektif <> 'DIBATALKAN' AND Flag_Batal = 'Y');

SELECT COUNT(*) FROM N_VISITOR_Tamu_Efektif
 WHERE (Status_Efektif = 'DIBATALKAN' AND Flag_Batal <> 'Y')
    OR (Status_Efektif <> 'DIBATALKAN' AND Flag_Batal = 'Y');
```

Kalau salah satu tidak nol, ada jalur penulisan yang belum patuh — catat
`No_Transaksi`-nya dan aksi mana yang mendahuluinya.

---

## C4. Jadwal yang digeser setelah kedaluwarsa

24. Daftarkan tamu bertanggal **kemarin**, tunggu ≤5 menit (atau jalankan
    `visitor:expire-efektif`) sampai barisnya `EXPIRED`, lalu **geser
    tanggalnya ke lusa** dan simpan.

**Harus:** baris `N_VISITOR_Tamu_Efektif` kembali `AKTIF` dengan jendela baru
dan `Flag_Sync='T'`. Sebelum perbaikan `732bc950`, jadwal barunya berhenti di
HCIS dan tamunya ditolak di pos tanpa satu pun galat.

25. Sebaliknya: tamu yang sudah **check-out** (aksesnya diakhiri lebih awal),
    lalu sunting hal lain pada kunjungan itu — misalnya daftar anggota — tanpa
    mengubah tanggalnya.

**Harus:** barisnya **tetap `EXPIRED`**. Akses tamu yang sudah pulang tidak
boleh terbuka lagi hanya karena kunjungannya disunting.

---

## Yang sudah diketahui, tidak perlu dilaporkan

- **Kartu lama tidak dikenali.** Kartu yang tercetak sebelum DDL-2026-127
  memakai format kode lama dan perlu dicetak ulang.
- **PDF kartu ±880 KB** karena font DejaVu ditanam di Blade.
