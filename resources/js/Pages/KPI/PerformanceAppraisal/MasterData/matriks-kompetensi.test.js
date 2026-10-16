import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Halaman from './matriks-kompetensi.vue';

const api = vi.hoisted(() => ({ matriksKompetensi: vi.fn(), simpanMatriksKompetensi: vi.fn() }));
vi.mock('../api/paApi.js', () => ({ default: api }));
vi.mock('@/components/ui', async (asli) => ({ ...(await asli()), hcisMessage: { success: vi.fn(), warning: vi.fn(), info: vi.fn(), error: vi.fn() } }));

const stubs = {
    HcisHeader: { template: '<header><slot name="actions" /></header>' },
    HcisButton: { props: ['label', 'disabled', 'loading'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>' },
    HcisAlert: { props: ['title'], template: '<div class="alert">{{ title }}</div>' },
    HcisBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
    HcisIcon: { template: '<i />' },
    HcisField: { props: ['modelValue'], emits: ['update:modelValue'], template: '<input />' },
    HcisSkeleton: { template: '<div />' },
    HcisResultState: { template: '<div />' },
    HcisEmptyState: { props: ['title'], template: '<div>{{ title }}</div>' },
    HcisDialog: { props: ['modelValue'], template: '<div v-if="modelValue" class="dialog"><slot /><slot name="footer" /></div>' },
    HcisSwitch: { props: ['modelValue', 'disabled', 'ariaLabel', 'activeText'], emits: ['update:modelValue'], template: '<input type="checkbox" class="sw" :checked="modelValue" :disabled="disabled" :aria-label="ariaLabel || activeText" @change="$emit(\'update:modelValue\', $event.target.checked)" />' },
    HcisSelect: { props: ['modelValue', 'disabled'], emits: ['update:modelValue'], template: '<div class="sel" :data-nilai="modelValue" :data-mati="disabled ? 1 : 0"><slot /></div>' },
    HcisSelectOption: { props: ['value', 'label'], template: '<span class="opsi">{{ label }}</span>' },
    PaDaftarPerilaku: { props: ['teks'], template: '<p class="eb">{{ teks }}</p>' },
};

const DATA = {
    level: [{ id: 4, nama: 'OFFICER', dikecualikan: false }, { id: 5, nama: 'SENIOR OFFICER', dikecualikan: false }, { id: 2, nama: 'STAFF', dikecualikan: true }],
    kompetensi: [
        { id: 1, kode: 'K01', nama: 'Analytical Thinking', jenis_id: 1, jenis_kode: 'MANAJERIAL', jenis_nama: 'Kompetensi Manajerial',
            tingkat: [{ id: 11, tingkat: 1, nama: 'Basic', expected_behaviour: 'Mengenali masalah' }, { id: 12, tingkat: 2, nama: 'Intermediate', expected_behaviour: 'Mengurai masalah' }] },
        { id: 2, kode: 'K02', nama: 'Problem Solving', jenis_id: 1, jenis_kode: 'MANAJERIAL', jenis_nama: 'Kompetensi Manajerial',
            tingkat: [{ id: 21, tingkat: 1, nama: 'Basic', expected_behaviour: '' }, { id: 22, tingkat: 2, nama: 'Intermediate', expected_behaviour: 'Menyusun opsi' }] },
        { id: 3, kode: 'BH01', nama: 'Integrity', jenis_id: 3, jenis_kode: 'BEHAVIOR', jenis_nama: 'Behavior', tingkat: [] },
        { id: 4, kode: 'QL01', nama: 'Strength', jenis_id: 4, jenis_kode: 'KUALITATIF', jenis_nama: 'Qualitative Question',
            tingkat: [{ id: 41, tingkat: 1, nama: 'Basic', expected_behaviour: 'Menyebut kekuatan' }] },
    ],
    sel: [{ id_level: 4, kompetensi_id: 1, tingkat_id: 12 }, { id_level: 4, kompetensi_id: 4, tingkat_id: 41 }, { id_level: 5, kompetensi_id: 2, tingkat_id: 22 }],
};

beforeEach(() => {
    vi.clearAllMocks();
    api.matriksKompetensi.mockResolvedValue(JSON.parse(JSON.stringify(DATA)));
});

async function pasang() {
    const w = mount(Halaman, { global: { stubs } });
    await flushPromises();
    return w;
}
const baris = (w, kode) => w.findAll('[data-uji="mk-kompetensi"]').find((b) => b.text().includes(kode));
const sakelar = (w, kode) => baris(w, kode).find('input.sw');
const tombol = (w, awal) => w.findAll('button').find((b) => b.text().startsWith(awal));

describe('Matriks Kompetensi per level', () => {
    it('menampilkan level PA saja (non-PA bisa dimunculkan) dan status tiap kompetensi', async () => {
        const w = await pasang();
        expect(w.findAll('[data-uji="mk-level"]').map((b) => b.text())).toEqual([expect.stringContaining('OFFICER'), expect.stringContaining('SENIOR OFFICER')]);
        expect(baris(w, 'K01').text()).toContain('L2 · Intermediate');
        expect(baris(w, 'K02').text()).toContain('Tidak dinilai di level ini');
        // Qualitative Question juga bertingkat seperti jenis lain.
        expect(baris(w, 'QL01').text()).toContain('L1 · Basic');
        // Belum punya tingkat → tidak bisa dinilai, dengan arahan membuat tingkat.
        expect(sakelar(w, 'BH01').attributes('disabled')).toBeDefined();
        expect(baris(w, 'BH01').text()).toContain('Belum punya tingkat');
        // Tidak ada lagi pilihan "Dinilai (Umum)".
        expect(w.text()).not.toContain('Umum');

        await w.find('[data-uji="mk-non-pa"]').setValue(true);
        expect(w.findAll('[data-uji="mk-level"]')).toHaveLength(3);
    });

    it('menghidupkan kompetensi wajib memilih tingkat sebelum simpan; simpan mengirim tingkat per kompetensi', async () => {
        const w = await pasang();
        await sakelar(w, 'K02').setValue(true);
        expect(baris(w, 'K02').text()).toContain('Pilih tingkat yang dituntut');
        await tombol(w, 'Simpan OFFICER').trigger('click');
        expect(api.simpanMatriksKompetensi).not.toHaveBeenCalled();
        expect(w.find('[data-uji="mk-galat"]').text()).toContain('1 kompetensi dinilai tanpa tingkat');

        baris(w, 'K02').findComponent(stubs.HcisSelect).vm.$emit('update:modelValue', 21);
        await flushPromises();
        api.simpanMatriksKompetensi.mockResolvedValue({ peringatan: [] });
        await tombol(w, 'Simpan OFFICER').trigger('click');
        await flushPromises();
        expect(api.simpanMatriksKompetensi).toHaveBeenCalledWith(4, { 1: 12, 2: 21, 4: 41 });
        expect(w.find('[data-uji="mk-status"]').text()).toContain('Tersimpan');
    });

    it('sakelar jenis mematikan seluruh jenis untuk level ini; "Set semua ke tingkat" mengisi tingkat bernomor sama', async () => {
        const w = await pasang();
        const jenis = w.find('[data-uji="mk-jenis-MANAJERIAL"]');
        await jenis.find('[data-uji="mk-jenis-switch"]').setValue(false);
        expect(baris(w, 'K01').text()).toContain('Tidak dinilai di level ini');

        jenis.findComponent(stubs.HcisSelect).vm.$emit('update:modelValue', 1);
        await flushPromises();
        expect(baris(w, 'K01').text()).toContain('L1 · Basic');
        expect(baris(w, 'K02').text()).toContain('L1 · Basic');

        api.simpanMatriksKompetensi.mockResolvedValue({ peringatan: [] });
        await tombol(w, 'Simpan OFFICER').trigger('click');
        await flushPromises();
        expect(api.simpanMatriksKompetensi).toHaveBeenCalledWith(4, { 1: 11, 2: 21, 4: 41 });
    });

    it('Batalkan mengembalikan perubahan; Salin menyalin pengaturan level lain sebagai draf', async () => {
        const w = await pasang();
        await sakelar(w, 'K01').setValue(false);
        expect(w.find('[data-uji="mk-status"]').text()).toContain('belum disimpan');
        await w.find('[data-uji="mk-batal"]').trigger('click');
        expect(baris(w, 'K01').text()).toContain('L2 · Intermediate');

        await w.find('[data-uji="mk-salin"]').trigger('click');
        w.findAllComponents(stubs.HcisSelect).at(-1).vm.$emit('update:modelValue', 5);
        await flushPromises();
        await w.find('[data-uji="mk-salin-jalankan"]').trigger('click');
        expect(baris(w, 'K01').text()).toContain('Tidak dinilai');
        expect(baris(w, 'K02').text()).toContain('L2 · Intermediate');
    });

    it('kompetensi lama yang dinilai tanpa tingkat dan belum punya tingkat tetap bisa dimatikan', async () => {
        const data = JSON.parse(JSON.stringify(DATA));
        data.sel.push({ id_level: 4, kompetensi_id: 3, tingkat_id: null });
        api.matriksKompetensi.mockResolvedValue(data);
        const w = await pasang();
        expect(sakelar(w, 'BH01').attributes('disabled')).toBeUndefined();
        await tombol(w, 'Simpan OFFICER').trigger('click');
        expect(api.simpanMatriksKompetensi).not.toHaveBeenCalled();

        await sakelar(w, 'BH01').setValue(false);
        expect(sakelar(w, 'BH01').attributes('disabled')).toBeDefined();
        api.simpanMatriksKompetensi.mockResolvedValue({ peringatan: [] });
        await tombol(w, 'Simpan OFFICER').trigger('click');
        await flushPromises();
        expect(api.simpanMatriksKompetensi).toHaveBeenCalledWith(4, { 1: 12, 4: 41 });
    });

    it('pratinjau Expected Behaviour mengikuti tingkat terpilih', async () => {
        const w = await pasang();
        expect(baris(w, 'K01').find('.eb').text()).toBe('Mengurai masalah');
    });

    it('accordion jenis kompetensi dapat dibuka dan ditutup, termasuk toggle semua', async () => {
        const w = await pasang();
        const jenisManajerial = w.find('[data-uji="mk-jenis-MANAJERIAL"]');
        const badanManajerial = jenisManajerial.find('.pa-mk-jenis__badan');
        // v-show: isVisible() butuh attachTo; cukup periksa display elemennya.
        const terbuka = () => badanManajerial.element.style.display !== 'none';
        expect(terbuka()).toBe(true);

        // Toggle satu jenis via tombol accordion
        await jenisManajerial.find('[data-uji="mk-jenis-toggle"]').trigger('click');
        expect(terbuka()).toBe(false);

        // Buka kembali via klik kepala jenis
        await jenisManajerial.find('.pa-mk-jenis__kepala').trigger('click');
        expect(terbuka()).toBe(true);

        // Toggle semua accordion via tombol di header
        const tombolToggleSemua = w.find('[data-uji="mk-toggle-semua"]');
        expect(tombolToggleSemua.text()).toContain('Tutup semua');
        await tombolToggleSemua.trigger('click');
        expect(terbuka()).toBe(false);

        expect(tombolToggleSemua.text()).toContain('Buka semua');
        await tombolToggleSemua.trigger('click');
        expect(terbuka()).toBe(true);
    });
});
