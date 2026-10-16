<?php

namespace App\Http\Controllers\SYSTEM\Integration;

use App\Http\Controllers\Controller;
use App\Services\SYSTEM\Integration\OutboundContractService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Outbound Integration Contracts / Feature Requests.
 *
 * Dua permukaan dengan aturan berlawanan, dan bentuknya pun berbeda — itu
 * disengaja, bukan warisan yang belum sempat diseragamkan:
 *
 * 1. halaman() — portal manajemen INTERNAL. Halaman Inertia di dalam shell
 *    aplikasi, gerbang `system.admin`, di /system/integrasi/kontrak-outbound.
 * 2. show()/json() — viewer PUBLIK. Dokumen Blade mandiri yang berdiri sendiri
 *    di luar shell, tanpa auth dan tanpa Api_Public, karena tautannya memang
 *    dibagikan ke developer vendor. Mensyaratkan login membatalkan gunanya.
 *
 * Portal manajemen sempat ikut berupa Blade dan itu tampak sebagai modal galat
 * Inertia setelah login: klien Inertia mengikuti redirect()->intended() sebagai
 * XHR ber-header X-Inertia, lalu menerima HTML polos yang bukan respons
 * Inertia. Halaman internal di aplikasi ini berbentuk Inertia; itu bukan
 * selera, melainkan syarat agar alur autentikasinya utuh.
 */
class OutboundContractController extends Controller
{
    public function __construct(
        private readonly OutboundContractService $contractService
    ) {}

    /**
     * Portal internal manajemen kontrak & permintaan integrasi outbound.
     *
     * Halaman Inertia di dalam shell aplikasi, BUKAN Blade mandiri. Bentuk itu
     * bukan soal selera: halaman non-Inertia di balik `auth` di dalam aplikasi
     * Inertia akan tampil di dalam modal galat setelah login, karena klien
     * Inertia mengikuti redirect()->intended() sebagai XHR ber-header X-Inertia
     * dan menerima HTML polos sebagai jawabannya.
     *
     * Datanya statis dan hanya tiga baris, jadi dikirim sekaligus sebagai prop —
     * endpoint daftar terpisah hanya akan menambah satu perjalanan tanpa ada
     * yang dihemat.
     */
    public function halaman(Request $request): Response|JsonResponse
    {
        // Tautan viewer dibuatkan di sini, bertanda tangan. Halaman tidak boleh
        // merakitnya sendiri dari baseUrl + slug: sejak viewer bergerbang
        // `signed`, tautan rakitan tangan akan selalu dijawab 403.
        $contracts = $this->contractService->semuaDenganTautan();

        if ($request->wantsJson() || $request->query('format') === 'json') {
            return response()->json([
                'success' => true,
                'data' => $contracts,
            ]);
        }

        return Inertia::render('SYSTEM/Integration/OutboundContracts/OutboundContractsPage', [
            'contracts' => $contracts,
            'targetSystems' => $this->contractService->targetSystems(),
            'categories' => $this->contractService->categories(),
            'baseUrl' => $request->getSchemeAndHttpHost(),
        ]);
    }

    /**
     * Halaman publik mandiri untuk vendor/klien melihat spesifikasi kontrak (tanpa login/Api_Public).
     */
    public function show(Request $request, string $slug): View|JsonResponse
    {
        $contract = $this->contractService->findBySlug($slug);

        if (! $contract) {
            abort(404, "Spesifikasi kontrak integrasi '{$slug}' tidak ditemukan.");
        }

        if ($request->wantsJson() || $request->query('format') === 'json') {
            return response()->json([
                'success' => true,
                'data' => $contract,
            ]);
        }

        return view('SYSTEM.Integration.outbound-contract-viewer', [
            'contract' => $contract,
            'baseUrl' => $request->getSchemeAndHttpHost(),

            // Tombol "Raw JSON" di halaman ini menunjuk rute yang juga
            // bergerbang `signed`. Tautannya dibuat di sini, bukan dirangkai di
            // Blade, supaya tidak lahir tautan yang tampak benar tetapi 403.
            'tautanJson' => $this->contractService->tautanJson($slug),
        ]);
    }

    /**
     * Endpoint raw JSON spesifikasi kontrak untuk integrasi otomasi/CI-CD.
     */
    public function json(string $slug): JsonResponse
    {
        $contract = $this->contractService->findBySlug($slug);

        if (! $contract) {
            return response()->json([
                'success' => false,
                'message' => "Kontrak '{$slug}' tidak ditemukan.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $contract,
        ]);
    }
}
