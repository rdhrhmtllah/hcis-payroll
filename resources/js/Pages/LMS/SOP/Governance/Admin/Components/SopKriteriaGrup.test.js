import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SopKriteriaGrup, { blankGroup, GROUP_FIELDS } from './SopKriteriaGrup.vue';

/*
 * Komponen ini dipakai DUA tempat — grup target dan grup pengecualian. Yang
 * dijaga di sini adalah bahwa keduanya benar-benar berbagi satu perilaku:
 * saringan yang sama, ringkasan yang sama, dan penanda "belum ada kriteria"
 * yang sama. Kalau salah satunya menyimpang, yang paling gampang tertinggal
 * adalah saringan yang baru ditambahkan belakangan.
 */

const stubs = {
    HcisBadge: { template: '<span>{{ label }}</span>', props: ['label'] },
    HcisButton: { template: '<button v-bind="$attrs" @click="$emit(\'click\')">{{ label }}</button>', props: ['label'] },
    HcisCard: { template: '<div><slot name="header" /><slot /></div>' },
    HcisEmptyState: { template: '<div class="kosong">{{ title }}</div>', props: ['title'] },
    HcisField: { template: '<div><slot /></div>' },
    HcisSelect: true,
    HcisSelectOption: true,
};

const OPTIONS = {
    divisi: ['IT', 'Finance'],
    departemen: ['Dev', 'Accounting'],
    level: ['Staff', 'Manager'],
    jabatan: ['Engineer'],
    lokasi: ['Head Office'],
};

const pasang = (modelValue, props = {}) =>
    mount(SopKriteriaGrup, {
        props: { modelValue, options: OPTIONS, judul: 'Uji', ...props },
        global: { stubs },
    });

describe('SopKriteriaGrup', () => {
    it('menawarkan seluruh saringan yang dikenal server', () => {
        expect(GROUP_FIELDS.map((f) => f.key)).toEqual(['divisi', 'dept', 'level', 'jabatan', 'lokasi']);
    });

    it('memetakan dept ke daftar departemen, bukan ke daftar bernama dept', () => {
        const w = pasang([blankGroup()]);

        expect(w.vm.pilihan('dept')).toEqual(['Dev', 'Accounting']);
        expect(w.vm.pilihan('divisi')).toEqual(['IT', 'Finance']);
    });

    it('menampilkan keadaan kosong ketika belum ada grup', () => {
        const w = pasang([], { judulKosong: 'Belum Ada Grup Pengecualian' });

        expect(w.find('.kosong').text()).toBe('Belum Ada Grup Pengecualian');
    });

    /* Grup tanpa kriteria ditolak server. Menyebutnya di kartu membuat
       penolakan itu tak datang sebagai kejutan saat Simpan ditekan. */
    it('menandai grup yang belum punya kriteria apa pun', () => {
        const w = pasang([blankGroup()]);

        expect(w.text()).toContain('Belum ada kriteria — wajib diisi');
    });

    it('meringkas kriteria yang sudah dipilih', () => {
        const w = pasang([{ ...blankGroup(), divisi: ['IT'], level: ['Staff', 'Manager'], tipe: 'PHL' }]);

        expect(w.text()).toContain('1 Divisi');
        expect(w.text()).toContain('2 Level Jabatan');
        expect(w.text()).toContain('PHL');
    });

    it('menambah grup memancarkan grup kosong baru', async () => {
        const w = pasang([]);

        w.vm.tambah();
        await w.vm.$nextTick();

        const hasil = w.emitted('update:modelValue').at(-1)[0];
        expect(hasil).toHaveLength(1);
        expect(hasil[0]).toEqual(blankGroup());
    });

    it('mengubah satu saringan tidak menyentuh grup lain', async () => {
        const w = pasang([blankGroup(), blankGroup()]);

        w.vm.ubah(1, 'divisi', ['Finance']);
        await w.vm.$nextTick();

        const hasil = w.emitted('update:modelValue').at(-1)[0];
        expect(hasil[1].divisi).toEqual(['Finance']);
        expect(hasil[0].divisi).toEqual([]);
    });

    it('tidak memutasi prop yang diterimanya', async () => {
        const asal = [blankGroup()];
        const w = pasang(asal);

        w.vm.ubah(0, 'divisi', ['IT']);
        await w.vm.$nextTick();

        expect(asal[0].divisi).toEqual([]);
    });

    /* minimal=1 dipakai grup target: aturan wajib punya satu. minimal=0 dipakai
       pengecualian: nol pengecualian adalah keadaan yang wajar. */
    it('menyembunyikan tombol hapus ketika grup sudah menyentuh batas minimal', () => {
        const w = pasang([blankGroup()], { minimal: 1 });

        expect(w.text()).not.toContain('Hapus Grup');
    });

    it('menampilkan tombol hapus ketika masih di atas batas minimal', () => {
        const w = pasang([blankGroup(), blankGroup()], { minimal: 1 });

        expect(w.text()).toContain('Hapus Grup');
    });

    it('grup pengecualian tunggal tetap boleh dihapus', () => {
        const w = pasang([blankGroup()], { minimal: 0 });

        expect(w.text()).toContain('Hapus Grup');
    });

    it('menghapus grup memancarkan daftar tanpa grup itu', async () => {
        const w = pasang([
            { ...blankGroup(), divisi: ['IT'] },
            { ...blankGroup(), divisi: ['Finance'] },
        ], { minimal: 0 });

        w.vm.hapus(0);
        await w.vm.$nextTick();

        const hasil = w.emitted('update:modelValue').at(-1)[0];
        expect(hasil).toHaveLength(1);
        expect(hasil[0].divisi).toEqual(['Finance']);
    });
});
