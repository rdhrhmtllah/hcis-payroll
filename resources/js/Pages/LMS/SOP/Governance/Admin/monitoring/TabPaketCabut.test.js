import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import TabPaket from './TabPaket.vue';

vi.mock('axios');
vi.mock('element-plus', async (importOriginal) => ({
    ...(await importOriginal()),
    ElMessage: { success: vi.fn(), error: vi.fn(), warning: vi.fn() },
    ElMessageBox: { confirm: vi.fn(() => Promise.resolve()) },
}));

/**
 * Pintu KEDUA pencabutan akses: Monitoring → rincian paket → satu karyawan.
 *
 * Modul ini punya riwayat panjang aturan yang hanya terpasang di salah satu dari
 * dua jalur setara. Pintu pertama (pencabutan massal per batch) sudah dijaga di
 * BatchKaryawanTable.test.js; berkas ini menjaga pasangannya, dan sengaja
 * menguji EFEKNYA — apa yang benar-benar dikirim ke server — bukan sekadar
 * bahwa dialognya dipanggil.
 */
function pasang() {
    const dikirim = [];
    axios.get = vi.fn(() =>
        Promise.resolve({ data: { success: true, result: [], pagination: { total: 0 }, summary: {} } }),
    );
    axios.post = vi.fn((url, body) => {
        dikirim.push({ url, body });

        return Promise.resolve({ data: { success: true, message: 'ok', result: { dicabut: 1 } } });
    });

    const w = mount(TabPaket, {
        props: { filterOptions: {} },
        global: {
            stubs: {
                ModernDataTable: true,
                FilterPanel: true,
                ProgressBar: true,
                StatusPill: true,
                ItemTypeIcon: true,
                SopRing: true,
                SopModal: true,
                SopCabutAksesDialog: true,
            },
        },
    });

    return { w, dikirim };
}

describe('TabPaket — pintu pencabutan per karyawan', () => {
    it('membuka dialog sebab dulu, tidak langsung mencabut', async () => {
        const { w, dikirim } = pasang();
        await flushPromises();

        w.vm.cabutKaryawan({ Kode_Karyawan: 'A1', Nama: 'Budi' });

        expect(w.vm.cabut.buka).toBe(true);
        expect(dikirim).toHaveLength(0);
    });

    it('mengirim sebab dan catatan bersama pencabutan', async () => {
        const { w, dikirim } = pasang();
        await flushPromises();

        w.vm.drill = { idPaket: 'pkt-1', nama: 'K3 Dasar' };
        w.vm.cabutKaryawan({ Kode_Karyawan: 'A1', Nama: 'Budi' });
        await w.vm.kirimCabut({ sebab: 'KELUAR', catatan: 'Resign 31 Jul' });
        await flushPromises();

        const cabut = dikirim.filter((d) => d.url.endsWith('/penjadwalan/cabut'));
        expect(cabut).toHaveLength(1);
        expect(cabut[0].body).toMatchObject({
            kode_karyawan: 'A1',
            id_paket: 'pkt-1',
            sebab: 'KELUAR',
            catatan: 'Resign 31 Jul',
        });
    });

    it('menyebut siapa dan paket apa yang akan dicabut', async () => {
        const { w } = pasang();
        await flushPromises();

        w.vm.drill = { idPaket: 'pkt-1', nama: 'K3 Dasar' };
        w.vm.cabutKaryawan({ Kode_Karyawan: 'A1', Nama: 'Budi' });

        // Dialog yang tidak menyebut sasarannya membuat pencabutan orang keliru
        // terlihat persis sama dengan yang benar.
        expect(w.vm.subjudulCabut).toContain('Budi');
        expect(w.vm.subjudulCabut).toContain('K3 Dasar');
    });

    it('melepas kunci sibuk meski permintaannya gagal', async () => {
        const { w } = pasang();
        await flushPromises();
        axios.post = vi.fn(() => Promise.reject(new Error('putus')));

        w.vm.drill = { idPaket: 'pkt-1', nama: 'K3 Dasar' };
        w.vm.cabutKaryawan({ Kode_Karyawan: 'A1', Nama: 'Budi' });
        await w.vm.kirimCabut({ sebab: 'DUPLIKAT', catatan: null });
        await flushPromises();

        // Tanpa pelepasan ini, satu kegagalan jaringan mengunci tombolnya
        // selamanya dan admin menyangka dialognya rusak.
        expect(w.vm.cabut.sibuk).toBe(false);
        expect(w.vm.cabut.buka).toBe(true);
    });
});
