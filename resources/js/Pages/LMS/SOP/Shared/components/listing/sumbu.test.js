import { describe, expect, it } from 'vitest';
import {
    SUMBU,
    AKSIS,
    TANPA,
    sumbuDari,
    nilaiSumbu,
    bangunGrup,
    opsiSumbu,
    terapkanSaringan,
    emberTenggat,
    emberEstimasi,
} from './sumbu.js';

/*
 * Yang dijaga di sini adalah SATU sifat yang paling mudah rusak: sumbu
 * bernilai jamak menempatkan satu tugas di beberapa kelompok, sehingga jumlah
 * per kelompok TIDAK menjumlah ke jumlah tugas. Selama itu disebut, ia benar.
 * Begitu tidak, ia terbaca sebagai angka yang salah.
 */

const tema = () => ({ color: '#111', icon: 'x' });

function tugas(over = {}) {
    return {
        Nama_Paket: 'Paket A',
        Prioritas: 'NORMAL',
        Persen: 0,
        Total_Item: 4,
        Item_Selesai: 0,
        Item_Perlu_Ulang: 0,
        Is_Terlambat: false,
        Tenggat: null,
        Estimasi: null,
        Sumber: 'MANUAL',
        Departemen: [],
        Jenis_Dokumen: [],
        Apqc: [],
        ...over,
    };
}

const dept = (id, nama) => ({ id, nama, kode: null, level: null });

describe('sumbuDari', () => {
    it('melempar pada kunci tak dikenal, tidak jatuh ke sumbu pertama', () => {
        // Cadangan diam-diam akan mengelompokkan hal yang sama sekali berbeda
        // dari yang diminta, dengan hasil yang tampak masuk akal.
        expect(() => sumbuDari('depatremen')).toThrow(/tidak dikenal/i);
    });

    it('mengenal apqc walau ia tidak ditawarkan sebagai sumbu rail', () => {
        expect(AKSIS.apqc).toBeTruthy();
        expect(SUMBU.some((s) => s.key === 'apqc')).toBe(false);
    });
});

describe('nilaiSumbu', () => {
    it('tugas tanpa nilai TIDAK lenyap — ia dapat kelompok "tanpa"', () => {
        const n = nilaiSumbu(tugas({ Departemen: [] }), 'departemen');

        expect(n).toHaveLength(1);
        expect(n[0].nama).toBe(TANPA.departemen);
        expect(n[0].kosong).toBe(true);
    });

    it('dokumen bertuan-kosong tetap dihitung sebagai satu kelompok "tanpa"', () => {
        const n = nilaiSumbu(tugas({ Departemen: [dept(null, null)] }), 'departemen');

        expect(n).toHaveLength(1);
        expect(n[0].nama).toBe(TANPA.departemen);
    });
});

describe('bangunGrup', () => {
    it('sumbu tunggal: penempatan sama dengan jumlah tugas', () => {
        const r = bangunGrup([tugas(), tugas({ Prioritas: 'KRITIS' })], 'prioritas', tema);

        expect(r.jamak).toBe(false);
        expect(r.tugas).toBe(2);
        expect(r.penempatan).toBe(2);
        expect(r.grup).toHaveLength(2);
    });

    it('sumbu jamak: satu paket dua departemen muncul di KEDUA kelompok', () => {
        const r = bangunGrup(
            [tugas({ Departemen: [dept(1, 'HSE'), dept(2, 'Produksi')] })],
            'departemen',
            tema,
        );

        expect(r.grup.map((g) => g.nama).sort()).toEqual(['HSE', 'Produksi']);
        expect(r.tugas).toBe(1);
        // Inilah selisih yang WAJIB disebut layar.
        expect(r.penempatan).toBe(2);
        expect(r.jamak).toBe(true);
    });

    it('kelompok "tanpa" selalu di urutan terakhir, berapa pun angkanya', () => {
        const r = bangunGrup(
            [
                tugas({ Departemen: [] }),
                tugas({ Departemen: [] }),
                tugas({ Departemen: [] }),
                tugas({ Departemen: [dept(1, 'HSE')], Is_Terlambat: true }),
            ],
            'departemen',
            tema,
        );

        // Meski "Tanpa Pemilik" berisi tiga dan HSE hanya satu.
        expect(r.grup[r.grup.length - 1].nama).toBe(TANPA.departemen);
    });

    /* Sumbu Kategori dicabut 28 Agu 2026 bersama masternya, dan tautan
       "lihat di Perpustakaan" ikut dicabut: Perpustakaan tak lagi berak per
       kategori, jadi tautan itu hanya bisa menjanjikan rak yang tak ada.

       Penjaganya dibalik — yang dijaga sekarang KETIADAAN sumbu itu. Tanpa
       ini, seseorang yang menghidupkan lagi AKSIS.kategori tak akan tahu
       bahwa ia menghidupkan tautan yang menunjuk ke halaman 404. */
    it('sumbu Kategori sudah tak dikenal, dan tak ada grup yang membawa tautan Perpustakaan', () => {
        expect(SUMBU.map((s) => s.key)).toEqual(['departemen', 'jenis', 'prioritas']);
        expect(() => bangunGrup([tugas()], 'kategori', tema)).toThrow(/tidak dikenal/i);

        const dep = bangunGrup([tugas({ Departemen: [dept(1, 'HSE')] })], 'departemen', tema);
        expect(dep.grup[0]).not.toHaveProperty('id_kategori');
    });

    it('persen kelompok dihitung dari item, bukan dari jumlah paket', () => {
        const r = bangunGrup(
            [
                tugas({ Departemen: [dept(1, 'HSE')], Total_Item: 10, Item_Selesai: 5 }),
                tugas({ Departemen: [dept(1, 'HSE')], Total_Item: 10, Item_Selesai: 0 }),
            ],
            'departemen',
            tema,
        );

        expect(r.grup[0].persen).toBe(25);
    });
});

describe('opsiSumbu', () => {
    it('menawarkan "tanpa pemilik" sebagai pilihan, bukan menyembunyikannya', () => {
        const o = opsiSumbu([tugas({ Departemen: [dept(1, 'HSE')] }), tugas({ Departemen: [] })], 'departemen');

        expect(o.map((x) => x.value)).toContain('__kosong__');
        // Dan ia selalu terakhir.
        expect(o[o.length - 1].value).toBe('__kosong__');
    });

    it('tidak menawarkan pilihan yang tak menyentuh tugas siapa pun', () => {
        const o = opsiSumbu([tugas({ Departemen: [dept(1, 'HSE')] })], 'departemen');

        expect(o).toHaveLength(1);
        expect(o[0].label).toBe('HSE');
    });
});

describe('emberTenggat', () => {
    it.each([
        [null, -5, 'tanpa'],
        ['2026-01-01', -1, 'lewat'],
        ['2026-01-01', 0, 'minggu'],
        ['2026-01-01', 7, 'minggu'],
        ['2026-01-01', 8, 'bulan'],
        ['2026-01-01', 30, 'bulan'],
        ['2026-01-01', 31, 'nanti'],
    ])('tenggat %s sisa %i hari → %s', (tgl, sisa, harap) => {
        expect(emberTenggat(tugas({ Tenggat: tgl }), sisa)).toBe(harap);
    });
});

describe('emberEstimasi', () => {
    it.each([
        [null, null],
        [10, 'pendek'],
        [11, 'sedang'],
        [30, 'sedang'],
        [31, 'panjang'],
    ])('estimasi %s → %s', (m, harap) => {
        expect(emberEstimasi(tugas({ Estimasi: m }))).toBe(harap);
    });
});

describe('terapkanSaringan', () => {
    const sisaHari = (x) => (x.__sisa === undefined ? 99 : x.__sisa);

    const data = [
        tugas({ Nama_Paket: 'Evakuasi', Departemen: [dept(1, 'HSE')], Jenis_Dokumen: [dept(9, 'Prosedur')] }),
        tugas({ Nama_Paket: 'Cuti', Departemen: [dept(2, 'HR')], Jenis_Dokumen: [dept(8, 'Kebijakan')] }),
        tugas({ Nama_Paket: 'Kuis Umum', Departemen: [], Jenis_Dokumen: [] }),
    ];

    it('tanpa saringan, semuanya lewat', () => {
        expect(terapkanSaringan(data, {}, sisaHari)).toHaveLength(3);
    });

    it('departemen: memilih HSE menyisakan yang menyentuh HSE saja', () => {
        const r = terapkanSaringan(data, { departemen: ['1'] }, sisaHari);

        expect(r.map((x) => x.Nama_Paket)).toEqual(['Evakuasi']);
    });

    it('departemen: "tanpa pemilik" bisa dipilih dan benar-benar menyaring', () => {
        const r = terapkanSaringan(data, { departemen: ['__kosong__'] }, sisaHari);

        expect(r.map((x) => x.Nama_Paket)).toEqual(['Kuis Umum']);
    });

    it('departemen: dua pilihan bersifat ATAU, bukan DAN', () => {
        const r = terapkanSaringan(data, { departemen: ['1', '2'] }, sisaHari);

        expect(r).toHaveLength(2);
    });

    it('paket lintas-departemen lolos saringan mana pun yang menyentuhnya', () => {
        const lintas = [tugas({ Nama_Paket: 'Lintas', Departemen: [dept(1, 'HSE'), dept(2, 'HR')] })];

        expect(terapkanSaringan(lintas, { departemen: ['1'] }, sisaHari)).toHaveLength(1);
        expect(terapkanSaringan(lintas, { departemen: ['2'] }, sisaHari)).toHaveLength(1);
        expect(terapkanSaringan(lintas, { departemen: ['3'] }, sisaHari)).toHaveLength(0);
    });

    it('jenis dokumen menyaring', () => {
        expect(terapkanSaringan(data, { jenis: ['8'] }, sisaHari).map((x) => x.Nama_Paket)).toEqual(['Cuti']);
    });

    it('tenggat memakai ember, bukan tanggal', () => {
        const d = [
            tugas({ Nama_Paket: 'Lewat', Tenggat: '2026-01-01', __sisa: -3 }),
            tugas({ Nama_Paket: 'Mepet', Tenggat: '2026-01-01', __sisa: 2 }),
            tugas({ Nama_Paket: 'Bebas' }),
        ];

        expect(terapkanSaringan(d, { tenggat: 'lewat' }, sisaHari).map((x) => x.Nama_Paket)).toEqual(['Lewat']);
        expect(terapkanSaringan(d, { tenggat: 'tanpa' }, sisaHari).map((x) => x.Nama_Paket)).toEqual(['Bebas']);
    });

    it('perlu dibaca ulang hanya menyisakan yang angkanya di atas nol', () => {
        const d = [tugas({ Nama_Paket: 'Berubah', Item_Perlu_Ulang: 2 }), tugas({ Nama_Paket: 'Tetap' })];

        expect(terapkanSaringan(d, { perlu_ulang: true }, sisaHari).map((x) => x.Nama_Paket)).toEqual(['Berubah']);
    });

    it('sumber: penugasan tanpa sumber dianggap ditugaskan admin', () => {
        const d = [tugas({ Nama_Paket: 'Lama', Sumber: null }), tugas({ Nama_Paket: 'Mandiri', Sumber: 'MANDIRI' })];

        expect(terapkanSaringan(d, { sumber: 'MANUAL' }, sisaHari).map((x) => x.Nama_Paket)).toEqual(['Lama']);
    });

    it('pencarian menjangkau nama departemen, bukan hanya nama paket', () => {
        // Orang mengetik "HSE" untuk menemukan yang berkaitan dengan HSE,
        // bukan hanya paket yang kebetulan bernama HSE.
        const r = terapkanSaringan(data, { search: 'hse' }, sisaHari);

        expect(r.map((x) => x.Nama_Paket)).toEqual(['Evakuasi']);
    });

    it('saringan bertumpuk bersifat DAN antar jenis saringan', () => {
        const r = terapkanSaringan(data, { departemen: ['1'], jenis: ['8'] }, sisaHari);

        expect(r).toHaveLength(0);
    });
});
