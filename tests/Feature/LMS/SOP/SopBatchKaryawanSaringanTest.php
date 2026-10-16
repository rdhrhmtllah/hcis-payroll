<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopPenjadwalanListService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Saringan organisasi pada daftar karyawan di dalam satu batch penjadwalan.
 *
 * Yang paling berbahaya di layar ini bukan daftarnya, melainkan tombol
 * "Pilih semua N hasil": ia memanggil endpoint LAIN (batchKaryawanKodes) dan
 * hasilnya langsung disodorkan ke pencabutan massal. Kalau kedua endpoint itu
 * tak membaca saringan yang sama persis, admin menyaring satu divisi, menekan
 * "pilih semua", lalu mencabut akses seluruh batch — dan layarnya tetap
 * memperlihatkan divisi yang tadi disaring.
 */
class SopBatchKaryawanSaringanTest extends TestCase
{
    /** Batch dengan keragaman organisasi terbesar — saringan yang tak menyaring apa pun tak membuktikan apa pun. */
    private function batchUji(): int
    {
        $id = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('Karyawan as kar', 'kar.Kode_Karyawan', '=', 'd.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as div', 'kar.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->groupBy('d.Id_SOP_Penjadwalan')
            ->orderByRaw('COUNT(DISTINCT div.nama_divisi) DESC, COUNT(*) DESC')
            ->limit(1)
            ->value('d.Id_SOP_Penjadwalan');

        $this->assertNotNull($id, 'Butuh minimal satu batch penjadwalan berisi karyawan.');

        return (int) $id;
    }

    private function svc(): SopPenjadwalanListService
    {
        return app(SopPenjadwalanListService::class);
    }

    private function semuaBaris(int $batch, array $f = [])
    {
        // limit tinggi supaya perbandingan tak tergantung paginasi
        return $this->svc()->batchKaryawan($batch, $f + ['status' => 'SEMUA'], 1, 500)['rows'];
    }

    /**
     * Satu nilai nyata untuk tiap saringan, diambil dari isi batch itu sendiri.
     *
     * @return array<string,array{0:string,1:string}> kunci saringan => [nilai, kolom hasil]
     */
    private function contohNilai(int $batch): array
    {
        $baris = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('Karyawan as kar', 'kar.Kode_Karyawan', '=', 'd.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as div', 'kar.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'kar.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan')
            ->where('d.Id_SOP_Penjadwalan', $batch)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->first([
                'div.nama_divisi', 'div.nama_sub_divisi', 'lvl.nama_level',
                'lvl.nama_jabatan', 'kar.Cabang_Untuk_Gaji',
            ]);

        return array_filter([
            'divisi' => $baris->nama_divisi ? [$baris->nama_divisi, 'Divisi'] : null,
            'dept' => $baris->nama_sub_divisi ? [$baris->nama_sub_divisi, 'Departemen'] : null,
            'level' => $baris->nama_level ? [$baris->nama_level, 'Level'] : null,
            'jabatan' => $baris->nama_jabatan ? [$baris->nama_jabatan, 'Jabatan'] : null,
            'lokasi' => $baris->Cabang_Untuk_Gaji ? [$baris->Cabang_Untuk_Gaji, 'Lokasi'] : null,
        ]);
    }

    public function test_the_row_carries_every_attribute_the_filters_act_on(): void
    {
        // Saringan yang menyempitkan atas kolom yang tak pernah ditampilkan
        // menghasilkan daftar yang tak bisa dibaca siapa pun: orangnya hilang
        // dan tak ada di baris mana pun yang menjelaskan kenapa.
        $baris = $this->semuaBaris($this->batchUji())->first();

        $this->assertNotNull($baris, 'Batch uji kosong.');
        foreach (['Divisi', 'Departemen', 'Level', 'Jabatan', 'Lokasi'] as $kolom) {
            $this->assertObjectHasProperty($kolom, $baris, "Baris tak membawa {$kolom}.");
        }
    }

    public function test_each_organisational_filter_narrows_and_every_row_matches(): void
    {
        $batch = $this->batchUji();
        $semua = $this->semuaBaris($batch);
        $diuji = 0;

        foreach ($this->contohNilai($batch) as $kunci => [$nilai, $kolom]) {
            $hasil = $this->semuaBaris($batch, [$kunci => [$nilai]]);

            $this->assertLessThanOrEqual(count($semua), count($hasil), "Saringan {$kunci} justru melebarkan daftar.");
            $this->assertGreaterThan(0, count($hasil), "Saringan {$kunci}={$nilai} tak menyisakan siapa pun.");

            foreach ($hasil as $r) {
                $this->assertSame($nilai, $r->{$kolom}, "Saringan {$kunci} meloloskan baris di luar {$nilai}.");
            }
            $diuji++;
        }

        $this->assertGreaterThan(0, $diuji, 'Tak ada saringan yang benar-benar diuji.');
    }

    /** Kedua endpoint lewat CONTROLLER, bukan service — di situlah keduanya bisa berpisah. */
    private function lewatController(string $aksi, int $batch, array $query): array
    {
        $id = \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode($batch);
        $req = \Illuminate\Http\Request::create('/x?'.http_build_query($query), 'GET');
        $res = app(\App\Http\Controllers\LMS\SOP\Governance\SopAdminPenjadwalanController::class)->{$aksi}($id, $req);

        return json_decode($res->getContent(), true)['result'] ?? [];
    }

    public function test_select_all_across_pages_obeys_the_same_filters_as_the_list(): void
    {
        $batch = $this->batchUji();

        foreach ($this->contohNilai($batch) as $kunci => [$nilai, $kolom]) {
            $query = [$kunci => [$nilai], 'status' => 'SEMUA', 'limit' => 100];

            // Sengaja lewat controller: service-nya memang satu jalur, tapi yang
            // menerjemahkan query jadi saringan ada DUA pemanggilan terpisah, dan
            // itulah yang bisa ketinggalan satu kunci tanpa ketahuan.
            $daftar = collect($this->lewatController('batchKaryawan', $batch, $query))
                ->pluck('Kode_Karyawan')->sort()->values()->all();
            $kodes = collect($this->lewatController('batchKaryawanKodes', $batch, $query))
                ->sort()->values()->all();

            // Inilah jalur yang memberi makan pencabutan massal.
            $this->assertSame(
                $daftar,
                $kodes,
                "\"Pilih semua\" tak sepakat dengan daftar saat disaring {$kunci}={$nilai}.",
            );
            $this->assertNotEmpty($kodes, "Saringan {$kunci}={$nilai} lewat controller tak menyisakan siapa pun.");
        }
    }

    public function test_combining_two_filters_never_widens_the_result(): void
    {
        $batch = $this->batchUji();
        $contoh = $this->contohNilai($batch);

        if (count($contoh) < 2) {
            $this->markTestSkipped('Butuh dua atribut organisasi yang terisi.');
        }

        [$k1, $k2] = array_slice(array_keys($contoh), 0, 2);
        $gabung = $this->semuaBaris($batch, [$k1 => [$contoh[$k1][0]], $k2 => [$contoh[$k2][0]]]);

        foreach ([$k1, $k2] as $k) {
            $this->assertLessThanOrEqual(
                count($this->semuaBaris($batch, [$k => [$contoh[$k][0]]])),
                count($gabung),
                'Dua saringan bersamaan menghasilkan lebih banyak orang daripada satu saringan.',
            );
        }
    }

    public function test_an_unfiltered_list_is_left_exactly_as_it_was(): void
    {
        $batch = $this->batchUji();

        // Jalur tanpa saringan adalah yang dilihat admin tiap hari; menambah
        // saringan tak boleh menggeser satu baris pun di sana.
        $this->assertSame(
            DB::table('N_SOP_Penjadwalan_Detail')
                ->where('Id_SOP_Penjadwalan', $batch)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->where('Flag_Cabut_Akses', 'T')
                ->count(),
            $this->svc()->batchKaryawan($batch, ['status' => 'SEMUA'], 1, 500)['total'],
        );
    }

    public function test_filters_still_respect_the_revoked_access_bucket(): void
    {
        $batch = $this->batchUji();
        $contoh = $this->contohNilai($batch);
        $kunci = array_key_first($contoh);

        // Saringan organisasi menumpuk DI ATAS saringan status, tak menggantikannya.
        $hasil = $this->svc()->batchKaryawan($batch, [$kunci => [$contoh[$kunci][0]], 'status' => 'DICABUT'], 1, 500)['rows'];

        foreach ($hasil as $r) {
            $this->assertSame('Y', $r->Flag_Cabut_Akses);
        }
    }
}
