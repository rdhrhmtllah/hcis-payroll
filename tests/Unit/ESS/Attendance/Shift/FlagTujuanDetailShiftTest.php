<?php

namespace Tests\Unit\ESS\Attendance\Shift;

use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Aturan penentuan Flag_Approval sebuah Detail shift.
 *
 * ── Kenapa dijaga ────────────────────────────────────────────────────────────
 *
 * Aturan ini dulu tertanam di dalam perulangan yang, untuk setiap detail,
 * menanyakan Det-nya sendiri lalu memperbarui dirinya sendiri: 1 + 2N kueri di
 * dalam transaksi approval. Saat dijadikan set-based, aturannya harus tetap
 * SAMA PERSIS — dan itu tidak bisa dibuktikan oleh pemindai mana pun, hanya
 * oleh uji yang menjalankan aturannya.
 *
 * Diekstrak menjadi fungsi murni supaya bisa diuji tanpa basis data: kelas
 * layanannya sendiri menuntut sembilan dependensi di konstruktor, dan aturan
 * yang hanya bisa diuji lewat jalur ber-DB pada praktiknya tidak pernah diuji.
 *
 * ── Urutan aturannya penting ─────────────────────────────────────────────────
 *
 * "Masih menunggu" diperiksa LEBIH DULU daripada "ada yang approve". Sebuah
 * Detail dengan satu Y dan satu yang belum vote harus tetap menunggu, bukan
 * langsung disetujui — kalau urutannya terbalik, approver kedua kehilangan
 * suaranya tanpa jejak.
 */
class FlagTujuanDetailShiftTest extends TestCase
{
    /** @param list<string|null> $flag */
    private function dets(array $flag): Collection
    {
        return collect($flag)->map(fn ($f) => (object) ['Flag_Approval' => $f]);
    }

    public function test_tanpa_det_yang_menuntut_approval_detail_dibiarkan(): void
    {
        $this->assertSame([false, null], ShiftRequestService::flagTujuanDetail($this->dets([])));
    }

    public function test_semua_sudah_setuju_menjadi_y(): void
    {
        $this->assertSame([true, 'Y'], ShiftRequestService::flagTujuanDetail($this->dets(['Y', 'Y'])));
    }

    public function test_satu_setuju_di_antara_yang_menolak_sudah_cukup(): void
    {
        $this->assertSame([true, 'Y'], ShiftRequestService::flagTujuanDetail($this->dets(['T', 'Y'])));
    }

    public function test_semua_menolak_menjadi_t(): void
    {
        $this->assertSame([true, 'T'], ShiftRequestService::flagTujuanDetail($this->dets(['T', 'T'])));
    }

    public function test_masih_ada_yang_belum_vote_tetap_menunggu(): void
    {
        $this->assertSame([true, null], ShiftRequestService::flagTujuanDetail($this->dets([null, 'T'])));
    }

    /**
     * Justru ini yang paling mudah terbalik saat aturannya ditulis ulang:
     * satu Y sudah ada, tetapi masih ada yang belum vote.
     */
    public function test_sudah_ada_yang_setuju_tetapi_masih_ada_yang_belum_vote_tetap_menunggu(): void
    {
        $this->assertSame([true, null], ShiftRequestService::flagTujuanDetail($this->dets(['Y', null])));
    }

    public function test_satu_det_yang_belum_vote_tetap_menunggu(): void
    {
        $this->assertSame([true, null], ShiftRequestService::flagTujuanDetail($this->dets([null])));
    }
}
