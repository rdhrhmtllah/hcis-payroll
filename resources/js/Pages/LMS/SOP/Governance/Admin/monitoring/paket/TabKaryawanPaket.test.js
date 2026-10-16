import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import TabKaryawanPaket from './TabKaryawanPaket.vue';

const KARYAWAN_MOCK = [
    {
        Kode_Karyawan: 'K001',
        Nama: 'Budi Santoso',
        No_Induk_Karyawan: 'NIK-001',
        Departemen: 'Teknologi Informasi',
        Divisi: 'Software Engineering',
        Jabatan: 'Lead Developer',
        Level: 'Staff',
        Lokasi: 'Kantor Pusat',
        Status_Penugasan: 'SELESAI',
        Status_Paket: 'SELESAI',
        Total_Item: 5,
        Item_Selesai: 5,
        Item_Dibuka: 5,
        Persen: 100,
        Is_Terlambat: false,
        Tanggal_Selesai: '2026-08-20 14:00',
        Tanggal_Deadline: '2026-08-25',
        Token: 'tok_001',
    },
    {
        Kode_Karyawan: 'K002',
        Nama: 'Siti Rahma',
        No_Induk_Karyawan: 'NIK-002',
        Departemen: 'Operasional',
        Divisi: 'Logistik',
        Jabatan: 'Admin Logistik',
        Level: 'Staff',
        Lokasi: 'Gudang Barat',
        Status_Penugasan: 'PROGRESS',
        Status_Paket: 'PROGRESS',
        Total_Item: 5,
        Item_Selesai: 2,
        Item_Dibuka: 3,
        Persen: 40,
        Is_Terlambat: true,
        Tanggal_Selesai: null,
        Tanggal_Deadline: '2026-08-10',
        Token: 'tok_002',
    },
    {
        Kode_Karyawan: 'K003',
        Nama: 'Agus Wijaya',
        No_Induk_Karyawan: 'NIK-003',
        Departemen: 'Keuangan',
        Divisi: 'Akuntansi',
        Jabatan: 'Staff Pajak',
        Level: 'Staff',
        Lokasi: 'Kantor Pusat',
        Status_Penugasan: 'BELUM',
        Status_Paket: 'BELUM',
        Total_Item: 5,
        Item_Selesai: 0,
        Item_Dibuka: 0,
        Persen: 0,
        Is_Terlambat: false,
        Tanggal_Selesai: null,
        Tanggal_Deadline: '2026-08-30',
        Token: 'tok_003',
    },
];

const PAKET_MOCK = {
    Id_SOP_Paket: 'aLwOr9XpPWzQkjJlKD5E',
    Nama_Paket: 'Prosedur Operasional Keamanan Data',
    Prioritas: 'KRITIS',
};

function mountComponent(props = {}) {
    return mount(TabKaryawanPaket, {
        props: {
            karyawanList: KARYAWAN_MOCK,
            paket: PAKET_MOCK,
            idPaket: 'aLwOr9XpPWzQkjJlKD5E',
            loading: false,
            ...props,
        },
        global: {
            stubs: {
                HcisStatGroup: true,
                HcisButton: true,
                HcisBadge: true,
                HcisAvatar: true,
                HcisFilterBar: true,
                HcisPagination: true,
                HcisCard: true,
                HcisTable: true,
                HcisIcon: true,
                HcisContextBanner: true,
                HcisEmptyState: true,
                HcisDialog: true,
                StatusPill: true,
            },
        },
    });
};

describe('TabKaryawanPaket — KPI Summary & Perhitungan Metrik', () => {
    it('menghitung total penerima, selesai, progress, belum, dan terlambat dengan benar', () => {
        const wrapper = mountComponent();
        const summary = wrapper.vm.kpiSummary;

        expect(summary.total).toBe(3);
        expect(summary.selesai).toBe(1);
        expect(summary.progress).toBe(0); // K002 is terlambat so counted in terlambat
        expect(summary.belum).toBe(1);
        expect(summary.terlambat).toBe(1);
        expect(summary.pctSelesai).toBe(33); // 1 dari 3 = 33%
    });

    it('menghitung jumlah terlambat untuk memicu context banner', () => {
        const wrapper = mountComponent();
        expect(wrapper.vm.jumlahTerlambat).toBe(1);
    });
});

describe('TabKaryawanPaket — Pencarian dan Penyaringan Karyawan', () => {
    it('menyaring karyawan berdasarkan kata kunci nama', async () => {
        const wrapper = mountComponent();
        wrapper.vm.filterState.search = 'Budi';

        const filtered = wrapper.vm.filteredAndSortedKaryawan;
        expect(filtered.length).toBe(1);
        expect(filtered[0].Nama).toBe('Budi Santoso');
    });

    it('menyaring karyawan berdasarkan status selesai', async () => {
        const wrapper = mountComponent();
        wrapper.vm.filterState.status = 'selesai';

        const filtered = wrapper.vm.filteredAndSortedKaryawan;
        expect(filtered.length).toBe(1);
        expect(filtered[0].Kode_Karyawan).toBe('K001');
    });

    it('menyaring karyawan berdasarkan status terlambat', async () => {
        const wrapper = mountComponent();
        wrapper.vm.filterState.status = 'terlambat';

        const filtered = wrapper.vm.filteredAndSortedKaryawan;
        expect(filtered.length).toBe(1);
        expect(filtered[0].Kode_Karyawan).toBe('K002');
    });

    it('menyaring karyawan berdasarkan departemen', async () => {
        const wrapper = mountComponent();
        wrapper.vm.filterState.dept = ['Teknologi Informasi'];

        const filtered = wrapper.vm.filteredAndSortedKaryawan;
        expect(filtered.length).toBe(1);
        expect(filtered[0].Departemen).toBe('Teknologi Informasi');
    });
});

describe('TabKaryawanPaket — Interaksi & Event', () => {
    it('mengirimkan event open-drawer saat baris atau tombol audit diklik', () => {
        const wrapper = mountComponent();
        wrapper.vm.openDrawer(KARYAWAN_MOCK[0]);

        expect(wrapper.emitted('open-drawer')).toBeTruthy();
        expect(wrapper.emitted('open-drawer')[0][0]).toEqual(KARYAWAN_MOCK[0]);
    });

    it('dapat memilih dan membatalkan pilihan karyawan massal', () => {
        const wrapper = mountComponent();
        expect(wrapper.vm.selectedKaryawanKeys).toEqual([]);

        wrapper.vm.toggleSelectKaryawan('K001');
        expect(wrapper.vm.selectedKaryawanKeys).toContain('K001');

        wrapper.vm.toggleSelectKaryawan('K001');
        expect(wrapper.vm.selectedKaryawanKeys).not.toContain('K001');
    });

    it('dapat memilih seluruh karyawan di halaman aktif', () => {
        const wrapper = mountComponent();
        wrapper.vm.pilihSemuaHalaman();

        expect(wrapper.vm.selectedKaryawanKeys.length).toBe(3);
    });
});
