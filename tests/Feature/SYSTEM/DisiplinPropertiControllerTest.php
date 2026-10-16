<?php

namespace Tests\Feature\SYSTEM;

use Tests\TestCase;

/**
 * Controller tak boleh membaca properti yang tak pernah ada.
 *
 * PHP tidak menolak `$this->apaSaja` saat kompilasi. Barisnya baru meledak
 * ketika benar-benar dijalankan, dan bila ia berada di dalam `try` bercatch
 * generik — bentuk yang tersebar di controller repo ini — kegagalannya hanya
 * tampak sebagai "Tidak bisa mengambil data". Tak ada yang membacanya sebagai
 * cacat pemrograman.
 *
 * Itulah yang terjadi pada kalender homepage. `HomepageController::getCalendarData()`
 * memakai `$this->attendanceService` yang tidak pernah disuntikkan di
 * konstruktor. Dua rute — `homepage/calendar-data` dan `uDash/getCalendarData` —
 * menjawab 500 untuk SIAPA PUN, setiap kali, entah sejak kapan.
 *
 * YANG DIBURU, DAN YANG SENGAJA TIDAK.
 * Properti dinamis yang DITUGASKAN di dalam kelasnya sendiri
 * (`$this->x = $y;` di konstruktor tanpa deklarasi) tetap bekerja — usang sejak
 * PHP 8.2, tapi bukan urusan penjaga ini; menuduhnya berarti enam tuduhan palsu
 * dan penjaga yang tak lagi dipercaya orang. Yang diburu hanya properti yang
 * DIBACA padahal tak pernah dideklarasikan MAUPUN ditugaskan di mana pun —
 * bentuk yang pasti meledak begitu barisnya tersentuh.
 */
class DisiplinPropertiControllerTest extends TestCase
{
    /** @return array<int,string> */
    private function berkasController(): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('app/Http/Controllers'), \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $out[] = $f->getPathname();
            }
        }
        sort($out);

        return $out;
    }

    /** Komentar dibuang, jumlah barisnya dipertahankan agar nomor baris tetap benar. */
    private function tanpaKomentar(string $s): string
    {
        $s = preg_replace_callback('~/\*.*?\*/~s', fn ($m) => str_repeat("\n", substr_count($m[0], "\n")), $s);

        return (string) preg_replace('~//[^\n]*~', '', (string) $s);
    }

    /**
     * SATU-SATUNYA tempat aturannya dinyatakan; dipakai uji repo maupun
     * penjaga-bagi-penjaga.
     *
     * @return array<int,array{nama:string,baris:int}>
     */
    private function pindai(string $isi, \ReflectionClass $ref): array
    {
        // Kelas ber-__get memang boleh punya properti dinamis.
        if ($ref->hasMethod('__get')) {
            return [];
        }

        $sah = [];
        for ($c = $ref; $c; $c = $c->getParentClass()) {
            foreach ($c->getProperties() as $pr) {
                $sah[$pr->getName()] = true;
            }
        }
        if ($ref->getConstructor()) {
            foreach ($ref->getConstructor()->getParameters() as $par) {
                $sah[$par->getName()] = true;
            }
        }

        $kode = $this->tanpaKomentar($isi);

        /* Nama diambil UTUH dulu, baru diperiksa apa yang mengikutinya.
           Versi pertama memakai lookahead negatif di dalam pola yang sama —
           ([a-zA-Z_]\w*)\s*(?![\s]*\() — dan itu memaksa mundur satu huruf
           ketika yang menyusul memang '(', sehingga
           $this->extractSelectedDatesFromRecord terbaca sebagai properti
           bernama "...Recor". Hasilnya 601 temuan yang hampir seluruhnya hantu;
           laporan sebanyak itu bukan cuma salah, ia membuat orang berhenti
           membaca laporannya sama sekali. */
        preg_match_all('~\$this->([a-zA-Z_]\w*)~', $kode, $m, PREG_OFFSET_CAPTURE);

        $hasil = [];
        $sudah = [];
        foreach ($m[1] as [$nama, $off]) {
            if (isset($sah[$nama]) || isset($sudah[$nama]) || $ref->hasMethod($nama)) {
                continue;
            }

            $sisa = ltrim(substr($kode, $off + strlen($nama)));
            if ($sisa !== '' && $sisa[0] === '(') {
                continue;
            }

            if (preg_match('~\$this->'.preg_quote($nama, '~').'\s*=[^=]~', $kode)) {
                continue;
            }

            $sudah[$nama] = true;
            $hasil[] = ['nama' => $nama, 'baris' => substr_count(substr($kode, 0, $off), "\n") + 1];
        }

        return $hasil;
    }

    public function test_tidak_ada_controller_membaca_properti_yang_tak_pernah_ada(): void
    {
        $pelanggar = [];
        $diperiksa = 0;

        foreach ($this->berkasController() as $path) {
            $isi = (string) file_get_contents($path);
            if (! preg_match('~^\s*namespace\s+([^;]+);~m', $isi, $ns)) {
                continue;
            }
            if (! preg_match('~^\s*(?:final\s+|abstract\s+)?class\s+(\w+)~m', $isi, $kn)) {
                continue;
            }
            $kelas = trim($ns[1]).'\\'.$kn[1];
            if (! class_exists($kelas)) {
                continue;
            }

            try {
                $ref = new \ReflectionClass($kelas);
            } catch (\Throwable $e) {
                continue;
            }
            if ($ref->isAbstract()) {
                continue;
            }
            $diperiksa++;

            $rel = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
            foreach ($this->pindai($isi, $ref) as $t) {
                $pelanggar[] = str_replace('\\', '/', $rel).':'.$t['baris'].'  $this->'.$t['nama'];
            }
        }

        $this->assertGreaterThan(50, $diperiksa, 'Terlalu sedikit controller terbaca — pemindainya tak bekerja.');

        $this->assertSame(
            [],
            $pelanggar,
            count($pelanggar).' controller membaca properti yang tak pernah dideklarasikan '
            .'maupun ditugaskan. PHP baru meledak saat barisnya dijalankan, dan di dalam try '
            .'bercatch generik itu hanya tampak sebagai "gagal mengambil data" — rutenya mati '
            ."tanpa ada yang tahu.\n\nYang bermasalah:\n  ".implode("\n  ", $pelanggar)
        );
    }

    /**
     * Penjaga bagi penjaga.
     *
     * Pemindai yang salah baca melaporkan NOL pada kode yang bercacat, dan
     * hijau seperti itu lebih berbahaya daripada tidak ada penjaga. Di sini ia
     * disuruh membaca contoh yang isinya sudah diketahui: satu properti hantu,
     * satu properti dinamis yang sah karena ditugaskan, satu properti
     * terdeklarasi, dan satu pemanggilan metode berimbuhan panjang yang dulu
     * membuat versi pertama pemindai salah potong.
     */
    public function test_pemindai_mengenali_hantu_dan_tidak_menuduh_yang_sah(): void
    {
        $contoh = <<<'PHP'
<?php
namespace Tests\Contoh;

class ContohPropertiController
{
    protected $terdeklarasi;

    public function __construct($lewatKonstruktor)
    {
        $this->dinamisTapiDitugaskan = $lewatKonstruktor;
    }

    public function jalan()
    {
        $a = $this->terdeklarasi;
        $b = $this->dinamisTapiDitugaskan;
        $c = $this->extractSelectedDatesFromRecord($a);
        $d = $this->hantu->getSesuatu();

        return [$a, $b, $c, $d];
    }

    private function extractSelectedDatesFromRecord($x)
    {
        return $x;
    }
}
PHP;

        $berkas = tempnam(sys_get_temp_dir(), 'hantu').'.php';
        file_put_contents($berkas, $contoh);

        try {
            require_once $berkas;
            $ref = new \ReflectionClass(\Tests\Contoh\ContohPropertiController::class);
            $temuan = array_column($this->pindai($contoh, $ref), 'nama');

            $this->assertSame(
                ['hantu'],
                $temuan,
                'Pemindai harus menemukan tepat satu properti hantu. Ditemukan: '.json_encode($temuan)
            );
        } finally {
            @unlink($berkas);
        }
    }
}
