import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import TargetPilihOrang from './TargetPilihOrang.vue';

vi.mock('axios');

function buatOrang(jumlah) {
    return Array.from({ length: jumlah }, (_, i) => ({
        Kode_Karyawan: `K${String(i).padStart(4, '0')}`,
        Nama: `Karyawan ${i}`,
        Jabatan: 'Staff',
        Departemen: 'HSE',
        Lokasi: 'Pusat',
        Sudah_Assign: false,
        Jumlah_Paket_Aktif: 0,
    }));
}

async function pasang(total) {
    axios.get = vi.fn((_url, cfg) => {
        const limit = cfg?.params?.limit ?? 24;
        return Promise.resolve({
            data: {
                success: true,
                result: buatOrang(Math.min(total, limit)),
                pagination: { total },
            },
        });
    });

    const wrapper = mount(TargetPilihOrang, {
        props: { modelValue: [], idPaket: 'abc', opsi: {} },
        global: { stubs: { 'el-select': true, 'el-option': true } },
    });
    await flushPromises();
    return wrapper;
}

function tombolPilihSemua(wrapper) {
    return wrapper.findAll('button').find((b) => b.text().includes('Pilih semua'));
}

describe('TargetPilihOrang — pilih semua hasil tersaring', () => {
    it('tidak menembak request saat hasil melampaui batas yang bisa dipilih', async () => {
        const wrapper = await pasang(250);
        axios.get.mockClear();

        await tombolPilihSemua(wrapper).trigger('click');
        await flushPromises();

        expect(axios.get).not.toHaveBeenCalled();
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('mengambil dan memilih seluruh hasil saat masih di bawah batas', async () => {
        const wrapper = await pasang(80);
        axios.get.mockClear();

        await tombolPilihSemua(wrapper).trigger('click');
        await flushPromises();

        expect(axios.get).toHaveBeenCalledTimes(1);
        expect(axios.get.mock.calls[0][1].params.limit).toBe(100);

        const terakhir = wrapper.emitted('update:modelValue').at(-1)[0];
        expect(terakhir).toHaveLength(80);
    });

    it('menggabungkan dengan pilihan yang sudah ada, bukan menimpanya', async () => {
        const wrapper = await pasang(80);
        await wrapper.setProps({ modelValue: ['SUDAH-DIPILIH'] });
        axios.get.mockClear();

        await tombolPilihSemua(wrapper).trigger('click');
        await flushPromises();

        const terakhir = wrapper.emitted('update:modelValue').at(-1)[0];
        expect(terakhir).toContain('SUDAH-DIPILIH');
        expect(terakhir).toHaveLength(81);
    });
});
