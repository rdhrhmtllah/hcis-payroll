<?php

namespace App\Models\ESS\Workflow\Ticket\Models;

use App\Models\ESS\Employee\Models\User;
use App\Models\ESS\Organization\Division\Models\Division;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use HasFactory;

    protected $table = 'KPI_Ticket';

    protected $primaryKey = 'Id_Ticket';

    protected $fillable = [
        'No_Ticket',
        'Date',
        'Time',
        'Description',
        'Requester_Id',
        'Recipient_Id',
        'Division_Id',
        'Flag_Approve',
        'Case_Id',
        'Flag_Finish',
    ];

    public $timestamps = false;

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'Requester_Id', 'Id_Users');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'Recipient_Id', 'Id_Users');
    }

    public function divisions(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'Division_Id', 'Id_Division');
    }

    public function responses()
    {
        return $this->belongsTo(ReponseTicket::class, 'Id_Ticket', 'Ticket_Id');
    }
}
