<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use App\Jobs\KPI\PerformanceAppraisal\PenjadwalanHcLearnPaJob;
use App\Services\KPI\PerformanceAppraisal\Katalog\PaLevelDikecualikan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * REVISI PESERTA SESUDAH SIKLUS DIKUNCI — satu jalur terkendali untuk perubahan per orang.
 *
 * Sesudah dikunci aturan siklus beku, tetapi kejadian per orang tetap datang: Level PA-nya
 * dikoreksi, orang yang dikecualikan ternyata harus dinilai, karyawan baru harus ikut.
 * Sebelum ini ketiganya tidak punya jalan sama sekali. Jalurnya selalu sama:
 *
 *   batalkan penugasan lama yang BELUM diisi (jadwal HC Learn-nya ikut dihapus, PR #673)
 *   → ubah / buat baris pesertanya
 *   → bentuk penugasan untuk orang itu saja (`bentukUntukPeserta`)
 *   → jadwalkan ke HC Learn sebagai tugas latar (hanya yang belum punya pintu).
 *
 * Penugasan yang SUDAH DIISI tidak pernah disentuh — itu jawaban orang. Karena itu koreksi
 * level ditolak bila sudah ada yang mengisi: jawabannya dibuat untuk soal level lama.
 *
 * Alasan wajib, dan tercatat di `Cancel_Reason` penugasan yang dibatalkan (dibaca lini masa
 * audit) dengan awalan `Revisi peserta:` — awalan yang juga membuat pembentukan ulang tidak
 * menganggap penilai lamanya "pernah dicabut admin".
 *
 * Kecualikan juga di sini, tetapi lewat jalurnya sendiri (`tambahPraKecuali`): penugasan yang
 * belum diisi dibatalkan dan jadwal HC Learn-nya dihapus; yang sudah diisi tetap tercatat.
 */
class PaRevisiPesertaService
{
    public const TERAPKAN_LEVEL = 'TERAPKAN_LEVEL';

    public const IKUTKAN_KEMBALI = 'IKUTKAN_KEMBALI';

    public const TAMBAH_SUSULAN = 'TAMBAH_SUSULAN';

    /* Lewat jalur pengecualian yang sudah ada (`tambahPraKecuali`) — di sini supaya SEMUA
       perubahan sesudah dikunci cukup dari satu dialog, bukan sebagian di Langkah 4. */
    public const KECUALIKAN = 'KECUALIKAN';

    /* Atasan/rekan/bawahan berganti sesudah dikunci (mutasi, pindah atasan): penilai USULAN yang
       belum mengisi dan tidak lagi ada di relasi diganti calon dari relasi terbaru. */
    public const SELARASKAN_RELASI = 'SELARASKAN_RELASI';

    /* Orang ini (peserta atau bukan) berhenti MENILAI orang lain — resign, cuti panjang.
       Penugasannya yang belum diisi dibatalkan dan tiap peserta yang ditinggal dibentukkan
       pengganti dari relasi. */
    public const LEPAS_PENILAI = 'LEPAS_PENILAI';

    public const SEMUA_TINDAKAN = [self::TERAPKAN_LEVEL, self::IKUTKAN_KEMBALI, self::TAMBAH_SUSULAN, self::KECUALIKAN,
        self::SELARASKAN_RELASI, self::LEPAS_PENILAI];

    /* SENGAJA bukan awalan pembatalan "sistem" (`PREFIKS_BATAL_SISTEM`): penilai yang dilepas
       terbaca "pernah dicabut", jadi pembentukan pengganti tidak memilihnya lagi. */
    public const AWALAN_PENILAI_DILEPAS = 'Penilai dilepas dari siklus: ';

    /* Rencana selaras dihitung per orang (membaca relasi); di atas ini pratinjau banyak orang
       tidak menghitungnya dan orang yang ternyata sudah sesuai dilewati saat dijalankan — satu
       rencana sekitar 0,45 detik di dev (siklus 14705), jadi 20 orang ≈ 9 detik. */
    public const MAKS_RENCANA_PRATINJAU = 20;

    /* Di bawah ini sisa jendela pengisian dianggap "hampir habis" bagi penilai baru. */
    public const JENDELA_HAMPIR_JAM = 72;

    /* Jatah peserta yang ditinggal yang diperiksa peringatan kategorinya per pratinjau. */
    public const MAKS_PESERTA_PERINGATAN = 40;

    public const AWALAN_ALASAN = 'Revisi peserta: ';

    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaCyclePreparationService $penyiapan,
        private readonly PaAssignmentBuilderService $penugasan,
        private readonly PaHcLearnScheduleService $jadwal,
        private readonly PaPenjadwalanTugasService $tugasJadwal,
        private readonly PaCycleSetupStepService $langkahSetup,
        private readonly PaHcLearnKaryawanSyncService $karyawanHcLearn,
    ) {}

    /* ------------------------------------------------------------------ *
     * Memo per siklus — revisi ratusan orang tidak boleh memindai HRIS    *
     * dan aturan level sekali per orang.                                  *
     * ------------------------------------------------------------------ */

    /** @var array<int, array<string, object>> */
    private array $memoKaryawan = [];

    /** @var array<int, array<int, int>> */
    private array $memoSalinan = [];

    /** @var array<int, array<int, true>> */
    private array $memoPaket = [];

    private function karyawan(int $cycleId, string $kodePerusahaan, string $kodeKaryawan): ?object
    {
        $this->memoKaryawan[$cycleId] ??= $this->penyiapan->petaKaryawanTerkini($cycleId);

        return $this->memoKaryawan[$cycleId][$kodePerusahaan.'|'.trim($kodeKaryawan)] ?? null;
    }

    /** @return array<int, int> */
    private function salinan(int $cycleId): array
    {
        return $this->memoSalinan[$cycleId] ??= $this->penyiapan->salinanAturanPerLevel($cycleId);
    }

    private function adaPaket(int $cycleId, int $levelId): bool
    {
        $this->memoPaket[$cycleId] ??= DB::table('N_PA_Cycle_Question_Package')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->distinct()
            ->pluck('ID_Level')
            ->filter()
            ->mapWithKeys(fn ($l) => [(int) $l => true])
            ->all();

        return isset($this->memoPaket[$cycleId][$levelId]);
    }

    public function lupakanMemo(): void
    {
        $this->memoKaryawan = $this->memoSalinan = $this->memoPaket = [];
    }

    /* ------------------------------------------------------------------ *
     * Pratinjau                                                           *
     * ------------------------------------------------------------------ */

    /**
     * Keadaan satu karyawan di siklus ini, tindakan yang boleh, dan dampaknya — dibaca SEBELUM
     * admin memutuskan.
     *
     * @return array<string, mixed>
     */
    public function pratinjau(int $cycleId, string $kodePerusahaan, string $kodeKaryawan): array
    {
        $status = $this->gate->status($cycleId);
        $peserta = $this->peserta($cycleId, $kodePerusahaan, $kodeKaryawan);
        $k = $this->karyawan($cycleId, $kodePerusahaan, $kodeKaryawan);

        $dampak = $peserta ? $this->dampak((int) $peserta->Id_PA_Participant) : ['penugasan_aktif' => 0, 'penugasan_diisi' => 0, 'jadwal_hclearn' => 0];
        $tugasMenilai = $this->sebagaiPenilai($cycleId, $kodePerusahaan, $kodeKaryawan);
        $dampak['menilai'] = count($tugasMenilai);
        $dampak['peringatan_lepas'] = $tugasMenilai === [] ? [] : $this->peringatanKategori($tugasMenilai);
        $rencana = $peserta && $peserta->Status_Peserta === 'DIIKUTKAN'
            ? $this->penugasan->rencanaSelaras((int) $peserta->Id_PA_Participant)
            : null;
        if ($rencana !== null) {
            $rencana['peringatan'] = $this->peringatanSelaras($peserta->Nama_Karyawan_Saat_Cycle, $rencana, (int) $peserta->Id_PA_Participant);
        }

        $tindakan = [];
        foreach (self::SEMUA_TINDAKAN as $t) {
            $sebab = $this->sebabTidakBoleh($t, $cycleId, $status, $peserta, $k, $dampak, null, $rencana);
            $tindakan[$t] = ['boleh' => $sebab === null, 'sebab' => $sebab, 'perlu_koreksi_level' => $this->perluKoreksiLevel];
        }

        return [
            'status_siklus' => $status,
            'peserta' => $peserta ? [
                'id' => (int) $peserta->Id_PA_Participant,
                'nama' => $peserta->Nama_Karyawan_Saat_Cycle,
                'status' => $peserta->Status_Peserta,
                'alasan_dikecualikan' => $peserta->Alasan_Dikecualikan,
                'id_level' => $peserta->ID_Level_Saat_Cycle === null ? null : (int) $peserta->ID_Level_Saat_Cycle,
                'nama_level' => $peserta->Nama_Level_Saat_Cycle,
            ] : null,
            'karyawan' => $k ? [
                'nama' => $k->Nama,
                'id_level' => $k->ID_Level === null ? null : (int) $k->ID_Level,
                'nama_level' => $k->nama_level,
            ] : null,
            'dampak' => $dampak,
            'rencana_selaras' => $rencana,
            'tindakan' => $tindakan,
            'jendela' => $this->keadaanJendela($cycleId),
            'alamat_koreksi_level' => self::ALAMAT_KOREKSI_LEVEL,
        ];
    }

    /* ------------------------------------------------------------------ *
     * Tindakan                                                            *
     * ------------------------------------------------------------------ */

    /**
     * @return array{tindakan:string, participant_id:int, dibatalkan:int, dibentuk:int, penjadwalan:?string, catatan:string}
     */
    public function jalankan(string $tindakan, int $cycleId, string $kodePerusahaan, string $kodeKaryawan, string $alasan, ?int $olehUserId = null, bool $jadwalkan = true, array $opsi = []): array
    {
        $this->hclearnTerakhir = null;
        $hasil = $this->jalankanTindakan($tindakan, $cycleId, $kodePerusahaan, $kodeKaryawan, $alasan, $olehUserId, $jadwalkan, $opsi);

        /* Penilai baru yang ditolak HC Learn disebut di hasilnya, bukan ditemukan belakangan
           sebagai jadwal GAGAL (diperiksa di `jadwalkan()`). */
        if ($this->hclearnTerakhir !== null) {
            $hasil['hclearn'] = $this->hclearnTerakhir;
            if (($kalimat = self::kalimatTidakSiap($this->hclearnTerakhir)) !== '') {
                $hasil['catatan'] = trim($hasil['catatan'].' '.$kalimat);
            }
        }

        return $hasil;
    }

    /** @return array<string, mixed> */
    private function jalankanTindakan(string $tindakan, int $cycleId, string $kodePerusahaan, string $kodeKaryawan, string $alasan, ?int $olehUserId, bool $jadwalkan, array $opsi): array
    {
        $alasan = trim($alasan);
        if (mb_strlen($alasan) < 5) {
            throw new RuntimeException('Alasan revisi wajib diisi (minimal 5 huruf) — ia satu-satunya jejak kenapa peserta ini diubah sesudah siklus dikunci.');
        }

        $status = $this->gate->status($cycleId);
        $peserta = $this->peserta($cycleId, $kodePerusahaan, $kodeKaryawan);
        $k = $this->karyawan($cycleId, $kodePerusahaan, $kodeKaryawan);
        $dampak = $peserta ? $this->dampak((int) $peserta->Id_PA_Participant) : ['penugasan_aktif' => 0, 'penugasan_diisi' => 0, 'jadwal_hclearn' => 0];
        $dampak['menilai'] = count($this->sebagaiPenilai($cycleId, $kodePerusahaan, $kodeKaryawan));

        /* Selaras: rencana yang KOSONG bukan kegagalan — orangnya sudah sesuai relasi. Ditolak
           di pratinjau satu orang, tetapi di jalan banyak orang ia cukup dilewati. */
        $rencana = $tindakan === self::SELARASKAN_RELASI && $peserta && $peserta->Status_Peserta === 'DIIKUTKAN'
            ? $this->penugasan->rencanaSelaras((int) $peserta->Id_PA_Participant)
            : null;

        if (($sebab = $this->sebabTidakBoleh($tindakan, $cycleId, $status, $peserta, $k, $dampak, null, $rencana, true)) !== null) {
            throw new RuntimeException($sebab);
        }

        if ($tindakan === self::SELARASKAN_RELASI) {
            return $this->selaraskan($cycleId, (int) $peserta->Id_PA_Participant, $rencana, $alasan, $olehUserId, $jadwalkan);
        }

        if ($tindakan === self::LEPAS_PENILAI) {
            return ['tindakan' => $tindakan, 'participant_id' => (int) ($peserta->Id_PA_Participant ?? 0)]
                + $this->lepasSebagaiPenilai($cycleId, $kodePerusahaan, $kodeKaryawan, $alasan, $olehUserId, $jadwalkan);
        }

        if ($tindakan === self::KECUALIKAN) {
            $hasil = $this->penyiapan->tambahPraKecuali(
                $cycleId,
                [['kode_perusahaan' => $kodePerusahaan, 'kode_karyawan' => $kodeKaryawan]],
                $alasan,
                $olehUserId,
            );

            $catatan = ($hasil['peserta_dikecualikan'] ?? 0) > 0
                ? 'Peserta dikecualikan; jadwal HC Learn penugasan yang dibatalkan dihapus di latar.'
                    .($dampak['penugasan_diisi'] > 0 ? " {$dampak['penugasan_diisi']} penugasan yang sudah diisi tetap tercatat." : '')
                : 'Ditambahkan ke daftar pengecualian.';

            /* Dikecualikan sebagai PESERTA tidak otomatis berhenti menilai: orang yang levelnya
               tidak dinilai tetap menilai bawahannya. Admin yang memutuskan, lewat opsi ini. */
            $lepas = ! empty($opsi['lepas_penilai']) && $dampak['menilai'] > 0
                ? $this->lepasSebagaiPenilai($cycleId, $kodePerusahaan, $kodeKaryawan, $alasan, $olehUserId, $jadwalkan)
                : null;

            return [
                'tindakan' => $tindakan,
                'participant_id' => (int) $peserta->Id_PA_Participant,
                'dibatalkan' => $dampak['penugasan_aktif'] - $dampak['penugasan_diisi'] + ($lepas['dibatalkan'] ?? 0),
                'dibentuk' => $lepas['dibentuk'] ?? 0,
                'penjadwalan' => $lepas['penjadwalan'] ?? null,
                'tanpa_pengganti' => $lepas['tanpa_pengganti'] ?? [],
                'catatan' => $catatan.($lepas ? ' '.$lepas['catatan'] : ''),
            ];
        }

        $snapshot = $this->salinan($cycleId)[(int) $k->ID_Level];
        $sekarang = FormatTanggalHelper::getCurrentTime();
        $catatan = self::AWALAN_ALASAN.$this->judulTindakan($tindakan).'. '.$alasan;
        $dibatalkan = 0;

        $pid = DB::transaction(function () use ($tindakan, $cycleId, $peserta, $k, $snapshot, $sekarang, $catatan, $olehUserId, &$dibatalkan) {
            if ($tindakan === self::TAMBAH_SUSULAN) {
                DB::table('N_PA_Participant')->insert($this->penyiapan->barisPesertaSusulan($cycleId, $k, $snapshot) + [
                    'Updated_At' => $sekarang,
                    'Updated_By' => $olehUserId,
                ]);
                $pid = (int) DB::table('N_PA_Participant')
                    ->where('PA_Cycle_Id', $cycleId)
                    ->where('Kode_Perusahaan', $k->Kode_Perusahaan)
                    ->where('Kode_Karyawan', $k->Kode_Karyawan)
                    ->where('Flag_Aktif', 'Y')
                    ->value('Id_PA_Participant');
            } else {
                $pid = (int) $peserta->Id_PA_Participant;

                /* Yang BELUM diisi dibatalkan — jadwal HC Learn-nya ikut dihapus di antrean
                   sesudah commit. Yang sudah diisi dibiarkan (ikutkan kembali) atau sudah
                   ditolak di muka (terapkan level). */
                foreach ($this->penugasanBelumDiisi($pid) as $aid) {
                    $this->penugasan->batalkan($aid, $catatan, $olehUserId);
                    $dibatalkan++;
                }

                DB::table('N_PA_Participant')->where('Id_PA_Participant', $pid)->update([
                    'ID_Level_Saat_Cycle' => $k->ID_Level,
                    'Nama_Level_Saat_Cycle' => $k->nama_level,
                    'ID_Level_HRIS_Saat_Cycle' => $k->ID_Level_HRIS,
                    'Nama_Level_HRIS_Saat_Cycle' => $k->nama_level_hris,
                    'PA_Karyawan_Level_PA_Id' => $k->Koreksi_Level_PA_Id,
                    'PA_Cycle_Rater_Policy_Snapshot_Id' => $snapshot,
                    'Status_Peserta' => 'DIIKUTKAN',
                    'Alasan_Dikecualikan' => null,
                    'Updated_At' => $sekarang,
                    'Updated_By' => $olehUserId,
                ]);
            }

            /* Daftar pra-kecuali orang ini ikut dicabut: admin baru saja memutuskan ia DINILAI,
               dan baris yang tetap berkata "dikecualikan" akan membantahnya di Langkah 4. */
            DB::table('N_PA_Cycle_Pre_Exclusion')
                ->where('PA_Cycle_Id', $cycleId)
                ->where('Kode_Perusahaan', $k->Kode_Perusahaan)
                ->where('Kode_Karyawan', $k->Kode_Karyawan)
                ->where('Flag_Aktif', 'Y')
                ->update(['Flag_Aktif' => 'T', 'Updated_At' => $sekarang, 'Updated_By' => $olehUserId]);

            return $pid;
        });

        $this->penugasan->lupakanMemo();

        /* SESUDAH COMMIT: pembentukan dan penjadwalan masing-masing bisa gagal tanpa membatalkan
           revisinya — pesertanya sudah benar, dan yang kurang terlihat di Langkah 5. */
        $dibentuk = 0;
        $galat = [];
        try {
            $dibentuk = $this->penugasan->bentukUntukPeserta($pid, $olehUserId)['dibuat'];
        } catch (Throwable $e) {
            $galat[] = 'Pembentukan penugasan: '.$e->getMessage();
        }

        $tugas = null;
        if ($dibentuk > 0 && $jadwalkan) {
            try {
                $tugas = $this->jadwalkan($cycleId, $olehUserId);
            } catch (Throwable $e) {
                $galat[] = 'Penjadwalan HC Learn: '.$e->getMessage();
            }
        }

        Log::info('PA: revisi peserta sesudah dikunci', [
            'cycle_id' => $cycleId, 'participant_id' => $pid, 'tindakan' => $tindakan,
            'dibatalkan' => $dibatalkan, 'dibentuk' => $dibentuk, 'oleh' => $olehUserId,
        ]);

        return [
            'tindakan' => $tindakan,
            'participant_id' => $pid,
            'dibatalkan' => $dibatalkan,
            'dibentuk' => $dibentuk,
            'penjadwalan' => $tugas,
            'catatan' => $galat
                ? implode(' ', $galat)
                : ($dibentuk === 0
                    ? 'Tidak ada penilai yang bisa dibentuk dari relasi organisasi — tambahkan lewat Kelola Rater.'
                    : 'Penugasan dibentuk; penjadwalan ke HC Learn berjalan di latar (Tugas & Aktivitas).'),
        ];
    }

    /* ------------------------------------------------------------------ *
     * Banyak orang sekaligus                                              *
     * ------------------------------------------------------------------ */

    public const MAKS_ORANG = 1000;

    /**
     * Mencari orang untuk direvisi, berfilter — dasar "centang semua yang lolos filter".
     *
     *   peserta   peserta siklus ini (terapkan level, ikutkan kembali, kecualikan)
     *   karyawan  karyawan aktif yang BELUM peserta (peserta susulan)
     *   penilai   siapa pun yang masih punya tugas menilai yang belum diisi — termasuk yang bukan
     *             peserta dan yang sudah tidak aktif di HRIS (status TIDAK_AKTIF)
     *
     * @param  array{q?:string, status?:string, id_level?:mixed, divisi?:mixed, departemen?:mixed, cabang?:mixed}  $saringan
     * @return list<array<string, mixed>>
     */
    public function cari(int $cycleId, string $sumber, array $saringan, int $batas = self::MAKS_ORANG): array
    {
        $daftar = fn ($v) => array_values(array_filter(array_map('trim', (array) ($v ?? [])), fn ($x) => $x !== ''));
        $batas = max(1, min($batas, self::MAKS_ORANG));

        if ($sumber === 'penilai') {
            $q = mb_strtolower(trim((string) ($saringan['q'] ?? '')));
            $hanyaTidakAktif = ($saringan['status'] ?? '') === 'TIDAK_AKTIF';
            $hasil = [];
            foreach ($this->petaMenilai($cycleId, []) as $kunci => $m) {
                [$kp, $kk] = explode('|', $kunci, 2);
                $aktif = $this->karyawan($cycleId, $kp, $kk) !== null;
                if (($hanyaTidakAktif && $aktif)
                    || ($q !== '' && ! str_contains(mb_strtolower($m['nama']), $q) && ! str_contains(mb_strtolower($kk), $q))) {
                    continue;
                }
                $hasil[] = ['kode_perusahaan' => $kp, 'kode_karyawan' => $kk, 'nama' => $m['nama'],
                    'status' => $aktif ? 'AKTIF' : 'TIDAK_AKTIF', 'menilai' => $m['jumlah']];
            }
            usort($hasil, fn ($a, $b) => strcmp($a['nama'], $b['nama']));

            return array_slice($hasil, 0, $batas);
        }

        if ($sumber === 'karyawan') {
            $sudah = DB::table('N_PA_Participant')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')
                ->get(['Kode_Perusahaan', 'Kode_Karyawan'])
                ->mapWithKeys(fn ($r) => [$r->Kode_Perusahaan.'|'.trim((string) $r->Kode_Karyawan) => true])->all();

            return array_values(array_slice(array_filter(
                $this->langkahSetup->cariKaryawan($cycleId, $saringan, 2000),
                fn ($r) => ! isset($sudah[$r['kode_perusahaan'].'|'.trim((string) $r['kode_karyawan'])]),
            ), 0, $batas));
        }

        $q = trim((string) ($saringan['q'] ?? ''));

        return DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('Nama_Karyawan_Saat_Cycle', 'like', "%{$q}%")->orWhere('Kode_Karyawan', 'like', "%{$q}%")))
            ->when(in_array($saringan['status'] ?? '', ['DIIKUTKAN', 'DIKECUALIKAN', 'TERTUNDA'], true), fn ($w) => $w->where('Status_Peserta', $saringan['status']))
            ->when($daftar($saringan['id_level'] ?? null) !== [], fn ($w) => $w->whereIn('ID_Level_Saat_Cycle', array_map('intval', $daftar($saringan['id_level']))))
            ->when($daftar($saringan['divisi'] ?? null) !== [], fn ($w) => $w->whereIn('Nama_Divisi_Saat_Cycle', $daftar($saringan['divisi'])))
            ->when($daftar($saringan['departemen'] ?? null) !== [], fn ($w) => $w->whereIn('Nama_Sub_Divisi_Saat_Cycle', $daftar($saringan['departemen'])))
            ->orderBy('Nama_Karyawan_Saat_Cycle')
            ->limit($batas)
            ->get([
                'Kode_Perusahaan as kode_perusahaan',
                'Kode_Karyawan as kode_karyawan',
                'Nama_Karyawan_Saat_Cycle as nama',
                'Status_Peserta as status',
                'ID_Level_Saat_Cycle as id_level',
                'Nama_Level_Saat_Cycle as nama_level',
                'Nama_Divisi_Saat_Cycle as nama_divisi',
                'Nama_Sub_Divisi_Saat_Cycle as nama_departemen',
            ])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Pratinjau SATU tindakan untuk banyak orang — boleh/tidak beserta sebabnya, dan dampaknya.
     *
     * Kueri DIBUNDEL: peserta, penugasan, jadwal, dan hasil terbit dibaca sekali untuk semua
     * orang. Satu bolak-balik ke DB ini sekitar 54 ms; empat kueri per orang untuk 500 orang
     * berarti hampir dua menit menunggu sebuah pratinjau.
     *
     * @param  list<array{kode_perusahaan:string, kode_karyawan:string}>  $orang
     * @return array{baris:list<array<string, mixed>>, ringkasan:array<string, int>}
     */
    public function pratinjauBanyak(int $cycleId, string $tindakan, array $orang, bool $denganPeringatan = true): array
    {
        if (! in_array($tindakan, self::SEMUA_TINDAKAN, true)) {
            throw new RuntimeException("Tindakan {$tindakan} tidak dikenal.");
        }
        if (count($orang) > self::MAKS_ORANG) {
            throw new RuntimeException('Paling banyak '.self::MAKS_ORANG.' orang sekali revisi.');
        }

        $status = $this->gate->status($cycleId);
        $kunci = fn ($kp, $kk) => $kp.'|'.trim((string) $kk);

        $peserta = DB::table('N_PA_Participant')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')
            ->get()->keyBy(fn ($p) => $kunci($p->Kode_Perusahaan, $p->Kode_Karyawan));

        $pid = $peserta->pluck('Id_PA_Participant')->map(fn ($x) => (int) $x)->all();
        $statPenugasan = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('a.Flag_Aktif', 'Y')->where('a.Status_Penugasan', '<>', 'DIBATALKAN')
            ->groupBy('a.PA_Participant_Id')
            ->selectRaw("a.PA_Participant_Id as pid, COUNT(*) as aktif, SUM(CASE WHEN a.Status_Penugasan IN ('".implode("','", PaStatusPenugasan::SUDAH_DIKERJAKAN)."') THEN 1 ELSE 0 END) as diisi")
            ->get()->keyBy('pid');
        $terbit = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('r.Flag_Aktif', 'Y')->where('r.Status_Hasil', 'TERBIT')
            ->pluck('r.PA_Participant_Id')->mapWithKeys(fn ($x) => [(int) $x => true])->all();
        $menilai = $this->petaMenilai($cycleId, $terbit);
        $hitungRencana = $tindakan === self::SELARASKAN_RELASI && count($orang) <= self::MAKS_RENCANA_PRATINJAU;
        /* Peringatan kategori membaca usulan tiap peserta yang DITINGGAL (~0,45 detik per
           peserta di dev) — dijatah per pratinjau supaya permintaannya tidak kehabisan waktu. */
        $hitungPeringatan = $denganPeringatan && in_array($tindakan, [self::LEPAS_PENILAI, self::KECUALIKAN], true);
        $jatahPeringatan = self::MAKS_PESERTA_PERINGATAN;
        $peringatanLengkap = true;
        $kustomSiklus = $tindakan === self::TERAPKAN_LEVEL ? $this->kategoriKustom(null, $cycleId) : [];

        $baris = [];
        $ringkasan = ['total' => 0, 'bisa' => 0, 'tidak' => 0, 'penugasan_dibatalkan' => 0, 'perlu_koreksi_level' => 0, 'menilai' => 0, 'peringatan' => 0];

        foreach ($orang as $o) {
            $kp = trim((string) ($o['kode_perusahaan'] ?? ''));
            $kk = trim((string) ($o['kode_karyawan'] ?? ''));
            if ($kp === '' || $kk === '') {
                continue;
            }

            $p = $peserta[$kunci($kp, $kk)] ?? null;
            $st = $p ? ($statPenugasan[(int) $p->Id_PA_Participant] ?? null) : null;
            $dampak = [
                'penugasan_aktif' => (int) ($st->aktif ?? 0),
                'penugasan_diisi' => (int) ($st->diisi ?? 0),
                'jadwal_hclearn' => 0,
                'menilai' => $menilai[$kunci($kp, $kk)]['jumlah'] ?? 0,
            ];
            $k = $this->karyawan($cycleId, $kp, $kk);
            $rencana = $hitungRencana && $p && $p->Status_Peserta === 'DIIKUTKAN'
                ? $this->penugasan->rencanaSelaras((int) $p->Id_PA_Participant)
                : null;
            $sebab = $this->sebabTidakBoleh($tindakan, $cycleId, $status, $p, $k, $dampak,
                $p ? isset($terbit[(int) $p->Id_PA_Participant]) : false, $rencana);

            $peringatan = [];
            if ($sebab === null && $hitungPeringatan && $dampak['menilai'] > 0) {
                if ($dampak['menilai'] <= $jatahPeringatan) {
                    $jatahPeringatan -= $dampak['menilai'];
                    $peringatan = $this->peringatanKategori($this->sebagaiPenilai($cycleId, $kp, $kk));
                } else {
                    $peringatanLengkap = false;
                }
            } elseif ($sebab === null && $rencana !== null) {
                $peringatan = $this->peringatanSelaras($p->Nama_Karyawan_Saat_Cycle, $rencana, (int) $p->Id_PA_Participant);
            } elseif ($sebab === null && $tindakan === self::TERAPKAN_LEVEL && $p !== null) {
                /* Penugasannya dibentuk ULANG dari nol — bobot khusus tidak ikut pindah. */
                foreach ($kustomSiklus[(int) $p->Id_PA_Participant] ?? [] as $nama) {
                    $peringatan[] = self::kalimatKustom($p->Nama_Karyawan_Saat_Cycle, $nama, true);
                }
            }
            $ringkasan['peringatan'] += count($peringatan);

            $ringkasan['total']++;
            $sebab === null ? $ringkasan['bisa']++ : $ringkasan['tidak']++;
            if ($sebab === null) {
                $ringkasan['penugasan_dibatalkan'] += match ($tindakan) {
                    self::TAMBAH_SUSULAN => 0,
                    self::LEPAS_PENILAI => $dampak['menilai'],
                    self::SELARASKAN_RELASI => count($rencana['keluar'] ?? []),
                    default => $dampak['penugasan_aktif'] - $dampak['penugasan_diisi'],
                };
                $ringkasan['menilai'] += $dampak['menilai'];
            }

            $baris[] = [
                'kode_perusahaan' => $kp,
                'kode_karyawan' => $kk,
                'nama' => $p->Nama_Karyawan_Saat_Cycle ?? $k->Nama ?? $menilai[$kunci($kp, $kk)]['nama'] ?? $kk,
                'rencana' => $rencana === null ? null : [
                    'acuan' => $rencana['acuan'],
                    'keluar' => array_map(fn ($a) => $a['nama'].' ('.$a['kategori'].')', $rencana['keluar']),
                    'masuk' => array_map(fn ($a) => $a['nama'].' ('.$a['kategori'].')', $rencana['masuk']),
                ],
                'level_siklus' => $p->Nama_Level_Saat_Cycle ?? null,
                'level_terkini' => $k->nama_level ?? null,
                'boleh' => $sebab === null,
                'sebab' => $sebab,
                'perlu_koreksi_level' => $sebab !== null && $this->perluKoreksiLevel,
                'dampak' => $dampak,
                'peringatan' => $peringatan,
            ];
            if ($sebab !== null && $this->perluKoreksiLevel) {
                $ringkasan['perlu_koreksi_level']++;
            }
        }

        return ['baris' => $baris, 'ringkasan' => $ringkasan, 'alamat_koreksi_level' => self::ALAMAT_KOREKSI_LEVEL,
            'jendela' => $this->keadaanJendela($cycleId),
            'rencana_dihitung' => $tindakan !== self::SELARASKAN_RELASI || $hitungRencana,
            'peringatan_lengkap' => $peringatanLengkap];
    }

    /**
     * Siapa saja yang masih punya tugas MENILAI yang belum diisi di siklus ini, sekali baca —
     * tanpa penilaian diri dan tanpa peserta yang hasilnya sudah TERBIT.
     *
     * @param  array<int, true>  $terbit
     * @return array<string, array{jumlah:int, nama:string}>
     */
    private function petaMenilai(int $cycleId, array $terbit): array
    {
        $peta = [];
        DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('p.Flag_Aktif', 'Y')
            ->where('a.Flag_Aktif', 'Y')->where('a.Status_Penugasan', '<>', 'DIBATALKAN')
            ->whereNotIn('a.Status_Penugasan', PaStatusPenugasan::SUDAH_DIKERJAKAN)
            ->whereRaw('NOT (a.Kode_Perusahaan_Penilai = p.Kode_Perusahaan AND a.Kode_Karyawan_Penilai = p.Kode_Karyawan)')
            ->get(['a.Kode_Perusahaan_Penilai as kp', 'a.Kode_Karyawan_Penilai as kk', 'a.Nama_Penilai_Saat_Cycle as nama', 'a.PA_Participant_Id as pid'])
            ->each(function ($r) use (&$peta, $terbit) {
                if (isset($terbit[(int) $r->pid])) {
                    return;
                }
                $kunci = $r->kp.'|'.trim((string) $r->kk);
                $peta[$kunci] ??= ['jumlah' => 0, 'nama' => (string) $r->nama];
                $peta[$kunci]['jumlah']++;
            });

        return $peta;
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    /** Satu kalimat sebab bila tindakan TIDAK boleh; null bila boleh. */
    /** Alamat halaman Koreksi Level PA — dirujuk ketika level orangnya tidak dinilai. */
    public const ALAMAT_KOREKSI_LEVEL = '/performance-appraisal/master/level-pa';

    /**
     * Ditandai `sebabTidakBoleh()` bila penolakannya karena LEVEL PA orang itu tidak dinilai di
     * siklus ini — satu-satunya jalan keluarnya mengoreksi Level PA, bukan revisi. Layar
     * menawarkan tombol ke halaman Koreksi Level untuk kasus ini saja.
     */
    private bool $perluKoreksiLevel = false;

    /**
     * Kalimat untuk orang yang Level PA-nya tidak dinilai (dikecualikan, tanpa aturan, atau
     * tanpa paket soal) — selalu menyebut jalan keluarnya: koreksi dulu, lalu ulangi revisi.
     */
    private function sebabLevel(object $k, string $kenapa, bool $terkini = false): string
    {
        $this->perluKoreksiLevel = true;

        return sprintf(
            '%s %s %s. Bila orang ini memang harus dinilai di siklus ini, koreksi Level PA-nya dulu di halaman Koreksi Level, lalu ulangi revisi.',
            $terkini ? 'Level PA terbarunya' : 'Level PA-nya',
            $k->nama_level ?? '(belum ada)',
            $kenapa,
        );
    }

    /**
     * @param  array{keluar:list<array<string, mixed>>, masuk:list<array<string, mixed>>, acuan:string}|null  $rencana
     *                                                                                                                  rencana selaras orang ini; null = tidak dihitung (pratinjau banyak orang yang besar)
     * @param  bool  $rencanaKosongBoleh  jalan banyak orang: yang sudah sesuai dilewati, bukan ditolak
     */
    private function sebabTidakBoleh(string $tindakan, int $cycleId, string $status, ?object $peserta, ?object $k, array $dampak, ?bool $terbit = null, ?array $rencana = null, bool $rencanaKosongBoleh = false): ?string
    {
        $this->perluKoreksiLevel = false;

        if (! in_array($tindakan, self::SEMUA_TINDAKAN, true)) {
            return "Tindakan {$tindakan} tidak dikenal.";
        }

        if (! in_array($status, ['LOCKED', 'OPEN'], true)) {
            return "Revisi peserta untuk siklus yang sudah dikunci (LOCKED/OPEN); siklus ini {$status}. "
                .'Sebelum dikunci, pakai Langkah 4 (Jalankan Ulang, pengecualian) dan Langkah 5.';
        }

        /* Lepas penilai: siapa pun yang masih punya tugas menilai yang belum diisi — peserta
           atau bukan, aktif atau sudah resign. */
        if ($tindakan === self::LEPAS_PENILAI) {
            return ($dampak['menilai'] ?? 0) > 0
                ? null
                : 'Orang ini tidak sedang menilai siapa pun (tugas menilainya sudah diisi semua, atau memang tidak ada).';
        }

        if ($tindakan === self::SELARASKAN_RELASI) {
            if ($peserta === null) {
                return 'Karyawan ini belum menjadi peserta siklus ini.';
            }
            if ($peserta->Status_Peserta !== 'DIIKUTKAN') {
                return 'Hanya untuk peserta yang diikutkan.';
            }
            if ($terbit ?? $this->penugasan->hasilSudahTerbit((int) $peserta->Id_PA_Participant)) {
                return 'Hasil peserta ini sudah TERBIT — batalkan penerbitannya lebih dulu.';
            }
            if ($rencana !== null && ! $rencanaKosongBoleh && $rencana['keluar'] === [] && $rencana['masuk'] === []) {
                return 'Penilainya sudah sesuai relasi organisasi per '.$rencana['acuan'].' — tidak ada yang diganti.';
            }

            return null;
        }

        /* Kecualikan boleh untuk karyawan yang sudah TIDAK aktif — justru kasus paling umum
           (resign sesudah dikunci). Pemeriksaan level/paket tidak relevan untuknya. */
        if ($tindakan === self::KECUALIKAN) {
            if ($peserta === null) {
                return 'Karyawan ini belum menjadi peserta siklus ini.';
            }
            if ($peserta->Status_Peserta !== 'DIIKUTKAN') {
                return 'Peserta ini sudah dikecualikan.';
            }

            return ($terbit ?? $this->penugasan->hasilSudahTerbit((int) $peserta->Id_PA_Participant))
                ? 'Hasil peserta ini sudah TERBIT — batalkan penerbitannya lebih dulu.'
                : null;
        }

        if ($k === null) {
            return 'Karyawan ini tidak aktif di HRIS, jadi tidak bisa dinilai.';
        }

        if ($tindakan === self::TAMBAH_SUSULAN) {
            if ($peserta !== null) {
                return 'Karyawan ini sudah menjadi peserta ('.$peserta->Status_Peserta.').';
            }

            if (($sebab = $this->sebabTidakLayak($cycleId, $k)) !== null) {
                return $sebab;
            }
        } elseif ($peserta === null) {
            return 'Karyawan ini belum menjadi peserta siklus ini — pakai Tambah peserta susulan.';
        } elseif ($tindakan === self::IKUTKAN_KEMBALI) {
            if ($peserta->Status_Peserta !== 'DIKECUALIKAN') {
                return 'Peserta ini tidak sedang dikecualikan.';
            }
            /* Kelayakannya diuji sama dengan susulan: orang yang dikecualikan karena levelnya
               tidak dinilai atau karena resign tidak "diikutkan kembali" begitu saja. */
            if (($sebab = $this->sebabTidakLayak($cycleId, $k)) !== null) {
                return $sebab;
            }
        } elseif ($tindakan === self::TERAPKAN_LEVEL) {
            if ($peserta->Status_Peserta !== 'DIIKUTKAN') {
                return 'Hanya untuk peserta yang diikutkan.';
            }
            if (! $this->penyiapan->levelBerbeda($peserta, $k)) {
                return 'Level PA-nya sudah sesuai ('.($peserta->Nama_Level_Saat_Cycle ?? '-').') — tidak ada yang diterapkan.';
            }
            if ($dampak['penugasan_diisi'] > 0) {
                return "{$dampak['penugasan_diisi']} penilai sudah mengisi dengan soal level lama — level tidak bisa diganti tanpa membuang jawaban mereka.";
            }
        }

        if ($terbit ?? $this->penugasan->hasilSudahTerbit((int) ($peserta->Id_PA_Participant ?? 0))) {
            return 'Hasil peserta ini sudah TERBIT — batalkan penerbitannya lebih dulu.';
        }

        $terkini = $tindakan === self::TERAPKAN_LEVEL;

        if ($k->ID_Level === null) {
            return $this->sebabLevel($k, 'belum terpetakan', $terkini);
        }

        if (PaLevelDikecualikan::apakah($k->ID_Level)) {
            return $this->sebabLevel($k, 'termasuk level yang tidak dinilai', $terkini);
        }

        if (! isset($this->salinan($cycleId)[(int) $k->ID_Level])) {
            return $this->sebabLevel($k, 'tidak punya aturan penilai di siklus ini (aturan dibekukan saat dikunci)', $terkini);
        }

        if (! $this->adaPaket($cycleId, (int) $k->ID_Level)) {
            return $this->sebabLevel($k, 'tidak punya paket soal di siklus ini, jadi penilainya tidak bisa dijadwalkan ke HC Learn', $terkini);
        }

        return null;
    }

    /**
     * Kelayakan peserta (aturan yang sama dengan penyiapan) — sebab LEVEL dipisah dari sebab
     * lain (resign) karena jalan keluarnya berbeda.
     */
    private function sebabTidakLayak(int $cycleId, object $k): ?string
    {
        [$layak, $alasan] = $this->penyiapan->kelayakanKaryawan($cycleId, $k, $this->salinan($cycleId));

        if ($layak === 'DIIKUTKAN') {
            return null;
        }

        if ($k->ID_Level === null) {
            return $this->sebabLevel($k, 'belum terpetakan');
        }
        if (PaLevelDikecualikan::apakah($k->ID_Level)) {
            return $this->sebabLevel($k, 'termasuk level yang tidak dinilai');
        }
        if ($layak === 'TERTUNDA') {
            return $this->sebabLevel($k, 'tidak punya aturan penilai di siklus ini (aturan dibekukan saat dikunci)');
        }

        return 'Tidak memenuhi syarat peserta: '.($alasan ?: $layak).'.';
    }

    private function peserta(int $cycleId, string $kodePerusahaan, string $kodeKaryawan): ?object
    {
        return DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Kode_Perusahaan', $kodePerusahaan)
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Flag_Aktif', 'Y')
            ->first();
    }

    /** @return array{penugasan_aktif:int, penugasan_diisi:int, jadwal_hclearn:int} */
    private function dampak(int $pid): array
    {
        $aktif = DB::table('N_PA_Assignment')->where('PA_Participant_Id', $pid)->where('Flag_Aktif', 'Y')
            ->where('Status_Penugasan', '<>', 'DIBATALKAN')->pluck('Status_Penugasan');

        return [
            'penugasan_aktif' => $aktif->count(),
            'penugasan_diisi' => $aktif->filter(fn ($s) => in_array($s, PaStatusPenugasan::SUDAH_DIKERJAKAN, true))->count(),
            'jadwal_hclearn' => DB::table('N_PA_Assignment_Integration as i')
                ->join('N_PA_Assignment as a', 'a.Id_PA_Assignment', '=', 'i.PA_Assignment_Id')
                ->where('a.PA_Participant_Id', $pid)->where('a.Flag_Aktif', 'Y')->where('i.Flag_Aktif', 'Y')
                ->whereNotIn('a.Status_Penugasan', PaStatusPenugasan::SUDAH_DIKERJAKAN)
                ->count(),
        ];
    }

    /** @return list<int> */
    private function penugasanBelumDiisi(int $pid): array
    {
        return DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $pid)
            ->where('Flag_Aktif', 'Y')
            ->where('Status_Penugasan', '<>', 'DIBATALKAN')
            ->whereNotIn('Status_Penugasan', PaStatusPenugasan::SUDAH_DIKERJAKAN)
            ->pluck('Id_PA_Assignment')
            ->map(fn ($x) => (int) $x)
            ->all();
    }

    /**
     * Penilai yang BELUM punya jadwal HC Learn di siklus ini — termasuk yang baru dibentuk
     * revisi — dipastikan dikenal HC Learn sebelum dijadwalkan. Gagal memeriksa tidak
     * menggagalkan revisinya: pesertanya sudah benar, dan penjadwalan tetap mencoba.
     *
     * @return array{diperiksa:int, didaftarkan:int, tidak_siap:list<array<string, string>>, galat?:string}
     */
    public function pastikanPenilaiBaruTerdaftar(int $cycleId): array
    {
        $penilai = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('p.Flag_Aktif', 'Y')
            ->where('a.Flag_Aktif', 'Y')->where('a.Status_Penugasan', '<>', 'DIBATALKAN')
            ->whereNotIn('a.Status_Penugasan', PaStatusPenugasan::SUDAH_DIKERJAKAN)
            ->whereNotExists(fn ($q) => $q->from('N_PA_Assignment_Integration as i')
                ->whereColumn('i.PA_Assignment_Id', 'a.Id_PA_Assignment')->where('i.Flag_Aktif', 'Y'))
            ->distinct()
            ->get(['a.Kode_Perusahaan_Penilai as kp', 'a.Kode_Karyawan_Penilai as kk', 'a.Nama_Penilai_Saat_Cycle as nama']);

        try {
            $hasil = $this->karyawanHcLearn->pastikanTerdaftar($penilai->map(fn ($r) => [$r->kp, $r->kk])->all());
        } catch (Throwable $e) {
            Log::warning('PA: pemeriksaan HC Learn penilai baru gagal', ['cycle_id' => $cycleId, 'galat' => $e->getMessage()]);

            return ['diperiksa' => 0, 'didaftarkan' => 0, 'tidak_siap' => [], 'galat' => $e->getMessage()];
        }

        $nama = $penilai->mapWithKeys(fn ($r) => [trim((string) $r->kk) => $r->nama])->all();
        foreach ($hasil['tidak_siap'] as $i => $t) {
            $hasil['tidak_siap'][$i]['nama'] = (string) ($nama[$t['kode_karyawan']] ?? $t['kode_karyawan']);
        }

        return $hasil;
    }

    /** Kalimat untuk penilai baru yang ditolak HC Learn; kosong bila tidak ada. */
    public static function kalimatTidakSiap(array $hclearn): string
    {
        $tidak = $hclearn['tidak_siap'] ?? [];
        if ($tidak === []) {
            return '';
        }
        $nama = array_map(fn ($t) => "{$t['nama']} ({$t['keterangan']})", array_slice($tidak, 0, 3));

        return count($tidak).' penilai baru ditolak HC Learn dan tidak akan bisa mengisi: '.implode('; ', $nama)
            .(count($tidak) > 3 ? '; …' : '').'. Ganti lewat Kelola penilai atau Lepas sebagai penilai.';
    }

    /**
     * Penilai yang DITAMBAH lewat Kelola penilai sesudah siklus dikunci langsung dijadwalkan ke
     * HC Learn — sama dengan jalur revisi, termasuk pemeriksaan pendaftarannya. Tanpa ini
     * penilai tambahan tidak punya akses sampai admin ingat menekan "Jadwalkan sisanya".
     *
     * @return array{penjadwalan:?string, hclearn:?array, catatan:string}|null null bila siklus belum dikunci
     */
    public function jadwalkanSesudahKelola(int $participantId, ?int $olehUserId): ?array
    {
        $cycleId = (int) DB::table('N_PA_Participant')->where('Id_PA_Participant', $participantId)->value('PA_Cycle_Id');
        if ($cycleId === 0 || ! in_array($this->gate->status($cycleId), ['LOCKED', 'OPEN'], true)) {
            return null;
        }

        $this->hclearnTerakhir = null;
        try {
            $id = $this->jadwalkan($cycleId, $olehUserId);
        } catch (Throwable $e) {
            return ['penjadwalan' => null, 'hclearn' => $this->hclearnTerakhir,
                'catatan' => 'Penjadwalan ke HC Learn gagal dimulai: '.$e->getMessage().' Jalankan lewat "Jadwalkan sisanya".'];
        }

        return ['penjadwalan' => $id, 'hclearn' => $this->hclearnTerakhir,
            'catatan' => trim('Penilai baru dijadwalkan ke HC Learn di latar (Tugas & Aktivitas). '.self::kalimatTidakSiap($this->hclearnTerakhir ?? []))];
    }

    /** Hasil pemeriksaan HC Learn terakhir dari `jadwalkan()` — dibawa ke hasil revisi. */
    private ?array $hclearnTerakhir = null;

    /** Memulai (atau memakai) tugas penjadwalan latar siklus ini — hanya yang belum berpintu dikirim. */
    private function jadwalkan(int $cycleId, ?int $olehUserId): string
    {
        $this->hclearnTerakhir = $this->pastikanPenilaiBaruTerdaftar($cycleId);

        $mulai = $this->tugasJadwal->mulai(
            $cycleId,
            $this->jadwal->ringkasan($cycleId),
            $olehUserId,
            optional(optional(Auth::user())->karyawan)->Kode_Karyawan,
        );

        if ($mulai['baru']) {
            PenjadwalanHcLearnPaJob::dispatch($mulai['id']);
        }

        return $mulai['id'];
    }

    /**
     * Tugas MENILAI seseorang yang belum diisi di siklus ini — tanpa penilaian dirinya sendiri,
     * dan tanpa peserta yang hasilnya sudah TERBIT (penilainya tidak boleh berubah lagi).
     *
     * @return list<array{assignment_id:int, participant_id:int, peserta:string, kategori_id:int}>
     */
    private function sebagaiPenilai(int $cycleId, string $kodePerusahaan, string $kodeKaryawan): array
    {
        $daftar = $this->penugasan->penugasanSebagaiPenilai($cycleId, $kodePerusahaan, $kodeKaryawan);

        return array_values(array_filter($daftar, fn ($a) => ! $this->penugasan->hasilSudahTerbit($a['participant_id'])));
    }

    /**
     * Sisa jendela pengisian bagi PENILAI BARU hasil revisi.
     *
     * Jadwal HC Learn penilai baru memakai jendela siklus yang SAMA (`jendelaSah`): revisi dua
     * hari sebelum tutup memberi penilai baru dua hari, dan revisi sesudah tutup menjadwalkan
     * akses yang sudah kedaluwarsa — penilainya tidak pernah bisa mengisi. Disebut sebelum
     * admin menjalankan, dengan jalan keluarnya: perpanjang jendela.
     *
     * @return array{status:string, sampai:?string, sisa_jam:?int, pesan:?string}
     */
    public function keadaanJendela(int $cycleId): array
    {
        $sampai = $this->jadwal->jendelaSiklus($cycleId)['sampai'];
        if ($sampai === null) {
            return ['status' => 'KOSONG', 'sampai' => null, 'sisa_jam' => null,
                'pesan' => 'Siklus ini belum punya jendela pengisian — penilai baru belum bisa dijadwalkan ke HC Learn sampai jendelanya diisi.'];
        }

        $sisaJam = (int) floor((strtotime($sampai) - strtotime(FormatTanggalHelper::getCurrentTime())) / 3600);
        $tanggal = date('d-m-Y H:i', strtotime($sampai));

        if ($sisaJam < 0) {
            return ['status' => 'LEWAT', 'sampai' => $sampai, 'sisa_jam' => $sisaJam,
                'pesan' => "Jendela pengisian sudah berakhir {$tanggal}. Penilai baru hasil revisi tidak akan bisa mengisi — perpanjang jendela pengisian dulu, lalu jadwalkan ulang."];
        }

        if ($sisaJam < self::JENDELA_HAMPIR_JAM) {
            $sisa = $sisaJam < 24 ? "{$sisaJam} jam" : intdiv($sisaJam, 24).' hari';

            return ['status' => 'HAMPIR', 'sampai' => $sampai, 'sisa_jam' => $sisaJam,
                'pesan' => "Pengisian berakhir {$tanggal} — penilai baru hasil revisi hanya punya sekitar {$sisa}. Pertimbangkan memperpanjang jendela pengisian."];
        }

        return ['status' => 'AMAN', 'sampai' => $sampai, 'sisa_jam' => $sisaJam, 'pesan' => null];
    }

    /**
     * Kategori yang TIDAK AKAN DIHITUNG di hasil sesudah tugas-tugas menilai ini dibatalkan.
     *
     * Kategori hanya dipakai bila pengisinya mencapai `min_rater_selesai`; di bawahnya
     * jawaban yang ada pun dibuang dan bobotnya dialihkan ke kategori lain. Admin harus tahu
     * itu SEBELUM melepas — "Bawahan perlu 2, tersisa 1" berarti jawaban satu-satunya bawahan
     * tidak dipakai. Pengganti dari relasi ikut diperhitungkan, sama seperti pembentukannya:
     * hanya sampai `min_rater`, tanpa calon yang pernah dicabut.
     *
     * @param  list<array{assignment_id:int, participant_id:int, peserta:string, kategori_id:int}>  $tugas
     * @return list<string>
     */
    private function peringatanKategori(array $tugas): array
    {
        $per = [];
        foreach ($tugas as $t) {
            $per[$t['participant_id']]['peserta'] = $t['peserta'];
            $per[$t['participant_id']]['hapus'][$t['kategori_id']] = ($per[$t['participant_id']]['hapus'][$t['kategori_id']] ?? 0) + 1;
        }

        $pesan = [];
        $kustom = $this->kategoriKustom(array_map('intval', array_keys($per)));
        foreach ($per as $pid => $p) {
            foreach ($this->penugasan->usulkan((int) $pid) as $d) {
                $hapus = $p['hapus'][$d['kategori_id']] ?? 0;
                if ($hapus === 0) {
                    continue;
                }
                $pesan = array_merge($pesan, $this->kalimatKategori($p['peserta'], $d, (int) $d['terpasang'] - $hapus, $this->tersedia($d)));
                if (isset($kustom[(int) $pid][(int) $d['kategori_id']])) {
                    $pesan[] = self::kalimatKustom($p['peserta'], $d['nama_kategori']);
                }
            }
        }

        return $pesan;
    }

    /**
     * @param  array{kategori?:list<array<string, mixed>>}  $rencana
     * @return list<string>
     */
    private function peringatanSelaras(string $peserta, array $rencana, ?int $pid = null): array
    {
        $pesan = [];
        $kustom = $pid === null ? [] : ($this->kategoriKustom([$pid])[$pid] ?? []);
        foreach ($rencana['kategori'] ?? [] as $k) {
            $pesan = array_merge($pesan, $this->kalimatKategori($peserta, $k, (int) $k['sisa'], (int) $k['masuk']));
            if (isset($kustom[(int) ($k['kategori_id'] ?? 0)])) {
                $pesan[] = self::kalimatKustom($peserta, $k['nama_kategori']);
            }
        }

        return $pesan;
    }

    /** @return list<string> kosong bila kategorinya tetap dihitung */
    private function kalimatKategori(string $peserta, array $d, int $sisa, int $tersedia): array
    {
        $min = (int) $d['min_rater'];
        $akan = $sisa < $min ? min($min, $sisa + $tersedia) : $sisa;
        $ambang = (int) ($d['min_rater_selesai'] ?? $min);

        return $akan < $ambang
            ? ["{$peserta}: kategori {$d['nama_kategori']} tidak akan dihitung (perlu {$ambang} pengisi, paling banyak {$akan} penilai) — bobotnya dialihkan ke kategori lain. Tambah penilai lewat Kelola penilai."]
            : [];
    }

    /**
     * Kategori berbobot KHUSUS (diatur admin) per peserta — bobot itu dibagi ulang begitu
     * susunan penilainya berubah, jadi revisi yang menyentuhnya harus menyebutnya.
     *
     * @param  list<int>|null  $pid  null = seluruh peserta siklus `$cycleId`
     * @return array<int, array<int, string>> pid => [kategori_id => nama kategori]
     */
    private function kategoriKustom(?array $pid, ?int $cycleId = null): array
    {
        if ($pid === []) {
            return [];
        }

        $peta = [];
        DB::table('N_PA_Assignment as a')
            ->join('N_PA_Rater_Category as k', 'k.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id')
            ->when($pid !== null, fn ($q) => $q->whereIn('a.PA_Participant_Id', $pid))
            ->when($pid === null, fn ($q) => $q->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
                ->where('p.PA_Cycle_Id', $cycleId)->where('p.Flag_Aktif', 'Y'))
            ->where('a.Flag_Aktif', 'Y')->where('a.Bobot_Kustom', 'Y')
            ->distinct()
            ->get(['a.PA_Participant_Id as pid', 'a.PA_Rater_Category_Id as kat', 'k.Nama_Kategori as nama'])
            ->each(function ($r) use (&$peta) {
                $peta[(int) $r->pid][(int) $r->kat] = (string) $r->nama;
            });

        return $peta;
    }

    private static function kalimatKustom(string $peserta, string $kategori, bool $hilang = false): string
    {
        return $hilang
            ? "{$peserta}: bobot khusus penilai kategori {$kategori} hilang — penugasannya dibentuk ulang dengan bobot rata. Atur lagi lewat Kelola penilai bila perlu."
            : "{$peserta}: bobot khusus penilai kategori {$kategori} dibagi ulang karena penilainya berubah. Periksa lagi lewat Kelola penilai.";
    }

    /** Calon dari relasi yang bisa dipakai pengganti. */
    private function tersedia(array $d): int
    {
        return count(array_filter($d['calon'], fn ($c) => empty($c['sudah_ditugaskan']) && empty($c['pernah_dicabut']) && empty($c['di_luar_relasi'])));
    }

    /**
     * Ganti penilai USULAN yang sudah tidak ada di relasi; isi yang kurang dari relasi terbaru.
     *
     * @param  array{keluar:list<array<string, mixed>>, masuk:list<array<string, mixed>>, acuan:string}  $rencana
     * @return array<string, mixed>
     */
    private function selaraskan(int $cycleId, int $pid, array $rencana, string $alasan, ?int $olehUserId, bool $jadwalkan): array
    {
        $dasar = ['tindakan' => self::SELARASKAN_RELASI, 'participant_id' => $pid, 'acuan' => $rencana['acuan'],
            'keluar' => array_column($rencana['keluar'], 'nama'), 'masuk' => array_column($rencana['masuk'], 'nama')];

        if ($rencana['keluar'] === [] && $rencana['masuk'] === []) {
            return $dasar + ['dibatalkan' => 0, 'dibentuk' => 0, 'penjadwalan' => null,
                'catatan' => 'Sudah sesuai relasi organisasi per '.$rencana['acuan'].' — tidak ada yang diganti.'];
        }

        $catatan = self::AWALAN_ALASAN.'Relasi organisasi berubah (acuan '.$rencana['acuan'].'). '.$alasan;
        $dibatalkan = 0;
        DB::transaction(function () use ($rencana, $catatan, $olehUserId, &$dibatalkan) {
            foreach ($rencana['keluar'] as $a) {
                $this->penugasan->batalkan($a['assignment_id'], $catatan, $olehUserId);
                $dibatalkan++;
            }
        });
        $this->penugasan->lupakanMemo();

        [$dibentuk, $galat] = $this->bentukPengganti([$pid], $olehUserId);
        $tugas = $this->jadwalkanBila($dibentuk, $jadwalkan, $cycleId, $olehUserId, $galat);

        Log::info('PA: revisi selaraskan relasi', ['cycle_id' => $cycleId, 'participant_id' => $pid,
            'dibatalkan' => $dibatalkan, 'dibentuk' => $dibentuk, 'oleh' => $olehUserId]);

        return $dasar + [
            'dibatalkan' => $dibatalkan,
            'dibentuk' => $dibentuk,
            'penjadwalan' => $tugas,
            'catatan' => $galat ? implode(' ', $galat) : "{$dibatalkan} penilai lama dibatalkan, {$dibentuk} penilai dari relasi terbaru dibentuk.",
        ];
    }

    /**
     * Membatalkan tugas MENILAI seseorang yang belum diisi, lalu membentuk pengganti dari relasi
     * untuk tiap peserta yang ditinggal. Peserta yang tetap kurang penilai disebut namanya —
     * merekalah yang harus diisi tangan lewat Kelola Rater.
     *
     * @return array{dibatalkan:int, dibentuk:int, penjadwalan:?string, tanpa_pengganti:list<string>, catatan:string}
     */
    private function lepasSebagaiPenilai(int $cycleId, string $kodePerusahaan, string $kodeKaryawan, string $alasan, ?int $olehUserId, bool $jadwalkan): array
    {
        $tugasMenilai = $this->sebagaiPenilai($cycleId, $kodePerusahaan, $kodeKaryawan);
        $catatan = self::AWALAN_PENILAI_DILEPAS.$alasan;

        DB::transaction(function () use ($tugasMenilai, $catatan, $olehUserId) {
            foreach ($tugasMenilai as $a) {
                $this->penugasan->batalkan($a['assignment_id'], $catatan, $olehUserId);
            }
        });
        $this->penugasan->lupakanMemo();

        $pid = array_values(array_unique(array_column($tugasMenilai, 'participant_id')));
        [$dibentuk, $galat] = $this->bentukPengganti($pid, $olehUserId);

        $nama = array_column($tugasMenilai, 'peserta', 'participant_id');
        $tanpa = [];
        foreach ($pid as $id) {
            if ($this->masihKurang($id)) {
                $tanpa[] = $nama[$id];
            }
        }

        $tugas = $this->jadwalkanBila($dibentuk, $jadwalkan, $cycleId, $olehUserId, $galat);

        Log::info('PA: revisi lepas penilai', ['cycle_id' => $cycleId, 'penilai' => $kodePerusahaan.'|'.$kodeKaryawan,
            'dibatalkan' => count($tugasMenilai), 'dibentuk' => $dibentuk, 'tanpa_pengganti' => count($tanpa), 'oleh' => $olehUserId]);

        return [
            'dibatalkan' => count($tugasMenilai),
            'dibentuk' => $dibentuk,
            'penjadwalan' => $tugas,
            'tanpa_pengganti' => $tanpa,
            'catatan' => $galat
                ? implode(' ', $galat)
                : count($tugasMenilai).' tugas menilai dibatalkan, '.$dibentuk.' pengganti dibentuk dari relasi.'
                    .($tanpa ? ' '.count($tanpa).' peserta masih kurang penilai — tambahkan lewat Kelola Rater: '.implode(', ', array_slice($tanpa, 0, 5)).(count($tanpa) > 5 ? ', …' : '').'.' : ''),
        ];
    }

    /**
     * @param  list<int>  $pid
     * @param  list<string>  $galat
     * @return array{0:int, 1:list<string>}
     */
    private function bentukPengganti(array $pid, ?int $olehUserId, array $galat = []): array
    {
        $dibentuk = 0;
        foreach ($pid as $id) {
            try {
                $dibentuk += $this->penugasan->bentukUntukPeserta($id, $olehUserId)['dibuat'];
            } catch (Throwable $e) {
                $galat[] = 'Pembentukan pengganti: '.$e->getMessage();
            }
        }

        return [$dibentuk, $galat];
    }

    /** Ada kategori yang terpasangnya masih di bawah minimum. */
    private function masihKurang(int $pid): bool
    {
        foreach ($this->penugasan->usulkan($pid) as $d) {
            if ((int) $d['terpasang'] < (int) $d['min_rater']) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<string>  $galat */
    private function jadwalkanBila(int $dibentuk, bool $jadwalkan, int $cycleId, ?int $olehUserId, array &$galat): ?string
    {
        if ($dibentuk === 0 || ! $jadwalkan) {
            return null;
        }
        try {
            return $this->jadwalkan($cycleId, $olehUserId);
        } catch (Throwable $e) {
            $galat[] = 'Penjadwalan HC Learn: '.$e->getMessage();

            return null;
        }
    }

    private function judulTindakan(string $t): string
    {
        return match ($t) {
            self::TERAPKAN_LEVEL => 'Level PA terbaru diterapkan',
            self::IKUTKAN_KEMBALI => 'Diikutkan kembali',
            self::TAMBAH_SUSULAN => 'Peserta susulan',
            self::KECUALIKAN => 'Dikecualikan',
            self::SELARASKAN_RELASI => 'Penilai diselaraskan dengan relasi organisasi',
            self::LEPAS_PENILAI => 'Dilepas sebagai penilai',
            default => $t,
        };
    }
}
