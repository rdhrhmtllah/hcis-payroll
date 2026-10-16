import { beforeEach, describe, expect, it, vi } from 'vitest';
import { usePaCycleSetup } from './usePaCycleSetup.js';

const cycle = {
    Id_PA_Cycle: 657,
    Kode_Cycle: 'PA-DEMO-FE',
    Nama_Cycle: 'Siklus Contoh',
    Tahun: 2026,
    Deskripsi: 'Catatan lama',
    Periode_Dinilai_Dari: '2026-01-01',
    Periode_Dinilai_Sampai: '2026-06-30',
    Pengisian_Dari: null,
    Pengisian_Sampai: null,
    Tanggal_Acuan_Organisasi: null,
    Status_Cycle: 'CLOSED',
};

const detail = {
    siklus: cycle,
    operasi_boleh: ['TERBITKAN'],
    boleh_ubah_periode: false,
    alasan_periode_terkunci: 'Periode sudah dikunci oleh server.',
    peserta: { total: 12 },
};

function createApi() {
    return {
        detailSiklus: vi.fn(),
        buatSiklus: vi.fn(),
        perbaruiSiklus: vi.fn(),
    };
}

beforeEach(() => vi.clearAllMocks());

describe('usePaCycleSetup', () => {
    it('mengirim payload pembuatan sesuai kontrak dan tidak mengirim status buatan frontend', async () => {
        const api = createApi();
        api.buatSiklus.mockResolvedValue({ cycle_id: 1393, kode_cycle: 'PA-2026-H1', status: 'DRAFT' });
        const state = usePaCycleSetup(api);

        state.bukaBuat();
        Object.assign(state.form, {
            /* Sengaja diisi walau tidak akan dikirim: layar boleh saja masih memegang
               medan ini, dan yang dijaga uji ini adalah bahwa payloadnya TIDAK ikut
               membawanya — kodenya dibuat server. */
            kode_cycle: 'pa-2026-h1',
            nama_cycle: 'Semester 1 2026',
            tahun: '2026',
            deskripsi: '',
            periode_dinilai_dari: '2026-01-01',
            periode_dinilai_sampai: '2026-06-30',
            pengisian_dari: '2026-07-01',
            pengisian_sampai: '2026-07-31',
            tanggal_acuan_organisasi: '2026-06-30',
        });

        const response = await state.simpan();

        expect(api.buatSiklus).toHaveBeenCalledWith({
            nama_cycle: 'Semester 1 2026',
            tahun: 2026,
            deskripsi: null,
            periode_dinilai_dari: '2026-01-01',
            periode_dinilai_sampai: '2026-06-30',
            pengisian_dari: '2026-07-01',
            pengisian_sampai: '2026-07-31',
            tanggal_acuan_organisasi: '2026-06-30',
        });
        // Ditegaskan tersendiri: kode yang ikut terkirim akan diabaikan server, jadi
        // kegagalannya tidak akan pernah menampakkan diri sebagai galat.
        expect(api.buatSiklus.mock.calls[0][0]).not.toHaveProperty('kode_cycle');

        expect(response.data.status).toBe('DRAFT');
        expect(state.saveSuccess.value).toContain('DRAFT');

        // Kode yang ditampilkan berasal dari BALASAN server, bukan dari isian layar.
        expect(state.saveSuccess.value).toContain('PA-2026-H1');
    });

    it('memuat detail authoritative dan hanya mengirim field yang berubah saat update', async () => {
        const api = createApi();
        api.detailSiklus.mockResolvedValue(detail);
        api.perbaruiSiklus.mockResolvedValue({
            cycle_id: 657,
            periode_berubah: [],
            perlu_siapkan_ulang: false,
        });
        const state = usePaCycleSetup(api);

        await state.bukaUbah(cycle);
        state.form.nama_cycle = 'Siklus Contoh Revisi';
        const response = await state.simpan();

        expect(api.detailSiklus).toHaveBeenCalledWith(657);
        expect(api.perbaruiSiklus).toHaveBeenCalledWith(657, { nama_cycle: 'Siklus Contoh Revisi' });
        expect(response.data.perlu_siapkan_ulang).toBe(false);
    });

    it('mempertahankan pesan 422 dan metadata error dari API', async () => {
        const api = createApi();
        api.buatSiklus.mockRejectedValue(Object.assign(new Error('Kode siklus PA-2026-H1 sudah dipakai.'), {
            status: 422,
            error_code: 'PA_CYCLE_CODE_EXISTS',
            request_id: 'req-pa-cycle-1',
        }));
        const state = usePaCycleSetup(api);

        state.bukaBuat();
        Object.assign(state.form, { kode_cycle: 'PA-2026-H1', nama_cycle: 'Siklus Baru', tahun: '2026' });
        const response = await state.simpan();

        expect(response.error.message).toBe('Kode siklus PA-2026-H1 sudah dipakai.');
        expect(state.saveError.value).toBe('Kode siklus PA-2026-H1 sudah dipakai.');
        expect(state.saveErrorStatus.value).toBe(422);
        expect(state.saveErrorCode.value).toBe('PA_CYCLE_CODE_EXISTS');
        expect(state.saveRequestId.value).toBe('req-pa-cycle-1');
    });
});
