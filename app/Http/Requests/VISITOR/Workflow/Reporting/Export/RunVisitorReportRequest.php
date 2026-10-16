<?php

namespace App\Http\Requests\VISITOR\Workflow\Reporting\Export;

use Illuminate\Foundation\Http\FormRequest;

class RunVisitorReportRequest extends FormRequest
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
            'hitung_total' => ['nullable', 'boolean'],
            'sort_key' => ['nullable', 'string', 'max:60'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
