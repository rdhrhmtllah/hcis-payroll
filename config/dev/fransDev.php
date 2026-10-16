<?php

return [
    'hclearn_api' => [
        'domain' => env('HCLEARN_API_DOMAIN', 'https://hclearn.evonusabersaudara.co.id'),
        'public' => env('HCLEARN_API_PUBLIC'),
        'secret' => env('HCLEARN_API_SECRET'),

        /*
         | Akun layanan HC Learn — DUA MODUL, bukan hanya SOP.
         |
         | SOP (lapis 2 rantai identitas kuis): dipakai saat penjadwalan tak punya
         | pembuat yang sah — assign otomatis, resertifikasi, pendaftaran mandiri
         | SOP publik, atau admin yang sudah nonaktif.
         |
         | PA (`PaHcLearnJadwalContract::identitas()`): dipakai setiap kali tidak ada
         | sesi sama sekali — `pa:jadwalkan-penilaian` dan `pa:tarik-jadwal` dari
         | terminal atau penjadwal. Tanpa keduanya terisi, perintah itu berhenti
         | sebelum mengirim apa pun.
         |
         | Keduanya WAJIB pasangan yang dikenal HC Learn — X-App-ID dan
         | X-Kode-Karyawan diverifikasi bersama di sana, bukan sendiri-sendiri.
         */
        'service_user_key' => env('SOP_HCLEARN_USER_KEY'),
        'service_kode_users' => env('SOP_HCLEARN_KODE_USERS'),
    ],
];
