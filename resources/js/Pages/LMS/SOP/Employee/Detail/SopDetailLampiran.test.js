import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
vi.mock('element-plus', async (asli) => ({
    ...(await asli()),
    ElMessage: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() },
}));

import axios from 'axios';
import SopDetail from './SopDetail.vue';

/**
 * Unduh form kosong dari kartu FORM.
 *
 * Diuji sebagai method lepas, seperti uji SopDetail lain: yang dikunci adalah
 * alamat yang diminta dan bahwa unduhan form kosong TIDAK mencatat progres —
 * progres item FORM tetap dari "Tandai selesai".
 */
const konteks = () => ({ idPaket: 'pkt1', unduhLampiranId: null, buka: vi.fn() });
const item = { Id_SOP_Paket_Item: 'it1', Id_Item_Versi_Aktif: 'ver1', Tipe_Item: 'FORM' };
const lampiran = { Id_Lampiran: 'lmp1', Nama: 'Form kosong' };

describe('SopDetail — unduhLampiran', () => {
    beforeEach(() => {
        delete window.location;
        window.location = { href: '' };
    });

    it('meminta tautan lampiran versi yang berlaku lalu mengunduhnya', async () => {
        axios.get.mockResolvedValue({ data: { success: true, result: { url: 'https://gcs/form.docx' } } });
        const ctx = konteks();

        await SopDetail.methods.unduhLampiran.call(ctx, item, lampiran);

        expect(axios.get).toHaveBeenCalledWith('/api/v1/sop/pkt1/versi/ver1/lampiran/lmp1');
        expect(window.location.href).toBe('https://gcs/form.docx');
        expect(ctx.unduhLampiranId).toBe(null);
    });

    it('tidak mencatat progres', async () => {
        axios.get.mockResolvedValue({ data: { success: true, result: { url: 'https://gcs/form.docx' } } });
        const ctx = konteks();

        await SopDetail.methods.unduhLampiran.call(ctx, item, lampiran);

        expect(ctx.buka).not.toHaveBeenCalled();
    });

    it('tanpa tautan tidak berpindah halaman', async () => {
        axios.get.mockRejectedValue({ response: { data: { message: 'Form kosong tidak ditemukan.' } } });
        const ctx = konteks();

        await SopDetail.methods.unduhLampiran.call(ctx, item, lampiran);

        expect(window.location.href).toBe('');
        expect(ctx.unduhLampiranId).toBe(null);
    });
});

/**
 * Unduh Semua = satu ZIP (laporan user 6 Okt 2026).
 *
 * Versi sebelumnya membuka satu tab per berkas; browser memblokir semuanya
 * kecuali yang pertama. Kini satu permintaan ke /lampiran-zip, hasilnya
 * diunduh sebagai satu berkas.
 */
describe('SopDetail — unduhSemuaLampiran', () => {
    const item = {
        Id_SOP_Paket_Item: 'it1',
        Id_Item_Versi_Aktif: 'ver1',
        Judul_Item: 'Formulir A',
        Lampiran: [{ Id_Lampiran: 'a' }, { Id_Lampiran: 'b' }],
    };
    const konteks = () => ({ idPaket: 'pkt1', unduhSemuaLoading: null });
    let klik;

    beforeEach(() => {
        URL.createObjectURL = vi.fn(() => 'blob:zip');
        URL.revokeObjectURL = vi.fn();
        klik = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});
    });

    afterEach(() => {
        delete URL.createObjectURL;
        delete URL.revokeObjectURL;
        klik.mockRestore();
    });

    it('meminta satu ZIP dan mengunduhnya sebagai satu berkas', async () => {
        axios.get.mockResolvedValue({
            data: new Blob(['PK'], { type: 'application/zip' }),
            headers: { 'content-disposition': 'attachment; filename="Form kosong - Formulir A.zip"' },
        });
        const ctx = konteks();

        await SopDetail.methods.unduhSemuaLampiran.call(ctx, item);

        expect(axios.get).toHaveBeenCalledTimes(1);
        expect(axios.get).toHaveBeenCalledWith('/api/v1/sop/pkt1/versi/ver1/lampiran-zip', { responseType: 'blob' });
        expect(klik).toHaveBeenCalledTimes(1);
        expect(ctx.unduhSemuaLoading).toBe(null);
    });

    it('galat dari server disebutkan, bukan diam', async () => {
        const { ElMessage } = await import('element-plus');
        const isi = new Blob([JSON.stringify({ message: 'Belum ada form kosong yang bisa diunduh.' })], {
            type: 'application/json',
        });
        isi.text = async () => JSON.stringify({ message: 'Belum ada form kosong yang bisa diunduh.' });
        axios.get.mockRejectedValue({ response: { data: isi } });
        const ctx = konteks();

        await SopDetail.methods.unduhSemuaLampiran.call(ctx, item);

        expect(ElMessage.error).toHaveBeenCalledWith('Belum ada form kosong yang bisa diunduh.');
        expect(klik).not.toHaveBeenCalled();
        expect(ctx.unduhSemuaLoading).toBe(null);
    });
});
