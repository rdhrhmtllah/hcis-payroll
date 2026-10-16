import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import PanelCoverageDokumenPaket from './PanelCoverageDokumenPaket.vue';

const dummyDokumen = {
    Total_Dokumen: 25,
    Dokumen_Ditugaskan: 20,
    Rerata_Coverage: 72.5,
    Sebaran: { Tinggi: 12, Sedang: 5, Rendah: 2, Nol: 1 },
    Tertinggi: [
        { Id_Dokumen: 'd-1', Judul: 'SOP Onboarding', Nomor: 'SOP/HR/001', Target: 100, Selesai: 95, Persen: 95 },
    ],
    Terendah: [
        { Id_Dokumen: 'd-2', Judul: 'SOP K3 Gedung', Nomor: 'SOP/GA/002', Target: 50, Selesai: 5, Persen: 10 },
    ],
};

const dummyPaket = {
    Total_Paket: 10,
    Paket_Ditugaskan: 8,
    Rerata_Coverage: 80.0,
    Sebaran: { Tinggi: 6, Sedang: 2, Rendah: 0, Nol: 0 },
    Tertinggi: [
        { Id_Paket: 'p-1', Nama: 'Paket Karyawan Baru 2026', Kode: 'PKT-001', Target: 20, Selesai: 20, Persen: 100 },
    ],
    Terendah: [
        { Id_Paket: 'p-2', Nama: 'Paket Sertifikasi Tahunan', Kode: 'PKT-002', Target: 30, Selesai: 18, Persen: 60 },
    ],
};

const pasang = (props = {}) => mount(PanelCoverageDokumenPaket, {
    props: {
        coverageDokumen: dummyDokumen,
        coveragePaket: dummyPaket,
        ...props,
    },
});

describe('PanelCoverageDokumenPaket', () => {
    it('menampilkan data coverage dokumen secara default', () => {
        const w = pasang();

        expect(w.text()).toContain('72.5%');
        expect(w.text()).toContain('SOP Onboarding');
        expect(w.text()).toContain('95%');
        expect(w.text()).toContain('SOP K3 Gedung');
        expect(w.text()).toContain('10%');
    });

    it('dapat beralih ke tab Per Paket saat tombol paket diklik', async () => {
        const w = pasang();
        const btnPaket = w.findAll('.pcdp-tab-btn')[1];

        await btnPaket.trigger('click');

        expect(w.text()).toContain('80%');
        expect(w.text()).toContain('Paket Karyawan Baru 2026');
        expect(w.text()).toContain('100%');
        expect(w.text()).toContain('Paket Sertifikasi Tahunan');
        expect(w.text()).toContain('60%');
    });

    it('menampilkan sebaran kategori persentase coverage', () => {
        const w = pasang();

        expect(w.text()).toContain('≥ 80% (Tinggi)');
        expect(w.text()).toContain('50 – 79% (Sedang)');
        expect(w.text()).toContain('1 – 49% (Rendah)');
        expect(w.text()).toContain('0% (Belum Mulai)');
    });
});
