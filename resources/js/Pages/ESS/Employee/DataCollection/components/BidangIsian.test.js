import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import BidangIsian from './BidangIsian.vue';

vi.mock('axios', () => ({ default: { get: vi.fn() } }));

const buat = (definisi, props = {}) =>
    mount(BidangIsian, {
        props: { kunci: 'uji', definisi, modelValue: '', ...props },
    });

const hp = { label: 'Nomor HP / WhatsApp', tipe: 'teks', format: 'hp', prefix: '8', prefix_simpan: '62', maks_digit: 13, min_digit: 9 };

const field = (w) => w.findComponent({ name: 'HcisField' });
// HcisSelect baru merender daftarnya saat dibuka (dan mem-teleport-nya ke
// body). Uji harus membukanya dulu — daftar yang belum pernah dibuka memang
// tidak ada di DOM mana pun.
const bukaDaftar = async (w) => {
    await w.find('.hcis-select__trigger').trigger('click');

    return w.findAllComponents({ name: 'HcisSelectOption' });
};
const kalender = (w) => w.findComponent({ name: 'HcisDatePicker' });

describe('BidangIsian', () => {
    // -------------------------------------------------------------------------
    // Dirakit dari komponen bersama, bukan dari kotak buatan sendiri.
    // -------------------------------------------------------------------------

    it('memakai HcisField untuk isian teks, beserta labelnya', () => {
        const w = buat({ label: 'Nama lengkap', tipe: 'teks' });

        expect(field(w).exists()).toBe(true);
        expect(field(w).props('label')).toBe('Nama lengkap');
        expect(w.find('input').exists()).toBe(true);
    });

    it('memakai HcisSelect untuk tipe pilihan', async () => {
        const w = buat({ label: 'Status', tipe: 'pilihan', pilihan: ['TK/0', 'K/1'] });

        expect((await bukaDaftar(w)).map((o) => o.props('value'))).toEqual(['TK/0', 'K/1']);
    });

    it('memakai label_pilihan sebagai kata yang dibaca orang', async () => {
        const w = buat({
            label: 'Jenis kelamin',
            tipe: 'pilihan',
            pilihan: ['L', 'P'],
            label_pilihan: { L: 'Laki-laki', P: 'Perempuan' },
        });

        const opsi = await bukaDaftar(w);

        expect(opsi.map((o) => o.props('label'))).toContain('Laki-laki');
        // Nilai yang tersimpan tetap istilah resminya.
        expect(opsi.map((o) => o.props('value'))).toEqual(['L', 'P']);
    });

    it('memakai pilihan dinamis untuk field bersumber server', async () => {
        const w = buat(
            { label: 'Departemen', tipe: 'pilihan', sumber_pilihan: 'divisi' },
            { pilihanDinamis: ['PRODUCTION', 'IT'] },
        );

        expect((await bukaDaftar(w)).map((o) => o.props('value'))).toEqual(['PRODUCTION', 'IT']);
    });

    it('memakai HcisDatePicker untuk tipe tanggal', () => {
        expect(kalender(buat({ label: 'Tanggal lahir', tipe: 'tanggal' })).exists()).toBe(true);
    });

    // Hari Ini/Besok/Akhir Bulan tak pernah jawaban wajar untuk data diri.
    it('menyembunyikan preset cepat pada kalender', () => {
        expect(kalender(buat({ label: 'Tanggal lahir', tipe: 'tanggal' })).props('showPresets')).toBe(false);
    });

    it("menampilkan tanggal dalam bentuk baca, mis. '24 Desember 2024'", () => {
        const w = buat({ label: 'Tanggal lahir', tipe: 'tanggal' }, { modelValue: '2024-12-24' });

        expect(kalender(w).props('format')).toBe('D MMMM YYYY');
        expect(w.find('.hcis-date-picker__input').element.value).toBe('24 Desember 2024');
    });

    it('meneruskan galat ke komponen, bukan menggambar kotak galatnya sendiri', () => {
        const w = buat({ label: 'NIK KTP', tipe: 'teks' }, { galat: 'Wajib 16 digit' });

        expect(field(w).props('error')).toBe('Wajib 16 digit');
    });

    it('meneruskan perubahan lewat update:modelValue', () => {
        const w = buat({ label: 'Email', tipe: 'teks' });

        field(w).vm.$emit('update:modelValue', 'a@b.co');

        expect(w.emitted('update:modelValue')[0]).toEqual(['a@b.co']);
    });

    // -------------------------------------------------------------------------
    // Batas ketikan — yang khas formulir ini, dan karena itu tetap tinggal.
    // -------------------------------------------------------------------------

    it('memasang batas ketikan nomor HP sesuai skema', () => {
        const w = buat(hp, { kunci: 'no_hp' });

        expect(field(w).props('maxLength')).toBe(13);
        expect(field(w).props('showCount')).toBe(true);
        expect(w.find('input').attributes('maxlength')).toBe('13');
    });

    it('menolak angka ke-14 pada nomor HP, termasuk saat ditempel', () => {
        const w = buat(hp, { kunci: 'no_hp' });

        field(w).vm.$emit('update:modelValue', '81234567890123456789');

        expect(w.emitted('update:modelValue').at(-1)[0]).toBe('628123456789012');
    });

    // Bentuk simpan '62…' bukan '08…': itu bentuk yang dipakai seluruh sistem
    // (Karyawan.HP, dan pengirim WhatsApp yang menormalkan apa pun ke '62…').
    // Sampai 4 Sep 2026 fitur ini menyimpan '08…' sendirian, sehingga prefill
    // harus membengkokkan data master supaya lolos aturan fiturnya sendiri.
    it('menyimpan nomor HP dengan awalan yang DIDEKLARASIKAN skema, bukan yang ditanam', () => {
        const w = buat(hp, { kunci: 'no_hp' });

        field(w).vm.$emit('update:modelValue', '81234567890');

        expect(w.emitted('update:modelValue').at(-1)[0]).toBe('6281234567890');

        // Awalannya dibaca, bukan ditulis di dalam kode: skema yang menyebut
        // awalan lain harus diikuti. Tanpa penjaga ini, '62' yang ditanam di
        // nilaiSimpanHp() akan tetap hijau — dan bentuk simpan kembali jadi
        // angka yang hidup di dua tempat dan bisa berselisih diam-diam.
        const lain = buat({ ...hp, prefix_simpan: '0' }, { kunci: 'no_hp' });

        field(lain).vm.$emit('update:modelValue', '81234567890');

        expect(lain.emitted('update:modelValue').at(-1)[0]).toBe('081234567890');
    });

    it('memasang batas ketikan NIK sesuai skema, bukan angka yang ditanam', () => {
        const w = buat({ label: 'Nomor KTP (NIK)', tipe: 'teks', format: 'nik', maks_digit: 16 }, { kunci: 'nik_ktp' });

        expect(field(w).props('maxLength')).toBe(16);

        field(w).vm.$emit('update:modelValue', '1234567890123456789');
        expect(w.emitted('update:modelValue').at(-1)[0]).toBe('1234567890123456');
    });

    it('tidak menebak sifat field dari kata di dalam labelnya', () => {
        // 'Tek-nik' mengandung 'nik'; tanpa deklarasi format, penebakan lama
        // akan menyulapnya jadi kotak NIK 16 digit.
        const w = buat({ label: 'Bidang Teknik', tipe: 'teks' }, { kunci: 'bidang_teknik' });

        expect(w.find('input').attributes('maxlength')).toBeUndefined();

        field(w).vm.$emit('update:modelValue', 'Teknik Mesin');
        expect(w.emitted('update:modelValue').at(-1)[0]).toBe('Teknik Mesin');
    });

    it('memperlihatkan hitungan angka supaya berhentinya tidak terasa mendadak', () => {
        const w = buat(hp, { kunci: 'no_hp', modelValue: '628123456789012' });

        expect(w.text().replace(/\s/g, '')).toContain('13/13');
    });

    it('memberi tahu awalan yang salah seketika, tanpa mencereweti nomor yang baru separuh', () => {
        expect(field(buat(hp, { kunci: 'no_hp', modelValue: '0212333' })).props('error')).toMatch(/diawali 8/i);
        expect(field(buat(hp, { kunci: 'no_hp', modelValue: '0812' })).props('error')).toBe('');
    });

    it('tidak membiarkan tanggal di luar jendela dipilih sama sekali', () => {
        // Tanpa usia_min/usia_maks — persis bentuk yang dikirim server sejak
        // batas usia dilepas. Jendelanya jatuh ke baku 100 tahun di
        // batasTanggalLahir(), dan tepi "hari ini" tetap sama dengan aturan
        // server 'before_or_equal:today'.
        const w = buat(
            { label: 'Tanggal Lahir', tipe: 'tanggal', format: 'tanggal_lahir' },
            { kunci: 'tanggal_lahir' },
        );

        const terlarang = kalender(w).props('disabledDate');

        expect(typeof terlarang).toBe('function');
        expect(terlarang(new Date(Date.now() + 86400000))).toBe(true);   // besok
        expect(terlarang(new Date('1995-04-17T00:00:00'))).toBe(false);  // usia wajar
        expect(terlarang(new Date('1900-01-01T00:00:00'))).toBe(true);   // di luar jendela 100 tahun
    });

    it('memakai ikon yang dideklarasikan skema', () => {
        const w = buat({ label: 'Status Pernikahan', tipe: 'pilihan', pilihan: ['Menikah'], ikon: 'bi bi-people' }, { kunci: 'status_pernikahan' });

        expect(w.findComponent({ name: 'HcisIcon' }).props('name')).toBe('bi bi-people');
    });

    // -------------------------------------------------------------------------
    // Atasan PHL: dulu jatuh ke kotak teks bebas karena tipe 'cari_karyawan'
    // tidak dikenal, sehingga apa pun yang diketik tersimpan sebagai atasan.
    // -------------------------------------------------------------------------
    describe('cari_karyawan', () => {
        const atasan = { label: 'Supervisor / Atasan Langsung', tipe: 'cari_karyawan' };
        const pemilih = (w) => w.findComponent({ name: 'HcisSelect' });

        afterEach(() => {
            vi.useRealTimers();
            axios.get.mockReset();
        });

        it('dirender sebagai pemilih yang bisa dicari, bukan kotak teks', () => {
            const w = buat(atasan, { kunci: 'atasan_spv' });

            expect(pemilih(w).exists()).toBe(true);
            expect(pemilih(w).props('filterable')).toBe(true);
            expect(field(w).exists()).toBe(false);
        });

        it('tidak memanggil server untuk ketikan kurang dari dua huruf', async () => {
            vi.useFakeTimers();
            const w = buat(atasan, { kunci: 'atasan_spv' });

            pemilih(w).vm.$emit('search', 'a');
            await vi.advanceTimersByTimeAsync(400);

            expect(axios.get).not.toHaveBeenCalled();
        });

        it('mencari ke server lalu memancarkan kode beserta namanya saat dipilih', async () => {
            vi.useFakeTimers();
            axios.get.mockResolvedValue({
                data: { data: [{ kode: 'K123', nama: 'Budi Santoso', keterangan: 'IT Support - Staff' }] },
            });
            const w = buat(atasan, { kunci: 'atasan_spv' });

            pemilih(w).vm.$emit('search', 'budi');
            await vi.advanceTimersByTimeAsync(400);
            await w.vm.$nextTick();

            expect(axios.get).toHaveBeenCalledWith('/pengumpulan-data/cari-karyawan', { params: { q: 'budi' } });
            const opsi = w.findAllComponents({ name: 'HcisSelectOption' }).find((o) => o.props('value') === 'K123');
            // Yang dibaca orang nama dan unitnya; kode tidak dihafal siapa pun.
            expect(opsi.props('label')).toBe('Budi Santoso');
            expect(opsi.props('description')).toBe('IT Support - Staff');

            pemilih(w).vm.$emit('update:modelValue', 'K123');

            expect(w.emitted('nama').at(-1)).toEqual(['K123', { nama: 'Budi Santoso', keterangan: 'IT Support - Staff' }]);
            expect(w.emitted('update:modelValue').at(-1)).toEqual(['K123']);
        });

        it('menampilkan nama untuk nilai yang sudah terisi dari draf', () => {
            const w = buat(atasan, {
                kunci: 'atasan_spv',
                modelValue: 'K9',
                karyawanTerpilih: { nama: 'Siti Aminah', keterangan: 'Finance - Supervisor' },
            });

            const opsi = w.findAllComponents({ name: 'HcisSelectOption' });
            expect(opsi.map((o) => o.props('label'))).toContain('Siti Aminah');
            expect(opsi.map((o) => o.props('description'))).toContain('Finance - Supervisor');
        });

        it('menampilkan penanda memuat selama permintaan berjalan, lalu melepasnya', async () => {
            vi.useFakeTimers();
            let selesaikan;
            axios.get.mockReturnValue(new Promise((ok) => { selesaikan = ok; }));
            // Daftar HcisSelect di-teleport ke body; penandanya dicari di sana.
            const w = mount(BidangIsian, {
                props: { kunci: 'atasan_spv', definisi: atasan, modelValue: '' },
                attachTo: document.body,
            });
            const berputar = () => document.querySelector('.pd-cari__spinner') !== null;

            pemilih(w).vm.$emit('search', 'budi');
            await w.vm.$nextTick();
            // Seketika, sebelum jeda ketik 300 ms habis.
            expect(berputar()).toBe(true);

            await vi.advanceTimersByTimeAsync(400);
            expect(berputar()).toBe(true);

            selesaikan({ data: { data: [{ kode: 'K1', nama: 'Budi', keterangan: '' }] } });
            await vi.advanceTimersByTimeAsync(0);
            await w.vm.$nextTick();

            expect(berputar()).toBe(false);
            w.unmount();
        });
    });
});
