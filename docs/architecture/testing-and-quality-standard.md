# KPI-Unreal Testing and Quality Standard

Status: **APPROVED_BASELINE**

Dokumen ini menetapkan cara agent menyiapkan, menjalankan, dan melaporkan test untuk feature baru, bug fix, refactor scoped, dan perubahan lintas frontend/backend. Tujuannya adalah mengurangi testing manual berulang tanpa mengklaim bahwa automated test dapat membuktikan tidak ada bug sama sekali.

## 1. Prinsip utama

1. Automated test memperkecil risiko dan regression; bukan bukti matematis bahwa aplikasi bebas bug.
2. Test harus memeriksa behavior nyata dan boundary, bukan hanya apakah method terpanggil atau component dapat di-mount.
3. Setiap feature baru wajib disertai test plan sebelum implementation dianggap selesai.
4. Bug yang ditemukan manual harus diubah menjadi regression test bila dapat direproduksi.
5. “Test lulus” harus selalu menyebutkan suite, flow, environment, dan cakupan yang benar-benar dijalankan.
6. Agent dilarang menyatakan “tidak ada bug” secara absolut. Klaim yang benar:

```text
Tidak ditemukan failure pada suite dan flow yang dijalankan.
Area di luar cakupan tersebut belum dianggap terverifikasi.
```

7. Manual validation user tetap merupakan acceptance gate sampai smoke/regression otomatis terbukti stabil melalui beberapa siklus.

## 2. Scope change control dan development checkpoint

Development tidak boleh melebar diam-diam. Saat ada permintaan baru di tengah feature aktif, agent wajib membandingkannya dengan scope, acceptance criteria, file, boundary, dan impact matrix yang sedang berlaku.

### 2.1 Klasifikasi perubahan

| Kategori | Contoh | Tindakan |
|---|---|---|
| In-scope | Bug pada feature aktif atau validasi yang memang bagian requirement | Tetap di branch dan update test plan bila perlu |
| Related change | Perubahan kecil pada shared API, permission, atau database yang dibutuhkan feature | Jelaskan dampak dan minta keputusan sebelum memasukkan |
| Out-of-scope | Feature berbeda, refactor global, perubahan unrelated dashboard/auth/layout | Sarankan ditunda atau checkpoint lalu branch baru |

Agent tidak boleh menambahkan feature, refactor shared component, perubahan authorization global, atau perubahan database unrelated hanya karena “sekalian”.

### 2.2 Scope gate

Untuk perubahan related atau out-of-scope, agent harus menyampaikan:

```text
Permintaan baru:
Hubungan dengan feature aktif:
File dan boundary yang terdampak:
Risiko terhadap flow aktif:
Test tambahan yang dibutuhkan:
Estimasi dampak terhadap waktu dan cakupan:
Rekomendasi: lanjut sekarang / tunda / checkpoint dan branch baru
Keputusan yang dibutuhkan dari developer:
```

Default yang aman adalah menyelesaikan feature aktif terlebih dahulu. Agent boleh tetap mengerjakan perubahan baru pada branch yang sama hanya setelah developer memilih memasukkannya ke scope dan acceptance criteria diperbarui.

### 2.3 Checkpoint sebelum berpindah pekerjaan

Jika developer memilih pekerjaan baru sebelum feature aktif selesai, agent harus membuat checkpoint status tanpa menghapus atau menyembunyikan perubahan:

```markdown
# Development Checkpoint

## Active feature
Nama dan tujuan:

## Current phase
Discovery / implementation / bug fixing / feature complete / testing preparation / testing execution

## Original scope
- ...

## New request
- ...

## Scope classification
In-scope / Related / Out-of-scope

## Impact
- Files:
- Backend:
- Frontend:
- Database:
- Authorization:
- Testing:

## Current status
- Completed:
- In progress:
- Pending:
- Blocked:

## Test evidence
- Already run:
- Not yet run:
- Known failures/blockers:

## Decision
- Continue current feature
- Include new change
- Defer new change
- Create new branch

## Resume instructions
- ...
```

Perubahan berbeda yang berpotensi mengubah flow, shared boundary, database, authorization, transaction, atau test baseline harus dipisahkan ke branch dan fase baru setelah checkpoint. Jangan menggunakan operasi destruktif seperti `reset --hard`, `clean -fd`, `restore`, atau `stash` untuk memindahkan pekerjaan tanpa persetujuan eksplisit.

### 2.4 Finalisasi sebelum testing lengkap

Agent tidak perlu membuat seluruh test component atau menjalankan full regression pada setiap perubahan kecil. Selama development, agent cukup mempertahankan test plan ringan dan melakukan focused check yang relevan.

Saat agent menilai implementasi feature selesai, agent harus berhenti pada feature-complete checkpoint dan menanyakan:

```text
Apakah masih ada behavior, validasi, permission, UI, atau edge case yang ingin diperbaiki/ditambahkan?
```

Testing package lengkap dibuat setelah developer menyatakan behavior final. Setelah testing package selesai dibuat, agent harus menjelaskan cakupannya dan meminta pilihan eksekusi:

```text
feature langsung saja
feature + fitur terdampak
full applicable suite/repository
```

Agent kemudian menjalankan hanya pilihan yang disetujui developer, kecuali focused check yang aman dan cepat selama development. Bila perubahan high-risk, agent tetap harus memberi peringatan bahwa full applicable suite atau manual acceptance mungkin diperlukan sebelum release.

## 3. Test pyramid

KPI-Unreal menggunakan lapisan berikut:

```text
Exploratory/UAT manual
        ▲
Browser E2E dan smoke
        ▲
Contract dan integration
        ▲
Feature/API
        ▲
Unit/component
```

Proporsi yang diharapkan:

- banyak unit/component test yang cepat;
- cukup feature/API dan contract test;
- E2E lebih sedikit tetapi mencakup journey kritis;
- exploratory manual fokus pada risiko, UX, dan scenario yang sulit diotomatisasi.

Tidak semua perubahan harus menjalankan seluruh pyramid, tetapi setiap perubahan harus menjelaskan lapisan mana yang dijalankan dan mengapa lapisan lain tidak relevan.

## 4. Requirement wajib sebelum implementasi

Sebelum menulis production code, agent wajib membuat test plan minimal:

```text
Feature/bug:
Module/submodule/feature/layer:
User role/persona:
Entry route/API:
Success behavior:
Empty behavior:
Validation behavior:
Unauthorized behavior:
Failure/retry behavior:
Mutation/idempotency behavior:
Data/fixture requirement:
Impacted existing flows:
Required test layers:
Manual acceptance scenarios:
Known exclusions:
```

Untuk bug fix, harus ada reproduction yang dapat gagal sebelum fix bila memungkinkan:

```text
RED reproduction → minimal fix → GREEN regression → impacted suite
```

Jika test belum dapat dibuat karena environment blocker, agent harus menyebutkan blocker dan tidak mengklaim regression sudah terlindungi.

## 5. Test impact matrix

Agent wajib menguji boundary yang terdampak, bukan hanya file yang berubah.

| Perubahan | Test minimum |
|---|---|
| Pure helper/formatter | Unit edge cases |
| Vue component | Component render dan state |
| API module/normalizer | Response mapping dan error mapping |
| Page/Inertia props | Page/component test dan HTTP/browser smoke |
| Controller/route | Feature/API status, middleware, response |
| Authorization | Allowed dan denied path |
| Service/query | Unit plus real integration bila query/DB penting |
| Database/schema | Schema inspection dan integration SQL target |
| Transaction | Commit, rollback, duplicate, retry/after-commit |
| Job/Outbox | Idempotency, claim, retryable, permanent failure |
| Provider/upload | Fake contract plus sandbox/integration bila tersedia |
| AppShell/sidebar/layout | Navigation, console error, desktop/mobile smoke |
| Chart/modal/async UI | Component state plus browser render |
| Shared helper/ResponseHelper | Semua critical consumers dan contract test |
| Authentication/session | Login, expiry/401, unauthorized, logout |

Impact matrix harus mencantumkan fitur existing yang paling mungkin terkena perubahan.

## 6. Lapisan test dan tanggung jawabnya

### 5.1 Unit test

Untuk pure function, classifier, normalizer, policy resolver, dan business rule terisolasi.

Wajib mencakup:

- happy path;
- boundary/minimum/maksimum;
- invalid input;
- null/empty/missing field;
- error classification;
- determinism.

Unit test tidak boleh dianggap cukup untuk membuktikan route, middleware, database, atau browser behavior.

### 5.2 Component test

Component test harus menguji behavior dan state, bukan hanya `exists()`.

State minimum yang relevan:

```text
loading
success
empty
error
unauthorized
validation error
saving/submitting
retrying
stale/conflict
```

Wajib menguji event penting, disabled state, duplicate submit guard, dan safe error message.

### 5.3 Feature/API test

Untuk route/controller/middleware/Form Request/service boundary.

Minimal bila endpoint relevan:

```text
200/201/202 success
empty response
401
403
404
409
422
429
500/503
```

Test harus memastikan response shape, status HTTP, authorization, validasi, dan `error_code`/`request_id` bila contract tersebut berlaku.

### 5.4 Integration test

Wajib untuk behavior yang bergantung pada:

- SQL/query nyata;
- transaction dan locking;
- database constraint/index;
- storage;
- queue/Outbox;
- provider adapter;
- external boundary yang memiliki sandbox/fake contract.

Mock tidak boleh menggantikan seluruh integration test pada boundary yang memang menjadi sumber risiko.

### 5.5 Contract test

Backend dan frontend harus memiliki contract test atau verified fixture untuk endpoint penting.

Fixture/contract wajib memiliki:

```text
endpoint
method
request example
response example
HTTP status
environment
verification date
```

Fixture tidak boleh dibuat berdasarkan tebakan. Legacy response shape diberi label legacy dan tidak diubah diam-diam.

### 5.6 Browser E2E/smoke

Untuk flow user nyata: login, navigation, page render, request API, mutation, authorization, dan recovery.

Browser test wajib memeriksa:

- URL dan component benar;
- tidak ada `pageerror`/uncaught exception;
- tidak ada `console.error` aplikasi yang relevan;
- tidak ada critical HTTP 5xx;
- loading/success/empty/error state;
- action utama;
- desktop/mobile bila layout terdampak.

Implementasi baseline memakai fixture bersama `tests/e2e/support/uat.js` dan classifier `tests/e2e/support/browserQualityGate.js`. Gate mengumpulkan `pageerror`, `console.error`, response 5xx pada document/script/style/xhr/fetch, dan request failure kritis. Failure dilaporkan sebagai attachment `browser-quality-gate.txt`. Noise `ERR_BLOCKED_BY_CLIENT`, Cloudflare Insights, uBlock, ad blocker, serta request abort/cancel yang dikenal tidak dianggap failure aplikasi.

Classifier memiliki test terpisah dan dapat diverifikasi dengan:

```bash
npm run test:e2e:gate
```

Error extension seperti ad blocker harus dibedakan dari error aplikasi dan sebaiknya browser test menggunakan context bersih tanpa extension.

### 5.7 Exploratory/manual UAT

Manual test tetap diperlukan untuk:

- UX dan copy;
- keyboard/screen reader yang belum terotomasi;
- scenario bisnis baru;
- visual/layout kompleks;
- browser/device nyata;
- data dan workflow yang belum dapat dibuat deterministic.

Manual test harus memakai checklist dan dicatat, bukan dilakukan acak tanpa bukti.

## 6. Smoke vs regression suite

### Smoke suite

Cepat dan dijalankan pada setiap feature penting atau perubahan shared boundary:

```text
login/session
homepage render
AppShell/sidebar navigation
critical API read
critical mutation bila relevan
unauthorized path
error/retry path
logout/session expiry
```

Target smoke harus singkat dan stabil.

### Regression suite

Dijalankan sebelum commit release/merge sesuai risk level:

```text
critical business journey
authorization matrix
transaction/upload
Outbox/provider
affected module flows
browser desktop/mobile
```

Full regression tidak harus dijalankan untuk setiap perubahan kecil, tetapi perubahan shared service, response contract, AppShell, authorization, transaction, atau database memerlukan cakupan lebih luas.

## 7. Risk level dan minimum gate

### Low risk

Contoh: dokumentasi, pure CSS lokal, helper terisolasi.

```text
focused test bila ada
build/static check bila relevan
git diff --check
```

### Medium risk

Contoh: page, API module, service, component reusable.

```text
focused unit/component/API test
impacted suite
build
git diff --check
```

### High risk

Contoh: auth, authorization, AppShell, shared response, transaction, database, upload, Outbox, provider, deployment.

```text
test-first/reproduction
unit/component
feature/API
integration/contract
browser smoke
impact regression
build/static checks
manual acceptance bila relevan
```

### Release/production readiness

```text
full applicable automated suite
browser smoke dan critical regression
security/dependency check
manual acceptance sign-off
known limitations terdokumentasi
rollback/recovery plan
```

## 8. Test data, account, dan environment

Test wajib menggunakan data deterministic dan environment yang aman.

Target ideal:

```text
unit             → isolated/no DB
feature          → isolated test DB atau fixture terkontrol
integration      → SQL Server test/staging terisolasi bila SQL Server-specific
browser E2E      → dedicated UAT/test database
manual UAT       → environment yang disepakati, bukan production
```

Playwright tidak boleh menulis ke database development bersama yang sedang digunakan manusia tanpa persetujuan dan guardrail khusus.

Akun test minimal bila feature memiliki authorization:

```text
authorized user
system/admin user bila relevan
unauthorized user
expired/session user bila relevan
```

Test data tidak boleh memakai credential production, data sensitif nyata, atau identifier yang dapat mengubah data user nyata.

## 9. Error-path dan resilience testing

Setiap feature API/async minimal mempertimbangkan:

```text
network timeout
401 session expired
403 denied
404 missing
409 conflict/stale
422 validation
429 rate limit
500 server error
503 temporary dependency failure
cancelled request
retry dan duplicate request
```

Untuk async/transaction:

- retry tidak boleh menggandakan side effect;
- handler harus idempotent;
- failure sementara dan permanent dibedakan;
- rollback dan after-commit diuji;
- UI tidak menyatakan hasil final sebelum task selesai;
- request ID/error code dipertahankan bila contract mendukungnya.

## 10. Browser, accessibility, dan visual quality

Playwright menjadi tool browser standar yang sudah tersedia di project. Jangan menambah Cypress/Dusk hanya karena Playwright belum dimanfaatkan.

Tahap addon yang direkomendasikan:

1. gunakan `@playwright/test` yang sudah terpasang;
2. aktifkan smoke/regression command dan console/network gate;
3. tambahkan `@axe-core/playwright` untuk accessibility critical/serious;
4. tambahkan visual snapshot hanya pada page deterministik;
5. gunakan trace/screenshot pada failure.

Accessibility otomatis tidak menggantikan keyboard/manual review. Baseline gate menggunakan `@axe-core/playwright` pada smoke page yang sudah memiliki akun UAT dan menganggap violation `critical`/`serious` sebagai blocking; violation `moderate`/`minor` tetap dilaporkan untuk backlog dan review. Command baseline:

```bash
npm run test:e2e:a11y
```

Test accessibility wajib menyimpan route/page dan hasil violation yang ditemukan. Jangan menambahkan `disableRules` atau mengabaikan violation tanpa alasan tertulis dan persetujuan scope.

Visual regression harus memakai fixture deterministik, waktu/font stabil, dan baseline yang direview manusia.

## 11. CI quality gates

CI ideal memiliki job terpisah:

```text
static/format
backend unit
backend feature/integration dengan test DB
frontend unit/component
frontend build
browser smoke
accessibility/security
full regression scheduled/manual
```

Penerapan bertahap:

1. baseline report-only untuk failure legacy/environment;
2. blocking focused unit, build, dan smoke yang stabil;
3. blocking feature/integration setelah test DB siap;
4. blocking browser smoke setelah environment deterministic;
5. regression penuh scheduled/manual atau wajib untuk high-risk change.

`continue-on-error` hanya boleh digunakan bila failure diklasifikasikan, dilaporkan, dan tidak disamarkan sebagai pass.

Implementasi workflow saat ini berada di `.github/workflows/quality.yml` dan mencakup:

- backend Unit;
- frontend Vitest dan build;
- browser quality classifier;
- Playwright smoke/accessibility test discovery.

Job CI browser belum menjalankan flow UAT terhadap aplikasi karena runner tidak
memiliki `.env.uat` dan credential database terisolasi. Eksekusi aktual
`npm run test:e2e:a11y` tetap dilakukan pada UAT clone `Web_HRIS_Cut_Off`.
Job browser baru boleh dibuat blocking untuk flow aplikasi setelah environment
UAT CI memiliki database terisolasi, credential server-side, cleanup journal,
dan lifecycle server yang deterministic.

## 12. Test execution order untuk agent

Urutan standar:

```text
1. Inspect baseline dan working tree
2. Tulis test plan/impact matrix
3. Buat RED reproduction/test bila memungkinkan
4. Implement minimal fix/feature
5. Jalankan focused tests
6. Jalankan impacted backend/frontend tests
7. Jalankan contract/integration tests
8. Jalankan browser smoke
9. Jalankan regression sesuai risk level
10. Build/static/security checks
11. Review diff dan test evidence
12. Manual acceptance checklist bila diminta/relevan
```

Agent boleh berhenti setelah focused gate hanya jika task memang low-risk dan explicitly scoped demikian. Untuk high-risk, focused test saja tidak cukup.

## 13. Bukti dan laporan test

Laporan wajib memisahkan:

```text
Test command:
Environment:
Suite/flow:
Result:
Evidence:
Coverage boundary:
Not covered:
Blocker:
```

Format status:

```text
## Automated verification
- Passed:
- Failed:
- Not run:

## Browser verification
- Passed flows:
- Console/network result:
- Screenshots/trace:

## Manual acceptance
- Scenario:
- Result:
- Tester/date:

## Remaining risk
- ...
```

Jangan mengubah kalimat “unit test lulus” menjadi “fitur bebas bug”.

## 14. Bug-to-test rule

Setiap bug yang dapat direproduksi harus menghasilkan:

1. reproduction steps;
2. failing test atau browser spec;
3. minimal fix;
4. passing regression test;
5. impacted suite result;
6. catatan apakah bug berasal dari FE, BE, contract, environment, atau deployment.

Jika bug tidak dapat diotomatisasi, simpan manual regression checklist dengan data, role, route, dan expected behavior yang jelas.

## 15. Definition of Done testing

Feature/fix belum dianggap quality-complete sebelum:

```text
[ ] test plan dan impact matrix ada
[ ] ownership/module terverifikasi
[ ] happy path diuji
[ ] empty/validation/unauthorized/error path diuji sesuai relevansi
[ ] duplicate/retry/idempotency diuji bila mutation/async
[ ] focused test lulus
[ ] impacted suite lulus atau failure diklasifikasi
[ ] contract/integration diuji bila boundary relevan
[ ] browser smoke dijalankan untuk UI/route penting
[ ] console/network error diperiksa
[ ] build/static check lulus bila relevan
[ ] manual acceptance dicatat bila diperlukan
[ ] semua blocker dan area tidak tercakup dilaporkan
[ ] tidak ada klaim absolute bebas bug
```

## 16. Baseline commands

Command standar yang tersedia setelah fase baseline ini:

```text
npm run test:frontend              # seluruh Vitest frontend
npm run test:backend:unit          # PHPUnit Unit
npm run test:backend:feature       # PHPUnit Feature
npm run test:backend:integration   # PHPUnit Integration
npm run test:backend               # seluruh PHPUnit yang applicable
npm run test:e2e:smoke             # smoke auth/session/protected-route baseline
npm run test:e2e:regression        # seluruh Playwright E2E yang tersedia
npm run test:all                   # backend + frontend + build + E2E regression
```

`test:e2e:smoke` saat ini memakai `tests/e2e/000-penopang.spec.js`, yang mencakup session, protected route, dan authorization dasar. Test E2E lain menjadi regression suite sampai smoke flow yang lebih luas dibuat dan diberi label.

`test:all` tidak boleh dijalankan terhadap database development bersama tanpa memastikan environment dan data isolation terlebih dahulu. Command tersedia sebagai orchestration contract; readiness dan keamanan target tetap harus diverifikasi sebelum eksekusi.

## 17. Gerbang verifikasi struktural

Lima skrip di `scripts/` memeriksa keutuhan struktur, bukan kebenaran perilaku.
Wajib dijalankan pada setiap perubahan yang memindahkan, mengganti nama, atau
menghapus berkas.

```text
php scripts/verify-references.php --max=0     simbol App\ dari `use` dan `::class` yang tidak resolve
php scripts/verify-frontend-imports.php       jalur impor relatif dan beralias yang tidak menunjuk berkas
php scripts/verify-inertia-pages.php          nama halaman Inertia yang tidak punya komponen
php scripts/route-snapshot.php FILE           method, URI, nama, middleware, action seluruh route
php scripts/console-snapshot.php FILE         perintah artisan beserta dependency yang dapat di-resolve
```

Cara pakai kedua snapshot: ambil baseline sebelum perubahan, ambil lagi
sesudahnya, lalu bandingkan. Setiap perbedaan harus disengaja dan dijelaskan.

Masing-masing menangkap kelas kesalahan yang tidak terlihat oleh yang lain:

- `Foo::class` dievaluasi menjadi string pada waktu compile dan tidak memicu
  autoloader. Route tetap terdaftar tanpa error dan baru gagal saat dispatch.
  Inilah yang mematikan seluruh endpoint SOP tanpa terdeteksi.
- Nama halaman Inertia hanyalah string. Tidak ada autoloader, linter, maupun
  compiler yang memeriksanya; gejalanya layar putih di browser.
- Acuan tak-terkualifikasi ke bekas tetangga sesama namespace tidak terlihat
  oleh pemindai `use`/`::class`, karena tidak ada `use` maupun `::class` yang
  berubah. `console-snapshot.php` menangkapnya karena mem-boot container.
- `route-snapshot.php` menggantikan `php artisan route:list`, yang tidak dapat
  dijalankan di environment ini karena `RouteListCommand` me-resolve controller
  dan memicu kegagalan Google ADC.
- `vitest` menangkap alias yang tidak terdaftar di `vitest.config.js`;
  `npm run build` tidak menangkapnya karena membaca `vite.config.js` yang
  terpisah.

Jangan menaikkan `--max` untuk melewatkan temuan baru. Begitu ambangnya naik,
gerbang berhenti dapat membedakan kerusakan lama dari kerusakan baru — itu
persis kondisi yang meloloskan kerusakan sebelumnya.

### 17.1 Sapuan konsol peramban

Kelima skrip di atas memeriksa struktur; tidak satu pun melihat apa yang
sebenarnya terjadi saat halaman dirender. Peringatan Vue — prop bertipe salah,
binding ke variabel yang tak pernah ada, komponen tak dikenal — tidak
menggagalkan build, tidak menggagalkan test, dan tidak muncul di mana pun selain
konsol peramban.

Cara menemukannya tanpa membuka 94 halaman satu per satu:

1. Turunkan daftar halaman dari `scripts/route-snapshot.php`, saring ke rute GET
   tanpa parameter yang aksinya benar-benar memanggil `inertia(` atau
   `Inertia::render`.
2. Muat tiap halaman dengan Playwright memakai fixture `masuk` di
   `tests/e2e/support/uat.js`, rekam `console`, `pageerror`, dan `requestfailed`.
3. Kelompokkan pesannya setelah membuang bagian `at <Komponen ...>`, sehingga
   pesan sejenis dari banyak halaman menyatu menjadi satu temuan.

Nilainya ada pada pengelompokan itu: satu sebab kerap muncul di belasan halaman,
dan memperbaikinya sekali membersihkan semuanya. Pada sapuan 2026-08-18, 183
temuan mengerucut menjadi enam sebab yang masing-masing satu berkas.

Sapuan ini **bukan** gerbang wajib: ia menuntut lingkungan hidup, sesi
terautentikasi, dan belasan menit. Jalankan sesudah perubahan lintas halaman,
bukan pada setiap commit. Berkas spesifikasinya bersifat sementara dan tidak
ikut di-commit — `tests/e2e` tetap berisi UAT SOP saja.

Jebakan yang sudah menggigit: mencatat XHR per halaman dalam SATU tab membuat
permintaan halaman sebelumnya yang baru mendarat terbaca seolah milik halaman
berikutnya. Bila yang diperiksa adalah "halaman ini memanggil endpoint apa",
pakai konteks peramban terpisah per halaman.

## 18. Struktur direktori test

Test mengikuti struktur modul yang sama dengan source:

```text
tests/Unit/<MODUL>/<Submodul>/
tests/Feature/<MODUL>/<Submodul>/
tests/Integration/<MODUL>/<Submodul>/
tests/e2e/                          Playwright, di luar PHPUnit
tests/TestCase.php                  dipakai seluruh modul, tetap di akar
tests/CreatesApplication.php        idem
```

Kepemilikan modul ditentukan dari **domain bisnis yang diuji**, bukan dari
modul yang paling banyak diimpor. Sebuah test cuti yang banyak memakai layanan
outbox tetap milik `ESS`, bukan `SYSTEM`. Sebaliknya, test yang menguji kelas
milik `SYSTEM/TaskProcessing` tetap milik `SYSTEM` meski namanya menyebut LMS.

`phpunit.xml` memakai testsuite berbasis direktori, sehingga penataan subfolder
tidak memengaruhi keanggotaan suite.

## 16. Roadmap implementasi tooling

### Fase 1 — Governance

- gunakan dokumen ini sebagai rule agent;
- buat template test plan/impact matrix;
- bedakan smoke, regression, dan manual acceptance;
- ubah laporan agent agar menyertakan evidence.

### Fase 2 — Existing stack

- tambah script Playwright standar;
- buat smoke tags;
- tambah console/network gate;
- pastikan Vitest/PHPUnit command mudah dijalankan;
- catat baseline failures.

### Fase 3 — Environment

- siapkan dedicated test/UAT DB;
- siapkan test accounts/fixtures;
- hentikan ketergantungan browser test pada DB development bersama.

### Fase 4 — Contract dan accessibility

- buat verified API fixtures/contract tests;
- tambahkan `@axe-core/playwright`;
- terapkan critical/serious accessibility gate.

### Fase 5 — CI dan regression

- aktifkan browser smoke di CI;
- naikkan gate bertahap dari report-only ke blocking;
- jadwalkan full regression;
- tambahkan visual snapshot hanya pada flow deterministik.

Automated testing dianggap matang setelah beberapa siklus perubahan berbeda lulus dan user melakukan manual validation berulang tanpa menemukan regression yang sama. Tidak ada jumlah siklus yang menjadi jaminan bebas bug; confidence harus dibangun dari evidence.
