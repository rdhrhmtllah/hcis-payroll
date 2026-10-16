<?php

namespace Tests\Feature\LMS\SOP\Uat;

use App\Services\LMS\SOP\SopPenjadwalanService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\Feature\LMS\SOP\Uat\Support\UatDunia;
use Tests\TestCase;

/**
 * Pencabutan akses massal — perilaku DIKUNCI sebelum dioptimalkan.
 *
 * bulkCabut() mengirim 6,1 kueri PER ORANG (terukur: 20 orang = 122 kueri), dan
 * di dalam SATU transaksi. Yang membuatnya mahal bukan kuerinya melainkan
 * jumlah bolak-baliknya — satu kueri sepele pun berbiaya 72 ms ke basis data dev.
 *
 * Berkas ini ada untuk memastikan bentuk berbasis-himpunan menghasilkan
 * keadaan akhir yang PERSIS SAMA. Optimasi yang mengubah hasil bukan optimasi,
 * dan rollup kepatuhan adalah tempat kesalahan semacam itu paling sulit
 * terlihat: angkanya tetap masuk akal, hanya salah.
 */
class CabutMassalTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{dunia:UatDunia, kode:list<string>, penjadwalan:int} */
    private function duniaBanyakOrang(int $jumlah = 4): array
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan keenam hak akses halaman dokumen.');
        }

        $kode = DB::table('Karyawan')->where('Aktif', 'Y')->whereNotNull('Kode_Karyawan')
            ->orderBy('Kode_Karyawan')->limit($jumlah)->pluck('Kode_Karyawan')
            ->map(fn ($k) => trim((string) $k))->all();

        if (count($kode) < $jumlah) {
            $this->markTestSkipped('Karyawan aktif kurang dari '.$jumlah.'.');
        }

        $dunia = UatDunia::mulai((int) $admin->Id_Users)
            ->buatDokumenTerbit()
            ->buatPaket()
            ->tambahItem('Dokumen Satu')
            ->tambahItem('Dokumen Dua')
            ->terbitkanPaket();

        $hasil = app(SopPenjadwalanService::class)->assign($dunia->idPaket, $kode, [
            'nama_batch' => $dunia->nama('Batch Cabut'),
            'sumber' => 'MANUAL',
        ], (int) $admin->Id_Users);

        return [
            'dunia' => $dunia,
            'kode' => $kode,
            'penjadwalan' => (int) $hasil['penjadwalan_id'],
            'userId' => (int) $admin->Id_Users,
        ];
    }

    public function test_cabut_massal_menandai_seluruh_detail_dan_menulis_ledger(): void
    {
        ['kode' => $kode, 'penjadwalan' => $penjadwalan, 'userId' => $userId] = $this->duniaBanyakOrang();

        $hasil = app(SopPenjadwalanService::class)->bulkCabut($penjadwalan, $kode, $userId);

        $this->assertSame(count($kode), $hasil['dicabut']);

        $belumTercabut = DB::table('N_SOP_Penjadwalan_Detail')
            ->where('Id_SOP_Penjadwalan', $penjadwalan)
            ->where('Flag_Cabut_Akses', 'T')
            ->count();

        $this->assertSame(0, $belumTercabut, 'Ada penugasan yang lolos dari pencabutan massal.');

        $ledger = DB::table('N_SOP_Kepatuhan_Riwayat as r')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'r.Id_SOP_Penjadwalan_Detail')
            ->where('d.Id_SOP_Penjadwalan', $penjadwalan)
            ->where('r.Peristiwa', SopStatus::RWT_DICABUT)
            ->count();

        $this->assertSame(count($kode), $ledger,
            'Ledger DICABUT tidak ditulis untuk setiap orang. Tanpa itu, pencabutan tak '
            .'punya jejak dan audit kepatuhan kehilangan alasan orang menghilang dari angka.');
    }

    public function test_rollup_target_turun_persis_sebanyak_yang_dicabut(): void
    {
        ['dunia' => $dunia, 'kode' => $kode, 'penjadwalan' => $penjadwalan, 'userId' => $userId]
            = $this->duniaBanyakOrang();

        $sebelum = DB::table('N_SOP_Coverage_Rollup')
            ->where('Id_SOP_Paket', $dunia->idPaket)
            ->pluck('Total_Target', 'Id_SOP_Item_Versi');

        $this->assertNotEmpty($sebelum, 'Rollup tak terbentuk saat assign; uji ini tak menguji apa pun.');

        /* Penurunannya dihitung dari JUMLAH BARIS PROGRES, bukan jumlah orang.
           Satu versi dokumen bisa disajikan lebih dari satu item dalam paket yang
           sama, dan tiap item menaikkan target versi itu sekali — jadi seorang
           karyawan bisa menyumbang lebih dari satu ke target yang sama. Harapan
           "turun sebanyak jumlah orang" terlihat masuk akal dan salah. */
        $turunSeharusnya = DB::table('N_SOP_Progress as pr')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'pr.Id_SOP_Penjadwalan_Detail')
            ->where('d.Id_SOP_Penjadwalan', $penjadwalan)
            ->whereNotNull('pr.Id_SOP_Item_Versi')
            ->select('pr.Id_SOP_Item_Versi', DB::raw('COUNT(*) as n'))
            ->groupBy('pr.Id_SOP_Item_Versi')
            ->pluck('n', 'Id_SOP_Item_Versi');

        app(SopPenjadwalanService::class)->bulkCabut($penjadwalan, $kode, $userId);

        $sesudah = DB::table('N_SOP_Coverage_Rollup')
            ->where('Id_SOP_Paket', $dunia->idPaket)
            ->pluck('Total_Target', 'Id_SOP_Item_Versi');

        foreach ($sebelum as $idVersi => $target) {
            $this->assertSame(
                max(0, (int) $target - (int) ($turunSeharusnya[$idVersi] ?? 0)),
                (int) $sesudah[$idVersi],
                "Total_Target versi {$idVersi} tidak turun sebanyak baris progres yang dicabut. "
                .'Angka kepatuhan item-level dan paket-level akan permanen berbeda di layar yang sama.'
            );
        }
    }

    public function test_target_tidak_pernah_menjadi_negatif(): void
    {
        ['dunia' => $dunia, 'kode' => $kode, 'penjadwalan' => $penjadwalan, 'userId' => $userId]
            = $this->duniaBanyakOrang();

        // Dicabut DUA KALI. Yang kedua tak menemukan detail aktif, jadi tak boleh
        // menurunkan apa pun lagi.
        app(SopPenjadwalanService::class)->bulkCabut($penjadwalan, $kode, $userId);
        app(SopPenjadwalanService::class)->bulkCabut($penjadwalan, $kode, $userId);

        $negatif = DB::table('N_SOP_Coverage_Rollup')
            ->where('Id_SOP_Paket', $dunia->idPaket)
            ->where(function ($q) {
                $q->where('Total_Target', '<', 0)->orWhere('Jumlah_Dikonfirmasi', '<', 0);
            })
            ->count();

        $this->assertSame(0, $negatif, 'Rollup jatuh ke angka negatif setelah pencabutan berulang.');
    }

    /**
     * Offboarding: satu orang dicabut dari SELURUH paketnya sekaligus.
     *
     * Jalur ini yang paling menuntut pengelompokan rollup per (paket, versi).
     * Versi dokumen yang sama bisa disajikan beberapa paket; menurunkan rollup
     * hanya berdasarkan versi akan mengurangi target paket yang TIDAK dicabut —
     * kesalahan yang tak pernah terlihat karena angkanya tetap masuk akal.
     */
    public function test_cabut_lintas_paket_hanya_menurunkan_paket_yang_dicabut(): void
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan keenam hak akses halaman dokumen.');
        }
        $userId = (int) $admin->Id_Users;

        $kode = trim((string) DB::table('Karyawan')->where('Aktif', 'Y')
            ->whereNotNull('Kode_Karyawan')->orderBy('Kode_Karyawan')->value('Kode_Karyawan'));

        /* DUA paket yang menyajikan dokumen yang SAMA. Itulah bentuk yang
           membuat pengelompokan salah menjadi terlihat. */
        $dunia = UatDunia::mulai($userId)->buatDokumenTerbit()
            ->buatPaket()->tambahItem('Dokumen Satu')->terbitkanPaket()->tugaskanKe($kode);

        $lain = $dunia->paketTambahan('Paket Lain');
        $dunia->terbitkanPaketLain($lain['paket']);
        app(SopPenjadwalanService::class)->assign($lain['paket'], [$kode], [
            'nama_batch' => $dunia->nama('Batch Lain'), 'sumber' => 'MANUAL',
        ], $userId);

        $targetLainSebelum = DB::table('N_SOP_Coverage_Rollup')
            ->where('Id_SOP_Paket', $lain['paket'])->sum('Total_Target');

        $hasil = app(SopPenjadwalanService::class)->cabutAkses($kode, $dunia->idPaket, $userId);

        $this->assertGreaterThan(0, $hasil['dicabut'], 'Tak ada yang tercabut; uji ini tak menguji apa pun.');

        $targetLainSesudah = DB::table('N_SOP_Coverage_Rollup')
            ->where('Id_SOP_Paket', $lain['paket'])->sum('Total_Target');

        $this->assertSame((int) $targetLainSebelum, (int) $targetLainSesudah,
            'Mencabut akses pada satu paket ikut menurunkan target paket LAIN yang '
            .'menyajikan dokumen yang sama. Angka kepatuhan paket itu jadi salah '
            .'tanpa ada yang menyentuhnya.');
    }

    public function test_token_kuis_dimatikan_barisnya_tetap_ada(): void
    {
        ['penjadwalan' => $penjadwalan, 'kode' => $kode, 'userId' => $userId] = $this->duniaBanyakOrang();

        $sebelum = DB::table('N_SOP_Penjadwalan_Integrasi as i')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'i.Id_SOP_Penjadwalan_Detail')
            ->where('d.Id_SOP_Penjadwalan', $penjadwalan)
            ->count();

        app(SopPenjadwalanService::class)->bulkCabut($penjadwalan, $kode, $userId);

        $sesudah = DB::table('N_SOP_Penjadwalan_Integrasi as i')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'i.Id_SOP_Penjadwalan_Detail')
            ->where('d.Id_SOP_Penjadwalan', $penjadwalan)
            ->count();

        $this->assertSame($sebelum, $sesudah,
            'Baris integrasi ikut terhapus. Ia sengaja dipertahankan untuk audit — '
            .'yang dimatikan Flag_Aktif-nya, bukan barisnya.');

        $masihAktif = DB::table('N_SOP_Penjadwalan_Integrasi as i')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'i.Id_SOP_Penjadwalan_Detail')
            ->where('d.Id_SOP_Penjadwalan', $penjadwalan)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->count();

        $this->assertSame(0, $masihAktif, 'Token kuis masih aktif setelah aksesnya dicabut.');
    }
}
