import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PaCycleSetupDialog from './PaCycleSetupDialog.vue';

const uiStubs = {
    HcisButton: {
        props: ['label', 'disabled', 'loading'],
        emits: ['click'],
        template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>',
    },
    HcisDatePicker: { props: ['modelValue', 'disabled'], template: '<input :disabled="disabled" :value="modelValue" />' },
    HcisDialog: {
        props: ['modelValue'],
        template: '<div v-if="modelValue"><slot /><slot name="footer" /></div>',
    },
    HcisField: {
        props: ['id', 'label', 'modelValue', 'disabled'],
        emits: ['update:modelValue'],
        template: '<label :data-field="id"><span>{{ label }}</span><input :disabled="disabled" :value="modelValue" /><slot /></label>',
    },
    HcisIcon: { template: '<i />' },
    HcisResultState: { props: ['title', 'description'], template: '<div>{{ title }} {{ description }}</div>' },
    HcisStatusBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
    HcisTextarea: { props: ['modelValue', 'label'], template: '<textarea :aria-label="label">{{ modelValue }}</textarea>' },
};

describe('PaCycleSetupDialog', () => {
    it('menampilkan alasan periode terkunci dari server dan tetap menyediakan informasi edit metadata', () => {
        const wrapper = mount(PaCycleSetupDialog, {
            props: {
                modelValue: true,
                mode: 'edit',
                cycle: { Kode_Cycle: 'PA-DEMO-FE', Nama_Cycle: 'Siklus Contoh', Status_Cycle: 'CLOSED' },
                detail: {
                    siklus: { Kode_Cycle: 'PA-DEMO-FE', Nama_Cycle: 'Siklus Contoh', Tahun: 2026, Status_Cycle: 'CLOSED' },
                    boleh_ubah_periode: false,
                    alasan_periode_terkunci: 'Siklus berstatus CLOSED. Periode tidak dapat digeser.',
                    peserta: { total: 958 },
                },
                form: {
                    kode_cycle: 'PA-DEMO-FE',
                    nama_cycle: 'Siklus Contoh',
                    tahun: '2026',
                    deskripsi: '',
                    periode_dinilai_dari: '2026-01-01',
                    periode_dinilai_sampai: '2026-06-30',
                },
            },
            global: { stubs: uiStubs },
        });

        expect(wrapper.text()).toContain('Siklus berstatus CLOSED. Periode tidak dapat digeser.');
        expect(wrapper.text()).toContain('Nama dan deskripsi tetap dapat disunting sesuai kontrak.');
        expect(wrapper.find('[data-field="pa-cycle-tahun"] input').attributes('disabled')).toBeDefined();
    });

    it('mengeluarkan draft saat aksi simpan ditekan', async () => {
        const wrapper = mount(PaCycleSetupDialog, {
            props: {
                modelValue: true,
                mode: 'create',
                form: { kode_cycle: 'PA-2026', nama_cycle: 'Siklus Baru', tahun: '2026' },
            },
            global: { stubs: uiStubs },
        });

        const buttons = wrapper.findAll('button');
        await buttons[buttons.length - 1].trigger('click');

        expect(wrapper.emitted('save')).toHaveLength(1);
        expect(wrapper.emitted('save')[0][0]).toMatchObject({ kode_cycle: 'PA-2026', nama_cycle: 'Siklus Baru', tahun: '2026' });
    });

    it('periode dinilai memakai month picker dan otomatis menetapkan awal & akhir bulan', async () => {
        const customStubs = {
            ...uiStubs,
            HcisDatePicker: {
                props: ['modelValue', 'disabled', 'type'],
                emits: ['update:modelValue'],
                template: '<input :disabled="disabled" :data-type="type" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
            },
        };

        const wrapper = mount(PaCycleSetupDialog, {
            props: {
                modelValue: true,
                mode: 'create',
                form: {
                    kode_cycle: 'PA-2026',
                    nama_cycle: 'Siklus Baru',
                    tahun: '2026',
                    periode_dinilai_dari: '2026-01-01',
                    periode_dinilai_sampai: '2026-06-30',
                },
            },
            global: { stubs: customStubs },
        });

        // Verifikasi input periode dinilai bertipe month dan membawa nilai YYYY-MM
        const startField = wrapper.find('[data-field="pa-cycle-period-start"] input[data-type]');
        const endField = wrapper.find('[data-field="pa-cycle-period-end"] input[data-type]');

        expect(startField.attributes('data-type')).toBe('month');
        expect(startField.element.value).toBe('2026-01');
        expect(endField.attributes('data-type')).toBe('month');
        expect(endField.element.value).toBe('2026-06');
        expect(wrapper.text()).toContain('Rentang evaluasi KPI efektif: 1 Jan 2026 s/d 30 Jun 2026');

        // Ubah bulan periode dinilai dari ke Februari 2026 (harus menghasilkan 2026-02-01)
        await startField.setValue('2026-02');
        await startField.trigger('input');

        // Ubah bulan periode dinilai sampai ke Februari 2026 (harus menghasilkan 2026-02-28)
        await endField.setValue('2026-02');
        await endField.trigger('input');

        // Klik simpan dan pastikan tanggal awal dan akhir bulan yang terkirim
        const buttons = wrapper.findAll('button');
        await buttons[buttons.length - 1].trigger('click');

        expect(wrapper.emitted('save')).toBeTruthy();
        const savedDraft = wrapper.emitted('save')[0][0];
        expect(savedDraft.periode_dinilai_dari).toBe('2026-02-01');
        expect(savedDraft.periode_dinilai_sampai).toBe('2026-02-28');
    });

    it('menghitung tanggal akhir bulan kabisat secara akurat (29 Feb untuk tahun kabisat)', async () => {
        const customStubs = {
            ...uiStubs,
            HcisDatePicker: {
                props: ['modelValue', 'disabled', 'type'],
                emits: ['update:modelValue'],
                template: '<input :disabled="disabled" :data-type="type" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
            },
        };

        const wrapper = mount(PaCycleSetupDialog, {
            props: {
                modelValue: true,
                mode: 'create',
                form: {
                    nama_cycle: 'Siklus 2024',
                },
            },
            global: { stubs: customStubs },
        });

        const startField = wrapper.find('[data-field="pa-cycle-period-start"] input[data-type]');
        const endField = wrapper.find('[data-field="pa-cycle-period-end"] input[data-type]');

        await startField.setValue('2024-02');
        await startField.trigger('input');

        await endField.setValue('2024-02');
        await endField.trigger('input');

        const buttons = wrapper.findAll('button');
        await buttons[buttons.length - 1].trigger('click');

        const savedDraft = wrapper.emitted('save')[0][0];
        expect(savedDraft.periode_dinilai_dari).toBe('2024-02-01');
        expect(savedDraft.periode_dinilai_sampai).toBe('2024-02-29');
    });
});
