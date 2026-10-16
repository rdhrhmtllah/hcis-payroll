<?php

namespace App\Services\LMS\SOP\Support;

use Illuminate\Support\Facades\DB;

/**
 * Gerbang urutan (prasyarat) item SOP — penegakan BACKEND.
 * Meniru persis logika "Terkunci" di SopProgressReadService::detailBundle:
 * pada paket Mode_Urutan_Item = 'SEKUENSIAL', sebuah item TERKUNCI bila ada
 * item WAJIB dengan Urutan lebih kecil yang belum selesai (SELESAI/DIKONFIRMASI)
 * pada versi aktifnya. Dipakai konfirmasi() & launch() agar UI-lock tak bisa
 * ditembus lewat API langsung.
 */
class SopSequenceGate
{
    /** Terkunci? true bila item tak boleh dikerjakan karena prasyarat urutan. */
    public static function isLocked(string $kode, int $idPaket, int $idItem): bool
    {
        $mode = DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->value('Mode_Urutan_Item');
        if ($mode !== 'SEKUENSIAL') {
            return false;
        } // mode BEBAS → tak pernah terkunci

        $detailId = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('pj.Id_SOP_Paket', $idPaket)
            ->where('d.Kode_Karyawan', $kode)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->orderByDesc('d.Id_SOP_Penjadwalan_Detail')
            ->value('d.Id_SOP_Penjadwalan_Detail');
        if (! $detailId) {
            return false;
        } // tak ditugaskan → penolakan ditangani alur lain

        $selesai = [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI];

        /*
         * Status dipetakan PER ITEM, bukan per pasangan item+versi.
         *
         * Progres ditulis pada versi AKTIF saat itu (SopCompletionService::resolve),
         * sementara daftar urutan di bawah dibaca dari SNAPSHOT rilis paket. Kedua
         * angka itu sah berbeda: versi item yang diterbitkan di Builder baru masuk
         * snapshot ketika admin menekan Publish Paket, jadi selama ada revisi
         * tertunda pointer live sudah bergeser sementara snapshot belum.
         *
         * Mencocokkan dengan kunci "item:versi" membuat penyelesaian yang SAH
         * tak pernah terbaca — item wajib itu dianggap selamanya belum selesai dan
         * seluruh item sesudahnya terkunci permanen. Terbukti pada paket 23 item 88
         * (snapshot v83, live v86): item urutan 6-8 mustahil dibuka.
         *
         * Yang dipedulikan gerbang ini hanyalah "apakah item wajib sebelumnya sudah
         * dituntaskan", dan itu benar untuk versi mana pun yang dikerjakan karyawan.
         * Kewajiban membaca ULANG versi baru ditangani jalur revisi versi, bukan di
         * sini.
         */
        $statusMap = [];
        foreach (DB::table('N_SOP_Progress')->where('Id_SOP_Penjadwalan_Detail', $detailId)
            ->get(['Id_SOP_Paket_Item', 'Status']) as $pr) {
            $item = (int) $pr->Id_SOP_Paket_Item;
            // Satu item bisa punya beberapa baris progres (satu per versi). Yang
            // menang adalah status "sudah selesai" mana pun di antaranya.
            if (in_array($pr->Status, $selesai, true) || ! isset($statusMap[$item])) {
                $statusMap[$item] = $pr->Status;
            }
        }

        $snapshot = app(\App\Services\LMS\SOP\SopPaketVersionService::class)->releasedSnapshot($idPaket);
        $items = $snapshot
            ? collect($snapshot['items'])->map(fn ($row) => (object) [
                'Id_SOP_Paket_Item' => (int) $row['Id_SOP_Paket_Item'],
                'Id_Item_Versi_Aktif' => $row['Id_SOP_Item_Versi'] ?? null,
                'Flag_Wajib' => ! empty($row['Wajib']) ? SopStatus::AKTIF : SopStatus::INAKTIF,
            ])
            : DB::table('N_SOP_Paket_Item')
                ->where('Id_SOP_Paket', $idPaket)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->orderBy('Urutan')
                ->get(['Id_SOP_Paket_Item', 'Id_Item_Versi_Aktif', 'Flag_Wajib']);

        $adaWajibBelumSelesai = false;

        foreach ($items as $it) {
            if ((int) $it->Id_SOP_Paket_Item === $idItem) {
                /*
                 * Item yang SUDAH dituntaskan karyawan ini tak pernah digerbangi.
                 *
                 * Gerbang urutan memaksa orang mengerjakan berurutan; item yang
                 * sudah rampung tak punya apa pun untuk digerbangi. Tanpa jalan
                 * keluar ini, satu revisi pada dokumen di TENGAH paket membuat
                 * karyawan tak bisa lagi MEMBUKA dokumen-dokumen sesudahnya yang
                 * sudah ia tuntaskan — buka() memakai gerbang yang sama — jadi
                 * arsip pribadinya ikut terkunci gara-gara perubahan pada berkas
                 * lain.
                 */
                if (in_array($statusMap[$idItem] ?? SopStatus::ITEM_BELUM, $selesai, true)) {
                    return false;
                }

                // terkunci bila ada wajib SEBELUMNYA yang belum selesai
                return $adaWajibBelumSelesai;
            }
            $st = $statusMap[(int) $it->Id_SOP_Paket_Item] ?? SopStatus::ITEM_BELUM;
            if ($it->Flag_Wajib === SopStatus::AKTIF && ! in_array($st, $selesai, true)) {
                $adaWajibBelumSelesai = true;
            }
        }

        return false; // item tak ada di urutan aktif → anggap tak terkunci
    }
}
