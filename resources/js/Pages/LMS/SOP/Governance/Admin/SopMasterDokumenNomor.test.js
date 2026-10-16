import { describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import { shallowMount } from '@vue/test-utils';
import SopMasterDokumen from './SopMasterDokumen.vue';

vi.mock('axios', () => ({
    default: {
        get: vi.fn().mockResolvedValue({ data: { data: [], tipe: ['DOKUMEN', 'FORM', 'VIDEO', 'KUIS'] } }),
        post: vi.fn().mockResolvedValue({ data: {} }),
        put: vi.fn().mockResolvedValue({ data: {} }),
    },
}));

/**
 * Pintu KETIGA tempat dokumen SOP lahir: form buat/ubah di halaman Master SOP.
 *
 * Nomor SOP resmi hanya berlaku untuk DOKUMEN — nol dari 17 item non-dokumen
 * pernah punya. Setelah server menegakkannya, form ini menolak menyimpan VIDEO
 * karena masih mengirim nomor yang tak pernah ia perlihatkan sebagai relevan.
 *
 * Yang paling menjebak: field nomor berada DI ATAS pemilih tipe. Orang mengisi
 * nomor lebih dulu, lalu memilih VIDEO — dan nilainya tertinggal di form,
 * terkirim, lalu ditolak server karena field yang sudah tak terlihat lagi.
 */
const pasang = () => shallowMount(SopMasterDokumen, { props: { awal: null } });

describe('nomor dokumen resmi hanya untuk DOKUMEN', () => {
    it('berlaku untuk DOKUMEN, tidak untuk tiga tipe lain', () => {
        const w = pasang();

        w.vm.form.Tipe = 'DOKUMEN';
        expect(w.vm.pakaiNomorResmi).toBe(true);

        for (const t of ['FORM', 'VIDEO', 'KUIS']) {
            w.vm.form.Tipe = t;
            expect(w.vm.pakaiNomorResmi, t).toBe(false);
        }
    });

    it('nomor yang sudah diketik DIBUANG saat tipe berpindah ke non-dokumen', async () => {
        const w = pasang();

        w.vm.form.Tipe = 'DOKUMEN';
        w.vm.form.Nomor_SOP_Resmi = 'SOP-K3-001';
        await w.vm.$nextTick();

        w.vm.form.Tipe = 'VIDEO';
        await w.vm.$nextTick();

        expect(w.vm.form.Nomor_SOP_Resmi).toBe('');
    });
});

/**
 * Pratinjau nomor resmi tak boleh tertimpa respons yang lebih tua.
 *
 * Memilih entitas pemilik ikut mengubah ruang lingkup, sehingga DUA permintaan
 * saran-nomor terbang hampir bersamaan. Tanpa penjagaan urutan, respons yang
 * berangkat lebih dulu bisa tiba belakangan dan menimpa hasil yang lebih baru —
 * dan sejak kotak ini menampilkan Nomor SOP Resmi, tertimpa berarti
 * memperlihatkan nomor yang salah kepada admin.
 */
describe('urutan respons saran-nomor', () => {
    it('respons lama yang tiba belakangan diabaikan', async () => {
        const w = pasang();
        w.vm.form.open = true;

        let bukaLambat;
        const lambat = new Promise((res) => { bukaLambat = res; });

        axios.get
            // Permintaan PERTAMA: sengaja ditahan supaya tiba paling akhir.
            .mockImplementationOnce(() => lambat.then(() => ({
                data: { result: { No_Dokumen: 0, Nomor_Pratinjau: 'LAMA/??/??/??/??/00/00', Segmen_Kosong: ['Jenis dokumen'] } },
            })))
            // Permintaan KEDUA: menjawab lebih cepat, dan inilah yang benar.
            .mockResolvedValueOnce({
                data: { result: { No_Dokumen: 0, Nomor_Pratinjau: 'BARU/ENB/IT/EHG/09/00/00', Segmen_Kosong: [] } },
            });

        const pertama = w.vm.mintaSaranNomor();
        const kedua = w.vm.mintaSaranNomor();

        await kedua;
        expect(w.vm.pratinjauNomorLengkap).toBe('BARU/ENB/IT/EHG/09/00/00');

        bukaLambat();
        await pertama;

        expect(w.vm.pratinjauNomorLengkap).toBe('BARU/ENB/IT/EHG/09/00/00');
        expect(w.vm.segmenNomorKosong).toEqual([]);
    });
});
