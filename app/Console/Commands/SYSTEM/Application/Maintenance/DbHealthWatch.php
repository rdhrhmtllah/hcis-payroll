<?php

namespace App\Console\Commands\SYSTEM\Application\Maintenance;

use App\Services\SYSTEM\Audit\Activity\GerbangDiagnostikRealtime;
use App\Services\SYSTEM\Audit\Activity\PembacaKunciLangsung;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Memantau kunci & permintaan aktif SELAMA satu sesi uji — hanya di dev.
 *
 * Bedanya dengan db:health-snapshot, dan kenapa perintah ini perlu ada:
 * snapshot itu sekali jalan DAN berambang (hanya menulis bila ada blocking
 * atau permintaan aktif >= 20). Dalam uji terkendali berisi dua orang, ambang
 * itu tidak pernah tersentuh — jadi tabelnya tetap kosong persis pada keadaan
 * yang ingin diukur. Terbukti: N_HRIS_DB_Health_Snapshot punya 0 baris meski
 * perintahnya sudah lama ada.
 *
 * Perintah ini merekam TIAP sampel tanpa ambang, sehingga sesi uji apa pun —
 * seramai atau sesepi apa pun — meninggalkan jejak yang bisa ditinjau setelah
 * ujinya selesai.
 *
 * Contoh:
 *   php artisan db:health-watch --detik=120 --interval=2
 *   php artisan db:health-watch --detik=60 --hanya-blokir
 */
class DbHealthWatch extends Command
{
    protected $signature = 'db:health-watch
                            {--detik=60 : Lama pemantauan dalam detik}
                            {--interval= : Jarak antar-sampel dalam detik (baku dari config)}
                            {--hanya-blokir : Hanya tulis sampel yang memuat blocking}
                            {--tanpa-tulis : Tampilkan di layar saja, tidak menulis ke tabel}';

    protected $description = 'Pantau lock & permintaan aktif SQL Server selama sesi uji (HANYA lingkungan pengembangan).';

    public function handle(
        GerbangDiagnostikRealtime $gerbang,
        PembacaKunciLangsung $pembaca,
    ): int {
        $tertutup = $gerbang->alasanTertutup();
        if ($tertutup !== null) {
            $this->error('Pemantauan realtime tertutup: '.$tertutup);
            $this->line('Ini disengaja. Di produksi tiap sampel memakai satu koneksi dari');
            $this->line('pool yang sama yang sedang habis — ia menambah beban tepat pada');
            $this->line('saat sistemnya paling rapuh.');

            return self::FAILURE;
        }

        $detik = max(1, (int) $this->option('detik'));
        $interval = max(1, (int) ($this->option('interval')
            ?: config('request-diagnostics.realtime.interval_detik', 2)));
        $hanyaBlokir = (bool) $this->option('hanya-blokir');
        $menulis = ! $this->option('tanpa-tulis');

        if (! config('request-diagnostics.tag_session', false)) {
            $this->warn('REQUEST_DIAGNOSTICS_TAG_SESSION mati — baris DMV tidak akan');
            $this->warn('bisa dikaitkan ke controller mana pun. Nyalakan dulu bila yang');
            $this->warn('dicari adalah "fitur mana yang mengunci".');
        }

        $selesaiPada = microtime(true) + $detik;
        $sampelKe = 0;
        $sampelBerblokir = 0;
        $barisDitulis = 0;
        $puncakSesi = 0;
        $puncakTidurBertransaksi = 0;

        $this->info("Memantau {$detik} dtk, tiap {$interval} dtk. Ctrl+C untuk berhenti lebih awal.");
        $this->newLine();

        while (microtime(true) < $selesaiPada) {
            $sampelKe++;

            try {
                $s = $pembaca->sampel();
            } catch (\Throwable $e) {
                // Basis data tak terjangkau saat memantau basis data adalah
                // temuan, bukan alasan berhenti.
                $this->error(now()->format('H:i:s').'  GAGAL BACA: '.$e->getMessage());
                sleep($interval);

                continue;
            }

            $adaBlokir = count($s['rantai_blokir']) > 0;
            $sampelBerblokir += $adaBlokir ? 1 : 0;
            $puncakSesi = max($puncakSesi, (int) $s['koneksi']['total_sesi']);
            $puncakTidurBertransaksi = max($puncakTidurBertransaksi, (int) $s['koneksi']['tidur_bertransaksi']);

            $this->laporkan($s, $adaBlokir);

            if ($menulis && (! $hanyaBlokir || $adaBlokir)) {
                $barisDitulis += $this->rekam($s);
            }

            sleep($interval);
        }

        $this->newLine();
        $this->info('── Ringkasan ──');
        $this->line("  sampel diambil          : {$sampelKe}");
        $this->line("  sampel memuat blocking  : {$sampelBerblokir}");
        $this->line("  puncak sesi pengguna    : {$puncakSesi}");
        $this->line("  puncak tidur+transaksi  : {$puncakTidurBertransaksi}");
        $this->line('  baris direkam           : '.($menulis ? $barisDitulis : '(tanpa-tulis)'));

        if ($sampelBerblokir === 0) {
            $this->newLine();
            $this->line('Tidak ada blocking sepanjang jendela ini. Itu jawaban, bukan kegagalan:');
            $this->line('kalau "Login timeout expired" tetap terjadi tanpa blocking, yang habis');
            $this->line('koneksinya — bukan lock-nya.');
        }

        return self::SUCCESS;
    }

    /** Satu baris ringkas per sampel, supaya layarnya terbaca saat uji berjalan. */
    protected function laporkan(array $s, bool $adaBlokir): void
    {
        $k = $s['koneksi'];
        $garis = sprintf(
            '%s  aktif=%-3d  sesi=%-3d  tidur+trx=%-3d  blokir=%d',
            substr((string) $s['diambil_pada'], 11),
            count($s['permintaan']),
            $k['total_sesi'],
            $k['tidur_bertransaksi'],
            count($s['rantai_blokir']),
        );

        $adaBlokir ? $this->warn($garis) : $this->line($garis);

        foreach ($s['rantai_blokir'] as $r) {
            $this->warn(sprintf(
                '      %s (%s) ditahan akar #%d%s%s',
                $r['jalur_korban'] ?? ('sesi #'.$r['session_korban']),
                $r['wait_type'] ?: 'tanpa wait_type',
                $r['session_akar'],
                $r['jalur_akar'] ? ' = '.$r['jalur_akar'] : '',
                $r['akar_menganggur'] ? '  [AKAR MENGANGGUR: transaksi terbuka tanpa kueri berjalan]' : '',
            ));
        }
    }

    /** Rekam sampel ke tabel yang sudah dipakai db:health-snapshot. */
    protected function rekam(array $s): int
    {
        if (! $s['permintaan']) {
            return 0;
        }

        $baris = array_map(static fn ($p) => [
            'Captured_At' => $s['diambil_pada'],
            'Session_Id' => $p['session_id'] ?? null,
            'Blocking_Session_Id' => $p['blocking_session_id'] ?? null,
            'Status' => $p['status'] ?? null,
            'Wait_Type' => $p['wait_type'] ?? null,
            'Wait_Time_Ms' => $p['wait_ms'] ?? null,
            'Cpu_Ms' => $p['cpu_ms'] ?? null,
            'Login_Name' => $p['login_name'] ?? null,
            'Host_Name' => $p['host_name'] ?? null,
            'Program_Name' => $p['program_name'] ?? null,
            'Context_Info' => $p['context_info'] ?? null,
            'Sql_Text' => $p['sql_text'] ?? null,
        ], $s['permintaan']);

        try {
            foreach (array_chunk($baris, 100) as $potong) {
                DB::table('N_HRIS_DB_Health_Snapshot')->insert($potong);
            }

            return count($baris);
        } catch (\Throwable $e) {
            $this->error('  gagal merekam: '.$e->getMessage());

            return 0;
        }
    }
}
