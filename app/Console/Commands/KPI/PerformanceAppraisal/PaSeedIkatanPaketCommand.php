<?php

namespace App\Console\Commands\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaHcLearnCatalogService;
use App\Services\KPI\PerformanceAppraisal\PaQuestionBindingMasterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Mengisi matriks paket soal baku dengan data contoh, supaya alurnya bisa diuji utuh
 * sebelum HC Learn menjawab kontraknya.
 *
 * KENAPA PERINTAH, BUKAN DML
 *
 * Ini data UJI, bukan data yang akan hidup di produksi. DML akan ikut antre di SQL Tracker
 * dan kelak dijalankan di produksi bersama perubahan lain — membawa pilihan paket palsu ke
 * sistem yang dipakai orang sungguhan. Perintah tinggal dijalankan di dev, bisa diulang,
 * dan bisa dibersihkan.
 *
 * PENOLAKAN DI PRODUCTION ADA DI KODE
 *
 * Bukan di setelan, bukan di kebijakan. Setelan bisa keliru tersalin antar lingkungan;
 * pemeriksaan lingkungan tidak bisa.
 *
 * YANG DIPILIHNYA BUKAN TEBAKAN
 *
 * Untuk tiap (level x kategori penilai), dipilih paket yang dimensinya SAMA PERSIS dengan
 * dimensi wajib level itu — aturan yang sama dengan yang ditegakkan saat impor. Kalau tidak
 * ada yang cocok, selnya DILEWATI dan dilaporkan, bukan diisi paket asal supaya angkanya
 * terlihat penuh.
 */
class PaSeedIkatanPaketCommand extends Command
{
    protected $signature = 'pa:seed-ikatan-paket
        {--bersihkan : Menghapus seluruh ikatan baku bertanda SIM- (data contoh), tanpa mengisi}
        {--per-penilai : Mengisi tiap kategori penilai dengan variannya sendiri, bukan satu paket baku per level}';

    protected $description = 'Mengisi matriks paket soal baku dengan data contoh (dev saja).';

    public function handle(
        PaHcLearnCatalogService $katalog,
        PaQuestionBindingMasterService $master,
    ): int {
        if (app()->isProduction()) {
            $this->error('Perintah ini menulis data contoh dan TIDAK boleh berjalan di production.');

            return self::FAILURE;
        }

        if ($this->option('bersihkan')) {
            return $this->bersihkan();
        }

        $daftar = $katalog->daftarPaket();

        if (! ($daftar['simulasi'] ?? false)) {
            /* Katalog sungguhan berarti paket-paketnya nyata, dan mengikat sesuatu ke sana
               atas nama "data contoh" akan menyesatkan siapa pun yang membacanya nanti. */
            $this->error('Katalog sedang dilayani HC Learn sungguhan, bukan simulasi.');
            $this->line('Perintah ini hanya untuk mengisi contoh dari katalog simulasi.');

            return self::FAILURE;
        }

        $paket = collect($daftar['paket']);
        $matriks = $master->matriks();
        $perPenilai = (bool) $this->option('per-penilai');

        $terisi = 0;
        $dilewati = [];

        foreach ($matriks['level'] as $l) {
            $wajib = collect($l['dimensi_wajib'])->pluck('kode')->sort()->values()->all();

            if ($wajib === []) {
                continue;
            }

            /* Kandidat: paket yang dimensinya SAMA PERSIS. Aturan yang sama dengan penjaga
               impor — kalau di sini lebih longgar, data contohnya akan lolos dibuat lalu
               ditolak saat dipakai. */
            $cocok = $paket->filter(function ($p) use ($wajib) {
                $d = collect($p['Dimensi'] ?? [])->sort()->values()->all();

                return $d === $wajib;
            });

            if ($cocok->isEmpty()) {
                $dilewati[] = sprintf('%s — tidak ada paket berdimensi %s', $l['nama_level'], implode('+', $wajib));

                continue;
            }

            /* Hanya kategori yang BENAR-BENAR menilai level ini. Mengisi kategori lain
               menghasilkan paket yang tidak akan pernah dipakai siapa pun — dan matriksnya
               tampak lengkap sambil menyembunyikan sel yang benar-benar kosong. Percobaan
               pertama perintah ini mengisi 52 sel padahal yang sah hanya 45. */
            $sasaran = $perPenilai
                ? collect($matriks['kategori'])
                    ->filter(fn ($k) => $l['sel'][$k['kode']]['ikut_menilai'] ?? false)
                    ->map(fn ($k) => [$k['id'], $k['kode'], $k['nama']])
                    ->values()
                    ->all()
                : [[null, null, 'baku']];

            foreach ($sasaran as [$kategoriId, $kodeKategori, $namaKategori]) {
                $pilihan = $kodeKategori === null
                    ? $cocok->first(fn ($p) => ! isset($p['Untuk_Kategori']))
                    : $cocok->first(fn ($p) => ($p['Untuk_Kategori'] ?? null) === $kodeKategori);

                // Tidak ada varian untuk kategori itu; jatuh ke paket dasarnya.
                $pilihan ??= $cocok->first(fn ($p) => ! isset($p['Untuk_Kategori'])) ?? $cocok->first();

                if ($pilihan === null) {
                    $dilewati[] = sprintf('%s x %s — tidak ada kandidat', $l['nama_level'], $namaKategori);

                    continue;
                }

                try {
                    $master->simpan($l['id_level'], $kategoriId, $pilihan['Id_Master_Ujian']);
                    $terisi++;
                } catch (Throwable $e) {
                    $dilewati[] = sprintf('%s x %s — %s', $l['nama_level'], $namaKategori, $e->getMessage());
                }
            }
        }

        $this->info(sprintf('%d ikatan baku terisi.', $terisi));

        if ($dilewati !== []) {
            /* Yang dilewati DILAPORKAN, bukan didiamkan. Pengisian yang diam-diam melewatkan
               separuh selnya membuat orang mengira matriksnya sudah lengkap. */
            $this->newLine();
            $this->warn(sprintf('%d dilewati:', count($dilewati)));

            foreach ($dilewati as $x) {
                $this->line('  - '.$x);
            }
        }

        $sesudah = $master->matriks();
        $this->newLine();
        $this->line(sprintf(
            'Matriks sekarang: %d sel kosong, %d bermasalah, %d salah tempat.',
            $sesudah['total_kosong'],
            $sesudah['total_bermasalah'],
            $sesudah['total_salah_tempat'] ?? 0,
        ));

        if (($sesudah['total_salah_tempat'] ?? 0) > 0) {
            /* Sel terisi untuk kategori yang tidak menilai level itu. Bisa peninggalan
               pengisian lama, atau aturan penilai yang berubah sesudah ikatannya dibuat. */
            $this->warn('Ada sel terisi untuk kategori yang tidak menilai levelnya.');
            $this->line('Bersihkan dengan --bersihkan lalu jalankan ulang.');
        }
        $this->line('Bersihkan dengan: php artisan pa:seed-ikatan-paket --bersihkan');

        return self::SUCCESS;
    }

    /**
     * MENGHAPUS ikatan yang menunjuk paket SIMULASI.
     *
     * Modul ini biasanya menonaktifkan, tidak menghapus — riwayat keputusan harus tetap bisa
     * dibaca. Ikatan simulasi BUKAN keputusan: ia data uji, dan menyimpannya sebagai baris
     * mati hanya menumpuk puing yang membuat "sel ini pernah pakai apa saja" bercampur
     * dengan percobaan pengisian yang keliru.
     *
     * Yang menjaga batasnya awalan `SIM-`: ikatan ke paket HC Learn sungguhan tidak pernah
     * tersentuh, dan itulah gunanya awalan itu ikut tersimpan di database, bukan hanya ada
     * di balasan API.
     */
    private function bersihkan(): int
    {
        $jumlah = DB::table('N_PA_Question_Binding_Master')
            ->where('Id_Master_Ujian', 'like', 'SIM-%')
            ->delete();

        $this->info(sprintf('%d ikatan bertanda SIM- dihapus.', $jumlah));

        $sisa = DB::table('N_PA_Question_Binding_Master')->count();

        if ($sisa > 0) {
            $this->line(sprintf('%d ikatan lain tetap ada (bukan data contoh).', $sisa));
        }

        return self::SUCCESS;
    }
}
