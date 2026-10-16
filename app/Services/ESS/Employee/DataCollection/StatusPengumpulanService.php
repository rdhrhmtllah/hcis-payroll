<?php

namespace App\Services\ESS\Employee\DataCollection;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Baris ringan yang dibaca di SETIAP kunjungan halaman.
 *
 * Sengaja tidak menyentuh tabel submission: yang dibaca berulang harus baris
 * kecil, bukan JSON jawaban. Repo ini punya riwayat "Login timeout expired"
 * akibat koneksi jenuh, jadi jalur ini di-cache dan dibuang saat ada tulisan.
 *
 * Tidak bergantung pada KonfigurasiKampanye: penjagaan sakelar milik lapisan
 * pemanggil (controller), bukan lapisan data.
 */
class StatusPengumpulanService
{
    public function untuk(string $kodeKaryawan): array
    {
        return Cache::remember(
            $this->kunciCache($kodeKaryawan),
            now()->addMinutes(5),
            fn () => $this->bacaLangsung($kodeKaryawan),
        );
    }

    public function catatTunda(string $kodeKaryawan): array
    {
        $this->pastikanBarisAda($kodeKaryawan);

        $maks = $this->maksPenundaan();

        DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Status', 'BELUM')
            ->where('Jumlah_Tunda', '<', $maks)
            ->update([
                'Jumlah_Tunda' => DB::raw('Jumlah_Tunda + 1'),
                'Terakhir_Tunda_At' => now(),
                'Updated_At' => now(),
            ]);

        // Kunci dipasang sebagai langkah terpisah supaya aturannya satu tempat:
        // status jadi TERKUNCI begitu pencacah menyentuh maksimum.
        DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Status', 'BELUM')
            ->where('Jumlah_Tunda', '>=', $maks)
            ->update(['Status' => 'TERKUNCI', 'Updated_At' => now()]);

        $this->bersihkanCache($kodeKaryawan);

        return $this->untuk($kodeKaryawan);
    }

    public function simpanDraft(string $kodeKaryawan, array $data, int $langkah): void
    {
        $this->pastikanBarisAda($kodeKaryawan);

        DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->whereIn('Status', ['BELUM', 'TERKUNCI'])
            ->update([
                'Draft_Json' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'Draft_Langkah' => $langkah,
                'Draft_Updated_At' => now(),
                'Updated_At' => now(),
            ]);

        $this->bersihkanCache($kodeKaryawan);
    }

    public function ambilDraft(string $kodeKaryawan): array
    {
        $baris = DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->first(['Draft_Json', 'Draft_Langkah']);

        return [
            'data' => json_decode($baris->Draft_Json ?? '{}', true) ?: [],
            'langkah' => (int) ($baris->Draft_Langkah ?? 0),
        ];
    }

    public function tandaiSelesai(string $kodeKaryawan): void
    {
        $this->pastikanBarisAda($kodeKaryawan);

        DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->update([
                'Status' => 'SELESAI',
                'Selesai_At' => now(),
                'Draft_Json' => null,
                'Draft_Langkah' => null,
                'Updated_At' => now(),
            ]);

        $this->bersihkanCache($kodeKaryawan);
    }

    /**
     * Catat bahwa modal sudah ditampilkan.
     *
     * Penandanya hidup di tabel ini, BUKAN di sessionStorage/localStorage.
     * Alasannya: penanda di browser tak terlihat dan tak bisa diurus siapa pun
     * — menghapus baris status tidak benar-benar mereset orangnya, dan admin
     * tak punya cara memaksa modal muncul lagi. Bayarannya satu tulisan per
     * sesi (atau per hari), dan itu murah karena hanya terjadi saat modal
     * benar-benar tampil, bukan tiap render halaman.
     */
    public function catatTampil(string $kodeKaryawan, ?string $sesiId): void
    {
        $this->pastikanBarisAda($kodeKaryawan);

        DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->update([
                'Terakhir_Tampil_At' => now(),
                'Terakhir_Tampil_Sesi' => $sesiId,
                'Updated_At' => now(),
            ]);

        $this->bersihkanCache($kodeKaryawan);
    }

    /**
     * Aturan murni: apakah modal perlu tampil sekarang?
     *
     * Sengaja tanpa database dan tanpa waktu tersembunyi — tanggal dan id sesi
     * masuk sebagai parameter — supaya seluruh kombinasi bisa diuji.
     */
    public function perluTampil(array $status, string $frekuensi, ?string $sesiId, ?string $tanggalHariIni = null): bool
    {
        // Yang terkunci TIDAK ditagih lewat modal lagi: gerbangnya sudah berupa
        // pengalihan halaman (ArahkanKeFormulirPengumpulanData), jadi ia sudah
        // berada di formulirnya dan tak pernah membuka halaman lain. Memaksa
        // modal untuknya cuma menghidupkan kembali layar yang mengurung.
        if (! empty($status['terkunci'])) {
            return false;
        }

        if ($frekuensi === 'HALAMAN') {
            return true;
        }

        if ($frekuensi === 'HARIAN') {
            $hariIni = $tanggalHariIni ?? now()->format('Y-m-d');

            return ($status['terakhir_tampil_tanggal'] ?? null) !== $hariIni;
        }

        // SESI, dan apa pun yang tidak dikenal — gagal ke arah paling tegas
        // yang masih sopan.
        if ($sesiId === null || $sesiId === '') {
            return true;
        }

        return ($status['terakhir_tampil_sesi'] ?? null) !== $sesiId;
    }

    public function bersihkanCache(string $kodeKaryawan): void
    {
        Cache::forget($this->kunciCache($kodeKaryawan));
    }

    private function bacaLangsung(string $kodeKaryawan): array
    {
        $baris = DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->first(['Status', 'Jumlah_Tunda', 'Is_Dikecualikan', 'Draft_Langkah', 'Terakhir_Tampil_At', 'Terakhir_Tampil_Sesi']);

        $status = (string) ($baris->Status ?? 'BELUM');
        $tunda = (int) ($baris->Jumlah_Tunda ?? 0);
        $maks = $this->maksPenundaan();

        return [
            'status' => $status,
            'jumlah_tunda' => $tunda,
            'sisa_tunda' => max(0, $maks - $tunda),
            'terkunci' => $status === 'TERKUNCI',
            'is_dikecualikan' => ($baris->Is_Dikecualikan ?? 'T') === 'Y',
            'draft_langkah' => (int) ($baris->Draft_Langkah ?? 0),
            // Dipotong ke tanggal saja: pembandingnya memang hari, dan menyimpan
            // jamnya di sini cuma mengundang salah banding.
            'terakhir_tampil_tanggal' => $baris?->Terakhir_Tampil_At
                ? substr((string) $baris->Terakhir_Tampil_At, 0, 10)
                : null,
            'terakhir_tampil_sesi' => $baris->Terakhir_Tampil_Sesi ?? null,
        ];
    }

    private function pastikanBarisAda(string $kodeKaryawan): void
    {
        $ada = DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->exists();

        if (! $ada) {
            DB::table('N_HRIS_Pengumpulan_Data_Status')->insert([
                'Kode_Karyawan' => $kodeKaryawan,
                'Status' => 'BELUM',
                'Jumlah_Tunda' => 0,
                'Is_Dikecualikan' => 'T',
                'Created_At' => now(),
            ]);
        }
    }

    private function kunciCache(string $kodeKaryawan): string
    {
        return "pengumpulan-data:status:{$kodeKaryawan}";
    }

    private function maksPenundaan(): int
    {
        return (int) config('pengumpulan_data.maks_penundaan', 3);
    }
}
