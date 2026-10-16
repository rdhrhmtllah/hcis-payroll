import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import VisitorMasterDataImportDialog from './VisitorMasterDataImportDialog.vue';

const importPreview = vi.fn();
const importRun = vi.fn();

vi.mock('../api/visitorMasterDataApi', () => ({
    visitorMasterDataApi: {
        importPreview: (...args) => importPreview(...args),
        importRun: (...args) => importRun(...args),
    },
    simpanBerkasUnduhan: vi.fn(),
}));

vi.mock('@/components/ui', () => ({
    HcisAlert: { props: ['title', 'description'], template: '<div class="alert-stub">{{ title }}</div>' },
    HcisBadge: { props: ['variant', 'size', 'dot', 'pulse'], template: '<span class="badge-stub"><slot /></span>' },
    HcisButton: {
        props: ['disabled', 'loading'],
        template: '<button class="button-stub" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
    },
    HcisDialog: {
        props: ['modelValue'],
        template: '<div v-if="modelValue" class="dialog-stub"><slot /><slot name="footer" /></div>',
    },
    HcisIcon: { template: '<i class="icon-stub" />' },
    HcisSkeleton: { template: '<div class="skeleton-stub" />' },
    hcisMessage: { success: vi.fn(), error: vi.fn() },
}));

const LAPORAN = {
    nama_berkas: 'master.xlsx',
    sidik: 'a'.repeat(64),
    dijalankan: false,
    ringkas: { baris: 4, ditambah: 2, dilewati: 1, ditolak: 1, dinonaktifkan: 1 },
    lembar: [
        {
            master: 'vehicle-type',
            sheet: 'Tipe Kendaraan',
            label: 'Tipe Kendaraan',
            terbaca: true,
            galat: null,
            kolom: ['Tipe Kendaraan', 'Status'],
            baris: 3,
            ditambah: 2,
            dilewati: 1,
            ditolak: 0,
            contoh: ['Mobil Listrik', 'Bus Kecil'],
            masalah: [],
            masalah_lain: 0,
        },
        {
            master: 'vehicle-model',
            sheet: 'Model Kendaraan',
            label: 'Model Kendaraan',
            terbaca: true,
            galat: null,
            kolom: ['Tipe Kendaraan', 'Model Kendaraan', 'Status'],
            baris: 1,
            ditambah: 0,
            dilewati: 0,
            ditolak: 1,
            contoh: [],
            masalah: [{ baris: 4, jenis: 'RELASI', pesan: 'Tipe Kendaraan "Truk Kecil" tidak ada pada master Tipe Kendaraan atau sedang nonaktif.' }],
            masalah_lain: 0,
        },
    ],
    lembar_diabaikan: ['Catatan Saya'],
    catatan: ['Tipe Kendaraan: kolom Digunakan Untuk tidak ada di berkas, diisi "Permit & Tamu".'],
};

const berkas = () => new File(['x'], 'master.xlsx');

function pasang() {
    return mount(VisitorMasterDataImportDialog, {
        props: {
            modelValue: true,
            baseUrl: '/api/v1/visitor/master-data',
            domain: 'permit',
        },
    });
}

async function pilihBerkas(wrapper, file = berkas()) {
    wrapper.vm.pilihBerkas({ target: { files: [file] } });
    await flushPromises();

    return file;
}

describe('VisitorMasterDataImportDialog.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        importPreview.mockResolvedValue({ data: LAPORAN, message: '' });
        importRun.mockResolvedValue({
            data: { ...LAPORAN, dijalankan: true },
            message: 'Import selesai: 2 baris ditambahkan.',
        });
    });

    it('menilai berkas lewat server tanpa mengeksekusi apa pun', async () => {
        const wrapper = pasang();
        const file = await pilihBerkas(wrapper);

        expect(importPreview).toHaveBeenCalledWith('/api/v1/visitor/master-data', 'permit', file);
        expect(importRun).not.toHaveBeenCalled();
    });

    it('menampilkan rencana per lembar beserta baris yang ditolak', async () => {
        const wrapper = pasang();
        await pilihBerkas(wrapper);

        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
        expect(wrapper.text()).toContain('2 akan ditambahkan');
        expect(wrapper.text()).toContain('1 sudah ada');
        expect(wrapper.text()).toContain('Model Kendaraan baris 4');
        expect(wrapper.text()).toContain('tidak ada pada master Tipe Kendaraan');
        // Kolom yang diisi bawaan harus terbaca di layar, bukan hanya di kode.
        expect(wrapper.text()).toContain('Digunakan Untuk');
        expect(wrapper.text()).toContain('Catatan Saya');
    });

    it('mengirim sidik dari pratinjau saat import dijalankan', async () => {
        const wrapper = pasang();
        const file = await pilihBerkas(wrapper);

        await wrapper.findAll('.button-stub').at(-1).trigger('click');
        await flushPromises();

        expect(importRun).toHaveBeenCalledWith('/api/v1/visitor/master-data', 'permit', file, LAPORAN.sidik);
        expect(wrapper.emitted('selesai')).toHaveLength(1);
        expect(wrapper.text()).toContain('2 ditambahkan');
    });

    it('menahan import ketika tidak ada baris yang bisa ditambahkan', async () => {
        importPreview.mockResolvedValue({
            data: { ...LAPORAN, ringkas: { ...LAPORAN.ringkas, ditambah: 0 } },
            message: '',
        });

        const wrapper = pasang();
        await pilihBerkas(wrapper);

        await wrapper.findAll('.button-stub').at(-1).trigger('click');
        expect(importRun).not.toHaveBeenCalled();
    });

    it('menahan import ketika berkasnya ditolak server', async () => {
        importPreview.mockRejectedValue(Object.assign(new Error('Berkas tidak dapat dibaca sebagai Excel.'), {
            errorCode: 'VISITOR_MASTER_CONFLICT',
            requestId: '',
        }));

        const wrapper = pasang();
        await pilihBerkas(wrapper);

        expect(wrapper.find('.alert-stub').text()).toContain('Berkas tidak dapat dibaca');

        await wrapper.findAll('.button-stub').at(-1).trigger('click');
        expect(importRun).not.toHaveBeenCalled();
    });

    it('meminta templat tanpa menutup jalan kembali', async () => {
        const wrapper = pasang();

        await wrapper.find('.visitor-master-import__tautan').trigger('click');

        expect(wrapper.emitted('minta-template')).toHaveLength(1);
    });
});
