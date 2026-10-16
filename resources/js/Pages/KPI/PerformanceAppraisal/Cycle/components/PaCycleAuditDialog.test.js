import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PaCycleAuditDialog from './PaCycleAuditDialog.vue';

const uiStubs = {
    HcisBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
    HcisButton: { props: ['label'], template: '<button>{{ label }}</button>' },
    HcisCheckbox: { props: ['label', 'description'], template: '<label>{{ label }} {{ description }}</label>' },
    HcisDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /><slot name="footer" /></div>' },
    HcisField: { props: ['label', 'modelValue'], template: '<label>{{ label }}<input :value="modelValue" /></label>' },
    HcisFilterBar: { template: '<div class="filter-bar-stub" />' },
    HcisIcon: { template: '<i />' },
    HcisPagination: { template: '<nav class="pagination-stub" />' },
    HcisResultState: { props: ['title', 'description'], template: '<div class="result-state">{{ title }} {{ description }}</div>' },
    HcisSelect: { template: '<div class="select-stub"><slot /></div>' },
    HcisSelectOption: { template: '<option><slot /></option>' },
    HcisTimeline: {
        props: ['items'],
        template: '<div class="timeline-stub"><div v-for="item in items" :key="item.id">{{ item.title }} {{ item.status }} {{ item.name }}<slot name="itemContent" :item="item" /></div></div>',
    },
};

describe('PaCycleAuditDialog', () => {
    it('menampilkan kedua cacah, membedakan sumber kejadian, dan menjelaskan batas nilai lama', () => {
        const wrapper = mount(PaCycleAuditDialog, {
            props: {
                modelValue: true,
                cycle: { Nama_Cycle: 'Siklus Contoh', Kode_Cycle: 'PA-DEMO-FE', Status_Cycle: 'CLOSED' },
                events: [
                    { waktu: '2026-09-01 10:00:00', jenis: 'KALIBRASI', sasaran: 'Diva', rincian: 'Grade B menjadi A', oleh_sistem: false, aktor_nama: 'Budi' },
                    { waktu: '2026-09-01 09:00:00', jenis: 'PESERTA_DIKECUALIKAN', sasaran: 'Firman', oleh_sistem: true, aktor_tidak_diketahui: true },
                ],
                counts: { keputusan_manusia: 3, oleh_sistem: 99 },
                bounds: { konfigurasi_tanpa_nilai_lama: true, catatan: 'Nilai sebelum perubahan konfigurasi tidak disimpan.' },
                pagination: { halaman: 1, per_halaman: 10, total: 102, total_halaman: 11, dipangkas: false },
            },
            global: { stubs: uiStubs },
        });

        expect(wrapper.text()).toContain('3');
        expect(wrapper.text()).toContain('99');
        expect(wrapper.text()).toContain('Keputusan manusia');
        expect(wrapper.text()).toContain('Aturan sistem');
        expect(wrapper.text()).toContain('Nilai sebelum perubahan konfigurasi tidak disimpan.');
    });

    it('membedakan daftar kosong dari state gagal', () => {
        const wrapper = mount(PaCycleAuditDialog, {
            props: { modelValue: true, events: [], error: '' },
            global: { stubs: uiStubs },
        });

        expect(wrapper.text()).toContain('Belum ada kejadian yang cocok');
        expect(wrapper.text()).not.toContain('Audit belum dapat dimuat');
    });

    it('menampilkan jenis audit baru dan nama aktor dari server', () => {
        const wrapper = mount(PaCycleAuditDialog, {
            props: {
                modelValue: true,
                events: [
                    { waktu: '2026-09-02 10:00:00', jenis: 'HASIL_DITERBITKAN', sasaran: 'Budi', oleh_sistem: false, aktor_nama: 'Ridho' },
                    { waktu: '2026-09-02 09:00:00', jenis: 'BOBOT_PILAR_DIUBAH', sasaran: 'Level 3', oleh_sistem: true },
                ],
            },
            global: { stubs: uiStubs },
        });

        expect(wrapper.text()).toContain('HASIL DITERBITKAN');
        expect(wrapper.text()).toContain('BOBOT PILAR DIUBAH');
        expect(wrapper.text()).toContain('Ridho');
        expect(wrapper.text()).toContain('Oleh sistem');
    });
});
