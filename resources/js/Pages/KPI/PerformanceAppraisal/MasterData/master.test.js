import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MasterPage from './master.vue';

const paApiMock = vi.hoisted(() => ({
    masterReferensi: vi.fn(),
    masterDaftar: vi.fn(),
    masterDetail: vi.fn(),
    masterSimpan: vi.fn(),
    masterPerbarui: vi.fn(),
    masterNonaktif: vi.fn(),
    simpanRincianAturan: vi.fn(),
    aktifkanAturan: vi.fn(),
}));

vi.mock('../api/paApi.js', () => ({
    default: paApiMock,
}));

const referensi = {
    level: [{ ID_Level: 2, Nama_Level: 'STAFF' }],
    jenis_kompetensi: [{ Id_PA_Competency_Type: 1, Nama_Type: 'Manajerial' }],
    kategori_penilai: [],
    jenis_relasi: [],
    status_aturan: [],
    sumbu: [{ Kode_Type: 'FINAL', Nama_Type: 'Final' }],
    kegunaan: [{ Kode_Usage: 'GRADE', Nama_Usage: 'Grade' }],
    kompetensi: [],
    jenis_catatan: [],
    siklus: [],
};

const uiStubs = {
    HcisBadge: {
        props: ['label'],
        template: '<span class="badge-stub">{{ label }}</span>',
    },
    HcisButton: {
        props: ['label', 'href', 'disabled'],
        template: '<a v-if="href" :href="href">{{ label }}</a><button v-else :disabled="disabled">{{ label }}</button>',
    },
    HcisCard: { template: '<article><slot name="header" /><slot /></article>' },
    HcisDialog: {
        props: ['modelValue'],
        template: '<div v-if="modelValue"><slot /><slot name="footer" /></div>',
    },
    HcisField: {
        props: ['modelValue', 'label'],
        emits: ['update:modelValue'],
        template: '<label><span>{{ label }}</span><input :value="modelValue" /></label>',
    },
    // Slot aksi ikut dirender: tombol tambah tiap tampilan master kini tinggal di bilah saringan.
    HcisFilterBar: { template: '<div class="filter-bar-stub"><slot name="actions" /></div>' },
    HcisHeader: { template: '<header><slot name="actions" /><slot name="console" /></header>' },
    HcisIcon: { template: '<i />' },
    HcisResultState: {
        props: ['title', 'description', 'primaryText'],
        template: '<div class="result-state"><strong>{{ title }}</strong><span>{{ description }}</span></div>',
    },
    HcisStatusBadge: {
        props: ['label', 'status'],
        template: '<span class="status-badge-stub">{{ label || status }}</span>',
    },
    HcisTable: { template: '<div class="table-stub"><slot name="empty" /></div>' },
    PaMasterEditorDialog: true,
    PaMasterDetailDialog: true,
    PaPolicyDetailDialog: true,
};

function mountPage(pathname) {
    window.history.replaceState({}, '', pathname);
    return mount(MasterPage, { global: { stubs: uiStubs } });
}

beforeEach(() => {
    paApiMock.masterReferensi.mockResolvedValue(referensi);
    paApiMock.masterDaftar.mockReset();
    window.history.replaceState({}, '', '/');
});

describe('Performance Appraisal admin master page', () => {
    it('renders the dedicated administrator landing catalog', async () => {
        const wrapper = mountPage('/performance-appraisal/master');
        await flushPromises();

        expect(wrapper.text()).toContain('ADMINISTRATOR PA');
        expect(wrapper.text()).not.toContain('Administrator Performance Appraisal');
        expect(wrapper.text()).toContain('Jenis Kompetensi');
        expect(wrapper.find('a[href="/performance-appraisal/master/pita-nilai"]').exists()).toBe(true);
        expect(paApiMock.masterDaftar).not.toHaveBeenCalled();
    });

    it('loads a selected master and honors server operation permissions', async () => {
        paApiMock.masterDaftar.mockResolvedValue({
            master: 'kategori-penilai',
            nama: 'Kategori Penilai',
            lingkup: 'master',
            dapat_ditambah: false,
            dapat_dinonaktifkan: false,
            baris: [
                {
                    Id_PA_Rater_Category: 1,
                    Kode_Kategori: 'ATASAN',
                    Nama_Kategori: 'Atasan',
                    Deskripsi: 'Rater atasan',
                    Flag_Aktif: 'Y',
                },
            ],
            halaman: 1,
            per_halaman: 25,
            total: 1,
            total_halaman: 1,
            dipangkas: false,
        });

        const wrapper = mountPage('/performance-appraisal/master/kategori-penilai');
        await flushPromises();

        expect(paApiMock.masterDaftar).toHaveBeenCalledWith(
            'kategori-penilai',
            expect.objectContaining({ halaman: 1 }),
        );
        expect(wrapper.text()).toContain('Atasan');
        expect(wrapper.find('.pa-master-filter-bar').exists()).toBe(true);
        expect(wrapper.find('.pa-master-detail-panel').exists()).toBe(false);
        const buttonLabels = wrapper.findAll('button').map((button) => button.text());
        expect(buttonLabels).not.toContain('Tambah data');
        expect(buttonLabels).not.toContain('Nonaktifkan');
    });

    it('keeps empty and error states separate', async () => {
        paApiMock.masterDaftar.mockResolvedValueOnce({
            master: 'kompetensi',
            nama: 'Kompetensi',
            lingkup: 'master',
            dapat_ditambah: true,
            dapat_dinonaktifkan: true,
            baris: [],
            halaman: 1,
            per_halaman: 25,
            total: 0,
            total_halaman: 1,
            dipangkas: false,
        });
        const emptyWrapper = mountPage('/performance-appraisal/master/kompetensi');
        await flushPromises();
        expect(emptyWrapper.text()).toContain('Belum Ada Data Kompetensi');
        expect(emptyWrapper.text()).not.toContain('Gagal memuat daftar kompetensi');

        paApiMock.masterDaftar.mockRejectedValueOnce(new Error('Master kompetensi tidak tersedia.'));
        const errorWrapper = mountPage('/performance-appraisal/master/kompetensi');
        await flushPromises();
        expect(errorWrapper.text()).toContain('Gagal memuat daftar kompetensi');
        expect(errorWrapper.text()).toContain('Master kompetensi tidak tersedia.');
    });

    it('renders specialized Bobot Pilar view with dual proportion bars and level hierarchy', async () => {
        paApiMock.masterDaftar.mockResolvedValueOnce({
            master: 'bobot-pilar',
            nama: 'Bobot Pilar',
            lingkup: 'master',
            dapat_ditambah: true,
            dapat_dinonaktifkan: false,
            baris: [
                {
                    Id_PA_Pillar_Weight: 10,
                    ID_Level: 2,
                    Bobot_KPI: 80,
                    Bobot_Kompetensi: 20,
                    Catatan: 'Level Staff 80/20',
                },
            ],
            halaman: 1,
            per_halaman: 25,
            total: 1,
            total_halaman: 1,
            dipangkas: false,
        });

        const wrapper = mountPage('/performance-appraisal/master/bobot-pilar');
        await flushPromises();

        expect(paApiMock.masterDaftar).toHaveBeenCalledWith(
            'bobot-pilar',
            expect.objectContaining({ halaman: 1 }),
        );
        expect(wrapper.text()).toContain('TANGGA HIERARKI BOBOT KARIR');
        expect(wrapper.text()).toContain('STAFF');
        /* Sejak tata letak responsif, label dan angkanya dipisah ke dua elemen, jadi teksnya
           terbaca "KPI80%". Proporsinya diperiksa lewat batang tangga yang menggambarnya. */
        expect(wrapper.find('.pa-ladder-seg--kpi').attributes('title')).toBe('KPI 80%');
        expect(wrapper.find('.pa-ladder-seg--komp').attributes('title')).toBe('Kompetensi 20%');
        expect(wrapper.find('.pa-ladder-seg--kpi').attributes('style')).toContain('width: 80%');
        expect(wrapper.text()).toContain('Terkonfigurasi');
    });

    it('renders specialized Rentang Nilai view with category tabs and score spectrum continuum', async () => {
        paApiMock.masterDaftar.mockResolvedValueOnce({
            master: 'pita-nilai',
            nama: 'Rentang Nilai',
            lingkup: 'master',
            dapat_ditambah: true,
            dapat_dinonaktifkan: true,
            baris: [
                {
                    Id_PA_Score_Range: 1,
                    Score_Type: 'FINAL',
                    Score_Usage: 'GRADE',
                    Kode_Range: 'A',
                    Nama_Range: 'Istimewa',
                    Nilai_Dari: 90,
                    Nilai_Sampai: 999.99,
                    Nilai_Hasil: null,
                    Target_Persen: 10,
                    Warna: '#16a34a',
                    Urutan_Tampil: 1,
                    Flag_Aktif: 'Y',
                },
                {
                    Id_PA_Score_Range: 2,
                    Score_Type: 'FINAL',
                    Score_Usage: 'GRADE',
                    Kode_Range: 'B',
                    Nama_Range: 'Baik',
                    Nilai_Dari: 0,
                    Nilai_Sampai: 90,
                    Nilai_Hasil: null,
                    Target_Persen: 90,
                    Warna: '#4f46e5',
                    Urutan_Tampil: 2,
                    Flag_Aktif: 'Y',
                },
                {
                    Id_PA_Score_Range: 3, Score_Type: 'KPI', Score_Usage: 'HAVMAP', Kode_Range: 'KPI_TINGGI',
                    Nama_Range: 'Capaian Tinggi', Nilai_Dari: 80, Nilai_Sampai: 999.99, Nilai_Hasil: 3,
                    Target_Persen: null, Warna: '#16a34a', Urutan_Tampil: 1, Flag_Aktif: 'Y',
                },
                {
                    Id_PA_Score_Range: 4, Score_Type: 'KPI', Score_Usage: 'HAVMAP', Kode_Range: 'KPI_RENDAH',
                    Nama_Range: 'Capaian Rendah', Nilai_Dari: 0, Nilai_Sampai: 80, Nilai_Hasil: 1,
                    Target_Persen: null, Warna: '#ef4444', Urutan_Tampil: 2, Flag_Aktif: 'Y',
                },
            ],
            halaman: 1,
            per_halaman: 25,
            total: 2,
            total_halaman: 1,
            dipangkas: false,
        });

        const wrapper = mountPage('/performance-appraisal/master/pita-nilai');
        await flushPromises();

        expect(paApiMock.masterDaftar).toHaveBeenCalledWith(
            'pita-nilai',
            expect.objectContaining({ halaman: 1 }),
        );
        /* Kategori Grade disembunyikan (UAT 8 Okt): halaman membuka HAV Map · Capaian KPI, dan
           baris FINAL • GRADE tidak tampil di tab mana pun. */
        // Spektrum menampilkan kode pita: hanya pita HAV KPI, tanpa grade A/B.
        expect(wrapper.text()).toContain('KPI_TINGGI');
        expect(wrapper.text()).toContain('Rentang Nilai');
        expect(wrapper.text()).not.toContain('Grade Nilai Akhir');
        expect(wrapper.find('.pa-range-spectrum-track').text()).not.toMatch(/\bA\b/);
        expect(wrapper.text()).toContain('Kontinum Sempurna');
        expect(wrapper.text()).not.toContain('Belum Valid');
    });

    it('renders specialized Aturan Penilai view with level grouped cards and policy status', async () => {
        paApiMock.masterDaftar.mockResolvedValueOnce({
            master: 'aturan-penilai',
            nama: 'Aturan Penilai',
            lingkup: 'master',
            dapat_ditambah: true,
            dapat_dinonaktifkan: true,
            baris: [
                {
                    Id_PA_Rater_Policy: 1,
                    Kode_Policy: 'POL_STAFF_2026',
                    Nama_Policy: 'Aturan Penilai Staff',
                    Deskripsi: 'Kebijakan evaluasi level staff',
                    ID_Level: 2,
                    Versi: 1,
                    Status_Policy: 'ACTIVE',
                    Berlaku_Dari: '2026-01-01',
                    Berlaku_Sampai: null,
                    Catatan_Versi: 'Versi aktif awal',
                    Flag_Aktif: 'Y',
                },
            ],
            halaman: 1,
            per_halaman: 25,
            total: 1,
            total_halaman: 1,
            dipangkas: false,
        });

        const wrapper = mountPage('/performance-appraisal/master/aturan-penilai');
        await flushPromises();

        expect(paApiMock.masterDaftar).toHaveBeenCalledWith(
            'aturan-penilai',
            expect.objectContaining({ halaman: 1 }),
        );
        expect(wrapper.text()).toContain('MATRIKS MULTI-RATER PERUSAHAAN');
        expect(wrapper.text()).toContain('STAFF');
        expect(wrapper.text()).toContain('POL_STAFF_2026');
        expect(wrapper.text()).toContain('Aktif');
        expect(wrapper.text()).toContain('v1');
    });
});
