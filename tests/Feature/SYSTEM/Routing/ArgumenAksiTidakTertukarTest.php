<?php

namespace Tests\Feature\SYSTEM\Routing;

use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Argumen aksi controller tidak boleh bisa tertukar.
 *
 * Laravel memanggil aksi lewat `Controller::callAction()`:
 *
 *     $this->{$method}(...array_values($parameters));
 *
 * `array_values()` MEMBUANG kuncinya, jadi argumennya POSISIONAL mengikuti
 * urutan parameter RUTE — bukan urutan tanda tangan method. Parameter dari
 * `->defaults(...)` selalu menyusul SESUDAH parameter URI, sehingga rute
 * seperti
 *
 *     Route::patch('/{id}/status', [C::class, 'ubah'])->defaults('master', 'x')
 *
 * memanggil `ubah('15', 'x')`. Tanda tangan `ubah(string $master, int $id)`
 * karena itu menerima keduanya terbalik.
 *
 * DUA BENTUK KEGAGALANNYA, dan yang kedua lebih buruk:
 *
 *   - Tipe berbeda (string vs int) → TypeError, HTTP 500 "Server Error" tanpa
 *     satu pun jejak di log modulnya, sebab galatnya terjadi SEBELUM blok
 *     try/catch di dalam aksi. Itu yang terjadi pada Master Data Visitor
 *     (22 Sep 2026): halaman terbuka normal, tombol aktif/nonaktif 500.
 *   - Tipe sama (dua-duanya string) → PHP diam saja dan aksinya bekerja pada
 *     sasaran yang salah. Empat aksi Export Studio Visitor berada dalam
 *     keadaan ini tanpa ada yang menyadarinya.
 *
 * Penjaga ini membaca SELURUH rute aplikasi, jadi ia juga menutup rute yang
 * ditulis kelak. Perbaikannya selalu sama: baca parameternya lewat nama
 * (`$request->route('master')`), bukan lewat urutan argumen.
 */
class ArgumenAksiTidakTertukarTest extends TestCase
{
    public function test_tidak_ada_aksi_yang_argumennya_bisa_tertukar(): void
    {
        $bermasalah = [];

        foreach (Route::getRoutes() as $rute) {
            $defaults = $rute->defaults ?? [];

            if ($defaults === []) {
                continue;   // tanpa default, urutan rute = urutan URI saja
            }

            preg_match_all('/\{(\w+)\??\}/', $rute->uri(), $cocok);
            $paramUri = $cocok[1];

            if ($paramUri === []) {
                continue;   // tak ada yang bisa mendahului defaultnya
            }

            $aksi = $rute->getActionName();

            if (! str_contains($aksi, '@')) {
                continue;   // closure: tak ada tanda tangan untuk ditertibkan
            }

            [$kelas, $metode] = explode('@', $aksi);

            if (! class_exists($kelas) || ! method_exists($kelas, $metode)) {
                continue;
            }

            /* Hanya parameter SKALAR yang diisi dari rute; yang berkelas
               (Request, model, service) disisipkan container dan tak ikut
               dalam permainan urutan ini. */
            $skalar = [];
            foreach ((new ReflectionMethod($kelas, $metode))->getParameters() as $p) {
                $tipe = $p->getType();

                if ($tipe && ! $tipe->isBuiltin()) {
                    continue;
                }

                $skalar[] = $p->getName();
            }

            if (count($skalar) < 2) {
                continue;   // satu parameter tak bisa tertukar dengan apa pun
            }

            $urutanRute = array_merge($paramUri, array_keys($defaults));

            if (array_slice($urutanRute, 0, count($skalar)) !== $skalar) {
                $bermasalah[] = sprintf(
                    '%s %s → %s::%s | rute mengirim [%s], method menerima [%s]',
                    implode(',', $rute->methods()),
                    $rute->uri(),
                    class_basename($kelas),
                    $metode,
                    implode(', ', $urutanRute),
                    implode(', ', $skalar),
                );
            }
        }

        $this->assertSame([], $bermasalah, "Aksi berikut menerima argumen rute dalam urutan yang salah:\n  ".implode("\n  ", $bermasalah)."\n\nBaca parameternya lewat nama (\$request->route('...')), bukan lewat urutan argumen.");
    }
}
