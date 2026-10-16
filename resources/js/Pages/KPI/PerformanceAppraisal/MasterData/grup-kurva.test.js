import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import fixture from '../api/fixtures/master-grup-kurva.json';
import paApi from '../api/paApi.js';
import GrupKurvaPage from './grup-kurva.vue';

vi.mock('../api/paApi.js', () => ({
    default: { daftarGrupKurva: vi.fn(), simpanGrupKurva: vi.fn() },
}));

vi.mock('@/components/ui', async (asli) => ({
    ...(await asli()),
    hcisMessage: { success: vi.fn(), error: vi.fn() },
}));

const stubs = {
    HcisHeader: { template: '<header><slot name="actions" /><slot name="console" /></header>' },
    HcisSelect: { template: '<div class="select-stub"><slot /></div>' },
    HcisSelectOption: { template: '<span />' },
};

async function pasang() {
    paApi.daftarGrupKurva.mockResolvedValue(JSON.parse(JSON.stringify(fixture.result)));
    const w = mount(GrupKurvaPage, { global: { stubs } });
    await flushPromises();
    return w;
}

describe('Master Grup Level Kurva', () => {
    beforeEach(() => vi.clearAllMocks());

    it('menampilkan grup dan menyorot level tanpa grup', async () => {
        const w = await pasang();

        expect(w.find('[data-uji="grup-STAF"]').text()).toContain('395 peserta');
        expect(w.text()).toContain('1 level belum masuk grup');
    });

    it('menyimpan SELURUH konfigurasi, termasuk level yang dipindah ke grup baru', async () => {
        paApi.simpanGrupKurva.mockResolvedValue(JSON.parse(JSON.stringify(fixture.result)));
        const w = await pasang();

        w.vm.baru.kode = 'mgr';
        w.vm.baru.nama = 'Manajerial';
        w.vm.tambahGrup();
        w.vm.level.find((l) => l.id === 6).grup = 'MGR';
        await w.vm.simpan();

        expect(paApi.simpanGrupKurva).toHaveBeenCalledWith([
            { kode: 'STAF', nama: 'Staff & Senior Staff', level_ids: [2, 3] },
            { kode: 'MGR', nama: 'Manajerial', level_ids: [6] },
        ]);
    });

    it('galat server ditampilkan apa adanya', async () => {
        paApi.simpanGrupKurva.mockRejectedValue(new Error('Level 2 dimasukkan ke dua grup.'));
        const w = await pasang();

        await w.vm.simpan();
        await flushPromises();

        expect(w.text()).toContain('Level 2 dimasukkan ke dua grup.');
    });
});
