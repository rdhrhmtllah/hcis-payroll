import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Dialog from './OrgEksporDialog.vue';

const stubs = {
    HcisDialog: { props: ['modelValue', 'title'], template: '<div v-if="modelValue" class="dialog"><slot /><slot name="footer" /></div>' },
    HcisButton: { props: ['label', 'disabled', 'loading'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>' },
    HcisAlert: { props: ['title'], template: '<div class="alert">{{ title }}<slot /></div>' },
    HcisBadge: { props: ['label'], emits: ['remove'], template: '<span class="badge">{{ label }}</span>' },
    HcisCheckbox: { props: ['modelValue', 'value', 'label', 'indeterminate'], emits: ['update:modelValue'], template: '<label class="cek">{{ label }}</label>' },
    HcisSwitch: { props: ['modelValue'], emits: ['update:modelValue'], template: '<i class="switch" />' },
    HcisField: { props: ['modelValue'], emits: ['update:modelValue'], template: '<input class="cari" :value="modelValue" />' },
    HcisSelect: { props: ['modelValue', 'multiple'], emits: ['update:modelValue'], template: '<div class="sel"><slot /></div>' },
    HcisSelectOption: { props: ['value', 'label'], template: '<div class="opsi" :data-nilai="value">{{ label }}</div>' },
    HcisPagination: { props: ['total', 'currentPage'], emits: ['update:currentPage'], template: '<nav class="halaman" />' },
    HcisProgress: { props: ['value', 'label', 'subtitle'], template: '<div class="progres">{{ label }} {{ value }} {{ subtitle }}</div>' },
    HcisTable: { props: ['rows', 'selectedKeys', 'columns'], emits: ['update:selectedKeys'], template: '<div class="tabel"><div v-for="r in rows" :key="r.kunci" class="baris">{{ r.nama }} <slot name="cell-tgl_masuk" :row="r" /></div></div>' },
};
const JENIS = [{ id: 9, nama: 'Atasan', jumlah: 10 }, { id: 1010, nama: 'Bawahan', jumlah: 10 }, { id: 6, nama: 'Rekan Kerja', jumlah: 5 }];
const ORANG = [
    { kode_perusahaan: '001', kode_karyawan: 'H45', nama: 'Ridho', nama_level: 'OFFICER', divisi: 'IT', tgl_masuk: '2025-01-14 00:00:00.000' },
    { kode_perusahaan: '001', kode_karyawan: 'A4', nama: 'Aji', nama_level: 'OFFICER', divisi: 'IT' },
];
const balasCari = (baris, total = baris.length) => ({ ok: true, json: () => Promise.resolve({ result: { baris, total, dipangkas: total > baris.length } }) });
const balasMulai = (result) => ({ ok: true, json: () => Promise.resolve({ result }) });
const balasUnduh = () => ({ ok: true, blob: () => Promise.resolve(new Blob(['x'])), headers: { get: (h) => (h === 'X-Jumlah-Baris' ? '7' : 'attachment; filename="Relasi.xlsx"') } });

let fetchMock;
beforeEach(() => {
    vi.useFakeTimers();
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
    vi.stubGlobal('URL', { ...URL, createObjectURL: vi.fn(() => 'blob:x'), revokeObjectURL: vi.fn() });
});
afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

async function pasang(awal = {}) {
    fetchMock.mockResolvedValueOnce(balasCari(ORANG, 2));
    const w = mount(Dialog, {
        props: { modelValue: true, jenis: JENIS, daftarDivisi: ['IT', 'HR'], daftarDepartemen: ['DEV'], daftarLevel: [{ id: 4, nama: 'OFFICER' }], awal },
        global: { stubs },
    });
    await vi.advanceTimersByTimeAsync(350);
    await flushPromises();
    return w;
}
const tombol = (w, awalan) => w.findAll('button').find((b) => b.text().startsWith(awalan));
const urlTerakhir = () => decodeURIComponent(fetchMock.mock.calls.at(-1)[0]);

describe('OrgEksporDialog', () => {
    it('saat dibuka memuat karyawan dari server dan semua kategori tercentang', async () => {
        const w = await pasang({ tipe_id: 'semua' });
        expect(fetchMock.mock.calls[0][0]).toContain('/system/organisasi/relasi/cari-karyawan?');
        expect(fetchMock).toHaveBeenCalledTimes(1); // sekali, bukan dua kali
        expect(w.findAll('.baris')).toHaveLength(2);
        expect(w.findAll('.baris')[0].text()).toContain('14 Jan 2025'); // kolom Tgl Masuk
        expect(w.findComponent(stubs.HcisTable).props('columns').map((c) => c.label)).toContain('Tgl Masuk');
        expect(w.find('[data-uji="ro-export-ringkas"]').text()).toContain('Semua karyawan');
        expect(tombol(w, 'Unduh Excel').attributes('disabled')).toBeUndefined();
    });

    it('saringan lanjutan dikirim ke server; tanpa centang, unduhan = semua yang cocok saringan', async () => {
        const w = await pasang();
        const [divisi, , level] = w.findAllComponents(stubs.HcisSelect);
        fetchMock.mockResolvedValueOnce(balasCari([ORANG[0]], 37));
        divisi.vm.$emit('update:modelValue', ['IT']);
        level.vm.$emit('update:modelValue', ['4']);
        await flushPromises();
        vi.advanceTimersByTime(350);
        await flushPromises();
        expect(urlTerakhir()).toContain('divisi[]=IT');
        expect(urlTerakhir()).toContain('id_level[]=4');
        expect(w.find('[data-uji="ro-export-ringkas"]').text()).toContain('Semua 37 karyawan yang cocok saringan');
        expect(w.find('[data-uji="ro-export-info"]').text()).toContain('37 karyawan cocok · halaman 1 dari 1');

        fetchMock.mockResolvedValueOnce(balasMulai({ mode: 'langsung' })).mockResolvedValueOnce(balasUnduh());
        await tombol(w, 'Unduh Excel').trigger('click');
        await flushPromises();
        const [alamat, opsi] = fetchMock.mock.calls.at(-1);
        expect(alamat).toBe('/system/organisasi/relasi/ekspor');
        expect(opsi.method).toBe('POST');
        const isi = JSON.parse(opsi.body);
        expect(isi.subjek_divisi).toEqual(['IT']);
        expect(isi.subjek_level).toEqual(['4']);
        expect(isi.karyawan).toBeUndefined();
    });

    it('"Centang halaman ini" memilih baris di layar, lalu unduhan membawa karyawan[]; Kosongkan melepasnya', async () => {
        const w = await pasang();
        await tombol(w, 'Centang halaman ini').trigger('click');
        expect(w.find('[data-uji="ro-export-ringkas"]').text()).toContain('2 karyawan dipilih');

        await tombol(w, 'Kosongkan').trigger('click');
        expect(w.find('[data-uji="ro-export-ringkas"]').text()).toContain('Semua karyawan');

        w.findComponent(stubs.HcisTable).vm.$emit('update:selectedKeys', ['001|H45']);
        await flushPromises();
        fetchMock.mockResolvedValueOnce(balasMulai({ mode: 'langsung' })).mockResolvedValueOnce(balasUnduh());
        await tombol(w, 'Unduh Excel').trigger('click');
        await flushPromises();
        expect(JSON.parse(fetchMock.mock.calls.at(-1)[1].body).karyawan).toEqual(['001|H45']);
        expect(w.emitted('terunduh')[0]).toEqual([7]);
    });

    it('kategori awal mengikuti saringan halaman bila itu id kategori', async () => {
        const w = await pasang({ tipe_id: 9 });
        expect(w.findAllComponents(stubs.HcisCheckbox).find((c) => c.props('label') === 'Pilih semua').props('indeterminate')).toBe(true);
        fetchMock.mockResolvedValueOnce({ ok: false, status: 422, json: () => Promise.resolve({ message: 'Melebihi 50.000 baris.' }) });
        await tombol(w, 'Unduh Excel').trigger('click');
        await flushPromises();
        const kirim = JSON.parse(fetchMock.mock.calls.at(-1)[1].body);
        expect(fetchMock.mock.calls.at(-1)[0]).toBe('/system/organisasi/relasi/ekspor/mulai');
        expect(kirim.tipe_ids).toEqual([9]);
        expect(w.text()).toContain('Melebihi 50.000 baris.');
    });

    it('mode latar: progres dipantau sampai siap, lalu diunduh lewat Activity Center', async () => {
        const w = await pasang();
        const klik = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});
        fetchMock
            .mockResolvedValueOnce(balasMulai({ mode: 'latar', export_id: 'ro-exp-1' }))
            .mockResolvedValueOnce(balasMulai({ status: 'processing', percent: 40, processed_records: 400, total_records: 1000, label: 'Relasi Organisasi' }))
            .mockResolvedValueOnce(balasMulai({ status: 'ready', percent: 100, processed_records: 1000, total_records: 1000, label: 'Relasi Organisasi' }));
        await tombol(w, 'Unduh Excel').trigger('click');
        await flushPromises();
        expect(w.find('[data-uji="ro-export-proses"]').text()).toContain('400 / 1.000');
        expect(w.find('[data-uji="ro-export-proses"]').text()).toContain('Membaca relasi per batch');
        expect(tombol(w, 'Tutup (lanjut di latar)')).toBeTruthy();

        await vi.advanceTimersByTimeAsync(1600);
        await flushPromises();
        expect(fetchMock.mock.calls.at(-1)[0]).toBe('/system/organisasi/relasi/ekspor/ro-exp-1/status');
        expect(w.find('[data-uji="ro-export-proses"]').text()).toContain('Selesai');
        expect(klik).toHaveBeenCalled();
        expect(w.emitted('terunduh')[0]).toEqual([1000]);
        klik.mockRestore();
    });

    it('daftar berhalaman 50 dan "Centang semua yang cocok" mencentang seluruh yang cocok, bukan hanya yang termuat', async () => {
        fetchMock.mockResolvedValueOnce(balasCari(ORANG, 958));
        const w = mount(Dialog, { props: { modelValue: true, jenis: JENIS, daftarDivisi: [], daftarDepartemen: [], daftarLevel: [] }, global: { stubs } });
        await vi.advanceTimersByTimeAsync(350);
        await flushPromises();
        expect(decodeURIComponent(fetchMock.mock.calls[0][0])).toContain('limit=50');
        expect(decodeURIComponent(fetchMock.mock.calls[0][0])).toContain('halaman=1');
        expect(w.find('[data-uji="ro-export-info"]').text()).toContain('958 karyawan cocok · halaman 1 dari 20');

        fetchMock.mockResolvedValueOnce(balasCari(ORANG, 958));
        w.findComponent(stubs.HcisPagination).vm.$emit('update:currentPage', 3);
        await flushPromises();
        expect(decodeURIComponent(fetchMock.mock.calls.at(-1)[0])).toContain('halaman=3');

        const semua = Array.from({ length: 958 }, (_, i) => ({ kode_perusahaan: '001', kode_karyawan: `K${i}`, nama: `Orang ${i}` }));
        fetchMock.mockResolvedValueOnce(balasCari(semua, 958));
        await tombol(w, 'Centang semua yang cocok').trigger('click');
        await flushPromises();
        expect(fetchMock.mock.calls.at(-1)[0]).toContain('/system/organisasi/relasi/cari-karyawan/semua?');
        expect(w.find('[data-uji="ro-export-ringkas"]').text()).toContain('958 karyawan dipilih');
    });
});
