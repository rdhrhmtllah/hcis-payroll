import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisProgress from './HcisProgress.vue';

describe('HcisProgress.vue', () => {
    it('renders line mode progress correctly and bounds value between 0 and 100', async () => {
        const wrapper = mount(HcisProgress, {
            props: {
                value: 75,
                label: 'Kemajuan Pelatihan',
            },
        });

        expect(wrapper.find('.hcis-progress__label').text()).toBe('Kemajuan Pelatihan');
        expect(wrapper.find('.hcis-progress__value').text()).toContain('75%');
        expect(wrapper.find('.hcis-progress__fill').attributes('style')).toContain('width: 75%');
    });

    it('renders circle mode progress with SVG stroke offset', async () => {
        const wrapper = mount(HcisProgress, {
            props: {
                type: 'circle',
                value: 50,
                sentiment: 'good',
            },
        });

        expect(wrapper.find('.hcis-progress-circle').exists()).toBe(true);
        expect(wrapper.find('.hcis-progress-circle__value').text()).toBe('50%');
    });

    it('renders indeterminate loading state', async () => {
        const wrapper = mount(HcisProgress, {
            props: {
                indeterminate: true,
            },
        });

        expect(wrapper.find('.hcis-progress').classes()).toContain('hcis-progress--indeterminate');
    });
});
