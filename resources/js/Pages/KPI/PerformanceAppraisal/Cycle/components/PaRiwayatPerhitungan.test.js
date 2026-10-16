import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PaRiwayatPerhitungan from './PaRiwayatPerhitungan.vue';

const paApiMock = vi.hoisted(() => ({ riwayatHasil: vi.fn() }));
vi.mock('../../api/paApi.js', () => ({ default: paApiMock }));

const stubs = { HcisIcon: { template: '<i />' } };

const angka = (final, grade) => ({ status: 'DIHITUNG', kpi: 90, kompetensi: 80, final, grade_sistem: grade, grade_final: grade, alasan_tertahan: null });
const respons = {
    result_id: 1,
    versi_kini: 2,
    peristiwa: [
        { jenis: 'KURVA_DIKUNCI', pada: '2026-10-02 10:05:00', oleh: 'Sistem', versi: 2, angka: angka(85.5, 'B'), perubahan: [{ kolom: 'grade_sistem', sebelum: null, sesudah: 'B' }], kompetensi: [] },
        {
            jenis: 'HITUNG_ULANG', pada: '2026-10-02 10:00:00', oleh: 'Ridho', versi: 2, angka: angka(85.5, null),
            perubahan: [{ kolom: 'final', sebelum: 80, sesudah: 85.5 }],
            kompetensi: [{ kode: 'K1', nama: 'Integritas', nilai: 82, sebelum: 75 }, { kode: 'K2', nama: 'Analisis', nilai: 70, sebelum: 70 }],
        },
        { jenis: 'HITUNG', pada: '2026-10-01 09:00:00', oleh: 'Ridho', versi: 1, angka: angka(80, null), perubahan: [], kompetensi: [] },
    ],
};

describe('PaRiwayatPerhitungan', () => {
    beforeEach(() => {
        paApiMock.riwayatHasil.mockReset();
        paApiMock.riwayatHasil.mockResolvedValue(respons);
    });

    it('baru memuat saat tab aktif, lalu menampilkan perubahan sebelum → sesudah', async () => {
        const w = mount(PaRiwayatPerhitungan, { props: { participantId: 7, aktif: false }, global: { stubs } });
        await flushPromises();
        expect(paApiMock.riwayatHasil).not.toHaveBeenCalled();

        await w.setProps({ aktif: true });
        await flushPromises();
        expect(paApiMock.riwayatHasil).toHaveBeenCalledWith(7);

        expect(w.find('[data-uji="riwayat-ringkas"]').text()).toContain('Versi 2');
        expect(w.find('[data-uji="riwayat-ringkas"]').text()).toContain('2× dihitung');
        const ulang = w.find('[data-uji="riwayat-HITUNG_ULANG"]');
        expect(ulang.text()).toContain('Ridho');
        expect(ulang.find('[data-uji="riwayat-perubahan"]').text()).toContain('+5,50');
        // Versi pertama tidak punya pembanding: angkanya ditampilkan apa adanya.
        expect(w.find('[data-uji="riwayat-HITUNG"] [data-uji="riwayat-angka"]').text()).toContain('80,00');

        // Nilai per kompetensi dibuka dengan klik; hanya yang berubah diberi selisih.
        expect(ulang.text()).toContain('1 berubah');
        await ulang.find('.pa-rp__toggle').trigger('click');
        const baris = ulang.findAll('[data-uji="riwayat-kompetensi"] tr');
        expect(baris[0].text()).toContain('+7,00');
        expect(baris[1].find('em').exists()).toBe(false);

        // Pindah tab lalu kembali tidak memuat ulang.
        await w.setProps({ aktif: false });
        await w.setProps({ aktif: true });
        await flushPromises();
        expect(paApiMock.riwayatHasil).toHaveBeenCalledTimes(1);
    });

    it('mode verifikasi menyembunyikan peristiwa dan perubahan grade', async () => {
        const w = mount(PaRiwayatPerhitungan, { props: { participantId: 7, aktif: true, modeVerifikasi: true }, global: { stubs } });
        await flushPromises();
        expect(w.find('[data-uji="riwayat-KURVA_DIKUNCI"]').exists()).toBe(false);
        expect(w.text()).not.toContain('Grade');
    });

    it('catatan kualitatif tampil sebelum → sesudah dan bisa disaring', async () => {
        paApiMock.riwayatHasil.mockResolvedValue({
            versi_kini: 2,
            peristiwa: [
                {
                    jenis: 'CATATAN', pada: '2026-10-03 09:00:00', oleh: 'Ridho', versi: null, angka: null, kompetensi: [], keterangan: null,
                    perubahan: [
                        { kolom: 'catatan', kode: 'QL01', nama: 'Strength', sebelum: 'Teliti', sesudah: 'Teliti dan cepat' },
                        { kolom: 'catatan', kode: 'QL03', nama: 'Initiative', sebelum: null, sesudah: 'Proaktif' },
                    ],
                },
                ...respons.peristiwa,
            ],
        });
        const w = mount(PaRiwayatPerhitungan, { props: { participantId: 7, aktif: true }, global: { stubs } });
        await flushPromises();

        const c = w.find('[data-uji="riwayat-CATATAN"] [data-uji="riwayat-catatan"]');
        expect(c.find('.pa-rp__catatan-lama').text()).toBe('Teliti');
        expect(c.find('.pa-rp__catatan-baru').text()).toBe('Teliti dan cepat');
        expect(c.text()).toContain('baru');
        expect(w.find('[data-uji="riwayat-ringkas"]').text()).toContain('1× catatan');

        await w.find('[data-uji="saring-catatan"]').trigger('click');
        expect(w.findAll('.pa-rp__item').length).toBe(1);
        await w.find('[data-uji="saring-angka"]').trigger('click');
        expect(w.find('[data-uji="riwayat-CATATAN"]').exists()).toBe(false);
        expect(w.findAll('.pa-rp__item').length).toBe(3);
    });

    it('respons peserta lama yang datang terlambat tidak menimpa peserta baru', async () => {
        let lepasLama;
        paApiMock.riwayatHasil
            .mockImplementationOnce(() => new Promise((r) => { lepasLama = r; }))
            .mockResolvedValueOnce({ versi_kini: 9, peristiwa: [{ jenis: 'HITUNG', pada: '2026-10-03 08:00:00', oleh: 'Baru', versi: 9, angka: angka(60, null), perubahan: [], kompetensi: [] }] });
        const w = mount(PaRiwayatPerhitungan, { props: { participantId: 1, aktif: true }, global: { stubs } });
        await w.setProps({ participantId: 2 });
        await flushPromises();
        lepasLama(respons);
        await flushPromises();
        expect(w.find('[data-uji="riwayat-ringkas"]').text()).toContain('Versi 9');
        expect(w.text()).not.toContain('Ridho');
    });
});
