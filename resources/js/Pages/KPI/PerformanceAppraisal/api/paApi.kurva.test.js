import axios from 'axios';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import paApi from './paApi.js';

vi.mock('axios', () => ({ default: vi.fn() }));

const P = '/api/v1/performance-appraisal';

describe('seam API kurva grade', () => {
    beforeEach(() => {
        axios.mockReset();
        axios.mockResolvedValue({ data: { result: {} } });
    });

    it.each([
        ['kurvaSiklus', [7], 'get', `${P}/cycles/7/kurva`, undefined],
        ['kunciKurva', [7], 'post', `${P}/cycles/7/kurva/kunci`, undefined],
        ['bukaKurva', [7, 'Grouping salah'], 'post', `${P}/cycles/7/kurva/buka`, { alasan: 'Grouping salah' }],
        ['ubahMetodeGrade', [7, 'KURVA'], 'put', `${P}/cycles/7/kurva/metode`, { metode: 'KURVA' }],
        [
            'aturGrupKurvaSiklus',
            [7, [{ kode: 'STAF', nama: 'Staf', level_ids: [2, 3] }]],
            'put',
            `${P}/cycles/7/kurva/grup`,
            { grup: [{ kode: 'STAF', nama: 'Staf', level_ids: [2, 3] }] },
        ],
        ['daftarGrupKurva', [], 'get', `${P}/master/grup-kurva`, undefined],
        [
            'simpanGrupKurva',
            [[{ kode: 'STAF', nama: 'Staf', level_ids: [2] }]],
            'put',
            `${P}/master/grup-kurva`,
            { grup: [{ kode: 'STAF', nama: 'Staf', level_ids: [2] }] },
        ],
    ])('%s memanggil alamat dan badan yang benar', async (nama, args, method, url, data) => {
        await paApi[nama](...args);
        expect(axios).toHaveBeenLastCalledWith({ method, url, data });
    });
});
