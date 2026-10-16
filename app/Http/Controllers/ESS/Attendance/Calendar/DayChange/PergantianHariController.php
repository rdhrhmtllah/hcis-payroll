<?php

namespace App\Http\Controllers\ESS\Attendance\Calendar\DayChange;

use App\Http\Controllers\Controller;

class PergantianHariController extends Controller
{
    public function index()
    {
        return inertia('ESS/Attendance/Calendar/DayChange/pergantianHari');
    }
}
