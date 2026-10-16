<?php

namespace Tests\Feature\SYSTEM;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Rute tidak boleh menunjuk metode controller yang tidak ada.
 *
 * Rute seperti itu adalah 500 yang menunggu disentuh. Tak ada yang gagal saat
 * boot, tak ada peringatan saat deploy — ia diam saja sampai seseorang membuka
 * halamannya. `GET /getOffset` hidup begitu entah sejak kapan; ditemukan hanya
 * karena sapuan rute Fase 6 menjalankannya.
 *
 * DULU RATCHET, SEKARANG NOL.
 * Saat penjaga ini ditulis ada **31** rute berkondisi sama, dan angkanya
 * dijadikan ratchet karena membereskannya menuntut keputusan per rute.
 * Keputusan itu sudah diambil: ketiga puluh satunya diperiksa satu per satu,
 * tak satu pun dirujuk frontend — tidak lewat URL, tidak lewat nama rute — dan
 * seluruhnya dihapus. Sebagian besar sisa scaffolding CRUD (division, level,
 * employee, case, evaluation, task, project, lmsmodulabsensi); dua di antaranya
 * salah tunjuk terang-terangan: `dashboardUser` menunjuk metode yang memang
 * dikomentari, dan `crossFeedbackDetail` memakai POTONGAN JALUR sebagai nama
 * metode.
 *
 * Maka ambangnya kini NOL, dan itu jauh lebih kuat: setiap rute rusak yang
 * masuk akan merah seketika, bukan bersembunyi di bawah utang lama.
 */
class RuteMenunjukMetodeAdaTest extends TestCase
{
    /**
     * Utang lama sudah lunas (6 Sep 2026): dari 31 menjadi 0.
     *
     * JANGAN pernah dinaikkan. Kalau uji ini merah, artinya ada rute yang
     * menunjuk metode tak ada — dan itu yang harus diperbaiki, bukan angkanya.
     */
    private const UTANG_LAMA = 0;

    /** @return array<int,string> */
    private function ruteRusak(): array
    {
        $rusak = [];

        foreach (Route::getRoutes() as $r) {
            $act = $r->getActionName();
            if (! str_contains($act, '@')) {
                continue;  // Closure atau invokable — bukan urusan penjaga ini.
            }

            [$kelas, $metode] = explode('@', $act, 2);

            if (! class_exists($kelas)) {
                $rusak[] = implode('|', $r->methods()).'  '.$r->uri().'  -> kelas tidak ada: '.$kelas;

                continue;
            }

            if (method_exists($kelas, $metode)) {
                continue;
            }

            /* __call memang bisa melahirkan metode secara dinamis — TAPI hanya
               bila controllernya sendiri yang mendeklarasikannya. Kelas dasar
               Illuminate\Routing\Controller punya __call yang justru MELEMPAR
               BadMethodCallException; memperlakukannya sebagai "mungkin ada"
               membuat pemeriksaan ini hampa untuk SELURUH controller. Versi
               pertama pemindai ini melakukan persis itu dan melaporkan 0 dari
               1.238 rute rusak, padahal ada yang nyata-nyata menjawab 500. */
            if (method_exists($kelas, '__call')) {
                $pemilik = (new \ReflectionMethod($kelas, '__call'))->getDeclaringClass()->getName();
                if (! str_starts_with($pemilik, 'Illuminate\\')) {
                    continue;
                }
            }

            $rusak[] = implode('|', $r->methods()).'  '.$r->uri().'  -> '.$act;
        }

        sort($rusak);

        return $rusak;
    }

    public function test_jumlah_rute_rusak_tidak_bertambah(): void
    {
        $rusak = $this->ruteRusak();
        $n = count($rusak);

        $this->assertLessThanOrEqual(
            self::UTANG_LAMA,
            $n,
            ($n - self::UTANG_LAMA).' rute rusak BARU. Rute yang menunjuk metode tak ada '
            .'adalah 500 yang menunggu disentuh — tak ada yang gagal saat boot, tak ada '
            ."peringatan saat deploy.\n\nSeluruh yang rusak sekarang:\n  "
            .implode("\n  ", $rusak)
        );

        if ($n < self::UTANG_LAMA) {
            $this->markTestIncomplete(
                "Bagus — rute rusak tinggal {$n} (patokan ".self::UTANG_LAMA.'). '
                .'Turunkan UTANG_LAMA di berkas ini supaya ratchetnya terkunci di angka baru.'
            );
        }
    }

    /**
     * Penjaga bagi penjaga.
     *
     * Pemindai yang salah baca melaporkan NOL pada repo yang bercacat — dan
     * versi pertama pemindai ini melakukan persis itu. Di sini ia disuruh
     * membaca kelas contoh yang isinya sudah diketahui.
     */
    public function test_pemindai_mengenali_metode_yang_tak_ada(): void
    {
        $kelas = new class extends \App\Http\Controllers\Controller
        {
            public function adaBeneran()
            {
                return 'ok';
            }
        };

        $nama = get_class($kelas);

        $this->assertTrue(
            method_exists($nama, 'adaBeneran'),
            'Metode yang jelas-jelas ada tak terbaca — pemindainya rusak.'
        );

        $this->assertFalse(
            method_exists($nama, 'tidakPernahAda'),
            'Metode yang tidak ada terbaca sebagai ada. Ini yang membuat versi pertama '
            .'pemindai melaporkan 0 dari 1.238 rute rusak.'
        );

        /* Inti kekeliruan versi pertama: controller mewarisi __call dari kelas
           dasar Laravel, sehingga syarat "punya __call" benar untuk semuanya. */
        $this->assertTrue(
            method_exists($nama, '__call'),
            'Kelas dasar controller sudah tidak punya __call — catatan di penjaga ini perlu disesuaikan.'
        );
        $this->assertStringStartsWith(
            'Illuminate\\',
            (new \ReflectionMethod($nama, '__call'))->getDeclaringClass()->getName(),
            '__call ternyata bukan milik kelas dasar Laravel — logika pengecualian di penjaga ini perlu ditinjau.'
        );
    }
}
