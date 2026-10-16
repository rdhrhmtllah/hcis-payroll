/*
 * Satu kolom saringan.
 *
 * Yang dijaga di sini bukan rupanya, tapi PEMETAAN TIPE — bagian yang paling
 * mudah patah tanpa suara. Tipe yang salah eja dulu jatuh diam-diam ke input
 * teks: kolomnya tetap tampil wajar, nilainya tidak pernah cocok dengan apa
 * pun, dan tidak ada yang tahu kenapa hasil saringannya selalu kosong.
 */
import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisFilterField from './HcisFilterField.vue';

const pasang = (filter, modelValue = '') =>
    mount(HcisFilterField, { props: { filter, modelValue } });

describe('HcisFilterField — pemetaan tipe', () => {
    const PETA = [
        ['input', 'HcisField'],
        ['number', 'HcisInputNumber'],
        ['select', 'HcisSelect'],
        ['date', 'HcisDatePicker'],
        // Sejak peleburan, rentang tanggal dirender HcisDatePicker
        // type="daterange" -- bukan lagi komponen terpisah.
        ['daterange', 'HcisDatePicker'],
        ['cascader', 'HcisCascader'],
        ['radio', 'HcisRadio'],
        ['switch', 'HcisSwitch'],
    ];

    it.each(PETA)('tipe "%s" dirender oleh %s', (tipe, komponen) => {
        const w = pasang({ key: 'k', label: 'L', type: tipe, options: [] });
        expect(w.findComponent({ name: komponen }).exists()).toBe(true);
    });

    it('checkbox menghasilkan satu kotak per opsi', () => {
        const w = pasang({
            key: 'k',
            label: 'L',
            type: 'checkbox',
            options: [{ value: 'a', label: 'A' }, { value: 'b', label: 'B' }],
        });
        expect(w.findAllComponents({ name: 'HcisCheckbox' })).toHaveLength(2);
    });

    it('nama lama dari modernFilter tetap dikenali', () => {
        // Konfigurasi yang sudah ada di halaman lama harus bisa dipindah apa
        // adanya, tanpa diketik ulang.
        const alias = {
            text: 'HcisField',
            multiselect: 'HcisSelect',
            dateselect: 'HcisDatePicker',
            monthrange: 'HcisDatePicker',
        };

        for (const [tipe, komponen] of Object.entries(alias)) {
            const w = pasang({ key: 'k', label: 'L', type: tipe, options: [] });
            expect(w.findComponent({ name: komponen }).exists(), tipe).toBe(true);
        }
    });

    it('tipe tak dikenal TIDAK diam-diam jadi input teks', () => {
        /*
         * Ini perubahan sengaja dari perilaku lama. Cadangan diam membuat salah
         * ketik (`slect`) terlihat wajar sampai berbulan-bulan kemudian.
         */
        const w = pasang({ key: 'k', label: 'L', type: 'slect' });

        expect(w.findComponent({ name: 'HcisField' }).exists()).toBe(false);
        expect(w.find('.hcis-filter-field__galat').exists()).toBe(true);
        expect(w.text()).toContain('slect');
    });
});

describe('HcisFilterField — nilai', () => {
    it('mengirim nilai apa adanya', async () => {
        const w = pasang({ key: 'k', label: 'L', type: 'input' });
        await w.findComponent({ name: 'HcisField' }).vm.$emit('update:modelValue', 'halo');
        expect(w.emitted('update:modelValue').at(-1)).toEqual(['halo']);
    });

    it('checkbox menambah dan membuang nilai dari larik', async () => {
        const filter = {
            key: 'k',
            label: 'L',
            type: 'checkbox',
            options: [{ value: 'a', label: 'A' }, { value: 'b', label: 'B' }],
        };

        const w = pasang(filter, ['a']);
        const kotak = w.findAllComponents({ name: 'HcisCheckbox' });

        // Centang 'b' → larik bertambah, 'a' tidak hilang.
        await kotak[1].vm.$emit('update:modelValue', true);
        expect(w.emitted('update:modelValue').at(-1)[0]).toEqual(['a', 'b']);

        // Lepas 'a' → hanya 'a' yang dibuang.
        await kotak[0].vm.$emit('update:modelValue', false);
        expect(w.emitted('update:modelValue').at(-1)[0]).toEqual([]);
    });

    it('checkbox tidak menggandakan nilai yang sudah ada', async () => {
        const filter = {
            key: 'k',
            label: 'L',
            type: 'checkbox',
            options: [{ value: 'a', label: 'A' }],
        };
        const w = pasang(filter, ['a']);

        await w.findComponent({ name: 'HcisCheckbox' }).vm.$emit('update:modelValue', true);
        expect(w.emitted('update:modelValue').at(-1)[0]).toEqual(['a']);
    });

    it('checkbox tahan terhadap nilai awal yang bukan larik', async () => {
        // Konfigurasi lama kadang menaruh '' sebagai nilai awal. Tanpa penjagaan
        // ini, mencentang satu opsi akan melempar galat.
        const filter = {
            key: 'k',
            label: 'L',
            type: 'checkbox',
            options: [{ value: 'a', label: 'A' }],
        };
        const w = pasang(filter, '');

        await w.findComponent({ name: 'HcisCheckbox' }).vm.$emit('update:modelValue', true);
        expect(w.emitted('update:modelValue').at(-1)[0]).toEqual(['a']);
    });
});
