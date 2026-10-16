<?php

namespace App\Console\Commands\KPI\PerformanceAppraisal;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/**
 * Memeriksa kelengkapan modul Performance Appraisal, lapis per lapis, fitur per fitur.
 *
 * KENAPA PERINTAH, BUKAN DOKUMEN
 *
 * Peta kerja yang ditulis tangan akan basi dalam hitungan hari: seseorang menambah
 * service, mencentang barisnya, lalu lupa mencentang yang lain. Yang dibutuhkan bukan
 * daftar melainkan PEMERIKSA — sesuatu yang menjawab "apa yang belum" dengan membaca
 * keadaan sebenarnya, bukan membaca catatan tentang keadaan.
 *
 * Ini lahir dari kejadian nyata: empat dari sepuluh operasi gerbang ternyata tidak punya
 * satu baris pun jalur kode, dan tak seorang pun menyadarinya sampai lapisannya dipetakan.
 * Termasuk `TERBITKAN` — tanpa mana modul penilaian tidak menghasilkan apa pun bagi
 * karyawan.
 *
 * YANG TIDAK BISA DIPERIKSA DI SINI
 *
 * Keselarasan ERD dan kelengkapan kamus data hidup di layanan luar dan hanya bisa dibaca
 * lewat MCP. Keduanya DILAPORKAN sebagai butir manual, bukan dianggap beres — butir yang
 * tak bisa diperiksa lalu dihilangkan dari daftar adalah cara paling halus melewatkan
 * pekerjaan.
 */
class PaAuditCommand extends Command
{
    protected $signature = 'pa:audit
        {--fitur= : Hanya periksa satu fitur (nomor atau potongan namanya)}
        {--lapis= : Hanya periksa satu lapisan, misalnya L2}
        {--sunyi : Hanya tampilkan yang BELUM selesai}';

    protected $description = 'Memeriksa kelengkapan modul Performance Appraisal per lapisan dan per fitur';

    /**
     * Fitur beserta jejak yang menandai tiap lapisannya selesai.
     *
     * `gerbang` adalah kunci pemeriksaan terpenting: operasi yang sudah ada di
     * PaCycleGate tetapi tak dipakai satu service pun berarti arsitekturnya sudah
     * memutuskan operasi itu ada, sementara jalurnya tidak pernah ditulis.
     *
     * @var array<int, array<string, mixed>>
     */
    private const FITUR = [
        1 => [
            'nama' => 'Siklus & penyiapan peserta',
            'gerbang' => [],
            'service' => ['PaCyclePreparationService'],
            'uji' => ['CyclePreparationTest'],
            'rute' => ['cycles/{cycleId}/siapkan', 'cycles/{cycleId}/kunci'],
            'kontrak' => ['2.2', '2.3'],
            'halaman' => ['PerformanceAppraisalPage'],
        ],
        2 => [
            'nama' => 'Aturan, salinan, resolver',
            'gerbang' => ['SUNTING_SALINAN'],
            'service' => ['PaParticipantRuleResolver', 'PaCycleGate', 'PaBobotGuard'],
            'uji' => ['ResolverPenggabunganTest', 'ResolverSatuPintuTest', 'CycleGateTest', 'BobotGuardTest'],
            'rute' => [],
            'kontrak' => [],
            'halaman' => [],
        ],
        3 => [
            'nama' => 'Override per peserta (K32)',
            'gerbang' => ['TULIS_OVERRIDE'],
            'service' => ['PaParticipantOverrideService'],
            'uji' => ['ParticipantOverrideTest'],
            'rute' => ['participants/{participantId}/override'],
            'kontrak' => [],
            'halaman' => [],
        ],
        4 => [
            'nama' => 'Penugasan penilai',
            'gerbang' => ['BUAT_PENUGASAN'],
            'service' => ['PaAssignmentBuilderService'],
            // Diuji ServiceFlowTest: bobot SELF seratus, dan penugasan manual tanpa alasan
            // ditolak sebelum menyentuh basis data.
            'uji' => ['ServiceFlowTest'],
            'rute' => ['participants/{participantId}/penugasan', 'assignments/{assignmentId}/batalkan'],
            'kontrak' => ['2.5', '2.6', '2.7'],
            'halaman' => ['PerformanceAppraisalPage'],
        ],
        5 => [
            /* NAMANYA DIPERSEMPIT pada 2 September 2026, dan itu koreksi, bukan kosmetik.
               Yang lengkap hanya arah MASUK: callback HC Learn diterima, dipetakan, dan
               dijaga uji. Arah KELUAR — mengirim penugasan ke HC Learn dan menyimpan
               tokennya di `N_PA_Assignment_Integration` — tidak punya satu pun penulis di
               jalur produksi; yang mengisinya hanya perintah data contoh.

               Sebelum ini fiturnya bernama "Integrasi HC Learn" dan tercentang penuh,
               sehingga audit melaporkan integrasi yang selesai padahal tidak seorang
               penilai pun bisa menerima tautan formulirnya. Audit yang menghitung
               setengah rantai sebagai satu rantai utuh lebih berbahaya daripada audit
               yang tidak ada, karena ia memberi rasa aman.

               Arah keluar menunggu kontrak HC Learn yang sampai hari ini BELUM DIKIRIM —
               lihat `docs/superpowers/specs/2026-09-01-kontrak-hc-learn-pa-siap-kirim.md`.
               Ia sengaja ditahan atas keputusan pemilik proyek, dan pemicu pengirimannya
               sudah menyala. */
            'nama' => 'Integrasi HC Learn — ARAH MASUK saja (callback)',
            'gerbang' => ['TERIMA_PAYLOAD'],
            'service' => ['PaPayloadMapperService'],
            // Diuji ServiceFlowTest: token historis memakai paket soal asalnya, dan payload
            // tak lengkap tidak menyelesaikan penugasan.
            'uji' => ['ServiceFlowTest'],
            'rute' => [],
            'kontrak' => [],
            'halaman' => [],
        ],
        6 => [
            'nama' => 'Perhitungan hasil',
            'gerbang' => ['HITUNG_HASIL'],
            'service' => ['PaResultCalculationService'],
            // Diuji ServiceFlowTest (perhitungan pertama menahan peserta tanpa KPI) dan
            // CyclePillarWeightTest (hasil terbit menolak dihitung ulang).
            'uji' => ['ServiceFlowTest'],
            'rute' => ['cycles/{cycleId}/hitung', 'participants/{participantId}/hitung'],
            'kontrak' => ['2.8'],
            'halaman' => ['PerformanceAppraisalPage'],
        ],
        7 => [
            'nama' => 'Kalibrasi',
            'gerbang' => ['KALIBRASI'],
            'service' => ['PaCalibrationService'],
            'uji' => ['CalibrationTest'],
            'rute' => ['results/{resultId}/kalibrasi'],
            'kontrak' => ['2.11'],
            'halaman' => ['PerformanceAppraisalPage'],
        ],
        8 => [
            'nama' => 'Publikasi hasil',
            'gerbang' => ['TERBITKAN'],
            'service' => ['PaResultPublishService'],
            'uji' => ['ResultPublishTest'],
            'rute' => ['cycles/{cycleId}/terbitkan'],
            'kontrak' => [],
            'halaman' => [],
        ],
        9 => [
            'nama' => 'Bobot pilar per siklus (K23)',
            'gerbang' => ['SUNTING_BOBOT_PILAR'],
            'service' => ['PaCyclePillarWeightService'],
            'uji' => ['CyclePillarWeightTest'],
            'rute' => ['cycles/{cycleId}/bobot-pilar'],
            'kontrak' => ['2.15'],
            'halaman' => [],
        ],
        10 => [
            'nama' => 'Sunting peserta manual',
            'gerbang' => ['SUNTING_PESERTA'],
            'service' => ['PaParticipantEditService'],
            'uji' => ['ParticipantEditTest'],
            'rute' => ['participants/{participantId}/status'],
            'kontrak' => ['2.16'],
            'halaman' => [],
        ],
        11 => [
            'nama' => 'Laporan, daftar peserta, HAV Map',
            'gerbang' => [],
            'service' => ['PaParticipantReadService'],
            'uji' => ['ParticipantReadTest'],
            'rute' => ['cycles/{cycleId}/participants', 'cycles/{cycleId}/hav-map',
                'participants/{participantId}/hasil'],
            'kontrak' => ['2.9', '2.10', '2.12'],
            'halaman' => ['PerformanceAppraisalPage'],
        ],
        12 => [
            'nama' => 'Master data admin',
            'gerbang' => ['PILIH_PAKET_SOAL'],
            'service' => ['PaMasterReadService', 'PaMasterWriteService'],
            'uji' => ['MasterDataTest'],
            'rute' => ['master/{master}', 'aturan-penilai/{policyId}/rincian'],
            'kontrak' => ['4.2', '4.6'],
            'halaman' => ['PaMasterKompetensiPage', 'PaMasterPitaNilaiPage'],
        ],

        /* Fitur 13-19 diturunkan dari MOCKUP, bukan dari operasi gerbang. Dua belas fitur
           pertama dipetakan dari PaCycleGate, dan itu melewatkan seluruh sisi peserta —
           empat layar yang justru dilihat 958 orang. Peta yang hanya bersumber dari kode
           hanya bisa menemukan yang sudah terpikir. */
        13 => [
            'nama' => 'Dashboard peserta',
            'gerbang' => [],
            'service' => ['PaPesertaService'],
            'uji' => ['PesertaTest'],
            'rute' => ['saya/ringkasan'],
            'kontrak' => ['3.1'],
            'halaman' => ['PerformanceAppraisalSayaPage'],
        ],
        14 => [
            'nama' => 'Daftar assignment peserta',
            'gerbang' => [],
            'service' => ['PaPesertaService'],
            'uji' => ['PesertaTest'],
            'rute' => ['saya/assignment'],
            'kontrak' => ['3.2'],
            'halaman' => ['PerformanceAppraisalSayaPage'],
        ],
        15 => [
            'nama' => 'Riwayat penilaian peserta',
            'gerbang' => [],
            'service' => ['PaPesertaService'],
            'uji' => ['PesertaTest'],
            'rute' => ['saya/riwayat'],
            'kontrak' => ['3.4'],
            'halaman' => ['PerformanceAppraisalSayaPage'],
        ],
        16 => [
            'nama' => 'Dashboard admin',
            'gerbang' => [],
            'service' => ['PaAdminDashboardService'],
            'uji' => ['AdminDashboardTest'],
            'rute' => ['cycles/{cycleId}/ringkasan'],
            'kontrak' => ['2.17'],
            'halaman' => [],
        ],
        17 => [
            'nama' => 'Monitoring progress',
            'gerbang' => [],
            'service' => ['PaAdminDashboardService'],
            'uji' => ['AdminDashboardTest'],
            'rute' => ['cycles/{cycleId}/monitoring'],
            'kontrak' => ['2.18'],
            'halaman' => [],
        ],
        18 => [
            'nama' => 'Distribusi performance',
            'gerbang' => [],
            'service' => ['PaDistribusiService'],
            'uji' => ['DistribusiTest'],
            'rute' => ['cycles/{cycleId}/distribusi'],
            'kontrak' => ['2.19'],
            'halaman' => [],
        ],
        19 => [
            'nama' => 'Riwayat penilaian admin',
            'gerbang' => [],
            'service' => ['PaRiwayatService'],
            'uji' => ['RiwayatTest'],
            /* BUKAN 'riwayat' saja. Token satu segmen itu cocok dengan alamat PESERTA
               'saya/riwayat', sehingga fitur admin ini sempat dilaporkan sudah punya rute
               padahal belum ada satu baris pun - persis jenis kabar yang membuat audit
               lebih berbahaya daripada tidak ada audit. */
            'rute' => ['performance-appraisal/riwayat'],
            'kontrak' => ['2.20'],
            'halaman' => [],
        ],

        /* Fitur 20 bukan layar. Ia lintas-potong: tanpa pemberitahuan, seluruh layar di
           atasnya berdiri pada asumsi bahwa orang tahu ia punya tugas — padahal tak ada
           yang pernah memberitahunya. Tidak punya rute, kontrak, maupun halaman sendiri
           karena ia menumpang topbar bersama seluruh HCIS. */
        20 => [
            'nama' => 'Pemberitahuan in-app (B9)',
            'gerbang' => [],
            'service' => ['PaNotificationService'],
            'uji' => ['NotificationTest'],
            'rute' => [],
            'kontrak' => [],
            'halaman' => [],
        ],

        /* Fitur 21 lahir dari permintaan jalur FE (A09), bukan dari mockup maupun gerbang.
           Daftar siklus sudah ada sejak awal; yang tidak pernah ada adalah membuat dan
           menyuntingnya — sehingga siklus hanya bisa lahir lewat perintah konsol. */
        21 => [
            'nama' => 'Setup siklus (A09)',
            'gerbang' => [],
            'service' => ['PaCycleSetupService'],
            'uji' => ['CycleSetupTest'],
            'rute' => ['performance-appraisal/cycles'],
            'kontrak' => ['2.21', '2.22', '2.23'],
            'halaman' => [],
        ],

        /* Fitur 22 lahir dari permintaan FE (A12). Tidak membawa tabel baru: riwayatnya
           sudah tersimpan sebagai baris nonaktif berikut sebabnya, dan tabel riwayat kedua
           akan menyalin data yang sama ke tempat lain — dua salinan riwayat pasti
           berselisih suatu hari. */
        22 => [
            'nama' => 'Lini masa audit (A12)',
            'gerbang' => [],
            'service' => ['PaAuditService'],
            'uji' => ['AuditTimelineTest'],
            'rute' => ['cycles/{cycleId}/audit'],
            'kontrak' => ['2.24'],
            'halaman' => [],
        ],

        /* Fitur 23 (L01–L02) sempat terhalang bukan oleh kode melainkan oleh satu
           keputusan: sampai sedalam apa leader boleh melihat nilai anggotanya. Diputuskan
           2 September 2026 — agregat plus rincian per kategori, dengan kategori berpengisi
           di bawah ambang disembunyikan.

           Sumber "siapa anggota saya" adalah `N_HRIS_Organization_Relationship` sejak
           6 September 2026 — tabel yang SAMA dengan yang dibaca mesin penugasan. Semula
           `HRIS_Karyawan_Team`, dipilih ketika tabel relasi masih berisi nol baris.
           Alasan itu kedaluwarsa, dan yang menuntut penggantian bukan jumlah barisnya
           melainkan bahwa dua tabel menjawab satu pertanyaan: 1.717 dari 1.721 penugasan
           kategori ATASAN punya penilai yang TIDAK melihat orangnya di halaman tim. */
        [
            'nama' => 'Dashboard & detail tim leader (L01–L02)',
            'gerbang' => [],
            'service' => ['PaLeaderService'],
            'uji' => ['LeaderTest'],
            'rute' => ['performance-appraisal/leader'],
            'kontrak' => ['5.1', '5.2'],
            /* Dipulihkan 6 September 2026 lewat DML-2026-105, sesudah halaman Leader
               disepakati sebagai DASHBOARD hasil terbit — peran ketiga yang tidak ada di
               mockup lama, dan yang karenanya sempat dicabut DML-2026-093 pada 3 September
               2026. */
            'halaman' => ['PerformanceAppraisalLeaderPage'],
        ],
    ];

    private const DIR_SERVICE = 'app/Services/KPI/PerformanceAppraisal';

    private const DIR_UJI = 'tests/Feature/KPI/PerformanceAppraisal';

    private const KONTRAK = 'docs/superpowers/specs/2026-09-01-kontrak-api-performance-appraisal.md';

    public function handle(): int
    {
        $this->info('AUDIT MODUL PERFORMANCE APPRAISAL');
        $this->line(str_repeat('=', 78));

        $this->lapisNol();
        $this->newLine();

        $hasil = $this->perFitur();
        $this->newLine();

        $this->ringkasan($hasil);

        return $hasil['belum'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * L0 berlaku untuk seluruh modul sekaligus, bukan per fitur: tabelnya saling
     * merujuk, dan memecahnya menghasilkan urutan ketergantungan yang berbelit.
     */
    private function lapisNol(): void
    {
        $this->line('<comment>L0 — Arsitektur database (seluruh modul)</comment>');

        $tabel = collect(DB::select(
            "SELECT name FROM sys.tables WHERE name LIKE 'N[_]PA[_]%' ORDER BY name"
        ))->pluck('name');

        $dok = collect(File::files(base_path('docs/database/tables/kpi')))
            ->map(fn ($f) => $f->getFilename())
            ->filter(fn ($f) => str_starts_with($f, 'n-pa-'))
            ->map(fn ($f) => strtolower(str_replace(['n-pa-', '.md'], '', $f)));

        $namaDok = $tabel->map(fn ($t) => strtolower(str_replace('_', '-', substr($t, 5))));

        $this->butir('Tabel punya dokumen', $namaDok->diff($dok)->isEmpty(),
            $namaDok->diff($dok)->implode(', '));
        $this->butir('Dokumen punya tabel', $dok->diff($namaDok)->isEmpty(),
            $dok->diff($namaDok)->implode(', '));

        $ledger = json_decode(File::get(base_path('database/ddl/status.json')), true)['changes'] ?? [];
        $pa = array_values(array_filter($ledger, fn ($c) => str_contains($c['change_id'], 'performance-appraisal')
            || str_contains($c['change_id'], 'organization-relationship')
            || str_contains($c['change_id'], 'pita-nilai')
            || str_contains($c['change_id'], 'menu-admin')));

        $tanpaDokumentasi = array_filter($pa, fn ($c) => empty($c['documentation_path'])
            || ! File::exists(base_path($c['documentation_path'])));
        $this->butir('Tiap change punya README', $tanpaDokumentasi === [],
            implode(', ', array_column($tanpaDokumentasi, 'change_id')));

        $statusBeda = array_filter($pa, fn ($c) => $c['status'] !== 'TRACKER_PENDING');
        $this->butir('Status change seragam TRACKER_PENDING', $statusBeda === [],
            implode(', ', array_column($statusBeda, 'change_id')));

        /* DML murni tidak punya forward.sql, jadi checksum-nya ada di dml_checksum.
           Menuntut forward_checksum pada semuanya membuat pemeriksa ini menuduh change
           yang sebenarnya lengkap - dan pemeriksa yang berbohong lebih berbahaya
           daripada tidak ada pemeriksa. */
        $checksumHilang = array_filter(
            $pa,
            fn ($c) => empty($c['forward_checksum_sha256']) && empty($c['dml_checksum_sha256'])
        );
        $this->butir('Tiap change punya checksum', $checksumHilang === [],
            implode(', ', array_column($checksumHilang, 'change_id')));

        $keProduksi = array_filter($pa, fn ($c) => $c['production'] !== 'PENDING');
        $this->butir('Sudah masuk production', $keProduksi !== [],
            count($pa).' change masih PENDING — pekerjaan pemilik proyek, bukan agent');

        // Keduanya hidup di layanan luar dan hanya terbaca lewat MCP. Dilaporkan sebagai
        // butir manual, bukan dihilangkan dari daftar.
        $this->butirManual('ERD selaras dengan DDL terakhir',
            'periksa lewat MCP erd_schema_read; per 1 Sep tertinggal DDL-076 r2, 082, 083 r2');
        $this->butirManual('Kamus data ERD lengkap',
            'periksa lewat MCP erd_dictionary_read; per 1 Sep 100% (540/540), 0 kolom kosong');
    }

    /**
     * @return array{selesai:int, belum:int, baris:list<array<string,mixed>>}
     */
    private function perFitur(): array
    {
        $saringFitur = $this->option('fitur');
        $saringLapis = $this->option('lapis') === null ? null : strtoupper((string) $this->option('lapis'));

        $selesai = 0;
        $belum = 0;
        $baris = [];

        foreach (self::FITUR as $no => $f) {
            if ($saringFitur !== null
                && (string) $no !== (string) $saringFitur
                && ! str_contains(strtolower($f['nama']), strtolower((string) $saringFitur))) {
                continue;
            }

            $lapis = [
                'L2' => $this->cekService($f),
                'L3' => $this->cekUji($f),
                'L4' => $this->cekRute($f),
                'L5' => $this->cekKontrak($f),
                'L6' => $this->cekHalaman($f),
            ];

            $gerbangYatim = $this->gerbangTanpaPemakai($f);

            $this->line(sprintf('<comment>%2d. %s</comment>', $no, $f['nama']));

            foreach ($lapis as $kode => $l) {
                if ($saringLapis !== null && $kode !== $saringLapis) {
                    continue;
                }

                if ($l['ok']) {
                    $selesai++;
                } else {
                    $belum++;
                }

                if ($l['ok'] && $this->option('sunyi')) {
                    continue;
                }

                $this->butir("  {$kode} {$l['label']}", $l['ok'], $l['catatan']);
            }

            if ($gerbangYatim !== []) {
                $this->butir('  ⚠  operasi gerbang tanpa pemakai', false, implode(', ', $gerbangYatim));
            }

            $baris[] = ['no' => $no, 'nama' => $f['nama'], 'lapis' => $lapis, 'gerbang_yatim' => $gerbangYatim];
        }

        return ['selesai' => $selesai, 'belum' => $belum, 'baris' => $baris];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{ok:bool, label:string, catatan:string}
     */
    private function cekService(array $f): array
    {
        $hilang = array_values(array_filter(
            $f['service'],
            fn ($s) => ! File::exists(base_path(self::DIR_SERVICE."/{$s}.php"))
        ));

        return [
            'ok' => $f['service'] !== [] && $hilang === [],
            'label' => 'service',
            'catatan' => $hilang === [] ? implode(', ', $f['service']) : 'belum ada: '.implode(', ', $hilang),
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{ok:bool, label:string, catatan:string}
     */
    private function cekUji(array $f): array
    {
        $hilang = array_values(array_filter(
            $f['uji'],
            fn ($u) => ! File::exists(base_path(self::DIR_UJI."/{$u}.php"))
        ));

        return [
            'ok' => $f['uji'] !== [] && $hilang === [],
            'label' => 'uji service',
            'catatan' => $hilang === [] ? implode(', ', $f['uji']) : 'belum ada: '.implode(', ', $hilang),
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{ok:bool, label:string, catatan:string}
     */
    private function cekRute(array $f): array
    {
        if ($f['rute'] === []) {
            return ['ok' => true, 'label' => 'rute', 'catatan' => 'tidak berlaku'];
        }

        /* Penjaga atas penjaga. Pencocokan di bawah memakai str_contains, sehingga token
           satu segmen seperti 'riwayat' akan cocok dengan alamat mana pun yang kebetulan
           memuatnya - dan fitur yang belum dibangun dilaporkan sudah punya rute. Token
           wajib memuat '/' supaya sekurang-kurangnya mengikat dua segmen. */
        foreach ($f['rute'] as $token) {
            if (! str_contains($token, '/')) {
                return [
                    'ok' => false,
                    'label' => 'rute',
                    'catatan' => "token '{$token}' terlalu longgar; pakai minimal dua segmen",
                ];
            }
        }

        $terdaftar = collect(Route::getRoutes())->map(fn ($r) => $r->uri())->all();

        $hilang = array_values(array_filter(
            $f['rute'],
            fn ($u) => ! collect($terdaftar)->contains(fn ($t) => str_contains($t, $u))
        ));

        return [
            'ok' => $hilang === [],
            'label' => 'rute',
            'catatan' => $hilang === [] ? count($f['rute']).' alamat' : 'belum ada: '.implode(', ', $hilang),
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{ok:bool, label:string, catatan:string}
     */
    private function cekKontrak(array $f): array
    {
        if ($f['kontrak'] === []) {
            return ['ok' => true, 'label' => 'kontrak API', 'catatan' => 'tidak berlaku'];
        }

        $isi = File::exists(base_path(self::KONTRAK)) ? File::get(base_path(self::KONTRAK)) : '';

        $hilang = array_values(array_filter(
            $f['kontrak'],
            fn ($s) => ! str_contains($isi, "### {$s} ") && ! str_contains($isi, "### §{$s}")
        ));

        return [
            'ok' => $hilang === [],
            'label' => 'kontrak API',
            'catatan' => $hilang === [] ? 'bagian '.implode(', ', $f['kontrak']) : 'belum ada: '.implode(', ', $hilang),
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{ok:bool, label:string, catatan:string}
     */
    private function cekHalaman(array $f): array
    {
        if ($f['halaman'] === []) {
            return ['ok' => true, 'label' => 'akses & menu', 'catatan' => 'tidak berlaku'];
        }

        $hilang = [];

        foreach ($f['halaman'] as $h) {
            $menu = DB::table('N_HRIS_Menus')->where('Jenis_page', $h)->first(['Is_Global_Menu']);
            $adaMap = DB::table('N_HRIS_Grup_Fitur_Map')->where('Jenis_page', $h)->exists();

            /* DUA jalur akses yang sah, bukan satu.
               Halaman bergerbang sempit memakai HRIS_Page_Access, yang penggunanya disebut
               satu per satu. Halaman yang dipakai SELURUH karyawan memakai
               Is_Global_Menu='Y', karena mendaftarkan seribu baris berarti setiap orang
               baru harus ditambahkan lagi. Pemeriksa yang hanya mengenal jalur pertama
               akan menyalakan merah selamanya pada halaman yang justru sudah benar - dan
               merah yang menetap adalah cara paling cepat membuat orang berhenti membaca
               audit ini sama sekali. */
            $global = $menu !== null
                && strtoupper(trim((string) $menu->Is_Global_Menu)) === 'Y';

            $adaAkses = $global || DB::table('HRIS_Page_Access')
                ->where('Jenis_page', $h)->where('Flag_Aktif', 'Y')->exists();

            if ($menu === null || ! $adaMap || ! $adaAkses) {
                $hilang[] = $h.' ('.($menu !== null ? '' : 'menu ').($adaMap ? '' : 'map ').($adaAkses ? '' : 'akses').')';
            }
        }

        return [
            'ok' => $hilang === [],
            'label' => 'akses & menu',
            'catatan' => $hilang === [] ? implode(', ', $f['halaman']) : 'belum lengkap: '.implode(', ', $hilang),
        ];
    }

    /**
     * Operasi gerbang yang tidak dipakai satu service pun.
     *
     * @param  array<string, mixed>  $f
     * @return list<string>
     */
    private function gerbangTanpaPemakai(array $f): array
    {
        $yatim = [];

        foreach ($f['gerbang'] as $op) {
            $ada = false;

            foreach (File::allFiles(base_path('app')) as $berkas) {
                if ($berkas->getFilename() === 'PaCycleGate.php') {
                    continue;
                }

                if (str_contains($berkas->getContents(), "PaCycleGate::{$op}")) {
                    $ada = true;
                    break;
                }
            }

            if (! $ada) {
                $yatim[] = $op;
            }
        }

        return $yatim;
    }

    /**
     * @param  array{selesai:int, belum:int, baris:list<array<string,mixed>>}  $hasil
     */
    private function ringkasan(array $hasil): void
    {
        $this->line(str_repeat('=', 78));

        $total = $hasil['selesai'] + $hasil['belum'];
        $persen = $total > 0 ? round($hasil['selesai'] / $total * 100) : 0;

        $this->info("RINGKASAN: {$hasil['selesai']} dari {$total} butir selesai ({$persen}%)");

        $yatim = [];

        foreach ($hasil['baris'] as $b) {
            foreach ($b['gerbang_yatim'] as $g) {
                $yatim[] = "{$g} (fitur {$b['no']})";
            }
        }

        if ($yatim !== []) {
            $this->newLine();
            $this->error('OPERASI GERBANG TANPA JALUR KODE: '.implode(', ', $yatim));
            $this->line('Arsitekturnya sudah memutuskan operasi itu ada, tetapi tidak ada');
            $this->line('satu baris pun yang menjalankannya.');
        }

        $this->newLine();
        $this->line('Peta lengkap: plans/kpi/pa-peta-kerja.md');
    }

    private function butir(string $label, bool $ok, string $catatan = ''): void
    {
        $tanda = $ok ? '<info>  OK  </info>' : '<error> BELUM</error>';
        $this->line(sprintf('%s %-46s %s', $tanda, $label, $catatan === '' ? '' : "<comment>{$catatan}</comment>"));
    }

    private function butirManual(string $label, string $catatan): void
    {
        $this->line(sprintf('<comment>MANUAL</comment> %-46s <comment>%s</comment>', $label, $catatan));
    }
}
