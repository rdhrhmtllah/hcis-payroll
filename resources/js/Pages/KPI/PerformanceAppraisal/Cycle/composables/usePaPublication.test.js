import { describe, expect, it, vi } from 'vitest';
import { usePaPublication } from './usePaPublication.js';

describe('usePaPublication', () => {
    it('memuat ringkasan penerbitan dan menjaga state empty tetap bersih', async () => {
        const api = {
            ringkasanPenerbitan: vi.fn().mockResolvedValue({ terbit: 2, dihitung: 3, tertahan: 1, total: 6 }),
        };
        const state = usePaPublication(api);

        await state.muat(3);

        expect(api.ringkasanPenerbitan).toHaveBeenCalledWith(3);
        expect(state.ringkasan.value).toEqual({ terbit: 2, dihitung: 3, tertahan: 1, total: 6 });
        expect(state.error.value).toBe('');
        expect(state.loading.value).toBe(false);
    });

    it('mencegah response ringkasan lama menimpa siklus terbaru', async () => {
        let resolveFirst;
        const first = new Promise((resolve) => { resolveFirst = resolve; });
        const api = {
            ringkasanPenerbitan: vi.fn().mockReturnValueOnce(first).mockResolvedValueOnce({ total: 2 }),
        };
        const state = usePaPublication(api);
        const requestLama = state.muat(1);
        await state.muat(2);
        resolveFirst({ total: 1 });
        await requestLama;

        expect(state.ringkasan.value).toEqual({ total: 2 });
    });

    it('menampilkan seluruh angka respons setelah penerbitan massal', async () => {
        const api = {
            ringkasanPenerbitan: vi.fn().mockResolvedValue({ total: 6 }),
            terbitkan: vi.fn().mockResolvedValue({
                diterbitkan: 2,
                sudah_terbit: 1,
                tertahan: 2,
                belum_dihitung: 1,
                total_peserta: 6,
                siap_ditutup: false,
            }),
        };
        const state = usePaPublication(api);

        const response = await state.terbitkan(3);

        expect(response.data).toEqual(expect.objectContaining({ diterbitkan: 2, tertahan: 2, belum_dihitung: 1 }));
        expect(state.actionResult.value.total_peserta).toBe(6);
        expect(state.actionSuccess.value).toContain('2 diterbitkan');
        expect(api.ringkasanPenerbitan).toHaveBeenCalledWith(3);
        expect(state.acting.value).toBe(false);
    });

    it('menyebut hasil yang dilewati karena KPI belum lengkap dan meneruskan payload', async () => {
        const api = {
            ringkasanPenerbitan: vi.fn().mockResolvedValue({ total: 3 }),
            terbitkan: vi.fn().mockResolvedValue({
                diterbitkan: 1,
                sudah_terbit: 0,
                tertahan: 0,
                belum_dihitung: 0,
                total_peserta: 3,
                siap_ditutup: false,
                dilewati_kpi_belum_lengkap: 2,
                peserta_kpi_belum_lengkap: [
                    { participant_id: 1, nama: 'Ani', kpi_belum_disetujui: 1 },
                    { participant_id: 2, nama: 'Budi', kpi_belum_disetujui: 2 },
                ],
            }),
        };
        const state = usePaPublication(api);

        await state.terbitkan(3, { participant_ids: [1, 2, 3] });

        expect(api.terbitkan).toHaveBeenCalledWith(3, { participant_ids: [1, 2, 3] });
        expect(state.actionSuccess.value).toContain('2 dilewati karena sebagian pengajuan KPI-nya belum disetujui');
        expect(state.actionSuccess.value).toContain('Ani, Budi');
    });

    it('membawa pesan 422 dan metadata saat penutupan ditolak', async () => {
        const api = {
            tutupPenerbitan: vi.fn().mockRejectedValue({
                message: 'Penerbitan belum dapat ditutup karena masih ada peserta tertahan.',
                status: 422,
                error_code: 'PA_PUBLICATION_INCOMPLETE',
                request_id: 'req-pa-publish-1',
            }),
        };
        const state = usePaPublication(api);

        const response = await state.tutup(3);

        expect(response.error.message).toContain('belum dapat ditutup');
        expect(state.actionError.value).toBe('Penerbitan belum dapat ditutup karena masih ada peserta tertahan.');
        expect(state.actionErrorStatus.value).toBe(422);
        expect(state.actionErrorCode.value).toBe('PA_PUBLICATION_INCOMPLETE');
        expect(state.actionRequestId.value).toBe('req-pa-publish-1');
    });

    it('menerbitkan satu peserta melalui endpoint khusus tanpa mengarang payload', async () => {
        const api = {
            terbitkanPeserta: vi.fn().mockResolvedValue({ participant_id: 701, result_id: 902, status: 'TERBIT' }),
        };
        const state = usePaPublication(api);

        const response = await state.terbitkanPeserta(701);

        expect(api.terbitkanPeserta).toHaveBeenCalledWith(701);
        expect(response.data).toEqual({ participant_id: 701, result_id: 902, status: 'TERBIT' });
        expect(state.actionSuccess.value).toBe('Hasil peserta diterbitkan dengan status TERBIT.');
    });
});
