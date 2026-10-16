import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import SayaPage from './saya.vue';

const paApiMock = vi.hoisted(() => ({
    ringkasanSaya: vi.fn(),
    assignmentSaya: vi.fn(),
    hasilSaya: vi.fn(),
    riwayatSaya: vi.fn(),
    kemajuanPengirimanSaya: vi.fn(),
    kirimSemuaPenilaianSaya: vi.fn(),
}));

const routerMock = vi.hoisted(() => ({
    visit: vi.fn(),
}));

vi.mock('../api/paApi.js', () => ({ default: paApiMock }));
vi.mock('@inertiajs/vue3', () => ({ router: routerMock }));

const uiStubs = {
    HcisButton: {
        props: ['label', 'loading'],
        emits: ['click'],
        template: '<button @click="$emit(\'click\')">{{ label }}</button>',
    },
    HcisHeader: {
        props: ['title', 'loading'],
        template: '<header :data-title="title"><slot name="actions" /><slot name="console" /><slot name="tabs" /></header>',
    },
    HcisStatusBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
    HcisTabs: {
        props: ['tabs', 'modelValue'],
        emits: ['update:modelValue'],
        template: '<nav><button v-for="tab in tabs" :key="tab.key" :data-tab="tab.key" @click="$emit(\'update:modelValue\', tab.key)">{{ tab.label }}</button></nav>',
    },
    HcisStatCard: {
        props: ['label', 'value'],
        emits: ['click'],
        template: '<button class="stat-card" @click="$emit(\'click\')">{{ label }} {{ value }}</button>',
    },
    PaSayaSummaryPanel: {
        props: ['data', 'loading', 'error'],
        emits: ['switch-tab'],
        template: '<div class="summary-panel">{{ data?.karyawan?.nama || error || (loading ? "loading" : "empty") }}<button class="btn-to-hasil" @click="$emit(\'switch-tab\', \'hasil\')">hasil</button></div>',
    },
    PaSayaWizardPenilaian: {
        props: ['kategoriAwal'],
        emits: ['terkirim'],
        template: `<div class="wizard-panel" :data-kategori="kategoriAwal"><button class="wizard-kirim" @click="$emit('terkirim')">kirim</button></div>`,
    },
};

const summary = {
    karyawan: { kode_karyawan: 'A4', nama: 'Aji' },
    total_assignment: '2',
    belum_dimulai: '1',
    sedang_dikerjakan: '0',
    sudah_selesai: '1',
    deadline_dekat: '1',
    terlambat: '0',
    progres_persen: 50,
    ambang_dekat_hari: 3,
};

beforeEach(() => {
    vi.clearAllMocks();
    paApiMock.ringkasanSaya.mockResolvedValue(summary);
    paApiMock.assignmentSaya.mockResolvedValue({ assignment: [], halaman: 1, per_halaman: 25, total: 0, total_halaman: 1, dipangkas: false });
    paApiMock.hasilSaya.mockResolvedValue({ tersedia: false, pesan: 'Belum terbit.', hasil: null, kompetensi: [], catatan: [] });
    paApiMock.riwayatSaya.mockResolvedValue({ periode: [], jumlah_periode: 0, rata_rata: null, terbaik: null, perubahan_terakhir: null });
    paApiMock.kemajuanPengirimanSaya.mockResolvedValue({ total: 0, sisa: 0, terkumpul: 0, bisa_kirim: false, sudah_terkirim_semua: false, cycle_id: 1 });
});

afterEach(() => {
    window.history.replaceState(null, '', '/');
});

function mountPage() {
    return mount(SayaPage, { global: { stubs: uiStubs } });
}

describe('Performance Appraisal Saya page', () => {
    it('mengorkestrasi endpoint dan menampilkan ringkasan awal', async () => {
        const wrapper = mountPage();
        await flushPromises();

        expect(paApiMock.ringkasanSaya).toHaveBeenCalledWith(null);
        expect(paApiMock.assignmentSaya).toHaveBeenCalledWith({ halaman: 1, per_halaman: 25 });
        expect(wrapper.find('.summary-panel').text()).toContain('Aji');
        expect(wrapper.find('[data-title="Appraisal Individu"]').exists()).toBe(true);
    });

    it('berpindah workspace antara ringkasan dan tugas penilaian', async () => {
        const wrapper = mountPage();
        await flushPromises();

        await wrapper.find('[data-tab="assignment"]').trigger('click');
        expect(wrapper.find('.wizard-panel').exists()).toBe(true);
        expect(window.location.search).toBe('?tab=tugas-penilaian');

        await wrapper.find('[data-tab="ringkasan"]').trigger('click');
        expect(wrapper.find('.summary-panel').exists()).toBe(true);
    });

    it('pindah ke tab hasil atau riwayat mengarahkan ke dashboard individu', async () => {
        const wrapper = mountPage();
        await flushPromises();

        await wrapper.find('.btn-to-hasil').trigger('click');
        expect(routerMock.visit).toHaveBeenCalledWith('/performance-appraisal/dashboard-individu');
    });

    it('membuka URL dengan tab hasil otomatis mengarahkan ke dashboard individu', async () => {
        window.history.replaceState(null, '', '/performance-appraisal/saya?tab=hasil');
        mountPage();
        await flushPromises();

        expect(routerMock.visit).toHaveBeenCalledWith('/performance-appraisal/dashboard-individu', { replace: true });
    });

    it('membuka tab dan kategori dari URL kepulangan HC Learn', async () => {
        window.history.replaceState(null, '', '/performance-appraisal/saya?tab=tugas-penilaian&kategori=SELF');
        const wrapper = mountPage();
        await flushPromises();

        expect(wrapper.find('.summary-panel').exists()).toBe(false);
        expect(wrapper.find('.wizard-panel').attributes('data-kategori')).toBe('SELF');
    });

    it('tab Tugas Penilaian menampilkan centang bila semua sudah terkirim', async () => {
        const tab = vi.fn();
        paApiMock.kemajuanPengirimanSaya.mockResolvedValue([
            { cycle_id: 1, total: 3, terkumpul: 0, terkirim: 3, sisa: 0, bisa_kirim: false, sudah_terkirim_semua: true },
        ]);
        const wrapper = mount(SayaPage, {
            global: {
                stubs: {
                    ...uiStubs,
                    HcisTabs: { props: ['tabs'], setup: (p) => { tab.mockImplementation(() => p.tabs); return () => null; } },
                },
            },
        });
        await flushPromises();

        const tugas = tab().find((t) => t.key === 'assignment');
        expect(tugas.badge).toBe('✓');
        expect(tugas.badgeVariant).toBe('success');
        wrapper.unmount();
    });

    it('tab tak dikenal di URL jatuh ke Ringkasan', async () => {
        window.history.replaceState(null, '', '/performance-appraisal/saya?tab=entah');
        const wrapper = mountPage();
        await flushPromises();

        expect(wrapper.find('.summary-panel').exists()).toBe(true);
    });

    it('sesudah wizard mengirim, seluruh halaman dan kemajuan pengiriman dimuat ulang', async () => {
        const wrapper = mountPage();
        await flushPromises();
        await wrapper.find('[data-tab="assignment"]').trigger('click');

        paApiMock.ringkasanSaya.mockClear();
        paApiMock.kemajuanPengirimanSaya.mockClear();

        await wrapper.find('.wizard-kirim').trigger('click');
        await flushPromises();

        expect(paApiMock.ringkasanSaya).toHaveBeenCalledTimes(1);
        expect(paApiMock.kemajuanPengirimanSaya).toHaveBeenCalledTimes(1);
    });
});
