<?php

namespace Tests\Unit\ESS\Attendance;

use App\Services\ESS\Attendance\Processing\KlasifikasiKeterlambatan as K;
use PHPUnit\Framework\TestCase;

/**
 * Keterlambatan yang dimaafkan tidak boleh hilang tanpa jejak.
 *
 * SP absensi (HRIS_SP_GET_ABSEN_NEW_V3) menghitung keterlambatan mentah lalu
 * MENIMPANYA jadi 0 bila di bawah toleransi:
 *
 *     @RealLate = DATEDIFF(Minute, @Temp_JamMasuk, @CheckIn)
 *     IF @RealLate < @TLate  -> @RealLate = 0
 *     ELSE IF @RealLate > 50 -> @RealLate = 50
 *
 * Angka mentahnya tidak disimpan di kolom mana pun. Dashboard membaca
 * RealLate, jadi orang yang datang 07:10 dari jadwal 07:00 dengan toleransi
 * 30 menit tampil PERSIS SAMA dengan yang datang tepat waktu.
 *
 * Kelas ini menghitung ulang angka mentah itu dari bahan yang sudah dikirim
 * wrapper — Jam_Masuk, CheckIn, TLate — lalu menamai sebabnya. Ia tidak
 * mengubah RealLate: angka resmi tetap milik SP.
 */
class KlasifikasiKeterlambatanTest extends TestCase
{
    /** Bentuk baris apa adanya dari SP: semua nilai datang sebagai string. */
    private function baris(array $ganti = []): object
    {
        return (object) array_merge([
            'Tanggal' => '2026-05-02',
            'Jam_Masuk' => '07:00:00',
            'CheckIn' => '2026-05-02 07:00:00.000',
            'TLate' => '0',
            'RealLate' => '0',

            // '-' adalah bentuk NYATA dari "tidak ada transaksi" di snapshot,
            // bukan NULL. Fixture versi pertama memakai null dan karenanya
            // menyembunyikan cacat: '-' lolos empty() sehingga setiap
            // keterlambatan yang dimaafkan toleransi salah dilabeli 'berizin'.
            'No_Transaksi_Terlambat' => '-',
            'Status_Kehadiran' => null,
        ], $ganti);
    }

    public function test_datang_tepat_waktu(): void
    {
        $h = K::dari($this->baris());

        $this->assertSame(K::TEPAT_WAKTU, $h['kategori']);
        $this->assertSame(0, $h['mentah_menit']);
        $this->assertFalse($h['terlambat_sebenarnya']);
    }

    /** Datang lebih awal bukan keterlambatan negatif. */
    public function test_datang_lebih_awal_tidak_jadi_menit_minus(): void
    {
        $h = K::dari($this->baris(['CheckIn' => '2026-05-02 06:45:00.000']));

        $this->assertSame(K::TEPAT_WAKTU, $h['kategori']);
        $this->assertSame(0, $h['mentah_menit']);
    }

    public function test_terlambat_yang_memang_dihitung(): void
    {
        $h = K::dari($this->baris([
            'CheckIn' => '2026-05-02 07:20:00.000',
            'RealLate' => '20',
        ]));

        $this->assertSame(K::TERLAMBAT, $h['kategori']);
        $this->assertSame(20, $h['mentah_menit']);
        $this->assertSame(20, $h['tercatat_menit']);
        $this->assertSame(0, $h['tak_tercatat_menit']);
        $this->assertTrue($h['terlambat_sebenarnya']);
    }

    /**
     * Inti persoalannya: telat 10 menit, toleransi 30, tercatat 0.
     */
    public function test_terlambat_tetapi_termaafkan_toleransi(): void
    {
        $h = K::dari($this->baris([
            'CheckIn' => '2026-05-02 07:10:00.000',
            'TLate' => '30',
            'RealLate' => '0',
        ]));

        $this->assertSame(K::TOLERANSI, $h['kategori']);
        $this->assertSame(10, $h['mentah_menit']);
        $this->assertSame(0, $h['tercatat_menit']);
        $this->assertSame(10, $h['tak_tercatat_menit'], 'Menit yang hilang dari dashboard harus dinyatakan.');
        $this->assertTrue($h['terlambat_sebenarnya'], 'Dimaafkan bukan berarti tidak terlambat.');
    }

    /**
     * Tebing di batas toleransi. SP memakai `<` tegas, bukan pengurangan:
     * menit ke-29 dimaafkan penuh, menit ke-30 dihitung penuh 30.
     */
    public function test_tepat_di_batas_toleransi_tetap_dihitung(): void
    {
        $h = K::dari($this->baris([
            'CheckIn' => '2026-05-02 07:30:00.000',
            'TLate' => '30',
            'RealLate' => '30',
        ]));

        $this->assertSame(K::TERLAMBAT, $h['kategori']);
        $this->assertSame(30, $h['tercatat_menit']);
    }

    public function test_terlambat_tetapi_berizin(): void
    {
        $h = K::dari($this->baris([
            'CheckIn' => '2026-05-02 07:20:00.000',
            'TLate' => '0',
            'RealLate' => '0',
            'No_Transaksi_Terlambat' => 'TP06-26-0017',
            'Status_Kehadiran' => 'IZIN TERLAMBAT',
        ]));

        $this->assertSame(K::BERIZIN, $h['kategori']);
        $this->assertSame(20, $h['mentah_menit']);
        $this->assertTrue($h['terlambat_sebenarnya']);
    }

    /**
     * Punya izin TAPI tetap dihitung terlambat oleh SP — yang menang adalah
     * angka SP, bukan keberadaan izinnya. Kalau urutannya terbalik, orang yang
     * tetap dihukum akan salah dilabeli 'dimaafkan'.
     */
    public function test_izin_tidak_menutupi_keterlambatan_yang_tetap_dihitung(): void
    {
        $h = K::dari($this->baris([
            'CheckIn' => '2026-05-02 07:40:00.000',
            'RealLate' => '40',
            'No_Transaksi_Terlambat' => 'TP06-26-0099',
        ]));

        $this->assertSame(K::TERLAMBAT, $h['kategori']);
    }

    /**
     * Shift malam. CheckIn membawa tanggalnya sendiri, jadi selisihnya benar
     * tanpa menebak lintas hari — dibuktikan oleh baris nyata 2026-05-20:
     * jadwal 23:00, masuk 2026-05-21 00:00, RealLate 50 (mentah 60 kena plafon).
     */
    public function test_shift_malam_melewati_tengah_malam(): void
    {
        $h = K::dari($this->baris([
            'Tanggal' => '2026-05-20',
            'Jam_Masuk' => '23:00',
            'CheckIn' => '2026-05-21 00:00:00.000',
            'RealLate' => '50',
        ]));

        $this->assertSame(60, $h['mentah_menit'], 'Lintas tengah malam tidak boleh jadi minus.');
        $this->assertSame(50, $h['tercatat_menit']);
        $this->assertTrue($h['kena_plafon'], 'Plafon 50 menit harus dinyatakan, bukan disembunyikan.');
        $this->assertSame(10, $h['tak_tercatat_menit']);
    }

    /** Jam_Masuk kadang '07:00', kadang '07:00:00' — keduanya harus terbaca. */
    public function test_jam_masuk_tanpa_detik_tetap_terbaca(): void
    {
        $h = K::dari($this->baris([
            'Jam_Masuk' => '07:00',
            'CheckIn' => '2026-05-02 07:15:00.000',
            'TLate' => '30',
        ]));

        $this->assertSame(15, $h['mentah_menit']);
        $this->assertSame(K::TOLERANSI, $h['kategori']);
    }

    public function test_tanpa_check_in_bukan_keterlambatan(): void
    {
        $h = K::dari($this->baris(['CheckIn' => null]));

        $this->assertSame(K::TANPA_CHECK_IN, $h['kategori']);
        $this->assertFalse($h['terlambat_sebenarnya']);
        $this->assertSame(0, $h['mentah_menit']);
    }

    /** Hari tanpa jadwal (OFF): tak ada acuan, jangan mengarang keterlambatan. */
    public function test_tanpa_jam_masuk_tidak_mengarang_keterlambatan(): void
    {
        $h = K::dari($this->baris([
            'Jam_Masuk' => null,
            'CheckIn' => '2026-05-02 09:00:00.000',
        ]));

        $this->assertSame(K::TANPA_CHECK_IN, $h['kategori']);
        $this->assertSame(0, $h['mentah_menit']);
    }

    /**
     * Telat, tercatat nol, tapi tak ada izin dan tak ada toleransi.
     * Ini tidak boleh diam-diam disebut 'dimaafkan' — sebabnya belum diketahui
     * dan justru itu yang perlu diselidiki.
     */
    public function test_dinolkan_tanpa_sebab_yang_diketahui(): void
    {
        $h = K::dari($this->baris([
            'CheckIn' => '2026-05-02 07:20:00.000',
            'TLate' => '0',
            'RealLate' => '0',
        ]));

        $this->assertSame(K::DINOLKAN, $h['kategori']);
        $this->assertTrue($h['terlambat_sebenarnya']);
    }

    /**
     * Penanda "tidak ada" di tabel absen adalah '-', bukan NULL.
     *
     * Ini cacat yang benar-benar terjadi: kelas ini sempat membaca '-' sebagai
     * nomor transaksi yang sah, sehingga keterlambatan yang dimaafkan TOLERANSI
     * dilaporkan sebagai BERIZIN. Ketahuan hanya setelah dijalankan atas baris
     * sungguhan — uji fixture-nya hijau karena kolomnya tidak disertakan.
     */
    public function test_strip_bukan_nomor_izin(): void
    {
        $h = K::dari($this->baris([
            'CheckIn' => '2026-05-02 07:10:00.000',
            'TLate' => '15',
            'RealLate' => '0',
            'No_Transaksi_Terlambat' => '-',
        ]));

        $this->assertSame(K::TOLERANSI, $h['kategori'],
            "'-' berarti tidak ada izin; melabelinya 'berizin' menyembunyikan sebab yang sebenarnya.");
    }

    public function test_spasi_kosong_juga_bukan_nomor_izin(): void
    {
        $h = K::dari($this->baris([
            'CheckIn' => '2026-05-02 07:10:00.000',
            'TLate' => '15',
            'No_Transaksi_Terlambat' => '   ',
        ]));

        $this->assertSame(K::TOLERANSI, $h['kategori']);
    }

    /** Baris array (bukan object) harus diterima juga. */
    public function test_menerima_baris_berbentuk_array(): void
    {
        $h = K::dari((array) $this->baris(['CheckIn' => '2026-05-02 07:05:00.000', 'TLate' => '30']));

        $this->assertSame(5, $h['mentah_menit']);
        $this->assertSame(K::TOLERANSI, $h['kategori']);
    }
}
