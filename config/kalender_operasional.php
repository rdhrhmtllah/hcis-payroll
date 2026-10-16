<?php

/*
|--------------------------------------------------------------------------
| Kalender Operasional
|--------------------------------------------------------------------------
|
| Dipakai API kalender operasional (docs/superpowers/specs/
| 2026-09-04-api-kalender-operasional-ho-design.md).
|
| Penanda unit sengaja ditaruh di sini, bukan di kode: nilai
| Karyawan.Cabang_Untuk_Gaji di master masih perlu dibersihkan, dan polanya
| harus bisa disesuaikan tanpa deploy ulang. Menambah unit baru cukup
| menambah satu entri di bawah — tidak ada kode yang perlu berubah.
|
*/

return [
    'batas_rentang_hari' => 400,

    'tahun_minimal' => 2020,

    'tahun_maksimal' => 2100,

    // Master libur berubah beberapa kali setahun, jadi TTL pendek sudah cukup
    // dan tidak menimbulkan kelas bug "kalender basi".
    'cache_ttl_detik' => 900,

    'unit' => [
        'HEAD_OFFICE' => [
            // 1 = Senin .. 6 = Sabtu, 0 = Minggu.
            'hari_kerja_mingguan' => [1, 2, 3, 4, 5, 6],

            // Dicocokkan LIKE ke Karyawan.Cabang_Untuk_Gaji.
            'pola_cabang' => ['%HEAD OFFICE%'],
        ],
    ],
];
