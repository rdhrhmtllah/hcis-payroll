import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisOrgChart from './HcisOrgChart.vue';

describe('HcisOrgChart.vue', () => {
    const mockTreeData = {
        id: 'ceo',
        name: 'Hendra Wijaya',
        title: 'Direktur Utama',
        department: 'Direksi Utama',
        children: [
            {
                id: 'dir-hc',
                name: 'Budi Santoso',
                title: 'Direktur HC',
                department: 'Direktorat HC',
                children: [
                    {
                        id: 'mgr-hcis',
                        name: 'Ahmad Fauzi',
                        title: 'Manager HCIS',
                        department: 'Departemen HCIS',
                    }
                ]
            }
        ]
    };

    it('renders root node and children nodes correctly', () => {
        const wrapper = mount(HcisOrgChart, {
            props: {
                title: 'Struktur Organisasi PT HCIS',
                data: mockTreeData,
            }
        });

        expect(wrapper.text()).toContain('Struktur Organisasi PT HCIS');
        expect(wrapper.text()).toContain('Hendra Wijaya');
        expect(wrapper.text()).toContain('Direktur Utama');
        expect(wrapper.text()).toContain('Budi Santoso');
        expect(wrapper.text()).toContain('Ahmad Fauzi');
    });

    it('emits node-click event when a node is clicked', async () => {
        const wrapper = mount(HcisOrgChart, {
            props: {
                data: mockTreeData,
            }
        });

        const rootNode = wrapper.find('.hcis-org-node.is-root');
        expect(rootNode.exists()).toBe(true);
        await rootNode.trigger('click');

        expect(wrapper.emitted('node-click')).toBeTruthy();
        expect(wrapper.emitted('node-click')[0][0].id).toBe('ceo');
    });

    it('toggles collapse branch when toggle button is clicked', async () => {
        const wrapper = mount(HcisOrgChart, {
            props: {
                data: mockTreeData,
            }
        });

        const toggleBtn = wrapper.find('.hcis-org-node__toggle-btn');
        expect(toggleBtn.exists()).toBe(true);

        await toggleBtn.trigger('click');
        expect(wrapper.text()).not.toContain('Ahmad Fauzi');
    });
});
