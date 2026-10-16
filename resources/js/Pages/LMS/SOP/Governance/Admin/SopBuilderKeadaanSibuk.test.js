import { beforeEach, describe, expect, it, vi } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { parse } from '@vue/compiler-sfc';

vi.mock('axios', () => ({
    default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

import axios from 'axios';
import SopBuilder from './SopBuilder.vue';

/**
 * Keadaan menunggu pada Builder.
 *
 * KENAPA DIUJI SEBAGAI TRANSISI, BUKAN SEBAGAI PIKSEL. Yang membuat layar
 * terasa macet bukan bentuk spinner-nya melainkan JENDELA WAKTU tempat tak ada
 * apa pun yang menandai kerja: bendera yang dinyalakan tapi dipadamkan terlalu
 * cepat, atau modal yang ditutup sebelum kerjanya selesai, menghasilkan
 * tampilan yang sama persis dengan tidak punya indikator sama sekali. Yang
 * dijaga karena itu adalah NILAI BENDERA PADA SAAT permintaan masih di jalan —
 * dan satu-satunya cara memeriksanya adalah menahan permintaan itu.
 *
 * Dijalankan atas metode langsung, tanpa merakit halaman lima ribu baris:
 * seluruh perilaku di sini murni urusan urutan `await`.
 */

/** Permintaan yang ditahan, supaya keadaan di TENGAH penantian bisa diperiksa. */
function tertahan() {
    let lepas;
    let tolak;
    const janji = new Promise((res, rej) => {
        lepas = res;
        tolak = rej;
    });

    return { janji, lepas, tolak };
}

function konteks(over = {}) {
    return {
        loading: { list: false, save: false, editor: false, struktur: false, urutan: false },
        current: { Id_SOP_Paket: 'pkt1' },
        sections: [],
        items: [],
        mode: 'editor',
        paketVersi: null,
        nextVersiPaket: 1,
        pendingChanges: { total: 0, summary: {} },
        draftActivity: null,
        dukungDokumenDraft: false,
        sectionModal: { show: true, mode: 'create', id: null, Nama_Section: 'Persiapan', Deskripsi: '', saving: false },
        reloadItems: vi.fn(),
        handleKonflik: vi.fn(),
        ...over,
    };
}

const panggil = (nama, ctx, ...args) => SopBuilder.methods[nama].call(ctx, ...args);

beforeEach(() => {
    vi.clearAllMocks();
});

describe('reloadItems — daftar tak pernah diam tanpa keterangan', () => {
    it('menyalakan penanda struktur SELAMA permintaan masih di jalan', async () => {
        const req = tertahan();
        axios.get.mockReturnValue(req.janji);

        const ctx = konteks();
        const jalan = panggil('reloadItems', ctx);

        expect(ctx.loading.struktur).toBe(true);

        req.lepas({ data: { success: true, result: { sections: [], items: [] } } });
        await jalan;

        expect(ctx.loading.struktur).toBe(false);
    });

    it('memadamkan penandanya juga ketika permintaannya gagal', async () => {
        axios.get.mockRejectedValue(new Error('jaringan'));

        const ctx = konteks();
        await panggil('reloadItems', ctx);

        /* Penanda yang tersangkut menyala pada kegagalan jauh lebih buruk
           daripada tak punya penanda: daftarnya redup dan tak bisa diklik
           selamanya, dan satu-satunya jalan keluar adalah memuat ulang halaman. */
        expect(ctx.loading.struktur).toBe(false);
    });
});

describe('submitSection — modal bertahan sampai strukturnya segar', () => {
    it('belum menutup modal saat struktur baru sedang ditarik', async () => {
        axios.post.mockResolvedValue({ data: { success: true } });

        const req = tertahan();
        let showSaatReload = null;
        const ctx = konteks({
            reloadItems: vi.fn(() => {
                showSaatReload = ctx.sectionModal.show;

                return req.janji;
            }),
        });

        const jalan = panggil('submitSection', ctx);
        await Promise.resolve();
        await Promise.resolve();

        req.lepas();
        await jalan;

        /* Bentuk lamanya menutup modal lebih dulu, sehingga satu-satunya tanda
           kerja di layar lenyap tepat ketika kerjanya belum selesai. */
        expect(showSaatReload).toBe(true);
        expect(ctx.sectionModal.show).toBe(false);
        expect(ctx.sectionModal.saving).toBe(false);
    });

    it('menahan tombolnya sibuk sepanjang simpan DAN penyegaran', async () => {
        axios.post.mockResolvedValue({ data: { success: true } });

        const req = tertahan();
        let savingSaatReload = null;
        const ctx = konteks({
            reloadItems: vi.fn(() => {
                savingSaatReload = ctx.sectionModal.saving;

                return req.janji;
            }),
        });

        const jalan = panggil('submitSection', ctx);
        await Promise.resolve();
        await Promise.resolve();
        req.lepas();
        await jalan;

        expect(savingSaatReload).toBe(true);
    });

    it('menolak kiriman kedua selagi yang pertama masih berjalan', async () => {
        const ctx = konteks({ sectionModal: { ...konteks().sectionModal, saving: true } });

        await panggil('submitSection', ctx);

        // Enter di kotak nama memanggil metode yang sama; dua section bernama
        // sama lahir dari dua ketukan yang berjarak lebih pendek dari jaringan.
        expect(axios.post).not.toHaveBeenCalled();
        expect(axios.put).not.toHaveBeenCalled();
    });

    it('nama kosong ditolak tanpa menyalakan keadaan sibuk apa pun', async () => {
        const ctx = konteks({ sectionModal: { ...konteks().sectionModal, Nama_Section: '   ' } });

        await panggil('submitSection', ctx);

        expect(axios.post).not.toHaveBeenCalled();
        expect(ctx.sectionModal.saving).toBe(false);
        expect(ctx.sectionModal.show).toBe(true);
    });
});

describe('urutan — susunan optimistis harus mengaku sedang dikirim', () => {
    it.each([
        ['persistSectionOrder', 'sections', { Id_SOP_Section: 's1' }],
        ['persistOrder', 'items', { Id_SOP_Paket_Item: 'i1', Id_SOP_Section: 's1' }],
    ])('%s menyalakan penanda urutan selama pengiriman', async (metode, ladang, baris) => {
        const req = tertahan();
        axios.post.mockReturnValue(req.janji);

        const ctx = konteks({ [ladang]: [baris] });
        const jalan = panggil(metode, ctx);

        expect(ctx.loading.urutan).toBe(true);

        req.lepas({ data: { success: true } });
        await jalan;

        expect(ctx.loading.urutan).toBe(false);
    });

    it.each(['persistSectionOrder', 'persistOrder'])(
        '%s menunggu pemulihan selesai sebelum memadamkan penandanya',
        async (metode) => {
            axios.post.mockRejectedValue(new Error('gagal'));

            const req = tertahan();
            let urutanSaatPulih = null;
            const ctx = konteks({
                sections: [{ Id_SOP_Section: 's1' }],
                items: [{ Id_SOP_Paket_Item: 'i1', Id_SOP_Section: 's1' }],
                reloadItems: vi.fn(() => {
                    urutanSaatPulih = ctx.loading.urutan;

                    return req.janji;
                }),
            });

            const jalan = panggil(metode, ctx);
            await Promise.resolve();
            await Promise.resolve();
            req.lepas();
            await jalan;

            /* Tanpa `await` pada pemulihan, pil padam sementara struktur yang
               benar masih dalam perjalanan — dan urutan yang salah tampak
               seperti urutan yang sudah tersimpan. */
            expect(ctx.reloadItems).toHaveBeenCalled();
            expect(urutanSaatPulih).toBe(true);
            expect(ctx.loading.urutan).toBe(false);
        },
    );
});

describe('penanda mana yang boleh tampil', () => {
    const sibuk = (loading, sections = []) => SopBuilder.computed.strukturSibuk.call({ loading, sections });
    const kerangka = (loading, sections = []) => SopBuilder.computed.strukturKosongMemuat.call({ loading, sections });

    const ADA = [{ Id_SOP_Section: 's1' }];

    it('lapisan struktur menyala saat menyegarkan daftar yang sudah berisi', () => {
        expect(sibuk({ struktur: true, save: false }, ADA)).toBe(true);
    });

    it('lapisan struktur DIAM saat editor item sudah memasang overlaynya', () => {
        // Keduanya menandai penantian yang sama; dua lapisan membuat layar
        // tampak sibuk dua kali lebih lama daripada kenyataannya.
        expect(sibuk({ struktur: true, save: true }, ADA)).toBe(false);
    });

    it('lapisan struktur DIAM pada muat pertama — kerangkanya sudah jadi penanda', () => {
        expect(sibuk({ struktur: true, save: false }, [])).toBe(false);
        expect(kerangka({ struktur: true }, [])).toBe(true);
    });

    it('kerangka hanya untuk struktur yang belum pernah terisi', () => {
        expect(kerangka({ struktur: true }, [])).toBe(true);
        expect(kerangka({ struktur: true }, [{ Id_SOP_Section: 's1' }])).toBe(false);
        expect(kerangka({ struktur: false }, [])).toBe(false);
    });
});

/*
 * Keadaan kosong yang berkelebat tak bisa dibuktikan lewat transisi bendera —
 * ia soal URUTAN CABANG di template. Diperiksa sebagai struktur, seperti
 * SopBuilderModalAkar memeriksa kedalaman modal.
 */
describe('template — "Belum ada section" tak boleh mendahului datanya', () => {
    it('kerangka datang lebih dulu, keadaan kosong jadi cabang berikutnya', () => {
        const p = 'resources/js/Pages/LMS/SOP/Governance/Admin/SopBuilder.vue';
        const { descriptor } = parse(readFileSync(resolve(process.cwd(), p), 'utf8'), { filename: p });
        /* Komentar dibuang dulu. Yang dicari adalah URUTAN CABANG, dan
           komentar yang kebetulan mengutip kalimatnya menggeser posisi yang
           diukur tanpa mengubah apa pun yang dirender. */
        const tpl = descriptor.template.content.replace(/<!--[\s\S]*?-->/g, '');

        const posKerangka = tpl.indexOf('struktur-skel');
        const posKosong = tpl.indexOf('title="Belum ada section"');

        expect(posKerangka).toBeGreaterThan(-1);
        expect(posKosong).toBeGreaterThan(posKerangka);

        /* `v-if` pada keadaan kosong berarti ia dirender BERBARENGAN dengan
           kerangka pada muat pertama — dua jawaban sekaligus, salah satunya
           berbunyi "buat section dulu" atas paket yang isinya belum tiba. */
        const antara = tpl.slice(posKerangka, posKosong);
        expect(antara).toContain('v-else-if="!sections.length"');
    });
});
