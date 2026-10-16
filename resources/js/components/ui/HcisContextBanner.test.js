import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisContextBanner from './HcisContextBanner.vue';

describe('HcisContextBanner.vue', () => {
    it('renders title, code, role label, and capabilities chips', async () => {
        const wrapper = mount(HcisContextBanner, {
            props: {
                title: 'Grup Akuntansi',
                code: 'FIN-GRP',
                roleLabel: 'System Admin',
                capabilities: [
                    { id: 'menu', label: 'Kelola Menu', icon: 'list-check' },
                    { id: 'approval', label: 'Kelola Approval', icon: 'diagram-3' },
                ],
            },
        });

        expect(wrapper.text()).toContain('Grup Akuntansi');
        expect(wrapper.text()).toContain('FIN-GRP');
        expect(wrapper.text()).toContain('System Admin');
        expect(wrapper.text()).toContain('Kelola Menu');
        expect(wrapper.text()).toContain('Kelola Approval');
    });
});
