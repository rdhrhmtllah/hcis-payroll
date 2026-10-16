import { describe, it, expect, vi } from 'vitest';
import { createAlur } from './useVersiAlur.js';

const langkah = (aktif) => [
    { Nomor: 1, Nama: 'Pengajuan', Kelompok: 'PENGAJUAN', Keadaan: aktif > 1 ? 'selesai' : 'berjalan', Terkunci: 1 > aktif },
    { Nomor: 2, Nama: 'Review', Kelompok: 'REVIEW', Keadaan: aktif > 2 ? 'selesai' : aktif === 2 ? 'berjalan' : 'belum', Terkunci: 2 > aktif },
    { Nomor: 3, Nama: 'Pengesahan', Kelompok: 'PENGESAHAN', Keadaan: 'belum', Terkunci: 3 > aktif },
    { Nomor: 4, Nama: 'Pemberkasan', Kelompok: 'TERBIT', Keadaan: 'belum', Terkunci: 4 > aktif },
];

const balasan = (aktif) => ({
    data: {
        result: {
            Langkah: langkah(aktif),
            Langkah_Aktif: aktif,
            Aksi_Utama: { Jenis: 'SIMPAN_LANJUT', Label: 'Simpan & Lanjut ke Pengesahan', Ke_Tahap: 'APPROVAL', Syarat_Kurang: [] },
            Aksi_Lain: [],
            Tahap: { Kode_Tahap: 'REVIEW', Nama_Tahap: 'Review' },
        },
    },
});

const apiPalsu = (aktif = 2) => ({ get: vi.fn().mockResolvedValue(balasan(aktif)) });

describe('useVersiAlur', () => {
    it('membuka langkah yang ditunjuk server, bukan langkah pertama yang isinya kurang', async () => {
        const alur = createAlur(apiPalsu(2));
        await alur.muat('abc');

        expect(alur.langkahAktif).toBe(2);
        expect(alur.dibuka).toBe(2);
        expect(alur.kelompok).toBe('REVIEW');
    });

    it('menolak membuka langkah yang terkunci', async () => {
        const alur = createAlur(apiPalsu(2));
        await alur.muat('abc');

        expect(alur.buka(3)).toBe(false);
        expect(alur.dibuka).toBe(2);
    });

    it('mengizinkan kembali ke langkah yang sudah dilewati', async () => {
        const alur = createAlur(apiPalsu(2));
        await alur.muat('abc');

        expect(alur.buka(1)).toBe(true);
        expect(alur.dibuka).toBe(1);
    });

    it('memuat ulang sesudah simpan tidak menyeret pemakai keluar dari langkahnya', async () => {
        /* REGRESI atas gejala aslinya: tiap simpan memanggil muat ulang, dan
           muat ulang memindahkan layar ke langkah lain — kerja admin tersimpan
           tapi layarnya melompat, sehingga terasa seperti tidak tersimpan. */
        const alur = createAlur(apiPalsu(2));
        await alur.muat('abc');
        alur.buka(1);

        await alur.muat('abc', { pertahankanDibuka: true });

        expect(alur.dibuka).toBe(1);
    });

    it('tidak mempertahankan langkah yang berubah jadi terkunci', async () => {
        /* Sesudah versi MUNDUR tahap, langkah tempat admin berdiri bisa
           terkunci. Bertahan di sana berarti menyodorkan formulir yang tak bisa
           disimpan. */
        const api = { get: vi.fn() };
        api.get.mockResolvedValueOnce(balasan(3));
        const alur = createAlur(api);
        await alur.muat('abc');
        alur.buka(3);

        api.get.mockResolvedValueOnce(balasan(1));
        await alur.muat('abc', { pertahankanDibuka: true });

        expect(alur.dibuka).toBe(1);
    });

    /* Sejak 2026-08-27 endpoint template mengirim BENTUK YANG SAMA dengan
       endpoint alur versi — termasuk Aksi_Utama. Terkunci/Lengkap juga sudah
       dihitung server, bukan lagi ditempelkan layar. */
    const hasilTemplate = (aksi) => ({
        data: {
            result: {
                Langkah: [
                    { Nomor: 1, Nama: 'Pengajuan', Kelompok: 'PENGAJUAN', Keadaan: 'belum', Terkunci: false, Lengkap: true, Kurang: [] },
                    { Nomor: 2, Nama: 'Review', Kelompok: 'REVIEW', Keadaan: 'belum', Terkunci: true, Lengkap: false, Kurang: [] },
                    { Nomor: 3, Nama: 'Pengesahan', Kelompok: 'PENGESAHAN', Keadaan: 'belum', Terkunci: true, Lengkap: false, Kurang: [] },
                    { Nomor: 4, Nama: 'Pemberkasan', Kelompok: 'TERBIT', Keadaan: 'belum', Terkunci: true, Lengkap: false, Kurang: [] },
                ],
                Langkah_Aktif: 1,
                Aksi_Utama: aksi ?? {
                    Jenis: 'SIMPAN_LANJUT', Label: 'Simpan & Lanjut ke Review',
                    Ke_Tahap: 'REVIEW', Syarat_Kurang: [],
                },
                Aksi_Lain: [],
            },
        },
    });

    it('versi baru memakai template: langkah 1 terbuka, sisanya terkunci', async () => {
        const api = { get: vi.fn().mockResolvedValue(hasilTemplate()) };
        const alur = createAlur(api);
        await alur.muatTemplate();

        expect(alur.dibuka).toBe(1);
        expect(alur.langkah[0].Terkunci).toBe(false);
        expect(alur.langkah[1].Terkunci).toBe(true);
        expect(alur.buka(2)).toBe(false);
    });

    /**
     * REGRESI. Layar pernah memaku aksi versi baru sebagai
     * `{ Jenis: 'SIMPAN', Label: 'Simpan Draft Versi' }`, dan itu memaksa admin
     * melewati satu klik yang tak dimintanya: satu untuk menyimpan, satu lagi
     * untuk lanjut — padahal ia membuka wizard ini memang untuk mendefinisikan
     * versi lalu meneruskannya.
     */
    it('aksi versi baru datang dari server, tidak dipaku layar', async () => {
        const api = { get: vi.fn().mockResolvedValue(hasilTemplate()) };
        const alur = createAlur(api);
        await alur.muatTemplate();

        expect(alur.aksiUtama.Jenis).toBe('SIMPAN_LANJUT');
        expect(alur.aksiUtama.Label).toBe('Simpan & Lanjut ke Review');
        expect(alur.aksiUtama.Ke_Tahap).toBe('REVIEW');
    });

    /** Kalau server memang menahannya, layar menurut — tak mengarang tombol maju. */
    it('menurut saat server menahan di simpan biasa', async () => {
        const api = {
            get: vi.fn().mockResolvedValue(hasilTemplate({
                Jenis: 'SIMPAN', Label: 'Simpan', Ke_Tahap: null,
                Syarat_Kurang: ['Ringkasan perubahan'],
            })),
        };
        const alur = createAlur(api);
        await alur.muatTemplate();

        expect(alur.aksiUtama.Jenis).toBe('SIMPAN');
        expect(alur.aksiUtama.Syarat_Kurang).toEqual(['Ringkasan perubahan']);
    });

    it('galat dari server disampaikan apa adanya, bukan ditelan', async () => {
        const api = { get: vi.fn().mockRejectedValue({ response: { data: { message: 'Versi tidak ditemukan' } } }) };
        const alur = createAlur(api);
        await alur.muat('abc');

        expect(alur.galat).toBe('Versi tidak ditemukan');
        expect(alur.memuat).toBe(false);
    });
});

describe('useVersiAlur — tempat berdiri bertahan melewati muat ulang', () => {
    /**
     * GEJALANYA: admin menuntaskan Pemberkasan, menekan "Simpan & Lanjut ke
     * Tinjauan Akhir", membaca rekapnya — lalu menyegarkan halaman dan
     * terlempar kembali ke Pemberkasan, berulang kali.
     *
     * SEBABNYA: Tinjauan Akhir dicapai lewat Aksi_Antar, perpindahan LAYAR yang
     * sengaja TIDAK menggeser tahap — penerbitan tak bisa ditarik, jadi rekapnya
     * harus dibaca lebih dulu. Akibatnya tahap tetap di Pemberkasan dan
     * Langkah_Aktif tetap 4, sementara tempat berdiri admin tak tersimpan di
     * mana pun. Tiap pemuatan memulangkannya ke tahap.
     */
    const langkahTerbit = [
        { Nomor: 1, Nama: 'Pengajuan', Kelompok: 'PENGAJUAN', Terkunci: false },
        { Nomor: 2, Nama: 'Review', Kelompok: 'REVIEW', Terkunci: false },
        { Nomor: 3, Nama: 'Pengesahan', Kelompok: 'PENGESAHAN', Terkunci: false },
        { Nomor: 4, Nama: 'Pemberkasan', Kelompok: 'TERBIT', Terkunci: false },
        /* Tak terkunci meski belum ditempati: Aksi_Antar menunjuk ke sini, dan
           langkah tujuan penerbitan memang ada untuk DILIHAT sebelum terbit. */
        { Nomor: 5, Nama: 'Tinjauan Akhir', Kelompok: 'TINJAUAN', Terkunci: false },
    ];

    const apiTerbit = () => ({
        get: vi.fn().mockResolvedValue({
            data: {
                result: {
                    Langkah: langkahTerbit,
                    Langkah_Aktif: 4,
                    Aksi_Utama: { Jenis: 'TERBITKAN', Label: 'Terbitkan Versi', Ke_Langkah: 5, Syarat_Kurang: [] },
                    Aksi_Antar: { Jenis: 'LANJUT_LANGKAH', Label: 'Simpan & Lanjut ke Tinjauan Akhir', Ke_Langkah: 5 },
                    Aksi_Lain: [],
                    Tahap: { Kode_Tahap: 'APPROVED', Nama_Tahap: 'Pemberkasan' },
                },
            },
        }),
    });

    it('mengembalikan admin ke Tinjauan Akhir, bukan ke tahapnya', async () => {
        const alur = createAlur(apiTerbit());
        await alur.muat('abc', { dibukaAwal: 5 });

        expect(alur.langkahAktif).toBe(4);
        expect(alur.dibuka).toBe(5);
        expect(alur.kelompok).toBe('TINJAUAN');
    });

    it('tanpa permintaan pemanggil, tetap jatuh ke langkah yang ditunjuk server', async () => {
        const alur = createAlur(apiTerbit());
        await alur.muat('abc');

        expect(alur.dibuka).toBe(4);
    });

    /**
     * Yang diminta hanyalah TEMPAT BERDIRI, bukan izin. Versi yang sudah mundur
     * tahap membuat langkah di depannya terkunci lagi, dan URL lama tak boleh
     * menyodorkan formulir yang tak bisa disimpan.
     */
    it('mengabaikan permintaan ke langkah yang terkunci', async () => {
        const alur = createAlur(apiPalsu(2));
        await alur.muat('abc', { dibukaAwal: 4 });

        expect(alur.dibuka).toBe(2);
    });

    it('mengabaikan angka yang tak masuk akal', async () => {
        const alur = createAlur(apiPalsu(2));
        await alur.muat('abc', { dibukaAwal: 99 });

        expect(alur.dibuka).toBe(2);
    });

    /** Langkah yang sedang dibuka tetap menang atas permintaan pemanggil. */
    it('pertahankanDibuka mengalahkan langkah yang diminta', async () => {
        const alur = createAlur(apiTerbit());
        await alur.muat('abc', { dibukaAwal: 5 });
        alur.buka(2);

        await alur.muat('abc', { pertahankanDibuka: true, dibukaAwal: 5 });

        expect(alur.dibuka).toBe(2);
    });
});
