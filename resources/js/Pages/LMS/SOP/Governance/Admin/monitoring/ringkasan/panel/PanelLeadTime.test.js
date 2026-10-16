import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import PanelLeadTime from './PanelLeadTime.vue';

vi.mock('@inertiajs/vue3', () => ({
    router: {
        visit: vi.fn(),
    },
}));

const dummyLeadTime = {
    Total_Dokumen_Terbit: 12,
    Rerata_Hari: 14.5,
    Tercepat_Hari: 3,
    Terlama_Hari: 45,
    Distribusi: {
        kurang_7: 2,
        '7_sampai_14': 5,
        '15_sampai_30': 3,
        lebih_30: 2,
    },
    Daftar: [
        {
            Id_Dokumen: 'dok-1',
            Judul: 'SOP Pelayanan Pelanggan',
            Nomor: 'SOP/CS/001',
            Jenis: 'Prosedur',
            Kode_Jenis: 'SOP',
            Departemen: 'Customer Service',
            Tanggal_Mulai: '2026-01-01',
            Tanggal_Publish: '2026-01-15',
            Hari: 14,
        },
        {
            Id_Dokumen: 'dok-2',
            Judul: 'IK Pengoperasian Mesin',
            Nomor: 'IK/PROD/002',
            Jenis: 'Instruksi Kerja',
            Kode_Jenis: 'IK',
            Departemen: 'Produksi',
            Tanggal_Mulai: '2026-02-01',
            Tanggal_Publish: '2026-02-05',
            Hari: 4,
        },
    ],
};

const pasang = (props = {}) => mount(PanelLeadTime, {
    props: {
        leadTime: { ...dummyLeadTime, ...props },
    },
});

describe('PanelLeadTime', () => {
    it('merender metrik rata-rata lead time, tercepat, dan terlama', () => {
        const w = pasang();

        expect(w.text()).toContain('14.5');
        expect(w.text()).toContain('Tercepat: 3 hari');
        expect(w.text()).toContain('Terlama: 45 hari');
        expect(w.text()).toContain('Sudah terbit: 12 dok');
    });

    it('menampilkan sebaran kategori kecepatan penerbitan', () => {
        const w = pasang();

        expect(w.text()).toContain('Sangat Cepat');
        expect(w.text()).toContain('Ideal');
        expect(w.text()).toContain('Standar');
        expect(w.text()).toContain('Perlu Akselerasi');
    });

    it('menampilkan daftar dokumen dan durasi penerbitannya', () => {
        const w = pasang();

        expect(w.text()).toContain('SOP Pelayanan Pelanggan');
        expect(w.text()).toContain('14 hari');
        expect(w.text()).toContain('IK Pengoperasian Mesin');
        expect(w.text()).toContain('4 hari');
    });

    it('mengarahkan ke halaman master dokumen saat tombol buka diklik', async () => {
        const w = pasang();
        const btnBuka = w.findAll('.plt-btn-buka')[0];

        await btnBuka.trigger('click');
        expect(router.visit).toHaveBeenCalledWith('/sop/admin/dokumen?dok=dok-1');
    });

    it('menampilkan status kosong saat tidak ada daftar dokumen', () => {
        const w = pasang({ Daftar: [], Total_Dokumen_Terbit: 0 });

        expect(w.text()).toContain('Belum ada dokumen dengan tanggal respon');
    });
});
