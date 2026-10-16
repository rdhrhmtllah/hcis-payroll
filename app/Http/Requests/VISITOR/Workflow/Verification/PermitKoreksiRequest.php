<?php

namespace App\Http\Requests\VISITOR\Workflow\Verification;

use Illuminate\Foundation\Http\FormRequest;

class PermitKoreksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_kategori_permit' => ['required', 'integer'],
            'no_plat' => ['required', 'string', 'max:30'],
            'id_vehicle_model' => ['required', 'integer'],
            'id_vehicle_color' => ['required', 'integer'],
            'tahun_kendaraan' => ['required', 'integer', 'min:1900', 'max:'.((int) date('Y') + 1)],
            'keterangan_pengajuan' => ['nullable', 'string', 'max:500'],
            'alasan' => ['required', 'string', 'max:500'],
            // Isi form saat dialog dibuka; dasar deteksi koreksi rekan yang masuk lebih dulu.
            'nilai_awal' => ['nullable', 'array'],
            'nilai_awal.id_kategori_permit' => ['nullable'],
            'nilai_awal.no_plat' => ['nullable', 'string', 'max:30'],
            'nilai_awal.id_vehicle_model' => ['nullable'],
            'nilai_awal.id_vehicle_color' => ['nullable'],
            'nilai_awal.tahun_kendaraan' => ['nullable'],
            'nilai_awal.keterangan_pengajuan' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['alasan' => 'alasan koreksi', 'no_plat' => 'nomor plat', 'id_kategori_permit' => 'jenis permit'];
    }
}
