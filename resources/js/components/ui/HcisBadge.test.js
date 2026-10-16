import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisBadge from './HcisBadge.vue';

describe('HcisBadge.vue', () => {
    it('renders basic manual label and variant', () => {
        const wrapper = mount(HcisBadge, {
            props: { label: 'Manual Tag', variant: 'primary' }
        });

        expect(wrapper.text()).toContain('Manual Tag');
        expect(wrapper.classes()).toContain('hcis-badge--primary');
    });

    it('automatically maps status prop to localized label, variant, and icon', () => {
        const wrapper = mount(HcisBadge, {
            props: { status: 'PENDING_MANAGER' }
        });

        expect(wrapper.text()).toContain('Menunggu Manajer');
        expect(wrapper.classes()).toContain('hcis-badge--warning');
        // Status menunggu sengaja TENANG sejak 15 Sep 2026 (520dba35): denyut
        // hanya untuk keadaan yang sedang berlangsung di lapangan.
        expect(wrapper.classes()).not.toContain('is-pulsing');
    });

    it('berdenyut hanya untuk status yang sedang berlangsung', () => {
        const wrapper = mount(HcisBadge, {
            props: { status: 'CHECKED_IN' }
        });

        expect(wrapper.text()).toContain('Sedang di Area');
        expect(wrapper.classes()).toContain('is-pulsing');
        expect(wrapper.find('.hcis-badge__dot-pulse').exists()).toBe(true);
    });

    it('prop pulse tetap bisa memaksa denyut pada status apa pun', () => {
        const wrapper = mount(HcisBadge, {
            props: { status: 'PENDING_MANAGER', pulse: true }
        });

        expect(wrapper.classes()).toContain('is-pulsing');
    });
});
