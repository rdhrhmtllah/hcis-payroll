<?php

namespace App\Services\LMS\Training\Scheduling;

use Illuminate\Support\Facades\DB;

/**
 * Menyalin struktur silabus sebuah materi menjadi struktur penjadwalan.
 *
 * KENAPA JADI SERVICE. Metode ini pernah hidup sebagai salinan IDENTIK
 * BYTE-PER-BYTE di dua controller — LmsMateriController:2348 dan
 * TrainingCatalogController:664, 76 baris masing-masing. Dua salinan berarti
 * setiap perbaikan harus mendarat dua kali, dan modul ini punya riwayat panjang
 * aturan yang hanya terpasang di satu dari dua jalur setara. Yang dicegah bukan
 * ketidakrapian, melainkan pola itu.
 *
 * SNAPSHOT, BUKAN CERMIN. Penjadwalan adalah potret silabus pada saat
 * assign/update. Kalau detailnya sudah pernah tersimpan, ia TIDAK disinkronkan
 * ulang ke silabus terbaru — sebab menyinkronkan akan menambahkan modul baru ke
 * jadwal lama, dan peserta yang sudah menyelesaikan jadwalnya tiba-tiba punya
 * pekerjaan yang belum selesai. Itulah arti pemeriksaan $hasExistingSnapshot di
 * awal, dan ia harus tetap menjadi hal pertama yang dikerjakan.
 */
class PenjadwalanStrukturService
{
    public function pastikan(int $penjadwalanId, int $materiId): void
    {
        DB::transaction(function () use ($penjadwalanId, $materiId) {
            $hasExistingSnapshot = DB::table('N_LMS_Penjadwalan_Detail as pd')
                ->join('N_LMS_Materi_Detail as md', 'pd.Id_Materi_Detail', '=', 'md.Id_Materi_Detail')
                ->where('pd.Id_Penjadwalan', $penjadwalanId)
                ->where('md.Id_Master_Training', $materiId)
                ->exists();

            // Penjadwalan harus menjadi snapshot pada saat assign/update.
            // Kalau detail training sudah pernah tersimpan, jangan sinkronkan lagi
            // ke struktur silabus terbaru karena bisa menambah modul baru ke jadwal lama.
            if ($hasExistingSnapshot) {
                return;
            }

            $sections = DB::table('N_LMS_Materi_Detail')
                ->where('Id_Master_Training', $materiId)
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Urutan_Sub_Training')
                ->get();

            foreach ($sections as $section) {
                $penjadwalanDetail = DB::table('N_LMS_Penjadwalan_Detail')
                    ->where('Id_Penjadwalan', $penjadwalanId)
                    ->where('Id_Materi_Detail', $section->Id_Materi_Detail)
                    ->first();

                if (! $penjadwalanDetail) {
                    $penjadwalanDetailId = DB::table('N_LMS_Penjadwalan_Detail')->insertGetId([
                        'Id_Penjadwalan' => $penjadwalanId,
                        'Id_Materi_Detail' => $section->Id_Materi_Detail,
                        'Urutan' => $section->Urutan_Sub_Training ?? 1,
                        'Flag_Aktif' => 'Y',
                    ]);

                    $penjadwalanDetail = (object) [
                        'Id_Penjadwalan_Detail' => $penjadwalanDetailId,
                    ];
                }

                $items = DB::table('N_LMS_Materi_Det')
                    ->leftJoin(
                        'N_LMS_Master_Aktivitas as akt',
                        'N_LMS_Materi_Det.Id_Master_Aktivitas',
                        '=',
                        'akt.Id_Master_Aktivitas',
                    )
                    ->where('N_LMS_Materi_Det.Id_Materi', $materiId)
                    ->where('N_LMS_Materi_Det.Id_Materi_Detail', $section->Id_Materi_Detail)
                    ->where('N_LMS_Materi_Det.Flag_Aktif', 'Y')
                    ->orderBy('N_LMS_Materi_Det.Urutan_Materi')
                    ->select(['N_LMS_Materi_Det.Id_Materi_Det', 'akt.Flag_Third_Party as Aktivitas_Third_Party'])
                    ->get();

                foreach ($items as $item) {
                    $exists = DB::table('N_LMS_Penjadwalan_Det')
                        ->where('Id_Penjadwalan_Detail', $penjadwalanDetail->Id_Penjadwalan_Detail)
                        ->where('Id_Materi_Det', $item->Id_Materi_Det)
                        ->exists();

                    if (! $exists) {
                        DB::table('N_LMS_Penjadwalan_Det')->insert([
                            'Id_Penjadwalan_Detail' => $penjadwalanDetail->Id_Penjadwalan_Detail,
                            'Id_Materi_Det' => $item->Id_Materi_Det,
                            'Flag_Checkin' => 'T',
                            'Flag_Third_Party' => $item->Aktivitas_Third_Party ?? 'T',
                            'Flag_Selesai' => 'T',
                            'Flag_Mempunyai_Akhir_Waktu' => 'T',
                            'Status_Download' => 'T',
                        ]);
                    }
                }
            }
        });
    }
}
