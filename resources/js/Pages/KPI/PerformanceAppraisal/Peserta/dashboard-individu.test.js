import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import DashboardIndividuPage from './dashboard-individu.vue';

const paApiMock = vi.hoisted(() => ({
    hasilSaya: vi.fn(),
    riwayatSaya: vi.fn(),
}));

vi.mock('../api/paApi.js', () => ({ default: paApiMock }));

const uiStubs = {
    HcisButton: {
        props: ['label', 'loading'],
        emits: ['click'],
        template: '<button @click="$emit(\'click\')">{{ label }}</button>',
    },
    HcisHeader: {
        props: ['title', 'loading'],
        template: '<header :data-title="title"><slot name="actions" /><slot name="tabs" /></header>',
    },
    HcisTabs: {
        props: ['tabs', 'modelValue'],
        emits: ['update:modelValue'],
        template: '<nav><button v-for="tab in tabs" :key="tab.key" :data-tab="tab.key" @click="$emit(\'update:modelValue\', tab.key)">{{ tab.label }}</button></nav>',
    },
    PaSayaResultPanel: {
        props: ['data', 'loading', 'error'],
        emits: ['retry', 'cycle-change'],
        template: '<div class="result-panel">{{ data?.tersedia ? "tersedia" : error || "belum" }}</div>',
    },
    PaSayaHistoryPanel: {
        props: ['data', 'loading', 'error'],
        emits: ['retry', 'view-cycle'],
        template: '<div class="history-panel">{{ data?.jumlah_periode ?? error ?? "belum" }}<button class="btn-view-cycle" @click="$emit(\'view-cycle\', 123)">view</button></div>',
    },
};

beforeEach(() => {
    vi.clearAllMocks();
    paApiMock.hasilSaya.mockResolvedValue({ tersedia: true, nilai_akhir: 85.5, hasil: {}, kompetensi: [], catatan: [] });
    paApiMock.riwayatSaya.mockResolvedValue({ periode: [{ cycle_id: 1, nama_cycle: '2026-H1', nilai_akhir: 85.5 }], jumlah_periode: 1, rata_rata: 85.5, terbaik: 85.5, perubahan_terakhir: null });
});

afterEach(() => {
    window.history.replaceState(null, '', '/');
});

function mountPage() {
    return mount(DashboardIndividuPage, { global: { stubs: uiStubs } });
}

describe('Dashboard PA Individu page', () => {
    it('memuat data hasil dan riwayat saat halaman dibuka', async () => {
        const wrapper = mountPage();
        await flushPromises();

        expect(paApiMock.hasilSaya).toHaveBeenCalledWith(null);
        expect(paApiMock.riwayatSaya).toHaveBeenCalledTimes(1);
        expect(wrapper.find('[data-title="Dashboard PA Individu"]').exists()).toBe(true);
        expect(wrapper.find('.result-panel').text()).toContain('tersedia');
    });

    it('berpindah tab antara Hasil Penilaian dan Riwayat Cycle', async () => {
        const wrapper = mountPage();
        await flushPromises();

        await wrapper.find('[data-tab="riwayat"]').trigger('click');
        expect(wrapper.find('.history-panel').exists()).toBe(true);
        expect(window.location.search).toBe('?tab=riwayat');

        await wrapper.find('[data-tab="hasil"]').trigger('click');
        expect(wrapper.find('.result-panel').exists()).toBe(true);
    });

    it('membuka tab riwayat langsung dari query parameter URL', async () => {
        window.history.replaceState(null, '', '/performance-appraisal/dashboard-individu?tab=riwayat');
        const wrapper = mountPage();
        await flushPromises();

        expect(wrapper.find('.history-panel').exists()).toBe(true);
    });

    it('tombol view cycle di panel riwayat membuka hasil cycle tersebut di tab hasil', async () => {
        const wrapper = mountPage();
        await flushPromises();

        await wrapper.find('[data-tab="riwayat"]').trigger('click');
        paApiMock.hasilSaya.mockClear();

        await wrapper.find('.btn-view-cycle').trigger('click');
        await flushPromises();

        expect(wrapper.find('.result-panel').exists()).toBe(true);
        expect(paApiMock.hasilSaya).toHaveBeenCalledWith(123);
    });
});
