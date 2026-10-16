import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisSwitch from './HcisSwitch.vue';

describe('HcisSwitch.vue', () => {
    it('toggles value on click', async () => {
        const wrapper = mount(HcisSwitch, {
            props: {
                modelValue: false,
            },
        });

        await wrapper.find('.hcis-switch__core').trigger('click');
        expect(wrapper.emitted('update:modelValue')[0]).toEqual([true]);
    });

    it('supports custom activeValue and inactiveValue', async () => {
        const wrapper = mount(HcisSwitch, {
            props: {
                modelValue: 'N',
                activeValue: 'Y',
                inactiveValue: 'N',
            },
        });

        await wrapper.find('.hcis-switch__core').trigger('click');
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['Y']);
    });

    it('does not toggle when disabled', async () => {
        const wrapper = mount(HcisSwitch, {
            props: {
                modelValue: false,
                disabled: true,
            },
        });

        await wrapper.find('.hcis-switch__core').trigger('click');
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });
});
