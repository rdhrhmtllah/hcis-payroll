import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import TabAuditPaket from './TabAuditPaket.vue';

const AKTIVITAS_SAMPLE = [
    {
        Id_SOP_Progress: 501,
        Kode_Karyawan: 'K001',
        Nama: 'Budi Pratama',
        No_Induk_Karyawan: 'NIK-001',
        Departemen: 'Operasional',
        Divisi: 'Plant 1',
        Jabatan: 'Supervisor HSE',
        Judul_Item: 'SOP Standar Keamanan',
        Tipe_Item: 'DOKUMEN',
        Nomor_SOP_Resmi: 'SOP-SEC-001',
        Urutan: 1,
        Waktu_Buka: '2026-08-20 08:35:00',
        Waktu_Konfirmasi: '2026-08-20 08:40:00',
        Durasi_Detik: 300,
        Skor_Kuis: 100,
        Status: 'SELESAI',
        IP_Address: '192.168.1.50',
    },
    {
        Id_SOP_Progress: 502,
        Kode_Karyawan: 'K001',
        Nama: 'Budi Pratama',
        No_Induk_Karyawan: 'NIK-001',
        Departemen: 'Operasional',
        Divisi: 'Plant 1',
        Jabatan: 'Supervisor HSE',
        Judul_Item: 'Form Checklist Harian',
        Tipe_Item: 'FORM',
        Nomor_SOP_Resmi: 'FORM-SEC-002',
        Urutan: 2,
        Waktu_Buka: '2026-08-20 08:45:00',
        Waktu_Konfirmasi: null,
        Durasi_Detik: 60,
        Skor_Kuis: null,
        Status: 'BUKA',
        IP_Address: '192.168.1.50',
    },
    {
        Id_SOP_Progress: 503,
        Kode_Karyawan: 'K002',
        Nama: 'Siti Rahma',
        No_Induk_Karyawan: 'NIK-002',
        Departemen: 'HR & GA',
        Divisi: 'People Ops',
        Jabatan: 'Staff HR',
        Judul_Item: 'SOP Standar Keamanan',
        Tipe_Item: 'DOKUMEN',
        Nomor_SOP_Resmi: 'SOP-SEC-001',
        Urutan: 1,
        Waktu_Buka: '2026-08-20 09:10:00',
        Waktu_Konfirmasi: '2026-08-20 09:15:00',
        Durasi_Detik: 240,
        Skor_Kuis: 75,
        Status: 'SELESAI',
        IP_Address: '10.0.0.12',
    },
];

const pasang = (props = {}) => {
    return mount(TabAuditPaket, {
        props: {
            aktivitasList: AKTIVITAS_SAMPLE,
            paket: { Nama_Paket: 'Paket Onboarding Staff' },
            idPaket: 'test-paket-123',
            loading: false,
            ...props,
        },
        global: {
            stubs: {
                HcisButton: {
                    template: '<button :class="variant" @click="$emit(\'click\', $event)"><slot>{{ label }}</slot></button>',
                    props: ['variant', 'size', 'leadingIcon', 'label', 'iconOnly', 'disabled', 'title'],
                },
                HcisBadge: {
                    template: '<span class="hcis-badge" :class="variant"><slot>{{ label }}</slot></span>',
                    props: ['label', 'variant', 'appearance', 'size', 'icon', 'closable'],
                },
                HcisCard: {
                    template: '<div class="hcis-card"><slot name="header"></slot><slot name="actions"></slot><slot></slot><slot name="footer"></slot></div>',
                    props: ['size', 'variant', 'sentiment', 'title', 'description', 'badge', 'badgeVariant', 'interactive'],
                },
                HcisTable: {
                    template: '<div class="hcis-table"><div v-for="(row, idx) in rows" :key="row.Id_SOP_Progress" class="table-row" @click="$emit(\'row-click\', row)"><slot name="cell-waktu" :row="row"></slot><slot name="cell-karyawan" :row="row"></slot><slot name="cell-item" :row="row"></slot><slot name="cell-durasi" :row="row"></slot><slot name="cell-kuis" :row="row"></slot><slot name="cell-status" :row="row"></slot><slot name="cell-ip" :row="row"></slot><slot name="cell-aksi" :row="row"></slot></div></div>',
                    props: ['columns', 'rows', 'loading', 'rowKey', 'hoverable', 'interactive', 'size', 'variant', 'sentiment'],
                },
                HcisIcon: {
                    template: '<i :class="name"></i>',
                    props: ['name', 'size'],
                },
                HcisAvatar: {
                    template: '<div class="hcis-avatar">{{ name }}</div>',
                    props: ['name', 'size', 'shape', 'variant'],
                },
                HcisPagination: {
                    template: '<div class="hcis-pagination">Pagination</div>',
                    props: ['currentPage', 'pageSize', 'totalItems', 'size'],
                },
                HcisStatGroup: {
                    template: '<div class="hcis-stat-group"><div v-for="s in stats" :key="s.label" class="stat-item">{{ s.label }}: {{ s.value }}</div></div>',
                    props: ['stats', 'cols', 'gap', 'layout', 'appearance'],
                },
                HcisEmptyState: {
                    template: '<div class="hcis-empty-state"><h3>{{ title }}</h3><p>{{ description }}</p><slot name="actions"></slot></div>',
                    props: ['title', 'description', 'icon', 'variant', 'sentiment'],
                },
                HcisDialog: {
                    template: '<div v-if="modelValue" class="hcis-dialog"><slot></slot><slot name="footer"></slot></div>',
                    props: ['modelValue', 'title', 'description', 'kicker', 'icon', 'sentiment', 'variant', 'size'],
                },
                HcisFilterBar: {
                    template: '<div class="hcis-filter-bar"><slot></slot></div>',
                    props: ['search', 'filters', 'filterValues', 'quickChips', 'activeChipValue', 'resultsCount', 'showViewToggle', 'viewMode'],
                },
                ItemTypeIcon: {
                    template: '<span class="item-type-icon">{{ tipe }}</span>',
                    props: ['tipe'],
                },
            },
        },
    });
};

describe('TabAuditPaket.vue', () => {
    it('merender komponen dengan benar dan menghitung statistik audit', () => {
        const wrapper = pasang();

        expect(wrapper.exists()).toBe(true);

        const stats = wrapper.vm.statAudit;
        expect(stats).toHaveLength(4);
        expect(stats[0].value).toBe(3); // Total audit
        expect(stats[1].value).toBe(2); // Selesai
        expect(stats[2].value).toBe(1); // Buka
    });

    it('menyaring aktivitas berdasarkan kata kunci pencarian', async () => {
        const wrapper = pasang();

        wrapper.vm.filterState.search = 'Siti Rahma';
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.filteredAktivitas).toHaveLength(1);
        expect(wrapper.vm.filteredAktivitas[0].Nama).toBe('Siti Rahma');
    });

    it('menyaring aktivitas berdasarkan status', async () => {
        const wrapper = pasang();

        wrapper.vm.filterState.status = 'SELESAI';
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.filteredAktivitas).toHaveLength(2);
        expect(wrapper.vm.filteredAktivitas.every((a) => a.Status === 'SELESAI')).toBe(true);
    });

    it('menyaring aktivitas berdasarkan tipe item', async () => {
        const wrapper = pasang();

        wrapper.vm.filterState.tipe = 'FORM';
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.filteredAktivitas).toHaveLength(1);
        expect(wrapper.vm.filteredAktivitas[0].Tipe_Item).toBe('FORM');
    });

    it('dapat membuka dialog modal detail audit forensik', async () => {
        const wrapper = pasang();

        expect(wrapper.vm.modalDetail.show).toBe(false);

        wrapper.vm.bukaDetailModal(AKTIVITAS_SAMPLE[0]);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.modalDetail.show).toBe(true);
        expect(wrapper.vm.modalDetail.item.Nama).toBe('Budi Pratama');
    });

    it('dapat mereset semua filter dengan resetFilters()', async () => {
        const wrapper = pasang();

        wrapper.vm.filterState.search = 'Budi';
        wrapper.vm.filterState.status = 'SELESAI';
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isFilterActive).toBe(true);

        wrapper.vm.resetFilters();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.filterState.search).toBe('');
        expect(wrapper.vm.filterState.status).toBe('all');
        expect(wrapper.vm.isFilterActive).toBe(false);
    });
});
