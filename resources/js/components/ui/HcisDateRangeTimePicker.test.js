import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import HcisDatePicker from './HcisDatePicker.vue';
import HcisDateRangeTimePicker from './HcisDateRangeTimePicker.vue';

/**
 * HcisDateRangeTimePicker bukan lagi komponen berdiri sendiri.
 *
 * Kemampuan rentang tanggal + jam dilebur ke
 * <HcisDatePicker type="datetimerange"> dan berkas ini tinggal pembungkus
 * kompatibilitas untuk impor lama. Uji sebelumnya masih memeriksa markup yang
 * dulu direndernya sendiri (`.hcis-trigger-pill`, `.hcis-calendar-panel`,
 * `.hcis-time-strip`) — tidak satu pun masih ada.
 *
 * Yang dijaga sekarang penyalurannya, sebab itulah satu-satunya alasan berkas
 * ini masih hidup.
 */
describe('HcisDateRangeTimePicker.vue (pembungkus kompatibilitas)', () => {
    const pasang = (props = {}) =>
        mount(HcisDateRangeTimePicker, {
            props: {
                modelValue: ['2026-08-01 09:00', '2026-08-12 17:00'],
                teleportTo: false,
                ...props,
            },
        });

    it('menyalurkan ke HcisDatePicker dalam mode rentang tanggal+jam', () => {
        const dalam = pasang().findComponent(HcisDatePicker);

        expect(dalam.exists()).toBe(true);
        expect(dalam.props('type')).toBe('datetimerange');
    });

    it('meneruskan prop yang dipegangnya ke komponen di dalamnya', () => {
        const dalam = pasang({ placeholder: 'Pilih rentang waktu', disabled: true, clearable: false })
            .findComponent(HcisDatePicker);

        expect(dalam.props('placeholder')).toBe('Pilih rentang waktu');
        expect(dalam.props('disabled')).toBe(true);
        expect(dalam.props('clearable')).toBe(false);
    });

    it('meneruskan modelValue ke dalam, dan memuntahkan kembali perubahannya', async () => {
        const w = pasang();
        const dalam = w.findComponent(HcisDatePicker);

        expect(dalam.props('modelValue')).toEqual(['2026-08-01 09:00', '2026-08-12 17:00']);

        await dalam.vm.$emit('update:modelValue', ['2026-09-01 08:00', '2026-09-30 16:00']);

        expect(w.emitted('update:modelValue')?.[0]?.[0]).toEqual([
            '2026-09-01 08:00',
            '2026-09-30 16:00',
        ]);
    });

    it('meneruskan event change dan clear tanpa menelannya', async () => {
        const w = pasang();
        const dalam = w.findComponent(HcisDatePicker);

        await dalam.vm.$emit('change', ['2026-09-01 08:00', '2026-09-30 16:00']);
        await dalam.vm.$emit('clear');

        expect(w.emitted('change')).toBeTruthy();
        expect(w.emitted('clear')).toBeTruthy();
    });
});
