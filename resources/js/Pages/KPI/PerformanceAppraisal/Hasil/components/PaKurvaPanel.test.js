import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import fixture from '../../api/fixtures/kurva-siklus.json';
import paApi from '../../api/paApi.js';
import PaKurvaPanel from './PaKurvaPanel.vue';

vi.mock('../../api/paApi.js', () => ({
    default: {
        kurvaSiklus: vi.fn(),
        kunciKurva: vi.fn(),
        bukaKurva: vi.fn(),
        ubahMetodeGrade: vi.fn(),
        aturGrupKurvaSiklus: vi.fn(),
    },
}));

vi.mock('@/components/ui', async (asli) => ({
    ...(await asli()),
    hcisMessage: { success: vi.fn(), error: vi.fn() },
}));

/* Dialog ditelepor ke body oleh kit; untuk uji cukup menggambar isinya saat terbuka. */
const stubs = {
    HcisDialog: {
        props: ['modelValue', 'title'],
        template: '<div v-if="modelValue" class="dialog-stub"><slot /><slot name="footer" /></div>',
    },
    HcisSelect: { template: '<div class="select-stub"><slot /></div>' },
    HcisSelectOption: { template: '<span />' },
};

function salin(ubah = {}) {
    const r = JSON.parse(JSON.stringify(fixture.result));
    return { ...r, ...ubah };
}

async function pasang(data) {
    paApi.kurvaSiklus.mockResolvedValue(data);
    const w = mount(PaKurvaPanel, { props: { cycleId: 7 }, global: { stubs } });
    await flushPromises();
    return w;
}

describe('PaKurvaPanel', () => {
    beforeEach(() => vi.clearAllMocks());

    it('menampilkan rata-rata, simpangan baku, dan pita grup dari server', async () => {
        const w = await pasang(salin({ terkunci: false, kunci: null }));

        expect(paApi.kurvaSiklus).toHaveBeenCalledWith(7);
        expect(w.find('[data-uji="rata"]').text()).toBe('71,51');
        expect(w.find('[data-uji="sd"]').text()).toBe('17,18');
        expect(w.text()).toContain('88,69 – < 106,00');
        expect(w.text()).toContain('≥ 106,00');
        expect(w.text()).toContain('Sementara');
    });

    it('proporsi teoretis mengikuti batas z', async () => {
        const w = await pasang(salin());
        const pita = fixture.result.grup[0].pita;

        expect(w.vm.teoretis(pita, 0)).toBeCloseTo(0.0228, 3);
        expect(w.vm.teoretis(pita, 2)).toBeCloseTo(0.3413, 3);
    });

    it('tombol kunci mati selama siklus belum CLOSED', async () => {
        const w = await pasang(salin({ terkunci: false, kunci: null, boleh_kunci: false }));

        expect(w.find('[data-uji="kunci"]').attributes('disabled')).toBeDefined();
    });

    it('tooltip kunci menyebut alasan dari server, bukan selalu "harus CLOSED"', async () => {
        const w = await pasang(salin({
            terkunci: false, kunci: null, boleh_kunci: false, boleh_kunci_dengan_alasan: false,
            alasan_kunci: 'Batas z grade belum diatur di salinan siklus ini.',
        }));
        const tombol = w.find('[data-uji="kunci"]');

        expect(tombol.attributes('disabled')).toBeDefined();
        expect(tombol.attributes('title')).toBe('Batas z grade belum diatur di salinan siklus ini.');
    });

    it('hasil belum lengkap: kunci hanya terkirim bersama alasan tertulis', async () => {
        paApi.kunciKurva.mockResolvedValue(salin());
        const w = await pasang(salin({
            terkunci: false, kunci: null, boleh_kunci: false, boleh_kunci_dengan_alasan: true,
            alasan_kunci: 'Masih ada 3 hasil belum dihitung dan 1 tertahan.',
        }));

        await w.find('[data-uji="kunci"]').trigger('click');
        expect(w.find('[data-uji="kunci-belum-lengkap"]').exists()).toBe(true);

        await w.find('[data-uji="konfirmasi-kunci"]').trigger('click');
        await flushPromises();
        expect(paApi.kunciKurva).not.toHaveBeenCalled();
        expect(w.vm.galatAlasanKunci).toContain('5 karakter');

        w.vm.alasanKunci = 'Tertahan permanen tanpa akun KPI';
        await w.find('[data-uji="konfirmasi-kunci"]').trigger('click');
        await flushPromises();
        expect(paApi.kunciKurva).toHaveBeenCalledWith(7, 'Tertahan permanen tanpa akun KPI');
    });

    it('konfirmasi kunci menyebut grup kosong yang tidak ikut dikunci', async () => {
        const data = salin({ terkunci: false, kunci: null, boleh_kunci: true });
        data.grup = [...data.grup, { ...data.grup[0], id: 99, kode: 'KOSONG', n: 0, pita: [], sebaran: {}, peringatan: [] }];
        const w = await pasang(data);

        await w.find('[data-uji="kunci"]').trigger('click');

        expect(w.find('[data-uji="grup-kosong"]').text()).toContain('KOSONG');
    });

    it('kunci hanya terkirim sesudah konfirmasi', async () => {
        paApi.kunciKurva.mockResolvedValue(salin());
        const w = await pasang(salin({ terkunci: false, kunci: null, boleh_kunci: true }));

        await w.find('[data-uji="kunci"]').trigger('click');
        expect(paApi.kunciKurva).not.toHaveBeenCalled();

        await w.find('[data-uji="konfirmasi-kunci"]').trigger('click');
        await flushPromises();
        expect(paApi.kunciKurva).toHaveBeenCalledWith(7);
        expect(w.emitted('berubah')).toHaveLength(1);
    });

    it('buka kunci menolak alasan pendek tanpa memanggil server', async () => {
        const w = await pasang(salin());

        await w.find('[data-uji="buka"]').trigger('click');
        w.vm.alasan = 'ab';
        await w.vm.buka();

        expect(paApi.bukaKurva).not.toHaveBeenCalled();
        expect(w.vm.galatAlasan).toContain('5 karakter');
    });

    it('buka kunci mati bila sudah ada yang terbit', async () => {
        const w = await pasang(salin({ sudah_terbit: 3 }));

        expect(w.find('[data-uji="buka"]').attributes('disabled')).toBeDefined();
    });

    it('buka kunci mati bila sudah ada yang dirilis ke leader (koreksi 7 Okt 2026)', async () => {
        const w = await pasang(salin({ terkunci: true, sudah_terbit: 0, sudah_dirilis: 3 }));
        const tombol = w.findAll('button').find((b) => b.text().includes('Buka Kunci Kurva'));

        expect(tombol.attributes('disabled')).toBeDefined();
        expect(tombol.attributes('title')).toContain('3 hasil sudah dirilis ke leader');
    });

    it('siklus PITA menawarkan pindah ke kurva', async () => {
        paApi.ubahMetodeGrade.mockResolvedValue(salin({ terkunci: false, kunci: null }));
        const w = await pasang(salin({ metode: 'PITA', terkunci: false, kunci: null }));

        expect(w.text()).toContain('pita nilai tetap');
        await w.find('[data-uji="pindah-kurva"]').trigger('click');
        await w.find('[data-uji="konfirmasi-pindah"]').trigger('click');
        await flushPromises();

        expect(paApi.ubahMetodeGrade).toHaveBeenCalledWith(7, 'KURVA');
    });
});
