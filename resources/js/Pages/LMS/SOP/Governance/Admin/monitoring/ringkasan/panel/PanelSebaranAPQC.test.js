import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import PanelSebaranAPQC from './PanelSebaranAPQC.vue';

const dummyApqc = {
    Total_Proses: 5,
    Proses_Kosong: 1,
    Tanpa_Proses: 2,
    Baris: [
        { Id: 1, Kode: '1.0', Nama: 'Develop Vision and Strategy', Total: 4, Fungsional: 2, Publik: 1, Belum_Beredar: 1 },
        { Id: 2, Kode: '2.0', Nama: 'Develop and Manage Products', Total: 0, Fungsional: 0, Publik: 0, Belum_Beredar: 0 },
        { Id: 3, Kode: '3.0', Nama: 'Market and Sell Products', Total: 6, Fungsional: 4, Publik: 2, Belum_Beredar: 0 },
    ],
};

const pasang = (props = {}) => mount(PanelSebaranAPQC, {
    props: {
        apqc: { ...dummyApqc, ...props },
    },
});

describe('PanelSebaranAPQC', () => {
    it('merender total dokumen terpetakan dan status proses APQC', () => {
        const w = pasang();

        expect(w.text()).toContain('10 dokumen terpetakan ke APQC');
        expect(w.text()).toContain('4 Proses Terisi');
        expect(w.text()).toContain('1 Proses Kosong');
        expect(w.text()).toContain('2 Belum Ber-APQC');
    });

    it('menampilkan baris proses APQC beserta nama dan jumlah dokumen', () => {
        const w = pasang();

        expect(w.text()).toContain('1.0');
        expect(w.text()).toContain('Develop Vision and Strategy');
        expect(w.text()).toContain('2.0');
        expect(w.text()).toContain('belum ada dokumen');
        expect(w.text()).toContain('3.0');
        expect(w.text()).toContain('Market and Sell Products');
    });

    it('dapat menyaring daftar proses berdasarkan kata kunci pencarian', async () => {
        const w = pasang();
        const input = w.find('.psa-cari-input');

        await input.setValue('Vision');
        expect(w.text()).toContain('Develop Vision and Strategy');
        expect(w.text()).not.toContain('Market and Sell Products');

        await input.setValue('99.9');
        expect(w.text()).toContain('Tidak ditemukan proses APQC');
    });
});
