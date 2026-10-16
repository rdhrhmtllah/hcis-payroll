import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Dialog from './PaKompetensiTingkatDialog.vue';

const api = vi.hoisted(() => ({ tingkatKompetensi: vi.fn(), simpanTingkatKompetensi: vi.fn() }));
vi.mock('../../api/paApi.js', () => ({ default: api }));

const stubs = {
    HcisDualPaneDialog: { props: ['modelValue', 'title', 'description'], template: '<div v-if="modelValue" class="dialog"><div class="kiri"><slot name="left" /></div><div class="kanan"><slot name="right" /></div><slot name="footer-start" /><slot name="footer" /></div>' },
    HcisButton: { props: ['label', 'disabled', 'title'], emits: ['click'], template: '<button :disabled="disabled" :data-title="title" @click="$emit(\'click\')">{{ label }}</button>' },
    HcisAlert: { props: ['title', 'description'], template: '<div class="alert">{{ title }}</div>' },
    HcisBadge: { props: ['label'], template: '<span class="badge">{{ label }}</span>' },
    HcisIcon: { template: '<i />' },
    HcisEmptyState: { props: ['title'], template: '<div class="kosong">{{ title }}<slot /></div>' },
    HcisField: { props: ['modelValue', 'error'], emits: ['update:modelValue'], template: '<div><input class="nama" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /><span class="galat-nama">{{ error }}</span></div>' },
    HcisTextarea: { props: ['modelValue'], emits: ['update:modelValue'], template: '<textarea class="eb" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
};
const KOMP = { Id_PA_Competency: 7, Kode_Competency: 'K01', Nama_Competency: 'Analytical Thinking' };
const ADA = [
    { id: 11, tingkat: 1, nama: 'Basic', expected_behaviour: 'Mengenali masalah', dipakai_level: 2 },
    { id: 12, tingkat: 2, nama: 'Advanced', expected_behaviour: '', dipakai_level: 0 },
];

beforeEach(() => vi.clearAllMocks());

async function pasang(daftar = ADA) {
    api.tingkatKompetensi.mockResolvedValue(daftar);
    const w = mount(Dialog, { props: { kompetensi: KOMP }, global: { stubs } });
    await flushPromises();
    return w;
}
const tombol = (w, teks) => w.findAll('button').find((b) => b.text() === teks || b.attributes('data-title') === teks);

describe('PaKompetensiTingkatDialog', () => {
    it('menampilkan daftar dengan status Expected Behaviour dan pemakaian di matriks', async () => {
        const w = await pasang();
        const kartu = w.findAll('[data-uji="tkt-baris"]');
        expect(kartu).toHaveLength(2);
        expect(kartu[0].text()).toContain('Basic');
        expect(kartu[0].text()).toContain('Expected Behaviour terisi');
        expect(kartu[0].text()).toContain('dipakai 2 level');
        expect(kartu[1].text()).toContain('Expected Behaviour kosong');
        // Tingkat yang dituntut matriks tidak bisa dihapus.
        expect(w.find('[data-uji="tkt-hapus"]').attributes('disabled')).toBeDefined();
        expect(w.find('[data-uji="tkt-status"]').text()).toContain('Semua perubahan tersimpan');
        expect(w.find('[data-uji="tkt-simpan"]').attributes('disabled')).toBeDefined();
    });

    it('mengubah urutan menomori ulang tingkat menurut posisi saat disimpan', async () => {
        const w = await pasang();
        await w.findAll('[data-uji="tkt-baris"]')[1].trigger('click');
        await w.find('textarea.eb').setValue('Membangun model analisis');
        await w.findAll('button').filter((b) => b.attributes('data-title') === 'Naikkan')[1].trigger('click');
        expect(w.find('[data-uji="tkt-status"]').text()).toContain('belum disimpan');

        api.simpanTingkatKompetensi.mockResolvedValue([
            { id: 12, tingkat: 1, nama: 'Advanced', expected_behaviour: 'Membangun model analisis', dipakai_level: 0 },
            { id: 11, tingkat: 2, nama: 'Basic', expected_behaviour: 'Mengenali masalah', dipakai_level: 2 },
        ]);
        await w.find('[data-uji="tkt-simpan"]').trigger('click');
        await flushPromises();
        expect(api.simpanTingkatKompetensi).toHaveBeenCalledWith(7, [
            { id: 12, tingkat: 1, nama: 'Advanced', expected_behaviour: 'Membangun model analisis' },
            { id: 11, tingkat: 2, nama: 'Basic', expected_behaviour: 'Mengenali masalah' },
        ]);
        expect(w.find('[data-uji="tkt-status"]').text()).toContain('Semua perubahan tersimpan');
    });

    it('nama kosong ditahan sebelum dikirim, dan tingkat bermasalah dipilih', async () => {
        const w = await pasang();
        await w.find('[data-uji="tkt-tambah"]').trigger('click');
        await w.find('[data-uji="tkt-simpan"]').trigger('click');
        await flushPromises();
        expect(api.simpanTingkatKompetensi).not.toHaveBeenCalled();
        expect(w.text()).toContain('Nama tingkat 3 wajib diisi.');
        expect(w.find('.galat-nama').text()).toContain('wajib diisi');
    });

    it('kosong → "Pakai 4 tingkat standar" mengisi Basic s.d. Expert', async () => {
        const w = await pasang([]);
        expect(w.find('[data-uji="tkt-kosong"]').exists()).toBe(true);
        await w.find('[data-uji="tkt-standar"]').trigger('click');
        expect(w.findAll('[data-uji="tkt-baris"]').map((k) => k.text().split('Expected')[0].replace(/\d/g, '').trim()))
            .toEqual(['Basic', 'Intermediate', 'Advanced', 'Expert']);
    });

    it('menutup dengan perubahan belum disimpan meminta konfirmasi sekali lagi', async () => {
        const w = await pasang();
        await w.find('input.nama').setValue('Dasar');
        await w.find('[data-uji="tkt-tutup"]').trigger('click');
        expect(w.emitted('tutup')).toBeFalsy();
        expect(w.find('[data-uji="tkt-status"]').text()).toContain('tekan lagi untuk membuang');
        await w.find('[data-uji="tkt-tutup"]').trigger('click');
        expect(w.emitted('tutup')).toBeTruthy();
    });

    it('"Salin dari tingkat sebelumnya" memakai Expected Behaviour tingkat di atasnya', async () => {
        const w = await pasang();
        await w.findAll('[data-uji="tkt-baris"]')[1].trigger('click');
        await w.find('[data-uji="tkt-salin"]').trigger('click');
        expect(w.find('textarea.eb').element.value).toBe('Mengenali masalah');
    });
});
