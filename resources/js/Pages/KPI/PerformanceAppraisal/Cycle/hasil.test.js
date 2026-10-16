import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import HasilPage from './hasil.vue';
import paApi from '../api/paApi.js';

vi.mock('../api/paApi.js', () => ({
    default: {
        daftarSiklus: vi.fn(),
        daftarPeserta: vi.fn(),
        hitungSiklus: vi.fn(),
        ringkasanPenerbitan: vi.fn(),
        terbitkanSiklus: vi.fn(),
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
    HcisFilterBar: {
        props: ['search', 'quickChips'],
        template: '<div class="filter-bar"><slot /></div>',
    },
    HcisTable: {
        props: ['columns', 'rows'],
        template: '<table class="table-stub"><tbody><tr v-for="r in rows" :key="r.Id_PA_Participant"><td>{{ r.Nama_Karyawan }}</td></tr></tbody></table>',
    },
    HcisBadge: {
        props: ['label'],
        template: '<span class="badge">{{ label }}</span>',
    },
    HcisStatusBadge: {
        props: ['status', 'label'],
        template: '<span class="status-badge">{{ label }}</span>',
    },
    HcisButton: {
        props: ['label', 'loading', 'disabled'],
        template: '<button :disabled="disabled">{{ label }}</button>',
    },
    HcisIcon: { template: '<i />' },
    HcisResultState: { template: '<div class="result-state" />' },
    HcisEmptyState: { template: '<div class="empty-state" />' },
    HcisSkeleton: { template: '<div class="skeleton" />' },
    PaResultDialog: { template: '<div class="result-dialog-stub" />' },
    PaPublicationDialog: { template: '<div class="publication-dialog-stub" />' },
};

describe('PerformanceAppraisalHasilPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        paApi.daftarSiklus.mockResolvedValue({
            siklus: [
                { Id_PA_Cycle: 12, Kode_Cycle: 'PA-2026-H1', Nama_Cycle: 'Semester 1 2026', Status_Cycle: 'CALCULATED' },
            ],
        });
        paApi.daftarPeserta.mockResolvedValue({
            peserta: [
                {
                    Id_PA_Participant: 201,
                    Nama_Karyawan: 'Citra Kirana',
                    Kode_Karyawan: 'EMP001',
                    Nama_Level: 'Manager',
                    Nilai_KPI: 88.5,
                    Nilai_Kompetensi: 91.2,
                    Nilai_Akhir: 89.85,
                    Kode_Grade_Final: 'A',
                    Status_Hasil: 'DIHITUNG',
                    Flag_Dikalibrasi: 'T',
                },
            ],
        });
    });

    it('renders header, loads cycle and fetches calculated participant results', async () => {
        const wrapper = mount(HasilPage, {
            global: { stubs: uiStubs },
        });

        await vi.dynamicImportSettled();

        expect(wrapper.find('h1').text()).toContain('Hasil & Penerbitan Nilai');
        expect(paApi.daftarSiklus).toHaveBeenCalled();
        expect(paApi.daftarPeserta).toHaveBeenCalledWith(12);

        expect(wrapper.text()).toContain('Citra Kirana');
        expect(wrapper.text()).toContain('Peserta Terhitung');
    });

    it('displays gateway navigation cards for Kalibrasi, HAV Map, Distribusi, and Riwayat', async () => {
        const wrapper = mount(HasilPage, {
            global: { stubs: uiStubs },
        });

        await vi.dynamicImportSettled();

        expect(wrapper.text()).toContain('Monitoring & Hasil');
        expect(wrapper.text()).toContain('Matriks 9-Box HAV Map');
        expect(wrapper.text()).toContain('Distribusi Nilai & Grade');
        expect(wrapper.text()).toContain('Riwayat & Lini Masa Audit');
    });

    /* Bentuk baris SUNGGUHAN dari daftar peserta (lihat api/fixtures/participants.json):
       nilai akhir di `Final_Score`, status terbit `TERBIT`. */
    it('membaca status TERBIT dan Final_Score dari bentuk respons sungguhan', async () => {
        paApi.daftarPeserta.mockResolvedValue({
            peserta: [
                { Id_PA_Participant: 301, Nama_Karyawan: 'Ani', Final_Score: 80, Kode_Grade_Final: 'B', Status_Hasil: 'TERBIT', Flag_Dikalibrasi: 'T' },
                { Id_PA_Participant: 302, Nama_Karyawan: 'Budi', Final_Score: 90, Kode_Grade_Sistem: 'A', Status_Hasil: 'DIHITUNG', Flag_Dikalibrasi: 'T' },
                { Id_PA_Participant: 303, Nama_Karyawan: 'Cici', Final_Score: null, Status_Hasil: 'TERTAHAN', Flag_Dikalibrasi: 'T' },
            ],
        });
        const wrapper = mount(HasilPage, { global: { stubs: uiStubs } });
        await vi.dynamicImportSettled();

        const kartu = Object.fromEntries(wrapper.findAll('.stat-card').map((c) => [c.find('span').text(), c.find('strong').text()]));
        expect(kartu['Status Penerbitan']).toContain('1 Terbit');
        expect(kartu['Rata-rata Skor Akhir']).toContain('85');
        expect(wrapper.vm.calculatedCount).toBe(2);
        expect(wrapper.vm.publishedCount).toBe(1);
    });
});
