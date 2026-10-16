<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/* Konstanta "DIBATALKAN" dirujuk dari pembentuk penugasan, tidak disalin: dua ejaan yang
   suatu hari berselisih akan membuat panel ini menghitung penilai yang sudah dibatalkan
   sebagai penilai yang masih ada. */

/**
 * Kesiapan karyawan siklus di HC Learn — tahap yang berdiri SEBELUM penjadwalan.
 *
 * ── Kenapa berdiri sendiri ────────────────────────────────────────────────────
 *
 * `store` penjadwalan menolak 422 bila ada SATU kode karyawan yang belum dikenal HC Learn,
 * dan penolakannya membatalkan SELURUH kiriman — bukan barisnya saja. Tanpa tahap ini,
 * satu orang yang belum terdaftar menggagalkan penjadwalan ratusan penilai, dan admin baru
 * mengetahuinya pada saat paling mahal: ketika siklus sudah dikunci dan jendela pengisian
 * seharusnya dibuka.
 *
 * ── Populasinya siklus, bukan master karyawan ─────────────────────────────────
 *
 * Yang diperiksa hanya orang yang benar-benar terlibat: peserta DIIKUTKAN, dan penilai yang
 * dipilih aturan. Mengirim seluruh master karyawan aktif akan membuat daftar "belum
 * terdaftar" penuh nama yang tidak menghalangi apa pun, sehingga yang benar-benar
 * menghalangi tenggelam di antaranya. Ia juga mengirim data orang yang tak ada urusannya
 * dengan asesmen ini ke sistem luar.
 *
 * ── Penilai: yang sudah ditugaskan, ATAU yang akan dipilih ────────────────────
 *
 * Di Langkah 4 penugasan belum tentu terbentuk. Karena itu penilainya diproyeksikan dengan
 * aturan yang sama dengan pembentukan penugasan: relasi organisasi pada TANGGAL ACUAN
 * siklus, diurutkan `Prioritas` lalu `Kode_Karyawan`, diambil sebanyak `Min_Rater`. Urutan
 * itu wajib sama persis dengan `PaAssignmentBuilderService::calonDariRelasi()`; kalau
 * berbeda, yang diperiksa di sini bukan orang yang kelak benar-benar ditugaskan.
 *
 * ATURANNYA DIBACA LEWAT RESOLVER, bukan dari tabel salinan langsung (K32). Versi pertama
 * berkas ini menembak tabel salinan aturan penilai sendiri dan ditangkap
 * `ResolverSatuPintuTest` — dan tangkapan itu benar: peserta yang punya penyimpangan
 * penilai akan diproyeksikan memakai aturan standar levelnya. Kategori yang justru DICABUT
 * baginya tetap terkirim penilainya, dan kategori yang minimumnya ditambah kurang terkirim.
 * Keduanya membuat daftar "yang perlu didaftarkan" salah pada orang yang paling tidak
 * biasa — tepat orang yang paling mungkin belum ada di HC Learn.
 *
 * Penugasan yang SUDAH ada tetap dibaca dan digabung — sesudah Langkah 5 dijalankan,
 * merekalah kenyataannya, dan proyeksi tidak boleh menggantikan kenyataan.
 */
class PaHcLearnKaryawanSyncService
{
    public const PERAN_DINILAI = 'DINILAI';

    public const PERAN_PENILAI = 'PENILAI';

    public const SIAP = 'SIAP';

    public const PERLU_SINKRON = 'PERLU_SINKRON';

    public const PERLU_TINDAKAN = 'PERLU_TINDAKAN';

    /**
     * Belum ada seorang pun yang terlibat.
     *
     * BUKAN `SIAP`, dan pemisahan itu bukan kerapian. Panel ini terbuka di Langkah 4
     * SEBELUM penyiapan dijalankan, jadi populasi kosong adalah keadaan yang wajar di sana —
     * dan menjawab "siap dijadwalkan" untuknya adalah kebohongan yang tenang: admin membaca
     * lampu hijau pada siklus yang belum punya seorang pun, lalu meneruskan.
     */
    public const BELUM_ADA_PESERTA = 'BELUM_ADA_PESERTA';

    /**
     * Asal populasi — dan inilah yang membedakan panel Langkah 4 dari Langkah 5.
     *
     * Di Langkah 4 penugasan belum terbentuk, jadi hampir seluruh penilai masih PROYEKSI:
     * dugaan terbaik dari relasi organisasi. Di Langkah 5 penugasannya sudah ada, dan yang
     * diperiksa adalah orang yang SUNGGUH akan dikirimi pintu. Angka ini selalu ikut
     * dikembalikan supaya layar tidak perlu menebak sedang melihat yang mana — panel yang
     * menampilkan "259 siap" tanpa menyebut 259 itu dugaan atau kenyataan membuat admin
     * menutup Langkah 4 dengan perasaan sudah beres, padahal populasinya masih bisa berubah.
     *
     * @var array<string, int>
     */
    private const ASAL_KOSONG = [
        'dinilai' => 0,
        'penilai_terpasang' => 0,
        'penilai_proyeksi' => 0,
    ];

    /**
     * Kolom yang dikirim ke HC Learn saat mendaftarkan karyawan.
     *
     * DAFTAR TERTUTUP, dan itu keputusan. Dokumentasi mereka menyatakan kolom asing
     * diabaikan diam-diam, sehingga secara teknis seluruh baris `Karyawan` boleh dikirim —
     * tetapi "boleh dikirim" bukan alasan mengirim nomor rekening, NPWP, dan BPJS ke sistem
     * luar untuk membuat akun ujian. Yang dikirim hanya yang dipakai HC Learn mengenali dan
     * menempatkan orangnya.
     *
     * @var array<string, string> nama kolom HC Learn => kolom Karyawan
     */
    private const KOLOM_KIRIM = [
        'kode_perusahaan' => 'Kode_Perusahaan',
        'kode_karyawan' => 'Kode_Karyawan',
        'nama' => 'Nama',
        'nik' => 'NIK',
        'email' => 'Email',
        'no_hp' => 'HP',
        'alamat' => 'Alamat',
        'tanggal_lahir' => 'Tgl_Lahir',
        'jenis_kelamin' => 'Jenis_Kelamin',
        'agama' => 'Agama',
        'pendidikan' => 'Pendidikan_Terakhir',
        'kode_jabatan' => 'Kode_Jabatan',
        'kode_divisi' => 'Kode_Divisi',
        'lokasi' => 'Lokasi',
        'status_kepegawaian' => 'Status_Kepegawaian',
        'status_kontrak' => 'status_kontrak',
        'tanggal_masuk' => 'Tgl_Masuk',
        'aktif' => 'Aktif',
    ];

    public function __construct(
        private readonly PaHcLearnJadwalContract $kontrak,

        /* SATU-SATUNYA pintu ke aturan penilai per peserta (K32). Membaca tabel salinannya
           langsung akan melewati penyimpangan per orang — lihat catatan kelas di atas. */
        private readonly PaParticipantRuleResolver $resolver,
    ) {}

    /**
     * Siapa yang terlibat, dan siapa yang belum dikenal HC Learn.
     *
     * @return array{
     *     cycle_id:int, status:string, ringkasan:array<string,int>,
     *     baris:list<array<string,mixed>>, catatan:string
     * }
     */
    public function kesiapan(int $cycleId): array
    {
        $asal = self::ASAL_KOSONG;
        $terlibat = $this->terlibat($cycleId, $asal);

        if ($terlibat === []) {
            return [
                'cycle_id' => $cycleId,
                'status' => self::BELUM_ADA_PESERTA,
                'ringkasan' => ['terlibat' => 0, 'siap' => 0, 'belum_terdaftar' => 0, 'tidak_siap' => 0] + $asal,
                'baris' => [],
                'catatan' => 'Belum ada peserta pada siklus ini, jadi belum ada yang bisa '
                    .'diperiksa ke HC Learn. Jalankan penyiapan lebih dulu, lalu periksa ulang.',
            ];
        }

        $periksa = $this->kontrak->periksaKaryawan(array_keys($terlibat));
        $baris = [];
        $ringkasan = ['terlibat' => count($terlibat), 'siap' => 0, 'belum_terdaftar' => 0, 'tidak_siap' => 0] + $asal;

        foreach ($terlibat as $kode => $orang) {
            /* Kode yang TIDAK dijawab HC Learn diperlakukan seperti belum terdaftar, bukan
               dianggap siap. Menganggapnya siap berarti melewatkannya ke `store`, dan di
               sanalah ia membatalkan seluruh kiriman. */
            $hasil = $periksa[$kode] ?? [
                'ada' => false,
                'siap' => false,
                'alasan' => 'BELUM_TERDAFTAR',
                'keterangan' => 'HC Learn tidak menjawab status kode ini.',
            ];

            $baris[] = $orang + [
                'ada' => $hasil['ada'],
                'siap' => $hasil['siap'],
                'alasan' => $hasil['alasan'],
                'keterangan' => $hasil['keterangan'],

                /* Hanya yang BELUM ADA yang bisa dibereskan tombol sinkron. Yang terdaftar
                   tetapi nonaktif atau sudah resign butuh keputusan manusia — mendaftarkan
                   ulang tidak menghidupkannya, dan tombol yang menjanjikan sebaliknya
                   membuat admin menekannya berkali-kali tanpa hasil. */
                'dapat_disinkron' => ! $hasil['ada'],
            ];

            if ($hasil['siap']) {
                $ringkasan['siap']++;
            } elseif (! $hasil['ada']) {
                $ringkasan['belum_terdaftar']++;
            } else {
                $ringkasan['tidak_siap']++;
            }
        }

        /* Yang menghalangi naik ke atas: belum terdaftar dulu (bisa dibereskan sekarang),
           lalu yang tidak siap (butuh keputusan), baru yang siap. Daftar berurut abjad
           membuat admin menggulir ratusan baris hijau untuk menemukan satu yang merah. */
        usort($baris, function (array $a, array $b) {
            $bobot = fn (array $x) => $x['siap'] ? 2 : ($x['ada'] ? 1 : 0);

            return [$bobot($a), $a['kode_karyawan']] <=> [$bobot($b), $b['kode_karyawan']];
        });

        $baris = $this->lengkapiTanggungan($cycleId, $baris);
        $ringkasan['menghalangi'] = 0;

        foreach ($baris as $i => $b) {
            $baris[$i]['menghalangi'] = ! $b['siap'] && $this->menghalangi($b);

            if ($baris[$i]['menghalangi']) {
                $ringkasan['menghalangi']++;
            }
        }

        return [
            'cycle_id' => $cycleId,
            'status' => $this->status($ringkasan),
            'ringkasan' => $ringkasan,
            'baris' => $baris,
            'catatan' => $this->catatan($ringkasan),
        ];
    }

    /**
     * Menyebut SIAPA yang tergantung pada orang yang menghalangi.
     *
     * "A1 sudah resign" memberi tahu admin ada masalah, bukan di mana masalahnya. Yang harus
     * dibereskan bukan A1 — ia tidak bisa dibereskan — melainkan penugasan yang menunjuk
     * padanya: peserta mana yang kehilangan penilai, di kategori apa, dan apakah membatalkannya
     * saja sudah cukup. Tanpa daftar ini admin menelusuri sendiri 259 orang untuk menemukan
     * satu nama, dan itu pekerjaan yang seluruh panel ini justru dibuat untuk menghapus.
     *
     * `cukup_dibatalkan` adalah jawaban atas pertanyaan yang paling mudah terlewat: kategori
     * yang penilainya TEPAT sebanyak minimumnya akan jatuh di bawah minimum begitu satu
     * dibatalkan, jadi yang dibutuhkan penggantian — bukan pembatalan. Pembatalan tanpa
     * pengganti tidak memunculkan galat apa pun; yang terjadi hanya kompetensi itu dihitung
     * dari penilai yang lebih sedikit, diam-diam, dengan bobot penuh.
     *
     * HANYA baris yang MENGHALANGI yang ditelusuri — biasanya nol sampai beberapa orang — jadi
     * tiga kueri ini tidak tumbuh bersama jumlah peserta.
     *
     * @param  list<array<string, mixed>>  $baris
     * @return list<array<string, mixed>>
     */
    private function lengkapiTanggungan(int $cycleId, array $baris): array
    {
        $kode = [];

        /* KUNCINYA SELALU ADA, walau kosong. Bentuk respons yang berubah-ubah tergantung cabang
           mana yang diambil adalah jebakan bagi setiap pembacanya: layar membaca `undefined`
           pada siklus yang bersih, dan uji yang menegaskan bentuknya gagal dengan "Undefined
           array key" — kegagalan yang tidak mengatakan apa pun tentang perilakunya. */
        foreach ($baris as $i => $b) {
            $baris[$i]['tanggungan'] = [];

            if (! $b['siap']) {
                $kode[] = $b['kode_karyawan'];
            }
        }

        if ($kode === []) {
            return $baris;
        }

        $tugas = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->leftJoin('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Flag_Aktif', 'Y')
            ->where('a.Flag_Aktif', 'Y')
            ->where('a.Status_Penugasan', '<>', PaAssignmentBuilderService::STATUS_DIBATALKAN)
            ->whereIn('a.Kode_Karyawan_Penilai', $kode)
            ->orderBy('p.Kode_Karyawan')
            ->get([
                'a.Id_PA_Assignment', 'a.Kode_Karyawan_Penilai', 'a.PA_Rater_Category_Id',
                'c.Kode_Kategori', 'c.Nama_Kategori',
                'p.Id_PA_Participant', 'p.Kode_Karyawan', 'p.Nama_Karyawan_Saat_Cycle',
                'p.ID_Level_Saat_Cycle', 'p.Nama_Level_Saat_Cycle', 'p.Nama_Divisi_Saat_Cycle',
            ]);

        if ($tugas->isEmpty()) {
            return $baris;
        }

        $pesertaId = $tugas->pluck('Id_PA_Participant')->unique()->values()->all();

        /* Berapa penilai AKTIF yang dipegang tiap (peserta, kategori) sekarang. */
        $cacah = [];

        foreach (DB::table('N_PA_Assignment')
            ->whereIn('PA_Participant_Id', $pesertaId)
            ->where('Flag_Aktif', 'Y')
            ->where('Status_Penugasan', '<>', PaAssignmentBuilderService::STATUS_DIBATALKAN)
            ->groupBy('PA_Participant_Id', 'PA_Rater_Category_Id')
            ->get(['PA_Participant_Id', 'PA_Rater_Category_Id', DB::raw('COUNT(*) as jumlah')]) as $r) {
            $cacah[$r->PA_Participant_Id.'|'.$r->PA_Rater_Category_Id] = (int) $r->jumlah;
        }

        /* Minimumnya dibaca LEWAT RESOLVER, bukan dari tabel salinan aturan langsung (K32).
           |
           | Versi pertama menembak tabel salinan aturan penilai sendiri dan ditangkap
           | `ResolverSatuPintuTest` — tangkapan yang benar. Peserta yang minimumnya
           | DISIMPANGKAN per orang akan dinilai dengan minimum standar levelnya, sehingga
           | kalimat "boleh dibatalkan, sisanya masih memenuhi minimal" bisa keliru tepat pada
           | peserta yang paling tidak biasa — dan kekeliruannya tidak memunculkan galat apa
           | pun, hanya kompetensi yang dihitung dari penilai yang kurang. */
        $this->resolver->muatOverrideSiklus($cycleId);
        $this->resolver->muatPesertaBanyak($pesertaId);

        $minimum = [];

        foreach ($pesertaId as $id) {
            foreach ($this->resolver->raterRules($id) as $a) {
                $minimum[$id.'|'.($a['kategori_id'] ?? 0)] = [
                    'min' => (int) ($a['min_rater'] ?? 1),
                    'bobot' => $a['bobot_persen'] ?? null,
                ];
            }
        }

        $perPenilai = [];

        foreach ($tugas as $t) {
            $kunciCacah = $t->Id_PA_Participant.'|'.$t->PA_Rater_Category_Id;
            $kunciMin = $kunciCacah;
            $sekarang = $cacah[$kunciCacah] ?? 1;
            $min = $minimum[$kunciMin]['min'] ?? 1;

            $perPenilai[trim((string) $t->Kode_Karyawan_Penilai)][] = [
                'assignment_id' => (int) $t->Id_PA_Assignment,

                /* Kuncinya memakai nama yang DIMINTA dialog Kelola Rater, supaya barisnya bisa
                   diteruskan apa adanya tanpa pemetaan kedua yang bisa salah tebak. */
                'participant_id' => (int) $t->Id_PA_Participant,
                'kode_karyawan' => trim((string) $t->Kode_Karyawan),
                'nama' => (string) $t->Nama_Karyawan_Saat_Cycle,
                'id_level' => (int) $t->ID_Level_Saat_Cycle,
                'nama_level' => (string) $t->Nama_Level_Saat_Cycle,
                'divisi' => (string) $t->Nama_Divisi_Saat_Cycle,

                'kategori' => (string) ($t->Kode_Kategori ?? ''),
                'nama_kategori' => (string) ($t->Nama_Kategori ?? ''),
                'penilai_sekarang' => $sekarang,
                'min_rater' => $min,
                'bobot_kategori' => $minimum[$kunciMin]['bobot'] ?? null,
                'cukup_dibatalkan' => ($sekarang - 1) >= $min,
            ];
        }

        foreach ($baris as $i => $b) {
            $baris[$i]['tanggungan'] = $perPenilai[$b['kode_karyawan']] ?? [];
        }

        return $baris;
    }

    /**
     * MENGHALANGI atau sekadar TERCATAT — dan bedanya menentukan bisa-tidaknya siklus dikunci.
     *
     * Orang yang ditolak HC Learn hanya menghalangi bila ia benar-benar akan dikirimi pintu
     * atau benar-benar sedang dinilai. Penilai yang penugasannya SUDAH dicabut tidak menghalangi
     * apa pun: penjadwalan menyaringnya di muka dan tidak pernah mengirimkan namanya.
     *
     * Tanpa pembedaan ini, membereskannya tidak pernah menyelesaikan apa pun — dan itu terjadi:
     * admin mencabut penugasan A1, panel ini berkata "tidak ada yang perlu dibereskan", tetapi
     * penguncian tetap tertahan atas nama orang yang sama. Sebabnya proyeksi: selama ia masih
     * `Aktif='Y'` di HCIS, aturan penilai tetap memilihnya sebagai CALON, jadi ia terus muncul
     * di populasi yang diperiksa. Yang salah bukan kehadirannya di daftar — itu jujur — melainkan
     * menganggap kehadiran itu sebagai penghalang.
     *
     * @param  array<string, mixed>  $b
     */
    private function menghalangi(array $b): bool
    {
        if (($b['tanggungan'] ?? []) !== []) {
            return true;
        }

        /* Peserta yang DIIKUTKAN tetap menghalangi walau tak punya penugasan: dialah yang
           dinilai, dan tindakannya jelas — kecualikan, atau minta HC Learn mengaktifkannya. */
        return in_array(self::PERAN_DINILAI, $b['peran'] ?? [], true);
    }

    /**
     * Mendaftarkan yang belum dikenal, lalu memeriksa ulang.
     *
     * Pemeriksaan ulangnya bukan kemewahan: `add-new` bisa menjawab 207 — sebagian
     * tersimpan, sebagian ditolak — dan yang menentukan boleh-tidaknya menjadwalkan adalah
     * keadaan SESUDAH pendaftaran, bukan laporan pendaftarannya.
     *
     * @return array{sinkron:array<string,mixed>, kesiapan:array<string,mixed>}
     */
    public function sinkronkan(int $cycleId): array
    {
        $sebelum = $this->kesiapan($cycleId);
        $perlu = array_values(array_filter(
            $sebelum['baris'],
            fn (array $b) => $b['dapat_disinkron'],
        ));

        if ($perlu === []) {
            throw new RuntimeException(
                'Tidak ada karyawan yang perlu didaftarkan ke HC Learn. '
                .($sebelum['ringkasan']['tidak_siap'] > 0
                    ? 'Yang tersisa sudah terdaftar tetapi nonaktif atau sudah resign; '
                        .'keikutsertaannya perlu ditinjau, bukan didaftarkan ulang.'
                    : 'Seluruh yang terlibat sudah siap dipakai.')
            );
        }

        $muatan = $this->muatanPendaftaran(array_map(
            fn (array $b) => [$b['kode_perusahaan'], $b['kode_karyawan']],
            $perlu,
        ));

        return [
            'sinkron' => $this->kontrak->daftarkanKaryawan($muatan),
            'kesiapan' => $this->kesiapan($cycleId),
        ];
    }

    /**
     * Memastikan orang-orang TERTENTU dikenal HC Learn — jalur revisi sesudah dikunci.
     *
     * Revisi membentuk penilai baru yang belum pernah diperiksa: pemeriksaan Langkah 5 sudah
     * lewat. Yang belum terdaftar didaftarkan di sini; yang terdaftar tetapi tidak siap
     * (nonaktif, resign di sisi HC Learn) dilaporkan — keduanya SEBELUM jadwalnya dikirim,
     * karena penjadwalan atas kode yang tidak dikenal hanya akan berakhir GAGAL.
     *
     * Sengaja tidak memakai `kesiapan()`: itu memeriksa seluruh siklus termasuk proyeksi,
     * sedangkan yang perlu di sini hanya segelintir orang.
     *
     * @param  list<array{0:string, 1:string}>  $pasangan  [kode_perusahaan, kode_karyawan]
     * @return array{diperiksa:int, didaftarkan:int, tidak_siap:list<array{kode_karyawan:string, keterangan:string}>}
     */
    public function pastikanTerdaftar(array $pasangan): array
    {
        $peta = [];
        foreach ($pasangan as [$kp, $kk]) {
            $kk = trim((string) $kk);
            if ($kk !== '') {
                $peta[$kk] = [(string) $kp, $kk];
            }
        }

        if ($peta === []) {
            return ['diperiksa' => 0, 'didaftarkan' => 0, 'tidak_siap' => []];
        }

        $periksa = $this->kontrak->periksaKaryawan(array_keys($peta));
        $belum = array_values(array_filter($peta, fn ($p, $k) => ! ($periksa[$k]['ada'] ?? false), ARRAY_FILTER_USE_BOTH));
        $didaftarkan = 0;

        if ($belum !== []) {
            $hasil = $this->kontrak->daftarkanKaryawan($this->muatanPendaftaran($belum));
            $didaftarkan = (int) ($hasil['ringkasan']['tersimpan'] ?? 0);
            /* Diperiksa ULANG: `add-new` bisa sukses sebagian (207). */
            $periksa = $this->kontrak->periksaKaryawan(array_column($belum, 1)) + $periksa;
        }

        $tidakSiap = [];
        foreach (array_keys($peta) as $kk) {
            if (! ($periksa[$kk]['siap'] ?? false)) {
                $tidakSiap[] = [
                    'kode_karyawan' => $kk,
                    'keterangan' => (string) (($periksa[$kk]['keterangan'] ?? '') ?: 'HC Learn tidak menjawab status kode ini.'),
                ];
            }
        }

        return ['diperiksa' => count($peta), 'didaftarkan' => $didaftarkan, 'tidak_siap' => $tidakSiap];
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    /**
     * Peserta dan penilai siklus, berkunci kode karyawan.
     *
     * @return array<string, array{kode_perusahaan:string, kode_karyawan:string, nama:string, peran:list<string>}>
     */
    private function terlibat(int $cycleId, ?array &$asal = null): array
    {
        $siklus = DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->first(['Id_PA_Cycle', 'Tanggal_Acuan_Organisasi', 'Periode_Dinilai_Sampai']);

        if ($siklus === null) {
            throw new RuntimeException("Siklus {$cycleId} tidak ada.");
        }

        $orang = [];
        $peserta = $this->peserta($cycleId);
        $asal = self::ASAL_KOSONG;

        foreach ($peserta as $p) {
            $this->kumpulkan($orang, $p->Kode_Perusahaan, $p->Kode_Karyawan, $p->Nama_Karyawan_Saat_Cycle, self::PERAN_DINILAI);
        }

        $asal['dinilai'] = count($orang);

        foreach ($this->penilaiTerpasang($cycleId) as $a) {
            $this->kumpulkan($orang, $a->Kode_Perusahaan_Penilai, $a->Kode_Karyawan_Penilai, $a->Nama_Penilai_Saat_Cycle, self::PERAN_PENILAI);
            $asal['penilai_terpasang']++;
        }

        /* Dihitung sebagai ORANG BARU, bukan sebagai banyaknya calon. Proyeksi yang jatuh pada
           orang yang penugasannya sudah ada bukan dugaan lagi — menghitungnya di sini membuat
           angka "masih proyeksi" membengkak justru ketika Langkah 5 sudah selesai, tepat
           kebalikan dari yang ingin diketahui. */
        foreach ($this->penilaiDiproyeksikan($cycleId, $peserta, $this->acuan($siklus)) as $c) {
            $sebelum = count($orang);
            $this->kumpulkan($orang, $c['kode_perusahaan'], $c['kode_karyawan'], $c['nama'], self::PERAN_PENILAI);

            if (count($orang) > $sebelum) {
                $asal['penilai_proyeksi']++;
            }
        }

        return $orang;
    }

    /** @param array<string, array<string, mixed>> $orang */
    private function kumpulkan(array &$orang, ?string $kodePerusahaan, ?string $kodeKaryawan, ?string $nama, string $peran): void
    {
        $kode = trim((string) $kodeKaryawan);

        if ($kode === '') {
            return;
        }

        /* Dikunci KODE KARYAWAN saja, bukan pasangan perusahaan+karyawan, karena itulah
           satu-satunya kunci yang dipakai HC Learn — dokumentasi mereka menyatakan NIK
           tidak dipakai justru karena tidak unik. Orang yang sama muncul sebagai peserta
           DAN penilai cukup diperiksa sekali; perannya digabung supaya layar tetap bisa
           mengatakan kenapa ia terlibat. */
        if (! isset($orang[$kode])) {
            $orang[$kode] = [
                'kode_perusahaan' => (string) $kodePerusahaan,
                'kode_karyawan' => $kode,
                'nama' => (string) $nama,
                'peran' => [],
            ];
        }

        if (! in_array($peran, $orang[$kode]['peran'], true)) {
            $orang[$kode]['peran'][] = $peran;
        }
    }

    private function acuan(object $siklus): string
    {
        $tgl = $siklus->Tanggal_Acuan_Organisasi ?? $siklus->Periode_Dinilai_Sampai;

        return $tgl === null
            ? FormatTanggalHelper::getCurrentTime()->format('Y-m-d')
            : substr((string) $tgl, 0, 10);
    }

    private function peserta(int $cycleId)
    {
        return DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->get(['Id_PA_Participant', 'Kode_Perusahaan', 'Kode_Karyawan', 'Nama_Karyawan_Saat_Cycle']);
    }

    private function penilaiTerpasang(int $cycleId)
    {
        return DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Flag_Aktif', 'Y')
            ->where('a.Flag_Aktif', 'Y')
            ->where('a.Status_Penugasan', '<>', 'DIBATALKAN')
            ->distinct()
            ->orderBy('a.Kode_Karyawan_Penilai')
            ->get(['a.Kode_Perusahaan_Penilai', 'a.Kode_Karyawan_Penilai', 'a.Nama_Penilai_Saat_Cycle']);
    }

    /**
     * Penilai yang AKAN dipilih pembentukan penugasan.
     *
     * DUA SUMBER, DAN PEMBAGIANNYA DISENGAJA:
     *
     *   - ATURAN dibaca `PaParticipantRuleResolver`, satu-satunya pintu yang tahu apakah
     *     peserta ini punya penyimpangan (K32). Aturannya dimemokan per peserta, dan
     *     pemuat massalnya dipanggil lebih dulu supaya seluruh peserta terjawab dari satu
     *     kueri, bukan satu kueri per orang.
     *   - RELASI ORGANISASI dibaca di sini, berkelompok seratus peserta sekali jalan.
     *     Penyaring dan urutannya disalin persis dari `calonDariRelasi()`; kalau berbeda,
     *     yang diperiksa di sini bukan orang yang kelak benar-benar ditugaskan.
     *
     * Kategori tanpa jenis relasi (SELF) sengaja tidak ikut: penilainya pesertanya sendiri,
     * yang sudah terhitung sebagai DINILAI.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $peserta
     * @return list<array{kode_perusahaan:string, kode_karyawan:string, nama:string}>
     */
    private function penilaiDiproyeksikan(int $cycleId, $peserta, string $acuan): array
    {
        if ($peserta->isEmpty()) {
            return [];
        }

        $ids = $peserta->pluck('Id_PA_Participant')->map(fn ($x) => (int) $x)->all();

        $this->resolver->muatOverrideSiklus($cycleId);
        $this->resolver->muatPesertaBanyak($ids);

        /* Penugasan yang SUDAH ada, dan yang pernah DICABUT, per "peserta|kategori".

           Proyeksi hanya mengisi KEKURANGAN, persis seperti pembentukan penugasan: kategori yang
           sudah memenuhi minimum tidak diproyeksikan, orang yang sudah terpasang tidak dihitung
           dua kali, dan orang yang dicabut admin tidak diproyeksikan kembali. Tanpa ini, penilai
           yang baru dicabut (mis. karena resign) tetap muncul sebagai "1 penilai masih proyeksi"
           karena ia masih calon pertama di relasi organisasi — defect UAT 8 Okt. */
        [$terpasang, $dicabut] = $this->penugasanPerKategori($ids);

        /* Berapa penilai yang dibutuhkan tiap peserta, per jenis relasi. */
        $butuh = [];
        $tipe = [];
        $lewati = [];

        foreach ($peserta as $p) {
            foreach ($this->resolver->raterRules((int) $p->Id_PA_Participant) as $a) {
                $tipeId = $a['relationship_type_id'] ?? null;
                $min = (int) ($a['min_rater'] ?? 0);

                if ($tipeId === null || $min < 1) {
                    continue;
                }

                $kunci = (int) $p->Id_PA_Participant;
                $kunciKategori = $kunci.'|'.(int) ($a['kategori_id'] ?? 0);
                $sudah = $terpasang[$kunciKategori] ?? [];
                $kurang = $min - count($sudah);

                if ($kurang < 1) {
                    continue;
                }

                $butuh[$kunci][(int) $tipeId] = max($butuh[$kunci][(int) $tipeId] ?? 0, $kurang);
                $lewati[$kunci.'|'.(int) $tipeId] = $sudah + ($dicabut[$kunciKategori] ?? []);
                $tipe[(int) $tipeId] = true;
            }
        }

        if ($butuh === []) {
            return [];
        }

        $calon = $this->calonRelasi($peserta, array_keys($tipe), $acuan);
        $hasil = [];

        foreach ($butuh as $participantId => $perTipe) {
            foreach ($perTipe as $tipeId => $min) {
                /* Dipotong sebanyak `Min_Rater`, sama seperti pembentukan penugasan.
                   Mengambil SELURUH calon akan memasukkan orang yang tidak akan pernah
                   ditugaskan ke dalam daftar "harus didaftarkan ke HC Learn". */
                $abaikan = $lewati[$participantId.'|'.$tipeId] ?? [];
                $tersedia = array_values(array_filter(
                    $calon[$participantId.'|'.$tipeId] ?? [],
                    fn ($c) => ! isset($abaikan[trim((string) $c['kode_karyawan'])]),
                ));

                foreach (array_slice($tersedia, 0, $min) as $c) {
                    $hasil[$c['kode_karyawan']] = $c;
                }
            }
        }

        return array_values($hasil);
    }

    /**
     * Penilai aktif dan penilai yang dicabut, masing-masing berkunci "peserta|kategori" lalu
     * kode karyawan. Yang dicabut tetapi sudah dipasang lagi hanya tercatat sebagai aktif.
     *
     * @param  list<int>  $participantIds
     * @return array{0: array<string, array<string, true>>, 1: array<string, array<string, true>>}
     */
    private function penugasanPerKategori(array $participantIds): array
    {
        $aktif = [];
        $dicabut = [];

        foreach (array_chunk($participantIds, 1000) as $potongan) {
            $baris = DB::table('N_PA_Assignment')
                ->whereIn('PA_Participant_Id', $potongan)
                ->where(function ($w) {
                    $w->where('Flag_Aktif', 'Y')->orWhere('Status_Penugasan', 'DIBATALKAN');
                })
                ->get(['PA_Participant_Id', 'PA_Rater_Category_Id', 'Kode_Karyawan_Penilai', 'Flag_Aktif', 'Status_Penugasan']);

            foreach ($baris as $b) {
                $kunci = (int) $b->PA_Participant_Id.'|'.(int) $b->PA_Rater_Category_Id;
                $kode = trim((string) $b->Kode_Karyawan_Penilai);

                if ($b->Flag_Aktif === 'Y' && $b->Status_Penugasan !== 'DIBATALKAN') {
                    $aktif[$kunci][$kode] = true;
                } else {
                    $dicabut[$kunci][$kode] = true;
                }
            }
        }

        return [$aktif, $dicabut];
    }

    /**
     * Calon penilai dari relasi organisasi, berkunci "peserta|tipe".
     *
     * Penyaring, urutan, dan syarat tanggalnya disalin dari
     * `PaAssignmentBuilderService::calonDariRelasi()`. Yang berbeda hanya bentuk
     * pemanggilannya: seratus peserta sekali jalan, bukan satu peserta satu kueri.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $peserta
     * @param  list<int>  $tipeIds
     * @return array<string, list<array{kode_perusahaan:string, kode_karyawan:string, nama:string}>>
     */
    private function calonRelasi($peserta, array $tipeIds, string $acuan): array
    {
        if ($tipeIds === []) {
            return [];
        }

        $indeks = [];

        foreach ($peserta as $p) {
            $indeks[$p->Kode_Perusahaan.'|'.$p->Kode_Karyawan] = (int) $p->Id_PA_Participant;
        }

        $calon = [];

        foreach (array_chunk($peserta->all(), 100) as $potongan) {
            $baris = DB::table('N_HRIS_Organization_Relationship as r')
                ->join('Karyawan as k', function ($j) {
                    $j->on('k.Kode_Perusahaan', '=', 'r.Kode_Perusahaan_Terkait')
                        ->on('k.Kode_Karyawan', '=', 'r.Kode_Karyawan_Terkait');
                })
                ->where(function ($w) use ($potongan) {
                    foreach ($potongan as $p) {
                        $w->orWhere(function ($x) use ($p) {
                            $x->where('r.Kode_Perusahaan_Subjek', $p->Kode_Perusahaan)
                                ->where('r.Kode_Karyawan_Subjek', $p->Kode_Karyawan);
                        });
                    }
                })
                ->whereIn('r.Id_Organization_Relationship_Type', $tipeIds)
                ->where('r.Flag_Aktif', 'Y')
                ->whereNull('r.Cancelled_At')
                ->where('r.Berlaku_Dari', '<=', $acuan)
                ->where(function ($q) use ($acuan) {
                    $q->whereNull('r.Berlaku_Sampai')->orWhere('r.Berlaku_Sampai', '>=', $acuan);
                })
                ->where('k.Aktif', 'Y')
                ->orderBy('r.Prioritas')
                ->orderBy('k.Kode_Karyawan')
                ->get([
                    'r.Kode_Perusahaan_Subjek', 'r.Kode_Karyawan_Subjek',
                    'r.Id_Organization_Relationship_Type',
                    'r.Kode_Perusahaan_Terkait', 'r.Kode_Karyawan_Terkait', 'k.Nama',
                ]);

            foreach ($baris as $r) {
                $pid = $indeks[$r->Kode_Perusahaan_Subjek.'|'.$r->Kode_Karyawan_Subjek] ?? null;

                if ($pid === null) {
                    continue;
                }

                $calon[$pid.'|'.((int) $r->Id_Organization_Relationship_Type)][] = [
                    'kode_perusahaan' => (string) $r->Kode_Perusahaan_Terkait,
                    'kode_karyawan' => (string) $r->Kode_Karyawan_Terkait,
                    'nama' => (string) $r->Nama,
                ];
            }
        }

        return $calon;
    }

    /**
     * Baris Karyawan apa adanya, dipetakan ke nama kolom HC Learn.
     *
     * @param  list<array{0:string,1:string}>  $pasangan
     * @return list<array<string, mixed>>
     */
    private function muatanPendaftaran(array $pasangan): array
    {
        if ($pasangan === []) {
            return [];
        }

        $q = DB::table('Karyawan');

        foreach ($pasangan as [$kp, $kk]) {
            $q->orWhere(function ($w) use ($kp, $kk) {
                $w->where('Kode_Perusahaan', $kp)->where('Kode_Karyawan', $kk);
            });
        }

        $muatan = [];

        foreach ($q->get(array_values(self::KOLOM_KIRIM)) as $k) {
            $baris = [];

            foreach (self::KOLOM_KIRIM as $tujuan => $sumber) {
                $nilai = $k->{$sumber} ?? null;

                /* Tanggal dipotong ke YYYY-MM-DD. Kolomnya datetime di sisi kita, dan
                   mengirim jam pada kolom tanggal mereka membuat nilainya ditolak atau
                   dipotong diam-diam — dua-duanya sulit ditelusuri dari sisi ini. */
                if (in_array($tujuan, ['tanggal_lahir', 'tanggal_masuk'], true) && $nilai !== null) {
                    $nilai = substr((string) $nilai, 0, 10);
                }

                $baris[$tujuan] = $nilai;
            }

            $muatan[] = $baris;
        }

        return $muatan;
    }

    /** @param array<string,int> $ringkasan */
    private function status(array $ringkasan): string
    {
        if ($ringkasan['belum_terdaftar'] > 0) {
            return self::PERLU_SINKRON;
        }

        /* Yang menentukan `PERLU_TINDAKAN` adalah yang MENGHALANGI, bukan semua yang tercatat
           tidak siap. Orang yang ditolak tetapi tidak dipakai siapa pun tetap tampil di daftar
           — jujur, dan berguna — tetapi ia tidak boleh membuat lampu berhenti hijau selamanya
           pada siklus yang sebenarnya sudah beres. */
        return ($ringkasan['menghalangi'] ?? $ringkasan['tidak_siap']) > 0
            ? self::PERLU_TINDAKAN
            : self::SIAP;
    }

    /** @param array<string,int> $ringkasan */
    private function catatan(array $ringkasan): string
    {
        if ($ringkasan['belum_terdaftar'] > 0) {
            return sprintf(
                '%d dari %d orang yang terlibat belum terdaftar di HC Learn. Penjadwalan '
                .'akan ditolak seluruhnya selama masih ada satu pun — daftarkan mereka lebih dulu.',
                $ringkasan['belum_terdaftar'],
                $ringkasan['terlibat'],
            );
        }

        if (($ringkasan['menghalangi'] ?? 0) > 0) {
            return sprintf(
                '%d orang terdaftar di HC Learn tetapi tidak bisa dipakai (nonaktif atau sudah '
                .'resign di sana). Mendaftarkan ulang tidak mengubahnya; ganti penilainya, atau '
                .'kecualikan pesertanya, atau minta HC Learn mengaktifkannya.',
                $ringkasan['menghalangi'],
            );
        }

        if ($ringkasan['tidak_siap'] > 0) {
            return sprintf(
                '%d orang ditolak HC Learn tetapi TIDAK menghalangi: tidak ada penugasan aktif '
                .'yang menunjuk mereka, dan penjadwalan memang tidak akan mengirimkan namanya. '
                .'Mereka tetap didaftar di sini supaya bisa dibereskan di data induk kelak.',
                $ringkasan['tidak_siap'],
            );
        }

        return sprintf('%d orang yang terlibat sudah siap dijadwalkan.', $ringkasan['terlibat']);
    }
}
