import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisPipelineVisualizer from './HcisPipelineVisualizer.vue';

describe('HcisPipelineVisualizer.vue', () => {
    const steps = [
        { order: 1, label: 'Atasan Langsung', mode: 'Direct Manager', editable: true },
        { order: 2, label: 'HR Admin', mode: 'Role Based', mandatory: true },
    ];

    it('renders step nodes, numbers, and badges correctly', async () => {
        const wrapper = mount(HcisPipelineVisualizer, {
            props: {
                title: 'Alur Approval Cuti',
                steps,
            },
        });

        expect(wrapper.text()).toContain('Alur Approval Cuti');
        expect(wrapper.text()).toContain('Atasan Langsung');
        expect(wrapper.text()).toContain('HR Admin');
        expect(wrapper.text()).toContain('Editable');
        expect(wrapper.text()).toContain('Wajib');
    });
});
