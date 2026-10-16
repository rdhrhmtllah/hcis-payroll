<?php

namespace Tests\Feature\SYSTEM\Application;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Muatan permintaan tidak boleh masuk utuh ke penulisan basis data.
 *
 * ── Cacat yang ditutup ───────────────────────────────────────────────────────
 *
 *     $request->validate([ ...tujuh field... ]);
 *     $data = $request->except(['Id_Aturan_Cuti']);
 *     DB::table('N_HRIS_Aturan_Kelayakan_Cuti')->insertGetId($data);
 *
 * Validasinya memeriksa tujuh field; yang ditulis SELURUH muatan. Karena ini
 * query builder dan bukan Eloquent, $guarded tidak berlaku sama sekali: setiap
 * field tambahan langsung menjadi kolom yang ditulis. Field yang kebetulan
 * bernama sama dengan kolom nyata tertulis diam-diam; yang tidak, membuat
 * pernyataannya gagal.
 *
 * Perbaikannya satu kata: pakai NILAI KEMBALIAN validate(), yang berisi tepat
 * field yang aturannya ditulis di situ.
 *
 * ── Yang TIDAK dianggap pelanggaran ──────────────────────────────────────────
 *
 * ->only([...]) dan ->validate([...]) menyaring berdasarkan daftar kolom yang
 * ditulis pengembang, jadi keduanya aman. Yang dijaga hanya ->all(), ->except()
 * dan ->input() tanpa nama field — ketiganya menyerahkan pemilihan kolom kepada
 * pengirim permintaan.
 *
 * ── Biasnya disengaja ────────────────────────────────────────────────────────
 *
 * Pencocokan nama variabel bersifat per-BERKAS, bukan per-metode: satu
 * $data = $request->except(...) di mana pun membuat setiap ->insert($data) di
 * berkas itu ikut dilaporkan, sekalipun milik metode lain yang mengisi $data
 * dari sumber aman. Untuk penjaga keamanan arah itu yang benar — ia boleh
 * terlalu banyak bertanya, tidak boleh terlalu sedikit. Bila sebuah laporan
 * memang keliru, ganti nama variabelnya atau pisahkan metodenya.
 */
class MuatanPermintaanTidakUtuhTest extends TestCase
{
    /** Sumber yang mengembalikan seluruh muatan, tanpa daftar kolom. */
    private const SUMBER = '/\$(\w+)\s*=\s*\$request->(all|except|input)\s*\(/';

    /** Pemanggilan yang menulis ke basis data dengan satu variabel sebagai muatan. */
    private const PENULIS = '/->(create|update|fill|insert|insertGetId|updateOrCreate|firstOrCreate)\s*\(\s*\$(\w+)\s*[,)]/';

    public function test_tidak_ada_penulisan_yang_menerima_muatan_permintaan_utuh(): void
    {
        $temuan = [];

        foreach ($this->berkasPhp(base_path('app')) as $jalur) {
            $isi = $this->tanpaKomentar(file_get_contents($jalur));
            $rel = str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $jalur);

            if (! preg_match_all(self::SUMBER, $isi, $sumber, PREG_SET_ORDER)) {
                continue;
            }

            $mentah = [];
            foreach ($sumber as $s) {
                $mentah[$s[1]] = $s[2];
            }

            if (! preg_match_all(self::PENULIS, $isi, $tulis, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                continue;
            }

            foreach ($tulis as $t) {
                $var = $t[2][0];

                if (! isset($mentah[$var])) {
                    continue;
                }

                $baris = substr_count(substr($isi, 0, $t[0][1]), "\n") + 1;
                $temuan[] = sprintf('%s:%d ->%s($%s) menerima $request->%s()', $rel, $baris, $t[1][0], $var, $mentah[$var]);
            }
        }

        sort($temuan);

        $this->assertSame(
            [],
            $temuan,
            'Sebuah penulisan basis data menerima muatan permintaan yang tidak disaring per '
            .'kolom, sehingga pengirim ikut menentukan kolom mana yang ditulis. Pakai nilai '
            ."kembalian \$request->validate([...]) atau \$request->only([...]).\n  "
            .implode("\n  ", $temuan)
        );
    }

    /** Komentar dibuang tetapi jumlah barisnya dipertahankan. */
    private function tanpaKomentar(string $teks): string
    {
        $token = @token_get_all($teks);

        if ($token === false) {
            return $teks;
        }

        $hasil = '';
        foreach ($token as $t) {
            if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $hasil .= str_repeat("\n", substr_count($t[1], "\n"));

                continue;
            }

            $hasil .= is_array($t) ? $t[1] : $t;
        }

        return $hasil;
    }

    /** @return list<string> */
    private function berkasPhp(string $akar): array
    {
        $hasil = [];
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($akar, FilesystemIterator::SKIP_DOTS));

        foreach ($rii as $berkas) {
            if ($berkas->getExtension() === 'php') {
                $hasil[] = $berkas->getPathname();
            }
        }

        return $hasil;
    }
}
