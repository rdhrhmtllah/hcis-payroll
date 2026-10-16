<?php

namespace Tests\Feature\ESS\Attendance;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Riwayat konversi lembur mengambil langkah approval SEKALI, bukan per baris.
 *
 * `getHistoryForUser()` memanggil `getApprovalStepsForTransaction()` di dalam
 * `->map()`, jadi satu kueri ke `N_HRIS_VW_Approval_Request_V2` untuk tiap
 * baris riwayat. Bentuk massalnya sudah lama ada di kelas yang sama
 * (`getApprovalStepsMapForTransactions()`, dipakai `getCalendarStatus()`) —
 * hanya belum dipakai di sini.
 *
 * Terukur di dev: riwayat 14 -> 7 kueri, statistik 10 -> 3.
 *
 * YANG DIJAGA KUERI ITU SENDIRI, BUKAN JUMLAH TOTAL.
 * Ambang atas jumlah kueri akan rapuh: satu kueri tetap yang wajar ditambahkan
 * di kemudian hari bisa membuatnya merah tanpa ada N+1 sama sekali. Yang
 * dituntut di sini lebih sempit dan tepat sasaran — kueri langkah approval
 * boleh berjalan PALING BANYAK SEKALI, berapa pun baris riwayatnya.
 */
class KonversiLemburRiwayatKueriTest extends TestCase
{
    use DatabaseTransactions;

    /** Ciri kueri yang dijaga. */
    private const TABEL_APPROVAL = 'N_HRIS_VW_Approval_Request_V2';

    protected function setUp(): void
    {
        parent::setUp();

        /* Penggunanya dipilih yang BENAR-BENAR punya riwayat konversi. Memakai
           karyawan pertama begitu saja menghasilkan riwayat kosong, dan uji
           yang melewati dirinya sendiri karena "tak ada data" tidak menguji
           apa pun. */
        $pemilik = DB::table('N_HRIS_Transaksi_Konversi_Lembur_Detail')
            ->whereNotNull('Kode_Karyawan')
            ->groupBy('Kode_Karyawan')
            ->orderByRaw('COUNT(*) DESC')
            ->selectRaw('Kode_Karyawan')
            ->first();

        $aktor = $pemilik
            ? User::query()
                ->join('Karyawan as k', 'k.UserID_Web', '=', 'KPI_Users.Id_Users')
                ->select('KPI_Users.Id_Users')
                ->where('k.Kode_Karyawan', $pemilik->Kode_Karyawan)
                ->first()
            : null;

        if (! $aktor) {
            $this->markTestSkipped('Tidak ada user dengan riwayat konversi lembur di data dev.');
        }

        $this->withoutMiddleware();
        Auth::login(User::find($aktor->Id_Users));
    }

    /** @return array{0:\Illuminate\Testing\TestResponse,1:int,2:int} */
    private function panggil(string $uri): array
    {
        $semua = [];
        DB::listen(function ($q) use (&$semua) {
            $semua[] = $q->sql;
        });

        $res = $this->getJson($uri);

        $approval = 0;
        foreach ($semua as $sql) {
            if (stripos($sql, self::TABEL_APPROVAL) !== false) {
                $approval++;
            }
        }

        return [$res, $approval, count($semua)];
    }

    public function test_langkah_approval_diambil_sekali_untuk_seluruh_riwayat(): void
    {
        [$res, $approval, $total] = $this->panggil('/konversi-lembur/history');
        $res->assertOk();

        $baris = count($res->json('data') ?? []);
        if ($baris < 3) {
            $this->markTestSkipped("Hanya {$baris} baris riwayat; terlalu sedikit untuk membedakan sekali dari per-baris.");
        }

        $this->assertLessThanOrEqual(
            1,
            $approval,
            'Kueri ke '.self::TABEL_APPROVAL." berjalan {$approval}x untuk {$baris} baris riwayat "
            ."(total {$total} kueri). Ia harus berjalan paling banyak SEKALI — ambil massal "
            .'lewat getApprovalStepsMapForTransactions() sebelum map(), jangan per baris.'
        );
    }

    /**
     * Sisi kedua: memangkas kueri tak ada gunanya kalau datanya ikut hilang.
     *
     * Kalau pemetaan kunci No_Transaksi meleset, approval_steps jadi array
     * kosong untuk semua orang — dan jumlah kuerinya justru terlihat makin
     * bagus.
     *
     * VERSI PERTAMA UJI INI TIDAK MENGGIGIT. Ia hanya menuntut kuncinya ada dan
     * isinya array; pemetaan yang sengaja dimeleskan tetap lolos, karena array
     * kosong juga array. Yang dituntut sekarang: harus ada baris yang langkah
     * approval-nya BENAR-BENAR TERISI. Di data dev, 13 dari 24 baris punya —
     * jadi kalau tak satu pun terisi, pemetaannya yang rusak, bukan datanya.
     */
    public function test_riwayat_tetap_membawa_langkah_approval_tiap_baris(): void
    {
        $res = $this->getJson('/konversi-lembur/history');
        $res->assertOk();

        $rows = $res->json('data') ?? [];
        if ($rows === []) {
            $this->markTestSkipped('Riwayat konversi kosong di data dev.');
        }

        $terisi = 0;
        foreach ($rows as $baris) {
            $this->assertArrayHasKey('approval_steps', $baris);
            $this->assertIsArray($baris['approval_steps']);
            $this->assertArrayHasKey('No_Transaksi', $baris);
            $this->assertArrayHasKey('Status', $baris);

            if ($baris['approval_steps'] !== []) {
                $terisi++;
            }
        }

        /* Premisnya diperiksa lebih dulu: kalau memang tak ada satu pun baris
           yang punya langkah approval di basis data, uji ini tak bisa
           membuktikan apa pun dan harus mengatakannya terang-terangan. */
        $adaDiDb = DB::table(self::TABEL_APPROVAL)
            ->whereIn('No_Transaksi', array_column($rows, 'No_Transaksi'))
            ->exists();

        if (! $adaDiDb) {
            $this->markTestSkipped('Tak ada baris approval untuk transaksi milik karyawan uji.');
        }

        $this->assertGreaterThan(
            0,
            $terisi,
            'Tak satu pun dari '.count($rows).' baris riwayat membawa langkah approval, padahal '
            .'basis data punya barisnya. Pengambilan massal kemungkinan salah memetakan kunci '
            .'No_Transaksi, sehingga semua baris jatuh ke array kosong — gejala yang justru '
            .'membuat jumlah kueri tampak membaik.'
        );
    }

    /**
     * Bentuk massal harus memberi isi yang SAMA dengan bentuk per-transaksi.
     *
     * Keduanya hidup berdampingan di kelas yang sama; kalau salah satu diubah
     * sendirian, riwayat dan kalender akan menampilkan langkah approval yang
     * berbeda untuk transaksi yang sama — dan tidak ada error yang memberitahu.
     */
    public function test_bentuk_massal_dan_per_transaksi_sepakat(): void
    {
        $no = DB::table(self::TABEL_APPROVAL)
            ->whereNotNull('No_Transaksi')
            ->value('No_Transaksi');

        if ($no === null) {
            $this->markTestSkipped('Tidak ada baris approval untuk dibandingkan.');
        }

        $service = app(\App\Services\ESS\Attendance\Overtime\Conversion\KonversiLemburService::class);
        $ref = new \ReflectionClass($service);

        $satu = $ref->getMethod('getApprovalStepsForTransaction');
        $satu->setAccessible(true);
        $massal = $ref->getMethod('getApprovalStepsMapForTransactions');
        $massal->setAccessible(true);

        $dariSatu = $satu->invoke($service, (string) $no, []);
        $dariMassal = ($massal->invoke($service, [(string) $no => []]))[(string) $no] ?? [];

        $this->assertSame(
            $dariSatu,
            $dariMassal,
            'getApprovalStepsForTransaction() dan getApprovalStepsMapForTransactions() '
            ."memberi hasil berbeda untuk transaksi {$no}. Keduanya dipakai di jalur yang "
            .'berbeda, jadi perbedaan ini akan tampil sebagai dua kebenaran di dua layar.'
        );
    }
}
