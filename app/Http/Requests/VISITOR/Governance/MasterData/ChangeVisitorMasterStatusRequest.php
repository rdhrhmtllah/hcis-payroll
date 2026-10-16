<?php

namespace App\Http\Requests\VISITOR\Governance\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class ChangeVisitorMasterStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['required', 'string', 'in:Y,T']];
    }
}
