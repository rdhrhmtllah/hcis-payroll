<?php

namespace App\Http\Controllers\ESS\Organization\Level;

use App\Http\Controllers\Controller;
use App\Models\ESS\Organization\Level\Models\Level;

class LevelController extends Controller
{
    public function index()
    {
        $title = 'Jabatan';
        $data = Level::all();

        return view('level.index', ['data' => $data, 'title' => $title]);
    }
}
