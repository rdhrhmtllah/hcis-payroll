<?php

namespace App\Http\Controllers\LMS\SOP\Governance;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\SOP\Support\SopLog;
use App\Services\LMS\SOP\Support\SopSetting;
use App\Services\LMS\SOP\Support\SopTipeItemAktif;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Set Up System — modul SOP.
 *
 * Memuat dua hal yang sebelumnya menumpang di halaman Kebijakan & Otomasi:
 * kebijakan global (guardrail kepatuhan + tuning mesin) dan status scheduler
 * terjadwal. Pemisahannya per-halaman, bukan per-grup fitur: halaman ini tetap
 * milik modul SOP (Id_Grup_Fitur = 3 / LMS), hanya duduk di header sidebar
 * sendiri. Gerbangnya `menuSopSetupSystemPage`.
 *
 * Aturan penugasan dan riwayat eksekusi TIDAK ada di sini — keduanya tetap di
 * SopAdminKebijakanController.
 */
class SopAdminSetupSystemController extends Controller
{
    /**
     * Kontrak setelan global SOP. Dipindahkan utuh dari SopAdminKebijakanController
     * agar tak ada dua definisi yang bisa berbeda diam-diam.
     */
    private const SETTINGS = [
        'reader_dwell_min_dtk' => ['type' => 'int', 'min' => 1, 'max' => 120, 'default' => 3],
        'video_ambang_persen' => ['type' => 'int', 'min' => 1, 'max' => 100, 'default' => 90],
        'default_reminder_hari' => ['type' => 'int', 'min' => 1, 'max' => 365, 'default' => 3],
        'mover_auto_assign' => ['type' => 'bool', 'default' => 'Y'],
        'mover_auto_release' => ['type' => 'bool', 'default' => 'T'],
        // Ambang jumlah target sebelum penjadwalan dipindah ke jalur asinkron.
        // Sebelumnya nilainya ditampilkan settings() tetapi tak ada di daftar ini,
        // sehingga updateSettings() menolaknya — tampil di UI namun mustahil diubah.
        'penjadwalan_async_ambang' => ['type' => 'int', 'min' => 1, 'max' => 5000, 'default' => 30],
        /* Jumlah karyawan per transaksi rekonsiliasi. Batas atasnya sengaja
           rendah: angka di atas ukuran paket terbesar membuat pemotongannya tak
           pernah terjadi, dan satu transaksi besar menahan lock N_SOP_Progress
           cukup lama untuk menggagalkan halaman karyawan. */
        'sop_reconcile_chunk' => ['type' => 'int', 'min' => 1, 'max' => 200, 'default' => 10],
    ];

    /**
     * Kontrak setelan LENGKAP: yang tetap di atas, ditambah satu sakelar per
     * tipe item.
     *
     * Sakelar tipe TIDAK diketik ulang di sini. Bawaannya dibaca dari
     * SopTipeItemAktif — kelas yang sama yang dipakai seluruh penjaga di
     * server. Dua daftar untuk satu keputusan pasti menyimpang, dan yang
     * menyimpang di sini berarti halaman setelan menampilkan "aktif" untuk
     * tipe yang penjaganya justru menolak.
     */
    private static function kontrakSetelan(): array
    {
        $settings = self::SETTINGS;
        foreach (SopTipeItemAktif::bawaan() as $tipe => $hidup) {
            $settings[SopTipeItemAktif::kunci($tipe)] = [
                'type' => 'bool',
                'default' => $hidup ? 'Y' : 'T',
            ];
        }

        return $settings;
    }

    /**
     * Command scheduler milik SOP di N_HRIS_Scheduler_Configs.
     *
     * Tabel itu milik platform (18 baris lintas modul: KPI, cuti, absensi,
     * visitor, talent-pool). Halaman ini hanya menampilkan baris SOP; sisanya
     * bukan wewenang modul ini.
     */
    private const SCHEDULER_COMMANDS = ['sop:rule-scan', 'sop:reminder-scan'];

    public function index()
    {
        return inertia('LMS/SOP/Governance/Admin/SopSetupSystem');
    }

    /**
     * Satu panggilan untuk seluruh isi halaman: setelan + status scheduler.
     *
     * schedulerHealth() sebelumnya menempel di /summary milik halaman Kebijakan.
     * Karena tab yang memakainya pindah ke sini, sumbernya ikut pindah agar
     * halaman lama tak lagi membawa data yang tak dipakainya.
     */
    public function settings()
    {
        try {
            return ResponseHelper::success([
                'settings' => $this->settingValues(),
                'schedulers' => $this->schedulerHealth(),
            ]);
        } catch (\Throwable $e) {
            return $this->serverError('settings', $e);
        }
    }

    public function updateSettings(Request $request)
    {
        try {
            $input = $request->only(array_keys(self::kontrakSetelan()));
            if (! $input) {
                return ResponseHelper::error('Tidak ada pengaturan yang dikirim.', 422);
            }

            $changes = [];
            DB::transaction(function () use ($input, &$changes) {
                foreach ($input as $key => $value) {
                    $definition = self::kontrakSetelan()[$key];
                    if ($definition['type'] === 'int') {
                        $value = filter_var($value, FILTER_VALIDATE_INT);
                        if ($value === false || $value < $definition['min'] || $value > $definition['max']) {
                            throw ValidationException::withMessages([$key => "Nilai harus {$definition['min']}-{$definition['max']}."]);
                        }
                        $stored = (string) $value;
                    } else {
                        $stored = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Y' : 'T';
                    }

                    $before = DB::table('N_SOP_Setting')->where('Kode_Setting', $key)->value('Nilai');
                    DB::table('N_SOP_Setting')->updateOrInsert(
                        ['Kode_Setting' => $key],
                        ['Nilai' => $stored, 'Updated_By' => Auth::id(), 'Updated_At' => now()],
                    );
                    $changes[$key] = ['before' => $before, 'after' => $stored];
                }
            });
            SopSetting::flush();
            SopLog::catat('KEBIJAKAN_SETTING', 'Pengaturan global kebijakan dokumen diperbarui.', null, null, null, null, [
                'changes' => $changes,
                'oleh_id_users' => Auth::id(),
            ]);

            return ResponseHelper::success(null, 'Kebijakan global diperbarui.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->serverError('update settings', $e);
        }
    }

    private function settingValues(): array
    {
        $values = [];
        foreach (self::kontrakSetelan() as $key => $definition) {
            $raw = SopSetting::get($key, (string) $definition['default']);
            $values[$key] = $definition['type'] === 'int' ? (int) $raw : strtoupper((string) $raw) === 'Y';
        }

        return $values;
    }

    private function schedulerHealth(): array
    {
        try {
            return DB::table('N_HRIS_Scheduler_Configs')->whereIn('Command', self::SCHEDULER_COMMANDS)
                ->get(['Command', 'Cron_Expression', 'Timezone', 'Flag_Aktif'])->map(fn ($row) => (array) $row)->all();
        } catch (\Throwable $e) {
            return collect(self::SCHEDULER_COMMANDS)->map(fn ($command) => [
                'Command' => $command, 'Cron_Expression' => null, 'Timezone' => 'Asia/Jakarta',
                'Flag_Aktif' => 'T', 'error' => 'Konfigurasi scheduler belum tersedia.',
            ])->all();
        }
    }

    private function serverError(string $action, \Throwable $e)
    {
        Log::error("SOP setup system {$action}: {$e->getMessage()}", ['exception' => $e]);

        return ResponseHelper::error('Terjadi kesalahan sistem.', 500);
    }
}
