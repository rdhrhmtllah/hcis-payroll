<?php

namespace App\Services\ESS\Attendance\Calendar\Operational;

use DateTimeImmutable;
use Throwable;

/**
 * Menentukan tanggal Head Office tidak beroperasi.
 *
 * Kelas ini MURNI: tidak menyentuh database, tidak tahu HTTP, tidak membaca
 * config. Seluruh aturan §4 dan §5 spec hidup di sini dan hanya di sini —
 * lihat docs/superpowers/specs/2026-09-04-api-kalender-operasional-ho-design.md.
 *
 * Pemanggilnya (KalenderOperasionalService) yang mengambil datanya.
 */
class PenentuTanggalTidakOperasional
{
    public const JENIS_LIBUR_NASIONAL = 'LIBUR_NASIONAL';

    public const JENIS_CUTI_BERSAMA = 'CUTI_BERSAMA';

    public const STATUS_TERJADWAL = 'TERJADWAL';

    public const STATUS_BERJALAN = 'BERJALAN';

    public const STATUS_SELESAI = 'SELESAI';

    /**
     * @param  list<array{tanggal:?string, nama?:?string}>  $libur
     * @param  list<array<string,mixed>>  $cutiBersama
     * @param  list<array{kode:?string, cabang?:?string, divisi?:?string, sub_divisi?:?string}>  $karyawanHo
     * @param  array<int, array<string, true>>  $pengecualian  [Id_Cuti_Bersama => [KODE => true]]
     * @return list<array{tanggal:string, jenis:string, nama:string, status?:string}>
     */
    public function tentukan(
        array $libur,
        array $cutiBersama,
        array $karyawanHo,
        array $pengecualian,
        string $dari,
        string $sampai,
        string $hariIni,
    ): array {
        $entri = [];

        // Libur nasional lebih dulu: pada tanggal yang sama ia menang atas cuti
        // bersama, sehingga keluaran tetap "tanpa duplikat" seperti kontraknya.
        foreach ($libur as $baris) {
            $tanggal = $this->tanggalSaja($baris['tanggal'] ?? null);
            if ($tanggal === null || $tanggal < $dari || $tanggal > $sampai) {
                continue;
            }

            $entri[$tanggal] = [
                'tanggal' => $tanggal,
                'jenis' => self::JENIS_LIBUR_NASIONAL,
                'nama' => $this->namaAtau($baris['nama'] ?? null, 'Hari Libur'),
            ];
        }

        // Urutkan menurut id supaya seri pada tanggal yang sama dimenangkan baris
        // terlama — keluaran tidak boleh bergantung urutan baris yang kebetulan
        // dikembalikan SQL Server.
        usort($cutiBersama, fn (array $a, array $b) => ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0)));

        foreach ($cutiBersama as $baris) {
            if (! $this->cutiBersamaBerlaku($baris)) {
                continue;
            }

            if (! $this->menyasarHeadOffice($baris, $karyawanHo, $pengecualian)) {
                continue;
            }

            $nama = $this->namaAtau($baris['nama'] ?? null, 'Cuti Bersama');

            foreach ($this->tanggalCutiBersama($baris) as $tanggal) {
                if ($tanggal < $dari || $tanggal > $sampai || isset($entri[$tanggal])) {
                    continue;
                }

                $entri[$tanggal] = [
                    'tanggal' => $tanggal,
                    'jenis' => self::JENIS_CUTI_BERSAMA,
                    'nama' => $nama,
                    'status' => $this->statusCutiBersama($tanggal, $hariIni),
                ];
            }
        }

        ksort($entri);

        return array_values($entri);
    }

    /**
     * Aktif dan tidak dibatalkan. Flag_Executed SENGAJA tidak diperiksa: ia
     * menandai saldo cuti sudah dipotong, bukan kantor tutup. Cuti bersama yang
     * sudah ditetapkan tapi belum dieksekusi tetap hari libur.
     */
    public function cutiBersamaBerlaku(array $baris): bool
    {
        $aktif = strtoupper(trim((string) ($baris['flag_aktif'] ?? '')));
        $dibatalkan = strtoupper(trim((string) ($baris['flag_cancelled'] ?? '')));

        return $aktif === 'Y' && $dibatalkan !== 'Y';
    }

    /**
     * Selected_Dates dipakai bila terisi; bila kosong barulah rentang
     * Tanggal_Mulai..Tanggal_Selesai dibentangkan.
     *
     * ATURAN YANG SAMA, IMPLEMENTASI KEDUA. Rumah aturan ini adalah
     * App\Services\ESS\Attendance\Leave\Shared\TanggalCutiBersama::dariItem().
     * Metode di sini SENGAJA tidak digabung ke sana, dan alasannya bukan
     * kemalasan melainkan kontrak masukan yang berbeda: di sini `selected_dates`
     * bisa datang sebagai STRING JSON langsung dari kolom basis data, dan
     * uraikanLarik() menanganinya. Rumah bersama itu hanya menerima array yang
     * sudah ter-decode; string JSON akan diabaikan di sana dan hasilnya jatuh ke
     * rentang.
     *
     * Menyatukan keduanya berarti mengubah perilaku salah satu jalur, pada
     * logika kuota cuti yang sudah pernah melahirkan cacat overbooking. Itu
     * keputusan produk, bukan perapian kode - jadi divergensinya ditulis di sini
     * supaya terlihat, bukan diam-diam.
     *
     * @return list<string>
     */
    public function tanggalCutiBersama(array $baris): array
    {
        $terpilih = $this->uraikanLarik($baris['selected_dates'] ?? null);

        if ($terpilih !== []) {
            $tanggal = [];
            foreach ($terpilih as $nilai) {
                $normal = $this->tanggalSaja($nilai);
                if ($normal !== null) {
                    $tanggal[$normal] = true;
                }
            }
            $tanggal = array_keys($tanggal);
            sort($tanggal);

            return $tanggal;
        }

        $mulai = $this->tanggalSaja($baris['tanggal_mulai'] ?? null);
        if ($mulai === null) {
            return [];
        }

        $selesai = $this->tanggalSaja($baris['tanggal_selesai'] ?? null) ?? $mulai;
        if ($mulai > $selesai) {
            [$mulai, $selesai] = [$selesai, $mulai];
        }

        $tanggal = [];
        $kursor = new DateTimeImmutable($mulai);
        $batas = new DateTimeImmutable($selesai);
        while ($kursor <= $batas) {
            $tanggal[] = $kursor->format('Y-m-d');
            $kursor = $kursor->modify('+1 day');
        }

        return $tanggal;
    }

    /**
     * Satu karyawan HO yang tersasar sudah cukup membuat kantor tutup.
     *
     * Aturan sasarannya disalin dari cutiBersamaService::
     * isKaryawanTargetedForQueuedCutiBersama agar tidak ada dua definisi
     * "tersasar" yang bisa berbeda diam-diam.
     */
    public function menyasarHeadOffice(array $baris, array $karyawanHo, array $pengecualian): bool
    {
        $filterKode = $this->normalkanKode($this->uraikanLarik($baris['filter_kode_karyawan'] ?? null));
        $filterCabang = $this->uraikanLarik($baris['filter_cabang'] ?? null);
        $filterDivisi = $this->uraikanLarik($baris['filter_divisi'] ?? null);
        $filterSubDivisi = $this->uraikanLarik($baris['filter_sub_divisi'] ?? null);

        $adaFilterKode = $filterKode !== [];
        $adaFilterUnit = $filterCabang !== [] || $filterDivisi !== [] || $filterSubDivisi !== [];

        $dikecualikan = $pengecualian[(int) ($baris['id'] ?? 0)] ?? [];

        foreach ($karyawanHo as $karyawan) {
            $kode = strtoupper(trim((string) ($karyawan['kode'] ?? '')));
            if ($kode === '' || isset($dikecualikan[$kode])) {
                continue;
            }

            $diKode = in_array($kode, $filterKode, true);

            $cocokCabang = $filterCabang === []
                || in_array(trim((string) ($karyawan['cabang'] ?? '')), $filterCabang, true);
            $cocokDivisi = $filterDivisi === []
                || in_array(trim((string) ($karyawan['divisi'] ?? '')), $filterDivisi, true);
            $cocokSubDivisi = $filterSubDivisi === []
                || in_array(trim((string) ($karyawan['sub_divisi'] ?? '')), $filterSubDivisi, true);

            $diUnit = $adaFilterUnit && $cocokCabang && $cocokDivisi && $cocokSubDivisi;

            if ($diKode || $diUnit || (! $adaFilterKode && ! $adaFilterUnit)) {
                return true;
            }
        }

        return false;
    }

    public function statusCutiBersama(string $tanggal, string $hariIni): string
    {
        if ($tanggal > $hariIni) {
            return self::STATUS_TERJADWAL;
        }

        return $tanggal === $hariIni ? self::STATUS_BERJALAN : self::STATUS_SELESAI;
    }

    /**
     * Kolom filter disimpan sebagai JSON di nvarchar(max), tapi pemanggil lain
     * bisa saja sudah menguraikannya. Terima keduanya.
     *
     * @return list<string>
     */
    private function uraikanLarik(mixed $nilai): array
    {
        if (is_string($nilai)) {
            $nilai = trim($nilai) === '' ? null : json_decode($nilai, true);
        }

        if (! is_array($nilai)) {
            return [];
        }

        $hasil = [];
        foreach ($nilai as $item) {
            if ($item === null || is_array($item)) {
                continue;
            }
            $teks = trim((string) $item);
            if ($teks !== '') {
                $hasil[] = $teks;
            }
        }

        return $hasil;
    }

    /**
     * @param  list<string>  $kode
     * @return list<string>
     */
    private function normalkanKode(array $kode): array
    {
        return array_values(array_map(static fn (string $item) => strtoupper(trim($item)), $kode));
    }

    private function tanggalSaja(mixed $nilai): ?string
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable((string) $nilai))->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function namaAtau(mixed $nama, string $baku): string
    {
        $teks = trim((string) ($nama ?? ''));

        return $teks !== '' ? $teks : $baku;
    }
}
