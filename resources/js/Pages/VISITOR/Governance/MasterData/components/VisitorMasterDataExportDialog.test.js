import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import VisitorMasterDataExportDialog from './VisitorMasterDataExportDialog.vue';

const exportPreview = vi.fn();
const exportDownload = vi.fn();
const simpanBerkasUnduhan = vi.fn();

vi.mock('../api/visitorMasterDataApi', () => ({
    visitorMasterDataApi: {
        exportPreview: (...args) => exportPreview(...args),
        exportDownload: (...args) => exportDownload(...args),
    },
    simpanBerkasUnduhan: (...args) => simpanBerkasUnduhan(...args),
}));

vi.mock('@/components/ui', () => ({
    HcisAlert: { props: ['title', 'description'], template: '<div class="alert-stub">{{ title }}</div>' },
    HcisBadge: { template: '<span class="badge-stub"><slot /></span>' },
    HcisButton: {
        props: ['disabled', 'loading'],
        template: '<button class="button-stub" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
    },
    HcisDialog: {
        props: ['modelValue'],
        template: '<div v-if="modelValue" class="dialog-stub"><slot /><slot name="footer" /></div>',
    },
    HcisSkeleton: { template: '<div class="skeleton-stub" />' },
    hcisMessage: { success: vi.fn(), error: vi.fn() },
}));

const PRATINJAU = {
    nama_berkas: 'Master_Data_Permit_2026-09-21.xlsx',
    master_tersaring: 'vehicle-type',
    total_baris: 9,
    sheets: [
        { master: 'vehicle-type', sheet: 'Tipe Kendaraan', kolom: ['Tipe Kendaraan', 'Status'], baris: 2, total: 4, tersaring: true },
        { master: 'vehicle-model', sheet: 'Model Kendaraan', kolom: ['Tipe Kendaraan', 'Model Kendaraan', 'Status'], baris: 7, total: 7, tersaring: false },
    ],
};

function pasang(props = {}) {
    return mount(VisitorMasterDataExportDialog, {
        props: {
            modelValue: true,
            baseUrl: '/api/v1/visitor/master-data',
            domain: 'permit',
            masterAktif: 'vehicle-type',
            filters: { page: 3, limit: 10, search: 'mobil', Flag_Aktif: 'Y' },
            ...props,
        },
    });
}

describe('VisitorMasterDataExportDialog.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        exportPreview.mockResolvedValue({ data: PRATINJAU, message: '' });
        exportDownload.mockResolvedValue({ blob: new Blob(['x']), filename: 'Master_Data_Permit_2026-09-21.xlsx' });
    });

    it('menarik pratinjau saat dibuka dan membuang parameter halaman', async () => {
        pasang();
        await flushPromises();

        expect(exportPreview).toHaveBeenCalledWith('/api/v1/visitor/master-data', 'permit', {
            search: 'mobil',
            Flag_Aktif: 'Y',
            master: 'vehicle-type',
        });
    });

    it('menampilkan satu baris per lembar beserta pembanding saringan', async () => {
        const wrapper = pasang();
        await flushPromises();

        const baris = wrapper.findAll('tbody tr');
        expect(baris).toHaveLength(2);
        expect(wrapper.text()).toContain('Master_Data_Permit_2026-09-21.xlsx');
        expect(wrapper.text()).toContain('9 baris');
        // Lembar tersaring wajib membawa pembandingnya; tanpa itu angka "2"
        // terbaca sebagai seluruh isi master.
        expect(baris[0].text()).toContain('dari 4');
        expect(baris[0].find('.badge-stub').exists()).toBe(true);
        expect(baris[1].find('.badge-stub').exists()).toBe(false);
    });

    it('tidak mengunduh apa pun sebelum tombol unduh ditekan', async () => {
        pasang();
        await flushPromises();

        expect(exportDownload).not.toHaveBeenCalled();
        expect(simpanBerkasUnduhan).not.toHaveBeenCalled();
    });

    it('mengunduh dengan parameter yang sama dengan pratinjaunya lalu menutup dialog', async () => {
        const wrapper = pasang();
        await flushPromises();

        await wrapper.findAll('.button-stub')[1].trigger('click');
        await flushPromises();

        expect(exportDownload).toHaveBeenCalledWith('/api/v1/visitor/master-data', 'permit', {
            search: 'mobil',
            Flag_Aktif: 'Y',
            master: 'vehicle-type',
        });
        expect(simpanBerkasUnduhan).toHaveBeenCalled();
        expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([false]);
    });

    it('menahan unduhan ketika pratinjau gagal', async () => {
        exportPreview.mockRejectedValue(Object.assign(new Error('Master data belum dapat diproses.'), {
            errorCode: 'VISITOR_MASTER_PROCESSING_FAILED',
            requestId: 'req-1',
        }));

        const wrapper = pasang();
        await flushPromises();

        expect(wrapper.find('.alert-stub').text()).toContain('Master data belum dapat diproses.');
        expect(wrapper.findAll('.button-stub')[1].attributes('disabled')).toBeDefined();

        await wrapper.findAll('.button-stub')[1].trigger('click');
        expect(exportDownload).not.toHaveBeenCalled();
    });

    it('menahan unduhan ketika seluruh lembar kosong', async () => {
        exportPreview.mockResolvedValue({
            data: { ...PRATINJAU, total_baris: 0, sheets: [] },
            message: '',
        });

        const wrapper = pasang();
        await flushPromises();

        expect(wrapper.findAll('.button-stub')[1].attributes('disabled')).toBeDefined();
    });
});
