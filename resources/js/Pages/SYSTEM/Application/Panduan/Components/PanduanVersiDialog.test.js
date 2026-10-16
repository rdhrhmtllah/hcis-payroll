import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PanduanVersiDialog from './PanduanVersiDialog.vue';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } }));
vi.mock('@/components/ui/hcisDialog.js', async (asli) => ({ ...(await asli()), hcisConfirm: vi.fn(() => Promise.resolve(true)) }));

const RIWAYAT = {
    tersedia: true,
    versi: [
        { id: 13, no: 3, label: 'v3', catatan: 'Draf bab baru', status: 'DRAF', aktif: false, dibuat_at: '2026-09-29 10:00:00', dibuat_by: 'H45' },
        { id: 12, no: 2, label: 'v2', catatan: 'Revisi', status: 'TERBIT', aktif: true, diterbitkan_at: '2026-09-20 10:00:00', diterbitkan_by: 'H45' },
        { id: 11, no: 1, label: 'v1', catatan: null, status: 'TERBIT', aktif: false, diterbitkan_at: '2026-09-01 10:00:00', diterbitkan_by: 'H45' },
    ],
};

function tombol(teks, dalam) {
    return [...dalam.querySelectorAll('button')].filter((b) => b.textContent.trim() === teks);
}

function baris(no) {
    return document.querySelector(`[data-versi="${no}"]`);
}

describe('PanduanVersiDialog', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockResolvedValue({ data: { result: RIWAYAT } });
        axios.post.mockResolvedValue({ data: { result: RIWAYAT } });
        axios.delete.mockResolvedValue({ data: { result: RIWAYAT } });
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    function pasang() {
        return mount(PanduanVersiDialog, {
            props: { modelValue: true, panduan: { id: 5, judul: 'Manual Permit' }, maksMb: 30 },
            attachTo: document.body,
        });
    }

    it('memuat riwayat dan aksi sesuai status', async () => {
        pasang();
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/system/panduan/5/versi');
        expect(tombol('Terbitkan', baris(3))).toHaveLength(1);
        expect(tombol('Hapus draf', baris(3))).toHaveLength(1);
        expect(tombol('Jadikan aktif', baris(2))).toHaveLength(0); // sudah aktif
        expect(tombol('Jadikan aktif', baris(1))).toHaveLength(1);
        expect(baris(2).textContent).toContain('Aktif');
    });

    it('menerbitkan draf memunculkan pengingat cek halaman mulai', async () => {
        const w = pasang();
        await flushPromises();

        tombol('Terbitkan', baris(3))[0].click();
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/system/panduan/5/versi/13/aktifkan');
        expect(document.querySelector('[data-testid="versi-pengingat"]')).not.toBeNull();
        expect(w.emitted('berubah')).toBeTruthy();

        tombol('Cek halaman', document.body)[0].click();
        expect(w.emitted('ubah-halaman')).toBeTruthy();
    });

    it('simpan draf mengirim berkas, label, catatan tanpa terbit', async () => {
        const w = pasang();
        await flushPromises();

        tombol('Simpan draf', document.body)[0].click();
        await flushPromises();
        expect(axios.post).not.toHaveBeenCalled(); // berkas belum dipilih

        w.vm.$.setupState.unggah.berkas = [{ raw: new File(['%PDF'], 'v4.pdf', { type: 'application/pdf' }) }];
        w.vm.$.setupState.unggah.label = 'v4';
        w.vm.$.setupState.unggah.catatan = 'Tambah FAQ';
        tombol('Simpan draf', document.body)[0].click();
        await flushPromises();

        const [url, fd] = axios.post.mock.calls[0];
        expect(url).toBe('/system/panduan/5/versi');
        expect(fd.get('terbitkan')).toBe('0');
        expect(fd.get('label')).toBe('v4');
        expect(fd.get('catatan')).toBe('Tambah FAQ');
        expect(document.querySelector('[data-testid="versi-pengingat"]')).toBeNull(); // draf: versi aktif tidak berganti
    });

    it('lihat versi mengirim berkas versi itu ke viewer', async () => {
        const w = pasang();
        await flushPromises();

        tombol('Lihat', baris(1))[0].click();
        expect(w.emitted('lihat')[0][0]).toMatchObject({ url: '/system/panduan/5/versi/11/berkas', ext: 'pdf' });
    });

    it('DDL belum dijalankan: tampil pemberitahuan, tanpa form unggah', async () => {
        axios.get.mockResolvedValue({ data: { result: { tersedia: false, versi: [] } } });
        pasang();
        await flushPromises();

        expect(document.body.textContent).toContain('Versioning belum tersedia');
        expect(document.querySelector('[data-testid="versi-unggah"]')).toBeNull();
    });
});
