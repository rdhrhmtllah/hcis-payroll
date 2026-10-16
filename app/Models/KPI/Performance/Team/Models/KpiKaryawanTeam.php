<?php

namespace App\Models\KPI\Performance\Team\Models;

use App\Models\ESS\Employee\Models\Karyawan;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KpiKaryawanTeam extends Model
{
    use HasFactory;

    protected $table = 'KPI_Karyawan_Team';

    protected $primaryKey = 'Id_Karyawan_Team';

    public $timestamps = false;

    protected $fillable = [
        'Kode_Karyawan_Leader',
        'Kode_Karyawan_Team',
        'create_at',
        'update_at',
        'Id_User',
        'Flag_Aktif',
    ];

    protected $casts = [
        'create_at' => 'datetime',
        'update_at' => 'datetime',
    ];

    public function leader()
    {
        return $this->belongsTo(Karyawan::class, 'Kode_Karyawan_Leader', 'Kode_Karyawan');
    }

    public function member()
    {
        return $this->belongsTo(Karyawan::class, 'Kode_Karyawan_Team', 'Kode_Karyawan');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'Id_User', 'Id_Users');
    }

    // Scope untuk mendapatkan tim yang aktif
    public function scopeActive($query)
    {
        return $query->where('Flag_Aktif', 'Y');
    }

    // Scope untuk mendapatkan anggota tim berdasarkan leader
    public function scopeByLeader($query, $leaderKode)
    {
        return $query->where('Kode_Karyawan_Leader', $leaderKode);
    }

    // Scope untuk mendapatkan leader dari anggota
    public function scopeByMember($query, $memberKode)
    {
        return $query->where('Kode_Karyawan_Team', $memberKode);
    }
}
