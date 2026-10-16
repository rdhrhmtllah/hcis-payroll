<?php

namespace App\Http\Requests\KPI\PerformanceAppraisal\Export;

use Illuminate\Foundation\Http\FormRequest;

class SavePaReportTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'definisi' => ['required', 'array'],
            'definisi.dataset' => ['required', 'string', 'in:hasil,penugasan,kompetensi,peserta'],
            'definisi.mode' => ['required', 'string', 'in:ringkasan,rinci'],
            'tingkat_bagi' => ['nullable', 'string', 'in:PRIBADI,DIBAGIKAN,ORGANISASI'],
        ];
    }
}
