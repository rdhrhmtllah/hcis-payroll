import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import TamuMonitoringPanel from './TamuMonitoringPanel.vue';

function mountPanel() {
    return mount(TamuMonitoringPanel, {
        global: {
            stubs: {
                HcisTabs: { name: 'HcisTabs', props: ['tabs', 'modelValue'], template: '<nav />' },
                TamuDashboard: true,
                TamuAllMonitoring: true,
            },
        },
    });
}

describe('TamuMonitoringPanel', () => {
    it('punya sub-tab Dashboard dan All Monitoring', () => {
        expect(mountPanel().vm.subTabs.map((t) => t.key)).toEqual(['dashboard', 'semua']);
    });

    it('membuka pada Dashboard', () => {
        expect(mountPanel().vm.subTab).toBe('dashboard');
    });

    it('merender Dashboard tamu, bukan daftar semua', () => {
        const wrapper = mountPanel();

        expect(wrapper.findComponent({ name: 'TamuDashboard' }).exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'TamuAllMonitoring' }).exists()).toBe(false);
    });

    it('berpindah ke All Monitoring saat sub-tab diganti', async () => {
        const wrapper = mountPanel();

        wrapper.vm.subTab = 'semua';
        await wrapper.vm.$nextTick();

        expect(wrapper.findComponent({ name: 'TamuAllMonitoring' }).exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'TamuDashboard' }).exists()).toBe(false);
    });

    it('tidak membawa blok milik domain permit', () => {
        // Pemisahan yang hanya di judul akan lolos bila cuma diperiksa
        // "ada isinya"; yang ditagih di sini adalah ketiadaan tetangganya.
        //
        // Diperiksa pada TEKS, bukan html(). Vue Test Utils merender nama prop
        // sebuah stub menjadi atribut — sehingga `boleh-buka-permit`, yang
        // hanya menentukan boleh-tidaknya sebuah tautan dibuka dan tidak pernah
        // terlihat siapa pun, cukup untuk memerahkan penjaga ini. Yang
        // dipertaruhkan pemisahan domain adalah apa yang DIBACA admin di layar.
        const panel = mountPanel();

        expect(panel.text()).not.toMatch(/Permit/i);
        // Komponen milik tetangga tetap ditagih secara langsung, supaya
        // pelonggaran di atas tidak membuka jalan bagi bloknya ikut terbawa.
        expect(panel.findComponent({ name: 'PermitAllMonitoring' }).exists()).toBe(false);
        expect(panel.findComponent({ name: 'PermitDashboard' }).exists()).toBe(false);
    });
});
