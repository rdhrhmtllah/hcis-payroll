import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HcisCascader from './HcisCascader.vue';

describe('HcisCascader.vue', () => {
    const mockOptions = [
        {
            value: 'hc',
            label: 'Human Capital',
            children: [
                { value: 'rec', label: 'Recruitment' },
                { value: 'pay', label: 'Payroll' },
            ],
        },
        {
            value: 'it',
            label: 'Information Technology',
            children: [{ value: 'dev', label: 'Software Engineering' }],
        },
    ];

    it('renders trigger shell displaying selected path label', () => {
        const wrapper = mount(HcisCascader, {
            props: { modelValue: ['hc', 'rec'], options: mockOptions },
        });

        expect(wrapper.find('.hcis-cascader__trigger').exists()).toBe(true);
        expect(wrapper.text()).toContain('Human Capital / Recruitment');
    });

    it('opens cascading popover columns when trigger is clicked', async () => {
        const wrapper = mount(HcisCascader, {
            props: { modelValue: [], options: mockOptions, teleportTo: false },
        });

        expect(wrapper.find('.hcis-cascader__popover').exists()).toBe(false);
        await wrapper.find('.hcis-cascader__trigger').trigger('click');
        expect(wrapper.find('.hcis-cascader__popover').exists()).toBe(true);
        expect(wrapper.findAll('.hcis-cascader-col').length).toBe(1);
    });
});
