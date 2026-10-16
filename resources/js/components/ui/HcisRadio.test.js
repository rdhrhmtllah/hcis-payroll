import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisRadio from './HcisRadio.vue';

describe('HcisRadio.vue', () => {
    const options = [
        { value: 'opsi1', label: 'Opsi Pertama' },
        { value: 'opsi2', label: 'Opsi Kedua' },
    ];

    it('renders options array and emits update:modelValue on selection', async () => {
        const wrapper = mount(HcisRadio, {
            props: {
                modelValue: 'opsi1',
                options,
                name: 'test-radio',
            },
        });

        const inputs = wrapper.findAll('input[type="radio"]');
        expect(inputs.length).toBe(2);
        expect(inputs[0].element.checked).toBe(true);

        await inputs[1].setValue(true);
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['opsi2']);
    });

    it('supports standalone single radio mode', async () => {
        const wrapper = mount(HcisRadio, {
            props: {
                modelValue: 'opsi-single',
                value: 'opsi-single',
                label: 'Pilihan Standalone',
                name: 'single-radio',
            },
        });

        expect(wrapper.text()).toContain('Pilihan Standalone');
        expect(wrapper.find('input[type="radio"]').element.checked).toBe(true);
    });

    it('renders option icons with their own color while retaining radio semantics', () => {
        const wrapper = mount(HcisRadio, {
            props: {
                modelValue: 'red',
                options: [
                    { value: 'red', label: 'Permit Merah', icon: 'shield-fill-exclamation', iconColor: '#dc2626' },
                    { value: 'green', label: 'Permit Hijau', icon: 'shield-fill-check', iconColor: '#16a34a' },
                ],
                variant: 'card',
                direction: 'horizontal',
            },
        });

        expect(wrapper.findAll('.hcis-choice__visual')).toHaveLength(2);
        expect(wrapper.find('.hcis-choice__visual .hcis-icon').attributes('style')).toContain('color');
        expect(wrapper.findAll('input[type="radio"]')).toHaveLength(2);
    });
});
