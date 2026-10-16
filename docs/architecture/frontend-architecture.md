# KPI-Unreal Frontend Architecture and Engineering Standard

Status: **APPROVED_BASELINE**

Dokumen ini adalah standar frontend resmi KPI-Unreal untuk pekerjaan baru dan perubahan fitur yang memiliki scope eksplisit. Standar ini tidak memerintahkan migrasi, pemindahan, rename, atau refactor menyeluruh terhadap file legacy. Legacy tetap berjalan dengan pola existing sampai ada task migrasi yang terpisah, memiliki scope, test, dan approval.

## 1. Scope dan prinsip utama

Frontend KPI-Unreal menggunakan Vue 3 + Inertia Vue 3 dengan Vite. Standar ini berlaku untuk:

- page dan feature baru;
- perubahan terarah pada feature existing;
- component, API module, composable, utility, dan test baru;
- perbaikan bug yang menyentuh boundary frontend/backend.

Prinsip utama:

1. Maintainability dan perilaku existing lebih penting daripada modernisasi gaya.
2. Legacy yang masih berjalan tidak dipindahkan atau dimigrasikan hanya demi konsistensi.
3. Perubahan harus scoped; jangan melakukan broad refactor tanpa persetujuan terpisah.
4. UI tidak boleh menampilkan detail exception teknis, stack trace, SQL, credential, atau payload sensitif.
5. Setiap perubahan penting harus diverifikasi melalui test, build, dan browser/smoke check sesuai risikonya.
6. Backend dan frontend harus diperlakukan sebagai kontrak bersama: status HTTP, `error_code`, `request_id`, validation errors, retryability, dan state UI harus konsisten.

## 2. Ownership dan struktur module

Semua pekerjaan baru mengikuti module/feature registry yang dibaca dari database. Agent wajib memverifikasi `N_HRIS_Master_Grup_Fitur` dan `N_HRIS_Grup_Fitur_Map` pada environment terkait; daftar module di dokumentasi hanya contoh/snapshot, bukan authority terbaru.

Struktur ownership:

```text
Module → Submodule → Feature → Page → Components / API / Composables / Utils / Tests
```

Sebelum membuat file baru, agent wajib menentukan dan menyebutkan:

```text
Module:
Submodule:
Feature:
Layer:
```

Jika ownership tidak jelas, agent harus berhenti dan meminta keputusan. Jangan membuat module baru, folder `Misc`, `General`, atau folder berbasis role hanya karena aksesnya Admin/User/SystemAdmin. Role adalah concern authorization; module adalah concern ownership dan placement.

Pemetaan module dan inventory wajib diperbarui bila ditemukan boundary baru atau mapping legacy yang relevan.

## 3. Struktur direktori target

```text
resources/js/
├── Pages/
│   └── <MODUL>/<SUBMODUL>/<FITUR>/
│       ├── <Feature>ListPage.vue
│       ├── <Feature>CreatePage.vue
│       ├── <Feature>EditPage.vue
│       ├── <Feature>DetailPage.vue
│       ├── Components/       # khusus feature
│       ├── api/              # request API khusus feature
│       ├── composables/      # state/behavior khusus feature
│       └── *.test.js         # test khusus feature bila relevan
├── components/
│   ├── ui/
│   ├── forms/
│   ├── tables/
│   ├── page/
│   ├── feedback/
│   ├── navigation/
│   └── <MODUL>/              # reusable khusus satu module
├── Layouts/
├── composables/              # reusable lintas feature/module
├── utils/                    # pure utility dan normalizer
├── services/                 # hanya untuk komunikasi/integrasi khusus
├── stores/                   # state lintas halaman bila memang diperlukan
├── Types/                    # bila repository memerlukannya
└── app.js
```

Catatan: repository memiliki variasi kapitalisasi/folder legacy. File baru harus mengikuti mapping yang berlaku pada feature target dan tidak boleh memindahkan legacy secara otomatis.

## 4. Penamaan

- Page baru tidak menggunakan `index.vue` karena terlalu generik.
- Nama page harus menjelaskan feature dan fungsi:
  - `SopExportListPage.vue`
  - `KaryawanListPage.vue`
  - `KpiApprovalDetailPage.vue`
  - `DatabaseChangeDashboardPage.vue`
- Component menggunakan PascalCase.
- Composable menggunakan `use<Name>.js`.
- API module menggunakan nama feature, misalnya `databaseChangeApi.js`.
- Utility harus memiliki nama yang menjelaskan transformasi atau tanggung jawabnya.
- Jangan menggunakan nama generik seperti `Page.vue`, `View.vue`, `Data.vue`, atau `helper.js` untuk file baru.

## 5. Vue API dan component style

Vue 3 Options API tetap resmi didukung dan boleh diprioritaskan bila konsisten dengan feature/area existing:

```js
export default {
    data(),
    computed: {},
    mounted(),
    methods: {},
    watch: {},
};
```

Composition API juga boleh digunakan untuk:

- feature baru yang memang lebih jelas dengan `script setup`;
- logic reusable melalui composable;
- state/lifecycle kompleks yang sulit dipisahkan dengan Options API.

Tidak ada kewajiban migrasi global ke Composition API. Jangan mengubah satu page legacy dari Options API ke Composition API hanya demi style. Dalam satu feature, pilih pola yang konsisten kecuali ada alasan teknis yang terdokumentasi.

## 6. Tanggung jawab Page, API module, component, dan composable

Pola standar feature baru:

```text
Page
 ├── API module
 ├── Components
 ├── Composables
 ├── validation/normalizer
 └── UI states
```

### Page

Page bertanggung jawab atas:

- orchestration feature;
- membaca props Inertia;
- memanggil API module/composable;
- mengelola state halaman dan transisi state;
- menghubungkan event component dengan action feature;
- navigasi Inertia bila diperlukan.

Page tidak boleh menjadi tempat untuk menumpuk seluruh detail HTTP, formatting, dan business rule backend.

### API module

API module bertanggung jawab atas:

- URL dan HTTP method feature;
- parameter/query/payload mapping;
- komunikasi Axios/fetch;
- response pass-through atau transformasi ringan yang reusable.

Component tidak boleh menyebarkan pemanggilan Axios langsung di banyak tempat. Jika sebuah request dinamis diperlukan oleh page, letakkan di API module feature. API module tidak boleh menyimpan credential server-side atau menanam token rahasia.

### Component

Component bertanggung jawab atas:

- presentasi dan interaksi lokal;
- props, emits, slots, dan state UI lokal;
- accessible name dan keyboard behavior.

Component reusable tidak boleh mengandung business logic module yang tidak relevan dengan component tersebut.

### Composable

Composable digunakan untuk behavior/state yang benar-benar reusable atau terlalu kompleks untuk page. Jangan membuat composable hanya untuk memindahkan kode tanpa boundary yang jelas.

## 7. Layout dan authorization

- `AppLayout`/`AppShell` adalah shell default sesuai implementasi repository.
- Layout tidak dibuat berdasarkan role hanya karena permission berbeda.
- Role mengatur menu, visibility, authorization, dan action capability.
- Layout khusus hanya dibuat jika struktur visual/navigasinya benar-benar berbeda.
- Layout harus defensif terhadap props Inertia yang belum lengkap saat initial render atau navigation.
- Akses page tetap ditentukan backend middleware/policy/authorization; frontend visibility bukan pengganti authorization backend.
- Jika akses ditolak, tampilkan state unauthorized yang aman dan action recovery yang relevan.

## 8. Kontrak data dan error frontend

Kontrak error baru yang diharapkan:

```json
{
  "success": false,
  "message": "Pesan aman untuk pengguna",
  "error_code": "STABLE_ERROR_CODE",
  "request_id": "trace-id",
  "errors": {}
}
```

Aturan:

1. `error_code` adalah identifier stabil; frontend tidak boleh bergantung pada pencocokan string `message`.
2. `message` harus aman dan user-friendly.
3. `request_id` digunakan untuk korelasi support/log dan boleh ditampilkan sebagai referensi singkat.
4. `errors` digunakan untuk validation errors per field bila tersedia.
5. Stack trace, nama class, SQL, path credential, raw exception, dan detail internal tidak ditampilkan ke user.
6. Detail teknis dicatat backend pada log/channel yang sesuai.
7. Legacy response shape dipertahankan kecuali perubahan kontrak memang menjadi scope tersendiri.
8. Normalizer frontend harus menangani response baru dan variasi legacy secara aman tanpa mengarang error detail.

Pemetaan minimal state HTTP:

| Kondisi | State UI | Action |
|---|---|---|
| Network/timeout/5xx sementara | `error` atau `retryableError` | Retry |
| 401/session expired | `sessionExpired` | Login/refresh |
| 403 | `unauthorized` | Kembali atau minta akses |
| 404 | `notFound` | Kembali/refresh |
| 409 | `conflict`/`staleData` | Refresh, bukan blind retry mutation |
| 422 | `validationError` | Tampilkan error field |
| 429/503 | `retryableError` | Retry terbatas |
| 2xx tanpa data | `empty` | Empty state yang jelas |

## 9. UI state wajib

Page baru harus memodelkan state yang relevan, tidak hanya happy path:

```text
loading
success
empty
error
unauthorized
validation error
saving/submitting
stale data/conflict
retrying/refreshing
```

Aturan:

- Loading tidak boleh terlihat sebagai empty.
- Empty tidak boleh terlihat sebagai error.
- Unauthorized tidak boleh disamarkan sebagai empty.
- Mutation state harus mencegah double submit bila request masih berjalan.
- Tombol retry harus memanggil ulang request yang aman dan menunjukkan state retry.
- Retry mutation hanya boleh dilakukan bila operasi backend idempotent atau memiliki idempotency key.
- Conflict biasanya menawarkan refresh/reload data terbaru.
- Data lama boleh dipertahankan saat refresh bila aman, tetapi harus diberi state stale/refreshing yang jelas.
- Error state harus memiliki pesan sopan dan action recovery yang relevan.

## 10. Axios, request lifecycle, dan cancellation

- Request dinamis dipusatkan di API module atau composable yang memiliki boundary jelas.
- Gunakan normalizer error terpusat untuk mengubah error teknis menjadi state aman.
- Request yang dapat overlap harus memiliki guard, sequence, cancellation, atau stale-response protection.
- Request lama tidak boleh menimpa hasil request terbaru.
- Abort/cancellation tidak boleh ditampilkan sebagai error user bila memang disebabkan navigasi atau unmount.
- Loading state harus dibersihkan pada success, error, dan cancellation.
- Cache hanya digunakan bila semantics invalidation dan freshness jelas.
- Jangan melakukan retry tanpa batas di browser.

## 11. Form dan mutation

- Validasi client-side membantu UX, tetapi tidak menggantikan validasi backend.
- Field error ditampilkan dekat field dengan accessible association.
- Submit button dinonaktifkan atau dilindungi dari double submit selama mutation.
- Setelah mutation berhasil, state lokal, cache, list, dan navigation harus disinkronkan.
- Setelah conflict, jangan mengulang mutation secara otomatis tanpa keputusan user atau idempotency contract.
- Pesan sukses tidak boleh menyatakan proses async sudah selesai bila backend baru menerima task `202 Accepted`.
- Untuk task asynchronous, UI menampilkan status proses dan tidak mengklaim hasil final sebelum handler selesai.

## 12. Responsive, accessibility, dan visual system

Setiap component/page baru harus mempertimbangkan desktop dan mobile:

- tidak boleh ada horizontal overflow yang tidak disengaja;
- layout dan table memiliki strategi mobile yang jelas;
- focus state terlihat;
- form memiliki label/description yang dapat diakses;
- icon-only button memiliki accessible name;
- keyboard interaction tersedia untuk control penting;
- status tidak disampaikan dengan warna saja;
- target sentuh memadai;
- modal/drawer memiliki focus management dan close behavior yang dapat diakses;
- loading/error/empty state dapat dipahami tanpa mengandalkan animasi saja.

Design token dipusatkan untuk warna, typography, spacing, border, radius, shadow, breakpoint, transition, dan z-index. Style lokal component memakai `<style scoped>` bila sesuai; style design system/layout berada di `resources/css/`. Jangan menyalin nilai visual acak ke setiap page.

Stack styling yang disepakati:

- Bootstrap terutama untuk grid/utilitas yang sudah dipakai repository;
- vanilla CSS untuk design system, layout, spacing, typography, responsive behavior, dan custom design;
- CSS Grid/Flexbox boleh digunakan bila Bootstrap tidak cukup;
- jangan menambahkan framework styling baru tanpa scope dan persetujuan.

## 13. Reusable components

Placement:

```text
Satu page/feature → Pages/<MODUL>/<SUBMODUL>/<FITUR>/Components/
Satu module       → resources/js/components/<MODUL>/
Lintas module     → resources/js/components/ui        (design system HCIS)
Warisan pra-DS    → resources/js/components/legacy    (dibekukan)
```

`components/ui` adalah milik design system HCIS: komponen `Hcis*`, `index.js`
sebagai barrel, dan `registry.js`. Komponen lintas modul yang sudah ada sebelum
design system tinggal di `components/legacy` dan tidak dikembangkan lagi.
Pekerjaan baru memakai `@/components/ui`; setiap `@/components/legacy` yang
tersisa adalah utang migrasi yang terlihat. Rinciannya di
`docs/design-system.md`.

Sebelum membuat component baru, agent wajib mencari component existing. Component global tidak boleh mengandung business logic spesifik module.

### Cara merujuk component lintas module

Gunakan alias `@`, yang menunjuk `resources/js`:

```js
import Modal from '@/components/ui/modalV2-noir.vue';
import Header from '@/components/ui/header.vue';
```

Jangan memakai jalur relatif berjenjang seperti
`../../../../../components/ui/header.vue`. Jalur semacam itu ikut rusak setiap
kali sebuah page berpindah kedalaman folder, dan itulah yang terjadi pada pass
alignment sebelumnya. Alias membuat rujukan tidak bergantung pada posisi page.

Alias ini didefinisikan di `vite.config.js`. Sebelum 2026-08-18 alias `@`
menunjuk `./src`, direktori yang tidak pernah ada di project ini, sehingga mati
dan dipakai nol kali.

### Component bersama tidak boleh tinggal di dalam Pages/

`resources/js/Pages` adalah namespace halaman Inertia: resolver memetakan nama
halaman langsung menjadi `./Pages/<nama>.vue`. Component yang diletakkan di
sana ikut masuk peta halaman meski bukan halaman.

Pada 2026-08-18 ditemukan design system bersama tinggal di
`Pages/KPI/components/ui` — salah pemilik sekaligus salah lapisan.
`modalV2-noir.vue` dipakai 70 berkas dari 10 module, `header.vue` 52 berkas dari
7 module. Seluruhnya dipindahkan ke `resources/js/components/`.

Component reusable yang benar-benar digunakan lintas feature harus memiliki boundary yang jelas melalui props, emits, slots, variant, dan contoh penggunaan/test bila relevan.

Prioritas component dasar:

```text
BaseButton, BaseInput, BaseSelect, BaseModal, BaseDrawer,
BaseCard, BaseBadge, FormField, FormSection, DataTable,
TableToolbar, TablePagination, PageHeader, PageSection,
EmptyState, LoadingState, ConfirmDialog, ToastNotification,
ApprovalStatus, ApprovalTimeline, FileUploader
```

## 14. Date, number, dan display formatting

- Formatting display harus konsisten dengan utility/helper yang sudah ada.
- Jangan membuat variasi format tanggal/angka baru tanpa kebutuhan feature.
- Waktu yang berasal dari backend harus diperlakukan sesuai timezone contract.
- Jangan memakai string tanggal sebagai dasar business calculation tanpa parsing/contract yang jelas.
- Technical IDs seperti `request_id` boleh ditampilkan sebagai referensi, tetapi tidak boleh diperlakukan sebagai label user.

## 15. Testing dan verification gate

Jenis verification dipilih berdasarkan risiko:

### Unit/component test

Wajib untuk:

- normalizer error;
- API mapping penting;
- state transition kompleks;
- permission/visibility UI yang berisiko;
- mutation guard dan retry behavior;
- component dengan behavior non-trivial.

### Build/static check

Perubahan frontend minimal diverifikasi dengan:

```bash
npm run test:unit -- <focused-test>
NODE_OPTIONS=--max-old-space-size=4096 npm run build
git diff --check
```

Lint/type-check dijalankan bila script dan scope repository mendukungnya.

### Browser/smoke verification

Wajib untuk perubahan yang memengaruhi:

- routing/navigasi;
- layout/AppShell/sidebar;
- authentication/authorization;
- responsive behavior;
- chart/modal/file upload;
- critical dashboard atau mutation flow.

Browser verification minimal memeriksa:

- URL dan page component benar;
- tidak ada uncaught exception;
- tidak ada error HTTP yang relevan;
- loading/success/empty/error state tampil sesuai kondisi;
- action utama dapat digunakan;
- mobile dan desktop tidak memiliki regression yang jelas.

Jika browser automation tidak tersedia, laporkan keterbatasan tersebut dan lakukan smoke check HTTP/source/test yang paling dekat. Jangan mengklaim browser verification berhasil tanpa bukti.

## 16. Legacy dan migrasi

- File existing yang salah tidak otomatis diperbaiki hanya karena dokumen ini lebih ketat.
- Migrasi legacy dilakukan per feature dalam session/scope terpisah.
- Migrasi harus memeriksa import, route, props, response contract, test, build, dan browser bila relevan.
- Tidak ada big-bang rename/move.
- Perubahan pilot boleh memakai aturan baru tanpa memaksa seluruh frontend mengikuti pola baru.
- Bug fix sempit boleh dilakukan pada legacy bila memang diperlukan untuk menyelesaikan bug, tetapi diff harus tetap scoped dan tidak menjadi refactor gaya.

## 17. Checklist agent frontend

Sebelum implementasi:

- [ ] Module, submodule, feature, dan layer sudah jelas.
- [ ] File/component/API/composable existing sudah dicari.
- [ ] Apakah perubahan baru atau migrasi legacy sudah dibedakan?
- [ ] Kontrak endpoint, response, `error_code`, `request_id`, dan retryability sudah diketahui.
- [ ] State loading/success/empty/error/unauthorized/validation/saving/stale sudah dipetakan.

Saat implementasi:

- [ ] Page tidak menyebarkan Axios call ke component.
- [ ] API module memiliki boundary feature yang jelas.
- [ ] Technical error tidak ditampilkan ke user.
- [ ] Double submit dan stale response dicegah.
- [ ] Responsive/accessibility diperhatikan.
- [ ] Legacy behavior tidak berubah di luar scope.

Sebelum menyatakan selesai:

- [ ] Focused test lulus.
- [ ] Build berhasil atau kegagalannya dijelaskan dengan bukti.
- [ ] `git diff --check` lulus.
- [ ] Browser/smoke verification dilakukan sesuai risiko.
- [ ] Working tree, commit, dan push status dilaporkan secara terpisah.

## 18. HCIS Enterprise Suite Signature UI Design System Standard

Setiap komponen visual (Container, Wadah, Card, Header, Modal, Panel, Result State) wajib mengikuti spesifikasi **HCIS Enterprise Suite Signature Design System**:

### 1. Sentiment-Adaptive Luminous Glass Surfaces
- Permukaan standar (`variant="glass"`) menggunakan kombinasi pencahayaan frosted glass 225° diagonal dengan *dual-corner radial glow auras* (kanan atas dan kiri bawah) yang beradaptasi otomatis mengikuti tingkat urgensi atau konteks modul (`sentiment`):
  - **`brand`** (Indigo / Sapphire `#6366f1`): Modul inti HCIS, ringkasan umum, kokpit eksekutif.
  - **`purple` / `violet`** (Amethyst `#7c3aed`): Modul Kuis SOP, ujian kompetensi, sertifikasi, asesmen.
  - **`teal` / `cyan`** (Mint Toska `#0d9488`): Pustaka dokumen, kebijakan legalitas, acuan kerja, link eksternal.
  - **`success`** (Emerald `#10b981`): Status selesai, transaksi terverifikasi, kepatuhan hijau.
  - **`warning`** (Amber `#f59e0b`): Gate absensi, antrean persetujuan, mendekati batas tenggat waktu.
  - **`danger`** (Crimson `#ef4444`): Status kritis, anomali data, keterlambatan, penolakan transaksi.
  - **`info`** (Sky Blue `#0ea5e9`): Pengumuman baru, agenda pelatihan mandiri.
  - **`neutral` / `none`**: Kaca kristal netral bersih tanpa pendaran warna aksen.
- Dilarang menggunakan garis aksen kaku di bagian atas (*top border strip*) yang memotong keindahan permukaan kaca.

### 2. Obsidian Slate Typography
- **Judul (*Title*)**: Menggunakan Obsidian Slate `var(--hcis-slate-800)` (`#1e293b`), `font-weight: 750`, `letter-spacing: -0.02em` tanpa *text-shadow* pudar.
- **Deskripsi (*Subtitle/Description*)**: Menggunakan Slate `var(--hcis-slate-500)` (`#64748b`), `font-size: 0.8125rem`, `line-height: 1.45`.

### 3. Jewel Icon Box Architecture
- Wadah ikon menggunakan kotak permata bersudut halus (`border-radius: var(--hcis-radius-lg)`).
- Latar gradasi 135deg lembut (`linear-gradient(135deg, tint-50, tint-100)`), border 1px transparan (`rgb(tint-500-rgb / 0.22 - 0.25)`), dan kilau atas `box-shadow: inset 0 1px 0 var(--hcis-white)`.

### 4. Dual-Layer Crystal Etched Dividers
- Garis pembatas header, tab bar dock, konsol, dan footer menggunakan teknik *dual-layer crystal etched*:
  - Garis dasar: `border-top / border-bottom: 1px solid rgb(tint-500-rgb / 0.14 - 0.22)`.
  - Pantulan cahaya: `box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8)`.

### 5. Interactive Micro-Lift Elevation
- Komponen interaktif (`interactive`) menggunakan elevasi GPU `transform: translateY(-3px)`, pendaran *shadow bloom*, dan pembesaran halus pada kotak ikon permata (`transform: scale(1.06)`).

## 19. Multi-Device Responsive Architecture & Mobile UI/UX Standard (Desktop, Tablet, Mobile)

Setiap pengembangan halaman atau fitur baru di Frontend HCIS wajib memperlakukan **Desktop ($\ge 1024\text{px}$)**, **Tablet ($768\text{px} - 1023\text{px}$)**, dan **Mobile ($< 768\text{px}$)** sebagai warga kelas satu (*first-class citizens*). Dilarang merilis halaman yang hanya diuji pada resolusi desktop tanpa memastikan pengalaman pengguna mobile bebas dari *horizontal scroll*, elemen terpotong, atau tombol yang jatuh berantakan.

### 1. Viewport Containment & Strict Zero Horizontal Overflow (`min-width: 0` Rule)
- Setiap item Flexbox (`display: flex`) dan CSS Grid (`display: grid`) di dalam layout halaman, kartu, form, dan kontainer wajib memiliki deklarasi proteksi:
  ```css
  min-width: 0;
  max-width: 100%;
  box-sizing: border-box;
  ```
- Seluruh input form (`HcisField`, `HcisPhoneInput`, `HcisIdentityInput`, `HcisPlateInput`, `HcisDatePicker`, `HcisSelect`, `HcisInputNumber`, `HcisTextarea`) wajib beradaptasi penuh (`width: 100% !important; max-width: 100% !important; min-width: 0 !important;`) pada kontainer kartu di layar ponsel sempit ($320\text{px}-420\text{px}$).
- Elemen grid yang memuat input teks/dropdown tidak boleh dipaksa berjejer 2 kolom jika ruang tidak mencukupi; gunakan sub-breakpoint `@media (max-width: 420px)` untuk otomatis beralih menjadi 1 kolom penuh.

### 2. Single-Row Container Headers (Strict No-Wrap Policy)
- Header kontainer (`HcisContainer`, `HcisDataTable`) harus selalu terkunci dalam **satu baris horizontal presisi** tanpa membiarkan tombol aksi jatuh ke baris kedua:
  ```css
  .hcis-container__header {
      display: flex !important;
      flex-direction: row !important;
      flex-wrap: nowrap !important;
      align-items: center !important;
      justify-content: space-between !important;
  }
  ```
- **Judul**: Buat ringkas dan selalu beri proteksi *ellipsis* (`overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0;`).
- **Deskripsi/Subtitle**: Subtitle panjang pada header kontainer **wajib disembunyikan** di mobile (`@media (max-width: 640px) { .hcis-container__subtitle { display: none !important; } }`) untuk menghilangkan ruang kosong (*dead vertical space*).
- **Tombol Aksi Header (Refresh / Muat Ulang)**:
  - Wajib memiliki `flex-shrink: 0;`.
  - Pada layar mobile ($\le 640\text{px}$), label teks disembunyikan (`.hcis-btn__text { display: none !important; }`), berubah menjadi tombol ikon persegi kompak ($1.85\text{rem} - 2\text{rem}$) yang hemat ruang.

### 3. Executive Stat Cards / Metric Pods Interaktif
- Kartu statistik eksekutif di bagian atas halaman harus interaktif (`hoverable`, `interactive`), memiliki *signature pattern* (`grid`, `dots`, `crosshair`), subtext dinamis yang informatif, dan mendukung navigasi 1-sentuhan menuju tab terkait (`targetTab` / `@click`).
- Pembagian kolom responsif:
  - Desktop ($\ge 1024\text{px}$): 3–4 kolom.
  - Tablet ($768\text{px} - 1023\text{px}$): 2–3 kolom.
  - Mobile ($< 768\text{px}$): 1–2 kolom dengan elevasi mikro (`translateY(-2px)`).

### 4. Pola Formulir: Desktop Sticky Sidebar vs. Mobile Floating Glass Dock
- **Desktop Mode ($\ge 992\text{px}$)**:
  - Panel ringkasan / kesiapan (*readiness summary panel*) diletakkan di sebelah kanan form dengan perilaku *sticky* yang setia mengikuti scroll:
    ```css
    position: sticky;
    top: 5.5rem;
    align-self: start;
    z-index: 15;
    max-height: calc(100vh - 6rem);
    overflow-y: auto;
    ```
- **Mobile Mode ($< 640\text{px}$)**:
  - Panel ringkasan desktop disembunyikan (`display: none !important;`).
  - Digantikan oleh **Floating Glass Bottom Dock** melayang di bawah layar (`<Teleport to="body">`):
    - Background kaca kristal (*Backdrop Filter Blur* $20\text{px}$).
    - Kapsul badge progres kelengkapan (mengetuk kapsul membuka *Bottom Sheet Drawer* rincian checklist).
    - Tombol simpan/submit utama yang mudah dijangkau satu tangan.
  - Kontainer formulir **wajib memiliki padding bawah aman** (`padding-bottom: 5.5rem`) agar input paling bawah (misal Textarea Keterangan) tidak tertutup oleh floating dock.
- **Input Spacing & Numbers**:
  - Kolom angka (`HcisInputNumber`) menggunakan perataan tengah (`align="center"`).
  - Kolom plat nomor kendaraan memanjang penuh di bawah pilihan moda kedatangan.

### 5. Tabel Data vs. Card Grid View Responsif (`HcisDataTable`)
- **Perilaku Inisialisasi**:
  - Desktop ($\ge 768\text{px}$) secara default membuka mode Tabel (`viewMode: 'table'`).
  - Mobile ($< 768\text{px}$) **wajib otomatis mendeteksi dan membuka mode Kartu Grid** (`viewMode: typeof window !== 'undefined' && window.innerWidth < 768 ? 'grid' : 'table'`) dengan pemanggilan `detectInitialViewMode()` saat `mounted()`.
- **Integrasi Slot `#grid`**:
  - Tampilan kartu Grid **harus berada di dalam `<template #grid>` bawaan `HcisDataTable`**, bukan ditaruh di luar tabel. Ini memastikan filter pencarian, *quick chips*, tombol ganti tampilan, dan paginasi tetap tersinkronisasi 100% di kedua mode.
- **Struktur Kartu Grid Mobile**:
  - Header: Avatar/Ikon + Nama/Judul + Kategori Pill + Status Badge.
  - Body: Grid spesifikasi $2\times2$ yang rapi dengan latar lembut (`background: rgba(248, 250, 252, 0.7)`).
  - Status Aktif (*Live Presence Strip*): Dilengkapi titik denyut (*pulse dot*) dan durasi waktu aktual saat transaksi aktif.
  - Footer: Tombol aksi berlebar penuh yang mudah ditekan jempol.
  - Skeleton Loading: Menampilkan 6 kartu skeleton shimmer saat status `loading` aktif.

### 6. Modal Konfirmasi & Dialog Full-Screen di Mobile
- Modal konfirmasi/review pengajuan di mobile ($< 640\text{px}$) wajib memenuhi layar ($100\text{dvh} \times 100\text{vw}$), teks deskripsi panjang disembunyikan agar panggung visual fokus pada struk data (*receipt*), dan tombol aksi footer berjejer rapi $50\% / 50\%$.
- Label tombol sekunder harus ringkas (gunakan `"Reset"` daripada `"Reset Form"`).
- Pada struk konfirmasi berkas/foto, **dilarang menampilkan nama file mentah/hash panjang** (misal `f3865aeae8fa6e11975af...jpg`); gantikan dengan label status bersih dan ramah pengguna seperti `'Sudah Terlampir'`.

### 7. Dual-Pane Dialogs (`HcisDualPaneDialog`) Mobile Segmented Switcher
- Pada Desktop ($\ge 992\text{px}$): Menampilkan tata letak 2 kolom berdampingan (*Left & Right Pane*).
- Pada Mobile ($< 992\text{px}$): Otomatis beralih menjadi modal *full-screen* dengan **Capsule Segmented Tab Switcher** di bagian atas (contoh: **[ 👤 Identitas & Status ]** dan **[ 📑 Rincian & Log ]**) untuk navigasi 1 sentuhan tanpa menumpuk konten vertikal secara berlebihan.

### 8. Standardisasi Istilah Identitas
- Dilarang menggunakan label "NIK" untuk entitas PIC atau karyawan pada modul publik/kunjungan/izin umum; gunakan istilah standar yang netral yaitu **"Kode"** (contoh: `Kode: EMP-1029`).

### 9. Distinct Emerald Green Active Status Identification (Mode Kartu & Mode Tabel)
- Seluruh pengajuan/entitas yang berada dalam kondisi **Aktif, Sedang Berada di Area, atau Disetujui** (`APPROVED`, `ACTIVE`, `AKTIF`, `BERLAKU`, `DISETUJUI`, `CHECKED_IN`, `TERJADWAL`, `SCHEDULED`, atau `Flag_Approval = 'Y'`) wajib memiliki penanda visual **Hijau Emerald (*Success Glass*)** yang kontras dan mudah dikenali:
  - **Mode Kartu (Grid View)**:
    - Background: Gradasi kristal hijau emerald (`radial-gradient` dengan `linear-gradient(225deg, rgba(236, 253, 245, 0.96) 0%, rgba(255, 255, 255, 0.98) 45%, rgba(209, 250, 229, 0.75) 100%)`).
    - Border & Aksen Kiri: Border beraksen hijau lembut (`rgba(16, 185, 129, 0.45)`) ditambah **Garis Aksen Kiri Tebal 4.5px Solid Emerald** (`border-left: 4.5px solid var(--hcis-success-500, #10b981)`).
    - Shadow Bloom: Pendaran bayangan hijau halus (`box-shadow: 0 4px 14px -2px rgba(16, 185, 129, 0.14)`).
  - **Mode Tabel (Table View)**:
    - Kolom status wajib menampilkan `HcisBadge` varian `success` (hijau kristal) dengan teks status aktif (misal "Aktif" atau "Sedang di Area") dan ikon/titik denyut aktif (`pulse`).
  - **Live Presence Pod**:
    - Kontainer status kehadiran real-time menggunakan latar hijau mint lembut (`background: rgba(236, 253, 245, 0.75); border: 1px solid rgba(167, 243, 208, 0.75)`) dengan titik denyut hijau (`background-color: var(--hcis-success-500, #10b981)`).

### 10. Desktop Table Column Width & Container Clamping Standard
- Pembungkus tabel data (`.hcis-table-container`, `.hcis-data-table__wrapper`, dan workspace pembungkus) **wajib dikunci penuh** dengan:
  ```css
  width: 100%;
  max-width: 100%;
  min-width: 0;
  box-sizing: border-box;
  ```
- **Larangan Persentase Campuran (> 100%)**:
  - Dilarang mendefinisikan kolom tabel menggunakan kombinasi persentase yang totalnya mendekati/mencapai $100\%$ ditambah kolom aksi dengan lebar piksel tetap (contoh anti-pola: $26\% + 22\% + 20\% + 16\% + 16\% + 110\text{px} > 100\%$) karena akan memaksa tabel desktop meluber keluar dari kartu kontainer induk.
  - Gunakan `minWidth` untuk kolom teks fleksibel (misal `minWidth: '220px'`) dan `width` piksel presisi untuk kolom angka, status, dan aksi (misal `width: '140px'`).

