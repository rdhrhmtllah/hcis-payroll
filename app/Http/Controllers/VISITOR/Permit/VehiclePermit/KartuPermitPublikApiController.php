<?php

namespace App\Http\Controllers\VISITOR\Permit\VehiclePermit;

use App\Http\Controllers\Controller;
use App\Services\VISITOR\Permit\VehiclePermit\VerifikasiKartuPermitService;
use Illuminate\Http\JsonResponse;

/**
 * API cek kartu permit untuk LAYANAN CEK KARTU PUBLIK (aplikasi terpisah).
 *
 * QR kartu mengarah ke layanan itu, bukan ke HCIS; layanan itulah yang memanggil
 * endpoint ini server-ke-server lewat hris.api.guard (HMAC + nonce + whitelist
 * endpoint per aplikasi). Domain HCIS karenanya tidak pernah terlihat oleh orang
 * yang memindai kartu.
 *
 * Jawabannya sengaja MINIMAL — setara dengan yang ditampilkan ke publik:
 * status, kategori, plat tersamar, dan masa berlaku. Kunci API yang bocor
 * karena itu tidak memberi apa pun melebihi memindai kartu sendiri: kode kartu
 * acak 128 bit tidak bisa ditebak, dan tidak ada pencarian maupun daftar.
 *
 * Spec: docs/superpowers/specs/2026-09-28-cek-kartu-permit-publik-design.md
 */
class KartuPermitPublikApiController extends Controller
{
    public function __construct(
        private readonly VerifikasiKartuPermitService $verifikasi,
    ) {}

    public function show(string $kode): JsonResponse
    {
        return response()->json([
            'sukses' => true,
            'message' => 'Status kartu permit',
            'result' => $this->verifikasi->keadaanPublik($kode),
        ]);
    }
}
