<?php

namespace Tests\Feature\SYSTEM\Application;

use App\Services\SYSTEM\Application\Transactions\AllTransactionsReadService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Parity test: AllTransactionsReadService HARUS menghasilkan output identik
 * dengan view `N_HRIS_VW_All_Transactions2` untuk filter yang sama.
 *
 * Ini adalah GATE sebelum pemanggil view diganti ke service (T3).
 * Test membaca DB nyata (read-only). Jika view/data tidak tersedia, test di-skip
 * agar tidak rapuh di environment tanpa koneksi DB produksi.
 *
 * Kunci pembanding per baris: Source_Row_Key (unik per item) +
 * No_Transaksi + Dashboard_Status + Flow_State.
 */
class AllTransactionsReadParityTest extends TestCase
{
    /** Banyak kode per klausa IN — cukup besar untuk memangkas kueri, cukup kecil agar rencananya tak terus direkompilasi. */
    private const POTONGAN_KODE = 200;

    private AllTransactionsReadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AllTransactionsReadService;

        if (! $this->viewExists()) {
            $this->markTestSkipped('View N_HRIS_VW_All_Transactions2 tidak tersedia di koneksi ini.');
        }
    }

    private function viewExists(): bool
    {
        try {
            DB::table('N_HRIS_VW_All_Transactions2')->limit(1)->exists();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Ambil beberapa Kode_Karyawan yang punya transaksi (sampel nyata).
     *
     * @return array<int,string>
     */
    private function sampleRequesters(int $limit = 5): array
    {
        return DB::table('N_HRIS_VW_All_Transactions2')
            ->select('Kode_Karyawan')
            ->whereNotNull('Kode_Karyawan')
            ->groupBy('Kode_Karyawan')
            ->orderByRaw('COUNT(*) DESC')
            ->limit($limit)
            ->pluck('Kode_Karyawan')
            ->all();
    }

    /**
     * @return array<int,string>
     */
    private function sampleApprovers(int $limit = 5): array
    {
        return DB::table('N_HRIS_VW_All_Transactions2')
            ->select('Current_Approver')
            ->whereNotNull('Current_Approver')
            ->groupBy('Current_Approver')
            ->orderByRaw('COUNT(*) DESC')
            ->limit($limit)
            ->pluck('Current_Approver')
            ->all();
    }

    /**
     * Normalisasi koleksi baris menjadi map key->fingerprint untuk dibandingkan.
     *
     * @param  iterable<int,object>  $rows
     * @return array<string,string>
     */
    private function fingerprint(iterable $rows): array
    {
        $map = [];
        foreach ($rows as $r) {
            $key = ($r->Source_Row_Key ?? '').'#'.($r->No_Transaksi ?? '');
            // Bandingkan banyak kolom kunci agar parity benar-benar menyeluruh.
            $map[$key] = implode('|', [
                (string) ($r->Dashboard_Status ?? ''),
                (string) ($r->Flow_State ?? ''),
                (string) ($r->Current_Approver ?? ''),
                (string) ($r->Approved_By_Kode ?? ''),
                (string) ($r->Jenis ?? ''),
                (string) ($r->Kode_Karyawan ?? ''),
                $r->Tanggal_Item ? \Carbon\Carbon::parse($r->Tanggal_Item)->format('Y-m-d') : '',
                (string) ($r->Source_View ?? ''),
                (string) ($r->Deskripsi ?? ''),
            ]);
        }
        ksort($map);

        return $map;
    }

    public function test_requester_rows_match_view(): void
    {
        $requesters = $this->sampleRequesters();

        if (empty($requesters)) {
            $this->markTestSkipped('Tidak ada data requester untuk diuji.');
        }

        foreach ($requesters as $kode) {
            $viewRows = DB::table('N_HRIS_VW_All_Transactions2')
                ->where('Kode_Karyawan', $kode)
                ->get();

            $serviceRows = $this->service->forRequester($kode)->get();

            $this->assertSame(
                $this->fingerprint($viewRows),
                $this->fingerprint($serviceRows),
                "Output service != view untuk requester {$kode}"
            );
        }
    }

    /**
     * Parity MENYELURUH: bandingkan SELURUH baris view vs seluruh baris service
     * (semua Kode_Karyawan, bukan sampel). Membuktikan tidak ada baris yang
     * hilang/berbeda di seluruh dataset.
     *
     * DIBANDINGKAN SECARA MASSAL, BUKAN PER ORANG.
     *
     * Bentuk lamanya melooping tiap Kode_Karyawan dan menembak satu kueri view
     * PLUS satu kueri service untuk masing-masing. Pada 966 karyawan itu berarti
     * sekitar 1.932 kueri, dan view-nya sendiri berbiaya ~7.470 logical reads
     * sekali jalan — uji ini seorang diri memakan 361 detik dari suite dan
     * menggilas basis data dev yang dipakai bersama.
     *
     * Jaminannya TIDAK dikurangi: gabungan `WHERE Kode_Karyawan = ?` atas semua
     * kode yang ada persis sama dengan `WHERE Kode_Karyawan IS NOT NULL`, dan
     * `forRequesters([...])` adalah bentuk jamak dari `forRequester()` yang sama
     * (padKodeList hanya mengulang kode terakhir; duplikat di IN tak berpengaruh).
     * Yang berubah cuma cara mengambilnya, bukan apa yang dibandingkan.
     *
     * Daftar kode tetap dipotong per bagian: satu klausa IN berisi ratusan
     * parameter memancing rekompilasi rencana, dan itu justru pernah jadi
     * temuan tersendiri di sistem ini.
     */
    public function test_full_dataset_requester_parity(): void
    {
        $allKode = DB::table('N_HRIS_VW_All_Transactions2')
            ->whereNotNull('Kode_Karyawan')
            ->distinct()
            ->pluck('Kode_Karyawan')
            ->all();

        if (empty($allKode)) {
            $this->markTestSkipped('Tidak ada data untuk diuji.');
        }

        $viewAll = $this->fingerprint(
            DB::table('N_HRIS_VW_All_Transactions2')->whereNotNull('Kode_Karyawan')->get()
        );

        $svcAll = [];
        foreach (array_chunk($allKode, self::POTONGAN_KODE) as $bagian) {
            $svcAll += $this->fingerprint($this->service->forRequesters($bagian)->get());
        }

        ksort($viewAll);
        ksort($svcAll);

        $this->assertSame(count($viewAll), count($svcAll), 'Jumlah baris service != view (seluruh dataset)');
        $this->assertSame($viewAll, $svcAll, 'Ada baris berbeda antara service dan view di seluruh dataset');
    }

    public function test_approver_rows_match_view(): void
    {
        $approvers = $this->sampleApprovers();

        if (empty($approvers)) {
            $this->markTestSkipped('Tidak ada data approver untuk diuji.');
        }

        foreach ($approvers as $kode) {
            $viewRows = DB::table('N_HRIS_VW_All_Transactions2')
                ->where('Current_Approver', $kode)
                ->get();

            $serviceRows = $this->service->forApprover($kode)->get();

            $this->assertSame(
                $this->fingerprint($viewRows),
                $this->fingerprint($serviceRows),
                "Output service != view untuk approver {$kode}"
            );
        }
    }

    /**
     * Parity push-down tanggal: forRequesterBetween() (filter Tanggal_Item di HULU)
     * HARUS menghasilkan baris identik dengan forRequester() lalu difilter tanggal
     * di sisi PHP. Membuktikan optimasi pushdown tidak mengubah/menghilangkan data.
     */
    public function test_requester_between_matches_requester_filtered(): void
    {
        $requesters = $this->sampleRequesters();

        if (empty($requesters)) {
            $this->markTestSkipped('Tidak ada data requester untuk diuji.');
        }

        foreach ($requesters as $kode) {
            // Rentang nyata dari data karyawan ini.
            $range = DB::query()
                ->fromSub($this->service->forRequester($kode), 'tx')
                ->selectRaw('MIN(CAST(Tanggal_Item AS DATE)) as min_d, MAX(CAST(Tanggal_Item AS DATE)) as max_d')
                ->first();

            if (! $range || ! $range->min_d || ! $range->max_d) {
                continue;
            }

            $from = \Carbon\Carbon::parse($range->min_d)->format('Y-m-d');
            $to = \Carbon\Carbon::parse($range->max_d)->format('Y-m-d');

            /* Baseline: forRequester() (tanpa pushdown) difilter tanggal di PHP,
               DENGAN SEMANTIK YANG SAMA PERSIS seperti SQL-nya.

               Versi lama membandingkan pada ketelitian TANGGAL (format Y-m-d di
               kedua sisi), dan itu bukan kontrak yang dijanjikan. Pushdown-nya
               memakai `Tanggal_Item <= '$to'`, yang di SQL Server berarti
               `<= $to 00:00:00` — sebagian view sumber (KPI_Parameter,
               KPI_Performance) bertipe DATETIME, sehingga baris berjam pada
               tanggal `$to` memang TIDAK ikut. Perilaku itu disengaja dan
               dijaga: docblock forRequesterBetween() menyatakan ia meniru persis
               whereBetween lama supaya optimasi tak mengubah hasil, dan melarang
               menggantinya jadi `< to+1hari` tanpa keputusan tersendiri.

               Membandingkan pada ketelitian tanggal membuat uji ini menuntut
               perilaku yang justru dilarang, lalu melaporkannya sebagai
               ketidakcocokan pushdown. */
            $batasBawah = \Carbon\Carbon::parse($from);
            $batasAtas = \Carbon\Carbon::parse($to);

            $baseline = $this->fingerprint(
                collect($this->service->forRequester($kode)->get())->filter(function ($r) use ($batasBawah, $batasAtas) {
                    if (! $r->Tanggal_Item) {
                        return false;
                    }
                    $d = \Carbon\Carbon::parse($r->Tanggal_Item);

                    return $d->gte($batasBawah) && $d->lte($batasAtas);
                })
            );

            $pushed = $this->fingerprint($this->service->forRequesterBetween($kode, $from, $to)->get());

            $this->assertSame(
                $baseline,
                $pushed,
                "forRequesterBetween != forRequester+filter untuk {$kode} [{$from}..{$to}]"
            );
        }
    }

    public function test_status_by_no_transaksi_matches_view(): void
    {
        $noList = DB::table('N_HRIS_VW_All_Transactions2')
            ->whereNotNull('No_Transaksi')
            ->groupBy('No_Transaksi')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(20)
            ->pluck('No_Transaksi')
            ->all();

        if (empty($noList)) {
            $this->markTestSkipped('Tidak ada No_Transaksi untuk diuji.');
        }

        $viewRows = DB::table('N_HRIS_VW_All_Transactions2')
            ->whereIn('No_Transaksi', $noList)
            ->get();

        $serviceRows = $this->service->statusByNoTransaksi($noList);

        $this->assertSame(
            $this->fingerprint($viewRows),
            $this->fingerprint($serviceRows),
            'Output statusByNoTransaksi != view'
        );
    }
}
