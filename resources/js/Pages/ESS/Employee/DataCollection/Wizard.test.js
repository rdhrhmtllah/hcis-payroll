import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Wizard from './Wizard.vue';

vi.mock('@inertiajs/vue3', () => ({
    router: { post: vi.fn() },
    usePage: () => ({ props: {} }),
}));

// Draf disimpan lewat axios; di test cukup dijadikan janji yang selalu sukses.
vi.mock('axios', () => ({
    default: { post: vi.fn(() => Promise.resolve({ data: { ok: true } })) },
}));

const skema = {
    versi: 1,
    langkah: [
        { kunci: 'identitas', judul: 'Identitas', field: ['nama_lengkap'] },
        { kunci: 'kontak', judul: 'Kontak', field: ['email'] },
    ],
    field: {
        nama_lengkap: { label: 'Nama lengkap', tipe: 'teks' },
        email: { label: 'Email aktif', tipe: 'teks' },
    },
};

// Isian pilihan/tanggal tidak lagi berupa elemen native yang bisa di-setValue;
// mendorongnya lewat komponen justru lebih jujur — itu jalur yang benar-benar
// dipakai saat orang memilih dari daftar.
const isi = async (w, kunci, nilai) => {
    const bidang = w.findAllComponents({ name: 'BidangIsian' }).find((b) => b.props('kunci') === kunci);
    bidang.vm.$emit('update:modelValue', nilai);

    await w.vm.$nextTick();
};

const buat = (props = {}) =>
    mount(Wizard, {
        props: {
            skema,
            status: { status: 'BELUM', sisa_tunda: 3, terkunci: false, draft_langkah: 0 },
            draft: { data: {}, langkah: 0 },
            pilihanDivisi: [],
            ...props,
        },
    });

describe('Wizard pengumpulan data', () => {
    it('memulai di langkah pertama dan hanya menampilkan fieldnya', () => {
        const w = buat();

        expect(w.text()).toContain('Identitas');
        expect(w.find('#pd-nama_lengkap').exists()).toBe(true);
        expect(w.find('#pd-email').exists()).toBe(false);
    });

    it('menolak maju bila field wajib pada langkah ini masih kosong', async () => {
        const w = buat();

        await w.find('[data-uji="lanjut"]').trigger('click');

        expect(w.find('#pd-nama_lengkap').exists()).toBe(true);
        expect(w.text()).toContain('Wajib diisi');
    });

    it('maju ke langkah berikutnya setelah field terisi', async () => {
        const w = buat();

        await w.find('#pd-nama_lengkap').setValue('Budi');
        await w.find('[data-uji="lanjut"]').trigger('click');

        expect(w.find('#pd-email').exists()).toBe(true);
    });

    it('memulihkan draf ke langkah yang tersimpan', () => {
        const w = buat({ draft: { data: { nama_lengkap: 'Budi' }, langkah: 1 } });

        expect(w.find('#pd-email').exists()).toBe(true);
    });

    it('menampilkan panel peninjauan setelah langkah terakhir', async () => {
        const w = buat({ draft: { data: { nama_lengkap: 'Budi' }, langkah: 1 } });

        await w.find('#pd-email').setValue('a@b.co');
        await w.find('[data-uji="lanjut"]').trigger('click');

        expect(w.text()).toContain('Periksa sekali lagi');
        expect(w.find('[data-uji="kirim"]').exists()).toBe(true);
    });

    it('bisa kembali dari peninjauan ke langkah yang dipilih', async () => {
        const w = buat({ draft: { data: { nama_lengkap: 'Budi' }, langkah: 1 } });

        await w.find('#pd-email').setValue('a@b.co');
        await w.find('[data-uji="lanjut"]').trigger('click');
        await w.find('[data-uji="ubah-0"]').trigger('click');

        expect(w.find('#pd-nama_lengkap').exists()).toBe(true);
        expect(w.find('#pd-nama_lengkap').element.value).toBe('Budi');
    });

    it('mengunci tombol kirim selama pengiriman berlangsung agar tidak dobel', async () => {
        const w = buat({ draft: { data: { nama_lengkap: 'Budi' }, langkah: 1 } });

        await w.find('#pd-email').setValue('a@b.co');
        await w.find('[data-uji="lanjut"]').trigger('click');
        await w.find('[data-uji="konfirmasi-data"]').setValue(true);

        const kirim = w.find('[data-uji="kirim"]');
        await kirim.trigger('click');

        expect(w.find('[data-uji="kirim"]').attributes('disabled')).toBeDefined();
    });

    it('mengunci tombol kirim sampai pernyataan kebenaran data dicentang', async () => {
        const { router } = await import('@inertiajs/vue3');
        router.post.mockClear();

        const w = buat({ draft: { data: { nama_lengkap: 'Budi' }, langkah: 1 } });

        await w.find('#pd-email').setValue('a@b.co');
        await w.find('[data-uji="lanjut"]').trigger('click');

        expect(w.find('[data-uji="kirim"]').attributes('disabled')).toBeDefined();

        // Klik yang tetap sampai (mis. atribut disabled dibuang lewat devtools)
        // tidak boleh mengirim apa pun.
        const tombol = w.find('[data-uji="kirim"]');
        tombol.element.removeAttribute('disabled');
        await tombol.trigger('click');
        expect(router.post).not.toHaveBeenCalled();

        await w.find('[data-uji="konfirmasi-data"]').setValue(true);

        expect(w.find('[data-uji="kirim"]').attributes('disabled')).toBeUndefined();

        await w.find('[data-uji="kirim"]').trigger('click');

        expect(router.post).toHaveBeenCalledTimes(1);
        expect(router.post.mock.calls[0][1]).toMatchObject({ konfirmasi: true });
    });

    // -------------------------------------------------------------------------
    // Sifat field DIDEKLARASIKAN, tidak ditebak dari labelnya.
    //
    // Penebakan lewat potongan kata pernah mengunci formulir ini: label
    // 'Status Pernikahan' mengandung 'nik', sehingga pertanyaan pilihan itu
    // dituntut '16 digit angka sesuai KTP'. Tidak ada jawaban yang bisa
    // memuaskannya — orangnya berhenti di langkah itu.
    // -------------------------------------------------------------------------

    const skemaNikah = {
        versi: 2,
        langkah: [{ kunci: 'kepegawaian', judul: 'Info Pekerjaan', field: ['status_pernikahan'] }],
        field: {
            status_pernikahan: {
                label: 'Status Pernikahan',
                tipe: 'pilihan',
                pilihan: ['Belum Menikah', 'Menikah', 'Cerai Hidup', 'Cerai Mati'],
            },
        },
    };

    it('tidak menuntut 16 digit KTP pada pertanyaan Status Pernikahan', async () => {
        const w = buat({ skema: skemaNikah });

        await isi(w, 'status_pernikahan', 'Menikah');
        await w.find('[data-uji="lanjut"]').trigger('click');

        expect(w.text()).not.toContain('16 digit');
    });

    it('membiarkan orang lanjut setelah memilih status pernikahan', async () => {
        const w = buat({ skema: skemaNikah });

        await isi(w, 'status_pernikahan', 'Belum Menikah');
        await w.find('[data-uji="lanjut"]').trigger('click');

        // Langkah terakhir -> tombolnya membawa ke peninjauan.
        expect(w.find('[data-uji="kirim"]').exists()).toBe(true);
    });

    it('tetap menuntut 16 digit pada field yang memang NIK', async () => {
        const w = buat({
            skema: {
                versi: 2,
                langkah: [{ kunci: 'identitas', judul: 'Data Diri', field: ['nik_ktp'] }],
                field: {
                    nik_ktp: { label: 'Nomor KTP (NIK)', tipe: 'teks', format: 'nik', maks_digit: 16 },
                },
            },
        });

        await w.find('#pd-nik_ktp').setValue('12345');
        await w.find('[data-uji="lanjut"]').trigger('click');

        expect(w.text()).toContain('16 digit');
    });

    it('tidak menyangka field ber-format hp sebagai NIK, dan sebaliknya', async () => {
        const w = buat({
            skema: {
                versi: 2,
                langkah: [{ kunci: 'kontak', judul: 'Kontak', field: ['no_hp'] }],
                field: {
                    no_hp: {
                        label: 'Nomor HP / WhatsApp',
                        tipe: 'teks',
                        format: 'hp',
                        maks_digit: 13,
                        min_digit: 9,
                    },
                },
            },
        });

        await w.find('#pd-no_hp').setValue('81234567890');
        await w.find('[data-uji="lanjut"]').trigger('click');

        expect(w.text()).not.toContain('16 digit');
    });

    // -------------------------------------------------------------------------
    // Tanggal lahir.
    //
    // Sebelumnya wizard sama sekali tidak memeriksanya, jadi tanggal mustahil
    // (26 Agu 2026 — hari yang belum datang) lolos empat langkah, baru ditolak
    // SERVER saat Kirim, dengan kalimat mesin "The tanggal lahir field must be
    // a date before -15 years." Yang salah ditemukan di ujung, dalam bahasa
    // yang bukan bahasa penggunanya.
    // -------------------------------------------------------------------------

    const skemaLahir = {
        versi: 2,
        langkah: [{ kunci: 'identitas', judul: 'Data Diri', field: ['tanggal_lahir'] }],
        field: {
            tanggal_lahir: {
                label: 'Tanggal Lahir',
                tipe: 'tanggal',
                format: 'tanggal_lahir',
            },
        },
    };

    it('menahan tanggal lahir di masa depan pada langkahnya sendiri', async () => {
        // Tanggalnya DIHITUNG, tidak ditulis mati. Versi sebelumnya memakai
        // '2026-08-26' yang memang masa depan saat uji ini ditulis lalu diam-diam
        // menjadi masa lalu — uji berubah dari menjaga sesuatu menjadi menuntut
        // hal yang mustahil, dan merahnya terbaca seperti kerusakan kode.
        // Tanggalnya dirakit dari komponen LOKAL, bukan toISOString().
        //
        // toISOString() memakai UTC. Di WIB (+07:00), setiap jam antara 00:00
        // dan 07:00 masih terhitung tanggal kemarin di UTC — sehingga "besok"
        // menghasilkan HARI INI, dan hari ini lolos before_or_equal:today.
        // Versi pertama perbaikan ini memakai toISOString() dan lulus hanya
        // karena dijalankan pukul 22:14; ia gagal pukul 02:58 keesokan harinya.
        const b = new Date(Date.now() + 86400000);
        const besok = [
            b.getFullYear(),
            String(b.getMonth() + 1).padStart(2, '0'),
            String(b.getDate()).padStart(2, '0'),
        ].join('-');
        const w = buat({ skema: skemaLahir, draft: { data: { tanggal_lahir: besok }, langkah: 0 } });

        await w.find('[data-uji="lanjut"]').trigger('click');

        expect(w.find('[data-uji="kirim"]').exists()).toBe(false);
        expect(w.text()).not.toMatch(/must be|years/i);
    });

    it('meloloskan tanggal lahir yang wajar', async () => {
        const w = buat({ skema: skemaLahir, draft: { data: { tanggal_lahir: '1995-04-17' }, langkah: 0 } });

        await w.find('[data-uji="lanjut"]').trigger('click');

        expect(w.find('[data-uji="kirim"]').exists()).toBe(true);
    });

    it('menahan nomor HP yang tidak diawali 8 di langkahnya, bukan di ujung', async () => {
        const w = buat({
            skema: {
                versi: 2,
                langkah: [{ kunci: 'kontak', judul: 'Kontak', field: ['no_hp'] }],
                field: {
                    no_hp: { label: 'Nomor HP / WhatsApp', tipe: 'teks', format: 'hp', prefix: '8', maks_digit: 13, min_digit: 9 },
                },
            },
            draft: { data: { no_hp: '02123333333333' }, langkah: 0 },
        });

        await w.find('[data-uji="lanjut"]').trigger('click');

        expect(w.find('[data-uji="kirim"]').exists()).toBe(false);
        expect(w.text()).toMatch(/diawali 8/i);
    });
});

// ---------------------------------------------------------------------------
// Pertanyaan bersyarat: jumlah tanggungan hanya untuk yang menikah.
// ---------------------------------------------------------------------------
const skemaBersyarat = {
    versi: 3,
    langkah: [
        {
            kunci: 'kepegawaian',
            judul: 'Info Pekerjaan',
            field: ['status_pernikahan', 'jumlah_tanggungan'],
        },
    ],
    field: {
        status_pernikahan: {
            label: 'Status Pernikahan',
            tipe: 'pilihan',
            pilihan: ['Belum Menikah', 'Menikah', 'Cerai Hidup', 'Cerai Mati'],
        },
        jumlah_tanggungan: {
            label: 'Jumlah Tanggungan',
            tipe: 'teks',
            format: 'angka',
            maks_digit: 2,
            nilai_min: 0,
            nilai_maks: 20,
            tampil_jika: { field: 'status_pernikahan', nilai: ['Menikah', 'Cerai Hidup', 'Cerai Mati'] },
        },
    },
};

const buatBersyarat = (props = {}) =>
    mount(Wizard, {
        props: {
            skema: skemaBersyarat,
            status: { status: 'BELUM', sisa_tunda: 3, terkunci: false, draft_langkah: 0 },
            draft: { data: {}, langkah: 0 },
            pilihanDivisi: [],
            ...props,
        },
    });

describe('Wizard — pertanyaan bersyarat', () => {
    it('menyembunyikan jumlah tanggungan sampai statusnya dijawab', async () => {
        const w = buatBersyarat();

        expect(w.find('#pd-jumlah_tanggungan').exists()).toBe(false);

        await isi(w, 'status_pernikahan', 'Menikah');

        expect(w.find('#pd-jumlah_tanggungan').exists()).toBe(true);
    });

    /**
     * Dua keadaan yang paling mudah terlewat: kata 'tanggungan' terlanjur
     * terbaca sebagai urusan orang yang masih berpasangan, padahal anak tetap
     * menjadi tanggungan setelah bercerai atau ditinggal wafat.
     */
    it('ikut menampilkannya pada yang bercerai dan yang ditinggal wafat', async () => {
        for (const status of ['Cerai Hidup', 'Cerai Mati']) {
            const w = buatBersyarat();

            await isi(w, 'status_pernikahan', status);

            expect(w.find('#pd-jumlah_tanggungan').exists()).toBe(true);
        }
    });

    it('tidak menuntut jumlah tanggungan dari yang belum menikah', async () => {
        const w = buatBersyarat();

        await isi(w, 'status_pernikahan', 'Belum Menikah');
        await w.find('[data-uji="lanjut"]').trigger('click');

        // Kotaknya tidak ada di layar, jadi tuntutannya tidak boleh ada juga —
        // kalau ada, tidak ada isian yang bisa memuaskannya.
        expect(w.text()).not.toContain('Wajib diisi');
    });

    it('menuntut jumlah tanggungan begitu statusnya Menikah', async () => {
        const w = buatBersyarat();

        await isi(w, 'status_pernikahan', 'Menikah');
        await w.find('[data-uji="lanjut"]').trigger('click');

        expect(w.text()).toContain('Wajib diisi');
    });

    it('menerima nol sebagai jawaban, bukan menganggapnya kosong', async () => {
        const w = buatBersyarat();

        await isi(w, 'status_pernikahan', 'Menikah');
        await isi(w, 'jumlah_tanggungan', '0');
        await w.find('[data-uji="lanjut"]').trigger('click');

        expect(w.text()).not.toContain('Wajib diisi');
    });

    /**
     * Angka yang sudah diketik lalu statusnya diganti adalah nilai SISA.
     * Membiarkannya ikut terkirim berarti mengirim data yang salah, bukan data
     * tambahan — dan di layar tidak ada kotak yang menunjukkan angka itu masih
     * ada.
     */
    it('membuang angka yang tertinggal saat statusnya diganti', async () => {
        const w = buatBersyarat();

        await isi(w, 'status_pernikahan', 'Menikah');
        await isi(w, 'jumlah_tanggungan', '3');
        await isi(w, 'status_pernikahan', 'Belum Menikah');

        expect(w.find('#pd-jumlah_tanggungan').exists()).toBe(false);

        await isi(w, 'status_pernikahan', 'Menikah');

        const bidang = w
            .findAllComponents({ name: 'BidangIsian' })
            .find((b) => b.props('kunci') === 'jumlah_tanggungan');

        expect(bidang.props('modelValue')).toBe('');
    });

    it('tidak memulihkan angka tanggungan dari draf yang belum menikah', () => {
        const w = buatBersyarat({
            draft: { data: { status_pernikahan: 'Belum Menikah', jumlah_tanggungan: '4' }, langkah: 0 },
        });

        expect(w.find('#pd-jumlah_tanggungan').exists()).toBe(false);
    });
});

describe('Wizard — pilihan yang sudah tidak ada', () => {
    const skemaPendidikan = {
        versi: 4,
        langkah: [{ kunci: 'kepegawaian', judul: 'Info Pekerjaan', field: ['pendidikan_terakhir'] }],
        field: {
            pendidikan_terakhir: { label: 'Pendidikan Terakhir', tipe: 'pilihan', pilihan: ['D3', 'D4', 'S1'] },
        },
    };
    const nilaiPendidikan = (w) =>
        w.findAllComponents({ name: 'BidangIsian' }).find((b) => b.props('kunci') === 'pendidikan_terakhir').props('modelValue');

    // Draf dari sebelum 'D4/S1' dipisah: kotaknya tampak terisi padahal
    // server akan menolaknya saat Kirim. Orangnya harus diminta memilih ulang.
    it("mengosongkan 'D4/S1' dari draf lama", () => {
        const w = buat({ skema: skemaPendidikan, draft: { data: { pendidikan_terakhir: 'D4/S1' }, langkah: 0 } });

        expect(nilaiPendidikan(w)).toBe('');
    });

    it('membiarkan pilihan yang masih sah', () => {
        const w = buat({ skema: skemaPendidikan, draft: { data: { pendidikan_terakhir: 'S1' }, langkah: 0 } });

        expect(nilaiPendidikan(w)).toBe('S1');
    });
});
