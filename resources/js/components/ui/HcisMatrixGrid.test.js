import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisMatrixGrid from './HcisMatrixGrid.vue';

describe('HcisMatrixGrid.vue', () => {
    const mockColumns = [
        { key: 'view', label: 'Lihat Data' },
        { key: 'create', label: 'Tambah' },
        { key: 'edit', label: 'Ubah' },
        { key: 'delete', label: 'Hapus' }
    ];

    const mockRows = [
        {
            id: 1,
            title: 'Master Data Karyawan',
            code: 'HR-EMP-01',
            group: 'Modul HR Operations',
            permissions: { view: true, create: true, edit: true, delete: false }
        },
        {
            id: 2,
            title: 'Pengajuan Cuti',
            code: 'HR-LEAVE-01',
            group: 'Modul HR Operations',
            permissions: { view: true, create: true, edit: false, delete: false }
        }
    ];

    it('renders column headers and row titles correctly', () => {
        const wrapper = mount(HcisMatrixGrid, {
            props: {
                title: 'Matriks Otorisasi Modul',
                columns: mockColumns,
                rows: mockRows,
                groupBy: 'group',
            }
        });

        expect(wrapper.text()).toContain('Matriks Otorisasi Modul');
        expect(wrapper.text()).toContain('Lihat Data');
        expect(wrapper.text()).toContain('Tambah');
        expect(wrapper.text()).toContain('Master Data Karyawan');
        expect(wrapper.text()).toContain('Modul HR Operations');
    });

    it('toggles permission cell on click and emits cell-change event', async () => {
        const wrapper = mount(HcisMatrixGrid, {
            props: {
                columns: mockColumns,
                rows: mockRows,
            }
        });

        const cellButtons = wrapper.findAll('.hcis-matrix-cell-pill');
        expect(cellButtons.length).toBe(8);

        // Click delete cell of first row (which was false)
        await cellButtons[3].trigger('click');

        expect(wrapper.emitted('cell-change')).toBeTruthy();
        expect(wrapper.emitted('cell-change')[0][0].colKey).toBe('delete');
        expect(wrapper.emitted('cell-change')[0][0].value).toBe(true);
    });
});
