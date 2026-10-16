import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import PanelPiramidJenis from './PanelPiramidJenis.vue';

vi.mock('@inertiajs/vue3', () => ({
    router: {
        visit: vi.fn(),
    },
}));

const piramid = [
    { Id: 3, Nama: 'Prosedur', Kode: 'SOP', Level: 3, Total: 20, Fungsional: 6, Publik: 4, Belum_Beredar: 10 },
    { Id: 1, Nama: 'Kebijakan', Kode: 'PLC', Level: 1, Total: 0, Fungsional: 0, Publik: 0, Belum_Beredar: 0 },
    { Id: 4, Nama: 'Instruksi Kerja', Kode: 'IK', Level: 4, Total: 10, Fungsional: 10, Publik: 0, Belum_Beredar: 0 },
];

const pasang = () => mount(PanelPiramidJenis, { props: { piramid } });

describe('PanelPiramidJenis', () => {
    it('menampilkan tingkat yang kosong, tidak menyembunyikannya', () => {
        /* Tingkat piramid yang kosong adalah temuan paling penting di panel
           ini. Menyembunyikannya karena tak ada datanya membuat ketimpangan
           tak pernah terlihat. */
        const w = pasang();

        expect(w.text()).toContain('Kebijakan');
        expect(w.text()).toContain('belum ada dokumen');
    });

    it('mengurutkan dari puncak piramid ke dasar', () => {
        const nama = pasang().findAll('.ppj-nama').map((n) => n.text());

        expect(nama).toEqual(['Kebijakan', 'Prosedur', 'Instruksi Kerja']);
    });

    it('menskalakan batang terhadap tingkat terbanyak, bukan terhadap total dokumen', () => {
        /* Yang dibandingkan adalah antar-tingkat. Menskalakan ke total membuat
           semua tingkat tampak sama-sama pendek dan grafiknya tak berguna. */
        const w = pasang();
        const gaya = w.findAll('.ppj-baris')[1].findAll('.ppj-seg').map((s) => s.attributes('style'));

        // Prosedur = 20 dari puncak 20 → tiga segmennya berjumlah 100%
        const total = gaya.reduce((n, s) => n + parseFloat(s.match(/width:\s*([\d.]+)%/)[1]), 0);
        expect(Math.round(total)).toBe(100);
    });

    it('tidak merender segmen bernilai nol', () => {
        /* Segmen selebar 0% tetap menyisakan garis tipis pada beberapa mesin
           render, dan garis itu terbaca sebagai "ada sedikit". */
        const w = pasang();
        const segIk = w.findAll('.ppj-baris')[2].findAll('.ppj-seg');

        expect(segIk).toHaveLength(1);
    });

    it('menandai baris kosong supaya terbaca berbeda', () => {
        const w = pasang();

        expect(w.findAll('.ppj-baris')[0].classes()).toContain('is-kosong');
    });

    it('menampilkan ringkasan total dokumen, tingkat kosong, dan belum beredar di header', () => {
        const w = pasang();

        expect(w.text()).toContain('30');
        expect(w.text()).toContain('1 tingkat kosong');
        expect(w.text()).toContain('10 belum beredar');
    });

    it('dapat menyorot kelompok tertentu saat tombol legenda diklik', async () => {
        const w = pasang();
        const btnBelum = w.find('.ppj-lg-btn.is-belum');

        await btnBelum.trigger('click');

        // Segmen 'belum' aktif disorot, segmen lainnya redup
        expect(w.find('.ppj-seg.is-belum').classes()).toContain('is-sorot');
        expect(w.find('.ppj-seg.is-fungsional').classes()).toContain('is-redup');

        // Klik lagi untuk membatalkan sorotan
        await btnBelum.trigger('click');
        expect(w.find('.ppj-seg.is-belum').classes()).not.toContain('is-sorot');
        expect(w.find('.ppj-seg.is-fungsional').classes()).not.toContain('is-redup');
    });

    it('dapat membuka dan menutup panel rincian saat baris berisi dokumen diklik', async () => {
        const w = pasang();
        const barisProsedur = w.findAll('.ppj-baris')[1];

        // Awalnya panel rincian belum terbuka
        expect(w.find('.ppj-detail-panel').exists()).toBe(false);

        // Klik baris prosedur
        await barisProsedur.trigger('click');
        expect(w.find('.ppj-detail-panel').exists()).toBe(true);
        expect(w.find('.ppj-detail-panel').text()).toContain('Ditugaskan');
        expect(w.find('.ppj-detail-panel').text()).toContain('Beredar');
        expect(w.find('.ppj-detail-panel').text()).toContain('Belum Beredar');

        // Klik link navigasi ke master dokumen
        const linkMaster = w.find('.ppj-detail-link');
        await linkMaster.trigger('click');
        expect(router.visit).toHaveBeenCalledWith('/sop/admin/dokumen?jenis=3');

        // Klik lagi untuk menutup
        await barisProsedur.trigger('click');
        expect(w.find('.ppj-detail-panel').exists()).toBe(false);
    });

    it('tidak membuka rincian saat baris kosong diklik', async () => {
        const w = pasang();
        const barisKebijakan = w.findAll('.ppj-baris')[0];

        await barisKebijakan.trigger('click');
        expect(w.find('.ppj-detail-panel').exists()).toBe(false);
    });
});

