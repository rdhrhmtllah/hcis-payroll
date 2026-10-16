import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PengajuanShiftAdmin from './PengajuanShiftAdmin.vue';

const get = vi.fn();
const post = vi.fn();

vi.mock('axios', () => ({
    default: {
        get: (...a) => get(...a),
        post: (...a) => post(...a),
    },
}));

const baris = (ganti = {}) => ({
    kunci: 'SH10-26-0008#910',
    no_transaksi: 'SH10-26-0008',
    id_detail: 910,
    jenis: 'GANTI_SHIFT',
    sumber: 'KARYAWAN',
    pengaju: 'Tari',
    status_hari: null,
    kode_karyawan: 'Y62',
    nama: 'Tari',
    divisi: 'PRODUKSI',
    departemen: 'MESIN',
    level: 'STAFF',
    jabatan: 'OPERATOR',
    lokasi: 'EMI',
    target: 'SEMENTARA',
    tanggal_awal: '2026-10-20',
    tanggal_akhir: '2026-10-22',
    tanggal_bekerja: null,
    tanggal_off: null,
    jumlah_tanggal: 3,
    jumlah_diterapkan: 1,
    jumlah_tertunda: 2,
    status: 'SEBAGIAN',
    catatan_batal_admin: null,
    catatan_tolak: null,
    diajukan_pada: '2026-10-12 11:07',
    boleh_batal: true,
    ...ganti,
});

const stats = { total: 4, menunggu: 1, sebagian: 1, diterapkan: 1, ditolak: 0, dibatalkan: 1, tukar_hari: 1 };

const balasDaftar = (items = [baris()]) => ({
    data: { success: true, result: { list: { items, total: items.length, page: 1, limit: 15 }, stats } },
});

const balasDetail = (ganti = {}) => ({
    data: {
        success: true,
        result: {
            ...baris(),
            alasan: 'tukar jadwal',
            tanggal: [
                { tanggal: '2026-10-20', shift_lama: 'S2', shift_baru: 'S1', status: 'DITERAPKAN', catatan: null },
                { tanggal: '2026-10-21', shift_lama: 'S2', shift_baru: 'S1', status: 'MENUNGGU', catatan: null },
                { tanggal: '2026-10-22', shift_lama: 'S2', shift_baru: 'S1', status: 'MENUNGGU', catatan: null },
            ],
            approval_steps: [{ urutan: 1, nama: 'Arya', status: 'pending', waktu: null, catatan: null }],
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
    lokasi: ['EMI'],
};

const buat = () => mount(PengajuanShiftAdmin, { props: { opsiSaringan }, global: { stubs: { teleport: true } } });

const opsi = (w, kunci) => w.vm.saringanBar.find((f) => f.key === kunci).options.map((o) => o.value);

describe('Halaman admin Pengajuan Shift', () => {
    beforeEach(() => {
        get.mockReset();
        post.mockReset();
        get.mockImplementation((url) => Promise.resolve(url.includes('/detail/') ? balasDetail() : balasDaftar()));
    });

    it('memuat daftar saat dibuka dan menampilkan baris beserta progres penerapan', async () => {
        const w = buat();
        await flushPromises();

        expect(get).toHaveBeenCalledWith('/admin/pengajuan-shift/data', expect.objectContaining({ params: expect.objectContaining({ page: 1, status: '' }) }));
        expect(w.text()).toContain('Tari');
        expect(w.text()).toContain('Ganti Shift');
        expect(w.text()).toContain('Sebagian Diterapkan');
        expect(w.text()).toContain('1/3 diterapkan');
    });

    it('menampilkan tukar hari sebagai pasangan tanggal masuk dan libur', async () => {
        get.mockImplementation(() => Promise.resolve(balasDaftar([baris({
            kunci: 'TH10-26-0001#34', no_transaksi: 'TH10-26-0001', id_detail: 34, jenis: 'TUKAR_HARI', target: null,
            tanggal_bekerja: '2026-10-18', tanggal_off: '2026-10-19', jumlah_tanggal: 1, jumlah_diterapkan: 0, status: 'MENUNGGU',
        })])));
        const w = buat();
        await flushPromises();

        expect(w.text()).toContain('Tukar Hari');
        expect(w.vm.ringkasTanggal(w.vm.baris[0])).toMatch(/Masuk .*18.* · Libur .*19/);
    });

    it('menyaring status lewat kartu ringkasan, klik kedua melepasnya', async () => {
        const w = buat();
        await flushPromises();

        w.vm.pilihStatus('SEBAGIAN');
        await flushPromises();
        expect(get).toHaveBeenLastCalledWith('/admin/pengajuan-shift/data', expect.objectContaining({ params: expect.objectContaining({ status: 'SEBAGIAN' }) }));

        w.vm.pilihStatus('SEBAGIAN');
        await flushPromises();
        expect(get).toHaveBeenLastCalledWith('/admin/pengajuan-shift/data', expect.objectContaining({ params: expect.objectContaining({ status: '' }) }));
    });

    it('menyempitkan departemen ke divisi terpilih dan mengirim saringan organisasi sebagai daftar', async () => {
        const w = buat();
        await flushPromises();

        w.vm.gantiFilter({ key: 'departemen', value: ['MESIN', 'PENERIMAAN'] });
        w.vm.gantiFilter({ key: 'divisi', value: ['GUDANG'] });
        w.vm.gantiFilter({ key: 'tanggal', value: ['2026-10-01', '2026-10-31'] });
        await flushPromises();

        expect(opsi(w, 'departemen')).toEqual(['PENERIMAAN']);
        expect(get).toHaveBeenLastCalledWith('/admin/pengajuan-shift/data', expect.objectContaining({
            params: expect.objectContaining({ divisi: ['GUDANG'], departemen: ['PENERIMAAN'], tanggal_dari: '2026-10-01', tanggal_sampai: '2026-10-31' }),
        }));
    });

    it('dialog batal menyebut bahwa tanggal yang sudah diterapkan tetap berlaku', async () => {
        const w = buat();
        await flushPromises();

        await w.vm.mintaBatal(baris());

        expect(get).toHaveBeenLastCalledWith('/admin/pengajuan-shift/detail/SH10-26-0008/910');
        expect(w.vm.dialogBatal).toBe(true);
        const dampak = w.vm.dampakBatal.join(' ');
        expect(dampak).toContain('2 tanggal yang belum diterapkan dibatalkan');
        expect(dampak).toContain('1 tanggal yang sudah diterapkan tetap berlaku');
        expect(dampak).not.toMatch(/WhatsApp ke karyawan/);
    });

    it('menolak alasan yang terlalu pendek tanpa mengirim apa pun', async () => {
        const w = buat();
        await flushPromises();
        await w.vm.mintaBatal(baris());

        w.vm.alasan = 'ok';
        await w.vm.konfirmasiBatal();

        expect(post).not.toHaveBeenCalled();
        expect(w.vm.galatAlasan).toContain('minimal 5');
    });

    it('mengirim alasan lalu menutup dialog dan memuat ulang daftar', async () => {
        post.mockResolvedValue({ data: { success: true, message: '2 tanggal dibatalkan.' } });
        const w = buat();
        await flushPromises();
        await w.vm.mintaBatal(baris());
        const sebelum = get.mock.calls.length;

        w.vm.alasan = '  Shift diganti lewat leader  ';
        await w.vm.konfirmasiBatal();
        await flushPromises();

        expect(post).toHaveBeenCalledWith('/admin/pengajuan-shift/batal/SH10-26-0008/910', { alasan: 'Shift diganti lewat leader' });
        expect(w.vm.dialogBatal).toBe(false);
        expect(get.mock.calls.length).toBeGreaterThan(sebelum);
    });

    it('tidak membuka dialog bila pengajuan ternyata sudah tidak bisa dibatalkan', async () => {
        get.mockImplementation((url) => Promise.resolve(url.includes('/detail/') ? balasDetail({ boleh_batal: false, status: 'DITERAPKAN' }) : balasDaftar()));
        const w = buat();
        await flushPromises();

        await w.vm.mintaBatal(baris());

        expect(w.vm.dialogBatal).toBe(false);
    });
    it('menampilkan siapa yang mengajukan dan menyaring berdasarkan sumber', async () => {
        get.mockImplementation(() => Promise.resolve(balasDaftar([baris({ sumber: 'LEADER', pengaju: 'Aji' })])));
        const w = buat();
        await flushPromises();

        expect(w.text()).toContain('Leader · Aji');

        w.vm.gantiFilter({ key: 'sumber', value: 'LEADER' });
        await flushPromises();
        expect(get).toHaveBeenLastCalledWith('/admin/pengajuan-shift/data', expect.objectContaining({ params: expect.objectContaining({ sumber: 'LEADER' }) }));
    });

    it('meringkas status hari beserta jenis harinya', async () => {
        const w = buat();
        await flushPromises();

        const ringkas = w.vm.ringkasTanggal(baris({ jenis: 'STATUS_HARI', status_hari: 'OFF', tanggal_awal: '2026-10-24', tanggal_akhir: '2026-10-24' }));
        expect(ringkas).toMatch(/24.*· Libur/);
    });

    it('dialog batal untuk pengajuan leader menegaskan anggota lain tidak terpengaruh', async () => {
        get.mockImplementation((url) => Promise.resolve(url.includes('/detail/') ? balasDetail({ sumber: 'LEADER', pengaju: 'Aji' }) : balasDaftar()));
        const w = buat();
        await flushPromises();

        await w.vm.mintaBatal(baris({ sumber: 'LEADER' }));

        expect(w.vm.dampakBatal.join(' ')).toContain('Anggota lain dalam pengajuan leader yang sama tidak terpengaruh');
        expect(w.vm.dampakBatal.join(' ')).toContain('riwayat leader');
    });
    it('membuka jejak jadwal untuk karyawan & tanggal di laci detail', async () => {
        get.mockImplementation((url) => Promise.resolve(
            url.includes('/jejak-hari') ? { data: { success: false, message: 'tidak dipakai di uji ini' } }
                : url.includes('/detail/') ? balasDetail() : balasDaftar(),
        ));
        const w = buat();
        await flushPromises();
        await w.vm.bukaDetail(baris());
        await flushPromises();

        w.vm.bukaJejak('2026-10-21');

        expect(w.vm.jejakHari).toEqual({ buka: true, kode: 'Y62', tanggal: '2026-10-21' });
    });
});
