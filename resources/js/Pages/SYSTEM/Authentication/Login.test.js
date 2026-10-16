import { mount } from '@vue/test-utils';
import { reactive } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import Login from './Login.vue';

/**
 * Tombol Login pernah terkunci padahal kotaknya terlihat terisi.
 *
 * Laporan produksi (4 Sep 2026): beberapa orang mengisi username & password,
 * menyelesaikan verifikasi Cloudflare, lalu tombol Login tetap kelabu dengan
 * kursor 'not-allowed'. Turnstile bukan penyebabnya — canSubmit memang tidak
 * pernah memeriksanya. Yang mengunci adalah:
 *
 *     if (!form.username || !form.password) return false;
 *
 * v-model hanya mendengar event 'input'. Pengelola kata sandi dan sebagian
 * jalur autofill menulis `input.value` LANGSUNG tanpa event, jadi kotaknya
 * terlihat terisi sementara form-nya kosong. Diperparah oleh alur gagal login:
 * prosesLogin() membalas dengan redirect (bukan galat validasi), halaman
 * dirender ulang dari nol dengan form kosong, lalu pengelola kata sandi
 * mengisi kotaknya kembali — tanpa event. Tombolnya mati, dan tidak ada satu
 * pun di layar yang menjelaskan kenapa.
 *
 * Perbaikannya dua sisi, dan uji di bawah menjaga keduanya:
 *   1. submit() menyalin isi kotak ke form — supaya kredensial hasil autofill
 *      benar-benar TERKIRIM, bukan sekadar tombolnya hidup.
 *   2. Tombol hanya terkunci selama pengiriman berjalan.
 */

// useForm asli tidak dipakai supaya uji ini tidak menyentuh jaringan, tetapi
// bentuknya ditiru apa adanya — reaktif, dengan reset() yang mengembalikan
// nilai awal — sebab justru reaktivitas itulah yang sedang diuji.
const buatForm = () => {
    const form = reactive({
        username: '',
        password: '',
        'cf-turnstile-response': '',
        processing: false,
        errors: {},
        reset(...kunci) {
            const awal = { username: '', password: '', 'cf-turnstile-response': '' };
            (kunci.length ? kunci : Object.keys(awal)).forEach((k) => {
                form[k] = awal[k];
            });
        },
        // Merekam nilai form PADA SAAT dipanggil: itulah yang benar-benar
        // berangkat ke server. Membacanya setelah panggilan tidak membuktikan
        // apa pun kalau kelak ada kode yang mengubahnya sesudah post.
        post: vi.fn(() => {
            form.post.terkirim = { username: form.username, password: form.password };
        }),
        clearErrors: vi.fn(),
    });

    return form;
};

let formSaatIni;

vi.mock('@inertiajs/vue3', () => ({
    Head: { render: () => null },
    usePage: () => ({ props: { flash: {}, errors: {} } }),
    useForm: () => formSaatIni,
}));

vi.mock('@/utils/logout-transition.js', () => ({
    cleanupLogoutTransition: vi.fn(),
    isLogoutTransitionFresh: () => false,
}));

const pasang = () => mount(Login, { props: { captchaEnabled: false, turnstileSiteKey: '', version: 'uji' } });

const tombol = (w) => w.get('button.btn-login');
const terkunci = (w) => tombol(w).attributes('disabled') !== undefined;

/** Seperti orang mengetik: v-model mendengar event 'input'. */
const ketik = async (w, id, nilai) => {
    await w.get(`#${id}`).setValue(nilai);
};

/**
 * Seperti pengelola kata sandi: `.value` ditulis langsung, tanpa event apa pun.
 * Inilah yang membedakan "terlihat terisi" dari "terisi".
 */
const isiDiamDiam = async (w, id, nilai) => {
    w.get(`#${id}`).element.value = nilai;
    await w.vm.$nextTick();
};

const kirim = async (w) => {
    await w.get('form').trigger('submit');
};

describe('Tombol Login', () => {
    beforeEach(() => {
        formSaatIni = buatForm();
    });

    it('tidak terkunci saat kotaknya diisi tanpa event — kasus pengelola kata sandi', async () => {
        const w = pasang();

        await isiDiamDiam(w, 'u', 'erlangga');
        await isiDiamDiam(w, 'p', 'rahasia123');

        expect(w.get('#u').element.value).toBe('erlangga');
        expect(terkunci(w)).toBe(false);
    });

    /**
     * Inti perbaikannya. Tombol yang hidup tapi mengirim isian kosong hanya
     * memindahkan kebuntuan dari layar ke server.
     */
    it('mengirim isian yang hanya ada di kotak, bukan yang ada di form', async () => {
        const w = pasang();

        await isiDiamDiam(w, 'u', 'erlangga');
        await isiDiamDiam(w, 'p', 'rahasia123');

        // Form masih kosong — kotaknya tak pernah membangkitkan event.
        expect(formSaatIni.username).toBe('');

        await kirim(w);

        expect(formSaatIni.post).toHaveBeenCalledTimes(1);
        expect(formSaatIni.post.terkirim).toEqual({ username: 'erlangga', password: 'rahasia123' });
    });

    /**
     * Bentuk yang paling sering terjadi di produksi: percobaan KEDUA.
     *
     * prosesLogin() membalas gagal login dengan redirect, jadi halamannya
     * dirender ulang dan form-nya lahir kosong; pengelola kata sandi lalu
     * mengisi kotaknya kembali tanpa event. Orang yang password-nya tersimpan
     * memang tidak pernah mengetik, jadi tanpa perbaikan ini ia terjebak
     * selamanya.
     */
    it('tetap bisa dikirim setelah form dikosongkan lalu kotaknya diisi ulang tanpa event', async () => {
        const w = pasang();

        await ketik(w, 'u', 'erlangga');
        await ketik(w, 'p', 'salah');

        formSaatIni.reset();
        await w.vm.$nextTick();

        await isiDiamDiam(w, 'u', 'erlangga');
        await isiDiamDiam(w, 'p', 'rahasia123');

        expect(terkunci(w)).toBe(false);

        await kirim(w);

        expect(formSaatIni.post.terkirim).toEqual({ username: 'erlangga', password: 'rahasia123' });
    });

    /**
     * Yang benar-benar kosong ditolak dengan kalimat yang bisa dibaca, bukan
     * dengan tombol mati tanpa penjelasan. Pintu yang bisa diketuk.
     */
    it('menolak kirim saat benar-benar kosong, dan mengatakan alasannya', async () => {
        const w = pasang();

        expect(terkunci(w)).toBe(false);

        await kirim(w);

        expect(formSaatIni.post).not.toHaveBeenCalled();
        expect(w.text()).toContain('Username dan password wajib diisi');
    });

    it('terkunci hanya selama pengiriman berjalan', async () => {
        const w = pasang();

        await ketik(w, 'u', 'erlangga');
        await ketik(w, 'p', 'rahasia123');
        expect(terkunci(w)).toBe(false);

        formSaatIni.processing = true;
        await w.vm.$nextTick();

        expect(terkunci(w)).toBe(true);
        expect(tombol(w).text()).toContain('Memverifikasi');
    });

    it('ketikan biasa tetap bekerja seperti sebelumnya', async () => {
        const w = pasang();

        await ketik(w, 'u', 'erlangga');
        await ketik(w, 'p', 'rahasia123');

        await kirim(w);

        expect(formSaatIni.post.terkirim).toEqual({ username: 'erlangga', password: 'rahasia123' });
    });
});
