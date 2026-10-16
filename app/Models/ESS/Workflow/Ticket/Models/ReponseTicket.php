<?php

namespace App\Models\ESS\Workflow\Ticket\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReponseTicket extends Model
{
    use HasFactory;

    protected $table = 'KPI_Ticket_Response';

    protected $primaryKey = 'Id_Ticket_Response';

    protected $fillable = [
        'No_Ticket',
        'Date',
        'Time',
        'Flag_Approve',
        'Flag_Finish',
        'Ticket_Id',
        'Tanggal_Estimasi_Selesai',
    ];

    public $timestamps = false;
}
