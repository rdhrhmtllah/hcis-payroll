<?php

namespace App\Http\Requests\VISITOR\Guest\Visit;

use App\Services\VISITOR\Guest\Visit\AturanFormTamu;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Aturan masukan registrasi tamu lewat layar HCIS.
 *
 * Aturannya milik AturanFormTamu (satu sumber untuk layar Regular, layar B2B,
 * dan API mitra). Tipe diambil dari rute — `visitor.tipe-tamu:<TIPE>` — dan
 * TIDAK dari isian `tipe_tamu`: halaman Regular tidak boleh bisa dipakai
 * mengajukan tamu B2B hanya dengan menambah satu field.
 */
class SimpanTamuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return AturanFormTamu::aturan(TipeTamu::dariPermintaan($this));
    }

    public function attributes(): array
    {
        return AturanFormTamu::atribut();
    }
}
