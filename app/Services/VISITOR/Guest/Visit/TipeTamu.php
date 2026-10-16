<?php

namespace App\Services\VISITOR\Guest\Visit;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/**
 * Tipe tamu (REGULAR / B2B) dan kategori turunannya (VISITOR / MITRA).
 *
 * Tipe DITETAPKAN RUTE, tidak pernah dikirim klien: middleware
 * `visitor.tipe-tamu:<TIPE>` menaruhnya di atribut permintaan. Halaman Regular
 * dan B2B memakai controller yang sama, sehingga satu-satunya yang mencegah
 * pemegang kunci Regular membaca atau menyetujui kunjungan B2B adalah
 * penyaring yang membaca atribut ini — bukan tab yang disembunyikan di Vue.
 */
final class TipeTamu
{
    public const REGULAR = 'REGULAR';

    public const B2B = 'B2B';

    public const SEMUA = [self::REGULAR, self::B2B];

    public const ATRIBUT = 'visitor_tipe_tamu';

    public const KATEGORI_VISITOR = 'VISITOR';

    public const KATEGORI_MITRA = 'MITRA';

    /** Kunjungan lebih lama dari ini (bukan sama dengan) adalah MITRA. */
    public const BATAS_MITRA_DETIK = 86400;

    public static function sah(string $tipe): string
    {
        $tipe = strtoupper(trim($tipe));

        if (! in_array($tipe, self::SEMUA, true)) {
            throw new \InvalidArgumentException("Tipe tamu tidak dikenal: {$tipe}");
        }

        return $tipe;
    }

    /**
     * Tipe yang ditetapkan rute. Tanpa penetapan (pemanggilan langsung di uji,
     * rute lama) yang berlaku REGULAR — halaman lama memang menjadi Regular.
     */
    public static function dariPermintaan(Request $request): string
    {
        return self::sah((string) ($request->attributes->get(self::ATRIBUT) ?? self::REGULAR));
    }

    /** Tipe yang ditetapkan rute, atau null bila rutenya melayani kedua tipe. */
    public static function dariPermintaanAtauSemua(Request $request): ?string
    {
        $tipe = $request->attributes->get(self::ATRIBUT);

        return $tipe === null ? null : self::sah((string) $tipe);
    }

    /**
     * Tipe untuk endpoint monitoring bersama (papan, antrean) yang juga dibaca
     * halaman permit. Tipe dari rute menang; `domain=tamu` tanpa tipe rute
     * berarti halaman Regular; tanpa domain berarti tampilan gabungan.
     */
    public static function untukDomain(Request $request, ?string $domain): ?string
    {
        $dariRute = self::dariPermintaanAtauSemua($request);

        if ($dariRute !== null) {
            return $dariRute;
        }

        return $domain === 'tamu' ? self::REGULAR : null;
    }

    /** Halaman verifikator yang memegang baris bertipe ini. */
    public static function urlVerifikator(?string $tipe): string
    {
        return strtoupper((string) $tipe) === self::B2B ? '/verifikator-visitor/b2b' : '/verifikator-visitor/tamu';
    }

    /** Modul approval & notifikasi di VisitorApprovalPolicyService. */
    public static function modulApproval(string $tipe): string
    {
        return self::sah($tipe) === self::B2B ? 'tamu_b2b' : 'tamu';
    }

    public static function kategori(CarbonInterface $masuk, CarbonInterface $keluar): string
    {
        return $keluar->getTimestamp() - $masuk->getTimestamp() > self::BATAS_MITRA_DETIK
            ? self::KATEGORI_MITRA
            : self::KATEGORI_VISITOR;
    }

    /**
     * Penyaring tipe untuk kueri ber-alias detail. NULL dibaca REGULAR, sama
     * dengan bawaan kolomnya sejak DDL-2026-101.
     */
    public static function saring($query, string $tipe, string $detailAlias = 'd')
    {
        return self::sah($tipe) === self::B2B
            ? $query->where($detailAlias.'.Tipe_Tamu', self::B2B)
            : $query->where(function ($q) use ($detailAlias) {
                $q->whereNull($detailAlias.'.Tipe_Tamu')->orWhere($detailAlias.'.Tipe_Tamu', self::REGULAR);
            });
    }
}
