<?php

namespace Database\Seeders;

use App\Helpers\Whatsapp;
use App\Models\ESS\Employee\Models\Karyawan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class fiturHCIS extends Seeder
{
    public function run()
    {
        // Kode Karyawan yang sesuai dengan urutan usernames.
        // Penting: Pastikan urutan dan jumlah elemen di sini cocok dengan $usernames
        $karyawanCodes = [
            'AKONG',
            'ALFAN',
            'ALFIAN KURNIA',
            'ALFIN FIRDAUS',
            'ALIFLARIK',
            'AN NAUFAL',
            'ANGGA SURYA',
            'ARIYANTO',
            'ASTRID',
            'DADI ISWARA',
            'DANI RAMDANI',
            'FADHILAH',
            'FAJAR',
            'FARID',
            'FARIZAL',
            'FAUZAN',
            'HANSEN',
            'ILHAM ADITYA',
            'ISMAIL',
            'KAMALLUDIN',
            'LINGGAR',
            'LISKA',
            'M LUTHFI',
            'M. IRWANSYAH',
            'MANSYUR M',
            'MARDIAN',
            'MUNADHIL',
            'NAFIUR',
            'PUTU WIRA',
            'REKA SETIAWAN',
            'RIFAATUL',
            'RINI NOOR',
            'SYAIFUL',
            'TANHAR MAHARSI',
            'TAUFIK SAPUTRA',
            'TEGUH',
            'WILI NANDA',
            'WILSON',
            'YOGA ADHITIA',
            'YUSA',
            'ZAINAL',
        ];

        // Ambil data karyawan berdasarkan $karyawanCodes dan urutkan hasilnya agar sesuai dengan urutan $karyawanCodes.
        // Ini adalah langkah KRITIS untuk memastikan pencocokan 1-ke-1.
        $karyawanData = Karyawan::where('Tanggal_Resign', null)->whereIn('Kode_Karyawan', $karyawanCodes)->get();

        // Loop tunggal untuk mencocokkan berdasarkan indeks
        for ($i = 0; $i < count($karyawanCodes); $i++) {
            $karyawan = $karyawanData[$i];

            $tableUser = DB::table('KPI_Users')->where('Kode_Users', $karyawan->Kode_Karyawan)->exists();
            if (! $tableUser) {
                $this->command->warn("Skip {$karyawan->Nama} Karena Tidak ada Di tabel KPI_Users");

                continue; // Lanjutkan ke iterasi berikutnya
            }

            $divisionId = $karyawan->division ? $karyawan->division->ID_Divisi : null;
            $levelId = $karyawan->level ? $karyawan->level->ID_Level : null;
            $UserID_Web = $karyawan->UserID_Web ?? null;
            $HP = $karyawan->HP ?? null;
            $namaKaryawan = $karyawan->Nama ?? null;
            // --- Pengecekan Duplikasi Username yang Sudah di-clean ---
            // Gunakan $cleanUsername atau $karyawan->Kode_Karyawan untuk pengecekan, tergantung kebutuhan
            $usernameExists = DB::table('HRIS_Page_Access')
                ->where('UserID_Web', $UserID_Web) // Cek berdasarkan Username yang sudah di-clean

                ->where('Flag_Aktif', 'Y')
                ->where('Jenis_page', 'absensiPage')
                ->exists();

            if ($usernameExists) {
                $this->command->warn(
                    "Skipping user {$karyawan->Kode_Karyawan} (Nama: {$karyawan->Nama} because a similar USERID_WEB already exists in HRIS_PAGE_ACCEESS table.",
                );
            } else {
                if ($divisionId !== null && $levelId !== null && $UserID_Web !== null) {
                    DB::transaction(function () use (

                        $divisionId,
                        $levelId,
                        $UserID_Web,
                    ) {
                        try {
                            DB::table('HRIS_Page_Access')->Insert([
                                'Kode_Perusahaan' => '001',
                                'ID_Level' => $levelId,
                                'ID_Divisi' => $divisionId,
                                'Jenis_Page' => 'absensiPage',
                                'UserID_Web' => $UserID_Web,
                                'Created_At' => Carbon::now()->format('Y-m-d H:i:s'),
                                'Updated_At' => Carbon::now()->format('Y-m-d H:i:s'),
                                'Flag_Aktif' => 'Y',
                            ]);

                            $this->command->info('User Berhasil di buat dan berhasil di beri notifikasi.');
                        } catch (\Exception $e) {
                            $this->command->error('Gagal Membuat akses page absen karena : '.$e->getMessage());
                        }
                    });
                } else {
                    $this->command->warn(
                        "Skipping user {$karyawan->Kode_Karyawan} (Nama: {$karyawan->Nama}) due to missing Division_Id or Level_Id.",
                    );
                }
            }

            // --- Pengecekan Duplikasi Selesai ---

            if ($HP == null) {
                $this->command->warn("{$karyawan->Kode_Karyawan} Tidak Memiliki No HP");
            }

            try {
                $pesan = [
                    'messaging_product' => 'whatsapp',
                    'to' => $HP,
                    'type' => 'template',
                    'template' => [
                        'name' => 'pakai_absen',
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
                                        'text' => $namaKaryawan,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];

                $response = Whatsapp::send_message($pesan);

                // Log the WhatsApp API response for debugging or monitoring
                Log::channel('whatsapp_error')->warning('Pesan Error', [
                    'pesan' => $response,
                ]);
            } catch (\Throwable $e) {
                $this->command->warn("Gagal mengirim wa ke {$karyawan->Kode_Karyawan} karena :".$e->getMessage());
            }
        }

        $this->command->info('Users Page creating process completed!');
    }

    /**
     * Membersihkan dan menormalisasi nama/kode karyawan.
     *
     * @param  string  $name
     * @return string
     */
}
