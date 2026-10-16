<?php

namespace App\Console\Commands\VISITOR\Operations\Demo;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Simulator aplikasi palang di gerbang (khusus pengembangan).
 *
 * Di produksi, kehadiran tamu dicatat aplikasi pihak ketiga di gerbang yang
 * menulis Waktu_Aktual_Masuk / Waktu_Aktual_Keluar LANGSUNG ke
 * N_VISITOR_Tamu_Efektif. HCIS tidak punya jalur tulis ke sana — route
 * check-in/check-out sengaja dicabut (commit d641d350) dan dijaga
 * VisitorRouteContractTest.
 *
 * Perintah ini meniru pihak ketiga itu supaya alurnya bisa dijalankan di
 * pengembangan. Ia karena itu menulis ke tempat yang sama persis, dan TIDAK
 * menyentuh:
 *
 *  - kolom waktu di tabel Detail — itu jalur catatan manual, bukan jalur palang;
 *  - Status_Kunjungan — itu status daur hidup pengajuan, bukan kehadiran.
 *
 * Ini alat pengembangan, BUKAN fitur. Jangan menjadikannya endpoint.
 */
class SimulasiPalang extends Command
{
    protected $signature = 'visitor:simulasi-palang
                            {--masuk= : No_Transaksi tamu yang lewat gerbang masuk}
                            {--keluar= : No_Transaksi tamu yang lewat gerbang keluar}
                            {--acak : tandai masuk satu tamu hari ini yang belum datang}
                            {--daftar : tampilkan kandidat hari ini tanpa mengubah apa pun}';

    protected $description = 'Simulasikan aplikasi palang menandai tamu masuk/keluar (khusus pengembangan)';

    private const BASIS_DATA_AMAN = ['Web_HRIS'];

    public function handle(): int
    {
        if (! $this->lingkunganAman()) {
            return self::FAILURE;
        }

        if ($this->option('daftar')) {
            return $this->tampilkanKandidat();
        }

        if ($this->option('acak')) {
            return $this->tandaiAcak();
        }

        $masuk = trim((string) $this->option('masuk'));
        $keluar = trim((string) $this->option('keluar'));

        if ($masuk === '' && $keluar === '') {
            $this->error('Sebutkan --masuk=<No_Transaksi>, --keluar=<No_Transaksi>, --acak, atau --daftar.');

            return self::FAILURE;
        }

        if ($masuk !== '') {
            return $this->tandai($masuk, 'masuk');
        }

        return $this->tandai($keluar, 'keluar');
    }

    private function lingkunganAman(): bool
    {
        $basis = DB::selectOne('SELECT DB_NAME() AS n')->n;

        if (app()->environment('production')) {
            $this->error('Simulator palang tidak boleh dijalankan pada production.');

            return false;
        }

        if (! in_array($basis, self::BASIS_DATA_AMAN, true)) {
            $this->error("Basis data '{$basis}' tidak ada dalam daftar aman; perintah dihentikan.");

            return false;
        }

        return true;
    }

    private function tandai(string $noTransaksi, string $arah): int
    {
        $baris = DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $noTransaksi)->first();

        if ($baris === null) {
            $this->error("Tidak ada baris efektif untuk {$noTransaksi}.");
            $this->line('Baris efektif lahir dari sinkronisasi HCIS; tamu yang belum disetujui belum punya baris.');

            return self::FAILURE;
        }

        $nama = $baris->Nama_Tamu ?? '-';

        if ($arah === 'masuk') {
            if ($baris->Waktu_Aktual_Masuk !== null) {
                $this->error("{$nama} sudah tercatat masuk pada {$baris->Waktu_Aktual_Masuk}.");

                return self::FAILURE;
            }

            $this->tulis($noTransaksi, ['Waktu_Aktual_Masuk' => now()]);
            $this->info("Palang menandai MASUK: {$nama} ({$noTransaksi}) pada ".now()->format('H:i').'.');

            return self::SUCCESS;
        }

        if ($baris->Waktu_Aktual_Masuk === null) {
            $this->error("{$nama} belum tercatat masuk, jadi belum bisa ditandai keluar.");

            return self::FAILURE;
        }

        if ($baris->Waktu_Aktual_Keluar !== null) {
            $this->error("{$nama} sudah tercatat keluar pada {$baris->Waktu_Aktual_Keluar}.");

            return self::FAILURE;
        }

        $this->tulis($noTransaksi, ['Waktu_Aktual_Keluar' => now()]);
        $this->info("Palang menandai KELUAR: {$nama} ({$noTransaksi}) pada ".now()->format('H:i').'.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string,mixed>  $nilai
     */
    private function tulis(string $noTransaksi, array $nilai): void
    {
        DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $noTransaksi)->update($nilai);

        // Papan dan kartu dashboard di-cache; tanpa dibuang, perubahannya tidak
        // terlihat sampai cache kedaluwarsa dan simulasi ini terasa tidak jalan.
        Cache::forget('visitor.dashboard.sekarang');
        Cache::forget(
            'visitor.dashboard.analitik.'.now()->subDays(13)->toDateString().'.'.now()->toDateString(),
        );
    }

    private function tandaiAcak(): int
    {
        $kandidat = $this->kandidatBelumMasuk()->inRandomOrder()->first();

        if ($kandidat === null) {
            $this->warn('Tidak ada tamu hari ini yang belum tercatat masuk.');

            return self::SUCCESS;
        }

        return $this->tandai($kandidat->No_Transaksi, 'masuk');
    }

    private function tampilkanKandidat(): int
    {
        $belumMasuk = $this->kandidatBelumMasuk()->get();
        $diDalam = DB::table('N_VISITOR_Tamu_Efektif')
            ->whereNotNull('Waktu_Aktual_Masuk')
            ->whereNull('Waktu_Aktual_Keluar')
            ->orderBy('Waktu_Aktual_Masuk')
            ->get(['No_Transaksi', 'Nama_Tamu', 'Waktu_Aktual_Masuk']);

        $this->line('BELUM MASUK (hari ini) — pakai --masuk=<No_Transaksi>');
        foreach ($belumMasuk as $r) {
            $this->line('   '.str_pad($r->No_Transaksi, 18).($r->Nama_Tamu ?? '-'));
        }
        if ($belumMasuk->isEmpty()) {
            $this->line('   (kosong)');
        }

        $this->newLine();
        $this->line('SEDANG DI DALAM — pakai --keluar=<No_Transaksi>');
        foreach ($diDalam as $r) {
            $this->line('   '.str_pad($r->No_Transaksi, 18).str_pad((string) ($r->Nama_Tamu ?? '-'), 28)
                .'masuk '.substr((string) $r->Waktu_Aktual_Masuk, 11, 5));
        }
        if ($diDalam->isEmpty()) {
            $this->line('   (kosong)');
        }

        return self::SUCCESS;
    }

    private function kandidatBelumMasuk(): \Illuminate\Database\Query\Builder
    {
        return DB::table('N_VISITOR_Tamu_Efektif')
            ->whereNull('Waktu_Aktual_Masuk')
            ->whereRaw('Tanggal_Kunjungan >= CAST(GETDATE() AS date)')
            ->whereRaw('Tanggal_Kunjungan < DATEADD(day, 1, CAST(GETDATE() AS date))')
            ->select('No_Transaksi', 'Nama_Tamu');
    }
}
