import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisBreadcrumb from './HcisBreadcrumb.vue';

describe('HcisBreadcrumb.vue', () => {
    const items = [
        { label: 'Modul DMS', href: '/sop', icon: 'journal-text' },
        { label: 'Penjadwalan', href: '/sop/penjadwalan' },
        { label: 'Formulir Jadwal Baris' },
    ];

    it('renders breadcrumb items and last item as active page', async () => {
        const wrapper = mount(HcisBreadcrumb, {
            props: { items },
        });

        const links = wrapper.findAll('.hcis-breadcrumb__link');
        expect(links.length).toBe(2);
        expect(links[0].text()).toContain('Modul DMS');
        expect(wrapper.find('.hcis-breadcrumb__current').text()).toBe('Formulir Jadwal Baris');
    });

    it('supports showHome prop and chevron separator', async () => {
        const wrapper = mount(HcisBreadcrumb, {
            props: {
                items,
                showHome: true,
                separator: 'chevron',
            },
        });

        expect(wrapper.find('.hcis-breadcrumb__link--home').exists()).toBe(true);
        expect(wrapper.find('.hcis-breadcrumb__sep-icon').exists()).toBe(true);
    });

    it('truncates items when maxItems is set', async () => {
        const longItems = [
            { label: 'Level 1', href: '#' },
            { label: 'Level 2', href: '#' },
            { label: 'Level 3', href: '#' },
            { label: 'Level 4', href: '#' },
            { label: 'Level 5' },
        ];
        const wrapper = mount(HcisBreadcrumb, {
            props: {
                items: longItems,
                maxItems: 3,
            },
        });

        expect(wrapper.find('.hcis-breadcrumb__ellipsis').text()).toBe('...');
    });
});
