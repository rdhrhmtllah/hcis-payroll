import { describe, expect, it, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';

vi.mock('axios', () => ({ default: { get: vi.fn(() => Promise.resolve({ data: {} })) } }));

import KontenPickerModal from './KontenPickerModal.vue';

/**
 * Aturan format per tujuan pemilih.
 *
 * Contoh pengisian FORM wajib PDF sejak 10 Okt 2026 — hanya PDF yang bisa
 * ditahan "hanya lihat" di pembaca aman. Form kosong (lampiran) memakai pemilih
 * yang sama tetapi justru lazim berupa Word/Excel, jadi aturannya terpisah.
 * Server menagih aturan PDF di gerbang terbit; ini hanya mencegah admin memilih
 * yang pasti ditolak.
 */
const pasang = (tipeItem) => shallowMount(KontenPickerModal, { props: { visible: false, tipeItem } });

const PDF = { ext: 'pdf' };
const XLSX = { ext: 'xlsx' };
const DOCX = { ext: 'docx' };

describe('KontenPickerModal — aturan format', () => {
    it('contoh pengisian FORM hanya menerima PDF', () => {
        const { aturan } = pasang('FORM').vm;

        expect(aturan.uji(PDF)).toBe(true);
        expect(aturan.uji(XLSX)).toBe(false);
        expect(aturan.uji(DOCX)).toBe(false);
    });

    it('form kosong menerima semua format', () => {
        const { aturan } = pasang('LAMPIRAN_FORM').vm;

        for (const f of [PDF, XLSX, DOCX]) expect(aturan.uji(f)).toBe(true);
        // Bar keterangan menyebut nama yang terbaca manusia, bukan kode tujuannya.
        expect(aturan.nama).toBe('Form kosong');
    });

    it('PDF dikenali dari mime bila ekstensi kosong (baris lama)', () => {
        const { aturan } = pasang('FORM').vm;

        expect(aturan.uji({ ext: '', metadata: { typeFile: 'application/pdf' } })).toBe(true);
    });

    it('tanpa tipe tidak menyaring apa pun (builder training LMS)', () => {
        expect(pasang('').vm.aturan).toBeNull();
    });
});
