import { describe, expect, it } from 'vitest';
import {
    adaPerubahan, berubah, butuhAlasan, butuhAlasanPeserta, diLuarRentang, formatNilai, galatPeserta, hitungKotak,
    payloadSimpan, payloadSimulasi, pindahKotak, susunKotak,
} from './balancing.js';

const peserta = (ubah = {}) => ({
    participant_id: 7,
    nama: 'Andi',
    status_tinjau: 'BELUM',
    alasan: null,
    kompetensi: [
        { competency_id: 1, nama: 'Integritas', sebelum: 70, sesudah: 70, alasan: null, ...ubah.k1 },
        { competency_id: 2, nama: 'Kerja sama', sebelum: 85, sesudah: 85, alasan: null },
    ],
    kualitatif: [{ competency_id: 21, nama: 'Strength', sebelum: null, sesudah: null, ...ubah.q }],
});

describe('balancing util', () => {
    it('perubahan memakai ambang 0,005 seperti backend', () => {
        expect(berubah(70, 70.004)).toBe(false);
        expect(berubah(70, 70.01)).toBe(true);
    });

    it('alasan wajib hanya bila nilainya berubah', () => {
        expect(butuhAlasan({ sebelum: 70, sesudah: 70, alasan: '' })).toBe(false);
        expect(butuhAlasan({ sebelum: 70, sesudah: 80, alasan: '  ' })).toBe(true);
        expect(butuhAlasan({ sebelum: 70, sesudah: 80, alasan: 'Konsisten' })).toBe(false);
    });

    it('rentang 0–100', () => {
        expect(diLuarRentang({ sesudah: 101 })).toBe(true);
        expect(diLuarRentang({ sesudah: -1 })).toBe(true);
        expect(diLuarRentang({ sesudah: '' })).toBe(true);
        expect(diLuarRentang({ sesudah: 100 })).toBe(false);
    });

    it('alasan dicatat per orang: wajib bila ada nilai atau catatan yang diubah', () => {
        expect(butuhAlasanPeserta(peserta())).toBe(false);
        expect(butuhAlasanPeserta(peserta({ k1: { sesudah: 80 } }))).toBe(true);
        expect(butuhAlasanPeserta({ ...peserta({ k1: { sesudah: 80 } }), alasan: '  ' })).toBe(true);
        expect(butuhAlasanPeserta({ ...peserta({ k1: { sesudah: 80 } }), alasan: 'Kesepakatan forum' })).toBe(false);
        expect(butuhAlasanPeserta(peserta({ q: { sesudah: '<p>baru</p>' } }))).toBe(true);
    });

    it('galatPeserta: rentang per kompetensi, alasan per orang', () => {
        expect(galatPeserta(peserta({ k1: { sesudah: 80 } }))).toEqual(['Andi: alasan perubahan wajib diisi karena ada nilai yang diubah.']);
        expect(galatPeserta({ ...peserta({ k1: { sesudah: 80 } }), alasan: 'Kesepakatan forum' })).toEqual([]);
        expect(galatPeserta({ ...peserta({ k1: { sesudah: 120 } }), alasan: 'x' })).toEqual(['Integritas: nilai After harus 0 sampai 100.']);
        expect(galatPeserta(peserta())).toEqual([]);
    });

    it('adaPerubahan melihat kompetensi dan kualitatif', () => {
        expect(adaPerubahan(peserta())).toBe(false);
        expect(adaPerubahan(peserta({ k1: { sesudah: 71 } }))).toBe(true);
        expect(adaPerubahan(peserta({ q: { sesudah: '<p>Rapi</p>' } }))).toBe(true);
    });

    it('payloadSimpan dan payloadSimulasi mengikuti kontrak API', () => {
        const p = peserta({ k1: { sesudah: '80', alasan: 'Konsisten' } });
        p.status_tinjau = 'TANPA_PERUBAHAN';
        expect(payloadSimpan([p])).toEqual([{
            participant_id: 7, status_tinjau: 'TANPA_PERUBAHAN', alasan: null,
            kompetensi: [
                { competency_id: 1, nilai_sesudah: 80, alasan: 'Konsisten' },
                { competency_id: 2, nilai_sesudah: 85, alasan: null },
            ],
            kualitatif: [{ competency_id: 21, isi_sesudah: '' }],
        }]);
        expect(payloadSimulasi([p])).toEqual([{
            participant_id: 7,
            kompetensi: [{ competency_id: 1, nilai_sesudah: 80 }, { competency_id: 2, nilai_sesudah: 85 }],
        }]);
    });

    it('status DIUBAH tidak dikirim; server yang menurunkannya', () => {
        const p = peserta();
        p.status_tinjau = 'DIUBAH';
        expect(payloadSimpan([p])[0].status_tinjau).toBeNull();
    });

    it('kotak HAV disusun dalam urutan grid standar dan dihitung per sisi', () => {
        const kuadran = [1, 2, 3, 4, 5, 6, 7, 8, 9].map((n) => ({ Nomor_Box: n, Kode_Kuadran: `K${n}` }));
        expect(susunKotak(kuadran).map((k) => k.Nomor_Box)).toEqual([7, 3, 1, 8, 4, 2, 9, 6, 5]);
        const baris = [
            { nama: 'A', sebelum: { kode_kuadran: 'K4' }, sesudah: { kode_kuadran: 'K1' } },
            { nama: 'B', sebelum: { kode_kuadran: 'K4' }, sesudah: { kode_kuadran: 'K4' } },
        ];
        expect(hitungKotak(baris, 'sebelum')).toEqual({ K4: 2 });
        expect(hitungKotak(baris, 'sesudah')).toEqual({ K1: 1, K4: 1 });
        expect(pindahKotak(baris)).toEqual([{ nama: 'A', dari: 'K4', ke: 'K1' }]);
    });

    it('formatNilai dua desimal gaya Indonesia', () => {
        expect(formatNilai(82.5)).toBe('82,50');
        expect(formatNilai(null)).toBe('–');
    });
});
