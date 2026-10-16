import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisTabs from './HcisTabs.vue';

describe('HcisTabs.vue', () => {
    const tabs = [
        { key: 'tab1', label: 'Tab Pertama', icon: 'grid' },
        { key: 'tab2', label: 'Tab Kedua', badge: 5 },
        { key: 'tab3', label: 'Tab Nonaktif', disabled: true },
    ];

    it('renders tabs correctly and emits update:modelValue on click', async () => {
        const wrapper = mount(HcisTabs, {
            props: {
                modelValue: 'tab1',
                tabs,
            },
        });

        const tabButtons = wrapper.findAll('button[role="tab"]');
        expect(tabButtons.length).toBe(3);
        expect(tabButtons[0].classes()).toContain('is-active');

        await tabButtons[1].trigger('click');
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['tab2']);
    });

    it('does not emit update:modelValue when clicking a disabled tab', async () => {
        const wrapper = mount(HcisTabs, {
            props: {
                modelValue: 'tab1',
                tabs,
            },
        });

        const tabButtons = wrapper.findAll('button[role="tab"]');
        await tabButtons[2].trigger('click');
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('renders panel slots when active tab matches', async () => {
        const wrapper = mount(HcisTabs, {
            props: {
                modelValue: 'tab1',
                tabs,
            },
            slots: {
                'panel-tab1': '<div>Konten Tab 1</div>',
                'panel-tab2': '<div>Konten Tab 2</div>',
            },
        });

        expect(wrapper.find('.hcis-tabs__content').text()).toContain('Konten Tab 1');
    });

    it('renders exactly one dynamic panel while switching tabs', async () => {
        const wrapper = mount(HcisTabs, {
            props: {
                modelValue: 'tab1',
                tabs,
            },
            slots: {
                'panel-tab1': '<div>Konten Tab 1</div>',
                'panel-tab2': '<div>Konten Tab 2</div>',
            },
        });

        expect(wrapper.findAll('.hcis-tabs__panel')).toHaveLength(1);
        await wrapper.setProps({ modelValue: 'tab2' });
        expect(wrapper.findAll('.hcis-tabs__panel')).toHaveLength(1);
        expect(wrapper.find('.hcis-tabs__panel').attributes('id')).toBe('panel-tab2');
    });

    it('supports vertical tabs orientation and collapse toggle', async () => {
        const wrapper = mount(HcisTabs, {
            props: {
                modelValue: 'tab1',
                tabs,
                orientation: 'vertical',
                collapsible: true,
            },
        });

        expect(wrapper.classes()).toContain('hcis-tabs--vertical');
        expect(wrapper.find('.hcis-tabs__collapse-btn').exists()).toBe(true);

        await wrapper.find('.hcis-tabs__collapse-btn').trigger('click');
        expect(wrapper.classes()).toContain('is-collapsed');
        expect(wrapper.emitted('toggle-collapse')[0]).toEqual([true]);
    });

    it('applies size and block layout classes', () => {
        const wrapper = mount(HcisTabs, {
            props: {
                modelValue: 'tab1',
                tabs,
                size: 'lg',
                block: true,
            },
        });

        expect(wrapper.classes()).toContain('hcis-tabs--size-lg');
        expect(wrapper.classes()).toContain('hcis-tabs--block');
    });

    it('uses direction-aware keyboard navigation', async () => {
        const horizontal = mount(HcisTabs, {
            props: { modelValue: 'tab1', tabs },
        });
        await horizontal.find('[role="tablist"]').trigger('keydown', { key: 'ArrowRight' });
        expect(horizontal.emitted('update:modelValue')[0]).toEqual(['tab2']);

        const vertical = mount(HcisTabs, {
            props: { modelValue: 'tab1', tabs, orientation: 'vertical' },
        });
        await vertical.find('[role="tablist"]').trigger('keydown', { key: 'ArrowDown' });
        expect(vertical.emitted('update:modelValue')[0]).toEqual(['tab2']);
    });

    it('turns vertical navigation into a compact picker on mobile', async () => {
        const originalMatchMedia = window.matchMedia;
        window.matchMedia = () => ({ matches: true });

        const wrapper = mount(HcisTabs, {
            props: {
                modelValue: 'tab1',
                tabs,
                orientation: 'vertical',
                collapsible: true,
                collapsed: true,
            },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.hcis-tabs__mobile-picker').exists()).toBe(true);
        expect(wrapper.find('.hcis-tabs__picker-trigger').text()).toContain('Tab Pertama');
        expect(wrapper.classes()).not.toContain('is-collapsed');

        await wrapper.find('.hcis-tabs__picker-trigger').trigger('click');
        expect(wrapper.findAll('.hcis-tabs__picker-option')).toHaveLength(3);

        await wrapper.findAll('.hcis-tabs__picker-option')[1].trigger('click');
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['tab2']);

        wrapper.unmount();
        window.matchMedia = originalMatchMedia;
    });

    it('supports variant="header" for integrated compact header navigation', () => {
        const wrapper = mount(HcisTabs, {
            props: {
                modelValue: 'tab1',
                tabs,
                variant: 'header',
            },
        });

        expect(wrapper.classes()).toContain('hcis-tabs--variant-header');
        expect(wrapper.findAll('button[role="tab"]')).toHaveLength(3);
    });
});
