import { describe, it, expect, vi, beforeEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';

vi.mock('axios', () => ({
    default: { get: vi.fn(() => Promise.resolve({ data: { success: true, result: {} } })) },
}));
vi.mock('element-plus', () => ({ ElMessage: { error: vi.fn(), success: vi.fn() } }));

import PermitForm from './PermitForm.vue';

const KEBIJAKAN_SEHAT = {
    needs_approval: true,
    allow_submit_without_flow: false,
};

function mountForm(kuota) {
    return shallowMount(PermitForm, {
        props: {
            config: { enabled: true },
            approvalPolicy: KEBIJAKAN_SEHAT,
            approverFlows: [{ kode_karyawan: 'A1' }],
            kuota,
        },
    });
}

beforeEach(() => {
    vi.clearAllMocks();
});

describe('PermitForm — kuota pengajuan', () => {
    it('mengunci pengiriman saat jatah sudah penuh', () => {
        const wrapper = mountForm({ terpakai: 2, maks: 2 });

        expect(wrapper.vm.kuotaHabis).toBe(true);
        expect(wrapper.vm.isSubmitBlocked).toBe(true);
    });

    it('membiarkan pengiriman selama jatah masih tersisa', () => {
        const wrapper = mountForm({ terpakai: 1, maks: 2 });

        expect(wrapper.vm.kuotaHabis).toBe(false);
        expect(wrapper.vm.isSubmitBlocked).toBe(false);
    });

    it('memperlakukan batas 0 sebagai tanpa batas', () => {
        // Sama dengan sisi server: 0 adalah jalan keluar eksplisit, bukan
        // "jatah nol" yang mengunci semua orang.
        const wrapper = mountForm({ terpakai: 9, maks: 0 });

        expect(wrapper.vm.kuotaHabis).toBe(false);
        expect(wrapper.vm.isSubmitBlocked).toBe(false);
    });

    it('tidak mengunci apa pun sebelum angka kuotanya tiba', () => {
        // Stats dimuat asinkron; mengunci saat propnya masih kosong membuat
        // tombol mati sekejap tanpa sebab yang terlihat.
        const wrapper = mountForm({});

        expect(wrapper.vm.kuotaHabis).toBe(false);
        expect(wrapper.vm.isSubmitBlocked).toBe(false);
    });

    it('menyebut jatah sebagai alasan, bukan alasan approver', () => {
        const wrapper = mountForm({ terpakai: 2, maks: 2 });

        expect(wrapper.vm.alasanTerkunci).toContain('2');
        expect(wrapper.vm.alasanTerkunci).not.toContain('approver');
    });

    it('menyebut sisa jatah dan batasnya selagi masih ada', () => {
        // Sengaja tidak mematok kalimatnya: yang wajib benar adalah angkanya —
        // sisa 2 dari batas 3. Mematok teks membuat uji ini pecah setiap kali
        // kata-katanya dipoles, tanpa ada perilaku yang berubah.
        const ringkasan = mountForm({ terpakai: 1, maks: 3 }).vm.ringkasanKuota;

        expect(ringkasan).toMatch(/\b2\b/);
        expect(ringkasan).toMatch(/\b3\b/);
    });

    it('tidak menampilkan apa pun saat tanpa batas atau angkanya belum tiba', () => {
        expect(mountForm({ terpakai: 9, maks: 0 }).vm.ringkasanKuota).toBeNull();
        expect(mountForm({}).vm.ringkasanKuota).toBeNull();
    });

    it('tidak pernah menampilkan sisa negatif', () => {
        // Jatah bisa terlampaui kalau batasnya diturunkan setelah orang sudah
        // memegang beberapa permit; "-3" di layar bukan informasi.
        const ringkasan = mountForm({ terpakai: 5, maks: 2 }).vm.ringkasanKuota;

        expect(ringkasan).not.toBeNull();
        expect(ringkasan).not.toMatch(/-\d/);
    });

    /*
     * Dua batas lain yang dulu hanya menggigit SESUDAH kirim: pengajuan yang
     * masih menunggu keputusan, dan akses inspeksi harian. Pemohon menempuh
     * tiga langkah - foto kendaraan, STNK, SIM, lalu memilih slot inspeksi -
     * untuk mendapat penolakan yang sudah bisa dipastikan sejak layarnya
     * dibuka, dan slot yang ia pegang sempat terpakai sia-sia.
     */
    it('memblokir di depan saat pengajuan lain masih menunggu keputusan', () => {
        const wrapper = mountForm({ terpakai: 0, maks: 2, pendingTerpakai: 1, pendingMaks: 1 });

        expect(wrapper.vm.kuotaHabis).toBe(true);
        expect(wrapper.vm.isSubmitBlocked).toBe(true);
        expect(wrapper.vm.blokirKuota.kode).toBe('PENDING');
    });

    it('memblokir di depan saat jatah pengajuan hari ini habis', () => {
        const wrapper = mountForm({ terpakai: 0, maks: 2, aksesTerpakai: 2, aksesMaks: 2 });

        expect(wrapper.vm.kuotaHabis).toBe(true);
        expect(wrapper.vm.blokirKuota.kode).toBe('AKSES_HARIAN');
    });

    it('menyebut sebab yang sama dengan yang akan diucapkan penolakan server', () => {
        // assertAvailableWithinTransaction() memeriksa jatah permit dulu, baru
        // pending, baru akses harian. Bila layar menyebut sebab yang lain,
        // pemohon menunggu hal yang salah: "besok" padahal yang menahan adalah
        // pengajuan yang tinggal diputus atasannya.
        const semua = mountForm({
            terpakai: 2, maks: 2,
            pendingTerpakai: 1, pendingMaks: 1,
            aksesTerpakai: 2, aksesMaks: 2,
        });
        expect(semua.vm.blokirKuota.kode).toBe('KUOTA');

        const duaTerakhir = mountForm({
            terpakai: 0, maks: 2,
            pendingTerpakai: 1, pendingMaks: 1,
            aksesTerpakai: 2, aksesMaks: 2,
        });
        expect(duaTerakhir.vm.blokirKuota.kode).toBe('PENDING');
    });

    it('memperlakukan batas 0 sebagai tanpa batas pada ketiga sumbunya', () => {
        const wrapper = mountForm({
            terpakai: 9, maks: 0,
            pendingTerpakai: 9, pendingMaks: 0,
            aksesTerpakai: 9, aksesMaks: 0,
        });

        expect(wrapper.vm.blokirKuota).toBeNull();
        expect(wrapper.vm.isSubmitBlocked).toBe(false);
    });

    it('tidak menyebut jatah permit saat yang menahan adalah batas lain', () => {
        // Sebabnya harus terbaca dari angkanya sendiri; menyebut "2 dari 2
        // permit" saat jatah permitnya justru kosong membuat pemohon mengira
        // ia harus menunggu permitnya kedaluwarsa.
        const harian = mountForm({ terpakai: 0, maks: 2, aksesTerpakai: 2, aksesMaks: 2 });

        expect(harian.vm.ringkasanKuota).toContain('2');
        expect(harian.vm.alasanTerkunci).toMatch(/hari ini/i);
        expect(harian.vm.alasanTerkunci).not.toMatch(/kedaluwarsa/i);
    });

    it('tetap menyebut approver saat itu yang menghalangi', () => {
        const wrapper = shallowMount(PermitForm, {
            props: {
                config: { enabled: true },
                approvalPolicy: KEBIJAKAN_SEHAT,
                approverFlows: [],
                kuota: { terpakai: 0, maks: 2 },
            },
        });

        expect(wrapper.vm.isSubmitBlocked).toBe(true);
        expect(wrapper.vm.alasanTerkunci).toContain('approver');
    });
});
