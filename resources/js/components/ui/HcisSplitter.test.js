import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisSplitter from './HcisSplitter.vue';

describe('HcisSplitter.vue', () => {
    it('renders horizontal split panes and drag handle separator', () => {
        const wrapper = mount(HcisSplitter, {
            props: {
                initialRatio: 30,
                orientation: 'horizontal',
            },
            slots: {
                first: '<div class="left-pane">Navigasi Kiri</div>',
                second: '<div class="right-pane">Konten Kanan</div>'
            }
        });

        expect(wrapper.text()).toContain('Navigasi Kiri');
        expect(wrapper.text()).toContain('Konten Kanan');
        expect(wrapper.find('[role="separator"]').exists()).toBe(true);

        const firstPane = wrapper.find('.hcis-pane-first');
        expect(firstPane.attributes('style')).toContain('width: 30%');
    });

    it('toggles collapse on quick collapse arrow click and emits collapse-change', async () => {
        const wrapper = mount(HcisSplitter, {
            props: {
                collapsible: true,
                initialRatio: 30,
            }
        });

        const collapseBtn = wrapper.find('.hcis-collapse-prev');
        expect(collapseBtn.exists()).toBe(true);

        await collapseBtn.trigger('click');

        expect(wrapper.emitted('collapse-change')).toBeTruthy();
        expect(wrapper.emitted('collapse-change')[0][0].pane).toBe('first');
        expect(wrapper.emitted('collapse-change')[0][0].collapsed).toBe(true);
    });
});
