<?php

namespace App\Console\Commands\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingNilaiBerlaku;
use App\Services\KPI\PerformanceAppraisal\PaBobotGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Memeriksa kesiapan satu siklus PA — TANPA MENULIS APA PUN.
 *
 * Direncanakan di `plans/kpi/2026-08-31-performance-appraisal-arsitektur-konsolidasi.md`
 * bagian 5 ("perintah verifikasi `pa:verify` yang membaca ulang keadaan"), lalu terlewat.
 * Dibangun 3 September 2026 sesudah uji rantai penuh membuktikan kenapa ia dibutuhkan.
 *
 * BEDANYA DENGAN `pa:audit`
 *
 * `pa:audit` memeriksa KODE: apakah tiap fitur punya service, uji, rute, kontrak. Ia
 * melaporkan 115/115 pada hari modul ini tidak bisa dijalani sampai selesai.
 *
 * `pa:verify` memeriksa DATA satu siklus: apakah siklus INI bisa dijalankan sampai
 * hasilnya terbit. Keduanya bisa hijau sendiri-sendiri sementara siklusnya tetap macet —
 * dan itulah yang terjadi.
 *
 * KENAPA TIAP PEMERIKSAAN ADA
 *
 * Setiap butir di bawah pernah benar-benar terjadi, bukan dibayangkan. Yang paling mahal
 * bukan yang menghentikan proses dengan galat, melainkan yang **diam**: bobot KPI
 * berjumlah 200 yang lolos tanpa ditandai, penugasan tanpa token yang membuat penilainya
 * tidak pernah menerima tautan, paket soal yang tidak menutup jenis kompetensi wajib.
 * Ketiganya menghasilkan siklus yang tampak sehat sampai hasilnya keluar salah.
 */
class PaVerifyCommand extends Command
{
    protected $signature = 'pa:verify
                            {kode? : Kode siklus. Kosong = seluruh siklus yang belum PUBLISHED}
                            {--rinci : Tampilkan daftar orangnya, bukan hanya jumlahnya}
                            {--batas=10 : Berapa contoh yang ditampilkan per temuan}';

    protected $description = 'Memeriksa kesiapan data satu siklus PA. Read-only, tidak menulis apa pun.';

    private int $temuan = 0;

    private int $peringatan = 0;

    public function handle(PaBobotGuard $bobot): int
    {
        $this->info('VERIFIKASI KESIAPAN SIKLUS — PERFORMANCE APPRAISAL');
        $this->line(str_repeat('=', 78));
        $this->warn('  Read-only. Perintah ini tidak menulis satu baris pun.');

        $siklus = $this->siklusYangDiperiksa();

        if ($siklus->isEmpty()) {
            $this->newLine();
            $this->error('  Tidak ada siklus yang cocok.');

            return self::FAILURE;
        }

        foreach ($siklus as $s) {
            $this->periksaSatu($s, $bobot);
        }

        $this->newLine();
        $this->line(str_repeat('=', 78));

        if ($this->temuan === 0 && $this->peringatan === 0) {
            $this->info('  Tidak ada temuan.');

            return self::SUCCESS;
        }

        $this->line(sprintf(
            '  %d temuan yang menghalangi, %d peringatan.',
            $this->temuan,
            $this->peringatan,
        ));

        /* Keluar dengan kode gagal HANYA bila ada yang benar-benar menghalangi.
           Peringatan tidak boleh menggagalkan perintah: kalau ia ikut menggagalkan, orang
           akan berhenti menjalankannya sama sekali, dan yang menghalangi pun ikut
           tidak terlihat. */
        return $this->temuan === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function periksaSatu(object $s, PaBobotGuard $bobot): void
    {
        $id = (int) $s->Id_PA_Cycle;

        $this->newLine();
        $this->line(str_repeat('-', 78));
        $this->info(sprintf('  %s — %s  [%s]', $s->Kode_Cycle, $s->Nama_Cycle, $s->Status_Cycle));
        $this->newLine();

        $this->periksaJendela($s);
        $this->periksaAturanPenilai($id, $bobot);
        $this->periksaBobotPilar($id, $bobot);
        $this->periksaPesertaTertunda($id);
        $this->periksaPenugasan($id);
        $this->periksaToken($id);
        $this->periksaJendelaPintu($id, $s);
        $this->periksaCakupanPaket($id);
        $this->periksaButirTanpaKompetensi($id);
        $this->periksaBobotKpi($id);
        $this->periksaKurva($id);
        $this->periksaHasilTertahan($id);
        $this->periksaHasilTerhitung($id);
    }

    /* ------------------------------------------------------------------ *
     * Pemeriksaan                                                         *
     * ------------------------------------------------------------------ */

    private function periksaJendela(object $s): void
    {
        if ($s->Periode_Dinilai_Dari === null || $s->Periode_Dinilai_Sampai === null) {
            $this->halangi('Periode dinilai kosong',
                'Nilai KPI diambil YTD atas rentang ini. Tanpa rentang, perhitungan menolak berjalan.');
        } elseif ((int) $s->Tahun !== (int) substr((string) $s->Periode_Dinilai_Dari, 0, 4)) {
            /* Penjaga A09 sudah menolak ini saat siklus dibuat, tetapi siklus lama
               mungkin lahir sebelum penjaga itu ada. */
            $this->halangi('Tahun siklus tidak sesuai periode dinilai',
                sprintf('Tahun %s versus periode mulai %s. Pilar KPI menyaring jadwal memakai '
                    .'TAHUN — kalau berselisih, SETIAP peserta berakhir "Tidak ada KPI disetujui".',
                    $s->Tahun, $s->Periode_Dinilai_Dari));
        }

        if ($s->Pengisian_Dari === null || $s->Pengisian_Sampai === null) {
            $this->peringatkan('Jendela pengisian kosong',
                'Penugasan lahir tanpa tenggat, sehingga penilai yang tidak mengisi tidak akan '
                .'pernah tercatat terlambat. Membuka pengisian akan ditolak.');
        }
    }

    private function periksaAturanPenilai(int $id, PaBobotGuard $bobot): void
    {
        $snapshot = DB::table('N_PA_Cycle_Rater_Policy_Snapshot')->where('PA_Cycle_Id', $id)->count();

        if ($snapshot === 0) {
            $this->halangi('Salinan aturan penilai belum dibentuk',
                'Siklus belum disiapkan. Jalankan penyiapan lebih dulu.');

            return;
        }

        // Bobot kategori per aturan harus berjumlah 100 — kalau tidak, nilai kompetensi
        // lahir dari pembagi yang salah dan angkanya tetap terlihat wajar.
        $rusak = DB::table('N_PA_Cycle_Rater_Policy_Detail_Snapshot as d')
            ->join('N_PA_Cycle_Rater_Policy_Snapshot as p',
                'p.Id_PA_Cycle_Rater_Policy_Snapshot', '=', 'd.PA_Cycle_Rater_Policy_Snapshot_Id')
            ->where('p.PA_Cycle_Id', $id)
            ->groupBy('p.Id_PA_Cycle_Rater_Policy_Snapshot', 'p.Kode_Policy_Salinan')
            ->havingRaw('ABS(SUM(d.Bobot_Persen) - 100) > ?', [PaBobotGuard::TOLERANSI])
            ->selectRaw('p.Kode_Policy_Salinan as kode, SUM(d.Bobot_Persen) as total')
            ->get();

        if ($rusak->isNotEmpty()) {
            $this->halangi(
                sprintf('%d salinan aturan penilai bobotnya bukan 100', $rusak->count()),
                'Nilai kompetensi akan lahir dari pembagi yang salah, dan angkanya tetap wajar.',
                $rusak->map(fn ($r) => sprintf('%s = %s', $r->kode, $this->angka($r->total)))->all(),
            );
        }
    }

    private function periksaBobotPilar(int $id, PaBobotGuard $bobot): void
    {
        $baris = DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')
            ->where('PA_Cycle_Id', $id)
            ->get(['ID_Level', 'Bobot_KPI', 'Bobot_Kompetensi']);

        if ($baris->isEmpty()) {
            $this->halangi('Salinan bobot pilar belum ada',
                'Perhitungan menolak berjalan tanpa pembagian KPI versus kompetensi.');

            return;
        }

        $rusak = $baris->filter(
            fn ($b) => ! $bobot->berjumlahSeratus(['KPI' => (float) $b->Bobot_KPI, 'K' => (float) $b->Bobot_Kompetensi])
        );

        if ($rusak->isNotEmpty()) {
            $this->halangi(
                sprintf('%d level bobot pilarnya bukan 100', $rusak->count()),
                'Nilai akhir akan lahir dari pembagian yang tidak sah.',
                $rusak->map(fn ($b) => sprintf('level %s: KPI %s + kompetensi %s',
                    $b->ID_Level, $this->angka($b->Bobot_KPI), $this->angka($b->Bobot_Kompetensi)))->all(),
            );
        }
    }

    private function periksaPesertaTertunda(int $id): void
    {
        $n = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $id)
            ->where('Status_Peserta', 'TERTUNDA')
            ->where('Flag_Aktif', 'Y')
            ->count();

        if ($n > 0) {
            $this->halangi(
                sprintf('%d peserta masih TERTUNDA', $n),
                'Levelnya gagal diresolusi atau levelnya tidak punya aturan penilai. '
                .'Penguncian siklus akan ditolak selama keranjang ini terisi.',
                $this->contohPeserta($id, 'TERTUNDA'),
            );
        }
    }

    private function periksaPenugasan(int $id): void
    {
        $tanpa = DB::table('N_PA_Participant as p')
            ->where('p.PA_Cycle_Id', $id)
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('p.Flag_Aktif', 'Y')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('N_PA_Assignment as a')
                    ->whereColumn('a.PA_Participant_Id', 'p.Id_PA_Participant')
                    ->where('a.Flag_Aktif', 'Y');
            })
            ->count();

        if ($tanpa > 0) {
            $this->halangi(
                sprintf('%d peserta belum punya satu penilai pun', $tanpa),
                'Hasilnya pasti tertahan. Tetapkan penilainya, atau kecualikan orangnya bila '
                .'memang tidak dinilai siklus ini.',
            );
        }
    }

    private function periksaToken(int $id): void
    {
        $tanpa = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $id)
            ->where('a.Flag_Aktif', 'Y')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('N_PA_Assignment_Integration as i')
                    ->whereColumn('i.PA_Assignment_Id', 'a.Id_PA_Assignment')
                    ->where('i.Flag_Aktif', 'Y');
            })
            ->count();

        if ($tanpa > 0) {
            /* Peringatan, bukan penghalang — dan bedanya penting. Jalur pengiriman ke
               HC Learn memang BELUM ADA di kode produksi (menunggu kontraknya), jadi
               menandai ini sebagai penghalang akan membuat perintah ini selalu merah
               untuk sebab yang tidak bisa diperbaiki siapa pun hari ini. */
            $this->peringatkan(
                sprintf('%d penugasan belum punya token HC Learn', $tanpa),
                'Penilainya tidak akan menerima tautan formulir. Jalur pengiriman ke HC Learn '
                .'belum ada di kode produksi — menunggu kontrak HC Learn.',
            );
        }
    }

    /**
     * Pintu yang dicetak untuk jendela yang SUDAH TIDAK BERLAKU.
     *
     * Sejak 6 September 2026 jendela pengisian boleh digeser selagi siklus berjalan. Yang
     * tidak ikut bergeser adalah pintu yang sudah dicetak: token di HC Learn tetap membawa
     * rentang tanggal lama, dan penilai yang membukanya akan ditolak "belum waktunya" atau
     * "sudah lewat" pada tanggal yang menurut HCIS masih sah.
     *
     * Tidak ada apa pun yang memberitahukannya. Admin menggeser jendela, layar HCIS berubah,
     * dan yang gagal adalah sesuatu di sistem orang lain — yang keluhannya datang satu per
     * satu dari penilai, berminggu-minggu kemudian, dan terbaca seperti "HC Learn sedang
     * bermasalah".
     *
     * Peringatan, bukan penghalang: perbaikannya `cetakUlangPintu()` per penugasan, dan
     * selama pencetakan masih simulasi hal ini tak berakibat apa-apa.
     *
     * Pintu yang jejaknya TIDAK memuat jendela sama sekali dilewati, bukan dihitung
     * menyimpang — ia dicetak sebelum jendelanya mulai dicatat, dan menuduhnya menyimpang
     * berarti melaporkan seluruh siklus lama sebagai bermasalah.
     */
    private function periksaJendelaPintu(int $id, object $s): void
    {
        $dari = $s->Pengisian_Dari === null ? null : substr((string) $s->Pengisian_Dari, 0, 10);
        $sampai = $s->Pengisian_Sampai === null ? null : substr((string) $s->Pengisian_Sampai, 0, 10);

        if ($dari === null && $sampai === null) {
            // Jendela kosongnya sendiri sudah dilaporkan `periksaJendela()`.
            return;
        }

        $menyimpang = 0;
        $contoh = [];

        DB::table('N_PA_Assignment_Integration as i')
            ->join('N_PA_Assignment as a', 'a.Id_PA_Assignment', '=', 'i.PA_Assignment_Id')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $id)
            ->where('i.Flag_Aktif', 'Y')
            ->whereNotNull('i.Payload_Json')
            ->orderBy('i.Id_PA_Assignment_Integration')
            ->select(['i.Id_Ujian_Token', 'i.Payload_Json'])
            ->chunk(1500, function ($baris) use (&$menyimpang, &$contoh, $dari, $sampai) {
                foreach ($baris as $b) {
                    $jejak = json_decode((string) $b->Payload_Json, true);

                    if (! is_array($jejak) || ! array_key_exists('jendela_dari', $jejak)) {
                        continue;
                    }

                    $pintuDari = $jejak['jendela_dari'] === null
                        ? null
                        : substr((string) $jejak['jendela_dari'], 0, 10);
                    $pintuSampai = $jejak['jendela_sampai'] === null
                        ? null
                        : substr((string) $jejak['jendela_sampai'], 0, 10);

                    if ($pintuDari === $dari && $pintuSampai === $sampai) {
                        continue;
                    }

                    $menyimpang++;

                    if (count($contoh) < 5) {
                        $contoh[] = sprintf(
                            '%s dicetak untuk %s s.d. %s',
                            $b->Id_Ujian_Token,
                            $pintuDari ?? '-',
                            $pintuSampai ?? '-',
                        );
                    }
                }
            });

        if ($menyimpang === 0) {
            return;
        }

        $this->peringatkan(
            sprintf('%d pintu dicetak untuk jendela yang sudah tidak berlaku', $menyimpang),
            sprintf(
                'Jendela siklus sekarang %s s.d. %s. Token di HC Learn masih membawa rentang '
                .'lama, sehingga penilainya bisa ditolak pada tanggal yang menurut HCIS masih '
                .'sah. Perbaikannya: cetak ulang pintu penugasan yang bersangkutan.',
                $dari ?? '-',
                $sampai ?? '-',
            ),
            $contoh,
        );
    }

    private function periksaCakupanPaket(int $id): void
    {
        // Jenis kompetensi WAJIB per level, dibandingkan dengan jenis yang benar-benar
        // dimuat paket soal siklus ini pada level yang sama.
        $kurang = DB::table('N_PA_Cycle_Competency_Requirement_Snapshot as w')
            ->where('w.PA_Cycle_Id', $id)
            ->where('w.Flag_Wajib', 'Y')
            ->whereNotExists(function ($q) use ($id) {
                $q->select(DB::raw(1))
                    ->from('N_PA_Cycle_Question_Package as pk')
                    ->join('N_PA_Cycle_Question_Package_Item as it',
                        'it.PA_Cycle_Question_Package_Id', '=', 'pk.Id_PA_Cycle_Question_Package')
                    ->join('N_PA_Competency as c', 'c.Id_PA_Competency', '=', 'it.PA_Competency_Id')
                    ->where('pk.PA_Cycle_Id', $id)
                    ->whereColumn('pk.ID_Level', 'w.ID_Level')
                    ->whereColumn('c.PA_Competency_Type_Id', 'w.PA_Competency_Type_Id');
            })
            ->get(['w.ID_Level', 'w.Kode_Type_Salinan']);

        if ($kurang->isNotEmpty()) {
            $this->halangi(
                sprintf('%d jenis kompetensi wajib tidak tercakup paket soal', $kurang->count()),
                'Hasil peserta pada level itu akan DITAHAN. Lengkapi paketnya, atau cabut '
                .'kewajiban jenis itu bagi mereka.',
                $kurang->map(fn ($r) => sprintf('level %s butuh %s', $r->ID_Level, $r->Kode_Type_Salinan))->all(),
            );
        }
    }

    /**
     * Butir soal yang tidak menunjuk kompetensi mana pun.
     *
     * Butir seperti ini DITERIMA basis data dan DIISI penilai dengan normal. Jawabannya
     * tersimpan lengkap di `N_PA_Assignment_Score`, dan tidak satu pun galat muncul di mana
     * pun — tetapi ia tidak bisa ditempatkan di panel apa pun, jadi tidak pernah sampai ke
     * report peserta. Yang terlihat admin hanyalah pilar yang nilainya kurang, atau hasil
     * yang tertahan karena jenis kompetensi wajib "tidak tercakup", padahal soalnya ada dan
     * jawabannya ada.
     *
     * Sudah terjadi: 16 butir uraian pada dua siklus percobaan, 504 jawaban, ditemukan hanya
     * karena satu peserta tertahan tanpa sebab yang masuk akal (DML-2026-106). Pemeriksaan
     * ini membuatnya terlihat pada siklus BERIKUTNYA sebelum ada yang mengisi apa pun.
     */
    private function periksaButirTanpaKompetensi(int $id): void
    {
        $butir = DB::table('N_PA_Cycle_Question_Package_Item as it')
            ->join('N_PA_Cycle_Question_Package as pk',
                'pk.Id_PA_Cycle_Question_Package', '=', 'it.PA_Cycle_Question_Package_Id')
            ->where('pk.PA_Cycle_Id', $id)
            ->where('pk.Flag_Aktif', 'Y')
            ->where('it.Flag_Aktif', 'Y')
            ->whereNull('it.PA_Competency_Id')
            ->orderBy('pk.ID_Level')
            ->orderBy('it.Id_Jenis_Soal')
            ->get(['pk.ID_Level', 'it.Id_Jenis_Soal', 'it.Nama_Bagian_Sumber']);

        if ($butir->isEmpty()) {
            return;
        }

        $this->halangi(
            sprintf('%d butir soal tidak menunjuk kompetensi', $butir->count()),
            'Butirnya tetap ditanyakan dan tetap dijawab, tetapi jawabannya tidak akan '
            .'muncul di report mana pun — tidak sebagai nilai, tidak pula sebagai catatan. '
            .'Petakan butirnya ke kompetensi di master paket soal sebelum pengisian dibuka.',
            $butir->take(10)->map(fn ($r) => sprintf(
                'level %s: %s (%s)', $r->ID_Level, $r->Id_Jenis_Soal, $r->Nama_Bagian_Sumber ?? '-',
            ))->all(),
        );
    }

    private function periksaBobotKpi(int $id): void
    {
        $siklus = DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $id)->first(['Tahun']);

        /* Total bobot parameter KPI yang DISETUJUI, tanpa memandang realisasi.
           Inilah pemeriksaan yang sampai 2 September 2026 salah himpunan: mesin
           menghitungnya dari parameter yang sudah punya realisasi saja, sehingga menahan
           orang yang masternya sehat dan MELOLOSKAN yang masternya berjumlah 200. */
        $rusak = DB::table('N_PA_Participant as p')
            ->join('KPI_Users as u', 'u.Kode_Users', '=', 'p.Kode_Karyawan')
            ->join('KPI_Pengajuan as g', function ($j) {
                $j->on('g.Id_User', '=', 'u.Id_Users')
                    ->where('g.Flag_Aktif', 'Y')->where('g.Flag_Approval', 'Y')
                    ->whereNull('g.Status');
            })
            ->join('KPI_Master_Penjadwalan as mp', function ($j) use ($siklus) {
                $j->on('mp.Id_Master_Penjadwalan', '=', 'g.Id_Penjadwalan')
                    ->where('mp.Tahun', (string) $siklus->Tahun);
            })
            ->join('KPI_Pengajuan_Detail as d', 'd.Id_Pengajuan', '=', 'g.Id_Pengajuan')
            ->where('p.PA_Cycle_Id', $id)
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('p.Flag_Aktif', 'Y')
            ->groupBy('p.Kode_Karyawan', 'p.Nama_Karyawan_Saat_Cycle')
            ->havingRaw('ABS(SUM(d.Bobot) - 100) > ?', [PaBobotGuard::TOLERANSI])
            ->selectRaw('p.Kode_Karyawan as kode, p.Nama_Karyawan_Saat_Cycle as nama, SUM(d.Bobot) as total')
            ->get();

        if ($rusak->isNotEmpty()) {
            $this->halangi(
                sprintf('%d peserta total bobot KPI-nya bukan 100', $rusak->count()),
                'Hasil mereka akan ditahan. Ini cacat di master KPI, bukan di PA — '
                .'perbaikannya di rencana KPI orang itu.',
                $rusak->map(fn ($r) => sprintf('%s %s = %s', $r->kode, $r->nama, $this->angka($r->total)))->all(),
            );
        }
    }

    private function periksaHasilTertahan(int $id): void
    {
        $baris = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $id)
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('r.Flag_Aktif', 'Y')
            ->where('r.Status_Hasil', 'TERTAHAN')
            ->get(['p.Kode_Karyawan', 'r.Alasan_Tertahan']);

        if ($baris->isEmpty()) {
            return;
        }

        /* Dikelompokkan menurut KALIMAT PERTAMA sebabnya. Menampilkan 200 baris tertahan
           satu per satu menyembunyikan bahwa semuanya berasal dari satu sebab yang sama —
           dan satu sebab yang sama biasanya satu perbaikan. */
        $per = [];

        foreach ($baris as $b) {
            $sebab = (string) ($b->Alasan_Tertahan ?? 'tanpa sebab tercatat');
            $kunci = trim(explode('.', $sebab)[0]);
            $per[$kunci] = ($per[$kunci] ?? 0) + 1;
        }

        arsort($per);

        $this->peringatkan(
            sprintf('%d hasil TERTAHAN', $baris->count()),
            'Tertahan bukan kegagalan — itu keputusan sadar mesin untuk tidak mengarang angka. '
            .'Tetapi tiap sebab biasanya satu perbaikan.',
            array_map(fn ($k, $n) => sprintf('%dx %s', $n, mb_substr($k, 0, 90)),
                array_keys($per), array_values($per)),
        );
    }

    /**
     * Menghitung ULANG setiap hasil yang sudah terhitung, lalu membandingkannya.
     *
     * ------------------------------------------------------------------------------
     * KENAPA DIHITUNG ULANG DI SINI, BUKAN DENGAN MEMANGGIL MESINNYA
     * ------------------------------------------------------------------------------
     *
     * Memanggil PaResultCalculationService untuk memeriksa PaResultCalculationService
     * hanya membuktikan mesinnya konsisten dengan dirinya sendiri. Kesalahan rumus tidak
     * akan pernah terlihat dari sana: ia akan salah dengan cara yang sama di kedua sisi,
     * dan hasilnya cocok sempurna.
     *
     * Karena itu rantainya ditulis ulang di sini dari tabel MENTAH — normalisasi butir,
     * pembobotan penilai, pembobotan kategori, pembagian ulang, pencampuran pilar. Dua
     * implementasi yang lahir dari pembacaan berbeda; kalau keduanya sepakat pada seluruh
     * peserta, angkanya bisa dipercaya. Kalau tidak, salah satunya keliru dan perintah ini
     * menunjuk pesertanya.
     *
     * Yang TIDAK dihitung ulang: nilai KPI. Ia datang dari modul lain lewat rantai
     * persetujuan yang punya pemeriksanya sendiri, dan menyalinnya ke sini berarti
     * menduplikasi aturan yang bukan milik PA. Nilainya dipakai apa adanya dari hasil
     * tersimpan, sehingga yang diuji tetap pencampurannya.
     *
     * Peringatan, bukan penghalang: perbedaannya bisa juga berarti data sumbernya berubah
     * SESUDAH hasil dihitung — penilai ditambah, bobot disunting — dan itu keadaan sah
     * yang perbaikannya adalah menghitung ulang, bukan memperbaiki kode.
     */
    /**
     * Kurva grade (siklus KURVA). Dihitung ULANG dari batas z salinan dan μ/σ yang dibekukan
     * — tidak lewat PaKurvaService, supaya pemverifikasi tidak memeriksa mesin dengan mesin.
     */
    private function periksaKurva(int $id): void
    {
        if (DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $id)->value('Metode_Grade') !== 'KURVA') {
            return;
        }

        $kurva = DB::table('N_PA_Cycle_Curve as k')
            ->join('N_PA_Cycle_Curve_Group as g', 'g.Id_PA_Cycle_Curve_Group', '=', 'k.PA_Cycle_Curve_Group_Id')
            ->where('k.PA_Cycle_Id', $id)->where('k.Flag_Aktif', 'Y')
            ->get(['k.Id_PA_Cycle_Curve', 'g.Kode_Grup', 'k.Jumlah_Peserta', 'k.Rata_Rata', 'k.Simpangan_Baku']);

        if ($kurva->isEmpty()) {
            $this->peringatkan('Kurva grade belum dikunci', 'Grade siklus bermetode KURVA baru ada sesudah admin mengunci kurva di layar Distribusi.');

            return;
        }

        // Grup yang kosong saat dikunci sengaja tanpa kurva; hasil susulannya TERTAHAN.
        $tanpaKurva = DB::table('N_PA_Cycle_Curve_Group')->where('PA_Cycle_Id', $id)->where('Flag_Aktif', 'Y')
            ->whereNotIn('Kode_Grup', $kurva->pluck('Kode_Grup')->all())->pluck('Kode_Grup')->all();

        if ($tanpaKurva !== []) {
            $this->peringatkan(count($tanpaKurva).' grup kurva tanpa kurva beku', 'Kosong saat dikunci; hasil yang menyusul di grup ini TERTAHAN sampai kurva dibuka lalu dikunci ulang.', $tanpaKurva);
        }

        $kecil = $kurva->filter(fn ($k) => (int) $k->Jumlah_Peserta < 30)
            ->map(fn ($k) => "{$k->Kode_Grup}: {$k->Jumlah_Peserta} orang")->values()->all();

        if ($kecil !== []) {
            $this->peringatkan(count($kecil).' grup kurva di bawah 30 orang', 'Kurvanya kurang bermakna; pertimbangkan menggabung level.', $kecil);
        }

        $z = DB::table('N_PA_Cycle_Score_Range_Snapshot')->where('PA_Cycle_Id', $id)->where('Flag_Aktif', 'Y')
            ->where('Score_Type', 'FINAL')->where('Score_Usage', 'GRADE')->pluck('Batas_Z', 'Kode_Range');
        $salah = [];

        foreach ($kurva as $k) {
            if ($k->Simpangan_Baku === null || (float) $k->Simpangan_Baku <= 0) {
                continue;
            }

            foreach (DB::table('N_PA_Cycle_Curve_Band')->where('PA_Cycle_Curve_Id', $k->Id_PA_Cycle_Curve)->whereNotNull('Batas_Z')->get() as $b) {
                $harus = max(0.0, round((float) $k->Rata_Rata + (float) $z[$b->Kode_Range] * (float) $k->Simpangan_Baku, 4));

                if (abs($harus - (float) $b->Nilai_Dari) > 0.0001) {
                    $salah[] = sprintf('%s %s: pita %s, seharusnya %s', $k->Kode_Grup, $b->Kode_Range, $b->Nilai_Dari, $harus);
                }
            }
        }

        if ($salah !== []) {
            $this->halangi('Pita kurva tidak sama dengan μ + z·σ', 'Pita beku disunting di luar aplikasi atau batas z salinan berubah sesudah dikunci.', $salah);
        }
    }

    private function periksaHasilTerhitung(int $id): void
    {
        $metode = trim((string) DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $id)->value('Metode_Grade'));

        $hasil = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $id)
            ->where('r.Flag_Aktif', 'Y')
            /* TERTAHAN ikut diperiksa. Kebanyakan penahanan terjadi di pilar KPI —
               akun belum ada, belum disetujui, bobot bukan 100 — sementara pilar
               KOMPETENSInya sudah terhitung penuh dan tersimpan. Melewatkannya berarti
               membuang bagian terbesar dari yang sebenarnya bisa diverifikasi. */
            ->whereIn('r.Status_Hasil', ['DIHITUNG', 'TERBIT', 'TERTAHAN'])
            ->get([
                'r.PA_Participant_Id', 'p.Kode_Karyawan', 'p.PA_Cycle_Rater_Policy_Snapshot_Id',
                'r.Status_Hasil',
                'r.Nilai_KPI', 'r.Nilai_Kompetensi', 'r.Final_Score', 'r.Kode_Grade_Sistem', 'r.PA_Cycle_Curve_Id',
                'r.Bobot_KPI_Terpakai', 'r.Bobot_Kompetensi_Terpakai',
                'r.Jumlah_Rater_Diharapkan', 'r.Jumlah_Rater_Mengisi',
            ])
            ->keyBy('PA_Participant_Id');

        if ($hasil->isEmpty()) {
            return;
        }

        $tugas = DB::table('N_PA_Assignment')
            ->whereIn('PA_Participant_Id', $hasil->keys())
            ->where('Flag_Aktif', 'Y')
            ->get(['Id_PA_Assignment', 'PA_Participant_Id', 'PA_Rater_Category_Id',
                'Bobot_Dalam_Kategori', 'Status_Penugasan'])
            ->groupBy('PA_Participant_Id');

        /* Dipotong 1.500 per kueri: SQL Server menolak lebih dari 2.100 parameter, dan
           satu siklus penuh bisa punya ribuan penugasan. Mesin aslinya tak menemui batas
           ini karena ia bekerja satu peserta sekali jalan. */
        $payload = collect();

        foreach ($tugas->flatten()->pluck('Id_PA_Assignment')->chunk(1500) as $potongan) {
            $payload = $payload->merge(
                DB::table('N_PA_Assignment_Payload as pl')
                    ->join('N_PA_Assignment_Integration as i',
                        'i.Id_PA_Assignment_Integration', '=', 'pl.PA_Assignment_Integration_Id')
                    ->whereIn('i.PA_Assignment_Id', $potongan)
                    ->where('pl.Flag_Terpeta', 'Y')
                    ->orderBy('i.PA_Assignment_Id')
                    ->orderByDesc('pl.Id_PA_Assignment_Payload')
                    ->get(['pl.Id_PA_Assignment_Payload', 'i.PA_Assignment_Id', 'pl.Diterima_At'])
            );
        }

        $payload = $payload->unique('PA_Assignment_Id')->keyBy('PA_Assignment_Id');
        $skor = collect();

        foreach ($payload->pluck('Id_PA_Assignment_Payload')->chunk(1500) as $potongan) {
            $skor = $skor->merge(
                DB::table('N_PA_Assignment_Score')
                    ->whereIn('PA_Assignment_Payload_Id', $potongan)
                    ->where('Flag_Aktif', 'Y')
                    ->whereNotNull('Skor')
                    ->where('Skor_Maksimal', '>', 0)
                    ->get(['PA_Assignment_Payload_Id', 'PA_Competency_Id', 'Skor', 'Skor_Maksimal', 'Bobot_Sumber'])
            );
        }

        /* KALIBRASI PENILAI DITERAPKAN SENDIRI, tidak lewat PaSkorEfektif. Perintah ini
           berguna justru karena ia menghitung ulang dengan jalannya sendiri; memakai pintu
           yang sama dengan mesin berarti kesalahan di pintu itu akan cocok sempurna di kedua
           sisi. Tanpa langkah ini, setiap hasil dari skor terkalibrasi dilaporkan berselisih. */
        $kalibrasi = $this->kalibrasiPenilaiTerbaru(
            $payload->pluck('PA_Assignment_Id')->map(fn ($x) => (int) $x)->all(),
            $payload->mapWithKeys(fn ($p) => [(int) $p->PA_Assignment_Id => $p->Diterima_At])->all(),
        );
        $payloadKeTugas = $payload
            ->mapWithKeys(fn ($p) => [(int) $p->Id_PA_Assignment_Payload => (int) $p->PA_Assignment_Id])
            ->all();

        $skor = $skor->map(function ($b) use ($kalibrasi, $payloadKeTugas) {
            $tugasId = $payloadKeTugas[(int) $b->PA_Assignment_Payload_Id] ?? null;
            $kompetensiId = $b->PA_Competency_Id === null ? null : (int) $b->PA_Competency_Id;

            if ($tugasId !== null && $kompetensiId !== null && isset($kalibrasi[$tugasId][$kompetensiId])) {
                $b->Skor = $kalibrasi[$tugasId][$kompetensiId];
            }

            return $b;
        })->groupBy('PA_Assignment_Payload_Id');

        $aturan = DB::table('N_PA_Cycle_Rater_Policy_Detail_Snapshot')
            ->whereIn('PA_Cycle_Rater_Policy_Snapshot_Id',
                $hasil->pluck('PA_Cycle_Rater_Policy_Snapshot_Id')->filter()->unique())
            ->where('Flag_Aktif', 'Y')
            ->get(['PA_Cycle_Rater_Policy_Snapshot_Id', 'PA_Rater_Category_Id',
                'Kode_Kategori_Salinan', 'Bobot_Persen', 'Min_Rater', 'Min_Rater_Selesai'])
            ->groupBy('PA_Cycle_Rater_Policy_Snapshot_Id');

        $pita = DB::table('N_PA_Cycle_Score_Range_Snapshot')
            ->where('PA_Cycle_Id', $id)
            ->where('Flag_Aktif', 'Y')
            ->where('Score_Type', 'FINAL')
            ->where('Score_Usage', 'GRADE')
            ->get(['Kode_Range', 'Nilai_Dari', 'Nilai_Sampai']);

        // Siklus KURVA: pita tiap hasil dibaca dari kurva beku yang memberinya grade.
        $pitaKurva = DB::table('N_PA_Cycle_Curve_Band')
            ->whereIn('PA_Cycle_Curve_Id', $hasil->pluck('PA_Cycle_Curve_Id')->filter()->unique()->values()->all() ?: [0])
            ->get(['PA_Cycle_Curve_Id', 'Kode_Range', 'Nilai_Dari', 'Nilai_Sampai'])
            ->groupBy('PA_Cycle_Curve_Id');

        /* Lapisan Balancing dibaca lewat PINTU YANG SAMA dengan mesin hitung
           (PaBalancingNilaiBerlaku). Salinan aturan "sesi FINAL terbaru" di sini akan diam-diam
           berbeda dari mesinnya begitu aturan itu berubah — dan verifikasi yang ikut berubah
           bersama kesalahannya tidak memverifikasi apa pun. */
        $pintu = app(PaBalancingNilaiBerlaku::class);
        $pintu->muatSiklus((int) $id);
        $after = [];
        foreach ($hasil->keys() as $pid) {
            $nilai = $pintu->kompetensiBerlaku((int) $pid, (int) $id);
            if ($nilai !== []) {
                $after[(int) $pid] = $nilai;
            }
        }

        $selisih = [];
        $diperiksa = 0;

        foreach ($hasil as $pid => $r) {
            $snap = $aturan->get($r->PA_Cycle_Rater_Policy_Snapshot_Id);

            if ($snap === null) {
                continue;
            }

            $anggota = [];

            foreach (($tugas->get($pid) ?? collect()) as $t) {
                $kid = (int) $t->PA_Rater_Category_Id;
                $anggota[$kid] ??= ['diharapkan' => 0, 'penilai' => []];
                $anggota[$kid]['diharapkan']++;

                if ($t->Status_Penugasan !== 'SELESAI') {
                    continue;
                }

                $pl = $payload->get($t->Id_PA_Assignment);
                // Butir tanpa kompetensi tidak membentuk nilai (aturan mesin sejak #704).
                $butir = $pl === null ? null : $skor->get($pl->Id_PA_Assignment_Payload)
                    ?->filter(fn ($b) => $b->PA_Competency_Id !== null)->values();

                if ($butir === null || $butir->isEmpty()) {
                    continue;
                }

                $anggota[$kid]['penilai'][] = [
                    'bobot' => (float) $t->Bobot_Dalam_Kategori,
                    'nilai' => $this->ulangNilaiPenilai($butir),
                    'butir' => $butir,
                ];
            }

            $bobotAturan = [];
            $nilaiKat = [];
            $diharapkan = 0;
            $mengisi = 0;

            foreach ($snap as $a) {
                $kode = $a->Kode_Kategori_Salinan;
                $bobotAturan[$kode] = (float) $a->Bobot_Persen;
                $isi = $anggota[(int) $a->PA_Rater_Category_Id] ?? ['diharapkan' => 0, 'penilai' => []];
                $n = count($isi['penilai']);
                $diharapkan += $isi['diharapkan'];
                $mengisi += $n;
                $minSelesai = $a->Min_Rater_Selesai === null ? (int) $a->Min_Rater : (int) $a->Min_Rater_Selesai;

                if ($n >= $minSelesai && $n > 0) {
                    $nilaiKat[$kode] = $this->ulangNilaiKategori($isi['penilai']);
                }
            }

            if ($nilaiKat === []) {
                /* Sah bila hasilnya memang tertahan tanpa nilai kompetensi — itu justru
                   yang seharusnya terjadi. Yang janggal hanya bila hasilnya sudah
                   terhitung namun tak satu kategori pun memenuhi ambang. */
                if ($r->Status_Hasil !== 'TERTAHAN') {
                    $selisih[] = sprintf('%s: berstatus terhitung tetapi tak satu kategori pun memenuhi ambang',
                        $r->Kode_Karyawan);
                }

                continue;
            }

            if ($r->Nilai_Kompetensi === null) {
                /* Tertahan SEBELUM pilar kompetensi menghasilkan angka (mis. jenis wajib
                   tak tercakup). Tidak ada yang bisa dibandingkan. */
                continue;
            }

            $diperiksa++;

            $total = array_sum(array_intersect_key($bobotAturan, $nilaiKat));
            $kompetensi = 0.0;

            foreach ($nilaiKat as $kode => $v) {
                $kompetensi += $v * ($bobotAturan[$kode] / $total);
            }

            /* LAPISAN BALANCING, ditulis ulang di sini (14 Okt 2026). Tanpanya setiap peserta
               yang dibahas di sesi FINAL dilaporkan "selisih" — padahal mesinnya benar dan yang
               tidak lengkap justru pemverifikasinya. */
            if (isset($after[$pid])) {
                $bobotTerpakai = [];
                foreach ($nilaiKat as $kode => $v) {
                    $bobotTerpakai[$kode] = $bobotAturan[$kode] / $total * 100;
                }

                $kodeKategori = $snap->mapWithKeys(fn ($a) => [(int) $a->PA_Rater_Category_Id => $a->Kode_Kategori_Salinan]);
                $kompetensi = $this->ulangLapisanBalancing($kompetensi, $anggota, $kodeKategori->all(), $bobotTerpakai, $after[$pid]);
            }

            $kompetensi = round($kompetensi, 2);
            $terhitung = $r->Status_Hasil !== 'TERTAHAN';
            $final = ! $terhitung ? null : round(
                (float) $r->Nilai_KPI * (float) $r->Bobot_KPI_Terpakai / 100
                + $kompetensi * (float) $r->Bobot_Kompetensi_Terpakai / 100,
                2
            );

            $grade = null;
            $pitaHasil = $r->PA_Cycle_Curve_Id !== null ? ($pitaKurva[$r->PA_Cycle_Curve_Id] ?? collect()) : $pita;

            // Siklus KURVA yang belum dikunci belum punya grade untuk diperiksa.
            if ($final !== null && ! ($metode === 'KURVA' && $r->PA_Cycle_Curve_Id === null)) {
                foreach ($pitaHasil as $p) {
                    if ((float) $p->Nilai_Dari <= $final && $final < (float) $p->Nilai_Sampai) {
                        $grade = $p->Kode_Range;
                        break;
                    }
                }
            }

            /* Toleransi 0,02 pada nilai kompetensi: mesin membagi ulang bobotnya ke dua
               angka desimal DULU lalu mengalikan, sedangkan di sini pembagiannya dibiarkan
               penuh. Keduanya benar; selisih pembulatannya tidak boleh dilaporkan sebagai
               cacat. Yang di atas itu bukan pembulatan lagi. */
            if (abs($kompetensi - (float) $r->Nilai_Kompetensi) > 0.02) {
                $selisih[] = sprintf('%s: Nilai_Kompetensi tersimpan %s, hitung ulang %s',
                    $r->Kode_Karyawan, $r->Nilai_Kompetensi, $kompetensi);
            }

            if ($final !== null && abs($final - (float) $r->Final_Score) > 0.02) {
                $selisih[] = sprintf('%s: Final_Score tersimpan %s, hitung ulang %s',
                    $r->Kode_Karyawan, $r->Final_Score, $final);
            }

            if ($final !== null && $grade !== null && $grade !== $r->Kode_Grade_Sistem
                && abs($final - (float) $r->Final_Score) <= 0.02) {
                $selisih[] = sprintf('%s: grade tersimpan %s, pita menunjuk %s untuk nilai %s',
                    $r->Kode_Karyawan, $r->Kode_Grade_Sistem, $grade, $final);
            }

            if ((int) $r->Jumlah_Rater_Mengisi !== $mengisi) {
                $selisih[] = sprintf('%s: jumlah pengisi tersimpan %s, terhitung %d',
                    $r->Kode_Karyawan, $r->Jumlah_Rater_Mengisi, $mengisi);
            }

            if ((int) $r->Jumlah_Rater_Diharapkan !== $diharapkan) {
                $selisih[] = sprintf('%s: jumlah penilai diharapkan tersimpan %s, terhitung %d',
                    $r->Kode_Karyawan, $r->Jumlah_Rater_Diharapkan, $diharapkan);
            }
        }

        if ($selisih === []) {
            $this->line(sprintf(
                '  <fg=green>COCOK</>        %d hasil dihitung ulang dari data mentah; nol selisih.',
                $diperiksa,
            ));

            return;
        }

        $this->peringatkan(
            sprintf('%d selisih pada %d hasil yang dihitung ulang', count($selisih), $diperiksa),
            'Angka tersimpan tidak sama dengan hasil hitung ulang dari data mentah. Bisa berarti '
            .'data sumbernya berubah setelah hasil dihitung — penilai ditambah, bobot disunting — '
            .'yang perbaikannya menghitung ulang. Bila datanya tidak berubah, rumusnya yang keliru.',
            $selisih,
        );
    }

    /** Rata-rata butir satu penilai — ditulis ulang, bukan dipanggil dari service. */
    private function ulangNilaiPenilai($butir): float
    {
        $semuaBerbobot = $butir->every(fn ($b) => $b->Bobot_Sumber !== null);
        $totalBobot = $semuaBerbobot ? (float) $butir->sum(fn ($b) => (float) $b->Bobot_Sumber) : 0.0;
        $jumlah = 0.0;

        if ($semuaBerbobot && $totalBobot > 0) {
            foreach ($butir as $b) {
                $jumlah += ((float) $b->Skor / (float) $b->Skor_Maksimal * 100) * (float) $b->Bobot_Sumber;
            }

            return round($jumlah / $totalBobot, 4);
        }

        foreach ($butir as $b) {
            $jumlah += (float) $b->Skor / (float) $b->Skor_Maksimal * 100;
        }

        return round($jumlah / $butir->count(), 4);
    }

    /** @param list<array{bobot:float, nilai:float}> $penilai */
    private function ulangNilaiKategori(array $penilai): float
    {
        $totalBobot = 0.0;
        $jumlah = 0.0;

        foreach ($penilai as $p) {
            $totalBobot += $p['bobot'];
            $jumlah += $p['nilai'] * $p['bobot'];
        }

        if ($totalBobot <= 0) {
            return round(array_sum(array_column($penilai, 'nilai')) / count($penilai), 4);
        }

        return round($jumlah / $totalBobot, 4);
    }

    /* ------------------------------------------------------------------ *
     * Pelaporan                                                           *
     * ------------------------------------------------------------------ */

    /** @param list<string> $contoh */
    private function halangi(string $judul, string $sebab, array $contoh = []): void
    {
        $this->temuan++;
        $this->line("  <fg=red>MENGHALANGI</>  {$judul}");
        $this->line("               {$sebab}");
        $this->contoh($contoh);
    }

    /** @param list<string> $contoh */
    private function peringatkan(string $judul, string $sebab, array $contoh = []): void
    {
        $this->peringatan++;
        $this->line("  <fg=yellow>PERINGATAN</>   {$judul}");
        $this->line("               {$sebab}");
        $this->contoh($contoh);
    }

    /** @param list<string> $contoh */
    private function contoh(array $contoh): void
    {
        if ($contoh === [] || ! $this->option('rinci')) {
            if ($contoh !== []) {
                $this->line('               (pakai --rinci untuk melihat daftarnya)');
            }

            return;
        }

        $batas = max(1, (int) $this->option('batas'));

        foreach (array_slice($contoh, 0, $batas) as $c) {
            $this->line("                 - {$c}");
        }

        if (count($contoh) > $batas) {
            $this->line(sprintf('                 … dan %d lainnya', count($contoh) - $batas));
        }
    }

    private function siklusYangDiperiksa()
    {
        $q = DB::table('N_PA_Cycle');

        if ($kode = $this->argument('kode')) {
            $q->where('Kode_Cycle', $kode);
        } else {
            // Siklus yang sudah terbit tidak perlu diperiksa kesiapannya lagi.
            $q->where('Status_Cycle', '<>', 'PUBLISHED')
                ->where('Status_Cycle', '<>', 'ARCHIVED');
        }

        return $q->orderByDesc('Tahun')->orderByDesc('Id_PA_Cycle')
            ->get([
                'Id_PA_Cycle', 'Kode_Cycle', 'Nama_Cycle', 'Status_Cycle', 'Tahun',
                'Periode_Dinilai_Dari', 'Periode_Dinilai_Sampai',
                'Pengisian_Dari', 'Pengisian_Sampai',
            ]);
    }

    /** @return list<string> */
    private function contohPeserta(int $id, string $status): array
    {
        return DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $id)
            ->where('Status_Peserta', $status)
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->limit(50)
            ->get(['Kode_Karyawan', 'Nama_Karyawan_Saat_Cycle', 'Nama_Level_Saat_Cycle'])
            ->map(fn ($r) => sprintf('%s %s (%s)',
                $r->Kode_Karyawan, $r->Nama_Karyawan_Saat_Cycle, $r->Nama_Level_Saat_Cycle ?? 'level tak dikenal'))
            ->all();
    }

    private function angka(mixed $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }

    /**
     * Skor_Sesudah terbaru per (penugasan, kompetensi), ditulis ulang di sini dengan sengaja —
     * lihat catatan pada pemakainya di pemeriksaan hasil.
     *
     * @param  list<int>  $assignmentIds
     * @return array<int, array<int, float>>
     */
    private function kalibrasiPenilaiTerbaru(array $assignmentIds, array $diterima = []): array
    {
        $hasil = [];

        foreach (array_chunk(array_values(array_unique($assignmentIds)), 1500) as $potongan) {
            $baris = DB::table('N_PA_Kalibrasi_Penilai as k')
                ->join('N_PA_Kalibrasi_Penilai_Detail as d', 'd.PA_Kalibrasi_Penilai_Id', '=', 'k.Id_PA_Kalibrasi_Penilai')
                ->whereIn('k.PA_Assignment_Id', $potongan)
                ->orderByDesc('k.Id_PA_Kalibrasi_Penilai')
                ->get(['k.PA_Assignment_Id', 'k.Created_At', 'd.PA_Competency_Id', 'd.Skor_Sesudah']);

            foreach ($baris as $b) {
                // Kalibrasi yang lebih tua dari kiriman terpeta terakhir gugur (aturan mesin sejak #706).
                $batas = $diterima[(int) $b->PA_Assignment_Id] ?? null;
                if ($batas !== null && $b->Created_At !== null && strtotime((string) $b->Created_At) < strtotime((string) $batas)) {
                    continue;
                }

                $hasil[(int) $b->PA_Assignment_Id][(int) $b->PA_Competency_Id] ??= (float) $b->Skor_Sesudah;
            }
        }

        return $hasil;
    }

    /**
     * mesin + Σ bobotₖ × (Afterₖ − mesinₖ), dibatasi 0–100.
     *
     * mesinₖ = rata-rata kategori (bobot terpakai) dari rata-rata penilai (Bobot_Dalam_Kategori)
     * atas rata-rata butir kompetensi itu. bobotₖ = Σ kategori (bobot terpakai/100) × Σ penilai
     * (bobot/Σbobot) × porsi butir kompetensi itu di nilai penilainya.
     *
     * @param  array<int, array{diharapkan:int, penilai:list<array{bobot:float, nilai:float, butir:mixed}>}>  $anggota
     * @param  array<int, string>  $kodeKategori
     * @param  array<string, float>  $bobotTerpakai
     * @param  array<int, float>  $after
     */
    private function ulangLapisanBalancing(float $mesin, array $anggota, array $kodeKategori, array $bobotTerpakai, array $after): float
    {
        $bobotK = [];
        $agregat = [];
        $agregatBobot = [];

        foreach ($anggota as $kategoriId => $isi) {
            $kode = $kodeKategori[$kategoriId] ?? null;

            if ($kode === null || ! isset($bobotTerpakai[$kode]) || $isi['penilai'] === []) {
                continue;
            }

            $bt = $bobotTerpakai[$kode];
            $totalBobot = array_sum(array_map(fn ($p) => $p['bobot'], $isi['penilai']));
            $tertimbang = [];
            $bobotPer = [];
            $mentah = [];

            foreach ($isi['penilai'] as $p) {
                $w = $totalBobot > 0 ? $p['bobot'] / $totalBobot : 1 / count($isi['penilai']);
                $butir = $p['butir'];
                $semuaBerbobot = $butir->every(fn ($b) => $b->Bobot_Sumber !== null);
                $total = $semuaBerbobot ? (float) $butir->sum(fn ($b) => (float) $b->Bobot_Sumber) : 0.0;
                $pakaiBobot = $semuaBerbobot && $total > 0;
                $penyebut = $pakaiBobot ? $total : (float) $butir->count();
                $perK = [];

                foreach ($butir as $b) {
                    $c = (int) $b->PA_Competency_Id;
                    $bobotK[$c] = ($bobotK[$c] ?? 0.0) + ($bt / 100) * $w * (($pakaiBobot ? (float) $b->Bobot_Sumber : 1.0) / $penyebut);
                    $perK[$c][] = (float) $b->Skor / (float) $b->Skor_Maksimal * 100;
                }

                foreach ($perK as $c => $nilai) {
                    $v = round(array_sum($nilai) / count($nilai), 4);
                    $tertimbang[$c] = ($tertimbang[$c] ?? 0.0) + $v * max($p['bobot'], 0);
                    $bobotPer[$c] = ($bobotPer[$c] ?? 0.0) + max($p['bobot'], 0);
                    $mentah[$c][] = $v;
                }
            }

            foreach ($mentah as $c => $nilai) {
                $v = round($bobotPer[$c] > 0 ? $tertimbang[$c] / $bobotPer[$c] : array_sum($nilai) / count($nilai), 2);
                $agregat[$c] = ($agregat[$c] ?? 0.0) + $v * $bt;
                $agregatBobot[$c] = ($agregatBobot[$c] ?? 0.0) + $bt;
            }
        }

        $geser = 0.0;

        foreach ($after as $c => $nilai) {
            if (! isset($bobotK[$c], $agregat[$c]) || $agregatBobot[$c] <= 0) {
                continue;
            }

            $geser += $bobotK[$c] * ($nilai - $agregat[$c] / $agregatBobot[$c]);
        }

        return max(0.0, min(100.0, $mesin + $geser));
    }
}
