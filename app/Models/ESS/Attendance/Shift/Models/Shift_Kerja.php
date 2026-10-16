<?php

namespace App\Models\ESS\Attendance\Shift\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift_Kerja extends Model
{
    use HasFactory;

    protected $table = 'HRIS_Shift_Kerja';

    protected $primaryKey = 'ID_Shift';
}
