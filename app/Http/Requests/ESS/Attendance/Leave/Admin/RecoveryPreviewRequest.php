<?php

namespace App\Http\Requests\ESS\Attendance\Leave\Admin;

use App\Services\ESS\Attendance\Leave\Processing\CutiProcessBatchService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecoveryPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Parse string mentah (comma/space-separated) menjadi array SEKALI di sini,
     * supaya backend & frontend tidak duplikasi parser.
     */
    protected function prepareForValidation(): void
    {
        $months = $this->input('months');
        if (is_string($months)) {
            $months = $this->splitInput($months);
        } elseif (is_array($months)) {
            $months = $this->normalizeArray($months);
        }

        $monthsText = $this->input('months_text');
        if (is_string($monthsText) && $monthsText !== '') {
            $months = array_values(array_unique(array_merge((array) $months, $this->splitInput($monthsText))));
        }

        $kode = $this->input('kode_karyawan');
        if (is_string($kode)) {
            $kode = $this->splitInput($kode, true);
        } elseif (is_array($kode)) {
            $kode = $this->normalizeArray($kode, true);
        }

        $kodeText = $this->input('kode_text');
        if (is_string($kodeText) && $kodeText !== '') {
            $kode = array_values(array_unique(array_merge((array) $kode, $this->splitInput($kodeText, true))));
        }

        $employeeScope = (string) $this->input('employee_scope', 'selected');
        if ($employeeScope === 'all') {
            $kode = DB::table('Karyawan')
                ->where('Aktif', 'Y')
                ->whereNull('Tanggal_Resign')
                ->orderBy('Kode_Karyawan')
                ->pluck('Kode_Karyawan')
                ->map(fn ($k) => strtoupper(trim((string) $k)))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $dueDates = $this->input('due_dates');
        if (is_string($dueDates)) {
            $dueDates = $this->splitInput($dueDates);
        } elseif (is_array($dueDates)) {
            $dueDates = $this->normalizeArray($dueDates);
        }

        $dueDate = $this->input('due_date');
        if (is_string($dueDate) && $dueDate !== '') {
            $dueDates = array_values(array_unique(array_merge((array) $dueDates, [$dueDate])));
        }

        $this->merge([
            'months' => $months,
            'kode_karyawan' => $kode,
            'employee_scope' => $employeeScope,
            'due_dates' => $dueDates,
            'due_date' => $dueDates[0] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'source_type' => ['required', Rule::in(CutiProcessBatchService::recoverableSourceTypes())],
            'employee_scope' => ['required', Rule::in(['selected', 'all'])],
            'months' => ['required', 'array', 'min:1', 'max:24'],
            'months.*' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'kode_karyawan' => [Rule::requiredIf($this->input('employee_scope', 'selected') !== 'all'), 'array', 'max:500'],
            'kode_karyawan.*' => ['required', 'string', 'max:50'],
            'due_dates' => ['nullable', 'array', 'max:24'],
            'due_dates.*' => ['required', 'string', 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'keterangan' => ['nullable', 'string', 'max:500'],
            'preview_token' => ['nullable', 'string', 'max:128'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $todayMonth = Carbon::now()->startOfMonth();
            foreach ((array) $this->input('months', []) as $month) {
                if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $month)) {
                    continue;
                }
                $periodStart = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfMonth();
                if ($periodStart->gt($todayMonth)) {
                    $validator
                        ->errors()
                        ->add('months', "Periode {$month} berada di masa depan dan tidak boleh diproses.");
                }
            }

            $dueDates = (array) $this->input('due_dates', []);
            foreach ($dueDates as $dueDate) {
                if (! $dueDate || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dueDate)) {
                    continue;
                }

                $due = Carbon::createFromFormat('Y-m-d', $dueDate)->startOfDay();
                if ($due->gt(Carbon::now()->startOfDay())) {
                    $validator
                        ->errors()
                        ->add('due_dates', "Tanggal due {$dueDate} berada di masa depan dan tidak boleh diproses.");
                }

                $months = (array) $this->input('months', []);
                if (! in_array($due->format('Y-m'), $months, true)) {
                    $validator
                        ->errors()
                        ->add('due_dates', "Tanggal due {$dueDate} harus berada di salah satu bulan recovery.");
                }
            }
        });
    }

    /**
     * Return validated payload yang sudah dinormalisasi untuk dipakai service.
     */
    public function validatedRecovery(): array
    {
        $data = $this->validated();

        return [
            'source_type' => $data['source_type'],
            'employee_scope' => $data['employee_scope'] ?? 'selected',
            'months' => collect($data['months'])->unique()->values()->all(),
            'kode_karyawan' => collect($data['kode_karyawan'])
                ->map(fn ($k) => strtoupper(trim((string) $k)))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'due_dates' => collect($data['due_dates'] ?? [])
                ->map(fn ($d) => trim((string) $d))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'due_date' => $data['due_date'] ?? ($data['due_dates'][0] ?? null),
            'keterangan' => $data['keterangan'] ?? null,
        ];
    }

    private function splitInput(string $value, bool $upper = false): array
    {
        return collect(preg_split('/[,\s]+/', $value))
            ->map(fn ($v) => $upper ? strtoupper(trim((string) $v)) : trim((string) $v))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeArray(array $items, bool $upper = false): array
    {
        return collect($items)
            ->map(fn ($v) => $upper ? strtoupper(trim((string) $v)) : trim((string) $v))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
