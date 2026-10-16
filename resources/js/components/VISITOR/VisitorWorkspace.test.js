import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import VisitorWorkspace from './VisitorWorkspace.vue';

describe('VisitorWorkspace', () => {
    it('normalizes legacy stat and tab shapes without changing callers', async () => {
        const wrapper = mount(VisitorWorkspace, {
            props: {
                title: 'Visitor Test',
                modelValue: 'form',
                stats: [
                    {
                        title: 'Menunggu',
                        value: 2,
                        unit: 'Pengajuan',
                        bgIcon: 'bg-warning-light',
                    },
                ],
                tabs: [
                    { id: 'form', label: 'Form', icon: 'person-plus' },
                    { id: 'history', label: 'Riwayat', icon: 'clock-history' },
                ],
            },
            slots: {
                'panel-form': '<p data-test="form-panel">Form aktif</p>',
            },
        });

        expect(wrapper.text()).toContain('Visitor Test');
        expect(wrapper.text()).toContain('Menunggu');
        expect(wrapper.text()).toContain('Pengajuan');
        expect(wrapper.get('[data-test="form-panel"]').text()).toBe('Form aktif');

        const tabs = wrapper.findAll('[role="tab"]');
        await tabs[1].trigger('click');
        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['history']);
    });
});
