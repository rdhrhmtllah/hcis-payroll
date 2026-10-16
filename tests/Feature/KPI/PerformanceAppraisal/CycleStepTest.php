<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaCycleStepService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Alur berlangkah siklus.
 *
 * Yang paling dijaga di berkas ini satu hal: **langkah aktif diturunkan dari POSISI, bukan
 * dari kelengkapan isi.** Modul SOP sudah membayar mahal untuk pelajaran itu — wizard
 * versi dokumen dulu memilih langkah dengan mencari "langkah pertama yang isinya belum
 * lengkap", dan layarnya melompat setiap kali menyimpan. Penggunanya melaporkannya sebagai
 * "tersimpan tapi selalu balik ke Review", yang terdengar seperti cacat penyimpanan.
 *
 * Karena itu uji di sini memaksa keadaan isi yang MENYESATKAN — siklus DRAFT dengan
 * peserta sudah terbentuk, siklus CLOSED tanpa satu hasil pun — lalu menuntut langkahnya
 * tetap mengikuti status. Uji yang hanya memakai keadaan wajar tidak akan pernah
 * membedakan kedua cara memilih langkah itu.
 */
class CycleStepTest extends TestCase
{
    use DatabaseTransactions;

    public function test_langkah_aktif_mengikuti_status_bukan_kelengkapan_isi(): void
    {
        $cycleId = $this->siklus('DRAFT');

        /* Peserta SUDAH dibentuk padahal statusnya masih DRAFT — keadaan yang membuat
           pemilih "berdasar kelengkapan isi" melompat ke langkah 3, karena langkah 2
           tampak sudah selesai. Langkahnya harus tetap 2. */
        $this->peserta($cycleId, 3);

        $alur = $this->service()->alur($cycleId);

        $this->assertSame(2, $alur['langkah_aktif'],
            'DRAFT harus berhenti di langkah Peserta, betapapun lengkap isinya.');
        $this->assertSame('BERJALAN', $alur['langkah'][1]['keadaan']);
        $this->assertSame('SELESAI', $alur['langkah'][0]['keadaan']);
        $this->assertSame('TERKUNCI', $alur['langkah'][2]['keadaan']);
    }

    public function test_langkah_tetap_maju_meski_isinya_kosong(): void
    {
        /* Kebalikannya, dan sama pentingnya: siklus CLOSED tanpa satu hasil pun. Pemilih
           berbasis kelengkapan akan menariknya mundur ke langkah awal; posisinya tetap 5. */
        $cycleId = $this->siklus('CLOSED');

        $alur = $this->service()->alur($cycleId);

        $this->assertSame(5, $alur['langkah_aktif']);
        $this->assertSame('BERJALAN', $alur['langkah'][4]['keadaan']);
    }

    public function test_setiap_status_memetakan_ke_tepat_satu_langkah(): void
    {
        /* Status yang memetakan ke dua langkah berarti ada keadaan tempat layar tidak bisa
           menjawab "saya di mana" — dan itu persis pertanyaan yang alur ini ada untuk
           menjawabnya. */
        $terlihat = [];

        foreach (['DRAFT', 'PREPARED', 'LOCKED', 'OPEN', 'CLOSED', 'PUBLISHED'] as $status) {
            $cycleId = $this->siklus($status);
            $alur = $this->service()->alur($cycleId);

            $berjalan = array_values(array_filter(
                $alur['langkah'],
                fn ($l) => $l['keadaan'] === 'BERJALAN',
            ));

            $this->assertCount(1, $berjalan,
                "Status {$status} menghasilkan ".count($berjalan).' langkah BERJALAN, harus tepat satu.');

            $terlihat[$status] = $berjalan[0]['nomor'];
        }

        // Dan urutannya harus MENAIK seiring siklus maju — kalau tidak, alurnya mundur
        // di suatu titik dan itu membingungkan lebih daripada tidak ada alur sama sekali.
        $urut = array_values($terlihat);
        $terurut = $urut;
        sort($terurut);

        $this->assertSame($terurut, $urut, 'Nomor langkah harus menaik seiring status maju.');
    }

    public function test_penghalang_hanya_untuk_langkah_berjalan(): void
    {
        $cycleId = $this->siklus('PREPARED');
        $this->peserta($cycleId, 4);

        $alur = $this->service()->alur($cycleId);

        $berjalan = $alur['langkah'][$alur['langkah_aktif'] - 1];

        $this->assertNotEmpty($berjalan['penghalang'],
            'Peserta tanpa penilai harus dilaporkan sebagai penghalang langkah Penilai.');
        $this->assertStringContainsString('belum punya satu penilai pun', $berjalan['penghalang'][0]);

        /* Langkah terkunci TIDAK boleh membawa penghalang — membanjiri layar dengan
           keluhan tentang pekerjaan yang belum waktunya dikerjakan membuat yang benar-benar
           menghalangi tenggelam. */
        foreach ($alur['langkah'] as $l) {
            if ($l['keadaan'] === 'TERKUNCI') {
                $this->assertSame([], $l['penghalang'],
                    "Langkah terkunci {$l['kode']} membawa penghalang.");
            }
        }
    }

    public function test_aksi_utama_hanya_pada_langkah_berjalan_dan_cocok_dengan_gerbang(): void
    {
        $cycleId = $this->siklus('PREPARED');

        $alur = $this->service()->alur($cycleId);
        $berjalan = $alur['langkah'][$alur['langkah_aktif'] - 1];

        $this->assertNotNull($berjalan['aksi_utama']);
        $this->assertNotSame('', $berjalan['aksi_utama']['label']);

        /* Operasinya harus ADA di `operasi_boleh`. Tombol yang ditawarkan sebagai aksi
           utama tetapi ditolak gerbang saat ditekan adalah bentuk paling langsung dari
           dua sumber kebenaran. */
        $this->assertContains($berjalan['aksi_utama']['operasi'], $alur['operasi_boleh']);

        foreach ($alur['langkah'] as $l) {
            if ($l['keadaan'] !== 'BERJALAN') {
                $this->assertNull($l['aksi_utama'], "Langkah {$l['kode']} membawa aksi utama.");
            }
        }
    }

    /* ------------------------------------------------------------------ *
     * Penyiapan                                                           *
     * ------------------------------------------------------------------ */

    private function siklus(string $status): int
    {
        $kode = 'UJI-LANGKAH-'.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => $kode,
            'Nama_Cycle' => 'Siklus Uji Langkah',
            'Tahun' => 2026,
            'Status_Cycle' => $status,
            'Periode_Dinilai_Dari' => '2026-01-01',
            'Periode_Dinilai_Sampai' => '2026-06-30',
            'Pengisian_Dari' => '2026-07-01',
            'Pengisian_Sampai' => '2026-07-31',
        ]);

        return (int) DB::table('N_PA_Cycle')->where('Kode_Cycle', $kode)->value('Id_PA_Cycle');
    }

    private function peserta(int $cycleId, int $jumlah): void
    {
        $levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');

        $karyawan = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')->limit($jumlah)
            ->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan']);

        foreach ($karyawan as $k) {
            DB::table('N_PA_Participant')->insert([
                'PA_Cycle_Id' => $cycleId,
                'Kode_Perusahaan' => $k->Kode_Perusahaan,
                'Kode_Karyawan' => $k->Kode_Karyawan,
                'Nama_Karyawan_Saat_Cycle' => $k->Nama,
                'ID_Level_Jabatan_Saat_Cycle' => $k->ID_Level_Jabatan,
                'ID_Level_Saat_Cycle' => $levelId,
                'Status_Peserta' => 'DIIKUTKAN',
            ]);
        }
    }

    private function service(): PaCycleStepService
    {
        return $this->app->make(PaCycleStepService::class);
    }
}
