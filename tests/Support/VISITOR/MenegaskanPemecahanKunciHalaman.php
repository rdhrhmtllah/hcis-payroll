<?php

namespace Tests\Support\VISITOR;

use Illuminate\Support\Facades\DB;

/**
 * Invarian pemecahan kunci halaman Visitor — versi yang masih berlaku.
 *
 * Ketika sebuah halaman gabungan dipecah menjadi dua halaman domain, bentuk
 * pertama penjaganya menuntut setiap pemegang kunci lama menerima KEDUA kunci
 * barunya. Itu benar pada saat pemecahannya: langkahnya murni migrasi, dan
 * seorang pun tidak boleh kehilangan apa pun.
 *
 * Lalu datang pemisahan per-role (DDL-2026-110): ADMIN_PERMIT hanya membuka
 * halaman permit, ADMIN_TAMU hanya halaman tamu. Sejak itu, menuntut "kedua
 * kunci" berarti menuntut kebalikan dari pemisahan yang justru diminta — dan
 * penjaga yang memerah atas keadaan yang benar akan dibungkam orang, lalu
 * berhenti menjaga apa pun.
 *
 * Yang tersisa dan tetap harus dijaga adalah dua bahaya sesungguhnya:
 *
 *   1. Ada orang yang kehilangan SELURUH aksesnya di antara dua langkah —
 *      kegagalan senyap yang baru ketahuan ketika ia membuka sidebar dan
 *      menunya hilang.
 *   2. Ada orang yang mendapat akses yang TIDAK pernah ia punya, karena
 *      pemecahan kunci dipakai sebagai pintu belakang pemberian hak.
 *
 * Keduanya dijaga tanpa memaksakan bentuk pembagiannya.
 */
trait MenegaskanPemecahanKunciHalaman
{
    /**
     * Bandingkan TUJUAN pengalihan, bukan bentuk penulisannya.
     *
     * Laravel menuliskan header Location sebagai URL absolut
     * (http://localhost/...), sementara yang ingin dijaga adalah path dan
     * query-nya. Menyamakan string mentah membuat penjaga ini bergantung pada
     * APP_URL environment — merah di satu mesin, hijau di mesin lain, dan
     * tidak satu pun ada hubungannya dengan pengalihan yang diuji.
     */
    protected function assertMengalihkanKe(string $tujuan, ?string $location): void
    {
        $this->assertNotNull($location, 'Tidak ada pengalihan sama sekali.');
        $this->assertSame($tujuan, $this->pathPengalihan($location));
    }

    /** Path + query dari sebuah header Location, tanpa skema dan host. */
    protected function pathPengalihan(?string $location): string
    {
        if ($location === null || $location === '') {
            return '';
        }

        $bagian = parse_url($location);

        return ($bagian['path'] ?? '').(isset($bagian['query']) ? '?'.$bagian['query'] : '');
    }

    /**
     * @param  array<int, string>  $kunciBaru
     */
    protected function assertPemecahanKunciTidakMerugikan(string $kunciLama, array $kunciBaru): void
    {
        $pemegang = static fn (string $jenis): array => DB::table('HRIS_Page_Access')
            ->where('Jenis_page', $jenis)
            ->where('Flag_Aktif', 'Y')
            ->pluck('UserID_Web')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $lama = $pemegang($kunciLama);

        $this->assertNotEmpty($lama, "Prasyarat berubah: {$kunciLama} tidak punya pemegang aktif.");

        $gabungan = [];
        foreach ($kunciBaru as $baru) {
            $ini = $pemegang($baru);
            $gabungan = array_merge($gabungan, $ini);

            // "Tidak ada yang mendapat akses baru" TIDAK bisa diukur dari kunci
            // lama saja.
            //
            // Hak akses Visitor punya DUA sumber: kunci per orang di
            // HRIS_Page_Access, dan hibah lewat role→grup fitur. DDL-2026-110
            // sengaja mengubah sumber kedua menjadi yang pertama, sehingga baris
            // kunci baru memang bertambah untuk orang yang selama ini masuk
            // lewat hibah grup dan tidak pernah punya baris kunci lamanya.
            // Membandingkan dengan kunci lama saja menuduh pemisahan yang justru
            // diminta.
            //
            // Yang benar-benar berbahaya adalah kunci yang diberikan kepada
            // orang tanpa dasar apa pun. Itulah yang ditagih di sini: setiap
            // pemegang kunci domain harus memegang ROLE domain itu.
            $domain = str_contains($baru, 'Permit') ? 'ADMIN_PERMIT' : 'ADMIN_TAMU';

            $berhak = DB::table('N_HRIS_Role_User as ru')
                ->join('N_HRIS_Role_Functional as r', 'r.Id_Role', '=', 'ru.Id_Role')
                ->where('ru.Flag_Aktif', 'Y')
                ->where('r.Flag_Aktif', 'Y')
                ->where('r.Role_Code', $domain)
                ->pluck('ru.UserId_Web')
                ->unique()
                ->all();

            $tanpaDasar = array_diff($ini, $lama, $berhak);

            $this->assertSame(
                [],
                array_values($tanpaDasar),
                "Pemegang {$baru} memuat orang yang tidak memegang {$kunciLama} "
                    ."maupun role {$domain}: ".implode(', ', $tanpaDasar),
            );
        }

        // Tidak ada yang KEHILANGAN seluruh aksesnya.
        $tertinggal = array_diff($lama, array_unique($gabungan));
        $this->assertSame(
            [],
            array_values($tertinggal),
            "Pemegang {$kunciLama} berikut tidak menerima satu pun kunci barunya: "
                .implode(', ', $tertinggal),
        );
    }
}
