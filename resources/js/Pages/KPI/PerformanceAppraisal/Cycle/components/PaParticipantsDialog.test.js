import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PaParticipantsDialog from './PaParticipantsDialog.vue';

const uiStubs = {
    HcisBadge: { template: '<span class="badge-stub"><slot /></span>' },
    HcisButton: {
        props: ['label', 'disabled'],
        emits: ['click'],
        template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>',
    },
    HcisDialog: {
        props: ['modelValue'],
        template: '<div v-if="modelValue"><slot /><slot name="footer-start" /><slot name="footer" /></div>',
    },
    HcisFilterBar: {
        name: 'HcisFilterBar',
        props: ['search', 'quickChips', 'filters', 'filterValues', 'resultsCount'],
        template: '<div class="filter-bar-stub">{{ search }} {{ resultsCount }}</div>',
    },
    HcisIcon: { template: '<i />' },
    HcisResultState: {
        props: ['title', 'description'],
        template: '<div class="result-state"><strong>{{ title }}</strong><span>{{ description }}</span></div>',
    },
    HcisStatusBadge: { template: '<span class="status-badge-stub" />' },
    HcisTable: {
        props: ['rows', 'loading'],
        template:
            '<div class="table-stub"><div v-for="row in rows" :key="row.Id_PA_Participant"><slot name="cell-peserta" :row="row" /><slot name="cell-hasil" :row="row" /><slot name="cell-aksi" :row="row" /></div></div>',
    },
};

function mountDialog(props = {}) {
    return mount(PaParticipantsDialog, {
        props: { modelValue: true, cycle: { Kode_Cycle: 'PA-2026' }, ...props },
        global: { stubs: uiStubs },
    });
}

describe('PaParticipantsDialog', () => {
    it('uses the shared filter and table components for participant data', () => {
        const wrapper = mountDialog({
            items: [
                {
                    Id_PA_Participant: 501,
                    Nama_Karyawan_Saat_Cycle: 'Budi Santoso',
                    Kode_Karyawan: 'K001',
                    Status_Peserta: 'DIIKUTKAN',
                },
            ],
            total: 1,
        });

        expect(wrapper.find('.filter-bar-stub').exists()).toBe(true);
        expect(wrapper.find('.table-stub').exists()).toBe(true);
        expect(wrapper.text()).toContain('Budi Santoso');
        expect(wrapper.text()).toContain('1 peserta total');
    });

    it('menyediakan pintu langsung untuk binding penilai saat operasi diizinkan server', async () => {
        const wrapper = mountDialog({
            canAssign: true,
            items: [{ Id_PA_Participant: 501, Nama_Karyawan_Saat_Cycle: 'Budi Santoso' }],
        });

        await wrapper.findAll('button').find((button) => button.text() === 'Kelola penilai').trigger('click');

        expect(wrapper.emitted('assign')).toEqual([[expect.objectContaining({ Id_PA_Participant: 501 })]]);
    });

    it('keeps empty and error states separate', () => {
        const emptyWrapper = mountDialog({ items: [] });
        expect(emptyWrapper.text()).toContain('Tidak ada peserta yang cocok');
        expect(emptyWrapper.text()).not.toContain('Peserta gagal dimuat');

        const errorWrapper = mountDialog({ error: 'Siklus terkunci.', errorCode: 'PA_CYCLE_LOCKED', requestId: 'req-1' });
        expect(errorWrapper.text()).toContain('Peserta gagal dimuat');
        expect(errorWrapper.text()).toContain('Siklus terkunci.');
        expect(errorWrapper.text()).toContain('PA_CYCLE_LOCKED');
        expect(errorWrapper.text()).toContain('req-1');
        expect(errorWrapper.text()).not.toContain('Tidak ada peserta yang cocok');
    });

    it('menampilkan alasan tertahan utuh untuk lebih dari satu sebab', () => {
        const reason = 'Bobot KPI belum sesuai. Kategori kompetensi juga belum memenuhi ambang penilai.';
        const wrapper = mountDialog({
            items: [{ Id_PA_Participant: 501, Nama_Karyawan_Saat_Cycle: 'Budi Santoso', Status_Hasil: 'TERTAHAN', Alasan_Tertahan: reason }],
        });

        expect(wrapper.find('.pa-held-reason').text()).toContain(reason);
        expect(wrapper.find('.pa-held-reason').attributes('title')).toBe(reason);
    });

    it('emits current filters when the shared filter bar changes', async () => {
        const wrapper = mountDialog({ items: [{ Id_PA_Participant: 501 }] });
        const filterBar = wrapper.findComponent({ name: 'HcisFilterBar' });

        expect(filterBar.props('filterValues')).toEqual({
            status_hasil: '',
            id_divisi: '',
            id_sub_divisi: '',
            id_level: '',
            kode_jabatan: '',
            lokasi: '',
        });

        filterBar.vm.$emit('filter-change', { key: 'status_hasil', value: 'TERTAHAN' });
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('load')).toEqual([[expect.objectContaining({ status_hasil: 'TERTAHAN', halaman: 1 })]]);

        filterBar.vm.$emit('filter-change', { key: 'id_divisi', value: 4 });
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('load')).toEqual([
            [expect.objectContaining({ status_hasil: 'TERTAHAN', halaman: 1 })],
            [expect.objectContaining({ status_hasil: 'TERTAHAN', id_divisi: 4, halaman: 1 })],
        ]);
    });

    it('menyediakan filter divisi, departemen, level, jabatan, dan lokasi dengan search select (filterable)', () => {
        const opsiProps = {
            divisi: [{ value: 1, label: 'Technology' }],
            sub_divisi: [{ value: 10, label: 'Core Engineering' }],
            level: [{ value: 3, label: 'SENIOR OFFICER' }],
            jabatan: [{ value: 'ENG-SR', label: 'Senior Engineer' }],
            lokasi: [{ value: 'HO-JKT', label: 'Head Office Jakarta' }],
        };

        const wrapper = mountDialog({
            opsi: opsiProps,
            items: [{ Id_PA_Participant: 501 }],
        });

        const filterBar = wrapper.findComponent({ name: 'HcisFilterBar' });
        const definitions = filterBar.props('filters') || [];

        const keys = definitions.map((d) => d.key);
        expect(keys).toContain('id_divisi');
        expect(keys).toContain('id_sub_divisi');
        expect(keys).toContain('id_level');
        expect(keys).toContain('kode_jabatan');
        expect(keys).toContain('lokasi');
        expect(keys).toContain('status_hasil');

        // Pastikan semua pilihan bertipe select dengan search filterable aktif
        definitions.forEach((def) => {
            expect(def.type).toBe('select');
            expect(def.filterable).toBe(true);
        });

        const divisiDef = definitions.find((d) => d.key === 'id_divisi');
        expect(divisiDef.options).toEqual([{ value: 1, label: 'Technology' }]);

        const deptDef = definitions.find((d) => d.key === 'id_sub_divisi');
        expect(deptDef.label).toBe('Departemen');
        expect(deptDef.options).toEqual([{ value: 10, label: 'Core Engineering' }]);

        const jabatanDef = definitions.find((d) => d.key === 'kode_jabatan');
        expect(jabatanDef.options).toEqual([{ value: 'ENG-SR', label: 'Senior Engineer' }]);
    });
});
