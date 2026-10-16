<?php

namespace App\Http\Requests\VISITOR\Guest\Visit;

use Illuminate\Foundation\Http\FormRequest;

class UbahTamuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal_kunjungan' => ['nullable', 'date'],
            'waktu_mulai_kunjungan' => ['nullable', 'date'],
            'waktu_selesai_kunjungan' => ['nullable', 'date', 'after:waktu_mulai_kunjungan'],
            'daftar_anggota' => ['sometimes', 'array'],
            'daftar_anggota.*.nama' => ['required', 'string', 'max:150'],
            'daftar_anggota.*.jenis_identitas' => ['nullable', 'string', 'max:20'],
            'daftar_anggota.*.no_identitas' => ['nullable', 'string', 'max:100'],
        ];
    }
}
