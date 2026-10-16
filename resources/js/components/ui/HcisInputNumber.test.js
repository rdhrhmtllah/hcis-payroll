import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisInputNumber from './HcisInputNumber.vue';

describe('HcisInputNumber.vue', () => {
    it('renders initial modelValue correctly', async () => {
        const wrapper = mount(HcisInputNumber, {
            props: {
                modelValue: 5,
                min: 1,
                max: 10,
            },
        });

        expect(wrapper.find('input').element.value).toBe('5');
    });

    it('increments value on plus button click', async () => {
        const wrapper = mount(HcisInputNumber, {
            props: {
                modelValue: 3,
                min: 1,
                max: 10,
                step: 1,
            },
        });

        await wrapper.find('.hcis-input-number__btn--increase').trigger('click');
        expect(wrapper.emitted('update:modelValue')[0]).toEqual([4]);
    });

    it('decrements value on minus button click', async () => {
        const wrapper = mount(HcisInputNumber, {
            props: {
                modelValue: 5,
                min: 1,
                max: 10,
                step: 1,
            },
        });

        await wrapper.find('.hcis-input-number__btn--decrease').trigger('click');
        expect(wrapper.emitted('update:modelValue')[0]).toEqual([4]);
    });

    it('disables decrease button when at min value', async () => {
        const wrapper = mount(HcisInputNumber, {
            props: {
                modelValue: 1,
                min: 1,
                max: 10,
            },
        });

        const decreaseBtn = wrapper.find('.hcis-input-number__btn--decrease');
        expect(decreaseBtn.attributes('disabled')).toBeDefined();
    });
});
