import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import VisitorDataTable from './VisitorDataTable.vue';
import VisitorDialog from './VisitorDialog.vue';
import VisitorFilterBar from './VisitorFilterBar.vue';
import VisitorTabsCompat from './VisitorTabsCompat.vue';

describe('Visitor design-system compatibility adapters', () => {
    it('maps legacy table columns and column slots to HcisDataTable', () => {
        const wrapper = mount(VisitorDataTable, {
            props: {
                columns: [{ key: 'name', title: 'Nama' }],
                data: [{ id: 1, name: 'Rina' }],
                showPagination: false,
            },
            slots: {
                'column-name':
                    '<template #default="{ item }"><strong data-test="name">{{ item.name }}</strong></template>',
            },
        });

        expect(wrapper.text()).toContain('Nama');
        expect(wrapper.get('[data-test="name"]').text()).toBe('Rina');
    });

    it('emits the complete legacy filter payload', async () => {
        vi.useFakeTimers();
        const wrapper = mount(VisitorFilterBar, {
            props: {
                config: {
                    searchPlaceholder: 'Cari tamu',
                    filters: [{ key: 'status', label: 'Status', type: 'select', default: 'PENDING' }],
                },
            },
        });

        const search = wrapper.get('input[type="search"]');
        await search.setValue('Rina');
        vi.advanceTimersByTime(350);
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('filters-update')?.at(-1)?.[0]).toMatchObject({
            search: 'Rina',
            status: 'PENDING',
        });
        vi.useRealTimers();
    });

    it('maps legacy dialog visibility and size', async () => {
        const wrapper = mount(VisitorDialog, {
            props: { show: true, title: 'Detail Permit', size: 'large' },
            slots: { body: '<p>Isi dialog</p>' },
            attachTo: document.body,
        });

        expect(document.body.textContent).toContain('Isi dialog');
        document.querySelector('[aria-label="Tutup dialog"]').click();
        await wrapper.vm.$nextTick();
        expect(wrapper.emitted('close')).toHaveLength(1);
        wrapper.unmount();
    });

    it('keeps legacy tab ids and events', async () => {
        const wrapper = mount(VisitorTabsCompat, {
            props: {
                activeTab: 'queue',
                tabs: [
                    { id: 'queue', label: 'Antrian' },
                    { id: 'history', label: 'Riwayat' },
                ],
            },
            slots: { queue: '<p>Antrian aktif</p>' },
        });

        expect(wrapper.text()).toContain('Antrian aktif');
        await wrapper.findAll('[role="tab"]')[1].trigger('click');
        expect(wrapper.emitted('tab-change')?.[0]).toEqual(['history']);
    });
});
