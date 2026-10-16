/*
 * Rel vertikal yang dilipat.
 *
 * Saat dilipat, label disembunyikan dan yang tersisa hanya ikon. Tab TANPA
 * ikon karena itu menjadi tombol kosong — tidak bisa dikenali, dan tidak ada
 * cara menebak isinya selain mengekliknya satu per satu.
 *
 * Ini kegagalan senyap: tidak ada galat, tidak ada yang terlihat rusak, hanya
 * navigasi yang berhenti bisa dibaca. Ia terjadi sungguhan di /design-system —
 * delapan dari sembilan rumpun tampil tanpa ikon karena datanya lupa
 * mencantumkannya.
 */
import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisTabs from './HcisTabs.vue';

const TABS = [
    { key: 'a', label: 'Semua Rumpun', icon: 'grid-3x3-gap' },
    { key: 'b', label: 'Umpan balik' },      // sengaja tanpa ikon
    { key: 'c', label: 'Data' },             // sengaja tanpa ikon
];

const pasang = (props = {}) =>
    mount(HcisTabs, {
        props: { modelValue: 'a', tabs: TABS, orientation: 'vertical', ...props },
    });

describe('HcisTabs — rel vertikal terlipat', () => {
    it('tab tanpa ikon tetap punya penanda saat dilipat', () => {
        const w = pasang({ collapsed: true });
        const tombol = w.findAll('.hcis-tabs__tab');

        // Tiap tombol wajib menyisakan sesuatu yang terlihat: ikon ATAU inisial.
        tombol.forEach((t, i) => {
            const adaIkon = t.find('.hcis-tabs__icon').exists();
            const adaInisial = t.find('.hcis-tabs__inisial').exists();
            expect(
                adaIkon || adaInisial,
                'tab ke-' + i + ' (' + TABS[i].label + ') kosong saat dilipat',
            ).toBe(true);
        });
    });

    it('inisial diambil dari dua kata pertama', () => {
        const w = pasang({ collapsed: true });
        const inisial = w.findAll('.hcis-tabs__inisial').map((n) => n.text());
        expect(inisial).toEqual(['UB', 'D']);
    });

    it('tab yang PUNYA ikon tidak ikut diberi inisial', () => {
        // Kalau keduanya muncul, tombolnya jadi dua lambang berdampingan di
        // ruang selebar 3,75rem.
        const w = pasang({ collapsed: true });
        const pertama = w.findAll('.hcis-tabs__tab')[0];
        expect(pertama.find('.hcis-tabs__icon').exists()).toBe(true);
        expect(pertama.find('.hcis-tabs__inisial').exists()).toBe(false);
    });

    it('saat TIDAK dilipat, inisial tidak dipakai sama sekali', () => {
        // Labelnya terbaca penuh; inisial di sebelahnya hanya kebisingan.
        const w = pasang({ collapsed: false });
        expect(w.findAll('.hcis-tabs__inisial')).toHaveLength(0);
    });

    it('orientasi mendatar tidak pernah memakai inisial', () => {
        const w = mount(HcisTabs, {
            props: { modelValue: 'a', tabs: TABS, orientation: 'horizontal', collapsed: true },
        });
        expect(w.findAll('.hcis-tabs__inisial')).toHaveLength(0);
    });
});
