<?php

namespace App\Console\Commands\LMS\SOP\Operations;

use App\Services\LMS\SOP\Uat\UatSkenario;
use Illuminate\Console\Command;
use SimpleXMLElement;

/**
 * Laporan kesiapan sebelum sesi UAT digelar.
 *
 * Menjawab satu pertanyaan: skenario mana yang sudah terbukti otomatis, dan
 * mana yang masih harus dipercaya begitu saja saat sesi berlangsung. Itulah
 * yang dibaca sebelum mengundang orang.
 *
 * DOKUMEN UAT TIDAK DITIMPA. Kolom Hasil Aktual dan Status di dalamnya tetap
 * hak penguji manusia untuk mengisi saat sesi. Mengisinya otomatis mengubah
 * dokumen kesaksian menjadi keluaran mesin, dan tanda tangan di bawahnya jadi
 * menjamin sesuatu yang tak pernah dilihat penandatangannya.
 */
class SopUatLaporan extends Command
{
    protected $signature = 'sop:uat-laporan
        {--junit= : Berkas JUnit XML hasil `php artisan test --log-junit`}
        {--playwright= : Berkas JSON hasil `npx playwright test`}
        {--keluaran= : Berkas markdown tujuan}';

    protected $description = 'Petakan hasil uji otomatis ke 48 skenario dokumen UAT SOP';

    public function handle(): int
    {
        $junit = $this->option('junit') ?: storage_path('app/uat-auto/junit.xml');

        if (! is_file($junit)) {
            $this->error("Berkas JUnit tidak ditemukan: {$junit}");
            $this->line('Hasilkan dulu dengan:');
            $this->line('  php artisan test tests/Feature/Uat --log-junit '.$junit);

            return self::FAILURE;
        }

        $hasilUji = $this->bacaJunit($junit);
        $peta = $this->petaGrup();

        /* Hasil peramban digabung sebagai kunci semu "playwright::<judul>".
           Lapis peramban tak punya kelas dan metode; penandanya tag @UAT-SOP-###
           pada judul test, jadi pemetaannya lahir dari berkas hasilnya sendiri
           alih-alih dari pemindaian sumber. */
        $pw = $this->option('playwright') ?: storage_path('app/uat-auto/playwright.json');
        if (is_file($pw)) {
            [$hasilPw, $petaPw] = $this->bacaPlaywright($pw);
            $hasilUji += $hasilPw;
            foreach ($petaPw as $id => $kunci) {
                $peta[$id] = array_merge($peta[$id] ?? [], $kunci);
            }
        } else {
            $this->warn("Hasil Playwright tidak ditemukan ({$pw}); lapis peramban dilaporkan BELUM.");
        }

        $baris = [];
        $rekap = ['PASS' => 0, 'FAIL' => 0, 'MANUAL' => 0, 'BELUM' => 0];

        foreach (UatSkenario::semua() as $n) {
            $id = UatSkenario::id($n);
            $kunci = $peta[$id] ?? [];

            $lulus = 0;
            $gagal = 0;
            foreach ($kunci as $k) {
                $st = $hasilUji[$k] ?? null;
                if ($st === 'lulus') {
                    $lulus++;
                } elseif ($st !== null) {
                    $gagal++;
                }
            }

            $status = match (true) {
                $gagal > 0 => 'FAIL',
                $lulus > 0 => 'PASS',
                UatSkenario::adaBagianManual($n) => 'MANUAL',
                default => 'BELUM',
            };

            $rekap[$status]++;

            $baris[] = [
                'id' => $id,
                'modul' => UatSkenario::modul($n),
                'kontrol' => UatSkenario::adalahKontrol($n),
                /* "campuran" bukan sinonim backend: bagian sisanya memang tak
                   bisa diotomatiskan sama sekali. Menuliskannya "backend"
                   membuat pembaca menyangka ujinya tinggal ditulis. */
                'lapis' => match (true) {
                    UatSkenario::butuhPeramban($n) => 'peramban',
                    UatSkenario::adaBagianManual($n) => 'campuran',
                    default => 'backend',
                },
                'status' => $status,
                'uji' => $lulus + $gagal,
            ];
        }

        $keluaran = $this->option('keluaran')
            ?: base_path('plans/sop/uat-kesiapan-'.date('Y-m-d').'.md');

        file_put_contents($keluaran, $this->markdown($baris, $rekap));

        $this->tampilkanRingkas($rekap, $baris);
        $this->info("Laporan: {$keluaran}");

        /* Kode keluar bukan-nol bila ada yang FAIL, supaya perintah ini bisa
           dipasang sebagai gerbang sebelum sesi UAT dijadwalkan. */
        return $rekap['FAIL'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Status tiap uji dari JUnit XML.
     *
     * @return array<string, string> "Kelas::metode" => lulus|gagal|lewat
     */
    private function bacaJunit(string $jalur): array
    {
        $xml = new SimpleXMLElement((string) file_get_contents($jalur));
        $hasil = [];

        foreach ($xml->xpath('//testcase') ?: [] as $tc) {
            $kelas = (string) $tc['class'] ?: (string) $tc['classname'];
            $nama = (string) $tc['name'];

            $status = 'lulus';
            foreach (['failure', 'error'] as $buruk) {
                if ($tc->{$buruk}->count() > 0) {
                    $status = 'gagal';
                }
            }
            if ($tc->skipped->count() > 0) {
                /* Uji yang di-SKIP bukan bukti. Diperlakukan sebagai tidak ada
                   sama sekali, supaya skenario yang ujinya selalu ter-skip
                   karena prasyarat kurang tidak terbaca sebagai PASS. */
                $status = 'lewat';
            }

            $hasil["{$kelas}::{$nama}"] = $status;
        }

        return $hasil;
    }

    /**
     * Hasil lapis peramban dari JSON reporter Playwright.
     *
     * @return array{0: array<string,string>, 1: array<string, list<string>>}
     */
    private function bacaPlaywright(string $jalur): array
    {
        $json = json_decode((string) file_get_contents($jalur), true);
        $hasil = [];
        $peta = [];

        /* Suite Playwright bersarang (berkas → describe → spec), dan
           kedalamannya berubah begitu seseorang menambahkan describe. Ditelusuri
           rekursif, bukan diasumsikan dua tingkat. */
        $telusur = function (array $simpul) use (&$telusur, &$hasil, &$peta) {
            foreach ($simpul['specs'] ?? [] as $spec) {
                $judul = (string) ($spec['title'] ?? '');
                $kunci = 'playwright::'.$judul;

                $lulus = ! empty($spec['ok']);
                $dilewat = collect($spec['tests'] ?? [])
                    ->flatMap(fn ($t) => $t['results'] ?? [])
                    ->contains(fn ($r) => ($r['status'] ?? '') === 'skipped');

                $hasil[$kunci] = $dilewat ? 'lewat' : ($lulus ? 'lulus' : 'gagal');

                if (preg_match('/@(UAT-SOP-\d{3})/', $judul, $m)) {
                    $peta[$m[1]][] = $kunci;
                }
            }

            foreach ($simpul['suites'] ?? [] as $anak) {
                $telusur($anak);
            }
        };

        foreach ($json['suites'] ?? [] as $s) {
            $telusur($s);
        }

        return [$hasil, $peta];
    }

    /**
     * UAT-ID → daftar "Kelas::metode", dipungut dari atribut #[Group] di sumber.
     *
     * DIBACA DARI SUMBER, bukan dari JUnit: keluaran JUnit tidak memuat grup
     * PHPUnit sama sekali. Alternatifnya menjalankan suite 48 kali dengan
     * --group, dan itu mengubah laporan lima menit menjadi laporan satu jam.
     *
     * @return array<string, list<string>>
     */
    private function petaGrup(): array
    {
        $peta = [];

        foreach (glob(base_path('tests/Feature/Uat/*.php')) ?: [] as $berkas) {
            $isi = (string) file_get_contents($berkas);

            if (! preg_match('/namespace\s+([^;]+);/', $isi, $ns)) {
                continue;
            }
            if (! preg_match('/class\s+(\w+)/', $isi, $kls)) {
                continue;
            }
            $kelas = trim($ns[1]).'\\'.$kls[1];

            /* Satu metode boleh membawa lebih dari satu #[Group]; pola ini
               memungut tiap pasangan grup-lalu-metode terdekat. */
            preg_match_all(
                "/#\[Group\('UAT-SOP-(\d{3})'\)\][\s\S]{0,400}?public function (\w+)\s*\(/",
                $isi,
                $cocok,
                PREG_SET_ORDER
            );

            foreach ($cocok as $c) {
                $id = 'UAT-SOP-'.$c[1];
                $peta[$id][] = $kelas.'::'.$c[2];
            }
        }

        return $peta;
    }

    private function tampilkanRingkas(array $rekap, array $baris): void
    {
        $this->line('');
        $this->line(sprintf(
            '  PASS %d   FAIL %d   MANUAL %d   BELUM %d   (dari %d skenario aktif; %d dicabut)',
            $rekap['PASS'], $rekap['FAIL'], $rekap['MANUAL'], $rekap['BELUM'],
            count(UatSkenario::semua()), count(UatSkenario::DICABUT)
        ));

        $kontrolBelum = array_filter($baris, fn ($b) => $b['kontrol'] && $b['status'] !== 'PASS');
        if ($kontrolBelum) {
            $this->line('');
            $this->warn('  Skenario KONTROL yang belum terbukti otomatis:');
            foreach ($kontrolBelum as $b) {
                $this->line("    {$b['id']}  {$b['status']}  {$b['modul']}");
            }
        }
        $this->line('');
    }

    private function markdown(array $baris, array $rekap): string
    {
        $t = date('d F Y');
        $out = "# Kesiapan UAT Modul DMS — {$t}\n\n";

        $out .= "Dihasilkan `php artisan sop:uat-laporan` dari hasil uji otomatis.\n"
            ."**Dokumen UAT tidak ditimpa** — kolom Hasil Aktual dan Status di sana tetap\n"
            ."diisi penguji saat sesi berlangsung. Berkas ini dibaca SEBELUM sesi, untuk\n"
            ."mengetahui skenario mana yang sudah terbukti dan mana yang masih harus\n"
            ."dipercaya begitu saja.\n\n";

        $out .= "| Status | Jumlah | Arti |\n|---|---:|---|\n"
            ."| PASS | {$rekap['PASS']} | terbukti otomatis |\n"
            ."| FAIL | {$rekap['FAIL']} | terbukti gagal — perbaiki sebelum mengundang orang |\n"
            ."| MANUAL | {$rekap['MANUAL']} | ada bagian yang tak bisa diotomatiskan; wajib dijalankan manusia |\n"
            ."| BELUM | {$rekap['BELUM']} | ujinya belum ditulis |\n\n";

        $out .= "Laporan ini **tidak akan pernah 48/48**, dan itu disengaja. Dua skenario\n"
            ."menyentuh sesuatu di luar jangkauan suite: **034** mengerjakan kuis di sistem\n"
            ."ujian HC Learn, dan **036** menulis foto check-in ke penyimpanan awan yang tak\n"
            ."bisa dibereskan pembersih. Langkahnya ada di `plans/sop/uat-panduan-manual.md`.\n\n";

        $out .= "## Seluruh skenario\n\n";
        $out .= "| UAT-ID | Modul | Lapis | Kontrol | Status | Uji |\n|---|---|---|:-:|:-:|--:|\n";

        foreach ($baris as $b) {
            $out .= sprintf(
                "| %s | %s | %s | %s | **%s** | %d |\n",
                $b['id'], $b['modul'], $b['lapis'], $b['kontrol'] ? '✓' : '', $b['status'], $b['uji']
            );
        }

        $kontrolBelum = array_filter($baris, fn ($b) => $b['kontrol'] && $b['status'] !== 'PASS');
        $out .= "\n## Skenario kontrol yang belum terbukti\n\n";
        $out .= $kontrolBelum
            ? "Menurut Acceptance Criteria, skenario kontrol **tidak dapat diturunkan menjadi\n"
              ."Minor**. Yang belum terbukti otomatis harus dijalankan manusia dengan teliti:\n\n"
              .implode("\n", array_map(fn ($b) => "- **{$b['id']}** — {$b['modul']} ({$b['status']})", $kontrolBelum))
              ."\n"
            : "Seluruh skenario kontrol sudah terbukti otomatis.\n";

        return $out;
    }
}
