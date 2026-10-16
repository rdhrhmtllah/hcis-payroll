import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HcisContainer from './HcisContainer.vue';

describe('HcisContainer.vue', () => {
    it('renders default container card with content slot', () => {
        const wrapper = mount(HcisContainer, {
            slots: {
                default: '<div class="test-content">Container Inner Body</div>',
            },
        });

        expect(wrapper.classes()).toContain('hcis-container');
        expect(wrapper.classes()).toContain('hcis-container--card');
        expect(wrapper.find('.test-content').text()).toBe('Container Inner Body');
    });

    it('renders title, subtitle, and badge in header when provided', () => {
        const wrapper = mount(HcisContainer, {
            props: {
                title: 'Data Karyawan',
                subtitle: 'Manajemen informasi profil dan struktur organisasi',
                badge: 'Aktif',
                badgeVariant: 'success',
            },
        });

        expect(wrapper.find('.hcis-container__title').text()).toBe('Data Karyawan');
        expect(wrapper.find('.hcis-container__subtitle').text()).toBe('Manajemen informasi profil dan struktur organisasi');
        expect(wrapper.find('.hcis-container__header').exists()).toBe(true);
    });

    it('applies variant and size classes correctly', () => {
        const wrapper = mount(HcisContainer, {
            props: {
                variant: 'glass',
                size: '2xl',
                fluid: true,
            },
        });

        expect(wrapper.classes()).toContain('hcis-container--glass');
        expect(wrapper.classes()).toContain('hcis-container--2xl');
        expect(wrapper.classes()).toContain('hcis-container--fluid');
    });
});
