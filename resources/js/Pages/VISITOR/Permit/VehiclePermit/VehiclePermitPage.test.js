import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import VehiclePermitPage from './VehiclePermitPage.vue';

const viewDetail = vi.fn();

const StatusListStub = {
    name: 'PermitStatusList',
    template: '<div />',
    methods: {
        viewDetail,
        fetchData() {},
    },
};

function mountPage(search, stats = {}) {
    global.fetch = vi.fn(() =>
        Promise.resolve({ ok: true, json: () => Promise.resolve({ success: true, result: stats }) }),
    );

    // Halaman membaca window.location.search saat mounted.
    Object.defineProperty(window, 'location', {
        value: { ...window.location, search },
        writable: true,
    });

    return mount(VehiclePermitPage, {
        global: {
            stubs: {
                VisitorWorkspace: {
                    template: '<div><slot name="panel-status" /><slot name="panel-form" /></div>',
                },
                PermitDigitalPass: true,
                PermitForm: true,
                HcisAlert: true,
                PermitStatusList: StatusListStub,
            },
        },
    });
}

beforeEach(() => {
    viewDetail.mockClear();
    global.fetch = vi.fn(() =>
        Promise.resolve({ ok: true, json: () => Promise.resolve({ success: true, result: {} }) }),
    );
});

// Tautan notifikasi membawa token tersamar, bukan Id berurutan.
const TOKEN = 'eyJpdiI6IlRva2VuVWppQ29udG9oIiwidmFsdWUiOiJ4WXoifQ';

describe('VehiclePermitPage — tautan detail dari notifikasi', () => {
    it('meneruskan token dari ?detail= apa adanya ke detail transaksi', async () => {
        const wrapper = mountPage(`?detail=${TOKEN}`);
        await flushPromises();

        expect(wrapper.vm.activeTab).toBe('status');
        expect(viewDetail).toHaveBeenCalledWith({ Id: TOKEN });
    });

    it('masih menerima Id polos demi notifikasi lama', async () => {
        const wrapper = mountPage('?detail=412');
        await flushPromises();

        expect(wrapper.vm.activeTab).toBe('status');
        expect(viewDetail).toHaveBeenCalledWith({ Id: '412' });
    });

    it('tidak menyentuh apa pun saat tautan biasa', async () => {
        const wrapper = mountPage('');
        await flushPromises();

        expect(wrapper.vm.activeTab).toBe('cards');
        expect(viewDetail).not.toHaveBeenCalled();
    });

    it('mengabaikan detail yang bukan token maupun Id transaksi', async () => {
        const wrapper = mountPage('?detail=abc');
        await flushPromises();

        expect(wrapper.vm.activeTab).toBe('cards');
        expect(viewDetail).not.toHaveBeenCalled();
    });
});

describe('VehiclePermitPage — kuota diteruskan ke formulir', () => {
    it('meneruskan jatah dari stats ke PermitForm', async () => {
        const wrapper = mountPage('', {
            kuota_terpakai: 2, kuota_maks: 3,
            pending_terpakai: 1, pending_maks: 1,
            akses_terpakai: 2, akses_maks: 2,
        });
        await flushPromises();

        // Formulir tak boleh menghitung jatahnya sendiri; angkanya lahir dari
        // VisitorPermitQuotaService di server dan diteruskan apa adanya.
        //
        // KETIGANYA ikut: pengajuan yang menunggu keputusan dan akses inspeksi
        // harian sama-sama bisa menolak pengajuan, dan formulir hanya bisa
        // memblokir di depan kalau angkanya sampai ke sana.
        expect(wrapper.findComponent({ name: 'PermitForm' }).props('kuota')).toEqual({
            terpakai: 2,
            maks: 3,
            pendingTerpakai: 1,
            pendingMaks: 1,
            aksesTerpakai: 2,
            aksesMaks: 2,
        });
    });

    it('meneruskan jatah kosong sebelum stats tiba', () => {
        const wrapper = mountPage('');

        expect(wrapper.findComponent({ name: 'PermitForm' }).props('kuota')).toEqual({
            terpakai: 0,
            maks: 0,
            pendingTerpakai: 0,
            pendingMaks: 0,
            aksesTerpakai: 0,
            aksesMaks: 0,
        });
    });
});

describe('VehiclePermitPage — cek kartu', () => {
    const baca = async (relatif) => {
        const { readFileSync } = await import('node:fs');
        const path = await import('node:path');
        return readFileSync(path.resolve(process.cwd(), 'resources/js/Pages/VISITOR', relatif), 'utf8');
    };

    it('karyawan tidak lagi punya tombol "Cek Verifikasi Kartu" (QR kartu membuka halaman cek publik)', async () => {
        const isi = await baca('Permit/VehiclePermit/VehiclePermitPage.vue');

        expect(isi).not.toContain('Cek Verifikasi Kartu');
        expect(isi).not.toContain('<VerifikasiKartuScanner');
    });

    it('pemindai admin tetap ada di halaman admin', async () => {
        for (const halaman of [
            'Workflow/Verification/Verifikator/verifikatorPermit.vue',
            'Workflow/Reporting/Monitoring/monitoringPermit.vue',
            'Permit/Inspection/AgendaInspeksiPage.vue',
        ]) {
            expect(await baca(halaman)).toContain('endpoint="/laporanvisitor-verifikasi-kartu"');
        }
    });
});

describe('VehiclePermitPage — bantuan permit', () => {
    it('kontak bantuan di Pusat Bantuan (bukan kepala halaman), tetap di detail permit', async () => {
        const { readFileSync } = await import('node:fs');
        const path = await import('node:path');
        const baca = (rel) => readFileSync(path.resolve(process.cwd(), 'resources/js/Pages/VISITOR/Permit/VehiclePermit', rel), 'utf8');

        // Keputusan user 29 Sep 2026: kepala halaman lebih rapi, kontak ada di Pusat Bantuan.
        expect(baca('VehiclePermitPage.vue')).not.toContain('<KontakBantuanTombol');
        expect(baca('components/PermitStatusList.vue')).toMatch(/<template #footer-start>[\s\S]*?<KontakBantuanTombol/);
        expect(baca('components/PermitForm.vue')).not.toContain('KontakBantuan');
    });
});

describe('Detail permit — footer di HP', () => {
    it('dua baris selebar layar dan "Tutup" disembunyikan (✕ di kepala dialog sudah ada)', async () => {
        const { readFileSync } = await import('node:fs');
        const path = await import('node:path');
        const isi = readFileSync(path.resolve(process.cwd(), 'resources/js/Pages/VISITOR/Permit/VehiclePermit/components/PermitStatusList.vue'), 'utf8');

        expect(isi).toContain('class="permit-detail-footer-start"');
        expect(isi).toContain('class="permit-detail-footer-end');
        expect(isi).toMatch(/@media \(max-width: 640px\)[\s\S]*?flex-direction: column !important[\s\S]*?\.permit-detail-footer-tutup\)? \{\s*display: none !important;/);
    });
});
