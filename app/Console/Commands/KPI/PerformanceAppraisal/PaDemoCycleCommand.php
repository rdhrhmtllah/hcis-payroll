<?php

namespace App\Console\Commands\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaAssignmentBuilderService;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaCyclePreparationService;
use App\Services\KPI\PerformanceAppraisal\PaPayloadMapperService;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Membuat siklus contoh supaya frontend punya sesuatu untuk dilihat.
 *
 * KENAPA PERINTAH INI ADA
 *
 * Tanpa satu pun siklus, seluruh layar PA kosong dan frontend tidak bisa melihat hasil
 * kerjanya sendiri. Menunggu siklus sungguhan berarti frontend menganggur sampai
 * backend selesai — persis hal yang ingin dihindari saat dua jalur berjalan paralel.
 *
 * KENAPA BUKAN SEEDER
 *
 * Seeder berjalan atas seluruh database dan mudah tertukar dengan seeder produksi.
 * Perintah ini hanya menyentuh siklus ber-kode PA-DEMO-FE dan menolak menyentuh yang
 * lain, sehingga tidak ada cara memakainya untuk merusak data siklus sungguhan.
 *
 * KENAPA ANGKA KOMPETENSINYA DIACAK
 *
 * Yang dibutuhkan frontend adalah sebaran nilai, bukan nilai yang benar. Angka yang
 * seluruhnya sama membuat tabel dan grafik terlihat betul padahal belum tentu. Acaknya
 * memakai benih tetap supaya dua kali menjalankan menghasilkan angka yang sama — layar
 * yang berubah-ubah sendiri tiap dijalankan mustahil dibandingkan.
 */
class PaDemoCycleCommand extends Command
{
    protected $signature = 'pa:demo-cycle
        {--hapus : Membuang siklus contoh beserta seluruh turunannya}
        {--peserta=8 : Berapa peserta yang diberi penilai dan hasil}
        {--berhenti-di-persiapan : Berhenti sesudah siklus disiapkan, tanpa menugaskan atau menghitung}';

    protected $description = 'Membuat siklus Performance Appraisal contoh untuk pengembangan frontend';

    /** Kode ini yang membatasi seluruh perintah. Tidak ada siklus lain yang tersentuh. */
    private const KODE = 'PA-DEMO-FE';

    /**
     * Siklus contoh kedua, berhenti di tahap PERSIAPAN.
     *
     * Kode terpisah, BUKAN memakai ulang yang pertama. Memakai ulang berarti
     * mengembalikan siklus yang sudah CLOSED ke DRAFT sementara hasil-hasilnya tetap
     * TERBIT — keadaan yang gerbangnya sendiri menyatakan tidak boleh ada, dan sekaligus
     * merusak satu-satunya siklus yang bisa dipakai menguji layar hasil.
     *
     * Dua siklus melayani dua keluarga layar: yang ini untuk penyiapan (daftar peserta,
     * keranjang tertunda, tombol kunci), yang pertama untuk hasil dan penerbitan.
     */
    private const KODE_SIAP = 'PA-DEMO-FE-SIAP';

    /** Benih tetap supaya angkanya sama tiap kali dijalankan. */
    private const BENIH = 20260901;

    public function handle(
        PaCycleGate $gate,
        PaCyclePreparationService $penyiapan,
        PaAssignmentBuilderService $penugasan,
        PaPayloadMapperService $pemeta,
        PaResultCalculationService $perhitungan,
    ): int {
        if (app()->environment('production')) {
            $this->error('Perintah ini tidak boleh dijalankan di production.');

            return self::FAILURE;
        }

        if ($this->option('hapus')) {
            return $this->hapus();
        }

        $jumlahPeserta = max(1, (int) $this->option('peserta'));

        $tahapPersiapan = (bool) $this->option('berhenti-di-persiapan');
        $kode = $tahapPersiapan ? self::KODE_SIAP : self::KODE;

        try {
            $cycleId = $this->siklus($kode);
            $this->info("Siklus contoh: #{$cycleId} ({$kode})");

            $gate->lupakanMemo();
            $hasilSiap = $penyiapan->siapkan($cycleId);
            $this->info(sprintf(
                'Disiapkan: %d diikutkan, %d dikecualikan, %d tertunda.',
                $hasilSiap['peserta']['diikutkan'] ?? 0,
                $hasilSiap['peserta']['dikecualikan'] ?? 0,
                $hasilSiap['peserta']['tertunda'] ?? 0,
            ));

            if ($tahapPersiapan) {
                /* Siklus dibiarkan pada tahap PERSIAPAN, bukan ditutup.
                   Layar penyiapan - daftar peserta, keranjang tertunda, tombol kunci -
                   hanya bisa dilihat hidup ketika siklusnya memang belum dikunci. Siklus
                   yang sudah CLOSED menonaktifkan semuanya, sehingga frontend tidak
                   pernah melihat keadaan yang justru sedang dirancangnya. */
                $status = DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->value('Status_Cycle');

                $this->info("Berhenti di tahap persiapan. Status siklus: {$status}.");
                $this->line('Jalankan tanpa --berhenti-di-persiapan untuk melanjutkan sampai hasil terbit.');

                return self::SUCCESS;
            }

            $peserta = $this->pilihPeserta($cycleId, $jumlahPeserta);

            if ($peserta->isEmpty()) {
                $this->warn('Tidak ada peserta DIIKUTKAN; berhenti di sini.');

                return self::SUCCESS;
            }

            $paket = $this->paketSoal($cycleId, $peserta->pluck('ID_Level_Saat_Cycle')->unique());
            $kodeSoal = $this->petaSoal($paket);

            /* Urutannya mengikuti gerbang, bukan sebaliknya. Penugasan hanya boleh saat
               PREPARED, payload hanya saat OPEN atau CLOSED, dan perhitungan hanya saat
               CLOSED. Menyusun ulang urutan ini akan membuat perintahnya ditolak
               gerbangnya sendiri, dan itu akan terbaca seperti perintahnya yang rusak. */
            $ditugaskan = 0;

            foreach ($peserta as $p) {
                $ditugaskan += $this->beriPenilaiDiri($penugasan, (int) $p->Id_PA_Participant);
            }

            $this->pindahStatus($gate, $cycleId, 'OPEN');

            $dipetakan = 0;

            foreach ($peserta as $p) {
                $dipetakan += $this->isikanJawaban(
                    $pemeta, (int) $p->Id_PA_Participant,
                    $paket[(int) $p->ID_Level_Saat_Cycle] ?? null, $kodeSoal
                );
            }

            $this->info("Penugasan dibuat: {$ditugaskan}. Payload dipetakan: {$dipetakan}.");

            $this->pindahStatus($gate, $cycleId, 'CLOSED');

            /* Dihitung hanya untuk peserta contoh, BUKAN seluruh siklus.
               Siklus ini memuat seluruh karyawan aktif karena penyiapan memang bekerja
               atas semuanya, tetapi hanya segelintir yang diberi penilai dan payload.
               Menghitung sisanya hanya menghasilkan ratusan baris TERTAHAN yang sudah
               bisa ditebak, dengan ongkos menit-menit ke database jarak jauh. */
            $dihitung = 0;
            $tertahan = 0;

            foreach ($peserta as $p) {
                $h = $perhitungan->hitung((int) $p->Id_PA_Participant);

                if ($h['status'] === 'TERTAHAN') {
                    $tertahan++;
                    $this->warn("  tertahan #{$p->Id_PA_Participant}: {$h['alasan_tertahan']}");

                    continue;
                }

                $dihitung++;
                $this->line(sprintf(
                    '  #%d %s: kompetensi %s, KPI %s, akhir %s (%s)',
                    $p->Id_PA_Participant,
                    $p->Nama_Karyawan_Saat_Cycle,
                    $h['nilai_kompetensi'] ?? '-',
                    $h['nilai_kpi'] ?? '-',
                    $h['final_score'] ?? '-',
                    $h['kode_grade'] ?? '-',
                ));
            }

            $this->info("Hasil: {$dihitung} dihitung, {$tertahan} tertahan.");

            $this->newLine();
            $this->info('Buka /performance-appraisal untuk melihatnya.');

            return self::SUCCESS;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Memindahkan status siklus contoh lewat kolomnya langsung.
     *
     * Perjalanan status yang sebenarnya milik aplikasi dan punya syaratnya sendiri.
     * Perintah ini tidak berpura-pura menjadi aplikasi — ia hanya menempatkan siklus
     * CONTOH pada keadaan yang dibutuhkan langkah berikutnya, dan hanya pada siklus
     * ber-kode PA-DEMO-FE.
     */
    private function pindahStatus(PaCycleGate $gate, int $cycleId, string $status): void
    {
        /*
         | PINTU KEDUA yang menulis OPEN.
         |
         | Perintah ini sengaja melewati gerbang — dan justru karena itu ia bisa mematahkan
         | aturan "hanya satu siklus boleh terbuka" tanpa satu pun lapisan yang mencegahnya.
         | Aturan yang hanya dipasang di satu dari dua jalur setara bukan aturan; ia hanya
         | kebetulan yang bertahan sampai jalur satunya dipakai.
         */
        if ($status === 'OPEN') {
            $lain = DB::table('N_PA_Cycle')
                ->where('Status_Cycle', 'OPEN')
                ->where('Flag_Aktif', 'Y')
                ->where('Id_PA_Cycle', '<>', $cycleId)
                ->first(['Kode_Cycle']);

            if ($lain !== null) {
                throw new RuntimeException(sprintf(
                    'Siklus %s sedang terbuka. Hanya satu siklus boleh terbuka pada satu '
                    .'waktu; tutup dulu siklus itu sebelum menjalankan contoh ini.',
                    $lain->Kode_Cycle,
                ));
            }
        }

        DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->where('Kode_Cycle', self::KODE)
            ->update(['Status_Cycle' => $status]);

        // Gerbang menghafal status per siklus. Tanpa dilupakan, langkah berikutnya
        // masih dinilai memakai status yang lama.
        $gate->lupakanMemo();
    }

    /**
     * Membuat siklusnya, atau mengembalikan yang sudah ada ke DRAFT.
     *
     * Mengembalikannya ke DRAFT penting: menjalankan ulang perintah ini pada siklus yang
     * sudah CLOSED akan ditolak gerbangnya, dan frontend akan mengira perintahnya rusak.
     */
    private function siklus(string $kode = self::KODE): int
    {
        $ada = DB::table('N_PA_Cycle')->where('Kode_Cycle', $kode)->first(['Id_PA_Cycle']);

        if ($ada !== null) {
            DB::table('N_PA_Cycle')
                ->where('Id_PA_Cycle', $ada->Id_PA_Cycle)
                ->update(['Status_Cycle' => 'DRAFT', 'Locked_At' => null, 'Locked_By' => null]);

            return (int) $ada->Id_PA_Cycle;
        }

        return (int) DB::table('N_PA_Cycle')->insertGetId([
            'Kode_Cycle' => $kode,
            'Nama_Cycle' => $kode === self::KODE_SIAP
                ? 'Siklus Contoh Tahap Persiapan'
                : 'Siklus Contoh Pengembangan Frontend',
            'Tahun' => (int) date('Y'),
            'Deskripsi' => 'Dibuat perintah pa:demo-cycle. Bukan siklus sungguhan.',
            'Periode_Dinilai_Dari' => date('Y').'-01-01',
            'Periode_Dinilai_Sampai' => date('Y').'-06-30',
            'Status_Cycle' => 'DRAFT',
            'Flag_Aktif' => 'Y',
        ], 'Id_PA_Cycle');
    }

    /**
     * Memilih peserta contoh, MENDAHULUKAN yang punya KPI disetujui.
     *
     * Peserta tanpa KPI berakhir TERTAHAN - itu perilaku yang benar, tetapi layar yang
     * seluruh barisnya tertahan tidak menunjukkan apa pun tentang grade, kuadran HAV,
     * maupun nilai akhir. Di database pengembangan hanya segelintir karyawan yang punya
     * KPI disetujui, jadi memilih delapan pertama menurut nomor hampir pasti meleset
     * dari mereka semua.
     *
     * @return \Illuminate\Support\Collection
     */
    private function pilihPeserta(int $cycleId, int $jumlah)
    {
        $kolom = ['Id_PA_Participant', 'ID_Level_Saat_Cycle', 'Nama_Karyawan_Saat_Cycle'];

        // Karyawan yang punya baris KPI berstatus resmi, apa pun periodenya.
        $berkpi = DB::table('KPI_Update_Performance as up')
            ->join('KPI_Pengajuan as p', 'p.Id_Pengajuan', '=', 'up.Id_Pengajuan')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'p.Id_User')
            ->where('up.Flag_Aktif', 'Y')
            ->where('up.Flag_Approval', 'Y')
            ->where('up.Flag_Verifikasi', 'Y')
            ->whereNull('up.Status')
            ->whereNotNull('u.Kode_Users')
            ->distinct()
            ->pluck('u.Kode_Users')
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->all();

        $dasar = fn () => DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN');

        $pilihan = $berkpi === []
            ? collect()
            : $dasar()->whereIn('Kode_Karyawan', $berkpi)
                ->orderBy('Id_PA_Participant')->limit($jumlah)->get($kolom);

        if ($pilihan->count() >= $jumlah) {
            return $pilihan;
        }

        // Ditambal peserta lain supaya jumlahnya tetap terpenuhi. Mereka akan TERTAHAN,
        // dan itu justru berguna: layar perlu menunjukkan kedua keadaan.
        $tambahan = $dasar()
            ->whereNotIn('Id_PA_Participant', $pilihan->pluck('Id_PA_Participant')->all() ?: [0])
            ->orderBy('Id_PA_Participant')
            ->limit($jumlah - $pilihan->count())
            ->get($kolom);

        $this->info(sprintf(
            'Peserta contoh: %d punya KPI, %d tanpa KPI (akan tertahan).',
            $pilihan->count(), $tambahan->count()
        ));

        return $pilihan->concat($tambahan);
    }

    /**
     * Satu paket soal per level yang dipakai peserta contoh.
     *
     * @return array<int, int> level → id paket
     */
    private function paketSoal(int $cycleId, $levels): array
    {
        $hasil = [];

        foreach ($levels as $level) {
            $level = (int) $level;

            $ada = DB::table('N_PA_Cycle_Question_Package')
                ->where('PA_Cycle_Id', $cycleId)
                ->where('ID_Level', $level)
                ->value('Id_PA_Cycle_Question_Package');

            $hasil[$level] = $ada !== null ? (int) $ada : (int) DB::table('N_PA_Cycle_Question_Package')
                ->insertGetId([
                    'PA_Cycle_Id' => $cycleId,
                    'ID_Level' => $level,
                    'Id_Master_Ujian' => 'DEMO-L'.$level,
                    'Nama_Ujian_Sumber' => 'Paket Contoh Level '.$level,
                    'Sumber_Kontrak' => 'DEMO',
                    'Catatan' => 'Dibuat pa:demo-cycle; bukan paket HC Learn sungguhan.',
                    'Flag_Aktif' => 'Y',
                ], 'Id_PA_Cycle_Question_Package');
        }

        return $hasil;
    }

    /**
     * Memetakan kode soal ke kompetensi nyata, plus dua butir uraian menjadi catatan.
     *
     * @param  array<int, int>  $paket
     * @return list<array{kode:string, competency_id:int|null, teks:bool}>
     */
    private function petaSoal(array $paket): array
    {
        $kompetensi = DB::table('N_PA_Competency')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Berskor', 'Y')
            ->orderBy('Urutan_Tampil')
            ->limit(8)
            ->get(['Id_PA_Competency']);

        if ($kompetensi->isEmpty()) {
            throw new RuntimeException('Master kompetensi kosong; siklus contoh tidak bisa dibuat.');
        }

        /* Butir uraiannya menunjuk KOMPETENSI KUALITATIF, sama seperti butir berskala
           menunjuk kompetensi berskor. Butir yang tidak menunjuk kompetensi mana pun
           tersimpan jawabannya tetapi tidak pernah sampai ke panel catatan. */
        $kualitatif = DB::table('N_PA_Competency as k')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'k.PA_Competency_Type_Id')
            ->where('k.Flag_Aktif', 'Y')
            ->where('t.Kode_Type', 'KUALITATIF')
            ->orderBy('k.Urutan_Tampil')
            ->pluck('k.Id_PA_Competency')
            ->all();

        $butir = [];
        $i = 0;

        foreach ($kompetensi as $k) {
            $i++;
            $butir[] = [
                'kode' => sprintf('K%02d', $i),
                'competency_id' => (int) $k->Id_PA_Competency,
                'teks' => false,
            ];
        }

        foreach ($kualitatif as $n => $idKualitatif) {
            $butir[] = [
                'kode' => sprintf('QL%02d', $n + 1),
                'competency_id' => (int) $idKualitatif,
                'teks' => true,
            ];
        }

        foreach ($paket as $paketId) {
            foreach ($butir as $urutan => $b) {
                $sudah = DB::table('N_PA_Cycle_Question_Package_Item')
                    ->where('PA_Cycle_Question_Package_Id', $paketId)
                    ->where('Id_Jenis_Soal', $b['kode'])
                    ->exists();

                if ($sudah) {
                    continue;
                }

                DB::table('N_PA_Cycle_Question_Package_Item')->insert([
                    'PA_Cycle_Question_Package_Id' => $paketId,
                    'Id_Jenis_Soal' => $b['kode'],
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
     * Menugaskan peserta menilai dirinya sendiri.
     *
     * SELF adalah satu-satunya kategori yang bisa dibentuk tanpa data relasi organisasi,
     * dan N_HRIS_Organization_Relationship masih kosong. Kategori lain sengaja tidak
     * dikarang: penilai palsu akan membuat layar tampak lengkap padahal jalur usulannya
     * belum pernah benar-benar dicoba.
     */
    private function beriPenilaiDiri(PaAssignmentBuilderService $penugasan, int $participantId): int
    {
        $sudah = DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->exists();

        if ($sudah) {
            return 0;
        }

        $usulan = $penugasan->usulkan($participantId);
        $self = $usulan[PaAssignmentBuilderService::KATEGORI_SELF] ?? null;

        if ($self === null || $self['calon'] === []) {
            return 0;
        }

        $calon = $self['calon'][0];

        $hasil = $penugasan->tugaskan($participantId, [[
            'kode_kategori' => PaAssignmentBuilderService::KATEGORI_SELF,
            'kode_perusahaan' => $calon['kode_perusahaan'],
            'kode_karyawan' => $calon['kode_karyawan'],
            'sumber' => PaAssignmentBuilderService::SUMBER_USULAN,
        ]]);

        return $hasil['dibuat'];
    }

    /**
     * Membuat token integrasi lalu mengirim payload seolah-olah datang dari HC Learn.
     *
     * @param  list<array{kode:string, competency_id:int|null, teks:bool}>  $butir
     */
    private function isikanJawaban(
        PaPayloadMapperService $pemeta,
        int $participantId,
        ?int $paketId,
        array $butir,
    ): int {
        if ($paketId === null) {
            return 0;
        }

        $tugas = DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->get(['Id_PA_Assignment', 'Kode_Karyawan_Penilai']);

        $dipetakan = 0;

        foreach ($tugas as $t) {
            $token = 'demo-'.$t->Id_PA_Assignment;

            $integrasi = DB::table('N_PA_Assignment_Integration')
                ->where('Id_Ujian_Token', $token)
                ->exists();

            if (! $integrasi) {
                DB::table('N_PA_Assignment_Integration')->insert([
                    'PA_Assignment_Id' => $t->Id_PA_Assignment,
                    'PA_Cycle_Question_Package_Id' => $paketId,
                    'Id_Ujian_Token' => $token,
                    'Flag_Aktif' => 'Y',
                ]);
            }

            $sudahAdaPayload = DB::table('N_PA_Assignment_Payload as p')
                ->join('N_PA_Assignment_Integration as i',
                    'i.Id_PA_Assignment_Integration', '=', 'p.PA_Assignment_Integration_Id')
                ->where('i.Id_Ujian_Token', $token)
                ->exists();

            if ($sudahAdaPayload) {
                continue;
            }

            $pemeta->terimaDanPetakan($token, $this->payload($t, $butir));
            $dipetakan++;
        }

        return $dipetakan;
    }

    /**
     * @param  list<array{kode:string, competency_id:int|null, teks:bool}>  $butir
     * @return array<string, mixed>
     */
    private function payload(object $tugas, array $butir): array
    {
        // Benih diturunkan dari id penugasan supaya tiap orang berbeda tetapi selalu
        // sama pada penjalanan berikutnya.
        mt_srand(self::BENIH + (int) $tugas->Id_PA_Assignment);

        $detail = [];

        foreach ($butir as $b) {
            if ($b['teks']) {
                $detail[] = [
                    'Id_Jenis_Soal' => $b['kode'],
                    'Nama_Soal' => 'Pertanyaan uraian',
                    'Jenis_Soal' => 'teks',
                    'Skor' => null,
                    'Skor_Maksimal' => null,
                    'Bobot_Penilaian' => null,
                    'Jawaban_Teks' => 'Jawaban contoh untuk '.$b['kode'].'.',
                ];

                continue;
            }

            $detail[] = [
                'Id_Jenis_Soal' => $b['kode'],
                'Nama_Soal' => 'Kompetensi '.$b['kode'],
                'Jenis_Soal' => 'skala',
                'Skor' => mt_rand(2, 5),
                'Skor_Maksimal' => 5,
                'Bobot_Penilaian' => null,
            ];
        }

        mt_srand();

        return [
            'Kode_Karyawan' => $tugas->Kode_Karyawan_Penilai,
            'Id_Penjadwalan_Integrasi' => 0,
            'Id_Ujian_Token' => 'demo-'.$tugas->Id_PA_Assignment,
            'Id_Ujian_Nilai_Akhir' => 'demo-hasil-'.$tugas->Id_PA_Assignment,
            'Api_Version' => 'pa-demo',
            'Finished_At' => date('Y-m-d H:i:s'),
            'Detail' => $detail,
        ];
    }

    /**
     * Membuang siklus contoh beserta seluruh turunannya, dari daun ke akar.
     */
    private function hapus(): int
    {
        /* KEDUA kode contoh, bukan hanya yang pertama. Perintah pembersih yang tidak
           membersihkan segala yang ia sendiri buat meninggalkan siklus yatim berisi
           ratusan peserta — dan yatimnya tidak akan pernah ketahuan, karena orang yang
           menjalankan --hapus percaya semuanya sudah hilang. */
        $terbuang = 0;

        foreach ([self::KODE, self::KODE_SIAP] as $kode) {
            $terbuang += $this->hapusSatu($kode);
        }

        if ($terbuang === 0) {
            $this->info('Tidak ada siklus contoh untuk dibuang.');
        }

        return self::SUCCESS;
    }

    /**
     * Membuang satu siklus contoh. Mengembalikan 1 bila ada yang dibuang, 0 bila tidak.
     */
    private function hapusSatu(string $kode): int
    {
        $cycleId = DB::table('N_PA_Cycle')->where('Kode_Cycle', $kode)->value('Id_PA_Cycle');

        if ($cycleId === null) {
            return 0;
        }

        $this->line("Membuang siklus contoh {$kode}...");

        $cycleId = (int) $cycleId;

        DB::transaction(function () use ($cycleId) {
            $peserta = DB::table('N_PA_Participant')->where('PA_Cycle_Id', $cycleId)
                ->pluck('Id_PA_Participant');
            $hasil = DB::table('N_PA_Result')->whereIn('PA_Participant_Id', $peserta)
                ->pluck('Id_PA_Result');
            $tugas = DB::table('N_PA_Assignment')->whereIn('PA_Participant_Id', $peserta)
                ->pluck('Id_PA_Assignment');
            $integrasi = DB::table('N_PA_Assignment_Integration')->whereIn('PA_Assignment_Id', $tugas)
                ->pluck('Id_PA_Assignment_Integration');
            $payload = DB::table('N_PA_Assignment_Payload')
                ->whereIn('PA_Assignment_Integration_Id', $integrasi)
                ->pluck('Id_PA_Assignment_Payload');
            $paket = DB::table('N_PA_Cycle_Question_Package')->where('PA_Cycle_Id', $cycleId)
                ->pluck('Id_PA_Cycle_Question_Package');

            DB::table('N_PA_Assignment_Score')->whereIn('PA_Assignment_Payload_Id', $payload)->delete();
            DB::table('N_PA_Assignment_Payload')->whereIn('Id_PA_Assignment_Payload', $payload)->delete();
            DB::table('N_PA_Assignment_Integration')->whereIn('Id_PA_Assignment_Integration', $integrasi)->delete();

            DB::table('N_PA_Result_Note')->whereIn('PA_Result_Id', $hasil)->delete();
            DB::table('N_PA_Result_Competency')->whereIn('PA_Result_Id', $hasil)->delete();
            DB::table('N_PA_Result_Rater_Category')->whereIn('PA_Result_Id', $hasil)->delete();
            DB::table('N_PA_Calibration_History')->whereIn('PA_Result_Id', $hasil)->delete();
            DB::table('N_PA_Result')->whereIn('Id_PA_Result', $hasil)->delete();

            DB::table('N_PA_Assignment')->whereIn('Id_PA_Assignment', $tugas)->delete();
            DB::table('N_PA_Participant')->whereIn('Id_PA_Participant', $peserta)->delete();

            DB::table('N_PA_Cycle_Question_Package_Item')
                ->whereIn('PA_Cycle_Question_Package_Id', $paket)->delete();
            DB::table('N_PA_Cycle_Question_Package')->whereIn('Id_PA_Cycle_Question_Package', $paket)->delete();

            // Detail salinan menggantung pada induknya, jadi ia lebih dulu.
            $salinanInduk = DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
                ->where('PA_Cycle_Id', $cycleId)
                ->pluck('Id_PA_Cycle_Rater_Policy_Snapshot');

            DB::table('N_PA_Cycle_Rater_Policy_Detail_Snapshot')
                ->whereIn('PA_Cycle_Rater_Policy_Snapshot_Id', $salinanInduk)->delete();

            foreach ([
                'N_PA_Cycle_Rater_Policy_Snapshot',
                'N_PA_Cycle_Pillar_Weight_Snapshot',
                'N_PA_Cycle_Competency_Requirement_Snapshot',
                'N_PA_Cycle_Score_Range_Snapshot',
            ] as $tabel) {
                DB::table($tabel)->where('PA_Cycle_Id', $cycleId)->delete();
            }

            DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->delete();
        });

        $this->info("Siklus contoh #{$cycleId} dibuang beserta turunannya.");

        // 1, BUKAN self::SUCCESS. Konstanta itu bernilai 0, sehingga penghitung di
        // hapus() akan selalu nol dan pesannya selalu berkata tidak ada yang dibuang -
        // tepat ketika sesuatu baru saja dibuang.
        return 1;
    }
}
