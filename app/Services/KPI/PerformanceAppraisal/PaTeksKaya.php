<?php

namespace App\Services\KPI\PerformanceAppraisal;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Teks kaya (rich text) untuk jawaban esai Kalibrasi Penilai.
 *
 * DUA FORMAT, DIKETAHUI DARI SUMBERNYA — TIDAK PERNAH DITEBAK. Jawaban asli HC Learn selalu
 * teks polos; revisi yang ditulis penilai di HCIS selalu HTML hasil penyaring ini. Menebak
 * format dari isinya ("ada tanda < berarti HTML") akan menjalankan teks polos yang kebetulan
 * memuat tanda kurung siku sebagai markup.
 *
 * DISARING DI SERVER, bukan hanya di peramban. HTML revisi dipakai sebagai catatan hasil yang
 * digambar dengan `v-html` di layar karyawan dan leader; DOMPurify di editor tidak melindungi
 * apa pun dari permintaan yang dikirim langsung ke API. Yang lolos hanya tag format dasar,
 * TANPA atribut sama sekali — tidak ada `style`, `href`, `on*`, maupun `class`.
 */
class PaTeksKaya
{
    /** Tag yang boleh bertahan. Tag lain dibuang, isinya dipertahankan sebagai teks. */
    public const TAG_IZIN = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ol', 'ul', 'li', 'blockquote'];

    /** Tag yang dibuang BESERTA isinya: isinya bukan teks bacaan. */
    /** Tag HTML lain yang mungkin datang dari editor, hanya untuk MENGENALI isi berformat HTML. */
    private const TAG_HTML_LAIN = ['span', 'div', 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'sub', 'sup', 'code', 'pre', 'img', 'hr', 'table', 'tr', 'td', 'th', 'tbody', 'thead'];

    private const TAG_BUANG = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math', 'head', 'title'];

    /**
     * HTML aman: hanya TAG_IZIN, tanpa atribut. String kosong bila isinya tidak punya teks.
     */
    public function bersihkan(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $dok = new DOMDocument('1.0', 'UTF-8');
        $lama = libxml_use_internal_errors(true);
        // Awalan XML memaksa UTF-8; pembungkus memberi satu akar yang pasti.
        $dok->loadHTML('<?xml encoding="UTF-8"><div id="akar">'.$html.'</div>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($lama);

        $akar = $dok->getElementById('akar');

        if ($akar === null) {
            return '';
        }

        $this->luruskanDaftarQuill($dok, $akar);
        $this->saring($akar);

        $hasil = '';

        foreach ($akar->childNodes as $anak) {
            $hasil .= $dok->saveHTML($anak);
        }

        $hasil = trim($hasil);

        return $this->teksPolos($hasil) === '' ? '' : $hasil;
    }

    /**
     * Teks polos HC Learn → HTML setara: di-escape, satu baris satu paragraf. Dipakai supaya
     * editor dan layar menampilkan jawaban asli apa adanya, tanpa satu karakter pun terbaca
     * sebagai markup.
     */
    public function dariTeksPolos(string $teks): string
    {
        $baris = preg_split('/\R/u', trim($teks)) ?: [];

        return implode('', array_map(
            fn (string $b) => trim($b) === ''
                ? '<p><br></p>'
                : '<p>'.htmlspecialchars($b, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8').'</p>',
            $baris,
        ));
    }

    /**
     * Isi esai per soal dari HC Learn (`Esai[].Pertanyaan`, `Esai[].Jawaban_Peserta`) → HTML aman.
     *
     * Sejak 28 Sep 2026 HC Learn mengirim keduanya sebagai HTML editor Quill APA ADANYA, tidak
     * dibersihkan. Kiriman sebelum itu, dan butir yang tidak diketik di editor kaya, masih
     * teks polos. Keduanya dibedakan dari ISINYA: teks yang memuat tag HTML dibersihkan,
     * selebihnya di-escape per baris. Pengenalannya sengaja ketat: hanya nama tag HTML yang
     * dikenal, sehingga "2 < 3" atau "Apa <kekuatan> utamanya?" tetap terbaca sebagai teks.
     */
    public function dariEsaiHcLearn(string $isi): string
    {
        return $this->berupaHtml($isi) ? $this->bersihkan($isi) : $this->dariTeksPolos($isi);
    }

    public function berupaHtml(string $isi): bool
    {
        $tag = implode('|', array_merge(self::TAG_IZIN, self::TAG_BUANG, self::TAG_HTML_LAIN));

        return preg_match('#</?(?:'.$tag.')(?:\s[^<>]*)?/?>#i', $isi) === 1;
    }

    /**
     * HTML → teks polos yang terbaca: batas paragraf dan butir daftar menjadi baris baru.
     * Dipakai untuk memeriksa kosong/panjang dan untuk tempat yang menampilkan teks biasa.
     */
    public function teksPolos(string $html): string
    {
        $teks = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $teks = preg_replace('#</(p|li|blockquote|ol|ul)>#i', "\n", $teks) ?? $teks;
        $teks = html_entity_decode(strip_tags($teks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $teks = preg_replace("/[ \t\x{00A0}]+/u", ' ', $teks) ?? $teks;
        $teks = preg_replace("/\n\s*\n+/u", "\n", $teks) ?? $teks;

        return trim($teks);
    }

    /**
     * Quill 2 menulis SEMUA daftar sebagai `<ol>` dan membedakan jenisnya lewat
     * `<li data-list="bullet|ordered">`. Atribut itu dibuang penyaring, jadi tanpa langkah ini
     * daftar berpoin tersimpan — dan tampil di layar karyawan — sebagai daftar bernomor.
     * Butir berurutan sejenis dikelompokkan ke `<ul>` atau `<ol>` yang sebenarnya.
     */
    private function luruskanDaftarQuill(DOMDocument $dok, DOMElement $akar): void
    {
        foreach (iterator_to_array($akar->getElementsByTagName('ol')) as $ol) {
            $adaPenanda = false;

            foreach ($ol->childNodes as $li) {
                if ($li instanceof DOMElement && $li->hasAttribute('data-list')) {
                    $adaPenanda = true;
                    break;
                }
            }

            if (! $adaPenanda) {
                continue;
            }

            $sekarang = null;
            $jenisSekarang = null;

            foreach (iterator_to_array($ol->childNodes) as $li) {
                if (! $li instanceof DOMElement || strtolower($li->tagName) !== 'li') {
                    continue;
                }

                $jenis = $li->getAttribute('data-list') === 'bullet' ? 'ul' : 'ol';

                if ($jenis !== $jenisSekarang) {
                    $sekarang = $dok->createElement($jenis);
                    $ol->parentNode->insertBefore($sekarang, $ol);
                    $jenisSekarang = $jenis;
                }

                $sekarang->appendChild($li);
            }

            $ol->parentNode->removeChild($ol);
        }
    }

    private function saring(DOMNode $simpul): void
    {
        // Disalin dulu: menghapus anak selagi menelusuri NodeList hidup melompati simpul.
        foreach (iterator_to_array($simpul->childNodes) as $anak) {
            if ($anak instanceof DOMElement) {
                $tag = strtolower($anak->tagName);

                if (in_array($tag, self::TAG_BUANG, true)) {
                    $simpul->removeChild($anak);

                    continue;
                }

                $this->saring($anak);

                if (! in_array($tag, self::TAG_IZIN, true)) {
                    // Tag asing dibuka: isinya tetap, bungkusnya hilang.
                    while ($anak->firstChild !== null) {
                        $simpul->insertBefore($anak->firstChild, $anak);
                    }

                    $simpul->removeChild($anak);

                    continue;
                }

                foreach (iterator_to_array($anak->attributes) as $atribut) {
                    $anak->removeAttribute($atribut->nodeName);
                }
            } elseif ($anak->nodeType === XML_COMMENT_NODE || $anak->nodeType === XML_PI_NODE) {
                $simpul->removeChild($anak);
            }
        }
    }
}
