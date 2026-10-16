import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import KonversiLemburAdmin from './KonversiLemburAdmin.vue';

const get = vi.fn();
const post = vi.fn();

vi.mock('axios', () => ({
    default: {
        get: (...a) => get(...a),
        post: (...a) => post(...a),
    },
}));

const baris = (ganti = {}) => ({
    no_transaksi: 'KL26-08-0003',
    kode_karyawan: 'K001',
    nama: 'Budi',
    divisi: 'Produksi',
    tanggal_lembur: '2026-08-10',
    jam_masuk: '08:00',
    jam_keluar: '17:00',
    durasi_jam: 8,
    hari_dikonversi: 1,
    jenis_konversi: 'TO_CUTI',
    tanggal_off: null,
    status: 'DITERAPKAN',
    catatan_karyawan: null,
    catatan_keputusan: null,
    diajukan_pada: '2026-08-11 09:00:00',
    boleh_batal: true,
    ...ganti,
});

const stats = { total: 5, menunggu: 1, disetujui: 0, diterapkan: 3, ditolak: 1, dibatalkan: 0, jam_diterapkan: 24 };

const balasDaftar = (items = [baris()]) => ({
    data: { success: true, result: { list: { items, total: items.length, page: 1, limit: 15 }, stats } },
});

const balasDetail = (ganti = {}) => ({
    data: {
        success: true,
        result: {
            ...baris(),
            approval_steps: [{ step: 'Step 1', actor: 'Atasan', status: 'approved', approved_at: '11 Agu 2026', note: null }],
            dampak_saldo_cuti: { already: false, hutang_dipulihkan: 0, jatah_ditarik: 1, hutang_baru: 0 },
            ...ganti,
        },
    },
});

const opsiSaringan = {
    divisi: ['PRODUKSI', 'GUDANG'],
    departemen: [
        { nama: 'MESIN', divisi: 'PRODUKSI' },
        { nama: 'PENERIMAAN', divisi: 'GUDANG' },
    ],
    level: ['STAFF'],
    jabatan: ['OPERATOR'],
    lokasi: ['EMI', 'JKT'],
};

const buat = () => mount(KonversiLemburAdmin, { props: { opsiSaringan }, global: { stubs: { teleport: true } } });

const opsi = (w, kunci) => w.vm.saringanBar.find((f) => f.key === kunci).options.map((o) => o.value);

describe('Halaman admin Konversi Lembur', () => {
    beforeEach(() => {
        get.mockReset();
        post.mockReset();
        get.mockImplementation((url) => Promise.resolve(url.includes('/detail/') ? balasDetail() : balasDaftar()));
    });

    it('memuat daftar saat dibuka dan menampilkan baris pengajuan', async () => {
        const w = buat();
        await flushPromises();

        expect(get).toHaveBeenCalledWith('/admin/konversi-lembur/data', expect.objectContaining({ params: expect.objectContaining({ page: 1, status: '' }) }));
        expect(w.text()).toContain('Budi');
        expect(w.text()).toContain('Saldo Cuti');
        expect(w.text()).toContain('Diterapkan');
    });

    it('menyaring status saat kartu ringkasan diklik, dan klik kedua melepasnya', async () => {
        const w = buat();
        await flushPromises();

        w.vm.pilihStatus('MENUNGGU');
        await flushPromises();
        expect(get).toHaveBeenLastCalledWith('/admin/konversi-lembur/data', expect.objectContaining({ params: expect.objectContaining({ status: 'MENUNGGU', page: 1 }) }));

        w.vm.pilihStatus('MENUNGGU');
        await flushPromises();
        expect(get).toHaveBeenLastCalledWith('/admin/konversi-lembur/data', expect.objectContaining({ params: expect.objectContaining({ status: '' }) }));
    });

    it('memecah rentang tanggal lembur menjadi tanggal_dari dan tanggal_sampai', async () => {
        const w = buat();
        await flushPromises();

        w.vm.gantiFilter({ key: 'tanggal', value: ['2026-08-01', '2026-08-31'] });
        await flushPromises();

        expect(get).toHaveBeenLastCalledWith('/admin/konversi-lembur/data', expect.objectContaining({
            params: expect.objectContaining({ tanggal_dari: '2026-08-01', tanggal_sampai: '2026-08-31' }),
        }));
    });

    it('menyediakan saringan divisi, departemen, level, jabatan, dan lokasi kerja dari prop', async () => {
        const w = buat();
        await flushPromises();

        expect(w.vm.saringanBar.map((f) => f.key)).toEqual(expect.arrayContaining(['divisi', 'departemen', 'level', 'jabatan', 'lokasi']));
        expect(opsi(w, 'lokasi')).toEqual(['EMI', 'JKT']);
        expect(opsi(w, 'departemen')).toEqual(['MESIN', 'PENERIMAAN']);
    });

    it('menyempitkan departemen ke divisi terpilih dan membuang departemen yang tak lagi cocok', async () => {
        const w = buat();
        await flushPromises();

        w.vm.gantiFilter({ key: 'departemen', value: ['MESIN', 'PENERIMAAN'] });
        w.vm.gantiFilter({ key: 'divisi', value: ['GUDANG'] });
        await flushPromises();

        expect(opsi(w, 'departemen')).toEqual(['PENERIMAAN']);
        expect(get).toHaveBeenLastCalledWith('/admin/konversi-lembur/data', expect.objectContaining({
            params: expect.objectContaining({ divisi: ['GUDANG'], departemen: ['PENERIMAAN'], page: 1 }),
        }));
    });

    it('mengosongkan saringan organisasi saat reset', async () => {
        const w = buat();
        await flushPromises();
        w.vm.gantiFilter({ key: 'jabatan', value: ['OPERATOR'] });

        w.vm.resetFilter();
        await flushPromises();

        expect(get).toHaveBeenLastCalledWith('/admin/konversi-lembur/data', expect.objectContaining({
            params: expect.objectContaining({ jabatan: [], lokasi: [], divisi: [] }),
        }));
    });

    it('dialog batal menyebut dampak saldo cuti dari pratinjau TERKINI sebelum dikonfirmasi', async () => {
        const w = buat();
        await flushPromises();

        await w.vm.mintaBatal(baris());
        await flushPromises();

        expect(get).toHaveBeenLastCalledWith('/admin/konversi-lembur/detail/KL26-08-0003');
        expect(w.vm.dialogBatal).toBe(true);
        expect(w.vm.ringkasSaldo).toContain('1 hari jatah Cuti Tahunan ditarik kembali');
        expect(w.vm.dampakBatal.join(' ')).toContain('Saldo cuti yang dikreditkan dari lembur ini dikoreksi');
        // Tak ada template WA "dibatalkan": layar tak boleh menjanjikan notifikasi.
        expect(w.vm.dampakBatal.join(' ')).not.toMatch(/diberi tahu lewat WhatsApp/);
    });

    it('menolak konfirmasi tanpa alasan yang cukup dan tidak mengirim apa pun', async () => {
        const w = buat();
        await flushPromises();
        await w.vm.mintaBatal(baris());

        w.vm.alasan = 'ok';
        await w.vm.konfirmasiBatal();

        expect(post).not.toHaveBeenCalled();
        expect(w.vm.galatAlasan).toContain('minimal 5');
    });

    it('mengirim alasan lalu menutup dialog dan memuat ulang daftar', async () => {
        post.mockResolvedValue({ data: { success: true, message: 'Pengajuan dibatalkan.' } });
        const w = buat();
        await flushPromises();
        await w.vm.mintaBatal(baris());
        const panggilanSebelum = get.mock.calls.length;

        w.vm.alasan = '  Salah pilih jenis konversi  ';
        await w.vm.konfirmasiBatal();
        await flushPromises();

        expect(post).toHaveBeenCalledWith('/admin/konversi-lembur/batal/KL26-08-0003', { alasan: 'Salah pilih jenis konversi' });
        expect(w.vm.dialogBatal).toBe(false);
        expect(get.mock.calls.length).toBeGreaterThan(panggilanSebelum);
    });

    it('tidak membuka dialog bila pengajuan ternyata sudah tidak bisa dibatalkan', async () => {
        get.mockImplementation((url) => Promise.resolve(url.includes('/detail/') ? balasDetail({ boleh_batal: false, status: 'DIBATALKAN' }) : balasDaftar()));
        const w = buat();
        await flushPromises();

        await w.vm.mintaBatal(baris());

        expect(w.vm.dialogBatal).toBe(false);
    });
});
