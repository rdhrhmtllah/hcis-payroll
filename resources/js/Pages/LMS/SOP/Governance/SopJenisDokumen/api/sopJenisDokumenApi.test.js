import { describe, it, expect, vi, beforeEach } from 'vitest';
import axios from 'axios';
import { sopJenisDokumenApi, sopMasterDataApi, labelDepartemen, labelEntitas } from './sopJenisDokumenApi.js';

vi.mock('axios');

/**
 * Kontrak modul API Jenis Dokumen.
 *
 * Diuji di sini dan bukan lewat halaman, karena yang mudah menyimpang adalah
 * BENTUK-nya: alamat endpoint, nama parameter, dan cara galat diterjemahkan.
 * Ketiganya tak terlihat dari layar sampai layarnya kosong tanpa penjelasan.
 */
describe('sopJenisDokumenApi', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        axios.isCancel = vi.fn(() => false);
    });

    it('membaca daftar dari endpoint jenis-dokumen dengan paging', async () => {
        axios.get.mockResolvedValue({
            data: { success: true, result: [{ Kode_Jenis: 'PROSEDUR' }], page: 2, total_page: 3, total_data: 51 },
        });

        const res = await sopJenisDokumenApi.list({ page: 2, limit: 25, q: '  prosedur  ' });

        expect(axios.get).toHaveBeenCalledWith('/api/v1/sop/admin/jenis-dokumen/current', expect.anything());
        const params = axios.get.mock.calls[0][1].params;
        expect(params).toMatchObject({ page: 2, limit: 25, q: 'prosedur' });
        expect(res.success).toBe(true);
        expect(res.total_data).toBe(51);
    });

    it('404 dibaca sebagai daftar kosong, bukan kegagalan', async () => {
        // Master yang belum diisi bukan galat. Melemparkannya sebagai galat
        // membuat halaman baru menyambut admin dengan layar merah.
        axios.get.mockRejectedValue({ response: { status: 404, data: {} } });

        const res = await sopJenisDokumenApi.list();

        expect(res.success).toBe(true);
        expect(res.data).toEqual([]);
        expect(res.total_data).toBe(0);
    });

    it('meneruskan pesan galat server apa adanya', async () => {
        axios.post.mockRejectedValue({
            response: { status: 422, data: { message: 'Kode jenis "PROSEDUR" sudah dipakai "Prosedur".' } },
        });

        await expect(sopJenisDokumenApi.store({ Kode_Jenis: 'PROSEDUR' })).rejects.toMatchObject({
            status: 422,
            message: 'Kode jenis "PROSEDUR" sudah dipakai "Prosedur".',
        });
    });

    it('menolak update tanpa id', async () => {
        await expect(sopJenisDokumenApi.update(null, {})).rejects.toThrow();
        expect(axios.put).not.toHaveBeenCalled();
    });
});

describe('sopMasterDataApi', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        axios.isCancel = vi.fn(() => false);
    });

    it('menyaring jenis berdasarkan tipe item', async () => {
        axios.get.mockResolvedValue({ data: { success: true, result: [{ Kode_Jenis: 'FORMULIR' }] } });

        const opsi = await sopMasterDataApi.jenisDokumen('FORM');

        expect(axios.get).toHaveBeenCalledWith('/api/v1/sop/admin/master-data/jenis-dokumen', { params: { tipe: 'FORM' } });
        expect(opsi).toEqual([{ Kode_Jenis: 'FORMULIR' }]);
    });

    it('tanpa tipe, tak mengirim parameter tipe sama sekali', async () => {
        // Dipakai dropdown FILTER, yang memang harus bisa menyebut jenis apa
        // pun — bukan hanya yang sah bagi satu tipe.
        axios.get.mockResolvedValue({ data: { success: true, result: [] } });

        await sopMasterDataApi.jenisDokumen(null);

        expect(axios.get).toHaveBeenCalledWith('/api/v1/sop/admin/master-data/jenis-dokumen', { params: {} });
    });

    it('unit kerja mengembalikan dua bentuk sekaligus', async () => {
        // Bentuk lama (divisi bertingkat) dipertahankan sebagai jalur mundur;
        // menghapusnya di rilis yang sama dengan mengubah layar akan memutus
        // apa pun yang belum ikut berpindah, tanpa peringatan.
        axios.get.mockResolvedValue({
            data: {
                success: true,
                result: {
                    departemen: [{ Id: 14, Nama: 'ACCOUNTING', Divisi: 'FAT' }],
                    divisi: [{ Id: 9, Nama: 'PRODUKSI', Sub: [] }],
                },
            },
        });

        const unit = await sopMasterDataApi.unitKerja();

        expect(axios.get).toHaveBeenCalledWith('/api/v1/sop/admin/master-data/unit-kerja');
        expect(unit.departemen[0].Nama).toBe('ACCOUNTING');
        expect(unit.divisi[0].Nama).toBe('PRODUKSI');
    });

    it('departemen dibaca dari MASTER KODE, bukan struktur HRIS', async () => {
        // Endpoint berbeda dari unit-kerja, dan itu bukan detail: master kode
        // memuat 52 baris sementara struktur HRIS hanya 32. Memanggil yang
        // salah membuat 35 kode hilang dari dropdown tanpa satu pun galat.
        axios.get.mockResolvedValue({
            data: { success: true, result: [{ Id: 'd1', Kode_Departemen: 'IT', Nama_Departemen: 'Information Technology', Terikat: true }] },
        });

        const dep = await sopMasterDataApi.departemen();

        expect(axios.get).toHaveBeenCalledWith('/api/v1/sop/admin/master-data/departemen');
        expect(dep[0].Kode_Departemen).toBe('IT');
    });

    it('entitas dibaca dari endpoint bersama', async () => {
        axios.get.mockResolvedValue({
            data: { success: true, result: [{ Id: 'x1', Kode_Entitas: 'ENB', Nama_Entitas: 'PT Evo Nusa Bersaudara' }] },
        });

        const entitas = await sopMasterDataApi.entitas();

        expect(axios.get).toHaveBeenCalledWith('/api/v1/sop/admin/master-data/entitas');
        expect(entitas[0].Kode_Entitas).toBe('ENB');
    });
});

describe('kontrak payload ResponseHelper', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        axios.isCancel = vi.fn(() => false);
    });

    /**
     * ResponseHelper::success menaruh payload di `result`, BUKAN `data`.
     *
     * Ini pernah salah dan lolos jauh: dropdown jenis, departemen, dan entitas
     * diam-diam kosong, halaman tetap memuat tanpa satu pun galat konsol karena
     * array kosong tak melempar apa pun — dan test-nya sendiri ikut memakai
     * bentuk keliru, sehingga justru mengunci bug alih-alih menangkapnya.
     * Tes ini menahan bentuk yang benar dari sisi yang berlawanan.
     */
    it('payload di `data` (bentuk keliru) dibaca sebagai kosong', async () => {
        axios.get.mockResolvedValue({ data: { success: true, data: [{ Kode_Jenis: 'PROSEDUR' }] } });

        expect(await sopMasterDataApi.jenisDokumen('DOKUMEN')).toEqual([]);
    });

    it('payload di `result` terbaca', async () => {
        axios.get.mockResolvedValue({ data: { success: true, result: [{ Kode_Jenis: 'PROSEDUR' }] } });

        expect(await sopMasterDataApi.jenisDokumen('DOKUMEN')).toEqual([{ Kode_Jenis: 'PROSEDUR' }]);
    });
});

describe('labelDepartemen', () => {
    it('kode lebih dulu, karena itu yang tercetak di nomor dokumen', () => {
        expect(labelDepartemen({ Kode_Departemen: 'IT', Nama_Departemen: 'Information Technology' }))
            .toBe('IT — Information Technology');
    });

    it('tak melempar saat departemennya belum dipilih', () => {
        expect(labelDepartemen(null)).toBe('');
    });
});

describe('labelEntitas', () => {
    it('menyebut arti payung DI TITIK MEMILIH', () => {
        // Admin yang memilih EHG harus tahu saat itu juga bahwa ia mencakup
        // seluruh entitas — bukan dari dokumentasi yang tak pernah dibuka.
        expect(labelEntitas({ Kode_Entitas: 'EHG', Nama_Entitas: 'Evo Holding / Group', Cakup_Semua: true }))
            .toBe('EHG — Evo Holding / Group (seluruh entitas)');
    });

    it('entitas biasa tidak mengaku mencakup apa pun', () => {
        expect(labelEntitas({ Kode_Entitas: 'ENB', Nama_Entitas: 'PT Evo Nusa Bersaudara', Cakup_Semua: false }))
            .toBe('ENB — PT Evo Nusa Bersaudara');
    });

    it('nilai kosong tidak melempar', () => {
        expect(labelEntitas(null)).toBe('');
    });
});
