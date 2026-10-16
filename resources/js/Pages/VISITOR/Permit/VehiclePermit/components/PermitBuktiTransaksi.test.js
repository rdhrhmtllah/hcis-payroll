import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PermitBuktiTransaksi from './PermitBuktiTransaksi.vue';

/*
 * Lembar ini men-teleport dirinya ke <body>, jadi isinya TIDAK ada di
 * wrapper.element. Membacanya lewat wrapper.text() akan selalu mengembalikan
 * string kosong dan setiap assertion "tidak mengandung X" akan lulus tanpa
 * menguji apa pun. Semua pembacaan di berkas ini menembak document.body.
 */
const permitLengkap = {
    No_Transaksi: 'PMT0926-0001',
    Tanggal_Pengajuan: '2026-09-01 08:15:00',
    Status_Permit: 'APPROVED',
    Nama_Pemohon: 'Anita',
    NIK: '2301008',
    Kode_Karyawan_Pemohon: 'B8',
    Departemen: 'Produksi',
    Jabatan: 'Staff',
    No_Plat_Kendaraan: 'BG 1234 XY',
    Nama_Vehicle_Type: 'Mobil',
    Nama_Model_Manual: 'Toyota Avanza',
    Warna_Manual: 'Hitam',
    Tahun_Kendaraan: '2022',
    Nama_Kategori: 'MERAH',
    Deskripsi_Kategori: 'Akses penuh sampai Area Boiler',
    Snapshot_Nama_Durasi: '6 Bulan',
    Tanggal_Mulai_Berlaku: '2026-09-01',
    Tanggal_Kadaluarsa: '2027-03-01',
    Keterangan_Pengajuan: 'Antar bahan baku',
};

const approvals = [
    {
        Id_Approval_Request: 1,
        Request_Order_Flow: 1,
        Nama_Approval: 'Atasan Langsung',
        Nama_Approver: 'Hendry',
        Flag_Approval: 'Y',
        Tanggal_Approval: '2026-09-01 09:00:00',
        Catatan: 'Setuju',
    },
    {
        Id_Approval_Request: 2,
        Request_Order_Flow: 2,
        Nama_Approval: 'Security',
        Nama_Approver: 'Aji',
        Flag_Approval: null,
        Tanggal_Approval: null,
        Catatan: null,
    },
];

const checklists = [
    { Id_Permit_Checklist: 9, Pertanyaan: 'STNK masih berlaku?', Jawaban: 'Ya', Catatan: null },
];

const dokumens = [
    { Id_Permit_Dokumen: 3, Jenis_Dokumen: 'STNK', File_Name: 'stnk.pdf' },
];

describe('PermitBuktiTransaksi', () => {
    let wrapper = null;

    const pasang = (props = {}) => {
        wrapper = mount(PermitBuktiTransaksi, {
            props: { permit: permitLengkap, approvals, checklists, dokumens, ...props },
        });

        return wrapper;
    };

    /** Akar lembar cetak di <body>. Gagal keras kalau teleportnya tidak jalan. */
    const lembar = () => {
        const akar = document.body.querySelector('.permit-bukti-print');
        expect(akar).not.toBeNull();

        return akar;
    };

    const teks = () => lembar().textContent.replace(/\s+/g, ' ');

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
            wrapper = null;
        }
    });

    it('mencetak seluruh bagian laporan dari data', () => {
        pasang();
        const isi = teks();

        expect(isi).toContain('BUKTI PENGAJUAN PERMIT KENDARAAN');
        expect(isi).toContain('PMT0926-0001');
        expect(isi).toContain('Anita');
        expect(isi).toContain('2301008');
        expect(isi).toContain('BG 1234 XY');
        expect(isi).toContain('Akses penuh sampai Area Boiler');
        expect(isi).toContain('Hendry');
        expect(isi).toContain('STNK masih berlaku?');
        expect(isi).toContain('stnk.pdf');
    });

    it('menerjemahkan keputusan approval ke bahasa manusia', () => {
        pasang();
        const isi = teks();

        expect(isi).toContain('Disetujui');
        // Tahap kedua belum diputus - harus terbaca menunggu, bukan kosong.
        expect(isi).toContain('Menunggu');
    });

    it('menyatakan bagian yang kosong, bukan menghilangkannya', () => {
        // Bukti yang menyembunyikan bagian kosongnya membuat pembaca mengira
        // bagian itu tidak pernah ada. Judulnya harus tetap tercetak.
        pasang({ approvals: [], checklists: [], dokumens: [] });
        const isi = teks();

        expect(isi).toContain('D. Riwayat Persetujuan');
        expect(isi).toContain('Tidak ada tahap persetujuan yang tercatat.');
        expect(isi).toContain('E. Checklist Pemeriksaan');
        expect(isi).toContain('Tidak ada checklist pemeriksaan untuk pengajuan ini.');
        expect(isi).toContain('F. Dokumen Terlampir');
        expect(isi).toContain('Tidak ada berkas dokumen terlampir.');
    });

    it('mengisi nilai kosong dengan tanda hubung, termasuk spasi belaka', () => {
        pasang({
            permit: { ...permitLengkap, NIK: '   ', Jabatan: null, Warna_Manual: '' },
        });

        const kosong = Array.from(lembar().querySelectorAll('td'))
            .filter((td) => td.textContent.trim() === '-');

        expect(kosong.length).toBeGreaterThanOrEqual(3);
    });

    it('memasang peringatan pencabutan saat aksesnya sudah dicabut', () => {
        // Pencabutan tidak mengubah Flag_Approval, jadi tanpa blok ini lembarnya
        // masih terbaca sebagai izin yang sah.
        pasang({
            permit: {
                ...permitLengkap,
                Status_Permit: 'REVOKED',
                Alasan_Pencabutan: 'Kendaraan sudah tidak dipakai',
                Dicabut_Oleh: 'Admin Gerbang',
                Dicabut_Pada: '2026-09-10 10:00:00',
            },
        });
        const isi = teks();

        expect(isi).toContain('Akses Telah Dicabut');
        expect(isi).toContain('Kendaraan sudah tidak dipakai');
        expect(isi).toContain('Admin Gerbang');
    });

    it('tidak memasang peringatan pencabutan pada permit yang masih sah', () => {
        pasang();

        expect(teks()).not.toContain('Akses Telah Dicabut');
    });

    it('menempel langsung di body dan hilang saat dilepas', () => {
        pasang();
        expect(lembar().parentElement).toBe(document.body);

        wrapper.unmount();
        wrapper = null;

        expect(document.body.querySelector('.permit-bukti-print')).toBeNull();
    });

    describe('cetak()', () => {
        let printSpy;

        beforeEach(() => {
            printSpy = vi.spyOn(window, 'print').mockImplementation(() => {});
        });

        afterEach(() => {
            printSpy.mockRestore();
            document.title = '';
        });

        it('memanggil print sekali', () => {
            pasang().vm.cetak();

            expect(printSpy).toHaveBeenCalledTimes(1);
        });

        it('memakai nomor transaksi sebagai judul dokumen saat mencetak', () => {
            let judulSaatCetak = null;
            printSpy.mockImplementation(() => {
                judulSaatCetak = document.title;
            });

            pasang().vm.cetak();

            expect(judulSaatCetak).toBe('Bukti Permit PMT0926-0001');
        });

        it('mengembalikan judul semula walau print melempar', () => {
            document.title = 'HCIS';
            printSpy.mockImplementation(() => {
                throw new Error('printer tidak siap');
            });

            const vm = pasang().vm;

            expect(() => vm.cetak()).toThrow('printer tidak siap');
            expect(document.title).toBe('HCIS');
        });
    });
});
