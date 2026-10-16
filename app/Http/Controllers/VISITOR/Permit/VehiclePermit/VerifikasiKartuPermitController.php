<?php

namespace App\Http\Controllers\VISITOR\Permit\VehiclePermit;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\VISITOR\Permit\VehiclePermit\VerifikasiKartuPermitService;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalPolicyService;
use Illuminate\Http\Request;

/**
 * Cek keaslian kartu permit yang dicetak, dari QR yang dipindai DI DALAM HCIS.
 *
 * Tidak ada halaman publik: yang memindai wajib pengguna HCIS yang sudah
 * login, sehingga data pemegang kartu tidak pernah terbuka ke internet.
 *
 * Dua lapis yang menjaganya:
 * - Kode pada kartu acak kriptografis 128 bit dan hanya ada di basis data
 *   (KartuPermitQr). Tidak ada rumus yang menghubungkannya dengan nomor
 *   permit, jadi kode kartu lain tidak menolong siapa pun menebak.
 * - Keadaan permit dibaca TERKINI dari basis data, bukan dari kertas. Kartu
 *   asli yang sudah dicabut tetap terbaca "tidak berlaku" walau cetakannya
 *   masih mulus.
 */
class VerifikasiKartuPermitController extends Controller
{
    public function __construct(
        protected VerifikasiKartuPermitService $verifikasi,
        protected VisitorApprovalPolicyService $approvalPolicy,
    ) {}

    /**
     * Pintu KARYAWAN (halaman permit miliknya sendiri).
     *
     * Hanya kartunya sendiri. Karyawan yang memungut kartu orang lain tidak
     * perlu — dan tidak boleh — melihat nama, departemen, serta masa berlaku
     * pemiliknya; itu pekerjaan petugas, bukan rekan sekantor.
     */
    public function __invoke(Request $request)
    {
        return $this->periksa($request, hanyaMilikSendiri: true);
    }

    /**
     * Pintu ADMIN (Verifikator, Agenda SHE, Monitoring).
     *
     * Boleh memeriksa kartu siapa pun — itulah gunanya di gerbang. Yang
     * membedakan kedua pintu adalah KUNCI HALAMAN pada rutenya, bukan nilai
     * yang dikirim layar: parameter dari klien bisa dipalsukan, kunci halaman
     * tidak.
     */
    public function admin(Request $request)
    {
        return $this->periksa($request, hanyaMilikSendiri: false);
    }

    private function periksa(Request $request, bool $hanyaMilikSendiri)
    {
        $data = $request->validate([
            // Kode polos (kartu lama) atau URL halaman cek publik (kartu baru).
            'token' => 'required|string|max:255',
        ], [], ['token' => 'kode kartu']);

        ['dikenali' => $dikenali, 'permit' => $permit] = $this->verifikasi->cari((string) $data['token']);

        if (! $dikenali) {
            return ResponseHelper::success([
                'sah' => false,
                'judul' => 'KARTU TIDAK DIKENALI',
                'sebab' => 'Kode pada kartu ini tidak dikenali HCIS. Kartu patut dicurigai palsu.',
                'dicek' => $this->verifikasi->dicek(),
            ]);
        }

        if ($hanyaMilikSendiri && $permit) {
            $kode = (string) $this->approvalPolicy->requesterKode($request->user());

            if ($kode === '' || $kode !== (string) $permit->Kode_Karyawan_Pemohon) {
                // Tanpa identitas pemegangnya: yang memindai hanya perlu tahu
                // bahwa kartu ini bukan urusannya.
                return ResponseHelper::success([
                    'sah' => false,
                    'judul' => 'BUKAN KARTU ANDA',
                    'sebab' => 'Kartu ini milik karyawan lain. Minta petugas SHE atau admin Visitor untuk memeriksanya.',
                    'dicek' => $this->verifikasi->dicek(),
                ]);
            }
        }

        return ResponseHelper::success($this->verifikasi->keadaan($permit));
    }
}
