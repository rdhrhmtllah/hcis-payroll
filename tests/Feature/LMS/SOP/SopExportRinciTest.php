<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopExportRinciService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Rincian per item — satu baris untuk tiap (karyawan × paket × item).
 *
 * Yang dijaga di sini bukan "angkanya bagus", melainkan tiga hal yang gampang
 * rusak diam-diam pada export selebar ini:
 *  1. Item yang BELUM PERNAH DIBUKA tetap muncul. Kalau join progress-nya inner,
 *     seluruh tunggakan hilang dari berkas dan admin membaca kepatuhan palsu.
 *  2. Urutan deterministik. OFFSET/FETCH tanpa pemecah seri membuat orang
 *     berpindah halaman antar-potongan — pelajaran yang sudah dibayar sekali
 *     di target penjadwalan.
 *  3. Definisi kolom dan isi baris berasal dari satu sumber, jadi pratinjau di
 *     layar tak mungkin berbeda bentuk dari berkas yang diunduh.
 */
class SopExportRinciTest extends TestCase
{
    private function svc(): SopExportRinciService
    {
        return app(SopExportRinciService::class);
    }

    /**
     * Bentuk join yang sama, ditulis ulang tangan sebagai pembanding independen.
     *
     * GERBANG PAKET PUBLIK IKUT DITULIS DI SINI, dan sengaja ditulis ulang
     * alih-alih memanggil SopGerbangPaket — pembanding yang meminjam kode yang
     * dibandingkannya berhenti jadi pembanding.
     *
     * Tanpa gerbang ini kedua sisi menghitung himpunan yang berbeda: service
     * membuang penugasan MANDIRI pada paket PUBLIK yang belum pernah dibuka
     * (minat, bukan kewajiban), pembanding tidak. Selisihnya nyata dan terukur
     * — 4 baris di data dev — dan uji ini sempat membacanya sebagai kerusakan
     * mesin ekspor, padahal justru aturan yang bekerja sebagaimana mestinya.
     */
    private function bandingan()
    {
        return DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->join('N_SOP_Paket_Item as i', function ($j) {
                $j->on('i.Id_SOP_Paket', '=', 'p.Id_SOP_Paket')
                    ->where('i.Flag_Aktif', '=', SopStatus::AKTIF);
            })
            ->leftJoin('N_SOP_Progress as pr', function ($j) {
                $j->on('pr.Id_SOP_Penjadwalan_Detail', '=', 'd.Id_SOP_Penjadwalan_Detail')
                    ->on('pr.Id_SOP_Paket_Item', '=', 'i.Id_SOP_Paket_Item');
            })
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'd.Kode_Karyawan')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF)
            ->where(function ($w) {
                $w->where('p.Mode_Distribusi', 'TERBATAS')
                    ->orWhereIn('pj.Sumber', ['MANUAL', 'AUTO_RULE'])
                    ->orWhere('d.Status_Paket', '<>', SopStatus::STATUS_BELUM)
                    ->orWhereExists(fn ($s) => $s->select(DB::raw(1))
                        ->from('N_SOP_Progress as pr_cek')
                        ->whereColumn('pr_cek.Id_SOP_Penjadwalan_Detail', 'd.Id_SOP_Penjadwalan_Detail')
                        ->whereNotNull('pr_cek.Waktu_Buka'));
            });
    }

    /** Karyawan dengan penugasan aktif terbanyak — bukan kode yang dipatok. */
    private function kodeUji(): string
    {
        $kode = (clone $this->bandingan())
            ->groupBy('d.Kode_Karyawan')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(1)
            ->value('d.Kode_Karyawan');

        $this->assertNotNull($kode, 'Butuh minimal satu karyawan dengan penugasan dokumen aktif.');

        return $kode;
    }

    public function test_row_count_matches_the_assignment_times_item_join(): void
    {
        $this->assertSame((clone $this->bandingan())->count(), $this->svc()->count([]));
    }

    public function test_items_never_opened_still_appear(): void
    {
        $belum = (clone $this->bandingan())->whereNull('pr.Id_SOP_Progress')->count();

        if ($belum === 0) {
            $this->markTestSkipped('Tak ada item tanpa rekam progress di basis data ini.');
        }

        $baris = $this->svc()->rows(['kodes' => [$this->kodeUji()]], 1, 500);

        // Item tanpa rekam progress adalah tunggakan. Join yang inner akan
        // menghapusnya dari berkas dan membuat kepatuhan tampak lebih baik.
        $this->assertNotEmpty(
            $baris->filter(fn ($r) => $r['status_item'] === SopStatus::ITEM_BELUM),
            'Item yang belum pernah dibuka hilang dari hasil.',
        );
    }

    public function test_every_row_carries_every_declared_column(): void
    {
        $baris = $this->svc()->rows([], 1, 5);
        $this->assertNotEmpty($baris, 'Butuh minimal satu baris untuk diuji.');

        foreach ($this->svc()->columns() as $kolom) {
            $this->assertArrayHasKey(
                $kolom['key'],
                $baris->first(),
                "Kolom {$kolom['key']} dideklarasikan tapi tak pernah diisi.",
            );
        }
    }

    public function test_column_keys_are_unique(): void
    {
        $keys = array_column($this->svc()->columns(), 'key');

        $this->assertSame($keys, array_values(array_unique($keys)), 'Ada kunci kolom kembar.');
    }

    public function test_choosing_column_groups_actually_shrinks_the_file(): void
    {
        $semua = $this->svc()->columns();
        $sebagian = $this->svc()->columns(['identitas', 'item']);

        $this->assertLessThan(count($semua), count($sebagian));
        $this->assertSame(
            ['inti', 'identitas', 'item'],
            array_values(array_unique(array_column($sebagian, 'grup'))),
        );
    }

    public function test_row_number_survives_every_column_choice(): void
    {
        // Tanpa nomor urut, berkas yang terpotong tak bisa disambung.
        $this->assertContains('no', array_column($this->svc()->columns(['kuis']), 'key'));
    }

    public function test_unknown_column_group_is_ignored_not_fatal(): void
    {
        // Permintaan lama yang tersimpan di job tetap harus bisa dijalankan
        // setelah daftar kelompok berubah.
        $kolom = $this->svc()->columns(['identitas', 'kelompok-yang-sudah-tiada']);

        $this->assertSame(['inti', 'identitas'], array_values(array_unique(array_column($kolom, 'grup'))));
    }

    public function test_no_group_selected_means_every_column(): void
    {
        $this->assertSame($this->svc()->columns(), $this->svc()->columns([]));
    }

    public function test_paging_never_repeats_or_drops_a_row(): void
    {
        $sidik = fn ($r) => $r['kode'].'|'.$r['paket'].'|'.$r['item'].'|'.$r['versi_dibaca'].'|'.$r['waktu_buka'];

        $sekaligus = $this->svc()->rows([], 1, 60)->map($sidik)->all();
        if (count($sekaligus) < 60) {
            $this->markTestSkipped('Butuh minimal 60 baris untuk menguji paginasi.');
        }

        $bertahap = array_merge(
            $this->svc()->rows([], 1, 30)->map($sidik)->all(),
            $this->svc()->rows([], 2, 30)->map($sidik)->all(),
        );

        // Tanpa pemecah seri pada ORDER BY, SQL Server bebas mengurut ulang tiap
        // potongan — baris yang sama muncul dua kali, yang lain tak pernah muncul.
        $this->assertSame($sekaligus, $bertahap);
    }

    public function test_selecting_employees_narrows_the_result_to_them(): void
    {
        $kode = $this->kodeUji();

        $baris = $this->svc()->rows(['kodes' => [$kode]], 1, 200);

        $this->assertNotEmpty($baris);
        $this->assertSame([$kode], $baris->pluck('kode')->unique()->values()->all());
    }

    public function test_selection_count_agrees_with_the_rows_it_would_write(): void
    {
        $kode = $this->kodeUji();
        $f = ['kodes' => [$kode]];

        $this->assertSame($this->svc()->count($f), $this->svc()->rows($f, 1, 5000)->count());
    }

    public function test_row_numbering_continues_across_pages(): void
    {
        if ($this->svc()->count([]) < 12) {
            $this->markTestSkipped('Butuh minimal 12 baris.');
        }

        $this->assertSame(11, $this->svc()->rows([], 2, 10)->first()['no']);
    }

    public function test_reading_a_page_costs_a_single_query(): void
    {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->svc()->rows([], 1, 50);

        $this->assertSame(1, $queries, 'Satu halaman rincian harus satu query, bukan N+1 per baris.');
    }

    public function test_unknown_employee_yields_no_rows_not_an_error(): void
    {
        $this->assertSame(0, $this->svc()->count(['kodes' => ['ZZ-TIDAK-ADA']]));
        $this->assertTrue($this->svc()->rows(['kodes' => ['ZZ-TIDAK-ADA']], 1, 10)->isEmpty());
    }
}
