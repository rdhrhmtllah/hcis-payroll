<?php

namespace App\Console\Commands\SYSTEM\Application\Maintenance;

use App\Services\SYSTEM\Audit\Activity\GerbangDiagnostikRealtime;
use App\Services\SYSTEM\Audit\Activity\PembacaKueriTerberat;
use App\Services\SYSTEM\Audit\Activity\PenilaiTemuanDiagnostik;
use App\Services\SYSTEM\Audit\Activity\PerekamKueriPerRute;
use Illuminate\Console\Command;

/**
 * Menyusun SATU laporan yang bisa dibaca orang maupun agen.
 *
 * Panel di layar bagus untuk melihat keadaan sekarang, tetapi tampilan bukan
 * artefak: ia tidak bisa dilampirkan ke isu, tidak bisa dibandingkan dengan
 * pekan lalu, dan tidak bisa dibaca oleh siapa pun yang tidak sedang menatap
 * layarnya. Perintah ini menuliskan hasilnya ke berkas Markdown, sehingga
 * jejaknya bisa dibawa keluar dari mesin tempat ia diukur.
 *
 * Yang membuatnya berguna bukan angkanya, melainkan TEMUANNYA: aturan di
 * PenilaiTemuanDiagnostik menerjemahkan "kueri sama 4 kali dalam satu
 * permintaan" menjadi "ini N+1, di rute ini, perbaiki begini". Tanpa itu,
 * pembacanya tetap harus sudah mengerti lebih dulu — dan orang yang sudah
 * mengerti tidak butuh laporan.
 *
 * Contoh:
 *   php artisan diagnostik:laporan
 *   php artisan diagnostik:laporan --sesi=20260904-205523 --simpan
 */
class DiagnostikLaporan extends Command
{
    protected $signature = 'diagnostik:laporan
                            {--sesi= : Id sesi rekaman; baku memakai yang terakhir}
                            {--jam=6 : Jendela jam untuk data lock & diagnostik permintaan}
                            {--simpan : Tulis juga ke storage/app/diagnostik/laporan-*.md}';

    protected $description = 'Susun temuan diagnostik jadi satu laporan (HANYA lingkungan pengembangan).';

    public function handle(
        GerbangDiagnostikRealtime $gerbang,
        PenilaiTemuanDiagnostik $penilai,
        PerekamKueriPerRute $perekam,
        PembacaKueriTerberat $kueri,
    ): int {
        $tertutup = $gerbang->alasanTertutup();
        if ($tertutup !== null) {
            $this->error('Diagnostik tertutup: '.$tertutup);

            return self::FAILURE;
        }

        $sesi = $this->option('sesi') ?: null;
        $jam = max(1, (int) $this->option('jam'));

        $rekaman = $perekam->laporan($sesi);
        $temuan = $penilai->nilai($sesi, $jam);

        $md = $this->susun($rekaman, $temuan, $kueri, $jam);

        $this->line($md);

        if ($this->option('simpan')) {
            $berkas = storage_path('app/diagnostik/laporan-'.now()->format('Ymd-His').'.md');
            @mkdir(dirname($berkas), 0775, true);
            file_put_contents($berkas, $md);
            $this->newLine();
            $this->info('Tersimpan: '.$berkas);
        }

        return self::SUCCESS;
    }

    protected function susun(array $rekaman, array $temuan, PembacaKueriTerberat $kueri, int $jam): string
    {
        $b = [];
        $b[] = '# Laporan diagnostik — '.now()->format('Y-m-d H:i:s');
        $b[] = '';

        // ── Temuan lebih dulu. Yang membaca laporan ingin tahu apa yang salah,
        //    bukan berapa angkanya; angkanya menyusul sebagai bukti.
        $b[] = '## Temuan';
        $b[] = '';
        if (! $temuan) {
            $b[] = 'Tidak ada yang melewati ambang. Itu jawaban, bukan kegagalan —';
            $b[] = 'tetapi periksa dulu apakah sesi rekamannya memang berisi.';
        } else {
            $perBerat = ['tinggi' => 0, 'sedang' => 0, 'rendah' => 0];
            foreach ($temuan as $t) {
                $perBerat[$t['berat']] = ($perBerat[$t['berat']] ?? 0) + 1;
            }
            $b[] = sprintf('%d temuan — %d tinggi, %d sedang.',
                count($temuan), $perBerat['tinggi'], $perBerat['sedang']);
            $b[] = '';

            foreach ($temuan as $i => $t) {
                $b[] = sprintf('### %d. [%s] %s — %s', $i + 1, strtoupper($t['berat']), $t['jenis'], $t['tempat']);
                $b[] = '';
                $b[] = $t['ringkas'];
                $b[] = '';
                $b[] = '- **Bukti:** '.$t['bukti'];
                $b[] = '- **Arah perbaikan:** '.$t['saran'];
                $b[] = '';
            }
        }

        // ── Rekaman sesi uji.
        $b[] = '## Rekaman sesi uji';
        $b[] = '';
        if (($rekaman['sesi'] ?? null) === null) {
            $b[] = 'Tidak ada rekaman. Jalankan `Mulai rekam` di /admin/activity lebih dulu.';
        } else {
            $t = $rekaman['total'];
            $b[] = "Sesi `{$rekaman['sesi']}` — {$t['permintaan']} permintaan, {$t['kueri']} kueri, {$t['ms']} ms di basis data.";
            $b[] = '';
            $b[] = '| rute | permintaan | kueri | per permintaan | puncak | ms |';
            $b[] = '|---|---:|---:|---:|---:|---:|';
            foreach ($rekaman['rute'] as $r) {
                $b[] = sprintf('| `%s` | %d | %d | %s | %d | %s |',
                    $r['rute'], $r['permintaan'], $r['kueri'],
                    $r['kueri_per_permintaan'], $r['kueri_terbanyak'], $r['ms']);
            }
        }
        $b[] = '';

        // ── Kueri termahal.
        $b[] = '## Kueri termahal (sisi basis data)';
        $b[] = '';
        $k = $kueri->ambil('cpu', 10);
        $b[] = $k['ada_garis_dasar']
            ? "Selisih sejak garis dasar `{$k['garis_dasar_pada']}`."
            : 'Kumulatif sejak rencananya masuk cache — **bukan** hanya sesi uji ini.';
        $b[] = '';
        $b[] = '| eksekusi | CPU ms | rata ms | baca/eks | kueri |';
        $b[] = '|---:|---:|---:|---:|---|';
        foreach ($k['baris'] as $r) {
            $b[] = sprintf('| %s | %s | %s | %s | `%s` |',
                number_format($r['eksekusi']), number_format($r['cpu_ms']), $r['rata_ms'],
                number_format($r['baca_per_eksekusi']),
                str_replace('|', '\\|', substr($r['sql_text'], 0, 90)));
        }
        $b[] = '';

        $b[] = '---';
        $b[] = '';
        $b[] = 'Ambang yang dipakai tertulis pada tiap temuan. Angka mentahnya ada di';
        $b[] = '`N_HRIS_DB_Health_Snapshot`, `N_HRIS_Request_Diagnostics`, dan';
        $b[] = '`storage/app/diagnostik/rekaman-*.jsonl`.';

        return implode("\n", $b);
    }
}
