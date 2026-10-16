import { describe, it, expect, vi, beforeEach } from 'vitest';
import { shallowMount, flushPromises } from '@vue/test-utils';

const get = vi.fn();

vi.mock('axios', () => ({ default: { get: (...args) => get(...args) } }));
vi.mock('element-plus', () => ({ ElMessage: { error: vi.fn(), success: vi.fn() } }));

import PermitForm from './PermitForm.vue';

/**
 * Bentuk NYATA balasan /transaksipermit-master.
 *
 * ResponseHelper::success() membungkus muatannya di bawah `result`. Membacanya
 * dari akar tetap memberi `success: true`, jadi tak ada error, tak ada pesan,
 * tak ada gejala — hanya semua daftar master jadi kosong dan pilihan "Jenis
 * permit" hilang dari layar.
 */
const BALASAN_SERVER = {
    data: {
        success: true,
        status: 200,
        message: 'Berhasil',
        result: {
            plat_codes: ['BA', 'B'],
            vehicle_types: [{ Id: 1, Nama_Tipe_Kendaraan: 'Mobil' }],
            models: [{ Id: 1, Id_Master_Tipe: 1, Nama_Model_Kendaraan: 'Avanza' }],
            colors: [{ Id: 1, Nama_Warna: 'Hitam' }],
            kategoris: [
                { Id: 1, Nama_Kategori: 'HIJAU', Deskripsi_Akses: 'Akses area hijau' },
                { Id: 2, Nama_Kategori: 'MERAH', Deskripsi_Akses: 'Akses area merah' },
            ],
        },
    },
};

async function mountForm() {
    get.mockResolvedValue(BALASAN_SERVER);

    const wrapper = shallowMount(PermitForm, {
        props: {
            config: { enabled: true },
            approvalPolicy: { needs_approval: true, allow_submit_without_flow: false },
            approverFlows: [{ kode_karyawan: 'A1' }],
            kuota: { terpakai: 0, maks: 2 },
        },
    });
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    vi.clearAllMocks();
});

describe('PermitForm — memuat master data', () => {
    it('membaca daftar dari amplop result, bukan dari akar balasan', async () => {
        const wrapper = await mountForm();

        expect(wrapper.vm.master.kategoris).toHaveLength(2);
        expect(wrapper.vm.master.plat_codes).toHaveLength(2);
        expect(wrapper.vm.master.vehicle_types).toHaveLength(1);
        expect(wrapper.vm.master.models).toHaveLength(1);
        expect(wrapper.vm.master.colors).toHaveLength(1);
    });

    it('menyajikan pilihan jenis permit ke pemilih kategori', async () => {
        const wrapper = await mountForm();

        // HcisRadio dengan options kosong tidak merender apa pun — persis
        // gejala "jenis permit tidak muncul".
        expect(wrapper.vm.categoryOptions.map((o) => o.label)).toEqual(['HIJAU', 'MERAH']);
    });

    it('tidak meninggalkan formulir dalam keadaan memuat', async () => {
        const wrapper = await mountForm();

        expect(wrapper.vm.masterLoading).toBe(false);
    });
});
