import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import PanelKomposisiDokumen from './PanelKomposisiDokumen.vue';

const komposisi = {
    Total: 3,
    Baris: [
        { Kelompok: 'DITUGASKAN', Nama: 'Ditugaskan', Keterangan: 'Ditugaskan', Jumlah: 1, Persen: 33.3 },
        { Kelompok: 'BEREDAR', Nama: 'Beredar', Keterangan: 'Beredar', Jumlah: 1, Persen: 33.3 },
        { Kelompok: 'BELUM_BEREDAR', Nama: 'Belum Beredar', Keterangan: 'Tak sampai', Jumlah: 1, Persen: 33.3 },
    ],
};

const pasang = (props = {}) => mount(PanelKomposisiDokumen, {
    props: { komposisi, ...props },
});

const lebar = (w) => w.findAll('.pkd-seg').map((s) => s.attributes('style'));

describe('PanelKomposisiDokumen', () => {
    it('menghitung lebar batang dari jumlah, bukan dari persen yang sudah dibulatkan', () => {
        /* Tiga persen yang masing-masing dibulatkan ke 33,3 berjumlah 99,9 dan
           menyisakan celah tipis di ujung batang. Lebarnya karena itu dihitung
           ulang dari jumlah. */
        const w = pasang();

        expect(lebar(w).every((s) => s.includes('33.33'))).toBe(true);
    });

    it('tidak membagi nol saat belum ada dokumen', () => {
        const w = pasang({ komposisi: { Total: 0, Baris: komposisi.Baris.map((b) => ({ ...b, Jumlah: 0 })) } });

        expect(lebar(w).every((s) => s.includes('width: 0%'))).toBe(true);
    });

    it('mengemit kelompok yang diklik', async () => {
        const w = pasang();
        await w.findAll('.pkd-item')[0].trigger('click');

        expect(w.emitted('pilih')[0]).toEqual(['DITUGASKAN']);
    });

    it('klik kedua pada kelompok yang sama melepas pilihan', async () => {
        /* Saringan yang hanya bisa dipasang dan tak bisa dilepas memaksa orang
           memuat ulang halaman untuk kembali melihat semuanya. */
        const w = pasang({ dipilih: 'DITUGASKAN' });
        await w.findAll('.pkd-item')[0].trigger('click');

        expect(w.emitted('pilih')[0]).toEqual([null]);
    });

    it('menyebut nama kelompok dan keterangannya apa adanya dari server', () => {
        /* Layar tak menyimpan daftar kelompok sendiri: menambah kelompok
           keempat cukup mengubah service. */
        const w = pasang();

        expect(w.text()).toContain('Belum Beredar');
        expect(w.text()).toContain('Tak sampai');
    });
});
