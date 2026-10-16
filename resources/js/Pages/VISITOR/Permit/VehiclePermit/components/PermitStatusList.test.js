import { mount, shallowMount } from '@vue/test-utils';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import PermitStatusList from './PermitStatusList.vue';

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
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
        Flag_Approval: null,
        Nama_Pemohon: 'Budi Santoso',
        Departemen: 'Teknologi Informasi',
        Kode_Karyawan_Pemohon: 'EMP001',
    },
    {
        Id: 103,
        Id_Transaksi_Permit: 103,
        No_Transaksi: 'PMT-20260824-0003',
        No_Plat_Kendaraan: 'B 9999 DEF',
        Nama_Vehicle_Type: 'Mobil',
        Nama_Model_Manual: 'Honda CR-V',
        Tahun_Kendaraan: 2021,
        Warna_Manual: 'Abu-abu',
        Nama_Kategori: 'Permit Merah',
        Deskripsi_Kategori: 'Akses parkir utama',
        Kode_Warna: '#dc2626',
        Tanggal_Pengajuan: '2026-08-20 09:00:00',
        Tanggal_Berlaku: null,
        Tanggal_Kadaluarsa: null,
        Sisa_Hari: null,
        Status_Permit: 'REJECTED',
        Flag_Approval: 'T',
        Alasan_Tolak: 'Berkas STNK tidak terbaca jelas.',
        Nama_Pemohon: 'Budi Santoso',
        Departemen: 'Teknologi Informasi',
        Kode_Karyawan_Pemohon: 'EMP001',
    },
];

describe('PermitStatusList design-system composition & UX', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockImplementation((url) => {
            if (url === '/transaksipermit-my') {
                return Promise.resolve({
                    data: {
                        success: true,
                        result: mockPermits,
                    },
                });
            }
            if (url.startsWith('/transaksipermit/')) {
                return Promise.resolve({
                    data: {
                        success: true,
                        result: {
                            permit: mockPermits[0],
                            dokumens: [
                                {
                                    Id_Permit_Dokumen: 1,
                                    Jenis_Dokumen: 'FOTO_KENDARAAN',
                                    File_Name: 'mobil.jpg',
                                    Path_File_URL: 'visitor/permits/PMT-20260824-0001/mobil.jpg',
                                    File_Url: '/storage/visitor/permits/PMT-20260824-0001/mobil.jpg',
                                },
                                {
                                    Id_Permit_Dokumen: 2,
                                    Jenis_Dokumen: 'STNK',
                                    File_Name: 'stnk.pdf',
                                    Path_File_URL: 'visitor/permits/PMT-20260824-0001/stnk.pdf',
                                    File_Url: '/storage/visitor/permits/PMT-20260824-0001/stnk.pdf',
                                },
                            ],
                            approvals: [
                                {
                                    Id_Approval_Request: 1,
                                    Nama_Approver: 'Jane Doe',
                                    Nama_Approval: 'Verifikasi GA',
                                    Flag_Approval: 'Y',
                                    Tanggal_Approval: '2026-08-24 10:15:00',
                                    Catatan: 'Disetujui',
                                },
                            ],
                            checklists: [],
                        },
                    },
                });
            }
            return Promise.reject(new Error('URL not mocked: ' + url));
        });
    });

    it('uses official HCIS components in PermitStatusList.vue template', () => {
        const source = readFileSync(
            path.resolve(
                process.cwd(),
                'resources/js/Pages/VISITOR/Permit/VehiclePermit/components/PermitStatusList.vue',
            ),
            'utf8',
        );

        expect(source).toContain('<HcisDataTable');
        expect(source).toContain('<HcisBadge');
        expect(source).toContain('<HcisDialog');
        expect(source).toContain('<HcisDualPaneDialog');
        expect(source).toContain('<HcisCard');
        expect(source).toContain('<HcisTimeline');
        expect(source).toContain('<HcisFileViewer');
        expect(source).toContain('<HcisEmptyState');
        expect(source).toContain('permit-plate-badge');
        expect(source).toContain('permit-category-pill');
        expect(source).not.toContain('modernFilter');
        expect(source).not.toContain('ModernDataTable');
    });

    it('fetches permit list and calculates quick filter chips with counts', async () => {
        const wrapper = shallowMount(PermitStatusList);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(axios.get).toHaveBeenCalledWith('/transaksipermit-my');
        expect(wrapper.vm.permits).toHaveLength(3);
        expect(wrapper.vm.counts).toEqual({
            total: 3,
            approved: 1,
            pending: 1,
            rejected: 1,
            expired: 0,
            revoked: 0,
        });
        expect(wrapper.vm.quickChips).toHaveLength(5);
    });

    it('filters permits by status chip and search query', async () => {
        const wrapper = shallowMount(PermitStatusList);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // Filter by APPROVED
        wrapper.vm.onChipClick({ value: 'APPROVED' });
        expect(wrapper.vm.filteredPermits).toHaveLength(1);
        expect(wrapper.vm.filteredPermits[0].No_Plat_Kendaraan).toBe('B 1234 XYZ');

        // Filter by PENDING
        wrapper.vm.onChipClick({ value: 'PENDING' });
        expect(wrapper.vm.filteredPermits).toHaveLength(1);
        expect(wrapper.vm.filteredPermits[0].No_Plat_Kendaraan).toBe('D 5678 ABC');

        // Reset to ALL and search by query
        wrapper.vm.onChipClick({ value: 'ALL' });
        wrapper.vm.onSearchUpdate('Vario');
        expect(wrapper.vm.filteredPermits).toHaveLength(1);
        expect(wrapper.vm.filteredPermits[0].Nama_Model_Manual).toBe('Honda Vario 160');

        // Reset filter
        wrapper.vm.resetFilter();
        expect(wrapper.vm.filteredPermits).toHaveLength(3);
    });

    it('loads detail data and populates timeline and documents for viewing', async () => {
        const wrapper = shallowMount(PermitStatusList);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        await wrapper.vm.viewDetail(mockPermits[0]);
        await wrapper.vm.$nextTick();

        expect(axios.get).toHaveBeenCalledWith('/transaksipermit/101');
        expect(wrapper.vm.showModal).toBe(true);
        expect(wrapper.vm.permitData).toBeTruthy();
        expect(wrapper.vm.dokumens).toHaveLength(2);
        expect(wrapper.vm.approvals).toHaveLength(1);
        expect(wrapper.vm.approvalTimelineItems).toHaveLength(1);
        expect(wrapper.vm.approvalTimelineItems[0].status).toBe('Disetujui');
        expect(wrapper.vm.approvalTimelineItems[0].sentiment).toBe('success');

        // Open in-app document viewer
        wrapper.vm.openFileViewer(wrapper.vm.dokumens[0]);
        expect(wrapper.vm.showFileViewer).toBe(true);
        expect(wrapper.vm.viewerFiles).toHaveLength(2);
    });

    it('handles permit cancellation workflow for pending permits', async () => {
        axios.post.mockResolvedValueOnce({
            data: {
                success: true,
                message: 'Pengajuan permit berhasil dibatalkan.',
            },
        });

        const wrapper = shallowMount(PermitStatusList);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // Permit 102 is PENDING
        const pendingPermit = mockPermits[1];
        expect(wrapper.vm.canCancelPermit(pendingPermit)).toBe(true);
        expect(wrapper.vm.canCancelPermit(mockPermits[0])).toBe(false);

        // Open cancel confirm
        wrapper.vm.openCancelConfirm(pendingPermit);
        expect(wrapper.vm.showCancelModal).toBe(true);
        expect(wrapper.vm.permitToCancel).toEqual(pendingPermit);

        // Confirm cancellation
        await wrapper.vm.confirmCancelPermit();
        await wrapper.vm.$nextTick();

        expect(axios.post).toHaveBeenCalledWith('/transaksipermit/102/cancel');
        expect(wrapper.vm.showCancelModal).toBe(false);
        expect(wrapper.emitted('permit-canceled')).toBeTruthy();
    });
});

/**
 * Permit yang dicabut: pemohon harus tahu, dan tahu KENAPA.
 *
 * Pencabutan berbeda arti dari penolakan — ditolak berarti pengajuannya tidak
 * pernah hidup, dicabut berarti pernah hidup lalu dihentikan. Menyamakan
 * keduanya, atau menyamakannya dengan "kadaluarsa", membuat pemohon mengira
 * tinggal menunggu perpanjangan.
 */
describe('PermitStatusList — permit dicabut', () => {
    const DICABUT = {
        Id: 91,
        Id_Transaksi_Permit: 91,
        No_Transaksi: 'PMT-RVK-001',
        Status_Permit: 'REVOKED',
        No_Plat_Kendaraan: 'BG 1102 AA',
        Tanggal_Berlaku: '2026-08-01',
        Tanggal_Kadaluarsa: '2026-12-01',
        Alasan_Pencabutan: 'Kendaraan sudah tidak dipakai di area pabrik.',
        Dicabut_Oleh: 'Admin SHE',
        Dicabut_Pada: '2026-08-26',
    };

    async function pasang(permits = [DICABUT]) {
        axios.get.mockImplementation((url) => (url === '/transaksipermit-my'
            ? Promise.resolve({ data: { success: true, result: permits } })
            : Promise.reject(new Error('URL not mocked: ' + url))));

        const wrapper = shallowMount(PermitStatusList);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        return wrapper;
    }

    it('menamainya "Dicabut", bukan Ditolak atau Kadaluarsa', async () => {
        const { vm } = await pasang();

        expect(vm.getStatusLabel('REVOKED')).toBe('Dicabut');
        expect(vm.getStatusLabel('REVOKED')).not.toBe(vm.getStatusLabel('REJECTED'));
        expect(vm.getStatusLabel('REVOKED')).not.toBe(vm.getStatusLabel('EXPIRED'));
    });

    it('tidak dihitung sebagai permit aktif', async () => {
        const { vm } = await pasang();

        expect(vm.isPermitActive(DICABUT)).toBe(false);
        expect(vm.getCardSentiment('REVOKED')).toBe('danger');
        expect(vm.counts.approved).toBe(0);
        expect(vm.counts.revoked).toBe(1);
    });

    it('pil saringan "Dicabut" hanya muncul ketika memang ada', async () => {
        // Saringan permanen yang selalu nol hanya menambah kebisingan.
        const adaPil = (wrapper) => wrapper.vm.quickChips.some((c) => c.value === 'REVOKED');

        expect(adaPil(await pasang([DICABUT]))).toBe(true);
        expect(adaPil(await pasang([{ ...DICABUT, Status_Permit: 'APPROVED' }]))).toBe(false);
    });

    it('menyaring menurut pil "Dicabut"', async () => {
        const wrapper = await pasang([DICABUT, { ...DICABUT, No_Transaksi: 'PMT-OK', Status_Permit: 'APPROVED' }]);

        wrapper.vm.onChipClick({ value: 'REVOKED' });

        expect(wrapper.vm.filteredPermits).toHaveLength(1);
        expect(wrapper.vm.filteredPermits[0].No_Transaksi).toBe('PMT-RVK-001');
    });

    /*
     * Catatan pencabutan tidak boleh lagi menyambung ke keterangan sistemnya.
     *
     * Bentuk lamanya merangkai tiga hal jadi satu paragraf: catatan bebas si
     * pencabut, lalu "Dicabut oleh X pada <tanggal>.", lalu akibatnya di
     * gerbang. Kalimat orang jarang berakhir dengan titik, jadi di layar
     * ketiganya menyatu jadi satu kalimat panjang yang seolah ditulis sistem.
     * Sekarang catatannya berdiri sendiri, apa adanya.
     */
    it('menyimpan catatan pencabutan apa adanya, tanpa disambung keterangan lain', async () => {
        const wrapper = await pasang();
        wrapper.vm.permitData = DICABUT;

        expect(wrapper.vm.alasanPencabutan).toBe(DICABUT.Alasan_Pencabutan);
        expect(wrapper.vm.alasanPencabutan).not.toContain('Admin SHE');
        expect(wrapper.vm.alasanPencabutan.toLowerCase()).not.toContain('tidak berlaku');
    });

    it('tetap punya kalimat pengganti saat alasannya tidak tercatat', async () => {
        const wrapper = await pasang();
        wrapper.vm.permitData = { ...DICABUT, Alasan_Pencabutan: null };

        expect(wrapper.vm.alasanPencabutan).toMatch(/tidak tercatat/i);
    });
});
/**
 * Cetak sekarang satu jalur: tombol "Cetak Bukti" di modal detail, yang
 * memanggil komponen Bukti Transaksi. Memanggil window.print() langsung dari
 * sini akan melewati penukaran judul dokumen dan seluruh aturan halaman, dan
 * yang tercetak menjadi seluruh aplikasi.
 */
describe('PermitStatusList - cetak bukti transaksi', () => {
    it('meneruskan perintah cetak ke komponen buktinya, bukan ke window.print', async () => {
        /* Lembar buktinya digantung `v-if="showModal && permitData"`, jadi
           $refs.buktiRef BELUM ADA pada komponen yang baru dipasang — dan Vue 3
           menolak penambahan properti ke $refs yang kosong ("object is not
           extensible"). Menyuntik ref palsu bukan lagi jalan keluarnya.

           Yang benar justru lebih dekat ke kenyataan: buka modalnya supaya
           lembarnya sungguh ter-render, lalu sediakan stub yang PUNYA cetak(). */
        const cetak = vi.fn();
        const wrapper = shallowMount(PermitStatusList, {
            global: {
                stubs: {
                    PermitBuktiTransaksi: {
                        name: 'PermitBuktiTransaksi',
                        template: '<div />',
                        methods: { cetak },
                    },
                },
            },
        });

        await wrapper.setData({ showModal: true, permitData: { No_Transaksi: 'VP-1' } });

        const printSpy = vi.spyOn(window, 'print').mockImplementation(() => {});

        wrapper.vm.cetakBukti();

        expect(cetak).toHaveBeenCalledTimes(1);
        expect(printSpy, 'window.print dipanggil langsung — cetakan akan memuat seluruh aplikasi').not.toHaveBeenCalled();
        printSpy.mockRestore();
    });

    it('tidak meledak saat lembar buktinya belum ter-render', () => {
        // Modal bisa dibuka sebelum detailnya selesai dimuat; klik cepat pada
        // tombol cetak tidak boleh melempar.
        const wrapper = shallowMount(PermitStatusList);

        expect(() => wrapper.vm.cetakBukti()).not.toThrow();
    });

    it('mencantumkan pencetak dari sesi, dengan nama pemohon sebagai cadangan', () => {
        const wrapper = shallowMount(PermitStatusList, {
            props: { user: { Nama: 'Anita' } },
        });
        expect(wrapper.vm.namaPencetak).toBe('Anita');

        const tanpaUser = shallowMount(PermitStatusList);
        tanpaUser.vm.permitData = { Nama_Pemohon: 'Hendry' };
        expect(tanpaUser.vm.namaPencetak).toBe('Hendry');
    });
});

/**
 * Izin tamu harus TERLIHAT berbeda dari izin kendaraan pribadi.
 *
 * Keduanya terdaftar atas nama orang yang sama dan tampil bersebelahan di
 * riwayat. Tanpa penanda, pemohon membaca izin tamunya sebagai izin miliknya —
 * lalu menekan Batal, atau mencarinya di Pass Digital dan menyangka hilang.
 */
describe('Riwayat permit — penanda izin milik tamu', () => {
    // Pola yang sama dengan uji di atas: path.resolve dari process.cwd(),
    // bukan __dirname — berkas uji ini dijalankan sebagai ESM.
    const sumber = readFileSync(
        path.resolve(
            process.cwd(),
            'resources/js/Pages/VISITOR/Permit/VehiclePermit/components/PermitStatusList.vue',
        ),
        'utf8',
    );

    it('mengenali izin tamu dari tautannya, bukan dari kategori permitnya', () => {
        // Kategori COKLAT juga dipakai permit karyawan biasa; mencabangkan
        // lewat kategori akan menandai izin yang salah.
        expect(sumber).toContain('dariKunjungan(item)');
        expect(sumber).toContain('item.No_Transaksi_Tamu');
        expect(sumber).not.toContain("Nama_Kategori === 'COKLAT'");
    });

    it('menandai barisnya di tampilan tabel MAUPUN kartu', () => {
        // Penanda yang hanya ada di tabel hilang begitu layarnya sempit.
        expect(sumber).toContain('permit-tamu-badge');
        expect(sumber).toContain('permit-tamu-note');
        expect(sumber).toContain('dariKunjungan(row)');
    });

    it('detailnya menampilkan asal kunjungan, senada dengan meja verifikator', () => {
        expect(sumber).toContain('Asal Pengajuan');
        expect(sumber).toContain('asalPengajuan');
        expect(sumber).toContain('No. Kunjungan');
    });

    it('tombol Batal ditutup untuk izin tamu', () => {
        // Servernya menolak; tombol yang tetap menyala hanya menghasilkan galat
        // yang tidak bisa dimengerti pemakainya.
        expect(sumber).toContain('if (this.dariKunjungan(item)) return false;');
    });
});

describe('PermitStatusList — jadwal inspeksi (DDL-2026-123)', () => {
    const INSPEKSI = {
        status: 'TERJADWAL',
        id_pos: 7,
        pos: 'Pos Utama',
        lokasi: 'Gerbang depan',
        mulai: '2026-09-21 08:00',
        selesai: '2026-09-21 08:30',
        jadwal_ulang_terpakai: 0,
        tidak_hadir: 0,
        gugur: false,
        boleh_pindah: true,
        wajib_pilih_ulang: false,
    };

    function pasangMock(inspeksi) {
        axios.get.mockImplementation((url) => {
            if (url === '/transaksipermit-my') {
                return Promise.resolve({ data: { success: true, result: mockPermits } });
            }
            if (url === '/transaksipermit-slot-inspeksi') {
                return Promise.resolve({
                    data: { success: true, result: { slot: [{ id_pos: 7, pos: 'Pos Utama', mulai: '2026-09-22 09:00', selesai: '2026-09-22 09:30', sisa: 1 }] } },
                });
            }
            if (url.startsWith('/transaksipermit/')) {
                return Promise.resolve({
                    data: { success: true, result: { permit: mockPermits[1], dokumens: [], approvals: [], checklists: [], inspeksi } },
                });
            }
            return Promise.reject(new Error('URL not mocked: ' + url));
        });
    }

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('menyimpan ringkasan inspeksi dari detail dan membersihkannya saat ditutup', async () => {
        pasangMock(INSPEKSI);
        const wrapper = shallowMount(PermitStatusList);
        await wrapper.vm.viewDetail(mockPermits[1]);

        expect(wrapper.vm.inspeksi).toEqual(INSPEKSI);
        expect(wrapper.vm.inspeksiStatus).toEqual({ label: 'Terjadwal', variant: 'info' });

        wrapper.vm.closeModal();
        expect(wrapper.vm.inspeksi).toBeNull();
    });

    it('permit gugur ditandai gugur, bukan sekadar status booking terakhirnya', async () => {
        pasangMock({ ...INSPEKSI, status: 'TIDAK_HADIR', tidak_hadir: 2, gugur: true, boleh_pindah: false });
        const wrapper = shallowMount(PermitStatusList);
        await wrapper.vm.viewDetail(mockPermits[1]);

        expect(wrapper.vm.inspeksiStatus).toEqual({ label: 'Permit gugur', variant: 'danger' });
    });

    it('pengajuan kedaluwarsa tidak menawarkan Batal', () => {
        const wrapper = shallowMount(PermitStatusList);

        expect(wrapper.vm.canCancelPermit({ ...mockPermits[1], Status_Permit: 'EXPIRED', Flag_Approval: null })).toBe(false);
    });

    it('tombol Batal ditutup pada hari H inspeksi, di baris maupun di detail', async () => {
        pasangMock({ ...INSPEKSI, boleh_batal: false });
        const wrapper = shallowMount(PermitStatusList);

        expect(wrapper.vm.canCancelPermit({ ...mockPermits[1], inspeksi: { ...INSPEKSI, boleh_batal: false } })).toBe(false);
        expect(wrapper.vm.canCancelPermit({ ...mockPermits[1], inspeksi: { ...INSPEKSI, boleh_batal: true } })).toBe(true);

        await wrapper.vm.viewDetail(mockPermits[1]);
        expect(wrapper.vm.canCancelPermit(wrapper.vm.permitData)).toBe(false);
    });

    it('memindah jadwal lewat endpoint pemohon dengan slot yang dipilih', async () => {
        pasangMock(INSPEKSI);
        const baru = { ...INSPEKSI, mulai: '2026-09-22 09:00', selesai: '2026-09-22 09:30', jadwal_ulang_terpakai: 1 };
        axios.post.mockResolvedValueOnce({ data: { success: true, message: 'ok', result: { inspeksi: baru } } });

        const wrapper = shallowMount(PermitStatusList);
        await wrapper.vm.viewDetail(mockPermits[1]);
        await wrapper.vm.bukaPindahInspeksi();

        expect(wrapper.vm.showPindahInspeksi).toBe(true);
        expect(wrapper.vm.slotInspeksi).toHaveLength(1);

        wrapper.vm.slotPindah = { id_pos: 7, pos: 'Pos Utama', mulai: '2026-09-22 09:00', selesai: '2026-09-22 09:30' };
        await wrapper.vm.simpanPindahInspeksi();

        expect(axios.post).toHaveBeenCalledWith('/transaksipermit/102/pindah-inspeksi', {
            inspeksi_id_pos: 7,
            inspeksi_mulai: '2026-09-22 09:00',
        });
        expect(wrapper.vm.inspeksi).toEqual(baru);
        expect(wrapper.vm.showPindahInspeksi).toBe(false);
    });
});
