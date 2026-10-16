<?php

namespace App\Models\ESS\Workflow\CrossDivision\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrossReviews extends Model
{
    use HasFactory;

    protected $table = 'KPI_Cross_Review';

    // protected $primaryKey = 'Id_Cross_Review'
    protected $guarded = [];

    public $timestamp = false;
}
