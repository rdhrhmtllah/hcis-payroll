import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import LeaderPage from './tim.vue';

const paApiMock = vi.hoisted(() => ({
    timLeader: vi.fn(),
    detailAnggotaLeader: vi.fn(),
}));

vi.mock('../api/paApi.js', () => ({ default: paApiMock }));

const team = {
    leader: { kode_karyawan: 'A4', nama: 'Aji' },
    cycle: { cycle_id: 657, kode_cycle: 'PA-DEMO-FE', nama_cycle: 'Siklus Demo', status_cycle: 'CLOSED', periode_dinilai_dari: '2026-01-01', periode_dinilai_sampai: '2026-06-30' },
    anggota: [{ participant_id: 59657, nama: 'Amelia', terbit: true }],
    ringkasan: { total_anggota: 1, hasil_terbit: 1, hasil_tertahan: 0, anggota_punya_keterlambatan: 0, anggota_penilaian_lengkap: 1 },
    batas: { sumber_tim: 'N_HRIS_Organization_Relationship', min_penilai_untuk_tampil: 3, nilai_hanya_setelah_terbit: true, tanpa_nilai_per_penilai: true },
    tanpa_tim: false,
};

const uiStubs = {
    HcisBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
    HcisButton: { props: ['label'], emits: ['click'], template: '<button @click="$emit(\'click\')">{{ label }}</button>' },
    HcisCard: { props: ['loading'], template: '<article><slot name="header" /><slot /></article>' },
    HcisHeader: { props: ['title'], template: '<header :data-title="title"><slot name="actions" /><slot name="console" /><slot name="tabs" /></header>' },
    HcisTabs: {
        props: ['modelValue', 'tabs'],
        emits: ['update:modelValue'],
        template: '<nav><button v-for="t in tabs" :key="t.key" :data-tab="t.key" @click="$emit(\'update:modelValue\', t.key)">{{ t.label }}</button></nav>',
    },
    PaLeaderRingkasan: { props: ['tim'], emits: ['detail', 'lihat-anggota'], template: '<section class="ringkasan-stub"><button class="ke-terlambat" @click="$emit(\'lihat-anggota\', \'TERLAMBAT\')">lihat</button></section>' },
    HcisIcon: { template: '<i />' },
    HcisResultState: { props: ['title', 'description'], template: '<div class="result-state">{{ title }} {{ description }}</div>' },
    HcisStatCard: { props: ['label', 'value'], template: '<div class="stat-card">{{ label }} {{ value }}</div>' },
    HcisStatusBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
    PaLeaderMemberTable: { props: ['rows'], emits: ['detail'], template: '<div class="member-table">{{ rows.length }} members</div>' },
    PaLeaderMemberDetailDialog: { props: ['modelValue', 'detail'], template: '<div class="detail-dialog">{{ modelValue ? detail?.nama || "loading" : "closed" }}</div>' },
    HcisDialog: { props: ['modelValue', 'title'], template: '<div v-if="modelValue" class="dialog-stub"><slot /></div>' },
};

beforeEach(() => {
    vi.clearAllMocks();
    window.scrollTo = vi.fn();
    paApiMock.timLeader.mockResolvedValue(team);
    paApiMock.detailAnggotaLeader.mockResolvedValue({ nama: 'Amelia', participant_id: 59657, terbit: true });
});

describe('Performance Appraisal Leader page', () => {
    it('memuat dashboard tim dan menampilkan scope siklus dari server', async () => {
        const wrapper = mount(LeaderPage, { global: { stubs: uiStubs } });
        await flushPromises();

        expect(paApiMock.timLeader).toHaveBeenCalledWith(null);
        expect(wrapper.find('[data-title="Dashboard PA Tim"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Siklus Demo');

        // Ringkasan adalah tab pertama; daftar anggota satu klik di sebelahnya.
        expect(wrapper.find('.ringkasan-stub').exists()).toBe(true);
        await wrapper.find('[data-tab="anggota"]').trigger('click');
        expect(wrapper.text()).toContain('1 members');
    });

    it('kartu di Ringkasan membuka tab Daftar Anggota dengan saringan yang sesuai', async () => {
        const wrapper = mount(LeaderPage, { global: { stubs: uiStubs } });
        await flushPromises();
        await wrapper.find('.ke-terlambat').trigger('click');
        expect(wrapper.find('.ringkasan-stub').exists()).toBe(false);
        expect(wrapper.text()).toContain('1 members');
    });

    it('membedakan tanpa_tim dari galat', async () => {
        paApiMock.timLeader.mockResolvedValue({ leader: { nama: 'Budi' }, anggota: [], tanpa_tim: true });
        const wrapper = mount(LeaderPage, { global: { stubs: uiStubs } });
        await flushPromises();

        expect(wrapper.text()).toContain('Anda belum memiliki scope tim');
        expect(wrapper.text()).not.toContain('Dashboard tim belum dapat dimuat');
    });

    it('menampilkan pesan galat apa adanya beserta state recovery', async () => {
        paApiMock.timLeader.mockRejectedValue(Object.assign(new Error('Akses Leader belum diberikan.'), { status: 403, error_code: 'PA_LEADER_FORBIDDEN', request_id: 'req-leader-page' }));
        const wrapper = mount(LeaderPage, { global: { stubs: uiStubs } });
        await flushPromises();

        expect(wrapper.text()).toContain('Akses Leader belum diberikan.');
        expect(wrapper.text()).toContain('Dashboard tim belum dapat dimuat');
    });

    it('dapat memilih dan mengganti periode siklus penilaian', async () => {
        const teamWithMultipleCycles = {
            ...team,
            daftar_cycle: [
                { cycle_id: 657, kode_cycle: 'PA-2026-H1', nama_cycle: 'Siklus 2026 Semester 1', status_cycle: 'CLOSED', tahun: 2026 },
                { cycle_id: 658, kode_cycle: 'PA-2026-H2', nama_cycle: 'Siklus 2026 Semester 2', status_cycle: 'OPEN', tahun: 2026 },
            ],
        };
        paApiMock.timLeader.mockResolvedValue(teamWithMultipleCycles);

        const wrapper = mount(LeaderPage, { global: { stubs: uiStubs } });
        await flushPromises();

        expect(wrapper.text()).toContain('PERIODE');
        const periodTrigger = wrapper.find('.pa-period-trigger');
        expect(periodTrigger.exists()).toBe(true);
        await periodTrigger.trigger('click');

        const periodCards = wrapper.findAll('.pa-period-card');
        expect(periodCards.length).toBe(2);

        await periodCards[1].trigger('click');
        await flushPromises();

        expect(paApiMock.timLeader).toHaveBeenCalledWith(658);
    });
});

