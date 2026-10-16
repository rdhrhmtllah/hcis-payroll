import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import KartuLampiranForm from './KartuLampiranForm.vue';

/**
 * Form kosong dokumen FORM: daftar yang disusun admin di langkah Pemberkasan.
 *
 * Komponen ini tak menyimpan apa pun sendiri — ia memancarkan daftar baru,
 * dan wizard yang mengirimnya. Yang dikunci di sini: hapus dan geser
 * menghasilkan daftar yang benar, dan keadaan terkunci benar-benar mematikan
 * setiap aksi.
 */
const HcisButton = {
    name: 'HcisButton',
    props: ['label', 'disabled'],
    emits: ['click'],
    template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>',
};

const dua = [
    { Id_Master_Konten: 'aaa', Judul: 'Form A', Ext: 'docx' },
    { Id_Master_Konten: 'bbb', Judul: 'Form B', Ext: 'xlsx' },
];

const pasang = (modelValue = dua, props = {}) =>
    mount(KartuLampiranForm, {
        props: { modelValue, ...props },
        global: {
            stubs: {
                HcisCard: { template: '<div><slot /></div>' },
                HcisButton,
                HcisBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
                HcisIcon: { template: '<i />' },
                HcisFileViewer: { template: '<div />' },
            },
        },
    });

const tombol = (w, aksi, i = 0) => w.findAll(`[data-aksi="${aksi}"]`)[i];

describe('KartuLampiranForm', () => {
    it('menampilkan tiap form kosong dengan namanya', () => {
        const w = pasang();
        expect(w.text()).toContain('Form A');
        expect(w.text()).toContain('Form B');
    });

    it('hapus memancarkan daftar tanpa baris itu', async () => {
        const w = pasang();
        await tombol(w, 'hapus', 0).trigger('click');
        expect(w.emitted('update:modelValue')[0][0]).toEqual([dua[1]]);
    });

    it('turun menukar baris dengan sesudahnya', async () => {
        const w = pasang();
        await tombol(w, 'turun', 0).trigger('click');
        expect(w.emitted('update:modelValue')[0][0]).toEqual([dua[1], dua[0]]);
    });

    it('naik pada baris pertama dan turun pada baris terakhir dimatikan', () => {
        const w = pasang();
        expect(tombol(w, 'naik', 0).attributes('disabled')).toBeDefined();
        expect(tombol(w, 'turun', 1).attributes('disabled')).toBeDefined();
    });

    it('tambah memancarkan permintaan memilih berkas', async () => {
        const w = pasang();
        await tombol(w, 'tambah').trigger('click');
        expect(w.emitted('tambah')).toHaveLength(1);
    });

    it('terkunci mematikan tambah, hapus, dan geser', () => {
        const w = pasang(dua, { terkunci: true });
        for (const aksi of ['tambah', 'hapus', 'naik', 'turun']) {
            for (const b of w.findAll(`[data-aksi="${aksi}"]`)) {
                expect(b.attributes('disabled'), aksi).toBeDefined();
            }
        }
    });

    it('daftar kosong menyebut belum ada form kosong', () => {
        const w = pasang([]);
        expect(w.text()).toContain('Belum ada form kosong');
    });
});
