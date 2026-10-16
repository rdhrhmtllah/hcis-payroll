import { describe, expect, it } from 'vitest';
import { BATAS_BAKU, batasDariConfig, hitungMetrikParameter, jepitRasio } from './kpiCap';

const BATAS = { min: 50, max: 125 };

describe('jepitRasio', () => {
    it('menjepit rasio di atas batas atas', () => {
        expect(jepitRasio(3, BATAS)).toBe(1.25);
    });

    it('mengangkat rasio di bawah batas bawah', () => {
        expect(jepitRasio(0.2, BATAS)).toBe(0.5);
    });

    it('membiarkan rasio di dalam rentang', () => {
        expect(jepitRasio(0.9, BATAS)).toBe(0.9);
    });

    it('melewati sisi yang batasnya kosong', () => {
        expect(jepitRasio(3, { min: null, max: null })).toBe(3);
        expect(jepitRasio(0.2, { min: null, max: 125 })).toBe(0.2);
    });

    it('memperlakukan rasio bukan angka sebagai nol lalu menjepitnya', () => {
        expect(jepitRasio(Number.NaN, BATAS)).toBe(0.5);
        expect(jepitRasio(Number.POSITIVE_INFINITY, BATAS)).toBe(0.5);
    });
});

describe('hitungMetrikParameter', () => {
    it('menurunkan achievement dan skor dari rasio yang sudah dijepit', () => {
        const m = hitungMetrikParameter(3, 50, BATAS);

        expect(m.achievement).toBe(125);
        expect(m.score).toBe(62.5);
        expect(m.achievementRaw).toBe(300);
        expect(m.scoreRaw).toBe(150);
        expect(m.terjepit).toBe(true);
    });

    it('tidak menandai terjepit saat nilai tepat di batas', () => {
        expect(hitungMetrikParameter(1.25, 50, BATAS).terjepit).toBe(false);
    });

    it('meneruskan apa adanya saat batas kosong', () => {
        const m = hitungMetrikParameter(3, 50, { min: null, max: null });

        expect(m.achievement).toBe(300);
        expect(m.score).toBe(150);
        expect(m.terjepit).toBe(false);
    });

    it('menjepit rasio negatif ke lantai', () => {
        const m = hitungMetrikParameter(-2, 50, BATAS);

        expect(m.achievement).toBe(50);
        expect(m.score).toBe(25);
        expect(m.achievementRaw).toBe(-200);
        expect(m.terjepit).toBe(true);
    });
});

describe('batasDariConfig', () => {
    it('membaca batas per-parameter dari payload', () => {
        expect(batasDariConfig({ param_max_achievement_score: 125, param_min_achievement_score: 50 })).toEqual({
            min: 50,
            max: 125,
        });
    });

    it('membaca angka yang datang sebagai teks', () => {
        expect(batasDariConfig({ param_max_achievement_score: '120', param_min_achievement_score: '60' })).toEqual({
            min: 60,
            max: 120,
        });
    });

    it('memulangkan MATI hanya untuk null yang disengaja', () => {
        expect(batasDariConfig({ param_max_achievement_score: null, param_min_achievement_score: null })).toEqual({
            min: null,
            max: null,
        });
    });

    /**
     * Ini penjaga arah kegagalan. Batas kosong berarti "tidak menjepit", jadi
     * memulangkannya saat payload tidak bisa dibaca membuat layar menghitung
     * tanpa penjepitan sementara server tetap menjepit.
     */
    it('jatuh ke baku, bukan ke batas kosong, saat payload tidak memuatnya', () => {
        expect(batasDariConfig({ max_achievement_score: 125 })).toEqual(BATAS_BAKU);
        expect(batasDariConfig(null)).toEqual(BATAS_BAKU);
        expect(batasDariConfig(undefined)).toEqual(BATAS_BAKU);
    });

    it('jatuh ke baku saat nilainya bukan angka', () => {
        expect(batasDariConfig({ param_max_achievement_score: 'seratus', param_min_achievement_score: '' })).toEqual(
            BATAS_BAKU,
        );
    });

    it('baku klien mencerminkan baku server 125/50', () => {
        expect(BATAS_BAKU).toEqual({ min: 50, max: 125 });
    });

    it('tidak membagikan objek baku yang sama antar pemanggil', () => {
        const a = batasDariConfig(null);
        a.max = 999;

        expect(BATAS_BAKU.max).toBe(125);
    });
});
