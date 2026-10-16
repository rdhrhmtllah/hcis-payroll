<?php

namespace App\Models\ESS\Attendance\Overtime\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lembur extends Model
{
    use HasFactory;

    protected $table = 'Transaksi_Lembur';

    // protected $primaryKey = 'Id_Cross_Review'
    protected $guarded = [];

    public $timestamps = false;
}
