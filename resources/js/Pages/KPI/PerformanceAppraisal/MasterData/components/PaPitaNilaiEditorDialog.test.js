import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PaPitaNilaiEditorDialog from './PaPitaNilaiEditorDialog.vue';

const uiStubs = {
    HcisDialog: {
        props: ['modelValue', 'title'],
        template: '<div v-if="modelValue" class="dialog-stub"><h2>{{ title }}</h2><slot /><slot name="footer" /></div>',
    },
    HcisBadge: {
        props: ['label'],
        template: '<span class="badge-stub">{{ label }}</span>',
    },
    HcisButton: {
        props: ['label', 'disabled', 'loading'],
        template: '<button :disabled="disabled">{{ label }}</button>',
    },
    HcisField: {
        props: ['modelValue', 'label'],
        emits: ['update:modelValue'],
        template: '<label><span>{{ label }}</span><input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
    },
    HcisIcon: { template: '<i />' },
    HcisResultState: {
        props: ['title', 'description'],
        template: '<div class="result-state"><strong>{{ title }}</strong><span>{{ description }}</span></div>',
    },
};

const references = {
    sumbu: [
        { Kode_Type: 'FINAL', Nama_Type: 'Final' },
        { Kode_Type: 'KPI', Nama_Type: 'KPI' },
        { Kode_Type: 'KOMPETENSI', Nama_Type: 'Kompetensi' },
    ],
    kegunaan: [
        { Kode_Usage: 'GRADE', Nama_Usage: 'Grade' },
        { Kode_Usage: 'HAVMAP', Nama_Usage: 'HAV Map' },
    ],
};

describe('PaPitaNilaiEditorDialog', () => {
    it('renders and allows setting open-ended 999.99 upper bound with single click', async () => {
        const wrapper = mount(PaPitaNilaiEditorDialog, {
            props: {
                modelValue: true,
                references,
                existingRows: [],
                defaultType: 'FINAL',
                defaultUsage: 'GRADE',
            },
            global: { stubs: uiStubs },
        });

        expect(wrapper.find('h2').text()).toContain('Tambah Rentang Nilai Baru');
        const maxToggle = wrapper.find('.pa-range-max-toggle');
        expect(maxToggle.exists()).toBe(true);
        await maxToggle.trigger('click');

        const sampaiInput = wrapper.find('#pa-range-nilai-sampai');
        expect(Number(sampaiInput.element.value)).toBe(999.99);
        expect(wrapper.text()).toContain('Terbuka ke atas hingga nilai tertinggi');
    });

    it('emits save event with sanitized uppercase code and numeric boundaries', async () => {
        const wrapper = mount(PaPitaNilaiEditorDialog, {
            props: {
                modelValue: true,
                references,
                existingRows: [],
                defaultType: 'FINAL',
                defaultUsage: 'GRADE',
            },
            global: { stubs: uiStubs },
        });

        // Set Kode and Nama
        const kodeInput = wrapper.find('#pa-range-code input');
        await kodeInput.setValue('a_plus');

        const namaInput = wrapper.find('#pa-range-name input');
        await namaInput.setValue('Sangat Istimewa');

        const dariInput = wrapper.find('#pa-range-nilai-dari');
        await dariInput.setValue(95);

        const sampaiInput = wrapper.find('#pa-range-nilai-sampai');
        await sampaiInput.setValue(999.99);

        // Click color swatch button
        const swatches = wrapper.findAll('.pa-color-swatch-btn');
        if (swatches.length > 0) {
            await swatches[0].trigger('click');
        }

        const saveButton = wrapper.findAll('button').find((b) => b.text().includes('Simpan Rentang Nilai'));
        expect(saveButton).toBeTruthy();
        await saveButton.trigger('click');

        expect(wrapper.emitted('save')).toBeTruthy();
        const payload = wrapper.emitted('save')[0][0];
        expect(payload.kode_range).toBe('A_PLUS');
        expect(payload.nama_range).toBe('Sangat Istimewa');
        expect(payload.nilai_dari).toBe(95);
        expect(payload.nilai_sampai).toBe(999.99);
        /* Editor kini hanya untuk HAV Map: tipe FINAL tidak ditawarkan, kegunaan terkunci HAVMAP. */
        expect(payload.sumbu).toBe('KPI');
        expect(payload.kegunaan).toBe('HAVMAP');
    });
});
