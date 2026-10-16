import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Dialog from './PaBalancingBuatSesiDialog.vue';
import paApi from '../../api/paApi.js';
import { hcisMessage } from '@/components/ui';

vi.mock('../../api/paApi.js', () => ({ default: { kandidatBalancing: vi.fn(), timPenilaiBalancing: vi.fn(), buatSesiBalancing: vi.fn() } }));
vi.mock('@inertiajs/vue3', () => ({ usePage: () => ({ props: { auth: { user: { id: 622, name: 'Ridho' } } } }) }));
vi.mock('@/components/ui', async (asli) => ({
    ...(await asli()),
    hcisMessage: { warning: vi.fn(), error: vi.fn(), success: vi.fn() },
}));

const stubs = {
    HcisDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /><slot name="footer" /></div>' },
    HcisButton: { props: ['label', 'disabled', 'loading'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>' },
    HcisSkeleton: { template: '<div />' },
    HcisAvatar: { template: '<i />' },
    HcisIcon: { template: '<i />' },
    HcisBadge: { props: ['label'], template: '<span class="badge">{{ label }}</span>' },
    HcisPagination: { props: ['total'], template: '<nav class="paginasi">{{ total }}</nav>' },
    HcisSelect: {
        props: ['modelValue'],
        emits: ['update:modelValue'],
        template: '<select :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><option value=""></option><slot /></select>',
    },
    HcisSelectOption: { props: ['value', 'label'], template: '<option :value="value">{{ label }}</option>' },
};

const kandidat = [
    { participant_id: 1, nama: 'Andi', kode_karyawan: 'K1', kode_perusahaan: '001', level_id: 2, level: 'Staff', divisi_id: 7, divisi: 'Ops', grade: 'B_PLUS', layak: true, alasan: null, jumlah_sesi: 0 },
    { participant_id: 2, nama: 'Budi', kode_karyawan: 'K2', kode_perusahaan: '001', level_id: 3, level: 'Supervisor', divisi_id: 7, divisi: 'Ops', grade: 'A', layak: false, alasan: 'Hasil sudah terbit ke karyawan', jumlah_sesi: 1 },
];
const karyawan = [
    { kunci: '001|K9', kode_perusahaan: '001', kode_karyawan: 'K9', nama: 'Citra', jabatan: 'Dept Head Produksi', level_id: 5, level: 'Manager', divisi_id: 7, divisi: 'Ops', sub_divisi_id: null, sub_divisi: null },
];

const pasang = async () => {
    const w = mount(Dialog, { props: { modelValue: true, cycleId: 5 }, global: { stubs } });
    await flushPromises();
    return w;
};
const tombol = (w, awal) => w.findAll('button').find((b) => b.text().trim().startsWith(awal));
const lanjut = async (w) => { await tombol(w, 'Selanjutnya').trigger('click'); await flushPromises(); };
const panelAktif = (w) => w.findAll('[data-uji^="panel-"]').find((p) => p.attributes('style') === undefined || !p.attributes('style').includes('display: none'));

describe('PaBalancingBuatSesiDialog', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        paApi.kandidatBalancing.mockResolvedValue(kandidat);
        paApi.timPenilaiBalancing.mockResolvedValue(karyawan);
    });

    it('memuat kandidat sekali tanpa saringan server dan calon tim penilai dari semua karyawan', async () => {
        await pasang();
        expect(paApi.kandidatBalancing).toHaveBeenCalledTimes(1);
        expect(paApi.kandidatBalancing).toHaveBeenCalledWith(5, {});
        expect(paApi.timPenilaiBalancing).toHaveBeenCalledTimes(1);
    });

    it('kandidat tidak layak tidak bisa dipilih dan alasannya tampil', async () => {
        const w = await pasang();
        expect(w.find('[data-uji="pilih-2"]').attributes('disabled')).toBeDefined();
        expect(w.text()).toContain('Hasil sudah terbit ke karyawan');
    });

    it('saringan berjalan di layar: cari dan level, label grade dibaca orang', async () => {
        const w = await pasang();
        await w.find('[data-uji="cari"]').setValue('andi');
        expect(w.find('[data-uji="pilih-1"]').exists()).toBe(true);
        expect(w.find('[data-uji="pilih-2"]').exists()).toBe(false);

        await w.find('[data-uji="cari"]').setValue('');
        await w.find('[data-uji="saring-level"]').setValue('3');
        expect(w.find('[data-uji="pilih-1"]').exists()).toBe(false);
        expect(w.find('[data-uji="pilih-2"]').exists()).toBe(true);

        // Grade B_PLUS tampil sebagai B+, beserta jumlah kandidatnya, tanpa memanggil server lagi.
        expect(w.text()).toContain('B+ (1)');
        expect(w.text()).not.toContain('B_PLUS');
        expect(paApi.kandidatBalancing).toHaveBeenCalledTimes(1);
    });

    it('"pilih semua hasil filter" memilih yang layak di SEMUA halaman, bukan halaman yang terbuka saja', async () => {
        const banyak = Array.from({ length: 30 }, (_, i) => ({
            ...kandidat[0], participant_id: 100 + i, nama: `Orang ${i}`, kode_karyawan: `X${i}`,
        }));
        paApi.kandidatBalancing.mockResolvedValue([...banyak, kandidat[1]]);
        const w = await pasang();
        // Halaman pertama hanya 25 baris.
        expect(w.findAll('[data-uji^="pilih-1"]').length).toBeLessThanOrEqual(25);
        await w.find('[data-uji="pilih-semua-filter"]').trigger('click');
        expect(w.text()).toContain('30 dipilih');
    });

    describe('wizard empat langkah', () => {
        it('empat langkah terpisah, Buat hanya di langkah terakhir', async () => {
            const w = await pasang();
            expect(w.findAll('[data-uji$="-tab"]')).toHaveLength(4);
            expect(w.find('[data-uji="langkah-aktif"]').text()).toContain('Identitas Sesi');
            expect(tombol(w, 'Buat sesi')).toBeUndefined();
        });

        it('tidak bisa lanjut tanpa nama sesi — peringatan sebagai pesan, bukan banner', async () => {
            const w = await pasang();
            await lanjut(w);
            expect(hcisMessage.warning).toHaveBeenCalledWith('Isi nama sesi lebih dulu.', 'Belum bisa lanjut');
            expect(w.find('[data-uji="langkah-aktif"]').text()).toContain('Identitas Sesi');
        });

        it('peserta dan tim penilai wajib minimal satu', async () => {
            const w = await pasang();
            await w.find('[data-uji="nama-sesi"]').setValue('Balancing Staff');
            await lanjut(w);
            await lanjut(w);
            expect(hcisMessage.warning).toHaveBeenCalledWith('Pilih minimal 1 peserta.', 'Belum bisa lanjut');

            await w.find('[data-uji="pilih-1"]').setValue(true);
            await lanjut(w);
            expect(w.find('[data-uji="langkah-aktif"]').text()).toContain('Tim Penilai');
            await lanjut(w);
            expect(hcisMessage.warning).toHaveBeenCalledWith('Pilih minimal 1 anggota tim penilai.', 'Belum bisa lanjut');
            expect(w.find('[data-uji="langkah-aktif"]').text()).toContain('Tim Penilai');
        });

        it('membuat sesi: PIC pengguna, peserta terpilih, tim penilai tanpa isian peran', async () => {
            paApi.buatSesiBalancing.mockResolvedValue({ id: 9 });
            const w = await pasang();
            await w.find('[data-uji="nama-sesi"]').setValue('Balancing Staff');
            await lanjut(w);
            await w.find('[data-uji="pilih-1"]').setValue(true);
            await lanjut(w);
            // Tim penilai boleh di luar peserta siklus; perannya otomatis jabatan (tidak diisi).
            expect(w.text()).toContain('Dept Head Produksi');
            await w.find('[data-uji="forum-K9"]').setValue(true);
            await lanjut(w);

            const tinjau = w.find('[data-uji="panel-4"]').text();
            expect(tinjau).toContain('Balancing Staff');
            expect(tinjau).toContain('Andi');
            expect(tinjau).toContain('Citra');
            expect(tinjau).toContain('Dept Head Produksi');

            await tombol(w, 'Buat sesi').trigger('click');
            await flushPromises();
            expect(paApi.buatSesiBalancing).toHaveBeenCalledWith(5, {
                nama: 'Balancing Staff', pic_user_id: 622,
                forum: [{ kode_perusahaan: '001', kode_karyawan: 'K9' }],
                peserta_ids: [1],
            });
            expect(w.emitted('dibuat')[0][0]).toEqual({ id: 9 });
        });

        it('galat server tampil sebagai pesan dan dialog tetap terbuka', async () => {
            paApi.buatSesiBalancing.mockRejectedValue(new Error('Peserta berikut tidak bisa masuk sesi: Andi.'));
            const w = await pasang();
            await w.find('[data-uji="nama-sesi"]').setValue('Balancing Staff');
            await lanjut(w);
            await w.find('[data-uji="pilih-1"]').setValue(true);
            await lanjut(w);
            await w.find('[data-uji="forum-K9"]').setValue(true);
            await lanjut(w);
            await tombol(w, 'Buat sesi').trigger('click');
            await flushPromises();
            expect(hcisMessage.error).toHaveBeenCalledWith('Peserta berikut tidak bisa masuk sesi: Andi.', 'Sesi gagal dibuat');
            expect(w.emitted('dibuat')).toBeUndefined();
            expect(panelAktif(w)).toBeTruthy();
        });
    });
});
