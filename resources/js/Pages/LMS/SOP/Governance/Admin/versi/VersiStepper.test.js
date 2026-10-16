import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import VersiStepper from './VersiStepper.vue';

const langkah = [
    { Nomor: 1, Nama: 'Pengajuan', Keadaan: 'selesai', Terkunci: false, Kurang: [], Ringkasan: 'baru diajukan' },
    { Nomor: 2, Nama: 'Review', Keadaan: 'berjalan', Terkunci: false, Kurang: [], Ringkasan: '0 putaran review' },
    { Nomor: 3, Nama: 'Pengesahan', Keadaan: 'belum', Terkunci: true, Kurang: ['Realisasi pengesah'] },
    { Nomor: 4, Nama: 'Pemberkasan', Keadaan: 'belum', Terkunci: true, Kurang: [] },
];

const pasang = () => mount(VersiStepper, { props: { langkah, dibuka: 2 } });

describe('VersiStepper', () => {
    it('tidak mengemit pilih untuk langkah terkunci', async () => {
        const w = pasang();
        await w.findAll('button')[2].trigger('click');

        expect(w.emitted('pilih')).toBeUndefined();
    });

    it('mematikan tombol langkah terkunci', () => {
        const w = pasang();
        const tombol = w.findAll('button');

        expect(tombol[2].attributes('disabled')).toBeDefined();
        expect(tombol[3].attributes('disabled')).toBeDefined();
        expect(tombol[1].attributes('disabled')).toBeUndefined();
    });

    it('mengemit pilih untuk langkah yang sudah dilewati', async () => {
        const w = pasang();
        await w.findAll('button')[0].trigger('click');

        expect(w.emitted('pilih')[0]).toEqual([1]);
    });

    it('menyebut apa yang kurang pada langkah terkunci', () => {
        /* Kotak yang cuma bilang "terkunci" memaksa admin menebak sebabnya. */
        expect(pasang().text()).toContain('Realisasi pengesah');
    });

    it('menandai langkah yang sedang dibuka sebagai step berjalan', () => {
        const w = pasang();

        expect(w.findAll('button')[1].attributes('aria-current')).toBe('step');
        expect(w.findAll('button')[0].attributes('aria-current')).toBeUndefined();
    });
});

describe('VersiStepper — centang mengikuti kelengkapan, bukan posisi tahap', () => {
    /**
     * GEJALANYA: admin melengkapi berkas final dan ketiga tanggal masa berlaku,
     * lalu Pemberkasan tetap berbunyi "sedang dikerjakan" — selamanya.
     *
     * SEBABNYA: Keadaan menjawab "tahapnya di mana", dan langkah yang sedang
     * DITEMPATI selalu 'berjalan'. Pemberkasan hanya bisa ditinggalkan dengan
     * menerbitkan, sehingga selama masih draft ia tak pernah bisa berbunyi
     * 'selesai'. Kartu ringkasan di bilah samping sudah membaca Lengkap sejak
     * awal, jadi dua rel di halaman yang sama saling bertentangan.
     */
    const pemberkasanTuntas = [
        { Nomor: 4, Nama: 'Pemberkasan', Keadaan: 'berjalan', Lengkap: true, Terkunci: false, Kurang: [], Ringkasan: 'berkas final siap' },
    ];

    it('mencentang langkah yang tuntas meski masih ditempati', () => {
        const w = mount(VersiStepper, { props: { langkah: pemberkasanTuntas, dibuka: 4 } });

        expect(w.find('.bi-check-lg').exists()).toBe(true);
        expect(w.find('button').classes()).toContain('is-lengkap');
    });

    it('tidak mencentang langkah yang belum tuntas', () => {
        const w = mount(VersiStepper, {
            props: {
                langkah: [{ ...pemberkasanTuntas[0], Lengkap: false, Ringkasan: 'berkas final belum ada' }],
                dibuka: 4,
            },
        });

        expect(w.find('.bi-check-lg').exists()).toBe(false);
        expect(w.find('button').classes()).not.toContain('is-lengkap');
    });

    /** Versi batal bukan versi selesai — pembatalan menang di urutan teratas. */
    it('pembatalan mengalahkan kelengkapan', () => {
        const w = mount(VersiStepper, {
            props: {
                langkah: [{ ...pemberkasanTuntas[0], Keadaan: 'cancel', Lengkap: true }],
                dibuka: 4,
            },
        });

        expect(w.find('.bi-x-lg').exists()).toBe(true);
        expect(w.find('.bi-check-lg').exists()).toBe(false);
    });

    it('tanpa ringkasan, keterangannya menyebut kelengkapan bukan posisi tahap', () => {
        const w = mount(VersiStepper, {
            props: {
                langkah: [{ ...pemberkasanTuntas[0], Ringkasan: null }],
                dibuka: 4,
            },
        });

        expect(w.text()).toContain('selesai');
        expect(w.text()).not.toContain('sedang dikerjakan');
    });
});
