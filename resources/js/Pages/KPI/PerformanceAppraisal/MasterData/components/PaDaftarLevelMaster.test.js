import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import PaDaftarLevelMaster from './PaDaftarLevelMaster.vue';

/*
 | Daftar Level PA: level HRIS hanya-baca, level khusus PA bisa ditambah (dengan salinan matriks
 | dari level dasar), diubah, dan dinonaktifkan.
 */

const paApiMock = vi.hoisted(() => ({
    daftarLevelMaster: vi.fn(),
    tambahLevelMaster: vi.fn(),
    ubahLevelMaster: vi.fn(),
    aturAktifLevelMaster: vi.fn(),
    periksaLevelMaster: vi.fn(),
    sinkronLevelMaster: vi.fn(),
}));
vi.mock('../../api/paApi.js', () => ({ default: paApiMock }));

const stubs = {
    HcisTable: {
        props: ['rows', 'columns'],
        template: '<table><tr v-for="r in rows" :key="r.id" :data-baris="r.id"><td v-for="c in columns" :key="c.key"><slot :name="`cell-${c.key}`" :row="r" /></td></tr></table>',
    },
    HcisDialog: { props: ['modelValue'], template: '<div v-if="modelValue" class="dialog"><slot /><slot name="footer" /></div>' },
    HcisButton: { props: ['label', 'disabled', 'loading'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>' },
    HcisField: { props: ['modelValue'], emits: ['update:modelValue'], template: '<input class="nama" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
    HcisSelect: { props: ['modelValue'], emits: ['update:modelValue'], template: '<div class="sel"><slot /></div>' },
    HcisSelectOption: { props: ['value', 'label'], template: '<span class="opsi">{{ label }}</span>' },
    HcisInputNumber: { props: ['modelValue'], template: '<span class="urutan">{{ modelValue }}</span>' },
    HcisCheckbox: { props: ['modelValue', 'label'], template: '<label class="salin">{{ modelValue }}</label>' },
    HcisBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
    HcisAlert: { props: ['description'], template: '<div class="galat">{{ description }}</div>' },
};

const daftar = [
    { id: 4, nama: 'OFFICER', sumber: 'HRIS', id_level_dasar: 4, nama_level_dasar: null, urutan: 4, aktif: true, dipakai: 0 },
    { id: 1000000, nama: 'OFFICER SPECIALIST', sumber: 'PA', id_level_dasar: 4, nama_level_dasar: 'OFFICER', urutan: 4.5, aktif: true, dipakai: 3 },
    { id: 5, nama: 'SENIOR OFFICER', sumber: 'HRIS', id_level_dasar: 5, nama_level_dasar: null, urutan: 5, aktif: true, dipakai: 0 },
];

describe('PaDaftarLevelMaster', () => {
    beforeEach(() => {
        Object.values(paApiMock).forEach((f) => f.mockReset());
        paApiMock.daftarLevelMaster.mockResolvedValue({ level: daftar });
        paApiMock.tambahLevelMaster.mockResolvedValue({ id: 1000001, disalin: { bobot_pilar: 1, komposisi_penilai: 1 } });
        paApiMock.periksaLevelMaster.mockResolvedValue({ sinkron: true, jumlah_hris: 16, tersalin: 16, belum: [], beda: [], hilang: [] });
    });

    it('level HRIS hanya-baca, level khusus PA bisa diubah dan dinonaktifkan', async () => {
        const w = mount(PaDaftarLevelMaster, { global: { stubs } });
        await flushPromises();

        expect(w.find('[data-baris="4"]').text()).toContain('ikut HRIS');
        expect(w.find('[data-baris="1000000"]').text()).toContain('Khusus PA');
        expect(w.find('[data-baris="1000000"]').text()).toContain('Dasar: OFFICER');
        expect(w.find('[data-uji="dlm-ubah-1000000"]').exists()).toBe(true);
        expect(w.find('[data-uji="dlm-ubah-4"]').exists()).toBe(false);

        paApiMock.aturAktifLevelMaster.mockResolvedValue({});
        await w.find('[data-uji="dlm-aktif-1000000"]').trigger('click');
        expect(paApiMock.aturAktifLevelMaster).toHaveBeenCalledWith(1000000, false);
    });

    it('menambah level khusus PA dengan salinan matriks dari level dasar', async () => {
        const w = mount(PaDaftarLevelMaster, { global: { stubs } });
        await flushPromises();
        await w.find('[data-uji="dlm-tambah"]').trigger('click');

        /* Hanya level HRIS yang bisa jadi level dasar. */
        expect(w.findAll('.opsi').map((o) => o.text())).toEqual(['OFFICER', 'SENIOR OFFICER']);
        await w.find('.nama').setValue('Officer Analyst');
        w.vm.$.setupState.form.id_level_dasar = 4;
        await flushPromises();
        expect(w.find('.urutan').text()).toBe('4.5');

        await w.find('[data-uji="dlm-simpan"]').trigger('click');
        await flushPromises();

        expect(paApiMock.tambahLevelMaster).toHaveBeenCalledWith({ nama: 'Officer Analyst', id_level_dasar: 4, urutan: 4.5, salin_matriks: true });
        expect(w.emitted('berubah')).toBeTruthy();
    });

    /* Di produksi level HRIS bisa berbeda: keadaannya disebut, sinkron lewat tombol. */
    it('menyebut level HRIS yang belum tersalin/berbeda/hilang dan menyinkronkan', async () => {
        paApiMock.periksaLevelMaster.mockResolvedValue({
            sinkron: false, jumlah_hris: 17, tersalin: 16,
            belum: [{ id: 22, nama: 'KONTRAK' }],
            beda: [{ id: 4, nama_pa: 'OFFICER', nama_hris: 'OFFICER I', urutan_pa: 4, urutan_hris: 4 }],
            hilang: [{ id: 15, nama: 'BELUM', dipakai: 3 }],
        });
        paApiMock.sinkronLevelMaster.mockResolvedValue({
            hasil: { ditambah: 1, diperbarui: 1 },
            periksa: { sinkron: true, jumlah_hris: 17, tersalin: 17, belum: [], beda: [], hilang: [{ id: 15, nama: 'BELUM', dipakai: 3 }] },
        });
        const w = mount(PaDaftarLevelMaster, { global: { stubs } });
        await flushPromises();

        expect(w.find('[data-uji="dlm-sinkron"]').text()).toContain('Belum selaras dengan HRIS: 1 level HRIS belum tersalin, 1 level berbeda nama/urutan');
        expect(w.find('[data-uji="dlm-sinkron-rincian"]').text()).toContain('OFFICER → OFFICER I');
        expect(w.find('[data-uji="dlm-sinkron-hilang"]').text()).toContain('BELUM (dipakai 3 baris)');

        await w.find('[data-uji="dlm-sinkron-btn"]').trigger('click');
        await flushPromises();
        expect(w.find('[data-uji="dlm-sinkron"]').text()).toContain('Selaras dengan HRIS: 17 dari 17');
        expect(w.find('[data-uji="dlm-sinkron-rincian"]').exists()).toBe(false);
    });
});
