<?php

namespace App\Models\ESS\Workflow\Ticket\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketDetailReview extends Model
{
    use HasFactory;

    protected $table = 'KPI_Ticket_Detail_Review';

    protected $guarded = [];

    public $timestamps = false;
}
