<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Aturan kelayakan peserta berdasarkan masa kerja (DDL-2026-191).
 *
 * Berlapis dua seperti setelan PA lain: master satu baris (`N_PA_Eligibility_Setting`)
 * menjadi cetak biru, dan tiap siklus menyimpan salinannya sendiri
 * (`N_PA_Cycle.Min_Masa_Kerja_Bulan`) yang diambil saat siklus dibuat. Mengubah master
 * tidak menyentuh siklus yang sudah ada.
 *
 * Salinan siklus hanya boleh diubah SEBELUM peserta pertama kali dibentuk. Sesudah itu
 * angkanya dibekukan: penyiapan ulang tidak menilai ulang peserta yang sudah ada, jadi
 * angka yang berubah sesudahnya akan tampil di layar tanpa pernah berlaku bagi mereka.
 *
 * Rumusnya (`alasanTidakLayak`) satu-satunya tempat keputusan ini dibuat; penyiapan,
 * pratinjau, dan revisi peserta memanggilnya lewat `PaCyclePreparationService`.
 */
class PaKelayakanMasaKerjaService
{
    /** Dipakai bila master belum pernah disimpan. */
    public const BAWAAN_BULAN = 3;

    public const MAKS_BULAN = 24;

    private const ID_MASTER = 1;

    public function __construct(
        private readonly PaCycleGate $gate,
    ) {}

    /**
     * Sebab karyawan TIDAK layak karena masa kerjanya, atau null bila layak / aturan tidak berlaku.
     *
     * Masa kerja diukur dari tanggal masuk sampai akhir periode yang dinilai. Karyawan yang
     * masuk SESUDAH periode berakhir selalu tidak layak — bahkan pada minimal 0 bulan —
     * karena tidak ada satu hari pun dari periode itu yang ia jalani.
     *
     * Kalimatnya dipotong di " pada " oleh pratinjau untuk mengelompokkan sebab, jadi
     * tanggal selalu ditaruh sesudah kata itu.
     */
    public static function alasanTidakLayak(?string $tglMasuk, ?string $akhirPeriode, ?int $minBulan): ?string
    {
        if ($minBulan === null || $tglMasuk === null || $akhirPeriode === null
            || trim($tglMasuk) === '' || trim($akhirPeriode) === '') {
            return null;
        }

        $masuk = substr($tglMasuk, 0, 10);
        $akhir = substr($akhirPeriode, 0, 10);

        if ($masuk > $akhir) {
            return "Bergabung setelah periode penilaian berakhir pada {$akhir} (masuk {$masuk}).";
        }

        if ($minBulan <= 0) {
            return null;
        }

        /* Genap N bulan: masuk 1 April dengan minimal 3 bulan genap pada 30 Juni (akhir hari),
           masuk 2 April belum. Tanpa luapan akhir bulan: 31 Januari + 1 bulan = 28/29 Februari. */
        $genap = Carbon::parse($masuk)->addMonthsNoOverflow($minBulan)->toDateString();
        $sesudahAkhir = Carbon::parse($akhir)->addDay()->toDateString();

        if ($genap > $sesudahAkhir) {
            return "Masa kerja kurang dari {$minBulan} bulan pada akhir periode {$akhir} (masuk {$masuk}).";
        }

        return null;
    }

    /** Nilai master yang berlaku sekarang. */
    public function nilaiMaster(): int
    {
        $n = DB::table('N_PA_Eligibility_Setting')
            ->where('Id_PA_Eligibility_Setting', self::ID_MASTER)
            ->value('Min_Masa_Kerja_Bulan');

        return $n === null ? self::BAWAAN_BULAN : (int) $n;
    }

    /** @return array{min_masa_kerja_bulan:int, maks_bulan:int, tersimpan:bool, updated_at:?string} */
    public function master(): array
    {
        $baris = DB::table('N_PA_Eligibility_Setting')
            ->where('Id_PA_Eligibility_Setting', self::ID_MASTER)
            ->first(['Min_Masa_Kerja_Bulan', 'Created_At', 'Updated_At']);

        return [
            'min_masa_kerja_bulan' => $baris === null ? self::BAWAAN_BULAN : (int) $baris->Min_Masa_Kerja_Bulan,
            'maks_bulan' => self::MAKS_BULAN,
            'tersimpan' => $baris !== null,
            'updated_at' => $baris === null ? null : (string) ($baris->Updated_At ?? $baris->Created_At),
        ];
    }

    /** @return array{min_masa_kerja_bulan:int, maks_bulan:int, tersimpan:bool, updated_at:?string} */
    public function simpanMaster(int $bulan, ?int $olehUserId): array
    {
        $this->pastikanRentang($bulan);
        $kini = FormatTanggalHelper::getCurrentTime();

        DB::transaction(function () use ($bulan, $olehUserId, $kini) {
            /* Dikunci dulu supaya dua admin yang menyimpan bersamaan pada master yang belum
               berbaris tidak sama-sama menyisipkan lalu salah satunya gagal karena kunci utama. */
            $ada = DB::table('N_PA_Eligibility_Setting')
                ->where('Id_PA_Eligibility_Setting', self::ID_MASTER)
                ->lockForUpdate()
                ->exists();

            if ($ada) {
                DB::table('N_PA_Eligibility_Setting')
                    ->where('Id_PA_Eligibility_Setting', self::ID_MASTER)
                    ->update(['Min_Masa_Kerja_Bulan' => $bulan, 'Updated_At' => $kini, 'Updated_By' => $olehUserId]);
            } else {
                DB::table('N_PA_Eligibility_Setting')->insert([
                    'Id_PA_Eligibility_Setting' => self::ID_MASTER,
                    'Min_Masa_Kerja_Bulan' => $bulan,
                    'Created_At' => $kini,
                    'Created_By' => $olehUserId,
                ]);
            }
        });

        return $this->master();
    }

    /**
     * Salinan siklus beserta apakah masih boleh diubah.
     *
     * @return array{min_masa_kerja_bulan:?int, nilai_master:int, maks_bulan:int, boleh_ubah:bool, alasan_terkunci:?string}
     */
    public function untukSiklus(int $cycleId): array
    {
        $siklus = $this->siklus($cycleId);
        $alasan = $this->alasanTerkunci($cycleId);

        if ($alasan !== null && $siklus->Min_Masa_Kerja_Bulan === null) {
            $alasan = 'Siklus ini dibuat sebelum aturan masa kerja ada dan pesertanya sudah dibentuk tanpa aturan ini.';
        }

        return [
            'min_masa_kerja_bulan' => $siklus->Min_Masa_Kerja_Bulan === null ? null : (int) $siklus->Min_Masa_Kerja_Bulan,
            'nilai_master' => $this->nilaiMaster(),
            'maks_bulan' => self::MAKS_BULAN,
            'boleh_ubah' => $alasan === null,
            'alasan_terkunci' => $alasan,
        ];
    }

    /** @return array{min_masa_kerja_bulan:?int, nilai_master:int, maks_bulan:int, boleh_ubah:bool, alasan_terkunci:?string} */
    public function simpanSiklus(int $cycleId, int $bulan, ?int $olehUserId): array
    {
        $this->pastikanRentang($bulan);
        $this->gate->pastikanBoleh(PaCycleGate::SUNTING_SALINAN, $cycleId);

        DB::transaction(function () use ($cycleId, $bulan, $olehUserId) {
            /* Penyiapan mengunci baris yang sama sepanjang transaksinya. Diperiksa SESUDAH
               kunci: penyiapan yang baru selesai sudah meninggalkan peserta, dan perubahan
               ini ditolak alih-alih tercatat tanpa pernah berlaku. */
            $this->siklus($cycleId, true);

            if (($alasan = $this->alasanTerkunci($cycleId)) !== null) {
                throw new RuntimeException($alasan);
            }

            DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->update([
                'Min_Masa_Kerja_Bulan' => $bulan,
                'Updated_At' => FormatTanggalHelper::getCurrentTime(),
                'Updated_By' => $olehUserId,
            ]);
        });

        return $this->untukSiklus($cycleId);
    }

    private function alasanTerkunci(int $cycleId): ?string
    {
        if (! $this->gate->boleh(PaCycleGate::SUNTING_SALINAN, $cycleId)) {
            return 'Siklus sudah dikunci; aturan kelayakannya ikut membeku.';
        }

        $adaPeserta = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->exists();

        return $adaPeserta
            ? 'Peserta sudah dibentuk dengan aturan ini. Untuk orang tertentu, ubah status pesertanya satu per satu.'
            : null;
    }

    private function siklus(int $cycleId, bool $kunci = false): object
    {
        $q = DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId);
        if ($kunci) {
            $q->lockForUpdate();
        }
        $siklus = $q->first(['Id_PA_Cycle', 'Min_Masa_Kerja_Bulan']);

        if ($siklus === null) {
            throw new RuntimeException("Siklus {$cycleId} tidak ditemukan.");
        }

        return $siklus;
    }

    private function pastikanRentang(int $bulan): void
    {
        if ($bulan < 0 || $bulan > self::MAKS_BULAN) {
            throw new RuntimeException('Minimal masa kerja harus antara 0 dan '.self::MAKS_BULAN.' bulan.');
        }
    }
}
