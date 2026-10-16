<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use App\Services\KPI\KpiEnforcementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Nomor transaksi KPI: dibuat oleh counter atomik, dan dibuat DI LUAR transaksi.
 *
 * Bentuk lamanya `MAX(CAST(RIGHT(No_Transaksi,4) AS INT))` dengan
 * `lockForUpdate`. Di SQL Server itu `UPDLOCK, HOLDLOCK`, dan karena
 * `KPI_Update_Performance_Sementara` tidak punya indeks pada kolom nomor, yang
 * terkunci bukan satu baris melainkan RENTANG seluruh baris yang dipindai —
 * tertahan sampai transaksi bisnis pemanggilnya commit, bukan sampai nomornya
 * jadi.
 *
 * Bukti dari `N_HRIS_Request_Diagnostics`, 19 Agustus: satu permintaan
 * `api/v1/update-performance/store/temporary` menunggu **13,7 detik** di kueri
 * itu — padahal tabelnya cuma 124 baris, jadi itu antrean, bukan pemindaian.
 * Di jendela yang sama `UPDATE KPI_Pengajuan ... WHERE No_Transaksi = ?`
 * menunggu **17,1 detik** untuk pekerjaan yang mestinya seketika.
 *
 * YANG DIJAGA DI SINI BUKAN KECEPATANNYA.
 * Kecepatan di bawah beban tak bisa direproduksi dengan jujur di basis data
 * dev bersama. Yang dijaga dua sifat yang MENYEBABKAN kecepatan itu, dan satu
 * lagi yang jauh lebih penting daripada keduanya: nomor tidak boleh kembar.
 */
class NomorTransaksiKpiTest extends TestCase
{
    use DatabaseTransactions;

    /** Kedua berkas yang mencetak nomor seri KPI; keduanya disisir. */
    private const BERKAS = [
        'app/Http/Controllers/KPI/Performance/Update/UpdatePerformanceController.php',
        'app/Services/KPI/KpiEnforcementService.php',
    ];

    private function panggilPrivat(string $metode, string $kelas = UpdatePerformanceController::class): string
    {
        $m = new ReflectionMethod($kelas, $metode);
        $m->setAccessible(true);

        return (string) $m->invoke(app($kelas));
    }

    private function isiBerkas(int $indeks = 0): string
    {
        return (string) file_get_contents(base_path(self::BERKAS[$indeks]));
    }

    /**
     * Inti keselamatannya: nomor baru harus selalu di ATAS yang sudah ada.
     *
     * Counter yang salah semai akan mengulang nomor yang sudah terpakai, dan
     * itu jauh lebih buruk daripada lambat. Diperiksa terhadap isi tabel yang
     * sebenarnya, bukan terhadap counter-nya sendiri — memeriksa counter dengan
     * counter tidak membuktikan apa pun.
     *
     * @dataProvider seriNomor
     */
    public function test_nomor_baru_selalu_di_atas_yang_sudah_ada(
        string $metode,
        string $tabel,
        string $kolom,
        string $awalan,
    ): void {
        $now = Carbon::now();
        $prefix = $awalan.'-'.$now->format('m').'-'.$now->format('Y').'-';

        /* PREMISNYA DIBUAT, TIDAK DITUNGGU.
           Awal bulan, prefix bulan berjalan belum punya satu baris pun — dan
           dengan premis kosong, penyemaian yang SALAH pun lolos karena apa pun
           lebih besar daripada nol. Sabotase pertama saya memang lolos begitu.
           Jadi satu baris bernomor tinggi disisipkan lebih dulu, dan baris
           counter untuk prefix ini dihapus supaya jalur penyemaian benar-benar
           dilewati. Keduanya ikut dibatalkan bersama transaksi uji. */
        $patokan = 9001;
        DB::table('HRIS_Counter')->where('prefix', $prefix)->delete();
        DB::table($tabel)->insert([$kolom => $prefix.$patokan]);

        $tertinggiSebelum = (int) DB::table($tabel)
            ->where($kolom, 'like', $prefix.'%')
            ->max(DB::raw("CAST(RIGHT({$kolom}, 4) AS INT)"));

        $this->assertSame(
            $patokan,
            $tertinggiSebelum,
            'Patokan tidak terbaca kembali; uji ini tak bisa membuktikan apa pun tanpa premis itu.'
        );

        $dihasilkan = [];
        for ($i = 0; $i < 3; $i++) {
            $dihasilkan[] = $this->panggilPrivat($metode);
        }

        $urut = [];
        foreach ($dihasilkan as $nomor) {
            $this->assertStringStartsWith(
                $prefix,
                $nomor,
                "Nomor '{$nomor}' tidak berawalan '{$prefix}'."
            );

            $angka = (int) substr($nomor, -4);

            $this->assertGreaterThan(
                $tertinggiSebelum,
                $angka,
                "Nomor '{$nomor}' berada DI BAWAH atau SAMA DENGAN nomor tertinggi yang sudah ada di "
                ."{$tabel} ({$tertinggiSebelum}). Counter kemungkinan salah semai — ini menghasilkan "
                .'nomor dokumen kembar, jauh lebih buruk daripada lambat.'
            );

            $urut[] = $angka;
        }

        $this->assertSame(
            $urut,
            array_values(array_unique($urut)),
            'Tiga panggilan berturut-turut menghasilkan nomor yang sama: '.implode(', ', $urut).'.'
        );

        $terurut = $urut;
        sort($terurut);
        $this->assertSame($terurut, $urut, 'Nomor tidak menaik: '.implode(', ', $urut).'.');
    }

    /**
     * Seri AKPI punya DUA penulis, dan keduanya harus mencetak dari sumber yang
     * sama.
     *
     * Ini bukan uji performa; ini uji tabrakan. Selama keduanya memakai
     * `MAX(...) + lockForUpdate`, keduanya saling mengunci sehingga tak pernah
     * bertabrakan. Memindahkan SALAH SATU saja ke counter atomik memutus
     * koordinasi itu: counter membagikan 0006 sementara MAX+1 juga menghitung
     * 0006. Uji ini memastikan keduanya tidak pernah terpisah lagi.
     */
    public function test_kedua_penulis_seri_akpi_mencetak_dari_sumber_yang_sama(): void
    {
        $now = Carbon::now();
        $prefix = 'AKPI-'.$now->format('m').'-'.$now->format('Y').'-';

        $patokan = 9001;
        DB::table('HRIS_Counter')->where('prefix', $prefix)->delete();
        DB::table('KPI_Update_Performance')->insert(['No_Transaksi' => $prefix.$patokan]);

        /* Diselang-seling: kalau keduanya memakai sumber berbeda, keduanya akan
           menghasilkan angka yang sama dan bentrokannya langsung terlihat. */
        $nomor = [
            $this->panggilPrivat('generateNewTransactionNumber'),
            $this->panggilPrivat('generateNewTransactionNumber', KpiEnforcementService::class),
            $this->panggilPrivat('generateNewTransactionNumber'),
            $this->panggilPrivat('generateNewTransactionNumber', KpiEnforcementService::class),
        ];

        $angka = array_map(fn ($n) => (int) substr($n, -4), $nomor);

        foreach ($angka as $satu) {
            $this->assertGreaterThan(
                $patokan,
                $satu,
                'Nomor '.$prefix.$satu.' berada di bawah nomor yang sudah ada ('.$patokan.').'
            );
        }

        $this->assertSame(
            $angka,
            array_values(array_unique($angka)),
            'Dua penulis seri AKPI menghasilkan nomor yang SAMA ('.implode(', ', $angka).'). '
            .'Salah satunya kemungkinan masih memakai MAX(...) + lockForUpdate sementara yang lain sudah '
            .'memakai counter atomik — keduanya berhenti berkoordinasi dan nomor dokumen jadi kembar.'
        );

        $terurut = $angka;
        sort($terurut);
        $this->assertSame($terurut, $angka, 'Nomor tidak menaik: '.implode(', ', $angka).'.');
    }

    public static function seriNomor(): array
    {
        return [
            'sementara TAKPI' => [
                'generateNomorSementara',
                'KPI_Update_Performance_Sementara',
                'No_Transaksi_Sementara',
                'TAKPI',
            ],
            'final AKPI' => [
                'generateNewTransactionNumber',
                'KPI_Update_Performance',
                'No_Transaksi',
                'AKPI',
            ],
        ];
    }

    /**
     * Sifat pertama: pembuatan nomor tidak boleh lagi mengunci tabel bisnis.
     *
     * `lockForUpdate` yang dipasangkan dengan `MAX(CAST(RIGHT(...)))` adalah
     * bentuk yang tepat dilarang di sini. Keduanya boleh muncul sendiri-sendiri
     * di berkas ini untuk keperluan lain; yang dilarang pasangannya.
     */
    public function test_pembuatan_nomor_tidak_mengunci_tabel_bisnis(): void
    {
        foreach (array_keys(self::BERKAS) as $indeks) {
            $this->periksaTakAdaKunciPembuatanNomor($indeks);
        }
    }

    private function periksaTakAdaKunciPembuatanNomor(int $indeks): void
    {
        $isi = $this->isiBerkas($indeks);

        /* Baris berkomentar tidak dihitung: berkas ini menyimpan potongan lama
           sebagai catatan, dan menuduhnya akan membuat penjaga ini berbohong. */
        $hidup = [];
        foreach (explode("\n", $isi) as $nomorBaris => $baris) {
            $bersih = ltrim($baris);
            if ($bersih === '' || str_starts_with($bersih, '//') || str_starts_with($bersih, '*')) {
                continue;
            }
            $hidup[$nomorBaris + 1] = $baris;
        }

        $blok = implode("\n", $hidup);

        /* Pola yang dicari: lockForUpdate dan CAST(RIGHT dalam satu rangkaian
           pemanggilan yang sama — dipisah paling banyak beberapa baris. */
        $ketemu = preg_match_all(
            '/lockForUpdate\(\)(?:[^;]{0,400}?)CAST\(RIGHT\(|CAST\(RIGHT\((?:[^;]{0,400}?)lockForUpdate\(\)/s',
            $blok,
            $cocok
        );

        $this->assertSame(
            0,
            $ketemu,
            'Pada '.self::BERKAS[$indeks].": pola lama pembuatan nomor muncul kembali {$ketemu}x: "
            .'MAX(CAST(RIGHT(...))) dengan lockForUpdate. '
            .'Di SQL Server itu UPDLOCK+HOLDLOCK, dan tanpa indeks pada kolom nomor ia mengunci rentang '
            .'seluruh baris yang dipindai sampai transaksi pemanggilnya commit. Pakai '
            .'TransactionNumberService (counter atomik di HRIS_Counter).'
        );
    }

    /**
     * Sifat kedua, dan tanpa ini yang pertama tak ada gunanya.
     *
     * Counter atomik hanya melepas kuncinya seketika bila dipanggil DI LUAR
     * transaksi. Dipanggil di dalam `DB::beginTransaction()`, kunci satu baris
     * counter itu tertahan sampai commit — dan semua yang submit bersamaan
     * kembali antre, persis seperti sebelum diperbaiki. Perubahannya jadi
     * terlihat benar sambil tidak memperbaiki apa pun.
     */
    public function test_nomor_diambil_sebelum_transaksi_dibuka(): void
    {
        $baris = explode("\n", $this->isiBerkas());

        $panggilan = [];
        $bukaTransaksi = [];

        foreach ($baris as $i => $isi) {
            $bersih = ltrim($isi);
            if (str_starts_with($bersih, '//') || str_starts_with($bersih, '*')) {
                continue;
            }
            if (str_contains($isi, '$this->generateNomorSementara()')) {
                $panggilan[] = $i + 1;
            }
            if (str_contains($isi, 'DB::beginTransaction()')) {
                $bukaTransaksi[] = $i + 1;
            }
        }

        $this->assertNotEmpty($panggilan, 'Tak ada pemanggilan generateNomorSementara() — penjaga ini kehilangan sasarannya.');

        foreach ($panggilan as $barisPanggil) {
            $sesudahnya = array_filter($bukaTransaksi, fn ($b) => $b > $barisPanggil);

            $this->assertNotEmpty(
                $sesudahnya,
                "generateNomorSementara() di baris {$barisPanggil} tidak diikuti DB::beginTransaction() mana pun. "
                .'Kalau pemanggilnya berpindah ke dalam transaksi, penjaga ini harus ikut diperbarui — '
                .'jangan dilonggarkan tanpa memeriksa ulang kuncinya.'
            );

            $jarak = min($sesudahnya) - $barisPanggil;

            /* Jaraknya dibatasi supaya "sebelum transaksi" berarti benar-benar
               tepat sebelumnya, bukan sekadar ada beginTransaction di suatu
               tempat jauh di bawah. */
            $this->assertLessThan(
                12,
                $jarak,
                "generateNomorSementara() di baris {$barisPanggil} terpisah {$jarak} baris dari "
                .'DB::beginTransaction() berikutnya. Pastikan ia memang mendahului transaksi yang sama.'
            );
        }
    }
}
