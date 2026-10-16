import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import JejakJadwalHariDrawer from './JejakJadwalHariDrawer.vue';

const get = vi.fn();

vi.mock('axios', () => ({
    default: { get: (...a) => get(...a) },
}));

const jejak = (ganti = {}) => ({
    karyawan: { kode: 'B8', nama: 'Budi' },
    tanggal: '2026-09-09',
    hasil: {
        shift_id: 44,
        shift_nama: 'EMI-S1',
        jam_masuk: '07:00',
        jam_keluar: '15:00',
        sumber_shift: 'PERMANEN',
        status_hari: 'WORK',
        kategori: 'MASUK',
        sebab: 'BAWAAN',
        penjelasan: {
            judul: 'Hari kerja biasa',
            rincian: ['Masuk sesuai shift EMI-S1.', 'Shift EMI-S1 (permanen) dipasang 14-09-2026 11:21 oleh Ridho lewat Admin Shift · SH09-26-0013.'],
        },
    },
    lapisan: [
        { kelompok: 'STATUS', kode: 'STATUS_HARI', judul: 'Status hari khusus', ada: false, berlaku: false, keterangan: null, transaksi: null },
        { kelompok: 'SHIFT', kode: 'SHIFT_SEMENTARA', judul: 'Shift sementara', ada: false, berlaku: false, keterangan: null, transaksi: null },
        { kelompok: 'SHIFT', kode: 'SHIFT_PERMANEN', judul: 'Shift permanen', ada: true, berlaku: true, keterangan: 'EMI-S1 sejak 2026-09-09', transaksi: { no: 'SH09-26-0001', label: 'Admin Shift' } },
    ],
    linimasa: [
        {
            waktu: '2026-09-14 11:21',
            aksi: 'NONAKTIF',
            lapisan: 'SHIFT_SEMENTARA',
            judul: 'Shift sementara EMI-S3 dinonaktifkan',
            pelaku: 'Ridho',
            transaksi: { no: 'SH09-26-0002', label: 'Admin Shift' },
            alasan: 'Terhapus otomatis karena shift permanen baru dipasang',
        },
        {
            waktu: '2026-09-15 08:00',
            aksi: 'MENUNGGU',
            lapisan: 'SHIFT',
            judul: 'Pengajuan ganti shift ke EMI-S2',
            pelaku: 'Budi',
            transaksi: { no: 'SH09-26-0009', label: 'Pengajuan karyawan (Self Shift)' },
            alasan: 'Belum berlaku — menunggu persetujuan',
        },
    ],
    peringatan: [],
    ...ganti,
});

const buat = (props = {}) =>
    mount(JejakJadwalHariDrawer, {
        props: { modelValue: true, endpoint: '/leader-shift/jejak-hari', kode: 'B8', tanggal: '2026-09-09', ...props },
        global: { stubs: { teleport: true } },
    });

describe('Laci Riwayat Jadwal', () => {
    beforeEach(() => {
        get.mockReset();
        get.mockResolvedValue({ data: { success: true, result: jejak() } });
    });

    it('memuat jejak dari endpoint pemanggil dengan kode & tanggal', async () => {
        buat();
        await flushPromises();

        expect(get).toHaveBeenCalledWith('/leader-shift/jejak-hari', { params: { kode: 'B8', tanggal: '2026-09-09' } });
    });

    it('pintu pribadi tidak mengirim kode karyawan', async () => {
        buat({ endpoint: '/self-shift/jejak-hari', kode: '' });
        await flushPromises();

        expect(get).toHaveBeenCalledWith('/self-shift/jejak-hari', { params: { tanggal: '2026-09-09' } });
    });

    it('menampilkan hasil akhir, lapisan yang berlaku, dan alasan perubahan', async () => {
        const w = buat();
        await flushPromises();
        const teks = w.text();

        expect(teks).toContain('EMI-S1');
        expect(teks).toContain('07:00–15:00');
        expect(teks).toContain('Masuk kerja');
        expect(teks).toContain('Shift permanen');
        expect(teks).toContain('Berlaku');
        // Lapisan yang tidak ada tidak memenuhi layar.
        expect(teks).not.toContain('Status hari khusus');
        expect(teks).toContain('Terhapus otomatis karena shift permanen baru dipasang');
    });

    it('menandai pengajuan yang belum berlaku sebagai menunggu', async () => {
        const w = buat();
        await flushPromises();

        const item = w.vm.itemLinimasa.find((i) => i.title.includes('EMI-S2'));
        expect(item.sentiment).toBe('warning');
        expect(item.status).toBe('Menunggu persetujuan');
    });

    it('memuat ulang saat tanggal berganti dan tidak memuat saat tertutup', async () => {
        const w = buat({ modelValue: false });
        await flushPromises();
        expect(get).not.toHaveBeenCalled();

        await w.setProps({ modelValue: true });
        await flushPromises();
        await w.setProps({ tanggal: '2026-09-10' });
        await flushPromises();

        expect(get).toHaveBeenCalledTimes(2);
        expect(get).toHaveBeenLastCalledWith('/leader-shift/jejak-hari', { params: { kode: 'B8', tanggal: '2026-09-10' } });
    });

    it('menampilkan pesan server bila akses ditolak', async () => {
        get.mockRejectedValue({ response: { status: 403, data: { message: 'Karyawan ini bukan anggota tim Anda.' } } });
        const w = buat();
        await flushPromises();

        expect(w.text()).toContain('Karyawan ini bukan anggota tim Anda.');
    });

    it('menampilkan peringatan dari server', async () => {
        get.mockResolvedValue({ data: { success: true, result: jejak({ peringatan: ['Hari ini dihitung libur karena shift PERMANEN-nya libur.'] }) } });
        const w = buat();
        await flushPromises();

        expect(w.text()).toContain('Hari ini dihitung libur karena shift PERMANEN-nya libur.');
    });
    it('menjelaskan SEBAB hari libur secara spesifik, termasuk siapa dan kapan', async () => {
        get.mockResolvedValue({ data: { success: true, result: jejak({ hasil: {
            ...jejak().hasil,
            kategori: 'LIBUR',
            sebab: 'SHIFT_DEFAULT_OFF',
            penjelasan: {
                judul: 'Libur bawaan shift',
                rincian: ['Shift permanen EMI-LS2 menetapkan setiap hari Minggu sebagai hari libur.', 'Shift EMI-S1 (permanen) dipasang 14-09-2026 11:21 oleh Ridho lewat Admin Shift · SH09-26-0013.'],
            },
        } }) } });
        const w = buat();
        await flushPromises();
        const teks = w.text();

        expect(teks).toContain('Libur bawaan shift');
        expect(teks).toContain('menetapkan setiap hari Minggu sebagai hari libur');
        expect(teks).toContain('oleh Ridho');
    });

    it('tetap menampilkan label umum bila server belum mengirim penjelasan', async () => {
        const hasil = { ...jejak().hasil };
        delete hasil.penjelasan;
        get.mockResolvedValue({ data: { success: true, result: jejak({ hasil }) } });
        const w = buat();
        await flushPromises();

        expect(w.text()).toContain('Hari kerja biasa menurut shift yang berlaku.');
    });

    it('merender badge Diperbarui dan Diganti dengan benar', async () => {
        const dataUji = jejak({
            linimasa: [
                {
                    waktu: '2026-10-11 21:39',
                    aksi: 'PERBARUI',
                    lapisan: 'SHIFT_SEMENTARA',
                    judul: 'Shift sementara EMI-S1-TEMP diperbarui',
                    pelaku: 'VENGINE ULYAMA',
                    transaksi: { no: 'SH10-26-1750', label: 'Leader Shift' },
                    alasan: 'Menggantikan pengajuan sebelumnya (SH10-26-0104)',
                },
                {
                    waktu: '2026-10-10 10:00',
                    aksi: 'GANTI',
                    lapisan: 'SHIFT_SEMENTARA',
                    judul: 'Shift sementara diganti: OFF-K1 → EMI-S1-TEMP',
                    pelaku: 'VENGINE ULYAMA',
                    transaksi: { no: 'SH10-26-0104', label: 'Leader Shift' },
                    alasan: 'Menggantikan pengajuan sebelumnya',
                },
            ],
        });
        get.mockResolvedValue({ data: { success: true, result: dataUji } });
        const w = buat();
        await flushPromises();

        const perbarui = w.vm.itemLinimasa.find((i) => i.title.includes('diperbarui'));
        expect(perbarui.status).toBe('Diperbarui');
        expect(perbarui.sentiment).toBe('brand');
        expect(perbarui.description).toContain('Menggantikan pengajuan sebelumnya (SH10-26-0104)');

        const ganti = w.vm.itemLinimasa.find((i) => i.title.includes('diganti'));
        expect(ganti.status).toBe('Diganti');
        expect(ganti.sentiment).toBe('brand');
    });

    it('merender badge Sedang Berlaku dan Lampau untuk shift permanen', async () => {
        const dataUji = jejak({
            linimasa: [
                {
                    waktu: '2026-06-04 10:33',
                    aksi: 'LAMPAU',
                    lapisan: 'SHIFT_PERMANEN',
                    judul: 'Shift permanen EMI-S1 berlaku mulai 2026-06-05',
                    pelaku: 'Iqbal',
                    transaksi: { no: 'SH06-26-0003', label: 'Leader Shift' },
                    alasan: 'Pernah berlaku mulai 2026-06-05 (terlewati oleh periode shift berikutnya)',
                },
                {
                    waktu: '2026-02-26 15:24',
                    aksi: 'BERLAKU',
                    lapisan: 'SHIFT_PERMANEN',
                    judul: 'Shift permanen EMI-LS2 berlaku mulai 2026-09-09',
                    pelaku: 'Tidak tercatat',
                    transaksi: { no: 'SH02-26-0054', label: 'Leader Shift' },
                    alasan: 'Shift yang aktif untuk tanggal ini (periode mulai 2026-09-09)',
                },
            ],
        });
        get.mockResolvedValue({ data: { success: true, result: dataUji } });
        const w = buat();
        await flushPromises();

        const berlaku = w.vm.itemLinimasa.find((i) => i.title.includes('EMI-LS2'));
        expect(berlaku.status).toBe('Sedang Berlaku');
        expect(berlaku.sentiment).toBe('success');

        const lampau = w.vm.itemLinimasa.find((i) => i.title.includes('EMI-S1'));
        expect(lampau.status).toBe('Lampau');
        expect(lampau.sentiment).toBe('neutral');
    });
});
