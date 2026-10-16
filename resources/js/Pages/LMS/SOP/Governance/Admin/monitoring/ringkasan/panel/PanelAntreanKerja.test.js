import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';

vi.mock('@inertiajs/vue3', () => ({ router: { visit: vi.fn() } }));

import PanelAntreanKerja from './PanelAntreanKerja.vue';

const baris = [
    {
        Jenis: 'MENUNGGU_SAH', Nama_Jenis: 'Menunggu pengesahan',
        Judul: 'Prosedur Kalibrasi', Nomor: 'SOP/01', Keterangan: 'Versi 2 menunggu tanda tangan.',
        Umur_Hari: 38, Id_Dokumen: 'dokA', Id_Versi: 'verA',
    },
    {
        Jenis: 'TERBIT_TAK_BEREDAR', Nama_Jenis: 'Sah tapi tak beredar',
        Judul: 'Kebijakan Mutu', Nomor: 'PLC/01', Keterangan: 'Sudah terbit tetapi belum masuk paket.',
        Umur_Hari: 12, Id_Dokumen: 'dokB', Id_Versi: null,
    },
    {
        Jenis: 'PROSES_KOSONG', Nama_Jenis: 'Proses tanpa dokumen',
        Judul: 'Manage Financial Resources', Nomor: 'APQC-09',
        Keterangan: 'Belum punya satu dokumen pun.',
        Umur_Hari: null, Id_Dokumen: null, Id_Versi: null,
    },
];

const pasang = (props = {}) => mount(PanelAntreanKerja, {
    props: { antrean: { Baris: baris, Total: baris.length }, ...props },
    global: { stubs: { HcisAlert: { template: '<div />' } } },
});

describe('PanelAntreanKerja', () => {
    beforeEach(() => vi.clearAllMocks());

    it('menyebut nama tiap temuan, bukan sekadar jumlahnya', () => {
        /* Inilah sebab kartu penghitung diganti: "9 dokumen tanpa pemilik unit"
           menyisakan seluruh pekerjaan berburu kepada pembacanya. */
        const t = pasang().text();

        expect(t).toContain('Prosedur Kalibrasi');
        expect(t).toContain('Manage Financial Resources');
    });

    it('membuka versi yang macet langsung pada versinya', () => {
        const w = pasang();
        w.findAll('.pak-buka')[0].trigger('click');

        expect(router.visit).toHaveBeenCalledWith('/sop/admin/dokumen/dokA/versi/verA');
    });

    it('temuan tanpa versi mendarat di formulir versi dokumennya', () => {
        /* Bukan ke daftar dokumen: mengirim orang ke daftar berarti ia harus
           mencari lagi apa yang barusan ditunjukkan kepadanya. */
        const w = pasang();
        w.findAll('.pak-buka')[1].trigger('click');

        expect(router.visit).toHaveBeenCalledWith('/sop/admin/dokumen/dokB/versi');
    });

    it('temuan tanpa dokumen tak punya tombol buka', () => {
        /* Proses APQC kosong menamai PROSES, bukan dokumen — tak ada halaman
           yang bisa dituju, dan tombol yang tak ke mana-mana lebih buruk
           daripada tak ada tombol. */
        expect(pasang().findAll('.pak-buka')).toHaveLength(2);
    });

    it('menandai temuan yang sudah lama terbengkalai', () => {
        const w = pasang();

        expect(w.findAll('.pak-umur')[0].classes()).toContain('is-tua');
        expect(w.findAll('.pak-umur')[1].classes()).not.toContain('is-tua');
    });

    it('melaporkan sisa yang tak ditampilkan, tidak memotong diam-diam', () => {
        /* Daftar yang terpotong tanpa tanda terbaca sebagai "cuma segini
           pekerjaannya". */
        const w = pasang({ antrean: { Baris: baris, Total: 30 } });

        expect(w.text()).toContain('+27 temuan lainnya');
    });

    it('mengatakan bersih saat memang tak ada pekerjaan', () => {
        const w = pasang({ antrean: { Baris: [], Total: 0 } });

        expect(w.text()).toContain('Tata kelola master dokumen bersih');
    });

    it('tidak mengurutkan ulang — urutan datang dari server', () => {
        /* Bobot tiap jenis temuan adalah keputusan produk. Layar yang
           mengurutkannya sendiri akan menyimpang begitu bobotnya diubah. */
        const judul = pasang().findAll('.pak-judul').map((n) => n.text());

        expect(judul).toEqual(['Prosedur Kalibrasi', 'Kebijakan Mutu', 'Manage Financial Resources']);
    });
});
