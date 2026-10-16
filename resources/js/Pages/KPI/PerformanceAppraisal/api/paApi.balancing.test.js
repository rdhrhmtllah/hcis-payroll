import axios from 'axios';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import paApi from './paApi.js';

vi.mock('axios');

describe('paApi Balancing', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.mockResolvedValue({ data: { result: { ok: true } } });
    });

    const dipanggil = () => axios.mock.calls[0][0];

    it.each([
        ['daftarSesiBalancing', [5], 'get', '/api/v1/performance-appraisal/cycles/5/balancing/sesi', undefined],
        ['buatSesiBalancing', [5, { nama: 'S' }], 'post', '/api/v1/performance-appraisal/cycles/5/balancing/sesi', { nama: 'S' }],
        ['detailSesiBalancing', [9], 'get', '/api/v1/performance-appraisal/balancing/sesi/9', undefined],
        ['simpanSesiBalancing', [9, { versi_data: 2 }], 'put', '/api/v1/performance-appraisal/balancing/sesi/9', { versi_data: 2 }],
        ['finalkanSesiBalancing', [9, 3], 'post', '/api/v1/performance-appraisal/balancing/sesi/9/final', { versi_data: 3 }],
        ['batalkanSesiBalancing', [9, 'Ditunda'], 'post', '/api/v1/performance-appraisal/balancing/sesi/9/batal', { alasan: 'Ditunda' }],
        ['simulasiBalancing', [9, []], 'post', '/api/v1/performance-appraisal/balancing/sesi/9/simulasi', { peserta: [] }],
        ['hitungUlangBalancing', [5], 'post', '/api/v1/performance-appraisal/cycles/5/balancing/hitung-ulang', undefined],
        ['riwayatBalancing', [77], 'get', '/api/v1/performance-appraisal/participants/77/balancing', undefined],
        ['riwayatHasil', [77], 'get', '/api/v1/performance-appraisal/participants/77/hasil/riwayat', undefined],
    ])('%s', async (nama, argumen, metode, url, data) => {
        await expect(paApi[nama](...argumen)).resolves.toEqual({ ok: true });
        expect(dipanggil()).toEqual({ method: metode, url, data });
    });

    it('kandidat mengirim saringan larik sebagai query', async () => {
        await paApi.kandidatBalancing(5, { level_ids: [1, 2], cari: 'an', grade: [], jumlah_sesi: null });
        expect(dipanggil().url).toBe('/api/v1/performance-appraisal/cycles/5/balancing/kandidat?level_ids%5B%5D=1&level_ids%5B%5D=2&cari=an');
    });

    it('galat membawa error_code', async () => {
        axios.mockRejectedValue({ response: { status: 409, data: { message: 'Data sesi sudah diubah', error_code: 'PA_BAL_VERSI_BERUBAH', request_id: 'r1' } } });
        await expect(paApi.simpanSesiBalancing(9, {})).rejects.toMatchObject({ status: 409, error_code: 'PA_BAL_VERSI_BERUBAH' });
    });
});
