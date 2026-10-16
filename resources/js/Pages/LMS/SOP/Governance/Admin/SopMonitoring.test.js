import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import SopMonitoring from './SopMonitoring.vue';
import { sumberMonitoring } from './monitoring/sumberPalet.js';

vi.mock('axios');

function setUrl(search) {
    window.history.replaceState({}, '', `/sop/admin/monitoring${search}`);
}

async function pasang(search = '') {
    setUrl(search);
    axios.get = vi.fn(() => Promise.resolve({ data: { success: true, result: [] } }));

    const wrapper = mount(SopMonitoring, {
        global: {
            stubs: {
                HeaderKpi: true,
                TabRingkasan: true,
                TabKaryawan: true,
                TabPaket: true,
                CommandPalette: true,
                SopExportStudio: true,
            },
        },
    });
    await flushPromises();
    return wrapper;
}

describe('SopMonitoring — deretan tab', () => {
    beforeEach(() => setUrl(''));

    it('hanya menampilkan tiga tab', async () => {
        const wrapper = await pasang();

        /* Ditanyakan ke KONTRAK tab, bukan ke markup: deretannya kini digambar
           HcisTabs, jadi kelas .rail-seg-btn milik shell lama tak pernah ada di
           DOM lagi dan findAll() mengembalikan [] — merah yang menyamar jadi
           "tab hilang" padahal tabnya ada dan tergambar. */
        const label = wrapper.vm.tabItems.map((t) => t.label);
        expect(label).toHaveLength(3);
        expect(label.join(' ')).toContain('Ringkasan');
        expect(label.join(' ')).toContain('Karyawan');
        expect(label.join(' ')).toContain('Paket');
    });

    it('tidak lagi menawarkan tab Dokumen maupun Analitik', async () => {
        const teks = (await pasang()).vm.tabItems.map((t) => t.label).join(' ');

        expect(teks).not.toContain('Dokumen');
        expect(teks).not.toContain('Analitik');
    });
});

describe('SopMonitoring — pagar URL lama', () => {
    beforeEach(() => setUrl(''));

    it.each(['?tab=dokumen', '?tab=analitik', '?tab=entahapa'])(
        'jatuh ke ringkasan untuk %s, bukan halaman kosong',
        async (search) => {
            const wrapper = await pasang(search);

            expect(wrapper.vm.tab).toBe('ringkasan');
            expect(wrapper.findComponent({ name: 'TabRingkasan' }).exists()).toBe(true);
        },
    );

    it('menulis ulang URL supaya tidak menyesatkan', async () => {
        await pasang('?tab=dokumen');

        expect(window.location.search).not.toContain('dokumen');
    });

    it('tetap menghormati tab yang masih sah', async () => {
        const wrapper = await pasang('?tab=paket');

        expect(wrapper.vm.tab).toBe('paket');
    });
});

describe('sumberPalet — perintah dokumen dicabut', () => {
    it('tidak menyisakan aksi yang menunjuk tab dokumen atau analitik', () => {
        const sumber = sumberMonitoring(() => ({ paketOptions: [] }));

        const semuaItem = sumber.flatMap((s) => (Array.isArray(s.items) ? s.items : []));
        const tabDituju = semuaItem.map((i) => i.aksi && i.aksi.tab).filter(Boolean);

        expect(tabDituju).not.toContain('dokumen');
        expect(tabDituju).not.toContain('analitik');
    });
});
