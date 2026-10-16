import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import SopDokumenMeta from './SopDokumenMeta.vue';

const DOKUMEN_LENGKAP = {
    Nomor_SOP_Resmi: 'SOP/HSE/002',
    Versi: 3,
    Tanggal_Berlaku: '2026-03-15',
    Disahkan_Oleh: 'Budi Santoso',
    Jabatan_Pengesah: 'Manager HSE',
    Tanggal_Pengesahan: '2026-03-10',
    Nomor_Pengesahan: '001/SK/III/2026',
    Publisher_Nama: 'Admin SOP',
    Ringkasan_Perubahan: 'Penambahan prosedur APD area B',
    Ada_Riwayat: true,
    Keberlakuan: { status: 'BERLAKU', alasan: 'Versi yang sedang berlaku.' },
};

const DOKUMEN_KOSONG = {
    Nomor_SOP_Resmi: null,
    Versi: 1,
    Tanggal_Berlaku: null,
    Disahkan_Oleh: null,
    Jabatan_Pengesah: null,
    Tanggal_Pengesahan: null,
    Nomor_Pengesahan: null,
    Publisher_Nama: null,
    Ringkasan_Perubahan: null,
    Ada_Riwayat: false,
    Keberlakuan: { status: 'BERLAKU', alasan: 'Versi yang sedang berlaku.' },
};

function pasang(props = {}) {
    return mount(SopDokumenMeta, {
        props: { dokumen: DOKUMEN_LENGKAP, ...props },
        global: { stubs: { SopVersiTimeline: true } },
    });
}

describe('SopDokumenMeta — lencana status', () => {
    const kasus = [
        ['BERLAKU', 'Berlaku'],
        ['BELUM_BERLAKU', 'Belum berlaku'],
        ['VERSI_LAMA', 'Versi lama'],
    ];

    it.each(kasus)('menampilkan lencana untuk status %s', (status, label) => {
        const wrapper = pasang({
            dokumen: { ...DOKUMEN_LENGKAP, Keberlakuan: { status, alasan: 'apa pun' } },
        });

        expect(wrapper.find('.dm-badge').text()).toContain(label);
    });

    /* Status tak dikenal tetap harus tampil sebagai sesuatu. Sejak KEDALUWARSA
       dan PERLU_TINJAU dicabut bersama konsep masa berlaku, data lama masih bisa
       membawanya — dan lencana kosong lebih buruk daripada lencana yang jujur
       berkata "berlaku". */
    it('jatuh ke lencana Berlaku untuk status yang tak dikenal', () => {
        const wrapper = pasang({
            dokumen: { ...DOKUMEN_LENGKAP, Keberlakuan: { status: 'KEDALUWARSA', alasan: 'x' } },
        });

        expect(wrapper.find('.dm-badge').text()).toContain('Berlaku');
    });
});

describe('SopDokumenMeta — field kosong disembunyikan', () => {
    it('merender baris yang ada isinya', () => {
        const teks = pasang().text();

        expect(teks).toContain('SOP/HSE/002');
        expect(teks).toContain('Budi Santoso');
        expect(teks).toContain('Manager HSE');
        expect(teks).toContain('001/SK/III/2026');
    });

    /* Masa berlaku dicabut 15 Agu 2026 — barisnya tak boleh muncul sekalipun
       data lama masih membawa tanggalnya. */
    it('tidak pernah merender baris masa berlaku', () => {
        const teks = pasang({
            dokumen: { ...DOKUMEN_LENGKAP, Tanggal_Kadaluarsa: '2026-12-31', Tanggal_Review: '2026-06-01' },
        }).text();

        expect(teks).not.toContain('Berlaku sampai');
        expect(teks).not.toContain('Ditinjau lagi');
    });

    it('menampilkan satu pesan jujur saat seluruh catatan tata kelola kosong', () => {
        const wrapper = pasang({ dokumen: DOKUMEN_KOSONG });

        expect(wrapper.find('.dm-empty').exists()).toBe(true);
        expect(wrapper.findAll('.dm-row')).toHaveLength(0);
    });

    it('tidak menampilkan pesan kosong saat ada satu saja catatan', () => {
        const wrapper = pasang({
            dokumen: { ...DOKUMEN_KOSONG, Tanggal_Berlaku: '2026-01-01' },
        });

        expect(wrapper.find('.dm-empty').exists()).toBe(false);
    });
});

describe('SopDokumenMeta — versi sebelumnya', () => {
    const riwayat = [
        { Id_SOP_Item_Versi: 'v3', Versi_Ke: 3, Is_Aktif: true, Tanggal_Berlaku: '2026-03-15' },
        { Id_SOP_Item_Versi: 'v2', Versi_Ke: 2, Is_Aktif: false, Tanggal_Berlaku: '2025-01-01' },
    ];

    it('menyebut versi yang digantikan beserta tanggal mulainya', () => {
        const teks = pasang({ riwayat }).find('.dm-prev').text();

        // Dua digit sejak penomoran versi dimulai dari nol — lihat
        // Pages/LMS/SOP/Shared/support/versi.js. "v2" adalah format lama.
        expect(teks).toContain('v02');
        expect(teks).toContain('2025');
    });

    /* Versi lama TIDAK diberi tanggal akhir, sekalipun data lamanya membawa
       tanggal kadaluarsa. Menyimpulkan akhir masa berlaku dari tanggal mulai
       versi berikutnya berarti mengarang fakta di fitur yang justru dibangun
       untuk dipercaya. */
    it('tidak mengarang tanggal akhir untuk versi yang digantikan', () => {
        const dgnTanggalLama = [riwayat[0], { ...riwayat[1], Tanggal_Kadaluarsa: '2026-03-14' }];

        const teks = pasang({ riwayat: dgnTanggalLama }).find('.dm-prev').text();

        expect(teks).toContain('2025');
        expect(teks).not.toContain('2026');
    });

    it('tidak menampilkan baris versi sebelumnya saat riwayat belum dimuat', () => {
        expect(pasang().find('.dm-prev').exists()).toBe(false);
    });
});

describe('SopDokumenMeta — memuat riwayat', () => {
    it('meminta riwayat saat pertama dibentangkan', async () => {
        const wrapper = pasang();

        await wrapper.find('.dm-expand').trigger('click');

        expect(wrapper.emitted('muat-riwayat')).toHaveLength(1);
    });

    it('tidak meminta ulang saat riwayat sudah ada', async () => {
        const wrapper = pasang({ riwayat: [{ Id_SOP_Item_Versi: 'v1', Versi_Ke: 1, Is_Aktif: true }] });

        await wrapper.find('.dm-expand').trigger('click');

        expect(wrapper.emitted('muat-riwayat')).toBeUndefined();
    });

    it('menawarkan coba lagi saat pemuatan gagal, tanpa menutup panel', async () => {
        const wrapper = pasang({ galatRiwayat: 'Gagal memuat' });
        await wrapper.find('.dm-expand').trigger('click');

        expect(wrapper.find('.dm-error').exists()).toBe(true);
        await wrapper.find('.dm-retry').trigger('click');
        expect(wrapper.emitted('muat-riwayat').length).toBeGreaterThanOrEqual(1);
        expect(wrapper.find('.dm-head').exists()).toBe(true);
    });
});

describe('SopDokumenMeta — sedang membaca versi lama', () => {
    const versiLama = {
        Id_SOP_Item_Versi: 'v2',
        Versi_Ke: 2,
        Is_Aktif: false,
        Tanggal_Berlaku: '2025-01-01',
        Disahkan_Oleh: 'Pengesah Lama',
        Jabatan_Pengesah: null,
        Nomor_Pengesahan: null,
        Tanggal_Pengesahan: null,
        Ringkasan_Perubahan: 'Revisi alur pelaporan',
        Keberlakuan: { status: 'VERSI_LAMA', alasan: 'Sudah digantikan versi yang lebih baru.' },
    };

    it('menjelaskan versi yang sedang dibaca, bukan versi aktif', () => {
        const wrapper = pasang({ versiDibaca: versiLama });

        expect(wrapper.find('.dm-badge').text()).toContain('Versi lama');
        expect(wrapper.text()).toContain('Pengesah Lama');
        expect(wrapper.text()).not.toContain('Budi Santoso');
    });

    it('memberi tahu versi mana yang berlaku sekarang', () => {
        const wrapper = pasang({ versiDibaca: versiLama });

        expect(wrapper.find('.dm-active-note').text()).toContain('3');
    });
});
