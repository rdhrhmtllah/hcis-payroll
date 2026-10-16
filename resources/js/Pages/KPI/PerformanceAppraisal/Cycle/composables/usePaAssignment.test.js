import { describe, expect, it, vi } from 'vitest';
import { usePaAssignment } from './usePaAssignment.js';

const usulan = {
    ATASAN: {
        kategori_id: 1,
        kode_kategori: 'ATASAN',
        nama_kategori: 'Atasan Langsung',
        min_rater: 1,
        maks_rater: 2,
        terpasang: 1,
        butuh_manual: false,
        izinkan_manual: true,
        calon: [
            {
                kode_perusahaan: '001',
                kode_karyawan: 'A0044',
                nama: 'Dewi Anggraini',
                organization_relationship_id: 91,
                sudah_ditugaskan: true,
            },
            {
                kode_perusahaan: '001',
                kode_karyawan: 'A0012',
                nama: 'Hendra Wijaya',
                organization_relationship_id: 92,
                sudah_ditugaskan: false,
            },
        ],
    },
    PEER: {
        kategori_id: 4,
        kode_kategori: 'PEER',
        nama_kategori: 'Rekan Kerja',
        min_rater: 2,
        maks_rater: 4,
        terpasang: 0,
        butuh_manual: true,
        izinkan_manual: true,
        calon: [],
    },
};

describe('usePaAssignment', () => {
    it('memetakan usulan menurut urutan kontrak dan menyusun payload USULAN serta MANUAL', async () => {
        const kesiapan = {
            siap_dibuka: false,
            siap_dihitung: false,
            kategori: { ATASAN: { cukup_terpasang: true, terpasang: 1, selesai: 1 } },
        };
        const api = {
            usulanPenilai: vi.fn().mockResolvedValue({ usulan, kesiapan }),
            tugaskanPenilai: vi.fn().mockResolvedValue({ dibuat: 2, kategori_dihitung_ulang: [1, 4] }),
        };
        const state = usePaAssignment(api);

        await state.muat(12);
        expect(state.kategori.value.map((item) => item.kode_kategori)).toEqual(['ATASAN', 'PEER']);
        expect(state.pilihan.ATASAN).toEqual(['A0044']);
        expect(state.kesiapan.value).toEqual(kesiapan);

        state.ubahPilihan('ATASAN', usulan.ATASAN.calon[1]);
        state.ubahManual('PEER', 'kode_perusahaan', '001');
        state.ubahManual('PEER', 'kode_karyawan', 'A0099');
        state.ubahManual('PEER', 'alasan_manual', 'Kebutuhan lintas fungsi');

        const result = await state.simpan(12);

        expect(result.data).toEqual({ dibuat: 2, kategori_dihitung_ulang: [1, 4] });
        /*
         | A0044 (`sudah_ditugaskan: true`) TIDAK boleh ikut terkirim.
         |
         | Ia tetap tercentang di layar — `pilihan` memang berisi terpasang + usulan — tetapi
         | endpoint ini MEMBUAT penugasan, dan `tugaskan()` menolak penilai yang sudah ada.
         | Mengirimnya membuat Simpan gagal bagi setiap peserta yang sudah punya penilai, dan
         | penyimpanan bobot yang berjalan sesudahnya tidak pernah kesampaian.
         */
        expect(api.tugaskanPenilai).toHaveBeenCalledWith(12, [
            {
                kode_kategori: 'ATASAN',
                kode_perusahaan: '001',
                kode_karyawan: 'A0012',
                sumber: 'USULAN',
                organization_relationship_id: 92,
                alasan_manual: null,
            },
            {
                kode_kategori: 'PEER',
                kode_perusahaan: '001',
                kode_karyawan: 'A0099',
                sumber: 'MANUAL',
                organization_relationship_id: null,
                alasan_manual: 'Kebutuhan lintas fungsi',
            },
        ], {});
        /* Argumen ketiga adalah `tambahan` — bobot dan bobot_rata dikirim dalam
           permintaan yang SAMA supaya penugasan dan bobotnya masuk satu transaksi.
           Kosong di sini karena uji ini hanya menambah penilai. */
        expect(state.submitSuccess.value).toBe('2 penugasan tersimpan.');
    });

    /* Sesudah dikunci server langsung menjadwalkan penilai baru ke HC Learn — disebut. */
    it('menyebut penjadwalan HC Learn otomatis sesudah menambah penilai', async () => {
        const api = {
            usulanPenilai: vi.fn().mockResolvedValue({ usulan, kesiapan: null }),
            tugaskanPenilai: vi.fn().mockResolvedValue({ dibuat: 1, jadwal_hclearn: { penjadwalan: 'pa-jdw-1', catatan: 'Penilai baru dijadwalkan ke HC Learn di latar (Tugas & Aktivitas).' } }),
        };
        const state = usePaAssignment(api);
        await state.muat(12);
        state.ubahPilihan('ATASAN', usulan.ATASAN.calon[1]);
        await state.simpan(12);

        expect(state.submitSuccess.value).toBe('1 penugasan tersimpan. Penilai baru dijadwalkan ke HC Learn di latar (Tugas & Aktivitas).');
    });

    it('tidak mengusulkan lagi penilai yang baru dicabut, melompat ke calon berikutnya', async () => {
        const bawahan = {
            BAWAHAN: {
                kategori_id: 3,
                kode_kategori: 'BAWAHAN',
                nama_kategori: 'Bawahan',
                min_rater: 2,
                terpasang: 1,
                calon: [
                    { kode_perusahaan: '001', kode_karyawan: 'A1', nama: 'Frans', sudah_ditugaskan: false, pernah_dicabut: true },
                    { kode_perusahaan: '001', kode_karyawan: 'R1', nama: 'Rif', sudah_ditugaskan: true },
                    { kode_perusahaan: '001', kode_karyawan: 'Y1', nama: 'Ryan', sudah_ditugaskan: false },
                ],
            },
        };
        const state = usePaAssignment({ usulanPenilai: vi.fn().mockResolvedValue({ usulan: bawahan }) });

        await state.muat(12);
        expect(state.pilihan.BAWAHAN).toEqual(['R1', 'Y1']);
    });

    it('menahan submit manual tanpa alasan dan tidak memanggil API', async () => {
        const api = {
            usulanPenilai: vi.fn().mockResolvedValue({ usulan }),
            tugaskanPenilai: vi.fn(),
        };
        const state = usePaAssignment(api);

        await state.muat(12);
        state.ubahManual('PEER', 'kode_karyawan', 'A0099');

        const result = await state.simpan(12);

        expect(result.error).toBeInstanceOf(Error);
        expect(state.submitError.value).toBe('Alasan manual wajib diisi untuk penilai manual.');
        expect(api.tugaskanPenilai).not.toHaveBeenCalled();
    });

    it('mempertahankan pesan dan metadata error saat penugasan ditolak', async () => {
        const exception = Object.assign(new Error('Kategori PEER belum memenuhi minimum penilai.'), {
            status: 422,
            error_code: 'PA_RATER_MINIMUM',
            request_id: 'req-pa-assignment-1',
        });
        const api = {
            usulanPenilai: vi.fn().mockResolvedValue({ usulan }),
            tugaskanPenilai: vi.fn().mockRejectedValue(exception),
        };
        const state = usePaAssignment(api);

        await state.muat(12);

        /* SATU PENILAI DIPILIH LEBIH DULU. Tanpa ini `simpan()` pulang lebih awal lewat
           penjaga "tanpa penilai baru = tanpa permintaan", API-nya tidak pernah
           dipanggil, dan jalur galat yang justru sedang diuji tidak pernah dilalui —
           ujinya lalu memerah dengan `undefined`, bukan dengan penolakan yang dimaksud. */
        state.ubahPilihan('ATASAN', usulan.ATASAN.calon[1]);

        const result = await state.simpan(12);

        expect(api.tugaskanPenilai).toHaveBeenCalled();
        expect(result.error).toBe(exception);
        expect(state.submitError.value).toBe('Kategori PEER belum memenuhi minimum penilai.');
        expect(state.submitErrorStatus.value).toBe(422);
        expect(state.submitErrorCode.value).toBe('PA_RATER_MINIMUM');
        expect(state.submitRequestId.value).toBe('req-pa-assignment-1');
    });

    it('mempertahankan nilai KPI di atas 100 dari hasil server', async () => {
        const api = {
            hitungPeserta: vi.fn().mockResolvedValue({
                result_id: 902,
                status: 'DIHITUNG',
                versi: 1,
                nilai_kpi: 104.5,
                nilai_kompetensi: 82.31,
                final_score: 90.13,
                kode_grade: 'A',
                kode_kuadran: 'STRIVING_STAR',
                alasan_tertahan: null,
            }),
        };
        const state = usePaAssignment(api);

        const result = await state.hitung(12);

        expect(result.data.nilai_kpi).toBe(104.5);
        expect(state.hasilPeserta.value).toMatchObject({ nilai_kpi: 104.5, final_score: 90.13 });
        expect(api.hitungPeserta).toHaveBeenCalledWith(12);
    });
});
