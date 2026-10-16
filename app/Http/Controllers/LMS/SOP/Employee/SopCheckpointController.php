<?php

namespace App\Http\Controllers\LMS\SOP\Employee;

use App\Http\Controllers\Controller;
use App\Services\LMS\SOP\SopKuisDispatchService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

/**
 * SOP — Checkpoint Kuis (karyawan).
 * GET /sop/{id}/kuis/{item}: lazy-mint / reuse token ujian HC Learn lalu
 * redirect ke Link_Ujian. Gagal → balik ke detail SOP dgn pesan (?kuis_err=).
 */
class SopCheckpointController extends Controller
{
    public function __construct(private SopKuisDispatchService $dispatch) {}

    public function launch($id, $item)
    {
        $p = Hashids::connection('custom')->decode($id);
        $i = Hashids::connection('custom')->decode($item);
        if (! $p || ! $i) {
            return redirect('/sop/repository');
        }

        $kode = optional(Auth::user()->karyawan)->Kode_Karyawan;
        if (! $kode) {
            return redirect('/sop/'.$id.'?kuis_err='.urlencode('Akun tidak tertaut data karyawan.'));
        }

        try {
            $res = $this->dispatch->launch($kode, $p[0], $i[0]);
        } catch (\Exception $e) {
            Log::error('SOP checkpoint launch gagal', ['exception' => $e]);

            return redirect('/sop/'.$id.'?kuis_err='.urlencode('Terjadi kesalahan sistem.'));
        }

        if (! empty($res['url'])) {
            return redirect()->away($res['url']);
        }

        return redirect('/sop/'.$id.'?kuis_err='.urlencode($res['_error'] ?? 'Gagal membuka kuis.'));
    }
}
