<?php

namespace Tests\Unit\KPI\PerformanceAppraisal\Katalog;

use App\Services\KPI\PerformanceAppraisal\Katalog\PaBagianKatalog;
use RuntimeException;
use Tests\TestCase;

/**
 * Identitas bagian adalah titik paling rapuh di kontrak baru HC Learn, dan satu-satunya
 * yang gagalnya TIDAK terlihat: label yang terikat ke identitas yang salah tetap
 * tersimpan, tetap tampak lengkap, dan baru terasa saat nilainya pulang ke kompetensi
 * yang keliru.
 *
 * HC Learn tidak menyediakan id per-instance untuk bagian — hanya `id_jenis_soal`, yang
 * dokumentasi mereka sendiri sebut pengenal BANK SOAL: "Bank yang sama bisa dipakai
 * beberapa alat tes." Karena itu identitasnya PASANGAN dengan alat tesnya, dan seluruh
 * penafsiran bentuk dipusatkan di satu kelas.
 *
 * Cara membuat berkas ini MERAH: ubah kunci() supaya mengembalikan id_jenis_soal saja.
 */
class PaBagianKatalogTest extends TestCase
{
    /** Ujian dua alat tes, keduanya memakai bank soal 77 — sah, dan HARUS terbedakan. */
    private function ujianDuaAlatTes(): array
    {
        return [
            'id_master_ujian' => 31,
            'nama' => 'Internal Assessment',
            'paket' => [
                'id_master_paket_ujian' => 9,
                'nama' => 'PAKET A',
                'alat_tes' => [
                    [
                        'id_jenis_indikator' => 100,
                        'nama' => 'TIU',
                        'bagian' => [
                            ['id_jenis_soal' => 77, 'nama' => 'Kemampuan Verbal', 'kode_soal' => 'TKV'],
                            ['id_jenis_soal' => 78, 'nama' => 'Kemampuan Numerik', 'kode_soal' => 'TKN'],
                        ],
                    ],
                    [
                        'id_jenis_indikator' => 200,
                        'nama' => 'TKD',
                        'bagian' => [
                            ['id_jenis_soal' => 77, 'nama' => 'Kemampuan Verbal', 'kode_soal' => 'TKV'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_kunci_memasangkan_alat_tes_dengan_jenis_soal(): void
    {
        $this->assertSame(
            '100|77',
            PaBagianKatalog::kunci(['id_jenis_indikator' => 100, 'id_jenis_soal' => 77]),
        );
    }

    public function test_bank_soal_yang_sama_di_dua_alat_tes_tidak_bertabrakan(): void
    {
        // Inti perbaikan ini. Kalau kuncinya hanya id_jenis_soal, kedua bagian ini terbaca
        // sebagai bagian yang sama, dan salah satunya kehilangan labelnya.
        $daftar = PaBagianKatalog::ratakan($this->ujianDuaAlatTes());

        $this->assertCount(3, $daftar);
        $this->assertSame(
            ['100|77', '100|78', '200|77'],
            array_map(fn ($b) => PaBagianKatalog::kunci($b), $daftar),
        );
        $this->assertSame([], PaBagianKatalog::kembar($daftar));
    }

    public function test_ratakan_membawa_nama_alat_tes_tiap_bagian(): void
    {
        // Satu paket bisa memuat dua alat tes yang sama-sama punya "Bagian 1". Tanpa nama
        // alat tesnya, admin tidak tahu yang mana yang sedang dilabeli.
        $daftar = PaBagianKatalog::ratakan($this->ujianDuaAlatTes());

        $this->assertSame(['TIU', 'TIU', 'TKD'], array_column($daftar, 'nama_alat_tes'));
        $this->assertSame([0, 0, 1], array_column($daftar, 'urutan_alat_tes'));
        $this->assertSame([0, 1, 0], array_column($daftar, 'urutan_bagian'));
    }

    public function test_alat_tes_khusus_tanpa_bagian_dilewati(): void
    {
        // Kraeplin, PAPI, DISC: jumlah_bagian = 0 karena soalnya memang tidak dipecah.
        // Bukan galat — hanya tidak menyumbang bagian yang bisa dilabeli.
        $daftar = PaBagianKatalog::ratakan(['paket' => ['alat_tes' => [
            ['id_jenis_indikator' => 300, 'nama' => 'Kraeplin', 'tes_khusus' => true, 'bagian' => []],
        ]]]);

        $this->assertSame([], $daftar);
    }

    public function test_bagian_tanpa_id_jenis_soal_ditolak(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/identitas bagian/i');

        PaBagianKatalog::ratakan(['paket' => ['alat_tes' => [
            ['id_jenis_indikator' => 100, 'nama' => 'TIU', 'bagian' => [['nama' => 'Tanpa Id']]],
        ]]]);
    }

    public function test_pasangan_kembar_terdeteksi(): void
    {
        // Satu alat tes memakai bank soal yang sama dua kali. Dokumentasi HC Learn tidak
        // melarangnya, dan kalau terjadi, pemetaannya ambigu.
        $daftar = PaBagianKatalog::ratakan(['paket' => ['alat_tes' => [
            ['id_jenis_indikator' => 100, 'nama' => 'TIU', 'bagian' => [
                ['id_jenis_soal' => 77, 'nama' => 'Verbal A'],
                ['id_jenis_soal' => 77, 'nama' => 'Verbal B'],
            ]],
        ]]]);

        $this->assertSame(['100|77'], PaBagianKatalog::kembar($daftar));
    }

    public function test_sidik_tidak_bergantung_pada_urutan_masukan(): void
    {
        // Sidik memotret HIMPUNAN pasangan. Urutan yang berubah tanpa mengubah himpunannya
        // tidak merugikan pemetaan kita, jadi tidak boleh terbaca berubah.
        $a = [
            ['id_jenis_indikator' => 100, 'id_jenis_soal' => 77],
            ['id_jenis_indikator' => 200, 'id_jenis_soal' => 78],
        ];

        $this->assertSame(PaBagianKatalog::sidik($a), PaBagianKatalog::sidik(array_reverse($a)));
    }

    public function test_sidik_berubah_ketika_ada_bagian_hilang(): void
    {
        $a = [
            ['id_jenis_indikator' => 100, 'id_jenis_soal' => 77],
            ['id_jenis_indikator' => 200, 'id_jenis_soal' => 78],
        ];

        $this->assertNotSame(PaBagianKatalog::sidik($a), PaBagianKatalog::sidik([$a[0]]));
    }
}
