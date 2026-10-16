import { shallowMount } from '@vue/test-utils';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import { ElMessage } from 'element-plus';
import PermitDigitalPass from './PermitDigitalPass.vue';

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
    },
}));

vi.mock('element-plus', () => ({
    ElMessage: {
        error: vi.fn(),
        success: vi.fn(),
        warning: vi.fn(),
        info: vi.fn(),
    },
}));

const mockPermits = [
    {
        Id: 101,
        Id_Transaksi_Permit: 101,
        No_Transaksi: 'PMT-20260824-0001',
        No_Plat_Kendaraan: 'B 1234 XYZ',
        Nama_Vehicle_Type: 'Mobil',
        Nama_Model_Manual: 'Toyota Avanza',
        Tahun_Kendaraan: 2022,
        Warna_Manual: 'Hitam Metalik',
        Nama_Kategori: 'Permit Merah',
        Deskripsi_Kategori: 'Akses parkir utama',
        Kode_Warna: '#dc2626',
        Tanggal_Pengajuan: '2026-08-24 10:00:00',
        Tanggal_Berlaku: '2026-08-24',
        Tanggal_Kadaluarsa: '2027-08-24',
        Sisa_Hari: 365,
        Status_Permit: 'APPROVED',
        Nama_Pemohon: 'Budi Santoso',
        Departemen: 'Teknologi Informasi',
        Kode_Karyawan_Pemohon: 'EMP001',
        Approved_At: '2026-08-24 12:00:00',
    },
    {
        Id: 102,
        Id_Transaksi_Permit: 102,
        No_Transaksi: 'PMT-20260824-0002',
        No_Plat_Kendaraan: 'D 5678 ABC',
        Nama_Vehicle_Type: 'Sepeda Motor',
        Nama_Model_Manual: 'Honda Vario 160',
        Tahun_Kendaraan: 2023,
        Warna_Manual: 'Putih',
        Nama_Kategori: 'Permit Hijau',
        Deskripsi_Kategori: 'Akses area motor',
        Kode_Warna: '#16a34a',
        Tanggal_Pengajuan: '2026-08-24 11:30:00',
        Tanggal_Berlaku: null,
        Tanggal_Kadaluarsa: null,
        Sisa_Hari: null,
        Status_Permit: 'PENDING',
        Nama_Pemohon: 'Budi Santoso',
        Departemen: 'Teknologi Informasi',
        Kode_Karyawan_Pemohon: 'EMP001',
    },
];

describe('PermitDigitalPass Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockResolvedValue({
            data: {
                success: true,
                result: mockPermits,
            },
        });
    });

    it('renders active approved permit as a digital badge card', async () => {
        const wrapper = shallowMount(PermitDigitalPass, {
            props: {
                permitsData: mockPermits,
                user: { Nama: 'Budi Santoso', Kode_Karyawan: 'EMP001' },
            },
        });

        expect(wrapper.vm.activePermits.length).toBe(1);
        expect(wrapper.vm.currentPermit.No_Plat_Kendaraan).toBe('B 1234 XYZ');
        expect(wrapper.vm.getCategoryBadgeStyle(wrapper.vm.currentPermit)).toEqual(
            expect.objectContaining({ color: '#dc2626' }),
        );
    });

    it('handles card flip between front and back sides', async () => {
        const wrapper = shallowMount(PermitDigitalPass, {
            props: {
                permitsData: mockPermits,
            },
        });

        expect(wrapper.vm.isFlipped).toBe(false);
        wrapper.vm.isFlipped = true;
        expect(wrapper.vm.isFlipped).toBe(true);
    });

    it('tidak lagi punya jalur cetak sendiri', () => {
        // Cetak dipindah seluruhnya ke Bukti Transaksi di modal Detail Permit.
        // Komponen ini tidak boleh menumbuhkan window.print() lagi: aturan
        // @media print miliknya sudah dibuang, jadi yang tercetak akan menjadi
        // seluruh halaman aplikasi.
        const wrapper = shallowMount(PermitDigitalPass, {
            props: {
                permitsData: mockPermits,
            },
        });

        expect(wrapper.vm.triggerPrintPass).toBeUndefined();
        expect(wrapper.text()).not.toContain('Cetak Pass Dashboard');
    });

    it('emits view-detail when detail action is triggered', async () => {
        const wrapper = shallowMount(PermitDigitalPass, {
            props: {
                permitsData: mockPermits,
            },
        });

        wrapper.vm.emitViewDetail();
        expect(wrapper.emitted('view-detail')).toBeTruthy();
        expect(wrapper.emitted('view-detail')[0][0].No_Transaksi).toBe('PMT-20260824-0001');
    });

    it('renders empty state when there are no active approved permits', async () => {
        const wrapper = shallowMount(PermitDigitalPass, {
            props: {
                permitsData: [mockPermits[1]], // only PENDING permit
            },
        });

        expect(wrapper.vm.activePermits.length).toBe(0);
        expect(wrapper.vm.currentPermit).toEqual({});
        expect(wrapper.find('.permit-empty-pass-wrapper').exists()).toBe(true);
    });
});

/**
 * Dek pass hanya memuat permit yang masih hidup.
 *
 * Sebab pencabutan dilaporkan pada kartu permitnya sendiri di daftar status
 * (permit-revoked-box di PermitStatusList), bukan sebagai pemberitahuan yang
 * menumpang di atas dek. Jadi yang dijaga di sini: yang dicabut tidak boleh
 * bocor jadi pass aktif, dan tidak ada widget pencabutan yang tumbuh lagi
 * di komponen ini.
 */
describe('PermitDigitalPass — permit dicabut', () => {
    const DICABUT = {
        Id: 191,
        Id_Transaksi_Permit: 191,
        No_Transaksi: 'PMT-RVK-001',
        No_Plat_Kendaraan: 'BG 1102 AA',
        Status_Permit: 'REVOKED',
        Tanggal_Berlaku: '2026-08-01',
        Tanggal_Kadaluarsa: '2026-12-01',
        Alasan_Pencabutan: 'Kendaraan sudah tidak dipakai di area pabrik.',
        Dicabut_Oleh: 'Admin SHE',
        Dicabut_Pada: '2026-08-26',
    };

    function pasang(permitsData) {
        return shallowMount(PermitDigitalPass, {
            props: { permitsData, user: { Nama: 'Budi Santoso' } },
        });
    }

    it('tidak menampilkan kartu dicabut sebagai pass aktif', () => {
        const wrapper = pasang([DICABUT]);

        expect(wrapper.vm.activePermits).toHaveLength(0);
        expect(wrapper.vm.revokedPermits).toHaveLength(1);
    });

    it('pengajuan kedaluwarsa tidak tampil sebagai jadwal inspeksi mendatang', () => {
        const inspeksi = { status: 'TIDAK_HADIR', wajib_pilih_ulang: false, kedaluwarsa: true };
        const menunggu = { Id_Transaksi_Permit: 301, No_Transaksi: 'PMT-TNG-001', Flag_Approval: null, Status_Permit: 'PENDING', inspeksi: { status: 'TERJADWAL' } };
        const wrapper = pasang([
            { Id_Transaksi_Permit: 300, No_Transaksi: 'PMT-EXP-001', Flag_Approval: null, Status_Permit: 'EXPIRED', inspeksi },
            menunggu,
        ]);

        expect(wrapper.vm.scheduledPermits.map((p) => p.No_Transaksi)).toEqual(['PMT-TNG-001']);
    });

    it('memisahkan yang dicabut dari yang masih aktif', () => {
        const wrapper = pasang([DICABUT, ...mockPermits]);

        expect(wrapper.vm.activePermits.map((p) => p.No_Transaksi)).not.toContain('PMT-RVK-001');
        expect(wrapper.vm.revokedPermits.map((p) => p.No_Transaksi)).toEqual(['PMT-RVK-001']);
    });

    /*
     * Keadaan kosong tidak boleh menuduh sebab.
     *
     * Kalimat lamanya — "Pass Kendaraan Anda Tidak Lagi Aktif / akses Anda telah
     * dicabut" — dipilih hanya dari ADA-TIDAKNYA permit dicabut, tanpa mengenal
     * waktu. Sekali seseorang pernah dicabut, kalimat itu menang selamanya:
     * pengajuan yang ditolak kemarin, yang kedaluwarsa bulan lalu, bahkan yang
     * baru dikirim pagi ini, semuanya tetap terbaca sebagai "akses Anda
     * dicabut".
     */
    it('kalimat kosongnya sama saja walau pernah ada permit dicabut', () => {
        // renderStubDefaultSlot: isi HcisCard-nya harus ikut dirender, sebab
        // keadaan kosong itu duduk di dalam slot kartunya.
        const pasangDalam = (data) => shallowMount(PermitDigitalPass, {
            props: { permitsData: data, user: { Nama: 'Budi Santoso' } },
            global: { renderStubDefaultSlot: true },
        });

        const kosong = pasangDalam([]);
        const pernahDicabut = pasangDalam([DICABUT]);
        const judul = (w) => w.findComponent({ name: 'HcisEmptyState' }).props('title');

        expect(judul(pernahDicabut)).toBe(judul(kosong));
        expect(judul(kosong)).toMatch(/tidak ada pass/i);
        expect(pernahDicabut.findComponent({ name: 'HcisEmptyState' }).props('description'))
            .not.toMatch(/akses permit anda telah dicabut/i);
    });

    it('tidak menumbuhkan widget pencabutan di atas dek pass', () => {
        const wrapper = pasang([DICABUT, ...mockPermits]);

        expect(wrapper.vm.revokedPermits).toHaveLength(1);
        expect(wrapper.find('.permit-revoked-notice').exists()).toBe(false);
        expect(wrapper.html()).not.toContain('Kendaraan sudah tidak dipakai di area pabrik.');
    });
});

/**
 * Pencabutan satu kartu tidak boleh menutup kartu yang lain.
 *
 * Uji "dicabut" di atas hanya memeriksa nilai computed, sehingga tetap hijau
 * meskipun dek kartunya sama sekali tidak dirender. Uji di sini memeriksa
 * EFEKNYA di layar: keberadaan dek aktif dan keadaan kosong.
 */
describe('PermitDigitalPass — pencabutan tidak menutup kartu lain', () => {
    const AKTIF_A = {
        Id: 201,
        Id_Transaksi_Permit: 201,
        No_Transaksi: 'PMT-AKTIF-A',
        No_Plat_Kendaraan: 'B 1111 AA',
        Status_Permit: 'APPROVED',
        Tanggal_Berlaku: '2026-08-01',
        Tanggal_Kadaluarsa: '2027-08-01',
    };
    const AKTIF_B = {
        Id: 202,
        Id_Transaksi_Permit: 202,
        No_Transaksi: 'PMT-AKTIF-B',
        No_Plat_Kendaraan: 'B 2222 BB',
        Status_Permit: 'APPROVED',
        Tanggal_Berlaku: '2026-08-01',
        Tanggal_Kadaluarsa: '2027-08-01',
    };
    const DICABUT = {
        Id: 203,
        Id_Transaksi_Permit: 203,
        No_Transaksi: 'PMT-DICABUT',
        No_Plat_Kendaraan: 'B 3333 CC',
        Status_Permit: 'REVOKED',
        Alasan_Pencabutan: 'Kendaraan sudah dijual.',
        Dicabut_Oleh: 'Admin SHE',
        Dicabut_Pada: '2026-08-26',
    };

    function pasang(permitsData) {
        return shallowMount(PermitDigitalPass, {
            props: { permitsData, user: { Nama: 'Budi Santoso' } },
        });
    }

    it('tetap merender dek pass ketika satu dari dua kartu dicabut', () => {
        const wrapper = pasang([AKTIF_A, AKTIF_B, DICABUT]);

        expect(wrapper.vm.activePermits).toHaveLength(2);
        expect(wrapper.find('.permit-digital-pass-ready').exists()).toBe(true);
        expect(wrapper.find('.permit-empty-pass-wrapper').exists()).toBe(false);
    });

    it('merender kedua kartu aktifnya, bukan hanya yang tersisa satu', () => {
        const wrapper = pasang([AKTIF_A, AKTIF_B, DICABUT]);

        expect(wrapper.find('.permit-vehicle-deck-container').exists()).toBe(true);
        expect(wrapper.text()).toContain('2 Terdaftar');
    });

    it('menampilkan keadaan kosong ketika satu-satunya kartu dicabut', () => {
        const wrapper = pasang([DICABUT]);

        expect(wrapper.find('.permit-empty-pass-wrapper').exists()).toBe(true);
        expect(wrapper.find('.permit-digital-pass-ready').exists()).toBe(false);
    });

    it('tidak menumpuk keadaan kosong di bawah kerangka saat masih memuat', () => {
        axios.get.mockReturnValue(new Promise(() => {}));

        const wrapper = shallowMount(PermitDigitalPass, {
            props: { user: { Nama: 'Budi Santoso' } },
        });

        expect(wrapper.vm.loading).toBe(true);
        expect(wrapper.find('.permit-digital-pass-skeleton').exists()).toBe(true);
        expect(wrapper.find('.permit-empty-pass-wrapper').exists()).toBe(false);
    });
});
/**
 * Cakupan akses harus datang dari data, bukan dari teks mati.
 *
 * Kartu ini pernah memuat daftar gerbang tetap - "Gerbang Utama (Main Gate)",
 * "Gerbang Logistik" - plus lencana "LIVE CLEARANCE" yang tidak terhubung ke
 * apa pun. Tidak ada satu pun yang berasal dari database, dan untuk zona yang
 * menurut master hanya berhak sampai area parkir, kartunya justru menyatakan
 * otorisasi penuh atas seluruh portal gerbang. Salah pada kartu yang dipakai
 * di gerbang.
 *
 * Penjaga di bawah menagih isinya lewat teks yang benar-benar ter-render.
 */
describe('PermitDigitalPass - cakupan akses', () => {
    /* renderStubDefaultSlot WAJIB di sini. Sejak kartu ini diadopsi ke kit,
       blok cakupan akses bersarang di dalam slot komponen kit — dan shallowMount
       TIDAK merender slot bawaan sebuah stub. Tanpa opsi ini yang tersisa cuma
       cabang skeleton, sehingga .permit-scope-akses-text kosong dan
       .permit-scope-akses-line--kosong tak pernah ada: merah yang terbaca
       seperti "cakupan aksesnya hilang" padahal ia tergambar dengan benar. */
    const mount = (permit) =>
        shallowMount(PermitDigitalPass, {
            props: {
                permitsData: [permit],
                user: { Nama: 'Budi Santoso', Kode_Karyawan: 'EMP001' },
            },
            global: { renderStubDefaultSlot: true },
        });

    it('menampilkan cakupan akses dari master kategori', () => {
        const wrapper = mount({
            ...mockPermits[0],
            Deskripsi_Kategori: 'Akses penuh sampai Area Boiler',
        });

        expect(wrapper.find('.permit-scope-akses-text').text()).toBe('Akses penuh sampai Area Boiler');
        expect(wrapper.find('.permit-scope-akses-line--kosong').exists()).toBe(false);
    });

    it('tidak lagi menyebut nama gerbang yang tidak ada datanya', () => {
        const teks = mount(mockPermits[0]).text();

        expect(teks).not.toContain('Gerbang Utama');
        expect(teks).not.toContain('Gerbang Logistik');
        expect(teks).not.toContain('LIVE CLEARANCE');
    });

    it('tidak mengklaim akses apa pun saat master belum mengisi cakupannya', () => {
        // Diam lebih aman daripada menebak. Kartu tanpa cakupan harus terlihat
        // belum lengkap, bukan terlihat mengizinkan segalanya.
        const wrapper = mount({ ...mockPermits[0], Deskripsi_Kategori: null });

        expect(wrapper.find('.permit-scope-akses-line--kosong').exists()).toBe(true);
        expect(wrapper.find('.permit-scope-akses-text').text()).toContain('belum diisi');
    });

    it('mewarnai lencana zona dengan warna kategorinya, bukan hijau tetap', () => {
        // Lencana lama selalu hijau, sehingga zona MERAH pun terbaca aman.
        const wrapper = mount(mockPermits[0]);

        /* jsdom menormalkan hex jadi rgba(), jadi mencari '#dc2626' apa adanya
           akan gagal walaupun warnanya SUDAH benar. Yang ditagih komponennya,
           bukan ejaannya: gaya lencana harus membawa warna kategori (220,38,38)
           dan TIDAK boleh jatuh ke hijau tetap 16,185,129 (#10b981) yang dulu
           membuat zona MERAH pun terbaca aman. */
        const gaya = wrapper.find('.permit-scope-zona-badge').attributes('style');

        expect(gaya).toMatch(/220,\s*38,\s*38/);
        expect(gaya, 'lencana jatuh ke hijau tetap').not.toMatch(/16,\s*185,\s*129/);
    });

    it('tidak menampilkan nama, model, atau warna contoh saat datanya kosong', () => {
        // Sisa mockup: 'Budi Santoso', 'Toyota Avanza', 'Hitam'. Nilai contoh
        // pada kartu gerbang terbaca sebagai identitas sungguhan.
        const wrapper = shallowMount(PermitDigitalPass, {
            props: {
                permitsData: [{
                    ...mockPermits[0],
                    Nama_Pemohon: null,
                    Nama_Model_Manual: null,
                    Nama_Vehicle_Type: null,
                    Warna_Manual: null,
                }],
                user: {},
            },
        });

        const teks = wrapper.text();

        expect(teks).not.toContain('Budi Santoso');
        expect(teks).not.toContain('Toyota Avanza');
        expect(teks).not.toContain('Toyota Innova Zenix');
    });
});

describe('PermitDigitalPass — kartu dashboard (PDF)', () => {
    it('meminta PDF kartu ke server lalu membukanya di file viewer', async () => {
        const blob = new Blob(['%PDF-1.4'], { type: 'application/pdf' });
        axios.get.mockResolvedValueOnce({ data: blob });
        // jsdom tidak punya URL.createObjectURL sama sekali, jadi spyOn gagal
        // sebelum sempat memasang tiruannya. Dipasang dulu, baru ditiru, lalu
        // dilepas lagi supaya tidak bocor ke uji lain (pola HcisFileViewer.test).
        const tadinyaTidakAda = typeof URL.createObjectURL !== 'function';
        if (tadinyaTidakAda) URL.createObjectURL = () => '';
        const objectUrl = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:kartu');

        try {
            const wrapper = shallowMount(PermitDigitalPass, { props: { permitsData: [{ ...mockPermits[0] }] } });
            await wrapper.vm.bukaKartuPdf();

            // responseType blob WAJIB: tanpa itu PDF-nya rusak jadi teks.
            expect(axios.get).toHaveBeenLastCalledWith('/transaksipermit/101/kartu-pdf', { responseType: 'blob' });
            expect(objectUrl).toHaveBeenCalledWith(blob);
            expect(wrapper.vm.showKartuViewer).toBe(true);
            expect(wrapper.vm.kartuFiles[0]).toMatchObject({ url: 'blob:kartu', ext: 'pdf', allowDownload: true });
            expect(wrapper.vm.kartuFiles[0].name).toBe('kartu-permit-B1234XYZ.pdf');
            expect(wrapper.vm.kartuLoading).toBe(false);
        } finally {
            objectUrl.mockRestore();
            if (tadinyaTidakAda) delete URL.createObjectURL;
        }
    });

    it('penolakan server dibaca dari blob, bukan viewer kosong', async () => {
        const pesan = new Blob([JSON.stringify({ message: 'Kartu hanya bisa dicetak untuk permit yang masih aktif.' })]);
        axios.get.mockRejectedValueOnce({ response: { status: 422, data: pesan } });

        const wrapper = shallowMount(PermitDigitalPass, { props: { permitsData: [{ ...mockPermits[0] }] } });
        await wrapper.vm.bukaKartuPdf();

        expect(wrapper.vm.showKartuViewer).toBe(false);
        expect(ElMessage.error).toHaveBeenCalledWith('Kartu hanya bisa dicetak untuk permit yang masih aktif.');
    });

    it('tidak mencetak langsung dari kartu layar', () => {
        // window.print() dari sini akan mencetak seluruh halaman; kartunya
        // sekarang PDF server yang dibuka di viewer.
        const sumber = readFileSync(
            path.resolve(process.cwd(), 'resources/js/Pages/VISITOR/Permit/VehiclePermit/components/PermitDigitalPass.vue'),
            'utf8',
        );

        expect(sumber).not.toMatch(/window\.print\(\)\s*;/);
        expect(sumber).toContain('<HcisFileViewer');
        expect(sumber).toContain('/kartu-pdf');
    });
});
