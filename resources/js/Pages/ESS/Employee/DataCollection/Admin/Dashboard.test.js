import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Dashboard from './Dashboard.vue';
import HcisFilterField from '@/components/ui/HcisFilterField.vue';

const get = vi.fn();
const post = vi.fn();

vi.mock('axios', () => ({
    default: {
        get: (...a) => get(...a),
        post: (...a) => post(...a),
    },
}));

// router.reload() menyegarkan prop Inertia setelah cakupan berubah. Tanpa
// tiruan ini ia menembak permintaan sungguhan dari dalam uji komponen.
const reload = vi.fn();
vi.mock('@inertiajs/vue3', () => ({ router: { reload: (...a) => reload(...a) } }));

const ringkasan = {
    total: 100,
    sudah: 40,
    belum: 55,
    terkunci: 12,
    dikecualikan: 5,
    persen: 40,
    kampanye_aktif: true,
    cakupan_lokasi: [],
    di_luar_cakupan: { total: 0, tanpa_lokasi: 0 },
    per_departemen: [{ departemen: 'IT', total: 10, sudah: 4, belum: 6 }],
};

const daftar = {
    items: [
        {
            Kode_Karyawan: 'A2',
            nama: 'Adrian',
            nik_karyawan: '123',
            departemen: 'IT',
            status: 'SELESAI',
            jumlah_tunda: 1,
            selesai_at: null,
            pernah_dibuka_kunci: false,
        },
    ],
    total: 1,
    halaman: 1,
    per_halaman: 25,
    total_halaman: 1,
};

const skema = {
    versi: 1,
    langkah: [{ kunci: 'identitas', judul: 'Identitas', field: ['nama_lengkap'] }],
    field: { nama_lengkap: { label: 'Nama lengkap', tipe: 'teks' } },
};

const buat = (gantiProps = {}) =>
    mount(Dashboard, {
        props: {
            ringkasan,
            daftar,
            pilihanDepartemen: ['IT'],
            pilihanLokasi: ['EMI', 'JKT'],
            skema,
            pengumuman: { judul: 'J', teks: 'T', label_tombol_isi: 'Isi', label_tombol_tunda: 'Nanti' },
            ...gantiProps,
        },
    });

const kotakCakupan = (w, lokasi) =>
    w.findAllComponents({ name: 'HcisCheckbox' }).find((k) => k.props('value') === lokasi);

describe('Dashboard admin pengumpulan data', () => {
    beforeEach(() => {
        get.mockReset();
        post.mockReset();
        reload.mockReset();
        get.mockResolvedValue({ data: ringkasan });
    });

    describe('cakupan lokasi', () => {
        it('menyatakan kampanye terbuka untuk semua saat tidak ada lokasi tercentang', () => {
            const w = buat();

            expect(w.text()).toContain('Seluruh lokasi');
            expect(kotakCakupan(w, 'EMI')).toBeTruthy();
            expect(kotakCakupan(w, 'JKT')).toBeTruthy();
        });

        it('menawarkan lokasi yang belum dibuka, bukan hanya yang sudah', () => {
            // Kotak centang yang hanya memuat cakupan berjalan membuat lokasi
            // baru mustahil dibuka dari layar ini.
            const w = buat({ ringkasan: { ...ringkasan, cakupan_lokasi: ['EMI'] } });

            expect(kotakCakupan(w, 'JKT')).toBeTruthy();
            expect(w.text()).toContain('1 lokasi dibuka');
        });

        it('tombol simpan mati sampai centangnya benar-benar berubah', async () => {
            // Penyegaran ringkasan berjalan sendiri saat komponen lahir; tiruannya
            // harus sepakat dengan prop, kalau tidak yang diuji justru penimpaan.
            const berlaku = { ...ringkasan, cakupan_lokasi: ['EMI'] };
            get.mockResolvedValue({ data: berlaku });

            const w = buat({ ringkasan: berlaku });
            await flushPromises();

            const tombol = () =>
                w.findAllComponents({ name: 'HcisButton' }).find((b) => b.props('label') === 'Simpan cakupan');

            expect(tombol().props('disabled')).toBe(true);

            kotakCakupan(w, 'JKT').vm.$emit('update:modelValue', ['EMI', 'JKT']);
            await flushPromises();

            expect(tombol().props('disabled')).toBe(false);
        });

        it('menyimpan mengirim daftar lokasi tercentang dan menyegarkan daftar', async () => {
            const w = buat();
            await flushPromises();
            post.mockResolvedValue({
                data: { ok: true, cakupan_lokasi: ['EMI'], ringkasan: { ...ringkasan, cakupan_lokasi: ['EMI'] } },
            });

            kotakCakupan(w, 'EMI').vm.$emit('update:modelValue', ['EMI']);
            await flushPromises();

            w.findAllComponents({ name: 'HcisButton' })
                .find((b) => b.props('label') === 'Simpan cakupan')
                .vm.$emit('click');
            await flushPromises();

            expect(post).toHaveBeenCalledWith('/admin/pengumpulan-data/cakupan', { lokasi: ['EMI'] });
            expect(reload).toHaveBeenCalled();
            expect(w.text()).toContain('1 lokasi dibuka');
        });

        it('memperingatkan karyawan tanpa lokasi HANYA saat cakupan dipakai', () => {
            const pesan = 'tidak punya lokasi kerja di';

            expect(
                buat({ ringkasan: { ...ringkasan, di_luar_cakupan: { total: 0, tanpa_lokasi: 7 } } }).text(),
            ).not.toContain(pesan);

            expect(
                buat({
                    ringkasan: {
                        ...ringkasan,
                        cakupan_lokasi: ['EMI'],
                        di_luar_cakupan: { total: 20, tanpa_lokasi: 7 },
                    },
                }).text(),
            ).toContain(pesan);
        });
    });

    it('menampilkan angka ringkasan dan daftar karyawan', () => {
        const w = buat();

        expect(w.text()).toContain('40');
        expect(w.text()).toContain('Adrian');
        // Status dibacakan sebagai kalimat, bukan kode mentah 'SELESAI'.
        expect(w.text()).toContain('Sudah mengisi');
        expect(w.text()).not.toContain('SELESAI');
    });

    it('menyembunyikan catatan kampanye mati saat kampanye menyala', () => {
        expect(buat().text()).not.toContain('Kampanye dimatikan');
    });

    it('memuat pemeriksa kualitas hanya saat tabnya dibuka', async () => {
        const w = buat();
        await flushPromises();
        get.mockResolvedValue({ data: { temuan: [{ jenis: 'NIK_KEMBAR', nilai: '321', karyawan: [{ kode: 'A2', nama: 'Adrian' }] }] } });

        const pemanggilanSebelum = get.mock.calls.filter((c) => c[0].includes('/kualitas')).length;
        expect(pemanggilanSebelum).toBe(0);

        w.findComponent({ name: 'HcisTabs' }).vm.$emit('update:modelValue', 'kualitas');
        await flushPromises();

        expect(get.mock.calls.some((c) => c[0].includes('/kualitas'))).toBe(true);
        expect(w.text()).toContain('NIK dipakai lebih dari satu orang');
    });

    it('sakelar mengirim kebalikan dari keadaan sekarang', async () => {
        const w = buat();
        await flushPromises();
        post.mockResolvedValue({ data: { ok: true, aktif: false } });

        w.findComponent({ name: 'HcisSwitch' }).vm.$emit('update:modelValue', false);
        await flushPromises();

        expect(post).toHaveBeenCalledWith('/admin/pengumpulan-data/sakelar', { aktif: false });
        expect(w.text()).toContain('Kampanye dimatikan');
    });

    it('menyusun kartu statistik dengan lebar kolom yang dikenal komponennya', () => {
        // HcisStatGroup hanya mengenal 1-4 atau 'auto'. Angka di luar itu lolos
        // diam-diam sebagai kelas tanpa aturan CSS, dan kartunya bertumpuk satu
        // per baris — cacat yang hanya terlihat di layar, bukan di uji lama.
        const grup = buat().findComponent({ name: 'HcisStatGroup' });

        expect(['1', '2', '3', '4', 'auto', 1, 2, 3, 4]).toContain(grup.props('cols'));
        expect(grup.props('stats')).toHaveLength(5);
    });

    it('memberi HcisTabs kunci yang benar-benar dikenalinya', () => {
        // Komponen mencocokkan tab lewat 'key'. Memberi 'value' saja lolos tanpa
        // galat apa pun, lalu isi tab kosong di layar — cacat yang tidak akan
        // pernah terlihat dari uji yang hanya memanggil pilihTab() langsung.
        const daftar = buat().findComponent({ name: 'HcisTabs' }).props('tabs');

        expect(daftar.map((t) => t.key)).toEqual(['daftar', 'departemen', 'kualitas', 'audit']);
        expect(daftar.every((t) => t.key !== undefined)).toBe(true);
    });

    it('berpindah halaman benar-benar meminta halaman itu ke server', async () => {
        const w = buat();
        await flushPromises();
        get.mockResolvedValue({ data: { ...daftar, halaman: 3 } });

        w.findComponent({ name: 'HcisDataTable' }).vm.$emit('page-change', 3);
        await flushPromises();

        const panggilan = get.mock.calls.find((c) => c[0].includes('/daftar'));
        expect(panggilan).toBeTruthy();
        expect(panggilan[1].params.halaman).toBe(3);
    });

    describe('tombol pengecualian', () => {
        const denganStatus = (status) =>
            buat({ daftar: { ...daftar, items: [{ ...daftar.items[0], status }] } });

        const tombolAksi = (w) =>
            w
                .findAllComponents({ name: 'HcisButton' })
                .map((b) => b.props('label'))
                .filter((l) => ['Kecualikan', 'Ikutsertakan'].includes(l));

        it('menyebut tindakan yang akan terjadi, bukan nama fiturnya', () => {
            // Label tetap membuat tombol pada orang yang SUDAH dikecualikan
            // terbaca seperti akan mengecualikannya lagi.
            expect(tombolAksi(denganStatus('BELUM'))).toEqual(['Kecualikan']);
            expect(tombolAksi(denganStatus('DIKECUALIKAN'))).toEqual(['Ikutsertakan']);
        });

        it('membatalkan pengecualian menanyakan dulu, tidak langsung jalan', async () => {
            const w = denganStatus('DIKECUALIKAN');
            await flushPromises();

            const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);

            w.findAllComponents({ name: 'HcisButton' })
                .find((b) => b.props('label') === 'Ikutsertakan')
                .vm.$emit('click');
            await flushPromises();

            expect(confirm).toHaveBeenCalled();
            expect(post).not.toHaveBeenCalled();

            confirm.mockReturnValue(true);
            post.mockResolvedValue({ data: { ok: true, ringkasan } });
            get.mockResolvedValue({ data: daftar });

            w.findAllComponents({ name: 'HcisButton' })
                .find((b) => b.props('label') === 'Ikutsertakan')
                .vm.$emit('click');
            await flushPromises();

            expect(post).toHaveBeenCalledWith('/admin/pengumpulan-data/pengecualian', {
                kode_karyawan: 'A2',
                dikecualikan: false,
                alasan: null,
            });

            confirm.mockRestore();
        });
    });

    it('setiap saringan benar-benar dirender sebagai dropdown, bukan kotak teks', () => {
        // Cacat yang pernah lolos: 'options' diisi tapi 'type' lupa ditulis.
        // HcisFilterField memetakan tipe yang hilang ke 'input', jadi ketiga
        // saringan tampil sebagai kotak teks kosong yang tidak pernah
        // menawarkan satu pilihan pun — dan tidak ada galat apa pun.
        // Memeriksa konfigurasinya saja tidak cukup; yang diperiksa di sini
        // adalah apa yang benar-benar lahir di layar.
        const daftarSaringan = buat().findComponent({ name: 'HcisDataTable' }).props('filters');

        expect(daftarSaringan.map((f) => f.key)).toEqual(['status', 'departemen', 'lokasi']);

        daftarSaringan.forEach((f) => {
            const kolom = mount(HcisFilterField, { props: { filter: f, modelValue: '' } });

            expect(kolom.findComponent({ name: 'HcisSelect' }).exists()).toBe(true);
            expect(kolom.findComponent({ name: 'HcisField' }).exists()).toBe(false);
            expect(kolom.text()).not.toContain('tidak dikenali');
            expect(f.options.length).toBeGreaterThan(1);
        });
    });

    it('dropdown saringan bisa dicari, bukan daftar panjang yang harus digulir', () => {
        // Lokasi dan departemen bisa berjumlah puluhan. HcisSelect menyalakan
        // pencarian selama 'filterable' tidak dimatikan — penjaga ini ada supaya
        // tidak ada yang mematikannya belakangan tanpa sadar.
        const daftarSaringan = buat().findComponent({ name: 'HcisDataTable' }).props('filters');

        daftarSaringan.forEach((f) => {
            const pilihan = mount(HcisFilterField, { props: { filter: f, modelValue: '' } })
                .findComponent({ name: 'HcisSelect' });

            expect(pilihan.props('filterable')).toBe(true);
        });
    });

    it('menyaring daftar per lokasi kerja tanpa menyentuh cakupan', async () => {
        const w = buat();
        await flushPromises();
        get.mockResolvedValue({ data: daftar });

        const tabel = w.findComponent({ name: 'HcisDataTable' });
        expect(tabel.props('filters').map((f) => f.key)).toContain('lokasi');

        tabel.vm.$emit('filter-change', { key: 'lokasi', value: 'EMI' });
        await flushPromises();

        const panggilan = get.mock.calls.filter((c) => c[0].includes('/daftar')).at(-1);
        expect(panggilan[1].params.lokasi).toBe('EMI');
        // Saringan tampilan tidak boleh diam-diam menyimpan cakupan baru.
        expect(post).not.toHaveBeenCalledWith('/admin/pengumpulan-data/cakupan', expect.anything());
    });

    it('mengubah jumlah baris kembali ke halaman satu', async () => {
        const w = buat();
        await flushPromises();
        get.mockResolvedValue({ data: daftar });

        w.findComponent({ name: 'HcisDataTable' }).vm.$emit('size-change', 50);
        await flushPromises();

        const panggilan = get.mock.calls.filter((c) => c[0].includes('/daftar')).at(-1);
        expect(panggilan[1].params.per_halaman).toBe(50);
        expect(panggilan[1].params.halaman).toBe(1);
    });
});
