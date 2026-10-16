<?php

namespace App\Console\Commands\KPI\Operations;

use App\Services\KPI\AuditTargetSebelumMulaiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Baris target KPI yang berada SEBELUM bulan mulai karyawannya.
 *
 * Sejak bulan mulai diresolusi per anggota saat menyimpan, baris seperti ini
 * tidak terbentuk lagi — perintah ini hanya untuk peninggalan lama. Dampaknya
 * nyata: YTD menjumlahkan target dari baris yang ADA, jadi target bulan yang tak
 * pernah dijalani menekan achievement tanpa sebab yang terlihat.
 *
 * BAWAAN HANYA MELAPORKAN. Penghapusan hanya terjadi bila --terapkan diberikan,
 * karena membersihkannya MENGUBAH SKOR YTD yang sudah tampil di dashboard.
 *
 * Contoh:
 *   php artisan kpi:rapikan-target-sebelum-mulai
 *   php artisan kpi:rapikan-target-sebelum-mulai --kode=Y62
 *   php artisan kpi:rapikan-target-sebelum-mulai --terapkan
 */
class RapikanTargetSebelumMulai extends Command
{
    protected $signature = 'kpi:rapikan-target-sebelum-mulai
        {--kode= : Batasi ke satu Kode_Karyawan}
        {--tahun= : Batasi ke satu tahun jadwal}
        {--dry-run : Hanya melapor (sudah jadi bawaan; ada agar niatnya eksplisit)}
        {--terapkan : Benar-benar hapus}';

    protected $description = 'Laporkan (atau bersihkan) baris KPI_Pengajuan_Det sebelum bulan mulai karyawan';

    public function handle(AuditTargetSebelumMulaiService $audit): int
    {
        // --dry-run selalu menang atas --terapkan: kalau keduanya diberikan,
        // yang aman yang dipakai.
        $terapkan = (bool) $this->option('terapkan') && ! $this->option('dry-run');

        $this->info($terapkan
            ? 'MODE TERAPKAN — baris akan DIHAPUS.'
            : 'MODE LAPORAN (dry run) — tidak ada data yang diubah. Tambahkan --terapkan untuk membersihkan.');
        $this->line('');

        $hasil = $audit->jalankan($this->option('kode'), $this->option('tahun'));

        if (empty($hasil['data'])) {
            $this->info('Bersih: tidak ada baris target sebelum bulan mulai.');

            return self::SUCCESS;
        }

        $this->laporkan($hasil);

        if (! $terapkan) {
            $this->line('');
            $this->warn('Tidak ada yang diubah. Jalankan ulang dengan --terapkan bila laporan di atas sudah benar.');

            return self::SUCCESS;
        }

        return $this->terapkan($hasil);
    }

    private function laporkan(array $hasil): void
    {
        foreach ($hasil['data'] as $orang) {
            $this->line(sprintf(
                '<fg=yellow>%s</> (%s) — %s, mulai %s [%s]',
                $orang['nama_karyawan'],
                $orang['kode_karyawan'],
                $orang['no_transaksi'],
                $orang['bulan_mulai'],
                $orang['sumber_bulan_mulai'],
            ));

            foreach ($orang['baris'] as $b) {
                $this->line(sprintf(
                    '   %-8s %-32s target %s%s',
                    $b['periode'],
                    mb_strimwidth($b['nama_parameter'], 0, 32, '…'),
                    $b['nilai_target'],
                    $b['bisa_dibersihkan'] ? '' : '  <fg=red>[sudah ada realisasi — dilewati]</>',
                ));
            }

            foreach ($orang['dampak_parameter'] as $d) {
                $this->line(sprintf(
                    '   -> "%s" bobot %s: Σtarget %s menjadi %s (rasio %s%% menjadi %s%%)',
                    mb_strimwidth($d['nama_parameter'], 0, 30, '…'),
                    $d['bobot'],
                    $d['total_target_sekarang'],
                    $d['total_target_setelah_bersih'],
                    $d['rasio_sekarang_persen'] ?? '-',
                    $d['rasio_setelah_bersih_persen'] ?? '-',
                ));
            }

            $this->line('');
        }

        $r = $hasil['ringkasan'];
        $this->info("Karyawan terdampak: {$r['total_karyawan']} | baris: {$r['total_baris']} | bisa dibersihkan: {$r['total_baris_bisa_dibersihkan']}");
    }

    private function terapkan(array $hasil): int
    {
        $ids = collect($hasil['data'])
            ->flatMap(fn ($o) => collect($o['baris'])->where('bisa_dibersihkan', true)->pluck('id_pengajuan_det'))
            ->values();

        if ($ids->isEmpty()) {
            $this->warn('Tidak ada baris yang aman dihapus (semuanya sudah punya realisasi).');

            return self::SUCCESS;
        }

        // Cadangan sebelum menghapus: KPI_Pengajuan_Det tidak punya kolom
        // soft-delete, jadi satu-satunya jalan pulang adalah berkas ini.
        $namaBerkas = 'kpi/rapikan-target-'.date('Ymd-His').'.json';
        Storage::disk('local')->put($namaBerkas, json_encode($hasil, JSON_PRETTY_PRINT));
        $this->info('Cadangan ditulis ke storage/app/'.$namaBerkas);

        if (! $this->confirm("Hapus {$ids->count()} baris target? Skor YTD yang sudah tampil akan berubah.", false)) {
            $this->warn('Dibatalkan. Tidak ada yang dihapus.');

            return self::SUCCESS;
        }

        DB::beginTransaction();

        try {
            $dihapus = DB::table('KPI_Pengajuan_Det')->whereIn('Id_Pengajuan_Det', $ids)->delete();
            DB::commit();

            $this->info("Selesai. {$dihapus} baris dihapus.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Gagal, semua perubahan dibatalkan: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
