# Staging Cloud Run

Lingkungan untuk mencoba branch `staging` di Cloud Run sebelum apa pun mendarat
di `master`. Alur branch-nya ada di
[`alur-rilis-dan-penjaga.md`](alur-rilis-dan-penjaga.md); dokumen ini hanya soal
infrastrukturnya.

## Keadaan yang sudah ada

| | Produksi |
|---|---|
| Project | `project-6a30e7a0-bed0-4614-a28` |
| Region | `asia-southeast1` |
| Service | `hcisxkpi` |
| Image | `asia-southeast1-docker.pkg.dev/…/cloud-run-source-deploy/kpi-unreal/hcisxkpi:<commit-sha>` |
| Pemicu | push ke `master` → Cloud Build → deploy |
| Port kontainer | 80 |

Karena push ke `master` langsung mendarat di lingkungan yang diperlakukan sebagai
produksi, **gerbang manusianya adalah approval pull request**, bukan approval
deploy tersendiri. Yang menyetujui merge ke `master` sedang menyetujui rilis.

## Yang dibangun

| | Staging |
|---|---|
| Project & region | sama dengan produksi |
| Service | `hcis-dev` (`https://hcis-dev-595840247695.asia-southeast1.run.app`) |
| Image | `…/kpi-unreal/hcis-dev:<commit-sha>` (repositori terpisah) |
| Pemicu | push ke `staging` → Cloud Build (`cloudbuild.yaml`) → deploy |
| Database | `Web_HRIS_Cut_Off` |

Tidak ada kredensial baru di GitHub: Cloud Build sudah tersambung ke repo, jadi
staging hanya menambah satu trigger. Workload Identity Federation baru relevan
kalau kelak deploy pindah ke GitHub Actions.

## Langkah 1 — Buat service sekali (butuh akun ber-izin)

Konfigurasi lingkungan dipasang **sekali** di sini. Setelah itu setiap deploy
hanya menukar image dan mempertahankan env yang sudah ada — karena itulah
`cloudbuild.yaml` tidak boleh menyebut env sama sekali.

Isi dulu nilai yang dikosongkan. Jangan menyalin nilai produksi apa adanya untuk
baris-baris di tabel Langkah 2.

```sh
gcloud run deploy hcis-dev \
  --region=asia-southeast1 \
  --platform=managed \
  --port=80 \
  --max-instances=1 \
  --allow-unauthenticated \
  --image=asia-southeast1-docker.pkg.dev/project-6a30e7a0-bed0-4614-a28/cloud-run-source-deploy/kpi-unreal/hcisxkpi:latest \
  --set-env-vars="APP_ENV=staging,APP_DEBUG=false,APP_NAME=HCIS Staging" \
  --set-env-vars="APP_KEY=<kunci BARU, jangan kunci produksi>" \
  --set-env-vars="APP_URL=<url staging setelah service jadi>" \
  --set-env-vars="DB_CONNECTION=sqlsrv,DB_DATABASE=Web_HRIS_Cut_Off" \
  --set-env-vars="DB_HOST=<host>,DB_PORT=<port>,DB_USERNAME=<user>,DB_PASSWORD=<sandi>,DB_TRUST_SERVER_CERTIFICATE=true" \
  --set-env-vars="SESSION_DRIVER=database,SESSION_LIFETIME=120,SESSION_SECURE_COOKIE=true,SESSION_SAME_SITE=lax"
```

`APP_URL` baru diketahui setelah service pertama kali jadi; jalankan ulang
`gcloud run services update hcis-dev --update-env-vars APP_URL=…`
sesudahnya. Sisa env pada tabel Langkah 2 dipasang dengan `--update-env-vars`
yang sama.

## Langkah 2 — Env yang WAJIB berbeda dari produksi

Staging yang berbagi env dengan produksi bukan staging, melainkan produksi kedua.
Urutan di bawah dari yang paling merusak.

| Env | Kalau disamakan dengan produksi |
|---|---|
| `Phone_Id`, `WHATSAPP_API_ENDPOINT` | **Staging mengirim WhatsApp sungguhan ke karyawan sungguhan.** Tidak ada penjaga mode apa pun di kode — tak ada `dummy`, tak ada allowlist — jadi begitu ada notifikasi, pesannya benar-benar terkirim. Arahkan ke endpoint mati atau nomor uji **sebelum** staging pertama kali dinyalakan, bukan sesudah kejadian |
| `CLOUD_TASKS_QUEUE*`, `CLOUD_TASKS_HANDLER_URL` | Task yang dibuat staging dieksekusi oleh **produksi**, dan sebaliknya. Buat antrean sendiri dan arahkan handler ke URL staging |
| `DB_DATABASE` | Uji staging menulis ke basis data yang dipakai orang |
| `APP_KEY` | Kunci sama membuat cookie dan data terenkripsi staging sah di produksi |
| `GOOGLE_CLOUD_STORAGE_BUCKET` | Berkas uji masuk bucket produksi dan ikut terlayani ke user |
| `BUNNY_*` | Video uji masuk library produksi. Pakai `BUNNY_COLLECTION_ID` sendiri |
| `HCLEARN_API_*` | Staging memanggil HC Learn sungguhan dan membuat jadwal sungguhan di sana |
| `API_TOKEN` | Token pihak ketiga yang sama membuat panggilan staging tak terbedakan dari produksi di sisi lawan |

Boleh sama: `GOOGLE_CLOUD_PROJECT_ID`, `CLOUDFLARE_TURNSTILE_*` (kalau domainnya
diizinkan), `LOG_VIEWER_API_STATEFUL_DOMAINS` (sesuaikan domain),
`MENU_MATRIX_ENFORCE_TARGET_SCOPE`. Juga `DB_HOST`, `DB_PORT`, `DB_USERNAME`,
`DB_PASSWORD` selama `Web_HRIS_Cut_Off` berada di instance yang sama.

### Dua jebakan yang sudah menggigit di service ini

**Membandingkan env berbasis secret dengan nilainya tidak berarti apa-apa.** Pada
service ini hanya dua variabel yang merupakan referensi Secret Manager —
`API_TOKEN` → `APIWaToken:latest` dan `Phone_Id` → `Phone_IdWa:latest` — dan
keduanya muncul sebagai penanda buram, bukan nilai. Membandingkan staging dengan
produksi apa adanya akan melaporkan "sama persis" untuk setiap variabel semacam
itu, terlepas dari isi secretnya. Bandingkan `valueFrom.secretKeyRef` (nama
secret + versi), bukan `value`. Sisanya, termasuk `DB_PASSWORD`, `BUNNY_API_KEY`,
`HCLEARN_API_SECRET`, dan `CLOUDFLARE_TURNSTILE_SECRET`, tersimpan sebagai
literal biasa — layak dipindahkan ke Secret Manager suatu saat.

**`--remove-env-vars` tidak menyentuh variabel berbasis secret, dan tidak
mengeluh.** Perintahnya sukses, revisi baru terbentuk, variabelnya tetap ada.
Untuk melepasnya:

```sh
gcloud run services update hcis-dev --region asia-southeast1 \
  --remove-secrets=Phone_Id
```

Mencoba menimpanya dengan literal kosong (`--update-env-vars="Phone_Id="`) justru
gagal terang-terangan dengan *"Cannot update environment variable [Phone_Id] to
string literal because it has already been set with a different type"* — dan
justru galat itulah yang menyingkap bahwa ia sebuah secret.

## Langkah 3 — Trigger Cloud Build

Sudah ada. Keduanya memakai service account yang sama
(`595840247695-compute@developer.gserviceaccount.com`).

| | Produksi | Staging |
|---|---|---|
| Nama | `rmgpgab-hcisxkpi-asia-southeast1-…` | `stagging-dev-hcis` |
| Branch | `^master$` | `^staging$` |
| Konfigurasi | **inline di dalam trigger**, tanpa berkas di repo | `cloudbuild.yaml` |
| Langkah | build Dockerfile → push → `gcloud run services update` | sama |

Trigger produksi dibuat lewat *continuous deployment* Cloud Run, sehingga langkah
buildnya tersimpan di dalam trigger dan **tidak membaca berkas apa pun di repo**.
Itu sebabnya `cloudbuild.yaml` di akar repo aman memakai nama bawaan: produksi
tidak akan pernah memungutnya.

Meski begitu, nama bawaan tetap berbahaya untuk trigger yang dibuat di kemudian
hari tanpa mengisi kolom filename. Karena itu langkah pertama di `cloudbuild.yaml`
memeriksa `$BRANCH_NAME` dan menolak jalan di luar `staging`. Penjaga itu tidak
boleh dicabut hanya karena terlihat berlebihan — ia justru bekerja pada hari
ketika seseorang membuat trigger baru dan lupa satu kolom.

Kalau trigger perlu diubah, akun compute default **bisa membaca tapi tidak bisa
menulis** trigger; perubahan harus dari akun pemilik.

Langkah deploy sengaja memakai `gcloud run services update`, sama seperti
produksi, bukan `gcloud run deploy`. Perbedaannya kecil tapi nyata: `run deploy`
sanggup **membuat** service kalau namanya salah ketik, dan service baru itu lahir
tanpa satu pun env lalu naik melayani permintaan dengan konfigurasi kosong.
`services update` gagal terang-terangan pada nama yang tidak ada.

## Langkah 4 — Buat branch `staging`

```sh
git fetch origin
git checkout -b staging origin/master
git push -u origin staging
```

Lalu masukkan fitur yang sedang ditahan:

```sh
git merge --no-ff origin/feat/performance-apprisal
git push
```

Staging **tidak pernah di-merge keluar**. Kalau isinya kacau, buang dan bangun
ulang dari `master` ditambah branch yang sedang diuji — itu memang perannya.

## Langkah 5 — Basis data `Web_HRIS_Cut_Off`

Perlakukan sebagai basis data buang. Jangan mencoba "menyamakan isi" secara
terus-menerus dengan `Web_HRIS`: sinkronisasi dua arah selalu menyimpang dan
menjadi proyek tersendiri. Yang dibutuhkan hanya **salinan satu arah yang murah
diulang**.

Pada instance yang sama:

```sql
BACKUP DATABASE Web_HRIS
    TO DISK = 'D:\refresh\Web_HRIS_refresh.bak'
    WITH INIT, COPY_ONLY, COMPRESSION;

ALTER DATABASE Web_HRIS_Cut_Off SET SINGLE_USER WITH ROLLBACK IMMEDIATE;

RESTORE DATABASE Web_HRIS_Cut_Off
    FROM DISK = 'D:\refresh\Web_HRIS_refresh.bak'
    WITH REPLACE,
         MOVE 'Web_HRIS'     TO 'D:\data\Web_HRIS_Cut_Off.mdf',
         MOVE 'Web_HRIS_log' TO 'D:\data\Web_HRIS_Cut_Off_log.ldf';

ALTER DATABASE Web_HRIS_Cut_Off SET MULTI_USER;
```

Nama berkas logis pada `MOVE` diperiksa dengan
`RESTORE FILELISTONLY FROM DISK = '…'` sebelum dijalankan pertama kali.

Tiga akibat yang disengaja:

- **Skema staging otomatis ikut development.** DDL tidak perlu dijalankan dua
  kali; yang dijalankan di `Web_HRIS` akan ikut terbawa pada refresh berikutnya.
- **Apa pun yang ditulis staging hilang saat refresh.** Itu fitur, bukan cacat —
  staging tidak boleh jadi tempat menyimpan sesuatu.
- **Salinan ini membawa data karyawan sungguhan**, termasuk nomor telepon. Inilah
  alasan kedua kenapa env WhatsApp di Langkah 2 harus sudah diarahkan ke tempat
  yang aman sebelum staging pertama kali hidup.

## Setelah deploy pertama — daftar periksa

1. `gcloud run services describe hcis-dev --region asia-southeast1` —
   pastikan tag image = commit SHA `staging`, bukan `latest`.
2. Buka `APP_URL` staging, login, dan pastikan halaman terbuka.
3. Pastikan `DB_DATABASE` benar-benar `Web_HRIS_Cut_Off` — cek lewat halaman yang
   menampilkan data, bukan lewat menebak dari env.
4. Picu satu notifikasi dan pastikan **tidak ada** WhatsApp yang benar-benar
   terkirim.
5. Buat satu task antrean dan pastikan yang mengeksekusinya service staging,
   bukan produksi — terlihat di log Cloud Run service mana yang menerima
   `/handle-task`.
6. Pastikan revisi produksi `hcisxkpi` **tidak berubah** setelah push ke
   `staging`. Kalau berubah, pola branch trigger produksi terlalu longgar.

## Riwayat nama service

Service staging semula bernama `hcisxkpi-stagging-dev` — dua huruf g, salah eja —
dan diganti menjadi `hcis-dev` pada 8 September 2026. Cloud Run **tidak bisa
mengganti nama service**: URL diturunkan dari namanya, jadi penggantian berarti
membuat service baru, memindahkan seluruh konfigurasinya, lalu menghapus yang
lama.

Cara memindahkannya yang benar adalah lewat YAML, bukan mengetik ulang env satu
per satu:

```sh
gcloud run services describe <lama> --region asia-southeast1 --format=export > svc.yaml
# ganti metadata.name; buang anotasi milik server (run.googleapis.com/urls,
# ingress-status, satisfiesPzs, creator/lastModifier, operation-id) dan nama
# revisi, karena semuanya dibuat ulang oleh Cloud Run
gcloud run services replace svc.yaml --region asia-southeast1
```

Yang menyelamatkan cara ini: `API_TOKEN` pada service ini adalah **referensi
Secret Manager**, bukan literal. Menyalin env dengan membaca nilainya akan
menghasilkan string `<<ref>>` atau kosong, dan service baru naik tanpa token
tanpa satu pun galat saat deploy.

Jebakan yang sudah kena: mengganti nama dengan cari-ganti menyeluruh ikut
mengganti **path image**, yang belum ada di Artifact Registry. Deploy gagal
dengan `Image ... not found`. Saat membuat service baru, image harus tetap
menunjuk repositori lama; repositori baru baru terisi pada build berikutnya.

Sesudah service baru jadi, tiga hal wajib menyusul: `APP_URL` dan
`CLOUD_TASKS_HANDLER_URL` diarahkan ke URL baru (kalau tidak, task yang dibuat
staging dieksekusi service lama), `_SERVICE` dan `_IMAGE` di `cloudbuild.yaml`
diganti, dan service lama dihapus setelah build pertama ke service baru terbukti
hijau — bukan sebelumnya.
