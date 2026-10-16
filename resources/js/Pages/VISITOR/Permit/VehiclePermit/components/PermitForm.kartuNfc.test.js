import { describe, it, expect, vi, beforeEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import axios from 'axios';

vi.mock('axios', () => ({
    default: { get: vi.fn(() => Promise.resolve({ data: { success: true, result: {} } })) },
}));
vi.mock('element-plus', () => ({ ElMessage: { error: vi.fn(), success: vi.fn() } }));

import PermitForm from './PermitForm.vue';

function mountForm(kartuNfc) {
    return shallowMount(PermitForm, {
        props: {
            config: { enabled: true },
            approvalPolicy: { needs_approval: true, allow_submit_without_flow: false },
            approverFlows: [{ kode_karyawan: 'A1' }],
            kuota: {},
            kartuNfc,
        },
    });
}

beforeEach(() => {
    vi.clearAllMocks();
});

describe('PermitForm — syarat kartu NFC', () => {
    it('kartu belum siap: form diganti panel arahan IT support dan master data tidak dimuat', () => {
        const wrapper = mountForm({
            boleh: false,
            status: 'BELUM_PAIRING',
            pesan: 'ID Card Anda belum terdaftar. Silakan hubungi IT support terdekat untuk pairing ID Card Anda.',
        });

        expect(wrapper.find('[data-testid="permit-kartu-nfc-blokir"]').exists()).toBe(true);
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.vm.judulKartuNfc).toBe('ID Card belum terdaftar');
        expect(axios.get).not.toHaveBeenCalled();
    });

    it('kartu terdaftar: form tampil seperti biasa', () => {
        const wrapper = mountForm({ boleh: true, status: 'TERDAFTAR', pesan: null });

        expect(wrapper.find('[data-testid="permit-kartu-nfc-blokir"]').exists()).toBe(false);
        expect(axios.get).toHaveBeenCalled();
    });

    it('istilah yang tampil ke pemakai adalah "ID Card", bukan "NFC"', () => {
        const wrapper = mountForm({ boleh: false, status: 'KARTU_GANDA', pesan: 'x' });
        const teks = wrapper.find('[data-testid="permit-kartu-nfc-blokir"]').html();

        expect(wrapper.vm.judulKartuNfc).toBe('ID Card tercatat ganda');
        expect(teks).not.toMatch(/NFC/);
    });

    it('tanpa prop kartu (halaman lama): form tetap tampil, server yang menjaga', () => {
        const wrapper = mountForm(null);

        expect(wrapper.vm.kartuNfcTerkunci).toBe(false);
    });
});
