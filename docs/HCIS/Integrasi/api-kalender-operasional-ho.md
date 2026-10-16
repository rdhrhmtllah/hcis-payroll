# API Kalender Operasional Head Office

Panduan untuk konsumen (Web Careers). Menyebutkan tanggal berapa saja Head Office
tidak beroperasi, supaya SLA dapat dihitung dalam hari kerja.

Spec desain internal: `docs/superpowers/specs/2026-09-04-api-kalender-operasional-ho-design.md`

> **Dokumentasi interaktif: `https://<host>/api/docs`.** Masukkan `Api_Public`
> Anda di sana untuk melihat endpoint yang memang diizinkan untuk kunci itu —
> daftarnya dibaca dari registry yang sama dengan yang dipakai gerbang izin, jadi
> tidak mungkin menampilkan endpoint yang ternyata dijawab 403. Bila `Api_Secret`
> ikut dimasukkan, tombol **Try it out** akan menandatangani permintaan sendiri
> di peramban Anda; rahasianya tidak pernah dikirim ke server HCIS.

## Autentikasi

Setiap permintaan ditandatangani HMAC-SHA512 memakai pasangan kunci yang
diberikan HCIS.

| Header | Isi |
|---|---|
| `X-HC-Public` | kunci publik Anda |
| `X-HC-Timestamp` | waktu permintaan; selisih maksimal **60 detik** terhadap jam server |
| `X-HC-Nonce` | nilai acak, **sekali pakai** — nonce yang berulang ditolak |
| `X-HC-Body-Hash` | `sha256(body)`; untuk `GET` body kosong, jadi `sha256("")` |
| `X-HC-Signature` | `hmac_sha512(payload, api_secret)` |

`payload` adalah tujuh baris berikut, dipisah `\n`:

```
METHOD
PATH
QUERY
TIMESTAMP
NONCE
BODY_HASH
PUBLIC_KEY
```

- **`PATH` adalah jalur yang benar-benar Anda panggil**, diawali `/` dan tanpa
  query string. Untuk permintaan tahun 2026 nilainya
  `/api/v1/hcis/kalender-operasional/tahun/2026` — bukan pola rute internal kami.
- `QUERY` adalah query string mentah tanpa `?`, mis. `dari=2026-09-01&sampai=2026-12-31`.
  Kosongkan (string kosong) bila tidak ada.
- `METHOD` huruf besar: `GET`.

Contoh (PHP):

```php
$method    = 'GET';
$path      = '/api/v1/hcis/kalender-operasional/tahun/2026';
$query     = '';
$timestamp = gmdate('c');
$nonce     = bin2hex(random_bytes(16));
$bodyHash  = hash('sha256', '');

$payload = implode("\n", [$method, $path, $query, $timestamp, $nonce, $bodyHash, $apiPublic]);
$signature = hash_hmac('sha512', $payload, $apiSecret);
```

Di production permintaan wajib lewat HTTPS.

## 1. Kalender setahun

```
GET /api/v1/hcis/kalender-operasional/tahun/{tahun}
```

| Nama | Posisi | Wajib | Contoh | Keterangan |
|---|---|---|---|---|
| `tahun` | path | wajib | `2026` | 4 digit, antara 2020 dan 2100 |
| `unit` | query | opsional | `HEAD_OFFICE` | default `HEAD_OFFICE` |

```json
{
  "sukses": true,
  "message": "Kalender operasional 2026",
  "result": {
    "unit": "HEAD_OFFICE",
    "tahun": 2026,
    "hari_kerja_mingguan": [1, 2, 3, 4, 5, 6],
    "digenerate_at": "2026-09-02T08:00:00+07:00",
    "tanggal_tidak_operasional": [
      { "tanggal": "2026-03-31", "jenis": "LIBUR_NASIONAL", "nama": "Hari Raya Idul Fitri 1447 Hijriah" },
      { "tanggal": "2026-04-02", "jenis": "CUTI_BERSAMA", "nama": "Cuti Bersama Idul Fitri", "status": "TERJADWAL" }
    ]
  }
}
```

## 2. Kalender per rentang

```
GET /api/v1/hcis/kalender-operasional?dari=2026-09-01&sampai=2026-12-31
```

| Nama | Posisi | Wajib | Contoh | Keterangan |
|---|---|---|---|---|
| `dari` | query | wajib | `2026-09-01` | `YYYY-MM-DD`, inklusif |
| `sampai` | query | wajib | `2026-12-31` | `YYYY-MM-DD`, inklusif |
| `unit` | query | opsional | `HEAD_OFFICE` | default `HEAD_OFFICE` |

`result` sama seperti endpoint tahun, hanya `tahun` diganti `dari` dan `sampai`.

## 3. Field response

| Field | Tipe | Keterangan |
|---|---|---|
| `unit` | string | unit yang diminta |
| `hari_kerja_mingguan` | array int | 1 = Senin … 6 = Sabtu, 0 = Minggu |
| `digenerate_at` | ISO 8601 | waktu data dihasilkan, offset `+07:00` |
| `tanggal_tidak_operasional` | array | urut menaik, tanpa duplikat tanggal |
| ` └ tanggal` | string | `YYYY-MM-DD` |
| ` └ jenis` | string | `LIBUR_NASIONAL` · `CUTI_BERSAMA` · `LIBUR_PERUSAHAAN` |
| ` └ nama` | string | nama libur |
| ` └ status` | string | **hanya pada `CUTI_BERSAMA`**: `TERJADWAL` · `BERJALAN` · `SELESAI` |

Catatan yang perlu diketahui saat mengonsumsi:

- Satu tanggal muncul **sekali saja**. Bila tanggal itu sekaligus libur nasional
  dan cuti bersama, `jenis`-nya `LIBUR_NASIONAL`.
- `status` hanya menerangkan posisi tanggal terhadap hari ini; ia **tidak**
  memengaruhi apakah kantor tutup. Tanggal berstatus `TERJADWAL` tetap tanggal
  kantor tutup.
- `LIBUR_PERUSAHAAN` sudah ada di kontrak sebagai cadangan, tapi belum pernah
  terbit karena sumber datanya belum ada.

## 4. Response galat

| Kondisi | Kode |
|---|---|
| `dari` / `sampai` kosong, format tanggal salah, `sampai` < `dari`, rentang > 400 hari, `unit` tak dikenal, `tahun` di luar 2020–2100 | 400 |
| Header keamanan tidak lengkap, kedaluwarsa, nonce berulang, atau tanda tangan salah | 401 |
| Aplikasi sah tapi endpoint tidak diizinkan untuknya | 403 |
| Tidak ada libur pada rentang tersebut | 200, `"tanggal_tidak_operasional": []` |

```json
{
  "sukses": false,
  "message": "Parameter 'dari' wajib diisi (format YYYY-MM-DD).",
  "result": null
}
```

Rentang tanpa libur **bukan** galat.
