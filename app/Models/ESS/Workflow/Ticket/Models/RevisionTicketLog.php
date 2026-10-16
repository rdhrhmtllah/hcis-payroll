<?php

namespace App\Models\ESS\Workflow\Ticket\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RevisionTicketLog extends Model
{
    use HasFactory;

    protected $table = 'KPI_Revision_Ticket_Log';

    protected $primaryKey = 'Id_Revision_Ticket';

    protected $fillable = [
        'No_Ticket',
        'Date',
        'Time',
        'Deadline',
        'Description',
        'Ticket_Id',
    ];

    public $timestamps = false;
}
