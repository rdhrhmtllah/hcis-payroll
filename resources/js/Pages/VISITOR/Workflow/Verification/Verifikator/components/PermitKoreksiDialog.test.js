import { flushPromises, shallowMount } from '@vue/test-utils';
import axios from 'axios';
import { afterEach, describe, expect, it, vi } from 'vitest';
import PermitKoreksiDialog from './PermitKoreksiDialog.vue';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const MASTER = {
    plat_codes: ['BG', 'B'],
    vehicle_types: [{ Id_Vehicle_Type: 2, Nama_Tipe_Kendaraan: 'Sepeda Motor' }, { Id_Vehicle_Type: 3, Nama_Tipe_Kendaraan: 'Mobil Penumpang' }],
    models: [{ Id_Vehicle_Model: 3, Id_Master_Tipe: 3, Nama_Model_Kendaraan: 'Toyota Avanza' }, { Id_Vehicle_Model: 1, Id_Master_Tipe: 2, Nama_Model_Kendaraan: 'Honda Beat' }],
    colors: [{ Id_Master_Color: 1, Nama_Warna: 'Hitam' }, { Id_Master_Color: 2, Nama_Warna: 'Putih' }],
    kategoris: [
        { Id_Kategori_Permit: 1, Nama_Kategori: 'HIJAU', Deskripsi_Akses: 'Hanya area parkir' },
        { Id_Kategori_Permit: 2, Nama_Kategori: 'MERAH', Deskripsi_Akses: 'Akses penuh sampai Area Boiler' },
    ],
};

const PERMIT = {
    No_Transaksi: 'PMT0926-0001',
    Id_Master_Kategori_Permit: '2',
    Nama_Kategori: 'MERAH',
    No_Plat_Kendaraan: 'BG 1234 AA',
    Id_Master_Model: '3',
    Id_Master_Warna: '1',
    Tahun_Kendaraan: '2020',
    Keterangan_Pengajuan: 'Parkir harian',
};

async function pasang() {
    axios.get.mockImplementation((url) =>
        url === '/verifikator-permit-koreksi-master'
            ? Promise.resolve({ data: { success: true, result: MASTER } })
            : Promise.resolve({ data: { success: true, result: { dipertahankan: 2, dibuang: [{ teks: 'Rem mobil', terjawab: true }], ditambah: [] } } }),
    );
    // Dialog asli di-teleport ke body; stub ini tetap merender isi dan footer-nya.
    const wrapper = shallowMount(PermitKoreksiDialog, {
        props: { modelValue: true, permit: PERMIT },
        global: { stubs: { HcisDialog: { template: '<div><slot /><slot name="footer" /></div>' } } },
    });
    await flushPromises();
    return wrapper;
}

afterEach(() => vi.clearAllMocks());

describe('PermitKoreksiDialog', () => {
    it('form terisi data permit dan belum bisa disimpan tanpa perubahan', async () => {
        const w = await pasang();
        expect(w.vm.form.id_kategori_permit).toBe('2');
        expect(w.vm.form.no_plat).toBe('BG 1234 AA');
        expect(w.vm.perubahan).toEqual([]);
        expect(w.vm.bisaSimpan).toBe(false);
    });

    it('deskripsi akses mengikuti jenis permit yang dipilih', async () => {
        const w = await pasang();
        expect(w.vm.kategoriTerpilih.Deskripsi_Akses).toBe('Akses penuh sampai Area Boiler');
        w.vm.form.id_kategori_permit = '1';
        expect(w.vm.kategoriTerpilih.Deskripsi_Akses).toBe('Hanya area parkir');
    });

    it('mengubah huruf besar/kecil keterangan dihitung perubahan, spasi tepi tidak', async () => {
        const w = await pasang();
        w.vm.form.keterangan_pengajuan = '  Parkir harian  ';
        expect(w.vm.perubahan).toEqual([]);
        w.vm.form.keterangan_pengajuan = 'PARKIR HARIAN';
        expect(w.vm.perubahan).toEqual(['Keterangan']);
    });

    it('perubahan terdeteksi tapi alasan tetap wajib', async () => {
        const w = await pasang();
        w.vm.form.id_kategori_permit = '1';
        expect(w.vm.perubahan).toEqual(['Jenis permit']);
        expect(w.vm.bisaSimpan).toBe(false);
        w.vm.form.alasan = '  ';
        expect(w.vm.bisaSimpan).toBe(false);
        w.vm.form.alasan = 'Salah zona';
        expect(w.vm.bisaSimpan).toBe(true);
    });

    it('ganti model memuat pratinjau checklist dan menampilkan jawaban yang akan terbuang', async () => {
        const w = await pasang();
        w.vm.form.id_vehicle_model = '1';
        await w.vm.muatPratinjau();
        expect(axios.get).toHaveBeenCalledWith('/verifikator-permit/PMT0926-0001/koreksi/pratinjau-checklist', {
            params: { id_kategori_permit: '2', id_vehicle_model: '1' },
        });
        expect(w.vm.jawabanTerbuang).toBe(1);
    });

    it('simpan mengirim seluruh isian lalu memancarkan tersimpan', async () => {
        axios.post.mockResolvedValueOnce({ data: { success: true, result: { berubah: ['TAHUN'] } } });
        const w = await pasang();
        w.vm.form.tahun_kendaraan = 2019;
        w.vm.form.alasan = 'STNK 2019';
        await w.vm.simpan();
        expect(axios.post).toHaveBeenCalledWith('/verifikator-permit/PMT0926-0001/koreksi', {
            id_kategori_permit: '2',
            no_plat: 'BG 1234 AA',
            id_vehicle_model: '3',
            id_vehicle_color: '1',
            tahun_kendaraan: 2019,
            keterangan_pengajuan: 'Parkir harian',
            alasan: 'STNK 2019',
            nilai_awal: {
                id_kategori_permit: '2',
                no_plat: 'BG 1234 AA',
                id_vehicle_model: '3',
                id_vehicle_color: '1',
                tahun_kendaraan: 2020,
                keterangan_pengajuan: 'Parkir harian',
            },
        });
        expect(w.emitted('tersimpan')[0]).toEqual([{ berubah: ['TAHUN'] }]);
        expect(w.emitted('update:modelValue')[0]).toEqual([false]);
    });

    it('selama menyimpan form dikunci dan dialog tidak bisa ditutup', async () => {
        let selesai;
        axios.post.mockReturnValueOnce(new Promise((r) => { selesai = r; }));
        const w = await pasang();
        w.vm.form.tahun_kendaraan = 2019;
        w.vm.form.alasan = 'STNK 2019';
        const janji = w.vm.simpan();
        await w.vm.$nextTick();
        expect(w.vm.menyimpan).toBe(true);
        expect(w.find('.koreksi-overlay').exists()).toBe(true);
        expect(w.find('fieldset').attributes('disabled')).toBeDefined();
        selesai({ data: { success: true, result: { berubah: ['TAHUN'] } } });
        await janji;
        expect(w.vm.menyimpan).toBe(false);
    });

    it('pratinjau checklist menandai sedang memuat', async () => {
        const w = await pasang();
        w.vm.form.id_kategori_permit = '1';
        const janji = w.vm.muatPratinjau();
        expect(w.vm.memuatPratinjau).toBe(true);
        await janji;
        expect(w.vm.memuatPratinjau).toBe(false);
    });

    it('footer meringkas perubahan dan menyebut alasan yang belum diisi', async () => {
        const w = await pasang();
        expect(w.find('.koreksi-footer__ringkas').text()).toContain('Belum ada data yang diubah');
        w.vm.form.id_kategori_permit = '1';
        await w.vm.$nextTick();
        expect(w.find('.koreksi-footer__ringkas').text()).toContain('1 perubahan');
        expect(w.find('.koreksi-footer__kurang').exists()).toBe(true);
        w.vm.form.alasan = 'Salah zona';
        await w.vm.$nextTick();
        expect(w.find('.koreksi-footer__kurang').exists()).toBe(false);
    });

    it('galat server tampil di dialog, dialog tetap terbuka', async () => {
        axios.post.mockRejectedValueOnce({ response: { data: { message: 'Permit sudah tidak menunggu review.' } } });
        const w = await pasang();
        w.vm.form.tahun_kendaraan = 2019;
        w.vm.form.alasan = 'x';
        await w.vm.simpan();
        expect(w.vm.galat).toBe('Permit sudah tidak menunggu review.');
        expect(w.emitted('update:modelValue')).toBeUndefined();
    });
});
