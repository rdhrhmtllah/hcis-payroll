<?php

namespace App\Http\Requests\SYSTEM\TaskProcessing;

use Illuminate\Foundation\Http\FormRequest;

class DispatchTaskOutboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'lease_minutes' => ['sometimes', 'integer', 'min:1', 'max:60'],
        ];
    }
}
