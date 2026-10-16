import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SopAturanPratinjau from './SopAturanPratinjau.vue';

const stubs = {
    HcisCard: { template: '<div><slot name="badge" /><slot /></div>' },
    HcisBadge: { template: '<span v-bind="$attrs">{{ label }}</span>', props: ['label'] },
    HcisAlert: { template: '<div class="peringatan">{{ title }} {{ message }}</div>', props: ['title', 'message'] },
};

const baris = (nama, angka = {}) => ({
    Id_SOP_Paket: nama,
    Nama_Paket: nama,
    matched: 0,
    new: 0,
    kept: 0,
    covered: 0,
    release: 0,
    withheld: 0,
    tertahan_tenggat: false,
    ...angka,
});

const pasang = (preview) => mount(SopAturanPratinjau, { props: { preview }, global: { stubs } });

describe('SopAturanPratinjau', () => {
    it('menampilkan satu baris per paket dan baris TOTAL', () => {
        const w = pasang({
            paket: [
                baris('Paket A', { matched: 10, new: 4, kept: 6 }),
                baris('Paket B', { matched: 10, new: 10 }),
            ],
            total: { matched: 20, new: 14, kept: 6, covered: 0, release: 0, withheld: 0 },
            auto_release: true,
        });

        expect(w.text()).toContain('Paket A');
        expect(w.text()).toContain('Paket B');
        expect(w.find('[data-uji="pratinjau-total"]').text()).toContain('20');
    });

    it('menandai paket yang tertahan gerbang tenggat absolut', () => {
        const w = pasang({
            paket: [baris('Paket Lewat', { matched: 5, new: 5, withheld: 5, tertahan_tenggat: true })],
            total: { matched: 5, new: 5, kept: 0, covered: 0, release: 0, withheld: 5 },
            auto_release: true,
        });

        expect(w.find('[data-uji="tertahan-tenggat"]').exists()).toBe(true);
    });

    it('tidak menandai apa pun ketika semua paket akan jalan', () => {
        const w = pasang({
            paket: [baris('Paket A', { matched: 3, new: 3 })],
            total: { matched: 3, new: 3, kept: 0, covered: 0, release: 0, withheld: 0 },
            auto_release: true,
        });

        expect(w.find('[data-uji="tertahan-tenggat"]').exists()).toBe(false);
    });

    /*
     * Kolom Dicabut selalu nol selama saklar auto-release mati. Tanpa kalimat
     * ini, admin membaca "0 dicabut" sebagai jawaban tentang datanya, padahal
     * itu jawaban tentang setelan yang bahkan tak ada di halaman ini.
     */
    it('menjelaskan kenapa kolom Dicabut nol ketika auto-release mati', () => {
        const w = pasang({
            paket: [baris('Paket A', { matched: 3, withheld: 2 })],
            total: { matched: 3, new: 0, kept: 0, covered: 0, release: 0, withheld: 2 },
            auto_release: false,
        });

        const peringatan = w.find('.peringatan');
        expect(peringatan.exists()).toBe(true);
        expect(peringatan.text()).toContain('Set Up System');
    });

    it('menyembunyikan penjelasan itu ketika auto-release menyala', () => {
        const w = pasang({
            paket: [baris('Paket A', { matched: 3, release: 2 })],
            total: { matched: 3, new: 0, kept: 0, covered: 0, release: 2, withheld: 0 },
            auto_release: true,
        });

        expect(w.find('.peringatan').exists()).toBe(false);
    });

    it('tetap menampilkan baris TOTAL walau belum ada paket', () => {
        const w = pasang({
            paket: [],
            total: { matched: 0, new: 0, kept: 0, covered: 0, release: 0, withheld: 0 },
            auto_release: true,
        });

        expect(w.find('[data-uji="pratinjau-total"]').exists()).toBe(true);
    });
});
