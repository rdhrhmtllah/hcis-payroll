import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import PaRevisiPenugasanModal from './PaRevisiPenugasanModal.vue';

/*
 | Revisi penugasan sesudah dikunci, dari kartu siklus: keadaan singkat lalu dua jalan.
 | Yang dijaga: keadaannya dibaca, peringatannya muncul hanya bila ada, dan tiap tombol
 | membawa admin ke tempat yang benar tanpa kembali ke wizard penyiapan.
 */

const paApiMock = vi.hoisted(() => ({
    statusPenjadwalanLatar: vi.fn(),
    cariRevisiPeserta: vi.fn(),
    kesehatanPenugasan: vi.fn(),
    mulaiPenjadwalanLatar: vi.fn(),
    opsiFilterKaryawanPraKecuali: vi.fn(),
    pratinjauRevisiMassal: vi.fn(),
    mulaiRevisiMassal: vi.fn(),
    statusRevisiMassal: vi.fn(),
}));
vi.mock('../../api/paApi.js', () => ({ default: paApiMock }));
vi.mock('@/composables/useActivityCenter', () => ({ useActivityCenter: () => ({ notifyNewTask: vi.fn() }) }));

const stubs = {
    HcisDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /><slot name="footer" /></div>' },
    HcisButton: { props: ['label', 'disabled', 'loading'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>' },
    HcisAlert: { props: ['title', 'description'], template: '<div>{{ title }} {{ description }}<slot /></div>' },
    PaRevisiPesertaMassalDialog: { name: 'PaRevisiPesertaMassalDialog', props: ['modelValue', 'cycleId', 'karyawanAwal'], template: '<div v-if="modelValue" data-uji="stub-revisi">{{ karyawanAwal?.tindakan }}</div>' },
};

const cycle = { Id_PA_Cycle: 77, Nama_Cycle: 'PA 2026', Status_Cycle: 'LOCKED' };
const pasang = () => mount(PaRevisiPenugasanModal, { props: { modelValue: true, cycle }, global: { stubs } });

describe('PaRevisiPenugasanModal', () => {
    beforeEach(() => {
        Object.values(paApiMock).forEach((f) => f.mockReset());
        paApiMock.statusPenjadwalanLatar.mockResolvedValue({ tugas: null, ringkasan: { total: 400, terjadwal: 396, antre: 0, gagal: 1, belum: 3 } });
        paApiMock.cariRevisiPeserta.mockResolvedValue({ baris: [{ kode_karyawan: 'R1' }] });
        paApiMock.kesehatanPenugasan.mockResolvedValue({ total_peserta_kurang: 2 });
        paApiMock.mulaiPenjadwalanLatar.mockResolvedValue({ tugas: { id: 't' } });
    });

    it('membaca keadaan dan menyebut yang perlu ditindaklanjuti', async () => {
        const w = pasang();
        await flushPromises();

        expect(paApiMock.cariRevisiPeserta).toHaveBeenCalledWith(77, { sumber: 'penilai', status: 'TIDAK_AKTIF' });
        const keadaan = w.find('[data-uji="rpn-keadaan"]').text();
        expect(keadaan).toContain('396 dari 400');
        expect(keadaan).toContain('Peserta kurang penilai');
        expect(w.find('[data-uji="rpn-belum-terjadwal"]').text()).toContain('4 penilai belum punya akses');
        expect(w.find('[data-uji="rpn-tidak-aktif"]').text()).toContain('1 penilai sudah tidak aktif');

        await w.find('[data-uji="rpn-jadwalkan"]').trigger('click');
        await flushPromises();
        expect(paApiMock.mulaiPenjadwalanLatar).toHaveBeenCalledWith(77);
    });

    it('lepas penilai membuka dialog revisi dengan tindakannya, modal menyingkir', async () => {
        const w = pasang();
        await flushPromises();
        await w.find('[data-uji="rpn-lepas"]').trigger('click');

        expect(w.find('[data-uji="stub-revisi"]').text()).toBe('LEPAS_PENILAI');
        expect(w.find('[data-uji="rpn-keadaan"]').exists()).toBe(false);
    });

    it('kelola penilai per peserta diserahkan ke halaman siklus', async () => {
        const w = pasang();
        await flushPromises();
        await w.find('[data-uji="rpn-kelola-rater"]').trigger('click');

        expect(w.emitted('kelola-rater')[0][0]).toEqual(cycle);
        expect(w.emitted('update:modelValue')[0]).toEqual([false]);
    });

    it('tanpa masalah tidak ada peringatan', async () => {
        paApiMock.statusPenjadwalanLatar.mockResolvedValue({ tugas: null, ringkasan: { total: 10, terjadwal: 10, antre: 0, gagal: 0, belum: 0 } });
        paApiMock.cariRevisiPeserta.mockResolvedValue({ baris: [] });
        paApiMock.kesehatanPenugasan.mockResolvedValue({ total_peserta_kurang: 0 });
        const w = pasang();
        await flushPromises();

        expect(w.find('[data-uji="rpn-belum-terjadwal"]').exists()).toBe(false);
        expect(w.find('[data-uji="rpn-tidak-aktif"]').exists()).toBe(false);
    });

    it('menyebut jendela pengisian yang hampir habis', async () => {
        paApiMock.cariRevisiPeserta.mockResolvedValue({ baris: [], jendela: { status: 'HAMPIR', pesan: 'Pengisian berakhir 15-10-2026 23:59 — penilai baru hasil revisi hanya punya sekitar 2 hari.' } });
        const w = pasang();
        await flushPromises();

        expect(w.find('[data-uji="rpn-jendela"]').text()).toContain('hanya punya sekitar 2 hari');
    });

    /* Sesudah pengisian ditutup server menolak revisi: keadaan tetap terlihat, jalannya mati. */
    it('hanya-baca bila revisi tidak lagi boleh', async () => {
        const w = mount(PaRevisiPenugasanModal, { props: { modelValue: true, cycle, bolehRevisi: false }, global: { stubs } });
        await flushPromises();

        expect(w.find('[data-uji="rpn-hanya-baca"]').exists()).toBe(true);
        expect(w.find('[data-uji="rpn-revisi-peserta"]').attributes('disabled')).toBeDefined();
        expect(w.find('[data-uji="rpn-kelola-rater"]').attributes('disabled')).toBeDefined();
    });
});
