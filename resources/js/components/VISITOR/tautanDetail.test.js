import { describe, it, expect } from 'vitest';
import { bacaRujukanDetail, bacaJenisDetail } from './tautanDetail';

describe('bacaRujukanDetail', () => {
    it('menerima token tersamar dari notifikasi', () => {
        // Bentuk nyata token: base64url dari Crypt::encryptString, panjang ~200+.
        const token = 'eyJpdiI6Ik5xTFhBNS1hMDlIYnZWLXcwZyIsInZhbHVlIjoiWm1abV8xMjMifQ';

        expect(bacaRujukanDetail(`?detail=${token}`)).toBe(token);
        expect(bacaRujukanDetail(`?detail=${token}&jenis=tamu`)).toBe(token);
    });

    it('masih menerima Id polos demi notifikasi lama dan klik dari tabel', () => {
        expect(bacaRujukanDetail('?detail=412')).toBe('412');
        expect(bacaRujukanDetail('detail=7')).toBe('7');
        expect(bacaRujukanDetail('?tab=status&detail=99')).toBe('99');
    });

    it('mengembalikan null bila tidak ada detail yang bisa dibuka', () => {
        expect(bacaRujukanDetail('')).toBeNull();
        expect(bacaRujukanDetail('?tab=status')).toBeNull();
        expect(bacaRujukanDetail(undefined)).toBeNull();
        expect(bacaRujukanDetail('?detail=')).toBeNull();
    });

    it('menolak nilai yang akan merusak jalur permintaan', () => {
        // Nilainya ditempel ke path `/transaksipermit/<nilai>`. Karakter di luar
        // abjad token memecah ruas path atau menyeret permintaan ke tempat lain.
        expect(bacaRujukanDetail('?detail=../../etc/passwd')).toBeNull();
        expect(bacaRujukanDetail('?detail=a/b')).toBeNull();
        expect(bacaRujukanDetail('?detail=a b')).toBeNull();
        expect(bacaRujukanDetail('?detail=a+b')).toBeNull();
        expect(bacaRujukanDetail('?detail=a.b')).toBeNull();
        expect(bacaRujukanDetail('?detail=a=b')).toBeNull();
        expect(bacaRujukanDetail(`?detail=${'a'.repeat(2001)}`)).toBeNull();
    });

    it('menolak Id polos yang bukan transaksi', () => {
        expect(bacaRujukanDetail('?detail=0')).toBeNull();
        expect(bacaRujukanDetail('?detail=-5')).toBeNull();
        expect(bacaRujukanDetail('?detail=1.5')).toBeNull();
    });

    it('menolak sampah pendek yang kebetulan berabjad token', () => {
        // Token Crypt selalu ratusan karakter; potongan pendek tak mungkin sah.
        expect(bacaRujukanDetail('?detail=abc')).toBeNull();
        expect(bacaRujukanDetail('?detail=-5')).toBeNull();
        expect(bacaRujukanDetail(`?detail=${'a'.repeat(31)}`)).toBeNull();
        expect(bacaRujukanDetail(`?detail=${'a'.repeat(32)}`)).toBe('a'.repeat(32));
    });
});

describe('bacaJenisDetail', () => {
    it('mengenali jenis transaksi yang dituju', () => {
        expect(bacaJenisDetail('?detail=412&jenis=permit')).toBe('permit');
        expect(bacaJenisDetail('?detail=77&jenis=tamu')).toBe('tamu');
        expect(bacaJenisDetail('?jenis=TAMU')).toBe('tamu');
    });

    it('mengembalikan null untuk jenis yang tidak dikenal atau kosong', () => {
        expect(bacaJenisDetail('?detail=412')).toBeNull();
        expect(bacaJenisDetail('?jenis=kendaraan')).toBeNull();
        expect(bacaJenisDetail('')).toBeNull();
        expect(bacaJenisDetail(undefined)).toBeNull();
    });
});
