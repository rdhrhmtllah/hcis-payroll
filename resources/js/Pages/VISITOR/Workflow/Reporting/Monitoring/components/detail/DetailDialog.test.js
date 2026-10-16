import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const get = vi.fn();

vi.mock('axios', () => ({ default: { get: (...a) => get(...a) } }));

import PermitDetailDialog from './PermitDetailDialog.vue';
import TamuDetailDialog from './TamuDetailDialog.vue';

/**
 * Dialog detail monitoring: baca-saja, dan sebab pencabutan wajib terbaca.
 *
 * Yang dijaga di sini adalah tiga hal yang gampang lolos: dialog memuat ulang
 * ketika dibuka untuk baris LAIN (kalau tidak, isinya milik baris sebelumnya),
 * tombol Cabut tidak pernah muncul tanpa izin server, dan pencabutan yang sudah
 * terjadi ditampilkan lengkap dengan siapa/kapan/kenapa.
 */
const PERMIT_AKTIF = {
    permit: {
        Id: 7,
        No_Transaksi: 'PMT0826-0002',
        Status_Permit: 'APPROVED',
        Status_Efektif: 'AKTIF',
        Nama_Pemohon: 'Tari',
        Jabatan: 'SHIFT MAINTENANCE ELECTRICAL',
        Departemen: 'PRODUCTION SUPPORT',
        No_Plat_Kendaraan: 'BA 1231 DD',
        Tanggal_Mulai_Berlaku: '2026-08-24',
        Tanggal_Kadaluarsa: '2026-11-23',
        Sisa_Hari: 89,
        Alasan_Pencabutan: null,
        Dicabut_Oleh: null,
        Dicabut_Pada: null,
    },
    dokumens: [{ Id_Permit_Dokumen: 1, Jenis_Dokumen: 'STNK', File_Url: '/x/stnk.pdf' }],
    checklists: [],
    approvals: [
        {
            id: 11,
            Nama_Approver: 'Admin SHE',
            Nama_Approval: 'Verifikasi GA',
            Flag_Approval: 'Y',
            Tanggal_Approval: '2026-08-24 16:20:00',
            Catatan: 'Disetujui',
            Is_Expired: 0,
        },
    ],
    boleh_revoke: true,
};

const PERMIT_DICABUT = {
    ...PERMIT_AKTIF,
    permit: {
        ...PERMIT_AKTIF.permit,
        Id: 22,
        No_Transaksi: 'PMT0826-0004',
        Status_Permit: 'REVOKED',
        Status_Efektif: 'DIBATALKAN',
        Alasan_Pencabutan: 'Kendaraan sudah tidak dipakai di area pabrik.',
        Dicabut_Oleh: 'Arya',
        Dicabut_Pada: '2026-08-26 01:44:46.267',
    },
};

const TAMU = {
    tamu: {
        Id: 3,
        No_Transaksi: 'VSR0826-0011',
        Nama_Tamu: 'Siti Nurhaliza',
        Instansi_Asal: 'PT Contoh',
        Nama_PIC: 'Arya',
        Divisi_PIC: 'PRODUCTION',
        // Status tampil DDL-2026-121: sudah masuk, belum keluar = CHECKED_IN
        // (dulu endpoint mengirim Status_Efektif mentah, 'AKTIF').
        Status_Kunjungan: 'CHECKED_IN',
        Tanggal_Kunjungan: '2026-08-25',
        Waktu_Aktual_Masuk: '2026-08-25 10:05:00',
        Waktu_Aktual_Keluar: null,
        // Asal-usul jam kehadiran; dihitung server dari penanda bypass palang.
        Sumber_Masuk: 'TAPPING',
        Sumber_Keluar: null,
        Bypass_Masuk_Oleh: null,
        Bypass_Keluar_Oleh: null,
        Jumlah_Tamu: 2,
    },
    approvals: [],
};

const stubs = {
    HcisAlert: { props: ['title', 'description'], template: '<div data-test="peringatan">{{ title }} {{ description }}<slot /></div>' },
    HcisBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
    HcisButton: { props: ['label'], template: '<button @click="$emit(\'click\')">{{ label }}</button>' },
    HcisIcon: true,
    HcisSkeleton: true,
    HcisDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /><slot name="footer" /></div>' },
    HcisDualPaneDialog: {
        props: ['modelValue', 'title'],
        template: '<div v-if="modelValue"><div class="title">{{ title }}</div><slot name="left" /><slot name="right" /><slot name="footer" /></div>',
    },
    HcisCard: {
        props: ['title', 'description'],
        template: '<div data-test="card"><h4>{{ title }}</h4><p>{{ description }}</p><slot /></div>',
    },
    HcisTimeline: true,
    HcisField: true,
    HcisFileViewer: true,
    HcisSelect: true,
    HcisSelectOption: true,
};

beforeEach(() => {
    vi.clearAllMocks();
});

function pasangPermit(payload = PERMIT_AKTIF, props = {}) {
    get.mockResolvedValue({ data: { success: true, result: payload } });

    return mount(PermitDetailDialog, {
        props: { show: true, idPermit: payload.permit.Id, ...props },
        global: { stubs },
    });
}

describe('PermitDetailDialog', () => {
    it('memuat detail dari endpoint monitoring, bukan endpoint meja verifikasi', async () => {
        pasangPermit();
        await flushPromises();

        expect(get.mock.calls[0][0]).toBe('/laporanvisitor-detail-permit/7');
    });

    it('memuat ulang ketika dibuka untuk baris lain', async () => {
        // Dialog yang sama dipakai ulang. Tanpa pemuatan ulang, membuka baris
        // kedua menampilkan dossier baris pertama — salah orang, tanpa error.
        const wrapper = pasangPermit();
        await flushPromises();

        get.mockResolvedValue({ data: { success: true, result: PERMIT_DICABUT } });
        await wrapper.setProps({ idPermit: 22 });
        await flushPromises();

        expect(get).toHaveBeenCalledTimes(2);
        expect(get.mock.calls[1][0]).toBe('/laporanvisitor-detail-permit/22');
        const modal = wrapper.findComponent({ name: 'PermitReviewModal' });
        expect(modal.vm.permit.No_Transaksi).toBe('PMT0826-0004');
    });

    it('tidak memuat apa pun selama dialog tertutup', async () => {
        get.mockResolvedValue({ data: { success: true, result: PERMIT_AKTIF } });
        mount(PermitDetailDialog, {
            props: { show: false, idPermit: 7 },
            global: { stubs },
        });
        await flushPromises();

        expect(get).not.toHaveBeenCalled();
    });

    it('menampilkan siapa, kapan, dan alasan pencabutan', async () => {
        const wrapper = pasangPermit(PERMIT_DICABUT);
        await flushPromises();

        const teks = wrapper.find('[data-test="peringatan"]').text();

        expect(teks).toContain('Arya');
        expect(teks).toContain('Kendaraan sudah tidak dipakai di area pabrik.');
    });

    it('tidak menampilkan panel pencabutan pada permit yang masih aktif', async () => {
        const wrapper = pasangPermit();
        await flushPromises();

        const modal = wrapper.findComponent({ name: 'PermitReviewModal' });
        expect(modal.vm.dicabut).toBe(false);
        expect(wrapper.find('[data-test="peringatan"]').exists()).toBe(false);
    });

    it('tombol Hapus akses muncul hanya bila server mengizinkan dan aksesnya masih aktif', async () => {
        const punyaTombol = async (payload) => {
            const w = pasangPermit(payload);
            await flushPromises();

            return w.findAll('button').some((b) => b.text().includes('Hapus akses') || b.text().includes('Cabut permit'));
        };

        expect(await punyaTombol(PERMIT_AKTIF)).toBe(true);
        expect(await punyaTombol({ ...PERMIT_AKTIF, boleh_revoke: false })).toBe(false);
        // Sudah dicabut: tidak boleh bisa dicabut lagi.
        expect(await punyaTombol(PERMIT_DICABUT)).toBe(false);
    });

    it('meneruskan permintaan cabut ke induknya, tidak memanggil endpoint sendiri', async () => {
        // Pencabutannya dimiliki halaman induk bersama dialog beralasannya;
        // menaruh panggilan POST di sini berarti dua jalur pencabutan.
        const wrapper = pasangPermit();
        await flushPromises();

        await wrapper.findAll('button').find((b) => b.text().includes('Hapus akses') || b.text().includes('Cabut permit')).trigger('click');

        expect(wrapper.emitted('minta-revoke')).toBeTruthy();
        expect(wrapper.emitted('minta-revoke')[0][0].No_Transaksi).toBe('PMT0826-0002');
    });

    it('menampilkan sebab kegagalan alih-alih dialog kosong', async () => {
        get.mockRejectedValue({ response: { data: { message: 'Data permit tidak ditemukan.' } } });
        const wrapper = mount(PermitDetailDialog, {
            props: { show: true, idPermit: 999 },
            global: { stubs },
        });
        await flushPromises();

        const modal = wrapper.findComponent({ name: 'PermitReviewModal' });
        expect(modal.vm.errorMsg).toBe('Data permit tidak ditemukan.');
    });
});

describe('TamuDetailDialog', () => {
    function pasang(tamu = {}) {
        get.mockResolvedValue({
            data: { success: true, result: { ...TAMU, tamu: { ...TAMU.tamu, ...tamu } } },
        });

        return mount(TamuDetailDialog, {
            props: { show: true, idTamu: 3 },
            global: { stubs },
        });
    }

    it('memuat detail dari endpoint monitoring', async () => {
        pasang();
        await flushPromises();

        expect(get.mock.calls[0][0]).toBe('/laporanvisitor-detail-tamu/3');
    });

    it('menampilkan identitas, PIC, dan kehadiran gerbang', async () => {
        const wrapper = pasang();
        await flushPromises();

        const teks = wrapper.text();

        expect(teks).toContain('Siti Nurhaliza');
        expect(teks).toContain('Arya');
    });

    /**
     * Asal-usul jam kehadiran ikut tampil, ketiga-tiganya.
     *
     * Penanda yang hanya muncul saat menyimpang membuat ketiadaannya ambigu:
     * pembaca tidak bisa membedakan "kartunya terbaca" dari "kolomnya belum
     * pernah terisi". Karena itu TAPPING pun diberi label, dan penjaga ini
     * menagih ketiganya bersama — bukan BYPASS saja — sebab satu label yang
     * menelan seluruh cabang adalah kekeliruan yang paling merugikan: ia
     * menuduh petugas membuka palang untuk kunjungan yang tak ia sentuh.
     */
    it('membedakan tapping, bypass satpam, dan catatan manual', async () => {
        const kasus = [
            [{ Sumber_Masuk: 'TAPPING' }, 'Tapping kartu di palang'],
            [{ Sumber_Masuk: 'BYPASS', Bypass_Masuk_Oleh: 'Baraya' }, 'Bypass palang oleh Baraya'],
            [{ Sumber_Masuk: 'MANUAL' }, 'Dicatat manual di HCIS'],
        ];

        for (const [tamu, label] of kasus) {
            const wrapper = pasang(tamu);
            await flushPromises();

            expect(wrapper.text()).toContain(label);
        }
    });

    /**
     * Bypass tanpa nama petugas tetap harus mengaku bypass.
     *
     * Nama petugas disembunyikan server bila penandanya padam, jadi kolomnya
     * bisa kosong sementara jamnya tetap hasil bypass. Label yang dirakit dari
     * nama akan menghilang seluruhnya di keadaan itu, dan yang paling perlu
     * dilihat justru menjadi yang tidak terlihat.
     */
    it('bypass tanpa nama petugas tetap ditandai', async () => {
        const wrapper = pasang({ Sumber_Masuk: 'BYPASS', Bypass_Masuk_Oleh: null });
        await flushPromises();

        expect(wrapper.text()).toContain('Bypass palang (petugas tidak tercatat)');
    });

    /**
     * Yang dilarang adalah aksi yang MENGUBAH keadaan, bukan setiap tombol.
     *
     * Bentuk sebelumnya menagih daftar tombol persis ['Tutup']. Begitu dialog
     * ini memperoleh tombol salin ikon-saja — yang memang diminta supaya nomor
     * transaksi dan identitas bisa disalin — penjaga itu memerah atas fitur
     * yang benar, padahal tidak satu pun aksi mutasi bertambah. Penjaga yang
     * memerah atas hal yang benar akan dibungkam orang, lalu berhenti menjaga
     * hal yang salah.
     */
    it('tidak menyediakan aksi yang mengubah keadaan', async () => {
        const wrapper = pasang();
        await flushPromises();

        const namaTombol = wrapper.findAll('button').map(
            (b) => b.text() || b.attributes('aria-label') || '',
        );

        expect(namaTombol).toContain('Tutup');

        for (const terlarang of ['Setujui', 'Tolak', 'Cabut', 'Hapus', 'Simpan']) {
            expect(namaTombol.join(' | ')).not.toContain(terlarang);
        }
    });

    /**
     * Sisi PENERIMAAN: tombol ikon-saja WAJIB punya nama yang terbaca. Tanpa
     * ini, penjaga di atas tetap hijau seandainya seluruh tombolnya anonim —
     * dan pembaca layar hanya mendengar "tombol".
     */
    it('tombol ikon punya nama yang bisa dibaca', async () => {
        const wrapper = pasang();
        await flushPromises();

        for (const tombol of wrapper.findAll('button')) {
            const nama = tombol.text() || tombol.attributes('aria-label') || '';

            expect(nama.trim(), 'ada tombol tanpa nama yang bisa dibaca').not.toBe('');
        }
    });
});
