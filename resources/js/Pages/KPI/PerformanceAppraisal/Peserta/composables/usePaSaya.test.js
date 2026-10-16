import { describe, expect, it, vi } from 'vitest';

import { usePaSaya } from './usePaSaya.js';

const ringkasan = {
    karyawan: { kode_karyawan: 'A4', nama: 'Aji' },
    total_assignment: '2',
    belum_dimulai: '1',
    sedang_dikerjakan: '0',
    sudah_selesai: '1',
    deadline_dekat: '1',
    terlambat: '0',
    progres_persen: 50,
    ambang_dekat_hari: 3,
};

const assignment = {
    assignment: [{ Id_PA_Assignment: '147', Status_Penugasan: 'SELESAI', Id_Ujian_Token: 'token-147' }],
    halaman: 1,
    per_halaman: 25,
    total: 1,
    total_halaman: 1,
    dipangkas: false,
};

describe('usePaSaya', () => {
    it('memuat empat endpoint melalui API module dan menormalisasi pagination', async () => {
        const api = {
            ringkasanSaya: vi.fn().mockResolvedValue(ringkasan),
            assignmentSaya: vi.fn().mockResolvedValue(assignment),
            hasilSaya: vi.fn().mockResolvedValue({ tersedia: false, pesan: 'Belum terbit.', hasil: null, kompetensi: [], catatan: [] }),
            riwayatSaya: vi.fn().mockResolvedValue({ periode: [], jumlah_periode: '0', rata_rata: null, terbaik: null, perubahan_terakhir: null }),
        };
        const state = usePaSaya(api);

        await state.muatSemua();

        expect(api.ringkasanSaya).toHaveBeenCalledWith(null);
        expect(api.assignmentSaya).toHaveBeenCalledWith({ halaman: 1, per_halaman: 25 });
        expect(api.hasilSaya).toHaveBeenCalledWith(null);
        expect(api.riwayatSaya).toHaveBeenCalledTimes(1);
        expect(state.ringkasan.value).toEqual(ringkasan);
        expect(state.assignment.value).toEqual(assignment.assignment);
        expect(state.assignmentMeta.value.total).toBe(1);
        expect(state.hasil.value.tersedia).toBe(false);
        expect(state.riwayat.value.jumlah_periode).toBe(0);
        expect(state.memuat.value).toBe(false);
    });

    it('mempertahankan pesan 422, status, error_code, dan request_id', async () => {
        const exception = Object.assign(new Error('Status tugas tidak diizinkan.'), {
            status: 422,
            error_code: 'PA_ASSIGNMENT_FILTER_INVALID',
            request_id: 'req-pa-saya-1',
        });
        const api = {
            assignmentSaya: vi.fn().mockRejectedValue(exception),
        };
        const state = usePaSaya(api);

        const result = await state.muatAssignment({ status: 'INVALID' });

        expect(result.error).toBe(exception);
        expect(state.errorAssignment.value).toBe('Status tugas tidak diizinkan.');
        expect(state.statusErrorAssignment.value).toBe(422);
        expect(state.kodeErrorAssignment.value).toBe('PA_ASSIGNMENT_FILTER_INVALID');
        expect(state.requestIdAssignment.value).toBe('req-pa-saya-1');
        expect(state.memuatAssignment.value).toBe(false);
    });

    it('memisahkan hasil belum terbit sebagai data sah, bukan error', async () => {
        const api = {
            hasilSaya: vi.fn().mockResolvedValue({
                tersedia: false,
                pesan: 'Hasil penilaian Anda belum diterbitkan.',
                hasil: null,
                kompetensi: [],
                catatan: [],
            }),
        };
        const state = usePaSaya(api);

        await state.muatHasil();

        expect(state.errorHasil.value).toBe('');
        expect(state.hasil.value.tersedia).toBe(false);
        expect(state.hasil.value.pesan).toBe('Hasil penilaian Anda belum diterbitkan.');
    });

    it('mengabaikan respons assignment lama saat saringan terbaru sudah selesai', async () => {
        let resolveFirst;
        let resolveSecond;
        const first = new Promise((resolve) => { resolveFirst = resolve; });
        const second = new Promise((resolve) => { resolveSecond = resolve; });
        const api = { assignmentSaya: vi.fn().mockReturnValueOnce(first).mockReturnValueOnce(second) };
        const state = usePaSaya(api);

        const firstRequest = state.muatAssignment({ q: 'lama' });
        const secondRequest = state.muatAssignment({ q: 'baru' });

        resolveSecond({ ...assignment, assignment: [{ Id_PA_Assignment: 'baru' }], total: 1 });
        await secondRequest;
        resolveFirst({ ...assignment, assignment: [{ Id_PA_Assignment: 'lama' }], total: 1 });
        const stale = await firstRequest;

        expect(stale.stale).toBe(true);
        expect(state.assignment.value[0].Id_PA_Assignment).toBe('baru');
    });
});

