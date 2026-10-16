<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopMonitoringAnalyticsService;
use App\Services\LMS\SOP\SopMonitoringService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Saringan tab Ringkasan berlaku untuk SELURUH panelnya.
 *
 * Bilah saringan yang diam-diam tak berlaku di sebagian kartu lebih berbahaya
 * daripada tidak ada saringan sama sekali: angkanya tetap terbaca masuk akal,
 * dan tak ada apa pun di layar yang memberi tahu bahwa dua kartu bersebelahan
 * sedang bicara tentang himpunan yang berbeda.
 *
 * Dua hal yang paling rawan dan karena itu diuji paling keras:
 *  1. papanRisiko() dulu menerima array saringan tapi HANYA memakai `prioritas`.
 *  2. Seluruh grafik di-cache 900 detik dengan kunci yang tak memuat saringan —
 *     hasil tersaring bisa dilayani dari cache tampilan polos.
 */
class SopRingkasanSaringanTest extends TestCase
{
    private function analitik(): SopMonitoringAnalyticsService
    {
        return app(SopMonitoringAnalyticsService::class);
    }

    /** Divisi yang benar-benar punya penugasan, tapi tak mencakup semua orang. */
    private function divisiUji(): ?string
    {
        return DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'd.Kode_Karyawan')
            ->join('View_Divisi_Sub_Divisi as v', 'k.ID_Divisi_Sub_Divisi', '=', 'v.ID_DIVISI_SUB_DIVISI')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF)
            ->whereNotNull('v.nama_divisi')
            ->groupBy('v.nama_divisi')
            ->orderByRaw('COUNT(*) ASC')
            ->limit(1)
            ->value('v.nama_divisi');
    }

    /**
     * Satu nama paket terbit, dipakai sebagai saringan berlingkup paket.
     *
     * Dulu uji-uji di bawah memakai Kategori. Kategori dicabut 28 Agu 2026,
     * tapi yang dijaga ketiganya sama sekali bukan tentang kategori: bahwa
     * papan risiko benar-benar membaca array saringannya, dan bahwa jalur
     * tersaring tidak dilayani dari cache jalur polos. Saringan `paket` ada di
     * lingkup yang sama, jadi penjaganya dipindahkan ke sana, bukan dibuang.
     */
    private function paketUji(): ?string
    {
        return DB::table('N_SOP_Master_Paket as p')
            ->where('p.Status', SopStatus::PAKET_PUBLISHED)
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->value('p.Nama_Paket');
    }

    public function test_unfiltered_summary_still_matches_the_original_queries(): void
    {
        $hariIni = Carbon::today();
        $dasar = fn () => DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF)
            ->where('d.Status_Paket', '<>', SopStatus::STATUS_SELESAI);

        $s = app(SopMonitoringService::class)->summary([]);

        // summary() ditulis ulang agar sadar-saringan. Jalur tanpa saringan tak
        // boleh ikut bergeser — itu angka yang dilihat admin tiap hari.
        $this->assertSame(
            DB::table('N_SOP_Penjadwalan_Detail')
                ->where('Flag_Aktif', SopStatus::AKTIF)->where('Flag_Cabut_Akses', SopStatus::INAKTIF)
                ->where('Status_Paket', '<>', SopStatus::STATUS_SELESAI)
                ->distinct()->count('Kode_Karyawan'),
            $s['tertunda'],
        );
        $this->assertSame(
            $dasar()->where('pj.Tipe_Tenggat', 'DEADLINE')->where('pj.Tanggal_Deadline', '<', $hariIni)->count(),
            $s['terlambat'],
        );
        $this->assertSame(
            DB::table('N_SOP_Master_Paket')
                ->where('Status', SopStatus::PAKET_PUBLISHED)->where('Flag_Aktif', SopStatus::AKTIF)->count(),
            $s['paket_aktif'],
        );
    }

    public function test_filtering_by_division_never_widens_the_summary(): void
    {
        $divisi = $this->divisiUji();
        if (! $divisi) {
            $this->markTestSkipped('Tak ada divisi dengan penugasan dokumen.');
        }

        $polos = app(SopMonitoringService::class)->summary([]);
        $sempit = app(SopMonitoringService::class)->summary(['divisi' => [$divisi]]);

        foreach (['tertunda', 'terlambat', 'jatuh_tempo', 'user_total', 'user_ditugaskan'] as $k) {
            $this->assertLessThanOrEqual($polos[$k], $sempit[$k], "Saringan justru melebarkan {$k}.");
        }
        $this->assertLessThan($polos['tertunda'], $sempit['tertunda'], 'Saringan divisi tak menyempitkan apa pun — kemungkinan tak terpasang.');
        $this->assertLessThan($polos['user_total'], $sempit['user_total'],
            'Populasi user tak ikut menyempit — saringan organisasi belum terpasang pada tabel Karyawan.');
    }

    public function test_user_ditugaskan_tak_pernah_melebihi_total_user(): void
    {
        /* Angka bawah kartu "Total User" adalah BAGIAN dari angka atasnya.
           Keduanya karena itu wajib berangkat dari populasi yang sama —
           Karyawan.Aktif='Y'. Tanpa gerbang yang sama pada sisi penugasan,
           karyawan nonaktif yang masih memegang penugasan menghasilkan
           "223 dari 958" yang mustahil dijelaskan kepada siapa pun.

           Diperiksa juga di bawah saringan, bukan hanya pada keadaan polos:
           gerbang yang lupa dipasang pada satu sisi baru terlihat begitu
           populasinya menyempit. */
        $kasus = [[]];
        if ($divisi = $this->divisiUji()) {
            $kasus[] = ['divisi' => [$divisi]];
        }

        foreach ($kasus as $f) {
            $s = app(SopMonitoringService::class)->summary($f);
            $nama = $f === [] ? 'tanpa saringan' : 'divisi '.$f['divisi'][0];

            $this->assertLessThanOrEqual($s['user_total'], $s['user_ditugaskan'],
                "Pada {$nama}, user yang ditugaskan melebihi total user.");
        }
    }

    public function test_total_user_memakai_populasi_yang_sama_dengan_jangkauan_publik(): void
    {
        /* Dua kartu di satu halaman yang sama-sama menyebut "user" tetapi
           berangkat dari populasi berbeda adalah dua angka untuk satu hal —
           dan selisih yang tak bisa dijelaskan menghentikan pemakaian
           keduanya. Predikatnya sengaja dipegang bersama. */
        $s = app(SopMonitoringService::class)->summary([]);

        $this->assertSame(
            DB::table('Karyawan')->where('Aktif', SopStatus::AKTIF)->count(),
            $s['user_total'],
        );
    }

    public function test_risk_board_finally_honours_the_package_filter(): void
    {
        $paket = $this->paketUji();
        if (! $paket) {
            $this->markTestSkipped('Tak ada paket terbit.');
        }

        $hasil = $this->analitik()->papanRisiko(['paket' => [$paket]], 50);
        if ($hasil->isEmpty()) {
            $this->markTestSkipped('Paket ini tak punya baris berisiko.');
        }

        // Inilah bug yang diperbaiki: method ini menerima array saringan tapi
        // hanya membaca `prioritas`, sehingga panel risiko tak pernah ikut
        // menyempit bersama panel lain di layar yang sama.
        $this->assertSame([$paket], $hasil->pluck('Nama_Paket')->unique()->values()->all());
    }

    public function test_risk_board_narrows_when_a_package_is_chosen(): void
    {
        $paket = $this->paketUji();
        if (! $paket) {
            $this->markTestSkipped('Tak ada paket terbit.');
        }

        $this->assertLessThanOrEqual(
            $this->analitik()->papanRisiko([], 50)->count(),
            $this->analitik()->papanRisiko(['paket' => [$paket]], 50)->count(),
        );
    }

    public function test_filtered_charts_are_not_served_from_the_unfiltered_cache(): void
    {
        $paket = $this->paketUji();
        if (! $paket) {
            $this->markTestSkipped('Tak ada paket terbit.');
        }

        /* URUTAN ARGUMENNYA: heatmap(saringan, sumbuX, halaman, perHalaman).
           Bentuk lama memanggilnya (12, 8, [...]) — angka di posisi array
           saringan. Itu TypeError, jadi ujinya tak pernah benar-benar sampai
           ke asersinya. */
        $polos = $this->analitik()->heatmap([], 'prioritas', 1, 12);
        $sempit = $this->analitik()->heatmap(['paket' => [$paket]], 'prioritas', 1, 12);

        // Kunci cache tak memuat saringan; kalau jalur tersaring tak memutus
        // cache, hasil di bawah akan identik dengan tampilan polos.
        $this->assertLessThanOrEqual(count($polos['dept']), count($sempit['dept']),
            'Jalur tersaring dilayani dari cache jalur polos.');
    }

    public function test_every_summary_panel_accepts_the_filter_without_breaking(): void
    {
        $divisi = $this->divisiUji();
        if (! $divisi) {
            $this->markTestSkipped('Tak ada divisi dengan penugasan dokumen.');
        }

        $f = ['divisi' => [$divisi]];
        $a = $this->analitik();

        // Panel yang melempar exception akan tampil sebagai kartu kosong tanpa
        // penjelasan — mode gagal yang tak terlihat sampai ada yang menyaring.
        $this->assertIsArray($a->pulse(null, $f));
        $this->assertIsArray($a->tren(Carbon::today()->subDays(90)->toDateString(), null, 'day', $f));
        $this->assertIsArray($a->agingTerlambat($f));
        $this->assertIsArray($a->distribusiWaktuSelesai($f));
        // Urutan argumen: heatmap(saringan, sumbuX, halaman, perHalaman).
        // Bentuk lama menaruh angka di posisi saringan — TypeError, jadi panel
        // ini tak pernah benar-benar ikut diperiksa oleh uji "setiap panel".
        $this->assertIsArray($a->heatmap($f, 'prioritas', 1, 12));
        $this->assertIsArray($a->konfirmasiKilat($f, 1, 25));
        $this->assertIsArray($a->mandek(7, 1, 25, $f));
    }

    public function test_trend_denominator_is_filtered_together_with_its_numerator(): void
    {
        $divisi = $this->divisiUji();
        if (! $divisi) {
            $this->markTestSkipped('Tak ada divisi dengan penugasan dokumen.');
        }

        $d = $this->analitik()->tren(
            Carbon::today()->subDays(365)->toDateString(), null, 'week', ['divisi' => [$divisi]],
        );
        if (empty($d['titik'])) {
            $this->markTestSkipped('Ledger belum cukup untuk divisi ini.');
        }

        // Penyebut se-organisasi dibagi pembilang satu divisi menghasilkan
        // coverage yang selalu terlalu rendah, dan tak ada di layar yang
        // menunjukkan kenapa.
        foreach ($d['titik'] as $t) {
            $this->assertLessThanOrEqual(
                $t['kumulatif_ditugaskan'],
                $t['kumulatif_selesai'],
                'Selesai melebihi yang ditugaskan — penyebut tak ikut tersaring.',
            );
        }
    }
}
