import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisIcon from './HcisIcon.vue';

describe('HcisIcon.vue', () => {
    it('parses bootstrap icon names correctly', async () => {
        const wrapper1 = mount(HcisIcon, { props: { name: 'pencil' } });
        expect(wrapper1.classes()).toContain('bi-pencil');
        expect(wrapper1.classes()).toContain('bi');

        const wrapper2 = mount(HcisIcon, { props: { name: 'bi-search' } });
        expect(wrapper2.classes()).toContain('bi-search');

        const wrapper3 = mount(HcisIcon, { props: { name: 'bi bi-check-lg' } });
        expect(wrapper3.classes()).toContain('bi-check-lg');
    });

    it('supports custom hex colors and custom sizes', async () => {
        const wrapper = mount(HcisIcon, {
            props: {
                name: 'heart-fill',
                color: '#ec4899',
                size: '24px',
            },
        });

        expect(wrapper.attributes('style')).toContain('color: rgb(236, 72, 153)');
        expect(wrapper.attributes('style')).toContain('font-size: 24px');
    });

    it('applies boxed and circle variants', async () => {
        const wrapper = mount(HcisIcon, {
            props: {
                name: 'shield-check',
                color: 'success',
                variant: 'circle',
            },
        });

        expect(wrapper.classes()).toContain('hcis-icon--variant-circle');
        expect(wrapper.classes()).toContain('hcis-icon--color-success');
    });
});
