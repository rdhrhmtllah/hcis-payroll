<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopMonitoringPeopleService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Rekam baca per item — lapisan yang selama ini tak pernah ditampilkan halaman
 * monitoring per karyawan. Isinya fakta apa adanya dari N_SOP_Progress; tidak
 * ada skor gabungan, pembanding, atau label penilaian di sini.
 */
class SopRekamBacaTest extends TestCase
{
    /** Karyawan mana pun yang punya rekam progress — tak dipatok ke satu kode. */
    private function kodeUji(): string
    {
        $kode = DB::table('N_SOP_Progress as pr')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'pr.Id_SOP_Penjadwalan_Detail')
            ->groupBy('d.Kode_Karyawan')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(1)
            ->value('d.Kode_Karyawan');

        $this->assertNotNull($kode, 'Butuh minimal satu karyawan dengan rekam progress.');

        return $kode;
    }

    private function baris(string $kode)
    {
        return DB::table('N_SOP_Progress as pr')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'pr.Id_SOP_Penjadwalan_Detail')
            ->where('d.Kode_Karyawan', $kode);
    }

    private function rekam(string $kode): array
    {
        return app(SopMonitoringPeopleService::class)->rekamBaca($kode);
    }

    public function test_returns_one_row_per_stored_reading_record(): void
    {
        $kode = $this->kodeUji();

        $hasil = $this->rekam($kode);

        $this->assertCount((clone $this->baris($kode))->count(), $hasil['item']);
    }

    public function test_every_row_carries_the_facts_the_page_needs(): void
    {
        $hasil = $this->rekam($this->kodeUji());

        foreach ([
            'Judul_Item', 'Nama_Paket', 'Tipe_Item', 'Status', 'Versi_Ke',
            'Waktu_Buka', 'Waktu_Konfirmasi', 'Durasi_Aktif_Detik',
            'Halaman_Terakhir', 'Persen_Selesai',
            'Skor_Quiz', 'Jumlah_Percobaan', 'Status_Lulus',
            'Konfirmasi_IP', 'Konfirmasi_User_Agent',
        ] as $field) {
            $this->assertArrayHasKey($field, $hasil['item'][0], "Field {$field} tak ikut dikirim.");
        }
    }

    public function test_status_composition_matches_the_stored_records(): void
    {
        $kode = $this->kodeUji();

        $harapan = (clone $this->baris($kode))
            ->groupBy('pr.Status')
            ->selectRaw('pr.Status as s, COUNT(*) as n')
            ->pluck('n', 's')
            ->map(fn ($n) => (int) $n)
            ->all();

        $aktual = collect($this->rekam($kode)['komposisi']['status'])
            ->pluck('jumlah', 'label')
            ->all();

        ksort($harapan);
        ksort($aktual);
        $this->assertSame($harapan, $aktual);
    }

    public function test_type_composition_matches_the_stored_records(): void
    {
        $kode = $this->kodeUji();

        $harapan = (clone $this->baris($kode))
            ->groupBy('pr.Tipe_Item')
            ->selectRaw('pr.Tipe_Item as t, COUNT(*) as n')
            ->pluck('n', 't')
            ->map(fn ($n) => (int) $n)
            ->all();

        $aktual = collect($this->rekam($kode)['komposisi']['tipe'])
            ->pluck('jumlah', 'label')
            ->all();

        ksort($harapan);
        ksort($aktual);
        $this->assertSame($harapan, $aktual);
    }

    public function test_each_row_identifies_its_package_by_id_not_only_by_name(): void
    {
        $hasil = $this->rekam($this->kodeUji());

        // Mengelompokkan lewat nama akan meleburkan dua paket berbeda yang
        // kebetulan senama — pengelompokan harus bersandar pada identitas.
        $this->assertArrayHasKey('Id_SOP_Paket', $hasil['item'][0]);
        $this->assertNotEmpty($hasil['item'][0]['Id_SOP_Paket']);
    }

    public function test_package_id_is_opaque_not_a_raw_database_id(): void
    {
        $hasil = $this->rekam($this->kodeUji());

        $this->assertFalse(
            ctype_digit((string) $hasil['item'][0]['Id_SOP_Paket']),
            'Id paket harus token hashid, bukan id mentah.',
        );
    }

    public function test_composition_is_ordered_largest_first_so_chips_do_not_shuffle(): void
    {
        $komposisi = $this->rekam($this->kodeUji())['komposisi'];

        foreach (['status', 'tipe'] as $sumbu) {
            $jumlah = array_column($komposisi[$sumbu], 'jumlah');
            $urut = $jumlah;
            rsort($urut);

            $this->assertSame($urut, $jumlah, "Komposisi {$sumbu} tidak terurut menurun.");
        }
    }

    public function test_totals_are_plain_sums_not_derived_scores(): void
    {
        $kode = $this->kodeUji();

        $harapan = (clone $this->baris($kode))->selectRaw('
            SUM(CAST(pr.Durasi_Aktif_Detik AS INT)) as detik,
            COUNT(DISTINCT pr.Id_SOP_Paket_Item) as item_unik,
            COUNT(DISTINCT pr.Id_SOP_Item_Versi) as versi_unik,
            SUM(CASE WHEN pr.Waktu_Buka IS NOT NULL THEN 1 ELSE 0 END) as dibuka,
            SUM(CASE WHEN pr.Waktu_Konfirmasi IS NOT NULL THEN 1 ELSE 0 END) as dikonfirmasi
        ')->first();

        $r = $this->rekam($kode)['ringkas'];

        $this->assertSame((int) $harapan->detik, $r['total_detik']);
        $this->assertSame((int) $harapan->item_unik, $r['item_unik']);
        $this->assertSame((int) $harapan->versi_unik, $r['versi_unik']);
        $this->assertSame((int) $harapan->dibuka, $r['pernah_dibuka']);
        $this->assertSame((int) $harapan->dikonfirmasi, $r['dikonfirmasi']);
    }

    public function test_version_trail_lists_only_items_the_person_met_more_than_once(): void
    {
        $kode = $this->kodeUji();

        $harapan = (clone $this->baris($kode))
            ->groupBy('pr.Id_SOP_Paket_Item')
            ->havingRaw('COUNT(DISTINCT pr.Id_SOP_Item_Versi) > 1')
            ->selectRaw('pr.Id_SOP_Paket_Item')
            ->get()
            ->count();

        $jejak = $this->rekam($kode)['jejak_versi'];

        $this->assertCount($harapan, $jejak);
        foreach ($jejak as $j) {
            $this->assertGreaterThan(1, count($j['versi']), 'Jejak versi tak boleh memuat item yang cuma satu versi.');
            $this->assertArrayHasKey('Judul_Item', $j);
        }
    }

    public function test_reading_record_stays_within_a_small_query_budget(): void
    {
        $kode = $this->kodeUji();

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->rekam($kode);

        // Komposisi & jejak versi diturunkan di PHP dari satu tarikan baris —
        // bukan satu query tambahan per kelompok.
        $this->assertLessThanOrEqual(2, $queries);
    }

    public function test_unknown_employee_yields_empty_record_not_an_error(): void
    {
        $hasil = $this->rekam('ZZ-TIDAK-ADA');

        $this->assertSame([], $hasil['item']);
        $this->assertSame(0, $hasil['ringkas']['total_rekam']);
        $this->assertSame([], $hasil['jejak_versi']);
    }
}
