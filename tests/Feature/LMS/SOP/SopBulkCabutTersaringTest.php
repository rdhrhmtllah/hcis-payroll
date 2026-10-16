<?php

namespace Tests\Feature\LMS\SOP;

use App\Http\Controllers\LMS\SOP\Governance\SopAdminPenjadwalanController;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Rantai penuh: saring → "pilih semua" → cabut akses.
 *
 * Tes lain di repo ini berhenti di endpoint daftar. Yang belum pernah dibuktikan
 * adalah bahwa orang di HALAMAN 2 dan seterusnya benar-benar ikut tercabut —
 * dan itulah satu-satunya pertanyaan yang penting, karena admin hanya pernah
 * MELIHAT halaman 1. Kalau rantainya putus di mana pun, gejalanya tak terlihat:
 * layar berkata "57 orang", pesan sukses berkata "dicabut", dan orang-orang di
 * halaman berikutnya diam-diam masih memegang akses penuh.
 *
 * Menulis sungguhan ke database, lalu di-rollback oleh DatabaseTransactions.
 * cabutSatuDetail() murni DB — tak ada HTTP atau antrean yang bocor keluar.
 */
class SopBulkCabutTersaringTest extends TestCase
{
    use DatabaseTransactions;

    private const HALAMAN = 10; // sengaja kecil supaya hasilnya PASTI lebih dari satu halaman

    /**
     * Sebab pencabutan yang ikut dikirim setiap kali rantai ini dijalankan.
     *
     * WAJIB sejak DDL-2026-014 (Sebab/Catatan/Aktor di riwayat kepatuhan):
     * pencabutan akses harus bersebab, dan aturannya ditegakkan di server
     * (SopAdminPenjadwalanController::bulkCabut memvalidasi `sebab` terhadap
     * SopSebabCabut::kunciManual()), bukan hanya di dialog. Uji ini memanggil
     * controllernya langsung, jadi permintaan tanpa `sebab` bukan berbalas 422
     * melainkan MELEMPAR ValidationException — itulah bentuk merahnya sebelum
     * ini diperbaiki, dan bukan bukti bahwa pencabutannya rusak.
     *
     * SALAH_TARGET dipilih dengan sengaja: ia satu-satunya sebab yang benar
     * untuk data uji, dan ia BUKAN 'LAINNYA' — yang akan ikut menuntut
     * `catatan` lewat required_if dan menambah satu variabel yang tak ada
     * hubungannya dengan yang sedang diuji di sini.
     */
    private const SEBAB = 'SALAH_TARGET';

    /** Batch terbesar dengan keragaman divisi — supaya saringannya benar-benar menyaring. */
    private function batchUji(): int
    {
        $id = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('Karyawan as kar', 'kar.Kode_Karyawan', '=', 'd.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as div', 'kar.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->groupBy('d.Id_SOP_Penjadwalan')
            ->havingRaw('COUNT(*) > ?', [self::HALAMAN])
            ->orderByRaw('COUNT(DISTINCT div.nama_divisi) DESC, COUNT(*) DESC')
            ->limit(1)
            ->value('d.Id_SOP_Penjadwalan');

        if (! $id) {
            $this->markTestSkipped('Butuh batch berisi lebih dari satu halaman karyawan aktif.');
        }

        return (int) $id;
    }

    private function ctl(): SopAdminPenjadwalanController
    {
        return app(SopAdminPenjadwalanController::class);
    }

    private function hash(int $id): string
    {
        return Hashids::connection('custom')->encode($id);
    }

    private function req(array $query = [], array $body = []): Request
    {
        return Request::create('/x?'.http_build_query($query), $body ? 'POST' : 'GET', $body);
    }

    private function hasil($res): array
    {
        return json_decode($res->getContent(), true);
    }

    /** Kode aktif di dalam batch yang cocok dengan saringan — kebenaran dari DB, bukan dari endpoint. */
    private function seharusnya(int $batch, array $saringan): array
    {
        $q = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('Karyawan as kar', 'kar.Kode_Karyawan', '=', 'd.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as div', 'kar.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
            ->where('d.Id_SOP_Penjadwalan', $batch)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T');

        if (! empty($saringan['divisi'])) {
            $q->whereIn('div.nama_divisi', (array) $saringan['divisi']);
        }

        return $q->pluck('d.Kode_Karyawan')->sort()->values()->all();
    }

    /** Divisi yang ada di batch ini tapi TIDAK mencakup semua orangnya. */
    private function divisiSebagian(int $batch): ?string
    {
        $rows = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('Karyawan as kar', 'kar.Kode_Karyawan', '=', 'd.Kode_Karyawan')
            ->join('View_Divisi_Sub_Divisi as div', 'kar.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
            ->where('d.Id_SOP_Penjadwalan', $batch)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->groupBy('div.nama_divisi')
            ->orderByRaw('COUNT(*) DESC')
            ->selectRaw('div.nama_divisi as nama, COUNT(*) as c')
            ->get();

        $total = $rows->sum('c');

        return optional($rows->first(fn ($r) => $r->c > 0 && $r->c < $total))->nama;
    }

    private function masukSebagaiAdmin(): void
    {
        $id = DB::table('KPI_Users')->value('Id_Users');
        $this->assertNotNull($id, 'Butuh minimal satu user.');
        Auth::loginUsingId($id);
    }

    private function statusCabut(int $batch): array
    {
        return DB::table('N_SOP_Penjadwalan_Detail')
            ->where('Id_SOP_Penjadwalan', $batch)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->pluck('Flag_Cabut_Akses', 'Kode_Karyawan')
            ->all();
    }

    /**
     * Menjalankan rantai penuh dan mengembalikan [kodeDariPilihSemua, jumlahDicabut].
     */
    private function saringPilihSemuaLaluCabut(int $batch, array $saringan): array
    {
        $this->masukSebagaiAdmin();
        $query = $saringan + ['status' => 'SEMUA'];

        // 1. Halaman pertama saja — inilah yang benar-benar dilihat admin.
        $hal1 = $this->hasil($this->ctl()->batchKaryawan($this->hash($batch), $this->req($query + ['page' => 1, 'limit' => self::HALAMAN])));
        $terlihat = collect($hal1['result'])->pluck('Kode_Karyawan')->all();

        // 2. "Pilih semua N hasil".
        $kodes = $this->hasil($this->ctl()->batchKaryawanKodes($this->hash($batch), $this->req($query)))['result'];

        $this->assertGreaterThan(
            count($terlihat),
            count($kodes),
            'Hasilnya tak melampaui satu halaman — tesnya tak menguji apa pun.',
        );

        // 3. Cabut. `sebab` ikut karena server memang menuntutnya (DDL-2026-014).
        $res = $this->hasil($this->ctl()->bulkCabut(
            $this->hash($batch),
            $this->req([], ['kode_karyawan' => $kodes, 'sebab' => self::SEBAB]),
        ));
        $this->assertTrue($res['success'] ?? false, 'Pencabutan gagal: '.json_encode($res));

        return [$kodes, $res['result']['dicabut'] ?? 0];
    }

    public function test_select_all_with_a_filter_revokes_every_page_not_just_the_visible_one(): void
    {
        $batch = $this->batchUji();
        $divisi = $this->divisiSebagian($batch);
        if (! $divisi) {
            $this->markTestSkipped('Batch ini tak punya divisi yang hanya mencakup sebagian orang.');
        }

        $target = $this->seharusnya($batch, ['divisi' => [$divisi]]);
        [$kodes, $dicabut] = $this->saringPilihSemuaLaluCabut($batch, ['divisi' => [$divisi]]);

        // "Pilih semua" mengambil seluruh hasil saringan, bukan halaman aktif.
        $this->assertSame($target, collect($kodes)->sort()->values()->all());
        $this->assertSame(count($target), $dicabut, 'Jumlah tercabut tak sama dengan jumlah yang diminta.');

        $status = $this->statusCabut($batch);
        foreach ($target as $kode) {
            $this->assertSame('Y', $status[$kode], "Karyawan {$kode} lolos dari pencabutan.");
        }
    }

    public function test_a_filtered_revoke_never_touches_anyone_outside_the_filter(): void
    {
        $batch = $this->batchUji();
        $divisi = $this->divisiSebagian($batch);
        if (! $divisi) {
            $this->markTestSkipped('Batch ini tak punya divisi yang hanya mencakup sebagian orang.');
        }

        $sebelum = $this->statusCabut($batch);
        $target = $this->seharusnya($batch, ['divisi' => [$divisi]]);

        $this->saringPilihSemuaLaluCabut($batch, ['divisi' => [$divisi]]);

        // Sisi sebaliknya, dan yang paling mahal kalau salah: menyaring lalu
        // mencabut TIDAK boleh menyentuh divisi lain. Kesalahan arah ini tak
        // terlihat sama sekali di layar yang sedang tersaring.
        $sesudah = $this->statusCabut($batch);
        $luar = array_diff(array_keys($sesudah), $target);

        $this->assertNotEmpty($luar, 'Saringan mencakup semua orang — tesnya tak menguji apa pun.');
        foreach ($luar as $kode) {
            $this->assertSame($sebelum[$kode], $sesudah[$kode], "Karyawan {$kode} di luar saringan ikut berubah.");
        }
    }

    public function test_select_all_without_any_filter_revokes_the_whole_batch(): void
    {
        $batch = $this->batchUji();
        $target = $this->seharusnya($batch, []);

        [$kodes, $dicabut] = $this->saringPilihSemuaLaluCabut($batch, []);

        $this->assertSame($target, collect($kodes)->sort()->values()->all());
        $this->assertSame(count($target), $dicabut);

        $status = $this->statusCabut($batch);
        foreach ($target as $kode) {
            $this->assertSame('Y', $status[$kode], "Karyawan {$kode} lolos dari pencabutan tanpa saringan.");
        }
    }

    /**
     * Penjaga atas prasyarat uji ini sendiri.
     *
     * Menambahkan `sebab` ke keempat panggilan di atas menghijaukan berkas ini,
     * tetapi hanya benar selama server memang MENUNTUTnya. Tanpa uji ini,
     * seseorang yang kelak mencabut kewajiban `sebab` — sengaja atau tidak —
     * tak akan membuat satu pun uji merah, dan pencabutan akses kembali bisa
     * terjadi tanpa alasan yang tercatat (persis yang ditutup DDL-2026-014).
     */
    public function test_a_revoke_without_a_stated_reason_is_refused(): void
    {
        $batch = $this->batchUji();
        $this->masukSebagaiAdmin();

        $kode = DB::table('N_SOP_Penjadwalan_Detail')
            ->where('Id_SOP_Penjadwalan', $batch)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->value('Kode_Karyawan');

        $this->assertNotNull($kode, 'Batch uji ternyata kosong — uji ini tak menguji apa pun.');

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->ctl()->bulkCabut($this->hash($batch), $this->req([], ['kode_karyawan' => [$kode]]));
    }

    public function test_the_reported_count_matches_what_actually_changed_in_the_database(): void
    {
        $batch = $this->batchUji();

        $sebelum = collect($this->statusCabut($batch))->filter(fn ($v) => $v === 'Y')->count();
        [, $dicabut] = $this->saringPilihSemuaLaluCabut($batch, []);
        $sesudah = collect($this->statusCabut($batch))->filter(fn ($v) => $v === 'Y')->count();

        // Angka di pesan sukses adalah satu-satunya umpan balik yang diterima
        // admin. Kalau ia dihitung dari yang DIMINTA alih-alih dari yang benar-benar
        // berubah, kegagalan sebagian akan terbaca sebagai keberhasilan penuh.
        $this->assertSame($dicabut, $sesudah - $sebelum);
    }
}
