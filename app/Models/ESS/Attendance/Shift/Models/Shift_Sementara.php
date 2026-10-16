<?php

namespace App\Models\ESS\Attendance\Shift\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift_Sementara extends Model
{
    use HasFactory;

    protected $table = 'HRIS_Shift_Sementara';

    protected $primaryKey = 'Urut';
}
