<?php

namespace App\Http\Controllers\PAYROLL\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('PAYROLL/Dashboard/Index', [
            'title' => 'Dashboard Payroll',
            'stats' => [
                'total_karyawan' => 0,
                'total_payroll_berjalan' => 0,
                'status_periode' => 'DRAFT',
                'periode_aktif' => now()->translatedFormat('F Y'),
            ],
        ]);
    }
}
