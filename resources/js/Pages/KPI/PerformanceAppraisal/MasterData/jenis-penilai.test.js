import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import JenisPenilaiPage from './jenis-penilai.vue';
import paApi from '../api/paApi.js';

vi.mock('../api/paApi.js', () => ({
    default: {
        matriksJenisPenilai: vi.fn(),
        setelSelJenisPenilai: vi.fn(),
        kecualikanLevelPa: vi.fn(),
        cabutPengecualianLevelPa: vi.fn(),
        simpanKomposisiLevel: vi.fn(),
        kecualikanBanyakLevelPa: vi.fn(),
        cabutBanyakPengecualianLevelPa: vi.fn(),
    },
}));

const uiStubs = {
    HcisAlert: { props: ['title'], template: '<div class="alert-stub">{{ title }}<slot /></div>' },
    HcisBadge: { props: ['label'], template: '<span class="badge-stub">{{ label }}</span>' },
    HcisButton: {
        props: ['label', 'disabled', 'href'],
        emits: ['click'],
        template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>',
    },
    HcisCard: { template: '<div class="card-stub"><slot /></div>' },
    HcisEmptyState: {
        props: ['title', 'description', 'primaryText'],
        emits: ['primary-click'],
        template: '<div class="empty-stub"><h3>{{ title }}</h3><p>{{ description }}</p><button @click="$emit(\'primary-click\')">{{ primaryText }}</button></div>',
    },
    HcisDialog: {
        props: ['modelValue', 'title'],
        emits: ['update:modelValue'],
        template: '<div v-if="modelValue" class="dialog-stub">{{ title }}<slot /><slot name="footer" /></div>',
    },
    HcisField: {
        props: ['modelValue', 'label', 'placeholder', 'type'],
        emits: ['update:modelValue'],
        template: '<label class="field-stub">{{ label }}<textarea v-if="type === \'textarea\'" :value="modelValue" :placeholder="placeholder" @input="$emit(\'update:modelValue\', $event.target.value)" /><input v-else :value="modelValue" :placeholder="placeholder" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
    },
    HcisHeader: {
        props: ['title', 'description'],
        template: '<header><h1>{{ title }}</h1><p>{{ description }}</p><slot name="badge" /><slot name="actions" /><slot name="console" /></header>',
    },
    HcisIcon: { template: '<i />' },
    HcisResultState: { props: ['title', 'description'], template: '<div class="result-stub">{{ title }} {{ description }}</div>' },
    HcisSkeleton: { template: '<div class="skeleton-stub" />' },
    HcisStatGroup: { template: '<div class="stat-group-stub"><slot /></div>' },
    HcisStatCard: { props: ['label', 'value', 'caption'], template: '<div class="stat-card-stub"><span>{{ label }}</span><strong>{{ value }}</strong><small>{{ caption }}</small></div>' },
};

/* Bentuknya mengikuti `PaJenisPenilaiMatriksService::matriks()` apa adanya. */
const matriks = {
    kategori: [
        // Urutan kolom sengaja dipertahankan: beberapa uji di bawah menunjuk sel menurut indeks.
        { id: 1, kode: 'ATASAN', nama: 'Atasan', deskripsi: null, urutan_pengisian: 1, boleh_kalibrasi: true },
        { id: 2, kode: 'BAWAHAN', nama: 'Bawahan', deskripsi: null, urutan_pengisian: 2, boleh_kalibrasi: false },
        { id: 4, kode: 'SELF', nama: 'Diri Sendiri', deskripsi: null, urutan_pengisian: 3, boleh_kalibrasi: false },
    ],
    level: [
        {
            id_level: 2,
            nama_level: 'STAFF',
            jumlah_karyawan: 391,
            dikecualikan_pa: false,
            alasan_dikecualikan: null,
            policy: { kode: 'PA-RTR-STAFF', nama: 'Aturan Staff', versi: 1, status: 'ACTIVE' },
            sel: {
                ATASAN: { berlaku: true, berbobot: true, bobot: 80, min: 1, maks: 2, min_selesai: 1, izinkan_manual: true },
                BAWAHAN: null,
                SELF: { berlaku: true, berbobot: false, bobot: null, min: null, maks: null, min_selesai: null, izinkan_manual: false },
            },
            jumlah_kategori: 2,
            jumlah_belum_berbobot: 1,
            total_bobot: 80,
        },
        {
            id_level: 21,
            nama_level: 'FREELANCE',
            jumlah_karyawan: 98,
            dikecualikan_pa: true,
            alasan_dikecualikan: 'Level FREELANCE dikecualikan dari Performance Appraisal.',
            policy: null,
            sel: { ATASAN: null, BAWAHAN: null, SELF: null },
            jumlah_kategori: 0,
            jumlah_belum_berbobot: 0,
            total_bobot: 0,
        },
    ],
    perlu_perhatian: [],
    total_dikecualikan: 1,
};

function pasang() {
    return mount(JenisPenilaiPage, { global: { stubs: uiStubs } });
}

describe('PerformanceAppraisalJenisPenilaiPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        paApi.matriksJenisPenilai.mockResolvedValue(matriks);
        paApi.kecualikanLevelPa.mockResolvedValue({ id_level: 2, nama_level: 'STAFF', peserta_siklus_berjalan: 12 });
        paApi.cabutPengecualianLevelPa.mockResolvedValue({ id_level: 21 });
        paApi.setelSelJenisPenilai.mockResolvedValue({ id_level: 2, pa_rater_category_id: 2, berlaku: true, berbobot: false });
        paApi.simpanKomposisiLevel.mockResolvedValue({ policy_id: 1, baris: 2 });
        paApi.kecualikanBanyakLevelPa.mockResolvedValue({ jumlah_level: 1, level: [{ id_level: 2, nama_level: 'STAFF', peserta_siklus_berjalan: 12 }], total_peserta_berjalan: 12, alasan: 'Massal' });
        paApi.cabutBanyakPengecualianLevelPa.mockResolvedValue({ jumlah_level: 1, id_level: [21] });
    });

    it('merender matriks level x kategori beserta bobot dan kuotanya', async () => {
        const wrapper = pasang();
        await flushPromises();

        expect(paApi.matriksJenisPenilai).toHaveBeenCalled();
        expect(wrapper.find('h1').text()).toContain('Jenis & Komposisi Penilai');

        /* Kolomnya kategori penilai, barisnya level — kalau salah satu tertukar, halaman ini
           kembali menjadi daftar yang tidak menjawab apa pun. */
        expect(wrapper.text()).toContain('Atasan');
        expect(wrapper.text()).toContain('Diri Sendiri');
        expect(wrapper.text()).toContain('STAFF');
        expect(wrapper.text()).toContain('80%');
        expect(wrapper.text()).toContain('1–2 penilai');

        /* Sel yang BERLAKU tetapi belum berbobot tidak boleh menampilkan persentase apa pun.
           "0%" berarti kategori itu menilai tanpa menyumbang skor — pernyataan yang berbeda,
           dan admin yang membacanya akan mengira bobotnya sudah ditetapkan. */
        const selMenunggu = wrapper.findAll('tbody tr')
            .find((r) => r.text().includes('STAFF'))
            .findAll('.pa-cell-btn')[2];

        expect(selMenunggu.text()).toContain('Menunggu bobot');
        expect(selMenunggu.text()).not.toContain('%');
    });

    /* TIDAK ADA YANG BERUBAH TANPA DITANYA. Perubahan di layar ini menentukan siapa menilai
       siapa pada siklus berikutnya; satu klik meleset tidak menghasilkan galat, ia
       menghasilkan penilaian yang orangnya salah. */
    async function klikSel(wrapper, namaLevel, indeks) {
        const baris = wrapper.findAll('tbody tr').find((r) => r.text().includes(namaLevel));
        await baris.findAll('.pa-cell-btn')[indeks].trigger('click');

        return wrapper.find('.dialog-stub');
    }

    it('menanyakan konfirmasi sebelum memberlakukan sel, dan menyebut akibatnya', async () => {
        const wrapper = pasang();
        await flushPromises();

        const dialog = await klikSel(wrapper, 'STAFF', 1);   // BAWAHAN, belum berlaku

        expect(dialog.exists()).toBe(true);
        expect(paApi.setelSelJenisPenilai).not.toHaveBeenCalled();

        /* Akibatnya disebut, bukan sekadar "yakin?" - konfirmasi yang cuma bertanya yakin
           akan dijawab ya tanpa dibaca dalam dua-tiga kali pemakaian. */
        expect(dialog.text()).toContain('tanpa bobot');
        expect(dialog.text()).toContain('tidak bisa disiapkan');

        await dialog.find('[data-uji="konfirmasi-lanjut"]').trigger('click');
        await flushPromises();

        expect(paApi.setelSelJenisPenilai).toHaveBeenCalledWith(2, 2, true);
        expect(paApi.matriksJenisPenilai).toHaveBeenCalledTimes(2);
    });

    it('membatalkan konfirmasi tidak mengubah apa pun', async () => {
        const wrapper = pasang();
        await flushPromises();

        const dialog = await klikSel(wrapper, 'STAFF', 0);   // ATASAN, sedang berlaku

        await dialog.findAll('button').find((b) => b.text() === 'Batal').trigger('click');
        await flushPromises();

        expect(paApi.setelSelJenisPenilai).not.toHaveBeenCalled();
        expect(wrapper.find('.dialog-stub').exists()).toBe(false);
    });

    it('menyebut bobot yang ikut tercabut sebelum mencabut sel', async () => {
        const wrapper = pasang();
        await flushPromises();

        const dialog = await klikSel(wrapper, 'STAFF', 0);   // ATASAN, sedang berlaku

        expect(dialog.text()).toContain('ikut tercabut');
        expect(dialog.text()).toContain('dinonaktifkan, bukan dihapus');

        await dialog.find('[data-uji="konfirmasi-lanjut"]').trigger('click');
        await flushPromises();

        expect(paApi.setelSelJenisPenilai).toHaveBeenCalledWith(2, 1, false);
    });

    it('menandai level yang dikecualikan berikut alasannya, dan mengosongkan selnya', async () => {
        const wrapper = pasang();
        await flushPromises();

        const barisFreelance = wrapper.findAll('tbody tr').find((r) => r.text().includes('FREELANCE'));

        expect(barisFreelance).toBeTruthy();
        expect(barisFreelance.classes()).toContain('is-excluded');
        expect(barisFreelance.text()).toContain('Non-PA');

        /* Alasannya WAJIB terlihat. Pengecualian tanpa sebab yang terbaca membuat tidak ada
           yang berani mencabutnya setahun kemudian. */
        expect(barisFreelance.text()).toContain('dikecualikan dari Performance Appraisal');

        // Barisnya diredupkan, BUKAN disembunyikan: yang tak terlihat tak bisa dicabut.
        expect(barisFreelance.text()).toContain('Ikutkan Lagi');
    });

    it('menuntut alasan sebelum tombol kecualikan bisa ditekan', async () => {
        const wrapper = pasang();
        await flushPromises();

        const baris = wrapper.findAll('tbody tr').find((r) => r.text().includes('STAFF'));
        await baris.findAll('button').find((b) => b.text() === 'Kecualikan').trigger('click');

        const dialog = wrapper.find('.dialog-stub');

        expect(dialog.exists()).toBe(true);

        const tombol = dialog.findAll('button').find((b) => b.text() === 'Kecualikan Level Ini');

        expect(tombol.attributes('disabled')).toBeDefined();
        expect(paApi.kecualikanLevelPa).not.toHaveBeenCalled();

        await dialog.find('textarea').setValue('Bukan jenjang jabatan sungguhan.');

        expect(
            wrapper.find('.dialog-stub').findAll('button').find((b) => b.text() === 'Kecualikan Level Ini')
                .attributes('disabled'),
        ).toBeUndefined();
    });

    it('mengirim alasan apa adanya dan memuat ulang matriksnya', async () => {
        const wrapper = pasang();
        await flushPromises();

        const baris = wrapper.findAll('tbody tr').find((r) => r.text().includes('STAFF'));
        await baris.findAll('button').find((b) => b.text() === 'Kecualikan').trigger('click');
        await wrapper.find('.dialog-stub textarea').setValue('  Bukan jenjang jabatan sungguhan.  ');
        await wrapper.find('.dialog-stub').findAll('button').find((b) => b.text() === 'Kecualikan Level Ini').trigger('click');
        await flushPromises();

        expect(paApi.kecualikanLevelPa).toHaveBeenCalledWith(2, 'Bukan jenjang jabatan sungguhan.');
        expect(paApi.matriksJenisPenilai).toHaveBeenCalledTimes(2);

        /* Angka peserta siklus berjalan DISEBUT ulang: yang tadi dijanjikan "tidak disentuh"
           harus terlihat benar-benar tidak tersentuh. */
        expect(wrapper.text()).toContain('12 peserta di siklus berjalan tetap utuh');
    });

    it('menanyakan konfirmasi sebelum mengikutkan kembali level yang dikecualikan', async () => {
        const wrapper = pasang();
        await flushPromises();

        const baris = wrapper.findAll('tbody tr').find((r) => r.text().includes('FREELANCE'));
        await baris.findAll('button').find((b) => b.text() === 'Ikutkan Lagi').trigger('click');

        const dialog = wrapper.find('.dialog-stub');

        expect(dialog.exists()).toBe(true);
        expect(paApi.cabutPengecualianLevelPa).not.toHaveBeenCalled();

        // Angkanya disebut: 98 orang akan kembali menjadi peserta.
        expect(dialog.text()).toContain('98 karyawan');

        await dialog.find('[data-uji="konfirmasi-lanjut"]').trigger('click');
        await flushPromises();

        expect(paApi.cabutPengecualianLevelPa).toHaveBeenCalledWith(21);
        expect(paApi.matriksJenisPenilai).toHaveBeenCalledTimes(2);
    });

    it('mendukung penyaringan baris level lewat tab filter', async () => {
        const wrapper = pasang();
        await flushPromises();

        expect(wrapper.text()).toContain('STAFF');
        expect(wrapper.text()).toContain('FREELANCE');

        // Klik chip "Dikecualikan" di HcisFilterBar (dulu tab `.pa-filter-tab`, sejak tata letak responsif).
        const tabDikecualikan = wrapper.findAll('.hcis-chip-btn').find((t) => t.text().includes('Dikecualikan'));
        expect(tabDikecualikan).toBeTruthy();
        await tabDikecualikan.trigger('click');

        expect(wrapper.text()).toContain('FREELANCE');
        expect(wrapper.text()).not.toContain('STAFF');
    });

    it('mendukung pencarian real-time dan reset filter saat kosong', async () => {
        const wrapper = pasang();
        await flushPromises();

        // Cari "STAFF"
        wrapper.vm.kataKunciLevel = 'STAFF';
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('STAFF');
        expect(wrapper.text()).not.toContain('FREELANCE');

        // Cari kata kunci yang tidak ada
        wrapper.vm.kataKunciLevel = 'NON_EXISTENT_KEYWORD';
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.empty-stub').exists()).toBe(true);

        // Reset filter
        wrapper.vm.resetFilter();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.empty-stub').exists()).toBe(false);
        expect(wrapper.text()).toContain('STAFF');
        expect(wrapper.text()).toContain('FREELANCE');
    });

    it('membuka dialog komposisi penilai saat tombol atur bobot atau pill total diklik dan dapat menyimpan komposisi', async () => {
        const wrapper = pasang();
        await flushPromises();

        const barisStaff = wrapper.findAll('tbody tr').find((r) => r.text().includes('STAFF'));
        expect(barisStaff.classes()).toContain('is-deficit');

        // Total pill menampilkan "Kurang 20%"
        const totalPill = barisStaff.find('.pa-total-pill.is-off');
        expect(totalPill.exists()).toBe(true);
        expect(totalPill.text()).toContain('80% (Kurang 20%)');

        // Kolom aksi menyediakan tombol "Atur Bobot"
        const tombolAtur = barisStaff.findAll('button').find((b) => b.text().includes('Atur Bobot'));
        expect(tombolAtur).toBeTruthy();
        await tombolAtur.trigger('click');
        await flushPromises();

        // Dialog komposisi terbuka
        expect(wrapper.vm.dialogKomposisi).toBe(true);
        expect(wrapper.vm.levelKomposisi.id_level).toBe(2);

        // Tes Bagi Rata Bobot: 2 kategori aktif (ATASAN dan SELF) -> 50% & 50%
        wrapper.vm.bagiRataBobot();
        expect(wrapper.vm.totalBobotDraf).toBe(100);
        expect(wrapper.vm.drafValid).toBe(true);

        // Simpan komposisi
        await wrapper.vm.simpanKomposisiDraf();
        await flushPromises();

        expect(paApi.simpanKomposisiLevel).toHaveBeenCalledWith(2, expect.arrayContaining([
            expect.objectContaining({ pa_rater_category_id: 1, bobot_persen: 50 }),
            expect.objectContaining({ pa_rater_category_id: 4, bobot_persen: 50 }),
        ]));
        expect(paApi.matriksJenisPenilai).toHaveBeenCalledTimes(2);
    });

    it('mendukung pemilihan multi-level dan menampilkan floating bulk action bar', async () => {
        const wrapper = pasang();
        await flushPromises();

        expect(wrapper.vm.terpilih).toEqual([]);
        expect(wrapper.find('.pa-bulk-bar').exists()).toBe(false);

        // Pilih baris level STAFF (ID: 2)
        wrapper.vm.togglePilihLevel(2);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.terpilih).toEqual([2]);
        expect(wrapper.find('.pa-bulk-bar').exists()).toBe(true);
        expect(wrapper.find('.pa-bulk-bar').text()).toContain('1 level dipilih');
        expect(wrapper.find('.pa-bulk-bar').text()).toContain('391 karyawan');

        // Pilih Semua
        wrapper.vm.togglePilihSemua(true);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.terpilih).toEqual([2, 21]);
        expect(wrapper.vm.isSemuaTerpilih).toBe(true);
        expect(wrapper.find('.pa-bulk-bar').text()).toContain('2 level dipilih');
        expect(wrapper.find('.pa-bulk-bar').text()).toContain('1 aktif, 1 non-PA');

        // Batalkan pemilihan lewat tombol Batal Pilih
        const tombolBatal = wrapper.find('.pa-bulk-bar').findAll('button').find((b) => b.text().includes('Batal Pilih'));
        expect(tombolBatal).toBeTruthy();
        await tombolBatal.trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.terpilih).toEqual([]);
        expect(wrapper.find('.pa-bulk-bar').exists()).toBe(false);
    });

    it('dapat mengecualikan banyak level sekaligus dengan alasan yang valid', async () => {
        const wrapper = pasang();
        await flushPromises();

        // Pilih level 2 (aktif)
        wrapper.vm.terpilih = [2];
        await wrapper.vm.$nextTick();

        // Buka dialog bulk kecualikan
        wrapper.vm.bukaDialogBulkKecualikan();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.dialogBulkKecualikan).toBe(true);

        const dialog = wrapper.find('.dialog-stub');
        expect(dialog.exists()).toBe(true);
        expect(dialog.text()).toContain('STAFF');

        // Isi alasan dan jalankan
        wrapper.vm.alasanBulk = 'Pengecualian massal untuk departemen pendukung.';
        await wrapper.vm.jalankanBulkKecualikan();
        await flushPromises();

        expect(paApi.kecualikanBanyakLevelPa).toHaveBeenCalledWith([2], 'Pengecualian massal untuk departemen pendukung.');
        expect(wrapper.vm.dialogBulkKecualikan).toBe(false);
        expect(wrapper.vm.terpilih).toEqual([]);
        expect(paApi.matriksJenisPenilai).toHaveBeenCalledTimes(2);
    });

    it('dapat mencabut pengecualian banyak level sekaligus secara massal', async () => {
        const wrapper = pasang();
        await flushPromises();

        // Pilih level 21 (dikecualikan)
        wrapper.vm.terpilih = [21];
        await wrapper.vm.$nextTick();

        // Buka konfirmasi bulk cabut
        wrapper.vm.bukaKonfirmasiBulkCabut();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.dialogBulkCabut).toBe(true);

        // Jalankan pencabutan massal
        await wrapper.vm.jalankanBulkCabut();
        await flushPromises();

        expect(paApi.cabutBanyakPengecualianLevelPa).toHaveBeenCalledWith([21]);
        expect(wrapper.vm.dialogBulkCabut).toBe(false);
        expect(wrapper.vm.terpilih).toEqual([]);
        expect(paApi.matriksJenisPenilai).toHaveBeenCalledTimes(2);
    });
});

