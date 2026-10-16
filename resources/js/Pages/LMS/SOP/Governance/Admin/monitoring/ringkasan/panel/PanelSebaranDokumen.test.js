import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';

vi.mock('@inertiajs/vue3', () => ({ router: { visit: vi.fn() } }));

import PanelSebaranDokumen from './PanelSebaranDokumen.vue';

const baris = (nama, total, f, p, b, extra = {}) => ({
    Id: null, Nilai: null, Kode: null, Nama: nama, Label: null, Tanpa: false,
    Total: total, Fungsional: f, Publik: p, Belum_Beredar: b, ...extra,
});

const sebaran = {
    Total: 10,
    Sumbu: [
        {
            Kunci: 'jenis', Nama: 'Jenis Dokumen', Ikon: 'bar-chart-steps', Satuan: 'tingkat',
            Keterangan: 'Tingkat hirarki mutu.', Param: 'jenis', Baris: 3,
        },
        {
            Kunci: 'paket', Nama: 'Paket Penugasan', Ikon: 'box-seam', Satuan: 'paket',
            Keterangan: 'Berapa dokumen yang dibawa tiap paket.', Param: null, Baris: 2,
        },
    ],
    Data: {
        jenis: {
            Baris: [
                baris('Kebijakan', 4, 3, 0, 1, { Id: 7, Nilai: 'xyzHASH', Kode: 'PLC', Label: 'Tk. 1' }),
                baris('Prosedur', 6, 4, 1, 1, { Id: 8, Nilai: 'abcHASH', Kode: 'PRO', Label: 'Tk. 2' }),
                baris('Standar', 0, 0, 0, 0, { Id: 9, Nilai: 'defHASH', Kode: 'STD', Label: 'Tk. 3' }),
            ],
            Kosong: 1, Menjumlah: true, Catatan: null,
        },
        paket: {
            Baris: [
                baris('Paket Induksi', 6, 6, 0, 0, { Id: 21 }),
                baris('Belum masuk paket mana pun', 5, 0, 0, 5, { Tanpa: true }),
            ],
            Kosong: 0,
            Menjumlah: false,
            Catatan: '1 dokumen dipakai lebih dari satu paket, jadi 10 dokumen menempati 11 tempat.',
        },
    },
};

const pasang = (props = {}) => mount(PanelSebaranDokumen, {
    props: { sebaran, ...props },
    global: { stubs: { HcisEmptyState: { template: '<div class="kosong" />' } } },
});

describe('PanelSebaranDokumen', () => {
    beforeEach(() => vi.clearAllMocks());

    it('membuka pada sumbu pertama dan menggambar barisnya', () => {
        const t = pasang().text();

        expect(t).toContain('Kebijakan');
        expect(t).toContain('Prosedur');
        expect(t).toContain('tingkat');
    });

    it('berganti sumbu tanpa memuat ulang apa pun', async () => {
        /* Seluruh sumbu datang dalam satu balasan; kalau perpindahannya sampai
           menuntut permintaan baru, meringkas lima kartu jadi satu hanya
           menukar tinggi layar dengan jeda. */
        const w = pasang();
        await w.findAll('.psd-sumbu-btn')[1].trigger('click');

        expect(w.text()).toContain('Paket Induksi');
        expect(w.text()).not.toContain('Kebijakan');
    });

    it('menyebut catatan ketika batangnya tak bisa dijumlahkan', async () => {
        const w = pasang();
        expect(w.find('.psd-catatan').exists()).toBe(false);

        await w.findAll('.psd-sumbu-btn')[1].trigger('click');

        expect(w.find('.psd-catatan').exists()).toBe(true);
        expect(w.text()).toContain('lebih dari satu paket');
    });

    it('menampilkan baris yang kosong, bukan menyembunyikannya', () => {
        /* Tingkat piramid tanpa dokumen adalah temuan, bukan baris yang tak
           perlu digambar. */
        const w = pasang();

        expect(w.text()).toContain('Standar');
        expect(w.text()).toContain('belum ada dokumen');
        expect(w.text()).toContain('1 tingkat kosong');
    });

    it('menutup rincian yang terbuka begitu sumbu berganti', async () => {
        const w = pasang();
        await w.findAll('.psd-baris')[0].trigger('click');
        expect(w.find('.psd-detail').exists()).toBe(true);

        await w.findAll('.psd-sumbu-btn')[1].trigger('click');

        expect(w.find('.psd-detail').exists()).toBe(false);
    });

    it('mengirim nilai tautan apa adanya ke Master Dokumen', async () => {
        /* Nilainya disiapkan server sebagai hashid. Layar yang mengarang
           sendiri dari Id mentah akan mendarat di daftar tak tersaring —
           itulah cacat yang diperbaiki bersama kartu ini. */
        const w = pasang();
        await w.findAll('.psd-baris')[0].trigger('click');
        await w.find('.psd-detail-link').trigger('click');

        expect(router.visit).toHaveBeenCalledWith('/sop/admin/dokumen?jenis=xyzHASH');
    });

    it('tak menawarkan tautan pada sumbu yang tak punya saringannya', async () => {
        const w = pasang();
        await w.findAll('.psd-sumbu-btn')[1].trigger('click');
        await w.findAll('.psd-baris')[0].trigger('click');

        expect(w.find('.psd-detail').exists()).toBe(true);
        expect(w.find('.psd-detail-link').exists()).toBe(false);
    });

    it('menyebut sisa baris yang belum ditampilkan', async () => {
        const banyak = {
            ...sebaran,
            Data: {
                ...sebaran.Data,
                jenis: {
                    ...sebaran.Data.jenis,
                    Kosong: 0,
                    Baris: Array.from({ length: 14 }, (_, i) => baris(`Jenis ${i + 1}`, 1, 1, 0, 0, { Id: i + 1 })),
                },
            },
        };
        const w = pasang({ sebaran: banyak });

        expect(w.findAll('.psd-baris')).toHaveLength(10);
        expect(w.text()).toContain('Tampilkan 4 tingkat lainnya');

        await w.find('.psd-lagi').trigger('click');

        expect(w.findAll('.psd-baris')).toHaveLength(14);
    });

    it('bertahan pada payload kosong', () => {
        const w = mount(PanelSebaranDokumen, {
            props: { sebaran: { Total: 0, Sumbu: [], Data: {} } },
            global: { stubs: { HcisEmptyState: { template: '<div class="kosong" />' } } },
        });

        expect(w.find('.kosong').exists()).toBe(true);
        expect(w.findAll('.psd-baris')).toHaveLength(0);
    });
});
