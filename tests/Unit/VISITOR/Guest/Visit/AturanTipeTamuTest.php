<?php

namespace Tests\Unit\VISITOR\Guest\Visit;

use App\Services\VISITOR\Guest\Visit\AturanFormTamu;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Aturan pemisahan Tamu Regular & B2B (DDL-2026-121).
 *
 * Kategori, aturan kendaraan B2B, dan kosakata status adalah keputusan user
 * yang dikunci di satu tempat masing-masing. Uji ini menjaga batasnya — tepat
 * di titik yang paling mudah bergeser satu detik atau satu kata.
 */
class AturanTipeTamuTest extends TestCase
{
    private const PLAT = ['prefix' => 'BG', 'angka' => '1234', 'suffix' => null];

    private const TANPA_PLAT = ['prefix' => null, 'angka' => null, 'suffix' => null];

    public function test_tepat_24_jam_masih_visitor(): void
    {
        $masuk = Carbon::parse('2026-09-20 08:00:00');

        $this->assertSame(TipeTamu::KATEGORI_VISITOR, TipeTamu::kategori($masuk, $masuk->copy()->addDay()));
    }

    public function test_lebih_satu_detik_dari_24_jam_jadi_mitra(): void
    {
        $masuk = Carbon::parse('2026-09-20 08:00:00');

        $this->assertSame(
            TipeTamu::KATEGORI_MITRA,
            TipeTamu::kategori($masuk, $masuk->copy()->addDay()->addSecond()),
        );
    }

    public function test_melewati_tengah_malam_kurang_24_jam_tetap_visitor(): void
    {
        // Batasnya durasi, bukan beda tanggal kalender — keputusan user.
        $this->assertSame(
            TipeTamu::KATEGORI_VISITOR,
            TipeTamu::kategori(Carbon::parse('2026-09-20 20:00'), Carbon::parse('2026-09-21 04:00')),
        );
    }

    public function test_tipe_tak_dikenal_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TipeTamu::sah('VIP');
    }

    public function test_modul_approval_mengikuti_tipe(): void
    {
        $this->assertSame('tamu', TipeTamu::modulApproval(TipeTamu::REGULAR));
        $this->assertSame('tamu_b2b', TipeTamu::modulApproval(TipeTamu::B2B));
    }

    public function test_b2b_tanpa_kendaraan_ditolak(): void
    {
        $this->expectExceptionMessage('wajib berkendaraan');

        AturanFormTamu::periksa(TipeTamu::B2B, ...$this->rentang(), jenisKendaraan: 'Tidak Berkendara', plat: self::PLAT);
    }

    public function test_b2b_tanpa_plat_ditolak(): void
    {
        $this->expectExceptionMessage('Plat nomor');

        AturanFormTamu::periksa(TipeTamu::B2B, ...$this->rentang(), jenisKendaraan: 'Mobil', plat: self::TANPA_PLAT);
    }

    public function test_b2b_berkendaraan_dan_berplat_lolos(): void
    {
        AturanFormTamu::periksa(TipeTamu::B2B, ...$this->rentang(), jenisKendaraan: 'Mobil', plat: self::PLAT);

        $this->addToAssertionCount(1);
    }

    public function test_masa_transisi_api_melonggarkan_hanya_aturan_kendaraan(): void
    {
        AturanFormTamu::periksa(TipeTamu::B2B, ...$this->rentang(), jenisKendaraan: null, plat: self::TANPA_PLAT, longgarB2b: true);
        $this->addToAssertionCount(1);

        // Rentang tetap dijaga walau dilonggarkan.
        $this->expectExceptionMessage('setelah Estimasi Masuk');
        $masuk = Carbon::parse('2026-09-20 08:00');
        AturanFormTamu::periksa(TipeTamu::B2B, $masuk, $masuk->copy(), null, self::TANPA_PLAT, true);
    }

    public function test_regular_boleh_tanpa_kendaraan_dan_plat(): void
    {
        AturanFormTamu::periksa(TipeTamu::REGULAR, ...$this->rentang(), jenisKendaraan: 'Tidak Berkendara', plat: self::TANPA_PLAT);

        $this->addToAssertionCount(1);
    }

    public function test_rentang_lebih_30_hari_ditolak(): void
    {
        $masuk = Carbon::parse('2026-09-20 08:00');

        $this->expectExceptionMessage('maksimal 30 hari');

        AturanFormTamu::periksa(TipeTamu::REGULAR, $masuk, $masuk->copy()->addDays(30)->addMinute(), null, self::TANPA_PLAT);
    }

    public function test_aturan_validasi_b2b_mewajibkan_kendaraan_dan_plat(): void
    {
        $b2b = AturanFormTamu::aturan(TipeTamu::B2B);
        $regular = AturanFormTamu::aturan(TipeTamu::REGULAR);

        $this->assertContains('required', $b2b['jenis_kendaraan']);
        $this->assertContains('required', $b2b['plat_prefix']);
        $this->assertContains('required', $b2b['plat_angka']);
        $this->assertContains('required', $b2b['plat_suffix'], 'Kode belakang plat tamu B2B wajib (29 Sep 2026).');
        $this->assertContains('nullable', $regular['jenis_kendaraan']);
        $this->assertContains('nullable', $regular['plat_prefix']);
        $this->assertContains('nullable', $regular['plat_suffix']);
        $this->assertContains('prohibited', $regular['foto_kendaraan']);
        $this->assertArrayNotHasKey('tipe_tamu', $b2b, 'Tipe ditetapkan rute, bukan isian.');
    }

    public function test_status_turunan_tidak_boleh_disimpan(): void
    {
        foreach ([StatusKunjunganTamu::CHECKED_IN, StatusKunjunganTamu::CHECKED_OUT, StatusKunjunganTamu::CANCELLED] as $turunan) {
            try {
                StatusKunjunganTamu::tersimpan($turunan);
                $this->fail("{$turunan} tidak boleh lolos sebagai status tersimpan.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(StatusKunjunganTamu::WITHDRAWN, StatusKunjunganTamu::tersimpan('WITHDRAWN'));
    }

    public function test_sql_daftar_menolak_kode_karangan(): void
    {
        $this->assertSame("'REJECTED', 'REVOKED'", StatusKunjunganTamu::sqlDaftar(['REJECTED', 'REVOKED']));

        $this->expectException(InvalidArgumentException::class);
        StatusKunjunganTamu::sqlDaftar(["X'; DROP TABLE t; --"]);
    }

    /** @return array{masuk: Carbon, keluar: Carbon} */
    private function rentang(): array
    {
        $masuk = Carbon::parse('2026-09-20 08:00');

        return ['masuk' => $masuk, 'keluar' => $masuk->copy()->addHours(8)];
    }
}
