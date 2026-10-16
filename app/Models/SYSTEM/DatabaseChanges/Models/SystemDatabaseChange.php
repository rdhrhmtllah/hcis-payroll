<?php

namespace App\Models\SYSTEM\DatabaseChanges\Models;

use Illuminate\Database\Eloquent\Model;

class SystemDatabaseChange extends Model
{
    protected $table = 'N_SYSTEM_Database_Change';

    protected $primaryKey = 'Id_Database_Change';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['Created_At' => 'datetime', 'Updated_At' => 'datetime'];
}
