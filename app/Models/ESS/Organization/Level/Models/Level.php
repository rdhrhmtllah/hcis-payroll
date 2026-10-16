<?php

namespace App\Models\ESS\Organization\Level\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    use HasFactory;

    protected $table = 'KPI_Levels';

    protected $primaryKey = 'Id_Level';
}
