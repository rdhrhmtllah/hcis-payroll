import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import SopPublishPaketPratinjau from './SopPublishPaketPratinjau.vue';

vi.mock('axios');
vi.mock('element-plus', async (importOriginal) => {
    const asli = await importOriginal();

    return { ...asli, ElMessage: Object.assign(vi.fn(), { error: vi.fn(), success: vi.fn() }) };
});

/**
 * Sifat revisi dipilih PER DOKUMEN.
 *
 * Satu publish paket bisa menaikkan beberapa dokumen dengan alasan berbeda:
 * satu cuma salah ketik, satu lagi benar-benar berubah isinya. Dulu modal ini
 * mendaftar ketiganya satu per satu lalu cuma menyediakan SATU saklar — admin
 * melihat tiga baris tapi hanya boleh mengambil satu keputusan. Karena kalau
 * ragu orang memilih mayor, hasilnya sama saja dengan tak punya pilihan, dan
 * "dibuka lagi" kehilangan artinya.
 *
 * Yang diuji di sini APA YANG DIKIRIM ke induk — bukan keadaan internalnya.
 * Peta yang benar di data() dengan emit yang salah menghasilkan revisi yang
 * salah sifat, dan tak ada satu pun tanda di layar.
 */

const DUA_DOKUMEN = {
    versi: [
        { Id_SOP_Dokumen: 'dok-a', Judul_Item: 'SOP Meeting', Versi_Sekarang: 5, Versi_Baru: 6, Paket_Lain: [] },
        { Id_SOP_Dokumen: 'dok-b', Judul_Item: 'Formulir Booking', Versi_Sekarang: 2, Versi_Baru: 3, Paket_Lain: [] },
        // Item baru: belum punya dokumen, jadi tak bisa dipilih sifatnya.
        { Id_SOP_Dokumen: null, Judul_Item: 'Item baru', Versi_Sekarang: null, Versi_Baru: 0, Paket_Lain: [] },
    ],
    paket_lain: 0,
    karyawan_lain: 0,
};

async function pasang(result = DUA_DOKUMEN) {
    axios.get = vi.fn(() => Promise.resolve({ data: { success: true, result } }));

    const w = mount(SopPublishPaketPratinjau, {
        props: { show: true, paket: 'hash-paket' },
        global: {
            mocks: { $vlabel: (v) => `v${String(v ?? 0).padStart(2, '0')}` },
            stubs: { SopModal: { template: '<div><slot /><slot name="footer" /></div>' } },
        },
    });
    await flushPromises();

    return w;
}

const pil = (w, teks) => w.findAll('.pp-pil').filter((b) => new RegExp(teks, 'i').test(b.text()));
const tombolTerbit = (w) => w.findAll('button').find((b) => /terbitkan/i.test(b.text()));

describe('SopPublishPaketPratinjau — dua keadaan tanpa revisi dokumen', () => {
    /* Rilis pertama dan revisi susunan sama-sama tak membawa revisi dokumen,
       tapi akibatnya berlawanan besarnya: yang satu menugaskan SOP kepada
       karyawan untuk PERTAMA KALI. Satu kalimat untuk keduanya — "yang berubah
       hanya susunan paket ini" — membuat penerbitan pertama terbaca seperti
       pemindahan urutan, dan admin menyimpulkan sistem tak mengenali kerjanya. */
    async function pasangKosong(extra) {
        axios.get = vi.fn(() =>
            Promise.resolve({
                data: { result: { versi: [], paket_lain: 0, karyawan_lain: 0, ...extra } },
            }),
        );

        const w = mount(SopPublishPaketPratinjau, {
            props: { show: false, paket: 'hash-paket' },
            global: { stubs: { teleport: true }, mocks: { $vlabel: (v) => `v${v}` } },
        });
        await w.setProps({ show: true });
        await flushPromises();

        return w;
    }

    it('rilis pertama disebut sebagai rilis pertama, berikut jumlah itemnya', async () => {
        const w = await pasangKosong({ rilis_pertama: true, jumlah_item: 7 });

        expect(w.text()).toContain('pertama kali');
        expect(w.text()).toContain('7 item');
        expect(w.text()).not.toContain('hanya susunan');
    });

    it('revisi susunan tetap menyebut akibatnya bagi penerima', async () => {
        const w = await pasangKosong({ rilis_pertama: false });

        expect(w.text()).not.toContain('pertama kali');
        // Mengganti item BUKAN perubahan kosmetik: penerima membaca SOP lain.
        expect(w.text()).toContain('membaca dokumen yang berbeda');
    });
});

describe('SopPublishPaketPratinjau — sifat per dokumen', () => {
    it('menawarkan pilihan pada tiap dokumen, bukan sekali untuk semua', async () => {
        const w = await pasang();

        // Dua baris berdokumen × 2 opsi, ditambah 2 tombol "setel semuanya".
        expect(w.findAll('.pp-pilih')).toHaveLength(2);
        expect(w.find('.pp-baru').exists()).toBe(true);
    });

    it('bawaannya mayor untuk semuanya — diam bukan berarti "tak usah baca"', async () => {
        const w = await pasang();
        await tombolTerbit(w).trigger('click');

        const [sifat, peta] = w.emitted('terbitkan')[0];
        expect(sifat).toBe('Y');
        // Peta boleh kosong; yang penting tak satu pun bernilai minor.
        expect(Object.values(peta).filter((v) => v === 'T')).toHaveLength(0);
    });

    it('menandai satu dokumen minor tak menyeret yang lain', async () => {
        const w = await pasang();

        // Pil "Minor" milik baris pertama.
        await w.findAll('.pp-pilih')[0].findAll('.pp-pil')[1].trigger('click');
        await tombolTerbit(w).trigger('click');

        const [, peta] = w.emitted('terbitkan')[0];
        expect(peta['dok-a']).toBe('T');
        expect(peta['dok-b'] ?? 'Y').toBe('Y');
    });

    it('sifat menyeluruh yang dikirim tetap mayor — item baru selalu kewajiban baru', async () => {
        const w = await pasang();

        await w.findAll('.pp-pilih')[0].findAll('.pp-pil')[1].trigger('click');
        await w.findAll('.pp-pilih')[1].findAll('.pp-pil')[1].trigger('click');
        await tombolTerbit(w).trigger('click');

        // Walau SEMUA dokumen ditandai minor, item yang belum punya dokumen
        // tetap jatuh ke mayor lewat nilai menyeluruh ini.
        expect(w.emitted('terbitkan')[0][0]).toBe('Y');
    });

    it('"setel semuanya" mengisi setiap baris, bukan menggantikan pilihannya', async () => {
        const w = await pasang();

        await pil(w, 'minor').at(-1).trigger('click'); // tombol pintasan di bawah
        await tombolTerbit(w).trigger('click');

        const [, peta] = w.emitted('terbitkan')[0];
        expect(peta).toEqual({ 'dok-a': 'T', 'dok-b': 'T' });
    });

    it('menghitung berapa dokumen yang dilonggarkan', async () => {
        const w = await pasang();
        expect(w.find('.pp-catat').exists()).toBe(false);

        await w.findAll('.pp-pilih')[0].findAll('.pp-pil')[1].trigger('click');

        expect(w.find('.pp-catat').text()).toMatch(/1 dari 2 dokumen ditandai minor/i);
    });

    it('pilihan tak nyangkut saat modal dibuka lagi untuk paket lain', async () => {
        const w = await pasang();
        await w.findAll('.pp-pilih')[0].findAll('.pp-pil')[1].trigger('click');

        await w.setProps({ show: false });
        await w.setProps({ show: true });
        await flushPromises();

        await tombolTerbit(w).trigger('click');
        const [, peta] = w.emitted('terbitkan').at(-1);
        expect(Object.values(peta).filter((v) => v === 'T')).toHaveLength(0);
    });
});
