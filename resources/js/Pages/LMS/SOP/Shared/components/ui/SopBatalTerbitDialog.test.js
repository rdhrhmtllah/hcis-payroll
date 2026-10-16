import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';

vi.mock('axios');
vi.mock('@/components/ui', () => ({
    hcisMessage: { success: vi.fn(), error: vi.fn() },
}));

import SopBatalTerbitDialog from './SopBatalTerbitDialog.vue';
import { hcisMessage } from '@/components/ui';

/**
 * Dialog pembatalan versi yang SEDANG BERLAKU.
 *
 * Yang dijaga di sini bukan tampilannya, melainkan bahwa admin diberi
 * BAHAN untuk menimbang sebelum menekan: versi ini akan mati, dan versi
 * mana yang menggantikannya. Dialog yang cuma bertanya "yakin?" memindahkan
 * penimbangan ke orang yang tak diberi apa-apa untuk menimbang.
 */
const VERSI = { Id_SOP_Item_Versi: 'hash-aktif', Versi_Ke: 1 };

const JAWABAN = {
    Boleh_Batal: true,
    Kurang: [],
    Kandidat: [
        { Id_SOP_Item_Versi: 'hash-v00', Versi_Ke: 0, Tanggal_Berlaku: '2026-01-01', Disahkan_Oleh: 'Budi' },
    ],
};

const stubs = {
    SopModal: { template: '<div><slot /><slot name="footer" /></div>' },
    HcisButton: {
        props: ['label', 'disabled'],
        template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>',
    },
    HcisAlert: { props: ['title'], template: '<div class="alert"><b>{{ title }}</b><slot /></div>' },
};

async function pasang(jawaban = JAWABAN) {
    axios.get = vi.fn(() => Promise.resolve({ data: { result: jawaban } }));
    axios.post = vi.fn(() => Promise.resolve({ data: { message: 'Versi dibatalkan.' } }));

    const w = mount(SopBatalTerbitDialog, { props: { versi: VERSI }, global: { stubs } });
    await flushPromises();

    return w;
}

const tombol = (w, teks) => w.findAll('button').find((b) => teks.test(b.text()));

describe('SopBatalTerbitDialog', () => {
    beforeEach(() => vi.clearAllMocks());

    /** Dampaknya dijawab SERVER — layar tak menyimpulkannya dari status. */
    it('mengambil dampak pembatalan sebelum apa pun bisa ditekan', async () => {
        const w = await pasang();

        expect(axios.get).toHaveBeenCalledWith('/api/v1/sop/admin/dokumen/versi/hash-aktif/batal-terbit');
        expect(w.text()).toContain('akan dinonaktifkan');
        expect(w.text()).toContain('Versi mana yang berlaku sesudah ini?');
    });

    /**
     * Pilihan terbaru dipilih lebih dulu.
     *
     * Bukan kemudahan: radio yang semuanya kosong membuat "Ya, Batalkan" berarti
     * "dokumen ini berakhir tanpa versi berlaku" tanpa ada yang memilihnya.
     */
    it('memilih kandidat terbaru sejak awal', async () => {
        const w = await pasang();

        expect(w.vm.pilihan).toBe('hash-v00');
    });

    it('mengirim versi pengganti yang dipilih', async () => {
        const w = await pasang();

        await tombol(w, /Ya, Batalkan/i).trigger('click');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith(
            '/api/v1/sop/admin/dokumen/versi/hash-aktif/batal-terbit',
            { Id_Pengganti: 'hash-v00' },
        );
        expect(w.emitted('berhasil')).toBeTruthy();
    });

    /**
     * Tanpa kandidat, akibatnya disebut — bukan dibiarkan sebagai daftar kosong
     * yang admin tafsirkan sendiri.
     */
    it('menyebut akibatnya saat tak ada versi yang bisa dikembalikan', async () => {
        const w = await pasang({ ...JAWABAN, Kandidat: [] });

        expect(w.text()).toContain('tanpa versi yang berlaku');
        expect(w.vm.pilihan).toBe(null);
    });

    /**
     * Ditolak = sebabnya dibaca, tombolnya hilang.
     *
     * Tombol yang tetap ada lalu ditolak di ujung adalah cara admin berhenti
     * memercayai layar.
     */
    it('menjelaskan penolakan dan tak menawarkan tombolnya', async () => {
        const w = await pasang({ Boleh_Batal: false, Kurang: ['Belum diterima siapa pun'], Kandidat: [] });

        expect(w.text()).toContain('sudah sampai ke karyawan');
        expect(w.text()).toContain('Belum diterima siapa pun');
        expect(tombol(w, /Ya, Batalkan/i)).toBeUndefined();
    });

    /** Gagal memuat = dialog menutup dengan kabar, bukan diam berisi kotak kosong. */
    it('menutup diri saat dampaknya gagal diambil', async () => {
        axios.get = vi.fn(() => Promise.reject({ response: { data: { message: 'Versi tak ditemukan.' } } }));

        const w = mount(SopBatalTerbitDialog, { props: { versi: VERSI }, global: { stubs } });
        await flushPromises();

        expect(hcisMessage.error).toHaveBeenCalledWith('Versi tak ditemukan.');
        expect(w.emitted('close')).toBeTruthy();
    });
});
