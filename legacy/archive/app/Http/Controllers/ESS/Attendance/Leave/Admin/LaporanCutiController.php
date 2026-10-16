<?php

namespace App\Http\Controllers\ESS\Attendance\Leave\Admin;

use App\Http\Controllers\Controller;
use App\Models\PengajuanCuti;
use Illuminate\Http\Request;

class LaporanCutiController extends Controller
{
    public function exportCSV(Request $request)
    {
        $pengajuan = PengajuanCuti::with(['karyawan', 'jenisCuti'])
            ->whereBetween('created_at', [
                $request->start_date ?? now()->startOfMonth(),
                $request->end_date ?? now()->endOfMonth(),
            ])
            ->get();

        $fileName = 'laporan-cuti-'.now()->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        $callback = function () use ($pengajuan) {
            $file = fopen('php://output', 'w');

            // Header
            fputcsv($file, [
                'Nama Karyawan',
                'Kode Karyawan',
                'Jenis Cuti',
                'Tanggal Mulai',
                'Tanggal Selesai',
                'Jumlah Hari',
                'Status',
                'Alasan',
                'Tanggal Pengajuan',
            ]);

            // Data
            foreach ($pengajuan as $p) {
                fputcsv($file, [
                    $p->karyawan->nama,
                    $p->karyawan->kode_karyawan,
                    $p->jenisCuti->nama,
                    $p->tanggal_mulai,
                    $p->tanggal_selesai,
                    $p->jumlah_hari,
                    $this->getStatusText($p->status),
                    $p->alasan,
                    $p->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportPDF(Request $request)
    {
        $pengajuan = PengajuanCuti::with(['karyawan', 'jenisCuti'])
            ->whereBetween('created_at', [
                $request->start_date ?? now()->startOfMonth(),
                $request->end_date ?? now()->endOfMonth(),
            ])
            ->get();

        // You can use DomPDF or any PDF library here
        // This is a simplified example
        $pdf = \PDF::loadView('admin.cuti.laporan-pdf', [
            'pengajuan' => $pengajuan,
            'periode' => [
                'start' => $request->start_date ?? now()->startOfMonth()->format('d/m/Y'),
                'end' => $request->end_date ?? now()->endOfMonth()->format('d/m/Y'),
            ],
        ]);

        return $pdf->download('laporan-cuti-'.now()->format('Y-m-d').'.pdf');
    }

    private function getStatusText($status)
    {
        $statuses = [
            'pending' => 'Menunggu',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
        ];

        return $statuses[$status] ?? $status;
    }
}
