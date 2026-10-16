import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import TabAbsensiPaket from './TabAbsensiPaket.vue';

const ABSENSI_SAMPLE = [
    {
        Id_SOP_Absensi: 201,
        Kode_Karyawan: 'K001',
        Nama: 'Budi Pratama',
        No_Induk_Karyawan: 'NIK-001',
        Departemen: 'Operasional',
        Divisi: 'Plant 1',
        Jabatan: 'Supervisor HSE',
        Lokasi: 'Site Jakarta',
        Foto_Url: 'https://example.com/foto1.jpg',
        Checktime: '2026-08-20 08:30:00',
        Output: 'IN',
        Latitude: -6.2088,
        Longitude: 106.8456,
        Akurasi_Meter: 15,
        Lokasi_Meragukan: false,
        IP_Address: '192.168.1.50',
    },
    {
        Id_SOP_Absensi: 202,
        Kode_Karyawan: 'K001',
        Nama: 'Budi Pratama',
        No_Induk_Karyawan: 'NIK-001',
        Departemen: 'Operasional',
        Divisi: 'Plant 1',
        Jabatan: 'Supervisor HSE',
        Lokasi: 'Site Jakarta',
        Foto_Url: 'https://example.com/foto2.jpg',
        Checktime: '2026-08-21 08:15:00',
        Output: 'IN',
        Latitude: -6.2090,
        Longitude: 106.8460,
        Akurasi_Meter: 80,
        Lokasi_Meragukan: true,
        IP_Address: '192.168.1.50',
    },
    {
        Id_SOP_Absensi: 203,
        Kode_Karyawan: 'K002',
        Nama: 'Siti Rahma',
        No_Induk_Karyawan: 'NIK-002',
        Departemen: 'HR & GA',
        Divisi: 'People Ops',
        Jabatan: 'Staff HR',
        Lokasi: 'Head Office',
        Foto_Url: null,
        Checktime: '2026-08-20 09:00:00',
        Output: 'IN',
        Latitude: -6.1754,
        Longitude: 106.8272,
        Akurasi_Meter: 10,
        Lokasi_Meragukan: false,
        IP_Address: '10.0.0.12',
    },
];

const pasang = (props = {}) => {
    return mount(TabAbsensiPaket, {
        props: {
            absensiList: ABSENSI_SAMPLE,
            paket: { Nama_Paket: 'Paket Dokumen Keamanan' },
            idPaket: 'test-paket-123',
            loading: false,
            ...props,
        },
        global: {
            stubs: {
                HcisButton: {
                    template: '<button :class="variant" @click="$emit(\'click\', $event)"><slot>{{ label }}</slot></button>',
                    props: ['variant', 'size', 'leadingIcon', 'label', 'iconOnly'],
                },
                HcisBadge: {
                    template: '<span class="hcis-badge" :class="variant"><slot>{{ label }}</slot></span>',
                    props: ['label', 'variant', 'appearance', 'size', 'icon', 'pulse'],
                },
                HcisCard: {
                    template: '<div class="hcis-card"><slot name="header"></slot><slot></slot></div>',
                    props: ['size', 'variant', 'sentiment', 'title', 'description', 'badge'],
                },
                HcisAvatar: {
                    template: '<div class="hcis-avatar">{{ name }}</div>',
                    props: ['name', 'size', 'shape', 'variant'],
                },
                HcisFilterBar: {
                    template: '<div class="hcis-filter-bar"><slot name="actions"></slot><slot></slot></div>',
                    props: ['search', 'filters', 'filterValues', 'quickChips', 'activeChipValue', 'resultsCount'],
                },
                HcisPagination: {
                    template: '<div class="hcis-pagination"></div>',
                    props: ['currentPage', 'pageSize', 'totalItems', 'size'],
                },
                HcisIcon: {
                    template: '<i :class="name"></i>',
                    props: ['name', 'size'],
                },
                HcisDialog: {
                    template: '<div v-if="modelValue" class="hcis-dialog"><slot></slot><slot name="footer"></slot></div>',
                    props: ['modelValue', 'title', 'subtitle', 'size'],
                },
                HcisEmptyState: {
                    template: '<div class="hcis-empty-state"><slot name="actions"></slot></div>',
                    props: ['icon', 'title', 'description'],
                },
                HcisStatGroup: {
                    template: '<div class="hcis-stat-group"><slot></slot></div>',
                    props: ['stats', 'cols', 'gap', 'layout', 'appearance'],
                },
            },
        },
    });
};

describe('TabAbsensiPaket — Metrik KPI Presensi', () => {
    it('menghitung total catatan presensi, foto selfie, dan lokasi meragukan dengan tepat', () => {
        const wrapper = pasang();
        expect(wrapper.vm.totalCatatanPresensi).toBe(3);
        expect(wrapper.vm.totalFotoSelfie).toBe(2);
        expect(wrapper.vm.totalLokasiMeragukan).toBe(1);
    });

    it('mengelompokkan catatan presensi per karyawan', () => {
        const wrapper = pasang();
        const grouped = wrapper.vm.groupedAbsensi;
        expect(grouped.length).toBe(2); // K001 and K002
        expect(grouped[0].Kode_Karyawan).toBe('K001');
        expect(grouped[0].total_presensi).toBe(2);
        expect(grouped[0].ada_foto).toBe(true);
        expect(grouped[0].ada_lokasi_meragukan).toBe(true);

        expect(grouped[1].Kode_Karyawan).toBe('K002');
        expect(grouped[1].total_presensi).toBe(1);
        expect(grouped[1].ada_foto).toBe(false);
        expect(grouped[1].ada_lokasi_meragukan).toBe(false);
    });

    it('mengekstrak daftar departemen unik', () => {
        const wrapper = pasang();
        expect(wrapper.vm.daftarDepartemenUnik).toContain('Operasional');
        expect(wrapper.vm.daftarDepartemenUnik).toContain('HR & GA');
    });
});

describe('TabAbsensiPaket — Penyaringan & Quick Chips', () => {
    it('menyaring karyawan berdasarkan pencarian nama atau NIK', async () => {
        const wrapper = pasang();
        wrapper.vm.filterState.search = 'Siti';
        expect(wrapper.vm.filteredGroupedAbsensi.length).toBe(1);
        expect(wrapper.vm.filteredGroupedAbsensi[0].Nama).toBe('Siti Rahma');
    });

    it('menyaring karyawan berdasarkan chip jenis foto selfie', async () => {
        const wrapper = pasang();
        wrapper.vm.filterState.jenis = 'foto';
        expect(wrapper.vm.filteredGroupedAbsensi.length).toBe(1);
        expect(wrapper.vm.filteredGroupedAbsensi[0].Kode_Karyawan).toBe('K001');
    });

    it('menyaring karyawan berdasarkan chip akurasi GPS meragukan', async () => {
        const wrapper = pasang();
        wrapper.vm.filterState.jenis = 'meragukan';
        expect(wrapper.vm.filteredGroupedAbsensi.length).toBe(1);
        expect(wrapper.vm.filteredGroupedAbsensi[0].Kode_Karyawan).toBe('K001');
    });

    it('menyaring karyawan berdasarkan status GPS akurat', async () => {
        const wrapper = pasang();
        wrapper.vm.filterState.status_gps = 'akurat';
        expect(wrapper.vm.filteredGroupedAbsensi.length).toBe(1);
        expect(wrapper.vm.filteredGroupedAbsensi[0].Kode_Karyawan).toBe('K002');
    });
});

describe('TabAbsensiPaket — Interaksi Accordion & Lightbox Dialog', () => {
    it('membuka dan menutup accordion karyawan secara individual', () => {
        const wrapper = pasang();
        expect(wrapper.vm.isOpen('K001')).toBe(false);
        wrapper.vm.toggleAccordion('K001');
        expect(wrapper.vm.isOpen('K001')).toBe(true);
        wrapper.vm.toggleAccordion('K001');
        expect(wrapper.vm.isOpen('K001')).toBe(false);
    });

    it('mendukung aksi buka semua dan tutup semua accordion', () => {
        const wrapper = pasang();
        wrapper.vm.bukaSemuaAccordion();
        expect(wrapper.vm.openAccordionKeys.length).toBe(2);
        expect(wrapper.vm.isOpen('K001')).toBe(true);
        expect(wrapper.vm.isOpen('K002')).toBe(true);

        wrapper.vm.tutupSemuaAccordion();
        expect(wrapper.vm.openAccordionKeys.length).toBe(0);
    });

    it('membuka modal dialog lightbox saat thumbnail presensi diklik', () => {
        const wrapper = pasang();
        expect(wrapper.vm.fotoDialog.show).toBe(false);
        wrapper.vm.bukaLightboxModal(ABSENSI_SAMPLE[0]);
        expect(wrapper.vm.fotoDialog.show).toBe(true);
        expect(wrapper.vm.fotoDialog.item.Nama).toBe('Budi Pratama');
    });
});
