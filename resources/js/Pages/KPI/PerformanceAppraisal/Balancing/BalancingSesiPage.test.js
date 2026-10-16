import { flushPromises, mount } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Page from './BalancingSesiPage.vue';
import paApi from '../api/paApi.js';

vi.mock('../api/paApi.js', () => ({
    default: {
        detailSesiBalancing: vi.fn(), simpanSesiBalancing: vi.fn(), finalkanSesiBalancing: vi.fn(),
        batalkanSesiBalancing: vi.fn(), simulasiBalancing: vi.fn(), havMap: vi.fn(),
    },
}));
vi.mock('@inertiajs/vue3', () => ({ router: { visit: vi.fn(), on: vi.fn(() => () => {}) }, Link: { template: '<a><slot /></a>' } }));

const stubs = {
    HcisHeader: { props: ['title'], template: '<header><h1>{{ title }}</h1><slot name="badge" /><slot name="actions" /></header>' },
    HcisButton: { props: ['label', 'disabled', 'loading'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>' },
    HcisBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
    HcisAlert: { props: ['title', 'description'], template: '<div class="alert">{{ title }} {{ description }}<slot /></div>' },
    HcisResultState: { props: ['title', 'description'], template: '<div class="result">{{ title }} {{ description }}</div>' },
    HcisSkeleton: { template: '<div />' },
    HcisIcon: { template: '<i />' },
    HcisRichText: { props: ['modelValue'], template: '<div class="rt" />' },
    HcisDialog: { props: ['modelValue', 'title'], template: '<div v-if="modelValue" class="dialog"><slot /><slot name="footer" /></div>' },
    HcisTextarea: { props: ['modelValue'], emits: ['update:modelValue'], template: '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
    PaBalancingSimulasiPanel: { props: ['hasil', 'basi'], template: '<div class="panel-sim">{{ hasil ? "ada" : "" }}{{ basi ? " basi" : "" }}</div>' },
};

const detail = (status = 'DRAFT') => ({
    id: 3, cycle_id: 5, nama: 'Sesi Staff', status, versi_data: 4, catatan_rapat: null, pic_nama: 'Ridho', forum: [],
    peserta: [{
        participant_id: 7, nama: 'Andi', kode_karyawan: 'K7', level: 'Staff', status_tinjau: 'BELUM', alasan: null,
        sebelum: { final: 83.75, nilai_kompetensi: 77.5, nilai_kpi: 90, grade: 'B' },
        kompetensi: [{ competency_id: 1, nama: 'Integritas', bobot: 0.5, sebelum: 70, sesudah: 70, alasan: null }],
        kualitatif: [],
    }],
});

async function pasang(status = 'DRAFT') {
    paApi.detailSesiBalancing.mockResolvedValue(detail(status));
    paApi.havMap.mockResolvedValue({ kuadran: [] });
    const w = mount(Page, { props: { sesiId: 3 }, global: { stubs } });
    await flushPromises();
    return w;
}

const tombol = (w, teks) => w.findAll('button').find((b) => b.text().includes(teks));

describe('BalancingSesiPage', () => {
    beforeEach(() => vi.clearAllMocks());

    it('mengirim isian layar yang belum disimpan ke simulasi', async () => {
        paApi.simulasiBalancing.mockResolvedValue({ metode: 'PITA', peserta: [], terdampak: [] });
        const w = await pasang();
        await tombol(w, 'Andi').trigger('click');
        await w.find('[data-uji="after-1"]').setValue('90');
        await tombol(w, 'Simulasikan').trigger('click');
        await flushPromises();
        expect(paApi.simulasiBalancing).toHaveBeenCalledWith(3, [{ participant_id: 7, kompetensi: [{ competency_id: 1, nilai_sesudah: 90 }] }]);
        expect(w.find('.panel-sim').text()).toBe('ada');
    });

    it('simpan ditahan bila ada alasan kosong', async () => {
        const w = await pasang();
        await tombol(w, 'Andi').trigger('click');
        await w.find('[data-uji="after-1"]').setValue('90');
        expect(tombol(w, 'Simpan draft').attributes('disabled')).toBeDefined();
    });

    it('409 menampilkan pesan muat ulang tanpa membuang isian', async () => {
        paApi.simpanSesiBalancing.mockRejectedValue(Object.assign(new Error('Data sesi sudah diubah orang lain.'), { status: 409, error_code: 'PA_BAL_VERSI_BERUBAH' }));
        const w = await pasang();
        await tombol(w, 'Andi').trigger('click');
        await w.find('[data-uji="after-1"]').setValue('90');
        // Alasan dicatat per orang: tombol alasan di kepala kolom membuka kotak alasan.
        await w.find('[data-alasan="7"]').trigger('click');
        await flushPromises();
        const kotak = document.body.querySelector('.pa-bal-mx-pop__input');
        kotak.value = 'Konsisten';
        kotak.dispatchEvent(new Event('input'));
        await flushPromises();
        await tombol(w, 'Simpan draft').trigger('click');
        await flushPromises();
        expect(w.text()).toContain('Data sesi sudah diubah orang lain.');
        expect(tombol(w, 'Muat ulang')).toBeTruthy();
        expect(w.find('[data-uji="after-1"]').element.value).toBe('90');
    });

    it('sesi FINAL baca-saja', async () => {
        const w = await pasang('FINAL');
        await tombol(w, 'Andi').trigger('click');
        expect(w.find('[data-uji="after-1"]').exists()).toBe(false);
        expect(tombol(w, 'Simpan draft')).toBeUndefined();
        expect(tombol(w, 'Finalkan')).toBeUndefined();
    });

    it('Kembali dengan isian belum tersimpan meminta konfirmasi', async () => {
        const w = await pasang();
        await tombol(w, 'Andi').trigger('click');
        await w.find('[data-uji="after-1"]').setValue('90');
        const konfirmasi = vi.spyOn(window, 'confirm').mockReturnValue(false);
        await tombol(w, 'Kembali').trigger('click');
        expect(konfirmasi).toHaveBeenCalled();
        expect(router.visit).not.toHaveBeenCalled();
        konfirmasi.mockRestore();
    });

    it('simulasi ditandai basi bila isian berubah sesudahnya', async () => {
        paApi.simulasiBalancing.mockResolvedValue({ metode: 'PITA', peserta: [], terdampak: [] });
        const w = await pasang();
        await tombol(w, 'Andi').trigger('click');
        await tombol(w, 'Simulasikan').trigger('click');
        await flushPromises();
        expect(w.find('.panel-sim').text()).toBe('ada');
        await w.find('[data-uji="after-1"]').setValue('75');
        expect(w.find('.panel-sim').text()).toBe('ada basi');
    });
});
