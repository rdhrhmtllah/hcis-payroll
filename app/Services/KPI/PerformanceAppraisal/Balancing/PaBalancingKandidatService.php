<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\PaSkorEfektif;
use Illuminate\Support\Facades\DB;

/**
 * Kandidat peserta sesi Balancing (B6). Kandidat yang tidak layak TETAP dikembalikan beserta
 * alasannya: layar menampilkannya abu-abu, jadi admin tahu kenapa seseorang tidak bisa dipilih.
 */
class PaBalancingKandidatService
{
    /** @return list<array<string, mixed>> */
    public function daftar(int $cycleId, array $saringan = []): array
    {
        $q = $this->kueri($cycleId);

        foreach ([
            'level_ids' => 'p.ID_Level_Saat_Cycle', 'divisi_ids' => 'p.ID_Divisi_Saat_Cycle',
            'sub_divisi_ids' => 'p.ID_Sub_Divisi_Saat_Cycle', 'grup_kurva_ids' => 'gl.PA_Cycle_Curve_Group_Id',
            'grade' => 'r.Kode_Grade_Final', 'kuadran_ids' => 'r.PA_Hav_Quadrant_Id',
        ] as $kunci => $kolom) {
            if (! empty($saringan[$kunci])) {
                $q->whereIn($kolom, (array) $saringan[$kunci]);
            }
        }

        if (($saringan['cari'] ?? '') !== '') {
            $cari = '%'.trim((string) $saringan['cari']).'%';
            $q->where(fn ($w) => $w->where('p.Nama_Karyawan_Saat_Cycle', 'like', $cari)->orWhere('p.Kode_Karyawan', 'like', $cari));
        }

        $baris = $q->orderBy('p.Nama_Karyawan_Saat_Cycle')->get();
        [$terbuka, $jumlah, $menunggu] = $this->petaSesi($cycleId);
        $berkesimpulan = $this->berkesimpulan($baris->pluck('Id_PA_Result')->filter()->map(fn ($v) => (int) $v)->all());
        $berjawaban = $this->berjawabanKualitatif($baris->pluck('Id_PA_Participant')->map(fn ($v) => (int) $v)->all());

        $hasil = [];

        foreach ($baris as $r) {
            $pid = (int) $r->Id_PA_Participant;
            $jumlahSesi = $jumlah[$pid] ?? 0;

            if (array_key_exists('jumlah_sesi', $saringan) && $saringan['jumlah_sesi'] !== null && $jumlahSesi !== (int) $saringan['jumlah_sesi']) {
                continue;
            }

            $alasan = $this->alasan($r, $terbuka[$pid] ?? null, $menunggu[$pid] ?? null);

            $hasil[] = [
                'participant_id' => $pid,
                'nama' => (string) $r->Nama_Karyawan_Saat_Cycle,
                'kode_perusahaan' => (string) $r->Kode_Perusahaan,
                'kode_karyawan' => (string) $r->Kode_Karyawan,
                'level_id' => $r->ID_Level_Saat_Cycle === null ? null : (int) $r->ID_Level_Saat_Cycle,
                'level' => $r->Nama_Level_Saat_Cycle,
                'jabatan' => $r->Nama_Jabatan_Saat_Cycle,
                'divisi_id' => $r->ID_Divisi_Saat_Cycle === null ? null : (int) $r->ID_Divisi_Saat_Cycle,
                'divisi' => $r->Nama_Divisi_Saat_Cycle,
                'sub_divisi_id' => $r->ID_Sub_Divisi_Saat_Cycle === null ? null : (int) $r->ID_Sub_Divisi_Saat_Cycle,
                'sub_divisi' => $r->Nama_Sub_Divisi_Saat_Cycle,
                'grup_kurva_id' => $r->grup_id === null ? null : (int) $r->grup_id,
                'grup_kurva' => $r->Nama_Grup,
                'grade' => $r->Kode_Grade_Final,
                'kuadran_id' => $r->PA_Hav_Quadrant_Id === null ? null : (int) $r->PA_Hav_Quadrant_Id,
                'kode_kuadran' => $r->Kode_Kuadran,
                'nama_kuadran' => $r->Nama_Kuadran,
                'nomor_box' => $r->Nomor_Box === null ? null : (int) $r->Nomor_Box,
                'final' => $r->Final_Score === null ? null : (float) $r->Final_Score,
                'jumlah_sesi' => $jumlahSesi,
                'layak' => $alasan === null,
                'alasan' => $alasan,
                /* Isi awal kualitatif board diambil dari kesimpulan admin (T3); tanpa itu forum
                   mulai dari kotak kosong. Hanya untuk orang yang MEMANG punya jawaban
                   Strength/Development Area — yang tidak punya jawaban tidak perlu disimpulkan.
                   Hanya peringatan, tidak mengubah kelayakan. */
                'perlu_kesimpulan' => isset($berjawaban[$pid])
                    && ! ($r->Id_PA_Result !== null && isset($berkesimpulan[(int) $r->Id_PA_Result])),
            ];
        }

        return $hasil;
    }

    /** @return list<array{participant_id:int, nama:string, alasan:string}> */
    public function alasanTidakLayak(int $cycleId, array $participantIds): array
    {
        $baris = $this->kueri($cycleId)->whereIn('p.Id_PA_Participant', $participantIds)->get()->keyBy('Id_PA_Participant');
        [$terbuka, , $menunggu] = $this->petaSesi($cycleId);
        $tolak = [];

        foreach ($participantIds as $pid) {
            $r = $baris[$pid] ?? null;

            if ($r === null) {
                $tolak[] = ['participant_id' => (int) $pid, 'nama' => "#{$pid}", 'alasan' => 'Bukan peserta siklus ini'];

                continue;
            }

            $alasan = $this->alasan($r, $terbuka[(int) $pid] ?? null, $menunggu[(int) $pid] ?? null);

            if ($alasan !== null) {
                $tolak[] = ['participant_id' => (int) $pid, 'nama' => (string) $r->Nama_Karyawan_Saat_Cycle, 'alasan' => $alasan];
            }
        }

        return $tolak;
    }

    private function kueri(int $cycleId)
    {
        return DB::table('N_PA_Participant as p')
            ->leftJoin('N_PA_Result as r', fn ($j) => $j->on('r.PA_Participant_Id', '=', 'p.Id_PA_Participant')->where('r.Flag_Aktif', 'Y'))
            ->leftJoin('N_PA_Hav_Quadrant as q', 'q.Id_PA_Hav_Quadrant', '=', 'r.PA_Hav_Quadrant_Id')
            ->leftJoin('N_PA_Cycle_Curve_Group_Level as gl', function ($j) {
                $j->on('gl.PA_Cycle_Id', '=', 'p.PA_Cycle_Id')->on('gl.ID_Level', '=', 'p.ID_Level_Saat_Cycle')->where('gl.Flag_Aktif', 'Y');
            })
            ->leftJoin('N_PA_Cycle_Curve_Group as g', 'g.Id_PA_Cycle_Curve_Group', '=', 'gl.PA_Cycle_Curve_Group_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('p.Status_Peserta', 'DIIKUTKAN')->where('p.Flag_Aktif', 'Y')
            ->select([
                'p.Id_PA_Participant', 'p.Kode_Perusahaan', 'p.Kode_Karyawan', 'p.Nama_Karyawan_Saat_Cycle', 'p.ID_Level_Saat_Cycle',
                'p.Nama_Level_Saat_Cycle', 'p.Nama_Jabatan_Saat_Cycle', 'p.ID_Divisi_Saat_Cycle', 'p.Nama_Divisi_Saat_Cycle',
                'p.ID_Sub_Divisi_Saat_Cycle', 'p.Nama_Sub_Divisi_Saat_Cycle', 'gl.PA_Cycle_Curve_Group_Id as grup_id',
                'g.Nama_Grup', 'r.Id_PA_Result', 'r.Status_Hasil', 'r.Published_Leader_At', 'r.Kode_Grade_Final', 'r.PA_Hav_Quadrant_Id',
                'q.Kode_Kuadran', 'q.Nama_Kuadran', 'q.Nomor_Box', 'r.Final_Score',
            ]);
    }

    /** @return array<int, true> peserta yang punya jawaban penilai untuk kualitatif Balancing */
    private function berjawabanKualitatif(array $participantIds): array
    {
        $hasil = [];

        foreach (array_chunk($participantIds, 1000) as $potongan) {
            DB::table('N_PA_Assignment as a')
                ->join('N_PA_Assignment_Integration as i', fn ($j) => $j->on('i.PA_Assignment_Id', '=', 'a.Id_PA_Assignment')->where('i.Flag_Aktif', 'Y'))
                ->join('N_PA_Assignment_Payload as pl', 'pl.PA_Assignment_Integration_Id', '=', 'i.Id_PA_Assignment_Integration')
                ->join('N_PA_Assignment_Score as sc', fn ($j) => $j->on('sc.PA_Assignment_Payload_Id', '=', 'pl.Id_PA_Assignment_Payload')->where('sc.Flag_Aktif', 'Y'))
                ->join('N_PA_Competency as k', 'k.Id_PA_Competency', '=', 'sc.PA_Competency_Id')
                ->whereIn('a.PA_Participant_Id', $potongan)->where('a.Flag_Aktif', 'Y')
                ->whereIn('k.Kode_Competency', PaBalancingSesiService::KUALITATIF_BALANCING)
                ->whereRaw(PaSkorEfektif::sqlAdaJawaban('sc'))
                ->distinct()->pluck('a.PA_Participant_Id')
                ->each(function ($id) use (&$hasil) { $hasil[(int) $id] = true; });
        }

        return $hasil;
    }

    /** @return array<int, true> hasil yang punya kesimpulan admin aktif untuk kualitatif Balancing */
    private function berkesimpulan(array $resultIds): array
    {
        $hasil = [];

        foreach (array_chunk($resultIds, 1000) as $potongan) {
            DB::table('N_PA_Result_Note as n')
                ->join('N_PA_Competency as k', 'k.Id_PA_Competency', '=', 'n.PA_Competency_Id')
                ->whereIn('n.PA_Result_Id', $potongan)->where('n.Flag_Aktif', 'Y')->whereNull('n.PA_Assignment_Id')
                ->whereIn('k.Kode_Competency', PaBalancingSesiService::KUALITATIF_BALANCING)
                ->distinct()->pluck('n.PA_Result_Id')
                ->each(function ($id) use (&$hasil) { $hasil[(int) $id] = true; });
        }

        return $hasil;
    }

    /** @return array{0: array<int, string>, 1: array<int, int>, 2: array<int, string>} [sesi terbuka, jumlah sesi FINAL, sesi FINAL menunggu hitung ulang] per peserta */
    private function petaSesi(int $cycleId): array
    {
        $baris = DB::table('N_PA_Balancing_Peserta as bp')
            ->join('N_PA_Balancing_Sesi as s', 's.Id_PA_Balancing_Sesi', '=', 'bp.PA_Balancing_Sesi_Id')
            ->where('s.PA_Cycle_Id', $cycleId)
            ->whereIn('s.Status_Sesi', [...PaBalancingStatus::SESI_BISA_DIUBAH, PaBalancingStatus::FINAL])
            ->get(['bp.PA_Participant_Id', 's.Nama_Sesi', 's.Status_Sesi', 's.Hitung_Ulang_At']);

        $terbuka = [];
        $jumlah = [];
        $menunggu = [];

        foreach ($baris as $b) {
            $pid = (int) $b->PA_Participant_Id;

            if ($b->Status_Sesi === PaBalancingStatus::FINAL) {
                $jumlah[$pid] = ($jumlah[$pid] ?? 0) + 1;

                if ($b->Hitung_Ulang_At === null) {
                    $menunggu[$pid] = (string) $b->Nama_Sesi;
                }
            } else {
                $terbuka[$pid] = (string) $b->Nama_Sesi;
            }
        }

        return [$terbuka, $jumlah, $menunggu];
    }

    private function alasan(object $r, ?string $sesiTerbuka, ?string $sesiMenunggu = null): ?string
    {
        if ($r->Status_Hasil === 'TERBIT') {
            return 'Hasil sudah terbit ke karyawan';
        }

        if ($r->Status_Hasil !== 'DIHITUNG') {
            return 'Hasil belum DIHITUNG (status '.($r->Status_Hasil ?? 'tanpa hasil').')';
        }

        if ($r->Published_Leader_At !== null) {
            return 'Hasil sudah dirilis ke leader';
        }

        if ($sesiTerbuka !== null) {
            return "Sedang dibahas di sesi \"{$sesiTerbuka}\"";
        }

        /* Sesi FINAL yang belum dihitung ulang: Before sesi baru diambil dari radar, yang baru
           memuat After sesudah hitung ulang. Membolehkannya membuat sesi baru menimpa keputusan
           sesi itu dengan nilai lama tanpa ada yang tahu. */
        return $sesiMenunggu === null ? null : "Menunggu hitung ulang sesi \"{$sesiMenunggu}\"";
    }
}
