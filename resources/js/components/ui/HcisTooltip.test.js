import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HcisTooltip from './HcisTooltip.vue';

describe('HcisTooltip owned primitive component', () => {
    it('renders slot content correctly', () => {
        const wrapper = mount(HcisTooltip, {
            props: {
                content: 'Info petunjuk',
            },
            slots: {
                default: '<button class="test-btn">Tombol</button>',
            },
        });
        expect(wrapper.find('.test-btn').exists()).toBe(true);
        expect(wrapper.text()).toContain('Tombol');
    });

    it('shows tooltip content on mouseenter when trigger is hover', async () => {
        const wrapper = mount(HcisTooltip, {
            props: {
                content: 'Teks rahasia',
                showAfter: 0,
                hideAfter: 0,
            },
            slots: {
                default: '<button class="test-btn">Hover Saya</button>',
            },
            attachTo: document.body,
        });

        await wrapper.find('.hcis-tooltip-wrapper').trigger('mouseenter');
        await new Promise((resolve) => setTimeout(resolve, 50));
        await wrapper.vm.$nextTick();

        const popper = document.querySelector('.hcis-tooltip');
        expect(popper).not.toBeNull();
        expect(popper.textContent).toContain('Teks rahasia');

        wrapper.unmount();
    });

    it('renders title, icon, and footer slots correctly in overpowered mode', async () => {
        const wrapper = mount(HcisTooltip, {
            props: {
                title: 'Judul Lembur',
                icon: 'bi bi-clock-history',
                theme: 'glass',
                visible: true,
            },
            slots: {
                content: '<div class="custom-body">14.5 Jam Lembur</div>',
                footer: '<span class="custom-foot">Tips Tambahan</span>',
            },
            attachTo: document.body,
        });

        await wrapper.vm.$nextTick();
        const popper = document.querySelector('.hcis-tooltip');
        expect(popper.classList.contains('hcis-tooltip--glass')).toBe(true);
        expect(popper.querySelector('.hcis-tooltip__title').textContent).toContain('Judul Lembur');
        expect(popper.querySelector('.custom-body').textContent).toContain('14.5 Jam Lembur');
        expect(popper.querySelector('.custom-foot').textContent).toContain('Tips Tambahan');

        wrapper.unmount();
    });

    it('applies theme classes correctly', async () => {
        const wrapper = mount(HcisTooltip, {
            props: {
                content: 'Warna Brand',
                theme: 'brand',
                visible: true,
            },
            attachTo: document.body,
        });

        await wrapper.vm.$nextTick();
        const popper = document.querySelector('.hcis-tooltip');
        expect(popper.classList.contains('hcis-tooltip--brand')).toBe(true);

        wrapper.unmount();
    });

    it('respects disabled prop and does not show tooltip', async () => {
        const wrapper = mount(HcisTooltip, {
            props: {
                content: 'Tooltip mati',
                disabled: true,
                showAfter: 0,
            },
            attachTo: document.body,
        });

        await wrapper.find('.hcis-tooltip-wrapper').trigger('mouseenter');
        await new Promise((resolve) => setTimeout(resolve, 50));

        const popper = document.querySelector('.hcis-tooltip');
        expect(popper).toBeNull();

        wrapper.unmount();
    });
});
