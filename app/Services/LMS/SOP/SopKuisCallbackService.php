<?php

namespace App\Services\LMS\SOP;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Satu-satunya otak untuk callback hasil kuis SOP.
 *
 * KENAPA ADA
 * Hasil ujian bisa masuk lewat DUA pintu:
 *   1. Endpoint callback LMS (dijaga LmsApiGuard). Ini yang HIDUP — HC Learn
 *      memakai satu URL callback tetap. Bila token tak dikenali sisi LMS,
 *      guard mencarinya di N_SOP_Penjadwalan_Integrasi lalu mendelegasikan ke
 *      LmsThirdPartyCallbackController::completeSopKuis().
 *   2. Endpoint SOP khusus /api/v1/hris/third-party/sop/exam/complete
 *      (dijaga HrisApiGuard). Sudah terdaftar di N_HRIS_Api_*, tapi sejauh ini
 *      hanya pernah kena uji manual.
 *
 * Punya dua pintu bukan masalah — yang berbahaya adalah dua pintu dengan ATURAN
 * BERBEDA. Sebelum kelas ini, pintu LMS mencari token tanpa memedulikan
 * Flag_Aktif (sengaja: peserta yang kadung mulai versi lama tetap boleh submit),
 * sementara pintu SOP mensyaratkan Flag_Aktif='Y' + kecocokan
 * Id_Penjadwalan_Integrasi — sehingga hasil ujian yang sama bisa TERSIMPAN atau
 * HILANG tergantung pintu mana yang dipakai. Kedua controller sekarang
 * memanggil kelas ini, jadi aturannya cuma satu.
 */
class SopKuisCallbackService
{
    public function __construct(private SopCompletionService $completion) {}

    /**
     * @param  string|int|null  $idIntegrasi  Id_SOP_Penjadwalan_Integrasi kiriman HC Learn (cocok-silang).
     * @return array{ok:bool, code:int, message:string, result:?string}
     */
    public function terima(string $kodeKaryawan, string $token, $idIntegrasi, float $score): array
    {
        $kode = strtoupper(trim($kodeKaryawan));

        /*
         * Token = sumber kebenaran. Ia unik & tak bisa ditebak per sesi ujian,
         * dan permintaannya sudah lolos HMAC di middleware.
         *
         * Flag_Aktif SENGAJA tidak difilter: saat admin menerbitkan versi test
         * baru, integrasi versi lama dinonaktifkan. Peserta yang sudah terlanjur
         * mengerjakan versi lama tetap harus bisa menyetor hasilnya — kalau
         * tidak, jerih payahnya hilang tanpa jejak.
         */
        $integrasi = DB::table('N_SOP_Penjadwalan_Integrasi')
            ->where('Id_Ujian_Token', $token)
            ->orderByDesc('Id_SOP_Penjadwalan_Integrasi')
            ->first();

        if (! $integrasi) {
            return $this->gagal(404, 'Id_Ujian_Token tidak dikenali');
        }

        /*
         * Id_Penjadwalan_Integrasi dipakai sebagai COCOK-SILANG, bukan syarat.
         * Log produksi menunjukkan HC Learn memang mengembalikannya dengan benar,
         * tapi menolak saat tidak cocok berarti membuang hasil ujian yang nyata
         * hanya karena kontrak pihak seberang berubah. Selisihnya dicatat supaya
         * ketahuan, bukan dijadikan alasan menolak.
         */
        if ($idIntegrasi !== null && (string) $idIntegrasi !== (string) $integrasi->Id_SOP_Penjadwalan_Integrasi) {
            Log::warning('SOP_KUIS_CALLBACK_ID_TIDAK_COCOK', [
                'dikirim' => $idIntegrasi,
                'dari_token' => $integrasi->Id_SOP_Penjadwalan_Integrasi,
                'token' => $token,
            ]);
        }

        // Pertahanan berlapis: peserta di body harus pemilik token.
        if (strtoupper(trim((string) $integrasi->Kode_Karyawan)) !== $kode) {
            return $this->gagal(403, 'Kode_Karyawan tidak cocok dengan pemilik token ujian');
        }

        $item = DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket_Item', $integrasi->Id_SOP_Paket_Item)
            ->first(['Id_SOP_Paket', 'Tipe_Item', 'Nilai_Minimum']);

        if (! $item) {
            return $this->gagal(404, 'Item kuis Dokumen tidak ditemukan untuk token ini');
        }

        $result = $this->completion->submitKuisResult(
            (int) $integrasi->Id_SOP_Penjadwalan_Detail,
            (int) $integrasi->Id_SOP_Paket_Item,
            $integrasi->Id_SOP_Item_Versi !== null ? (int) $integrasi->Id_SOP_Item_Versi : null,
            (int) $item->Id_SOP_Paket,
            (string) $integrasi->Kode_Karyawan,
            (string) $item->Tipe_Item,
            $score,
            $item->Nilai_Minimum !== null ? (float) $item->Nilai_Minimum : null,
            $token, // kunci anti-dobel: satu token = satu sesi = satu hasil
        );

        Log::info('SOP_KUIS_CALLBACK', [
            // Token adalah kredensial sesi sekaligus kunci anti-dobel. File log
            // cukup menyimpan hash-nya; token mentah tetap hanya hidup di jalur
            // pemrosesan dan basis data integrasi.
            'token_hash' => hash('sha256', $token),
            'Id_SOP_Penjadwalan_Integrasi' => $integrasi->Id_SOP_Penjadwalan_Integrasi,
            'Id_SOP_Item_Versi' => $integrasi->Id_SOP_Item_Versi,
            'Kode_Karyawan' => $integrasi->Kode_Karyawan,
            'Score' => $score,
            'result' => $result,
        ]);

        return ['ok' => true, 'code' => 200, 'message' => 'Hasil ujian dokumen tersimpan.', 'result' => $result];
    }

    private function gagal(int $code, string $message): array
    {
        Log::warning('SOP_KUIS_CALLBACK_DITOLAK', ['code' => $code, 'message' => $message]);

        return ['ok' => false, 'code' => $code, 'message' => $message, 'result' => null];
    }
}
