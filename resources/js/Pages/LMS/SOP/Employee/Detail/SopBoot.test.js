import { describe, expect, it, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import axios from 'axios';
import SopDetail from './SopDetail.vue';
import SopSaya from '../Saya/SopSaya.vue';
import SopRepository from '../Repository/SopRepository.vue';

vi.mock('axios');

/* Element Plus dimock SEBAGIAN. Mengganti seluruh modulnya membuat komponen
   lain yang mengimpor ElInput/ElDialog gagal dimuat — yang gagal bukan uji ini,
   melainkan seluruh berkasnya, dan pesannya menyesatkan. */
vi.mock('element-plus', async (importOriginal) => {
    const asli = await importOriginal();

    return {
        ...asli,
        ElMessage: Object.assign(vi.fn(), {
            error: vi.fn(),
            success: vi.fn(),
            warning: vi.fn(),
            info: vi.fn(),
        }),
    };
});

/**
 * Apa yang dilihat karyawan pada CAT PERTAMA, sebelum jawaban server tiba.
 *
 * Ketiga halaman ini memanggil fetch-nya tanpa syarat di mounted(), jadi tak ada
 * satu momen pun di mana mereka benar-benar "tidak sedang memuat". Tapi
 * benderanya lahir false, sehingga render pertama jatuh ke cabang kosong:
 * halaman detail menyambut dengan "SOP Tidak Ditemukan", daftar tugas dengan
 * "belum ada tugas". Pada halaman detail transisinya out-in, jadi layar salah
 * itu harus selesai memudar dulu sebelum kerangkanya muncul — bukan kedipan,
 * melainkan pesan yang sempat terbaca.
 *
 * Diuji lewat yang terender, bukan lewat nilai data(): bendera yang benar dengan
 * template yang salah urutan memberi gejala yang sama persis.
 */

// Jawaban yang tak pernah tiba — menahan komponen di keadaan cat pertama.
const menggantung = () => new Promise(() => {});

function pasang(komponen, props = {}) {
    axios.get = vi.fn(menggantung);

    return shallowMount(komponen, {
        props,
        global: {
            mocks: { $vnum: (v) => String(v ?? ''), $vlabel: (v) => String(v ?? '') },
            stubs: { Head: true, Link: true, transition: false },
        },
    });
}

describe('cat pertama halaman dokumen karyawan', () => {
    it('detail SOP tidak menyambut dengan "tidak ditemukan"', () => {
        const w = pasang(SopDetail, { idPaket: 'hash-paket' });

        expect(w.text()).not.toMatch(/tidak ditemukan/i);
        expect(w.text()).toMatch(/memuat/i);
    });

    it('daftar tugas tidak menyambut dengan keadaan kosong', () => {
        const w = pasang(SopSaya);

        expect(w.vm.loading).toBe(true);
        // EmptyState di halaman ini ber-v-else-if="!visibleItems.length", jadi
        // yang membuktikannya adalah komponen itu TIDAK ikut terender.
        expect(w.findComponent({ name: 'EmptyState' }).exists()).toBe(false);
    });

    /* Dulu diuji lewat HalamanKategori — drilldown satu folder Kategori.
       Folder itu dicabut 28 Agu 2026 bersama masternya; lensa "Semua Dokumen"
       di Perpustakaan mengambil alih perannya, dan jebakan cat pertamanya
       persis sama: bendera loading lahir false, jadi render pertama bisa
       jatuh ke "belum ada dokumen" sebelum jawaban server tiba. */
    it('perpustakaan tidak menyambut dengan "belum ada dokumen"', () => {
        const w = pasang(SopRepository);

        expect(w.vm.loading).toBe(true);
        expect(w.text()).not.toMatch(/belum ada dokumen publik/i);
    });
});
