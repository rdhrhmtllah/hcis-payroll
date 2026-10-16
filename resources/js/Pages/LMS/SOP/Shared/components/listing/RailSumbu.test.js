import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import RailSumbu from './RailSumbu.vue';

/*
 * Yang dijaga di sini satu hal: SELISIH ANTARA TUGAS DAN PENEMPATAN HARUS
 * DISEBUT.
 *
 * Pada sumbu bernilai jamak (Departemen, Jenis Dokumen) satu paket menempati
 * beberapa kelompok, sehingga angka pada tiap baris rail tidak menjumlah ke
 * jumlah tugas. Itu benar dan disengaja — tapi tanpa keterangan, pembaca yang
 * menjumlahkannya sendiri akan menyimpulkan salah satunya salah, dan angka yang
 * diragukan tak dipakai siapa pun.
 */

const GRUP = [
    { nama: 'HSE', warna: '#111', ikon: 'bi-x', total: 6, persen: 40, terlambat: 0, kosong: false },
    { nama: 'Produksi', warna: '#222', ikon: 'bi-y', total: 5, persen: 80, terlambat: 0, kosong: false },
    { nama: 'Tanpa Pemilik', warna: '#333', ikon: 'bi-z', total: 4, persen: 0, terlambat: 0, kosong: true },
];

function pasang(over = {}) {
    return mount(RailSumbu, {
        props: {
            grup: GRUP,
            aktif: '',
            judul: 'Departemen',
            jamak: true,
            penempatan: 15,
            totalSemua: 12,
            persenSemua: 40,
            ...over,
        },
    });
}

describe('RailSumbu — kepala rail', () => {
    it('memakai nama sumbu yang sedang aktif, bukan kata "Kategori" yang dipatri', () => {
        expect(pasang().find('.rl-judul').text()).toContain('Departemen');
        expect(pasang({ judul: 'Jenis Dokumen' }).find('.rl-judul').text()).toContain('Jenis Dokumen');
    });

    it('menyebut selisih tugas dan penempatan pada sumbu jamak', () => {
        const t = pasang().find('.rl-selisih').text();

        expect(t).toContain('12 tugas');
        expect(t).toContain('15 penempatan');
    });

    it('tidak menyebut selisih pada sumbu tunggal', () => {
        // Kategori: satu tugas satu kelompok. Menuliskan "12 tugas · 12
        // penempatan" hanya menambah kata tanpa menambah keterangan.
        const w = pasang({ jamak: false, penempatan: 12, judul: 'Kategori' });

        expect(w.find('.rl-selisih').exists()).toBe(false);
    });

    it('tidak menyebut selisih ketika kebetulan tak ada selisihnya', () => {
        // Sumbu jamak tapi tak satu pun paket menyentuh dua kelompok.
        const w = pasang({ penempatan: 12 });

        expect(w.find('.rl-selisih').exists()).toBe(false);
    });

    /* Harapannya sempat basi: label aksesibilitas ikut memakai `labelSemua`
       sejak rail dipakai di luar Functional Document, tapi baris ini masih
       menuntut bunyi lamanya. Yang dijaga tetap sama — sumbu aktif harus
       disebut, bukan kata "Kategori" yang dipatri. */
    it('label aksesibilitasnya ikut menyebut sumbu, bukan "Kategori tugas"', () => {
        expect(pasang().find('nav').attributes('aria-label')).toBe('Semua tugas dikelompokkan per Departemen');
        expect(pasang({ judul: 'Kategori', labelSemua: 'Semua paket' }).find('nav').attributes('aria-label'))
            .toBe('Semua paket dikelompokkan per Kategori');
    });
});

describe('RailSumbu — daftar kelompok', () => {
    it('merender satu tombol per kelompok, di luar tombol "Semua tugas"', () => {
        expect(pasang().findAll('.rl-item')).toHaveLength(GRUP.length + 1);
    });

    it('memancarkan nama kelompok saat diklik', async () => {
        const w = pasang();
        await w.findAll('.rl-item')[1].trigger('click');

        expect(w.emitted('pilih')[0]).toEqual(['HSE']);
    });

    it('tombol "Semua tugas" memancarkan string kosong', async () => {
        const w = pasang({ aktif: 'HSE' });
        await w.findAll('.rl-item')[0].trigger('click');

        expect(w.emitted('pilih')[0]).toEqual(['']);
    });
});

/*
 * SOP Builder menyaring berdasarkan id (kategori) dan kode (status, prioritas,
 * distribusi), bukan berdasarkan nama yang dibaca orang. Kelompok karena itu
 * boleh membawa `nilai` sendiri. Yang dijaga di sini: `nilai` dipakai untuk
 * MEMANCARKAN dan untuk MENYOROT sekaligus — kalau hanya salah satunya,
 * kelompok yang benar-benar sedang disaring tak pernah tampak menyala.
 */
describe('RailSumbu — kelompok bernilai sendiri', () => {
    const BERNILAI = [
        { nilai: 'k1', nama: 'Operasional', warna: '#111', ikon: 'bi-x', total: 6, persen: 0, terlambat: 0 },
        { nilai: 'k2', nama: 'Mutu', warna: '#222', ikon: 'bi-y', total: 3, persen: 0, terlambat: 0 },
    ];

    it('memancarkan nilai kelompok, bukan namanya', async () => {
        const w = pasang({ grup: BERNILAI, jamak: false });
        await w.findAll('.rl-item')[1].trigger('click');

        expect(w.emitted('pilih')[0]).toEqual(['k1']);
    });

    it('menyorot berdasarkan nilai yang sedang aktif', () => {
        const w = pasang({ grup: BERNILAI, jamak: false, aktif: 'k2' });
        const menyala = w.findAll('.rl-item').filter((b) => b.classes().includes('on'));

        expect(menyala).toHaveLength(1);
        expect(menyala[0].text()).toContain('Mutu');
    });

    it('kelompok tanpa `nilai` tetap berperilaku seperti sebelumnya', async () => {
        const w = pasang({ aktif: 'HSE' });
        await w.findAll('.rl-item')[2].trigger('click');

        expect(w.emitted('pilih')[0]).toEqual(['Produksi']);
        expect(w.findAll('.rl-item')[1].classes()).toContain('on');
    });
});
