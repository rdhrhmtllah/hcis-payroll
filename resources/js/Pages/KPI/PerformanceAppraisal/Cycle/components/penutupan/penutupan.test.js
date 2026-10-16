import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Stepper from './PaPenutupanStepper.vue';
import Terbit from './PaTahapTerbit.vue';
import Kepala from './PaTahapKepala.vue';
import { tahapDariUrl } from './tahapPenutupan.js';
import paApi from '../../../api/paApi.js';
import { HcisFilterBar, HcisTable } from '@/components/ui';

vi.mock('../../../api/paApi.js', () => ({ default: {
    rilisLeader: vi.fn(), daftarPeserta: vi.fn(), terbitkan: vi.fn(),
    ringkasanPenerbitan: vi.fn(), periksaKesimpulanSebelumTerbit: vi.fn(), terbitkanPeserta: vi.fn(),
} }));
vi.mock('@/components/ui', async (asli) => ({
    ...(await asli()),
    hcisMessage: { warning: vi.fn(), error: vi.fn(), success: vi.fn(), info: vi.fn() },
}));

const stubs = {
    HcisIcon: { template: '<i />' },
    HcisButton: { props: ['label', 'disabled'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>' },
    HcisDialog: { props: ['modelValue'], template: '<div v-if="modelValue" class="dialog"><slot /><slot name="footer" /></div>' },
};

const alur = {
    tahap_disarankan: 'kurva',
    tahap: [
        { kode: 'pengisian', nama: 'Pengisian', keadaan: 'SELESAI', ringkas: '98 dari 98 penugasan', penghambat: [] },
        { kode: 'verifikasi', nama: 'Hitung & Verifikasi', keadaan: 'SELESAI', ringkas: '', penghambat: [] },
        { kode: 'kualitatif', nama: 'Kesimpulan Kualitatif', keadaan: 'SELESAI', ringkas: '3 dari 3 peserta sudah disimpulkan.', penghambat: [] },
        { kode: 'kurva', nama: 'Kurva Normal', keadaan: 'BERJALAN', ringkas: '', penghambat: ['Belum ada hasil DIHITUNG untuk membentuk kurva.'] },
        { kode: 'balancing', nama: 'Balancing', keadaan: 'DILEWATI', ringkas: '', penghambat: [] },
        { kode: 'terbit', nama: 'Rilis & Terbit', keadaan: 'MENUNGGU', ringkas: '', penghambat: [] },
    ],
};

describe('tahapDariUrl', () => {
    it('membaca ?tahap= dan tetap menerima ?tab= lama', () => {
        expect(tahapDariUrl('?tahap=kurva')).toEqual({ tahap: 'kurva', sub: 'HASIL' });
        expect(tahapDariUrl('?tahap=kualitatif')).toEqual({ tahap: 'kualitatif', sub: 'HASIL' });
        expect(tahapDariUrl('?tahap=verifikasi&sub=keranjang')).toEqual({ tahap: 'verifikasi', sub: 'KERANJANG' });
        expect(tahapDariUrl('?tab=KERANJANG')).toEqual({ tahap: 'verifikasi', sub: 'KERANJANG' });
        expect(tahapDariUrl('?tab=PENGISIAN')).toEqual({ tahap: 'pengisian', sub: 'HASIL' });
        expect(tahapDariUrl('?tahap=ngawur')).toEqual({ tahap: 'ikhtisar', sub: 'HASIL' });
        expect(tahapDariUrl('')).toEqual({ tahap: 'ikhtisar', sub: 'HASIL' });
    });
});

describe('PaPenutupanStepper', () => {
    it('enam tahap dengan keadaan dari server, saran berikutnya, dan memancarkan pilihan', async () => {
        const w = mount(Stepper, { props: { alur, aktif: 'pengisian' }, global: { stubs } });
        expect(w.findAll('.pa-tp-langkah__tombol')).toHaveLength(6);
        // Kesimpulan Kualitatif berada tepat sesudah Hitung & Verifikasi.
        expect(w.findAll('.pa-tp-langkah__tombol')[2].attributes('data-uji')).toBe('tahap-kualitatif');
        expect(w.find('[data-uji="tahap-pengisian"]').text()).toContain('Selesai');
        expect(w.find('[data-uji="tahap-kurva"]').text()).toContain('1 perlu dibereskan');
        expect(w.find('[data-uji="tahap-kurva"]').text()).toContain('Berikutnya');
        expect(w.find('[data-uji="tahap-balancing"]').text()).toContain('Dilewati');
        await w.find('[data-uji="tahap-terbit"]').trigger('click');
        expect(w.emitted('pilih')[0]).toEqual(['terbit']);
    });

    it('tetap bisa dipakai sebelum alurnya termuat', () => {
        const w = mount(Stepper, { props: { alur: null, aktif: 'ikhtisar' }, global: { stubs } });
        expect(w.findAll('.pa-tp-langkah__tombol')).toHaveLength(6);
        expect(w.text()).toContain('Menunggu');
    });
});

describe('PaTahapKepala', () => {
    it('menampilkan penghambat dan aktivitas terakhir', () => {
        const w = mount(Kepala, {
            props: { kode: 'kurva', tahap: { ...alur.tahap[3], aktivitas: { aksi: 'Kurva dikunci', oleh: 'Ridho', pada: '2026-09-30 21:39:47' } } },
            global: { stubs },
        });
        expect(w.text()).toContain('Kurva Normal');
        expect(w.text()).toContain('Belum ada hasil DIHITUNG');
        expect(w.text()).toContain('Kurva dikunci');
        expect(w.text()).toContain('Ridho');
    });

    it('catatan informatif tetap tampil pada tahap SELESAI tanpa daftar penghambat', () => {
        const w = mount(Kepala, {
            props: { kode: 'pengisian', tahap: { ...alur.tahap[0], ringkas: '15 dari 1140 penugasan selesai diisi.', catatan: 'Ditutup dengan 15 dari 1140 penugasan selesai; yang belum selesai tidak ikut dihitung.' } },
            global: { stubs },
        });
        expect(w.find('[data-uji="catatan-tahap"]').text()).toContain('Ditutup dengan 15 dari 1140');
        expect(w.text()).not.toContain('Hal yang Perlu Diselesaikan');
    });
});

describe('PaTahapTerbit', () => {
    const cacah = { hasil_total: 10, hasil_dihitung: 8, hasil_terbit: 0, hasil_tertahan: 1, hasil_belum: 1, dirilis_leader: 0 };
    const baris = (id, status = 'DIHITUNG') => ({
        Id_PA_Participant: id, Nama_Karyawan_Saat_Cycle: `Orang ${id}`, Kode_Karyawan: `K${id}`,
        Status_Hasil: status, Final_Score: 80, Kode_Grade_Final: 'B', Published_Leader_At: null, Published_At: null,
    });
    beforeEach(() => {
        vi.clearAllMocks();
        paApi.daftarPeserta.mockImplementation((_, q) => Promise.resolve(q.hanya_id
            ? { ids: Array.from({ length: 60 }, (_x, i) => i + 1), total: 60 }
            : { peserta: [baris(1), baris(2), baris(3, 'TERTAHAN')], total: 60, opsi: { divisi: [{ value: 7, label: 'Ops' }], level: [] } }));
    });
    const pasang = () => mount(Terbit, { props: { cycleId: 5, cacah, bolehRilis: true, bolehTerbit: true }, global: { stubs } });

    /* Koreksi tahap Rilis & Terbit (7 Okt 2026) */
    it('tombol rilis dan terbit mati beserta sebabnya bila syarat server belum terpenuhi', async () => {
        paApi.ringkasanPenerbitan.mockResolvedValue({ syarat: { boleh_rilis: false, boleh_terbit: false, alasan: 'Kurva grade siklus ini belum dikunci.' } });
        const w = pasang();
        await flushPromises();
        expect(w.find('[data-uji="syarat-publish"]').text()).toContain('belum dikunci');
        expect(w.find('[data-uji="rilis-leader"]').attributes('disabled')).toBeDefined();
        expect(w.find('[data-uji="buka-penerbitan"]').attributes('disabled')).toBeDefined();
    });

    it('terbit satu peserta lewat konfirmasi, dengan pengingat kesimpulan kualitatif', async () => {
        paApi.ringkasanPenerbitan.mockResolvedValue({ syarat: { boleh_rilis: true, boleh_terbit: true, alasan: null } });
        paApi.periksaKesimpulanSebelumTerbit.mockResolvedValue({ belum: 1, contoh: ['Orang 1'] });
        paApi.terbitkanPeserta.mockResolvedValue({});
        const w = pasang();
        await flushPromises();

        await w.find('[data-uji="terbit-satu-1"]').trigger('click');
        await flushPromises();
        // Belum terbit: klik pertama hanya membuka konfirmasi.
        expect(paApi.terbitkanPeserta).not.toHaveBeenCalled();
        expect(paApi.periksaKesimpulanSebelumTerbit).toHaveBeenCalledWith(5, [1]);
        expect(w.find('[data-uji="konfirmasi-terbit-satu"]').text()).toContain('belum punya kesimpulan kualitatif');

        await w.find('[data-uji="ya-terbit-satu"]').trigger('click');
        await flushPromises();
        expect(paApi.terbitkanPeserta).toHaveBeenCalledWith(1);
    });

    it('daftar dimuat dari server: peserta DIIKUTKAN, halaman, dan opsi saringan', async () => {
        pasang();
        await flushPromises();
        expect(paApi.daftarPeserta).toHaveBeenCalledWith(5, expect.objectContaining({ status_peserta: 'DIIKUTKAN', halaman: 1, per_halaman: 25, dengan_opsi: 1 }));
    });

    it('saringan dikirim ke server dan membuang pilihan lama', async () => {
        const w = pasang();
        await flushPromises();
        w.findComponent(HcisTable).vm.$emit('update:selectedKeys', [1]);
        w.findComponent(HcisFilterBar).vm.$emit('chip-click', { value: 'TERTAHAN' });
        w.findComponent(HcisFilterBar).vm.$emit('filter-change', { key: 'rilis_leader', value: 'T' });
        await flushPromises();
        expect(paApi.daftarPeserta).toHaveBeenLastCalledWith(5, expect.objectContaining({
            status_hasil: 'TERTAHAN,BELUM_DIHITUNG', rilis_leader: 'T', halaman: 1,
        }));
        expect(w.findComponent(HcisTable).props('selectedKeys')).toEqual([]);
    });

    it('centang semua memilih SELURUH halaman lewat server, lalu rilis mengirim semua id', async () => {
        paApi.rilisLeader.mockResolvedValue({ dirilis: 60 });
        const w = pasang();
        await flushPromises();
        w.findComponent(HcisTable).vm.$emit('select-all', { checked: true });
        await flushPromises();
        expect(paApi.daftarPeserta).toHaveBeenLastCalledWith(5, expect.objectContaining({ hanya_id: 1, status_peserta: 'DIIKUTKAN' }));
        expect(w.findComponent(HcisTable).props('selectedKeys')).toHaveLength(60);

        await w.find('[data-uji="rilis-terpilih"]').trigger('click');
        await w.find('[data-uji="konfirmasi-rilis"]').trigger('click');
        await flushPromises();
        expect(paApi.rilisLeader).toHaveBeenCalledWith(5, { participant_ids: Array.from({ length: 60 }, (_x, i) => i + 1) });

        w.findComponent(HcisTable).vm.$emit('select-all', { checked: false });
        await flushPromises();
        expect(w.findComponent(HcisTable).props('selectedKeys')).toEqual([]);
    });

    it('baris tertahan tidak bisa dicentang', async () => {
        const w = pasang();
        await flushPromises();
        const boleh = w.findComponent(HcisTable).props('rowSelectable');
        expect(boleh(baris(1))).toBe(true);
        expect(boleh(baris(3, 'TERTAHAN'))).toBe(false);
        expect(boleh({ ...baris(4, 'TERBIT'), Published_Leader_At: '2026-10-01' })).toBe(false);
    });

    /* Ganti ukuran halaman: HcisPagination memancarkan size-change LALU current-change(1).
       Kedua jalur memuat — satu perubahan harus jadi satu request. */
    it('ganti ukuran halaman saat di halaman 3 hanya memicu satu request', async () => {
        const w = pasang();
        await flushPromises();
        const pag = w.findComponent({ name: 'HcisPagination' });
        pag.vm.$emit('update:currentPage', 3);
        pag.vm.$emit('current-change', 3);
        await flushPromises();
        const n = paApi.daftarPeserta.mock.calls.length;

        pag.vm.$emit('update:pageSize', 50);
        pag.vm.$emit('size-change', 50);
        pag.vm.$emit('update:currentPage', 1);
        pag.vm.$emit('current-change', 1);
        await flushPromises();

        const sesudah = paApi.daftarPeserta.mock.calls.slice(n).map((c) => [c[1].halaman, c[1].per_halaman]);
        expect(sesudah).toEqual([[1, 50]]);
    });

    it('respons saringan lama yang tiba belakangan tidak menimpa saringan terbaru', async () => {
        const w = pasang();
        await flushPromises();
        let lepasLama;
        paApi.daftarPeserta.mockImplementation((_, q) => (q.status_hasil === 'DIHITUNG'
            ? new Promise((r) => { lepasLama = () => r({ peserta: [baris(77)], total: 1 }); })
            : Promise.resolve({ peserta: [baris(88)], total: 1 })));

        w.findComponent(HcisFilterBar).vm.$emit('chip-click', { value: 'SIAP' });
        w.findComponent(HcisFilterBar).vm.$emit('chip-click', { value: 'TERBIT' });
        await flushPromises();
        lepasLama();
        await flushPromises();

        const ids = w.findComponent(HcisTable).props('rows').map((r) => r.Id_PA_Participant);
        expect(ids).toEqual([88]);
    });

    it('pilih semua yang masih memuat dibatalkan bila saringan berubah', async () => {
        const w = pasang();
        await flushPromises();
        let lepasIds;
        paApi.daftarPeserta.mockImplementation((_, q) => (q.hanya_id
            ? new Promise((r) => { lepasIds = () => r({ ids: [1, 2, 3], total: 3 }); })
            : Promise.resolve({ peserta: [baris(1)], total: 1 })));

        w.findComponent(HcisTable).vm.$emit('select-all', { checked: true });
        w.findComponent(HcisFilterBar).vm.$emit('chip-click', { value: 'TERTAHAN' });
        await flushPromises();
        lepasIds();
        await flushPromises();

        // ID dari saringan lama tidak boleh ikut terpilih — bisa ikut dirilis/terbit.
        expect(w.findComponent(HcisTable).props('selectedKeys')).toEqual([]);
    });

    it('rilis ke leader minta konfirmasi lalu memanggil API dan memancarkan berubah', async () => {
        paApi.rilisLeader.mockResolvedValue({ dirilis: 8, sudah_dirilis: 0, belum_layak: 2 });
        const w = mount(Terbit, { props: { cycleId: 5, cacah, bolehRilis: true, bolehTerbit: true }, global: { stubs } });
        await w.find('[data-uji="rilis-leader"]').trigger('click');
        expect(paApi.rilisLeader).not.toHaveBeenCalled();
        await w.find('[data-uji="konfirmasi-rilis"]').trigger('click');
        await flushPromises();
        expect(paApi.rilisLeader).toHaveBeenCalledWith(5);
        expect(w.emitted('berubah')).toBeTruthy();
    });

    it('tutup penerbitan mati sampai semua hasil terbit; terbitkan membuka dialog penerbitan', async () => {
        const w = mount(Terbit, { props: { cycleId: 5, cacah, bolehRilis: true, bolehTerbit: true }, global: { stubs } });
        expect(w.find('[data-uji="buka-tutup-penerbitan"]').attributes('disabled')).toBeDefined();
        await w.find('[data-uji="buka-penerbitan"]').trigger('click');
        expect(w.emitted('buka-penerbitan')).toBeTruthy();

        await w.setProps({ cacah: { ...cacah, hasil_terbit: 10, hasil_dihitung: 0, hasil_tertahan: 0, hasil_belum: 0, dirilis_leader: 10 } });
        expect(w.find('[data-uji="buka-tutup-penerbitan"]').attributes('disabled')).toBeUndefined();
        expect(w.find('[data-uji="rilis-leader"]').attributes('disabled')).toBeDefined();
    });

    it('tanpa izin gerbang semua tombol mati', () => {
        const w = mount(Terbit, { props: { cycleId: 5, cacah, bolehRilis: false, bolehTerbit: false }, global: { stubs } });
        expect(w.find('[data-uji="rilis-leader"]').attributes('disabled')).toBeDefined();
        expect(w.find('[data-uji="buka-penerbitan"]').attributes('disabled')).toBeDefined();
    });
});
