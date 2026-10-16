import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import HcisDatePicker from './HcisDatePicker.vue';
import HcisDateTimePicker from './HcisDateTimePicker.vue';

/**
 * HcisDateTimePicker bukan lagi komponen berdiri sendiri.
 *
 * Kemampuan tanggal + jam dilebur ke <HcisDatePicker type="datetime"> dan
 * berkas ini tinggal pembungkus kompatibilitas untuk impor lama. Uji
 * sebelumnya masih memeriksa markup yang dulu direndernya sendiri
 * (`.hcis-dt-combined-view`, `.hcis-dt-pill`, `.hcis-dt-calendar-side`,
 * `.hcis-time-item`) — tidak satu pun masih ada, jadi merahnya tidak
 * menandakan cacat apa pun.
 *
 * Yang dijaga sekarang adalah satu-satunya alasan berkas ini masih hidup:
 * penyaluran ke HcisDatePicker beserta prop dan event-nya. Kalau itu putus,
 * setiap halaman yang masih memakai nama lama ikut rusak diam-diam.
 */
describe('HcisDateTimePicker.vue (pembungkus kompatibilitas)', () => {
    const pasang = (props = {}) =>
        mount(HcisDateTimePicker, {
            props: { modelValue: '2026-08-01 09:30', teleportTo: false, ...props },
        });

    it('menyalurkan ke HcisDatePicker dalam mode tanggal+jam', () => {
        const dalam = pasang().findComponent(HcisDatePicker);

        expect(dalam.exists()).toBe(true);
        expect(dalam.props('type')).toBe('datetime');
    });

    it('merender kotak pemicu versi tanggal+jam', () => {
        expect(pasang().find('.hcis-date-picker__trigger--datetime').exists()).toBe(true);
    });

    it('meneruskan prop yang dipegangnya ke komponen di dalamnya', () => {
        const dalam = pasang({ placeholder: 'Pilih waktu', disabled: true, clearable: false })
            .findComponent(HcisDatePicker);

        expect(dalam.props('placeholder')).toBe('Pilih waktu');
        expect(dalam.props('disabled')).toBe(true);
        expect(dalam.props('clearable')).toBe(false);
    });

    it('meneruskan modelValue ke dalam, dan memuntahkan kembali perubahannya', async () => {
        const w = pasang();
        const dalam = w.findComponent(HcisDatePicker);

        expect(dalam.props('modelValue')).toBe('2026-08-01 09:30');

        await dalam.vm.$emit('update:modelValue', '2026-09-02 14:00');

        expect(w.emitted('update:modelValue')?.[0]?.[0]).toBe('2026-09-02 14:00');
    });

    it('meneruskan event change dan clear tanpa menelannya', async () => {
        const w = pasang();
        const dalam = w.findComponent(HcisDatePicker);

        await dalam.vm.$emit('change', '2026-09-02 14:00');
        await dalam.vm.$emit('clear');

        expect(w.emitted('change')).toBeTruthy();
        expect(w.emitted('clear')).toBeTruthy();
    });
});
