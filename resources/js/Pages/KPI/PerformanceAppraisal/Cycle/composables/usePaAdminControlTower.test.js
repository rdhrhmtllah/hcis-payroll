import { describe, expect, it, vi } from 'vitest';
import { usePaAdminControlTower } from './usePaAdminControlTower.js';

describe('usePaAdminControlTower', () => {
    it('memuat tiga laporan siklus secara paralel dan menyimpan response terbaru', async () => {
        const api = {
            ringkasanSiklus: vi.fn().mockResolvedValue({ siklus: { Id_PA_Cycle: 657 } }),
            monitoringSiklus: vi.fn().mockResolvedValue({ peta_panas: { status: [], baris: [] } }),
            distribusiSiklus: vi.fn().mockResolvedValue({ keseluruhan: { jumlah: 0 } }),
            riwayatAdmin: vi.fn(),
        };
        const state = usePaAdminControlTower(api);

        await state.muatLaporanSiklus(657, {
            monitoring: { halaman: 1, per_halaman: 25 },
            distribusi: { id_level: 2 },
        });

        expect(api.ringkasanSiklus).toHaveBeenCalledWith(657);
        expect(api.monitoringSiklus).toHaveBeenCalledWith(657, { halaman: 1, per_halaman: 25 });
        expect(api.distribusiSiklus).toHaveBeenCalledWith(657, { id_level: 2 });
        expect(state.ringkasan.data.siklus.Id_PA_Cycle).toBe(657);
        expect(state.ringkasan.loading).toBe(false);
    });

    it('mencegah response lama menimpa permintaan ringkasan terbaru', async () => {
        let resolveFirst;
        const first = new Promise((resolve) => { resolveFirst = resolve; });
        const api = {
            ringkasanSiklus: vi.fn().mockReturnValueOnce(first).mockResolvedValueOnce({ siklus: { Kode_Cycle: 'TERBARU' } }),
            monitoringSiklus: vi.fn(),
            distribusiSiklus: vi.fn(),
            riwayatAdmin: vi.fn(),
        };
        const state = usePaAdminControlTower(api);
        const requestLama = state.muatRingkasan(1);
        await state.muatRingkasan(2);
        resolveFirst({ siklus: { Kode_Cycle: 'LAMA' } });
        await requestLama;

        expect(state.ringkasan.data.siklus.Kode_Cycle).toBe('TERBARU');
    });

    it('membawa status, error_code, request_id, dan pesan aman dari PaApiError', async () => {
        const api = {
            ringkasanSiklus: vi.fn().mockRejectedValue({ message: 'Siklus tidak dapat dibaca.', status: 403, error_code: 'PA_FORBIDDEN', request_id: 'req-pa-1' }),
            monitoringSiklus: vi.fn(),
            distribusiSiklus: vi.fn(),
            riwayatAdmin: vi.fn(),
        };
        const state = usePaAdminControlTower(api);

        await state.muatRingkasan(657);

        expect(state.ringkasan.error).toBe('Siklus tidak dapat dibaca.');
        expect(state.ringkasan.status).toBe(403);
        expect(state.ringkasan.errorCode).toBe('PA_FORBIDDEN');
        expect(state.ringkasan.requestId).toBe('req-pa-1');
        expect(state.ringkasan.loading).toBe(false);
    });
});
