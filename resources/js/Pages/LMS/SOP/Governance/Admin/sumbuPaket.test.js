import { describe, expect, it } from 'vitest';
import {
    EMBER_ESTIMASI,
    EMBER_TERBIT,
    KOSONG,
    SUMBU_PAKET,
    TANPA,
    adaSaringanAktif,
    bangunGrupPaket,
    paramsPaket,
    railAktif,
    saringanKosong,
    sumbuPaketDari,
} from './sumbuPaket.js';

/*
 * Yang dijaga di sini adalah dua sifat yang paling mahal kalau rusak, dan
 * dua-duanya tak terlihat di layar sampai terlambat:
 *
 *   1. Parameter yang TIDAK dikirim. Saringan bawaan yang ikut terkirim
 *      membuat endpoint hitungan dan endpoint daftar menerima permintaan yang
 *      berbeda untuk keadaan yang sama.
 *   2. Rail yang menyala pada satu nilai padahal daftarnya disaring tiga
 *      nilai. Layar akan membacakan "sedang melihat Prioritas A" atas daftar
 *      yang berisi tiga tingkat, dan tak ada yang tahu itu bohong.
 */

describe('sumbuPaketDari', () => {
    it('mengenali seluruh sumbu yang ditawarkan rail', () => {
        SUMBU_PAKET.forEach((s) => {
            expect(sumbuPaketDari(s.key).key).toBe(s.key);
        });
    });

    it('MELEMPAR pada sumbu tak dikenal, tidak jatuh ke sumbu pertama', () => {
        expect(() => sumbuPaketDari('sumbu_palsu')).toThrow(/tidak dikenal/i);
    });

    /* Sumbu Kategori dicabut 28 Agu 2026 bersama masternya. Penjaganya dibalik:
       yang dijaga sekarang KETIADAANNYA, supaya menghidupkannya kembali tanpa
       menghidupkan kolomnya di server tidak lolos diam-diam. */
    it('sumbu Kategori sudah tak dikenal', () => {
        expect(SUMBU_PAKET.map((s) => s.key)).toEqual(['departemen', 'jenis', 'apqc', 'prioritas', 'status', 'distribusi']);
        expect(() => sumbuPaketDari('kategori')).toThrow(/tidak dikenal/i);
    });
});

describe('paramsPaket — yang tak menyaring, tak dikirim', () => {
    it('saringan kosong menghasilkan nol parameter', () => {
        expect(paramsPaket(saringanKosong())).toEqual({});
        expect(adaSaringanAktif(saringanKosong())).toBe(false);
    });

    it('urutan bawaan tidak pernah dikirim, urutan lain dikirim', () => {
        expect(paramsPaket({ ...saringanKosong(), sort: 'newest' }).sort).toBeUndefined();
        expect(paramsPaket({ ...saringanKosong(), sort: 'nama' }).sort).toBe('nama');
    });

    it('sakelar mati tidak dikirim; sakelar hidup dikirim sebagai Y', () => {
        expect(paramsPaket({ ...saringanKosong(), absensi: false }).absensi).toBeUndefined();
        expect(paramsPaket({ ...saringanKosong(), absensi: true }).absensi).toBe('Y');
    });

    it('daftar berisi dikirim utuh sebagai daftar', () => {
        const p = paramsPaket({
            ...saringanKosong(),
            prioritas: ['KRITIS'],
            status: ['DRAFT', 'PUBLISHED'],
            distribusi: ['PUBLIK'],
        });

        expect(p.prioritas).toEqual(['KRITIS']);
        expect(p.status).toEqual(['DRAFT', 'PUBLISHED']);
        expect(p.distribusi).toEqual(['PUBLIK']);
    });

    it('daftar berisi nilai kosong dibersihkan, bukan dikirim apa adanya', () => {
        const p = paramsPaket({ ...saringanKosong(), prioritas: ['', null, 'KRITIS', undefined] });

        expect(p.prioritas).toEqual(['KRITIS']);
    });

    it('nilai tunggal non-daftar tetap dikirim sebagai daftar', () => {
        expect(paramsPaket({ ...saringanKosong(), status: 'DRAFT' }).status).toEqual(['DRAFT']);
    });

    it('ember terbit & estimasi dikirim sebagai keadaan, bukan tanggal', () => {
        const p = paramsPaket({ ...saringanKosong(), terbit: 'belum', estimasi: 'tanpa' });

        expect(p.terbit).toBe('belum');
        expect(p.estimasi).toBe('tanpa');
        expect(p.tgl_dari).toBeUndefined();
    });

    it('ember dan rentang tanggal hidup berdampingan, tidak saling membatalkan', () => {
        const p = paramsPaket({
            ...saringanKosong(),
            terbit: '30hari',
            tgl_publish: ['2026-03-03', '2026-03-17'],
        });

        expect(p.terbit).toBe('30hari');
        expect(p.tgl_dari).toBe('2026-03-03');
        expect(p.tgl_sampai).toBe('2026-03-17');
    });

    /*
     * Penjaga ini pernah MERAH dengan toISOString(): tengah malam 1 Maret di
     * WIB adalah 2026-02-28T17:00Z, jadi "sejak 1 Maret" ikut memuat 28
     * Februari. Tanggal dibaca dari komponen lokal, bukan dari UTC.
     */
    it('objek Date dipetakan dari komponen LOKAL, bukan lewat UTC', () => {
        const p = paramsPaket({
            ...saringanKosong(),
            tgl_publish: [new Date(2026, 2, 1, 0, 0, 0), new Date(2026, 2, 31, 23, 59, 0)],
        });

        expect(p.tgl_dari).toBe('2026-03-01');
        expect(p.tgl_sampai).toBe('2026-03-31');
    });

    it('rentang setengah terisi hanya mengirim ujung yang ada', () => {
        const p = paramsPaket({ ...saringanKosong(), tgl_publish: ['2026-03-03'] });

        expect(p.tgl_dari).toBe('2026-03-03');
        expect(p.tgl_sampai).toBeUndefined();
    });

    it('adaSaringanAktif menyala begitu satu ladang terisi', () => {
        expect(adaSaringanAktif({ ...saringanKosong(), search: 'mutu' })).toBe(true);
        expect(adaSaringanAktif({ ...saringanKosong(), status: ['DRAFT'] })).toBe(true);
    });
});

describe('bangunGrupPaket', () => {
    it('menerjemahkan baris server jadi kelompok RailSumbu yang lengkap', () => {
        const { grup, total } = bangunGrupPaket(
            [
                { nilai: 'KRITIS', nama: null, total: 12 },
                { nilai: 'NORMAL', nama: null, total: 5 },
            ],
            'prioritas',
        );

        expect(total).toBe(17);
        expect(grup[0].nilai).toBe('KRITIS');
        expect(grup[0].warna).toMatch(/^#/);
        expect(grup[0].ikon).toMatch(/^bi-/);
    });

    it('mengurutkan kelompok terbesar lebih dulu', () => {
        const { grup } = bangunGrupPaket(
            [
                { nilai: 'NORMAL', nama: 'Kecil', total: 2 },
                { nilai: 'KRITIS', nama: 'Besar', total: 9 },
            ],
            'prioritas',
        );

        expect(grup.map((g) => g.nama)).toEqual(['Besar', 'Kecil']);
    });

    it('kelompok tanpa nilai diberi nama & selalu di bawah, berapa pun angkanya', () => {
        const { grup } = bangunGrupPaket(
            [
                { nilai: null, nama: null, total: 99 },
                { nilai: 'KRITIS', nama: 'Kritis', total: 1 },
            ],
            'prioritas',
        );

        expect(grup[1].nama).toBe(TANPA.prioritas);
        expect(grup[1].nilai).toBe(KOSONG);
        expect(grup[1].kosong).toBe(true);
    });

    it('memberi label yang dibaca orang pada status & distribusi', () => {
        const status = bangunGrupPaket([{ nilai: 'PUBLISHED', total: 3 }], 'status');
        const dist = bangunGrupPaket([{ nilai: 'TERBATAS', total: 3 }], 'distribusi');

        expect(status.grup[0].nama).toBe('Terbit');
        expect(dist.grup[0].nama).toBe('Terbatas');
    });

    it('daftar kosong menghasilkan nol kelompok dan total nol', () => {
        expect(bangunGrupPaket([], 'status')).toEqual({ grup: [], total: 0 });
        expect(bangunGrupPaket(null, 'status')).toEqual({ grup: [], total: 0 });
    });
});

describe('railAktif', () => {
    it('menyala ketika sumbu itu disaring tepat satu nilai', () => {
        expect(railAktif({ prioritas: ['KRITIS'] }, 'prioritas')).toBe('KRITIS');
    });

    it('PADAM ketika sumbu itu disaring lebih dari satu nilai', () => {
        expect(railAktif({ prioritas: ['KRITIS', 'NORMAL'] }, 'prioritas')).toBe('');
    });

    it('padam ketika sumbu itu tidak disaring sama sekali', () => {
        expect(railAktif(saringanKosong(), 'prioritas')).toBe('');
        expect(railAktif({}, 'status')).toBe('');
    });

    it('membaca ladang milik sumbunya sendiri, bukan ladang tetangga', () => {
        const f = { ...saringanKosong(), status: ['DRAFT'] };

        expect(railAktif(f, 'status')).toBe('DRAFT');
        expect(railAktif(f, 'prioritas')).toBe('');
    });
});

describe('daftar ember', () => {
    it('ember terbit menyediakan keadaan yang tak bisa jadi rentang tanggal', () => {
        expect(EMBER_TERBIT.map((e) => e.value)).toContain('belum');
    });

    it('ember estimasi memberi tempat pada paket tanpa estimasi', () => {
        expect(EMBER_ESTIMASI.map((e) => e.value)).toContain('tanpa');
    });
});
