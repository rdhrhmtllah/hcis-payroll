import { mount, shallowMount } from '@vue/test-utils';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import TamuHistory from './TamuHistory.vue';

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
    ElNotification: vi.fn(),
}));

const mockGuests = [
    {
        Id: 201,
        Id_Transaksi_Tamu: 201,
        No_Transaksi: 'VSR-20260824-0001',
        Status_Permit_Coklat: 'AKTIF',
        Nama_Tamu: 'Ahmad Fauzi',
        Nama_Lengkap_Tamu: 'Ahmad Fauzi',
        Instansi_Asal: 'PT Vendor Solusi',
        Instansi_Atau_Asal: 'PT Vendor Solusi',
        No_HP: '081234567890',
        Nomor_Handphone: '081234567890',
        No_Identitas: '3201234567890001',
        Jenis_Identitas: 'KTP',
        PIC_Tujuan: 'Budi Santoso',
        Kode_Karyawan_PIC: 'EMP001',
        Tanggal_Kunjungan: '2026-08-24',
        Waktu_Estimasi_Kunjungan: '1-2 jam',
        Deksripsi_Keperluan: 'Meeting evaluasi vendor',
        Jumlah_Tamu: 2,
        Jenis_Kendaraan: 'Mobil',
        No_Plat_Kendaraan: 'B 1234 XYZ',
        Status_Kunjungan: 'CHECKED_IN',
        Waktu_Aktual_Masuk: '2026-08-24 09:15:00',
        Waktu_Aktual_Keluar: null,
        Needs_Approval: 'Y',
        Status_Approval: 'APPROVED',
        Status_Approval_Label: 'Disetujui',
        Created_At: '2026-08-24 08:30:00',
    },
    {
        Id: 202,
        Id_Transaksi_Tamu: 202,
        No_Transaksi: 'VSR-20260824-0002',
        Status_Permit_Coklat: 'EXPIRED',
        Nama_Tamu: 'Dewi Lestari',
        Nama_Lengkap_Tamu: 'Dewi Lestari',
        Instansi_Asal: 'CV Mitra Sukses',
        Instansi_Atau_Asal: 'CV Mitra Sukses',
        No_HP: '081987654321',
        Nomor_Handphone: '081987654321',
        No_Identitas: '3201987654320002',
        Jenis_Identitas: 'KTP',
        PIC_Tujuan: 'Siti Rahma',
        Kode_Karyawan_PIC: 'EMP002',
        Tanggal_Kunjungan: '2026-08-25',
        Waktu_Estimasi_Kunjungan: '30-60 menit',
        Deksripsi_Keperluan: 'Pengantaran dokumen kontrak',
        Jumlah_Tamu: 1,
        Jenis_Kendaraan: 'Motor',
        No_Plat_Kendaraan: 'D 5678 ABC',
        Status_Kunjungan: 'PENDING_APPROVAL',
        Waktu_Aktual_Masuk: null,
        Waktu_Aktual_Keluar: null,
        Needs_Approval: 'Y',
        Status_Approval: 'PENDING',
        Status_Approval_Label: 'Menunggu Approval',
        Created_At: '2026-08-24 10:00:00',
    },
    {
        Id: 203,
        Id_Transaksi_Tamu: 203,
        No_Transaksi: 'VSR-20260824-0003',
        Status_Permit_Coklat: null,
        Nama_Tamu: 'Rudi Hartono',
        Nama_Lengkap_Tamu: 'Rudi Hartono',
        Instansi_Asal: 'PT Logistik Prima',
        Instansi_Atau_Asal: 'PT Logistik Prima',
        No_HP: '081122334455',
        Nomor_Handphone: '081122334455',
        No_Identitas: '3201112233440003',
        Jenis_Identitas: 'SIM',
        PIC_Tujuan: 'Budi Santoso',
        Kode_Karyawan_PIC: 'EMP001',
        Tanggal_Kunjungan: '2026-08-24',
        Waktu_Estimasi_Kunjungan: '2-4 jam',
        Deksripsi_Keperluan: 'Pemeriksaan utilitas',
        Jumlah_Tamu: 3,
        Jenis_Kendaraan: null,
        No_Plat_Kendaraan: null,
        Status_Kunjungan: 'CHECKED_OUT',
        Waktu_Aktual_Masuk: '2026-08-24 08:00:00',
        Waktu_Aktual_Keluar: '2026-08-24 11:30:00',
        Needs_Approval: 'N',
        Status_Approval: 'APPROVED',
        Status_Approval_Label: 'Langsung Aktif (Tanpa Approval)',
        Created_At: '2026-08-24 07:45:00',
    },
];

describe('TamuHistory design-system composition & UX', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockImplementation((url) => {
            if (url === '/transaksitamu-list') {
                return Promise.resolve({
                    data: {
                        success: true,
                        result: {
                            data: mockGuests,
                            total: 3,
                            current_page: 1,
                            last_page: 1,
                        },
                    },
                });
            }
            if (url.startsWith('/transaksitamu/')) {
                return Promise.resolve({
                    data: {
                        success: true,
                        result: {
                            ...mockGuests[0],
                            Approvals: [
                                {
                                    id: 1,
                                    Nama_Approver: 'Jane Doe / GA Head',
                                    Nama_Approval: 'Persetujuan Atasan',
                                    Flag_Approval: 'Y',
                                    Tanggal_Approval: '2026-08-24 08:45:00',
                                    Catatan: 'Kunjungan disetujui.',
                                },
                            ],
                        },
                    },
                });
            }
            return Promise.reject(new Error('URL not mocked: ' + url));
        });
    });

    it('uses official HCIS components in TamuHistory.vue template', () => {
        const source = readFileSync(
            path.resolve(
                process.cwd(),
                'resources/js/Pages/VISITOR/Guest/Visit/components/TamuHistory.vue',
            ),
            'utf8',
        );

        expect(source).toContain('<HcisDataTable');
        expect(source).toContain('<HcisBadge');
        expect(source).toContain('<HcisButton');
        expect(source).toContain('<HcisDialog');
        expect(source).toContain('<HcisDualPaneDialog');
        expect(source).toContain('<HcisCard');
        expect(source).toContain('<HcisAvatar');
        expect(source).toContain('<HcisTimeline');
        expect(source).toContain('<HcisEmptyState');
        expect(source).toContain('tamu-plate-badge');
        expect(source).not.toContain('stitch-table');
        expect(source).not.toContain('stitch-btn');
        expect(source).not.toContain('modernFilter');
    });

    it('fetches guest list and calculates quick filter chips with active presence counts', async () => {
        const wrapper = shallowMount(TamuHistory);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(axios.get).toHaveBeenCalledWith('/transaksitamu-list', expect.objectContaining({
            params: expect.objectContaining({ page: 1, limit: 15 }),
        }));
        expect(wrapper.vm.tableData).toHaveLength(3);
        expect(wrapper.vm.activeGuestsCount).toBe(1);

        const chips = wrapper.vm.quickChips;
        // Kode status baru (DDL-2026-121); BATAL merangkum Rejected, Withdrawn,
        // Revoked, dan Cancelled.
        expect(chips.map((c) => c.value)).toEqual([
            'ALL',
            'CHECKED_IN',
            'PENDING_APPROVAL',
            'SCHEDULED',
            'CHECKED_OUT',
            'BATAL',
        ]);
        expect(chips.find((c) => c.value === 'ALL').badge).toBe(3);
        expect(chips.find((c) => c.value === 'CHECKED_IN').badge).toBe(1);
        expect(chips.find((c) => c.value === 'CHECKED_IN').ping).toBe(true);
        expect(chips.find((c) => c.value === 'PENDING_APPROVAL').badge).toBe(1);
        expect(chips.find((c) => c.value === 'SCHEDULED').badge).toBe(0);
        expect(chips.find((c) => c.value === 'CHECKED_OUT').badge).toBe(1);
        expect(chips.find((c) => c.value === 'BATAL').badge).toBe(0);
    });

    it('menghitung kunjungan berakhir tanpa hadir ke chip BATAL', async () => {
        axios.get.mockImplementation(() =>
            Promise.resolve({
                data: {
                    success: true,
                    result: {
                        data: ['REJECTED', 'WITHDRAWN', 'REVOKED', 'CANCELLED', 'SCHEDULED'].map((status, i) => ({
                            ...mockGuests[2],
                            Id: 300 + i,
                            Id_Transaksi_Tamu: 300 + i,
                            Status_Kunjungan: status,
                        })),
                        total: 5,
                        current_page: 1,
                        last_page: 1,
                    },
                },
            }),
        );

        const wrapper = shallowMount(TamuHistory);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.quickChips.find((c) => c.value === 'BATAL').badge).toBe(4);
        expect(wrapper.vm.quickChips.find((c) => c.value === 'SCHEDULED').badge).toBe(1);
    });

    // Selaras dengan label Monitoring Tamu (TamuAllMonitoring::labelStatusTamu).
    it('memberi label status berdasarkan kode baru', () => {
        const wrapper = shallowMount(TamuHistory);

        expect(wrapper.vm.getStatusBadgeLabel('PENDING_APPROVAL')).toBe('Menunggu Approval');
        expect(wrapper.vm.getStatusBadgeLabel('SCHEDULED')).toBe('Scheduled');
        expect(wrapper.vm.getStatusBadgeLabel('CHECKED_IN')).toBe('Check-In');
        expect(wrapper.vm.getStatusBadgeLabel('CHECKED_OUT')).toBe('Check-Out');
        expect(wrapper.vm.getStatusBadgeLabel('REJECTED')).toBe('Ditolak');
        expect(wrapper.vm.getStatusBadgeLabel('WITHDRAWN')).toBe('Ditarik Pemohon');
        expect(wrapper.vm.getStatusBadgeLabel('REVOKED')).toBe('Dicabut');
        expect(wrapper.vm.getStatusBadgeLabel('CANCELLED')).toBe('Cancelled');
        expect(wrapper.vm.getStatusBadgeVariant('REJECTED')).toBe('danger');
        expect(wrapper.vm.getStatusBadgeVariant('SCHEDULED')).toBe('info');
    });

    it('filters guests by status chip and search query', async () => {
        const wrapper = shallowMount(TamuHistory);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // Click on CHECKED_IN chip
        wrapper.vm.onChipClick({ value: 'CHECKED_IN' });
        expect(wrapper.vm.activeChip).toBe('CHECKED_IN');
        expect(axios.get).toHaveBeenCalledWith('/transaksitamu-list', expect.objectContaining({
            params: expect.objectContaining({ status: 'CHECKED_IN' }),
        }));

        // Search query update
        wrapper.vm.onSearchUpdate('Dewi');
        expect(wrapper.vm.searchQuery).toBe('Dewi');
        expect(axios.get).toHaveBeenCalledWith('/transaksitamu-list', expect.objectContaining({
            params: expect.objectContaining({ search: 'Dewi' }),
        }));

        // Reset filter
        wrapper.vm.resetFilter();
        expect(wrapper.vm.activeChip).toBe('ALL');
        expect(wrapper.vm.searchQuery).toBe('');
    });

    it('loads detail data and populates timeline items for viewing', async () => {
        const wrapper = shallowMount(TamuHistory);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        await wrapper.vm.viewDetail(mockGuests[0]);
        await wrapper.vm.$nextTick();

        expect(axios.get).toHaveBeenCalledWith('/transaksitamu/201');
        expect(wrapper.vm.showModal).toBe(true);
        expect(wrapper.vm.tamuData).toBeTruthy();
        expect(wrapper.vm.approvalTimelineItems).toHaveLength(1);
        expect(wrapper.vm.approvalTimelineItems[0].status).toBe('Disetujui');
        expect(wrapper.vm.approvalTimelineItems[0].sentiment).toBe('success');
    });

    it('tidak lagi menyediakan check-out dari HCIS; kehadiran dicatat palang', async () => {
        // DDL-2026-121: tombol check-out memanggil rute yang sengaja dicabut
        // (lihat VisitorRouteContractTest), jadi tombol, dialog, dan method-nya
        // dibuang. Yang dulu diuji di sini kini dijaga sebaliknya.
        const wrapper = shallowMount(TamuHistory);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.openCheckoutModal).toBeUndefined();
        expect(wrapper.vm.confirmCheckout).toBeUndefined();
        expect(wrapper.vm.showCheckoutModal).toBeUndefined();

        const source = readFileSync(
            path.resolve(process.cwd(), 'resources/js/Pages/VISITOR/Guest/Visit/components/TamuHistory.vue'),
            'utf8',
        );
        expect(source).not.toContain('check-out/');
        expect(source).not.toMatch(/openCheckoutModal|confirmCheckout/);
        expect(axios.post).not.toHaveBeenCalled();
    });

    it('memakai prefiks API halamannya untuk daftar, detail, dan pembatalan', async () => {
        // Halaman B2B memasang TamuHistory dengan apiPrefix salinan rute B2B
        // yang dijaga kuncinya sendiri.
        axios.get.mockImplementation((url) => {
            if (url === '/transaksi-tamu-b2b-list') {
                return Promise.resolve({
                    data: { success: true, result: { data: mockGuests, total: 3, current_page: 1, last_page: 1 } },
                });
            }
            if (url.startsWith('/transaksi-tamu-b2b/')) {
                return Promise.resolve({ data: { success: true, result: { ...mockGuests[1], Approvals: [] } } });
            }
            return Promise.reject(new Error('URL not mocked: ' + url));
        });
        axios.post.mockResolvedValueOnce({ data: { success: true, message: 'Dibatalkan.' } });

        const wrapper = shallowMount(TamuHistory, { props: { tipe: 'B2B', apiPrefix: 'transaksi-tamu-b2b' } });
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(axios.get).toHaveBeenCalledWith('/transaksi-tamu-b2b-list', expect.anything());
        expect(wrapper.vm.tableData).toHaveLength(3);

        await wrapper.vm.viewDetail(mockGuests[1]);
        expect(axios.get).toHaveBeenCalledWith('/transaksi-tamu-b2b/202');

        wrapper.vm.openCancelModal(mockGuests[1]);
        await wrapper.vm.confirmCancel();
        expect(axios.post).toHaveBeenCalledWith('/transaksi-tamu-b2b/cancel/202');
    });

    it('handles guest submission cancellation workflow', async () => {
        axios.post.mockResolvedValueOnce({
            data: {
                success: true,
                message: 'Pengajuan tamu berhasil dibatalkan.',
            },
        });

        const wrapper = shallowMount(TamuHistory);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const pendingGuest = mockGuests[1];
        expect(wrapper.vm.canCancelTamu(pendingGuest)).toBe(true);
        expect(wrapper.vm.canCancelTamu(mockGuests[0])).toBe(false);

        wrapper.vm.openCancelModal(pendingGuest);
        expect(wrapper.vm.showCancelModal).toBe(true);
        expect(wrapper.vm.itemToCancel).toEqual(pendingGuest);

        await wrapper.vm.confirmCancel();
        await wrapper.vm.$nextTick();

        expect(axios.post).toHaveBeenCalledWith('/transaksitamu/cancel/202');
        expect(wrapper.vm.showCancelModal).toBe(false);
        expect(wrapper.emitted('tamu-canceled')).toBeTruthy();
        expect(wrapper.emitted('tamu-updated')).toBeTruthy();
    });
});

/**
 * Status permit COKLAT tampil terpisah dari status kunjungan.
 *
 * Keduanya bisa berbeda dan itu bukan anomali: kunjungan yang masih SCHEDULED
 * boleh punya permit yang jendelanya sudah habis, dan tamunya akan ditahan di
 * gerbang. PIC harus bisa melihat itu tanpa menebak dari status kunjungan.
 */
describe('TamuHistory banner izin gerbang mengikuti jendela permit', () => {
    const banner = (tamuData) => TamuHistory.computed.permitBannerInfo.call({ tamuData });

    it('kunjungan lewat tanggal tetapi permit masih AKTIF (diperpanjang admin) tidak terbaca kadaluarsa', () => {
        const info = banner({ Tipe_Tamu: 'B2B', Status_Kunjungan: 'CANCELLED', Status_Permit_Coklat: 'AKTIF' });
        expect(info.statusLabel).toBe('Disetujui');
    });

    it('permit yang jendelanya habis tetap kadaluarsa', () => {
        expect(banner({ Tipe_Tamu: 'B2B', Status_Kunjungan: 'SCHEDULED', Status_Permit_Coklat: 'EXPIRED' }).statusLabel).toBe('Kadaluarsa');
        expect(banner({ Tipe_Tamu: 'B2B', Status_Kunjungan: 'CANCELLED', Status_Permit_Coklat: 'EXPIRED' }).statusLabel).toBe('Kadaluarsa');
    });
});

describe('TamuHistory status permit coklat', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockImplementation((url) => {
            if (url === '/transaksitamu-list') {
                return Promise.resolve({
                    data: {
                        success: true,
                        result: {
                            data: mockGuests,
                            total: 3,
                            current_page: 1,
                            last_page: 1,
                        },
                    },
                });
            }
            return Promise.reject(new Error('URL not mocked: ' + url));
        });
    });

    it('menerjemahkan setiap nilai proyeksi menjadi label yang dibaca PIC', async () => {
        const wrapper = shallowMount(TamuHistory);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.labelPermitCoklat('AKTIF')).toBe('Permit berlaku');
        expect(wrapper.vm.labelPermitCoklat('EXPIRED')).toBe('Permit habis');
        expect(wrapper.vm.labelPermitCoklat('DIBATALKAN')).toBe('Permit dicabut');
    });

    it('tidak menampilkan apa pun untuk registrasi lama tanpa permit', async () => {
        const wrapper = shallowMount(TamuHistory);
        await wrapper.vm.$nextTick();

        // Registrasi sebelum permit coklat ada mengembalikan null, bukan string
        // kosong — labelnya harus jatuh ke '' supaya v-if di template mematikan
        // lencananya sama sekali.
        expect(wrapper.vm.labelPermitCoklat(null)).toBe('');
        expect(wrapper.vm.labelPermitCoklat(undefined)).toBe('');
    });

    it('membawa Status_Permit_Coklat apa adanya dari respons backend', async () => {
        const wrapper = shallowMount(TamuHistory);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.tableData.map((row) => row.Status_Permit_Coklat)).toEqual([
            'AKTIF',
            'EXPIRED',
            null,
        ]);
    });

    it('merender lencana permit di tabel dan di kartu, dengan penjaga v-if', () => {
        const source = readFileSync(
            path.resolve(
                process.cwd(),
                'resources/js/Pages/VISITOR/Guest/Visit/components/TamuHistory.vue',
            ),
            'utf8',
        );

        expect(source).toContain('tamu-permit-badge');
        expect(source).toContain('v-if="row.Status_Permit_Coklat"');
        expect(source).toContain('v-if="item.Status_Permit_Coklat"');
    });

    it('detail riwayat menampilkan daftar anggota dengan fallback data lama', () => {
        const source = readFileSync(
            path.resolve(
                process.cwd(),
                'resources/js/Pages/VISITOR/Guest/Visit/components/TamuHistory.vue',
            ),
            'utf8',
        );

        expect(source).toContain("from '../daftarAnggota'");
        expect(source).toContain('bacaDaftarAnggota(tamuData.Daftar_Anggota_Json)');
        expect(source).toContain('daftar nama tidak dikumpulkan');
    });
});
