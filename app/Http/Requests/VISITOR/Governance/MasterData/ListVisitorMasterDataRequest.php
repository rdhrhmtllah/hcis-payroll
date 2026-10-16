<?php

namespace App\Http\Requests\VISITOR\Governance\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListVisitorMasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', Rule::in([10, 24, 25, 50, 100])],
            'search' => ['nullable', 'string', 'max:100'],
            'sort_by' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_]+$/'],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            // 'ALL' = tampilkan aktif DAN nonaktif sekaligus. Kosong berarti
            // aktif saja - lihat applyListFilters().
            'Flag_Aktif' => ['nullable', Rule::in(['Y', 'T', 'ALL'])],
            'Usage_Status' => ['nullable', Rule::in(['USED', 'UNUSED'])],
            'Akses' => ['nullable', Rule::in(['ALL', 'PERMIT', 'TAMU'])],
            'Id_Master_Tipe' => ['nullable', 'integer', 'min:1'],
            'Wajib_Diisi' => ['nullable', Rule::in(['Y', 'T'])],
            'Target_Scope' => ['nullable', Rule::in(['GLOBAL', 'TARGETED'])],
            'Target_Id_Kategori_Permit' => ['nullable', 'integer', 'min:1'],
            'Target_Id_Tipe_Kendaraan' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
