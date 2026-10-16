import { describe, expect, it } from 'vitest';
import { bagianModul } from './bagianMenu';

const grup = (id, sectionId = null) => ({ id, title: id, items: [{ id: `${id}-1` }], sectionId });

describe('Pengelompokan menu sidebar per bagian', () => {
    it('modul tanpa bagian tampil seperti semula: satu kelompok tanpa judul', () => {
        const module = { groups: [grup('personal'), grup('admin')], sections: [] };

        expect(bagianModul(module)).toEqual([{ id: null, title: '', icon: '', groups: module.groups }]);
    });

    it('data lama tanpa properti sections tetap aman', () => {
        expect(bagianModul({ groups: [grup('a')] })[0].groups.map((g) => g.id)).toEqual(['a']);
    });

    it('mengelompokkan grup sesuai bagian dari server dengan urutan bagian dipertahankan', () => {
        const module = {
            groups: [grup('kpi__dashboard', 'kpi'), grup('kpi__individu', 'kpi'), grup('pa__appraisal', 'pa'), grup('administrasi__kpi', 'administrasi')],
            sections: [
                { id: 'kpi', title: 'Key Performance Indicator', icon: 'bi bi-graph-up-arrow' },
                { id: 'pa', title: 'Performance Appraisal', icon: 'bi bi-clipboard2-check' },
                { id: 'administrasi', title: 'Administrasi', icon: 'bi bi-gear-fill' },
            ],
        };

        const hasil = bagianModul(module);

        expect(hasil.map((k) => k.title)).toEqual(['Key Performance Indicator', 'Performance Appraisal', 'Administrasi']);
        expect(hasil[0].groups.map((g) => g.id)).toEqual(['kpi__dashboard', 'kpi__individu']);
    });

    it('bagian yang semua grupnya tersaring pencarian ikut hilang', () => {
        const module = {
            groups: [grup('pa__appraisal', 'pa')],
            sections: [{ id: 'kpi', title: 'Key Performance Indicator' }, { id: 'pa', title: 'Performance Appraisal' }],
        };

        expect(bagianModul(module).map((k) => k.title)).toEqual(['Performance Appraisal']);
    });

    it('grup tanpa bagian di modul berbagian tetap tampil di atas tanpa judul', () => {
        const module = { groups: [grup('lainnya'), grup('kpi__dashboard', 'kpi')], sections: [{ id: 'kpi', title: 'KPI' }] };

        const hasil = bagianModul(module);

        expect(hasil[0]).toMatchObject({ title: '', groups: [expect.objectContaining({ id: 'lainnya' })] });
        expect(hasil[1].title).toBe('KPI');
    });
});
