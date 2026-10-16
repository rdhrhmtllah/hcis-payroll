import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import PaKesiapanHcLearn from './PaKesiapanHcLearn.vue';

/*
 | SATU PANEL, DUA LANGKAH — dan perbedaannya justru yang dijaga di sini.
 |
 | Panel ini berdiri di Langkah 4 (sesudah peserta dibentuk) dan di Langkah 5 (sebelum siklus
 | dikunci). Di Langkah 4 penilainya masih PROYEKSI, dan itu keadaan yang wajar di sana: kalau
 | peringatan "masih proyeksi" ikut menyala, ia menyala pada setiap kunjungan dan berhenti
 | dibaca. Di Langkah 5 proyeksi berarti ada orang yang akan dikirimi pintu tetapi belum pernah
 | disinggung pemeriksaan ini — di sana ia memang harus berbunyi.
 |
 | Tanpa uji ini, `konteks` bisa jadi prop yang diterima tetapi tidak pernah dipakai, dan
 | keduanya tampak sama sampai ada yang menutup Langkah 5 terlalu cepat.
 */

const paApiMock = vi.hoisted(() => ({
    kesiapanKaryawanHcLearn: vi.fn(),
    sinkronkanKaryawanHcLearn: vi.fn(),
}));

vi.mock('../../api/paApi.js', () => ({ default: paApiMock }));

const uiStubs = {
    HcisAlert: { props: ['title'], template: '<div class="hcis-alert">{{ title }}<slot /></div>' },
    HcisBadge: { props: ['label'], template: '<span class="hcis-badge">{{ label }}</span>' },
    HcisButton: {
        props: ['label', 'disabled', 'loading'],
        /* `emits` DIDEKLARASIKAN: tanpa itu `@click` induk juga terpasang sebagai listener
           native, satu klik memanggil handler dua kali, dan yang terukur adalah keliru stub. */
        emits: ['click'],
        template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}<slot /></button>',
    },
};

function kesiapan(ganti = {}) {
    return {
        status: 'SIAP',
        ringkasan: {
            terlibat: 3, siap: 3, belum_terdaftar: 0, tidak_siap: 0,
            dinilai: 1, penilai_terpasang: 1, penilai_proyeksi: 1,
        },
        baris: [],
        catatan: '3 orang yang terlibat sudah siap dijadwalkan.',
        ...ganti,
    };
}

/** Baris penghalang nyata: disalin dari keadaan siklus PA-2026-01 pada 10 September 2026. */
function barisResign(ganti = {}) {
    return {
        kode_perusahaan: '001',
        kode_karyawan: 'A1',
        nama: 'Frans Bachtiar',
        peran: ['PENILAI'],
        ada: true,
        siap: false,
        alasan: 'SUDAH_RESIGN',
        keterangan: 'Karyawan terdaftar tetapi tidak aktif / sudah resign.',
        dapat_disinkron: false,
        tanggungan: [{
            assignment_id: 4359,
            participant_id: 1221428,
            kode_karyawan: 'V59',
            nama: 'Samuel',
            id_level: 4,
            nama_level: 'Officer',
            divisi: 'Operasional',
            kategori: 'BAWAHAN',
            nama_kategori: 'Bawahan',
            penilai_sekarang: 2,
            min_rater: 2,
            bobot_kategori: 25,
            cukup_dibatalkan: false,
            ...ganti,
        }],
    };
}

function pasang(konteks) {
    return mount(PaKesiapanHcLearn, {
        props: { cycleId: 91, dapatDisunting: true, konteks },
        global: { stubs: uiStubs },
    });
}

describe('PaKesiapanHcLearn', () => {
    beforeEach(() => {
        Object.values(paApiMock).forEach((fn) => fn.mockReset());
        paApiMock.kesiapanKaryawanHcLearn.mockResolvedValue({ result: kesiapan() });
    });

    it('memuat kesiapan siklusnya sendiri saat dipasang', async () => {
        const wrapper = pasang('PESERTA');
        await flushPromises();

        expect(paApiMock.kesiapanKaryawanHcLearn).toHaveBeenCalledWith(91);
        expect(wrapper.text()).toContain('sudah siap dijadwalkan');
    });

    /**
     * Perubahan yang membuat uji ini merah: tampilkan peringatan proyeksi tanpa melihat
     * `konteks`.
     */
    it('menyalakan peringatan proyeksi HANYA di langkah penugasan', async () => {
        const langkah5 = pasang('PENUGASAN');
        await flushPromises();
        expect(langkah5.text()).toContain('masih proyeksi, belum punya penugasan');

        const langkah4 = pasang('PESERTA');
        await flushPromises();
        expect(langkah4.text()).not.toContain('belum punya penugasan');
    });

    /**
     * ASAL POPULASI DISEBUT DI KEDUA LANGKAH. Angka "terlibat" tanpa keterangan tidak bisa
     * dibedakan antara dugaan dan kenyataan, dan admin menimbang keduanya sama.
     */
    it('menyebut berapa yang dinilai, terpasang, dan masih proyeksi', async () => {
        const wrapper = pasang('PESERTA');
        await flushPromises();

        const teks = wrapper.text();
        expect(teks).toContain('1 dinilai');
        expect(teks).toContain('1 penilai dari penugasan yang sudah terbentuk');
        expect(teks).toContain('1 penilai masih proyeksi');
    });

    /**
     * Yang TERDAFTAR TETAPI NONAKTIF tidak bisa dibereskan tombol sinkron — mendaftarkan ulang
     * tidak menghidupkan akunnya. Tombol yang tetap muncul di situ ditekan berkali-kali tanpa
     * hasil, dan admin menyimpulkan sistemnya rusak alih-alih ratersnya harus diganti.
     *
     * Perubahan yang membuat uji ini merah: jadikan syarat tombolnya `!b.siap`.
     */
    it('tidak menawarkan sinkronisasi untuk orang yang sudah resign', async () => {
        paApiMock.kesiapanKaryawanHcLearn.mockResolvedValue({
            result: kesiapan({
                status: 'PERLU_TINDAKAN',
                baris: [{
                    kode_perusahaan: '001',
                    kode_karyawan: 'A1',
                    nama: 'Frans Bachtiar',
                    peran: ['PENILAI'],
                    ada: true,
                    siap: false,
                    alasan: 'SUDAH_RESIGN',
                    keterangan: 'Karyawan terdaftar tetapi tidak aktif / sudah resign.',
                    dapat_disinkron: false,
                }],
            }),
        });

        const wrapper = pasang('PENUGASAN');
        await flushPromises();

        expect(wrapper.text()).toContain('Frans Bachtiar');
        expect(wrapper.text()).toContain('SUDAH_RESIGN');
        expect(wrapper.findAll('button').find((b) => b.text().includes('Sinkronkan'))).toBeFalsy();
    });

    /**
     * ORANG YANG MENGHALANGI MENUNJUK PEKERJAANNYA SENDIRI.
     *
     * Yang bisa dibereskan admin bukan orang yang sudah resign, melainkan penugasan yang
     * menunjuk padanya. Panel yang berhenti pada "A1 sudah resign" memindahkan pekerjaan
     * menemukan penugasan itu kembali ke admin — padahal servernya sudah tahu.
     *
     * Perubahan yang membuat uji ini merah: berhenti menampilkan `tanggungan`, atau tampilkan
     * angkanya saja tanpa nama pesertanya.
     */
    it('menyebut peserta yang kehilangan penilai, dan memperingatkan bila harus diganti', async () => {
        paApiMock.kesiapanKaryawanHcLearn.mockResolvedValue({
            result: kesiapan({
                status: 'PERLU_TINDAKAN',
                baris: [barisResign()],
            }),
        });

        const wrapper = pasang('PENUGASAN');
        await flushPromises();

        const teks = wrapper.text();
        expect(teks).toContain('Samuel');
        expect(teks).toContain('V59');
        expect(teks).toContain('Bawahan');

        /* Kategori BAWAHAN minimal 2 dan sekarang tepat 2: membatalkan saja menyisakan 1. */
        expect(teks).toContain('wajib DIGANTI');
        expect(teks).toContain('minimal 2');
    });

    /**
     * Kategori yang masih berlebih boleh dibatalkan begitu saja — dan itu DIKATAKAN, supaya
     * admin tidak mencari pengganti yang tidak dibutuhkan.
     */
    it('mengatakan bila pembatalan saja sudah cukup', async () => {
        paApiMock.kesiapanKaryawanHcLearn.mockResolvedValue({
            result: kesiapan({
                status: 'PERLU_TINDAKAN',
                baris: [barisResign({ penilai_sekarang: 4, min_rater: 2, cukup_dibatalkan: true })],
            }),
        });

        const wrapper = pasang('PENUGASAN');
        await flushPromises();

        expect(wrapper.text()).toContain('boleh dibatalkan');
        expect(wrapper.text()).not.toContain('wajib DIGANTI');
    });

    /**
     * TOMBOLNYA HANYA DI LANGKAH 5, sebab hanya di sana ada dialog Kelola Rater yang bisa
     * dibuka. Menampilkannya di Langkah 4 berarti tombol yang tidak menuju ke mana-mana.
     */
    it('menawarkan Kelola Rater hanya di langkah penugasan, dan meneruskan pesertanya', async () => {
        paApiMock.kesiapanKaryawanHcLearn.mockResolvedValue({
            result: kesiapan({ status: 'PERLU_TINDAKAN', baris: [barisResign()] }),
        });

        const langkah4 = pasang('PESERTA');
        await flushPromises();
        expect(langkah4.findAll('button').find((b) => b.text().includes('Kelola Rater'))).toBeFalsy();

        const langkah5 = pasang('PENUGASAN');
        await flushPromises();

        const tombol = langkah5.findAll('button').find((b) => b.text().includes('Kelola Rater'));
        expect(tombol).toBeTruthy();

        await tombol.trigger('click');

        const dipancarkan = langkah5.emitted('kelola-penilai');
        expect(dipancarkan).toHaveLength(1);

        /* Kuncinya harus yang DIMINTA dialog. Menebaknya — `id_peserta`, misalnya — membuat
           dialog terbuka pada peserta tanpa id lalu gagal dengan pesan yang tidak menyebut
           sebabnya. */
        expect(dipancarkan[0][0].participant_id).toBe(1221428);
        expect(dipancarkan[0][0].kode_karyawan).toBe('V59');
        expect(dipancarkan[0][0].nama).toBe('Samuel');
    });

    /**
     * Pesan hasil sinkronisasi DINAIKKAN ke langkah induk, tidak diucapkan sendiri di tengah
     * halaman: satu langkah punya satu tempat bicara.
     *
     * Perubahan yang membuat uji ini merah: tampilkan pesannya di dalam panel dan hentikan
     * emit-nya.
     */
    it('menyerahkan pesan hasil sinkronisasi kepada langkah induk', async () => {
        paApiMock.kesiapanKaryawanHcLearn.mockResolvedValue({
            result: kesiapan({
                status: 'PERLU_SINKRON',
                baris: [{
                    kode_perusahaan: '001',
                    kode_karyawan: 'A2',
                    nama: 'Sri Wahyuni',
                    peran: ['PENILAI'],
                    ada: false,
                    siap: false,
                    alasan: 'BELUM_TERDAFTAR',
                    keterangan: 'Karyawan belum ada di HCLearn.',
                    dapat_disinkron: true,
                }],
            }),
        });

        paApiMock.sinkronkanKaryawanHcLearn.mockResolvedValue({
            result: {
                sinkron: { ringkasan: { diminta: 1, tersimpan: 1, sudah_ada: 0, gagal: 0 }, gagal: [] },
                kesiapan: kesiapan({ catatan: 'Seluruhnya sudah siap.' }),
            },
        });

        const wrapper = pasang('PESERTA');
        await flushPromises();

        await wrapper.findAll('button').find((b) => b.text().includes('Sinkronkan')).trigger('click');
        await flushPromises();

        expect(paApiMock.sinkronkanKaryawanHcLearn).toHaveBeenCalledWith(91);

        const dipancarkan = wrapper.emitted('pesan');
        expect(dipancarkan).toHaveLength(1);
        expect(dipancarkan[0][0].teks).toContain('1 karyawan terdaftar di HC Learn');
        expect(dipancarkan[0][0].error).toBe(false);

        /* Keadaan barunya dibaca dari BALASAN sinkronisasi, bukan dengan memanggil ulang. */
        expect(paApiMock.kesiapanKaryawanHcLearn).toHaveBeenCalledTimes(1);
        expect(wrapper.text()).toContain('Seluruhnya sudah siap.');
    });

    /**
     * HC Learn mati TIDAK menjatuhkan langkahnya. Isi langkah seluruhnya data kita sendiri;
     * membiarkan sambungan luar menghapusnya berarti satu layanan yang tumbang melumpuhkan
     * pekerjaan yang sebenarnya tidak membutuhkannya.
     */
    it('melaporkan kegagalan sambungan tanpa menghapus dirinya diam-diam', async () => {
        paApiMock.kesiapanKaryawanHcLearn.mockRejectedValue(new Error('Sambungan HC Learn terputus.'));

        const wrapper = pasang('PENUGASAN');
        await flushPromises();

        expect(wrapper.text()).toContain('tidak dapat dibaca');
        expect(wrapper.text()).toContain('Sambungan HC Learn terputus.');
    });

    /**
     * YANG MENAHAN DIPISAH DARI YANG SEKADAR TERCATAT (UAT 13 Okt).
     *
     * Sebelumnya semua yang ditolak berada di bawah satu judul "menahan penguncian siklus",
     * termasuk baris yang lencananya sendiri berkata "tidak menghalangi".
     *
     * Perubahan yang membuat uji ini merah: kembalikan satu daftar untuk keduanya.
     */
    it('di langkah penugasan: yang menahan terbuka, yang tidak menghalangi dilipat terpisah', async () => {
        const tercatat = { ...barisResign(), kode_karyawan: 'B2', nama: 'Budi Lepas', tanggungan: [], menghalangi: false };
        paApiMock.kesiapanKaryawanHcLearn.mockResolvedValue({
            result: kesiapan({ status: 'PERLU_TINDAKAN', baris: [{ ...barisResign(), menghalangi: true }, tercatat] }),
        });

        const wrapper = mount(PaKesiapanHcLearn, {
            props: { cycleId: 91, dapatDisunting: true, konteks: 'PENUGASAN' },
            global: {
                stubs: {
                    ...uiStubs,
                    HcisCollapse: { template: '<div class="hcis-collapse"><slot /></div>' },
                    HcisCollapseItem: { props: ['title'], template: '<div class="hcis-collapse-item">{{ title }}<slot /></div>' },
                },
            },
        });
        await flushPromises();

        const menahan = wrapper.find('[data-uji="hcl-menahan"]');
        expect(menahan.text()).toContain('Menahan penguncian (1)');
        expect(menahan.text()).toContain('Frans Bachtiar');
        expect(menahan.text()).not.toContain('Budi Lepas');

        const lipat = wrapper.find('[data-uji="hcl-tercatat"]');
        expect(lipat.text()).toContain('Tidak menghalangi — bereskan di data induk (1)');
        expect(lipat.text()).toContain('Budi Lepas');
    });

    it('hanya yang tidak menghalangi: tidak ada judul "menahan penguncian"', async () => {
        const tercatat = { ...barisResign(), tanggungan: [], menghalangi: false };
        paApiMock.kesiapanKaryawanHcLearn.mockResolvedValue({ result: kesiapan({ baris: [tercatat] }) });

        const wrapper = mount(PaKesiapanHcLearn, {
            props: { cycleId: 91, dapatDisunting: true, konteks: 'PENUGASAN' },
            global: {
                stubs: {
                    ...uiStubs,
                    HcisCollapse: { template: '<div class="hcis-collapse"><slot /></div>' },
                    HcisCollapseItem: { props: ['title'], template: '<div>{{ title }}<slot /></div>' },
                },
            },
        });
        await flushPromises();

        expect(wrapper.find('[data-uji="hcl-menahan"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Menahan penguncian');
        expect(wrapper.find('[data-uji="hcl-tercatat"]').exists()).toBe(true);
    });

    /* Di Langkah 4 penugasan belum ada: penilai proyeksi yang ditolak ditandai server "tidak
       menghalangi", padahal mereka akan menahan begitu penugasannya terbentuk. Jangan dilipat. */
    it('di langkah peserta: semua yang ditolak satu daftar terbuka, tidak dilipat', async () => {
        const proyeksi = { ...barisResign(), tanggungan: [], menghalangi: false };
        paApiMock.kesiapanKaryawanHcLearn.mockResolvedValue({ result: kesiapan({ baris: [proyeksi] }) });

        const wrapper = pasang('PESERTA');
        await flushPromises();

        const daftar = wrapper.find('[data-uji="hcl-menahan"]');
        expect(daftar.text()).toContain('Ditolak HC Learn (1)');
        expect(daftar.text()).toContain('Frans Bachtiar');
        expect(wrapper.find('[data-uji="hcl-tercatat"]').exists()).toBe(false);
    });
});
