import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import HcisFileViewer from './HcisFileViewer.vue';

describe('HcisFileViewer owned primitive component', () => {
    it('renders image viewer correctly when modelValue is true and file is an image', () => {
        const wrapper = mount(HcisFileViewer, {
            props: {
                modelValue: true,
                file: { name: 'foto-profil.png', url: 'https://example.com/foto.png', size: 500000 },
                teleportTo: false,
            },
        });

        expect(wrapper.text()).toContain('foto-profil.png');
        expect(wrapper.find('.hcis-viewer__img').exists()).toBe(true);
        expect(wrapper.find('.hcis-viewer__img').attributes('src')).toBe('https://example.com/foto.png');
    });

    it('renders PDF viewer iframe correctly when file is a PDF', () => {
        const wrapper = mount(HcisFileViewer, {
            props: {
                modelValue: true,
                file: { name: 'surat-dokter.pdf', url: 'https://example.com/doc.pdf', size: 1048576 },
                teleportTo: false,
            },
        });

        expect(wrapper.text()).toContain('surat-dokter.pdf');
        expect(wrapper.find('.hcis-viewer__pdf-iframe').exists()).toBe(true);
    });

    it('renders Video player correctly when file is an mp4 video', () => {
        const wrapper = mount(HcisFileViewer, {
            props: {
                modelValue: true,
                file: { name: 'video-sosialisasi.mp4', url: 'https://example.com/video.mp4' },
                teleportTo: false,
            },
        });

        expect(wrapper.find('.hcis-viewer__video').exists()).toBe(true);
    });

    it('emits close and update:modelValue on close button click', async () => {
        const wrapper = mount(HcisFileViewer, {
            props: {
                modelValue: true,
                file: 'https://example.com/test.pdf',
                teleportTo: false,
            },
        });

        const closeBtn = wrapper.find('.hcis-viewer__btn--close');
        await closeBtn.trigger('click');

        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([false]);

        // 'close' TIDAK lahir dari kliknya, melainkan dari watcher modelValue
        // saat nilainya benar-benar berubah jadi false. Di pemakaian nyata
        // induknya mengikat v-model sehingga putaran itu terjadi sendiri; di
        // uji, putarannya harus ditirukan -- kalau tidak, yang diuji bukan
        // perilaku komponennya melainkan ketiadaan induk.
        await wrapper.setProps({ modelValue: false });

        expect(wrapper.emitted('close')).toBeTruthy();
    });
});

/**
 * CETAK BERKAS LINTAS-ORIGIN.
 *
 * `iframe.contentWindow.print()` hanya boleh dipanggil pada bingkai se-origin.
 * Berkas yang dilayani penyimpanan objek lewat URL bertanda tangan SELALU
 * lintas-origin, jadi panggilan itu selalu dilempar — dan cadangan lamanya
 * membuka tab berisi URL penyimpanan mentahnya. Pengguna menekan "Cetak" lalu
 * mendapat tab berisi tautan, tanpa satu pun dialog cetak.
 *
 * Yang benar: ambil berkasnya sebagai blob, cetak dari bingkai `blob:` yang
 * se-origin.
 */
describe('HcisFileViewer — cetak berkas lintas-origin', () => {
    const PDF = { name: 'surat-dokter.pdf', url: 'https://storage.example/doc.pdf?sig=x' };

    const pasang = (props = {}) => mount(HcisFileViewer, {
        props: { modelValue: true, file: PDF, teleportTo: false, ...props },
    });

    const tombolCetak = (w) => w.findAll('button').find((b) => /cetak/i.test(b.attributes('title') || ''));

    afterEach(() => {
        vi.restoreAllMocks();
        document.querySelectorAll('iframe[aria-hidden="true"]').forEach((f) => f.remove());
    });

    it('mengambil berkasnya sebagai blob, bukan membuka tab', async () => {
        const buka = vi.spyOn(window, 'open').mockImplementation(() => null);
        const ambil = vi.spyOn(globalThis, 'fetch').mockResolvedValue({
            ok: true,
            blob: async () => new Blob(['%PDF-1.4'], { type: 'application/pdf' }),
        });
        // jsdom tidak punya URL.createObjectURL sama sekali, jadi spyOn
        // gagal sebelum sempat memasang tiruannya. Fungsinya dipasang
        // dulu, baru ditiru — dan dilepas lagi supaya tidak bocor ke uji
        // lain yang justru ingin tahu ia tidak ada.
        if (typeof URL.createObjectURL !== 'function') {
            URL.createObjectURL = () => '';
            URL.revokeObjectURL = () => {};
        }
        vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:uji');

        const w = pasang();
        await tombolCetak(w).trigger('click');
        await new Promise((r) => setTimeout(r, 0));

        expect(ambil).toHaveBeenCalledWith(PDF.url, { mode: 'cors' });
        expect(buka).not.toHaveBeenCalled();
    });

    /**
     * Berkas yang tak boleh dibuka di tab tak boleh pula BOCOR lewat cadangan
     * cetak — di situ membuka tautannya justru hal yang sengaja dilarang.
     */
    it('tidak membuka tab penyimpanan saat tautan luar dilarang', async () => {
        const buka = vi.spyOn(window, 'open').mockImplementation(() => null);
        vi.spyOn(globalThis, 'fetch').mockRejectedValue(new Error('CORS'));

        const w = pasang({ allowExternalLink: false });
        await tombolCetak(w).trigger('click');
        await new Promise((r) => setTimeout(r, 0));

        expect(buka).not.toHaveBeenCalled();
    });

    it('tombol cetak tak ditawarkan saat pencetakan dilarang', () => {
        expect(tombolCetak(pasang({ allowPrint: false }))).toBeUndefined();
    });
});

/**
 * PDF DI HP.
 *
 * Chrome Android menampilkan <iframe> PDF sebagai bingkai kosong dan Safari iOS
 * hanya halaman pertama. Di peramban seperti itu viewer memakai penggambar
 * PDF.js (HcisPdfCanvas); desktop tetap iframe bawaan. Halaman mulai (panduan)
 * diteruskan ke keduanya.
 */
describe('HcisFileViewer — pemilihan penampil PDF', () => {
    const stubKanvas = { HcisPdfCanvas: { name: 'HcisPdfCanvas', props: ['url', 'halamanMulai'], template: '<div class="stub-kanvas" />' } };

    function pasang(file) {
        return mount(HcisFileViewer, {
            props: { modelValue: true, file, teleportTo: false },
            global: { stubs: stubKanvas },
        });
    }

    afterEach(() => {
        vi.restoreAllMocks();
        delete navigator.pdfViewerEnabled;
    });

    it('desktop: iframe bawaan dengan fragmen #page untuk halaman mulai', () => {
        const wrapper = pasang({ name: 'manual.pdf', url: '/panduan/7/berkas', ext: 'pdf', halamanMulai: 5 });

        expect(wrapper.find('.stub-kanvas').exists()).toBe(false);
        expect(wrapper.find('.hcis-viewer__pdf-iframe').attributes('src')).toBe('/panduan/7/berkas#page=5');
    });

    it('desktop tanpa halaman mulai: URL apa adanya', () => {
        const wrapper = pasang({ name: 'manual.pdf', url: '/panduan/7/berkas', ext: 'pdf' });

        expect(wrapper.find('.hcis-viewer__pdf-iframe').attributes('src')).toBe('/panduan/7/berkas');
    });

    it('peramban tanpa penampil PDF bawaan: pakai PDF.js dan teruskan halaman mulai', () => {
        Object.defineProperty(navigator, 'pdfViewerEnabled', { value: false, configurable: true });

        const wrapper = pasang({ name: 'manual.pdf', url: '/panduan/7/berkas', ext: 'pdf', halamanMulai: 3 });

        expect(wrapper.find('.hcis-viewer__pdf-iframe').exists()).toBe(false);
        const kanvas = wrapper.findComponent({ name: 'HcisPdfCanvas' });
        expect(kanvas.exists()).toBe(true);
        expect(kanvas.props('url')).toBe('/panduan/7/berkas');
        expect(kanvas.props('halamanMulai')).toBe(3);
    });

    it('HP Android: pakai PDF.js walau pdfViewerEnabled tidak dilaporkan', () => {
        vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue('Mozilla/5.0 (Linux; Android 14; Pixel 7) Mobile Safari/537.36');

        const wrapper = pasang({ name: 'kartu.pdf', url: 'https://storage.example.com/kartu.pdf' });

        expect(wrapper.findComponent({ name: 'HcisPdfCanvas' }).exists()).toBe(true);
        expect(wrapper.find('.hcis-viewer__pdf-iframe').exists()).toBe(false);
    });
});
