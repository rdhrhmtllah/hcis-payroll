import { readFileSync } from 'node:fs';
import path from 'node:path';
import { flushPromises, shallowMount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import AgendaInspeksiPage from './AgendaInspeksiPage.vue';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
vi.mock('element-plus', () => ({ ElMessage: { error: vi.fn(), success: vi.fn() } }));

const AGENDA = {
    tanggal: '2026-09-21',
    permit: [
        { Jenis: 'PERMIT', Status_Inspeksi: 'TERJADWAL', Pos_Inspeksi: 'Pos Utama', Mulai: '2026-09-21 09:00', Selesai: '2026-09-21 09:30', No_Transaksi: 'PMT0926-0002', Kategori: 'Permit Merah', Nama: 'B', Flag_Approval: null, Boleh_Tidak_Hadir: true, Tautan_Review: '/verifikator-visitor/permit?detail=x' },
        { Jenis: 'PERMIT', Status_Inspeksi: 'SELESAI', Pos_Inspeksi: 'Pos Utama', Mulai: '2026-09-21 08:00', Selesai: '2026-09-21 08:30', No_Transaksi: 'PMT0926-0001', Kategori: 'Permit Hijau', Nama: 'A', Flag_Approval: 'Y', Boleh_Tidak_Hadir: false },
        { Jenis: 'PERMIT', Status_Inspeksi: 'TIDAK_HADIR', Pos_Inspeksi: 'Pos Utama', Mulai: '2026-09-21 10:00', Selesai: '2026-09-21 10:30', No_Transaksi: 'PMT0926-0003', Kategori: 'Permit Hijau', Nama: 'C', Flag_Approval: 'T', Boleh_Tidak_Hadir: false },
    ],
    coklat: [
        { Jenis: 'COKLAT', Kategori: 'COKLAT', Mulai: '2026-09-21 08:15', Selesai: '2026-09-23 17:00', No_Transaksi: 'PMT0926-0004', Nama: 'Tamu', Instansi: 'PT X', Status_Kunjungan: 'SCHEDULED' },
    ],
    slot: [
        { id_pos: 7, pos: 'Pos Utama', lokasi: 'Gerbang depan', mulai: '2026-09-21 08:00', kuota: 2, terisi: 1, sisa: 1 },
        { id_pos: 7, pos: 'Pos Utama', lokasi: 'Gerbang depan', mulai: '2026-09-21 08:30', kuota: 2, terisi: 0, sisa: 2 },
    ],
};

function pasang() {
    axios.get.mockImplementation((url) => {
        if (url === '/agenda-inspeksi-data') return Promise.resolve({ data: { success: true, result: AGENDA } });
        if (url === '/agenda-inspeksi-pola') {
            return Promise.resolve({
                data: {
                    success: true,
                    result: {
                        hari: [
                            { hari: 1, aktif: true, shift: [{ id: 1, aktif: true }, { id: 3, aktif: false }] },
                            { hari: 2, aktif: false, shift: [{ id: 1, aktif: true }, { id: 3, aktif: true }] },
                            { hari: 3, aktif: true, shift: [{ id: 1, aktif: true }, { id: 3, aktif: true }] },
                        ],
                        shift: [
                            { id: 1, nama: 'Shift 1', jam_mulai: '07:00', jam_selesai: '15:00', urutan: 1, aktif: true },
                            { id: 3, nama: 'Shift 3', jam_mulai: '23:00', jam_selesai: '07:00', urutan: 3, aktif: false },
                        ],
                        pos: [
                            { id: 7, nama: 'Pos Utama', lokasi: 'Gerbang depan', urutan: 1, aktif: true },
                            { id: 8, nama: 'Pos Lama', lokasi: null, urutan: 2, aktif: false },
                        ],
                        pola: [{ id: 7, id_pos: 7, id_shift: 1, hari: 1, jam_mulai: '08:00', jam_selesai: '11:00', durasi: 30, kuota: 2 }],
                        pengecualian: [],
                    },
                },
            });
        }
        return Promise.reject(new Error('URL not mocked: ' + url));
    });
}

describe('AgendaInspeksiPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        pasang();
    });

    it('menggabungkan permit karyawan dan tamu B2B; yang belum diinspeksi di atas, lalu urut jam', async () => {
        const wrapper = shallowMount(AgendaInspeksiPage, { props: { serverDate: '2026-09-21' } });
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/agenda-inspeksi-data', { params: { tanggal: '2026-09-21' } });
        // Belum diinspeksi: 0004 (tamu tanpa hasil, 08:15), 0002 (terjadwal, 09:00).
        // Sudah: 0001 (selesai, 08:00), 0003 (tidak hadir, 10:00).
        expect(wrapper.vm.agenda.map((x) => x.No_Transaksi)).toEqual(['PMT0926-0004', 'PMT0926-0002', 'PMT0926-0001', 'PMT0926-0003']);
        expect(wrapper.vm.kapasitasPos).toEqual([{ id: 7, pos: 'Pos Utama', lokasi: 'Gerbang depan', kuota: 4, terisi: 1, slot: 2, tamu: 0 }]);
    });

    it('menghitung kartu ringkasan dari status booking', async () => {
        const wrapper = shallowMount(AgendaInspeksiPage, { props: { serverDate: '2026-09-21' } });
        await flushPromises();

        expect(wrapper.vm.statCards.map((s) => s.value)).toEqual([1, 1, 1, 1]);
    });

    it('memberi warna menurut kategori dan menandai permit gugur', async () => {
        const wrapper = shallowMount(AgendaInspeksiPage, { props: { serverDate: '2026-09-21' } });
        await flushPromises();
        const [coklat, merah, hijau, gugur] = wrapper.vm.agenda;

        expect(wrapper.vm.warnaKategori(hijau)).toBe('success');
        expect(wrapper.vm.warnaKategori(merah)).toBe('danger');
        expect(wrapper.vm.warnaKategori(coklat)).toBe('warning');
        expect(wrapper.vm.statusItem(gugur).label).toBe('Tidak hadir · permit gugur');
        expect(wrapper.vm.waktuTamu(coklat.Selesai)).toBe('23/09 17:00');
        expect(wrapper.vm.waktuTamu(`${wrapper.vm.tanggal} 08:00`)).toBe('08:00');
    });

    it('menggeser tanggal lalu memuat agenda tanggal itu', async () => {
        const wrapper = shallowMount(AgendaInspeksiPage, { props: { serverDate: '2026-09-30' } });
        await flushPromises();

        wrapper.vm.geserHari(1);
        await flushPromises();

        expect(wrapper.vm.tanggal).toBe('2026-10-01');
        expect(axios.get).toHaveBeenLastCalledWith('/agenda-inspeksi-data', { params: { tanggal: '2026-10-01' } });
    });

    it('mencatat tidak hadir lewat endpoint SHE lalu memuat ulang agenda', async () => {
        axios.post.mockResolvedValueOnce({ data: { success: true, message: 'ok', result: { gugur: false, jumlah: 1 } } });
        const wrapper = shallowMount(AgendaInspeksiPage, { props: { serverDate: '2026-09-21' } });
        await flushPromises();

        wrapper.vm.konfirmasiTidakHadir(AGENDA.permit[0]);
        await wrapper.vm.simpanTidakHadir();
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/agenda-inspeksi/PMT0926-0002/tidak-hadir');
        expect(wrapper.vm.dialogTidakHadir).toBe(false);
        expect(axios.get.mock.calls.filter(([url]) => url === '/agenda-inspeksi-data')).toHaveLength(2);
    });

    it('memuat pola hanya saat tab pengaturan dibuka, dan mengirimnya tanpa kunci lokal', async () => {
        axios.post.mockResolvedValueOnce({ data: { success: true } });
        const wrapper = shallowMount(AgendaInspeksiPage, { props: { serverDate: '2026-09-21' } });
        await flushPromises();
        expect(axios.get).not.toHaveBeenCalledWith('/agenda-inspeksi-pola');

        wrapper.vm.activeTab = 'pengaturan';
        await flushPromises();
        expect(wrapper.vm.pola).toHaveLength(1);

        wrapper.vm.tambahJadwal(2, 1);
        expect(wrapper.vm.pola[1]).toMatchObject({ id: null, id_pos: 7, id_shift: 1, hari: 2, durasi: 30, kuota: 2 });

        await wrapper.vm.simpanPola();
        const [url, isi] = axios.post.mock.calls[0];
        expect(url).toBe('/agenda-inspeksi-pola');
        expect(isi.pola).toHaveLength(2);
        expect(isi.pola.every((p) => !('_kunci' in p))).toBe(true);
    });

    it('pengecualian TUTUP tidak mengirim jam, durasi, dan kuota', async () => {
        axios.post.mockResolvedValueOnce({ data: { success: true } });
        const wrapper = shallowMount(AgendaInspeksiPage, { props: { serverDate: '2026-09-21' } });
        await flushPromises();

        wrapper.vm.pengecualianBaru = { ...wrapper.vm.pengecualianKosong('2026-09-25'), keterangan: 'Libur' };
        await wrapper.vm.simpanPengecualian();

        expect(axios.post).toHaveBeenCalledWith('/agenda-inspeksi-pengecualian', {
            tanggal: '2026-09-25',
            jenis: 'TUTUP',
            id_pos: null,
            id_shift: null,
            keterangan: 'Libur',
        });
    });

    it('pilihan pos hanya yang aktif; pos baru dikirim ke endpoint master', async () => {
        axios.post.mockResolvedValue({ data: { success: true, result: { booking_terdampak: 0 } } });
        const wrapper = shallowMount(AgendaInspeksiPage, { props: { serverDate: '2026-09-21' } });
        await flushPromises();
        wrapper.vm.activeTab = 'pengaturan';
        await flushPromises();

        expect(wrapper.vm.daftarPos.map((p) => p.id)).toEqual([7]);
        expect(wrapper.vm.namaPos(8)).toBe('Pos Lama');

        wrapper.vm.posForm = { id: null, nama: 'Pos Belakang', lokasi: 'Gudang B', urutan: 3 };
        await wrapper.vm.simpanPos();
        expect(axios.post).toHaveBeenCalledWith('/agenda-inspeksi-pos', { id: null, nama: 'Pos Belakang', lokasi: 'Gudang B', urutan: 3 });
        expect(wrapper.vm.posForm).toEqual({ id: null, nama: '', lokasi: '', urutan: 0 });

        await wrapper.vm.ubahStatusPos({ id: 7, aktif: true });
        expect(axios.post).toHaveBeenCalledWith('/agenda-inspeksi-pos/7/status', { aktif: 0 });
    });

    it('shift: hanya yang aktif bisa dipilih, jam bisa diubah, dan nonaktif dilaporkan', async () => {
        axios.post.mockResolvedValue({ data: { success: true, result: { booking_terdampak: 2 } } });
        const wrapper = shallowMount(AgendaInspeksiPage, { props: { serverDate: '2026-09-21' } });
        await flushPromises();
        wrapper.vm.activeTab = 'pengaturan';
        await flushPromises();

        expect(wrapper.vm.shiftAktif.map((s) => s.id)).toEqual([1]);
        expect(wrapper.vm.jumlahJadwalShift(1)).toBe(1);
        expect(wrapper.vm.shiftLewatTengahMalam(wrapper.vm.shiftList[1])).toBe(true);

        wrapper.vm.shiftForm = { id: 1, nama: 'Shift 1', jam_mulai: '06:00', jam_selesai: '14:00', urutan: 1 };
        await wrapper.vm.simpanShift();
        expect(axios.post).toHaveBeenCalledWith('/agenda-inspeksi-shift', { id: 1, nama: 'Shift 1', jam_mulai: '06:00', jam_selesai: '14:00', urutan: 1 });

        await wrapper.vm.ubahStatusShift({ id: 1, aktif: true });
        expect(axios.post).toHaveBeenCalledWith('/agenda-inspeksi-shift/1/status', { aktif: 0 });
    });

    it('menandai jadwal yang jamnya di luar jam kerja shift, tanpa menolaknya', async () => {
        const wrapper = shallowMount(AgendaInspeksiPage, { props: { serverDate: '2026-09-21' } });
        await flushPromises();
        wrapper.vm.activeTab = 'pengaturan';
        await flushPromises();

        // Shift 1 kerja 07:00-15:00; inspeksi 06:00-07:00 sengaja di luar jam itu.
        expect(wrapper.vm.diLuarJamShift({ id_shift: 1, jam_mulai: '06:00', jam_selesai: '07:00' })).toBe(true);
        expect(wrapper.vm.diLuarJamShift({ id_shift: 1, jam_mulai: '10:00', jam_selesai: '11:00' })).toBe(false);
        // Shift malam: 23:30-00:30 masih di dalam jam kerjanya.
        expect(wrapper.vm.diLuarJamShift({ id_shift: 3, jam_mulai: '23:30', jam_selesai: '00:30' })).toBe(false);
    });

    it('menyusun hari > shift > jadwal, dan sakelarnya per hari maupun per shift', async () => {
        axios.post.mockResolvedValue({ data: { success: true, result: { booking_terdampak: 0 } } });
        const wrapper = shallowMount(AgendaInspeksiPage, { props: { serverDate: '2026-09-21' } });
        await flushPromises();
        wrapper.vm.activeTab = 'pengaturan';
        await flushPromises();

        const [senin, selasa] = wrapper.vm.hariList;
        expect(senin.aktif).toBe(true);
        expect(selasa.aktif).toBe(false);
        // Shift 3 mati khusus hari Senin, tetapi hidup di hari Selasa.
        expect(wrapper.vm.aktifShiftHari(senin, 3)).toBe(false);
        expect(wrapper.vm.aktifShiftHari(selasa, 3)).toBe(true);
        // Hari yang tidak punya catatan sakelar dianggap aktif.
        expect(wrapper.vm.aktifShiftHari(wrapper.vm.hariList[2], 99)).toBe(true);

        expect(wrapper.vm.jadwalHariShift(1, 1).map((p) => p.id)).toEqual([7]);
        expect(wrapper.vm.jadwalHariShift(1, 3)).toEqual([]);

        await wrapper.vm.ubahStatusHari(2, null, true);
        expect(axios.post).toHaveBeenCalledWith('/agenda-inspeksi-hari', { hari: 2, id_shift: null, aktif: 1 });

        await wrapper.vm.ubahStatusHari(1, 1, false);
        expect(axios.post).toHaveBeenCalledWith('/agenda-inspeksi-hari', { hari: 1, id_shift: 1, aktif: 0 });
    });
});

describe('Agenda inspeksi — pindah jadwal oleh petugas', () => {
    it('tombol pindah hanya muncul bila server mengizinkan, dan memakai pemilih slot pemohon', () => {
        const source = readFileSync(path.resolve(process.cwd(), 'resources/js/Pages/VISITOR/Permit/Inspection/AgendaInspeksiPage.vue'), 'utf8');

        expect(source).toContain('v-if="item.Boleh_Pindah"');
        expect(source).toContain('<SlotInspeksiPicker');
        expect(source).toContain("'/agenda-inspeksi-slot'");
        expect(source).toContain(':disabled="!slotPindah || !alasanPindah.trim() || menyimpanPindah"');
    });
});

describe('Agenda inspeksi — kedatangan tamu B2B di kartu okupansi', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        pasang();
    });

    it('kedatangan tamu dihitung per pos, terpisah dari kuota', async () => {
        const wrapper = shallowMount(AgendaInspeksiPage, { props: { serverDate: '2026-09-21' } });
        await flushPromises();

        wrapper.vm.coklat = [
            { Jenis: 'COKLAT', No_Transaksi: 'A', Id_Pos_Inspeksi: 7, Pos_Inspeksi: 'Pos Utama', Mulai: '2026-09-21 08:00' },
            { Jenis: 'COKLAT', No_Transaksi: 'B', Id_Pos_Inspeksi: 9, Pos_Inspeksi: 'Pos Gerbang Tamu', Mulai: '2026-09-21 09:00' },
        ];

        const pos7 = wrapper.vm.kapasitasPos.find((p) => p.id === 7);
        expect(pos7).toMatchObject({ kuota: 4, terisi: 1, tamu: 1 });

        // Pos tamu tanpa slot hari itu tetap tampil, supaya bebannya terlihat.
        expect(wrapper.vm.kapasitasPos.find((p) => p.id === 9)).toMatchObject({ pos: 'Pos Gerbang Tamu', kuota: 0, slot: 0, tamu: 1 });
    });
});

describe('Agenda inspeksi — panel lewat jadwal & belum dicatat', () => {
    const sumber = () => readFileSync(path.resolve(process.cwd(), 'resources/js/Pages/VISITOR/Permit/Inspection/AgendaInspeksiPage.vue'), 'utf8');

    it('panel lintas tanggal dirender, bukan hanya dimuat', () => {
        expect(sumber()).toContain('v-for="item in belumDicatat"');
    });

    it('baris tamu B2B ditutup lewat pencatatan kedatangan, baris permit lewat tidak-hadir', () => {
        const s = sumber();
        expect(s).toContain("catatKedatangan(item, 'TIDAK_HADIR')");
        expect(s).toContain('konfirmasiTidakHadir(item)');
    });
});
