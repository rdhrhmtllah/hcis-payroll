<?php

namespace App\Services\VISITOR\Permit\VehiclePermit;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Master data form permit karyawan. Dipakai form pengajuan dan dialog koreksi
 * verifikator supaya pilihan keduanya tidak bisa berbeda.
 */
class VisitorPermitMasterService
{
    public function untukPengajuan(): array
    {
        return [
            'plat_codes' => DB::table('N_EMI_VISITOR_Master_Plat_Kendaraan')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Kode_Plat')
                ->pluck('Kode_Plat'),
            'vehicle_types' => DB::table('N_EMI_VISITOR_Master_Vehicle_Type')
                ->where('Flag_Aktif', 'Y')
                ->where(function ($query) {
                    $query->whereNull('Akses')->orWhereIn('Akses', ['PERMIT', 'ALL']);
                })
                ->orderBy('Nama_Tipe_Kendaraan')
                ->select(
                    'Id_Vehicle_Type as Id',
                    'Id_Vehicle_Type',
                    'Nama_Tipe_Kendaraan as Nama_Vehicle_Type',
                    'Nama_Tipe_Kendaraan',
                )
                ->get(),
            // Hanya model yang jenisnya boleh untuk permit: server menolak sisanya saat
            // disimpan, jadi menawarkannya di dropdown hanya menunda kegagalan.
            'models' => DB::table('N_EMI_VISITOR_Master_Vehicle_Model')
                ->where('Flag_Aktif', 'Y')
                ->whereIn('Id_Master_Tipe', fn ($q) => $q->select('Id_Vehicle_Type')
                    ->from('N_EMI_VISITOR_Master_Vehicle_Type')
                    ->where('Flag_Aktif', 'Y')
                    ->where(fn ($a) => $a->whereNull('Akses')->orWhereIn('Akses', ['PERMIT', 'ALL'])))
                ->orderBy('Nama_Model_Kendaraan')
                ->select('Id_Vehicle_Model as Id', 'Id_Vehicle_Model', 'Id_Master_Tipe', 'Nama_Model_Kendaraan')
                ->get(),
            'colors' => DB::table('N_EMI_VISITOR_Master_Vehicle_Color')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Nama_Warna')
                ->select('Id_Master_Color as Id', 'Id_Master_Color', 'Nama_Warna')
                ->get(),
            'kategoris' => DB::table('N_EMI_VISITOR_Master_Kategori_Permit')
                ->where('Flag_Aktif', 'Y')
                ->where(fn (Builder $query) => KategoriPermitPengajuan::saring($query))
                ->orderBy('Nama_Kategori')
                ->select(
                    'Id_Kategori_Permit as Id',
                    'Id_Kategori_Permit',
                    'Nama_Kategori',
                    'Deskripsi_Akses as Deskripsi',
                    'Deskripsi_Akses',
                    'Kode_Warna_UI as Kode_Warna',
                    'Kode_Warna_UI',
                )
                ->get(),
            'durations' => DB::table('N_EMI_VISITOR_Master_Durasi_Permit')
                ->where('Flag_Aktif', 'Y')
                ->where('Satuan_Waktu', 'MONTH')
                ->where('Jumlah_Angka', '>', 0)
                ->orderBy('Jumlah_Angka')
                ->select('Id_Durasi_Permit', 'Nama_Durasi', 'Jumlah_Angka', 'Satuan_Waktu')
                ->get(),
        ];
    }
}
