import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisIcon from './HcisIcon.vue';
import HcisAlert from './HcisAlert.vue';
import HcisAvatar from './HcisAvatar.vue';
import HcisTextarea from './HcisTextarea.vue';
import HcisCheckbox from './HcisCheckbox.vue';
import HcisRadio from './HcisRadio.vue';
import HcisProgress from './HcisProgress.vue';
import HcisSteps from './HcisSteps.vue';
import HcisDivider from './HcisDivider.vue';

describe('HcisIcon', () => {
    it('memakai kelas Bootstrap Icons dan disembunyikan bila tanpa label', () => {
        const wrapper = mount(HcisIcon, { props: { name: 'check-circle' } });

        expect(wrapper.classes()).toContain('bi-check-circle');
        expect(wrapper.attributes('aria-hidden')).toBe('true');
        expect(wrapper.attributes('aria-label')).toBeUndefined();
    });

    it('dibacakan pembaca layar bila label diisi', () => {
        const wrapper = mount(HcisIcon, { props: { name: 'trash', label: 'Hapus' } });

        expect(wrapper.attributes('aria-hidden')).toBeUndefined();
        expect(wrapper.attributes('aria-label')).toBe('Hapus');
        expect(wrapper.attributes('role')).toBe('img');
    });
});

describe('HcisAlert', () => {
    it('memakai varian dan memancarkan close', async () => {
        const wrapper = mount(HcisAlert, {
            props: { variant: 'warning', title: 'Periode ditutup', closable: true },
        });

        // Akar komponennya <Transition>; kelasnya ada di div di dalamnya.
        expect(wrapper.find('.hcis-alert').classes()).toContain('hcis-alert--warning');
        await wrapper.find('.hcis-alert__close').trigger('click');
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('tanpa closable tidak menampilkan tombol tutup', () => {
        const wrapper = mount(HcisAlert, { props: { description: 'Info biasa.' } });
        expect(wrapper.find('.hcis-alert__close').exists()).toBe(false);
    });
});

describe('HcisAvatar', () => {
    it('jatuh ke inisial bila src kosong', () => {
        const wrapper = mount(HcisAvatar, { props: { name: 'Budi Santoso' } });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toBe('BS');
    });

    it('memakai satu huruf untuk nama satu kata', () => {
        const wrapper = mount(HcisAvatar, { props: { name: 'Sukarno' } });
        expect(wrapper.text()).toBe('S');
    });

    it('memakai foto bila src diisi, dengan alt dari name', () => {
        const wrapper = mount(HcisAvatar, { props: { name: 'Budi Santoso', src: '/foto.jpg' } });

        expect(wrapper.find('img').attributes('alt')).toBe('Budi Santoso');
    });
});

describe('HcisTextarea', () => {
    it('memancarkan nilai baru', async () => {
        const wrapper = mount(HcisTextarea, { props: { id: 'ket', label: 'Keterangan', modelValue: '' } });

        await wrapper.find('textarea').setValue('Sakit');
        expect(wrapper.emitted('update:modelValue')).toEqual([['Sakit']]);
    });

    it('menampilkan penghitung sisa karakter bila maxlength diisi', () => {
        const wrapper = mount(HcisTextarea, {
            props: { id: 'ket', label: 'Keterangan', modelValue: 'Sakit', maxlength: 100 },
        });

        expect(wrapper.find('.hcis-textarea__count').text()).toBe('5/100');
    });

    it('tanpa maxlength tidak menampilkan penghitung', () => {
        const wrapper = mount(HcisTextarea, { props: { id: 'ket', label: 'Keterangan' } });
        expect(wrapper.find('.hcis-textarea__count').exists()).toBe(false);
    });
});

describe('HcisCheckbox', () => {
    it('memancarkan boolean saat dipakai tunggal', async () => {
        const wrapper = mount(HcisCheckbox, { props: { modelValue: false, label: 'Setuju' } });

        await wrapper.find('input').setValue(true);
        expect(wrapper.emitted('update:modelValue')).toEqual([[true]]);
    });

    it('menambah dan membuang nilai dari array saat dipakai berkelompok', async () => {
        const wrapper = mount(HcisCheckbox, { props: { modelValue: ['a'], value: 'b', label: 'B' } });

        await wrapper.find('input').setValue(true);
        expect(wrapper.emitted('update:modelValue')[0][0]).toEqual(['a', 'b']);
    });

    it('menandai keadaan sebagian tercentang', () => {
        const wrapper = mount(HcisCheckbox, { props: { modelValue: false, indeterminate: true } });
        expect(wrapper.find('input').element.indeterminate).toBe(true);
    });
});

describe('HcisRadio', () => {
    it('merender seluruh opsi dan memancarkan nilai terpilih', async () => {
        const options = [{ value: 'a', label: 'Tahunan' }, { value: 'b', label: 'Melahirkan' }];
        const wrapper = mount(HcisRadio, { props: { modelValue: 'a', options, name: 'jenis' } });

        expect(wrapper.findAll('input[type="radio"]')).toHaveLength(2);
        await wrapper.findAll('input')[1].setValue(true);
        expect(wrapper.emitted('update:modelValue')).toEqual([['b']]);
    });
});

describe('HcisProgress', () => {
    it('membatasi nilai pada rentang 0–100 dan memakai sentiment untuk warna', () => {
        const wrapper = mount(HcisProgress, { props: { value: 140, sentiment: 'bad' } });

        expect(wrapper.find('.hcis-progress__fill').attributes('style')).toContain('width: 100%');
        expect(wrapper.find('.hcis-progress__fill').classes()).toContain('hcis-progress__fill--danger');
    });

    it('membatasi nilai negatif ke nol', () => {
        const wrapper = mount(HcisProgress, { props: { value: -20 } });
        expect(wrapper.find('.hcis-progress__fill').attributes('style')).toContain('width: 0%');
    });
});

describe('HcisSteps', () => {
    const steps = [{ label: 'Diajukan' }, { label: 'Atasan' }, { label: 'HRD' }];

    it('menandai tahap selesai, berjalan, dan belum', () => {
        const wrapper = mount(HcisSteps, { props: { current: 1, steps } });
        const items = wrapper.findAll('.hcis-steps__item');

        expect(items[0].classes()).toContain('is-done');
        expect(items[1].classes()).toContain('is-current');
        expect(items[2].classes()).toContain('is-todo');
    });

    it('menghormati state eksplisit untuk tahap yang ditolak', () => {
        const ditolak = [{ label: 'Diajukan' }, { label: 'Atasan', state: 'rejected' }];
        const wrapper = mount(HcisSteps, { props: { current: 1, steps: ditolak } });

        expect(wrapper.findAll('.hcis-steps__item')[1].classes()).toContain('is-rejected');
    });
});

describe('HcisDivider', () => {
    it('merender label di tengah bila diisi', () => {
        const wrapper = mount(HcisDivider, { props: { label: 'Riwayat' } });
        expect(wrapper.find('.hcis-divider__label').text()).toBe('Riwayat');
    });

    it('tanpa label hanya merender garis', () => {
        const wrapper = mount(HcisDivider);
        expect(wrapper.find('.hcis-divider__label').exists()).toBe(false);
    });
});
