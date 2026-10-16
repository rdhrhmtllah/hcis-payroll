import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import VisitorMasterDataPanel from './VisitorMasterDataPanel.vue';

const mockDefinition = {
    key: 'kategori-permit',
    label: 'Kategori Permit',
    short_label: 'Kategori',
    icon: 'tags-fill',
    description: 'Jenis akses permit kendaraan.',
    id_key: 'Id_Kategori_Permit',
    has_status: true,
    columns: [
        { key: 'Id_Kategori_Permit', label: 'ID', width: '72px' },
        { key: 'Nama_Kategori', label: 'Kategori' },
        { key: 'Deskripsi_Akses', label: 'Deskripsi' },
        { key: 'Kode_Warna_UI', label: 'Warna' },
    ],
    fields: [
        { key: 'Nama_Kategori', label: 'Nama Kategori', type: 'text', required: true, max: 50 },
        { key: 'Deskripsi_Akses', label: 'Deskripsi Akses', type: 'textarea', required: false, max: 255 },
        { key: 'Kode_Warna_UI', label: 'Warna UI', type: 'color', required: false, max: 10, default: '#6366F1' },
    ],
};

const mockItems = [
    {
        Id_Kategori_Permit: 1,
        Nama_Kategori: 'VIP Visitor',
        Deskripsi_Akses: 'Akses seluruh gedung',
        Kode_Warna_UI: '#4F46E5',
        Flag_Aktif: 'Y',
        Usage_Count: 12,
    },
    {
        Id_Kategori_Permit: 2,
        Nama_Kategori: 'Vendor Regular',
        Deskripsi_Akses: 'Akses loading dock',
        Kode_Warna_UI: '#10B981',
        Flag_Aktif: 'T',
        Usage_Count: 0,
    },
];

describe('VisitorMasterDataPanel.vue', () => {
    it('renders master data table with responsive controls and stats', () => {
        const wrapper = mount(VisitorMasterDataPanel, {
            props: {
                definition: mockDefinition,
                items: mockItems,
                stats: [
                    { label: 'Total', value: '2', sentiment: 'neutral', icon: 'collection' },
                    { label: 'Aktif', value: '1', sentiment: 'success', icon: 'check-circle' },
                    { label: 'Nonaktif', value: '1', sentiment: 'purple', icon: 'pause-circle' },
                ],
                pagination: { page: 1, pageSize: 10, total: 2, totalPages: 1 },
            },
            global: {
                stubs: {
                    HcisDataTable: {
                        template: `
                            <div class="hcis-data-table-stub">
                                <slot name="headerActions" />
                                <slot name="grid" :data="items" />
                                <div class="table-rows">
                                    <div v-for="item in items" :key="item.Id_Kategori_Permit" class="row">
                                        <slot name="cell-Flag_Aktif" :row="item" />
                                        <slot name="cell-Kode_Warna_UI" :row="item" />
                                        <slot name="cell-Usage_Count" :row="item" />
                                        <slot name="cell-actions" :row="item" />
                                    </div>
                                </div>
                            </div>
                        `,
                        props: ['items', 'columns', 'data', 'loading', 'viewMode'],
                    },
                    HcisDialog: {
                        template: '<div v-if="modelValue" class="hcis-dialog-stub"><slot /><slot name="footer" /></div>',
                        props: ['modelValue'],
                    },
                    HcisField: true,
                    HcisSelect: true,
                    HcisSelectOption: true,
                    HcisStatCard: true,
                    HcisStatGroup: true,
                    HcisBadge: true,
                    HcisButton: true,
                    HcisIcon: true,
                    HcisCard: true,
                },
            },
        });

        expect(wrapper.find('.visitor-master-stats-grid').exists()).toBe(true);
        expect(wrapper.find('.visitor-master-header-actions').exists()).toBe(true);
        expect(wrapper.find('.visitor-master-add-btn').exists()).toBe(true);
    });

    it('opens editor dialog with populated form values on edit', async () => {
        const wrapper = mount(VisitorMasterDataPanel, {
            props: {
                definition: mockDefinition,
                items: mockItems,
                pagination: { page: 1, pageSize: 10, total: 2, totalPages: 1 },
            },
            global: {
                stubs: {
                    HcisDataTable: true,
                    HcisDialog: {
                        template: '<div v-if="modelValue" class="hcis-dialog-stub"><slot /><slot name="footer" /></div>',
                        props: ['modelValue'],
                    },
                    HcisField: true,
                    HcisSelect: true,
                    HcisSelectOption: true,
                    HcisBadge: true,
                    HcisButton: true,
                    HcisIcon: true,
                    HcisCard: true,
                },
            },
        });

        wrapper.vm.openEdit(mockItems[0]);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.editorOpen).toBe(true);
        expect(wrapper.vm.editingId).toBe(1);
        expect(wrapper.vm.form.Nama_Kategori).toBe('VIP Visitor');
        expect(wrapper.vm.form.Kode_Warna_UI).toBe('#4F46E5');
    });

    it('emits save event with form payload on submit', async () => {
        const wrapper = mount(VisitorMasterDataPanel, {
            props: {
                definition: mockDefinition,
                items: mockItems,
                pagination: { page: 1, pageSize: 10, total: 2, totalPages: 1 },
            },
            global: {
                stubs: {
                    HcisDataTable: true,
                    HcisDialog: true,
                    HcisField: true,
                    HcisSelect: true,
                    HcisSelectOption: true,
                    HcisBadge: true,
                    HcisButton: true,
                    HcisIcon: true,
                    HcisCard: true,
                },
            },
        });

        wrapper.vm.openCreate();
        wrapper.vm.form.Nama_Kategori = 'Kategori Baru';
        wrapper.vm.form.Deskripsi_Akses = 'Deskripsi baru';
        wrapper.vm.submit();

        expect(wrapper.emitted('save')).toBeTruthy();
        expect(wrapper.emitted('save')?.[0]?.[0]).toEqual({
            id: null,
            payload: {
                Nama_Kategori: 'Kategori Baru',
                Deskripsi_Akses: 'Deskripsi baru',
                Kode_Warna_UI: '#6366F1',
            },
        });
    });
});
