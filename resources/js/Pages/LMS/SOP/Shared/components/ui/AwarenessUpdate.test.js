import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import SopTugasCard from './SopTugasCard.vue';

/*
 * Kartu rak rujukan (Public Document).
 *
 * Uji ini dulu menyasar KartuSop.vue. Kartu itu dicabut ketika Public Document
 * dan Functional Document disatukan ke SATU kartu, dan perilaku yang dijaga di
 * sini ikut pindah — bukan ikut hilang: lencana "Baru"/"Diperbarui" harus tetap
 * menyebutkan BERAPA item yang berubah, karena lencana tanpa angka menyuruh
 * orang membuka paketnya hanya untuk tahu seberapa besar perubahannya.
 */
const PAKET = {
    Id_SOP_Paket: 'p1',
    Nama_Paket: 'SOP Meeting',
    Kode_Paket: null,
    Prioritas: 'NORMAL',
    Estimasi: 17,
    Isi: { DOKUMEN: 3 },
    Total_Item: 3,
    Item_Tugas: 0,
    Item_Selesai: 0,
    Persen: 0,
    Departemen: [],
    Jenis_Dokumen: [],
    Apqc: [],
    Wajib: false,
    Favorit: false,
    Pernah_Buka: false,
    Baru: true,
    Diperbarui: false,
    Item_Baru: 2,
};

function kartu(k = {}, props = {}) {
    return mount(SopTugasCard, {
        props: { tugas: { ...PAKET, ...k }, rujukan: true, ...props },
        global: { stubs: { HcisButton: true, HcisProgress: true } },
    });
}

describe('SopTugasCard (rujukan) — jumlah item yang berubah', () => {
    it('menyebut berapa item berubah pada lencana Baru', () => {
        expect(kartu().find('.tg-badge').text()).toContain('2');
    });

    it('menyebut berapa item berubah pada lencana Diperbarui', () => {
        const w = kartu({ Baru: false, Diperbarui: true, Pernah_Buka: true, Item_Baru: 3 });
        expect(w.find('.tg-badge').text()).toContain('Diperbarui');
        expect(w.find('.tg-badge').text()).toContain('3');
    });

    it('menyembunyikan angka nol — "Baru 0" membantah dirinya sendiri', () => {
        const t = kartu({ Item_Baru: 0 }).find('.tg-badge').text();
        expect(t).toContain('Baru');
        expect(t).not.toContain('0');
    });
});

describe('SopTugasCard (rujukan) — apa yang TIDAK boleh muncul', () => {
    /*
     * Penjaga ini pernah melihat MERAH: sebelum prop `rujukan` ada, kartu
     * pustaka menampilkan "Tanpa tenggat" untuk dokumen yang memang bebas
     * dibaca — terbaca sebagai penjadwalan yang terlupakan.
     */
    it('tidak menampilkan chip tenggat', () => {
        expect(kartu().text()).not.toContain('Tanpa tenggat');
    });

    it('tidak memakai isi paket sebagai penyebut progres', () => {
        /* Paket berisi 3 dokumen tetapi TAK ditugaskan: bar progres tak boleh
           mengabarkan "0/3 item selesai" atas pekerjaan yang tak pernah
           dibebankan kepada orang ini. */
        const w = kartu();
        expect(w.find('.tg-noitem').exists()).toBe(true);
        expect(w.text()).not.toContain('0/3');
    });

    it('menampilkan progres begitu paketnya benar-benar ditugaskan', () => {
        const w = kartu({ Item_Tugas: 4, Item_Selesai: 1, Persen: 25 });
        expect(w.find('.tg-noitem').exists()).toBe(false);
        expect(w.findComponent({ name: 'HcisProgress' }).attributes('label')).toBe('1/4 item selesai');
    });
});

describe('SopTugasCard — mode tugas tidak ikut berubah', () => {
    it('tetap menampilkan tenggat dan memakai Total_Item sebagai penyebut', () => {
        const w = mount(SopTugasCard, {
            props: {
                tugas: {
                    Id_SOP_Paket: 'p9',
                    Nama_Paket: 'SOP Evakuasi',
                    Total_Item: 5,
                    Item_Selesai: 2,
                    Persen: 40,
                    Tenggat: null,
                    Departemen: [],
                },
            },
            global: { stubs: { HcisButton: true, HcisProgress: true } },
        });

        expect(w.text()).toContain('Tanpa tenggat');
        expect(w.findComponent({ name: 'HcisProgress' }).attributes('label')).toBe('2/5 item selesai');
    });
});
