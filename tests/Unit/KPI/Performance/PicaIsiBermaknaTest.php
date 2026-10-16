<?php

namespace Tests\Unit\KPI\Performance;

use App\Services\KPI\KpiPicaIsi;
use Tests\TestCase;

/**
 * Penjaga isi PICA. Fungsi murni, tidak menyentuh basis data.
 *
 * ── Cacat yang dijaga ───────────────────────────────────────────────────────
 *
 * PICA adalah satu-satunya tempat di modul KPI yang menjelaskan KENAPA sebuah
 * target meleset. Selama isian apa pun diterima, kolom itu terisi tanpa pernah
 * menjawab apa pun. Diukur di production 16 Sep 2026: dari 2.766 baris PICA,
 * **1.167 berisi tiga karakter atau kurang** — '-', '1', 'sda'. Pada Juli 2026
 * saja ada 412 baris yang kedua sisinya ≤2 byte, dan semuanya ikut tercetak di
 * laporan serta export, menutupi baris yang benar-benar berisi.
 *
 * ── Yang ditegaskan ─────────────────────────────────────────────────────────
 *
 * Nilai-nilai di bawah BUKAN karangan: '-', '1', dan 'sda' diambil langsung
 * dari baris yang ada di production. Uji ini akan merah bila ambangnya
 * dilonggarkan kembali, dan juga bila dilonggarkan ke arah sebaliknya sampai
 * menolak kalimat yang sah.
 */
class PicaIsiBermaknaTest extends TestCase
{
    /** @return array<string,array{0:?string,1:bool}> */
    public static function contohIsi(): array
    {
        return [
            // Diambil apa adanya dari production.
            'strip saja' => ['-', false],
            'angka satu' => ['1', false],
            'sda (tiga huruf)' => ['sda', true],

            'kosong' => ['', false],
            'null' => [null, false],
            'spasi saja' => ['   ', false],
            'titik-titik' => ['...', false],
            'angka panjang' => ['2024', false],
            'dua huruf' => ['ok', false],
            'huruf berselang angka' => ['a1b2', false],
            'tiga huruf berspasi' => [' a b c ', true],
            'kalimat wajar' => ['Agent terlambat mengirim invoice', true],
            'non-latin' => ['حالة', true],
        ];
    }

    /**
     * @dataProvider contohIsi
     */
    public function test_menilai_isian_tunggal(?string $teks, bool $harap): void
    {
        $this->assertSame(
            $harap,
            KpiPicaIsi::bermakna($teks),
            'Isian '.var_export($teks, true).' dinilai keliru. Ambangnya adalah jumlah HURUF '.
            '(minimal '.KpiPicaIsi::MIN_HURUF.'), bukan panjang string — supaya "2024" ditolak '.
            'sementara "sda" diterima.',
        );
    }

    public function test_kedua_sisi_harus_bermakna(): void
    {
        $this->assertTrue(KpiPicaIsi::pasanganBermakna('Stok menipis', 'Tambah buffer'));

        $this->assertFalse(
            KpiPicaIsi::pasanganBermakna('Stok menipis', '-'),
            'Identifikasi tanpa tindakan koreksi hanyalah keluhan; ia tidak boleh lolos '.
            'hanya karena salah satu sisinya berisi.',
        );
        $this->assertFalse(
            KpiPicaIsi::pasanganBermakna('1', 'Tambah buffer'),
            'Tindakan tanpa identifikasi tidak bisa ditelusuri kembali ke sebabnya.',
        );
        $this->assertFalse(KpiPicaIsi::pasanganBermakna('1', '1'));
    }

    public function test_ambang_tertulis_sebagai_konstanta_bukan_angka_liar(): void
    {
        // Angka ambang ikut muncul di pesan yang dibaca pengguna. Kalau keduanya
        // bisa berselisih, layar akan menyebut aturan yang tidak dijalankan.
        $this->assertStringContainsString(
            (string) KpiPicaIsi::MIN_HURUF,
            KpiPicaIsi::PESAN,
            'Pesan ke pengguna tidak menyebut ambang yang sebenarnya berlaku.',
        );
    }
}
