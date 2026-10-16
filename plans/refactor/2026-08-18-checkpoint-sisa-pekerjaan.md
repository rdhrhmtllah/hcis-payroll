# Checkpoint: sisa pekerjaan setelah alignment struktur

Tanggal: 2026-08-18
Basis: `master` — seluruh alignment struktur sudah masuk
Status: `STRUCTURE_DONE_OPEN_ITEMS_QUEUED`
Pembaruan: 2026-08-18 — T2, T3, T4, T6, dan T7 ditutup; lihat catatan di tiap tugas.

Dokumen ini membagi sisa pekerjaan menjadi tugas-tugas yang dapat dikerjakan
paralel di worktree terpisah. Setiap tugas ditulis agar dapat dikerjakan tanpa
konteks percakapan sebelumnya: masalahnya, bukti yang sudah dikumpulkan,
definisi selesai, dan gerbang verifikasinya.

## Cara memulai sebuah tugas

`master` bersih dan identik dengan `origin/master`, sehingga setiap tugas boleh
bercabang penuh dari sana.

```bash
git fetch origin
git worktree add .worktrees/<nama-branch> -b <nama-branch> origin/master
cd .worktrees/<nama-branch>
npm install
```

`.worktrees/` sudah tercantum di `.gitignore`. Jangan memakai direktori worktree
yang tidak diabaikan git — isinya akan ikut ter-commit.

## Gerbang verifikasi yang berlaku untuk semua tugas

Jalankan sebelum menyatakan selesai. Ambil baseline lebih dulu bila tugasnya
memindahkan berkas.

```bash
composer dump-autoload -o --strict-psr          # harus nol pelanggaran
php scripts/verify-references.php --max=0       # simbol App\ yang tidak resolve
php scripts/verify-frontend-imports.php         # jalur impor yang putus
php scripts/verify-inertia-pages.php            # nama halaman tanpa berkas
php scripts/route-snapshot.php /tmp/routes.txt  # bandingkan dengan baseline
php scripts/console-snapshot.php /tmp/cmd.txt   # bandingkan dengan baseline
./vendor/bin/phpunit --testsuite Unit           # 98/98
npx vitest run                                  # 103 berkas, 737 tes
npm run build
```

Angka acuan pada `master` per pembaruan terakhir: 1173 route, 170 perintah
artisan, referensi tidak resolve 0, impor frontend putus 0, halaman tidak
tertaut 0.

Suite `Feature` **tidak** dipakai sebagai gerbang lulus/gagal: ia memerlukan
database hidup, berjalan lebih dari sembilan menit, dan sudah gagal sebagian
sebelum pekerjaan ini karena sebab lingkungan. Lihat "Kendala lingkungan".

---

## T1 — Rotasi kunci service account GCP

**Prioritas: tertinggi. Bukan pekerjaan kode.**

Berkas kunci sempat ter-commit dan ter-push ke `origin/master` lewat commit
merge `2b5bc9ba`. Pelacakannya sudah dilepas, tetapi blob-nya tetap terjangkau
lewat SHA di riwayat sampai GitHub menjalankan garbage collection.

Selesai bila: kunci lama dicabut di Google Cloud Console, kunci baru diletakkan
di `secret/` (sudah diabaikan git dan Docker), dan `config/filesystems.php`
menunjuk path yang benar.

Pertimbangkan mengaktifkan GitHub Secret Scanning dan Push Protection agar
kejadian serupa ditolak otomatis.

---

## T2 — Error runtime halaman SOP Builder

**Status: DITUTUP — tidak tereproduksi. Artefak dev server, bukan cacat kode.**

Gejala aslinya:

```
TypeError: Cannot read properties of null (reading 'ce')
    at renderSlot (runtime-core.esm-bundler.js:2961)
    at Proxy._sfc_render (AppShell.vue:9)
```

Baris 2961 membaca `currentRenderingInstance.ce`; nilainya null. Baris 9
`AppShell.vue` saat itu adalah `<slot />`.

**Tiga lapis pembuktian, semuanya bersih:**

| Lapis | Cara | Hasil |
|---|---|---|
| jsdom | `mount(AppShell, { slots: { default: () => h(SopBuilder) } })` | render tanpa TypeError |
| build produksi | `npm run build` | hijau |
| peramban sungguhan | Playwright + sesi admin, memuat `/sop/admin/builder` di lingkungan dev | render bersih, props asli, tanpa TypeError |

**Hipotesis tambahan yang digugurkan pada putaran ini:**

| Hipotesis | Bukti yang menggugurkannya |
|---|---|
| Dep ditemukan terlambat lalu Vite re-optimize | Dev server dingin: seluruh dep (termasuk `quill`, `vue3-apexcharts`, `@mediapipe/face_detection`) sudah masuk pemindaian awal karena `import.meta.glob` menyeret semua halaman. Meminta modul SopBuilder tidak memicu re-optimisasi apa pun |
| Dua instance Vue antar modul | Modul yang benar-benar dilayani dev server: `AppShell.vue` dan `SopBuilder.vue` sama-sama mengimpor `/node_modules/.vite/deps/vue.js?v=0cf09b1e` — hash identik |

Catatan atas alasan lama: "dev server basi" pernah dicatat **digugurkan** karena
`/absensiSales` normal pada sesi yang sama. Alasan itu keliru — kebasian HMR
bersifat per-modul, bukan global, sehingga satu halaman bisa rusak sementara
yang lain normal. Itulah satu-satunya penjelasan yang tersisa dan konsisten
dengan seluruh bukti.

**Bila muncul lagi:** muat ulang keras, atau restart dev server. Kalau bertahan
setelah restart bersih, bukti di atas gugur dan perlu penyelidikan baru — mulai
dari konsol lengkap sejak baris pertama.

Yang justru nyata dan sudah diperbaiki dari diagnosa ini: dua peringatan Vue di
halaman itu. Lihat T6.

---

## T3 — Otorisasi endpoint api/v1 LMS Training

**Status: SELESAI.**

Delapan belas endpoint yang sebelumnya hanya `auth` kini menuntut izin halaman.
Sebelumnya setiap pengguna yang sudah login bisa memanggil `store`, `update`,
`toggle`, `bulk-toggle`, dan `reassign-third-party`.

Penyisiran pemanggil menentukan bentuk penjagaannya:

| Endpoint | Pemanggil | Middleware sekarang |
|---|---|---|
| `api/v1/lms/penjadwalan/*` (15) | hanya `TrainingSchedulingPage.vue` | `check.jabatan:LMSPenjadwalanPage` |
| `api/v1/training/kategori/current` | `TrainingCategoryPage.vue` **dan** `AdminTrainingListPage.vue` | `check.jabatan:LMSTrainingKategoriPage,LMSAdminTrainingPage` |
| `api/v1/training/kategori/store`, `/update` | hanya `TrainingCategoryPage.vue` | `check.jabatan:LMSTrainingKategoriPage` |

Grup kategori **dipecah** justru karena pembacaannya punya dua pemanggil dengan
izin berbeda: satu `check.jabatan:LMSTrainingKategoriPage` untuk seluruh grup
akan mematikan daftar kategori di layar Admin Training bagi orang yang punya
akses admin training tetapi bukan master kategori. `CheckJabatan` menerima
beberapa halaman dengan semantik ATAU (lihat `app/Http/Middleware/CheckJabatan.php`).

Verifikasi: snapshot route tetap 1173 — tidak ada rute hilang atau bertambah,
hanya middleware yang berubah. Keempat halaman LMS Training dimuat di peramban
dengan sesi admin dan seluruh panggilan `api/v1`-nya mengembalikan 200.

Catatan pemeriksaan: probe pertama sempat menunjukkan `/lms/admin/training`
memanggil endpoint penjadwalan. Itu keliru — XHR halaman sebelumnya baru
mendarat sesudah navigasi di tab yang sama. Diuji ulang dengan konteks peramban
terpisah per halaman, panggilan itu tidak ada.

**Yang masih perlu ditegaskan pemilik produk:** apakah ada integrasi non-peramban
(skrip, Postman, layanan lain) yang memakai endpoint ini. Penyisiran kode tidak
menemukan satu pun, tetapi kode tidak bisa membuktikan ketiadaan pemanggil di
luar repo.

---

## T4 — Lima kelas model yang dirujuk tetapi tidak ada

**Status: DITUTUP — tidak ada yang perlu dibuat.**

`App\Models\Cuti`, `PengajuanCuti`, `SaldoCuti`, `HCIS\CUTI\CutiBersama`, dan
`KpiUser` diverifikasi ulang pada 2026-08-18: **nol rujukan** di `app/`,
`routes/`, `tests/`, `config/`, dan `database/`, dan **nol berkas** dengan nama
itu di `app/Models/`.

Seluruh pemakainya sudah terbuang bersama generasi lama fitur cuti.
`verify-references.php --max=0` menjadi gerbang yang menahan kemunculannya lagi:
rujukan baru ke kelas yang tak ada akan langsung menggagalkannya.

Bila suatu saat model-model ini memang dibutuhkan, pembuatannya tetap tunduk
pada workflow DDL di `AGENTS.md` — nama tabel dan kolom diverifikasi ke database,
tidak dikarang.

---

## T5 — Migrasi komponen legacy ke design system

**Status: BERJALAN — 1 dari 9 komponen selesai.**

`modalV1` selesai 2026-08-18: isinya dibangun ulang di atas `HcisDialog`
dan `HcisButton`, API publiknya dipertahankan persis, dan **tidak satu pun dari
15 pemanggil** perlu diubah karena prop atau slotnya.

Satu pemanggil tetap harus disentuh, dan alasannya penting untuk migrasi
berikutnya: `masterPenjadwalan.vue` melebarkan modal dan mematikan penutupan
lewat backdrop dengan menembak kelas INTERNAL `modal-dialog` memakai `:deep()`.
Kelas itu lenyap begitu isinya diganti, jadi aturannya mati diam-diam. Dua prop
DITAMBAHKAN — `size` dan `closeOnOverlay`, keduanya berbawaan sama dengan
perilaku lama — dan ketiga aturan `:deep()` itu dihapus.

### Audit kopling `:deep()` — kerjakan SEBELUM menyentuh komponen mana pun

Ini penyebab kerusakan diam yang paling mahal: pemanggil menembak kelas internal
komponen legacy, dan kelas itu tidak ada lagi setelah isinya dibangun ulang.
Hasil penyisiran seluruh pemanggil, kelas Bootstrap generik (`row`, `btn`,
`badge`, `form-control`) sudah dibuang karena tetap ada setelah migrasi:

| Komponen | Pemanggil | Yang akan rusak diam-diam |
|---|---|---|
| `DetailApprovalModal` | 3 | — |
| `formV1` | 6 | — |
| `listAccordionUiV1` | 8 | — |
| `modalV1` | 15 | **selesai** (`modal-dialog` di masterPenjadwalan) |
| `table-noir` | 21 | KuotaManagement (`btn-modern`, `empty-state`, `page-btn`, `page-info`, `pagination-container`); PermitStatusList (`card-status`, `card-status-indicator`, `data-card`, `modern-table`) |
| `toggleSectionV2` | 32 | CutiBersamaAdvanced (`approval-tabs-container`, `tab-button`, `tab-content`, `tabs-header`) |
| `Filter/modernFilter` | 32 | ExportTrainingModal (`desktop-filters-grid`, `filter-label`, `filter-search`, `search-input-enhanced`) |
| `header` | 39 | — |
| `modalV2-noir` | 50 | KuotaManagement (`btn-modern`, `loading-spinner`, `modal-footer`); CutiBersamaAdvanced (`modal-body`, `modal-footer`, `modal-header`, `modal-title`) |

Enam pemanggil tersisa yang perlu ditangani bersamaan dengan komponennya.

Jebakan kedua yang sudah terbukti: pemanggil mengirim `icon` berupa kelas
Bootstrap utuh (`'bi bi-activity'`), sedangkan komponen design system
meneruskannya ke `<HcisIcon :name="...">` yang mengharap nama ikon. Lewatkan
lewat slot `#icon`, jangan lewat prop.

Jebakan ketiga, baru dari `modalV1`: `HcisDialog` mem-`Teleport` isinya ke
`body`. Aturan `:deep()` milik pemanggil dikompilasi menjadi
`[data-v-pemanggil] .kelas`, dan setelah di-teleport tidak ada lagi leluhur
ber-`data-v-pemanggil` — jadi aturannya berhenti cocok bahkan bila nama
kelasnya dipertahankan. Ubah jadi prop, jangan dipaksa lewat CSS.

Urutan berikutnya, dari paling mapan ke paling berisiko: `listAccordionUiV1`
(8, nol kopling) → `DetailApprovalModal` (3, nol kopling) → `formV1` (6, nol
kopling) → `header` (39, nol kopling) → `table-noir` (21, dua pemanggil) →
`toggleSectionV2` (32, satu pemanggil) → `modernFilter` (32, satu pemanggil) →
`modalV2-noir` (50, dua pemanggil).

Catatan: 59 dari 61 komponen `Hcis*` belum dipakai di luar katalog design
system. Analisis keterjangkauan tidak boleh dijalankan atas komponen design
system tanpa memperlakukan `registry.js` dan katalog sebagai akar.

---

## T6 — Peringatan Vue yang tersisa di halaman lain

**Status: SELESAI — diukur, bukan diperkirakan.**
 Seluruh 94
halaman Inertia tanpa parameter dibuka di peramban sungguhan (Playwright, sesi
admin) dan konsolnya direkam: **183 temuan di 64 halaman**.

Sebagian besar bukan sekadar kebisingan. Yang diperbaiki:

| Temuan | Akibat sebenarnya |
|---|---|
| `Overtime`: blok "History Detail Modal" 155 baris | Memakai `<Modal>` dan `<Badge>` yang tak pernah diimpor dan `showHistoryDetail` yang nol kemunculan di `<script>`. Vue tetap me-resolve komponennya di awal render, di luar `v-if`, jadi 18 peringatan per muat halaman. Blok dihapus. |
| `addKaryawanTeam`: 4 properti dipakai template, nol di `data()` | Menulis ke properti yang tak ada di `data()` tidak membuatnya reaktif. Filter tanggal di-`v-model` ke sana dan **tidak pernah berlaku**; modal detail tak ikut berubah saat `currentDetail` diisi. |
| `addKaryawanTeam`: `<edit />` | Ikon Element Plus yang tak pernah diimpor — tidak pernah tampil. |
| `CrossDivisionReview`: `<Textarea>` | Huruf besar membuatnya diperlakukan sebagai komponen; isian "Keterangan Penilaian" **tidak dirender sama sekali**. |
| `CrossDivisionReview`: `completedRatings` computed **dan** data | Data menang atas computed di Vue, jadi computed-nya tak pernah dievaluasi dan layar menampilkan **jumlah mentah diikuti tanda persen** (mis. "3%" untuk 3 dari 12). Computed diganti nama jadi `persenSelesai`. |
| `IzinAtasanApproval`: `<apexchart>` | Tak pernah didaftarkan — ketiga grafik di halaman itu tidak muncul. |
| `IzinAtasanApproval`: `dayNight: "day"` | Validator `HeaderKpi` menuntut `Siang`/`Malam`, jadi selalu ditolak. Diganti computed berbasis jam seperti puluhan halaman lain. |
| `SelfShift`: `backdate_max_days` | Ada di dalam prop `config`; dipakai bare sehingga kartu "Backdate Policy" tak pernah tampil. |
| `Overtime`/`OvertimeAdmin`: `shiftList` di Props **dan** Data | Data menang, jadi payload dari server tak pernah terbaca. Prop dilepas, dan payload beserta query SQL di baliknya dibuang dari `OvertimeController` — query itu jalan tiap muat halaman tanpa pembaca. |
| `modernFilter`: `:icon="RefreshRight"` | Terdaftar di `components:` sehingga bentuk tag bekerja, tetapi `:icon="..."` diselesaikan terhadap instance. Diekspos lewat computed. |
| `SopModal`, `PermitForm`, `TamuForm`: `emits` tak dideklarasikan | Listener jatuh ke atribut elemen; komponen berakar fragment sehingga Vue memperingati tiap pemasangan. |
| `table-noir`: validator `columns` | `col.key && col.title` menolak kolom checkbox dan aksi yang memang berjudul kosong. |

### Hasil terukur

Sapuan ulang atas 29 halaman yang sama, dibandingkan setara:

| Kelas | Sebelum | Sesudah |
|---|---|---|
| binding tak terdefinisi | 59 | 0 |
| komponen tak terdaftar | 24 | 0 |
| emits tak dideklarasikan | 14 | 0 |
| prop salah tipe | 16 | 2 |
| prop wajib hilang | 4 | 0 |
| **total** | **124** | **8** |

Sapuan verifikasi akhir atas 12 halaman inti: 48 → 4, dan keempat sisanya
adalah galat 500 dari server, bukan Vue.

### Yang SENGAJA tidak dibungkam

`<lord-icon>` dipakai 15 kali di 7 komponen dan skrip `lordicon.js` **tidak
dimuat** di `resources/views/app.blade.php` — ia hanya ada di layout blade
warisan. Jadi elemennya inert di setiap halaman Inertia: ikonnya tidak pernah
muncul. Mendeklarasikannya sebagai custom element menghilangkan peringatannya
tanpa memperbaiki apa pun. Keputusannya: muat skripnya, atau ganti dengan
`HcisIcon`. Yang kedua sejalan dengan arah menjauh dari CDN.

### Temuan baru yang belum ditangani

- `PAGEERROR` apexcharts "Element not found" di `/homepage`,
  `/homepage/leader-dashboard`, dan `/leader-shift/admin` — grafik dipasang ke
  simpul DOM yang belum atau sudah tidak ada.
- `PAGEERROR` PerfectScrollbar di `/summary-review`, berasal dari pipeline aset
  blade warisan (`assets/compiled/js/app.js`), bukan dari Vue.
- HTTP **500** saat mengambil daftar data di `/akses-user/divisi` dan
  `/akses-user/level`. Ada sejak sapuan pertama, sebelum perubahan apa pun.
- HTTP 403 pada satu sumber daya di `/admin/activity` dan
  `/admin/system-manager` — kemungkinan memang batas izin, perlu dipastikan.

---

## T7 — Kerapuhan yang tersisa pada berkas route

**Status: DITUTUP.**

Semua berkas route modul yang dimuat dari grup `auth` di `web.php` sudah
mendeklarasikan `auth` sendiri, sehingga proteksinya tidak bergantung pada
posisi `require`.

Dua hal yang sengaja dibiarkan sudah ditinjau:

- `SYSTEM/TaskProcessing.php` memakai middleware khusus sendiri
  (`outbox.dispatcher.auth`, `outbox.handler.auth`). Aman apa adanya.
- Grup `api/v1` pada berkas LMS Training — **selesai lewat T3**.

---

## Yang tersisa dan boleh dikerjakan paralel

Setelah T2, T3, T4, T6 (enam kelas terbesar), dan T7 ditutup, yang tersisa:

| Kelompok | Tugas | Berkas yang disentuh |
|---|---|---|
| A | T1 — rotasi kunci GCP | tidak menyentuh kode aplikasi |
| B | T5 — migrasi legacy | `resources/js/components/legacy/**` dan pemanggilnya |
| C | T6 sisa (a)–(d) | halaman Vue yang terdaftar, satu per satu |
| D | T6 sisa (e) — 500 & SQL | controller dan query di sisi server |

Kelompok B dan C sama-sama menyentuh halaman Vue. Bila dikerjakan bersamaan,
batasi C pada halaman yang tidak sedang dimigrasikan B — daftar pemanggil tiap
komponen ada di tabel T5.

Kelompok A dan D tidak beririsan dengan yang lain.

---

## Kendala lingkungan yang bukan bug

Jangan menghabiskan waktu mengejar hal-hal berikut sebagai kerusakan:

- `php artisan route:list` tidak dapat dijalankan; `RouteListCommand`
  me-resolve controller dan memicu kegagalan Google ADC lewat `CloudTaskService`.
  Pakai `scripts/route-snapshot.php`.
- `pdo_sqlite` tidak terpasang, sehingga 30 tes `HomepageControllerTest` selalu
  error.
- Tiga tes SOP gagal 500 karena `config/filesystems.php` menunjuk path
  kredensial GCS bergaya Windows (`C:\gKey\...`) yang dijalankan di Linux.
- `KpiLifecycleE2ETest` bypass endpoint 500, dan `LmsMateriLinkUjianTest` gagal
  karena bentuk data di database development.
- Baseline suite Feature: 601 tes, 30 error, 5 gagal, 45 skip.
