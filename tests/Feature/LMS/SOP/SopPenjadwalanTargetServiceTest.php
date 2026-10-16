<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopPenjadwalanTargetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit pemilihan target penjadwalan SOP.
 *
 * Data uji disisipkan ke tabel nyata lalu di-rollback oleh DatabaseTransactions —
 * cacat yang diuji hanya muncul pada volume dan pola data tertentu, jadi tak bisa
 * dibuktikan hanya dengan data dev apa adanya.
 */
class SopPenjadwalanTargetServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const PREFIX_UJI = 'ZZDUP';

    private function filterKosong(): array
    {
        return [
            'q' => null, 'dept' => [], 'divisi' => [], 'level' => [],
            'jabatan' => [], 'lokasi' => [], 'tipe' => 'Semua',
        ];
    }

    /** @param string|null $nama null = tiap karyawan bernama unik */
    private function sisipkanKaryawanUji(int $jumlah, ?string $nama = null): void
    {
        $perusahaan = DB::table('Karyawan')->value('Kode_Perusahaan');

        $baris = [];
        for ($i = 0; $i < $jumlah; $i++) {
            $urut = str_pad((string) $i, 5, '0', STR_PAD_LEFT);
            $baris[] = [
                'Kode_Perusahaan' => $perusahaan,
                'Kode_Karyawan' => self::PREFIX_UJI.$urut,
                'Nama' => $nama ?? ('ZZ KARYAWAN UJI '.$urut),
                'Aktif' => 'Y',
            ];
        }

        foreach (array_chunk($baris, 200) as $potong) {
            DB::table('Karyawan')->insert($potong);
        }
    }

    private function idPaketPublished(): int
    {
        return (int) DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', 'Y')
            ->where('Status', 'PUBLISHED')
            ->value('Id_SOP_Paket');
    }

    /** Telusuri seluruh halaman persis seperti yang dilakukan UI. */
    private function kumpulkanLintasHalaman(SopPenjadwalanTargetService $svc, array $f, int $limit): array
    {
        [, $total] = $svc->kandidat($f, 1, $limit, null);

        $kodes = [];
        for ($page = 1; $page <= (int) ceil($total / $limit); $page++) {
            [$rows] = $svc->kandidat($f, $page, $limit, null);
            foreach ($rows as $r) {
                $kodes[] = $r->Kode_Karyawan;
            }
        }

        return [$kodes, $total];
    }

    public function test_candidate_paging_never_repeats_a_person_across_pages(): void
    {
        $this->sisipkanKaryawanUji(40, 'ZZ KARYAWAN BERNAMA KEMBAR');

        $svc = app(SopPenjadwalanTargetService::class);
        [$kodes] = $this->kumpulkanLintasHalaman($svc, $this->filterKosong(), 10);

        $duplikat = count($kodes) - count(array_unique($kodes));

        $this->assertSame(0, $duplikat, 'Ada karyawan yang muncul lebih dari sekali saat menyisir halaman.');
    }

    public function test_candidate_paging_reaches_every_person_that_will_be_assigned(): void
    {
        $this->sisipkanKaryawanUji(40, 'ZZ KARYAWAN BERNAMA KEMBAR');

        $svc = app(SopPenjadwalanTargetService::class);
        $f = $this->filterKosong();

        [$kodes, $total] = $this->kumpulkanLintasHalaman($svc, $f, 10);
        $terlihat = array_unique($kodes);
        $akanDiassign = $svc->resolveTargets($f);

        $tidakPernahTampil = array_values(array_diff($akanDiassign, $terlihat));

        $this->assertSame(
            [],
            $tidakPernahTampil,
            'Ada karyawan yang tak pernah muncul di halaman mana pun, jadi mustahil dipilih manual.',
        );
        $this->assertCount($total, $terlihat);
    }

    public function test_summary_counts_every_pasted_code_beyond_the_five_hundred_mark(): void
    {
        $this->sisipkanKaryawanUji(520);

        $svc = app(SopPenjadwalanTargetService::class);
        $semuaKode = $svc->resolveTargets($this->filterKosong());

        $this->assertGreaterThan(500, count($semuaKode), 'Skenario butuh lebih dari 500 kode.');

        $ringkas = $svc->hitungRingkas([], $semuaKode, $this->idPaketPublished());

        $this->assertSame(
            count($semuaKode),
            $ringkas['total'],
            'Pratinjau memotong daftar kode, padahal assign() memproses semuanya.',
        );
    }

    public function test_summary_keeps_new_and_already_assigned_adding_up_beyond_the_cap(): void
    {
        $this->sisipkanKaryawanUji(520);

        $svc = app(SopPenjadwalanTargetService::class);
        $semuaKode = $svc->resolveTargets($this->filterKosong());

        $ringkas = $svc->hitungRingkas([], $semuaKode, $this->idPaketPublished());

        $this->assertSame(
            $ringkas['total'],
            $ringkas['baru'] + $ringkas['sudah_assign'],
            'Rincian baru + sudah_assign harus menjumlah ke total.',
        );
    }

    /** Cabang mode-unit ikut ditata ulang saat plafon 500 dicabut — ini pagarnya. */
    public function test_unit_filter_summary_matches_exactly_what_will_be_assigned(): void
    {
        $this->sisipkanKaryawanUji(120);

        $svc = app(SopPenjadwalanTargetService::class);
        $f = $this->filterKosong();

        $ringkas = $svc->hitungRingkas($f, null, $this->idPaketPublished());

        $this->assertSame(
            count($svc->resolveTargets($f)),
            $ringkas['total'],
            'Angka pratinjau mode unit harus sama dengan jumlah yang benar-benar di-assign.',
        );
        $this->assertSame($ringkas['total'], $ringkas['baru'] + $ringkas['sudah_assign']);
    }

    public function test_paste_validation_reports_how_many_codes_it_refused_to_process(): void
    {
        $kode = [];
        for ($i = 0; $i < 1200; $i++) {
            $kode[] = 'ZZPASTE'.str_pad((string) $i, 6, '0', STR_PAD_LEFT);
        }

        $hasil = app(SopPenjadwalanTargetService::class)->validasiTempel(implode("\n", $kode));

        $this->assertArrayHasKey('terpotong', $hasil, 'Kode yang dibuang harus dilaporkan, bukan hilang diam-diam.');
        $this->assertSame(200, $hasil['terpotong']);
    }

    public function test_paste_validation_reports_zero_truncation_when_everything_fits(): void
    {
        $hasil = app(SopPenjadwalanTargetService::class)->validasiTempel("ZZPASTE000001\nZZPASTE000002");

        $this->assertSame(0, $hasil['terpotong']);
    }
}
