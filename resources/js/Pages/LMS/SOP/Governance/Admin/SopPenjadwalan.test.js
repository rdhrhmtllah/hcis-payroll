import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import SopPenjadwalan from './SopPenjadwalan.vue';
import WizardPenjadwalan from './penjadwalan/WizardPenjadwalan.vue';

vi.mock('axios');

const PAKET = [
    {
        Id_SOP_Paket: 'abc',
        Nama_Paket: 'Paket A',
        Mode_Distribusi: 'PUBLIK',
        Prioritas: 'NORMAL',
        Nama_Kategori: 'HSE',
        Versi_Terakhir: 1,
    },
];

function balasan(url, terpotong) {
    if (url.includes('/options/paket')) {
        return Promise.resolve({ data: { success: true, result: PAKET, terpotong, batas: 300 } });
    }
    if (url.includes('/options/filter')) {
        return Promise.resolve({ data: { success: true, result: {} } });
    }
    return Promise.resolve({ data: { success: true, result: [], pagination: { total: 0 } } });
}

async function pasang(terpotong) {
    axios.get = vi.fn((url) => balasan(url, terpotong));

    const wrapper = mount(SopPenjadwalan, {
        global: {
            stubs: {
                HeaderKpi: true,
                QuickStat: true,
                FilterPanel: true,
                BatchCard: true,
                EditTenggatDialog: true,
                TambahKaryawanDialog: true,
                WizardPenjadwalan: true,
            },
        },
    });
    await flushPromises();
    return wrapper;
}

describe('SopPenjadwalan meneruskan penanda potongan server', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
    });

    it('meneruskan paketTerpotong=true ke wizard saat server memotong daftar', async () => {
        const wrapper = await pasang(true);

        expect(wrapper.findComponent(WizardPenjadwalan).props('paketTerpotong')).toBe(true);
    });

    it('meneruskan paketTerpotong=false saat server mengirim daftar utuh', async () => {
        const wrapper = await pasang(false);

        expect(wrapper.findComponent(WizardPenjadwalan).props('paketTerpotong')).toBe(false);
    });

    it('tetap membaca result sebagai daftar paket', async () => {
        const wrapper = await pasang(false);

        expect(wrapper.findComponent(WizardPenjadwalan).props('paketOptions')).toHaveLength(1);
    });
});
