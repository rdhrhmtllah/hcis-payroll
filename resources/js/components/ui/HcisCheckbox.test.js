import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisCheckbox from './HcisCheckbox.vue';

describe('HcisCheckbox.vue', () => {
    it('toggles single boolean modelValue on click', async () => {
        const wrapper = mount(HcisCheckbox, {
            props: {
                modelValue: false,
                label: 'Setuju syarat & ketentuan',
            },
        });

        expect(wrapper.find('.hcis-choice__label').text()).toBe('Setuju syarat & ketentuan');

        await wrapper.find('input[type="checkbox"]').setValue(true);
        expect(wrapper.emitted('update:modelValue')[0]).toEqual([true]);
    });

    it('handles array modelValue for checkbox groups', async () => {
        const wrapper = mount(HcisCheckbox, {
            props: {
                modelValue: ['vue'],
                value: 'react',
                label: 'React JS',
            },
        });

        expect(wrapper.classes()).not.toContain('is-checked');

        await wrapper.find('input[type="checkbox"]').setValue(true);
        expect(wrapper.emitted('update:modelValue')[0]).toEqual([['vue', 'react']]);
    });

    it('renders indeterminate state correctly', async () => {
        const wrapper = mount(HcisCheckbox, {
            props: {
                modelValue: false,
                indeterminate: true,
            },
        });

        expect(wrapper.classes()).toContain('is-indeterminate');
    });
});
