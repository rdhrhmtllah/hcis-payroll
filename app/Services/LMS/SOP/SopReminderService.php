<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopReminderCadence;
use App\Services\LMS\SOP\Support\SopSetting;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SopReminderService
{
    /** Baris per putaran pemindaian. Menahan memori tetap datar berapa pun jumlah penugasan. */
    private const CHUNK_PINDAI = 1000;

    /** Karyawan per putaran pengiriman. Menentukan seberapa besar kerja yang hilang bila satu putaran gagal. */
    private const CHUNK_KIRIM = 200;

    public function scan(?int $defaultDays = null): array
    {
        $fallback = max(1, $defaultDays ?: SopSetting::int('default_reminder_hari', 3));
        $today = Carbon::today();

        $q = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->where('d.Status_Paket', '<>', SopStatus::STATUS_SELESAI)
            ->where('pj.Flag_Aktif', SopStatus::AKTIF)
            ->where('pj.Flag_Cabut_Akses', 'T')
            ->where('pj.Flag_Reminder', 'Y')
            // Paket yang sudah diarsipkan atau dinonaktifkan bukan lagi kewajiban:
            // coveragePaket dan summary sudah tak menghitungnya, tapi reminder masih
            // menagih karyawan untuk SOP yang sudah tidak berlaku.
            ->where('p.Status', SopStatus::PAKET_PUBLISHED)
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->select([
                'd.Id_SOP_Penjadwalan_Detail', 'd.Kode_Karyawan', 'd.Terakhir_Diingatkan_At',
                'd.Jumlah_Reminder', 'd.Created_At as Detail_Created_At', 'pj.Tanggal_Assign',
                'pj.Tipe_Tenggat', 'pj.Tanggal_Deadline', 'pj.Irama_Reminder_Hari',
                'pj.Id_SOP_Paket', 'p.Nama_Paket',
            ]);

        /*
         * Dipindai per potongan, bukan sekali get().
         *
         * Kueri ini menarik SELURUH penugasan aktif yang belum selesai lalu
         * menyaringnya di PHP — pada ribuan karyawan dikali puluhan paket itu
         * berarti puluhan ribu baris berisi sebelas kolom ditahan di memori
         * sekaligus, padahal hanya sebagian kecil yang benar-benar jatuh tempo.
         * Penyaringannya tak bisa dipindah ke SQL karena irama pengingat dihitung
         * SopReminderCadence, tapi memorinya bisa dibuat datar.
         */
        $found = 0;
        $due = collect();

        $q->chunkById(self::CHUNK_PINDAI, function ($rows) use (&$found, &$due, $today, $fallback) {
            $found += $rows->count();

            foreach ($rows as $row) {
                $interval = max(1, (int) ($row->Irama_Reminder_Hari ?: $fallback));
                $assignedAt = $row->Tanggal_Assign ?: $row->Detail_Created_At;
                $tenggat = $row->Tipe_Tenggat === 'DEADLINE' ? $row->Tanggal_Deadline : null;

                if (! SopReminderCadence::isDue($today, $interval, $row->Terakhir_Diingatkan_At, $tenggat, $assignedAt)) {
                    continue;
                }

                $deadline = $row->Tanggal_Deadline ? Carbon::parse($row->Tanggal_Deadline)->startOfDay() : null;
                $due->push([
                    'detail_id' => (int) $row->Id_SOP_Penjadwalan_Detail,
                    'kode' => $row->Kode_Karyawan,
                    'id_paket' => (int) $row->Id_SOP_Paket,
                    'nama' => $row->Nama_Paket,
                    'tenggat' => $deadline?->format('Y-m-d'),
                    'overdue' => $deadline?->lt($today) ?? false,
                ]);
            }
        }, 'd.Id_SOP_Penjadwalan_Detail', 'Id_SOP_Penjadwalan_Detail');

        if ($due->isEmpty()) {
            return ['found' => $found, 'due' => 0, 'sent' => 0, 'details_updated' => 0];
        }

        $groups = $due->groupBy('kode')->map(fn ($items) => $items->all())->all();

        /*
         * Kirim lalu TANDAI per putaran, bukan semua-lalu-semua.
         *
         * Sebelumnya seluruh karyawan dikirimi dalam satu panggilan dan penandaan
         * Terakhir_Diingatkan_At baru dilakukan sesudahnya. Satu kegagalan di
         * tengah karena itu membatalkan penandaan untuk SEMUA orang — termasuk
         * yang notifikasinya sudah benar-benar terkirim — sehingga besok malam
         * scan mengulang persis pekerjaan yang sama dan gagal di titik yang sama.
         * Dengan penandaan per putaran, kemajuan yang sudah sah tetap berdiri dan
         * putaran yang gagal cukup diulang di jadwal berikutnya.
         */
        $sent = 0;
        $updated = 0;
        $gagal = 0;

        foreach (array_chunk($groups, self::CHUNK_KIRIM, true) as $i => $batch) {
            try {
                $sent += app(SopNotificationService::class)->notifReminderDigest($batch, $today->format('Y-m-d'));

                $ids = [];
                foreach ($batch as $items) {
                    foreach ($items as $it) {
                        $ids[] = $it['detail_id'];
                    }
                }
                $ids = array_values(array_unique($ids));

                foreach (array_chunk($ids, 400) as $chunk) {
                    DB::table('N_SOP_Penjadwalan_Detail')->whereIn('Id_SOP_Penjadwalan_Detail', $chunk)->update([
                        'Terakhir_Diingatkan_At' => now(),
                        'Jumlah_Reminder' => DB::raw('Jumlah_Reminder + 1'),
                        'Updated_At' => now(),
                    ]);
                }

                $updated += count($ids);
            } catch (\Throwable $e) {
                $gagal += count($batch);
                Log::error('SOP reminder scan gagal', ['putaran' => $i, 'exception' => $e]);
            }
        }

        return [
            'found' => $found,
            'due' => $due->count(),
            'sent' => $sent,
            'details_updated' => $updated,
            'employees' => count($groups),
            'overdue' => $due->where('overdue', true)->count(),
            'gagal' => $gagal,
        ];
    }
}
