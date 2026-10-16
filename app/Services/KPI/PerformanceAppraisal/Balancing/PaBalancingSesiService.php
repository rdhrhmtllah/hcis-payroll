<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

use App\Helpers\FormatTanggalHelper;
use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use App\Services\KPI\PerformanceAppraisal\PaTeksKaya;
use Illuminate\Support\Facades\DB;

/**
 * Siklus hidup sesi Balancing: TERBUKA → DRAFT → FINAL, atau DIBATALKAN (spec §3).
 *
 * Isian sesi FINAL tidak pernah disunting; koreksi sesudahnya lewat sesi baru, sehingga
 * riwayat setiap putaran tetap utuh.
 */
class PaBalancingSesiService
{
    /**
     * Kualitatif yang dibahas di Balancing: hanya rangkuman admin untuk Strength (QL01) dan
     * Development Area (QL02). Initiative dan Recommendation tidak ikut dibahas di forum.
     * Kodenya mengikuti daftar kompetensi yang disepakati bersama HC (QL01–QL04).
     */
    public const KUALITATIF_BALANCING = ['QL01', 'QL02'];

    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaKurvaService $kurva,
        private readonly PaBalancingKandidatService $kandidat,
        private readonly PaResultCalculationService $perhitungan,
        private readonly PaTeksKaya $teksKaya,
        private readonly PaBalancingPenjaga $penjaga,
        private readonly PaBalancingTimPenilaiService $timPenilai,
    ) {}

    public function buat(int $cycleId, array $data, ?int $olehUserId): array
    {
        $this->pastikanSiklusSiap($cycleId);

        $nama = trim((string) ($data['nama'] ?? ''));

        if ($nama === '' || mb_strlen($nama) > 200) {
            throw new PaBalancingException('Nama sesi wajib diisi, paling banyak 200 karakter.', PaBalancingException::DATA_TIDAK_SAH);
        }

        $picId = (int) ($data['pic_user_id'] ?? 0);

        if (! DB::table('KPI_Users')->where('Id_Users', $picId)->exists()) {
            throw new PaBalancingException('PIC sesi tidak ditemukan di daftar pengguna.', PaBalancingException::DATA_TIDAK_SAH);
        }

        $pesertaIds = array_values(array_unique(array_map('intval', (array) ($data['peserta_ids'] ?? []))));

        if ($pesertaIds === []) {
            throw new PaBalancingException('Pilih sekurangnya satu peserta.', PaBalancingException::DATA_TIDAK_SAH);
        }

        $forum = $this->forumSah((array) ($data['forum'] ?? []));
        $this->pastikanLayak($cycleId, $pesertaIds);

        // Dibaca di luar transaksi: membaca penugasan tiap peserta, tidak menulis apa pun.
        $bobot = [];

        foreach ($pesertaIds as $pid) {
            $bobot[$pid] = $this->perhitungan->rincianKompetensi($pid)['bobot_kompetensi'] ?? [];
        }

        $sesiId = DB::transaction(function () use ($cycleId, $nama, $picId, $forum, $pesertaIds, $bobot, $olehUserId) {
            DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->lockForUpdate()->first(['Id_PA_Cycle']);
            // Diperiksa ulang di dalam kunci: dua admin bisa membuat sesi untuk orang yang sama bersamaan.
            $this->pastikanLayak($cycleId, $pesertaIds);

            $sesiId = (int) DB::table('N_PA_Balancing_Sesi')->insertGetId([
                'PA_Cycle_Id' => $cycleId, 'Nama_Sesi' => $nama, 'PIC_User_Id' => $picId,
                'Status_Sesi' => PaBalancingStatus::TERBUKA, 'Versi_Data' => 1, 'Created_By' => $olehUserId,
            ], 'Id_PA_Balancing_Sesi');

            foreach ($forum as $i => $f) {
                DB::table('N_PA_Balancing_Forum')->insert([...$f, 'PA_Balancing_Sesi_Id' => $sesiId, 'Urutan_Tampil' => $i + 1, 'Created_By' => $olehUserId]);
            }

            foreach ($pesertaIds as $pid) {
                $this->potretPeserta($sesiId, $pid, $bobot[$pid], $olehUserId);
            }

            return $sesiId;
        });

        return $this->detail($sesiId);
    }

    public function simpanDraft(int $sesiId, int $versiData, array $peserta, ?string $catatanRapat, ?int $olehUserId): array
    {
        DB::transaction(function () use ($sesiId, $versiData, $peserta, $catatanRapat, $olehUserId) {
            $sesi = $this->kunciSesiTerbuka($sesiId, $versiData);
            $jam = FormatTanggalHelper::getCurrentTime();
            $anggota = DB::table('N_PA_Balancing_Peserta as bp')
                ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'bp.PA_Participant_Id')
                ->where('bp.PA_Balancing_Sesi_Id', $sesiId)
                ->get(['bp.*', 'p.Nama_Karyawan_Saat_Cycle as Nama'])->keyBy('PA_Participant_Id');
            $namaKompetensi = DB::table('N_PA_Competency')->pluck('Nama_Competency', 'Id_PA_Competency');

            foreach ($peserta as $p) {
                $bp = $anggota[(int) ($p['participant_id'] ?? 0)] ?? null;

                if ($bp === null) {
                    throw new PaBalancingException('Peserta #'.($p['participant_id'] ?? '?').' bukan anggota sesi ini.', PaBalancingException::DATA_TIDAK_SAH);
                }

                $this->simpanKompetensi($bp, (array) ($p['kompetensi'] ?? []), $namaKompetensi, $jam, $olehUserId);
                $this->simpanKualitatif($bp, (array) ($p['kualitatif'] ?? []), $jam, $olehUserId);

                /* Alasan dicatat PER ORANG, bukan per kompetensi: forum menyepakati penyesuaian
                   seseorang sebagai satu keputusan. Wajib bila ada nilai atau catatan yang diubah. */
                $alasan = array_key_exists('alasan', $p) ? trim((string) $p['alasan']) : trim((string) $bp->Alasan);
                $status = $this->statusTinjau($bp, $p['status_tinjau'] ?? null);

                if ($status === PaBalancingStatus::DIUBAH && $alasan === '') {
                    throw new PaBalancingException("{$bp->Nama}: alasan perubahan wajib diisi karena ada nilai yang diubah.", PaBalancingException::ALASAN_WAJIB);
                }

                DB::table('N_PA_Balancing_Peserta')->where('Id_PA_Balancing_Peserta', $bp->Id_PA_Balancing_Peserta)->update([
                    'Status_Tinjau' => $status,
                    'Alasan' => $alasan === '' ? null : mb_substr($alasan, 0, 1000),
                    'Updated_At' => $jam, 'Updated_By' => $olehUserId,
                ]);
            }

            DB::table('N_PA_Balancing_Sesi')->where('Id_PA_Balancing_Sesi', $sesiId)->update([
                'Status_Sesi' => PaBalancingStatus::DRAFT,
                'Catatan_Rapat' => $catatanRapat === null ? $sesi->Catatan_Rapat : ($this->teksKaya->bersihkan($catatanRapat) ?: null),
                'Versi_Data' => (int) $sesi->Versi_Data + 1,
                'Updated_At' => $jam, 'Updated_By' => $olehUserId,
            ]);
        });

        return $this->detail($sesiId);
    }

    public function finalkan(int $sesiId, int $versiData, ?int $olehUserId): array
    {
        DB::transaction(function () use ($sesiId, $versiData, $olehUserId) {
            $sesi = $this->kunciSesiTerbuka($sesiId, $versiData);
            $this->pastikanSiklusSiap((int) $sesi->PA_Cycle_Id);
            $belum = DB::table('N_PA_Balancing_Peserta as bp')
                ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'bp.PA_Participant_Id')
                ->where('bp.PA_Balancing_Sesi_Id', $sesiId)->where('bp.Status_Tinjau', PaBalancingStatus::BELUM)
                ->pluck('p.Nama_Karyawan_Saat_Cycle');

            if ($belum->isNotEmpty()) {
                throw new PaBalancingException(
                    'Masih ada peserta yang belum ditinjau: '.$belum->implode(', ').'. Tandai "tanpa perubahan" atau ubah nilainya.',
                    PaBalancingException::PESERTA_BELUM_DITINJAU,
                );
            }

            $jam = FormatTanggalHelper::getCurrentTime();
            DB::table('N_PA_Balancing_Sesi')->where('Id_PA_Balancing_Sesi', $sesiId)->update([
                'Status_Sesi' => PaBalancingStatus::FINAL, 'Final_At' => $jam, 'Final_By' => $olehUserId,
                'Versi_Data' => (int) $sesi->Versi_Data + 1, 'Updated_At' => $jam, 'Updated_By' => $olehUserId,
            ]);
        });

        return $this->detail($sesiId);
    }

    public function batalkan(int $sesiId, string $alasan, ?int $olehUserId): array
    {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 500) {
            throw new PaBalancingException('Alasan membatalkan sesi wajib diisi, 5 sampai 500 karakter.', PaBalancingException::DATA_TIDAK_SAH);
        }

        DB::transaction(function () use ($sesiId, $alasan, $olehUserId) {
            $sesi = $this->kunciSesiTerbuka($sesiId, null);
            $jam = FormatTanggalHelper::getCurrentTime();
            DB::table('N_PA_Balancing_Sesi')->where('Id_PA_Balancing_Sesi', $sesiId)->update([
                'Status_Sesi' => PaBalancingStatus::DIBATALKAN, 'Cancelled_At' => $jam, 'Cancelled_By' => $olehUserId,
                'Alasan_Batal' => $alasan, 'Versi_Data' => (int) $sesi->Versi_Data + 1, 'Updated_At' => $jam, 'Updated_By' => $olehUserId,
            ]);
        });

        return $this->detail($sesiId);
    }

    /** @return list<array<string, mixed>> */
    public function daftar(int $cycleId): array
    {
        return DB::table('N_PA_Balancing_Sesi as s')
            ->leftJoin('KPI_Users as u', 'u.Id_Users', '=', 's.PIC_User_Id')
            ->where('s.PA_Cycle_Id', $cycleId)
            ->orderByDesc('s.Id_PA_Balancing_Sesi')
            ->get([
                's.Id_PA_Balancing_Sesi', 's.Nama_Sesi', 's.Status_Sesi', 's.PIC_User_Id', 'u.Nama as PIC_Nama',
                's.Created_At', 's.Final_At', 's.Hitung_Ulang_At', 's.Nomor_Hitung_Ulang',
                DB::raw('(SELECT COUNT(*) FROM N_PA_Balancing_Peserta bp WHERE bp.PA_Balancing_Sesi_Id = s.Id_PA_Balancing_Sesi) AS Jumlah_Peserta'),
                DB::raw('(SELECT COUNT(*) FROM N_PA_Balancing_Forum f WHERE f.PA_Balancing_Sesi_Id = s.Id_PA_Balancing_Sesi) AS Jumlah_Forum'),
            ])
            ->map(fn ($s) => [
                'id' => (int) $s->Id_PA_Balancing_Sesi, 'nama' => $s->Nama_Sesi, 'status' => $s->Status_Sesi,
                'pic_user_id' => (int) $s->PIC_User_Id, 'pic_nama' => $s->PIC_Nama,
                'jumlah_peserta' => (int) $s->Jumlah_Peserta, 'jumlah_forum' => (int) $s->Jumlah_Forum,
                'created_at' => $s->Created_At, 'final_at' => $s->Final_At, 'hitung_ulang_at' => $s->Hitung_Ulang_At,
                'nomor_hitung_ulang' => $s->Nomor_Hitung_Ulang === null ? null : (int) $s->Nomor_Hitung_Ulang,
            ])->all();
    }

    public function detail(int $sesiId): array
    {
        $s = DB::table('N_PA_Balancing_Sesi as s')->leftJoin('KPI_Users as u', 'u.Id_Users', '=', 's.PIC_User_Id')
            ->where('s.Id_PA_Balancing_Sesi', $sesiId)->first(['s.*', 'u.Nama as PIC_Nama']);

        if ($s === null) {
            throw new PaBalancingException("Sesi Balancing {$sesiId} tidak ditemukan.", PaBalancingException::SESI_TIDAK_DITEMUKAN, 404);
        }

        $bp = DB::table('N_PA_Balancing_Peserta as bp')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'bp.PA_Participant_Id')
            ->leftJoin('N_PA_Result as r', 'r.Id_PA_Result', '=', 'bp.PA_Result_Id')
            ->where('bp.PA_Balancing_Sesi_Id', $sesiId)->orderBy('p.Nama_Karyawan_Saat_Cycle')
            ->get([
                'bp.*', 'p.Nama_Karyawan_Saat_Cycle', 'p.Kode_Karyawan', 'p.Nama_Level_Saat_Cycle', 'p.ID_Level_Saat_Cycle', 'p.Nama_Divisi_Saat_Cycle', 'p.Nama_Sub_Divisi_Saat_Cycle',
                'r.Bobot_KPI_Terpakai', 'r.Bobot_Kompetensi_Terpakai',
            ]);
        $ids = $bp->pluck('Id_PA_Balancing_Peserta')->all();

        $komp = DB::table('N_PA_Balancing_Kompetensi as k')->join('N_PA_Competency as c', 'c.Id_PA_Competency', '=', 'k.PA_Competency_Id')
            ->whereIn('k.PA_Balancing_Peserta_Id', $ids)->orderBy('c.Urutan_Tampil')->orderBy('c.Id_PA_Competency')
            ->get(['k.*', 'c.Kode_Competency', 'c.Nama_Competency', 'c.Deskripsi', 'c.Expected_Behaviour'])->groupBy('PA_Balancing_Peserta_Id');
        $kual = DB::table('N_PA_Balancing_Kualitatif as k')->join('N_PA_Competency as c', 'c.Id_PA_Competency', '=', 'k.PA_Competency_Id')
            ->whereIn('k.PA_Balancing_Peserta_Id', $ids)->whereIn('c.Kode_Competency', self::KUALITATIF_BALANCING)->orderBy('c.Urutan_Tampil')->orderBy('c.Id_PA_Competency')
            ->get(['k.*', 'c.Kode_Competency', 'c.Nama_Competency'])->groupBy('PA_Balancing_Peserta_Id');

        $angka = fn ($v) => $v === null ? null : (float) $v;

        /* Expected Behaviour sesuai tingkat yang dituntut untuk level tiap peserta (DDL-2026-175),
           dibaca sekali per level. */
        $matriks = app(\App\Services\KPI\PerformanceAppraisal\PaKompetensiMatriksService::class);
        $perilakuLevel = [];
        $perilaku = function ($b, $k) use ($matriks, &$perilakuLevel, $s) {
            $lv = $b->ID_Level_Saat_Cycle === null ? null : (int) $b->ID_Level_Saat_Cycle;
            $perilakuLevel[$lv ?? 0] ??= $matriks->perilakuDituntut((int) $s->PA_Cycle_Id, $lv);
            $p = $perilakuLevel[$lv ?? 0][(int) $k->PA_Competency_Id] ?? null;

            // Hanya dari tingkat yang dituntut level peserta; EB umum kompetensi tidak dipakai lagi.
            return $p !== null && trim((string) $p['expected_behaviour']) !== '' ? $p['expected_behaviour'] : null;
        };
        $tingkat = function ($b, $k) use ($matriks, &$perilakuLevel, $s) {
            $lv = $b->ID_Level_Saat_Cycle === null ? null : (int) $b->ID_Level_Saat_Cycle;
            $perilakuLevel[$lv ?? 0] ??= $matriks->perilakuDituntut((int) $s->PA_Cycle_Id, $lv);

            return $perilakuLevel[$lv ?? 0][(int) $k->PA_Competency_Id] ?? null;
        };

        return [
            'id' => (int) $s->Id_PA_Balancing_Sesi, 'cycle_id' => (int) $s->PA_Cycle_Id, 'nama' => $s->Nama_Sesi,
            'status' => $s->Status_Sesi, 'versi_data' => (int) $s->Versi_Data, 'catatan_rapat' => $s->Catatan_Rapat,
            'pic_user_id' => (int) $s->PIC_User_Id, 'pic_nama' => $s->PIC_Nama, 'final_at' => $s->Final_At,
            'hitung_ulang_at' => $s->Hitung_Ulang_At, 'alasan_batal' => $s->Alasan_Batal,
            'forum' => DB::table('N_PA_Balancing_Forum')->where('PA_Balancing_Sesi_Id', $sesiId)->orderBy('Urutan_Tampil')
                ->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama_Karyawan', 'Peran'])
                ->map(fn ($f) => ['kode_perusahaan' => $f->Kode_Perusahaan, 'kode_karyawan' => $f->Kode_Karyawan, 'nama' => $f->Nama_Karyawan, 'peran' => $f->Peran])->all(),
            'peserta' => $bp->map(fn ($b) => [
                'participant_id' => (int) $b->PA_Participant_Id, 'nama' => $b->Nama_Karyawan_Saat_Cycle,
                'kode_karyawan' => $b->Kode_Karyawan, 'level' => $b->Nama_Level_Saat_Cycle,
                'divisi' => $b->Nama_Divisi_Saat_Cycle, 'sub_divisi' => $b->Nama_Sub_Divisi_Saat_Cycle,
                'status_tinjau' => $b->Status_Tinjau, 'alasan' => $b->Alasan,
                'sebelum' => [
                    'nilai_kpi' => $angka($b->Nilai_KPI_Sebelum), 'nilai_kompetensi' => $angka($b->Nilai_Kompetensi_Sebelum),
                    'final' => $angka($b->Final_Score_Sebelum), 'grade' => $b->Kode_Grade_Sebelum,
                    // Porsi pilar dalam Final (persen) — bahan matriks board: Final = KPI×bobot + Komp×bobot.
                    'bobot_kpi' => $angka($b->Bobot_KPI_Terpakai), 'bobot_kompetensi' => $angka($b->Bobot_Kompetensi_Terpakai),
                    'kuadran_id' => $b->PA_Hav_Quadrant_Sebelum_Id === null ? null : (int) $b->PA_Hav_Quadrant_Sebelum_Id,
                ],
                'kompetensi' => collect($komp->get($b->Id_PA_Balancing_Peserta, []))->map(fn ($k) => [
                    'competency_id' => (int) $k->PA_Competency_Id, 'kode' => $k->Kode_Competency, 'nama' => $k->Nama_Competency,
                    'deskripsi' => $k->Deskripsi, 'expected_behaviour' => $perilaku($b, $k),
                    'tingkat' => $tingkat($b, $k)['tingkat'] ?? null, 'nama_tingkat' => $tingkat($b, $k)['nama_tingkat'] ?? null, 'bobot' => (float) $k->Bobot, 'sebelum' => (float) $k->Nilai_Sebelum,
                    'sesudah' => (float) $k->Nilai_Sesudah, 'alasan' => $k->Alasan,
                ])->values()->all(),
                'kualitatif' => collect($kual->get($b->Id_PA_Balancing_Peserta, []))->map(fn ($k) => [
                    'competency_id' => (int) $k->PA_Competency_Id, 'kode' => $k->Kode_Competency, 'nama' => $k->Nama_Competency,
                    'sebelum' => $k->Isi_Sebelum, 'sesudah' => $k->Isi_Sesudah,
                ])->values()->all(),
            ])->all(),
        ];
    }

    /**
     * Boleh-tidaknya sesi baru dibuat, dengan aturan yang SAMA dengan buat(). Layar mematikan
     * tombolnya dan menyebut sebabnya, alih-alih membiarkan admin menyelesaikan wizard lalu ditolak.
     *
     * @return array{boleh:bool, alasan:?string}
     */
    public function bolehBuat(int $cycleId): array
    {
        try {
            $this->pastikanSiklusSiap($cycleId);

            return ['boleh' => true, 'alasan' => null];
        } catch (PaBalancingException $e) {
            return ['boleh' => false, 'alasan' => $e->getMessage()];
        }
    }

    private function pastikanSiklusSiap(int $cycleId): void
    {
        if (! $this->gate->boleh(PaCycleGate::BALANCING, $cycleId)) {
            throw new PaBalancingException('Balancing hanya bisa dijalankan sesudah pengisian siklus ditutup (CLOSED).', PaBalancingException::SIKLUS_BELUM_DITUTUP);
        }

        if ($this->kurva->metode($cycleId) === 'KURVA' && ! $this->kurva->terkunci($cycleId)) {
            throw new PaBalancingException('Kunci kurva grade siklus ini lebih dulu; Before di sesi harus memakai grade resmi.', PaBalancingException::KURVA_BELUM_DIKUNCI);
        }

        /* Hitung ulang siklus KURVA mengunci ulang kurva seluruh grup, dan itu ditolak selama ada
           hasil yang sudah dibaca. Sesi yang dibuka sekarang akan FINAL tetapi tidak pernah bisa
           dihitung ulang — dan sesi FINAL yang menunggu menahan seluruh pintu publish. */
        if ($this->kurva->metode($cycleId) === 'KURVA' && $this->penjaga->jumlahSudahDibaca($cycleId) > 0) {
            throw new PaBalancingException(
                'Sebagian hasil siklus KURVA ini sudah terbit atau dirilis ke leader, sehingga kurvanya tidak bisa dikunci ulang. '
                .'Cabut rilis leader lebih dulu; Balancing tidak bisa dijalankan sesudah ada hasil yang terbit.',
                PaBalancingException::SUDAH_ADA_YANG_TERBIT,
            );
        }
    }

    private function pastikanLayak(int $cycleId, array $pesertaIds): void
    {
        $tolak = $this->kandidat->alasanTidakLayak($cycleId, $pesertaIds);

        if ($tolak !== []) {
            throw new PaBalancingException(
                'Peserta berikut tidak bisa masuk sesi: '.implode('; ', array_map(fn ($t) => "{$t['nama']} ({$t['alasan']})", $tolak)).'.',
                PaBalancingException::PESERTA_TIDAK_LAYAK,
            );
        }
    }

    /** @return list<array{Kode_Perusahaan:string, Kode_Karyawan:string, Nama_Karyawan:string, Peran:string}> */
    private function forumSah(array $forum): array
    {
        if ($forum === []) {
            throw new PaBalancingException('Pilih sekurangnya satu anggota tim penilai.', PaBalancingException::DATA_TIDAK_SAH);
        }

        $hasil = [];

        foreach ($forum as $f) {
            $k = $this->timPenilai->satu((string) ($f['kode_perusahaan'] ?? ''), (string) ($f['kode_karyawan'] ?? ''));

            if ($k === null) {
                throw new PaBalancingException('Anggota tim penilai harus karyawan aktif.', PaBalancingException::DATA_TIDAK_SAH);
            }

            if (isset($hasil[$k['kunci']])) {
                continue;
            }

            // Peran = jabatan dari master golongan/jabatan, bukan isian bebas; level bila jabatan kosong.
            $peran = $k['jabatan'] ?? $k['level'] ?? 'Tim penilai';
            $hasil[$k['kunci']] = ['Kode_Perusahaan' => $k['kode_perusahaan'], 'Kode_Karyawan' => $k['kode_karyawan'], 'Nama_Karyawan' => $k['nama'], 'Peran' => mb_substr($peran, 0, 100)];
        }

        return array_values($hasil);
    }

    /** @param array<int, float> $bobot */
    private function potretPeserta(int $sesiId, int $pid, array $bobot, ?int $olehUserId): void
    {
        $hasil = DB::table('N_PA_Result')->where('PA_Participant_Id', $pid)->where('Flag_Aktif', 'Y')->first();

        $bpId = (int) DB::table('N_PA_Balancing_Peserta')->insertGetId([
            'PA_Balancing_Sesi_Id' => $sesiId, 'PA_Participant_Id' => $pid, 'PA_Result_Id' => $hasil->Id_PA_Result,
            'Calculation_Version_Sebelum' => (int) $hasil->Calculation_Version, 'Nilai_KPI_Sebelum' => $hasil->Nilai_KPI,
            'Nilai_Kompetensi_Sebelum' => $hasil->Nilai_Kompetensi, 'Final_Score_Sebelum' => $hasil->Final_Score,
            'Kode_Grade_Sebelum' => $hasil->Kode_Grade_Final, 'PA_Hav_Quadrant_Sebelum_Id' => $hasil->PA_Hav_Quadrant_Id,
            'Status_Tinjau' => PaBalancingStatus::BELUM, 'Created_By' => $olehUserId,
        ], 'Id_PA_Balancing_Peserta');

        // Before = nilai radar yang berlaku (agregat aktif), sudah memuat After sesi sebelumnya.
        $radar = DB::table('N_PA_Result_Competency')->where('PA_Result_Id', $hasil->Id_PA_Result)
            ->where('Flag_Aktif', 'Y')->whereNull('PA_Rater_Category_Id')->whereNotNull('Nilai')
            ->get(['PA_Competency_Id', 'Nilai']);

        foreach ($radar as $r) {
            DB::table('N_PA_Balancing_Kompetensi')->insert([
                'PA_Balancing_Peserta_Id' => $bpId, 'PA_Competency_Id' => (int) $r->PA_Competency_Id,
                'Bobot' => round($bobot[(int) $r->PA_Competency_Id] ?? 0.0, 6),
                'Nilai_Sebelum' => $r->Nilai, 'Nilai_Sesudah' => $r->Nilai,
            ]);
        }

        $kualitatif = DB::table('N_PA_Competency as k')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'k.PA_Competency_Type_Id')
            ->where('k.Flag_Aktif', 'Y')->where('t.Kode_Type', 'KUALITATIF')
            ->whereIn('k.Kode_Competency', self::KUALITATIF_BALANCING)->pluck('k.Id_PA_Competency');
        // Before kualitatif = catatan ADMIN yang aktif (T3). Kosong bila admin belum menulis.
        $catatan = DB::table('N_PA_Result_Note')->where('PA_Result_Id', $hasil->Id_PA_Result)->where('Flag_Aktif', 'Y')
            ->whereNull('PA_Assignment_Id')->orderBy('Urutan_Tampil')->get(['PA_Competency_Id', 'Isi'])->groupBy('PA_Competency_Id');

        foreach ($kualitatif as $cid) {
            /* Dilewatkan penyaring yang sama dengan isian After. Tanpa itu kutip di catatan polos
               tersimpan sebagai entitas (&apos;) sedangkan After tersaring menjadi karakter biasa,
               sehingga isi yang dikirim balik apa adanya terbaca berubah dan peserta jadi "DIUBAH". */
            $isi = $this->teksKaya->bersihkan(collect($catatan->get($cid, []))->map(fn ($n) => $this->teksKaya->dariEsaiHcLearn((string) $n->Isi))->implode(''));
            DB::table('N_PA_Balancing_Kualitatif')->insert([
                'PA_Balancing_Peserta_Id' => $bpId, 'PA_Competency_Id' => (int) $cid,
                'Isi_Sebelum' => $isi === '' ? null : $isi, 'Isi_Sesudah' => $isi === '' ? null : $isi,
            ]);
        }
    }

    private function kunciSesiTerbuka(int $sesiId, ?int $versiData): object
    {
        $sesi = DB::table('N_PA_Balancing_Sesi')->where('Id_PA_Balancing_Sesi', $sesiId)->lockForUpdate()->first();

        if ($sesi === null) {
            throw new PaBalancingException("Sesi Balancing {$sesiId} tidak ditemukan.", PaBalancingException::SESI_TIDAK_DITEMUKAN, 404);
        }

        if (! in_array($sesi->Status_Sesi, PaBalancingStatus::SESI_BISA_DIUBAH, true)) {
            throw new PaBalancingException("Sesi \"{$sesi->Nama_Sesi}\" sudah {$sesi->Status_Sesi}; isiannya tidak bisa diubah. Buat sesi baru untuk koreksi.", PaBalancingException::SESI_TERKUNCI);
        }

        if ($versiData !== null && (int) $sesi->Versi_Data !== $versiData) {
            throw new PaBalancingException('Data sesi sudah diubah orang lain. Muat ulang lalu ulangi perubahan Anda.', PaBalancingException::VERSI_BERUBAH, 409);
        }

        return $sesi;
    }

    private function simpanKompetensi(object $bp, array $kompetensi, $namaKompetensi, string $jam, ?int $olehUserId): void
    {
        $lama = DB::table('N_PA_Balancing_Kompetensi')->where('PA_Balancing_Peserta_Id', $bp->Id_PA_Balancing_Peserta)
            ->get()->keyBy('PA_Competency_Id');

        foreach ($kompetensi as $k) {
            $cid = (int) ($k['competency_id'] ?? 0);
            $baris = $lama[$cid] ?? null;
            $label = "{$bp->Nama} — ".($namaKompetensi[$cid] ?? "kompetensi #{$cid}");

            if ($baris === null) {
                throw new PaBalancingException("{$label} tidak ada di sesi ini.", PaBalancingException::DATA_TIDAK_SAH);
            }

            $nilai = (float) ($k['nilai_sesudah'] ?? -1);

            if ($nilai < 0 || $nilai > 100) {
                throw new PaBalancingException("{$label}: nilai After harus 0 sampai 100.", PaBalancingException::NILAI_DI_LUAR_RENTANG);
            }

            // Alasan per kompetensi tidak lagi diminta (lihat simpanDraft); yang terkirim tetap disimpan.
            $alasan = trim((string) ($k['alasan'] ?? ''));

            DB::table('N_PA_Balancing_Kompetensi')->where('Id_PA_Balancing_Kompetensi', $baris->Id_PA_Balancing_Kompetensi)->update([
                'Nilai_Sesudah' => round($nilai, 2), 'Alasan' => $alasan === '' ? null : mb_substr($alasan, 0, 1000),
                'Updated_At' => $jam, 'Updated_By' => $olehUserId,
            ]);
        }
    }

    private function simpanKualitatif(object $bp, array $kualitatif, string $jam, ?int $olehUserId): void
    {
        foreach ($kualitatif as $q) {
            $n = DB::table('N_PA_Balancing_Kualitatif')->where('PA_Balancing_Peserta_Id', $bp->Id_PA_Balancing_Peserta)
                ->where('PA_Competency_Id', (int) ($q['competency_id'] ?? 0))
                ->update(['Isi_Sesudah' => $this->teksKaya->bersihkan((string) ($q['isi_sesudah'] ?? '')) ?: null, 'Updated_At' => $jam, 'Updated_By' => $olehUserId]);

            if ($n === 0) {
                throw new PaBalancingException("Kategori kualitatif #{$q['competency_id']} tidak ada di sesi ini.", PaBalancingException::DATA_TIDAK_SAH);
            }
        }
    }

    /** DIUBAH diturunkan dari isian; admin hanya bisa memilih BELUM atau TANPA_PERUBAHAN. */
    private function statusTinjau(object $bp, ?string $diminta): string
    {
        $adaUbah = DB::table('N_PA_Balancing_Kompetensi')->where('PA_Balancing_Peserta_Id', $bp->Id_PA_Balancing_Peserta)->get(['Nilai_Sebelum', 'Nilai_Sesudah'])
            ->contains(fn ($k) => PaBalancingKalkulator::berubah((float) $k->Nilai_Sebelum, (float) $k->Nilai_Sesudah))
            || DB::table('N_PA_Balancing_Kualitatif')->where('PA_Balancing_Peserta_Id', $bp->Id_PA_Balancing_Peserta)->get(['Isi_Sebelum', 'Isi_Sesudah'])
                ->contains(fn ($k) => (string) $k->Isi_Sebelum !== (string) $k->Isi_Sesudah);

        if ($adaUbah) {
            return PaBalancingStatus::DIUBAH;
        }

        if (in_array($diminta, [PaBalancingStatus::BELUM, PaBalancingStatus::TANPA_PERUBAHAN], true)) {
            return $diminta;
        }

        return $bp->Status_Tinjau === PaBalancingStatus::DIUBAH ? PaBalancingStatus::BELUM : $bp->Status_Tinjau;
    }
}
