import { shallowMount } from '@vue/test-utils';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import TamuReviewModal from './TamuReviewModal.vue';

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

vi.mock('element-plus', () => ({
    ElMessage: {
        error: vi.fn(),
        success: vi.fn(),
        warning: vi.fn(),
    },
}));

const source = readFileSync(
    path.resolve(
        process.cwd(),
        'resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/TamuReviewModal.vue',
    ),
    'utf8',
);

describe('TamuReviewModal daftar anggota rombongan', () => {
    it('menampilkan manifes sebelum verifikator memutuskan', () => {
        expect(source).toContain("from '@/Pages/VISITOR/Guest/Visit/daftarAnggota'");
        expect(source).toContain('bacaDaftarAnggota(tamu.Daftar_Anggota_Json)');
        expect(source).toContain('daftar nama tidak dikumpulkan');
    });

    // DDL-2026-121: permit COKLAT mengikuti rentang estimasi kunjungan, bukan
    // satu hari kalender. Verifikator tamu tetap tidak meminta jam sendiri.
    it('menjelaskan permit COKLAT B2B mengikuti estimasi tanpa meminta jam', () => {
        expect(source).toContain('Permit COKLAT selama rentang Estimasi Masuk–Keluar');
        expect(source).not.toContain('satu hari kalender');
        expect(source).not.toContain('Jam Mulai Permit COKLAT');
        expect(source).not.toContain('Jam Selesai Permit COKLAT');
        expect(source).not.toContain('waktu_mulai_kunjungan');
        expect(source).not.toContain('waktu_selesai_kunjungan');
    });
});

/**
 * Kode status baru dan meja per tipe (DDL-2026-121).
 *
 * Tombol keputusan hanya muncul untuk PENDING_APPROVAL (dulu MENUNGGU_APPROVAL),
 * dan aksinya dikirim ke prefiks meja yang membuka modal: meja B2B punya rute
 * sendiri yang menolak (404) nomor tipe lain.
 */
describe('TamuReviewModal status dan meja per tipe', () => {
    const pasang = (props = {}, tamu = {}) => {
        const wrapper = shallowMount(TamuReviewModal, { props: { show: false, ...props } });
        wrapper.vm.tamu = { No_Transaksi: 'VSR-0926-0001', Status_Kunjungan: 'PENDING_APPROVAL', ...tamu };

        return wrapper;
    };

    beforeEach(() => {
        vi.clearAllMocks();
        axios.post.mockResolvedValue({ data: { success: true } });
    });

    it('menganggap PENDING_APPROVAL sebagai menunggu keputusan', async () => {
        const wrapper = pasang();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isPending).toBe(true);

        wrapper.vm.tamu = { ...wrapper.vm.tamu, Status_Kunjungan: 'SCHEDULED' };
        expect(wrapper.vm.isPending).toBe(false);

        // Kode lama tidak lagi ditulis siapa pun.
        wrapper.vm.tamu = { ...wrapper.vm.tamu, Status_Kunjungan: 'MENUNGGU_APPROVAL' };
        expect(wrapper.vm.isPending).toBe(false);
    });

    it('mode baca saja tidak pernah menampilkan keputusan', () => {
        const wrapper = pasang({ readOnly: true });

        expect(wrapper.vm.isPending).toBe(false);
    });

    it('mengirim keputusan ke meja Regular secara bawaan', async () => {
        const wrapper = pasang();

        await wrapper.vm.submitAction('approve');

        expect(axios.post).toHaveBeenCalledWith('/verifikator-tamu/VSR-0926-0001/approve', {});
        expect(wrapper.emitted('approved')).toBeTruthy();
    });

    it('mengirim keputusan ke meja B2B bila dibuka dari sana', async () => {
        const wrapper = pasang({ actionEndpoint: '/verifikator-b2b' });

        await wrapper.vm.submitAction('reject', 'Plat tidak sesuai');

        expect(axios.post).toHaveBeenCalledWith('/verifikator-b2b/VSR-0926-0001/reject', {
            alasan: 'Plat tidak sesuai',
        });
        expect(wrapper.emitted('rejected')).toBeTruthy();
    });
});
