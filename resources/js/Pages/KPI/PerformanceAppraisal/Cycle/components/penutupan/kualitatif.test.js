import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Tahap from './PaTahapKualitatif.vue';
import RuangKerja from './PaRuangKerjaKualitatif.vue';
import paApi from '../../../api/paApi.js';

vi.mock('../../../api/paApi.js', () => ({
    default: { daftarKesimpulanKualitatif: vi.fn(), ruangKerjaKualitatif: vi.fn(), simpanCatatanHasil: vi.fn() },
}));
vi.mock('@/components/ui', async (asli) => ({
    ...(await asli()),
    hcisMessage: { warning: vi.fn(), error: vi.fn(), success: vi.fn(), info: vi.fn() },
}));

const stubs = {
    HcisIcon: { template: '<i />' },
    HcisButton: { props: ['label', 'disabled'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>' },
    HcisDialog: { props: ['modelValue'], template: '<div v-if="modelValue" class="dialog"><slot /><slot name="footer" /></div>' },
    HcisRichText: { props: ['modelValue', 'disabled'], emits: ['update:modelValue'], template: '<textarea class="rt" :disabled="disabled" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
};

const baris = (id, nama, status = 'BELUM') => ({
    participant_id: id, nama, kode_karyawan: `K${id}`, divisi: 'IT', level: 'OFFICER', jabatan: 'Staf',
    status_hasil: 'DIHITUNG', terkunci: false, jumlah_penilai: 3, jumlah_jawaban: 6,
    kategori_total: 2, kategori_tersimpul: status === 'SUDAH' ? 2 : 0, status, ikut_balancing: false, terakhir: null,
});

const ruang = (id, nama) => ({
    peserta: { participant_id: id, nama, kode_karyawan: `K${id}` },
    terkunci: false,
    kategori: [
        { kode: 'QL01', nama: 'Strength', jawaban: [{ kategori_penilai: 'ATASAN', nama_kategori: 'Atasan', penilai: 'Budi', html: '<p>Teliti &amp; rapi</p>' }], catatan: '' },
        { kode: 'QL02', nama: 'Development Area', jawaban: [], catatan: '<p>Lama</p>' },
    ],
});

describe('PaRuangKerjaKualitatif', () => {
    beforeEach(() => {
        paApi.ruangKerjaKualitatif.mockReset();
        paApi.simpanCatatanHasil.mockReset();
        paApi.ruangKerjaKualitatif.mockResolvedValue(ruang(7, 'Dimas'));
        paApi.simpanCatatanHasil.mockResolvedValue({ ditulis: 2 });
    });

    it('kutip jawaban, simpan SELURUH kategori, lalu maju ke orang berikutnya', async () => {
        const w = mount(RuangKerja, { props: { modelValue: true, participantId: 7, bisaBerikut: true }, global: { stubs } });
        await flushPromises();

        expect(w.find('[data-uji="rkk-QL01"]').text()).toContain('Teliti & rapi');
        // Belum ada perubahan: tombol Simpan mati.
        expect(w.find('[data-uji="rkk-simpan"]').attributes('disabled')).toBeDefined();

        await w.find('.pa-rkk__kutip').trigger('click');
        expect(w.findAll('.rt')[0].element.value).toContain('Kutipan Budi (Atasan)');
        // Navigasi dikunci selagi ada perubahan yang belum disimpan.
        expect(w.find('[data-uji="rkk-berikut"]').attributes('disabled')).toBeDefined();

        await w.find('[data-uji="rkk-simpan-berikut"]').trigger('click');
        await flushPromises();

        const [pid, muatan] = paApi.simpanCatatanHasil.mock.calls[0];
        expect(pid).toBe(7);
        // Kategori yang tidak disentuh tetap dikirim — PUT menimpa semua catatan admin.
        expect(Object.keys(muatan)).toEqual(['QL01', 'QL02']);
        expect(muatan.QL02).toEqual(['<p>Lama</p>']);
        expect(w.emitted('tersimpan')[0]).toEqual([7]);
        expect(w.emitted('navigasi')[0]).toEqual(['berikut']);
    });

    it('hasil terbit hanya bisa dibaca', async () => {
        paApi.ruangKerjaKualitatif.mockResolvedValue({ ...ruang(7, 'Dimas'), terkunci: true });
        const w = mount(RuangKerja, { props: { modelValue: true, participantId: 7, bisaBerikut: true }, global: { stubs } });
        await flushPromises();
        expect(w.find('[data-uji="rkk-terkunci"]').exists()).toBe(true);
        expect(w.find('[data-uji="rkk-simpan"]').exists()).toBe(false);
        expect(w.find('.pa-rkk__kutip').exists()).toBe(false);
        expect(w.find('.rt').attributes('disabled')).toBeDefined();
    });
});

describe('PaTahapKualitatif', () => {
    beforeEach(() => {
        paApi.daftarKesimpulanKualitatif.mockReset();
        paApi.ruangKerjaKualitatif.mockReset();
        paApi.ruangKerjaKualitatif.mockImplementation((pid) => Promise.resolve(ruang(pid, `Orang ${pid}`)));
    });

    it('memuat daftar dengan ringkas dan opsi, lalu saringan status dikirim ke server', async () => {
        paApi.daftarKesimpulanKualitatif.mockResolvedValue({
            peserta: [baris(1, 'Aji'), baris(2, 'Budi', 'SUDAH')], total: 2,
            ringkas: { total: 2, sudah: 1, belum: 1 }, opsi: { divisi: [{ value: 1, label: 'IT' }], level: [] },
        });
        const w = mount(Tahap, { props: { cycleId: 9 }, global: { stubs } });
        await flushPromises();

        expect(paApi.daftarKesimpulanKualitatif).toHaveBeenCalledWith(9, expect.objectContaining({ halaman: 1, per_halaman: 25, dengan_opsi: 1 }));
        expect(w.text()).toContain('Belum disimpulkan');
        expect(w.find('[data-uji="tulis-1"]').text()).toBe('Tulis');
        expect(w.find('[data-uji="tulis-2"]').text()).toBe('Ubah');

        w.findComponent({ name: 'HcisFilterBar' }).vm.$emit('chip-click', { value: 'BELUM' });
        await flushPromises();
        expect(paApi.daftarKesimpulanKualitatif).toHaveBeenLastCalledWith(9, expect.objectContaining({ status: 'BELUM', halaman: 1 }));
    });

    it('di saringan Belum, orang yang baru disimpan hilang dan orang berikutnya menempati posisi yang sama', async () => {
        paApi.daftarKesimpulanKualitatif
            .mockResolvedValueOnce({ peserta: [baris(1, 'Aji'), baris(2, 'Budi')], total: 2, ringkas: { total: 2, sudah: 0, belum: 2 } })
            .mockResolvedValueOnce({ peserta: [baris(1, 'Aji'), baris(2, 'Budi')], total: 2, ringkas: { total: 2, sudah: 0, belum: 2 } })
            .mockResolvedValueOnce({ peserta: [baris(2, 'Budi')], total: 1, ringkas: { total: 2, sudah: 1, belum: 1 } });
        const w = mount(Tahap, { props: { cycleId: 9 }, global: { stubs } });
        await flushPromises();
        w.findComponent({ name: 'HcisFilterBar' }).vm.$emit('chip-click', { value: 'BELUM' });
        await flushPromises();

        await w.find('[data-uji="tulis-1"]').trigger('click');
        await flushPromises();
        const rk = w.findComponent(RuangKerja);
        expect(rk.props('participantId')).toBe(1);

        rk.vm.$emit('tersimpan', 1);
        rk.vm.$emit('navigasi', 'berikut');
        await flushPromises();

        expect(rk.props('participantId')).toBe(2);
        expect(w.emitted('berubah')).toHaveLength(1);
    });
});
