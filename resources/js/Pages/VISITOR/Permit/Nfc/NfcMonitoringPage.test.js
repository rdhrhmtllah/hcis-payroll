import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import NfcMonitoringPage from './NfcMonitoringPage.vue';

vi.mock('axios');

const WorkspaceStub = {
    name: 'VisitorWorkspace',
    props: ['title', 'subtitle', 'stats'],
    template: '<div><slot name="actions" /><slot /></div>',
};

const FilterBarStub = {
    name: 'HcisFilterBar',
    props: ['search', 'quickChips', 'activeChipValue'],
    template: '<div class="hcis-filter-bar-stub"><slot /></div>',
};

const TableStub = {
    name: 'HcisTable',
    props: ['columns', 'rows'],
    template: `
        <div class="hcis-table-stub">
            <div v-for="(row, idx) in rows" :key="idx" class="table-row">
                <slot name="cell-kategori" :row="row" :index="idx" />
                <slot name="cell-identitas" :row="row" :index="idx" />
                <slot name="cell-nfc_hex" :row="row" :index="idx" />
                <slot name="cell-nfc_inbio" :row="row" :index="idx" />
                <slot name="cell-status_gabungan" :row="row" :index="idx" />
                <slot name="cell-aksi" :row="row" :index="idx" />
            </div>
            <slot name="footer" />
        </div>
    `,
};

function baris(ubah = {}) {
    return {
        id_binding: 1,
        id_nfc: 10,
        nfc_hex: '9BDAC6A6',
        nfc_inbio: 13032091,
        flag_validasi_sistem: 'Y',
        master_aktif: 'Y',
        nik: '2502118',
        kode_karyawan: 'AALAMSYAH',
        nama: 'ALAMSYAH',
        no_mitra: null,
        binding_aktif: 'Y',
        kategori: 'KARYAWAN',
        status: 'TERDAFTAR',
        created_at: '2026-09-15 10:00:00',
        ...ubah,
    };
}

function mountPage(props = {}) {
    return mount(NfcMonitoringPage, {
        props: {
            initialStats: {
                total_master: 100,
                total_karyawan: 80,
                total_phl: 20,
                total_anomali: 0,
                total_nonaktif: 0,
            },
            initialData: {
                items: [baris()],
                total: 1,
                page: 1,
                limit: 15,
            },
            ...props,
        },
        global: {
            stubs: {
                VisitorWorkspace: WorkspaceStub,
                HcisFilterBar: FilterBarStub,
                HcisTable: TableStub,
                HcisPagination: true,
                HcisField: true,
                HcisAutocomplete: true,
                HcisButton: { props: ['label'], template: '<button>{{ label }}<slot /></button>' },
                HcisBadge: { props: ['label'], template: '<span class="badge">{{ label }}</span>' },
                HcisIcon: true,
                HcisDialog: true,
                HcisAlert: true,
            },
        },
    });
}

beforeEach(() => {
    axios.get.mockReset();
    axios.post.mockReset();
});

describe('NfcMonitoringPage', () => {
    it('merender kolom terpisah nfc_hex dan nfc_inbio serta status gabungan', () => {
        const w = mountPage();

        expect(w.findComponent(TableStub).exists()).toBe(true);
        expect(w.findComponent(FilterBarStub).exists()).toBe(true);

        // NFC HEX terpisah
        expect(w.text()).toContain('9BDAC6A6');
        // NFC InBio terpisah
        expect(w.text()).toContain('13032091');
        // Identitas
        expect(w.text()).toContain('ALAMSYAH');
        expect(w.text()).toContain('AALAMSYAH');
        // Status gabungan
        expect(w.text()).toContain('Aktif');
        expect(w.text()).toContain('Sistem Valid');
    });

    it('memuat data ulang saat chip saringan diubah', async () => {
        axios.get.mockResolvedValue({
            data: {
                success: true,
                result: {
                    list: { items: [baris({ kategori: 'PHL', no_mitra: '03-V067' })], total: 1, page: 1, limit: 15 },
                    stats: { total_master: 100, total_karyawan: 80, total_phl: 20, total_anomali: 0, total_nonaktif: 0 },
                },
            },
        });

        const w = mountPage();
        const filterBar = w.findComponent(FilterBarStub);
        expect(filterBar.exists()).toBe(true);

        filterBar.vm.$emit('chip-click', { value: 'phl' });
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/admin/visitor/nfc-data', expect.objectContaining({
            params: expect.objectContaining({ tab: 'phl' }),
        }));
    });

    it('menampilkan tombol Tambah Pairing Manual dan Import Excel', () => {
        const w = mountPage();
        expect(w.text()).toContain('Tambah Pairing Manual');
        expect(w.text()).toContain('Import Excel');
        // Tombol preset 15 Sep sudah tidak ada
        expect(w.text()).not.toContain('Impor Berkas 15 Sep 2026');
    });
});
