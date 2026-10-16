import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisButton from './HcisButton.vue';

describe('HcisButton.vue', () => {
    it('renders label, leadingIcon, and trailingIcon correctly', async () => {
        const wrapper = mount(HcisButton, {
            props: {
                label: 'Lanjutkan',
                leadingIcon: 'plus-lg',
                trailingIcon: 'arrow-right',
                variant: 'primary',
            },
        });

        expect(wrapper.text()).toContain('Lanjutkan');
        expect(wrapper.find('.hcis-btn__icon--leading').exists()).toBe(true);
        expect(wrapper.find('.hcis-btn__icon--trailing').exists()).toBe(true);
        expect(wrapper.classes()).toContain('hcis-btn--primary');
    });

    it('handles loading state and disables button', async () => {
        const wrapper = mount(HcisButton, {
            props: {
                label: 'Simpan',
                loading: true,
            },
        });

        expect(wrapper.find('.hcis-btn__spinner').exists()).toBe(true);
        expect(wrapper.attributes('disabled')).toBeDefined();
    });
});
