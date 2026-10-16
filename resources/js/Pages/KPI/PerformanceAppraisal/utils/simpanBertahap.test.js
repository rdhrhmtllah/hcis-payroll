import { describe, expect, it } from 'vitest';
import { pesanGagalSebagian } from './simpanBertahap.js';

describe('pesanGagalSebagian', () => {
    it('menyebut berapa yang sudah tersimpan, di mana berhenti, dan sebabnya', () => {
        const p = pesanGagalSebagian({ tersimpan: 3, total: 5, gagalDi: 'MANAGER', galat: new Error('Bobot tidak 100') });
        expect(p).toContain('3 dari 5 level sudah tersimpan');
        expect(p).toContain('MANAGER');
        expect(p).toContain('Bobot tidak 100');
    });

    it('membedakan gagal sejak permintaan pertama', () => {
        expect(pesanGagalSebagian({ tersimpan: 0, total: 4, galat: 'Jaringan putus', satuan: 'sel' }))
            .toBe('Tidak ada sel yang tersimpan — gagal: Jaringan putus');
    });
});
