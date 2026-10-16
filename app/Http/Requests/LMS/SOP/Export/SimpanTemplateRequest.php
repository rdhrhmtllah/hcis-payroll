<?php

namespace App\Http\Requests\LMS\SOP\Export;

use Illuminate\Foundation\Http\FormRequest;

class SimpanTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi halaman ditegakkan middleware check.jabatan di route.
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'definisi' => ['required', 'array'],
            'definisi.dataset' => ['required', 'string', 'max:40'],
            'definisi.mode' => ['required', 'string', 'in:ringkasan,rinci'],
            'tingkat_bagi' => ['nullable', 'string', 'in:PRIBADI,DIBAGIKAN,ORGANISASI'],
        ];
    }
}
