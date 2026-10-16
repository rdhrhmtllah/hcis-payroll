<?php

namespace App\Http\Requests\VISITOR\Permit\VehiclePermit;

use Illuminate\Foundation\Http\FormRequest;

class SetPermitValidityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_durasi_permit' => ['required', 'integer'],
        ];
    }
}
