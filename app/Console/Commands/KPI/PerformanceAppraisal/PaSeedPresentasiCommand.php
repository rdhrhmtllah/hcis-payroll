<?php

namespace App\Console\Commands\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingHitungUlangService;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingKandidatService;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingSesiService;
use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use App\Services\KPI\PerformanceAppraisal\PaAdminDashboardService;
use App\Services\KPI\PerformanceAppraisal\PaAssignmentBuilderService;
use App\Services\KPI\PerformanceAppraisal\PaCalibrationService;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaCyclePreparationService;
use App\Services\KPI\PerformanceAppraisal\PaCycleSetupService;
use App\Services\KPI\PerformanceAppraisal\PaHcLearnScheduleService;
use App\Services\KPI\PerformanceAppraisal\PaParticipantReadService;
use App\Services\KPI\PerformanceAppraisal\PaPayloadMapperService;
use App\Services\KPI\PerformanceAppraisal\PaQuestionBindingMasterService;
use App\Services\KPI\PerformanceAppraisal\PaRaterSubmitService;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use App\Services\KPI\PerformanceAppraisal\PaResultNoteService;
use App\Services\KPI\PerformanceAppraisal\PaResultPublishService;
use App\Services\KPI\PerformanceAppraisal\PaSimulasiPengisianService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * ==========================================================================================
 * TIGA SIKLUS UNTUK DIPRESENTASIKAN: DUA LAMPAU YANG TERBIT, SATU BERJALAN YANG TIMPANG.
 * ==========================================================================================
 *
 * KENAPA ADA, PADAHAL SUDAH ADA `pa:seed-skenario`
 *
 * `pa:seed-skenario` juga membuat tiga siklus, tetapi berbeda pada tiga hal yang menentukan:
 *
 *  1. PAKET SOALNYA DIBANGUN DARI KOMPETENSI WAJIB TIAP LEVEL. `pa:seed-skenario` memberi
 *     SETIAP level butir uraian dan butir yang sama untuk semua level, sehingga tidak ada
 *     satu pun level yang bebas esai dan keadaan "penilaian tanpa pertanyaan naratif" —
 *     yang nyata, dan yang dimiliki level 1 sampai 5 — mustahil ditunjukkan. Di sini butir
 *     paket adalah kompetensi wajib level itu apa adanya, dan naratif atau tidaknya
 *     ditentukan `Flag_Berskor` pada kompetensinya, bukan oleh perintah ini.
 *
 *     Ia TIDAK disalin dari ikatan paket master, dan itu bukan pilihan melainkan kenyataan
 *     yang terukur. Lihat `bangunPaketSoal()`: aturan pengikatan menuntut bijeksi antara
 *     bagian ujian HC Learn dan kompetensi wajib, sedangkan katalog HC Learn pada 14
 *     September 2026 tidak punya satu paket pun yang cukup besar untuk level 2 ke atas.
 *
 *  2. KPI IKUT DISEMAI, jadi populasinya tidak dibatasi segelintir orang yang kebetulan
 *     punya realisasi terverifikasi di basis data pengembangan. `pa:seed-skenario` menyusut
 *     ke sekitar enam peserta pada siklus yang harus terbit; di sini lima puluh lebih.
 *
 *  3. PERIODENYA TIGA TRIWULAN BERURUTAN YANG TIDAK TUMPANG TINDIH, dan capaian KPI-nya
 *     BERGERAK antar triwulan. Riwayat dan grafik tren seseorang baru bercerita kalau
 *     angkanya berubah; tiga siklus dengan nilai yang sama persis menghasilkan garis datar
 *     yang tidak membuktikan apa pun tentang layar yang menggambarnya.
 *
 * SIAPA MENDAPAT KEADAAN APA (siklus berjalan, triwulan III)
 *
 *   Erlangga        seluruh penilainya selesai, hasilnya DIHITUNG lalu DIGESER FORUM —
 *                   tinggal diterbitkan
 *   Ridho, Imam     kira-kira SEPARUH lembar penilainya terisi, separuh lagi kosong
 *   Iqbal, Aji,     seluruh lembarnya terisi KECUALI SATU; lembar yang disisakan
 *   Tari            didahulukan lembar dirinya sendiri dan dipastikan tanpa butir esai
 *   Andre           belum tersentuh sama sekali — nol lembar terisi
 *   4 tanpa KPI     sengaja diikutkan dari karyawan yang memang tidak punya akun KPI,
 *                   supaya keranjang "Butuh KPI" pada Antrean Tindak Lanjut ada isinya
 *   3 tanpa penilai seluruh penugasannya dibatalkan lewat jalur pembatalan yang sah,
 *                   supaya keranjang "Butuh Penilai" ada isinya
 *   sisanya         timpang berbenih tetap: sebagian tuntas lalu dihitung, sebagian tuntas
 *                   tetapi SENGAJA belum dihitung (keranjang "Siap Dihitung"), sebagian
 *                   terisi tetapi penilainya belum menekan kirim, sebagian belum mengisi
 *
 * Keranjang `tertahan_lain` TIDAK dikarang. Satu-satunya sebabnya adalah paket soal yang
 * tidak menutup jenis kompetensi wajib sebuah level — merusak paket demi mengisi keranjang
 * berarti menyemai cacat dan menyebutnya data contoh. Kalau ikatan master memang berlubang,
 * keranjang itu akan terisi sendiri, dan itulah satu-satunya cara ia boleh terisi.
 *
 * KENAPA KPI IKUT DISEMAI
 *
 * Nilai akhir PA 75% berasal dari KPI, dan hasil tanpa KPI DITAHAN — benar menurut aturan,
 * tetapi layar hasil, kuadran, dan kalibrasi jadi kosong. Di basis data pengembangan hanya
 * segelintir karyawan punya KPI disetujui, jadi perintah ini menyemaikannya untuk peserta
 * yang dipilihnya, dengan pencapaian yang SENGAJA bervariasi — antar orang supaya grade dan
 * kuadrannya tersebar, dan antar TRIWULAN supaya orang yang sama bergerak naik atau turun.
 * Setiap barisnya bertanda pada `Deskripsi` parameternya, dan `--hapus` mencabutnya kembali
 * lewat penanda itu.
 */
class PaSeedPresentasiCommand extends Command
{
    /**
     * Penanda kepemilikan, dipakai `LIKE '%...%'`.
     *
     * TANPA `[`, `_`, MAUPUN `%`: ketiganya wildcard di SQL Server, dan penanda yang
     * mengandung wildcard berhenti menjadi penanda — `--hapus` akan menyasar siklus milik
     * orang lain. Pelajaran ini sudah pernah dibayar di `pa:seed-skenario`.
     */
    private const PENANDA = 'PRESENTASI-PA-SEED';

    /** Penanda pada KPI yang disemai, supaya bisa dibedakan dari KPI sungguhan dan dicabut. */
    private const PENANDA_KPI = 'PRESENTASI-PA-SEED KPI contoh, bukan KPI sungguhan.';

    /**
     * Penanda pada baris target yang DITAMBAHKAN ke rencana KPI milik orang lain.
     *
     * Ditulis pada `Nama_Target` karena `KPI_Pengajuan_Det` tidak punya kolom keterangan
     * maupun `Flag_Aktif`. Tanpa penanda, baris target yang ditambahkan perintah ini tidak
     * bisa dibedakan dari target yang benar-benar disepakati orangnya.
     */
    private const PENANDA_TARGET = 'PRESENTASI-PA-SEED';

    /** Benih tetap: dua kali dijalankan menghasilkan sebaran yang sama. */
    private const BENIH = 20260912;

    /**
     * Orang yang WAJIB ikut ketiga siklus.
     *
     * Dicocokkan dengan `Nama LIKE '<nama>%'` pada karyawan aktif, dan perintah ini BERHENTI
     * kalau salah satunya tidak ketemu. Diam-diam melanjutkan tanpa mereka menghasilkan
     * siklus yang tampak penuh tetapi tidak bisa dipakai bercerita, dan sebabnya baru
     * ketahuan di depan orang.
     */
    private const WAJIB_IKUT = ['Aji', 'Iqbal', 'Ridho', 'Erlangga', 'Andre', 'Imam', 'Tari'];

    /** Tuntas, dihitung, lalu digeser forum — tinggal diterbitkan. */
    private const NAMA_SIAP_TERBIT = 'Erlangga';

    /** Kira-kira separuh lembar penilainya terisi. */
    private const NAMA_SEPARUH = ['Ridho', 'Imam'];

    /** Seluruh lembarnya terisi kecuali SATU. */
    private const NAMA_SISA_SATU = ['Iqbal', 'Aji', 'Tari'];

    /** Nol lembar terisi. */
    private const NAMA_BELUM_TERSENTUH = 'Andre';

    /** Berapa lembar yang disisakan bagi `NAMA_SISA_SATU`. */
    private const SISA_LEMBAR = 1;

    /** Berapa peserta tanpa akun KPI yang sengaja diikutkan (keranjang "Butuh KPI"). */
    private const JUMLAH_TANPA_KPI = 4;

    /** Berapa peserta yang seluruh penugasannya dibatalkan (keranjang "Butuh Penilai"). */
    private const JUMLAH_TANPA_PENILAI = 3;

    protected $signature = 'pa:seed-presentasi
        {--peserta=64 : Berapa peserta yang ikut di ketiga siklus (sebagian terhapus saat siklus terbit)}
        {--hapus : Buang siklus presentasi beserta KPI yang disemainya}
        {--tutup-siklus-lain : Tutup siklus lain yang sedang OPEN supaya siklus berjalan bisa dibuka}
        {--hanya-berjalan : Lewati dua siklus lampau, hanya bangun siklus berjalan (OPEN)}
        {--hclearn : Pakai pintu HC Learn sungguhan untuk siklus berjalan (bawaan: pintu simulasi lokal)}
        {--rajin=92 : Persen penilai yang menuntaskan tugasnya di siklus berjalan (1-100)}
        {--tak-mengirim=10 : Persen penilai yang lembarnya terisi tetapi tidak menekan kirim (0-100)}';

    protected $description = '[DEV] Tiga siklus PA untuk presentasi: dua lampau yang terbit, satu berjalan yang timpang';

    public function handle(
        PaCycleGate $gate,
        PaCycleSetupService $setup,
        PaCyclePreparationService $penyiapan,
        PaQuestionBindingMasterService $ikatan,
        PaAssignmentBuilderService $penugasan,
        PaHcLearnScheduleService $penjadwalan,
        PaPayloadMapperService $pemeta,
        PaRaterSubmitService $pengiriman,
        PaResultCalculationService $perhitungan,
        PaCalibrationService $kalibrasi,
        PaResultPublishService $penerbitan,
    ): int {
        if (app()->isProduction()) {
            $this->error('DITOLAK: perintah ini mengarang nilai penilaian dan KPI, dan tidak boleh berjalan di production.');

            return self::FAILURE;
        }

        if ($this->option('hapus')) {
            return $this->hapus();
        }

        $jumlah = max(6, (int) $this->option('peserta'));

        try {
            /* PENANDA TANGAN UNTUK HC LEARN.
               Penjadwalan sungguhan menandatangani permintaannya memakai identitas pengguna
               yang sedang masuk; di terminal tidak ada yang masuk. `identitas()` punya jalan
               keluar berupa akun layanan, tetapi di pengembangan setelannya kosong — jadi
               satu pengguna ber-User_Key dipakai sebagai penanda tangan, dan disebutkan
               supaya jejak auditnya di sisi mereka bisa dijelaskan. */
            $penandaTangan = $this->masukSebagaiPenandaTangan();

            $this->tutupSiklusLain($gate);

            $tahun = 2026;
            $orang = $this->pilihPopulasi($jumlah);

            $this->semaiKpi(array_values($orang['ikut']), $tahun);

            /* URUTAN WAKTUNYA DIJAGA: yang lampau dibangun lebih dulu dan periodenya
               mendahului. Riwayat seseorang dibaca menurut periode, dan siklus berjalan yang
               periodenya mendahului siklus terbit membuat grafik trennya mundur.

               Triwulanannya TIDAK tumpang tindih. Nilai KPI diambil YTD atas rentang periode
               siklus, jadi rentang yang bertumpang membuat dua siklus membaca bulan yang sama
               dan menghasilkan angka yang nyaris identik — persis garis datar yang hendak
               dihindari. */
            $lampau = [];

            if (! $this->option('hanya-berjalan')) {
                foreach ([
                    ['Penilaian Kinerja Triwulan I '.$tahun, $tahun.'-01-01', $tahun.'-03-31'],
                    ['Penilaian Kinerja Triwulan II '.$tahun, $tahun.'-04-01', $tahun.'-06-30'],
                ] as $ke => [$nama, $dari, $sampai]) {
                    $siklus = $this->bangun(
                        $gate, $setup, $penyiapan, $ikatan, $penugasan, $penjadwalan,
                        nama: $nama,
                        tahun: $tahun,
                        dari: $dari,
                        sampai: $sampai,
                        orang: $orang,
                        hclearn: false,
                    );

                    $this->isiSeluruhnya($pemeta, $pengiriman, (int) $siklus['id']);

                    /* Triwulan I menempuh alur sampai PUBLISHED; Triwulan II berhenti di tengah sesi
                       Balancing (Draft) — keduanya lewat service yang sama dengan layar admin. */
                    $this->tuntaskan(
                        $gate, $penyiapan, $perhitungan, $kalibrasi, $penerbitan,
                        (int) $siklus['id'],
                        setiapKe: $ke === 0 ? 4 : 3,
                        sampaiTerbit: $ke === 0,
                    );

                    $lampau[] = $siklus;
                }
            } else {
                $this->info('Siklus lampau dilewati (--hanya-berjalan).');
            }

            $berjalan = $this->bangun(
                $gate, $setup, $penyiapan, $ikatan, $penugasan, $penjadwalan,
                nama: 'Penilaian Kinerja Triwulan III '.$tahun,
                tahun: $tahun,
                dari: $tahun.'-07-01',
                sampai: $tahun.'-09-30',
                orang: $orang,
                hclearn: (bool) $this->option('hclearn'),
                berjalan: true,
            );

            $this->isiTimpang($pemeta, $pengiriman, $penugasan, (int) $berjalan['id'], $orang);
            $this->hitungBerjalan($perhitungan, $kalibrasi, (int) $berjalan['id'], $orang);

            $this->newLine();
            $this->info('Selesai. Tiga siklus presentasi siap:');
            $this->laporkan([...$lampau, $berjalan], $penandaTangan);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->line('  di '.$e->getFile().':'.$e->getLine());

            return self::FAILURE;
        }
    }

    /* ====================================================================== *
     * Persiapan lingkungan                                                    *
     * ====================================================================== */

    private function masukSebagaiPenandaTangan(): string
    {
        $u = DB::table('KPI_Users')
            ->whereNotNull('User_Key')->where('User_Key', '<>', '')
            ->whereNotNull('Kode_Users')
            ->orderBy('Id_Users')
            ->first(['Id_Users', 'Kode_Users']);

        if ($u === null) {
            /* Bukan cuma soal HC Learn. Kalibrasi mencatat SIAPA yang memutuskan, dan
               `PaCalibrationService` menolak pelaku kosong — tanpa pengguna yang masuk,
               ketiga siklus ini akan berdiri tanpa satu pun geseran forum, dan layar riwayat
               kalibrasi yang kosong terbaca sebagai "fiturnya belum jalan". */
            throw new RuntimeException(
                'Tidak ada pengguna ber-User_Key di KPI_Users. Penjadwalan HC Learn tidak bisa '
                .'ditandatangani dan kalibrasi tidak punya pelaku, jadi perintah ini berhenti '
                .'di sini alih-alih menghasilkan siklus yang separuh.'
            );
        }

        Auth::loginUsingId($u->Id_Users);

        return (string) $u->Kode_Users;
    }

    private function tutupSiklusLain(PaCycleGate $gate): void
    {
        $lain = DB::table('N_PA_Cycle')
            ->where('Status_Cycle', 'OPEN')
            ->where('Flag_Aktif', 'Y')
            ->where(function ($q) {
                $q->whereNull('Deskripsi')->orWhere('Deskripsi', 'not like', '%'.self::PENANDA.'%');
            })
            ->get(['Id_PA_Cycle', 'Kode_Cycle']);

        if ($lain->isEmpty()) {
            return;
        }

        if (! $this->option('tutup-siklus-lain')) {
            throw new RuntimeException(sprintf(
                'Siklus %s sedang terbuka, dan hanya satu siklus boleh terbuka pada satu waktu. '
                .'Tutup dulu siklus itu, atau jalankan ulang dengan --tutup-siklus-lain.',
                $lain->pluck('Kode_Cycle')->implode(', '),
            ));
        }

        foreach ($lain as $s) {
            DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $s->Id_PA_Cycle)->update(['Status_Cycle' => 'CLOSED']);
            $this->warn("  siklus {$s->Kode_Cycle} ditutup supaya siklus presentasi bisa dibuka");
        }

        $gate->lupakanMemo();
    }

    /* ====================================================================== *
     * Populasi                                                                *
     * ====================================================================== */

    /**
     * Memilih siapa yang ikut, dan mengembalikan kunci orang-orang bernama.
     *
     * @return array{ikut: array<string, string>, khusus: array<string, string>, tanpa_kpi: list<string>}
     */
    private function pilihPopulasi(int $jumlah): array
    {
        $khusus = [];
        $ikut = [];

        foreach (self::WAJIB_IKUT as $nama) {
            $k = DB::table('Karyawan')
                ->where('Aktif', 'Y')
                ->where('Nama', 'like', $nama.'%')
                ->orderBy('Kode_Karyawan')
                ->first(['Kode_Perusahaan', 'Kode_Karyawan']);

            if ($k === null) {
                throw new RuntimeException(
                    "Karyawan bernama {$nama} tidak ditemukan. Skenario presentasi ini bertumpu "
                    .'pada ketujuhnya, jadi melanjutkan tanpa dia akan menghasilkan siklus yang '
                    .'tidak bercerita apa pun.'
                );
            }

            $kunci = $k->Kode_Perusahaan.'|'.$k->Kode_Karyawan;
            $khusus[$nama] = $kunci;
            $ikut[$kunci] = $kunci;
        }

        /* EMPAT ORANG YANG MEMANG TIDAK PUNYA AKUN KPI, diikutkan dengan sengaja.
           |
           | Keranjang "Butuh KPI" pada Antrean Tindak Lanjut hanya terisi kalau ada peserta
           | yang benar-benar tidak bisa diambil nilai KPI-nya. Mengarang penahanan dengan
           | membuang KPI orang yang punya akan menghapus data yang bukan milik perintah ini;
           | mengikutkan orang yang memang tidak punya akun menghasilkan penahanan yang
           | sebabnya SUNGGUHAN, dan tindak lanjutnya di layar admin juga sungguhan. */
        $tanpaKpi = [];

        foreach ($this->karyawanTanpaAkunKpi(self::JUMLAH_TANPA_KPI) as $kunci) {
            if (isset($ikut[$kunci])) {
                continue;
            }

            $tanpaKpi[] = $kunci;
            $ikut[$kunci] = $kunci;
        }

        if (count($tanpaKpi) < self::JUMLAH_TANPA_KPI) {
            $this->warn(sprintf(
                '  hanya %d karyawan aktif tanpa akun KPI yang bisa diikutkan (diminta %d); '
                .'keranjang "Butuh KPI" akan lebih sedikit isinya',
                count($tanpaKpi), self::JUMLAH_TANPA_KPI,
            ));
        }

        /* KANDIDATNYA DIAMBIL DARI YANG TERBUKTI PERNAH DIIKUTKAN, bukan dari daftar karyawan.
           |
           | `siapkan()` hanya mengikutkan karyawan yang levelnya punya aturan penilai DAN punya
           | relasi organisasi; sisanya ia kecualikan sendiri. Pada basis data pengembangan itu
           | sekitar satu dari lima — terukur: 958 karyawan disiapkan, 190 diikutkan. Memilih
           | lima puluh nama dari daftar karyawan karena itu menghasilkan siklus berisi sembilan
           | orang, dan angka "50" di laporan perintah ini akan berbohong tanpa satu pun galat.
           |
           | Yang dipakai sebagai tanda kelayakan adalah kenyataan: siapa yang SUDAH pernah
           | berstatus DIIKUTKAN di siklus mana pun. Itu jawaban yang sama dengan yang akan
           | diberikan `siapkan()`, tanpa perlu menirukan aturannya di sini — dan aturan yang
           | ditirukan di dua tempat akan menyimpang. */
        /* DAN HARUS PUNYA AKUN KPI. Nilai akhir 75% berasal dari KPI, dan kode karyawan yang
           tidak punya pasangan di `KPI_Users` ditahan dengan alasan "tidak punya akun KPI" —
           penahanan yang benar, tetapi tidak bisa dibereskan perintah ini tanpa mengarang akun
           login. Terukur pada pengembangan: 186 dari 190 karyawan yang layak punya akunnya,
           jadi menyaringnya di sini hanya melepas empat orang dan menukarnya dengan peserta
           yang hasilnya benar-benar bisa ditunjukkan. */
        $berakun = DB::table('KPI_Users')->whereNotNull('Kode_Users')->pluck('Kode_Users')
            ->map(fn ($x) => strtoupper(trim((string) $x)))->unique()->all();
        $berakun = array_flip($berakun);

        $layak = DB::table('N_PA_Participant')
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->whereNotNull('Kode_Karyawan')
            ->orderBy('Kode_Karyawan')
            ->get(['Kode_Perusahaan', 'Kode_Karyawan'])
            ->filter(fn ($p) => isset($berakun[strtoupper(trim((string) $p->Kode_Karyawan))]))
            ->map(fn ($p) => $p->Kode_Perusahaan.'|'.$p->Kode_Karyawan)
            ->unique()
            ->values();

        foreach ($layak as $kunci) {
            if (count($ikut) >= $jumlah) {
                break;
            }

            $ikut[$kunci] = $kunci;
        }

        /* Kalau belum pernah ada siklus sama sekali, ditambal dari daftar karyawan.
           |
           | DISARING PADA LEVEL YANG PUNYA ATURAN PENILAI, bukan diambil apa adanya. Kriteria
           | itu persis yang dipakai `siapkan()` untuk memutuskan DIIKUTKAN atau TERTUNDA;
           | tanpa saringan ini, sebagian calon mendarat TERTUNDA lalu dikecualikan, dan
           | siklus yang diminta berisi 55 orang berdiri dengan 49 tanpa satu pun galat.
           |
           | AKUN KPI TETAP DISARING, persis seperti cabang di atas. Cabang ini dulu tidak
           | menyaringnya, dan selama basis data masih punya siklus lama hal itu tidak pernah
           | terlihat karena cabang ini tidak pernah dipakai. Pada basis data yang bersih ia
           | menjadi satu-satunya pemasok, dan hasilnya terukur: dari 55 peserta, 17 tidak
           | punya akun KPI — bukan empat seperti yang dilaporkan perintah ini sendiri. Empat
           | orang tanpa akun yang MEMANG disengaja sudah masuk lebih dulu di atas, jadi
           | menyaring di sini tidak menghilangkannya. */
        if (count($ikut) < $jumlah) {
            $levelBeraturan = DB::table('N_PA_Rater_Policy')
                ->where('Status_Policy', 'ACTIVE')->where('Flag_Aktif', 'Y')
                ->distinct()->pluck('ID_Level')->map(fn ($x) => (int) $x)->all();

            $tambahan = DB::table('Karyawan as k')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v',
                    'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
                ->where('k.Aktif', 'Y')
                ->whereNull('k.Tanggal_Resign')
                ->when($levelBeraturan !== [], fn ($q) => $q->whereIn('v.ID_Level', $levelBeraturan))
                ->orderBy('k.Kode_Karyawan')
                ->get(['k.Kode_Perusahaan', 'k.Kode_Karyawan'])
                ->filter(fn ($k) => isset($berakun[strtoupper(trim((string) $k->Kode_Karyawan))]));

            foreach ($tambahan as $k) {
                if (count($ikut) >= $jumlah) {
                    break;
                }

                $kunci = $k->Kode_Perusahaan.'|'.$k->Kode_Karyawan;
                $ikut[$kunci] = $kunci;
            }
        }

        /* Dihitung ULANG dari populasi yang benar-benar terbentuk, bukan disalin dari niat.
           Versi sebelumnya melaporkan angka yang DIRENCANAKAN, dan ketika cabang penambal
           meloloskan orang tanpa akun, laporannya tetap berkata "4" sementara kenyataannya
           17 — angka yang tidak pernah salah karena ia tidak pernah memeriksa apa pun. */
        $nyataTanpaAkun = 0;

        foreach ($ikut as $kunci) {
            [, $kk] = explode('|', $kunci);

            if (! isset($berakun[strtoupper(trim($kk))])) {
                $nyataTanpaAkun++;
            }
        }

        $this->line(sprintf(
            '  populasi: %d orang dicalonkan (%d pernah diikutkan, %d tanpa akun KPI — %d di antaranya disengaja)',
            count($ikut), $layak->count(), $nyataTanpaAkun, count($tanpaKpi),
        ));

        return ['ikut' => $ikut, 'khusus' => $khusus, 'tanpa_kpi' => $tanpaKpi];
    }

    /**
     * Karyawan aktif yang levelnya punya aturan penilai TETAPI tidak punya akun KPI.
     *
     * Levelnya diperiksa karena orang yang levelnya tanpa aturan penilai tidak akan pernah
     * berstatus DIIKUTKAN — ia mendarat TERTUNDA lalu dikecualikan, dan keranjang yang
     * hendak diisi tetap kosong tanpa satu pun tanda kenapa.
     *
     * @return list<string> 'kodePerusahaan|kodeKaryawan'
     */
    private function karyawanTanpaAkunKpi(int $berapa): array
    {
        $berakun = DB::table('KPI_Users')->whereNotNull('Kode_Users')->pluck('Kode_Users')
            ->map(fn ($x) => strtoupper(trim((string) $x)))->unique()->flip();

        $levelBeraturan = DB::table('N_PA_Rater_Policy')
            ->where('Status_Policy', 'ACTIVE')->where('Flag_Aktif', 'Y')
            ->distinct()->pluck('ID_Level')->map(fn ($x) => (int) $x)->all();

        if ($levelBeraturan === []) {
            return [];
        }

        return DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v',
                'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->whereIn('v.ID_Level', $levelBeraturan)
            ->orderBy('k.Kode_Karyawan')
            ->get(['k.Kode_Perusahaan', 'k.Kode_Karyawan'])
            ->reject(fn ($k) => isset($berakun[strtoupper(trim((string) $k->Kode_Karyawan))]))
            ->take($berapa)
            ->map(fn ($k) => $k->Kode_Perusahaan.'|'.$k->Kode_Karyawan)
            ->values()
            ->all();
    }

    /* ====================================================================== *
     * KPI                                                                     *
     * ====================================================================== */

    /**
     * Melengkapi KPI peserta sampai bisa dihitung PA.
     *
     * ── YANG KURANG BUKAN RENCANANYA, MELAINKAN REALISASINYA ──────────────────
     *
     * Terukur pada pengembangan 12 September 2026: dari 186 karyawan yang layak ikut siklus,
     * 177 SUDAH punya rencana KPI 2026 yang disetujui — dengan parameter, bobot, target
     * bulanan, dan total bobot tepat 100 pada ketujuh-belas-puluh-tujuhnya. Yang tidak ada
     * adalah realisasi terverifikasi: hanya 6 yang punya.
     *
     * Karena itu perintah ini TIDAK mengarang parameter bagi mereka. Ia mengisi realisasi di
     * atas rencana yang sudah ada, sehingga layar presentasi menyebut nama parameter yang
     * sungguhan — bukan satu parameter karangan bernama sama untuk lima puluh orang. Rencana
     * dikarang HANYA bagi yang benar-benar belum punya.
     *
     * Menambahkan rencana kedua di atas rencana yang sudah disetujui justru berbahaya:
     * bobotnya akan berjumlah 200, dan `PaResultCalculationService` menahan hasil yang total
     * bobotnya bukan 100 — dengan alasan yang menunjuk master KPI, bukan perintah ini,
     * sehingga penyebabnya nyaris mustahil ditemukan kembali.
     *
     * @param  list<string>  $kunciOrang  'kodePerusahaan|kodeKaryawan'
     */
    private function semaiKpi(array $kunciOrang, int $tahun): void
    {
        $idPenjadwalan = DB::table('KPI_Master_Penjadwalan')
            ->where('Tahun', (string) $tahun)->orderBy('Id_Master_Penjadwalan')
            ->value('Id_Master_Penjadwalan');

        if ($idPenjadwalan === null) {
            $this->warn("  tidak ada KPI_Master_Penjadwalan bertahun {$tahun}; KPI tidak disemai");

            return;
        }

        /* Periode yang disemai menutup Januari sampai September — tepat ketiga triwulan yang
           dipakai ketiga siklus. Nilai KPI diambil YTD atas rentang periode siklus, jadi
           bulan yang tidak disemai berarti satu siklus penuh yang pesertanya TERTAHAN
           "tidak ada KPI disetujui" tanpa satu pun tanda bahwa sebabnya di sini. */
        $periode = [];

        for ($b = 1; $b <= 9; $b++) {
            $periode[] = sprintf('%d-%02d', $tahun, $b);
        }

        $direalisasikan = 0;
        $rencanaBaru = 0;
        $sudahLengkap = 0;
        $tanpaAkun = 0;
        $ditambal = 0;

        foreach ($kunciOrang as $kunci) {
            [, $kodeKaryawan] = explode('|', $kunci);

            $user = DB::table('KPI_Users')->where('Kode_Users', $kodeKaryawan)->first(['Id_Users']);

            if ($user === null) {
                $tanpaAkun++;

                continue;
            }

            $idUser = (int) $user->Id_Users;

            $capaian = $this->capaianPerPeriode($kodeKaryawan, $periode);

            $parameter = $this->parameterDisetujui($idUser, $tahun);

            if ($parameter === []) {
                $this->semaiSatuKpi($idUser, $kodeKaryawan, (int) $idPenjadwalan, $periode, $capaian);
                $rencanaBaru++;

                continue;
            }

            $parameter = $this->lengkapiPeriodeRencana($parameter, $periode, $ditambal);

            $n = $this->realisasikanRencana($idUser, $kodeKaryawan, $parameter, $capaian);

            $n > 0 ? $direalisasikan++ : $sudahLengkap++;
        }

        $this->line(sprintf(
            '  KPI: %d rencana asli direalisasikan, %d rencana dibuat dari nol, %d sudah lengkap, '
            .'%d tanpa akun, %d baris target ditambal',
            $direalisasikan, $rencanaBaru, $sudahLengkap, $tanpaAkun, $ditambal,
        ));

        $this->periksaKpiOrangBernama($tahun, $periode);
    }

    /**
     * Melengkapi baris target rencana KPI untuk periode yang belum punya.
     *
     * ── KENAPA PERLU, DAN KENAPA INI BUKAN MENGARANG ──────────────────────────
     *
     * Nilai KPI dibaca PA lewat rentang periode siklus. Rencana KPI sungguhan di basis data
     * pengembangan tidak selalu membentang Januari sampai Desember — terukur pada 14
     * September 2026, rencana Tari (Y62) baru mulai Mei, sehingga pada siklus Triwulan I ia
     * TERTAHAN "tidak ada KPI disetujui" dan dikeluarkan saat penutupan. Orangnya hilang dari
     * siklus yang seharusnya memuatnya, dan sebabnya tidak terbaca di layar mana pun.
     *
     * Yang ditambahkan hanyalah BARIS TARGET pada periode yang kosong, dengan nilai target
     * yang disalin dari periode lain milik parameter yang sama — bukan angka karangan, dan
     * bukan parameter baru. Bobotnya tidak disentuh sama sekali, sehingga total bobot rencana
     * tetap seperti semula dan penjaga "total bobot harus 100" tidak pernah terusik.
     *
     * Tiap baris bertanda `PENANDA_TARGET` pada `Nama_Target`: tabel ini tidak punya kolom
     * keterangan maupun `Flag_Aktif`, jadi tanpa penanda tidak ada cara membedakan target
     * yang ditambal perintah ini dari target yang benar-benar disepakati orangnya.
     *
     * @param  list<array{pengajuan:int, detail:int, bobot:float, formula:?int, det:list<array{id:int, periode:string, target:float}>}>  $parameter
     * @param  list<string>  $periode
     * @return list<array{pengajuan:int, detail:int, bobot:float, formula:?int, det:list<array{id:int, periode:string, target:float}>}>
     */
    private function lengkapiPeriodeRencana(array $parameter, array $periode, int &$ditambal): array
    {
        foreach ($parameter as $i => $p) {
            $ada = array_column($p['det'], 'periode');
            $kurang = array_values(array_diff($periode, $ada));

            if ($kurang === []) {
                continue;
            }

            /* Target disalin dari periode yang SUDAH ADA milik parameter ini. Parameter yang
               sama sekali belum punya target tidak ditambal: tanpa satu pun angka acuan,
               apa pun yang ditulis di sini adalah karangan, dan karangan yang menumpang pada
               parameter sungguhan jauh lebih sulit dikenali daripada rencana yang jelas-jelas
               dibuat perintah ini. */
            if ($p['det'] === []) {
                continue;
            }

            $acuan = (float) $p['det'][0]['target'];

            foreach ($kurang as $pr) {
                $id = (int) DB::table('KPI_Pengajuan_Det')->insertGetId([
                    'Id_Pengajuan_Detail' => $p['detail'],
                    'Nama_Target' => 'Target '.$pr.' — '.self::PENANDA_TARGET,
                    'Nilai_Target' => $acuan,
                    'Periode' => $pr,
                    'Id_Target_Breakdown' => null,
                ], 'Id_Pengajuan_Det');

                $parameter[$i]['det'][] = ['id' => $id, 'periode' => $pr, 'target' => $acuan];
                $ditambal++;
            }
        }

        return $parameter;
    }

    /**
     * Memeriksa bahwa PA benar-benar bisa membaca KPI ketujuh orang bernama, per triwulan.
     *
     * ── KENAPA DIPERIKSA LEWAT PEMBACA PA, BUKAN LEWAT KUERI SENDIRI ──────────
     *
     * Penyemaian di atas memakai penyaringnya sendiri. Selama penyaring itu lebih longgar
     * daripada penyaring `PaResultCalculationService`, perintah ini akan melaporkan "KPI
     * disemai" untuk orang yang tetap TERTAHAN — dan penahanan itu baru terlihat sepuluh
     * menit kemudian, sebagai peserta yang HILANG dari siklus karena dikeluarkan saat
     * penutupan. Yang ditanya di sini karena itu bukan "apakah saya menulis sesuatu",
     * melainkan "apakah yang saya tulis terbaca oleh yang akan membacanya".
     *
     * Hasilnya PERINGATAN, bukan kegagalan. Sebab yang paling sering muncul — total bobot
     * rencana bukan 100, atau satu kode karyawan menunjuk dua akun KPI — berakar pada data
     * master KPI milik orang lain, dan membetulkannya bukan wewenang perintah data contoh.
     *
     * @param  list<string>  $periode
     */
    private function periksaKpiOrangBernama(int $tahun, array $periode): void
    {
        $triwulan = [
            'TW I' => array_values(array_filter($periode, fn ($p) => (int) substr($p, 5, 2) <= 3)),
            'TW II' => array_values(array_filter($periode, fn ($p) => (int) substr($p, 5, 2) >= 4 && (int) substr($p, 5, 2) <= 6)),
            'TW III' => array_values(array_filter($periode, fn ($p) => (int) substr($p, 5, 2) >= 7 && (int) substr($p, 5, 2) <= 9)),
        ];

        $peringatan = [];

        foreach (self::WAJIB_IKUT as $nama) {
            $k = DB::table('Karyawan')->where('Aktif', 'Y')->where('Nama', 'like', $nama.'%')
                ->orderBy('Kode_Karyawan')->first(['Kode_Karyawan']);

            if ($k === null) {
                continue;
            }

            $kode = (string) $k->Kode_Karyawan;
            $akun = DB::table('KPI_Users')->where('Kode_Users', $kode)->pluck('Id_Users')->all();

            if (count($akun) !== 1) {
                $peringatan[] = sprintf(
                    '%s (%s): %d akun KPI. PA menahan hasilnya karena memilih salah satu berarti '
                    .'berpeluang memakai KPI milik orang lain.',
                    $nama, $kode, count($akun),
                );

                continue;
            }

            $idUser = (int) $akun[0];

            $bobot = (float) DB::table('KPI_Pengajuan_Detail as pd')
                ->join('KPI_Pengajuan as p', function ($j) {
                    $j->on('p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
                        ->where('p.Flag_Aktif', 'Y')->where('p.Flag_Approval', 'Y')->whereNull('p.Status');
                })
                ->join('KPI_Master_Penjadwalan as mp', function ($j) use ($tahun) {
                    $j->on('mp.Id_Master_Penjadwalan', '=', 'p.Id_Penjadwalan')->where('mp.Tahun', (string) $tahun);
                })
                ->where('p.Id_User', $idUser)
                ->sum('pd.Bobot');

            if (abs($bobot - 100.0) > 0.01) {
                $peringatan[] = sprintf(
                    '%s (%s): total bobot rencana KPI %s aktif berjumlah %.2f, bukan 100. PA '
                    .'MENAHAN hasilnya di SELURUH siklus, dan pada siklus yang terbit ia akan '
                    .'dikeluarkan saat penutupan. Sebabnya rencana KPI miliknya sendiri, bukan '
                    .'perintah ini — periksa KPI_Pengajuan miliknya sebelum presentasi.',
                    $nama, $kode, $tahun, $bobot,
                );

                continue;
            }

            $kosong = [];

            foreach ($triwulan as $label => $bulan) {
                $ada = DB::table('KPI_Pengajuan_Detail as pd')
                    ->join('KPI_Pengajuan as p', function ($j) {
                        $j->on('p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
                            ->where('p.Flag_Aktif', 'Y')->where('p.Flag_Approval', 'Y')->whereNull('p.Status');
                    })
                    ->join('KPI_Pengajuan_Det as d', 'd.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail')
                    ->join('KPI_Update_Performance_Detail as upd', 'upd.Id_Pengajuan_Det', '=', 'd.Id_Pengajuan_Det')
                    ->join('KPI_Update_Performance as up', function ($j) {
                        $j->on('up.Id_Update_Performance', '=', 'upd.Id_Update_Performance')
                            ->where('up.Flag_Aktif', 'Y')->where('up.Flag_Approval', 'Y')
                            ->where('up.Flag_Verifikasi', 'Y')->whereNull('up.Status');
                    })
                    ->where('p.Id_User', $idUser)
                    ->whereIn('d.Periode', $bulan)
                    ->exists();

                if (! $ada) {
                    $kosong[] = $label;
                }
            }

            if ($kosong !== []) {
                $peringatan[] = sprintf(
                    '%s (%s): tidak ada realisasi KPI terverifikasi pada %s. Ia akan TERTAHAN di '
                    .'siklus itu.',
                    $nama, $kode, implode(' dan ', $kosong),
                );
            }
        }

        if ($peringatan === []) {
            $this->line('  KPI ketujuh orang bernama terbaca di ketiga triwulan.');

            return;
        }

        $this->newLine();
        $this->warn('  PERIKSA — KPI orang bernama yang tidak akan terbaca PA:');

        foreach ($peringatan as $p) {
            $this->line('    - '.$p);
        }

        $this->newLine();
    }

    /**
     * Pencapaian KPI per periode — bervariasi antar ORANG dan BERGERAK antar TRIWULAN.
     *
     * ── KENAPA TIDAK SATU ANGKA PER ORANG ─────────────────────────────────────
     *
     * Versi sebelumnya memberi satu pencapaian untuk seluruh tahun. Dengan dua siklus itu
     * belum kelihatan; dengan tiga siklus berurutan ia menghasilkan orang yang nilainya
     * sama persis di ketiga triwulan — dan layar riwayat, grafik tren, serta perbandingan
     * antar-siklus lalu menggambar garis datar yang tidak membuktikan apa pun tentang
     * dirinya sendiri. Data contoh yang tidak bisa membedakan "layarnya benar" dari
     * "datanya kebetulan seragam" lebih buruk daripada tidak ada.
     *
     * Arah geraknya diundi PER ORANG, bukan ditetapkan. Kalau semuanya menanjak, sebaran
     * kuadran bergeser serempak tiap triwulan dan forum kalibrasi tidak punya apa pun untuk
     * diperdebatkan.
     *
     * @param  list<string>  $periode  'YYYY-MM'
     * @return array<string, float> periode => rasio pencapaian (1.00 = tepat target)
     */
    private function capaianPerPeriode(string $kodeKaryawan, array $periode): array
    {
        mt_srand(self::BENIH + crc32($kodeKaryawan));
        $dasar = mt_rand(62, 118) / 100;
        $arah = mt_rand(0, 1) === 1 ? 1 : -1;
        $langkah = mt_rand(3, 11) / 100;
        mt_srand();

        $hasil = [];

        foreach ($periode as $p) {
            $triwulan = (int) ceil(((int) substr($p, 5, 2)) / 3);

            /* Riak per bulan supaya akumulasi YTD-nya tidak terbaca sebagai tiga anak tangga
               yang rapi. Kecil saja: yang harus terbaca adalah trennya, bukan deraunya. */
            mt_srand(self::BENIH + crc32($kodeKaryawan.'|'.$p));
            $riak = mt_rand(-4, 4) / 100;
            mt_srand();

            /* Dijepit supaya tidak ada capaian negatif maupun capaian yang melampaui apa pun
               yang pernah terlihat di lapangan. Penjepitan skor 50-125 milik mesin KPI
               ditegakkan belakangan, di tempat skornya benar-benar dihitung. */
            $hasil[$p] = round(
                max(0.45, min(1.30, $dasar + $arah * $langkah * ($triwulan - 2) + $riak)),
                4,
            );
        }

        return $hasil;
    }

    /**
     * Parameter KPI yang SUDAH disetujui pada tahun itu, beserta rencana per periodenya.
     *
     * Rangkaian penyaringnya meniru `PaResultCalculationService::parameterKpi()`: kalau di
     * sini lebih longgar, perintah ini akan menganggap seseorang punya rencana yang sah
     * padahal mesin PA tidak melihatnya, lalu melewatkannya — dan orangnya tetap TERTAHAN.
     *
     * @return list<array{pengajuan:int, detail:int, bobot:float, formula:?int, det:list<array{id:int, periode:string, target:float}>}>
     */
    private function parameterDisetujui(int $idUser, int $tahun): array
    {
        $parameter = DB::table('KPI_Pengajuan_Detail as pd')
            ->join('KPI_Pengajuan as p', function ($j) {
                $j->on('p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
                    ->where('p.Flag_Aktif', 'Y')->where('p.Flag_Approval', 'Y')->whereNull('p.Status');
            })
            ->join('KPI_Master_Penjadwalan as mp', function ($j) use ($tahun) {
                $j->on('mp.Id_Master_Penjadwalan', '=', 'p.Id_Penjadwalan')->where('mp.Tahun', (string) $tahun);
            })
            ->where('p.Id_User', $idUser)
            ->orderBy('pd.Id_Pengajuan_Detail')
            ->get([
                'pd.Id_Pengajuan_Detail as detail',
                'pd.Id_Pengajuan as pengajuan',
                'pd.Bobot as bobot',
                'pd.Id_Master_Formula as formula',
            ]);

        if ($parameter->isEmpty()) {
            return [];
        }

        $det = DB::table('KPI_Pengajuan_Det')
            ->whereIn('Id_Pengajuan_Detail', $parameter->pluck('detail')->all())
            ->orderBy('Periode')
            ->get(['Id_Pengajuan_Det as id', 'Id_Pengajuan_Detail as detail', 'Periode as periode', 'Nilai_Target as target'])
            ->groupBy('detail');

        $hasil = [];

        foreach ($parameter as $p) {
            $hasil[] = [
                'pengajuan' => (int) $p->pengajuan,
                'detail' => (int) $p->detail,
                'bobot' => (float) $p->bobot,
                'formula' => $p->formula === null ? null : (int) $p->formula,
                'det' => ($det[$p->detail] ?? collect())->map(fn ($d) => [
                    'id' => (int) $d->id,
                    'periode' => (string) $d->periode,
                    'target' => (float) $d->target,
                ])->values()->all(),
            ];
        }

        return $hasil;
    }

    /**
     * Mengisi realisasi terverifikasi di atas rencana KPI yang sudah ada.
     *
     * ── NILAI AKTUAL DIBALIK MENURUT RUMUSNYA ─────────────────────────────────
     *
     * Rumusnya benar-benar dievaluasi `KpiFormulaHelper`, jadi "aktual = target × capaian"
     * hanya benar untuk rumus yang semakin besar semakin baik. Pada rumus kebalikannya, nilai
     * itu menghasilkan pencapaian yang justru berlawanan — dan data presentasi yang angkanya
     * berlawanan dengan maksudnya jauh lebih buruk daripada data yang tidak ada, karena tidak
     * ada satu pun tanda bahwa ia salah.
     *
     * Tiga rumus yang dipakai data pengembangan:
     *   3  `actual/target`                          semakin besar semakin baik
     *   4  `1+(target-actual)/target`               semakin KECIL semakin baik
     *   5  `IF(actual==0,1,0)`                      biner: hanya nol yang bernilai penuh
     *
     * Rumus 5 karena itu diberi aktual NOL bagi yang pencapaiannya bagus, dan sebesar target
     * bagi yang tidak — bukan angka di antaranya, karena di antaranya tidak ada.
     *
     * @param  list<array{pengajuan:int, detail:int, bobot:float, formula:?int, det:list<array{id:int, periode:string, target:float}>}>  $parameter
     * @param  array<string, float>  $capaian  periode => rasio pencapaian
     * @return int berapa baris realisasi yang dibuat
     */
    private function realisasikanRencana(int $idUser, string $kodeKaryawan, array $parameter, array $capaian): int
    {
        /* Periode yang SUDAH punya realisasi terverifikasi dilewati: menambah baris kedua
           untuk periode yang sama menggandakan aktualnya, dan `parameterKpi()` menjumlahkan
           seluruh realisasi — sehingga pencapaiannya melonjak tanpa sebab yang kelihatan. */
        $sudahAda = DB::table('KPI_Update_Performance')
            ->whereIn('Id_Pengajuan', array_unique(array_column($parameter, 'pengajuan')))
            ->where('Flag_Aktif', 'Y')->where('Flag_Approval', 'Y')->where('Flag_Verifikasi', 'Y')
            ->whereNull('Status')
            ->get(['Id_Pengajuan', 'Periode'])
            ->mapWithKeys(fn ($r) => [$r->Id_Pengajuan.'|'.$r->Periode => true])
            ->all();

        /* Dikelompokkan per (pengajuan, periode): satu baris realisasi menampung seluruh
           parameter periode itu, persis seperti yang dibuat layar update performance. */
        $kelompok = [];

        foreach ($parameter as $p) {
            foreach ($p['det'] as $d) {
                $kelompok[$p['pengajuan'].'|'.$d['periode']][] = ['param' => $p, 'det' => $d];
            }
        }

        $dibuat = 0;

        foreach ($kelompok as $kunci => $isi) {
            if (isset($sudahAda[$kunci])) {
                continue;
            }

            [$idPengajuan, $periodeIni] = explode('|', $kunci);

            /* Periode di LUAR yang disemai dilewati, bukan diberi angka apa adanya. Rencana
               KPI sungguhan membentang Januari sampai Desember, sedangkan hari ini masih di
               triwulan III — merealisasikan Oktober sampai Desember berarti menaruh capaian
               yang belum terjadi ke dalam data yang dipakai orang menilai orang. */
            $capaianIni = $capaian[$periodeIni] ?? null;

            if ($capaianIni === null) {
                continue;
            }

            $baris = [];
            $skorBerbobot = 0.0;
            $totalBobot = 0.0;

            foreach ($isi as $x) {
                $target = (float) $x['det']['target'];
                $bobot = (float) $x['param']['bobot'];
                $formula = $x['param']['formula'];

                $aktual = match ($formula) {
                    4 => round($target * (2 - $capaianIni), 2),
                    5 => $capaianIni >= 0.85 ? 0.0 : $target,
                    default => round($target * $capaianIni, 2),
                };

                /* Skor yang DITULIS ke kolom milik modul KPI, supaya layarnya sendiri tidak
                   menampilkan angka yang bertentangan dengan yang dihitung PA. Penjepitan
                   50-125 ditirukan di sini karena itulah yang dilakukan mesinnya. */
                $rasio = match ($formula) {
                    5 => $aktual == 0.0 ? 1.0 : 0.0,
                    default => $capaianIni,
                };
                $skor = round(max(50, min(125, $rasio * 100)), 2);

                $skorBerbobot += $skor * $bobot / 100;
                $totalBobot += $bobot;

                $baris[] = [
                    'Id_Pengajuan_Det' => (int) $x['det']['id'],
                    'Kode_Karyawan' => $kodeKaryawan,
                    'Nilai_Actual' => $aktual,
                    'Score' => $skor,
                    'Achievement' => round($skor * $bobot / 100, 2),
                    'File_Berkas' => null,
                    'Flag_Pica' => 'T',
                    'Flag_Capped' => 'T',
                ];
            }

            if ($baris === []) {
                continue;
            }

            $bulan = substr($periodeIni, 5, 2);
            $maks = (string) DB::table('KPI_Update_Performance')
                ->where('No_Transaksi', 'like', 'AKPI-'.$bulan.'-2026-%')->max('No_Transaksi');
            $urut = $maks === '' ? 1 : ((int) substr($maks, -4)) + 1;

            $idUp = (int) DB::table('KPI_Update_Performance')->insertGetId([
                'No_Transaksi' => 'AKPI-'.$bulan.'-2026-'.str_pad((string) $urut, 4, '0', STR_PAD_LEFT),

                /* PENANDA KEPEMILIKAN ada di sini. Tabel ini tidak punya kolom keterangan,
                   dan tanpa penanda `--hapus` tidak bisa membedakan realisasi yang dibuat
                   perintah ini dari realisasi yang diisi orang. */
                'No_Transaksi_Ref' => self::PENANDA,
                'Id_Penjadwalan' => DB::table('KPI_Pengajuan')->where('Id_Pengajuan', $idPengajuan)->value('Id_Penjadwalan'),
                'Id_Pengajuan' => (int) $idPengajuan,
                'Status' => null,
                'Periode' => $periodeIni,
                'Total_Score' => round($skorBerbobot, 2),
                'Total_Score_Achievement' => round($skorBerbobot, 2),
                'Total_Bobot' => round($totalBobot, 2),
                'Status_Pengajuan' => 'disetujui',
                'Id_Performance' => 1,
                'Id_User_Sender' => $idUser,
                'Jam' => date('H:i:s'),
                'Tanggal' => date('Y-m-d H:i:s'),
                'Id_User' => $idUser,
                'Flag_Approval' => 'Y',
                'Flag_Team' => 'Y',
                'Flag_Aktif' => 'Y',
                'Flag_Verifikasi' => 'Y',
                'Flag_Edit' => 'Y',
                'Total_Score_Raw' => round($skorBerbobot, 2),
                'Total_Score_Achievement_Raw' => round($skorBerbobot, 2),
                'Flag_Capped' => 'T',
            ], 'Id_Update_Performance');

            foreach ($baris as $b) {
                $b['Id_Update_Performance'] = $idUp;
                DB::table('KPI_Update_Performance_Detail')->insert($b);
            }

            $dibuat++;
        }

        return $dibuat;
    }

    /**
     * @param  list<string>  $periode
     * @param  array<string, float>  $capaian  periode => rasio pencapaian
     */
    private function semaiSatuKpi(int $idUser, string $kodeKaryawan, int $idPenjadwalan, array $periode, array $capaian): void
    {
        DB::transaction(function () use ($idUser, $kodeKaryawan, $idPenjadwalan, $periode, $capaian) {
            $target = 100.0;

            $maks = (string) DB::table('KPI_Pengajuan')->where('No_Transaksi', 'like', 'PKPI09-26-%')->max('No_Transaksi');
            $urut = $maks === '' ? 1 : ((int) substr($maks, -4)) + 1;

            $idPengajuan = (int) DB::table('KPI_Pengajuan')->insertGetId([
                'No_Transaksi' => 'PKPI09-26-'.str_pad((string) $urut, 4, '0', STR_PAD_LEFT),
                'Id_Penjadwalan' => $idPenjadwalan,
                'Id_User' => $idUser,
                'Tanggal' => date('Y-m-d 00:00:00'),
                'Jam' => '08:00:00',
                'Flag_Approval' => 'Y',
                'Status' => null,
                'Flag_Aktif' => 'Y',
                'Flag_Team' => 'Y',
            ], 'Id_Pengajuan');

            $idDetail = (int) DB::table('KPI_Pengajuan_Detail')->insertGetId([
                'Id_Pengajuan' => $idPengajuan,
                'Nama_Parameter' => 'Pencapaian Output & Efisiensi Operasional',
                'Id_Bsc_Kategori' => 17,
                'Id_Master_Formula' => 3,   // [actualKpiEvo]/[targetKpiEvo]
                'Id_Master_Satuan' => 11,
                'Id_Master_Target' => 3,
                'Bobot' => 100.0,
                'Deskripsi' => self::PENANDA_KPI,
                'Kode_Karyawan' => $kodeKaryawan,
            ], 'Id_Pengajuan_Detail');

            foreach ($periode as $p) {
                /* Skornya dijepit 50-125 di sini, persis seperti yang dilakukan mesin KPI.
                   Tanpa penjepitan, kolom milik modul KPI akan menampilkan angka yang
                   berselisih dengan yang dibaca PA untuk baris yang sama. */
                $skor = round(max(50, min(125, ($capaian[$p] ?? 1.0) * 100)), 2);
                $actual = round($target * ($capaian[$p] ?? 1.0), 2);

                $idDet = (int) DB::table('KPI_Pengajuan_Det')->insertGetId([
                    'Id_Pengajuan_Detail' => $idDetail,
                    'Nama_Target' => 'Target '.$p,
                    'Nilai_Target' => $target,
                    'Periode' => $p,
                    'Id_Target_Breakdown' => null,
                ], 'Id_Pengajuan_Det');

                $bulan = substr($p, 5, 2);
                $maksUp = (string) DB::table('KPI_Update_Performance')
                    ->where('No_Transaksi', 'like', 'AKPI-'.$bulan.'-2026-%')->max('No_Transaksi');
                $urutUp = $maksUp === '' ? 1 : ((int) substr($maksUp, -4)) + 1;

                $idUp = (int) DB::table('KPI_Update_Performance')->insertGetId([
                    'No_Transaksi' => 'AKPI-'.$bulan.'-2026-'.str_pad((string) $urutUp, 4, '0', STR_PAD_LEFT),
                    'No_Transaksi_Ref' => self::PENANDA,
                    'Id_Penjadwalan' => $idPenjadwalan,
                    'Id_Pengajuan' => $idPengajuan,
                    'Status' => null,
                    'Periode' => $p,
                    'Total_Score' => $skor,
                    'Total_Score_Achievement' => $skor,
                    'Total_Bobot' => 100.0,
                    'Status_Pengajuan' => 'disetujui',
                    'Id_Performance' => 1,
                    'Id_User_Sender' => $idUser,
                    'Jam' => date('H:i:s'),
                    'Tanggal' => date('Y-m-d H:i:s'),
                    'Id_User' => $idUser,
                    'Flag_Approval' => 'Y',
                    'Flag_Team' => 'Y',
                    'Flag_Aktif' => 'Y',
                    'Flag_Verifikasi' => 'Y',
                    'Flag_Edit' => 'Y',
                    'Total_Score_Raw' => $skor,
                    'Total_Score_Achievement_Raw' => $skor,
                    'Flag_Capped' => 'T',
                ], 'Id_Update_Performance');

                DB::table('KPI_Update_Performance_Detail')->insert([
                    'Id_Update_Performance' => $idUp,
                    'Id_Pengajuan_Det' => $idDet,
                    'Kode_Karyawan' => $kodeKaryawan,
                    'Nilai_Actual' => $actual,
                    'Score' => $skor,
                    'Achievement' => $skor,
                    'File_Berkas' => null,
                    'Flag_Pica' => 'T',
                    'Flag_Capped' => 'T',
                ]);
            }
        });
    }

    /* ====================================================================== *
     * Kerangka siklus                                                         *
     * ====================================================================== */

    /**
     * @param  array{ikut: array<string, string>, khusus: array<string, string>}  $orang
     * @return array{id:int, kode:string, nama:string, mode:string, hclearn:bool}
     */
    private function bangun(
        PaCycleGate $gate,
        PaCycleSetupService $setup,
        PaCyclePreparationService $penyiapan,
        PaQuestionBindingMasterService $ikatan,
        PaAssignmentBuilderService $penugasan,
        PaHcLearnScheduleService $penjadwalan,
        string $nama,
        int $tahun,
        string $dari,
        string $sampai,
        array $orang,
        bool $hclearn,
        bool $berjalan = false,
    ): array {
        $this->newLine();
        $this->info('== '.$nama.($hclearn ? ' (pintu HC Learn sungguhan)' : ' (pintu lokal)'));

        /* ── JENDELA PENGISIANNYA DITENTUKAN OLEH PERAN SIKLUS, BUKAN OLEH PINTUNYA ──
           |
           | Versi sebelumnya menurunkannya dari `$hclearn`, dan itu kebetulan benar selama
           | satu-satunya siklus berjalan memakai HC Learn. Begitu siklus berjalan memakai
           | pintu lokal, jendelanya jatuh ke rumus "periode selesai + 7 hari" — yaitu masa
           | depan bagi siklus yang periodenya belum berakhir, sehingga siklus yang disebut
           | SEDANG BERJALAN punya jendela yang belum dibuka.
           |
           | Siklus berjalan karena itu mendapat jendela yang MENCAKUP HARI INI: pengisian
           | memang lazim dibuka sebelum periodenya habis, dan pintu HC Learn yang jendelanya
           | sudah lewat ditolak saat dibuka — tepat keadaan yang tidak boleh terjadi di
           | tengah presentasi. Siklus lampau tetap memakai jendela sesudah periodenya, yang
           | memang sudah lewat, dan itulah yang membuatnya terbaca sebagai lampau. */
        $pengisianDari = $berjalan
            ? date('Y-m-d', strtotime('-6 days'))
            : date('Y-m-d', strtotime($sampai.' +7 days'));
        $pengisianSampai = $berjalan
            ? date('Y-m-d', strtotime('+30 days'))
            : date('Y-m-d', strtotime($sampai.' +45 days'));

        $dibuat = $setup->buat([
            'nama_cycle' => $nama,
            'deskripsi' => self::PENANDA.' | Data contoh untuk presentasi, bukan penilaian sungguhan.',
            'tahun' => $tahun,
            'periode_dinilai_dari' => $dari,
            'periode_dinilai_sampai' => $sampai,
            'pengisian_dari' => $pengisianDari,
            'pengisian_sampai' => $pengisianSampai,
        ]);

        $cycleId = (int) $dibuat['cycle_id'];
        $this->line("  siklus #{$cycleId} {$dibuat['kode_cycle']}");

        $this->praKecualiSelainIkut($penyiapan, $cycleId, $orang['ikut']);

        $gate->lupakanMemo();
        $siap = $penyiapan->siapkan($cycleId);
        $this->line(sprintf(
            '  disiapkan: %d diikutkan, %d dikecualikan, %d tertunda',
            $siap['peserta']['diikutkan'] ?? 0,
            $siap['peserta']['dikecualikan'] ?? 0,
            $siap['peserta']['tertunda'] ?? 0,
        ));

        $this->bersihkanTertunda($cycleId);

        $this->bangunPaketSoal($ikatan, $cycleId);

        $bentuk = $penugasan->bentukMassalCepat($cycleId, penuh: true, batas: 500);
        $this->line(sprintf(
            '  penugasan: %d dibuat untuk %d peserta',
            $bentuk['dibuat'] ?? 0,
            $bentuk['peserta_tersentuh'] ?? 0,
        ));

        if ($hclearn) {
            $this->bukaLewatHcLearn($gate, $penyiapan, $penjadwalan, $cycleId);
        } else {
            $this->pindahStatus($gate, $cycleId, 'OPEN');
            $n = $this->cetakPintuLokal($cycleId);
            $this->line("  pintu lokal dicetak: {$n}");
        }

        return [
            'id' => $cycleId,
            'kode' => (string) $dibuat['kode_cycle'],
            'nama' => $nama,
            'mode' => $berjalan ? 'BERJALAN' : 'LAMPAU',
            'hclearn' => $hclearn,
        ];
    }

    /**
     * Membangun paket soal siklus per level, dari KOMPETENSI WAJIB level itu.
     *
     * ── KENAPA TIDAK DISALIN DARI IKATAN MASTER ───────────────────────────────
     *
     * Karena ikatan master tidak bisa dilengkapi, dan itu terukur — bukan dugaan. Aturan
     * pengikatan menuntut bijeksi: jumlah bagian ujian HC Learn harus SAMA PERSIS dengan
     * jumlah kompetensi wajib level itu, satu bagian satu kompetensi (`PaBijeksiLabel`).
     * Diperiksa pada 14 September 2026 terhadap katalog HC Learn sungguhan: 27 paket, bagian
     * terbanyak 14, sementara level 2-13 menuntut 17 atau 21 kompetensi. Hanya level 1
     * (Pramukarya, 3 kompetensi) yang punya pasangan. Menyalin dari ikatan master karena itu
     * menghasilkan NOL sel bagi setiap level yang dipakai siklus ini.
     *
     * ── YANG DIBANGUN DI SINI TIDAK MENGARANG SATU PUN KEPUTUSAN BISNIS ───────
     *
     * Butirnya adalah kompetensi wajib level itu apa adanya, satu butir satu kompetensi —
     * bijeksi yang sama yang dituntut jalur sungguhan, hanya saja sumbernya matriks
     * kompetensi kita sendiri alih-alih katalog pihak ketiga. Mana yang berskor dan mana yang
     * dijawab naratif juga ditentukan datanya (`Flag_Berskor`), bukan perintah ini: level 1-5
     * kompetensi wajibnya seluruhnya berskor sehingga paketnya BEBAS ESAI, sedangkan level 6
     * ke atas membawa empat butir kualitatif. Keadaan "penilaian tanpa pertanyaan naratif"
     * karena itu tetap bisa ditunjukkan, dan ia benar karena datanya memang begitu.
     *
     * Paketnya ditandai `Sumber_Kontrak = 'PRESENTASI'` supaya tidak pernah tertukar dengan
     * paket yang benar-benar datang dari HC Learn.
     */
    private function bangunPaketSoal(PaQuestionBindingMasterService $ikatan, int $cycleId): void
    {
        $level = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->where('Flag_Aktif', 'Y')
            ->whereNotNull('ID_Level_Saat_Cycle')
            ->distinct()
            ->orderBy('ID_Level_Saat_Cycle')
            ->pluck('ID_Level_Saat_Cycle');

        $paket = 0;
        $butir = 0;
        $beresai = 0;
        $tanpaKompetensi = [];

        foreach ($level as $l) {
            $l = (int) $l;
            $wajib = $ikatan->kompetensiWajibLevel($l);

            if ($wajib === []) {
                $tanpaKompetensi[] = $l;

                continue;
            }

            $kompetensi = DB::table('N_PA_Competency as c')
                ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'c.PA_Competency_Type_Id')
                ->whereIn('c.Id_PA_Competency', $wajib)
                ->orderBy('t.Kode_Type')
                ->orderBy('c.Kode_Competency')
                ->get([
                    'c.Id_PA_Competency as id',
                    'c.Nama_Competency as nama',
                    'c.Flag_Berskor as berskor',
                ]);

            $namaPaket = 'Asesmen 360 Presentasi — Level '.$l;

            $paketId = (int) DB::table('N_PA_Cycle_Question_Package')->insertGetId([
                'PA_Cycle_Id' => $cycleId,
                'ID_Level' => $l,
                'PA_Rater_Category_Id' => null,
                'Id_Master_Ujian' => 'PRS-L'.$l,
                'Nama_Ujian_Sumber' => $namaPaket,
                'Id_Master_Paket_Ujian' => 'PRS-L'.$l.'-PKT',
                'Nama_Paket_Ujian_Sumber' => $namaPaket,
                'Sumber_Kontrak' => 'PRESENTASI',
                'Catatan' => self::PENANDA.' | Paket dibangun dari kompetensi wajib level, bukan dari HC Learn.',
                'Flag_Aktif' => 'Y',
            ], 'Id_PA_Cycle_Question_Package');

            $baris = [];

            foreach ($kompetensi as $urutan => $k) {
                $teks = strtoupper((string) $k->berskor) === 'T';
                $kode = sprintf('K%02d', $urutan + 1);

                $baris[] = [
                    'PA_Cycle_Question_Package_Id' => $paketId,

                    /* `Id_Jenis_Indikator` + `Id_Jenis_Soal` adalah identitas bagian yang
                       dipakai `PaBagianKatalog::kunci()`. Diberi nilai yang unik per paket
                       supaya dua paket tidak pernah terbaca sebagai bagian yang kembar. */
                    'Id_Jenis_Indikator' => 'PRS-L'.$l,
                    'Id_Jenis_Soal' => $kode,

                    /* KODE YANG DIPAKAI KONTRAK IKUT DITULIS.
                       Pemeta payload mencocokkan `Detail[].Kode_Soal` dengan kolom INI, bukan
                       dengan `Id_Jenis_Soal`. Selama kolom ini kosong, seluruh butir kiriman
                       mendarat tak terpetakan — penugasan tak pernah SELESAI, dan tak satu pun
                       galat muncul di mana pun. */
                    'Kode_Soal_Sumber' => $kode,
                    'Nama_Bagian_Sumber' => (string) $k->nama,
                    'Nama_Alat_Tes' => $namaPaket,
                    'Jenis_Soal' => $teks ? 'teks' : 'skala',
                    'Skor_Maksimal' => $teks ? null : 100,
                    'Bobot_Penilaian' => null,
                    'PA_Competency_Id' => (int) $k->id,
                    'Urutan_Tampil' => $urutan + 1,
                    'Flag_Aktif' => 'Y',
                ];

                if ($teks) {
                    $beresai++;
                }
            }

            foreach (array_chunk($baris, 100) as $potongan) {
                DB::table('N_PA_Cycle_Question_Package_Item')->insert($potongan);
            }

            $paket++;
            $butir += count($baris);
        }

        if ($tanpaKompetensi !== []) {
            /* DILAPORKAN, bukan didiamkan. Level tanpa kompetensi wajib menghasilkan peserta
               yang punya penugasan tetapi tidak punya lembar, dan di layar itu terbaca sebagai
               penilai yang lalai — tuduhan kepada orang atas cacat di data master. */
            $this->warn('  level tanpa kompetensi wajib, pesertanya tidak akan punya lembar: '
                .implode(', ', $tanpaKompetensi));
        }

        if ($paket === 0) {
            throw new RuntimeException(
                'Tidak ada satu paket soal pun yang bisa dibangun: tak satu level peserta pun '
                .'punya kompetensi wajib. Tanpa paket tidak ada lembar yang bisa diisi, dan '
                .'seluruh peserta akan berakhir TERTAHAN dengan sebab yang tidak menunjuk ke '
                .'sini. Periksa N_PA_Competency_Requirement untuk level yang dipakai siklus ini.'
            );
        }

        $this->line(sprintf(
            '  paket soal dibangun dari kompetensi wajib: %d paket, %d butir (%d naratif)',
            $paket, $butir, $beresai,
        ));
    }

    /**
     * @param  array<string, string>  $ikut
     */
    private function praKecualiSelainIkut(PaCyclePreparationService $penyiapan, int $cycleId, array $ikut): void
    {
        $dikecualikan = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->get(['Kode_Perusahaan', 'Kode_Karyawan'])
            ->reject(fn ($k) => isset($ikut[$k->Kode_Perusahaan.'|'.$k->Kode_Karyawan]))
            ->map(fn ($k) => ['kode_perusahaan' => $k->Kode_Perusahaan, 'kode_karyawan' => $k->Kode_Karyawan])
            ->values()
            ->all();

        if ($dikecualikan === []) {
            return;
        }

        $penyiapan->tambahPraKecuali(
            $cycleId,
            $dikecualikan,
            'Data contoh presentasi: populasi dibatasi supaya siklusnya bisa diisi utuh.',
        );
    }

    private function bersihkanTertunda(int $cycleId): void
    {
        $n = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'TERTUNDA')
            ->update([
                'Status_Peserta' => 'DIKECUALIKAN',
                'Alasan_Dikecualikan' => 'Level tanpa aturan penilai (data contoh presentasi).',
            ]);

        if ($n > 0) {
            $this->line("  {$n} peserta tertunda dikecualikan supaya siklus bisa dikunci");
        }
    }

    /**
     * Mengunci, menjadwalkan ke HC Learn, menarik pintunya, lalu membuka pengisian.
     */
    private function bukaLewatHcLearn(
        PaCycleGate $gate,
        PaCyclePreparationService $penyiapan,
        PaHcLearnScheduleService $penjadwalan,
        int $cycleId,
    ): void {
        $penyiapan->kunci($cycleId);
        $gate->lupakanMemo();
        $this->line('  siklus dikunci');

        $kirim = $penjadwalan->jadwalkanSiklus($cycleId);
        $this->line(sprintf(
            '  dijadwalkan ke HC Learn: %d diminta, %d dititipkan, %d dilewati, %d gagal',
            $kirim['diminta'] ?? 0,
            $kirim['dititipkan'] ?? 0,
            $kirim['dilewati'] ?? 0,
            $kirim['gagal'] ?? 0,
        ));

        foreach (array_slice((array) ($kirim['contoh_galat'] ?? []), 0, 3) as $g) {
            $this->warn('    '.$g);
        }

        /* Penjadwalan mereka ASINKRON: yang kembali hanya process_id, tokennya menyusul.
           Ditarik berulang sampai tidak ada lagi yang ANTRE — tanpa ini pintunya tercatat
           ada tetapi tanpa `Api_Ref`, dan tombol "Mulai Menilai" mati tanpa satu pun galat. */
        for ($putaran = 1; $putaran <= 15; $putaran++) {
            $tarik = $penjadwalan->tarikStatus($cycleId);

            $this->line(sprintf(
                '  tarik #%d: %d proses, %d pintu terisi, %d masih antre, %d gagal',
                $putaran,
                $tarik['proses'] ?? 0,
                $tarik['terisi'] ?? 0,
                $tarik['masih_antre'] ?? 0,
                $tarik['gagal'] ?? 0,
            ));

            if ((int) ($tarik['masih_antre'] ?? 0) === 0) {
                break;
            }

            sleep(5);
        }

        $ringkas = $penjadwalan->ringkasan($cycleId);
        $this->line(sprintf(
            '  pintu: %d terjadwal dari %d, %d antre, %d gagal, %d belum',
            $ringkas['terjadwal'] ?? 0,
            $ringkas['total'] ?? 0,
            $ringkas['antre'] ?? 0,
            $ringkas['gagal'] ?? 0,
            $ringkas['belum'] ?? 0,
        ));

        $penyiapan->bukaPengisian($cycleId);
        $gate->lupakanMemo();
        $this->line('  pengisian dibuka');
    }

    /**
     * Pintu simulasi lokal untuk siklus yang tidak akan dibuka siapa pun.
     *
     * Awalan `SIM-PA-` dipakai supaya lembar simulasi internal mengenalinya; `Api_Ref`
     * dibiarkan kosong karena tidak ada token HC Learn yang mewakilinya, dan mengarang
     * 32 heksadesimal di sana berarti membuat pintu yang TAMPAK nyata padahal menunjuk ke
     * tempat yang tidak ada.
     */
    private function cetakPintuLokal(int $cycleId): int
    {
        $paket = $this->petaPaket($cycleId);

        $tugas = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->leftJoin('N_PA_Assignment_Integration as i', function ($j) {
                $j->on('i.PA_Assignment_Id', '=', 'a.Id_PA_Assignment')->where('i.Flag_Aktif', '=', 'Y');
            })
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('a.Flag_Aktif', 'Y')
            ->where('a.Status_Penugasan', '<>', 'DIBATALKAN')
            ->whereNull('i.Id_PA_Assignment_Integration')
            ->get([
                'a.Id_PA_Assignment as id',
                'a.PA_Rater_Category_Id as kategori',
                'p.ID_Level_Saat_Cycle as level',
            ]);

        $baris = [];

        foreach ($tugas as $t) {
            $paketId = $paket[(int) $t->level][(int) $t->kategori]
                ?? $paket[(int) $t->level][0]
                ?? null;

            if ($paketId === null) {
                continue;
            }

            $baris[] = [
                'PA_Assignment_Id' => (int) $t->id,
                'PA_Cycle_Question_Package_Id' => $paketId,
                'Id_Ujian_Token' => PaSimulasiPengisianService::AWALAN_SIMULASI.'PRS'.str_pad((string) $t->id, 8, '0', STR_PAD_LEFT),
                'Flag_Aktif' => 'Y',
            ];
        }

        foreach (array_chunk($baris, 200) as $potongan) {
            DB::table('N_PA_Assignment_Integration')->insert($potongan);
        }

        return count($baris);
    }

    /**
     * Paket soal siklus per level dan kategori penilai.
     *
     * @return array<int, array<int, int>>
     */
    private function petaPaket(int $cycleId): array
    {
        $peta = [];

        foreach (DB::table('N_PA_Cycle_Question_Package')
            ->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')
            ->get(['Id_PA_Cycle_Question_Package as id', 'ID_Level as level', 'PA_Rater_Category_Id as kategori']) as $p) {
            $peta[(int) $p->level][(int) ($p->kategori ?? 0)] = (int) $p->id;
        }

        return $peta;
    }

    /* ====================================================================== *
     * Pengisian                                                               *
     * ====================================================================== */

    private function isiSeluruhnya(PaPayloadMapperService $pemeta, PaRaterSubmitService $pengiriman, int $cycleId): void
    {
        $terisi = $this->isiLembar($pemeta, $cycleId, fn () => true);
        $this->line("  pengisian: {$terisi} lembar");

        $mengirim = $this->kirimYangLengkap($pengiriman, $cycleId);
        $this->line("  pengiriman: {$mengirim} penilai");
    }

    /**
     * Pengisian TIMPANG untuk siklus berjalan, dengan tujuh orang yang keadaannya ditentukan.
     *
     * Urutan langkahnya tidak bebas dan tidak boleh ditukar:
     *
     *   1. penugasan yang hendak dibatalkan dibatalkan LEBIH DULU, supaya lembarnya tidak
     *      sempat terisi dan penilainya tidak sempat terbebani olehnya;
     *   2. lembar yang sengaja disisakan ditetapkan SEBELUM pengisian, karena satu-satunya
     *      cara membiarkan sebuah lembar kosong adalah tidak mengisinya;
     *   3. pengiriman dijalankan SEBELUM pelepasan paksa, supaya penilai yang memang lalai
     *      tetap terbaca sebagai lalai dan bukan sebagai "dilepas admin".
     *
     * @param  array{ikut: array<string, string>, khusus: array<string, string>, tanpa_kpi: list<string>}  $orang
     */
    private function isiTimpang(
        PaPayloadMapperService $pemeta,
        PaRaterSubmitService $pengiriman,
        PaAssignmentBuilderService $penugasan,
        int $cycleId,
        array $orang,
    ): void {
        $pid = [];

        foreach ($orang['khusus'] as $nama => $kunci) {
            $pid[$nama] = $this->pesertaDariKunci($cycleId, $kunci);
        }

        $pidTanpaKpi = array_values(array_filter(array_map(
            fn (string $kunci) => $this->pesertaDariKunci($cycleId, $kunci),
            $orang['tanpa_kpi'],
        )));

        /* ── 1. KERANJANG "BUTUH PENILAI" ─────────────────────────────────────────────
           Seluruh penugasan beberapa peserta dibatalkan lewat `batalkan()`, jalur yang sama
           dengan tombol admin. Akibatnya `sisaPenugasan()` menjadi nol — peserta itu SIAP
           dihitung menurut mesin — tetapi tak satu pun kategori penilai memenuhi ambang, dan
           hasilnya TERTAHAN dengan sebab yang menunjuk penilainya. Itulah keadaan yang
           dimaksud keranjang tersebut, dan ia dilahirkan, bukan dituliskan. */
        $pidTanpaPenilai = $this->batalkanSeluruhPenugasan(
            $penugasan, $cycleId, $orang, self::JUMLAH_TANPA_PENILAI,
        );

        /* ── 2. LEMBAR YANG SENGAJA DISISAKAN ────────────────────────────────────────── */
        $disisakan = [];

        if ($pid[self::NAMA_BELUM_TERSENTUH] ?? null) {
            $semua = $this->lembarPeserta($cycleId, (int) $pid[self::NAMA_BELUM_TERSENTUH])
                ->pluck('id')->map(fn ($x) => (int) $x)->all();
            $disisakan = [...$disisakan, ...$semua];
            $this->line(sprintf(
                '  %s: seluruh %d lembarnya dibiarkan kosong',
                self::NAMA_BELUM_TERSENTUH, count($semua),
            ));
        }

        foreach (self::NAMA_SEPARUH as $nama) {
            if (($pid[$nama] ?? null) === null) {
                continue;
            }

            $semua = $this->lembarPeserta($cycleId, (int) $pid[$nama]);

            /* Dibulatkan KE ATAS, jadi jumlah lembar ganjil menyisakan yang lebih banyak
               kosong. Pilihan itu disengaja: "separuh" yang dibulatkan ke bawah pada tiga
               lembar menghasilkan dua terisi dari tiga — 67%, yang tidak terbaca sebagai
               separuh oleh siapa pun yang melihat layarnya. */
            $sisa = (int) ceil($semua->count() / 2);
            $dipilih = $semua->slice($semua->count() - $sisa)->pluck('id')
                ->map(fn ($x) => (int) $x)->all();

            $disisakan = [...$disisakan, ...$dipilih];
            $this->line(sprintf(
                '  %s: %d dari %d lembar dibiarkan kosong (separuh)',
                $nama, count($dipilih), $semua->count(),
            ));
        }

        foreach (self::NAMA_SISA_SATU as $nama) {
            if (($pid[$nama] ?? null) === null) {
                continue;
            }

            $dipilih = $this->pilihLembarDisisakan($cycleId, (int) $pid[$nama], self::SISA_LEMBAR);
            $disisakan = [...$disisakan, ...$dipilih];
            $this->line(sprintf(
                '  %s: lembar yang disisakan untuk diisi langsung: %s',
                $nama, implode(', ', $dipilih),
            ));
        }

        $disisakan = array_values(array_unique($disisakan));

        /* ── 3. KERAJINAN DITENTUKAN PER PENILAI, BUKAN PER PESERTA ───────────────────
           |
           | Versi pertama mengundi berapa bagian penilai seorang peserta yang mengisi. Hasilnya
           | terlihat benar di tabel tetapi keliru di layar: seorang penilai mengirim SELURUH
           | tugasnya sekaligus, jadi satu lembar kosong untuk peserta mana pun menahan seluruh
           | lembar lainnya. Terukur pada 50 peserta: 27 peserta lembarnya terisi penuh, tetapi
           | hanya 5 yang hasilnya lahir — sisanya tertahan oleh penilai yang lembarnya utuh
           | untuk dia tetapi kosong untuk orang lain.
           |
           | Yang terjadi pada orang sungguhan bukan itu. Seseorang mengerjakan penilaiannya atau
           | tidak; ia tidak mengerjakan separuh lalu berhenti di tengah daftar. Karena itu yang
           | diundi di sini adalah PENILAINYA — sekitar tujuh dari sepuluh menuntaskan seluruh
           | tugasnya — dan sebaran "berapa penilai yang sudah mengisi" lahir dengan sendirinya
           | dari persilangan itu, sama seperti di lapangan. */
        /* Ambangnya bisa disetel karena yang dibacanya bukan satu peserta melainkan
           PERSILANGAN: peserta ber-10 penilai baru punya hasil bila kesepuluhnya menuntaskan,
           jadi 70% kerajinan hanya melahirkan 0,7^10 - sekitar 3 dari 50. Bawaannya 92%, yang
           menggambarkan siklus menjelang akhir jendela pengisian: hampir semua sudah mengisi,
           sebagian kecil belum, dan hasilnya separuh-separuh. */
        $ambangRajin = max(1, min(100, (int) $this->option('rajin')));
        $rajin = [];

        foreach (DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('a.Flag_Aktif', 'Y')
            ->distinct()->pluck('a.Kode_Karyawan_Penilai') as $kode) {
            $kode = (string) $kode;

            mt_srand(self::BENIH + crc32($kode));
            $rajin[$kode] = mt_rand(1, 100) <= $ambangRajin;
            mt_srand();
        }

        /* Penilai orang-orang bernama WAJIB rajin: keadaan merekalah yang diceritakan, dan
           menyerahkannya pada undian berarti presentasi yang isinya berganti tiap disemai.
           `NAMA_BELUM_TERSENTUH` ikut di sini dan itu bukan kekeliruan — penilainya memang
           rajin, tetapi SELURUH lembarnya ada di daftar yang disisakan, jadi ia tetap nol. */
        $wajibTuntas = [...array_values(array_filter($pid)), ...$pidTanpaKpi];

        foreach ($wajibTuntas as $p) {
            foreach (DB::table('N_PA_Assignment')->where('PA_Participant_Id', $p)
                ->where('Flag_Aktif', 'Y')->pluck('Kode_Karyawan_Penilai') as $kode) {
                $rajin[(string) $kode] = true;
            }
        }

        $terisi = $this->isiLembar(
            $pemeta,
            $cycleId,
            function (object $t) use ($rajin, $disisakan) {
                if (in_array((int) $t->id, $disisakan, true)) {
                    return false;
                }

                return $rajin[(string) $t->penilai] ?? false;
            },
        );

        $this->line(sprintf(
            '  pengisian: %d lembar; %d dari %d penilai menuntaskan tugasnya',
            $terisi,
            count(array_filter($rajin)),
            count($rajin),
        ));

        /* ── 4. PENILAI YANG LEMBARNYA TERISI TETAPI TIDAK MENEKAN KIRIM ──────────────
           |
           | Keadaan ini nyata dan tindak lanjutnya berbeda dari "belum mengisi": yang satu
           | ditagih mengerjakan, yang lain ditagih satu klik — atau dilepas paksa. Tanpa
           | seorang pun berada di sana, layar tidak pernah menunjukkan bedanya.
           |
           | Yang dipilih SENGAJA bukan penilai orang-orang bernama maupun peserta tanpa KPI:
           | mereka semua dilepas paksa sesaat lagi, dan penilai yang dilepas berhenti menjadi
           | contoh "belum menekan kirim". Keranjangnya akan kosong tanpa satu pun galat. */
        $terlindung = $this->penilaiDariPeserta($wajibTuntas);
        $ambangTakKirim = max(0, min(100, (int) $this->option('tak-mengirim')));
        $takMengirim = [];

        foreach (array_keys($rajin) as $kode) {
            if (isset($terlindung[$kode])) {
                continue;
            }

            mt_srand(self::BENIH + 7 + crc32($kode));
            $undi = mt_rand(1, 100);
            mt_srand();

            if ($undi <= $ambangTakKirim) {
                $takMengirim[$kode] = true;
            }
        }

        $mengirim = $this->kirimYangLengkap(
            $pengiriman,
            $cycleId,
            fn (string $kode) => ! isset($takMengirim[$kode]),
        );

        $this->line(sprintf(
            '  pengiriman: %d penilai mengirim; %d menahan kirimannya (lembar terisi, belum dikirim)',
            $mengirim, count($takMengirim),
        ));

        /* ── 5. PELEPASAN PAKSA BAGI YANG HARUS BENAR-BENAR TUNTAS ────────────────────
           |
           | Lembarnya memang sudah terisi, tetapi penilaiannya baru dihitung sesudah
           | penilainya mengirim — dan penilai belum boleh mengirim selama ia masih punya
           | lembar kosong untuk peserta LAIN. Pada siklus yang sengaja timpang itu berarti
           | orang yang seharusnya tuntas justru menggantung karena kelalaian orang ketiga.
           | Dilepas lewat pintu darurat admin yang memang disediakan untuk keadaan ini.
           |
           | Pelepasan paksa hanya menyentuh yang berstatus TERKUMPUL, jadi lembar yang
           | sengaja dibiarkan kosong tetap BELUM_MULAI — persis yang ingin diperagakan.
           | Tanpa langkah ini, orang yang disisakan SATU lembar akan tampak menyisakan enam:
           | lima lembarnya sudah terisi tetapi berhenti di TERKUMPUL, dan `sisaPenugasan()`
           | menghitung TERKUMPUL sebagai belum selesai. */
        foreach ($pid as $nama => $p) {
            if ($p === null || $nama === self::NAMA_BELUM_TERSENTUH) {
                continue;
            }

            $dilepas = $this->lepaskanPenilai($pengiriman, $cycleId, (int) $p, $nama);
            $this->line("  {$nama}: {$dilepas} penilai dilepas supaya keadaannya sesuai skenario");
        }

        foreach ($pidTanpaKpi as $p) {
            $this->lepaskanPenilai($pengiriman, $cycleId, $p, 'peserta tanpa akun KPI');
        }

        $this->line(sprintf(
            '  keranjang disiapkan: %d peserta tanpa akun KPI, %d peserta yang penugasannya dibatalkan',
            count($pidTanpaKpi), count($pidTanpaPenilai),
        ));
    }

    /**
     * Membatalkan SELURUH penugasan beberapa peserta biasa, lewat jalur pembatalan yang sah.
     *
     * Yang dipilih sengaja bukan orang bernama dan bukan peserta tanpa akun KPI: keduanya
     * sudah mengisi keranjang lain, dan satu orang yang menempati dua keranjang sekaligus
     * membuat angka kartu dan isi daftarnya berselisih.
     *
     * @param  array{ikut: array<string, string>, khusus: array<string, string>, tanpa_kpi: list<string>}  $orang
     * @return list<int> participant id yang penugasannya dibatalkan
     */
    private function batalkanSeluruhPenugasan(
        PaAssignmentBuilderService $penugasan,
        int $cycleId,
        array $orang,
        int $berapa,
    ): array {
        if ($berapa < 1) {
            return [];
        }

        $dikecualikan = [];

        foreach ([...array_values($orang['khusus']), ...$orang['tanpa_kpi']] as $kunci) {
            $p = $this->pesertaDariKunci($cycleId, $kunci);

            if ($p !== null) {
                $dikecualikan[$p] = true;
            }
        }

        $kandidat = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Participant')
            ->pluck('Id_PA_Participant')
            ->map(fn ($x) => (int) $x)
            ->reject(fn (int $p) => isset($dikecualikan[$p]))
            ->values();

        if ($kandidat->isEmpty()) {
            return [];
        }

        /* Diambil MERATA sepanjang daftar, bukan dari pangkalnya. Tiga peserta berurutan di
           awal daftar cenderung satu divisi, dan keranjang yang isinya satu divisi saja
           terbaca sebagai masalah divisi itu alih-alih sebagai contoh keadaan. */
        $langkah = max(1, (int) floor($kandidat->count() / $berapa));
        $dipilih = [];

        for ($i = 0; $i < $berapa; $i++) {
            $posisi = min($kandidat->count() - 1, $i * $langkah);
            $p = (int) $kandidat[$posisi];

            if (! in_array($p, $dipilih, true)) {
                $dipilih[] = $p;
            }
        }

        $pelaku = (int) (Auth::id() ?? 0);
        $dibatalkan = 0;

        foreach ($dipilih as $p) {
            foreach (DB::table('N_PA_Assignment')
                ->where('PA_Participant_Id', $p)
                ->where('Flag_Aktif', 'Y')
                ->where('Status_Penugasan', '<>', 'DIBATALKAN')
                ->pluck('Id_PA_Assignment') as $id) {
                try {
                    $penugasan->batalkan(
                        (int) $id,
                        'Data contoh presentasi: penilai peserta ini dibatalkan supaya keranjang '
                        .'"Butuh Penilai" pada Antrean Tindak Lanjut ada isinya.',
                        $pelaku > 0 ? $pelaku : null,
                    );
                    $dibatalkan++;
                } catch (Throwable $e) {
                    $this->warn('  penugasan '.$id.' gagal dibatalkan: '.$e->getMessage());
                }
            }
        }

        $this->line(sprintf(
            '  %d penugasan dibatalkan pada %d peserta (keranjang "Butuh Penilai")',
            $dibatalkan, count($dipilih),
        ));

        return $dipilih;
    }

    /**
     * Kode penilai yang menangani peserta mana pun dalam daftar.
     *
     * @param  list<int>  $participantId
     * @return array<string, true>
     */
    private function penilaiDariPeserta(array $participantId): array
    {
        if ($participantId === []) {
            return [];
        }

        return DB::table('N_PA_Assignment')
            ->whereIn('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->distinct()
            ->pluck('Kode_Karyawan_Penilai')
            ->mapWithKeys(fn ($x) => [(string) $x => true])
            ->all();
    }

    private function pesertaDariKunci(int $cycleId, ?string $kunci): ?int
    {
        if ($kunci === null) {
            return null;
        }

        [$kp, $kk] = explode('|', $kunci);

        $pid = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Kode_Perusahaan', $kp)
            ->where('Kode_Karyawan', $kk)
            ->where('Flag_Aktif', 'Y')
            ->value('Id_PA_Participant');

        return $pid === null ? null : (int) $pid;
    }

    /**
     * Lembar seorang peserta yang benar-benar bisa diisi — yaitu yang sudah punya pintunya.
     *
     * Penugasan tanpa baris integrasi tidak punya token, jadi tidak ada yang bisa
     * mengisinya; memasukkannya ke daftar "yang disisakan" berarti menyisakan sesuatu yang
     * memang tidak akan pernah terisi, dan angka di layar berhenti bisa dijelaskan.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function lembarPeserta(int $cycleId, int $participantId)
    {
        return DB::table('N_PA_Assignment as a')
            ->join('N_PA_Assignment_Integration as i', function ($j) {
                $j->on('i.PA_Assignment_Id', '=', 'a.Id_PA_Assignment')->where('i.Flag_Aktif', '=', 'Y');
            })
            ->where('a.PA_Participant_Id', $participantId)
            ->where('a.Flag_Aktif', 'Y')
            ->where('a.Status_Penugasan', '<>', 'DIBATALKAN')
            ->orderBy('a.Id_PA_Assignment')
            ->get([
                'a.Id_PA_Assignment as id',
                'a.Kode_Karyawan_Penilai as penilai',
                'i.PA_Cycle_Question_Package_Id as paket',
            ]);
    }

    /**
     * Memilih lembar yang disisakan: DIRI SENDIRI lebih dulu, lalu penilai lain — dan
     * semuanya WAJIB berada pada paket tanpa butir esai.
     *
     * Kenapa esainya diperiksa: lembar yang disisakan itulah yang akan dibuka di depan
     * orang. Lembar ber-esai menuntut pengisinya mengarang dua paragraf di tempat, dan itu
     * bukan yang hendak ditunjukkan ketika yang ingin dilihat adalah perhitungannya.
     *
     * @return list<int>
     */
    private function pilihLembarDisisakan(int $cycleId, int $participantId, int $berapa): array
    {
        $peserta = DB::table('N_PA_Participant')->where('Id_PA_Participant', $participantId)
            ->first(['Kode_Karyawan']);

        $lembar = $this->lembarPeserta($cycleId, $participantId);

        $beresai = $this->paketBeresai($cycleId);

        $bebasEsai = $lembar->reject(fn ($l) => in_array((int) $l->paket, $beresai, true))->values();

        if ($bebasEsai->count() < $berapa) {
            throw new RuntimeException(sprintf(
                'Peserta %s hanya punya %d lembar pada paket tanpa esai, sedangkan %d harus '
                .'disisakan tanpa esai. Pilih orang lain untuk peran "sisa satu", atau '
                .'longgarkan syarat bebas esainya — memaksakannya akan menyisakan lembar '
                .'ber-esai dan pengisian terakhirnya berhenti menjadi peragaan yang singkat.',
                (string) ($peserta->Kode_Karyawan ?? $participantId),
                $bebasEsai->count(),
                $berapa,
            ));
        }

        /* DIRI SENDIRI didahulukan kalau ada: pengisian terakhir oleh orangnya sendiri
           adalah cerita yang paling mudah diikuti — "sekarang saya menilai diri saya, dan
           angkanya keluar". */
        $diri = $bebasEsai->firstWhere('penilai', $peserta->Kode_Karyawan ?? null);
        $pilihan = [];

        if ($diri !== null) {
            $pilihan[] = (int) $diri->id;
        }

        foreach ($bebasEsai as $l) {
            if (count($pilihan) >= $berapa) {
                break;
            }

            if (! in_array((int) $l->id, $pilihan, true)) {
                $pilihan[] = (int) $l->id;
            }
        }

        return $pilihan;
    }

    /**
     * Paket siklus yang memuat butir kompetensi TAK BERSKOR, yaitu yang dijawab naratif.
     *
     * @return list<int>
     */
    private function paketBeresai(int $cycleId): array
    {
        return DB::table('N_PA_Cycle_Question_Package_Item as it')
            ->join('N_PA_Cycle_Question_Package as k', 'k.Id_PA_Cycle_Question_Package', '=', 'it.PA_Cycle_Question_Package_Id')
            ->join('N_PA_Competency as c', 'c.Id_PA_Competency', '=', 'it.PA_Competency_Id')
            ->where('k.PA_Cycle_Id', $cycleId)
            ->where('it.Flag_Aktif', 'Y')
            ->where('c.Flag_Berskor', 'T')
            ->distinct()
            ->pluck('it.PA_Cycle_Question_Package_Id')
            ->map(fn ($x) => (int) $x)
            ->all();
    }

    /**
     * Mengisi lembar yang lolos `$boleh`, satu per satu, lewat pemeta payload.
     *
     * @param  callable(object): bool  $boleh
     */
    private function isiLembar(PaPayloadMapperService $pemeta, int $cycleId, callable $boleh): int
    {
        $lembar = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->join('N_PA_Assignment_Integration as i', function ($j) {
                $j->on('i.PA_Assignment_Id', '=', 'a.Id_PA_Assignment')->where('i.Flag_Aktif', '=', 'Y');
            })
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('a.Flag_Aktif', 'Y')
            ->whereNotIn('a.Status_Penugasan', ['TERKUMPUL', 'SELESAI', 'DIBATALKAN'])
            ->orderBy('a.PA_Participant_Id')
            ->orderBy('a.Id_PA_Assignment')
            ->get([
                'a.Id_PA_Assignment as id',
                'a.PA_Participant_Id as participant_id',
                'a.Kode_Karyawan_Penilai as penilai',
                'a.Nama_Penilai_Saat_Cycle as nama_penilai',
                'i.Id_PA_Assignment_Integration as intg',
                'i.Api_Ref as ref',
                'i.Id_Ujian_Token as token',
                'i.PA_Cycle_Question_Package_Id as paket',
                'i.Waktu_Mulai as mulai',
                'i.Waktu_Akhir as akhir',
            ]);

        $urutan = [];
        $terisi = 0;

        foreach ($lembar as $t) {
            $pid = (int) $t->participant_id;
            $urutan[$pid] = ($urutan[$pid] ?? 0) + 1;
            $t->urutan = $urutan[$pid];

            if (! $boleh($t)) {
                continue;
            }

            $pengenal = (string) ($t->ref ?: $t->token);

            if ($pengenal === '') {
                continue;
            }

            try {
                $pemeta->terimaDanPetakan($pengenal, $this->payload($t));
                $terisi++;
            } catch (Throwable $e) {
                /* ── KEGAGALAN SAMBUNGAN MENGHENTIKAN, KEGAGALAN ATURAN TIDAK ────────
                   |
                   | Keduanya sampai ke sini sebagai Throwable, tetapi artinya berlawanan.
                   | "Aturan menolak lembar ini" adalah keadaan yang memang hendak disemai,
                   | dan menghentikan seluruh siklus karenanya membuang ratusan lembar yang
                   | sudah benar. "Sambungan ke basis data putus" bukan tentang lembar ini
                   | sama sekali — dan meneruskannya berarti setiap lembar berikutnya ikut
                   | gagal dengan sebab yang sama.
                   |
                   | Terukur 14 September 2026: host basis data hilang di tengah pengisian,
                   | dan perintah ini mencetak ribuan baris peringatan yang seluruhnya
                   | mengulang satu galat yang sama, lalu berjalan terus dan meninggalkan
                   | siklus yang terisi separuh tanpa satu pun tanda bahwa ia tidak utuh. */
                if ($this->galatSambungan($e)) {
                    throw new RuntimeException(
                        'Sambungan ke basis data putus di tengah pengisian (lembar '.$t->id.'): '
                        .$e->getMessage()."\n"
                        .'Dihentikan supaya siklus tidak berdiri separuh terisi. Jalankan '
                        .'`pa:seed-presentasi --hapus` lalu ulangi ketika sambungan stabil.',
                        0,
                        $e,
                    );
                }

                $this->warn('  lembar '.$t->id.' gagal: '.$e->getMessage());
            }
        }

        return $terisi;
    }

    /**
     * Apakah galat ini soal SAMBUNGAN, bukan soal data.
     *
     * Dikenali dari SQLSTATE kelas 08 (connection exception) yang dilaporkan driver ODBC,
     * bukan dari mencocokkan kalimat galatnya — kalimatnya berbeda antar driver dan antar
     * bahasa sistem, dan penjaga yang bergantung padanya akan diam-diam berhenti mengenali
     * apa pun ketika salah satunya berubah.
     */
    private function galatSambungan(Throwable $e): bool
    {
        /* `QueryException` milik Laravel TURUNAN `PDOException`, jadi satu pemeriksaan sudah
           menutup keduanya. Menuliskannya dua kali menghasilkan cabang yang tidak pernah
           dijalankan — dan cabang mati di dalam penjaga adalah cara penjaga berhenti dijaga. */
        for ($x = $e; $x !== null; $x = $x->getPrevious()) {
            if ($x instanceof PDOException && str_starts_with((string) $x->getCode(), '08')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Muatan satu kiriman, berbentuk sama dengan yang dikirim HC Learn.
     *
     * `Kode_Soal` diambil dari `Kode_Soal_Sumber` butir paket siklus — kunci yang MEREKA
     * pakai. Memakai `Id_Jenis_Soal` di sini akan menghasilkan data contoh yang hijau
     * sempurna atas bentuk yang tidak pernah ada di lapangan; cacat itu sudah pernah
     * terjadi dan menyembunyikan dirinya selama sehari penuh.
     *
     * @return array<string, mixed>
     */
    private function payload(object $t): array
    {
        $butir = $this->butirPaket((int) $t->paket);

        if ($butir === []) {
            throw new RuntimeException('Paket '.$t->paket.' tidak punya satu butir aktif pun.');
        }

        /* Kecenderungan per PENILAI dan dasar per PESERTA, bukan acak murni per butir.
           Penilai sungguhan ada yang murah nilai dan ada yang pelit, dan orang yang dinilai
           memang punya tingkat yang berbeda-beda. Angka acak seragam menghapus persis dua
           sifat yang membuat kalibrasi dan sebaran kuadran ada. */
        $dasar = 58 + (crc32((string) $t->participant_id) % 34);
        $condong = -8 + (int) (crc32((string) $t->penilai) % 17);

        $berskor = array_values(array_filter($butir, fn ($b) => strtoupper((string) $b->berskor) !== 'T'));
        $bobotSatuan = count($berskor) > 0 ? round(100 / count($berskor), 2) : 0.0;

        mt_srand(self::BENIH + (int) $t->id);

        $detail = [];
        $total = 0.0;
        $sisaBobot = 100.0;
        $ke = 0;

        foreach ($butir as $b) {
            $kode = trim((string) ($b->kode ?? ''));

            if ($kode === '') {
                continue;
            }

            $satu = [
                'Kode_Soal' => $kode,
                'Kode_Alat_Tes' => null,
                'Nama_Alat_Tes' => (string) ($b->alat ?? ''),
                'Jenis_Soal' => (string) ($b->bagian ?? ''),
            ];

            if (strtoupper((string) $b->berskor) === 'T') {
                $satu['Skor'] = null;
                $satu['Skor_Maksimal'] = null;
                $satu['Bobot_Penilaian'] = null;
                $satu['Jawaban_Teks'] = $this->naratif((string) ($b->komp ?? ''), (int) $t->id);
            } else {
                $ke++;
                $skor = max(30, min(100, 5 * (int) round(($dasar + $condong + mt_rand(-6, 6)) / 5)));
                $bobot = $ke === count($berskor) ? round($sisaBobot, 2) : $bobotSatuan;
                $sisaBobot -= $bobot;

                $satu['Skor'] = $skor;
                $satu['Skor_Maksimal'] = 100;
                $satu['Bobot_Penilaian'] = $bobot;
                $satu['Jawaban_Teks'] = null;
                $total += $skor * $bobot / 100;
            }

            $satu['Status_Interpretation'] = null;
            $satu['Warna'] = null;
            $satu['Keterangan'] = (string) ($b->komp ?? '');
            $satu['Jumlah_Soal'] = 5;
            $satu['Jumlah_Terjawab'] = 5;

            $detail[] = $satu;
        }

        mt_srand();

        $selesai = date('Y-m-d H:i:s');

        return [
            'Kode_Karyawan' => (string) $t->penilai,
            'Id_Integrasi' => (int) $t->intg,
            'Id_Ujian_Token' => (string) ($t->ref ?: $t->token),
            'Id_Ujian_Nilai_Akhir' => 'PRS-'.$t->intg.'-FINAL',
            'Status_Skor' => 'FINAL',
            'is_multi' => false,
            'kode_akses' => null,
            'penilaian' => [
                'Nama_Tes' => (string) ($butir[0]->alat ?? ''),
                'Waktu_Mulai' => (string) $t->mulai,
                'Waktu_Akhir' => (string) $t->akhir,
                'Waktu_Mulai_Akses' => date('Y-m-d H:i:s', strtotime($selesai) - 240),
                'Waktu_Selesai_Akses' => $selesai,
                'Ambang_Batas_Nilai' => 0,
                'Status_Kelulusan' => null,
                'Warna' => null,
            ],
            'Total_Nilai' => round($total, 2),
            'Detail' => $detail,
        ];
    }

    /** @var array<int, list<object>> */
    private array $cacheButir = [];

    /**
     * @return list<object>
     */
    private function butirPaket(int $paketId): array
    {
        if (! isset($this->cacheButir[$paketId])) {
            $this->cacheButir[$paketId] = DB::table('N_PA_Cycle_Question_Package_Item as it')
                ->leftJoin('N_PA_Competency as c', 'c.Id_PA_Competency', '=', 'it.PA_Competency_Id')
                ->where('it.PA_Cycle_Question_Package_Id', $paketId)
                ->where('it.Flag_Aktif', 'Y')
                ->orderBy('it.Urutan_Tampil')
                ->get([
                    'it.Kode_Soal_Sumber as kode',
                    'it.Nama_Bagian_Sumber as bagian',
                    'it.Nama_Alat_Tes as alat',
                    'c.Nama_Competency as komp',
                    'c.Flag_Berskor as berskor',
                ])->all();
        }

        return $this->cacheButir[$paketId];
    }

    /**
     * Kalimat naratif yang berbeda per kompetensi DAN per lembar.
     *
     * Kalimat yang sama disalin ke semua orang membuat layar hasil tampak benar tanpa
     * membuktikan apa pun: kolom mana milik siapa tidak bisa dibedakan, dan justru itu yang
     * paling sering ditanyakan ketika seseorang menyanggah catatan tentang dirinya.
     */
    private function naratif(string $kompetensi, int $assignmentId): string
    {
        $pool = [
            'Strength' => [
                'Paling menonjol ketika menguraikan masalah teknis yang belum pernah ditemui sebelumnya; penjelasannya mudah diikuti rekan satu tim.',
                'Konsisten menepati komitmen tanggal tanpa perlu diingatkan, termasuk pada pekerjaan yang sebenarnya bukan porsinya.',
                'Cepat mengambil alih ketika rekan terhambat, dan melakukannya tanpa membuat yang bersangkutan merasa disalahkan.',
                'Tenang menghadapi perubahan prioritas mendadak; keputusannya tetap bisa dijelaskan alasannya kepada tim.',
                'Rapi mendokumentasikan apa yang dikerjakannya, sehingga pekerjaannya mudah dilanjutkan orang lain saat ia tidak ada.',
                'Berani menyampaikan pendapat berbeda di forum, tetapi menutupnya dengan usulan yang bisa dijalankan.',
            ],
            'Development Area' => [
                'Perlu lebih awal menyuarakan hambatan; beberapa kali baru terungkap ketika tenggatnya sudah dekat.',
                'Masih cenderung mengerjakan sendiri hal yang sebetulnya bisa didelegasikan, dan itu membatasi kapasitas timnya.',
                'Dokumentasi hasil kerjanya masih tertinggal dari kualitas pekerjaannya sendiri.',
                'Disarankan memperdalam penguasaan teknis pada area yang kini menjadi tanggung jawabnya.',
                'Komunikasi ke lintas divisi masih terlalu singkat, sehingga konteksnya sering perlu ditanyakan ulang.',
                'Perlu lebih konsisten menutup tindak lanjut rapat; sebagian selesai, sebagian lain menggantung tanpa kabar.',
            ],
        ];

        $pilihan = $pool[$kompetensi] ?? [
            'Catatan naratif untuk '.($kompetensi !== '' ? $kompetensi : 'kompetensi kualitatif').'.',
        ];

        return $pilihan[$assignmentId % count($pilihan)];
    }

    /**
     * @param  (callable(string): bool)|null  $boleh  penyaring per kode penilai
     */
    private function kirimYangLengkap(PaRaterSubmitService $pengiriman, int $cycleId, ?callable $boleh = null): int
    {
        $penilai = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('a.Flag_Aktif', 'Y')
            ->where('a.Status_Penugasan', '<>', 'DIBATALKAN')
            ->groupBy('a.Kode_Perusahaan_Penilai', 'a.Kode_Karyawan_Penilai')
            ->havingRaw("SUM(CASE WHEN a.Status_Penugasan = 'TERKUMPUL' THEN 1 ELSE 0 END) > 0")
            ->havingRaw("SUM(CASE WHEN a.Status_Penugasan NOT IN ('TERKUMPUL','SELESAI') THEN 1 ELSE 0 END) = 0")
            ->selectRaw('a.Kode_Perusahaan_Penilai as kp, a.Kode_Karyawan_Penilai as kk')
            ->get();

        $n = 0;

        foreach ($penilai as $r) {
            if ($boleh !== null && ! $boleh((string) $r->kk)) {
                continue;
            }

            try {
                $pengiriman->kirimSemua((string) $r->kp, (string) $r->kk, $cycleId);
                $n++;
            } catch (RuntimeException $e) {
                // Masih punya lembar kosong: memang belum boleh kirim.
            }
        }

        return $n;
    }

    private function lepaskanPenilai(PaRaterSubmitService $pengiriman, int $cycleId, int $participantId, string $nama): int
    {
        $penilai = DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->where('Status_Penugasan', PaRaterSubmitService::STATUS_TERKUMPUL)
            ->get(['Kode_Perusahaan_Penilai as kp', 'Kode_Karyawan_Penilai as kk']);

        $n = 0;

        foreach ($penilai as $r) {
            try {
                $pengiriman->lepasPaksa(
                    $cycleId,
                    (string) $r->kp,
                    (string) $r->kk,
                    'Data contoh presentasi: penilaian '.$nama.' dituntaskan supaya hasilnya bisa ditunjukkan.',
                );
                $n++;
            } catch (RuntimeException $e) {
                // Sudah terlepas lewat pengiriman penilainya sendiri.
            }
        }

        return $n;
    }

    /* ====================================================================== *
     * Hasil                                                                   *
     * ====================================================================== */

    /**
     * Perhitungan siklus BERJALAN: tidak semua yang bisa dihitung lantas dihitung.
     *
     * ── KENAPA SEBAGIAN SENGAJA DIBIARKAN BELUM DIHITUNG ──────────────────────
     *
     * Keranjang "Siap Dihitung" pada Antrean Tindak Lanjut berisi peserta yang penilaiannya
     * sudah lengkap tetapi hasilnya belum pernah dihitung. Kalau perintah ini menghitung
     * SEMUA yang bisa dihitung, keranjang itu selalu kosong — dan tombol "hitung yang
     * tertinggal" tidak pernah punya apa pun untuk dikerjakan, sehingga tidak pernah
     * terbukti bekerja.
     *
     * Orang-orang bernama dan peserta yang mengisi keranjang lain SELALU dihitung: keadaan
     * mereka yang diceritakan, dan menyerahkannya pada pembagian rata berarti cerita yang
     * berganti tiap disemai.
     *
     * @param  array{ikut: array<string, string>, khusus: array<string, string>, tanpa_kpi: list<string>}  $orang
     */
    private function hitungBerjalan(
        PaResultCalculationService $perhitungan,
        PaCalibrationService $kalibrasi,
        int $cycleId,
        array $orang,
    ): void {
        $wajibDihitung = [];

        foreach ([...array_values($orang['khusus']), ...$orang['tanpa_kpi']] as $kunci) {
            $p = $this->pesertaDariKunci($cycleId, $kunci);

            if ($p !== null) {
                $wajibDihitung[$p] = true;
            }
        }

        /* Peserta yang tidak punya satu pun penugasan aktif — yaitu yang seluruh penilainya
           dibatalkan — juga WAJIB dihitung. Merekalah isi keranjang "Butuh Penilai", dan itu
           hanya terbaca sesudah perhitungan menuliskan sebab penahanannya. Kalau pembagian
           "sengaja belum dihitung" kebetulan mengenai salah satunya, ia pindah diam-diam ke
           keranjang "Siap Dihitung" — keranjang yang isinya lalu menjanjikan hasil yang
           sebenarnya tidak akan pernah lahir. */
        $punyaPenugasan = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('a.Flag_Aktif', 'Y')
            ->distinct()
            ->pluck('a.PA_Participant_Id')
            ->mapWithKeys(fn ($x) => [(int) $x => true])
            ->all();

        $dihitung = 0;
        $tertahan = 0;
        $ditunda = 0;
        $ke = 0;

        foreach (DB::table('N_PA_Participant')->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN')->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Participant')->pluck('Id_PA_Participant') as $pid) {
            $pid = (int) $pid;

            if ($perhitungan->sisaPenugasan($pid) > 0) {
                continue;
            }

            $ke++;

            if (! isset($wajibDihitung[$pid]) && isset($punyaPenugasan[$pid]) && $ke % 4 === 0) {
                $ditunda++;

                continue;
            }

            try {
                $h = $perhitungan->hitung($pid);
                ($h['status'] ?? null) === 'TERTAHAN' ? $tertahan++ : $dihitung++;
            } catch (RuntimeException $e) {
                // Aturan yang menolak — bukan kerusakan, dan bukan alasan menghentikan sisanya.
            }
        }

        $this->line(sprintf(
            '  hasil: %d dihitung, %d tertahan, %d sengaja dibiarkan belum dihitung (keranjang "Siap Dihitung")',
            $dihitung, $tertahan, $ditunda,
        ));

        /* Kalibrasi Forum sudah dilepas (diganti Balancing), dan siklus OPEN belum bisa
           mengunci kurva — jadi siklus berjalan berhenti di hasil yang dihitung. */
    }

    /**
     * Kalibrasi pada siklus berjalan: satu orang yang DIPASTIKAN, sisanya seperlima.
     *
     * Orang yang dipastikan adalah `NAMA_SIAP_TERBIT`. Dialah satu-satunya yang seluruh
     * rantainya lengkap — dinilai, dihitung, digeser forum — sehingga tombol publikasi
     * punya sesuatu yang benar-benar siap terbit, bukan sekadar terhitung.
     *
     * @param  array{ikut: array<string, string>, khusus: array<string, string>, tanpa_kpi: list<string>}  $orang
     */
    private function kalibrasiBerjalan(PaCalibrationService $kalibrasi, int $cycleId, array $orang): void
    {
        $pidSiapTerbit = $this->pesertaDariKunci($cycleId, $orang['khusus'][self::NAMA_SIAP_TERBIT] ?? null);

        $digeser = $this->kalibrasiSebagian(
            $kalibrasi,
            $cycleId,
            setiapKe: 5,
            wajib: $pidSiapTerbit === null ? [] : [$pidSiapTerbit],
        );

        $this->line("  kalibrasi: {$digeser} hasil digeser forum; ".self::NAMA_SIAP_TERBIT.' siap diterbitkan');
    }

    private function tuntaskan(
        PaCycleGate $gate,
        PaCyclePreparationService $penyiapan,
        PaResultCalculationService $perhitungan,
        PaCalibrationService $kalibrasi,
        PaResultPublishService $penerbitan,
        int $cycleId,
        int $setiapKe = 4,
        bool $sampaiTerbit = true,
    ): void {
        $uid = (int) Auth::id();

        /* DITUTUP LEWAT SERVICE yang sama dengan tombol Tutup Pengisian, baru dihitung massal.
           `hitungSiklus()` menolak siklus yang masih OPEN — dan penolakannya benar. */
        $penyiapan->tutupPengisian($cycleId, $uid);
        $gate->lupakanMemo();

        $h = $perhitungan->hitungSiklus($cycleId, null, 500);
        $this->line("  hitung: {$h['dihitung']} dihitung, {$h['tertahan']} tertahan");

        $dikeluarkan = $this->keluarkanYangTertahan($cycleId);

        if ($dikeluarkan > 0) {
            $this->line("  {$dikeluarkan} peserta tertahan dikeluarkan supaya penerbitan bisa ditutup");
        }

        // Admin merangkum jawaban uraian penilai (Strength & Development Area) sebelum forum.
        $this->line('  rangkuman admin: '.$this->rangkumKualitatif($cycleId).' peserta');

        $kurva = app(PaKurvaService::class);

        if ($kurva->metode($cycleId) === 'KURVA') {
            // Data presentasi: sisa yang belum lengkap memang bagian dari skenarionya.
            $kurva->kunci($cycleId, $uid, 'Data presentasi (pa:seed-presentasi).');
            $gate->lupakanMemo();
            $this->line('  kurva grade dikunci');
        }

        $sesi = $this->sesiBalancing($cycleId, $uid, selesai: $sampaiTerbit);

        if (! $sampaiTerbit) {
            $this->line("  Balancing sedang berjalan (sesi #{$sesi}, Draft) — siklus berhenti di sini");

            return;
        }

        $hu = app(PaBalancingHitungUlangService::class)->jalankan($cycleId, $uid);
        $gate->lupakanMemo();
        $this->line('  hitung ulang Balancing #'.($hu['nomor'] ?? '?').': '.($hu['dihitung'] ?? 0).' peserta');

        $r = $penerbitan->rilisKeLeader($cycleId, $uid);
        $this->line('  rilis ke leader: '.($r['dirilis'] ?? 0).' hasil');

        $t = $penerbitan->terbitkan($cycleId, $uid);
        $this->line('  terbit: '.($t['diterbitkan'] ?? $t['terbit'] ?? 0).' hasil');

        $tertinggal = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('r.Flag_Aktif', 'Y')->where('r.Status_Hasil', 'DIHITUNG')->count();

        if ($tertinggal > 0) {
            $t2 = $penerbitan->terbitkan($cycleId, $uid);
            $this->line('  terbit ulang: '.($t2['diterbitkan'] ?? 0).' hasil yang tertimpa perhitungan antrean');
        }

        try {
            $penerbitan->tutupPenerbitan($cycleId, $uid);
            $gate->lupakanMemo();
            $this->line('  penerbitan ditutup; siklus PUBLISHED');
        } catch (RuntimeException $e) {
            $this->warn('  penerbitan belum bisa ditutup: '.$e->getMessage());
        }
    }

    /**
     * Rangkuman admin untuk kualitatif yang dibahas Balancing (Strength, Development Area),
     * disusun dari jawaban uraian penilai orang itu — seperti yang dilakukan admin di layar hasil.
     */
    private function rangkumKualitatif(int $cycleId): int
    {
        // Nama kategori → kode, hanya yang dibahas Balancing (Strength, Development Area).
        $kode = DB::table('N_PA_Competency')->whereIn('Kode_Competency', PaBalancingSesiService::KUALITATIF_BALANCING)
            ->where('Flag_Aktif', 'Y')->pluck('Kode_Competency', 'Nama_Competency');
        $baca = app(PaParticipantReadService::class);
        $jawaban = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('p.Status_Peserta', 'DIIKUTKAN')->where('p.Flag_Aktif', 'Y')
            ->where('r.Flag_Aktif', 'Y')->where('r.Status_Hasil', 'DIHITUNG')
            ->pluck('p.Id_PA_Participant')
            ->mapWithKeys(fn ($pid) => [(int) $pid => collect($baca->kualitatifPeserta((int) $pid)['jawaban'])
                ->filter(fn ($j) => isset($kode[$j['Nama_Competency'] ?? '']))]);

        $layanan = app(PaResultNoteService::class);
        $ditulis = 0;

        foreach ($jawaban as $pid => $baris) {
            $catatan = [];

            foreach ($baris->groupBy('Nama_Competency') as $nama => $isi) {
                $poin = $isi->pluck('Jawaban_Teks')->map(fn ($t) => trim(strip_tags((string) $t)))->filter()->unique()->take(3);

                if ($poin->isNotEmpty()) {
                    $catatan[$kode[$nama]] = ['<ul>'.$poin->map(fn ($t) => '<li>'.e($t).'</li>')->implode('').'</ul>'];
                }
            }

            if ($catatan !== []) {
                $layanan->simpanCatatanAdmin((int) $pid, $catatan, (int) Auth::id());
                $ditulis++;
            }
        }

        return $ditulis;
    }

    /**
     * Satu sesi Balancing lewat service yang sama dengan layar: kandidat layak, tim penilai,
     * penyesuaian skor dengan satu alasan per orang. `selesai` = semua ditinjau lalu FINAL;
     * selain itu sesi dibiarkan Draft dengan sebagian peserta belum ditinjau.
     */
    private function sesiBalancing(int $cycleId, int $uid, bool $selesai): int
    {
        $sesiSvc = app(PaBalancingSesiService::class);
        $kandidat = collect(app(PaBalancingKandidatService::class)->daftar($cycleId, []))->where('layak', true);

        // Sebar peserta ke tiap grup kurva, maksimal 5 per grup.
        $peserta = $kandidat->groupBy(fn ($k) => $k['grup_kurva'] ?? '-')
            ->flatMap(fn ($g) => $g->sortByDesc('final')->values()->filter(fn ($k, $i) => $i % 6 === 0)->take(5))
            ->pluck('participant_id')->values()->all();

        if ($peserta === []) {
            throw new RuntimeException("Siklus {$cycleId}: tidak ada kandidat Balancing yang layak.");
        }

        $tim = DB::table('Karyawan as k')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as g', 'g.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')->whereNull('k.Tanggal_Resign')->whereIn('g.nama_level', ['GENERAL MANAGER', 'MANAGER'])
            ->orderBy('k.Kode_Karyawan')->limit(3)->get(['k.Kode_Perusahaan', 'k.Kode_Karyawan'])
            ->map(fn ($k) => ['kode_perusahaan' => $k->Kode_Perusahaan, 'kode_karyawan' => $k->Kode_Karyawan])->all();

        $d = $sesiSvc->buat($cycleId, [
            'nama' => $selesai ? 'Forum Balancing Lintas Grup' : 'Forum Balancing Berjalan',
            'pic_user_id' => $uid, 'forum' => $tim, 'peserta_ids' => $peserta,
        ], $uid);

        $alasan = [
            'Forum menilai kontribusi proyek lintas divisi belum tercermin pada skor kompetensi.',
            'Bukti kerja kuartal ini lebih kuat dari skor penilai; disepakati naik terbatas.',
            'Penilai tunggal terlalu longgar dibanding rekan satu grup; disesuaikan agar setara.',
            'Ada keluhan pelanggan yang terverifikasi; forum menurunkan kompetensi terkait.',
        ];
        $isi = [];

        foreach ($d['peserta'] as $i => $p) {
            $ubah = $i % 3 !== 2;               // dua dari tiga orang disesuaikan
            $belum = ! $selesai && $i % 4 === 3; // di sesi berjalan, sebagian belum ditinjau
            $arah = $i % 4 === 3 ? -1 : 1;

            if ($belum) {
                continue;
            }

            $komp = array_map(function ($k, $j) use ($ubah, $arah, $i) {
                $baru = $ubah && $j < 2 ? max(0, min(100, round($k['sebelum'] + $arah * (3 + ($i + $j) % 6), 2))) : $k['sebelum'];

                return ['competency_id' => $k['competency_id'], 'nilai_sesudah' => $baru];
            }, $p['kompetensi'], array_keys($p['kompetensi']));

            $isi[] = [
                'participant_id' => $p['participant_id'],
                'status_tinjau' => $ubah ? null : 'TANPA_PERUBAHAN',
                'alasan' => $ubah ? $alasan[$i % count($alasan)] : '',
                'kompetensi' => $komp,
            ];
        }

        $d = $sesiSvc->simpanDraft($d['id'], $d['versi_data'], $isi,
            '<p>Forum membahas sebaran nilai per grup kurva dan menyepakati penyesuaian terbatas dengan alasan per orang.</p>', $uid);

        if ($selesai) {
            $sesiSvc->finalkan($d['id'], $d['versi_data'], $uid);
            $this->line("  Balancing: sesi #{$d['id']} FINAL (".count($peserta).' peserta)');
        }

        return (int) $d['id'];
    }

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
            DB::table('N_PA_Participant')->where('Id_PA_Participant', $t->id)->update([
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

    /**
     * Menggeser sebagian hasil lewat kalibrasi forum.
     *
     * Hanya sebagian, dan itu disengaja: siklus yang seluruh hasilnya dikalibrasi tidak bisa
     * dipakai menunjukkan selisih antara grade mesin dan grade forum — padahal selisih itulah
     * yang dibaca layar hasil, riwayat kalibrasi, dan audit.
     *
     * `$setiapKe` berbeda antar siklus dengan sengaja. Dua siklus yang menggeser peserta pada
     * posisi yang sama persis membuat layar riwayat kalibrasi terbaca seolah forum selalu
     * memutuskan hal yang sama, dan keserupaan itu adalah artefak perintah ini, bukan sifat
     * datanya.
     *
     * @param  list<int>  $wajib  participant id yang HARUS digeser, di luar pembagian rata
     */
    private function kalibrasiSebagian(
        PaCalibrationService $kalibrasi,
        int $cycleId,
        int $setiapKe = 4,
        array $wajib = [],
    ): int {
        $setiapKe = max(2, $setiapKe);

        $hasil = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('r.Flag_Aktif', 'Y')
            ->where('r.Status_Hasil', 'DIHITUNG')
            ->whereNotNull('r.Kode_Grade_Sistem')
            ->orderBy('r.PA_Participant_Id')
            ->get([
                'r.Id_PA_Result as rid',
                'r.Kode_Grade_Sistem as grade',
                'r.PA_Participant_Id as pid',
            ]);

        /* Pita diambil dari SALINAN SIKLUS, bukan dari master: siklus yang sudah berjalan
           dinilai dengan pita yang berlaku SAAT ITU, dan menggeser grade ke kode yang hanya
           ada di master akan menghasilkan grade yang tidak dikenali layarnya sendiri. */
        $pita = DB::table('N_PA_Cycle_Score_Range_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Score_Type', 'FINAL')
            ->where('Score_Usage', 'GRADE')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Nilai_Dari')
            ->pluck('Kode_Range')
            ->all();

        if ($pita === []) {
            return 0;
        }

        $pelaku = (int) (Auth::id() ?? 0);

        if ($pelaku === 0) {
            return 0;
        }

        $n = 0;

        foreach ($hasil as $i => $r) {
            if ($i % $setiapKe !== 0 && ! in_array((int) $r->pid, $wajib, true)) {
                continue;
            }

            $posisi = array_search((string) $r->grade, $pita, true);

            if ($posisi === false) {
                continue;
            }

            /* Digeser SATU tingkat, arahnya berganti-ganti. Forum sungguhan menaikkan dan
               menurunkan; data contoh yang hanya menaikkan membuat layar selisih terbaca
               seolah kalibrasi selalu menguntungkan. */
            $tujuan = $n % 2 === 0
                ? min(count($pita) - 1, $posisi + 1)
                : max(0, $posisi - 1);

            /* Grade yang sudah berada di ujung pita tidak bisa digeser ke arah yang diundi.
               Dibalikkan, bukan dilewati: kalau dilewati, peserta yang HARUS digeser — orang
               yang seluruh rantainya dipakai bercerita — bisa diam-diam tidak terkalibrasi
               hanya karena nilainya kebetulan tertinggi atau terendah. */
            if ($tujuan === $posisi) {
                $tujuan = $n % 2 === 0
                    ? max(0, $posisi - 1)
                    : min(count($pita) - 1, $posisi + 1);
            }

            if ($tujuan === $posisi) {
                continue;
            }

            try {
                $kalibrasi->kalibrasi(
                    (int) $r->rid,
                    $pita[$tujuan],
                    null,
                    'Keputusan forum kalibrasi (data contoh presentasi).',
                    $pelaku,
                    'Forum Kalibrasi',
                );
                $n++;
            } catch (Throwable $e) {
                // Aturan kalibrasi yang menolak — bukan alasan menghentikan sisanya.
            }
        }

        return $n;
    }

    /**
     * Memindahkan status siklus MILIK PERINTAH INI lewat kolomnya langsung.
     *
     * Perjalanan status sungguhan menuntut pencetakan pintu HC Learn, dan siklus lampau
     * memang sengaja tidak punya itu. Yang dijaga di sini justru batasnya: hanya siklus
     * ber-penanda milik perintah ini yang tersentuh, dan aturan "satu siklus terbuka" tetap
     * ditegakkan sebelum menulis OPEN — aturan yang hanya dipasang di satu dari dua jalur
     * setara bukan aturan.
     */
    private function pindahStatus(PaCycleGate $gate, int $cycleId, string $status): void
    {
        $milikSendiri = DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->where('Deskripsi', 'like', '%'.self::PENANDA.'%')
            ->exists();

        if (! $milikSendiri) {
            throw new RuntimeException(
                "Siklus {$cycleId} bukan milik perintah ini; statusnya tidak disentuh."
            );
        }

        if ($status === 'OPEN') {
            $lain = DB::table('N_PA_Cycle')
                ->where('Status_Cycle', 'OPEN')
                ->where('Flag_Aktif', 'Y')
                ->where('Id_PA_Cycle', '<>', $cycleId)
                ->value('Kode_Cycle');

            if ($lain !== null) {
                throw new RuntimeException(
                    "Siklus {$lain} sedang terbuka; hanya satu siklus boleh terbuka pada satu waktu."
                );
            }
        }

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->update(['Status_Cycle' => $status]);
        $gate->lupakanMemo();
    }

    /* ====================================================================== *
     * Laporan dan pembersihan                                                 *
     * ====================================================================== */

    /**
     * @param  list<array{id:int, kode:string, nama:string, mode:string, hclearn:bool}>  $siklus
     */
    private function laporkan(array $siklus, string $penandaTangan): void
    {
        foreach ($siklus as $s) {
            /* Hanya peserta DIIKUTKAN yang dihitung. Baris hasil juga terbentuk bagi yang
               dikecualikan, dan memasukkannya membuat laporan ini berisi ratusan
               BELUM_DIHITUNG milik orang yang memang tidak ikut — angka yang benar secara
               tabel tetapi berbohong tentang siklusnya. */
            $r = DB::table('N_PA_Result as r')
                ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
                ->where('p.PA_Cycle_Id', $s['id'])
                ->where('p.Status_Peserta', 'DIIKUTKAN')
                ->where('p.Flag_Aktif', 'Y')
                ->where('r.Flag_Aktif', 'Y')
                ->selectRaw('r.Status_Hasil as st, COUNT(*) as n')
                ->groupBy('r.Status_Hasil')->pluck('n', 'st')->all();

            $ikut = DB::table('N_PA_Participant')->where('PA_Cycle_Id', $s['id'])
                ->where('Status_Peserta', 'DIIKUTKAN')->where('Flag_Aktif', 'Y')->count();

            $status = DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $s['id'])->value('Status_Cycle');

            $this->line(sprintf(
                '  #%d %s — %s [%s] %s, %d peserta, hasil: %s',
                $s['id'], $s['kode'], $s['nama'], (string) $status, $s['mode'], $ikut, json_encode($r),
            ));
        }

        $berjalan = null;

        foreach ($siklus as $s) {
            if ($s['mode'] === 'BERJALAN') {
                $berjalan = $s;
            }
        }

        if ($berjalan !== null) {
            $this->newLine();
            $this->line('  Keranjang Antrean Tindak Lanjut pada siklus berjalan '.$berjalan['kode'].':');

            foreach ($this->ringkasKeranjang((int) $berjalan['id']) as $label => $n) {
                $this->line(sprintf('    %-22s %d', $label, $n));
            }
        }

        $this->newLine();

        if (array_filter(array_column($siklus, 'hclearn')) !== []) {
            $this->line('  Penjadwalan HC Learn ditandatangani sebagai pengguna '.$penandaTangan.'.');
        } else {
            $this->line('  Seluruh pintu pengisian dicetak lokal; HC Learn tidak disentuh.');
        }

        $this->line('  KPI contoh bertanda "'.self::PENANDA_KPI.'" pada deskripsi parameternya.');
        $this->line('  Jalankan ulang dengan --hapus untuk mencabut siklus dan KPI contoh ini.');
    }

    /**
     * Isi keranjang Antrean Tindak Lanjut, dibaca dari SUMBER YANG SAMA dengan layarnya.
     *
     * Menghitungnya sendiri di sini akan menghasilkan laporan yang bisa berselisih dengan
     * apa yang dilihat orang di layar — dan yang dipercaya orang adalah layarnya, sehingga
     * selisihnya akan terbaca sebagai perintah ini yang berbohong.
     *
     * @return array<string, int>
     */
    private function ringkasKeranjang(int $cycleId): array
    {
        $k = app(PaAdminDashboardService::class)->keranjang($cycleId);

        return [
            'Siap Dihitung' => (int) ($k['siap_dihitung'] ?? 0),
            'Butuh KPI' => (int) ($k['needs_kpi'] ?? 0),
            'Butuh Penilai' => (int) ($k['needs_rater'] ?? 0),
            'Tertahan lain' => (int) ($k['tertahan_lain'] ?? 0),
            'Perlu disimpulkan' => (int) ($k['needs_review_qualitative'] ?? 0),
            'Sedang dikalibrasi' => (int) ($k['reviewing_calibration'] ?? 0),
            'Sudah terbit' => (int) ($k['terbit'] ?? 0),
        ];
    }

    private function hapus(): int
    {
        $cycleIds = DB::table('N_PA_Cycle')
            ->where('Deskripsi', 'like', '%'.self::PENANDA.'%')
            ->pluck('Id_PA_Cycle')
            ->map(fn ($x) => (int) $x)
            ->all();

        if ($cycleIds === []) {
            $this->line('Tidak ada siklus presentasi untuk dibuang.');
        } else {
            /* Dinonaktifkan, TIDAK dihapus barisnya. Penghapusan keras menuntut urutan yang
               benar atas sepuluh tabel turunan, dan satu urutan yang salah meninggalkan baris
               yatim yang lebih sulit ditemukan daripada siklus yang tinggal ditandai mati. */
            DB::table('N_PA_Cycle')->whereIn('Id_PA_Cycle', $cycleIds)
                ->update(['Flag_Aktif' => 'T', 'Status_Cycle' => 'CLOSED']);

            DB::table('N_PA_Participant')->whereIn('PA_Cycle_Id', $cycleIds)->update(['Flag_Aktif' => 'T']);

            $this->line('Siklus presentasi dinonaktifkan: '.implode(', ', $cycleIds));
        }

        /* Dua hal yang berbeda dicabut dengan cara berbeda, dan membedakannya penting.
           |
           | RENCANA yang dibuat perintah ini dicabut seluruhnya — ia memang tidak pernah ada
           | sebelumnya. REALISASI di atas rencana ORANG LAIN hanya realisasinya yang dicabut;
           | mencabut rencananya berarti membuang rencana KPI sungguhan yang tidak pernah
           | dibuat perintah ini, dan itu kerusakan yang tidak bisa dikembalikan dari sini. */
        $rencanaSendiri = DB::table('KPI_Pengajuan_Detail')
            ->where('Deskripsi', self::PENANDA_KPI)
            ->pluck('Id_Pengajuan')
            ->unique()
            ->map(fn ($x) => (int) $x)
            ->all();

        if ($rencanaSendiri !== []) {
            DB::table('KPI_Pengajuan')->whereIn('Id_Pengajuan', $rencanaSendiri)->update(['Flag_Aktif' => 'T']);
            DB::table('KPI_Update_Performance')->whereIn('Id_Pengajuan', $rencanaSendiri)->update(['Flag_Aktif' => 'T']);
            $this->line('Rencana KPI contoh dinonaktifkan: '.count($rencanaSendiri).' pengajuan.');
        }

        $realisasi = DB::table('KPI_Update_Performance')
            ->where('No_Transaksi_Ref', self::PENANDA)
            ->where('Flag_Aktif', 'Y')
            ->update(['Flag_Aktif' => 'T']);

        $this->line('Realisasi KPI contoh dinonaktifkan: '.$realisasi.' baris.');

        if ($rencanaSendiri === [] && $realisasi === 0) {
            $this->line('Tidak ada KPI contoh untuk dicabut.');
        }

        /* BARIS TARGET YANG DITAMBAL SENGAJA TIDAK DIHAPUS, dan itu bukan kelalaian.
           |
           | `KPI_Update_Performance_Detail` menunjuk `Id_Pengajuan_Det` lewat kolom NOT NULL.
           | Menghapus baris targetnya meninggalkan baris realisasi yatim yang menunjuk ke
           | baris yang tidak ada lagi — kerusakan yang jauh lebih sulit ditemukan daripada
           | baris target berlebih yang namanya sendiri sudah mengaku dari mana asalnya.
           |
           | Dilaporkan supaya yang membaca tahu jejaknya masih ada, dan bagaimana mencarinya. */
        $target = DB::table('KPI_Pengajuan_Det')
            ->where('Nama_Target', 'like', '%'.self::PENANDA_TARGET.'%')
            ->count();

        if ($target > 0) {
            $this->line(sprintf(
                '%d baris target tambalan DIBIARKAN (realisasi menunjuk padanya). Cari dengan: '
                ."Nama_Target LIKE '%%%s%%'.",
                $target, self::PENANDA_TARGET,
            ));
        }

        return self::SUCCESS;
    }
}
