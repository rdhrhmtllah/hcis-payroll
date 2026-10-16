import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import HcisDatePicker from './HcisDatePicker.vue';
import HcisDateRangePicker from './HcisDateRangePicker.vue';

/**
 * HcisDateRangePicker bukan lagi komponen berdiri sendiri.
 *
 * Seluruh kemampuan rentang tanggal dilebur ke <HcisDatePicker type="daterange">
 * dan berkas ini tinggal pembungkus kompatibilitas untuk impor lama. Uji
 * sebelumnya masih memeriksa markup yang dulu direndernya sendiri
 * (`.hcis-date-range-picker__trigger`, `.hcis-trigger-pill`,
 * `.hcis-calendar-panel`, `.hcis-range-chip`) — tidak satu pun masih ada, jadi
 * ketiga ujinya merah sejak peleburan itu tanpa menandakan cacat apa pun.
 *
 * Yang PANTAS dijaga di sini bukan markupnya, melainkan satu-satunya alasan
 * berkas ini masih hidup: ia harus tetap menyalurkan ke HcisDatePicker dalam
 * mode rentang, dan tetap meneruskan prop serta event. Kalau penyaluran itu
 * putus, setiap halaman yang masih mengimpor nama lama ikut rusak — diam-diam,
 * karena komponennya tetap ter-render.
 */
describe('HcisDateRangePicker.vue (pembungkus kompatibilitas)', () => {
    const pasang = (props = {}) =>
        mount(HcisDateRangePicker, {
            props: { modelValue: ['2026-08-01', '2026-08-12'], teleportTo: false, ...props },
        });

    it('menyalurkan ke HcisDatePicker dalam mode rentang', () => {
        const dalam = pasang().findComponent(HcisDatePicker);

        expect(dalam.exists()).toBe(true);
        expect(dalam.props('type')).toBe('daterange');
    });

    it('merender kotak pemicu versi rentang, bukan versi tanggal tunggal', () => {
        const w = pasang();

        expect(w.find('.hcis-date-picker__trigger--range').exists()).toBe(true);
        expect(w.find('.hcis-date-picker__range-inputs').exists()).toBe(true);
    });

    it('meneruskan prop yang dipegangnya ke komponen di dalamnya', () => {
        const dalam = pasang({ placeholder: 'Pilih rentang', disabled: true, clearable: false })
            .findComponent(HcisDatePicker);

        expect(dalam.props('placeholder')).toBe('Pilih rentang');
        expect(dalam.props('disabled')).toBe(true);
        expect(dalam.props('clearable')).toBe(false);
    });

    it('meneruskan modelValue ke dalam, dan memuntahkan kembali perubahannya', async () => {
        const w = pasang();
        const dalam = w.findComponent(HcisDatePicker);

        expect(dalam.props('modelValue')).toEqual(['2026-08-01', '2026-08-12']);

        await dalam.vm.$emit('update:modelValue', ['2026-09-01', '2026-09-30']);

        expect(w.emitted('update:modelValue')?.[0]?.[0]).toEqual(['2026-09-01', '2026-09-30']);
    });

    it('meneruskan event change dan clear tanpa menelannya', async () => {
        const w = pasang();
        const dalam = w.findComponent(HcisDatePicker);

        await dalam.vm.$emit('change', ['2026-09-01', '2026-09-30']);
        await dalam.vm.$emit('clear');

        expect(w.emitted('change')).toBeTruthy();
        expect(w.emitted('clear')).toBeTruthy();
    });
});
