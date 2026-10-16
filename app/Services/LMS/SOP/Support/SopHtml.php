<?php

namespace App\Services\LMS\SOP\Support;

use DOMDocument;
use DOMElement;

/**
 * Sanitasi HTML sisi-server untuk konten rich-text SOP (item PARAGRAF → Konten_Teks).
 *
 * Client (Quill + DOMPurify) TIDAK bisa dipercaya (bisa di-bypass dgn POST langsung),
 * jadi ini otoritas terakhir sebelum simpan. Whitelist ketat & selaras dengan editor:
 *  - Tag: p,br,b,strong,i,em,u,s,h1-h3,ul,ol,li,blockquote,a,span (lainnya di-unwrap).
 *  - Atribut: a[href(http/https/mailto/#//),target,rel], class(ql-*), style(color/bg/align/size).
 *  - Blokir: script/iframe/on*-handlers/javascript:/url()/expression/@import, dll.
 */
class SopHtml
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's',
        'h1', 'h2', 'h3', 'ul', 'ol', 'li', 'blockquote', 'a', 'span',
    ];

    private const ALLOWED_STYLE_PROPS = ['color', 'background-color', 'text-align', 'font-size'];

    /** Tag berbahaya: dibuang total beserta isinya (bukan di-unwrap). */
    private const DROP_TAGS = [
        'script', 'style', 'noscript', 'iframe', 'object', 'embed', 'template',
        'svg', 'math', 'form', 'input', 'button', 'textarea', 'select',
        'link', 'meta', 'base', 'title', 'head',
    ];

    public static function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        // batasi ukuran payload
        if (mb_strlen($html) > 200000) {
            $html = mb_substr($html, 0, 200000);
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"?><div id="__root__">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $dom->documentElement; // div#__root__
        if (! $root) {
            return '';
        }

        self::cleanNode($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    private static function cleanNode(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, self::DROP_TAGS, true)) {
                    // buang total beserta isinya
                    $node->removeChild($child);

                    continue;
                }
                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    // unwrap: bersihkan isinya lalu naikkan anak-anaknya, hapus elemennya
                    self::cleanNode($child);
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);

                    continue;
                }
                self::cleanAttributes($child, $tag);
                self::cleanNode($child);
            } elseif ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                $node->removeChild($child);
            }
            // DOMText dibiarkan — aman, saveHTML() meng-escape entitas.
        }
    }

    private static function cleanAttributes(DOMElement $el, string $tag): void
    {
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);

            if ($tag === 'a' && $name === 'href') {
                if (! self::safeHref($attr->value)) {
                    $el->removeAttribute($attr->name);
                }

                continue;
            }
            if ($tag === 'a' && in_array($name, ['target', 'rel'], true)) {
                continue; // dipertahankan
            }
            if ($name === 'class') {
                $clean = self::safeClass($attr->value);
                $clean === '' ? $el->removeAttribute($attr->name) : $el->setAttribute('class', $clean);

                continue;
            }
            if ($name === 'style') {
                $clean = self::safeStyle($attr->value);
                $clean === '' ? $el->removeAttribute($attr->name) : $el->setAttribute('style', $clean);

                continue;
            }

            // sisanya (termasuk on*-handlers, id, src, dll.) dibuang
            $el->removeAttribute($attr->name);
        }

        // link yang buka tab baru → paksa rel aman
        if ($tag === 'a' && strtolower($el->getAttribute('target')) === '_blank') {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function safeHref(string $v): bool
    {
        $v = trim($v);
        if ($v === '') {
            return false;
        }
        if (str_starts_with($v, '#') || str_starts_with($v, '/')) {
            return true;
        }

        return (bool) preg_match('#^(https?:|mailto:)#i', $v);
    }

    private static function safeClass(string $v): string
    {
        $ok = [];
        foreach (preg_split('/\s+/', trim($v)) ?: [] as $c) {
            if ($c !== '' && preg_match('/^ql-(align|size|font|indent)-[a-z0-9-]+$/', $c)) {
                $ok[] = $c;
            }
        }

        return implode(' ', $ok);
    }

    private static function safeStyle(string $v): string
    {
        $ok = [];
        foreach (explode(';', $v) as $decl) {
            if (! str_contains($decl, ':')) {
                continue;
            }
            [$prop, $val] = explode(':', $decl, 2);
            $prop = strtolower(trim($prop));
            $val = trim($val);

            if (! in_array($prop, self::ALLOWED_STYLE_PROPS, true)) {
                continue;
            }
            if (preg_match('/url\s*\(|expression|javascript:|@import|[<>]/i', $val)) {
                continue;
            }

            if ($prop === 'text-align') {
                if (! in_array(strtolower($val), ['left', 'right', 'center', 'justify'], true)) {
                    continue;
                }
            } elseif ($prop === 'font-size') {
                if (! preg_match('/^(\d{1,3}(\.\d+)?(px|pt|em|rem|%)|xx-small|x-small|small|medium|large|x-large|xx-large|smaller|larger)$/i', $val)) {
                    continue;
                }
            } else { // color / background-color
                if (! preg_match('/^(#[0-9a-f]{3,8}|rgba?\([0-9.,\s%]+\)|hsla?\([0-9.,\s%]+\)|[a-z]{3,20})$/i', $val)) {
                    continue;
                }
            }
            $ok[] = $prop.': '.$val;
        }

        return implode('; ', $ok);
    }
}
