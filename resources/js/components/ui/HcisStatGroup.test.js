import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HcisStatGroup from './HcisStatGroup.vue';

describe('HcisStatGroup.vue', () => {
    it('renders list of stat cards correctly', () => {
        const stats = [
            { label: 'Total Kuota Cuti', value: '12 Hari', icon: 'calendar-check' },
            { label: 'Kuota Terpakai', value: '4 Hari', icon: 'clock-history', variant: 'warning' },
        ];

        const wrapper = mount(HcisStatGroup, {
            props: { stats, cols: 2 },
        });

        expect(wrapper.classes()).toContain('hcis-stat-group--cols-2');
        expect(wrapper.findAll('.hcis-stat-group__item').length).toBe(2);
        expect(wrapper.text()).toContain('Total Kuota Cuti');
        expect(wrapper.text()).toContain('12 Hari');
    });

    it('emits item-click event when clickable card is clicked', async () => {
        const stats = [{ label: 'Pending Approval', value: 5, clickable: true }];
        const wrapper = mount(HcisStatGroup, { props: { stats } });

        await wrapper.find('.hcis-stat-group__item').trigger('click');
        expect(wrapper.emitted('item-click')).toBeTruthy();
        expect(wrapper.emitted('item-click')[0][0].index).toBe(0);
    });
});
