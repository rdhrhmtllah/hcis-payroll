# Penyesuaian API Permit B2B: Idempotensi Internal & OpenAPI Completeness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyederhanakan pengajuan API Permit B2B: header `Idempotency-Key` menjadi opsional (diasumsikan otomatis dari `referensi_mitra` / UUID acak), menghapus kewajiban input `tanggal_berlaku` dari payload B2B (masa berlaku ditentukan Approver), dan melengkapi dokumentasi OpenAPI dengan contoh nilai default yang siap di-try-out.

**Architecture:** 
1. `AjukanPermitApiRequest` menghapus aturan `tanggal_berlaku` dan menjadikan `Idempotency-Key` header opsional.
2. `PermitTamuApiController::ajukan()` mengekstrak header `Idempotency-Key`, atau membuat idempoten acak/dari `referensi_mitra` jika header tidak dikirim.
3. `resources/openapi/hcis-third-party.yaml` diperbarui dengan contoh payload lengkap yang siap di-try-out pada Swagger UI.

**Tech Stack:** Laravel, PHPUnit, OpenAPI 3.1 YAML.

---

## Global Constraints

- **Aturan Proyek:** Tulis seluruh test terlebih dahulu. Tahan eksekusi test/lint/build/penjaga sampai pengguna memberikan instruksi/ACC eksplisit. Jangan push atau buka PR.
- **File Yang Disentuh:**
  - `app/Http/Requests/VISITOR/Permit/VehiclePermit/AjukanPermitApiRequest.php`
  - `app/Http/Controllers/VISITOR/Permit/VehiclePermit/PermitTamuApiController.php`
  - `resources/openapi/hcis-third-party.yaml`
  - `tests/Feature/VISITOR/Permit/VehiclePermit/PermitTamuApiAjukanTest.php`
  - `tests/Feature/VISITOR/Permit/VehiclePermit/PermitTamuApiRegistrasiTest.php`
  - `tests/Feature/VISITOR/Permit/VehiclePermit/PermitTamuApiStatusBatalTest.php`

---

### Task 1: Update Tests — Header Idempotency-Key Opsional & Hapus Tanggal Berlaku B2B

**Files:**
- Modify: `tests/Feature/VISITOR/Permit/VehiclePermit/PermitTamuApiAjukanTest.php`
- Modify: `tests/Feature/VISITOR/Permit/VehiclePermit/PermitTamuApiRegistrasiTest.php`

**Interfaces:**
- Consumes: Kontrak API Permit B2B.
- Produces: Test suite yang menegaskan request tanpa header `Idempotency-Key` dan tanpa `tanggal_berlaku` diterima 201.

- [ ] **Step 1: Update `PermitTamuApiAjukanTest.php`**

Ubah test & helper `payload()`:
- `payload()` tidak lagi mengirim `tanggal_berlaku`.
- Tambahkan test `test_pengajuan_tanpa_header_idempotency_key_tetap_diterima_dan_idempoten_lewat_referensi_mitra()`:
```php
public function test_pengajuan_tanpa_header_idempotency_key_tetap_diterima_dan_idempoten_lewat_referensi_mitra(): void
{
    $ref = 'REF-B2B-'.Str::random(10);
    $pertama = $this->postJson(self::URL, array_merge($this->payload(), ['referensi_mitra' => $ref]))
        ->assertStatus(201)
        ->json('result.no_transaksi');

    $kedua = $this->postJson(self::URL, array_merge($this->payload(), ['referensi_mitra' => $ref]))
        ->assertStatus(200);

    $this->assertSame($pertama, $kedua->json('result.no_transaksi'));
    $this->assertTrue($kedua->json('result.idempotent_replay'));
}
```

- [ ] **Step 2: Update `PermitTamuApiRegistrasiTest.php`**

Ubah `test_durasi_satu_hari_disebut_di_dokumentasi()` menjadi menegaskan bahwa `Idempotency-Key` opsional dan `tanggal_berlaku` tidak lagi disyaratkan pada request body.

---

### Task 2: Backend — `AjukanPermitApiRequest` & `PermitTamuApiController`

**Files:**
- Modify: `app/Http/Requests/VISITOR/Permit/VehiclePermit/AjukanPermitApiRequest.php`
- Modify: `app/Http/Controllers/VISITOR/Permit/VehiclePermit/PermitTamuApiController.php`

**Interfaces:**
- Consumes: Request `POST /api/v1/hcis/visitor/permit-tamu`.
- Produces: Header `Idempotency-Key` opsional; permit `PENDING` tersimpan tanpa tanggal berlaku; `referensi_mitra` dipakai sebagai fallback kunci idempoten.

- [ ] **Step 1: Update `AjukanPermitApiRequest.php`**

Ubah `rules()`:
```php
return [
    'Idempotency-Key' => ['nullable', 'string', 'max:100'],
    'nama_tamu' => ['required', 'string', 'max:150'],
    'instansi' => ['required', 'string', 'max:150'],
    'no_identitas' => ['nullable', 'string', 'max:100'],
    'jenis_identitas' => ['nullable', 'string', 'max:20'],
    'jenis_kendaraan' => ['nullable', 'string', 'max:50'],
    'plat_prefix' => ['nullable', 'string', 'max:5'],
    'plat_angka' => ['nullable', 'string', 'max:10'],
    'plat_suffix' => ['nullable', 'string', 'max:10'],
    'keperluan' => ['required', 'string', 'max:250'],
    'referensi_mitra' => ['nullable', 'string', 'max:100'],
];
```

- [ ] **Step 2: Update `PermitTamuApiController::ajukan()`**

Di `ajukan()`:
```php
$kunciHeader = trim((string) $request->header('Idempotency-Key'));
$referensiMitra = trim((string) $request->input('referensi_mitra'));
$kunci = $kunciHeader !== '' 
    ? $kunciHeader 
    : ($referensiMitra !== '' ? 'REF-'.$referensiMitra : 'API-'.Str::uuid());
```

Di insert detail:
- `'Waktu_Mulai_Berlaku' => null,`
- `'Waktu_Berakhir' => null,`
- `'Tanggal_Mulai_Berlaku' => null,`
- `'Tanggal_Kadaluarsa' => null,`
- `'Snapshot_Nama_Durasi' => null,`

Di response 201 `sukses()`:
- Kembalikan `status => 'PENDING'`, `no_transaksi`, `referensi_mitra`.

---

### Task 3: OpenAPI Documentation — Try It Out Default Examples

**Files:**
- Modify: `resources/openapi/hcis-third-party.yaml`

**Interfaces:**
- Consumes: OpenAPI 3.1 spec.
- Produces: Dokumentasi OpenAPI lengkap dengan `example` nilai default pada `PermitTamuRequest` dan header opsional `Idempotency-Key`.

- [ ] **Step 1: Perbarui `/api/v1/hcis/visitor/permit-tamu` di `hcis-third-party.yaml`**

Ubah parameter `Idempotency-Key` di header menjadi `required: false`.
Ubah `requestBody` schema `PermitTamuRequest`:
- `required: [nama_tamu, instansi, keperluan]` (hapus `waktu_mulai`, `waktu_selesai`, `tanggal_berlaku`).
- Tambahkan contoh `example` pada seluruh properti:
```yaml
    PermitTamuRequest:
      type: object
      required: [nama_tamu, instansi, keperluan]
      example:
        nama_tamu: Rudi Mitra
        instansi: PT Mitra Sejahtera
        no_identitas: '3201234567890001'
        jenis_identitas: KTP
        jenis_kendaraan: Mobil
        plat_prefix: BG
        plat_angka: '1234'
        plat_suffix: MT
        keperluan: Pengiriman material ke gudang utama
        referensi_mitra: REF-20260912-001
      properties:
        nama_tamu: { type: string, maxLength: 150, example: Rudi Mitra }
        instansi: { type: string, maxLength: 150, example: PT Mitra Sejahtera }
        no_identitas: { type: string, maxLength: 100, nullable: true, example: '3201234567890001' }
        jenis_identitas: { type: string, maxLength: 20, nullable: true, example: KTP }
        jenis_kendaraan:
          type: string
          maxLength: 50
          nullable: true
          description: Kosongkan bila tamu datang tanpa kendaraan; permitnya tetap terbit.
          example: Mobil
        plat_prefix: { type: string, maxLength: 5, nullable: true, example: BG }
        plat_angka: { type: string, maxLength: 10, nullable: true, example: '1234' }
        plat_suffix: { type: string, maxLength: 10, nullable: true, example: MT }
        keperluan: { type: string, maxLength: 250, example: Pengiriman material }
        referensi_mitra:
          type: string
          maxLength: 100
          nullable: true
          description: Id/Nomor referensi registrasi tamu di sistem Anda. Dipakai sebagai kunci idempoten jika header Idempotency-Key tidak dikirim.
          example: REF-20260912-001
```

---

## Execution Handoff

Setelah plan disimpan, tawarkan dua opsi eksekusi:
1. **Subagent-Driven (recommended)**
2. **Inline Execution**
