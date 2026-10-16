# hcis-avscan — layanan pemindai antivirus Library Konten

> **Status: belum dipakai.** `KONTEN_AV_URL` sengaja dikosongkan dan
> `KONTEN_AV_WAJIB=false`, sehingga tahap pemindaian dilewati dan verifikasi
> berhenti di pemeriksaan struktur. Semua di sini sudah siap; untuk
> menyalakannya, deploy sesuai bagian **Deploy** lalu isi kedua variabel itu
> bersamaan. Tak ada kode yang perlu diubah.

Lapisan terakhir verifikasi berkas: menangkap berkas yang **formatnya sah tapi
isinya jahat** — yang tak bisa ditangkap allowlist ekstensi maupun pemeriksaan
struktur, karena docx ber-malware tetap docx yang sempurna secara struktur.

Dipanggil verifikator Fase 2 (`KontenVerifikasiWebhookController`) setelah
pemeriksaan struktur lolos dan **sebelum** objeknya dipromosikan dari karantina.

## Bentuknya

```
Laravel                     Cloud Run: hcis-avscan          GCS
  |                                |                          |
  |-- POST /scan {signed url} ---->|                          |
  |   (token OIDC)                 |-- GET signed url ------->|
  |                                |<-- byte objek -----------|
  |                                |   clamd INSTREAM         |
  |<-- {bersih, tanda_tangan} -----|                          |
```

Byte-nya melintas **sekali**. Kalau Laravel yang mengunduh lalu meneruskan,
setiap berkas melintas dua kali dan Cloud Run aplikasi menahan 50 MB di memori
hanya untuk jadi perantara.

## Deploy

```bash
PROJECT=$(gcloud config get-value project)
REGION=asia-southeast1

gcloud builds submit docker/avscan \
  --tag "gcr.io/${PROJECT}/hcis-avscan"

gcloud run deploy hcis-avscan \
  --image "gcr.io/${PROJECT}/hcis-avscan" \
  --region "${REGION}" \
  --no-allow-unauthenticated \
  --memory 2Gi \
  --cpu 1 \
  --min-instances 0 \
  --max-instances 3 \
  --concurrency 4 \
  --timeout 300
```

**`--min-instances 0` — nol biaya saat idle.**

clamd memang butuh ~15–30 detik memuat basis tanda tangan saat container start,
tapi verifikasi di sistem ini **asinkron**: ia dikirim Cloud Tasks, punya retry,
dan tak ada pengguna yang menunggu di depan layar. Cloud Run menahan permintaan
selama container start (batas startup 240 detik), dan `start.sh` sengaja belum
membuka port sampai clamd siap — jadi permintaan pertama justru mendarat di
clamd yang sudah hangat. Untuk volume unggahan Library, tagihannya praktis nol.

Naikkan ke `--min-instances 1` HANYA bila Anda ingin verifikasi selesai dalam
hitungan detik alih-alih ~1 menit. Itu keputusan kenyamanan, bukan kebutuhan
teknis, dan harganya satu instance 2 Gi yang menyala terus.

`--concurrency 4` menyamai `--threads 4` di gunicorn: satu instance memindai
maksimum empat berkas sekaligus, sehingga 2 Gi tak diperebutkan lebih banyak
pemindaian daripada yang bisa ia tampung.

### Kenapa layanan terpisah, bukan sidecar di layanan aplikasi

Cloud Run mendukung multi-container, jadi menyatukannya bukan hal yang mustahil.
Yang membuatnya mahal adalah dua hal:

- Batas memori berlaku **per-instance untuk seluruh container**. Aplikasi yang
  kini 512Mi akan menjadi ~2,5Gi per instance, dikali jumlah instance saat
  menskala — padahal pemindaian hanya terjadi sesekali.
- Sebuah instance belum dianggap siap sampai **semua** container-nya siap. Jadi
  setiap penambahan instance akibat lonjakan trafik web harus menunggu 1 GB
  basis tanda tangan selesai dimuat lebih dulu.

Artinya biaya dibayar di jalur panas (trafik web) untuk sesuatu yang dipakai di
jalur dingin (unggah Library). Layanan terpisah membuat keduanya menskala
menurut kebutuhannya masing-masing.

**`--no-allow-unauthenticated` wajib.** Layanan ini menerima URL bertanda tangan
dari pemanggilnya; membiarkannya publik berarti siapa pun bisa memakainya
sebagai pemindai gratis, dan lebih buruk lagi, sebagai penarik URL sembarang.

Beri service account aplikasi izin memanggilnya:

```bash
gcloud run services add-iam-policy-binding hcis-avscan \
  --region "${REGION}" \
  --member "serviceAccount:<SA-APLIKASI>@${PROJECT}.iam.gserviceaccount.com" \
  --role roles/run.invoker
```

Lalu isi `.env` aplikasi:

```
KONTEN_AV_URL=https://hcis-avscan-xxxxx-as.a.run.app
KONTEN_AV_WAJIB=true
```

## Menguji tanpa deploy

Berkas uji EICAR adalah string tak berbahaya yang SEMUA antivirus sepakat
mendeteksinya — dipakai justru supaya pengujian tak perlu malware sungguhan:

```bash
docker build -t avscan docker/avscan
docker run --rm -p 8080:8080 avscan
# di terminal lain, sajikan berkas EICAR lalu:
curl -s localhost:8080/scan -H 'Content-Type: application/json' \
  -d '{"url":"http://host.docker.internal:8000/eicar.txt"}'
# -> {"bersih":false,"tanda_tangan":"Win.Test.EICAR_HDB-1"}
```

## Perilaku saat layanan mati

Aplikasi **fail-closed**: berkas berhenti di `TERTUNDA_PEMINDAIAN`, tidak pernah
naik ke `SIAP`, dan tidak bisa dipilih di Builder. Cloud Task mengulang dengan
backoff.

Satu batas yang perlu diketahui: objek yang menunggu berada di bucket karantina
yang lifecycle-nya menghapus pada umur 1 hari. Bila pemadaman AV melewati satu
hari, objeknya hilang dan konten tersebut harus **diunggah ulang** — statusnya
tetap `TERTUNDA_PEMINDAIAN` sebagai penanda, bukan diam-diam jadi siap.
