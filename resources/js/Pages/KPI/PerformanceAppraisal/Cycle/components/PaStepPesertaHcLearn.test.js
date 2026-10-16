import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PaStepPeserta from './PaStepPeserta.vue';
import paApi from '../../api/paApi.js';

/**
 * Kesiapan karyawan HC Learn di Langkah 4.
 *
 * Yang dijaga: layar MENYEBUT siapa yang menghalangi, dan tombol sinkronnya memanggil
 * endpoint siklus yang bersangkutan. Panel yang hanya berbunyi "belum siap" tanpa nama
 * memindahkan pekerjaan menemukan orangnya ke admin, dan itu justru pekerjaan yang paling
 * mahal — nama itu hanya ada di HC Learn.
 */
const uiStubs = {
    HcisCard: { template: '<div class="hcis-card"><slot name="actions" /><slot /></div>', props: ['title', 'kicker', 'description'] },
    HcisButton: {
        /* `emits` DIDEKLARASIKAN, dan itu bukan detail gaya. Tanpa deklarasi, `@click` dari
           induk jatuh ke `attrs` dan ikut terpasang sebagai listener native pada elemen
           root — sehingga satu klik memanggil handler DUA KALI, dan penghitungan panggilan
           API di uji ini akan mengukur kekeliruan stub, bukan perilaku komponen. */
        emits: ['click'],
        template: '<button :disabled="disabled" @click="$emit(\'click\', $event)"><slot />{{ label }}</button>',
        props: ['label', 'disabled', 'loading', 'variant', 'href'],
    },
    HcisBadge: { template: '<span class="hcis-badge">{{ label }}</span>', props: ['label', 'variant'] },
    HcisAlert: { template: '<div class="hcis-alert">{{ title }}<slot /></div>', props: ['title', 'variant'] },
    HcisResultState: { template: '<div class="hcis-result-state">{{ title }}</div>', props: ['title'] },
    HcisEmptyState: { template: '<div class="hcis-empty-state">{{ title }}</div>', props: ['title'] },
    HcisSkeleton: { template: '<div class="hcis-skeleton" />' },
    HcisDialog: { template: '<div v-if="modelValue" class="hcis-dialog"><slot /><slot name="footer" /></div>', props: ['modelValue', 'title'] },
    HcisField: { template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />', props: ['modelValue'] },
    HcisSelect: { template: '<select><slot /></select>', props: ['modelValue'] },
    HcisSelectOption: { template: '<option :value="value">{{ label }}</option>', props: ['value', 'label'] },
    HcisTextarea: { template: '<textarea :value="modelValue" />', props: ['modelValue'] },
    HcisStatGroup: { template: '<div class="hcis-stat-group"><slot /></div>', props: ['cols'] },
    HcisStatCard: { template: '<div class="hcis-stat-card">{{ label }}: {{ value }}</div>', props: ['label', 'value'] },
    HcisIcon: { template: '<i :class="name" />', props: ['name'] },
};

function pasang() {
    return mount(PaStepPeserta, {
        props: { cycleId: 2585, dapatDisunting: true, siapDijalankan: true, penghalang: [] },
        global: { stubs: uiStubs },
    });
}

describe('PaStepPeserta — kesiapan karyawan HC Learn', () => {
    beforeEach(() => {
        vi.restoreAllMocks();

        vi.spyOn(paApi, 'pratinjauPenyiapan').mockResolvedValue({
            result: { cacah: { diikutkan: 2, dikecualikan: 0, tertunda: 0 }, level_tanpa_aturan: [] },
        });
        vi.spyOn(paApi, 'daftarPraKecuali').mockResolvedValue({ result: { baris: [] } });
        vi.spyOn(paApi, 'levelSiklus').mockResolvedValue({
            level: [{ id_level: 1, nama_level: 'Staff', jumlah_karyawan: 2 }],
        });
        vi.spyOn(paApi, 'opsiFilterKaryawanPraKecuali').mockResolvedValue({ divisi: [], departemen: [], level: [] });
        vi.spyOn(paApi, 'cariKaryawanPraKecuali').mockResolvedValue({ baris: [] });
    });

    /**
     * Perubahan yang membuat uji ini merah: tampilkan hanya ringkasan angka tanpa baris,
     * atau saring baris yang belum terdaftar keluar dari daftar.
     */
    it('menyebut nama dan sebab orang yang belum dikenal HC Learn', async () => {
        vi.spyOn(paApi, 'kesiapanKaryawanHcLearn').mockResolvedValue({
            result: {
                status: 'PERLU_SINKRON',
                ringkasan: { terlibat: 2, siap: 1, belum_terdaftar: 1, tidak_siap: 0 },
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
                catatan: '1 dari 2 orang yang terlibat belum terdaftar di HC Learn.',
            },
        });

        const wrapper = pasang();
        await flushPromises();

        expect(paApi.kesiapanKaryawanHcLearn).toHaveBeenCalledWith(2585);
        expect(wrapper.text()).toContain('Sri Wahyuni');
        expect(wrapper.text()).toContain('A2');
        expect(wrapper.text()).toContain('BELUM_TERDAFTAR');
    });

    /**
     * Perubahan yang membuat uji ini merah: panggil endpoint sinkron tanpa cycleId, atau
     * jangan segarkan kesiapannya sesudah sinkronisasi berhasil.
     */
    it('menyinkronkan lalu menampilkan keadaan terbaru', async () => {
        const kesiapan = vi.spyOn(paApi, 'kesiapanKaryawanHcLearn')
            .mockResolvedValueOnce({
                result: {
                    status: 'PERLU_SINKRON',
                    ringkasan: { terlibat: 2, siap: 1, belum_terdaftar: 1, tidak_siap: 0 },
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
                    catatan: 'Belum siap.',
                },
            });

        vi.spyOn(paApi, 'sinkronkanKaryawanHcLearn').mockResolvedValue({
            result: {
                sinkron: {
                    ringkasan: { diminta: 1, tersimpan: 1, sudah_ada: 0, gagal: 0 },
                    tersimpan: [{ kode: 'A2', nama: 'Sri Wahyuni' }],
                    sudah_ada: [],
                    gagal: [],
                },
                kesiapan: {
                    status: 'SIAP',
                    ringkasan: { terlibat: 2, siap: 2, belum_terdaftar: 0, tidak_siap: 0 },
                    baris: [],
                    catatan: '2 orang yang terlibat sudah siap dijadwalkan.',
                },
            },
        });

        const wrapper = pasang();
        await flushPromises();

        const tombol = wrapper.findAll('button').find((b) => b.text().includes('Sinkronkan ke HC Learn'));
        expect(tombol).toBeTruthy();

        await tombol.trigger('click');
        await flushPromises();

        expect(paApi.sinkronkanKaryawanHcLearn).toHaveBeenCalledWith(2585);

        /* Keadaan barunya datang dari BALASAN sinkronisasi, bukan dari pemanggilan ulang.
           Memanggil ulang berarti satu perjalanan tambahan untuk jawaban yang sudah di
           tangan — dan pada jaringan ke HC Learn itu perjalanan yang benar-benar terasa. */
        expect(kesiapan).toHaveBeenCalledTimes(1);
        expect(wrapper.text()).toContain('sudah siap dijadwalkan');
        expect(wrapper.text()).not.toContain('Sri Wahyuni');
    });

    /**
     * PENYIAPAN MELAHIRKAN PESERTA, jadi kesiapannya berubah saat itu juga.
     *
     * Sebelum penyiapan dijalankan populasinya kosong dan panel berbunyi "Menunggu
     * penyiapan". Kalau ia tidak dibaca ulang sesudahnya, admin melihat lampu abu-abu pada
     * siklus yang baru saja berisi ratusan orang — dan yang paling mungkin ia simpulkan
     * adalah panel itu tidak berlaku baginya.
     *
     * Perubahan yang membuat uji ini merah: jangan panggil ulang kesiapan sesudah
     * `siapkanSiklus()` berhasil.
     */
    it('membaca ulang kesiapan sesudah penyiapan dijalankan', async () => {
        const kesiapan = vi.spyOn(paApi, 'kesiapanKaryawanHcLearn').mockResolvedValue({
            result: {
                status: 'BELUM_ADA_PESERTA',
                ringkasan: { terlibat: 0, siap: 0, belum_terdaftar: 0, tidak_siap: 0 },
                baris: [],
                catatan: 'Belum ada peserta pada siklus ini.',
            },
        });

        vi.spyOn(paApi, 'siapkanSiklus').mockResolvedValue({ result: { peserta: { diikutkan: 150 } } });

        const wrapper = pasang();
        await flushPromises();
        expect(kesiapan).toHaveBeenCalledTimes(1);

        const jalankan = wrapper.findAll('button').find((b) => b.text().includes('Jalankan Penyiapan'));
        await jalankan.trigger('click');

        const konfirmasi = wrapper.findAll('.hcis-dialog button').find((b) => b.text().includes('Ya, Jalankan Penyiapan'));
        await konfirmasi.trigger('click');
        await flushPromises();
        // Penyiapan menahan 400 ms di 100% sebelum dialog ditutup dan langkah berikutnya dibuka.
        await new Promise((r) => setTimeout(r, 450));
        await flushPromises();

        expect(paApi.siapkanSiklus).toHaveBeenCalledWith(2585);
        expect(kesiapan).toHaveBeenCalledTimes(2);
    });
});
