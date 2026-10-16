import { flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { usePaLeader } from './usePaLeader.js';

const paApiMock = {
    timLeader: vi.fn(),
    detailAnggotaLeader: vi.fn(),
};

const team = {
    leader: { kode_karyawan: 'A4', nama: 'Aji' },
    cycle: { cycle_id: 657, kode_cycle: 'PA-DEMO-FE', nama_cycle: 'Siklus Demo' },
    anggota: [{ participant_id: 59657, nama: 'Amelia', terbit: true, nilai_akhir: 82.5 }],
    ringkasan: { total_anggota: 1, hasil_terbit: 1, hasil_tertahan: 0 },
    batas: { min_penilai_untuk_tampil: 3 },
    tanpa_tim: false,
};

const member = {
    participant_id: 59657,
    nama: 'Amelia',
    terbit: true,
    nilai_akhir: 82.5,
    kompetensi: [],
    kategori: [],
};

beforeEach(() => {
    vi.clearAllMocks();
    paApiMock.timLeader.mockResolvedValue(team);
    paApiMock.detailAnggotaLeader.mockResolvedValue(member);
});

describe('usePaLeader', () => {
    it('memuat dashboard tim dan detail anggota melalui API seam', async () => {
        const state = usePaLeader(paApiMock);

        await state.muatTim();
        await state.muatDetail(59657);

        expect(paApiMock.timLeader).toHaveBeenCalledWith(null);
        expect(paApiMock.detailAnggotaLeader).toHaveBeenCalledWith(59657);
        expect(state.tim.value.leader.nama).toBe('Aji');
        expect(state.detail.value.nama).toBe('Amelia');
        expect(state.anggotaTerpilih.value).toBe(59657);
    });

    it('mempertahankan flag tanpa_tim sebagai keadaan sah', async () => {
        paApiMock.timLeader.mockResolvedValue({ leader: { nama: 'Budi' }, anggota: [], tanpa_tim: true });
        const state = usePaLeader(paApiMock);

        await state.muatTim();

        expect(state.tim.value.tanpa_tim).toBe(true);
        expect(state.errorTim.value).toBe('');
    });

    it('tidak membiarkan response detail lama menimpa anggota terbaru', async () => {
        let resolveFirst;
        const first = new Promise((resolve) => { resolveFirst = resolve; });
        paApiMock.detailAnggotaLeader.mockReturnValueOnce(first).mockResolvedValueOnce({ ...member, participant_id: 60606, nama: 'Tari' });
        const state = usePaLeader(paApiMock);

        const firstRequest = state.muatDetail(59657);
        const secondRequest = state.muatDetail(60606);
        await secondRequest;
        resolveFirst({ ...member, nama: 'Amelia' });
        await firstRequest;
        await flushPromises();

        expect(state.detail.value.nama).toBe('Tari');
        expect(state.anggotaTerpilih.value).toBe(60606);
    });

    it('meneruskan pesan 422 dan metadata error untuk detail', async () => {
        paApiMock.detailAnggotaLeader.mockRejectedValue({
            message: 'Peserta tersebut bukan anggota tim Anda.',
            status: 422,
            error_code: 'PA_LEADER_MEMBER_SCOPE',
            request_id: 'req-leader-422',
        });
        const state = usePaLeader(paApiMock);

        await state.muatDetail(999);

        expect(state.errorDetail.value).toBe('Peserta tersebut bukan anggota tim Anda.');
        expect(state.statusErrorDetail.value).toBe(422);
        expect(state.kodeErrorDetail.value).toBe('PA_LEADER_MEMBER_SCOPE');
        expect(state.requestIdDetail.value).toBe('req-leader-422');
    });
});

