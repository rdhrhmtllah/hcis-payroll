import { describe, expect, it } from 'vitest';
import { gabungJawabanLokal } from './checklistLokal';

describe('gabungJawabanLokal', () => {
    it('jawaban yang belum disimpan bertahan setelah detail dimuat ulang', () => {
        const lama = [
            { Id_Permit_Checklist: 1, localJawaban: 'YA', localCatatan: 'ok', tampilCatatan: true },
            { Id_Permit_Checklist: 2, localJawaban: 'TIDAK', localCatatan: 'rem aus', tampilCatatan: true },
        ];
        const baru = [
            { Id_Permit_Checklist: 1, localJawaban: '', localCatatan: '', tampilCatatan: false },
            { Id_Permit_Checklist: 3, localJawaban: '', localCatatan: '', tampilCatatan: false },
        ];

        expect(gabungJawabanLokal(baru, lama)).toEqual([
            { Id_Permit_Checklist: 1, localJawaban: 'YA', localCatatan: 'ok', tampilCatatan: true },
            { Id_Permit_Checklist: 3, localJawaban: '', localCatatan: '', tampilCatatan: false },
        ]);
    });

    it('tanpa isian lama, baris baru dipakai apa adanya', () => {
        const baru = [{ Id_Permit_Checklist: 1, localJawaban: 'YA', localCatatan: '', tampilCatatan: false }];
        expect(gabungJawabanLokal(baru, [])).toEqual(baru);
    });
});
