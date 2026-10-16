import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const axiosGet = vi.fn();
vi.mock('axios', () => ({
    default: {
        get: (...a) => axiosGet(...a),
        post: vi.fn(() => Promise.resolve({ data: {} })),
        create: () => ({ get: vi.fn(() => Promise.resolve({ data: {} })) }),
        defaults: { headers: { common: {} } },
        interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
    },
}));
vi.mock('@inertiajs/vue3', () => ({
    router: { on: vi.fn(() => () => {}), visit: vi.fn(), reload: vi.fn(), post: vi.fn() },
    usePage: () => ({ props: {}, url: '/' }),
    Link: { template: '<a><slot /></a>' },
}));

import AppTopbar from './AppTopbar.vue';
import { useShellState } from '../../composables/useShellState';

function penyimpananTiruan() {
    let isi = {};
    return {
        getItem: (k) => (k in isi ? isi[k] : null),
        setItem: (k, v) => {
            isi[k] = String(v);
        },
        removeItem: (k) => {
            delete isi[k];
        },
        clear: () => {
            isi = {};
        },
    };
}


/**
 * Panduan halaman di Pusat Bantuan topbar: dimuat per halaman aktif
 * (layout.shell.activeSubMenu), dan klik membuka viewer PDF di halaman mulainya.
 */
describe('AppTopbar — panduan halaman', () => {
    beforeEach(() => {
        // Status shell bersifat global: Pusat Bantuan yang dibuka test lain tidak boleh terbawa.
        useShellState().state.showHelp = false;
        axiosGet.mockReset();
        axiosGet.mockImplementation((url) => {
            if (url.startsWith('/panduan/halaman/')) {
                return Promise.resolve({ data: { result: [{ id: 9, judul: 'Manual Permit', deskripsi: null, versi: 'v2', halaman_mulai: 4 }] } });
            }
            return Promise.resolve({ data: {} });
        });
    });

    function pasang(activeSubMenu) {
        return mount(AppTopbar, {
            props: { user: { name: 'Uji', kode_karyawan: 'K001' }, layout: { shell: { activeSubMenu } } },
            global: { stubs: { teleport: true, HcisFileViewer: { name: 'HcisFileViewer', props: ['files', 'modelValue'], template: '<div class="stub-viewer" />' }, AppNotificationModal: true } },
            attachTo: document.body,
        });
    }

    it('memuat panduan untuk halaman aktif dan menandai tombol bantuan', async () => {
        const wrapper = pasang('vehiclePermitPage');
        await flushPromises();

        expect(axiosGet).toHaveBeenCalledWith('/panduan/halaman/vehiclePermitPage');
        expect(wrapper.find('[data-testid="topbar-panduan-dot"]').exists()).toBe(true);
        wrapper.unmount();
    });

    it('tanpa halaman aktif tidak memanggil server dan tanpa tanda', async () => {
        const wrapper = pasang(undefined);
        await flushPromises();

        expect(axiosGet.mock.calls.some(([u]) => String(u).startsWith('/panduan/'))).toBe(false);
        expect(wrapper.find('[data-testid="topbar-panduan-dot"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('klik panduan membuka viewer di halaman mulainya', async () => {
        const wrapper = pasang('vehiclePermitPage');
        await flushPromises();

        await wrapper.find('[aria-label^="Bantuan"]').trigger('click');
        await flushPromises();
        const daftar = wrapper.find('[data-testid="topbar-panduan"]');
        expect(daftar.exists()).toBe(true);
        expect(daftar.text()).toContain('Manual Permit');

        await daftar.find('button').trigger('click');
        await flushPromises();

        const viewer = wrapper.findComponent({ name: 'HcisFileViewer' });
        expect(viewer.exists()).toBe(true);
        expect(viewer.props('files')[0]).toMatchObject({ url: '/panduan/9/berkas', ext: 'pdf', halamanMulai: 4 });
        wrapper.unmount();
    });

    it('panduan yang baru diperbarui diberi tanda dan catatan perubahannya', async () => {
        axiosGet.mockImplementation((url) => {
            if (url.startsWith('/panduan/halaman/')) {
                return Promise.resolve({ data: { result: [
                    { id: 9, judul: 'Manual Permit', deskripsi: 'Deskripsi lama', halaman_mulai: 1, baru: true, diperbarui: '2026-09-28T03:00:00+00:00', catatan: 'Bab persetujuan ditulis ulang' },
                    { id: 10, judul: 'Manual Lain', deskripsi: 'Tetap', halaman_mulai: 1, baru: false, diperbarui: null, catatan: 'x' },
                ] } });
            }
            return Promise.resolve({ data: {} });
        });
        const wrapper = pasang('vehiclePermitPage');
        await flushPromises();
        await wrapper.find('[aria-label^="Bantuan"]').trigger('click');
        await flushPromises();

        const daftar = wrapper.find('[data-testid="topbar-panduan"]');
        expect(daftar.findAll('[data-testid="topbar-panduan-baru"]')).toHaveLength(1);
        const [baru, lama] = daftar.findAll('button');
        expect(baru.text()).toContain('Diperbarui');
        expect(baru.text()).toContain('Bab persetujuan ditulis ulang');
        expect(baru.text()).not.toContain('Deskripsi lama');
        expect(lama.text()).toContain('Tetap');
        wrapper.unmount();
    });

    it('jawaban halaman lama tidak menimpa halaman baru', async () => {
        let selesaikanLama;
        axiosGet.mockImplementation((url) => {
            if (url === '/panduan/halaman/halamanLama') return new Promise((r) => (selesaikanLama = r));
            if (url === '/panduan/halaman/halamanBaru') return Promise.resolve({ data: { result: [] } });
            return Promise.resolve({ data: {} });
        });

        const wrapper = pasang('halamanLama');
        await wrapper.setProps({ layout: { shell: { activeSubMenu: 'halamanBaru' } } });
        await flushPromises();
        selesaikanLama({ data: { result: [{ id: 1, judul: 'Lama', halaman_mulai: 1 }] } });
        await flushPromises();

        expect(wrapper.find('[data-testid="topbar-panduan-dot"]').exists()).toBe(false);
        wrapper.unmount();
    });
    it('petunjuk panduan muncul di dekat tombol bantuan dan membuka panduannya', async () => {
        vi.useFakeTimers();
        vi.stubGlobal('localStorage', penyimpananTiruan());
        try {
            const wrapper = pasang('vehiclePermitPage');
            await flushPromises();
            vi.advanceTimersByTime(1500);
            await flushPromises();

            const petunjuk = wrapper.find('[data-testid="panduan-petunjuk"]');
            expect(petunjuk.exists()).toBe(true);
            expect(wrapper.find('.is-berdenyut').exists()).toBe(true);

            await petunjuk.find('[data-testid="panduan-petunjuk-buka"]').trigger('click');
            await flushPromises();
            expect(wrapper.findComponent({ name: 'HcisFileViewer' }).props('files')[0]).toMatchObject({ url: '/panduan/9/berkas', halamanMulai: 4 });
            expect(wrapper.find('.is-berdenyut').exists()).toBe(false);
            wrapper.unmount();
        } finally {
            vi.useRealTimers();
            vi.unstubAllGlobals();
        }
    });
    it('kontak bantuan: satu baris per modul, modal berisi kontak per fitur', async () => {
        const kontak = (nama) => ({ nama, email: `${nama}@contoh.id`, tautan_email: `mailto:${nama}@contoh.id` });
        axiosGet.mockImplementation((url) => {
            if (url === '/bantuan/kontak') {
                return Promise.resolve({ data: { result: [
                    { kunci: 'visitor', judul: 'Bantuan VMS', subjudul: 'Visitor Management System', relevan: true, jumlah: 4, fitur: ['Permit', 'Tamu'], bagian: [
                        { kode_fitur: 'UMUM', label: 'Umum', jumlah: 1, kontak: [kontak('HelpdeskVMS')] },
                        { kode_fitur: 'PERMIT', label: 'Permit', jumlah: 2, kontak: [kontak('AdminPermit'), kontak('HelpdeskGA')] },
                        { kode_fitur: 'TAMU', label: 'Tamu', jumlah: 1, kontak: [kontak('Resepsionis')] },
                    ] },
                    { kunci: 'ess', judul: 'Bantuan HCIS', subjudul: 'Employee Self Service', relevan: false, jumlah: 1, fitur: [], bagian: [
                        { kode_fitur: 'UMUM', label: 'Umum', jumlah: 1, kontak: [kontak('PICHCIS')] },
                    ] },
                ] } });
            }
            if (url.startsWith('/panduan/halaman/')) return Promise.resolve({ data: { result: [] } });
            return Promise.resolve({ data: {} });
        });
        const wrapper = pasang('vehiclePermitPage');
        await flushPromises();
        expect(axiosGet.mock.calls.some(([u]) => u === '/bantuan/kontak')).toBe(false); // baru dimuat saat dibuka

        await wrapper.find('[aria-label^="Bantuan"]').trigger('click');
        await flushPromises();
        expect(axiosGet).toHaveBeenCalledWith('/bantuan/kontak', { params: { halaman: 'vehiclePermitPage' } });

        const baris = wrapper.findAll('[data-testid="topbar-kontak"] [data-kelompok]');
        expect(baris.map((b) => b.attributes('data-kelompok'))).toEqual(['visitor', 'ess']);
        expect(baris[0].text()).toContain('Bantuan VMS');
        expect(baris[0].text()).toContain('Modul ini');
        expect(baris[0].text()).toContain('4 kontak · Permit, Tamu');
        expect(baris[1].text()).toContain('1 kontak · Employee Self Service');
        expect(wrapper.text()).not.toContain('AdminPermit'); // kontak tidak di daftar

        // Cari berdasarkan nama fitur tetap menemukan modulnya.
        wrapper.vm.$.setupState.cariBantuan = 'tamu';
        await flushPromises();
        expect(wrapper.findAll('[data-testid="topbar-kontak"] [data-kelompok]').map((b) => b.attributes('data-kelompok'))).toEqual(['visitor']);
        wrapper.vm.$.setupState.cariBantuan = '';
        await flushPromises();

        await wrapper.find('[data-kelompok="visitor"]').trigger('click');
        await flushPromises();
        const dialog = wrapper.findComponent({ name: 'HcisDialog' });
        expect(dialog.props('modelValue')).toBe(true);
        expect(dialog.props('title')).toBe('Bantuan VMS');
        const isi = () => wrapper.find('[data-testid="kontak-modul-dialog"]');
        expect(isi().findAll('[data-fitur]').map((b) => b.attributes('data-fitur'))).toEqual(['UMUM', 'PERMIT', 'TAMU']);

        // Akordeon: bagian fitur halaman aktif (Permit di vehiclePermitPage) terbuka & ditandai,
        // bagian lain terlipat sampai diklik.
        const kepala = (fitur) => isi().find(`[data-fitur="${fitur}"] .shell-kontak-bagian__header`);
        expect(kepala('PERMIT').attributes('aria-expanded')).toBe('true');
        expect(kepala('PERMIT').text()).toMatch(/2 kontak/i);
        expect(kepala('PERMIT').text()).toContain('Halaman Ini');
        expect(isi().find('[data-fitur="PERMIT"]').text()).toContain('AdminPermit');
        expect(kepala('TAMU').attributes('aria-expanded')).toBe('false');
        await kepala('TAMU').trigger('click');
        expect(kepala('TAMU').attributes('aria-expanded')).toBe('true');

        // Modul dengan satu bagian: tanpa kepala bagian.
        await dialog.vm.$emit('update:modelValue', false);
        await flushPromises();
        await wrapper.find('[aria-label^="Bantuan"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-kelompok="ess"]').trigger('click');
        await flushPromises();
        expect(isi().find('.shell-kontak-bagian__header').exists()).toBe(false);
        expect(isi().text()).toContain('PICHCIS');
        wrapper.unmount();
    });
});
