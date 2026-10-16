import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PaBobotPilarEditorDialog from './PaBobotPilarEditorDialog.vue';

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
};

describe('PaBobotPilarEditorDialog', () => {
    it('initializes default 80/20 weights and auto-calculates total to 100%', async () => {
        const wrapper = mount(PaBobotPilarEditorDialog, {
            props: {
                modelValue: true,
                references,
                existingRows: [],
            },
            global: { stubs: uiStubs },
        });

        expect(wrapper.find('h2').text()).toContain('Atur Bobot KPI & Kompetensi Level');
        const kpiInput = wrapper.find('#pa-input-bobot-kpi');
        const kompInput = wrapper.find('#pa-input-bobot-komp');
        expect(Number(kpiInput.element.value)).toBe(80);
        expect(Number(kompInput.element.value)).toBe(20);
        expect(wrapper.text()).toContain('Total 100% (Valid)');
    });

    it('syncs Kompetensi weight automatically when adjusting KPI weight via preset chip', async () => {
        const wrapper = mount(PaBobotPilarEditorDialog, {
            props: {
                modelValue: true,
                references,
                existingRows: [],
            },
            global: { stubs: uiStubs },
        });

        // Find the 60 / 40 preset chip
        const presetChips = wrapper.findAll('.pa-pillar-preset-chip');
        const chip6040 = presetChips.find((c) => c.text().includes('60 / 40'));
        expect(chip6040).toBeTruthy();
        await chip6040.trigger('click');

        const kpiInput = wrapper.find('#pa-input-bobot-kpi');
        const kompInput = wrapper.find('#pa-input-bobot-komp');
        expect(Number(kpiInput.element.value)).toBe(60);
        expect(Number(kompInput.element.value)).toBe(40);
        expect(wrapper.text()).toContain('Total 100% (Valid)');
    });

    it('emits save event with correct payload on valid submission', async () => {
        const wrapper = mount(PaBobotPilarEditorDialog, {
            props: {
                modelValue: true,
                references,
                existingRows: [],
            },
            global: { stubs: uiStubs },
        });

        // Select level 1
        const levelSelect = wrapper.find('#pa-pillar-level-select');
        await levelSelect.setValue('1');

        // Submit form
        const saveButton = wrapper.findAll('button').find((b) => b.text().includes('Simpan Bobot KPI'));
        expect(saveButton).toBeTruthy();
        await saveButton.trigger('click');

        expect(wrapper.emitted('save')).toBeTruthy();
        expect(wrapper.emitted('save')[0][0]).toEqual({
            id_level: 1,
            bobot_kpi: 80,
            bobot_kompetensi: 20,
            catatan: null,
        });
    });
});
