import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { parse } from '@vue/compiler-sfc';

/**
 * Modal harus dirender DI LUAR percabangan mode/kondisi.
 *
 * Jebakan yang sudah menggigit DUA kali di modul ini, dan keduanya gagal
 * tanpa satu pun galat:
 *
 *  1. KontenPickerModal bersarang di blok "kandidat konten" → jalur dokumen
 *     baru mengkliknya tanpa hasil apa pun.
 *  2. SopKonfirmasi bersarang di blok mode DAFTAR → di mode EDITOR, Duplikat
 *     dan Hapus item menyalakan `konfirmasi.buka` pada komponen yang tak
 *     pernah dirender; tak ada modal, dan Promise-nya menggantung selamanya.
 *
 * Bentuknya selalu sama: pemanggilnya bertambah, komponennya tetap di kurungan
 * pemanggil pertama. Uji ini memeriksa STRUKTUR templatenya, bukan perilakunya,
 * karena merakit seluruh halaman Builder untuk membuktikannya jauh lebih mahal
 * daripada membaca kedalaman satu tag.
 */

const berkas = (p) => readFileSync(resolve(process.cwd(), p), 'utf8');

/**
 * Kedalaman `v-if`/`v-else-if`/`v-else` yang membungkus sebuah tag.
 *
 * Dihitung dengan menyisir template dari awal sampai posisi tag: setiap elemen
 * berkondisi yang dibuka menambah satu, penutupnya mengurangi.
 */
function kedalamanKondisi(template, tag) {
    const posisi = template.indexOf(`<${tag}`);
    if (posisi < 0) return null;

    const sebelum = template.slice(0, posisi);
    const tumpukan = [];
    const re = /<(\/?)([A-Za-z][\w-]*)((?:"[^"]*"|'[^']*'|[^>"'])*?)(\/?)>/g;
    let m;

    while ((m = re.exec(sebelum))) {
        const [, tutup, nama, atribut, tunggal] = m;
        if (tunggal) continue;
        if (tutup) {
            const i = tumpukan.map((x) => x.nama).lastIndexOf(nama);
            if (i >= 0) tumpukan.splice(i, 1);
            continue;
        }
        tumpukan.push({ nama, kondisi: /\sv-(if|else-if|else)\b/.test(atribut) });
    }

    return tumpukan.filter((x) => x.kondisi).length;
}

/* KontenPickerModal di ItemFields dan SopKonfirmasi di SopVersionPanel dicabut
   dari daftar ini pada 27 Agu 2026: keduanya ikut hilang bersama jalur "buat
   dokumen baru" dan siklus tulis versi di Builder. Yang tersisa tetap dijaga —
   aturannya tak berubah, hanya pemakainya yang berkurang.

   SopDokumenPickerModal sengaja TIDAK masuk daftar: ia memang bersarang di
   dalam blok dokumen, dan satu-satunya yang menyalakannya adalah tombol di blok
   yang sama. Aturan ini menjaga modal yang pemanggilnya bisa berada di cabang
   LAIN — bukan setiap modal yang kebetulan punya v-if di atasnya. */
const KASUS = [
    { berkas: 'resources/js/Pages/LMS/SOP/Governance/Admin/SopBuilder.vue', tag: 'SopKonfirmasi' },
    { berkas: 'resources/js/Pages/LMS/SOP/Shared/components/ui/ItemFields.vue', tag: 'SopKonfirmasi' },
];

describe('Modal Builder dirender di akar template', () => {
    it.each(KASUS)('$tag tidak terkurung percabangan', ({ berkas: p, tag }) => {
        const { descriptor } = parse(berkas(p), { filename: p });
        const dalam = kedalamanKondisi(descriptor.template.content, tag);

        expect(dalam, `<${tag}> tak ditemukan di ${p}`).not.toBeNull();
        expect(
            dalam,
            `<${tag}> berada di dalam ${dalam} percabangan — pemanggil di cabang lain akan ` +
                'menyalakannya tanpa ada yang dirender, dan tak ada galat yang muncul.',
        ).toBe(0);
    });
});
