<?php

namespace App\Services\SYSTEM\Application\Reports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * CRUD template, selalu terikat satu modul.
 *
 * $module datang dari KONSTRUKTOR, yang diisi controller dari route — tak
 * pernah dari badan permintaan. Kalau modul boleh dikirim pengguna, satu query
 * yang lupa menyaring langsung membocorkan daftar template modul lain.
 *
 * Template sistem juga DISUNTIKKAN, bukan di-import. Kelas ini milik SYSTEM dan
 * tidak boleh menyebut satu pun modul bisnis: begitu ia meng-import
 * SopTemplateSistem, modul kedua harus mengubah mesin untuk bergabung — dan itu
 * persis kegagalan yang ingin dihindari desain ini.
 */
class ReportTemplateService
{
    public const TABEL = 'N_HRIS_Report_Template';

    /**
     * Tingkat bagi yang membuat template terlihat di luar pemiliknya.
     *
     * DIBAGIKAN dan ORGANISASI saat ini berperilaku sama — keduanya berarti
     * "terlihat semua orang yang berhak membuka Studio modul ini". Penyasaran
     * per divisi/jabatan sudah punya tabel (N_HRIS_Report_Template_Akses) tapi
     * belum dipakai; sampai ia dipakai, jangan perlakukan ORGANISASI sebagai
     * lingkup yang lebih sempit daripada DIBAGIKAN.
     */
    public const TINGKAT_TERBUKA = ['DIBAGIKAN', 'ORGANISASI'];

    /** @param array<string, array> $templateSistem berkunci Kode */
    public function __construct(
        private string $module,
        private DatasetRegistry $registry,
        private DefinisiValidator $validator,
        private array $templateSistem = [],
    ) {}

    public function galeri(string $kodeKaryawan): array
    {
        return [
            'resmi' => array_values($this->templateSistem),
            'saya' => DB::table(self::TABEL)
                ->where('Module', $this->module)
                ->where('Kode_Karyawan_Pemilik', $kodeKaryawan)
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Nama')->get()->all(),
            'dibagikan' => DB::table(self::TABEL)
                ->where('Module', $this->module)
                ->whereIn('Tingkat_Bagi', ['DIBAGIKAN', 'ORGANISASI'])
                ->where('Kode_Karyawan_Pemilik', '!=', $kodeKaryawan)
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Nama')->get()->all(),
        ];
    }

    /**
     * $kodeKaryawan WAJIB, bukan opsional.
     *
     * galeri(), perbarui(), dan nonaktifkan() sejak awal discope pemilik;
     * ambil() dulu satu-satunya yang tidak — padahal ia yang dipakai pratinjau,
     * jalankan, dan duplikat. Akibatnya siapa pun yang bisa membuka Studio dan
     * menebak sebuah Kode ikut membaca template PRIBADI orang lain berikut
     * seluruh nilai saringannya. Parameter wajib membuat pemanggil BERIKUTNYA
     * tidak bisa lupa; nilai bawaan akan mengulang celah yang sama.
     *
     * Penyaringan dilakukan DI DALAM query, bukan setelah baris terambil:
     * "tidak ditemukan" dan "bukan hak Anda" jadi tak terbedakan, sehingga
     * endpoint ini tidak bisa dipakai menebak template mana yang ada.
     */
    public function ambil(string $kode, string $kodeKaryawan): array
    {
        if (isset($this->templateSistem[$kode])) {
            return $this->templateSistem[$kode] + ['sistem' => true];
        }

        $baris = DB::table(self::TABEL)
            ->where('Module', $this->module)
            ->where('Kode', $kode)
            ->where('Flag_Aktif', 'Y')
            ->where(function ($w) use ($kodeKaryawan) {
                $w->where('Kode_Karyawan_Pemilik', $kodeKaryawan)
                    ->orWhereIn('Tingkat_Bagi', self::TINGKAT_TERBUKA);
            })
            ->first();

        if (! $baris) {
            throw new RuntimeException("Template tidak ditemukan: {$kode}");
        }

        return [
            'kode' => $baris->Kode,
            'nama' => $baris->Nama,
            'deskripsi' => $baris->Deskripsi,
            'dataset' => $baris->Dataset_Key,
            'mode' => strtolower($baris->Mode),
            'definisi' => json_decode($baris->Definisi_Json, true) ?: [],
            'sistem' => false,
        ];
    }

    public function simpan(array $data, string $kodeKaryawan): string
    {
        $definisi = DefinisiLaporan::dariArray($data['definisi'] ?? []);
        $dataset = $this->registry->ambil($this->module, $definisi->dataset);

        $galat = $this->validator->periksa($definisi, $dataset);
        if ($galat !== []) {
            throw new RuntimeException('Definisi laporan tidak sah: '.json_encode($galat, JSON_UNESCAPED_UNICODE));
        }

        $kode = $this->kodeBaru((string) ($data['nama'] ?? 'laporan'));

        DB::table(self::TABEL)->insert([
            'Module' => $this->module,
            'Kode' => $kode,
            'Nama' => (string) $data['nama'],
            'Deskripsi' => $data['deskripsi'] ?? null,
            'Dataset_Key' => $definisi->dataset,
            'Mode' => strtoupper($definisi->mode),
            'Definisi_Json' => json_encode($definisi->toArray(), JSON_UNESCAPED_UNICODE),
            'Versi_Definisi' => $definisi->versi,
            'Tingkat_Bagi' => $data['tingkat_bagi'] ?? 'PRIBADI',
            'Kode_Karyawan_Pemilik' => $kodeKaryawan,
            'Kode_Template_Asal' => $data['kode_template_asal'] ?? null,
            'Created_By' => $kodeKaryawan,
        ]);

        return $kode;
    }

    public function perbarui(string $kode, array $data, string $kodeKaryawan): string
    {
        $definisi = DefinisiLaporan::dariArray($data['definisi'] ?? []);
        $dataset = $this->registry->ambil($this->module, $definisi->dataset);

        $galat = $this->validator->periksa($definisi, $dataset);
        if ($galat !== []) {
            throw new RuntimeException('Definisi laporan tidak sah: '.json_encode($galat, JSON_UNESCAPED_UNICODE));
        }

        $terpengaruh = DB::table(self::TABEL)
            ->where('Module', $this->module)
            ->where('Kode', $kode)
            ->where('Kode_Karyawan_Pemilik', $kodeKaryawan)
            ->where('Flag_Aktif', 'Y')
            ->update([
                'Nama' => (string) $data['nama'],
                'Deskripsi' => $data['deskripsi'] ?? null,
                'Dataset_Key' => $definisi->dataset,
                'Mode' => strtoupper($definisi->mode),
                'Definisi_Json' => json_encode($definisi->toArray(), JSON_UNESCAPED_UNICODE),
                'Versi_Definisi' => $definisi->versi,
                'Tingkat_Bagi' => $data['tingkat_bagi'] ?? 'PRIBADI',
                'Updated_At' => now(),
                'Updated_By' => $kodeKaryawan,
            ]);

        if ($terpengaruh === 0) {
            throw new RuntimeException("Template tidak ditemukan atau bukan milik Anda: {$kode}");
        }

        return $kode;
    }

    public function duplikat(string $kodeAsal, string $kodeKaryawan): string
    {
        $asal = $this->ambil($kodeAsal, $kodeKaryawan);

        return $this->simpan([
            'nama' => $asal['nama'].' (salinan)',
            'deskripsi' => $asal['deskripsi'] ?? null,
            'definisi' => $asal['definisi'],
            'tingkat_bagi' => 'PRIBADI',
            'kode_template_asal' => $kodeAsal,
        ], $kodeKaryawan);
    }

    public function nonaktifkan(string $kode, string $kodeKaryawan): void
    {
        // Menonaktifkan, bukan menghapus: Kode_Template_Asal pada turunannya
        // harus tetap bisa ditelusuri.
        $terpengaruh = DB::table(self::TABEL)
            ->where('Module', $this->module)
            ->where('Kode', $kode)
            ->where('Kode_Karyawan_Pemilik', $kodeKaryawan)
            ->where('Flag_Aktif', 'Y')
            ->update(['Flag_Aktif' => 'T', 'Updated_At' => now(), 'Updated_By' => $kodeKaryawan]);

        if ($terpengaruh === 0) {
            throw new RuntimeException("Template tidak ditemukan atau bukan milik Anda: {$kode}");
        }
    }

    /**
     * UX_Report_Template_Kode unik pada (Module, Kode). Tanpa percobaan ulang,
     * dua template bernama sama yang kebetulan menarik akhiran sama muncul di
     * layar sebagai QueryException mentah — 500, bukan pesan yang bisa dibaca.
     */
    private function kodeBaru(string $nama): string
    {
        $dasar = $this->module.'-'.(Str::slug(Str::limit($nama, 40, '')) ?: 'laporan');

        for ($percobaan = 0; $percobaan < 5; $percobaan++) {
            $kode = $dasar.'-'.bin2hex(random_bytes(3));

            $sudahAda = DB::table(self::TABEL)
                ->where('Module', $this->module)
                ->where('Kode', $kode)
                ->exists();

            if (! $sudahAda) {
                return $kode;
            }
        }

        throw new RuntimeException('Gagal membuat kode template yang unik. Ubah nama template lalu coba lagi.');
    }
}
