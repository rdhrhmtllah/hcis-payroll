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
 * Menghapus pos/shift inspeksi: bersih dibuang, bekas disimpan.
 *
 * SHE meminta pos yang tidak terpakai benar-benar hilang, bukan menumpuk
 * sebagai daftar nonaktif. Tetapi pos yang PERNAH menjadi tempat pemeriksaan
 * tidak boleh lenyap: tiga tabel menunjuknya lewat foreign key, dan salah
 * satunya riwayat booking yang mencatat siapa diperiksa di mana. Menghapusnya
 * berarti menghapus jawaban atas pertanyaan yang baru akan ditanyakan
 * berbulan-bulan kemudian — dan basis data akan menolaknya dengan galat FK
 * yang tak berarti apa-apa bagi penggunanya.
 *
 * Jadi yang dijaga di sini bukan "boleh hapus atau tidak", melainkan bahwa
 * keduanya menghasilkan sesuatu yang MASUK AKAL bagi SHE: bersih benar-benar
 * hilang, bekas berhenti muncul sebagai pilihan sambil tetap bisa dibaca.
 */
class HapusMasterInspeksiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pos_yang_belum_pernah_dipakai_benar_benar_dihapus(): void
    {
        $id = $this->buatPos();

        $hasil = $this->hapusPos($id);

        $this->assertTrue($hasil['dihapus'] ?? false);
        $this->assertDatabaseMissing(JadwalInspeksiService::T_POS, ['Id_Pos_Inspeksi' => $id]);
    }

    public function test_pos_yang_punya_booking_tidak_dihapus_melainkan_dinonaktifkan(): void
    {
        $id = $this->buatPos();
        $this->buatBookingDiPos($id);

        $hasil = $this->hapusPos($id);

        $this->assertFalse($hasil['dihapus'] ?? true, 'Pos berisi riwayat booking justru dihapus.');
        $this->assertDatabaseHas(JadwalInspeksiService::T_POS, ['Id_Pos_Inspeksi' => $id, 'Flag_Aktif' => 'T']);
    }

    public function test_pos_yang_masih_dipakai_pola_tidak_dihapus(): void
    {
        $id = $this->buatPos();
        $idShift = $this->buatShift();
        $this->buatPola($id, $idShift);

        $hasil = $this->hapusPos($id);

        $this->assertFalse($hasil['dihapus'] ?? true);
        $this->assertDatabaseHas(JadwalInspeksiService::T_POS, ['Id_Pos_Inspeksi' => $id, 'Flag_Aktif' => 'T']);
    }

    public function test_shift_yang_belum_pernah_dipakai_benar_benar_dihapus(): void
    {
        // Pintu kedua dengan penanganan sendiri.
        $id = $this->buatShift();

        $hasil = $this->hapusShift($id);

        $this->assertTrue($hasil['dihapus'] ?? false);
        $this->assertDatabaseMissing(JadwalInspeksiService::T_SHIFT, ['Id_Shift_Inspeksi' => $id]);
    }

    public function test_shift_yang_masih_dipakai_pola_tidak_dihapus(): void
    {
        $idPos = $this->buatPos();
        $id = $this->buatShift();
        $this->buatPola($idPos, $id);

        $hasil = $this->hapusShift($id);

        $this->assertFalse($hasil['dihapus'] ?? true);
        $this->assertDatabaseHas(JadwalInspeksiService::T_SHIFT, ['Id_Shift_Inspeksi' => $id, 'Flag_Aktif' => 'T']);
    }

    public function test_pos_yang_tidak_ada_dibalas_404(): void
    {
        $balasan = app(AgendaInspeksiController::class)
            ->hapusPos(Request::create('/x', 'POST'), '99999999');

        $this->assertSame(404, $balasan->getStatusCode());
    }

    // ---------------------------------------------------------------- helpers

    /** @return array<string, mixed> */
    private function hapusPos(int $id): array
    {
        $balasan = app(AgendaInspeksiController::class)
            ->hapusPos(Request::create('/agenda-inspeksi-pos/'.$id.'/hapus', 'POST'), (string) $id);

        return json_decode($balasan->getContent(), true)['result'] ?? [];
    }

    /** @return array<string, mixed> */
    private function hapusShift(int $id): array
    {
        $balasan = app(AgendaInspeksiController::class)
            ->hapusShift(Request::create('/agenda-inspeksi-shift/'.$id.'/hapus', 'POST'), (string) $id);

        return json_decode($balasan->getContent(), true)['result'] ?? [];
    }

    private function buatPos(): int
    {
        return (int) DB::table(JadwalInspeksiService::T_POS)->insertGetId([
            'Nama_Pos' => 'Pos Hapus '.Str::upper(Str::random(8)),
            'Urutan' => 950,
            'Flag_Aktif' => 'Y',
            'Created_By' => 'UJI',
        ]);
    }

    private function buatShift(): int
    {
        return (int) DB::table(JadwalInspeksiService::T_SHIFT)->insertGetId([
            'Nama_Shift' => 'Shift Hapus '.Str::upper(Str::random(8)),
            'Jam_Mulai' => '07:00',
            'Jam_Selesai' => '15:00',
            'Urutan' => 950,
            'Flag_Aktif' => 'Y',
            'Created_By' => 'UJI',
        ]);
    }

    private function buatPola(int $idPos, int $idShift): void
    {
        DB::table('N_VISITOR_Inspeksi_Pola')->insert([
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

    private function buatBookingDiPos(int $idPos): void
    {
        // Permit yang BELUM punya booking aktif. Ada indeks unik tersaring satu
        // booking TERJADWAL per permit, jadi memungut permit sembarangan membuat
        // uji ini gagal karena fixture-nya - bukan karena yang sedang diuji.
        $permit = DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
            ->whereNull('h.No_Transaksi_Tamu')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('N_VISITOR_Inspeksi_Booking as b')
                    ->whereColumn('b.No_Transaksi_Permit', 'h.No_Transaksi')
                    ->where('b.Status', JadwalInspeksiService::TERJADWAL);
            })
            ->orderByDesc('h.Id_Transaksi_Permit')
            ->value('h.No_Transaksi');

        $this->assertNotNull($permit, 'Tidak ada permit untuk digantungi booking uji.');

        DB::table('N_VISITOR_Inspeksi_Booking')->insert([
            'No_Transaksi_Permit' => $permit,
            'Id_Pos_Inspeksi' => $idPos,
            'Waktu_Mulai' => now()->addDay()->setTime(9, 0),
            'Waktu_Selesai' => now()->addDay()->setTime(9, 30),
            'Status' => JadwalInspeksiService::TERJADWAL,
            // Snapshot nama pos NOT NULL: booking menyimpan salinan namanya saat
            // dipesan supaya riwayat tetap terbaca walau posnya kemudian berubah
            // nama - justru itulah yang membuat pos tak boleh dihapus begitu saja.
            'Snapshot_Nama_Pos' => 'Pos Uji Hapus',
            'Created_By' => 'UJI',
        ]);
    }
}
