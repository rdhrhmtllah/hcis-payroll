import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HcisPlateInput from './HcisPlateInput.vue';

describe('HcisPlateInput.vue', () => {
    it('keeps prefix, number, and suffix inside one visual control', async () => {
        const wrapper = mount(HcisPlateInput, {
            props: {
                id: 'plate-number',
                label: 'Nomor plat kendaraan',
                modelValue: 'B1234XYZ',
                prefixOptions: ['B', 'D'],
                teleportTo: false,
            },
        });

        expect(wrapper.find('.hcis-plate-input__control').exists()).toBe(true);
        expect(wrapper.findAll('input.hcis-plate-input__segment')).toHaveLength(2);
        expect(wrapper.find('.hcis-plate-input__number').element.value).toBe('1234');
        expect(wrapper.find('.hcis-plate-input__suffix').element.value).toBe('XYZ');

        await wrapper.find('.hcis-plate-input__number').setValue('5678');
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['B 5678 XYZ']);

        await wrapper.find('.hcis-select__trigger').trigger('click');
        await wrapper.findAll('.hcis-select-option')[1].trigger('click');
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['D 5678 XYZ']);
    });

    it('normalizes compact plate values and exposes legacy parts', () => {
        const wrapper = mount(HcisPlateInput, {
            props: {
                id: 'plate-number',
                modelValue: 'AB 12 CD',
                prefixOptions: ['AB'],
            },
        });

        expect(wrapper.find('.hcis-plate-input__number').element.value).toBe('12');
        expect(wrapper.find('.hcis-plate-input__suffix').element.value).toBe('CD');
    });
});
