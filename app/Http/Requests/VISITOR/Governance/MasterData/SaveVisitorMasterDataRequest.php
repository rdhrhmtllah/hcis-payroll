<?php

namespace App\Http\Requests\VISITOR\Governance\MasterData;

use App\Services\VISITOR\Governance\MasterData\VisitorMasterDataCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveVisitorMasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $definition = VisitorMasterDataCatalog::get((string) $this->route('master'));
        $rules = [];

        foreach ($definition['fields'] as $field) {
            $fieldRules = [$field['required'] ? 'required' : 'nullable'];

            if ($field['type'] === 'number' || $field['type'] === 'relation') {
                $fieldRules[] = 'integer';
                if (isset($field['min'])) {
                    $fieldRules[] = 'min:'.$field['min'];
                }
                if (isset($field['max'])) {
                    $fieldRules[] = 'max:'.$field['max'];
                }
            } else {
                $fieldRules[] = 'string';
                if (isset($field['max'])) {
                    $fieldRules[] = 'max:'.$field['max'];
                }
            }

            if ($field['type'] === 'select') {
                $fieldRules[] = Rule::in(array_keys($field['options']));
            }

            if ($field['type'] === 'color') {
                $fieldRules[] = 'regex:/^#[0-9A-Fa-f]{6}$/';
            }

            // Kontak bantuan permit: minimal salah satu dari HP atau email, dan
            // keduanya berbentuk sah supaya tautan WhatsApp/mailto di layar jalan.
            if ($field['key'] === 'No_HP') {
                $fieldRules[] = 'required_without:Email';
                $fieldRules[] = 'regex:/^\+?[0-9][0-9 ()\-]{7,19}$/';
            }

            if ($field['key'] === 'Email') {
                $fieldRules[] = 'email:rfc';
            }

            if ($field['key'] === 'Kode_Plat') {
                $fieldRules[] = 'regex:/^[A-Za-z]{1,5}$/';
            }

            if ($field['type'] === 'relation') {
                $relation = VisitorMasterDataCatalog::get($field['relation']);
                $fieldRules[] = Rule::exists($relation['table'], $relation['id']);
            }

            $rules[$field['key']] = $fieldRules;
        }

        return $rules;
    }
}
