<?php

namespace App\Http\Requests\VISITOR\Governance\MasterData;

use App\Services\VISITOR\Governance\MasterData\VisitorMasterDataCatalog;
use Illuminate\Validation\Rule;

/**
 * Parameter export master data Visitor.
 *
 * Saringannya persis milik layar (`ListVisitorMasterDataRequest`) ditambah
 * `master`: tab mana yang sedang terbuka saat tombol ditekan. Hanya lembar itu
 * yang mengikuti saringan layar; lembar lain diekspor utuh.
 *
 * `page`/`limit` sengaja TIDAK diterima — export bukan halaman, dan menerima
 * keduanya diam-diam akan memotong berkas sebesar satu halaman tabel.
 */
class ExportVisitorMasterDataRequest extends ListVisitorMasterDataRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['page'], $rules['limit']);

        return $rules + [
            'master' => ['nullable', Rule::in(VisitorMasterDataCatalog::KEYS)],
        ];
    }
}
