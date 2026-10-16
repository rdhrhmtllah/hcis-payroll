import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PanduanPage from './PanduanPage.vue';
import PanduanPohonHalaman from './Components/PanduanPohonHalaman.vue';

vi.mock('axios', () => ({
    default: { get: vi.fn(), post: vi.fn(), patch: vi.fn() },
}));

const HALAMAN = [
    { value: 'vehiclePermitPage', label: 'Permit Kendaraan', modul: 'VISITOR', fitur: 'Permit' },
    { value: 'permitStatusPage', label: 'Status Permit', modul: 'VISITOR', fitur: 'Permit' },
    { value: 'jadwalTamuPage', label: 'Jadwal Tamu', modul: 'VISITOR', fitur: 'Tamu' },
    { value: 'paPage', label: 'Penilaian', modul: 'KPI', fitur: 'PA' },
];

const ROLE = [
    { value: 7, label: 'Admin Permit', kode: 'ADM_PERMIT' },
    { value: 8, label: 'Security', kode: 'SEC' },
];

const panduan = { id: 3, judul: 'Manual Permit', deskripsi: null, versi: 'v2', no_versi: 2, jumlah_draf: 1, nama_file: 'm.pdf', aktif: true, jumlah_halaman_terpetakan: 1, jumlah_role: 2 };

afterEach(() => {
    document.body.innerHTML = '';
});

function isiForm(fd) {
    return Object.fromEntries([...fd.entries()].filter(([, v]) => typeof v === 'string'));
}

describe('PanduanPohonHalaman', () => {
    function pasang(modelValue = []) {
        return mount(PanduanPohonHalaman, { props: { halaman: HALAMAN, modelValue } });
    }

    it('mengelompokkan Modul › Fitur, ESS/KPI/LMS/VISITOR berurutan', () => {
        const w = pasang();
        const modul = w.findAll('.pohon__baris--modul .pohon__nama').map((n) => n.text());
        expect(modul).toEqual(['KPI', 'VISITOR']);
    });

    it('mencentang fitur memilih semua halamannya; modul jadi sebagian', async () => {
        const w = pasang(['permitStatusPage']);
        // Cabang yang memuat pilihan terbuka otomatis.
        const fiturPermit = w.findAll('.pohon__baris--fitur').find((b) => b.text().includes('Permit'));
        await fiturPermit.find('input').setValue(true);

        expect(w.emitted('update:modelValue').at(-1)[0]).toEqual(['permitStatusPage', 'vehiclePermitPage']);

        await w.setProps({ modelValue: ['permitStatusPage', 'vehiclePermitPage'] });
        const cekModul = w.findAll('.pohon__baris--modul').find((b) => b.text().includes('VISITOR')).find('input').element;
        expect(cekModul.checked).toBe(false);
        expect(cekModul.indeterminate).toBe(true);
    });

    it('pencarian membuka dan menyaring pohon', async () => {
        const w = pasang();
        await w.find('input[type="text"], input:not([type])').setValue('jadwal');
        const halaman = w.findAll('.pohon__baris--halaman').map((b) => b.attributes('data-halaman'));
        expect(halaman).toEqual(['jadwalTamuPage']);
    });
});

describe('PanduanPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockImplementation((url) => {
            if (url === '/system/panduan/data') return Promise.resolve({ data: { result: [panduan] } });
            if (url === '/system/panduan/opsi') return Promise.resolve({ data: { result: { halaman: HALAMAN, role: ROLE, role_tersedia: true } } });
            if (url === '/system/panduan/3/sasaran') {
                return Promise.resolve({ data: { result: { halaman: [{ id: 11, jenis_page: 'vehiclePermitPage', halaman_mulai: 4 }], role: [{ id: 5, id_role: 7 }] } } });
            }
            return Promise.resolve({ data: { result: null } });
        });
        axios.post.mockResolvedValue({ data: { result: panduan } });
    });

    it('memuat daftar panduan', async () => {
        const w = mount(PanduanPage, { props: { maksKb: 30720 } });
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/system/panduan/data');
        expect(w.text()).toContain('Manual Permit');
        expect(w.text()).toContain('1 halaman · 2 role');
        expect(w.text()).toContain('v2');
        expect(w.text()).toContain('1 draf');
    });

    it('ubah: halaman lama tercentang dan tersimpan bersama form (atur_sasaran)', async () => {
        const w = mount(PanduanPage, { props: { maksKb: 30720 }, attachTo: document.body });
        await flushPromises();

        await w.findAll('button').find((b) => b.text().includes('Ubah')).trigger('click');
        await flushPromises();

        const pohon = w.findComponent(PanduanPohonHalaman);
        expect(pohon.props('modelValue')).toEqual(['vehiclePermitPage']);
        // Form Ubah tidak mengganti berkas: diarahkan ke tombol Versi.
        expect(document.querySelector('[data-testid="panduan-arah-versi"]')).not.toBeNull();
        expect(document.getElementById('panduan-versi')).toBeNull();

        // Tambah satu halaman lewat pohon, lalu simpan.
        pohon.vm.$emit('update:modelValue', ['vehiclePermitPage', 'jadwalTamuPage']);
        await flushPromises();
        [...document.querySelectorAll('button')].find((b) => b.textContent.trim() === 'Simpan').click();
        await flushPromises();

        const [url, fd] = axios.post.mock.calls[0];
        expect(url).toBe('/system/panduan/3');
        expect(fd.has('berkas')).toBe(false);
        expect(fd.has('versi')).toBe(false);
        expect(isiForm(fd)).toMatchObject({
            atur_sasaran: '1',
            'role[0]': '7',
            'halaman[0][jenis_page]': 'vehiclePermitPage',
            'halaman[0][halaman_mulai]': '4',
            'halaman[1][jenis_page]': 'jadwalTamuPage',
        });
        expect(isiForm(fd)['halaman[1][halaman_mulai]']).toBeUndefined();
        w.unmount();
    });

    it('ubah: bila halaman lama gagal dimuat, pemetaan tidak ditimpa', async () => {
        axios.get.mockImplementation((url) => {
            if (url === '/system/panduan/data') return Promise.resolve({ data: { result: [panduan] } });
            if (url === '/system/panduan/opsi') return Promise.resolve({ data: { result: { halaman: HALAMAN, role: ROLE, role_tersedia: true } } });
            return Promise.reject(new Error('jaringan'));
        });
        const w = mount(PanduanPage, { props: { maksKb: 30720 }, attachTo: document.body });
        await flushPromises();

        await w.findAll('button').find((b) => b.text().includes('Ubah')).trigger('click');
        await flushPromises();
        [...document.querySelectorAll('button')].find((b) => b.textContent.trim() === 'Simpan').click();
        await flushPromises();

        expect(isiForm(axios.post.mock.calls[0][1]).atur_sasaran).toBeUndefined();
        w.unmount();
    });

    it('tambah: halaman wajib, role opsional ikut terkirim', async () => {
        const w = mount(PanduanPage, { props: { maksKb: 30720 }, attachTo: document.body });
        await flushPromises();

        await w.findAll('button').find((b) => b.text().includes('Tambah Panduan')).trigger('click');
        await flushPromises();
        const form = w.vm.$.setupState.form;
        form.judul = 'Manual Security';
        form.berkas = [{ raw: new File(['%PDF'], 'm.pdf', { type: 'application/pdf' }) }];
        form.role = [8];
        await flushPromises();

        const unggah = () => [...document.querySelectorAll('button')].find((b) => b.textContent.trim() === 'Unggah').click();

        // Tanpa halaman: ditahan di form.
        unggah();
        await flushPromises();
        expect(axios.post).not.toHaveBeenCalled();
        expect(document.body.textContent).toContain('Pilih minimal satu halaman');

        w.findComponent(PanduanPohonHalaman).vm.$emit('update:modelValue', ['vehiclePermitPage']);
        await flushPromises();
        unggah();
        await flushPromises();

        const [url, fd] = axios.post.mock.calls[0];
        expect(url).toBe('/system/panduan');
        expect(isiForm(fd)).toMatchObject({ atur_sasaran: '1', 'halaman[0][jenis_page]': 'vehiclePermitPage', 'role[0]': '8' });
        w.unmount();
    });
    it('tombol Versi membuka riwayat versi panduan itu', async () => {
        const w = mount(PanduanPage, { props: { maksKb: 30720 }, attachTo: document.body });
        await flushPromises();

        await w.findAll('button').find((b) => b.text() === 'Versi').trigger('click');
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/system/panduan/3/versi');
        w.unmount();
    });
});
