import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import BarisPaket from './BarisPaket.vue';

const TUGAS = {
    Id_SOP_Paket: 'p1',
    Nama_Paket: 'Tata Tertib Karyawan',
    Prioritas: 'NORMAL',
    Status_Paket: 'PROGRESS',
    Total_Item: 5,
    Item_Selesai: 2,
    Item_Perlu_Ulang: 3,
    Persen: 40,
    Tenggat: '2026-08-20',
    Is_Terlambat: false,
    Is_Diperbarui: true,
};

function pasang(k = {}) {
    return mount(BarisPaket, {
        props: { k: { ...TUGAS, ...k } },
        global: { stubs: { StatusPill: true, ProgressBar: true, ItemTypeIcon: true } },
    });
}

describe('BarisPaket — lencana kepemilikan dokumen', () => {
    const dept = (id, nama) => ({ id, nama, kode: null, level: null });

    it('menyebut departemen pemilik dokumen di dalam paket', () => {
        expect(pasang({ Departemen: [dept(1, 'HSE')] }).find('.dept-chip').text()).toContain('HSE');
    });

    it('memendekkan banyak pemilik jadi "+n" tapi menyebut semuanya di tooltip', () => {
        // Tiga nama departemen sebaris akan mendorong chip membungkus dan
        // mengubah tinggi kartu tanpa alasan yang terlihat.
        const w = pasang({ Departemen: [dept(1, 'HSE'), dept(2, 'Produksi'), dept(3, 'HR')] });
        const chip = w.find('.dept-chip');

        expect(chip.text()).toContain('HSE');
        expect(chip.text()).toContain('+2');
        expect(chip.attributes('title')).toContain('Produksi');
        expect(chip.attributes('title')).toContain('HR');
    });

    it('dokumen yang belum bertuan disebut, bukan disembunyikan', () => {
        // Paket yang separuh isinya belum bertuan tak boleh tampil seolah rapi.
        expect(pasang({ Departemen: [dept(null, null)] }).find('.dept-chip').text()).toContain('Tanpa pemilik');
    });

    it('tidak memasang lencana sama sekali bila paket tak memuat dokumen', () => {
        expect(pasang({ Departemen: [] }).find('.dept-chip').exists()).toBe(false);
    });

    it('lencana jenis memakai yang pertama — server sudah mengurutkannya menurut hirarki', () => {
        const w = pasang({ Jenis_Dokumen: [dept(8, 'Kebijakan'), dept(9, 'Instruksi Kerja')] });

        expect(w.find('.jenis-chip').text()).toContain('Kebijakan');
        expect(w.find('.jenis-chip').attributes('title')).toContain('Instruksi Kerja');
    });
});

describe('BarisPaket — berapa item perlu dibaca ulang', () => {
    it('menyebut jumlah item yang harus dibaca ulang', () => {
        expect(pasang().find('.bt-ulang').text()).toContain('3');
    });

    it('menyembunyikan penanda saat tak ada yang perlu dibaca ulang', () => {
        expect(pasang({ Item_Perlu_Ulang: 0 }).find('.bt-ulang').exists()).toBe(false);
    });

    it('tidak menampilkannya pada tugas yang sudah tuntas', () => {
        const w = pasang({ Status_Paket: 'SELESAI', Persen: 100, Item_Selesai: 5, Is_Diperbarui: false });

        expect(w.find('.bt-ulang').exists()).toBe(false);
    });

    it('tetap aman bila server lama belum mengirim fieldnya', () => {
        const tanpa = { ...TUGAS };
        delete tanpa.Item_Perlu_Ulang;

        // Dipasang langsung, tanpa lewat helper — helper menyebar TUGAS lebih
        // dulu sehingga field yang dihapus akan terpasang kembali.
        const wrapper = mount(BarisPaket, {
            props: { k: tanpa },
            global: { stubs: { StatusPill: true, ProgressBar: true, ItemTypeIcon: true } },
        });

        expect(wrapper.find('.bt-ulang').exists()).toBe(false);
    });
});
