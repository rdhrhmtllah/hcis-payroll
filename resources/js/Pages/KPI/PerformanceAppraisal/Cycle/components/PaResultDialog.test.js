import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PaResultDialog from './PaResultDialog.vue';

const paApiMock = vi.hoisted(() => ({ simpanCatatanHasil: vi.fn(), riwayatBalancing: vi.fn() }));

vi.mock('../../api/paApi.js', () => ({ default: paApiMock }));

const uiStubs = {
    HcisBadge: {
        props: ['label'],
        template: '<span class="badge-stub">{{ label }}</span>',
    },
    HcisButton: {
        props: ['label', 'disabled', 'loading'],
        emits: ['click'],

        // `<slot />` ikut dirender: sebagian tombol menuliskan teksnya sebagai ISI, bukan
        // lewat prop `label`, dan stub tanpa slot membuat tombol itu tidak punya teks sama
        // sekali — pencarian lewat `text()` gagal pada tombol yang jelas ada di layar.
        template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}<slot /></button>',
    },
    HcisCard: {
        template: '<article><slot name="header" /><slot name="actions" /><slot /><slot name="footer" /></article>',
    },
    /* `options` dideklarasikan supaya isinya bisa diperiksa uji. Komponen sungguhannya
       memang menerima prop ini; stub tanpa deklarasi membuat opsi grafik menjadi bagian
       yang tak pernah bisa diuji sama sekali. */
    HcisChart: { name: 'HcisChart', props: ['options', 'type', 'categories', 'series', 'data'], template: '<div class="chart-stub" />' },
    HcisDialog: {
        props: ['modelValue'],
        template: '<div v-if="modelValue"><slot /><slot name="footer-start" /><slot name="footer" /></div>',
    },
    HcisField: {
        props: ['id', 'modelValue', 'label'],
        emits: ['update:modelValue'],
        template:
            '<label :for="id">{{ label }}<input :id="id" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
    },
    HcisIcon: { template: '<i />' },
    HcisResultState: {
        props: ['title', 'description', 'errorCode', 'requestId'],
        template:
            '<div class="result-state"><strong>{{ title }}</strong><span>{{ description }}</span><small>{{ errorCode }} {{ requestId }}</small></div>',
    },
    HcisStatCard: {
        props: ['label', 'value'],
        template: '<div class="stat-card-stub">{{ label }} {{ value }}</div>',
    },
    HcisStatusBadge: {
        props: ['label'],
        template: '<span class="status-badge-stub">{{ label }}</span>',
    },
    HcisTextarea: {
        props: ['id', 'modelValue', 'label'],
        emits: ['update:modelValue'],
        template:
            '<label :for="id">{{ label }}<textarea :id="id" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
    },
    /* Select kalibrasi. Stub-nya merender <option> sungguhan supaya uji bisa memeriksa APA
       yang ditawarkan — bukan sekadar bahwa ada select. Komponen aslinya membawa popover
       ber-teleport yang tidak berguna diperiksa di jsdom. */
    HcisSelect: {
        name: 'HcisSelect',
        props: ['id', 'modelValue', 'placeholder', 'disabled'],
        emits: ['update:modelValue'],
        template:
            '<select :id="id" :disabled="disabled" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><slot /></select>',
    },
    HcisSelectOption: {
        name: 'HcisSelectOption',
        props: ['value', 'label', 'description'],
        template: '<option :value="value">{{ label }}</option>',
    },
};

const participant = {
    Id_PA_Participant: 12,
    Nama_Karyawan_Saat_Cycle: 'Ahmad Fauzan',
    Kode_Karyawan: 'A0118',
};

const result = {
    peserta: {
        Id_PA_Result: 902,
        Kode_Karyawan: 'A0118',
        Nama_Karyawan_Saat_Cycle: 'Ahmad Fauzan',
        Nama_Level_Saat_Cycle: 'STAFF',
        Nilai_KPI: '104.50',
        Nilai_KPI_Sebelum_Cap: '125.00',
        Nilai_Kompetensi: '82.31',
        Final_Score: '101.25',
        Kode_Grade_Sistem: 'A',
        Kode_Grade_Final: 'A',
        Flag_Dikalibrasi: 'T',
        Calculation_Version: 2,
        Alasan_Tertahan: null,
    },
    kuadran: { Kode_Kuadran: 'STRIVING_STAR', Nama_Kuadran: 'Striving Star' },
    kategori: [
        {
            Kode_Kategori: 'SELF',
            Nama_Kategori: 'Diri Sendiri',
            Nilai: '82.31',
            Bobot_Terpakai: '100.00',
            Jumlah_Rater_Diharapkan: 1,
            Jumlah_Rater_Mengisi: 1,
        },
    ],
    kompetensi: [
        {
            Nama_Competency: 'Analytical Thinking',
            Nilai: '104.50',
            agregat: true,
            Kode_Kategori: null,
        },
    ],
    /* Dua kuadran bernama SAMA, dan itu disalin dari keadaan sungguhan: pada development
       #2 FUTURE_STAR_A dan #3 FUTURE_STAR_B keduanya bernama "Future Star". Fixture yang
       merapikannya menjadi dua nama berbeda akan membuat uji label lolos tanpa pernah
       menyentuh kasus yang justru membuat label itu diperlukan. */
    pilihan_kalibrasi: {
        grade: [
            { Kode_Range: 'A', Nama_Range: 'Istimewa', Nilai_Dari: '90.00', Nilai_Sampai: '999.99' },
            { Kode_Range: 'B_PLUS', Nama_Range: 'Sangat Baik', Nilai_Dari: '80.00', Nilai_Sampai: '90.00' },
            { Kode_Range: 'B', Nama_Range: 'Baik', Nilai_Dari: '70.00', Nilai_Sampai: '80.00' },
        ],
        kuadran: [
            { Id_PA_Hav_Quadrant: '2', Kode_Kuadran: 'FUTURE_STAR_A', Nama_Kuadran: 'Future Star', Nomor_Box: '2' },
            { Id_PA_Hav_Quadrant: '3', Kode_Kuadran: 'FUTURE_STAR_B', Nama_Kuadran: 'Future Star', Nomor_Box: '3' },
            { Id_PA_Hav_Quadrant: '5', Kode_Kuadran: 'TOP_PERFORMER', Nama_Kuadran: 'Top Performer', Nomor_Box: '5' },
        ],
    },
    catatan: [],
    penilai: [
        {
            Id_PA_Assignment: 4412,
            Kode_Kategori: 'SELF',
            Nama_Kategori: 'Diri Sendiri',
            Kode_Karyawan_Penilai: 'A0118',
            Status_Penugasan: 'BELUM_MULAI',
            Due_At: '2026-07-31',
            terlambat: true,
            hari_terlambat: 3,
        },
    ],
};

const kategoriCatatan = [
    { Kode_Competency: 'QL01', Nama_Competency: 'Strength' },
    { Kode_Competency: 'QL02', Nama_Competency: 'Development Area' },
    { Kode_Competency: 'QL03', Nama_Competency: 'Initiative' },
    { Kode_Competency: 'QL04', Nama_Competency: 'Recommendation' },
];

function mountDialog(props = {}) {
    return mount(PaResultDialog, {
        props: { modelValue: true, participant, result, ...props },
        global: { stubs: uiStubs },
    });
}

describe('PaResultDialog', () => {
    beforeEach(() => {
        paApiMock.simpanCatatanHasil.mockReset();
        paApiMock.simpanCatatanHasil.mockResolvedValue({ data: { ditulis: 1 } });
    });

    it('menampilkan hasil di atas 100, radar agregat, bobot kategori, dan keterlambatan server', () => {
        const wrapper = mountDialog({
            canCalculate: true,
            canAssign: true,
            calculation: {
                status: 'TERTAHAN',
                versi: 3,
                final_score: '101.25',
                alasan_tertahan: 'Kategori penilai belum memenuhi ambang.',
            },
        });

        expect(wrapper.text()).toContain('104,5');
        expect(wrapper.text()).toContain('101,25');
        expect(wrapper.text()).toContain('125');
        expect(wrapper.text()).toContain('Perhitungan selesai, hasil tertahan');
        expect(wrapper.text()).toContain('Kategori penilai belum memenuhi ambang.');
        expect(wrapper.find('.chart-stub').exists()).toBe(true);
        expect(wrapper.text()).toContain('100% bobot');
        expect(wrapper.text()).toContain('Terlambat');
        expect(wrapper.text()).toContain('3 hari terlambat');
    });

    /* DDL-2026-096. Tiga aturan radar, dan ketiganya mudah rusak tanpa suara: sumbu yang
       tidak seharusnya ada, jaring KKM yang hilang, dan ambang kosong yang berubah jadi nol. */
    it('menyaring sumbu radar dan menggambar jaring KKM terpisah', () => {
        const wrapper = mountDialog({
            result: {
                ...result,
                kompetensi: [
                    { Nama_Competency: 'Analytical Thinking', Nilai: '80.00', agregat: true, Kode_Kategori: null, Flag_Radar: 'Y', Nilai_Ambang: '75.00' },
                    { Nama_Competency: 'Problem Solving', Nilai: '60.00', agregat: true, Kode_Kategori: null, Flag_Radar: 'Y', Nilai_Ambang: null },
                    { Nama_Competency: 'Tidak Di Radar', Nilai: '90.00', agregat: true, Kode_Kategori: null, Flag_Radar: 'T', Nilai_Ambang: '70.00' },
                ],
            },
        });

        /* Radar kini dikirim ke HcisChart sebagai `categories` + `series`; gaya garis
           (putus-putus, tanpa bidang) diurus HcisChart, bukan opsi ECharts dari halaman. */
        const radar = wrapper.findAllComponents({ name: 'HcisChart' }).find((c) => c.props('type') === 'radar');
        const sumbu = radar.props('categories').map((i) => i.name);

        expect(sumbu).toEqual(['Analytical Thinking', 'Problem Solving']);
        expect(sumbu).not.toContain('Tidak Di Radar');

        const jaring = radar.props('series');

        expect(jaring).toHaveLength(2);
        expect(jaring[1].name).toBe('Ambang (KKM)');
        /* null, BUKAN 0. Ambang yang belum ditetapkan harus memutus jaringnya di sumbu itu,
           bukan menariknya ke pusat seolah standarnya nol. */
        expect(jaring[1].data).toEqual([75, null]);
    });

    it('tidak menggambar jaring KKM ketika tidak satu pun ambang ditetapkan', () => {
        /* Pasangan penolakan dari uji di atas. Tanpa ini, kode yang SELALU menambahkan seri
           kedua tetap hijau — dan layar menampilkan legenda untuk jaring yang kosong. */
        const wrapper = mountDialog({
            result: {
                ...result,
                kompetensi: [
                    { Nama_Competency: 'Analytical Thinking', Nilai: '80.00', agregat: true, Kode_Kategori: null, Flag_Radar: 'Y', Nilai_Ambang: null },
                ],
            },
        });

        const radar = wrapper.findAllComponents({ name: 'HcisChart' }).find((c) => c.props('type') === 'radar');

        expect(radar.props('series')).toHaveLength(1);
        expect(radar.props('series')[0].name).toBe('Nilai Agregat');
    });

    it('memperlakukan kompetensi tanpa Flag_Radar sebagai sumbu radar', () => {
        /* Respons lama — dan hasil siklus yang datanya belum lewat DML-2026-121 — tidak
           membawa `Flag_Radar` sama sekali. Radar harus tetap tergambar seperti sebelumnya,
           bukan mendadak kosong. Fixture dasar di berkas ini memang tidak memuat flag itu. */
        const wrapper = mountDialog({});

        const radar = wrapper.findAllComponents({ name: 'HcisChart' }).find((c) => c.props('type') === 'radar');

        expect(radar.props('categories').map((i) => i.name)).toEqual(['Analytical Thinking']);
    });

    /* Kalibrasi Forum digantikan Balancing (B7, spec 2026-09-30): tombol dan dialognya dilepas. */
    /* Tab Ringkasan & Skor: wawasan yang tidak terbaca dari radar — komposisi Final, 9-box,
       posisi kurva, tren. Kartu perilaku, rincian, dan persepsi sengaja tidak ada. */
    it('komposisi nilai akhir, 9-box, posisi kurva, dan tren dari detail server', () => {
        const wrapper = mountDialog({
            result: {
                ...result,
                peserta: {
                    ...result.peserta,
                    Nilai_KPI: '100.00', Nilai_KPI_Sebelum_Cap: '120.00', Bobot_KPI_Terpakai: '75.00',
                    Nilai_Kompetensi: '80.00', Bobot_Kompetensi_Terpakai: '25.00', Final_Score: '95.00',
                    Jumlah_KPI_Belum_Disetujui: 2, Kode_Grade_Sistem: 'B_PLUS', Kode_Grade_Final: 'A',
                },
                kuadran: { Nomor_Box: '1', Nama_Kuadran: 'Star', Tingkat_Performa: 'TINGGI', Tingkat_Kompetensi: 'TINGGI' },
                posisi_kurva: {
                    grup: { id: 1, nama: 'Grup Staf' }, terkunci: true, n: 3, peringkat: 1, persentil: 67,
                    nilai: 88.49, rata: 80, sd: 11.12, z: 0.7636, grade: 'B',
                    grade_berikut: { kode: 'B_PLUS', batas: 92.51, jarak: 4.02 }, peringatan: [],
                },
                tren: { cycle: { cycle_id: 9, kode_cycle: 'PA-2025', nama_cycle: 'PA 2025' }, final: '90.00', grade: 'B', kpi: '95.00', kompetensi: '82.00' },
            },
        });
        for (const lama of ['perilaku-kompetensi', 'rincian-kompetensi', 'wawasan-persepsi']) {
            expect(wrapper.find(`[data-uji="${lama}"]`).exists()).toBe(false);
        }
        const komposisi = wrapper.find('[data-uji="wawasan-komposisi"]');
        expect(komposisi.text()).toContain('75,00');
        expect(komposisi.find('[data-uji="komposisi-final"]').text()).toContain('95,00');
        expect(komposisi.find('[data-uji="komposisi-kpi-belum"]').text()).toContain('2 KPI belum disetujui');
        expect(komposisi.find('[data-uji="komposisi-cap"]').text()).toContain('120,00');
        expect(komposisi.find('[data-uji="komposisi-grade"]').text()).toContain('B+');

        expect(wrapper.find('[data-uji="hav-nama"]').text()).toBe('Star');
        expect(wrapper.find('.pa-wh__hav-kotak.is-aktif').text()).toBe('1');

        const kurva = wrapper.find('[data-uji="wawasan-kurva"]');
        expect(kurva.find('[data-uji="kurva-peringkat"]').text()).toContain('1 dari 3');
        expect(kurva.find('[data-uji="kurva-berikut"]').text()).toContain('4,02 poin');

        const tren = wrapper.find('[data-uji="wawasan-tren"]');
        expect(tren.find('[data-uji="tren-final"]').text()).toContain('+5,00');
        expect(tren.find('[data-uji="tren-kompetensi"]').text()).toContain('−2,00');
    });

    /* Koreksi 8 Okt 2026: nama kotak 9-box dari master N_PA_Hav_Quadrant, bukan nama keras. */
    it('nama kotak 9-box di wawasan mengikuti master, dengan pembeda untuk nama kembar', () => {
        const wrapper = mountDialog({
            result: {
                ...result,
                kuadran: { Nomor_Box: '9', Nama_Kuadran: 'Deadwood' },
                pilihan_kalibrasi: {
                    ...result.pilihan_kalibrasi,
                    kuadran: [
                        { Id_PA_Hav_Quadrant: '1', Kode_Kuadran: 'STRIVING_STAR', Nama_Kuadran: 'Striving Star', Nomor_Box: '1' },
                        ...result.pilihan_kalibrasi.kuadran,
                        { Id_PA_Hav_Quadrant: '9', Kode_Kuadran: 'DEADWOOD', Nama_Kuadran: 'Deadwood', Nomor_Box: '9' },
                    ],
                },
            },
        });
        const label = wrapper.findAll('.pa-wh__hav-cell-label').map((l) => l.text());

        expect(label).toEqual(expect.arrayContaining(['Striving Star', 'Future Star A', 'Future Star B', 'Deadwood', 'Top Performer']));
        expect(label).not.toContain('Star');
        expect(label).not.toContain('Under Perf.');
        expect(wrapper.find('[data-uji="hav-nama"]').text()).toBe('Deadwood');
    });

    it('mode verifikasi menyembunyikan posisi kurva dan grade di wawasan', () => {
        const wrapper = mountDialog({
            modeVerifikasi: true,
            result: { ...result, posisi_kurva: { grup: { nama: 'X' }, n: 1, peringkat: 1, z: 0, grade: 'B', grade_berikut: null, peringatan: [] }, tren: null },
        });
        expect(wrapper.find('[data-uji="wawasan-kurva"]').exists()).toBe(false);
        expect(wrapper.find('[data-uji="komposisi-grade"]').exists()).toBe(false);
        expect(wrapper.find('[data-uji="wawasan-tren"]').text()).toContain('Belum ada hasil siklus sebelumnya');
    });

    it('tidak ada tombol Kalibrasi Forum lagi', () => {
        const wrapper = mountDialog({ canCalibrate: true });

        expect(wrapper.findAll('button').find((b) => b.text() === 'Kalibrasi')).toBeUndefined();
        expect(wrapper.find('#alasan-kalibrasi-pa').exists()).toBe(false);
    });

    it('tab Balancing memuat riwayat sesi dan jejak peserta', async () => {
        paApiMock.riwayatBalancing.mockResolvedValue({
            sesi: [{ id: 3, nama: 'Sesi Staff', status: 'FINAL', status_tinjau: 'DIUBAH', final_at: '2026-09-30', kompetensi: [{ nama: 'Integritas', sebelum: 70, sesudah: 80, alasan: 'Konsisten' }] }],
            jejak: [{ Nomor_Hitung_Ulang: 1, Final_Score_Sebelum: 83.75, Final_Score_Sesudah: 86.25, Kode_Grade_Sebelum: 'B', Kode_Grade_Sesudah: 'B_PLUS', Sebab: 'BALANCING' }],
        });
        const wrapper = mountDialog();

        await wrapper.findAll('button').find((b) => b.text().includes('Balancing')).trigger('click');
        await flushPromises();

        expect(paApiMock.riwayatBalancing).toHaveBeenCalled();
        expect(wrapper.text()).toContain('Sesi Staff');
        expect(wrapper.text()).toContain('Integritas');
        // Grade ditampilkan dengan label (B+), bukan kode mentah B_PLUS.
        expect(wrapper.text()).toContain('B+');
        expect(wrapper.text()).not.toContain('B_PLUS');
    });

    it('tab Balancing memuat ulang saat peserta berganti', async () => {
        paApiMock.riwayatBalancing.mockReset();
        paApiMock.riwayatBalancing
            .mockResolvedValueOnce({ sesi: [{ id: 1, nama: 'Sesi Milik A', status: 'FINAL', kompetensi: [] }], jejak: [] })
            .mockResolvedValueOnce({ sesi: [{ id: 2, nama: 'Sesi Milik B', status: 'FINAL', kompetensi: [] }], jejak: [] });
        const wrapper = mountDialog();

        await wrapper.findAll('button').find((b) => b.text().includes('Balancing')).trigger('click');
        await flushPromises();
        await wrapper.setProps({ participant: { ...participant, Id_PA_Participant: 999 } });
        await flushPromises();

        expect(paApiMock.riwayatBalancing).toHaveBeenLastCalledWith(999);
        expect(wrapper.text()).toContain('Sesi Milik B');
        expect(wrapper.text()).not.toContain('Sesi Milik A');
    });

    it('membedakan akses hasil 403 dan mempertahankan metadata request', () => {
        const wrapper = mountDialog({
            result: null,
            error: 'Anda tidak memiliki akses hasil.',
            errorStatus: 403,
            errorCode: 'PA_RESULT_FORBIDDEN',
            requestId: 'req-result-1',
        });

        expect(wrapper.text()).toContain('Akses hasil tidak tersedia');
        expect(wrapper.text()).toContain('Anda tidak memiliki akses hasil.');
        expect(wrapper.text()).toContain('PA_RESULT_FORBIDDEN');
        expect(wrapper.text()).toContain('req-result-1');
        expect(wrapper.text()).not.toContain('Detail hasil belum tersedia');
    });

    /* Dua penilai pada satu kategori: pembatalan salah satunya SAH, karena yang tersisa
       masih memenuhi ambang. Fixture dasar hanya punya satu penilai, dan sejak pembatalan
       yang mengosongkan kategori ditahan, fixture itu tidak lagi bisa menguji jalur normal. */
    const duaPenilai = {
        ...result,
        aturan_penilai: { SELF: { bobot_persen: 100, min_rater: 1, min_rater_selesai: 1 } },
        penilai: [
            result.penilai[0],
            {
                ...result.penilai[0],
                Id_PA_Assignment: 4413,
                Kode_Karyawan_Penilai: 'A0119',
                Nama_Penilai_Saat_Cycle: 'Rekan Kedua',
                Bobot_Dalam_Kategori: '50.00',
            },
        ],
    };

    it('meneruskan aksi pembatalan dan perhitungan ke page', async () => {
        const wrapper = mountDialog({ canCalculate: true, canAssign: true, result: duaPenilai });

        // Hasil fixture sudah bernilai, jadi tombolnya berlabel "Hitung Ulang".
        const calculateButton = wrapper.findAll('button').find((button) => button.text() === 'Hitung Ulang');
        await calculateButton.trigger('click');
        expect(wrapper.emitted('calculate')).toHaveLength(1);

        const cancelButton = wrapper.findAll('button').find((button) => button.text() === 'Batalkan');
        await cancelButton.trigger('click');
        await wrapper.find('#alasan-batal-pa').setValue('Penilai pindah divisi');
        const submitCancelButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Batalkan penugasan');
        await submitCancelButton.trigger('click');

        expect(wrapper.emitted('cancel-assignment')).toEqual([
            [{ assignment: duaPenilai.penilai[0], alasan: 'Penilai pindah divisi' }],
        ]);
    });

    /*
     | PEMBATALAN YANG MENGOSONGKAN KATEGORI DITAHAN, BUKAN SEKADAR DIPERINGATKAN.
     |
     | Membatalkan penilai terakhir sebuah kategori membuat kategori itu dikeluarkan dari
     | perhitungan dan porsinya berpindah ke kategori lain — perubahan besar yang tidak bisa
     | dibatalkan lewat layar mana pun, dan hampir selalu bukan yang dimaksud admin: yang ia
     | maksud biasanya MENGGANTI orangnya.
     */
    it('menahan pembatalan yang mengosongkan kategori dan menyebut jalan keluarnya', async () => {
        const wrapper = mountDialog({ canAssign: true });

        await wrapper.findAll('button').find((b) => b.text() === 'Batalkan').trigger('click');
        await wrapper.find('#alasan-batal-pa').setValue('Salah pilih penilai');

        const kirim = wrapper.findAll('button').find((b) => b.text() === 'Batalkan penugasan');

        expect(kirim.attributes('disabled')).toBeDefined();
        expect(wrapper.text()).toContain('penilai terakhir');
        expect(wrapper.text()).toContain('Tambahkan penilai penggantinya lebih dulu');

        await kirim.trigger('click');
        expect(wrapper.emitted('cancel-assignment')).toBeUndefined();
    });

    /*
     | Angka pembagiannya harus SAMA PERSIS dengan yang dihitung server: tiap orang
     | dibulatkan dua desimal dan selisihnya ditanggung yang pertama. Tiga penilai menjadi
     | 33,34 / 33,33 / 33,33 — bukan tiga kali 33,33 yang berjumlah 99,99.
     */
    it('menampilkan pembagian bobot sesudah pembatalan, dengan pembulatan server', async () => {
        const tigaPenilai = {
            ...result,
            aturan_penilai: { SELF: { bobot_persen: 100, min_rater: 1, min_rater_selesai: 1 } },
            penilai: [1, 2, 3].map((n) => ({
                ...result.penilai[0],
                Id_PA_Assignment: 4500 + n,
                Kode_Karyawan_Penilai: `A05${n}`,
                Nama_Penilai_Saat_Cycle: `Penilai ${n}`,
                Bobot_Dalam_Kategori: '33.33',
            })),
        };

        const wrapper = mountDialog({ canAssign: true, result: tigaPenilai });

        await wrapper.findAll('button').find((b) => b.text() === 'Batalkan').trigger('click');

        expect(wrapper.text()).toContain('2 penilai tersisa');
        expect(wrapper.text()).toContain('50%');
    });

    /*
     | PERTANYAAN YANG BELUM DIJAWAB TETAP DIGAMBAR.
     |
     | Panel ini pernah digambar dari daftar JAWABAN, sehingga pertanyaan yang diajukan
     | tetapi belum dijawab siapa pun tidak muncul sama sekali - dan admin tidak bisa
     | membedakan "tidak ditanyakan" dari "belum dijawab", dua keadaan yang menuntut
     | tindakan berbeda: yang satu paket soalnya salah, yang satu penilainya perlu ditagih.
     */
    it('menggambar pertanyaan kualitatif termasuk yang belum dijawab', () => {
        const wrapper = mountDialog({
            result: {
                ...result,
                pertanyaan_kualitatif: [
                    {
                        Kode_Soal: 'QL01-01-ATASAN',
                        Kode_Competency: 'QL01',
                        Nama_Competency: 'Strength',
                        Kode_Kategori: 'ATASAN',
                        Nama_Kategori: 'Atasan',
                        diminta: 1,
                        terjawab: 1,
                    },
                    {
                        Kode_Soal: 'QL03-01-PEER',
                        Kode_Competency: 'QL03',
                        Nama_Competency: 'Initiative',
                        Kode_Kategori: 'PEER',
                        Nama_Kategori: 'Rekan Kerja',
                        diminta: 2,
                        terjawab: 0,
                    },
                ],
                jawaban_naratif: [
                    {
                        Kode_Soal_Sumber: 'QL01-01-ATASAN',
                        Nama_Competency: 'Strength',
                        Kode_Kategori: 'ATASAN',
                        Nama_Kategori: 'Atasan',
                        Nama_Penilai_Saat_Cycle: 'Budi',
                        Jawaban_Teks: 'Konsisten menutup pekerjaan tepat waktu.',
                    },
                ],
            },
        });

        expect(wrapper.text()).toContain('Initiative');
        expect(wrapper.text()).toContain('QL03-01-PEER');
        expect(wrapper.text()).toContain('Belum dijawab');
        expect(wrapper.text()).toContain('1 dari 3 terjawab');
        expect(wrapper.text()).toContain('Konsisten menutup pekerjaan tepat waktu.');
    });

    /* Balasan lama tidak membawa `pertanyaan_kualitatif`. Panelnya tidak boleh mendadak
       kosong: jawabannya tetap digambar, hanya tanpa pertanyaan yang belum terjawab. */
    it('tetap menggambar jawaban ketika server belum mengirim daftar pertanyaan', () => {
        const wrapper = mountDialog({
            result: {
                ...result,
                jawaban_naratif: [
                    {
                        Kode_Soal_Sumber: 'QL02-01-SELF',
                        Nama_Competency: 'Development Area',
                        Kode_Kategori: 'SELF',
                        Nama_Kategori: 'Diri Sendiri',
                        Nama_Penilai_Saat_Cycle: 'Ahmad Fauzan',
                        Jawaban_Teks: 'Perlu lebih banyak mendelegasikan.',
                    },
                ],
            },
        });

        expect(wrapper.text()).toContain('Development Area');
        expect(wrapper.text()).toContain('Perlu lebih banyak mendelegasikan.');
    });

    /*
     | LEVEL TANPA SOAL KUALITATIF TIDAK MENDAPAT PANEL CATATAN SAMA SEKALI.
     |
     | Versi pertama menarik kategorinya dari MASTER, sehingga empat kotak isian muncul juga
     | pada peserta yang penilainya tidak pernah ditanyai satu pun soal kualitatif. Kotak yang
     | tidak punya bahan bukan keseragaman, melainkan pekerjaan yang tampak menggantung.
     */
    it('menyembunyikan panel catatan admin ketika tidak ada dimensi kualitatif', () => {
        const wrapper = mountDialog({ result: { ...result, kategori_catatan: [] } });

        expect(wrapper.text()).not.toContain('CATATAN ADMIN');
        expect(wrapper.find('.pa-notes--admin').exists()).toBe(false);
    });

    /*
     | KOTAK CATATAN ADMIN LAHIR DARI DATA, BUKAN DARI KODE YANG DIPAKU.
     |
     | Versi pertama menuliskan dua kode tetap (STRENGTH, DEVELOPMENT_AREA) di komponen,
     | sementara masternya punya empat dimensi kualitatif. Akibatnya dua dimensi tidak punya
     | kotak isian sama sekali - dan tidak ada satu pun galat yang menandainya, hanya panel
     | yang tidak ada. Uji ini memakai EMPAT kategori supaya versi yang dipaku gagal.
     */
    it('membuat satu kotak catatan per kategori yang dikirim server', () => {
        const wrapper = mountDialog({ result: { ...result, kategori_catatan: kategoriCatatan } });

        expect(wrapper.find('#pa-catatan-QL01').exists()).toBe(true);
        expect(wrapper.find('#pa-catatan-QL02').exists()).toBe(true);
        expect(wrapper.find('#pa-catatan-QL03').exists()).toBe(true);
        expect(wrapper.find('#pa-catatan-QL04').exists()).toBe(true);
        expect(wrapper.text()).toContain('Recommendation');
    });

    /*
     | Kotaknya diisi HANYA dari catatan yang ditulis admin. Memasukkan catatan penilai ke
     | sini berarti admin yang menekan simpan tanpa mengubah apa pun diam-diam MENYALIN
     | observasi orang lain menjadi tulisannya sendiri.
     */
    it('mengisi kotak dari catatan admin saja, bukan catatan penilai', () => {
        const wrapper = mountDialog({
            result: {
                ...result,
                kategori_catatan: kategoriCatatan,
                catatan: [
                    { Kode_Type: 'QL03', Nama_Type: 'Initiative', Isi: 'Ditulis admin', dari_penilai: false },
                    { Kode_Type: 'QL03', Nama_Type: 'Initiative', Isi: 'Kalimat penilai', dari_penilai: true },
                ],
            },
        });

        const richTextQL03 = wrapper
            .findAllComponents({ name: 'HcisRichText' })
            .find((c) => c.props('id') === 'pa-catatan-QL03');
        expect(richTextQL03?.props('modelValue')).toBe('Ditulis admin');
        expect(richTextQL03?.props('modelValue')).not.toContain('Kalimat penilai');
    });

    it('mengirim seluruh kategori saat disimpan, termasuk yang dikosongkan', async () => {
        const wrapper = mountDialog({ result: { ...result, kategori_catatan: kategoriCatatan } });

        const richTextQL04 = wrapper
            .findAllComponents({ name: 'HcisRichText' })
            .find((c) => c.props('id') === 'pa-catatan-QL04');
        await richTextQL04?.vm.$emit('update:modelValue', 'Dipertahankan di posisi sekarang');

        const saveButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Simpan ke Report Peserta');
        await saveButton.trigger('click');
        await flushPromises();

        // Kategori yang dikosongkan HARUS ikut terkirim: itulah cara admin menghapus
        // catatan yang pernah ia tulis. Mengirim yang terisi saja membuat kalimat lama
        // bertahan di report meski kotaknya sudah dikosongkan di layar.
        expect(paApiMock.simpanCatatanHasil).toHaveBeenCalledWith(12, {
            QL01: [''],
            QL02: [''],
            QL03: [''],
            QL04: ['Dipertahankan di posisi sekarang'],
        });
    });

    it('meminta konfirmasi sebelum menerbitkan hasil satu peserta', async () => {
        const wrapper = mountDialog({
            canPublish: true,
            result: { ...result, peserta: { ...result.peserta, Status_Hasil: 'DIHITUNG' } },
        });

        const publishButton = wrapper.findAll('button').find((button) => button.text() === 'Terbitkan Hasil');
        await publishButton.trigger('click');
        expect(wrapper.text()).toContain('Hasil ini akan terlihat oleh karyawan setelah diterbitkan.');
        expect(wrapper.text()).toContain('Final score');

        const confirmButton = wrapper.findAll('button').find((button) => button.text() === 'Ya, terbitkan hasil');
        await confirmButton.trigger('click');

        expect(wrapper.emitted('publish')).toHaveLength(1);
    });

    /* Koreksi 8 Okt 2026: tombol yang pasti ditolak server tidak ditawarkan. */
    const tombol = (wrapper, teks) => wrapper.findAll('button').find((b) => b.text() === teks);

    it('hasil TERBIT: tanpa Hitung Ulang dan Terbitkan Ulang, sebabnya ditampilkan', () => {
        const wrapper = mountDialog({
            canCalculate: true,
            canPublish: true,
            result: { ...result, peserta: { ...result.peserta, Status_Hasil: 'TERBIT', Published_At: '2026-10-01 08:00:00' } },
        });

        expect(tombol(wrapper, 'Hitung Ulang')).toBeUndefined();
        expect(tombol(wrapper, 'Terbitkan Ulang')).toBeUndefined();
        expect(wrapper.find('[data-uji="alasan-tak-hitung"]').text()).toContain('sudah terbit');
    });

    it('hasil yang dirilis ke leader tidak bisa dihitung ulang, tetapi tetap bisa diterbitkan', () => {
        const wrapper = mountDialog({
            canCalculate: true,
            canPublish: true,
            result: { ...result, peserta: { ...result.peserta, Status_Hasil: 'DIHITUNG', Published_Leader_At: '2026-10-02 09:00:00' } },
        });

        expect(tombol(wrapper, 'Hitung Ulang')).toBeUndefined();
        expect(wrapper.find('[data-uji="alasan-tak-hitung"]').text()).toContain('Cabut rilis leader');
        expect(tombol(wrapper, 'Terbitkan Hasil')).toBeDefined();
    });

    it('Terbitkan hanya untuk status DIHITUNG', () => {
        const tertahan = mountDialog({
            canPublish: true,
            result: { ...result, peserta: { ...result.peserta, Status_Hasil: 'TERTAHAN', Final_Score: null } },
        });
        expect(tombol(tertahan, 'Terbitkan Hasil')).toBeUndefined();

        const dihitung = mountDialog({
            canCalculate: true,
            canPublish: true,
            result: { ...result, peserta: { ...result.peserta, Status_Hasil: 'DIHITUNG' } },
        });
        expect(tombol(dihitung, 'Terbitkan Hasil')).toBeDefined();
        expect(tombol(dihitung, 'Hitung Ulang')).toBeDefined();
        expect(dihitung.find('[data-uji="alasan-tak-hitung"]').exists()).toBe(false);
    });
});
