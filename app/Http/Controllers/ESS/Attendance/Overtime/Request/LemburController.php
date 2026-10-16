<?php

namespace App\Http\Controllers\ESS\Attendance\Overtime\Request;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\ESS\Attendance\Overtime\Request\LemburRequestService;
use App\Services\SYSTEM\Governance\Authorization\Shared\AccessContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class LemburController extends Controller
{
    protected LemburRequestService $lemburRequestService;

    protected AccessContext $accessContext;

    public function __construct(LemburRequestService $lemburRequestService, AccessContext $accessContext)
    {
        $this->lemburRequestService = $lemburRequestService;
        $this->accessContext = $accessContext;
    }

    public function index()
    {
        $this->accessContext->set('user');

        return inertia('ESS/Attendance/Overtime/Management/Overtime-unstable', [
            'isAdmin' => false,
            'tanggal' => $this->buildTanggalOptions(false),
            'shiftList' => [],
        ]);
    }

    public function indexAdmin()
    {
        $this->accessContext->set('admin');

        return inertia('ESS/Attendance/Overtime/Management/Overtime-unstable', [
            'isAdmin' => true,
            'tanggal' => $this->buildTanggalOptions(true),
            'shiftList' => [],
        ]);
    }

    public function submit(Request $request)
    {
        $this->accessContext->set('user');

        return $this->handleSubmit($request, false);
    }

    public function validateDraft(Request $request)
    {
        $this->accessContext->set('user');

        return $this->handleValidateDraft($request, false);
    }

    public function submitAdmin(Request $request)
    {
        $this->accessContext->set('admin');

        return $this->handleSubmit($request, true);
    }

    public function validateDraftAdmin(Request $request)
    {
        $this->accessContext->set('admin');

        return $this->handleValidateDraft($request, true);
    }

    public function getUserActive(Request $request)
    {
        $this->accessContext->set('user');
        try {
            $data = $this->lemburRequestService->getActiveUsers($request, false);
            if ($this->isActiveUserPayloadEmpty($data)) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            return ResponseHelper::success($data, 'Data user aktif berhasil dimuat', 200);
        } catch (InvalidArgumentException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->error('Gagal mengambil data userActive: '.$e->getMessage());

            return ResponseHelper::error('Terjadi error saat mengambil data userActive', 500);
        }
    }

    public function getUserActiveAll(Request $request)
    {
        $this->accessContext->set('admin');
        try {
            $data = $this->lemburRequestService->getActiveUsers($request, true);
            if ($this->isActiveUserPayloadEmpty($data)) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            return ResponseHelper::success($data, 'Data user aktif berhasil dimuat', 200);
        } catch (InvalidArgumentException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->error('Gagal mengambil data userActiveAll: '.$e->getMessage());

            return ResponseHelper::error('Terjadi error saat mengambil data userActiveAll', 500);
        }
    }

    public function getData(Request $request)
    {
        $this->accessContext->set('user');
        try {
            $data = $this->lemburRequestService->getHistory($request, false);

            // Angka widget dihitung di server dengan saringan yang sama seperti
            // tabelnya. Menurunkannya di klien dari satu halaman paginasi selalu
            // salah begitu datanya lebih dari satu halaman.
            return ResponseHelper::success(
                $data,
                'Data riwayat lembur berhasil dimuat',
                200,
                ['ringkasan' => $this->lemburRequestService->ringkasanHistory($request, false)],
            );
        } catch (InvalidArgumentException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->error('Gagal mengambil data riwayat lembur: '.$e->getMessage());

            return ResponseHelper::error('Terjadi error saat mengambil data riwayat lembur', 500);
        }
    }

    public function getDataAdmin(Request $request)
    {
        $this->accessContext->set('admin');
        try {
            $data = $this->lemburRequestService->getHistory($request, true);

            // Angka widget dihitung di server dengan saringan yang sama seperti
            // tabelnya. Menurunkannya di klien dari satu halaman paginasi selalu
            // salah begitu datanya lebih dari satu halaman.
            return ResponseHelper::success(
                $data,
                'Data riwayat lembur admin berhasil dimuat',
                200,
                ['ringkasan' => $this->lemburRequestService->ringkasanHistory($request, true)],
            );
        } catch (InvalidArgumentException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->error('Gagal mengambil data riwayat lembur admin: '.$e->getMessage());

            return ResponseHelper::error('Terjadi error saat mengambil data riwayat lembur admin', 500);
        }
    }

    public function getDetails(Request $request)
    {
        $isAdminRequest = str_contains((string) optional($request->route())->getName(), 'lembur-management.admin.');
        $this->accessContext->set($isAdminRequest ? 'admin' : 'user');
        try {
            $data = $this->lemburRequestService->getDetails($request, $isAdminRequest);

            return ResponseHelper::success($data, 'Detail lembur berhasil dimuat', 200);
        } catch (InvalidArgumentException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->error('Gagal mengambil detail lembur: '.$e->getMessage());

            return ResponseHelper::error('Terjadi error saat mengambil detail lembur', 500);
        }
    }

    protected function handleSubmit(Request $request, bool $isAdmin)
    {
        $this->accessContext->set($isAdmin ? 'admin' : 'user');
        try {
            $result = $this->lemburRequestService->submitFromRequest($request, $isAdmin);

            return ResponseHelper::success($result, 'Berhasil menambahkan lembur.', 200);
        } catch (InvalidArgumentException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->error('Gagal submit lembur: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyimpan lembur.', 500);
        }
    }

    protected function handleValidateDraft(Request $request, bool $isAdmin)
    {
        $this->accessContext->set($isAdmin ? 'admin' : 'user');
        try {
            $result = $this->lemburRequestService->validateDraftFromRequest($request, $isAdmin);

            return ResponseHelper::success($result, 'Validasi draft lembur berhasil.', 200);
        } catch (InvalidArgumentException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->error('Gagal validasi draft lembur: '.$e->getMessage());

            return ResponseHelper::error('Gagal validasi draft lembur.', 500);
        }
    }

    private function buildTanggalOptions(bool $isAdmin): array
    {
        try {
            return $this->lemburRequestService->getTanggalOptions($isAdmin);
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->warning('Fallback tanggal options statis: '.$e->getMessage());
        }

        $start = Carbon::today();

        return [
            $start->format('Y-m-d'),
            $start->copy()->addDay()->format('Y-m-d'),
            $start->copy()->addDays(2)->format('Y-m-d'),
        ];
    }

    private function isActiveUserPayloadEmpty(array $payload): bool
    {
        if (! array_key_exists('data', $payload)) {
            return empty($payload);
        }

        $total = (int) ($payload['meta']['total'] ?? count($payload['data'] ?? []));

        return $total === 0;
    }
}
