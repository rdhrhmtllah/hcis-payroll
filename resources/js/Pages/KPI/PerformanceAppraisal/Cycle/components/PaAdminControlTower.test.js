import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PaAdminControlTower from './PaAdminControlTower.vue';

const paApiMock = vi.hoisted(() => ({
    ringkasanSiklus: vi.fn(),
    monitoringSiklus: vi.fn(),
    distribusiSiklus: vi.fn(),
    riwayatAdmin: vi.fn(),
}));

vi.mock('../../api/paApi.js', () => ({ default: paApiMock }));

const cycle = {
    Id_PA_Cycle: 657,
    Kode_Cycle: 'PA-DEMO-FE',
    Nama_Cycle: 'Siklus Demo Frontend',
    Status_Cycle: 'CLOSED',
    Periode_Dinilai_Dari: '2026-01-01',
    Periode_Dinilai_Sampai: '2026-06-30',
};

const resultSummary = {
    siklus: cycle,
    peserta: { total: 10, diikutkan: 8, tertunda: 1, dikecualikan: 2 },
    penugasan: { total: 8, selesai: 4, terlambat: 1, penyelesaian_persen: 50, per_status: { SELESAI: 4, BERJALAN: 4 } },
    hasil: { total: 10, terbit: 2, tertahan: 1, per_status: { TERBIT: 2, TERTAHAN: 1 } },
    per_divisi: [{ id_divisi: 1, nama_divisi: 'PRODUCTION', total: 8, selesai: 4, terlambat: 1, penyelesaian_persen: 50 }],
};

const resultMonitoring = {
    peta_panas: { status: ['BELUM_MULAI', 'BERJALAN', 'SELESAI'], baris: [{ divisi: 'PRODUCTION', sel: [1, 2, 5], total: 8 }] },
    terlambat: { baris: [], halaman: 1, per_halaman: 25, total: 0, total_halaman: 0, dipangkas: false },
    pilihan_saringan: { divisi: [], level: [], kategori_penilai: [], status_penugasan: [] },
};

const resultDistribution = {
    asal_angka: { per_status: { TERBIT: 2 }, dinilai: 2, tertahan_tanpa_nilai: 1, catatan: 'Angka dari hasil yang sudah bernilai.' },
    keseluruhan: { jumlah: 2, rata_rata: 77, standar_deviasi: 7.78, terendah: 71.5, tertinggi: 82.5, cukup_untuk_kurva: false, alasan_kurva_ditahan: 'Belum cukup data.' },
    grade: [{ kode_grade: 'B', jumlah: 2, persen: 100 }],
    kuadran: [],
    histogram: [{ dari: 70, sampai: 75, jumlah: 1 }],
};

const uiStubs = {
    HcisBadge: { props: ['label'], template: '<span class="badge-stub">{{ label }}</span>' },
    HcisButton: { props: ['label', 'disabled'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>' },
    HcisCard: { props: ['loading'], template: '<article><slot name="header" /><slot /><slot name="footer" /></article>' },
    HcisChart: { props: ['title'], template: '<div class="chart-stub">{{ title }}</div>' },
    HcisFilterBar: { emits: ['update:search', 'chip-click', 'reset'], template: '<div class="filter-stub" />' },
    HcisIcon: { template: '<i />' },
    HcisPagination: { emits: ['current-change'], template: '<button class="pagination-stub" @click="$emit(\'current-change\', 2)">next</button>' },
    HcisProgress: { template: '<div class="progress-stub" />' },
    HcisResultState: { props: ['title', 'description'], template: '<div class="result-stub">{{ title }} {{ description }}</div>' },
    HcisSelect: { props: ['modelValue'], emits: ['update:modelValue'], template: '<div class="select-stub"><slot /></div>' },
    HcisSelectOption: { props: ['label'], template: '<span class="option-stub">{{ label }}</span>' },
    HcisSkeleton: { template: '<div class="skeleton-stub" />' },
    HcisStatCard: { props: ['label', 'value'], template: '<div class="stat-stub">{{ label }} {{ value }}</div>' },
    HcisTable: { props: ['rows'], template: '<div class="table-stub">{{ rows.length }} rows</div>' },
    HcisTabs: { props: ['modelValue', 'tabs'], emits: ['update:modelValue'], template: '<div class="tabs-stub"><button v-for="tab in tabs" :key="tab.key" @click="$emit(\'update:modelValue\', tab.key)">{{ tab.label }}</button></div>' },
};

beforeEach(() => {
    vi.clearAllMocks();
    paApiMock.ringkasanSiklus.mockResolvedValue(resultSummary);
    paApiMock.monitoringSiklus.mockResolvedValue(resultMonitoring);
    paApiMock.distribusiSiklus.mockResolvedValue(resultDistribution);
    paApiMock.riwayatAdmin.mockResolvedValue({ baris: [], total: 0, pilihan_saringan: { siklus: [], tahun: [], grade: [], status_hasil: [], status_peserta: ['DIIKUTKAN', 'DIKECUALIKAN', 'TERTUNDA'] }, tren: [] });
});

describe('PaAdminControlTower', () => {
    it('memuat ringkasan, monitoring, dan distribusi dari cycle yang dipilih', async () => {
        const wrapper = mount(PaAdminControlTower, { props: { cycles: [cycle] }, global: { stubs: uiStubs } });
        await flushPromises();

        expect(paApiMock.ringkasanSiklus).toHaveBeenCalledWith('657');
        expect(paApiMock.distribusiSiklus).toHaveBeenCalledWith('657', expect.objectContaining({ id_level: '', id_divisi: '' }));
        expect(wrapper.text()).toContain('Kesehatan siklus dalam satu pandangan');
        expect(wrapper.text()).toContain('PRODUCTION');
    });

    it('membedakan daftar siklus kosong dari loading', async () => {
        const wrapper = mount(PaAdminControlTower, { props: { cycles: [], cyclesLoading: false }, global: { stubs: uiStubs } });
        await flushPromises();

        expect(wrapper.text()).toContain('Belum ada siklus untuk dianalisis');
        expect(wrapper.find('.skeleton-stub').exists()).toBe(false);
    });

    it('sorotan talenta memakai nama grade dari laporan dan tidak mengarang grade atau kuadran', async () => {
        paApiMock.distribusiSiklus.mockResolvedValue({
            ...resultDistribution,
            grade: [{ kode_grade: 'B_PLUS', nama_grade: 'Sangat Baik', jumlah: 1, persen: 100 }],
            talent_highlights: {
                top_performers: [{ participant_id: 1, nama: 'Ani', kode_karyawan: 'A1', skor: 88, grade: 'B_PLUS' }],
                high_potential: [{ participant_id: 2, nama: 'Budi', kode_karyawan: 'A2', skor: 80, nilai_kompetensi: 0, kuadran: null }],
                need_improvement: [{ participant_id: 3, nama: 'Cici', kode_karyawan: 'A3', skor: 50, grade: null }],
            },
        });
        const wrapper = mount(PaAdminControlTower, { props: { cycles: [cycle] }, global: { stubs: uiStubs } });
        await flushPromises();

        const lencana = wrapper.findAll('.badge-stub').map((b) => b.text());
        expect(lencana).toContain('Sangat Baik');
        expect(lencana).not.toContain('A');
        expect(lencana).not.toContain('D');
        expect(lencana).not.toContain('Future Star');
        expect(wrapper.text()).not.toContain('88%');
    });
});
