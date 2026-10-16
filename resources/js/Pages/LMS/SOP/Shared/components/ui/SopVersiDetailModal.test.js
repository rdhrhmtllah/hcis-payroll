import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import SopVersiDetailModal from './SopVersiDetailModal.vue';

vi.mock('axios', () => ({ default: { get: vi.fn(() => Promise.resolve({ data: { result: null } })) } }));
vi.mock('@inertiajs/vue3', () => ({ router: { visit: vi.fn() } }));

/**
 * Lampiran review punya DUA alamat, dan hanya satu yang bisa dibuka.
 *
 * `SopVersiReviewService::lampiranPerReview()` mengirim `Path_Berkas` — jalur
 * objek di GCS seperti `sop/review-lampiran/2026/08/609-….png` — berdampingan
 * dengan `Url`, alamat bertanda tangan berumur 20 menit. Layar dulu memakai
 * `Path_Berkas`, dan browser membacanya sebagai alamat RELATIF terhadap
 * halaman: thumbnail selalu gagal dimuat dan pratinjaunya membuka alamat yang
 * tak pernah ada. Tak ada galat yang muncul di mana pun — hanya gambar rusak.
 *
 * Yang dijaga di sini bukan "field bernama url ada", melainkan bahwa yang
 * dipakai adalah alamat MUTLAK. Itulah yang dilanggar dulu, dan itu pula yang
 * akan dilanggar lagi bila suatu saat ada yang menyederhanakan normalisasinya.
 */
const lampiranPng = {
    Id_Lampiran: 'L1',
    Path_Berkas: 'sop/review-lampiran/2026/08/609-2d7fbb8b.png',
    Url: 'https://storage.googleapis.com/hcisxkpi/sop/review-lampiran/2026/08/609-2d7fbb8b.png?sig=abc',
    Nama_Berkas: 'logo.png',
    Tipe_Berkas: 'image/png',
    Ukuran_Byte: 2946,
};

/** Penandatanganan bisa gagal (kredensial GCS bermasalah) — Url null, path tetap ada. */
const lampiranTanpaUrl = {
    Id_Lampiran: 'L2',
    Path_Berkas: 'sop/review-lampiran/2026/08/609-63ea8547.jpg',
    Url: null,
    Nama_Berkas: 'bukti.jpg',
    Tipe_Berkas: 'image/jpeg',
    Ukuran_Byte: 50389,
};

const lampiranPdf = {
    Id_Lampiran: 'L3',
    Path_Berkas: 'sop/review-lampiran/2026/08/609-aa11.pdf',
    Url: 'https://storage.googleapis.com/hcisxkpi/sop/review-lampiran/2026/08/609-aa11.pdf?sig=xyz',
    Nama_Berkas: 'notulen.pdf',
    Tipe_Berkas: 'application/pdf',
    Ukuran_Byte: 1024,
};

function pasang(lampiran) {
    /* modelValue false: badan dialog tak dirender dan pengamatnya tak memanggil
       server, jadi yang diuji murni normalisasi datanya. */
    const wrapper = mount(SopVersiDetailModal, { props: { modelValue: false } });

    wrapper.vm.detailBackend = {
        Review: [{ Id_Review: 'R1', Review_Ke: 1, Tanggal_Review: '2026-08-27', Hasil_Review: '<p>ok</p>', Lampiran: lampiran }],
    };

    return wrapper;
}

describe('SopVersiDetailModal — lampiran review', () => {
    it('memakai alamat mutlak bertanda tangan, bukan jalur objek GCS', () => {
        const { LampiranTampil } = pasang([lampiranPng]).vm.reviewTampil[0];

        expect(LampiranTampil[0].url).toBe(lampiranPng.Url);
        expect(LampiranTampil[0].url.startsWith('http')).toBe(true);
        expect(LampiranTampil[0].url).not.toBe(lampiranPng.Path_Berkas);
    });

    it('menandai lampiran yang alamatnya gagal ditandatangani sebagai tak bisa dibuka', () => {
        const { LampiranTampil } = pasang([lampiranTanpaUrl]).vm.reviewTampil[0];

        /* Jatuh ke Path_Berkas di sini akan menghidupkan kembali persis cacatnya:
           tombol yang tampak bisa diklik, membuka alamat yang tak ada. */
        expect(LampiranTampil[0].url).toBeNull();
    });

    it('membedakan gambar dari berkas lain supaya non-gambar tak masuk <img>', () => {
        const { LampiranTampil } = pasang([lampiranPng, lampiranPdf]).vm.reviewTampil[0];

        expect(LampiranTampil[0].gambar).toBe(true);
        expect(LampiranTampil[1].gambar).toBe(false);
        expect(LampiranTampil[1].ikon).toBe('file-earmark-pdf');
    });

    it('viewer hanya menerima lampiran yang bisa dimuat, dan mulai dari yang diklik', () => {
        const wrapper = pasang([lampiranTanpaUrl, lampiranPng, lampiranPdf]);
        const daftar = wrapper.vm.reviewTampil[0].LampiranTampil;

        // indeks 2 = lampiranPdf pada daftar penuh
        wrapper.vm.bukaViewerReview(daftar, 2);

        expect(wrapper.vm.viewerBuka).toBe(true);
        expect(wrapper.vm.viewerFiles).toHaveLength(2);
        expect(wrapper.vm.viewerFiles.every((f) => f.url.startsWith('http'))).toBe(true);
        /* Indeks dihitung ulang terhadap daftar tersaring: tanpa itu, mengklik
           berkas terakhir membuka berkas yang salah. */
        expect(wrapper.vm.viewerData.name).toBe('notulen.pdf');
    });

    it('tidak membuka viewer bila tak satu pun lampiran punya alamat', () => {
        const wrapper = pasang([lampiranTanpaUrl]);

        wrapper.vm.bukaViewerReview(wrapper.vm.reviewTampil[0].LampiranTampil, 0);

        expect(wrapper.vm.viewerBuka).toBe(false);
    });
});

describe('SopVersiDetailModal — keadaan versi', () => {
    const pasangVersi = (raw) => {
        const wrapper = mount(SopVersiDetailModal, { props: { modelValue: false } });
        wrapper.vm.detailBackend = raw;

        return wrapper;
    };

    it('READY bukan arsip obsolete', () => {
        /* Cabang yang dulu cuma mengenali PUBLISHED dan DRAFT menjatuhkan READY
           ke cabang terakhir, sehingga versi yang menunggu diterbitkan dilabeli
           kebalikan artinya. */
        expect(pasangVersi({ Status: 'READY' }).vm.statusLabel).toBe('Siap Diterbitkan');
    });

    it('berlaku ditentukan penunjuk dokumen, bukan status terbit', () => {
        expect(pasangVersi({ Status: 'PUBLISHED', Is_Versi_Aktif: true }).vm.keadaanVersi).toBe('berlaku');
        expect(pasangVersi({ Status: 'PUBLISHED', Is_Versi_Aktif: false }).vm.keadaanVersi).toBe('terbit-lepas');
    });

    it('tanpa keterangan penunjuk, versi terbit tetap dianggap berlaku', () => {
        /* Data baris ringkas tak membawa Is_Versi_Aktif. Menuduh anomali
           berdasarkan field yang memang tak dikirim lebih buruk daripada diam. */
        expect(pasangVersi({ Status: 'PUBLISHED' }).vm.keadaanVersi).toBe('berlaku');
    });

    it('tahap CANCEL menang atas status apa pun', () => {
        expect(pasangVersi({ Status: 'DRAFT', Tahap_Kode: 'CANCEL' }).vm.keadaanVersi).toBe('batal');
    });

    /**
     * Dibatalkan dikirim server sebagai fakta. Layar sempat menyimpulkannya
     * sendiri dengan mencari kata "batal" pada nama tahap — yang ternyata
     * berbunyi "Cancel", sehingga cabang itu tak pernah menyala.
     */
    it('mengikuti Dibatalkan dari server, tanpa perlu menebak dari nama tahap', () => {
        expect(pasangVersi({ Status: 'DRAFT', Dibatalkan: true }).vm.keadaanVersi).toBe('batal');
    });

    it('nama tahap "Cancel" saja tak pernah cukup untuk menyimpulkan pembatalan', () => {
        /* Bukti bahwa penebakan lama memang buta: tanpa Tahap_Kode maupun
           Dibatalkan, nama tahapnya tak boleh dipakai menyimpulkan apa pun. */
        expect(pasangVersi({ Status: 'DRAFT', Tahap_Nama: 'Cancel' }).vm.keadaanVersi).not.toBe('batal');
    });
});

describe('SopVersiDetailModal — form kosong dokumen FORM', () => {
    const formKosong = [
        { Id_Lampiran: 1, Id_Master_Konten: 'kA', Judul: 'Form kosong Word', Ext: 'docx', Urutan: 0 },
        { Id_Lampiran: 2, Id_Master_Konten: 'kB', Judul: 'Form kosong Excel', Ext: 'xlsx', Urutan: 1 },
    ];

    it('membaca daftar form kosong dari detail versi, berurutan', () => {
        const wrapper = mount(SopVersiDetailModal, { props: { modelValue: false } });
        wrapper.vm.detailBackend = { Lampiran: formKosong };

        expect(wrapper.vm.formKosong.map((l) => l.Judul)).toEqual(['Form kosong Word', 'Form kosong Excel']);
    });

    it('tanpa lampiran daftarnya kosong, bukan undefined', () => {
        const wrapper = mount(SopVersiDetailModal, { props: { modelValue: false } });
        wrapper.vm.detailBackend = {};

        expect(wrapper.vm.formKosong).toEqual([]);
    });

    it('mengunduh lewat berkas konten admin sebagai unduhan', async () => {
        const axios = (await import('axios')).default;
        axios.get.mockResolvedValueOnce({ data: { result: { url: 'https://gcs/kosong.docx' } } });
        const wrapper = mount(SopVersiDetailModal, { props: { modelValue: false } });
        const klik = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});

        await wrapper.vm.unduhFormKosong(formKosong[0]);

        expect(axios.get).toHaveBeenCalledWith('/api/v1/sop/admin/dokumen/konten/kA/berkas?unduh=1');
        expect(klik).toHaveBeenCalled();
        expect(wrapper.vm.mengunduhFormKosong).toBe(null);
    });

    it('gagal mengunduh menyebut alasannya, bukan diam', async () => {
        const axios = (await import('axios')).default;
        const { hcisMessage } = await import('@/components/ui');
        const galat = vi.spyOn(hcisMessage, 'error').mockImplementation(() => {});
        axios.get.mockRejectedValueOnce({ response: { data: { message: 'Konten tidak ditemukan.' } } });
        const wrapper = mount(SopVersiDetailModal, { props: { modelValue: false } });

        await wrapper.vm.unduhFormKosong(formKosong[1]);

        expect(galat).toHaveBeenCalledWith('Konten tidak ditemukan.');
        expect(wrapper.vm.mengunduhFormKosong).toBe(null);
    });
});
