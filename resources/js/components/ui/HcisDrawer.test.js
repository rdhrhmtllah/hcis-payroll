import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisDrawer from './HcisDrawer.vue';

describe('HcisDrawer.vue', () => {
    it('renders title and content when modelValue is true', async () => {
        const wrapper = mount(HcisDrawer, {
            props: {
                modelValue: true,
                title: 'Judul Laci Uji',
                teleportTo: false,
            },
            slots: {
                default: '<div class="test-content">Konten Laci Uji</div>',
            },
        });

        expect(wrapper.find('.hcis-drawer__title').text()).toBe('Judul Laci Uji');
        expect(wrapper.find('.test-content').text()).toBe('Konten Laci Uji');
    });

    it('emits update:modelValue false when close button is clicked', async () => {
        const wrapper = mount(HcisDrawer, {
            props: {
                modelValue: true,
                title: 'Judul Laci',
                showClose: true,
                teleportTo: false,
            },
        });

        await wrapper.find('.hcis-drawer__close').trigger('click');
        expect(wrapper.emitted('update:modelValue')[0]).toEqual([false]);
        expect(wrapper.emitted('close')).toBeTruthy();
    });

    it('supports sentiment variants and custom direction', async () => {
        const wrapper = mount(HcisDrawer, {
            props: {
                modelValue: true,
                title: 'Warning Drawer',
                direction: 'btt',
                sentiment: 'warning',
                teleportTo: false,
            },
        });

        expect(wrapper.find('.hcis-drawer--btt').exists()).toBe(true);
        expect(wrapper.find('.hcis-drawer--sentiment-warning').exists()).toBe(true);
    });
});
