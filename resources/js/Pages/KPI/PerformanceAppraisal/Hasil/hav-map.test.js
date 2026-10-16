import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import HavMapPage from './hav-map.vue';
import paApi from '../api/paApi.js';

vi.mock('../api/paApi.js', () => ({
    default: {
        daftarSiklus: vi.fn(),
        havMap: vi.fn(),
        detailHasil: vi.fn(),
        kalibrasi: vi.fn(),
        hitungUlang: vi.fn(),
    },
}));

const uiStubs = {
    HcisHeader: {
        props: ['title', 'description'],
        template: '<header><h1>{{ title }}</h1><p>{{ description }}</p><slot name="badge" /><slot name="actions" /><slot name="console" /></header>',
    },
    HcisStatGroup: { template: '<div class="stat-group"><slot /></div>' },
    HcisStatCard: {
        props: ['label', 'value'],
        template: '<div class="stat-card"><span>{{ label }}</span><strong>{{ value }}</strong></div>',
    },
    HcisBadge: {
        props: ['label'],
        template: '<span class="badge">{{ label }}</span>',
    },
    HcisButton: {
        props: ['label', 'loading', 'disabled'],
        template: '<button :disabled="disabled">{{ label }}</button>',
    },
    HcisIcon: { template: '<i />' },
    HcisResultState: { template: '<div class="result-state" />' },
    HcisSkeleton: { template: '<div class="skeleton" />' },
    PaResultDialog: { template: '<div class="result-dialog-stub" />' },
};

describe('PerformanceAppraisalHavMapPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        paApi.daftarSiklus.mockResolvedValue({
            siklus: [
                { Id_PA_Cycle: 16, Kode_Cycle: 'PA-2026-H1', Nama_Cycle: 'Semester 1 2026', Status_Cycle: 'CALCULATED' },
            ],
        });
        paApi.havMap.mockResolvedValue({
            ringkasan: { total: 10, dihitung: 10, tertahan: 0, terbit: 0 },
            kuadran: [
                {
                    Nomor_Box: 9,
                    Nama_Kuadran: 'Star / High Performer',
                    Tingkat_Performa: 'Tinggi',
                    Tingkat_Kompetensi: 'Tinggi',
                    Warna: '#10B981',
                    jumlah: 2,
                    peserta: [
                        { Id_PA_Participant: 401, Nama_Karyawan: 'Andi Wijaya', Kode_Karyawan: 'EMP010', Nilai_Akhir: 94.5 },
                    ],
                },
            ],
            grade: [
                { Kode_Grade: 'A', jumlah: 2, persen: 20, Warna: '#10B981' },
            ],
        });
    });

    it('renders header, loads cycle and fetches 9-box quadrants', async () => {
        const wrapper = mount(HavMapPage, {
            global: { stubs: uiStubs },
        });

        await vi.dynamicImportSettled();

        expect(wrapper.find('h1').text()).toContain('Matriks 9-Box HAV Map');
        expect(paApi.daftarSiklus).toHaveBeenCalled();
        expect(paApi.havMap).toHaveBeenCalledWith('16');

        expect(wrapper.text()).toContain('Star / High Performer');
        expect(wrapper.text()).toContain('Andi Wijaya');
    });
});
