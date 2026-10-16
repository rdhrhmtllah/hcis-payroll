<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use App\Jobs\KPI\PerformanceAppraisal\HapusJadwalHcLearnPaJob;
use App\Jobs\KPI\PerformanceAppraisal\HitungHasilPesertaJob;
use App\Services\SYSTEM\Organization\OrganizationRelationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Pembentuk penugasan penilai — jembatan antara peserta yang sudah disiapkan dan
 * payload yang kelak datang dari HC Learn.
 *
 * SISTEM MENGUSULKAN, ADMIN MENUGASKAN (K10)
 *
 * `usulkan()` tidak menulis apa pun. Ia membaca relasi organisasi dan mengembalikan
 * daftar calon beserta alasannya, lalu berhenti. Yang menulis adalah `tugaskan()`,
 * dan itu hanya berjalan atas nama seseorang yang memilih. Perbedaan ini bukan gaya:
 * relasi organisasi di HRIS tidak pernah lengkap, dan penugasan yang terbentuk sendiri
 * dari data yang tidak lengkap akan menghasilkan penilaian yang tidak ada yang mengaku
 * memutuskannya.
 *
 * KENAPA BOBOT DIHITUNG ULANG SETIAP KALI
 *
 * `Bobot_Dalam_Kategori` membagi 100 di antara penilai dalam satu kategori. Menambah
 * atau membatalkan satu penilai mengubah bobot SEMUA penilai lain di kategori itu.
 * Karena itu setiap perubahan memanggil `hitungUlangBobot()` untuk kategori yang
 * tersentuh — menyimpan bobot sekali saat disisipkan akan meninggalkan jumlah yang
 * bukan 100 begitu ada satu pembatalan.
 *
 * PEMBATALAN BUKAN PENGHAPUSAN
 *
 * `batalkan()` menuntut alasan, mencatat pelaku dan waktunya, lalu menonaktifkan
 * barisnya. Barisnya tidak pernah dihapus. Flag_Aktif ikut turun karena unique index
 * penugasan menyaring `Flag_Aktif = 'Y'`: tanpa itu, penilai yang dibatalkan karena
 * salah pilih tidak akan pernah bisa ditugaskan lagi.
 */
class PaAssignmentBuilderService
{
    /** Kategori yang penilainya adalah peserta itu sendiri. */
    public const KATEGORI_SELF = 'SELF';

    public const SUMBER_USULAN = 'USULAN';

    public const SUMBER_MANUAL = 'MANUAL';

    public const STATUS_BELUM_MULAI = 'BELUM_MULAI';

    public const STATUS_DIBATALKAN = 'DIBATALKAN';

    /**
     * Penilai sudah mengirim, tetapi angkanya belum disahkan admin HC Learn.
     *
     * BUKAN selesai: nilainya belum boleh ikut dihitung, dan pesertanya belum lengkap.
     * BUKAN belum-mulai: orangnya sudah mengerjakan bagiannya, jadi menagihnya lagi
     * lewat pengingat adalah menagih pekerjaan yang sudah dilakukan — dan pengingat
     * yang salah alamat membuat seluruh pengingat berhenti dipercaya.
     *
     * Keadaan ketiga inilah yang membuat kedua kesalahan itu tidak perlu dipilih.
     */
    public const STATUS_MENUNGGU_VERIFIKASI = 'MENUNGGU_VERIFIKASI';

    /**
     * Terisi dan terpetakan, tetapi penilainya BELUM mengirim seluruh tugasnya.
     *
     * Bedanya dengan `MENUNGGU_VERIFIKASI` ada pada siapa yang ditunggu. Yang itu menunggu
     * pengesah di HC Learn; yang ini menunggu penilainya sendiri menuntaskan lembar-lembar
     * lain miliknya, lalu menekan kirim sekali untuk semuanya. Lihat `PaRaterSubmitService`.
     *
     * SENGAJA TIDAK dikecualikan dari `sisaPenugasan()`: justru itu yang membuat peserta
     * terbaca belum lengkap, sehingga tidak ada angka yang lahir di atas penilaian yang
     * pengisinya belum menganggap pekerjaannya selesai.
     */
    public const STATUS_TERKUMPUL = 'TERKUMPUL';

    /** Dieja di sini juga supaya penurunan tenggat tak perlu mengimpor lapisan hasil. */
    public const STATUS_SELESAI_TUGAS = 'SELESAI';

    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaParticipantRuleResolver $resolver,
        private readonly PaBobotGuard $bobot,
        private readonly PaNotificationService $pemberitahuan,
    ) {}

    /**
     * Calon penilai untuk satu peserta, per kategori. TIDAK menulis apa pun.
     *
     * Kategori yang tidak punya jenis relasi di aturannya tetap dikembalikan dengan
     * daftar calon kosong dan `butuh_manual = true`. Menyembunyikannya akan membuat
     * admin mengira kategori itu tidak berlaku, padahal justru kategori itulah yang
     * harus diisi tangan.
     *
     * @return array<string, array{
     *     kategori_id:int, kode_kategori:string, nama_kategori:string,
     *     min_rater:int, maks_rater:int|null, terpasang:int,
     *     butuh_manual:bool, izinkan_manual:bool,
     *     calon:list<array{kode_perusahaan:string, kode_karyawan:string, nama:string,
     *                      organization_relationship_id:int|null, kode_relasi:string|null,
     *                      sudah_ditugaskan:bool}>
     * }>
     */
    public function usulkan(int $participantId): array
    {
        $peserta = $this->peserta($participantId);
        $aturan = $this->resolver->raterRules($participantId);
        $terpasang = $this->penugasanAktif($participantId);
        $dicabut = $this->penilaiDicabut($participantId);
        $acuan = $this->tanggalAcuan((int) $peserta->PA_Cycle_Id);
        $diLuar = $this->relasiDiLuarAcuan($peserta, $aturan, $acuan);

        $hasil = [];
        $tanggalAcuan = $acuan;

        foreach ($aturan as $kode => $a) {
            $calon = $kode === self::KATEGORI_SELF
                ? $this->calonDiriSendiri($peserta)
                : $this->calonDariRelasi($peserta, $a['relationship_type_id'], $acuan);

            $sudah = $terpasang[$a['kategori_id']] ?? [];

            foreach ($calon as $i => $c) {
                $milik = $sudah[$c['kode_perusahaan'].'|'.$c['kode_karyawan']] ?? null;

                $calon[$i]['sudah_ditugaskan'] = $milik !== null;
                $calon[$i]['assignment_id'] = $milik['assignment_id'] ?? null;
                $calon[$i]['bobot'] = $milik['bobot'] ?? null;
                $calon[$i]['status_penugasan'] = $milik['status'] ?? null;

                /* Dicabut admin di siklus ini dan belum dipasang lagi. Usulan otomatis — dialog
                   maupun pembentukan massal — melewatinya: tanpa penanda ini, calon pertama yang
                   belum terpasang untuk menutup minimum justru orang yang baru saja dicabut, dan
                   centangnya muncul lagi seketika (defect UAT 8 Okt). Memasangnya kembali tetap
                   boleh, tetapi harus dicentang tangan. */
                $calon[$i]['pernah_dicabut'] = $milik === null
                    && isset($dicabut[$a['kategori_id'].'|'.$c['kode_perusahaan'].'|'.$c['kode_karyawan']]);
            }

            /* Penilai yang SUDAH ditugaskan tetapi TIDAK ADA di daftar calon relasi.

               Terjadi pada penilai manual, dan pada penilai yang relasinya dicabut sesudah
               ditugaskan. Tanpa baris ini mereka hilang dari layar: bobotnya ikut menghitung,
               tetapi admin tidak melihatnya dan tidak bisa mencabutnya — penilai hantu yang
               hanya muncul di hasil. */
            $adaDiCalon = [];

            foreach ($calon as $c) {
                $adaDiCalon[$c['kode_perusahaan'].'|'.$c['kode_karyawan']] = true;
            }

            foreach ($sudah as $kunci => $milik) {
                if (isset($adaDiCalon[$kunci])) {
                    continue;
                }

                [$pt, $kk] = explode('|', $kunci, 2);
                $orang = DB::table('Karyawan')->where('Kode_Perusahaan', $pt)->where('Kode_Karyawan', $kk)->first(['Nama', 'Aktif']);

                /* Penilai yang SUDAH terpasang hanya DITAMPILKAN di sini, jadi yang sudah resign
                   pun tetap muncul (bertanda) — bukan melempar galat. Sampai 13 Okt 2026 ia
                   melempar lewat `namaKaryawanAktif()`, sehingga Kelola penilai dan revisi gagal
                   dibuka persis untuk peserta yang penilainya resign, kasus yang paling butuh. */
                $calon[] = [
                    'kode_perusahaan' => $pt,
                    'kode_karyawan' => $kk,
                    'nama' => (string) ($orang->Nama ?? $kk),
                    'tidak_aktif' => ($orang->Aktif ?? 'T') !== 'Y',
                    'organization_relationship_id' => null,
                    'kode_relasi' => null,
                    'sudah_ditugaskan' => true,
                    'assignment_id' => $milik['assignment_id'],
                    'bobot' => $milik['bobot'],
                    'status_penugasan' => $milik['status'],
                    'di_luar_relasi' => true,
                ];
            }

            $hasil[$kode] = [
                'kategori_id' => $a['kategori_id'],
                'kode_kategori' => $kode,
                'tanggal_acuan' => $tanggalAcuan,
                'nama_kategori' => $a['nama_kategori'],
                'min_rater' => $a['min_rater'],
                /* Ambang pengisi agar kategori ini DIHITUNG di hasil (kosong = min_rater). */
                'min_rater_selesai' => $a['min_rater_selesai'] ?? $a['min_rater'],
                'maks_rater' => $a['maks_rater'],
                'terpasang' => count($sudah),
                // Tidak ada calon yang bisa diusulkan mesin: entah jenis relasinya
                // memang tidak ditetapkan, entah datanya belum ada.
                'butuh_manual' => $calon === [],
                'izinkan_manual' => ($a['flag_izinkan_manual'] ?? 'T') === 'Y',

                /* Apakah kategori ini TERIKAT pada satu jenis relasi organisasi.
                   Penambahan penilai bekerja dengan membentuk relasinya lebih dulu, jadi
                   kategori tanpa jenis relasi tidak bisa menerima penilai baru sama sekali —
                   SELF yang paling jelas: penilainya sudah pasti pesertanya sendiri, dan tidak
                   ada relasi organisasi yang menghubungkan orang dengan dirinya.
                   Dikirim supaya LAYAR tidak menawarkan tombol yang pasti ditolak server. */
                'terikat_relasi' => ($a['relationship_type_id'] ?? null) !== null,

                /* Relasi yang ADA tetapi tidak terbaca siklus ini karena tanggalnya — lihat
                   `relasiDiLuarAcuan()`. Nol untuk kategori yang memang tidak punya relasi. */
                'relasi_di_luar_acuan' => $diLuar[$a['relationship_type_id'] ?? 0]
                    ?? ['belum_berlaku' => 0, 'mulai_paling_awal' => null, 'sudah_berakhir' => 0],
                'calon' => $calon,
            ];
        }

        return $hasil;
    }

    /**
     * Menugaskan sekumpulan penilai kepada satu peserta.
     *
     * Seluruhnya berhasil atau seluruhnya batal. Menugaskan sebagian akan meninggalkan
     * bobot dalam kategori yang sudah terlanjur dihitung atas himpunan yang bukan
     * himpunan akhirnya.
     *
     * @param  list<array{kode_kategori:string, kode_perusahaan:string, kode_karyawan:string,
     *                    sumber?:string, organization_relationship_id?:int|null,
     *                    alasan_manual?:string|null}>  $penilai
     * @return array{dibuat:int, kategori_dihitung_ulang:list<int>}
     */
    /**
     * Membentuk penugasan untuk BANYAK peserta dengan tulisan yang digabung.
     *
     * ------------------------------------------------------------------------------------------
     * BEDANYA DENGAN `bentukMassal()`
     * ------------------------------------------------------------------------------------------
     *
     * `bentukMassal()` memanggil `tugaskan()` sekali per peserta. Tiap panggilan membawa kunci
     * baris, `max(Id)`, satu `INSERT`, lalu hitung ulang bobot per kategori — sekitar tujuh
     * perjalanan ke database per orang. Untuk 859 peserta itu ribuan perjalanan, dan pada DB
     * yang setiap perjalanannya 55-324 ms itulah keseluruhan biayanya.
     *
     * Di sini penjaganya tetap dijalankan PER CALON lewat `susunBarisPenugasan()` — metode yang
     * sama persis dengan yang dipakai `tugaskan()`, bukan salinannya — tetapi TULISANNYA
     * digabung: satu `INSERT` untuk ratusan baris.
     *
     * ------------------------------------------------------------------------------------------
     * KENAPA TIDAK PERLU HITUNG ULANG BOBOT
     * ------------------------------------------------------------------------------------------
     *
     * Yang diproses hanya peserta yang BELUM punya satu penugasan pun. Artinya tiap kategori
     * lahir utuh di sini, tidak ada bobot khusus yang harus dihormati, dan pembagiannya sudah
     * diketahui sebelum barisnya ditulis. Bobotnya karena itu diisi LANGSUNG — bukan ditulis nol
     * lalu dibaca ulang lalu diperbarui, yang berarti dua perjalanan tambahan per kategori.
     *
     * Konsekuensinya jelas dan disengaja: metode ini TIDAK bisa dipakai untuk MELENGKAPI peserta
     * yang penilainya sudah sebagian. Untuk itu `bentukMassal()` tetap jalurnya, karena di sana
     * `hitungUlangBobot()` harus berjalan untuk menghormati `Bobot_Kustom`.
     *
     * ------------------------------------------------------------------------------------------
     * KEGAGALAN SATU PESERTA TIDAK MEMBATALKAN SEPOTONG
     * ------------------------------------------------------------------------------------------
     *
     * Baris disusun di memori LEBIH DULU, per peserta. Peserta yang penjaganya menolak dibuang
     * dari potongan itu saja; sisanya tetap ditulis. Tanpa pemisahan ini satu peserta bermasalah
     * akan menggugurkan 99 lainnya — kemunduran nyata dibanding jalur per-peserta yang setiap
     * orangnya commit sendiri.
     *
     * @return array{peserta_diproses:int, peserta_tersentuh:int, dibuat:int, gagal:list<string>, belum_berpenugasan:int}
     */
    public function bentukMassalCepat(
        int $cycleId,
        bool $penuh = false,
        int $batas = 100,
        ?int $olehUserId = null,
        ?int $mulaiDariId = null,
    ): array {
        $this->gate->pastikanBoleh(PaCycleGate::BUAT_PENUGASAN, $cycleId);

        /* KURSOR `mulaiDariId`: peserta yang SELALU gagal dibentuk (mis. calon wajibnya sudah
           nonaktif) tetap tanpa penugasan, sehingga tanpa kursor ia diambil lagi di setiap
           panggilan — layar memutar 200 kali dan jalur "lengkapi" tidak pernah tercapai. */
        $peserta = DB::table('N_PA_Participant as p')
            ->leftJoin('N_PA_Assignment as a', function ($j) {
                $j->on('a.PA_Participant_Id', '=', 'p.Id_PA_Participant')
                    ->where('a.Flag_Aktif', '=', 'Y');
            })
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('p.Flag_Aktif', 'Y')
            ->whereNull('a.Id_PA_Assignment')
            ->when($mulaiDariId !== null, fn ($q) => $q->where('p.Id_PA_Participant', '>', $mulaiDariId))
            ->orderBy('p.Id_PA_Participant')
            ->limit(max(1, $batas))
            ->get(['p.Id_PA_Participant as id', 'p.Nama_Karyawan_Saat_Cycle as nama']);

        $hasil = [
            'peserta_diproses' => $peserta->count(),
            'terakhir_id' => $peserta->isEmpty() ? $mulaiDariId : (int) $peserta->last()->id,
            'peserta_tersentuh' => 0,
            'dibuat' => 0,
            'gagal' => [],
            'belum_berpenugasan' => 0,
        ];

        if ($peserta->isEmpty()) {
            $hasil['belum_berpenugasan'] = $this->pesertaTanpaPenugasan($cycleId);

            return $hasil;
        }

        $idPeserta = $peserta->pluck('id')->map(fn ($x) => (int) $x)->all();

        /* Seluruh bacaan disiapkan lebih dulu — sama seperti `bentukMassal()`. */
        $this->resolver->muatOverrideSiklus($cycleId);
        $this->resolver->muatPesertaBanyak($idPeserta);
        $this->hangatkanPeserta($idPeserta);
        $this->hangatkanPenugasanAktif($idPeserta);

        $tipeRelasi = [];

        foreach ($idPeserta as $id) {
            foreach ($this->resolver->raterRules($id) as $a) {
                if (($a['relationship_type_id'] ?? null) !== null) {
                    $tipeRelasi[(int) $a['relationship_type_id']] = true;
                }
            }
        }

        $this->hangatkanCalon(
            array_map(fn ($id) => $this->peserta($id), $idPeserta),
            array_keys($tipeRelasi),
            $this->tanggalAcuan($cycleId),
        );

        /* Calon dipilih lebih dulu untuk SELURUH potongan, supaya karyawan dan relasinya bisa
           dibaca sekali untuk semuanya. */
        $rencana = [];
        $semuaPenilai = [];

        foreach ($peserta as $p) {
            $pilihan = [];

            foreach ($this->usulkan((int) $p->id) as $d) {
                $target = $penuh ? (int) ($d['maks_rater'] ?? $d['min_rater']) : (int) $d['min_rater'];
                $perlu = $target - (int) ($d['terpasang'] ?? 0);

                if ($perlu <= 0) {
                    continue;
                }

                $tersedia = array_values(array_filter(
                    $d['calon'],
                    fn ($c) => empty($c['sudah_ditugaskan']) && empty($c['pernah_dicabut']),
                ));

                foreach (array_slice($tersedia, 0, $perlu) as $c) {
                    $pilihan[] = [
                        'kode_kategori' => $d['kode_kategori'],
                        'kode_perusahaan' => $c['kode_perusahaan'],
                        'kode_karyawan' => $c['kode_karyawan'],
                        'sumber' => 'USULAN',
                        'organization_relationship_id' => $c['organization_relationship_id'] ?? null,
                    ];
                }
            }

            if ($pilihan === []) {
                continue;
            }

            $rencana[(int) $p->id] = ['nama' => $p->nama, 'pilihan' => $pilihan];

            foreach ($pilihan as $x) {
                $semuaPenilai[] = $x;
            }
        }

        if ($rencana === []) {
            $hasil['belum_berpenugasan'] = $this->pesertaTanpaPenugasan($cycleId);

            return $hasil;
        }

        /* Karyawan untuk SELURUH potongan: satu kueri, bukan satu per peserta. */
        $petaKaryawan = $this->karyawanBanyak(array_map(
            fn ($x) => [(string) $x['kode_perusahaan'], (string) $x['kode_karyawan']],
            $semuaPenilai,
        ));

        /* Sidik relasi untuk SELURUH potongan — satu kueri, bukan satu per peserta. */
        $semuaRelasiId = [];

        foreach ($semuaPenilai as $x) {
            if (($x['organization_relationship_id'] ?? null) !== null) {
                $semuaRelasiId[] = (int) $x['organization_relationship_id'];
            }
        }

        $sidikPotongan = $this->sidikDariRelasi(array_values(array_unique($semuaRelasiId)));

        $tenggat = $this->tenggatSiklus($cycleId);
        $baris = [];

        foreach ($rencana as $pid => $r) {
            try {
                $pesertaObjek = $this->peserta($pid);

                if ($pesertaObjek->Status_Peserta !== 'DIIKUTKAN') {
                    throw new RuntimeException(
                        "Peserta {$pid} berstatus {$pesertaObjek->Status_Peserta}; "
                        .'hanya peserta DIIKUTKAN yang boleh diberi penilai.'
                    );
                }

                $aturan = $this->resolver->raterRules($pid);
                $terpasang = $this->penugasanAktif($pid);

                $this->sidikRelasi = $sidikPotongan;

                [$barisPeserta, $kategoriTersentuh] = $this->susunBarisPenugasan(
                    $pesertaObjek,
                    $aturan,
                    $r['pilihan'],
                    $terpasang,
                    $petaKaryawan,
                    $tenggat,
                    $olehUserId,
                );

                /* BOBOT DIISI LANGSUNG. Kategorinya lahir utuh di sini — pesertanya tidak punya
                   penugasan sebelumnya — sehingga pembagian ratanya sudah pasti dan tidak perlu
                   ditulis nol lalu diperbaiki. */
                $perKategori = [];

                foreach ($barisPeserta as $i => $b) {
                    $perKategori[$b['PA_Rater_Category_Id']][] = $i;
                }

                foreach ($perKategori as $anggota) {
                    $bagian = $this->bobot->bagiRata(count($anggota));

                    foreach ($anggota as $urut => $i) {
                        $barisPeserta[$i]['Bobot_Dalam_Kategori'] = $bagian[$urut];
                    }
                }

                foreach ($barisPeserta as $b) {
                    $baris[] = $b;
                }

                $hasil['peserta_tersentuh']++;
                unset($this->memoPenugasanAktif[$pid]);
            } catch (\Throwable $e) {
                /* Peserta ini saja yang dibuang dari potongan; sisanya tetap ditulis. */
                $hasil['gagal'][] = $r['nama'].': '.substr($e->getMessage(), 0, 90);
            } finally {
                $this->sidikRelasi = null;
            }
        }

        $sekarang = $this->sekarang();
        $idBaru = [];

        foreach (array_chunk($baris, 100) as $potongan) {
            foreach ($potongan as $i => $b) {
                $potongan[$i]['Created_At'] = $sekarang;
            }

            DB::table('N_PA_Assignment')->insert($potongan);
            $hasil['dibuat'] += count($potongan);
        }

        $hasil['belum_berpenugasan'] = $this->pesertaTanpaPenugasan($cycleId);

        /* Pemberitahuan menyusul, dan kegagalannya tidak menjatuhkan pembentukan. */
        if ($hasil['dibuat'] > 0) {
            $idBaru = DB::table('N_PA_Assignment')
                ->whereIn('PA_Participant_Id', array_keys($rencana))
                ->where('Flag_Aktif', 'Y')
                ->pluck('Id_PA_Assignment')
                ->map(fn ($x) => (int) $x)
                ->all();

            foreach (array_chunk($idBaru, 100) as $potongan) {
                try {
                    $this->pemberitahuan->penugasanDibentuk($potongan);
                } catch (\Throwable $e) {
                    $hasil['gagal'][] = 'Pemberitahuan gagal: '.substr($e->getMessage(), 0, 70);
                }
            }
        }

        return $hasil;
    }

    /**
     * Penilai yang akan dibentuk untuk satu peserta: dari usulan, sampai `min_rater` (atau
     * `maks_rater` bila `$penuh`), melewati yang sudah terpasang atau pernah dicabut.
     *
     * Satu tempat untuk pembentukan massal DAN pembentukan satu peserta sesudah dikunci —
     * dua salinan aturan memilih penilai pasti menyimpang suatu hari.
     *
     * @return list<array<string, mixed>>
     */
    private function pilihanDariUsulan(int $participantId, bool $penuh): array
    {
        $pilihan = [];

        foreach ($this->usulkan($participantId) as $d) {
            $target = $penuh
                ? (int) ($d['maks_rater'] ?? $d['min_rater'])
                : (int) $d['min_rater'];

            $perlu = $target - (int) ($d['terpasang'] ?? 0);

            if ($perlu <= 0) {
                continue;
            }

            /*
             | YANG SUDAH TERPASANG DIBUANG LEBIH DULU.
             |
             | `usulkan()` memulangkan SELURUH calon, termasuk yang sudah jadi penilai —
             | layar membutuhkannya untuk menampilkan centang. Mengambil begitu saja dari
             | urutan pertama karena itu bisa memilih orang yang sudah terpasang, dan
             | penjaga penilai kembar menolaknya.
             |
             | Cacat ini TIDAK terlihat pada pembentukan pertama — saat itu belum ada
             | penugasan sama sekali sehingga tidak ada yang bertanda. Ia baru muncul saat
             | DIJALANKAN ULANG, yaitu justru jalur pemulihan yang dianjurkan ketika
             | pembentukan sebelumnya terhenti. Terlihat pada peserta 208513: PEER min 2,
             | satu sudah terpasang, satu-satunya calon adalah orang yang sama.
             */
            $tersedia = array_values(array_filter(
                $d['calon'],
                fn ($c) => empty($c['sudah_ditugaskan']) && empty($c['pernah_dicabut']),
            ));

            foreach (array_slice($tersedia, 0, $perlu) as $c) {
                /* Relasi asalnya WAJIB diteruskan. Penugasan USULAN selain SELF ditolak
                   tanpa itu, dan penolakannya benar: penilai yang tidak bisa ditelusuri
                   ke relasi mana pun tidak bisa dipertanggungjawabkan setahun lagi.
                   Untuk SELF nilainya justru harus NULL. */
                $pilihan[] = [
                    'kode_kategori' => $d['kode_kategori'],
                    'kode_perusahaan' => $c['kode_perusahaan'],
                    'kode_karyawan' => $c['kode_karyawan'],
                    'sumber' => 'USULAN',
                    'organization_relationship_id' => $c['organization_relationship_id'] ?? null,
                ];
            }
        }

        return $pilihan;
    }

    /**
     * Rencana MENYELARASKAN penilai satu peserta dengan relasi organisasi (per tanggal acuan).
     *
     *   keluar  penugasan USULAN yang BELUM diisi dan penilainya tidak lagi ada di relasi —
     *           atasan/rekan/bawahan yang sudah berganti. MANUAL (pilihan admin) dan yang sudah
     *           diisi tidak pernah disentuh.
     *   masuk   calon dari relasi untuk mengisi kategori yang menjadi kurang dari minimum.
     *
     * SELF dilewati: penilaian diri tidak berasal dari relasi.
     *
     * @return array{keluar:list<array<string, mixed>>, masuk:list<array<string, mixed>>, acuan:string, kategori:list<array<string, mixed>>}
     */
    public function rencanaSelaras(int $participantId): array
    {
        $peserta = $this->peserta($participantId);
        $usulan = $this->usulkan($participantId);
        $aktif = DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->where('Status_Penugasan', '<>', self::STATUS_DIBATALKAN)
            ->get(['Id_PA_Assignment', 'PA_Rater_Category_Id', 'Kode_Perusahaan_Penilai', 'Kode_Karyawan_Penilai',
                'Nama_Penilai_Saat_Cycle', 'Sumber_Penugasan', 'Status_Penugasan']);

        $keluar = [];
        $masuk = [];
        $tersentuh = [];

        foreach ($usulan as $kode => $d) {
            if ($kode === self::KATEGORI_SELF) {
                continue;
            }

            /* `usulkan()` ikut memulangkan penilai terpasang yang TIDAK ada di relasi
               (`di_luar_relasi`) supaya layar bisa menampilkannya — justru mereka yang dicari. */
            $calon = [];
            foreach ($d['calon'] as $c) {
                if (empty($c['di_luar_relasi'])) {
                    $calon[$c['kode_perusahaan'].'|'.trim((string) $c['kode_karyawan'])] = true;
                }
            }

            $diKategori = $aktif->where('PA_Rater_Category_Id', $d['kategori_id']);
            $keluarKategori = $diKategori->filter(fn ($a) => $a->Sumber_Penugasan === self::SUMBER_USULAN
                && ! in_array($a->Status_Penugasan, PaStatusPenugasan::SUDAH_DIKERJAKAN, true)
                && ! isset($calon[$a->Kode_Perusahaan_Penilai.'|'.trim((string) $a->Kode_Karyawan_Penilai)]));

            foreach ($keluarKategori as $a) {
                $keluar[] = [
                    'assignment_id' => (int) $a->Id_PA_Assignment,
                    'kode_karyawan' => trim((string) $a->Kode_Karyawan_Penilai),
                    'nama' => $a->Nama_Penilai_Saat_Cycle,
                    'kategori' => $d['nama_kategori'],
                ];
            }

            $perlu = max(0, (int) $d['min_rater'] - ($diKategori->count() - $keluarKategori->count()));
            $tersedia = array_values(array_filter($d['calon'], fn ($c) => empty($c['sudah_ditugaskan']) && empty($c['pernah_dicabut'])));

            $masukKategori = array_slice($tersedia, 0, $perlu);
            if ($keluarKategori->isNotEmpty() || $masukKategori !== []) {
                /* Bahan peringatan "kategori ini tidak akan dihitung" di lapisan revisi. */
                $tersentuh[] = [
                    'kategori_id' => $d['kategori_id'],
                    'nama_kategori' => $d['nama_kategori'],
                    'min_rater' => $d['min_rater'],
                    'min_rater_selesai' => $d['min_rater_selesai'],
                    'sisa' => $diKategori->count() - $keluarKategori->count(),
                    'masuk' => count($masukKategori),
                ];
            }

            foreach ($masukKategori as $c) {
                $masuk[] = [
                    'kode_kategori' => $d['kode_kategori'],
                    'kode_perusahaan' => $c['kode_perusahaan'],
                    'kode_karyawan' => $c['kode_karyawan'],
                    'nama' => $c['nama'],
                    'kategori' => $d['nama_kategori'],
                    'sumber' => self::SUMBER_USULAN,
                    'organization_relationship_id' => $c['organization_relationship_id'] ?? null,
                ];
            }
        }

        return ['keluar' => $keluar, 'masuk' => $masuk, 'acuan' => $this->tanggalAcuan((int) $peserta->PA_Cycle_Id), 'kategori' => $tersentuh];
    }

    /**
     * Penugasan seseorang SEBAGAI PENILAI di satu siklus yang belum diisi — tidak termasuk
     * penilaian dirinya sendiri (SELF pada baris pesertanya).
     *
     * @return list<array{assignment_id:int, participant_id:int, peserta:string, kategori_id:int}>
     */
    public function penugasanSebagaiPenilai(int $cycleId, string $kodePerusahaan, string $kodeKaryawan): array
    {
        return DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Flag_Aktif', 'Y')
            ->where('a.Kode_Perusahaan_Penilai', $kodePerusahaan)
            ->where('a.Kode_Karyawan_Penilai', $kodeKaryawan)
            ->where('a.Flag_Aktif', 'Y')
            ->where('a.Status_Penugasan', '<>', self::STATUS_DIBATALKAN)
            ->whereNotIn('a.Status_Penugasan', PaStatusPenugasan::SUDAH_DIKERJAKAN)
            ->where(fn ($q) => $q->where('p.Kode_Perusahaan', '<>', $kodePerusahaan)->orWhere('p.Kode_Karyawan', '<>', $kodeKaryawan))
            ->orderBy('p.Nama_Karyawan_Saat_Cycle')
            ->get(['a.Id_PA_Assignment as aid', 'p.Id_PA_Participant as pid', 'p.Nama_Karyawan_Saat_Cycle as nama', 'a.PA_Rater_Category_Id as kat'])
            ->map(fn ($r) => ['assignment_id' => (int) $r->aid, 'participant_id' => (int) $r->pid, 'peserta' => $r->nama, 'kategori_id' => (int) $r->kat])
            ->all();
    }

    /** Hasil peserta ini sudah TERBIT — dilihat orangnya, jadi penilainya tidak boleh berubah. */
    public function hasilSudahTerbit(int $participantId): bool
    {
        return $participantId > 0 && DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->where('Status_Hasil', 'TERBIT')
            ->exists();
    }

    /**
     * Membentuk penugasan SATU peserta — jalur revisi sesudah siklus dikunci.
     *
     * Lewat `tugaskan()`, yang gerbangnya `TAMBAH_PENILAI` (terbuka sampai OPEN), jadi seluruh
     * penjaganya tetap berlaku: kategori level, `maks_rater`, penilai kembar, hasil terbit.
     * Yang dibentuk hanya yang KURANG — penugasan yang sudah ada tidak disentuh.
     *
     * @return array{dibuat:int, id_baru:list<int>}
     */
    public function bentukUntukPeserta(int $participantId, ?int $olehUserId = null): array
    {
        $pilihan = $this->pilihanDariUsulan($participantId, false);

        if ($pilihan === []) {
            return ['dibuat' => 0, 'id_baru' => []];
        }

        $hasil = $this->tugaskan($participantId, $pilihan, $olehUserId);

        return ['dibuat' => (int) ($hasil['dibuat'] ?? 0), 'id_baru' => array_map('intval', $hasil['id_baru'] ?? [])];
    }

    /**
     * Membentuk penugasan penilai untuk BANYAK peserta sekaligus, dari usulan yang sudah ada.
     *
     * KENAPA ADA DI SINI, BUKAN DI DALAM PERINTAH KONSOL
     *
     * Sampai 4 September 2026 seluruh alurnya hidup di `pa:buat-penugasan` dan tidak punya satu
     * pun pintu di layar. Akibatnya Langkah 5 wizard melaporkan "859 peserta belum memenuhi
     * kuota, 2.648 slot kosong" dan menyuruh admin membereskannya "lewat halaman Penugasan
     * Penilai" — halaman yang bekerja SATU PESERTA sekali jalan. Untuk 859 peserta itu bukan
     * pekerjaan yang bisa diselesaikan siapa pun, sehingga langkahnya mustahil dituntaskan
     * kecuali oleh orang yang punya akses terminal.
     *
     * Dipindahkan ke sini supaya perintah konsol dan layar memanggil yang SAMA. Menyalinnya
     * akan melahirkan dua salinan yang menyimpang, dan yang menyimpang di sini berarti dua cara
     * membentuk penugasan dengan hasil yang berbeda.
     *
     * KENAPA MELALUI `tugaskan()`, BUKAN MENULIS LANGSUNG
     *
     * Semua penjaga tinggal di sana: kategori yang bukan milik levelnya ditolak, penilai yang
     * melewati `maks_rater` ditolak, penilai kembar ditolak, dan bobot kategori dihitung ulang
     * sesudahnya. Menulis langsung akan melewati semuanya dan menghasilkan penugasan yang
     * tampak benar lalu hilang dari hasil tanpa jejak.
     *
     * IDEMPOTEN, DAN ITU YANG MEMBUATNYA BISA DICICIL
     *
     * Peserta yang penilainya sudah cukup dilewati. Karena itu `$batas` aman dipakai berkali-kali:
     * layar bisa memanggilnya per potongan sambil menunjukkan kemajuan, dan permintaan HTTP tidak
     * pernah berjalan belasan menit lalu mati kehabisan waktu.
     *
     * @return array{peserta_diproses:int, peserta_tersentuh:int, sudah_cukup:int, dibuat:int, gagal:list<string>, belum_berpenugasan:int}
     */
    public function bentukMassal(
        int $cycleId,
        bool $penuh = false,
        int $batas = 0,
        ?int $olehUserId = null,
        bool $kering = false,
    ): array {
        $this->gate->pastikanBoleh(PaCycleGate::BUAT_PENUGASAN, $cycleId);

        /*
         | SAAT DICICIL, yang diambil hanya peserta yang BELUM punya penugasan sama sekali.
         |
         | Tanpa syarat itu `limit` selalu mengambil 100 peserta PERTAMA menurut id — sehingga
         | panggilan kedua mengerjakan orang yang sama, mendapati semuanya sudah cukup, dan
         | berhenti. Pencicilan yang tidak pernah maju.
         |
         | Terbukti mahal: pembentukan penuh siklus 1295 dilaporkan "SELESAI" sesudah dua
         | putaran, padahal baru 100 dari 859 peserta yang tersentuh. Angkanya terlihat wajar
         | dan prosesnya berakhir tanpa galat — bentuk kegagalan yang paling sulit disadari.
         |
         | Tanpa `batas` (0) seluruh peserta diambil, termasuk yang sudah punya sebagian
         | penugasan; itulah jalur untuk MELENGKAPI yang kurang, bukan sekadar membentuk.
         */
        $peserta = DB::table('N_PA_Participant as p')
            ->when($batas > 0, fn ($q) => $q
                ->leftJoin('N_PA_Assignment as a', function ($j) {
                    $j->on('a.PA_Participant_Id', '=', 'p.Id_PA_Participant')
                        ->where('a.Flag_Aktif', '=', 'Y');
                })
                ->whereNull('a.Id_PA_Assignment')
                ->limit($batas))
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('p.Flag_Aktif', 'Y')
            ->orderBy('p.Id_PA_Participant')
            ->get(['p.Id_PA_Participant as id', 'p.Nama_Karyawan_Saat_Cycle as nama']);

        /*
         | SELURUH bacaan disiapkan lebih dulu, satu kueri untuk seluruh potongan.
         |
         | Tanpa ini tiap peserta menembak database sendiri-sendiri untuk data yang sebagian
         | besar sama: aturannya, override yang biasanya kosong, penugasan yang biasanya belum
         | ada, dan relasi organisasinya. Pada DB yang 52 ms sekali jalan, itulah keseluruhan
         | biayanya — bukan pekerjaan SQL-nya, melainkan berapa kali harus menyeberang.
         |
         | Yang dipercepat CARA DATANYA SAMPAI, bukan aturannya. `usulkan()` dan `tugaskan()`
         | tetap berjalan persis seperti semula, lengkap dengan seluruh penjaganya; mereka
         | hanya menemukan jawabannya sudah di tangan.
         */
        $idPeserta = $peserta->pluck('id')->map(fn ($x) => (int) $x)->all();

        $this->resolver->muatOverrideSiklus($cycleId);
        $this->resolver->muatPesertaBanyak($idPeserta);
        $this->hangatkanPeserta($idPeserta);
        $this->hangatkanPenugasanAktif($idPeserta);

        $tipeRelasi = [];

        foreach ($idPeserta as $id) {
            foreach ($this->resolver->raterRules($id) as $a) {
                if (($a['relationship_type_id'] ?? null) !== null) {
                    $tipeRelasi[(int) $a['relationship_type_id']] = true;
                }
            }
        }

        $this->hangatkanCalon(
            array_map(fn ($id) => $this->peserta($id), $idPeserta),
            array_keys($tipeRelasi),
            $this->tanggalAcuan($cycleId),
        );

        $dibuat = 0;
        $tersentuh = 0;
        $sudahCukup = 0;
        $gagal = [];
        $idBaruSemua = [];

        foreach ($peserta as $p) {
            try {
                $pilihan = $this->pilihanDariUsulan((int) $p->id, $penuh);

                if ($pilihan === []) {
                    $sudahCukup++;

                    continue;
                }

                if ($kering) {
                    $dibuat += count($pilihan);
                } else {
                    $hasil = $this->tugaskan((int) $p->id, $pilihan, $olehUserId, [], [], true);
                    $dibuat += (int) ($hasil['dibuat'] ?? 0);

                    foreach ($hasil['id_baru'] ?? [] as $id) {
                        $idBaruSemua[] = $id;
                    }
                }

                $tersentuh++;
            } catch (\Throwable $e) {
                /*
                 | GALAT BELUM TENTU KEGAGALAN.
                 |
                 | Jaringan ke database melintasi internet, dan sambungannya terbukti bisa putus
                 | di tengah ("connection was aborted by the software in your host machine").
                 | Ketika itu terjadi SESUDAH server menuliskan barisnya tetapi SEBELUM
                 | balasannya sampai, percobaan ulang menabrak indeks unik dan melapor
                 | "Cannot insert duplicate key" - padahal penugasannya sudah ada dan benar.
                 |
                 | Dibuktikan pada peserta 208982: dilaporkan gagal, sementara keenam barisnya
                 | tercatat rapi pada detik yang sama. Sepuluh peserta berikutnya dilaporkan
                 | gagal semua sementara angka kemajuan justru naik 42.
                 |
                 | Karena itu keadaannya DIPERIKSA, bukan disimpulkan dari galatnya. Yang
                 | benar-benar gagal tetap dicatat; yang ternyata berhasil dihitung sebagaimana
                 | mestinya. Menghitung keduanya sebagai kegagalan membuat admin mengulang
                 | pekerjaan yang sudah selesai dan tidak pernah tahu mana yang sungguh kurang.
                 */
                $mendarat = (int) DB::table('N_PA_Assignment')
                    ->where('PA_Participant_Id', (int) $p->id)
                    ->where('Flag_Aktif', 'Y')
                    ->count();

                if ($mendarat > 0) {
                    $tersentuh++;
                    $dibuat += $mendarat;
                    unset($this->memoPenugasanAktif[(int) $p->id]);

                    continue;
                }

                /* Kegagalan satu peserta TIDAK membatalkan sisanya, tetapi namanya dicatat.
                   "12 gagal" tanpa nama tidak bisa ditindaklanjuti siapa pun. */
                $gagal[] = $p->nama.': '.substr($e->getMessage(), 0, 90);
            }
        }

        /*
         | Pemberitahuan TIDAK BOLEH menjatuhkan pembentukan.
         |
         | Terbukti mahal: pada pembentukan 1295 kueri pemberitahuan atas 368 id — tiga tabel
         | di-join sekaligus — memutus koneksi ("connection was aborted by the software in your
         | host machine") dan menghentikan seluruh proses di putaran kedua. Penugasan yang sudah
         | terbentuk selamat karena tiap peserta commit sendiri, tetapi sisanya tidak pernah
         | dikerjakan.
         |
         | Membentuk penugasan adalah pekerjaan utamanya; memberi tahu adalah akibatnya. Yang
         | kedua gagal tidak boleh membatalkan yang pertama — ia dicatat, dan penilainya tetap
         | akan mendengar lewat pengingat tenggat.
         |
         | Potongannya 100, bukan 500: yang membebani bukan jumlah parameternya melainkan
         | join tiga tabel atas ratusan id sekaligus.
         */
        foreach (array_chunk($idBaruSemua, 100) as $potongan) {
            try {
                $this->pemberitahuan->penugasanDibentuk($potongan);
            } catch (\Throwable $e) {
                $gagal[] = 'Pemberitahuan '.count($potongan).' penugasan gagal: '
                    .substr($e->getMessage(), 0, 80);
            }
        }

        return [
            'peserta_diproses' => $peserta->count(),
            'peserta_tersentuh' => $tersentuh,
            'sudah_cukup' => $sudahCukup,
            'dibuat' => $dibuat,
            'gagal' => $gagal,

            /* Berapa peserta DIIKUTKAN yang belum tersentuh potongan ini — dipakai layar untuk
               tahu apakah perlu memanggil lagi.

               SENGAJA hitungan murah: satu kueri, bukan "berapa yang masih kurang kuotanya".
               Versi pertama memanggil `usulkan()` untuk tiap peserta demi angka yang lebih
               tepat — 859 peserta x beberapa kueri, di akhir SETIAP potongan. Angka yang lebih
               benar tidak sepadan dengan membuat operasinya berlipat lebih lambat; kekurangan
               kuota yang sesungguhnya sudah dilaporkan `PaAssignmentHealthService`. */
            'belum_berpenugasan' => $this->pesertaTanpaPenugasan($cycleId),
        ];
    }

    /**
     * Menyusun baris penugasan dari daftar calon — DENGAN SELURUH PENJAGANYA.
     *
     * KENAPA DIPISAH DARI `tugaskan()`
     *
     * Pembentukan massal perlu menyusun baris untuk RATUSAN peserta lalu menuliskannya sekali,
     * bukan satu peserta satu penulisan. Tanpa pemisahan ini jalur massal harus menyalin
     * penjaganya — dan dua salinan penjaga akan menyimpang. Yang menyimpang di sini
     * menghasilkan penugasan yang tampak benar lalu hilang dari hasil tanpa jejak: kategori
     * yang bukan milik levelnya dihitung dengan bobot yang tidak ada, penilai kembar
     * menggandakan suara, `maks_rater` terlewati tanpa ada yang tahu.
     *
     * Karena itu penjaganya tinggal DI SINI SAJA, dan kedua jalur memanggilnya.
     *
     * `$terpasang` DIUBAH DI TEMPAT: tiap calon yang lolos langsung dicatat ke dalamnya,
     * supaya calon berikutnya diuji terhadap keadaan yang sudah memperhitungkannya. Tanpa itu
     * dua calon yang sama dalam satu kiriman akan lolos berdua.
     *
     * @param  array<string, array<string, mixed>>  $aturan
     * @param  list<array<string, mixed>>  $penilai
     * @param  array<int, array<string, mixed>>  $terpasang
     * @param  array<string, object>  $petaKaryawan
     * @return array{0: list<array<string, mixed>>, 1: array<int, true>}
     */
    private function susunBarisPenugasan(
        object $peserta,
        array $aturan,
        array $penilai,
        array &$terpasang,
        array $petaKaryawan,
        ?string $tenggat,
        ?int $olehUserId,
    ): array {
        $participantId = (int) $peserta->Id_PA_Participant;
        $baris = [];
        $kategoriTersentuh = [];

        foreach ($penilai as $p) {
            $kode = (string) ($p['kode_kategori'] ?? '');
            $a = $aturan[$kode] ?? null;

            if ($a === null) {
                throw new RuntimeException(
                    "Kategori {$kode} tidak berlaku bagi peserta {$participantId}. "
                    .'Penilai di kategori yang bukan miliknya akan dihitung dengan bobot '
                    .'yang tidak ada dan hilang dari hasil tanpa jejak.'
                );
            }

            $sumber = (string) ($p['sumber'] ?? self::SUMBER_USULAN);

            if (! in_array($sumber, [self::SUMBER_USULAN, self::SUMBER_MANUAL], true)) {
                throw new RuntimeException("Sumber penugasan {$sumber} tidak didukung.");
            }

            if ($sumber === self::SUMBER_MANUAL && ($a['flag_izinkan_manual'] ?? 'T') !== 'Y') {
                throw new RuntimeException(
                    "Kategori {$kode} tidak mengizinkan penilai manual menurut aturan levelnya."
                );
            }

            $kp = (string) $p['kode_perusahaan'];
            $kk = (string) $p['kode_karyawan'];
            $kunci = $kp.'|'.$kk;

            $this->pastikanDiriSendiriKonsisten($kode, $peserta, $kp, $kk);
            $this->pastikanSumberKonsisten($p, $a, $peserta, $sumber, $kp, $kk);

            $nama = $this->namaKaryawanAktif($kp, $kk, $petaKaryawan);

            if (isset($terpasang[$a['kategori_id']][$kunci])) {
                throw new RuntimeException(
                    "{$nama} sudah ditugaskan sebagai {$kode} untuk peserta {$participantId}."
                );
            }

            /* Bentuknya disamakan dengan yang dipulangkan `penugasanAktif()`. Menaruh
               `true` di antara nilai-nilai bertipe array bekerja hari ini — pemakainya
               hanya `isset` dan `count` — dan akan menjadi jebakan bagi pembaca berikutnya
               yang membaca isinya. */
            $terpasang[$a['kategori_id']][$kunci] = [
                'assignment_id' => null,
                'bobot' => null,
                'status' => self::STATUS_BELUM_MULAI,
            ];

            if ($a['maks_rater'] !== null && count($terpasang[$a['kategori_id']]) > $a['maks_rater']) {
                throw new RuntimeException(
                    "Kategori {$kode} dibatasi {$a['maks_rater']} penilai menurut aturan levelnya; "
                    .'penugasan ini melewatinya.'
                );
            }

            $baris[] = [
                'PA_Participant_Id' => $participantId,
                'Kode_Perusahaan_Penilai' => $kp,
                'Kode_Karyawan_Penilai' => $kk,
                // Nama dibekukan sekarang. Penilai yang berganti nama atau keluar
                // setelah siklus berjalan tetap terbaca sebagaimana saat ditugaskan.
                'Nama_Penilai_Saat_Cycle' => $nama,
                'PA_Rater_Category_Id' => $a['kategori_id'],
                // Bobot sementara; nilai sebenarnya dihitung ulang di bawah setelah
                // seluruh anggota kategorinya diketahui.
                'Bobot_Dalam_Kategori' => 0,
                'Sumber_Penugasan' => $sumber,
                'Organization_Relationship_Id' => $p['organization_relationship_id'] ?? null,
                'Alasan_Manual' => $p['alasan_manual'] ?? null,
                'Status_Penugasan' => self::STATUS_BELUM_MULAI,
                /* Tenggat diwarisi dari jendela pengisian siklus, dan boleh ditimpa
                   per penugasan. Tanpa ini kolomnya selalu kosong — dan tenggat yang
                   kosong membuat "terlambat" mustahil dihitung, sehingga penilai yang
                   tidak mengisi baru ketahuan saat perhitungan, ketika siklusnya sudah
                   ditutup dan tak ada lagi yang bisa dilakukan selain menahan hasilnya. */
                'Due_At' => $p['due_at'] ?? $tenggat,
                'Flag_Aktif' => 'Y',
                'Created_By' => $olehUserId,
            ];

            $kategoriTersentuh[$a['kategori_id']] = true;
        }

        return [$baris, $kategoriTersentuh];
    }

    /*
     |--------------------------------------------------------------------------
     | Pemanas memo untuk pembentukan massal
     |--------------------------------------------------------------------------
     |
     | Ketiga metode di bawah TIDAK mengubah satu pun aturan. Mereka hanya menyediakan lebih
     | dulu data yang toh akan dibaca, dalam satu kueri untuk ratusan peserta alih-alih satu
     | kueri per peserta.
     |
     | Itulah bentuk optimasi yang aman di sini: yang dipercepat bukan logikanya, melainkan
     | cara datanya sampai. `usulkan()` dan `tugaskan()` tetap berjalan persis seperti semula
     | dan tetap melewati seluruh penjaganya — mereka hanya menemukan jawabannya sudah di
     | tangan alih-alih harus menyeberang ke Google Cloud dan kembali, 52 ms sekali jalan.
     */

    /** Memuat peserta sekaligus. Satu kueri untuk seluruh potongan. */
    private function hangatkanPeserta(array $ids): void
    {
        $belum = array_values(array_diff($ids, array_keys($this->memoPeserta)));

        if ($belum === []) {
            return;
        }

        foreach (array_chunk($belum, 500) as $potongan) {
            foreach (DB::table('N_PA_Participant')
                ->whereIn('Id_PA_Participant', $potongan)
                ->get([
                    'Id_PA_Participant', 'PA_Cycle_Id', 'Kode_Perusahaan', 'Kode_Karyawan',
                    'Nama_Karyawan_Saat_Cycle', 'Status_Peserta',
                ]) as $r) {
                $this->memoPeserta[(int) $r->Id_PA_Participant] = $r;
            }
        }
    }

    /**
     * Memuat penugasan aktif sekaligus.
     *
     * Peserta yang TIDAK punya penugasan tetap dicatat sebagai array kosong — kalau tidak,
     * memo-nya meleset dan tiap peserta tanpa penugasan (yaitu semuanya, pada pembentukan
     * pertama) tetap menembak database satu per satu.
     */
    private function hangatkanPenugasanAktif(array $ids): void
    {
        $belum = array_values(array_diff($ids, array_keys($this->memoPenugasanAktif)));

        if ($belum === []) {
            return;
        }

        foreach ($belum as $id) {
            $this->memoPenugasanAktif[(int) $id] = [];
        }

        foreach (array_chunk($belum, 500) as $potongan) {
            foreach (DB::table('N_PA_Assignment')
                ->whereIn('PA_Participant_Id', $potongan)
                ->where('Flag_Aktif', 'Y')
                ->get([
                    'Id_PA_Assignment', 'PA_Participant_Id', 'PA_Rater_Category_Id',
                    'Kode_Perusahaan_Penilai', 'Kode_Karyawan_Penilai',
                    'Bobot_Dalam_Kategori', 'Status_Penugasan',
                ]) as $r) {
                $this->memoPenugasanAktif[(int) $r->PA_Participant_Id][(int) $r->PA_Rater_Category_Id][$r->Kode_Perusahaan_Penilai.'|'.$r->Kode_Karyawan_Penilai] = [
                    /* Cast-nya HARUS sama persis dengan `penugasanAktif()`. Pemanas yang
                           memulangkan "100.00" alih-alih 100.0 menghasilkan bentuk yang
                           berbeda untuk data yang sama — dan perbedaan diam seperti itu baru
                           terlihat sebagai selisih di layar, bukan sebagai galat. */
                    'assignment_id' => (int) $r->Id_PA_Assignment,
                    'bobot' => (float) $r->Bobot_Dalam_Kategori,
                    'status' => $r->Status_Penugasan,
                ];
            }
        }
    }

    /**
     * Memuat calon penilai dari relasi organisasi sekaligus.
     *
     * Penyaring dan urutannya PERSIS sama dengan `calonDariRelasi()` — kalau berbeda, usulan
     * massal dan usulan satuan akan menghasilkan calon yang berlainan, dan selisihnya hanya
     * akan terlihat sebagai "kenapa penilai orang ini tidak sama dengan kemarin".
     *
     * @param  list<object>  $pesertaList
     * @param  list<int>  $tipeIds
     */
    private function hangatkanCalon(array $pesertaList, array $tipeIds, string $acuan): void
    {
        if ($pesertaList === [] || $tipeIds === []) {
            return;
        }

        /* Semua sel ditandai kosong lebih dulu. Peserta yang memang tidak punya relasi harus
           terjawab dari memo, bukan jatuh kembali ke kueri per orang. */
        $indeks = [];

        foreach ($pesertaList as $p) {
            foreach ($tipeIds as $t) {
                $this->memoCalon[$p->Id_PA_Participant.'|'.$t] = [];
            }

            $indeks[$p->Kode_Perusahaan.'|'.$p->Kode_Karyawan] = (int) $p->Id_PA_Participant;
        }

        foreach (array_chunk($pesertaList, 100) as $potongan) {
            $baris = DB::table('N_HRIS_Organization_Relationship as r')
                ->join('Karyawan as k', function ($j) {
                    $j->on('k.Kode_Perusahaan', '=', 'r.Kode_Perusahaan_Terkait')
                        ->on('k.Kode_Karyawan', '=', 'r.Kode_Karyawan_Terkait');
                })
                ->join(
                    'N_HRIS_Organization_Relationship_Type as t',
                    't.Id_Organization_Relationship_Type',
                    '=',
                    'r.Id_Organization_Relationship_Type',
                )
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
                    'r.Id_Organization_Relationship', 'r.Kode_Perusahaan_Terkait',
                    'r.Kode_Karyawan_Terkait', 'k.Nama', 't.Kode_Relasi',
                ]);

            foreach ($baris as $r) {
                $pid = $indeks[$r->Kode_Perusahaan_Subjek.'|'.$r->Kode_Karyawan_Subjek] ?? null;

                if ($pid === null) {
                    continue;
                }

                $this->memoCalon[$pid.'|'.$r->Id_Organization_Relationship_Type][] = [
                    'kode_perusahaan' => (string) $r->Kode_Perusahaan_Terkait,
                    'kode_karyawan' => (string) $r->Kode_Karyawan_Terkait,
                    'nama' => (string) $r->Nama,
                    'organization_relationship_id' => (int) $r->Id_Organization_Relationship,
                    'kode_relasi' => $r->Kode_Relasi,
                    'sudah_ditugaskan' => false,
                ];
            }
        }
    }

    /**
     * Kemajuan pembentukan penugasan satu siklus. DUA kueri, sengaja ringan.
     *
     * Dipanggil berulang oleh layar selama pembentukan berjalan. Ringkasan kesehatan yang
     * lengkap terlalu mahal untuk itu — ia membaca aturan tiap level dan menghitung kekurangan
     * per kategori, dan menjadikannya sumber progres berarti layar membebani database justru
     * ketika database sedang sibuk membentuk penugasan.
     *
     * @return array{total:int, sudah:int, belum:int, penugasan:int, selesai:bool}
     */
    public function progresPembentukan(int $cycleId): array
    {
        $total = (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->where('Flag_Aktif', 'Y')
            ->count();

        $belum = $this->pesertaTanpaPenugasan($cycleId);

        $penugasan = (int) DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('a.Flag_Aktif', 'Y')
            ->count();

        return [
            'total' => $total,
            'sudah' => max(0, $total - $belum),
            'belum' => $belum,
            'penugasan' => $penugasan,

            /* "Selesai" = tidak ada lagi peserta TANPA penugasan. Bukan "kuotanya terpenuhi" —
               sebagian peserta memang tidak punya cukup calon, dan menunggu angka itu nol akan
               membuat pemantauan berputar selamanya. Kekurangan kuota dilaporkan terpisah. */
            'selesai' => $total > 0 && $belum === 0,
        ];
    }

    /** Peserta DIIKUTKAN yang belum punya SATU pun penugasan aktif. Satu kueri. */
    public function pesertaTanpaPenugasan(int $cycleId): int
    {
        return (int) DB::table('N_PA_Participant as p')
            ->leftJoin('N_PA_Assignment as a', function ($j) {
                $j->on('a.PA_Participant_Id', '=', 'p.Id_PA_Participant')->where('a.Flag_Aktif', '=', 'Y');
            })
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('p.Flag_Aktif', 'Y')
            ->whereNull('a.Id_PA_Assignment')
            ->count();
    }

    /**
     * Menugaskan penilai DAN menetapkan bobotnya dalam SATU transaksi.
     *
     * KENAPA DISATUKAN
     *
     * Sebelumnya layar memanggil dua endpoint berurutan, dengan penyegaran usulan di antaranya
     * untuk mengetahui id penugasan penilai yang baru dibuat. Akibatnya tiga hal:
     *
     *   - dua penyegaran untuk satu kali Simpan, dan pada DB ini satu penyegaran mahal;
     *   - ada jeda nyata di mana penugasannya sudah tersimpan sementara bobotnya belum, dan
     *     kegagalan di jeda itu meninggalkan pekerjaan setengah;
     *   - bagi admin ia terasa seperti dua penyimpanan, karena memang begitu.
     *
     * Bobotnya diterima berdasarkan IDENTITAS ORANG, bukan id penugasan. Itu yang menghapus
     * kebutuhan penyegaran di tengah: penilai yang barisnya baru lahir dalam transaksi ini pun
     * bisa ditunjuk, karena yang dipakai kode perusahaan dan kode karyawannya.
     *
     * @param  list<array<string, mixed>>  $penilai  penilai BARU yang akan dibuat
     * @param  list<array<string, mixed>>  $bobot  [{kode_kategori, kode_perusahaan, kode_karyawan, bobot}]
     * @param  list<string>  $kategoriRata  kode kategori yang dikembalikan ke pembagian rata
     */
    public function tugaskan(
        int $participantId,
        array $penilai,
        ?int $olehUserId = null,
        array $bobot = [],
        array $kategoriRata = [],
        bool $tundaPemberitahuan = false,
    ): array {
        $this->gate->pastikanBolehUntukPeserta(PaCycleGate::TAMBAH_PENILAI, $participantId);

        /* Menambah penilai boleh sampai pengisian dibuka, tetapi MENGUBAH bobot hanya selama
           penugasan masih boleh dibentuk — gerbang yang sama dengan endpoint bobot khusus.
           Tanpa ini bobot penilai bisa digeser lewat jalur tambah-penilai sesudah siklus
           dikunci, sementara hasil yang sudah dihitung tidak ikut dihitung ulang. */
        if ($bobot !== [] || $kategoriRata !== []) {
            $this->gate->pastikanBolehUntukPeserta(PaCycleGate::BUAT_PENUGASAN, $participantId);
        }

        $peserta = $this->peserta($participantId);

        /* HASIL YANG SUDAH TERBIT TIDAK BOLEH DITAMBAHI PENILAI.

           Menambah penilai membuat pesertanya terhitung belum lengkap lagi, dan itu benar
           selama angkanya belum keluar. Tetapi bila hasilnya SUDAH TERBIT, orangnya sudah
           melihat nilainya — menambahkan penilai berarti nilai itu kelak berubah tanpa ada
           yang pernah mengatakannya. Yang benar adalah membatalkan penerbitannya lebih
           dulu, lewat jalur yang memang punya jejaknya sendiri. */
        $sudahTerbit = DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->where('Status_Hasil', 'TERBIT')
            ->exists();

        if ($sudahTerbit) {
            throw new RuntimeException(
                "Hasil peserta {$participantId} sudah TERBIT. Menambah penilai sekarang "
                .'membuat nilai yang sudah dilihat orang berubah kelak tanpa pemberitahuan. '
                .'Batalkan penerbitannya lebih dulu bila memang perlu dinilai ulang.'
            );
        }

        if ($peserta->Status_Peserta !== 'DIIKUTKAN') {
            throw new RuntimeException(
                "Peserta {$participantId} berstatus {$peserta->Status_Peserta}; "
                .'hanya peserta DIIKUTKAN yang boleh diberi penilai.'
            );
        }

        /* Tidak ada yang perlu dikerjakan sama sekali. Bentuk kembaliannya disamakan dengan
           jalur biasa: pemanggil membaca kuncinya tanpa tahu cabang mana yang dilaluinya, dan
           kunci yang kadang ada kadang tidak adalah undefined yang menunggu giliran. */
        if ($penilai === [] && $bobot === [] && $kategoriRata === []) {
            return [
                'dibuat' => 0,
                'kategori_dihitung_ulang' => [],
                'bobot_kustom_digugurkan' => [],
                'bobot_diterapkan' => 0,
                'kategori_dikembalikan_rata' => [],
            ];
        }

        $aturan = $this->resolver->raterRules($participantId);
        $tenggat = $this->tenggatSiklus((int) $peserta->PA_Cycle_Id);

        /* Seluruh penilai dibaca SEKALI, bukan satu kueri per orang. Validasinya tidak
           berubah — `namaKaryawanAktif()` tetap menolak yang tidak ada dan yang nonaktif,
           hanya sumber datanya yang sudah di tangan. */
        $petaKaryawan = $this->karyawanBanyak(array_map(
            fn ($p) => [(string) ($p['kode_perusahaan'] ?? ''), (string) ($p['kode_karyawan'] ?? '')],
            $penilai,
        ));

        $this->sidikRelasi = $this->sidikRelasiSah($peserta, $penilai, $aturan);

        /* Batas id SEBELUM menyisipkan, supaya baris baru bisa dikenali sesudahnya tanpa
           menebak. Insert-nya massal sehingga tidak memulangkan id, dan menyaring per
           peserta membuat batas ini aman terhadap penyisipan bersamaan untuk orang lain:
           id bertambah monoton, jadi baris milik peserta ini yang ber-id lebih besar
           pasti baru saja dibuat. */
        $idSebelum = (int) DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->max('Id_PA_Assignment');

        $hasil = DB::transaction(function () use ($participantId, $peserta, $penilai, $aturan, $tenggat, $olehUserId, $bobot, $kategoriRata, $petaKaryawan) {
            DB::table('N_PA_Participant')
                ->where('Id_PA_Participant', $participantId)
                ->lockForUpdate()
                ->first(['Id_PA_Participant']);

            $terpasang = $this->penugasanAktif($participantId);
            $baris = [];
            $kategoriTersentuh = [];

            [$baris, $kategoriTersentuh] = $this->susunBarisPenugasan(
                $peserta,
                $aturan,
                $penilai,
                $terpasang,
                $petaKaryawan,
                $tenggat,
                $olehUserId,
            );

            foreach (array_chunk($baris, 100) as $potongan) {
                DB::table('N_PA_Assignment')->insert($potongan);
            }

            $bobotKustomDigugurkan = [];

            foreach (array_keys($kategoriTersentuh) as $kategoriId) {
                $hasil = $this->hitungUlangBobot($participantId, (int) $kategoriId, $olehUserId);

                if ($hasil['digugurkan'] ?? false) {
                    $bobotKustomDigugurkan[] = (int) $kategoriId;
                }
            }

            /*
             | URUTANNYA MENGIKAT.
             |
             | 1. penyisipan  - barisnya lahir
             | 2. hitung ulang - kategori yang anggotanya berubah kembali rata
             | 3. kembalikan rata - kategori yang admin minta ikut bawaan, penandanya dilepas
             | 4. terapkan bobot khusus - angka keputusan admin, menimpa hasil langkah 2
             |
             | Menukar 4 dengan 2 berarti pembagian rata menimpa angka yang baru saja ditetapkan.
             */
            $dikembalikanRata = [];

            foreach ($kategoriRata as $kode) {
                $kodeKategori = strtoupper(trim((string) $kode));
                $kategoriId = $aturan[$kodeKategori]['kategori_id'] ?? null;

                if ($kategoriId === null) {
                    throw new RuntimeException(
                        "Kategori {$kodeKategori} tidak berlaku bagi peserta {$participantId}."
                    );
                }

                $this->lepasPenandaKustom($participantId, (int) $kategoriId);
                $this->hitungUlangBobot($participantId, (int) $kategoriId, $olehUserId);
                $dikembalikanRata[] = (int) $kategoriId;
            }

            $bobotDiterapkan = 0;

            if ($bobot !== []) {
                $peta = $this->petakanBobotIdentitas($participantId, $bobot, $aturan);
                $bobotDiterapkan = $this->terapkanBobot($participantId, $peta, $olehUserId)['diperbarui'];
            }

            return [
                'dibuat' => count($baris),
                'kategori_dihitung_ulang' => array_map('intval', array_keys($kategoriTersentuh)),
                'bobot_diterapkan' => $bobotDiterapkan,
                'kategori_dikembalikan_rata' => $dikembalikanRata,

                /* Kategori yang bobot khususnya GUGUR karena susunan penilainya berubah.
                   Dikembalikan supaya layar bisa mengatakannya; perubahan bobot yang tidak
                   diberitahukan mengubah nilai akhir seseorang tanpa ada yang tahu. */
                'bobot_kustom_digugurkan' => $bobotKustomDigugurkan,
            ];
        });

        /* SESUDAH COMMIT, bukan di dalam transaksi.
           `N_HRIS_Notification` di-polling topbar setiap menit oleh setiap orang yang
           sedang membuka HCIS. Menulisinya di dalam transaksi yang masih memegang kunci
           pada tabel penugasan pernah membuat kolam koneksi habis di modul lain, dan
           seluruh sistem menjawab "Login timeout expired". */
        if ($hasil['dibuat'] > 0) {
            $idBaru = DB::table('N_PA_Assignment')
                ->where('PA_Participant_Id', $participantId)
                ->where('Id_PA_Assignment', '>', $idSebelum)
                ->where('Flag_Aktif', 'Y')
                ->pluck('Id_PA_Assignment')
                ->map(fn ($id) => (int) $id)
                ->all();

            $hasil['id_baru'] = $idBaru;

            /* Memo penugasan aktif peserta ini SUDAH BASI begitu barisnya bertambah. Membiarkannya
               berarti pemanggil berikutnya membaca keadaan sebelum penulisan ini — dan pada
               pembentukan massal itu berarti penilai yang sama ditugaskan dua kali. */
            unset($this->memoPenugasanAktif[$participantId]);

            /*
             | Pemberitahuannya boleh DITUNDA, dan pada pembentukan massal memang ditunda.
             |
             | Per peserta ia tiga kueri; dikalikan 859 peserta itu 2.577 perjalanan jaringan
             | hanya untuk memberi tahu — lebih lama daripada membentuk penugasannya sendiri.
             | Pemanggil massal mengumpulkan idnya lalu memberitahukan SEKALI di akhir, dan
             | penilainya tetap menerima kabar yang sama.
             |
             | Tidak dihapus, hanya ditunda: `penugasanDibentuk` adalah SATU-SATUNYA kabar yang
             | diterima penilai bahwa ia punya penilaian. Membuangnya berarti 894 orang tidak
             | pernah tahu, dan mereka baru mendengar saat pengingat tenggat menyala.
             */
            if (! $tundaPemberitahuan) {
                $this->pemberitahuan->penugasanDibentuk($idBaru);
            }
        }

        return $hasil;
    }

    /**
     * Membatalkan satu penugasan. Alasannya wajib.
     *
     * Penugasan yang sudah SELESAI tidak boleh dibatalkan: payload-nya sudah masuk dan
     * membatalkannya akan membuat nilai yang sudah dihitung tidak bisa direproduksi.
     * Yang benar untuk kasus itu adalah menghitung ulang hasilnya, bukan menghapus
     * jejak penilainya.
     */
    public function batalkan(int $assignmentId, string $alasan, ?int $olehUserId = null): void
    {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw new RuntimeException('Pembatalan penugasan wajib beralasan.');
        }

        $this->gate->pastikanBolehUntukPenugasan(PaCycleGate::BATALKAN_PENUGASAN, $assignmentId);

        /* Ditangkap KELUAR dari transaksi: pemicu perhitungan di bawah berjalan sesudah
           commit, dan variabel yang hanya hidup di dalam closure tidak sampai ke sana. */
        $participantId = 0;

        DB::transaction(function () use ($assignmentId, $alasan, $olehUserId, &$participantId) {
            $tugas = DB::table('N_PA_Assignment')
                ->where('Id_PA_Assignment', $assignmentId)
                ->lockForUpdate()
                ->first(['Id_PA_Assignment', 'PA_Participant_Id', 'PA_Rater_Category_Id',
                    'Status_Penugasan', 'Flag_Aktif']);

            if ($tugas === null) {
                throw new RuntimeException("Penugasan {$assignmentId} tidak ada.");
            }

            if ($tugas->Flag_Aktif !== 'Y') {
                throw new RuntimeException("Penugasan {$assignmentId} sudah tidak aktif.");
            }

            /* Bukan hanya SELESAI: lembar TERKUMPUL dan MENUNGGU_VERIFIKASI juga sudah berisi
               jawaban yang terpetakan. Membatalkannya membuang pekerjaan penilai yang sudah
               dilakukan, dan ia tidak bisa mengirim ulang lembar yang sama. */
            if (in_array($tugas->Status_Penugasan, PaStatusPenugasan::SUDAH_DIKERJAKAN, true)) {
                throw new RuntimeException(
                    "Penugasan {$assignmentId} sudah diisi penilainya ({$tugas->Status_Penugasan}); "
                    .'membatalkannya membuang jawaban yang sudah masuk dan membuat hasil tak bisa direproduksi. '
                    .'Hitung ulang hasilnya, jangan hapus jejak penilainya.'
                );
            }

            $sekarang = $this->sekarang();

            DB::table('N_PA_Assignment')
                ->where('Id_PA_Assignment', $assignmentId)
                ->update([
                    'Status_Penugasan' => self::STATUS_DIBATALKAN,
                    'Cancel_Reason' => $alasan,
                    'Cancelled_At' => $sekarang,
                    'Cancelled_By' => $olehUserId,
                    // Turun supaya penilai yang sama boleh ditugaskan lagi; unique index
                    // penugasan hanya menyaring baris aktif.
                    'Flag_Aktif' => 'T',
                    'Updated_At' => $sekarang,
                    'Updated_By' => $olehUserId,
                ]);

            $participantId = (int) $tugas->PA_Participant_Id;

            unset($this->memoPenugasanAktif[$participantId]);

            $this->hitungUlangBobot(
                (int) $tugas->PA_Participant_Id,
                (int) $tugas->PA_Rater_Category_Id,
                $olehUserId
            );
        });

        /*
         | SESUDAH COMMIT TERLUAR, bukan sesudah transaksi metode ini saja (14 Okt 2026).
         |
         | Revisi peserta dan ubah status membatalkan beberapa penugasan di dalam SATU transaksi
         | luar. Ditulis langsung di sini, pemberitahuan dan pemeriksaan kelengkapan di bawah tetap
         | berjalan selagi kunci penugasan transaksi luar masih dipegang — persis keadaan yang
         | menghabiskan kolam koneksi. `afterCommit` menundanya sampai transaksi terluar selesai
         | (dan membuangnya bila transaksi itu dibatalkan); tanpa transaksi luar ia langsung jalan.
         */
        DB::afterCommit(function () use ($assignmentId, $participantId, $olehUserId) {
            /* SESUDAH COMMIT, alasan yang sama dengan `tugaskan()` di atas: menulis
               `N_HRIS_Notification` sambil memegang kunci pada tabel penugasan pernah
               menghabiskan kolam koneksi di modul lain.

               Sampai 2 September 2026 pembatalan tidak memberi tahu siapa pun — terukur 16
               pemberitahuan saat penugasan dibentuk, NOL saat dibatalkan. Penilai tetap
               memegang tautannya dan tetap mengerjakannya. Ini pasangan wajib dari penjaga
               di `PaPayloadMapperService`: penjaga tanpa pemberitahuan hanya menolak
               pekerjaan orang tanpa pernah memberitahunya lebih dulu. */
            $this->pemberitahuan->penugasanDibatalkan([$assignmentId]);

            /* JADWAL HC LEARN-NYA IKUT DIHAPUS (13 Okt 2026). Tanpa ini penilai yang dibatalkan
               tetap memegang tautan aktif, mengisi lembarnya, lalu kirimannya ditolak penjaga di
               `PaPayloadMapperService`. Lewat antrean — pembatalan tidak menunggu HC Learn, dan
               tidak ikut gagal bila HC Learn menolak; sisanya tampil di Langkah 5. */
            try {
                HapusJadwalHcLearnPaJob::dispatch([$assignmentId], null, $olehUserId);
            } catch (\Throwable $e) {
                Log::error('PA: gagal mengantrekan hapus jadwal HC Learn', [
                    'assignment_id' => $assignmentId,
                    'galat' => $e->getMessage(),
                ]);
            }

            /*
             | PEMBATALAN JUGA BISA MELENGKAPKAN SESEORANG.
             |
             | Aturannya satu kalimat: begitu tidak ada lagi penilai yang ditunggu, hasilnya
             | dihitung. Sampai 7 September 2026 aturan itu hanya berlaku pada satu jalan —
             | kiriman penilai terakhir — padahal ada jalan kedua yang menghasilkan keadaan yang
             | sama persis: penilai terakhir yang ditunggu justru DIBATALKAN. `sisaPenugasan()`
             | memang tidak menghitung yang dibatalkan, jadi orang itu lengkap sejak detik ini.
             |
             | Tanpa ini hasilnya menunggu admin menekan "Hitung" secara manual, dan yang paling
             | mungkin terjadi adalah tidak ada yang menekannya: pembatalan dilakukan justru
             | ketika admin sedang mengurus hal lain, dan orang yang menjadi lengkap karenanya
             | tidak muncul di keranjang mana pun sebagai pekerjaan baru.
             |
             | SESUDAH COMMIT, dan sengaja: pekerjaan antrean yang dijadwalkan dari dalam
             | transaksi bisa mulai berjalan sebelum transaksinya selesai, lalu membaca keadaan
             | yang belum ada. Kegagalan mengantre juga tidak boleh menggagalkan pembatalannya —
             | pembatalannya sudah sah dan sudah dikabarkan, dan hasilnya tetap bisa dihitung
             | admin kapan saja.
             */
            $this->picuPerhitunganBilaLengkap($participantId, $olehUserId);
        });
    }

    /**
     * Mengantrekan perhitungan bila peserta tidak lagi menunggu penilai mana pun.
     *
     * Kelengkapannya DITANYAKAN ke pemilik aturannya, bukan dihitung ulang di sini — sama
     * seperti yang dilakukan pemeta payload. Dua tempat yang menjawab "sudah lengkap belum"
     * akan menyimpang, dan yang menyimpang di sini menentukan kapan angka seseorang lahir.
     */
    private function picuPerhitunganBilaLengkap(int $participantId, ?int $olehUserId): void
    {
        if ($participantId === 0) {
            return;
        }

        try {
            if (app(\App\Services\KPI\PerformanceAppraisal\PaResultCalculationService::class)->sisaPenugasan($participantId) > 0) {
                return;
            }

            HitungHasilPesertaJob::dispatch($participantId, $olehUserId);
        } catch (\Throwable $e) {
            Log::error('PA: gagal mengantrekan perhitungan sesudah pembatalan', [
                'participant_id' => $participantId,
                'galat' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Membagi 100 rata di antara penilai aktif satu kategori.
     *
     * Kategori yang kehabisan penilai tidak menyisakan apa-apa untuk dibagi, dan itu
     * bukan kesalahan — redistribusi antar kategori yang menanganinya saat hasil
     * dihitung.
     */
    /**
     * Mencari calon penilai untuk satu kategori, LENGKAP dengan alasan bila tidak bisa dipilih.
     *
     * KENAPA ALASANNYA DIHITUNG DI SINI
     *
     * Yang menentukan boleh-tidaknya adalah aturan relasi organisasi: satu pasang orang hanya
     * boleh punya satu jenis hubungan. Layar tidak punya bahan untuk menilainya — ia tidak tahu
     * relasi apa yang sudah ada di antara peserta dan tiap kandidat.
     *
     * Menyerahkannya ke layar berarti menuliskan ulang aturan yang sama di dua tempat, dan yang
     * satu akan menyimpang saat aturannya berubah. Di sini layar hanya menampilkan apa yang
     * sudah diputuskan.
     *
     * KENAPA DIKEMBALIKAN, BUKAN DISARING
     *
     * Yang tidak bisa dipilih tetap dipulangkan, bertanda dan beralasan. Membuangnya dari hasil
     * membuat admin mencari nama yang ia tahu ada, tidak menemukannya, lalu menyimpulkan
     * pencariannya rusak — padahal orangnya memang tidak boleh dipilih, dan itu keterangan yang
     * paling ia butuhkan.
     *
     * @return array{baris: list<array<string, mixed>>, total: int, batas: int, dipangkas: bool}
     */
    public function cariCalonPenilai(
        int $participantId,
        string $kodeKategori,
        string $kata = '',
        int $batas = 15,
    ): array {
        $kodeKategori = strtoupper(trim($kodeKategori));
        $peserta = $this->peserta($participantId);
        $aturan = $this->resolver->raterRules($participantId);
        $a = $aturan[$kodeKategori] ?? null;

        if ($a === null) {
            throw new RuntimeException("Kategori {$kodeKategori} tidak berlaku bagi peserta {$participantId}.");
        }

        $tipeRelasiId = $a['relationship_type_id'] ?? null;

        if ($tipeRelasiId === null) {
            throw new RuntimeException(
                "Kategori {$kodeKategori} tidak terikat relasi organisasi, jadi penilainya tidak "
                .'bisa ditambahkan.'
            );
        }

        $kata = trim($kata);
        $batas = max(1, min(50, $batas));

        // Label level calon = Level PA (DDL-2026-177), sama dengan yang dipakai PA untuk orang itu.
        $q = PaLevelPaResolver::gabung(DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan'))
            ->leftJoin('N_PA_Level as l', 'l.ID_Level', '=', DB::raw(PaLevelPaResolver::sqlLevel()))
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Aktif', 'Y')

            /* Pesertanya sendiri tidak pernah jadi calon di kategori mana pun selain SELF, dan
               SELF tidak sampai ke sini. Dikeluarkan di SQL, bukan ditandai: "tidak bisa memilih
               diri sendiri" bukan keterangan yang menolong, ia hanya baris yang mengganggu. */
            ->where(function ($w) use ($peserta) {
                $w->where('k.Kode_Perusahaan', '<>', $peserta->Kode_Perusahaan)
                    ->orWhere('k.Kode_Karyawan', '<>', $peserta->Kode_Karyawan);
            });

        if ($kata !== '') {
            $q->where(function ($w) use ($kata) {
                $w->where('k.Nama', 'like', "%{$kata}%")
                    ->orWhere('k.Kode_Karyawan', 'like', "{$kata}%");
            });
        }

        $total = (clone $q)->count();

        $baris = $q->orderBy('k.Nama')->limit($batas)->get([
            'k.Kode_Perusahaan as kode_perusahaan',
            'k.Kode_Karyawan as kode_karyawan',
            'k.Nama as nama',
            'l.Nama_Level as nama_level',
            'vd.nama_divisi as nama_divisi',
        ])->all();

        if ($baris === []) {
            return ['baris' => [], 'total' => $total, 'batas' => $batas, 'dipangkas' => false];
        }

        /* Relasi yang sudah ada antara peserta dan orang-orang di halaman ini — SATU kueri,
           kedua arah. Menanyakannya per kandidat berarti 15 perjalanan ke database untuk satu
           ketikan. */
        $kunci = array_map(fn ($b) => $b->kode_perusahaan.'|'.$b->kode_karyawan, $baris);
        $relasi = [];

        foreach (DB::table('N_HRIS_Organization_Relationship as r')
            ->join(
                'N_HRIS_Organization_Relationship_Type as t',
                't.Id_Organization_Relationship_Type',
                '=',
                'r.Id_Organization_Relationship_Type',
            )
            ->where('r.Flag_Aktif', 'Y')
            ->whereNull('r.Cancelled_At')
            ->where(function ($w) use ($peserta) {
                $w->where(function ($x) use ($peserta) {
                    $x->where('r.Kode_Perusahaan_Subjek', $peserta->Kode_Perusahaan)
                        ->where('r.Kode_Karyawan_Subjek', $peserta->Kode_Karyawan);
                })->orWhere(function ($x) use ($peserta) {
                    $x->where('r.Kode_Perusahaan_Terkait', $peserta->Kode_Perusahaan)
                        ->where('r.Kode_Karyawan_Terkait', $peserta->Kode_Karyawan);
                });
            })
            ->get([
                't.Kode_Relasi as kode', 't.Nama_Relasi as nama',
                'r.Kode_Perusahaan_Subjek as spt', 'r.Kode_Karyawan_Subjek as skode',
                'r.Kode_Perusahaan_Terkait as tpt', 'r.Kode_Karyawan_Terkait as tkode',
            ]) as $r) {
            $maju = $r->spt === $peserta->Kode_Perusahaan && $r->skode === $peserta->Kode_Karyawan;
            $lain = $maju ? $r->tpt.'|'.$r->tkode : $r->spt.'|'.$r->skode;

            $relasi[$lain][] = ['kode' => $r->kode, 'nama' => $r->nama, 'maju' => $maju];
        }

        $terpasang = $this->penugasanAktif($participantId)[$a['kategori_id']] ?? [];

        /* Himpunan SETARA untuk penulisan (kategori ini, peserta -> kandidat). Cerminan aturan
           di `OrganizationRelationService`; kalau aturannya bergeser di sana, ia harus bergeser
           di sini juga — dan ketidakcocokannya akan terlihat sebagai kandidat yang ditawarkan
           lalu ditolak. */
        $kebalikan = ['ATASAN' => 'BAWAHAN', 'BAWAHAN' => 'ATASAN', 'PEER' => 'PEER'][$kodeKategori] ?? null;

        $hasil = array_map(function ($b) use ($relasi, $terpasang, $kodeKategori, $kebalikan) {
            $k = $b->kode_perusahaan.'|'.$b->kode_karyawan;
            $row = (array) $b;

            $row['bisa_dipilih'] = true;
            $row['alasan'] = null;

            if (isset($terpasang[$k])) {
                $row['bisa_dipilih'] = false;
                $row['alasan'] = 'Sudah menjadi penilai pada kategori ini.';

                return $row;
            }

            foreach ($relasi[$k] ?? [] as $r) {
                $setara = ($r['maju'] && $r['kode'] === $kodeKategori)
                    || (! $r['maju'] && $kebalikan !== null && $r['kode'] === $kebalikan);

                if ($setara) {
                    continue;
                }

                $row['bisa_dipilih'] = false;

                /* ARAHNYA disebut. "Sudah tercatat sebagai Atasan" tanpa arah terbaca sebagai
                   "orang ini atasannya", padahal bisa justru sebaliknya — dan admin yang salah
                   membacanya akan mencabut relasi yang keliru. */
                $row['alasan'] = $r['maju']
                    ? "Sudah tercatat sebagai {$r['nama']} bagi peserta ini. Satu pasang orang "
                        .'hanya boleh punya satu jenis hubungan — cabut relasi yang ada dulu.'
                    : "Peserta ini sudah tercatat sebagai {$r['nama']} baginya. Satu pasang orang "
                        .'hanya boleh punya satu jenis hubungan — cabut relasi yang ada dulu.';

                return $row;
            }

            return $row;
        }, $baris);

        return [
            'baris' => $hasil,
            'total' => $total,
            'batas' => $batas,

            /* Pemangkasan DIKATAKAN. Daftar yang berhenti di 15 tanpa keterangan terbaca sebagai
               "hanya segini yang cocok". */
            'dipangkas' => $total > count($hasil),
        ];
    }

    /**
     * Menambahkan satu penilai DAN relasi organisasinya sekaligus.
     *
     * KENAPA KEDUANYA, BUKAN SALAH SATU
     *
     * Sebelum ini menambah penilai di luar usulan berarti penugasan MANUAL: admin mengetik kode
     * perusahaan, kode karyawan, dan alasan; hasilnya tersimpan tanpa relasi asal. Dua akibatnya
     * sama-sama merugikan.
     *
     * Pertama, penugasannya tidak bisa ditelusuri: setahun lagi tidak ada yang bisa menjawab
     * kenapa orang itu menilai, selain sebaris alasan bebas.
     *
     * Kedua, dan ini yang lebih mahal: pengetahuan yang admin punya — "orang ini memang
     * atasannya" — hilang begitu siklusnya selesai. Siklus berikutnya mengulang pekerjaan yang
     * sama karena master relasinya tidak pernah ikut belajar.
     *
     * Di sini relasinya DIBENTUK, lalu penugasannya bersandar padanya. Penugasannya jadi
     * bersumber USULAN dengan `Organization_Relationship_Id` terisi — sama seperti penilai yang
     * datang dari relasi yang sudah ada — dan siklus berikutnya akan mengusulkannya sendiri.
     *
     * TANGGAL BERLAKUNYA MUNDUR KE TANGGAL ACUAN
     *
     * `usulkan()` membaca relasi PADA tanggal acuan siklus (`Berlaku_Dari <= acuan`). Relasi baru
     * yang berlaku hari ini TIDAK akan terlihat oleh siklus yang acuannya kemarin — dan gejalanya
     * paling menyesatkan yang mungkin: relasinya tersimpan, penugasannya gagal dibentuk, dan
     * tidak ada satu pun pesan yang menghubungkan keduanya.
     *
     * SELF TIDAK LEWAT SINI
     *
     * Penilaian diri tidak punya relasi organisasi, dan penilainya sudah pasti pesertanya
     * sendiri. Tidak ada yang perlu ditambahkan.
     *
     * @return array{relasi_dibuat:int, penugasan_dibuat:int, organization_relationship_id:int}
     */
    public function tambahPenilaiDenganRelasi(
        int $participantId,
        string $kodeKategori,
        string $kodePerusahaan,
        string $kodeKaryawan,
        ?int $olehUserId = null,
    ): array {
        $this->gate->pastikanBolehUntukPeserta(PaCycleGate::BUAT_PENUGASAN, $participantId);

        $kodeKategori = strtoupper(trim($kodeKategori));
        $peserta = $this->peserta($participantId);
        $aturan = $this->resolver->raterRules($participantId);
        $a = $aturan[$kodeKategori] ?? null;

        if ($a === null) {
            throw new RuntimeException(
                "Kategori {$kodeKategori} tidak berlaku bagi peserta {$participantId}."
            );
        }

        if ($kodeKategori === self::KATEGORI_SELF) {
            throw new RuntimeException(
                'Penilaian diri tidak bisa ditambahkan: penilainya sudah pasti pesertanya sendiri.'
            );
        }

        $tipeRelasiId = $a['relationship_type_id'] ?? null;

        if ($tipeRelasiId === null) {
            throw new RuntimeException(
                "Kategori {$kodeKategori} tidak terikat jenis relasi organisasi mana pun, "
                .'jadi penilainya tidak bisa disandarkan pada relasi. Perbaiki aturan penilainya dulu.'
            );
        }

        $kodeRelasi = DB::table('N_HRIS_Organization_Relationship_Type')
            ->where('Id_Organization_Relationship_Type', $tipeRelasiId)
            ->value('Kode_Relasi');

        if ($kodeRelasi === null) {
            throw new RuntimeException("Jenis relasi {$tipeRelasiId} tidak ada di master.");
        }

        $acuan = $this->tanggalAcuan((int) $peserta->PA_Cycle_Id);

        /* Relasinya dibentuk lebih dulu. Penjaganya berlaku penuh di sini — arah yang
           bertentangan, relasi ke diri sendiri, dan karyawan nonaktif semuanya ditolak
           `OrganizationRelationService`, bukan diperiksa ulang dengan aturan tandingan. */
        $relasi = app(OrganizationRelationService::class);

        $relasi->tambah([
            'kode_relasi' => $kodeRelasi,
            'perusahaan_subjek' => $peserta->Kode_Perusahaan,
            'kode_subjek' => $peserta->Kode_Karyawan,
            'perusahaan_terkait' => $kodePerusahaan,
            'kode_terkait' => $kodeKaryawan,

            /* Mundur ke tanggal acuan supaya siklus ini bisa melihatnya. Kalau acuannya di masa
               depan, hari ini yang dipakai — relasi yang berlaku besok bukan kebohongan yang
               pantas ditulis hanya supaya satu layar terisi. */
            'berlaku_dari' => min($acuan, now()->format('Y-m-d')),
            'sumber' => 'PA_PENUGASAN',
            'catatan' => "Dibentuk saat menambahkan penilai {$kodeKategori} pada siklus "
                .$peserta->PA_Cycle_Id.'.',
        ], $olehUserId);

        /* Id relasinya dibaca SESUDAH pembentukan, bukan diasumsikan dari nilai balik: kalau
           relasinya sudah ada sebelumnya, `tambah()` tidak membuat apa-apa dan tetap benar —
           yang dibutuhkan penugasan adalah id yang berlaku, bukan id yang baru dibuat. */
        $relationshipId = DB::table('N_HRIS_Organization_Relationship')
            ->where('Id_Organization_Relationship_Type', $tipeRelasiId)
            ->where('Kode_Perusahaan_Subjek', $peserta->Kode_Perusahaan)
            ->where('Kode_Karyawan_Subjek', $peserta->Kode_Karyawan)
            ->where('Kode_Perusahaan_Terkait', $kodePerusahaan)
            ->where('Kode_Karyawan_Terkait', $kodeKaryawan)
            ->where('Flag_Aktif', 'Y')
            ->whereNull('Cancelled_At')
            ->where('Berlaku_Dari', '<=', $acuan)

            /*
             | Masa berlaku yang SUDAH BERAKHIR ikut disaring — sebelumnya tidak.
             |
             | Akibatnya jalur penambahan menerima relasi yang sudah usai sebelum tanggal acuan,
             | sementara `calonDariRelasi()` yang menyusun daftar calon menolaknya. Dua jalur yang
             | membaca organisasi yang sama dengan syarat yang berbeda: orang yang tidak pernah
             | muncul sebagai calon tetap bisa ditugaskan lewat jalur ini, dan penugasannya
             | bersandar pada hubungan yang menurut siklusnya sendiri sudah tidak ada.
             */
            ->where(function ($q) use ($acuan) {
                $q->whereNull('Berlaku_Sampai')->orWhere('Berlaku_Sampai', '>=', $acuan);
            })
            ->orderByDesc('Id_Organization_Relationship')
            ->value('Id_Organization_Relationship');

        $dimundurkan = false;

        if ($relationshipId === null) {
            /*
             | RELASINYA ADA, TETAPI MULAI BERLAKU SESUDAH TANGGAL ACUAN SIKLUS.
             |
             | Terjadi ketika pasangan ini sudah pernah dihubungkan — lewat layar relasi, contoh
             | data, atau percobaan sebelumnya — dengan `Berlaku_Dari` hari itu, sementara siklus
             | membaca organisasi pada tanggal yang lebih awal. `tambah()` benar tidak membuat
             | baris kedua (satu pasang hanya boleh punya satu hubungan), sehingga pencarian
             | dengan syarat tanggal tidak menemukan apa pun.
             |
             | Versi sebelumnya berhenti di sini dengan galat. Akibatnya buntu permanen: relasinya
             | sudah ada jadi tidak akan pernah dibuat ulang, tanggalnya tidak akan pernah
             | berubah sendiri, dan admin mengulang penambahan yang sama tanpa pernah berhasil.
             |
             | Yang benar adalah MEMUNDURKAN masa berlakunya ke tanggal acuan. Tanggal itu semula
             | hanyalah "hari ketika relasinya dicatat" — bukan pernyataan bahwa hubungannya baru
             | dimulai hari itu. Sementara tindakan admin sekarang justru menyatakan hubungan itu
             | berlaku bagi siklus ini. Perubahannya DILAPORKAN, bukan dilakukan diam-diam.
             */
            $adaTanpaTanggal = DB::table('N_HRIS_Organization_Relationship')
                ->where('Id_Organization_Relationship_Type', $tipeRelasiId)
                ->where('Kode_Perusahaan_Subjek', $peserta->Kode_Perusahaan)
                ->where('Kode_Karyawan_Subjek', $peserta->Kode_Karyawan)
                ->where('Kode_Perusahaan_Terkait', $kodePerusahaan)
                ->where('Kode_Karyawan_Terkait', $kodeKaryawan)
                ->where('Flag_Aktif', 'Y')
                ->whereNull('Cancelled_At')
                ->orderByDesc('Id_Organization_Relationship')
                ->first(['Id_Organization_Relationship', 'Berlaku_Dari', 'Berlaku_Sampai']);

            if ($adaTanpaTanggal === null) {
                throw new RuntimeException(
                    'Relasi organisasinya tidak terbentuk, sehingga penugasannya tidak bisa '
                    .'disandarkan pada apa pun.'
                );
            }

            /* Masa berlakunya yang BERAKHIR sebelum acuan adalah hal lain sama sekali: itu
               hubungan yang memang sudah usai, dan memundurkan awalnya tidak membuatnya berlaku
               kembali. Ditolak dengan sebab yang menyebut tanggalnya. */
            $sampai = $adaTanpaTanggal->Berlaku_Sampai;

            if ($sampai !== null && substr((string) $sampai, 0, 10) < $acuan) {
                throw new RuntimeException(
                    'Relasi organisasinya sudah berakhir pada '.substr((string) $sampai, 0, 10)
                    .", sementara siklus ini membaca organisasi pada {$acuan}. Perpanjang dulu "
                    .'masa berlakunya di halaman Relasi Organisasi.'
                );
            }

            /*
             | KEDUA ARAHNYA dimundurkan, bukan hanya yang maju.
             |
             | `OrganizationRelationService` menulis relasi hierarkis BERPASANGAN: menambah
             | "A ATASAN B" ikut menulis cerminnya "B BAWAHAN A". Keduanya adalah satu fakta yang
             | disimpan dua kali, dan `calonDariRelasi()` membaca masing-masing dari sisinya
             | sendiri — daftar ATASAN peserta A dibaca dari baris maju, daftar BAWAHAN peserta B
             | dibaca dari baris cermin.
             |
             | Versi sebelumnya hanya menyentuh baris maju. Akibatnya A melihat B sebagai
             | atasannya, tetapi B TIDAK melihat A sebagai bawahannya — satu fakta yang berlaku
             | dari satu sisi saja. Layarnya tidak pernah mengeluh; yang terlihat hanyalah
             | kategori BAWAHAN yang kosong pada peserta yang jelas punya bawahan, dan sebabnya
             | berada di halaman peserta yang sama sekali lain.
             |
             | Cerminnya dicari lewat PASANGAN ORANGNYA, bukan lewat jenis relasinya: yang
             | menjadikan dua baris satu fakta adalah pasangan itu, dan aturan "satu pasang hanya
             | punya satu hubungan" menjamin tidak ada baris lain yang ikut terbawa.
             */
            $sasaran = [(int) $adaTanpaTanggal->Id_Organization_Relationship];

            $cermin = DB::table('N_HRIS_Organization_Relationship')
                ->where('Kode_Perusahaan_Subjek', $kodePerusahaan)
                ->where('Kode_Karyawan_Subjek', $kodeKaryawan)
                ->where('Kode_Perusahaan_Terkait', $peserta->Kode_Perusahaan)
                ->where('Kode_Karyawan_Terkait', $peserta->Kode_Karyawan)
                ->where('Flag_Aktif', 'Y')
                ->whereNull('Cancelled_At')
                ->where('Berlaku_Dari', '>', $acuan)
                ->pluck('Id_Organization_Relationship')
                ->all();

            foreach ($cermin as $idCermin) {
                $sasaran[] = (int) $idCermin;
            }

            DB::table('N_HRIS_Organization_Relationship')
                ->whereIn('Id_Organization_Relationship', $sasaran)
                ->update([
                    'Berlaku_Dari' => $acuan,
                    'Updated_At' => $this->sekarang(),
                    'Updated_By' => $olehUserId,
                ]);

            $relationshipId = (int) $adaTanpaTanggal->Id_Organization_Relationship;
            $dimundurkan = true;
        }

        $hasil = $this->tugaskan($participantId, [[
            'kode_kategori' => $kodeKategori,
            'kode_perusahaan' => $kodePerusahaan,
            'kode_karyawan' => $kodeKaryawan,
            'sumber' => self::SUMBER_USULAN,
            'organization_relationship_id' => (int) $relationshipId,
        ]], $olehUserId);

        return [
            'relasi_dibuat' => 1,
            'penugasan_dibuat' => (int) ($hasil['dibuat'] ?? 0),
            'organization_relationship_id' => (int) $relationshipId,

            /* Dilaporkan supaya layar bisa mengatakannya: masa berlaku relasi yang dimundurkan
               membuatnya terlihat oleh siklus lain yang acuannya lebih awal juga. */
            'relasi_dimundurkan' => $dimundurkan,
            'tanggal_acuan' => $acuan,
        ];
    }

    /**
     * Menyimpan pembagian bobot ANTAR PENILAI dalam satu atau beberapa kategori.
     *
     * KENAPA ADA
     *
     * Sampai sekarang bobot antar penilai SELALU dibagi rata dan dihitung ulang setiap ada
     * perubahan. Rata adalah bawaan yang masuk akal, tetapi bukan satu-satunya yang benar:
     * atasan langsung dan atasan dua tingkat di atas tidak selalu layak menimbang sama.
     * Sebelum ini tidak ada satu pun jalan untuk mengubahnya.
     *
     * YANG DITUNTUT, DAN KENAPA
     *
     * Seluruh penilai aktif dalam kategori yang disentuh WAJIB disebut. Menerima sebagian
     * berarti menerima jumlah yang tidak bisa diperiksa: 60 untuk satu orang tidak berarti
     * apa-apa tanpa tahu berapa yang lain. Jumlah per kategori WAJIB 100 — itu ditegakkan
     * `PaBobotGuard`, penjaga yang sama yang dipakai bobot pilar dan kategori.
     *
     * YANG TIDAK DIJANJIKAN
     *
     * Bobot khusus TIDAK bertahan ketika susunan penilainya berubah. Menambah atau mencabut
     * penilai memanggil `hitungUlangBobot()` dan kategorinya kembali rata. Itu disengaja:
     * menyimpan "ini bobot khusus" butuh kolom baru, dan kolom yang tidak ada lebih jujur
     * daripada janji yang diam-diam tidak ditepati. Layar WAJIB mengatakannya.
     *
     * @param  array<int, float|int|string>  $bobotPerAssignment  id penugasan => bobot
     * @return array{diperbarui:int, kategori:list<int>}
     */
    /*
     |--------------------------------------------------------------------------
     | Memo dalam satu proses
     |--------------------------------------------------------------------------
     |
     | DB dev berada di seberang internet: `SELECT 1` saja 55 ms, dan `COUNT(*)` seluruh tabel
     | juga 58 ms. Artinya biayanya BUKAN pekerjaan SQL-nya melainkan perjalanan bolak-baliknya,
     | dan satu-satunya cara mempercepat adalah mengurangi jumlah perjalanan.
     |
     | Diukur sebelum perubahan ini: satu peserta = 29 kueri, 2.133 ms, rata-rata 74 ms per
     | kueri. Untuk 859 peserta itu 25.000 perjalanan dan sekitar setengah jam — sementara
     | sebagian besar jawabannya SAMA untuk setiap peserta di siklus yang sama.
     |
     | Memo ini hidup satu proses saja. Nilai yang dimemokan hanya yang tidak berubah di tengah
     | pembentukan: tenggat siklus dan waktu sekarang. Peserta dan aturannya TIDAK dimemokan
     | lintas peserta karena memang berbeda per orang.
     */
    /** @var array<int, string|null> tenggat per siklus */
    private array $memoTenggat = [];

    /** @var array<int, object> peserta per id */
    private array $memoPeserta = [];

    /** @var array<int, string|null> tanggal acuan organisasi per siklus */
    private array $memoAcuan = [];

    /** @var array<int, array<int, array<string, mixed>>> penugasan aktif per peserta */
    private array $memoPenugasanAktif = [];

    /** @var array<string, list<array<string, mixed>>> calon relasi per "peserta|tipe" */
    private array $memoCalon = [];

    /**
     * Sidik relasi yang sah untuk kiriman yang sedang diproses; null berarti belum dipramuat
     * dan pemeriksaannya jatuh ke kueri satuan.
     *
     * @var array<string, true>|null
     */
    private ?array $sidikRelasi = null;

    /** Waktu sekarang, diambil sekali per proses. */
    private ?string $memoWaktu = null;

    /**
     * Waktu sekarang.
     *
     * `FormatTanggalHelper::getCurrentTime()` MENEMBAK DATABASE setiap kali dipanggil — 74 ms
     * hanya untuk mengetahui jam. Pada pembentukan massal ia terpanggil beberapa kali per
     * peserta lewat `hitungUlangBobot()`, jadi ribuan perjalanan hanya untuk stempel waktu.
     *
     * Helper-nya sendiri TIDAK diubah: ia dipakai 36 berkas lintas modul, dan mengubah
     * perilakunya berarti menyentuh LMS, SOP, dan ESS demi masalah yang ada di sini.
     */
    private function sekarang(): string
    {
        return $this->memoWaktu ??= FormatTanggalHelper::getCurrentTime();
    }

    /** Melupakan memo. Dipakai proses panjang yang ingin stempel waktunya bergerak. */
    public function lupakanMemo(): void
    {
        $this->memoTenggat = [];
        $this->memoPeserta = [];
        $this->memoAcuan = [];
        $this->memoPenugasanAktif = [];
        $this->memoCalon = [];
        $this->sidikRelasi = null;
        $this->memoWaktu = null;
    }

    /** Toleransi pembulatan, sama dengan yang dipakai `PaBobotGuard`. */
    private const TOLERANSI_BOBOT = 0.011;

    public function simpanBobotPenilai(int $participantId, array $bobotPerAssignment, ?int $olehUserId = null): array
    {
        $this->gate->pastikanBolehUntukPeserta(PaCycleGate::BUAT_PENUGASAN, $participantId);

        return DB::transaction(fn () => $this->terapkanBobot($participantId, $bobotPerAssignment, $olehUserId));
    }

    /**
     * Menerapkan bobot yang sudah berkunci id penugasan.
     *
     * TIDAK membuka transaksinya sendiri dan TIDAK memeriksa gerbang akses: keduanya sudah
     * dikerjakan pemanggilnya. Dipisahkan supaya `tugaskan()` bisa memakai aturan yang PERSIS
     * SAMA di dalam transaksinya — kalau disalin, salah satu salinan akan menyimpang, dan yang
     * menyimpang di sini berarti bobot yang lolos tanpa berjumlah 100.
     *
     * @param  array<int, float|int|string>  $bobotPerAssignment
     */
    private function terapkanBobot(int $participantId, array $bobotPerAssignment, ?int $olehUserId): array
    {
        if ($bobotPerAssignment === []) {
            return ['diperbarui' => 0, 'kategori' => []];
        }

        $baris = DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->get(['Id_PA_Assignment', 'PA_Rater_Category_Id', 'Nama_Penilai_Saat_Cycle']);

        $milikPeserta = $baris->keyBy('Id_PA_Assignment');

        foreach (array_keys($bobotPerAssignment) as $id) {
            if (! $milikPeserta->has((int) $id)) {
                /* Ditolak, bukan dilewati: id yang bukan miliknya berarti layar mengirim
                   sesuatu yang tidak ia lihat, dan melewatinya menyembunyikan sebabnya. */
                throw new RuntimeException(
                    "Penugasan {$id} bukan milik peserta {$participantId} atau sudah tidak aktif."
                );
            }
        }

        $perKategori = $baris->groupBy('PA_Rater_Category_Id');
        $tersentuh = [];

        foreach ($perKategori as $kategoriId => $anggota) {
            $disebut = $anggota->filter(fn ($a) => array_key_exists((int) $a->Id_PA_Assignment, $bobotPerAssignment));

            if ($disebut->isEmpty()) {
                continue;
            }

            if ($disebut->count() !== $anggota->count()) {
                $hilang = $anggota->reject(fn ($a) => array_key_exists((int) $a->Id_PA_Assignment, $bobotPerAssignment))
                    ->pluck('Nama_Penilai_Saat_Cycle')
                    ->implode(', ');

                throw new RuntimeException(
                    'Seluruh penilai dalam satu kategori harus disebut bobotnya sekaligus. '
                    ."Belum disebut: {$hilang}."
                );
            }

            $nilai = [];

            foreach ($anggota as $a) {
                $nilai[$a->Nama_Penilai_Saat_Cycle ?: (string) $a->Id_PA_Assignment]
                    = $bobotPerAssignment[(int) $a->Id_PA_Assignment];
            }

            $this->bobot->pastikanSeratus($nilai, "penilai kategori {$kategoriId}");
            $tersentuh[] = (int) $kategoriId;
        }

        $sekarang = $this->sekarang();
        $diperbarui = 0;

        foreach ($bobotPerAssignment as $id => $nilai) {
            DB::table('N_PA_Assignment')
                ->where('Id_PA_Assignment', (int) $id)
                ->update([
                    'Bobot_Dalam_Kategori' => round((float) $nilai, 2),

                    /* DITANDAI sebagai keputusan. Tanpa ini angka yang baru saja
                       ditetapkan akan tersapu pembagian rata pada perubahan susunan
                       penilai berikutnya, dan tidak ada satu pun jejak bahwa pernah ada
                       yang memutuskannya. */
                    'Bobot_Kustom' => 'Y',
                    'Bobot_Diatur_Oleh' => $olehUserId,
                    'Bobot_Diatur_Pada' => $sekarang,
                    'Updated_At' => $sekarang,
                    'Updated_By' => $olehUserId,
                ]);

            $diperbarui++;
        }

        return ['diperbarui' => $diperbarui, 'kategori' => $tersentuh];
    }

    public function hitungUlangBobot(int $participantId, int $kategoriId, ?int $olehUserId = null): array
    {
        $baris = DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->where('PA_Rater_Category_Id', $kategoriId)
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Assignment')
            ->get(['Id_PA_Assignment', 'Bobot_Dalam_Kategori', 'Bobot_Kustom']);

        if ($baris->isEmpty()) {
            return ['digugurkan' => false];
        }

        $kustom = $baris->filter(fn ($r) => $r->Bobot_Kustom === 'Y')->values();
        $biasa = $baris->filter(fn ($r) => $r->Bobot_Kustom !== 'Y')->values();
        $sekarang = $this->sekarang();

        /* Tidak ada bobot khusus: perilaku lama, rata untuk semua. */
        if ($kustom->isEmpty()) {
            $this->tulisBobot($baris->pluck('Id_PA_Assignment')->all(), $this->bobot->bagiRata($baris->count()), $sekarang, $olehUserId);

            return ['digugurkan' => false];
        }

        $terpakai = round($kustom->sum(fn ($r) => (float) $r->Bobot_Dalam_Kategori), 2);
        $sisa = round(100 - $terpakai, 2);

        /*
         | KAPAN BOBOT KHUSUS MASIH BISA DIPERTAHANKAN
         |
         | Susunan penilai baru saja berubah, dan niat admin hanya diketahui untuk orang-orang
         | yang dulu ia atur. Bobot khusus dipertahankan hanya bila sisanya masih bisa dibagi
         | secara berarti:
         |
         |   - tidak ada penilai biasa, dan yang khusus memang mengisi tepat 100; atau
         |   - ada penilai biasa, dan sisanya benar-benar lebih dari nol.
         |
         | Sisa NOL dengan penilai biasa yang menunggu bukan keadaan yang sah, walau jumlahnya
         | tepat 100: penilai berbobot 0% adalah penilai yang jawabannya tidak ikut dihitung
         | sama sekali. Membiarkannya berarti seseorang mengisi penilaian yang tidak pernah
         | berpengaruh, tanpa ada yang memberitahunya.
         |
         | Sisa NEGATIF berarti yang khusus sudah melebihi 100 — mustahil dibagi.
         |
         | Di luar itu kategorinya dikembalikan rata DAN penandanya dilepas, lalu keadaan itu
         | DILAPORKAN ke pemanggil. Diam-diam mengembalikannya adalah persis cacat yang hendak
         | ditutup kolom Bobot_Kustom.
         */
        $dipertahankan = $sisa >= 0
            && ($biasa->isEmpty()
                ? abs($sisa) <= self::TOLERANSI_BOBOT
                : $sisa > self::TOLERANSI_BOBOT);

        if (! $dipertahankan) {
            $this->lepasPenandaKustom($participantId, $kategoriId);

            $this->tulisBobot($baris->pluck('Id_PA_Assignment')->all(), $this->bobot->bagiRata($baris->count()), $sekarang, $olehUserId);

            return [
                'digugurkan' => true,
                'kategori_id' => $kategoriId,
                'sebab' => $sisa < 0
                    ? 'bobot khusus melebihi 100%'
                    : 'penilai baru tidak kebagian bobot',
            ];
        }

        /* Yang khusus tidak disentuh; sisanya dibagi rata ke yang biasa. */
        if ($biasa->isNotEmpty()) {
            $bagian = $this->bagiSisa($sisa, $biasa->count());
            $this->tulisBobot($biasa->pluck('Id_PA_Assignment')->all(), $bagian, $sekarang, $olehUserId);
        }

        return ['digugurkan' => false];
    }

    /**
     * Menerjemahkan bobot ber-IDENTITAS menjadi peta berkunci id penugasan.
     *
     | Inilah yang menghapus penyegaran di tengah: penilai yang barisnya baru lahir dalam
     | transaksi ini pun ketemu, karena yang dicocokkan kode perusahaan dan kode karyawannya,
     | bukan id yang belum diketahui layar.
     *
     * @param  list<array<string, mixed>>  $bobot
     * @param  array<string, array<string, mixed>>  $aturan
     * @return array<int, float>
     */
    private function petakanBobotIdentitas(int $participantId, array $bobot, array $aturan): array
    {
        $baris = DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->get(['Id_PA_Assignment', 'PA_Rater_Category_Id', 'Kode_Perusahaan_Penilai', 'Kode_Karyawan_Penilai']);

        $indeks = [];

        foreach ($baris as $r) {
            $indeks[$r->PA_Rater_Category_Id.'|'.$r->Kode_Perusahaan_Penilai.'|'.$r->Kode_Karyawan_Penilai]
                = (int) $r->Id_PA_Assignment;
        }

        $peta = [];

        foreach ($bobot as $b) {
            $kode = strtoupper(trim((string) ($b['kode_kategori'] ?? '')));
            $kategoriId = $aturan[$kode]['kategori_id'] ?? null;

            if ($kategoriId === null) {
                throw new RuntimeException("Kategori {$kode} tidak berlaku bagi peserta {$participantId}.");
            }

            $kunci = $kategoriId.'|'.($b['kode_perusahaan'] ?? '').'|'.($b['kode_karyawan'] ?? '');

            if (! isset($indeks[$kunci])) {
                /* Ditolak, bukan dilewati. Bobot untuk orang yang bukan penilai peserta ini
                   berarti layar mengirim sesuatu yang tidak ia lihat, dan melewatinya membuat
                   jumlah kategorinya tidak lagi 100 tanpa satu pun keterangan. */
                throw new RuntimeException(
                    ($b['kode_karyawan'] ?? '(kosong)')." bukan penilai {$kode} bagi peserta {$participantId}."
                );
            }

            $peta[$indeks[$kunci]] = (float) ($b['bobot'] ?? 0);
        }

        return $peta;
    }

    /** Melepas penanda bobot khusus seluruh penilai aktif satu kategori. */
    private function lepasPenandaKustom(int $participantId, int $kategoriId): void
    {
        DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->where('PA_Rater_Category_Id', $kategoriId)
            ->where('Flag_Aktif', 'Y')
            ->update([
                'Bobot_Kustom' => 'T',
                'Bobot_Diatur_Oleh' => null,
                'Bobot_Diatur_Pada' => null,
            ]);
    }

    /** Membagi sebuah total ke sejumlah bagian; sisa pembulatan jatuh ke yang terakhir. */
    private function bagiSisa(float $total, int $jumlah): array
    {
        $dasar = floor(($total / $jumlah) * 100) / 100;
        $bagian = [];
        $tersisa = $total;

        for ($i = 0; $i < $jumlah; $i++) {
            $nilai = $i === $jumlah - 1 ? round($tersisa, 2) : $dasar;
            $bagian[] = $nilai;
            $tersisa = round($tersisa - $nilai, 2);
        }

        return $bagian;
    }

    /**
     * Menulis bobot BANYAK baris dalam satu pernyataan.
     *
     * Versi sebelumnya satu UPDATE per baris. Pada kategori berpenilai tiga itu tiga perjalanan
     * 74 ms; dikalikan kategori dan dikalikan 859 peserta, ia salah satu penyumbang terbesar
     * setengah jam pembentukan massal.
     *
     * Baris yang bobotnya SUDAH sama dilewati: menulis nilai yang tidak berubah tetap membayar
     * perjalanan penuh, dan pada pembentukan massal sebagian besar kategori memang sudah rata.
     *
     * @param  list<int>  $ids
     */
    private function tulisBobot(array $ids, array $bagian, string $sekarang, ?int $olehUserId): void
    {
        if ($ids === []) {
            return;
        }

        /* Dikelompokkan menurut NILAI: penilai yang berbagi bobot sama cukup satu UPDATE.
           Pembagian rata membuat hampir semuanya sama, jadi tiga penilai biasanya jadi dua
           pernyataan (satu untuk 33.33, satu untuk sisa pembulatan). */
        $perNilai = [];

        foreach ($ids as $i => $id) {
            $perNilai[(string) $bagian[$i]][] = $id;
        }

        foreach ($perNilai as $nilai => $kelompok) {
            DB::table('N_PA_Assignment')
                ->whereIn('Id_PA_Assignment', $kelompok)
                ->update([
                    'Bobot_Dalam_Kategori' => (float) $nilai,
                    'Updated_At' => $sekarang,
                    'Updated_By' => $olehUserId,
                ]);
        }
    }

    /**
     * Mengembalikan satu kategori ke pembagian rata DAN melepas penanda bobot khususnya.
     *
     * Dibutuhkan karena "bagi rata" bukan sekadar mengisi angka rata: selama penandanya masih
     * menyala, angka itu tetap diperlakukan sebagai keputusan dan tidak akan ikut menyesuaikan
     * ketika penilainya berubah. Tanpa jalan ini, sekali sebuah kategori diatur khusus ia tidak
     * pernah benar-benar bisa dikembalikan ke perilaku bawaan.
     */
    public function kembalikanBobotRata(int $participantId, int $kategoriId, ?int $olehUserId = null): array
    {
        $this->gate->pastikanBolehUntukPeserta(PaCycleGate::BUAT_PENUGASAN, $participantId);

        return DB::transaction(function () use ($participantId, $kategoriId, $olehUserId) {
            $this->lepasPenandaKustom($participantId, $kategoriId);

            return $this->hitungUlangBobot($participantId, $kategoriId, $olehUserId);
        });
    }

    /**
     * Apakah peserta ini sudah cukup penilainya untuk dinilai.
     *
     * `Min_Rater` diperiksa terhadap penugasan yang ADA, `Min_Rater_Selesai` terhadap
     * yang SUDAH MENGISI. Keduanya berbeda dan keduanya perlu: yang pertama menjawab
     * "apakah siklusnya siap dibuka", yang kedua "apakah hasilnya boleh dihitung".
     *
     * @return array{siap_dibuka:bool, siap_dihitung:bool, kategori:array<string, array{
     *     min_rater:int, maks_rater:int|null, min_rater_selesai:int|null,
     *     terpasang:int, selesai:int, cukup_terpasang:bool, cukup_selesai:bool
     * }>}
     */
    public function kesiapan(int $participantId): array
    {
        $aturan = $this->resolver->raterRules($participantId);

        $cacah = DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->groupBy('PA_Rater_Category_Id')
            ->select([
                'PA_Rater_Category_Id',
                DB::raw('COUNT(*) AS terpasang'),
                DB::raw("SUM(CASE WHEN Status_Penugasan = 'SELESAI' THEN 1 ELSE 0 END) AS selesai"),
            ])
            ->get()
            ->keyBy('PA_Rater_Category_Id');

        $kategori = [];
        $siapDibuka = true;
        $siapDihitung = true;

        foreach ($aturan as $kode => $a) {
            $baris = $cacah->get($a['kategori_id']);
            $terpasang = $baris === null ? 0 : (int) $baris->terpasang;
            $selesai = $baris === null ? 0 : (int) $baris->selesai;

            // Tanpa Min_Rater_Selesai sendiri, ambang penghitungan mengikuti ambang
            // penugasan. Menganggapnya nol akan membuat kategori tanpa satu pun
            // pengisi tetap lolos.
            $minSelesai = $a['min_rater_selesai'] ?? $a['min_rater'];

            $cukupTerpasang = $terpasang >= $a['min_rater'];
            $cukupSelesai = $selesai >= $minSelesai;

            $siapDibuka = $siapDibuka && $cukupTerpasang;
            $siapDihitung = $siapDihitung && $cukupSelesai;

            $kategori[$kode] = [
                'min_rater' => $a['min_rater'],
                'maks_rater' => $a['maks_rater'],
                'min_rater_selesai' => $minSelesai,
                'terpasang' => $terpasang,
                'selesai' => $selesai,
                'cukup_terpasang' => $cukupTerpasang,
                'cukup_selesai' => $cukupSelesai,
            ];
        }

        return [
            'siap_dibuka' => $siapDibuka,
            'siap_dihitung' => $siapDihitung,
            'kategori' => $kategori,
        ];
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    private function peserta(int $participantId): object
    {
        /* Dimemokan: `usulkan()` dan `tugaskan()` membacanya untuk peserta yang sama, berurutan. */
        if (isset($this->memoPeserta[$participantId])) {
            return $this->memoPeserta[$participantId];
        }

        $baris = DB::table('N_PA_Participant')
            ->where('Id_PA_Participant', $participantId)
            ->first([
                'Id_PA_Participant', 'PA_Cycle_Id', 'Kode_Perusahaan', 'Kode_Karyawan',
                'Nama_Karyawan_Saat_Cycle', 'Status_Peserta',
            ]);

        if ($baris === null) {
            throw new RuntimeException("Peserta {$participantId} tidak ada.");
        }

        return $this->memoPeserta[$participantId] = $baris;
    }

    /**
     | MENURUNKAN TENGGAT KE PENUGASAN YANG LAHIR SEBELUM JENDELANYA ADA.
     |
     | Penugasan mewarisi tenggat dari `Pengisian_Sampai` PADA SAAT DIBENTUK. Siklus yang
     | penugasannya dibentuk lebih dulu — urutan yang wajar, karena membentuk penugasan adalah
     | langkah penyiapan sementara jadwal sering baru dipastikan belakangan — melahirkan ribuan
     | penugasan ber-`Due_At` kosong, dan pengisian tanpa tenggat berarti tidak seorang pun bisa
     | tercatat terlambat.
     |
     | Hanya yang KOSONG yang diisi. Tenggat yang sudah ada boleh jadi ditetapkan per penugasan
     | dengan sengaja (`due_at` bisa ditimpa saat penugasan dibuat), dan menimpanya berarti
     | membuang keputusan seseorang untuk menyeragamkan angka.
     |
     | @return int banyaknya penugasan yang baru mendapat tenggat
     */
    public function turunkanTenggat(int $cycleId, bool $timpaYangSudahAda = false): int
    {
        $tenggat = $this->tenggatSiklus($cycleId);

        if ($tenggat === null) {
            return 0;
        }

        $peserta = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->select('Id_PA_Participant');

        /*
         | `$timpaYangSudahAda` dipakai saat JENDELA SIKLUS DIGESER di tengah jalan.
         |
         | Bawaannya false — pembukaan pertama hanya mengisi yang masih kosong, dan itu
         | benar: menimpa di sana tidak ada gunanya karena belum ada yang terisi.
         |
         | Saat jendelanya diperpanjang, yang kosong saja tidak cukup: seluruh penugasan
         | sudah memegang tenggat lama, dan membiarkannya berarti admin melihat tanggal
         | baru di layar siklus sementara para penilai masih ditagih menurut tanggal lama.
         | Dua tanggal untuk satu jendela, dan tidak ada yang mengatakan mana yang berlaku.
         |
         | YANG SUDAH SELESAI TIDAK IKUT DITIMPA. Tenggatnya sudah menjadi bagian dari
         | catatan apakah orang itu terlambat; menggesernya sekarang menulis ulang sejarah
         | yang sudah terjadi. Yang dibatalkan juga dilewati — ia tidak menunggu siapa pun.
         */
        return (int) DB::table('N_PA_Assignment')
            ->whereIn('PA_Participant_Id', $peserta)
            ->where('Flag_Aktif', 'Y')
            ->when(
                $timpaYangSudahAda,
                fn ($q) => $q->whereNotIn('Status_Penugasan', [self::STATUS_SELESAI_TUGAS, self::STATUS_DIBATALKAN]),
                fn ($q) => $q->whereNull('Due_At'),
            )
            ->update([
                'Due_At' => $tenggat,
                'Updated_At' => $this->sekarang(),
            ]);
    }

    /**
     * Membuang memo tenggat satu siklus. WAJIB dipanggil sesudah jendela pengisiannya
     * berubah — tanpa itu proses yang sama akan terus memakai tanggal yang sudah usang.
     */
    public function lupakanTenggat(int $cycleId): void
    {
        unset($this->memoTenggat[$cycleId]);
    }

    /**
     * Tenggat baku penugasan: akhir jendela pengisian siklus.
     *
     * Boleh kosong — siklus DRAFT memang belum tentu punya jadwal, dan menebak tanggal
     * lebih buruk daripada mengakui belum ada. Penugasan tanpa tenggat tidak akan pernah
     * dihitung terlambat, dan itu jawaban yang benar untuknya.
     */
    private function tenggatSiklus(int $cycleId): ?string
    {
        if (array_key_exists($cycleId, $this->memoTenggat)) {
            return $this->memoTenggat[$cycleId];
        }

        $tgl = DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->value('Pengisian_Sampai');

        // Kolomnya bertipe DATE, jadi jam apa pun yang ditulis akan dipotong. Menuliskan
        // "23:59:59" hanya menyesatkan pembaca kode berikutnya menjadi mengira waktunya
        // tersimpan; artinya "sampai akhir hari" ditegakkan saat DIBACA, bukan saat ditulis.
        return $this->memoTenggat[$cycleId] = ($tgl === null ? null : substr((string) $tgl, 0, 10));
    }

    private function tanggalAcuan(int $cycleId): string
    {
        /* Dimemokan per siklus: nilainya sama untuk SETIAP peserta, tetapi dibaca ulang sekali
           per peserta — 859 perjalanan 55 ms untuk satu tanggal yang tidak berubah. */
        if (isset($this->memoAcuan[$cycleId])) {
            return $this->memoAcuan[$cycleId];
        }

        $siklus = DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->first(['Tanggal_Acuan_Organisasi', 'Periode_Dinilai_Sampai']);

        $tgl = $siklus?->Tanggal_Acuan_Organisasi ?? $siklus?->Periode_Dinilai_Sampai;

        // Siklus yang belum bertanggal dibaca menurut keadaan organisasi hari ini.
        // Itu jawaban yang bisa dipertanggungjawabkan; menebak tanggal lain tidak.
        return $this->memoAcuan[$cycleId] = $tgl === null
            ? FormatTanggalHelper::getCurrentTime()->format('Y-m-d')
            : substr((string) $tgl, 0, 10);
    }

    /**
     * @return list<array{kode_perusahaan:string, kode_karyawan:string, nama:string,
     *                   organization_relationship_id:int|null, kode_relasi:string|null,
     *                   sudah_ditugaskan:bool}>
     */
    /**
     | RELASI YANG ADA TETAPI TIDAK TERBACA SIKLUS INI KARENA TANGGALNYA.
     |
     | `calonDariRelasi()` membaca organisasi PADA TANGGAL ACUAN siklus. Relasi yang mulai
     | berlaku sesudah tanggal itu — atau sudah berakhir sebelumnya — dibuang tanpa jejak, dan
     | yang tersisa di layar hanyalah kategori kosong bertuliskan "perlu penilai manual".
     |
     | Itu keterangan yang salah, dan salahnya ke arah yang paling mahal: admin membaca "sistem
     | tidak menemukan siapa pun", lalu mulai menambahkan penilai satu per satu untuk orang yang
     | sebenarnya SUDAH tercatat sebagai bawahannya. Sebabnya nyata dan bisa ditindaklanjuti —
     | relasinya dicatat pada 3 September sementara siklusnya membaca organisasi per 30 Juni —
     | tetapi tidak pernah sampai ke orang yang bisa memperbaikinya.
     |
     | SATU kueri untuk seluruh kategori peserta ini. Menanyakannya per kategori berarti empat
     | perjalanan ke database untuk membuka satu modal, dan seluruh biaya layar ini memang
     | terletak pada jumlah perjalanan, bukan pada kerja SQL-nya.
     |
     | @param  array<string, array<string, mixed>>  $aturan
     | @return array<int, array{belum_berlaku:int, mulai_paling_awal:string|null, sudah_berakhir:int}>
     */
    private function relasiDiLuarAcuan(object $peserta, array $aturan, string $acuan): array
    {
        $tipe = [];

        foreach ($aturan as $a) {
            if (($a['relationship_type_id'] ?? null) !== null) {
                $tipe[] = (int) $a['relationship_type_id'];
            }
        }

        if ($tipe === []) {
            return [];
        }

        $baris = DB::table('N_HRIS_Organization_Relationship as r')
            ->join('Karyawan as k', function ($j) {
                $j->on('k.Kode_Perusahaan', '=', 'r.Kode_Perusahaan_Terkait')
                    ->on('k.Kode_Karyawan', '=', 'r.Kode_Karyawan_Terkait');
            })
            ->where('r.Kode_Perusahaan_Subjek', $peserta->Kode_Perusahaan)
            ->where('r.Kode_Karyawan_Subjek', $peserta->Kode_Karyawan)
            ->whereIn('r.Id_Organization_Relationship_Type', array_values(array_unique($tipe)))
            ->where('r.Flag_Aktif', 'Y')
            ->whereNull('r.Cancelled_At')

            /* Karyawan nonaktif TIDAK dihitung. Mereka juga dibuang oleh `calonDariRelasi()`,
               dan dengan sebab yang sama sekali berbeda — menyebutnya di sini akan menyuruh
               admin mengurus tanggal padahal yang salah orangnya. */
            ->where('k.Aktif', 'Y')

            /* Yang di LUAR jendela acuan: belum mulai, atau sudah berakhir. */
            ->where(function ($w) use ($acuan) {
                $w->where('r.Berlaku_Dari', '>', $acuan)
                    ->orWhere('r.Berlaku_Sampai', '<', $acuan);
            })
            ->groupBy('r.Id_Organization_Relationship_Type')
            ->selectRaw(
                'r.Id_Organization_Relationship_Type AS tipe,'
                .' SUM(CASE WHEN r.Berlaku_Dari > ? THEN 1 ELSE 0 END) AS belum,'
                .' MIN(CASE WHEN r.Berlaku_Dari > ? THEN r.Berlaku_Dari END) AS awal,'
                .' SUM(CASE WHEN r.Berlaku_Sampai < ? THEN 1 ELSE 0 END) AS usai',
                [$acuan, $acuan, $acuan]
            )
            ->get();

        $keluar = [];

        foreach ($baris as $b) {
            $keluar[(int) $b->tipe] = [
                'belum_berlaku' => (int) $b->belum,
                'mulai_paling_awal' => $b->awal === null ? null : substr((string) $b->awal, 0, 10),
                'sudah_berakhir' => (int) $b->usai,
            ];
        }

        return $keluar;
    }

    private function calonDiriSendiri(object $peserta): array
    {
        return [[
            'kode_perusahaan' => (string) $peserta->Kode_Perusahaan,
            'kode_karyawan' => (string) $peserta->Kode_Karyawan,
            'nama' => (string) $peserta->Nama_Karyawan_Saat_Cycle,
            // Penilaian diri tidak berasal dari relasi organisasi mana pun.
            'organization_relationship_id' => null,
            'kode_relasi' => null,
            'sudah_ditugaskan' => false,
        ]];
    }

    /**
     * @return list<array{kode_perusahaan:string, kode_karyawan:string, nama:string,
     *                   organization_relationship_id:int|null, kode_relasi:string|null,
     *                   sudah_ditugaskan:bool}>
     */
    private function calonDariRelasi(object $peserta, ?int $tipeRelasiId, string $acuan): array
    {
        if ($tipeRelasiId === null) {
            return [];
        }

        $kunciMemo = $peserta->Id_PA_Participant.'|'.$tipeRelasiId;

        if (array_key_exists($kunciMemo, $this->memoCalon)) {
            return $this->memoCalon[$kunciMemo];
        }

        return $this->memoCalon[$kunciMemo] = DB::table('N_HRIS_Organization_Relationship as r')
            ->join('Karyawan as k', function ($j) {
                $j->on('k.Kode_Perusahaan', '=', 'r.Kode_Perusahaan_Terkait')
                    ->on('k.Kode_Karyawan', '=', 'r.Kode_Karyawan_Terkait');
            })
            ->join('N_HRIS_Organization_Relationship_Type as t',
                't.Id_Organization_Relationship_Type', '=', 'r.Id_Organization_Relationship_Type')
            ->where('r.Kode_Perusahaan_Subjek', $peserta->Kode_Perusahaan)
            ->where('r.Kode_Karyawan_Subjek', $peserta->Kode_Karyawan)
            ->where('r.Id_Organization_Relationship_Type', $tipeRelasiId)
            ->where('r.Flag_Aktif', 'Y')
            ->whereNull('r.Cancelled_At')
            /* `where`, BUKAN `whereDate`. Keduanya menghasilkan jawaban yang sama, tetapi
               `whereDate` menghasilkan `cast([Berlaku_Dari] as date) <= ?` — membungkus
               KOLOMNYA. Padahal kolomnya sudah bertipe `date`, jadi cast itu tidak
               mengubah apa pun kecuali satu hal: predikat yang membungkus kolom tidak bisa
               memakai indeks, sehingga kuerinya memindai seluruh tabel.

               Belum terasa hari ini karena `N_HRIS_Organization_Relationship` masih nol
               baris. Justru itu sebabnya diperbaiki sekarang: sesudah tabelnya diisi,
               setiap usulan penilai akan memindainya, dan pemindaian yang lahir bersama
               datanya jauh lebih sulit dikenali sebagai regresi. */
            ->where('r.Berlaku_Dari', '<=', $acuan)
            ->where(function ($q) use ($acuan) {
                $q->whereNull('r.Berlaku_Sampai')->orWhere('r.Berlaku_Sampai', '>=', $acuan);
            })
            // Penilai yang sudah tidak aktif tidak akan pernah mengisi. Mengusulkannya
            // hanya memindahkan kekosongan ke tahap berikutnya.
            ->where('k.Aktif', 'Y')
            ->orderBy('r.Prioritas')
            ->orderBy('k.Kode_Karyawan')
            ->get([
                'r.Id_Organization_Relationship', 'r.Kode_Perusahaan_Terkait',
                'r.Kode_Karyawan_Terkait', 'k.Nama', 't.Kode_Relasi',
            ])
            ->map(fn ($r) => [
                'kode_perusahaan' => (string) $r->Kode_Perusahaan_Terkait,
                'kode_karyawan' => (string) $r->Kode_Karyawan_Terkait,
                'nama' => (string) $r->Nama,
                'organization_relationship_id' => (int) $r->Id_Organization_Relationship,
                'kode_relasi' => $r->Kode_Relasi,
                'sudah_ditugaskan' => false,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, true>> dikunci kategori lalu "perusahaan|karyawan"
     */
    /**
     * Awalan alasan pembatalan yang dibuat SISTEM, bukan keputusan admin atas satu penilai.
     *
     * Pembatalan karena pesertanya dikecualikan, Level PA-nya berubah, atau direvisi sesudah
     * dikunci membatalkan SEMUA penilainya sekaligus — bukan menolak orang tertentu. Bila itu
     * dihitung "pernah dicabut", pembentukan ulang melewati seluruh penilai alaminya (atasan,
     * rekan, bawahan) dan pesertanya berakhir tanpa penilai (13 Okt 2026).
     */
    public const PREFIKS_BATAL_SISTEM = [
        'Peserta dikecualikan dari siklus:',
        'Level PA berubah ke',
        'Revisi peserta:',
    ];

    /**
     * Penilai yang penugasannya pernah DICABUT ADMIN untuk peserta ini, per kategori — tidak
     * diusulkan lagi. Pembatalan oleh sistem (`PREFIKS_BATAL_SISTEM`) tidak dihitung.
     *
     * @return array<string, true> kunci "kategori|perusahaan|karyawan"
     */
    private function penilaiDicabut(int $participantId): array
    {
        $hasil = [];

        $rows = DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'T')
            ->where('Status_Penugasan', self::STATUS_DIBATALKAN)
            ->get(['PA_Rater_Category_Id', 'Kode_Perusahaan_Penilai', 'Kode_Karyawan_Penilai', 'Cancel_Reason'])
            ->filter(function ($r) {
                foreach (self::PREFIKS_BATAL_SISTEM as $awalan) {
                    if (str_starts_with((string) $r->Cancel_Reason, $awalan)) {
                        return false;
                    }
                }

                return true;
            });

        foreach ($rows as $r) {
            $hasil[(int) $r->PA_Rater_Category_Id.'|'.$r->Kode_Perusahaan_Penilai.'|'.$r->Kode_Karyawan_Penilai] = true;
        }

        return $hasil;
    }

    private function penugasanAktif(int $participantId): array
    {
        /* Dimemokan: `usulkan()` dan `tugaskan()` membacanya berurutan untuk peserta yang sama.
           Memo DIBUANG begitu penugasannya berubah — lihat `lupakanPenugasan()`. */
        if (isset($this->memoPenugasanAktif[$participantId])) {
            return $this->memoPenugasanAktif[$participantId];
        }

        $hasil = [];

        /* Membawa id dan bobotnya, bukan sekadar penanda "ada".

           Layar butuh keduanya: id untuk mencabut satu penilai, bobot untuk menampilkan dan
           menyunting pembagian persen di dalam kategori. Memulangkan `true` memaksa layar
           menebak keduanya — dan menebak id penugasan berarti mencabut baris yang salah. */
        $rows = DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->get([
                'Id_PA_Assignment',
                'PA_Rater_Category_Id',
                'Kode_Perusahaan_Penilai',
                'Kode_Karyawan_Penilai',
                'Bobot_Dalam_Kategori',
                'Status_Penugasan',
            ]);

        foreach ($rows as $r) {
            $hasil[(int) $r->PA_Rater_Category_Id][$r->Kode_Perusahaan_Penilai.'|'.$r->Kode_Karyawan_Penilai] = [
                'assignment_id' => (int) $r->Id_PA_Assignment,
                'bobot' => (float) $r->Bobot_Dalam_Kategori,
                'status' => $r->Status_Penugasan,
            ];
        }

        return $this->memoPenugasanAktif[$participantId] = $hasil;
    }

    /**
     * SELF wajib peserta itu sendiri, dan kategori lain wajib BUKAN dirinya.
     *
     * Keduanya kelihatan sepele sampai terjadi: penilaian diri yang diisi orang lain
     * dan atasan yang ternyata dirinya sendiri sama-sama menghasilkan angka yang
     * tampak wajar dan tidak bisa dibedakan dari yang benar setelah tersimpan.
     */
    private function pastikanDiriSendiriKonsisten(
        string $kodeKategori,
        object $peserta,
        string $kodePerusahaan,
        string $kodeKaryawan,
    ): void {
        $dirinya = $kodePerusahaan === (string) $peserta->Kode_Perusahaan
            && $kodeKaryawan === (string) $peserta->Kode_Karyawan;

        if ($kodeKategori === self::KATEGORI_SELF && ! $dirinya) {
            throw new RuntimeException(
                'Kategori SELF hanya boleh diisi peserta itu sendiri.'
            );
        }

        if ($kodeKategori !== self::KATEGORI_SELF && $dirinya) {
            throw new RuntimeException(
                "Peserta tidak boleh menjadi penilai dirinya sendiri di kategori {$kodeKategori}."
            );
        }
    }

    /**
     * @param  array<string, mixed>  $penilai
     * @param  array<string, mixed>  $aturan
     */
    private function pastikanSumberKonsisten(
        array $penilai,
        array $aturan,
        object $peserta,
        string $sumber,
        string $kodePerusahaan,
        string $kodeKaryawan,
    ): void {
        $alasan = trim((string) ($penilai['alasan_manual'] ?? ''));
        $relationshipId = $penilai['organization_relationship_id'] ?? null;

        if ($sumber === self::SUMBER_MANUAL) {
            if ($alasan === '') {
                throw new RuntimeException('Penugasan MANUAL wajib menyertakan alasan.');
            }

            if ($relationshipId !== null) {
                throw new RuntimeException(
                    'Penugasan MANUAL tidak boleh mengaku berasal dari relasi organisasi.'
                );
            }

            return;
        }

        if ($alasan !== '') {
            throw new RuntimeException('Alasan_Manual hanya boleh diisi untuk sumber MANUAL.');
        }

        if (($aturan['kode_kategori'] ?? null) === self::KATEGORI_SELF) {
            if ($relationshipId !== null) {
                throw new RuntimeException('Kategori SELF tidak memakai relasi organisasi.');
            }

            return;
        }

        if ($relationshipId === null) {
            throw new RuntimeException(
                'Penugasan USULAN selain SELF wajib menunjuk relasi organisasi asal.'
            );
        }

        /* Sidik relasi yang SAH untuk pasangan ini. Bentuknya sengaja sama persis dengan yang
           disusun `sidikRelasiSah()`, supaya pemeriksaan satuan dan pemeriksaan terpramuat
           menegakkan syarat yang identik. */
        $sidik = implode('|', [
            (int) $relationshipId,
            $peserta->Kode_Perusahaan,
            $peserta->Kode_Karyawan,
            $kodePerusahaan,
            $kodeKaryawan,
            (int) $aturan['relationship_type_id'],
        ]);

        if ($this->sidikRelasi !== null) {
            /* Sudah dipramuat untuk seluruh kiriman: nol perjalanan ke database di sini.
               Inilah kueri paling sering di jalur pembentukan — satu per calon penilai, dan
               pada 859 peserta itu ribuan perjalanan untuk pertanyaan yang bisa dijawab
               sekaligus. */
            $cocok = isset($this->sidikRelasi[$sidik]);
        } else {
            $cocok = DB::table('N_HRIS_Organization_Relationship')
                ->where('Id_Organization_Relationship', (int) $relationshipId)
                ->where('Kode_Perusahaan_Subjek', $peserta->Kode_Perusahaan)
                ->where('Kode_Karyawan_Subjek', $peserta->Kode_Karyawan)
                ->where('Kode_Perusahaan_Terkait', $kodePerusahaan)
                ->where('Kode_Karyawan_Terkait', $kodeKaryawan)
                ->where('Id_Organization_Relationship_Type', $aturan['relationship_type_id'])
                ->where('Flag_Aktif', 'Y')
                ->whereNull('Cancelled_At')
                ->exists();
        }

        if (! $cocok) {
            throw new RuntimeException(
                'Relasi organisasi asal tidak cocok dengan peserta, penilai, atau kategori.'
            );
        }
    }

    /**
     * Sidik seluruh relasi organisasi yang SAH bagi satu kiriman penugasan — satu kueri.
     *
     * Menggantikan satu `exists` per calon penilai. Terukur sebagai kueri paling sering di
     * jalur pembentukan: 21 kali untuk lima peserta, dan pada DB yang setiap perjalanannya
     * puluhan hingga ratusan milidetik itulah penyumbang terbesar yang tersisa.
     *
     * Syaratnya PERSIS sama dengan pemeriksaan satuannya — id, subjek, terkait, tipe, aktif,
     * belum dibatalkan. Kalau berbeda, jalur massal akan meloloskan relasi yang ditolak jalur
     * satuan, dan penugasan yang lahir dari situ tidak bisa ditelusuri ke relasi mana pun.
     *
     * @param  list<array<string, mixed>>  $penilai
     * @param  array<string, array<string, mixed>>  $aturan
     * @return array<string, true>
     */
    private function sidikRelasiSah(object $peserta, array $penilai, array $aturan): array
    {
        $id = [];

        foreach ($penilai as $p) {
            $r = $p['organization_relationship_id'] ?? null;

            if ($r !== null) {
                $id[] = (int) $r;
            }
        }

        return $this->sidikDariRelasi(array_values(array_unique($id)));
    }

    /**
     * Sidik relasi dari SEKUMPULAN id — tanpa menyaring pesertanya.
     *
     * Subjeknya diambil dari BARIS RELASINYA SENDIRI, bukan dari peserta yang sedang diproses.
     * Karena itu satu kueri bisa melayani ratusan peserta sekaligus: sidiknya memuat pasangan
     * subjek-terkait apa adanya, dan pencocokan di `pastikanSumberKonsisten()` tetap menuntut
     * subjek itu sama dengan peserta yang bersangkutan — syaratnya tidak berubah sedikit pun.
     *
     * Menyaring per peserta seperti versi sebelumnya memaksa satu kueri per orang: 30 kueri
     * untuk 30 peserta, penyumbang terbesar yang tersisa di jalur cepat.
     *
     * @param  list<int>  $id
     * @return array<string, true>
     */
    private function sidikDariRelasi(array $id): array
    {
        if ($id === []) {
            return [];
        }

        $sidik = [];

        foreach (array_chunk($id, 500) as $potongan) {
            foreach (DB::table('N_HRIS_Organization_Relationship')
                ->whereIn('Id_Organization_Relationship', $potongan)
                ->where('Flag_Aktif', 'Y')
                ->whereNull('Cancelled_At')
                ->get([
                    'Id_Organization_Relationship',
                    'Kode_Perusahaan_Subjek', 'Kode_Karyawan_Subjek',
                    'Kode_Perusahaan_Terkait', 'Kode_Karyawan_Terkait',
                    'Id_Organization_Relationship_Type',
                ]) as $r) {
                $sidik[implode('|', [
                    (int) $r->Id_Organization_Relationship,
                    $r->Kode_Perusahaan_Subjek,
                    $r->Kode_Karyawan_Subjek,
                    $r->Kode_Perusahaan_Terkait,
                    $r->Kode_Karyawan_Terkait,
                    (int) $r->Id_Organization_Relationship_Type,
                ])] = true;
            }
        }

        return $sidik;
    }

    /**
     * Nama + keaktifan BANYAK karyawan sekaligus.
     *
     * Menggantikan satu kueri per penilai. Pada peserta berpenilai enam itu enam perjalanan
     * 74 ms untuk pertanyaan yang bisa dijawab sekali.
     *
     * @param  list<array{0:string,1:string}>  $pasangan
     * @return array<string, object> berkunci "PT|KODE"
     */
    private function karyawanBanyak(array $pasangan): array
    {
        if ($pasangan === []) {
            return [];
        }

        $q = DB::table('Karyawan');

        foreach ($pasangan as [$pt, $kode]) {
            $q->orWhere(function ($w) use ($pt, $kode) {
                $w->where('Kode_Perusahaan', $pt)->where('Kode_Karyawan', $kode);
            });
        }

        $peta = [];

        foreach ($q->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'Aktif']) as $r) {
            $peta[$r->Kode_Perusahaan.'|'.$r->Kode_Karyawan] = $r;
        }

        return $peta;
    }

    private function namaKaryawanAktif(string $kodePerusahaan, string $kodeKaryawan, ?array $peta = null): string
    {
        if ($peta !== null) {
            $k = $peta[$kodePerusahaan.'|'.$kodeKaryawan] ?? null;

            if ($k === null) {
                throw new RuntimeException("Karyawan {$kodePerusahaan}/{$kodeKaryawan} tidak ada.");
            }

            if ($k->Aktif !== 'Y') {
                throw new RuntimeException(
                    "Karyawan {$kodePerusahaan}/{$kodeKaryawan} sudah tidak aktif; "
                    .'penilai nonaktif tidak bisa ditugaskan.'
                );
            }

            return (string) $k->Nama;
        }

        $k = DB::table('Karyawan')
            ->where('Kode_Perusahaan', $kodePerusahaan)
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->first(['Nama', 'Aktif']);

        if ($k === null) {
            throw new RuntimeException("Karyawan {$kodePerusahaan}/{$kodeKaryawan} tidak ada.");
        }

        if ($k->Aktif !== 'Y') {
            throw new RuntimeException(
                "Karyawan {$kodePerusahaan}/{$kodeKaryawan} sudah tidak aktif dan tidak bisa "
                .'ditugaskan menilai.'
            );
        }

        return (string) $k->Nama;
    }
}
