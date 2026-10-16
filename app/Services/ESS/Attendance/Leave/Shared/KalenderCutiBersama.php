<?php

namespace App\Services\ESS\Attendance\Leave\Shared;

use App\Services\ESS\Attendance\Leave\Admin\cutiBersamaService as CutiBersamaService;
use Carbon\Carbon;

/**
 * Peta tanggal → judul cuti bersama untuk satu karyawan pada satu rentang.
 *
 * ── Kenapa dipusatkan ────────────────────────────────────────────────────────
 *
 * getCutiBersamaByDateForKaryawan() ada DUA salinan identik — 1.174 byte sama
 * persis di AbsensiController dan uDashController. Keduanya memberi makan
 * kalender yang dilihat karyawan: satu di halaman absensi, satu di dashboard
 * personal. Dua salinan berarti dua kalender yang bisa berbeda isinya tanpa ada
 * yang menyadari, dan yang berbeda itu bukan hiasan melainkan hari libur.
 *
 * Tanggalnya sendiri sudah punya rumah — TanggalCutiBersama::dariItem(), yang
 * dibuat justru karena aturan yang sama pernah tersalin di TIGA controller.
 * Yang belum punya rumah adalah lapisan di atasnya: menyaring ke rentang,
 * mengelompokkan judul per tanggal, dan menggabungkannya.
 *
 * ── Perilaku yang dipertahankan apa adanya ───────────────────────────────────
 *
 * Dua kehalusan sengaja TIDAK dirapikan, karena mengubahnya berarti mengubah
 * apa yang dilihat pengguna:
 *
 *   1. Item tanpa `nama_cuti_bersama` DIBUANG. CutiController, yang membaca
 *      sumber yang sama untuk keperluan lain, justru memberinya nilai baku
 *      'Cuti Bersama'. Menyeragamkannya adalah keputusan produk — apakah hari
 *      libur tanpa nama pantas muncul di kalender — bukan perapian kode.
 *   2. Rentang terbalik (mulai > selesai) menghasilkan peta KOSONG, tidak
 *      ditukar. TanggalCutiBersama::dariItem menukar rentang terbalik pada
 *      level item, dan cutiBersamaItemOverlapsRange menukarnya juga; lapisan
 *      ini tidak. Dibiarkan supaya perpindahan ini tidak diam-diam mengubah
 *      hasil, tetapi dicatat karena ia tidak konsisten dengan tetangganya.
 */
class KalenderCutiBersama
{
    public function __construct(private CutiBersamaService $cutiBersamaService) {}

    /**
     * Peta 'Y-m-d' => 'Judul A, Judul B' untuk karyawan pada rentang tertentu.
     *
     * @return array<string, string>
     */
    public function untukKaryawan(string $kodeKaryawan, string $rangeStart, string $rangeEnd): array
    {
        $hasil = $this->cutiBersamaService->getDataCutiByKode_Karyawan($kodeKaryawan);

        return self::petakan($hasil['data'][0]['items'] ?? [], $rangeStart, $rangeEnd);
    }

    /**
     * Inti aturannya, tanpa basis data — dan karena itu bisa benar-benar diuji.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, string>
     */
    public static function petakan(array $items, string $rangeStart, string $rangeEnd): array
    {
        $mulai = Carbon::parse($rangeStart)->toDateString();
        $selesai = Carbon::parse($rangeEnd)->toDateString();

        $perTanggal = [];
        foreach ($items as $item) {
            $judul = $item['nama_cuti_bersama'] ?? null;
            if (! $judul) {
                continue;
            }

            foreach (TanggalCutiBersama::dariItem($item) as $tanggal) {
                if ($tanggal < $mulai || $tanggal > $selesai) {
                    continue;
                }
                if (! isset($perTanggal[$tanggal])) {
                    $perTanggal[$tanggal] = [];
                }
                /* Judul kembar pada tanggal yang sama digabung jadi satu:
                   dua item cuti bersama bernama sama pada hari yang sama tidak
                   pantas tampil dua kali di kalender. */
                if (! in_array($judul, $perTanggal[$tanggal], true)) {
                    $perTanggal[$tanggal][] = $judul;
                }
            }
        }

        foreach ($perTanggal as $tanggal => $daftarJudul) {
            $perTanggal[$tanggal] = implode(', ', $daftarJudul);
        }

        return $perTanggal;
    }
}
