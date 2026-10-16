import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { ElDatePicker } from 'element-plus';
import HcisDatePicker from '@/components/ui/HcisDatePicker.vue';
import FormV2 from './formV2.vue';

/**
 * Kolom tanggal-jam FormV2 memakai date picker design system.
 *
 * Laporan user 5 Okt 2026: Due Date PICA tak bisa dipilih di ponsel — panel
 * el-date-picker yang tak di-teleport terpotong di dalam badan dialog. User
 * meminta komponennya diganti HcisDatePicker, yang di ponsel membuka dialog
 * kalendernya sendiri di luar wadah apa pun.
 *
 * Nilainya kini TEKS "YYYY-MM-DD HH:mm:ss", bukan objek Date: HcisDatePicker
 * membaca dan mengirim teks. Nilai lama berbentuk Date dinormalkan dulu, dan
 * pembanding tanggal Jadwal_Mulai/Jadwal_Akhir membaca teks itu tanpa
 * bergantung pada parser Safari, yang menolak "YYYY-MM-DD HH:mm:ss".
 */
const pasang = (modelValue, field = {}) =>
    mount(FormV2, {
        props: {
            modelValue,
            fields: [{ name: 'targetDate', label: 'Due Date', type: 'datetime-local', ...field }],
        },
    });

describe('FormV2 — kolom tanggal-jam', () => {
    it('memakai HcisDatePicker bertipe datetime, bukan el-date-picker', () => {
        const w = pasang({ targetDate: '' });

        expect(w.findComponent(ElDatePicker).exists()).toBe(false);
        expect(w.findComponent(HcisDatePicker).props('type')).toBe('datetime');
    });

    it('nilai Date lama dinormalkan menjadi teks yang dibaca picker', () => {
        const w = pasang({ targetDate: new Date(2026, 9, 12, 8, 30, 0) });

        expect(w.findComponent(HcisDatePicker).props('modelValue')).toBe('2026-10-12 08:30:00');
    });

    it('pilihan dari picker diteruskan apa adanya ke model', async () => {
        const w = pasang({ targetDate: '' });

        await w.findComponent(HcisDatePicker).vm.$emit('update:modelValue', '2026-10-12 09:00:00');

        expect(w.emitted('update:modelValue').at(-1)[0]).toEqual({ targetDate: '2026-10-12 09:00:00' });
    });

    it('mode lihat mengunci picker', () => {
        const w = pasang({ targetDate: '2026-10-12 09:00:00' }, { disabled: true });

        expect(w.findComponent(HcisDatePicker).props('disabled')).toBe(true);
    });

    it('Jadwal Akhir sebelum Jadwal Mulai dimatikan, membaca teks berformat SQL', () => {
        const w = mount(FormV2, {
            props: {
                modelValue: { Jadwal_Mulai: '2026-10-12 08:00:00.000', Jadwal_Akhir: '' },
                fields: [
                    { name: 'Jadwal_Mulai', label: 'Mulai', type: 'datetime-local' },
                    { name: 'Jadwal_Akhir', label: 'Akhir', type: 'datetime-local' },
                ],
            },
        });

        expect(w.vm.getDisabledDate('Jadwal_Akhir', new Date(2026, 9, 11))).toBe(true);
        expect(w.vm.getDisabledDate('Jadwal_Akhir', new Date(2026, 9, 12))).toBe(false);
    });

    it('teks bergaya input HTML (pakai T) dinormalkan ke spasi', () => {
        const w = pasang({ targetDate: '2026-10-12T08:30' });

        expect(w.findComponent(HcisDatePicker).props('modelValue')).toBe('2026-10-12 08:30:00');
    });

    it('format tampilan dari server dibaca picker (Edit PICA sesudah kembali)', () => {
        const w = pasang({ targetDate: '12 Okt 2026 08:00' });

        expect(w.findComponent(HcisDatePicker).props('modelValue')).toBe('2026-10-12 08:00:00');
    });
});
