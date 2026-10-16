<?php

namespace App\Http\Controllers\ESS\Employee\Profile;

use App\Http\Controllers\Controller;
use App\Models\ESS\Employee\Models\User;
use App\Models\ESS\Organization\Division\Models\Division;

class EmployeeController extends Controller
{
    public function index()
    {
        $divisi = Division::all();
        $title = 'Karyawan';
        $data = User::all();

        return view('employee.index', ['data' => $data, 'title' => $title, 'divisions' => $divisi]);
    }
}
