<?php

namespace App\Http\Requests\VISITOR\Workflow\Approval;

use Illuminate\Foundation\Http\FormRequest;

class VisitorRejectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'max:500'],
        ];
    }
}
