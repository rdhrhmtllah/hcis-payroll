<?php

namespace App\Console\Commands\LMS\SOP\Operations;

use App\Models\ESS\Employee\Models\Karyawan;
use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopPenjadwalanService;
use App\Services\LMS\SOP\Uat\UatBebanFase;
use App\Services\LMS\SOP\Uat\UatBebanKlien;
use App\Services\LMS\SOP\Uat\UatBebanStatistik;
use App\Services\LMS\SOP\Uat\UatGerbang;
use App\Services\LMS\SOP\Uat\UatPembangunDunia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Uji beban & kunci modul SOP — tiga fase berurutan.
 *
 * PERTANYAANNYA SATU, BUKAN DUA. RCSI mati di Web_HRIS, sehingga pembaca
 * mengantre di belakang penulis. "Tahan 80 orang" dan "operasi massal tidak
 * mengunci" karena itu sebenarnya satu pertanyaan: ketika operasi massal
 * berjalan, apa yang terjadi pada orang yang sedang membaca.
 *
 * Menguji beban dan operasi massal secara TERPISAH tak akan menemukan apa pun —
 * bahayanya lahir justru dari keduanya bersamaan. Fase 1 hanya ada supaya fase 2
 * dan 3 punya pembanding.
 */
class SopBebanJalan extends Command
{
    protected $signature = 'sop:beban-jalan
        {--serentak=50 : Permintaan yang berjalan bersamaan pada tingkat penuh}
        {--detik=60 : Lama tiap fase}
        {--karyawan=50 : Jumlah karyawan yang di-assign lalu dicabut}
        {--url= : Basis URL; bawaannya UAT_BASE_URL}
        {--paket-sama : Operasi massal menghantam paket yang SEDANG DIBACA}';

    protected $description = 'Jalankan uji beban & kunci SOP (3 fase) dan tulis hasilnya ke JSON';

    /** Detik ke berapa dalam fase operasi massal ditembakkan. */
    private const TEMBAK_PADA_DETIK = 20;

    public function handle(UatPembangunDunia $pembangun): int
    {
        try {
            UatGerbang::pastikanTerbuka();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $basis = rtrim((string) ($this->option('url') ?: env('UAT_BASE_URL', 'http://hcis-kpi-grouped.test')), '/');
        $serentak = max(1, (int) $this->option('serentak'));
        $detik = max(5, (int) $this->option('detik'));

        $admin = $this->userDari((string) env('UAT_NIK_ADMIN'));
        if (! $admin) {
            $this->error('UAT_NIK_ADMIN tidak ditemukan atau tak tertaut akun login.');

            return self::FAILURE;
        }

        $sasaran = $pembangun->pilihKaryawanAktif(max(1, (int) $this->option('karyawan')));
        if (count($sasaran) <= 30) {
            $this->warn(sprintf(
                'Hanya %d sasaran — DI BAWAH ambang async 30. Penugasan berjalan inline; '
                .'jalur antrean TIDAK akan tersentuh uji ini.', count($sasaran)
            ));
        }

        /* DUA paket, dan pemisahan ini bukan kerumitan yang bisa dihemat.

           Paket BACA ditugaskan ke SELURUH sasaran: kalau tidak, pembaca yang
           belum ditugaskan menerima 404 — perilaku yang benar untuk paket
           TERBATAS, tapi 404 itu murah, dan fase dasar jadi mengukur penolakan
           alih-alih pembacaan. Pembanding yang terlalu cepat membuat fase 2
           dan 3 terlihat buruk tanpa sebab.

           Paket MASSAL sengaja hanya berisi satu orang, supaya assign() di fase
           2 benar-benar menyisipkan puluhan baris. Kalau sasarannya paket yang
           sama, seluruhnya dilewati sebagai "sudah aktif" dan fase itu tak
           melakukan apa-apa sementara laporannya menyatakan aman.

           Bentuk ini juga yang paling menyerupai kenyataan: orang membaca SOP
           yang satu, sementara admin menugaskan SOP yang lain. */
        /* Pada mode paket-sama, paket baca ditugaskan HANYA kepada pembaca —
           yaitu yang punya akun login. Kalau semua orang sudah ditugaskan sejak
           awal, assign() di fase 2 melewati seluruh sasaran ('sudah aktif') dan
           pencabutan menyasar header kosong: nol ditugaskan, nol dicabut, dan
           seluruh angkanya bagus karena tak ada yang terjadi. */
        $penerimaAwal = $this->option('paket-sama')
            ? array_values(array_diff($sasaran, $this->tanpaAkunLogin($sasaran)))
            : $sasaran;

        $this->info('Membangun paket BACA (ditugaskan ke '.count($penerimaAwal).' orang)…');
        $dunia = $pembangun->bangun(
            (int) $admin->Id_Users, $penerimaAwal, ['Dokumen 1', 'Dokumen 2']);

        /* --paket-sama: bentuk TERKERAS. Operasi massal menghantam baris paket
           yang persis sedang dibaca orang — tabel yang sama, paket yang sama.
           Kalau ada yang bakal mengunci, di sinilah tempatnya.

           Sasarannya SENGAJA orang yang tak punya akun login, sehingga tak
           seorang pun pembaca kehilangan aksesnya di tengah jalan. Tanpa
           pemisahan itu, pencabutan membuat pembaca menerima 404 — perilaku yang
           benar, tapi ia akan terhitung sebagai kegagalan dan menutupi
           pertanyaan sesungguhnya, yaitu soal kunci. */
        $sasaranBaca = $penerimaAwal;

        if ($this->option('paket-sama')) {
            $duniaMassal = $dunia;
            $sasaran = $this->tanpaAkunLogin($sasaran);

            if ($sasaran === []) {
                $this->error('Tak ada karyawan tanpa akun login untuk dijadikan sasaran massal.');

                return self::FAILURE;
            }

            $this->warn('MODE PAKET-SAMA: operasi massal menghantam paket yang sedang dibaca. '
                .'Sasaran '.count($sasaran).' karyawan tanpa akun login.');
        } else {
            $this->info('Membangun paket MASSAL (sasaran assign/cabut)…');
            $duniaMassal = $pembangun->bangun(
                (int) $admin->Id_Users, [$sasaran[0]], ['Dokumen Massal']);
        }

        $jalan = $dunia['jalan'];
        $this->line("  baca: jalan={$jalan} paket={$dunia['paket']} | massal: jalan={$duniaMassal['jalan']} paket={$duniaMassal['paket']}");
        $this->line('  sasaran massal: '.count($sasaran).' karyawan');

        $hasil = [
            'jalan' => $jalan,
            'basis' => $basis,
            'serentak' => $serentak,
            'detik_per_fase' => $detik,
            'karyawan' => count($sasaran),
            'mulai' => now()->toDateTimeString(),
            'fase' => [],
            'transaksi_massal_ms' => [],
        ];

        try {
            $klien = new UatBebanKlien($basis, $this->sesiKaryawan($sasaranBaca), $this->sesiAdmin($admin));

            $hasil['fase'][] = $this->jalankanFase($klien, $dunia, 'dasar', $serentak, $detik, null);

            /* Penjadwalan yang lahir di fase 2 harus dipegang untuk fase 3:
               assign() membuat header BARU tiap panggilan, jadi mencabut header
               awal hanya akan menyentuh satu orang. */
            $penjadwalanBeban = 0;

            $hasil['fase'][] = $this->jalankanFase($klien, $dunia, 'assign', $serentak, $detik,
                function () use ($duniaMassal, $sasaran, $admin, &$hasil, &$penjadwalanBeban) {
                    $mulai = microtime(true);
                    $r = app(SopPenjadwalanService::class)->assign($duniaMassal['paket'], $sasaran, [
                        'nama_batch' => $duniaMassal['tanda'].' Batch Beban',
                        'sumber' => 'MANUAL',
                    ], (int) $admin->Id_Users);
                    $hasil['transaksi_massal_ms'][] = (int) ((microtime(true) - $mulai) * 1000);
                    $penjadwalanBeban = (int) ($r['penjadwalan_id'] ?? 0);
                    $hasil['assign'] = ['ditugaskan' => $r['assigned'] ?? 0, 'dilewati' => $r['skipped'] ?? 0];
                });

            $hasil['fase'][] = $this->jalankanFase($klien, $dunia, 'cabut', $serentak, $detik,
                function () use ($sasaran, $admin, &$hasil, &$penjadwalanBeban) {
                    $mulai = microtime(true);
                    $r = app(SopPenjadwalanService::class)->bulkCabut(
                        $penjadwalanBeban, $sasaran, (int) $admin->Id_Users);
                    $hasil['transaksi_massal_ms'][] = (int) ((microtime(true) - $mulai) * 1000);
                    $hasil['cabut'] = ['dicabut' => $r['dicabut'] ?? 0, 'diminta' => $r['diminta'] ?? 0];
                });
        } catch (Throwable $e) {
            $hasil['galat'] = $e->getMessage();
            $this->error('Jalan uji berhenti: '.$e->getMessage());
        } finally {
            /* Jurnal dilengkapi APA PUN yang terjadi. Kegagalan di tengah jalan
               tetap meninggalkan baris; jurnal yang tak lengkap berarti
               pembersihan berhenti separuh pada foreign key — keadaan yang
               paling sulit dibereskan tangan. */
            $hasil['selesai'] = now()->toDateTimeString();
            $hasil['jalan_massal'] = $duniaMassal['jalan'];

            /* KEDUA jalan dilengkapi. Melewatkan salah satunya meninggalkan
               separuh dunia uji di basis data tanpa jurnal yang menjangkaunya. */
            $pembangun->lengkapiJurnal($jalan, (int) $dunia['penjadwalan'], (int) $dunia['paket']);
            $pembangun->lengkapiJurnal(
                $duniaMassal['jalan'], (int) $duniaMassal['penjadwalan'], (int) $duniaMassal['paket']);

            $berkas = storage_path("app/uat-auto/beban-{$jalan}.json");
            @mkdir(dirname($berkas), 0777, true);
            file_put_contents($berkas, json_encode($hasil, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info("Hasil: {$berkas}");
        }

        return self::SUCCESS;
    }

    /**
     * Satu fase: beban baca berulang selama N detik, dengan operasi massal
     * ditembakkan di tengahnya.
     *
     * Beban DINAIKKAN BERTAHAP (10% → 50% → penuh) supaya kegagalan yang muncul
     * bisa dibedakan: gagal sejak tingkat terendah berarti ada yang rusak, gagal
     * hanya di tingkat penuh berarti memang soal beban.
     */
    private function jalankanFase(
        UatBebanKlien $klien,
        array $dunia,
        string $nama,
        int $serentak,
        int $detik,
        ?callable $operasiMassal,
    ): array {
        $this->info("Fase {$nama} ({$detik} dtk, sampai {$serentak} serentak)…");

        $waitAwal = UatBebanFase::potretWait();
        $mulaiJendela = now()->toDateTimeString();
        $t0 = microtime(true);

        $ms = [];
        $gagal = 0;
        $timeout = 0;
        $contohGagal = [];
        $sudahTembak = $operasiMassal === null;
        $tingkat = [max(1, (int) ($serentak * 0.1)), max(1, (int) ($serentak * 0.5)), $serentak];
        $putaran = 0;

        while ((microtime(true) - $t0) < $detik) {
            $lewat = microtime(true) - $t0;
            $n = $tingkat[min(2, (int) floor($lewat / max(1, $detik / 3)))];

            if (! $sudahTembak && $lewat >= self::TEMBAK_PADA_DETIK) {
                $this->line('  → menembakkan operasi massal di tengah beban');
                $operasiMassal();
                $sudahTembak = true;
            }

            foreach ($klien->tembak($klien->rencanaPermintaan($dunia, $n)) as $r) {
                $ms[] = $r['ms'];
                if ($r['galat'] !== null) {
                    $timeout++;
                    $contohGagal[$r['url'].' | '.$r['galat']] ??= 0;
                    $contohGagal[$r['url'].' | '.$r['galat']]++;
                } elseif ($r['http'] !== 200) {
                    $gagal++;
                    /* URL berikut kodenya dicatat, bukan sekadar dihitung.
                       "4 gagal" tanpa menyebut yang mana memaksa penelusuran
                       ulang dari nol — dan pada uji yang lamanya semenit,
                       menelusuri ulang berarti menjalankan ulang. */
                    $contohGagal[$r['url'].' | HTTP '.$r['http']] ??= 0;
                    $contohGagal[$r['url'].' | HTTP '.$r['http']]++;
                }
            }

            // Potret blocking per putaran; perintahnya sendiri hanya menulis bila ADA blocking.
            Artisan::call('db:health-snapshot');
            $putaran++;
        }

        if (! $sudahTembak) {
            $operasiMassal();
        }

        $selesaiJendela = now()->toDateTimeString();
        $ringkas = UatBebanStatistik::ringkas($ms);
        $blocking = UatBebanFase::blockingDalamJendela($mulaiJendela, $selesaiJendela);

        $this->line(sprintf(
            '  n=%d  p50=%.0f ms  p95=%.0f ms  maks=%.0f ms  gagal=%d  timeout=%d  blocking terlama=%d ms',
            $ringkas['n'], $ringkas['p50'], $ringkas['p95'], $ringkas['maks'],
            $gagal, $timeout, $blocking['terlama_ms']
        ));

        arsort($contohGagal);

        return array_merge($ringkas, [
            'nama' => $nama,
            'contoh_gagal' => array_slice($contohGagal, 0, 8, true),
            'putaran' => $putaran,
            'gagal' => $gagal,
            'timeout' => $timeout,
            'mulai' => $mulaiJendela,
            'selesai' => $selesaiJendela,
            'delta_wait' => UatBebanFase::deltaWait($waitAwal, UatBebanFase::potretWait()),
            'blocking' => $blocking,
        ]);
    }

    /**
     * Karyawan yang TIDAK punya akun login.
     *
     * Mereka tak bisa jadi pembaca, jadi mencabut aksesnya tak mengganggu beban
     * baca yang sedang berjalan — yang tersisa untuk diukur murni soal kunci.
     *
     * @return list<string>
     */
    private function tanpaAkunLogin(array $kode): array
    {
        $punyaAkun = DB::table('Karyawan as k')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->whereIn('k.Kode_Karyawan', $kode)
            ->pluck('k.Kode_Karyawan')
            ->map(fn ($v) => trim((string) $v))
            ->all();

        return array_values(array_diff($kode, $punyaAkun));
    }

    /** @return list<array{name:string,value:string}> */
    private function sesiKaryawan(array $kode): array
    {
        $punyaAkun = DB::table('Karyawan as k')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->whereIn('k.Kode_Karyawan', $kode)
            ->pluck('k.Kode_Karyawan');

        $sesi = [];
        foreach ($punyaAkun as $k) {
            if ($s = $this->cetakSesi(trim((string) $k))) {
                $sesi[] = $s;
            }
        }

        $this->line('  sesi karyawan tercetak: '.count($sesi));

        return $sesi;
    }

    /** @return list<array{name:string,value:string}> */
    private function sesiAdmin(User $admin): array
    {
        $kode = Karyawan::where('UserID_Web', $admin->Id_Users)->value('Kode_Karyawan');
        $s = $kode ? $this->cetakSesi(trim((string) $kode)) : null;

        return $s ? [$s] : [];
    }

    private function cetakSesi(string $nik): ?array
    {
        Artisan::call('sop:uat-sesi', ['nik' => $nik, '--json' => true]);

        foreach (explode("\n", Artisan::output()) as $baris) {
            $baris = trim($baris);
            if (str_starts_with($baris, '{') && str_ends_with($baris, '}')) {
                $j = json_decode($baris, true);
                if (isset($j['name'], $j['value'])) {
                    return ['name' => $j['name'], 'value' => $j['value']];
                }
            }
        }

        return null;
    }

    private function userDari(string $nik): ?User
    {
        $k = Karyawan::where('Kode_Karyawan', $nik)->first();

        return $k ? User::where('Id_Users', $k->UserID_Web)->first() : null;
    }
}
