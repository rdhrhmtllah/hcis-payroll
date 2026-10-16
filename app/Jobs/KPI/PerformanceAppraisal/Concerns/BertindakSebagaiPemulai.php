<?php

namespace App\Jobs\KPI\PerformanceAppraisal\Concerns;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Job latar PA yang memanggil HC Learn bertindak SEBAGAI ADMIN YANG MEMULAINYA.
 *
 * Permintaan ke HC Learn ditandatangani dengan `User_Key` + `Kode_Users` pengguna yang masuk
 * (`PaHcLearnJadwalContract::identitas()`), atau akun layanan bila diisi. Di antrean tidak ada
 * yang masuk, dan akun layanan di dev kosong — sehingga penjadwalan latar dan penghapusan
 * jadwal gagal menandatangani setiap kirimannya (13 Okt 2026, ditemukan sebelum uji nyata).
 *
 * Bertindak sebagai pemulainya juga yang paling jujur: dulu kiriman yang sama berangkat dari
 * browser admin itu, dan jejak di HC Learn menyebut orangnya. Akun layanan tetap dipakai bila
 * pemulainya tidak diketahui atau tidak punya kunci.
 */
trait BertindakSebagaiPemulai
{
    protected function bertindakSebagai(?int $userId): void
    {
        if ($userId === null || Auth::check()) {
            return;
        }

        $pengguna = User::find($userId);

        if ($pengguna !== null && ! empty($pengguna->User_Key) && ! empty($pengguna->Kode_Users)) {
            Auth::setUser($pengguna);
        }
    }
}
