import { mount } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import TabDokumenPaket from './TabDokumenPaket.vue';

const ITEMS_SAMPLE = [
    {
        Id_SOP_Paket_Item: 101,
        Judul_Item: 'SOP Standar Keamanan Kantor',
        Tipe_Item: 'DOKUMEN',
        Nomor_SOP_Resmi: 'SOP-SEC-001',
        Urutan: 1,
        Nama_Section: 'Keamanan & HSE',
        Versi_Ke: 2,
        Total_Target: 50,
        Jumlah_Dibuka: 45,
        Jumlah_Dikonfirmasi: 40,
        Persen_Dikonfirmasi: 80,
    },
    {
        Id_SOP_Paket_Item: 102,
        Judul_Item: 'Formulir Checklist Harian',
        Tipe_Item: 'FORM',
        Nomor_SOP_Resmi: 'FORM-SEC-002',
        Urutan: 2,
        Nama_Section: 'Keamanan & HSE',
        Versi_Ke: 1,
        Total_Target: 50,
        Jumlah_Dibuka: 30,
        Jumlah_Dikonfirmasi: 25,
        Persen_Dikonfirmasi: 50,
    },
    {
        Id_SOP_Paket_Item: 103,
        Judul_Item: 'Video Panduan Evakuasi',
        Tipe_Item: 'VIDEO',
        Nomor_SOP_Resmi: 'VID-EMG-003',
        Urutan: 3,
        Nama_Section: 'Tanggap Darurat',
        Versi_Ke: 1,
        Total_Target: 50,
        Jumlah_Dibuka: 50,
        Jumlah_Dikonfirmasi: 50,
        Persen_Dikonfirmasi: 100,
    },
];

const pasang = (props = {}) => {
    return mount(TabDokumenPaket, {
        props: {
            itemsList: ITEMS_SAMPLE,
            paket: { Nama_Paket: 'Paket Onboarding Staff' },
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
                HcisTable: {
                    template: '<div class="hcis-table"><div v-for="(row, idx) in rows" :key="row.Id_SOP_Paket_Item" class="table-row"><slot name="cell-urutan" :row="row" :index="idx"></slot><slot name="cell-judul" :row="row"></slot><slot name="cell-section" :row="row"></slot><slot name="cell-target" :row="row"></slot><slot name="cell-dibuka" :row="row"></slot><slot name="cell-dikonfirmasi" :row="row"></slot><slot name="cell-kepatuhan" :row="row"></slot><slot name="cell-aksi" :row="row"></slot></div></div>',
                    props: ['columns', 'rows', 'loading', 'rowKey'],
                },
                HcisProgress: {
                    template: '<div class="hcis-progress" :data-val="value"></div>',
                    props: ['value', 'sentiment', 'size', 'animated'],
                },
                HcisFilterBar: {
                    template: '<div class="hcis-filter-bar"><slot></slot></div>',
                    props: ['search', 'filters', 'filterValues', 'quickChips', 'activeChipValue', 'resultsCount', 'showViewToggle', 'viewMode'],
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
                    props: ['modelValue', 'title', 'subtitle', 'width', 'size'],
                },
                HcisEmptyState: {
                    template: '<div class="hcis-empty-state"><slot name="actions"></slot></div>',
                    props: ['icon', 'title', 'description'],
                },
                HcisStatGroup: {
                    template: '<div class="hcis-stat-group"><slot></slot></div>',
                    props: ['stats', 'cols', 'gap', 'layout', 'appearance'],
                },
                ItemTypeIcon: {
                    template: '<span class="item-type-icon">{{ tipe }}</span>',
                    props: ['tipe'],
                },
            },
        },
    });
};

describe('TabDokumenPaket — Metrik & Perhitungan Kepatuhan', () => {
    it('menghitung rata-rata kepatuhan seluruh dokumen dengan benar', () => {
        const wrapper = pasang();
        // (80 + 50 + 100) / 3 = 76.66 -> 77%
        expect(wrapper.vm.rataRataKepatuhan).toBe(77);
    });

    it('mengekstrak daftar section unik dari item dokumen', () => {
        const wrapper = pasang();
        expect(wrapper.vm.daftarSectionUnik).toEqual(['Keamanan & HSE', 'Tanggap Darurat']);
    });

    it('menghitung total pembacaan dibuka seluruh item', () => {
        const wrapper = pasang();
        // 45 + 30 + 50 = 125
        expect(wrapper.vm.totalPembacaanDibuka).toBe(125);
    });
});

describe('TabDokumenPaket — Penyaringan & Quick Chips', () => {
    it('menyaring dokumen berdasarkan kata kunci pencarian judul atau nomor resmi', async () => {
        const wrapper = pasang();
        wrapper.vm.filterState.search = 'Evakuasi';
        expect(wrapper.vm.filteredAndSortedItems.length).toBe(1);
        expect(wrapper.vm.filteredAndSortedItems[0].Judul_Item).toBe('Video Panduan Evakuasi');
    });

    it('menyaring dokumen berdasarkan chip tipe item', async () => {
        const wrapper = pasang();
        wrapper.vm.filterState.tipe = 'FORM';
        expect(wrapper.vm.filteredAndSortedItems.length).toBe(1);
        expect(wrapper.vm.filteredAndSortedItems[0].Tipe_Item).toBe('FORM');
    });

    it('menyaring dokumen berdasarkan multi-select section', async () => {
        const wrapper = pasang();
        wrapper.vm.filterState.section = ['Tanggap Darurat'];
        expect(wrapper.vm.filteredAndSortedItems.length).toBe(1);
        expect(wrapper.vm.filteredAndSortedItems[0].Nama_Section).toBe('Tanggap Darurat');
    });

    it('menyaring dokumen berdasarkan status kepatuhan tuntas 100%', async () => {
        const wrapper = pasang();
        wrapper.vm.filterState.kepatuhan = 'tuntas';
        expect(wrapper.vm.filteredAndSortedItems.length).toBe(1);
        expect(wrapper.vm.filteredAndSortedItems[0].Persen_Dikonfirmasi).toBe(100);
    });
});

describe('TabDokumenPaket — Tabel Matriks & Modal Detail', () => {
    it('menghasilkan paginatedItems sesuai ukuran halaman', () => {
        const wrapper = pasang();
        expect(wrapper.vm.paginatedItems.length).toBe(3);
        expect(wrapper.vm.paginatedItems[0].Judul_Item).toBe('SOP Standar Keamanan Kantor');
    });

    it('membuka dialog rincian metrik saat item diklik', async () => {
        const wrapper = pasang();
        expect(wrapper.vm.dialogDetailOpen).toBe(false);
        wrapper.vm.bukaDetailModal(ITEMS_SAMPLE[0]);
        expect(wrapper.vm.dialogDetailOpen).toBe(true);
        expect(wrapper.vm.selectedItemForModal.Judul_Item).toBe('SOP Standar Keamanan Kantor');
    });
});
