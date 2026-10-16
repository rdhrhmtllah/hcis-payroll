<?php

namespace Tests\Feature\SYSTEM;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Pembanding potret skema harus benar-benar MELIHAT selisihnya.
 *
 * ── Kenapa dijaga ─────────────────────────────────────────────────────────
 *
 * Seluruh angka performa yang bisa diukur dari worktree ini berasal dari
 * pengembangan, dan pengembangan berbohong tentang dua hal: jumlah barisnya
 * jauh lebih kecil, dan indeksnya belum tentu sama dengan produksi. Perintah
 * db:inventaris-skema ada supaya perbedaan itu bisa dilihat, bukan ditebak.
 *
 * Sebuah pembanding yang diam-diam tidak menemukan apa pun jauh lebih
 * berbahaya daripada tidak punya pembanding sama sekali: ia memberi rasa
 * yakin bahwa kedua lingkungan sama. Karena itu setiap kelas selisih diuji
 * dengan potret yang selisihnya SUDAH DIKETAHUI.
 *
 * Kelas yang diuji:
 *   1. tabel hanya ada di salah satu sisi
 *   2. kolom hanya ada di salah satu sisi
 *   3. indeks hilang di sisi A, dan hilang di sisi B (dua arah)
 *   4. indeks BERNAMA SAMA tetapi isinya berbeda — kasus paling licik,
 *      sebab skrip penerapan mengira indeksnya sudah ada lalu melewatinya
 *
 * Uji ini tidak menyentuh basis data: jalur --banding membaca dua berkas
 * JSON lalu keluar sebelum koneksi mana pun dibuka.
 */
class InventarisSkemaBandingTest extends TestCase
{
    private string $berkasA = '';

    private string $berkasB = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->berkasA = tempnam(sys_get_temp_dir(), 'skemaA').'.json';
        $this->berkasB = tempnam(sys_get_temp_dir(), 'skemaB').'.json';
    }

    protected function tearDown(): void
    {
        foreach ([$this->berkasA, $this->berkasB] as $f) {
            if ($f !== '' && is_file($f)) {
                unlink($f);
            }
        }
        parent::tearDown();
    }

    /** @param  array<string, mixed>  $tabel */
    private function tulis(string $berkas, string $nama, array $tabel): void
    {
        file_put_contents($berkas, json_encode([
            'basis_data' => $nama,
            'diambil_pada' => '2026-09-08 00:00:00',
            'sqlserver_start_time' => '2026-08-18 03:26:39.903',
            'tabel' => $tabel,
        ], JSON_PRETTY_PRINT));
    }

    /** @return array<string, mixed> */
    private function tabelDasar(): array
    {
        return [
            'Karyawan' => [
                'skema' => 'dbo',
                'baris' => 965,
                'heap' => false,
                'kolom' => ['Kode_Karyawan' => 'varchar(50,0,0) NOT NULL', 'Aktif' => 'char(1,0,0) NULL'],
                'indeks' => [
                    'IX_Karyawan_Kode' => ['jenis' => 'NONCLUSTERED', 'kunci' => 'Kode_Karyawan'],
                    'IX_Karyawan_User' => ['jenis' => 'NONCLUSTERED', 'kunci' => 'UserID_Web'],
                ],
            ],
        ];
    }

    private function banding(): string
    {
        /*
         * Artisan::call, bukan $this->artisan(): PendingCommand menampung
         * keluarannya sendiri sehingga Artisan::output() kembali kosong, dan
         * setiap assertStringContainsString lolos tanpa memeriksa apa pun.
         */
        $kode = Artisan::call('db:inventaris-skema', [
            '--banding' => $this->berkasA.','.$this->berkasB,
        ]);
        $this->assertSame(0, $kode, 'perintah banding gagal');

        $keluar = Artisan::output();
        $this->assertNotSame('', trim($keluar), 'pembanding tidak mengeluarkan apa pun');

        return $keluar;
    }

    public function test_tabel_yang_hanya_ada_di_satu_sisi_terlihat(): void
    {
        $a = $this->tabelDasar();
        $a['Tabel_Hanya_Dev'] = ['skema' => 'dbo', 'baris' => 1, 'heap' => true, 'kolom' => [], 'indeks' => []];
        $b = $this->tabelDasar();
        $b['Tabel_Hanya_Prod'] = ['skema' => 'dbo', 'baris' => 9, 'heap' => true, 'kolom' => [], 'indeks' => []];

        $this->tulis($this->berkasA, 'DEV', $a);
        $this->tulis($this->berkasB, 'PROD', $b);
        $keluar = $this->banding();

        $this->assertStringContainsString('tabel hanya di A: 1', $keluar);
        $this->assertStringContainsString('Tabel_Hanya_Dev', $keluar);
        $this->assertStringContainsString('tabel hanya di B: 1', $keluar);
        $this->assertStringContainsString('Tabel_Hanya_Prod', $keluar);
    }

    public function test_kolom_yang_hanya_ada_di_satu_sisi_terlihat(): void
    {
        $a = $this->tabelDasar();
        $b = $this->tabelDasar();
        unset($b['Karyawan']['kolom']['Aktif']);

        $this->tulis($this->berkasA, 'DEV', $a);
        $this->tulis($this->berkasB, 'PROD', $b);
        $keluar = $this->banding();

        $this->assertStringContainsString('kolom yang hanya ada di salah satu: 1', $keluar);
        $this->assertStringContainsString('Karyawan.Aktif  (hanya di A)', $keluar);
    }

    public function test_indeks_hilang_terlihat_di_kedua_arah(): void
    {
        $a = $this->tabelDasar();
        $b = $this->tabelDasar();
        unset($b['Karyawan']['indeks']['IX_Karyawan_Kode']);   // hilang di PROD
        $a['Karyawan']['indeks'] = array_diff_key($a['Karyawan']['indeks'], ['IX_Karyawan_User' => 1]);

        $this->tulis($this->berkasA, 'DEV', $a);
        $this->tulis($this->berkasB, 'PROD', $b);
        $keluar = $this->banding();

        $this->assertStringContainsString('indeks TIDAK ADA di B (PROD): 1', $keluar);
        $this->assertStringContainsString('Karyawan.IX_Karyawan_Kode', $keluar);
        $this->assertStringContainsString('indeks TIDAK ADA di A (DEV): 1', $keluar);
        $this->assertStringContainsString('Karyawan.IX_Karyawan_User', $keluar);
    }

    public function test_indeks_bernama_sama_tetapi_isinya_berbeda_terlihat(): void
    {
        $a = $this->tabelDasar();
        $b = $this->tabelDasar();
        /*
         * Nama sama, kunci berbeda. Kalau kelas ini lolos, sebuah indeks yang
         * di produksi ternyata berkunci lain akan dianggap "sudah ada" dan
         * tidak pernah diperbaiki.
         */
        $b['Karyawan']['indeks']['IX_Karyawan_Kode']['kunci'] = 'Kode_Karyawan, Aktif';

        $this->tulis($this->berkasA, 'DEV', $a);
        $this->tulis($this->berkasB, 'PROD', $b);
        $keluar = $this->banding();

        $this->assertStringContainsString('indeks bernama sama tetapi BERBEDA isinya: 1', $keluar);
        $this->assertStringContainsString('A=[Kode_Karyawan] B=[Kode_Karyawan, Aktif]', $keluar);
    }

    public function test_potret_identik_tidak_melaporkan_selisih_apa_pun(): void
    {
        /*
         * Sisi lain dari penjaga: pembanding yang menemukan selisih pada dua
         * potret yang sama persis sama tidak bergunanya dengan yang buta.
         */
        $this->tulis($this->berkasA, 'DEV', $this->tabelDasar());
        $this->tulis($this->berkasB, 'PROD', $this->tabelDasar());
        $keluar = $this->banding();

        foreach ([
            'tabel hanya di A: 0',
            'tabel hanya di B: 0',
            'kolom yang hanya ada di salah satu: 0',
            'indeks TIDAK ADA di A (DEV): 0',
            'indeks TIDAK ADA di B (PROD): 0',
            'indeks bernama sama tetapi BERBEDA isinya: 0',
        ] as $harap) {
            $this->assertStringContainsString($harap, $keluar);
        }
    }
}
