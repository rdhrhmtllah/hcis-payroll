import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';

import paApi from '../../api/paApi.js';
import { usePaWizardPenilaian } from './usePaWizardPenilaian.js';

vi.mock('../../api/paApi.js', () => ({
    default: {
        wizardPenilaianSaya: vi.fn(),
        lembarLangkahSaya: vi.fn(),
        pratinjauLangkahSaya: vi.fn(),
        simpanKalibrasiPenilai: vi.fn(),
        kirimSemuaPenilaianSaya: vi.fn(),
    },
}));

const langkah = (status, kode = 'ATASAN', boleh = true) => ({
    kode_kategori: kode,
    nama_kategori: kode,
    status,
    boleh_kalibrasi: boleh,
    jumlah_lembar: 1,
    jumlah_terisi: status === 'AKTIF' ? 0 : 1,
});

// Tanpa ini pendengar `visibilitychange` dari uji sebelumnya ikut terpicu.
enableAutoUnmount(afterEach);

function pasang(opsi = {}) {
    let api;
    mount(
        defineComponent({
            setup() {
                api = usePaWizardPenilaian(opsi);
                return () => h('div');
            },
        }),
    );
    return api;
}

function tangguhan() {
    let lunasi;
    const janji = new Promise((r) => {
        lunasi = r;
    });
    return { janji, lunasi };
}

describe('usePaWizardPenilaian', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        // Lingkungan uji ini tidak menyediakan localStorage; dipasang yang sederhana.
        const isi = new Map();
        Object.defineProperty(window, 'localStorage', {
            configurable: true,
            value: {
                getItem: (k) => (isi.has(k) ? isi.get(k) : null),
                setItem: (k, v) => isi.set(k, String(v)),
                removeItem: (k) => isi.delete(k),
                clear: () => isi.clear(),
            },
        });
        paApi.lembarLangkahSaya.mockResolvedValue({ lembar: [] });
        paApi.pratinjauLangkahSaya.mockResolvedValue({ orang: [], kompetensi: [] });
    });

    it('membuka langkah yang ditentukan server', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValue({
            siklus: { cycle_id: 7 },
            langkah: [langkah('AKTIF')],
            langkah_aktif: 'ATASAN',
        });
        const w = pasang();
        await flushPromises();

        expect(w.kodeDilihat.value).toBe('ATASAN');
        expect(w.tampilan.value).toBe('lembar');
        expect(paApi.lembarLangkahSaya).toHaveBeenCalledWith('ATASAN', 7);
    });

    it('kategori dari URL dibuka sekali, lalu kembali mengikuti server', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValue({
            siklus: { cycle_id: 7 },
            langkah: [langkah('TUNTAS', 'SELF'), langkah('AKTIF')],
            langkah_aktif: 'ATASAN',
        });
        const w = pasang({ kategoriAwal: 'self' });
        await flushPromises();

        // Langkah yang baru tuntas dibuka di pratinjaunya: titik Kalibrasi Penilai.
        expect(w.kodeDilihat.value).toBe('SELF');
        expect(w.tampilan.value).toBe('pratinjau');

        await w.muat();
        expect(w.kodeDilihat.value).toBe('ATASAN');
    });

    it('kategori tanpa kalibrasi tidak pernah membuka pratinjau', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValueOnce({
            siklus: { cycle_id: 7 },
            langkah: [langkah('AKTIF', 'SELF', false), langkah('TERKUNCI')],
            langkah_aktif: 'SELF',
        });
        const w = pasang();
        await flushPromises();

        // Lembar terakhir SELF selesai di HC Learn: tetap di daftar lembar, bukan pratinjau.
        paApi.wizardPenilaianSaya.mockResolvedValueOnce({
            siklus: { cycle_id: 7 },
            langkah: [langkah('TUNTAS', 'SELF', false), langkah('AKTIF')],
            langkah_aktif: 'ATASAN',
        });
        await w.muat({ pertahankanPosisi: true });
        expect(w.kodeDilihat.value).toBe('SELF');
        expect(w.tampilan.value).toBe('lembar');

        // Dibuka dari ringkasan atau tombol: pratinjau ditolak, jatuh ke lembar.
        w.gantiTampilan('pratinjau');
        expect(w.tampilan.value).toBe('lembar');
        w.buka('SELF', 'pratinjau');
        expect(w.tampilan.value).toBe('lembar');
        expect(paApi.pratinjauLangkahSaya).not.toHaveBeenCalled();
    });

    it('pulang dari HC Learn: pratinjau kategori yang bisa dikalibrasi tampil dulu, sekali', async () => {
        const data = {
            siklus: { cycle_id: 7 },
            langkah: [langkah('TUNTAS', 'SELF', false), langkah('TUNTAS'), langkah('AKTIF', 'PEER')],
            langkah_aktif: 'PEER',
            pengiriman: { sudah_terkirim_semua: false },
        };
        paApi.wizardPenilaianSaya.mockResolvedValue(data);
        const w = pasang();
        await flushPromises();

        // SELF tanpa kalibrasi dilewati; ATASAN bisa dikalibrasi dan belum pernah dilihat.
        expect(w.kodeDilihat.value).toBe('ATASAN');
        expect(w.tampilan.value).toBe('pratinjau');

        // Sudah dilihat: pemuatan baru berikutnya mengikuti server.
        const lagi = pasang();
        await flushPromises();
        expect(lagi.kodeDilihat.value).toBe('PEER');
        expect(lagi.tampilan.value).toBe('lembar');
    });

    it('pratinjau yang dibuka selagi kategori belum tuntas tidak dihitung sudah dilihat', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValueOnce({
            siklus: { cycle_id: 7 },
            langkah: [langkah('AKTIF', 'PEER')],
            langkah_aktif: 'PEER',
            pengiriman: { sudah_terkirim_semua: false },
        });
        const w = pasang();
        await flushPromises();

        // Penilai mengintip matriks saat baru 1 dari 2 lembar terisi.
        w.gantiTampilan('pratinjau');
        await flushPromises();
        expect(paApi.pratinjauLangkahSaya).toHaveBeenCalled();

        // Lembar terakhir masuk; pulang dari HC Learn dengan pemuatan baru.
        paApi.wizardPenilaianSaya.mockResolvedValueOnce({
            siklus: { cycle_id: 7 },
            langkah: [langkah('TUNTAS', 'PEER')],
            langkah_aktif: 'RINGKASAN',
            pengiriman: { sudah_terkirim_semua: false },
        });
        const lagi = pasang();
        await flushPromises();

        expect(lagi.kodeDilihat.value).toBe('PEER');
        expect(lagi.tampilan.value).toBe('pratinjau');
    });

    it('tidak membelokkan ke pratinjau setelah semua terkirim', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValue({
            siklus: { cycle_id: 7 },
            langkah: [langkah('TUNTAS')],
            langkah_aktif: 'RINGKASAN',
            pengiriman: { sudah_terkirim_semua: true },
        });
        const w = pasang();
        await flushPromises();

        expect(w.tampilan.value).toBe('ringkasan');
    });

    it('memegang siklus pilihan server lalu bisa pindah siklus', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValue({
            siklus: { cycle_id: 7 },
            siklus_tersedia: [{ cycle_id: 7 }, { cycle_id: 9 }],
            langkah: [langkah('AKTIF')],
            langkah_aktif: 'ATASAN',
        });
        const w = pasang();
        await flushPromises();

        // Pemuatan pertama tanpa pilihan; muat ulang memakai siklus yang dipilih server.
        expect(paApi.wizardPenilaianSaya).toHaveBeenNthCalledWith(1, null);
        await w.muat({ pertahankanPosisi: true });
        expect(paApi.wizardPenilaianSaya).toHaveBeenLastCalledWith(7);

        w.gantiSiklus(9);
        await flushPromises();
        expect(paApi.wizardPenilaianSaya).toHaveBeenLastCalledWith(9);
    });

    it('kategori dari URL yang terkunci diabaikan', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValue({
            siklus: { cycle_id: 7 },
            langkah: [langkah('AKTIF'), langkah('TERKUNCI', 'PEER')],
            langkah_aktif: 'ATASAN',
        });
        const w = pasang({ kategoriAwal: 'PEER' });
        await flushPromises();

        expect(w.kodeDilihat.value).toBe('ATASAN');
    });

    it('berpindah ke pratinjau ketika langkah yang dilihat baru saja tuntas', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValueOnce({
            siklus: { cycle_id: 7 },
            langkah: [langkah('AKTIF'), langkah('TERKUNCI', 'PEER')],
            langkah_aktif: 'ATASAN',
        });
        const w = pasang();
        await flushPromises();

        paApi.wizardPenilaianSaya.mockResolvedValueOnce({
            siklus: { cycle_id: 7 },
            langkah: [langkah('TUNTAS'), langkah('AKTIF', 'PEER')],
            langkah_aktif: 'PEER',
        });
        await w.muat({ pertahankanPosisi: true });

        expect(w.kodeDilihat.value).toBe('ATASAN');
        expect(w.tampilan.value).toBe('pratinjau');
        expect(w.langkahBerikut.value.kode_kategori).toBe('PEER');
    });

    it('langsung ke ringkasan bila semua langkah tuntas dan tidak ada pratinjau tertunda', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValue({
            siklus: { cycle_id: 7 },
            // Tanpa kalibrasi; yang bisa dikalibrasi dibuka di pratinjaunya dulu (uji di atas).
            langkah: [langkah('TUNTAS', 'ATASAN', false)],
            langkah_aktif: 'RINGKASAN',
        });
        const w = pasang();
        await flushPromises();

        expect(w.tampilan.value).toBe('ringkasan');
        expect(paApi.lembarLangkahSaya).not.toHaveBeenCalled();
    });

    it('langkah terkunci tidak bisa dibuka', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValue({
            siklus: { cycle_id: 7 },
            langkah: [langkah('AKTIF'), langkah('TERKUNCI', 'PEER')],
            langkah_aktif: 'ATASAN',
        });
        const w = pasang();
        await flushPromises();

        w.buka('PEER');

        expect(w.kodeDilihat.value).toBe('ATASAN');
        expect(w.langkahBerikut.value).toBeNull();
    });

    it('menolak berpindah langkah selagi ada perubahan belum tersimpan, kecuali dipaksa', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValue({
            siklus: { cycle_id: 7 },
            langkah: [langkah('TUNTAS'), langkah('TUNTAS', 'PEER')],
            langkah_aktif: 'RINGKASAN',
        });
        const w = pasang();
        await flushPromises();
        w.kotor.value = true;

        expect(w.buka('PEER')).toBe(false);
        expect(w.buka('PEER', null, { paksa: true })).toBe(true);
        expect(w.kodeDilihat.value).toBe('PEER');
        expect(w.kotor.value).toBe(false);
    });

    it('menandai bersih SEBELUM simpan selesai dilaporkan, dan memakai pratinjau dari server', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValue({
            siklus: { cycle_id: 7 },
            langkah: [langkah('TUNTAS')],
            langkah_aktif: 'RINGKASAN',
        });
        paApi.simpanKalibrasiPenilai.mockResolvedValue({
            pratinjau: { orang: [{ assignment_id: 1 }], kompetensi: [] },
        });
        const w = pasang();
        await flushPromises();
        w.kotor.value = true;

        const ok = await w.simpanKalibrasi({ assignmentId: 1, perubahan: [], alasan: 'x' });

        expect(ok).toBe(true);
        expect(w.kotor.value).toBe(false);
        expect(w.pratinjau.value.orang[0].assignment_id).toBe(1);
    });

    it('galat simpan tetap kotor dan membawa kodenya', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValue({
            siklus: { cycle_id: 7 },
            langkah: [langkah('TUNTAS')],
            langkah_aktif: 'RINGKASAN',
        });
        paApi.simpanKalibrasiPenilai.mockRejectedValue(
            Object.assign(new Error('Lembar sudah terkirim'), { status: 409, error_code: 'PA_LEMBAR_BEKU' }),
        );
        const w = pasang();
        await flushPromises();
        w.kotor.value = true;

        const ok = await w.simpanKalibrasi({ assignmentId: 1, perubahan: [], alasan: 'x' });

        expect(ok).toBe(false);
        expect(w.kotor.value).toBe(true);
        expect(w.galatSimpan.value.kode).toBe('PA_LEMBAR_BEKU');
    });

    it('jawaban isi yang terlambat dari langkah yang sudah ditinggalkan tidak menimpa', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValue({
            siklus: { cycle_id: 7 },
            langkah: [langkah('TUNTAS'), langkah('TUNTAS', 'PEER')],
            langkah_aktif: 'RINGKASAN',
        });
        const w = pasang();
        await flushPromises();

        const lambat = tangguhan();
        paApi.pratinjauLangkahSaya.mockReturnValueOnce(lambat.janji);
        paApi.pratinjauLangkahSaya.mockResolvedValueOnce({ kategori: { kode: 'PEER' }, orang: [] });

        w.buka('ATASAN');
        w.buka('PEER');
        await flushPromises();
        lambat.lunasi({ kategori: { kode: 'ATASAN' }, orang: [] });
        await flushPromises();

        expect(w.pratinjau.value.kategori.kode).toBe('PEER');
    });

    it('tidak memuat ulang saat tab kembali aktif selagi ada perubahan belum tersimpan', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValue({
            siklus: { cycle_id: 7 },
            langkah: [langkah('TUNTAS')],
            langkah_aktif: 'RINGKASAN',
        });
        Object.defineProperty(document, 'visibilityState', { value: 'visible', configurable: true });
        const w = pasang();
        await flushPromises();
        paApi.wizardPenilaianSaya.mockClear();

        w.kotor.value = true;
        document.dispatchEvent(new Event('visibilitychange'));
        await flushPromises();
        expect(paApi.wizardPenilaianSaya).not.toHaveBeenCalled();

        w.kotor.value = false;
        document.dispatchEvent(new Event('visibilitychange'));
        await flushPromises();
        expect(paApi.wizardPenilaianSaya).toHaveBeenCalledTimes(1);
    });

    it('kembali ke tab memuat ulang SENYAP: isi lama tetap tampil, tanpa skeleton, posisi tetap', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValue({
            siklus: { cycle_id: 7 },
            langkah: [langkah('TUNTAS', 'PEER')],
            langkah_aktif: 'PEER',
        });
        paApi.pratinjauLangkahSaya.mockResolvedValue({ kategori: { kode: 'PEER' }, orang: [{ assignment_id: 1 }] });
        Object.defineProperty(document, 'visibilityState', { value: 'visible', configurable: true });
        const w = pasang();
        await flushPromises();
        expect(w.tampilan.value).toBe('pratinjau');

        const lambat = tangguhan();
        paApi.pratinjauLangkahSaya.mockReturnValueOnce(lambat.janji);
        document.dispatchEvent(new Event('visibilitychange'));
        await flushPromises();

        // Selama data baru diambil, isi lama tetap tampil dan tidak diganti skeleton.
        expect(w.memuatIsi.value).toBe(false);
        expect(w.pratinjau.value.orang).toHaveLength(1);

        lambat.lunasi({ kategori: { kode: 'PEER' }, orang: [{ assignment_id: 1 }, { assignment_id: 2 }] });
        await flushPromises();
        expect(w.pratinjau.value.orang).toHaveLength(2);
        expect(w.tampilan.value).toBe('pratinjau');
        expect(w.kodeDilihat.value).toBe('PEER');
    });

    it('simpan banyak berurutan dengan satu alasan; berhenti di yang gagal, yang sudah tersimpan tetap', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValue({ siklus: { cycle_id: 7 }, langkah: [langkah('TUNTAS', 'PEER')], langkah_aktif: 'PEER' });
        const w = pasang();
        await flushPromises();

        paApi.simpanKalibrasiPenilai
            .mockResolvedValueOnce({ pratinjau: { orang: [{ assignment_id: 1 }] } })
            .mockRejectedValueOnce(Object.assign(new Error('Ditolak server'), { status: 422 }));
        const tersimpan = [];
        const hasil = await w.simpanKalibrasiBanyak(
            [
                { assignmentId: 1, nama: 'Aji', perubahan: [{ competency_id: 11, skor: 70 }], alasan: 'Disejajarkan' },
                { assignmentId: 2, nama: 'Imam', perubahan: [{ competency_id: 11, skor: 60 }], alasan: 'Disejajarkan' },
                { assignmentId: 3, nama: 'Nanda', perubahan: [{ competency_id: 11, skor: 65 }], alasan: 'Disejajarkan' },
            ],
            { saatTersimpan: (id) => tersimpan.push(id) },
        );

        expect(paApi.simpanKalibrasiPenilai).toHaveBeenCalledTimes(2);
        expect(paApi.simpanKalibrasiPenilai.mock.calls[0][0]).toEqual({
            assignmentId: 1, perubahan: [{ competency_id: 11, skor: 70 }], perubahanJawaban: [], alasan: 'Disejajarkan',
        });
        expect(hasil).toEqual({ berhasil: 1, gagal: 2 });
        expect(tersimpan).toEqual([1]);
        expect(w.galatSimpan.value.pesan).toContain('Gagal menyimpan Imam');
        expect(w.menyimpanId.value).toBe(0);
        expect(w.progresSimpan.value).toBeNull();
    });

    it('galat membawa kode dan nomor permintaan', async () => {
        paApi.wizardPenilaianSaya.mockRejectedValue(
            Object.assign(new Error('Ditolak'), {
                status: 404,
                error_code: 'PA_LANGKAH_TIDAK_DIKENAL',
                request_id: 'r-9',
            }),
        );
        const w = pasang();
        await flushPromises();

        expect(w.galat.value).toEqual({
            pesan: 'Ditolak',
            status: 404,
            kode: 'PA_LANGKAH_TIDAK_DIKENAL',
            requestId: 'r-9',
        });
    });
});
