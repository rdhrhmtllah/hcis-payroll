import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PaStepBobotPilar from './PaStepBobotPilar.vue';
import PaStepPaketSoal from './PaStepPaketSoal.vue';
import PaStepAturanPenilai from './PaStepAturanPenilai.vue';
import PaStepPeserta from './PaStepPeserta.vue';
import paApi from '../../api/paApi.js';

const uiStubs = {
    HcisCard: { template: '<div class="hcis-card"><slot name="actions" /><slot /><slot name="footer" /></div>', props: ['title', 'kicker', 'description'] },
    HcisButton: { template: '<button :disabled="disabled" @click="$emit(\'click\', $event)"><slot />{{ label }}</button>', props: ['label', 'disabled', 'loading', 'variant'] },
    HcisBadge: { template: '<span class="hcis-badge">{{ label }}</span>', props: ['label', 'variant'] },
    HcisAlert: { template: '<div class="hcis-alert">{{ title }}<slot /></div>', props: ['title', 'variant'] },
    HcisResultState: { template: '<div class="hcis-result-state">{{ title }}</div>', props: ['title'] },
    HcisEmptyState: { template: '<div class="hcis-empty-state">{{ title }}<button @click="$emit(\'primary-click\')">Action</button></div>', props: ['title'] },
    HcisSkeleton: { template: '<div class="hcis-skeleton" />' },
    HcisInputNumber: {
        template: '<input type="number" :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', Number($event.target.value))" />',
        props: ['modelValue', 'disabled', 'min', 'max', 'size'],
    },
    HcisSwitch: {
        template: '<input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.checked)" />',
        props: ['modelValue', 'disabled', 'size'],
    },
    HcisCollapse: { template: '<div class="hcis-collapse"><slot /></div>', props: ['modelValue', 'accordion'] },
    HcisCollapseItem: { template: '<div class="hcis-collapse-item"><slot name="extra" /><slot /></div>', props: ['name', 'title'] },
    HcisTable: { template: '<div class="hcis-table"><slot name="cell-aksi" :row="{ Id_PA_Cycle_Question_Package: 101 }" /></div>', props: ['columns', 'rows'] },
    HcisDialog: { template: '<div v-if="modelValue" class="hcis-dialog"><slot /><slot name="footer" /></div>', props: ['modelValue', 'title'] },
    HcisField: { template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />', props: ['modelValue', 'placeholder'] },
    HcisSelect: { template: '<select :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><slot /></select>', props: ['modelValue'] },
    HcisSelectOption: { template: '<option :value="value">{{ label }}</option>', props: ['value', 'label'] },
    HcisTextarea: { template: '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />', props: ['modelValue'] },
    HcisStatGroup: { template: '<div class="hcis-stat-group"><slot /></div>', props: ['cols'] },
    HcisStatCard: { template: '<div class="hcis-stat-card">{{ label }}: {{ value }}</div>', props: ['label', 'value'] },
    HcisIcon: { template: '<i :class="name" />', props: ['name'] },
};

describe('PaStepBobotPilar', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(paApi, 'bobotPilarSiklus').mockResolvedValue({
            result: {
                bobot: [
                    { ID_Level: 1, Nama_Level: 'Staff', Bobot_KPI: 80, Bobot_Kompetensi: 20, dapat_disunting: true },
                    { ID_Level: 2, Nama_Level: 'Manager', Bobot_KPI: 70, Bobot_Kompetensi: 30, dapat_disunting: true },
                ],
            },
        });
        vi.spyOn(paApi, 'simpanBobotPilar').mockResolvedValue({
            result: { pesan: 'Bobot tersimpan.' },
        });
    });

    it('renders level weights with dual proportion indicators and presets', async () => {
        const wrapper = mount(PaStepBobotPilar, {
            props: { cycleId: 2585, dapatDisunting: true },
            global: { stubs: uiStubs },
        });

        await flushPromises();

        expect(paApi.bobotPilarSiklus).toHaveBeenCalledWith(2585);
        expect(wrapper.text()).toContain('Staff');
        expect(wrapper.text()).toContain('Manager');
        expect(wrapper.text()).toContain('100% Seimbang');
    });

    it('applies preset and saves level weight', async () => {
        const wrapper = mount(PaStepBobotPilar, {
            props: { cycleId: 2585, dapatDisunting: true },
            global: { stubs: uiStubs },
        });

        await flushPromises();

        // Click preset 70:30 on Staff
        const preset7030 = wrapper.findAll('.pa-preset-chip').find((b) => b.text() === '70:30');
        expect(preset7030).toBeTruthy();
        await preset7030.trigger('click');

        // Click Simpan Level
        const simpanButton = wrapper.findAll('button').find((b) => b.text().includes('Simpan Level'));
        expect(simpanButton).toBeTruthy();
        await simpanButton.trigger('click');
        await flushPromises();

        expect(paApi.simpanBobotPilar).toHaveBeenCalledWith(2585, 1, {
            bobot_kpi: 70,
            bobot_kompetensi: 30,
        });
    });
});

describe('PaStepBobotPilar — simpan semua gagal di tengah', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(paApi, 'bobotPilarSiklus').mockResolvedValue({
            result: {
                bobot: [
                    { ID_Level: 1, Nama_Level: 'Staff', Bobot_KPI: 80, Bobot_Kompetensi: 20, dapat_disunting: true },
                    { ID_Level: 2, Nama_Level: 'Manager', Bobot_KPI: 70, Bobot_Kompetensi: 30, dapat_disunting: true },
                ],
            },
        });
    });

    /* Tiap level satu permintaan; yang sudah tersimpan TETAP tersimpan. Pesannya wajib
       menyebut itu, bukan "gagal menyimpan seluruh bobot". */
    it('menyebut berapa level yang sudah tersimpan dan di mana berhenti', async () => {
        vi.spyOn(paApi, 'simpanBobotPilar')
            .mockResolvedValueOnce({ result: { pesan: 'ok' } })
            .mockRejectedValueOnce(new Error('Bobot level ini terkunci.'));
        const wrapper = mount(PaStepBobotPilar, { props: { cycleId: 2585, dapatDisunting: true }, global: { stubs: uiStubs } });
        await flushPromises();

        for (const chip of wrapper.findAll('.pa-preset-chip').filter((b) => b.text() === '60:40')) {
            await chip.trigger('click');
        }
        await wrapper.vm.simpanSemua();
        await flushPromises();

        expect(paApi.simpanBobotPilar).toHaveBeenCalledTimes(2);
        expect(wrapper.text()).toContain('1 dari 2 level sudah tersimpan');
        expect(wrapper.text()).toContain('Manager');
        expect(wrapper.text()).toContain('Bobot level ini terkunci.');
        expect(wrapper.emitted('tersimpan')).toBeTruthy();
    });
});

describe('PaStepPaketSoal', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(paApi, 'masterDaftar').mockResolvedValue({
            result: {
                baris: [
                    { Id_PA_Cycle_Question_Package: 101, ID_Level: 1, Id_Master_Ujian: 'PKT-STF-2026', Nama_Ujian_Sumber: 'Paket Staff', Flag_Aktif: 'Y' },
                ],
            },
        });
        vi.spyOn(paApi, 'daftarAturanPenilai').mockResolvedValue({
            result: {
                level: [
                    { id_level: 1, nama_level: 'Staff' },
                    { id_level: 2, nama_level: 'Manager' },
                ],
            },
        });
        /* levelSiklus TIDAK pernah dimock sebelumnya, sehingga ia mencoba permintaan HTTP
           sungguhan di jsdom. Komponen memang punya jalur cadangan ke daftarAturanPenilai,
           tetapi memuat komponen sambil membiarkan satu panggilan menembak jaringan
           membuat ujinya bergantung pada kegagalan yang waktunya tidak kita kendalikan. */
        vi.spyOn(paApi, 'levelSiklus').mockResolvedValue({
            level: [
                { id_level: 1, nama_level: 'Staff', jumlah_karyawan: 120 },
                { id_level: 2, nama_level: 'Manager', jumlah_karyawan: 30 },
            ],
        });

        /* Komponen memuat EMPAT sumber sekaligus lewat Promise.allSettled. Satu saja yang
           tidak dimock akan menembak jaringan sungguhan di jsdom dan tidak pernah settle,
           sehingga `memuat` tetap true — yang dirender lalu kerangka, bukan isinya. Ujinya
           gagal dengan "teks tidak ditemukan", seolah komponennya tidak menampilkan apa-apa,
           padahal ia masih menunggu. */
        vi.spyOn(paApi, 'bobotPilarSiklus').mockResolvedValue({ bobot: [] });
        vi.spyOn(paApi, 'masterSimpan').mockResolvedValue({ result: { id: 102 } });
        vi.spyOn(paApi, 'masterNonaktif').mockResolvedValue({ result: { id: 101 } });
    });

    it('identifies missing levels and allows adding package', async () => {
        const wrapper = mount(PaStepPaketSoal, {
            props: { cycleId: 2585, dapatDisunting: true },
            global: { stubs: uiStubs },
        });

        await flushPromises();

        expect(wrapper.text()).toContain('1 Level Belum Memiliki Paket');
        expect(wrapper.text()).toContain('Manager');

        const missingChip = wrapper.find('.pa-missing-chip');
        expect(missingChip.exists()).toBe(true);
        await missingChip.trigger('click');

        expect(wrapper.find('.hcis-dialog').exists()).toBe(true);
    });
});

describe('PaStepAturanPenilai', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(paApi, 'daftarAturanPenilai').mockResolvedValue({
            result: {
                perlu_disalin: false,
                level: [
                    {
                        id_level: 1,
                        nama_level: 'Staff',
                        nama_policy: 'Policy Staff 360',
                        versi_policy: 1,
                        rincian: [
                            { id: 1, nama_kategori: 'Atasan Langsung', kode_relasi: 'MGR', bobot_persen: 60, min_rater: 1, maks_rater: 1, min_rater_selesai: 1, izinkan_manual: false },
                            { id: 2, nama_kategori: 'Peer', kode_relasi: 'PEER', bobot_persen: 40, min_rater: 2, maks_rater: 3, min_rater_selesai: 2, izinkan_manual: true },
                        ],
                    },
                ],
                level_tanpa_aturan: [],
            },
        });
        vi.spyOn(paApi, 'simpanAturanPenilai').mockResolvedValue({
            result: { baris_diperbarui: 2 },
        });
    });

    it('renders category weights and supports 360 preset models', async () => {
        const wrapper = mount(PaStepAturanPenilai, {
            props: { cycleId: 2585, dapatDisunting: true },
            global: { stubs: uiStubs },
        });

        await flushPromises();

        expect(wrapper.text()).toContain('Policy Staff 360');
        expect(wrapper.text()).toContain('Atasan Langsung');
        expect(wrapper.text()).toContain('100% Pas');

        // Click Simpan Level Ini
        const simpanBtn = wrapper.findAll('button').find((b) => b.text().includes('Simpan Level Ini'));
        expect(simpanBtn).toBeTruthy();
        await simpanBtn.trigger('click');
        await flushPromises();

        expect(paApi.simpanAturanPenilai).toHaveBeenCalledWith(2585, 1, expect.any(Array));
    });
});

describe('PaStepPeserta', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(paApi, 'pratinjauPenyiapan').mockResolvedValue({
            result: {
                cacah: { diikutkan: 150, dikecualikan: 12, tertunda: 0 },
                pra_kecuali_terpakai: 2,
                level_tanpa_aturan: [],
            },
        });
        vi.spyOn(paApi, 'daftarPraKecuali').mockResolvedValue({
            result: {
                baris: [
                    { id: 1, kode_karyawan: '10001', nama: 'Budi Santoso', alasan: 'Cuti di luar tanggungan', karyawan_sudah_tidak_aktif: false },
                ],
            },
        });
        vi.spyOn(paApi, 'levelSiklus').mockResolvedValue({
            level: [{ id_level: 1, nama_level: 'Staff', jumlah_karyawan: 150 }],
        });
        vi.spyOn(paApi, 'opsiFilterKaryawanPraKecuali').mockResolvedValue({
            divisi: [], departemen: [], level: [],
        });
        vi.spyOn(paApi, 'cariKaryawanPraKecuali').mockResolvedValue({ karyawan: [] });
        vi.spyOn(paApi, 'siapkanSiklus').mockResolvedValue({
            result: { peserta: { diikutkan: 150 } },
        });
    });

    it('renders simulation metrics and triggers cycle preparation launch', async () => {
        const wrapper = mount(PaStepPeserta, {
            props: { cycleId: 2585, dapatDisunting: true, siapDijalankan: true, penghalang: [] },
            global: { stubs: uiStubs },
        });

        await flushPromises();

        expect(wrapper.text()).toContain('150');
        expect(wrapper.text()).toContain('Budi Santoso');
        expect(wrapper.text()).toContain('Cuti di luar tanggungan');

        const launchBtn = wrapper.findAll('button').find((b) => b.text().includes('Jalankan Penyiapan'));
        expect(launchBtn).toBeTruthy();
        await launchBtn.trigger('click');

        // Confirm modal opens
        expect(wrapper.find('.hcis-dialog').exists()).toBe(true);
        const confirmBtn = wrapper.findAll('.hcis-dialog button').find((b) => b.text().includes('Ya, Jalankan Penyiapan'));
        expect(confirmBtn).toBeTruthy();
        await confirmBtn.trigger('click');
        await flushPromises();
        // Penyiapan menahan 400 ms di 100% sebelum dialog ditutup dan langkah berikutnya dibuka.
        await new Promise((r) => setTimeout(r, 450));
        await flushPromises();

        expect(paApi.siapkanSiklus).toHaveBeenCalledWith(2585);
        expect(wrapper.emitted('selesai')).toBeTruthy();
    });

    it('menyebut peserta yang Level PA-nya disegarkan dan yang tertahan beserta sebabnya', async () => {
        paApi.siapkanSiklus.mockResolvedValue({
            peserta: {
                level_diperbarui: 2,
                level_tertahan: [{ participant_id: 9, nama: 'Andre', kode_karyawan: 'B6', nama_level_seharusnya: 'OFFICER', sebab: 'Sudah punya penugasan penilai.' }],
            },
        });
        const wrapper = mount(PaStepPeserta, {
            props: { cycleId: 2585, dapatDisunting: true, siapDijalankan: true, penghalang: [] },
            global: { stubs: uiStubs },
        });
        await flushPromises();
        await wrapper.findAll('button').find((b) => b.text().includes('Jalankan Penyiapan')).trigger('click');
        await wrapper.findAll('.hcis-dialog button').find((b) => b.text().includes('Ya, Jalankan Penyiapan')).trigger('click');
        await flushPromises();
        await new Promise((r) => setTimeout(r, 450));
        await flushPromises();

        expect(wrapper.text()).toContain('2 peserta disegarkan ke Level PA terbaru');
        const alert = wrapper.find('[data-uji="level-tertahan"]');
        expect(alert.exists()).toBe(true);
        expect(alert.text()).toContain('Andre');
        expect(alert.text()).toContain('Sudah punya penugasan penilai.');
        // Ada yang tertahan → tidak pindah ke Langkah 5, supaya daftarnya terbaca.
        expect(wrapper.emitted('selesai')).toBeFalsy();
    });
});

describe('PaStepPeserta — sudah dijalankan', () => {
    const pratinjauSesudah = (bilaDiulang = { peserta_baru: 0, level_berubah: 0, level_tertahan: 0 }) => ({
        result: {
            cacah: { diikutkan: 0, dikecualikan: 0, dikecualikan_otomatis: 0, tertunda: 0, sudah_ada: 958 },
            level_tanpa_aturan: [],
            sudah_dijalankan: true,
            terbentuk: { total: 958, diikutkan: 191, dikecualikan: 767, tertunda: 0 },
            terakhir_dijalankan: { pada: '2026-10-12 16:44:40', oleh: 'NOIR' },
            bila_diulang: bilaDiulang,
        },
    });

    const pasang = () =>
        mount(PaStepPeserta, {
            props: { cycleId: 2585, dapatDisunting: true, siapDijalankan: true, penghalang: [] },
            global: { stubs: uiStubs },
        });

    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(paApi, 'pratinjauPenyiapan').mockResolvedValue(pratinjauSesudah());
        vi.spyOn(paApi, 'daftarPraKecuali').mockResolvedValue({ result: { baris: [] } });
        vi.spyOn(paApi, 'levelSiklus').mockResolvedValue({ level: [] });
        vi.spyOn(paApi, 'kesiapanKaryawanHcLearn').mockResolvedValue({ result: { status: 'SIAP', ringkasan: {}, baris: [] } });
        vi.spyOn(paApi, 'siapkanSiklus').mockResolvedValue({ result: { peserta: { level_diperbarui: 0, level_tertahan: [] } } });
    });

    it('melaporkan hasil nyata dan kapan dijalankan, bukan bertanya "siap menjalankan?"', async () => {
        const w = pasang();
        await flushPromises();

        const status = w.find('[data-uji="penyiapan-sudah"]');
        expect(status.text()).toContain('Penyiapan sudah dijalankan');
        expect(w.find('[data-uji="penyiapan-terakhir"]').text()).toContain('oleh NOIR');
        expect(w.find('[data-uji="penyiapan-terakhir"]').text()).toContain('191 diikutkan');
        expect(w.find('[data-uji="penyiapan-bila-ulang"]').text()).toContain('Tidak ada perubahan');
        expect(w.text()).not.toContain('Siap menjalankan penyiapan?');
        expect(w.findAll('button').some((b) => b.text() === 'Jalankan Penyiapan')).toBe(false);

        // Kartu angka membaca peserta yang terbentuk, bukan pratinjau orang baru (yang nol).
        expect(w.find('[data-uji="peserta-angka"]').text()).toContain('Diikutkan: 191');
        expect(w.find('[data-uji="peserta-angka"]').text()).toContain('Dikecualikan: 767');

        // Panel HC Learn hanya di Langkah 5 sesudah penyiapan berjalan.
        expect(paApi.kesiapanKaryawanHcLearn).not.toHaveBeenCalled();

        await w.find('[data-uji="lanjut-penugasan"]').trigger('click');
        expect(w.emitted('lanjut')).toBeTruthy(); // stub tombol memancarkan klik dua kali (native + emit)
    });

    it('menyebut apa yang berubah bila dijalankan ulang, lalu menjalankannya', async () => {
        paApi.pratinjauPenyiapan.mockResolvedValue(pratinjauSesudah({ peserta_baru: 3, level_berubah: 2, level_tertahan: 1 }));
        const w = pasang();
        await flushPromises();

        const ulang = w.find('[data-uji="penyiapan-bila-ulang"]').text();
        expect(ulang).toContain('3 karyawan baru');
        expect(ulang).toContain('2 peserta berubah Level PA');
        expect(ulang).toContain('1 di antaranya tertahan');

        await w.find('[data-uji="jalankan-ulang"]').trigger('click');
        expect(w.find('[data-uji="dialog-ulang"]').exists()).toBe(true);
        await w.findAll('.hcis-dialog button').find((b) => b.text() === 'Ya, Jalankan Ulang Penyiapan').trigger('click');
        await flushPromises();
        await new Promise((r) => setTimeout(r, 450));
        await flushPromises();

        expect(paApi.siapkanSiklus).toHaveBeenCalledWith(2585);
    });
});

describe('PaStepPeserta — cabut pengecualian (centang)', () => {
    const baris = [
        { id: 11, kode_perusahaan: '001', kode_karyawan: 'B8', nama: 'Anita', alasan: 'Karena ini', dikecualikan_saat_ini: true },
        { id: 12, kode_perusahaan: '001', kode_karyawan: 'K32', nama: 'Iqbal', alasan: 'Karena ini', dikecualikan_saat_ini: true },
    ];

    /* Stub checkbox yang memahami mode larik (v-model berisi daftar id) DAN mode tunggal. */
    const HcisCheckbox = {
        props: ['modelValue', 'value', 'label', 'indeterminate'],
        emits: ['update:modelValue'],
        template: '<label class="cb"><input type="checkbox" :checked="Array.isArray(modelValue) ? modelValue.includes(value) : !!modelValue" @change="ubah($event.target.checked)" />{{ label }}</label>',
        methods: {
            ubah(c) {
                if (Array.isArray(this.modelValue)) {
                    this.$emit('update:modelValue', c ? [...this.modelValue, this.value] : this.modelValue.filter((v) => v !== this.value));
                } else {
                    this.$emit('update:modelValue', c);
                }
            },
        },
    };

    const pasang = (props = {}) =>
        mount(PaStepPeserta, {
            props: { cycleId: 2585, dapatDisunting: true, bolehKecualikan: true, siapDijalankan: true, penghalang: [], ...props },
            global: { stubs: { ...uiStubs, HcisCheckbox } },
        });

    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(paApi, 'pratinjauPenyiapan').mockResolvedValue({
            result: { cacah: {}, level_tanpa_aturan: [], sudah_dijalankan: true, terbentuk: { total: 958, diikutkan: 189, dikecualikan: 769, tertunda: 0 }, bila_diulang: { peserta_baru: 0, level_berubah: 0, level_tertahan: 0 } },
        });
        vi.spyOn(paApi, 'daftarPraKecuali').mockResolvedValue({ result: { baris } });
        vi.spyOn(paApi, 'levelSiklus').mockResolvedValue({ level: [] });
        vi.spyOn(paApi, 'kesiapanKaryawanHcLearn').mockResolvedValue({ result: { status: 'SIAP', ringkasan: {}, baris: [] } });
    });

    /*
     | Centang semua → satu konfirmasi → satu panggilan untuk semuanya. Sesudahnya langkah
     | berikutnya disebut: peserta yang diikutkan kembali belum punya penugasan, jadi tombolnya
     | ke Langkah 5 — BUKAN "Jalankan Ulang", yang tidak mengubah peserta yang sudah ada.
     */
    it('mencabut yang dicentang sekaligus, lalu mengarahkan ke Langkah 5 untuk melengkapi penugasan', async () => {
        vi.spyOn(paApi, 'cabutPraKecualiBanyak').mockResolvedValue({ dicabut: 2, diikutkan: 2, belum_peserta: 0, ada_penugasan: true, gagal: [] });
        const w = pasang();
        await flushPromises();

        expect(w.find('[data-uji="cabut-terpilih"]').attributes('disabled')).toBeDefined();
        await w.find('[data-uji="cabut-pilih-semua"] input').setValue(true);
        expect(w.find('[data-uji="cabut-terpilih"]').text()).toContain('Cabut Pengecualian (2)');

        await w.find('[data-uji="cabut-terpilih"]').trigger('click');
        await w.find('[data-uji="cabut-konfirmasi"]').trigger('click');
        await flushPromises();

        expect(paApi.cabutPraKecualiBanyak).toHaveBeenCalledWith(2585, [11, 12]);
        const hasil = w.find('[data-uji="hasil-cabut"]');
        expect(hasil.text()).toContain('2 pengecualian dicabut · 2 diikutkan kembali');
        expect(w.find('[data-uji="cabut-jalankan-ulang"]').exists()).toBe(false);

        await w.find('[data-uji="cabut-ke-langkah-5"]').trigger('click');
        expect(w.emitted('lanjut')).toBeTruthy();
    });

    it('menawarkan Jalankan Ulang hanya untuk orang yang belum pernah menjadi peserta, dan menyebut yang gagal', async () => {
        vi.spyOn(paApi, 'cabutPraKecualiBanyak').mockResolvedValue({
            dicabut: 1, diikutkan: 0, belum_peserta: 1, ada_penugasan: false,
            gagal: [{ id: 12, nama: 'Iqbal', sebab: 'Level belum punya aturan penilai.' }],
        });
        const w = pasang();
        await flushPromises();

        await w.findAll('tbody .cb input')[0].setValue(true);
        await w.findAll('tbody .cb input')[1].setValue(true);
        await w.find('[data-uji="cabut-terpilih"]').trigger('click');
        await w.find('[data-uji="cabut-konfirmasi"]').trigger('click');
        await flushPromises();

        const hasil = w.find('[data-uji="hasil-cabut"]').text();
        expect(hasil).toContain('1 gagal');
        expect(hasil).toContain('Iqbal — Level belum punya aturan penilai.');
        expect(w.find('[data-uji="cabut-jalankan-ulang"]').exists()).toBe(true);
        expect(w.find('[data-uji="cabut-ke-langkah-5"]').exists()).toBe(false);
    });

    /* Mencabut hanya sebelum siklus dikunci; mengecualikan masih boleh sesudahnya. */
    it('tidak menawarkan centang maupun cabut sesudah siklus dikunci', async () => {
        const w = pasang({ dapatDisunting: false });
        await flushPromises();

        expect(w.find('[data-uji="cabut-pilih-semua"]').exists()).toBe(false);
        expect(w.findAll('button').some((b) => b.text().includes('Cabut Pengecualian'))).toBe(false);
    });
});


describe('PaStepPeserta — minimal masa kerja siklus', () => {
    const pratinjauBelum = { result: { cycle_id: 2585, cacah: { diikutkan: 10, dikecualikan_otomatis: 2 }, sudah_dijalankan: false } };

    const pasang = () =>
        mount(PaStepPeserta, {
            props: { cycleId: 2585, dapatDisunting: true, siapDijalankan: true, penghalang: [] },
            global: { stubs: uiStubs },
        });

    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(paApi, 'pratinjauPenyiapan').mockResolvedValue(pratinjauBelum);
        vi.spyOn(paApi, 'daftarPraKecuali').mockResolvedValue({ result: { baris: [] } });
        vi.spyOn(paApi, 'levelSiklus').mockResolvedValue({ level: [] });
    });

    it('menyimpan angka baru lalu memuat ulang pratinjau supaya akibatnya terlihat', async () => {
        const sesudah = { result: { min_masa_kerja_bulan: 6, nilai_master: 3, maks_bulan: 24, boleh_ubah: true, alasan_terkunci: null } };
        vi.spyOn(paApi, 'kelayakanMasaKerjaSiklus')
            .mockResolvedValueOnce({
                result: { min_masa_kerja_bulan: 3, nilai_master: 3, maks_bulan: 24, boleh_ubah: true, alasan_terkunci: null },
            })
            .mockResolvedValue(sesudah);
        const simpan = vi.spyOn(paApi, 'simpanKelayakanMasaKerjaSiklus').mockResolvedValue({
            result: { min_masa_kerja_bulan: 6, nilai_master: 3, maks_bulan: 24, boleh_ubah: true, alasan_terkunci: null },
        });

        const w = pasang();
        await flushPromises();

        await w.find('[data-uji="masa-kerja-ubah"]').trigger('click');
        await flushPromises();
        expect(w.find('[data-uji="masa-kerja-simpan"]').attributes('disabled')).toBeDefined(); // belum ada perubahan
        const muatSebelum = paApi.pratinjauPenyiapan.mock.calls.length;

        await w.find('[data-uji="aturan-masa-kerja"] input').setValue('6');
        await w.find('[data-uji="masa-kerja-simpan"]').trigger('click');
        await flushPromises();

        expect(simpan).toHaveBeenCalledWith(2585, 6);
        expect(paApi.pratinjauPenyiapan.mock.calls.length).toBeGreaterThan(muatSebelum);
        expect(w.find('[data-uji="masa-kerja-nilai"]').text()).toBe('6 bulan');
        // Dialog menutup sendiri sesudah simpan berhasil.
        expect(w.find('[data-uji="masa-kerja-simpan"]').exists()).toBe(false);

        await w.find('[data-uji="masa-kerja-ubah"]').trigger('click');
        await flushPromises();
        expect(w.find('[data-uji="masa-kerja-keterangan"]').text()).toContain('Nilai master saat ini 3 bulan');
    });

    it('sesudah peserta dibentuk hanya menampilkan angkanya beserta sebab terkuncinya', async () => {
        vi.spyOn(paApi, 'kelayakanMasaKerjaSiklus').mockResolvedValue({
            result: { min_masa_kerja_bulan: 3, nilai_master: 3, maks_bulan: 24, boleh_ubah: false, alasan_terkunci: 'Peserta sudah dibentuk dengan aturan ini.' },
        });

        const w = pasang();
        await flushPromises();

        expect(w.find('[data-uji="masa-kerja-ubah"]').exists()).toBe(false);
        expect(w.find('[data-uji="masa-kerja-input"]').exists()).toBe(false);
        expect(w.find('[data-uji="masa-kerja-nilai"]').text()).toBe('3 bulan');
        expect(w.find('[data-uji="masa-kerja-terkunci"]').text()).toContain('Peserta sudah dibentuk');
    });
});
