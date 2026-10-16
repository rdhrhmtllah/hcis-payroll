<?php

namespace App\Models\ESS\Workflow\Ticket\Models;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubmitTicketLog extends Model
{
    use HasFactory;

    protected $table = 'KPI_Submit_Ticket_Log';

    protected $primaryKey = 'Id_Submit_Ticket_Log';

    protected $fillable = [
        'Id_Submit_Ticket_Log',
        'Date',
        'Time',
        'Note',
        'Ticket_Id',
        'Id_User',
        'Status_Note',
    ];

    public $timestamps = false;

    public function users()
    {
        return $this->belongsTo(User::class, 'Id_User');
    }
}
