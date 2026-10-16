<?php

namespace Tests\Integration\VISITOR\Permit\Inspection;

use App\Http\Controllers\VISITOR\Permit\Inspection\AgendaInspeksiController;
use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Menonaktifkan pos/shift harus ikut memensiunkan pola yang menunjuknya.
 *
 * Pola yang tertinggal TIDAK menghasilkan slot apa pun — slotRentang() hanya
 * membangun dari pos dan shift yang aktif — sehingga sepintas ia terlihat tak
 * berbahaya. Yang terjadi sebenarnya lebih buruk: ia tetap muncul di layar
 * pengaturan dan ikut divalidasi saat Simpan, lalu aturan "pos harus aktif"
 * menolaknya. SELURUH pengaturan pola gagal disimpan, dengan pesan yang
 * menunjuk baris yang bahkan tidak sedang disunting.
 *
 * Buntu itu tidak bisa dibereskan dari layar: baris yang memblokir justru
 * tidak bisa diperbaiki tanpa mengaktifkan kembali posnya lebih dulu.
 */
class NonaktifPosMemensiunkanPolaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_menonaktifkan_pos_memensiunkan_polanya(): void
    {
        [$idPos, $idShift] = $this->masterUji();
        $idPola = $this->polaUji($idPos, $idShift);

        $hasil = $this->nonaktifkanPos($idPos);

        $this->assertSame('T', $this->flagPola($idPola), 'Pola masih aktif padahal posnya sudah dinonaktifkan.');
        $this->assertSame(1, $hasil['pola_dipensiunkan'] ?? 0, 'Jumlah pola yang dipensiunkan tidak dilaporkan.');
    }

    public function test_menonaktifkan_shift_memensiunkan_polanya(): void
    {
        // Pintu kedua dengan penanganan sendiri. Aturan yang hanya dipasang di
        // sisi pos akan lolos uji di atas dan bocor di sini.
        [$idPos, $idShift] = $this->masterUji();
        $idPola = $this->polaUji($idPos, $idShift);

        $hasil = $this->nonaktifkanShift($idShift);

        $this->assertSame('T', $this->flagPola($idPola));
        $this->assertSame(1, $hasil['pola_dipensiunkan'] ?? 0);
    }

    public function test_tidak_ada_pola_aktif_yang_menunjuk_master_nonaktif(): void
    {
        // Menagih KEADAAN, bukan pemanggilan method — supaya jalur ketiga yang
        // menonaktifkan master lewat cara lain ikut ketahuan.
        $sisa = DB::table('N_VISITOR_Inspeksi_Pola as pl')
            ->leftJoin(JadwalInspeksiService::T_POS.' as po', 'po.Id_Pos_Inspeksi', '=', 'pl.Id_Pos_Inspeksi')
            ->leftJoin(JadwalInspeksiService::T_SHIFT.' as sh', 'sh.Id_Shift_Inspeksi', '=', 'pl.Id_Shift_Inspeksi')
            ->where('pl.Flag_Aktif', 'Y')
            ->where(function ($q) {
                $q->whereNull('po.Id_Pos_Inspeksi')
                    ->orWhere('po.Flag_Aktif', '<>', 'Y')
                    ->orWhereNull('sh.Id_Shift_Inspeksi')
                    ->orWhere('sh.Flag_Aktif', '<>', 'Y');
            })
            ->count();

        $this->assertSame(0, $sisa, 'Ada pola aktif yang menunjuk pos/shift nonaktif; pengaturan pola tidak akan bisa disimpan.');
    }

    // ---------------------------------------------------------------- helpers

    /** @return array<string, mixed> */
    private function nonaktifkanPos(int $id): array
    {
        $balasan = app(AgendaInspeksiController::class)->ubahStatusPos(
            Request::create('/agenda-inspeksi-pos/'.$id.'/status', 'POST', ['aktif' => 0]),
            (string) $id,
        );

        return json_decode($balasan->getContent(), true)['result'] ?? [];
    }

    /** @return array<string, mixed> */
    private function nonaktifkanShift(int $id): array
    {
        $balasan = app(AgendaInspeksiController::class)->ubahStatusShift(
            Request::create('/agenda-inspeksi-shift/'.$id.'/status', 'POST', ['aktif' => 0]),
            (string) $id,
        );

        return json_decode($balasan->getContent(), true)['result'] ?? [];
    }

    private function flagPola(int $id): string
    {
        return (string) DB::table('N_VISITOR_Inspeksi_Pola')->where('Id_Inspeksi_Pola', $id)->value('Flag_Aktif');
    }

    /** @return array{0: int, 1: int} */
    private function masterUji(): array
    {
        $pos = (int) DB::table(JadwalInspeksiService::T_POS)->insertGetId([
            'Nama_Pos' => 'Pos Uji '.Str::upper(Str::random(8)),
            'Keterangan_Lokasi' => 'Fixture uji',
            'Urutan' => 900,
            'Flag_Aktif' => 'Y',
            'Created_By' => 'UJI',
        ]);

        $shift = (int) DB::table(JadwalInspeksiService::T_SHIFT)->insertGetId([
            'Nama_Shift' => 'Shift Uji '.Str::upper(Str::random(8)),
            'Jam_Mulai' => '07:00',
            'Jam_Selesai' => '15:00',
            'Urutan' => 900,
            'Flag_Aktif' => 'Y',
            'Created_By' => 'UJI',
        ]);

        return [$pos, $shift];
    }

    private function polaUji(int $idPos, int $idShift): int
    {
        return (int) DB::table('N_VISITOR_Inspeksi_Pola')->insertGetId([
            'Id_Pos_Inspeksi' => $idPos,
            'Id_Shift_Inspeksi' => $idShift,
            'Hari_Minggu' => 1,
            'Jam_Mulai' => '08:00',
            'Jam_Selesai' => '11:00',
            'Durasi_Menit' => 30,
            'Kuota_Per_Slot' => 2,
            'Flag_Aktif' => 'Y',
            'Created_By' => 'UJI',
        ]);
    }
}
