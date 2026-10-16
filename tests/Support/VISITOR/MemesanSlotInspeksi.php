<?php

namespace Tests\Support\VISITOR;

use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use Illuminate\Support\Facades\DB;

/**
 * Jadwal inspeksi untuk uji yang mengajukan permit karyawan (DDL-2026-123).
 *
 * Sejak DDL-2026-123 store() menolak pengajuan tanpa slot inspeksi. Uji-uji
 * lama tidak sedang menguji jadwal, jadi mereka cukup meminjam satu slot dari
 * pola pos rekaan yang ditanam di dalam transaksi uji — TIDAK bergantung pada
 * pola dev yang bisa diubah SHE kapan saja.
 */
trait MemesanSlotInspeksi
{
    private const POS_INSPEKSI_UJI = 'Pos Uji Otomatis';

    /** Field slot siap digabung ke payload store(): inspeksi_id_pos + inspeksi_mulai. */
    protected function slotInspeksiUji(): array
    {
        $besok = now()->addDay();

        $idPos = DB::table(JadwalInspeksiService::T_POS)->where('Nama_Pos', self::POS_INSPEKSI_UJI)->value('Id_Pos_Inspeksi')
            ?? DB::table(JadwalInspeksiService::T_POS)->insertGetId([
                'Nama_Pos' => self::POS_INSPEKSI_UJI,
                'Flag_Aktif' => 'Y',
                'Created_By' => 'UJI',
            ]);

        if (! DB::table('N_VISITOR_Inspeksi_Pola')
            ->where('Id_Pos_Inspeksi', $idPos)
            ->where('Hari_Minggu', $besok->dayOfWeekIso)
            ->where('Flag_Aktif', 'Y')
            ->exists()) {
            $idShift = DB::table(JadwalInspeksiService::T_SHIFT)->where('Flag_Aktif', 'Y')->orderBy('Urutan')->value('Id_Shift_Inspeksi')
                ?? DB::table(JadwalInspeksiService::T_SHIFT)->insertGetId([
                    'Nama_Shift' => 'Shift Uji', 'Jam_Mulai' => '07:00', 'Jam_Selesai' => '15:00',
                    'Flag_Aktif' => 'Y', 'Created_By' => 'UJI',
                ]);

            DB::table('N_VISITOR_Inspeksi_Pola')->insert([
                'Id_Pos_Inspeksi' => $idPos,
                'Id_Shift_Inspeksi' => $idShift,
                'Hari_Minggu' => $besok->dayOfWeekIso,
                'Jam_Mulai' => '08:00',
                'Jam_Selesai' => '12:00',
                'Durasi_Menit' => 30,
                'Kuota_Per_Slot' => 50,
                'Flag_Aktif' => 'Y',
                'Created_By' => 'UJI',
            ]);
        }

        return [
            'inspeksi_id_pos' => (int) $idPos,
            'inspeksi_mulai' => $besok->format('Y-m-d').' 08:00',
        ];
    }

    /**
     * Majukan jam inspeksi permit ke masa lalu, supaya persetujuan tidak lagi
     * tertahan penjaga "belum waktunya inspeksi" — uji persetujuan tidak sedang
     * menguji penjaga itu (ia diuji JadwalInspeksiServiceTest).
     */
    protected function lewatiJamInspeksi(string $noTransaksi): void
    {
        DB::table('N_VISITOR_Inspeksi_Booking')
            ->where('No_Transaksi_Permit', $noTransaksi)
            ->where('Status', JadwalInspeksiService::TERJADWAL)
            ->update([
                'Waktu_Mulai' => now()->subMinutes(30),
                'Waktu_Selesai' => now(),
            ]);
    }
}
