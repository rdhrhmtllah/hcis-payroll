<?php

namespace App\Http\Controllers\ESS\Organization\Division;

use App\Http\Controllers\Controller;
use App\Models\ESS\Organization\Division\Models\Division;

class DivisionController extends Controller
{
    public function index()
    {
        $title = 'Divisi';
        $data = Division::all();

        return view('division.index', ['data' => $data, 'title' => $title]);
    }
}
