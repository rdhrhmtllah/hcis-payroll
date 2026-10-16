import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import paApi from '../../api/paApi.js';
import PaPengaturanKategoriPenilai from './PaPengaturanKategoriPenilai.vue';

vi.mock('../../api/paApi.js', () => ({
    default: { tataUrutanKategoriPenilai: vi.fn(), setelBolehKalibrasiKategori: vi.fn() },
}));

const kategori = [
    { id: 4, kode: 'SELF', nama: 'Diri Sendiri', urutan_pengisian: 1, boleh_kalibrasi: false },
    { id: 1, kode: 'ATASAN', nama: 'Atasan', urutan_pengisian: 2, boleh_kalibrasi: true },
    { id: 2, kode: 'PEER', nama: 'Rekan', urutan_pengisian: null, boleh_kalibrasi: false },
];

describe('PaPengaturanKategoriPenilai', () => {
    beforeEach(() => vi.clearAllMocks());

    it('menampilkan urutan server; tanpa urutan di belakang', () => {
        const w = mount(PaPengaturanKategoriPenilai, { props: { kategori } });
        const nama = w.findAll('.pa-atur-kategori__nama').map((n) => n.text());

        expect(nama).toEqual(['Diri Sendiri', 'Atasan', 'Rekan']);
    });

    it('menggeser urutan mengirim DAFTAR LENGKAP ke server', async () => {
        paApi.tataUrutanKategoriPenilai.mockResolvedValue([]);
        const w = mount(PaPengaturanKategoriPenilai, { props: { kategori } });

        await w.vm.geser(1, -1);
        await flushPromises();

        expect(paApi.tataUrutanKategoriPenilai).toHaveBeenCalledWith(['ATASAN', 'SELF', 'PEER']);
        expect(w.emitted('berubah')).toHaveLength(1);
    });

    it('menyalakan kalibrasi memanggil endpoint kategori itu', async () => {
        paApi.setelBolehKalibrasiKategori.mockResolvedValue({});
        const w = mount(PaPengaturanKategoriPenilai, { props: { kategori } });

        await w.vm.setelKalibrasi(kategori[0], true);
        await flushPromises();

        expect(paApi.setelBolehKalibrasiKategori).toHaveBeenCalledWith(4, true);
    });

    it('galat server ditampilkan apa adanya dan tidak memancarkan berubah', async () => {
        paApi.tataUrutanKategoriPenilai.mockRejectedValue(
            new Error('Anda tidak punya akses ke master kategori-penilai.'),
        );
        const w = mount(PaPengaturanKategoriPenilai, { props: { kategori } });

        await w.vm.geser(0, 1);
        await flushPromises();

        expect(w.text()).toContain('Anda tidak punya akses ke master kategori-penilai.');
        expect(w.emitted('berubah')).toBeUndefined();
    });

    it('menyebut bahwa perubahan berlaku seketika', () => {
        const w = mount(PaPengaturanKategoriPenilai, { props: { kategori } });
        expect(w.text()).toContain('berlaku seketika');
    });
});
