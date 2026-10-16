<?php

namespace App\Services\LMS\SOP\Uat;

use RuntimeException;

/**
 * Jurnal jalan-uji: catatan baris apa saja yang dibuat satu jalan otomasi UAT.
 *
 * KENAPA JURNAL, BUKAN MENCOCOKKAN AWALAN NAMA. Menghapus berdasarkan awalan
 * "UAT-AUTO-" terasa cukup sampai dua hal terjadi, dan keduanya pasti terjadi:
 * suatu hari ada dokumen sungguhan bernama awalan sama dan ikut terhapus, dan
 * suatu hari sebuah test gagal setelah menyisipkan baris tapi sebelum sempat
 * menamainya — baris itu lalu tak bisa dikenali selamanya. Jurnal mencatat ID
 * pada saat barisnya lahir, jadi tak bergantung pada nama sama sekali.
 *
 * Hanya lapis peramban yang membutuhkannya. Test backend memakai
 * DatabaseTransactions dan ter-rollback sendiri; browser memukul server lewat
 * HTTP di proses lain, sehingga tak ada transaksi yang bisa dibatalkan.
 */
class UatJurnal
{
    /** @var array<string, list<int>> */
    private array $baris = [];

    private function __construct(
        private readonly string $id,
        private readonly string $sarang,
    ) {}

    public static function mulai(?string $sarang = null): self
    {
        $sarang = $sarang ?? self::sarangBaku();
        if (! is_dir($sarang)) {
            mkdir($sarang, 0777, true);
        }

        /* Cap waktu membuat jurnal bisa diurutkan mata; bagian acak mencegah
           dua jalan pada detik yang sama saling menimpa. */
        $id = 'uat-'.date('Ymd-His').'-'.bin2hex(random_bytes(3));

        return new self($id, $sarang);
    }

    public static function muat(string $id, ?string $sarang = null): self
    {
        $sarang = $sarang ?? self::sarangBaku();
        $j = new self(self::idSah($id), $sarang);

        $berkas = $j->berkas();
        if (! is_file($berkas)) {
            throw new RuntimeException("Jurnal jalan-uji '{$id}' tidak ditemukan di {$sarang}.");
        }

        $isi = json_decode((string) file_get_contents($berkas), true);
        if (! is_array($isi)) {
            throw new RuntimeException("Jurnal jalan-uji '{$id}' rusak dan tidak bisa dibaca.");
        }

        $j->baris = $isi['baris'] ?? [];

        return $j;
    }

    /** @return list<string> */
    public static function semua(?string $sarang = null): array
    {
        $sarang = $sarang ?? self::sarangBaku();

        return array_values(array_map(
            fn (string $f) => basename($f, '.json'),
            glob(rtrim($sarang, '/\\').'/uat-*.json') ?: []
        ));
    }

    public function id(): string
    {
        return $this->id;
    }

    /** @return array<string, list<int>> */
    public function baris(): array
    {
        return $this->baris;
    }

    /**
     * Catat satu baris yang baru dibuat, lalu SEGERA tulis ke disk.
     *
     * Menahan catatan di memori sampai akhir jalan lebih hemat, tapi jalan yang
     * mati di tengah — CTRL-C, listrik padam, peramban hang — justru keadaan
     * yang paling membutuhkan jurnal, dan itu persis keadaan saat penulisan
     * di akhir tak pernah kesampaian.
     */
    public function catat(string $tabel, int $id): void
    {
        UatTabelIzin::pastikanDiizinkan($tabel);

        if ($id < 1) {
            throw new RuntimeException(
                "ID yang dicatat harus bilangan bulat positif, diberi: {$id}. "
                .'Nilai 0 biasanya berarti penyisipan gagal tapi kegagalannya tak diperiksa.'
            );
        }

        $this->baris[$tabel] ??= [];
        if (! in_array($id, $this->baris[$tabel], true)) {
            $this->baris[$tabel][] = $id;
        }

        $this->simpan();
    }

    public function simpan(): void
    {
        file_put_contents($this->berkas(), json_encode([
            'id' => $this->id,
            'baris' => $this->baris,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function hapusBerkas(): void
    {
        if (is_file($this->berkas())) {
            unlink($this->berkas());
        }
    }

    public function berkas(): string
    {
        return rtrim($this->sarang, '/\\').DIRECTORY_SEPARATOR.$this->id.'.json';
    }

    private static function sarangBaku(): string
    {
        return storage_path('app/uat-auto');
    }

    /**
     * ID jalan ikut menyusun jalur berkas, jadi ia adalah masukan yang bisa
     * dipakai menembus ke folder lain. Dibatasi ke bentuk yang memang dihasilkan
     * mulai().
     */
    private static function idSah(string $id): string
    {
        if (! preg_match('/^uat-\d{8}-\d{6}-[0-9a-f]{6}$/', $id)) {
            throw new RuntimeException("ID jalan-uji tidak sah: '{$id}'.");
        }

        return $id;
    }
}
