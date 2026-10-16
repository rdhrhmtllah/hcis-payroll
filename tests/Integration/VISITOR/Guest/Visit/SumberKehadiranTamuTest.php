<?php

namespace Tests\Integration\VISITOR\Guest\Visit;

use App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Asal-usul jam kehadiran tamu: TAPPING, BYPASS, atau MANUAL.
 *
 * Jamnya sendiri sudah lama tampil, tetapi satu angka tidak menjawab
 * pertanyaan yang benar-benar diajukan ketika ada tamu bermasalah: apakah
 * kartunya memang terbaca palang, atau satpam yang membukakan karena tapping
 * gagal, atau jamnya tidak pernah menyentuh gerbang sama sekali.
 *
 * Empat keadaan diuji bersama dan itu disengaja. Penjaga yang hanya menagih
 * BYPASS tetap hijau seandainya seluruh cabang lain ikut berbunyi BYPASS —
 * dan kekeliruan itu justru yang paling merugikan, sebab ia menuduh petugas
 * membuka palang untuk kunjungan yang tidak pernah ia sentuh.
 */
class SumberKehadiranTamuTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tapping_bypass_manual_dan_belum_masuk_dibedakan(): void
    {
        $tapping = $this->buatTamu();
        $this->buatEfektif($tapping, masuk: now()->subHours(3));

        $bypass = $this->buatTamu();
        $this->buatEfektif($bypass, masuk: now()->subHours(2), bypass: 'Baraya');

        $manual = $this->buatTamu();
        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $manual)
            ->update(['Waktu_Aktual_Masuk' => now()->subHour()]);

        $belum = $this->buatTamu();

        $baris = $this->ambil([$tapping, $bypass, $manual, $belum]);

        $this->assertSame(VisitorKehadiranQueryService::SUMBER_TAPPING, $baris[$tapping]->Sumber_Masuk);
        $this->assertSame(VisitorKehadiranQueryService::SUMBER_BYPASS, $baris[$bypass]->Sumber_Masuk);
        $this->assertSame(VisitorKehadiranQueryService::SUMBER_MANUAL, $baris[$manual]->Sumber_Masuk);
        $this->assertNull($baris[$belum]->Sumber_Masuk, 'Tamu yang belum masuk diberi asal-usul; kolomnya jadi tak bisa dipercaya.');

        $this->assertSame('Baraya', $baris[$bypass]->Bypass_Masuk_Oleh);
    }

    /**
     * Nama petugas yang tertinggal tidak boleh ikut tampil.
     *
     * Penanda dan nama ditulis palang di kolom terpisah, dan tidak ada yang
     * mengosongkan nama ketika bypass tidak lagi berlaku. Membaca kolom nama
     * mentah-mentah karena itu menuduh petugas membuka palang untuk kunjungan
     * yang kartunya sebenarnya terbaca — penanda yang memutuskan, bukan
     * ada-tidaknya isi nama.
     */
    public function test_nama_petugas_tertinggal_tidak_ikut_tampil(): void
    {
        $nomor = $this->buatTamu();

        $this->buatEfektif($nomor, masuk: now()->subHours(3));
        DB::table('N_VISITOR_Tamu_Efektif')
            ->where('No_Transaksi', $nomor)
            ->update([
                // Penandanya padam, namanya tertinggal dari percobaan sebelumnya.
                'Flag_ByPass_CheckIn' => null,
                'User_Bypass_CheckIn' => 'Baraya',
            ]);

        $baris = $this->ambil([$nomor])[$nomor];

        $this->assertSame(VisitorKehadiranQueryService::SUMBER_TAPPING, $baris->Sumber_Masuk);
        $this->assertNull($baris->Bypass_Masuk_Oleh, 'Nama petugas tampil padahal penanda bypass-nya padam.');
    }

    /**
     * Jam dan asal-usulnya WAJIB memilih sumber yang sama.
     *
     * Baris efektif menang atas kolom Detail (lihat masukSql). Bila kedua
     * kolom terisi dan asal-usulnya berkata MANUAL, yang tampil adalah jam
     * palang dengan penjelasan catatan manual — dua fakta yang bertentangan
     * dalam satu kartu.
     */
    public function test_saat_kedua_kolom_terisi_gerbang_yang_menang(): void
    {
        $nomor = $this->buatTamu();
        $jamPalang = now()->subHours(4)->startOfSecond();

        $this->buatEfektif($nomor, masuk: $jamPalang, bypass: 'Baraya');
        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $nomor)
            ->update(['Waktu_Aktual_Masuk' => now()->subMinutes(5)]);

        $baris = $this->ambil([$nomor])[$nomor];

        $this->assertSame(VisitorKehadiranQueryService::SUMBER_BYPASS, $baris->Sumber_Masuk);
        $this->assertSame(
            $jamPalang->format('Y-m-d H:i:s'),
            Carbon::parse($baris->Waktu_Aktual_Masuk)->format('Y-m-d H:i:s'),
            'Jamnya diambil dari Detail sementara asal-usulnya dari baris efektif.',
        );
    }

    public function test_keluar_punya_penanda_sendiri(): void
    {
        // Masuk normal, keluar yang di-bypass: penanda masuk tidak boleh ikut
        // menular ke keluar, dan sebaliknya.
        $nomor = $this->buatTamu();
        $this->buatEfektif($nomor, masuk: now()->subHours(3), keluar: now()->subHour(), bypassKeluar: 'Satpam Sore');

        $baris = $this->ambil([$nomor])[$nomor];

        $this->assertSame(VisitorKehadiranQueryService::SUMBER_TAPPING, $baris->Sumber_Masuk);
        $this->assertSame(VisitorKehadiranQueryService::SUMBER_BYPASS, $baris->Sumber_Keluar);
        $this->assertNull($baris->Bypass_Masuk_Oleh);
        $this->assertSame('Satpam Sore', $baris->Bypass_Keluar_Oleh);
    }

    /**
     * Penanda yang tidak menyala tanpa jamnya.
     *
     * Palang menulis penanda dan jam di kolom terpisah, dan tidak ada yang
     * mengosongkan penanda ketika barisnya di-sync ulang. Baris ber-Flag 'Y'
     * tanpa jam masuk karena itu mungkin, dan membacanya sebagai BYPASS
     * berarti melaporkan tamu yang belum datang sebagai sudah masuk.
     */
    public function test_penanda_tanpa_jam_tetap_belum_masuk(): void
    {
        $nomor = $this->buatTamu();
        $this->buatEfektif($nomor, masuk: null, bypass: 'Baraya');

        $baris = $this->ambil([$nomor])[$nomor];

        $this->assertNull($baris->Sumber_Masuk);
        $this->assertNull($baris->Waktu_Aktual_Masuk);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  array<int, string>  $nomor
     * @return array<string, object>
     */
    private function ambil(array $nomor): array
    {
        $baris = app(VisitorPermitQueryService::class)->baseTamuQuery()
            ->whereIn('h.No_Transaksi', $nomor)
            ->get();

        $this->assertCount(count($nomor), $baris, 'Fixture tidak terambil utuh; sisa asersinya tidak berarti.');

        return $baris->keyBy('No_Transaksi')->all();
    }

    private function buatEfektif(
        string $noTransaksi,
        ?\DateTimeInterface $masuk = null,
        ?\DateTimeInterface $keluar = null,
        ?string $bypass = null,
        ?string $bypassKeluar = null,
    ): void {
        DB::table('N_VISITOR_Tamu_Efektif')->insert([
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $noTransaksi,
            'Nama_Tamu' => 'Tamu Uji Sumber',
            'Nama_PIC' => 'PIC Uji',
            'Tanggal_Kunjungan' => now()->toDateString(),
            'Status_Efektif' => 'AKTIF',
            'Disetujui_Oleh_Nama' => 'UJI',
            'Disetujui_At' => now(),
            'Riwayat_Approval' => '[]',
            'Dibuat_At' => now(),
            'Dibuat_Oleh_Nama' => 'UJI',
            'Updated_At' => now(),
            'Flag_Sync' => 'T',
            'Waktu_Aktual_Masuk' => $masuk,
            'Waktu_Aktual_Keluar' => $keluar,
            // NULL, bukan 'T': itulah yang ditulis palang saat tidak bypass.
            'Flag_ByPass_CheckIn' => $bypass === null ? null : 'Y',
            'User_Bypass_CheckIn' => $bypass,
            'Flag_ByPass_CheckOut' => $bypassKeluar === null ? null : 'Y',
            'User_Bypass_CheckOut' => $bypassKeluar,
        ]);
    }

    private function buatTamu(): string
    {
        $nomor = 'VSR-SMB-'.Str::upper(Str::random(8));
        $pic = (string) DB::table('Karyawan')->orderBy('Kode_Karyawan')->value('Kode_Karyawan');

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => $pic,
            'Status_Kunjungan' => 'SCHEDULED',
            'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => now()->toDateString(),
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => $pic,
            'Idempotency_Key' => 'SMB-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Tamu Uji Sumber',
            'Tipe_Tamu' => 'REGULAR',
            'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Uji asal-usul kehadiran',
            'Jumlah_Tamu' => 1,
        ]);

        return $nomor;
    }
}
