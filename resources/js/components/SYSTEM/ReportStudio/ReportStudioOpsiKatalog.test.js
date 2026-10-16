import { describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import ReportStudio from '@/components/SYSTEM/ReportStudio/ReportStudio.vue';

/*
 * Penjaga batas modul.
 *
 * ReportStudio milik SYSTEM. Sebelumnya ia — dan PanelSaringan — memanggil
 * '/api/v1/sop/admin/monitoring/filter-options' langsung. Tiga akibatnya:
 * modul kedua ikut memanggil endpoint SOP; alamat itu dijaga hak halaman yang
 * berbeda dari Studio sehingga dropdown bisa kosong tanpa penjelasan; dan
 * daftarnya berisi NAMA paket padahal saringannya menyaring kolom ID.
 *
 * Karena itu dua hal diuji di sini sekaligus: pilihan datang dari katalog, dan
 * TIDAK ADA satu pun permintaan ke alamat modul bisnis.
 */

const KATALOG = {
    kunci: 'kepatuhan',
    label: 'Kepatuhan',
    grain: 'Satu baris = satu karyawan × paket × item.',
    dimensi: [
        { key: 'divisi', label: 'Divisi', grup: 'identitas', sensitif: false },
        { key: 'paket', label: 'Paket', grup: 'paket', sensitif: false },
    ],
    ukuran: [{ key: 'jml_karyawan', label: 'Jumlah Karyawan', format: 'angka', sensitif: false }],
    saringan: [
        { key: 'divisi', label: 'Divisi', tipe: 'teks', operator: ['salah_satu'] },
        { key: 'paket', label: 'Paket Dokumen', tipe: 'angka', operator: ['salah_satu'] },
    ],
    opsi_saringan: {
        divisi: ['IT', 'HR'],
        paket: [{ value: 18, label: 'Keselamatan Kerja' }],
    },
};

vi.mock('@/components/SYSTEM/ReportStudio/api/reportStudioApi', () => ({
    reportStudioApi: () => ({
        katalog: vi.fn().mockResolvedValue(KATALOG),
        galeri: vi.fn().mockResolvedValue({ resmi: [], saya: [], dibagikan: [] }),
        simpan: vi.fn(),
        duplikat: vi.fn(),
        hapus: vi.fn(),
        jalankan: vi.fn(),
        pratinjau: vi.fn().mockResolvedValue({
            title: 'P', generated_at: '', columns: [], rows: [], catatan: [],
        }),
    }),
}));

describe('ReportStudio — opsi saringan dari katalog', () => {
    it('meneruskan opsi katalog ke PanelSaringan dengan nilai apa adanya', async () => {
        const w = mount(ReportStudio, { props: { baseUrl: '/api/v1/uji', tabUtama: 'studio' } });
        await flushPromises();

        const panel = w.findComponent({ name: 'PanelSaringan' });
        expect(panel.exists()).toBe(true);

        const opsi = panel.props('opsiFilter');

        // Nilai ID tetap angka. Diubah jadi teks, ia dikirim ke kolom int dan
        // SQL Server menggagalkan seluruh query dengan galat konversi.
        expect(opsi.paket[0].value).toBe(18);
        expect(opsi.divisi).toEqual(['IT', 'HR']);
    });

    it('tidak memanggil satu pun endpoint modul bisnis', async () => {
        const mataMata = vi.spyOn(axios, 'get').mockResolvedValue({ data: {} });

        try {
            mount(ReportStudio, { props: { baseUrl: '/api/v1/uji', tabUtama: 'studio' } });
            await flushPromises();

            const keSop = mataMata.mock.calls.filter(([url]) => String(url).includes('/sop/'));

            expect(keSop, 'Komponen SYSTEM tidak boleh menyebut alamat modul bisnis.').toEqual([]);
        } finally {
            mataMata.mockRestore();
        }
    });
});
