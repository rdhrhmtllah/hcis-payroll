import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import VisitorMasterDataPage from './VisitorMasterDataPage.vue';

const mockCatalog = [
    {
        key: 'kategori-permit',
        label: 'Kategori Permit',
        short_label: 'Kategori',
        icon: 'tags-fill',
        description: 'Jenis akses permit kendaraan.',
        id_key: 'Id_Kategori_Permit',
        has_status: true,
        fields: [],
        columns: [],
    },
    {
        key: 'plat-kendaraan',
        label: 'Kode Plat Kendaraan',
        short_label: 'Kode Plat',
        icon: '123',
        description: 'Prefix wilayah plat.',
        id_key: 'Id_Plat_Kendaraan',
        has_status: true,
        fields: [],
        columns: [],
    },
];

describe('VisitorMasterDataPage.vue', () => {
    it('initializes workspace with catalog tabs and default active master', () => {
        const wrapper = mount(VisitorMasterDataPage, {
            props: {
                masterCatalog: mockCatalog,
            },
            global: {
                stubs: {
                    VisitorWorkspace: {
                        template: `
                            <div class="visitor-workspace-stub">
                                <slot name="context" />
                                <slot name="panel-kategori-permit" />
                                <slot name="panel-plat-kendaraan" />
                            </div>
                        `,
                        props: ['tabs', 'stats', 'modelValue'],
                    },
                    VisitorMasterDataPanel: true,
                },
            },
        });

        expect(wrapper.vm.activeMaster).toBe('kategori-permit');
        expect(wrapper.vm.tabs).toEqual([
            { key: 'kategori-permit', label: 'Kategori', icon: 'tags-fill' },
            { key: 'plat-kendaraan', label: 'Kode Plat', icon: '123' },
        ]);
    });

    /*
     * Tombol export hanya boleh muncul kalau server memberi domainnya. Halaman
     * ini dipakai bersama oleh Master Data Permit dan Master Data Tamu, dan
     * tombol yang muncul di halaman tamu akan memanggil endpoint yang belum
     * ada di sana.
     */
    it.each([
        ['tanpa domain export', {}, false],
        ['dengan domain export', { domainExport: 'permit' }, true],
    ])('menampilkan tombol berkas %s', (_nama, props, tampil) => {
        const wrapper = pasangHalaman(props);

        expect(wrapper.find('.export-button-stub').exists()).toBe(tampil);
        expect(wrapper.find('.export-dialog-stub').exists()).toBe(tampil);
        expect(wrapper.find('.import-dialog-stub').exists()).toBe(tampil);
        expect(wrapper.findAll('.export-button-stub')).toHaveLength(tampil ? 2 : 0);
    });

    /*
     * Templat import adalah berkas export. Tautan "unduh templat dulu" di
     * dalam dialog import harus benar-benar sampai ke dialog export, dan
     * dialog import harus ikut tertutup supaya keduanya tidak bertumpuk.
     */
    it('menukar dialog import ke dialog templat saat diminta', async () => {
        const wrapper = pasangHalaman({ domainExport: 'permit' });
        await wrapper.findAll('.export-button-stub')[1].trigger('click');
        await wrapper.vm.$nextTick();

        wrapper.findComponent({ name: 'VisitorMasterDataImportDialogStub' }).vm.$emit('minta-template');
        await wrapper.vm.$nextTick();

        expect(wrapper.findComponent({ name: 'VisitorMasterDataImportDialogStub' }).props('modelValue')).toBe(false);
        expect(wrapper.findComponent({ name: 'VisitorMasterDataExportDialogStub' }).props('modelValue')).toBe(true);
    });

    it('membuka dialog export dengan tab dan saringan yang sedang aktif', async () => {
        const wrapper = pasangHalaman({ domainExport: 'permit' });
        wrapper.vm.changeQuery({ page: 2, limit: 10, search: 'vip', Flag_Aktif: 'Y' });
        await wrapper.vm.$nextTick();

        await wrapper.find('.export-button-stub').trigger('click');
        await wrapper.vm.$nextTick();

        const dialog = wrapper.findComponent({ name: 'VisitorMasterDataExportDialogStub' });
        expect(dialog.props('modelValue')).toBe(true);
        expect(dialog.props('masterAktif')).toBe('kategori-permit');
        expect(dialog.props('domain')).toBe('permit');
        expect(dialog.props('filters')).toMatchObject({ search: 'vip', Flag_Aktif: 'Y' });
    });
});

function pasangHalaman(props = {}) {
    return mount(VisitorMasterDataPage, {
        props: { masterCatalog: mockCatalog, ...props },
        global: {
            stubs: {
                VisitorWorkspace: {
                    template: '<div class="visitor-workspace-stub"><slot name="actions" /><slot name="panel-kategori-permit" /></div>',
                    props: ['tabs', 'stats', 'modelValue'],
                },
                VisitorMasterDataPanel: true,
                HcisButton: { template: '<button class="export-button-stub" @click="$emit(\'click\')"><slot /></button>' },
                VisitorMasterDataExportDialog: {
                    name: 'VisitorMasterDataExportDialogStub',
                    props: ['modelValue', 'baseUrl', 'domain', 'masterAktif', 'filters'],
                    template: '<div class="export-dialog-stub" />',
                },
                VisitorMasterDataImportDialog: {
                    name: 'VisitorMasterDataImportDialogStub',
                    props: ['modelValue', 'baseUrl', 'domain'],
                    template: '<div class="import-dialog-stub" />',
                },
            },
        },
    });
}
