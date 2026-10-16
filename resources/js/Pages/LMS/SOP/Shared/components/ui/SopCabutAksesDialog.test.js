import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import SopCabutAksesDialog from './SopCabutAksesDialog.vue';

/**
 * Dialog pencabutan akses SOP.
 *
 * Yang dijaga di sini satu hal: TIDAK ADA jalan mengirim pencabutan tanpa sebab
 * yang menjelaskan. Itulah keadaan yang membuat 135 baris DICABUT di ledger
 * berisi "Dicabut" tanpa objek dan tanpa pelaku — dan yang membuat admin
 * bertanya "dicabut apa?" berbulan-bulan kemudian.
 */
function pasang(props = {}) {
    return mount(SopCabutAksesDialog, {
        props: { show: true, subjudul: 'Budi — paket "K3 Dasar"', ...props },
        global: { stubs: { SopModal: { template: '<div><slot /><slot name="footer" /></div>' } } },
    });
}

/* Dijangkar pada 'Hapus Akses', bukan kalimat penuhnya. Istilah modul ini
   sudah pernah diganti sekali (Cabut -> Hapus Akses) dan uji ini mati diam
   selama sepuluh hari karenanya: helper mengembalikan undefined, lalu SEMUA
   uji di berkas ini gagal dengan "Cannot read properties of undefined" --
   bentuk kegagalan yang menyamar jadi kerusakan komponen. */
const tombolCabut = (w) => w.findAll('button').find((b) => b.text().includes('Hapus Akses'));
const opsi = (w, teks) => w.findAll('.cad-opt').find((b) => b.text().includes(teks));

describe('SopCabutAksesDialog', () => {
    it('menolak mengirim selama sebab belum dipilih', async () => {
        const w = pasang();

        await tombolCabut(w).trigger('click');

        expect(w.emitted('konfirmasi')).toBeUndefined();
        expect(w.find('.cad-galat').text()).toContain('sebab');
    });

    it('mengirim sebab yang dipilih', async () => {
        const w = pasang();

        await opsi(w, 'Salah sasaran').trigger('click');
        await tombolCabut(w).trigger('click');

        expect(w.emitted('konfirmasi')[0][0]).toEqual({ sebab: 'SALAH_TARGET', catatan: null });
    });

    it('menuntut catatan saat sebabnya "Lainnya"', async () => {
        const w = pasang();

        await opsi(w, 'Lainnya').trigger('click');
        await tombolCabut(w).trigger('click');

        // "Lainnya" tanpa catatan tampak sudah dijawab padahal tidak menjelaskan
        // apa pun — justru lebih buruk daripada tidak memilih sebab sama sekali.
        expect(w.emitted('konfirmasi')).toBeUndefined();
        expect(w.find('.cad-galat').text()).toContain('catatan');

        await w.find('textarea').setValue('Diminta HRD, tiket #4471');
        await tombolCabut(w).trigger('click');

        expect(w.emitted('konfirmasi')[0][0]).toEqual({
            sebab: 'LAINNYA',
            catatan: 'Diminta HRD, tiket #4471',
        });
    });

    it('membuang catatan yang hanya berisi spasi', async () => {
        const w = pasang();

        await opsi(w, 'Karyawan keluar').trigger('click');
        await w.find('textarea').setValue('    ');
        await tombolCabut(w).trigger('click');

        expect(w.emitted('konfirmasi')[0][0].catatan).toBeNull();
    });

    it('tidak membawa sebab orang sebelumnya saat dibuka lagi', async () => {
        const w = pasang({ show: false });

        await w.setProps({ show: true });
        await opsi(w, 'Karyawan pindah').trigger('click');
        await w.find('textarea').setValue('pindah ke QC');
        await w.setProps({ show: false });
        await w.setProps({ show: true });

        expect(w.vm.sebab).toBe('');
        expect(w.vm.catatan).toBe('');
        await tombolCabut(w).trigger('click');
        expect(w.emitted('konfirmasi')).toBeUndefined();
    });

    it('mengunci tombol selama permintaan berjalan', async () => {
        const w = pasang({ sibuk: true });

        expect(tombolCabut(w).attributes('disabled')).toBeDefined();

        // Menutup dialog di tengah permintaan akan membuat pemanggil kehilangan
        // jejak permintaan yang masih di jalan.
        await w
            .findAll('button')
            .find((b) => b.text() === 'Batal')
            .trigger('click');
        expect(w.emitted('close')).toBeUndefined();
    });
});
