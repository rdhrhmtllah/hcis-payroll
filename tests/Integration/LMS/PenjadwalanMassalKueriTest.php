<?php

namespace Tests\Integration\LMS;

use App\Jobs\LMS\Training\Scheduling\ProcessBulkPenjadwalan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Penerbitan penjadwalan massal: biaya jalur ujian pihak ketiga tidak boleh
 * tumbuh per peserta.
 *
 * Job-nya sebenarnya sudah digarap serius — peserta dipotong per 20, tiga tabel
 * utama disisipkan massal ber-`OUTPUT`, syarat dibatch 100. Tapi jalur ujian
 * pihak ketiga masih menembak dua kueri untuk TIAP aktivitas TIAP peserta: satu
 * `insertGetId` ke `N_LMS_Penjadwalan_Integrasi`, lalu satu `update` untuk tiap
 * butir jawaban API.
 *
 * Hukum pertumbuhannya diukur, bukan ditaksir:
 *
 *   peserta x aktivitas   sebelum   sesudah
 *              5 x 2         28        —
 *             10 x 3         68        12
 *             40 x 6        496        32
 *
 * Pada data nyata rute `api/v1/lms/penjadwalan/store` pernah tercatat **3.683
 * kueri dalam satu permintaan, 251 detik**.
 *
 * KENAPA INI DIUJI LEWAT JOB, BUKAN LEWAT RUTENYA.
 * Menerbitkan penjadwalan sungguhan memanggil API HC Learn dan membuat
 * penugasan ujian NYATA di sistem pihak ketiga — efek keluar yang tidak bisa
 * ditarik kembali oleh uji. Di sini HTTP-nya dipalsukan dan job-nya dipanggil
 * langsung, sehingga tak ada satu pun panggilan keluar dan tak ada baris yang
 * tertinggal.
 */
class PenjadwalanMassalKueriTest extends TestCase
{
    use DatabaseTransactions;

    private const JUMLAH_PESERTA = 6;

    private const JUMLAH_AKTIVITAS = 3;

    /** @var array<int,string> */
    private array $karyawan = [];

    /** @var array<int,object> */
    private array $acuan = [];

    protected function setUp(): void
    {
        parent::setUp();

        /* Acuan diambil dari data yang memang ada. Id karangan akan ditolak
           foreign key, dan yang teruji jadi jalur galat — bukan jalur normal. */
        $acuan = DB::table('N_LMS_Master_Paket_Detail as pd')
            ->join('N_LMS_Materi_Detail as md', 'pd.Id_Master_Training', '=', 'md.Id_Master_Training')
            ->join('N_LMS_Materi_Det as mt', 'md.Id_Materi_Detail', '=', 'mt.Id_Materi_Detail')
            ->where('md.Flag_Aktif', 'Y')
            ->where('mt.Flag_Aktif', 'Y')
            ->select('pd.Id_Master_Paket', 'pd.Id_Master_Paket_Detail', 'md.Id_Materi_Detail', 'mt.Id_Materi_Det')
            ->orderBy('mt.Id_Materi_Det')
            ->limit(self::JUMLAH_AKTIVITAS)
            ->get();

        if ($acuan->count() < self::JUMLAH_AKTIVITAS) {
            $this->markTestSkipped('Data paket/materi dev tak cukup untuk membentuk payload uji.');
        }

        $karyawan = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNotNull('Kode_Karyawan')
            ->orderBy('Kode_Karyawan')
            ->limit(self::JUMLAH_PESERTA)
            ->pluck('Kode_Karyawan')
            ->all();

        if (count($karyawan) < self::JUMLAH_PESERTA) {
            $this->markTestSkipped('Karyawan aktif di data dev kurang dari '.self::JUMLAH_PESERTA.'.');
        }

        $this->acuan = $acuan->all();
        $this->karyawan = array_map(fn ($k) => (string) $k, $karyawan);
    }

    /**
     * Menjalankan job dengan HTTP dipalsukan, mengembalikan daftar SQL-nya.
     *
     * @return array<int,string>
     */
    private function jalankanJob(): array
    {
        $hash = fn ($id) => Hashids::connection('custom')->encode($id);
        $pertama = $this->acuan[0];

        $aktivitas = [];
        foreach ($this->acuan as $i => $baris) {
            $aktivitas[] = [
                'id_materi_det' => $hash($baris->Id_Materi_Det),
                'flag_third_party' => 'Y',
                'id_paket_soal' => 'UJI-PAKET-'.$i,
                'flag_camera' => 'T',
            ];
        }

        $payload = [
            'karyawan' => $this->karyawan,
            'id_training' => $hash($pertama->Id_Master_Paket),
            /* Dipatok, bukan now(): waktu yang bergerak membuat dua jalannya
               tak bisa dibandingkan. */
            'waktu_mulai' => '2026-09-09 08:00:00',
            'waktu_akhir' => '2026-09-16 08:00:00',
            'id_rumus_penilaian' => null,
            'sub_materi' => [[
                'Urutan' => 1,
                'Id_Materi_Detail' => $hash($pertama->Id_Materi_Detail),
                'Id_Master_Paket_Detail' => $hash($pertama->Id_Master_Paket_Detail),
                'Aktivitas' => $aktivitas,
            ]],
            'Jenis_User' => 'internal',
            'Flag_Learning_Gain' => 'T',
        ];

        /* Tiap peserta menerima token yang BERBEDA. Itu bukan hiasan: kalau
           tokennya seragam, pemetaan yang tertukar antar peserta tak akan
           terlihat sama sekali. */
        Http::fake(function () {
            $hasil = [];
            foreach ($this->karyawan as $kode) {
                $hasil[] = [
                    'Kode_Karyawan' => $kode,
                    'Id_Ujian_Token' => 'TOKEN-'.$kode,
                    'Payload_Json' => ['Link_Ujian' => 'https://contoh.test/ujian?otp=1'],
                ];
            }

            return Http::response(['result' => $hasil], 200);
        });

        /* Job notifikasi ditahan: pada penggerak cloudtasks, dispatch-nya
           membuat Cloud Task sungguhan. */
        Queue::fake();

        $processId = (string) Str::uuid();
        DB::table('N_LMS_Job_Payloads')->insert([
            'Process_Id' => $processId,
            'Payload_Json' => json_encode($payload),
        ]);

        $kueri = [];
        DB::listen(function ($q) use (&$kueri) {
            $kueri[] = $q->sql;
        });

        (new ProcessBulkPenjadwalan($processId, 'app-uji', 'SYSTEM', 4, 'uji', 'contoh.test'))->handle();

        return $kueri;
    }

    /**
     * Baris integrasi yang terbentuk oleh jalannya job ini.
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    private function barisIntegrasi()
    {
        $idPenjadwalan = DB::table('N_LMS_Penjadwalan')
            ->whereIn('Kode_Karyawan', $this->karyawan)
            ->where('Waktu_Enroll', '2026-09-09 08:00:00')
            ->pluck('Id_Penjadwalan');

        $idDetail = DB::table('N_LMS_Penjadwalan_Detail')
            ->whereIn('Id_Penjadwalan', $idPenjadwalan)
            ->pluck('Id_Penjadwalan_Detail');

        return DB::table('N_LMS_Penjadwalan_Integrasi as i')
            ->join('N_LMS_Penjadwalan_Det as d', 'i.Id_Penjadwalan_Det', '=', 'd.Id_Penjadwalan_Det')
            ->join('N_LMS_Penjadwalan_Detail as dd', 'd.Id_Penjadwalan_Detail', '=', 'dd.Id_Penjadwalan_Detail')
            ->join('N_LMS_Penjadwalan as p', 'dd.Id_Penjadwalan', '=', 'p.Id_Penjadwalan')
            ->whereIn('d.Id_Penjadwalan_Detail', $idDetail)
            ->get(['p.Kode_Karyawan', 'i.Id_Master_Ujian', 'i.Id_Ujian_Token', 'i.Payload_Json']);
    }

    public function test_jalur_ujian_pihak_ketiga_tidak_mengueri_per_peserta(): void
    {
        $kueri = $this->jalankanJob();

        $sisip = 0;
        $perbarui = 0;
        foreach ($kueri as $sql) {
            if (stripos($sql, 'N_LMS_Penjadwalan_Integrasi') === false) {
                continue;
            }
            if (stripos($sql, 'INSERT') !== false) {
                $sisip++;
            }
            if (stripos($sql, 'UPDATE') !== false) {
                $perbarui++;
            }
        }

        $peserta = count($this->karyawan);

        /* Ambangnya RELATIF terhadap jumlah peserta. Bentuk lamanya
           menghasilkan peserta x aktivitas untuk MASING-MASING (di sini 18);
           bentuk massal menghasilkan satu penyisipan per potongan 100 baris dan
           satu pembaruan per kelompok ujian — keduanya tak bergerak ketika
           pesertanya bertambah. */
        $this->assertLessThan(
            $peserta,
            $sisip,
            "Penyisipan ke N_LMS_Penjadwalan_Integrasi berjalan {$sisip}x untuk {$peserta} peserta — "
            .'biayanya ikut tumbuh per peserta. Barisnya harus dikumpulkan lalu disisipkan lewat '
            .'sisipkanDenganOutput().'
        );

        $this->assertLessThan(
            $peserta,
            $perbarui,
            "Pembaruan token berjalan {$perbarui}x untuk {$peserta} peserta — biayanya ikut tumbuh per "
            .'peserta. Token harus ditulis sekali per kelompok lewat perbaruiTokenMassal().'
        );
    }

    /**
     * Sisi kedua, dan inilah risiko sebenarnya dari perubahan ini.
     *
     * Bentuk lamanya tahu id integrasi SEKETIKA karena menyisipkan satu per
     * satu. Bentuk massal harus menjodohkannya kembali lewat
     * `Id_Penjadwalan_Det`. Kalau penjodohan itu meleset, token milik orang lain
     * menempel pada peserta yang salah — tak ada galat, tak ada kueri tambahan,
     * dan uji jumlah kueri di atas tetap hijau.
     */
    public function test_token_menempel_pada_peserta_yang_benar(): void
    {
        $this->jalankanJob();

        $baris = $this->barisIntegrasi();

        $diharapkan = count($this->karyawan) * self::JUMLAH_AKTIVITAS;
        $this->assertCount(
            $diharapkan,
            $baris,
            "Seharusnya terbentuk {$diharapkan} baris integrasi (peserta x aktivitas), terbentuk ".count($baris).'.'
        );

        foreach ($baris as $satu) {
            $this->assertSame(
                'TOKEN-'.$satu->Kode_Karyawan,
                (string) $satu->Id_Ujian_Token,
                "Peserta {$satu->Kode_Karyawan} menerima token '{$satu->Id_Ujian_Token}' padahal API "
                ."mengembalikan 'TOKEN-{$satu->Kode_Karyawan}' untuknya. Penjodohan hasil sisip massal "
                .'lewat Id_Penjadwalan_Det meleset.'
            );

            $this->assertNotNull(
                $satu->Payload_Json,
                "Peserta {$satu->Kode_Karyawan} kehilangan Payload_Json — kolomnya bertipe text, dan "
                .'penulisan massal harus tetap mengisinya.'
            );
        }

        /* Tiap peserta harus memegang aktivitas yang berbeda-beda, bukan satu
           ujian yang sama berulang: kalau kelompok ujiannya tertukar, jumlah
           barisnya tetap benar dan hanya ini yang menyadarinya. */
        $perPeserta = $baris->groupBy('Kode_Karyawan');
        foreach ($perPeserta as $kode => $miliknya) {
            $this->assertSame(
                self::JUMLAH_AKTIVITAS,
                $miliknya->pluck('Id_Master_Ujian')->unique()->count(),
                "Peserta {$kode} tidak memegang ".self::JUMLAH_AKTIVITAS.' paket ujian yang berbeda — '
                .'kelompok ujiannya kemungkinan tertukar.'
            );
        }
    }
}
