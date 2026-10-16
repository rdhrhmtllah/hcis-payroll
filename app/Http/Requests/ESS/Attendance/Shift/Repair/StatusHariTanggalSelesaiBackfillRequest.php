<?php

namespace App\Http\Requests\ESS\Attendance\Shift\Repair;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StatusHariTanggalSelesaiBackfillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('dry_run')) {
            $this->merge(['dry_run' => true]);
        }
    }

    public function rules(): array
    {
        $apply = ! $this->boolean('dry_run', true);

        return [
            'target_database' => ['required', 'string', 'max:128'],
            'target_environment' => ['required', Rule::in(['development', 'staging', 'production', 'unknown'])],
            'dry_run' => ['required', 'boolean'],
            'kode_perusahaan' => ['nullable', 'string', 'max:10'],
            'tanggal_mulai_dari' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_mulai_sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai_dari'],
            'batch_size' => ['nullable', 'integer', 'min:1', 'max:500'],
            'plan_hash' => [$apply ? 'required' : 'nullable', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'expected_auto_fixable_count' => [$apply ? 'required' : 'nullable', 'integer', 'min:0', 'max:500'],
            'actor_id' => [$apply ? 'required' : 'nullable', 'integer', 'min:1'],
            'confirmation' => [$apply ? 'required' : 'nullable', 'string', Rule::in(['APPLY_STATUS_HARI_BACKFILL'])],
        ];
    }
}
