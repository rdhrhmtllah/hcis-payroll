<?php

namespace App\Console\Commands\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaPayloadMapperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Menirukan HC Learn: melaporkan hasil penilaian, dan membersihkan jejak simulasi.
 *
 * KENAPA PERLU
 *
 * Tanpa ini seluruh separuh hilir modul — perhitungan, kalibrasi, penerbitan — tidak pernah
 * bisa dijalani sekali pun, karena tidak ada satu jawaban penilai pun yang masuk. Cacat di
 * sana baru akan ditemukan pada siklus sungguhan, ketika 894 orang sudah mengisi.
 *
 * ------------------------------------------------------------------------------------------
 * PERINTAH INI MENOLAK BERJALAN DI PRODUCTION
 * ------------------------------------------------------------------------------------------
 *
 * Ia MENGARANG nilai penilaian. Nilai karangan yang masuk ke siklus sungguhan akan mengalir
 * ke perhitungan, lalu ke hasil yang diterbitkan ke karyawan — dan tidak ada cara membedakannya
 * dari jawaban asli sesudah tercatat.
 *
 * Penolakannya tidak bergantung setelan apa pun. Setelan bisa salah tersalin; `.env` production
 * bisa mewarisi baris dari staging. Yang diperiksa adalah lingkungannya sendiri.
 *
 * ------------------------------------------------------------------------------------------
 * APA YANG DIBUKTIKAN, DAN APA YANG TIDAK
 * ------------------------------------------------------------------------------------------
 *
 * `laporkan` memanggil `PaPayloadMapperService` LANGSUNG, bukan lewat HTTP. Artinya ia
 * membuktikan pemetaan muatan, penilaian per butir, dan idempotensi kiriman ulang — tetapi
 * TIDAK membuktikan gerbang `LmsApiGuard` (tanda tangan, nonce, pemilihan modul). Itu
 * dikatakan supaya hijaunya tidak dibaca lebih luas daripada yang sebenarnya diuji; jalur
 * gerbangnya diperiksa terpisah lewat pemeriksaan statis di berkas uji keamanan.
 */
class PaSimulasiHcLearnCommand extends Command
{
    /*
     | Awalan token penjadwalan simulasi yang PERNAH ada.
     |
     | Simulasi penjadwalan dicabut 10 September 2026 ketika HC Learn menyerahkan kontraknya,
     | dan setelannya ikut dibuang. Awalannya tetap disebut di sini karena perintah
     | `bersihkan` masih harus bisa mengenali baris peninggalannya - lihat DML-2026-120 yang
     | membersihkannya sekali jalan. Nilainya dibekukan, bukan dibaca dari setelan yang sudah
     | tidak ada.
     */
    public const AWALAN_TOKEN_LAMA = 'SIM-PA-';

    protected $signature = 'pa:simulasi-hclearn
        {aksi : laporkan | bersihkan}
        {cycle : Id siklus PA}
        {--porsi=100 : Persen penugasan yang dilaporkan selesai (1-100)}
        {--seed=2026 : Benih acak, supaya nilainya bisa diulang persis}';

    protected $description = '[DEV] Menirukan HC Learn: melaporkan hasil penilaian, atau membersihkan jejak simulasi';

    public function handle(PaPayloadMapperService $pemeta): int
    {
        if (app()->isProduction()) {
            $this->error('DITOLAK: perintah ini mengarang nilai penilaian dan tidak boleh berjalan di production.');

            return self::FAILURE;
        }

        $cycleId = (int) $this->argument('cycle');

        return match ($this->argument('aksi')) {
            'laporkan' => $this->laporkan($pemeta, $cycleId),
            'bersihkan' => $this->bersihkan($cycleId),
            default => $this->gagalAksi(),
        };
    }

    private function gagalAksi(): int
    {
        $this->error('Aksi tidak dikenali. Pilih: laporkan | bersihkan');

        return self::FAILURE;
    }

    /**
     * Menirukan HC Learn mengirim hasil penilaian untuk penugasan yang sudah berpintu.
     */
    private function laporkan(PaPayloadMapperService $pemeta, int $cycleId): int
    {
        $porsi = max(1, min(100, (int) $this->option('porsi')));
        mt_srand((int) $this->option('seed'));

        $awalan = (string) PaSimulasiHcLearnCommand::AWALAN_TOKEN_LAMA;

        /* HANYA token simulasi. Melaporkan hasil karangan atas token SUNGGUHAN berarti
           mencampur jawaban palsu ke dalam penilaian yang benar-benar dikerjakan orang. */
        $penugasan = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->join('N_PA_Assignment_Integration as i', function ($j) {
                $j->on('i.PA_Assignment_Id', '=', 'a.Id_PA_Assignment')->where('i.Flag_Aktif', '=', 'Y');
            })
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('a.Flag_Aktif', 'Y')
            ->where('a.Status_Penugasan', '<>', 'SELESAI')
            ->where('i.Id_Ujian_Token', 'like', $awalan.'%')
            ->orderBy('a.Id_PA_Assignment')
            ->get([
                'a.Id_PA_Assignment as id',
                'a.Kode_Karyawan_Penilai as penilai',
                'i.Id_Ujian_Token as token',
                'i.PA_Cycle_Question_Package_Id as paket_id',
                'i.Id_PA_Assignment_Integration as integrasi_id',
            ]);

        if ($penugasan->isEmpty()) {
            $this->warn("Tidak ada penugasan bertoken simulasi yang belum selesai di siklus {$cycleId}.");
            $this->line('Jalankan `pa:jadwalkan-penilaian` lebih dulu.');

            return self::SUCCESS;
        }

        $ambil = (int) ceil($penugasan->count() * $porsi / 100);
        $dipilih = $penugasan->take($ambil);

        $this->line(sprintf(
            'Siklus %d: %d penugasan bertoken simulasi, %d akan dilaporkan (%d%%).',
            $cycleId,
            $penugasan->count(),
            $dipilih->count(),
            $porsi,
        ));

        /* Butir paket dimuat SEKALI per paket, bukan per penugasan: ratusan penugasan berbagi
           paket yang sama, dan menanyakannya per baris berarti ratusan kueri untuk jawaban
           yang sudah ada di tangan. */
        $butir = [];
        $berhasil = 0;
        $gagal = 0;
        $butirTanpaKode = 0;

        $bar = $this->output->createProgressBar($dipilih->count());
        $bar->start();

        foreach ($dipilih as $t) {
            $paketId = (int) ($t->paket_id ?? 0);

            if ($paketId === 0) {
                $gagal++;
                $bar->advance();

                continue;
            }

            if (! isset($butir[$paketId])) {
                $butir[$paketId] = DB::table('N_PA_Cycle_Question_Package_Item')
                    ->where('PA_Cycle_Question_Package_Id', $paketId)
                    ->where('Flag_Aktif', 'Y')
                    ->orderBy('Urutan_Tampil')
                    ->get(['Id_Jenis_Soal', 'Kode_Soal_Sumber', 'Skor_Maksimal', 'Jenis_Soal', 'Bobot_Penilaian'])
                    ->all();
            }

            if ($butir[$paketId] === []) {
                $gagal++;
                $bar->advance();

                continue;
            }

            $tanpaKode = 0;
            $detail = $this->detailUntuk($butir[$paketId], $tanpaKode);

            if ($detail === []) {
                /* Seluruh butir paketnya tanpa `Kode_Soal_Sumber`. Mengirimnya berarti kiriman
                   tanpa `Detail`, yang ditolak pemeta dengan pesan yang menuduh kirimannya —
                   padahal yang kurang adalah paketnya. Dikatakan di sini, sekali. */
                $gagal++;

                if ($gagal <= 3) {
                    $this->newLine();
                    $this->error("  Penugasan {$t->id}: paket {$paketId} tidak punya satu pun butir berkode soal sumber.");
                }

                $bar->advance();

                continue;
            }

            if ($tanpaKode > 0) {
                $butirTanpaKode += $tanpaKode;
            }

            $muatan = [
                'Kode_Karyawan' => $t->penilai,
                'Id_Penjadwalan_Integrasi' => (int) $t->integrasi_id,
                'Id_Ujian_Token' => $t->token,
                'Id_Ujian_Nilai_Akhir' => 'SIM-'.$t->integrasi_id,
                'Detail' => $detail,
            ];

            try {
                $pemeta->terimaDanPetakan((string) $t->token, $muatan, null);
                $berhasil++;
            } catch (\Throwable $e) {
                $gagal++;

                if ($gagal <= 3) {
                    $this->newLine();
                    $this->error("  Penugasan {$t->id}: ".$e->getMessage());
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->line("  dilaporkan {$berhasil} | gagal {$gagal}");

        if ($butirTanpaKode > 0) {
            /* Kirimannya MASUK, tetapi tidak lengkap. Dikatakan terpisah dari "gagal" karena
               tindakannya berbeda: yang ini dibereskan di paket soalnya (DML-2026-120), bukan
               dengan mengulang perintah ini. */
            $this->warn(sprintf(
                '  %d butir dibuang karena tanpa Kode_Soal_Sumber; kirimannya tercatat tidak lengkap.',
                $butirTanpaKode,
            ));
        }

        return $gagal > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Menyusun `Detail[]` satu kiriman, MENGIKUTI KONTRAK yang dituntut mapper.
     *
     * DUA HAL YANG SEMPAT SALAH, DAN KEDUANYA PENTING
     *
     * 1. `Skor_Maksimal` WAJIB ikut di tiap butir. Skalanya datang dari HC Learn bersama
     *    nilainya, bukan dibaca HCIS dari paketnya — karena paket bisa berubah sesudah orang
     *    mengisi, dan yang benar untuk menormalkan adalah skala yang berlaku SAAT diisi.
     *    Mapper menolak skor tanpa pasangannya, dan penolakannya benar.
     *
     * 2. Soal `teks` TIDAK BERSKOR. Kompetensi kualitatif dijawab naratif dan `Skor_Maksimal`-nya
     *    memang NULL. Simulasi yang mengarang angka untuknya bukan sekadar salah bentuk — ia
     *    membuat butir naratif ikut terhitung ke nilai akhir, dan itu mengubah hasil orang.
     *
     * 3. `Kode_Soal` adalah `Kode_Soal_Sumber` — kode yang DIBERIKAN HC LEARN saat paketnya
     *    diikat (`K01`..`K14`) — BUKAN `Id_Jenis_Soal` (100158..). Sampai 11 September 2026
     *    berkas ini mengirim `Id_Jenis_Soal`, dan karena pemeta mengunci kolom yang sama,
     *    simulasinya hijau sempurna: 13 butir terpetakan, kontrak lengkap, penugasan selesai.
     *    Yang tidak pernah diuji justru satu-satunya bentuk yang dipakai di lapangan — dan
     *    ketika penilaian sungguhan pertama masuk, nol butirnya terpetakan tanpa satu pun
     *    pesan galat. Fixture yang ikut salah bersama kode yang diujinya tidak menangkap apa
     *    pun; inilah sebabnya kodenya dibaca dari kolomnya sendiri sekarang.
     *
     * Butir tanpa `Kode_Soal_Sumber` DIBUANG dari kiriman, bukan dikirim berkode kosong:
     * pemeta menolak butir tanpa `Kode_Soal` dan penolakannya menggagalkan SELURUH kiriman,
     * sehingga satu butir tak berkode akan menghapus dua belas butir yang sah. Jumlah yang
     * dibuang dilaporkan lewat `$tanpaKode` supaya hilangnya terlihat, bukan terdiam —
     * kirimannya akan tercatat tidak lengkap, dan itu memang keadaan yang sebenarnya.
     *
     * @param  list<object>  $butir
     * @param  int  $tanpaKode  bertambah untuk tiap butir yang dibuang karena tak berkode
     * @return list<array<string, mixed>>
     */
    private function detailUntuk(array $butir, int &$tanpaKode = 0): array
    {
        $detail = [];

        foreach ($butir as $b) {
            $kodeSoal = trim((string) ($b->Kode_Soal_Sumber ?? ''));

            if ($kodeSoal === '') {
                $tanpaKode++;

                continue;
            }

            $jenis = strtolower((string) ($b->Jenis_Soal ?? 'skala'));
            $maks = $b->Skor_Maksimal !== null ? (float) $b->Skor_Maksimal : null;

            $satu = [
                'Kode_Soal' => $kodeSoal,
                'Jenis_Soal' => $b->Jenis_Soal,
                'Bobot_Penilaian' => $b->Bobot_Penilaian,
            ];

            if ($jenis === 'teks' || $maks === null || $maks <= 0) {
                $satu['Jawaban_Teks'] = 'Jawaban naratif simulasi untuk '.$kodeSoal.'.';
            } else {
                /* Nilainya condong ke atas, bukan rata acak. Sebaran seragam menghasilkan
                   rata-rata yang selalu di tengah, sehingga peta HAV dan kalibrasi tidak
                   pernah menghadapi sebaran yang mirip kenyataan. */
                $satu['Skor'] = min($maks, max(1, round($maks * (mt_rand(55, 100) / 100), 2)));
                $satu['Skor_Maksimal'] = $maks;
            }

            $detail[] = $satu;
        }

        return $detail;
    }

    /**
     * Membuang seluruh jejak simulasi satu siklus.
     *
     * WAJIB dijalankan sebelum data sungguhan masuk. Pintu palsu yang tertinggal akan
     * dihitung sebagai "sudah terjadwal" oleh `PaHcLearnScheduleService::ringkasan()`,
     * sehingga penjadwalan sungguhan melewatinya — dan penilainya tidak pernah dapat pintu.
     *
     * Hanya menyentuh baris berawalan token simulasi. Baris sungguhan tidak ikut terhapus
     * bahkan bila perintahnya salah sasaran siklus.
     */
    private function bersihkan(int $cycleId): int
    {
        $awalan = (string) PaSimulasiHcLearnCommand::AWALAN_TOKEN_LAMA;

        $id = DB::table('N_PA_Assignment_Integration as i')
            ->join('N_PA_Assignment as a', 'a.Id_PA_Assignment', '=', 'i.PA_Assignment_Id')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('i.Id_Ujian_Token', 'like', $awalan.'%')
            ->pluck('i.Id_PA_Assignment_Integration')
            ->all();

        if ($id === []) {
            $this->info("Tidak ada jejak simulasi di siklus {$cycleId}.");

            return self::SUCCESS;
        }

        $this->warn(sprintf('Akan menghapus %d baris integrasi bertoken %s pada siklus %d.', count($id), $awalan, $cycleId));

        if (! $this->confirm('Lanjutkan?', false)) {
            $this->line('Dibatalkan.');

            return self::SUCCESS;
        }

        $dihapus = 0;

        foreach (array_chunk($id, 500) as $potongan) {
            $dihapus += DB::table('N_PA_Assignment_Integration')
                ->whereIn('Id_PA_Assignment_Integration', $potongan)
                ->delete();
        }

        $this->info("{$dihapus} baris integrasi simulasi dihapus.");
        $this->line('Catatan: hasil penilaian yang sudah dipetakan TIDAK ikut terhapus — ia data terpisah.');

        return self::SUCCESS;
    }
}
