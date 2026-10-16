import { describe, expect, it } from 'vitest';
import { registry, getEntry, VALID_KIND, VALID_STATUS } from './registry';

describe('registry design system', () => {
    it('setiap entri punya medan wajib dan nilai yang sah', () => {
        expect(registry.length).toBeGreaterThan(0);

        for (const entry of registry) {
            expect(typeof entry.slug, `slug pada ${entry.name}`).toBe('string');
            expect(entry.slug).toMatch(/^[a-z0-9-]+$/);
            expect(typeof entry.name).toBe('string');
            expect(VALID_KIND).toContain(entry.kind);
            expect(VALID_STATUS).toContain(entry.status);
            expect(typeof entry.summary).toBe('string');
            expect(entry.summary.length).toBeGreaterThan(10);
            expect(Array.isArray(entry.rules.do)).toBe(true);
            expect(Array.isArray(entry.rules.dont)).toBe(true);
            expect(Array.isArray(entry.replaces)).toBe(true);
        }
    });

    it('slug bersifat unik', () => {
        const slugs = registry.map((e) => e.slug);
        expect(new Set(slugs).size).toBe(slugs.length);
    });

    it('entri owned mendeklarasikan props, entri borrowed mendeklarasikan batas pemakaian', () => {
        for (const entry of registry) {
            if (entry.kind === 'owned' || entry.kind === 'native') {
                expect(Array.isArray(entry.props), `props pada ${entry.slug}`).toBe(true);
            } else {
                expect(Array.isArray(entry.allowed), `allowed pada ${entry.slug}`).toBe(true);
                expect(Array.isArray(entry.forbidden), `forbidden pada ${entry.slug}`).toBe(true);
                for (const f of entry.forbidden) {
                    expect(typeof f.what).toBe('string');
                    expect(f.why.length, `alasan larangan ${f.what}`).toBeGreaterThan(10);
                }
            }
        }
    });

    it('getEntry mengembalikan entri berdasarkan slug dan undefined bila tak ada', () => {
        expect(getEntry('button').name).toBe('HcisButton');
        expect(getEntry('tidak-ada')).toBeUndefined();
    });
});
