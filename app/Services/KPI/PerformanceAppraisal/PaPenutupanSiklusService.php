<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingPenjaga;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingStatus;
use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Alur PENUTUPAN siklus — enam tahap (pengisian s.d. terbit), hanya-baca (spec 2026-10-02;
 * tahap Kesimpulan Kualitatif ditambahkan 2026-10-07).
 *
 * Keadaan tiap tahap DITURUNKAN dari data yang sudah ada (status siklus, status hasil, kunci
 * kurva, sesi Balancing), tidak disimpan. Penghambat memakai aturan yang sama dengan gerbang
 * yang akan menolak aksinya, supaya layar tidak menjanjikan tombol yang lalu ditolak server.
 */
class PaPenutupanSiklusService
{
    public const SELESAI = 'SELESAI';

    public const BERJALAN = 'BERJALAN';

    public const MENUNGGU = 'MENUNGGU';

    public const DILEWATI = 'DILEWATI';

    private const PASCA_PENGISIAN = ['CLOSED', 'PUBLISHED'];

    public function __construct(
        private readonly PaResultPublishService $penerbitan,
        private readonly PaKurvaService $kurva,
        private readonly PaBalancingPenjaga $balancing,
        private readonly PaKesimpulanKualitatifService $kualitatif,

        /* Pemilik aturan "hanya satu siklus boleh terbuka". Dipakai supaya penghambat tahap
           Pengisian menyebut siklus penghalangnya, persis seperti penolakan server nanti. */
        private readonly PaCyclePreparationService $penyiapan,
    ) {}

    /** @return array<string, mixed> */
    public function alur(int $cycleId): array
    {
        $s = DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)
            ->first(['Id_PA_Cycle', 'Kode_Cycle', 'Nama_Cycle', 'Status_Cycle', 'Updated_At', 'Updated_By', 'Published_At', 'Published_By']);

        if ($s === null) {
            throw new RuntimeException("Siklus {$cycleId} tidak ditemukan.");
        }

        $status = (string) $s->Status_Cycle;
        $pasca = in_array($status, self::PASCA_PENGISIAN, true);
        $metode = $this->kurva->metode($cycleId);
        $terkunci = $metode === 'KURVA' && $this->kurva->terkunci($cycleId);
        $hasil = $this->penerbitan->ringkasan($cycleId);
        $dirilis = $this->penerbitan->cacahDirilisKeLeader($cycleId);
        $penugasan = $this->cacahPenugasan($cycleId);
        $sesi = DB::table('N_PA_Balancing_Sesi')->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Sesi', '<>', PaBalancingStatus::DIBATALKAN)
            ->get(['Id_PA_Balancing_Sesi', 'Nama_Sesi', 'Status_Sesi', 'Final_At', 'Hitung_Ulang_At', 'Updated_At', 'Updated_By', 'Created_At', 'Created_By']);
        $sesiTerbuka = $this->balancing->sesiTerbuka($cycleId)->count();
        $sesiMenunggu = $this->balancing->sesiFinalBelumDihitung($cycleId)->count();
        $nama = $this->namaPengguna([
            $s->Updated_By, $s->Published_By,
            ...$sesi->pluck('Updated_By')->all(), ...$sesi->pluck('Created_By')->all(),
        ]);

        $tahap = [
            $this->tahapPengisian($cycleId, $status, $penugasan, $s, $nama),
            $this->tahapVerifikasi($cycleId, $pasca, $hasil, $nama),
            $this->tahapKualitatif($cycleId, $hasil),
            $this->tahapKurva($cycleId, $status, $pasca, $metode, $terkunci, $hasil),
            $this->tahapBalancing($pasca, $sesi, $sesiTerbuka, $sesiMenunggu, $metode, $terkunci, $hasil, $dirilis, $nama),
            $this->tahapTerbit($cycleId, $status, $pasca, $metode, $terkunci, $sesiTerbuka, $sesiMenunggu, $hasil, $dirilis, $s, $nama),
        ];

        $disarankan = collect($tahap)->first(fn ($t) => $t['keadaan'] === self::BERJALAN);

        /* PARALEL, bukan berurutan (UAT 8 Okt). Bila yang tersisa di Hitung & Verifikasi hanya
           orang yang tertahan karena KPI, pekerjaannya ada di pemilik KPI, bukan di admin —
           admin tidak perlu menunggu. Saran "Berikutnya" pindah ke Kesimpulan Kualitatif selama
           tahap itu masih berjalan; kesimpulannya selamat saat hasil dihitung ulang. */
        if (($disarankan['kode'] ?? null) === 'verifikasi' && $this->hanyaTertahanKpi($cycleId, $hasil)) {
            $kualitatif = collect($tahap)->firstWhere('kode', 'kualitatif');
            if (($kualitatif['keadaan'] ?? null) === self::BERJALAN) {
                $disarankan = $kualitatif;
            }
        }

        return [
            'siklus' => [
                'cycle_id' => (int) $s->Id_PA_Cycle, 'kode' => $s->Kode_Cycle, 'nama' => $s->Nama_Cycle,
                'status' => $status, 'metode_grade' => $metode,
            ],
            'tahap_disarankan' => $disarankan['kode'] ?? ($status === 'PUBLISHED' ? 'terbit' : 'pengisian'),
            'tahap' => $tahap,
            'cacah' => [
                'penugasan_total' => $penugasan['total'], 'penugasan_selesai' => $penugasan['selesai'],
                'hasil_total' => $hasil['total'], 'hasil_dihitung' => $hasil['dihitung'], 'hasil_tertahan' => $hasil['tertahan'],
                'hasil_belum' => $hasil['belum_dihitung'], 'hasil_terbit' => $hasil['terbit'], 'dirilis_leader' => $dirilis,
                'sesi_total' => $sesi->count(), 'sesi_terbuka' => $sesiTerbuka, 'sesi_menunggu' => $sesiMenunggu,
            ],
        ];
    }

    /** Yang tersisa di verifikasi hanya tertahan karena KPI (tanpa yang belum dihitung). */
    private function hanyaTertahanKpi(int $cycleId, array $hasil): bool
    {
        if ((int) ($hasil['tertahan'] ?? 0) === 0 || (int) ($hasil['belum_dihitung'] ?? 0) > 0) {
            return false;
        }

        $k = app(PaAdminDashboardService::class)->keranjang($cycleId);

        return (int) ($k['needs_rater'] ?? 0) === 0 && (int) ($k['tertahan_lain'] ?? 0) === 0;
    }

    /* ------------------------------------------------------------------ */

    private function tahapPengisian(int $cycleId, string $status, array $penugasan, object $s, array $nama): array
    {
        $keadaan = match (true) {
            $status === 'OPEN' => self::BERJALAN,
            in_array($status, self::PASCA_PENGISIAN, true) => self::SELESAI,
            default => self::MENUNGGU,
        };

        $penghambat = [];

        if ($status === 'LOCKED') {
            /* LOCKED berarti penyiapan SUDAH selesai dan dikunci. Kalimat lama ("selesaikan
               penyiapan lebih dulu") menyuruh admin kembali ke pekerjaan yang sudah beres,
               padahal langkah berikutnya adalah membuka pengisian. */
            $penghambat[] = 'Penyiapan siklus sudah selesai dan dikunci; langkah berikutnya membuka pengisian (tombol "Buka Pengisian").';

            // Penolakan server yang sama (`pastikanTidakAdaSiklusLainTerbuka`) disebut di sini lebih dulu.
            $lain = $this->penyiapan->siklusSedangTerbuka($cycleId);

            if ($lain !== null) {
                $penghambat[] = "Siklus {$lain['kode']} ({$lain['nama']}) sedang terbuka, dan hanya satu siklus boleh terbuka pada satu waktu. Tutup dulu pengisiannya.";
            }
        } elseif ($keadaan === self::MENUNGGU) {
            $penghambat[] = "Pengisian belum dibuka (status siklus {$status}). Selesaikan penyiapan siklus lebih dulu.";
        }

        /* Catatan, BUKAN penghambat. Siklus yang ditutup dengan sebagian besar penugasan
           tak pernah diisi tetap sah ditutup — tetapi tampil SELESAI tanpa sepatah kata pun
           membuat "15 dari 1.140" terbaca beres. `tahap()` membuang penghambat pada tahap
           SELESAI, jadi informasinya dibawa di kolom tersendiri. */
        $catatan = null;

        if ($keadaan === self::SELESAI && $penugasan['selesai'] < $penugasan['total']) {
            $catatan = sprintf('Ditutup dengan %d dari %d penugasan selesai; yang belum selesai tidak ikut dihitung.',
                $penugasan['selesai'], $penugasan['total']);
        }

        return $this->tahap('pengisian', 'Pengisian', $keadaan,
            sprintf('%d dari %d penugasan selesai diisi.', $penugasan['selesai'], $penugasan['total']),
            $penghambat,
            // Tidak ada log status siklus; yang jujur hanya "terakhir diubah", bukan "ditutup pada".
            $this->aktivitas("Siklus terakhir diubah (status {$status})", $s->Updated_By, $s->Updated_At, $nama),
            $catatan,
        );
    }

    private function tahapVerifikasi(int $cycleId, bool $pasca, array $h, array $nama): array
    {
        $penghambat = [];

        if (! $pasca) {
            $penghambat[] = 'Tutup pengisian lebih dulu; hitung massal hanya berjalan sesudah siklus CLOSED.';
        }

        if ($h['belum_dihitung'] > 0) {
            $penghambat[] = "{$h['belum_dihitung']} peserta belum dihitung.";
        }

        if ($h['tertahan'] > 0) {
            $penghambat[] = "{$h['tertahan']} hasil TERTAHAN. Bereskan di Antrean Tindak Lanjut (penilai belum lengkap atau data kurang).";
        }

        $keadaan = ! $pasca ? self::MENUNGGU : ($h['belum_dihitung'] === 0 && $h['tertahan'] === 0 && $h['total'] > 0 ? self::SELESAI : self::BERJALAN);
        $terakhir = DB::table('N_PA_Result as r')->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('r.Flag_Aktif', 'Y')->whereNotNull('r.Calculated_At')
            ->orderByDesc('r.Calculated_At')->first(['r.Calculated_At', 'r.Calculated_By']);

        return $this->tahap('verifikasi', 'Hitung & Verifikasi', $keadaan,
            sprintf('%d dihitung, %d tertahan, %d belum dihitung, %d terbit.', $h['dihitung'], $h['tertahan'], $h['belum_dihitung'], $h['terbit']),
            $penghambat,
            $terakhir === null ? null : $this->aktivitas('Hasil terakhir dihitung', $terakhir->Calculated_By, $terakhir->Calculated_At,
                $nama + $this->namaPengguna([$terakhir->Calculated_By])),
        );
    }

    /**
     * Kesimpulan kualitatif admin. TIDAK pernah menjadi penghambat: menerbitkan tanpa
     * kesimpulan tetap sah. Letaknya sebelum Balancing karena sesi Balancing mengambil
     * kesimpulan ini sebagai isi awal kualitatif di board forum.
     */
    private function tahapKualitatif(int $cycleId, array $h): array
    {
        if ($h['dihitung'] + $h['terbit'] + $h['tertahan'] === 0) {
            return $this->tahap('kualitatif', 'Kesimpulan Kualitatif', self::MENUNGGU,
                'Belum ada hasil yang dihitung; kesimpulan ditulis sesudah hasil peserta ada.', [], null);
        }

        $r = $this->kualitatif->ringkas($cycleId);

        if ($r['total'] === 0) {
            return $this->tahap('kualitatif', 'Kesimpulan Kualitatif', self::DILEWATI,
                'Tidak ada jawaban kualitatif dari penilai di siklus ini.', [], null);
        }

        $aktivitas = $r['terakhir'] === null ? null : $this->aktivitas('Kesimpulan terakhir disimpan',
            $r['terakhir']['oleh_id'], $r['terakhir']['pada'], $this->namaPengguna([$r['terakhir']['oleh_id']]));

        return $this->tahap('kualitatif', 'Kesimpulan Kualitatif', $r['belum'] === 0 ? self::SELESAI : self::BERJALAN,
            sprintf('%d dari %d peserta sudah disimpulkan%s%s. Tidak mengunci penerbitan; Balancing memakai kesimpulan ini sebagai isi awal.',
                $r['sudah'], $r['total'], $r['belum'] > 0 ? ", {$r['belum']} belum" : '',
                $r['terkunci'] > 0 ? ", {$r['terkunci']} sudah terbit tanpa kesimpulan" : ''),
            [], $aktivitas);
    }

    private function tahapKurva(int $cycleId, string $status, bool $pasca, string $metode, bool $terkunci, array $h): array
    {
        if ($metode !== 'KURVA') {
            return $this->tahap('kurva', 'Kurva Normal', self::DILEWATI,
                'Siklus ini memakai metode PITA: grade dari rentang nilai tetap, tidak perlu kunci kurva.', [], null);
        }

        $penghambat = [];

        if (! $terkunci) {
            if ($status !== 'CLOSED') {
                $penghambat[] = 'Kurva hanya bisa dikunci saat siklus CLOSED.';
            }

            if ($h['dihitung'] + $h['terbit'] === 0) {
                $penghambat[] = 'Belum ada hasil DIHITUNG untuk membentuk kurva.';
            }

            if ($h['tertahan'] + $h['belum_dihitung'] > 0) {
                $penghambat[] = sprintf('%d hasil belum dihitung/tertahan tidak akan ikut membentuk kurva. Bereskan lebih dulu, atau kunci dengan alasan tertulis bila memang disengaja.',
                    $h['tertahan'] + $h['belum_dihitung']);
            }
        } elseif ($h['tertahan'] + $h['belum_dihitung'] > 0) {
            /* Kurva terkunci BUKAN berarti tahap ini beres. Sampai 8 Oktober 2026 tahap ini
               tampil SELESAI tanpa keluhan walau kurvanya dibekukan dari sebagian kecil hasil —
               padahal yang menyusul menerima grade dari kurva yang tidak mereka bentuk. */
            $penghambat[] = sprintf('%d belum dihitung dan %d tertahan tidak ikut membentuk kurva yang terkunci; '
                .'sesudah dihitung mereka menerima grade dari kurva beku. Buka lalu kunci ulang bila mereka harus ikut membentuknya.',
                $h['belum_dihitung'], $h['tertahan']);
        }

        $kunci = DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $cycleId)
            ->orderByDesc('Id_PA_Cycle_Curve')->first(['Flag_Aktif', 'Locked_At', 'Locked_By', 'Unlocked_At', 'Unlocked_By']);
        $aktivitas = null;

        if ($kunci !== null) {
            $dibuka = $kunci->Unlocked_At !== null && $kunci->Flag_Aktif !== 'Y';
            $oleh = $dibuka ? $kunci->Unlocked_By : $kunci->Locked_By;
            $aktivitas = $this->aktivitas($dibuka ? 'Kunci kurva dibuka' : 'Kurva dikunci', $oleh,
                $dibuka ? $kunci->Unlocked_At : $kunci->Locked_At, $this->namaPengguna([$oleh]));
        }

        $keadaan = match (true) {
            // Terkunci tetapi masih ada yang belum berangka: penghambatnya harus tetap terbaca.
            $terkunci => $penghambat === [] ? self::SELESAI : self::BERJALAN,
            $pasca && $status === 'CLOSED' => self::BERJALAN,
            default => self::MENUNGGU,
        };

        return $this->tahap('kurva', 'Kurva Normal', $keadaan,
            $terkunci ? 'Kurva grade terkunci; grade resmi memakai kurva ini.' : 'Kurva belum dikunci; grade masih sementara.',
            $penghambat, $aktivitas);
    }

    private function tahapBalancing(bool $pasca, $sesi, int $terbuka, int $menunggu, string $metode, bool $terkunci, array $h, int $dirilis, array $nama): array
    {
        $penghambat = [];

        if ($terbuka > 0) {
            $penghambat[] = "{$terbuka} sesi masih terbuka. Finalkan atau batalkan sebelum menerbitkan.";
        }

        if ($menunggu > 0) {
            $penghambat[] = "{$menunggu} sesi FINAL belum dihitung ulang. Jalankan Hitung ulang Balancing.";
        }

        if ($pasca && $metode === 'KURVA' && ! $terkunci) {
            $penghambat[] = 'Kunci kurva lebih dulu; sesi Balancing memakai grade resmi sebagai Before.';
        }

        if ($metode === 'KURVA' && ($h['terbit'] > 0 || $dirilis > 0)) {
            $penghambat[] = 'Sudah ada hasil yang terbit/dirilis ke leader; sesi baru tidak bisa dibuat pada siklus KURVA.';
        }

        $keadaan = match (true) {
            ! $pasca => self::MENUNGGU,
            $terbuka > 0 || $menunggu > 0 => self::BERJALAN,
            $sesi->isNotEmpty() => self::SELESAI,
            default => self::DILEWATI,
        };

        $terakhir = $sesi->sortByDesc(fn ($x) => (string) ($x->Hitung_Ulang_At ?? $x->Updated_At ?? $x->Final_At ?? $x->Created_At))->first();
        $aktivitas = null;

        if ($terakhir !== null) {
            $aksi = match (true) {
                $terakhir->Hitung_Ulang_At !== null => "Sesi \"{$terakhir->Nama_Sesi}\" dihitung ulang",
                $terakhir->Status_Sesi === PaBalancingStatus::FINAL => "Sesi \"{$terakhir->Nama_Sesi}\" difinalkan",
                default => "Sesi \"{$terakhir->Nama_Sesi}\" diperbarui",
            };
            $pada = $terakhir->Hitung_Ulang_At ?? $terakhir->Updated_At ?? $terakhir->Final_At ?? $terakhir->Created_At;
            $aktivitas = $this->aktivitas($aksi, $terakhir->Updated_By ?? $terakhir->Created_By, $pada, $nama);
        }

        return $this->tahap('balancing', 'Balancing', $keadaan,
            $sesi->isEmpty() ? 'Opsional. Belum ada sesi Balancing di siklus ini.'
                : sprintf('%d sesi: %d terbuka, %d menunggu hitung ulang.', $sesi->count(), $terbuka, $menunggu),
            $keadaan === self::MENUNGGU ? ['Balancing dibuka sesudah pengisian ditutup.'] : $penghambat,
            $aktivitas);
    }

    private function tahapTerbit(int $cycleId, string $status, bool $pasca, string $metode, bool $terkunci, int $terbuka, int $menunggu, array $h, int $dirilis, object $s, array $nama): array
    {
        $penghambat = [];

        if (! $pasca) {
            /* Kalimatnya mengikuti gerbang, bukan sebaliknya. `TERBITKAN` dan `RILIS_LEADER`
               SENGAJA menerima OPEN (lihat PaCycleGate dan CycleGateTest): hasil per orang yang
               penilainya sudah lengkap tidak perlu menunggu orang terakhir. Kalimat lama
               "dibuka sesudah pengisian ditutup" menyangkal pintu yang sebenarnya terbuka. Pada
               siklus KURVA pintunya memang tertutup — grade menunggu kurva dikunci, dan kurva
               hanya bisa dikunci saat CLOSED. */
            $penghambat[] = match (true) {
                $status !== 'OPEN' => 'Pengisian belum dibuka; belum ada hasil untuk dirilis atau diterbitkan.',
                $metode === 'KURVA' => 'Penerbitan menunggu kurva dikunci, dan kurva baru bisa dikunci sesudah pengisian ditutup.',
                default => 'Pengisian masih berjalan. Hasil yang sudah dihitung boleh dirilis atau diterbitkan per orang; '
                    .'penerbitan menyeluruh dan penutupan siklus sebaiknya menunggu pengisian ditutup.',
            };
        } elseif ($status === 'CLOSED') {
            if ($metode === 'KURVA' && ! $terkunci) {
                $penghambat[] = 'Kurva belum dikunci; grade belum resmi.';
            }

            if ($terbuka + $menunggu > 0) {
                $penghambat[] = 'Balancing belum tuntas (sesi terbuka atau belum dihitung ulang).';
            }

            if ($h['tertahan'] + $h['belum_dihitung'] > 0) {
                $penghambat[] = sprintf('%d hasil tertahan/belum dihitung tidak ikut terbit; siklus tidak bisa ditutup (PUBLISHED) sebelum semuanya terbit.',
                    $h['tertahan'] + $h['belum_dihitung']);
            }
        }

        $aktivitas = null;

        if ($status === 'PUBLISHED') {
            $aktivitas = $this->aktivitas('Penerbitan ditutup (PUBLISHED)', $s->Published_By, $s->Published_At, $nama);
        } else {
            $akhir = DB::table('N_PA_Result as r')->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
                ->where('p.PA_Cycle_Id', $cycleId)->where('r.Flag_Aktif', 'Y')
                ->where(fn ($q) => $q->whereNotNull('r.Published_At')->orWhereNotNull('r.Published_Leader_At'))
                ->selectRaw('MAX(r.Published_At) as terbit, MAX(r.Published_Leader_At) as rilis')->first();

            if ($akhir !== null && ($akhir->terbit !== null || $akhir->rilis !== null)) {
                $terbit = (string) ($akhir->terbit ?? '') >= (string) ($akhir->rilis ?? '');
                $kolom = $terbit ? 'Published' : 'Published_Leader';
                $baris = DB::table('N_PA_Result as r')->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
                    ->where('p.PA_Cycle_Id', $cycleId)->where('r.Flag_Aktif', 'Y')->orderByDesc("r.{$kolom}_At")
                    ->first(["r.{$kolom}_At as pada", "r.{$kolom}_By as oleh"]);
                $aktivitas = $this->aktivitas($terbit ? 'Hasil diterbitkan ke karyawan' : 'Hasil dirilis ke leader',
                    $baris->oleh, $baris->pada, $this->namaPengguna([$baris->oleh]));
            }
        }

        return $this->tahap('terbit', 'Rilis & Terbit', $status === 'PUBLISHED' ? self::SELESAI : ($pasca ? self::BERJALAN : self::MENUNGGU),
            sprintf('%d dari %d hasil terbit ke karyawan, %d dirilis ke leader.', $h['terbit'], $h['total'], $dirilis),
            $penghambat, $aktivitas);
    }

    /* ------------------------------------------------------------------ */

    /** @return array{total:int, selesai:int} */
    private function cacahPenugasan(int $cycleId): array
    {
        $baris = DB::table('N_PA_Assignment as a')->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('p.Status_Peserta', 'DIIKUTKAN')->where('p.Flag_Aktif', 'Y')
            ->where('a.Flag_Aktif', 'Y')->where('a.Status_Penugasan', '<>', PaStatusPenugasan::DIBATALKAN)
            ->groupBy('a.Status_Penugasan')->selectRaw('a.Status_Penugasan as s, COUNT(*) as n')->pluck('n', 's');

        return [
            'total' => (int) $baris->sum(),
            'selesai' => (int) collect(PaStatusPenugasan::TUNTAS)->sum(fn ($k) => (int) ($baris[$k] ?? 0)),
        ];
    }

    /** @var array<int, string> nama pengguna yang sudah dibaca, supaya tiap tahap tidak bertanya ulang */
    private array $memoNama = [];

    /** @return array<int, string> */
    private function namaPengguna(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map(fn ($v) => $v === null ? null : (int) $v, $ids))));
        $baru = array_values(array_diff($ids, array_keys($this->memoNama)));

        // Nama kosong di KPI_Users (mis. akun uji) jatuh ke Username, bukan "Pengguna #id".
        if ($baru !== []) {
            $this->memoNama += DB::table('KPI_Users')->whereIn('Id_Users', $baru)->get(['Id_Users', 'Nama', 'Username'])
                ->mapWithKeys(fn ($u) => [(int) $u->Id_Users => trim((string) ($u->Nama ?? '')) ?: (string) $u->Username])->all();
        }

        return array_intersect_key($this->memoNama, array_flip($ids));
    }

    private function aktivitas(string $aksi, $oleh, $pada, array $nama): ?array
    {
        if ($pada === null) {
            return null;
        }

        return ['aksi' => $aksi, 'oleh' => $oleh === null ? null : ($nama[(int) $oleh] ?? "Pengguna #{$oleh}"), 'pada' => substr((string) $pada, 0, 19)];
    }

    private function tahap(string $kode, string $nama, string $keadaan, string $ringkas, array $penghambat, ?array $aktivitas, ?string $catatan = null): array
    {
        return [
            'kode' => $kode, 'nama' => $nama, 'keadaan' => $keadaan, 'ringkas' => $ringkas,
            // Tahap yang sudah selesai atau dilewati tidak menampilkan keluhan.
            'penghambat' => in_array($keadaan, [self::SELESAI, self::DILEWATI], true) ? [] : array_values($penghambat),
            // Informasi yang tidak menahan apa pun — tetap tampil pada tahap SELESAI.
            'catatan' => $catatan,
            'aktivitas' => $aktivitas,
        ];
    }
}
