import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisTable from './HcisTable.vue';

describe('HcisTable.vue', () => {
    const columns = [
        { key: 'name', label: 'Nama', sortable: true },
        { key: 'role', label: 'Jabatan' },
    ];
    const rows = [
        { id: 1, name: 'Budi Santoso', role: 'Software Engineer' },
        { id: 2, name: 'Ahmad Fauzi', role: 'Product Manager' },
    ];

    it('renders column headers and row data correctly', async () => {
        const wrapper = mount(HcisTable, {
            props: { columns, rows },
        });

        const headers = wrapper.findAll('th');
        expect(headers[0].text()).toContain('Nama');
        expect(headers[1].text()).toContain('Jabatan');

        const cells = wrapper.findAll('td');
        expect(cells[0].text()).toBe('Budi Santoso');
    });

    it('sorts rows when sortable header is clicked', async () => {
        const wrapper = mount(HcisTable, {
            props: { columns, rows },
        });

        await wrapper.findAll('th')[0].trigger('click');
        expect(wrapper.emitted('sort')[0]).toEqual([{ key: 'name', order: 'asc' }]);
    });

    it('renders loading skeleton when loading prop is true', async () => {
        const wrapper = mount(HcisTable, {
            props: { columns, rows: [], loading: true },
        });

        expect(wrapper.findAll('.hcis-table__skeleton-row').length).toBe(4);
    });

    it('renders empty state when rows is empty and not loading', async () => {
        const wrapper = mount(HcisTable, {
            props: { columns, rows: [] },
        });

        expect(wrapper.find('.hcis-table__empty-wrap').exists()).toBe(true);
    });
});

/**
 * Baris yang tindakan massalnya mustahil tidak boleh bisa dipilih.
 *
 * `selectable` hanya menyalakan kolom centangnya. Tanpa penentu per-baris,
 * SETIAP baris ikut terpilih — termasuk permit yang aksesnya sudah dicabut.
 * Server menolaknya diam, jadi yang terjadi bukan data rusak melainkan laporan
 * yang membingungkan: sepuluh dipilih, "3 berhasil", tujuh sisanya tanpa kabar.
 */
describe('HcisTable — rowSelectable', () => {
    const KOLOM = [{ key: 'nama', label: 'Nama' }];
    const BARIS = [
        { id: 'a', nama: 'Aktif satu', status: 'AKTIF' },
        { id: 'b', nama: 'Sudah dicabut', status: 'DIBATALKAN' },
        { id: 'c', nama: 'Aktif dua', status: 'AKTIF' },
    ];

    const pasang = (extra = {}) =>
        mount(HcisTable, {
            props: {
                columns: KOLOM,
                rows: BARIS,
                rowKey: 'id',
                selectable: true,
                selectedKeys: [],
                rowSelectable: (row) => row.status === 'AKTIF',
                ...extra,
            },
        });

    it('mengunci centang baris yang tidak boleh dipilih', () => {
        const wrapper = pasang();
        const centang = wrapper.findAll('tbody input[type="checkbox"]');

        expect(centang).toHaveLength(3);
        expect(centang[0].attributes('disabled')).toBeUndefined();
        expect(centang[1].attributes('disabled')).toBeDefined();
        expect(centang[2].attributes('disabled')).toBeUndefined();
    });

    it('"pilih semua" hanya menyapu baris yang boleh dipilih', async () => {
        const wrapper = pasang();

        await wrapper.find('thead input[type="checkbox"]').setValue(true);

        const terakhir = wrapper.emitted('update:selectedKeys').at(-1)[0];

        expect(terakhir).toEqual(['a', 'c']);
        expect(terakhir).not.toContain('b');
    });

    it('menganggap terpilih penuh walau ada baris terkunci', () => {
        // Tanpa ini, centang kepala tidak pernah tampak penuh pada halaman yang
        // memuat satu saja baris terkunci — dan pemakainya menekannya berulang
        // kali menyangka ada yang terlewat.
        const wrapper = pasang({ selectedKeys: ['a', 'c'] });

        expect(wrapper.find('thead input[type="checkbox"]').element.checked).toBe(true);
    });

    it('menolak pemilihan baris terkunci walau dipanggil langsung', async () => {
        // Atribut disabled hanya menjaga jalur tetikus; pemanggilan lewat ref
        // atau keyboard melewatinya.
        const wrapper = pasang();

        wrapper.vm.toggleSelectRow(BARIS[1], 1);
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:selectedKeys')).toBeFalsy();
    });

    it('tanpa rowSelectable, semua baris tetap bisa dipilih', async () => {
        // Perilaku lama tidak boleh berubah bagi pemakai yang belum menyetel apa pun.
        const wrapper = pasang({ rowSelectable: null });

        await wrapper.find('thead input[type="checkbox"]').setValue(true);

        expect(wrapper.emitted('update:selectedKeys').at(-1)[0]).toEqual(['a', 'b', 'c']);
    });
});
