<?php

namespace App\Services\KPI;

use Illuminate\Support\Facades\DB;

/**
 * Bulan mulai pengisian parameter KPI untuk SATU karyawan.
 *
 * Dipakai bersama oleh tiga tempat yang harus selalu sepakat:
 *   - FormKpiController saat menyimpan pengajuan tim
 *   - endpoint pratinjau yang dipakai form tim
 *   - audit/pembersih data lama
 *
 * Kalau ketiganya punya salinan aturan sendiri, cepat atau lambat yang dilihat
 * leader berbeda dengan yang tersimpan.
 */
class KpiStartPeriodeResolver
{
    /** @var array<int, bool> ingatan Flag_Perorangan per jadwal, sepanjang satu permintaan. */
    private array $peroranganPerJadwal = [];

    /**
     * Apakah jadwal payungnya sendiri sebuah jadwal perorangan.
     *
     * Sebagian pemanggil menyusun objek jadwalnya dengan select terbatas, jadi
     * Flag_Perorangan bisa tidak ikut terbawa. Dalam hal itu ia dibaca sekali
     * lalu diingat — menanyakannya per anggota membuat biaya daftar tim tumbuh
     * mengikuti jumlah orangnya.
     */
    private function payungPerorangan($jadwal): bool
    {
        if (property_exists($jadwal, 'Flag_Perorangan') || isset($jadwal->Flag_Perorangan)) {
            return ($jadwal->Flag_Perorangan ?? null) === 'Y';
        }

        $id = (int) ($jadwal->Id_Master_Penjadwalan ?? 0);

        if ($id === 0) {
            return false;
        }

        return $this->peroranganPerJadwal[$id] ??= DB::table('KPI_Master_Penjadwalan')
            ->where('Id_Master_Penjadwalan', $id)
            ->value('Flag_Perorangan') === 'Y';
    }

    /**
     * Ubah 'MM', 'M', atau 'YYYY-MM' menjadi 'YYYY-MM' yang bisa dibandingkan
     * sebagai string (bulan selalu dua digit, jadi urutan string = kronologis).
     */
    public function normalkan(?string $periode, $tahun): string
    {
        if (empty($periode)) {
            return $tahun.'-01';
        }

        if (str_contains($periode, '-')) {
            return $periode;
        }

        return $tahun.'-'.str_pad($periode, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Urutan: jadwal perorangan karyawan tsb menimpa jadwal global yang dipilih
     * leader; kalau ia tidak terdaftar, jatuh ke Periode jadwal global, lalu ke
     * bulan 1. Pendaftaran perorangan hanya menentukan BULAN MULAI — bukan syarat
     * boleh-tidaknya ikut pengajuan tim, karena kalau dijadikan syarat seluruh
     * pengajuan tim yang memakai jadwal global akan patah.
     *
     * Jendela tanggal jadwal perorangan sengaja tidak ikut dinilai: kalau ikut,
     * seorang anggota bisa diam-diam jatuh kembali ke bulan 1 hanya karena
     * tanggalnya terlewat, dan itu sulit dilacak.
     *
     * Bila payungnya sendiri adalah jadwal PERORANGAN dan anggota itu tidak
     * punya bulan mulai di sana, hasilnya BUKAN sebuah bulan melainkan
     * pernyataan "tidak tercakup". Jadwal perorangan lazimnya ber-Periode NULL,
     * sehingga jatuh ke sana menghasilkan Januari untuk semua orang di luar
     * rosternya — diam-diam, dan itu pernah benar-benar terjadi (Dhea dan Anita
     * tersimpan mulai 2026-01 lewat jalur ini).
     *
     * @return array{id_penjadwalan: int, mulai: ?string, sumber: string, tercakup: bool}
     */
    public function resolve(?string $kodeKaryawan, $jadwalGlobal): array
    {
        $tahun = $jadwalGlobal->Tahun;

        if (! empty($kodeKaryawan)) {
            $perorangan = DB::table('KPI_Master_Penjadwalan as m')
                ->join(
                    'N_KPI_Master_Penjadwalan_Perorangan as p',
                    'm.Id_Master_Penjadwalan',
                    '=',
                    'p.Id_Master_Penjadwalan',
                )
                ->where('p.Kode_Karyawan', $kodeKaryawan)
                ->where('p.Flag_Aktif', 'Y')
                ->whereNotNull('p.Start_Periode')
                ->where('m.Flag_Aktif', 'Y')
                ->where('m.Flag_Form_KPI', 'Y')
                ->where('m.Flag_Perorangan', 'Y')
                ->where('m.Tahun', $tahun)
                ->orderByDesc('m.Id_Master_Penjadwalan')
                ->select('m.Id_Master_Penjadwalan', 'p.Start_Periode')
                ->first();

            if ($perorangan) {
                return [
                    'id_penjadwalan' => $perorangan->Id_Master_Penjadwalan,
                    'mulai' => $this->normalkan($perorangan->Start_Periode, $tahun),
                    'sumber' => 'perorangan',
                    'tercakup' => true,
                ];
            }
        }

        // Payung perorangan TANPA Periode tidak boleh dijatuhi siapa pun yang
        // bukan pesertanya: normalkan(null) memberi Januari kepada seluruh
        // anggota di luar roster tanpa satu pun tanda — itu yang membuat Dhea
        // dan Anita tersimpan mulai 2026-01 lewat jadwal 57.
        //
        // Yang berbahaya adalah PERIODE KOSONGNYA, bukan sifat perorangannya.
        // Jadwal perorangan yang punya Periode sungguhan (21 dari 26 di data
        // dev) aman dijatuhi: bulannya nyata, bukan karangan dari null.
        if (empty($jadwalGlobal->Periode) && $this->payungPerorangan($jadwalGlobal)) {
            return [
                'id_penjadwalan' => $jadwalGlobal->Id_Master_Penjadwalan,
                'mulai' => null,
                'sumber' => 'tidak_tercakup',
                'tercakup' => false,
            ];
        }

        return [
            'id_penjadwalan' => $jadwalGlobal->Id_Master_Penjadwalan,
            'mulai' => $this->normalkan($jadwalGlobal->Periode, $tahun),
            'sumber' => 'global',
            'tercakup' => true,
        ];
    }
}
