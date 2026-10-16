import { describe, expect, it, vi } from 'vitest';
import { usePaResult } from './usePaResult.js';

const detail = {
    peserta: {
        Id_PA_Participant: 12,
        Id_PA_Result: 902,
        Status_Hasil: 'DIHITUNG',
        Nilai_KPI: '104.50',
        Final_Score: '101.25',
        Alasan_Tertahan: null,
    },
    kompetensi: [
        { Nama_Competency: 'Analytical Thinking', Nilai: '104.50', agregat: true, Kode_Kategori: null },
    ],
    kategori: [],
    penilai: [],
    catatan: [],
};

function deferred() {
    let resolve;
    const promise = new Promise((resolvePromise) => {
        resolve = resolvePromise;
    });

    return { promise, resolve };
}

describe('usePaResult', () => {
    it('memuat detail peserta apa adanya dan menjaga nilai di atas 100', async () => {
        const api = { detailHasil: vi.fn().mockResolvedValue(detail) };
        const state = usePaResult(api);

        const result = await state.muat(12);

        expect(result.data).toBe(detail);
        expect(state.detail.value).toEqual(detail);
        expect(state.peserta.value.Nilai_KPI).toBe('104.50');
        expect(state.error.value).toBe('');
        expect(state.loading.value).toBe(false);
    });

    it('menghitung peserta lalu menyegarkan detail dengan hasil server', async () => {
        const calculated = {
            result_id: 902,
            status: 'DIHITUNG',
            versi: 2,
            nilai_kpi: 104.5,
            nilai_kompetensi: 82.31,
            final_score: 90.13,
            kode_grade: 'A',
            kode_kuadran: 'STRIVING_STAR',
            alasan_tertahan: null,
        };
        const api = {
            hitungPeserta: vi.fn().mockResolvedValue(calculated),
            detailHasil: vi.fn().mockResolvedValue(detail),
        };
        const state = usePaResult(api);

        const result = await state.hitung(12);

        expect(result.data).toEqual(calculated);
        expect(state.hasilHitung.value.final_score).toBe(90.13);
        expect(api.hitungPeserta).toHaveBeenCalledWith(12);
        expect(api.detailHasil).toHaveBeenCalledWith(12);
        expect(state.recalculating.value).toBe(false);
    });

    it('membatalkan penugasan dengan alasan lalu menyegarkan detail', async () => {
        const api = {
            batalkanPenugasan: vi.fn().mockResolvedValue({ dibatalkan: 4412 }),
            detailHasil: vi.fn().mockResolvedValue(detail),
        };
        const state = usePaResult(api);

        const result = await state.batalkan(12, 4412, 'Penilai pindah divisi');

        expect(result.data).toEqual({ dibatalkan: 4412 });
        expect(api.batalkanPenugasan).toHaveBeenCalledWith(4412, 'Penilai pindah divisi');
        expect(api.detailHasil).toHaveBeenCalledWith(12);
        expect(state.actionSuccess.value).toBe('Penugasan 4412 dibatalkan.');
    });

    it('mengirim payload kalibrasi dan mempertahankan metadata error aturan', async () => {
        const exception = Object.assign(new Error('Hasil tertahan belum dapat dikalibrasi.'), {
            status: 422,
            error_code: 'PA_RESULT_HELD',
            request_id: 'req-pa-result-1',
        });
        const api = {
            kalibrasi: vi.fn().mockRejectedValue(exception),
        };
        const state = usePaResult(api);

        const result = await state.kalibrasi(12, 902, {
            kodeGrade: 'B_PLUS',
            kuadranId: 2,
            alasan: 'Hasil forum kalibrasi HR',
        });

        expect(result.error).toBe(exception);
        expect(api.kalibrasi).toHaveBeenCalledWith(902, {
            kodeGrade: 'B_PLUS',
            kuadranId: 2,
            alasan: 'Hasil forum kalibrasi HR',
        });
        expect(state.actionError.value).toBe('Hasil tertahan belum dapat dikalibrasi.');
        expect(state.actionErrorStatus.value).toBe(422);
        expect(state.actionErrorCode.value).toBe('PA_RESULT_HELD');
        expect(state.actionRequestId.value).toBe('req-pa-result-1');
    });

    it('mengabaikan detail lama saat peserta aktif memicu request baru', async () => {
        const pertama = deferred();
        const kedua = deferred();
        const api = {
            detailHasil: vi.fn().mockReturnValueOnce(pertama.promise).mockReturnValueOnce(kedua.promise),
        };
        const state = usePaResult(api);

        const requestPertama = state.muat(12);
        const requestKedua = state.muat(13);

        kedua.resolve({ ...detail, peserta: { ...detail.peserta, Id_PA_Participant: 13 } });
        await requestKedua;
        pertama.resolve(detail);
        const hasilLama = await requestPertama;

        expect(hasilLama.stale).toBe(true);
        expect(state.detail.value.peserta.Id_PA_Participant).toBe(13);
        expect(state.loading.value).toBe(false);
    });
});
