<?php

namespace Tests\Feature\SYSTEM;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Daftar halaman yang boleh diakses HARUS identik sesudah digabung jadi satu
 * perjalanan.
 *
 * Ini pemeriksaan HAK AKSES. Kesalahan di sini tidak muncul sebagai galat: ia
 * muncul sebagai orang yang kehilangan menu yang seharusnya ia punya, atau —
 * jauh lebih buruk — melihat menu yang seharusnya tidak. Karena itu yang diuji
 * bukan "bentuk barunya berjalan", melainkan **hasilnya sama persis dengan
 * bentuk lama, pada data yang sesungguhnya ada di basis data**.
 *
 * Bentuk lama (tiga kueri terpisah, digabung di PHP) ditulis ulang di dalam
 * uji ini sebagai pembanding. Ia sengaja disalin apa adanya: begitu ia berbeda
 * dari implementasi, uji inilah yang harus memerah lebih dulu.
 */
class UserPageAccessSatuPerjalananTest extends TestCase
{
    /**
     * Bentuk LAMA — tiga kueri, digabung dan dideduplikasi di PHP.
     *
     * @return array<int,string>
     */
    private function bentukLama(int $userId): array
    {
        $roleMappedPages = DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as rf', function ($join) {
                $join->on('rf.Id_Role', '=', 'ru.Id_Role')->where('rf.Flag_Aktif', 'Y');
            })
            ->join('N_HRIS_Grup_Fitur_Map as gfm', 'gfm.Id_Grup_Fitur', '=', 'rf.Id_Grup_Fitur')
            ->where('ru.UserId_Web', $userId)
            ->where('ru.Flag_Aktif', 'Y')
            ->pluck('gfm.Jenis_page');

        $explicitPageAccess = DB::table('HRIS_Page_Access as pa')
            ->where('pa.UserID_Web', $userId)
            ->where('pa.Flag_Aktif', 'Y')
            ->pluck('pa.Jenis_page');

        $globalPages = DB::table('N_HRIS_Menus')->where('Is_Global_Menu', 'Y')->pluck('Jenis_page');

        return collect()
            ->merge($roleMappedPages)
            ->merge($explicitPageAccess)
            ->merge($globalPages)
            ->map(fn ($v) => strtolower(str_replace(' ', '', trim((string) $v))))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** Pengguna nyata yang paling banyak haknya — di situlah bedanya paling mungkin muncul. */
    private function penggunaUji(int $berapa = 8): array
    {
        $dariPageAccess = DB::table('HRIS_Page_Access')
            ->where('Flag_Aktif', 'Y')
            ->whereNotNull('UserID_Web')
            ->select('UserID_Web', DB::raw('count(*) as n'))
            ->groupBy('UserID_Web')
            ->orderByRaw('count(*) desc')
            ->limit($berapa)
            ->pluck('UserID_Web');

        $dariRole = DB::table('N_HRIS_Role_User')
            ->where('Flag_Aktif', 'Y')
            ->whereNotNull('UserId_Web')
            ->select('UserId_Web', DB::raw('count(*) as n'))
            ->groupBy('UserId_Web')
            ->orderByRaw('count(*) desc')
            ->limit($berapa)
            ->pluck('UserId_Web');

        return $dariPageAccess->merge($dariRole)
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function test_hasilnya_identik_dengan_bentuk_tiga_kueri(): void
    {
        $pengguna = $this->penggunaUji();

        $this->assertNotEmpty(
            $pengguna,
            'Tak ada pengguna berhak akses di basis data ini — uji ini akan hijau tanpa '
            .'membandingkan apa pun. Semai data lebih dulu daripada memercayai hijaunya.',
        );

        $metode = new ReflectionMethod(User::class, 'memilikiAksesBaru');
        $metode->setAccessible(true);

        $diperiksa = 0;

        foreach ($pengguna as $userId) {
            $harapan = $this->bentukLama($userId);

            /* Cache statis per-pengguna harus dikosongkan antar-pengguna, kalau
               tidak pengguna kedua dan seterusnya membaca hasil pengguna pertama
               dan uji ini lulus tanpa memeriksa apa pun. */
            $this->kosongkanCacheAkses();

            $user = new User;
            $user->Id_Users = $userId;

            /* memilikiAksesBaru() menjawab boolean; yang ingin dibandingkan
               DAFTARNYA. Dipanggil sekali supaya cache-nya terisi, lalu
               daftarnya dibaca dari properti statis itu. */
            $metode->invoke($user, 'halaman-yang-pasti-tak-ada');

            $nyata = $this->daftarDariCache($userId);

            sort($harapan);
            sort($nyata);

            $this->assertSame(
                $harapan,
                $nyata,
                "Daftar akses pengguna {$userId} BERBEDA antara bentuk satu-perjalanan dan "
                .'bentuk tiga-kueri. Selisih inilah yang menjadi menu hilang atau menu yang '
                .'seharusnya tak terlihat.',
            );

            $diperiksa++;
        }

        $this->assertGreaterThanOrEqual(3, $diperiksa, 'Terlalu sedikit pengguna yang benar-benar dibandingkan.');
    }

    /**
     * Sisi negatifnya: pembanding di uji ini harus benar-benar bisa BERBEDA.
     *
     * Tanpa ini, `assertSame` di atas bisa saja membandingkan dua hal yang
     * kebetulan selalu sama karena keduanya kosong — bentuk kegagalan yang
     * lulus tanpa memeriksa apa pun.
     */
    public function test_pembandingnya_memang_mengembalikan_isi(): void
    {
        $pengguna = $this->penggunaUji(3);
        $this->assertNotEmpty($pengguna);

        $adaIsi = false;
        foreach ($pengguna as $userId) {
            if (count($this->bentukLama($userId)) > 0) {
                $adaIsi = true;
                break;
            }
        }

        $this->assertTrue($adaIsi, 'Bentuk pembanding mengembalikan daftar kosong untuk semua pengguna uji.');
    }

    /**
     * KEUNTUNGANNYA ikut dijaga, bukan hanya kebenarannya.
     *
     * Uji pertama membandingkan hasil akhir, dan hasil akhir tetap identik
     * seandainya penghematannya dibatalkan — sebab ->unique() di PHP tetap
     * melipat duplikatnya. Terbukti dua kali: sabotase UNION -> UNION ALL
     * LOLOS HIJAU di sana.
     *
     * Percobaan pertama menutup celah ini pun GAGAL, dan cara gagalnya layak
     * dicatat: helper-nya MEMBANGUN KUERINYA SENDIRI di dalam uji, sehingga
     * merusak implementasi tak berpengaruh apa pun — uji yang memeriksa
     * dirinya sendiri. Versi ini mendengarkan kueri yang BENAR-BENAR
     * dijalankan implementasi.
     *
     * Yang ditagih: satu perjalanan, dan server yang membuang duplikatnya.
     */
    public function test_satu_perjalanan_dan_server_yang_mendedup(): void
    {
        $pengguna = $this->penggunaUji(1);
        $this->assertNotEmpty($pengguna);

        $userId = $pengguna[0];
        $this->kosongkanCacheAkses();

        $terekam = [];
        DB::listen(function ($q) use (&$terekam) {
            $terekam[] = $q->sql;
        });

        $metode = new ReflectionMethod(User::class, 'memilikiAksesBaru');
        $metode->setAccessible(true);
        $metode->invoke((function () use ($userId) {
            $u = new User;
            $u->Id_Users = $userId;

            return $u;
        })(), 'halaman-yang-pasti-tak-ada');

        $menyentuhAkses = array_values(array_filter(
            $terekam,
            fn ($sql) => str_contains($sql, 'HRIS_Page_Access') || str_contains($sql, 'N_HRIS_Role_User'),
        ));

        $this->assertCount(
            1,
            $menyentuhAkses,
            'Daftar akses diambil dalam '.count($menyentuhAkses).' perjalanan, bukan satu. '
            .'ASYNC_NETWORK_IO adalah biaya TERBESAR di server ini (33,5%), jadi jumlah '
            .'perjalanan yang dihitung.

'.implode('
---
', $menyentuhAkses),
        );

        $sql = strtoupper(preg_replace('/\s+/', ' ', $menyentuhAkses[0]));

        $this->assertStringContainsString(' UNION ', $sql, 'Kuerinya tak lagi menggabungkan di server.');
        $this->assertStringNotContainsString(
            ' UNION ALL ',
            $sql,
            'UNION ALL mengirim baris kembar melintasi jaringan. Hasil akhirnya tetap benar '
            .'karena ->unique() di PHP melipatnya — tetapi barisnya sudah terlanjur dibayar '
            .'dua kali: sekali dikirim server, sekali diserap klien. Terukur 48% terbuang.',
        );
    }

    private function kosongkanCacheAkses(): void
    {
        foreach (['menuAccessCache', 'menuAccessLegacyCache'] as $nama) {
            $properti = new \ReflectionProperty(User::class, $nama);
            $properti->setAccessible(true);
            $properti->setValue(null, []);
        }
    }

    /** @return array<int,string> */
    private function daftarDariCache(int $userId): array
    {
        $properti = new \ReflectionProperty(User::class, 'menuAccessCache');
        $properti->setAccessible(true);
        $isi = $properti->getValue();

        return $isi[$userId] ?? [];
    }
}
