<?php

namespace App\Http\Requests\VISITOR\Workflow\Reporting\Export;

use Illuminate\Foundation\Http\FormRequest;

class SaveVisitorReportTemplateRequest extends FormRequest
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
            'definisi.dataset' => ['required', 'string', 'in:tamu,permit'],
            'definisi.mode' => ['required', 'string', 'in:ringkasan,rinci'],
            'tingkat_bagi' => ['nullable', 'string', 'in:PRIBADI,DIBAGIKAN,ORGANISASI'],
        ];
    }
}
