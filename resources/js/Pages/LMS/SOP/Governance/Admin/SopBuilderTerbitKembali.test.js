import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('axios', () => ({
    default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));
vi.mock('element-plus', async (asli) => ({
    ...(await asli()),
    ElMessage: Object.assign(vi.fn(), { success: vi.fn(), error: vi.fn(), warning: vi.fn() }),
}));

import axios from 'axios';
import SopBuilder from './SopBuilder.vue';

/**
 * Sesudah paket terbit, admin kembali ke daftar paket.
 *
 * Permintaan user 5 Okt 2026: tetap di editor lalu memuat ulang seluruh isi
 * paket terasa seperti layar yang macet. Paket yang baru terbit tak punya
 * pekerjaan lagi di editor; yang ingin dilihat admin adalah daftarnya.
 *
 * Diuji atas metode langsung, seperti uji Builder lain: perilakunya murni
 * urutan pemanggilan.
 */
const konteks = () => {
    const ctx = {
        current: { Id_SOP_Paket: 'pkt1', Status: 'DRAFT' },
        releaseSummary: 'Rilis pertama',
        publishPratinjau: true,
        publishMengirim: false,
        mode: 'editor',
        loadPaket: vi.fn(),
        resetEditorState: vi.fn(),
        syncUrl: vi.fn(),
        fetchPaket: vi.fn(),
        fetchCounts: vi.fn(),
    };
    ctx.keDaftar = SopBuilder.methods.keDaftar;
    return ctx;
};

describe('SopBuilder — sesudah terbit kembali ke daftar', () => {
    beforeEach(() => {
        axios.patch.mockResolvedValue({ data: { result: {} } });
    });

    it('pindah ke daftar paket tanpa memuat ulang editor', async () => {
        const ctx = konteks();

        await SopBuilder.methods.terbitkanDariPratinjau.call(ctx, 'TIDAK', {});

        expect(axios.patch).toHaveBeenCalledWith(
            '/api/v1/sop/admin/builder/paket/status/pkt1',
            expect.objectContaining({ status: 'PUBLISHED' }),
        );
        expect(ctx.mode).toBe('list');
        expect(ctx.resetEditorState).toHaveBeenCalled();
        expect(ctx.syncUrl).toHaveBeenCalled();
        expect(ctx.fetchPaket).toHaveBeenCalledWith(1);
        expect(ctx.fetchCounts).toHaveBeenCalled();
        expect(ctx.loadPaket).not.toHaveBeenCalled();
        expect(ctx.publishPratinjau).toBe(false);
    });

    it('gagal terbit tetap di editor', async () => {
        axios.patch.mockRejectedValue({ response: { data: { message: 'Belum siap' } } });
        const ctx = konteks();

        await SopBuilder.methods.terbitkanDariPratinjau.call(ctx, 'TIDAK', {});

        expect(ctx.mode).toBe('editor');
        expect(ctx.resetEditorState).not.toHaveBeenCalled();
        expect(ctx.publishMengirim).toBe(false);
    });
});
