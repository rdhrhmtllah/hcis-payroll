<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

use App\Helpers\FormatTanggalHelper;
use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaStatistik;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use App\Services\KPI\PerformanceAppraisal\PaResultHistoryService;
use App\Services\KPI\PerformanceAppraisal\PaTeksKaya;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Hitung ulang sesudah satu atau lebih sesi FINAL (spec §6.3). SATU TRANSAKSI: bila satu
 * langkah gagal, tidak ada hasil, kurva, maupun jejak yang tertinggal setengah jadi.
 *
 * Yang dihitung ulang hanya peserta sesi; orang lain hanya berubah grade lewat kurva baru,
 * dan jejaknya tetap dicatat dengan sebab KURVA.
 */
class PaBalancingHitungUlangService
{
    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaBalancingPenjaga $penjaga,
        private readonly PaBalancingPotretHasil $potret,
        private readonly PaBalancingNilaiBerlaku $nilaiBerlaku,
        private readonly PaResultCalculationService $perhitungan,
        private readonly PaKurvaService $kurva,
        private readonly PaTeksKaya $teksKaya,
    ) {}

    public function jalankan(int $cycleId, ?int $olehUserId): array
    {
        if (! $this->gate->boleh(PaCycleGate::BALANCING, $cycleId)) {
            throw new PaBalancingException('Hitung ulang Balancing hanya untuk siklus CLOSED.', PaBalancingException::SIKLUS_BELUM_DITUTUP);
        }

        return DB::transaction(function () use ($cycleId, $olehUserId) {
            /* Semua penjaga dan bacaan DI DALAM kunci siklus. Di luar kunci, klik ganda (atau dua
               admin) membuat panggilan kedua menunggu lalu mengulang seluruhnya: kurva dibuka dan
               dikunci lagi, dan jejak bernomor kedua tertulis untuk sesi yang sama. */
            DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->lockForUpdate()->first(['Id_PA_Cycle']);

            $terbuka = $this->penjaga->sesiTerbuka($cycleId);

            if ($terbuka->isNotEmpty()) {
                throw new PaBalancingException(
                    'Masih ada sesi terbuka: '.$terbuka->pluck('Nama_Sesi')->implode(', ').'. Finalkan atau batalkan lebih dulu.',
                    PaBalancingException::SESI_MASIH_TERBUKA,
                );
            }

            $sesi = $this->penjaga->sesiFinalBelumDihitung($cycleId);

            if ($sesi->isEmpty()) {
                throw new PaBalancingException('Tidak ada sesi FINAL yang menunggu hitung ulang.', PaBalancingException::TIDAK_ADA_SESI_FINAL);
            }

            $pakaiKurva = $this->kurva->metode($cycleId) === 'KURVA';

            if ($pakaiKurva && $this->penjaga->jumlahSudahDibaca($cycleId) > 0) {
                throw new PaBalancingException(
                    'Hitung ulang mengunci ulang kurva seluruh grup, padahal sebagian hasil siklus ini sudah terbit atau dirilis ke leader. '
                    .'Cabut rilis leader lebih dulu; hasil yang sudah terbit tidak bisa dihitung ulang.',
                    PaBalancingException::SUDAH_ADA_YANG_TERBIT,
                );
            }

            $sesiIds = $sesi->pluck('Id_PA_Balancing_Sesi')->map(fn ($x) => (int) $x)->all();
            // Peserta → sesi terbaru yang memuatnya (urut Final_At naik, yang terakhir menang).
            $sesiPeserta = DB::table('N_PA_Balancing_Peserta as bp')
                ->join('N_PA_Balancing_Sesi as s', 's.Id_PA_Balancing_Sesi', '=', 'bp.PA_Balancing_Sesi_Id')
                ->whereIn('bp.PA_Balancing_Sesi_Id', $sesiIds)->orderBy('s.Final_At')->orderBy('s.Id_PA_Balancing_Sesi')
                ->get(['bp.PA_Participant_Id', 'bp.PA_Balancing_Sesi_Id'])
                ->mapWithKeys(fn ($r) => [(int) $r->PA_Participant_Id => (int) $r->PA_Balancing_Sesi_Id])->all();

            $jam = FormatTanggalHelper::getCurrentTime();
            $sebelum = $this->potret->siklus($cycleId);
            $dikalibrasi = $this->lepasKalibrasiForum($cycleId);

            if ($pakaiKurva && $this->kurva->terkunci($cycleId)) {
                $this->kurva->buka($cycleId, 'Hitung ulang Balancing', $olehUserId);
            }

            /* Yang Kalibrasi Forum-nya dilepas ikut dihitung ulang: kotak HAV pilihan forum hanya
               hilang bila hasilnya dihitung dari angkanya sendiri. */
            $dihitung = array_values(array_unique([...array_keys($sesiPeserta), ...$dikalibrasi->pluck('PA_Participant_Id')->map(fn ($x) => (int) $x)->all()]));

            foreach ($dihitung as $pid) {
                $h = $this->perhitungan->hitung($pid, $olehUserId, PaResultHistoryService::BALANCING);

                if (($h['status'] ?? null) !== 'DIHITUNG') {
                    $nama = $sebelum[$pid]->Nama ?? "#{$pid}";
                    throw new PaBalancingException(
                        "{$nama} menjadi TERTAHAN saat dihitung ulang: {$h['alasan_tertahan']}. Hitung ulang dibatalkan seluruhnya.",
                        PaBalancingException::HITUNG_ULANG_TERTAHAN,
                    );
                }
            }

            if ($pakaiKurva) {
                /* Kunci ulang atas keputusan yang sama dengan kunci sebelumnya: hasil yang belum
                   dihitung/tertahan sudah diterima saat kurva pertama dikunci, jadi alasannya
                   diberikan di sini, bukan ditanyakan lagi di tengah transaksi. */
                $this->kurva->kunci($cycleId, $olehUserId, 'Kunci ulang otomatis sesudah hitung ulang Balancing.');
            }

            $this->catatPelepasanKalibrasi($dikalibrasi, $olehUserId, $jam);
            $kualitatifDilewati = $this->tulisKualitatif(array_keys($sesiPeserta), $olehUserId);

            $sesudah = $this->potret->siklus($cycleId);
            $nomor = (int) DB::table('N_PA_Balancing_Jejak_Hitung')->where('PA_Cycle_Id', $cycleId)->max('Nomor_Hitung_Ulang') + 1;
            $jejak = $this->jejak($cycleId, $nomor, $sebelum, $sesudah, $sesiPeserta, $pakaiKurva, $olehUserId);

            foreach (array_chunk($jejak, 100) as $potongan) {
                DB::table('N_PA_Balancing_Jejak_Hitung')->insert($potongan);
            }

            DB::table('N_PA_Balancing_Sesi')->whereIn('Id_PA_Balancing_Sesi', $sesiIds)->update([
                'Nomor_Hitung_Ulang' => $nomor, 'Hitung_Ulang_At' => $jam, 'Hitung_Ulang_By' => $olehUserId,
                'Updated_At' => $jam, 'Updated_By' => $olehUserId,
            ]);

            return [
                'nomor' => $nomor,
                'sesi' => $sesiIds,
                'dihitung' => count($dihitung),
                'kalibrasi_dilepas' => $dikalibrasi->count(),
                'berubah' => count(array_filter($jejak, fn ($j) => $j['Sebab'] !== PaBalancingStatus::SEBAB_TANPA_PERUBAHAN)),
                // Kesimpulan yang diubah admin sesudah sesi dibuat — tidak ditimpa; diputuskan manual.
                'kualitatif_dilewati' => $kualitatifDilewati,
            ];
        });
    }

    /**
     * T1: Kalibrasi Forum lama dilepas pada hitung ulang Balancing. Riwayatnya ditulis SESUDAH
     * hitung ulang (`catatPelepasanKalibrasi`), supaya grade dan kotak HAV sesudahnya nyata.
     *
     * HASIL YANG SUDAH DIBACA TIDAK DISENTUH (koreksi 8 Okt 2026). Pada siklus KURVA penjaga di
     * `jalankan()` sudah menolak seluruh hitung ulang bila ada satu saja yang terbit/dirilis.
     * Siklus PITA tidak punya penjaga itu — grade tiap orang berdiri sendiri — sehingga dulu
     * kalibrasi hasil yang sudah TERBIT ikut dilepas, lalu `hitung()` menolaknya dan seluruh
     * hitung ulang batal. Aturan "terbit atau dirilis = sudah dibaca" kini dipakai di sini juga:
     * keputusan forum atas hasil yang sudah dibaca orang tetap berlaku.
     */
    private function lepasKalibrasiForum(int $cycleId): Collection
    {
        $baris = $this->penjaga->kalibrasiForumBelumDibaca($cycleId);

        if ($baris->isNotEmpty()) {
            DB::table('N_PA_Result')->whereIn('Id_PA_Result', $baris->pluck('Id_PA_Result'))->update([
                'Flag_Dikalibrasi' => 'T', 'Kode_Grade_Final' => DB::raw('Kode_Grade_Sistem'),
            ]);
        }

        return $baris;
    }

    private function catatPelepasanKalibrasi(Collection $dilepas, ?int $olehUserId, string $jam): void
    {
        foreach ($dilepas as $r) {
            $kini = DB::table('N_PA_Result')->where('Id_PA_Result', $r->Id_PA_Result)->first(['Kode_Grade_Sistem', 'PA_Hav_Quadrant_Id']);

            DB::table('N_PA_Calibration_History')->insert([
                'PA_Result_Id' => (int) $r->Id_PA_Result,
                'Kode_Grade_Sebelum' => $r->Kode_Grade_Final,
                // CK_PA_Calibration_History_Pelaku: tanpa pelaku, grade sesudah wajib kosong.
                'Kode_Grade_Sesudah' => $olehUserId === null ? null : $kini->Kode_Grade_Sistem,
                'PA_Hav_Quadrant_Sebelum_Id' => $r->PA_Hav_Quadrant_Id,
                'PA_Hav_Quadrant_Sesudah_Id' => $kini->PA_Hav_Quadrant_Id,
                'Alasan' => 'Kalibrasi Forum dilepas: siklus ini memakai Balancing, dan grade kini hanya bergeser lewat skor kompetensi dan kurva.',
                'Performed_By' => $olehUserId,
                'Performed_By_Nama' => $olehUserId === null ? 'Sistem (hitung ulang Balancing)' : null,
                'Performed_At' => $jam,
            ]);
        }
    }

    /**
     * Kualitatif After sesi FINAL terbaru menjadi catatan admin, hanya bila berbeda dari Before.
     *
     * TIDAK MENIMPA SUNTINGAN ADMIN YANG LEBIH BARU (koreksi 7 Okt 2026). Before adalah potret
     * kesimpulan admin saat sesi DIBUAT. Bila admin mengubah kesimpulan itu sesudahnya, After
     * forum disusun dari isi lama — menimpanya menghapus suntingan admin tanpa jejak. Yang
     * seperti itu dilewati dan dilaporkan supaya diputuskan manual.
     *
     * @return list<array{participant_id:int, nama:string, kompetensi:string}>
     */
    private function tulisKualitatif(array $pids, ?int $olehUserId): array
    {
        $dilewati = [];

        foreach ($pids as $pid) {
            $resultId = (int) DB::table('N_PA_Result')->where('PA_Participant_Id', $pid)->where('Flag_Aktif', 'Y')->value('Id_PA_Result');

            foreach ($this->nilaiBerlaku->kualitatifBerlaku($pid) as $cid => $isi) {
                if ((string) $isi['sebelum'] === (string) $isi['sesudah']) {
                    continue;
                }

                // Dinormalkan persis seperti potret Before dibuat (PaBalancingSesiService).
                $kini = $this->teksKaya->bersihkan(DB::table('N_PA_Result_Note')->where('PA_Result_Id', $resultId)
                    ->where('PA_Competency_Id', $cid)->whereNull('PA_Assignment_Id')->where('Flag_Aktif', 'Y')
                    ->orderBy('Urutan_Tampil')->pluck('Isi')
                    ->map(fn ($n) => $this->teksKaya->dariEsaiHcLearn((string) $n))->implode(''));

                if ($kini !== (string) ($isi['sebelum'] ?? '')) {
                    $dilewati[] = [
                        'participant_id' => (int) $pid,
                        'nama' => (string) DB::table('N_PA_Participant')->where('Id_PA_Participant', $pid)->value('Nama_Karyawan_Saat_Cycle'),
                        'kompetensi' => (string) DB::table('N_PA_Competency')->where('Id_PA_Competency', $cid)->value('Nama_Competency'),
                    ];

                    continue;
                }

                DB::table('N_PA_Result_Note')->where('PA_Result_Id', $resultId)->where('PA_Competency_Id', $cid)
                    ->whereNull('PA_Assignment_Id')->where('Flag_Aktif', 'Y')
                    ->update(['Flag_Aktif' => 'T', 'Updated_At' => now(), 'Updated_By' => $olehUserId]);

                if (($isi['sesudah'] ?? '') !== '') {
                    DB::table('N_PA_Result_Note')->insert([
                        'PA_Result_Id' => $resultId, 'PA_Competency_Id' => $cid, 'PA_Assignment_Id' => null,
                        'Isi' => $isi['sesudah'], 'Urutan_Tampil' => 1, 'Flag_Aktif' => 'Y', 'Created_By' => $olehUserId,
                    ]);
                }
            }
        }

        return $dilewati;
    }

    /** @return list<array<string, mixed>> */
    private function jejak(int $cycleId, int $nomor, array $sebelum, array $sesudah, array $sesiPeserta, bool $pakaiKurva, ?int $olehUserId): array
    {
        $baris = [];

        /* Pita beku kurva BARU dimuat sekali untuk seluruh siklus. `gradeBeku()` per orang
           berarti dua kueri per peserta — ratusan perjalanan selagi baris siklus terkunci. */
        $pitaBeku = $pakaiKurva ? $this->kurva->pitaBekuPerPeserta($cycleId) : [];

        foreach ($sesudah as $pid => $s) {
            $b = $sebelum[$pid] ?? null;
            $finalLama = $b?->Final_Score;
            $gradeTengah = ($pakaiKurva && $finalLama !== null)
                ? (isset($pitaBeku[$pid]) ? PaKurvaStatistik::gradeUntuk($pitaBeku[$pid], $finalLama) : null)
                : $b?->Kode_Grade_Final;
            $sebab = PaBalancingKalkulator::sebab($finalLama, $s->Final_Score, $b?->Kode_Grade_Final, $gradeTengah, $s->Kode_Grade_Final);
            $diSesi = isset($sesiPeserta[$pid]);

            if ($sebab === null && ! $diSesi) {
                continue;
            }

            $baris[] = [
                'PA_Cycle_Id' => $cycleId, 'Nomor_Hitung_Ulang' => $nomor, 'PA_Participant_Id' => $pid,
                'PA_Balancing_Sesi_Id' => $sesiPeserta[$pid] ?? null,
                'Nilai_Kompetensi_Sebelum' => $b?->Nilai_Kompetensi, 'Nilai_Kompetensi_Sesudah' => $s->Nilai_Kompetensi,
                'Final_Score_Sebelum' => $finalLama, 'Final_Score_Sesudah' => $s->Final_Score,
                'Kode_Grade_Sebelum' => $b?->Kode_Grade_Final, 'Kode_Grade_Sesudah' => $s->Kode_Grade_Final,
                'PA_Hav_Quadrant_Sebelum_Id' => $b?->PA_Hav_Quadrant_Id, 'PA_Hav_Quadrant_Sesudah_Id' => $s->PA_Hav_Quadrant_Id,
                'Sebab' => $sebab ?? PaBalancingStatus::SEBAB_TANPA_PERUBAHAN,
                'Created_By' => $olehUserId,
            ];
        }

        return $baris;
    }
}
