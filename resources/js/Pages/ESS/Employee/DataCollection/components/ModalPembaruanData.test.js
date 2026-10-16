import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import ModalPembaruanData from './ModalPembaruanData.vue';

const pengumuman = {
    judul: 'Data kamu, sekarang di tanganmu',
    teks: 'Dua menit, sekali saja.',
    label_tombol_isi: 'Isi sekarang',
    label_tombol_tunda: 'Nanti dulu',
};

const status = (jumlahTunda) => ({
    status: 'BELUM',
    jumlah_tunda: jumlahTunda,
    sisa_tunda: 3 - jumlahTunda,
    terkunci: false,
});

let terpasang = [];

const buat = (jumlahTunda = 0) => {
    const w = mount(ModalPembaruanData, {
        props: { pengumuman, status: status(jumlahTunda), maksPenundaan: 3 },
        attachTo: document.body,
    });

    terpasang.push(w);

    return w;
};

// HcisDialog mem-TELEPORT isinya ke <body> — itu memang yang kita mau (lihat
// jebakan 'fixed terperangkap transform leluhur'), tapi artinya isi modal TIDAK
// berada di dalam pohon wrapper. Uji harus melihat ke tempat ia benar-benar
// hidup, bukan ke tempat ia dituliskan.
const layar = () => document.body;
const cari = (pilih) => document.body.querySelector(pilih);

afterEach(() => {
    terpasang.forEach((w) => w.unmount());
    terpasang = [];
});

describe('ModalPembaruanData', () => {
    it('dirakit dari HcisDialog, bukan dari lapisan buatan sendiri', () => {
        const w = buat();

        // Lapisan buatan sendiri berarti kunci gulir, jebakan fokus, dan
        // Teleport-nya ditulis ulang di sini — tiga hal yang sudah dipecahkan
        // sekali untuk seluruh aplikasi di HcisDialog.
        expect(w.findComponent({ name: 'HcisDialog' }).exists()).toBe(true);
        expect(cari('.pd-modal__panel')).toBeNull();
    });

    it('membawa kalimat dari database pada pertemuan pertama', () => {
        buat();

        expect(layar().textContent).toContain('Data kamu, sekarang di tanganmu');
    });

    it('memperlihatkan sisa jatah supaya terlihat menipis', () => {
        buat(0);
        expect(layar().textContent).toContain('3');

        terpasang.pop().unmount();

        buat(2);
        expect(layar().textContent).toContain('1');
    });

    it('selalu bisa ditutup — yang tak bisa ditunda tidak pernah sampai ke sini', () => {
        // Sejak gerbangnya jadi pengalihan server, orang yang jatahnya habis
        // tidak pernah membuka halaman lain: ia sudah berada di formulirnya.
        // Jadi modal tanpa jalan keluar tidak punya alasan untuk ada lagi.
        const dialog = buat().findComponent({ name: 'HcisDialog' });

        expect(dialog.props('showClose')).toBe(true);
        expect(dialog.props('closeOnEsc')).toBe(true);
        expect(dialog.props('closeOnOverlay')).toBe(true);
    });

    it('satu penutupan memotong TEPAT satu jatah tunda', async () => {
        // HcisDialog.close() memancarkan 'update:modelValue' DAN 'close'.
        // Menyimak keduanya membuat sekali menutup memakan dua jatah — dan
        // orangnya terkunci lebih cepat dari yang dijanjikan di layar.
        const w = buat();

        await w.findComponent({ name: 'HcisDialog' }).vm.$emit('close');

        expect(w.emitted('tunda')).toHaveLength(1);
    });

    it('memancarkan isi dari tombol utama', () => {
        const w = buat();

        cari('[data-uji="isi"]').click();

        expect(w.emitted('isi')).toBeTruthy();
    });

    it('memancarkan tunda dari tombol nanti dulu', () => {
        const w = buat();

        cari('[data-uji="tunda"]').click();

        expect(w.emitted('tunda')).toHaveLength(1);
    });

    it('memancarkan tampil sekali setelah benar-benar terpasang', () => {
        expect(buat().emitted('tampil')).toHaveLength(1);
    });

    it('memakai rasa hero HcisResultState, bukan kepala dialog yang datar', () => {
        // Kepala dialog biasa membariskan ikon kecil di kiri teks — cocok untuk
        // konfirmasi, terlalu datar untuk satu-satunya ajakan yang dilihat
        // seluruh karyawan. Hero-nya memakai lingkaran ikon besar di tengah,
        // sama seperti layar 'data kamu sudah masuk'.
        const w = buat();
        const hasil = w.findComponent({ name: 'HcisResultState' });

        expect(hasil.exists()).toBe(true);
        expect(hasil.props('animated')).toBe(true);
        expect(cari('.hcis-result-state__icon-ring')).not.toBeNull();

        // Judulnya dibawakan hero, jadi dialognya sendiri tidak boleh ikut
        // menuliskannya — kalau tidak, judulnya muncul dua kali.
        expect(w.findComponent({ name: 'HcisDialog' }).props('title')).toBe('');
    });

    it('menaikkan suhu hero-nya seiring jatah menipis', () => {
        expect(buat(0).findComponent({ name: 'HcisResultState' }).props('status')).toBe('info');
        expect(buat(1).findComponent({ name: 'HcisResultState' }).props('status')).toBe('warning');
    });
});
