import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Page from './BalancingPage.vue';
import paApi from '../api/paApi.js';

vi.mock('../api/paApi.js', () => ({ default: { daftarSiklus: vi.fn(), daftarSesiBalancing: vi.fn(), hitungUlangBalancing: vi.fn() } }));
vi.mock('@inertiajs/vue3', () => ({ router: { visit: vi.fn() } }));

const stubs = {
    HcisHeader: { props: ['title'], template: '<header><h1>{{ title }}</h1><slot name="actions" /></header>' },
    HcisButton: { props: ['label', 'disabled', 'loading'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>' },
    HcisBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
    HcisAlert: { props: ['title', 'description'], template: '<div class="alert">{{ title }} {{ description }}<slot /></div>' },
    HcisEmptyState: { props: ['title'], template: '<div class="empty">{{ title }}</div>' },
    HcisResultState: { props: ['title', 'description'], template: '<div>{{ title }} {{ description }}</div>' },
    HcisSkeleton: { template: '<div />' },
    HcisDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /><slot name="footer" /></div>' },
    PaBalancingBuatSesiDialog: { template: '<div />' },
};

describe('BalancingPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        paApi.daftarSiklus.mockResolvedValue({ siklus: [{ Id_PA_Cycle: 5, Kode_Cycle: 'PA-1', Nama_Cycle: 'PA 1', Status_Cycle: 'CLOSED' }] });
        paApi.daftarSesiBalancing.mockResolvedValue({
            sesi: [{ id: 3, nama: 'Sesi Staff', status: 'FINAL', pic_nama: 'Ridho', jumlah_peserta: 4, jumlah_forum: 2, created_at: '2026-09-30', hitung_ulang_at: null }],
            menunggu_hitung_ulang: 1, terbuka: 0,
        });
    });

    it('tombol buat sesi mati beserta sebabnya bila server melarang (koreksi 7 Okt 2026)', async () => {
        paApi.daftarSesiBalancing.mockResolvedValue({
            sesi: [], menunggu_hitung_ulang: 0, terbuka: 0,
            boleh_buat: false, alasan_tidak_boleh_buat: 'Kunci kurva grade siklus ini lebih dulu.',
        });
        const w = mount(Page, { global: { stubs } });
        await flushPromises();
        const tombol = w.findAll('button').filter((b) => /Buat sesi/i.test(b.text()));
        expect(tombol.length).toBeGreaterThan(0);
        tombol.forEach((b) => expect(b.attributes('disabled')).toBeDefined());
    });

    it('hasil hitung ulang menyebut kesimpulan kualitatif yang tidak ditimpa', async () => {
        paApi.hitungUlangBalancing.mockResolvedValue({
            nomor: 2, dihitung: 4, berubah: 1, kalibrasi_dilepas: 0,
            kualitatif_dilewati: [{ participant_id: 9, nama: 'Dimas', kompetensi: 'Strength' }],
        });
        const w = mount(Page, { global: { stubs } });
        await flushPromises();
        await w.findAll('button').find((b) => b.text().includes('Hitung ulang') || b.text().includes('Hitung Ulang')).trigger('click');
        await flushPromises();
        const ya = w.findAll('button').filter((b) => /Hitung ulang|Ya/i.test(b.text())).pop();
        await ya.trigger('click');
        await flushPromises();
        expect(w.text()).toContain('Dimas (Strength)');
    });

    it('tab "Siap Balancing" berisi siklus CLOSED, satu-satunya yang boleh di-Balancing (koreksi 8 Okt 2026)', async () => {
        paApi.daftarSiklus.mockResolvedValue({ siklus: [
            { Id_PA_Cycle: 5, Kode_Cycle: 'PA-1', Nama_Cycle: 'PA 1', Status_Cycle: 'CLOSED' },
            { Id_PA_Cycle: 6, Kode_Cycle: 'PA-2', Nama_Cycle: 'PA 2', Status_Cycle: 'PUBLISHED' },
            { Id_PA_Cycle: 7, Kode_Cycle: 'PA-3', Nama_Cycle: 'PA 3', Status_Cycle: 'LOCKED' },
        ] });
        const w = mount(Page, { global: { stubs } });
        await flushPromises();

        w.vm.tabStatusSiklus = 'READY';
        expect(w.vm.filteredSiklusList.map((c) => c.Id_PA_Cycle)).toEqual([5]);
        w.vm.tabStatusSiklus = 'OTHER';
        expect(w.vm.filteredSiklusList.map((c) => c.Id_PA_Cycle)).toEqual([6, 7]);
    });

    it('menampilkan sesi dan banner menunggu hitung ulang', async () => {
        const w = mount(Page, { global: { stubs } });
        await flushPromises();
        expect(paApi.daftarSesiBalancing).toHaveBeenCalledWith(5);
        expect(w.text()).toContain('Sesi Staff');
        expect(w.text()).toContain('1 sesi FINAL belum dihitung ulang');
    });

    it('hitung ulang lewat konfirmasi lalu memuat ulang daftar', async () => {
        paApi.hitungUlangBalancing.mockResolvedValue({ nomor: 1, dihitung: 4, berubah: 3, kalibrasi_dilepas: 0 });
        const w = mount(Page, { global: { stubs } });
        await flushPromises();
        await w.findAll('button').find((b) => b.text() === 'Hitung ulang').trigger('click');
        await w.findAll('button').find((b) => b.text() === 'Ya, hitung ulang').trigger('click');
        await flushPromises();
        expect(paApi.hitungUlangBalancing).toHaveBeenCalledWith(5);
        expect(paApi.daftarSesiBalancing).toHaveBeenCalledTimes(2);
        expect(w.text()).toContain('Hitung ulang #1 selesai');
    });
});
