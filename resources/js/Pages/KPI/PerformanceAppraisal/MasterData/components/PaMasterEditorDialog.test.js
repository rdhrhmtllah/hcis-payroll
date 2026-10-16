import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Editor from './PaMasterEditorDialog.vue';

const stubs = {
    HcisDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /><slot name="footer" /></div>' },
    HcisButton: { props: ['label'], template: '<button>{{ label }}</button>' },
    HcisIcon: { template: '<i />' },
    HcisResultState: { template: '<div />' },
    HcisField: { props: ['modelValue', 'label'], template: '<input />' },
    HcisTextarea: {
        props: ['modelValue', 'label', 'hint'],
        emits: ['update:modelValue'],
        template: '<label>{{ label }}<small>{{ hint }}</small><textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
    },
};

const fields = [
    { key: 'Deskripsi', payload: 'deskripsi', label: 'Deskripsi', type: 'textarea' },
    { key: 'Expected_Behaviour', payload: 'expected_behaviour', label: 'Expected Behaviour', type: 'textarea', preview: 'perilaku', hint: 'Satu perilaku per baris' },
];

describe('PaMasterEditorDialog — expected behaviour', () => {
    it('pratinjau butir muncul saat diisi, dan hanya untuk field perilaku', async () => {
        const w = mount(Editor, { props: { modelValue: true, fields, record: { Deskripsi: 'Arti', Expected_Behaviour: '' } }, global: { stubs } });
        expect(w.find('[data-uji="daftar-perilaku"]').exists()).toBe(false);
        expect(w.text()).toContain('Satu perilaku per baris');

        await w.findAll('textarea')[1].setValue('- Memeriksa data\n- Mencari akar masalah');
        const butir = w.findAll('[data-uji="daftar-perilaku"] li').map((l) => l.text());
        expect(butir).toEqual(['Memeriksa data', 'Mencari akar masalah']);
        expect(w.findAll('[data-uji="daftar-perilaku"]')).toHaveLength(1);
    });
});
