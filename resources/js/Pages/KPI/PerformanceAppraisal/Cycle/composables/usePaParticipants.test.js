import { describe, expect, it, vi } from 'vitest';
import { usePaParticipants } from './usePaParticipants.js';

function deferred() {
    let resolve;
    let reject;
    const promise = new Promise((resolvePromise, rejectPromise) => {
        resolve = resolvePromise;
        reject = rejectPromise;
    });

    return { promise, resolve, reject };
}

describe('usePaParticipants', () => {
    it('memetakan daftar dan metadata paginasi dari kontrak peserta', async () => {
        const api = {
            daftarPeserta: vi.fn().mockResolvedValue({
                peserta: [{ Id_PA_Participant: 501, Status_Peserta: 'DIIKUTKAN' }],
                halaman: 2,
                total: 36,
                total_halaman: 2,
                ringkasan: { DIIKUTKAN: 35, DIKECUALIKAN: 1, TERTUNDA: 0 },
                dipangkas: false,
            }),
        };
        const state = usePaParticipants(api);

        const result = await state.muat(12, { halaman: 2, per_halaman: 25 });

        expect(result.stale).toBe(false);
        expect(api.daftarPeserta).toHaveBeenCalledWith(12, { halaman: 2, per_halaman: 25 });
        expect(state.peserta.value).toEqual([{ Id_PA_Participant: 501, Status_Peserta: 'DIIKUTKAN' }]);
        expect(state.pagination).toMatchObject({ halaman: 2, total: 36, totalHalaman: 2, dipangkas: false });
        expect(state.pagination.ringkasan).toEqual({ DIIKUTKAN: 35, DIKECUALIKAN: 1, TERTUNDA: 0 });
        expect(state.memuat.value).toBe(false);
        expect(state.error.value).toBe('');
    });

    it('membedakan respons kosong dari error', async () => {
        const api = {
            daftarPeserta: vi.fn().mockResolvedValue({ peserta: [], halaman: 1, total: 0, total_halaman: 0 }),
        };
        const state = usePaParticipants(api);

        await state.muat(12);

        expect(state.peserta.value).toEqual([]);
        expect(state.error.value).toBe('');
        expect(state.memuat.value).toBe(false);
    });

    it('mempertahankan pesan manusia dan metadata error dari PaApiError', async () => {
        const exception = Object.assign(new Error('Siklus tidak boleh diubah.'), {
            error_code: 'PA_CYCLE_LOCKED',
            request_id: 'req-pa-501',
        });
        const api = { daftarPeserta: vi.fn().mockRejectedValue(exception) };
        const state = usePaParticipants(api);

        const result = await state.muat(12);

        expect(result.error).toBe(exception);
        expect(state.error.value).toBe('Siklus tidak boleh diubah.');
        expect(state.errorCode.value).toBe('PA_CYCLE_LOCKED');
        expect(state.requestId.value).toBe('req-pa-501');
        expect(state.memuat.value).toBe(false);
    });

    it('mengabaikan respons lama ketika filter memicu request baru', async () => {
        const pertama = deferred();
        const kedua = deferred();
        const api = {
            daftarPeserta: vi.fn().mockReturnValueOnce(pertama.promise).mockReturnValueOnce(kedua.promise),
        };
        const state = usePaParticipants(api);

        const requestPertama = state.muat(12, { q: 'lama' });
        const requestKedua = state.muat(12, { q: 'baru' });

        kedua.resolve({ peserta: [{ Id_PA_Participant: 502 }], total: 1, halaman: 1, total_halaman: 1 });
        await requestKedua;
        pertama.resolve({ peserta: [{ Id_PA_Participant: 501 }], total: 1, halaman: 1, total_halaman: 1 });
        const hasilLama = await requestPertama;

        expect(hasilLama.stale).toBe(true);
        expect(state.peserta.value).toEqual([{ Id_PA_Participant: 502 }]);
        expect(state.memuat.value).toBe(false);
    });
});
