import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import PaLeaderMemberDetailDialog from './PaLeaderMemberDetailDialog.vue';

const detail = {
    participant_id: 59657,
    kode_karyawan: 'A5',
    nama: 'Amelia',
    nama_jabatan: 'Staff',
    status_hasil: 'TERBIT',
    terbit: true,
    dikalibrasi: true,
    published_at: '2026-09-01 15:08:57',
    nilai_akhir: 125.25,
    kode_grade: 'A_PLUS',
    kode_kuadran: 'FUTURE_STAR_A',
    kompetensi: [
        { kode_kompetensi: 'K01', nama_kompetensi: 'Analytical Thinking', kode_type: 'MANAJERIAL', nama_type: 'Kompetensi Manajerial', nilai: 88, jumlah_penilai: 3 },
    ],
    kategori: [
        { kode_kategori: 'SELF', nama_kategori: 'Diri Sendiri', jumlah_penilai_diharapkan: 1, jumlah_penilai_mengisi: 1, bobot_terpakai: 20, nilai: 88, disembunyikan: false, alasan_disembunyikan: null },
        { kode_kategori: 'ATASAN', nama_kategori: 'Atasan', jumlah_penilai_diharapkan: 1, jumlah_penilai_mengisi: 1, bobot_terpakai: 40, nilai: null, disembunyikan: true, alasan_disembunyikan: 'Di bawah ambang keterlihatan.' },
    ],
    batas: { sumber_tim: 'N_HRIS_Organization_Relationship', min_penilai_untuk_tampil: 3, nilai_hanya_setelah_terbit: true, tanpa_nilai_per_penilai: true },
};

const stubs = {
    HcisBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
    HcisButton: { props: ['label'], emits: ['click'], template: '<button @click="$emit(\'click\')">{{ label }}</button>' },
    HcisCard: { template: '<article><slot name="header" /><slot /></article>' },
    HcisChart: { props: ['title'], template: '<div class="chart-stub">{{ title }}</div>' },
    HcisDialog: { props: ['modelValue', 'title'], template: '<div v-if="modelValue"><h1>{{ title }}</h1><slot /><slot name="footer" /></div>' },
    HcisIcon: { template: '<i />' },
    HcisResultState: { props: ['title', 'description'], template: '<div class="result-state">{{ title }} {{ description }}</div>' },
    HcisEmptyState: { props: ['title', 'description'], template: '<div class="empty-state">{{ title }} {{ description }}<slot name="actions" /></div>' },
    HcisStatCard: { props: ['label', 'value'], template: '<div class="stat-card">{{ label }} {{ value }}</div>' },
    HcisStatusBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
};

describe('PaLeaderMemberDetailDialog', () => {
    /* Kartu keterlihatan per kategori penilai dan kartu kebijakan keterlihatan dilepas dari
       tampilan leader (desain 14 Okt 2026): leader tidak lagi melihat rincian per kategori. */
    it('menampilkan score tanpa cap 100 tanpa rincian per kategori penilai', () => {
        const wrapper = mount(PaLeaderMemberDetailDialog, { props: { modelValue: true, detail }, global: { stubs } });

        expect(wrapper.text()).toContain('125,25');
        expect(wrapper.text()).not.toContain('Di bawah ambang keterlihatan.');
        expect(wrapper.text()).not.toContain('Tidak pernah ditampilkan');
        expect(wrapper.text()).toContain('Dikalibrasi');
    });

    it('tidak merender rincian angka saat hasil belum terbit', () => {
        const wrapper = mount(PaLeaderMemberDetailDialog, {
            props: { modelValue: true, detail: { ...detail, terbit: false, nilai_akhir: null, kompetensi: [], kategori: [], alasan_tertahan: 'Menunggu penerbitan.' } },
            global: { stubs },
        });

        expect(wrapper.text()).toContain('Hasil Penilaian Belum Diterbitkan');
        expect(wrapper.text()).toContain('Menunggu penerbitan.');
        expect(wrapper.text()).not.toContain('125,25');
    });
});
