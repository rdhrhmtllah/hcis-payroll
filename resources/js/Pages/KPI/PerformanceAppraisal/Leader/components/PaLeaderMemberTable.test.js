import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import PaLeaderMemberTable from './PaLeaderMemberTable.vue';

const member = {
    participant_id: 59657,
    kode_karyawan: 'A5',
    nama: 'Amelia',
    nama_jabatan: 'Staff',
    status_peserta: 'DIIKUTKAN',
    status_hasil: 'TERTAHAN',
    penugasan_total: 4,
    penugasan_selesai: 2,
    penugasan_terlambat: 1,
    progres_persen: 50,
    nilai_akhir: null,
    terbit: false,
    alasan_tertahan: 'Perlu pemeriksaan bobot.',
};

const stubs = {
    HcisBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
    HcisButton: { props: ['label'], emits: ['click'], template: '<button @click="$emit(\'click\')">{{ label }}</button>' },
    HcisIcon: { template: '<i />' },
    HcisProgress: { props: ['value'], template: '<div class="progress">{{ value }}</div>' },
    HcisTable: {
        props: ['columns', 'rows'],
        template: '<div class="table"><div v-for="row in rows" :key="row.participant_id"><slot name="cell-nama" :row="row" /><slot name="cell-status_hasil" :row="row" /><slot name="cell-progres_persen" :row="row" /><slot name="cell-nilai_akhir" :row="row" /><slot name="cell-aksi" :row="row" /></div></div>',
    },
};

describe('PaLeaderMemberTable', () => {
    /* Kelengkapan penilai 360° tidak lagi ditampilkan di tabel leader (desain 14 Okt 2026). */
    it('menampilkan alasan tertahan tanpa menampilkan nilai sebelum terbit', () => {
        const wrapper = mount(PaLeaderMemberTable, { props: { rows: [member] }, global: { stubs } });

        expect(wrapper.text()).toContain('Amelia');
        expect(wrapper.text()).not.toContain('50%');
        expect(wrapper.text()).toContain('Perlu pemeriksaan bobot.');
        expect(wrapper.text()).toContain('Belum terbit');
    });

    it('memancarkan detail ketika aksi anggota dipilih', async () => {
        const wrapper = mount(PaLeaderMemberTable, { props: { rows: [member] }, global: { stubs } });

        /* Tombol pertama kini milik bilah saringan; yang dicari tombol aksi barisnya. */
        const aksi = wrapper.findAll('button').find((b) => b.text().includes('Buka Detail'));
        expect(aksi, 'Tombol aksi baris tidak ditemukan.').toBeTruthy();
        await aksi.trigger('click');

        expect(wrapper.emitted('detail')).toHaveLength(1);
        expect(wrapper.emitted('detail')[0][0]).toEqual(member);
    });

    it('menampilkan angka yang baru dirilis ke leader, dan menyebut karyawannya belum diberi tahu', () => {
        const wrapper = mount(PaLeaderMemberTable, {
            props: {
                rows: [
                    {
                        ...member,
                        status_hasil: 'DIHITUNG',
                        terbit: false,
                        dirilis_ke_leader: true,
                        nilai_akhir: 88.5,
                        kode_grade: 'A',
                        alasan_tertahan: null,
                    },
                ],
            },
            global: { stubs },
        });

        expect(wrapper.text()).toContain('88,5');
        expect(wrapper.text()).not.toContain('Belum terbit');
        expect(wrapper.find('[data-uji="hanya-leader"]').text()).toContain('karyawan belum diberi tahu');
    });

    it('menandai hasil yang pernah dikalibrasi setelah diterbitkan', () => {
        const wrapper = mount(PaLeaderMemberTable, {
            props: { rows: [{ ...member, terbit: true, nilai_akhir: 101.25, dikalibrasi: true, status_hasil: 'TERBIT' }] },
            global: { stubs },
        });

        expect(wrapper.text()).toContain('Dikalibrasi');
        expect(wrapper.text()).toContain('101,25');
    });
});
