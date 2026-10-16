<?php

namespace App\Console\Commands\LMS\SOP\Operations;

use App\Services\LMS\SOP\Uat\UatGerbang;
use App\Services\LMS\SOP\Uat\UatJurnal;
use App\Services\LMS\SOP\Uat\UatPembersih;
use Illuminate\Console\Command;
use Throwable;

/**
 * Menghapus baris yang dibuat satu jalan otomasi UAT.
 *
 * HARUS BISA DIJALANKAN MANUAL, bukan sekadar dipanggil di akhir suite. Jalan
 * yang mati di tengah — CTRL-C, listrik padam, peramban hang — meninggalkan
 * jurnal tanpa pembersihnya, dan itu justru keadaan yang paling membutuhkan
 * perintah ini. Pembersih yang hanya hidup sebagai bagian dari suite tidak
 * pernah ada saat dibutuhkan.
 *
 * Yang dihapus HANYA baris yang tercatat di jurnal, pada tabel yang ada di
 * daftar-putih UatTabelIzin. Perintah ini tidak pernah menyapu berdasarkan
 * nama, tidak pernah TRUNCATE, dan tidak pernah menyentuh skema.
 */
class SopUatBersihkan extends Command
{
    protected $signature = 'sop:uat-bersihkan
        {jalan? : ID jalan-uji, mis. uat-20260813-141530-a1b2c3}
        {--semua : Bersihkan seluruh jurnal yang tertinggal}
        {--dry-run : Tampilkan rencana tanpa menghapus apa pun}';

    protected $description = 'Hapus baris yang dibuat jalan otomasi UAT (bersasar, lewat jurnal)';

    public function handle(UatPembersih $pembersih): int
    {
        try {
            UatGerbang::pastikanTerbuka();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $kering = (bool) $this->option('dry-run');
        $jalan = $this->argument('jalan');
        $semua = (bool) $this->option('semua');

        if ($jalan === null && ! $semua) {
            $tertinggal = UatJurnal::semua();
            $this->error('Sebutkan ID jalan-uji, atau --semua untuk membersihkan yang tertinggal.');
            if ($tertinggal) {
                $this->line('Jurnal yang tertinggal:');
                foreach ($tertinggal as $id) {
                    $this->line("  {$id}");
                }
            } else {
                $this->line('Tidak ada jurnal yang tertinggal.');
            }

            return self::FAILURE;
        }

        $daftar = $semua ? UatJurnal::semua() : [$jalan];
        if ($daftar === []) {
            $this->info('Tidak ada jurnal yang perlu dibersihkan.');

            return self::SUCCESS;
        }

        $gagal = 0;
        foreach ($daftar as $id) {
            $gagal += $this->bersihkanSatu($pembersih, (string) $id, $kering) ? 0 : 1;
        }

        return $gagal === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function bersihkanSatu(UatPembersih $pembersih, string $id, bool $kering): bool
    {
        $this->line('');
        $this->line("<comment>{$id}</comment>".($kering ? '  (dry-run)' : ''));

        try {
            $jurnal = UatJurnal::muat($id);
            $rencana = $pembersih->rencana($jurnal);
        } catch (Throwable $e) {
            $this->error('  '.$e->getMessage());

            return false;
        }

        if ($rencana === []) {
            $this->line('  Jurnal kosong — tidak ada yang dihapus.');
            if (! $kering) {
                $jurnal->hapusBerkas();
            }

            return true;
        }

        /* Rencana ditampilkan SELALU, bukan hanya saat dry-run. Perintah yang
           menghapus data harus menyebutkan apa yang dihapusnya di tempat yang
           sama dengan hasilnya, supaya keluaran yang tersimpan di log cukup
           untuk menjawab "apa yang hilang" tanpa menebak. */
        foreach ($rencana as $langkah) {
            $this->line(sprintf('  %-32s %s IN (%d id)',
                $langkah['tabel'], $langkah['kunci'], count($langkah['id'])));
        }

        try {
            $hasil = $pembersih->jalankan($rencana, $kering);
        } catch (Throwable $e) {
            $this->error('  Gagal: '.$e->getMessage());
            $this->warn('  Jurnal SENGAJA tidak dihapus supaya bisa dicoba lagi.');

            return false;
        }

        $total = array_sum($hasil);
        $this->info(sprintf('  %s %d baris pada %d tabel.',
            $kering ? 'Akan menghapus' : 'Terhapus', $total, count($hasil)));

        if (! $kering) {
            $jurnal->hapusBerkas();
        }

        return true;
    }
}
