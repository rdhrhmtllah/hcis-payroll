<?php

namespace App\Services\ESS\Attendance\Leave\Shared;

use Carbon\Carbon;

/**
 * Satu-satunya penerjemah "item cuti bersama" menjadi daftar tanggal.
 *
 * ── Kenapa ini harus punya satu rumah ────────────────────────────────────────
 *
 * Sebelum kelas ini ada, aturannya tersalin BYTE-PER-BYTE di tiga controller —
 * CutiController, AbsensiController, dan uDashController (37 baris, sidik isi
 * identik; satu-satunya beda adalah nama metodenya). Tiga salinan berarti
 * perbaikan pada satu tempat tidak pernah sampai ke dua lainnya.
 *
 * Itu bukan kekhawatiran teoretis untuk logika INI. Himpunan tanggal cuti sudah
 * pernah melahirkan cacat nyata: audit menemukan pengajuan bisa melebihi jatah
 * karena pemeriksaannya mengabaikan `selected_dates` dan hanya membaca rentang
 * mulai–selesai. Perbaikan seperti itu, dilakukan di satu salinan, meninggalkan
 * dua jalur yang masih salah — dan keduanya tampak benar saat dibaca.
 *
 * ── Dua bentuk yang sama-sama sah ────────────────────────────────────────────
 *
 * Sebuah item cuti bersama boleh menyebut tanggalnya dengan dua cara:
 *
 *   1. `selected_dates` — daftar tanggal yang dipilih satu per satu. Bentuk ini
 *      MENANG bila ada, karena ia lebih spesifik daripada rentang.
 *   2. `tanggal_mulai` .. `tanggal_selesai` — rentang; tanggal_selesai yang
 *      kosong berarti satu hari saja.
 *
 * Tanggal yang tak bisa diurai DIBUANG diam-diam, bukan menggagalkan seluruh
 * pemanggilan: data ini datang dari konfigurasi yang disunting manusia, dan satu
 * baris rusak tidak boleh menghapus seluruh kalender cuti bersama seseorang.
 *
 * ── Satu implementasi lain yang SENGAJA dibiarkan berdiri sendiri ────────────
 *
 * PenentuTanggalTidakOperasional::tanggalCutiBersama() menegakkan aturan yang
 * sama tetapi dengan kontrak masukan berbeda: di sana `selected_dates` bisa tiba
 * sebagai STRING JSON langsung dari kolom basis data. Kelas ini hanya menerima
 * array yang sudah ter-decode — string JSON diabaikan dan hasilnya jatuh ke
 * rentang.
 *
 * Menyatukannya berarti mengubah perilaku salah satu jalur pada logika kuota
 * cuti, yang sudah pernah melahirkan cacat overbooking. Itu keputusan produk,
 * bukan perapian kode.
 */
class TanggalCutiBersama
{
    /**
     * Daftar tanggal (Y-m-d) milik satu item cuti bersama — terurut dan unik.
     *
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    public static function dariItem(array $item): array
    {
        $selectedDates = $item['selected_dates'] ?? [];
        if (is_array($selectedDates) && ! empty($selectedDates)) {
            return collect($selectedDates)
                ->map(function ($date) {
                    /* Entri kosong dibuang SEBELUM diurai, dan ini memperbaiki
                       cacat yang terbawa dari ketiga salinan lama.
                       Carbon::parse(null), parse('') dan parse(' ') tidak
                       melempar — ketiganya mengembalikan HARI INI. Akibatnya
                       satu entri kosong di selected_dates diam-diam menjadi
                       tanggal cuti hari ini, lalu ikut terhitung sebagai
                       pemakaian jatah. Teks yang benar-benar tak terurai
                       (mis. 'bukan-tanggal') memang melempar dan sudah
                       tertangani di bawah. */
                    if ($date === null || trim((string) $date) === '') {
                        return null;
                    }

                    try {
                        return Carbon::parse($date)->toDateString();
                    } catch (\Throwable $e) {
                        return null;
                    }
                })
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        if (empty($item['tanggal_mulai'])) {
            return [];
        }

        try {
            $start = Carbon::parse($item['tanggal_mulai'])->startOfDay();
            $end = ! empty($item['tanggal_selesai'])
                ? Carbon::parse($item['tanggal_selesai'])->startOfDay()
                : $start->copy();
        } catch (\Throwable $e) {
            return [];
        }

        /* Rentang terbalik ditukar, bukan ditolak: konfigurasi yang tanggal
           akhirnya lebih awal jelas salah ketik, dan menukarnya memberi hasil
           yang jelas benar sementara menolaknya menghilangkan harinya. */
        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        $dates = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }
}
