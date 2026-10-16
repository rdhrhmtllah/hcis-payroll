/*
 * Jebakan fokus modal.
 *
 * Yang dijaga di sini tidak terlihat di layar: sebuah dialog bisa tampak
 * sempurna sementara Tab diam-diam membawa pengguna keyboard ke formulir di
 * BALIK tirai — tempat yang tidak bisa ia lihat. Ia mengisi kolom yang salah
 * tanpa tahu, dan tidak ada yang salah secara visual untuk dilaporkan.
 */
import { afterEach, describe, expect, it } from 'vitest';
import { defineComponent, h, nextTick, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { useModalFocus } from './useModalFocus';

/** Lapisan tipis yang berperilaku seperti dialog: wadah + beberapa kontrol. */
const Lapisan = defineComponent({
    props: { terbuka: { type: Boolean, default: false } },
    setup(props, { expose }) {
        const wadah = ref(null);
        const fokus = useModalFocus();

        expose({
            wadah,
            buka: async () => {
                fokus.tangkap();
                await nextTick();
                fokus.tahan(wadah.value);
            },
            tutup: () => fokus.lepas(),
        });

        return () =>
            h('div', { ref: wadah, tabindex: '-1', class: 'wadah' }, [
                h('button', { class: 'awal' }, 'awal'),
                h('input', { class: 'tengah' }),
                h('button', { class: 'akhir' }, 'akhir'),
            ]);
    },
});

let pemasangan = [];
const pasang = (komponen, opsi) => {
    const w = mount(komponen, { ...opsi, attachTo: document.body });
    pemasangan.push(w);
    return w;
};

afterEach(() => {
    pemasangan.forEach((w) => w.unmount());
    pemasangan = [];
    document.body.innerHTML = '';
});

describe('useModalFocus', () => {
    it('mengembalikan fokus ke pemicunya saat ditutup', async () => {
        const pemicu = document.createElement('button');
        pemicu.className = 'pemicu';
        document.body.appendChild(pemicu);
        pemicu.focus();
        expect(document.activeElement).toBe(pemicu);

        const w = pasang(Lapisan);
        await w.vm.buka();
        expect(document.activeElement).toBe(w.vm.wadah);

        w.vm.tutup();
        expect(document.activeElement, 'fokus tidak kembali ke pemicunya').toBe(pemicu);

        pemicu.remove();
    });

    it('tidak meledak kalau pemicunya sudah terhapus dari dokumen', async () => {
        // Nyata: baris tabel yang memicu dialog lalu dihapus oleh aksi dialog
        // itu sendiri. Memanggil focus() pada simpul lepas tidak melempar,
        // tapi fokusnya hilang ke <body> — yang penting di sini ia tidak
        // menjatuhkan halaman.
        const pemicu = document.createElement('button');
        document.body.appendChild(pemicu);
        pemicu.focus();

        const w = pasang(Lapisan);
        await w.vm.buka();
        pemicu.remove();

        expect(() => w.vm.tutup()).not.toThrow();
    });

    it('Tab dari elemen terakhir kembali ke yang pertama, bukan keluar', async () => {
        const w = pasang(Lapisan);
        await w.vm.buka();

        const akhir = document.querySelector('.akhir');
        const awal = document.querySelector('.awal');
        akhir.focus();

        const ev = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true });
        document.dispatchEvent(ev);

        expect(ev.defaultPrevented, 'Tab dibiarkan keluar dari dialog').toBe(true);
        expect(document.activeElement).toBe(awal);
    });

    it('Shift+Tab dari elemen pertama melompat ke yang terakhir', async () => {
        const w = pasang(Lapisan);
        await w.vm.buka();

        const awal = document.querySelector('.awal');
        const akhir = document.querySelector('.akhir');
        awal.focus();

        const ev = new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true, cancelable: true });
        document.dispatchEvent(ev);

        expect(ev.defaultPrevented).toBe(true);
        expect(document.activeElement).toBe(akhir);
    });

    it('fokus yang terlanjur di luar wadah ditarik kembali ke dalam', async () => {
        // Terjadi kalau elemen pemegang fokus di dalam dialog terhapus:
        // browser menjatuhkan fokus ke <body>, dan Tab berikutnya akan
        // menelusuri halaman di belakang tirai.
        const luar = document.createElement('button');
        document.body.appendChild(luar);

        const w = pasang(Lapisan);
        await w.vm.buka();
        luar.focus();

        const ev = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true });
        document.dispatchEvent(ev);

        expect(document.activeElement, 'fokus dibiarkan berkeliaran di luar dialog')
            .toBe(document.querySelector('.awal'));

        luar.remove();
    });

    it('berhenti menahan setelah ditutup', async () => {
        const w = pasang(Lapisan);
        await w.vm.buka();
        w.vm.tutup();

        const ev = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true });
        document.dispatchEvent(ev);

        expect(ev.defaultPrevented, 'masih menahan Tab padahal sudah ditutup').toBe(false);
    });

    it('tombol nonaktif tidak ikut dihitung sebagai perhentian Tab', async () => {
        const Lapisan2 = defineComponent({
            setup(_, { expose }) {
                const wadah = ref(null);
                const fokus = useModalFocus();
                expose({
                    wadah,
                    buka: async () => {
                        fokus.tangkap();
                        await nextTick();
                        fokus.tahan(wadah.value);
                    },
                });
                return () =>
                    h('div', { ref: wadah, tabindex: '-1' }, [
                        h('button', { class: 'a' }, 'a'),
                        h('button', { class: 'b', disabled: true }, 'b'),
                        h('button', { class: 'c' }, 'c'),
                    ]);
            },
        });

        const w = pasang(Lapisan2);
        await w.vm.buka();

        document.querySelector('.c').focus();
        const ev = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true });
        document.dispatchEvent(ev);

        // Kalau tombol nonaktif ikut dihitung, 'c' bukan yang terakhir dan
        // lingkarannya tidak pernah menutup.
        expect(document.activeElement).toBe(document.querySelector('.a'));
    });
});
