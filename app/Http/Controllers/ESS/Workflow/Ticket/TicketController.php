<?php

namespace App\Http\Controllers\ESS\Workflow\Ticket;

use App\Helpers\WhatsappLogHelper;
use App\Http\Controllers\Controller;
use App\Models\ESS\Employee\Models\User;
use App\Models\ESS\Organization\Division\Models\Division;
use App\Models\ESS\Workflow\Case\Models\Cases;
use App\Models\ESS\Workflow\Ticket\Models\ReponseTicket;
use App\Models\ESS\Workflow\Ticket\Models\SubmitTicketLog;
use App\Models\ESS\Workflow\Ticket\Models\Ticket;
use App\Models\ESS\Workflow\Ticket\Models\TicketDetailReview;
use App\Models\ESS\Workflow\Ticket\Models\TicketFile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RealRashid\SweetAlert\Facades\Alert;
use Vinkla\Hashids\Facades\Hashids;

class TicketController extends Controller
{
    public function index()
    {
        // dd("Oke");
        $user = Auth()->user();

        $divisiUser = $user->Division_Id;

        $dataDivisi_temp = Division::select('Id_Division', 'Name')->where([
            'Flag_Active' => 'Y',
            // 'Id_Division' => $divisiUser
        ])->get();
        $dataDivisi = [];
        foreach ($dataDivisi_temp as $item) {
            $temp = [];
            $temp['Id_Division'] = Hashids::connection('custom')->encode($item->Id_Division);
            $temp['Name'] = $item->Name;

            $dataDivisi[] = $temp;
        }

        // Ambil data divisi aktif
        // dd($dataDivisi);
        // return inertia('ESS/Workflow/Ticket/Ticket');
        return inertia('ESS/Workflow/Ticket/Ticket', [
            'dataDivisi' => $dataDivisi, // Pastikan data diubah menjadi array
        ]);
    }

    public function displayTicket()
    {
        $title = 'Display Ticket';
        $user = Auth::user();
        $user_id = $user->Id_Users;

        $data1 = 'select a.No_Ticket,a.Id_Ticket,a.Date,a.Time,a.Description,c.nama as Username,b.Flag_Approve,b.Flag_Finish, b.Flag_Finish_Requester,b.Flag_Finish_Reciepent from kpi_ticket a, KPI_Ticket_Response b, kpi_users c  where a.No_Ticket = b.No_Ticket and a.Id_Ticket = b.Ticket_Id and a.Requester_Id = :user  and a.Recipient_Id = c.Id_users and a.status is null ';
        $data_temp = DB::select($data1, [
            'user' => $user_id,
        ]);

        $data = [];

        foreach ($data_temp as $item) {
            $temp = [];
            $temp['Id_Ticket'] = Hashids::connection('custom')->encode($item->Id_Ticket);
            $temp['No_Ticket'] = $item->No_Ticket;
            $temp['Date'] = date('d M Y', strtotime($item->Date));
            $temp['Time'] = $item->Time;
            $temp['Description'] = $item->Description;
            $temp['Username'] = $item->Username;
            $temp['Flag_Approve'] = $item->Flag_Approve;
            $temp['Flag_Finish'] = $item->Flag_Finish;
            $temp['Flag_Finish_Requester'] = $item->Flag_Finish_Requester;
            $temp['Flag_Finish_Reciepent'] = $item->Flag_Finish_Reciepent;
            $temp['foto'] = '2LArREG625ocBP1PaWcSWxr3VcEpFu20BAEdFVUR.png';
            $data[] = $temp;
        }

        // dd($data);

        return view('ticket.displayTicket', ['title' => $title, 'data' => $data]);
    }

    public function takeTicket()
    {
        $title = 'Response Ticket';
        $divisi = Division::all();
        // $data = Ticket::join('KPI_Ticket_Response as b', function ($join) {
        //     $join->on('KPI_Ticket.No_Ticket', '=', 'b.No_Ticket');
        //     // ->on('KPI_Ticket.No_Ticket', '=', 'b.No_Ticket');
        // })
        //     ->where('KPI_Ticket.Recipient_Id', Auth::user()->Id_Users)
        //     ->whereNull('b.Flag_Approve')
        //     ->select('KPI_Ticket.*', 'b.*')
        //     ->get();

        $query = 'select a.Id_Ticket,a.No_Ticket,a.Date,a.Time,a.Description, c.Name as Division, d.Nama as Nama_User From kpi_ticket a, kpi_ticket_response  b, KPI_Divisions c, KPI_Users d where a.Id_ticket = b.Ticket_Id ';
        $query .= 'and a.Division_Id = c.Id_Division and a.requester_id = d.Id_Users  and  a.Recipient_id = :id_user and b.Flag_Approve is null and a.status is null  ';

        $data = DB::select($query, [
            'id_user' => Auth::user()->Id_Users,
        ]);

        // dd($data);
        // dd($data[0]['divisions'][0]->Name);
        return view('ticket.takeTicket', ['title' => $title, 'data' => $data, 'divisions' => $divisi]);
    }

    public function responseTicket(Request $request)
    {
        DB::beginTransaction(); // Start the transaction
        // dd($request->all());
        $id = $request->input('id_ticket');
        $status = $request->input('status');
        try {
            $data = ReponseTicket::where('Ticket_Id', $id)->first(); // Get the first matching record

            if ($data) {
                $data->Date = Carbon::now(); // Set the Date to current date and time
                // $data->Time = Carbon::now(); // Set the Time to current date and time
                $data->Time = '00:00:00';
                $data->Flag_Approve = $status; // Set Flag_Approve to 'Y'
                $data->save(); // Save the changes
            }
            if ($status == 'T') {
                $status_text = 'Ditolak';
            } elseif ($status == 'Y') {
                $status_text = 'Diterima';
            }
            SubmitTicketLog::create([
                'Date' => Carbon::now()->format('Y-m-d'),
                'Time' => Carbon::now()->format('H:i:s'),
                'Note' => $request->keterangan,
                'Ticket_Id' => $id,
                'Id_User' => Auth::user()->Id_Users,
                'Status_Note' => $status_text,
            ]);

            ReponseTicket::where('Ticket_Id', $id)
                ->update([

                    'Tanggal_Estimasi_Selesai' => $request->input('est_tgl_selesai'),
                ]);

            // if ($status == "T") {
            //     $status_text = "Ditolak";
            // } else if ($status == "Y") {
            //     $status_text = "Diterima";
            // }

            // NotifWa

            $ticket = Ticket::where('Id_Ticket', $id)->get();
            $UserNotif = User::where('Id_Users', $ticket[0]->Requester_Id)->get();
            $no_hp = $UserNotif[0]->No_Hp;
            $userKasihTiket = Auth::user()->Nama;
            if ($no_hp) {

                $pesan = [
                    'messaging_product' => 'whatsapp',
                    'to' => $no_hp,
                    'type' => 'template',
                    'template' => [
                        'name' => 'tiket_reminder',
                        'language' => [
                            'code' => 'id',
                            'policy' => 'deterministic',
                        ],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $ticket[0]->No_Ticket,
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $status_text,
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $userKasihTiket.'(Recipient)',
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => date('d-M-Y'),
                                    ],

                                ],
                            ],
                        ],
                    ],
                ];

                /* Pengiriman dipindah ke pintu terpusat: HTTP-nya dijalankan worker
                 * SETELAH commit. Sebelumnya tiga hal salah sekaligus di sini —
                 * getStatusCode() dipanggil pada ARRAY (send_message mengembalikan
                 * $response->json()), HTTP 10-20 detik menahan lock transaksi, dan
                 * WhatsApp yang bermasalah membatalkan data tiket yang sah. */
                WhatsappLogHelper::sendAndLog(
                    $UserNotif[0]->karyawan->Kode_Karyawan ?? null,
                    $UserNotif[0]->Nama ?? $UserNotif[0]->karyawan->Nama ?? null,
                    $no_hp ?? $pesan['to'] ?? null,
                    $ticket[0]->No_Ticket ?? $id ?? null,
                    $pesan,
                );
                // }

            }

            // // Akhir Notif Whatsapp

            DB::commit(); // Commit the transaction
            Alert::success('Success', 'Ticket Berhasil '.$status_text);

            return redirect()->back();
        } catch (\Exception $e) {
            Alert::error('Error', 'Terjadi Kesalahan.');
            DB::rollBack(); // Roll back the transaction if something goes wrong
            throw $e; // Rethrow the exception to handle it further if needed
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function finishTicket()
    {
        $title = 'Finish Ticket';
        $user = Auth::user();
        $user_id = $user->Id_Users;
        $divisi = Division::all();
        // $data = Ticket::where('Recipient_Id', $user_id)
        //     ->whereHas('responses', function ($query) {
        //         $query->where('Flag_Approve', '!=', null);
        //         // $query->where('Flag_Finish', NULL);
        //     })
        //     ->with(['responses' => function ($query) {
        //         $query->where('Flag_Approve', '!=', null);
        //         // $query->where('Flag_Finish', NULL);
        //     }])
        //     ->get();

        $string = 'select a.No_Ticket,a.Date,a.Time,a.Description,c.nama as Username,b.Flag_Approve,d.Name,b.Flag_Finish_Reciepent,a.Id_Ticket as Id_Ticket,b.Flag_Finish_Requester,a.Flag_Finish from KPI_Ticket a, KPI_Ticket_Response b, KPI_Users c, KPI_Divisions d  where a.Id_Ticket = b.Ticket_Id and b.Flag_Approve is not null and a.Recipient_Id = :userid ';
        $string .= 'and a.Requester_Id = c.Id_Users and a.Division_Id = d.Id_Division ';
        $data_temp = DB::select($string, [
            'userid' => $user_id,
        ]);
        $data = [];
        foreach ($data_temp as $item) {
            $temp = [];
            $temp['Id_Ticket'] = Hashids::connection('custom')->encode($item->Id_Ticket);
            $temp['No_Ticket'] = $item->No_Ticket;
            $temp['Date'] = $item->Date;
            $temp['Time'] = $item->Time;
            $temp['Description'] = $item->Description;
            $temp['Username'] = $item->Username;
            $temp['Name'] = $item->Name;
            $temp['Flag_Approve'] = $item->Flag_Approve;
            $temp['Flag_Finish'] = $item->Flag_Finish;
            $temp['Flag_Finish_Requester'] = $item->Flag_Finish_Requester;
            $temp['Flag_Finish_Reciepent'] = $item->Flag_Finish_Reciepent;

            $data[] = $temp;
        }

        // dd($data);
        // dd($data);
        return view('ticket.finishTicket', ['title' => $title, 'data' => $data, 'divisions' => $divisi]);
    }

    public function storeTicket(Request $request)
    {
        try {
            DB::beginTransaction();
            $from = $request->input('from');
            // dd($request->all());
            $id = Hashids::connection('custom')->decode($request->input('id_ticket'))[0];
            // Insert into Submit_Ticket_Log
            SubmitTicketLog::create([
                'Date' => Carbon::now()->format('Y-m-d'),
                'Time' => Carbon::now()->format('H:i:s'),
                'Note' => $request->keterangan,
                'Ticket_Id' => $id,
                'Id_User' => Auth::user()->Id_Users,
                'Status_Note' => $request->input('status_note'),
            ]);

            // Update KPI_Ticket_Response
            if ($from == 'requester') {
                if ($request->input('revisi') == 'revisi') {

                    ReponseTicket::where('Ticket_Id', $id)
                        ->update([
                            'Flag_Finish_Reciepent' => null,
                        ]);

                    $ticket = Ticket::where('Id_Ticket', $id)->get();
                    // dd($ticket);
                    $UserNotif = User::where('Id_Users', $ticket[0]->Recipient_Id)->get();
                    $userKasihTiket = Auth::user()->Nama;

                    if ($UserNotif) {

                        $data = [
                            'messaging_product' => 'whatsapp',
                            'to' => $UserNotif[0]->No_Hp,
                            'type' => 'template',
                            'template' => [
                                'name' => 'tiket_reminder',
                                'language' => [
                                    'code' => 'id',
                                    'policy' => 'deterministic',
                                ],
                                'components' => [
                                    [
                                        'type' => 'body',
                                        'parameters' => [
                                            [
                                                'type' => 'text',
                                                'text' => $ticket[0]->No_Ticket,
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => 'Di Revisi',
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => $userKasihTiket.'(Requester)',
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => date('d-M-Y'),
                                            ],

                                        ],
                                    ],
                                ],
                            ],
                        ];

                        /* Pengiriman dipindah ke pintu terpusat: HTTP-nya dijalankan worker
                         * SETELAH commit. Sebelumnya tiga hal salah sekaligus di sini —
                         * getStatusCode() dipanggil pada ARRAY (send_message mengembalikan
                         * $response->json()), HTTP 10-20 detik menahan lock transaksi, dan
                         * WhatsApp yang bermasalah membatalkan data tiket yang sah. */
                        WhatsappLogHelper::sendAndLog(
                            $UserNotif[0]->karyawan->Kode_Karyawan ?? null,
                            $UserNotif[0]->Nama ?? $UserNotif[0]->karyawan->Nama ?? null,
                            $no_hp ?? $data['to'] ?? null,
                            $ticket[0]->No_Ticket ?? $id ?? null,
                            $data,
                        );
                    }

                } else {

                    $ticket = Ticket::where('Id_Ticket', $id)->get();
                    $UserNotif = User::where('Id_Users', $ticket[0]->Recipient_Id)->get();
                    $userKasihTiket = Auth::user()->Nama;
                    if ($UserNotif) {

                        $data = [
                            'messaging_product' => 'whatsapp',
                            'to' => $UserNotif[0]->No_Hp,
                            'type' => 'template',
                            'template' => [
                                'name' => 'tiket_reminder',
                                'language' => [
                                    'code' => 'id',
                                    'policy' => 'deterministic',
                                ],
                                'components' => [
                                    [
                                        'type' => 'body',
                                        'parameters' => [
                                            [
                                                'type' => 'text',
                                                'text' => $ticket[0]->No_Ticket,
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => 'Diselesaikan2',
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => $userKasihTiket,
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => date('d-M-Y'),
                                            ],

                                        ],
                                    ],
                                ],
                            ],
                        ];

                        /* Pengiriman dipindah ke pintu terpusat: HTTP-nya dijalankan worker
                         * SETELAH commit. Sebelumnya tiga hal salah sekaligus di sini —
                         * getStatusCode() dipanggil pada ARRAY (send_message mengembalikan
                         * $response->json()), HTTP 10-20 detik menahan lock transaksi, dan
                         * WhatsApp yang bermasalah membatalkan data tiket yang sah. */
                        WhatsappLogHelper::sendAndLog(
                            $UserNotif[0]->karyawan->Kode_Karyawan ?? null,
                            $UserNotif[0]->Nama ?? $UserNotif[0]->karyawan->Nama ?? null,
                            $no_hp ?? $data['to'] ?? null,
                            $ticket[0]->No_Ticket ?? $id ?? null,
                            $data,
                        );
                    }

                    ReponseTicket::where('Ticket_Id', $id)
                        ->update([
                            'Flag_Finish_Requester' => 'Y',
                            'Tanggal_Finish_Requester' => Carbon::now()->format('Y-m-d'),
                            'Jam_Finish_Requester' => Carbon::now()->format('H:i:s'),

                        ]);
                }
            } elseif ($from == 'reciepent') {
                ReponseTicket::where('Ticket_Id', $id)
                    ->update([
                        'Flag_Finish_Reciepent' => 'Y',
                        'Tanggal_Finish_Reciepent' => Carbon::now()->format('Y-m-d'),
                        'Jam_Finish_Reciepent' => Carbon::now()->format('H:i:s'),
                    ]);

                $ticket = Ticket::where('Id_Ticket', $id)->get();
                $UserNotif = User::where('Id_Users', $ticket[0]->Requester_Id)->get();
                $userKasihTiket = Auth::user()->Nama;
                if ($UserNotif) {

                    $data = [
                        'messaging_product' => 'whatsapp',
                        'to' => $UserNotif[0]->No_Hp,
                        'type' => 'template',
                        'template' => [
                            'name' => 'tiket_reminder',
                            'language' => [
                                'code' => 'id',
                                'policy' => 'deterministic',
                            ],
                            'components' => [
                                [
                                    'type' => 'body',
                                    'parameters' => [
                                        [
                                            'type' => 'text',
                                            'text' => $ticket[0]->No_Ticket,
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => 'Diselesaikan',
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => $userKasihTiket.'(Recipient)',
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => date('d-M-Y'),
                                        ],

                                    ],
                                ],
                            ],
                        ],
                    ];

                    /* Pengiriman dipindah ke pintu terpusat: HTTP-nya dijalankan worker
                     * SETELAH commit. Sebelumnya tiga hal salah sekaligus di sini —
                     * getStatusCode() dipanggil pada ARRAY (send_message mengembalikan
                     * $response->json()), HTTP 10-20 detik menahan lock transaksi, dan
                     * WhatsApp yang bermasalah membatalkan data tiket yang sah. */
                    WhatsappLogHelper::sendAndLog(
                        $UserNotif[0]->karyawan->Kode_Karyawan ?? null,
                        $UserNotif[0]->Nama ?? $UserNotif[0]->karyawan->Nama ?? null,
                        $no_hp ?? $data['to'] ?? null,
                        $ticket[0]->No_Ticket ?? $id ?? null,
                        $data,
                    );
                }

            } else {
                DB::rollback();
                Alert::error('Error', 'Terjadi kesalahan: '.$e->getMessage());

                return redirect()->back();
            }

            DB::commit();
            Alert::success('Success', 'Ticket Berhasil Difinalisasi');

            return redirect()->back();
        } catch (\Exception $e) {
            DB::rollback();
            Alert::error('Error', 'Terjadi kesalahan: '.$e->getMessage());

            return redirect()->back();
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function approveFinishTicket($id)
    {
        try {
            DB::beginTransaction();
            $data = Ticket::where('Id_Ticket', $id)->first();
            $data->Flag_Finish = 'Y';
            $data->save();

            $ticket = Ticket::where('Id_Ticket', $id)->get();
            $UserNotif = User::where('Id_Users', $ticket[0]->Recipient_Id)->get();
            if ($UserNotif) {

                $pesan = [
                    'messaging_product' => 'whatsapp',
                    'to' => $UserNotif[0]->No_Hp,
                    'type' => 'template',
                    'template' => [
                        'name' => 'tiket_reminder',
                        'language' => [
                            'code' => 'id',
                            'policy' => 'deterministic',
                        ],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $ticket[0]->No_Ticket,
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => 'Diselesaikan sepenuhnya',
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $userKasihTiket,
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => date('d-M-Y'),
                                    ],

                                ],
                            ],
                        ],
                    ],
                ];

                /* Pengiriman dipindah ke pintu terpusat: HTTP-nya dijalankan worker
                 * SETELAH commit. Sebelumnya tiga hal salah sekaligus di sini —
                 * getStatusCode() dipanggil pada ARRAY (send_message mengembalikan
                 * $response->json()), HTTP 10-20 detik menahan lock transaksi, dan
                 * WhatsApp yang bermasalah membatalkan data tiket yang sah. */
                WhatsappLogHelper::sendAndLog(
                    $UserNotif[0]->karyawan->Kode_Karyawan ?? null,
                    $UserNotif[0]->Nama ?? $UserNotif[0]->karyawan->Nama ?? null,
                    $no_hp ?? $pesan['to'] ?? null,
                    $ticket[0]->No_Ticket ?? $id ?? null,
                    $pesan,
                );
            }

            DB::commit();
            Alert::success('Success', 'Ticket Berhasil Diapprove');

            return redirect()->back();
        } catch (\Exception $e) {
            DB::rollback();
            Alert::error('Error', 'Terjadi kesalahan: '.$e->getMessage());

            return redirect()->back();
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function revisionFinishTicket(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $data = Ticket::where('Id_Ticket', $id)->first();
            if (! $data) {
                throw new \Exception('Ticket tidak ditemukan');
            }

            // Menyimpan log revisi ke database
            DB::table('KPI_Revision_Ticket_Log')->insert([
                'No_Ticket' => $data->No_Ticket,
                'Date' => now()->toDateString(),
                'Time' => now()->toTimeString(),
                'Deadline' => $request->deadline,
                'Description' => $request->keterangan,
                'Ticket_Id' => $id,
            ]);

            DB::table('KPI_Ticket_Response')
                ->where('Ticket_Id', $id)
                ->update(['Flag_Finish' => null]);

            $ticket = Ticket::where('Id_Ticket', $id)->get();
            $UserNotif = User::where('Id_Users', $ticket[0]->Recipient_Id)->get();
            if ($UserNotif) {

                $pesan = [
                    'messaging_product' => 'whatsapp',
                    'to' => $UserNotif[0]->No_Hp,
                    'type' => 'template',
                    'template' => [
                        'name' => 'tiket_reminder',
                        'language' => [
                            'code' => 'id',
                            'policy' => 'deterministic',
                        ],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $ticket[0]->No_Ticket,
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => 'DiRevisi ',
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $userKasihTiket.'(Requester)',
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => date('d-M-Y'),
                                    ],

                                ],
                            ],
                        ],
                    ],
                ];

                /* Pengiriman dipindah ke pintu terpusat: HTTP-nya dijalankan worker
                 * SETELAH commit. Sebelumnya tiga hal salah sekaligus di sini —
                 * getStatusCode() dipanggil pada ARRAY (send_message mengembalikan
                 * $response->json()), HTTP 10-20 detik menahan lock transaksi, dan
                 * WhatsApp yang bermasalah membatalkan data tiket yang sah. */
                WhatsappLogHelper::sendAndLog(
                    $UserNotif[0]->karyawan->Kode_Karyawan ?? null,
                    $UserNotif[0]->Nama ?? $UserNotif[0]->karyawan->Nama ?? null,
                    $no_hp ?? $pesan['to'] ?? null,
                    $ticket[0]->No_Ticket ?? $id ?? null,
                    $pesan,
                );
            }

            DB::commit();
            Alert::success('Success', 'Ticket Berhasil Direvisi');

            return redirect()->back();
        } catch (\Exception $e) {
            DB::rollback();
            Alert::error('Error', 'Terjadi kesalahan: '.$e->getMessage());

            return redirect()->back();
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function getPICTicket($id_temp)
    {
        try {
            $user = Auth()->user();
            // dd($user);
            $id = Hashids::connection('custom')->decode($id_temp)[0];
            // dd($id)
            $divisiUser = $user->Division_Id;
            $data_temp = User::select('Id_Users', 'Nama')->where([
                ['Id_Users', '!=', $user->Id_Users],
                ['Division_Id', '=', $id],
            ])->OrderBy('Nama')->get();
            // dd($data);
            $dataCase_temp = Cases::select('Id_Case', 'Name')->where([
                ['Division_Id', '=', $id],
            ])->OrderBy('Name')->get();

            $dataCase = [];
            foreach ($dataCase_temp as $item) {
                $temp = [];
                $temp['Id_Case'] = Hashids::connection('custom')->encode($item->Id_Case);
                $temp['Name'] = $item->Name;

                $dataCase[] = $temp;
            }
            $data = [];
            foreach ($data_temp as $item) {
                $temp = [];
                $temp['Id_Users'] = Hashids::connection('custom')->encode($item->Id_Users);
                $temp['Username'] = $item->Nama;

                $data[] = $temp;
            }

            if (! $data) {
                throw new \Exception('Ticket tidak ditemukan');
            }

            return response()->json([
                'data' => $data,
                'cases' => $dataCase,
            ]);

            // Menyimpan log revisi ke database

        } catch (\Throwable $e) {
            // dd("oke");
            Log::channel('ticket_log')->info('ticket_log', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'terjadi kesalahan',
            ]);
        }
    }

    public function simpanTicket(Request $request)
    {

        // $request->validate([
        //     'files' => 'required|array|min:1',
        //     'files.*' => 'file|mimes:jpg,jpeg,png,pdf,xlsx,xls|max:10240', // max 10MB per file
        // ]);
        // $UserNotif = User::where('Id_Users', 2)->get();

        // dd($UserNotif);
        // return response()->json([
        //     'pesan' => $request->all()
        // ]);

        try {
            // dd($request->all());
            DB::beginTransaction();

            if ($request->input('division_id') == '') {
                DB::rollBack();

                return response()->json([
                    'pesan' => 'Divisi harus di pilih',
                ]);
            }
            if ($request->input('pic_id') == '') {
                DB::rollBack();

                return response()->json([
                    'pesan' => 'PIC harus di pilih',
                ]);
            }
            if ($request->input('case_id') == '') {
                DB::rollBack();

                return response()->json([
                    'pesan' => 'Case harus di pilih',
                ]);
            }

            $format_tanggal_sekarang = date('y').'/'.date('m');

            $string = 'SELECT TOP 1  ';
            $string .= 'RIGHT(No_Ticket, 4) as angka FROM KPI_Ticket ';
            $string .= "WHERE LEFT(No_Ticket, 9) = 'TK-".$format_tanggal_sekarang."-'";
            $string .= 'order by RIGHT(No_Ticket,4) desc ';
            // dd($string);
            $check_last_faktur = DB::select($string);
            if (count($check_last_faktur) == 0) {
                $angka_terakhir = 1;
            } else {
                // dd("sini");
                $angka_terakhir = (int) $check_last_faktur[0]->angka + 1;
                // dd($angka_terakhir);
            }
            // dd($angka_terakhir);

            $init = 'TK'.'-'.date('y/m');
            $no_faktur = $init.'-'.sprintf('%04d', $angka_terakhir);
            // dd($no_faktur);
            $reciepentId = Hashids::connection('custom')->decode($request->pic_id)[0];
            $division_id_decode = Hashids::connection('custom')->decode($request->division_id)[0];
            $case_decode = Hashids::connection('custom')->decode($request->case_id)[0];
            $data = Ticket::create([
                'No_Ticket' => $no_faktur,
                'Date' => date('Y-m-d'),
                'Time' => date('H:i:s'),
                // 'Division_Id' => $division_id_decode,
                'Division_Id' => Auth::user()->Division_Id,
                'Recipient_Id' => $reciepentId,
                'Case_Id' => $case_decode,
                'Requester_Id' => Auth::user()->Id_Users,
                'Description' => $request->description,
            ]);

            // // Notif Whatsapp

            //     // get Recipent Data
            $UserNotif = User::where('Id_Users', $reciepentId)->get();
            $no_hp = $UserNotif[0]->No_Hp;
            $userKasihTiket = Auth::user()->Nama;
            // dd($UserNotif);
            if ($no_hp) {

                $data = [
                    'messaging_product' => 'whatsapp',
                    'to' => $no_hp,
                    'type' => 'template',
                    'template' => [
                        'name' => 'tiket_reminder_opening',
                        'language' => [
                            'code' => 'id',
                            'policy' => 'deterministic',
                        ],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $userKasihTiket.'(Requester)',
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $no_faktur,
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => date('d M Y'),
                                    ],

                                ],
                            ],
                        ],
                    ],
                ];

                /* Pengiriman dipindah ke pintu terpusat: HTTP-nya dijalankan worker
                 * SETELAH commit. Sebelumnya tiga hal salah sekaligus di sini —
                 * getStatusCode() dipanggil pada ARRAY (send_message mengembalikan
                 * $response->json()), HTTP 10-20 detik menahan lock transaksi, dan
                 * WhatsApp yang bermasalah membatalkan data tiket yang sah. */
                WhatsappLogHelper::sendAndLog(
                    $UserNotif[0]->karyawan->Kode_Karyawan ?? null,
                    $UserNotif[0]->Nama ?? $UserNotif[0]->karyawan->Nama ?? null,
                    $no_hp ?? $data['to'] ?? null,
                    $ticket[0]->No_Ticket ?? $id ?? null,
                    $data,
                );
            }

            // }

            // // Akhir Notif Whatsapp

            $id_ticket = Ticket::where('No_Ticket', $no_faktur)->first();

            ReponseTicket::create([
                'No_Ticket' => $no_faktur,
                'Ticket_Id' => $id_ticket->Id_Ticket,
            ]);

            $uploadedUrls = [];

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {

                    // Simpan file ke storage/app/public/uploads
                    $path = $file->store('ticket', 'public');

                    // Buat URL file-nya
                    $url = Storage::url($path); // hasilnya: /storage/uploads/namafile.pdf

                    // Simpan ke database (kalau perlu)
                    TicketFile::insert([
                        'No_Ticket' => $no_faktur,
                        'Path_File' => $url,
                        'Nama_File' => $file->getClientOriginalName(),
                        'Ticket_Id' => $id_ticket->Id_Ticket,
                        'Mime' => $file->getClientMimeType(),
                        'Size' => $file->getSize(),
                    ]);

                    // $uploadedUrls[] = $url;
                }
            }

            DB::commit();
            Alert::success('Success', 'Ticket Berhasil Disimpan');

            return redirect()->back();
        } catch (\Throwable $e) {
            DB::rollback();
            Log::channel('ticket_log')->info('ticket_log', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('pesan', 'Terjadi kesalahan1');
        }
    }

    public function display_review_ticket($id_ticket_temp)
    {
        $id_ticket = Hashids::connection('custom')->decode($id_ticket_temp)[0];

        $user = Auth()->user();

        $divisiUser = $user->Division_Id;

        $ticket = Ticket::where('Id_Ticket', $id_ticket)->first();

        // $ticket = Ticket::where('Id_Ticket', $id)->get();
        $UserNotif = User::where('Id_Users', $ticket->Recipient_Id)->get();
        $userKasihTiket = Auth::user()->Nama;
        if ($UserNotif) {

            $data = [
                'messaging_product' => 'whatsapp',
                'to' => $UserNotif[0]->No_Hp,
                'type' => 'template',
                'template' => [
                    'name' => 'tiket_reminder',
                    'language' => [
                        'code' => 'id',
                        'policy' => 'deterministic',
                    ],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $ticket->No_Ticket,
                                ],
                                [
                                    'type' => 'text',
                                    'text' => 'Diselesaikan Sepenuhnya',
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $userKasihTiket.'(Requester)',
                                ],

                                [
                                    'type' => 'text',
                                    'text' => date('d-M-Y'),
                                ],

                            ],
                        ],
                    ],
                ],
            ];

            /* Pengiriman dipindah ke pintu terpusat: HTTP-nya dijalankan worker
             * SETELAH commit. Sebelumnya tiga hal salah sekaligus di sini —
             * getStatusCode() dipanggil pada ARRAY (send_message mengembalikan
             * $response->json()), HTTP 10-20 detik menahan lock transaksi, dan
             * WhatsApp yang bermasalah membatalkan data tiket yang sah. */
            WhatsappLogHelper::sendAndLog(
                $UserNotif[0]->karyawan->Kode_Karyawan ?? null,
                $UserNotif[0]->Nama ?? $UserNotif[0]->karyawan->Nama ?? null,
                $no_hp ?? $data['to'] ?? null,
                $ticket[0]->No_Ticket ?? $id ?? null,
                $data,
            );
        }

        $query = "select Id_Evaluation,Name from KPI_Evaluations where Case_Id = :caseid and flag_active = 'Y' order by Name asc ";
        $data = DB::select($query, [
            'caseid' => $ticket->Case_Id,
        ]);

        // dd($data);

        return inertia('ESS/Workflow/Ticket/TicketReview', [
            'data' => $data,
            'no_ticket' => $ticket->No_Ticket,
            'Ticket_Id' => $id_ticket,
            // 'dataDivisi' => $dataDivisi->toArray() // Pastikan data diubah menjadi array
        ]);
    }

    public function simpan_review_ticket(Request $request)
    {

        // Validate the request
        // $request->validate([
        //     'ratings' => 'required|array'
        // ]);

        $currentDate = Carbon::now();
        $user = Auth::user();
        $assessorId = $user->Id_Users;

        $ratings = $request->input('ratings');

        try {
            // Get all ratings data
            DB::beginTransaction();

            $ratings = $request->input('ratings');

            $data = [];
            foreach ($ratings as $criteriaId => $score) {
                $data[] = [
                    // "No_Ticket" => $request->input("no_ticket"),
                    'Score' => $score,
                    'Evaluation_Id' => $criteriaId,
                    'Ticket_Id' => $request->input('id_ticket'),

                ];
            }

            TicketDetailReview::insert($data);

            Ticket::where('No_Ticket', $request->input('no_ticket'))->update([
                'Flag_Finish' => 'Y',
                'Tanggal_Selesai' => $currentDate->toDateString(),
                'Time' => $currentDate->format('H:i:s'),
            ]);

            ReponseTicket::where('No_Ticket', $request->input('no_ticket'))->update(
                [
                    'Flag_Finish_Requester' => 'Y',
                    'Tanggal_Finish_Requester' => $currentDate->toDateString(),
                    'Jam_Finish_Requester' => $currentDate->format('H:i:s'),
                    'Flag_Finish' => 'Y',
                ]
            );

            // SubmitTicketLog::create([
            //     'Date' => Carbon::now()->format('Y-m-d'),
            //     'Time' => Carbon::now()->format('H:i:s'),
            //     'Note' => "Finish",
            //     'Ticket_Id' => $id,
            //     "Id_User" => Auth::user()->Id_Users,
            //     'Status_Note' => $status_text
            // ]);

            DB::commit();

            return response()->json([
                'pesan' => 'Data Berhasil disimpan',
            ]);
        } catch (\Throwable $e) {
            /* ROLLBACK YANG SELAMA INI HILANG.
               Transaksi dibuka di awal metode ini dan hanya di-commit pada
               jalur sukses; pada galat ia dibiarkan TERBUKA sepanjang sisa
               request, menahan lock yang didapatnya. Basis data ini berjalan
               tanpa RCSI, jadi selama itu pembaca tabel yang sama ikut antre. */
            DB::rollBack();

            /* Log::info untuk sebuah KEGAGALAN membuatnya tenggelam di antara
               catatan biasa, dan getMessage() saja membuang baris asalnya. */
            Log::error($e->getMessage(), ['exception' => $e]);

            return response()->json([
                'message' => 'Gagal menyimpan data',
                'error' => 'An error occurred while saving the review. Please try again',
            ], 500); // Penting: set status 500 agar masuk onError
        }
    }

    public function display_detail_ticket($id_ticket_temp, $from)
    {
        $user = Auth()->user();

        // $sql = "select b.Flag_Approve,b.date, b.flag_finish_Reciepent,b.Tanggal_Finish_Reciepent , b.flag_finish_Requester, b.Tanggal_Finish_Requester, a.Flag_Finish ";
        // $sql .= "from KPI_Ticket a,  KPI_Ticket_Response b where a.No_Ticket = b.No_Ticket and id_ticket = :id_ticket ";

        // $data = DB::select($sql, [
        //     'id_ticket' => $id_ticket
        // ]);
        $id_ticket = Hashids::connection('custom')->decode($id_ticket_temp)[0];
        $sql = 'select title,title2 From View_KPI_Tracking_Ticket  where id_ticket = :id_ticket ';

        $data = DB::select($sql, ['id_ticket' => $id_ticket]);

        $data1 = [];
        foreach ($data as $item) {
            $temp = [];

            $temp['title'] = $item->title;
            $temp['title2'] = date('d M Y', strtotime($item->title2));

            $data1[] = $temp;
        }
        // dd($data);

        $string2 = 'select a.Id_Ticket,a.No_Ticket, b.Description as Divisi,d.Tanggal_Estimasi_Selesai, c.Description as Keterangan_Case, a.Date as Tanggal_Pengajuan,  ';
        $string2 .= "isnull((select x.Nama From KPI_Users x where x.Id_Users = a.Requester_Id ), '-') as Requester, ";
        $string2 .= "isnull((select x.Nama From KPI_Users x where x.Id_Users = a.Recipient_Id ), '-') as Reciepent ";
        $string2 .= 'From KPI_Ticket a,KPI_Divisions b, KPI_Cases c,KPI_Ticket_Response d ';
        $string2 .= 'where a.Division_Id = b.Id_Division and a.Case_Id = c.Id_Case  and a.Case_Id = c.Id_Case  and a.Id_Ticket = d.Ticket_Id ';
        $string2 .= 'and a.id_ticket = :id_ticket ';

        $dataInformation_temp = DB::select($string2, ['id_ticket' => $id_ticket]);
        // dd($dataInformation_temp);
        $dataInformation = [];

        /*
         * Lampiran diambil SEKALI untuk seluruh tiket, lalu dikelompokkan per
         * Ticket_Id. Sebelumnya satu kueri per tiket pada daftar riwayat.
         */
        $berkasPerTiket = TicketFile::whereIn('Ticket_Id', $dataInformation_temp->pluck('Id_Ticket')->all())
            ->get()
            ->groupBy('Ticket_Id');

        /*
         * Log tiket ikut diambil sekali, seperti lampirannya. Relasi users
         * sudah eager sejak semula; yang berulang adalah kueri log itu sendiri,
         * satu per tiket pada daftar riwayat.
         */
        $logPerTiket = SubmitTicketLog::with('users')
            ->whereIn('Ticket_Id', $dataInformation_temp->pluck('Id_Ticket')->all())
            ->get()
            ->groupBy('Ticket_Id');

        foreach ($dataInformation_temp as $item) {
            $temp = [];
            $temp['no_ticket'] = $item->No_Ticket;
            $temp['divisi'] = $item->Divisi;
            $temp['keterangan_case'] = $item->Keterangan_Case;
            $temp['tanggal_pengajuan'] = date('d M Y', strtotime($item->Tanggal_Pengajuan));
            $temp['requester'] = $item->Requester;
            $temp['reciepent'] = $item->Reciepent;
            $temp['tanggal_estimasi'] = $item->Tanggal_Estimasi_Selesai !== null ? date('d M Y', strtotime($item->Tanggal_Estimasi_Selesai)) : '-';
            $temp['log'] = [];
            // dd($item->Id_Ticket_Response);
            $log_temp = $logPerTiket[$item->Id_Ticket] ?? collect();
            // dd($log_temp);
            foreach ($log_temp as $item2) {
                // dd($item2);
                array_push($temp['log'], [
                    'keterangan' => $item2->Note,
                    'date' => date('d M Y', strtotime($item2->Date)),
                    'time' => $item2->Time,
                    'pic' => $item2->users->Username,
                    'status_note' => $item2->Status_Note,
                ]);
            }

            $temp['files'] = [];

            $files = $berkasPerTiket[$item->Id_Ticket] ?? collect();
            foreach ($files as $item3) {
                // dd($item2);
                array_push($temp['files'], [
                    'path' => $item3->Path_File,
                    'nama_file' => $item3->Nama_File,
                    'mime' => $item3->Mime,
                ]);
            }
            // // dd($files);
            // TicketDetailReview::where

            /* Kueri SubmitTicketLog per iterasi dibuang: hasilnya tidak pernah
               dibaca, dan $id_ticket pun tidak berubah sepanjang perulangan. */

            $dataInformation[] = $temp;
        }
        // dd($dataInformation);

        return inertia('ESS/Workflow/Ticket/DetailTicket', [
            'data' => $data1,
            'datainformation' => $dataInformation,
            'from' => $from,
            // 'dataDivisi' => $dataDivisi->toArray() // Pastikan data diubah menjadi array
        ]);
    }
}
