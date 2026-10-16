import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HcisTransfer from './HcisTransfer.vue';

describe('HcisTransfer.vue', () => {
    const mockData = [
        { key: '1', label: 'Hak Aksa Read' },
        { key: '2', label: 'Hak Akses Write' },
        { key: '3', label: 'Hak Akses Delete' },
    ];

    it('renders dual transfer panels with source and target items', () => {
        const wrapper = mount(HcisTransfer, {
            props: { modelValue: ['3'], data: mockData },
        });

        expect(wrapper.findAll('.hcis-transfer-panel').length).toBe(2);
        expect(wrapper.text()).toContain('Daftar Tersedia');
        expect(wrapper.text()).toContain('Daftar Terpilih');
    });
});
