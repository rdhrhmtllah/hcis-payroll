import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PenyiapanPage from './penyiapan.vue';
import paApi from '../api/paApi.js';

const uiStubs = {
    HcisHeader: {
        template: '<div class="hcis-header"><h1>{{ title }}</h1><p>{{ subtitle }}</p><slot name="actions" /><slot name="console" /></div>',
        props: ['title', 'kicker', 'subtitle'],
    },
    HcisButton: {
        template: '<button :disabled="disabled" @click="$emit(\'click\', $event)"><slot />{{ label }}</button>',
        props: ['label', 'disabled', 'loading', 'variant'],
    },
    HcisBadge: {
        template: '<span class="hcis-badge">{{ label }}</span>',
        props: ['label', 'status', 'variant'],
    },
    HcisAlert: {
        template: '<div class="hcis-alert"><h4>{{ title }}</h4><slot /></div>',
        props: ['title', 'variant'],
    },
    HcisResultState: {
        template: '<div class="hcis-result-state">{{ title }} - {{ description }}</div>',
        props: ['title', 'description'],
    },
    HcisSteps: {
        template: '<div class="hcis-steps"><div v-for="step in steps" :key="step.kode" class="hcis-step" @click="$emit(\'change\', { step })">{{ step.label }}</div></div>',
        props: ['steps', 'current'],
    },
    HcisIcon: {
        template: '<i :class="name" />',
        props: ['name'],
    },
    PaStepBobotPilar: {
        name: 'PaStepBobotPilar',
        template: '<div class="pa-step-bobot"><button class="btn-lanjut" @click="$emit(\'lanjut\')">Lanjut</button></div>',
        props: ['cycleId', 'dapatDisunting'],
    },
    PaStepPaketSoal: {
        name: 'PaStepPaketSoal',
        template: '<div class="pa-step-paket"><button class="btn-lanjut" @click="$emit(\'lanjut\')">Lanjut</button></div>',
        props: ['cycleId', 'dapatDisunting'],
    },
    PaStepAturanPenilai: {
        name: 'PaStepAturanPenilai',
        template: '<div class="pa-step-aturan"><button class="btn-lanjut" @click="$emit(\'lanjut\')">Lanjut</button></div>',
        props: ['cycleId', 'dapatDisunting'],
    },
    PaStepPeserta: {
        name: 'PaStepPeserta',
        template: '<div class="pa-step-peserta"><button class="btn-selesai" @click="$emit(\'selesai\')">Selesai</button></div>',
        props: ['cycleId', 'dapatDisunting', 'siapDijalankan'],
    },
};

const mockKeadaanSetup = {
    cycle: {
        id: 2585,
        kode: 'PA-2026',
        nama: 'Performance Appraisal 2026',
        tahun: 2026,
        status: 'DRAFT',
    },
    /* `dapat_dibuka` ikut dikirim server sejak urutan langkah ditegakkan. Tanpa medan itu
       di sini, uji ini memeriksa halaman terhadap bentuk balasan yang sudah tidak ada. */
    langkah: [
        { kode: 'BOBOT_PILAR', nama: 'Bobot Penilaian', status: 'SELESAI', boleh_disunting: true, dapat_dibuka: true, ringkas: 'KPI vs Kompetensi' },
        { kode: 'PAKET_SOAL', nama: 'Pengaturan KPI', status: 'DRAFT', boleh_disunting: true, dapat_dibuka: true, ringkas: 'Paket soal' },
        { kode: 'ATURAN_PENILAI', nama: 'Aturan Penilai', status: 'BELUM', boleh_disunting: true, dapat_dibuka: true, ringkas: '360 multi-rater' },
        { kode: 'PESERTA', nama: 'Peserta', status: 'BELUM', boleh_disunting: true, dapat_dibuka: true, ringkas: 'Peserta & eksekusi' },
        { kode: 'PENUGASAN', nama: 'Penugasan Penilai', status: 'BELUM', boleh_disunting: true, dapat_dibuka: true, ringkas: 'Tinjau per orang' },
    ],
    langkah_aktif: 'BOBOT_PILAR',
    selesai: 1,
    total: 5,
    siap_dijalankan: false,
    penghalang: ['Level Staff belum memiliki paket soal'],
};

describe('PerformanceAppraisalPenyiapanPage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(paApi, 'keadaanSetup').mockResolvedValue({ result: mockKeadaanSetup });
        vi.spyOn(paApi, 'pindahLangkahSetup').mockResolvedValue({
            result: { ...mockKeadaanSetup, langkah_aktif: 'PAKET_SOAL' },
        });
    });

    it('renders header, steppers, and blockers alert correctly', async () => {
        const wrapper = mount(PenyiapanPage, {
            props: { cycleId: 2585 },
            global: { stubs: uiStubs },
        });

        await flushPromises();

        expect(paApi.keadaanSetup).toHaveBeenCalledWith(2585);
        expect(wrapper.text()).toContain('PA-2026');
        expect(wrapper.text()).toContain('Performance Appraisal 2026');
        expect(wrapper.text()).toContain('1 / 5 Steps Completed');
        expect(wrapper.text()).toContain('Level Staff belum memiliki paket soal');
        expect(wrapper.findComponent({ name: 'PaStepBobotPilar' }).exists()).toBe(true);
    });

    it('switches between wizard steps when stepper or next button is triggered', async () => {
        const wrapper = mount(PenyiapanPage, {
            props: { cycleId: 2585 },
            global: { stubs: uiStubs },
        });

        await flushPromises();

        const lanjutButton = wrapper.find('.pa-step-bobot .btn-lanjut');
        expect(lanjutButton.exists()).toBe(true);

        await lanjutButton.trigger('click');
        await flushPromises();

        expect(paApi.pindahLangkahSetup).toHaveBeenCalledWith(2585, 'PAKET_SOAL');
    });

    it('pindah sendiri ke langkah 5 sesudah penyiapan dijalankan', async () => {
        const wrapper = mount(PenyiapanPage, {
            props: { cycleId: 2585 },
            global: { stubs: uiStubs },
        });

        await flushPromises();

        // Halaman harus berdiri di langkah 4 lebih dulu; kalau tidak, kepindahannya
        // membuktikan sesuatu yang lain.
        await wrapper.find('.hcis-steps .hcis-step:nth-child(4)').trigger('click');
        await flushPromises();

        paApi.pindahLangkahSetup.mockClear();

        await wrapper.find('.pa-step-peserta .btn-selesai').trigger('click');
        await flushPromises();

        /* Sesudah penyiapan berjalan, langkah 4 tidak menyisakan pekerjaan apa pun —
           membiarkan admin berdiri di layar yang sudah selesai membuatnya menebak apakah
           masih ada yang harus ditekan di sana. */
        expect(paApi.pindahLangkahSetup).toHaveBeenCalledWith(2585, 'PENUGASAN');

        /* Dan keadaannya DIMUAT ULANG lebih dulu. Penjaga urutan di server menolak
           perpindahan ke langkah yang pendahulunya belum SELESAI, sementara status PESERTA
           baru berubah pada kiriman berikutnya — memindah tanpa memuat ulang berarti
           ditolak penjaga yang justru sedang bekerja dengan benar. */
        expect(paApi.keadaanSetup.mock.invocationCallOrder[paApi.keadaanSetup.mock.invocationCallOrder.length - 1])
            .toBeLessThan(paApi.pindahLangkahSetup.mock.invocationCallOrder[0]);
    });
});
