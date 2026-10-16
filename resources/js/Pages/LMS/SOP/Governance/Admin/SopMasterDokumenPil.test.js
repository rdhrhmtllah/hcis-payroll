import { describe, expect, it, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import SopMasterDokumen from './SopMasterDokumen.vue';

vi.mock('axios', () => ({
    default: {
        get: vi.fn().mockResolvedValue({ data: { data: [], tipe: ['DOKUMEN'] } }),
        post: vi.fn().mockResolvedValue({ data: {} }),
        put: vi.fn().mockResolvedValue({ data: {} }),
    },
}));

/**
 * Dua pil daftar kerja di kepala Master Dokumen.
 *
 * PERLU DITINJAU adalah jam yang berdetak — tanggalnya menagih, telat berarti
 * karyawan membaca dokumen yang sudah kedaluwarsa. BELUM LENGKAP adalah
 * pekerjaan menggantung — tak ada tenggat, dan jumlahnya memang akan selalu
 * besar karena mendeklarasikan SOP adalah langkah pertama alur.
 *
 * Digabung jadi satu angka, tumpukan draft menenggelamkan satu dokumen yang
 * benar-benar mau kedaluwarsa — kebalikan persis dari gunanya daftar ini.
 */
const pasang = (ringkas) => {
    const w = shallowMount(SopMasterDokumen, { props: { awal: null } });
    w.vm.ringkas = ringkas;

    return w;
};

const pil = (w) => w.findAll('.mh-alarm-pill');

describe('pil daftar kerja Master Dokumen', () => {
    it('menampilkan keduanya dengan angkanya masing-masing', async () => {
        const w = pasang({ PERHATIAN: 3, BELUM_LENGKAP: 15 });
        await w.vm.$nextTick();

        const teks = pil(w).map((b) => b.text());

        expect(teks.some((t) => /3\s*Perlu Ditinjau/.test(t))).toBe(true);
        expect(teks.some((t) => /15\s*Belum Lengkap/.test(t))).toBe(true);
    });

    /** Pil bernilai nol tak ditawarkan — ia hanya kebisingan di kepala daftar. */
    it('menyembunyikan pil yang angkanya nol', async () => {
        const w = pasang({ PERHATIAN: 0, BELUM_LENGKAP: 15 });
        await w.vm.$nextTick();

        const teks = pil(w).map((b) => b.text());

        expect(teks.some((t) => t.includes('Perlu Ditinjau'))).toBe(false);
        expect(teks.some((t) => t.includes('Belum Lengkap'))).toBe(true);
    });

    /**
     * KECUALI SAAT IA SEDANG MENYALA.
     *
     * Kartu ringkas boleh ditekan meski angkanya nol, dan sesudah itu pilnya
     * HARUS terlihat menyala — kalau ia tetap tersembunyi, admin menekan kartu
     * lalu melihat daftar kosong tanpa satu pun tanda bahwa penyaring sedang
     * aktif, dan menyimpulkan layarnya rusak.
     */
    it('tetap menampilkan pil bernilai nol saat penyaringnya sedang menyala', async () => {
        const w = pasang({ PERHATIAN: 0, BELUM_LENGKAP: 15 });
        w.vm.tapis = 'PERHATIAN';
        await w.vm.$nextTick();

        const tinjau = pil(w).find((b) => b.text().includes('Perlu Ditinjau'));

        expect(tinjau).toBeTruthy();
        expect(tinjau.classes()).toContain('on');
    });

    /** Daftar kosong harus menyebut penyaring mana yang sedang menyala. */
    it('menjelaskan daftar kosong dengan menyebut penyaringnya', async () => {
        const w = pasang({ PERHATIAN: 0, BELUM_LENGKAP: 15 });
        w.vm.tapis = 'PERHATIAN';
        await w.vm.$nextTick();

        expect(w.vm.kosongJudul).toBe('Tak Ada yang Perlu Ditinjau');
        expect(w.vm.kosongKalimat).toContain('90 hari');

        w.vm.tapis = 'BELUM_LENGKAP';
        await w.vm.$nextTick();

        expect(w.vm.kosongJudul).toBe('Semua Dokumen Sudah Lengkap');
    });

    /**
     * WARNA DIBEDAKAN. Pekerjaan tanpa tenggat tak boleh berteriak sekeras
     * tanggal yang sudah lewat — dua peringatan berwarna sama membuat keduanya
     * berhenti dibaca.
     */
    it('memberi pil tanpa tenggat warna yang lebih sunyi', async () => {
        const w = pasang({ PERHATIAN: 3, BELUM_LENGKAP: 15 });
        await w.vm.$nextTick();

        const tinjau = pil(w).find((b) => b.text().includes('Perlu Ditinjau'));
        const lengkap = pil(w).find((b) => b.text().includes('Belum Lengkap'));

        expect(tinjau.classes()).not.toContain('is-quiet');
        expect(lengkap.classes()).toContain('is-quiet');
    });

    it('menekan pil menyalakan penyaringnya', async () => {
        const w = pasang({ PERHATIAN: 3, BELUM_LENGKAP: 15 });
        await w.vm.$nextTick();

        await pil(w).find((b) => b.text().includes('Belum Lengkap')).trigger('click');

        expect(w.vm.tapis).toBe('BELUM_LENGKAP');
    });

    /** Keduanya harus punya pintunya sendiri di deretan tapis. */
    it('menawarkan keduanya sebagai tapis terpisah', () => {
        const w = pasang({});
        const kunci = w.vm.tapisan.map((t) => t.key);

        expect(kunci).toContain('PERHATIAN');
        expect(kunci).toContain('BELUM_LENGKAP');
    });
});
