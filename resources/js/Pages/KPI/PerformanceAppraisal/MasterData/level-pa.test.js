import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import LevelPa from './level-pa.vue';

const api = vi.hoisted(() => ({
    daftarLevelPa: vi.fn(),
    calonLevelPa: vi.fn(),
    cariKaryawanLevelPa: vi.fn(),
    simpanLevelPa: vi.fn(),
    ubahLevelPa: vi.fn(),
    cabutLevelPa: vi.fn(),
    imporLevelPa: vi.fn(),
    alamatEksporLevelPa: vi.fn(() => '/x.xlsx'),
}));
vi.mock('../api/paApi.js', () => ({ default: api }));

const stubs = {
    HcisHeader: { template: '<header><slot name="actions" /><slot name="tabs" /></header>' },
    HcisTabs: { props: ['modelValue', 'tabs'], emits: ['update:modelValue'], template: '<nav><button v-for="t in tabs" :key="t.key" :data-tab="t.key" @click="$emit(\'update:modelValue\', t.key)">{{ t.label }} {{ t.badge }}</button></nav>' },
    HcisFilterBar: { props: ['search', 'quickChips'], emits: ['update:search', 'chip-click', 'reset'], template: '<div class="filter"><button v-for="c in quickChips || []" :key="c.id" class="chip" @click="$emit(\'chip-click\', c)">{{ c.label }} {{ c.badge }}</button></div>' },
    HcisTable: { props: ['columns', 'rows', 'emptyTitle'], template: '<table><tr v-if="!rows.length"><td>{{ emptyTitle }}</td></tr><tr v-for="(r, i) in rows" :key="i"><td v-for="c in columns" :key="c.key"><slot :name="`cell-${c.key}`" :row="r" /></td></tr></table>' },
    HcisStatusBadge: { props: ['label'], template: '<span class="status">{{ label }}</span>' },
    HcisBadge: { props: ['label', 'variant'], emits: ['remove'], template: '<span class="badge" :data-varian="variant">{{ label }}</span>' },
    HcisPagination: { template: '<div />' },
    HcisButton: { props: ['label', 'href', 'disabled'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>' },
    HcisDialog: { props: ['modelValue', 'title'], template: '<div v-if="modelValue" class="dialog"><h3>{{ title }}</h3><slot /><slot name="footer" /></div>' },
    HcisAlert: { props: ['title'], template: '<div class="alert">{{ title }}<slot /></div>' },
    HcisIcon: { template: '<i />' },
    HcisField: { props: ['modelValue'], template: '<input :value="modelValue" />' },
    HcisSelect: { props: ['modelValue', 'disabled'], template: '<div class="sel" :data-nilai="modelValue"><slot /></div>' },
    HcisSelectOption: { props: ['value', 'label', 'disabled', 'badge'], template: '<div class="opsi" :data-nilai="value" :data-mati="disabled ? 1 : 0">{{ label }} {{ badge }}</div>' },
    HcisTextarea: { props: ['modelValue', 'label'], emits: ['update:modelValue'], template: '<textarea :value="modelValue" />' },
    HcisDatePicker: { props: ['modelValue'], template: '<input type="date" :value="modelValue" />' },
};

beforeEach(() => {
    vi.clearAllMocks();
    api.daftarLevelPa.mockResolvedValue({
        baris: [{ id: 1, kode_karyawan: 'A2', nama: 'Diva', nama_level_hris: 'SENIOR STAFF', nama_level_pa: 'OFFICER', id_level_pa: 4, alasan: 'Fungsi Officer', berlaku_dari: '2026-10-09', status: 'PERLU_DITINJAU' }],
        total: 1, halaman: 1, per_halaman: 25,
        cacah: { AKTIF: 0, PERLU_DITINJAU: 1, BELUM_BERLAKU: 0, KEDALUWARSA: 0, DICABUT: 0 },
        level: [{ id: 2, nama: 'STAFF', jumlah: 391 }, { id: 3, nama: 'SENIOR STAFF', jumlah: 4 }, { id: 4, nama: 'OFFICER', jumlah: 188 }],
    });
    api.calonLevelPa.mockResolvedValue({
        baris: [{ kode_perusahaan: '001', kode_karyawan: 'B6', nama: 'Andre', id_level_hris: 2, nama_level_hris: 'STAFF', jabatan: 'UTILITY', tanda: ['Punya 1 bawahan di relasi organisasi'], usulan_alasan: 'Terdeteksi: Punya 1 bawahan.' }],
        total: 1, saran_level: { id: 4, nama: 'OFFICER' },
    });
});

describe('Koreksi Level PA', () => {
    it('menandai koreksi yang perlu ditinjau dan menawarkan Konfirmasi', async () => {
        const w = mount(LevelPa, { global: { stubs } });
        await flushPromises();
        expect(w.find('[data-uji="lpa-perlu-tinjau"]').exists()).toBe(true);
        expect(w.text()).toContain('Konfirmasi');
        expect(w.find('.status').text()).toBe('Perlu ditinjau');
    });

    it('chip status menyaring ke server dan unduhan memakai alamat ekspor', async () => {
        const w = mount(LevelPa, { global: { stubs } });
        await flushPromises();
        await w.findAll('.chip').find((c) => c.text().startsWith('Aktif')).trigger('click');
        await flushPromises();
        expect(api.daftarLevelPa).toHaveBeenLastCalledWith({ q: '', status: 'AKTIF', halaman: 1 });

        const assign = vi.fn();
        vi.stubGlobal('location', { assign });
        await w.find('[data-uji="lpa-templat"]').trigger('click');
        expect(api.alamatEksporLevelPa).toHaveBeenCalledWith(true);
        expect(assign).toHaveBeenCalledWith('/x.xlsx');
        vi.unstubAllGlobals();
    });

    it('calon terdeteksi mengisi formulir dengan saran OFFICER dan alasan usulan', async () => {
        const w = mount(LevelPa, { global: { stubs } });
        await flushPromises();
        await w.find('[data-tab="calon"]').trigger('click');
        await flushPromises();
        expect(w.find('[data-uji="lpa-calon"]').text()).toContain('Andre');

        await w.findAll('button').find((b) => b.text() === 'Buat koreksi').trigger('click');
        await flushPromises();
        const dialog = w.find('.dialog');
        expect(dialog.text()).toContain('Andre');
        expect(dialog.find('[data-uji="lpa-karyawan"]').attributes('data-nilai')).toBe('001|B6');
        expect(dialog.find('[data-uji="lpa-level"]').attributes('data-nilai')).toBe('4');
        expect(dialog.find('textarea').element.value).toContain('Terdeteksi');
        // Level yang sama dengan level HRIS-nya (STAFF) tidak bisa dipilih.
        expect(dialog.find('[data-uji="lpa-level"] .opsi[data-nilai="2"]').attributes('data-mati')).toBe('1');
    });

    it('karyawan dicari ke server dan yang sudah punya koreksi tidak bisa dipilih', async () => {
        vi.useFakeTimers();
        api.cariKaryawanLevelPa.mockResolvedValue([
            { kode_perusahaan: '001', kode_karyawan: 'A2', nama: 'Diva', id_level_hris: 3, nama_level_hris: 'SENIOR STAFF', koreksi_id: 1 },
            { kode_perusahaan: '001', kode_karyawan: 'F19', nama: 'Erlangga', id_level_hris: 3, nama_level_hris: 'SENIOR STAFF', koreksi_id: null },
        ]);
        const w = mount(LevelPa, { global: { stubs } });
        await flushPromises();
        await w.find('[data-uji="lpa-tambah"]').trigger('click');
        await flushPromises();
        const sel = w.findAllComponents(stubs.HcisSelect)[0];
        expect(w.find('.dialog').text()).toContain('Ketik minimal 2 huruf');
        sel.vm.$emit('search', 'er');
        vi.advanceTimersByTime(350);
        vi.useRealTimers();
        await flushPromises();
        expect(api.cariKaryawanLevelPa).toHaveBeenCalledWith('er');
        const opsi = w.findAll('[data-uji="lpa-karyawan"] .opsi');
        expect(opsi).toHaveLength(2);
        expect(opsi[0].attributes('data-mati')).toBe('1');
        expect(opsi[0].text()).toContain('sudah dikoreksi');
        sel.vm.$emit('update:modelValue', ['001|F19']);
        await flushPromises();
        expect(w.find('[data-uji="lpa-terpilih"]').text()).toContain('Erlangga · SENIOR STAFF');
    });

    it('menyimpan satu koreksi untuk banyak karyawan sekaligus, dan menahan yang levelnya sama', async () => {
        vi.useFakeTimers();
        api.cariKaryawanLevelPa.mockResolvedValue([
            { kode_perusahaan: '001', kode_karyawan: 'F19', nama: 'Erlangga', id_level_hris: 3, nama_level_hris: 'SENIOR STAFF' },
            { kode_perusahaan: '001', kode_karyawan: 'B6', nama: 'Andre', id_level_hris: 2, nama_level_hris: 'STAFF' },
            { kode_perusahaan: '001', kode_karyawan: 'A4', nama: 'Aji', id_level_hris: 4, nama_level_hris: 'OFFICER' },
        ]);
        api.simpanLevelPa.mockResolvedValue({ dibuat: 2, dampak: [{ cycle_id: 7, nama: 'PA 2026', status: 'PREPARED', jumlah: 2, tindakan: 'Jalankan Siapkan lagi di siklus ini supaya Level PA terbaru dipakai.' }] });
        const w = mount(LevelPa, { global: { stubs } });
        await flushPromises();
        await w.find('[data-uji="lpa-tambah"]').trigger('click');
        await flushPromises();
        const [selKaryawan, selLevel] = w.findAllComponents(stubs.HcisSelect);
        selKaryawan.vm.$emit('search', 'a');
        selKaryawan.vm.$emit('search', 'an');
        vi.advanceTimersByTime(350);
        vi.useRealTimers();
        await flushPromises();
        selKaryawan.vm.$emit('update:modelValue', ['001|F19', '001|B6', '001|A4']);
        selLevel.vm.$emit('update:modelValue', 4);
        await flushPromises();

        // Aji sudah OFFICER di HRIS: ditandai merah dan penyimpanan ditahan.
        expect(w.find('[data-uji="lpa-bentrok"]').text()).toContain('1 orang sudah di level OFFICER');
        expect(w.findAll('.badge').find((b) => b.text().startsWith('Aji')).attributes('data-varian')).toBe('danger');
        w.findAllComponents(stubs.HcisTextarea)[0].vm.$emit('update:modelValue', 'Fungsi Officer, punya bawahan');
        await w.findAll('button').find((b) => b.text().startsWith('Simpan')).trigger('click');
        await flushPromises();
        expect(api.simpanLevelPa).not.toHaveBeenCalled();
        expect(w.text()).toContain('Keluarkan Aji');

        w.findAllComponents(stubs.HcisBadge).find((b) => b.text().startsWith('Aji')).vm.$emit('remove');
        await flushPromises();
        const tombol = w.findAll('button').find((b) => b.text().startsWith('Simpan'));
        expect(tombol.text()).toBe('Simpan untuk 2 orang');
        await tombol.trigger('click');
        await flushPromises();
        expect(api.simpanLevelPa).toHaveBeenCalledWith(expect.objectContaining({
            id_level_pa: 4,
            alasan: 'Fungsi Officer, punya bawahan',
            karyawan: [{ kode_perusahaan: '001', kode_karyawan: 'F19' }, { kode_perusahaan: '001', kode_karyawan: 'B6' }],
        }));
        // Orangnya sudah peserta di siklus yang disiapkan: admin diberi tahu langkah berikutnya.
        expect(w.find('[data-uji="lpa-dampak"]').text()).toContain('PA 2026');
        expect(w.find('[data-uji="lpa-dampak"]').text()).toContain('Jalankan Siapkan lagi');
    });
});
