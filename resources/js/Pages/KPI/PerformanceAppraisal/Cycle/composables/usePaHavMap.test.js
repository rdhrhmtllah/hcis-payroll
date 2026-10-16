import { describe, expect, it, vi } from 'vitest';
import { usePaHavMap } from './usePaHavMap.js';

const map = {
    kuadran: [
        { Id_PA_Hav_Quadrant: '9', Nomor_Box: '9', Kode_Kuadran: 'DEADWOOD', jumlah: 0 },
        { Id_PA_Hav_Quadrant: '4', Nomor_Box: '4', Kode_Kuadran: 'POTENTIAL_CANDIDATE', jumlah: 1 },
    ],
    grade: [{ Kode_Range: 'A', Target_Persen: '10.00', jumlah: 1, persen: 100 }],
    pita: {
        kpi: [{ Kode_Range: 'RENDAH', Nilai_Dari: 0, Nilai_Sampai: 70 }],
        kompetensi: [{ Kode_Range: 'RENDAH', Nilai_Dari: 0, Nilai_Sampai: 65 }],
    },
    ringkasan: { dihitung: 1, tertahan: 0, belum_dihitung: 0, terbit: 1, total: 1 },
};

describe('usePaHavMap', () => {
    it('mempertahankan kuadran, grade, dan ringkasan dari kontrak tanpa menyaring kotak nol', async () => {
        const api = { havMap: vi.fn().mockResolvedValue(map) };
        const state = usePaHavMap(api);

        const result = await state.muat(12);

        expect(result.data).toEqual(map);
        expect(state.kuadran.value).toEqual(map.kuadran);
        expect(state.kuadran.value.map((item) => item.Nomor_Box)).toEqual(['9', '4']);
        expect(state.kuadran.value[0].jumlah).toBe(0);
        expect(state.grade.value).toEqual(map.grade);
        expect(state.pita.value).toEqual(map.pita);
        expect(state.ringkasan.value).toEqual(map.ringkasan);
    });

    it('mengosongkan pita per sumbu bila server tidak mengirimnya, bukan menebak batas', async () => {
        const api = { havMap: vi.fn().mockResolvedValue({ kuadran: [], grade: [] }) };
        const state = usePaHavMap(api);

        await state.muat(12);

        expect(state.pita.value).toEqual({ kpi: [], kompetensi: [] });
    });

    it('memisahkan error akses dan mempertahankan pesan serta metadata', async () => {
        const exception = Object.assign(new Error('Siklus tidak dapat dilihat.'), {
            status: 403,
            error_code: 'PA_HAV_FORBIDDEN',
            request_id: 'req-hav-1',
        });
        const api = { havMap: vi.fn().mockRejectedValue(exception) };
        const state = usePaHavMap(api);

        const result = await state.muat(12);

        expect(result.error).toBe(exception);
        expect(state.error.value).toBe('Siklus tidak dapat dilihat.');
        expect(state.errorStatus.value).toBe(403);
        expect(state.errorCode.value).toBe('PA_HAV_FORBIDDEN');
        expect(state.requestId.value).toBe('req-hav-1');
        expect(state.loading.value).toBe(false);
    });

    it('mengabaikan respons lama ketika siklus aktif berganti', async () => {
        let resolveFirst;
        let resolveSecond;
        const first = new Promise((resolve) => {
            resolveFirst = resolve;
        });
        const second = new Promise((resolve) => {
            resolveSecond = resolve;
        });
        const api = { havMap: vi.fn().mockReturnValueOnce(first).mockReturnValueOnce(second) };
        const state = usePaHavMap(api);

        const firstRequest = state.muat(12);
        const secondRequest = state.muat(13);

        resolveSecond({ ...map, ringkasan: { ...map.ringkasan, total: 13 } });
        await secondRequest;
        resolveFirst(map);
        const staleResult = await firstRequest;

        expect(staleResult.stale).toBe(true);
        expect(state.ringkasan.value.total).toBe(13);
    });
});
