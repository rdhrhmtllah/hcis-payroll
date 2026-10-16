<?php

namespace Database\Seeders;

use App\Helpers\Whatsapp;
use App\Models\ESS\Employee\Models\Karyawan;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
// This model is not directly used but imported, keep if needed elsewhere.
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Users2 extends Seeder
{
    public function run()
    {

        Log::channel('CreateUserLog')->error('['.Carbon::now().'] Proses pemberian user dan akses dimulai.........');

        $usernames = [
            'LIUS',

        ];
        $karyawanCodes = [
            'LIUS',
        ];
        $specialAccess = [
            'LIUS',
        ];

        $karyawanData = Karyawan::whereNull('Tanggal_Resign')
            ->whereIn('Kode_Karyawan', $karyawanCodes)
            ->get()
            ->sortBy(function ($karyawan) use ($karyawanCodes) {
                return array_search($karyawan->Kode_Karyawan, $karyawanCodes);
            })
            ->values();

        if (count($usernames) !== count($karyawanData)) {
            $this->command->error('Jumlah username ('.count($usernames).') dan data karyawan ('.count($karyawanData).') tidak sama. Proses dihentikan.');

            return;
        }

        $saltFront = env('SALT_FRONT');
        $saltBack = env('SALT_BACK');
        $passwordList = [];

        for ($i = 0; $i < count($usernames); $i++) {
            $username = $usernames[$i];
            $karyawan = $karyawanData[$i];
            $cleanUsername = $this->cleanName($username);
            $cleanPasswordPart = $this->cleanName($username);
            $randomNumber = str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
            $rawPassword = $cleanPasswordPart.'_'.$randomNumber;
            $passwordWithSalt = $saltFront.$rawPassword.$saltBack;
            $encryptedPassword = Hash::make($passwordWithSalt);

            $divisionId = $karyawan->division ? $karyawan->division->ID_Divisi : null;
            $levelId = $karyawan->level ? $karyawan->level->ID_Level : null;

            if (! $divisionId || ! $levelId) {
                $this->command->warn("Karyawan {$karyawan->Kode_Karyawan} tidak memiliki Division atau Level.");

                continue;
            }

            // Cek apakah user sudah ada
            $existingUser = DB::table('KPI_Users')
                ->where('Kode_Users', $karyawan->Kode_Karyawan)
                ->first();

            $userId = null;

            DB::beginTransaction();
            try {
                if (! $existingUser) {
                    // INSERT user baru
                    $userId = DB::table('KPI_Users')->insertGetId([
                        'Username' => $cleanUsername,
                        'Password' => $encryptedPassword,
                        'Email' => $karyawan->Email,
                        'Role' => 'user',
                        'Flag_Active' => 'Y',
                        'Kode_Users' => $karyawan->Kode_Karyawan,
                        'Address' => $karyawan->Alamat,
                        'Division_Id' => $divisionId,
                        'Level_Id' => $levelId,
                        'Nama' => $karyawan->Nama,
                        'No_Hp' => $karyawan->HP,
                    ]);

                    $karyawan->UserID_Web = $userId;
                    $karyawan->save();

                    // Notifikasi WA
                    // $pesan = [
                    //     "messaging_product" => "whatsapp",
                    //     "to" => $karyawan->HP,
                    //     "type" => "template",
                    //     "template" => [
                    //         "name" => "notif_web_hc_new",
                    //         "language" => ["code" => "en", "policy" => "deterministic"],
                    //         "components" => [
                    //             [
                    //                 "type" => "body",
                    //                 "parameters" => [
                    //                     ["type" => "text", "text" => $cleanUsername],
                    //                     ["type" => "text", "text" => $karyawan->Nama],
                    //                     ["type" => "text", "text" => $rawPassword],
                    //                 ]
                    //             ]
                    //         ]
                    //     ]
                    // ];
                    // $response = Whatsapp::send_message($pesan);
                    // Log::channel('whatsapp_error')->warning('WA Response', ['pesan' => $response]);

                    $passwordList[] = [
                        'Username' => $cleanUsername,
                        'Nama' => $karyawan->Nama,
                        'Raw_Password' => $rawPassword,
                    ];

                    $this->command->info("User {$karyawan->Kode_Karyawan} dibuat.");
                    Log::channel('CreateUserLog')->info('['.Carbon::now()."] User {$karyawan->Kode_Karyawan} ({$cleanUsername}) dibuat dan ditambahkan ke KPI_Users.");
                } else {
                    $userId = $existingUser->Id_Users;
                    $this->command->info("User {$karyawan->Kode_Karyawan} sudah ada, akan diberi akses.");
                    Log::channel('CreateUserLog')->info('['.Carbon::now()."] User {$karyawan->Kode_Karyawan} sudah ada. Melanjutkan pemberian akses.");

                }

                // Beri akses (umum)
                $izinPageExists = DB::table('HRIS_Page_Access')
                    ->where('UserID_Web', $userId)
                    ->where('Jenis_Page', 'IzinPage')
                    ->exists();

                if (! $izinPageExists) {
                    DB::table('HRIS_Page_Access')->insert([
                        'Kode_Perusahaan' => '001',
                        'ID_Level' => $levelId,
                        'ID_Divisi' => $divisionId,
                        'Jenis_Page' => 'IzinPage',
                        'UserID_Web' => $userId,
                        'Flag_Aktif' => 'Y',
                        'Created_At' => now(),
                        'Updated_At' => now(),
                    ]);
                    $this->command->info("Akses IzinPage diberikan ke {$karyawan->Kode_Karyawan}");
                    Log::channel('CreateUserLog')->info('['.Carbon::now()."] Akses IzinPage diberikan ke {$karyawan->Kode_Karyawan}.");

                }

                $overtimeManagementExists = DB::table('HRIS_Page_Access')
                    ->where('UserID_Web', $userId)
                    ->where('Jenis_Page', 'overtimeManagement')
                    ->exists();

                if (! $overtimeManagementExists) {
                    DB::table('HRIS_Page_Access')->insert([
                        'Kode_Perusahaan' => '001',
                        'ID_Level' => $levelId,
                        'ID_Divisi' => $divisionId,
                        'Jenis_Page' => 'overtimeManagement',
                        'UserID_Web' => $userId,
                        'Flag_Aktif' => 'Y',
                        'Created_At' => now(),
                        'Updated_At' => now(),
                    ]);
                    $this->command->info("Akses IzinPage diberikan ke {$karyawan->Kode_Karyawan}");
                    Log::channel('CreateUserLog')->info('['.Carbon::now()."] Akses IzinPage diberikan ke {$karyawan->Kode_Karyawan}.");

                }
                // $shiftManagementExists = DB::table('HRIS_Page_Access')
                //     ->where('UserID_Web', $userId)
                //     ->where('Jenis_Page', 'shiftManagement')
                //     ->exists();

                // if (!$shiftManagementExists) {
                //     DB::table('HRIS_Page_Access')->insert([
                //         'Kode_Perusahaan' => '001',
                //         'ID_Level' => $levelId,
                //         'ID_Divisi' => $divisionId,
                //         'Jenis_Page' => 'shiftManagement',
                //         'UserID_Web' => $userId,
                //     ]);
                //     $this->command->info("Akses IzinPage diberikan ke {$karyawan->Kode_Karyawan}");
                //     Log::channel('CreateUserLog')->info("[" . Carbon::now() . "] Akses IzinPage diberikan ke {$karyawan->Kode_Karyawan}.");

                // }
                // $shiftManagementExists = DB::table('HRIS_Page_Access')
                //     ->where('UserID_Web', $userId)
                //     ->where('Jenis_Page', 'shiftManagement')
                //     ->exists();

                // if (!$shiftManagementExists) {
                //     DB::table('HRIS_Page_Access')->insert([
                //         'Kode_Perusahaan' => '001',
                //         'ID_Level' => $levelId,
                //         'ID_Divisi' => $divisionId,
                //         'Jenis_Page' => 'shiftManagement',
                //         'UserID_Web' => $userId,
                //     ]);
                //     $this->command->info("Akses IzinPage diberikan ke {$karyawan->Kode_Karyawan}");
                //     Log::channel('CreateUserLog')->info("[" . Carbon::now() . "] Akses IzinPage diberikan ke {$karyawan->Kode_Karyawan}.");

                // }

                // Beri akses approver jika termasuk special
                if (in_array($karyawan->Kode_Karyawan, $specialAccess)) {
                    $approverPageExists = DB::table('HRIS_Page_Access')
                        ->where('UserID_Web', $userId)
                        ->where('Flag_Aktif', 'Y')
                        ->where('Jenis_Page', 'IzinPageApprover')
                        ->exists();

                    if (! $approverPageExists) {
                        DB::table('HRIS_Page_Access')->insert([
                            'Kode_Perusahaan' => '001',
                            'ID_Level' => $levelId,
                            'ID_Divisi' => $divisionId,
                            'Flag_Aktif' => 'Y',
                            'Created_At' => now(),
                            'Updated_At' => now(),
                            'Jenis_Page' => 'IzinPageApprover',
                            'UserID_Web' => $userId,
                        ]);
                        $this->command->info("Akses Approver diberikan ke {$karyawan->Kode_Karyawan}");
                        Log::channel('CreateUserLog')->info('['.Carbon::now()."] Akses Approver (IzinPageApprover) diberikan ke {$karyawan->Kode_Karyawan}.");

                    }
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollback();
                $this->command->error("Error pada {$karyawan->Kode_Karyawan}: ".$e->getMessage());
                Log::channel('CreateUserLog')->error('['.Carbon::now()."] ERROR pada {$karyawan->Kode_Karyawan}: ".$e->getMessage());

            }
        }

        $this->exportPasswordList($passwordList);
        $this->command->info('Proses selesai.');
        Log::channel('CreateUserLog')->error('['.Carbon::now().'] Proses selesai');

    }

    protected function cleanName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^a-zA-Z0-9\s]/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name);
        $name = str_replace(' ', '', $name);

        return $name;
    }

    protected function exportPasswordList($list)
    {
        if (empty($list)) {
            $this->command->info('No users generated to export passwords.');

            return;
        }

        $fileName = 'user_passwords_'.now()->format('Ymd_His').'.csv';
        $headers = array_keys($list[0]);
        $csvContent = implode(',', $headers)."\n";

        foreach ($list as $row) {
            $csvContent .= implode(',', array_map(function ($value) {
                return '"'.str_replace('"', '""', $value).'"';
            }, $row))."\n";
        }

        Storage::disk('local')->put($fileName, $csvContent);

        $this->command->info("Password list exported to: storage/app/{$fileName}");
    }
}
