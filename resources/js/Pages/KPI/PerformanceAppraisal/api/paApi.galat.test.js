import axios from 'axios';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import paApi from './paApi.js';

vi.mock('axios', () => ({ default: vi.fn() }));

describe('panggil() meneruskan kode galat server', () => {
    beforeEach(() => {
        axios.mockReset();
    });

    it('membawa error_code dan request_id', async () => {
        axios.mockRejectedValueOnce({
            response: {
                status: 409,
                data: { message: 'Lembar sudah terkirim', error_code: 'PA_LEMBAR_BEKU', request_id: 'req-1' },
            },
        });

        await expect(
            paApi.simpanKalibrasiPenilai({ assignmentId: 1, perubahan: [], alasan: 'x' }),
        ).rejects.toMatchObject({
            status: 409,
            error_code: 'PA_LEMBAR_BEKU',
            request_id: 'req-1',
            message: 'Lembar sudah terkirim',
        });
    });

    it('kode kosong bila server tidak mengirimnya', async () => {
        axios.mockRejectedValueOnce({ response: { status: 422, data: { message: 'Ditolak' } } });

        await expect(paApi.wizardPenilaianSaya()).rejects.toMatchObject({ error_code: '', request_id: '' });
    });

    it('alamat wizard membawa cycle_id hanya bila diberikan', async () => {
        axios.mockResolvedValueOnce({ data: { result: { langkah: [] } } });
        await paApi.wizardPenilaianSaya(7);
        expect(axios).toHaveBeenLastCalledWith(
            expect.objectContaining({ url: '/api/v1/performance-appraisal/saya/wizard?cycle_id=7' }),
        );

        axios.mockResolvedValueOnce({ data: { result: { langkah: [] } } });
        await paApi.wizardPenilaianSaya();
        expect(axios).toHaveBeenLastCalledWith(
            expect.objectContaining({ url: '/api/v1/performance-appraisal/saya/wizard' }),
        );
    });

    it('kode langkah di-escape di alamat', async () => {
        axios.mockResolvedValueOnce({ data: { result: { lembar: [] } } });
        await paApi.lembarLangkahSaya('A/B', 3);
        expect(axios).toHaveBeenLastCalledWith(
            expect.objectContaining({
                url: '/api/v1/performance-appraisal/saya/wizard/langkah/A%2FB/lembar?cycle_id=3',
            }),
        );
    });

    it('kalibrasi mengirim badan snake_case', async () => {
        axios.mockResolvedValueOnce({ data: { result: { kalibrasi_id: 1 } } });
        await paApi.simpanKalibrasiPenilai({
            assignmentId: 9,
            perubahan: [{ competency_id: 11, skor: 80 }],
            alasan: 'Selaras',
        });
        expect(axios).toHaveBeenLastCalledWith(
            expect.objectContaining({
                method: 'post',
                url: '/api/v1/performance-appraisal/saya/kalibrasi',
                data: {
                    assignment_id: 9,
                    perubahan: [{ competency_id: 11, skor: 80 }],
                    perubahan_jawaban: [],
                    alasan: 'Selaras',
                },
            }),
        );
    });
});

describe('kalibrasi esai', () => {
    it('perubahanJawaban dikirim sebagai perubahan_jawaban', async () => {
        axios.mockResolvedValueOnce({ data: { result: { kalibrasi_id: 2 } } });
        await paApi.simpanKalibrasiPenilai({
            assignmentId: 9,
            perubahanJawaban: [{ kode_soal: 'Q07', jawaban: 'Baru' }],
            alasan: 'Diperjelas',
        });
        expect(axios).toHaveBeenLastCalledWith(
            expect.objectContaining({
                data: {
                    assignment_id: 9,
                    perubahan: [],
                    perubahan_jawaban: [{ kode_soal: 'Q07', jawaban: 'Baru' }],
                    alasan: 'Diperjelas',
                },
            }),
        );
    });
});
