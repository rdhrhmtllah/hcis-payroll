import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import SopKonfirmasi from './SopKonfirmasi.vue';

/**
 * Kotak konfirmasi modul SOP — pengganti ElMessageBox.
 *
 * Alasannya bukan selera: dialog Element Plus membawa bahasa visualnya sendiri
 * (sudut, bayangan, jarak, tombol) ke tengah modul yang punya modalnya sendiri,
 * dan ia muncul justru pada tindakan yang paling perlu terasa serius —
 * menerbitkan revisi yang membuka ulang kewajiban ratusan orang.
 *
 * Yang dijaga di sini bukan tampilannya, melainkan dua perilaku yang mudah
 * hilang saat konfirmasi dirakit ulang: angka dampak yang benar-benar terbaca,
 * dan modal yang tidak bisa ditutup selagi permintaannya masih berjalan.
 */

function pasang(props = {}) {
    return mount(SopKonfirmasi, {
        props: { show: true, judul: 'Terbitkan Versi', pesan: 'Versi lama jadi obsolete.', ...props },
        global: { stubs: { teleport: true } },
    });
}

const tombol = (w, teks) => w.findAll('button').find((b) => new RegExp(teks, 'i').test(b.text()));

describe('SopKonfirmasi', () => {
    it('memakai modal modul ini, bukan dialog Element Plus', () => {
        const w = pasang();

        expect(w.find('.sop-modal').exists()).toBe(true);
        expect(w.find('.el-message-box').exists()).toBe(false);
    });

    it('menampilkan angka dampak terpisah dari kalimatnya', () => {
        /* Dulu angkanya dilebur jadi ekor paragraf ("… Terdampak: 2 paket, 2
           item, 5 penugasan karyawan.") dan ikut terlewat bersama paragrafnya.
           Justru angka itulah yang harus dilihat sebelum tombolnya ditekan. */
        const w = pasang({
            statistik: [
                { nilai: 2, label: 'paket' },
                { nilai: 5, label: 'penugasan karyawan' },
            ],
        });

        const stat = w.findAll('.kf-stat li');
        expect(stat).toHaveLength(2);
        expect(stat[1].text()).toContain('5');
        expect(stat[1].text()).toContain('penugasan karyawan');
    });

    it('menunggu angkanya DI DALAM modal, bukan di belakang tombol', () => {
        /* Dampaknya dihitung lewat satu permintaan jaringan. Kalau ditunggu
           sebelum modal dibuka, ada jeda ketika tombol sudah ditekan dan layar
           belum berubah sama sekali — dan klik yang tak menghasilkan apa-apa
           terbaca sebagai klik yang tak terdaftar, lalu ditekan lagi. */
        const w = pasang({ memuatStatistik: true, teksSetuju: 'Hapus' });

        expect(w.find('.kf-stat-muat').exists(), 'tak ada penanda sedang dihitung').toBe(true);
        expect(w.findAll('.kf-stat li')).toHaveLength(0);
    });

    it('mengunci tombol setuju selama angkanya dihitung', () => {
        // Seluruh guna modal ini memperlihatkan dampak sebelum diputuskan;
        // tombol yang bisa ditekan lebih dulu meniadakan gunanya.
        const w = pasang({ memuatStatistik: true, teksSetuju: 'Hapus' });

        expect(tombol(w, 'Hapus').attributes('disabled')).toBeDefined();
        // Batal tetap terbuka: menunggu angka tak boleh menyandera orang.
        expect(tombol(w, 'Batal').attributes('disabled')).toBeUndefined();
    });

    it('tidak menampilkan kotak angka saat tak ada dampak yang dihitung', () => {
        // Gagal menghitung dampak tak boleh menyisakan kotak kosong yang
        // terbaca sebagai "nol terdampak".
        expect(pasang().find('.kf-stat').exists()).toBe(false);
    });

    it('meneruskan persetujuan ke pemanggil', async () => {
        const w = pasang({ teksSetuju: 'Terbitkan' });

        await tombol(w, 'Terbitkan').trigger('click');
        expect(w.emitted('setuju')).toHaveLength(1);
    });

    it('terkunci selagi permintaannya berjalan', async () => {
        /* Ditutup di tengah jalan berarti permintaannya berlanjut tanpa layar
           yang menampung hasilnya — termasuk galatnya. */
        const w = pasang({ sibuk: true, teksSetuju: 'Terbitkan', teksSibuk: 'Menerbitkan…' });

        expect(w.text()).toContain('Menerbitkan…');
        expect(tombol(w, 'Menerbitkan').attributes('disabled')).toBeDefined();
        expect(tombol(w, 'Batal').attributes('disabled')).toBeDefined();

        await tombol(w, 'Batal').trigger('click');
        expect(w.emitted('update:show')).toBeUndefined();
    });

    it('pemberitahuan tak menawarkan Batal yang tak berarti', () => {
        /* "Data ini baru saja diubah admin lain" bukan pilihan: apa pun
           jawabannya layar HARUS dimuat ulang, karena data basi di layar lebih
           berbahaya daripada gangguan satu klik. Tombol Batal di sana
           menjanjikan jalan keluar yang tak ada. */
        const w = pasang({ tanpaBatal: true, teksSetuju: 'Muat ulang' });

        expect(tombol(w, 'Batal')).toBeUndefined();
        expect(tombol(w, 'Muat ulang')).toBeTruthy();
    });

    it('teks batal bisa disesuaikan dengan tindakannya', () => {
        // "Kembali" pada penjaga perubahan-belum-disimpan lebih jelas daripada
        // "Batal", yang di sana justru ambigu: membatalkan apa?
        const w = pasang({ teksBatal: 'Kembali', teksSetuju: 'Tinggalkan' });

        expect(tombol(w, 'Kembali')).toBeTruthy();
    });

    it('tindakan merusak memakai tombol bahaya', () => {
        // Hapus dan terbitkan tak boleh terlihat sama; warna tombolnya bagian
        // dari peringatannya.
        const w = pasang({ ragam: 'danger', teksSetuju: 'Hapus' });

        expect(tombol(w, 'Hapus').classes()).toContain('danger');
    });
});
