import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisStatusBadge from './HcisStatusBadge.vue';

describe('HcisStatusBadge.vue', () => {
    it('automatically maps APPROVED status to success variant and check icon', () => {
        const wrapper = mount(HcisStatusBadge, {
            props: { status: 'APPROVED' }
        });

        expect(wrapper.text()).toContain('Disetujui');
        expect(wrapper.classes()).toContain('hcis-badge--success');
        expect(wrapper.find('.hcis-badge__icon').exists()).toBe(true);
    });

    it('automatically maps PENDING_MANAGER status to a calm warning variant', () => {
        const wrapper = mount(HcisStatusBadge, {
            props: { status: 'PENDING_MANAGER' }
        });

        expect(wrapper.text()).toContain('Menunggu Manajer');
        expect(wrapper.classes()).toContain('hcis-badge--warning');
        // Tenang sejak 15 Sep 2026 (520dba35); denyut khusus status berlangsung.
        expect(wrapper.find('.hcis-badge__dot-pulse').exists()).toBe(false);
    });

    it('meneruskan denyut status berlangsung ke HcisBadge', () => {
        const wrapper = mount(HcisStatusBadge, {
            props: { status: 'CHECKED_IN' }
        });

        expect(wrapper.find('.hcis-badge__dot-pulse').exists()).toBe(true);
    });

    it('allows label and variant override', () => {
        const wrapper = mount(HcisStatusBadge, {
            props: {
                status: 'REJECTED',
                label: 'Permohonan Dibatalkan Karyawan',
                variant: 'neutral',
            }
        });

        expect(wrapper.text()).toContain('Permohonan Dibatalkan Karyawan');
        expect(wrapper.classes()).toContain('hcis-badge--neutral');
    });
});
