<?php

namespace App\Console\Commands\KPI\PerformanceAppraisal;

use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\PerformanceAppraisal\PaHcLearnScheduleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Menarik hasil penjadwalan yang masih ANTRE di HC Learn.
 *
 * ── Kenapa ini ada sebagai perintah tersendiri ────────────────────────────────
 *
 * `POST /api/v1/pa/penjadwalan/store` menjawab **202** dan menitipkan pekerjaannya ke antrean
 * mereka; tokennya belum ada saat jawaban itu diterima. Menunggunya di dalam permintaan HTTP
 * berarti satu permintaan menggantung menit-menit atas ribuan penilai, lalu mati kehabisan
 * waktu dan meninggalkan sebagian tanpa pintu tanpa ada yang tahu berapa banyak.
 *
 * Jadi penjadwalan berhenti pada "sudah dititipkan", dan perintah inilah yang mengisi
 * pintunya. Aman dijalankan berulang: memanggil endpoint status tidak mengubah apa pun di
 * sisi mereka, dan baris yang sudah terisi tidak ikut ditanyakan lagi.
 *
 * ── Identitas penanda tangan ──────────────────────────────────────────────────
 *
 * Tanda tangan permintaannya memuat `X-App-ID` dan `X-Kode-Karyawan`. Di jalur CLI tidak ada
 * yang sedang masuk, jadi satu pengguna ber-`User_Key` dipilih bila ada. Tidak ada pun tidak
 * apa-apa sejak 10 September 2026: `PaHcLearnJadwalContract::identitas()` jatuh ke AKUN
 * LAYANAN. Yang tidak boleh terjadi hanyalah mengirim permintaan tanpa identitas lalu dijawab
 * 401 — dan itu ditolak di kontraknya, bukan di sini.
 */
class PaTarikJadwalCommand extends Command
{
    protected $signature = 'pa:tarik-jadwal
        {cycle? : Id siklus PA. Kosongkan untuk seluruh siklus yang masih punya baris ANTRE}';

    protected $description = 'Menarik hasil penjadwalan HC Learn yang masih ANTRE, lalu mengisi pintunya';

    public function handle(PaHcLearnScheduleService $jadwal): int
    {
        $pengguna = User::whereNotNull('User_Key')->first();

        if ($pengguna !== null) {
            Auth::login($pengguna);
        }

        $siklus = $this->argument('cycle') !== null
            ? [(int) $this->argument('cycle')]
            : $this->siklusYangMasihAntre();

        if ($siklus === []) {
            $this->info('Tidak ada penjadwalan yang masih menunggu HC Learn.');

            return self::SUCCESS;
        }

        $adaGagal = false;

        foreach ($siklus as $cycleId) {
            $this->line("Siklus {$cycleId}:");

            $hasil = $jadwal->tarikStatus($cycleId, $pengguna->UserID_Web ?? null);

            $this->line(sprintf(
                '  proses %d | terisi %d | masih antre %d | gagal %d',
                $hasil['proses'],
                $hasil['terisi'],
                $hasil['masih_antre'],
                $hasil['gagal'],
            ));

            foreach ($hasil['contoh_galat'] as $g) {
                $this->warn('  '.$g);
                $adaGagal = true;
            }

            if ($hasil['masih_antre'] > 0) {
                /* Dikatakan apa adanya. "Masih antre" bukan kegagalan — HC Learn memang
                   sedang menggarapnya — dan menyembunyikannya membuat orang menjalankan
                   penjadwalan ulang atas pekerjaan yang sedang berjalan. */
                $this->info('  Sebagian masih digarap HC Learn. Jalankan lagi beberapa saat kemudian.');
            }
        }

        return $adaGagal ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<int> */
    private function siklusYangMasihAntre(): array
    {
        return DB::table('N_PA_Assignment_Integration as i')
            ->join('N_PA_Assignment as a', 'a.Id_PA_Assignment', '=', 'i.PA_Assignment_Id')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('i.Flag_Aktif', 'Y')
            ->where('i.Status_Integrasi', 'ANTRE')
            ->whereNotNull('i.Process_Id')
            ->distinct()
            ->orderBy('p.PA_Cycle_Id')
            ->pluck('p.PA_Cycle_Id')
            ->map(fn ($x) => (int) $x)
            ->all();
    }
}
