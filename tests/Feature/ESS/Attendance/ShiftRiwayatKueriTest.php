<?php

namespace Tests\Feature\ESS\Attendance;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Shared\AccessContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Riwayat shift leader harus berbiaya TETAP terhadap ukuran halaman.
 *
 * getLeaderShiftHistory() dulu mengambil tiga hitungan pelengkap SATU PER SATU
 * untuk tiap transaksi: jumlah tanggal berbeda, jumlah baris detail yang masih
 * aktif, dan berapa di antaranya yang masih menunggu persetujuan. Satu halaman
 * sepuluh baris berarti sampai tiga puluh kueri, dan angkanya naik lurus
 * mengikuti `limit`:
 *
 *   limit  sebelum  sesudah
 *      10       17       13
 *      50       59        9
 *     100       59        9
 *
 * Tak ada yang rusak dan tak ada error — hanya makin lambat setiap kali
 * seseorang membesarkan halamannya.
 *
 * YANG DIJAGA ADALAH SIFATNYA, BUKAN ANGKANYA.
 * Ambang mati akan basi begitu data dev berubah, dan lebih buruk lagi: pada
 * data yang sedikit, pola per-baris pun masih muat di bawah ambang mana pun
 * yang wajar. Maka yang dituntut di sini adalah halaman BESAR tidak boleh
 * jauh lebih mahal daripada halaman kecil — itulah arti "tidak tumbuh per
 * baris", dan itu tetap benar berapa pun isi basis datanya.
 */
class ShiftRiwayatKueriTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Selisih kueri yang masih boleh antara halaman kecil dan halaman besar.
     *
     * Nol terlalu kaku — jalur kode boleh berbeda sedikit ketika barisnya lebih
     * beragam (misal muncul jenis transaksi kedua, yang memang menambah satu
     * kelompok kueri). Lima memberi ruang itu tanpa menoleransi satu-kueri-
     * per-baris, yang pada selisih 40 baris akan menambah puluhan.
     */
    private const SELISIH_MAKS = 5;

    protected function setUp(): void
    {
        parent::setUp();

        /* withoutMiddleware() mematikan autentikasi, sedangkan controllernya
           membaca Auth::user()->karyawan — tanpa pengguna, rutenya menjawab 500
           dan uji ini gagal karena alasan yang bukan urusannya.

           Penggunanya dipilih dari yang BENAR-BENAR punya riwayat. Mengambil
           karyawan aktif pertama begitu saja menghasilkan riwayat kosong, dan
           uji yang melewati dirinya sendiri karena "data kosong" tidak menguji
           apa pun — persis hijau palsu yang harus dihindari. */
        $pembuat = DB::table('N_HRIS_Transaksi_Shift')
            ->whereNotNull('Created_By')
            ->groupBy('Created_By')
            ->orderByRaw('COUNT(*) DESC')
            ->selectRaw('Created_By')
            ->first();

        // Created_By menyimpan Id_Users, bukan Kode_Karyawan.
        $aktor = $pembuat
            ? User::query()
                ->join('Karyawan as k', 'k.UserID_Web', '=', 'KPI_Users.Id_Users')
                ->select('KPI_Users.Id_Users')
                ->where('KPI_Users.Id_Users', $pembuat->Created_By)
                ->first()
            : null;

        if (! $aktor) {
            $this->markTestSkipped('Tidak ada user dengan riwayat shift di data dev.');
        }

        $this->withoutMiddleware();
        Auth::login(User::find($aktor->Id_Users));
        app(AccessContext::class)->set('admin');
    }

    private function cacahKueri(string $uri): array
    {
        $kueri = [];
        $pendengar = function ($q) use (&$kueri) {
            $kueri[] = $q->sql;
        };
        DB::listen($pendengar);

        $res = $this->getJson($uri);

        return [$res, count($kueri)];
    }

    public function test_biaya_riwayat_tidak_tumbuh_mengikuti_ukuran_halaman(): void
    {
        $jumlah = DB::table('N_HRIS_Transaksi_Shift')->count()
            + DB::table('N_HRIS_Transaksi_Status_Hari')->count();

        if ($jumlah < 20) {
            $this->markTestSkipped(
                "Hanya {$jumlah} transaksi shift di data dev; di bawah 20 baris, halaman "
                .'besar dan kecil berisi hal yang sama dan uji ini tak membuktikan apa pun.'
            );
        }

        [$resKecil, $kecil] = $this->cacahKueri('/leader-shift/history?limit=10');
        $resKecil->assertOk();

        [$resBesar, $besar] = $this->cacahKueri('/leader-shift/history?limit=50');
        $resBesar->assertOk();

        $this->assertLessThanOrEqual(
            $kecil + self::SELISIH_MAKS,
            $besar,
            "Halaman 50 baris memakai {$besar} kueri, halaman 10 baris {$kecil} — "
            .'selisihnya melebihi '.self::SELISIH_MAKS.'. Biayanya ikut tumbuh per baris, '
            .'artinya ada kueri yang kembali masuk ke dalam perulangan. Ambil massal '
            .'sebelum loop, lihat ambilHitunganRiwayat().'
        );
    }

    /**
     * Sisi kedua: memangkas kueri tak ada gunanya kalau datanya ikut hilang.
     *
     * tanggal_count dan can_cancel dulu dihitung per baris; sekarang dibaca
     * dari hasil pengambilan massal. Kalau pemetaan kuncinya meleset, medannya
     * diam-diam jadi 0/false untuk semua orang — dan jumlah kuerinya justru
     * terlihat makin bagus.
     */
    public function test_riwayat_tetap_membawa_hitungan_tiap_baris(): void
    {
        $res = $this->getJson('/leader-shift/history?limit=50');
        $res->assertOk();

        $items = $res->json('result') ?? $res->json('data') ?? [];
        if (! is_array($items) || $items === []) {
            $this->markTestSkipped('Riwayat kosong di data dev.');
        }

        $adaTanggalCount = false;
        foreach ($items as $baris) {
            if (! is_array($baris)) {
                continue;
            }
            $this->assertArrayHasKey('is_pending', $baris);
            $this->assertArrayHasKey('can_cancel', $baris);

            if (array_key_exists('tanggal_count', $baris) && (int) $baris['tanggal_count'] > 0) {
                $adaTanggalCount = true;
            }
        }

        $this->assertTrue(
            $adaTanggalCount,
            'Tidak satu pun baris riwayat membawa tanggal_count > 0. Pengambilan massal '
            .'kemungkinan salah memetakan kunci No_Transaksi, sehingga semua baris '
            .'jatuh ke nilai baku 0 — gejala yang justru membuat jumlah kueri tampak membaik.'
        );
    }
}
