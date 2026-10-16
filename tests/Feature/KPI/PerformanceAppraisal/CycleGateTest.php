<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;

/**
 * Gerbang status siklus.
 *
 * Empat aturan yang sebelumnya hanya berupa kalimat di README bermuara ke sini. Yang
 * paling menentukan: lapisan salinan siklus ADA justru karena janji "aturan membeku
 * saat dikunci". Kalau janji itu tidak ditegakkan, alasan keberadaan lapisan kedua
 * runtuh sementara skemanya tetap terlihat rapi.
 */
class CycleGateTest extends TestCase
{
    use DatabaseTransactions;

    private PaCycleGate $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gate = new PaCycleGate;
    }

    public function test_persiapan_boleh_sebelum_kunci_dan_tertutup_sesudahnya(): void
    {
        foreach ([PaCycleGate::SUNTING_SALINAN, PaCycleGate::PILIH_PAKET_SOAL,
            PaCycleGate::SUNTING_PESERTA, PaCycleGate::TULIS_OVERRIDE,
            PaCycleGate::BUAT_PENUGASAN] as $operasi) {
            $this->assertTrue($this->gate->boleh($operasi, $this->siklus('DRAFT')), "$operasi di DRAFT");
            $this->assertTrue($this->gate->boleh($operasi, $this->siklus('PREPARED')), "$operasi di PREPARED");
            $this->assertFalse($this->gate->boleh($operasi, $this->siklus('LOCKED')), "$operasi di LOCKED");
            $this->assertFalse($this->gate->boleh($operasi, $this->siklus('OPEN')), "$operasi di OPEN");
            $this->assertFalse($this->gate->boleh($operasi, $this->siklus('CLOSED')), "$operasi di CLOSED");
        }
    }

    public function test_bobot_pilar_adalah_pengecualian_yang_disengaja(): void
    {
        // K23: satu-satunya salinan siklus yang TIDAK ikut beku saat kunci. Kalau uji
        // ini gagal, pengecualian yang sengaja dirancang itu ikut tertutup diam-diam.
        $this->assertTrue($this->gate->boleh(PaCycleGate::SUNTING_BOBOT_PILAR, $this->siklus('LOCKED')));
        $this->assertTrue($this->gate->boleh(PaCycleGate::SUNTING_BOBOT_PILAR, $this->siklus('OPEN')));
        $this->assertTrue($this->gate->boleh(PaCycleGate::SUNTING_BOBOT_PILAR, $this->siklus('CLOSED')));

        // Tetapi tetap berhenti begitu hasilnya terbit.
        $this->assertFalse($this->gate->boleh(PaCycleGate::SUNTING_BOBOT_PILAR, $this->siklus('PUBLISHED')));

        // Dan berbeda dari salinan lain pada status yang sama.
        $lock = $this->siklus('LOCKED');
        $this->assertFalse($this->gate->boleh(PaCycleGate::SUNTING_SALINAN, $lock));
        $this->assertTrue($this->gate->boleh(PaCycleGate::SUNTING_BOBOT_PILAR, $lock));
    }

    public function test_payload_hanya_diterima_selama_pengisian_dan_penutupan(): void
    {
        $this->assertFalse($this->gate->boleh(PaCycleGate::TERIMA_PAYLOAD, $this->siklus('LOCKED')));
        $this->assertTrue($this->gate->boleh(PaCycleGate::TERIMA_PAYLOAD, $this->siklus('OPEN')));

        // Masih diterima saat CLOSED supaya hasil yang dikirim HC Learn tepat sebelum
        // penutupan tidak hilang.
        $this->assertTrue($this->gate->boleh(PaCycleGate::TERIMA_PAYLOAD, $this->siklus('CLOSED')));

        // Berhenti di PUBLISHED: hasil yang sudah diterbitkan tidak boleh berubah.
        $this->assertFalse($this->gate->boleh(PaCycleGate::TERIMA_PAYLOAD, $this->siklus('PUBLISHED')));
    }

    public function test_membatalkan_dan_menambah_penilai_boleh_selagi_siklus_berjalan(): void
    {
        /* Keduanya dulu menumpang BUAT_PENUGASAN, yang berhenti di PREPARED — sehingga
           sesudah pengisian dibuka penugasan tidak bisa dibatalkan maupun diganti sama
           sekali. Peserta yang resign dan penilai yang pindah unit tidak punya jalan
           keluar selain SQL manual, dan di sana Cancel_Reason, Cancelled_By, serta
           pencabutan tokennya semua terlewat.

           Dipisah menjadi operasi sendiri, bukan dengan melonggarkan BUAT_PENUGASAN:
           operasi itu dipakai delapan tempat, termasuk pembentukan MASSAL yang memang
           milik penyiapan. Menjalankannya di tengah siklus akan melahirkan ribuan
           penugasan sekaligus. */
        foreach ([PaCycleGate::BATALKAN_PENUGASAN, PaCycleGate::TAMBAH_PENILAI,
            PaCycleGate::KECUALIKAN_PESERTA, PaCycleGate::CETAK_ULANG_PINTU] as $operasi) {
            foreach (['DRAFT', 'PREPARED', 'LOCKED', 'OPEN'] as $status) {
                $this->assertTrue(
                    $this->gate->boleh($operasi, $this->siklus($status)),
                    "{$operasi} seharusnya boleh pada {$status}.",
                );
            }

            /* ARAH SEBALIKNYA, dan inilah batas yang sesungguhnya dijaga. Pada CLOSED
               admin sedang memverifikasi; pada PUBLISHED angkanya sudah dilihat orang.
               Mengubah masukannya di sana membatalkan yang sedang dipandang. Bila memang
               perlu, siklusnya dibuka lagi — CLOSED->OPEN memang diizinkan, dan pembukaan
               ulang meninggalkan jejak yang tidak dimiliki penyuntingan diam-diam. */
            foreach (['CLOSED', 'PUBLISHED'] as $status) {
                $this->assertFalse(
                    $this->gate->boleh($operasi, $this->siklus($status)),
                    "{$operasi} tidak boleh pada {$status}: angkanya sedang atau sudah dilihat orang.",
                );
            }
        }

        /* Pembentukan MASSAL tidak ikut dilonggarkan. Tanpa penegasan ini, seseorang bisa
           "merapikan" ketiganya menjadi satu operasi dan tidak ada yang memerah. */
        $this->assertFalse(
            $this->gate->boleh(PaCycleGate::BUAT_PENUGASAN, $this->siklus('OPEN')),
            'Pembentukan penugasan massal tidak boleh ikut terbuka saat siklus berjalan.',
        );

        /* Begitu pula penyuntingan daftar peserta. KECUALIKAN_PESERTA sengaja dipisah
           darinya: SUNTING_PESERTA juga berarti MENGIKUTKAN orang, dan orang yang
           diikutkan sesudah kunci lahir tanpa satu pun penilai — pembentukan penugasannya
           sudah berhenti. Ia akan menggantung menunggu angka yang tak pernah bisa
           dihitung. */
        $this->assertFalse(
            $this->gate->boleh(PaCycleGate::SUNTING_PESERTA, $this->siklus('OPEN')),
            'Penyuntingan daftar peserta tidak boleh ikut terbuka saat siklus berjalan.',
        );

        /* Begitu pula pemilihan paket soal. CETAK_ULANG_PINTU sengaja dipisah darinya:
           PILIH_PAKET_SOAL dipakai tujuh tempat termasuk penyuntingan ikatan soal, sehingga
           membukanya saat siklus berjalan berarti ISI soal bisa berubah selagi orang
           menjawabnya. Mencetak ulang pintu tidak menyentuh satu butir pun. */
        $this->assertFalse(
            $this->gate->boleh(PaCycleGate::PILIH_PAKET_SOAL, $this->siklus('OPEN')),
            'Pemilihan paket soal tidak boleh ikut terbuka saat siklus berjalan.',
        );
    }

    public function test_perhitungan_kalibrasi_dan_penerbitan_boleh_sejak_siklus_dibuka(): void
    {
        /* DIUBAH DENGAN SENGAJA, dan uji ini yang memegang alasannya.

           Semula ketiganya menuntut siklus DITUTUP. Itu membuat hasil setiap orang
           menunggu orang terakhir: peserta yang seluruh penilainya sudah mengisi tidak
           bisa dihitung, apalagi diterbitkan, hanya karena ada yang belum. Padahal
           nilainya tidak saling bergantung sama sekali — grade dan kuadran lahir dari
           pita ABSOLUT, bukan dari peringkat terhadap peserta lain, jadi tidak ada satu
           angka pun yang berubah ketika orang berikutnya menyusul.

           Yang menjaga agar tak ada hasil setengah jadi bukan status siklusnya melainkan
           `pastikanPesertaLengkap()`: selagi jendela terbuka, peserta yang penilainya
           belum lengkap tetap ditolak. Jadi yang dilonggarkan di sini SIKLUSNYA, bukan
           kelengkapan orangnya. */
        foreach ([PaCycleGate::HITUNG_HASIL, PaCycleGate::KALIBRASI, PaCycleGate::TERBITKAN] as $operasi) {
            $this->assertTrue($this->gate->boleh($operasi, $this->siklus('OPEN')), "$operasi di OPEN");
            $this->assertTrue($this->gate->boleh($operasi, $this->siklus('CLOSED')), "$operasi di CLOSED");

            /* Arah sebaliknya tetap dijaga: sebelum dibuka memang belum ada apa pun untuk
               dihitung, dan tanpa penegasan ini ujinya akan hijau seandainya gerbangnya
               mengizinkan SEGALANYA. */
            $this->assertFalse($this->gate->boleh($operasi, $this->siklus('DRAFT')), "$operasi di DRAFT");
            $this->assertFalse($this->gate->boleh($operasi, $this->siklus('LOCKED')), "$operasi di LOCKED");
        }
    }

    /**
     * Kalibrasi Penilai hanya selama pengisian terbuka.
     *
     * Berbeda dari KALIBRASI (forum) yang juga menerima CLOSED: penutupan pengisian melepas
     * seluruh lembar TERKUMPUL menjadi SELESAI, jadi sesudah CLOSED tidak ada lagi lembar yang
     * boleh diubah penilainya.
     */
    public function test_kalibrasi_penilai_hanya_saat_open(): void
    {
        $this->assertTrue($this->gate->boleh(PaCycleGate::KALIBRASI_PENILAI, $this->siklus('OPEN')));

        foreach (['DRAFT', 'PREPARED', 'LOCKED', 'CLOSED', 'PUBLISHED'] as $status) {
            $this->assertFalse(
                $this->gate->boleh(PaCycleGate::KALIBRASI_PENILAI, $this->siklus($status)),
                "KALIBRASI_PENILAI tidak boleh diizinkan saat {$status}."
            );
        }
    }

    public function test_kunci_kurva_hanya_saat_closed(): void
    {
        $this->assertTrue($this->gate->boleh(PaCycleGate::KUNCI_KURVA, $this->siklus('CLOSED')));

        foreach (['DRAFT', 'PREPARED', 'LOCKED', 'OPEN', 'PUBLISHED'] as $status) {
            $this->assertFalse(
                $this->gate->boleh(PaCycleGate::KUNCI_KURVA, $this->siklus($status)),
                "KUNCI_KURVA tidak boleh saat {$status} — kurva dibekukan sesudah pengisian ditutup.",
            );
        }
    }

    public function test_buka_kurva_hanya_saat_open_atau_closed(): void
    {
        foreach ([PaCycleGate::BUKA_KURVA] as $op) {
            foreach (['OPEN', 'CLOSED'] as $status) {
                $this->assertTrue($this->gate->boleh($op, $this->siklus($status)), "{$op} harus boleh saat {$status}.");
            }

            foreach (['DRAFT', 'PREPARED', 'LOCKED', 'PUBLISHED'] as $status) {
                $this->assertFalse($this->gate->boleh($op, $this->siklus($status)), "{$op} tidak boleh saat {$status}.");
            }
        }
    }

    public function test_siklus_yang_diarsipkan_tidak_mengizinkan_apa_pun(): void
    {
        $this->assertSame([], $this->gate->operasiYangBoleh($this->siklus('ARCHIVED')));
    }

    public function test_pastikan_boleh_melempar_dan_menyebut_status_serta_izinnya(): void
    {
        try {
            $this->gate->pastikanBoleh(PaCycleGate::TULIS_OVERRIDE, $this->siklus('LOCKED'));
            $this->fail('Menulis override pada siklus terkunci seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('LOCKED', $e->getMessage());
            $this->assertStringContainsString('DRAFT', $e->getMessage());
        }
    }

    public function test_operasi_yang_tidak_dikenal_ditolak_bukan_diizinkan(): void
    {
        // Salah ketik nama operasi harus gagal keras. Kalau ia lolos gerbang, seluruh
        // pemanggil yang salah ketik akan tampak aman padahal tidak dijaga sama sekali.
        $this->expectException(RuntimeException::class);
        $this->gate->boleh('SUNTING_SALINAN_TYPO', $this->siklus('LOCKED'));
    }

    public function test_siklus_yang_tidak_ada_melempar(): void
    {
        $this->expectException(RuntimeException::class);
        $this->gate->boleh(PaCycleGate::SUNTING_SALINAN, 999999999);
    }

    public function test_gerbang_bisa_dipanggil_lewat_peserta(): void
    {
        $cycleId = $this->siklus('LOCKED');
        $participantId = $this->peserta($cycleId);

        // Pemanggil yang memegang peserta tidak perlu mencari siklusnya sendiri —
        // dan karena itu tidak punya kesempatan lupa memanggil gerbangnya.
        $this->expectException(RuntimeException::class);
        $this->gate->pastikanBolehUntukPeserta(PaCycleGate::TULIS_OVERRIDE, $participantId);
    }

    public function test_daftar_operasi_menyusut_seiring_siklus_maju(): void
    {
        $draft = count($this->gate->operasiYangBoleh($this->siklus('DRAFT')));
        $locked = count($this->gate->operasiYangBoleh($this->siklus('LOCKED')));
        $published = count($this->gate->operasiYangBoleh($this->siklus('PUBLISHED')));

        $this->assertGreaterThan($locked, $draft);
        $this->assertGreaterThan($published, $locked);
        $this->assertSame(0, $published);
    }

    private function siklus(string $status): int
    {
        $kode = 'UJI-GATE-'.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => $kode,
            'Nama_Cycle' => 'Siklus Uji Gerbang',
            'Tahun' => 2026,
            'Status_Cycle' => $status,
        ]);

        $this->gate->lupakanMemo();

        return (int) DB::table('N_PA_Cycle')->where('Kode_Cycle', $kode)->value('Id_PA_Cycle');
    }

    private function peserta(int $cycleId): int
    {
        $karyawan = DB::table('Karyawan')->where('Aktif', 'Y')->orderBy('Kode_Karyawan')
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan']);
        $level = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');

        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $cycleId,
            'Kode_Perusahaan' => $karyawan->Kode_Perusahaan,
            'Kode_Karyawan' => $karyawan->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $karyawan->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $karyawan->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $level,
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        return (int) DB::table('N_PA_Participant')->where('PA_Cycle_Id', $cycleId)->value('Id_PA_Participant');
    }
}
