import { describe, expect, it } from 'vitest';

import {
    batasKetikan,
    batasTanggalLahir,
    bidangTampil,
    galatFormat,
    kunciTampil,
    nilaiSimpanAngka,
} from './formatBidang.js';

// Hari yang dipatok, supaya ujinya tidak berubah arti besok pagi.
const HARI_INI = new Date('2026-08-19T00:00:00');

// Cerminan config yang BERLAKU: batas usia 15-70 dicabut 3 Sep 2026, dan sejak
// itu 'tanggal_lahir' tidak lagi menerbitkan usia_min/usia_maks sama sekali.
// Fixture yang masih membawanya akan menguji aturan yang sudah tidak ada.
const tanggalLahir = { label: 'Tanggal Lahir', tipe: 'tanggal', format: 'tanggal_lahir' };

describe('galatFormat — tanggal lahir', () => {
    it('menolak tanggal di masa depan dengan kalimat yang bisa dibaca orang', () => {
        const pesan = galatFormat(tanggalLahir, '2026-08-26', HARI_INI);

        expect(pesan).toBeTruthy();
        // Yang keluar sebelumnya: "The tanggal lahir field must be a date before
        // -15 years." — kalimat mesin berisi potongan aturan mentah.
        expect(pesan).not.toMatch(/must be|field|-15 years/i);
        expect(pesan).toMatch(/masa depan/i);
    });

    // Batas usia 15-70 dicabut 3 Sep 2026 atas keputusan pengguna. Yang tersisa
    // di server hanya 'before_or_equal:today', jadi umur berapa pun diterima.
    // Uji ini menuntut PENERIMAAN supaya batas yang dipasang diam-diam kembali
    // akan menyalakannya — bukan sekadar menghapus uji lamanya.
    it('menerima yang dulu ditolak karena belum cukup umur', () => {
        expect(galatFormat(tanggalLahir, '2015-01-01', HARI_INI)).toBeNull();
    });

    it('menerima yang dulu ditolak karena melewati batas usia tertua', () => {
        expect(galatFormat(tanggalLahir, '1950-01-01', HARI_INI)).toBeNull();
    });

    it('menerima tanggal lahir yang wajar', () => {
        expect(galatFormat(tanggalLahir, '1995-04-17', HARI_INI)).toBeNull();
    });
});

describe('batasTanggalLahir', () => {
    it('menutup jendelanya HARI INI, sama dengan satu-satunya pagar yang tersisa di server', () => {
        const { paling_awal, paling_akhir } = batasTanggalLahir(tanggalLahir, HARI_INI);

        // before_or_equal:today -> paling lambat lahir hari ini juga.
        expect(paling_akhir).toBe('2026-08-19');

        // Tepi bawahnya 100 tahun: kalender sengaja LEBIH KETAT daripada server,
        // sebab arah yang berbahaya cuma satu — kalender menawarkan tanggal yang
        // kemudian ditolak. Sebaliknya tidak merugikan siapa pun.
        expect(paling_awal).toBe('1926-08-19');
    });

    it('mengikuti batas usia yang kelak dipasang lagi di server, tanpa mengubah berkasnya', () => {
        const { paling_awal } = batasTanggalLahir({ ...tanggalLahir, usia_maks: 70 }, HARI_INI);

        expect(paling_awal).toBe('1956-08-19');
    });

    it('tidak berlaku untuk field yang bukan tanggal lahir', () => {
        expect(batasTanggalLahir({ tipe: 'teks' }, HARI_INI)).toBeNull();
    });
});

const hp = { label: 'Nomor HP / WhatsApp', tipe: 'teks', format: 'hp', prefix: '8', maks_digit: 13, min_digit: 9 };

describe('galatFormat — nomor HP', () => {
    it('menolak nomor yang tidak diawali 8, seperti aturan server', () => {
        // '02123333333333' lolos panjangnya (13 angka diketik) tapi ditolak
        // server karena polanya menuntut 08. Selama browser cuma memeriksa
        // PANJANG, orang mengisi penuh empat langkah lalu ditolak di ujung
        // dengan kalimat yang tidak menyebut apa yang kurang.
        const pesan = galatFormat(hp, '02123333333333');

        expect(pesan).toBeTruthy();
        expect(pesan).toMatch(/diawali 8/i);
    });

    it('menerima nomor yang benar', () => {
        expect(galatFormat(hp, '08123456789')).toBeNull();
    });

    it('tetap menyebut panjang bila angkanya kurang', () => {
        expect(galatFormat(hp, '0812345')).toMatch(/minimal 9 angka/i);
    });

    it('menerima nomor yang ditempel lengkap dengan +62', () => {
        expect(galatFormat(hp, '+628123456789')).toBeNull();
    });
});

// Definisi yang dipakai jumlah tanggungan: hanya muncul bila status menikah.
const tanggungan = {
    label: 'Jumlah Tanggungan',
    tipe: 'teks',
    format: 'angka',
    maks_digit: 2,
    nilai_min: 0,
    nilai_maks: 20,
    // Bentuk yang sebenarnya dipakai: beberapa nilai sekaligus. Anak tetap
    // menjadi tanggungan setelah bercerai atau ditinggal wafat pasangan.
    tampil_jika: { field: 'status_pernikahan', nilai: ['Menikah', 'Cerai Hidup', 'Cerai Mati'] },
};

describe('bidangTampil — pertanyaan bersyarat', () => {
    it('menampilkan pertanyaan pada setiap nilai yang disyaratkan', () => {
        ['Menikah', 'Cerai Hidup', 'Cerai Mati'].forEach((status) => {
            expect(bidangTampil(tanggungan, { status_pernikahan: status })).toBe(true);
        });

        expect(bidangTampil(tanggungan, { status_pernikahan: 'Belum Menikah' })).toBe(false);
    });

    it('menyembunyikan pertanyaan selama syaratnya belum dijawab sama sekali', () => {
        expect(bidangTampil(tanggungan, {})).toBe(false);
    });

    it('membiarkan pertanyaan tanpa syarat selalu tampil', () => {
        expect(bidangTampil({ label: 'Nama' }, {})).toBe(true);
    });

    it('menyaring daftar kunci satu langkah sekaligus', () => {
        const skemaField = { status_pernikahan: { label: 'Status' }, jumlah_tanggungan: tanggungan };
        const daftar = ['status_pernikahan', 'jumlah_tanggungan'];

        expect(kunciTampil(daftar, skemaField, { status_pernikahan: 'Menikah' })).toEqual(daftar);
        expect(kunciTampil(daftar, skemaField, { status_pernikahan: 'Cerai Mati' })).toEqual(daftar);
        expect(kunciTampil(daftar, skemaField, { status_pernikahan: 'Belum Menikah' })).toEqual([
            'status_pernikahan',
        ]);
    });
});

describe('galatFormat — cacahan angka', () => {
    it('menerima nol sebagai jawaban yang sah', () => {
        expect(galatFormat(tanggungan, '0')).toBeNull();
    });

    it('menolak angka di atas batas dengan menyebut batasnya', () => {
        expect(galatFormat(tanggungan, '21')).toContain('20');
    });

    it('menerima tepat di batas atas', () => {
        expect(galatFormat(tanggungan, '20')).toBeNull();
    });

    it('menolak isian yang bukan angka bulat', () => {
        expect(galatFormat(tanggungan, '1,5')).toBeTruthy();
    });

    it('membatasi ketikan sepanjang batas atasnya', () => {
        // Dua digit cukup untuk mengetik 20; kalau batasnya satu digit, angka
        // yang sah tidak akan pernah bisa diketik penuh.
        expect(batasKetikan(tanggungan)).toBe(2);
        expect(nilaiSimpanAngka('123', 2)).toBe('12');
        // Nol harus bertahan sebagai '0', bukan berubah jadi isian kosong.
        expect(nilaiSimpanAngka('0', 2)).toBe('0');
    });

    // '01' lolos di layar tapi ditolak aturan 'integer' server saat Kirim,
    // dengan kalimat yang tidak menunjuk kesalahannya.
    it('membuang nol di depan saat mengetik, tanpa menghapus nol tunggal', () => {
        expect(nilaiSimpanAngka('01', 2)).toBe('1');
        expect(nilaiSimpanAngka('00', 2)).toBe('0');
        expect(nilaiSimpanAngka('007', 2)).toBe('7');
        expect(nilaiSimpanAngka('10', 2)).toBe('10');
    });
});
