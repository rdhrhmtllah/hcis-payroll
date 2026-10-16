import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import SopKontenTerpakai from './SopKontenTerpakai.vue';

vi.mock('axios');

/**
 * Pemberitahuan "berkas ini sudah dipakai dokumen lain".
 *
 * Sejak awal komponen ini memberi tahu tanpa melarang — dan itu tetap benar.
 * Yang kurang adalah JAWABAN: satu-satunya cara meneruskan adalah mengabaikan
 * kotak yang menyala, dan mengabaikan peringatan adalah kebiasaan yang menular
 * ke peringatan berikutnya. Tombol "Tetap pakai" mengubah pengabaian menjadi
 * keputusan.
 */

const PEMAKAI = [
    { Id_SOP_Dokumen: 'dok-a', Judul: 'SOP Meeting', Nomor_SOP_Resmi: 'SOP-MEET-001', Paket: ['Paket Umum'] },
];

async function pasang(props = {}, rows = PEMAKAI) {
    axios.get = vi.fn(() => Promise.resolve({ data: { success: true, result: { rows } } }));

    const w = mount(SopKontenTerpakai, {
        props: { konten: 'hash-konten', ...props },
    });
    await flushPromises();

    return w;
}

function tombol(w, teks) {
    return w.findAll('button').find((b) => new RegExp(teks, 'i').test(b.text()));
}

describe('SopKontenTerpakai', () => {
    it('diam sepenuhnya bila berkasnya belum dipakai dokumen lain', async () => {
        const w = await pasang({}, []);

        expect(w.find('.kt-kotak').exists()).toBe(false);
    });

    it('menawarkan tombol tetap pakai', async () => {
        const w = await pasang();

        expect(tombol(w, 'Tetap pakai')).toBeTruthy();
    });

    it('menyusut jadi baris konfirmasi setelah dijawab', async () => {
        const w = await pasang();
        await tombol(w, 'Tetap pakai').trigger('click');

        expect(w.find('.kt-kotak.diakui').exists()).toBe(true);
        /* Daftar dokumennya ikut menutup — peringatan yang tetap berteriak
           sesudah dijawab membuat admin ragu jawabannya sudah masuk. */
        expect(w.find('.kt-daftar').exists()).toBe(false);
        expect(w.emitted('tetap-pakai')).toBeTruthy();
        expect(w.emitted('tetap-pakai')[0][0]).toBe('hash-konten');
    });

    it('bisa ditinjau ulang setelah dijawab', async () => {
        const w = await pasang();
        await tombol(w, 'Tetap pakai').trigger('click');
        await tombol(w, 'Tinjau ulang').trigger('click');

        expect(w.find('.kt-kotak.diakui').exists()).toBe(false);
        expect(w.find('.kt-daftar').exists()).toBe(true);
    });

    it('berkas berbeda adalah keputusan berbeda — pengakuannya direset', async () => {
        const w = await pasang();
        await tombol(w, 'Tetap pakai').trigger('click');
        expect(w.find('.kt-kotak.diakui').exists()).toBe(true);

        await w.setProps({ konten: 'hash-konten-lain' });
        await flushPromises();

        expect(w.find('.kt-kotak.diakui').exists()).toBe(false);
    });

    it('tanpa tombol pakai-ini, sarannya tak menyuruh memakai yang tak ada', async () => {
        const w = await pasang({ bisaPakai: false });

        expect(w.find('.kt-saran').text()).not.toMatch(/pakai saja\./);
        expect(tombol(w, 'Pakai ini')).toBeUndefined();
    });

    it('di Builder, saran memakai ulang tetap muncul bersama tombolnya', async () => {
        const w = await pasang({ bisaPakai: true });

        expect(w.find('.kt-saran').text()).toMatch(/pakai saja/);
        expect(tombol(w, 'Pakai ini')).toBeTruthy();
    });
});
