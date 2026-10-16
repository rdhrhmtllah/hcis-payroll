<?php

namespace App\Http\Requests\VISITOR\Permit\VehiclePermit;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload pengajuan permit dari aplikasi mitra B2B.
 *
 * Tidak ada kode karyawan di sini, dan itu disengaja: PIC melekat pada
 * pendaftaran aplikasi. Kalau boleh dikirim pemanggil, siapa pun yang memegang
 * kunci API bisa menggantungkan tanggung jawab izin gerbang pada karyawan mana
 * pun yang ia tebak kodenya.
 */
class AjukanPermitApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi sepenuhnya di tangan hris.api.guard (HMAC + nonce +
        // whitelist endpoint per aplikasi); tidak ada sesi pengguna di sini.
        return true;
    }

    public function rules(): array
    {
        return [
            'Idempotency-Key' => ['nullable', 'string', 'max:100'],
            'nama_tamu' => ['required', 'string', 'max:150'],
            'instansi' => ['required', 'string', 'max:150'],
            'no_identitas' => ['nullable', 'string', 'max:100'],
            'jenis_identitas' => ['nullable', 'string', 'max:20'],
            'jenis_kendaraan' => ['nullable', 'string', 'max:50'],
            'no_plat_kendaraan' => ['nullable', 'string', 'max:50'],
            'plat_prefix' => ['nullable', 'string', 'max:5'],
            'plat_angka' => ['nullable', 'string', 'max:10'],
            'plat_suffix' => ['nullable', 'string', 'max:10'],
            'keperluan' => ['required', 'string', 'max:250'],
            'referensi_mitra' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'Idempotency-Key' => trim((string) $this->header('Idempotency-Key')),
        ]);
    }

    public function messages(): array
    {
        return [];
    }
}
