<?php

namespace App\Services\VISITOR\Governance\MasterData;

use InvalidArgumentException;

/**
 * Bentuk berkas Excel master data Visitor: lembar, kolom, dan kosakata nilainya.
 *
 * SATU sumber untuk dua arah. Berkas hasil unduh adalah templat yang dipakai
 * orang untuk meng-import balik, jadi begitu kolomnya didefinisikan dua kali
 * — sekali di penulis, sekali di pembaca — keduanya akan menyimpang dan
 * templat yang baru saja diunduh ditolak oleh pembacanya sendiri.
 *
 * Kolom "No" pada berkas acuan tidak ada di sini: nomor urut hanyalah posisi
 * baris, ikut berubah tiap kali datanya disaring, dan tidak menunjuk apa pun
 * ketika dibaca kembali. Penulis tidak menulisnya, pembaca mengabaikannya.
 */
final class VisitorMasterDataFileFormat
{
    /**
     * Urutan lembar pada berkas, mengikuti berkas acuan dari pengguna.
     *
     * 'kolom' berisi [kunci kolom tabel, header berkas, format opsional].
     * Kolom berformat 'relasi' ditulis dan dibaca sebagai NAMA masternya, bukan
     * id: berkas ini berpindah tangan antar-lingkungan, dan id master tidak
     * berarti apa-apa di luar basis data asalnya.
     */
    public const LEMBAR = [
        'vehicle-type' => [
            'sheet' => 'Tipe Kendaraan',
            'kolom' => [
                ['Nama_Tipe_Kendaraan', 'Tipe Kendaraan'],
            ],
        ],
        'vehicle-model' => [
            'sheet' => 'Model Kendaraan',
            'kolom' => [
                ['Id_Master_Tipe', 'Tipe Kendaraan', 'relasi'],
                ['Nama_Model_Kendaraan', 'Model Kendaraan'],
            ],
        ],
        'approval-question' => [
            'sheet' => 'Pertanyaan',
            'kolom' => [
                ['Teks_Pertanyaan', 'Pertanyaan'],
                ['Target_Id_Kategori_Permit', 'Kategori Permit', 'relasi'],
                ['Target_Id_Tipe_Kendaraan', 'Tipe Kendaraan', 'relasi'],
                ['Wajib_Diisi', 'Wajib Diisi', 'ya_tidak'],
            ],
        ],
        'vehicle-color' => [
            'sheet' => 'Warna Kendaraan',
            'kolom' => [
                ['Nama_Warna', 'Warna Kendaraan'],
            ],
        ],
        'kategori-permit' => [
            'sheet' => 'Kategori Permit',
            'kolom' => [
                ['Nama_Kategori', 'Kategori Permit'],
                ['Deskripsi_Akses', 'Deskripsi Akses'],
                ['Flag_Muncul_Pengajuan', 'Muncul di Pengajuan', 'ya_tidak'],
            ],
        ],
        'plat-kendaraan' => [
            'sheet' => 'Kode Plat',
            'kolom' => [
                ['Kode_Plat', 'Kode Plat'],
            ],
        ],
        'durasi-permit' => [
            'sheet' => 'Durasi Permit',
            'kolom' => [
                ['Nama_Durasi', 'Nama Durasi'],
                ['Jumlah_Angka', 'Jumlah'],
                ['Satuan_Waktu', 'Satuan', 'satuan'],
                ['Keterangan_Durasi', 'Keterangan'],
            ],
        ],
    ];

    /**
     * Urutan PEMROSESAN import — sengaja terpisah dari urutan lembar.
     *
     * Keduanya menjawab pertanyaan berbeda: LEMBAR menjawab "bagaimana berkas
     * dibaca manusia", daftar ini menjawab "siapa harus ada lebih dulu". Induk
     * (Tipe Kendaraan, Kategori Permit) wajib diproses sebelum lembar yang
     * merujuknya, supaya tipe baru yang datang di berkas yang SAMA sudah ada
     * ketika baris Model/Pertanyaan dibaca. Menyamakannya dengan urutan tampil
     * membuat berkas yang sah ditolak separuh tanpa sebab yang masuk akal.
     */
    public const URUTAN_IMPOR = [
        'vehicle-type',
        'kategori-permit',
        'vehicle-model',
        'approval-question',
        'vehicle-color',
        'plat-kendaraan',
        'durasi-permit',
    ];

    public const JUDUL_DOMAIN = [
        'permit' => 'Master_Data_Permit',
        'tamu' => 'Master_Data_Tamu',
    ];

    /**
     * Kosakata nilai berkas: teks yang dibaca manusia -> nilai kolom.
     *
     * Dipakai penulis (dibalik) dan pembaca (apa adanya), jadi "Aktif" yang
     * ditulis hari ini pasti dikenali pembaca besok.
     */
    public const NILAI = [
        'status' => ['Aktif' => 'Y', 'Nonaktif' => 'T'],
        'ya_tidak' => ['Ya' => 'Y', 'Tidak' => 'T'],
        'satuan' => ['Bulan' => 'MONTH'],
    ];

    /** Teks kolom relasi yang berarti "tidak dibatasi" pada master pertanyaan. */
    public const RELASI_SEMUA = 'Semua';

    /**
     * @return list<string> kunci master domain ini, dalam urutan lembar berkas.
     */
    public static function masterDomain(string $domain): array
    {
        return self::saringDomain($domain, array_keys(self::LEMBAR));
    }

    /**
     * @return list<string> kunci master domain ini, dalam urutan pemrosesan import.
     */
    public static function masterDomainUrutanImpor(string $domain): array
    {
        return self::saringDomain($domain, self::URUTAN_IMPOR);
    }

    /**
     * @param  list<string>  $urutan
     * @return list<string>
     */
    private static function saringDomain(string $domain, array $urutan): array
    {
        if (! array_key_exists($domain, self::JUDUL_DOMAIN)) {
            throw new InvalidArgumentException('Domain master data Visitor tidak dikenal.');
        }

        $milikDomain = VisitorMasterDataCatalog::keysDomain($domain);

        return array_values(array_filter(
            $urutan,
            static fn (string $master): bool => in_array($master, $milikDomain, true),
        ));
    }

    /**
     * Kolom bisnis lembar plus kolom Status milik master yang memilikinya.
     *
     * @return list<array{0: string, 1: string, 2?: string}>
     */
    public static function kolom(string $master): array
    {
        if (! isset(self::LEMBAR[$master])) {
            throw new InvalidArgumentException('Master data Visitor ini tidak punya bentuk berkas.');
        }

        $kolom = self::LEMBAR[$master]['kolom'];
        $definisi = VisitorMasterDataCatalog::get($master);

        if ($definisi['status']) {
            $kolom[] = [$definisi['status'], 'Status', 'status'];
        }

        return $kolom;
    }

    public static function sheet(string $master): string
    {
        return self::LEMBAR[$master]['sheet'];
    }

    /** Kunci master pemilik satu judul lembar, tanpa peduli beda huruf besar/kecil. */
    public static function masterDariSheet(string $judul, string $domain): ?string
    {
        $dicari = mb_strtolower(trim($judul));

        foreach (self::masterDomain($domain) as $master) {
            if (mb_strtolower(self::sheet($master)) === $dicari) {
                return $master;
            }
        }

        return null;
    }

    /** Nilai kolom -> teks berkas. Arah tulis. */
    public static function keTeks(?string $format, mixed $nilai): mixed
    {
        if ($format === null || $format === 'relasi') {
            return $nilai;
        }

        $peta = array_flip(self::NILAI[$format] ?? []);

        if ($format === 'status') {
            return $peta[$nilai] ?? 'Nonaktif';
        }

        if ($format === 'ya_tidak') {
            return $peta[$nilai] ?? 'Tidak';
        }

        return $peta[$nilai] ?? (string) ($nilai ?? '');
    }

    /**
     * Teks berkas -> nilai kolom. Arah baca; null berarti teksnya tidak dikenal.
     */
    public static function dariTeks(string $format, string $teks): ?string
    {
        foreach (self::NILAI[$format] ?? [] as $label => $nilai) {
            if (mb_strtolower($label) === mb_strtolower(trim($teks))) {
                return $nilai;
            }
        }

        return null;
    }

    /**
     * @return list<string> teks yang sah untuk satu format, untuk pesan galat.
     */
    public static function pilihan(string $format): array
    {
        return array_keys(self::NILAI[$format] ?? []);
    }
}
