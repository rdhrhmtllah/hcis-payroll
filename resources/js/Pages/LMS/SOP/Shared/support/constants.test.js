import { describe, expect, it } from 'vitest';
import { BLOK, PROFIL_TIPE, blokBerlaku } from './constants';

/**
 * Cermin backend SopTipeItem::PROFIL. Kalau keduanya menyimpang, layar dan
 * server tidak lagi sepakat soal field mana yang berlaku — dan itu persis
 * keadaan yang peta ini dibuat untuk mengakhiri.
 */
describe('profil tipe (cermin backend SopTipeItem)', () => {
    it('nomor resmi hanya milik DOKUMEN; pengesahan milik DOKUMEN dan FORM', () => {
        expect(blokBerlaku('DOKUMEN', BLOK.NOMOR_RESMI)).toBe(true);
        expect(blokBerlaku('DOKUMEN', BLOK.PENGESAHAN)).toBe(true);
        // FORM ikut disahkan sejak 5 Okt 2026 (opsional di server).
        expect(blokBerlaku('FORM', BLOK.PENGESAHAN)).toBe(true);
        expect(blokBerlaku('FORM', BLOK.NOMOR_RESMI)).toBe(false);

        for (const t of ['VIDEO', 'KUIS']) {
            expect(blokBerlaku(t, BLOK.NOMOR_RESMI), t).toBe(false);
            expect(blokBerlaku(t, BLOK.PENGESAHAN), t).toBe(false);
        }
    });

    it('berkas untuk tipe file, ujian untuk kuis', () => {
        for (const t of ['DOKUMEN', 'FORM', 'VIDEO']) {
            expect(blokBerlaku(t, BLOK.BERKAS), t).toBe(true);
            expect(blokBerlaku(t, BLOK.UJIAN), t).toBe(false);
        }

        expect(blokBerlaku('KUIS', BLOK.BERKAS)).toBe(false);
        expect(blokBerlaku('KUIS', BLOK.UJIAN)).toBe(true);
    });

    it('tipe tak dikenal tidak memberlakukan blok apa pun', () => {
        expect(blokBerlaku('TAUTAN', BLOK.BERKAS)).toBe(false);
        expect(blokBerlaku(undefined, BLOK.BERKAS)).toBe(false);
        expect(blokBerlaku(null, BLOK.PENGESAHAN)).toBe(false);
    });

    /* Masa berlaku dicabut 15 Agustus 2026. Cermin ini tidak boleh jadi pintu
       masuknya kembali. */
    it('tidak mengenal masa berlaku', () => {
        for (const blok of Object.values(PROFIL_TIPE).flat()) {
            expect(blok).not.toBe('kadaluarsa');
            expect(blok).not.toBe('review_berikutnya');
        }
    });

    it('lampiran form kosong hanya untuk FORM', () => {
        expect(blokBerlaku('FORM', BLOK.LAMPIRAN)).toBe(true);
        for (const t of ['DOKUMEN', 'VIDEO', 'KUIS']) {
            expect(blokBerlaku(t, BLOK.LAMPIRAN), t).toBe(false);
        }
    });
});
