<?php

namespace App\Console\Commands\SYSTEM\Application\Maintenance;

use App\Services\SYSTEM\Application\Database\PembandingSkema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Menjawab satu pertanyaan yang selama ini hanya bisa diduga: apakah basis data
 * staging masih sama dengan development?
 *
 * HANYA MEMBACA. Seluruh kueri menyasar katalog sistem (`sys.columns`,
 * `sys.tables`), tidak menyentuh data dan tidak mengubah apa pun. Aman dijalankan
 * kapan saja, termasuk terhadap basis data yang sedang dipakai orang.
 *
 * Perintah ini BUKAN cara menjaga kedua basis data tetap sama — yang menjaganya
 * adalah restore ulang satu arah dari development, sehingga tidak ada satu pun DDL
 * yang dijalankan dua kali. Perintah ini menutup celah yang tersisa: jeda antara
 * dua refresh, di situlah drift hidup dan tidak mengumumkan diri.
 */
class BandingSkema extends Command
{
    protected $signature = 'db:banding-skema
        {--dari=Web_HRIS : Basis data acuan, biasanya development}
        {--ke=Web_HRIS_Cut_Off : Basis data yang diperiksa, biasanya staging}
        {--ringkas : Hanya cetak jumlah perbedaan, tanpa rinciannya}';

    protected $description = 'Bandingkan inventaris tabel dan kolom dua basis data (hanya membaca).';

    public function handle(PembandingSkema $pembanding): int
    {
        $dari = (string) $this->option('dari');
        $ke = (string) $this->option('ke');

        try {
            PembandingSkema::pastikanNamaDatabaseAman($dari);
            PembandingSkema::pastikanNamaDatabaseAman($ke);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::INVALID;
        }

        if ($dari === $ke) {
            $this->error('Basis data acuan dan yang diperiksa sama; tidak ada yang bisa dibandingkan.');

            return self::INVALID;
        }

        $this->line("Membandingkan <options=bold>{$dari}</> (acuan) dengan <options=bold>{$ke}</>...");

        try {
            $inventarisDari = $this->ambilInventaris($dari);
            $inventarisKe = $this->ambilInventaris($ke);
        } catch (Throwable $e) {
            // Basis data yang tidak ada gagal di sini, dan gagalnya harus terbaca
            // sebagai "tidak bisa memeriksa", bukan sebagai "tidak ada perbedaan".
            $this->error('Gagal membaca katalog: '.$e->getMessage());

            return self::FAILURE;
        }

        $hasil = $pembanding->banding($inventarisDari, $inventarisKe);

        if ($hasil['sama']) {
            $this->info("Sama. {$dari} dan {$ke} punya tabel dan kolom yang identik.");

            return self::SUCCESS;
        }

        $jumlah = count($hasil['tabel_hilang']) + count($hasil['tabel_asing'])
            + count($hasil['kolom_hilang']) + count($hasil['kolom_asing']) + count($hasil['kolom_beda']);

        $this->newLine();
        $this->error("BERBEDA — {$jumlah} temuan.");

        if (! $this->option('ringkas')) {
            $this->cetak("Tabel ada di {$dari} tapi TIDAK di {$ke}", $hasil['tabel_hilang'],
                "Ini yang paling sering terjadi: {$ke} tertinggal. Refresh dari {$dari}.");

            $this->cetak("Tabel ada di {$ke} tapi TIDAK di {$dari}", $hasil['tabel_asing'],
                "Ada yang menjalankan DDL langsung di {$ke}. Perubahannya akan hilang pada refresh berikutnya.");

            $this->cetak('Kolom hilang', $hasil['kolom_hilang'], null);
            $this->cetak('Kolom asing', $hasil['kolom_asing'], null);

            if ($hasil['kolom_beda'] !== []) {
                $this->newLine();
                $this->line('<options=bold>Kolom yang sifatnya berbeda</> ('.count($hasil['kolom_beda']).')');
                $this->line('  Ini yang paling sulit terlihat sendiri: kolom yang berubah tipe atau');
                $this->line('  panjangnya tidak menggagalkan apa pun sampai ada nilai yang terpotong.');
                $this->newLine();
                $this->table(
                    ['Kolom', $dari, $ke],
                    array_map(fn ($b) => [$b['kolom'], $b['dari'], $b['ke']], $hasil['kolom_beda'])
                );
            }
        }

        $this->newLine();
        $this->line("Cara membereskannya: restore ulang {$ke} dari {$dari}, lalu semai ulang data ujinya.");
        $this->line('Langkahnya ada di docs/architecture/staging-cloud-run.md.');

        return self::FAILURE;
    }

    /**
     * @param  list<string>  $daftar
     */
    private function cetak(string $judul, array $daftar, ?string $keterangan): void
    {
        if ($daftar === []) {
            return;
        }

        $this->newLine();
        $this->line("<options=bold>{$judul}</> (".count($daftar).')');

        if ($keterangan !== null) {
            $this->line("  {$keterangan}");
        }

        foreach (array_slice($daftar, 0, 40) as $item) {
            $this->line("    - {$item}");
        }

        if (count($daftar) > 40) {
            $this->line('    ... dan '.(count($daftar) - 40).' lagi.');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ambilInventaris(string $database): array
    {
        return array_map(
            fn ($baris) => (array) $baris,
            DB::select(PembandingSkema::kueriInventaris($database))
        );
    }
}
