import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import PaRevisiPesertaMassalDialog from './PaRevisiPesertaMassalDialog.vue';

/*
 | Revisi banyak orang: tindakan → cari berfilter → centang (semua hasil filter) → pratinjau
 | per orang → satu alasan → tugas latar. Yang tidak bisa dilewati, bukan menggagalkan sisanya.
 */

const paApiMock = vi.hoisted(() => ({
    opsiFilterKaryawanPraKecuali: vi.fn(),
    cariRevisiPeserta: vi.fn(),
    pratinjauRevisiMassal: vi.fn(),
    mulaiRevisiMassal: vi.fn(),
    statusRevisiMassal: vi.fn(),
}));
vi.mock('../../api/paApi.js', () => ({ default: paApiMock }));

const stubs = {
    HcisDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /><slot name="footer" /></div>' },
    HcisButton: { props: ['label', 'disabled', 'loading'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>' },
    HcisField: { props: ['modelValue'], emits: ['update:modelValue'], template: '<input class="f" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
    HcisSelect: { props: ['modelValue'], template: '<div class="sel"><slot /></div>' },
    HcisSelectOption: { template: '<span />' },
    HcisTextarea: { props: ['modelValue'], emits: ['update:modelValue'], template: '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
    HcisAlert: { props: ['title', 'description'], template: '<div>{{ title }} {{ description }}<slot /></div>' },
    HcisCheckbox: {
        props: ['modelValue', 'label'],
        emits: ['update:modelValue'],
        template: '<label><input type="checkbox" :checked="!!modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" />{{ label }}</label>',
    },
};

const baris = [
    { kode_perusahaan: '001', kode_karyawan: 'A4', nama: 'Aji', status: 'DIIKUTKAN', nama_level: 'OFFICER' },
    { kode_perusahaan: '001', kode_karyawan: 'E15', nama: 'Dimas', status: 'DIIKUTKAN', nama_level: 'OFFICER' },
    { kode_perusahaan: '001', kode_karyawan: 'F23', nama: 'Fauzan', status: 'DIIKUTKAN', nama_level: 'OFFICER' },
];

const pasang = (props = {}) => mount(PaRevisiPesertaMassalDialog, { props: { modelValue: true, cycleId: 77, ...props }, global: { stubs } });

describe('PaRevisiPesertaMassalDialog', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        Object.values(paApiMock).forEach((f) => f.mockReset());
        paApiMock.opsiFilterKaryawanPraKecuali.mockResolvedValue({ level: [{ id: 4, nama: 'OFFICER' }], divisi: ['Operasional'], departemen: ['Production Support'] });
        paApiMock.cariRevisiPeserta.mockResolvedValue({ baris });
        paApiMock.pratinjauRevisiMassal.mockResolvedValue({
            baris: [
                { ...baris[0], boleh: true, sebab: null, dampak: { penugasan_aktif: 6, penugasan_diisi: 0 } },
                { ...baris[1], boleh: true, sebab: null, dampak: { penugasan_aktif: 6, penugasan_diisi: 2 } },
                { ...baris[2], boleh: false, sebab: 'Hasil peserta ini sudah TERBIT.', dampak: { penugasan_aktif: 6, penugasan_diisi: 6 } },
            ],
            ringkasan: { total: 3, bisa: 2, tidak: 1, penugasan_dibatalkan: 10 },
        });
        paApiMock.mulaiRevisiMassal.mockResolvedValue({ tugas: { id: 't1', status: 'queued', persen: 0, tindakan: 'KECUALIKAN', ringkasan: { total: 2, sudah: 0 }, hasil: [] }, diproses: 2, dilewati: 1 });
        paApiMock.statusRevisiMassal.mockResolvedValue({ tugas: { id: 't1', status: 'ready', persen: 100, tindakan: 'KECUALIKAN', ringkasan: { total: 2, sudah: 2, berhasil: 2, gagal: 0 }, hasil: [{ kode_karyawan: 'A4', nama: 'Aji', ok: true, dibatalkan: 6, dibentuk: 0 }] } });
    });

    it('pilih semua hasil filter → pratinjau per orang → jalankan sebagai tugas latar', async () => {
        const w = pasang();
        await flushPromises();

        await w.find('[data-uji="rvm-tindakan-KECUALIKAN"]').trigger('click');
        await vi.advanceTimersByTimeAsync(400);
        await flushPromises();

        /* Tindakan Kecualikan menyaring peserta yang DIIKUTKAN. */
        expect(paApiMock.cariRevisiPeserta).toHaveBeenLastCalledWith(77, expect.objectContaining({ sumber: 'peserta', status: 'DIIKUTKAN' }));

        await w.find('[data-uji="rvm-pilih-semua"] input').setValue(true);
        expect(w.find('[data-uji="rvm-terpilih"]').text()).toContain('3 dipilih');

        await w.find('[data-uji="rvm-pratinjau-btn"]').trigger('click');
        await flushPromises();

        const pr = w.find('[data-uji="rvm-pratinjau"]');
        expect(pr.text()).toContain('2 dari 3 bisa diproses · 1 dilewati');
        expect(pr.text()).toContain('Hasil peserta ini sudah TERBIT.');
        expect(pr.text()).toContain('2 yang sudah diisi tetap tercatat');

        const jalan = () => w.find('[data-uji="rvm-jalankan"]');
        expect(jalan().attributes('disabled')).toBeDefined();
        await w.find('textarea').setValue('Resign massal Oktober');
        await jalan().trigger('click');
        await flushPromises();

        expect(paApiMock.mulaiRevisiMassal).toHaveBeenCalledWith(77, expect.objectContaining({ tindakan: 'KECUALIKAN', alasan: 'Resign massal Oktober' }));
        expect(paApiMock.mulaiRevisiMassal.mock.calls[0][1].orang).toHaveLength(3);
        expect(w.find('[data-uji="rvm-status"]').text()).toContain('0 dari 2 orang diproses');

        await vi.advanceTimersByTimeAsync(3000);
        await flushPromises();
        expect(w.find('[data-uji="rvm-status"]').text()).toContain('selesai: 2 berhasil');
        expect(w.find('[data-uji="rvm-hasil"]').text()).toContain('6 penugasan dibatalkan');
        vi.useRealTimers();
    });

    it('dibuka dari satu baris: tindakan Terapkan level dan orangnya langsung tercentang', async () => {
        paApiMock.cariRevisiPeserta.mockResolvedValue({ baris: [baris[1]] });
        const w = pasang({ karyawanAwal: { kode_karyawan: 'E15', nama: 'Dimas' } });
        await vi.advanceTimersByTimeAsync(400);
        await flushPromises();

        expect(w.find('[data-uji="rvm-tindakan-TERAPKAN_LEVEL"]').attributes('aria-checked')).toBe('true');
        expect(w.find('[data-uji="rvm-terpilih"]').text()).toContain('1 dipilih');
        vi.useRealTimers();
    });

    /* Level yang tidak dinilai: arahkan ke Koreksi Level, bukan sekadar "tidak bisa". */
    it('menunjuk Koreksi Level bila level orangnya tidak dinilai', async () => {
        paApiMock.pratinjauRevisiMassal.mockResolvedValue({
            baris: [{ ...baris[0], boleh: false, perlu_koreksi_level: true, sebab: 'Level PA-nya FREELANCE termasuk level yang tidak dinilai. Bila orang ini memang harus dinilai di siklus ini, koreksi Level PA-nya dulu di halaman Koreksi Level, lalu ulangi revisi.', dampak: {} }],
            ringkasan: { total: 1, bisa: 0, tidak: 1, penugasan_dibatalkan: 0, perlu_koreksi_level: 1 },
            alamat_koreksi_level: '/performance-appraisal/master/level-pa',
        });
        const buka = vi.spyOn(window, 'open').mockImplementation(() => null);
        const w = pasang();
        await flushPromises();
        await w.find('[data-uji="rvm-tindakan-IKUTKAN_KEMBALI"]').trigger('click');
        await vi.advanceTimersByTimeAsync(400);
        await flushPromises();
        await w.find('[data-uji="rvm-pilih-A4"] input').setValue(true);
        await w.find('[data-uji="rvm-pratinjau-btn"]').trigger('click');
        await flushPromises();

        expect(w.find('[data-uji="rvm-perlu-koreksi"]').text()).toContain('1 orang Level PA-nya tidak dinilai');
        expect(w.find('[data-uji="rvm-pratinjau"]').text()).toContain('koreksi Level PA-nya dulu');
        await w.find('[data-uji="rvm-buka-koreksi"]').trigger('click');
        expect(buka).toHaveBeenCalledWith('/performance-appraisal/master/level-pa', '_blank', 'noopener');
        buka.mockRestore();
        vi.useRealTimers();
    });

    /* Dikecualikan tidak otomatis berhenti menilai: opsinya ditawarkan, dan dikirim bila dipilih. */
    it('kecualikan menawarkan lepas sebagai penilai bila orangnya masih menilai', async () => {
        paApiMock.pratinjauRevisiMassal.mockResolvedValue({
            baris: [{ ...baris[0], boleh: true, sebab: null, dampak: { penugasan_aktif: 6, penugasan_diisi: 0, menilai: 3 } }],
            ringkasan: { total: 1, bisa: 1, tidak: 0, penugasan_dibatalkan: 6, menilai: 3 },
        });
        const w = pasang();
        await flushPromises();
        await w.find('[data-uji="rvm-tindakan-KECUALIKAN"]').trigger('click');
        await vi.advanceTimersByTimeAsync(400);
        await flushPromises();
        await w.find('[data-uji="rvm-pilih-A4"] input').setValue(true);
        await w.find('[data-uji="rvm-pratinjau-btn"]').trigger('click');
        await flushPromises();

        expect(w.find('[data-uji="rvm-pratinjau"]').text()).toContain('masih menilai 3 peserta');
        await w.find('[data-uji="rvm-lepas-penilai"] input').setValue(true);
        await w.find('textarea').setValue('Resign per 1 Okt');
        await w.find('[data-uji="rvm-jalankan"]').trigger('click');
        await flushPromises();

        expect(paApiMock.mulaiRevisiMassal.mock.calls[0][1]).toMatchObject({ tindakan: 'KECUALIKAN', lepas_penilai: true });
        vi.useRealTimers();
    });

    /* Dibuka dari peringatan "penilai tidak aktif": mencari penilai, bukan peserta. */
    it('lepas penilai mencari penilai tidak aktif dan menyebut tugas menilainya', async () => {
        paApiMock.cariRevisiPeserta.mockResolvedValue({ baris: [{ kode_perusahaan: '001', kode_karyawan: 'R9', nama: 'Rudi', status: 'TIDAK_AKTIF', menilai: 4 }] });
        paApiMock.pratinjauRevisiMassal.mockResolvedValue({
            baris: [{ kode_perusahaan: '001', kode_karyawan: 'R9', nama: 'Rudi', boleh: true, sebab: null, dampak: { menilai: 4 } }],
            ringkasan: { total: 1, bisa: 1, tidak: 0, penugasan_dibatalkan: 4, menilai: 4 },
        });
        const w = pasang({ karyawanAwal: { tindakan: 'LEPAS_PENILAI', status: 'TIDAK_AKTIF' } });
        await vi.advanceTimersByTimeAsync(400);
        await flushPromises();

        expect(paApiMock.cariRevisiPeserta).toHaveBeenCalledWith(77, expect.objectContaining({ sumber: 'penilai', status: 'TIDAK_AKTIF' }));
        expect(w.text()).toContain('Menilai 4 peserta');
        expect(w.text()).toContain('Tidak aktif di HRIS');
        await w.find('[data-uji="rvm-pilih-semua"] input').setValue(true);
        await w.find('[data-uji="rvm-pratinjau-btn"]').trigger('click');
        await flushPromises();
        expect(w.find('[data-uji="rvm-pratinjau"]').text()).toContain('4 tugas menilai dibatalkan');
        vi.useRealTimers();
    });

    it('selaraskan relasi menyebut siapa keluar dan masuk serta tanggal acuannya', async () => {
        paApiMock.pratinjauRevisiMassal.mockResolvedValue({
            baris: [{ ...baris[1], boleh: true, sebab: null, dampak: {}, rencana: { acuan: '2026-06-30', keluar: ['Budi (Atasan)'], masuk: ['Sari (Atasan)'] } }],
            ringkasan: { total: 1, bisa: 1, tidak: 0, penugasan_dibatalkan: 1, menilai: 0 },
            rencana_dihitung: true,
        });
        const w = pasang();
        await flushPromises();
        await w.find('[data-uji="rvm-tindakan-SELARASKAN_RELASI"]').trigger('click');
        await vi.advanceTimersByTimeAsync(400);
        await flushPromises();
        await w.find('[data-uji="rvm-pilih-E15"] input').setValue(true);
        await w.find('[data-uji="rvm-pratinjau-btn"]').trigger('click');
        await flushPromises();

        const pr = w.find('[data-uji="rvm-pratinjau"]').text();
        expect(pr).toContain('Keluar: Budi (Atasan) · Masuk: Sari (Atasan)');
        expect(w.find('[data-uji="rvm-acuan"]').text()).toContain('2026-06-30');
        vi.useRealTimers();
    });

    /* Di bawah ambang pengisi jawaban yang ada pun dibuang — disebut SEBELUM dijalankan. */
    it('lepas penilai menyebut kategori yang tidak akan dihitung', async () => {
        paApiMock.cariRevisiPeserta.mockResolvedValue({ baris: [{ kode_perusahaan: '001', kode_karyawan: 'R9', nama: 'Rudi', status: 'AKTIF', menilai: 1 }] });
        paApiMock.pratinjauRevisiMassal.mockResolvedValue({
            baris: [{ kode_perusahaan: '001', kode_karyawan: 'R9', nama: 'Rudi', boleh: true, sebab: null, dampak: { menilai: 1 },
                peringatan: ['Andre: kategori Bawahan tidak akan dihitung (perlu 2 pengisi, paling banyak 1 penilai) — bobotnya dialihkan ke kategori lain. Tambah penilai lewat Kelola penilai.'] }],
            ringkasan: { total: 1, bisa: 1, tidak: 0, penugasan_dibatalkan: 1, menilai: 1, peringatan: 1 },
        });
        const w = pasang({ karyawanAwal: { tindakan: 'LEPAS_PENILAI', status: '' } });
        await vi.advanceTimersByTimeAsync(400);
        await flushPromises();
        await w.find('[data-uji="rvm-pilih-R9"] input').setValue(true);
        await w.find('[data-uji="rvm-pratinjau-btn"]').trigger('click');
        await flushPromises();

        expect(w.find('[data-uji="rvm-peringatan"]').text()).toContain('1 catatan penilai & bobot');
        expect(w.find('[data-uji="rvm-pratinjau"]').text()).toContain('Andre: kategori Bawahan tidak akan dihitung (perlu 2 pengisi');
        vi.useRealTimers();
    });

    /* Penilai baru memakai jendela yang sama — yang sudah lewat disebut sebelum dijalankan. */
    it('menyebut jendela pengisian yang sudah lewat untuk tindakan yang membentuk penilai', async () => {
        paApiMock.pratinjauRevisiMassal.mockResolvedValue({
            baris: [{ ...baris[1], boleh: true, sebab: null, dampak: { penugasan_aktif: 6, penugasan_diisi: 0 }, level_siklus: 'OFFICER', level_terkini: 'SPV' }],
            ringkasan: { total: 1, bisa: 1, tidak: 0, penugasan_dibatalkan: 6 },
            jendela: { status: 'LEWAT', pesan: 'Jendela pengisian sudah berakhir 10-10-2026 23:59. Penilai baru hasil revisi tidak akan bisa mengisi.' },
        });
        const w = pasang();
        await flushPromises();
        await w.find('[data-uji="rvm-tindakan-TERAPKAN_LEVEL"]').trigger('click');
        await vi.advanceTimersByTimeAsync(400);
        await flushPromises();
        await w.find('[data-uji="rvm-pilih-E15"] input').setValue(true);
        await w.find('[data-uji="rvm-pratinjau-btn"]').trigger('click');
        await flushPromises();

        expect(w.find('[data-uji="rvm-jendela"]').text()).toContain('sudah berakhir');
        vi.useRealTimers();
    });
});

