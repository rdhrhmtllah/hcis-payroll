# Alur rilis dan penjaganya

Dokumen ini menjelaskan bagaimana kode berpindah dari worktree ke produksi, dan
apa saja yang menahan perpindahan itu ketika seharusnya tertahan. Yang dijaga di
sini bukan kualitas kode — itu urusan review dan uji — melainkan **kode yang
belum boleh ikut rilis tetapi ikut terbawa tanpa ada yang menyadarinya**.

## Peran tiap branch

Ada **dua branch nyata**, dan keduanya adalah **dua jalur terpisah yang tidak
pernah bertemu**. "Development" bukan branch, melainkan sebutan untuk branch
kerja: `feat/*`, `fix/*`, `chore/*`.

```
                                      ┌──► master   ──► Cloud Run pra-produksi
kerja biasa   feat/*, fix/* ─► PR ────┘                 (diperlakukan produksi)

fitur ditahan  branch fiturnya ─► PR ─► staging  ──► Cloud Run staging-dev
                                        (tak pernah di-merge keluar)
```

| Branch | Perannya | Menerima dari | Diteruskan ke |
|---|---|---|---|
| `feat/*`, `fix/*` | Kerja harian, satu per worktree | — | `master` |
| `master` | Rilis; deploy `hcisxkpi` | branch kerja biasa | Cloud Run pra-produksi |
| `staging` | Deploy `hcis-dev` | branch fitur yang **ditahan** | **tidak ke mana-mana** |

Tiga aturan yang menopang semuanya:

- **Kerja biasa tidak singgah di `staging`.** Tempat ujinya adalah pra-produksi
  yang dicapai lewat `master`. Menyinggahkannya di staging hanya mencampurnya
  dengan fitur yang ditahan tanpa keuntungan apa pun.
- **Tidak ada yang pernah di-merge keluar dari `staging`.** Ia hanya menerima,
  dan boleh dibangun ulang kapan saja dari `master` ditambah branch fitur yang
  sedang diuji — itu memang perannya. Aturan inilah yang membuat bercampurnya
  isi staging tidak berbiaya.
- **Jangan pernah `git merge origin/staging` ke branch kerja.** Itu menarik
  seluruh fitur yang ditahan masuk ke branch itu, dan PR `master`-nya akan
  ditolak `Gerbang rilis`. Mutakhirkan dari `master`.

Fitur yang ditahan tinggal di branch-nya sendiri dan hanya di-merge ke `staging`.
Saat dinyatakan siap: tambahkan `master` ke `boleh_di` di manifes lewat PR, lalu
merge branch-nya ke `master` seperti kerja biasa.

**Catatan sejarah, supaya tidak diulang.** Rancangan pertama (8 Sep 2026)
memakai satu jalur — semua branch kerja di-merge dua kali, ke `staging` dulu baru
ke `master` — lengkap dengan penjaga `Urutan rilis` yang menolak PR master yang
belum lewat staging. Model itu dibatalkan pada hari yang sama: ia memaksa kerja
biasa bercampur dengan fitur yang ditahan tanpa alasan, padahal `master` sudah
mendarat di pra-produksi yang memang tempat ujinya. Penjaga `Urutan rilis` ikut
dicabut; kalau suatu saat modelnya berubah lagi, kodenya masih ada di branch
`chore/penjaga-urutan-rilis`.

## Penjaga 1 — Gerbang rilis

Berkas: `.github/gerbang-rilis.json` (data) dan `.github/scripts/gerbang-rilis.mjs`
(logika). Skripnya tidak pernah menyebut nama fitur apa pun; menahan fitur
berikutnya cukup menambah satu blok di manifes.

```json
{
  "kode": "PA",
  "nama": "Performance Appraisal",
  "alasan": "Masih dikembangkan.",
  "boleh_di": ["staging"],
  "paths": ["app/**/PerformanceAppraisal/**"],
  "penanda": ["PerformanceAppraisal", "N_PA_"],
  "penanda_kecuali": [".github/gerbang-rilis.json", "database/ddl/**", "docs/**"]
}
```

Dua saringan bekerja bersama, dan yang kedua yang menutup celah sebenarnya:

- **`paths`** menangkap berkas yang jelas-jelas milik fitur.
- **`penanda`** menangkap fitur yang menyusup lewat berkas **bersama** — satu
  baris provider di `config/app.php`, satu `require` di `routes/web.php`, satu
  baris modul di `config/exports.php`. Berkas itu bukan milik fitur mana pun
  sehingga lolos saringan path, padahal justru baris itulah yang membuat
  produksi meledak. Hanya baris yang **ditambah** yang diperiksa; mencabut jejak
  fitur dari berkas bersama tidak pernah tertahan.

`penanda_kecuali` **wajib memuat manifes itu sendiri**. Tanpa itu, PR yang
mempromosikan fitur akan ditolak oleh penandanya sendiri dan gerbangnya terkunci
selamanya dari dalam.

### Kalau gerbang menolak PR Anda

Laporan CI menyebutkan berkas dan barisnya. Tiga jalan keluar, berurut dari yang
paling sering benar:

1. **Arahkan PR ke branch yang diizinkan** (biasanya `staging`).
2. **Pecah PR-nya.** Kalau ada perubahan pada kode bersama yang ikut terbawa,
   perubahan itu harus berdiri sendiri tanpa menyebut fitur ini, lalu mendarat
   lebih dulu. Ini bukan formalitas: kode bersama yang hanya masuk akal bila
   dibaca bersama fiturnya memang belum siap jadi kode bersama.
3. **Promosikan fiturnya** — tambahkan branch tujuan ke `boleh_di` lewat PR
   tersendiri. Karena manifes dilindungi `CODEOWNERS`, persetujuan PR itulah
   keputusan rilisnya, dan keputusan itu tercatat di riwayat git.

Pengecualian sekali jalan: beri PR label `izinkan-<kode fitur huruf kecil>`,
misalnya `izinkan-pa`. Pintu ini sengaja ada — tanpa pintu keluar yang sah, orang
akan mencabut penjaganya, bukan memakai pintunya.

**Cabut entri fitur yang sudah rilis penuh.** Manifes basi menahan PR yang sah,
dan yang frustrasi akan mencabut penjaganya, bukan entrinya.

## Penjaga 2 — Perubahan basis data

Berkas: `.github/scripts/penjaga-perubahan-db.mjs`. Menahan tiga hal yang
berulang kali dilakukan agent yang kehilangan konteks, dan ketiganya tidak pernah
gagal saat uji berjalan:

- **Migration Laravel baru.** Proyek ini sengaja tidak memakai migration untuk
  perubahan skema. Migration baru berjalan otomatis saat deploy dan mengubah
  skema produksi tanpa ada yang memutuskan. Migration bawaan yang sudah ada tidak
  diusik — yang ditahan hanya berkas yang baru ditambahkan.
- **Perubahan tanpa pasangan atau tanpa catatan.** `database/ddl/<id>/` wajib
  punya `forward.sql` dan `drop.sql`, `database/dml/<id>/` wajib punya
  `data-change.sql`, dan keduanya wajib punya entri di `database/ddl/status.json`.
  Perubahan yang tak tercatat tidak akan pernah dijalankan di produksi, dan
  ketiadaannya baru ketahuan saat fiturnya mati.
- **Menyunting perubahan yang sudah beku.** Begitu sebuah perubahan punya
  referensi SQL Tracker atau berstatus produksi `DONE`, berkasnya menjadi catatan
  tentang apa yang benar-benar dijalankan. Perbaikan wajib ditulis sebagai
  perubahan baru. Sebelum garis itu, menyunting di tempat justru dianjurkan.

## Penjaga 3 — Hook lokal

`.githooks/pre-push` menolak push ke branch beralur dari worktree yang tidak
diberi izin untuknya. Pemasangan sekali per clone:

```sh
git config core.hooksPath .githooks
git config extensions.worktreeConfig true
```

Penandaan, dijalankan **di dalam** tiap worktree:

```sh
git config --worktree hcis.target "master"    # worktree kerja biasa
git config --worktree hcis.target "staging"   # worktree fitur yang ditahan
```

`git config` biasa menulis ke `.git/config` yang **dibagi seluruh worktree**, jadi
tanpa `--worktree` satu penandaan mengubah semuanya sekaligus — dan salahnya baru
terlihat ketika push yang seharusnya ditolak justru lolos.

Worktree yang belum diberi penanda diperlakukan **paling ketat**: hanya boleh
mendorong branch kerja. Penanda yang lupa dipasang tidak boleh berubah jadi izin.

Ini rambu, bukan pagar: bisa dilewati `--no-verify`, dan `core.hooksPath` harus
diset per-clone. Gunanya menghentikan kesalahan beberapa detik setelah terjadi.

## Yang harus dipasang di GitHub

Bagian ini tidak bisa diletakkan di repo — harus diklik oleh pemilik repo. Inilah
satu-satunya lapis yang benar-benar mengikat; sisanya membuat kesalahan ketahuan
lebih cepat.

**Ruleset untuk `master`**
- Larang push langsung dan penghapusan branch; larang force push.
- Wajib pull request, minimal satu approval, dan review dari code owner.
- Wajib percakapan review selesai sebelum merge.
- Required status checks: `Gerbang rilis`, `Perubahan database`, `Uji penjaga`,
  `Backend quality`, `Frontend quality`.
- Wajib branch mutakhir terhadap base sebelum merge.
- **Daftar bypass dikosongkan** — termasuk untuk pemilik repo. Tanpa ini seluruh
  susunan ini hanya berlaku bagi yang tidak punya pilihan lain.

**Ruleset untuk `staging`** — larang push langsung, wajib PR dengan check yang
sama, tetapi **izinkan force push**. Staging memang dibangun ulang, dan tak ada
yang pernah bercabang darinya.

**Token per agent.** Tiap agent memakai akun atau GitHub App sendiri dengan izin
minimal, dan tak satu pun masuk daftar bypass. Ini yang membuat "agent A boleh
mendaratkan ke master, agent B tidak" jadi kenyataan dan bukan imbauan. **Token
agent tidak boleh punya izin administrasi repo** — yang bisa mengubah ruleset
bisa membatalkan seluruh dokumen ini.

**Secret scanning dan push protection** dinyalakan.

**Environment `production`** dengan required reviewer = pemilik repo. Workflow
deploy produksi berhenti menunggu klik; agent boleh memicu, tak bisa melanjutkan.
Secret produksi hanya hidup di environment ini sehingga tak pernah terbaca job
biasa.

## Deploy

Keduanya lewat Cloud Build, bukan GitHub Actions, sehingga tidak ada kredensial
GCP yang perlu disimpan di GitHub sama sekali.

- Push ke `master` → trigger lama → service `hcisxkpi` (diperlakukan produksi).
- Push ke `staging` → `cloudbuild.yaml` → service `hcis-dev`.

Karena push ke `master` langsung mendarat di lingkungan yang diperlakukan
produksi, **gerbang manusianya adalah approval pull request** — yang menyetujui
merge sedang menyetujui rilis. Itulah sebabnya ruleset `master` mewajibkan
approval dan bukan sekadar check hijau.

Penyiapan staging selengkapnya, termasuk env yang wajib berbeda dan cara
me-refresh `Web_HRIS_Cut_Off`, ada di [`staging-cloud-run.md`](staging-cloud-run.md).

Satu sifat yang tidak boleh ditawar: **deploy hanya dari trigger.** Tidak ada
`gcloud run deploy` dari laptop atau dari sesi agent untuk lingkungan mana pun,
dan kredensial basis data produksi tidak pernah ada di mesin tempat agent
bekerja.

## Celah yang tetap ada

Ditulis di sini supaya tidak ada yang mengira susunan ini rapat:

- **Penanda teks itu heuristik.** Pemanggilan dinamis lewat string terangkai atau
  nilai config tidak akan memuat nama fiturnya di baris yang berubah.
- **Token dengan izin administrasi repo mengalahkan semuanya.**
- **CI tidak menjaga basis data.** DDL dan DML dijalankan manual; siapa pun yang
  memegang kredensial produksi bisa mengubahnya tanpa lewat pipeline.
- **Check hijau bukan berarti teruji.** Suite bisa lulus sambil tidak menguji apa
  pun. Hanya review yang bisa menilai itu.
- **Fitur yang lama ditahan akan menyimpang.** Makin lama sebuah fitur duduk di
  luar `master`, makin besar merge terakhirnya, dan ia tak pernah teruji di
  atas artefak yang persis sama dengan produksi. Penjaga tidak menghapus biaya
  ini; ia hanya mencegah biaya itu dibayar diam-diam di produksi.
