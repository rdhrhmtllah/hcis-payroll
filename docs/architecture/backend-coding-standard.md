# Backend Coding Standard

Dokumen ini berlaku untuk **kode backend baru** dan refactor yang secara eksplisit disetujui. Kode legacy yang belum mengikuti standar ini tidak dipindahkan atau diubah otomatis.

## 1. Struktur ownership

Setiap file baru harus memiliki module, submodule, feature, dan layer yang jelas. Struktur target:

```text
app/Http/Controllers/<MODUL>/<Submodul>/<Fitur>/
app/Http/Requests/<MODUL>/<Submodul>/<Fitur>/
app/Models/<MODUL>/<Submodul>/<Fitur>/
app/Services/<MODUL>/<Submodul>/<Fitur>/
routes/<MODUL>/<Submodul>/<Fitur>/
tests/Feature/<MODUL>/<Submodul>/<Fitur>/
```

Role seperti `Admin`, `User`, dan `SystemAdmin` bukan module. Jika ownership fitur ambigu, keputusan harus diminta sebelum membuat file.

## 2. Controller

Controller bertugas untuk:

- menerima HTTP request;
- menggunakan Form Request untuk validasi;
- memanggil service;
- mengembalikan redirect, Inertia response, atau response API;
- menentukan status HTTP yang sesuai.

Controller tidak boleh menjadi tempat business logic, query database kompleks, transaction, loop besar, integrasi eksternal, atau response JSON manual.

Semua response API baru wajib menggunakan `App\Helpers\ResponseHelper`, termasuk response sukses, error, dan pagination. Controller tidak boleh menggunakan `response()->json()` langsung untuk endpoint baru.

Tanggal dan waktu aplikasi pada kode baru wajib menggunakan `App\Helpers\FormatTanggalHelper`. Jangan memakai `now()`, `Carbon::now()`, `date()`, `strtotime()`, atau format Carbon langsung di controller/service baru kecuali berada di dalam helper atau ada alasan teknis yang disetujui.

## 3. Form Request dan validasi

Endpoint baru yang menerima input wajib menggunakan Form Request. Controller meneruskan hanya data hasil:

```php
$request->validated()
```

Form Request menangani validasi struktur input dan authorization sederhana. Form Request tidak boleh melakukan insert, update, transaction, audit, business process lintas tabel, atau integrasi eksternal. Business rule kompleks berada di service.

## 4. Service dan business logic

Service adalah pusat application/business flow dan dinamai berdasarkan use case atau feature, bukan sekadar tabel. Service:

- menerima data tervalidasi, bukan object `Request`;
- menerapkan business rule;
- mengatur urutan proses;
- mengoordinasikan model/service lain;
- menggunakan transaction jika diperlukan;
- memanggil authorization service dan audit service sesuai kebutuhan;
- mengembalikan model, collection, DTO, atau data hasil proses.

Service tidak boleh mengembalikan HTTP response, menentukan status HTTP, membuat format JSON/pagination, redirect, atau bergantung langsung pada frontend.

## 5. Helper yang disepakati

Untuk saat ini hanya direncanakan satu helper baru:

```text
app/Helpers/CurrentUserHelper.php
```

Helper ini belum dibuat. Fungsinya nanti hanya mengambil identitas user aktif secara konsisten, misalnya `Id_Users`, `UserID_Web`, `Kode_Karyawan`, `Kode_Perusahaan`, `Is_System_Admin`, dan actor.

Tidak dibuat helper authorization baru karena project sudah memiliki middleware/service/policy authorization. Pagination response sudah ditangani `ResponseHelper`. Audit harus menggunakan service/tabel audit database yang sudah ada setelah struktur existing diperiksa.


## 6. Kontrak response API dan error

Endpoint API baru harus menjadi kontrak aman untuk frontend. Bentuk umum response error:

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

- `error_code` stabil dan tidak boleh diganti hanya karena wording message berubah;
- `message` tidak boleh berisi stack trace, SQL, nama class, path credential, atau detail internal;
- `request_id` harus dapat menghubungkan response dengan log request/exception bila infrastructure mendukungnya;
- `errors` digunakan untuk validation errors per field;
- status HTTP harus merepresentasikan kategori error: 401 session/authentication, 403 authorization, 404 not found, 409 conflict, 422 validation, 429 rate limit, dan 5xx server/transient failure;
- exception tidak boleh ditelan tanpa logging dan classification yang jelas;
- response API baru tetap dibuat melalui `App\Helpers\ResponseHelper`;
- response legacy tidak diubah otomatis hanya untuk menyamakan format.

## 7. Logging dan observability

Kode baru harus mencatat event penting pada channel yang sesuai dengan context terstruktur. Minimal context yang relevan dapat mencakup:

```text
request_id, actor, module, feature, operation,
entity/id yang relevan, status, error_code, attempt, dan duration
```

Aturan logging:

- log technical exception lengkap di backend, bukan di frontend;
- jangan mencatat password, token, credential, secret, atau payload sensitif;
- gunakan level `info` untuk lifecycle penting, `warning` untuk kondisi recoverable/degraded, dan `error` untuk failure yang memerlukan investigasi;
- error yang dikembalikan sebagai retryable harus dapat dibedakan dari permanent failure;
- audit bisnis/authorization tidak digantikan hanya oleh application log bila kebutuhan audit database sudah disepakati;
- channel dan nama event harus stabil agar dapat dicari dan dimonitor;
- logging tidak boleh mengubah hasil business operation kecuali kegagalan audit memang secara eksplisit menjadi hard requirement.

## 8. Authorization, audit, dan legacy boundary

- Authorization menggunakan middleware, service, policy, dan registry existing yang sesuai.
- Menu visibility bukan pengganti authorization backend.
- Akses page dan action harus dibedakan bila feature memiliki capability terpisah.
- Mutasi authorization dan action governance wajib memiliki audit context sesuai mekanisme yang disetujui.
- Legacy behavior dipertahankan untuk path legacy; strict/fail-closed behavior diterapkan secara opt-in pada feature governed/new.
- Jangan membuat sistem authorization baru dari nol tanpa scope dan persetujuan.

## 9. Definition of Done backend baru

Sebelum menyatakan backend feature baru selesai:

- [ ] Module, submodule, feature, dan layer sudah dicatat.
- [ ] Controller tetap tipis dan tidak menerima business logic.
- [ ] Input memakai Form Request dan service menerima data tervalidasi.
- [ ] Response API memakai `ResponseHelper`.
- [ ] Error memakai `error_code`, safe message, dan request correlation bila tersedia.
- [ ] Technical exception tercatat pada log dengan context tanpa secret.
- [ ] Transaction boundary, after-commit, Outbox, idempotency, dan retry sudah dinilai sesuai kebutuhan.
- [ ] Authorization dan audit sudah dinilai.
- [ ] Test focused dan regression yang relevan lulus.
- [ ] DDL/DML, bila ada, mengikuti safety gate dan dokumentasi change ID.
- [ ] Legacy di luar scope tidak ikut direfactor.
