<?php

namespace App\Services\ESS\Employee\DataCollection;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Baris N_HRIS_Pengumpulan_Data_Config menampung hal yang harus bisa diubah
 * saat sistem berjalan TANPA deploy: sakelar kampanye, kalimat pengumuman, dan
 * cakupan lokasi kerja yang dibuka.
 *
 * Perilaku teknis (frekuensi modal, jatah penundaan, definisi field) TIDAK di
 * sini — itu di config/pengumpulan_data.php.
 */
class KonfigurasiKampanye
{
    private const KUNCI_CACHE = 'pengumpulan-data:config';

    public function aktif(): bool
    {
        $baris = $this->baris();

        if ($baris === null) {
            return (bool) config('pengumpulan_data.aktif_default', false);
        }

        return ($baris->Is_Aktif ?? 'T') === 'Y';
    }

    public function pengumuman(): array
    {
        $baris = $this->baris();

        return [
            'judul' => (string) ($baris->Judul_Pengumuman ?? 'Pembaruan data karyawan'),
            'teks' => (string) ($baris->Teks_Pengumuman ?? 'Mohon lengkapi data karyawanmu.'),
            'label_tombol_isi' => (string) ($baris->Label_Tombol_Isi ?? 'Isi sekarang'),
            'label_tombol_tunda' => (string) ($baris->Label_Tombol_Tunda ?? 'Nanti dulu'),
        ];
    }

    /**
     * Lokasi kerja (Karyawan.Cabang_Untuk_Gaji) yang kampanyenya dibuka.
     *
     * Daftar KOSONG berarti seluruh lokasi — itu perilaku sebelum cakupan ada,
     * dan menjadikannya bawaan berarti memasang fitur ini tidak mengubah satu
     * pun keputusan yang sudah berjalan.
     *
     * Kolom yang belum ada di database juga terbaca sebagai daftar kosong.
     * Arah gagalnya disengaja: DDL yang belum dijalankan tidak boleh membuat
     * halaman ini roboh. Konsekuensinya harus diingat saat rilis — selama
     * kolomnya belum ada, cakupan yang disimpan admin tidak berlaku sama sekali.
     *
     * @return string[] nilai ternormalkan (huruf besar, tanpa spasi pinggir)
     */
    public function cakupanLokasi(): array
    {
        $mentah = $this->baris()->Cakupan_Lokasi ?? null;

        if (! is_string($mentah) || trim($mentah) === '') {
            return [];
        }

        $daftar = json_decode($mentah, true);

        if (! is_array($daftar)) {
            return [];
        }

        return CakupanKampanye::normalkanDaftar($daftar);
    }

    public function bersihkanCache(): void
    {
        Cache::forget(self::KUNCI_CACHE);
    }

    private function baris(): ?object
    {
        return Cache::remember(self::KUNCI_CACHE, now()->addMinutes(10), function (): ?object {
            return DB::table('N_HRIS_Pengumpulan_Data_Config')->orderBy('Id')->first();
        });
    }
}
