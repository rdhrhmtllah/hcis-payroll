<?php

namespace Tests\Support;

use App\Services\SYSTEM\Audit\Activity\GerbangDiagnostikRealtime;
use App\Services\SYSTEM\Audit\Activity\PerekamKueriPerRute;
use Illuminate\Support\Facades\DB;

/**
 * Merekam kueri SETIAP uji, otomatis, tanpa ada yang perlu diklik.
 *
 * Menyisir fitur satu per satu lewat browser itu lambat dan bocor: yang
 * terlewat tidak ketahuan terlewat. Suite uji sudah menjalankan hampir setiap
 * controller dan service yang punya uji — jadi sekali jalan, ia memberi profil
 * kueri yang jauh lebih luas daripada apa pun yang bisa diklik orang.
 *
 * KENAPA DB::listen SENDIRI, BUKAN MENUMPANG MIDDLEWARE
 *
 * Middleware RequestDiagnostics hanya berjalan pada permintaan HTTP. Banyak uji
 * memanggil service atau controller LANGSUNG — dan justru uji semacam itu yang
 * paling sering menyentuh jalur berat. Menumpang middleware berarti melewatkan
 * mereka diam-diam, dan "diam-diam terlewat" adalah kegagalan yang paling
 * mahal pada alat ukur.
 *
 * Atribusinya nama UJI, bukan rute: satu uji bisa memanggil banyak rute, dan
 * yang ingin diketahui adalah "jalur mana yang mahal", bukan "HTTP apa yang
 * kebetulan lewat".
 *
 * MATI SECARA BAKU. Menyala hanya bila DIAGNOSTIK_REKAM_UJI=true DAN gerbang
 * dev terbuka. Tanpa itu, satu-satunya biayanya satu pemeriksaan boolean per
 * uji — suite yang biasa tidak boleh membayar apa pun untuk alat yang tidak
 * sedang dipakai.
 */
trait MerekamKueriUji
{
    private ?PerekamKueriPerRute $perekamUji = null;

    /** @var array<string,array{n:int,ms:float}> */
    private array $kueriUji = [];

    protected function setUpMerekamKueriUji(): void
    {
        if (! $this->rekamUjiMenyala()) {
            return;
        }

        $this->perekamUji = app(PerekamKueriPerRute::class);
        $this->kueriUji = [];

        DB::listen(function ($q) {
            // Kueri milik alat ukurnya sendiri tidak ikut, sama seperti pada
            // jalur HTTP — pemantau yang memuncaki daftarnya sendiri hanya
            // membuat orang mengejar bayangannya.
            $sql = ltrim($q->sql);
            if (str_starts_with($sql, 'SET CONTEXT_INFO')
                || str_contains($sql, 'dm_exec_')
                || str_contains($sql, 'dm_tran_locks')
                || str_contains($sql, 'dm_os_waiting')) {
                return;
            }

            $kunci = substr($q->sql, 0, 400);
            $this->kueriUji[$kunci] ??= ['n' => 0, 'ms' => 0.0];
            $this->kueriUji[$kunci]['n']++;
            $this->kueriUji[$kunci]['ms'] += (float) $q->time;
        });
    }

    protected function tearDownMerekamKueriUji(): void
    {
        if ($this->perekamUji === null || $this->kueriUji === []) {
            return;
        }

        $n = 0;
        $ms = 0.0;
        foreach ($this->kueriUji as $d) {
            $n += $d['n'];
            $ms += $d['ms'];
        }

        $this->perekamUji->catat([
            'rute' => 'uji: '.static::class.'::'.$this->name(),
            'kueri' => $n,
            'ms' => round($ms, 1),
            'total_ms' => round($ms, 1),
            'status' => 200,
            'sql' => $this->kueriUji,
        ]);

        $this->kueriUji = [];
    }

    private function rekamUjiMenyala(): bool
    {
        if (! filter_var(env('DIAGNOSTIK_REKAM_UJI', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        // Sesi harus sudah dimulai lebih dulu (php artisan diagnostik:rekam
        // mulai). Menyalakannya sendiri di sini akan membuat tiap berkas uji
        // memulai sesinya sendiri dan menimpa yang sebelumnya.
        return app(GerbangDiagnostikRealtime::class)->boleh()
            && app(PerekamKueriPerRute::class)->sesiAktif() !== null;
    }
}
