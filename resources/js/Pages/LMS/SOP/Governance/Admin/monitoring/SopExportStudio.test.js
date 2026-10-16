import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import SopExportStudio from './SopExportStudio.vue';

vi.mock('axios');
// Sebagian saja: modernFilter.vue ikut terimpor lewat studio dan butuh
// komponen Element Plus yang asli.
vi.mock('element-plus', async (importOriginal) => ({
    ...(await importOriginal()),
    ElMessage: { success: vi.fn(), error: vi.fn() },
}));
vi.mock('../../../../../../composables/useActivityCenter', () => ({
    useActivityCenter: () => ({ notifyNewTask: vi.fn() }),
}));

const OPSI = {
    departemen: ['Produksi'],
    divisi: ['Operasional'],
    lokasi: ['Pusat'],
    jabatan: ['Staff'],
    level: ['3'],
    paket: ['APD'],
    prioritas: ['KRITIS', 'TINGGI', 'NORMAL'],
    grup_kolom: {
        identitas: 'Identitas karyawan',
        paket: 'Paket & tenggat',
        item: 'Item & dokumen',
        versi: 'Versi & status baca',
        baca: 'Jejak waktu baca',
        kuis: 'Kuis',
        atestasi: 'Bukti atestasi',
    },
};

const ORANG = [
    {
        Kode_Karyawan: 'A38',
        Nama: 'Budi',
        Departemen: 'Produksi',
        Jabatan: 'Staff',
        total_sop: 3,
        selesai: 1,
        terlambat: 1,
        persen: 33,
    },
    {
        Kode_Karyawan: 'D12',
        Nama: 'Sari',
        Departemen: 'HR',
        Jabatan: 'Admin',
        total_sop: 2,
        selesai: 2,
        terlambat: 0,
        persen: 100,
    },
];

function pasangGet({ total = 240 } = {}) {
    axios.get.mockImplementation((url) => {
        if (url.includes('/export/estimasi')) {
            return Promise.resolve({ data: { success: true, result: { total } } });
        }
        if (url.includes('/paket')) {
            return Promise.resolve({
                data: {
                    result: [
                        {
                            Id_SOP_Paket: 'p1',
                            Nama_Paket: 'APD',
                            Total_Item: 5,
                            Item_Selesai: 2,
                            Item_Perlu_Ulang: 1,
                            Persen: 40,
                        },
                    ],
                },
            });
        }

        return Promise.resolve({ data: { result: ORANG, page: 1, total_page: 3, total_data: 42 } });
    });
}

function panggilan(fragmen) {
    return axios.get.mock.calls.filter(([url]) => url.includes(fragmen));
}

/** Khusus daftar karyawan — `/export/karyawan/{kode}/paket` juga memuat '/karyawan'. */
function panggilanDaftar() {
    return axios.get.mock.calls.filter(([url]) => url.endsWith('/karyawan'));
}

async function pasang(props = {}) {
    const w = mount(SopExportStudio, {
        props: { show: false, filterOptions: OPSI, filterAwal: {}, ...props },
        global: {
            stubs: {
                SopModal: { template: '<div><slot /><slot name="footer" /></div>' },
                FilterPanel: true,
            },
        },
    });
    await w.setProps({ show: true });
    await flushPromises();

    return w;
}

/** Debounce estimasi 250ms — tunggu sedikit lebih lama, lalu biarkan promise selesai. */
async function tungguEstimasi() {
    await new Promise((r) => setTimeout(r, 300));
    await flushPromises();
}

beforeEach(() => {
    vi.clearAllMocks();
    axios.post.mockResolvedValue({ data: { success: true, message: 'ok' } });
    pasangGet();
});

describe('SopExportStudio — cakupan yang dikirim ke server', () => {
    it('tanpa centang, tidak mengirim daftar kode sama sekali', async () => {
        await pasang();

        const [, opsi] = panggilan('/export/estimasi').at(-1);

        // Keadaan default BERARTI "semua yang lolos saringan". Mengirim daftar
        // kode di sini akan diam-diam mempersempit ke halaman yang kebetulan
        // sedang tampil.
        expect(opsi.params.kodes).toBeUndefined();
    });

    it('mencentang orang mempersempit ke orang itu saja', async () => {
        const w = await pasang();

        w.vm.togglePilih('A38');
        await tungguEstimasi();

        expect(panggilan('/export/estimasi').at(-1)[1].params.kodes).toEqual(['A38']);
    });

    it('daftar kandidat tidak ikut dipersempit oleh centang', async () => {
        const w = await pasang();

        w.vm.togglePilih('A38');
        await w.vm.muatOrang(1);

        // Kalau daftar ikut disempitkan, orang yang sudah dicentang menutupi
        // kandidat lain dan pilihan tak bisa lagi diubah.
        expect(panggilanDaftar().at(-1)[1].params.kodes).toBeUndefined();
    });

    it('mengirim saringan efektif saat export diantre', async () => {
        const w = await pasang({ filterAwal: { status: 'belum' } });

        w.vm.togglePilih('D12');
        await w.vm.kirim();

        const [url, body] = axios.post.mock.calls.at(-1);
        expect(url).toContain('/export/request');
        expect(body.jenis).toBe('rinci');
        expect(body.filters.status).toBe('belum');
        expect(body.filters.kodes).toEqual(['D12']);
    });
});

describe('SopExportStudio — saringan', () => {
    it('memulai dari saringan yang sedang tampil di layar', async () => {
        const w = await pasang({ filterAwal: { status: 'terlambat', dept: ['Produksi'], q: 'budi' } });

        expect(w.vm.saringan.status).toBe('terlambat');
        expect(w.vm.saringan.dept).toEqual(['Produksi']);
        expect(w.vm.saringan.search).toBe('budi');
        expect(panggilanDaftar().at(-1)[1].params.dept).toEqual(['Produksi']);
    });

    it('menerjemahkan rentang tanggal layar menjadi satu kolom rentang', async () => {
        const w = await pasang({ filterAwal: { date_start: '2026-01-01', date_end: '2026-03-31' } });

        expect(w.vm.saringan.date_range).toEqual(['2026-01-01', '2026-03-31']);
        expect(w.vm.params.date_start).toBe('2026-01-01');
    });

    it('saringan cepat memasang nilai yang memang ada, bukan semantik baru', async () => {
        const w = await pasang();

        w.vm.pakaiCepat(w.vm.cepat.find((c) => c.key === 'telat'));

        expect(w.vm.saringan.status).toBe('terlambat');
        expect(w.vm.cepatAktif).toBe('telat');
    });

    it('menekan saringan cepat yang aktif mematikannya lagi', async () => {
        const w = await pasang();
        const c = w.vm.cepat.find((x) => x.key === 'kritis');

        w.vm.pakaiCepat(c);
        w.vm.pakaiCepat(c);

        expect(w.vm.saringan.prioritas).toBe('');
        expect(w.vm.cepatAktif).toBeNull();
    });

    it('membersihkan saringan mengembalikan seluruh nilai ke keadaan kosong', async () => {
        const w = await pasang({ filterAwal: { status: 'belum', dept: ['Produksi'] } });

        w.vm.resetSaringan();

        expect(w.vm.adaSaringan).toBe(false);
        expect(w.vm.params.dept).toBeUndefined();
    });

    it('centang bertahan saat saringan berubah', async () => {
        const w = await pasang();

        w.vm.togglePilih('A38');
        w.vm.onSaringan({ status: 'belum' });

        // Centang di-key oleh kode karyawan, jadi tetap sah walau daftar berubah.
        expect(w.vm.terpilih).toEqual(['A38']);
    });
});

describe('SopExportStudio — pilihan kolom', () => {
    it('semua kelompok kolom menyala saat studio dibuka', async () => {
        const w = await pasang();

        expect(w.vm.kolomAktif).toEqual(Object.keys(OPSI.grup_kolom));
    });

    it('mematikan satu kelompok mengeluarkannya dari permintaan', async () => {
        const w = await pasang();

        w.vm.toggleKolom('atestasi');
        await w.vm.kirim();

        expect(axios.post.mock.calls.at(-1)[1].kolom).not.toContain('atestasi');
    });

    it('menolak mematikan kelompok terakhir yang tersisa', async () => {
        const w = await pasang();

        Object.keys(OPSI.grup_kolom).forEach((k) => w.vm.toggleKolom(k));

        // Berkas tanpa kolom apa pun selain nomor urut tak ada gunanya.
        expect(w.vm.kolomAktif.length).toBe(1);
    });

    it('tidak mengirim pilihan kolom untuk jenis yang tak memakainya', async () => {
        const w = await pasang();

        await w.vm.pilihJenis('coverage');
        await w.vm.kirim();

        expect(axios.post.mock.calls.at(-1)[1].kolom).toEqual([]);
    });
});

describe('SopExportStudio — penelusuran orang', () => {
    it('membuka satu orang memuat paketnya sekali saja', async () => {
        const w = await pasang();

        await w.vm.toggleBuka(ORANG[0]);
        await w.vm.toggleBuka(ORANG[0]);
        await w.vm.toggleBuka(ORANG[0]);
        await flushPromises();

        expect(panggilan('/paket').length).toBe(1);
    });

    it('menampilkan jumlah item paket, bukan hanya nama paketnya', async () => {
        const w = await pasang();

        await w.vm.toggleBuka(ORANG[0]);
        await flushPromises();

        expect(w.find('.xs-paket-sub').text()).toContain('2/5 item');
    });

    it('menampilkan satu baris per paket, dengan namanya', async () => {
        const w = await pasang();

        await w.vm.toggleBuka(ORANG[0]);
        await flushPromises();

        expect(w.findAll('.xs-paket-baris').length).toBe(1);
        expect(w.find('.xs-paket-nama').text()).toBe('APD');
    });

    it('daftar paket dihitung dengan saringan yang sama dengan angka di kepala kartu', async () => {
        const w = await pasang({ filterAwal: { prioritas: 'KRITIS' } });

        await w.vm.toggleBuka(ORANG[0]);
        await flushPromises();

        // Angka ringkas di kepala kartu dihitung server DENGAN saringan. Kalau
        // daftar di bawahnya diambil tanpa saringan, keduanya bicara tentang
        // himpunan berbeda — kepala menyebut 1 SOP, isinya memperlihatkan 4.
        const [url, opsi] = panggilan('/paket').at(-1);
        expect(url).toContain('/export/karyawan/');
        expect(opsi.params.prioritas).toBe('KRITIS');
    });

    it('membuang simpanan paket saat saringan berubah', async () => {
        const w = await pasang();

        await w.vm.toggleBuka(ORANG[0]);
        await flushPromises();
        w.vm.onSaringan({ prioritas: 'KRITIS' });

        // Isinya dihitung menurut saringan lama; menahannya berarti menampilkan
        // lingkup yang sudah tak berlaku.
        expect(w.vm.paketCache).toEqual({});
    });

    it('tidak menyatakan kosong dan berisi sekaligus', async () => {
        const w = await pasang();

        await w.vm.toggleBuka(ORANG[0]);
        await flushPromises();

        expect(w.text()).not.toContain('Tidak ada paket aktif');
    });

    it('mengatakan kosong saat karyawan memang tak punya paket', async () => {
        axios.get.mockImplementation((url) => {
            if (url.includes('/export/estimasi'))
                return Promise.resolve({ data: { success: true, result: { total: 5 } } });
            if (url.includes('/paket')) return Promise.resolve({ data: { result: [] } });

            return Promise.resolve({ data: { result: ORANG, page: 1, total_page: 1, total_data: 2 } });
        });
        const w = await pasang();

        await w.vm.toggleBuka(ORANG[0]);
        await flushPromises();

        expect(w.text()).toContain('Tidak ada paket aktif');
        expect(w.findAll('.xs-paket-baris').length).toBe(0);
    });

    it('mencentang halaman ini menambah semua yang tampil', async () => {
        const w = await pasang();

        w.vm.pilihHalaman();

        expect(w.vm.terpilih).toEqual(['A38', 'D12']);
    });

    it('memperingatkan saat hasil melampaui plafon baris', async () => {
        pasangGet({ total: 50001 });
        const w = await pasang();

        // Pemotongan diam-diam adalah mode gagal terburuk pada export: isinya
        // tetap terbaca masuk akal.
        expect(w.find('.xs-peringatan').exists()).toBe(true);
    });

    it('tombol export mati saat tak ada satu pun baris', async () => {
        pasangGet({ total: 0 });
        const w = await pasang();

        const tombol = w.findAll('button').find((b) => b.text().includes('Export'));
        expect(tombol.attributes('disabled')).toBeDefined();
    });
});
