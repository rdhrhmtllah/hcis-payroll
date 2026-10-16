import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HcisPhoneInput from './HcisPhoneInput.vue';

describe('HcisPhoneInput.vue', () => {
    it('renders country prefix select and national number input inside one visual control', async () => {
        const wrapper = mount(HcisPhoneInput, {
            props: {
                id: 'guest-phone',
                label: 'Nomor HP atau telepon',
                modelValue: '+62 8123456789',
                teleportTo: false,
            },
        });

        expect(wrapper.find('.hcis-phone-input__control').exists()).toBe(true);
        expect(wrapper.find('.hcis-phone-input__prefix').exists()).toBe(true);
        expect(wrapper.find('input.hcis-phone-input__number').exists()).toBe(true);
        expect(wrapper.find('input.hcis-phone-input__number').element.value).toBe('8123456789');
    });

    it('strips leading 0 and sanitizes non-digit characters automatically', async () => {
        const wrapper = mount(HcisPhoneInput, {
            props: {
                id: 'guest-phone',
                modelValue: '',
                teleportTo: false,
            },
        });

        const input = wrapper.find('input.hcis-phone-input__number');
        await input.setValue('0812-3456-7890');

        expect(input.element.value).toBe('81234567890');
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['+62 81234567890']);
        expect(wrapper.emitted('update:nationalNumber')?.at(-1)).toEqual(['81234567890']);
    });

    it('enforces maximum digit length per country', async () => {
        const wrapper = mount(HcisPhoneInput, {
            props: {
                id: 'guest-phone',
                countryCode: '+62',
                maxDigits: 13,
                teleportTo: false,
            },
        });

        const input = wrapper.find('input.hcis-phone-input__number');
        // Type 15 digits (starting with 8)
        await input.setValue('8123456789012345');

        // Should be clamped to 13 digits
        expect(input.element.value).toBe('8123456789012');
        expect(wrapper.find('.hcis-phone-input__counter').text()).toContain('13/13');
    });
});
