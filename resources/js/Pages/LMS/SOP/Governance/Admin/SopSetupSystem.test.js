import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import SopSetupSystem from './SopSetupSystem.vue';

vi.mock('axios');

const MOCK_SETTINGS = {
    reader_dwell_min_dtk: 5,
    video_ambang_persen: 85,
    default_reminder_hari: 3,
    mover_auto_assign: true,
    mover_auto_release: false,
    penjadwalan_async_ambang: 30,
    sop_reconcile_chunk: 10,
};

const MOCK_SCHEDULERS = [
    { Command: 'sop:rule-scan', Cron_Expression: '10 0 * * *', Timezone: 'Asia/Jakarta', Flag_Aktif: 'Y' },
    { Command: 'sop:reminder-scan', Cron_Expression: '30 0 * * *', Timezone: 'Asia/Jakarta', Flag_Aktif: 'Y' },
];

function mockApi(overrides = {}) {
    axios.get = vi.fn(() =>
        Promise.resolve({
            data: {
                success: true,
                result: {
                    settings: { ...MOCK_SETTINGS, ...(overrides.settings || {}) },
                    schedulers: overrides.schedulers ?? MOCK_SCHEDULERS,
                },
            },
        })
    );
    axios.put = vi.fn(() => Promise.resolve({ data: { success: true } }));
    axios.post = vi.fn(() => Promise.resolve({ data: { success: true, result: { run_id: 'run-1' } } }));
}

function mountPage() {
    return mount(SopSetupSystem, {
        global: {
            stubs: {
                HcisHeader: {
                    template:
                        '<header class="hcis-header-stub"><slot name="badge"/><slot name="actions"/><h1>{{ title }}</h1><slot name="tabs"/></header>',
                    props: ['title', 'kicker', 'description', 'icon', 'sentiment', 'size', 'animated'],
                },
            },
        },
    });
}

describe('SopSetupSystem.vue', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        mockApi();
    });

    it('loads settings and schedulers from the setup-system endpoint in one call', async () => {
        const wrapper = mountPage();
        await flushPromises();

        expect(axios.get).toHaveBeenCalledTimes(1);
        expect(axios.get).toHaveBeenCalledWith('/api/v1/sop/admin/setup-system/settings');
        expect(wrapper.vm.settings.video_ambang_persen).toBe(85);
        expect(wrapper.vm.schedulers).toHaveLength(2);
    });

    it('renders the global policy tab first', async () => {
        const wrapper = mountPage();
        await flushPromises();

        expect(wrapper.vm.tabItems.map((tab) => tab.key)).toEqual(['settings', 'automation']);
        expect(wrapper.text()).toContain('Kebijakan Global Dokumen');
        expect(wrapper.text()).toContain('Dwell Minimum Reader');
        expect(wrapper.text()).toContain('Ambang Video Selesai');
        expect(wrapper.text()).toContain('Auto Assign Mover');
        expect(wrapper.text()).toContain('Auto Release');
    });

    /* Halaman ini TIDAK boleh mengambil alih aturan penugasan atau riwayat
       eksekusi — keduanya tetap milik halaman Otomasi SOP. */
    it('does not carry the assignment rules or run history', async () => {
        const wrapper = mountPage();
        await flushPromises();

        expect(wrapper.vm.rules).toBeUndefined();
        expect(wrapper.vm.runs).toBeUndefined();
        expect(wrapper.text()).not.toContain('Daftar Aturan');
        expect(wrapper.text()).not.toContain('Riwayat Eksekusi Otomasi');
    });

    it('shows scheduler rows resolved by command on the automation tab', async () => {
        const wrapper = mountPage();
        await flushPromises();

        wrapper.vm.activeTab = 'automation';
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Otomasi Terjadwal System');
        expect(wrapper.text()).toContain('10 0 * * *');
        expect(wrapper.text()).toContain('30 0 * * *');
        expect(wrapper.text()).not.toContain('Belum dikonfigurasi');
        expect(wrapper.vm.schedulerAktifCount).toBe(2);
    });

    /* Regresi: job pertama dulu bernama type 'apply' / command 'sop:auto-apply'.
       Tak satu pun ada — runNow membalas 422 dan kartunya selalu berbunyi
       "Belum dikonfigurasi" karena N_HRIS_Scheduler_Configs memakai
       'sop:rule-scan'. */
    it('binds every automation job to a command that actually exists', async () => {
        const wrapper = mountPage();
        await flushPromises();

        const known = MOCK_SCHEDULERS.map((row) => row.Command);
        wrapper.vm.automationJobs.forEach((job) => {
            expect(known).toContain(job.command);
        });
        expect(wrapper.vm.automationJobs.map((job) => job.type)).toEqual(['rule-scan', 'reminder']);
    });

    it('flags a scheduler that has no configuration row', async () => {
        mockApi({ schedulers: [MOCK_SCHEDULERS[1]] });
        const wrapper = mountPage();
        await flushPromises();

        wrapper.vm.activeTab = 'automation';
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Belum dikonfigurasi');
        expect(wrapper.vm.schedulerAktifCount).toBe(1);
    });

    it('omits the system-managed async threshold when saving', async () => {
        const wrapper = mountPage();
        await flushPromises();

        await wrapper.vm.saveSettings();

        const [url, payload] = axios.put.mock.calls[0];
        expect(url).toBe('/api/v1/sop/admin/setup-system/settings');
        expect(payload).not.toHaveProperty('penjadwalan_async_ambang');
        expect(payload.video_ambang_persen).toBe(85);
    });

    /* Pemicu manual tetap memanggil rute milik controller Kebijakan; rute itu
       bergerbang dua kunci sehingga halaman ini boleh memanggilnya. */
    it('triggers a run through the dual-gated kebijakan route', async () => {
        const wrapper = mountPage();
        await flushPromises();

        await wrapper.vm.runNow('reminder');

        expect(axios.post).toHaveBeenCalledWith('/api/v1/sop/admin/kebijakan/runs/reminder');
    });
});
