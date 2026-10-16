<?php

namespace Tests\Integration\LMS;

use App\Http\Controllers\LMS\Training\Scheduling\TrainingSchedulingController;
use App\Models\ESS\Employee\Models\User;
use App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox;
use App\Services\SYSTEM\TaskProcessing\CloudTaskGateway;
use App\Services\SYSTEM\TaskProcessing\LmsPenjadwalanSyncThirdPartyHandler;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Sunting penjadwalan tidak boleh memanggil HC Learn selagi transaksi terbuka.
 *
 * `update()` dulu memanggil `Http::timeout(8)->post()` di dalam perulangan
 * bersarang, di dalam transaksi yang terbuka sejak awal metode. Selama
 * panggilan itu berlangsung seluruh kunci transaksi tetap dipegang, dan
 * lamanya ditentukan pihak lain: 8 detik dikali jumlah aktivitas berujian.
 * Pada basis data tanpa RCSI, setiap pembaca baris yang sama menunggu selama
 * itu. Itu temuan #2 di register audit.
 *
 * Sekarang pekerjaannya dititipkan ke outbox di dalam transaksi yang sama —
 * jadi atau batal bersama datanya — lalu dikerjakan handler sesudah commit,
 * dengan percobaan ulang bila gagal sementara.
 *
 * DUA SISI, DAN KEDUANYA PERLU.
 * Uji pertama menuntut controller TIDAK memanggil sama sekali. Uji kedua
 * menuntut handler memanggil pada tingkat transaksi GARIS DASAR. Tanpa yang
 * kedua, memindahkan panggilan ke handler yang ternyata membungkus dirinya
 * sendiri dalam transaksi akan lolos tanpa memperbaiki apa pun.
 */
class PenjadwalanUpdateKunciTest extends TestCase
{
    use DatabaseTransactions;

    /** Tingkat transaksi yang tercatat SAAT tiap panggilan keluar dilakukan. */
    private array $tingkatSaatPanggil = [];

    /**
     * Garis dasar tingkat transaksi di dalam uji ini.
     *
     * BUKAN nol: `DatabaseTransactions` sudah membuka satu transaksi pembungkus
     * supaya uji ini tak meninggalkan jejak. Menuntut nol berarti menuntut yang
     * tak mungkin tercapai — penjaganya merah selamanya dan mendorong
     * "perbaikan" yang mengejar hijau yang tak ada.
     */
    private int $garisDasar = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $aktor = User::query()->where('Id_Users', 4)->first();
        if (! $aktor) {
            $this->markTestSkipped('User uji tidak ada di data dev.');
        }

        Auth::login($aktor);
        $this->garisDasar = DB::transactionLevel();

        /* Gerbang Cloud Tasks diganti boneka: dispatch sesudah commit tak boleh
           membuat task sungguhan di proyek orang. */
        $this->app->bind(CloudTaskGateway::class, fn () => new class implements CloudTaskGateway
        {
            public function dispatch(string $queue, string $url, array $payload, ?\DateTimeInterface $availableAt = null): string
            {
                return 'tugas-boneka';
            }
        });

        Http::fake(function () {
            $this->tingkatSaatPanggil[] = DB::transactionLevel();

            return Http::response(['result' => [[
                'Kode_Karyawan' => 'X',
                'Id_Ujian_Token' => 'TOKEN-UJI-BARU',
                'Payload_Json' => ['Link_Ujian' => 'https://contoh.test/ujian'],
            ]]], 200);
        });
    }

    /** Penjadwalan nyata yang punya aktivitas berujian pihak ketiga. */
    private function acuan(): ?object
    {
        return DB::table('N_LMS_Penjadwalan as p')
            ->join('N_LMS_Penjadwalan_Detail as pd', 'p.Id_Penjadwalan', '=', 'pd.Id_Penjadwalan')
            ->join('N_LMS_Penjadwalan_Det as pdt', 'pd.Id_Penjadwalan_Detail', '=', 'pdt.Id_Penjadwalan_Detail')
            ->join('N_LMS_Penjadwalan_Integrasi as i', 'pdt.Id_Penjadwalan_Det', '=', 'i.Id_Penjadwalan_Det')
            ->whereNotNull('i.Id_Ujian_Token')
            ->select(
                'p.Id_Penjadwalan',
                'p.Kode_Karyawan',
                'p.Id_Master_Paket',
                'pd.Id_Materi_Detail',
                'pd.Id_Master_Paket_Detail',
                'pdt.Id_Materi_Det',
                'pdt.Id_Penjadwalan_Det',
                'i.Id_Penjadwalan_Integrasi',
                'i.Id_Master_Ujian',
                'i.Id_Ujian_Token',
            )
            ->first();
    }

    public function test_update_menitipkan_ke_outbox_dan_tidak_memanggil_pihak_ketiga(): void
    {
        $acuan = $this->acuan();
        if (! $acuan) {
            $this->markTestSkipped('Tak ada penjadwalan berujian pihak ketiga di data dev.');
        }

        $sebelum = SystemTaskOutbox::query()
            ->where('Task_Type', 'LMS_PENJADWALAN_SYNC_THIRD_PARTY')
            ->count();

        $hash = fn ($id) => Hashids::connection('custom')->encode($id);

        app(TrainingSchedulingController::class)->update(Request::create(
            '/api/v1/lms/penjadwalan/update',
            'POST',
            [
                'id_penjadwalan' => $hash($acuan->Id_Penjadwalan),
                'karyawan' => [$acuan->Kode_Karyawan],
                'id_training' => $hash($acuan->Id_Master_Paket),
                'waktu_mulai' => '2026-09-09 08:00:00',
                'waktu_akhir' => '2026-09-16 08:00:00',
                'sub_materi' => [[
                    'Urutan' => 1,
                    'Id_Materi_Detail' => $hash($acuan->Id_Materi_Detail),
                    'Id_Master_Paket_Detail' => $hash($acuan->Id_Master_Paket_Detail),
                    'Aktivitas' => [[
                        'id_materi_det' => $hash($acuan->Id_Materi_Det),
                        'flag_third_party' => 'Y',
                        'id_paket_soal' => $acuan->Id_Master_Ujian,
                        'flag_camera' => 'T',
                    ]],
                ]],
                'Jenis_User' => 'internal',
                'Flag_Learning_Gain' => 'T',
            ],
        ));

        $sesudah = SystemTaskOutbox::query()
            ->where('Task_Type', 'LMS_PENJADWALAN_SYNC_THIRD_PARTY')
            ->count();

        /* Premis: kalau tak ada titipan yang terbuat, penegasan "nol panggilan"
           di bawah lolos hanya karena tak ada pekerjaan sama sekali. */
        $this->assertGreaterThan(
            $sebelum,
            $sesudah,
            'Tak satu pun baris outbox LMS_PENJADWALAN_SYNC_THIRD_PARTY terbuat, jadi uji ini tak '
            .'membuktikan apa-apa. Aktivitas berujian seharusnya menitipkan pekerjaan sinkronnya.'
        );

        $this->assertSame(
            [],
            $this->tingkatSaatPanggil,
            count($this->tingkatSaatPanggil).' panggilan ke HC Learn dilakukan langsung dari update(). '
            .'Panggilan keluar harus dititipkan ke outbox dan dikerjakan handler sesudah commit — '
            .'memanggil dari sini menahan kunci transaksi selama jaringan menjawab.'
        );
    }

    public function test_handler_memanggil_di_luar_transaksi(): void
    {
        $acuan = $this->acuan();
        if (! $acuan) {
            $this->markTestSkipped('Tak ada penjadwalan berujian pihak ketiga di data dev.');
        }

        $muatan = [
            'mode' => 'update',
            'id_integrasi' => $acuan->Id_Penjadwalan_Integrasi,
            'token_lama' => $acuan->Id_Ujian_Token,
            'id_master_ujian' => $acuan->Id_Master_Ujian,
            'waktu_mulai' => '2026-09-09 08:00:00',
            'waktu_akhir' => '2026-09-16 08:00:00',
            'flag_camera' => false,
            'user_agent' => 'uji',
            'deleted_tokens' => [],
            'kode_karyawan' => $acuan->Kode_Karyawan,
            'app_id' => 'app-uji',
            'kode_karyawan_auth' => 'SYSTEM',
        ];

        app(LmsPenjadwalanSyncThirdPartyHandler::class)->handle(new SystemTaskOutbox, $muatan);

        $this->assertNotEmpty(
            $this->tingkatSaatPanggil,
            'Handler tidak memanggil HC Learn sama sekali; uji ini tak membuktikan apa-apa.'
        );

        $didalam = array_values(array_filter(
            $this->tingkatSaatPanggil,
            fn ($n) => $n > $this->garisDasar,
        ));

        $this->assertSame(
            [],
            $didalam,
            count($didalam).' panggilan dilakukan SELAGI transaksi terbuka (tingkat: '
            .implode(', ', $didalam).', garis dasar '.$this->garisDasar.'). Handler harus memanggil '
            .'di luar transaksi, lalu menulis tokennya sesudah jawaban diterima.'
        );

        $this->assertSame(
            'TOKEN-UJI-BARU',
            (string) DB::table('N_LMS_Penjadwalan_Integrasi')
                ->where('Id_Penjadwalan_Integrasi', $acuan->Id_Penjadwalan_Integrasi)
                ->value('Id_Ujian_Token'),
            'Token dari HC Learn tidak tertulis kembali; sinkronnya berhenti separuh jalan.'
        );
    }
}
