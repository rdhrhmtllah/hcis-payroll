<?php

namespace App\Console\Commands\LMS\SOP\Operations;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Laporan uji beban & kunci SOP.
 *
 * Menilai keenam kriteria yang DITETAPKAN DI MUKA pada spesifikasi. Kriteria
 * yang disusun setelah melihat hasil hanyalah pembenaran atas apa pun yang
 * kebetulan terjadi.
 */
class SopBebanLaporan extends Command
{
    protected $signature = 'sop:beban-laporan
        {--berkas= : Berkas hasil beban-*.json; bawaannya yang terbaru}
        {--keluaran= : Berkas markdown tujuan}';

    protected $description = 'Rakit hasil uji beban SOP jadi laporan, nilai keenam kriteria';

    /** Satu-satunya ambang yang angkanya PILIHAN, bukan turunan sistem. */
    public const AMBANG_P95_KALI = 2.0;

    /** Di bawah katup SET LOCK_TIMEOUT 10000 milik middleware web. */
    public const AMBANG_BLOCKING_MS = 2000;

    public const AMBANG_TRANSAKSI_MS = 5000;

    /**
     * @return array{lulus:bool, alasan:list<string>}
     */
    public static function nilaiKriteria(array $hasil): array
    {
        $alasan = [];
        $fase = $hasil['fase'] ?? [];
        $dasar = (float) ($fase[0]['p95'] ?? 0.0);

        foreach ($fase as $f) {
            if ((int) ($f['gagal'] ?? 0) > 0) {
                $alasan[] = "fase {$f['nama']}: {$f['gagal']} permintaan gagal";
            }
            if ((int) ($f['timeout'] ?? 0) > 0) {
                $alasan[] = "fase {$f['nama']}: {$f['timeout']} timeout";
            }
            if ($dasar > 0 && (float) $f['p95'] > $dasar * self::AMBANG_P95_KALI) {
                $alasan[] = sprintf('fase %s: p95 %.0f ms > %.1f× dasar (%.0f ms)',
                    $f['nama'], $f['p95'], self::AMBANG_P95_KALI, $dasar);
            }
            if ((int) ($f['blocking']['terlama_ms'] ?? 0) > self::AMBANG_BLOCKING_MS) {
                $alasan[] = sprintf('fase %s: pembaca terblokir %d ms',
                    $f['nama'], $f['blocking']['terlama_ms']);
            }
        }

        /* JALAN KOSONG ITU GAGAL, bukan lulus.
           Operasi massal yang tak menyentuh satu baris pun menghasilkan angka
           yang seluruhnya bagus — nol blocking, nol penantian, transaksi
           sekejap — dan laporan yang menyatakannya LULUS. Jalan pertama
           --paket-sama persis begitu: 0 ditugaskan, 0 dicabut, dan tetap hijau. */
        if (isset($hasil['assign']) && (int) ($hasil['assign']['ditugaskan'] ?? 0) === 0) {
            $alasan[] = 'assign massal tidak menugaskan siapa pun — jalan uji ini kosong, bukan aman';
        }
        if (isset($hasil['cabut']) && (int) ($hasil['cabut']['dicabut'] ?? 0) === 0) {
            $alasan[] = 'cabut massal tidak mencabut siapa pun — jalan uji ini kosong, bukan aman';
        }

        foreach ($hasil['transaksi_massal_ms'] ?? [] as $ms) {
            if ((int) $ms > self::AMBANG_TRANSAKSI_MS) {
                $alasan[] = "transaksi massal {$ms} ms melewati ambang ".self::AMBANG_TRANSAKSI_MS.' ms';
            }
        }

        return ['lulus' => $alasan === [], 'alasan' => $alasan];
    }

    public function handle(): int
    {
        $berkas = $this->option('berkas') ?: collect(glob(storage_path('app/uat-auto/beban-*.json')))
            ->sortDesc()->first();

        if (! $berkas || ! is_file($berkas)) {
            $this->error('Berkas hasil beban tidak ditemukan. Jalankan sop:beban-jalan lebih dulu.');

            return self::FAILURE;
        }

        $hasil = json_decode((string) file_get_contents($berkas), true);
        $nilai = self::nilaiKriteria($hasil);

        $keluaran = $this->option('keluaran')
            ?: base_path('plans/sop/uji-beban-'.date('Y-m-d').'.md');

        file_put_contents($keluaran, $this->markdown($hasil, $nilai, basename($berkas)));

        $this->line('');
        $this->line($nilai['lulus'] ? '  LULUS keenam kriteria.' : '  ADA KRITERIA YANG GAGAL:');
        foreach ($nilai['alasan'] as $a) {
            $this->warn("    - {$a}");
        }
        $this->info("Laporan: {$keluaran}");

        return $nilai['lulus'] ? self::SUCCESS : self::FAILURE;
    }

    private function markdown(array $h, array $nilai, string $sumber): string
    {
        $out = '# Uji beban & kunci modul DMS — '.date('d F Y')."\n\n";

        $out .= "Dihasilkan `php artisan sop:beban-laporan` dari `{$sumber}`.\n\n";

        /* Peringatan ini wajib ada di kepala. Berkas ini ditimpa tiap kali
           perintahnya dijalankan, sehingga ia hanya memuat jalan TERAKHIR —
           dan temuan dari jalan sebelumnya lenyap tanpa jejak. Pembaca yang tak
           tahu itu akan menyimpulkan bahwa jalan terakhir adalah keseluruhan
           ceritanya. */
        $out .= "> **Berkas ini memuat JALAN TERAKHIR saja** dan ditimpa setiap kali perintahnya\n"
            ."> dijalankan. Cerita lintas-jalan — termasuk temuan yang memicu perbaikan —\n"
            ."> disimpan terpisah di `uji-beban-<tanggal>-ringkasan.md`.\n\n";

        $out .= "> **ANGKA INI BATAS BAWAH, dan lapis webnya bukan Cloud Run.** Vhost Laragon\n"
            ."> dibatasi `FcgidMaxProcesses 50`, sedangkan produksi memakai Cloud Run yang\n"
            ."> menggandakan kontainer. Latensi yang terlihat di sini memuat antrean web-tier\n"
            ."> yang tidak akan terjadi di sana. Yang BERLAKU lintas lingkungan hanya temuan\n"
            ."> basis data — kunci, blocking, dan penantian — karena SQL Server-nya sama.\n\n";

        $out .= sprintf(
            "**Bentuk jalan:** %d permintaan serentak · %d detik per fase · %d karyawan disasar · %s\n\n",
            $h['serentak'], $h['detik_per_fase'], $h['karyawan'], $h['basis']
        );

        if (isset($h['assign'])) {
            $out .= sprintf('**Assign massal:** %d ditugaskan, %d dilewati. ',
                $h['assign']['ditugaskan'], $h['assign']['dilewati']);
        }
        if (isset($h['cabut'])) {
            $out .= sprintf("**Cabut massal:** %d dari %d.\n\n",
                $h['cabut']['dicabut'], $h['cabut']['diminta']);
        }

        $out .= "## Per fase\n\n";
        $out .= "| Fase | n | p50 | p95 | maks | gagal | timeout | blocking | terlama |\n";
        $out .= "|---|--:|--:|--:|--:|--:|--:|--:|--:|\n";

        foreach ($h['fase'] as $f) {
            $out .= sprintf("| %s | %d | %.0f ms | %.0f ms | %.0f ms | %d | %d | %d | %d ms |\n",
                $f['nama'], $f['n'], $f['p50'], $f['p95'], $f['maks'],
                $f['gagal'], $f['timeout'],
                $f['blocking']['jumlah'] ?? 0, $f['blocking']['terlama_ms'] ?? 0);
        }

        $out .= "\n## Penantian kunci (delta per fase)\n\n";
        $out .= "`LCK_M_S` adalah penantian kunci SHARED — yaitu PEMBACA yang mengantre.\n"
            ."Itulah bentuk konkret dari \"operasi massal mengganggu orang yang sedang membaca\".\n\n";
        $out .= "| Fase | jenis wait yang bertambah |\n|---|---|\n";

        foreach ($h['fase'] as $f) {
            $naik = array_filter($f['delta_wait'] ?? [], fn ($v) => $v > 0);
            $out .= sprintf("| %s | %s |\n", $f['nama'],
                $naik ? implode(', ', array_map(
                    fn ($k, $v) => sprintf('%s +%.0f ms', $k, $v),
                    array_keys($naik), $naik)) : 'tidak ada');
        }

        $out .= "\n## Lama transaksi massal\n\n";
        foreach ($h['transaksi_massal_ms'] ?? [] as $i => $ms) {
            $out .= sprintf("- %s: **%d ms**\n", $i === 0 ? 'assign' : 'cabut', $ms);
        }

        $korban = DB::table('N_HRIS_Request_Diagnostics')
            ->whereBetween('Created_At', [$h['mulai'], $h['selesai']])
            ->orderByDesc('Total_Ms')
            ->limit(10)
            ->get(['Path', 'Http_Status', 'Total_Ms', 'Conn_Acquire_Ms', 'Db_Query_Count', 'Db_Query_Ms']);

        $out .= "\n## Korban diagnostik dalam jendela uji\n\n";
        $out .= "Tabel ini hanya menulis bila permintaan **≥3 detik atau 5xx**, jadi ia daftar\n"
            ."korban — bukan sampel latensi. Kosong berarti tak ada yang tersiksa di sisi server.\n\n";

        if ($korban->isEmpty()) {
            $out .= "Tidak ada.\n";
        } else {
            $out .= "| Path | HTTP | total | tunggu koneksi | kueri | waktu kueri |\n|---|--:|--:|--:|--:|--:|\n";
            foreach ($korban as $k) {
                $out .= sprintf("| %s | %s | %s ms | %s ms | %s | %s ms |\n",
                    $k->Path, $k->Http_Status, $k->Total_Ms, $k->Conn_Acquire_Ms,
                    $k->Db_Query_Count, $k->Db_Query_Ms);
            }
        }

        $out .= "\n## Keenam kriteria\n\n";
        $out .= $nilai['lulus']
            ? "Seluruhnya **LULUS**.\n"
            : "**ADA YANG GAGAL:**\n\n".implode("\n", array_map(fn ($a) => "- {$a}", $nilai['alasan']))."\n";

        return $out;
    }
}
