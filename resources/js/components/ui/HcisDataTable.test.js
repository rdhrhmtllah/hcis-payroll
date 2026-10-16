import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HcisDataTable from './HcisDataTable.vue';

describe('HcisDataTable.vue', () => {
    it('renders turnkey table container with columns and data', () => {
        const columns = [
            { key: 'name', label: 'Nama' },
            { key: 'dept', label: 'Departemen' },
        ];
        const data = [{ name: 'Ahmad', dept: 'IT' }];

        const wrapper = mount(HcisDataTable, {
            props: {
                title: 'Daftar Karyawan',
                columns,
                data,
                total: 1,
            },
        });

        expect(wrapper.text()).toContain('Daftar Karyawan');
        expect(wrapper.text()).toContain('Ahmad');
        expect(wrapper.text()).toContain('IT');
    });

    it('emits add event when add button is clicked', async () => {
        const wrapper = mount(HcisDataTable, {
            props: {
                title: 'Data Cuti',
                allowAdd: true,
                addLabel: 'Pengajuan Baru',
            },
        });

        const addBtn = wrapper.find('.hcis-container__header-actions button');
        expect(addBtn.exists()).toBe(true);
        await addBtn.trigger('click');
        expect(wrapper.emitted('add')).toBeTruthy();
    });

    // -------------------------------------------------------------------------
    // Paginasi.
    //
    // Sempat mati untuk SEMUA pemakai: tabel memberi prop `page` (yang tidak
    // dikenal pager) dan menyimak `page-change` (yang tidak pernah dipancarkan
    // pager). Rangkaiannya terlihat tersambung di markup, tapi tidak ada satu
    // pun nilai yang lewat — dan tak ada yang menyadarinya karena belum ada
    // halaman yang memakainya.
    // -------------------------------------------------------------------------

    const denganPaginasi = () =>
        mount(HcisDataTable, {
            props: {
                columns: [{ key: 'nama', label: 'Nama' }],
                data: [{ nama: 'Adrian' }],
                total: 120,
                page: 3,
                pageSize: 10,
            },
        });

    it('benar-benar merender pagernya saat ada isinya', () => {
        // Pagernya duduk di slot #footer milik HcisContainer. Kalau slot itu
        // tidak dirender, seluruh perbaikan kabel di bawah tidak ada artinya:
        // tidak ada yang bisa diklik.
        const w = denganPaginasi();

        expect(w.find('.hcis-pagination').exists()).toBe(true);
        expect(w.text()).toContain('3');
    });

    it('menyembunyikan pager saat tidak ada isinya', () => {
        const w = mount(HcisDataTable, {
            props: { columns: [{ key: 'nama', label: 'Nama' }], data: [], total: 0 },
        });

        expect(w.find('.hcis-pagination').exists()).toBe(false);
    });

    it('memberi tahu pager halaman mana yang sedang aktif', () => {
        const pager = denganPaginasi().findComponent({ name: 'HcisPagination' });

        expect(pager.props('currentPage')).toBe(3);
        expect(pager.props('total')).toBe(120);
        expect(pager.props('pageSize')).toBe(10);
    });

    it('meneruskan perpindahan halaman ke pemakainya', async () => {
        const w = denganPaginasi();

        w.findComponent({ name: 'HcisPagination' }).vm.$emit('current-change', 5);
        await w.vm.$nextTick();

        expect(w.emitted('page-change')).toBeTruthy();
        expect(w.emitted('page-change')[0]).toEqual([5]);
    });

    it('meneruskan perubahan jumlah baris per halaman', async () => {
        const w = denganPaginasi();

        w.findComponent({ name: 'HcisPagination' }).vm.$emit('size-change', 50);
        await w.vm.$nextTick();

        expect(w.emitted('size-change')[0]).toEqual([50]);
    });

    it('satu klik halaman menghasilkan TEPAT satu page-change', async () => {
        // HcisPagination memancarkan enam peristiwa untuk satu klik supaya
        // pemakainya bebas memilih nama. Menyimak lebih dari satu membuat satu
        // klik menembakkan beberapa permintaan ke server sekaligus — mahal,
        // dan tak terlihat sampai seseorang membaca lognya.
        const w = denganPaginasi();
        const pager = w.findComponent({ name: 'HcisPagination' });

        // Ditiru persis seperti goToPage() yang sebenarnya.
        pager.vm.$emit('update:modelValue', 5);
        pager.vm.$emit('update:currentPage', 5);
        pager.vm.$emit('update:page', 5);
        pager.vm.$emit('change', { page: 5, pageSize: 10 });
        pager.vm.$emit('current-change', 5);
        pager.vm.$emit('page-change', 5);
        await w.vm.$nextTick();

        expect(w.emitted('page-change')).toHaveLength(1);
        expect(w.emitted('page-change')[0]).toEqual([5]);
    });

    it('satu perubahan jumlah baris menghasilkan TEPAT satu size-change', async () => {
        const w = denganPaginasi();
        const pager = w.findComponent({ name: 'HcisPagination' });

        // Persis seperti selectPageSize().
        pager.vm.$emit('update:pageSize', 50);
        pager.vm.$emit('size-change', 50);
        pager.vm.$emit('page-size-change', 50);
        await w.vm.$nextTick();

        expect(w.emitted('size-change')).toHaveLength(1);
        expect(w.emitted('size-change')[0]).toEqual([50]);
    });
});
