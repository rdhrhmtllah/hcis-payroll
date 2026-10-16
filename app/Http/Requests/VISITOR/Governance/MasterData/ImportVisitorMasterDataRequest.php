<?php

namespace App\Http\Requests\VISITOR\Governance\MasterData;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Berkas import master data Visitor.
 *
 * `sidik` hanya dikirim pintu eksekusi: isinya sidik jari berkas yang
 * ditampilkan pada pratinjau. Tanpa itu, yang ditulis bisa saja berkas lain
 * dari yang barusan dibaca admin di layar — dan laporan dry run-nya berubah
 * menjadi janji kosong.
 */
class ImportVisitorMasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'berkas' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'sidik' => ['nullable', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'berkas.required' => 'Pilih berkas Excel yang akan diimport.',
            'berkas.mimes' => 'Berkas harus berformat .xlsx.',
            'berkas.max' => 'Ukuran berkas maksimal 10 MB.',
        ];
    }
}
