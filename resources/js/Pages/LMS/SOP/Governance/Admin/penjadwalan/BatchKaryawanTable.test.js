import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import BatchKaryawanTable from './BatchKaryawanTable.vue';

vi.mock('axios');
// Dialog konfirmasi selalu disetujui; yang diuji apa yang DIKIRIM setelahnya.
vi.mock('element-plus', async (importOriginal) => ({
    ...(await importOriginal()),
    ElMessage: { success: vi.fn(), error: vi.fn(), warning: vi.fn() },
    ElMessageBox: { confirm: vi.fn(() => Promise.resolve()) },
}));

/**
 * Saringan unit kerja di daftar karyawan sebuah batch.
 *
 * Yang diikat paling keras bukan daftarnya, melainkan "Pilih semua N hasil".
 * Tombol itu memanggil endpoint LAIN (/karyawan-kodes) dan hasilnya langsung
 * jadi masukan pencabutan massal. Kalau ia mengirim saringan yang berbeda dari
 * yang dipakai daftar, admin menyaring satu divisi, menekan "pilih semua", lalu
 * mencabut akses seluruh batch — sementara layarnya tetap memperlihatkan divisi
 * yang tadi disaring, jadi tak ada apa pun yang terlihat keliru.
 */

const OPSI = {
    divisi: ['PRODUKSI', 'KEUANGAN'],
    departemen: ['QA', 'QC'],
    jabatan: ['Staff', 'Supervisor'],
    level: ['L1', 'L2'],
    lokasi: ['Pusat', 'Cabang A'],
};

const baris = (kode) => ({
    Kode_Karyawan: kode,
    Nama: `Nama ${kode}`,
    Departemen: 'QA',
    Divisi: 'PRODUKSI',
    Jabatan: 'Staff',
    Level: 'L1',
    Lokasi: 'Pusat',
    Status_Paket: 'BELUM',
    Flag_Cabut_Akses: 'T',
    Item_Total: 4,
    Item_Selesai: 1,
    Persen: 25,
});

let panggilan;

let dikirim;

function pasangMock(rows = ['K1', 'K2'], semuaHalaman = null) {
    panggilan = [];
    dikirim = [];
    axios.get = vi.fn((url, cfg) => {
        panggilan.push({ url, params: cfg?.params || {} });

        return Promise.resolve({
            data: url.endsWith('/karyawan-kodes')
                ? // Sengaja BUKAN isi halaman: inilah "seluruh hasil lintas halaman".
                  { success: true, result: semuaHalaman || rows }
                : { success: true, result: rows.map(baris), pagination: { total: (semuaHalaman || rows).length } },
        });
    });
    axios.post = vi.fn((url, body) => {
        dikirim.push({ url, body });

        return Promise.resolve({ data: { success: true, result: { dicabut: body.kode_karyawan.length } } });
    });
}

async function pasang() {
    pasangMock();
    const w = mount(BatchKaryawanTable, {
        props: { idBatch: 'batch-1', opsiFilter: OPSI },
        global: { stubs: { 'el-select': true, 'el-option': true } },
    });
    await flushPromises();

    return w;
}

const permintaanDaftar = () => panggilan.filter((p) => p.url.endsWith('/karyawan'));
const permintaanKodes = () => panggilan.filter((p) => p.url.endsWith('/karyawan-kodes'));

describe('BatchKaryawanTable — saringan unit kerja', () => {
    beforeEach(() => vi.clearAllMocks());

    it('menawarkan kelima atribut organisasi', async () => {
        const w = await pasang();
        const label = w.vm.filtersConfig.map((n) => n.label);
        expect(label).toEqual(['Divisi', 'Sub Divisi', 'Jabatan', 'Level', 'Lokasi Kerja']);
    });

    it('mengirim saringan yang dipilih ke daftar', async () => {
        const w = await pasang();
        w.vm.saringan.divisi = ['PRODUKSI'];
        w.vm.saringan.level = ['L2'];
        await w.vm.terapkanSaringan();
        await flushPromises();

        const terakhir = permintaanDaftar().at(-1).params;
        expect(terakhir.divisi).toEqual(['PRODUKSI']);
        expect(terakhir.level).toEqual(['L2']);
    });

    it('memberi "pilih semua" saringan yang sama persis dengan daftar', async () => {
        const w = await pasang();
        w.vm.saringan.divisi = ['PRODUKSI'];
        w.vm.saringan.lokasi = ['Cabang A'];
        await w.vm.terapkanSaringan();
        await flushPromises();

        await w.vm.pilihSemua();
        await flushPromises();

        // Inilah jalur yang memberi makan pencabutan massal.
        const daftar = { ...permintaanDaftar().at(-1).params };
        const kodes = { ...permintaanKodes().at(-1).params };
        delete daftar.page;
        delete daftar.limit;

        expect(kodes).toEqual(daftar);
    });

    it('membuang seleksi lama saat saringan berubah', async () => {
        const w = await pasang();
        w.vm.terpilih = ['K1', 'K2'];

        w.vm.saringan.divisi = ['KEUANGAN'];
        await w.vm.terapkanSaringan();
        await flushPromises();

        // Orang yang tercentang sebelum menyaring bisa saja tak ada lagi di
        // layar — membiarkannya tercentang berarti mencabut akses orang yang
        // tak terlihat oleh yang menekan tombolnya.
        expect(w.vm.terpilih).toEqual([]);
    });

    it('tidak mengirim kunci saringan yang kosong', async () => {
        const w = await pasang();
        w.vm.saringan.jabatan = [];
        await w.vm.terapkanSaringan();
        await flushPromises();

        const p = permintaanDaftar().at(-1).params;
        expect(p).not.toHaveProperty('jabatan');
        expect(p).not.toHaveProperty('divisi');
    });

    it('menghitung saringan aktif supaya tak menyaring diam-diam', async () => {
        const w = await pasang();
        expect(w.vm.jumlahSaringan).toBe(0);

        w.vm.saringan.divisi = ['PRODUKSI', 'KEUANGAN'];
        w.vm.saringan.lokasi = ['Pusat'];
        await w.vm.$nextTick();

        // Bilah bisa terlipat; angkanya yang memberi tahu daftar sedang disempitkan.
        expect(w.vm.jumlahSaringan).toBe(3);
    });

    it('mencabut seluruh hasil lintas halaman, bukan hanya yang terlihat', async () => {
        // Halaman menampilkan 2 orang; hasil saringan sebenarnya 5.
        pasangMock(['K1', 'K2'], ['K1', 'K2', 'K3', 'K4', 'K5']);
        const w = mount(BatchKaryawanTable, {
            props: { idBatch: 'batch-1', opsiFilter: OPSI },
            global: { stubs: { 'el-select': true, 'el-option': true } },
        });
        await flushPromises();

        w.vm.saringan.divisi = ['PRODUKSI'];
        await w.vm.terapkanSaringan();
        await flushPromises();

        await w.vm.pilihSemua();
        await flushPromises();

        // cabutMassal() kini hanya MEMBUKA dialog sebab; yang mengirim adalah
        // konfirmasinya. Diuji lewat dua langkah itu, bukan dilangkahi.
        await w.vm.cabutMassal();
        expect(w.vm.cabutBuka).toBe(true);
        expect(dikirim).toHaveLength(0);

        await w.vm.kirimCabut({ sebab: 'SALAH_TARGET', catatan: null });
        await flushPromises();

        // Mata rantai terakhir: apa yang dikembalikan "pilih semua" HARUS yang
        // dikirim ke pencabutan. Admin cuma pernah melihat K1 dan K2.
        expect(dikirim).toHaveLength(1);
        expect(dikirim[0].body.kode_karyawan).toEqual(['K1', 'K2', 'K3', 'K4', 'K5']);
        // Sebabnya ikut terkirim — tanpa ini barisnya masuk ledger tanpa penjelasan.
        expect(dikirim[0].body.sebab).toBe('SALAH_TARGET');
    });

    it('menawarkan jalan keluar saat saringan mengosongkan daftar', async () => {
        const w = await pasang();
        pasangMock([]);
        w.vm.saringan.divisi = ['KEUANGAN'];
        await w.vm.terapkanSaringan();
        await flushPromises();

        // Tanpa ini, daftar kosong terbaca "batch ini memang kosong".
        expect(w.find('.bkt-empty').text()).toContain('saringan');
    });
});
