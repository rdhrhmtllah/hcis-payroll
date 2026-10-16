import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisTimePicker from './HcisTimePicker.vue';

describe('HcisTimePicker.vue', () => {
    it('renders displayValue when modelValue is provided', async () => {
        const wrapper = mount(HcisTimePicker, {
            props: {
                modelValue: '08:30',
                teleportTo: false,
            },
        });

        expect(wrapper.find('input').element.value).toBe('08:30');
    });

    it('opens popover when trigger is clicked', async () => {
        const wrapper = mount(HcisTimePicker, {
            props: {
                modelValue: '09:00',
                teleportTo: false,
            },
        });

        expect(wrapper.find('.hcis-time-picker__popover').exists()).toBe(false);
        await wrapper.find('.hcis-time-picker__trigger').trigger('click');
        expect(wrapper.find('.hcis-time-picker__popover').exists()).toBe(true);
    });

    it('emits update:modelValue when a preset chip is clicked', async () => {
        const wrapper = mount(HcisTimePicker, {
            props: {
                modelValue: '08:00',
                teleportTo: false,
            },
        });

        await wrapper.find('.hcis-time-picker__trigger').trigger('click');
        // Chip preset kini memakai kelas bersama dari HcisDatePicker.
        const presetChip = wrapper.findAll('.hcis-date-picker__chip')[2]; // 12:00 (Istirahat)
        await presetChip.trigger('click');

        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['12:00']);
    });
});
