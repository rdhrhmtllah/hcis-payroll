<?php

namespace App\Http\Controllers\ESS\Dashboard\Personal;

use App\Http\Controllers\Controller;
use App\Models\ESS\Workflow\Ticket\Models\Ticket;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function superUser()
    {
        // $id_user = Auth::user()->Id_Users;

        $ticket_masuk = Ticket::whereHas('responses', function ($query) {
            $id_user = Auth::user()->Id_Users;
            $query->whereNull('Flag_Approve')->where('Recipient_Id', '=', $id_user);
        })->count();
        // dd($ticket_masuk);
        $ticket_onprosess = Ticket::whereHas('responses', function ($query) {
            $id_user = Auth::user()->Id_Users;
            $query->where('Flag_Approve', '=', 'Y')->where('Recipient_Id', '=', $id_user);
        })->count();
        $id_user = Auth::user()->Id_Users;
        $ticket_selesai = Ticket::where([
            'Flag_Finish' => 'Y',
            'Recipient_Id' => $id_user,
        ])->count();

        return view('dashboard.superUser', [
            'ticket_masuk' => $ticket_masuk,
            'ticket_onprosess' => $ticket_onprosess,
            'ticket_selesai' => $ticket_selesai,
        ]);
    }
    // public function user(){
    //     return view('dashboard.user');
    // }
}
