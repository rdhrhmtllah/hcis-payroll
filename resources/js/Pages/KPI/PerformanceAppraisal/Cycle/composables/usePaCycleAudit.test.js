import { beforeEach, describe, expect, it, vi } from 'vitest';
import { usePaCycleAudit } from './usePaCycleAudit.js';

const auditResponse = {
    siklus: { Id_PA_Cycle: 657, Kode_Cycle: 'PA-DEMO-FE', Status_Cycle: 'CLOSED' },
    kejadian: [{ waktu: '2026-09-01 10:00:00', jenis: 'KALIBRASI', participant_id: 59653 }],
    jenis_tersedia: ['KALIBRASI'],
    cacah: { keputusan_manusia: 3, oleh_sistem: 99 },
    batas: { konfigurasi_tanpa_nilai_lama: true, catatan: 'Nilai lama tidak disimpan.' },
    halaman: 1,
    per_halaman: 10,
    total: 102,
    total_halaman: 11,
    dipangkas: false,
};

function createApi() {
    return { auditSiklus: vi.fn() };
}

beforeEach(() => vi.clearAllMocks());

describe('usePaCycleAudit', () => {
    it('mengirim seluruh filter kontrak dan mempertahankan kedua cacah server', async () => {
        const api = createApi();
        api.auditSiklus.mockResolvedValue(auditResponse);
        const state = usePaCycleAudit(api);

        await state.buka({ Id_PA_Cycle: 657 });
        await state.saring(657, { jenis: 'KALIBRASI', participant_id: '59653', q: 'rapat', hanya_keputusan_manusia: true });

        expect(api.auditSiklus).toHaveBeenLastCalledWith(657, {
            jenis: 'KALIBRASI',
            participant_id: 59653,
            q: 'rapat',
            /* `1`, bukan `true`. Ini permintaan GET: boolean berangkat sebagai teks, dan
               aturan `boolean` Laravel menolak string "false" — setiap permintaan audit
               dijawab 422 "must be true or false". */
            hanya_keputusan_manusia: 1,
            halaman: 1,
            per_halaman: 10,
        });
        expect(state.cacah.value).toEqual({ keputusan_manusia: 3, oleh_sistem: 99 });
        expect(state.batas.value.catatan).toBe('Nilai lama tidak disimpan.');
    });

    it('menjaga hasil request terbaru ketika dua pemuatan audit berurutan bertumpuk', async () => {
        const api = createApi();
        let resolveFirst;
        api.auditSiklus
            .mockImplementationOnce(() => new Promise((resolve) => { resolveFirst = resolve; }))
            .mockResolvedValueOnce({ ...auditResponse, kejadian: [{ jenis: 'SIKLUS_DIKUNCI' }] });
        const state = usePaCycleAudit(api);

        const first = state.muat(657);
        const second = state.muat(657, { q: 'terbaru' });
        await second;
        resolveFirst({ ...auditResponse, kejadian: [{ jenis: 'KALIBRASI' }] });
        await first;

        expect(state.kejadian.value).toEqual([{ jenis: 'SIKLUS_DIKUNCI' }]);
    });

    it('menyimpan pesan dan metadata error tanpa menggantinya dengan pesan umum', async () => {
        const api = createApi();
        api.auditSiklus.mockRejectedValue(Object.assign(new Error('Audit hanya dapat dilihat oleh administrator.'), {
            status: 403,
            error_code: 'PA_AUDIT_FORBIDDEN',
            request_id: 'req-pa-audit-1',
        }));
        const state = usePaCycleAudit(api);

        await state.buka({ Id_PA_Cycle: 657 });

        expect(state.error.value).toBe('Audit hanya dapat dilihat oleh administrator.');
        expect(state.errorStatus.value).toBe(403);
        expect(state.errorCode.value).toBe('PA_AUDIT_FORBIDDEN');
        expect(state.requestId.value).toBe('req-pa-audit-1');
    });

    /*
     | SARINGAN YANG MATI TIDAK DIKIRIM SAMA SEKALI.
     |
     | `false` pada permintaan GET berangkat sebagai string "false", dan aturan `boolean`
     | Laravel menolaknya — 422 pada SETIAP pemuatan audit, bahkan ketika admin tidak
     | menyentuh saringannya. Cacatnya duduk di batas HTTP: layanan yang dipanggil langsung
     | bekerja, dan uji yang memalsukan api-nya juga hijau.
     */
    it('tidak mengirim saringan keputusan manusia ketika mati', async () => {
        const api = createApi();
        api.auditSiklus.mockResolvedValue(auditResponse);
        const state = usePaCycleAudit(api);

        await state.buka({ Id_PA_Cycle: 657 });

        const saringan = api.auditSiklus.mock.calls.at(-1)[1];

        expect(saringan.hanya_keputusan_manusia).toBeUndefined();
    });
});
