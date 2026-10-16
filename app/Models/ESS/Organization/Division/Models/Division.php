<?php

namespace App\Models\ESS\Organization\Division\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    use HasFactory;

    protected $table = 'KPI_Divisions';

    protected $primaryKey = 'Id_Division';
}
