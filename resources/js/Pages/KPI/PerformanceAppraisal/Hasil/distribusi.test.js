import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import DistribusiPage from './distribusi.vue';
import paApi from '../api/paApi.js';

vi.mock('../api/paApi.js', () => ({
    default: {
        daftarSiklus: vi.fn(),
        distribusiSiklus: vi.fn(),
    },
}));

const uiStubs = {
    HcisHeader: {
        props: ['title', 'description', 'kicker'],
        template: '<header><span class="kicker">{{ kicker }}</span><h1>{{ title }}</h1><p>{{ description }}</p><slot name="badge" /><slot name="actions" /><slot name="console" /></header>',
    },
    HcisStatGroup: { template: '<div class="stat-group"><slot /></div>' },
    HcisStatCard: {
        props: ['label', 'value', 'caption'],
        template: '<div class="stat-card"><span>{{ label }}</span><strong>{{ value }}</strong><small>{{ caption }}</small></div>',
    },
    HcisCard: {
        props: ['title', 'kicker', 'description'],
        template: '<section class="hcis-card"><slot name="header" /><div><slot /></div></section>',
    },
    HcisBadge: {
        props: ['label'],
        template: '<span class="badge">{{ label }}</span>',
    },
    HcisButton: {
        props: ['label', 'loading', 'disabled'],
        template: '<button :disabled="disabled">{{ label }}</button>',
    },
    HcisAvatar: {
        props: ['name', 'size'],
        template: '<div class="avatar"><span>{{ name }}</span></div>',
    },
    HcisChart: {
        props: ['type', 'options', 'height'],
        template: '<div class="chart-stub" :data-type="type" />',
    },
    HcisIcon: { template: '<i />' },
    HcisResultState: { template: '<div class="result-state" />' },
    HcisSkeleton: { template: '<div class="skeleton" />' },
};

describe('PerformanceAppraisalDistribusiPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        paApi.daftarSiklus.mockResolvedValue({
            siklus: [
                { Id_PA_Cycle: 18, Kode_Cycle: 'PA-2026-H1', Nama_Cycle: 'Semester 1 2026', Status_Cycle: 'CALCULATED' },
            ],
        });
        paApi.distribusiSiklus.mockResolvedValue({
            cukup_untuk_kurva: true,
            asal_angka: 'HASIL_AKHIR_DIHITUNG',
            statistik: {
                rerata: 82.5,
                simpangan_baku: 6.2,
                median: 83.0,
                min: 65.0,
                maks: 98.0,
                rata_kpi: 83.0,
                standar_deviasi_kpi: 6.5,
                rata_kompetensi: 82.0,
                standar_deviasi_kompetensi: 5.9,
            },
            per_level: [
                { id_level: 1, nama_level: 'Staff', jumlah: 620, cukup_untuk_kurva: true, rata_kpi: 70.0, standar_deviasi_kpi: 12.0, rata_kompetensi: 68.0, standar_deviasi_kompetensi: 13.0 },
                { id_level: 2, nama_level: 'Supervisor', jumlah: 340, cukup_untuk_kurva: true, rata_kpi: 76.0, standar_deviasi_kpi: 10.0, rata_kompetensi: 74.0, standar_deviasi_kompetensi: 11.0 },
                { id_level: 3, nama_level: 'Section Head', jumlah: 180, cukup_untuk_kurva: true, rata_kpi: 80.0, standar_deviasi_kpi: 9.0, rata_kompetensi: 78.0, standar_deviasi_kompetensi: 10.0 },
                { id_level: 4, nama_level: 'Department Head', jumlah: 64, cukup_untuk_kurva: true, rata_kpi: 85.0, standar_deviasi_kpi: 7.0, rata_kompetensi: 83.0, standar_deviasi_kompetensi: 8.0 },
                { id_level: 5, nama_level: 'Division Head', jumlah: 18, cukup_untuk_kurva: true, rata_kpi: 90.0, standar_deviasi_kpi: 6.0, rata_kompetensi: 88.0, standar_deviasi_kompetensi: 7.0 },
            ],
            talent_highlights: {
                top_performers: [
                    { participant_id: 101, nama: 'Andi Rahman', level: 'Department Head', divisi: 'Information Technology', skor: 87.0 },
                    { participant_id: 102, nama: 'Nadia Fitriani', level: 'Supervisor', divisi: 'Finance & Accounting', skor: 86.0 },
                    { participant_id: 103, nama: 'Dimas Pratama', level: 'Section Head', divisi: 'Operations & Supply Chain', skor: 84.0 },
                ],
                high_potential: [
                    { participant_id: 201, nama: 'Siti Wulandari', level: 'Staff', divisi: 'Human Capital', skor: 81.0 },
                    { participant_id: 202, nama: 'Rani A.', level: 'Supervisor', divisi: 'Marketing & Sales', skor: 80.0 },
                ],
                need_improvement: [
                    { participant_id: 301, nama: 'Karyawan #4021', level: 'Staff', divisi: 'Branch Operations', skor: 46.0 },
                    { participant_id: 302, nama: 'Karyawan #3990', level: 'Staff', divisi: 'General Services', skor: 50.0 },
                ],
            },
            grade: [
                { kode: 'A', label: 'Istimewa', jumlah: 122, persen: 10, target_persen: 10, selisih: 0 },
                { kode: 'B+', label: 'Sangat Baik', jumlah: 306, persen: 25, target_persen: 25, selisih: 0 },
            ],
            histogram: [
                { batas_bawah: 60, batas_atas: 70, jumlah: 245, label: '60-70' },
                { batas_bawah: 70, batas_atas: 80, jumlah: 450, label: '70-80' },
                { batas_bawah: 80, batas_atas: 90, jumlah: 310, label: '80-90' },
            ],
        });
    });

    it('renders header, loads cycle and fetches normal distribution and statistics', async () => {
        const wrapper = mount(DistribusiPage, {
            global: { stubs: uiStubs },
        });

        await vi.dynamicImportSettled();

        expect(wrapper.find('h1').text()).toContain('Distribusi Performance & Kurva Normal');
        expect(paApi.daftarSiklus).toHaveBeenCalled();
        expect(paApi.distribusiSiklus).toHaveBeenCalledWith('18');

        // Cockpit stats
        expect(wrapper.text()).toContain('Total Partisipan Dinilai');
        expect(wrapper.text()).toContain('Rata-rata Skor (Mean)');
        expect(wrapper.text()).toContain('82.5%');

        // Multi-level Normal Curve titles & Table
        expect(wrapper.text()).toContain('Analisis Kurva Normal per Tingkat Jabatan');
        expect(wrapper.text()).toContain('Distribusi Normal — Nilai KPI per Tingkat Jabatan');
        expect(wrapper.text()).toContain('Distribusi Normal — Nilai Kompetensi per Tingkat Jabatan');
        expect(wrapper.text()).toContain('Ringkasan Statistik per Tingkat Jabatan');

        // Job level rows in table
        expect(wrapper.text()).toContain('Staff');
        expect(wrapper.text()).toContain('Supervisor');
        expect(wrapper.text()).toContain('Section Head');
        expect(wrapper.text()).toContain('Department Head');
        expect(wrapper.text()).toContain('Division Head');

        // Talent highlights
        expect(wrapper.text()).toContain('Top Performer');
        expect(wrapper.text()).toContain('Andi Rahman');
        expect(wrapper.text()).toContain('High Potential');
        expect(wrapper.text()).toContain('Siti Wulandari');
        expect(wrapper.text()).toContain('Need Improvement');
        expect(wrapper.text()).toContain('Karyawan #4021');
    });

    it('menahan kurva normal dan tetap menampilkan alasan dari server', async () => {
        paApi.distribusiSiklus.mockResolvedValue({
            cukup_untuk_kurva: false,
            alasan_kurva_ditahan: 'Belum cukup data.',
            statistik: { rerata: 77, simpangan_baku: 7.78, median: 77, min: 71.5, maks: 82.5 },
            per_level: [],
            grade: [{ kode: 'B', label: 'Baik', jumlah: 2, persen: 100, target_persen: 100, selisih: 0 }],
            histogram: [{ batas_bawah: 70, batas_atas: 75, jumlah: 1, label: '70-75' }],
        });

        const wrapper = mount(DistribusiPage, {
            global: { stubs: uiStubs },
        });

        await vi.dynamicImportSettled();

        expect(wrapper.text()).toContain('Sampel Terbatas');
        expect(wrapper.text()).toContain('Kurva Ditahan');
        expect(wrapper.text()).toContain('Belum cukup data.');
    });

    it('tidak pernah mengisi data kosong dengan contoh karangan', async () => {
        window.history.replaceState(null, '', '/performance-appraisal/distribusi');
        paApi.distribusiSiklus.mockResolvedValue({
            keseluruhan: { jumlah: 2, rata_rata: 72.4, standar_deviasi: 22.8, cukup_untuk_kurva: false, alasan_kurva_ditahan: 'Baru 2 nilai.' },
            per_level: [{ id_level: 4, nama_level: 'OFFICER', jumlah: 2, cukup_untuk_kurva: true, rata_kpi: null, standar_deviasi_kpi: null, rata_kompetensi: 52.4, standar_deviasi_kompetensi: 36.5, cukup_untuk_kurva: false }],
            talent_highlights: { top_performers: [], high_potential: [], need_improvement: [] },
            grade: [],
            histogram: [],
        });
        const wrapper = mount(DistribusiPage, { global: { stubs: uiStubs } });
        await flushPromises();
        await flushPromises();
        const teks = wrapper.text();

        for (const karangan of ['Siti Wulandari', 'Andi Rahman', 'Karyawan #4021', '620', 'Istimewa (Top Performer)']) {
            expect(teks).not.toContain(karangan);
        }
        expect(wrapper.findAll('[data-uji="talenta-kosong"]')).toHaveLength(3);
        // Kurva per level ditahan: datanya kurang dari ambang server.
        expect(wrapper.find('[data-uji="kurva-level-ditahan"]').exists()).toBe(true);
    });
});
