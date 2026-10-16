<?php

namespace App\Console\Commands\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaAssignmentBuilderService;
use App\Services\KPI\PerformanceAppraisal\PaCalibrationService;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaCyclePreparationService;
use App\Services\KPI\PerformanceAppraisal\PaCycleSetupService;
use App\Services\KPI\PerformanceAppraisal\PaPayloadMapperService;
use App\Services\KPI\PerformanceAppraisal\PaRaterSubmitService;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use App\Services\KPI\PerformanceAppraisal\PaResultPublishService;
use App\Services\KPI\PerformanceAppraisal\PaSimulasiPengisianService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * ==========================================================================================
 * TIGA SIKLUS SKENARIO: DUA SELESAI, SATU BERJALAN.
 * ==========================================================================================
 *
 * KENAPA PERINTAH INI ADA, PADAHAL SUDAH ADA `pa:demo-cycle`
 *
 * `pa:demo-cycle` membuat SATU siklus dengan penilai diri sendiri saja. Itu cukup untuk
 * melihat satu layar hidup, tetapi tidak cukup untuk menguji apa pun yang membandingkan:
 * riwayat lintas periode, tren seseorang, keranjang admin yang isinya bermacam-macam, dan
 * seluruh perilaku yang hanya muncul ketika sebagian orang selesai sementara sebagian lain
 * belum. Layar yang isinya seragam terlihat benar padahal belum tentu.
 *
 * Perintah ini membuat keadaan yang bisa dibandingkan: dua siklus yang sudah tuntas dan
 * terbit, dan satu siklus yang sedang berjalan dengan pengisian yang SENGAJA tidak rata.
 *
 * LEWAT ALUR SUNGGUHAN, BUKAN INSERT MENTAH
 *
 * Setiap tahap memanggil service yang sama dengan yang dipakai layar admin: buat siklus,
 * siapkan, bentuk penugasan, buka pengisian, terima payload, hitung, kalibrasi, terbitkan.
 * Menyisipkan barisnya langsung akan menghasilkan siklus tanpa salinan aturan — dan siklus
 * seperti itu terlihat utuh di tabel sampai ada layar yang menanyakan pita nilainya, lalu
 * seluruh hasilnya tertahan dengan sebab yang tidak menunjuk penyebab aslinya.
 *
 * Satu-satunya yang ditulis langsung adalah PERPINDAHAN STATUS pada siklus milik perintah
 * ini sendiri (lihat `pindahStatus`), karena perjalanan status sungguhan menuntut jendela
 * waktu dan pencetakan pintu HC Learn yang tidak ada di pengembangan.
 *
 * KENAPA PESERTANYA DIBATASI LEWAT PRA-KECUALI
 *
 * `siapkan()` mengikutkan SELURUH karyawan aktif — 859 orang. Mengisi penilaian untuk
 * semuanya, tiga kali, berarti belasan ribu payload dan berjam-jam ke database. Yang
 * dibatasi karena itu bukan penyiapannya melainkan populasinya, lewat pintu yang memang
 * disediakan untuk itu: pra-kecuali. Hasilnya tetap siklus yang sah dari ujung ke ujung —
 * setiap orang yang IKUT punya penilai, jawaban, dan angka yang lahir dari aturan yang sama.
 *
 * SIAPA MENDAPAT KEADAAN APA (siklus berjalan)
 *
 *   Arya, Aji, Ridho    belum dinilai sama sekali — nol penilai yang mengisi
 *   Erlangga            sebagian penilainya sudah mengisi
 *   Iqbal               seluruh penilainya sudah mengisi dan sudah dikirim
 *   sisanya             diacak berbenih tetap
 *
 * Benihnya tetap supaya dua kali menjalankan menghasilkan sebaran yang sama. Data contoh
 * yang berubah sendiri tiap dijalankan mustahil dipakai membandingkan layar sebelum dan
 * sesudah sebuah perubahan.
 */
class PaSeedSkenarioCommand extends Command
{
    protected $signature = 'pa:seed-skenario
        {--hapus : Buang seluruh siklus skenario beserta turunannya}
        {--peserta=24 : Berapa peserta per siklus}
        {--tutup-siklus-lain : Tutup siklus lain yang sedang OPEN supaya siklus berjalan bisa dibuka}';

    protected $description = 'Membuat tiga siklus PA skenario: dua selesai terbit, satu sedang berjalan';

    /**
     * Penanda kepemilikan. Hanya siklus ber-deskripsi ini yang boleh disentuh perintah ini.
     *
     * TANPA `[`, `_`, MAUPUN `%` — DAN ITU BUKAN SOAL SELERA.
     *
     * Penanda pertama ditulis `[SKENARIO pa:seed-skenario]`, dan seluruh pencarian di berkas
     * ini memakainya lewat `LIKE '%...%'`. Di SQL Server `[...]` adalah KELAS KARAKTER: pola
     * itu tidak mencari teksnya, melainkan cocok dengan string mana pun yang memuat SATU saja
     * huruf dari himpunan itu — praktis setiap deskripsi yang tidak kosong.
     *
     * Akibatnya dua arah sekaligus, dan keduanya senyap. Penjaga "ada siklus lain terbuka"
     * memakai `NOT LIKE`, jadi ia menganggap siklus orang lain milik perintah ini dan
     * melewatinya. Lebih buruk: `--hapus` memakai `LIKE`, jadi ia menyasar hampir SELURUH
     * siklus di basis data — perintah pembersih yang menyapu jauh lebih luas daripada yang
     * ia buat.
     *
     * `_` dan `%` dijauhi karena alasan yang sama: ketiganya wildcard, dan penanda yang
     * mengandung wildcard berhenti menjadi penanda.
     */
    private const PENANDA = 'SKENARIO-PA-SEED';

    /** Benih tetap supaya sebarannya sama tiap kali dijalankan. */
    private const BENIH = 20260907;

    /** Orang yang keadaannya DITENTUKAN, bukan diacak, pada siklus berjalan. */
    private const BELUM_DINILAI = ['Arya', 'Aji', 'Ridho'];

    private const SEBAGIAN_DINILAI = ['Erlangga'];

    private const SUDAH_DINILAI = ['Iqbal'];

    /** Dieja di sini supaya kueri pelepasan tak perlu menebak nama statusnya. */
    private const STATUS_TERKUMPUL_PENILAI = PaRaterSubmitService::STATUS_TERKUMPUL;

    public function handle(
        PaCycleGate $gate,
        PaCycleSetupService $setup,
        PaCyclePreparationService $penyiapan,
        PaAssignmentBuilderService $penugasan,
        PaPayloadMapperService $pemeta,
        PaRaterSubmitService $pengiriman,
        PaResultCalculationService $perhitungan,
        PaCalibrationService $kalibrasi,
        PaResultPublishService $penerbitan,
    ): int {
        if (app()->environment('production')) {
            $this->error('Perintah ini tidak boleh dijalankan di production.');

            return self::FAILURE;
        }

        if ($this->option('hapus')) {
            return $this->hapus();
        }

        $jumlah = max(6, (int) $this->option('peserta'));

        try {
            $this->pastikanTakAdaSiklusTerbukaLain($gate);

            /* TAHUNNYA MENGIKUTI DATA KPI, BUKAN KALENDER.
               Pilar KPI menyaring jadwal memakai TAHUN siklus, dan di basis data pengembangan
               seluruh KPI yang disetujui berada di 2026 (enam karyawan, bulan 01-09). Siklus
               bertahun lain menghasilkan peserta yang seluruhnya TERTAHAN "tidak ada KPI
               disetujui" - benar menurut aturan, tetapi tidak menunjukkan apa pun tentang
               grade, kuadran, maupun kalibrasi, dan siklusnya tidak pernah bisa terbit. */
            $tahun = $this->tahunKpiTerbanyak();
            $this->line("Tahun siklus mengikuti data KPI yang tersedia: {$tahun}");

            /* URUTAN WAKTU DIJAGA: dua siklus selesai lebih dulu, yang berjalan terakhir.
               Riwayat seseorang dibaca menurut periode, dan siklus berjalan yang periodenya
               mendahului siklus yang sudah terbit akan membuat grafik trennya mundur. */
            $selesaiA = $this->bangunSiklus(
                $gate, $setup, $penyiapan, $penugasan, $pemeta, $pengiriman, $perhitungan,
                nama: 'Penilaian Kinerja Triwulan I '.$tahun,
                tahun: $tahun,
                dari: $tahun.'-01-01',
                sampai: $tahun.'-03-31',
                jumlah: $jumlah,
                skenario: 'SELESAI',
            );
            $this->tuntaskan($gate, $perhitungan, $kalibrasi, $penerbitan, $selesaiA);

            $selesaiB = $this->bangunSiklus(
                $gate, $setup, $penyiapan, $penugasan, $pemeta, $pengiriman, $perhitungan,
                nama: 'Penilaian Kinerja Semester I '.$tahun,
                tahun: $tahun,
                dari: $tahun.'-01-01',
                sampai: $tahun.'-06-30',
                jumlah: $jumlah,
                skenario: 'SELESAI',
            );
            $this->tuntaskan($gate, $perhitungan, $kalibrasi, $penerbitan, $selesaiB);

            $berjalan = $this->bangunSiklus(
                $gate, $setup, $penyiapan, $penugasan, $pemeta, $pengiriman, $perhitungan,
                /* SIKLUS BERJALAN MENCAKUP SETAHUN PENUH, bukan semester kedua.
                   KPI yang disetujui di basis data pengembangan tersebar pada bulan 01-09;
                   rentang yang mulai Juli hanya menjangkau dua orang, sehingga hampir seluruh
                   pesertanya mendarat TERTAHAN "tidak ada KPI disetujui" dan layar hasil tidak
                   punya satu pun angka untuk ditunjukkan. Rentang setahun membuat sebarannya
                   bercampur - sebagian bergrade, sebagian tertahan - dan campuran itulah yang
                   dihadapi admin sungguhnya. */
                nama: 'Penilaian Kinerja Tahunan '.$tahun,
                tahun: $tahun,
                dari: $tahun.'-01-01',
                sampai: $tahun.'-12-31',
                jumlah: $jumlah,
                skenario: 'BERJALAN',
            );

            $this->newLine();
            $this->info('Selesai. Tiga siklus skenario siap dipakai:');
            $this->laporkan([$selesaiA, $selesaiB, $berjalan]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->line('  di '.$e->getFile().':'.$e->getLine());

            return self::FAILURE;
        }
    }

    /* ====================================================================== *
     * Pembangunan satu siklus                                                 *
     * ====================================================================== */

    /**
     * @return array{id:int, kode:string, nama:string, skenario:string, peserta:int}
     */
    private function bangunSiklus(
        PaCycleGate $gate,
        PaCycleSetupService $setup,
        PaCyclePreparationService $penyiapan,
        PaAssignmentBuilderService $penugasan,
        PaPayloadMapperService $pemeta,
        PaRaterSubmitService $pengiriman,
        PaResultCalculationService $perhitungan,
        string $nama,
        int $tahun,
        string $dari,
        string $sampai,
        int $jumlah,
        string $skenario,
    ): array {
        $this->newLine();
        $this->info("== {$nama} ({$skenario})");

        $dibuat = $setup->buat([
            'nama_cycle' => $nama,
            'deskripsi' => self::PENANDA.' | Data contoh, bukan penilaian sungguhan.',
            'tahun' => $tahun,
            'periode_dinilai_dari' => $dari,
            'periode_dinilai_sampai' => $sampai,
            'pengisian_dari' => date('Y-m-d', strtotime($sampai.' +7 days')),
            'pengisian_sampai' => date('Y-m-d', strtotime($sampai.' +45 days')),
        ]);

        $cycleId = (int) $dibuat['cycle_id'];
        $this->line("  siklus #{$cycleId} {$dibuat['kode_cycle']}");

        // POPULASI DIBATASI SEBELUM DISIAPKAN. Sesudah peserta terbentuk, pra-kecuali
        // tidak lagi berpengaruh - ia dibaca sekali, saat pembentukan.
        $terpilih = $this->batasiPopulasi($penyiapan, $cycleId, $jumlah, $skenario, $tahun, $dari, $sampai);

        $gate->lupakanMemo();
        $hasilSiap = $penyiapan->siapkan($cycleId);
        $this->line(sprintf(
            '  disiapkan: %d diikutkan, %d dikecualikan, %d tertunda',
            $hasilSiap['peserta']['diikutkan'] ?? 0,
            $hasilSiap['peserta']['dikecualikan'] ?? 0,
            $hasilSiap['peserta']['tertunda'] ?? 0,
        ));

        /* Peserta TERTUNDA disingkirkan supaya siklusnya bisa dikunci.
           Tertunda berarti levelnya tidak punya aturan penilai - keadaan sah yang memang
           harus dihadapi admin, tetapi bukan yang sedang dicontohkan di sini, dan
           `kunci()` menolak selama keranjang itu belum bersih. */
        $this->bersihkanTertunda($cycleId);

        $bentuk = $penugasan->bentukMassalCepat($cycleId, penuh: true, batas: 500);
        $this->line(sprintf(
            '  penugasan: %d dibuat untuk %d peserta',
            $bentuk['dibuat'] ?? 0,
            $bentuk['peserta_tersentuh'] ?? 0,
        ));

        $this->pindahStatus($gate, $cycleId, 'OPEN');

        $paket = $this->paketSoal($cycleId);
        $butir = $this->petaSoal($paket);

        $pintu = $this->cetakPintu($cycleId, $paket);
        $this->line("  pintu penilaian dicetak: {$pintu}");

        $isian = $this->isikan($pemeta, $pengiriman, $cycleId, $paket, $butir, $skenario, $terpilih);
        $this->line(sprintf(
            '  pengisian: %d lembar terisi, %d penilai mengirim, %d peserta lengkap',
            $isian['lembar'], $isian['penilai_mengirim'], $isian['peserta_lengkap'],
        ));

        if ($skenario === 'BERJALAN') {
            /* PESERTA YANG LENGKAP DIHITUNG DI SINI JUGA, bukan hanya diserahkan ke antrean.

               Perhitungan otomatis saat penilai terakhir mengirim memang berjalan — tetapi
               lewat `HitungHasilPesertaJob`, dan job hanya benar-benar dieksekusi kalau ada
               pekerja antrean yang hidup. Data contoh yang angkanya baru muncul ketika
               seseorang kebetulan menjalankan `queue:work` bukan data contoh; ia hanya
               tampak rusak. Dihitung langsung di sini supaya siklusnya bisa dibuka dan
               langsung menunjukkan sesuatu. */
            $terhitung = $this->hitungYangLengkap($perhitungan, $cycleId);
            $this->line("  hasil: {$terhitung['dihitung']} dihitung, {$terhitung['tertahan']} tertahan");

            /* Siklus berjalan DIBIARKAN OPEN — itu memang keadaan yang dicontohkan. */
            return [
                'id' => $cycleId, 'kode' => $dibuat['kode_cycle'], 'nama' => $nama,
                'skenario' => $skenario, 'peserta' => $isian['peserta'],
            ];
        }

        $this->pindahStatus($gate, $cycleId, 'CLOSED');

        return [
            'id' => $cycleId, 'kode' => $dibuat['kode_cycle'], 'nama' => $nama,
            'skenario' => $skenario, 'peserta' => $isian['peserta'],
        ];
    }

    /**
     * Menghitung peserta yang penilainya SUDAH lengkap, satu per satu.
     *
     * @return array{dihitung:int, tertahan:int}
     */
    private function hitungYangLengkap(PaResultCalculationService $perhitungan, int $cycleId): array
    {
        $peserta = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Participant')
            ->pluck('Id_PA_Participant');

        $dihitung = 0;
        $tertahan = 0;

        foreach ($peserta as $pid) {
            if ($perhitungan->sisaPenugasan((int) $pid) > 0) {
                continue;
            }

            try {
                $h = $perhitungan->hitung((int) $pid);
                ($h['status'] ?? null) === 'TERTAHAN' ? $tertahan++ : $dihitung++;
            } catch (RuntimeException $e) {
                // Aturan yang menolak - bukan kerusakan, dan bukan alasan menghentikan sisanya.
            }
        }

        return ['dihitung' => $dihitung, 'tertahan' => $tertahan];
    }

    /**
     * Menghitung, mengkalibrasi sebagian, lalu menerbitkan.
     */
    private function tuntaskan(
        PaCycleGate $gate,
        PaResultCalculationService $perhitungan,
        PaCalibrationService $kalibrasi,
        PaResultPublishService $penerbitan,
        array $siklus,
    ): void {
        $cycleId = $siklus['id'];

        $h = $perhitungan->hitungSiklus($cycleId, null, 500);
        $this->line("  hitung: {$h['dihitung']} dihitung, {$h['tertahan']} tertahan");

        /* YANG TERTAHAN DIKELUARKAN DARI SIKLUS INI, bukan dibiarkan menggantung.

           Penutupan penerbitan DITOLAK selama masih ada peserta tertahan — aturan yang benar:
           sesudah PUBLISHED tidak ada operasi apa pun yang diizinkan lagi, jadi menutupnya
           berarti mengunci orang itu tanpa pernah bisa dihitung ulang. Sebab tertahannya di
           sini selalu di luar modul PA (rencana KPI berbobot 200, akun KPI belum ada), dan
           menunggunya beres berarti siklus contoh tidak akan pernah menjadi contoh siklus
           yang selesai.

           Yang dilakukan sama dengan yang akan dilakukan admin sungguhan: mengeluarkan
           orangnya dari siklus itu, dengan sebab tercatat. Ia tidak hilang — ia ikut di
           siklus yang sedang berjalan, tempat keadaan tertahan memang perlu terlihat. */
        $disingkirkan = $this->keluarkanYangTertahan($cycleId);

        if ($disingkirkan > 0) {
            $this->line("  {$disingkirkan} peserta tertahan dikeluarkan supaya siklus bisa ditutup");
        }

        /* KALIBRASI HANYA SEBAGIAN, dan itu disengaja.
           Siklus yang seluruh hasilnya dikalibrasi tidak bisa dipakai menguji apa pun yang
           membedakan grade mesin dari grade forum - dan justru selisih itulah yang dibaca
           layar hasil, riwayat kalibrasi, dan audit. */
        $dikalibrasi = $this->kalibrasiSebagian($kalibrasi, $cycleId);
        $this->line("  kalibrasi: {$dikalibrasi} hasil digeser forum");

        $t = $penerbitan->terbitkan($cycleId);
        $this->line('  terbit: '.($t['diterbitkan'] ?? $t['terbit'] ?? 0).' hasil');

        try {
            $penerbitan->tutupPenerbitan($cycleId);
            $gate->lupakanMemo();
            $this->line('  penerbitan ditutup; siklus PUBLISHED');
        } catch (RuntimeException $e) {
            /* Penutupan DITOLAK selama masih ada yang tertahan - itu aturan yang benar,
               bukan kegagalan perintah ini. Siklusnya tetap berguna: hasilnya sudah terbit
               dan bisa dibaca; yang tidak terjadi hanya penguncian terakhirnya. */
            $this->warn('  penerbitan belum bisa ditutup: '.$e->getMessage());
        }
    }

    /**
     * Mengeluarkan peserta yang hasilnya masih TERTAHAN dari sebuah siklus.
     *
     * Sebabnya ditulis apa adanya ke `Alasan_Dikecualikan`, memakai kalimat tertahannya
     * sendiri. Pengecualian tanpa sebab menghasilkan orang yang hilang dari penilaian tanpa
     * siapa pun bisa menjawab kenapa — dan pertanyaan itu selalu datang setahun kemudian.
     */
    private function keluarkanYangTertahan(int $cycleId): int
    {
        $tertahan = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('r.Flag_Aktif', 'Y')
            ->where('r.Status_Hasil', 'TERTAHAN')
            ->get(['p.Id_PA_Participant as id', 'r.Alasan_Tertahan as sebab']);

        foreach ($tertahan as $t) {
            DB::table('N_PA_Participant')
                ->where('Id_PA_Participant', $t->id)
                ->update([
                    'Status_Peserta' => 'DIKECUALIKAN',
                    'Alasan_Dikecualikan' => mb_substr(
                        'Dikeluarkan saat penutupan siklus karena hasilnya tertahan: '.(string) $t->sebab,
                        0,
                        450,
                    ),
                ]);
        }

        return $tertahan->count();
    }

    /* ====================================================================== *
     * Populasi                                                                *
     * ====================================================================== */

    /**
     * Mengecualikan semua karyawan KECUALI segelintir yang dipilih.
     *
     * Yang bernama khusus SELALU ikut — merekalah alasan skenario ini ada. Sisanya diambil
     * dengan mendahulukan karyawan yang punya KPI disetujui, supaya hasilnya melahirkan
     * angka alih-alih deretan TERTAHAN yang tidak menunjukkan apa pun tentang grade.
     *
     * @return array<string, string> peta nama-kunci => 'kodePerusahaan|kodeKaryawan'
     */
    private function batasiPopulasi(
        PaCyclePreparationService $penyiapan,
        int $cycleId,
        int $jumlah,
        string $skenario,
        int $tahun,
        string $dari,
        string $sampai,
    ): array {
        $berkpi = $this->kodeBerKpi($tahun, $dari, $sampai);

        /* SIKLUS SELESAI HANYA BERISI ORANG YANG PUNYA KPI DISETUJUI DI PERIODE ITU.
           Siklus dikatakan selesai kalau penerbitannya bisa ditutup, dan penutupan DITOLAK
           selama masih ada peserta tertahan. Mengikutkan orang tanpa KPI berarti siklus yang
           macet di CLOSED selamanya - persis kebalikan dari yang hendak dicontohkan. Yang
           lain tetap punya tempatnya: mereka ikut di siklus yang sedang berjalan, di mana
           tertahan memang keadaan yang wajar dan justru perlu terlihat. */
        if ($skenario === 'SELESAI') {
            $jumlah = max(1, min($jumlah, count($berkpi)));
        }
        $namaKhusus = $skenario === 'BERJALAN'
            ? array_merge(self::BELUM_DINILAI, self::SEBAGIAN_DINILAI, self::SUDAH_DINILAI)
            : array_merge(self::SEBAGIAN_DINILAI, self::SUDAH_DINILAI, self::BELUM_DINILAI);

        $terpilih = [];
        $kunciIkut = [];

        foreach ($namaKhusus as $n) {
            $k = DB::table('Karyawan')
                ->where('Aktif', 'Y')
                ->where('Nama', 'like', $n.'%')
                ->orderBy('Kode_Karyawan')
                ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama']);

            if ($k === null) {
                $this->warn("  karyawan bernama {$n} tidak ditemukan; dilewati");

                continue;
            }

            $kunci = $k->Kode_Perusahaan.'|'.$k->Kode_Karyawan;
            $terpilih[$n] = $kunci;

            /* Pada siklus SELESAI, orang tanpa KPI di periode itu sengaja tidak dipaksa
               ikut: satu orang tertahan cukup untuk membuat seluruh siklus tidak bisa
               ditutup penerbitannya. Perannya di skenario ini memang di siklus berjalan. */
            if ($skenario !== 'SELESAI' || in_array($k->Kode_Karyawan, $berkpi, true)) {
                $kunciIkut[$kunci] = true;
            }
        }

        // Sisanya: dahulukan yang punya KPI disetujui, lalu tambal dengan siapa pun.
        foreach ($skenario === 'SELESAI' ? [$berkpi] : [$berkpi, null] as $saring) {
            if (count($kunciIkut) >= $jumlah) {
                break;
            }

            $q = DB::table('Karyawan')->where('Aktif', 'Y')->orderBy('Kode_Karyawan');

            if ($saring !== null) {
                if ($saring === []) {
                    continue;
                }

                $q->whereIn('Kode_Karyawan', $saring);
            }

            foreach ($q->get(['Kode_Perusahaan', 'Kode_Karyawan']) as $k) {
                if (count($kunciIkut) >= $jumlah) {
                    break;
                }

                $kunciIkut[$k->Kode_Perusahaan.'|'.$k->Kode_Karyawan] = true;
            }
        }

        $dikecualikan = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->get(['Kode_Perusahaan', 'Kode_Karyawan'])
            ->reject(fn ($k) => isset($kunciIkut[$k->Kode_Perusahaan.'|'.$k->Kode_Karyawan]))
            ->map(fn ($k) => [
                'kode_perusahaan' => $k->Kode_Perusahaan,
                'kode_karyawan' => $k->Kode_Karyawan,
            ])
            ->values()
            ->all();

        if ($dikecualikan !== []) {
            $penyiapan->tambahPraKecuali(
                $cycleId,
                $dikecualikan,
                'Data contoh: populasi dibatasi supaya siklus skenario bisa diisi utuh.',
            );
        }

        $this->line('  populasi: '.count($kunciIkut).' ikut, '.count($dikecualikan).' pra-kecuali');

        return $terpilih;
    }

    /**
     * Kode karyawan yang punya KPI DISETUJUI pada tahun dan rentang bulan tertentu.
     *
     * Rangkaian join-nya MENIRU `PaResultCalculationService::parameterKpi()` persis, termasuk
     * lapisan `KPI_Pengajuan_Det` dan `KPI_Update_Performance_Detail`. Menyederhanakannya —
     * misalnya langsung dari `KPI_Update_Performance` ke `KPI_Pengajuan` — menghasilkan daftar
     * yang lebih panjang daripada yang sebenarnya dihitung mesin, dan perintah ini akan
     * memilih orang yang tetap berakhir TERTAHAN.
     *
     * @return list<string>
     */
    private function kodeBerKpi(int $tahun, string $dari, string $sampai): array
    {
        $periode = [];
        $jalan = strtotime(substr($dari, 0, 7).'-01');
        $henti = strtotime(substr($sampai, 0, 7).'-01');

        while ($jalan <= $henti) {
            $periode[] = date('Y-m', $jalan);
            $jalan = strtotime('+1 month', $jalan);
        }

        return DB::table('KPI_Pengajuan_Detail as pd')
            ->join('KPI_Pengajuan as p', function ($j) {
                $j->on('p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
                    ->where('p.Flag_Aktif', 'Y')->where('p.Flag_Approval', 'Y')->whereNull('p.Status');
            })
            ->join('KPI_Master_Penjadwalan as mp', function ($j) use ($tahun) {
                $j->on('mp.Id_Master_Penjadwalan', '=', 'p.Id_Penjadwalan')
                    ->where('mp.Tahun', (string) $tahun);
            })
            ->join('KPI_Pengajuan_Det as pdet', function ($j) use ($periode) {
                $j->on('pdet.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail')
                    ->whereIn('pdet.Periode', $periode);
            })
            ->join('KPI_Update_Performance_Detail as upd', 'upd.Id_Pengajuan_Det', '=', 'pdet.Id_Pengajuan_Det')
            ->join('KPI_Update_Performance as up', function ($j) {
                $j->on('up.Id_Update_Performance', '=', 'upd.Id_Update_Performance')
                    ->where('up.Flag_Aktif', 'Y')->where('up.Flag_Approval', 'Y')
                    ->where('up.Flag_Verifikasi', 'Y')->whereNull('up.Status');
            })
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'p.Id_User')
            ->whereNotNull('u.Kode_Users')
            ->distinct()
            ->pluck('u.Kode_Users')
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->values()
            ->all();
    }

    /** Tahun dengan KPI disetujui terbanyak — di situlah siklus contoh paling masuk akal. */
    private function tahunKpiTerbanyak(): int
    {
        $tahun = DB::table('KPI_Pengajuan as p')
            ->join('KPI_Master_Penjadwalan as mp', 'mp.Id_Master_Penjadwalan', '=', 'p.Id_Penjadwalan')
            ->join('KPI_Pengajuan_Detail as pd', 'pd.Id_Pengajuan', '=', 'p.Id_Pengajuan')
            ->join('KPI_Pengajuan_Det as pdet', 'pdet.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail')
            ->join('KPI_Update_Performance_Detail as upd', 'upd.Id_Pengajuan_Det', '=', 'pdet.Id_Pengajuan_Det')
            ->join('KPI_Update_Performance as up', function ($j) {
                $j->on('up.Id_Update_Performance', '=', 'upd.Id_Update_Performance')
                    ->where('up.Flag_Aktif', 'Y')->where('up.Flag_Approval', 'Y')
                    ->where('up.Flag_Verifikasi', 'Y')->whereNull('up.Status');
            })
            ->where('p.Flag_Aktif', 'Y')->where('p.Flag_Approval', 'Y')->whereNull('p.Status')
            ->groupBy('mp.Tahun')
            ->orderByRaw('COUNT(*) DESC')
            ->value('mp.Tahun');

        return $tahun === null ? (int) date('Y') : (int) $tahun;
    }

    private function bersihkanTertunda(int $cycleId): void
    {
        $n = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'TERTUNDA')
            ->update(['Status_Peserta' => 'DIKECUALIKAN', 'Alasan_Dikecualikan' => 'Level tanpa aturan penilai (data contoh).']);

        if ($n > 0) {
            $this->line("  {$n} peserta tertunda dijadikan dikecualikan supaya siklus bisa berjalan");
        }
    }

    /**
     * Mencetak pintu penilaian untuk SELURUH penugasan aktif di satu siklus.
     *
     * ------------------------------------------------------------------------------------
     * KENAPA SEMUANYA, BUKAN HANYA YANG AKAN DIISI
     * ------------------------------------------------------------------------------------
     *
     * Pintu adalah izin membuka lembar. Di alur sungguhan ia dicetak sekali untuk semua
     * penugasan ketika siklus dibuka — bukan menyusul satu per satu saat orang mulai
     * mengisi. Perintah ini sempat mencetaknya hanya untuk lembar yang memang akan diisi
     * skenario, dan akibatnya seluruh lembar yang sengaja dibiarkan kosong muncul di layar
     * penilai sebagai "Menunggu Akses" dengan tombol mati: data contoh yang tidak bisa
     * dilanjutkan mengisi justru pada bagian yang paling ingin dicoba.
     *
     * ------------------------------------------------------------------------------------
     * AWALAN `SIM-PA-` WAJIB
     * ------------------------------------------------------------------------------------
     *
     * Lembar simulasi menolak token yang tidak berawalan itu, dan `daftarAssignment()` hanya
     * menyusun `url_ujian` untuk token berawalan itu. Token dengan bentuk lain tersimpan
     * rapi, lolos tanpa galat, dan menghasilkan tombol yang tidak pernah menyala.
     *
     * @param  array<int, int>  $paket
     */
    private function cetakPintu(int $cycleId, array $paket): int
    {
        $tugas = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->leftJoin('N_PA_Assignment_Integration as i', function ($j) {
                $j->on('i.PA_Assignment_Id', '=', 'a.Id_PA_Assignment')->where('i.Flag_Aktif', '=', 'Y');
            })
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('a.Flag_Aktif', 'Y')
            ->where('a.Status_Penugasan', '<>', 'DIBATALKAN')
            ->whereNull('i.Id_PA_Assignment_Integration')
            ->get(['a.Id_PA_Assignment as id', 'p.ID_Level_Saat_Cycle as level']);

        $baris = [];

        foreach ($tugas as $t) {
            $paketId = $paket[(int) $t->level] ?? null;

            if ($paketId === null) {
                continue;
            }

            $baris[] = [
                'PA_Assignment_Id' => (int) $t->id,
                'PA_Cycle_Question_Package_Id' => $paketId,
                'Id_Ujian_Token' => PaSimulasiPengisianService::AWALAN_SIMULASI.'SKN'.str_pad((string) $t->id, 8, '0', STR_PAD_LEFT),
                'Flag_Aktif' => 'Y',
            ];
        }

        foreach (array_chunk($baris, 200) as $potongan) {
            DB::table('N_PA_Assignment_Integration')->insert($potongan);
        }

        return count($baris);
    }

    /* ====================================================================== *
     * Pengisian                                                               *
     * ====================================================================== */

    /**
     * Mengisi jawaban sesuai skenario, lalu MENGIRIMKANNYA lewat gerbang penilai.
     *
     * @param  array<int, int>  $paket
     * @param  list<array{kode:string, competency_id:int|null, teks:bool}>  $butir
     * @param  array<string, string>  $terpilih
     * @return array{lembar:int, penilai_mengirim:int, peserta_lengkap:int, peserta:int}
     */
    private function isikan(
        PaPayloadMapperService $pemeta,
        PaRaterSubmitService $pengiriman,
        int $cycleId,
        array $paket,
        array $butir,
        string $skenario,
        array $terpilih,
    ): array {
        $peserta = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Participant')
            ->get(['Id_PA_Participant', 'ID_Level_Saat_Cycle', 'Kode_Perusahaan', 'Kode_Karyawan', 'Nama_Karyawan_Saat_Cycle']);

        $kunci = fn ($p) => $p->Kode_Perusahaan.'|'.$p->Kode_Karyawan;
        $lembar = 0;
        $lengkap = 0;

        foreach ($peserta as $i => $p) {
            $porsi = $this->porsiPengisian($skenario, $kunci($p), $terpilih, $i);

            if ($porsi <= 0) {
                continue;
            }

            $terisi = $this->isikanPeserta(
                $pemeta, (int) $p->Id_PA_Participant,
                $paket[(int) $p->ID_Level_Saat_Cycle] ?? null, $butir, $porsi,
            );

            $lembar += $terisi['diisi'];

            if ($terisi['diisi'] > 0 && $terisi['diisi'] === $terisi['total']) {
                $lengkap++;
            }
        }

        /* PENGIRIMAN LEWAT GERBANGNYA SENDIRI, bukan dengan menulis SELESAI.
           Penilai mengirim seluruh tugasnya sekaligus; melompatinya akan menghasilkan
           penugasan SELESAI yang tidak pernah melewati aturan itu, dan siklus contoh
           berhenti bisa dipakai menguji aturannya. */
        $mengirim = 0;

        foreach ($this->penilaiYangLengkap($cycleId) as $r) {
            try {
                $pengiriman->kirimSemua($r->kp, $r->kk, $cycleId);
                $mengirim++;
            } catch (RuntimeException $e) {
                // Penilai yang ternyata masih punya lembar kosong: memang belum boleh kirim.
            }
        }

        /* ORANG YANG DITENTUKAN "SUDAH DINILAI" HARUS BENAR-BENAR SELESAI.

           Lembarnya memang sudah terisi semua, tetapi penilaiannya baru ikut dihitung sesudah
           penilainya mengirim — dan seorang penilai belum boleh mengirim selama ia masih punya
           lembar kosong untuk peserta LAIN. Pada siklus yang sengaja dibuat timpang, itu berarti
           orang yang seharusnya tuntas justru menggantung karena kelalaian orang ketiga.

           Dilepas lewat `lepasPaksa()` — pintu darurat admin yang memang disediakan untuk
           keadaan ini — bukan dengan menulis SELESAI langsung. Jalur yang dipakai data contoh
           harus jalur yang sama dengan yang dipakai orang. */
        foreach (self::SUDAH_DINILAI as $n) {
            $kunciOrang = $terpilih[$n] ?? null;

            if ($skenario !== 'BERJALAN' || $kunciOrang === null) {
                continue;
            }

            [$kp, $kk] = explode('|', $kunciOrang);

            $pid = DB::table('N_PA_Participant')
                ->where('PA_Cycle_Id', $cycleId)
                ->where('Kode_Perusahaan', $kp)
                ->where('Kode_Karyawan', $kk)
                ->value('Id_PA_Participant');

            if ($pid === null) {
                continue;
            }

            $penilai = DB::table('N_PA_Assignment')
                ->where('PA_Participant_Id', $pid)
                ->where('Flag_Aktif', 'Y')
                ->where('Status_Penugasan', self::STATUS_TERKUMPUL_PENILAI)
                ->get(['Kode_Perusahaan_Penilai as kp', 'Kode_Karyawan_Penilai as kk']);

            foreach ($penilai as $r) {
                try {
                    $pengiriman->lepasPaksa(
                        $cycleId, $r->kp, $r->kk,
                        'Data contoh: penilaian '.$n.' dituntaskan sebagai skenario "sudah dinilai".',
                    );
                    $mengirim++;
                } catch (RuntimeException $e) {
                    // Sudah terlepas lewat pengiriman penilainya sendiri.
                }
            }
        }

        return [
            'lembar' => $lembar,
            'penilai_mengirim' => $mengirim,
            'peserta_lengkap' => $lengkap,
            'peserta' => $peserta->count(),
        ];
    }

    /**
     * Berapa bagian penilai seorang peserta yang mengisi — 0.0 sampai 1.0.
     */
    private function porsiPengisian(string $skenario, string $kunci, array $terpilih, int $urutan): float
    {
        if ($skenario !== 'BERJALAN') {
            // Siklus yang sudah selesai: semua terisi. Itu justru yang membuatnya selesai.
            return 1.0;
        }

        foreach (self::BELUM_DINILAI as $n) {
            if (($terpilih[$n] ?? null) === $kunci) {
                return 0.0;
            }
        }

        foreach (self::SEBAGIAN_DINILAI as $n) {
            if (($terpilih[$n] ?? null) === $kunci) {
                return 0.5;
            }
        }

        foreach (self::SUDAH_DINILAI as $n) {
            if (($terpilih[$n] ?? null) === $kunci) {
                return 1.0;
            }
        }

        /* Sisanya diacak berbenih tetap. Sebarannya sengaja tidak rata: siklus yang sedang
           berjalan memang lebih banyak berisi orang yang setengah jalan daripada yang sudah
           tuntas, dan layar yang dibangun di atas sebaran seragam akan menyembunyikan
           persoalan yang justru muncul pada sebaran nyata. */
        mt_srand(self::BENIH + $urutan);
        $undi = mt_rand(1, 100);
        mt_srand();

        return match (true) {
            $undi <= 25 => 0.0,
            $undi <= 70 => 0.5,
            default => 1.0,
        };
    }

    /**
     * @param  list<array{kode:string, competency_id:int|null, teks:bool}>  $butir
     * @return array{diisi:int, total:int}
     */
    private function isikanPeserta(
        PaPayloadMapperService $pemeta,
        int $participantId,
        ?int $paketId,
        array $butir,
        float $porsi,
    ): array {
        if ($paketId === null) {
            return ['diisi' => 0, 'total' => 0];
        }

        $tugas = DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->where('Status_Penugasan', '<>', 'DIBATALKAN')
            ->orderBy('Id_PA_Assignment')
            ->get(['Id_PA_Assignment', 'Kode_Karyawan_Penilai']);

        $total = $tugas->count();
        $target = (int) ceil($total * $porsi);
        $diisi = 0;

        foreach ($tugas as $t) {
            if ($diisi >= $target) {
                break;
            }

            /* Tokennya DIBACA, tidak dibuat lagi di sini: pintunya sudah dicetak sekaligus
               saat siklus dibuka, persis seperti alur sungguhan. Membuatnya ulang di sini
               berarti dua pintu untuk satu penugasan, dan yang dipakai layar belum tentu
               yang dipakai pengisian. */
            $token = DB::table('N_PA_Assignment_Integration')
                ->where('PA_Assignment_Id', $t->Id_PA_Assignment)
                ->where('Flag_Aktif', 'Y')
                ->value('Id_Ujian_Token');

            if ($token === null) {
                continue;
            }

            $sudah = DB::table('N_PA_Assignment_Payload as p')
                ->join('N_PA_Assignment_Integration as i',
                    'i.Id_PA_Assignment_Integration', '=', 'p.PA_Assignment_Integration_Id')
                ->where('i.Id_Ujian_Token', $token)
                ->exists();

            if (! $sudah) {
                $pemeta->terimaDanPetakan($token, $this->payload($t, $butir, $token));
            }

            $diisi++;
        }

        return ['diisi' => $diisi, 'total' => $total];
    }

    /**
     * Penilai yang SELURUH tugasnya di siklus ini sudah terkumpul — merekalah yang boleh
     * mengirim.
     */
    private function penilaiYangLengkap(int $cycleId)
    {
        return DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('a.Flag_Aktif', 'Y')
            ->where('a.Status_Penugasan', '<>', 'DIBATALKAN')
            ->groupBy('a.Kode_Perusahaan_Penilai', 'a.Kode_Karyawan_Penilai')
            ->havingRaw("SUM(CASE WHEN a.Status_Penugasan = 'TERKUMPUL' THEN 1 ELSE 0 END) > 0")
            ->havingRaw("SUM(CASE WHEN a.Status_Penugasan NOT IN ('TERKUMPUL','SELESAI') THEN 1 ELSE 0 END) = 0")
            ->selectRaw('a.Kode_Perusahaan_Penilai as kp, a.Kode_Karyawan_Penilai as kk')
            ->get();
    }

    /* ====================================================================== *
     * Kalibrasi                                                               *
     * ====================================================================== */

    private function kalibrasiSebagian(PaCalibrationService $kalibrasi, int $cycleId): int
    {
        $hasil = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('r.Flag_Aktif', 'Y')
            ->where('r.Status_Hasil', 'DIHITUNG')
            ->orderBy('r.Id_PA_Result')
            ->get(['r.Id_PA_Result', 'r.Kode_Grade_Sistem']);

        $pita = DB::table('N_PA_Cycle_Score_Range_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Score_Usage', 'GRADE')
            ->orderBy('Urutan_Tampil')
            ->pluck('Kode_Range')
            ->all();

        if ($pita === []) {
            return 0;
        }

        $n = 0;

        foreach ($hasil as $i => $h) {
            // Kira-kira sepertiganya, dipilih dengan pola tetap.
            if ($i % 3 !== 0) {
                continue;
            }

            $sekarang = array_search($h->Kode_Grade_Sistem, $pita, true);
            $baru = $pita[max(0, ($sekarang === false ? 1 : $sekarang) - 1)] ?? null;

            if ($baru === null || $baru === $h->Kode_Grade_Sistem) {
                continue;
            }

            try {
                $kalibrasi->kalibrasi(
                    (int) $h->Id_PA_Result,
                    $baru,
                    null,
                    'Forum kalibrasi menilai kontribusi lintas divisi belum tercermin pada angka mesin.',
                    1,
                    'Forum Kalibrasi (data contoh)',
                );
                $n++;
            } catch (RuntimeException $e) {
                // Grade yang tidak sah atau tidak berubah: dilewati, bukan digagalkan.
            }
        }

        return $n;
    }

    /* ====================================================================== *
     * Paket soal & payload                                                    *
     * ====================================================================== */

    /** @return array<int, int> level => paket id */
    private function paketSoal(int $cycleId): array
    {
        $level = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->whereNotNull('ID_Level_Saat_Cycle')
            ->distinct()
            ->pluck('ID_Level_Saat_Cycle');

        $hasil = [];

        foreach ($level as $l) {
            $l = (int) $l;

            $ada = DB::table('N_PA_Cycle_Question_Package')
                ->where('PA_Cycle_Id', $cycleId)
                ->where('ID_Level', $l)
                ->value('Id_PA_Cycle_Question_Package');

            $hasil[$l] = $ada !== null ? (int) $ada : (int) DB::table('N_PA_Cycle_Question_Package')->insertGetId([
                'PA_Cycle_Id' => $cycleId,
                'ID_Level' => $l,
                'Id_Master_Ujian' => 'SKN-L'.$l,
                'Nama_Ujian_Sumber' => 'Paket Skenario Level '.$l,
                'Sumber_Kontrak' => 'SKENARIO',
                'Catatan' => self::PENANDA.' | Bukan paket HC Learn sungguhan.',
                'Flag_Aktif' => 'Y',
            ], 'Id_PA_Cycle_Question_Package');
        }

        return $hasil;
    }

    /**
     * @param  array<int, int>  $paket
     * @return list<array{kode:string, competency_id:int|null, teks:bool}>
     */
    private function petaSoal(array $paket): array
    {
        $berskor = DB::table('N_PA_Competency')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Berskor', 'Y')
            ->orderBy('Urutan_Tampil')
            ->pluck('Id_PA_Competency')
            ->all();

        if ($berskor === []) {
            throw new RuntimeException('Master kompetensi kosong; skenario tidak bisa dibuat.');
        }

        $kualitatif = DB::table('N_PA_Competency as k')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'k.PA_Competency_Type_Id')
            ->where('k.Flag_Aktif', 'Y')
            ->where('t.Kode_Type', 'KUALITATIF')
            ->orderBy('k.Urutan_Tampil')
            ->pluck('k.Id_PA_Competency')
            ->all();

        $butir = [];

        foreach ($berskor as $i => $id) {
            $butir[] = ['kode' => sprintf('K%02d', $i + 1), 'competency_id' => (int) $id, 'teks' => false];
        }

        foreach ($kualitatif as $i => $id) {
            $butir[] = ['kode' => sprintf('QL%02d', $i + 1), 'competency_id' => (int) $id, 'teks' => true];
        }

        foreach ($paket as $paketId) {
            foreach ($butir as $urutan => $b) {
                if (DB::table('N_PA_Cycle_Question_Package_Item')
                    ->where('PA_Cycle_Question_Package_Id', $paketId)
                    ->where('Id_Jenis_Soal', $b['kode'])
                    ->exists()) {
                    continue;
                }

                DB::table('N_PA_Cycle_Question_Package_Item')->insert([
                    'PA_Cycle_Question_Package_Id' => $paketId,
                    'Id_Jenis_Soal' => $b['kode'],

                    /* KODE YANG DIPAKAI KONTRAK IKUT DITULIS.
                       Pemeta mencocokkan `Detail[].Kode_Soal` dengan kolom INI, bukan dengan
                       `Id_Jenis_Soal`. Selama kolom ini kosong, seluruh butir kiriman skenario
                       mendarat tak terpetakan — penugasan tak pernah selesai, dan tak satu pun
                       galat muncul di mana pun. Di sini keduanya kebetulan bernilai sama karena
                       paket skenario memang mengarang kodenya sendiri; yang penting adalah
                       kolomnya terisi, sehingga jalur yang dilalui data contoh sama dengan
                       jalur yang dilalui kiriman HC Learn sungguhan. */
                    'Kode_Soal_Sumber' => $b['kode'],
                    'Nama_Bagian_Sumber' => $b['teks'] ? 'Pertanyaan uraian '.$b['kode'] : 'Kompetensi '.$b['kode'],
                    'Jenis_Soal' => $b['teks'] ? 'teks' : 'skala',
                    'Skor_Maksimal' => $b['teks'] ? null : 5,
                    'Bobot_Penilaian' => null,
                    'PA_Competency_Id' => $b['competency_id'],
                    'Urutan_Tampil' => $urutan + 1,
                    'Flag_Aktif' => 'Y',
                ]);
            }
        }

        return $butir;
    }

    /**
     * @param  list<array{kode:string, competency_id:int|null, teks:bool}>  $butir
     * @return array<string, mixed>
     */
    private function payload(object $tugas, array $butir, string $token): array
    {
        mt_srand(self::BENIH + (int) $tugas->Id_PA_Assignment);

        /* Kecenderungan per penilai, bukan acak murni per butir.
           Penilai sungguhan punya kecenderungan - ada yang murah nilai, ada yang pelit -
           dan angka acak seragam menghapus persis sifat yang membuat kalibrasi ada. */
        $condong = mt_rand(-1, 1);

        $uraian = [
            'Konsisten menyelesaikan pekerjaan tepat waktu dan mudah diajak berkoordinasi.',
            'Perlu memperdalam penguasaan teknis pada area yang menjadi tanggung jawabnya.',
            'Beberapa kali mengambil inisiatif memperbaiki proses tanpa diminta.',
            'Disarankan mengikuti penugasan lintas divisi untuk memperluas sudut pandang.',
        ];

        $detail = [];

        foreach ($butir as $i => $b) {
            if ($b['teks']) {
                $detail[] = [
                    /* `Kode_Soal` ADALAH YANG DIBACA PEMETA. Sampai 11 September 2026 berkas
                       ini hanya mengirim `Id_Jenis_Soal`, dan pemeta menolak setiap butir tanpa
                       `Kode_Soal` — artinya perintah ini tidak pernah bisa menyelesaikan satu
                       kiriman pun. Keduanya dikirim: yang pertama supaya bentuknya tetap sama
                       dengan muatan HC Learn, yang kedua karena itulah kuncinya. */
                    'Kode_Soal' => $b['kode'],
                    'Id_Jenis_Soal' => $b['kode'],
                    'Nama_Soal' => 'Pertanyaan uraian',
                    'Jenis_Soal' => 'teks',
                    'Skor' => null,
                    'Skor_Maksimal' => null,
                    'Bobot_Penilaian' => null,
                    'Jawaban_Teks' => $uraian[$i % count($uraian)],
                ];

                continue;
            }

            $detail[] = [
                'Kode_Soal' => $b['kode'],
                'Id_Jenis_Soal' => $b['kode'],
                'Nama_Soal' => 'Kompetensi '.$b['kode'],
                'Jenis_Soal' => 'skala',
                'Skor' => max(1, min(5, mt_rand(3, 5) + $condong)),
                'Skor_Maksimal' => 5,
                'Bobot_Penilaian' => null,
            ];
        }

        mt_srand();

        return [
            'Kode_Karyawan' => $tugas->Kode_Karyawan_Penilai,
            'Id_Penjadwalan_Integrasi' => 0,
            'Id_Ujian_Token' => $token,
            'Id_Ujian_Nilai_Akhir' => 'SIM-'.$token,
            'Api_Version' => 'pa-skenario',
            'Finished_At' => date('Y-m-d H:i:s'),
            'Detail' => $detail,
        ];
    }

    /* ====================================================================== *
     * Status, laporan, pembersihan                                            *
     * ====================================================================== */

    private function pastikanTakAdaSiklusTerbukaLain(PaCycleGate $gate): void
    {
        $lain = DB::table('N_PA_Cycle')
            ->where('Status_Cycle', 'OPEN')
            ->where('Flag_Aktif', 'Y')
            ->where(function ($q) {
                $q->whereNull('Deskripsi')->orWhere('Deskripsi', 'not like', '%'.self::PENANDA.'%');
            })
            ->get(['Id_PA_Cycle', 'Kode_Cycle', 'Nama_Cycle']);

        if ($lain->isEmpty()) {
            return;
        }

        if (! $this->option('tutup-siklus-lain')) {
            throw new RuntimeException(sprintf(
                'Siklus %s sedang terbuka. Hanya satu siklus boleh terbuka pada satu waktu. '
                .'Tutup dulu siklus itu, atau jalankan ulang dengan --tutup-siklus-lain.',
                $lain->pluck('Kode_Cycle')->implode(', '),
            ));
        }

        foreach ($lain as $s) {
            DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $s->Id_PA_Cycle)->update(['Status_Cycle' => 'CLOSED']);
            $this->warn("  siklus {$s->Kode_Cycle} ditutup supaya skenario bisa dibuka");
        }

        $gate->lupakanMemo();
    }

    /**
     * Memindahkan status siklus SKENARIO lewat kolomnya langsung.
     *
     * Perjalanan status sungguhan menuntut jendela waktu dan pencetakan pintu HC Learn yang
     * tidak ada di pengembangan. Yang dijaga di sini justru batasnya: hanya siklus
     * ber-penanda milik perintah ini yang tersentuh, dan aturan "satu siklus terbuka"
     * tetap ditegakkan sebelum menulis OPEN — aturan yang hanya dipasang di satu dari dua
     * jalur setara bukan aturan.
     */
    private function pindahStatus(PaCycleGate $gate, int $cycleId, string $status): void
    {
        if ($status === 'OPEN') {
            $lain = DB::table('N_PA_Cycle')
                ->where('Status_Cycle', 'OPEN')
                ->where('Flag_Aktif', 'Y')
                ->where('Id_PA_Cycle', '<>', $cycleId)
                ->first(['Kode_Cycle']);

            if ($lain !== null) {
                throw new RuntimeException("Siklus {$lain->Kode_Cycle} masih terbuka.");
            }
        }

        DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->where('Deskripsi', 'like', '%'.self::PENANDA.'%')
            ->update(['Status_Cycle' => $status]);

        $gate->lupakanMemo();
    }

    private function laporkan(array $siklus): void
    {
        $this->table(
            ['Kode', 'Nama', 'Skenario', 'Status', 'Peserta', 'Hasil dihitung', 'Terbit'],
            array_map(function ($s) {
                $status = DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $s['id'])->value('Status_Cycle');

                $cacah = fn ($st) => DB::table('N_PA_Result as r')
                    ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
                    ->where('p.PA_Cycle_Id', $s['id'])->where('r.Flag_Aktif', 'Y')
                    ->where('r.Status_Hasil', $st)->count();

                return [
                    $s['kode'], $s['nama'], $s['skenario'], $status, $s['peserta'],
                    $cacah('DIHITUNG'), $cacah('TERBIT'),
                ];
            }, $siklus),
        );
    }

    /**
     * Membuang siklus skenario beserta seluruh turunannya, dari daun ke akar.
     *
     * Dibatasi PENANDA di deskripsi. Perintah pembersih yang menyapu lebih luas daripada
     * yang ia buat adalah cara paling lazim kehilangan data yang tidak pernah dimaksudkan
     * ikut terhapus.
     */
    private function hapus(): int
    {
        $ids = DB::table('N_PA_Cycle')
            ->where('Deskripsi', 'like', '%'.self::PENANDA.'%')
            ->pluck('Id_PA_Cycle')
            ->map(fn ($v) => (int) $v)
            ->all();

        if ($ids === []) {
            $this->info('Tidak ada siklus skenario untuk dihapus.');

            return self::SUCCESS;
        }

        $this->warn('Menghapus siklus skenario: '.implode(', ', $ids));

        DB::transaction(function () use ($ids) {
            $peserta = DB::table('N_PA_Participant')->whereIn('PA_Cycle_Id', $ids)->pluck('Id_PA_Participant');
            $hasil = $this->kumpulkan('N_PA_Result', 'PA_Participant_Id', $peserta, 'Id_PA_Result');
            $tugas = $this->kumpulkan('N_PA_Assignment', 'PA_Participant_Id', $peserta, 'Id_PA_Assignment');
            $integrasi = $this->kumpulkan('N_PA_Assignment_Integration', 'PA_Assignment_Id', $tugas, 'Id_PA_Assignment_Integration');
            $payload = $this->kumpulkan('N_PA_Assignment_Payload', 'PA_Assignment_Integration_Id', $integrasi, 'Id_PA_Assignment_Payload');
            $paket = DB::table('N_PA_Cycle_Question_Package')->whereIn('PA_Cycle_Id', $ids)->pluck('Id_PA_Cycle_Question_Package');

            /* URUTANNYA DARI DAUN KE AKAR, dan turunan HASIL dibuang SEBELUM penugasan.
               `N_PA_Result_Note` menunjuk keduanya - hasil DAN penugasan yang melahirkannya
               (FK_PA_Result_Note_Assignment) - jadi membuang penugasan lebih dulu ditolak
               basis data di tengah jalan, meninggalkan siklus separuh terhapus yang tidak
               bisa dibersihkan lewat layar mana pun. */
            $this->buang('N_PA_Assignment_Score', 'PA_Assignment_Payload_Id', $payload);
            $this->buang('N_PA_Assignment_Payload', 'PA_Assignment_Integration_Id', $integrasi);
            $this->buang('N_PA_Assignment_Integration', 'PA_Assignment_Id', $tugas);

            $this->buang('N_PA_Calibration_History', 'PA_Result_Id', $hasil);
            $this->buang('N_PA_Result_Note', 'PA_Result_Id', $hasil);
            $this->buang('N_PA_Result_Competency', 'PA_Result_Id', $hasil);
            $this->buang('N_PA_Result_Rater_Category', 'PA_Result_Id', $hasil);

            $this->buang('N_PA_Assignment', 'PA_Participant_Id', $peserta);
            $this->buang('N_PA_Result', 'PA_Participant_Id', $peserta);

            $this->buang('N_PA_Cycle_Question_Package_Item', 'PA_Cycle_Question_Package_Id', $paket);
            DB::table('N_PA_Cycle_Question_Package')->whereIn('PA_Cycle_Id', $ids)->delete();

            $this->buang('N_PA_Participant', 'Id_PA_Participant', $peserta);

            /* Salinan aturan penilai bertingkat DUA: detailnya menunjuk headernya, dan
               header tidak bisa dibuang selama detailnya masih ada. Detail sendiri tidak
               punya PA_Cycle_Id, jadi ia dijangkau lewat id headernya. */
            $headerAturan = DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
                ->whereIn('PA_Cycle_Id', $ids)
                ->pluck('Id_PA_Cycle_Rater_Policy_Snapshot');

            $this->buang('N_PA_Cycle_Rater_Policy_Detail_Snapshot', 'PA_Cycle_Rater_Policy_Snapshot_Id', $headerAturan);

            foreach ([
                'N_PA_Cycle_Rater_Policy_Snapshot',
                'N_PA_Cycle_Score_Range_Snapshot', 'N_PA_Cycle_Pillar_Weight_Snapshot',
                'N_PA_Cycle_Competency_Requirement_Snapshot', 'N_PA_Cycle_Pre_Exclusion',
                'N_PA_Cycle_Setup_Step',
            ] as $tabel) {
                DB::table($tabel)->whereIn('PA_Cycle_Id', $ids)->delete();
            }

            DB::table('N_PA_Cycle')->whereIn('Id_PA_Cycle', $ids)->delete();
        });

        $this->info(count($ids).' siklus skenario dihapus.');

        return self::SUCCESS;
    }

    /**
     * `whereIn` YANG DIPOTONG. SQL Server membatasi 2.100 parameter per pernyataan, dan satu
     * siklus di sini berisi 958 peserta yang melahirkan ribuan penugasan — satu `whereIn`
     * utuh menabrak batas itu dan muncul sebagai galat database yang tidak menyebut sebabnya.
     */
    private function kumpulkan(string $tabel, string $kolom, $nilai, string $ambil)
    {
        $hasil = collect();

        foreach (array_chunk($nilai instanceof \Illuminate\Support\Collection ? $nilai->all() : $nilai, 1000) as $potongan) {
            $hasil = $hasil->merge(DB::table($tabel)->whereIn($kolom, $potongan)->pluck($ambil));
        }

        return $hasil;
    }

    private function buang(string $tabel, string $kolom, $nilai): void
    {
        foreach (array_chunk($nilai instanceof \Illuminate\Support\Collection ? $nilai->all() : $nilai, 1000) as $potongan) {
            DB::table($tabel)->whereIn($kolom, $potongan)->delete();
        }
    }
}
