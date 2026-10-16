/*
 * Kunci gulir bertumpuk.
 *
 * Bug yang dijaga di sini nyata dan pernah ada: tiap lapisan menutup layar
 * (dialog, laci, penampil berkas) menyetel `document.body.style.overflow`
 * sendiri-sendiri. Selama hanya satu lapisan terbuka, semuanya benar. Begitu
 * dua terbuka — penampil berkas dibuka DARI DALAM dialog rincian — menutup
 * yang kedua membuka gulir halaman di belakang yang pertama.
 *
 * Gejalanya halus dan mudah salah dibaca: isinya bergeser di balik tirai, dan
 * saat tirai ditutup pengguna mendapati dirinya di tempat lain.
 */
import { beforeEach, describe, expect, it } from 'vitest';
import { defineComponent, h } from 'vue';
import { mount } from '@vue/test-utils';
import { useScrollLock } from './useScrollLock';

/** Komponen tipis yang berperilaku seperti lapisan penutup layar. */
const Lapisan = defineComponent({
    setup(_, { expose }) {
        const { kunci, buka } = useScrollLock();
        expose({ kunci, buka });
        return () => h('div');
    },
});

const overflow = () => document.body.style.overflow;

describe('useScrollLock', () => {
    beforeEach(() => {
        document.body.style.overflow = '';
    });

    it('mengunci saat lapisan pertama membuka, membuka saat lapisan terakhir menutup', () => {
        const a = mount(Lapisan);

        a.vm.kunci();
        expect(overflow()).toBe('hidden');

        a.vm.buka();
        expect(overflow()).toBe('');

        a.unmount();
    });

    it('lapisan kedua yang menutup TIDAK membuka gulir milik lapisan pertama', () => {
        const dialog = mount(Lapisan);
        const penampil = mount(Lapisan);

        dialog.vm.kunci();
        penampil.vm.kunci();
        expect(overflow()).toBe('hidden');

        // Penampil berkas ditutup — dialognya masih terbuka.
        penampil.vm.buka();
        expect(overflow(), 'gulir terbuka padahal dialog masih menutup layar').toBe('hidden');

        dialog.vm.buka();
        expect(overflow()).toBe('');

        dialog.unmount();
        penampil.unmount();
    });

    it('satu pemakai hanya menghitung sekali, berapa kali pun mengunci', () => {
        // Tanpa penjaga ini, watch yang berjalan dua kali (mis. immediate +
        // perubahan nilai) menaikkan penghitung dua kali dan halaman tidak
        // pernah bisa digulir lagi.
        const a = mount(Lapisan);
        const b = mount(Lapisan);

        a.vm.kunci();
        a.vm.kunci();
        a.vm.kunci();
        b.vm.kunci();

        a.vm.buka();
        expect(overflow(), 'a mengunci 3× tapi hanya boleh dihitung sekali').toBe('hidden');

        b.vm.buka();
        expect(overflow()).toBe('');

        a.unmount();
        b.unmount();
    });

    it('membuka berlebih tidak merusak kunci milik lapisan lain', () => {
        const a = mount(Lapisan);
        const b = mount(Lapisan);

        a.vm.kunci();
        b.vm.buka();      // b tidak pernah mengunci
        b.vm.buka();

        expect(overflow(), 'buka berlebih menembus kunci milik a').toBe('hidden');

        a.vm.buka();
        expect(overflow()).toBe('');

        a.unmount();
        b.unmount();
    });

    it('komponen yang dilepas selagi terkunci tidak meninggalkan halaman beku', () => {
        // Ini yang paling berbahaya: navigasi Inertia melepas komponen tanpa
        // sempat menutupnya. Kalau kuncinya ikut hilang bersama komponennya,
        // halaman berikutnya tidak bisa digulir sama sekali dan satu-satunya
        // jalan keluar bagi pengguna adalah memuat ulang.
        const a = mount(Lapisan);
        a.vm.kunci();
        expect(overflow()).toBe('hidden');

        a.unmount();
        expect(overflow(), 'halaman tetap beku setelah komponennya dilepas').toBe('');
    });

    it('mengembalikan overflow milik halaman, bukan menganggapnya kosong', () => {
        // Ada halaman warisan yang menyetel overflow body-nya sendiri.
        // Mengembalikannya ke '' merusak halaman itu — dan kerusakannya baru
        // muncul setelah dialog ditutup, jadi hampir mustahil dilacak.
        document.body.style.overflow = 'clip';

        const a = mount(Lapisan);
        a.vm.kunci();
        expect(overflow()).toBe('hidden');

        a.vm.buka();
        expect(overflow()).toBe('clip');

        a.unmount();
        document.body.style.overflow = '';
    });
});
