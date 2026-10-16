import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisRow from './HcisRow.vue';
import HcisCol from './HcisCol.vue';

describe('HcisRow & HcisCol Layout System', () => {
    it('renders HcisRow with correct gutter style and flex alignment', () => {
        const wrapper = mount(HcisRow, {
            props: {
                gutter: 24,
                align: 'middle',
                justify: 'center',
            },
            slots: {
                default: '<div class="test-content">Content</div>'
            }
        });

        expect(wrapper.classes()).toContain('hcis-row');
        expect(wrapper.classes()).toContain('hcis-row--align-middle');
        expect(wrapper.classes()).toContain('hcis-row--justify-center');
        expect(wrapper.attributes('style')).toContain('margin-left: -12px');
        expect(wrapper.attributes('style')).toContain('margin-right: -12px');
    });

    it('renders HcisCol with responsive span classes and gutter padding', () => {
        const wrapper = mount(HcisRow, {
            props: { gutter: 16 },
            slots: {
                default: {
                    components: { HcisCol },
                    template: '<HcisCol :span="24" :md="12" :lg="8" offset="2">Column</HcisCol>'
                }
            }
        });

        const col = wrapper.findComponent(HcisCol);
        expect(col.exists()).toBe(true);
        expect(col.classes()).toContain('hcis-col-24');
        expect(col.classes()).toContain('hcis-col-md-12');
        expect(col.classes()).toContain('hcis-col-lg-8');
        expect(col.classes()).toContain('hcis-col-offset-2');
        expect(col.attributes('style')).toContain('padding-left: 8px');
        expect(col.attributes('style')).toContain('padding-right: 8px');
    });
});
