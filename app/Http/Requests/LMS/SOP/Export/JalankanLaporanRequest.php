<?php

namespace App\Http\Requests\LMS\SOP\Export;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Perhatikan: TIDAK ADA aturan untuk `module`.
 *
 * Modul datang dari route dan dipatri di controller. Menerimanya dari badan
 * permintaan berarti satu query yang lupa menyaring langsung membocorkan
 * template modul lain.
 */
class JalankanLaporanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format' => ['nullable', 'string', 'in:csv,xlsx,pdf'],
            'custom_name' => ['nullable', 'string', 'max:120'],
            'template_kode' => ['required_without:definisi', 'nullable', 'string', 'max:60'],
            'definisi' => ['required_without:template_kode', 'nullable', 'array'],
            'parameter' => ['nullable', 'array'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'search' => ['nullable', 'string', 'max:100'],
            // Total baris hanya dihitung saat definisi/saringan berubah.
            // Menghitungnya ulang tiap ganti halaman berarti dua kali pindai
            // penuh untuk angka yang tidak berubah.
            'hitung_total' => ['nullable', 'boolean'],
            'sort_key' => ['nullable', 'string', 'max:60'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
