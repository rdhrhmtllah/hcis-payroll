import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PaAturanPenilaiEditorDialog from './PaAturanPenilaiEditorDialog.vue';

const uiStubs = {
    HcisDialog: {
        props: ['modelValue', 'title'],
        template: '<div v-if="modelValue" class="dialog-stub"><h2>{{ title }}</h2><slot /><slot name="footer" /></div>',
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
    HcisBadge: {
        props: ['label'],
        template: '<span class="badge">{{ label }}</span>',
    },
    HcisIcon: { template: '<i />' },
    HcisResultState: {
        props: ['title', 'description'],
        template: '<div class="result-state"><strong>{{ title }}</strong><span>{{ description }}</span></div>',
    },
    HcisTextarea: {
        props: ['modelValue', 'label'],
        emits: ['update:modelValue'],
        template: '<label><span>{{ label }}</span><textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
    },
};

const references = {
    level: [
        { ID_Level: 1, Nama_Level: 'STAFF', Level_Hierarchy: 1 },
        { ID_Level: 2, Nama_Level: 'SUPERVISOR', Level_Hierarchy: 2 },
    ],
    kategori_penilai: [
        { Id_PA_Rater_Category: 1, Kode_Kategori: 'ATASAN', Nama_Kategori: 'Atasan Langsung' },
        { Id_PA_Rater_Category: 2, Kode_Kategori: 'PEER', Nama_Kategori: 'Rekan Kerja' },
    ],
};

describe('PaAturanPenilaiEditorDialog', () => {
    it('initializes and auto-formats policy code and name when level is selected', async () => {
        const wrapper = mount(PaAturanPenilaiEditorDialog, {
            props: {
                modelValue: true,
                references,
            },
            global: { stubs: uiStubs },
        });

        expect(wrapper.find('h2').text()).toContain('Buat Komposisi Penilai Baru');

        // Select Level 1
        const levelSelect = wrapper.find('#pa-policy-level');
        await levelSelect.setValue('1');

        expect(wrapper.text()).toContain('Tingkat 1');
    });

    it('emits save event with sanitized uppercase code and rincian on submit', async () => {
        const wrapper = mount(PaAturanPenilaiEditorDialog, {
            props: {
                modelValue: true,
                references,
            },
            global: { stubs: uiStubs },
        });

        // Set Level
        const levelSelect = wrapper.find('#pa-policy-level');
        await levelSelect.setValue('1');

        // Set Code
        const codeInput = wrapper.find('#pa-policy-code input');
        await codeInput.setValue('pol_staff_2026');

        // Set Name
        const nameInput = wrapper.find('#pa-policy-name input');
        await nameInput.setValue('Aturan Penilai Staff 2026');

        const saveDraftButton = wrapper.findAll('button').find((b) => b.text().includes('Simpan sebagai DRAFT'));
        expect(saveDraftButton).toBeTruthy();
        await saveDraftButton.trigger('click');

        expect(wrapper.emitted('save')).toBeTruthy();
        const payload = wrapper.emitted('save')[0][0];
        expect(payload.id_level).toBe(1);
        expect(payload.kode_policy).toBe('POL_STAFF_2026');
        expect(payload.nama_policy).toBe('Aturan Penilai Staff 2026');
        expect(payload.rincian).toBeInstanceOf(Array);
    });
});
