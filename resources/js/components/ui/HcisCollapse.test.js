import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisCollapse from './HcisCollapse.vue';
import HcisCollapseItem from './HcisCollapseItem.vue';

describe('HcisCollapse.vue & HcisCollapseItem.vue', () => {
    it('expands item when clicked', async () => {
        const wrapper = mount({
            components: { HcisCollapse, HcisCollapseItem },
            template: `
                <HcisCollapse v-model="activeItem">
                    <HcisCollapseItem name="panel1" title="Judul Panel 1">
                        Konten Panel 1
                    </HcisCollapseItem>
                    <HcisCollapseItem name="panel2" title="Judul Panel 2">
                        Konten Panel 2
                    </HcisCollapseItem>
                </HcisCollapse>
            `,
            data() {
                return { activeItem: ['panel1'] };
            },
        });

        const items = wrapper.findAllComponents(HcisCollapseItem);
        expect(items[0].classes()).toContain('hcis-collapse-item--active');
        expect(items[1].classes()).not.toContain('hcis-collapse-item--active');

        await items[1].find('.hcis-collapse-item__header').trigger('click');
        expect(wrapper.vm.activeItem).toContain('panel2');
    });

    it('behaves as accordion when accordion prop is true', async () => {
        const wrapper = mount({
            components: { HcisCollapse, HcisCollapseItem },
            template: `
                <HcisCollapse v-model="activeItem" accordion>
                    <HcisCollapseItem name="panel1" title="Panel 1">
                        Konten 1
                    </HcisCollapseItem>
                    <HcisCollapseItem name="panel2" title="Panel 2">
                        Konten 2
                    </HcisCollapseItem>
                </HcisCollapse>
            `,
            data() {
                return { activeItem: 'panel1' };
            },
        });

        const items = wrapper.findAllComponents(HcisCollapseItem);
        expect(items[0].classes()).toContain('hcis-collapse-item--active');

        await items[1].find('.hcis-collapse-item__header').trigger('click');
        expect(wrapper.vm.activeItem).toBe('panel2');
        expect(items[0].classes()).not.toContain('hcis-collapse-item--active');
    });
});
