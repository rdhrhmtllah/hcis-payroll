<?php

namespace App\Services\VISITOR\Guest\Visit;

use App\Services\VISITOR\Permit\VehiclePermit\PlatNomorKendaraan;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Aturan isian registrasi tamu per tipe — satu sumber untuk layar Regular,
 * layar B2B, dan API mitra.
 *
 * `aturan()` dipakai validator permintaan; `periksa()` dipanggil penulis
 * (RegistrasiTamuService) supaya aturan yang sama berlaku di pintu mana pun,
 * termasuk yang tidak memakai FormRequest.
 */
final class AturanFormTamu
{
    /** Penjaga salah ketik; kunjungan MITRA terpanjang yang wajar. */
    public const RENTANG_MAKS_HARI = 30;

    public const FOTO_MAKS_KB = 5120;

    public const FOTO_MIME = ['jpg', 'jpeg', 'png', 'webp'];

    /** Nilai jenis kendaraan yang berarti tamu datang tanpa kendaraan. */
    private const TANPA_KENDARAAN = ['tidak berkendara', 'tidak berkendaraan', 'tanpa kendaraan', 'pejalan kaki', 'jalan kaki', 'none'];

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function aturan(string $tipe): array
    {
        $b2b = TipeTamu::sah($tipe) === TipeTamu::B2B;

        $aturan = [
            'nama_tamu' => ['required', 'string', 'max:150'],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'keperluan' => ['required', 'string', 'max:250'],
            'pic_tujuan' => ['required', 'string', 'max:100'],
            // Diturunkan dari Estimasi Masuk; masih diterima demi pemanggil lama.
            'tanggal_kunjungan' => ['nullable', 'date'],
            'waktu_mulai_kunjungan' => ['required', 'date', 'after_or_equal:today'],
            'waktu_selesai_kunjungan' => ['required', 'date', 'after:waktu_mulai_kunjungan', self::aturanRentangMaks()],
            'instansi_asal' => ['required', 'string', 'max:150'],
            'jenis_kendaraan' => $b2b
                ? ['required', 'string', 'max:50', self::aturanWajibBerkendara()]
                : ['nullable', 'string', 'max:50'],
            // Batas dari kolom TERSEMPIT sepanjang rantai (proyeksi varchar(500)).
            'keterangan' => ['nullable', 'string', 'max:500'],
            'no_identitas' => ['nullable', 'string', 'max:50'],
            'jenis_identitas' => ['nullable', 'string', 'max:20'],
            // nullable, bukan present: pengajuan B2B berfoto dikirim sebagai
            // multipart, dan FormData tidak bisa membawa array kosong. Tamu
            // yang datang sendiri tetap sah; controller membacanya sebagai [].
            'daftar_anggota' => ['nullable', 'array'],
            'daftar_anggota.*.nama' => ['required', 'string', 'max:150'],
            'daftar_anggota.*.jenis_identitas' => ['nullable', 'string', 'max:20'],
            'daftar_anggota.*.no_identitas' => ['nullable', 'string', 'max:50'],
            // Unit dipilih hanya bila pengaju berhak lebih dari satu; server
            // tetap memeriksanya terhadap matriks (GuestRegistrationController).
            'b2b_unit' => $b2b ? ['nullable', 'string', 'in:PROCUREMENT,LOGISTIC'] : ['prohibited'],
            'foto_kendaraan' => $b2b
                ? ['nullable', 'file', 'mimes:'.implode(',', self::FOTO_MIME), 'max:'.self::FOTO_MAKS_KB]
                : ['prohibited'],
        ] + PlatNomorKendaraan::aturanValidasi(PlatNomorKendaraan::LEBAR_TAMU);

        if ($b2b) {
            $aturan['plat_prefix'] = ['required', 'string', 'max:'.PlatNomorKendaraan::LEBAR_TAMU];
            $aturan['plat_angka'] = ['required', 'string', 'max:'.PlatNomorKendaraan::LEBAR_TAMU];
            // Plat lengkap sampai kode belakang (permintaan user 29 Sep 2026):
            // plat tanpa kode belakang tidak cukup untuk dikenali di gerbang.
            // Hanya form web — kontrak API mitra tidak diubah diam-diam.
            $aturan['plat_suffix'] = ['required', 'string', 'max:'.PlatNomorKendaraan::LEBAR_TAMU];
        }

        return $aturan;
    }

    /** Label field untuk pesan validasi. */
    public static function atribut(): array
    {
        return [
            'waktu_mulai_kunjungan' => 'Estimasi Masuk',
            'waktu_selesai_kunjungan' => 'Estimasi Keluar',
            'jenis_kendaraan' => 'Jenis Kendaraan',
            'plat_prefix' => 'Kode Wilayah Plat',
            'plat_angka' => 'Nomor Plat',
            'plat_suffix' => 'Kode Belakang Plat',
            'foto_kendaraan' => 'Foto Kendaraan',
        ];
    }

    public static function tanpaKendaraan(?string $jenis): bool
    {
        $jenis = mb_strtolower(trim((string) $jenis));

        return $jenis === ''
            || in_array($jenis, self::TANPA_KENDARAAN, true)
            || str_contains($jenis, 'tidak')
            || str_contains($jenis, 'tanpa');
    }

    /**
     * Penjaga di penulis. Melempar InvalidArgumentException (dibalas 422 oleh
     * kedua pintu) bila baris yang akan ditulis melanggar aturan tipenya.
     *
     * `$longgarB2b` hanya dipakai API mitra selama masa transisi
     * (services.visitor.tamu_b2b_api.wajib_lengkap = false).
     *
     * @param  array{prefix: ?string, angka: ?string, suffix: ?string}  $plat
     */
    public static function periksa(string $tipe, Carbon $masuk, Carbon $keluar, ?string $jenisKendaraan, array $plat, bool $longgarB2b = false): void
    {
        if ($keluar->lte($masuk)) {
            throw new \InvalidArgumentException('Estimasi Keluar harus setelah Estimasi Masuk.');
        }

        if ($masuk->copy()->addDays(self::RENTANG_MAKS_HARI)->lt($keluar)) {
            throw new \InvalidArgumentException('Rentang kunjungan maksimal '.self::RENTANG_MAKS_HARI.' hari.');
        }

        if (TipeTamu::sah($tipe) !== TipeTamu::B2B || $longgarB2b) {
            return;
        }

        if (self::tanpaKendaraan($jenisKendaraan)) {
            throw new \InvalidArgumentException('Tamu B2B wajib berkendaraan.');
        }

        if (blank($plat['prefix'] ?? null) || blank($plat['angka'] ?? null)) {
            throw new \InvalidArgumentException('Plat nomor kendaraan tamu B2B wajib diisi.');
        }
    }

    private static function aturanWajibBerkendara(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (self::tanpaKendaraan(is_string($value) ? $value : null)) {
                $fail('Tamu B2B wajib berkendaraan.');
            }
        };
    }

    private static function aturanRentangMaks(): ValidationRule
    {
        return new class implements DataAwareRule, ValidationRule
        {
            private array $data = [];

            public function setData(array $data): static
            {
                $this->data = $data;

                return $this;
            }

            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                $mulai = $this->data['waktu_mulai_kunjungan'] ?? null;

                try {
                    if ($mulai && Carbon::parse($mulai)->addDays(AturanFormTamu::RENTANG_MAKS_HARI)->lt(Carbon::parse($value))) {
                        $fail('Rentang kunjungan maksimal '.AturanFormTamu::RENTANG_MAKS_HARI.' hari.');
                    }
                } catch (\Throwable) {
                    // Format tanggal yang salah sudah ditolak aturan 'date'.
                }
            }
        };
    }
}
